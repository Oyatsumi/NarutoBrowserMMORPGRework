#!/usr/bin/env python3
"""Teste da troca de vila natal (missões de transferência 177-181 dos embaixadores, docs/TROCA_DE_VILA.md).

Uso: python3 troca_vila_teste.py http://127.0.0.1:8080 /var/www/narutorpg
Jogadores de teste: tv_ana (pt, Folha) e tv_bob (en, Pedra), mais tv_adm (administrador).
Cobre: recusas (nível, graduação, a própria vila escondida/recusada, vila secreta, vila sem alinhamento, duas
transferências ao mesmo tempo, objetivo incompleto, Ryou insuficiente, espera entre trocas), o fluxo completo
(passos contados pelos ganchos reais de vitória/posição, entrega no embaixador), corridas (aceite e entrega em
paralelo: uma troca, uma cobrança, uma linha no histórico), o que depende da vila natal depois da troca (Pergaminho
de Retorno, volta após a morte, ficha, histórico), a espera configurável no painel e a tela do painel.
"""
import sys, re, time, json, subprocess, threading, urllib.request, urllib.parse

BASE, PASTA = sys.argv[1].rstrip('/'), sys.argv[2]
TESTES = __file__.rsplit('/', 1)[0]
ERRO = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice)</b>:\s*(.*?)<br', re.S)
falhas = []

class SemRedirecionar(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
abridor = urllib.request.build_opener(SemRedirecionar)

def sql(q):
    return subprocess.check_output(['mysql', '-uroot', '--default-character-set=utf8mb4', '-N', '-B', 'narutorpg', '-e', q], text=True).strip()

def jogador(nome, **campos):
    s = subprocess.check_output(['php', f'{TESTES}/mkuser.php', PASTA, nome] + [f'{k}={v}' for k, v in campos.items()], text=True).strip().split(' ')
    return s[0], ' '.join(s)

def token(nome):
    return subprocess.check_output(['php', f'{TESTES}/csrf.php', PASTA, nome], text=True).strip()

def pedir(cookie, idioma, caminho, dados=None, extra=''):
    req = urllib.request.Request(BASE + '/' + caminho, data=urllib.parse.urlencode(dados).encode() if dados is not None else None)
    req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(cookie) + '; idioma=' + idioma + extra)
    try:
        r = abridor.open(req, timeout=60); cod, corpo, loc = r.status, r.read().decode('utf8', 'replace'), ''
    except urllib.error.HTTPError as e:
        cod, corpo, loc = e.code, e.read().decode('utf8', 'replace'), e.headers.get('Location', '')
    for t, m in ERRO.findall(corpo): falhas.append(f'{caminho}: {t}: {re.sub("<.*?>", "", m)[:200]}')
    if 'Erro SQL' in corpo: falhas.append(f'{caminho}: SQL ' + corpo[corpo.find('Erro SQL'):][:300])
    if cod >= 500: falhas.append(f'{caminho}: HTTP {cod}')
    return cod, corpo, urllib.parse.unquote(loc)

def entrarpainel(nome, cookie, senha='senha123'):
    """Re-autenticação do painel (POST senhapainel): devolve o trecho do cookie narutorpg_painel."""
    req = urllib.request.Request(BASE + '/admin.php', data=urllib.parse.urlencode({'senhapainel': senha, 'csrf': token(nome)}).encode())
    req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(cookie) + '; idioma=pt')
    try: hdrs = abridor.open(req, timeout=60).headers
    except urllib.error.HTTPError as e: hdrs = e.headers
    for sc in hdrs.get_all('Set-Cookie') or []:
        m = re.match(r'narutorpg_painel=([^;]+)', sc)
        if m and m.group(1): return '; narutorpg_painel=' + m.group(1)
    return ''

def confere(cond, texto):
    print(('  ok   ' if cond else '  FALHA ') + texto)
    if not cond: falhas.append(texto)

def U(nome, campo):
    return sql(f"SELECT {campo} FROM naruto_users WHERE username='{nome}'")

def paralelo(n, f):
    res = []
    th = [threading.Thread(target=lambda: res.append(f())) for _ in range(n)]
    [t.start() for t in th]; [t.join() for t in th]
    return res

def gancho(nome, codigo):
    """Roda os ganchos reais do jogo (missoesextras_vitoria / missoesextras_posicao) com a linha atual do jogador."""
    php = ("chdir('%s'); $_GET=$_POST=array(); $_COOKIE=array(); include('lib.php'); $link=opendb(); include_once('funcoesmissoesextras.php'); "
           "$controlrow = mysqli_fetch_array(doquery(\"SELECT * FROM {{table}} WHERE id='1' LIMIT 1\", 'control')); "
           "$u = mysqli_fetch_array(doquery(\"SELECT * FROM {{table}} WHERE username='%s' LIMIT 1\", 'users'), MYSQLI_ASSOC); %s") % (PASTA, nome, codigo)
    r = subprocess.run(['php', '-r', php], capture_output=True, text=True)
    if r.stdout.strip() or r.stderr.strip(): falhas.append('gancho: ' + (r.stdout + r.stderr)[:300])

def vencer(nome, idmonstro, vezes):
    gancho(nome, "$m = mysqli_fetch_array(doquery(\"SELECT * FROM {{table}} WHERE id='%d' LIMIT 1\", 'monsters'), MYSQLI_ASSOC); for ($i = 0; $i < %d; $i++) { missoesextras_vitoria($u, $m); }" % (idmonstro, vezes))

def visitar(nome, lat, lon):
    sql(f"UPDATE naruto_users SET latitude={lat}, longitude={lon}, currentaction='Exploring' WHERE username='{nome}'")
    gancho(nome, "missoesextras_posicao($u);")

def estado(uid, mid):
    return sql(f"SELECT CONCAT(estado,'/',etapa_atual,'/',progresso) FROM naruto_missoesextras_jogador WHERE usuario_id={uid} AND missao_id={mid}")

NPC = {177: (-4, -5), 178: (131, -74), 179: (34, 176), 180: (-136, -125), 181: (164, 176)}
DIAS = sql("SELECT trocavila_dias FROM naruto_control WHERE id=1")
TAXA = 20000
base = dict(level=40, gold=50000, currentaction='Exploring', latitude=131, longitude=-74, maxhp=900, currenthp=900, maxmp=600, currentmp=600,
            maxtp=200, currenttp=200, towns='0,1,2,3,4,5,6', graduacao='Chuunin', alinhamento='folha', vilatroca_em=0, historico='None',
            bp1='None', bp2='None', bp3='None', bp4='None', bp5='None', bp6='None', bp7='None', bp8='None', passo_jutsu=0,
            batalha_timer2=0, batalha_nome='None', trocajogador1='None', currentfight=0, jutsuestado='', missao='22,0,0,0')
for n in ('tv_ana', 'tv_bob'):
    sql(f"DELETE FROM naruto_missoesextras_jogador WHERE usuario_id=(SELECT id FROM naruto_users WHERE username='{n}')")
idA, ckA = jogador('tv_ana', **base)
idB, ckB = jogador('tv_bob', **dict(base, alinhamento='pedra', latitude=-4, longitude=-5))
tA, tB = token('tv_ana'), token('tv_bob')
A = lambda c, d=None: pedir(ckA, 'pt', c, d)
B = lambda c, d=None: pedir(ckB, 'en', c, d)
SET = lambda nome, s: sql(f"UPDATE naruto_users SET {s} WHERE username='{nome}'")
aceitarA = lambda mid: A('missoesextras.php?do=aceitar', {'id': mid, 'csrf': tA})
entregarA = lambda mid: A('missoesextras.php?do=entregar', {'id': mid, 'csrf': tA})

print('1. Não existia troca de vila do lado do jogador; agora: embaixadores, quadro da vila natal e lista')
c, corpo, _ = A('falapersonagens.php')
confere('Sua vila natal atual: <b>Vila da Folha</b>' in corpo, 'embaixadora da Pedra mostra a vila natal atual (pt)')
confere('Transferência: Vila da Pedra' in corpo, 'transferência para a Pedra oferecida')
SET('tv_ana', 'latitude=-4, longitude=-5')
c, corpo, _ = A('falapersonagens.php')
confere('Transferência: Vila da Folha' not in corpo and 'Sua vila natal atual' in corpo, 'transferência para a própria vila natal escondida')
c, _, loc = aceitarA(177)
confere('Essa já é a sua vila natal' in loc, 'aceitar a própria vila (POST forjado) recusado')
c, corpo, _ = B('falapersonagens.php')
confere('Your current home village: <b>Stone Village</b>' in corpo and 'Transfer: Leaf Village' in corpo, 'embaixador da Folha em inglês (tv_bob, Pedra)')

print('2. Recusas no aceite: nível, graduação, vila secreta, vila sem alinhamento')
SET('tv_ana', 'latitude=131, longitude=-74, level=20')
c, _, loc = aceitarA(178)
confere('nível 30' in loc, f'nível 20 recusado ({loc[-80:]})')
SET('tv_ana', "level=40, graduacao='Genin'")
c, _, loc = aceitarA(178)
confere('Chuunin' in loc, 'Genin recusado')
SET('tv_ana', "graduacao='Chuunin'")
# Missões de teste apontando para uma vila secreta (8) e para uma vila sem alinhamento (2): nunca aceitas.
cols = "categoria,titulo,titulo_en,descricao,descricao_en,rank_texto,npc_nome,npc_avatar,npc_id,vila_id,nivel_minimo,graduacao_minima,tipo,alvo,quantidade,repetivel,intervalo_horas,config,ativa,ordem"
for mid, vila in ((990, 8), (991, 2)):
    sql(f"DELETE FROM naruto_missoesextras WHERE id={mid}")
    sql(f"""INSERT INTO naruto_missoesextras (id,{cols}) VALUES ({mid},'extra','Teste {vila}','Test {vila}','x','x','','Embaixadora Iwame','personagem7',33,0,1,'','cadeia','',2,1,0,'{{"troca_vila": {vila}, "etapas": [{{"tipo": "visitar_local", "alvo": "1,1", "quantidade": 1}}, {{"tipo": "pagar_ryou", "alvo": "", "quantidade": 1}}]}}',1,999)""")
    c, _, loc = aceitarA(mid)
    confere('não está aceitando ninjas' in loc, f'transferência para a vila {vila} ({"secreta" if vila == 8 else "sem alinhamento"}) recusada')
    confere(estado(idA, mid) == '', f'nada gravado para a missão {mid}')
    sql(f"DELETE FROM naruto_missoesextras WHERE id={mid}")

print('3. Aceite em paralelo (8x) e duas transferências ao mesmo tempo')
r = paralelo(8, lambda: aceitarA(178))
confere(sql(f"SELECT COUNT(*) FROM naruto_missoesextras_jogador WHERE usuario_id={idA} AND missao_id=178") == '1' and estado(idA, 178) == 'andamento/0/0', 'uma só linha em andamento')
SET('tv_ana', 'latitude=34, longitude=176')
c, _, loc = aceitarA(179)
confere('outra transferência de vila' in loc, 'segunda transferência (Névoa) recusada enquanto a da Pedra está em andamento')

print('4. Entrega antes da hora e passos contados pelos ganchos reais')
SET('tv_ana', 'latitude=131, longitude=-74')
c, _, loc = entregarA(178)
confere('ainda não completou' in loc, 'entrega com objetivo incompleto recusada')
vencer('tv_ana', 75, 3)  # inimigo errado: não conta
confere(estado(idA, 178) == 'andamento/0/0', 'inimigo errado não conta')
vencer('tv_ana', 76, 3)
confere(estado(idA, 178) == 'andamento/1/0', 'etapa 1 (3 Jounnin Traidores) concluída')
vencer('tv_ana', 73, 2)
confere(estado(idA, 178) == 'andamento/2/0', 'etapa 2 (2 ANBU Corrompidos) concluída')
visitar('tv_ana', 135, -80)
confere(estado(idA, 178) == 'andamento/3/0', 'etapa 3 (marco da fronteira) concluída')
SET('tv_ana', "latitude=131, longitude=-74, gold=5000")
c, _, loc = entregarA(178)
confere('ainda não completou' in loc and U('tv_ana', 'alinhamento') == 'folha' and U('tv_ana', 'gold') == '5000', 'sem a taxa: recusado, nada muda')
SET('tv_ana', f"gold=50000, vilatroca_em={int(time.time()) - 86400}")
c, _, loc = entregarA(178)
confere('Poderá trocar de novo em' in loc and U('tv_ana', 'alinhamento') == 'folha' and estado(idA, 178) == 'andamento/3/0', 'espera conferida também na entrega (trocou ontem)')
SET('tv_ana', 'vilatroca_em=0')

print('5. Entrega em paralelo (8x): uma troca, uma cobrança, uma linha no histórico')
antes = int(time.time())
r = paralelo(8, lambda: entregarA(178))
ok = [loc for c, _, loc in r if 'Parabéns' in loc]
confere(len(ok) == 1, f'uma só entrega com sucesso ({len(ok)})')
confere(ok and 'sua vila natal é a Vila da Pedra' in ok[0], 'mensagem da nova vila natal')
confere(U('tv_ana', 'alinhamento') == 'pedra', 'alinhamento = pedra')
confere(U('tv_ana', 'gold') == str(50000 - TAXA), f'taxa cobrada uma vez (gold {U("tv_ana", "gold")})')
confere(int(U('tv_ana', 'vilatroca_em')) >= antes, 'data da troca gravada')
hist = U('tv_ana', 'historico')
confere(hist.count('trocou de vila natal') == 1 and hist.count('Transferência: Vila da Pedra') == 1, 'histórico com uma troca e uma missão')
confere(estado(idA, 178).startswith('concluida'), 'missão concluída')

print('6. Tudo que depende da vila natal segue a nova vila')
c, corpo, _ = A('index.php')
confere('Você trocou de vila natal: Vila da Folha → Vila da Pedra.' in corpo, 'troca no histórico da coluna da esquerda (pt)')
c, corpo, _ = A('index.php?do=showchar')
confere(re.search(r'Vila natal</?[^>]*>?: Vila da Pedra <span class="small">\(desde', corpo) is not None or ('Vila da Pedra <span class="small">(desde' in corpo), 'ficha (Todos os Status) mostra Vila da Pedra e a data')
c, corpo, _ = A('mostrarchar.php?nomechar=tv_ana')
confere('Vila da Pedra' in corpo, 'ficha pública mostra a Vila da Pedra')
SET('tv_ana', "latitude=40, longitude=40, currentaction='Exploring', currenttp=200, bp1='Pergaminho de Retorno,ret,1,X'")
A('backpack.php?qual=1')
confere(U('tv_ana', 'latitude') + ',' + U('tv_ana', 'longitude') == '125,-70', 'Pergaminho de Retorno leva à Vila da Pedra')
morte = subprocess.run(['php', '-r', "chdir('%s'); $_GET=$_POST=$_COOKIE=array(); include('lib.php'); opendb(); include_once('cidadesconteudo.php'); $v = vilanataldoalinhamento('%s'); echo $v['id'];" % (PASTA, U('tv_ana', 'alinhamento'))], capture_output=True, text=True).stdout.strip()
confere(morte == '3', 'vila da morte/PvP/GM (vilanataldoalinhamento) = Pedra')
c, corpo, _ = A('index.php?do=showchar')

print('7. Espera entre trocas (control.trocavila_dias) e transferência de volta')
SET('tv_ana', "latitude=-4, longitude=-5, currentaction='Exploring'")
c, corpo, _ = A('falapersonagens.php')
confere('Próxima troca possível em' in corpo and 'Transferência: Vila da Folha' in corpo, 'quadro mostra a espera; a Folha agora aparece')
c, _, loc = aceitarA(177)
confere(f'Poderá trocar de novo em {DIAS} dia(s) e 0 hora(s)' in loc, f'aceite recusado durante a espera ({loc[-60:]})')
c, corpo, _ = B('falapersonagens.php')
SET('tv_bob', f"vilatroca_em={int(time.time()) - 3600}")
c, _, loc = B('missoesextras.php?do=aceitar', {'id': 177, 'csrf': tB})
confere('You can change again in' in loc, 'recusa em inglês (tv_bob)')
SET('tv_ana', f"vilatroca_em={int(time.time()) - (int(DIAS) + 1) * 86400}")
c, _, loc = aceitarA(177)
confere('aceita' in loc and estado(idA, 177) == 'andamento/0/0', 'depois da espera a transferência de volta é aceita')
sql("UPDATE naruto_control SET trocavila_dias=0 WHERE id=1")
SET('tv_bob', f"vilatroca_em={int(time.time())}")
c, _, loc = B('missoesextras.php?do=aceitar', {'id': 177, 'csrf': tB})
confere('accepted' in loc.lower(), f'com trocavila_dias = 0 não há espera ({loc[-60:]})')
sql(f"UPDATE naruto_control SET trocavila_dias={DIAS} WHERE id=1")
# Volta completa da tv_ana para a Folha (passos pelos ganchos) e conferência do histórico com duas trocas.
vencer('tv_ana', 3, 6); vencer('tv_ana', 4, 3); visitar('tv_ana', 10, -10)
SET('tv_ana', 'latitude=-4, longitude=-5, gold=30000')
c, _, loc = entregarA(177)
confere('Parabéns' in loc and U('tv_ana', 'alinhamento') == 'folha' and U('tv_ana', 'gold') == '10000', 'transferência de volta para a Folha concluída')
confere(U('tv_ana', 'historico').count('trocou de vila natal') == 2, 'duas trocas no histórico')

print('8. Painel do administrador')
idM, ckM = jogador('tv_adm', authlevel=1, level=20, gold=10000)
PN = entrarpainel('tv_adm', ckM)
confere(PN != '', 're-autenticação do painel')
c, corpo, _ = pedir(ckM, 'pt', 'admin.php?do=main', extra=PN)
confere('name="trocavila_dias"' in corpo, 'Configurações gerais com o campo de espera')
c, corpo, _ = pedir(ckM, 'pt', 'admin_missoes.php?do=editar:178', extra=PN)
confere(re.search(r'name="cfg_troca_vila">.*?<option value="3" selected="selected">', corpo, re.S) is not None, 'editor da missão 178 com a vila de destino Pedra')
confere(re.search(r'name="cfg_troca_vila">(.*?)</select>', corpo, re.S) and 'value="8"' not in re.search(r'name="cfg_troca_vila">(.*?)</select>', corpo, re.S).group(1), 'vilas secretas fora da lista de destinos')
# Salvar a missão pelo formulário real (todos os campos como vieram) mantém o destino; trocar para uma vila secreta é recusado.
import html as _h
def campos(corpo):
    f = {}
    form = corpo[corpo.find('name="salvar"') - 60000 if corpo.find('name="salvar"') > 60000 else 0:]
    for m in re.finditer(r'<input([^>]*)>', corpo):
        a = m.group(1); n = re.search(r'name="([^"]+)"', a); v = re.search(r'value="([^"]*)"', a); t = re.search(r'type="([^"]+)"', a)
        if not n: continue
        tp = t.group(1) if t else 'text'
        if tp in ('checkbox', 'radio') and 'checked' not in a: continue
        if tp in ('submit', 'image', 'button') and n.group(1) != 'salvar': continue
        f[n.group(1)] = _h.unescape(v.group(1)) if v else ''
    for m in re.finditer(r'<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>', corpo, re.S):
        sel = re.search(r'<option value="([^"]*)"[^>]*selected', m.group(2)) or re.search(r'<option value="([^"]*)"', m.group(2))
        f[m.group(1)] = _h.unescape(sel.group(1)) if sel else ''
    for m in re.finditer(r'<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>', corpo, re.S): f[m.group(1)] = _h.unescape(m.group(2))
    return f
c, corpo, _ = pedir(ckM, 'pt', 'admin_missoes.php?do=editar:178', extra=PN)
f = campos(corpo); f['salvar'] = f.get('salvar', '1')
cfgantes = sql("SELECT config FROM naruto_missoesextras WHERE id=178")
c, corpo2, _ = pedir(ckM, 'pt', 'admin_missoes.php?do=editar:178', f, extra=PN)
cfgdepois = sql("SELECT config FROM naruto_missoesextras WHERE id=178")
if 'Missão salva.' not in corpo2: print('    painel:', re.sub(r'\s+', ' ', re.sub('<[^>]+>', ' ', corpo2[corpo2.find('<h1'):]))[:700])
confere('Missão salva.' in corpo2 and json.loads(cfgdepois).get('troca_vila') == 3 and json.loads(cfgdepois)['etapas'] == json.loads(cfgantes)['etapas'], f'salvar no painel mantém troca_vila e as etapas')
f2 = dict(f); f2['cfg_troca_vila'] = '8'
c, corpo3, _ = pedir(ckM, 'pt', 'admin_missoes.php?do=editar:178', f2, extra=PN)
confere('precisa ser uma vila natal com alinhamento e não secreta' in corpo3 and json.loads(sql("SELECT config FROM naruto_missoesextras WHERE id=178")).get('troca_vila') == 3, 'destino secreto recusado no painel')
confere(sql("SELECT COUNT(*) FROM naruto_adminlog WHERE acao LIKE 'missoes:editar%' AND detalhes LIKE '%Transferência: Vila da Pedra%' AND quando > NOW() - INTERVAL 5 MINUTE") != '0', 'edição registrada no log do painel')
c, corpo, _ = pedir(ckM, 'en', 'admin_missoes.php?do=editar:178', extra=PN)
confere('Home village change' in corpo, 'editor em inglês')

print('9. Ajuda nos dois idiomas')
c, corpo, _ = A('help.php')
confere('<a name="trocavila"></a>Trocar de Vila Natal' in corpo and f'<b>{DIAS} dia(s)</b>' in corpo and '20.000 Ryou' in corpo, 'ajuda (pt) com espera e taxa atuais')
c, corpo, _ = B('help.php')
confere('Changing your Home Village' in corpo and 'citizenship fee' in corpo and '20,000 Ryou' in corpo, 'ajuda (en)')

for n in ('tv_ana', 'tv_bob'):
    sql(f"DELETE FROM naruto_missoesextras_jogador WHERE usuario_id=(SELECT id FROM naruto_users WHERE username='{n}')")
print()
print('RESULTADO:', 'OK, 0 problemas' if not falhas else f'{len(falhas)} problema(s)')
for f in falhas: print('  -', f)
sys.exit(1 if falhas else 0)
