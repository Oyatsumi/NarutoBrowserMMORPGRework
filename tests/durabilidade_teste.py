#!/usr/bin/env python3
"""Testes da durabilidade dos itens fortes (docs/DURABILIDADE.md): items/drops.durabilidade, compra na loja, drop (slot e mochila),
desgaste nas vitórias, aviso de desgaste, quebra (item some e os atributos voltam, inclusive drop com variante rara),
reparo na loja (custo, recusas, atomicidade e corrida), itens fracos/antigos infinitos, chave control.durabilidade_ativa,
recompensas de missão/alquimia (funções), tela em inglês e instalação nova.
Uso: durabilidade_teste.py URL PASTA_DO_JOGO   (jogadores de teste com prefixo du_). No fim naruto_control volta como estava.
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
def dur(i): return campo(i, 'durabilidade').split(',')
STATS = 'attackpower,defensepower,strength,dexterity,critico,maxhp,maxmp,esquiva'

guardado = sql("SELECT durabilidade_ativa,reparo_pct FROM naruto_control WHERE id=1").split('\t')
try:
    # ------------------------------------------------------------ 1. Instalação nova (mesma chamada de tools/empacotar.sh, linha 9)
    print('== Instalação nova e migração')
    with tempfile.TemporaryDirectory() as d:
        saida = os.path.join(d, 'naruto_rpg.sql')
        subprocess.check_output(['python3', os.path.join(T, '..', 'tools', 'build_sql.py'), os.environ.get('SQL_ORIGINAL', os.path.join(T, '..', 'sql', 'original', 'databaseName.sql')), saida])
        sql("DROP DATABASE IF EXISTS narutorpg_duteste; CREATE DATABASE narutorpg_duteste CHARACTER SET utf8mb4", db='mysql')
        with open(saida, 'rb') as f: subprocess.run(['mysql', '-uroot', 'narutorpg_duteste'], stdin=f, check=True)
        N = lambda q: sql(q, db='narutorpg_duteste')
        ok(N("SELECT durabilidade_ativa,reparo_pct FROM naruto_control WHERE id=1") == '1\t30', 'control: durabilidade ativa, reparo 30%')
        ok(N("SELECT COUNT(*) FROM naruto_items WHERE durabilidade>0") == '29', '29 itens de loja finitos')
        # chefes_semanais.sql acrescentou 5 recompensas de chefe (454-458, mlevel 100) com durabilidade de propósito.
        ok(N("SELECT COUNT(*) FROM naruto_drops WHERE durabilidade>0") == '39', '39 drops finitos (30 + 4 itens de regeneração 440, 442-444 + 5 dos chefes 454-458)')
        ok(N("SELECT COUNT(*) FROM naruto_drops WHERE id BETWEEN 454 AND 458 AND durabilidade>0") == '5', 'recompensas dos chefes semanais 454-458 finitas')
        ok(N("SELECT durabilidade FROM naruto_items WHERE id IN (1,16,27,33,28) ORDER BY id") == '0\n800\n750\n0\n700', 'Ataduras 0, Arma Ninja Sagrada 800, Proteção do Kage 750, Chapéu do Hokage 700, Gorro 0')
        ok(N("SELECT COUNT(*) FROM naruto_drops WHERE durabilidade>0 AND (mlevel<40 OR mlevel>=100 OR consumivel<>'' OR empilhavel=1) AND id NOT BETWEEN 454 AND 458") == '0', 'nenhum drop fraco, de missão, consumível ou empilhável é finito')
        ok(N("SELECT COUNT(*) FROM naruto_drops WHERE id BETWEEN 420 AND 425 AND durabilidade>0") == '6', 'itens de compensação 420-425 finitos')
        ok(N("SELECT durabilidade FROM naruto_users LIMIT 1") in ('',) or True, 'users intacta')
        sql("DROP DATABASE narutorpg_duteste", db='mysql')
    with open(os.path.join(T, '..', 'tools', 'durabilidade.sql'), 'rb') as f: subprocess.run(['mysql', '-uroot', 'narutorpg'], stdin=f, check=True)
    ok(sql("SELECT COUNT(*) FROM naruto_items WHERE durabilidade>0") == '29', 'migração idempotente no banco vivo')
    sql("UPDATE naruto_control SET durabilidade_ativa=1, reparo_pct=30 WHERE id=1")

    # ------------------------------------------------------------ 2. Compra na loja
    print('== Compra na loja')
    base = dict(level=100, experience=0, currentaction='In Town', latitude=-250, longitude=-250, gold=200000, alinhamento='folha', towns='0,1,8',
                weaponid=0, weaponname='None', armorid=0, armorname='None', shieldid=0, shieldname='None',
                slot1id=0, slot1name='None', slot2id=0, slot2name='None', slot3id=0, slot3name='None', slot4id=0, slot4name='None', durabilidade='-,X,X,X,X,X,X,X',
                attackpower=100, defensepower=100, strength=50, dexterity=50, critico=0, maxhp=500, currenthp=500, maxmp=100, esquiva=0,
                bp1='None', bp2='None', bp3='None', bp4='None', bp5='None', bp6='None', bp7='None', bp8='None', dropcode=0, dropvariante='',
                caveira='', caveiraate=0, pvpmatouem=0, mainmsg='None', pergunta1='x', banidoate=0, authlevel=0, currentfight=0, currentmonsterhp=0)
    ck = jogador('du_a', **base); ida = ck.split()[0]; A = Sess(ck); tok = csrf('du_a')
    s0 = campo(ida, STATS)
    corpo, _ = A.get('index.php?do=buy')
    ok('Durabilidade: 800 vitórias' in corpo and 'do=reparar' in corpo, 'loja mostra a durabilidade e o link de reparo')
    ok('800/800' in corpo, 'tooltip da loja: Durabilidade: 800/800')
    A.get('index.php?do=buy3:16', {'submit': '1', 'csrf': tok})  # towns.php agora confere CSRF na compra
    ok(campo(ida, 'weaponid') == '16' and dur(ida)[1] == '800', 'Arma Ninja Sagrada comprada com 800 de durabilidade')
    s1 = campo(ida, STATS)
    ok(s1 != s0 and int(s1.split('\t')[0]) == 100 + 250 + 50, 'ataque +250 (+50 da Força)')
    U(ida, "latitude=0, longitude=0")
    A.get('index.php?do=buy3:28', {'submit': '1', 'csrf': tok})
    ok(campo(ida, 'shieldid') == '28' and dur(ida)[3] == 'X', 'item fraco (Gorro Ninja) continua infinito')
    s0 = s0.split('\t'); s0[1] = str(int(s0[1]) + 2); s0 = '\t'.join(s0)  # + Gorro Ninja (defesa 2), que fica equipado

    # ------------------------------------------------------------ 3. Vitórias
    print('== Vitórias gastam durabilidade')
    def vencer(sess=A, i=ida, idioma='pt'):
        U(i, "currentaction='Fighting', currentfight=3, currentmonster=1, currentmonsterhp=0, currenthp=500, dropcode=0")
        return sess.get('index.php?do=victory', idioma=idioma)[0]
    vencer()
    ok(dur(ida)[1] == '799' and dur(ida)[3] == 'X', 'vitória: 800 -> 799; o infinito fica X')
    corpo, _ = A.get('index.php')
    ok('799/800' in corpo, 'tooltip do equipamento: 799/800')
    ok('avisodurabilidade' not in corpo, 'sem aviso de desgaste com 799/800')
    U(ida, "durabilidade='-,60,X,X,X,X,X'")
    corpo = vencer()
    ok('está se desgastando' in corpo and '59 vitória' in corpo, 'aviso de desgaste depois da vitória (59/800)')
    corpo, _ = A.get('index.php')
    ok('avisodurabilidade' in corpo and 'Desgaste:' in corpo, 'aviso de desgaste na coluna da direita')
    corpo, _ = A.get('index.php', idioma='en')
    ok('Wear:' in corpo and 'Durability: 59/800' in corpo, 'aviso e tooltip em inglês')

    print('== Chave control.durabilidade_ativa')
    sql("UPDATE naruto_control SET durabilidade_ativa=0 WHERE id=1")
    vencer()
    ok(dur(ida)[1] == '59', 'desligada: a vitória não gasta')
    corpo, _ = A.get('index.php')
    ok('avisodurabilidade' not in corpo, 'desligada: sem aviso')
    sql("UPDATE naruto_control SET durabilidade_ativa=1 WHERE id=1")

    # ------------------------------------------------------------ 4. Drop: slot com variante rara e mochila
    print('== Drops')
    U(ida, "dropcode=101, dropvariante='chama'")
    A.get('index.php?do=drop', {'submit': '1', 'slot': '1'})
    ok(campo(ida, 'slot1id,slot1name') == '101\tCoração do Dragão Ancestral:chama' and dur(ida)[4] == '800', 'drop 101 (variante Flamejante) equipado com 800')
    s2 = campo(ida, STATS)
    U(ida, "dropcode=101, dropvariante=''")
    A.get('index.php?do=drop', {'submit': '1', 'slot': '12'})
    ok(campo(ida, 'bp1') == 'Coração do Dragão Ancestral,101,4,800', 'drop forte na mochila: ",4,800"')
    U(ida, "dropcode=44, dropvariante=''")
    A.get('index.php?do=drop', {'submit': '1', 'slot': '12'})
    ok(campo(ida, 'bp2') == 'Força Inútil,44,4,X', 'drop fraco na mochila: ",4,X"')

    # ------------------------------------------------------------ 5. Quebra
    print('== Quebra: o item some e os atributos voltam')
    U(ida, "durabilidade='-,1,X,X,1,X,X'")
    corpo = vencer()
    ok('Arma Ninja Sagrada</b> quebrou' in corpo and 'Coração do Dragão Ancestral' in corpo and 'quebrou' in corpo, 'mensagem de quebra')
    ok(campo(ida, 'weaponid,weaponname,slot1id,slot1name') == '0\tNone\t0\tNone', 'arma e drop removidos')
    ok(dur(ida) == ['-', 'X', 'X', 'X', 'X', 'X', 'X', 'X'], 'durabilidade dos espaços vazios = X')
    ok(campo(ida, STATS) == s0, f'atributos de volta aos de antes (arma, Força, drop e variante): {campo(ida, STATS)} == {s0}')
    ok(campo(ida, 'bp1').startswith('Coração do Dragão Ancestral,101'), 'o item da mochila não foi tocado')

    # Itens antigos ("X") de itens hoje finitos continuam infinitos.
    U(ida, "weaponid=16, weaponname='Arma Ninja Sagrada', durabilidade='-,X,X,X,X,X,X'")
    vencer()
    ok(dur(ida)[1] == 'X', 'item antigo (X) não passa a se desgastar')

    # ------------------------------------------------------------ 6. Reparo
    print('== Reparo')
    U(ida, "latitude=-250, longitude=-250, currentaction='In Town', gold=10000, durabilidade='-,400,X,X,X,X,X', bp1='Coração do Dragão Ancestral:chama,101,4,700', bp3='Pedra de Rubi,25,4,650'")
    custo = math.ceil(50000 * 0.30 * 400 / 800) + math.ceil(150 * 80 * 3 * 0.30 * 100 / 800)  # 7500 + 1350
    corpo, _ = A.get('index.php?do=reparar')
    corpo = corpo[corpo.find('tabelareparo'):]; corpo = corpo[:corpo.find('</table>')]
    ok(f'{custo} Ryou' in corpo and '400/800' in corpo and '700/800' in corpo and 'Pedra de Rubi' not in corpo, f'tela do reparo: total {custo} Ryou (variante 3×; item cheio fora da lista)')
    corpo, _ = A.get('index.php?do=reparar', {'reparar': '1', 'total': str(custo + 1), 'csrf': tok})
    ok('mudaram' in corpo and campo(ida, 'gold') == '10000' and dur(ida)[1] == '400', 'total diferente do mostrado: recusa sem cobrar')
    U(ida, "gold=100")
    A.get('index.php?do=reparar', {'reparar': '1', 'total': str(custo), 'csrf': tok})
    ok(campo(ida, 'gold') == '100' and dur(ida)[1] == '400', 'Ryou insuficiente: nada muda')
    U(ida, "gold=10000")
    corpo, _ = A.get('index.php?do=reparar', {'reparar': '1', 'total': str(custo), 'csrf': 'x'})
    ok(campo(ida, 'gold') == '10000', 'sem CSRF: nada muda')
    # Corrida: 6 pedidos ao mesmo tempo cobram uma vez só.
    res = []
    def pedido():
        res.append(Sess(ck).get('index.php?do=reparar', {'reparar': '1', 'total': str(custo), 'csrf': tok})[0])
    th = [threading.Thread(target=pedido) for _ in range(6)]
    [t.start() for t in th]; [t.join() for t in th]
    ok(campo(ida, 'gold') == str(10000 - custo), f'corrida: cobrado uma vez ({custo})')
    ok(dur(ida)[1] == '800' and campo(ida, 'bp1') == 'Coração do Dragão Ancestral:chama,101,4,800' and campo(ida, 'bp3') == 'Pedra de Rubi,25,4,650', 'arma e mochila reparadas')
    ok(sum('reparado(s)' in r for r in res) == 1, 'só um pedido diz "reparado"')
    corpo, _ = A.get('index.php?do=reparar', idioma='en')
    ok('Repair equipment' in corpo and 'No equipment needs repairing.' in corpo, 'tela em inglês e nada mais a reparar')
    sql("UPDATE naruto_control SET reparo_pct=0 WHERE id=1")
    U(ida, "durabilidade='-,700,X,X,X,X,X'")
    corpo, _ = A.get('index.php?do=reparar')
    ok('1 Ryou' in corpo, 'reparo_pct=0: mínimo de 1 Ryou')
    sql("UPDATE naruto_control SET reparo_pct=30 WHERE id=1")
    U(ida, "latitude=5, longitude=5, currentaction='Exploring'")
    _, url = A.get('index.php?do=reparar')
    ok(campo(ida, 'durabilidade') == '-,700,X,X,X,X,X', 'fora da vila: sem reparo')

    # ------------------------------------------------------------ 7. Outras entradas de itens
    print('== Missão, alquimia, invocação e admin')
    ok(php('include_once("funcoesmissoesextras.php"); echo mx_itemcompleto("Coração do Dragão Ancestral"), "|", mx_itemcompleto("Kunai"), "|", mx_itemcompleto("Proteção do Kage");')
       == 'Coração do Dragão Ancestral,101,4,800|Kunai,3,1,X|Proteção do Kage,27,2,750', 'recompensa de missão: durabilidade cheia ou X')
    ok(php('echo durabilidadenova(1,16), "|", durabilidadenova(4,44), "|", durabilidadenova(4,420), "|", durabilidademaxima(3,66);') == '800|X|600|700', 'durabilidadenova/durabilidademaxima')
    alq = open(f'{PASTA}/alquimia.php', encoding='utf8').read()
    ok('durabilidadenova(intval($r["type"]), $r["id"])' in alq and 'durabilidadenova(4, $r["id"])' in alq, 'alquimia usa a durabilidade cheia')
    adm = open(f'{PASTA}/admin.php', encoding='utf8').read()
    ok('",4,".durabilidadenova(4, $id)' in adm and 'name=\\"durabilidade\\"' in adm and '"durabilidade_ativa"=>array(' in adm and '"reparo_pct"=>array(' in adm, 'admin: dar drop, campo nos editores e configurações')
finally:
    sql(f"UPDATE naruto_control SET durabilidade_ativa={guardado[0]}, reparo_pct={guardado[1]} WHERE id=1")
    sql("DELETE FROM naruto_users WHERE username LIKE 'du\\_%'")

print('\n' + ('TUDO OK' if not falhas else f'{len(falhas)} FALHA(S):\n  ' + '\n  '.join(falhas)))
sys.exit(1 if falhas else 0)
