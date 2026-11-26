#!/usr/bin/env python3
"""Testes do custo do "Viajar Para" (towns.php :: travelto(), funcoesinclusas.php :: custoviagem(); docs/PONTOS_E_JUTSUS.md):
vila natal mais cara que o Pergaminho de Retorno, TP por distância, Selo de Viagem (drop 436) exigido e gasto nas outras vilas,
recusas sem cobrança parcial, tela de viagem/dica com o custo, valores do administrador, instalação nova e corrida.
Uso: viagem_custo_teste.py URL PASTA_DO_JOGO   (jogadores de teste com prefixo vc_). No fim naruto_control volta como estava.
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
ERRO = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice)</b>:(.*?)<br', re.S)
class Sess:
    def __init__(s, cookie):
        s.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        s.cookie = cookie; s.extra = ''
    def get(s, p, dados=None, idioma='pt'):
        req = urllib.request.Request(BASE + '/' + p, data=urllib.parse.urlencode(dados).encode() if dados is not None else None)
        req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(s.cookie) + '; idioma=' + idioma + s.extra)
        try: r = s.op.open(req, timeout=60); corpo = r.read().decode('utf8', 'replace'); url = r.geturl()
        except urllib.error.HTTPError as e: corpo = e.read().decode('utf8', 'replace'); url = ''
        for t, m in ERRO.findall(corpo): falhas.append(f'PHP {t} em {p}: {re.sub("<.*?>", "", m)[:150]}')
        return corpo, urllib.parse.unquote(url)
def U(i, campos): sql(f"UPDATE naruto_users SET {campos} WHERE id={i}")
def campo(i, c): return sql(f"SELECT {c} FROM naruto_users WHERE id={i}")

PADROES = dict(viagemryou=5, viagemtpbase=10, viagemtpdist=10, viagemnataltppct=300, viagemnatalryoupct=400, viagemoutraryou=300, viagemselos=1, viagemseloitem=436)
COLS = ','.join(PADROES) + ',tpretorno,tpretornopct'
guardado = sql(f"SELECT {COLS} FROM naruto_control WHERE id=1").split('\t')
SELO = 'Selo de Viagem,436,4,X'
try:
    # ------------------------------------------------------------ 1. Instalação nova (mesma chamada de tools/empacotar.sh, linha 9)
    print('== Instalação nova')
    with tempfile.TemporaryDirectory() as d:
        saida = os.path.join(d, 'naruto_rpg.sql')
        subprocess.check_output(['python3', os.path.join(T, '..', 'tools', 'build_sql.py'), os.environ.get('SQL_ORIGINAL', os.path.join(T, '..', 'sql', 'original', 'databaseName.sql')), saida])
        sql("DROP DATABASE IF EXISTS narutorpg_vcteste; CREATE DATABASE narutorpg_vcteste CHARACTER SET utf8mb4", db='mysql')
        with open(saida, 'rb') as f: subprocess.run(['mysql', '-uroot', 'narutorpg_vcteste'], stdin=f, check=True)
        novo = sql(f"SELECT {','.join(PADROES)} FROM naruto_control WHERE id=1", db='narutorpg_vcteste').split('\t')
        ok(novo == [str(v) for v in PADROES.values()], f'padrões do control ({dict(zip(PADROES, novo))})')
        ok(sql("SELECT name,empilhavel,maxpilha FROM naruto_drops WHERE id=436", db='narutorpg_vcteste') == 'Selo de Viagem\t1\t10', 'drop 436 Selo de Viagem empilhável')
        ok(int(sql("SELECT COUNT(*) FROM naruto_monster_drops WHERE drop_id=436", db='narutorpg_vcteste')) > 50, 'cai de inimigos de nível 20-60')
        ok(sql("SELECT COUNT(*) FROM naruto_receitas WHERE resultado_tipo='drop' AND resultado_id='436'", db='narutorpg_vcteste') == '1', 'receita de Alquimia')
        ok(sql("SELECT COUNT(*) FROM naruto_missoesextras WHERE recompensa_item='Selo de Viagem' AND repetivel=1", db='narutorpg_vcteste') == '2', 'recompensa de 2 missões repetíveis')
        sql("DROP DATABASE narutorpg_vcteste", db='mysql')
    for f in ('drops', 'consumiveis'):
        ok(os.path.exists(f'{PASTA}/layoutnovo/equipamentos/{f}/436.gif'), f'ícone {f}/436.gif')
    # A migração roda de novo sem erro nem duplicar nada.
    with open(os.path.join(T, '..', 'tools', 'viagem_custo.sql'), 'rb') as f: subprocess.run(['mysql', '-uroot', 'narutorpg'], stdin=f, check=True)
    ok(sql("SELECT COUNT(*) FROM naruto_receitas WHERE resultado_id='436'") == '1', 'migração idempotente')

    sql("UPDATE naruto_control SET " + ','.join(f"{k}='{v}'" for k, v in PADROES.items()) + ", tpretorno=5, tpretornopct=50 WHERE id=1")
    base = dict(level=30, currentaction='In Town', latitude=30, longitude=170, maxtp=300, currenttp=300, gold=5000, alinhamento='folha', towns='1,3,4,9',
                bp1='None', bp2='None', bp3='None', bp4='None', bp5='None', bp6='None', bp7='None', bp8='None', caveira='', caveiraate=0, pvpmatouem=0,
                mainmsg='None', pergunta1='x', banidoate=0, authlevel=0)
    ck = jogador('vc_a', **base); ida = ck.split()[0]; A = Sess(ck); tok = csrf('vc_a')
    def ir(vila, conf=True, idioma='pt'):
        return A.get(f"index.php?do=gotown:{vila}&csrf={tok}" + ("&confirmar=1" if conf else ''), idioma=idioma)
    def tpdist(tp_vila, lat, lon, lat2, lon2): return tp_vila + 10 + math.ceil(math.ceil(math.hypot(lat2 - lat, lon2 - lon)) * 10 / 100)

    # ------------------------------------------------------------ 2. Vila natal
    print('== Vila natal (Folha) x Pergaminho de Retorno')
    corpo, _ = A.get('index.php')
    dist = math.ceil(math.hypot(30, 170)); tpn = max(tpdist(5, 30, 170, 0, 0), 15)
    ok(f'Vila da Folha - {tpn} TP + 600 Ryou (vila natal)' in corpo, f'dica da lista mostra o custo da vila natal ({tpn} TP + 600 Ryou)')
    tpo = tpdist(15, 30, 170, 125, -70)
    ok(f'Vila da Pedra - {tpo} TP + {300 + 5 * tpo} Ryou + 1× Selo de Viagem' in corpo, f'dica da lista mostra o custo de outra vila ({tpo} TP)')
    corpo, _ = ir(1, conf=False)
    ok('Viajar para Vila da Folha' in corpo and f'<b>{tpn}</b>' in corpo and '<b>600</b>' in corpo and 'confirmar=1' in corpo, 'tela de viagem mostra custo e botão')
    ok(campo(ida, 'latitude,longitude') == '30\t170', 'tela de viagem não viaja')
    ir(1)
    ok(campo(ida, 'latitude,longitude,currenttp,gold') == f'0\t0\t{300 - tpn}\t{5000 - 600}', f'chegou à Folha: -{tpn} TP, -600 Ryou (Retorno: 5 TP e 150 Ryou)')
    # Perto da vila natal o piso (300% do TP do Retorno = 15) vale; o TP máximo limita.
    U(ida, "latitude=0, longitude=2, currenttp=300, gold=5000")
    ir(1)
    ok(campo(ida, 'currenttp,gold') == f'{300 - 16}\t4400', 'a 2 casas: 16 TP pela distância e o piso de 600 Ryou')
    sql("UPDATE naruto_control SET tpretorno=10 WHERE id=1")  # Retorno: 10 TP -> piso da vila natal = 30 TP
    U(ida, "latitude=0, longitude=2, currenttp=300, gold=5000")
    ir(1)
    ok(campo(ida, 'currenttp,gold') == f'{300 - 30}\t4400', 'piso da vila natal: 300% do TP do Retorno (30 TP) e 600 Ryou')
    sql("UPDATE naruto_control SET tpretorno=5 WHERE id=1")
    U(ida, "latitude=0, longitude=2, currenttp=12, maxtp=12, gold=5000")
    ir(1)
    ok(campo(ida, 'latitude,currenttp') == '0\t0', 'TP máximo 12: a vila natal custa no máximo o TP cheio (sempre possível)')

    # ------------------------------------------------------------ 3. Outras vilas: selo, distância
    print('== Outras vilas')
    U(ida, "latitude=0, longitude=0, currenttp=300, maxtp=300, gold=5000, currentaction='In Town'")
    corpo, url = ir(3)
    ok(campo(ida, 'latitude,longitude,currenttp,gold') == '0\t0\t300\t5000' and 'Selo de Viagem' in url, 'sem selo: recusado, nada cobrado')
    corpo, url = ir(3, idioma='en')
    ok('Travel Seal' in url, 'mensagem em inglês')
    corpo, _ = ir(3, conf=False)
    ok('Falta: Selo de Viagem' in corpo and 'confirmar=1' not in corpo, 'tela de viagem avisa o que falta')
    U(ida, f"bp2='{SELO},4'")
    tp3 = tpdist(15, 0, 0, 125, -70); ry3 = 300 + 5 * tp3
    ir(3)
    ok(campo(ida, 'latitude,longitude,currenttp,gold,bp2') == f'125\t-70\t{300 - tp3}\t{5000 - ry3}\t{SELO},3', f'Folha -> Pedra: -{tp3} TP, -{ry3} Ryou, -1 selo')
    # Distância: Pedra -> Névoa custa mais que Folha -> Névoa? Conferir a fórmula pela posição.
    U(ida, "latitude=0, longitude=0, currenttp=300, gold=5000")
    tp4a = tpdist(30, 0, 0, 30, 170); ir(4)
    ok(campo(ida, 'currenttp') == str(300 - tp4a), f'Folha -> Névoa ({tp4a} TP)')
    U(ida, "latitude=125, longitude=-70, currenttp=300, gold=5000")
    tp4b = tpdist(30, 125, -70, 30, 170); ir(4)
    ok(campo(ida, 'currenttp') == str(300 - tp4b) and tp4b > tp4a, f'Pedra -> Névoa, mais longe: {tp4b} TP > {tp4a} TP')
    ok(campo(ida, 'bp2') == SELO, 'pilha de selos chegou a 1')
    # Último selo: o espaço fica vazio.
    U(ida, "latitude=0, longitude=0, currenttp=300, gold=5000"); ir(3)
    ok(campo(ida, 'latitude,bp2') == '125\tNone', 'último selo gasto: espaço vazio')

    # ------------------------------------------------------------ 4. Recusas sem cobrança parcial
    print('== Recusas')
    U(ida, f"latitude=0, longitude=0, currenttp=5, gold=5000, bp1='{SELO}'")
    corpo, url = ir(3)
    ok(campo(ida, 'latitude,currenttp,gold,bp1') == f'0\t5\t5000\t{SELO}' and 'TP suficiente' in url, 'sem TP: nada cobrado')
    U(ida, "currenttp=300, gold=10")
    corpo, url = ir(3)
    ok(campo(ida, 'latitude,currenttp,gold,bp1') == f'0\t300\t10\t{SELO}' and 'Ryou suficiente' in url, 'sem Ryou: nada cobrado')
    U(ida, "gold=10, latitude=30, longitude=170")
    corpo, url = ir(1)
    ok(campo(ida, 'latitude,currenttp,gold') == '30\t300\t10', 'vila natal sem Ryou: nada cobrado')
    corpo, _ = A.get(f"index.php?do=gotown:4&confirmar=1&csrf=errado")
    ok(campo(ida, 'latitude') == '30', 'sem CSRF: não viaja')
    U(ida, "towns='1'")
    ir(3)
    ok(campo(ida, 'latitude,currenttp') == '30\t300', 'sem o mapa: não viaja')
    U(ida, "towns='1,3,4,9', gold=5000, caveira='branca', caveiraate=UNIX_TIMESTAMP()+86400, pvpmatouem=UNIX_TIMESTAMP()-60")
    corpo, url = ir(3)
    ok(campo(ida, 'latitude,currenttp,gold,bp1') == f'30\t300\t5000\t{SELO}' and 'portões' in url, 'caveira exposta: barrado sem gastar nada')
    U(ida, "caveira='', caveiraate=0, pvpmatouem=0")
    # O selo não é equipável pela mochila.
    A.get('backpack.php?qual=1')
    ok(campo(ida, 'bp1,slot1id') == f'{SELO}\t0', 'selo não é equipado pela mochila')

    # ------------------------------------------------------------ 5. Valores do administrador
    print('== Administrador')
    sql("UPDATE naruto_control SET viagemselos=0, viagemoutraryou=0, viagemtpbase=0, viagemtpdist=0 WHERE id=1")
    U(ida, "latitude=0, longitude=0, currenttp=300, gold=5000")
    ir(3)
    ok(campo(ida, 'latitude,currenttp,gold,bp1') == f'125\t285\t{5000 - 75}\t{SELO}', 'selos=0, base=0, dist=0: 15 TP, 75 Ryou e nenhum selo')
    sql("UPDATE naruto_control SET " + ','.join(f"{k}='{v}'" for k, v in PADROES.items()) + " WHERE id=1")
    U(ida, "authlevel=1")
    req = urllib.request.Request(BASE + '/admin.php', data=urllib.parse.urlencode({'senhapainel': 'senha123', 'csrf': tok}).encode())
    req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(ck))
    class SemRedir(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k): return None
    try: hdrs = urllib.request.build_opener(SemRedir).open(req, timeout=60).headers
    except urllib.error.HTTPError as e: hdrs = e.headers
    for sc in hdrs.get_all('Set-Cookie') or []:
        m = re.match(r'narutorpg_painel=([^;]+)', sc)
        if m: A.extra = '; narutorpg_painel=' + m.group(1)
    corpo, _ = A.get('admin.php?do=main')
    ok(all(f'name="{k}"' in corpo for k in PADROES), 'painel do administrador tem todos os campos')
    U(ida, "authlevel=0")
    corpo, _ = A.get('help.php')
    ok('Selo de Viagem' in corpo and '600 Ryou' in corpo, 'ajuda explica o custo (pt)')
    corpo, _ = A.get('help.php', idioma='en')
    ok('Travel Seal' in corpo, 'ajuda em inglês')

    # ------------------------------------------------------------ 6. Corrida
    print('== Corrida')
    U(ida, f"latitude=0, longitude=0, currenttp=300, gold=5000, bp1='{SELO}', bp2='None'")
    th = [threading.Thread(target=lambda v=v: ir(v)) for v in (3, 4) * 5]
    [t.start() for t in th]; [t.join() for t in th]
    r = campo(ida, 'latitude,currenttp,gold,bp1').split('\t')
    gasto_tp = 300 - int(r[1]); gasto_ryou = 5000 - int(r[2])
    ok(r[3] == 'None' and r[0] in ('125', '30') and gasto_tp in (tp3, tp4a) and gasto_ryou == 300 + 5 * gasto_tp,
       f'10 viagens simultâneas com 1 selo: só uma vale, cobrada uma vez ({r})')
finally:
    sql("UPDATE naruto_control SET " + ','.join(f"{k}='{v}'" for k, v in zip(COLS.split(','), guardado)) + " WHERE id=1")
    sql("UPDATE naruto_users SET latitude=95, longitude=95, currentaction='Exploring' WHERE username LIKE 'vc\\_%'")

print('\n' + ('TUDO OK' if not falhas else f'{len(falhas)} FALHA(S):\n  ' + '\n  '.join(falhas)))
sys.exit(1 if falhas else 0)
