<?php // funcoesdiarias.php :: Missões diárias (3 por dia por jogador) e avisos curtos da coluna (mochila cheia).
//
// Estado em users.diarias (JSON): {"d":"AAAA-MM-DD","m":[{"t":tipo,"e":elemento,"n":meta,"p":progresso,"r":0|1}, ...]}.
// O dia vira à meia-noite de America/Sao_Paulo. O sorteio usa um hash de (id do jogador + data), então é sempre o mesmo
// no mesmo dia, mesmo que a coluna ainda não tenha sido gravada. Gravações usam "compare-and-set" (WHERE diarias=antigo),
// que funciona dentro ou fora de uma transação já aberta (victory() chama dentro).
// Tipos: vencer (N inimigos), elemento (N inimigos de um elemento), andar (N casas), taijutsu (N jutsus type=7),
//        jutsu (N jutsus quaisquer), pvp (1 duelo vencido).
// Ganchos: fight.php victory() e uso de jutsu, explore.php (cada casa), users.php dueloencerrar(). Resgate: index.php?do=diarias (POST).

if (!function_exists('diarias_hoje')){
function diarias_hoje(){ // Data de hoje no fuso de Brasília (o reset é à meia-noite de lá).
	$d = new DateTime("now", new DateTimeZone("America/Sao_Paulo"));
	return $d->format("Y-m-d");
}}

if (!function_exists('diarias_sortear')){
function diarias_sortear($idjogador, $nivel, $dia){ // 3 missões de tipos diferentes, metas proporcionais ao nível.
	$nivel = max(1, intval($nivel));
	$semente = crc32("diarias|".intval($idjogador)."|".$dia);
	$sorte = function ($max) use (&$semente) { // gerador próprio (não mexe no mt_rand global da luta).
		$semente = ($semente * 1103515245 + 12345) & 0x7fffffff;
		return $semente % $max;
	};
	$tipos = array("vencer", "elemento", "andar", "taijutsu", "jutsu", "pvp");
	$elementos = array("fogo", "agua", "terra", "vento", "raio");
	$lista = array();
	while (count($lista) < 3) {
		$t = $tipos[$sorte(count($tipos))];
		if (isset($lista[$t])) { continue; }
		$e = "";
		if ($t == "vencer") { $n = min(15, 4 + intdiv($nivel, 8)); }
		elseif ($t == "elemento") { $n = min(8, 2 + intdiv($nivel, 15)); $e = $elementos[$sorte(count($elementos))]; }
		elseif ($t == "andar") { $n = min(150, 20 + $nivel); }
		elseif ($t == "taijutsu") { $n = min(10, 3 + intdiv($nivel, 15)); }
		elseif ($t == "jutsu") { $n = min(15, 5 + intdiv($nivel, 10)); }
		else { $n = 1; }
		$lista[$t] = array("t" => $t, "e" => $e, "n" => $n, "p" => 0, "r" => 0, "pocao" => ($sorte(3) == 0 ? 431 + $sorte(5) : 0));
	}
	return array("d" => $dia, "m" => array_values($lista));
}}

if (!function_exists('diarias_estado')){
function diarias_estado($row){ // Lê users.diarias; se for de outro dia (ou vazio), sorteia as de hoje.
	$hoje = diarias_hoje();
	$e = json_decode((string)(isset($row["diarias"]) ? $row["diarias"] : ""), true);
	if (!is_array($e) || !isset($e["d"]) || $e["d"] !== $hoje || !isset($e["m"]) || !is_array($e["m"])) {
		$e = diarias_sortear($row["id"], $row["level"], $hoje);
	}
	return $e;
}}

if (!function_exists('diarias_premio')){
function diarias_premio($nivel){ // Recompensa pequena por missão: array(ryou, exp).
	$nivel = max(1, intval($nivel));
	return array(30 + 15 * $nivel, 20 + 8 * $nivel);
}}

if (!function_exists('diarias_texto')){
function diarias_texto($m){ // Objetivo da missão no idioma do jogador.
	$nomes = array("fogo" => "Fogo", "agua" => "Água", "terra" => "Terra", "vento" => "Vento", "raio" => "Raio");
	switch ($m["t"]) {
		case "vencer": return tf("Derrote %s inimigos", $m["n"]);
		case "elemento": return tf("Derrote %s inimigos de %s", $m["n"], t(isset($nomes[$m["e"]]) ? $nomes[$m["e"]] : $m["e"]));
		case "andar": return tf("Ande %s casas no mapa", $m["n"]);
		case "taijutsu": return tf("Use %s jutsus de Taijutsu em batalha", $m["n"]);
		case "jutsu": return tf("Use %s jutsus em batalha", $m["n"]);
		default: return t("Vença 1 duelo PvP");
	}
}}

if (!function_exists('diarias_contar')){
function diarias_contar($idjogador, $tipo, $qtd = 1, $elemento = ""){ // Soma progresso nas missões de hoje desse tipo.
	global $link;
	$idjogador = intval($idjogador); $qtd = max(0, intval($qtd));
	if ($idjogador <= 0 || $qtd == 0) { return; }
	for ($tentativa = 0; $tentativa < 4; $tentativa++) {
		$row = mysqli_fetch_array(doquery("SELECT id,level,diarias FROM {{table}} WHERE id='$idjogador' LIMIT 1", "users"));
		if (!$row) { return; }
		$e = diarias_estado($row);
		$mudou = false;
		foreach ($e["m"] as $i => $m) {
			if ($m["t"] !== $tipo || intval($m["p"]) >= intval($m["n"])) { continue; }
			if ($tipo == "elemento" && $m["e"] !== $elemento) { continue; }
			$e["m"][$i]["p"] = min(intval($m["n"]), intval($m["p"]) + $qtd); $mudou = true;
		}
		if (!$mudou) { return; }
		doquery("UPDATE {{table}} SET diarias='".addslashes(json_encode($e))."' WHERE id='$idjogador' AND diarias='".addslashes((string)$row["diarias"])."' LIMIT 1", "users");
		if (mysqli_affected_rows($link) > 0) { return; } // outra gravação passou na frente: lê de novo e tenta outra vez.
	}
}}

if (!function_exists('diarias_vitoria')){
function diarias_vitoria($row, $monstro){ // Gancho de fight.php victory().
	diarias_contar($row["id"], "vencer", 1);
	if (isset($monstro["elemento"])) { diarias_contar($row["id"], "elemento", 1, (string)$monstro["elemento"]); }
}}

if (!function_exists('diarias_jutsu')){
function diarias_jutsu($row, $spell){ // Gancho do uso de jutsu em fight.php.
	diarias_contar($row["id"], "jutsu", 1);
	if (intval($spell["type"]) == 7) { diarias_contar($row["id"], "taijutsu", 1); }
}}

if (!function_exists('diarias_resgatar')){
function diarias_resgatar($row, $indice){ // Paga a missão $indice (0-2) uma única vez. Devolve a mensagem para o jogador.
	global $link;
	$indice = intval($indice);
	doquery("START TRANSACTION", "users");
	$u = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($row["id"])."' LIMIT 1 FOR UPDATE", "users"));
	if (!$u) { doquery("ROLLBACK", "users"); return t("Missão inválida."); }
	$e = diarias_estado($u);
	if ($indice < 0 || !isset($e["m"][$indice])) { doquery("ROLLBACK", "users"); return t("Missão inválida."); }
	$m = $e["m"][$indice];
	if (intval($m["r"]) == 1) { doquery("ROLLBACK", "users"); return t("Essa recompensa já foi resgatada."); }
	if (intval($m["p"]) < intval($m["n"])) { doquery("ROLLBACK", "users"); return t("Essa missão ainda não foi concluída."); }
	list($ryou, $exp) = diarias_premio($u["level"]);
	$e["m"][$indice]["r"] = 1;
	$set = ""; $onde = ""; $extra = ""; $orig = $u; $mexidos = array(); // espaços da mochila alterados (item + poção), gravados juntos no fim.
	// Chance pequena (3%) de um equipamento simples a intermediário, de uma lista fixa (naruto_items).
	if (mt_rand(1, 100) <= 3) {
		$sorteaveis = array(3, 4, 5, 47, 48, 49, 67, 72, 73, 75, 55, 56, 57, 78, 81, 82, 61, 62, 63, 84);
		$it = mysqli_fetch_array(doquery("SELECT id,name,type FROM {{table}} WHERE id='".intval($sorteaveis[mt_rand(0, count($sorteaveis) - 1)])."' LIMIT 1", "items"));
		if ($it) {
			$espacos = mochilaguardar($u, $it["name"].",".intval($it["id"]).",".intval($it["type"]).",".durabilidadenova($it["type"], $it["id"]));
			if ($espacos === false) { $extra .= " ".tf("Sorte! Saiu o item %s, mas sua mochila está cheia e ele ficou para trás.", t($it["name"])); }
			else { $mexidos = array_merge($mexidos, $espacos); $extra .= " <b>".tf("Sorte! Você também ganhou o item %s.", t($it["name"]))."</b>"; }
		}
	}
	if (intval($m["pocao"]) > 0) { // poção pequena (drops 431-435) vai para a mochila, se couber.
		$p = mysqli_fetch_array(doquery("SELECT name,consumivel FROM {{table}} WHERE id='".intval($m["pocao"])."' LIMIT 1", "drops"));
		if ($p) {
			$c = array_pad(explode(",", (string)$p["consumivel"]), 2, 0);
			$espacos = mochilaguardar($u, $p["name"].",".$c[0].",".intval($c[1]).",1");
			if ($espacos === false) { $extra .= " ".t("Sua mochila está cheia: a poção ficou para trás."); }
			else { $mexidos = array_merge($mexidos, $espacos); $extra .= " ".tf("Você também recebeu: %s.", t($p["name"])); }
		}
	}
	if (count($mexidos) > 0) { list($s, $onde) = sqlmochila($u, $orig, array_unique($mexidos)); if ($s != "") { $set = ",".$s; } }
	doquery("UPDATE {{table}} SET diarias='".addslashes(json_encode($e))."',gold=LEAST(gold+$ryou,16777215),experience=LEAST(experience+$exp,16777215)$set WHERE id='".intval($u["id"])."' AND diarias='".addslashes((string)$u["diarias"])."' $onde LIMIT 1", "users");
	if (mysqli_affected_rows($link) < 1) { doquery("ROLLBACK", "users"); return t("Essa recompensa já foi resgatada."); }
	doquery("COMMIT", "users");
	return tf("Missão diária concluída! Você recebeu %s Ryou e %s de experiência.", $ryou, $exp).$extra;
}}

if (!function_exists('caixadiarias')){
function caixadiarias($row){ // Caixa "Missões diárias" da coluna da direita (padrão .cxb).
	if (!array_key_exists("diarias", $row)) { return ""; } // migração ainda não aplicada.
	$e = diarias_estado($row);
	list($ryou, $exp) = diarias_premio($row["level"]);
	$h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
	$corpo = "";
	foreach ($e["m"] as $i => $m) {
		$p = min(intval($m["p"]), intval($m["n"]));
		$pct = (int)floor($p * 100 / max(1, intval($m["n"])));
		$corpo .= "<div class=\"cxb-linha diaria\">".$h(diarias_texto($m))."<br>".$h(tf("Progresso: %s/%s", $p, $m["n"]))
			."<div style=\"height:6px;background:#c9a96a;border:1px solid #452202;margin-top:2px\"><div style=\"height:6px;width:$pct%;background:#2f7a2f\"></div></div>";
		if (intval($m["r"]) == 1) { $corpo .= "<span class=\"small\" style=\"color:#2f7a2f\">".t("Resgatada")."</span>"; }
		elseif ($p >= intval($m["n"])) {
			$corpo .= "<form method=\"post\" action=\"index.php?do=diarias\" style=\"margin:3px 0 0\">".csrf_campo()."<input type=\"hidden\" name=\"missao\" value=\"$i\">"
				."<input type=\"submit\" class=\"novobotao btnjogo bj-confirmar\" value=\"".$h(t("Resgatar"))."\" title=\"".$h(tf("%s Ryou e %s de experiência", $ryou, $exp))."\"></form>";
		}
		$corpo .= "</div>";
	}
	return "<div class=\"cxb\" id=\"caixadiarias\"><div class=\"cxb-cab\">".t("Missões diárias")."</div><div class=\"cxb-corpo\">".$corpo."</div></div>";
}}

if (!function_exists('avisomochilacheia')){
function avisomochilacheia($row){ // Aviso discreto quando os 8 espaços da mochila estão ocupados.
	for ($i = 1; $i <= 8; $i++) { if (!isset($row["bp".$i]) || (string)$row["bp".$i] == "None" || (string)$row["bp".$i] == "") { return ""; } }
	return "<div id=\"avisomochila\" style=\"margin:4px 6px 2px;padding:3px 6px;background:#fff1c7;border:1px solid #452202;color:#452202;text-align:center;font-size:11px\">".t("Sua mochila está cheia (8/8).")."</div>";
}}
