// toque.js :: Dicas (tooltips) usáveis em celulares e tablets, só JavaScript nativo do navegador.
// No computador nada muda: as dicas continuam aparecendo com o mouse (onmouseover/onmouseout e title).
// Em aparelhos de toque (ou quando o clique veio de um toque), o 1º toque num elemento com dica mostra o conteúdo
// na mesma caixinha do computador, aberta no ponto tocado, e cancela a ação; o 2º toque no mesmo elemento faz a ação normal (link/clique);
// tocar em outro lugar fecha a caixinha. Usa delegação de eventos no window/document, então continua funcionando
// depois que o js/navegacao.js troca as regiões da página (AJAX).
// Aviso único "Toque uma vez para ver os detalhes...": guardado em users.avisotoque (toque.php) para quem está logado
// e no localStorage como reserva (dentro de try/catch).
(function () {
	"use strict";
	var script = document.currentScript || document.querySelector("script[data-toque]");
	function dado(n, padrao) { return (script && script.getAttribute("data-" + n)) || padrao; }

	var mq = function (q) { try { return !!(window.matchMedia && window.matchMedia(q).matches); } catch (e) { return false; } };
	var toqueprincipal = mq("(hover: none) and (pointer: coarse)") || (mq("(any-pointer: coarse)") && !mq("(any-hover: hover)"));
	var ultimotoque = 0;   // Momento do último touchstart (aparelhos híbridos: o clique que vem logo depois é um toque).
	var armado = null;     // Elemento cuja caixinha está aberta (o próximo toque nele faz a ação).

	function ehtoque() { return toqueprincipal || (Date.now() - ultimotoque) < 1000; }

	var cssfeito = false;
	function criarcss() {
		ajustarzoom();
		if (cssfeito) { return; }
		cssfeito = true;
		var css = document.createElement("style");
		css.appendChild(document.createTextNode(
			"#toqueaviso{position:fixed;left:50%;top:12px;transform:translateX(-50%) scale(var(--toquez,1));transform-origin:top center;z-index:101;width:min(92vw / var(--toquez,1),480px);box-sizing:border-box;background:#452202;color:#fff;" +
			"border:2px solid #e4d094;border-radius:6px;padding:12px 14px;font:15px tahoma,arial,sans-serif;box-shadow:0 4px 14px rgba(0,0,0,.5)}" +
			"#toqueaviso b{display:block;margin-bottom:6px;font-size:16px}" +
			"#toqueaviso button{margin-top:10px;font:bold 15px arial,sans-serif;padding:6px 16px;background:#e4d094;color:#452202;border:0;border-radius:4px}"));
		document.head.appendChild(css);
	}

	function ajustarzoom() { // Páginas sem <meta viewport> aparecem reduzidas no celular: aumenta o aviso na mesma proporção.
		var z = 1;
		try { if (window.visualViewport && visualViewport.scale) { z = 1 / visualViewport.scale; } } catch (e) {}
		if (!(z > 0) || z < 1) { z = 1; } if (z > 4) { z = 4; }
		document.documentElement.style.setProperty("--toquez", String(Math.round(z * 100) / 100));
	}

	function caixa(id) { return document.getElementById(id); }
	function limpar(ids) { for (var i = 0; i < ids.length; i++) { var d = caixa(ids[i]); if (d) { d.innerHTML = ""; } } }
	var abriudrop = false;
	function fechar() { armado = null; limpar(abriudrop ? ["explicmenu", "dropmenu"] : ["explicmenu"]); abriudrop = false; }

	// Mantém a caixinha dentro da área visível (no toque ela abre no ponto tocado, que pode estar perto da borda).
	function encaixar(id) {
		var d = caixa(id); if (!d || !d.firstChild) { return; }
		var r = d.getBoundingClientRect(), vw = document.documentElement.clientWidth, vh = window.innerHeight;
		var x = parseInt(d.style.left, 10) || 0, y = parseInt(d.style.top, 10) || 0;
		if (r.right > vw - 4) { x -= (r.right - vw + 4); }
		if (r.left < 4) { x += (4 - r.left); }
		if (r.bottom > vh - 4 && r.height < vh) { y -= (r.bottom - vh + 4); }
		d.style.left = x + "px"; d.style.top = y + "px";
	}

	// 1º toque: abre a mesma caixinha do computador (o onmouseover do elemento), no ponto tocado.
	// Elementos só com title ganham uma caixinha no mesmo estilo.
	function abrirdica(el, e) {
		window.tempX = e.pageX || 0; window.tempY = e.pageY || 0;
		limpar(["explicmenu"]);
		var ex = caixa("explicmenu"), dr = caixa("dropmenu"), antesdr = dr ? dr.innerHTML : "";
		if (el.hasAttribute("onmouseover") && typeof el.onmouseover === "function") {
			try { el.onmouseover.call(el, { type: "mouseover", target: el, clientX: e.clientX, clientY: e.clientY, pageX: e.pageX, pageY: e.pageY }); } catch (err) {}
		}
		var temex = ex && ex.innerHTML.replace(/\s|&nbsp;/g, ""), temdr = dr && dr.innerHTML !== antesdr && dr.innerHTML.replace(/\s|&nbsp;/g, "");
		if (!temex && !temdr && ex) {
			var t = el.getAttribute("title") || el.getAttribute("data-toquetitle");
			if (!t) { return false; }
			var s = document.createElement("div"); s.textContent = t;
			ex.style.left = Math.max(4, window.tempX - 87) + "px"; ex.style.top = (window.tempY + 18) + "px";
			ex.innerHTML = "<div style=\"position:relative;width:174px\"><div style=\"background-image:url(layoutnovo/dropmenu/transparente/cima.png);height:2px\"></div><div style=\"background-image:url(layoutnovo/dropmenu/transparente/fundotabela.png);background-repeat:repeat-y;padding:5px 11px 0\">" + s.innerHTML + "<div style=\"background-image:url(layoutnovo/dropmenu/transparente/fim.png);height:7px;margin:3px -11px 0\"></div></div></div>";
			temex = true;
		}
		if (temex) { encaixar("explicmenu"); }
		if (temdr) { encaixar("dropmenu"); abriudrop = true; }
		return !!(temex || temdr);
	}

	// Dica "rica" = a caixinha de detalhes do jogo (explicdrop/menudrop...). Trocas de imagem no hover (ex.: ícones de PM) não contam.
	function dicarica(el) { return /explic|menudrop|dropmenu|mostrarexplic/i.test(el.getAttribute("onmouseover") || ""); }
	function clicavel(el) { // Link, botão ou algo com ação de clique: o toque deve agir direto quando a dica é só um rótulo.
		for (var n = el; n && n !== document.body; n = n.parentNode) {
			if (n.tagName === "A" && n.hasAttribute("href")) { return true; }
			if (n.tagName === "BUTTON" || (n.tagName === "INPUT" && /^(submit|button|image|checkbox|radio|reset)$/i.test(n.type)) || n.hasAttribute("onclick")) { return true; }
		}
		return false;
	}
	// Áreas onde o toque sempre age direto (sem o 1º toque de dica): minimapa, foto do avatar, botão de pegar o drop,
	// botões de ajuda/fechar/voltar das janelas, setas de direção e as barras de comando. Marque outras com data-toquedireto.
	var DIRETO = "[data-toquedireto], #minimapa, #minimapa *, a[href*='do=andar'], a[href*='outros.php?do=avatar'], a[href*='do=drop'], a[href*='do=move'], " +
		"a[href*='menuprincipal'], a[href*='fecharjogadores'], a[href*='mostrarjogadores'], a[href*='login.php?do=logout'], a[href*='fecharexplic'], img[title='Ajuda'], img[title='Help'], " +
		"img[title='Fechar'], img[title='Close'], img[title='Voltar'], img[title='Back']";

	function alvo(no) { // Elemento com dica mais próximo do toque (campos de digitação ficam de fora).
		while (no && no.nodeType !== 1) { no = no.parentNode; }
		for (; no && no !== document.body && no !== document.documentElement; no = no.parentNode) {
			if (no.id === "explicmenu" || no.id === "dropmenu" || no.id === "toqueaviso") { return "caixa"; }
			var tag = no.tagName;
			if (tag === "TEXTAREA" || tag === "SELECT" || tag === "OPTION" || (tag === "INPUT" && !/^(submit|button|image|checkbox|radio|reset)$/i.test(no.type))) { return null; }
			if (no.matches && no.matches(DIRETO)) { return null; }
			if (no.hasAttribute("onmouseover") && dicarica(no)) { return no; } // equipamentos, itens, barras com caixinha: dica primeiro.
			if (no.hasAttribute("title") || no.hasAttribute("data-toquetitle") || no.hasAttribute("onmouseover")) {
				// Só um rótulo (title): num link/botão age direto; em algo que não clica (barras, ícones das vilas) mostra o rótulo.
				return clicavel(no) ? null : no;
			}
		}
		return null;
	}

	window.addEventListener("touchstart", function () { ultimotoque = Date.now(); }, { capture: true, passive: true });

	// O toque gera mouseover/mouseout sintéticos que abririam ou fechariam a caixinha fora de hora: ignorados em modo toque.
	["mouseover", "mouseout"].forEach(function (tipo) {
		window.addEventListener(tipo, function (e) { if (ehtoque()) { e.stopImmediatePropagation(); } }, true);
	});

	window.addEventListener("click", function (e) {
		if (!ehtoque()) { return; }
		var el = alvo(e.target);
		if (el === "caixa") { return; } // Links dentro da caixinha funcionam direto.
		if (el && el === armado) { fechar(); return; } // 2º toque: ação normal.
		if (armado && el !== armado) { fechar(); }
		if (el && abrirdica(el, e)) {
			e.preventDefault(); e.stopImmediatePropagation();
			armado = el;
			return;
		}
		if (armado) { fechar(); }
	}, true);

	// Clique/toque fora das caixinhas (explicmenu, dropmenu e o que tiver data-fechafora) fecha as que já estavam abertas antes dele.
	// Se o próprio clique abriu ou trocou a caixa (ex.: tocar no nome de um personagem), ela fica: só fecha se o conteúdo não mudou.
	var FORA = ["explicmenu", "dropmenu"];
	function caixasfora() {
		var l = FORA.map(caixa).filter(function (d) { return !!d; });
		var extras = document.querySelectorAll("[data-fechafora]");
		for (var i = 0; i < extras.length; i++) { l.push(extras[i]); }
		return l;
	}
	function aberta(d) { return d.innerHTML.replace(/\s|&nbsp;/g, "") !== "" && d.style.display !== "none"; }
	function forafechar(e) {
		var alvos = caixasfora().filter(function (d) { return aberta(d) && !d.contains(e.target); });
		if (!alvos.length) { return; }
		var antes = alvos.map(function (d) { return d.innerHTML; });
		setTimeout(function () {
			alvos.forEach(function (d, i) {
				if (d.innerHTML !== antes[i]) { return; } // o clique abriu algo novo nela
				if (d.hasAttribute("data-fechafora")) { d.style.display = "none"; } else { d.innerHTML = ""; }
				if (d.id === "explicmenu" || d.id === "dropmenu") { armado = null; }
			});
		}, 0);
	}
	window.addEventListener("click", forafechar, true);
	window.addEventListener("touchend", function (e) { if (e.target && e.target.closest && !e.target.closest("a,button,input,select,textarea,[onclick]")) { forafechar(e); } }, { capture: true, passive: true });

	// Aviso único (só em aparelhos de toque).
	// Só para quem está logado (antes do login não aparece): uma vez por personagem, guardado em users.avisotoque.
	function avisovisto() { return dado("visto", "") === "1" || dado("csrf", "") === ""; }
	function marcarvisto() {
		var csrf = dado("csrf", "");
		if (csrf && window.fetch) {
			try { fetch("toque.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: "csrf=" + encodeURIComponent(csrf) }).catch(function () {}); } catch (e) {}
		}
	}
	function mostraraviso() {
		if (!toqueprincipal || avisovisto() || !document.body) { return; }
		criarcss();
		var a = document.createElement("div");
		a.id = "toqueaviso"; a.setAttribute("role", "status");
		var b = document.createElement("b"); b.textContent = dado("aviso", "Toque uma vez para ver os detalhes, toque de novo para usar.");
		var p = document.createElement("div"); p.textContent = dado("avisoextra", "");
		var lista = document.createElement("ul"); lista.style.cssText = "margin:8px 0 0;padding-left:18px;text-align:left";
		dado("avisolista", "").split("|").forEach(function (t) { if (t) { var li = document.createElement("li"); li.textContent = t; li.style.margin = "3px 0"; lista.appendChild(li); } });
		var ok = document.createElement("button"); ok.type = "button"; ok.textContent = dado("entendi", "OK");
		ok.addEventListener("click", function () { if (a.parentNode) { a.parentNode.removeChild(a); } });
		a.appendChild(b); a.appendChild(p); a.appendChild(lista); a.appendChild(ok);
		document.body.appendChild(a);
		marcarvisto();
	}
	if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", mostraraviso); } else { mostraraviso(); }
})();
