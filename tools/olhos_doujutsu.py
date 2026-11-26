"""Ícones 164x65 dos olhos novos do doujutsu (images/olhos/falcao.jpg e celeste.jpg), arte própria.
Parte do olho aberto de images/olhos/byakugan.jpg (pálpebras e fundo) e pinta uma íris nova por cima,
só nos pixels claros da íris original (os traços escuros das pálpebras continuam por cima)."""
import sys, math
from PIL import Image, ImageDraw
PASTA = sys.argv[1] if len(sys.argv) > 1 else 'game'
CENTROS = ((41, 30), (123, 30)); R = 15

def iris(desenho):
    base = Image.open(f'{PASTA}/images/olhos/byakugan.jpg').convert('RGB')
    px = base.load()
    camada = Image.new('RGB', base.size, (0, 0, 0)); d = ImageDraw.Draw(camada)
    for cx, cy in CENTROS: desenho(d, cx, cy)
    cp = camada.load(); out = base.copy(); op = out.load()
    for y in range(base.size[1]):
        for x in range(base.size[0]):
            r, g, b = px[x, y]
            for cx, cy in CENTROS:
                if (x - cx) ** 2 + (y - cy) ** 2 <= (R + 1) ** 2 and abs(r - b) < 28 and r > 115:
                    luz = min(1.15, (r + g + b) / 3 / 215)
                    nr, ng, nb = cp[x, y]
                    op[x, y] = (min(255, int(nr * luz)), min(255, int(ng * luz)), min(255, int(nb * luz)))
    return out

def falcao(d, cx, cy):  # íris âmbar com anéis e pupila em losango vertical
    for k in range(R, 0, -1):
        t = k / R; d.ellipse((cx - k, cy - k, cx + k, cy + k), fill=(int(150 + 90 * (1 - t)), int(85 + 90 * (1 - t)), int(10 + 20 * (1 - t))))
    d.ellipse((cx - R, cy - R, cx + R, cy + R), outline=(90, 45, 5), width=2)
    d.polygon([(cx, cy - 11), (cx + 3, cy), (cx, cy + 11), (cx - 3, cy)], fill=(25, 12, 2))

def celeste(d, cx, cy):  # íris azul-gelo com estrela de seis pontas e anel claro
    for k in range(R, 0, -1):
        t = k / R; d.ellipse((cx - k, cy - k, cx + k, cy + k), fill=(int(70 + 140 * (1 - t)), int(140 + 100 * (1 - t)), 230))
    d.ellipse((cx - R, cy - R, cx + R, cy + R), outline=(30, 70, 150), width=2)
    pts = []
    for i in range(12):
        a = math.pi / 6 * i - math.pi / 2; rr = 11 if i % 2 == 0 else 5
        pts.append((cx + rr * math.cos(a), cy + rr * math.sin(a)))
    d.polygon(pts, fill=(240, 250, 255)); d.ellipse((cx - 3, cy - 3, cx + 3, cy + 3), fill=(40, 90, 180))

iris(falcao).save(f'{PASTA}/images/olhos/falcao.jpg', quality=92)
iris(celeste).save(f'{PASTA}/images/olhos/celeste.jpg', quality=92)
print('ok')

# Ícones 24x24 dos mestres dos treinos de doujutsu (images/24/treino_CHAVE.gif), no estilo de tools/icones_treinos.py:
# círculo marrom com um olho amendoado e a íris na cor do olho.
def icone(chave, iriscor, fundo, extra=None):
    im = Image.new('RGBA', (96, 96), (0, 0, 0, 0)); d = ImageDraw.Draw(im)
    d.ellipse((4, 4, 92, 92), fill=(69, 34, 2, 255)); d.ellipse((10, 10, 86, 86), fill=fundo)
    d.chord((14, 28, 82, 92), 200, 340, fill=(255, 246, 216, 255)); d.chord((14, 4, 82, 68), 20, 160, fill=(255, 246, 216, 255))
    d.ellipse((34, 34, 62, 62), fill=iriscor)
    if extra: extra(d)
    else: d.ellipse((44, 44, 52, 52), fill=(20, 10, 2, 255))
    im = im.resize((24, 24), Image.LANCZOS)
    bg = Image.new('RGBA', im.size, (255, 0, 255, 255)); bg.alpha_composite(im)
    p = bg.convert('RGB').quantize(colors=63); pal = p.getpalette(); idx = None
    for i in range(64):
        if pal[i*3:i*3+3] == [255, 0, 255]: idx = i
    if idx is None: idx = 63; pal[189:192] = [255, 0, 255]; p.putpalette(pal)
    px = p.load(); a = im.split()[3].load()
    for y in range(24):
        for x in range(24):
            if a[x, y] < 110: px[x, y] = idx
    p.save(f'{PASTA}/images/24/treino_{chave}.gif', transparency=idx)
icone('olho_falcao', (200, 130, 20, 255), (150, 100, 40, 255))
icone('olho_sharingan', (200, 20, 20, 255), (90, 20, 20, 255))
icone('sharingan_evo', (200, 20, 20, 255), (40, 10, 10, 255), lambda d: [d.ellipse((x - 4, y - 4, x + 4, y + 4), fill=(10, 5, 5, 255)) for x, y in ((48, 39), (40, 53), (56, 53))])
icone('olho_byakugan', (225, 225, 235, 255), (120, 120, 150, 255), lambda d: d.ellipse((44, 44, 52, 52), outline=(150, 150, 170, 255), width=2))
icone('olho_celeste', (110, 170, 240, 255), (30, 70, 150, 255))
icone('olho_rinnegan', (170, 160, 200, 255), (60, 40, 90, 255), lambda d: [d.ellipse((48 - r, 48 - r, 48 + r, 48 + r), outline=(40, 30, 60, 255), width=2) for r in (5, 10)])
print('icones ok')
