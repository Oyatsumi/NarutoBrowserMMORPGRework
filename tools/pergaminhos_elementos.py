"""Gera layoutnovo/equipamentos/novos/pergaminho_ELEMENTO.gif (34x34): o pergaminho selado com o lacre
e uma faixa na cor do elemento do jutsu que ele ensina."""
from PIL import Image
import colorsys
BASE = "game/layoutnovo/equipamentos/novos/"
CORES = {"neutro": (0.0, 0.0, 0.85), "fogo": (0.06, 0.95, 1.0), "agua": (0.58, 0.85, 1.0), "terra": (0.09, 0.75, 0.62),
         "vento": (0.33, 0.75, 0.85), "raio": (0.14, 0.95, 1.0)}
src = Image.open(BASE + "pergaminho_selado.gif").convert("RGB")
for el, (h, s, v) in CORES.items():
    im = src.copy(); px = im.load()
    for y in range(34):
        for x in range(34):
            r, g, b = [c / 255 for c in px[x, y]]
            hh, ss, vv = colorsys.rgb_to_hsv(r, g, b)
            if ss > 0.45 and (hh < 0.05 or hh > 0.93) and vv > 0.15:  # lacre vermelho
                nr, ng, nb = colorsys.hsv_to_rgb(h, s * min(1, ss * 1.2), min(1, vv * v * 1.35))
                px[x, y] = tuple(int(c * 255) for c in (nr, ng, nb))
            elif vv > 0.55 and ss < 0.45 and el != "neutro":  # papel: leve tom do elemento
                tr, tg, tb = colorsys.hsv_to_rgb(h, 0.9, 1.0)
                px[x, y] = tuple(int(255 * (c * 0.82 + t * 0.18)) for c, t in zip((r, g, b), (tr, tg, tb)))
    im.convert("P", palette=Image.ADAPTIVE, colors=64).save(BASE + "pergaminho_%s.gif" % el)
print("ok")
