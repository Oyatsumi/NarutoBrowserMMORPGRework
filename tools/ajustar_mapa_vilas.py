"""Ajusta a arte do mapa global (images/mapa_grande.png) às coordenadas das vilas (tabela towns):
três vilas ficavam desenhadas no meio do mar. Em vez de mudar as coordenadas (missões, NPCs, terrenos e jogadores
dependem delas), o desenho ganha terra embaixo delas, no mesmo estilo chapado com contorno cinza:
  - Vila da Sabedoria [300,0]: uma língua de gelo descendo da geleira (o terreno ali já é 'neve', lat >= 300);
  - Castelo Real [239,151]: ilha (terreno 'costa');  - Vila da Nuvem [170,170]: ilha (terreno 'grama').
  python3 ajustar_mapa_vilas.py ORIGINAL.png SAIDA.png"""
import sys, math
import numpy as np
from PIL import Image, ImageDraw, ImageFilter
ORIG, SAIDA = sys.argv[1], sys.argv[2]
im = Image.open(ORIG).convert('RGB'); N = im.size[0]
q = lambda la, lo: ((lo + 400) / 800 * N, (400 - la) / 800 * N)
TERRA, GELO, BORDA = (167, 204, 150), (232, 239, 243), (128, 128, 128)
rng = np.random.default_rng(3)
def blob(la, lo, raio, irreg=0.25, pontos=28):  # contorno irregular em volta de [la,lo] (raio em casas)
    cx, cy = q(la, lo); r = raio / 800 * N; fases = rng.random(3) * 6.28
    return [(cx + r * (1 + irreg * sum(math.sin(k * a + f) / k for k, f in zip((2, 3, 5), fases))) * math.cos(a),
             cy + r * (1 + irreg * sum(math.sin(k * a + f) / k for k, f in zip((2, 3, 5), fases))) * math.sin(a))
            for a in np.linspace(0, 2 * math.pi, pontos, endpoint=False)]
g = ImageDraw.Draw(im)
for (la, lo, raio, cor) in ((239, 151, 24, TERRA), (170, 170, 26, TERRA)):
    pts = blob(la, lo, raio); g.polygon(pts, fill=cor); g.line(pts + [pts[0]], fill=BORDA, width=1)
# língua de gelo: da borda da geleira (lat ~320) até um pouco abaixo da vila (lat ~285)
pts = blob(300, 0, 22, 0.2); topo = q(325, 0)[1]
pts = [(x, min(y, topo + 2) if y < q(300, 0)[1] - 8 else y) for (x, y) in pts]
g.polygon(pts, fill=GELO)
contorno = [p for p in pts + [pts[0]]]
for a, b in zip(contorno, contorno[1:]):
    if a[1] > topo + 3 or b[1] > topo + 3: g.line([a, b], fill=BORDA, width=1)
im.save(SAIDA, optimize=True)
print('ok', SAIDA)
