#!/usr/bin/env python3
"""Banco com 30/40/50 itens (users.banco_limite) e as chaves de expansão (drops 521 e 522, consumivel "banco,ID").
Confere: limite inicial, guardar/retirar, usar a chave pela mochila, não gastar a chave à toa, chave como recompensa de missão
(formato de consumível na mochila) e os outros caminhos que guardam no banco (doar item) respeitando o limite novo.
Uso: banco_expansao_teste.py URL PASTA_DO_JOGO   (jogadores de teste com prefixo bx_)"""
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
ERRO = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice)</b>:(.*?)<br', re.S)
class Sess:
    def __init__(s, cookie): s.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())); s.cookie = cookie
    def get(s, p, dados=None):
        req = urllib.request.Request(BASE + '/' + p, data=urllib.parse.urlencode(dados).encode() if dados is not None else None)
        req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(s.cookie) + '; idioma=pt')
        try: r = s.op.open(req, timeout=60); corpo = r.read().decode('utf8', 'replace')
        except urllib.error.HTTPError as e: corpo = e.read().decode('utf8', 'replace')
        for t, m in ERRO.findall(corpo): falhas.append(f'PHP {t} em {p}: {re.sub("<.*?>", "", m)[:150]}')
        return corpo
def nbanco(u): b = sql(f"SELECT bancogeral FROM naruto_users WHERE username='{u}'"); return 0 if b in ('None', '') else len([x for x in b.split(';') if x])
vila = sql("SELECT CONCAT(latitude,' ',longitude) FROM naruto_towns WHERE id=1").split()
CID = dict(currentaction='In Town', latitude=vila[0], longitude=vila[1], level=20)

print('== Conta nova: 30 itens')
sql("DELETE FROM naruto_users WHERE username LIKE 'bx\\_%'")
A = Sess(jogador('bx_a', **CID))
ok(sql("SELECT banco_limite FROM naruto_users WHERE username='bx_a'") == '30', 'conta nova começa com banco de 30')
cheio = ';'.join(f'Kunai,3,1,X' for _ in range(29)) + ';'
sql(f"UPDATE naruto_users SET bancogeral='{cheio}', bp1='Fragmento de Vida,1,4,X', bp2='Minério de Ferro,75,4,X' WHERE username='bx_a'")
A.get('funcaoitens.php?do=depositarbp&qual=1')
ok(nbanco('bx_a') == 30, 'guarda o 30º item')
c = A.get('funcaoitens.php?do=depositarbp&qual=2')
ok(nbanco('bx_a') == 30 and sql("SELECT bp2 FROM naruto_users WHERE username='bx_a'") != 'None', '31º item é recusado (fica na mochila)')
ok('(30/30)' in A.get('funcaoitens.php?do=banco'), 'tela do banco mostra 30/30')

print('== Chave do Cofre Pequeno (40)')
sql("UPDATE naruto_users SET bp3='Chave do Cofre Pequeno,banco,521,1,2' WHERE username='bx_a'")
A.get('backpack.php?qual=3')
ok(sql("SELECT banco_limite FROM naruto_users WHERE username='bx_a'") == '40', 'usar a chave aumenta o banco para 40')
ok(sql("SELECT bp3 FROM naruto_users WHERE username='bx_a'") == 'Chave do Cofre Pequeno,banco,521,1', 'gasta uma chave da pilha')
A.get('backpack.php?qual=3')
ok(sql("SELECT bp3 FROM naruto_users WHERE username='bx_a'") == 'Chave do Cofre Pequeno,banco,521,1', 'segunda chave igual não é gasta (já tem 40)')
A.get('funcaoitens.php?do=depositarbp&qual=2')
ok(nbanco('bx_a') == 31, 'agora guarda o 31º item')
ok('(31/40)' in A.get('funcaoitens.php?do=banco'), 'tela do banco mostra 31/40')
A.get('funcaoitens.php?do=retirar&qual=30')
ok(nbanco('bx_a') == 30 and 'Minério de Ferro' in sql("SELECT CONCAT_WS('|',bp1,bp2,bp3,bp4,bp5,bp6,bp7,bp8,slot1name,slot2name,slot3name,slot4name) FROM naruto_users WHERE username='bx_a'"), 'retirar continua funcionando')

print('== Chave do Grande Cofre (50)')
cheio = ';'.join(f'Kunai,3,1,X' for _ in range(40)) + ';'
sql(f"UPDATE naruto_users SET bancogeral='{cheio}', bp4='Chave do Grande Cofre,banco,522,1', bp5='Presa de Lobo,71,4,X' WHERE username='bx_a'")
A.get('funcaoitens.php?do=depositarbp&qual=5')
ok(nbanco('bx_a') == 40, 'com 40 cheio recusa o 41º')
A.get('backpack.php?qual=4')
ok(sql("SELECT banco_limite FROM naruto_users WHERE username='bx_a'") == '50' and sql("SELECT bp4 FROM naruto_users WHERE username='bx_a'") == 'None', 'Grande Cofre leva a 50 e some da mochila')
A.get('funcaoitens.php?do=depositarbp&qual=5')
ok(nbanco('bx_a') == 41, 'guarda o 41º')
A.get('backpack.php?qual=3')
ok(sql("SELECT banco_limite FROM naruto_users WHERE username='bx_a'") == '50' and sql("SELECT bp3 FROM naruto_users WHERE username='bx_a'") != 'None', 'chave pequena não diminui nem é gasta com banco de 50')

print('== Doar item respeita o limite de quem recebe')
B = Sess(jogador('bx_b', **CID))
sql("UPDATE naruto_users SET bancogeral='" + ';'.join('Kunai,3,1,X' for _ in range(30)) + ";' WHERE username='bx_b'")
ok(sql("SELECT banco_limite FROM naruto_users WHERE username='bx_b'") == '30', 'conta B com 30')

print('== Recompensa de missão')
r = subprocess.check_output(['php', '-r', f'$_SERVER["REQUEST_METHOD"]="GET"; chdir("{PASTA}"); include "lib.php"; include_once "funcoesinclusas.php"; include_once "funcoesmissoesextras.php"; echo mx_itemcompleto("Chave do Cofre Pequeno"), "|", mx_itemcompleto("Poção Vermelha"), "|", mx_itemcompleto("Presa de Lobo");'], text=True).strip().split('\n')[-1]
ok(r == 'Chave do Cofre Pequeno,banco,521,1|Poção Vermelha,hp,50,1|Presa de Lobo,71,4,X', 'missão entrega consumíveis no formato de uso (' + r + ')')
ok(sql("SELECT recompensa_item FROM naruto_missoesextras WHERE id=128") == 'Chave do Cofre Pequeno', 'missão 128 dá a chave')

print('== Hover do item')
sql("UPDATE naruto_users SET bp6='Chave do Cofre Pequeno,banco,521,1' WHERE username='bx_a'")
c = A.get('index.php')
ok('Aumenta o seu banco para 40 itens' in c and 'Chave do Cofre Pequeno' in c and 'drops/521.gif' in c, 'mochila mostra o ícone, o nome e a explicação da chave')

sql("DELETE FROM naruto_users WHERE username LIKE 'bx\\_%'")
print('\n%d falha(s)' % len(falhas))
for f in falhas: print(' -', f)
sys.exit(1 if falhas else 0)
