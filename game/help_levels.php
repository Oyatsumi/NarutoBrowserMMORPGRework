<?php // help_levels.php :: Ajuda - tabela de leveis de cada especialização (experiência necessária, ganhos de status e jutsus aprendidos).

include('lib.php');
$link = opendb();
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

include('cookies.php');
$userrow = checkcookies();

// Nomes dos jutsus, para mostrar qual é aprendido em cada level.
$spells = array();
$spellsquery = doquery("SELECT id,name FROM {{table}} ORDER BY id", "spells");
while ($spellsrow = mysqli_fetch_array($spellsquery)) { $spells[$spellsrow["id"]] = $spellsrow["name"]; }

$page = "
<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/ajuda_titulo.gif\" alt=\"".t("Ajuda")."\" /></center></td></tr></table>
<center>[ <a href=\"help.php\">".t("Voltar à ajuda")."</a> | <a href=\"index.php\">".t("Voltar ao jogo")."</a> ]</center><br />
".t("Os valores de HP, CH, TP, Força e Destreza de cada linha são o quanto você <b>ganha</b> ao chegar naquele level (na linha do level 1 estão os valores iniciais). A coluna Exp. é a experiência total necessária para alcançar o level.")."
<br />";

// A tabela levels tem um grupo de colunas por especialização: 1_exp, 1_hp, ... 2_exp, ... 3_spells.
$exemplo = array();
for ($classe = 1; $classe <= 3; $classe++) {
	$page .= "
<br />
<table width=\"100%\" style=\"border: solid 1px black\" cellspacing=\"0\" cellpadding=\"0\">
<tr><td colspan=\"8\" bgcolor=\"#ffffff\"><center><b>".tf("Leveis de %s", t($controlrow["class".$classe."name"]))."</b></center></td></tr>
<tr><td><b>".t("Level")."</b></td><td><b>".t("Exp.")."</b></td><td><b>HP</b></td><td><b>CH</b></td><td><b>TP</b></td><td><b>".t("Força")."</b></td><td><b>".t("Destreza")."</b></td><td><b>".t("Jutsu")."</b></td></tr>
";
	$count = 1;
	$c = $classe; // Número da classe (1 a 3), usado no nome das colunas.
	$itemsquery = doquery("SELECT id,{$c}_exp,{$c}_hp,{$c}_mp,{$c}_tp,{$c}_strength,{$c}_dexterity,{$c}_spells FROM {{table}} ORDER BY id", "levels");
	while ($itemsrow = mysqli_fetch_array($itemsquery)) {
		// O último level (100) só existe como limite de experiência e não é mostrado.
		if ($itemsrow["id"] == 100) { continue; }
		if ($classe == 1 && ($itemsrow["id"] == 2 || $itemsrow["id"] == 3)) { $exemplo[$itemsrow["id"]] = $itemsrow["1_exp"]; }
		if ($count == 1) { $color = "bgcolor=\"#ffffff\""; $count = 2; } else { $color = ""; $count = 1; }
		$idjutsu = intval($itemsrow[$c."_spells"]);
		$spell = ($idjutsu != 0 && isset($spells[$idjutsu])) ? t($spells[$idjutsu]) : "<span class=\"light\">".t("Nenhum")."</span>";
		$page .= "<tr><td $color width=\"12%\">".$itemsrow["id"]."</td><td $color width=\"12%\">".number_format((float)$itemsrow[$c."_exp"])."</td><td $color width=\"12%\">".$itemsrow[$c."_hp"]."</td><td $color width=\"12%\">".$itemsrow[$c."_mp"]."</td><td $color width=\"12%\">".$itemsrow[$c."_tp"]."</td><td $color width=\"12%\">".$itemsrow[$c."_strength"]."</td><td $color width=\"12%\">".$itemsrow[$c."_dexterity"]."</td><td $color width=\"12%\">$spell</td></tr>\n";
	}
	$page .= "
</table>
<br />";
}

if (isset($exemplo[2]) && isset($exemplo[3])) {
	$page .= tf("<b>Ex.:</b> Para o level 2 de %s você precisa ter %s de Exp, para o level 3, %s de Exp.", t($controlrow["class1name"]), $exemplo[2], $exemplo[3]);
}

display($page, t("Ajuda - Leveis"), false, false, false);

?>
