"""celular_teste.py :: Testa o modo celular (celular.css + js/celular.js + lib.php scriptcelular()).
Uso: python3 celular_teste.py [http://127.0.0.1:8080] [/home/claude/work/game] [pt|en]
- detecção automática na 1ª visita (iPhone 13 / Pixel 7 -> modocelular=1; 1280px sem toque -> 0) e botão de troca que lembra;
- em cada tela (login, cadastro, início, explorar/mover, luta, jutsu, mochila, loja, banco, mapa, ficha, missões, clã,
  temporadas, talentos, chefes, duelo): sem rolagem horizontal, sem Warning/Fatal, sem erro JS, botões visíveis sem sobreposição;
- barra de level do painel direito com no máximo 40px de altura (não pode esticar);
- no computador (1280px) o layout é idêntico ao de antes (mesma posição e tamanho das colunas e do conteúdo).
Prints em tests/prints/celular/<aparelho>_<idioma>_<tela>.png"""
import subprocess, sys, os, re
from playwright.sync_api import sync_playwright

BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8080"
JOGO = sys.argv[2] if len(sys.argv) > 2 else "/home/claude/work/game"
IDIOMA = sys.argv[3] if len(sys.argv) > 3 else "pt"
AQUI = os.path.dirname(os.path.abspath(__file__))
PRINTS = os.path.join(AQUI, "prints", "celular")
os.makedirs(PRINTS, exist_ok=True)
falhas = []
def ok(c, msg):
    print(("OK   " if c else "FALHA ") + msg)
    if not c: falhas.append(msg)

def cookie(u, *sets):
    return subprocess.check_output(["php", AQUI + "/mkuser.php", JOGO, u] + list(sets), text=True).strip()

def sql(q):
    return subprocess.check_output(["mysql", "--default-character-set=utf8mb4", "narutorpg", "-N", "-e", q], text=True).strip()

CIDADE = ["avisotoque=1", "currentaction=In Town", "latitude=0", "longitude=0", "gold=5000", "level=12", "currenthp=60", "maxhp=60", "currentmp=40", "maxmp=40",
          "spells=0,1,2", "towns=0,1,2", "bp1=Kunai,3,1,X", "bp2=Poção de Vida,hp,20,3"]
EXPLORANDO = ["avisotoque=1", "currentaction=Exploring", "latitude=5", "longitude=-3"]
LUTA = ["avisotoque=1", "currentaction=Fighting", "currentfight=1", "currentmonster=0", "latitude=8", "longitude=8", "currenthp=60", "currentmp=40"]

# (tela, url, campos do jogador antes, ação extra)
TELAS = [
    ("inicio", "index.php", CIDADE, None),
    ("loja", "index.php?do=buy", CIDADE, None),
    ("banco", "funcaoitens.php?do=banco", CIDADE, None),
    ("mochila", "backpack.php", CIDADE, None),
    ("ficha", "outroseatributos.php?do=atributos", CIDADE, None),
    ("missoes", "treinamentoequests.php?do=quests", CIDADE, None),
    ("missoes_kage", "missoes.php", CIDADE, None),
    ("cla", "cla.php", CIDADE, None),
    ("temporadas", "temporadas.php", CIDADE, None),
    ("talentos", "talentos.php", CIDADE, None),
    ("chefes", "chefes.php", CIDADE, None),
    ("duelo", "users.php?do=batalha1", CIDADE, None),
    ("explorar", "index.php", EXPLORANDO, None),
    ("mover", "index.php", EXPLORANDO, "mover"),
    ("luta", "index.php?do=fight", LUTA, None),
    ("luta_atacar", "index.php?do=fight", LUTA, "atacar"),
    ("luta_jutsu", "index.php?do=fight", LUTA, "jutsu"),
    ("mapa", "index.php?do=showmap", CIDADE, None),
]

VERIFICA = """() => {
  const de = document.documentElement, r = [];
  const sw = Math.max(de.scrollWidth, document.body ? document.body.scrollWidth : 0);
  if (sw > window.innerWidth + 1) r.push('rolagem horizontal ' + sw + '>' + window.innerWidth);
  const vis = e => { const s = getComputedStyle(e); const b = e.getBoundingClientRect();
    return s.visibility !== 'hidden' && s.display !== 'none' && b.width > 0 && b.height > 0 && e.closest('[style*="hidden"]') === null; };
  const bt = [...document.querySelectorAll('input[type=submit],input[type=button],button')].filter(vis);
  for (const e of bt) { const b = e.getBoundingClientRect();
    if (b.right > window.innerWidth + 1 || b.left < -1) r.push('botão fora da tela: ' + (e.value || e.textContent));
  }
  for (let i = 0; i < bt.length; i++) for (let j = i + 1; j < bt.length; j++) {
    const a = bt[i].getBoundingClientRect(), b = bt[j].getBoundingClientRect();
    const ix = Math.min(a.right, b.right) - Math.max(a.left, b.left), iy = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
    if (ix > 2 && iy > 2) r.push('botões sobrepostos: ' + (bt[i].value || bt[i].textContent) + ' / ' + (bt[j].value || bt[j].textContent));
  }
  const imgs = [...document.querySelectorAll('img')].filter(vis);
  for (const e of imgs) { if (e.closest('td.celesq')) continue; const b = e.getBoundingClientRect(); if (b.right > window.innerWidth + 2) r.push('imagem estoura: ' + e.src.split('/').pop()); }
  // Barra de level (templates/rightnav.php): nada dentro dela pode passar de 40px de altura (já esticou 2x no celular).
  const lv = document.getElementById('qualquer');
  if (lv) { const caixa = lv.parentNode; [caixa, ...caixa.querySelectorAll('*')].forEach(e => { const h = e.getBoundingClientRect().height; if (h > 40) r.push('barra de level esticada: ' + e.tagName + ' ' + Math.round(h) + 'px'); }); }
  return r;
}"""

def checar(pg, rotulo, erros):
    txt = pg.content()
    ok(not re.search(r"<b>(Warning|Fatal error|Parse error|Notice|Deprecated)</b>|Erro SQL", txt), f"{rotulo}: sem Warning/Fatal")
    prob = pg.evaluate(VERIFICA)
    ok(not prob, f"{rotulo}: layout {prob[:4]}")
    ok(not erros, f"{rotulo}: sem erro JS {erros[:2]}")
    erros.clear()

def foto(pg, nome):
    pg.screenshot(path=os.path.join(PRINTS, nome + ".png"), full_page=True)

def medidas(pg):
    return pg.evaluate("""() => [...document.querySelectorAll('td.celesq,td.celmeio,td.celdir,.conteudomeio,table.celmoldura')]
        .map(e => { const b = e.getBoundingClientRect(); return [Math.round(b.left), Math.round(b.top), Math.round(b.width), Math.round(b.height)]; })""")

with sync_playwright() as p:
    nav = p.chromium.launch()
    TEXTO = {"pt": ("Versão celular", "Versão computador"), "en": ("Mobile version", "Desktop version")}[IDIOMA]
    for aparelho in ["iPhone 13", "Pixel 7", "1280"]:
        cel = aparelho != "1280"
        apel = aparelho.replace(" ", "").lower()
        u = "cel_" + apel + "_" + IDIOMA
        opts = dict(p.devices[aparelho]) if cel else dict(viewport={"width": 1280, "height": 900})
        ctx = nav.new_context(**opts)
        ctx.add_cookies([{"name": "idioma", "value": IDIOMA, "url": BASE + "/"}])
        pg = ctx.new_page(); erros = []
        pg.on("pageerror", lambda e: erros.append(str(e)))
        pg.on("console", lambda m: erros.append(m.text) if m.type == "error" and "favicon" not in m.text and "Failed to load resource" not in m.text else None)
        pg.on("dialog", lambda d: d.dismiss())
        # 1ª visita: detecção automática.
        pg.goto(BASE + "/login.php?do=login", wait_until="networkidle")
        ck = {c["name"]: c["value"] for c in ctx.cookies()}
        ok(ck.get("modocelular") == ("1" if cel else "0"), f"{aparelho}: detecção na 1ª visita grava modocelular={ck.get('modocelular')}")
        ok(pg.evaluate("document.documentElement.classList.contains('modocel')") == cel, f"{aparelho}: classe modocel = {cel}")
        botao = pg.locator("a.botaomodocel:visible").first
        if cel:
            ok(botao.count() == 1 and botao.inner_text().strip() == TEXTO[1], f"{aparelho}: botão visível com o texto certo")
        else:
            ok(pg.locator("a.botaomodocel:visible").count() == 0 and pg.locator("#celvoltar").count() == 0, f"{aparelho}: computador não vê o botão de versão")
        foto(pg, f"{apel}_{IDIOMA}_login"); checar(pg, f"{aparelho} login", erros)
        pg.goto(BASE + "/users.php?do=register", wait_until="networkidle")
        foto(pg, f"{apel}_{IDIOMA}_cadastro"); checar(pg, f"{aparelho} cadastro", erros)
        if cel:
            # Botão troca e lembra; na versão computador o celular vê o botão flutuante para voltar.
            pg.locator("a.botaomodocel:visible").first.click(); pg.wait_for_load_state("networkidle")
            ck = {c["name"]: c["value"] for c in ctx.cookies()}
            ok(ck.get("modocelular") == "0", f"{aparelho}: botão troca o modo ({ck.get('modocelular')})")
            pg.goto(BASE + "/login.php?do=login", wait_until="networkidle")
            ok(not pg.evaluate("document.documentElement.classList.contains('modocel')"), f"{aparelho}: o modo trocado é lembrado")
            ok(pg.locator("#celvoltar").count() == 1, f"{aparelho}: botão flutuante para voltar ao celular")
            pg.locator("#celvoltar").click(); pg.wait_for_load_state("networkidle")
            ok(pg.evaluate("document.documentElement.classList.contains('modocel')"), f"{aparelho}: voltou ao modo celular")
        erros.clear()
        # Telas logado.
        for tela, url, campos, acao in TELAS:
            c = cookie(u, *campos)
            if tela.startswith("luta"):
                sql(f"UPDATE naruto_users SET currentmonster=0, currentmonsterhp=0 WHERE username='{u}'")
            ctx.add_cookies([{"name": "narutorpg", "value": c, "url": BASE + "/"}])
            pg.goto(BASE + "/" + url, wait_until="networkidle")
            if acao == "mover":
                b = pg.locator("[name=north]:visible")
                if b.count(): b.first.click(); pg.wait_for_load_state("networkidle"); pg.wait_for_timeout(300)
                ok(b.count() > 0, f"{aparelho}: botão Norte visível")
            elif acao == "atacar":
                b = pg.locator("[name=fight]:visible")
                ok(b.count() > 0, f"{aparelho}: botão de atacar visível")
                if b.count(): b.first.click(); pg.wait_for_load_state("networkidle")
            elif acao == "jutsu":
                b = pg.locator("button[name=userspell]:visible")
                ok(b.count() > 0, f"{aparelho}: botão de jutsu visível")
                if b.count():
                    # No toque o 1º toque só mostra a dica (js/toque.js); o 2º usa o jutsu.
                    with pg.expect_navigation(): 
                        b.first.click()
                        if cel: pg.wait_for_timeout(300); b.first.click()
                    pg.wait_for_load_state("networkidle")
            foto(pg, f"{apel}_{IDIOMA}_{tela}")
            checar(pg, f"{aparelho} {tela}", erros)
            if cel and tela == "inicio":
                # Painéis recolhíveis: fecha a coluna e lembra.
                cab = pg.locator("td.celesq .celcab")
                ok(cab.count() == 1, f"{aparelho}: cabeçalho recolhível da navegação")
                cab.click(); pg.wait_for_timeout(200)
                ok(not pg.locator("td.celesq > center").is_visible(), f"{aparelho}: navegação recolhe")
                foto(pg, f"{apel}_{IDIOMA}_inicio_recolhido")
                pg.reload(wait_until="networkidle")
                ok(not pg.locator("td.celesq > center").is_visible(), f"{aparelho}: recolhido é lembrado")
                pg.locator("td.celesq .celcab").click(); pg.wait_for_timeout(200)
                ok(pg.locator("td.celesq > center").is_visible(), f"{aparelho}: navegação abre de novo")
            if not cel and tela == "inicio":
                # Computador idêntico: as colunas e a moldura têm as medidas de sempre (990 = 204 + 551 + 204 + bordas).
                m = medidas(pg)
                ok(len(m) == 5 and [x[2] for x in m] == [210, 557, 551, 525, 210], f"{aparelho}: medidas do computador {m}")
        ctx.close()
    nav.close()

print(f"\n{len(falhas)} falha(s)")
sys.exit(1 if falhas else 0)
