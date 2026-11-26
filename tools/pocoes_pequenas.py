# pocoes_pequenas.py :: desenha os ícones 34x34 das poções pequenas (drops 431-435), no estilo das poções dos drops
# (fundo escuro de drops/66.gif), mas num frasco FINO (tubo com rolha), para não parecer as poções grandes, e a cor da barra:
# HP vermelho, CH azul, TP amarelo-oliva, NP verde, EP roxo. Salva em drops/ID.gif e consumiveis/ID.gif.
import sys
from PIL import Image, ImageDraw
raiz = (sys.argv[1] if len(sys.argv) > 1 else 'game') + '/layoutnovo/equipamentos/'
base = Image.open('tools/base/drop_fundo.gif').convert('RGB')  # fundo original (o drops/66.gif foi redesenhado por pocoes_grandes.py)
fundo = base.getpixel((5, 5))
CORES = {431: (200, 40, 50), 432: (45, 100, 215), 433: (200, 180, 40), 434: (50, 170, 70), 435: (140, 70, 200)}

def tom(c, f):
    return tuple(max(0, min(255, int(v * f))) for v in c)

for iditem, cor in CORES.items():
    im = Image.new('RGB', (34, 34), fundo)
    for i in range(34):  # mesma moldura de 1 px dos ícones de drop
        for (a, b) in [(i, 0), (i, 33), (0, i), (33, i)]:
            im.putpixel((a, b), base.getpixel((a, b)))
    d = ImageDraw.Draw(im)
    # Frasco FINO (tubo de ensaio com rolha): bem diferente das poções grandes de frasco redondo.
    cx, contorno, vidro = 17, (25, 25, 32), (175, 190, 205)
    d.rounded_rectangle((cx - 4, 9, cx + 3, 29), radius=3, fill=contorno)        # contorno do tubo
    d.rounded_rectangle((cx - 3, 10, cx + 2, 28), radius=2, fill=vidro)          # vidro
    d.rounded_rectangle((cx - 3, 17, cx + 2, 28), radius=2, fill=cor)            # líquido (2/3 do tubo)
    d.rectangle((cx + 1, 18, cx + 2, 27), fill=tom(cor, .65))                    # sombra do líquido
    d.line((cx - 3, 17, cx + 2, 17), fill=tom(cor, 1.35))                        # superfície
    d.line((cx - 2, 12, cx - 2, 25), fill=(240, 245, 250))                       # reflexo no vidro
    d.rectangle((cx - 4, 6, cx + 3, 10), fill=(70, 40, 20))                      # rolha
    d.rectangle((cx - 3, 6, cx + 2, 9), fill=(165, 110, 60))
    d.line((cx - 3, 6, cx + 2, 6), fill=(200, 150, 90))
    out = im.convert('P', palette=Image.ADAPTIVE, colors=64)
    out.save(raiz + 'drops/%d.gif' % iditem)
    out.save(raiz + 'consumiveis/%d.gif' % iditem)
print('ok')
