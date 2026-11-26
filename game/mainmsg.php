<?php // mainmsg.php :: Ações da janela flutuante de mensagens (Jutsu de Busca e envio de mensagens privadas).
// Nada é exibido aqui: o resultado é gravado em users.mainmsg e o jogador volta para a última página que visitou
// (users.pagina), onde templates/mostrarmainmsg.php desenha a janela e apaga o campo.
// Formatos de users.mainmsg:
//   "1"           -> formulário do Jutsu de Busca;
//   "2,,Nome"     -> formulário de mensagem privada (com o destinatário já preenchido);
//   "imagem#$%;TITULO#$%;conteudo" -> janela com um texto pronto (imagem em layoutnovo/dropmenu/grande/avatares/).
// O título e o conteúdo são gravados no formato neutro de tradução (i18n("Chave com %s", valor)) e o template
// os exibe com textoi18n(), no idioma de quem estiver lendo.

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();

if ($userrow == false) { header("Location: login.php?do=login"); die(); }
if ($userrow["authlevel"] == 2) { die(t("Sua conta foi bloqueada. Por favor tente novamente mais tarde.")); }

// Regras das mensagens privadas.
$pm_separador = "@#$%!;;;";   // Separador das mensagens dentro de users.caixadepm.
$pm_maxcaracteres = 100;      // Tamanho máximo de uma mensagem.
$pm_maxpalavra = 58;          // Anti-spam: maior palavra aceita (palavras gigantes quebram o layout).
$pm_intervalo = 5;            // Anti-spam: segundos mínimos entre duas mensagens para o mesmo jogador.
$pm_porminuto = 3;            // Anti-spam: máximo de mensagens para o mesmo jogador em 60 segundos.

$do = pegartexto($_GET, "do2");
if ($do == "usarjutsubusca") { usarjutsubusca(); }
elseif ($do == "enviarpm") { enviarpm(); }
voltarpagina();

function voltarpagina() { // Volta para a última página do jogo visitada (gravada e validada por templates/getheader.php).

	global $userrow;
	$pagina = (string)$userrow["pagina"];
	// Revalida: só "arquivo.php" do próprio jogo, com query string opcional e sem quebras de linha.
	if (!preg_match("/^[a-z0-9_]+\.php(\?[^\r\n]*)?$/i", $pagina) || $pagina == "mainmsg.php" || strpos($pagina, "mainmsg.php?") === 0) { $pagina = "index.php"; }
	header("Location: ./".$pagina);
	die();

}

function gravarmainmsg($valor) { // Grava o conteúdo da janela flutuante do jogador logado.

	global $userrow;
	doquery("UPDATE {{table}} SET mainmsg='".addslashes($valor)."' WHERE id='".intval($userrow["id"])."' LIMIT 1","users");

}

function janelamainmsg($imagem, $titulo, $conteudo) { // Monta o formato "imagem#$%;TITULO#$%;conteudo" (sem deixar o separador dentro das partes).

	$imagem = preg_replace("/[^a-z0-9_]/i", "", $imagem);
	$titulo = str_replace("#$%;", "", $titulo);
	$conteudo = str_replace("#$%;", "", $conteudo);
	return $imagem."#$%;".$titulo."#$%;".$conteudo;

}

function tamanhotexto($texto) { // Quantidade real de caracteres (o texto chega com htmlspecialchars aplicado).

	return mb_strlen(html_entity_decode($texto, ENT_QUOTES, 'UTF-8'), 'UTF-8');

}

function usarjutsubusca() { // Jutsu de Busca: mostra as coordenadas de outro jogador e se ele está online (custa 30 de Chakra).

	global $userrow;

	// Só quem completou o treinamento na Vila da Areia (jutsudebusca.php) pode usar.
	// (No PHP 8, '' == 0 é falso: por isso a comparação é com 1, e não "== 0" como antes.)
	if ($userrow["jutsudebuscahtml"] != 1) {
		gravarmainmsg(janelamainmsg("byakugan", i18n("JUTSU DE BUSCA"), i18n("<br />Você não pode usar esse jutsu sem ter treinado!")));
		voltarpagina();
	}

	// Sem POST: abre o formulário do jutsu na janela flutuante.
	if (!isset($_POST["submit"])) {
		gravarmainmsg("1");
		voltarpagina();
	}

	$nomedaprocura = trim(pegartexto($_POST, "nomedaprocura"));
	$pagina = "";
	$userpara = false;
	if ($nomedaprocura == "") { $pagina = i18n("<br />Digite o nome do jogador que você quer procurar."); }
	else {
		$userquery = doquery("SELECT charname,latitude,longitude,(onlinetime >= NOW() - INTERVAL 61 SECOND) AS online FROM {{table}} WHERE charname='$nomedaprocura' LIMIT 1","users");
		if (mysqli_num_rows($userquery) != 1) { $pagina = i18n("<br />Não existe nenhuma conta com esse Nome."); }
		else { $userpara = mysqli_fetch_array($userquery); }
	}
	if ($pagina == "" && $userrow["currentmp"] < 30) { $pagina = i18n("<br />Esse jutsu requer 30 de Chakra para ser usado."); }

	if ($pagina == "") {
		// Desconta o Chakra só se ainda houver 30 (evita gastar duas vezes com cliques repetidos).
		doquery("UPDATE {{table}} SET currentmp=currentmp-30 WHERE id='".intval($userrow["id"])."' AND currentmp>=30 LIMIT 1","users");

		// Coordenadas no formato do jogo: latitude N/S e longitude E/W.
		$latitude = intval($userpara["latitude"]);
		$longitude = intval($userpara["longitude"]);
		$latitude = ($latitude < 0) ? ($latitude * -1)."S" : $latitude."N";
		$longitude = ($longitude < 0) ? ($longitude * -1)."W" : $longitude."E";
		if ($userpara["online"] != 1) {
			$pagina = i18n("<br />O jogador %s está na coordenada: [%s,%s].<br />Este jogador está <font color=\"red\">Offline</font>. <a href=\"javascript: openmappopup('%s/%s')\"><img src=\"images/maximizar.gif\" border=\"0\" title=\"Mostrar Localização no Mapa\" alt=\"\" /></a>", $userpara["charname"], $latitude, $longitude, $latitude, $longitude);
		} else {
			$pagina = i18n("<br />O jogador %s está na coordenada: [%s,%s].<br />Este jogador está <font color=\"green\">Online</font>.", $userpara["charname"], $latitude, $longitude);
		}
	}

	gravarmainmsg(janelamainmsg("byakugan", i18n("JUTSU DE BUSCA ATIVADO"), $pagina));
	voltarpagina();

}

function enviarpm() { // Envia uma mensagem privada (gravada na caixa users.caixadepm do destinatário).

	global $userrow, $pm_separador, $pm_maxcaracteres, $pm_maxpalavra, $pm_intervalo, $pm_porminuto;

	// Sem POST: abre o formulário na janela flutuante, com o destinatário preenchido ("2,,Nome").
	if (!isset($_POST["submit"])) {
		$nomedochar = mb_substr(str_replace(",,", "", stripslashes(pegartexto($_GET, "nomedochar"))), 0, 60, "UTF-8");
		gravarmainmsg("2,,".$nomedochar);
		voltarpagina();
	}

	if (segundosmudo($userrow) > 0) { gravarmainmsg(janelamainmsg("naruto", i18n("ENVIAR MENSAGEM PRIVADA"), "<br />".avisomudo($userrow))); voltarpagina(); } // silenciado por um GM.

	$nomedaprocura = trim(pegartexto($_POST, "nomedaprocura"));
	// O texto chega com addslashes + htmlspecialchars: tira as barras (a query inteira é escapada no final).
	$texto = trim(stripslashes(pegartexto($_POST, "txtmensagem")));
	// O separador da caixa não pode aparecer no texto (criaria mensagens falsas). Repete até sumir.
	while (strpos($texto, $pm_separador) !== false) { $texto = str_replace($pm_separador, "", $texto); }

	// Cada erro vira uma chave completa de tradução: "<br />Não foi possível...</font> " + motivo.
	$falha = "<br /><font color=\"darkred\">Não foi possível enviar sua mensagem.</font> ";
	$pagina = "";
	$userpara = false;
	if (tamanhotexto($texto) < 1) { $pagina = i18n($falha."Escreva alguma coisa na sua mensagem."); }
	elseif (tamanhotexto($texto) > $pm_maxcaracteres) { $pagina = i18n($falha."A mensagem que você quer enviar tem mais de %s caracteres!", $pm_maxcaracteres); }
	else {
		foreach (preg_split("/\s+/u", html_entity_decode($texto, ENT_QUOTES, 'UTF-8')) as $palavra) {
			if (mb_strlen($palavra, 'UTF-8') > $pm_maxpalavra) { $pagina = i18n($falha."Você ativou nosso sistema de bloqueio de spam, enviando uma palavra muito grande, por favor não repita isso novamente."); }
		}
	}
	if ($pagina == "") {
		if ($nomedaprocura == "") { $pagina = i18n($falha."Digite o nome do jogador que vai receber a mensagem."); }
		else {
			$userquery = doquery("SELECT id,charname,caixadepm FROM {{table}} WHERE charname='$nomedaprocura' LIMIT 1","users");
			if (mysqli_num_rows($userquery) != 1) { $pagina = i18n($falha."Não existe nenhum usuário com esse Nome."); }
			else { $userpara = mysqli_fetch_array($userquery); }
		}
	}

	if ($pagina == "") {
		// Caixa do destinatário: sempre 25 posições (a mais antiga sai quando uma nova chega).
		$caixa = (string)$userpara["caixadepm"];
		if ($caixa == "None") { $caixa = ""; }
		$mensagens = explode($pm_separador, $caixa);
		if (count($mensagens) < 25) { $mensagens = array_merge(array_fill(0, 25 - count($mensagens), ""), $mensagens); }

		// Anti-spam por destinatário: cada mensagem guarda um comentário invisível "<!--pm:idremetente:horario-->".
		$agora = time();
		$recentes = 0;
		foreach ($mensagens as $mensagem) {
			if (preg_match("/^<!--pm:(\d+):(\d+)-->/", $mensagem, $marca) && $marca[1] == $userrow["id"]) {
				if ($marca[2] > $agora - $pm_intervalo) { $pagina = i18n($falha."Aguarde alguns segundos antes de enviar outra mensagem para esse jogador."); }
				if ($marca[2] > $agora - 60) { $recentes++; }
			}
		}
		if ($pagina == "" && $recentes >= $pm_porminuto) { $pagina = i18n($falha."Você já enviou muitas mensagens para esse jogador. Aguarde um minuto."); }
	}

	if ($pagina == "") {
		// Cor do remetente (administrador, tutor ou GM), como no chat e no fórum.
		$link = ""; $cor = ""; $cor2 = "";
		if ($userrow["authlevel"] == 1) { $link = " id=\"adm\" "; $cor = "<font color=\"green\">"; $cor2 = "</font>"; }
		elseif ($userrow["acesso"] == 2) { $link = " id=\"tutor\" "; $cor = "<font color=\"orange\">"; $cor2 = "</font>"; }
		elseif ($userrow["acesso"] == 3) { $link = " id=\"gm\" "; $cor = "<font color=\"blue\">"; $cor2 = "</font>"; }
		$remetente = htmlspecialchars($userrow["charname"], ENT_QUOTES, 'UTF-8');
		$novamensagem = "<!--pm:".intval($userrow["id"]).":".$agora."-->[$cor<b><a href=\"mainmsg.php?do2=enviarpm&amp;nomedochar=".urlencode($userrow["charname"])."\" $link title=\"Enviar Mensagem\">".$remetente."</a></b>$cor2] $cor".$texto.$cor2;

		$mensagens = array_slice($mensagens, -24);
		$mensagens[] = $novamensagem;
		doquery("UPDATE {{table}} SET caixadepm='".addslashes(implode($pm_separador, $mensagens))."', pmsnovas='1' WHERE id='".intval($userpara["id"])."' LIMIT 1","users");

		gravarmainmsg(janelamainmsg("naruto", i18n("ENVIAR MENSAGEM PRIVADA"), i18n("<br />Sua mensagem foi enviada com sucesso!")));
		voltarpagina();
	}

	gravarmainmsg(janelamainmsg("naruto", i18n("ENVIAR MENSAGEM PRIVADA"), $pagina));
	voltarpagina();

}

?>
