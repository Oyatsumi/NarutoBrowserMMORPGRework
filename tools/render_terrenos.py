#!/usr/bin/env python3
"""Renderiza o terreno de cada casa (-400..400) com as mesmas regras de funcoesterreno.php (prioridade + tremida),
lado a lado com o mapa global (images/mapa_grande.png), com as vilas marcadas. Lê a tabela terrenos do banco.
  python3 render_terrenos.py SAIDA.png [TITULO]"""
import sys, subprocess
from PIL import Image, ImageDraw, ImageFont
SAIDA = sys.argv[1]; TIT = sys.argv[2] if len(sys.argv) > 2 else ''
def q(sql): return [l.split('\t') for l in subprocess.run(['mysql','-N','narutorpg','-e',sql],capture_output=True,text=True).stdout.strip().split('\n') if l]
ter = [(r[0], int(r[1]), int(r[2]), int(r[3]), int(r[4])) for r in q("SELECT nome,latmin,latmax,lonmin,lonmax FROM naruto_terrenos ORDER BY prioridade DESC, id DESC")]
towns = [(r[0], int(r[1]), int(r[2])) for r in q("SELECT name_en,latitude,longitude FROM naruto_towns")]
COR = {'grama':(120,180,80),'floresta':(30,100,40),'trilha':(150,110,70),'deserto':(230,205,140),'rochoso':(130,120,110),
       'neve':(240,245,250),'costa':(90,150,210),'pantano':(80,110,80),'vulcanico':(120,40,30)}
def casa(lat, lon):
    h = ((lat*73856093) ^ (lon*19349663)) & 0x7fffffff
    la = lat + (h % 5) - 2; lo = lon + ((h//5) % 5) - 2
    for n,a,b,c,d in ter:
        if a <= la <= b and c <= lo <= d: return n
    return 'grama'
N = 801
im = Image.new('RGB', (N, N))
px = im.load()
for lat in range(-400, 401):
    for lon in range(-400, 401):
        px[lon+400, 400-lat] = COR.get(casa(lat, lon), (255,0,255))
mapa = Image.open('game/images/mapa_grande.png').convert('RGB').resize((N, N))
out = Image.new('RGB', (2*N+30, N+90), (255,255,255)); out.paste(im, (0, 60)); out.paste(mapa, (N+30, 60))
g = ImageDraw.Draw(out)
try: f = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 14); fb = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 20)
except Exception: f = fb = ImageFont.load_default()
g.text((10, 10), TIT + '  (left: terrain per cell, minimap rules; right: world map art)', fill=(0,0,0), font=fb)
for ox in (0, N+30):
    for n, la, lo in towns:
        x, y = ox+lo+400, 60+400-la
        g.ellipse((x-6,y-6,x+6,y+6), fill=(255,0,0), outline=(0,0,0), width=2)
        g.text((x+8, y-8), n, fill=(0,0,0), font=f, stroke_width=2, stroke_fill=(255,255,255))
x = 10
for n, c in COR.items():
    g.rectangle((x, N+66, x+16, N+82), fill=c, outline=(0,0,0)); g.text((x+20, N+66), n, fill=(0,0,0), font=f); x += 110
out.save(SAIDA)
