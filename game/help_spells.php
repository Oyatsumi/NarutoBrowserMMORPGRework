<?php // help_spells.php :: Ajuda - lista de todos os jutsus (elemento, tipo, custo de Chakra, poder, efeitos, recarga e como aprender), com ordenação por coluna e filtros.

include('lib.php');
$link = opendb();
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

include('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php'); // jutsudescricaoefeitos(), jutsutipos() etc. (funcoesjutsus.php)
include_once('funcoestreinos.php'); // treinoslista(): jutsus ensinados pelos treinos longos.

// Ordenação: o nome da coluna vem da URL, então só valores desta lista branca chegam ao ORDER BY.
$ordenar = pegartexto($_GET, "ord", "nivelminimo");
if (!in_array($ordenar, array("name", "mp", "type", "attribute", "elemento", "nivelminimo", "cooldown"), true)) { $ordenar = "nivelminimo"; }
// Filtros: elemento e forma de aprender (lista branca).
$filtroelemento = pegartexto($_GET, "elemento");
if (!isset(jutsuelementos()[$filtroelemento])) { $filtroelemento = ""; }
$filtroaprender = pegartexto($_GET, "aprender");
if (!in_array($filtroaprender, array("", "nivel", "pergaminho"), true)) { $filtroaprender = ""; }

$tipos = jutsutipos();
$elementos = jutsuelementos();

// Quem ensina cada jutsu: tabela levels (nível + classe) e pergaminhos (drops.ensina_jutsu) com os inimigos que os soltam.
$pornivel = array();
$lq = doquery("SELECT id,1_spells,2_spells,3_spells FROM {{table}} ORDER BY id", "levels");
while ($lr = mysqli_fetch_array($lq)) {
	for ($c = 1; $c <= 3; $c++) { if (intval($lr[$c."_spells"]) > 0) { $pornivel[intval($lr[$c."_spells"])][intval($lr["id"])][] = $c; } }
}
$quemsolta = array(); // id do drop => nomes dos inimigos
$inimigos = array();
$mq = doquery("SELECT id,name,level FROM {{table}} ORDER BY level, id", "monsters");
while ($mr = mysqli_fetch_array($mq)) { $inimigos[intval($mr["id"])] = $mr; }
$dq = doquery("SELECT md.drop_id, md.monster_id FROM {{table}} AS md WHERE md.drop_id IN (SELECT id FROM ".$dbsettings["prefix"]."_drops WHERE ensina_jutsu>0)", "monster_drops");
while ($dr = mysqli_fetch_array($dq)) {
	if (isset($inimigos[intval($dr["monster_id"])])) { $m = $inimigos[intval($dr["monster_id"])]; $quemsolta[intval($dr["drop_id"])][] = t($m["name"])." (Lv ".intval($m["level"]).")"; }
}
$pergaminhos = jutsupergaminhos();

function linkordem($coluna, $texto) { // Cabeçalho que ordena pela coluna, mantendo os filtros.
	global $filtroelemento, $filtroaprender;
	return "<a href=\"help_spells.php?ord=$coluna&amp;elemento=$filtroelemento&amp;aprender=$filtroaprender\" title=\"".t("Ordenar")."\">".t($texto)."</a>";
}

$opcoeselemento = "<option value=\"\">".t("Todos os elementos")."</option>";
foreach ($elementos as $k => $v) { $opcoeselemento .= "<option value=\"$k\"".($k == $filtroelemento ? " selected=\"selected\"" : "").">".t($v)."</option>"; }
$opcoesaprender = "";
foreach (array(""=>"Todas as formas", "nivel"=>"Por nível", "pergaminho"=>"Por pergaminho") as $k => $v) { $opcoesaprender .= "<option value=\"$k\"".($k == $filtroaprender ? " selected=\"selected\"" : "").">".t($v)."</option>"; }

$page = "
<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/ajuda_titulo.gif\" alt=\"".t("Ajuda")."\" /></center></td></tr></table>
<center>[ <a href=\"help.php\">".t("Voltar à ajuda")."</a> | <a href=\"index.php\">".t("Voltar ao jogo")."</a> ]</center>
<br />
<form action=\"help_spells.php\" method=\"get\" style=\"text-align:center\"><input type=\"hidden\" name=\"ord\" value=\"$ordenar\" />
<select name=\"elemento\">$opcoeselemento</select> <select name=\"aprender\">$opcoesaprender</select> <input class=\"btnjogo bj-neutro\" type=\"submit\" value=\"".t("Filtrar")."\" /></form><br />
<table width=\"100%\" style=\"border: solid 1px black\" cellspacing=\"0\" cellpadding=\"3\">
<tr><td colspan=\"7\" bgcolor=\"#ffffff\"><center><b>".t("Jutsus")."</b></center></td></tr>
<tr><td><b>".linkordem("name", "Nome")."</b></td><td><b>".linkordem("elemento", "Elem.")."</b></td><td><b>".linkordem("type", "Tipo")."</b></td><td><b>".linkordem("mp", "Custo")."</b></td><td><b>".linkordem("attribute", "Poder")."</b> / ".t("Efeitos")."</td><td><b>".linkordem("cooldown", "Recarga")."</b></td><td><b>".linkordem("nivelminimo", "Como aprender")."</b></td></tr>
";

$count = 1;
$mostrados = 0;
$onde = ($filtroelemento != "") ? "elemento='$filtroelemento'" : "1";
$itemsquery = doquery("SELECT * FROM {{table}} WHERE $onde ORDER BY $ordenar, attribute, id", "spells");
while ($itemsrow = mysqli_fetch_array($itemsquery)) {
	$id = intval($itemsrow["id"]);
	// Como aprender: níveis (com a classe) e pergaminhos (com os inimigos que soltam).
	$aprender = array();
	if (isset($pornivel[$id])) {
		foreach ($pornivel[$id] as $nivel => $classes) {
			$aprender[] = (count($classes) == 3) ? tf("Nível %s", $nivel) : tf("Nível %s (%s)", $nivel, implode(", ", array_map("nomedaclasse", $classes)));
		}
	}
	$temnivel = count($aprender) > 0;
	$tempergaminho = isset($pergaminhos[$id]);
	if ($tempergaminho) {
		foreach ($pergaminhos[$id] as $drop) {
			$fontes = isset($quemsolta[intval($drop["id"])]) ? implode(", ", $quemsolta[intval($drop["id"])]) : t("nenhum inimigo no momento");
			$aprender[] = "<img src=\"".iconepergaminho($id)."\" width=\"17\" height=\"17\" style=\"vertical-align:middle\" alt=\"\" /> ".tf("Pergaminho dropado por: %s", $fontes);
		}
	}
	if (($filtroaprender == "nivel" && !$temnivel) || ($filtroaprender == "pergaminho" && !$tempergaminho)) { continue; }
	// Exclusivo de missão (spells.exclusivo_missao): só como recompensa de missão (nunca por nível nem pergaminho).
	if (intval($itemsrow["exclusivo_missao"]) == 1) { $aprender = array("<b>".t("Obtido apenas por missão")."</b>"); foreach (treinoslista() as $trj) { if (intval($trj["jutsu_id"]) == intval($itemsrow["id"])) { $aprender = array("<b>".tf("Obtido no treino %s (%s)", t($trj["nome"]), treinonomevila($trj["vila_id"]))."</b>"); } } $requisitos = jutsutextorequisitos($itemsrow); if ($requisitos != "") { $aprender[] = "<span class=\"small\">".$requisitos."</span>"; } }
	if (!count($aprender)) { $aprender[] = "<span class=\"light\">".t("Não pode ser aprendido no momento")."</span>"; }
	$requisitos = jutsutextorequisitos($itemsrow);
	if ($tempergaminho && $requisitos != "") { $aprender[] = "<span class=\"small\">".$requisitos."</span>"; }

	if ($count == 1) { $color = "bgcolor=\"#ffffff\""; $count = 2; } else { $color = ""; $count = 1; }
	$type = isset($tipos[$itemsrow["type"]]) ? t($tipos[$itemsrow["type"]]) : "-";
	$elemento = isset($elementos[$itemsrow["elemento"]]) ? $itemsrow["elemento"] : "neutro";
	$descricao = (trim((string)$itemsrow["descricao"]) != "") ? "<br /><span class=\"small\"><i>".t($itemsrow["descricao"])."</i></span>" : "";
	$recarga = (intval($itemsrow["cooldown"]) > 0) ? tf("%s turno(s)", intval($itemsrow["cooldown"])) : "-";
	$page .= "<tr><td $color width=\"24%\" valign=\"top\"><b>".t($itemsrow["name"])."</b>$descricao</td><td $color valign=\"top\"><center><img src=\"images/".$elemento.".gif\" title=\"".tf("Elemento %s", t($elementos[$elemento]))."\" alt=\"".t($elementos[$elemento])."\" /></center></td><td $color valign=\"top\">$type</td><td $color valign=\"top\">".intval($itemsrow["mp"])." CH</td><td $color valign=\"top\" width=\"25%\">".implode("<br />", jutsudescricaoefeitos($itemsrow, false))."</td><td $color valign=\"top\">$recarga</td><td $color valign=\"top\" width=\"24%\">".implode("<br />", $aprender)."</td></tr>\n";
	$mostrados++;
}
if ($mostrados == 0) { $page .= "<tr><td colspan=\"7\">".t("Nenhum jutsu encontrado.")."</td></tr>"; }
$page .= "
</table>
<ul>
<li />".t("<b>Medicinal</b>: recupera Pontos de Vida (HP), no máximo até o seu HP total. Pode ser usado também fora das lutas, pela lista de jutsus no painel da direita.")."
<li />".t("<b>Dano</b>: causa entre 5/6 e o total do poder do jutsu em dano ao inimigo, multiplicado pela vantagem ou desvantagem de elemento. Inimigos com imunidade a dano não são afetados.")."
<li />".t("<b>Sono</b>: coloca o inimigo para dormir. A cada turno ele tem uma chance em 15 de continuar dormindo igual ao poder do jutsu (ex.: poder 9 = 9 chances em 15). Inimigos imunes a dano e sono não são afetados.")."
<li />".t("<b>+Dano</b>: aumenta o seu dano de ataque pela porcentagem até o fim da luta.")."
<li />".t("<b>+Defesa</b>: reduz o dano dos ataques que você recebe pela porcentagem até o fim da luta.")."
<li />".t("<b>Suporte</b>: não causa dano; aplica só o efeito descrito (escudo, reforço, enfraquecimento do inimigo, atordoamento...).")."
<li />".t("<b>Efeitos por turnos</b>: queimadura, veneno e regeneração agem no fim de cada rodada; reforços e enfraquecimentos duram os turnos indicados. O mesmo efeito não acumula: vale o mais forte. Os ícones aparecem no cartão do inimigo e abaixo dele (os seus).")."
<li />".t("<b>Recarga</b>: depois de usar o jutsu, ele fica bloqueado pelo número de turnos indicado.")."
<li />".t("<b>Pergaminhos</b>: alguns inimigos podem soltar pergaminhos de jutsu. Clique no pergaminho na mochila para aprender o jutsu (se tiver os requisitos). Se você já conhece o jutsu, pode guardar o pergaminho no banco ou trocá-lo com outro jogador.")."
</ul>";
display($page, t("Ajuda - Jutsus"), false, false, false);

?>
