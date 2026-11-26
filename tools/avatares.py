"""Ícones dos Pergaminhos de Disfarce (drops 301-308): recolore o makimono selado (pergaminho_selado.gif) para roxo/dourado
e coloca o rosto do avatar (layoutnovo/avatares/<n>.jpg) num selo redondo. Saída: layoutnovo/equipamentos/drops/<id>.gif (34x34).
Uso: python3 tools/avatares.py /caminho/para/game"""
import sys, colorsys
from PIL import Image, ImageDraw
G = sys.argv[1]
TOKENS = {301: 5, 302: 12, 303: 3, 304: 8, 305: 1, 306: 7, 307: 17, 308: 14}
base = Image.open(G + '/layoutnovo/equipamentos/novos/pergaminho_selado.gif').convert('RGBA')
def recolor(im, hue):
    px = im.load()
    for y in range(im.size[1]):
        for x in range(im.size[0]):
            r, g, b, a = px[x, y]
            if a == 0: continue
            h, l, s = colorsys.rgb_to_hls(r/255, g/255, b/255)
            if s > 0.15: h = hue
            r, g, b = colorsys.hls_to_rgb(h, l, min(1, s*1.1))
            px[x, y] = (int(r*255), int(g*255), int(b*255), a)
    return im
for did, av in TOKENS.items():
    im = recolor(base.copy().resize((34, 34)), 0.78)
    face = Image.open(f'{G}/layoutnovo/avatares/{av}.jpg').convert('RGBA').crop((22, 25, 101, 104)).resize((16, 16))
    mask = Image.new('L', (16, 16), 0); ImageDraw.Draw(mask).ellipse((0, 0, 15, 15), fill=255)
    ring = Image.new('RGBA', (20, 20), (0, 0, 0, 0)); ImageDraw.Draw(ring).ellipse((0, 0, 19, 19), fill=(214, 170, 60, 255), outline=(90, 50, 10, 255))
    im.alpha_composite(ring, (13, 13)); im.paste(face, (15, 15), mask)
    bg = Image.new('RGBA', im.size, (255, 241, 199, 255)); bg.alpha_composite(im)
    bg.convert('RGB').convert('P', palette=Image.ADAPTIVE).save(f'{G}/layoutnovo/equipamentos/drops/{did}.gif')
print('ok', len(TOKENS))
