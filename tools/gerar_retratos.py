"""Gera retratos em estilo nanquim (traço + hachura, tons sépia) para os inimigos sem carta.
Desenhos genéricos e originais por categoria: ninja, besta selada, invocação, marionete, clone e planta.
A semente vem do "nome base" do inimigo, então "X Selo 1"/"X Selo 2"/"X #2" mantêm o mesmo visual,
com tratamento mais escuro (marcas de selo) nas formas avançadas.
"""
import math, random, re, sys, json
from PIL import Image, ImageDraw, ImageFilter, ImageChops, ImageOps

S = 4
W, H = 72 * S, 104 * S
TRACO = 28            # cor do contorno
LT = 6                # largura do contorno (x4)

class Tela:
    def __init__(self):
        self.img = Image.new('L', (W, H), 255)
    def forma(self, desenhar, preench=235, contorno=TRACO, larg=LT, hachura=None, rnd=None):
        """desenhar(draw, fill, outline, width) desenha a forma; aplica preenchimento, hachura e contorno.
        Estilo silhueta: tudo vira tinta escura (com leves variações de tom); só olhos/dentes/brilhos (>= 245) ficam claros."""
        if preench >= 245: preench = 236
        else: preench = int(44 + preench * 0.20); hachura = None
        contorno = None if contorno is not None and preench < 80 else contorno
        m = Image.new('L', (W, H), 0); desenhar(ImageDraw.Draw(m), 255, None, 0)
        cor = Image.new('L', (W, H), preench)
        self.img.paste(cor, (0, 0), m)
        if hachura:
            h = Image.new('L', (W, H), 255); dh = ImageDraw.Draw(h)
            ang, lado, esp = hachura  # ângulo, lado da sombra (-1 esq / 1 dir / 0 tudo), espaçamento
            for k in range(-H, W + H, esp):
                dh.line([(k, 0), (k + math.tan(math.radians(ang)) * H, H)], fill=110, width=3)
            sombra = Image.new('L', (W, H), 0); ds = ImageDraw.Draw(sombra)
            if lado == 0: ds.rectangle([0, 0, W, H], fill=255)
            else:
                bb = m.getbbox() or (0, 0, W, H); meio = (bb[0] + bb[2]) / 2 + (bb[2] - bb[0]) * 0.12 * lado
                ds.rectangle([meio, 0, W, H] if lado > 0 else [0, 0, meio, H], fill=255)
            self.img.paste(h, (0, 0), ImageChops.multiply(m, sombra).point(lambda v: 255 if v > 128 else 0).filter(ImageFilter.MinFilter(3)) if False else ImageChops.darker(m, sombra))
            # reaplica só a hachura (mantendo o preenchimento fora das linhas)
            hh = ImageChops.lighter(h, ImageChops.invert(ImageChops.darker(m, sombra)))
            self.img = ImageChops.darker(self.img, hh)
        if contorno is not None:
            borda = m.filter(ImageFilter.MaxFilter(larg if larg % 2 else larg + 1))
            anel = ImageChops.subtract(borda, m)
            self.img.paste(Image.new('L', (W, H), contorno), (0, 0), anel)
        return m
    def linha(self, pts, larg=LT, cor=TRACO):
        d = ImageDraw.Draw(self.img)
        for a, b in zip(pts, pts[1:]):
            d.line([a, b], fill=cor, width=larg)
            r = larg / 2; d.ellipse([b[0]-r, b[1]-r, b[0]+r, b[1]+r], fill=cor)

def curva(p0, p1, p2, n=24):
    return [((1-t)**2*p0[0] + 2*(1-t)*t*p1[0] + t*t*p2[0], (1-t)**2*p0[1] + 2*(1-t)*t*p1[1] + t*t*p2[1]) for t in (i / n for i in range(n + 1))]

def fundo(t, elem, rnd):
    c = 222
    if elem == 'fogo':
        for i in range(6):
            x = rnd.randint(0, W); h = rnd.randint(H // 3, int(H / 1.5))
            t.linha([(x + math.sin(k / 2.0 + i) * 16 + rnd.randint(-5, 5), H - h * k / 6) for k in range(0, 7)], rnd.randint(8, 16), c)
    elif elem == 'agua':
        for i in range(7):
            y = rnd.randint(H // 5, H); t.linha([(x, y + math.sin(x / 14.0 + i) * 9) for x in range(0, W + 8, 8)], 5, c)
    elif elem == 'vento':
        for i in range(5):
            cx, cy, r0 = rnd.randint(0, W), rnd.randint(0, H), rnd.randint(30, 90)
            t.linha([(cx + math.cos(a / 10.0) * (r0 - a), cy + math.sin(a / 10.0) * (r0 - a) * 0.6) for a in range(0, r0, 3)], 5, c)
    elif elem == 'raio':
        for i in range(4):
            x, y = rnd.randint(0, W), 0; pts = [(x, y)]
            while y < H: y += rnd.randint(25, 60); x += rnd.randint(-40, 40); pts.append((x, y))
            t.linha(pts, rnd.randint(5, 9), c)
    elif elem == 'terra':
        d = ImageDraw.Draw(t.img)
        for i in range(6):
            x, y, s = rnd.randint(-40, W), rnd.randint(H // 2, H), rnd.randint(40, 110)
            d.polygon([(x, y), (x + s // 2, y - s), (x + s, y)], outline=c - 10, width=4)
    else:
        d = ImageDraw.Draw(t.img); cx, cy, r = W // 2, int(H / 2.6), int(W / 2.2)
        d.arc([cx - r, cy - r, cx + r, cy + r], rnd.randint(0, 90), rnd.randint(250, 340), fill=c, width=7)

def ninja(t, rnd, escuro=False):
    cx = W // 2 + rnd.randint(-8, 8)
    hy, hr = int(H * 0.40), rnd.randint(74, 84)
    cabelo = 58 if not escuro else 34
    estilo = rnd.choice(['capuz', 'espetado', 'longo', 'chapeu', 'curto'])
    gola = rnd.choice(['colete', 'manto', 'cachecol'])
    # tronco
    t.forma(lambda d, f, o, w: d.polygon([(cx - 150, H + 10), (cx - 128, hy + hr + 62), (cx - 52, hy + hr + 18), (cx + 52, hy + hr + 18), (cx + 128, hy + hr + 62), (cx + 150, H + 10)], fill=f),
            preench=196 if gola != 'manto' else 92, hachura=(30, 1, 11), rnd=rnd)
    if gola == 'colete':
        t.forma(lambda d, f, o, w: d.polygon([(cx - 70, hy + hr + 26), (cx - 22, H + 10), (cx - 96, H + 10), (cx - 112, hy + hr + 70)], fill=f), preench=160, hachura=(30, 0, 13))
        t.forma(lambda d, f, o, w: d.polygon([(cx + 70, hy + hr + 26), (cx + 22, H + 10), (cx + 96, H + 10), (cx + 112, hy + hr + 70)], fill=f), preench=160, hachura=(30, 0, 13))
    elif gola == 'manto':
        t.forma(lambda d, f, o, w: d.polygon([(cx - 92, hy + hr - 10), (cx - 40, hy + hr + 60), (cx + 40, hy + hr + 60), (cx + 92, hy + hr - 10), (cx + 60, hy + hr + 30), (cx - 60, hy + hr + 30)], fill=f), preench=92)
        for k in range(3):
            x = cx + rnd.randint(-110, 110); y = rnd.randint(hy + hr + 80, H - 30)
            t.forma(lambda d, f, o, w, x=x, y=y: d.ellipse([x - 22, y - 12, x + 22, y + 12], fill=f), preench=232, contorno=60, larg=3)
    # pescoço
    t.forma(lambda d, f, o, w: d.rectangle([cx - 28, hy + hr - 26, cx + 28, hy + hr + 28], fill=f), preench=214, hachura=(30, -1, 9))
    if gola == 'cachecol' or estilo == 'capuz':
        t.forma(lambda d, f, o, w: d.rounded_rectangle([cx - 70, hy + hr - 6, cx + 70, hy + hr + 46], 20, fill=f), preench=170, hachura=(-35, 1, 10))
    # cabelo de trás / capuz
    if estilo == 'capuz':
        t.forma(lambda d, f, o, w: d.ellipse([cx - hr - 24, hy - hr - 30, cx + hr + 24, hy + hr + 40], fill=f), preench=88, hachura=(40, 1, 9))
    elif estilo == 'longo':
        t.forma(lambda d, f, o, w: d.polygon([(cx - hr - 12, hy - 20), (cx - hr - 30, H - 40), (cx - 40, H - 100), (cx + 40, H - 100), (cx + hr + 30, H - 40), (cx + hr + 12, hy - 20)], fill=f), preench=cabelo)
        t.forma(lambda d, f, o, w: d.ellipse([cx - hr - 12, hy - hr - 16, cx + hr + 12, hy + hr], fill=f), preench=cabelo)
    # rosto
    rosto = t.forma(lambda d, f, o, w: d.ellipse([cx - hr + 8, hy - hr + 6, cx + hr - 8, hy + hr + 12], fill=f), preench=240, hachura=(25, -1, 9))
    # orelhas
    for sx in (-1, 1):
        t.forma(lambda d, f, o, w, sx=sx: d.ellipse([cx + sx * (hr - 8) - 12, hy - 4, cx + sx * (hr - 8) + 12, hy + 30], fill=f), preench=228)
    # franja / cabelo da frente
    if estilo in ('espetado', 'curto', 'longo'):
        pontas = 9 if estilo == 'espetado' else 6
        def franja(d, f, o, w):
            pts = [(cx - hr - 14, hy - 6)]
            for k in range(pontas + 1):
                a = math.pi * (1.0 + k / pontas)
                rr = hr + (rnd.randint(36, 70) if estilo == 'espetado' else rnd.randint(10, 22))
                pts.append((cx + math.cos(a) * rr, hy + math.sin(a) * rr - 6))
                if k < pontas:
                    a2 = math.pi * (1.0 + (k + 0.5) / pontas)
                    pts.append((cx + math.cos(a2) * (hr - 10), hy - hr * 0.35 + math.sin(a2) * 8))
            pts.append((cx + hr + 14, hy - 6))
            d.polygon(pts, fill=f)
        t.forma(franja, preench=cabelo)
        for k in range(5):
            x = cx + rnd.randint(-hr + 10, hr - 10)
            t.linha(curva((x, hy - hr - 10), (x + rnd.randint(-20, 20), hy - hr + 20), (x + rnd.randint(-14, 14), hy - hr + 40)), 3, 150)
    elif estilo == 'chapeu':
        t.forma(lambda d, f, o, w: d.polygon([(cx - hr - 70, hy - hr + 34), (cx, hy - hr - 64), (cx + hr + 70, hy - hr + 34)], fill=f), preench=208, hachura=(-20, 1, 10))
    # bandana (protetor de testa, sem símbolo de vila)
    if estilo != 'chapeu':
        by = hy - int(hr * 0.42)
        t.forma(lambda d, f, o, w: d.rectangle([cx - hr + 4, by - 13, cx + hr - 4, by + 13], fill=f), preench=70)
        t.forma(lambda d, f, o, w: d.rounded_rectangle([cx - 34, by - 11, cx + 34, by + 11], 4, fill=f), preench=222, contorno=40, larg=4)
        t.linha([(cx - 18, by + 1), (cx + 18, by + 1)], 3, 120)
        for k in range(2):
            t.linha(curva((cx + hr - 6, by), (cx + hr + 26, by + 18 + k * 18), (cx + hr + 22 + k * 22, by + 58 + k * 26)), 8, 70)
    # olhos e sobrancelhas
    ey = hy + 6
    for sx in (-1, 1):
        ex = cx + sx * int(hr / 2.5)
        t.forma(lambda d, f, o, w, ex=ex, sx=sx: d.polygon([(ex - 19, ey + 2), (ex - 4, ey - 7), (ex + 19, ey - 3 - (4 if escuro else 0)), (ex + 15, ey + 7), (ex - 14, ey + 8)], fill=f), preench=250, contorno=TRACO, larg=5)
        t.forma(lambda d, f, o, w, ex=ex: d.ellipse([ex - 6, ey - 5, ex + 6, ey + 7], fill=f), preench=40 if not escuro else 150, contorno=None)
        t.linha([(ex - 22 * sx * -1 if False else ex - 22, ey - 22 + (sx * 4 if escuro else 0)), (ex + 18, ey - 16 - (sx * 6 if escuro else 0))], 6)
    # nariz e boca / máscara
    t.linha([(cx + 2, hy + 24), (cx - 4, hy + 40), (cx + 6, hy + 42)], 3, 90)
    if rnd.random() < 0.45 or estilo == 'capuz':
        t.forma(lambda d, f, o, w: d.chord([cx - hr + 8, hy + 18, cx + hr - 8, hy + hr + 14], 0, 180, fill=f), preench=150, hachura=(35, 0, 10))
        t.forma(lambda d, f, o, w: d.rectangle([cx - hr + 10, hy + 28, cx + hr - 10, hy + 36], fill=f), preench=150, contorno=None)
    else:
        t.linha(curva((cx - 18, hy + 60), (cx, hy + 64 + (-6 if escuro else 4)), (cx + 18, hy + 58)), 4, 50)
    # arma às costas
    if rnd.random() < 0.55:
        x0 = cx + rnd.choice([-1, 1]) * rnd.randint(96, 120)
        t.forma(lambda d, f, o, w: d.polygon([(x0 - 5, H), (x0 + 5, H), (x0 + 30, 40), (x0 + 22, 30)], fill=f), preench=214, hachura=(10, 1, 7))
        t.forma(lambda d, f, o, w: d.rectangle([x0 - 16, H - 90, x0 + 16, H - 76], fill=f), preench=60)
    if escuro:  # marcas do selo amaldiçoado espalhando pelo rosto
        r2 = random.Random(int(hy * 7 + hr))
        for k in range(10):
            x = cx + r2.randint(-hr + 14, hr - 14); y = hy + r2.randint(-hr // 2, hr - 10)
            t.linha(curva((x, y), (x + r2.randint(-26, 26), y + r2.randint(-26, 26)), (x + r2.randint(-36, 36), y + r2.randint(-36, 36)), 10), 5, 30)

def besta(t, rnd, caudas, escuro=False):
    cx, cy = W // 2, int(H * 0.42)
    pelo = 120 if not escuro else 70
    n = caudas
    for k in range(n):
        a = math.pi * (0.92 + 1.16 * (k + 0.5) / n) if n > 1 else math.pi * 1.42
        r1 = rnd.randint(200, 240)
        p0, p2 = (cx, cy + 70), (cx + math.cos(a) * r1, cy + math.sin(a) * r1 * 0.95)
        p1 = (cx + math.cos(a + 0.6) * r1 * 0.65, cy + math.sin(a + 0.6) * r1 * 0.65)
        pts = curva(p0, p1, p2, 30)
        def cauda(d, f, o, w, pts=pts):
            for i, (a1, b1) in enumerate(zip(pts, pts[1:])):
                larg = int(44 * (1 - i / len(pts)) + 12); d.line([a1, b1], fill=f, width=larg)
                d.ellipse([b1[0] - larg / 2, b1[1] - larg / 2, b1[0] + larg / 2, b1[1] + larg / 2], fill=f)
        t.forma(cauda, preench=pelo + 30, hachura=(45, 0, 12))
    if n == 0:
        t.linha([(cx + math.cos(q / 6.0) * q * 1.3, cy + math.sin(q / 6.0) * q * 1.3) for q in range(0, 150, 2)], 7, 150)
    # corpo agachado: peito e duas patas dianteiras com garras
    t.forma(lambda d, f, o, w: d.polygon([(cx - 120, H + 10), (cx - 96, cy + 70), (cx, cy + 40), (cx + 96, cy + 70), (cx + 120, H + 10)], fill=f), preench=pelo + 40)
    for sx in (-1, 1):
        t.forma(lambda d, f, o, w, sx=sx: d.polygon([(cx + sx * 40, cy + 110), (cx + sx * 118, cy + 120), (cx + sx * 132, H - 40), (cx + sx * 150, H - 6), (cx + sx * 40, H - 6)], fill=f), preench=pelo + 10)
        for g in range(3):
            gx = cx + sx * (66 + g * 26)
            t.forma(lambda d, f, o, w, gx=gx: d.polygon([(gx - 8, H - 8), (gx, H + 6), (gx + 8, H - 8)], fill=f), preench=250, contorno=None)
    # juba de pelos
    def juba(d, f, o, w):
        pts = []
        for k in range(30):
            a = 2 * math.pi * k / 30; rr = 108 if k % 2 == 0 else 84
            pts.append((cx + math.cos(a) * rr, cy + math.sin(a) * rr * 0.92))
        d.polygon(pts, fill=f)
    t.forma(juba, preench=pelo, hachura=(30, -1, 9))
    # orelhas/chifres
    for sx in (-1, 1):
        t.forma(lambda d, f, o, w, sx=sx: d.polygon([(cx + sx * 34, cy - 64), (cx + sx * rnd.randint(78, 100), cy - 150 - rnd.randint(0, 30)), (cx + sx * 84, cy - 30)], fill=f), preench=pelo - 20, hachura=(20, sx, 9))
    # cabeça
    t.forma(lambda d, f, o, w: d.ellipse([cx - 76, cy - 72, cx + 76, cy + 74], fill=f), preench=pelo + 70, hachura=(25, 1, 9))
    t.forma(lambda d, f, o, w: d.polygon([(cx - 44, cy + 14), (cx, cy + 100), (cx + 44, cy + 14)], fill=f), preench=pelo + 85)
    t.forma(lambda d, f, o, w: d.ellipse([cx - 12, cy + 70, cx + 12, cy + 88], fill=f), preench=40)
    for sx in (-1, 1):
        ex = cx + sx * 34
        t.forma(lambda d, f, o, w, ex=ex, sx=sx: d.polygon([(ex - 24 * sx, cy + 6), (ex + 20 * sx, cy - 16), (ex + 22 * sx, cy + 4), (ex - 18 * sx, cy + 14)], fill=f), preench=250, larg=5)
        t.forma(lambda d, f, o, w, ex=ex: d.ellipse([ex - 3, cy - 10, ex + 3, cy + 10], fill=f), preench=25, contorno=None)
        t.linha([(ex - 26 * sx, cy - 12), (ex + 24 * sx, cy - 32)], 7)
    for k in range(5):
        x = cx - 32 + k * 16
        t.forma(lambda d, f, o, w, x=x: d.polygon([(x, cy + 40), (x + 7, cy + 60 + (8 if k in (0, 4) else 0)), (x + 14, cy + 40)], fill=f), preench=250, larg=3)
    for k in range(max(n, 1) + 2):
        x, y = cx + rnd.randint(-90, 90), rnd.randint(cy + 140, H - 10)
        t.linha(curva((x - 24, y), (x, y - 16), (x + 24, y)), 4, 60)

def invocacao(t, rnd, animal):
    cx, cy = W // 2, int(H * 0.52)
    d = ImageDraw.Draw(t.img)
    d.ellipse([cx - 135, H - 92, cx + 135, H - 8], outline=150, width=5)
    for k in range(12):
        a = k * math.pi / 6
        d.line([cx + math.cos(a) * 104, H - 50 + math.sin(a) * 30, cx + math.cos(a) * 134, H - 50 + math.sin(a) * 41], fill=150, width=4)
    for k in range(9):
        x, y, r = rnd.randint(0, W), rnd.randint(H - 150, H - 30), rnd.randint(18, 40)
        t.forma(lambda d, f, o, w, x=x, y=y, r=r: d.ellipse([x - r, y - r, x + r, y + r], fill=f), preench=246, contorno=180, larg=3)
    if animal == 'sapo':
        t.forma(lambda d, f, o, w: d.ellipse([cx - 130, cy - 50, cx + 130, cy + 170], fill=f), preench=170, hachura=(30, 1, 10))
        t.forma(lambda d, f, o, w: d.chord([cx - 100, cy + 30, cx + 100, cy + 170], 0, 180, fill=f), preench=232)
        for sx in (-1, 1):
            t.forma(lambda d, f, o, w, sx=sx: d.ellipse([cx + sx * 72 - 38, cy - 120, cx + sx * 72 + 38, cy - 30], fill=f), preench=170, hachura=(30, sx, 9))
            t.forma(lambda d, f, o, w, sx=sx: d.ellipse([cx + sx * 72 - 22, cy - 102, cx + sx * 72 + 22, cy - 50], fill=f), preench=248)
            t.forma(lambda d, f, o, w, sx=sx: d.rectangle([cx + sx * 72 - 5, cy - 94, cx + sx * 72 + 5, cy - 58], fill=f), preench=30, contorno=None)
        t.linha(curva((cx - 96, cy + 20), (cx, cy + 66), (cx + 96, cy + 20)), 7)
        for k in range(7):
            x, y = cx + rnd.randint(-100, 100), cy + rnd.randint(-30, 20)
            t.forma(lambda d, f, o, w, x=x, y=y: d.ellipse([x - 12, y - 9, x + 12, y + 9], fill=f), preench=120, larg=3)
    elif animal == 'cobra':
        pts = [(cx + math.sin(q / 22.0) * (110 - q * 0.25), H - 30 - q * 1.15) for q in range(0, 300, 4)]
        def corpo(d, f, o, w):
            for a1, b1 in zip(pts, pts[1:]):
                d.line([a1, b1], fill=f, width=70); d.ellipse([b1[0] - 35, b1[1] - 35, b1[0] + 35, b1[1] + 35], fill=f)
        t.forma(corpo, preench=180, hachura=(40, 1, 9))
        for q in range(4, len(pts) - 4, 5):
            x, y = pts[q]; t.linha(curva((x - 30, y), (x, y + 10), (x + 30, y)), 3, 110)
        hx, hy = pts[-1]
        t.forma(lambda d, f, o, w: d.polygon([(hx - 80, hy + 14), (hx - 10, hy - 56), (hx + 10, hy - 56), (hx + 80, hy + 14), (hx, hy + 50)], fill=f), preench=170, hachura=(30, 1, 9))
        for sx in (-1, 1):
            t.forma(lambda d, f, o, w, sx=sx: d.polygon([(hx + sx * 42, hy - 6), (hx + sx * 14, hy - 22), (hx + sx * 16, hy + 4)], fill=f), preench=250, larg=4)
        t.linha([(hx, hy + 46), (hx - 8, hy + 78), (hx - 22, hy + 90)], 4)
        t.linha([(hx, hy + 46), (hx + 8, hy + 78), (hx + 22, hy + 90)], 4)
    elif animal == 'lesma':
        t.forma(lambda d, f, o, w: (d.chord([cx - 130, cy - 130, cx + 130, cy + 170], 180, 360, fill=f), d.rectangle([cx - 130, cy + 19, cx + 130, cy + 130], fill=f)), preench=214, hachura=(30, 1, 10))
        for k in range(4):
            t.linha(curva((cx - 110 + k * 12, cy + 40), (cx, cy - 110 + k * 30), (cx + 110 - k * 12, cy + 40)), 4, 150)
        for sx in (-1, 1):
            t.linha([(cx + sx * 30, cy - 100), (cx + sx * 54, cy - 170)], 10)
            t.forma(lambda d, f, o, w, sx=sx: d.ellipse([cx + sx * 54 - 14, cy - 186, cx + sx * 54 + 14, cy - 158], fill=f), preench=230)

def marionete(t, rnd, varias=False):
    qt = 3 if varias else 1
    ordem = [0, 2, 1] if varias else [0]
    for q in ordem:
        esc = 0.62 if varias else 1.0
        cx = W // 2 + (q - (qt - 1) / 2) * 92 + rnd.randint(-6, 6)
        top = int(H * 0.2) + (50 if varias and q != 1 else 0)
        d = ImageDraw.Draw(t.img)
        for k in range(3): d.line([cx - 46 * esc + k * 46 * esc, 0, cx - 46 * esc + k * 46 * esc, top + 24], fill=130, width=3)
        t.forma(lambda d, f, o, w, cx=cx: d.rectangle([cx - 66 * esc, 10, cx + 66 * esc, 24], fill=f), preench=150)
        hr = 46 * esc
        y0 = top + hr * 2.2
        t.forma(lambda d, f, o, w, cx=cx, y0=y0: d.polygon([(cx - 84 * esc, H + 10), (cx - 56 * esc, y0 + 16), (cx + 56 * esc, y0 + 16), (cx + 84 * esc, H + 10)], fill=f), preench=110, hachura=(30, 1, 10))
        for sx in (-1, 1):
            t.linha([(cx + sx * 56 * esc, y0 + 30), (cx + sx * 112 * esc, y0 + 96 * esc), (cx + sx * 96 * esc, y0 + 180 * esc)], int(14 * esc) + 2)
            t.forma(lambda d, f, o, w, cx=cx, sx=sx, y0=y0: d.polygon([(cx + sx * 96 * esc, y0 + 180 * esc), (cx + sx * 120 * esc, y0 + 250 * esc), (cx + sx * 86 * esc, y0 + 196 * esc)], fill=f), preench=220, larg=4)
        t.forma(lambda d, f, o, w, cx=cx, top=top: d.ellipse([cx - hr, top, cx + hr, top + hr * 2.2], fill=f), preench=226, hachura=(25, -1, 9))
        t.forma(lambda d, f, o, w, cx=cx, top=top: d.rectangle([cx - hr * 0.62, top + hr * 1.5, cx + hr * 0.62, top + hr * 1.62], fill=f), preench=40, contorno=None)
        for lado in (-1, 1):
            d = ImageDraw.Draw(t.img); d.line([cx + lado * hr * 0.62, top + hr * 1.56, cx + lado * hr * 0.62, top + hr * 2.1], fill=40, width=4)
            t.forma(lambda d, f, o, w, cx=cx, top=top, lado=lado: d.ellipse([cx + lado * hr * 0.42 - 9, top + hr * 0.8 - 7, cx + lado * hr * 0.42 + 9, top + hr * 0.8 + 7], fill=f), preench=30, contorno=None)
        for k in range(3):
            y = y0 + 50 + k * 54 * esc
            t.forma(lambda d, f, o, w, cx=cx, y=y: d.ellipse([cx - 13, y - 9, cx + 13, y + 9], fill=f), preench=210, larg=3)

def planta(t, rnd):
    cx, cy = W // 2, int(H * 0.42)
    t.forma(lambda d, f, o, w: d.rectangle([cx - 100, cy + 140, cx + 100, H + 10], fill=f), preench=120, hachura=(30, 1, 10))
    t.forma(lambda d, f, o, w: d.ellipse([cx - 58, cy - 66, cx + 58, cy + 66], fill=f), preench=238)
    t.forma(lambda d, f, o, w: d.pieslice([cx - 58, cy - 66, cx + 58, cy + 66], 90, 270, fill=f), preench=60, contorno=None)
    t.forma(lambda d, f, o, w: d.ellipse([cx - 34, cy - 14, cx - 16, cy + 4], fill=f), preench=245, contorno=None)
    t.forma(lambda d, f, o, w: d.ellipse([cx + 16, cy - 14, cx + 34, cy + 4], fill=f), preench=20, larg=3)
    for sx in (-1, 1):
        def folha(d, f, o, w, sx=sx):
            pts = curva((cx + sx * 6, H), (cx + sx * 200, cy), (cx + sx * 20, -60), 30) + curva((cx + sx * 20, -60), (cx + sx * 110, cy), (cx + sx * 64, H), 30)
            d.polygon(pts, fill=f)
        t.forma(folha, preench=150, hachura=(20 * sx, 0, 10))
        for k in range(8):
            y = cy - 160 + k * 42
            x = cx + sx * (70 - abs(k - 4) * 6)
            t.forma(lambda d, f, o, w, x=x, y=y, sx=sx: d.polygon([(x, y), (x - sx * 26, y + 10), (x, y + 20)], fill=f), preench=248, larg=3)

def aura(img):
    # brilho claro atrás da figura (deixa a silhueta destacada, como num cartaz de procurado)
    g = Image.new('L', (W, H), 0); d = ImageDraw.Draw(g)
    d.ellipse([W * 0.05, H * 0.08, W * 0.95, H * 0.75], fill=255)
    g = g.filter(ImageFilter.GaussianBlur(40))
    return ImageChops.lighter(img, ImageChops.invert(g).point(lambda v: 255 - v * 30 // 255).point(lambda v: v if v < 255 else 255))

def respingos(t, rnd, n=22):
    d = ImageDraw.Draw(t.img)
    for k in range(n):
        x, y, r = rnd.randint(0, W), rnd.randint(0, H), rnd.choice([2, 3, 4, 6, 8])
        d.ellipse([x - r, y - r, x + r, y + r], fill=rnd.randint(60, 140))

def papel(img, rnd):
    ruido = Image.effect_noise((W, H), 36).filter(ImageFilter.GaussianBlur(1.4))
    return ImageChops.multiply(img, ImageOps.autocontrast(ruido, cutoff=2).point(lambda v: 214 + v * 41 // 255))

def categoria(nome):
    n = nome.lower()
    caudas = {'reibi': 0, 'ichibi': 1, 'shukaku': 1, 'nibi': 2, 'sanbi': 3, 'yonbi': 4, 'gobi': 5, 'rokubi': 6, 'shichibi': 7, 'hachibi': 8, 'kyuubi': 9}
    for k, v in caudas.items():
        if k in n: return ('besta', 4 if '4 caudas' in n else v)
    if 'bijuu' in n: return ('besta', 3)
    if 'marionete' in n: return ('marionete', True)
    if 'sasori' in n: return ('marionete', False)
    if 'gama' in n or 'sapo' in n: return ('invocacao', 'sapo')
    if 'manda' in n or 'cobra' in n: return ('invocacao', 'cobra')
    if 'katsuyu' in n or 'lesma' in n: return ('invocacao', 'lesma')
    if 'zetsu' in n: return ('planta', None)
    if 'clone' in n: return ('clone', None)
    return ('ninja', None)

def nomebase(nome):
    b = re.sub(r'\s*(selo \d.*|#\d+|clone|suprimido|enfraquecido|dominado|sharingan|mangekyou|\(s\)|- o corrompido|4 caudas|d\'água do|das sombras incomum)\s*', ' ', nome.lower())
    return re.sub(r'\s+', ' ', b).strip()

def retrato(nome, elem, idm):
    nl = nome.lower()
    escuro = bool(re.search(r'selo 2|corrompido|mangekyou|sharingan|jashin|\(s\)', nl))
    rnd = random.Random(nomebase(nome))
    t = Tela()
    fundo(t, elem, random.Random(idm))
    cat, arg = categoria(nome)
    if cat == 'besta': besta(t, rnd, arg, escuro or ('suprimido' not in nl and 'enfraquecido' not in nl and arg >= 6))
    elif cat == 'marionete': marionete(t, rnd, arg)
    elif cat == 'invocacao': invocacao(t, rnd, arg)
    elif cat == 'planta': planta(t, rnd)
    elif cat == 'clone':
        base = Tela(); ninja(base, random.Random(nomebase(nome)), escuro)
        sombra = base.img.point(lambda v: 255 - (255 - v) * 40 // 100).transform((W, H), Image.AFFINE, (1, 0, 40, 0, 1, -8), fillcolor=255)
        t.img = ImageChops.multiply(ImageChops.multiply(t.img, sombra), base.img)
        for k in range(16):
            x, y = rnd.randint(0, W), rnd.randint(H // 2, H)
            t.forma(lambda d, f, o, w, x=x, y=y: d.ellipse([x - 5, y - 10, x + 5, y + 10], fill=f), preench=235, contorno=120, larg=3)
    else: ninja(t, rnd, escuro)
    if 'suprimido' in nl or 'enfraquecido' in nl or 'dominado' in nl:
        for y in (H // 3, H // 2 + 50):
            for x in range(-10, W, 26):
                t.forma(lambda d, f, o, w, x=x, y=y: d.ellipse([x, y - 9 + (x // 26 % 2) * 5, x + 24, y + 9 + (x // 26 % 2) * 5], outline=f, width=6), preench=50, contorno=None)
    respingos(t, random.Random(idm * 7))
    img = papel(t.img, random.Random(idm))
    img = img.filter(ImageFilter.GaussianBlur(0.8))
    return img.resize((W // S, H // S), Image.LANCZOS)

if __name__ == '__main__':
    alvos = json.load(open(sys.argv[1]))
    for idm, nome, elem in alvos:
        retrato(nome, elem, idm).save(f"{sys.argv[2]}/{idm}.png")
    print('ok', len(alvos))
