#!/usr/bin/env python3
"""Procura texto em português nas páginas exibidas em inglês.

Abre as páginas do jogo (seguindo os links internos, uma vez por rota) com o cookie idioma=en,
tira scripts/estilos/tags e lista palavras típicas do português que sobraram, com a página onde aparecem.
Uso: python3 varrer_portugues.py http://127.0.0.1:8080 /caminho/do/jogo
"""
import sys, re, subprocess, urllib.request, urllib.parse, html, collections

BASE, PASTA = sys.argv[1].rstrip('/'), sys.argv[2]
TESTES = __file__.rsplit('/', 1)[0]
# Palavras que só existem em português (as acentuadas são pegas pela regex de acentos).
PALAVRAS = set("""você voce não nao para com uma seu sua seus suas pelo pela está esta são sao jogador jogadores
vila cidade inimigo inimigos missão missao treino nível nivel ataque defesa força forca sorte mochila
voltar enviar mensagem mensagens conta senha usuário usuario nome entrar sair sim ajuda dinheiro
agora ainda depois quando onde aqui também tambem mais muito seja pode deve isso esse essa este
dos das nos nas pelos pelas ao aos""".split())
ACENTO = re.compile(r"[ãõçáéíóúâêôàÃÕÇÁÉÍÓÚÂÊÔ]")
IGNORAR = {"Português"}  # rótulo do seletor de idioma

class SemRedirecionar(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
abridor = urllib.request.build_opener(SemRedirecionar)

def pegar(cookie, caminho):
    req = urllib.request.Request(BASE + '/' + urllib.parse.quote(caminho.lstrip('/'), safe=":/?=&%,;'()"))
    req.add_header('Cookie', ('narutorpg=' + urllib.parse.quote(cookie) + '; ' if cookie else '') + 'idioma=en')
    try:
        r = abridor.open(req, timeout=60); return r.read().decode('utf8', 'replace')
    except urllib.error.HTTPError as e:
        return e.read().decode('utf8', 'replace')

def texto(corpo):
    corpo = re.sub(r'(?is)<(script|style)\b.*?</\1>', ' ', corpo)
    titulos = ' '.join(re.findall(r'(?i)\b(?:title|alt|value)="([^"]*)"', corpo))
    return html.unescape(re.sub(r'<[^>]+>', ' ', corpo) + ' ' + titulos)

def suspeitas(t):
    achou = []
    for palavra in re.findall(r"[\wÀ-ÿ’']+", t):
        if palavra in IGNORAR: continue
        if ACENTO.search(palavra) or palavra.lower() in PALAVRAS: achou.append(palavra)
    return achou

def rota(link):
    p = urllib.parse.urlparse(html.unescape(link))
    q = urllib.parse.parse_qs(p.query)
    return p.path.lstrip('/') + '?' + (q.get('do', [''])[0].split(':')[0])

resultado = collections.defaultdict(set)
jogadores = [('', None)]
for nome, campos in [('vp_novo', {}), ('vp_forte', {'level': '40', 'gold': '50000'}), ('vp_admin', {'authlevel': '1'})]:
    cookie = subprocess.check_output(['php', f'{TESTES}/mkuser.php', PASTA, nome] + [f'{k}={v}' for k, v in campos.items()], text=True).strip()
    jogadores.append((cookie, nome))

for cookie, nome in jogadores:
    vistos, fila = set(), ['index.php', 'login.php?do=login', 'help.php', 'help_monsters.php', 'help_items.php', 'help_spells.php', 'help_levels.php', 'users.php?do=register', 'users.php?do=lostpassword', 'rank.php']
    if nome == 'vp_admin': fila.append('admin.php')
    while fila and len(vistos) < 120:
        c = fila.pop(0)
        if rota(c) in vistos or any(x in c for x in ('logout', 'apagar', 'delete', 'deletar', 'csrf=')): continue
        vistos.add(rota(c))
        corpo = pegar(cookie, c)
        for s in suspeitas(texto(corpo)): resultado[s].add(c.split('&')[0])
        for l in re.findall(r'href="([^"#]+)"', corpo):
            if l.startswith(('http', 'javascript', 'mailto')) or '.php' not in l: continue
            fila.append(html.unescape(l))

for palavra, paginas in sorted(resultado.items(), key=lambda x: -len(x[1])):
    print(f"{palavra:25s} {len(paginas):3d}  {', '.join(sorted(paginas)[:4])}")
print(f"\n{len(resultado)} palavras suspeitas")
