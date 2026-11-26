#!/usr/bin/env python3
"""Gera images/terreno_vulcanico.jpg (155x155, emendável): basalto escuro com rachaduras de lava.
Base para tools/gerar_tiles_terreno.py. Uso: python3 gerar_textura_vulcanica.py PASTA_DO_JOGO. Só Pillow + numpy."""
import sys, os
import numpy as np
from PIL import Image, ImageDraw, ImageFilter
JOGO = sys.argv[1] if len(sys.argv) > 1 else 'game'
T = 155
r = np.random.default_rng(66)
def ruido(c, amp):
    g = r.random((c, c)); g = np.tile(g, (3, 3))                       # repetido: emenda nas bordas
    im = Image.fromarray((g * 255).astype(np.uint8)).resize((T * 3, T * 3), Image.BICUBIC)
    return (np.asarray(im).astype(float)[T:2 * T, T:2 * T] / 255 - 0.5) * 2 * amp
base = np.array([72, 58, 54], float)
lum = 1 + ruido(6, 0.18) + ruido(20, 0.10) + ruido(60, 0.07)
img = base[None, None] * lum[..., None]
pil = Image.fromarray(np.clip(img, 0, 255).astype(np.uint8))
lava = Image.new('L', (T * 3, T * 3), 0); d = ImageDraw.Draw(lava)
for _ in range(9):                                                      # rachaduras (desenhadas 3x3 para emendar)
    x, y = r.uniform(0, T), r.uniform(0, T); ang = r.uniform(0, 2 * np.pi); pts = []
    for _ in range(int(r.integers(6, 14))):
        pts.append((x, y)); ang += r.normal(0, 0.6); x += np.cos(ang) * 7; y += np.sin(ang) * 7
    for ox in (0, T, 2 * T):
        for oy in (0, T, 2 * T):
            d.line([(px + ox, py + oy) for px, py in pts], fill=255, width=2)
lava = np.asarray(lava.crop((T, T, 2 * T, 2 * T))).astype(float) / 255
brilho = np.asarray(Image.fromarray((lava * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(2.5))).astype(float) / 255
a = np.asarray(pil).astype(float)
a = a * (1 - np.clip(brilho * 1.4, 0, 0.6)[..., None]) + np.array([190, 60, 20]) * np.clip(brilho * 1.4, 0, 0.6)[..., None]
a = a * (1 - lava[..., None]) + np.array([245, 130, 40]) * lava[..., None]
Image.fromarray(np.clip(a, 0, 255).astype(np.uint8)).save(os.path.join(JOGO, 'images', 'terreno_vulcanico.jpg'), quality=92)
print('ok')
