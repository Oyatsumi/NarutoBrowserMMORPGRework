"""Transforma retratos quaisquer (PNG/JPG) em cartas de inimigo no estilo do jogo.

Para cada arquivo <id>.png / <id>.jpg da pasta de entrada:
  1. recorta no formato do quadro da carta (68x100, centralizado e puxado para o alto da imagem, onde fica o rosto);
  2. converte para o estilo "tinta sépia" das cartas originais (tons de cinza, contraste, traço reforçado e tom marrom);
  3. monta a carta 295x145 com o monstros.psd (moldura e respingos por cima), usando montar_cartas.py.

Uso: python3 estilizar_cartas.py pasta_dos_retratos monstros.psd pasta_saida
Depois copie <id>.jpg da pasta de saída para game/layoutnovo/monstros/ (ou envie pelo painel: Inimigos e cartas).
Use apenas imagens que você tem direito de usar (arte própria, encomendada ou gerada por você).
"""
import sys, os, subprocess, tempfile
from PIL import Image, ImageOps, ImageFilter, ImageEnhance

LARGURA, ALTURA = 67, 99            # quadro do retrato na carta (x=6..72, y=37..135)
ESCURO, CLARO = (52, 28, 8), (250, 236, 200)   # marrom da tinta e papel envelhecido

def estilizar(origem):
    img = Image.open(origem).convert("RGB")
    # Recorte no formato do quadro, mais perto do topo (rosto/tronco).
    proporcao = LARGURA / ALTURA
    l, a = img.size
    if l / a > proporcao:
        nl = int(a * proporcao); img = img.crop(((l - nl) // 2, 0, (l - nl) // 2 + nl, a))
    else:
        na = int(l / proporcao); topo = int((a - na) * 0.2); img = img.crop((0, topo, l, topo + na))
    # Trabalha em 3x para o traço sair limpo e só reduz no fim.
    img = img.resize((LARGURA * 3, ALTURA * 3), Image.LANCZOS)
    cinza = ImageOps.autocontrast(ImageOps.grayscale(img), cutoff=2)
    bordas = ImageOps.invert(cinza.filter(ImageFilter.FIND_EDGES)).point(lambda v: 255 if v > 200 else v)
    cinza = Image.composite(cinza, bordas, bordas.point(lambda v: 255 if v > 128 else 0))   # reforça o traço
    cinza = ImageEnhance.Contrast(cinza).enhance(1.25)
    sepia = ImageOps.colorize(cinza, black=ESCURO, white=CLARO)
    return sepia.resize((LARGURA, ALTURA), Image.LANCZOS)

def main():
    entrada, psd, saida = sys.argv[1:4]
    os.makedirs(saida, exist_ok=True)
    temp = tempfile.mkdtemp()
    itens = []
    for nome in sorted(os.listdir(entrada)):
        idm, ext = os.path.splitext(nome)
        if not idm.isdigit() or ext.lower() not in (".png", ".jpg", ".jpeg", ".webp"): continue
        destino = os.path.join(temp, idm + ".png")
        estilizar(os.path.join(entrada, nome)).save(destino)
        itens.append(f"{idm}:{destino}")
    if not itens: sys.exit("Nenhum arquivo <id>.png/.jpg encontrado em " + entrada)
    subprocess.check_call([sys.executable, os.path.join(os.path.dirname(os.path.abspath(__file__)), "montar_cartas.py"), psd, saida] + itens)

if __name__ == "__main__":
    main()
