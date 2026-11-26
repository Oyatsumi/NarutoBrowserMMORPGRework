#!/usr/bin/env python3
"""Generate English versions of the game's UI images that have Portuguese text baked in.

Reads the original images and writes <folder>/en/<same file name>, which
lib.php's localizarimagens() picks up when the language is English.
Only the text area is repainted; the rest of each image is left untouched.
Re-runnable: always starts from the originals.
"""
import os
import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

GAME = 'game'
TG = '/usr/share/texmf/fonts/opentype/public/tex-gyre/'
F_SERIF = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'Cinzel-700.ttf')  # Cinzel (OFL): serifas abertas como as letras originais dos menus
F_SERIF_REG = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'Cinzel-600.ttf')
F_COND = TG + 'texgyreheroscn-bold.otf'
F_SANS = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
F_SANS_BOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSansCondensed-Bold.ttf'

_fcache = {}


def font(path, size):
    k = (path, size)
    if k not in _fcache:
        _fcache[k] = ImageFont.truetype(path, size)
    return _fcache[k]


# ---------------------------------------------------------------- I/O helpers
def load(rel):
    im = Image.open(os.path.join(GAME, rel))
    return im, im.convert('RGBA')


def save(rel, orig, rgba):
    out = os.path.join(GAME, os.path.dirname(rel), 'en', os.path.basename(rel))
    os.makedirs(os.path.dirname(out), exist_ok=True)
    ext = rel.lower().rsplit('.', 1)[1]
    if ext == 'gif':
        pal = Image.new('P', (1, 1))
        pal.putpalette(orig.getpalette())
        q = rgba.convert('RGB').quantize(palette=pal, dither=Image.Dither.NONE)
        # keep original indices wherever the pixel was not modified
        o = np.asarray(orig.convert('RGB'))
        n = np.asarray(rgba.convert('RGB'))
        qa = np.asarray(q).copy()
        same = (o == n).all(2)
        qa[same] = np.asarray(orig)[same]
        q = Image.fromarray(qa, 'P')
        q.putpalette(orig.getpalette())
        kw = {}
        if 'transparency' in orig.info:
            kw['transparency'] = orig.info['transparency']
        q.save(out, **kw)
    elif ext == 'png':
        rgba.convert(orig.mode if orig.mode in ('RGB', 'RGBA') else 'RGBA').save(out, optimize=True)
    else:
        rgba.convert('RGB').save(out, quality=95, subsampling=0)
    return out


# ---------------------------------------------------------------- erasing
def erase_cols(img, box, src, seed=0):
    """Replace box (x0,y0,x1,y1) with columns taken from x-range src=(a,b) (same rows)."""
    x0, y0, x1, y1 = box
    a, b = src
    arr = np.asarray(img).copy()
    cols = list(range(a, b))
    seq = cols + cols[::-1][1:-1] if len(cols) > 2 else cols
    rng = np.random.default_rng(seed)
    off = int(rng.integers(0, len(seq)))
    for i, x in enumerate(range(x0, x1)):
        arr[y0:y1, x] = arr[y0:y1, seq[(i + off) % len(seq)]]
    return Image.fromarray(arr, img.mode)


def erase_interp(img, box, noise=4, seed=0, blur_rows=True):
    """Fill box by linear interpolation of each row between the left/right edge pixels."""
    x0, y0, x1, y1 = box
    arr = np.asarray(img).astype(float).copy()
    rng = np.random.default_rng(seed)
    L = arr[y0:y1, x0 - 2:x0].mean(1)
    R = arr[y0:y1, x1:x1 + 2].mean(1)
    w = x1 - x0
    t = (np.arange(w) + 0.5) / w
    fill = L[:, None, :] * (1 - t)[None, :, None] + R[:, None, :] * t[None, :, None]
    if noise:
        n = rng.normal(0, noise, fill.shape[:2])[..., None]
        fill[..., :3] += n
    arr[y0:y1, x0:x1] = fill
    return Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8), img.mode)


def erase_vinterp(img, box, noise=3, seed=0):
    """Fill box by vertical interpolation between the rows above/below."""
    x0, y0, x1, y1 = box
    arr = np.asarray(img).astype(float).copy()
    rng = np.random.default_rng(seed)
    T = arr[y0 - 1, x0:x1]
    B = arr[y1, x0:x1]
    h = y1 - y0
    t = (np.arange(h) + 0.5) / h
    fill = T[None] * (1 - t)[:, None, None] + B[None] * t[:, None, None]
    if noise:
        fill[..., :3] += rng.normal(0, noise, fill.shape[:2])[..., None]
    arr[y0:y1, x0:x1] = fill
    return Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8), img.mode)


def erase_fill(img, box, color, mask_fn=None):
    arr = np.asarray(img).copy()
    x0, y0, x1, y1 = box
    sub = arr[y0:y1, x0:x1]
    sel = np.ones(sub.shape[:2], bool) if mask_fn is None else mask_fn(sub)
    sub[sel, :3] = color
    arr[y0:y1, x0:x1] = sub
    return Image.fromarray(arr, img.mode)


def text_bbox(img, pred, area=None):
    arr = np.asarray(img.convert('RGB')).astype(int)
    x0, y0, x1, y1 = area or (0, 0, img.width, img.height)
    m = pred(arr[y0:y1, x0:x1])
    ys, xs = np.nonzero(m)
    return (x0 + xs.min(), y0 + ys.min(), x0 + xs.max() + 1, y0 + ys.max() + 1)


# ---------------------------------------------------------------- text drawing
def smallcaps_runs(text, big, small, path):
    """Split text into (string, font) runs: capitals at size big, rest uppercased at size small."""
    runs = []
    for ch in text:
        if ch.isupper() or not ch.isalpha():
            f = font(path, big if (ch.isupper() or ch.isdigit() or ch in '?!') else small)
            c = ch
        else:
            f = font(path, small)
            c = ch.upper()
        if runs and runs[-1][1] is f:
            runs[-1][0] += c
        else:
            runs.append([c, f])
    return runs


def runs_width(runs, tracking=0):
    w = 0
    for s, f in runs:
        w += f.getlength(s) + tracking * len(s)
    return w


def render_runs(runs, baseline_pad, tracking=0, size=None):
    """Render runs onto an 'L' mask; returns (mask, baseline_y)."""
    w = int(runs_width(runs, tracking)) + 8
    asc = max(f.getmetrics()[0] for _, f in runs)
    desc = max(f.getmetrics()[1] for _, f in runs)
    h = asc + desc + 8
    m = Image.new('L', (w, h), 0)
    d = ImageDraw.Draw(m)
    x = 4
    for s, f in runs:
        for ch in s:
            d.text((x, 4 + asc), ch, font=f, fill=255, anchor='ls')
            x += f.getlength(ch) + tracking
    return m, 4 + asc


def tight(mask):
    bb = mask.getbbox()
    return bb


def compose(img, mask, pos, color, shadow=None, outline=None, glow=None):
    """Paste mask (L) at pos with color; optional shadow=(dx,dy,blur,rgba), outline=(rgba,width), glow=(blur,rgba)."""
    base = img.convert('RGBA')
    layer = Image.new('RGBA', base.size, (0, 0, 0, 0))
    full = Image.new('L', base.size, 0)
    full.paste(mask, pos)

    def add(m, rgba):
        nonlocal layer
        c = Image.new('RGBA', base.size, rgba[:3] + (0,))
        a = m.point(lambda v: v * rgba[3] // 255)
        c.putalpha(a)
        layer = Image.alpha_composite(layer, c)

    if glow:
        b, rgba = glow
        g = full.filter(ImageFilter.GaussianBlur(b))
        g = g.point(lambda v: min(255, v * 2))
        add(g, rgba)
    if shadow:
        dx, dy, b, rgba = shadow
        s = Image.new('L', base.size, 0)
        s.paste(mask, (pos[0] + dx, pos[1] + dy))
        if b:
            s = s.filter(ImageFilter.GaussianBlur(b))
        add(s, rgba)
    if outline:
        rgba, wdt = outline
        o = full.filter(ImageFilter.MaxFilter(2 * wdt + 1))
        add(o, rgba)
    add(full, tuple(color) + (255,))
    out = Image.alpha_composite(base, layer)
    if img.mode == 'RGBA':
        # keep original alpha
        out.putalpha(img.getchannel('A'))
    return out.convert(img.mode)


def place(mask, align, box, baseline, mbase):
    """Compute paste position so that tight mask is aligned within box horizontally at given baseline."""
    bb = mask.getbbox()
    x0, x1 = box
    tw = bb[2] - bb[0]
    if align == 'center':
        x = (x0 + x1 - tw) / 2
    elif align == 'right':
        x = x1 - tw
    else:
        x = x0
    return (int(round(x - bb[0])), int(baseline - mbase))


def hsqueeze(mask, maxw):
    bb = mask.getbbox()
    tw = bb[2] - bb[0]
    if tw <= maxw:
        return mask
    c = mask.crop((bb[0], 0, bb[2], mask.height))
    c = c.resize((maxw, mask.height), Image.LANCZOS)
    m = Image.new('L', (maxw + bb[0] + 4, mask.height), 0)
    m.paste(c, (bb[0], 0))
    return m


def vstretch(mask, cap_target, cap_now, baseline):
    """Scale mask vertically around baseline so cap height becomes cap_target."""
    if cap_now == cap_target:
        return mask, baseline
    f = cap_target / cap_now
    nh = int(round(mask.height * f))
    m = mask.resize((mask.width, nh), Image.LANCZOS)
    return m, int(round(baseline * f))


# ---------------------------------------------------------------- 1. parchment strips
PARCH = {
    'ajuda_titulo.gif': 'Help',
    'alquimia.gif': 'Item Alchemy',
    'ativarconta.gif': 'Activate Account',
    'avatar.gif': 'Select Avatar',
    'banco.gif': 'Ryou Bank',
    'bancodeitens.gif': 'Item Bank',
    'comprarmapas.gif': 'Buy Maps',
    'contavip.gif': 'VIP Account',
    'criarconta.gif': 'Create Account',
    'descansar.gif': 'Rest',
    'distribuir.gif': 'Distribute Points',
    'doaritem.gif': 'Send Item',
    'doaryou.gif': 'Send Ryou',
    'drop.gif': 'Drop',
    'duelo.gif': 'Duel',
    'graduacao.gif': 'Graduation',
    'grupar.gif': 'Call the Group',
    'jutsudebusca.gif': 'Search Jutsu',
    'login.gif': 'Login',
    'minhaconta.gif': 'My Account',
    'missao.gif': 'Complete Missions',
    'noticias.gif': 'News',
    'online.gif': 'Who Is Online?',
    'opcoesjogador.gif': 'Player Options',
    'pm.gif': 'Private Messages',
    'procurar.gif': 'Find a Player',
    'rank.gif': 'Player Ranking',
    'fala.gif': 'Gathering Information',
    'title_exploring.gif': 'Exploring',
    'title_fighting.gif': 'Fighting',
    'title_forum.gif': 'In-Game Forum',
    'town_1.gif': 'Leaf Village',
    'town_2.gif': 'Mount Myoboku',
    'town_3.gif': 'Stone Village',
    'town_4.gif': 'Mist Village',
    'town_5.gif': 'Sand Village',
    'town_6.gif': 'Cloud Village',
    'town_7.gif': 'Heaven and Earth Hamlet',
    'town_8.gif': 'Akatsuki Hideout',
    'town_9.gif': 'Village of Wisdom',
    'town_10.gif': 'Royal Castle',
    'treinamento.gif': 'Training',
    'troca.gif': 'Trade',
    'ultimas.gif': 'Latest News',
    'viajar.gif': 'Travel',
}


def parchment(name, text):
    rel = 'images/' + name
    orig, img = load(rel)
    W, H = img.size
    arr = np.asarray(img.convert('RGB')).astype(int)
    lum = arr.mean(2)
    bg = np.median(lum)
    # text + shadow mask
    m = lum < bg - 18
    ys, xs = np.nonzero(m)
    x0, x1 = max(1, xs.min() - 3), min(W - 1, xs.max() + 4)
    # text colour = darkest pixels
    dark = lum < 110
    col = tuple(int(v) for v in np.median(arr[dark], 0)) if dark.any() else (69, 53, 38)
    if name == 'town_2.gif':
        col = (31, 29, 22)
    rows = [int(dark[y].sum()) for y in range(H)]
    base = 17 if H >= 25 else 15  # baseline of the original lettering
    # cap height of originals: 300x25 strips -> 13 px caps, 11 px small; 201x22 -> 12 / 9
    if H >= 25:
        cap, sc = 13, 10.2
    else:
        cap, sc = 11, 8.6
    # erase: copy text-free columns from the left (or right) margin
    if x0 > 25:
        src = (2, x0 - 2)
    else:
        src = (x1 + 2, W - 2)
    if src[1] - src[0] < 8:
        src = (1, 12)
    img = erase_cols(img, (x0, 0, x1, H), src, seed=len(name))
    # build text
    fpath = F_SERIF_REG
    f_big = font(fpath, 40)
    capnat = f_big.getbbox('H')[3] - f_big.getbbox('H')[1]
    big = round(40 * cap / capnat)
    small = round(40 * sc / capnat)
    runs = smallcaps_runs(text, big, small, fpath)
    mask, mb = render_runs(runs, 0, tracking=1.0)
    mask = hsqueeze(mask, W - 16)
    pos = place(mask, 'center', (0, W), base + 1, mb)
    img = compose(img, mask, pos, col,
                  glow=(1.2, (176, 140, 70, 140)),
                  shadow=(1, 1, 0.8, (120, 90, 40, 110)))
    return save(rel, orig, img)


# ---------------------------------------------------------------- 2. dark bars and misc images/
def dark_bar(name, text):
    rel = 'images/' + name
    orig, img = load(rel)
    W, H = img.size
    arr = np.asarray(img.convert('RGB')).astype(int)
    lum = arr.mean(2)
    sub = lum[4:18, 17:290]
    m = sub > 140
    ys, xs = np.nonzero(m)
    tx1 = 17 + xs.max() + 3
    img = erase_cols(img, (17, 4, tx1, 18), (292, 336), seed=3)
    runs = smallcaps_runs(text, 12, 9, F_SERIF)
    mask, mb = render_runs(runs, 0, tracking=0.9)
    # original: caps 10px tall, baseline y=15
    pos = place(mask, 'left', (19, W), 16, mb)
    img = compose(img, mask, pos, (226, 214, 190), shadow=(1, 1, 0, (20, 12, 5, 200)))
    return save(rel, orig, img)


def plain_label(rel, box, src, text, fpath, size, color, align, xr, baseline, method='cols'):
    orig, img = load(rel)
    if method == 'cols':  # noqa
        img = erase_cols(img, box, src)
    else:
        img = erase_interp(img, box, noise=2)
    f = font(fpath, size)
    m = Image.new('L', (int(f.getlength(text)) + 10, size * 2), 0)
    ImageDraw.Draw(m).text((4, size * 3 // 2), text, font=f, fill=255, anchor='ls')
    m = hsqueeze(m, xr[1] - xr[0])
    pos = place(m, align, xr, baseline, size * 3 // 2)
    img = compose(img, m, pos, color)
    return save(rel, orig, img)


def botoes(rel='images/botoes.jpg', tabs=None):
    orig, img = load(rel)
    # default: botoes.jpg; 'comandos' tab: dark grey box x 4..72, text x11..68 y12..25
    tabs = tabs or (((10, 10, 70, 27), (6, 72), 'commands'),
                    ((106, 14, 153, 31), (95, 162), 'towns'))
    for box, xr, word in tabs:
        img = erase_interp(img, box, noise=2)
        f = font(F_COND, 21)
        m = Image.new('L', (int(f.getlength(word)) + 10, 30), 0)
        ImageDraw.Draw(m).text((4, 22), word, font=f, fill=255, anchor='ls')
        m = hsqueeze(m, xr[1] - xr[0] - 6)
        pos = place(m, 'center', xr, box[3] - 2, 22)
        img = compose(img, m, pos, (205, 180, 150), shadow=(1, 1, 0, (60, 50, 40, 160)))
    return save(rel, orig, img)


def sorte(name):
    rel = 'images/' + name
    orig, img = load(rel)
    img = erase_cols(img, (34, 0, 175, 15), (178, 199), seed=1)
    # A linha marrom da caixa (y 15-18) fica inteira: as letras originais encostavam nela.
    img = img.copy(); px = img.load(); o = orig.convert("RGB")
    for x in range(34, 176):
        if sum(o.getpixel((x, 18))) < 300:
            for y in range(15, 19): px[x, y] = (79, 49, 23, 255)
    # Devolve a ponta da estrela (o apagador começa em x=34 e comia o contorno dela).
    for y in range(0, 19):
        for x in range(26, min(42, int(29 + 0.6 * y) + 1)):
            px[x, y] = o.getpixel((x, y)) + (255,)
    text = 'BATTLE LUCK'
    f = font(F_COND, 40)
    m, mb = render_runs([[text, f]], 0, tracking=7)
    bb = m.getbbox()
    m, mb = vstretch(m, 12, bb[3] - bb[1], mb)
    m = m.resize((int(m.width * 0.42), m.height), Image.LANCZOS)
    pos = place(m, 'left', (37, 200), 14, mb)  # 14: o texto não encosta na borda da caixa
    img = compose(img, m, pos, (75, 50, 25))
    return save(rel, orig, img)


def sasuke():
    rel = 'images/sasuke.jpg'
    orig, img = load(rel)
    img = erase_interp(img, (36, 96, 140, 115), noise=3)
    f = font(F_COND, 18)
    m, mb = render_runs([['LAST ENEMY', f]], 0, tracking=2.5)
    m = hsqueeze(m, 102)
    pos = place(m, 'center', (37, 139), 113, mb)
    img = compose(img, m, pos, (250, 250, 250), outline=((25, 20, 20, 255), 1))
    return save(rel, orig, img)


def sasuke2():
    rel = 'images/sasuke2.jpg'
    orig, img = load(rel)
    arr = np.asarray(img.convert('RGB')).astype(int)
    brown = tuple(int(v) for v in np.median(arr[90:100, 14:17], (0, 1)))
    # top header: light text on dark brown
    img = erase_fill(img, (25, 68, 152, 87), brown, lambda s: s[..., :3].astype(int).mean(2) > 0)
    f = font(F_COND, 18)
    m, mb = render_runs([['LAST ENEMIES', f]], 0, tracking=1.5)
    m = hsqueeze(m, 118)
    pos = place(m, 'center', (25, 151), 86, mb)
    img = compose(img, m, pos, (232, 214, 170))
    # HISTÓRICO: dark text on beige gradient
    img = erase_interp(img, (25, 155, 106, 175), noise=1)
    m, mb = render_runs([['HISTORY', font(F_COND, 24)]], 0, tracking=0.3)
    m = m.resize((int(m.width * 0.8), m.height), Image.LANCZOS)
    m = hsqueeze(m, 76)
    pos = place(m, 'center', (26, 104), 173, mb)
    img = compose(img, m, pos, (80, 45, 20))
    return save(rel, orig, img)


# ---------------------------------------------------------------- 3. layoutnovo/buttons
BIG = {  # file: (english, align)
    'historico.png': ('HISTORY', 'right'),
    'inventario.png': ('EQUIPMENT', 'left'),
    'local.png': ('COMMANDS', 'right'),
    'mochila.png': ('BACKPACK', 'left'),
    'personagem.png': ('CHARACTER', 'left'),
}


def map_button():
    """MAPA -> MAP reaproveitando as próprias letras do original (mesma fonte): tira o último A e alinha à direita."""
    rel = 'layoutnovo/buttons/coordenada.png'
    orig, img = load(rel)
    a = np.asarray(img).copy()
    src = a[0:26, 128:178].copy()
    a[0:26, 128:200] = 0
    reg = a[0:26, 146:196]
    m = src[..., 3] > 0
    reg[m] = src[m]
    return save(rel, orig, Image.fromarray(a, 'RGBA'))


def big_button(name, text, align):
    rel = 'layoutnovo/buttons/' + name
    orig, img = load(rel)
    W, H = img.size
    arr = np.asarray(img).astype(int)
    rgb = arr[..., :3]
    beige = (rgb.mean(2) > 110) & (arr[..., 3] > 0)
    ys, xs = np.nonzero(beige[3:25])
    x0, x1 = xs.min(), xs.max() + 1
    y0, y1 = 2, 26          # cap band of the original lettering (baseline at y=26)
    col = tuple(int(v) for v in np.median(rgb[3:25][beige[3:25] & (rgb[3:25].mean(2) > 160)], 0))
    transparent_bg = arr[0, 0, 3] == 0
    # erase: lettering pixels -> transparent (if the header has no black band) or splatter black
    a2 = arr.copy()
    box = a2[0:27, max(0, x0 - 2):min(W, x1 + 2)]
    lett = (box[..., :3].mean(2) > 18) & (box[..., 3] > 0)   # inclui a borda suavizada das letras
    lett = np.asarray(Image.fromarray((lett * 255).astype(np.uint8)).filter(ImageFilter.MaxFilter(5))) > 0
    if transparent_bg:
        box[lett, 3] = 0
    else:
        box[lett, :3] = (8, 6, 5)
    img = Image.fromarray(a2.astype(np.uint8), 'RGBA')
    # Fonte Anton (OFL, tools/Anton-Regular.ttf): condensada e pesada como as letras originais (estilo Impact).
    f = font(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'Anton-Regular.ttf'), 120)
    m, mb = render_runs([[text, f]], 0, tracking=1)
    bb = m.getbbox(); m = m.crop(bb)
    alt = y1 - y0
    m = m.resize((max(1, int(m.width * alt / m.height)), alt), Image.LANCZOS)
    maxw = (W - 4 - x0) if align == 'left' else (x1 - 4)
    m = hsqueeze(m, maxw)
    bb = m.getbbox()
    pos = place(m, align, (x0, x1), 0, 0)
    pos = (pos[0], y0 - bb[1])
    base = img.copy()
    full = Image.new('L', img.size, 0)
    full.paste(m, pos)
    txt = Image.new('RGBA', img.size, col + (0,))
    txt.putalpha(full)
    out = Image.alpha_composite(base, txt)
    return save(rel, orig, out)


SMALL = {  # file: (english, style)
    'acao.png': 'Action',
    'inventario2.png': 'Inventory',
    'personagem2.png': 'Character',
}
BAR = {
    'funcionais.png': 'Functions',
    'mensagens.png': 'Messages',
    'usarjutsu.png': 'Use Jutsus',
}


def small_center(name, text):
    rel = 'layoutnovo/buttons/' + name
    orig, img = load(rel)
    W, H = img.size
    arr = np.asarray(img.convert('RGB')).astype(int)
    if name == 'acao.png':
        # gold text on dark bar between two gold rules (y ~4 and ~22)
        lum = arr.mean(2)
        m = (lum[6:20] > 60)
        ys, xs = np.nonzero(m)
        x0, x1 = xs.min() - 2, xs.max() + 3
        col = tuple(int(v) for v in np.median(arr[6:20][lum[6:20] > 90], 0))
        img = erase_cols(img, (x0, 6, x1, 20), (8, x0 - 4))
        runs = smallcaps_runs(text, 16, 12, F_SERIF)
        mk, mb = render_runs(runs, 0, tracking=1.2)
        pos = place(mk, 'center', (0, W), 18, mb)
        img = compose(img, mk, pos, col)
    else:
        lum = arr.mean(2)
        bg = np.median(lum)
        m = lum[3:24] < bg - 25
        ys, xs = np.nonzero(m)
        x0, x1 = xs.min() - 2, xs.max() + 3
        dark = lum < bg - 70
        col = tuple(int(v) for v in np.median(arr[dark], 0))
        img = erase_cols(img, (x0, 3, x1, 24), (x1 + 4, W - 6))
        runs = smallcaps_runs(text, 16, 12, F_SERIF_REG)
        mk, mb = render_runs(runs, 0, tracking=1.4)
        pos = place(mk, 'center', (0, W), 19, mb)
        img = compose(img, mk, pos, col, glow=(1, (240, 220, 160, 90)))
    return save(rel, orig, img)


def bar_label(name, text):
    rel = 'layoutnovo/buttons/' + name
    orig, img = load(rel)
    W, H = img.size
    arr = np.asarray(img.convert('RGB')).astype(int)
    lum = arr.mean(2)
    m = lum[5:17, 17:120] > 140
    ys, xs = np.nonzero(m)
    x1 = 17 + xs.max() + 3
    img = erase_cols(img, (17, 5, x1, 18), (x1 + 15, W - 20), seed=2)
    runs = smallcaps_runs(text, 12, 9, F_SERIF)
    mk, mb = render_runs(runs, 0, tracking=0.9)
    pos = place(mk, 'left', (19, W), 16, mb)
    img = compose(img, mk, pos, (226, 214, 190), shadow=(1, 1, 0, (20, 12, 5, 200)))
    return save(rel, orig, img)


# ---------------------------------------------------------------- 4. menuslados plaques
PLAQUE = {
    'batalhando.png': 'Fighting',
    'descansando.png': 'Resting',
    'explorando.png': 'Exploring',
    'missao.png': 'On Mission',
    'nacidade.png': 'In Town',
    'treinando.png': 'Training',
}


def plaque(name, text):
    rel = 'layoutnovo/menuslados/' + name
    orig, img = load(rel)
    arr = np.asarray(img.convert('RGB')).astype(int)
    lum = arr.mean(2)
    reg = lum[115:137, 45:128]
    m = reg > 150
    ys, xs = np.nonzero(m)
    x0, x1 = 45 + xs.min() - 2, 45 + xs.max() + 3
    y0, y1 = 115 + ys.min() - 1, 115 + ys.max() + 2
    col = tuple(int(v) for v in np.median(reg[m.nonzero()[0], m.nonzero()[1]] if False else arr[115:137, 45:128][m], 0))
    img = erase_interp(img, (x0, y0, x1, y1), noise=6, seed=len(name))
    f = font(F_SANS_BOLD, 13)
    mk = Image.new('L', (int(f.getlength(text)) + 10, 26), 0)
    ImageDraw.Draw(mk).text((4, 18), text, font=f, fill=255, anchor='ls')
    mk = hsqueeze(mk, 82)
    pos = place(mk, 'center', (44, 127), y1 - 4, 18)
    img = compose(img, mk, pos, col, outline=((30, 20, 10, 230), 1))
    return save(rel, orig, img)


def kakashi():
    rel = 'layoutnovo/menuslados/kakashi.png'
    orig, img = load(rel)
    arr = np.asarray(img.convert('RGB')).astype(int)
    lum = arr.mean(2)
    reg = arr[156:174, 58:138]
    m = reg.mean(2) > 70
    col = tuple(int(v) for v in np.median(reg[m], 0))
    blk = tuple(int(v) for v in np.median(reg[reg.mean(2) < 25], 0))
    img = erase_fill(img, (58, 156, 138, 174), blk)
    runs = smallcaps_runs('OTHERS', 18, 18, F_SERIF_REG)
    mk, mb = render_runs(runs, 0, tracking=1.5)
    pos = place(mk, 'center', (58, 138), 171, mb)
    img = compose(img, mk, pos, col)
    return save(rel, orig, img)


# ---------------------------------------------------------------- 5. titulo/cima.jpg
def cima():
    rel = 'layoutnovo/titulo/cima.jpg'
    orig, img = load(rel)
    # top menu: zones = templates/primary.php offsets + content table offset in this image
    OFF = 104
    zones = [(120, 43, 'GAME'), (168, 110, 'NEW USER'), (280, 100, 'MY ACCOUNT'), (383, 45, 'LOG OUT')]
    arr = np.asarray(img.convert('RGB')).astype(int)
    col = tuple(int(v) for v in np.median(arr[24:37, 226:545][arr[24:37, 226:545].mean(2) > 175], 0))
    img = erase_interp(img, (223, 22, 550, 40), noise=7, seed=5)
    img = img.filter(ImageFilter.SMOOTH) if False else img
    masks = []
    for left, w, word in zones:
        f = font(F_COND, 40)
        mk, mb = render_runs([[word, f]], 0, tracking=0.4)
        bb = mk.getbbox()
        mk, mb = vstretch(mk, 13, bb[3] - bb[1], mb)
        bb = mk.getbbox()
        masks.append((mk, (bb[2] - bb[0]) / (w - 4)))
    # one common horizontal scale so all words look alike
    k = min(0.85, 1 / max(r for _, r in masks))
    for (left, w, word), (mk, _) in zip(zones, masks):
        mk = mk.resize((int(mk.width * k), mk.height), Image.LANCZOS)
        bb = mk.getbbox()
        xr = (OFF + left, OFF + left + w)
        pos = place(mk, 'center', xr, 0, 0)
        pos = (pos[0], 24 - bb[1])
        img = compose(img, mk, pos, col, shadow=(1, 1, 0, (25, 18, 12, 220)))
    # "Notícias:" -> "News:"
    img = erase_cols(img, (106, 189, 179, 209), (200, 300), seed=7)
    f = font(F_SANS, 18)
    mk = Image.new('L', (90, 34), 0)
    ImageDraw.Draw(mk).text((4, 24), 'News:', font=f, fill=255, anchor='ls')
    pos = place(mk, 'left', (108, 200), 207, 24)
    img = compose(img, mk, pos, (45, 45, 35))
    return save(rel, orig, img)


def main():
    out = []
    for n, t in PARCH.items():
        out.append(parchment(n, t))
    for n, t in (('vcbatalha.jpg', 'You are in a battle.'),
                 ('vcexpl.jpg', 'You are exploring the map.'),
                 ('vcvila.jpg', 'You are in a village.'),
                 ('outro.jpg', 'No action at the moment.')):
        out.append(dark_bar(n, t))
    out.append(plain_label('images/viajar.jpg', (3, 2, 45, 18), (48, 50), 'travel',
                           F_SANS, 15, (0, 0, 0), 'left', (4, 42), 15, method='interp'))
    out.append(plain_label('images/comandos.jpg', (22, 2, 98, 18), (98, 100), 'commands',
                           F_SANS, 15, (0, 0, 0), 'left', (23, 99), 15, method='interp'))
    out.append(botoes())
    out.append(botoes('images/naruto.jpg',
                      (((12, 209, 74, 226), (8, 78), 'commands'),
                       ((110, 212, 158, 230), (99, 168), 'towns'))))
    for s in ('ep', 'hp', 'mp', 'np', 'tp'):
        out.append(sorte('sorte_%s.jpg' % s))
    out.append(sasuke())
    out.append(sasuke2())
    out.append(map_button())
    for n, (t, a) in BIG.items():
        out.append(big_button(n, t, a))
    for n, t in SMALL.items():
        out.append(small_center(n, t))
    for n, t in BAR.items():
        out.append(bar_label(n, t))
    for n, t in PLAQUE.items():
        out.append(plaque(n, t))
    out.append(kakashi())
    out.append(cima())
    for o in out:
        print(o)


if __name__ == '__main__':
    main()
