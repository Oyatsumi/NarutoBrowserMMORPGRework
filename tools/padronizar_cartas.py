"""Padroniza a cor e o contraste do retrato de todas as cartas (mesmo tom sépia e contraste das cartas novas).
Só o quadro do retrato (x 6..72, y 37..135) é refeito; moldura e texto da carta ficam intactos.
Uso: python3 padronizar_cartas.py game/layoutnovo/monstros
"""
import sys, os
from PIL import Image, ImageOps, ImageEnhance
ESCURO, CLARO = (52, 28, 8), (250, 236, 200)
CAIXA = (6, 37, 73, 136)
pasta = sys.argv[1]
for f in sorted(os.listdir(pasta)):
    if not f.endswith('.jpg') or not f.split('.')[0].isdigit(): continue
    p = os.path.join(pasta, f); im = Image.open(p).convert('RGB')
    ret = im.crop(CAIXA)
    cinza = ImageOps.autocontrast(ImageOps.grayscale(ret), cutoff=2)
    cinza = ImageEnhance.Contrast(cinza).enhance(1.15)
    im.paste(ImageOps.colorize(cinza, black=ESCURO, white=CLARO), CAIXA[:2])
    im.save(p, quality=92)
