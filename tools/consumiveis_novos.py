# consumiveis_novos.py :: gera os ícones 34x34 do Pergaminho de Retorno e do Incenso Repelente
# (layoutnovo/equipamentos/novos/), no estilo dos ícones "novos" (fundo escuro, pixel art com contorno).
import sys
from PIL import Image, ImageDraw
pasta = (sys.argv[1] if len(sys.argv) > 1 else 'game') + '/layoutnovo/equipamentos/novos/'
base = Image.open(pasta + 'pergaminho_neutro.gif').convert('RGB')
fundo = base.getpixel((1, 1))

# 1) Pergaminho de Retorno: o pergaminho neutro com papel azulado, selo azul e uma seta curva de "voltar".
ret = base.copy(); px = ret.load()
for y in range(34):
    for x in range(34):
        r, g, b = px[x, y]
        if r > 120 and r > b + 25:            # papel bege -> papel levemente azulado
            px[x, y] = (int(r * .80), int(g * .90), min(255, int(b * 1.25) + 30))
        elif abs(r - g) < 14 and abs(g - b) < 14 and 110 < r < 230:   # selo cinza -> selo azul
            px[x, y] = (40, 90 + (r - 110) // 2, 200)
d = ImageDraw.Draw(ret)
azul, escuro = (40, 110, 220), (15, 35, 90)
d.arc((10, 8, 22, 20), 200, 360 + 60, fill=escuro, width=3)   # contorno da seta
d.arc((10, 8, 22, 20), 200, 360 + 60, fill=azul, width=1)
d.polygon([(8, 13), (13, 13), (10, 18)], fill=azul, outline=escuro)  # ponta da seta
ret.convert('P', palette=Image.ADAPTIVE, colors=64).save(pasta + 'pergaminho_retorno.gif')

# 2) Incenso Repelente: tigela de barro com três varetas acesas e fumaça roxa.
inc = Image.new('RGB', (34, 34), fundo); d = ImageDraw.Draw(inc)
for (x, y) in [(0, 0)]:
    pass
# borda igual à dos ícones novos (primeira/última linha e coluna do pergaminho)
for i in range(34):
    for (a, b) in [(i, 0), (i, 33), (0, i), (33, i)]:
        inc.putpixel((a, b), base.getpixel((a, b)))
contorno = (20, 12, 8)
d.ellipse((7, 21, 26, 30), fill=(120, 62, 30), outline=contorno)          # tigela
d.rectangle((8, 21, 25, 25), fill=(150, 80, 40))
d.line((8, 21, 25, 21), fill=(190, 120, 70))
d.ellipse((8, 19, 25, 23), fill=(70, 55, 45), outline=contorno)            # cinzas
for x, topo in [(13, 9), (17, 7), (21, 10)]:                               # varetas
    d.line((x, topo, x, 21), fill=(110, 40, 30))
    inc.putpixel((x, topo), (255, 170, 40)); inc.putpixel((x, topo + 1), (230, 90, 20))
roxo, roxoclaro = (140, 90, 200), (190, 150, 235)
for x, y in [(12, 7), (11, 6), (12, 5), (13, 4), (16, 5), (17, 4), (18, 3), (17, 2), (22, 8), (23, 7), (22, 6), (21, 5), (22, 4)]:
    inc.putpixel((x, y), roxo)
for x, y in [(13, 6), (18, 5), (21, 7), (16, 3), (23, 5)]:
    inc.putpixel((x, y), roxoclaro)
inc.convert('P', palette=Image.ADAPTIVE, colors=64).save(pasta + 'incenso_repelente.gif')
print('ok')
