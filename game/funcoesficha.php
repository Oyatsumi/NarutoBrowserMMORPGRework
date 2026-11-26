<?php // funcoesficha.php :: Ficha pública de um jogador (templates/onlinechar.php), usada por mostrarchar.php e index.php?do=onlinechar.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

/* Só dados públicos: nada de e-mail, conta (login), IP, banco, perguntas/código de recuperação, mochila ou senha.
   Os campos extras ({{vilanatal}}, {{critico}}, {{mapadescobertopct}}, {{pvpstatus}}, {{duelos_...}}) foram
   acrescentados na 5.0 para a ficha acompanhar as regras novas (especializações, Jounin, bônus de itens, neblina, PvP). */

if (!function_exists('fichapublica')) {

function fichapublica($row) { // Linha do jogador -> array de campos do template onlinechar (só o que é público).

	global $controlrow;
	include_once('funcoesinclusas.php');
	include_once('cidadesconteudo.php');
	$f = array();
	foreach (array("charname","level","avatar","currenthp","maxhp","currentmp","maxmp","currenttp","maxtp","currentnp","maxnp","currentep","maxep","droprate",
		"strength","dexterity","attackpower","defensepower","agilidade","sorte","determinacao","precisao","inteligencia") as $c) { $f[$c] = isset($row[$c]) ? $row[$c] : ""; }
	if (!file_exists("layoutnovo/avatares/".intval($f["avatar"]).".jpg")) { $f["avatar"] = 6; }
	$f["experience"] = number_format((float)$row["experience"]);
	$f["gold"] = ""; // Ryou carregados são privados: não aparecem na ficha pública.
	$f["duelos_vencidos"] = intval($row["duelos_vencidos"]);
	$f["duelos_perdidos"] = intval($row["duelos_perdidos"]);

	// Bônus de experiência / Ryou (itens especiais), ex.: "(+10%)".
	$f["plusexp"] = (intval($row["expbonus"]) != 0) ? "<span class=\"light\">(".(intval($row["expbonus"]) > 0 ? "+" : "").intval($row["expbonus"])."%)</span>" : "";
	$f["plusgold"] = (intval($row["goldbonus"]) != 0) ? "<span class=\"light\">(".(intval($row["goldbonus"]) > 0 ? "+" : "").intval($row["goldbonus"])."%)</span>" : "";

	// Próximo level: a tabela levels tem uma coluna por classe (sem especialização usa a curva 1_*).
	$classe = intval($row["charclass"]);
	$f["nextlevel"] = "<span class=\"light\">-</span>";
	$col = max(1, min(3, $classe));
	$lv = mysqli_fetch_array(doquery("SELECT ".$col."_exp FROM {{table}} WHERE id='".(intval($row["level"]) + 1)."' LIMIT 1", "levels"));
	if ($lv) { $f["nextlevel"] = number_format((float)$lv[$col."_exp"]); }
	$f["charclass"] = ($classe >= 1 && $classe <= 3) ? t(nomedaclasse($classe)) : t("Sem especialização (Estudante)");
	$iconesclasse = array(1 => "jutsu_dano.png", 2 => "jutsu_taijutsu.png", 3 => "jutsu_sono.png"); // Ninjutsu, Taijutsu, Genjutsu
	if (isset($iconesclasse[$classe])) { $f["charclass"] = "<img src=\"images/".$iconesclasse[$classe]."\" width=\"16\" height=\"14\" alt=\"\" style=\"vertical-align:-2px\" /> ".$f["charclass"]; }
	$f["graduacao"] = ($row["graduacao"] != "" && $row["graduacao"] != "None") ? t($row["graduacao"]) : t("Estudante da Academia");

	// Alinhamento: a vila natal (towns.alinhamento).
	$vilas = vilasnatais();
	$f["vilanatal"] = isset($vilas[$row["alinhamento"]]) ? selovila($row["alinhamento"])." ".t($vilas[$row["alinhamento"]]["name"]) : "-";
	// Trocou de vila natal (missão de transferência, docs/TROCA_DE_VILA.md): mostra desde quando.
	if (isset($row["vilatroca_em"]) && intval($row["vilatroca_em"]) > 0) { $f["vilanatal"] .= " <span class=\"small\">(".tf("desde %s", date("d/m/Y", intval($row["vilatroca_em"]))).")</span>"; }

	// Atributos novos dos itens (critico, esquiva e economia de chakra em %, regeneração de HP por passo).
	$f["critico"] = intval($row["critico"]); $f["esquiva"] = intval($row["esquiva"]);
	$f["regenhp"] = intval($row["regenhp"]); foreach (array("mp", "tp", "np", "ep") as $pt) { $f["regen".$pt] = isset($row["regen".$pt]) ? intval($row["regen".$pt]) : 0; } $f["economiachakra"] = intval($row["economiachakra"]);

	// Mapa descoberto (neblina de guerra), se o mundo tiver esse recurso.
	$f["mapadescobertopct"] = "-";
	if (isset($row["mapadescoberto"]) && file_exists("funcoesneblina.php")) { include_once('funcoesneblina.php'); $f["mapadescobertopct"] = neblina_percentual(neblina_mapadovisitante($row))."%"; }

	// Cargo, GM e caveira do PvP.
	// Clã (tag antes do nome) e títulos das temporadas de duelo (funcoescla.php).
	include_once('funcoescla.php');
	$f["clatag"] = clatag($row["id"]);
	$f["titulos"] = titulosficha($row["id"]);
	$f["adm"] = "";
	if (intval($row["authlevel"]) == 1) { $f["adm"] = "<font color=green>".t("Administrador")."</font><br>"; }
	elseif (intval($row["acesso"]) == 2) { $f["adm"] = "<font color=orange>".t("Tutor")."</font><br>"; }
	elseif (intval($row["acesso"]) == 3) { $f["adm"] = selogm()."<font color=blue>".t("GameMaster")."</font><br>"; }
	$cav = caveiraativa($row);
	if ($cav != "") { $f["adm"] .= selocaveira($row).($cav == "branca" ? t("Caveira branca") : "<font color=\"darkred\">".t("Caveira vermelha")."</font>")."<br>"; }

	// PvP no mapa (control.pvpmundo): protegido abaixo do nível seguro; GMs e administradores não entram no PvP.
	if (intval($controlrow["pvpmundo"]) != 1) { $f["pvpstatus"] = t("Desligado no servidor"); }
	elseif (intval($row["authlevel"]) == 1 || intval($row["acesso"]) == 3) { $f["pvpstatus"] = t("Fora do PvP (equipe do jogo)"); }
	elseif (intval($row["level"]) < intval($controlrow["pvpnivelseguro"])) { $f["pvpstatus"] = "<font color=\"darkgreen\">".tf("Protegido até o nível %s", intval($controlrow["pvpnivelseguro"]) - 1)."</font>"; }
	else { $f["pvpstatus"] = "<font color=\"darkred\">".t("Pode ser atacado no mapa")."</font>"; }

	// Recursos acrescentados depois (6.x): doujutsu, natureza do chakra, talentos, conjuntos, missões, alquimia, data de criação.
	$f["doujutsu"] = "-";
	if (file_exists("funcoesdoujutsu.php")) { include_once('funcoesdoujutsu.php');
		$olho = doujutsudojogador($row);
		if ($olho) { // imagem do olho (images/olhos/, a mesma da página do Doujutsu) + nome, que já traz o estágio
			$img = "images/olhos/".basename((string)$olho["imagem"]).".jpg";
			$f["doujutsu"] = (file_exists($img) ? "<img src=\"".htmlspecialchars($img, ENT_QUOTES, "UTF-8")."\" width=\"164\" height=\"64\" alt=\"\" style=\"vertical-align:middle;max-width:100%;height:auto\" /><br>" : "")."<b>".doujutsunome($olho)."</b>";
		}
	}
	$icel = function ($el) { return file_exists("images/".$el.".gif") ? "<img src=\"images/".$el.".gif\" width=\"16\" height=\"14\" alt=\"\" style=\"vertical-align:-2px\" /> " : ""; };
	// Elementos do ninja (elemento.php): principal e secundário, com o ícone de cada um.
	list($e1, $e2) = elementosdo((string)$row["elemento"]);
	if ($e1 == "neutro") { $f["natureza"] = $icel("neutro").t("Neutro"); }
	else { $f["natureza"] = $icel($e1)."<b>".elementonome($e1)."</b>".($e2 != "neutro" ? " / ".$icel($e2).elementonome($e2) : ""); }
	$f["talentos"] = "";
	if (file_exists("funcoestalentos.php")) { include_once('funcoestalentos.php');
		$lt = talentoslista();
		foreach (talentosescolhidos($row) as $nv => $op) { if (isset($lt[$nv][$op])) { $f["talentos"] .= "<span title=\"".htmlspecialchars(t($lt[$nv][$op]["desc"]), ENT_QUOTES, "UTF-8")."\" style=\"cursor:help\">".t($lt[$nv][$op]["nome"])."</span> <span class=\"small light\">(".$nv.")</span><br>"; } }
	}
	if ($f["talentos"] == "") { $f["talentos"] = t("Nenhum"); }
	$f["conjuntos"] = "";
	if (function_exists('conjuntoslista')) {
		foreach (conjuntoslista() as $c) { $n = conjuntoquantas($row, $c);
			if ($n >= 2) { $nm = idioma() == "en" && $c["nome_en"] != "" ? $c["nome_en"] : $c["nome"]; $f["conjuntos"] .= htmlspecialchars($nm, ENT_QUOTES, "UTF-8")." <span class=\"small light\">(".$n."/".count($c["lista"]).")</span><br>"; } }
	}
	if ($f["conjuntos"] == "") { $f["conjuntos"] = t("Nenhum"); }
	$mi = explode(",", (string)$row["missao"]);
	$f["missoes"] = max(0, intval($mi[0]) - 1);
	$f["alquimia"] = max(1, intval($row["alquimia_nivel"]));
	$f["desde"] = (intval($row["regdate"]) > 0) ? date("d/m/Y", is_numeric($row["regdate"]) ? intval($row["regdate"]) : strtotime($row["regdate"])) : "-";
	if (intval($row["convidado"]) == 1) { $f["adm"] .= "<span class=\"small\" style=\"color:#7a5a30\">".t("Personagem sem conta (convidado)")."</span><br>"; }

	// Equipamentos (variante rara no ícone e no nome) e tooltips com os até 3 bônus de cada um.
	foreach (array("weaponid","armorid","shieldid","slot1id","slot2id","slot3id","slot4id","weaponname","armorname","shieldname","slot1name","slot2name","slot3name","slot4name") as $c) { $f[$c] = $row[$c]; }
	iconesequipados($f);
	$durabm = array_pad(explode(",", (string)$row["durabilidade"]), 8, "X");
	for ($i = 1; $i <= 7; $i++) { $f["durabm".$i] = ($durabm[$i] == "X") ? "*" : $durabm[$i]; }
	$bruto = array();
	foreach (array("weaponname","armorname","shieldname","slot1name","slot2name","slot3name","slot4name") as $c) { $bruto[$c] = $f[$c]; $f[$c] = ($f[$c] == "None" || $f[$c] == "") ? "-" : nomeitemhtml($f[$c]); }
	$f["armaatr"] = conteudoexplic($row["weaponid"], '1', 'armaatr', $durabm[1], $bruto["weaponname"]);
	$segunda = "layoutnovo/equipamentos/".intval($row["weaponid"])."d.gif";
	$f["segundaarma"] = file_exists($segunda) ? "<img src=\"".iconeitem($row["weaponid"], false, $bruto["weaponname"], "d")."\" border=\"0\" onMouseOver=\"".$f["armaatr"]."\" onmouseout=\"fecharexplic();\" alt=\"\" />" : "";
	$f["armoratr"] = conteudoexplic($row["armorid"], '2', 'armoratr', $durabm[2], $bruto["armorname"]);
	$f["shieldatr"] = conteudoexplic($row["shieldid"], '3', 'shieldatr', $durabm[3], $bruto["shieldname"]);
	for ($i = 1; $i <= NUMACESSORIOS; $i++) { $f["slot".$i."atr"] = conteudoexplic($row["slot".$i."id"], '4', 'slot'.$i.'atr', $durabm[3 + $i], $bruto["slot".$i."name"]); }

	// Jutsus aprendidos (users.spells = "0,1,4,...") mais Senjutsu e Jutsu de Busca, se treinados.
	$meus = explode(",", (string)$row["spells"]);
	$f["magiclist"] = "";
	$q = doquery("SELECT id,name FROM {{table}} ORDER BY id", "spells");
	while ($s = mysqli_fetch_array($q)) { if (in_array($s["id"], $meus)) { $f["magiclist"] .= t($s["name"])."<br />"; } }
	if ($f["magiclist"] == "") { $f["magiclist"] = t("Nenhum"); }
	if ($row["senjutsuhtml"] != "") { $f["magiclist"] = "<font color=darkgreen>".t("Senjutsu")."</font><br>".$f["magiclist"]; }
	if ($row["jutsudebuscahtml"] != "") { $f["magiclist"] = "<font color=darkgreen>".t("Jutsu de Busca")."</font><br>".$f["magiclist"]; }

	return $f;

}

}
?>
