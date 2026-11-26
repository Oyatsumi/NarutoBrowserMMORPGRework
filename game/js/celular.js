// celular.js :: Modo celular (só reorganiza a tela; imagens, cores e textos são os mesmos do computador).
// A decisão (cookie modocelular=1/0, ou detecção na 1ª visita) e a classe "modocel" no <html> vêm do <head> (lib.php scriptcelular()).
// Aqui: o botão de troca, a barra do topo com o botão no celular, as colunas laterais recolhíveis no padrão .cxb
// e a escala do cabeçalho (a moldura de 551px encolhe para caber na tela).
(function () {
	"use strict";
	var script = document.currentScript || document.querySelector("script[data-celular]");
	function dado(n, padrao) { return (script && script.getAttribute("data-" + n)) || padrao; }
	var html = document.documentElement;
	function celular() { return (" " + html.className + " ").indexOf(" modocel ") >= 0; }

	// Troca o modo, grava por 1 ano e recarrega a página no novo layout.
	window.trocarmodocel = function () {
		document.cookie = "modocelular=" + (celular() ? 0 : 1) + "; path=/; max-age=31536000; SameSite=Lax";
		location.reload();
	};

	function guardar(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
	function ler(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }

	// Escala do cabeçalho e das molduras de largura fixa: nunca maior que 1 (no computador nada muda).
	function escala() {
		var w = html.clientWidth || window.innerWidth;
		var z = Math.min(1, w / 551);
		html.style.setProperty("--celz", String(Math.floor(z * 1000) / 1000));
		var z2 = Math.min(1, (w - 8) / 204);
		html.style.setProperty("--celz204", String(Math.floor(z2 * 1000) / 1000));
	}

	// Cabeçalho .cxb que recolhe/abre uma coluna lateral (fica fora da região trocada pelo AJAX, então não some).
	function recolhivel(td, chave, titulo, abertopadrao) {
		if (!td || td.querySelector(".celcab")) { return; }
		var cab = document.createElement("div");
		cab.className = "cxb-cab celcab";
		cab.setAttribute("role", "button");
		cab.setAttribute("tabindex", "0");
		cab.innerHTML = "<span></span><span class=\"celseta\"></span>";
		cab.firstChild.appendChild(document.createTextNode(titulo));
		td.insertBefore(cab, td.firstChild);
		var est = ler("celpainel_" + chave);
		var aberto = est === null ? abertopadrao : est === "1";
		function aplicar() {
			td.className = td.className.replace(/\s*celfechado/g, "") + (aberto ? "" : " celfechado");
			cab.lastChild.innerHTML = aberto ? "&#9650;" : "&#9660;";
			cab.setAttribute("aria-expanded", aberto ? "true" : "false");
		}
		function trocar(e) { if (e) { e.stopPropagation(); } aberto = !aberto; guardar("celpainel_" + chave, aberto ? "1" : "0"); aplicar(); }
		cab.onclick = trocar;
		cab.onkeydown = function (e) { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); trocar(e); } };
		aplicar();
	}

	// Cada painel das colunas ganha um botão ▲/▼ (estilo .cxb) que recolhe o painel até o título; lembrado por painel.
	function paineis(td, lado) {
		if (!td) { return; }
		var lista = td.querySelectorAll(":scope > center > div");
		for (var i = 0; i < lista.length; i++) {
			(function (p, chave) {
				if (p.querySelector(":scope > .celpbtn")) { return; }
				var bt = document.createElement("a");
				bt.href = "javascript:void(0)";
				bt.className = "celpbtn";
				p.className += " celpainel";
				p.insertBefore(bt, p.firstChild);
				var aberto = ler(chave) !== "0";
				function aplicar() {
					p.className = p.className.replace(/\s*celpfechado/g, "") + (aberto ? "" : " celpfechado");
					bt.innerHTML = aberto ? "&#9650;" : "&#9660;";
					bt.setAttribute("aria-expanded", aberto ? "true" : "false");
				}
				bt.onclick = function (e) { e.preventDefault(); e.stopPropagation(); aberto = !aberto; guardar(chave, aberto ? "1" : "0"); aplicar(); };
				aplicar();
			})(lista[i], "celp_" + lado + i);
		}
	}

	// Celular na versão de computador: botão fixo para voltar à versão celular (a página fica miúda e o link do rodapé some de vista).
	function aparelhomovel() {
		var toque = ("ontouchstart" in window) || (navigator.maxTouchPoints || 0) > 0;
		return toque && Math.min(screen.width || 9999, screen.height || 9999) <= 600;
	}
	if (aparelhomovel()) { html.className += " celcapaz"; } // o link "Versão celular" do rodapé só aparece em celular (celular.css)
	function botaovoltar() {
		if (!aparelhomovel() || document.getElementById("celvoltar")) { return; }
		var a = document.createElement("a");
		a.id = "celvoltar"; a.href = "javascript:void(0)";
		a.style.cssText = "position:fixed;right:10px;bottom:10px;z-index:9999;background:#452202;color:#fff;border:2px solid #fff1c7;border-radius:6px;padding:12px 18px;font:bold 28px tahoma,arial,sans-serif;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.5)";
		a.onclick = function (e) { e.stopPropagation(); window.trocarmodocel(); };
		a.appendChild(document.createTextNode(dado("vcel", "Versão celular")));
		document.body.appendChild(a);
	}

	function montar() {
		if (!celular()) { botaovoltar(); return; }
		// A caixinha de detalhes some ao rolar a página (no celular não há "mouseout" para fechá-la).
		var limparexplic = function () { var e = document.getElementById("explicmenu"); if (e && e.innerHTML !== "") { e.innerHTML = ""; } };
		window.addEventListener("scroll", limparexplic, { passive: true });
		escala();
		window.addEventListener("resize", escala);
		var b = document.body;
		if (!b) { revelar(); return; }
		// Barra do topo só com o botão de voltar à versão de computador.
		if (!document.getElementById("celbarra")) {
			var barra = document.createElement("div");
			barra.id = "celbarra";
			var a = document.createElement("a");
			a.href = "javascript:void(0)";
			a.className = "botaomodocel";
			a.onclick = function (e) { e.stopPropagation(); window.trocarmodocel(); };
			a.appendChild(document.createTextNode(dado("vpc", "Versão computador")));
			barra.appendChild(a);
			b.insertBefore(barra, b.firstChild);
		}
		// Cabeçalho (menus do topo) sobe para antes das colunas: banner, navegação, conteúdo, personagem.
		var menu = document.querySelector(".celtopomenu"), barra2 = document.getElementById("celbarra");
		if (menu && !document.getElementById("celtopo")) {
			var topo = document.createElement("div");
			topo.id = "celtopo";
			topo.appendChild(menu);
			barra2.parentNode.insertBefore(topo, barra2.nextSibling);
		}
		colunas();
		encaixartudo();
		// O js/navegacao.js troca as regiões por AJAX (innerHTML do <center> de cada coluna): os botões ▲/▼ de cada painel
		// iam embora junto e a coluna que chegava vazia ficava sem cabeçalho. Refaz tudo (é idempotente) a cada troca.
		if (window.MutationObserver) {
			// Na hora (o callback roda antes do navegador pintar): com o atraso de 60 ms a tela nova aparecia
			// grande e só depois encolhia. As próprias mudanças daqui são descartadas (takeRecords) para não repetir.
			var obs = new MutationObserver(function () { colunas(); encaixartudo(); obs.takeRecords(); });
			obs.observe(b, { childList: true, subtree: true });
		}
		window.addEventListener("load", encaixartudo);
		revelar();
	}
	// A página fica invisível (classe celmontando, posta no <head>) até o layout de celular estar montado,
	// senão ela aparecia no tamanho de computador e depois mudava. celular.css revela sozinha em 2 s se o JS falhar.
	function revelar() { html.className = html.className.replace(/\s*celmontando/g, ""); }

	// Colunas laterais: cabeçalho recolhível (.celcab) e botão ▲/▼ por painel; marca a coluna vazia.
	function colunas() {
		var esq = document.querySelector("td.celesq"), dir = document.querySelector("td.celdir");
		[[esq, "esq", "e", dado("nav", "Mapa e navegação")], [dir, "dir", "d", dado("pers", "Personagem")]].forEach(function (c) {
			var td = c[0];
			if (!td) { return; }
			var cab = td.querySelector(":scope > .celcab");
			var vazio = (td.textContent.replace(/\s/g, "") === "" || (cab && td.textContent.replace(/\s/g, "") === cab.textContent.replace(/\s/g, "")));
			td.className = td.className.replace(/\s*celvazio/g, "") + (vazio ? " celvazio" : "");
			if (!vazio) { recolhivel(td, c[1], c[3], true); paineis(td, c[2]); }
		});
	}

	// Blocos de largura fixa (mapa da vila, tabelas largas) que não cabem na tela: diminui o bloco inteiro (zoom),
	// mantendo as posições internas. Só o mais externo que estoura é reduzido.
	function encaixar(raiz) {
		if (!raiz || !raiz.getBoundingClientRect) { return; }
		var lim = raiz.getBoundingClientRect(), dir = Math.min(lim.right, html.clientWidth);
		var fila = [].slice.call(raiz.children);
		while (fila.length) {
			var el = fila.shift();
			if (el.nodeType !== 1 || el.id === "explicmenu" || el.id === "dropmenu") { continue; }
			var r = el.getBoundingClientRect();
			if (r.width === 0 && r.height === 0 && el.children.length) { fila = fila.concat([].slice.call(el.children)); continue; }
			if (r.right <= dir + 1) { fila = fila.concat([].slice.call(el.children)); continue; }
			var cs = getComputedStyle(el);
			if (cs.position === "absolute" || cs.position === "fixed") { continue; }
			var w = el.offsetWidth || r.width, livre = dir - r.left;
			if (el.getAttribute("data-celz")) { continue; }
			if (w > 0 && livre > 40) {
				var z = Math.floor((livre / w) * 1000) / 1000;
				el.style.zoom = String(z); el.setAttribute("data-celz", String(z));
			}
		}
	}
	function encaixartudo() {
		if (!celular()) { return; }
		var c = document.querySelectorAll(".conteudomeio, td.celdir > center, body > *:not(div)");
		for (var i = 0; i < c.length; i++) { encaixar(c[i]); }
	}

	if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", montar); } else { montar(); }
})();
