"""Redesenha os ícones 24x24 dos treinos/olhos (images/24/treino_*.gif) no padrão dos outros ícones do jogo:
objeto em pixel art, sem disco de fundo, sombreado simples e contorno escuro de 1 px. Arte original (PIL)."""
import sys
from PIL import Image, ImageDraw, ImageFilter
S = 8; N = 24 * S
CONT = (26, 15, 5, 255)

def novo(): return Image.new('RGBA', (N, N), (0, 0, 0, 0))

def finalizar(im, nome):
    # Mesmo tamanho e centro dos ícones antigos (ex.: pousada.gif ocupa ~16x16 com centro em 12,12):
    # recorta o desenho, cabe numa caixa de 17x17 e centraliza no quadro 24x24 (sobra 1 px para o contorno).
    caixa = im.getbbox(); im = im.crop(caixa)
    esc = 15.0 / max(im.width, im.height)
    im = im.resize((max(1, round(im.width * esc)), max(1, round(im.height * esc))), Image.LANCZOS)
    tela = Image.new('RGBA', (24, 24), (0, 0, 0, 0)); tela.alpha_composite(im, ((24 - im.width) // 2, (25 - im.height) // 2)); im = tela
    a = im.split()[3].point(lambda v: 255 if v > 110 else 0)
    im.putalpha(a)
    # contorno de 1 px: dilata o alfa e pinta o anel por baixo
    borda = a.filter(ImageFilter.MaxFilter(3))
    base = Image.new('RGBA', (24, 24), (0, 0, 0, 0)); base.paste(CONT, (0, 0), borda)
    base.alpha_composite(im)
    # GIF com transparência
    p = base.convert('RGBA'); fundo = Image.new('RGBA', p.size, (255, 0, 255, 255)); fundo.alpha_composite(p)
    q = fundo.convert('RGB').quantize(colors=63, method=Image.MEDIANCUT)
    pal = q.getpalette()[:189] + [255, 0, 255]; q.putpalette(pal + [0] * (768 - len(pal)))
    px = q.load(); al = p.split()[3].load()
    for y in range(24):
        for x in range(24):
            if al[x, y] < 128: px[x, y] = 63
    q.save(sys.argv[1] + '/' + nome, transparency=63)

def olho(nome, iris, pupila=(20, 10, 10), extra=None):
    im = novo(); d = ImageDraw.Draw(im)
    d.ellipse((1*S, 6*S, 23*S, 18*S), fill=(245, 240, 225, 255))
    d.ellipse((7*S, 6*S, 17*S, 18*S), fill=iris)
    d.ellipse((9*S, 8*S, 15*S, 14*S), fill=tuple(min(255, c + 45) for c in iris[:3]) + (255,))
    d.ellipse((10*S, 10*S, 14*S, 14*S), fill=pupila + (255,))
    if extra: extra(d)
    d.ellipse((11*S, 8*S, 13*S, 10*S), fill=(255, 255, 255, 255))
    finalizar(im, nome)

def tomoe(d):
    for cx, cy in ((9.5, 9.5), (14.5, 9.5), (12, 15)): d.ellipse(((cx-1)*S, (cy-1)*S, (cx+1)*S, (cy+1)*S), fill=(20, 10, 10, 255))
def aneis(d):
    for r in (4.5, 3.2): d.ellipse(((12-r)*S, (12-r)*S, (12+r)*S, (12+r)*S), outline=(40, 20, 60, 255), width=S//2)

olho('treino_olho_falcao.gif', (200, 140, 40, 255))
olho('treino_olho_sharingan.gif', (190, 20, 20, 255), extra=tomoe)
olho('treino_olho_byakugan.gif', (215, 205, 235, 255), pupila=(200, 190, 225))
olho('treino_olho_celeste.gif', (70, 140, 230, 255))
olho('treino_olho_rinnegan.gif', (160, 120, 210, 255), pupila=(40, 20, 60), extra=aneis)

im = novo(); d = ImageDraw.Draw(im)                       # evolução do sharingan: olho + seta
d.ellipse((1*S, 7*S, 19*S, 19*S), fill=(245, 240, 225, 255)); d.ellipse((6*S, 7*S, 14*S, 19*S), fill=(190, 20, 20, 255))
d.ellipse((8.5*S, 11.5*S, 11.5*S, 14.5*S), fill=(20, 10, 10, 255))
d.polygon([(19*S, 1*S), (23.5*S, 6*S), (21*S, 6*S), (21*S, 11*S), (17*S, 11*S), (17*S, 6*S), (14.5*S, 6*S)], fill=(250, 200, 40, 255))
finalizar(im, 'treino_sharingan_evo.gif')

im = novo(); d = ImageDraw.Draw(im)                       # controle de chakra: orbe azul em espiral
d.ellipse((3*S, 3*S, 21*S, 21*S), fill=(40, 110, 220, 255)); d.ellipse((6*S, 5*S, 16*S, 15*S), fill=(110, 180, 250, 255))
d.arc((7*S, 7*S, 17*S, 17*S), 0, 300, fill=(230, 245, 255, 255), width=2*S); d.ellipse((7*S, 5*S, 10*S, 8*S), fill=(255, 255, 255, 255))
finalizar(im, 'treino_chakra.gif')

im = novo(); d = ImageDraw.Draw(im)                       # domínio de elemento: três orbes
for (x, y), c in (((7, 7), (220, 50, 40)), ((17, 7), (50, 120, 230)), ((12, 16), (60, 170, 60))):
    d.ellipse(((x-5)*S, (y-5)*S, (x+5)*S, (y+5)*S), fill=c + (255,)); d.ellipse(((x-3)*S, (y-3.5)*S, (x-0.5)*S, (y-1)*S), fill=(255, 255, 255, 230))
finalizar(im, 'treino_elemento.gif')

im = novo(); d = ImageDraw.Draw(im)                       # corpo de ferro: escudo de metal
d.polygon([(4*S, 3*S), (20*S, 3*S), (20*S, 12*S), (12*S, 22*S), (4*S, 12*S)], fill=(150, 155, 165, 255))
d.polygon([(4*S, 3*S), (12*S, 3*S), (12*S, 22*S), (4*S, 12*S)], fill=(190, 195, 205, 255))
d.rectangle((11*S, 6*S, 13*S, 18*S), fill=(110, 110, 120, 255)); d.rectangle((7*S, 9*S, 17*S, 11*S), fill=(110, 110, 120, 255))
finalizar(im, 'treino_ferro.gif')

im = novo(); d = ImageDraw.Draw(im)                       # caminhada ninja: sandália de palha
d.rounded_rectangle((7*S, 2*S, 17*S, 22*S), radius=5*S, fill=(205, 165, 95, 255))
for y in range(5, 21, 3): d.line((8*S, y*S, 16*S, y*S), fill=(160, 120, 60, 255), width=S)
d.line((8*S, 6*S, 12*S, 10*S), fill=(150, 40, 30, 255), width=2*S); d.line((16*S, 6*S, 12*S, 10*S), fill=(150, 40, 30, 255), width=2*S)
finalizar(im, 'treino_caminhada.gif')

im = novo(); d = ImageDraw.Draw(im)                       # meditação natural: folha verde
d.ellipse((4*S, 3*S, 20*S, 21*S), fill=(70, 160, 60, 255)); d.polygon([(12*S, 1*S), (19*S, 8*S), (5*S, 8*S)], fill=(70, 160, 60, 255))
d.line((12*S, 4*S, 12*S, 23*S), fill=(30, 90, 30, 255), width=S); d.ellipse((7*S, 7*S, 11*S, 12*S), fill=(150, 215, 120, 255))
finalizar(im, 'treino_meditacao.gif')

im = novo(); d = ImageDraw.Draw(im)                       # jutsu raro: pergaminho com raio
d.rectangle((4*S, 5*S, 20*S, 19*S), fill=(235, 215, 165, 255)); d.rectangle((3*S, 3*S, 21*S, 6*S), fill=(150, 90, 40, 255)); d.rectangle((3*S, 18*S, 21*S, 21*S), fill=(150, 90, 40, 255))
d.polygon([(13*S, 7*S), (8*S, 13*S), (12*S, 13*S), (10*S, 18*S), (16*S, 11*S), (12*S, 11*S)], fill=(40, 40, 140, 255))
finalizar(im, 'treino_jutsuraro.gif')

im = novo(); d = ImageDraw.Draw(im)                       # especialização: placa de bandana com três pontos de classe
d.rectangle((1*S, 9*S, 23*S, 15*S), fill=(60, 60, 110, 255))
d.rounded_rectangle((5*S, 6*S, 19*S, 18*S), radius=2*S, fill=(180, 185, 195, 255)); d.rectangle((6*S, 7*S, 18*S, 9*S), fill=(215, 220, 228, 255))
for (x, c) in ((8.5, (210, 50, 40)), (12, (60, 160, 60)), (15.5, (60, 110, 220))): d.ellipse(((x-1.6)*S, 10.5*S, (x+1.6)*S, 13.7*S), fill=c + (255,))
finalizar(im, 'especializacao.gif')
