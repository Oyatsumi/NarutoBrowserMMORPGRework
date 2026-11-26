#!/usr/bin/env python3
"""Gera o mapa global de 400 casas (images/mapateste.jpg) a partir do mapa original de 300 casas.

O original (501x501, -300..300) fica pixel a pixel no meio (só a moldura preta de 1 px é trocada); em volta é desenhado
um anel novo (300..400 casas) no mesmo estilo chapado (cores lisas e fronteiras cinza):
  norte = geleira (neve) e mar gelado no nordeste; leste = mar aberto com ilhas (costa) e ilhas de mangue (pântano);
  sul = terras vulcânicas (vulcanico) e ermos de cânions no sudoeste (rochoso); oeste = grande deserto (deserto) com cânions.
As regiões batem com os retângulos da tabela terrenos (ver build_sql.py). Uso:
  python3 gerar_mapa_grande.py ORIGINAL.jpg SAIDA.jpg [FOLHA.png]
Só Pillow + numpy. Determinístico."""
import sys
import numpy as np
from PIL import Image, ImageDraw, ImageFilter

ORIG, SAIDA = sys.argv[1], sys.argv[2]
FOLHA = sys.argv[3] if len(sys.argv) > 3 else None
N = 667            # 501 px = 600 casas -> 800 casas = 667 px (0,834 px por casa, como o original)
OFF = (N - 501) // 2  # 83
SS = 4             # supersample do anel
M = N * SS
rng = np.random.default_rng(400)

orig = np.asarray(Image.open(ORIG).convert('RGB'))
TERRA, MAR, CINZA = (167, 204, 150), (153, 179, 204), (127, 127, 127)
COR = {2: (232, 239, 243), 3: MAR, 4: MAR, 5: (124, 94, 82), 6: (196, 156, 112), 7: (229, 207, 152),
       8: (190, 146, 104), 9: (150, 168, 116), 10: TERRA, 11: (238, 244, 248)}
AGUA = {1, 3, 4}

def ruido(semente, celulas, amp):
    """Ruído suave (valor interpolado) do tamanho M x M, em casas do jogo."""
    r = np.random.default_rng(semente).random((celulas + 1, celulas + 1))
    im = Image.fromarray((r * 255).astype(np.uint8)).resize((M, M), Image.BICUBIC)
    return (np.asarray(im).astype(float) / 255 - 0.5) * 2 * amp

# Coordenadas do jogo de cada pixel (supersample): lat para cima, lon para a direita.
idx = (np.arange(M) + 0.5) / SS
lon = (idx - N / 2) * 800 / N
lat = -lon.copy()
LON, LAT = np.meshgrid(lon, lat)
n1, n2, n3 = ruido(1, 18, 9), ruido(2, 18, 9), ruido(3, 9, 14)
la, lo = LAT + n1, LON + n2

# ---- biomas do anel (mesma divisão dos retângulos de terreno) -------------------------------------------
rot = np.zeros((M, M), np.uint8)
norte, sul, leste, oeste = la > 300, la < -300, lo > 300, lo < -300
rot[oeste] = 7
rot[oeste & (la + n3 > -100) & (la - n3 < 150) & (lo < -335 + n3)] = 8          # cânions no meio do deserto
rot[leste] = 4
rot[sul] = 5
rot[(sul | oeste) & (la < -250) & (lo < -250)] = 6                          # ermos de cânions (sudoeste)
rot[norte] = 2
rot[norte & (lo > 250)] = 3                                                 # mar gelado (nordeste)

# Ilhas no mar do leste (verdes), de mangue (pântano) e blocos de gelo no nordeste.
nil = ruido(9, 90, 1) + ruido(19, 30, 1)
def ilha(cy, cx, rr, rotulo, regiao):
    m = np.hypot(LAT - cy, (LON - cx) * 0.85) < rr * (1 + 0.3 * nil)
    rot[m & regiao] = rotulo
mar_l = rot == 4
for (cy, cx, rr) in ((240, 345, 13), (180, 372, 9), (110, 335, 16), (60, 380, 8), (20, 352, 11), (-40, 330, 9),
                     (-70, 376, 12), (-260, 350, 10), (280, 382, 6), (150, 318, 5)):
    ilha(cy, cx, rr, 10, mar_l)
for (cy, cx, rr) in ((-140, 340, 17), (-175, 366, 13), (-125, 378, 9), (-195, 330, 8)):
    ilha(cy, cx, rr, 9, mar_l)
mar_g = rot == 3
for (cy, cx, rr) in ((360, 275, 10), (335, 320, 8), (385, 340, 12), (350, 375, 9), (318, 362, 5), (378, 300, 5)):
    ilha(cy, cx, rr, 11, mar_g)
for (cy, cx, rr) in ((-345, 330, 14), (-385, 372, 10)):                     # ilhas vulcânicas no sudeste
    ilha(cy, cx, rr, 5, rot == 5)

# ---- faixa de transição: o mundo antigo "continua" um pouco (bordas replicadas) antes da fronteira nova ---
d = np.maximum(np.abs(LAT), np.abs(LON)) - 300
ext = 6 + (ruido(5, 70, 1) + ruido(15, 25, 1) + 2) * 5                     # 6 a 26 casas
faixa = (d > -1) & (d < ext)
px = np.clip(((LON * N / 800 + N / 2) - OFF).astype(int), 4, 496)
py = np.clip(((-LAT * N / 800 + N / 2) - OFF).astype(int), 4, 496)
borda = orig[py, px].astype(float)
eagua = borda[..., 2] > borda[..., 1]
rot[faixa & eagua] = 1
rot[faixa & ~eagua] = 0
# Mar antigo que encosta no mar novo não ganha fronteira; mar antigo encostado em terra nova vira costa.

img = np.zeros((M, M, 3), float)
for k, c in COR.items(): img[rot == k] = c
img[faixa] = borda[faixa]

# ---- detalhes desenhados (rachaduras de lava, dunas, estratos, fendas no gelo, vulcões) -------------------
pil = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8))
dr = ImageDraw.Draw(pil)
def P(la_, lo_): return ((lo_ * N / 800 + N / 2) * SS, (-la_ * N / 800 + N / 2) * SS)
def dentro(rotulos, la_, lo_):
    x, y = P(la_, lo_); x, y = int(x), int(y)
    return 0 <= x < M and 0 <= y < M and rot[y, x] in rotulos
def traco(rotulos, cor, qtd, passos, larg, curva, semente):
    r = np.random.default_rng(semente)
    for _ in range(qtd):
        a, b = r.uniform(-400, 400), r.uniform(-400, 400)
        if not dentro(rotulos, a, b): continue
        ang = r.uniform(0, 2 * np.pi); pts = [P(a, b)]
        for _ in range(passos):
            ang += r.normal(0, curva); a += np.sin(ang) * 3; b += np.cos(ang) * 3
            if not dentro(rotulos, a, b): break
            pts.append(P(a, b))
        if len(pts) > 2: dr.line(pts, fill=cor, width=larg, joint='curve')
traco({5}, (228, 98, 40), 260, 9, 3, 0.7, 21)        # rachaduras de lava
traco({5}, (250, 170, 70), 120, 4, 2, 0.9, 22)
traco({2}, (190, 210, 228), 700, 4, 3, 0.5, 23)     # fendas na geleira
traco({7}, (212, 186, 128), 300, 5, 3, 0.15, 24)     # dunas
traco({6, 8}, (160, 116, 80), 260, 10, 4, 0.12, 25)  # estratos dos cânions
traco({9}, (108, 128, 84), 160, 2, 3, 0.9, 26)       # mangue
for (a, b, t) in ((-352, -120, 15), (-372, 40, 19), (-340, 150, 13), (-385, 230, 16), (-330, -40, 10), (-360, 300, 9),
                  (-345, 330, 8), (-388, -200, 11)):
    if not dentro({5}, a, b): continue
    (x, y), h = P(a, b), t * SS * N / 800
    dr.polygon([(x - h, y + h * 0.55), (x + h, y + h * 0.55), (x + h * 0.22, y - h * 0.6), (x - h * 0.22, y - h * 0.6)], fill=(84, 62, 56), outline=CINZA)
    dr.polygon([(x - h * 0.22, y - h * 0.6), (x + h * 0.22, y - h * 0.6), (x, y - h * 0.3)], fill=(236, 90, 36))
    dr.line([(x - h * 0.05, y - h * 0.3), (x - h * 0.25, y + h * 0.4)], fill=(236, 90, 36), width=3)
for (a, b, t) in ((-150, -365, 9), (40, -380, 8), (-200, -350, 7), (250, -360, 7)):   # oásis no deserto
    if not dentro({7}, a, b): continue
    (x, y), h = P(a, b), t * SS * N / 800
    dr.ellipse([x - h, y - h * 0.6, x + h, y + h * 0.6], fill=MAR, outline=CINZA, width=3)
for (a, b) in ((330, -250), (360, -120), (345, 20), (372, 140), (318, 200), (385, -330)):   # picos gelados
    if not dentro({2}, a, b): continue
    (x, y), h = P(a, b), 9 * SS * N / 800
    dr.polygon([(x - h, y + h * 0.5), (x, y - h * 0.7), (x + h, y + h * 0.5)], fill=(205, 220, 232), outline=CINZA)
    dr.polygon([(x - h * 0.35, y - h * 0.2), (x, y - h * 0.7), (x + h * 0.35, y - h * 0.2)], fill=(250, 252, 255))
img = np.asarray(pil).astype(float)

# ---- fronteiras cinza (entre rótulos diferentes, menos água com água) e divisões de províncias -------------
classe = rot.astype(int).copy()
classe[np.isin(rot, list(AGUA))] = 1
classe[faixa & ~eagua] = 100                                                # terra antiga continua sem linha interna
fr = np.zeros((M, M), bool)
for dy, dx in ((1, 0), (0, 1), (2, 0), (0, 2), (3, 0), (0, 3)):
    a = classe; b = np.roll(np.roll(classe, dy, 0), dx, 1)
    fr |= (a != b)
# províncias: linhas extras dentro do deserto, geleira e terras vulcânicas
prov = np.zeros((M, M), int)
for k, (s, c) in enumerate(((31, 5), (32, 5), (33, 4))):
    prov += (ruido(s, c, 1) > 0).astype(int) << k
for dy, dx in ((1, 0), (0, 1), (2, 0), (0, 2)):
    fr |= (prov != np.roll(np.roll(prov, dy, 0), dx, 1)) & np.isin(rot, [5, 7]) & (d > 30)
img[fr] = CINZA

anel = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8)).resize((N, N), Image.LANCZOS)
final = np.asarray(anel).copy()
# Original no meio: miolo pixel a pixel; a moldura preta de 1 px (e a linha escura logo acima da de baixo) vira a cor vizinha.
o = orig.copy()
o[0, :] = o[1, :]; o[-4:, :] = o[-5, :]; o[:, 0] = o[:, 1]; o[:, -1] = o[:, -2]
final[OFF:OFF + 501, OFF:OFF + 501] = o
final[0, :] = final[-1, :] = 0; final[:, 0] = final[:, -1] = 0                # moldura preta de volta, no mapa todo
Image.fromarray(final).save(SAIDA, quality=95, subsampling=0)
assert (np.asarray(Image.fromarray(final))[OFF + 1:OFF + 497, OFF + 1:OFF + 500] == orig[1:497, 1:500]).all()
print('ok', SAIDA, final.shape)

if FOLHA:  # folha de referência: mapa 2x com a grade de casas e os nomes dos terrenos novos
    f = Image.fromarray(final).resize((N * 2, N * 2), Image.NEAREST).convert('RGB')
    g = ImageDraw.Draw(f)
    for c in range(-400, 401, 100):
        p = (c * N / 800 + N / 2) * 2
        g.line([(p, 0), (p, N * 2)], fill=(90, 90, 90) if c else (0, 0, 0), width=1)
        g.line([(0, p), (N * 2, p)], fill=(90, 90, 90) if c else (0, 0, 0), width=1)
        g.text((p + 3, 3), str(c), fill=(0, 0, 0)); g.text((3, (-c * N / 800 + N / 2) * 2 + 3), str(c), fill=(0, 0, 0))
    q = lambda la_, lo_: ((lo_ * N / 800 + N / 2) * 2, (-la_ * N / 800 + N / 2) * 2)
    for (a, b, t) in ((350, -150, 'neve (geleira)'), (350, 300, 'costa (mar gelado)'), (60, 340, 'costa'), (-160, 345, 'pantano'),
                      (-350, 0, 'vulcanico'), (-340, -350, 'rochoso'), (100, -360, 'deserto'), (10, -385, 'rochoso')):
        x, y = q(a, b); g.rectangle([x - 2, y - 2, x + 7 * len(t), y + 12], fill=(255, 255, 230)); g.text((x, y), t, fill=(0, 0, 0))
    x0, y0 = q(300, -300); x1, y1 = q(-300, 300)
    g.rectangle([x0, y0, x1, y1], outline=(200, 0, 0), width=2)
    f.save(FOLHA)
    print('folha', FOLHA)
