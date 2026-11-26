<?php // funcoesroubo.php :: Inimigos que roubam itens do jogador (naruto_itens_roubados) e status que inimigos causam no jogador (veneno, queimadura, sono, atordoar, dreno).
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }
include_once(__DIR__.'/funcoesinclusas.php');

/* ------------------------------------------------------------------------
   Roubo (naruto_control: roubo_ativo, roubo_chance_mochila, roubo_chance_equipado em % por turno;
   naruto_monsters.rouba 0-100: 0 = não rouba, 50 = chance base, 100 = o dobro).
   - No turno do inimigo (quando ele ataca), sorteia o roubo de um item equipado e, se não, da mochila. No máximo um por luta
     (users.roubo_luta, gravado com UPDATE condicional dentro da transação: cliques paralelos não roubam duas vezes).
   - O item vai para naruto_itens_roubados no mesmo texto da mochila/banco ("nome,id,tipo,durabilidade[,qtd]"; o nome leva
     o refino "+N" e a variante ":efeito"). Equipado sai pelo desequiparitem($qual, true): atributos e conjuntos recalculados.
   - naruto_monsters.roubo_modo 1: só volta se o jogador vencer ESTA luta; qualquer outro fim (fuga, morte, abandono, luta nova) = perdido
     (roubopurgar(), com aviso no histórico). roubo_modo 2: fica "com aquele tipo de inimigo" e volta ao vencer qualquer
     inimigo do mesmo id. Cada linha guarda o modo em que foi roubada.
   Status (naruto_control: status_monstros_ativo, status_monstros_mult em %; naruto_monsters.efeito + efeito_chance):
   - Aplicados quando o ataque do inimigo acerta, no lado "jog" do users.jutsuestado (somem no fim da luta, como os de jutsu).
   - veneno/queimadura: dano por turno (12% do ataque do inimigo, 3 turnos; não matam: deixam com 1 HP).
     atordoar/sono: o jogador perde o próximo turno. dreno: tira chakra na hora (50% do ataque do inimigo).
   ------------------------------------------------------------------------ */

if (!function_exists('roubocfg')){
function roubocfg() { // Configuração (com padrões se a migração ainda não rodou).
	global $controlrow;
	$c = is_array($controlrow) ? $controlrow : array();
	$v = function ($k, $pad) use ($c) { return isset($c[$k]) ? intval($c[$k]) : $pad; };
	return array("ativo"=>$v("roubo_ativo", 1), "mochila"=>max(0, $v("roubo_chance_mochila", 3)), "equipado"=>max(0, $v("roubo_chance_equipado", 1)), "status"=>$v("status_monstros_ativo", 1), "mult"=>max(0, $v("status_monstros_mult", 100)));
}}

if (!function_exists('roubochance')){
function roubochance($monsterrow, $tipo) { // Chance (%, pode ter fração) por turno de o inimigo roubar ("mochila" ou "equipado").
	$cfg = roubocfg();
	$r = isset($monsterrow["rouba"]) ? max(0, min(100, intval($monsterrow["rouba"]))) : 0;
	if (!$cfg["ativo"] || $r <= 0) { return 0; }
	return $cfg[$tipo] * $r / 50;
}}

if (!function_exists('roubomodo')){
function roubomodo($monsterrow) { return (isset($monsterrow["roubo_modo"]) && intval($monsterrow["roubo_modo"]) == 2) ? 2 : 1; } // naruto_monsters.roubo_modo (padrão 1).
}

if (!function_exists('robotexto')){
function robotexto($item) { return (strpos((string)$item, "ryou:") === 0) ? tf("%s Ryou", intval(substr((string)$item, 5))) : tnomeitem(explode(",", (string)$item)[0]); } // Nome (traduzido) do item guardado.
}

/* Roubo de Ryou (naruto_monsters.rouba_ryou 0-100, mesma escala do "rouba": 50 = control.roubo_ryou_chance % por turno, padrão 4).
   Leva uma porcentagem sorteada entre control.roubo_ryou_min e roubo_ryou_max (padrão 5-15%) do Ryou que o jogador carrega,
   no máximo control.roubo_ryou_teto × nível do inimigo (padrão 50; 0 = sem teto). Fica em naruto_itens_roubados
   como "ryou:N" (origem "ryou"), com as mesmas regras de recuperação do item (roubo_modo) e no máximo um roubo por luta. */
if (!function_exists('rouboryouchance')){
function rouboryouchance($monsterrow) {
	global $controlrow;
	$r = isset($monsterrow["rouba_ryou"]) ? max(0, min(100, intval($monsterrow["rouba_ryou"]))) : 0;
	if (!roubocfg()["ativo"] || $r <= 0) { return 0; }
	return (isset($controlrow["roubo_ryou_chance"]) ? max(0, intval($controlrow["roubo_ryou_chance"])) : 4) * $r / 50;
}}
if (!function_exists('rouboryoufazer')){
function rouboryoufazer($monsterrow) { // Rouba (atômico) uma parte do Ryou. Devolve a quantia ou 0.
	global $userrow, $controlrow;
	$id = intval($userrow["id"]);
	$cf = function ($k, $p) use ($controlrow) { return isset($controlrow[$k]) ? intval($controlrow[$k]) : $p; };
	$pmin = max(1, min(100, $cf("roubo_ryou_min", 5))); $pmax = max($pmin, min(100, $cf("roubo_ryou_max", 15))); $teto = max(0, $cf("roubo_ryou_teto", 50));
	$pct = mt_rand($pmin * 10, $pmax * 10) / 10; // varia a cada roubo (com uma casa decimal).
	doquery("START TRANSACTION", "users");
	$f = mysqli_fetch_array(doquery("SELECT id,gold,currentaction,currentmonster,roubo_luta FROM {{table}} WHERE id='$id' LIMIT 1 FOR UPDATE", "users"));
	if (!$f || $f["currentaction"] != "Fighting" || intval($f["roubo_luta"]) != 0 || intval($f["currentmonster"]) != intval($monsterrow["id"])) { doquery("ROLLBACK", "users"); return 0; }
	$valor = (int)ceil(intval($f["gold"]) * $pct / 100);
	if ($teto > 0) { $valor = min($valor, $teto * max(1, intval($monsterrow["level"]))); }
	if ($valor <= 0) { doquery("ROLLBACK", "users"); return 0; }
	doquery("UPDATE {{table}} SET gold=gold-$valor, roubo_luta=1 WHERE id='$id' AND gold>=$valor AND roubo_luta=0 LIMIT 1", "users");
	if (mysqli_affected_rows($GLOBALS["link"]) != 1) { doquery("ROLLBACK", "users"); return 0; }
	doquery("INSERT INTO {{table}} SET jogador='$id', monstro='".intval($monsterrow["id"])."', item='ryou:$valor', origem='ryou', modo='".roubomodo($monsterrow)."', quando='".time()."'", "itens_roubados");
	doquery("COMMIT", "users");
	$userrow["gold"] = intval($userrow["gold"]) - $valor; $userrow["roubo_luta"] = 1;
	return $valor;
}}

if (!function_exists('roubotentar')){
function roubotentar($monsterrow, &$pagearray) { // Turno do inimigo: sorteia e faz o roubo. Devolve true se roubou.
	global $userrow;
	if (!empty($userrow["roubo_luta"])) { return false; }
	foreach (array("equipado", "mochila") as $tipo) {
		$ch = roubochance($monsterrow, $tipo);
		if ($ch > 0 && mt_rand(1, 10000) <= $ch * 100) {
			$item = roubofazer($monsterrow, $tipo);
			if ($item === false) { return false; }
			$txt = tf("O inimigo roubou seu item %s!", robotexto($item));
			$extra = (roubomodo($monsterrow) == 1) ? t("Derrote-o nesta luta para recuperar!") : t("Derrote um inimigo igual a ele para recuperar.");
			$pagearray["monsterturn"] .= "<span class=\"avisoroubo\" style=\"color:#8b1a1a;font-weight:bold\"><img src=\"images/status/roubo.png\" width=\"12\" height=\"12\" alt=\"\" style=\"vertical-align:middle\"> ".htmlspecialchars($txt." ".$extra, ENT_QUOTES, "UTF-8")."</span><br />";
			return true;
		}
	}
	$ch = rouboryouchance($monsterrow);
	if ($ch > 0 && mt_rand(1, 10000) <= $ch * 100) {
		$v = rouboryoufazer($monsterrow);
		if ($v > 0) {
			$txt = tf("O inimigo roubou %s Ryou de você!", $v);
			$extra = (roubomodo($monsterrow) == 1) ? t("Derrote-o nesta luta para recuperar!") : t("Derrote um inimigo igual a ele para recuperar.");
			$pagearray["monsterturn"] .= "<span class=\"avisoroubo\" style=\"color:#8b1a1a;font-weight:bold\"><img src=\"images/status/roubo.png\" width=\"12\" height=\"12\" alt=\"\" style=\"vertical-align:middle\"> ".htmlspecialchars($txt." ".$extra, ENT_QUOTES, "UTF-8")."</span><br />";
			return true;
		}
	}
	return false;
}}

if (!function_exists('roubofazer')){
function roubofazer($monsterrow, $tipo) { // Rouba (atômico) um item da mochila ou equipado. Devolve o texto do item ou false.
	global $userrow;
	$mem = $userrow;
	$id = intval($userrow["id"]);
	doquery("START TRANSACTION", "users");
	$f = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1 FOR UPDATE", "users"));
	if (!$f || $f["currentaction"] != "Fighting" || intval($f["roubo_luta"]) != 0 || intval($f["currentmonster"]) != intval($monsterrow["id"])) { doquery("ROLLBACK", "users"); return false; }
	$item = false;
	if ($tipo == "mochila") {
		$cand = array();
		for ($i = 1; $i <= 8; $i++) { if (isset($f["bp".$i]) && $f["bp".$i] != "None" && $f["bp".$i] != "") { $cand[] = $i; } }
		if (!count($cand)) { doquery("ROLLBACK", "users"); return false; }
		$s = $cand[array_rand($cand)];
		$orig = (string)$f["bp".$s];
		$item = itemcomqtd($orig, 1); // pilha: leva só 1 unidade.
		$resto = itemcomqtd($orig, qtditem($orig) - 1);
		doquery("UPDATE {{table}} SET bp$s='".addslashes($resto)."', roubo_luta=1 WHERE id='$id' AND bp$s='".addslashes($orig)."' AND roubo_luta=0 LIMIT 1", "users");
		if (mysqli_affected_rows($GLOBALS["link"]) != 1) { doquery("ROLLBACK", "users"); return false; }
		$mem["bp".$s] = $resto;
	} else {
		$slots = slotsequipamento();
		$cand = array();
		foreach ($slots as $q => $campo) { if (isset($f[$campo."name"]) && $f[$campo."name"] != "None" && $f[$campo."name"] != "") { $cand[] = $q; } }
		if (!count($cand)) { doquery("ROLLBACK", "users"); return false; }
		$q = $cand[array_rand($cand)];
		$campo = $slots[$q];
		$dur = lerdurabilidade($f["durabilidade"]);
		$item = $f[$campo."name"].",".intval($f[$campo."id"]).",".($q <= 3 ? $q : 4).",".$dur[$q]; // mesmo formato do desequipar.
		doquery("UPDATE {{table}} SET roubo_luta=1 WHERE id='$id' AND roubo_luta=0 LIMIT 1", "users");
		if (mysqli_affected_rows($GLOBALS["link"]) != 1) { doquery("ROLLBACK", "users"); return false; }
		include_once(__DIR__.'/desequipar.php');
		$userrow = $f;
		if (desequiparitem($q, true) != "ok") { $userrow = $mem; doquery("ROLLBACK", "users"); return false; }
		// A luta continua com os valores da memória (HP desta rodada etc.); equipamentos e atributos vêm do banco atualizado.
		$novo = $userrow;
		$userrow = $mem;
		$campos = array("durabilidade","maxhp","maxmp","maxtp","maxnp","maxep","attackpower","defensepower","strength","dexterity","expbonus","goldbonus","sorte","agilidade",
			"determinacao","precisao","inteligencia","droprate","critico","esquiva","regenhp","regenmp","regentp","regennp","regenep","economiachakra","conjuntobonus");
		foreach ($slots as $s2) { $campos[] = $s2."name"; $campos[] = $s2."id"; }
		foreach ($campos as $c) { if (array_key_exists($c, $novo)) { $mem[$c] = $novo[$c]; } }
		foreach (array("hp", "mp", "tp", "np", "ep") as $b) { $mem["current".$b] = min(intval($mem["current".$b]), intval($mem["max".$b])); }
		$mem["currenthp"] = max(1, $mem["currenthp"]);
	}
	doquery("INSERT INTO {{table}} SET jogador='$id', monstro='".intval($monsterrow["id"])."', item='".addslashes($item)."', origem='".($tipo == "mochila" ? "mochila" : "equipado")."', modo='".roubomodo($monsterrow)."', quando='".time()."'", "itens_roubados");
	doquery("COMMIT", "users");
	$mem["roubo_luta"] = 1;
	$userrow = $mem;
	return $item;
}}

if (!function_exists('roubodevolver')){
function roubodevolver($idjog, $idmonstro) { // Vitória (chamar DENTRO da transação da vitória): devolve os itens que este inimigo levou. Devolve HTML (ou "").
	$idjog = intval($idjog); $idmonstro = intval($idmonstro);
	$q = doquery("SELECT * FROM {{table}} WHERE jogador='$idjog' AND monstro='$idmonstro' ORDER BY id FOR UPDATE", "itens_roubados");
	if (!$q || mysqli_num_rows($q) == 0) { return ""; }
	$u = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$idjog' LIMIT 1 FOR UPDATE", "users"));
	if (!$u) { return ""; }
	$banco = ((string)$u["bancogeral"] == "None" || (string)$u["bancogeral"] == "") ? array() : array_values(array_filter(explode(";", (string)$u["bancogeral"]), "strlen"));
	$linhas = ""; $apagar = array();
	while ($r = mysqli_fetch_array($q)) {
		$nome = htmlspecialchars(robotexto($r["item"]), ENT_QUOTES, "UTF-8");
		if (strpos((string)$r["item"], "ryou:") === 0) { // Ryou roubado: volta direto para o jogador.
			$u["gold"] = intval($u["gold"]) + intval(substr((string)$r["item"], 5));
			$linhas .= "<div class=\"cxb-linha\">".tf("Você recuperou <b>%s</b>.", $nome)."</div>"; $apagar[] = intval($r["id"]); $ganhouryou = true; continue;
		}
		if (mochilaguardar($u, $r["item"]) !== false) { $linhas .= "<div class=\"cxb-linha\">".tf("Você recuperou <b>%s</b> (mochila).", $nome)."</div>"; }
		elseif (count($banco) < 30) { $banco[] = itemsemqtd($r["item"]).(qtditem($r["item"]) > 1 ? ",".qtditem($r["item"]) : ""); $linhas .= "<div class=\"cxb-linha\">".tf("Você recuperou <b>%s</b>: a mochila estava cheia, ele foi para o banco.", $nome)."</div>"; }
		else { $linhas .= "<div class=\"cxb-linha\">".tf("Mochila e banco cheios: <b>%s</b> continua com o inimigo.", $nome)."</div>"; continue; }
		$apagar[] = intval($r["id"]);
	}
	if (count($apagar)) {
		doquery("DELETE FROM {{table}} WHERE id IN (".implode(",", $apagar).") AND jogador='$idjog'", "itens_roubados");
		$sets = array();
		for ($i = 1; $i <= 8; $i++) { $sets[] = "bp$i='".addslashes($u["bp".$i])."'"; }
		$sets[] = "bancogeral='".addslashes(count($banco) ? implode(";", $banco).";" : "None")."'";
		if (!empty($ganhouryou)) { $sets[] = "gold='".intval($u["gold"])."'"; global $userrow; if (is_array($userrow) && intval($userrow["id"]) == $idjog) { $userrow["gold"] = intval($u["gold"]); } }
		$GLOBALS["roubocachevelho"] = true; // o cartão do inimigo não mostra mais o que já voltou
		doquery("UPDATE {{table}} SET ".implode(",", $sets)." WHERE id='$idjog' LIMIT 1", "users");
	}
	return "<div class=\"cxb\" id=\"itemrecuperado\" style=\"margin:4px auto;max-width:460px\"><div class=\"cxb-cab\"><img src=\"images/status/roubo.png\" width=\"12\" height=\"12\" alt=\"\"> ".t("Item recuperado")."</div><div class=\"cxb-corpo\">".$linhas."</div></div>";
}}

if (!function_exists('roubopurgar')){
function roubopurgar($idjog) { // Modo 1: a luta acabou sem vitória: os itens roubados nela se perdem (aviso no histórico).
	$idjog = intval($idjog);
	if ($idjog <= 0) { return; }
	$q = doquery("SELECT id FROM {{table}} WHERE jogador='$idjog' AND modo=1 LIMIT 1", "itens_roubados");
	if (!$q || mysqli_num_rows($q) == 0) { return; } // caminho rápido: nada a fazer.
	doquery("START TRANSACTION", "users");
	$u = mysqli_fetch_array(doquery("SELECT id,historico FROM {{table}} WHERE id='$idjog' LIMIT 1 FOR UPDATE", "users"));
	$q = doquery("SELECT * FROM {{table}} WHERE jogador='$idjog' AND modo=1 FOR UPDATE", "itens_roubados");
	$hist = ($u["historico"] == "None" || $u["historico"] == "") ? "" : $u["historico"];
	$ids = array();
	while ($r = mysqli_fetch_array($q)) {
		$ids[] = intval($r["id"]);
		$m = mysqli_fetch_array(doquery("SELECT name FROM {{table}} WHERE id='".intval($r["monstro"])."' LIMIT 1", "monsters"));
		$aviso = (strpos((string)$r["item"], "ryou:") === 0) ? i18n("Você não derrotou %s: os %s Ryou que ele roubou foram perdidos.", $m ? $m["name"] : "?", intval(substr((string)$r["item"], 5)))
			: i18n("Você não derrotou %s: o item %s que ele roubou foi perdido.", $m ? $m["name"] : "?", explode(",", $r["item"])[0]);
		$hist = ($hist == "") ? $aviso : $hist.";;".$aviso;
	}
	if (count($ids)) {
		doquery("DELETE FROM {{table}} WHERE id IN (".implode(",", $ids).")", "itens_roubados");
		doquery("UPDATE {{table}} SET historico='".addslashes($hist)."' WHERE id='$idjog' LIMIT 1", "users");
	}
	doquery("COMMIT", "users");
}}

if (!function_exists('roubocomjogador')){
function roubocomjogador($idmonstro) { // Itens do jogador logado com este inimigo (lista de textos).
	global $userrow;
	if (!is_array($userrow) || empty($userrow["id"])) { return array(); }
	static $cache = null;
	if ($cache === null || !empty($GLOBALS["roubocachevelho"])) {
		$GLOBALS["roubocachevelho"] = false;
		$cache = array();
		$q = doquery("SELECT monstro,item,modo FROM {{table}} WHERE jogador='".intval($userrow["id"])."' ORDER BY id", "itens_roubados");
		while ($q && $r = mysqli_fetch_array($q)) { $cache[intval($r["monstro"])][] = $r; }
	}
	return isset($cache[intval($idmonstro)]) ? $cache[intval($idmonstro)] : array();
}}

/* ---------------- Status no jogador ---------------- */

if (!function_exists('statusmonstros')){
function statusmonstros() { // efeito => rótulo do cartão, ícone, rótulo do status.
	return array(
		"veneno"=>array("Envenena %s%%", "veneno", "Envenenado"),
		"queimadura"=>array("Queima %s%%", "queimadura", "Queimadura"),
		"atordoar"=>array("Atordoa %s%%", "atordoado", "Atordoado"),
		"sono"=>array("Faz dormir %s%%", "sono", "Dormindo"),
		"dreno"=>array("Drena chakra %s%%", "dreno", "Dreno de Chakra"));
}}

if (!function_exists('statuschance')){
function statuschance($monsterrow) { // Chance final (%) do efeito do inimigo, já com o multiplicador global. 0 = não causa.
	$cfg = roubocfg();
	$ef = isset($monsterrow["efeito"]) ? (string)$monsterrow["efeito"] : "";
	$tab = statusmonstros();
	if (!$cfg["status"] || !isset($tab[$ef])) { return 0; }
	return max(0, min(100, (int)round(intval($monsterrow["efeito_chance"]) * $cfg["mult"] / 100)));
}}

if (!function_exists('statusaplicar')){
function statusaplicar($monsterrow, &$pagearray, &$estado) { // Ataque do inimigo acertou: rola o efeito dele no jogador.
	global $userrow;
	$chance = statuschance($monsterrow);
	if ($chance <= 0) { return; }
	$resist = function_exists('classedojogador') ? intval(classedojogador($userrow)["resist_debuff"]) : 0; // especialização: resistência.
	if ($resist > 0) { $chance = intdiv($chance * max(0, 100 - min(100, $resist)), 100); }
	if (mt_rand(1, 100) > $chance) { return; }
	$ef = (string)$monsterrow["efeito"];
	$forca = max(1, (int)ceil(intval($monsterrow["maxdam"]) * 12 / 100));
	$msg = "";
	if ($ef == "veneno" || $ef == "queimadura") {
		$v = $forca;
		if (isset($estado["efeitos"]["jog"][$ef]) && intval($estado["efeitos"]["jog"][$ef]["v"]) > $v) { $v = intval($estado["efeitos"]["jog"][$ef]["v"]); }
		$estado["efeitos"]["jog"][$ef] = array("v"=>$v, "t"=>3, "n"=>(string)$monsterrow["name"], "m"=>1);
		$msg = ($ef == "veneno") ? tf("Você foi envenenado (%s por turno, %s turnos).", $v, 3) : tf("Você está em chamas (%s por turno, %s turnos).", $v, 3);
	} elseif ($ef == "atordoar" || $ef == "sono") {
		if (statusperdeturno($estado) != "") { return; } // não renova: não prende o jogador para sempre.
		$estado["efeitos"]["jog"][$ef] = array("v"=>0, "t"=>2, "n"=>(string)$monsterrow["name"], "m"=>1); // 2 = sobrevive ao fim desta rodada: perde o próximo turno.
		$msg = ($ef == "sono") ? t("Você adormeceu e vai perder o próximo turno.") : t("Você ficou atordoado e vai perder o próximo turno.");
	} elseif ($ef == "dreno") {
		$tirado = min(intval($userrow["currentmp"]), max(1, (int)ceil(intval($monsterrow["maxdam"]) * 50 / 100)));
		if ($tirado <= 0) { return; }
		$userrow["currentmp"] = intval($userrow["currentmp"]) - $tirado;
		$msg = tf("O inimigo drenou %s do seu Chakra.", $tirado);
	}
	if ($msg != "") {
		$tab = statusmonstros();
		$pagearray["monsterturn"] .= "<span class=\"statusinimigo\" style=\"color:#6a1a6a;font-weight:bold\"><img src=\"images/status/".$tab[$ef][1].".png\" width=\"12\" height=\"12\" alt=\"\" style=\"vertical-align:middle\"> ".htmlspecialchars($msg, ENT_QUOTES, "UTF-8")."</span><br />";
	}
}}

if (!function_exists('statusperdeturno')){
function statusperdeturno($estado) { // "atordoar"/"sono" se o jogador perde o turno agora, ou "".
	foreach (array("sono", "atordoar") as $ef) { if (isset($estado["efeitos"]["jog"][$ef]) && intval($estado["efeitos"]["jog"][$ef]["t"]) > 0) { return $ef; } }
	return "";
}}

if (!function_exists('statusjogadorcaixa')){
function statusjogadorcaixa($estado) { // Linha "Seus efeitos:" com ícone, nome e turnos restantes de cada efeito no jogador ("" se nenhum).
	if (empty($estado["efeitos"]["jog"])) { return ""; }
	$h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
	$jut = jutsuefeitos(); $mon = statusmonstros();
	$icones = array("atordoar"=>"atordoado");
	$partes = array();
	foreach ($estado["efeitos"]["jog"] as $ef => $d) {
		$t = intval($d["t"]);
		if ($ef == "sono" || $ef == "atordoar") { $rot = t($ef == "sono" ? "Dormindo" : "Atordoado"); $txt = t("perde o turno"); $ico = ($ef == "sono") ? "sono" : "atordoado"; }
		elseif (isset($jut[$ef]) && $jut[$ef]["icone"] != "") {
			$rot = t($jut[$ef]["rotulo"]); $ico = $jut[$ef]["icone"];
			$txt = in_array($ef, array("veneno", "queimadura", "cura_turnos")) ? tf("%s/turno, %s turno(s)", intval($d["v"]), $t) : tf("%s turno(s)", $t);
		} else { continue; }
		$partes[] = "<span class=\"stj stj-".$h($ef)."\" title=\"".$h($rot.": ".$txt)."\" style=\"display:inline-block;white-space:nowrap;margin:1px 3px\"><img src=\"images/status/".$h($ico).".png\" width=\"12\" height=\"12\" alt=\"\" style=\"vertical-align:middle\"> <b>".$h($rot)."</b> <span style=\"font-size:10px\">(".$h($txt).")</span></span>";
	}
	if (!count($partes)) { return ""; }
	return "<center class=\"small\" id=\"statusjogador\" style=\"max-width:320px;margin:2px auto;background:#fff1c7;border:1px solid #452202;border-radius:3px;padding:1px 3px\">".$h(t("Seus efeitos:"))." ".implode(" ", $partes)."</center>";
}}

if (!function_exists('statusavisobotao')){
function statusavisobotao($estado, $comando) { // Jogador atordoado/dormindo: aviso acima dos botões e o "Atacar" vira "Passar o turno".
	$ef = statusperdeturno($estado);
	if ($ef == "") { return $comando; }
	$aviso = ($ef == "sono") ? t("Você está dormindo: o próximo turno será perdido (qualquer ação passa a vez).") : t("Você está atordoado: o próximo turno será perdido (qualquer ação passa a vez).");
	$comando = str_replace("> ".t("Atacar")." <span", "> ".t("Passar o turno")." <span", $comando);
	return "<div id=\"avisoperdeturno\" style=\"text-align:center;color:#8b1a1a;font-weight:bold;margin:2px 0 6px\"><img src=\"images/status/".($ef == "sono" ? "sono" : "atordoado").".png\" width=\"12\" height=\"12\" alt=\"\" style=\"vertical-align:middle\"> ".htmlspecialchars($aviso, ENT_QUOTES, "UTF-8")."</div>".$comando;
}}

/* ---------------- Selos do cartão do monstro e da ajuda ---------------- */

if (!function_exists('cartaoselos')){
function cartaoselos($monsterrow, $traco = "") { // Linha comum de selos pequenos: traço da raridade ($traco, HTML pronto), "Rouba itens", "Envenena 20%" e o item do jogador com ele. "" se não houver nada.
	$h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
	$s = ($traco != "") ? $traco : "";
	if (roubochance($monsterrow, "mochila") > 0 || roubochance($monsterrow, "equipado") > 0) {
		$s .= "<span class=\"cselo2 sroubo\" title=\"".$h(t("Pode roubar um item da mochila ou equipado durante a luta."))."\"><img src=\"images/status/roubo.png\" alt=\"\">".$h(t("Rouba itens"))."</span>";
	}
	if (rouboryouchance($monsterrow) > 0) {
		$s .= "<span class=\"cselo2 sroubo\" title=\"".$h(t("Pode roubar parte do seu Ryou durante a luta."))."\"><img src=\"images/status/roubo.png\" alt=\"\">".$h(t("Rouba Ryou"))."</span>";
	}
	$cf = isset($monsterrow["chancefuga"]) ? intval($monsterrow["chancefuga"]) : -1;
	if ($cf >= 0 && $cf < 100) {
		$s .= "<span class=\"cselo2 sfuga\" title=\"".$h($cf == 0 ? t("Não é possível fugir deste inimigo.") : tf("Chance de conseguir fugir: %s%%", $cf))."\"><img src=\"images/status/bloqueiofuga.png\" alt=\"\">".$h($cf == 0 ? t("Impede fuga") : tf("Fuga %s%%", $cf))."</span>";
	}
	$ch = statuschance($monsterrow);
	if ($ch > 0) {
		$tab = statusmonstros(); $ef = (string)$monsterrow["efeito"];
		$s .= "<span class=\"cselo2 sefeito\" title=\"".$h(tf("Chance de causar no seu personagem a cada ataque que acerta: %s%%", $ch))."\"><img src=\"images/status/".$tab[$ef][1].".png\" alt=\"\">".$h(tf($tab[$ef][0], $ch))."</span>";
	}
	foreach (roubocomjogador(isset($monsterrow["id"]) ? $monsterrow["id"] : 0) as $r) {
		$nome = robotexto($r["item"]);
		$ehryou = (strpos((string)$r["item"], "ryou:") === 0);
		$txt = $ehryou ? ((intval($r["modo"]) == 1) ? tf("Roubou %s — derrote-o para recuperar!", $nome) : tf("Está com %s seus", $nome))
			: ((intval($r["modo"]) == 1) ? tf("Roubou seu item: %s — derrote-o para recuperar!", $nome) : tf("Está com o seu item: %s", $nome));
		$s .= "<span class=\"cselo2 sitem\" title=\"".$h($txt)."\"><img src=\"images/status/roubo.png\" alt=\"\">".$h($txt)."</span>";
	}
	return ($s == "") ? "" : "<div class=\"cselos\">".$s."</div>";
}}
?>
