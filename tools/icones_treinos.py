"""Ícones 24x24 (images/24/treino_CHAVE.gif) dos mestres dos treinos longos, mostrados no quadro "Outros" das vilas."""
import sys
from PIL import Image, ImageDraw
PASTA = sys.argv[1] if len(sys.argv) > 1 else 'game'
def base(cor):
    im = Image.new('RGBA', (96, 96), (0, 0, 0, 0)); d = ImageDraw.Draw(im)
    d.ellipse((4, 4, 92, 92), fill=(69, 34, 2, 255)); d.ellipse((10, 10, 86, 86), fill=cor)
    return im, d
def salvar(im, chave):
    im = im.resize((24, 24), Image.LANCZOS)
    fundo = Image.new('RGBA', im.size, (255, 0, 255, 255)); fundo.alpha_composite(im)
    p = fundo.convert('RGB').quantize(colors=63)
    pal = p.getpalette(); idx = None
    for i in range(64):
        if pal[i*3:i*3+3] == [255, 0, 255]: idx = i
    a = im.split()[3]
    out = p.copy(); px = out.load(); ap = a.load()
    if idx is None: idx = 63; pal[189:192] = [255, 0, 255]; out.putpalette(pal)
    for y in range(24):
        for x in range(24):
            if ap[x, y] < 110: px[x, y] = idx
    out.save(f'{PASTA}/images/24/treino_{chave}.gif', transparency=idx)
W = (255, 246, 216, 255)
im, d = base((120, 60, 160, 255)); d.polygon([(48, 18), (70, 50), (48, 80), (26, 50)], fill=W); d.polygon([(48, 34), (59, 50), (48, 66), (37, 50)], fill=(120, 60, 160, 255)); salvar(im, 'elemento')
im, d = base((40, 110, 200, 255)); d.ellipse((24, 24, 72, 72), outline=W, width=8); d.ellipse((40, 40, 56, 56), fill=W); salvar(im, 'chakra')
im, d = base((120, 120, 130, 255)); d.polygon([(26, 22), (70, 22), (70, 52), (48, 78), (26, 52)], fill=W); d.rectangle((44, 30, 52, 62), fill=(120, 120, 130, 255)); salvar(im, 'ferro')
im, d = base((190, 140, 50, 255)); d.ellipse((24, 50, 44, 76), fill=W); d.ellipse((52, 22, 72, 48), fill=W); salvar(im, 'caminhada')
im, d = base((40, 140, 70, 255)); d.polygon([(48, 18), (62, 46), (48, 78), (34, 46)], fill=W); d.line((48, 78, 48, 86), fill=W, width=5); salvar(im, 'meditacao')
im, d = base((30, 30, 50, 255)); d.polygon([(56, 14), (34, 52), (50, 52), (38, 84), (66, 40), (50, 40)], fill=(250, 230, 90, 255)); salvar(im, 'jutsuraro')
print('ok')
