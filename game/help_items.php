<?php // help_items.php :: Ajuda - lista dos itens vendidos nas lojas e dos itens que os inimigos dropam, com ordenação por coluna.

include('lib.php');

function marcasdoitem($row) { // Marcas pequenas ao lado do nome: "Empilhável (até N)" e "Não vendável" (colunas items/drops).

	$m = array();
	if (!empty($row["empilhavel"])) { $m[] = tf("Empilhável (até %s)", intval($row["maxpilha"])); }
	if (!empty($row["naoVendavel"])) { $m[] = "<font color=\"darkred\">".t("Não vendável")."</font>"; }
	return count($m) ? "<br /><span class=\"small\">".implode(" · ", $m)."</span>" : "";

}
$link = opendb();
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

include('cookies.php');
$userrow = checkcookies();

// Ordenação das duas tabelas: o nome da coluna vem da URL, então só valores das listas brancas chegam ao ORDER BY.
$ordenar = pegartexto($_GET, "ord", "type");
if (!in_array($ordenar, array("type", "name", "buycost", "attribute", "special"), true)) { $ordenar = "type"; }
$ordenar2 = pegartexto($_GET, "ord2", "mlevel");
if (!in_array($ordenar2, array("name", "mlevel"), true)) { $ordenar2 = "mlevel"; }

function nomeatributo($campo) { // Nome em português de um campo de bônus ("campo,valor") usado em items.special/special2/special3 e drops.attribute1/2/3.

	$nomes = array(
		"maxhp"=>"Max HP", "maxmp"=>"Max CH", "maxtp"=>"Max TP", "maxnp"=>"Max NP", "maxep"=>"Max EP",
		"goldbonus"=>"Bônus Ryou (%)", "expbonus"=>"Bônus Experiência (%)", "droprate"=>"Chance de Drop (%)",
		"strength"=>"Força", "dexterity"=>"Destreza", "attackpower"=>"Poder de Ataque", "defensepower"=>"Poder de Defesa",
		"agilidade"=>"Agilidade", "sorte"=>"Sorte", "determinacao"=>"Determinação", "precisao"=>"Precisão", "inteligencia"=>"Inteligência",
		"critico"=>"Chance de Crítico (%)", "esquiva"=>"Esquiva (%)", "regenhp"=>"Regeneração de HP por passo", "regenmp"=>"Regeneração de CH por passo", "regentp"=>"Regeneração de TP por passo", "regennp"=>"Regeneração de NP por passo", "regenep"=>"Regeneração de EP por passo", "economiachakra"=>"Economia de Chakra (%)");
	return isset($nomes[$campo]) ? t($nomes[$campo]) : $campo;

}

function textoatributos($lista) { // Vários bônus ("X" são ignorados) um embaixo do outro; nenhum -> "Nenhum".

	$partes = array();
	foreach ($lista as $especial) { if ($especial != "X" && $especial != "") { $partes[] = textoatributo($especial); } }
	return count($partes) ? implode("<br />", $partes) : textoatributo("X");

}

function textoconsumivel($consumivel, $senao) { // Drop consumível ("hp,50"/"mp,40") -> "Consumível: recupera 50 de HP"; senão devolve $senao.

	$c = array_pad(explode(",", (string)$consumivel), 2, 0);
	if ($c[0] == "hp") { return tf("Consumível: recupera %s de HP", intval($c[1])); }
	if ($c[0] == "mp") { return tf("Consumível: recupera %s de CH", intval($c[1])); }
	if (in_array($c[0], array("tp", "np", "ep"), true)) { return tf("Consumível: recupera %s de %s", intval($c[1]), strtoupper($c[0])); } // poções pequenas
	return $senao;

}

function textoatributo($especial) { // "maxhp,10" -> "Max HP +10"; "X" (sem bônus) -> "Nenhum".

	if ($especial == "X" || $especial == "") { return "<span class=\"light\">".t("Nenhum")."</span>"; }
	$partes = array_pad(explode(",", $especial), 2, "0");
	$valor = intval($partes[1]);
	return nomeatributo($partes[0])." ".(($valor > 0) ? "+".$valor : $valor);

}

$page = "

<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/ajuda_titulo.gif\" alt=\"".t("Ajuda")."\" /></center></td></tr></table>
<center>[ <a href=\"help.php\">".t("Voltar à Ajuda")."</a> | <a href=\"index.php\">".t("Voltar ao jogo")."</a> ]</center>

<br />
<table width=\"100%\" style=\"border: solid 1px black\" cellspacing=\"0\" cellpadding=\"4\"><tr><td bgcolor=\"#ffffff\"><b>".t("Bônus de combate dos equipamentos")."</b><br />
".t("<b>Chance de Crítico:</b> quando o ataque não vira um hit excelente pela Força e Determinação, ainda há esta chance (%) de causar dano dobrado. Máximo de 50%.")."<br />
".t("<b>Esquiva:</b> quando você não desvia do ataque inimigo pela Destreza e Agilidade, ainda há esta chance (%) de desviar. Máximo de 40%.")."<br />
".t("<b>Regeneração de HP por passo:</b> HP recuperado a cada casa andada no mapa, até o HP máximo.")."<br />
".t("<b>Economia de Chakra:</b> os jutsus custam essa porcentagem a menos de chakra (arredondado para cima, mínimo 1). Máximo de 50%.")."<br />
".t("Cada item pode ter até 3 bônus; os bônus de itens e drops equipados se somam.")."</td></tr></table>
<br />

<table width=\"100%\" style=\"border: solid 1px black\" cellspacing=\"0\" cellpadding=\"0\">
<tr><td colspan=\"5\" bgcolor=\"#ffffff\"><center><b>".t("Itens")."</b></center></td></tr>
<tr><td><b><a href=\"help_items.php?ord=type\" title=\"".t("Ordenar")."\">".t("Tipo")."</a></b></td><td><b><a href=\"help_items.php?ord=name\" title=\"".t("Ordenar")."\">".t("Nome")."</a></b></td><td><b><a href=\"help_items.php?ord=buycost\" title=\"".t("Ordenar")."\">".t("Custo")."</a></b></td><td><b><a href=\"help_items.php?ord=attribute\" title=\"".t("Ordenar")."\">".t("Atributo")."</a></b></td><td><b><a href=\"help_items.php?ord=special\" title=\"".t("Ordenar")."\">".t("Especial")."</a></b></td></tr>";

// Itens de loja: type 1 = arma, 2 = colete, 3 = bandana.
$count = 1;
$itemsquery = doquery("SELECT * FROM {{table}} ORDER BY $ordenar, attribute, id", "items");
while ($itemsrow = mysqli_fetch_array($itemsquery)) {
	if ($count == 1) { $color = "bgcolor=\"#ffffff\""; $count = 2; } else { $color = ""; $count = 1; }
	if ($itemsrow["type"] == 1) { $image = "weapon"; $tipo = t("Arma"); $poder = t("Poder de Ataque"); } elseif ($itemsrow["type"] == 2) { $image = "armor"; $tipo = t("Colete"); $poder = t("Poder de Destreza"); } else { $image = "shield"; $tipo = t("Bandana"); $poder = t("Poder de Destreza"); }
	$page .= "<tr><td $color width=\"5%\"><img src=\"images/icon_$image.gif\" alt=\"$tipo\" title=\"$tipo\" /></td><td $color width=\"30%\">".t($itemsrow["name"]).marcasdoitem($itemsrow)."</td><td $color width=\"20%\">".$itemsrow["buycost"]." Ryou</td><td $color width=\"20%\">".$itemsrow["attribute"]." $poder </td><td $color width=\"25%\">".textoatributos(array($itemsrow["special"], $itemsrow["special2"], $itemsrow["special3"]))."</td></tr>\n";
}

$page .= "
</table>
<br />
<br />
<table width=\"100%\" style=\"border: solid 1px black\" cellspacing=\"0\" cellpadding=\"0\">
<tr><td colspan=\"4\" bgcolor=\"#ffffff\"><center><b>".t("Drops")."</b></center></td></tr>
<tr><td><b><a href=\"help_items.php?ord2=name\" title=\"".t("Ordenar")."\">".t("Nome")."</a></b></td><td><a href=\"help_items.php?ord2=mlevel\" title=\"".t("Level do Inimigo que Dropa - Ordenar")."\"><b>".t("Level")."</b></a></td><td><b>".t("Atributos")."</b></td><td><b>".t("Inimigos que dropam")."</b></td></tr>";

// Drops: mlevel = level mínimo do inimigo para que o item possa cair pela regra antiga (inimigos sem tabela de drops);
// a última coluna vem da tabela monster_drops (quantos inimigos podem soltar o item e a maior chance).
$quemdropa = array();
$mdquery = doquery("SELECT drop_id, COUNT(*) AS total, MAX(chance_percent) AS maior FROM {{table}} GROUP BY drop_id", "monster_drops");
while ($mdrow = mysqli_fetch_array($mdquery)) { $quemdropa[intval($mdrow["drop_id"])] = $mdrow; }
$count = 1;
$itemsquery = doquery("SELECT * FROM {{table}} ORDER BY $ordenar2, id", "drops");
while ($itemsrow = mysqli_fetch_array($itemsquery)) {
	if ($count == 1) { $color = "bgcolor=\"#ffffff\""; $count = 2; } else { $color = ""; $count = 1; }
	$page .= "<tr><td $color width=\"25%\">".t($itemsrow["name"]).marcasdoitem($itemsrow)."</td><td $color width=\"15%\">".$itemsrow["mlevel"]."</td><td $color width=\"30%\">".textoconsumivel($itemsrow["consumivel"], textoatributos(array($itemsrow["attribute1"], $itemsrow["attribute2"], $itemsrow["attribute3"])))."</td><td $color width=\"30%\">".(isset($quemdropa[intval($itemsrow["id"])]) ? tf("%s inimigo(s), até %s%%", $quemdropa[intval($itemsrow["id"])]["total"], floatval($quemdropa[intval($itemsrow["id"])]["maior"])) : "<span class=\"light\">".t("Nenhum")."</span>")."</td></tr>\n";
}
$page .= "
</table>";

display($page, t("Ajuda - Itens"), false, false, false);

?>
