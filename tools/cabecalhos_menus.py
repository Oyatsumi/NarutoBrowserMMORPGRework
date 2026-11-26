# Gera os cabeçalhos grandes dos menus laterais (layoutnovo/buttons/*.png e en/*.png) no padrão dos originais:
# o PSD do layout (psds/interface/layout_sasuke.psd) usa a fonte Impact; aqui usamos Anton (livre, mesma forma).
# Para cada imagem: mede a letra original (cor, altura, lado), apaga o texto copiando o fundo ao lado e escreve o novo.
from PIL import Image, ImageDraw, ImageFont
import statistics
B = "/home/claude/work/game/layoutnovo/buttons/"
FONTE = ImageFont.truetype("/home/claude/tools/base/anton-latin-400-normal.woff", 60)
ACENTO = "ÁÉÍÓÚÃÕÂÊÔÇ"

def letra(p): return p[3] > 0 and sum(p[:3]) > 450

def gerar(orig, texto, saida, acento_orig):
    base = Image.open(orig).convert("RGBA"); px = base.load(); W = base.width
    # todos os títulos originais têm a maiúscula nas linhas 2..25 (24 px); acento acima disso
    pts = [(x, y) for x in range(W) for y in range(0, 26) if letra(px[x, y])]
    x0 = min(p[0] for p in pts); x1 = max(p[0] for p in pts) + 1
    y1 = 26
    cor = tuple(int(statistics.median(px[x, y][i] for x, y in pts)) for i in range(3)) + (255,)
    cap = 24
    direita = x0 > W - x1                                # texto encostado na direita?
    im = base.copy()
    # apaga o texto: copia a faixa de fundo do lado vazio por cima (só a parte de cima, onde fica o título)
    if direita: tira = base.crop((2, 0, max(30, x0 - 2), y1 + 1))
    else: tira = base.crop((min(W - 30, x1 + 2), 0, W - 2, y1 + 1))
    x = x0 - 2
    while x < x1 + 2: im.paste(tira, (x, 0)); x += tira.width
    # texto novo na mesma altura de maiúscula
    t = Image.new("L", (900, 120), 0); ImageDraw.Draw(t).text((10, 10), texto, font=FONTE, fill=255)
    bb = t.getbbox(); t = t.crop(bb)
    tem = any(c in ACENTO for c in texto)
    capf = t.height * (0.87 if tem else 1.0)
    esc = cap / capf
    t = t.resize((max(1, round(t.width * esc * 1.08)), max(1, round(t.height * esc))), Image.LANCZOS)
    maxw = W - 6
    if t.width > maxw: t = t.resize((maxw, t.height), Image.LANCZOS)  # nomes longos: comprime na largura
    yb = y1  # base das letras igual à original
    pos = ((x1 - t.width) if direita else x0, yb - t.height)
    im.paste(Image.new("RGBA", t.size, (25, 12, 5, 255)), (pos[0] + 1, pos[1] + 1), t)
    im.paste(Image.new("RGBA", t.size, cor), pos, t)
    im.save(saida)

# (arquivo pt, texto pt, texto en, pt tem acento)
MENUS = [("historico", "MISSÕES", "MISSIONS", True, "missoes")]
for orig, pt, en, ac, novo in MENUS:
    gerar(B + orig + ".png", pt, B + novo + ".png", ac)
# Versões em inglês de todos os títulos grandes, a partir do original em português
EN = [("coordenada", "MAP", False), ("historico", "HISTORY", True), ("inventario", "EQUIPMENT", False),
      ("local", "COMMANDS", False), ("mochila", "BACKPACK", False), ("personagem", "CHARACTER", False)]
for orig, en, ac in EN:
    gerar(B + orig + ".png", en, B + "en/" + orig + ".png", ac)
gerar(B + "historico.png", "MISSIONS", B + "en/missoes.png", True)
