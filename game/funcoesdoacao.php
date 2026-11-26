<?php // funcoesdoacao.php :: Botão de doação (control.linkdoacao), usado na página creditos.php e na janela de doação do painel do admin.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

if (!function_exists('botaodoacao')) {
function botaodoacao($controlrow, $grande = true) { // Botão grande de doação (Stripe) ou, sem link configurado, um aviso para o admin.
	$url = trim(html_entity_decode((string)$controlrow["linkdoacao"], ENT_QUOTES, "UTF-8"));
	if ($url == "" || !preg_match('#^https://#i', $url)) {
		return "<div class=\"crdaviso\">".t("O botão de doação ainda não foi configurado. Administrador: coloque o link de doação em Configurações gerais.")."</div>";
	}
	return "<a class=\"crdbotao".($grande ? "" : " pequeno")."\" href=\"".htmlspecialchars($url, ENT_QUOTES)."\" target=\"_blank\" rel=\"noopener\">&#10084; ".t("Doar com Stripe")."</a>";
}

}
?>
