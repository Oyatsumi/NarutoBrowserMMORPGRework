"""Restore accented characters lost (U+FFFD) in the 2018 sources using the intact
latin1 2012 sources as reference, then write everything as UTF-8."""
import os, re, sys, glob
OLD, NEW, OUT = sys.argv[1], sys.argv[2], sys.argv[3]
def rd(p):
    b = open(p, 'rb').read()
    try: return b.decode('utf8'), 'utf8'
    except UnicodeDecodeError: return b.decode('latin1'), 'latin1'
report = []
for p in glob.glob(NEW + '/**/*', recursive=True):
    if not os.path.isfile(p) or not re.search(r'\.(php|css|js|txt|html?)$', p): continue
    rel = os.path.relpath(p, NEW)
    txt, enc = rd(p)
    unresolved = 0
    if '�' in txt:
        ref = os.path.join(OLD, rel)
        reftxt = rd(ref)[0] if os.path.exists(ref) else ''
        out = []; i = 0
        chars = list(txt)
        for idx, ch in enumerate(chars):
            if ch != '�': continue
            fixed = None
            for w in (24, 16, 10, 6, 4):
                a = max(0, idx - w); b = min(len(chars), idx + w + 1)
                ctx = ''.join(chars[a:b])
                pat = ''.join('.' if c == '�' else re.escape(c) for c in ctx)
                ms = list(re.finditer(pat, reftxt, re.S))
                cands = set(m.group(0)[idx - a] for m in ms)
                if len(cands) == 1:
                    fixed = cands.pop(); break
            if fixed: chars[idx] = fixed
            else: unresolved += 1
        txt = ''.join(chars)
    dst = os.path.join(OUT, rel)
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    open(dst, 'w', encoding='utf8', newline='').write(txt)
    report.append((rel, enc, unresolved))
for r in sorted(report):
    if r[1] != 'utf8' or r[2]: print(r)
