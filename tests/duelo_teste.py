#!/usr/bin/env python3
"""Teste de ponta a ponta do duelo PvP (users.php?do=batalha1 / do=duelo) com dois jogadores (dz_ana em pt, dz_bob em en).

Uso: python3 duelo_teste.py http://127.0.0.1:8097 /var/www/narutorpg
Cobre: desafio, recusa, desafio com aposta, aceitar (apostas guardadas), turnos até o fim, tempo esgotado,
desistência, envios duplicados em paralelo (threads), entradas inválidas e efeitos de jutsu
(veneno, escudo, recarga, atordoar, dreno, ícones de status). Confere o banco depois de cada etapa.
"""
import sys, re, subprocess, threading, urllib.request, urllib.parse, time

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
    args = ['php', f'{TESTES}/mkuser.php', PASTA, nome] + [f'{k}={v}' for k, v in campos.items()]
    return subprocess.check_output(args, text=True).strip()

def token(nome):
    return subprocess.check_output(['php', f'{TESTES}/csrf.php', PASTA, nome], text=True).strip()

def pedir(cookie, idioma, caminho, dados=None):
    req = urllib.request.Request(BASE + '/' + caminho, data=urllib.parse.urlencode(dados).encode() if dados is not None else None)
    req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(cookie) + '; idioma=' + idioma)
    try:
        r = abridor.open(req, timeout=60); cod, corpo, loc = r.status, r.read().decode('utf8', 'replace'), ''
    except urllib.error.HTTPError as e:
        cod, corpo, loc = e.code, e.read().decode('utf8', 'replace'), e.headers.get('Location', '')
    for t, m in ERRO.findall(corpo): falhas.append(f'{caminho}: {t}: {re.sub("<.*?>", "", m)[:200]}')
    if 'Erro SQL' in corpo: falhas.append(f'{caminho}: SQL ' + corpo[corpo.find('Erro SQL'):][:300])
    if cod >= 500: falhas.append(f'{caminho}: HTTP {cod}')
    return cod, corpo, loc

def confere(cond, texto):
    print(('  ok   ' if cond else '  FALHA ') + texto)
    if not cond: falhas.append(texto)

def U(nome, campo):
    return sql(f"SELECT {campo} FROM naruto_users WHERE username='{nome}'")

def preparar():
    sql("UPDATE naruto_duelos SET estado='cancelado' WHERE estado IN ('pendente','ativo') AND (desafiante IN (SELECT id FROM naruto_users WHERE username IN ('dz_ana','dz_bob','dz_caio')) OR desafiado IN (SELECT id FROM naruto_users WHERE username IN ('dz_ana','dz_bob','dz_caio')))")
    base = dict(level=10, gold=5000, currentaction='In Town', latitude=0, longitude=0, currenthp=200, maxhp=200, currentmp=100, maxmp=100,
                attackpower=60, defensepower=40, strength=20, dexterity=20, spells='1,6,7,11,14,17', bp1='Poção de Vida,hp,50,X',
                bp2='Poção de Chakra,mp,40,X', bp3='None', bp4='Bomba de Papel Explosivo,bomba,473,X,3', batalha_timer2=0, batalha_nome='None', trocajogador1='None', duelos_vencidos=0, duelos_perdidos=0,
                charclass=1)  # classe fixa (Ninjutsu: sem bônus em veneno/escudo/resistência), para os valores esperados não dependerem da classe
    a = jogador('dz_ana', alinhamento='folha', avatar=3, elemento='fogo,neutro', **base).split(' ', 1)
    b = jogador('dz_bob', alinhamento='nevoa', avatar=7, elemento='agua,neutro', **base).split(' ', 1)
    return a[0], ' '.join([a[0]] + a[1].split(' ')), b[0], ' '.join([b[0]] + b[1].split(' '))

def duelo_atual():
    l = sql("SELECT id,estado,vez,turno,aposta,vencedor,atrasos1,atrasos2 FROM naruto_duelos WHERE desafiante IN (SELECT id FROM naruto_users WHERE username IN ('dz_ana','dz_bob')) ORDER BY id DESC LIMIT 1")
    return dict(zip(['id','estado','vez','turno','aposta','vencedor','atrasos1','atrasos2'], l.split('\t'))) if l else {}

idA, ckA, idB, ckB = preparar()
tA, tB = token('dz_ana'), token('dz_bob')
A = lambda c, d=None: pedir(ckA, 'pt', c, d)
B = lambda c, d=None: pedir(ckB, 'en', c, d)

print('1. Saguão nos dois idiomas')
c, corpo, _ = A('users.php?do=batalha1'); confere('Ninjas online neste lugar' in corpo and 'dz_bob' in corpo, 'saguão pt lista dz_bob')
c, corpo, _ = B('users.php?do=batalha1'); confere('Online ninjas here' in corpo and 'dz_ana' in corpo, 'saguão en lista dz_ana')

print('2. Entradas inválidas')
c, corpo, loc = A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '0'}); confere('expirou' in urllib.parse.unquote(loc), 'sem CSRF é recusado')
c, corpo, loc = A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_ana', 'aposta': '0', 'csrf': tA}); confere('mesmo' in urllib.parse.unquote(loc), 'não desafia a si mesmo')
c, corpo, loc = A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '-5', 'csrf': tA}); confere('aposta' in urllib.parse.unquote(loc).lower(), 'aposta negativa recusada')
c, corpo, loc = A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '999999', 'csrf': tA}); confere('Ryou suficiente' in urllib.parse.unquote(loc), 'aposta maior que o Ryou recusada')
c, corpo, loc = A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': "x' OR '1'='1", 'aposta': '0', 'csrf': tA}); confere('Não existe' in urllib.parse.unquote(loc), 'nome com aspas não quebra')
sql("UPDATE naruto_users SET latitude=5 WHERE username='dz_bob'")
c, corpo, loc = A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '0', 'csrf': tA}); confere('mesmo lugar' in urllib.parse.unquote(loc), 'lugar diferente recusado')
sql("UPDATE naruto_users SET latitude=0, currentaction='Fighting' WHERE username='dz_bob'")
c, corpo, loc = A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '0', 'csrf': tA}); confere('batalha' in urllib.parse.unquote(loc), 'oponente em luta PvE recusado')
sql("UPDATE naruto_users SET currentaction='In Town' WHERE username='dz_bob'")

print('3. Desafio e recusa')
c, corpo, loc = A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '0', 'csrf': tA}); confere('Desafio enviado' in urllib.parse.unquote(loc), 'desafio enviado')
d = duelo_atual(); confere(d.get('estado') == 'pendente', 'duelo pendente no banco')
confere('desafiou você' in U('dz_bob', 'historico'), 'aviso i18n no histórico do desafiado')
c, corpo, _ = B('index.php'); confere('challenged you to a duel' in corpo, 'aviso aparece em inglês para dz_bob')
c, corpo, _ = B('users.php?do=batalha1'); confere('Challenges received' in corpo and 'Accept' in corpo, 'desafio recebido aparece (en)')
c, corpo, loc = B('users.php?do=batalha1', {'acao': 'recusar', 'duelo': d['id'], 'csrf': tB}); confere(duelo_atual()['estado'] == 'recusado', 'recusado')
confere('recusou' in U('dz_ana', 'historico'), 'desafiante avisado da recusa')
c, corpo, loc = B('users.php?do=batalha1', {'acao': 'aceitar', 'duelo': d['id'], 'csrf': tB}); confere(duelo_atual()['estado'] == 'recusado', 'aceitar depois de recusar não faz nada')

print('4. Desafio com aposta + aceitar em paralelo (sem pagar duas vezes)')
c, corpo, loc = A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '1000', 'csrf': tA})
d = duelo_atual(); confere(d['estado'] == 'pendente' and d['aposta'] == '1000', 'pendente com aposta 1000')
res = []
th = [threading.Thread(target=lambda: res.append(B('users.php?do=batalha1', {'acao': 'aceitar', 'duelo': d['id'], 'csrf': tB}))) for _ in range(6)]
[t.start() for t in th]; [t.join() for t in th]
confere(U('dz_ana', 'gold') == '4000' and U('dz_bob', 'gold') == '4000', f'aposta retirada uma vez só (ana={U("dz_ana","gold")} bob={U("dz_bob","gold")})')
d = duelo_atual(); confere(d['estado'] == 'ativo', 'duelo ativo')
confere(U('dz_ana', 'batalha_timer2') == '5' and U('dz_bob', 'batalha_timer2') == '5', 'os dois marcados em duelo')
c, corpo, loc = A('index.php?do=explore'); confere('duelo' in corpo.lower(), 'não explora durante o duelo')
c, corpo, loc = A('backpack.php?qual=1'); confere(U('dz_ana', 'bp1') != 'None', 'mochila bloqueada durante o duelo')

print('5. Turnos (inclui cliques duplos em paralelo)')
nomes = {idA: ('ana', A, tA), idB: ('bob', B, tB)}
acoes = [{'acao': 'atacar'}, {'acao': 'jutsu', 'userspell': '14'}, {'acao': 'defender'}, {'acao': 'jutsu', 'userspell': '7'}, {'acao': 'pocao', 'qual': '1'}, {'acao': 'atacar'}, {'acao': 'arremesso', 'qual': '4'}]
k = 0
while duelo_atual()['estado'] == 'ativo' and k < 200:
    d = duelo_atual(); quem = nomes[d['vez']]; outro = nomes[idB if d['vez'] == idA else idA]
    dados = dict(acoes[k % len(acoes)] if k < 12 else {'acao': 'atacar'}, turno=d['turno'], csrf=quem[2])
    if k == 0:
        c, corpo, loc = outro[1]('users.php?do=duelo', dict(dados, csrf=outro[2])); confere(duelo_atual()['turno'] == d['turno'], 'quem não está na vez não joga')
        c, corpo, loc = quem[1]('users.php?do=duelo', dict(dados, turno=str(int(d['turno']) + 1))); confere(duelo_atual()['turno'] == d['turno'], 'turno errado é recusado')
        c, corpo, loc = quem[1]('users.php?do=duelo', {'acao': 'jutsu', 'userspell': '11', 'turno': d['turno'], 'csrf': quem[2]}); confere(duelo_atual()['turno'] == d['turno'], 'jutsu de sono recusado')
        c, corpo, loc = quem[1]('users.php?do=duelo', {'acao': 'jutsu', 'userspell': '9', 'turno': d['turno'], 'csrf': quem[2]}); confere(duelo_atual()['turno'] == d['turno'], 'jutsu não aprendido recusado')
        c, corpo, loc = quem[1]('users.php?do=duelo', {'acao': 'pocao', 'qual': '3', 'turno': d['turno'], 'csrf': quem[2]}); confere(duelo_atual()['turno'] == d['turno'], 'poção de slot vazio recusada')
    if k == 6:
        c, corpo, loc = quem[1]('users.php?do=duelo', {'acao': 'arremesso', 'qual': '3', 'turno': d['turno'], 'csrf': quem[2]}); confere(duelo_atual()['turno'] == d['turno'], 'arremesso de slot vazio recusado')
    if k in (1, 2):
        th = [threading.Thread(target=lambda: quem[1]('users.php?do=duelo', dados)) for _ in range(8)]
        [t.start() for t in th]; [t.join() for t in th]
        confere(int(duelo_atual()['turno']) == int(d['turno']) + 1 or duelo_atual()['estado'] == 'fim', f'8 cliques paralelos = 1 ação (turno {d["turno"]} -> {duelo_atual()["turno"]})')
    else:
        quem[1]('users.php?do=duelo', dados)
    if k == 3:
        c, corpo, _ = A('users.php?do=duelo'); confere('Registro do duelo' in corpo and 'VS' in corpo, 'tela do duelo pt')
        c, corpo, _ = B('users.php?do=duelo'); confere('Duel log' in corpo, 'tela do duelo en')
    k += 1
d = duelo_atual()
confere(d['estado'] == 'fim', f'duelo terminou em {k} ações')
venc = 'dz_ana' if d['vencedor'] == idA else 'dz_bob'; perd = 'dz_bob' if venc == 'dz_ana' else 'dz_ana'
confere(U(venc, 'gold') == '6000' and U(perd, 'gold') == '4000', f'vencedor {venc} levou 2000 Ryou')
confere(U(perd, 'currenthp') == '1', 'perdedor ficou com 1 HP (ninguém morre)')
confere(U(venc, 'duelos_vencidos') == '1' and U(perd, 'duelos_perdidos') == '1', 'placar atualizado')
confere(U('dz_ana', 'batalha_timer2') == '0' and U('dz_bob', 'batalha_timer2') == '0', 'marca de duelo limpa')
c, corpo, _ = A('users.php?do=duelo&ver=' + d['id']); confere('duelo' in corpo, 'tela de resultado pt')
c, corpo, _ = B('users.php?do=duelo&ver=' + d['id']); confere('duel' in corpo.lower(), 'tela de resultado en')

print('6. Tempo esgotado (3 vezes = derrota) e desistência')
sql("UPDATE naruto_users SET currenthp=200, currentmp=100 WHERE username IN ('dz_ana','dz_bob')")
A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '0', 'csrf': tA})
d = duelo_atual(); B('users.php?do=batalha1', {'acao': 'aceitar', 'duelo': d['id'], 'csrf': tB})
d = duelo_atual(); confere(d['estado'] == 'ativo', 'duelo 2 ativo')
sql(f"UPDATE naruto_duelos SET prazo=UNIX_TIMESTAMP()-1 WHERE id={d['id']}")
A('users.php?do=duelo'); d2 = duelo_atual()
confere(d2['vez'] != d['vez'] and (d2['atrasos1'] == '1' or d2['atrasos2'] == '1'), 'tempo esgotado: perdeu a vez')
sql(f"UPDATE naruto_duelos SET prazo=UNIX_TIMESTAMP()-{60*5+1} WHERE id={d['id']}")
B('users.php?do=duelo'); d3 = duelo_atual()
confere(d3['estado'] == 'fim' and d3['vencedor'] != d['vez'], f'3 tempos esgotados encerram o duelo (vencedor {d3["vencedor"]})')
sql("UPDATE naruto_users SET currenthp=200 WHERE username IN ('dz_ana','dz_bob')")
A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '500', 'csrf': tA})
d = duelo_atual(); B('users.php?do=batalha1', {'acao': 'aceitar', 'duelo': d['id'], 'csrf': tB})
ga, gb = int(U('dz_ana', 'gold')), int(U('dz_bob', 'gold'))
th = [threading.Thread(target=lambda: B('users.php?do=duelo', {'acao': 'desistir', 'turno': '1', 'csrf': tB})) for _ in range(6)]
[t.start() for t in th]; [t.join() for t in th]
d = duelo_atual(); confere(d['estado'] == 'fim' and d['vencedor'] == idA, 'desistência: dz_ana vence')
confere(int(U('dz_ana', 'gold')) == ga + 1000 and int(U('dz_bob', 'gold')) == gb, 'prêmio pago uma vez só')
confere(sql(f"SELECT COUNT(*) FROM naruto_duelos WHERE estado='ativo' AND (desafiante={idA} OR desafiado={idA})") == '0', 'nenhum duelo ativo sobrando')

print('7. Rank e ficha')
c, corpo, _ = A('rank.php?ord=duelos'); confere('dz_ana' in corpo, 'rank de duelos lista dz_ana')
c, corpo, _ = B('rank.php?ord=duelos'); confere('Duels' in corpo, 'rank de duelos em inglês')
c, corpo, _ = A('index.php?do=showchar'); confere('Duelos' in corpo, 'ficha mostra duelos')

print('8. Efeitos de jutsu no duelo (veneno, escudo, recarga, atordoar, dreno, ícones)')
import json
sql("UPDATE naruto_users SET currenthp=200, currentmp=100, spells='1,6,7,14,17,22,26,43' WHERE username IN ('dz_ana','dz_bob')")
A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '0', 'csrf': tA})
d = duelo_atual(); B('users.php?do=batalha1', {'acao': 'aceitar', 'duelo': d['id'], 'csrf': tB})
d = duelo_atual(); confere(d['estado'] == 'ativo', 'duelo 3 ativo')
did = d['id']
lado = lambda i: '1' if i == idA else '2'
est = lambda: json.loads(sql(f"SELECT jutsuestado FROM naruto_duelos WHERE id={did}") or '{}')
reg = lambda: sql(f"SELECT registro FROM naruto_duelos WHERE id={did}")
X = d['vez']; Y = idB if X == idA else idA
nx, ny = ('dz_ana', 'dz_bob') if X == idA else ('dz_bob', 'dz_ana')
px, py = nomes[X], nomes[Y]
px[1]('users.php?do=duelo', {'acao': 'jutsu', 'userspell': '26', 'turno': '1', 'csrf': px[2]})
e = est(); confere('veneno' in e.get('efeitos', {}).get(lado(Y), {}), f'veneno no lado {lado(Y)} ({e})')
confere('envenenado' in reg(), 'registro: envenenado')
hp_y = int(U(ny, 'currenthp'))
py[1]('users.php?do=duelo', {'acao': 'jutsu', 'userspell': '22', 'turno': '2', 'csrf': py[2]})
e = est(); confere('escudo' in e['efeitos'].get(lado(Y), {}) and '22' in e.get('cd', {}).get(lado(Y), {}), f'escudo ativo e recarga gravada ({e})')
confere(int(U(ny, 'currenthp')) == hp_y - 6 and 'dano do veneno' in reg(), f'fim da rodada: veneno tirou 6 HP ({hp_y} -> {U(ny, "currenthp")})')
confere(duelo_atual()['turno'] == '3' and duelo_atual()['vez'] == X, 'turno 3, vez de quem começou')
c, corpo, _ = pedir(ckA if X == idA else ckB, 'pt' if X == idA else 'en', 'users.php?do=duelo')
confere('images/status/veneno.png' in corpo and 'images/status/escudo.png' in corpo, 'ícones de status nos cartões')
for _ in range(4):  # ataques até o escudo absorver algo (pode desviar)
    t0 = duelo_atual()['turno']
    if duelo_atual()['vez'] != X: break
    px[1]('users.php?do=duelo', {'acao': 'atacar', 'turno': t0, 'csrf': px[2]})
    if 'escudo absorveu' in reg(): break
    if duelo_atual()['vez'] == Y: py[1]('users.php?do=duelo', {'acao': 'defender', 'turno': duelo_atual()['turno'], 'csrf': py[2]})
confere('escudo absorveu' in reg(), 'escudo absorveu dano do ataque')
d = duelo_atual()
if d['vez'] == X: px[1]('users.php?do=duelo', {'acao': 'defender', 'turno': d['turno'], 'csrf': px[2]}); d = duelo_atual()
if '22' in est().get('cd', {}).get(lado(Y), {}):
    c, corpo, _ = py[1]('users.php?do=duelo')
    confere(re.search(r'<button type="submit" name="userspell" value="22"[^>]*disabled="disabled".*?(recarga|cooldown): \d', corpo, re.S) is not None, 'jutsu em recarga desabilitado no cartão, com os turnos')
    c, corpo, loc = py[1]('users.php?do=duelo', {'acao': 'jutsu', 'userspell': '22', 'turno': d['turno'], 'csrf': py[2]})
    confere(duelo_atual()['turno'] == d['turno'] and ('cooldown' in urllib.parse.unquote(loc) or 'recarga' in urllib.parse.unquote(loc)), 'jutsu em recarga recusado no servidor')
# Atordoar: injeta atordoamento no X; Y joga, a vez de X é pulada (não conta como tempo esgotado).
d = duelo_atual(); e = est(); e.setdefault('efeitos', {}).setdefault(lado(X), {})['atordoar'] = {'v': 0, 't': 2, 'n': 'Punho Faiscante'}
sql(f"UPDATE naruto_duelos SET jutsuestado='{json.dumps(e)}' WHERE id={did}")
atr = d['atrasos1'] + d['atrasos2']; mp_x = int(U(nx, 'currentmp'))
py[1]('users.php?do=duelo', {'acao': 'jutsu', 'userspell': '43', 'turno': d['turno'], 'csrf': py[2]})
d2 = duelo_atual()
confere(d2['vez'] == Y and int(d2['turno']) == int(d['turno']) + 2 and d2['atrasos1'] + d2['atrasos2'] == atr, f'atordoado perde a vez sem contar atraso (turno {d["turno"]} -> {d2["turno"]})')
confere('atordoado e perde a vez' in reg(), 'registro: atordoado perde a vez')
confere(int(U(nx, 'currentmp')) < mp_x and 'drenou' in reg(), f'dreno de chakra ({mp_x} -> {U(nx, "currentmp")})')
c, corpo, _ = B('users.php?do=duelo'); confere('is stunned and loses the turn' in corpo or 'drained' in corpo, 'registro de efeitos em inglês')
sql("UPDATE naruto_users SET currenthp=200, currentmp=100 WHERE username IN ('dz_ana','dz_bob')")
c, corpo, _ = A('users.php?do=duelo'); open('/tmp/duelo_efeitos.html', 'w').write(corpo)
A('users.php?do=duelo', {'acao': 'desistir', 'turno': duelo_atual()['turno'], 'csrf': tA})
confere(duelo_atual()['estado'] == 'fim' and est() == {}, 'fim do duelo apaga os efeitos')
sql("UPDATE naruto_users SET spells='1,6,7,11,14,17' WHERE username IN ('dz_ana','dz_bob')")

print('Logout no meio do duelo = desistência (perde a aposta)')
sql("UPDATE naruto_users SET gold=5000, currenthp=200, currentmp=100 WHERE username IN ('dz_ana','dz_bob')")
tA, tB = token('dz_ana'), token('dz_bob')
A('users.php?do=batalha1', {'acao': 'desafiar', 'jogador': 'dz_bob', 'aposta': '300', 'csrf': tA})
d = duelo_atual(); B('users.php?do=batalha1', {'acao': 'aceitar', 'duelo': d['id'], 'csrf': tB})
confere(duelo_atual()['estado'] == 'ativo', 'duelo com aposta 300 ativo')
pedir(ckB, 'en', 'login.php?do=logout')
d = duelo_atual()
confere(d['estado'] == 'fim' and d['vencedor'] == idA, f"logout de dz_bob = derrota (vencedor {d.get('vencedor')})")
confere(int(U('dz_ana', 'gold')) == 5300 and int(U('dz_bob', 'gold')) == 4700, f"aposta paga ao vencedor (ana {U('dz_ana','gold')}, bob {U('dz_bob','gold')})")
idB_, ckB = idB, jogador('dz_bob')
print('\nFALHAS:' if falhas else '\nTudo certo.')
for f in falhas: print(' -', f)
