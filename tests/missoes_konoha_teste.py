#!/usr/bin/env python3
"""Teste das missões de começo de jogo no quadro da Vila da Folha (200-206, tools/missoes_konoha.sql).

Uso: python3 missoes_konoha_teste.py http://127.0.0.1:8080 /var/www/narutorpg
Jogadores de teste: mk_ana (pt) e mk_bob (en).
Cobre, para cada missão: aparece no quadro da Folha (pt/en), recusa por nível, aceite por HTTP, entrega antes da hora
recusada, progresso pelos ganchos reais (missoesextras_vitoria / missoesextras_posicao), conversa/entrega de item
com NPC por HTTP (falapersonagens.php), entrega no quadro por HTTP e recompensa (exp, Ryou, item na mochila).
Também confere que os inimigos pedidos aparecem de verdade perto da Folha (escolhermonstro do fight.php) e que a
ronda diária volta a ficar disponível depois do intervalo.
"""
import sys, re, subprocess, urllib.request, urllib.parse

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
    return cod, corpo, urllib.parse.unquote(loc)

def confere(cond, texto):
    print(('  ok   ' if cond else '  FALHA ') + texto)
    if not cond: falhas.append(texto)

def php(codigo):
    pre = ("chdir('%s'); $_GET=$_POST=array(); $_COOKIE=array(); include('lib.php'); $link=opendb(); include_once('funcoesmissoesextras.php'); "
           "$controlrow = mysqli_fetch_array(doquery(\"SELECT * FROM {{table}} WHERE id='1' LIMIT 1\", 'control')); ") % PASTA
    r = subprocess.run(['php', '-r', pre + codigo], capture_output=True, text=True)
    return r.stdout.strip(), r.stderr.strip()

def gancho(nome, codigo):
    out, err = php("$u = mysqli_fetch_array(doquery(\"SELECT * FROM {{table}} WHERE username='%s' LIMIT 1\", 'users'), MYSQLI_ASSOC); %s" % (nome, codigo))
    if out or err: falhas.append('gancho: ' + (out + err)[:300])

def vencer(nome, idmonstro, vezes):
    gancho(nome, "$m = mysqli_fetch_array(doquery(\"SELECT * FROM {{table}} WHERE id='%d' LIMIT 1\", 'monsters'), MYSQLI_ASSOC); for ($i = 0; $i < %d; $i++) { missoesextras_vitoria($u, $m); }" % (idmonstro, vezes))

def visitar(nome, lat, lon):
    SET(nome, f"latitude={lat}, longitude={lon}, currentaction='Exploring'")
    gancho(nome, "missoesextras_posicao($u);")

def aparece(idmonstro, lat, lon, tentativas=300):
    """O inimigo é sorteado de verdade pelo fight.php (mesma conta de faixa de nível + escolhermonstro) nessa casa?"""
    codigo = ("function escolhermonstro_teste() {} $src = file_get_contents('fight.php'); "
              "preg_match('/function escolhermonstro\\(.*?\\n}\\n/s', $src, $m); eval($m[0]); "
              "$lat=%d; $lon=%d; $max = floor(max(abs($lat)+5, abs($lon)+5) / 5); if ($max < 1) $max = 1; $min = max(1, $max - 2); "
              "$n = 0; for ($i = 0; $i < %d; $i++) { $r = escolhermonstro($min, $max, $lat, $lon); if (intval($r['id']) == %d) $n++; } echo $n;") % (lat, lon, tentativas, idmonstro)
    out, err = php(codigo)
    if err: falhas.append('aparece: ' + err[:300])
    return int(out or 0)

SET = lambda nome, s: sql(f"UPDATE naruto_users SET {s} WHERE username='{nome}'")
U = lambda nome, campo: sql(f"SELECT {campo} FROM naruto_users WHERE username='{nome}'")
def estado(uid, mid):
    return sql(f"SELECT CONCAT(estado,'/',etapa_atual,'/',progresso) FROM naruto_missoesextras_jogador WHERE usuario_id={uid} AND missao_id={mid}")

VAZIA = dict(bp1='None', bp2='None', bp3='None', bp4='None', bp5='None', bp6='None', bp7='None', bp8='None')
base = dict(level=1, experience=0, gold=1000, currentaction='In Town', latitude=0, longitude=0, maxhp=200, currenthp=200, maxmp=100, currentmp=100,
            maxtp=50, currenttp=50, towns='0,1', alinhamento='folha', historico='None', currentfight=0, jutsuestado='', missao='22,0,0,0', **VAZIA)
for n in ('mk_ana', 'mk_bob'):
    sql(f"DELETE FROM naruto_missoesextras_jogador WHERE usuario_id=(SELECT id FROM naruto_users WHERE username='{n}')")
idA, ckA = jogador('mk_ana', **base)
idB, ckB = jogador('mk_bob', **base)
tA, tB = token('mk_ana'), token('mk_bob')
A = lambda c, d=None: pedir(ckA, 'pt', c, d)
B = lambda c, d=None: pedir(ckB, 'en', c, d)
aceitar = lambda mid: A('missoesextras.php?do=aceitar', {'id': mid, 'csrf': tA})
entregar = lambda mid: A('missoesextras.php?do=entregar', {'id': mid, 'csrf': tA})
def natown(): SET('mk_ana', "latitude=0, longitude=0, currentaction='In Town'")

TIT = {200: ('Uivos na Orla da Mata', 'Howls at the Forest’s Edge'), 201: ('Marcos da Trilha Velha', 'Markers of the Old Trail'),
       202: ('O Remédio do Pescador', 'The Fisherman’s Remedy'), 203: ('Ronda Diária da Folha', 'Daily Leaf Patrol'),
       204: ('Presas para o Curtidor', 'Fangs for the Tanner'), 205: ('A Prova do Portão', 'The Gate Trial'),
       206: ('O Javali da Lavoura', 'The Field-Wrecking Boar')}

print('1. Quadro da Vila da Folha: pelo menos 7 missões de nível 1-20, as novas nos dois idiomas')
n = int(sql("SELECT COUNT(*) FROM naruto_missoesextras WHERE categoria='extra' AND ativa=1 AND npc_id=0 AND vila_id IN (0,1) AND nivel_minimo<=20"))
confere(n >= 7, f'{n} missões de nível até 20 no quadro da Folha')
SET('mk_ana', 'level=20'); SET('mk_bob', 'level=20')
c, corpo, _ = A('missoesextras.php'); c2, corpo2, _ = B('missoesextras.php')
for mid, (pt, en) in TIT.items():
    confere(pt in corpo, f'{mid} no quadro (pt): {pt}')
    confere(en in corpo2, f'{mid} no quadro (en): {en}')
visivel = re.sub(r"(, '[^']*'){3}\)\"", ')"', corpo2)  # tira os 3 últimos argumentos do mostrarquest (nomes internos de item/NPC/inimigo para o mapa)
resto = [visivel[max(0, m.start() - 60):m.end() + 20] for w in ('Lobo', 'Bandido de Estrada', 'Javali', 'Poção', 'Herborista') for m in re.finditer(w, visivel)]
confere(not resto and 'Wolf' in visivel, f'objetivos e recompensas traduzidos (en) {resto}')
SET('mk_ana', 'level=1'); SET('mk_bob', 'level=1')

print('2. Os inimigos pedidos aparecem de verdade em volta da Folha')
confere(aparece(2, 2, 1) > 0, 'Lobo (2) a 2 casas da vila')
confere(aparece(1, 3, 6) > 0, 'Cão Selvagem (1) perto do rio')
confere(aparece(167, 12, 0) > 0, 'Bandido de Estrada (167) nas trilhas a ~12 casas')
confere(aparece(187, 18, 4) > 0, 'Matilha de Cães Ninja (187) a ~18 casas')
confere(aparece(168, 10, -28) > 0, 'Javali de Presas Largas (168) a oeste (10,-28)')
out, _ = php("include_once('funcoesterreno.php'); foreach (array(array(12,8),array(-14,-6),array(3,-16)) as $p) { $t = terrenodacasa($p[0],$p[1]); echo $t[2],' '; }")
confere(all(x in ('floresta', 'trilha', 'grama') for x in out.split()), f'marcos da missão 201 em terreno andável ({out})')

print('3. Recusa por nível')
c, _, loc = aceitar(206)
confere('nível 10' in loc and estado(idA, 206) == '', 'nível 1 não aceita a 206 (nível 10)')

print('4. 200 Uivos na Orla da Mata: derrotar 5 Lobos')
c, _, loc = aceitar(200); confere(estado(idA, 200) == 'andamento/0/0', 'aceita')
c, _, loc = entregar(200); confere('ainda não completou' in loc, 'entrega antes da hora recusada')
vencer('mk_ana', 1, 3); confere(estado(idA, 200) == 'andamento/0/0', 'inimigo errado não conta')
vencer('mk_ana', 2, 5); natown()
g0, x0 = int(U('mk_ana', 'gold')), int(U('mk_ana', 'experience'))
c, _, loc = entregar(200)
confere('Parabéns' in loc and estado(idA, 200).startswith('concluida'), 'entregue')
confere(int(U('mk_ana', 'gold')) - g0 == 60 and int(U('mk_ana', 'experience')) - x0 >= 30, 'recompensa 30 exp + 60 Ryou')
confere('Poção Vermelha Pequena' in U('mk_ana', 'CONCAT_WS("|",bp1,bp2,bp3,bp4,bp5,bp6,bp7,bp8)'), 'Poção Vermelha Pequena na mochila')
c, _, loc = aceitar(200); confere(estado(idA, 200).startswith('concluida'), 'não repetível')
SET('mk_ana', "level=10, " + ", ".join(f"{k}='None'" for k in VAZIA))

print('5. 201 Marcos da Trilha Velha: visitar 3 marcos')
c, _, loc = aceitar(201); confere(estado(idA, 201) == 'andamento/0/0', 'aceita')
visitar('mk_ana', -14, -6); confere(estado(idA, 201) == 'andamento/0/0', 'fora de ordem não conta')
visitar('mk_ana', 12, 8); confere(estado(idA, 201) == 'andamento/1/0', 'marco 1')
visitar('mk_ana', -14, -6); confere(estado(idA, 201) == 'andamento/2/0', 'marco 2')
visitar('mk_ana', 3, -16); natown()
g0 = int(U('mk_ana', 'gold'))
c, _, loc = entregar(201)
confere('Parabéns' in loc and int(U('mk_ana', 'gold')) - g0 == 120, 'entregue, +120 Ryou')

print('6. 202 O Remédio do Pescador: falar com Suzuran, levar 2 poções ao Tomiji, 3 Cães Selvagens')
c, _, loc = aceitar(202); confere(estado(idA, 202) == 'andamento/0/0', 'aceita')
SET('mk_ana', "latitude=-6, longitude=12, currentaction='Exploring'")
A('falapersonagens.php'); confere(estado(idA, 202) == 'andamento/1/0', 'conversou com Suzuran (HTTP)')
SET('mk_ana', "latitude=8, longitude=6, currentaction='Exploring', bp1='Poção Vermelha Pequena,431,4,X'")
A('falapersonagens.php'); confere(estado(idA, 202) == 'andamento/1/0', 'com 1 poção só não entrega')
SET('mk_ana', "bp2='Poção Vermelha Pequena,431,4,X', bp3='Bolinho de Arroz,69,4,X'")
A('falapersonagens.php')
mochila = U('mk_ana', 'CONCAT_WS("|",bp1,bp2,bp3,bp4,bp5,bp6,bp7,bp8)')
confere(estado(idA, 202) == 'andamento/2/0' and 'Poção Vermelha Pequena' not in mochila and 'Bolinho de Arroz' in mochila, 'entregou 2 poções ao Tomiji (consumidas, resto fica)')
vencer('mk_ana', 1, 3); natown()
c, _, loc = entregar(202)
confere('Parabéns' in loc and 'Peixe Seco' in U('mk_ana', 'CONCAT_WS("|",bp1,bp2,bp3,bp4,bp5,bp6,bp7,bp8)'), 'entregue, Peixe Seco na mochila')

print('7. 203 Ronda Diária da Folha: 3 Bandidos de Estrada, repetível a cada 24 h')
c, _, loc = aceitar(203); vencer('mk_ana', 167, 3); natown()
g0 = int(U('mk_ana', 'gold'))
c, _, loc = entregar(203)
confere('Parabéns' in loc and int(U('mk_ana', 'gold')) - g0 == 110, 'entregue, +110 Ryou')
c, _, loc = aceitar(203)
confere(not estado(idA, 203).startswith('andamento'), f'antes de 24 h não aceita de novo ({loc[-70:]})')
sql(f"UPDATE naruto_missoesextras_jogador SET concluida_em=concluida_em - INTERVAL 25 HOUR WHERE usuario_id={idA} AND missao_id=203")
c, _, loc = aceitar(203)
confere(estado(idA, 203) == 'andamento/0/0', 'depois de 24 h aceita de novo')

print('8. 204 Presas para o Curtidor: coletar 3 Presas de Lobo da Matilha de Cães Ninja')
c, _, loc = aceitar(204); confere(estado(idA, 204) == 'andamento/0/0', 'aceita')
vencer('mk_ana', 2, 30); confere(estado(idA, 204) == 'andamento/0/0', 'outro inimigo não dá presa')
for _ in range(15):
    if estado(idA, 204).startswith('andamento/0/3'): break
    vencer('mk_ana', 187, 5)
confere(estado(idA, 204) == 'andamento/0/3', 'três presas coletadas (chance 40%)')
natown(); g0 = int(U('mk_ana', 'gold'))
c, _, loc = entregar(204)
confere('Parabéns' in loc and int(U('mk_ana', 'gold')) - g0 == 200 and 'Poção Azul Pequena' in U('mk_ana', 'CONCAT_WS("|",bp1,bp2,bp3,bp4,bp5,bp6,bp7,bp8)'), 'entregue, +200 Ryou e Poção Azul Pequena')

print('9. 205 A Prova do Portão: 4 vitórias seguidas (inimigo nível 3+), sem se curar')
SET('mk_ana', 'currenthp=200')
c, _, loc = aceitar(205)
visitar('mk_ana', 12, 0)
vencer('mk_ana', 2, 2); confere(estado(idA, 205) == 'andamento/0/0', 'inimigo nível 1 não conta')
SET('mk_ana', 'currenthp=150'); vencer('mk_ana', 167, 2); confere(estado(idA, 205) == 'andamento/0/2', 'duas vitórias seguidas')
SET('mk_ana', 'currenthp=140'); visitar('mk_ana', 12, 1)  # perdeu HP (não curou): mantém
confere(estado(idA, 205) == 'andamento/0/2', 'andar sem se curar mantém a sequência')
SET('mk_ana', 'currenthp=200'); visitar('mk_ana', 12, 2)  # curou: zera
confere(estado(idA, 205) == 'andamento/0/0', 'curar zera a sequência')
vencer('mk_ana', 167, 4); natown()
c, _, loc = entregar(205)
confere('Parabéns' in loc and 'Poção Verde Pequena' in U('mk_ana', 'CONCAT_WS("|",bp1,bp2,bp3,bp4,bp5,bp6,bp7,bp8)'), 'entregue, Poção Verde Pequena')

print('10. 206 O Javali da Lavoura: 3 Javalis de Presas Largas')
SET('mk_ana', ", ".join(f"{k}='None'" for k in VAZIA))
c, _, loc = aceitar(206); vencer('mk_ana', 168, 3); natown()
g0 = int(U('mk_ana', 'gold'))
c, _, loc = entregar(206)
confere('Parabéns' in loc and int(U('mk_ana', 'gold')) - g0 == 420 and 'Fragmento Médio de Vida' in U('mk_ana', 'bp1'), 'entregue, +420 Ryou e Fragmento Médio de Vida')

print('11. Fluxo em inglês (mk_bob): aceitar e entregar a 200')
c, _, loc = B('missoesextras.php?do=aceitar', {'id': 200, 'csrf': tB})
confere('accepted' in loc.lower(), f'aceite em inglês ({loc[-60:]})')
vencer('mk_bob', 2, 5)
c, _, loc = B('missoesextras.php?do=entregar', {'id': 200, 'csrf': tB})
confere('Congratulations' in loc or 'completed' in loc.lower(), f'entrega em inglês ({loc[-80:]})')

for n in ('mk_ana', 'mk_bob'):
    sql(f"DELETE FROM naruto_missoesextras_jogador WHERE usuario_id=(SELECT id FROM naruto_users WHERE username='{n}')")
print()
print('RESULTADO:', 'OK, 0 problemas' if not falhas else f'{len(falhas)} problema(s)')
for f in falhas: print('  -', f)
sys.exit(1 if falhas else 0)
