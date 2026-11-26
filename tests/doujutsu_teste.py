#!/usr/bin/env python3
"""Teste ponta a ponta do doujutsu (funcoesdoujutsu.php, doujutsu.php, treinos olho_*; docs/DOUJUTSU.md) via HTTP.

Aquisição pelo treino (requisitos, vila, sessões, olho no fim, treino sai da lista), ativação/gasto de Chakra/fechar
sozinho (e cobrança única com páginas simultâneas), exclusão mútua com o Senjutsu, troca de olho, os três caminhos de
evolução do Sharingan (abates de inimigos fortes, vitórias em duelos e o treino), teleporte do Rinnegan (custos,
recusas, caveira exposta, corrida) e os efeitos numa luta (bônus só com o olho ativo, nada gravado nos atributos,
Byakugan mostrando os drops do inimigo). Também confere as telas em português e inglês e tira screenshots.

Uso: python3 doujutsu_teste.py http://127.0.0.1:8080 /var/www/narutorpg
"""
import sys, re, os, subprocess, threading, time, urllib.request, urllib.parse

BASE, PASTA = sys.argv[1].rstrip('/'), sys.argv[2]
TESTES = os.path.dirname(os.path.abspath(__file__))
ARTE = os.path.join(T, '..', 'arte')
ERRO = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice|Parse error)</b>:(.{0,200})')
falhas = []; oks = 0

class SemRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
abridor = urllib.request.build_opener(SemRedir)

def sql(q):
    return subprocess.check_output(['mysql', '--default-character-set=utf8mb4', '-N', '-B', 'narutorpg', '-e', q], text=True).strip()

def jogador(nome, **campos):
    return subprocess.check_output(['php', f'{TESTES}/mkuser.php', PASTA, nome] + [f'{k}={v}' for k, v in campos.items()], text=True).strip()

def token(nome):
    return subprocess.check_output(['php', f'{TESTES}/csrf.php', PASTA, nome], text=True).strip()

def pedir(cookie, caminho, dados=None, idioma='pt'):
    req = urllib.request.Request(BASE + '/' + caminho, data=urllib.parse.urlencode(dados).encode() if dados is not None else None)
    req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(cookie) + '; idioma=' + idioma)
    try:
        r = abridor.open(req, timeout=60); cod, corpo, loc = r.status, r.read().decode('utf8', 'replace'), ''
    except urllib.error.HTTPError as e:
        cod, corpo, loc = e.code, e.read().decode('utf8', 'replace'), e.headers.get('Location', '')
    for t, m in ERRO.findall(corpo): falhas.append(f'{caminho}: {t}: {re.sub("<.*?>", "", m)}')
    if 'Erro SQL' in corpo or cod >= 500: falhas.append(f'{caminho}: HTTP {cod} ' + corpo[:300])
    return cod, corpo, urllib.parse.unquote(loc)

def checar(cond, msg):
    global oks
    print(('  ok    ' if cond else '  FALHA ') + msg)
    if cond: oks += 1
    else: falhas.append(msg)

def U(nome, campos):
    v = sql(f"SELECT {campos} FROM naruto_users WHERE username='{nome}'").split('\t')
    return v if ',' in campos else v[0]

def SET(nome, sets):
    sql(f"UPDATE naruto_users SET {sets} WHERE username='{nome}'")

def paralelo(n, f):
    th = [threading.Thread(target=f) for _ in range(n)]
    [t.start() for t in th]; [t.join() for t in th]

def phpexpr(nome, expr):
    code = (f"chdir('{PASTA}'); $_GET=$_POST=$_COOKIE=array(); include('lib.php'); opendb(); include_once('funcoesinclusas.php'); include_once('funcoesjutsus.php'); include_once('funcoesdoujutsu.php');"
            f"$userrow = mysqli_fetch_array(doquery(\"SELECT * FROM {{{{table}}}} WHERE username='{nome}' LIMIT 1\", 'users')); echo {expr};")
    return subprocess.check_output(['php', '-r', code], text=True).strip()

def liberar(nome):  # última sessão de todos os treinos no passado (fim do intervalo)
    t = U(nome, 'treinamento')
    novo = ';'.join(re.sub(r'^([^,]*,\d+,\d+),[^,]*,[^,]*,', r'\1,1/1/2020,00:00:00,', p) if p else p for p in t.split(';'))
    SET(nome, f"treinamento='{novo}'")

def entrada(nome, treino):
    for p in U(nome, 'treinamento').split(';'):
        if p.split(',')[0] == treino: return p.split(',')
    return None

VILAS = {r.split('\t')[0]: r.split('\t')[1:] for r in sql("SELECT id,latitude,longitude FROM naruto_towns").split('\n')}
TR = {r.split('\t')[0]: r.split('\t') for r in sql("SELECT chave,nome,vila_id,nivel_min,graduacao_min,sessoes FROM naruto_treinos WHERE chave LIKE 'olho\\_%' OR chave='sharingan_evo'").split('\n')}
OLHOS = {(r.split('\t')[0], r.split('\t')[1]): r.split('\t') for r in sql("SELECT chave,estagio,custo_ch,intervalo,agilidade,esquiva,ataque FROM naruto_doujutsu").split('\n')}
checar(len(TR) == 6 and len(OLHOS) == 8, f'6 treinos de doujutsu e 8 olhos/estágios no banco ({len(TR)}, {len(OLHOS)})')
CTL = dict(zip('dj_abates,dj_abatemargem,dj_vitorias,dj_tele_tpbase,dj_tele_tp10,dj_tele_chbase,dj_tele_ch10,dj_tele_hpbase,dj_tele_hp10,gamesize'.split(','),
               sql("SELECT dj_abates,dj_abatemargem,dj_vitorias,dj_tele_tpbase,dj_tele_tp10,dj_tele_chbase,dj_tele_ch10,dj_tele_hpbase,dj_tele_hp10,gamesize FROM naruto_control").split('\t')))

base = dict(level=40, graduacao='Genin', currentaction='In Town', currentfight=0, maxhp=800, currenthp=800, maxmp=500, currentmp=500, maxtp=300, currenttp=300,
            maxnp=200, currentnp=200, treinamento='None', doujutsu='', doujutsu_estagio=0, doujutsu_ativo=0, doujutsu_timer=0, doujutsu_abates=0,
            doujutsu_vitbase=0, duelos_vencidos=0, senjutsuhtml='', senjutsutimer='None', batalha_timer2=0, caveira='', caveiraate=0, pagina='index.php', lutaultimaacao=0)

def treinarate(nome, ck, chave, ate_fim=True):
    """Começa o treino e faz as sessões até concluir. Devolve a última Location."""
    _, nome_tr, vila, *_r, sessoes = TR[chave]
    lat, lon = VILAS[vila]
    SET(nome, f"latitude={lat}, longitude={lon}, currentaction='In Town'")
    _, _, loc = pedir(ck, f'treinoslongos.php?do=treinar&t={chave}&inicio=1')
    for i in range(int(sessoes)):
        liberar(nome)
        _, _, loc = pedir(ck, f'treinoslongos.php?do=treinar&t={chave}')
    return loc

# ------------------------------------------------------------------ aquisição
print('== aquisição (Despertar do Sharingan)')
A = 'dj_ana'; ckA = jogador(A, **base)
_, nome_s, vila_s, nivel_s, *_ = TR['olho_sharingan']
lat, lon = VILAS[vila_s]
SET(A, f"latitude={lat}, longitude={lon}, level=5")
_, corpo, _ = pedir(ckA, 'treinoslongos.php?do=falar&t=olho_sharingan')
checar('Sharingan' in corpo and 'level' in corpo.lower(), 'fala do mestre do Sharingan com recompensa e requisitos')
_, _, loc = pedir(ckA, 'treinoslongos.php?do=treinar&t=olho_sharingan&inicio=1')
checar('level' in loc and entrada(A, nome_s) is None, 'level baixo é recusado')
SET(A, "level=40, latitude=999, longitude=999")
_, _, loc = pedir(ckA, 'treinoslongos.php?do=treinar&t=olho_sharingan&inicio=1')
checar(entrada(A, nome_s) is None, 'fora da vila do treino é recusado')
_, corpo, _ = pedir(ckA, 'cidadesconteudo.php') if False else (0, '', '')
loc = treinarate(A, ckA, 'olho_sharingan')
checar(U(A, 'doujutsu,doujutsu_estagio,doujutsu_ativo') == ['sharingan', '1', '0'], f'Sharingan despertado no estágio 1 ({U(A, "doujutsu,doujutsu_estagio,doujutsu_ativo")})')
checar(entrada(A, nome_s) is None and 'doujutsu.php' in loc, 'treino concluído sai da lista e leva à tela do doujutsu')
_, _, loc = pedir(ckA, 'treinoslongos.php?do=treinar&t=olho_sharingan&inicio=1')
checar('possui' in loc and entrada(A, nome_s) is None, 'não refaz o treino do olho que já tem')

# ------------------------------------------------------------------ ativação, gasto e fechar sozinho
print('== ativação e gasto de Chakra')
custo, passo = int(OLHOS[('sharingan', '1')][2]), int(OLHOS[('sharingan', '1')][3])
custo = max(0, round(custo * (1 + (40 - 1) / 49.5)))  # o gasto cresce com o level (doujutsucustonivel(); o jogador de teste é level 40)
SET(A, "currentmp=0")
pedir(ckA, 'doujutsu.php?do=ativar')
checar(U(A, 'doujutsu_ativo') == '0', 'sem Chakra não ativa')
SET(A, "currentmp=500, senjutsuhtml='senjutsu', senjutsutimer=DATE_FORMAT(NOW(),'%e/%c/%Y,%H:%i:%s,1')")
_, _, loc = pedir(ckA, 'doujutsu.php?do=ativar')
checar(U(A, 'doujutsu_ativo') == '0' and 'Senjutsu' in loc, 'com o Senjutsu ativo o olho não abre')
SET(A, "senjutsuhtml='fechado', senjutsutimer='None'")
paralelo(8, lambda: pedir(ckA, 'doujutsu.php?do=ativar'))
checar(U(A, 'doujutsu_ativo') == '1', 'ativou (8 cliques simultâneos)')
_, _, loc = pedir(ckA, 'senjutsu.php?do=usar')
checar(U(A, 'senjutsuhtml') == 'fechado', 'com o olho aberto o Senjutsu não ativa')
_, corpo, _ = pedir(ckA, 'index.php')
checar('id="olhodoujutsu"' in corpo and 'sharingan1.jpg' in corpo and 'senjutsu.php?do=usar' in corpo, 'coluna da direita mostra os dois olhos (Senjutsu e doujutsu)')
SET(A, f"currentmp=500, doujutsu_timer=UNIX_TIMESTAMP()-{passo * 10}")
paralelo(8, lambda: pedir(ckA, 'index.php'))
mp = int(U(A, 'currentmp'))
checar(mp == 500 - custo * 10, f'gasto de 10 intervalos cobrado uma vez só com 8 páginas simultâneas (CH {mp}, esperado {500 - custo * 10})')
SET(A, f"currentmp={custo * 3}, doujutsu_timer=UNIX_TIMESTAMP()-{passo * 50}")
pedir(ckA, 'index.php')
checar(U(A, 'doujutsu_ativo,currentmp') == ['0', '0'], 'Chakra acabou: olho fechou sozinho')
SET(A, "currentmp=500"); pedir(ckA, 'doujutsu.php?do=ativar'); pedir(ckA, 'doujutsu.php?do=desativar')
checar(U(A, 'doujutsu_ativo') == '0', 'desativar pelo ícone')

# ------------------------------------------------------------------ efeitos numa luta
print('== efeitos na luta')
mon = sql("SELECT m.id FROM naruto_monsters m JOIN naruto_monster_drops d ON d.monster_id=m.id WHERE m.level>=40 ORDER BY m.level LIMIT 1")
inativo = phpexpr(A, 'json_encode(classedojogador($userrow))')
SET(A, "doujutsu_ativo=1, doujutsu_timer=UNIX_TIMESTAMP(), currentmp=500")
ativo = phpexpr(A, 'json_encode(classedojogador($userrow))')
checar('"dj_agilidade":0' in inativo and f'"dj_agilidade":{OLHOS[("sharingan", "1")][4]}' in ativo, 'agilidade do Sharingan só entra com o olho ativo')
antes = U(A, 'agilidade,inteligencia,sorte,precisao,esquiva,critico,attackpower,defensepower')
SET(A, f"currentaction='Fighting', currentfight=2, currentmonster={mon}, currentmonsterhp=1, attackpower=5000, currenthp=800, jutsuestado=''")
antes[6] = '5000'
_, _, loc = pedir(ckA, 'index.php?do=fight', {'fight': '1'})
if 'victory' not in loc:  # o inimigo pode esquivar: tenta de novo
    for _ in range(10):
        SET(A, "currentaction='Fighting', currentfight=2, currentmonsterhp=1, currenthp=800")
        _, _, loc = pedir(ckA, 'index.php?do=fight', {'fight': '1'})
        if 'victory' in loc: break
pedir(ckA, 'index.php?do=victory')
checar(U(A, 'doujutsu_abates') == '1', f'abate de inimigo forte com o Sharingan ativo contou ({U(A, "doujutsu_abates")})')
depois = U(A, 'agilidade,inteligencia,sorte,precisao,esquiva,critico,attackpower,defensepower')
checar(depois == antes, f'nenhum bônus gravado nos atributos depois da luta ({antes} -> {depois})')
SET(A, f"currentaction='Exploring', currentfight=0, attackpower=50, level={int(sql(f'SELECT level FROM naruto_monsters WHERE id={mon}')) + int(CTL['dj_abatemargem']) + 5}")
lv = U(A, 'level'); ab = U(A, 'doujutsu_abates')
SET(A, f"currentaction='Fighting', currentfight=2, currentmonster={mon}, currentmonsterhp=1, attackpower=5000, currenthp=800, doujutsu_timer=UNIX_TIMESTAMP(), currentmp=500")
for _ in range(10):
    _, _, loc = pedir(ckA, 'index.php?do=fight', {'fight': '1'})
    if 'victory' in loc: break
    SET(A, "currentaction='Fighting', currentfight=2, currentmonsterhp=1, currenthp=800")
pedir(ckA, 'index.php?do=victory')
checar(U(A, 'doujutsu_abates') == ab, 'inimigo fraco (level abaixo da margem) não conta')
SET(A, "level=40, attackpower=50, currentaction='Exploring', currentfight=0")

# Byakugan (outro jogador): vê os drops do inimigo na tela da luta; olho fechado não vê.
B = 'dj_beto'; ckB = jogador(B, **dict(base, doujutsu='byakugan', doujutsu_estagio=1, doujutsu_ativo=1, doujutsu_timer=int(time.time())))
SET(B, f"currentaction='Fighting', currentfight=2, currentmonster={mon}, currentmonsterhp=99999, currenthp=800, latitude=60, longitude=60")
_, corpo, _ = pedir(ckB, 'index.php?do=fight')
checar('id="visaodoujutsu"' in corpo, 'Byakugan ativo mostra os drops do inimigo')
os.makedirs(ARTE, exist_ok=True)
subprocess.run(['node', f'{TESTES}/shot.js', BASE + '/index.php?do=fight', f'{ARTE}/doujutsu_luta_byakugan.png', urllib.parse.quote(ckB)], capture_output=True)
SET(B, "doujutsu_ativo=0")
_, corpo, _ = pedir(ckB, 'index.php?do=fight')
checar('id="visaodoujutsu"' not in corpo, 'Byakugan fechado não mostra')
dl = phpexpr(B, 'classedojogador(array_merge($userrow, array("doujutsu_ativo"=>1,"doujutsu_timer"=>time())))["dj_sorte"]')
checar(dl == sql("SELECT sorte FROM naruto_doujutsu WHERE chave='byakugan'"), f'Byakugan ativo soma Sorte ({dl})')
SET(B, "currentaction='Exploring', currentfight=0")

# ------------------------------------------------------------------ evolução do Sharingan
print('== evolução do Sharingan')
ab = [int(x) for x in CTL['dj_abates'].split(',')]; vi = [int(x) for x in CTL['dj_vitorias'].split(',')]
# caminho 1: abates (falta 1 para o estágio 2)
SET(A, f"doujutsu_estagio=1, doujutsu_abates={ab[0] - 1}, doujutsu_ativo=1, doujutsu_timer=UNIX_TIMESTAMP(), currentmp=500, attackpower=5000, level=40, currentaction='Fighting', currentfight=2, currentmonster={mon}, currentmonsterhp=1, currenthp=800")
for _ in range(10):
    _, _, loc = pedir(ckA, 'index.php?do=fight', {'fight': '1'})
    if 'victory' in loc: break
    SET(A, "currentaction='Fighting', currentfight=2, currentmonsterhp=1, currenthp=800")
_, corpo, _ = pedir(ckA, 'index.php?do=victory')
checar(U(A, 'doujutsu_estagio,doujutsu_abates') == ['2', '0'] and 'evolucaodoujutsu' in corpo, f'caminho dos abates: estágio 2 ({U(A, "doujutsu_estagio,doujutsu_abates")})')
SET(A, "attackpower=50, currentaction='In Town', currentfight=0")
# caminho 2: vitórias em duelos desde o estágio atual
vb = int(U(A, 'doujutsu_vitbase'))
SET(A, f"duelos_vencidos={vb + vi[1] - 1}")
pedir(ckA, 'index.php')
checar(U(A, 'doujutsu_estagio') == '2', 'uma vitória a menos: não evolui')
SET(A, f"duelos_vencidos={vb + vi[1]}")
paralelo(6, lambda: pedir(ckA, 'index.php'))
checar(U(A, 'doujutsu_estagio') == '3', f'caminho das vitórias em duelos: estágio 3 uma vez só ({U(A, "doujutsu_estagio")})')
# caminho 3: treino Evolução do Sharingan
nome_evo = TR['sharingan_evo'][1]
loc = treinarate(A, ckA, 'sharingan_evo')
checar(U(A, 'doujutsu_estagio') == '4' and entrada(A, nome_evo) is None, f'caminho do treino: estágio 4 e o treino sai da lista ({U(A, "doujutsu_estagio")})')
_, _, loc = pedir(ckA, 'treinoslongos.php?do=treinar&t=sharingan_evo&inicio=1')
checar(entrada(A, nome_evo) is None, 'no estágio 4 o treino de evolução é recusado')
_, corpo, _ = pedir(ckA, 'index.php')
checar('sharingan4.jpg' in corpo, 'ícone do estágio 4 na coluna da direita')
subprocess.run(['node', f'{TESTES}/shot.js', BASE + '/doujutsu.php', f'{ARTE}/doujutsu_sharingan4.png', urllib.parse.quote(ckA)], capture_output=True)

# ------------------------------------------------------------------ troca de olho
print('== troca de olho')
SET(A, "graduacao='Chuunin', doujutsu_ativo=1, doujutsu_timer=UNIX_TIMESTAMP()")
loc = treinarate(A, ckA, 'olho_byakugan')
checar(U(A, 'doujutsu,doujutsu_estagio,doujutsu_ativo') == ['byakugan', '1', '0'], f'Byakugan substitui o Sharingan (e fecha) ({U(A, "doujutsu,doujutsu_estagio,doujutsu_ativo")})')
_, _, loc = pedir(ckA, 'treinoslongos.php?do=treinar&t=olho_sharingan&inicio=1') if False else (0, '', '')
lat, lon = VILAS[vila_s]; SET(A, f"latitude={lat}, longitude={lon}")
pedir(ckA, 'treinoslongos.php?do=treinar&t=olho_sharingan&inicio=1')
checar(entrada(A, nome_s) is not None, 'o treino do Sharingan pode ser refeito para voltar a ele')
loc = treinarate(A, ckA, 'olho_sharingan')
checar(U(A, 'doujutsu,doujutsu_estagio') == ['sharingan', '1'], 'voltou ao Sharingan (de novo no estágio 1)')
_, _, loc = pedir(ckA, 'treinoslongos.php?do=treinar&t=olho_rinnegan&inicio=1')
checar(entrada(A, TR['olho_rinnegan'][1]) is None, 'Rinnegan exige o Sharingan no estágio 4 (e Jounin/level 60)')

# ------------------------------------------------------------------ teleporte do Rinnegan
print('== teleporte do Rinnegan')
R = 'dj_rin'; ckR = jogador(R, **dict(base, level=60, graduacao='Jounin', doujutsu='rinnegan', doujutsu_estagio=1, latitude=50, longitude=50, currentaction='Exploring'))
def tele(lat, lon, ck=None, nome=R):
    return pedir(ck or ckR, 'doujutsu.php?do=teleporte', {'csrf': token(nome), 'lat': lat, 'lon': lon})
def custo(dist):
    d = -(-dist // 10)
    return (int(CTL['dj_tele_tpbase']) + d * int(CTL['dj_tele_tp10']), int(CTL['dj_tele_chbase']) + d * int(CTL['dj_tele_ch10']), -(-800 * min(95, int(CTL['dj_tele_hpbase']) + d * int(CTL['dj_tele_hp10'])) // 100))
_, corpo, _ = tele(80, 50)
checar(U(R, 'latitude') == '50' and 'Ative o Rinnegan' in corpo, 'olho fechado: recusa')
SET(R, "doujutsu_ativo=1, doujutsu_timer=UNIX_TIMESTAMP()")
_, corpo, _ = pedir(ckR, 'doujutsu.php', {'lat': 80, 'lon': 50}) if False else (0, '', '')
_, corpo, _ = pedir(ckR, 'doujutsu.php?do=teleporte', {'csrf': 'x', 'lat': 80, 'lon': 50})
checar(U(R, 'latitude') == '50', 'token anti-CSRF errado: recusa')
g = int(CTL['gamesize'])
_, corpo, _ = tele(g + 1, 0); checar(U(R, 'latitude') == '50' and 'fora do mapa' in corpo, 'fora do mapa: recusa')
_, corpo, _ = tele(0, 0); checar(U(R, 'latitude') == '50' and 'vilas' in corpo, 'casa de vila: recusa')
_, corpo, _ = tele(50, 50); checar('nessa coordenada' in corpo, 'mesma casa: recusa')
SET(R, "currentaction='Fighting'"); tele(80, 50); checar(U(R, 'latitude') == '50', 'em luta: recusa')
SET(R, "currentaction='Exploring', batalha_timer2=5"); tele(80, 50); checar(U(R, 'latitude') == '50', 'em duelo: recusa')
SET(R, "batalha_timer2=0, caveira='branca', caveiraate=UNIX_TIMESTAMP()+3600, pvpmatouem=UNIX_TIMESTAMP()")
_, corpo, _ = tele(80, 50); checar(U(R, 'latitude') == '50' and 'caveira' in corpo, 'caveira exposta (matou há pouco): recusa (não serve para fugir)')
SET(R, "caveira='', caveiraate=0, pvpmatouem=0")
tp, ch, hp = custo(30)
SET(R, f"currenttp={tp - 1}"); _, corpo, _ = tele(80, 50); checar(U(R, 'latitude') == '50' and 'TP' in corpo, 'TP insuficiente: recusa')
SET(R, f"currenttp=300, currenthp={hp}"); _, corpo, _ = tele(80, 50); checar(U(R, 'latitude') == '50' and 'HP' in corpo, 'HP insuficiente (precisa sobrar 1): recusa')
SET(R, "currenthp=800, currentmp=500, currenttp=300, doujutsu_timer=UNIX_TIMESTAMP()")
_, _, loc = tele(80, 50)
v = U(R, 'latitude,longitude,currenttp,currentmp,currenthp,currentaction')
checar(v[:2] == ['80', '50'] and int(v[2]) == 300 - tp and 500 - ch - 2 <= int(v[3]) <= 500 - ch and int(v[4]) == 800 - hp and v[5] == 'Exploring',
       f'teleporte de 30 casas: posição e custos ({v}, esperado -{tp} TP, -{ch} CH, -{hp} HP)')
# corrida: TP para um teleporte só
SET(R, f"currenttp={custo(20)[0]}, currentmp=500, currenthp=800, doujutsu_timer=UNIX_TIMESTAMP()")
res = []
def t1(): res.append(tele(100, 50))
paralelo(8, t1)
checar(U(R, 'latitude') == '100' and int(U(R, 'currenttp')) == 0, f'8 teleportes simultâneos com TP para um: só um foi feito (TP {U(R, "currenttp")})')
subprocess.run(['node', f'{TESTES}/shot.js', BASE + '/doujutsu.php', f'{ARTE}/doujutsu_rinnegan.png', urllib.parse.quote(ckR)], capture_output=True)

# ------------------------------------------------------------------ telas pt/en
print('== telas e idiomas')
for idioma, palavras in (('pt', ['Os olhos', 'Teleporte do Rinnegan', 'Olho Celeste']), ('en', ['The eyes', 'Rinnegan Teleport', 'Celestial Eye'])):
    _, corpo, _ = pedir(ckR, 'doujutsu.php', idioma=idioma)
    checar(all(p in corpo for p in palavras), f'tela do doujutsu em {idioma}')
    _, corpo, _ = pedir(ckR, 'help.php', idioma=idioma)
    checar('name="doujutsu"' in corpo and ('Técnicas Oculares' if idioma == 'pt' else 'Eye Techniques') in corpo, f'ajuda com a seção Doujutsu em {idioma}')
_, corpo, _ = pedir(ckR, 'doujutsu.php', idioma='en')
checar('Despertar' not in corpo and 'Sessões' not in corpo, 'tela em inglês sem frases em português dos treinos')
SET(R, "doujutsu_ativo=0")
subprocess.run(['node', f'{TESTES}/shot.js', BASE + '/index.php', f'{ARTE}/doujutsu_coluna.png', urllib.parse.quote(ckA)], capture_output=True)

print(f'\n{oks} ok, {len(falhas)} problema(s)')
for f in falhas: print(' -', f)
sys.exit(1 if falhas else 0)
