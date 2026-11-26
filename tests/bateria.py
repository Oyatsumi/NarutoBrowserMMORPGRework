#!/usr/bin/env python3
"""Bateria de testes do Naruto RPG.

1. Cenários: cria jogadores de teste em estados diferentes (novo, explorando, em luta, nível alto, admin,
   com itens na mochila, fora do mapa...) direto no banco.
2. Rastreador: para cada jogador abre a página inicial e segue todos os links internos (GET) uma vez por rota,
   procurando erros do PHP (Warning/Fatal/Deprecated), erros de SQL, páginas em branco e respostas 500.
3. Fluxos POST: login, cadastro, pousada, compra, movimento, luta até o fim, depósito no banco, mensagem, fórum...
4. Entradas maliciosas: aspas, negativos, textos em campos numéricos, IDs de outros jogadores.

Uso: python3 bateria.py http://127.0.0.1:8080 /caminho/do/jogo [pt|en]
Requer config.php com "debug" => true para os erros aparecerem no HTML.
"""
import sys, re, subprocess, urllib.request, urllib.parse, collections, html

BASE, PASTA = sys.argv[1].rstrip('/'), sys.argv[2]
IDIOMA = sys.argv[3] if len(sys.argv) > 3 else 'pt'  # 3º argumento opcional: pt ou en
TESTES = __file__.rsplit('/', 1)[0]
ERRO = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice|Parse error)</b>:\s*(.*?)<br', re.S)
falhas = []; total = 0

class SemRedirecionar(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
abridor = urllib.request.build_opener(SemRedirecionar)

def jogador(nome, **campos):
    args = ['php', f'{TESTES}/mkuser.php', PASTA, nome] + [f'{k}={v}' for k, v in campos.items()]
    return subprocess.check_output(args, text=True).strip()

def csrf(nome):
    return subprocess.check_output(['php', f'{TESTES}/csrf.php', PASTA, nome], text=True).strip()

def pedir(cookie, caminho, dados=None, rotulo=''):
    global total; total += 1
    req = urllib.request.Request(BASE + '/' + urllib.parse.quote(caminho.lstrip('/'), safe=":/?=&%,;'()"), data=urllib.parse.urlencode(dados).encode() if dados is not None else None)
    extra = ('narutorpg_painel=' + PAINEL[cookie] + '; ') if cookie in PAINEL else ''
    req.add_header('Cookie', ('narutorpg=' + urllib.parse.quote(cookie) + '; ' if cookie else '') + extra + 'idioma=' + IDIOMA)
    try:
        r = abridor.open(req, timeout=60); cod, corpo, loc = r.status, r.read().decode('utf8', 'replace'), ''
    except urllib.error.HTTPError as e:
        cod, corpo, loc = e.code, e.read().decode('utf8', 'replace'), e.headers.get('Location', '')
    problemas = [f"{t}: {re.sub('<.*?>', '', m).strip()[:200]}" for t, m in ERRO.findall(corpo)]
    if 'Erro SQL' in corpo or 'Ocorreu um erro ao acessar o banco' in corpo: problemas.append('SQL: ' + re.sub('<.*?>', ' ', corpo[corpo.find('Erro'):][:300]))
    if cod >= 500: problemas.append(f'HTTP {cod}')
    if cod == 200 and len(corpo.strip()) == 0: problemas.append('página em branco')
    if problemas: falhas.append((rotulo or caminho, problemas))
    return cod, corpo, loc

PAINEL = {}  # cookie de login -> cookie do painel (admin re-autenticado com a senha, painelseguranca.php)
def entrarpainel(nome, cookie, senha='senha123'):
    """Faz a re-autenticação do painel de verdade (POST senhapainel) e guarda o cookie narutorpg_painel."""
    req = urllib.request.Request(BASE + '/admin.php', data=urllib.parse.urlencode({'senhapainel': senha, 'csrf': csrf(nome)}).encode())
    req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(cookie) + '; idioma=' + IDIOMA)
    class SemRedir(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *a, **k): return None
    try: r = urllib.request.build_opener(SemRedir).open(req, timeout=60); hdrs = r.headers
    except urllib.error.HTTPError as e: hdrs = e.headers
    for sc in hdrs.get_all('Set-Cookie') or []:
        m = re.match(r'narutorpg_painel=([^;]+)', sc)
        if m and m.group(1): PAINEL[cookie] = m.group(1); return True
    return False

def rota(href):
    p = urllib.parse.urlparse(href); q = urllib.parse.parse_qs(p.query)
    do = q.get('do', q.get('do2', ['']))[0].split(':')[0]
    return p.path.lstrip('./') + '?' + do

PROIBIDOS = ('logout', 'apagar', 'deletar', 'acao=limpar', 'javascript:', 'http', 'mailto:', '#')
def rastrear(nome, cookie, limite=160):
    vistos, fila, n = set(), ['index.php'], 0
    while fila and n < limite:
        url = fila.pop(0)
        chave = rota(url)
        if chave in vistos: continue
        vistos.add(chave); n += 1
        cod, corpo, loc = pedir(cookie, url, rotulo=f'[{nome}] {url}')
        if loc and not loc.startswith('http') and rota(loc) not in vistos: fila.append(loc.lstrip('./'))
        for href in re.findall(r'href=["\']([^"\']+)["\']', corpo):
            href = html.unescape(href)
            if any(x in href for x in PROIBIDOS) or not re.match(r'^\.?/?[a-z_]+\.php', href): continue
            fila.append(href.lstrip('./'))
    return n

def secao(t): print(f'\n=== {t}')

# ---------------------------------------------------------------------------
secao('Páginas públicas (sem login)')
for p in ['login.php?do=login', 'users.php?do=register', 'users.php?do=lostpassword', 'users.php?do=changepassword',
          'users.php?do=verify', 'ativarconta.php', 'help.php', 'help_items.php', 'help_monsters.php', 'help_spells.php',
          'help_levels.php', 'rank.php', 'mostrarchar.php?nomechar=bt_novo', 'creditos.php', 'outros.php?do=avatar', 'index.php', 'admin.php',
          'fight.php', 'explore.php', 'towns.php', 'funcoesinclusas.php']:
    pedir('', p, rotulo='[anônimo] ' + p)
print('ok')

secao('Rastreamento por estado de jogador')
cenarios = {
    'bt_novo': {},
    'bt_explorando': dict(currentaction='Exploring', latitude=12, longitude=-7),
    'bt_luta': dict(currentaction='Fighting', currentfight=1, latitude=40, longitude=40, level=8),
    'bt_forte': dict(level=60, gold=500000, experience=900000, maxhp=900, currenthp=900, maxmp=500, currentmp=500, maxtp=400, currenttp=400,
                     spells='0,1,2,6,7,11,14,17', towns='0,1,2,3,4,5,6,7,8,9,10', strength=200, dexterity=200, attackpower=300, defensepower=300,
                     maxnp=50, currentnp=50, maxep=20, currentep=20, missao='12,0,5,60', graduacao='Chuunin', alinhamento='nevoa'),
    'bt_itens': dict(bp1='Kunai,3,1,X', bp2='Pedra Sannin,20,4,X', bp3='Poção de Vida,hp,20,3', weaponid=4, weaponname='Shuriken',
                     slot1id=5, slot1name='Fragmento Médio de Chakra', durabilidade='-,3,X,X,2,X,X,X', bancogeral='Kunai Invertida,5,1,X;'),
    'bt_borda': dict(currentaction='Exploring', latitude=300, longitude=-300, level=50),
    'bt_admin': dict(authlevel=1, level=20, gold=10000),
}
cookies = {}
for nome, campos in cenarios.items():
    cookies[nome] = jogador(nome, **{k: str(v) for k, v in campos.items()})
    n = rastrear(nome, cookies[nome])
    print(f'{nome}: {n} rotas visitadas')

secao('Painel do administrador (todas as telas)')
ca = cookies['bt_admin']
# Sem re-autenticação o painel mostra só a tela de senha; senha errada não abre.
cod, corpo, loc = pedir(ca, 'admin.php?do=main', rotulo='[admin sem senha do painel]')
if 'senhapainel' not in corpo or 'name="gamename"' in corpo: falhas.append(('admin re-auth', ['painel abriu sem pedir a senha de novo']))
if entrarpainel('bt_admin', ca, 'errada!!'): falhas.append(('admin re-auth', ['senha errada abriu o painel']))
subprocess.run(['mysql', '-uroot', 'narutorpg', '-e', "DELETE FROM naruto_loginfail WHERE ip='127.0.0.1'"])
if not entrarpainel('bt_admin', ca): falhas.append(('admin re-auth', ['senha certa não abriu o painel']))
for p in ['admin.php', 'admin_avatares.php', 'admin.php?do=main', 'admin.php?do=news', 'admin.php?do=mensagemglobal', 'admin.php?do=users',
          'admin.php?do=users&filtro=online', 'admin.php?do=moderacao', 'admin.php?do=manutencao', 'admin.php?do=monsters',
          'admin.php?do=editmonster:1', 'admin.php?do=editmonster:0', 'admin.php?do=items', 'admin.php?do=edititem:1',
          'admin.php?do=drops', 'admin.php?do=editdrop:1', 'admin.php?do=spells', 'admin.php?do=editspell:1',
          'admin.php?do=towns', 'admin.php?do=edittown:1', 'admin.php?do=edittown:0', 'admin.php?do=levels', 'admin.php?do=receitas', 'admin.php?do=editreceita:1', 'admin.php?do=editreceita:46']:
    pedir(ca, p, rotulo='[admin] ' + p)
cod, corpo, loc = pedir(cookies['bt_novo'], 'admin.php', rotulo='[jogador comum] admin.php')
if 'Painel do Administrador' in corpo and 'Resumo' in corpo: falhas.append(('admin.php', ['jogador comum acessou o painel!']))
cod, corpo, loc = pedir(ca, 'admin.php?do=main', {'submit': '1', 'gamename': 'X'}, '[admin] POST sem token')
if 'Token de segurança' not in corpo and 'security token' not in corpo.lower(): falhas.append(('admin POST', ['aceitou formulário sem token CSRF']))
print('ok')

secao('Fluxos (POST) e entradas maliciosas')
c = jogador('bt_fluxo', gold='1000', level='10', currentaction='In Town', latitude='0', longitude='0', currenthp='5')
pedir(c, 'index.php?do=inn', {'submit': '1'}, 'pousada')
pedir(c, 'index.php?do=buy2:3', None, 'comprar (confirmação)')
pedir(c, 'index.php?do=buy3:3', {'submit': '1'}, 'comprar kunai')
pedir(c, 'index.php?do=buy3:9999', {'submit': '1'}, 'comprar item inexistente')
pedir(c, "index.php?do=buy3:1' OR '1'='1", {'submit': '1'}, 'comprar com SQL')
for direcao in ('north', 'east', 'south', 'west', 'north'):
    pedir(c, 'index.php?do=move', {direcao: '1'}, 'andar ' + direcao)
pedir(c, 'index.php?do=fight', None, 'luta (se houver)')
jogador('bt_fluxo', currentaction='Fighting', currentfight='1', currenthp='50', maxhp='50')
for i in range(30):
    cod, corpo, loc = pedir(c, 'index.php?do=fight', {'fight': '1'}, f'atacar #{i}')
    if 'victory' in loc or 'Vitória' in corpo or 'morreu' in corpo: break
pedir(c, 'index.php?do=victory', None, 'vitória')
jogador('bt_fluxo', currentaction='In Town', latitude='0', longitude='0', gold='1000', banco_grana='0')
for v in ('100', '-50', 'abc', '99999999', "1' OR 1=1 -- "):
    pedir(c, 'funcaoitens.php?do=banco', {'deposito': v, 'submit': '1'}, f'depositar {v!r}')
    pedir(c, 'funcaoitens.php?do=banco', {'retirar': v, 'submit': '1'}, f'retirar {v!r}')
cod, corpo, _ = pedir(c, 'index.php', None, 'conferir ouro')
pedir(c, 'users.php?do=doardinheiro', {'jogador': 'bt_novo', 'quantia': '-100', 'submit': '1'}, 'doar negativo')
pedir(c, 'users.php?do=doardinheiro', {'jogador': 'bt_novo', 'quantia': '10', 'usuariologadonome': 'bt_forte', 'submit': '1'}, 'doar com campo forjado')
pedir(c, 'mainmsg.php?do2=enviarpm', {'nomedaprocura': 'bt_novo', 'txtmensagem': '<script>alert(1)</script> olá', 'submit': '1'}, 'MP com script')
pedir(cookies['bt_novo'], 'pm.php?do=ler', None, 'ler MP')
pedir(c, 'forum.php?do=new', {'title': "Tópico 'teste'", 'content': 'Conteúdo <b>x</b>', 'categoria': 'Geral', 'submit': '1'}, 'fórum novo tópico')
pedir(c, 'forum.php', None, 'fórum lista')
pedir(c, 'chat.php?do=chatmap', {'fala': "fala com 'aspas'", 'submit': '1'}, 'chat do mapa')
pedir(c, 'index.php?do=babblebox', {'babble': 'oi geral'}, 'chat global')
pedir(c, 'outroseatributos.php?do=atributos', {'agilidade': '-5', 'submit': '1'}, 'atributo negativo')
pedir(c, 'help_items.php?ord=sleep(3)', None, 'ordenação maliciosa')
pedir(c, "index.php?do=showmap&monstro=Lobo' AND sleep(3)-- ", None, 'mapa com SQL')
pedir(c, 'index.php?do=onlinechar:abc', None, 'onlinechar texto')
pedir(c, "mostrarchar.php?nomechar=x'%20OR%20'1'='1", None, 'perfil com SQL')
print('ok')

secao('Resultado')
print(f'{total} requisições, {len(falhas)} com problema')
for rot, probs in falhas:
    print(' -', rot)
    for p in probs[:4]: print('     ', p)
sys.exit(1 if falhas else 0)
