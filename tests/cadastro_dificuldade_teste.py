#!/usr/bin/env python3
"""Teste do cadastro sem vila natal, da recuperação (código x perguntas), da troca de dificuldade e do histórico maior
(docs/TROCA_DE_DIFICULDADE.md).

Uso: python3 cadastro_dificuldade_teste.py http://127.0.0.1:8080 /var/www/narutorpg
Contas de teste: cd_* (criadas pelo próprio cadastro e apagadas no fim), td_ana (pt), td_bob (en), td_adm (admin).
Cobre: formulário sem escolha de vila, vila inicial aplicada (padrão Folha e outra escolhida no painel), campo
"alinhamento" forjado ignorado, código de recuperação sem perguntas aceito, perguntas exigidas no outro método,
show/hide das perguntas no formulário; provações de dificuldade 190-192 (recusas, fluxo completo pelos ganchos reais,
corrida de aceite e de entrega, espera entre trocas, luta em andamento, log de auditoria, ficha); histórico de 12
mensagens (e configurável) sem quebrar históricos antigos; painel e ajuda nos dois idiomas.
"""
import sys, re, time, json, subprocess, threading, urllib.request, urllib.parse

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

def pedir(cookie, idioma, caminho, dados=None, extra=''):
    req = urllib.request.Request(BASE + '/' + caminho, data=urllib.parse.urlencode(dados).encode() if dados is not None else None)
    req.add_header('Cookie', ('narutorpg=' + urllib.parse.quote(cookie) + '; ' if cookie else '') + 'idioma=' + idioma + extra)
    try:
        r = abridor.open(req, timeout=60); cod, corpo, loc = r.status, r.read().decode('utf8', 'replace'), ''
    except urllib.error.HTTPError as e:
        cod, corpo, loc = e.code, e.read().decode('utf8', 'replace'), e.headers.get('Location', '')
    for t, m in ERRO.findall(corpo): falhas.append(f'{caminho}: {t}: {re.sub("<.*?>", "", m)[:200]}')
    if 'Erro SQL' in corpo: falhas.append(f'{caminho}: SQL ' + corpo[corpo.find('Erro SQL'):][:300])
    if cod >= 500: falhas.append(f'{caminho}: HTTP {cod}')
    return cod, corpo, urllib.parse.unquote(loc)

def entrarpainel(nome, cookie, senha='senha123'):
    req = urllib.request.Request(BASE + '/admin.php', data=urllib.parse.urlencode({'senhapainel': senha, 'csrf': token(nome)}).encode())
    req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(cookie) + '; idioma=pt')
    try: hdrs = abridor.open(req, timeout=60).headers
    except urllib.error.HTTPError as e: hdrs = e.headers
    for sc in hdrs.get_all('Set-Cookie') or []:
        m = re.match(r'narutorpg_painel=([^;]+)', sc)
        if m and m.group(1): return '; narutorpg_painel=' + m.group(1)
    return ''

def confere(cond, texto):
    print(('  ok   ' if cond else '  FALHA ') + texto)
    if not cond: falhas.append(texto)

def U(nome, campo):
    return sql(f"SELECT {campo} FROM naruto_users WHERE username='{nome}'")

def paralelo(n, f):
    res = []
    th = [threading.Thread(target=lambda: res.append(f())) for _ in range(n)]
    [t.start() for t in th]; [t.join() for t in th]
    return res

def gancho(nome, codigo):
    php = ("chdir('%s'); $_GET=$_POST=array(); $_COOKIE=array(); include('lib.php'); $link=opendb(); include_once('funcoesmissoesextras.php'); "
           "$controlrow = mysqli_fetch_array(doquery(\"SELECT * FROM {{table}} WHERE id='1' LIMIT 1\", 'control')); "
           "$u = mysqli_fetch_array(doquery(\"SELECT * FROM {{table}} WHERE username='%s' LIMIT 1\", 'users'), MYSQLI_ASSOC); %s") % (PASTA, nome, codigo)
    r = subprocess.run(['php', '-r', php], capture_output=True, text=True)
    if r.stdout.strip() or r.stderr.strip(): falhas.append('gancho: ' + (r.stdout + r.stderr)[:300])

def vencer(nome, idmonstro, vezes):
    gancho(nome, "$m = mysqli_fetch_array(doquery(\"SELECT * FROM {{table}} WHERE id='%d' LIMIT 1\", 'monsters'), MYSQLI_ASSOC); for ($i = 0; $i < %d; $i++) { missoesextras_vitoria($u, $m); $u = mysqli_fetch_array(doquery(\"SELECT * FROM {{table}} WHERE id='\".intval($u['id']).\"' LIMIT 1\", 'users'), MYSQLI_ASSOC); }" % (idmonstro, vezes))

def estado(uid, mid):
    return sql(f"SELECT CONCAT(estado,'/',etapa_atual,'/',progresso) FROM naruto_missoesextras_jogador WHERE usuario_id={uid} AND missao_id={mid}")

def monstro(nivel_min):
    return int(sql(f"SELECT id FROM naruto_monsters WHERE level>={nivel_min} ORDER BY level, id LIMIT 1"))

CTRL = dict(zip(('vilainicial', 'trocadif_dias', 'historicomax'), sql("SELECT vilainicial, trocadif_dias, historicomax FROM naruto_control WHERE id=1").split('\t')))
def restaurar_control():
    sql(f"UPDATE naruto_control SET vilainicial={CTRL['vilainicial']}, trocadif_dias={CTRL['trocadif_dias']}, historicomax={CTRL['historicomax']} WHERE id=1")

# --------------------------------------------------------------------------------------------- cadastro
print('1. Formulário de cadastro: sem escolha de vila, código x perguntas')
sql("DELETE FROM naruto_users WHERE username LIKE 'cd\\_%'")
sql("UPDATE naruto_control SET allowregister=1 WHERE id=1")
c, corpo, _ = pedir('', 'pt', 'users.php?do=register')
confere('name="alinhamento"' not in corpo, 'sem campo de vila natal no formulário')
confere('Todo ninja começa na <b>Vila da Folha</b>' in corpo, 'aviso da vila inicial (pt)')
confere('id="blocoperguntas"' in corpo and "b.style.display=c.checked?'none':''" in corpo, 'perguntas num bloco escondido pelo JavaScript quando o código é escolhido')
confere('Mestre das Provações' in corpo, 'aviso de que a dificuldade pode ser trocada no jogo')
c, corpo, _ = pedir('', 'en', 'users.php?do=register')
confere('Every ninja starts in the <b>Leaf Village</b>' in corpo and 'Master of Trials' in corpo, 'formulário em inglês')

def cadastrar(nome, idioma='pt', **extra):
    d = dict(username=nome, charname=nome, email1='', email2='', password1='segredo123', password2='segredo123', difficulty='2', submit='1')
    d.update(extra)
    return pedir('', idioma, 'users.php?do=register', d)

def perguntas_ok():
    return dict(metodo='perguntas', pergunta1='outra', perguntaoutra1='Qual meu primeiro gato?', resposta1='mingau', pergunta2='outra', perguntaoutra2='Qual minha rua?', resposta2='flores')

c, corpo, _ = cadastrar('cd_forja', alinhamento='pedra', **perguntas_ok())
confere('Sua conta foi criada com sucesso' in corpo, 'cadastro com perguntas aceito')
confere(U('cd_forja', 'alinhamento') == 'folha', f'campo "alinhamento=pedra" forjado ignorado: vila inicial Folha ({U("cd_forja", "alinhamento")})')
confere(U('cd_forja', 'difficulty') == '2', 'dificuldade escolhida no cadastro gravada')
confere(U('cd_forja', 'dificuldadetroca_em') == '0', 'dificuldadetroca_em começa em 0')
c, corpo, _ = cadastrar('cd_codigo', metodo='codigo')
confere('código de recuperação' in corpo and U('cd_codigo', 'codigorecuperacao') != '' and U('cd_codigo', 'pergunta1') == '', 'método código: perguntas não exigidas, código gerado')
c, corpo, _ = cadastrar('cd_codigo2', metodo='codigo', pergunta1='outra', perguntaoutra1='x', resposta1='y')
confere('Sua conta foi criada com sucesso' in corpo and U('cd_codigo2', 'pergunta1') == '', 'método código: perguntas meio preenchidas são ignoradas')
c, corpo, _ = cadastrar('cd_semperg', metodo='perguntas')
confere('Escolha (ou escreva) as duas perguntas secretas' in corpo and U('cd_semperg', 'id') == '', 'método perguntas sem perguntas: recusado (o contrário também é conferido)')
c, corpo, _ = cadastrar('cd_semmetodo')
confere('Escolha (ou escreva) as duas perguntas secretas' in corpo and U('cd_semmetodo', 'id') == '', 'sem método (POST sem rádio) = perguntas exigidas')
sql("UPDATE naruto_control SET vilainicial=5 WHERE id=1")
c, corpo, _ = pedir('', 'pt', 'users.php?do=register')
confere('Todo ninja começa na <b>Vila da Areia</b>' in corpo, 'vila inicial do painel (Areia) no aviso')
c, corpo, _ = cadastrar('cd_areia', alinhamento='folha', **perguntas_ok())
confere(U('cd_areia', 'alinhamento') == 'areia', 'vila inicial do painel aplicada (Areia), forja ignorada')
sql("UPDATE naruto_control SET vilainicial=8 WHERE id=1")  # vila secreta (só por SQL; o painel recusa): volta à regra da vila 1
c, corpo, _ = cadastrar('cd_secreta', **perguntas_ok())
confere(U('cd_secreta', 'alinhamento') == 'folha', 'vila inicial inválida no banco cai para a Folha')
restaurar_control()
sql("DELETE FROM naruto_users WHERE username LIKE 'cd\\_%'")

# --------------------------------------------------------------------------------------------- dificuldade
print('2. Mestre das Provações: quadro, lista e recusas')
NPC = (-9, 3)
DIAS = int(CTRL['trocadif_dias'])
TAXA = {190: 3000, 191: 5000, 192: 8000}
NIVEL = {190: 10, 191: 14, 192: 18}
QTD = {190: 3, 191: 4, 192: 5}
base = dict(level=20, gold=50000, currentaction='Exploring', latitude=NPC[0], longitude=NPC[1], maxhp=900, currenthp=900, maxmp=600, currentmp=600,
            maxtp=200, currenttp=200, towns='0,1', graduacao='Genin', alinhamento='folha', difficulty=1, dificuldadetroca_em=0, historico='None',
            bp1='None', bp2='None', bp3='None', bp4='None', bp5='None', bp6='None', bp7='None', bp8='None', passo_jutsu=0,
            batalha_timer2=0, batalha_nome='None', trocajogador1='None', currentfight=0, jutsuestado='', missao='22,0,0,0', authlevel=0)
for n in ('td_ana', 'td_bob'):
    sql(f"DELETE FROM naruto_missoesextras_jogador WHERE usuario_id=(SELECT id FROM naruto_users WHERE username='{n}')")
idA, ckA = jogador('td_ana', **base)
idB, ckB = jogador('td_bob', **dict(base, difficulty=3))
tA, tB = token('td_ana'), token('td_bob')
A = lambda c, d=None: pedir(ckA, 'pt', c, d)
B = lambda c, d=None: pedir(ckB, 'en', c, d)
SET = lambda nome, s: sql(f"UPDATE naruto_users SET {s} WHERE username='{nome}'")
aceitarA = lambda mid: A('missoesextras.php?do=aceitar', {'id': mid, 'csrf': tA})
entregarA = lambda mid: A('missoesextras.php?do=entregar', {'id': mid, 'csrf': tA})

c, corpo, _ = A('falapersonagens.php')
confere('Sua dificuldade atual: <b>Fácil</b>' in corpo, 'quadro com a dificuldade atual (pt)')
confere('Provação: Caminho do Equilíbrio' in corpo and 'Provação: Caminho Árduo' in corpo and 'Provação: Caminho Suave' not in corpo, 'provação da dificuldade atual escondida, as outras oferecidas')
c, _, loc = aceitarA(190)
confere('Essa já é a sua dificuldade' in loc, 'aceitar a própria dificuldade (POST forjado) recusado')
c, corpo, _ = B('falapersonagens.php')
confere('Your current difficulty: <b>Hard</b>' in corpo and 'Trial: Gentle Path' in corpo and 'Trial: Hard Path' not in corpo, 'quadro e lista em inglês (td_bob, Difícil)')
SET('td_ana', 'level=14')
c, _, loc = aceitarA(191)
confere('nível 15' in loc, f'nível 14 recusado ({loc[-60:]})')
SET('td_ana', 'level=20')
SET('td_ana', f"dificuldadetroca_em={int(time.time()) - 86400}")
c, _, loc = aceitarA(191)
confere('Poderá trocar de novo em' in loc, 'espera conferida no aceite (trocou ontem)')
SET('td_ana', 'dificuldadetroca_em=0')

print('3. Aceite em paralelo (8x) e duas provações ao mesmo tempo')
r = paralelo(8, lambda: aceitarA(192))
confere(sql(f"SELECT COUNT(*) FROM naruto_missoesextras_jogador WHERE usuario_id={idA} AND missao_id=192") == '1' and estado(idA, 192) == 'andamento/0/0', 'uma só linha em andamento')
c, _, loc = aceitarA(191)
confere('outra provação de dificuldade' in loc, 'segunda provação recusada enquanto a primeira está em andamento')

print('4. Passos pelos ganchos reais (vitórias seguidas contra inimigos do patamar) e recusas na entrega')
c, _, loc = entregarA(192)
confere('ainda não completou' in loc, 'entrega com objetivo incompleto recusada')
vencer('td_ana', monstro(1) if NIVEL[192] > 1 else 1, 3)
fraco = int(sql(f"SELECT id FROM naruto_monsters WHERE level<{NIVEL[192]} ORDER BY level DESC LIMIT 1"))
vencer('td_ana', fraco, 3)
confere(estado(idA, 192) == 'andamento/0/0', f'inimigos abaixo do nível {NIVEL[192]} não contam')
vencer('td_ana', monstro(NIVEL[192]), QTD[192])
confere(estado(idA, 192) == 'andamento/1/0', f'{QTD[192]} vitórias seguidas contra nível {NIVEL[192]}+ concluem a etapa 1')
SET('td_ana', 'gold=1000')
c, _, loc = entregarA(192)
confere('ainda não completou' in loc and U('td_ana', 'difficulty') == '1' and U('td_ana', 'gold') == '1000', 'sem a oferenda: recusado, nada muda')
SET('td_ana', "gold=50000, currentaction='Fighting'")
c, _, loc = entregarA(192)
confere(U('td_ana', 'difficulty') == '1' and estado(idA, 192) == 'andamento/1/0', f'no meio de uma luta: recusado ({loc[-70:]})')
SET('td_ana', "currentaction='Exploring'")
SET('td_ana', f"dificuldadetroca_em={int(time.time()) - 3600}")
c, _, loc = entregarA(192)
confere('Poderá trocar de novo em' in loc and U('td_ana', 'difficulty') == '1', 'espera conferida também na entrega')
SET('td_ana', 'dificuldadetroca_em=0')
SET('td_ana', 'difficulty=3')
c, _, loc = entregarA(192)
confere('Essa já é a sua dificuldade' in loc, 'dificuldade mudou por fora (painel) antes da entrega: recusado')
SET('td_ana', 'difficulty=1')

print('5. Entrega em paralelo (8x): uma troca, uma cobrança, uma linha no histórico, um registro de auditoria')
logantes = int(sql("SELECT COUNT(*) FROM naruto_adminlog WHERE acao='troca_dificuldade'"))
antes = int(time.time())
r = paralelo(8, lambda: entregarA(192))
ok = [loc for c, _, loc in r if 'Parabéns' in loc]
confere(len(ok) == 1, f'uma só entrega com sucesso ({len(ok)})')
confere(ok and 'sua dificuldade é Difícil' in re.sub('<[^>]+>', '', ok[0]), 'mensagem da nova dificuldade' + ('' if not ok else f' ({ok[0][-160:]})'))
confere(U('td_ana', 'difficulty') == '3', 'difficulty = 3')
confere(U('td_ana', 'gold') == str(50000 - TAXA[192]), f'oferenda cobrada uma vez (gold {U("td_ana", "gold")})')
confere(int(U('td_ana', 'dificuldadetroca_em')) >= antes, 'data da troca gravada')
hist = U('td_ana', 'historico')
confere(hist.count('trocou de dificuldade') == 1 and hist.count('Provação: Caminho Árduo') == 1, 'histórico com uma troca e uma missão')
confere(int(sql("SELECT COUNT(*) FROM naruto_adminlog WHERE acao='troca_dificuldade'")) == logantes + 1
        and sql(f"SELECT detalhes FROM naruto_adminlog WHERE acao='troca_dificuldade' ORDER BY id DESC LIMIT 1") == 'td_ana: 1 -> 3 (missao 192)', 'um registro de auditoria troca_dificuldade')
confere(estado(idA, 192).startswith('concluida'), 'missão concluída')

print('6. Depois da troca: ficha, histórico, luta usa a nova dificuldade')
c, corpo, _ = A('index.php')
confere('Você trocou de dificuldade: Fácil → Difícil.' in corpo, 'troca no histórico da coluna da esquerda (pt)')
c, corpo, _ = A('index.php?do=showchar')
confere('Dificuldade: Difícil <span class="small">(desde' in corpo, 'ficha (Todos os Status) mostra Difícil e a data')
c, corpo, _ = pedir(ckA, 'en', 'index.php')
confere('You changed your difficulty: Easy → Hard.' in corpo, 'histórico traduzido (en)')
# A dificuldade só é usada na luta (fight.php lê users.difficulty na hora): nenhum valor guardado precisa ser recalculado.
fonte = open(PASTA + '/fight.php', encoding='utf8').read()
confere(fonte.count('$userrow["difficulty"]') >= 6 and not re.search(r"difficulty\s*=\s*'", fonte), 'fight.php só lê a dificuldade na hora (HP/dano do inimigo, exp, Ryou); nada guardado')

print('7. Espera entre trocas (control.trocadif_dias) e volta')
c, corpo, _ = A('falapersonagens.php')
corpo = corpo[corpo.find('Sua dificuldade atual'):]  # só o painel do Mestre (o histórico da esquerda cita a missão)
confere('Próxima troca possível em' in corpo and 'Provação: Caminho Suave' in corpo and 'Provação: Caminho Árduo' not in corpo, 'quadro mostra a espera; a lista segue a nova dificuldade')
c, _, loc = aceitarA(190)
confere(f'Poderá trocar de novo em {DIAS} dia(s) e 0 hora(s)' in loc, f'aceite recusado durante a espera ({loc[-60:]})')
SET('td_bob', f"dificuldadetroca_em={int(time.time()) - 3600}")
c, _, loc = B('missoesextras.php?do=aceitar', {'id': 190, 'csrf': tB})
confere('You can change again in' in loc, 'recusa em inglês (td_bob)')
sql("UPDATE naruto_control SET trocadif_dias=0 WHERE id=1")
c, _, loc = B('missoesextras.php?do=aceitar', {'id': 190, 'csrf': tB})
confere('accepted' in loc.lower(), f'com trocadif_dias = 0 não há espera ({loc[-60:]})')
restaurar_control()
SET('td_ana', f"dificuldadetroca_em={int(time.time()) - (DIAS + 1) * 86400}")
c, _, loc = aceitarA(190)
confere('aceita' in loc and estado(idA, 190) == 'andamento/0/0', 'depois da espera a provação de volta é aceita')
vencer('td_ana', monstro(NIVEL[190]), QTD[190])
SET('td_ana', 'gold=10000')
c, _, loc = entregarA(190)
confere('Parabéns' in loc and U('td_ana', 'difficulty') == '1' and U('td_ana', 'gold') == str(10000 - TAXA[190]), 'volta para Fácil concluída')
confere(U('td_ana', 'historico').count('trocou de dificuldade') == 2, 'duas trocas no histórico')

# --------------------------------------------------------------------------------------------- histórico
print('8. Histórico maior (control.historicomax, padrão 12)')
antigo = 'velha 1;;velha 2;;velha 3'  # histórico antigo (até 5 mensagens) continua valendo
SET('td_ana', f"historico='{antigo}'")
c, corpo, _ = A('index.php')
confere(all(f'velha {i}' in corpo for i in (1, 2, 3)) and U('td_ana', 'historico') == antigo, 'histórico antigo curto aparece inteiro e não é mexido')
SET('td_ana', "historico='" + ';;'.join(f'msg {i:02d}' for i in range(1, 21)) + "'")
c, corpo, _ = A('index.php')
vis = re.findall(r'msg (\d\d)', corpo)
confere(len(vis) == 12 and vis[0] == '20' and vis[-1] == '09', f'mostra as 12 mais recentes, da mais nova para a mais velha ({len(vis)})')
confere(U('td_ana', 'historico').count(';;') == 11, 'guarda só 12 no banco')
sql("UPDATE naruto_control SET historicomax=15 WHERE id=1")
SET('td_ana', "historico='" + ';;'.join(f'msg {i:02d}' for i in range(1, 21)) + "'")
c, corpo, _ = A('index.php')
confere(len(re.findall(r'msg (\d\d)', corpo)) == 15, 'historicomax = 15 no painel: mostra 15')
restaurar_control()

# --------------------------------------------------------------------------------------------- painel e ajuda
print('9. Painel do administrador')
idM, ckM = jogador('td_adm', authlevel=1, level=20, gold=10000)
PN = entrarpainel('td_adm', ckM)
confere(PN != '', 're-autenticação do painel')
c, corpo, _ = pedir(ckM, 'pt', 'admin.php?do=main', extra=PN)
confere('name="vilainicial"' in corpo and 'name="trocadif_dias"' in corpo and 'name="historicomax"' in corpo, 'Configurações gerais com vila inicial, espera da dificuldade e tamanho do histórico')
sel = re.search(r'name="vilainicial">(.*?)</select>', corpo, re.S)
confere(sel and 'value="8"' not in sel.group(1) and 'value="2"' not in sel.group(1) and re.search(r'value="1" selected', sel.group(1)), 'só vilas natais comuns na lista; Folha selecionada')
c, corpo, _ = pedir(ckM, 'pt', 'admin_missoes.php?do=editar:191', extra=PN)
confere(re.search(r'name="cfg_troca_dificuldade">.*?<option value="2" selected="selected">', corpo, re.S) is not None, 'editor da missão 191 com a dificuldade de destino')
c, corpo, _ = pedir(ckM, 'en', 'admin_missoes.php?do=editar:191', extra=PN)
confere('Difficulty change' in corpo, 'editor em inglês')

print('10. Ajuda nos dois idiomas')
c, corpo, _ = A('help.php')
confere('<a name="trocadif"></a>Trocar de Dificuldade' in corpo and f'<b>{DIAS} dia(s)</b>' in corpo and 'Difícil: 8.000 Ryou' in corpo, 'ajuda (pt) com espera e oferendas atuais')
confere('que não pode ser trocado depois' not in corpo and 'A vila natal é escolhida no cadastro' not in corpo, 'ajuda (pt) não fala mais em escolher a vila / dificuldade fixa')
c, corpo, _ = B('help.php')
confere('Changing your Difficulty' in corpo and 'Hard: 8,000 Ryou' in corpo and 'which cannot be changed later' not in corpo and 'chosen at registration, but' not in corpo, 'ajuda (en)')

for n in ('td_ana', 'td_bob'):
    sql(f"DELETE FROM naruto_missoesextras_jogador WHERE usuario_id=(SELECT id FROM naruto_users WHERE username='{n}')")
print()
print('RESULTADO:', 'OK, 0 problemas' if not falhas else f'{len(falhas)} problema(s)')
for f in falhas: print('  -', f)
sys.exit(1 if falhas else 0)
