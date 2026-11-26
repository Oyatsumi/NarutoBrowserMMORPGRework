#!/usr/bin/env python3
"""Testes do PvP no mundo, caveiras, painel do GM, re-autenticação do admin, auditoria, lista de IPs e recuperação de senha.
Uso: pvp_gm_teste.py URL PASTA_DO_JOGO   (servidor com PHP_CLI_SERVER_WORKERS=8; jogadores de teste com prefixo pg_)
"""
import sys, re, subprocess, urllib.request, urllib.parse, http.cookiejar, threading, html, time
import os
BASE, PASTA = sys.argv[1].rstrip('/'), sys.argv[2]
T = os.path.dirname(os.path.abspath(__file__))  # pasta dos testes
falhas = []
def ok(cond, msg):
    print(('  ok   ' if cond else '  FALHA ') + msg)
    if not cond: falhas.append(msg)
def sql(q):
    return subprocess.check_output(['mysql', '-uroot', '--default-character-set=utf8mb4', '-N', 'narutorpg', '-e', q], text=True).strip()
def jogador(nome, **c):
    return subprocess.check_output(['php', f'{T}/mkuser.php', PASTA, nome] + [f'{k}={v}' for k, v in c.items()], text=True).strip()
def csrf(nome):
    return subprocess.check_output(['php', f'{T}/csrf.php', PASTA, nome], text=True).strip()
ERRO = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice)</b>:(.*?)<br', re.S)
class Sess:
    def __init__(s, cookie=None):
        s.jar = http.cookiejar.CookieJar()
        s.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.jar))
        s.cookie = cookie
    def get(s, p, dados=None, idioma='pt'):
        req = urllib.request.Request(BASE + '/' + p, data=urllib.parse.urlencode(dados, doseq=True).encode() if dados is not None else None)
        extra = '; '.join(f'{c.name}={c.value}' for c in s.jar if c.name != 'narutorpg')
        ck = ('narutorpg=' + urllib.parse.quote(s.cookie) + '; ' if s.cookie else '') + extra + '; idioma=' + idioma
        req.add_header('Cookie', ck)
        try: r = s.op.open(req, timeout=60); corpo = r.read().decode('utf8', 'replace'); url = r.geturl()
        except urllib.error.HTTPError as e: corpo = e.read().decode('utf8', 'replace'); url = ''
        for t, m in ERRO.findall(corpo): falhas.append(f'PHP {t} em {p}: {re.sub("<.*?>", "", m)[:150]}')
        return corpo, url

# ---------------------------------------------------------------- PvP
print('== PvP no mundo')
sql("UPDATE naruto_users SET latitude=90 WHERE username LIKE 'pg\\_%' AND username NOT IN ('pg_a','pg_b')")
# Jogadores de outros testes (ex. im_a do imunidades_teste) parados perto de [50,50] viram "Acampamento" no mini mapa e escondem a caveira.
sql("UPDATE naruto_users SET latitude=90 WHERE username NOT LIKE 'pg\\_%' AND latitude BETWEEN 45 AND 56 AND longitude BETWEEN 45 AND 56")
sql("DELETE FROM naruto_pvp WHERE atacante IN (SELECT id FROM naruto_users WHERE username LIKE 'pg\\_%') OR defensor IN (SELECT id FROM naruto_users WHERE username LIKE 'pg\\_%')")
base = dict(level=30, currentaction='Exploring', latitude=50, longitude=50, maxhp=300, currenthp=300, attackpower=60, defensepower=40, dexterity=20, strength=20,
            batalha_timer2=0, trocajogador1='None', trocajogador2='None', authlevel=0, acesso=0, caveira='', caveiraate=0, gold=1000, mainmsg='None', historico='None',
            bp1='None', bp2='None', bp3='None', bp4='None', bp5='None', bp6='None', bp7='None', bp8='None', pergunta1='x', alinhamento='folha', banidoate=0,
            charclass=1)  # classe fixa (Ninjutsu): os resultados das lutas não dependem da classe sorteada/anterior
ca = jogador('pg_a', **{**base, 'attackpower': 400, 'gold': 1000})
cb = jogador('pg_b', **{**base, 'latitude': 51, 'attackpower': 5, 'defensepower': 1, 'gold': 1000, 'bp1': 'Kunai,3,1,X', 'bp2': 'Kunai:dourado,3,1,X'})
ida, idb = ca.split()[0], cb.split()[0]
sql("UPDATE naruto_users SET onlinetime=NOW() WHERE username IN ('pg_a','pg_b')")  # os dois precisam estar online (últimos 10 min)
A, B = Sess(ca), Sess(cb)
sql("UPDATE naruto_control SET pvpmundo=0")
corpo, url = A.get(f'pvp.php?alvo={idb}')
ok('desligado' in corpo or 'index.php' in url, 'PvP desligado bloqueia pvp.php')
sql("UPDATE naruto_control SET pvpmundo=1, pvpdiflevel=10, pvpnivelseguro=10, pvpesperaalvo=30, pvpesperaataque=0, pvpchancedrop=100, pvpmaxitens=2, pvpondedrop=0, pvpprotegervariantes=1, pvpperdaryou=10, pvpbrancahoras=24, pvpvermelhamortes=3")
corpo, _ = A.get('index.php')
ok('pvp.php?alvo=' + idb in corpo, 'lista do explorar mostra Atacar para o vizinho')
corpo, _ = A.get(f'pvp.php?alvo={idb}')
ok('name="acao" value="atacar"' in corpo, 'tela de confirmação com botão Atacar')
corpo, url = A.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': 'x'})
ok(sql(f"SELECT COUNT(*) FROM naruto_pvp WHERE atacante={ida}") == '0', 'sem CSRF não ataca')
# Corrida: 10 ataques em paralelo -> só 1 luta (espera do mesmo alvo)
tk = csrf('pg_a')
th = [threading.Thread(target=lambda: Sess(ca).get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': tk})) for _ in range(10)]
[t.start() for t in th]; [t.join() for t in th]
ok(sql(f"SELECT COUNT(*) FROM naruto_pvp WHERE atacante={ida}") == '1', '10 ataques simultâneos = 1 luta')
r = sql(f"SELECT bp1,bp2,bp3,bp4,gold,currentaction,latitude,longitude,currenthp,maxhp FROM naruto_users WHERE id={idb}").split('\t')
ra = sql(f"SELECT bp1,bp2,bp3,bp4,gold,caveira FROM naruto_users WHERE id={ida}").split('\t')
ok(r[5] == 'In Town' and r[8] == str(-(-int(r[9]) // 4)), f'derrotado na vila com 1/4 do HP ({r[5]} {r[8]}/{r[9]})')
ok(r[4] == '900' and ra[4] == '1100', f'10% do Ryou passou ao vencedor ({r[4]} / {ra[4]})')
itens_b = [x for x in r[:4] if x != 'None']; itens_a = [x for x in ra[:4] if x != 'None']
ok(itens_b == ['Kunai:dourado,3,1,X'] and itens_a == ['Kunai,3,1,X'], f'item comum foi para o vencedor, variante protegida ({itens_a} / {itens_b})')
ok(ra[5] == 'branca', 'atacante ganhou caveira branca')
ok('ATACADO' in sql(f"SELECT mainmsg FROM naruto_users WHERE id={idb}") and 'i18n:' in sql(f"SELECT historico FROM naruto_users WHERE id={idb}"), 'defensor avisado (janela + histórico i18n)')
idluta = sql(f"SELECT MAX(id) FROM naruto_pvp WHERE atacante={ida}")
corpo, _ = B.get(f'pvp.php?ver={idluta}', idioma='en')
ok('Fight log' in corpo or 'Registro da luta' in corpo, 'defensor vê a luta (en)')
corpo, _ = Sess(jogador('pg_c', **{**base, 'latitude': 90})).get(f'pvp.php?ver={idluta}')
ok('Registro da luta' not in corpo, 'terceiro não vê a luta (IDOR)')
# Espera do mesmo alvo
jogador('pg_b', **{**base, 'latitude': 51})
corpo, _ = A.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': tk})
ok(sql(f"SELECT COUNT(*) FROM naruto_pvp WHERE atacante={ida}") == '1', 'espera entre os mesmos jogadores respeitada')
# Mochila cheia: item fica com o derrotado
sql(f"DELETE FROM naruto_pvp WHERE atacante={ida} OR defensor={ida}")
jogador('pg_a', **{**base, 'attackpower': 400, 'bp1': 'X1,hp,10,1', 'bp2': 'X2,hp,10,1', 'bp3': 'X3,hp,10,1', 'bp4': 'X4,hp,10,1', 'bp5': 'X5,hp,10,1', 'bp6': 'X6,hp,10,1', 'bp7': 'X7,hp,10,1', 'bp8': 'X8,hp,10,1'})
jogador('pg_b', **{**base, 'latitude': 51, 'attackpower': 5, 'defensepower': 1, 'bp1': 'Shuriken,4,1,X'})
A.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': tk})
ok(sql(f"SELECT bp1 FROM naruto_users WHERE id={idb}") == 'Shuriken,4,1,X', 'mochila do vencedor cheia: item fica com o derrotado')
# Regras: cidade, nível seguro, diferença de nível, GM, duelo
sql(f"DELETE FROM naruto_pvp WHERE atacante={ida} OR defensor={ida}")
for campos, nome in [({'level': 5}, 'nível seguro'), ({'level': 60}, 'diferença de nível'), ({'acesso': 3}, 'GM'), ({'batalha_timer2': 5}, 'em duelo'),
                     ({'currentaction': 'In Town'}, 'na vila'), ({'latitude': 53}, 'longe'), ({'trocajogador1': 'pg_a;0;0;0'}, 'em troca')]:
    jogador('pg_b', **{**base, 'latitude': 51, **campos})
    A.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': tk})
    ok(sql(f"SELECT COUNT(*) FROM naruto_pvp WHERE atacante={ida}") == '0', f'recusa ataque: {nome}')
# Legítima defesa: B ataca A; A revida -> sem caveira para A
sql(f"DELETE FROM naruto_pvp WHERE atacante IN ({ida},{idb}) OR defensor IN ({ida},{idb})")
jogador('pg_a', **{**base, 'attackpower': 400, 'caveira': '', 'caveiraate': 0})
jogador('pg_b', **{**base, 'latitude': 51, 'attackpower': 1, 'defensepower': 1})
B.get('pvp.php', {'acao': 'atacar', 'alvo': ida, 'csrf': csrf('pg_b')})
ok(sql(f"SELECT vencedor FROM naruto_pvp WHERE atacante={idb}") == ida, 'B atacou A e perdeu')
sql("UPDATE naruto_control SET pvpesperaalvo=0")
jogador('pg_b', **{**base, 'latitude': 51, 'attackpower': 1, 'defensepower': 1})
A.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': tk})
ok(sql(f"SELECT caveira FROM naruto_users WHERE id={ida}") == '' and sql(f"SELECT injusto FROM naruto_pvp WHERE atacante={ida}") == '0', 'legítima defesa não dá caveira')
# Caveira vermelha após 3 mortes injustas e banimento na 4ª
sql(f"DELETE FROM naruto_pvp WHERE atacante IN ({ida},{idb}) OR defensor IN ({ida},{idb})")
for i in range(3):
    jogador('pg_b', **{**base, 'latitude': 51, 'attackpower': 1, 'defensepower': 1})
    A.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': tk})
ok(sql(f"SELECT caveira FROM naruto_users WHERE id={ida}") == 'vermelha', '3 mortes injustas = caveira vermelha')
corpo, _ = A.get('index.php')
ok('caveira_vermelha.gif' in corpo, 'caveira aparece no mini mapa/listas')
# Vermelho morre: perde tudo
jogador('pg_b', **{**base, 'latitude': 51, 'attackpower': 900})
jogador('pg_a', **{**base, 'attackpower': 1, 'defensepower': 1, 'caveira': 'vermelha', 'caveiraate': int(time.time()) + 999, 'bp1': 'Kunai:dourado,3,1,X', 'bp2': 'P,hp,10,1', 'weaponid': 3, 'weaponname': 'Kunai'})
B.get('pvp.php', {'acao': 'atacar', 'alvo': ida, 'csrf': csrf('pg_b')})
ra = sql(f"SELECT bp1,bp2,weaponname FROM naruto_users WHERE id={ida}").split('\t')
ok(ra == ['None', 'None', 'None'], f'caveira vermelha morre e perde todos os itens ({ra})')
# Vermelho mata de novo: banido
sql(f"DELETE FROM naruto_pvp WHERE atacante IN ({ida},{idb}) OR defensor IN ({ida},{idb})")
jogador('pg_a', **{**base, 'attackpower': 900, 'caveira': 'vermelha', 'caveiraate': int(time.time()) + 999, 'bp1': 'A1,hp,1,1', 'bp2': 'A2,hp,1,1'})
jogador('pg_b', **{**base, 'latitude': 51, 'attackpower': 1, 'defensepower': 1})
A.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': tk})
r = sql(f"SELECT authlevel,banidoate>UNIX_TIMESTAMP()+86400*29,(bp1='None')+(bp2='None')+(bp3='None')+(bp4='None')+(bp5='None')+(bp6='None')+(bp7='None')+(bp8='None') FROM naruto_users WHERE id={ida}").split('\t')
ok(r[0] == '2' and r[1] == '1', 'caveira vermelha que mata é banida por 30 dias')
ok(sql(f"SELECT COUNT(*) FROM naruto_adminlog WHERE acao='pvp_banimento_automatico' AND alvo_id={ida}") != '0', 'banimento registrado na auditoria')
sql(f"UPDATE naruto_users SET banidoate=UNIX_TIMESTAMP()-1 WHERE id={ida}")
A.get('index.php')
ok(sql(f"SELECT authlevel FROM naruto_users WHERE id={ida}") == '0', 'banimento com prazo termina sozinho')
sql("UPDATE naruto_control SET pvpmundo=1, pvpdiflevel=30, pvpnivelseguro=15, pvpesperaalvo=60, pvpesperaataque=2, pvpchancedrop=25, pvpmaxitens=1, pvpondedrop=1, pvpprotegervariantes=1, pvpperdaryou=10, pvpbrancahoras=24, pvpvermelhamortes=3")  # volta aos padrões (tools/pvp_caveira.sql)

# ---------------------------------------------------------------- Troca durante duelo
print('== Troca durante duelo')
jogador('pg_a', **{**base, 'batalha_timer2': 5, 'currentaction': 'In Town'})
corpo, url = A.get('troca.php?do=troca')
ok('duelo' in url or 'duelo' in corpo.lower(), 'troca.php recusa durante duelo')
jogador('pg_a', **base)

# ---------------------------------------------------------------- Admin: re-autenticação, CSRF, confirmação, auditoria
print('== Admin')
cadm = jogador('pg_adm', authlevel=1, level=10)
ADM = Sess(cadm)
corpo, _ = ADM.get('admin.php?do=main')
ok('senhapainel' in corpo and 'name="gamename"' not in corpo, 'admin sem re-autenticação vê só a tela de senha')
sql("DELETE FROM naruto_loginfail WHERE ip='127.0.0.1'")
for i in range(5): ADM.get('admin.php', {'senhapainel': 'errada', 'csrf': csrf('pg_adm')})
corpo, _ = ADM.get('admin.php', {'senhapainel': 'senha123', 'csrf': csrf('pg_adm')})
ok('Muitas tentativas' in corpo, '5 senhas erradas bloqueiam (loginfail)')
sql("DELETE FROM naruto_loginfail WHERE ip='127.0.0.1'")
ADM.get('admin.php?do=main', {'senhapainel': 'senha123', 'csrf': csrf('pg_adm')})
corpo, url = ADM.get('admin.php?do=main')
ok('name="gamename"' in corpo, 're-autenticação abre o painel')
corpo2, _ = ADM.get('admin.php?do=main', {'submit': '1', 'gamename': 'X'})
ok('Token de segurança' in corpo2, 'POST sem CSRF recusado')
# Mudar PvP pede senha
c = sql("SELECT gamename,gamesize,gameurl,adminemail,closedmsg FROM naruto_control").split('\t')
form = re.findall(r'<(?:input|select|textarea)[^>]*name="([^"]+)"', corpo)
dados = {}
for m in re.finditer(r'<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"', corpo): dados[m.group(1)] = html.unescape(m.group(2))
for m in re.finditer(r'<select name="([^"]+)">(.*?)</select>', corpo, re.S):
    sel = re.search(r'value="([^"]*)" selected', m.group(2)) or re.search(r'value="([^"]*)"', m.group(2)); dados[m.group(1)] = sel.group(1)
for m in re.finditer(r'<textarea name="([^"]+)"[^>]*>(.*?)</textarea>', corpo, re.S): dados[m.group(1)] = html.unescape(m.group(2))
dados['submit'] = '1'; dados['pvpmundo'] = '0'; dados['csrf'] = csrf('pg_adm')
corpo, _ = ADM.get('admin.php?do=main', dados)
ok('senhaconfirmar' in corpo and sql("SELECT pvpmundo FROM naruto_control") == '1', 'mudar PvP (desligar) pede a senha (nada salvo antes)')
corpo, _ = ADM.get('admin.php?do=main', {**dados, 'senhaconfirmar': 'senha123'})
ok(sql("SELECT pvpmundo FROM naruto_control") == '0', 'com a senha certa o PvP é salvo')
dados['pvpmundo'] = '1'; ADM.get('admin.php?do=main', {**dados, 'senhaconfirmar': 'senha123'})
ok(sql("SELECT COUNT(*) FROM naruto_adminlog WHERE autor='pg_adm' AND acao='confirmado'") != '0', 'auditoria registra a confirmação')
corpo, _ = ADM.get('admin.php?do=auditoria&autor=pg_adm')
ok('painel_entrar' in corpo and 'senha123' not in corpo, 'página de auditoria lista ações e não mostra senhas')
# Mandar para a vila encerra duelo ativo
jogador('pg_d1', **{**base, 'batalha_timer2': 5}); jogador('pg_d2', **{**base, 'batalha_timer2': 5})
d1, d2 = sql("SELECT id FROM naruto_users WHERE username='pg_d1'"), sql("SELECT id FROM naruto_users WHERE username='pg_d2'")
sql(f"INSERT INTO naruto_duelos SET desafiante={d1}, desafiado={d2}, estado='ativo', aposta=0, vez={d1}, prazo=UNIX_TIMESTAMP()+60, turno=5, registro=''")
ADM.get(f'admin.php?do=edituser:{d1}', {'acao': 'vila', 'csrf': csrf('pg_adm')})
ok(sql(f"SELECT estado,vencedor FROM naruto_duelos WHERE desafiante={d1} ORDER BY id DESC LIMIT 1") == f'fim\t{d2}', 'Mandar para a vila encerra o duelo (o outro vence)')
ok(sql(f"SELECT batalha_timer2 FROM naruto_users WHERE id={d2}") == '0', 'oponente liberado')
# Lista de IPs
cfg = open(PASTA + '/config.php').read()
open(PASTA + '/config.php', 'w').write(re.sub(r'"adminips"\s*=>\s*"[^"]*"', '"adminips" => "10.9.9.9, 192.168.0.0/16"', cfg))
time.sleep(3); corpo, _ = ADM.get('admin.php')  # > opcache.revalidate_freq (2 s): o servidor precisa reler o config.php
ok('Acesso negado' in corpo, 'IP fora da lista é bloqueado')
open(PASTA + '/config.php', 'w').write(re.sub(r'"adminips"\s*=>\s*"[^"]*"', '"adminips" => "10.0.0.1, 127.0.0.0/8"', cfg))
time.sleep(3); corpo, _ = ADM.get('admin.php')
ok('Acesso negado' not in corpo and 'Resumo' in corpo, 'IP na lista (CIDR) entra')
open(PASTA + '/config.php', 'w').write(cfg)
time.sleep(3)  # o resto do teste usa o config.php original (sem lista de IPs)

# ---------------------------------------------------------------- Rotas proibidas para jogador comum e GM
print('== Rotas do admin para jogador/GM')
cgm = jogador('pg_gm', acesso=3, authlevel=0, level=10)
cj = jogador('pg_j', acesso=0, authlevel=0)
rotas = ['admin.php'] + ['admin.php?do=' + r for r in 'main news mensagemglobal users edituser:1 moderacao manutencao auditoria variantes caveiras monsters editmonster:1 items edititem:1 drops editdrop:1 spells editspell:1 towns edittown:1 terrenos editterreno:1 mapajogo levels editlevel'.split()] + ['admin_missoes.php', 'admin_missoes.php?do=npcs', 'admin_missoes.php?do=editar:1']
for nome, ck in [('jogador', cj), ('GM', cgm)]:
    s = Sess(ck); vazou = []
    for r in rotas:
        corpo, url = s.get(r)
        if 'senhapainel' in corpo or 'admconteudo' in corpo: vazou.append(r)
        corpo, url = s.get(r, {'submit': '1', 'apagar': '1', 'acao': 'apagar', 'csrf': csrf('pg_gm' if nome == 'GM' else 'pg_j')})
        if 'admconteudo' in corpo: vazou.append('POST ' + r)
    ok(not vazou, f'{nome} não abre nenhuma rota do admin {vazou}')
corpo, _ = Sess(cj).get('gm.php')
ok('admconteudo' not in corpo and 'senhapainel' not in corpo, 'jogador comum não abre o gm.php')

# ---------------------------------------------------------------- GM
print('== GM')
GM = Sess(cgm)
corpo, _ = GM.get('gm.php')
ok('senhapainel' in corpo, 'GM precisa digitar a senha de novo')
GM.get('gm.php', {'senhapainel': 'senha123', 'csrf': csrf('pg_gm')})
corpo, _ = GM.get('gm.php')
ok('Jogadores online' in corpo and 'admin.php?do=main' not in corpo, 'painel do GM com menu limitado')
idj = cj.split()[0]
jogador('pg_j', **{**base, 'batalha_timer2': 5, 'currentaction': 'Fighting', 'trocajogador1': 'x;0;0;0', 'latitude': 77})
corpo, _ = GM.get(f'gm.php?do=jogador:{idj}', {'acao': 'destravar', 'csrf': csrf('pg_gm')})
r = sql(f"SELECT currentaction,batalha_timer2,trocajogador1 FROM naruto_users WHERE id={idj}")
ok(r == 'In Town\t0\tNone', f'GM destrava ({r})')
GM.get(f'gm.php?do=jogador:{idj}', {'acao': 'mutar', 'minutos': '10', 'motivo': 'spam', 'csrf': csrf('pg_gm')})
ok(int(sql(f"SELECT mudoate FROM naruto_users WHERE id={idj}")) > time.time() + 500, 'GM silencia por 10 minutos')
J = Sess(jogador('pg_j'))
J.get('chat.php', {'fala': 'teste mudo', 'submit': '1'})
ok(sql("SELECT COUNT(*) FROM naruto_chatmap WHERE name='pg_j' AND fala='teste mudo'") == '0', 'silenciado não fala no chat do mapa')
GM.get(f'gm.php?do=jogador:{idj}', {'acao': 'desmutar', 'csrf': csrf('pg_gm')})
hashantes = sql(f"SELECT password FROM naruto_users WHERE id={idj}")
corpo, _ = GM.get(f'gm.php?do=jogador:{idj}', {'acao': 'liberar', 'csrf': csrf('pg_gm')})
ok(hashantes not in corpo and '$argon' not in corpo and '$2y$' not in corpo, 'GM nunca vê hash')
ok(sql(f"SELECT password FROM naruto_users WHERE id={idj}") == hashantes and int(sql(f"SELECT resetliberado FROM naruto_users WHERE id={idj}")) > time.time(), 'GM libera a recuperação sem mudar a senha')
GM.get('gm.php?do=mensagem', {'para': 'pg_j', 'texto': 'Olá do GM', 'enviar': '1', 'csrf': csrf('pg_gm')})
ok('Olá do GM' in sql(f"SELECT caixadepm FROM naruto_users WHERE id={idj}"), 'GM manda mensagem privada')
for acao, campos in [('edituser', {'submit': '1', 'gold': '999999'}), ('main', {'submit': '1'})]:
    GM.get(f'admin.php?do={acao}:{idj}', {**campos, 'csrf': csrf('pg_gm')})
ok(sql(f"SELECT gold FROM naruto_users WHERE id={idj}") != '999999', 'GM não consegue dar Ryou pelo admin')
cadm2 = Sess(cadm); cadm2.jar = ADM.jar
corpo, _ = GM.get(f'gm.php?do=jogador:{cadm.split()[0]}', {'acao': 'liberar', 'csrf': csrf('pg_gm')})
ok('não pode agir' in corpo, 'GM não age sobre administradores')
ok(sql("SELECT COUNT(DISTINCT acao) FROM naruto_adminlog WHERE autor='pg_gm' AND acao IN ('gm_destravar','gm_silenciar','gm_liberar_recuperacao','gm_mensagem')") >= '4', 'ações do GM na auditoria')
corpo, _ = Sess(cj).get(f'mostrarchar.php?nomechar=pg_gm')
ok('gm_badge.gif' in corpo, 'perfil do GM com selo')

# ---------------------------------------------------------------- Recuperação sem e-mail
print('== Recuperação de senha')
sql("UPDATE naruto_control SET emailativo=0")
sql("DELETE FROM naruto_loginfail WHERE ip='127.0.0.1'")
sql("DELETE FROM naruto_users WHERE username='pg_novo'")
V = Sess()
corpo, _ = V.get('users.php?do=register')
ok('pergunta1' in corpo and 'Atenção: as perguntas secretas' in corpo, 'cadastro pede as perguntas com aviso')
corpo, _ = V.get('users.php?do=register', idioma='en')
ok('security questions' in corpo.lower(), 'aviso em inglês')
V.get('users.php?do=register', {'username': 'pg_novo', 'charname': 'pg_novo', 'email1': '', 'email2': '', 'password1': 'senha123', 'password2': 'senha123', 'difficulty': '1', 'alinhamento': 'folha',
                                 'pergunta1': '0', 'resposta1': 'Rex  Cachorro', 'pergunta2': 'outra', 'perguntaoutra2': 'Cor do meu primeiro carro?', 'resposta2': 'azul', 'submit': '1'})
r = sql("SELECT verify,pergunta2,resposta1 LIKE '$argon2id%',level,YEAR(regdate),charclass FROM naruto_users WHERE username='pg_novo'").split('\t')
ok(r[0] == '1' and r[1] == 'Cor do meu primeiro carro?' and r[2] == '1', f'conta sem e-mail ativa na hora, respostas com Argon2id ({r[:3]})')
ok(r[5] == '0', f'cadastro começa sem especialização (charclass={r[5]})')
ano = r[4]
def recuperar(**c):
    s = Sess(); s.get('users.php?do=recuperar', {'etapa': '1', 'username': 'pg_novo', 'charname': 'pg_novo'})
    return s.get('users.php?do=recuperar', {'etapa': '2', 'username': 'pg_novo', 'charname': 'pg_novo', 'resposta1': 'rex cachorro', 'resposta2': 'AZUL', 'ano': ano, 'email': '', 'level': '1', 'newpass1': 'novasenha1', 'newpass2': 'novasenha1', **c})[0]
corpo = recuperar(level='2')
ok('não conferem' in corpo and sql("SELECT password FROM naruto_users WHERE username='pg_novo'").startswith('$'), 'level errado: erro genérico')
corpo = recuperar(resposta2='verde')
ok('não conferem' in corpo, 'resposta errada: erro genérico')
corpo = recuperar()
ok('modificada com sucesso' in corpo, 'tudo certo: senha trocada (respostas sem diferença de maiúsculas/espaços)')
for i in range(5): recuperar(level='9')
corpo = recuperar()
ok('Muitas tentativas' in corpo, 'recuperação limitada pelo loginfail')
ok(sql("SELECT COUNT(*) FROM naruto_adminlog WHERE acao LIKE 'recuperar%'") != '0', 'tentativas registradas')
sql("DELETE FROM naruto_loginfail WHERE ip='127.0.0.1'")
# Conta antiga sem perguntas: lembrete
jogador('pg_j', pergunta1='')
corpo, _ = Sess(jogador('pg_j')).get('index.php')
ok('users.php?do=perguntas' in corpo, 'conta sem perguntas recebe lembrete no jogo')

# Código de recuperação
print('== Código de recuperação')
sql("DELETE FROM naruto_users WHERE username='pg_cod'"); sql("DELETE FROM naruto_loginfail WHERE ip='127.0.0.1'")
corpo, _ = Sess().get('users.php?do=register', {'username': 'pg_cod', 'charname': 'pg_cod', 'email1': '', 'email2': '', 'password1': 'senha123', 'password2': 'senha123', 'difficulty': '1', 'alinhamento': 'folha', 'metodo': 'codigo', 'submit': '1'})
m = re.search(r'id="codigorec"[^>]*>([A-Z0-9-]+)<', corpo)
ok(m and len(m.group(1)) == 39 and 'required' in corpo, 'cadastro com código mostra o código uma vez (com "anotei")')
cod = m.group(1) if m else ''
h = sql("SELECT codigorecuperacao FROM naruto_users WHERE username='pg_cod'")
ok(h.startswith('$argon2id') and cod not in h, 'código guardado só como hash')
corpo, _ = Sess().get('users.php?do=recuperarcodigo', {'username': 'pg_cod', 'codigo': 'AAAA-BBBB', 'newpass1': 'x1234567', 'newpass2': 'x1234567', 'submit': '1'})
ok('não conferem' in corpo, 'código errado recusado')
def corrida_codigo():
    return Sess().get('users.php?do=recuperarcodigo', {'username': 'pg_cod', 'codigo': cod.lower().replace('-', ' '), 'newpass1': 'nova12345', 'newpass2': 'nova12345', 'submit': '1'})[0]
res = []
th = [threading.Thread(target=lambda: res.append(corrida_codigo())) for _ in range(5)]
[t.start() for t in th]; [t.join() for t in th]
novos = [re.search(r'id="codigorec"[^>]*>([A-Z0-9-]+)<', r) for r in res]
novos = [n.group(1) for n in novos if n]
ok(len(novos) == 1 and novos[0] != cod, f'código usado uma vez só (5 em paralelo -> {len(novos)} sucesso) e novo código gerado')
corpo, _ = Sess().get('users.php?do=recuperarcodigo', {'username': 'pg_cod', 'codigo': cod, 'newpass1': 'nova12345', 'newpass2': 'nova12345', 'submit': '1'})
ok('não conferem' in corpo, 'código antigo não vale mais')
sql("DELETE FROM naruto_loginfail WHERE ip='127.0.0.1'")

print('\nFALHAS:', len(falhas))
for f in falhas: print(' -', f)
