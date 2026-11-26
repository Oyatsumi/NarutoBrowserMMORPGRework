<?php // forum.php :: Fórum interno do jogo (lista de tópicos, leitura, respostas, criação e exclusão de tópicos).

include('lib.php');
include('cookies.php');
$link = opendb();
$userrow = checkcookies();
if ($userrow == false) { display(t("Esse fórum é reservado apenas à jogadores registrados."), t("Fórum")); die(); }
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

// Jogo fechado (somente administradores entram).
if (($controlrow["gameopen"] == 0) && ($userrow['authlevel'] != 1)) { display(t("O jogo está fechado para manutenção no momento, por favor volte mais tarde."), t("Jogo Fechado")); die(); }
// Obriga a verificação do e-mail, se estiver ativada.
if (verificaremail() && $userrow["verify"] != 1) { header("Location: users.php?do=verify"); die(); }
// Conta bloqueada.
if ($userrow["authlevel"] == 2) { die(t("Sua conta foi bloqueada. Por favor tente novamente mais tarde.")); }

// Regras do fórum.
$forum_intervalo = 30;           // Segundos mínimos entre duas mensagens do mesmo jogador (anti-flood).
$forum_maxtitulo = 50;           // Tamanho máximo do título (em caracteres).
$forum_maxmensagem = 2000;       // Tamanho máximo da mensagem (em caracteres).
$forum_portopico = 30;           // Tópicos por página na lista.
$forum_porresposta = 15;         // Mensagens por página dentro de um tópico.
// Categorias aceitas (valor gravado no banco => nome exibido, traduzido na hora de mostrar). "geral" = sem categoria / todas.
$forum_categorias = array("geral"=>"Sem Categoria", "duvidas"=>"Dúvidas", "tutoriais"=>"Tutoriais", "bate-papo"=>"Bate-Papo", "reclamacoes"=>"Reclamações");

// Rotas no formato "do=acao:param1:param2" (padrão do jogo).
if (isset($_GET["do"])) {
	$do = array_pad(explode(":", pegartexto($_GET, "do")), 3, "0");

	if ($do[0] == "thread") { showthread(intval($do[1]), intval($do[2])); }
	elseif ($do[0] == "new") { newthread(); }
	elseif ($do[0] == "reply") { reply(); }
	elseif ($do[0] == "list") { donothing(intval($do[1])); }
	elseif ($do[0] == "deletar") { deletar(intval($do[1])); }
	elseif ($do[0] == "msg") { msg(); }
	else { donothing(0); }

} else { donothing(0); }

function forum_titulo() { // Cabeçalho com a imagem do fórum, comum a todas as páginas.

	return "<center><table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/title_forum.gif\" alt=\"".t("Fórum")."\" /></center></td></tr></table></center>";

}

function forum_aviso() { // Mensagem curta vinda da URL (?conteudo=...), já escapada pelo tratamento global de entradas.

	$conteudo = lermsg("conteudo");
	if ($conteudo == "") { return ""; }
	return "<font color=\"brown\"><center>".strip_tags(stripslashes($conteudo))."</center></font><br />";

}

function forum_voltar($mensagem, $destino="forum.php") { // Redireciona para uma página do fórum mostrando um aviso (já traduzido com t()/tf()).

	$separador = (strpos($destino, "?") === false) ? "?" : "&";
	header("Location: ".$destino.$separador."conteudo=".rawurlencode($mensagem));
	die();

}

function forum_tamanho($texto) { // Quantidade real de caracteres digitados (o texto chega com addslashes + htmlspecialchars).

	return mb_strlen(html_entity_decode(stripslashes($texto), ENT_QUOTES, 'UTF-8'), 'UTF-8');

}

function forum_floodou() { // true se o jogador postou há menos de $forum_intervalo segundos (anti-flood).

	global $userrow, $forum_intervalo;
	$query = doquery("SELECT id FROM {{table}} WHERE author='".addslashes($userrow["charname"])."' AND postdate > (NOW() - INTERVAL ".intval($forum_intervalo)." SECOND) LIMIT 1", "forum");
	return (mysqli_num_rows($query) > 0);

}

function forum_estiloautor($charname, $escuro=false) { // Cor e id CSS do nome do autor (administrador, tutor ou GM). Guarda em cache para não repetir consultas.

	static $cache = array();
	if (!isset($cache[$charname])) {
		$userquery = doquery("SELECT authlevel,acesso FROM {{table}} WHERE charname='".addslashes($charname)."' LIMIT 1", "users");
		$cache[$charname] = mysqli_fetch_array($userquery);
	}
	$autor = $cache[$charname];
	$estilo = array("link"=>"", "cor"=>"", "cor2"=>"");
	if (!$autor) { return $estilo; }
	if ($autor["authlevel"] == 1) { $estilo["link"] = " id=\"adm\" "; $cor = ($escuro) ? "darkgreen" : "green"; }
	elseif ($autor["acesso"] == 2) { $estilo["link"] = " id=\"tutor\" "; $cor = ($escuro) ? "darkorange" : "orange"; }
	elseif ($autor["acesso"] == 3) { $estilo["link"] = " id=\"gm\" "; $cor = ($escuro) ? "darkblue" : "blue"; }
	else { return $estilo; }
	$estilo["cor"] = "<font color=\"$cor\">";
	$estilo["cor2"] = "</font>";
	return $estilo;

}

function forum_podeapagar($row) { // O autor da mensagem, um administrador (authlevel 1) ou um GM (moderação) podem apagar.

	global $userrow;
	return ($userrow["authlevel"] == 1 || ehgm($userrow) || $row["author"] === $userrow["charname"]);

}

function forum_linkapagar($row, $titulo) { // Link "x" com confirmação para apagar um tópico/resposta (leva o token anti-CSRF, pois é um GET).

	if (!forum_podeapagar($row)) { return ""; }
	return " <a href=\"javascript: yesorno('vermelho', '".tf("Deletar %s?", $titulo)."', 'forum.php?do=deletar:".intval($row["id"])."&amp;csrf=".csrf_token()."', 'javascript: fecharexplic()');\" title=\"".tf("Deletar %s", $titulo)."\" id=\"vermelho\">x</a>";

}

function forum_paginas($total, $porpagina, $atual, $url) { // Links "Páginas: 1 2 3" (o $url recebe o início de cada página no final).

	if ($total <= $porpagina) { return ""; }
	$html = t("Páginas:")." ";
	for ($i = 0; $i * $porpagina < $total; $i++) {
		$inicio = $i * $porpagina;
		if ($inicio == $atual) { $html .= "<b>".($i + 1)."</b> "; }
		else { $html .= "<a href=\"".$url.$inicio."\">".($i + 1)."</a> "; }
	}
	return $html;

}

function donothing($start=0) { // Lista os tópicos (mais recentes primeiro), com filtro opcional por categoria.

	global $userrow, $forum_categorias, $forum_portopico;

	$start = max(0, intval($start));

	// Categoria escolhida no select (somente valores da lista branca chegam à query).
	$categoria = pegartexto($_POST, "categoria", pegartexto($_GET, "categoria"));
	if ($categoria == "geral" || !isset($forum_categorias[$categoria])) { $categoria = ""; }
	$filtro = ($categoria != "") ? "AND categoria='".$categoria."'" : "";

	$totalquery = doquery("SELECT COUNT(*) AS total FROM {{table}} WHERE parent='0' $filtro", "forum");
	$totalrow = mysqli_fetch_array($totalquery);
	$query = doquery("SELECT * FROM {{table}} WHERE parent='0' $filtro ORDER BY newpostdate DESC LIMIT $start,".intval($forum_portopico), "forum");

	$page = forum_titulo().forum_aviso()."<table width=\"100%\"><tr><td style=\"padding:1px; background-color:black;\"><table width=\"100%\" style=\"margin:0px;\" cellspacing=\"1\" cellpadding=\"3\"><tr><td colspan=\"3\" style=\"background-color:#452202;\"><center><font color=\"white\">".t("Tópicos Criados")."</font></center></td></tr><tr></tr><tr><th width=\"50%\" style=\"background-color:#E4D094;\">".t("Tópico")."</th><th width=\"10%\" style=\"background-color:#E4D094;\">".t("Respostas")."</th><th style=\"background-color:#E4D094;\">".t("Último Post")."</th></tr>\n";
	if (mysqli_num_rows($query) == 0) {
		$page .= "<tr><td style=\"background-color:#FFF1C7;\" colspan=\"3\"><b>".t("Nenhum tópico nesse fórum ou categoria.")."</b></td></tr>\n";
	} else {
		while ($row = mysqli_fetch_array($query)) {
			$estilo = forum_estiloautor($row["author"]);
			$page .= "<tr><td style=\"background-color:#FFF1C7;\"><a ".$estilo["link"]." href=\"forum.php?do=thread:".intval($row["id"]).":0\">".$row["title"]."</a></td><td style=\"background-color:#FFF1C7;\">".intval($row["replies"])."</td><td style=\"background-color:#FFF1C7;\">".padraodate($row["newpostdate"]).forum_linkapagar($row, t("Tópico"))."</td></tr>\n";
		}
	}
	$paginas = forum_paginas($totalrow["total"], $forum_portopico, $start, "forum.php?categoria=".urlencode($categoria)."&amp;do=list:");
	$page .= "</table></td></tr></table>".(($paginas != "") ? "<center>$paginas</center>" : "")."<center><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td>
<form name=\"categoriaselecao\" action=\"forum.php\" method=\"post\" >
<select name=\"categoria\" style=\"width:170px\" onchange=\"if (this.form.requestSubmit) { this.form.requestSubmit(); } else { this.form.submit(); }\">
<option selected=\"selected\" value=\"\">".t("Categoria")."</option>
<option value=\"geral\">".t("Todos")."</option>
<option value=\"duvidas\">".t("Dúvidas")."</option>
<option value=\"tutoriais\">".t("Tutoriais")."</option>
<option value=\"bate-papo\">".t("Bate-Papo")."</option>
<option value=\"reclamacoes\">".t("Reclamações")."</option>
</select>
</form></td><td>

<a href=\"forum.php?do=new\"><img src=\"images/novotopico.gif\" title=\"".t("Criar Novo Tópico")."\" border=\"0\" style=\"padding-top:2px\" alt=\"".t("Novo Tópico")."\" /></a></td></tr></table></center>";

	display($page, t("Fórum"));

}

function showthread($id, $start) { // Mostra um tópico e suas respostas (15 por página) com o formulário de resposta.

	global $userrow, $forum_porresposta;

	$start = max(0, intval($start));
	$query2 = doquery("SELECT id,title FROM {{table}} WHERE id='".intval($id)."' AND parent='0' LIMIT 1", "forum");
	if (mysqli_num_rows($query2) != 1) { forum_voltar(t("Esse tópico não existe ou foi apagado.")); }
	$row2 = mysqli_fetch_array($query2);

	$totalquery = doquery("SELECT COUNT(*) AS total FROM {{table}} WHERE id='".intval($id)."' OR parent='".intval($id)."'", "forum");
	$totalrow = mysqli_fetch_array($totalquery);
	$query = doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' OR parent='".intval($id)."' ORDER BY id LIMIT $start,".intval($forum_porresposta), "forum");

	$page = forum_titulo().forum_aviso()."<table width=\"100%\"><tr><td style=\"padding:1px; background-color:black;\"><table width=\"100%\" style=\"margin:0px;\" cellspacing=\"1\" cellpadding=\"3\"><tr><td colspan=\"3\" style=\"background-color:#452202;\"><font color=\"white\"><center>".t("Tópico e Respostas")."</center></font></td></tr><tr><td colspan=\"2\" style=\"background-color:#E4D094;\"><b><a href=\"forum.php\">".t("Início")."</a> <img src=\"images/seta.gif\" alt=\"\" /> ".$row2["title"]."</b></td></tr>\n";
	$escuro = false; // As linhas se alternam entre cores normais e escuras para o nome do autor.
	while ($row = mysqli_fetch_array($query)) {
		$estilo = forum_estiloautor($row["author"], $escuro);
		// O nome vai dentro de uma string JavaScript em um atributo HTML: escapa para JS e depois para HTML.
		$autorjs = htmlspecialchars(addslashes($row["author"]), ENT_QUOTES, 'UTF-8');
		$page .= "<tr><td width=\"25%\" style=\"background-color:#FFF1C7; vertical-align:top;\"><span class=\"small\"><b>".$estilo["cor"]."<a ".$estilo["link"]." href=\"javascript: opcaochar('".$autorjs."');\">".$row["author"]."</a>".$estilo["cor2"].forum_linkapagar($row, ($row["parent"] == 0) ? t("Tópico") : t("Resposta"))."</b><br />".prettyforumdate($row["postdate"])."</span></td><td style=\"background-color:#FFF1C7; vertical-align:top;\">".nl2br($row["content"])."</td></tr>\n";
		$escuro = !$escuro;
	}
	$page .= "</table></td></tr></table>";
	$paginas = forum_paginas($totalrow["total"], $forum_porresposta, $start, "forum.php?do=thread:".intval($id).":");
	if ($paginas != "") { $page .= "<center>$paginas</center>"; }
	$page .= "<br /><table width=\"100%\"><tr><td><b>".t("Responder esse Tópico:")."</b><br /><form action=\"forum.php?do=reply\" method=\"post\"><input type=\"hidden\" name=\"parent\" value=\"".intval($id)."\" /><textarea name=\"content\" rows=\"7\" cols=\"40\"></textarea><br /><input class=\"btnjogo bj-confirmar\" type=\"submit\" name=\"submit\" value=\"".t("Enviar")."\" /> <input class=\"btnjogo bj-cancelar\" type=\"reset\" name=\"reset\" value=\"".t("Apagar")."\" /></form></td></tr></table>";

	display($page, t("Fórum"));

}

function reply() { // Grava uma resposta em um tópico existente.

	global $userrow, $forum_maxmensagem, $forum_intervalo, $forum_porresposta;

	if (!isset($_POST["submit"])) { header("Location: forum.php"); die(); }
	if (segundosmudo($userrow) > 0) { display(avisomudo($userrow), t("Fórum")); } // silenciado por um GM.
	$parent = pegarint($_POST, "parent");
	$content = trim(pegartexto($_POST, "content"));

	// O tópico precisa existir e ser um tópico (não uma resposta).
	$parentquery = doquery("SELECT id,title FROM {{table}} WHERE id='".intval($parent)."' AND parent='0' LIMIT 1", "forum");
	if (mysqli_num_rows($parentquery) != 1) { forum_voltar(t("Esse tópico não existe ou foi apagado.")); }
	$parentrow = mysqli_fetch_array($parentquery);
	$voltar = "forum.php?do=thread:".intval($parent).":0";

	if (forum_tamanho($content) < 1) { forum_voltar(t("Escreva alguma coisa antes de enviar sua resposta."), $voltar); }
	if (forum_tamanho($content) > $forum_maxmensagem) { forum_voltar(tf("Sua resposta é grande demais (máximo de %s caracteres).", $forum_maxmensagem), $voltar); }
	if (forum_floodou()) { forum_voltar(tf("Aguarde %s segundos entre uma mensagem e outra.", $forum_intervalo), $voltar); }

	// O título da resposta é montado aqui (antes vinha de um campo hidden que podia ser alterado). Coluna: varchar(100).
	$title = "Re: ".$parentrow["title"];
	if (strlen($title) > 100) { $title = $parentrow["title"]; }

	doquery("INSERT INTO {{table}} SET postdate=NOW(),newpostdate=NOW(),author='".addslashes($userrow["charname"])."',parent='".intval($parent)."',replies='0',title='".addslashes($title)."',content='$content'", "forum");
	doquery("UPDATE {{table}} SET newpostdate=NOW(),replies=replies+1 WHERE id='".intval($parent)."' LIMIT 1", "forum");

	// Vai direto para a última página do tópico, onde a resposta aparece.
	$totalquery = doquery("SELECT COUNT(*) AS total FROM {{table}} WHERE id='".intval($parent)."' OR parent='".intval($parent)."'", "forum");
	$totalrow = mysqli_fetch_array($totalquery);
	$ultima = floor(($totalrow["total"] - 1) / $forum_porresposta) * $forum_porresposta;
	header("Location: forum.php?do=thread:".intval($parent).":".intval($ultima));
	die();

}

function newthread() { // Formulário e gravação de um novo tópico.

	global $userrow, $forum_categorias, $forum_maxtitulo, $forum_maxmensagem, $forum_intervalo;

	if (isset($_POST["submit"]) && segundosmudo($userrow) > 0) { display(avisomudo($userrow), t("Fórum")); } // silenciado por um GM.
	if (isset($_POST["submit"])) {
		$title = trim(pegartexto($_POST, "title"));
		$content = trim(pegartexto($_POST, "content"));
		$categoria = pegartexto($_POST, "categoria", "geral");
		if (!isset($forum_categorias[$categoria])) { $categoria = "geral"; }

		if (forum_tamanho($title) < 1) { forum_voltar(t("Dê um título ao seu tópico."), "forum.php?do=new"); }
		// O título é gravado já escapado (&#039; etc.), por isso também se confere o tamanho gravado (coluna varchar(100)).
		if (forum_tamanho($title) > $forum_maxtitulo || strlen(stripslashes($title)) > 100) { forum_voltar(t("O título do tópico é grande demais."), "forum.php?do=new"); }
		if (forum_tamanho($content) < 1) { forum_voltar(t("Escreva a mensagem do seu tópico."), "forum.php?do=new"); }
		if (forum_tamanho($content) > $forum_maxmensagem) { forum_voltar(tf("A mensagem do seu tópico é grande demais (máximo de %s caracteres).", $forum_maxmensagem), "forum.php?do=new"); }
		if (forum_floodou()) { forum_voltar(tf("Aguarde %s segundos entre uma mensagem e outra.", $forum_intervalo), "forum.php?do=new"); }

		doquery("INSERT INTO {{table}} SET postdate=NOW(),newpostdate=NOW(),author='".addslashes($userrow["charname"])."',parent='0',replies='0',title='$title',content='$content',categoria='$categoria'", "forum");
		header("Location: forum.php");
		die();
	}

	$opcoes = "";
	foreach ($forum_categorias as $valor => $nome) { $opcoes .= "<option value=\"$valor\">".t($nome)."</option>\n"; }

	$page = forum_titulo().forum_aviso()."<table width=\"100%\"><tr><td><table width=\"100%\" style=\"margin:0px;border: 1px solid black;\" cellspacing=\"0\" cellpadding=\"0\"><tr><td style=\"background-color:#452202;\"><font color=\"white\"><center>".t("Menu")."</center></font></td></tr><tr><td colspan=\"2\" style=\"background-color:#E4D094;border:1px #000000 solid\"><b><a href=\"forum.php\">".t("Início")."</a> <img src=\"images/seta.gif\" alt=\"\" /> ".t("Criando Novo Tópico")."</b></td></tr></table><br /><form action=\"forum.php?do=new\" method=\"post\">".t("Título do Tópico:")."<br /><input type=\"text\" name=\"title\" size=\"50\" maxlength=\"$forum_maxtitulo\" /><br /><br />
".t("Categoria:")."<br />
<select name=\"categoria\" style=\"width:170px\">
$opcoes</select>
<br /><br />
".t("Mensagem:")."<br />
<textarea name=\"content\" rows=\"5\" cols=\"40\"></textarea><br /><br /><input class=\"btnjogo bj-confirmar\" type=\"submit\" name=\"submit\" value=\"".t("Enviar")."\" /> <input class=\"btnjogo bj-cancelar\" type=\"reset\" name=\"reset\" value=\"".t("Apagar")."\" /></form></td></tr></table>";
	display($page, t("Fórum"));

}

function deletar($id) { // Apaga um tópico (com todas as respostas) ou uma única resposta.

	global $userrow;

	// O link de exclusão é um GET: exige o token para que um link externo não apague nada em nome do jogador.
	if (!csrf_confere()) { forum_voltar(t("Não foi possível confirmar a exclusão. Tente novamente.")); }

	$query = doquery("SELECT id,author,parent,title,content FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "forum");
	if (mysqli_num_rows($query) != 1) { forum_voltar(t("Essa mensagem não existe ou já foi apagada.")); }
	$row = mysqli_fetch_array($query);
	if (!forum_podeapagar($row)) { forum_voltar(t("Você não tem permissão pra isso.")); }

	// Moderação (admin/GM apagando mensagem de outro jogador) fica no registro de auditoria.
	if ($row["author"] !== $userrow["charname"]) {
		require_once(__DIR__."/painelseguranca.php");
		registrarlog("forum_apagar", intval($row["id"]), "autor: ".$row["author"]." | ".(($row["parent"] == 0) ? "tópico: ".$row["title"] : "resposta no tópico ".intval($row["parent"]))." | ".mb_substr(strip_tags(html_entity_decode($row["content"], ENT_QUOTES, "UTF-8")), 0, 300, "UTF-8"));
	}
	doquery("DELETE FROM {{table}} WHERE id='".intval($row["id"])."' LIMIT 1", "forum");
	if ($row["parent"] == 0) {
		// Tópico: as respostas vão junto.
		doquery("DELETE FROM {{table}} WHERE parent='".intval($row["id"])."'", "forum");
		forum_voltar(t("Deletado com sucesso."));
	}
	// Resposta: atualiza o contador do tópico.
	doquery("UPDATE {{table}} SET replies=IF(replies>0,replies-1,0) WHERE id='".intval($row["parent"])."' LIMIT 1", "forum");
	forum_voltar(t("Deletado com sucesso."), "forum.php?do=thread:".intval($row["parent"]).":0");

}

function msg() { // Ícone do fórum na barra de comandos: mostra as regras na janela flutuante (mainmsg = 4) e abre o fórum.

	global $userrow;
	doquery("UPDATE {{table}} SET mainmsg='4' WHERE id='".intval($userrow["id"])."' LIMIT 1","users");
	header("Location: forum.php");
	die();

}

?>
