// navegacao.js :: Atualização dinâmica das páginas (AJAX, estilo "pjax"), só JavaScript nativo do navegador.
// Só é incluído pelo templates/primary.php quando control.atualizacaodinamica = 1.
// O servidor continua igual: cada clique busca a página inteira, mas só as regiões marcadas no layout
// (coluna da esquerda, conteúdo, coluna da direita, janela de mensagens e o título) são trocadas na tela.
// Qualquer situação estranha (link externo, upload, painel admin, login/logout, resposta que não é HTML,
// página sem as regiões, erro de rede) cai na navegação normal, então o jogo nunca fica "travado".
// Não há atualização periódica (polling): as colunas só mudam quando o jogador navega, como no modo clássico,
// para não aumentar a carga do servidor nem mudar o ritmo do jogo.
(function () {
	"use strict";
	if (!window.fetch || !window.DOMParser || !window.history || !history.pushState) { return; } // Navegador antigo: modo clássico.

	var REGIOES = ["regiaoesquerda", "regiaoconteudo", "regiaodireita", "mainmsg"];
	var ultimohtml = {};        // Último HTML recebido de cada região (para só trocar o que mudou).
	var carregando = false;     // Evita cliques/envios duplos enquanto uma página está sendo buscada.
	var temporefresh = null;    // Timer do <meta http-equiv="refresh"> da página atual (andar automático, descanso, duelo).
	var scriptatual = document.currentScript || document.querySelector("script[data-carregando]");
	var textocarregando = (scriptatual && scriptatual.getAttribute("data-carregando")) || "Carregando...";
	var selo = null;

	function mostrarcarregando(sim) { // Cursor de espera + pequeno selo "Carregando..." no canto da tela.
		carregando = sim;
		document.documentElement.style.cursor = sim ? "wait" : "";
		if (!selo) {
			selo = document.createElement("div");
			selo.style.cssText = "position:fixed;top:6px;right:6px;z-index:99;padding:3px 8px;background:#452202;color:#fff;font:11px tahoma,arial,sans-serif;border:1px solid #000;opacity:0.85;display:none";
			selo.appendChild(document.createTextNode(textocarregando));
			document.body.appendChild(selo);
		}
		selo.style.display = sim ? "block" : "none";
	}

	function urlpermitida(url) { // Decide se uma URL pode ser carregada dinamicamente.
		if (url.origin !== location.origin) { return false; }
		if (!/\.php$/i.test(url.pathname)) { return false; }
		if (/(^|\/)(admin|login)\.php$/i.test(url.pathname)) { return false; } // Painel e login/logout: sempre página inteira.
		if (/(^|[?&])(do=logout|idioma=)/i.test(url.search)) { return false; } // Trocar idioma muda os textos do <head>.
		return true;
	}

	function regioesdapagina(doc) { // Devolve as regiões da página recebida, ou null se faltar alguma.
		var achadas = {};
		for (var i = 0; i < REGIOES.length; i++) {
			var el = doc.getElementById(REGIOES[i]);
			if (!el || !document.getElementById(REGIOES[i])) { return null; }
			achadas[REGIOES[i]] = el;
		}
		return achadas;
	}

	function reexecutarscripts(el) { // Scripts inseridos via innerHTML não rodam: recria cada <script> para executar.
		var lista = el.querySelectorAll("script");
		for (var i = 0; i < lista.length; i++) {
			var velho = lista[i], novo = document.createElement("script");
			for (var j = 0; j < velho.attributes.length; j++) { novo.setAttribute(velho.attributes[j].name, velho.attributes[j].value); }
			novo.text = velho.text;
			velho.parentNode.replaceChild(novo, velho);
		}
	}

	function agendarrefresh(doc) { // Tira os <meta refresh> da página recebida e agenda uma navegação dinâmica no lugar.
		var metas = doc.querySelectorAll("meta[http-equiv]"), destino = null, espera = 0;
		for (var i = 0; i < metas.length; i++) {
			if (metas[i].getAttribute("http-equiv").toLowerCase() !== "refresh") { continue; }
			var m = /^\s*(\d+(?:\.\d+)?)\s*(?:[;,]\s*(?:url\s*=\s*)?['"]?([^'"]*)['"]?)?\s*$/i.exec(metas[i].getAttribute("content") || "");
			if (m && destino === null) { espera = parseFloat(m[1]); destino = m[2] ? m[2] : location.href; }
			metas[i].parentNode.removeChild(metas[i]);
		}
		if (destino !== null) {
			var url = new URL(destino, location.href);
			temporefresh = setTimeout(function () {
				temporefresh = null;
				if (urlpermitida(url)) { navegar(url.href, "GET", null, true, null); } else { location.assign(url.href); }
			}, espera * 1000);
		}
	}

	function trocartudo(texto, url) { // Página sem as regiões (ex.: erro, tela mínima): mostra a resposta inteira, sem pedir de novo.
		history.pushState(null, "", url);
		document.open();
		document.write(texto);
		document.close();
	}

	function alvodarolagem(veiodeformulario) { // Confirmação (Sim/Não, Confirmar) ou resposta de NPC a um formulário: para onde puxar a tela.
		var conf = document.querySelector("#regiaoconteudo .bj-cancelar, #regiaoconteudo button[type=reset], #regiaoconteudo [id^=confirmar]");
		if (conf) { return conf.closest(".falanpc") || document.querySelector("#regiaoconteudo .falanpc") || conf.closest(".cxb") || conf.closest("form") || conf; }
		if (veiodeformulario) { return document.querySelector("#regiaoconteudo .falanpc"); }
		return null;
	}

	function aplicar(texto, urlfinal, empilhar, veiodeformulario) { // Troca as regiões da página atual pelas da página recebida.
		var doc = new DOMParser().parseFromString(texto, "text/html");
		var regioes = regioesdapagina(doc);
		if (!regioes) { trocartudo(texto, urlfinal); return; }
		var rolagemx = window.scrollX || window.pageXOffset, rolagemy = window.scrollY || window.pageYOffset; // a tela fica onde estava
		agendarrefresh(doc);
		for (var nome in regioes) {
			var html = regioes[nome].innerHTML;
			if (ultimohtml[nome] === html && (nome === "regiaoesquerda" || nome === "regiaodireita")) { continue; } // Coluna igual: não mexe (evita piscar).
			var alvo = document.getElementById(nome);
			alvo.innerHTML = html;
			ultimohtml[nome] = html;
			reexecutarscripts(alvo);
		}
		document.title = doc.title;
		if (empilhar) { history.pushState({ navegacao: true }, "", urlfinal); }
		else if (urlfinal !== location.href) { history.replaceState({ navegacao: true }, "", urlfinal); }
		window.scrollTo(rolagemx, rolagemy); // não puxa a tela para cima ao receber a página (o navegador limita se a página ficou menor)
		// ...exceto quando a página nova pede uma confirmação ou traz a resposta de um NPC: aí a tela vai até o diálogo.
		var alvorolagem = alvodarolagem(veiodeformulario);
		if (alvorolagem) {
			var r = alvorolagem.getBoundingClientRect();
			if (r.top < 0 || r.top > window.innerHeight * 0.5) { window.scrollTo(rolagemx, Math.max(0, r.top + (window.scrollY || window.pageYOffset) - 10)); }
		}
	}

	// Busca uma página e aplica. metodo "GET" ou "POST"; dados = FormData (POST); form = formulário de origem (para o plano B).
	function navegar(url, metodo, dados, empilhar, form) {
		if (temporefresh) { clearTimeout(temporefresh); temporefresh = null; }
		mostrarcarregando(true);
		var opcoes = { method: metodo, credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } };
		if (metodo === "POST") { opcoes.body = dados; }
		fetch(url, opcoes).then(function (res) {
			var tipo = res.headers.get("Content-Type") || "";
			var urlfinal = res.url || url;
			if (metodo === "GET" && (!res.ok || tipo.indexOf("text/html") === -1 || !urlpermitida(new URL(urlfinal)))) {
				location.assign(urlfinal); // Resposta inesperada num GET: navegação normal até o endereço final.
				return;
			}
			return res.text().then(function (texto) {
				mostrarcarregando(false);
				// POST com resposta inesperada: mostra a resposta recebida (reenviar repetiria a ação).
				if (!res.ok || tipo.indexOf("text/html") === -1 || !urlpermitida(new URL(urlfinal))) { trocartudo(texto, urlfinal); }
				else { aplicar(texto, urlfinal, empilhar, !!form); }
			});
		}, function () {
			// Erro de rede (ou redirecionamento para outro site): navegação normal.
			mostrarcarregando(false);
			if (form) {
				if (form._botaonavegacao) { form.appendChild(form._botaonavegacao); }
				HTMLFormElement.prototype.submit.call(form);
			} else { location.assign(url); }
		});
	}

	// target="_top"/"_parent" (avisos do histórico) é a própria janela quando a página não está dentro de um quadro.
	function alvomesmajanela(alvo) { return alvo === "" || alvo === "_self" || ((alvo === "_top" || alvo === "_parent") && window.top === window.self); }

	// Para botões que navegam por JavaScript (onclick="irpara('...')", templates/primary.php): também sem recarregar a página.
	window.navegarjogo = function (endereco) {
		var url = new URL(endereco, location.href);
		if (!urlpermitida(url)) { location.assign(url.href); return; }
		if (!carregando) { navegar(url.href, "GET", null, true, null); }
	};

	document.addEventListener("click", function (e) { // Cliques em links do jogo.
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
		var a = e.target.closest ? e.target.closest("a[href]") : null;
		if (!a || a.hasAttribute("download") || a.hasAttribute("data-semajax")) { return; } // data-semajax: navegação normal (ex.: criar o convidado, que exige uma página inteira).
		var alvo = (a.getAttribute("target") || "").toLowerCase();
		if (!alvomesmajanela(alvo)) { return; }
		var href = a.getAttribute("href");
		if (/^\s*(javascript:|#|mailto:)/i.test(href)) { return; }
		var url = new URL(a.href, location.href);
		if (!urlpermitida(url)) { return; }
		e.preventDefault();
		if (carregando) { return; }
		navegar(url.href, "GET", null, true, null);
	});

	document.addEventListener("submit", function (e) { // Envios de formulários do jogo (GET e POST).
		if (e.defaultPrevented) { return; }
		var form = e.target, botao = e.submitter || null;
		var acao = (botao && botao.getAttribute("formaction")) ? botao.formAction : form.action;
		var metodo = ((botao && botao.getAttribute("formmethod")) || form.getAttribute("method") || "GET").toUpperCase();
		var alvo = (form.getAttribute("target") || "").toLowerCase();
		var url = new URL(acao || location.href, location.href);
		if (!alvomesmajanela(alvo) || !urlpermitida(url)) { return; }
		if ((form.getAttribute("enctype") || "").toLowerCase() === "multipart/form-data" || form.querySelector("input[type=file]")) { return; } // Upload: normal.
		e.preventDefault();
		if (carregando) { return; } // Já está enviando: ignora o clique repetido.
		var dados = new FormData(form);
		if (botao && botao.name) { dados.append(botao.name, botao.value); } // O botão clicado também vai, como no envio normal.
		if (metodo === "GET") {
			url.search = new URLSearchParams(dados).toString();
			navegar(url.href, "GET", null, true, null);
		} else {
			if (botao && botao.name) { // Plano B (erro de rede): o envio normal precisa saber qual botão foi usado.
				var oculto = form.querySelector("input[data-botaonavegacao]") || document.createElement("input");
				oculto.type = "hidden"; oculto.name = botao.name; oculto.value = botao.value; oculto.setAttribute("data-botaonavegacao", "1");
				form._botaonavegacao = oculto;
			}
			navegar(url.href, "POST", dados, true, form);
		}
	});

	window.addEventListener("popstate", function (e) { // Botões voltar/avançar: busca a página daquele endereço de novo.
		if (!e.state || !e.state.navegacao) { return; }
		if (!document.getElementById("regiaoconteudo")) { location.reload(); return; } // Página atual veio inteira (sem regiões).
		if (carregando) { return; }
		navegar(location.href, "GET", null, false, null);
	});

	// Estado inicial: guarda as regiões da página carregada e marca a entrada do histórico como dinâmica.
	for (var i = 0; i < REGIOES.length; i++) {
		var el = document.getElementById(REGIOES[i]);
		if (el) { ultimohtml[REGIOES[i]] = el.innerHTML; }
	}
	history.replaceState({ navegacao: true }, "", location.href);
})();
