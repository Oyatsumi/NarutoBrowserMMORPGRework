<?php // outros.php :: Troca de avatar do personagem (outros.php?do=avatar mostra a tela; outros.php?action=N grava a escolha).

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
include_once('funcoesavatares.php'); // catálogo e avatares liberados (não há mais contas VIP).

// Mudar avatar: só para um avatar do catálogo que o jogador já liberou.
if (isset($_GET["action"])) {
	$action = pegarint($_GET, "action");
	if ($userrow["acesso"] >= 3) { header("Location: index.php?conteudo=".rawurlencode(t("GameMasters não podem mudar seu avatar."))); die(); }
	$catalogo = avatarescatalogo();
	if (!isset($catalogo[$action])) { header("Location: index.php?conteudo=".rawurlencode(t("Tentativa de trapaça detectada."))); die(); }
	if (!avatartemliberado($userrow, $action)) { header("Location: outros.php?do=avatar&conteudo=".rawurlencode(t("Você ainda não liberou esse avatar."))); die(); }
	doquery("UPDATE {{table}} SET avatar='".$action."' WHERE id='".intval($userrow["id"])."' LIMIT 1","users");
	header("Location: index.php?conteudo=".rawurlencode(t("Avatar selecionado com sucesso.")));
	die();
}

if (isset($_GET["do"])) {
	$do = $_GET["do"];
	if ($do == "avatar") { avatar(); }
}

function avatar() { // Grade com todos os avatares: liberados (clicáveis) e bloqueados (cinza, com como liberar).
	global $topvar, $userrow;
	$topvar = true;
	$catalogo = avatarescatalogo();
	$meus = avataresdojogador($userrow);
	$grade = "<table class=\"avgrade\"><tr>"; $n = 0; $liberados = 0;
	foreach ($catalogo as $id => $r) {
		if ($n > 0 && $n % 4 == 0) { $grade .= "</tr><tr>"; }
		$nome = t($r["nome"]);
		$img = "<img class=\"avret\" src=\"layoutnovo/avatares/".$id.".jpg\" width=\"98\" height=\"120\" alt=\"".$nome."\" title=\"".$nome."\">";
		if (in_array($id, $meus, true)) {
			$liberados++;
			$atual = (intval($userrow["avatar"]) == $id);
			$rodape = $atual ? "<div class=\"avcomo\"><b>".t("Avatar atual")."</b></div>" : "<div class=\"avcomo\">".t("Clique para usar")."</div>";
			$grade .= "<td class=\"avcel".($atual ? " atual" : "")."\"><a href=\"outros.php?action=".$id."\">".$img."</a><div class=\"avnome\">".$nome."</div>".$rodape."</td>";
		} else {
			// Bloqueado: o retrato fica colorido por fora (papel e moldura) e só o miolo, dentro da moldura preta, vai para cinza.
			$miolo = "<img class=\"avmiolo\" src=\"layoutnovo/avatares/".$id.".jpg\" width=\"98\" height=\"120\" alt=\"\">";
			$grade .= "<td class=\"avcel bloq\"><span class=\"avbox\">".$img.$miolo."</span>"."<div class=\"avnome\"><span class=\"avcadeado\">&#128274;</span> ".$nome."</div><div class=\"avcomo\">".avatarcomoliberar($id)."</div></td>";
		}
		$n++;
	}
	while ($n % 4 != 0) { $grade .= "<td class=\"avcel\"></td>"; $n++; }
	$grade .= "</tr></table>";
	$page = parsetemplate(gettemplate("avatar"), array("grade" => $grade, "contagem" => tf("Avatares liberados: %s de %s", $liberados, count($catalogo))));
	display($page, t("Trocar Avatar"), false, false, false);
}

?>
