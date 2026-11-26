"""Monta as cartas dos inimigos (295x145) inserindo cada retrato como camada MULTIPLY no monstros.psd,
abaixo das camadas de moldura e respingos (exatamente como as cartas originais foram feitas).
Uso: montar_cartas.py monstros.psd pasta_saida  "id:arquivo.png" ...  |  "id:L035" (camada do PSD)"""
import sys
from psd_tools import PSDImage
from psd_tools.api.layers import PixelLayer
from PIL import Image

psd = PSDImage.open(sys.argv[1]); saida = sys.argv[2]
camadas = list(psd)
retratos = [l for l in camadas[13:106]]       # camadas de retrato (ocultas)
for l in retratos: l.visible = False
base_index = 13
for item in sys.argv[3:]:
    idm, origem = item.split(':', 1)
    if origem.startswith('L') and origem[1:].isdigit():
        camada = camadas[int(origem[1:])]; camada.visible = True
        img = psd.composite(force=True).convert('RGB'); camada.visible = False
    else:
        # Área interna do quadro do retrato medida nas cartas originais: x=6..72, y=37..135 (67x99).
        ret = Image.open(origem).convert('RGB').resize((67, 99), Image.LANCZOS)
        nova = PixelLayer.frompil(ret, psd, 'retrato', 37, 6)   # top, left
        nova.blend_mode = camadas[14].blend_mode
        psd.insert(base_index, nova)
        img = psd.composite(force=True).convert('RGB')
        psd.remove(nova)
    img.save(f"{saida}/{idm}.jpg", quality=92)
print('ok', len(sys.argv) - 3)
