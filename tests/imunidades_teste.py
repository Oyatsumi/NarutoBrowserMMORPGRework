#!/usr/bin/env python3
"""Testes das imunidades dos inimigos por tipo (monsters.imunidades: ninjutsu, taijutsu, fogo/agua/terra/vento/raio, sono;
tools/imunidades.sql): migração do legado immune, funções de funcoesjutsus.php, luta PvE (ataque físico, jutsu por
elemento, queimadura, sono), cartão da luta, ajuda dos monstros (pt/en), editor do admin (checkboxes e recusa de
taijutsu+ninjutsu), balanceamento (toda faixa de nível tem inimigos derrotáveis) e instalação nova.
Uso: imunidades_teste.py URL PASTA_DO_JOGO   (jogadores de teste com prefixo im_). No fim os inimigos alterados voltam.
"""
import sys, re, math, subprocess, urllib.request, urllib.parse, http.cookiejar, threading, os, tempfile
BASE, PASTA = sys.argv[1].rstrip('/'), sys.argv[2]
T = os.path.dirname(os.path.abspath(__file__))  # pasta dos testes
falhas = []
def ok(cond, msg):
    print(('  ok   ' if cond else '  FALHA ') + msg)
    if not cond: falhas.append(msg)
def sql(q, db='narutorpg'):
    return subprocess.check_output(['mysql', '-uroot', '--default-character-set=utf8mb4', '-N', db, '-e', q], text=True).strip()
def jogador(nome, **c):
    return subprocess.check_output(['php', f'{T}/mkuser.php', PASTA, nome] + [f'{k}={v}' for k, v in c.items()], text=True).strip()
def csrf(nome):
    return subprocess.check_output(['php', f'{T}/csrf.php', PASTA, nome], text=True).strip()
def php(codigo):  # roda funções do jogo pela linha de comando (mesmo bootstrap do mkuser.php)
    return subprocess.check_output(['php', '-r', f'chdir("{PASTA}"); $_GET=$_POST=$_COOKIE=array(); include("lib.php"); $link=opendb(); $controlrow=mysqli_fetch_array(doquery("SELECT * FROM {{{{table}}}} WHERE id=1","control")); include_once("funcoesinclusas.php"); {codigo}'], text=True).strip()
ERRO = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice)</b>:(.*?)<br', re.S)
class Sess:
    def __init__(s, cookie):
        s.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        s.cookie = cookie
    def get(s, p, dados=None, idioma='pt'):
        req = urllib.request.Request(BASE + '/' + p, data=urllib.parse.urlencode(dados).encode() if dados is not None else None)
        req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(s.cookie) + '; idioma=' + idioma)
        try: r = s.op.open(req, timeout=60); corpo = r.read().decode('utf8', 'replace'); url = r.geturl()
        except urllib.error.HTTPError as e: corpo = e.read().decode('utf8', 'replace'); url = ''
        for t, m in ERRO.findall(corpo): falhas.append(f'PHP {t} em {p}: {re.sub("<.*?>", "", m)[:150]}')
        if 'Erro SQL' in corpo: falhas.append(f'SQL em {p}')
        return corpo, urllib.parse.unquote(url)
def U(i, campos): sql(f"UPDATE naruto_users SET {campos} WHERE id={i}")
def campo(i, c): return sql(f"SELECT {c} FROM naruto_users WHERE id={i}")

BASE_J = dict(level=60, experience=0, currentaction='Fighting', latitude=50, longitude=50, gold=1000, alinhamento='folha', towns='0',
              attackpower=3000, defensepower=5000, strength=50, dexterity=50, precisao=30000, maxhp=60000, currenthp=60000, maxmp=9000, currentmp=9000,
              currentep=500, maxep=500, spells='20,6,11,21', authlevel=0, currentfight=0, currentmonsterhp=0, jutsuestado='', banidoate=0, mainmsg='None', pergunta1='x')
def luta(i, mid, hp=60000):
    imm = sql(f"SELECT immune FROM naruto_monsters WHERE id={mid}")
    U(i, f"currentaction='Fighting', currentfight=2, currentmonster={mid}, currentmonsterhp={hp}, currentmonstersleep=0, currentmonsterimmune={imm}, currenthp=60000, currentmp=9000, currentuberdamage=0, jutsuestado='', lutaultimaacao=0")
def hpm(i): return int(campo(i, 'currentmonsterhp'))

try:
    print('== Instalação nova (tools/empacotar.sh, linha 9) e migração')
    with tempfile.TemporaryDirectory() as d:
        saida = os.path.join(d, 'naruto_rpg.sql')
        subprocess.check_output(['python3', os.path.join(T, '..', 'tools', 'build_sql.py'), os.environ.get('SQL_ORIGINAL', os.path.join(T, '..', 'sql', 'original', 'databaseName.sql')), saida])
        sql("DROP DATABASE IF EXISTS narutorpg_imteste; CREATE DATABASE narutorpg_imteste CHARACTER SET utf8mb4", db='mysql')
        with open(saida, 'rb') as f: subprocess.run(['mysql', '-uroot', 'narutorpg_imteste'], stdin=f, check=True)
        N = lambda q: sql(q, db='narutorpg_imteste')
        # Chefes semanais (251-255, chefes_semanais.sql) têm imunidades próprias de propósito; a 255 é immune=1 com 'raio,ninjutsu'.
        ok(N("SELECT COUNT(*) FROM naruto_monsters WHERE immune=1 AND TRIM(BOTH ',' FROM REPLACE(CONCAT(',',imunidades,','),',genjutsu,',','))<>'ninjutsu' AND id NOT BETWEEN 251 AND 255") == '0', 'immune 1 -> ninjutsu')
        ok(N("SELECT COUNT(*) FROM naruto_monsters WHERE immune=1 AND NOT FIND_IN_SET('ninjutsu', imunidades)") == '0', 'todo immune 1 (inclusive chefes) é imune a ninjutsu')
        ok(N("SELECT COUNT(*) FROM naruto_monsters WHERE immune=2 AND TRIM(BOTH ',' FROM REPLACE(CONCAT(',',imunidades,','),',genjutsu,',','))<>'ninjutsu,sono'") == '0', 'immune 2 -> ninjutsu,sono')
        ok(N("SELECT TRIM(BOTH ',' FROM REPLACE(CONCAT(',',imunidades,','),',genjutsu,',',')) FROM naruto_monsters WHERE id IN (3,175,197,229) ORDER BY id") == 'fogo\ntaijutsu,terra\nninjutsu\ntaijutsu,sono', 'imunidades temáticas na instalação nova')
        ok(N("SELECT COUNT(*) FROM naruto_monsters WHERE id BETWEEN 222 AND 235") == '14', 'inimigos novos na instalação nova')
        ok(N("SELECT COUNT(*) FROM naruto_monsters WHERE FIND_IN_SET('taijutsu',imunidades) AND FIND_IN_SET('ninjutsu',imunidades)") == '0', 'ninguém imune a taijutsu e ninjutsu (instalação nova)')
        sql("DROP DATABASE narutorpg_imteste", db='mysql')
    with open(os.path.join(T, '..', 'tools', 'imunidades.sql'), 'rb') as f: subprocess.run(['mysql', '-uroot', 'narutorpg'], stdin=f, check=True)
    ok(sql("SELECT COUNT(*) FROM naruto_monsters WHERE id<256 AND TRIM(BOTH ',' FROM REPLACE(CONCAT(',',imunidades,','),',genjutsu,',','))<>''") == '106', 'migração idempotente no banco vivo (106 inimigos com imunidade: 73 + 16 do começo + 12 dos inimigos 236-250 + 5 chefes semanais 251-255)')
    ok(sql("SELECT COUNT(*) FROM naruto_monsters m WHERE id BETWEEN 222 AND 235 AND imunidades<>'' AND (SELECT COUNT(*) FROM naruto_monster_drops d WHERE d.monster_id=m.id) BETWEEN 3 AND 40") == '14', '14 inimigos novos com imunidade e drops (sem duplicar)')
    ok(all(os.path.exists(f'{PASTA}/layoutnovo/monstros/{i}.jpg') for i in range(222, 236)), 'cartas provisórias dos inimigos novos')

    print('== Balanceamento por faixa de nível')
    ok(sql("SELECT COUNT(*) FROM naruto_monsters WHERE FIND_IN_SET('taijutsu',imunidades) AND FIND_IN_SET('ninjutsu',imunidades)") == '0', 'nenhum inimigo imune a taijutsu e ninjutsu')
    maxl = int(sql("SELECT MAX(level) FROM naruto_monsters"))
    ruins, sonin = [], []
    for nivel in range(1, maxl + 1):  # o jogo sorteia entre nível-2 e nível: cada faixa precisa de inimigos que o ataque físico fere
        r = sql(f"SELECT COUNT(*), SUM(NOT FIND_IN_SET('taijutsu',imunidades)), SUM(NOT FIND_IN_SET('ninjutsu',imunidades)) FROM naruto_monsters WHERE level BETWEEN {max(1,nivel-2)} AND {nivel}").split('\t')
        if r[0] == '0': continue
        if int(r[1]) == 0: ruins.append(nivel)
        if int(r[2]) == 0: sonin.append(nivel)
    ok(not ruins, f'toda faixa de nível tem inimigos que o taijutsu fere {ruins}')
    print(f'  info faixas só com inimigos imunes a ninjutsu (legado, derrotáveis no físico): {sonin}')
    print('== Funções')
    r = php('echo json_encode(array(imunidadesdoinimigo(array("immune"=>0)), imunidadesdoinimigo(array("immune"=>1)), imunidadesdoinimigo(array("immune"=>2,"imunidades"=>"")), imunidadesdoinimigo(array("immune"=>1,"imunidades"=>"sono , fogo,xx")), imunidadesnormalizar("fogo,ninjutsu,sono"), imunidadeslegado(array("taijutsu","sono")), imunejutsu(array("imunidades"=>array("fogo")),"fogo"), imunejutsu(array("imunidades"=>array("fogo")),"agua"), imunejutsu(array("imune"=>1),"neutro")));')
    ok(r == '[[],["ninjutsu"],["ninjutsu","sono"],["fogo","sono"],["ninjutsu","sono"],0,"Ninjutsu de Fogo","","Ninjutsu"]', 'legado, normalização e imunejutsu: ' + r)

    print('== Luta PvE')
    ck = jogador('im_a', **BASE_J); ida = ck.split()[0]; A = Sess(ck)
    # Taijutsu: Golem de Argila (175, taijutsu,terra)
    luta(ida, 175); corpo, _ = A.get('index.php?do=fight', {'fight': 'Atacar'})
    ok(hpm(ida) == 60000 and 'imune a Taijutsu' in corpo, 'ataque físico em imune a Taijutsu: 0 de dano com mensagem')
    ok('Imune a Taijutsu' in corpo and 'Imune a Ninjutsu de Terra' in corpo, 'cartão mostra as imunidades (title)')
    luta(ida, 175); A.get('index.php?do=fight', {'spell': '1', 'userspell': '20'})
    ok(hpm(ida) < 60000, 'jutsu de fogo fere o golem (imune só a taijutsu/terra)')
    luta(ida, 175); corpo, _ = A.get('index.php?do=fight', {'fight': 'Atacar'}, idioma='en')
    ok('immune to Taijutsu' in corpo and 'Immune to Earth Ninjutsu' in corpo, 'mensagem e cartão em inglês')
    # Fogo: Lobo Rubro (3)
    for _ in range(4):
        luta(ida, 3); corpo, _ = A.get('index.php?do=fight', {'spell': '1', 'userspell': '20'})
        ok(hpm(ida) == 60000 and 'imune a Ninjutsu de Fogo' in corpo, 'jutsu de fogo (com queimadura) não fere imune a fogo')
        if hpm(ida) != 60000: break
    luta(ida, 3); A.get('index.php?do=fight', {'spell': '1', 'userspell': '21'})
    ok(hpm(ida) < 60000, 'jutsu de água fere imune a fogo')
    luta(ida, 3); A.get('index.php?do=fight', {'fight': 'Atacar'})
    ok(hpm(ida) < 60000, 'ataque físico fere imune a fogo')
    # Ninjutsu (legado immune=1): 5 Batedor Espião
    luta(ida, 5); corpo, _ = A.get('index.php?do=fight', {'spell': '1', 'userspell': '6'})
    ok(hpm(ida) == 60000 and 'imune a Ninjutsu' in corpo, 'imune a Ninjutsu: jutsu neutro sem dano')
    luta(ida, 5); A.get('index.php?do=fight', {'fight': 'Atacar'})
    ok(hpm(ida) < 60000, 'imune a Ninjutsu ainda leva dano físico')
    # Sono: Esqueleto Renascido (20) e Autômato (197)
    luta(ida, 20); corpo, _ = A.get('index.php?do=fight', {'spell': '1', 'userspell': '11'})
    ok(campo(ida, 'currentmonstersleep') == '0' and 'imune a ele' in corpo, 'imune a Sono: genjutsu de sono não funciona')
    luta(ida, 2); corpo, _ = A.get('index.php?do=fight', {'spell': '1', 'userspell': '11'})
    ok('O inimigo está dormindo' in corpo, 'sem imunidade: sono funciona')
    luta(ida, 2); corpo, _ = A.get('index.php?do=fight', {'fight': 'Atacar'})
    ok('Nenhuma' in corpo, 'cartão sem imunidade mostra Nenhuma')

    print('== Ajuda dos monstros')
    corpo, _ = A.get('help_monsters.php')
    ok('Imune a Ninjutsu de Fogo' in corpo and 'Imune a Taijutsu' in corpo and 'Imune a Sono' in corpo, 'ajuda mostra os nomes novos')
    corpo, _ = A.get('help_monsters.php?ord=immune', idioma='en')
    ok('Immune to Fire Ninjutsu' in corpo and 'Immune to Taijutsu' in corpo and 'Imune a' not in corpo, 'ajuda em inglês (ordenada por imunidade)')

    print('== Editor do admin')
    ckm = jogador('im_adm', authlevel=1, level=20, gold=10000, currentaction='In Town'); tok = csrf('im_adm')
    painel = ''
    def post(f, do='editmonster:175'):
        req = urllib.request.Request(BASE + '/admin.php' + ('?do=' + do if do else ''), data=urllib.parse.urlencode(f).encode() if f is not None else None)
        req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(ckm) + '; idioma=pt' + painel)
        class SemRedir(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *a, **k): return None
        try: r = urllib.request.build_opener(SemRedir).open(req, timeout=60)
        except urllib.error.HTTPError as e: r = e
        corpo = r.read().decode('utf8', 'replace')
        for t, m in ERRO.findall(corpo): falhas.append(f'PHP {t} em admin {do}: {re.sub("<.*?>", "", m)[:150]}')
        return corpo, r.headers
    _, h = post({'senhapainel': 'senha123', 'csrf': tok}, '')
    for sc in h.get_all('Set-Cookie') or []:
        m = re.match(r'narutorpg_painel=([^;]+)', sc)
        if m and m.group(1): painel = '; narutorpg_painel=' + m.group(1)
    ok(painel != '', 'entrou no painel do admin')
    corpo = post(None, 'editmonster:175')[0]
    ok('name="imunidades[]" value="taijutsu" checked' in corpo and 'value="terra" checked' in corpo and 'value="ninjutsu" />' in corpo, 'checkboxes marcadas conforme o banco')
    antes = sql("SELECT name,maxhp,maxdam,armor,level,maxexp,maxgold,elemento,terrenos FROM naruto_monsters WHERE id=175").split('\t')
    dados = dict(zip(['name','maxhp','maxdam','armor','level','maxexp','maxgold','elemento','terrenos'], antes)); dados['csrf'] = tok; dados['submit'] = '1'
    dados.setdefault('terrenos', '')
    drops = sql("SELECT drop_id, chance_percent, chance_variante_percent FROM naruto_monster_drops WHERE monster_id=175")
    lista = [l.split('\t') for l in drops.splitlines() if l]
    form = list(dados.items()) + [('imunidades[]', 'taijutsu'), ('imunidades[]', 'ninjutsu')] + sum([[('dropid[]', a), ('dropchance[]', b), ('dropvariante[]', c)] for a, b, c in lista], [])
    corpo = post(form)[0]
    ok('Taijutsu e a Ninjutsu' in corpo and sql("SELECT imunidades FROM naruto_monsters WHERE id=175") == 'taijutsu,terra', 'admin recusa taijutsu + ninjutsu')
    form2 = [x for x in form if x != ('imunidades[]', 'taijutsu')] + [('imunidades[]', 'sono')]
    corpo = post(form2)[0]
    ok(sql("SELECT imunidades, immune FROM naruto_monsters WHERE id=175") == 'ninjutsu,sono\t2', 'admin grava a lista e mantém immune=2 em sincronia')
    corpo = post(None, 'monsters')[0]
    ok('Ninjutsu' in corpo, 'lista do admin mostra as imunidades')
finally:
    with open(os.path.join(T, '..', 'tools', 'imunidades.sql'), 'rb') as f: subprocess.run(['mysql', '-uroot', 'narutorpg'], stdin=f, check=True)
    sql("UPDATE naruto_users SET currentaction='In Town', currentfight=0, currentmonster=0, currentmonsterhp=0 WHERE username LIKE 'im\\_%'")

print(f'\n{len(falhas)} problema(s)')
for f in falhas: print(' -', f)
sys.exit(1 if falhas else 0)
