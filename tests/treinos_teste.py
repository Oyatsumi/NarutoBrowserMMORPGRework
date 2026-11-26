#!/usr/bin/env python3
"""Teste ponta a ponta dos treinos longos (treinoslongos.php, funcoestreinos.php; docs/TREINOS.md) via HTTP.

Para cada um dos seis treinos: requisitos (level baixo, vila errada, Senjutsu), adquirir pelo mestre, primeira sessão
com o bônus por sessão, espera obrigatória entre sessões, corrida de cliques simultâneos (só uma sessão conta),
conclusão com a recompensa final, clique depois de concluído e o painel (dadosdotreino) em português e inglês.
Também confere o efeito das recompensas: custo de Chakra (custochakra), TP no descanso (encherhp.php), gasto de NP do
Senjutsu (senjutsuestado) e o jutsu liberado só ao concluir.

Uso: python3 treinos_teste.py http://127.0.0.1:8080 /var/www/narutorpg
"""
import sys, re, subprocess, threading, urllib.request, urllib.parse, time

BASE, PASTA = sys.argv[1].rstrip('/'), sys.argv[2]
TESTES = __file__.rsplit('/', 1)[0]
ERRO = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice|Parse error)</b>:')
falhas, oks = [], 0

class SemRedir(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
abridor = urllib.request.build_opener(SemRedir)

def sql(q):
    return subprocess.check_output(['mysql', '--default-character-set=utf8mb4', '-N', '-B', 'narutorpg', '-e', q], text=True).strip()

def jogador(nome, **campos):
    args = ['php', f'{TESTES}/mkuser.php', PASTA, nome] + [f'{k}={v}' for k, v in campos.items()]
    return subprocess.check_output(args, text=True).strip()

def pedir(cookie, caminho, idioma='pt'):
    req = urllib.request.Request(BASE + '/' + caminho)
    req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(cookie) + '; idioma=' + idioma)
    try:
        r = abridor.open(req, timeout=60); cod, corpo, loc = r.status, r.read().decode('utf8', 'replace'), ''
    except urllib.error.HTTPError as e:
        cod, corpo, loc = e.code, e.read().decode('utf8', 'replace'), e.headers.get('Location', '')
    if ERRO.search(corpo) or cod >= 500: falhas.append(f'erro PHP/HTTP {cod} em {caminho}: {corpo[:300]}')
    return cod, corpo, urllib.parse.unquote(loc)

def checar(cond, msg):
    global oks
    if cond: oks += 1
    else: falhas.append(msg); print('  FALHA:', msg)

def u(nome, campos):
    return dict(zip(campos.split(','), sql(f"SELECT {campos} FROM naruto_users WHERE username='{nome}'").split('\t')))

def entrada(nome, treino):
    t = sql(f"SELECT treinamento FROM naruto_users WHERE username='{nome}'")
    for p in t.split(';'):
        if p.split(',')[0] == treino: return p.split(',')
    return None

def liberar(nome):
    """Coloca a data da última sessão no passado (simula o fim do intervalo)."""
    t = sql(f"SELECT treinamento FROM naruto_users WHERE username='{nome}'")
    novo = ';'.join(re.sub(r'^([^,]*,\d+,\d+),[^,]*,[^,]*,', r'\1,1/1/2020,00:00:00,', p) if p else p for p in t.split(';'))
    sql(f"UPDATE naruto_users SET treinamento='{novo}' WHERE username='{nome}'")

def phpexpr(nome, expr):
    code = (f"chdir('{PASTA}'); $_GET=$_POST=$_COOKIE=array(); include('lib.php'); opendb(); include_once('funcoesinclusas.php');"
            f"$userrow = mysqli_fetch_array(doquery(\"SELECT * FROM {{{{table}}}} WHERE username='{nome}' LIMIT 1\", 'users')); echo {expr};")
    return subprocess.check_output(['php', '-r', code], text=True).strip()

TREINOS = {r.split('\t')[0]: r.split('\t') for r in sql("SELECT chave,nome,vila_id,nivel_min,graduacao_min,sessoes,intervalo,bonus_sessao,bonus_final,bonus_extra,jutsu_id FROM naruto_treinos ORDER BY id").split('\n')}
VILAS = {r.split('\t')[0]: r.split('\t')[1:] for r in sql("SELECT id,latitude,longitude FROM naruto_towns").split('\n')}
EN = {'elemento': 'Element Mastery', 'chakra': 'Chakra Control', 'ferro': 'Iron Body', 'caminhada': 'Ninja Trek', 'meditacao': 'Nature Meditation', 'jutsuraro': 'Rare Jutsu Training'}
CAMPO = {'elemento': 'maxep', 'chakra': 'maxmp', 'ferro': 'maxhp', 'caminhada': 'maxtp', 'meditacao': 'maxnp', 'jutsuraro': 'maxhp'}

for chave, (_, nome, vila, nivel, grad, sessoes, intervalo, bs, bf, bx, jutsu) in TREINOS.items():
    if chave not in CAMPO: continue  # treinos de doujutsu: testados em doujutsu_teste.py
    print(f'== {nome} ({chave})')
    sessoes, intervalo, bs, bf, bx = int(sessoes), int(intervalo), int(bs), int(bf), int(bx)
    lat, lon = VILAS[vila]
    outra = next(v for v in VILAS if v != vila)
    nomej = f'tt_{chave}'
    sql(f"DELETE FROM naruto_users WHERE username='{nomej}'")
    base = dict(level=int(nivel) + 5, graduacao='Chuunin', latitude=lat, longitude=lon, currentaction='In Town', treinamento='None',
                senjutsuhtml='fechado', maxhp=200, currenthp=200, maxmp=100, currentmp=100, maxtp=50, currenttp=50, maxnp=100, currentnp=100,
                maxep=5, currentep=5, defensepower=10, charclass=2, spells='0,1')
    ck = jogador(nomej, **base)
    rota = f'treinoslongos.php?do=treinar&t={chave}'

    # Requisitos: level baixo, graduação, vila errada e (Meditação) Senjutsu.
    jogador(nomej, level=int(nivel) - 1)
    pedir(ck, rota + '&inicio=1'); checar(entrada(nomej, nome) is None, f'{chave}: level baixo não deveria adquirir')
    jogador(nomej, level=int(nivel) + 5)
    if grad:
        jogador(nomej, graduacao='Estudante da Academia')
        pedir(ck, rota + '&inicio=1'); checar(entrada(nomej, nome) is None, f'{chave}: graduação baixa não deveria adquirir')
        jogador(nomej, graduacao='Chuunin')
    if chave == 'meditacao':
        jogador(nomej, senjutsuhtml='')
        pedir(ck, rota + '&inicio=1'); checar(entrada(nomej, nome) is None, f'{chave}: sem Senjutsu não deveria adquirir')
        jogador(nomej, senjutsuhtml='fechado')
    jogador(nomej, latitude=VILAS[outra][0], longitude=VILAS[outra][1])
    _, _, loc = pedir(ck, rota + '&inicio=1'); checar(entrada(nomej, nome) is None and 'só pode ser feito' in loc, f'{chave}: vila errada não deveria adquirir ({loc})')
    jogador(nomej, latitude=lat, longitude=lon)

    # Fala do mestre e aquisição.
    _, corpo, _ = pedir(ck, f'treinoslongos.php?do=falar&t={chave}')
    checar('inicio=1' in corpo and nome in corpo, f'{chave}: fala do mestre sem o link de início')
    _, corpo, _ = pedir(ck, 'index.php')
    checar(f'treinoslongos.php?do=falar&amp;t={chave}' in corpo, f'{chave}: ícone do mestre não aparece na vila')
    _, _, loc = pedir(ck, rota + '&inicio=1')
    e = entrada(nomej, nome); checar(e is not None and e[1] == '0' and e[2] == str(sessoes), f'{chave}: aquisição {e}')
    _, _, loc = pedir(ck, rota + '&inicio=1'); checar('já possui' in loc, f'{chave}: segunda aquisição deveria avisar ({loc})')

    antes = u(nomej, f'{CAMPO[chave]},treino_hp,treino_mp,treino_tp,treino_ep,treino_np')
    # Sessão 1 (a aquisição não tem espera) e espera obrigatória.
    pedir(ck, rota)
    e = entrada(nomej, nome); checar(e[1] == '1' and e[5] == str(intervalo), f'{chave}: sessão 1 {e}')
    depois = u(nomej, CAMPO[chave])
    esperado = bs if chave != 'elemento' else min(bs, bf)
    checar(int(depois[CAMPO[chave]]) - int(antes[CAMPO[chave]]) == (esperado if chave != 'jutsuraro' else 0), f'{chave}: bônus da sessão 1 {antes} -> {depois}')
    _, _, loc = pedir(ck, rota)
    checar(entrada(nomej, nome)[1] == '1' and 'aguardar' in loc, f'{chave}: espera não foi respeitada ({loc})')

    # Corrida: 8 cliques ao mesmo tempo depois do intervalo contam uma sessão só (e um bônus só).
    liberar(nomej)
    v0 = int(u(nomej, CAMPO[chave])[CAMPO[chave]])
    ths = [threading.Thread(target=pedir, args=(ck, rota)) for _ in range(8)]
    [t.start() for t in ths]; [t.join() for t in ths]
    checar(entrada(nomej, nome)[1] == '2', f'{chave}: corrida contou {entrada(nomej, nome)[1]} (esperado 2)')
    v1 = int(u(nomej, CAMPO[chave])[CAMPO[chave]])
    esperado2 = (min(bs, bf - min(bs, bf)) if chave == 'elemento' else bs) if chave != 'jutsuraro' else 0
    checar(v1 - v0 == esperado2, f'{chave}: bônus na corrida {v0} -> {v1}')

    if chave == 'jutsuraro':
        checar(jutsu not in u(nomej, 'spells')['spells'].split(','), f'{chave}: jutsu liberado antes de concluir')

    # Até concluir.
    for _ in range(sessoes - 2):
        liberar(nomej); _, _, loc = pedir(ck, rota)
    e = entrada(nomej, nome)
    checar(e[1] == str(sessoes) and 'Parabéns' in loc, f'{chave}: conclusão {e} {loc}')
    liberar(nomej); _, _, loc = pedir(ck, rota)
    checar('já completou' in loc, f'{chave}: clique depois de concluído ({loc})')

    f = u(nomej, 'maxhp,maxmp,maxtp,maxep,maxnp,defensepower,treino_hp,treino_mp,treino_tp,treino_ep,treino_np,treino_defesa,treino_chakra,treino_tpregen,treino_sjlento,spells')
    print('  final:', {k: v for k, v in f.items() if k.startswith('treino_') and v != '0'})
    if chave == 'elemento':
        checar(int(f['treino_ep']) == min(bf, bs * sessoes) and int(f['maxep']) == 5 + int(f['treino_ep']), f'{chave}: EP final {f}')
    if chave == 'chakra':
        checar(int(f['treino_mp']) == bs * sessoes and int(f['treino_chakra']) == bf, f'{chave}: final {f}')
        custo = phpexpr(nomej, 'custochakra(100)')
        checar(custo == str(100 - bf), f'{chave}: custochakra(100) = {custo}, esperado {100 - bf}')
    if chave == 'ferro':
        checar(int(f['treino_hp']) == bs * sessoes and int(f['maxhp']) == 200 + bs * sessoes, f'{chave}: HP final {f}')
        checar(int(f['treino_defesa']) == bf + bx and int(f['defensepower']) == 10 + bf + bx, f'{chave}: defesa Taijutsu {f}')
    if chave == 'caminhada':
        checar(int(f['treino_tp']) == bs * sessoes and int(f['treino_tpregen']) == bf, f'{chave}: final {f}')
        jogador(nomej, currenttp=0, ultimodescanso=int(time.time()) - 5)
        tok = subprocess.check_output(['php', f'{TESTES}/csrf.php', PASTA, nomej], text=True).strip()
        pedir(ck, f'encherhp.php?csrf={tok}')
        tp = int(u(nomej, 'currenttp')['currenttp'])
        base_tp = int(sql("SELECT tpdescanso FROM naruto_control WHERE id=1") or 1)
        checar(tp == base_tp + bf, f'{chave}: TP no descanso {tp}, esperado {base_tp + bf}')
    if chave == 'meditacao':
        checar(int(f['treino_np']) == bs * sessoes and int(f['treino_sjlento']) == bf, f'{chave}: final {f}')
        jogador(nomej, senjutsuhtml='senjutsu', currentnp=500, senjutsutimer=time.strftime('%-d/%-m/%Y,%H:%M:%S', time.localtime(time.time() - 60)) + ',1')
        cob = phpexpr(nomej, 'senjutsuestado($userrow)["cobrado"]')
        checar(int(cob) == 60 // (3 + bf), f'{chave}: Senjutsu cobrou {cob} NP em 60s, esperado {60 // (3 + bf)}')
        jogador(nomej, senjutsuhtml='fechado')
    if chave == 'jutsuraro':
        checar(jutsu in f['spells'].split(','), f'{chave}: jutsu {jutsu} não liberado ({f["spells"]})')

    # Painel de treinos (dadosdotreino) nos dois idiomas.
    _, corpo, _ = pedir(ck, 'treinamentoequests.php?do=treinamento')
    checar(nome in corpo and 'Treinamento Concluído' in corpo and ('Obtido até agora' in corpo or chave == 'jutsuraro'), f'{chave}: painel pt')
    _, corpo, _ = pedir(ck, 'treinamentoequests.php?do=treinamento', 'en')
    checar(EN[chave] in corpo and 'Training Completed' in corpo and ('Earned so far' in corpo or chave == 'jutsuraro') and 'Sessões' not in corpo, f'{chave}: painel en')

# Ajuda e admin nos dois idiomas.
ck = jogador('tt_ajuda')
for idi, txt in (('pt', 'Treinos Longos'), ('en', 'Long Trainings')):
    _, corpo, _ = pedir(ck, 'help.php', idi)
    checar(txt in corpo and ('Corpo de Ferro' in corpo if idi == 'pt' else 'Iron Body' in corpo), f'ajuda {idi}')
    _, corpo, _ = pedir(ck, 'help_spells.php', idi)
    checar(('Lança do Relâmpago Negro' if idi == 'pt' else 'Black Lightning Spear') in corpo, f'ajuda de jutsus {idi}')

print(f'\n{oks} verificações ok, {len(falhas)} falha(s)')
for f in falhas: print(' -', f[:400])
sys.exit(1 if falhas else 0)
