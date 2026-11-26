#!/usr/bin/env python3
"""Busca uma página do jogo como um jogador logado e mostra erros PHP encontrados.
Uso: fetch.py BASE_URL COOKIE path [path ...]   (path pode ser 'POST:url|campo=v&campo2=v')
"""
import sys, re, urllib.request, urllib.parse, http.cookiejar
base, cookie = sys.argv[1], sys.argv[2]
ERR = re.compile(r'<b>(Warning|Fatal error|Deprecated|Notice|Parse error)</b>:\s*(.*?)<br', re.S)
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
opener = urllib.request.build_opener(NoRedirect)
for p in sys.argv[3:]:
    data = None
    if p.startswith('POST:'):
        p, body = p[5:].split('|', 1); data = body.encode()
    req = urllib.request.Request(base.rstrip('/') + '/' + p, data=data)
    if cookie != '-': req.add_header('Cookie', 'narutorpg=' + urllib.parse.quote(cookie))
    try:
        r = opener.open(req); code = r.status; html = r.read().decode('utf8', 'replace'); loc = ''
    except urllib.error.HTTPError as e:
        code = e.code; html = e.read().decode('utf8', 'replace'); loc = e.headers.get('Location', '')
    errs = ERR.findall(html)
    fatal = [e for e in errs if e[0] in ('Fatal error', 'Parse error')]
    print(f"[{code}] {p} {('-> ' + loc) if loc else ''} len={len(html)} warnings={len(errs)-len(fatal)} FATAL={len(fatal)}")
    for t, m in errs[:int(__import__('os').environ.get('SHOWN', '6'))]:
        print('    ', t, re.sub(r'<.*?>', '', m).strip()[:220])
    if 'Erro SQL' in html: print('    SQL:', re.sub(r'<.*?>',' ',html[html.find('Erro SQL'):][:400]))
