"""Gera a neblina de guerra do mapa global (images/neblina_mapa.png, 420x420 = 20x20 células de 21 px do showmap)
e o marcador de vila (images/vila_marcador.png). Pergaminho escuro com nuvens suaves, no tom do jogo.
  python3 gerar_neblina.py PASTA_IMAGES"""
import sys, numpy as np
from PIL import Image, ImageFilter, ImageDraw
pasta = sys.argv[1] if len(sys.argv) > 1 else 'images'
rng = np.random.default_rng(7)
N = 420
def ruido(escala):  # ruído suave: grade pequena aleatória ampliada com bicúbico
    p = Image.fromarray((rng.random((N // escala + 2, N // escala + 2)) * 255).astype('uint8'))
    return np.asarray(p.resize((N + 2 * escala, N + 2 * escala), Image.BICUBIC))[escala:escala + N, escala:escala + N] / 255.0
n = 0.5 * ruido(60) + 0.3 * ruido(20) + 0.2 * ruido(6)
n = (n - n.min()) / (n.max() - n.min())
escuro = np.array([52, 36, 20]); claro = np.array([128, 104, 72])  # marrom do pergaminho velho
img = escuro + (claro - escuro) * (n[..., None] ** 1.4)
graos = rng.normal(0, 6, (N, N, 1))                                  # textura de papel
img = np.clip(img + graos, 0, 255).astype('uint8')
im = Image.fromarray(img).filter(ImageFilter.GaussianBlur(0.6))
im.save(pasta + '/neblina_mapa.png', optimize=True)
m = Image.new('RGBA', (11, 11), (0, 0, 0, 0)); g = ImageDraw.Draw(m)  # marcador de vila: losango vermelho com borda escura
g.polygon([(5, 0), (10, 5), (5, 10), (0, 5)], fill=(40, 20, 10, 255)); g.polygon([(5, 2), (8, 5), (5, 8), (2, 5)], fill=(214, 40, 30, 255))
m.save(pasta + '/vila_marcador.png')
print('ok')
