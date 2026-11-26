<?php // funcoespainel.php :: Caixas da coluna da direita no padrão visual "B" (.cxb, templates/primary.php):
//   caixamissaoativa($row)  -> "Missão ativa": a missão da história em andamento, o objetivo e o progresso (ex.: 3/10);
//   caixaefeitosativos($row) -> "Efeitos ativos": Incenso Repelente, Passo Veloz, Doujutsu e Senjutsu, com ícone e tempo.
// Usadas pelo templates/rightnav.php. Só leem dados; nada é gravado aqui.

include_once('funcoesinclusas.php');

if (!function_exists('caixamissaoativa')) {
function caixamissaoativa($row) {

	include_once('funcoesmissoesextras.php');
	$missoes = mx_historia();
	$m = array_pad(explode(",", (string)$row["missao"]), 4, "0");
	$etapa = intval($m[0]); $feitos = intval($m[1]); $total = intval($m[2]);
	$h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
	$corpo = "";
	if (!isset($missoes[$etapa])) {
		$corpo = "<div class=\"cxb-vazio\">".t("Todas as missões da história foram concluídas.")."</div>";
	} else {
		$mi = $missoes[$etapa];
		$objetivo = strip_tags(mx_comarg(mx_texto($mi, "descricao"), $mi["descricao_arg"]));
		if (mb_strlen($objetivo, "UTF-8") > 110) { $objetivo = mb_substr($objetivo, 0, 107, "UTF-8")."..."; }
		$corpo .= "<div class=\"cxb-linha\"><b>".$h(tf("Missão %s: %s", $etapa, mx_titulo($mi)))."</b></div>";
		$corpo .= "<div class=\"cxb-linha\">".$h($objetivo)."</div>";
		if ($total > 0) {
			$pct = min(100, (int)floor($feitos * 100 / $total));
			$corpo .= "<div class=\"cxb-linha\">".$h(tf("Progresso: %s/%s", min($feitos, $total), $total))
				."<div style=\"height:6px;background:#c9a96a;border:1px solid #452202;margin-top:2px\"><div style=\"height:6px;width:$pct%;background:#2f7a2f\"></div></div></div>";
		}
		if (trim((string)$mi["local_texto"]) != "") { $corpo .= "<div class=\"cxb-linha\">".$h(tf("Onde: %s", t($mi["local_texto"])))."</div>"; }
	}
	return "<div class=\"cxb\" id=\"caixamissao\"><div class=\"cxb-cab\">".t("Missão ativa")."<a href=\"treinamentoequests.php?do=quests\" title=\"".t("Painel de Missões")."\">&raquo;</a></div><div class=\"cxb-corpo\">".$corpo."</div></div>";

}}

if (!function_exists('caixaefeitosativos')) {
function caixaefeitosativos($row) {

	$linhas = array();
	$linha = function ($icone, $texto) { return "<div class=\"cxb-linha\" style=\"display:flex;align-items:center;gap:5px\"><img src=\"".$icone."\" width=\"17\" height=\"17\" alt=\"\" style=\"flex:none\"> <span>".$texto."</span></div>"; };
	// Incenso Repelente (backpack.php): passos e minutos que restam.
	if (isset($row["repelente_passos"]) && function_exists('repelenteativo') && repelenteativo($row) > 0) {
		$minutos = max(1, (int)ceil((intval($row["repelente_ate"]) - time()) / 60));
		$linhas[] = $linha("layoutnovo/equipamentos/novos/incenso_repelente.gif", tf("Incenso: %s passos / %s min", intval($row["repelente_passos"]), $minutos));
	}
	// Item de descanso (backpack.php): multiplicador do Descansar e o tempo que resta.
	if (function_exists('descansomult') && descansomult($row) > 0) {
		$resta = intval($row["descanso_ate"]) - time();
		$tempo = ($resta >= 3600) ? sprintf("%dh%02d", intdiv($resta, 3600), intdiv($resta % 3600, 60)) : max(1, (int)ceil($resta / 60))." min";
		$linhas[] = $linha("images/24/descansar.gif", tf("Descanso ×%s: %s", descansomulttexto($row["descanso_mult"]), $tempo));
	}
	// Passo Veloz (heal.php/explore.php): casas por passo.
	if (isset($row["passo_jutsu"]) && intval($row["passo_jutsu"]) > 0) {
		$sp = mysqli_fetch_array(doquery("SELECT name,tier FROM {{table}} WHERE id='".intval($row["passo_jutsu"])."' LIMIT 1", "spells"));
		if ($sp) { $linhas[] = $linha("images/jutsu_correr.png", tf("%s: %s casas por passo", t($sp["name"]), intval($sp["tier"]) + 1)); }
	}
	// Doujutsu aberto (funcoesdoujutsu.php): nome do olho e o Chakra que ele gasta.
	include_once('funcoesdoujutsu.php');
	if (isset($row["doujutsu"]) && (string)$row["doujutsu"] != "" && doujutsuativo($row)) {
		$olho = doujutsudojogador($row);
		if ($olho) { $linhas[] = $linha("images/olhos/".htmlspecialchars($olho["imagem"]).".jpg", tf("%s ativo (%s)", doujutsunome($olho), doujutsucustotexto($olho, $row["level"]))); }
	}
	// Senjutsu (senjutsu.php): 1 NP a cada 3 s; o tempo que resta sai do NP atual.
	if (function_exists('senjutsuativo') && senjutsuativo($row)) {
		$seg = max(0, intval($row["currentnp"]) * 3);
		$linhas[] = $linha("images/jutsu_suporte.png", tf("Modo Senjutsu: ~%s min de NP", max(1, (int)ceil($seg / 60))));
	}
	$corpo = count($linhas) ? implode("", $linhas) : "<div class=\"cxb-vazio\">".t("Nenhum efeito ativo.")."</div>";
	return "<div class=\"cxb\" id=\"caixaefeitos\"><div class=\"cxb-cab\">".t("Efeitos ativos")."</div><div class=\"cxb-corpo\">".$corpo."</div></div>";

}}

?>
