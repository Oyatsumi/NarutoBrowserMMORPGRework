<?php // help_monsters.php :: Ajuda - lista de todos os inimigos (monstros) do jogo, com a carta de cada um e ordenação por coluna.

include('lib.php');
$link = opendb();
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

include('cookies.php');
$userrow = checkcookies();
include_once('funcoesterreno.php'); // nomes dos terrenos de cada inimigo.
include_once('funcoesjutsus.php'); // imunidadesdoinimigo(), imunidadestexto().
include_once('funcoescartas.php'); // cartaodomonstro(): o mesmo cartão da luta, aberto ao clicar no retrato.
include_once('funcoesroubo.php'); // cartaoselos(): rouba itens, efeito no jogador e item roubado.

// Ordenação: o nome da coluna vem da URL, então só valores desta lista branca chegam ao ORDER BY.
$colunas = array("name", "maxhp", "maxdam", "armor", "level", "maxexp", "maxgold", "immune", "elemento");
$ordenar = pegartexto($_GET, "ord", "level");
if (!in_array($ordenar, $colunas, true)) { $ordenar = "level"; }

// Nome de exibição de cada elemento (o banco guarda sem acento).
$elementos = array("neutro"=>"Neutro", "fogo"=>"Fogo", "agua"=>"Água", "vento"=>"Vento", "raio"=>"Raio", "terra"=>"Terra");

$page = "
<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/ajuda_titulo.gif\" alt=\"".t("Ajuda")."\" /></center></td></tr></table>
<center>[ <a href=\"help.php\">".t("Voltar à ajuda")."</a> | <a href=\"index.php\">".t("Voltar ao jogo")."</a> ]</center>

<br />

<table width=\"100%\" style=\"border: solid 1px black\" cellspacing=\"0\" cellpadding=\"0\">
<tr><td colspan=\"10\" bgcolor=\"#ffffff\"><center><b>".t("Monstros")."</b></center></td></tr>
<tr><td colspan=\"10\" bgcolor=\"#ffffff\" class=\"small\">".t("Imunidades: <b>Imune a Ninjutsu</b> = nenhum jutsu causa dano (nem queimadura/veneno); <b>Imune a Ninjutsu de Fogo</b> (Água, Terra, Vento, Raio) = só os jutsus daquele elemento não causam dano; <b>Imune a Taijutsu</b> = o ataque físico não causa dano; <b>Imune a Sono</b> = jutsus de sono e atordoar não funcionam. Nenhum inimigo é imune a Taijutsu e a Ninjutsu ao mesmo tempo.")."</td></tr>
<tr><td><b>".t("Carta")."</b></td><td><b><a href=\"help_monsters.php?ord=name\" title=\"".t("Ordenar")."\">".t("Nome")."</a></b></td><td><b><a href=\"help_monsters.php?ord=maxhp\" title=\"".t("Ordenar")."\">".t("Max HP")."</a></b></td><td><b><a href=\"help_monsters.php?ord=maxdam\" title=\"".t("Ordenar")."\">".t("Max Dano")."</a></b></td><td><b><a href=\"help_monsters.php?ord=armor\" title=\"".t("Ordenar")."\">".t("Destreza")."</a></b></td><td><b><a href=\"help_monsters.php?ord=level\" title=\"".t("Ordenar")."\">".t("Level")."</a></b></td><td><b><a href=\"help_monsters.php?ord=maxexp\" title=\"".t("Ordenar")."\">".t("Max Exp")."</a></b></td><td><b><a href=\"help_monsters.php?ord=maxgold\" title=\"".t("Ordenar")."\">".t("Max Ryou")."</a></b></td><td><b><a href=\"help_monsters.php?ord=immune\" title=\"".t("Ordenar")."\">".t("Imunidade")."</a></b></td><td><b><a href=\"help_monsters.php?ord=elemento\" title=\"".t("Ordenar")."\">".t("Elem.")."</a></b></td></tr>
";
// Tabela de drops de cada inimigo (monster_drops): "Pode dropar: Nome (2%), ...". Lida de uma vez só.
$nomesdrops = array();
$consumiveis = array(); // poções/comidas (campo consumivel) nunca saem como variante rara.
$dq = doquery("SELECT id, name, consumivel FROM {{table}}", "drops");
while ($drow = mysqli_fetch_array($dq)) { $nomesdrops[intval($drow["id"])] = $drow["name"]; if (trim((string)$drow["consumivel"]) != "") { $consumiveis[intval($drow["id"])] = true; } }
$dropsinimigo = array();
$mdq = doquery("SELECT monster_id, drop_id, chance_percent, chance_variante_percent FROM {{table}} ORDER BY chance_percent DESC, id", "monster_drops");
while ($mdrow = mysqli_fetch_array($mdq)) {
	if (!isset($nomesdrops[intval($mdrow["drop_id"])])) { continue; }
	$chance = rtrim(rtrim(number_format(floatval($mdrow["chance_percent"]), 2, (idioma() == "pt") ? "," : ".", ""), "0"), ",.");
	// Chance de o drop sair como variante rara (✦ skin especial + bônus pequeno); 0 = nunca.
	$rara = rtrim(rtrim(number_format(floatval($mdrow["chance_variante_percent"]), 2, (idioma() == "pt") ? "," : ".", ""), "0"), ",.");
	if (floatval($mdrow["chance_variante_percent"]) > 0 && !isset($consumiveis[intval($mdrow["drop_id"])])) { $dropsinimigo[intval($mdrow["monster_id"])][] = tf("%s (%s%%, ✦ raro: %s%%)", t($nomesdrops[intval($mdrow["drop_id"])]), $chance, $rara); }
	else { $dropsinimigo[intval($mdrow["monster_id"])][] = tf("%s (%s%%)", t($nomesdrops[intval($mdrow["drop_id"])]), $chance); }
}

$count = 1;
$itemsquery = doquery("SELECT * FROM {{table}} ORDER BY ".($ordenar == "immune" ? "immune DESC, imunidades DESC" : $ordenar).", level, id", "monsters");
while ($itemsrow = mysqli_fetch_array($itemsquery)) {
	// Elite/raro: valores já com o bônus da raridade (iguais aos da luta) e o traço abaixo do nome.
	$itemsrow = monstroraridade($itemsrow);
	$cfgrar = raridadeconfig();
	if (isset($cfgrar[intval($itemsrow["raridade"])])) { $prar = $cfgrar[intval($itemsrow["raridade"])]["pct"]; $itemsrow["maxexp"] = (int)ceil(intval($itemsrow["maxexp"]) * (100 + $prar) / 100); $itemsrow["maxgold"] = (int)ceil(intval($itemsrow["maxgold"]) * (100 + $prar) / 100); }
	$traco = raridadetraco($itemsrow);
	if ($count == 1) { $color = "bgcolor=\"#ffffff\""; $count = 2; } else { $color = ""; $count = 1; }

	// Imunidade: 0 = nenhuma, 1 = jutsus de dano, 2 = jutsus de dano e de sono.
	$listaimune = imunidadesdoinimigo($itemsrow); // funcoesjutsus.php: Imune a Ninjutsu / Taijutsu / Ninjutsu de Fogo / Sono.
	$immune = count($listaimune) ? "<span class=\"small\">".imunidadestexto($listaimune, false, "<br />")."</span>" : "<span class=\"light\">".t("Nenhuma")."</span>";

	// Elemento: só nomes conhecidos viram nome de arquivo de imagem.
	$elemento = isset($elementos[$itemsrow["elemento"]]) ? $itemsrow["elemento"] : "neutro";

	// Retrato (recorte da carta layoutnovo/monstros/N.jpg) com a cor da raridade; o clique abre abaixo o mesmo cartão da luta (HP cheio).
	$carta = "layoutnovo/monstros/".intval($itemsrow["id"]).".jpg";
	if (!file_exists($carta)) { $carta = "layoutnovo/monstros/padrao.jpg"; }
	$corrar = array(0=>"#452202", 1=>"#a9b0ba", 2=>"#e2b84a", 3=>"#9a55cf");
	$rar = isset($itemsrow["raridade"]) ? intval($itemsrow["raridade"]) : 0; if (!isset($corrar[$rar])) { $rar = 0; }
	$idm = intval($itemsrow["id"]);
	$fundoret = file_exists("layoutnovo/monstros/retratos/$idm.jpg") ? "background-image:url('layoutnovo/monstros/retratos/$idm.jpg'); background-size:cover; background-position:center top;" : "background-image:url('$carta'); background-size:148px 73px; background-position:-3px -18px;";
	$retrato = "<a href=\"javascript:void(0)\" onclick=\"var c=document.getElementById('cartaaj$idm');c.style.display=(c.style.display=='none'?'':'none')\" title=\"".htmlspecialchars(tf("Ver a carta de %s", t($itemsrow["name"])), ENT_QUOTES, "UTF-8")."\"><span style=\"display:block; width:34px; height:50px; border:2px solid ".$corrar[$rar]."; border-radius:3px; $fundoret\"></span></a>";
	$cartaaj = "<tr id=\"cartaaj$idm\" style=\"display:none\"><td colspan=\"10\" style=\"padding:8px 0\">".cartaodomonstro($itemsrow, $itemsrow["maxhp"])."</td></tr>";

	// Região do mapa (opcional, definida no painel do administrador): mostrada abaixo do nome.
	$regiao = "";
	if ($itemsrow["regiao_latmin"] !== null && $itemsrow["regiao_latmax"] !== null && $itemsrow["regiao_lonmin"] !== null && $itemsrow["regiao_lonmax"] !== null) {
		$regiao = "<br /><span class=\"small\">".tf("Região: [%s,%s] até [%s,%s]", intval($itemsrow["regiao_latmax"]), intval($itemsrow["regiao_lonmin"]), intval($itemsrow["regiao_latmin"]), intval($itemsrow["regiao_lonmax"]));
		$regiao .= ($itemsrow["regiao_exclusiva"] == 1) ? " ".t("(aparece apenas nesta região)") : " ".t("(aparece com mais frequência nesta região)");
		$regiao .= "</span>";
	}

	// Terrenos (opcional): o inimigo só aparece em casas desses terrenos do mini mapa (vazio = qualquer terreno).
	$textoterreno = textoterrenosdoinimigo($itemsrow["terrenos"]);
	if ($textoterreno != "") { $regiao .= "<br /><span class=\"small\">".tf("Terreno: %s", $textoterreno)."</span>"; }

	if (isset($dropsinimigo[intval($itemsrow["id"])])) { $regiao .= "<br /><span class=\"small\">".tf("Pode dropar: %s", implode(", ", $dropsinimigo[intval($itemsrow["id"])]))."</span>"; }

	$page .= "<tr><td $color width=\"6%\">$retrato</td><td $color width=\"26%\">".t($itemsrow["name"]).$regiao.((isset($itemsrow["aparecimento"]) && intval($itemsrow["aparecimento"]) < 50 && intval($itemsrow["raridade"]) != 3) ? "<br /><span class=\"small\" style=\"color:#7a2a00\">".t("Raro de encontrar")."</span>" : "").($traco != "" ? "<br /><span class=\"small traco\" style=\"color:#6b4a12\">".$traco."</span>" : "").(($selosaj = cartaoselos($itemsrow)) != "" ? str_replace("class=\"cselos\"", "class=\"selosaj\"", $selosaj) : "")."</td><td $color width=\"9%\">".$itemsrow["maxhp"]."</td><td $color width=\"9%\">".$itemsrow["maxdam"]."</td><td $color width=\"9%\">".$itemsrow["armor"]."</td><td $color width=\"8%\">".$itemsrow["level"]."</td><td $color width=\"9%\">".$itemsrow["maxexp"]."</td><td $color width=\"9%\">".$itemsrow["maxgold"]."</td><td $color width=\"10%\">$immune</td><td $color><img src=\"images/".$elemento.".gif\" title=\"".tf("Elemento %s", t($elementos[$elemento]))."\" alt=\"".t($elementos[$elemento])."\" /></td></tr>\n".$cartaaj;
}
$page .= "
</table>";

display($page, t("Ajuda - Inimigos"), false, false, false);

?>
