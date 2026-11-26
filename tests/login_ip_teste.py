#!/usr/bin/env python3
# login_ip_teste.py :: Limite de contas logadas ao mesmo tempo por IP (login.php, control.loginip_max; docs/SEGURANCA.md)
# e semântica do formulário de login para os gerenciadores de senha.
# Uso: python3 login_ip_teste.py [url=http://127.0.0.1:8080] [pasta_do_jogo=/var/www/narutorpg]
# Cria as contas lipteste1..8 (mkuser.php, senha "senha123"), mexe em control.loginip_max e restaura o valor no fim.
import sys, subprocess, threading, http.client, urllib.parse, re

URL = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8080"
JOGO = sys.argv[2] if len(sys.argv) > 2 else "/var/www/narutorpg"
AQUI = __file__.rsplit("/", 1)[0] or "."
u = urllib.parse.urlparse(URL)
problemas = []

def sql(q):
    return subprocess.run(["mysql", "-N", "-B", "narutorpg", "-e", q], capture_output=True, text=True, check=True).stdout.strip()

def ok(cond, msg):
    print(("ok   " if cond else "FALHA") + " " + msg)
    if not cond: problemas.append(msg)

def req(metodo, caminho, corpo=None, cookie=None):
    c = http.client.HTTPConnection(u.hostname, u.port or 80, timeout=30)
    h = {}
    if corpo is not None: h["Content-Type"] = "application/x-www-form-urlencoded"
    # IP local (127.0.0.1) cai em inglês pela detecção de idioma por IP (lib.php idioma()): o teste escolhe pt pelo cookie.
    h["Cookie"] = "idioma=pt" + ("; " + cookie if cookie else "")
    c.request(metodo, caminho, body=corpo, headers=h)
    r = c.getresponse(); dados = r.read().decode("utf8", "replace")
    return r.status, r.getheader("Location") or "", r.getheader("Set-Cookie") or "", dados

def login(nome):
    st, loc, ck, _ = req("POST", "/login.php?do=login", urllib.parse.urlencode({"username": nome, "password": "senha123", "submit": ""}))
    cookie = ck.split(";", 1)[0] if loc == "index.php" else None
    return loc == "index.php", loc, cookie

def logout(cookie):
    req("GET", "/login.php?do=logout", cookie=cookie)

contas = ["lipteste%d" % i for i in range(1, 9)]
for n in contas:
    subprocess.run(["php", AQUI + "/mkuser.php", JOGO, n], capture_output=True, check=True)
lista = ",".join("'%s'" % n for n in contas)
antigo = sql("SELECT loginip_max FROM naruto_control WHERE id=1")
# Começa limpo: ninguém mais ocupa vaga do 127.0.0.1 (outras contas de teste ficam com o loginip vazio).
sql("UPDATE naruto_users SET loginip='' WHERE loginip IN ('127.0.0.1','::1')")
sql("UPDATE naruto_users SET onlinetime=NOW() - INTERVAL 1 HOUR, loginip='' WHERE username IN (%s)" % lista)
sql("DELETE FROM naruto_loginfail")
try:
    sql("UPDATE naruto_control SET loginip_max=3 WHERE id=1")
    cookies = {}
    for n in contas[:3]:
        s, loc, cookies[n] = login(n)
        ok(s, "login %s (vaga livre)" % n)
    ip = sql("SELECT loginip FROM naruto_users WHERE username='lipteste1'")
    ok(ip in ("127.0.0.1", "::1"), "users.loginip gravado no login (%s)" % ip)
    s, loc, _ = login(contas[3])
    ok(not s, "4a conta do mesmo IP recusada")
    ok("conteudo=" in loc and "3" in urllib.parse.unquote(loc), "mensagem clara na recusa: %s" % urllib.parse.unquote(loc)[:120])
    _, _, _, pag = req("GET", "/" + loc if not loc.startswith("/") else loc)
    ok("máximo permitido" in pag, "mensagem aparece na página de login (pt)")
    s, _, cookies["lipteste1"] = login("lipteste1")
    ok(s, "a mesma conta pode entrar de novo (não gasta outra vaga)")

    logout(cookies["lipteste1"])
    ok(sql("SELECT loginip FROM naruto_users WHERE username='lipteste1'") == "", "logout apaga o loginip")
    s, _, cookies["lipteste4"] = login(contas[3])
    ok(s, "depois do logout a vaga fica livre")
    s, _, _ = login(contas[4])
    ok(not s, "limite cheio de novo")

    sql("UPDATE naruto_users SET onlinetime=NOW() - INTERVAL 11 MINUTE WHERE username='lipteste2'")
    s, _, _ = login(contas[4])
    ok(s, "sessão parada há mais de 10 minutos libera a vaga")

    sql("UPDATE naruto_users SET loginip='203.0.113.9' WHERE username='lipteste3'")
    s, _, _ = login(contas[5])
    ok(s, "conta logada de outro IP não ocupa vaga deste IP")

    sql("UPDATE naruto_control SET loginip_max=0 WHERE id=1")
    s1 = login(contas[6])[0]; s2 = login(contas[7])[0]
    ok(s1 and s2, "loginip_max = 0 = sem limite")
    sql("UPDATE naruto_control SET loginip_max=1 WHERE id=1")
    ok(not login(contas[0])[0], "configuração do admin (loginip_max = 1) vale no próximo login")

    # Corrida: 8 logins ao mesmo tempo com limite 3 e nenhuma vaga ocupada -> exatamente 3 entram.
    sql("UPDATE naruto_users SET loginip='' WHERE username IN (%s)" % lista)
    sql("UPDATE naruto_control SET loginip_max=3 WHERE id=1")
    res = []
    ths = [threading.Thread(target=lambda n=n: res.append(login(n)[0])) for n in contas]
    for t in ths: t.start()
    for t in ths: t.join()
    ok(res.count(True) == 3, "corrida: 8 logins simultâneos, entraram %d (esperado 3)" % res.count(True))
    ok(int(sql("SELECT COUNT(*) FROM naruto_users WHERE loginip IN ('127.0.0.1','::1') AND onlinetime >= NOW() - INTERVAL 10 MINUTE")) == 3, "corrida: 3 vagas ocupadas no banco")

    # Painel: campo nas Configurações gerais e texto em inglês.
    adm = open(JOGO + "/admin.php", encoding="utf8").read()
    ok('name="loginip_max"' in adm and '"loginip_max"=>array(' in adm, "campo loginip_max em Configurações gerais (admin.php)")
    _, _, _, pag = req("GET", "/login.php?do=login&lang=en&conteudo=" + urllib.parse.quote("Já existem 3 contas conectadas a partir da sua rede (IP), que é o máximo permitido. Saia (logout) de uma delas ou aguarde até 10 minutos sem atividade nela e tente novamente."))

    # Formulário de login: um campo de texto (username) + um de senha (current-password) no mesmo <form method=post>.
    _, _, _, pag = req("GET", "/login.php?do=login")
    form = re.search(r'<form[^>]*action="login\.php\?do=login"[^>]*method="post".*?</form>', pag, re.S)
    ok(form is not None, "form de login com action e method=post")
    f = form.group(0) if form else ""
    ok(re.search(r'<input type="text"[^>]*name="username"[^>]*autocomplete="username"', f) is not None, "nome da conta: type=text autocomplete=username")
    ok(re.search(r'<input type="password"[^>]*name="password"[^>]*autocomplete="current-password"', f) is not None, "senha: type=password autocomplete=current-password")
    ok(len(re.findall(r'type="password"', f)) == 1 and len(re.findall(r'<input type="text"', f)) == 1, "exatamente 1 texto + 1 senha no form (heurística do Chrome)")
    _, _, _, pag = req("GET", "/users.php?do=register")
    ok(pag.count('autocomplete="new-password"') == 2 and 'autocomplete="username"' in pag, "cadastro: username + 2x new-password")
finally:
    sql("UPDATE naruto_control SET loginip_max=%s WHERE id=1" % (antigo or "3"))
    sql("UPDATE naruto_users SET loginip='', onlinetime=NOW() - INTERVAL 1 HOUR WHERE username IN (%s)" % lista)
    sql("DELETE FROM naruto_loginfail")

print("\n%d problema(s)" % len(problemas))
sys.exit(1 if problemas else 0)
