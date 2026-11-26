# selo_viagem.py :: desenha o ícone 34x34 do Selo de Viagem (drop 436), no formato dos ícones de drop/consumível
# (fundo escuro e moldura de 1 px de drops/66.gif): um talismã de papel com um selo vermelho e uma seta de viagem.
# Salva em drops/436.gif e consumiveis/436.gif.
import sys
from PIL import Image, ImageDraw
raiz = (sys.argv[1] if len(sys.argv) > 1 else 'game') + '/layoutnovo/equipamentos/'
base = Image.open(raiz + 'drops/66.gif').convert('RGB')
fundo = base.getpixel((5, 5))
im = Image.new('RGB', (34, 34), fundo)
for i in range(34):
    for (a, b) in [(i, 0), (i, 33), (0, i), (33, i)]:
        im.putpixel((a, b), base.getpixel((a, b)))
d = ImageDraw.Draw(im)
contorno, papel, sombra = (30, 22, 15), (236, 222, 180), (190, 170, 120)
d.rectangle((9, 4, 25, 30), fill=contorno)                 # talismã (ofuda)
d.rectangle((10, 5, 24, 29), fill=papel)
d.rectangle((22, 5, 24, 29), fill=sombra)                  # sombra lateral
d.rectangle((10, 5, 24, 7), fill=(150, 30, 35))            # faixa de cima
d.rectangle((10, 27, 24, 29), fill=(150, 30, 35))          # faixa de baixo
d.ellipse((11, 10, 23, 22), fill=(185, 30, 35))            # selo vermelho
d.ellipse((13, 12, 21, 20), fill=(215, 60, 55))
# seta de viagem (dourada) dentro do selo
ouro = (250, 215, 90)
d.line((14, 18, 20, 13), fill=ouro, width=2)
d.polygon([(21, 12), (17, 12), (21, 16)], fill=ouro)
d.line((12, 24, 22, 24), fill=(90, 60, 40))                # traço de tinta
d.line((13, 25, 19, 25), fill=(90, 60, 40))
out = im.convert('P', palette=Image.ADAPTIVE, colors=64)
out.save(raiz + 'drops/436.gif')
out.save(raiz + 'consumiveis/436.gif')
print('ok')
