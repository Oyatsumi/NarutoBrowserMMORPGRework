#!/usr/bin/env python3
"""Painel do GM: teleporte, mensagem para todos, caveiras e Mapa do jogo (com as permissões control.gm_teleporte / gm_mapa),
e o acesso do administrador ao painel do GM.
Uso: gm_extras_teste.py URL PASTA_DO_JOGO   (jogadores de teste com prefixo gx_)"""
import sys, re, subprocess, urllib.request, urllib.parse, http.cookiejar
import os
BASE, PASTA = sys.argv[1].rstrip('/'), sys.argv[2]
T = os.path.dirname(os.path.abspath(__file__))  # pasta dos testes
falhas = []
def ok(c, m):
    print(('  ok   ' if c else '  FALHA ') + m)
    if not c: falhas.append(m)
def sql(q): return subprocess.check_output(['mysql', '-uroot', '--default-character-set=utf8mb4', '-N', 'narutorpg', '-e', q], text=True).strip()
def jogador(nome, **c): return subprocess.check_output(['php', f'{T}/mkuser.php', PASTA, nome] + [f'{k}={v}' for k, v in c.items()], text=True).strip()
def csrf(nome): return subprocess.check_output(['php', f'{T}/csrf.php', PASTA, nome], text=True).strip()
ERRO = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice)</b>:(.*?)<br', re.S)
class Sess:
    def __init__(s, cookie): s.jar = http.cookiejar.CookieJar(); s.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.jar)); s.cookie = cookie
    def get(s, p, dados=None):
        req = urllib.request.Request(BASE + '/' + p, data=urllib.parse.urlencode(dados).encode() if dados is not None else None)
        extra = '; '.join(f'{c.name}={c.value}' for c in s.jar if c.name != 'narutorpg')
        req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(s.cookie) + '; ' + extra + '; idioma=pt')
        try: r = s.op.open(req, timeout=60); corpo = r.read().decode('utf8', 'replace')
        except urllib.error.HTTPError as e: corpo = e.read().decode('utf8', 'replace')
        for t, m in ERRO.findall(corpo): falhas.append(f'PHP {t} em {p}: {re.sub("<.*?>", "", m)[:150]}')
        return corpo

sql("UPDATE naruto_control SET gm_teleporte=1, gm_mapa=1")
GM = Sess(jogador('gx_gm', acesso=3, authlevel=0, level=10))
ADM = Sess(jogador('gx_adm', acesso=0, authlevel=1, level=10))
idj = jogador('gx_j', level=10, currentaction='Fighting', currentfight=1, latitude=3, longitude=4, mainmsg='None', historico='None').split()[0]
GM.get('gm.php', {'senhapainel': 'senha123', 'csrf': csrf('gx_gm')})
ADM.get('gm.php', {'senhapainel': 'senha123', 'csrf': csrf('gx_adm')})

print('== Menu')
c = GM.get('gm.php')
ok('do=mensagemglobal' in c and 'do=caveiras' in c and 'do=mapa' in c, 'menu do GM tem Mensagem para todos, Caveiras e Mapa do jogo')
ok('Painel do Administrador' not in c, 'GM não vê link do painel do administrador')
c = ADM.get('gm.php')
ok('admconteudo' in c and 'Painel do Administrador' in c, 'administrador abre o painel do GM (com link de volta ao admin)')
c = ADM.get('index.php')
ok('href="gm.php"' in c and 'href="admin.php"' in c, 'no jogo o administrador vê os links dos dois painéis')

print('== Teleporte')
c = GM.get(f'gm.php?do=jogador:{idj}')
ok('value="teleportar"' in c, 'ficha do jogador tem o formulário de teleporte')
GM.get(f'gm.php?do=jogador:{idj}', {'acao': 'teleportar', 'lat': '12', 'lon': '-7', 'csrf': csrf('gx_gm')})
ok(sql(f"SELECT CONCAT(latitude,',',longitude,',',currentaction,',',currentfight) FROM naruto_users WHERE id={idj}") == '12,-7,Exploring,0', 'GM teleporta (encerra a luta e deixa explorando)')
ok(sql(f"SELECT COUNT(*) FROM naruto_adminlog WHERE acao='gm_teleportar' AND alvo_id={idj}") != '0', 'teleporte vai para a auditoria')
vila = sql("SELECT CONCAT(latitude,' ',longitude) FROM naruto_towns WHERE id=1").split()
GM.get(f'gm.php?do=jogador:{idj}', {'acao': 'teleportar', 'lat': vila[0], 'lon': vila[1], 'csrf': csrf('gx_gm')})
ok(sql(f"SELECT currentaction FROM naruto_users WHERE id={idj}") == 'In Town', 'teleporte para a coordenada de uma vila deixa dentro da vila')
c = GM.get(f'gm.php?do=jogador:{idj}', {'acao': 'teleportar', 'lat': '99999', 'lon': '0', 'csrf': csrf('gx_gm')})
ok('fora do mapa' in c and sql(f"SELECT latitude FROM naruto_users WHERE id={idj}") == vila[0], 'coordenada fora do mapa é recusada')
GM.get(f'gm.php?do=jogador:{idj}', {'acao': 'teleportar', 'lat': '5', 'lon': '5'})
ok(sql(f"SELECT latitude FROM naruto_users WHERE id={idj}") == vila[0], 'sem CSRF não teleporta')
sql("UPDATE naruto_control SET gm_teleporte=0")
c = GM.get(f'gm.php?do=jogador:{idj}')
ok('value="teleportar"' not in c, 'com gm_teleporte=0 o formulário some para o GM')
GM.get(f'gm.php?do=jogador:{idj}', {'acao': 'teleportar', 'lat': '5', 'lon': '5', 'csrf': csrf('gx_gm')})
ok(sql(f"SELECT latitude FROM naruto_users WHERE id={idj}") == vila[0], 'com gm_teleporte=0 o GM não teleporta (POST direto)')
ADM.get(f'gm.php?do=jogador:{idj}', {'acao': 'teleportar', 'lat': '6', 'lon': '6', 'csrf': csrf('gx_adm')})
ok(sql(f"SELECT latitude FROM naruto_users WHERE id={idj}") == '6', 'o administrador teleporta mesmo com gm_teleporte=0')
sql("UPDATE naruto_control SET gm_teleporte=1")

print('== Mensagem para todos')
GM.get('gm.php?do=mensagemglobal', {'titulo': 'Aviso <b>x</b>', 'texto': 'Manutenção às 22h', 'imagem': 'naruto', 'somenteonline': '0', 'enviar': '1', 'csrf': csrf('gx_gm')})
m = sql(f"SELECT mainmsg FROM naruto_users WHERE id={idj}")
ok('Manutenção às 22h' in m and '<b>' not in m, 'mensagem chega aos jogadores (HTML escapado)')
ok(sql("SELECT COUNT(*) FROM naruto_adminlog WHERE acao='gm_mensagem_todos'") != '0', 'mensagem para todos vai para a auditoria')
sql(f"UPDATE naruto_users SET mainmsg='None' WHERE id={idj}")
GM.get('gm.php?do=mensagemglobal', {'titulo': 'X', 'texto': 'Y', 'imagem': 'naruto', 'somenteonline': '0', 'enviar': '1'})
ok(sql(f"SELECT mainmsg FROM naruto_users WHERE id={idj}") == 'None', 'sem CSRF não envia')

print('== Caveiras e mapa')
sql(f"UPDATE naruto_users SET caveira='vermelha', caveiraate=UNIX_TIMESTAMP()+3600 WHERE id={idj}")
c = GM.get('gm.php?do=caveiras')
ok('gx_j' in c and 'Limpar caveira' not in c, 'GM vê as caveiras (só leitura)')
sql(f"UPDATE naruto_users SET caveira='', caveiraate=0 WHERE id={idj}")
c = GM.get('gm.php?do=mapa')
ok('Regiões dos inimigos' in c and 'do=editmonster' not in c and 'do=edittown' not in c, 'GM vê o Mapa do jogo sem links de edição')
c = ADM.get('admin.php?do=mapajogo', {'senhapainel': 'senha123', 'csrf': csrf('gx_adm')}) and ADM.get('admin.php?do=mapajogo')
ok('do=editmonster' in c or 'do=edittown' in c, 'no admin o Mapa do jogo continua com os links de edição')
sql("UPDATE naruto_control SET gm_mapa=0")
c = GM.get('gm.php?do=mapa')
ok('não permite' in c and 'Regiões dos inimigos' not in c, 'com gm_mapa=0 o GM não vê o mapa')
ok('do=mapa"' not in GM.get('gm.php'), 'com gm_mapa=0 o link some do menu')
ok('Regiões dos inimigos' in ADM.get('gm.php?do=mapa'), 'o administrador vê o mapa no painel do GM mesmo com gm_mapa=0')
sql("UPDATE naruto_control SET gm_mapa=1")

sql("DELETE FROM naruto_users WHERE username LIKE 'gx\\_%'")
print('\n%d falha(s)' % len(falhas))
for f in falhas: print(' -', f)
sys.exit(1 if falhas else 0)
