<?php
// getheader.php :: Guarda no banco a última página visitada pelo jogador.
// O campo "pagina" é usado para voltar o jogador para onde ele estava depois de ações rápidas
// (desequipar item, usar mochila, enviar mensagem privada etc.).

global $userrow;

if ($userrow != false && isset($_SERVER["SCRIPT_NAME"]) && (!isset($_SERVER["REQUEST_METHOD"]) || $_SERVER["REQUEST_METHOD"] == "GET")) {

	// Só páginas de visualização entram com a rota: ações (comprar, viajar, apagar item...) nunca podem ser
	// repetidas pelo "voltar para onde estava", então nesses casos guardamos apenas o arquivo.
	$paginaatual = basename($_SERVER["SCRIPT_NAME"]);
	$rotasvisualizacao = array("banco","troca","quests","treinamento","missao","graduacao","fundir","jutsu","atributos","avatar","ler","msg","inn","buy","maps","fight","drop");
	$rotaatual = isset($_GET["do"]) && !is_array($_GET["do"]) ? $_GET["do"] : "";
	if ($rotaatual != "" && in_array($rotaatual, $rotasvisualizacao)) { $paginaatual .= "?do=" . $rotaatual; }
	if ($paginaatual == "fight.php" || $paginaatual == "explore.php" || $paginaatual == "towns.php") { $paginaatual = "index.php"; }
	// Só guarda páginas do próprio jogo (nome.php) e sem quebras de linha (evita injeção de cabeçalho).
	if (preg_match("/^[a-z0-9_]+\.php(\?[^\r\n]*)?$/i", $paginaatual) && strlen($paginaatual) <= 200) {
		$updatequery = doquery("UPDATE {{table}} SET pagina='".addslashes($paginaatual)."' WHERE id='".intval($userrow["id"])."' LIMIT 1","users");
	}
}

?>
