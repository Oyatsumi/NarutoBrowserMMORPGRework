"""Gera os ícones das VARIANTES raras dos itens do Naruto RPG (Pillow + numpy).

Para cada ícone 34x34 de layoutnovo/equipamentos/<id>.gif (armas, coletes, bandanas, inclusive as
segundas imagens <id>d.gif) e layoutnovo/equipamentos/drops/<id>.gif, cria 6 versões com UM efeito cada:
    dourado  recolorido em ouro + brilhos (pixels de estrela)
    brilho   aura branca/ciano suave em volta da silhueta + estrela piscando
    raio     pequenos raios/arcos elétricos azul-branco em volta da silhueta
    chama    aura de fogo laranja subindo pelas bordas
    gelo     tom azul gelado + cristais de gelo
    sombra   aura roxa/preta em volta do item
Saída: layoutnovo/equipamentos/variantes/<efeito>/<id>.gif e .../variantes/<efeito>/drops/<id>.gif
Uso: python3 variantes_itens.py /caminho/do/jogo [/caminho/previa.png]
A silhueta do item é separada do fundo escuro do quadrinho por "inundação" a partir da borda: a cor do
fundo é a cor mais comum da borda do ícone (os ícones novos usam ~#1e1f1f; os antigos podem variar).
"""
import sys, os, glob, random
import numpy as np
from PIL import Image

EFEITOS = ["dourado", "brilho", "raio", "chama", "gelo", "sombra"]


def carregar(caminho):
    im = Image.open(caminho).convert("RGB")
    return np.asarray(im).astype(np.float32)


def corfundo(a):
    # cantos (3x3) do quadrinho: a borda inteira não serve, há itens que encostam nela com contorno preto.
    borda = np.concatenate([a[:3, :3].reshape(-1, 3), a[:3, -3:].reshape(-1, 3),
                            a[-3:, :3].reshape(-1, 3), a[-3:, -3:].reshape(-1, 3)]).astype(int)
    cores, contagem = np.unique(borda, axis=0, return_counts=True)
    return cores[contagem.argmax()].astype(np.float32)


def silhueta(a):
    """True = item. Inunda a partir da borda tudo que for parecido com a cor do fundo."""
    h, w, _ = a.shape
    bg = corfundo(a)
    perto = np.abs(a - bg).sum(axis=2) < 30
    fundo = np.zeros((h, w), bool)
    pilha = [(y, x) for y in range(h) for x in (0, w - 1)] + [(y, x) for x in range(w) for y in (0, h - 1)]
    while pilha:
        y, x = pilha.pop()
        if fundo[y, x] or not perto[y, x]:
            continue
        fundo[y, x] = True
        for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            ny, nx = y + dy, x + dx
            if 0 <= ny < h and 0 <= nx < w and not fundo[ny, nx]:
                pilha.append((ny, nx))
    item = ~fundo
    # buracos fechados (ex.: o meio de um colar) com a cor do fundo também são fundo, se forem grandes.
    quase = (np.abs(a - bg).sum(axis=2) < 10) & item
    visto = np.zeros((h, w), bool)
    for y0 in range(h):
        for x0 in range(w):
            if quase[y0, x0] and not visto[y0, x0]:
                comp, pilha = [], [(y0, x0)]
                visto[y0, x0] = True
                while pilha:
                    y, x = pilha.pop()
                    comp.append((y, x))
                    for dy, dx in ((1, 0), (-1, 0), (0, 1), (0, -1)):
                        ny, nx = y + dy, x + dx
                        if 0 <= ny < h and 0 <= nx < w and quase[ny, nx] and not visto[ny, nx]:
                            visto[ny, nx] = True
                            pilha.append((ny, nx))
                if len(comp) >= 12:
                    for y, x in comp:
                        item[y, x] = False
    # a moldura de 1px do quadrinho nunca é item.
    item[0, :] = item[-1, :] = item[:, 0] = item[:, -1] = False
    return item, bg


def dilatar(m, n=1):
    for _ in range(n):
        p = np.pad(m, 1)
        m = p[1:-1, 1:-1] | p[:-2, 1:-1] | p[2:, 1:-1] | p[1:-1, :-2] | p[1:-1, 2:]
    return m


def dilatar8(m):
    p = np.pad(m, 1)
    r = m.copy()
    for dy in (-1, 0, 1):
        for dx in (-1, 0, 1):
            r |= p[1 + dy:35 + dy - 1 + 1, 1 + dx:35 + dx - 1 + 1][:m.shape[0], :m.shape[1]]
    return r


def aneis(item, n):
    """Lista de máscaras: anel 1 (colado no item), anel 2... até n (só pixels de fundo)."""
    r, ant = [], item
    for _ in range(n):
        nova = dilatar(ant)
        r.append(nova & ~ant)
        ant = nova
    return r


def misturar(a, mask, cor, alpha):
    cor = np.array(cor, np.float32)
    al = (mask.astype(np.float32) * alpha)[..., None]
    return a * (1 - al) + cor * al


def lum(a):
    return (a[..., 0] * 0.3 + a[..., 1] * 0.59 + a[..., 2] * 0.11) / 255.0


def mapa(l, paradas):
    """Mapa de gradiente: luminância 0..1 -> cor, por paradas [(pos, (r,g,b)), ...]."""
    pos = np.array([p for p, _ in paradas])
    cs = np.array([c for _, c in paradas], np.float32)
    out = np.zeros(l.shape + (3,), np.float32)
    for k in range(3):
        out[..., k] = np.interp(l, pos, cs[:, k])
    return out


def limitar(a):
    a[0, :] = a[0, :]
    return np.clip(a, 0, 255)


def estrela(a, y, x, nucleo, braco, tam=1):
    h, w, _ = a.shape
    def px(yy, xx, c, al=1.0):
        if 1 <= yy < h - 1 and 1 <= xx < w - 1:
            a[yy, xx] = a[yy, xx] * (1 - al) + np.array(c, np.float32) * al
    for d in range(1, tam + 1):
        al = 1.0 if d < tam else 0.6
        for dy, dx in ((d, 0), (-d, 0), (0, d), (0, -d)):
            px(y + dy, x + dx, braco, al)
    px(y, x, nucleo)


def pontosborda(item, rng, n, longe=1, preferir=None):
    """Escolhe n pontos de fundo a 'longe' px do item, espalhados (distância mínima entre eles)."""
    anel = aneis(item, longe)[-1]
    anel[:2, :] = anel[-2:, :] = False
    anel[:, :2] = anel[:, -2:] = False
    ys, xs = np.nonzero(anel)
    if len(ys) == 0:
        return []
    idx = list(range(len(ys)))
    if preferir == "cima":
        idx.sort(key=lambda i: ys[i] + rng.random() * 8)
    else:
        rng.shuffle(idx)
    esc = []
    for i in idx:
        if all(abs(ys[i] - y) + abs(xs[i] - x) >= 9 for y, x in esc):
            esc.append((int(ys[i]), int(xs[i])))
        if len(esc) >= n:
            break
    return esc


# ---------------------------------------------------------------- efeitos

def ef_dourado(a, item, bg, rng):
    l = lum(a)
    # estica o contraste da luminância do item para o ouro ficar vivo mesmo em itens escuros.
    li = l[item]
    lo, hi = (np.percentile(li, 3), np.percentile(li, 97)) if li.size else (0, 1)
    l2 = np.clip((l - lo) / max(0.05, hi - lo), 0, 1) * 0.9 + 0.05
    ouro = mapa(l2, [(0, (70, 38, 6)), (0.3, (150, 92, 14)), (0.55, (222, 160, 30)),
                     (0.8, (255, 216, 90)), (1.0, (255, 250, 210))])
    out = a.copy()
    out[item] = ouro[item] * 0.92 + a[item] * 0.08
    # brilho fraco de 1px só (um contorno forte fazia o item parecer maior/deslocado no quadrinho da mochila).
    r = aneis(item, 1)
    out = misturar(out, r[0], (255, 200, 60), 0.18)
    for y, x in pontosborda(item, rng, 3, 1, "cima"):
        estrela(out, y, x, (255, 255, 240), (255, 220, 90), 2)
    # pequenos brilhos de 1px em cima do item.
    ys, xs = np.nonzero(item & (l2 > 0.7))
    for k in rng.sample(range(len(ys)), min(3, len(ys))):
        out[ys[k], xs[k]] = (255, 255, 235)
    return out


def ef_brilho(a, item, bg, rng):
    out = a.copy()
    out[item] = np.clip(a[item] * 1.08 + 14, 0, 255)
    r = aneis(item, 4)
    out = misturar(out, r[0], (235, 255, 255), 0.70)
    out = misturar(out, r[1], (150, 235, 255), 0.45)
    out = misturar(out, r[2], (90, 190, 240), 0.25)
    out = misturar(out, r[3], (70, 150, 210), 0.10)
    pts = pontosborda(item, rng, 2, 2, "cima")
    for i, (y, x) in enumerate(pts):
        estrela(out, y, x, (255, 255, 255), (170, 240, 255), 2 if i == 0 else 1)
    return out


def raio(out, y, x, passos, rng, item, dirx, diry):
    """Desenha um raio em zigue-zague (núcleo branco-azulado + halo azul)."""
    caminho = []
    for _ in range(passos):
        caminho.append((y, x))
        if rng.random() < 0.55:
            x += dirx
        else:
            y += diry
        if rng.random() < 0.3:
            x += rng.choice((-1, 1)) if diry else 0
            y += rng.choice((-1, 1)) if dirx else 0
        if not (1 <= y < 33 and 1 <= x < 33):
            break
    halo = np.zeros(item.shape, bool)
    for yy, xx in caminho:
        halo[yy, xx] = True
    halo = dilatar(halo) & ~item
    out[:] = misturar(out, halo, (70, 140, 255), 0.65)
    for yy, xx in caminho:
        if not item[yy, xx]:
            out[yy, xx] = (225, 240, 255)


def ef_raio(a, item, bg, rng):
    out = a.copy()
    l = lum(a)
    azul = mapa(l, [(0, (10, 20, 60)), (0.5, (90, 140, 230)), (1, (230, 245, 255))])
    out[item] = a[item] * 0.75 + azul[item] * 0.25
    r = aneis(item, 2)
    out = misturar(out, r[0], (120, 180, 255), 0.45)
    out = misturar(out, r[1], (60, 110, 230), 0.18)
    ys, xs = np.nonzero(item)
    cy, cx = (ys.mean(), xs.mean()) if len(ys) else (17, 17)
    for y, x in pontosborda(item, rng, 4, 1):
        dirx = 1 if x >= cx else -1
        diry = 1 if y >= cy else -1
        raio(out, y, x, rng.randint(6, 9), rng, item, dirx, diry)
    # faíscas soltas.
    for y, x in pontosborda(item, rng, 3, 3):
        out[y, x] = (240, 250, 255)
    return out


def ef_chama(a, item, bg, rng):
    out = a.copy()
    l = lum(a)
    quente = mapa(l, [(0, (60, 10, 0)), (0.5, (230, 110, 30)), (1, (255, 240, 170))])
    out[item] = a[item] * 0.72 + quente[item] * 0.28
    h, w = item.shape
    # intensidade do fogo: cada coluna sobe a partir das bordas do item com altura aleatória.
    inten = np.zeros((h, w), np.float32)
    base = dilatar(item)
    for x in range(w):
        alt = rng.uniform(3, 7)
        for y in range(h):
            if base[y, x]:
                for k in range(0, int(alt) + 1):
                    yy = y - k
                    if yy < 1:
                        break
                    inten[yy, x] = max(inten[yy, x], 1 - k / (alt + 1))
    lado = aneis(item, 2)
    inten = np.maximum(inten, lado[0] * 0.75)
    inten = np.maximum(inten, lado[1] * 0.35)
    # chamas um pouco "rasgadas": ruído.
    ruido = np.array([[rng.random() for _ in range(w)] for _ in range(h)], np.float32)
    inten = inten * (0.7 + 0.5 * ruido)
    inten[item] = 0
    inten = np.clip(inten, 0, 1)
    cor = mapa(inten, [(0, (120, 20, 0)), (0.35, (220, 60, 10)), (0.65, (255, 150, 30)), (1, (255, 240, 140))])
    al = np.clip(inten * 1.15, 0, 1)[..., None]
    out = out * (1 - al) + cor * al
    for y, x in pontosborda(item, rng, 3, 4, "cima"):
        out[y, x] = (255, 200, 80)
    return out


def ef_gelo(a, item, bg, rng):
    out = a.copy()
    l = lum(a)
    li = l[item]
    lo, hi = (np.percentile(li, 3), np.percentile(li, 97)) if li.size else (0, 1)
    l2 = np.clip((l - lo) / max(0.05, hi - lo), 0, 1) * 0.85 + 0.12
    gelo = mapa(l2, [(0, (20, 45, 95)), (0.4, (70, 140, 210)), (0.75, (160, 220, 250)), (1, (240, 252, 255))])
    out[item] = a[item] * 0.3 + gelo[item] * 0.7
    # geada nas bordas de cima do item.
    acima = np.zeros_like(item)
    acima[1:] = item[1:] & ~item[:-1]
    out[acima] = out[acima] * 0.3 + np.array((235, 250, 255), np.float32) * 0.7
    r = aneis(item, 2)
    out = misturar(out, r[0], (150, 210, 255), 0.35)
    out = misturar(out, r[1], (100, 170, 230), 0.12)
    # cristais de gelo (flocos em X e +).
    for i, (y, x) in enumerate(pontosborda(item, rng, 4, 2)):
        if i % 2 == 0:
            estrela(out, y, x, (255, 255, 255), (170, 225, 255), 1)
            for dy, dx in ((1, 1), (-1, -1), (1, -1), (-1, 1)):
                yy, xx = y + dy, x + dx
                if 1 <= yy < 33 and 1 <= xx < 33:
                    out[yy, xx] = out[yy, xx] * 0.5 + np.array((120, 190, 240)) * 0.5
        else:
            estrela(out, y, x, (230, 248, 255), (130, 200, 245), 1)
    return out


def ef_sombra(a, item, bg, rng):
    out = a.copy()
    l = lum(a)
    roxo = mapa(l, [(0, (15, 0, 25)), (0.5, (110, 50, 150)), (1, (230, 190, 255))])
    out[item] = a[item] * 0.6 + roxo[item] * 0.4
    r = aneis(item, 4)
    h, w = item.shape
    ruido = np.array([[rng.random() for _ in range(w)] for _ in range(h)], np.float32)
    out = misturar(out, r[0], (190, 90, 255), 0.75)
    out = misturar(out, r[1], (100, 30, 160), 0.6 * (0.6 + 0.6 * ruido))
    out = misturar(out, r[2], (60, 10, 100), 0.45 * (0.4 + 0.8 * ruido))
    out = misturar(out, r[3], (25, 0, 45), 0.35 * ruido)
    # fiapos de fumaça subindo.
    for y, x in pontosborda(item, rng, 3, 2, "cima"):
        for k in range(rng.randint(2, 4)):
            yy, xx = y - k, x + (k % 2) * rng.choice((-1, 0, 1))
            if 1 <= yy < 33 and 1 <= xx < 33 and not item[yy, xx]:
                out[yy, xx] = out[yy, xx] * 0.3 + np.array((150, 70, 210)) * 0.7
    return out


FUNCOES = {"dourado": ef_dourado, "brilho": ef_brilho, "raio": ef_raio,
           "chama": ef_chama, "gelo": ef_gelo, "sombra": ef_sombra}


def variante(a, efeito, semente):
    item, bg = silhueta(a)
    rng = random.Random(semente * 7 + EFEITOS.index(efeito))
    if item.sum() == 0:
        return a.copy()
    out = FUNCOES[efeito](a, item, bg, rng)
    # a moldura do quadrinho continua igual à do original.
    out[0, :], out[-1, :], out[:, 0], out[:, -1] = a[0, :], a[-1, :], a[:, 0], a[:, -1]
    return np.clip(out, 0, 255)


def salvargif(arr, caminho):
    im = Image.fromarray(arr.round().astype(np.uint8), "RGB")
    im.quantize(colors=255, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE).save(caminho, optimize=True)


def main():
    jogo = sys.argv[1] if len(sys.argv) > 1 else "game"
    previa = sys.argv[2] if len(sys.argv) > 2 else "arte/variantes_previa.png"
    base = os.path.join(jogo, "layoutnovo/equipamentos")
    fontes = [(f, "") for f in glob.glob(base + "/*.gif")] + [(f, "drops/") for f in glob.glob(base + "/drops/*.gif")]
    fontes = [(f, p) for f, p in fontes if os.path.basename(f)[:-4].rstrip("d").isdigit() and os.path.basename(f) != "0.gif"]
    total = 0
    for f, pasta in sorted(fontes):
        a = carregar(f)
        if a.shape[:2] != (34, 34):
            continue
        nome = os.path.basename(f)
        semente = sum(ord(c) for c in pasta + nome)
        if os.environ.get("FALTANDO") and all(os.path.exists(os.path.join(base, "variantes", ef, pasta, nome)) for ef in EFEITOS):
            continue  # FALTANDO=1: só gera os ícones que ainda não existem (não mexe nos antigos)
        for ef in EFEITOS:
            destino = os.path.join(base, "variantes", ef, pasta)
            os.makedirs(destino, exist_ok=True)
            salvargif(variante(a, ef, semente), os.path.join(destino, nome))
            total += 1
    print("ícones gerados:", total)

    # Prévia: original + 6 variantes de itens representativos, ampliados 3x (vizinho mais próximo).
    exemplos = ["1.gif", "3.gif", "8.gif", "11.gif", "20.gif", "38.gif", "drops/2.gif", "drops/4.gif",
                "drops/11.gif", "drops/24.gif", "drops/29.gif", "drops/43.gif"]
    exemplos = [e for e in exemplos if os.path.exists(os.path.join(base, e))]
    z, m = 3, 4
    folha = Image.new("RGB", (7 * (34 * z + m) + m, len(exemplos) * (34 * z + m) + m), (245, 236, 205))
    for i, e in enumerate(exemplos):
        a = carregar(os.path.join(base, e))
        pasta = "drops/" if e.startswith("drops/") else ""
        ims = [os.path.join(base, e)] + [os.path.join(base, "variantes", ef, pasta, os.path.basename(e)) for ef in EFEITOS]
        for j, c in enumerate(ims):
            im = Image.open(c).convert("RGB").resize((34 * z, 34 * z), Image.NEAREST)
            folha.paste(im, (m + j * (34 * z + m), m + i * (34 * z + m)))
    os.makedirs(os.path.dirname(previa), exist_ok=True)
    folha.save(previa)
    print("prévia:", previa)


if __name__ == "__main__":
    main()
