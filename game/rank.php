<?php // rank.php :: Ranking público dos 100 melhores jogadores, por level, poder de ataque, poder de defesa ou duelos vencidos (?ord=...).
include_once('lib.php');
$link = opendb();
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

include_once('cookies.php');
$userrow = checkcookies(); // Só para saber se mostra as colunas do jogo (a página também abre para quem não está logado).

// A coluna de ordenação vem da URL, então só aceita as colunas da lista (nunca o texto direto na query).
$ordens = array("level", "attackpower", "defensepower", "duelos");
$ord = pegartexto($_GET, "ord", "level");
if (!in_array($ord, $ordens, true)) { $ord = "level"; }
// Título de cada ordenação e a cláusula ORDER BY (duelos: mais vitórias, depois menos derrotas).
$titulos = array("level"=>"Rank por Level", "attackpower"=>"Rank por Poder de Ataque", "defensepower"=>"Rank por Poder de Defesa", "duelos"=>"Rank de Duelos");
$ordem = ($ord == "duelos") ? "duelos_vencidos DESC, duelos_perdidos ASC, level DESC" : "$ord DESC, level DESC";
$filtro = ($ord == "duelos") ? " AND (duelos_vencidos>0 OR duelos_perdidos>0)" : "";

$page = "
<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/rank.gif\" /></center></td></tr></table>
<center>[ <a href=\"index.php\">".t("Voltar ao Jogo")."</a> ]</center>

<br />

<table width=\"100%\" style=\"border: solid 1px black\" cellspacing=\"0\" cellpadding=\"0\">
<tr><td colspan=\"7\" bgcolor=\"#ffffff\"><center><b>".t($titulos[$ord])."</b></center></td></tr>
<tr><td><b>".t("Rank")."</b></td><td><b><a href=\"rank.php?ord=level\" title=\"".t("Ordenar por Level")."\">".t("Level")."</a></b></td><td><a href=\"rank.php?ord=attackpower\" title=\"".t("Ordenar por Poder de Ataque")."\">".t("Poder de Ataque")."</a></td><td><a href=\"rank.php?ord=defensepower\" title=\"".t("Ordenar por Poder de Defesa")."\">".t("Poder de Defesa")."</a></td><td><a href=\"rank.php?ord=duelos\" title=\"".t("Ordenar por duelos vencidos")."\">".t("Duelos (V/D)")."</a></td><td><b>".t("Graduação")."</b></td><td><b>".t("Nome")."</b></td></tr>
";

// Administradores (authlevel 1) e banidos (authlevel 2) não entram no rank. Linhas com fundo branco alternado.
$contagemrank = 0;
$usersquery = doquery("SELECT charname,level,attackpower,defensepower,duelos_vencidos,duelos_perdidos,graduacao FROM {{table}} WHERE authlevel='0' AND convidado='0'$filtro ORDER BY $ordem LIMIT 100", "users");
while ($usersrow = mysqli_fetch_array($usersquery)) {
    $contagemrank++;
    $color = ($contagemrank % 2 == 1) ? "bgcolor=\"#ffffff\"" : "";
    $page .= "<tr><td $color width=\"15%\">$contagemrank</td><td $color width=\"15%\">".$usersrow["level"]."</td><td $color width=\"15%\">".$usersrow["attackpower"]."</td><td $color width=\"15%\">".$usersrow["defensepower"]."</td><td $color width=\"12%\">".intval($usersrow["duelos_vencidos"])." / ".intval($usersrow["duelos_perdidos"])."</td><td $color width=\"14%\">".t($usersrow["graduacao"])."</td><td $color width=\"*\"><a href=\"javascript: mostrarchar('".$usersrow["charname"]."');\">".$usersrow["charname"]."</a></td></tr>\n";
}

$page .= "
</table>";
display($page, t($titulos[$ord]), false, false, false);
?>
