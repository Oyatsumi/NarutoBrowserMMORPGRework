#!/usr/bin/env python3
"""PvP: níveis perdidos com a caveira vermelha (control.pvpbanirniveis no banimento automático e
pvpvermelhaperdamin..max ao morrer com caveira vermelha), conferindo que os atributos voltam aos de um nível mais baixo.
Uso: pvp_niveis_teste.py URL PASTA_DO_JOGO   (jogadores de teste com prefixo pn_). A linha de naruto_control volta como estava.
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
        ck = ('narutorpg=' + urllib.parse.quote(s.cookie) + '; ' if s.cookie else '') + ''.join(f'{c.name}={c.value}; ' for c in s.jar if c.name != 'narutorpg') + 'idioma=' + idioma
        req.add_header('Cookie', ck)
        try: r = s.op.open(req, timeout=60); corpo = r.read().decode('utf8', 'replace'); url = r.geturl()
        except urllib.error.HTTPError as e: corpo = e.read().decode('utf8', 'replace'); url = ''
        for t, m in ERRO.findall(corpo): falhas.append(f'PHP {t} em {p}: {re.sub("<.*?>", "", m)[:150]}')
        return corpo, urllib.parse.unquote(url)
def U(i, campos):
    sql(f"UPDATE naruto_users SET {campos} WHERE id={i}")
def campo(i, c):
    return sql(f"SELECT {c} FROM naruto_users WHERE id={i}")

COLS = 'pvpmundo,pvpdiflevel,pvpnivelseguro,pvpesperaalvo,pvpesperaataque,pvpvermelhamortes,pvpbanirmetade,pvpbanirniveis,pvpvermelhaperdamin,pvpvermelhaperdamax,pvpchancedrop'
guardado = sql(f"SELECT {COLS} FROM naruto_control WHERE id=1").split('\t')
sql("UPDATE naruto_control SET pvpmundo=1, pvpdiflevel=99, pvpnivelseguro=1, pvpesperaalvo=0, pvpesperaataque=0, pvpvermelhamortes=3, pvpbanirmetade=0, pvpbanirniveis=5, pvpvermelhaperdamin=1, pvpvermelhaperdamax=5, pvpchancedrop=0 WHERE id=1")
sql("DELETE FROM naruto_users WHERE username LIKE 'pn\\\\_%'")
def ganhos(c, de, ate):  # soma dos ganhos de level up de (de, ate]
    r = sql(f"SELECT SUM({c}_hp),SUM({c}_mp),SUM({c}_strength),SUM({c}_dexterity) FROM naruto_levels WHERE id>{de} AND id<={ate}").split('\t')
    return [int(x) for x in r]
base = dict(level=30, currentaction='Exploring', latitude=-3, longitude=40, maxhp=3000, currenthp=3000, maxmp=800, currentmp=800, attackpower=60, defensepower=40,
            dexterity=200, strength=200, maxnp=200, pontoatributos=50, authlevel=0, acesso=0, caveira='', caveiraate=0, pvpmatouem=0, gold=1000,
            mainmsg='None', historico='None', pergunta1='x', alinhamento='folha', banidoate=0, charclass=1, currenttp=500, towns='1',
            bp1='None', bp2='None', bp3='None', bp4='None', bp5='None', bp6='None', bp7='None', bp8='None')
ca = jogador('pn_a', **{**base, 'attackpower': 900})
cb = jogador('pn_b', **{**base, 'longitude': 41, 'attackpower': 1, 'defensepower': 1})
cc = jogador('pn_c', **{**base, 'longitude': 41, 'attackpower': 900})
ida, idb, idc = ca.split()[0], cb.split()[0], cc.split()[0]
sql(f"UPDATE naruto_users SET onlinetime=NOW() WHERE id IN ({ida},{idb},{idc})")
A, C = Sess(ca), Sess(cc)
hp, mp, fo, de = ganhos(1, 25, 30)

print('== Banimento automático: perde 5 níveis')
U(ida, "caveira='vermelha', caveiraate=UNIX_TIMESTAMP()+86400*14")
A.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': csrf('pn_a')})
r = campo(ida, 'authlevel,level,maxhp,maxmp,strength,dexterity,attackpower,defensepower,maxnp,pontoatributos,experience').split('\t')
ok(r[0] == '2', 'A matou com caveira vermelha e foi banido')
ok(r[1] == '25', f'A caiu do level 30 para o 25 ({r[1]})')
ok([int(x) for x in r[2:8]] == [3000 - hp, 800 - mp, 200 - fo, 200 - de, 900 - fo, 40 - de], f'HP/Chakra/Força/Destreza/Ataque/Defesa voltam pelos ganhos dos 5 níveis ({r[2:8]})')
ok(r[8:10] == ['175', '25'], f'NP máximo e pontos livres -25 ({r[8:10]})')
exp25 = sql("SELECT 1_exp FROM naruto_levels WHERE id=25")
ok(r[10] == exp25, 'experiência no início do level 25')
luta = sql(f"SELECT registro FROM naruto_pvp WHERE atacante={ida} ORDER BY id DESC LIMIT 1")
ok('perdeu %s nível(is) pela caveira vermelha.|pn_a|5' in luta, 'registro da luta conta os níveis perdidos')
ok('5 nível' in sql(f"SELECT detalhes FROM naruto_adminlog WHERE acao='pvp_banimento_automatico' AND alvo_id={ida} ORDER BY id DESC LIMIT 1").replace('níveis perdidos: 5', '5 nível'), 'auditoria registra os níveis perdidos')

print('== Morte com caveira vermelha: perde de 1 a 5 níveis')
perdas = set()
for i in range(8):
    U(idb, "level=30, maxhp=3000, currenthp=3000, maxmp=800, strength=200, dexterity=200, attackpower=1, defensepower=1, maxnp=200, pontoatributos=50, "
          "caveira='vermelha', caveiraate=UNIX_TIMESTAMP()+86400, currentaction='Exploring', latitude=-3, longitude=41, onlinetime=NOW()")
    U(idc, "currentaction='Exploring', latitude=-3, longitude=41, currenthp=3000, caveira='', caveiraate=0, onlinetime=NOW()")
    sql(f"DELETE FROM naruto_pvp WHERE atacante={idc} OR defensor={idc}")
    C.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': csrf('pn_c')})
    nv = int(campo(idb, 'level'))
    perdas.add(30 - nv)
ok(perdas and all(1 <= p <= 5 for p in perdas), f'cada morte tira entre 1 e 5 níveis ({sorted(perdas)})')
ok(len(perdas) > 1, 'a quantidade é sorteada (mais de um valor em 8 mortes)')
sql("UPDATE naruto_control SET pvpvermelhaperdamin=0, pvpvermelhaperdamax=0 WHERE id=1")
U(idb, "level=30, caveira='vermelha', caveiraate=UNIX_TIMESTAMP()+86400, currentaction='Exploring', latitude=-3, longitude=41, onlinetime=NOW()")
U(idc, "currentaction='Exploring', latitude=-3, longitude=41, onlinetime=NOW()")
C.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': csrf('pn_c')})
ok(campo(idb, 'level') == '30', 'com 0 a 0 no admin, não perde níveis')
U(idb, "level=7, caveira='vermelha', caveiraate=UNIX_TIMESTAMP()+86400, currentaction='Exploring', latitude=-3, longitude=41, onlinetime=NOW()")
sql("UPDATE naruto_control SET pvpvermelhaperdamin=5, pvpvermelhaperdamax=5 WHERE id=1")
U(idc, "currentaction='Exploring', latitude=-3, longitude=41, onlinetime=NOW()")
C.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': csrf('pn_c')})
ok(campo(idb, 'level') == '2', 'level 7 com perda de 5 vai para o 2 (os níveis baixos são protegidos do PvP)')
r_ = subprocess.check_output(['php', '-r', f'$_SERVER["REQUEST_METHOD"]="GET"; chdir("{PASTA}"); define("USERS_SOMENTE_FUNCOES", true); include "lib.php"; opendb(); $controlrow=mysqli_fetch_array(doquery("SELECT * FROM {{{{table}}}} WHERE id=1","control")); $s=file_get_contents("pvp.php"); $i=strpos($s, "function pvpperderniveis"); eval(substr($s, $i, strpos($s, "function pvpperdermetade")-$i)); echo pvpperderniveis({idb}, 9);'], text=True).strip().split('\n')[-1]
ok(r_ == '1' and campo(idb, 'level') == '1', f'nunca fica abaixo do level 1 (tirou {r_})')
U(idb, "caveira='', caveiraate=0, level=30, currentaction='Exploring', latitude=-3, longitude=41, onlinetime=NOW()")
U(idc, "currentaction='Exploring', latitude=-3, longitude=41, onlinetime=NOW()")
C.get('pvp.php', {'acao': 'atacar', 'alvo': idb, 'csrf': csrf('pn_c')})
ok(campo(idb, 'level') == '30', 'sem caveira vermelha, morrer não tira níveis')

print('== Admin')
adm = Sess(jogador('pn_adm', authlevel=1, level=10))
corpo, _ = adm.get('admin.php?do=main')
corpo, _ = adm.get('admin.php?do=main', {'senhapainel': 'senha123', 'csrf': csrf('pn_adm')})
corpo, _ = adm.get('admin.php?do=main')
ok('name="pvpbanirniveis"' in corpo and 'name="pvpvermelhaperdamin"' in corpo and 'name="pvpvermelhaperdamax"' in corpo, 'campos no painel (Configurações gerais > PvP)')

sql("UPDATE naruto_control SET " + ','.join(f"{k}='{v}'" for k, v in zip(COLS.split(','), guardado)) + " WHERE id=1")
sql("DELETE FROM naruto_users WHERE username LIKE 'pn\\\\_%'")
print('\n%d falha(s)' % len(falhas))
for f in falhas: print(' -', f)
sys.exit(1 if falhas else 0)
