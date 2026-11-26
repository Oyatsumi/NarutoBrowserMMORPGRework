"""Ícones 34x34 dos itens novos, desenhados do zero com Pillow (formas geométricas, sem partir de nenhuma arte existente),
no formato dos quadrinhos de layoutnovo/equipamentos (fundo escuro com borda):
- novos/pergaminho_teleporte.gif e drops/426.gif: pergaminho violeta com espiral de selo;
- drops/420-425: itens de compensação (amuleto, selo-lâmina, pluma, coração de pedra, olho, brasão) + selo verde/vermelho no canto;
- drops/427-430: pergaminhos dos jutsus novos (Passo Veloz e Invocação de Suprimentos).
Uso: python3 tools/icones_especiais.py game/layoutnovo/equipamentos"""
import sys, math
from PIL import Image, ImageDraw

B = sys.argv[1].rstrip('/')
S = 4  # desenha 4x maior e reduz (bordas suaves)

def quadro(fundo):
    im = Image.new('RGB', (34 * S, 34 * S), fundo)
    d = ImageDraw.Draw(im)
    d.rectangle([0, 0, 34 * S - 1, 34 * S - 1], outline=(70, 66, 60), width=S)
    return im, d

def P(*xy): return [v * S for v in xy]

def selo(im):
    """Selo de compensação no canto superior direito: seta verde para cima, seta vermelha para baixo."""
    d = ImageDraw.Draw(im)
    d.rectangle([24, 1, 33, 10], fill=(20, 16, 14))
    d.polygon([(25, 6), (27, 2), (29, 6)], fill=(80, 220, 90))
    d.polygon([(28, 5), (30, 9), (32, 5)], fill=(235, 50, 40))
    return im

def fim(im, rel, comselo=False):
    im = im.resize((34, 34), Image.LANCZOS)
    if comselo: im = selo(im)
    im.convert('P', palette=Image.ADAPTIVE, colors=128).save(B + '/' + rel)

def pergaminho(papel, faixa, simbolo):
    im, d = quadro((30, 28, 34))
    d.rounded_rectangle(P(9, 6, 25, 28), radius=2 * S, fill=papel, outline=(60, 40, 25), width=S)
    for y in (5, 27):
        d.rounded_rectangle(P(7, y, 27, y + 3), radius=S, fill=(110, 70, 40), outline=(50, 30, 15), width=S // 2)
    d.rectangle(P(9, 15, 25, 19), fill=faixa)
    simbolo(d)
    return im

# 426 / teleporte: pergaminho violeta com espiral.
def espiral(d):
    pts = [(17 * S + (0.5 + t * 0.55) * S * math.cos(t), 17 * S + (0.5 + t * 0.55) * S * math.sin(t)) for t in [i / 40 * 3 * math.pi for i in range(41)]]
    d.line(pts, fill=(200, 240, 255), width=S)
tel = pergaminho((120, 80, 170), (90, 50, 140), espiral)
fim(tel.copy(), 'novos/pergaminho_teleporte.gif'); fim(tel, 'drops/426.gif')

# 420 Amuleto do Berserker Rubro: cordão + gema vermelha facetada.
im, d = quadro((40, 22, 22))
d.arc(P(8, 2, 26, 20), 200, 340, fill=(190, 160, 90), width=S)
d.polygon(P(17, 12, 25, 20, 17, 30, 9, 20), fill=(200, 30, 30), outline=(250, 200, 120))
d.polygon(P(17, 14, 21, 20, 17, 26), fill=(255, 110, 100))
fim(im, 'drops/420.gif', True)
# 421 Selo da Lâmina Faminta: lâmina curva com dentes.
im, d = quadro((32, 26, 30))
d.polygon(P(6, 28, 26, 6, 29, 5, 28, 9, 9, 30), fill=(210, 215, 225), outline=(90, 90, 100))
for k in range(4): d.polygon(P(10 + 4 * k, 26 - 4 * k, 13 + 4 * k, 27 - 4 * k, 12 + 4 * k, 24 - 4 * k), fill=(170, 20, 40))
d.ellipse(P(4, 26, 10, 32), fill=(140, 20, 30))
fim(im, 'drops/421.gif', True)
# 422 Pluma do Vento Inquieto: pena verde-água.
im, d = quadro((20, 40, 40))
d.line(P(8, 29, 26, 5), fill=(230, 230, 210), width=S)
for k in range(9):
    x, y = 9 + 2 * k, 27 - 2.6 * k
    d.line(P(x, y, x - 6 + k * 0.3, y - 3)); d.line(P(x, y, x + 4, y + 4 - k * 0.2))
d.polygon(P(26, 5, 14, 12, 10, 24, 20, 20), fill=(60, 200, 180))
d.line(P(8, 29, 26, 5), fill=(240, 250, 240), width=S)
fim(im, 'drops/422.gif', True)
# 423 Coração de Rocha Fria: coração de pedra azul com rachaduras.
im, d = quadro((22, 28, 40))
d.ellipse(P(6, 7, 18, 19), fill=(60, 90, 150)); d.ellipse(P(16, 7, 28, 19), fill=(60, 90, 150))
d.polygon(P(6, 14, 28, 14, 17, 29), fill=(60, 90, 150))
d.line(P(12, 10, 16, 16, 13, 21, 18, 25), fill=(170, 220, 255), width=S)
d.line(P(22, 11, 19, 17, 23, 19), fill=(170, 220, 255), width=S)
fim(im, 'drops/423.gif', True)
# 424 Olho do Asceta Faminto: olho dourado.
im, d = quadro((40, 32, 18))
d.polygon(P(4, 17, 11, 10, 23, 10, 30, 17, 23, 24, 11, 24), fill=(240, 220, 170), outline=(120, 80, 20))
d.ellipse(P(12, 11, 22, 23), fill=(230, 150, 20)); d.ellipse(P(15, 14, 19, 20), fill=(20, 10, 0))
d.ellipse(P(16, 14, 18, 16), fill=(255, 255, 255))
fim(im, 'drops/424.gif', True)
# 425 Brasão do Mercador Sombrio: escudo escuro com moeda.
im, d = quadro((26, 24, 20))
d.polygon(P(7, 5, 27, 5, 27, 17, 17, 30, 7, 17), fill=(60, 50, 70), outline=(200, 170, 80))
d.ellipse(P(12, 9, 22, 19), fill=(220, 180, 60), outline=(120, 90, 20)); d.rectangle(P(16, 12, 18, 16), fill=(60, 50, 70))
fim(im, 'drops/425.gif', True)

# 427-430: pergaminhos dos jutsus novos.
def vento(d):
    for y in (11, 17, 23): d.arc(P(11, y - 3, 23, y + 3), 180, 360, fill=(40, 110, 60), width=S)
fim(pergaminho((200, 230, 190), (80, 160, 90), vento), 'drops/427.gif')
for (i, n) in [(428, 1), (429, 2), (430, 3)]:
    def caixas(d, n=n):
        for k in range(n):
            x = 17 - 3 * n + 6 * k + 1
            d.rectangle(P(x, 14, x + 4, 20), fill=(150, 100, 50), outline=(60, 35, 10))
    fim(pergaminho((230, 215, 175), (190, 160, 110), caixas), 'drops/%d.gif' % i)
print('ok')
