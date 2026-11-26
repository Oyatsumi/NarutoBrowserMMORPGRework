#!/usr/bin/env python3
"""Gera os ladrilhos do mini mapa (images/terreno/) a partir de images/terreno_NOME.jpg.

Para cada terreno: NOME_0..3.png (chão 30x30 sem objetos grandes, emendável), bordas NOME_b_{n,s,l,o}.png e cantos
NOME_c_{nl,no,sl,so}.png (o chão desse terreno com máscara irregular, para cobrir a casa vizinha de menor prioridade)
e decorações deco_NOME_K.png (24x24 transparentes, inteiras dentro da casa).
Detalhes sutis detalhe_NOME_K.png (ondas, vitórias-régias, pedrinhas...): python3 gerar_tiles_terreno.py PASTA_DO_JOGO --detalhes
Uso: python3 gerar_tiles_terreno.py PASTA_DO_JOGO [terreno,terreno]
Só Pillow + numpy. Determinístico (mesma saída sempre)."""
import sys, os
import numpy as np
from PIL import Image

JOGO = sys.argv[1] if len(sys.argv) > 1 else 'game'
OUT = os.path.join(JOGO, 'images', 'terreno'); os.makedirs(OUT, exist_ok=True)
C = 30          # casa do mini mapa: 24 px de ícone + 3 px de padding de cada lado
rng = np.random.default_rng(7)

# Cor-alvo do "chão limpo" de cada terreno (RGB) e quanto penalizar pixels escuros (objetos/sombras).
ALVO = {'grama': (98, 170, 40), 'floresta': (52, 78, 38), 'trilha': (138, 72, 40), 'deserto': (232, 186, 120),
        'rochoso': (88, 92, 98), 'neve': (228, 238, 250), 'costa': (60, 190, 200), 'pantano': (62, 52, 40),
        'vulcanico': (70, 56, 52)}

def tex(nome):
    return np.asarray(Image.open(os.path.join(JOGO, 'images', f'terreno_{nome}.jpg')).convert('RGB')).astype(float)

def emendavel(p):
    """Offset + mistura: borda passa a casar com a borda oposta (ladrilho contínuo)."""
    r = np.roll(np.roll(p, C // 2, 0), C // 2, 1)
    y, x = np.mgrid[0:C, 0:C]
    d = np.minimum(np.minimum(x, C - 1 - x), np.minimum(y, C - 1 - y)) / (C / 2)
    w = np.clip(d * 1.6, 0, 1)[..., None]
    return p * w + r * (1 - w)

def chaos(nome):
    t = tex(nome); H, W, _ = t.shape
    alvo = np.array(ALVO[nome])
    lum = t @ [0.3, 0.59, 0.11]
    cands = []
    for y in range(0, H - C, 3):
        for x in range(0, W - C, 3):
            p = t[y:y + C, x:x + C]; l = lum[y:y + C, x:x + C]
            dist = np.abs(p - alvo).sum(2)
            s = l.std() * 1.0 + np.percentile(dist, 95) * 0.6 + dist.mean() * 0.4
            cands.append((s, y, x))
    cands.sort(); esc = []
    for s, y, x in cands:
        if all(abs(y - a) >= C * 0.8 or abs(x - b) >= C * 0.8 for a, b in esc): esc.append((y, x))
        if len(esc) == 4: break
    tiles = []
    for k, (y, x) in enumerate(esc):
        p = t[y:y + C, x:x + C]
        p = np.rot90(p, k) if nome not in ('costa',) else (p if k % 2 == 0 else p[:, ::-1])
        tiles.append(np.clip(emendavel(p), 0, 255).astype(np.uint8))
    if nome == 'vulcanico':  # o chão limpo escolhido não tem lava: 2 das 4 variações ganham uma rachadura fina brilhando
        for k in (1, 3): tiles[k] = rachadura_lava(tiles[k], 40 + k)
    return tiles

def rachadura_lava(t, semente):
    r = np.random.default_rng(semente); t = t.copy().astype(float)
    y, x = r.uniform(8, 22), 2.0; ang = r.uniform(-0.4, 0.4)
    while 0 <= x < C - 1 and 0 <= y < C - 1:
        yi, xi = int(y), int(x)
        t[yi, xi] = (245, 130, 40)
        for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            if 0 <= yi + dy < C and 0 <= xi + dx < C: t[yi + dy, xi + dx] = t[yi + dy, xi + dx] * 0.55 + np.array((200, 70, 25)) * 0.45
        ang += r.normal(0, 0.5); x += np.cos(ang) * 0.8; y += np.sin(ang) * 0.8
    return np.clip(t, 0, 255).astype(np.uint8)

def ruido1d(n, semente, amp, base):
    r = np.random.default_rng(semente).random(n // 4 + 3)
    xs = np.arange(n) / 4; i = xs.astype(int); f = xs - i; f = f * f * (3 - 2 * f)
    return base + amp * (r[i] * (1 - f) + r[i + 1] * f)

BAYER = np.array([[0, 8, 2, 10], [12, 4, 14, 6], [3, 11, 1, 9], [15, 7, 13, 5]]) / 16

def mascara_borda(semente):
    """Alfa (C x C) cobrindo o lado de cima com profundidade irregular de 4 a 10 px e 2 px pontilhados."""
    prof = ruido1d(C, semente, 6, 4)
    y = np.arange(C)[:, None]
    a = np.clip((prof[None, :] + 1.0 - y) / 2.0, 0, 1)
    return a

def mascara_canto(semente):
    """Alfa cobrindo o canto superior esquerdo (quarto de círculo irregular, raio 6 a 10)."""
    y, x = np.mgrid[0:C, 0:C]
    ang = np.arctan2(y, x) / (np.pi / 2) * (C - 1)
    raio = ruido1d(C, semente, 4, 6)[np.clip(ang.astype(int), 0, C - 1)]
    return np.clip((raio + 1.0 - np.hypot(x, y)) / 2.0, 0, 1)

def pontilha(a):
    b = BAYER[np.arange(C)[:, None] % 4, np.arange(C)[None, :] % 4]
    return ((a > b) * 255).astype(np.uint8)

def salvar_rgba(rgb, alfa, nome):
    Image.fromarray(np.dstack([rgb, alfa])).save(os.path.join(OUT, nome), optimize=True)

# ---------------------------------------------------------------- decorações (pixel art desenhada por código)
def sprite(arr):
    return Image.fromarray(arr, 'RGBA')

def contorno(a, cor=(28, 24, 20)):
    """Contorno escuro de 1 px em volta das partes opacas + sombra elíptica no pé."""
    op = a[..., 3] > 0
    viz = np.zeros_like(op)
    for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1)): viz |= np.roll(np.roll(op, dy, 0), dx, 1)
    borda = viz & ~op
    a[borda] = (*cor, 255)
    return a

def sombra(a, cy, rx, ry):
    y, x = np.mgrid[0:24, 0:24]
    m = (((x - 12) / rx) ** 2 + ((y - cy) / ry) ** 2 <= 1) & (a[..., 3] == 0)
    a[m] = (0, 0, 0, 70)
    return a

def pinheiro(neve=False, alt=0):
    a = np.zeros((24, 24, 4), np.uint8)
    esc, med, cla = (24, 74, 46), (36, 104, 58), (66, 140, 72)
    if neve: cla = (232, 240, 250)
    a[19:22, 11:13] = (110, 70, 40, 255)
    topo = 2 + alt
    for k, (y0, larg) in enumerate(((topo, 3), (topo + 5, 5), (topo + 10, 7))):
        for dy in range(7):
            w = int(1 + (larg - 1) * dy / 6)
            for dx in range(-w, w + 1):
                yy, xx = y0 + dy, 12 + dx - (1 if dx >= 0 else 0) + (1 if dx >= 0 else 0)
                if yy > 20: continue
                cor = cla if (dx < -w / 3 and dy < 4) or (neve and dy <= 1) else (med if dx < w / 3 else esc)
                a[yy, xx] = (*cor, 255)
    return sombra(contorno(a), 21, 6, 2)

def pedra(base, neve=False, rx=7, ry=5, cy=14, seed=0):
    a = np.zeros((24, 24, 4), np.uint8); r = np.random.default_rng(seed)
    base = np.array(base, float)
    y, x = np.mgrid[0:24, 0:24]
    nx, ny = (x - 12) / rx, (y - cy) / ry
    dent = r.random(8) * 0.25
    ang = ((np.arctan2(ny, nx) + np.pi) / (2 * np.pi) * 8).astype(int) % 8
    m = nx ** 2 + ny ** 2 <= 1 - dent[ang]
    luz = np.clip(0.5 - nx * 0.35 - ny * 0.5, 0, 1)
    nivel = np.round(luz * 3) / 3
    cor = base[None, None] * (0.55 + 0.75 * nivel[..., None])
    if neve:
        topo = ny < -0.25 + 0.15 * np.sin(nx * 5)
        cor[topo] = (234, 242, 252)
    a[m] = np.dstack([np.clip(cor, 0, 255), np.full((24, 24), 255)])[m]
    # rachadura
    for k in range(3): a[cy + k - 1, 12 + k] = (*np.clip(base * 0.4, 0, 255).astype(int), 255) if m[cy + k - 1, 12 + k] else a[cy + k - 1, 12 + k]
    return sombra(contorno(a), cy + ry + 1, rx, 2)

def pedras_duas(base, neve, seed):
    a = pedra(base, neve, 5, 4, 15, seed)
    b = pedra(base, neve, 4, 3, 10, seed + 1)
    b = np.roll(b, (-0, 5), (0, 1)); a = np.roll(a, -3, 1)
    m = b[..., 3] > a[..., 3]; a[m] = b[m]
    return a

def cacto(var=0):
    a = np.zeros((24, 24, 4), np.uint8)
    esc, med, cla = (40, 110, 50), (70, 150, 60), (120, 190, 90)
    def bloco(y0, y1, x0, x1):
        for yy in range(y0, y1):
            for xx in range(x0, x1):
                a[yy, xx] = (*(cla if xx == x0 else (esc if xx == x1 - 1 else med)), 255)
    bloco(4, 21, 10, 14)
    if var == 0: bloco(9, 15, 5, 8); bloco(14, 16, 5, 11); bloco(7, 12, 16, 19); bloco(12, 14, 13, 19)
    else: bloco(11, 17, 15, 18); bloco(16, 18, 13, 18)
    a[4, 11:13] = (240, 120, 150, 255) if var == 0 else a[4, 11:13]
    return sombra(contorno(a), 21, 5, 2)

def arbusto_seco():
    a = np.zeros((24, 24, 4), np.uint8); r = np.random.default_rng(3)
    for k in range(9):
        ang = -np.pi / 2 + (k - 4) * 0.33
        for t in range(3, 9 + r.integers(0, 3)):
            yy, xx = int(19 + np.sin(ang) * t), int(12 + np.cos(ang) * t)
            a[yy, xx] = (150, 110, 60, 255) if t % 3 else (190, 150, 90, 255)
    return sombra(contorno(a, (70, 45, 25)), 20, 6, 2)

def juncos(var=0):
    a = np.zeros((24, 24, 4), np.uint8); r = np.random.default_rng(10 + var)
    xs = [7, 10, 12, 15, 17] if var == 0 else [8, 11, 14, 16]
    for k, x0 in enumerate(xs):
        h = 9 + r.integers(0, 7); inc = r.choice([-1, 0, 1])
        for t in range(h):
            yy = 20 - t; xx = x0 + (inc if t > h * 0.6 else 0)
            a[yy, xx] = (90, 140, 50, 255) if t % 4 else (130, 170, 70, 255)
        if k % 2 == 0:  # taboa
            a[20 - h:23 - h, x0 + inc] = (110, 66, 36, 255)
    return sombra(contorno(a, (30, 40, 20)), 21, 6, 2)

def recolor(caminho, fator, tom):
    im = np.asarray(Image.open(caminho).convert('RGBA')).astype(float)
    z = np.zeros((24, 24, 4)); h, w = im.shape[:2]; z[:h, (24 - w) // 2:(24 - w) // 2 + w] = im
    rgb = z[..., :3] * fator + np.array(tom); z[..., :3] = np.clip(rgb, 0, 255)
    return z.astype(np.uint8)

def respiradouro():
    """Fenda vulcânica com fumaça (decoração do terreno vulcanico)."""
    a = np.zeros((24, 24, 4), np.uint8)
    y, x = np.mgrid[0:24, 0:24]
    m = ((x - 12) / 7) ** 2 + ((y - 18) / 3) ** 2 <= 1
    a[m] = (60, 46, 42, 255)
    a[(((x - 12) / 4) ** 2 + ((y - 18) / 1.5) ** 2 <= 1)] = (240, 120, 36, 255)
    a[(((x - 12) / 2) ** 2 + ((y - 18) / 0.8) ** 2 <= 1)] = (255, 210, 90, 255)
    for k, (cy, cx, rr) in enumerate(((13, 12, 2.6), (9, 10, 3.0), (5, 13, 3.2))):
        f = (((x - cx) / rr) ** 2 + ((y - cy) / rr) ** 2 <= 1) & (a[..., 3] == 0)
        a[f] = (150, 140, 136, 200 - 40 * k)
    return a

def arvore_morta():
    a = np.zeros((24, 24, 4), np.uint8)
    tr = (58, 44, 38, 255)
    a[9:21, 11:13] = tr
    for (y0, x0, dx) in ((12, 11, -1), (10, 12, 1), (15, 12, 1)):
        for t in range(6):
            yy, xx = y0 - t // 2, x0 + dx * t
            if 0 <= xx < 24: a[yy, xx] = tr
    return sombra(contorno(a, (20, 14, 12)), 21, 5, 2)

def rocha_lava(seed):
    a = pedra((60, 48, 44), False, 6, 5, 14, seed)
    for (yy, xx) in ((12, 10), (13, 11), (14, 11), (15, 12), (13, 14), (14, 15)):
        if a[yy, xx, 3] > 0: a[yy, xx] = (245, 130, 40, 255)
    return a

ARV = os.path.join(JOGO, 'images', '24', 'arvore_random%d.gif')
ROCHA, AREIA = (110, 114, 122), (190, 150, 100)
DECO = {
    'grama': [recolor(ARV % k, 1, (0, 0, 0)) for k in (1, 2, 3, 4, 5)],
    'floresta': [recolor(ARV % k, 0.78, (0, 0, -6)) for k in (1, 2, 3)] + [pinheiro(False, 0), pinheiro(False, 2)],
    'pantano': [juncos(0), juncos(1), recolor(ARV % 4, 0.85, (0, 0, 0))],
    'rochoso': [pedra(ROCHA, False, 7, 5, 14, 1), pedras_duas(ROCHA, False, 4), pinheiro(False, 1)],
    'neve': [pinheiro(True, 0), pinheiro(True, 2), pedra(ROCHA, True, 7, 5, 14, 2), pedras_duas(ROCHA, True, 6)],
    'deserto': [cacto(0), cacto(1), arbusto_seco(), pedra(AREIA, False, 5, 4, 15, 9)],
    'trilha': [pedra((150, 130, 110), False, 4, 3, 16, 5)],
    'costa': [],
    'vulcanico': [rocha_lava(12), respiradouro(), arvore_morta(), pedras_duas((70, 54, 50), False, 14)],
}

# ---------------------------------------------------------------- detalhes (camada sutil, em mais casas que as decorações)
# detalhe_NOME_K.png: 24x24 transparentes, pequenos e de baixo contraste (ondas, espuma, vitórias-régias, pedrinhas...),
# para nenhum terreno ficar só com o chão liso. O minimap.php sorteia 1 detalhe em ~40% das casas livres sem decoração.
def _vazio(): return np.zeros((24, 24, 4), np.uint8)

def ondas(seed, n=2, cor=(210, 235, 245)):
    a = _vazio(); r = np.random.default_rng(seed)
    for _ in range(n):
        y0, x0 = int(r.integers(5, 19)), int(r.integers(3, 15))
        for t in range(6):
            a[y0 - (1 if 1 <= t <= 4 else 0) - (1 if 2 <= t <= 3 else 0), x0 + t] = (*cor, 190)
    return a

def espuma(seed):
    a = _vazio(); r = np.random.default_rng(seed)
    for _ in range(9):
        y, x = int(r.integers(4, 20)), int(r.integers(4, 20))
        a[y, x] = (235, 248, 252, 170); a[y, min(x + 1, 23)] = (235, 248, 252, 120)
    return a

def vitoria_regia(seed, flor=True):
    a = _vazio(); r = np.random.default_rng(seed)
    y, x = np.mgrid[0:24, 0:24]
    for (cy, cx, rr) in ((14, 10, 3.6), (9, 15, 2.6))[: 2 if seed % 2 else 1]:
        m = (x - cx) ** 2 + (y - cy) ** 2 <= rr * rr
        corte = (x > cx) & (abs(y - cy) <= (x - cx) * 0.5)
        a[m & ~corte] = (70, 130, 60, 235)
        a[m & ~corte & ((x - cx + 1) ** 2 + (y - cy + 1) ** 2 <= (rr - 1.5) ** 2)] = (100, 160, 75, 235)
    if flor: a[13:15, 9:11] = (240, 170, 200, 255); a[13, 10] = (255, 240, 250, 255)
    return a

def tronco_boiando():
    a = _vazio()
    a[12:15, 6:18] = (120, 82, 48, 255); a[12, 6:18] = (150, 108, 64, 255); a[14, 6:18] = (88, 58, 34, 255)
    a[12:15, 17] = (176, 140, 92, 255); a[13, 17] = (130, 96, 60, 255)
    a = contorno(a, (50, 36, 26))
    a[16, 5:19] = (200, 230, 240, 120)
    return a

def peixes():
    a = _vazio()
    for (cy, cx) in ((9, 8), (15, 14)):
        a[cy, cx - 2:cx + 2] = (20, 50, 70, 90); a[cy - 1:cy + 2, cx - 1:cx + 1] = (20, 50, 70, 90)
        a[cy - 1, cx + 2] = a[cy + 1, cx + 2] = (20, 50, 70, 80)
    return a

def boia():
    a = _vazio()
    a[10:16, 10:14] = (220, 60, 50, 255); a[12:14, 10:14] = (240, 240, 240, 255); a[8:10, 11:13] = (60, 60, 60, 255)
    a = contorno(a, (40, 20, 20)); a[17, 8:16] = (210, 235, 245, 150)
    return a

def ilhota():
    a = pedra((150, 140, 110), False, 6, 3, 14, 21)
    a[a[..., 3] == 70] = (210, 235, 245, 140)  # espuma em vez de sombra
    a[9:11, 12] = (60, 110, 50, 255); a[8, 11:14] = (80, 140, 60, 255)
    return a

def pedrinhas(base, seed, n=3):
    a = _vazio(); r = np.random.default_rng(seed); base = np.array(base)
    for _ in range(n):
        y, x = int(r.integers(6, 19)), int(r.integers(5, 18))
        a[y:y + 2, x:x + 2] = (*base, 255); a[y, x] = (*np.clip(base + 40, 0, 255), 255); a[y + 2, x:x + 2] = (0, 0, 0, 60)
    return a

def tufos(cor, seed, n=3, alt=3):
    a = _vazio(); r = np.random.default_rng(seed); cor = np.array(cor)
    for _ in range(n):
        y, x = int(r.integers(9, 20)), int(r.integers(5, 18))
        for dx, h in ((-1, alt - 1), (0, alt), (1, alt - 1)):
            a[y - h:y + 1, x + dx] = (*cor, 230)
        a[y - alt, x] = (*np.clip(cor + 40, 0, 255), 230)
    return a

def bolhas(seed):
    a = _vazio(); r = np.random.default_rng(seed)
    for _ in range(4):
        y, x = int(r.integers(5, 19)), int(r.integers(5, 19))
        a[y, x] = (180, 200, 150, 180); a[y - 1, x] = (230, 240, 210, 140)
    return a

def ossos():
    a = _vazio(); c = (225, 215, 190, 255)
    for t in range(9): a[10 + t // 2, 7 + t] = c
    for (y, x) in ((9, 6), (11, 6), (13, 16), (15, 16)): a[y, x] = c
    return contorno(a, (110, 90, 60))

def ondulacao_areia(seed):
    a = _vazio(); r = np.random.default_rng(seed)
    for k in range(3):
        y0, x0 = 7 + k * 5, int(r.integers(3, 9))
        for t in range(10): a[y0 + (1 if 3 <= t <= 6 else 0), x0 + t] = (150, 112, 70, 110)
    return a

def floco_neve(seed):
    a = _vazio(); r = np.random.default_rng(seed)
    for _ in range(3):
        y, x = int(r.integers(5, 19)), int(r.integers(5, 19))
        a[y, x - 1:x + 2] = (255, 255, 255, 220); a[y - 1:y + 2, x] = (255, 255, 255, 220)
        a[y + 2, x - 1:x + 2] = (150, 170, 200, 90)
    return a

def pegadas():
    a = _vazio()
    for k, (y, x) in enumerate(((19, 9), (15, 13), (11, 10), (7, 14))):
        a[y:y + 2, x:x + 2] = (110, 84, 60, 150)
    return a

def flores(seed, cores=((240, 230, 90), (240, 240, 250), (230, 120, 150))):
    a = _vazio(); r = np.random.default_rng(seed)
    for k in range(4):
        y, x = int(r.integers(7, 19)), int(r.integers(5, 19)); c = cores[k % len(cores)]
        a[y + 1:y + 3, x] = (70, 130, 50, 255); a[y, x] = (*c, 255)
    return a

def brasas(seed):
    a = _vazio(); r = np.random.default_rng(seed)
    for _ in range(5):
        y, x = int(r.integers(5, 20)), int(r.integers(5, 20))
        a[y, x] = (255, 150, 40, 230); a[y, x + 1] = (200, 70, 20, 200)
    return a

GRAMA_TUF = (70, 130, 50)
DETALHE = {
    'costa': [ondas(1), ondas(2, 3), espuma(3), vitoria_regia(4), vitoria_regia(5), tronco_boiando(), peixes(), ilhota()],
    'pantano': [vitoria_regia(7, False), vitoria_regia(8), bolhas(9), tufos((80, 110, 50), 10), tronco_boiando()],
    'deserto': [ondulacao_areia(11), ondulacao_areia(12), pedrinhas((170, 130, 90), 13), ossos(), tufos((150, 130, 70), 14, 2)],
    'neve': [floco_neve(15), floco_neve(16), pedrinhas((120, 125, 135), 17), pegadas(), tufos((110, 120, 90), 18, 2)],
    'rochoso': [pedrinhas((120, 122, 128), 19), pedrinhas((100, 102, 110), 20, 4), tufos((90, 120, 60), 21), pedrinhas((140, 130, 115), 22, 2)],
    'trilha': [pedrinhas((140, 120, 100), 23), pegadas(), tufos(GRAMA_TUF, 24, 2), pedrinhas((120, 105, 90), 25, 4)],
    'grama': [flores(26), tufos(GRAMA_TUF, 27), pedrinhas((130, 130, 120), 28, 2), flores(29, ((250, 250, 250), (240, 220, 80)))],
    'floresta': [tufos((50, 100, 40), 30), flores(31, ((240, 240, 250), (200, 80, 80))), pedrinhas((110, 110, 100), 32, 2)],
    'vulcanico': [brasas(33), brasas(34), pedrinhas((60, 50, 46), 35, 4), ossos()],
}

if __name__ == '__main__':
    if len(sys.argv) > 2 and sys.argv[2] == '--detalhes':  # só os detalhes (não regera chãos/bordas/decorações)
        for nome, lista in DETALHE.items():
            for f in os.listdir(OUT):
                if f.startswith(f'detalhe_{nome}_'): os.remove(os.path.join(OUT, f))
            for k, d in enumerate(lista): sprite(d).save(os.path.join(OUT, f'detalhe_{nome}_{k}.png'), optimize=True)
            print(nome, len(lista), 'detalhes')
        sys.exit(0)
    so = sys.argv[2].split(',') if len(sys.argv) > 2 else list(ALVO)  # opcional: só estes terrenos (ex.: vulcanico)
    for nome in so:
        tiles = chaos(nome)
        for v, t in enumerate(tiles): Image.fromarray(t).save(os.path.join(OUT, f'{nome}_{v}.png'), optimize=True)
        base = tiles[0]
        m = mascara_borda(sum(map(ord, nome)))
        for lado, k in (('n', 0), ('o', 1), ('s', 2), ('l', 3)):  # o = oeste (esquerda), l = leste
            salvar_rgba(base, pontilha(np.rot90(m, k)), f'{nome}_b_{lado}.png')
        mc = mascara_canto(sum(map(ord, nome)) + 5)
        for lado, k in (('no', 0), ('so', 1), ('sl', 2), ('nl', 3)):
            salvar_rgba(base, pontilha(np.rot90(mc, k)), f'{nome}_c_{lado}.png')
        for f in os.listdir(OUT):
            if f.startswith(f'deco_{nome}_'): os.remove(os.path.join(OUT, f))
        for k, d in enumerate(DECO[nome]): sprite(d).save(os.path.join(OUT, f'deco_{nome}_{k}.png'), optimize=True)
        print(nome, len(tiles), 'chãos,', len(DECO[nome]), 'decorações')
