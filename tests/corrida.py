#!/usr/bin/env python3
"""Testes de condição de corrida (race condition) do Naruto RPG: duplicação de itens e Ryou.

Cada cenário prepara jogadores de teste direto no banco, dispara VÁRIAS requisições ao mesmo tempo (threads
liberadas juntas por uma barreira, como cliques duplos/duas abas/script) e depois confere no banco se itens e
Ryou foram conservados (nada criado do nada).

Uso: python3 corrida.py http://127.0.0.1:8091 /var/www/narutorpg [N]
O servidor precisa atender em paralelo:  PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8091
Saída: OK/FALHOU por cenário; código de saída 1 se algum falhar.
"""
import sys, subprocess, threading, urllib.request, urllib.parse, hashlib

BASE, PASTA = sys.argv[1].rstrip('/'), sys.argv[2]
N = int(sys.argv[3]) if len(sys.argv) > 3 else 20
TESTES = __file__.rsplit('/', 1)[0]
MYSQL = ['mysql', '-uroot', '--default-character-set=utf8mb4', '-N', '-B', 'narutorpg']
falhas = []


class SemRedirecionar(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
abridor = urllib.request.build_opener(SemRedirecionar)


def sql(q):
    return subprocess.check_output(MYSQL + ['-e', q], text=True)


def esc(v):
    return str(v).replace('\\', '\\\\').replace("'", "\\'")


def jogador(nome, **campos):
    """Cria/zera o jogador de teste e devolve (id, cookie)."""
    base = dict(level=20, gold=0, banco_grana=0, currentaction='In Town', latitude=0, longitude=0,
                bp1='None', bp2='None', bp3='None', bp4='None', bp5='None', bp6='None', bp7='None', bp8='None', bancogeral='None', historico='None',
                weaponname='None', weaponid=0, armorname='None', armorid=0, shieldname='None', shieldid=0,
                slot1name='None', slot1id=0, slot2name='None', slot2id=0, slot3name='None', slot3id=0,
                durabilidade='-,X,X,X,X,X,X', trocajogador1='None', trocajogador2='None', trocaswitch=0, trocaate=0,
                attackpower=10, defensepower=10, batalha_timer2=0, acesso=0, authlevel=0)
    base.update(campos)
    args = ['php', f'{TESTES}/mkuser.php', PASTA, nome] + [f'{k}={v}' for k, v in base.items()]
    cookie = subprocess.check_output(args, text=True).strip()
    return int(cookie.split()[0]), cookie


def csrf(nome):
    return subprocess.check_output(['php', f'{TESTES}/csrf.php', PASTA, nome], text=True).strip()


def ler(id_, campos):
    linha = sql(f"SELECT {','.join(campos)} FROM naruto_users WHERE id={int(id_)}").rstrip('\n').split('\t')
    return dict(zip(campos, linha))


def pedir(cookie, caminho, dados=None):
    req = urllib.request.Request(BASE + '/' + caminho, data=(dados.encode() if isinstance(dados, str) else urllib.parse.urlencode(dados).encode()) if dados is not None else None)
    req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(cookie))
    try:
        r = abridor.open(req, timeout=60); return r.status, r.read().decode('utf8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf8', 'replace')
    except Exception as e:
        return 0, str(e)


def paralelo(pedidos):
    """pedidos = lista de (cookie, caminho, dados). Todos partem juntos. Devolve as respostas."""
    barreira = threading.Barrier(len(pedidos)); res = [None] * len(pedidos)
    def roda(i, p):
        barreira.wait(); res[i] = pedir(*p)
    ts = [threading.Thread(target=roda, args=(i, p)) for i, p in enumerate(pedidos)]
    [t.start() for t in ts]; [t.join() for t in ts]
    for cod, corpo in res:
        if cod >= 500 or 'Fatal error' in corpo or 'Erro SQL' in corpo:
            falhas.append(('resposta', cod, corpo[:300]))
    return res


def contar(linha, nome):
    """Quantas vezes o item 'nome' aparece na mochila, nos equipamentos e no banco do jogador."""
    n = 0
    for c in ('bp1', 'bp2', 'bp3', 'bp4', 'bp5', 'bp6', 'bp7', 'bp8'):
        if linha[c].split(',')[0] == nome: n += 1
    for c in ('weaponname', 'armorname', 'shieldname', 'slot1name', 'slot2name', 'slot3name'):
        if linha.get(c) == nome: n += 1
    if linha['bancogeral'] != 'None':
        n += sum(1 for it in linha['bancogeral'].split(';') if it and it.split(',')[0] == nome)
    return n


CAMPOS = ['gold', 'banco_grana', 'bp1', 'bp2', 'bp3', 'bp4', 'bp5', 'bp6', 'bp7', 'bp8', 'bancogeral', 'weaponname', 'armorname', 'shieldname',
          'slot1name', 'slot2name', 'slot3name', 'attackpower', 'trocajogador1', 'trocajogador2', 'trocaswitch']


outros = []  # falhas em arquivos fora deste conjunto de correções (só avisadas, não mudam o código de saída)


def confere(nome, cond, detalhe, aviso=False):
    print(('OK      ' if cond else ('AVISO   ' if aviso else 'FALHOU  ')) + nome + ('' if cond else '  -> ' + detalhe))
    if not cond: (outros if aviso else falhas).append((nome, detalhe))


# ---------------------------------------------------------------- cenários

def desequipar_x_banco():
    """Desequipar a arma (vai para a mochila) e depositar a mesma arma no banco, ao mesmo tempo."""
    a, ca = jogador('rc_a', weaponname='Kunai', weaponid=3, attackpower=15)
    p = []
    for i in range(N):
        p.append((ca, 'desequipar.php?qual=1') if i % 2 else (ca, 'funcaoitens.php?do=depositar&qual=1'))
    paralelo(p)
    l = ler(a, CAMPOS)
    confere('desequipar x depositar no banco', contar(l, 'Kunai') == 1, f"Kunai aparece {contar(l, 'Kunai')}x: {l}")


def mochila_x_banco():
    """Equipar bp1 pela mochila (regrava bp1..bp4) e guardar bp2 no banco, ao mesmo tempo."""
    a, ca = jogador('rc_a', bp1='Kunai,3,1,X', bp2='Shuriken,4,1,X')
    p = []
    for i in range(N):
        p.append((ca, 'backpack.php?qual=1') if i % 2 else (ca, 'funcaoitens.php?do=depositarbp&qual=2'))
    paralelo(p)
    l = ler(a, CAMPOS)
    ok = contar(l, 'Kunai') == 1 and contar(l, 'Shuriken') == 1
    confere('mochila (equipar) x depositar outro item no banco', ok, f"Kunai {contar(l, 'Kunai')}x, Shuriken {contar(l, 'Shuriken')}x: {l}")


def mochila_dupla():
    """Equipar o mesmo item da mochila várias vezes (um item equipado duas vezes = bônus em dobro)."""
    a, ca = jogador('rc_a', bp1='Kunai,3,1,X', attackpower=10)
    paralelo([(ca, 'backpack.php?qual=1')] * N)
    l = ler(a, CAMPOS)
    ok = contar(l, 'Kunai') == 1 and int(l['attackpower']) == 15
    confere('equipar o mesmo item várias vezes', ok, f"Kunai {contar(l, 'Kunai')}x, ataque {l['attackpower']} (esperado 15)")


def banco_ida_volta():
    """Retirar item do banco para a mochila e guardar de volta, misturados."""
    a, ca = jogador('rc_a', bancogeral='Kunai,3,1,X;Shuriken,4,1,X;')
    p = []
    for i in range(N):
        p.append((ca, ['funcaoitens.php?do=diretoparabp&qual=0', 'funcaoitens.php?do=depositarbp&qual=1',
                       'funcaoitens.php?do=retirar&qual=0', 'desequipar.php?qual=1'][i % 4]))
    paralelo(p)
    l = ler(a, CAMPOS)
    ok = contar(l, 'Kunai') == 1 and contar(l, 'Shuriken') == 1
    confere('banco <-> mochila/equipamento misturados', ok, f"Kunai {contar(l, 'Kunai')}x, Shuriken {contar(l, 'Shuriken')}x: {l}")


def banco_ryou():
    """Depositar e retirar Ryou ao mesmo tempo: gold + banco_grana tem que continuar igual."""
    a, ca = jogador('rc_a', gold=10000, banco_grana=5000)
    p = []
    for i in range(N):
        d = {'submit': '1', 'deposito': '700', 'retirar': ''} if i % 2 else {'submit': '1', 'deposito': '', 'retirar': '900'}
        p.append((ca, 'funcaoitens.php?do=banco', d))
    paralelo(p)
    l = ler(a, CAMPOS)
    total = int(l['gold']) + int(l['banco_grana'])
    confere('depositar/retirar Ryou em paralelo', total == 15000, f"total {total} (esperado 15000)")


def banco_doar_ryou():
    """Doar Ryou do banco para outro jogador N vezes ao mesmo tempo, com saldo para só algumas."""
    a, ca = jogador('rc_a', banco_grana=1000)
    b, cb = jogador('rc_b', gold=0)
    paralelo([(ca, 'funcaoitens.php?do=doarryou', {'submit': '1', 'csrf': csrf('rc_a'), 'jogador': 'rc_b', 'quantidaderyou': '300'})] * N)
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    ok = int(la['banco_grana']) + int(lb['gold']) == 1000 and int(la['banco_grana']) >= 0
    confere('doar Ryou do banco em paralelo', ok, f"banco A {la['banco_grana']} + gold B {lb['gold']} (esperado 1000)")


def doar_dinheiro():
    """users.php?do=doardinheiro: doação de Ryou carregado, N ao mesmo tempo."""
    a, ca = jogador('rc_a', gold=1000)
    b, cb = jogador('rc_b', gold=0)
    paralelo([(ca, 'users.php?do=doardinheiro', {'submit': '1', 'csrf': csrf('rc_a'), 'jogador': 'rc_b', 'quantia': '300'})] * N)
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    ok = int(la['gold']) + int(lb['gold']) == 1000
    confere('doar Ryou (users.php) em paralelo', ok, f"gold A {la['gold']} + gold B {lb['gold']} (esperado 1000)")


def enviar_item():
    """Enviar o mesmo item do banco para outro jogador N vezes ao mesmo tempo."""
    a, ca = jogador('rc_a', bancogeral='Kunai,3,1,X;')
    b, cb = jogador('rc_b')
    h = hashlib.md5(b'Kunai,3,1,X').hexdigest()[:10]
    paralelo([(ca, f'funcaoitens.php?do=enviaritem&qual=0&h={h}', {'submit': '1', 'csrf': csrf('rc_a'), 'jogador': 'rc_b'})] * N)
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    n = contar(la, 'Kunai') + contar(lb, 'Kunai')
    confere('enviar item do banco em paralelo', n == 1, f"Kunai {n}x (A: {la['bancogeral']} / B: {lb['bancogeral']})")


def troca_paralela():
    """Troca já aceita pelos dois; os dois lados abrem troca4 várias vezes ao mesmo tempo."""
    ofa = 'rc_b;Kunai,3,1,X;500;bp1'
    ofb = 'rc_a;Shuriken,4,1,X;0;bp2'
    a, ca = jogador('rc_a', gold=1000, bp1='Kunai,3,1,X', trocajogador1=ofa, trocajogador2=ofb, trocaswitch=1)
    b, cb = jogador('rc_b', gold=0, bp1='Shuriken,4,1,X', trocajogador1=ofb, trocajogador2=ofa, trocaswitch=1)
    paralelo([((ca if i % 2 else cb), 'troca.php?do=troca4') for i in range(N)])
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    ok = (contar(la, 'Kunai') + contar(lb, 'Kunai') == 1 and contar(la, 'Shuriken') + contar(lb, 'Shuriken') == 1
          and int(la['gold']) + int(lb['gold']) == 1000 and contar(lb, 'Kunai') == 1)
    confere('troca concluída em paralelo (troca4)', ok, f"A {la} / B {lb}")


def troca_x_banco():
    """Troca aceita e, ao mesmo tempo, o dono guarda o item oferecido no banco."""
    ofa = 'rc_b;Kunai,3,1,X;0;bp1'
    ofb = 'rc_a;None;0;None'
    a, ca = jogador('rc_a', bp1='Kunai,3,1,X', trocajogador1=ofa, trocajogador2=ofb, trocaswitch=1)
    b, cb = jogador('rc_b', trocajogador1=ofb, trocajogador2=ofa, trocaswitch=1)
    p = []
    for i in range(N):
        p.append((cb, 'troca.php?do=troca4') if i % 2 else (ca, 'funcaoitens.php?do=depositarbp&qual=1'))
    paralelo(p)
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    n = contar(la, 'Kunai') + contar(lb, 'Kunai')
    confere('troca x depositar o item oferecido', n == 1, f"Kunai {n}x  A {la} / B {lb}")


def troca_mudar_oferta():
    """Depois de B aceitar, A tenta trocar a oferta (tira o Ryou): as ofertas ficam travadas depois do primeiro aceite,
    então a mudança é recusada e só a troca que os DOIS aceitaram (500 Ryou pelo Kunai) pode ser concluída."""
    a, ca = jogador('rc_a', gold=1000)
    b, cb = jogador('rc_b', gold=0, bp1='Kunai,3,1,X')
    pedir(ca, 'troca.php?do=troca2', {'submit': '1', 'jogador': 'rc_b', 'ryoutroca': '500', 'qual': '0'})
    pedir(cb, 'troca.php?do=troca2', {'submit': '1', 'jogador': 'rc_a', 'ryoutroca': '', 'qual': '1'})
    lb = ler(b, ['trocajogador1', 'trocajogador2'])
    h = hashlib.md5((lb['trocajogador1'] + '|' + lb['trocajogador2']).encode()).hexdigest()
    pedir(cb, f'troca.php?do=troca3&h={h}&csrf={csrf("rc_b")}')            # B aceita "500 Ryou pelo Kunai"
    pedir(ca, 'troca.php?do=troca2', {'submit': '1', 'jogador': 'rc_b', 'ryoutroca': '1', 'qual': '0'})  # A tenta mudar para 1 Ryou
    la = ler(a, ['trocajogador1', 'trocajogador2'])
    travou = la['trocajogador1'].split(';')[2] == '500'
    h2 = hashlib.md5((la['trocajogador1'] + '|' + la['trocajogador2']).encode()).hexdigest()
    pedir(ca, f'troca.php?do=troca3&h={h2}&csrf={csrf("rc_a")}')
    paralelo([(ca, 'troca.php?do=troca4')] * 4)
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    confere('troca: oferta travada depois do aceite', travou and contar(la, 'Kunai') == 1 and contar(lb, 'Kunai') == 0 and int(lb['gold']) == 500 and int(la['gold']) == 500,
            f"travou {travou} A {la['bp1']} gold {la['gold']} / B {lb['bp1']} gold {lb['gold']}")


def unidades(linha, nome):
    """Unidades do item 'nome' na mochila e no banco (pilhas "nome,id,tipo,dur,qtd" contam a quantidade)."""
    n = 0
    itens = [linha[f'bp{i}'] for i in range(1, 9)] + ([x for x in linha['bancogeral'].split(';') if x] if linha['bancogeral'] != 'None' else [])
    for it in itens:
        p = it.split(',')
        if p[0] == nome: n += int(p[4]) if len(p) > 4 and p[4].isdigit() and int(p[4]) > 1 else 1
    return n


REP = 'Incenso Repelente,rep,100,X'


def troca_pilha_paralela():
    """A oferece 3 de uma pilha de 5 incensos; B já tem 2 (junta na pilha). troca4 dos dois lados N vezes ao mesmo tempo."""
    ofa = 'rc_b;' + REP + ',3;0;bp1'
    ofb = 'rc_a;None;100;None'
    a, ca = jogador('rc_a', gold=0, bp1=REP + ',5', trocajogador1=ofa, trocajogador2=ofb, trocaswitch=1, trocaate=0)
    b, cb = jogador('rc_b', gold=100, bp1=REP + ',2', trocajogador1=ofb, trocajogador2=ofa, trocaswitch=1, trocaate=0)
    paralelo([((ca if i % 2 else cb), 'troca.php?do=troca4') for i in range(N)])
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    ok = unidades(la, 'Incenso Repelente') == 2 and unidades(lb, 'Incenso Repelente') == 5 and lb['bp1'] == REP + ',5' and int(la['gold']) == 100 and int(lb['gold']) == 0
    confere('troca de parte de uma pilha (paralelo)', ok, f"A {la['bp1']} {la['gold']} / B {lb['bp1']} {lb['bp2']} {lb['gold']}")


def troca_pilha_x_uso():
    """Troca aceita de 3 incensos (de 3) e, ao mesmo tempo, o dono acende incensos da mesma pilha."""
    ofa = 'rc_b;' + REP + ',3;0;bp1'
    ofb = 'rc_a;None;0;None'
    a, ca = jogador('rc_a', bp1=REP + ',3', trocajogador1=ofa, trocajogador2=ofb, trocaswitch=1)
    b, cb = jogador('rc_b', trocajogador1=ofb, trocajogador2=ofa, trocaswitch=1)
    paralelo([(cb, 'troca.php?do=troca4') if i % 2 else (ca, 'backpack.php?qual=1') for i in range(N)])
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    ua, ub = unidades(la, 'Incenso Repelente'), unidades(lb, 'Incenso Repelente')
    ok = (ub == 3 and ua == 0) or (ub == 0 and ua < 3)
    confere('troca de pilha x usar a mesma pilha', ok, f"A {ua} un. / B {ub} un.")


def troca_expirada():
    """Troca aceita pelos dois mas vencida (trocaate no passado): troca4 não pode executar."""
    ofa = 'rc_b;Kunai,3,1,X;0;bp1'
    ofb = 'rc_a;None;500;None'
    a, ca = jogador('rc_a', bp1='Kunai,3,1,X', gold=0, trocajogador1=ofa, trocajogador2=ofb, trocaswitch=1, trocaate=1000)
    b, cb = jogador('rc_b', gold=500, trocajogador1=ofb, trocajogador2=ofa, trocaswitch=1, trocaate=1000)
    paralelo([(ca, 'troca.php?do=troca4'), (cb, 'troca.php?do=troca4')])
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    confere('troca vencida não executa', contar(la, 'Kunai') == 1 and int(lb['gold']) == 500, f"A {la['bp1']} / B gold {lb['gold']}")


def troca_ida_e_volta():
    """Fluxo completo pelas telas: A propõe 2 de 4 poções + 0 Ryou, B responde com 300 Ryou, os dois aceitam ao mesmo tempo."""
    pocao = 'Poção Vermelha,hp,50,1'
    a, ca = jogador('rc_a', gold=0, bp3=pocao + ',4')
    b, cb = jogador('rc_b', gold=300)
    pedir(ca, 'troca.php?do=troca2', {'submit': '1', 'jogador': 'rc_b', 'ryoutroca': '', 'qual': '3', 'qtd3': '2'})
    pedir(cb, 'troca.php?do=troca2', {'submit': '1', 'jogador': 'rc_a', 'ryoutroca': '300', 'qual': '0'})
    la, lb = ler(a, ['trocajogador1', 'trocajogador2']), ler(b, ['trocajogador1', 'trocajogador2'])
    ha = hashlib.md5((la['trocajogador1'] + '|' + la['trocajogador2']).encode()).hexdigest()
    hb = hashlib.md5((lb['trocajogador1'] + '|' + lb['trocajogador2']).encode()).hexdigest()
    paralelo([(ca, f'troca.php?do=troca3&h={ha}&csrf={csrf("rc_a")}'), (cb, f'troca.php?do=troca3&h={hb}&csrf={csrf("rc_b")}')])
    paralelo([(ca, 'troca.php?do=troca4'), (cb, 'troca.php?do=troca4')] * 3)
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    ok = unidades(la, 'Poção Vermelha') == 2 and unidades(lb, 'Poção Vermelha') == 2 and int(la['gold']) == 300 and int(lb['gold']) == 0
    confere('troca completa pelas telas (pilha + Ryou)', ok, f"A {la['bp3']} {la['gold']} / B {lb['bp1']} {lb['gold']} {lb['trocajogador1']}")


def venda_pilha_paralela():
    """Vender 1 unidade de uma pilha de 5 incensos N vezes ao mesmo tempo: Ryou = unidades vendidas × preço."""
    a, ca = jogador('rc_a', gold=0, bp1=REP + ',5')
    mult = int(sql("SELECT vendamochila FROM naruto_control WHERE id=1").strip())
    preco = max(1, 100 * mult // 100)
    dados = {'vender': '1', 'qual': '1', 'qtd': '1', 'h': codigo_venda(REP + ',5'), 'csrf': csrf('rc_a')}
    paralelo([(ca, 'index.php?do=vender', dados)] * N)
    l = ler(a, CAMPOS); u = unidades(l, 'Incenso Repelente')
    ok = u == 4 and int(l['gold']) == preco    # o código h só vale para a pilha de 5: uma venda só.
    confere('vender 1 de uma pilha (paralelo, mesmo formulário)', ok, f"{u} un., gold {l['gold']}")


def usar_pilha_paralela():
    """Acender incensos de uma pilha de 3 N vezes ao mesmo tempo: no máximo 3 usos, nunca quantidade negativa."""
    a, ca = jogador('rc_a', bp1=REP + ',3')
    paralelo([(ca, 'backpack.php?qual=1')] * N)
    l = ler(a, CAMPOS)
    confere('usar pilha em paralelo', l['bp1'] == 'None', f"bp1 {l['bp1']}")


def compra_pilha_paralela():
    """Comprar incensos (200) N vezes com Ryou para 3, tendo uma pilha de 18 (máx. 20) e a mochila cheia de Kunais."""
    cheia = {f'bp{i}': 'Kunai,3,1,X' for i in range(2, 9)}
    a, ca = jogador('rc_a', gold=600, bp1=REP + ',18', **cheia)
    maxp = int(sql("SELECT pilhaconsumiveis FROM naruto_control WHERE id=1").strip())
    paralelo([(ca, 'index.php?do=vender', {'comprar': '1', 'codigo': 'rep', 'csrf': csrf('rc_a')})] * N)
    l = ler(a, CAMPOS); u = unidades(l, 'Incenso Repelente'); g = int(l['gold'])
    esperado = min(18 + 3, max(18, maxp))
    ok = u == esperado and g == 600 - 200 * (u - 18)
    confere('comprar consumível juntando na pilha (paralelo, mochila cheia)', ok, f"{u} un. (esperado {esperado}), gold {g}")


def comprador_remoto_paralelo():
    """Vender o mesmo pergaminho ao comprador distante N vezes ao mesmo tempo (paga control.compradorremoto %)."""
    npc = sql("SELECT latitude, longitude FROM naruto_npcs WHERE papel='comprador_pergaminhos' AND ativo=1 ORDER BY id LIMIT 1").split()
    if len(npc) < 2:
        confere('comprador distante', False, 'nenhum NPC comprador_pergaminhos'); return
    item = 'Pergaminho de Jutsu,jutsu,20,1'
    a, ca = jogador('rc_a', gold=0, bp1=item + ',2', currentaction='Exploring', latitude=npc[0], longitude=npc[1])
    nivel = int(sql("SELECT nivelminimo FROM naruto_spells WHERE id=20").strip())
    mult = int(sql("SELECT vendamochila FROM naruto_control WHERE id=1").strip()); rem = int(sql("SELECT compradorremoto FROM naruto_control WHERE id=1").strip())
    preco = max(1, ((50 + 20 * nivel) * rem // 100) * mult // 100)
    dados = {'vender': '1', 'qual': '1', 'qtd': '2', 'h': codigo_venda(item + ',2'), 'csrf': csrf('rc_a')}
    paralelo([(ca, 'index.php?do=comprador', dados)] * N)
    l = ler(a, CAMPOS)
    ok = l['bp1'] == 'None' and int(l['gold']) == 2 * preco
    confere('comprador de pergaminhos distante (paralelo)', ok, f"bp1 {l['bp1']}, gold {l['gold']} (esperado {2 * preco})")


def alquimia_paralela():
    """Fundir os mesmos dois itens N vezes ao mesmo tempo."""
    a, ca = jogador('rc_a', latitude=-100, longitude=100, bp1='Espadas de Rai,20,1,X', bp2='Memória de Konoha,30,4,X')
    dados = {'submit': '1', 'bpnome1': 'Espadas de Rai,20,1,X', 'bpnome2': 'Memória de Konoha,30,4,X'}
    paralelo([(ca, 'alquimia.php?do=fundir', dados)] * N)
    l = ler(a, CAMPOS)
    ok = contar(l, 'Espadas Raiga') == 1 and contar(l, 'Espadas de Rai') == 0 and contar(l, 'Memória de Konoha') == 0
    confere('alquimia em paralelo', ok, f"{l['bp1']} | {l['bp2']} | {l['bp3']} | {l['bp4']}")


def alquimia_estado(l):
    import hashlib
    return hashlib.md5(''.join(l['bp%d' % i] + '|' for i in range(1, 9)).encode()).hexdigest()


def alquimia_receita_paralela():
    """A mesma receita (refino 3 Minérios -> 1 Liga) enviada N vezes com o mesmo estado da mochila: só uma tentativa vale."""
    a, ca = jogador('rc_alq1', latitude=-100, longitude=100, gold=1000, bp1='Minério de Ferro,75,4,X,9')
    sql("UPDATE naruto_users SET alquimia_nivel=1, alquimia_exp=0 WHERE id=%d" % a)
    dados = {'criar': '1', 'receita': '23', 'estado': alquimia_estado(ler(a, CAMPOS))}
    paralelo([(ca, 'alquimia.php?do=fundir&aba=refinar', dados)] * N)
    l = ler(a, CAMPOS)
    minerio, liga = unidades(l, 'Minério de Ferro'), unidades(l, 'Liga de Aço Ninja')
    ok = int(l['gold']) == 970 and liga in (0, 1) and (minerio == 6 if liga == 1 else minerio in (7, 8, 9)) and minerio + 3 * liga <= 9
    confere('alquimia: mesma receita em paralelo', ok, f"minério {minerio}, liga {liga}, gold {l['gold']}")


def alquimia_receita_x_banco():
    """Criar uma poção enquanto guarda/vende o mesmo ingrediente: nunca usa o item que saiu da mochila."""
    a, ca = jogador('rc_alq2', latitude=-100, longitude=100, gold=1000, bp1='Erva Medicinal Seca,51,4,X', bp2='Bolinho de Arroz,hp,25,1')
    dados = {'criar': '1', 'receita': '30', 'estado': alquimia_estado(ler(a, CAMPOS))}
    paralelo([(ca, 'alquimia.php?do=fundir&aba=pocoes', dados) if i % 2 else (ca, 'funcaoitens.php?do=depositarbp&qual=1', None) for i in range(N)])
    l = ler(a, CAMPOS)
    erva = unidades(l, 'Erva Medicinal Seca')  # mochila + banco
    pocao = unidades(l, 'Poção Vermelha')
    ok = (erva == 1 and pocao == 0) or (erva == 0 and pocao in (2, 4))
    confere('alquimia x guardar no banco (paralelo)', ok, f"erva {erva}, poções {pocao}, bp {l['bp1']} | {l['bp2']} | {l['bp3']}")


def alquimia_livro_paralelo():
    """Ler o mesmo livro de receitas N vezes ao mesmo tempo: aprende uma vez e gasta um livro."""
    a, ca = jogador('rc_alq3', latitude=-100, longitude=100, bp1='Livro de Receitas: Elixires,132,4,X,2')
    sql("UPDATE naruto_users SET alquimia_receitas='' WHERE id=%d" % a)
    dados = {'ler': '1', 'estado': alquimia_estado(ler(a, CAMPOS))}
    paralelo([(ca, 'alquimia.php?do=fundir', dados)] * N)
    l = ler(a, CAMPOS)
    rec = sql("SELECT alquimia_receitas FROM naruto_users WHERE id=%d" % a).strip()
    ok = unidades(l, 'Livro de Receitas: Elixires') == 1 and rec == '37,'
    confere('alquimia: ler livro em paralelo', ok, f"bp1 {l['bp1']}, receitas {rec!r}")


def alquimia_infusao_paralela():
    """Infusão no mesmo item N vezes: o item ganha no máximo uma variante e o material só some pelas tentativas válidas."""
    a, ca = jogador('rc_alq4', latitude=-100, longitude=100, gold=100000, bp1='Kunai,3,1,X', bp2='Pedra-Trovão,120,4,X,20')
    sql("UPDATE naruto_users SET alquimia_nivel=10 WHERE id=%d" % a)
    dados = {'criar': '1', 'receita': '48', 'alvo': '1', 'estado': alquimia_estado(ler(a, CAMPOS))}
    paralelo([(ca, 'alquimia.php?do=fundir&aba=infusao', dados)] * N)
    l = ler(a, CAMPOS)
    pedras = unidades(l, 'Pedra-Trovão'); kunais = sum(1 for i in range(1, 9) if l['bp%d' % i].startswith('Kunai'))
    custo = 1000 + int(sql("SELECT alquimiainfusaocusto FROM naruto_control WHERE id=1").strip())
    ok = kunais == 1 and int(l['gold']) == 100000 - custo and pedras in (18, 19)
    confere('alquimia: infusão em paralelo', ok, f"bp1 {l['bp1']}, pedras {pedras}, gold {l['gold']}")


def entradas_estranhas():
    """Quantias e índices forjados (negativos, notação científica, hexadecimal, arrays, para si mesmo, posição velha)."""
    a, ca = jogador('rc_a', gold=1000, banco_grana=1000, bancogeral='Kunai,3,1,X;Shuriken,4,1,X;')
    b, cb = jogador('rc_b', gold=0)
    for q in ['-0', '-5', '0x10', '1e9', '1e400', '-1e400', 'NaN', ' 5', '5abc', '0.5']:
        pedir(ca, 'funcaoitens.php?do=doarryou', {'submit': '1', 'jogador': 'rc_b', 'quantidaderyou': q})
        pedir(ca, 'users.php?do=doardinheiro', {'submit': '1', 'jogador': 'rc_b', 'quantia': q})
        pedir(ca, 'funcaoitens.php?do=banco', {'submit': '1', 'deposito': q, 'retirar': q})
        pedir(ca, 'troca.php?do=troca2', {'submit': '1', 'jogador': 'rc_b', 'ryoutroca': q, 'qual': '0'})
    pedir(ca, 'funcaoitens.php?do=doarryou', {'submit': '1', 'jogador': 'rc_a', 'quantidaderyou': '10'})
    pedir(ca, 'users.php?do=doardinheiro', {'submit': '1', 'jogador': 'rc_a', 'quantia': '10'})
    pedir(ca, 'funcaoitens.php?do=doarryou', 'submit=1&jogador[]=rc_b&quantidaderyou[]=10')
    for q in ['-1', '2', '99', '1e1', 'x']:
        pedir(ca, f'funcaoitens.php?do=retirar&qual={q}'); pedir(ca, f'funcaoitens.php?do=depositar&qual={q}')
        pedir(ca, f'backpack.php?qual={q}'); pedir(ca, f'desequipar.php?qual={q}')
    # Link velho de deletar/doar (código do item que estava na posição 0 antes) não pode pegar outro item.
    h = hashlib.md5(b'Shuriken,4,1,X').hexdigest()[:10]
    pedir(ca, f'funcaoitens.php?do=deletaritem&qual=0&h={h}&csrf={csrf("rc_a")}')
    pedir(ca, f'funcaoitens.php?do=enviaritem&qual=0&h={h}', {'submit': '1', 'jogador': 'rc_b'})
    la, lb = ler(a, CAMPOS), ler(b, CAMPOS)
    total = int(la['gold']) + int(la['banco_grana']) + int(lb['gold'])
    ok = total == 2000 and contar(la, 'Kunai') == 1 and contar(la, 'Shuriken') == 1 and contar(lb, 'Kunai') == 0
    confere('entradas estranhas (negativos, 1e9, 0x10, arrays, posição velha)', ok, f"total Ryou {total} (2000); A {la['bancogeral']} / B {lb['bancogeral']}")


def loja_x_banco():
    """Comprar Kunai na loja (a arma equipada é vendida por 1/4 do preço) e guardar a arma equipada no banco, juntos.
    Conta de chegada: cada arma que sumiu foi revendida uma vez. Se a Shuriken continua no banco E foi revendida,
    sobra Ryou que não fecha a conta (duplicação do valor de revenda)."""
    a, ca = jogador('rc_a', gold=10000, weaponname='Shuriken', weaponid=4, attackpower=18)
    ck = int(sql("SELECT buycost FROM naruto_items WHERE id=3").strip()); cs = int(sql("SELECT buycost FROM naruto_items WHERE id=4").strip())
    rk, rs = -(-ck // 4), -(-cs // 4)
    p = []
    for i in range(N):
        p.append((ca, 'index.php?do=buy3:3', {'submit': '1'}) if i % 2 else (ca, 'funcaoitens.php?do=depositar&qual=1'))
    paralelo(p)
    l = ler(a, CAMPOS)
    S, Kp, g = contar(l, 'Shuriken'), contar(l, 'Kunai'), int(l['gold'])
    # g = 10000 - ck*Kb + rs*(1-S) + rk*(Kb-Kp)  ->  Kb (Kunais compradas) tem que ser inteiro e >= Kp.
    num = 10000 + rs * (1 - S) - rk * Kp - g
    ok = num % (ck - rk) == 0 and num // (ck - rk) >= Kp
    confere('loja (comprar) x depositar a arma equipada', ok, f"conta não fecha: gold {g}, Shuriken {S}x, Kunai {Kp}x (towns.php buy3)", aviso=True)


def codigo_venda(texto):
    return hashlib.sha256(('venda|' + texto).encode()).hexdigest()[:16]


def venda_paralela():
    """Vender o mesmo item da mochila (espaço 6) N vezes ao mesmo tempo, misturado com guardar esse item no banco.
    O item só pode virar Ryou uma vez OU ir para o banco (nunca os dois)."""
    item = 'Pergaminho de Jutsu,jutsu,20,1'
    a, ca = jogador('rc_a', gold=1000, bp6=item, bp2='Kunai,3,1,X')
    preco = 50 + 20 * int(sql("SELECT nivelminimo FROM naruto_spells WHERE id=20").strip())
    mult = int(sql("SELECT vendamochila FROM naruto_control WHERE id=1").strip())
    preco = max(1, preco * mult // 100)
    dados = {'vender': '1', 'qual': '6', 'h': codigo_venda(item), 'csrf': csrf('rc_a')}
    p = []
    for i in range(N):
        p.append((ca, 'index.php?do=vender', dados) if i % 3 else (ca, 'funcaoitens.php?do=depositarbp&qual=6'))
    paralelo(p)
    l = ler(a, CAMPOS); g = int(l['gold']); nobanco = contar(l, 'Pergaminho de Jutsu')
    ok = (g == 1000 + preco and nobanco == 0) or (g == 1000 and nobanco == 1)
    confere('vender item da mochila x guardar no banco (paralelo)', ok, f"gold {g} (1000 + {preco}?), pergaminho {nobanco}x, bp6 {l['bp6']}")


def compra_consumivel_paralela():
    """Comprar o Incenso Repelente (200 Ryou) N vezes ao mesmo tempo com dinheiro para só um."""
    a, ca = jogador('rc_a', gold=300)
    paralelo([(ca, 'index.php?do=vender', {'comprar': '1', 'codigo': 'rep', 'csrf': csrf('rc_a')})] * N)
    l = ler(a, CAMPOS)
    ok = int(l['gold']) == 100 and contar(l, 'Incenso Repelente') == 1
    confere('comprar consumível em paralelo (dinheiro para 1)', ok, f"gold {l['gold']}, incensos {contar(l, 'Incenso Repelente')}")


def usar_retorno_paralelo():
    """Usar o Pergaminho de Retorno N vezes ao mesmo tempo e vendê-lo junto: só um uso OU uma venda."""
    item = 'Pergaminho de Retorno,ret,1,X'
    a, ca = jogador('rc_a', gold=0, bp1=item, currentaction='In Town')
    dados = {'vender': '1', 'qual': '1', 'h': codigo_venda(item), 'csrf': csrf('rc_a')}
    paralelo([(ca, 'backpack.php?qual=1') if i % 2 else (ca, 'index.php?do=vender', dados) for i in range(N)])
    l = ler(a, CAMPOS)
    ok = l['bp1'] == 'None' and int(l['gold']) in (0, max(1, 75 * int(sql("SELECT vendamochila FROM naruto_control WHERE id=1").strip()) // 100))
    confere('usar Pergaminho de Retorno x vender (paralelo)', ok, f"bp1 {l['bp1']}, gold {l['gold']}")


if __name__ == '__main__':
    for f in [desequipar_x_banco, mochila_x_banco, mochila_dupla, banco_ida_volta, banco_ryou, banco_doar_ryou,
              doar_dinheiro, enviar_item, troca_paralela, troca_x_banco, troca_mudar_oferta, alquimia_paralela, entradas_estranhas, loja_x_banco,
              venda_paralela, compra_consumivel_paralela, usar_retorno_paralelo,
              troca_pilha_paralela, troca_pilha_x_uso, troca_expirada, troca_ida_e_volta, venda_pilha_paralela, usar_pilha_paralela,
              compra_pilha_paralela, comprador_remoto_paralelo,
              alquimia_receita_paralela, alquimia_receita_x_banco, alquimia_livro_paralelo, alquimia_infusao_paralela]:
        f()
    print(f"\n{len(falhas)} problema(s); {len(outros)} aviso(s) em towns.php/outros arquivos.")
    for f in falhas: print('  ', f)
    sys.exit(1 if falhas else 0)
