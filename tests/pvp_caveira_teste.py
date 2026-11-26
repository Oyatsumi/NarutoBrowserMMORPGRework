#!/usr/bin/env python3
"""Testes do PvP no mundo 6.x (docs/PVP.md): padrões do PvP, caveira exposta (atacável offline por control.pvpcaveiraminutos
minutos depois de uma morte injusta), ataque a um jogador offline com caveira, vilas fechadas para quem tem a caveira exposta
(andar, Viajar Para, Pergaminho de Retorno), o fim do bloqueio e corridas.
Uso: pvp_caveira_teste.py URL PASTA_DO_JOGO   (jogadores de teste com prefixo pc_; as corridas são mais fortes com PHP_CLI_SERVER_WORKERS=8)
No fim a linha de naruto_control volta exatamente como estava.
"""
import sys, re, subprocess, urllib.request, urllib.parse, http.cookiejar, threading, time, os, tempfile
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
ERRO = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice)</b>:(.*?)<br', re.S)
class Sess:
    def __init__(s, cookie=None):
        s.jar = http.cookiejar.CookieJar()
        s.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.jar))
        s.cookie = cookie
    def get(s, p, dados=None, idioma='pt'):
        req = urllib.request.Request(BASE + '/' + p, data=urllib.parse.urlencode(dados, doseq=True).encode() if dados is not None else None)
        ck = ('narutorpg=' + urllib.parse.quote(s.cookie) + '; ' if s.cookie else '') + 'idioma=' + idioma
        req.add_header('Cookie', ck)
        try: r = s.op.open(req, timeout=60); corpo = r.read().decode('utf8', 'replace'); url = r.geturl()
        except urllib.error.HTTPError as e: corpo = e.read().decode('utf8', 'replace'); url = ''
        for t, m in ERRO.findall(corpo): falhas.append(f'PHP {t} em {p}: {re.sub("<.*?>", "", m)[:150]}')
        return corpo, urllib.parse.unquote(url)
def U(i, campos):
    sql(f"UPDATE naruto_users SET {campos} WHERE id={i}")
def campo(i, c):
    return sql(f"SELECT {c} FROM naruto_users WHERE id={i}")

PADROES = dict(pvpmundo=1, pvpdiflevel=30, pvpnivelseguro=11, pvpesperaalvo=60, pvpesperaataque=2, pvpperdaryou=10, pvpchancedrop=25, pvpmaxitens=1,
               pvpondedrop=1, pvpprotegervariantes=1, pvpbrancahoras=24, pvpbrancachance=25, pvpvermelhamortes=3, pvpvermelhadias=14,
               pvpvermelhabanco=0, pvpbanirdias=30, pvpbanirmetade=1, pvpcaveiraminutos=30)
COLS = ','.join(PADROES)
guardado = sql(f"SELECT {COLS} FROM naruto_control WHERE id=1").split('\t')

# ---------------------------------------------------------------- 1. Padrões
print('== Padrões do PvP no mundo')
with tempfile.TemporaryDirectory() as d:
    saida = os.path.join(d, 'naruto_rpg.sql')
    # Mesma chamada de tools/empacotar.sh (linha 9).
    subprocess.check_output(['python3', os.path.join(T, '..', 'tools', 'build_sql.py'), os.environ.get('SQL_ORIGINAL', os.path.join(T, '..', 'sql', 'original', 'databaseName.sql')), saida])
    sql("DROP DATABASE IF EXISTS narutorpg_pvpteste; CREATE DATABASE narutorpg_pvpteste CHARACTER SET utf8mb4", db='mysql')
    with open(saida, 'rb') as f:
        subprocess.run(['mysql', '-uroot', 'narutorpg_pvpteste'], stdin=f, check=True)
    novo = sql(f"SELECT {COLS} FROM naruto_control WHERE id=1", db='narutorpg_pvpteste').split('\t')
    ok(novo == [str(v) for v in PADROES.values()], f'instalação nova traz os padrões pedidos ({dict(zip(PADROES, novo))})')
    ok(sql("SHOW COLUMNS FROM naruto_users LIKE 'pvpmatouem'", db='narutorpg_pvpteste') != '', 'coluna users.pvpmatouem na instalação nova')
    sql("DROP DATABASE narutorpg_pvpteste", db='mysql')
# Migração numa instalação existente: só os padrões das colunas mudam, a escolha do administrador fica.
sql("UPDATE naruto_control SET pvpdiflevel=7 WHERE id=1")
subprocess.run(['mysql', '-uroot', 'narutorpg'], stdin=open(os.path.join(T, '..', 'tools', 'pvp_caveira.sql'), 'rb'), check=True)
ok(sql("SELECT pvpdiflevel FROM naruto_control WHERE id=1") == '7', 'pvp_caveira.sql não sobrescreve a escolha do administrador (reaplicável)')
sql("UPDATE naruto_control SET " + ','.join(f"{k}='{v}'" for k, v in zip(PADROES, guardado)) + " WHERE id=1")
corpo, _ = Sess().get('help.php')
ok('name="pvp"' in corpo and 'Caveira exposta' in corpo and 'Vilas fechadas' in corpo, 'ajuda (pt) com a seção de PvP')
corpo, _ = Sess().get('help.php?idioma=en', idioma='en')
ok('Exposed skull' in corpo and 'Closed villages' in corpo and 'Caveira exposta' not in corpo, 'ajuda (en) traduzida')

# ---------------------------------------------------------------- Preparação
# Regras de teste: sem esperas (várias lutas seguidas), o resto como os padrões.
sql("UPDATE naruto_control SET " + ','.join(f"{k}='{v}'" for k, v in PADROES.items()) + ", pvpesperaalvo=0, pvpesperaataque=0 WHERE id=1")
sql("UPDATE naruto_users SET latitude=95, longitude=95 WHERE username LIKE 'pc\\_%'")
base = dict(level=30, currentaction='Exploring', latitude=-3, longitude=40, maxhp=300, currenthp=300, attackpower=60, defensepower=40, dexterity=20, strength=20,
            batalha_timer2=0, trocajogador1='None', trocajogador2='None', authlevel=0, acesso=0, caveira='', caveiraate=0, pvpmatouem=0, gold=1000, mainmsg='None',
            historico='None', bp1='None', bp2='None', bp3='None', bp4='None', bp5='None', bp6='None', bp7='None', bp8='None', pergunta1='x', alinhamento='folha',
            banidoate=0, charclass=1, currenttp=500, towns='1')
ca = jogador('pc_a', **{**base, 'attackpower': 900})
cb = jogador('pc_b', **{**base, 'longitude': 41, 'attackpower': 1, 'defensepower': 1})
cc = jogador('pc_c', **{**base, 'longitude': 41, 'attackpower': 900})
ida, idb, idc = ca.split()[0], cb.split()[0], cc.split()[0]
ids = f"{ida},{idb},{idc}"
sql(f"DELETE FROM naruto_pvp WHERE atacante IN ({ids}) OR defensor IN ({ids})")
sql(f"UPDATE naruto_users SET onlinetime=NOW() WHERE id IN ({ids})")
A, B, C = Sess(ca), Sess(cb), Sess(cc)

# ---------------------------------------------------------------- 2. Caveira exposta
print('== Caveira exposta (offline)')
A.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': csrf('pc_a')})
ok(sql(f"SELECT COUNT(*) FROM naruto_pvp WHERE atacante={ida} AND vencedor={ida} AND injusto=1") == '1', 'A matou B (morte injusta)')
ok(campo(ida, 'caveira') == 'branca' and abs(int(campo(ida, 'pvpmatouem')) - time.time()) < 30, 'A ganhou caveira branca e pvpmatouem = agora')
# A sai do jogo (último acesso há 1 hora): continua na lista do PvP e na lista da casa de quem está perto.
U(ida, "onlinetime=NOW() - INTERVAL 1 HOUR, attackpower=1, defensepower=1, gold=1000, bp1='Kunai,3,1,X'")
U(idc, "longitude=40")
corpo, _ = C.get('index.php')
ok(f'pvp.php?alvo={ida}' in corpo and 'caveira exposta' in corpo, 'A offline aparece em "PvP: ninjas por perto" (marcado offline)')
ok('pc_a' in corpo, 'A offline aparece em "jogadores nesta casa"')
corpo, _ = C.get(f'pvp.php?alvo={ida}')
ok('name="acao" value="atacar"' in corpo, 'tela de ataque liberada contra A offline')
corpo, _ = C.get('index.php', idioma='en')
ok('exposed skull' in corpo, 'marca "offline, exposed skull" em inglês')
# Jogador offline SEM caveira continua invisível e protegido.
cd = jogador('pc_d', **{**base, 'longitude': 41})
idd = cd.split()[0]
U(idd, "onlinetime=NOW() - INTERVAL 1 HOUR")
corpo, _ = C.get('index.php')
ok(f'pvp.php?alvo={idd}' not in corpo, 'offline sem caveira não aparece')
C.get('pvp.php', {'acao': 'atacar', 'alvo': idd, 'csrf': csrf('pc_c')})
ok(sql(f"SELECT COUNT(*) FROM naruto_pvp WHERE defensor={idd}") == '0', 'offline sem caveira não pode ser atacado')
# Caveira com morte antiga (mais de 30 min): offline não fica exposto.
U(ida, "pvpmatouem=UNIX_TIMESTAMP()-31*60")
corpo, _ = C.get('index.php')
ok(f'pvp.php?alvo={ida}' not in corpo, 'depois de 30 min da morte, A offline some da lista')
C.get('pvp.php', {'acao': 'atacar', 'alvo': ida, 'csrf': csrf('pc_c')})
ok(sql(f"SELECT COUNT(*) FROM naruto_pvp WHERE defensor={ida}") == '0', 'depois de 30 min, A offline não pode ser atacado')
U(ida, "pvpmatouem=UNIX_TIMESTAMP()-60")

# Ataque ao jogador offline: perdas aplicadas, aviso na janela e no histórico.
print('== Ataque a um jogador offline com caveira')
sql("UPDATE naruto_control SET pvpchancedrop=75")  # 75 + 25 (caveira branca) = 100%: perda certa.
ouro_antes = int(campo(ida, 'gold')) + int(campo(idc, 'gold'))
C.get('pvp.php', {'acao': 'atacar', 'alvo': ida, 'csrf': csrf('pc_c')})
luta = sql(f"SELECT vencedor,ryou,registro FROM naruto_pvp WHERE atacante={idc} AND defensor={ida}").split('\t')
ok(len(luta) >= 3 and luta[0] == idc, 'C atacou A offline e venceu')
ok(len(luta) >= 3 and 'offline' in luta[2], 'registro da luta conta que A estava offline')
r = campo(ida, 'currentaction,latitude,longitude,gold').split('\t')
ok(r[0] == 'In Town' and r[1:3] == ['0', '0'], f'A derrotado volta para a vila natal mesmo com a caveira exposta ({r})')
ok(int(r[3]) == 900 and int(campo(ida, 'gold')) + int(campo(idc, 'gold')) == ouro_antes, 'Ryou: 10% passou para C, nada sumiu')
ok(campo(ida, 'bp1') == 'None' and 'Kunai,3,1,X' in campo(idc, 'bp1,bp2,bp3'), 'perda atômica: item de A foi para C (75% + 25% da caveira branca = 100%)')
sql("UPDATE naruto_control SET pvpchancedrop=25")
mm = campo(ida, 'mainmsg'); hist = campo(ida, 'historico')
ok('ATACADO' in mm and 'offline' in mm, 'A vê a janela de aviso (offline) ao voltar')
ok('enquanto estava offline' in hist, 'histórico de A registra o ataque offline')
U(ida, "onlinetime=NOW()")
corpo, _ = A.get('index.php')
ok('ATACADO' in corpo or 'mainmsg' in corpo, 'A ao voltar ao jogo recebe o aviso')

# ---------------------------------------------------------------- 3. Vilas fechadas
print('== Vilas fechadas para a caveira exposta')
U(ida, "currentaction='Exploring', latitude=-1, longitude=0, pvpmatouem=UNIX_TIMESTAMP()-60, caveira='branca', caveiraate=UNIX_TIMESTAMP()+86400, currenttp=500, gold=5000, mainmsg='None', passo_jutsu=0, repelente_passos=99, repelente_ate=UNIX_TIMESTAMP()+3600")
corpo, url = A.get('index.php?do=move', {'north': 'Norte'})
ok(campo(ida, 'latitude,longitude,currentaction') == '-1\t0\tExploring', 'andar até a vila: barrado')
ok('portões das vilas estão fechados' in url and re.search(r'mais (2\d|30) minuto', url) is not None, f'mensagem com o tempo que falta ({url[-90:]})')
corpo, url = A.get('index.php?do=move', {'north': 'Norte'}, idioma='en')
ok('village gates are closed' in url, 'mensagem em inglês')
corpo, url = A.get(f"index.php?do=gotown:1&csrf={csrf('pc_a')}")
ok(campo(ida, 'latitude,longitude,currentaction') == '-1\t0\tExploring' and campo(ida, 'currenttp') == '500' and 'portões das vilas' in url, 'Viajar Para: barrado (sem gastar TP)')
U(ida, "bp1='Pergaminho de Retorno,ret,1,X'")
A.get('backpack.php?qual=1')
ok(campo(ida, 'latitude,longitude,currentaction') == '-1\t0\tExploring' and campo(ida, 'bp1') == 'Pergaminho de Retorno,ret,1,X', 'Pergaminho de Retorno: barrado e não gasto')
U(ida, "bp1='Pergaminho de Teleporte Aleatorio,tel,1,X'")
A.get('backpack.php?qual=1')
ok(campo(ida, 'currentaction') == 'Exploring' and campo(ida, 'bp1').startswith('Pergaminho de Teleporte'), 'Pergaminho de Teleporte: barrado e não gasto')
# Andar para longe da vila continua livre.
A.get('index.php?do=move', {'south': 'Sul'})
ok(campo(ida, 'latitude') == '-2', 'andar para fora da vila continua livre')
U(ida, "latitude=-1")
# Corrida: 10 passos para dentro da vila ao mesmo tempo -> nenhum entra.
th = [threading.Thread(target=lambda: Sess(ca).get('index.php?do=move', {'north': 'Norte'})) for _ in range(10)]
[t.start() for t in th]; [t.join() for t in th]
ok(campo(ida, 'latitude,longitude,currentaction') == '-1\t0\tExploring', '10 passos simultâneos para a vila: nenhum entra')
# Fim do bloqueio: 31 minutos depois da morte a vila abre (a caveira branca continua).
U(ida, "pvpmatouem=UNIX_TIMESTAMP()-31*60")
A.get('index.php?do=move', {'north': 'Norte'})
ok(campo(ida, 'latitude,longitude,currentaction') == '0\t0\tIn Town' and campo(ida, 'caveira') == 'branca', 'depois de 30 min a vila abre (a caveira branca continua)')
# Caveira vermelha também fica exposta e barrada.
U(ida, "currentaction='Exploring', latitude=-1, caveira='vermelha', caveiraate=UNIX_TIMESTAMP()+86400*14, pvpmatouem=UNIX_TIMESTAMP()-120")
A.get('index.php?do=move', {'north': 'Norte'})
ok(campo(ida, 'latitude') == '-1', 'caveira vermelha exposta também é barrada')
# Tempo ajustável pelo administrador.
sql("UPDATE naruto_control SET pvpcaveiraminutos=1")
A.get('index.php?do=move', {'north': 'Norte'})
ok(campo(ida, 'latitude,currentaction') == '0\tIn Town', 'pvpcaveiraminutos=1: dois minutos depois a vila já abre')
sql("UPDATE naruto_control SET pvpcaveiraminutos=30")
corpo, _ = Sess(jogador('pc_adm', authlevel=1, level=10)).get('admin.php?do=main')
ok('name="pvpcaveiraminutos"' in corpo or 'senha' in corpo.lower(), 'campo "Caveira exposta" no painel (ou re-autenticação pedida)')

# ---------------------------------------------------------------- 4. Corridas no ataque offline
print('== Corridas')
# 6 atacantes diferentes atacam ao mesmo tempo o mesmo jogador offline exposto -> 1 luta só, Ryou conservado.
atac = []
for i in range(6):
    c = jogador(f'pc_r{i}', **{**base, 'longitude': 41, 'attackpower': 900})
    atac.append((c, c.split()[0], f'pc_r{i}'))
U(ida, "currentaction='Exploring', latitude=-3, longitude=40, caveira='branca', caveiraate=UNIX_TIMESTAMP()+86400, pvpmatouem=UNIX_TIMESTAMP()-60, onlinetime=NOW()-INTERVAL 1 HOUR, currenthp=300, gold=1000, attackpower=1, defensepower=1")
lista = ','.join(x[1] for x in atac)
sql(f"DELETE FROM naruto_pvp WHERE atacante IN ({lista}) OR defensor IN ({lista})")
sql(f"UPDATE naruto_users SET onlinetime=NOW() WHERE id IN ({lista})")
tokens = [csrf(x[2]) for x in atac]
soma = sql(f"SELECT SUM(gold) FROM naruto_users WHERE id IN ({lista},{ida})")
th = [threading.Thread(target=lambda c=c, t=t: Sess(c).get('pvp.php', {'acao': 'atacar', 'alvo': ida, 'csrf': t})) for (c, _, _), t in zip(atac, tokens)]
[t.start() for t in th]; [t.join() for t in th]
ok(sql(f"SELECT COUNT(*) FROM naruto_pvp WHERE defensor={ida} AND atacante IN ({lista})") == '1', '6 ataques simultâneos ao jogador offline = 1 luta')
ok(sql(f"SELECT SUM(gold) FROM naruto_users WHERE id IN ({lista},{ida})") == soma, 'Ryou conservado na corrida')

# ---------------------------------------------------------------- Limpeza
sql("UPDATE naruto_control SET " + ','.join(f"{k}='{v}'" for k, v in zip(PADROES, guardado)) + " WHERE id=1")
sql("UPDATE naruto_users SET latitude=95, longitude=95, currentaction='Exploring', onlinetime=NOW()-INTERVAL 1 DAY WHERE username LIKE 'pc\\_%'")
print('\nFALHAS:', len(falhas))
for f in falhas: print(' -', f)
sys.exit(1 if falhas else 0)
