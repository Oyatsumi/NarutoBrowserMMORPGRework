<?php // funcoesdoujutsu.php :: Doujutsu (técnicas oculares): Olho do Falcão, Sharingan (4 estágios), Byakugan, Olho Celeste e Rinnegan.
//
// Regras (docs/DOUJUTSU.md; migração tools/doujutsu.sql; páginas doujutsu.php e admin_doujutsu.php):
// - Cada jogador tem no máximo UM olho (users.doujutsu = chave, doujutsu_estagio = estágio). O olho é despertado num
//   treino longo (naruto_treinos, chaves olho_*; treinoslongos.php): ao concluir, o olho novo SUBSTITUI o atual e o
//   treino sai da lista para poder ser refeito depois (trocar de olho de novo).
// - Ativar/desativar pelo ícone da coluna da direita (doujutsu.php?do=ativar / do=desativar). Aberto, gasta
//   naruto_doujutsu.custo_ch de Chakra a cada `intervalo` segundos, mesmo sem atualizar a página (doujutsu_timer =
//   momento até onde já foi cobrado); fecha sozinho quando o Chakra acaba. O olho e o Senjutsu não ficam ativos ao
//   mesmo tempo (as duas ativações conferem a outra na própria UPDATE).
// - Os bônus são calculados na hora (doujutsumods()) e somados nos modificadores do combate (classedojogador(),
//   funcoesjutsus.php) e nos pontos onde fight.php e users.php (duelo/PvP) leem agilidade, inteligência, sorte,
//   precisão, esquiva e chance de drop. Nunca são gravados nos atributos.
// - Sharingan: estágio 1 ao despertar; sobe por (1) abates de inimigos fortes com o olho ativo (control.dj_abates,
//   dj_abatemargem), (2) vitórias em duelos desde o estágio atual (control.dj_vitorias) ou (3) o treino "Evolução do
//   Sharingan" (sharingan_evo). Rinnegan: teleporte para qualquer coordenada (doujutsu.php?do=teleporte).

include_once(__DIR__.'/funcoesinclusas.php');

if (!function_exists('doujutsulista')) {
function doujutsulista() { // Todas as linhas de naruto_doujutsu ("chave:estagio" => linha), lidas uma vez por página.
	static $lista = null;
	if ($lista === null) {
		$lista = array();
		$q = @doquery("SELECT * FROM {{table}} ORDER BY ordem, estagio", "doujutsu");
		if ($q) { while ($r = mysqli_fetch_array($q, MYSQLI_ASSOC)) { $lista[$r["chave"].":".intval($r["estagio"])] = $r; } }
	}
	return $lista;
}}

if (!function_exists('doujutsuolho')) {
function doujutsuolho($chave, $estagio = 1) { // Linha do olho/estágio, ou false.
	$l = doujutsulista();
	$k = (string)$chave.":".max(1, intval($estagio));
	return isset($l[$k]) ? $l[$k] : false;
}}

if (!function_exists('doujutsudojogador')) {
function doujutsudojogador($row) { // Linha do olho que o jogador tem agora, ou false.
	if (!is_array($row) || !isset($row["doujutsu"]) || (string)$row["doujutsu"] == "") { return false; }
	return doujutsuolho($row["doujutsu"], $row["doujutsu_estagio"]);
}}

if (!function_exists('doujutsuestado')) {
function doujutsuestado($row) { // array("ativo", "mp" já descontado, "cobrado", "ciclos", "olho") sem gravar nada.
	$r = array("ativo" => false, "mp" => max(0, intval(isset($row["currentmp"]) ? $row["currentmp"] : 0)), "cobrado" => 0, "ciclos" => 0, "olho" => doujutsudojogador($row));
	if (!$r["olho"] || intval($row["doujutsu_ativo"]) != 1) { return $r; }
	$passo = max(1, intval($r["olho"]["intervalo"])); $custo = doujutsucustonivel($r["olho"], isset($row["level"]) ? $row["level"] : 1);
	$seg = max(0, time() - intval($row["doujutsu_timer"]));
	$r["ciclos"] = intdiv($seg, $passo);
	$r["cobrado"] = min($r["mp"], $r["ciclos"] * $custo);
	$r["mp"] -= $r["cobrado"];
	$r["ativo"] = ($r["mp"] > 0);
	return $r;
}}

if (!function_exists('doujutsuativo')) {
function doujutsuativo($row) { $e = doujutsuestado($row); return $e["ativo"]; } // O olho está valendo agora?
}

if (!function_exists('doujutsucampos')) {
function doujutsucampos() { // Colunas de bônus de naruto_doujutsu.
	return array("agilidade", "inteligencia", "sorte", "precisao", "esquiva", "critico", "ataque", "defesa", "ignora_defesa", "droprate", "ver_inimigo", "teleporte", "reflete", "ver_mapa");
}}

if (!function_exists('doujutsumods')) {
function doujutsumods($row) { // Bônus do olho ativo agora (zeros se não houver olho ou se estiver fechado/sem Chakra).
	$m = array();
	foreach (doujutsucampos() as $c) { $m[$c] = 0; }
	$e = doujutsuestado($row);
	if (!$e["ativo"]) { return $m; }
	foreach (doujutsucampos() as $c) { $m[$c] = max(0, intval(isset($e["olho"][$c]) ? $e["olho"][$c] : 0)); }
	return $m;
}}

if (!function_exists('doujutsunome')) {
function doujutsunome($olho) { return $olho ? t($olho["nome"]) : ""; } // Nome traduzido.
}

if (!function_exists('doujutsubonustexto')) {
function doujutsubonustexto($olho) { // Lista (traduzida) dos bônus de um olho.
	if (!$olho) { return array(); }
	$rot = array("agilidade" => "+%s de Agilidade", "inteligencia" => "+%s de Inteligência", "sorte" => "+%s de Sorte", "precisao" => "+%s de Precisão",
		"esquiva" => "+%s%% de Esquiva", "critico" => "+%s%% de Chance de Crítico", "ataque" => "+%s%% de Poder de Ataque", "defesa" => "+%s%% de Poder de Defesa",
		"ignora_defesa" => "ignora %s%% da defesa do alvo", "droprate" => "+%s de Chance de Drop", "reflete" => "%s%% de chance de refletir o ataque do inimigo");
	$l = array();
	foreach ($rot as $c => $txt) { if (intval(isset($olho[$c]) ? $olho[$c] : 0) > 0) { $l[] = tf($txt, intval($olho[$c])); } }
	if (intval($olho["ver_inimigo"]) == 1) { $l[] = t("vê os itens que o inimigo pode dropar"); }
	if (intval(isset($olho["ver_mapa"]) ? $olho["ver_mapa"] : 0) == 1) { $l[] = t("vê o mapa geral inteiro, sem neblina"); }
	if (intval($olho["teleporte"]) == 1) { $l[] = t("teleporte para qualquer coordenada"); }
	if (intval($olho["teleporte"]) == 1) { $l[] = t("fugir de uma luta sempre funciona, sem perder nada"); }
	return $l;
}}

if (!function_exists('doujutsucustonivel')) {
function doujutsucustonivel($olho, $nivel) { // Chakra por intervalo: custo_ch da tabela cresce com o level (x1 no level 1, x2 no 50, x3 no 100).
	return max(0, (int)round(intval($olho["custo_ch"]) * (1 + (min(100, max(1, intval($nivel))) - 1) / 49.5)));
}}

if (!function_exists('doujutsucustotexto')) {
function doujutsucustotexto($olho, $nivel = 1) { return $olho ? tf("%s CH a cada %s s", doujutsucustonivel($olho, $nivel), intval($olho["intervalo"])) : ""; }
}

if (!function_exists('duracaotexto')) {
function duracaotexto($seg) { // "45 s", "12 min", "2 h 05 min".
	$seg = max(0, intval($seg));
	if ($seg < 60) { return tf("%s s", $seg); }
	if ($seg < 3600) { return tf("%s min", intdiv($seg, 60)); }
	return tf("%s h %s min", intdiv($seg, 3600), str_pad((string)intdiv($seg % 3600, 60), 2, "0", STR_PAD_LEFT));
}}

if (!function_exists('doujutsuduracao')) {
function doujutsuduracao($olho, $ch, $nivel = 1) { // Segundos que o olho fica aberto com $ch de Chakra (fecha quando o Chakra acaba).
	$c = $olho ? doujutsucustonivel($olho, $nivel) : 0;
	if ($c <= 0) { return 0; }
	return (int)ceil(max(0, intval($ch)) / $c) * max(1, intval($olho["intervalo"]));
}}

if (!function_exists('doujutsucobrar')) {
function doujutsucobrar() { // Cobra o Chakra do olho aberto (global $userrow) e fecha o olho quando o Chakra acaba. Atômico.
	global $userrow, $link;
	if (!is_array($userrow) || intval(isset($userrow["doujutsu_ativo"]) ? $userrow["doujutsu_ativo"] : 0) != 1) { return; }
	$e = doujutsuestado($userrow);
	if (!$e["olho"]) { // olho apagado pelo admin: só fecha.
		doquery("UPDATE {{table}} SET doujutsu_ativo='0' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
		$userrow["doujutsu_ativo"] = 0; return;
	}
	if ($e["ativo"] && $e["ciclos"] <= 0) { return; }
	$timer = intval($userrow["doujutsu_timer"]);
	$novotimer = $timer + $e["ciclos"] * max(1, intval($e["olho"]["intervalo"]));
	$ativo = $e["ativo"] ? 1 : 0;
	// "AND doujutsu_timer=<lido>": duas páginas ao mesmo tempo não cobram o mesmo intervalo duas vezes.
	doquery("UPDATE {{table}} SET currentmp=GREATEST(0, currentmp-".intval($e["cobrado"])."), doujutsu_timer='".($ativo ? $novotimer : 0)."', doujutsu_ativo='$ativo' WHERE id='".intval($userrow["id"])."' AND doujutsu_ativo='1' AND doujutsu_timer='$timer' LIMIT 1", "users");
	if (mysqli_affected_rows($link) == 1) {
		$userrow["currentmp"] = $e["mp"]; $userrow["doujutsu_ativo"] = $ativo; $userrow["doujutsu_timer"] = $ativo ? $novotimer : 0;
	} else {
		$r = mysqli_fetch_array(doquery("SELECT currentmp,doujutsu_ativo,doujutsu_timer FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1", "users"));
		if ($r) { $userrow["currentmp"] = $r["currentmp"]; $userrow["doujutsu_ativo"] = $r["doujutsu_ativo"]; $userrow["doujutsu_timer"] = $r["doujutsu_timer"]; }
	}
}}

/* ---------------------------------------------------------------- evolução do Sharingan */

if (!function_exists('doujutsulimites')) {
function doujutsulimites($texto) { // "40,120,300" -> array(2 => 40, 3 => 120, 4 => 300) (0 = caminho desligado para o estágio).
	$p = array_pad(explode(",", (string)$texto), 3, "0");
	return array(2 => max(0, intval($p[0])), 3 => max(0, intval($p[1])), 4 => max(0, intval($p[2])));
}}

if (!function_exists('doujutsucontrole')) {
function doujutsucontrole() { // Linha control (global, ou lida agora).
	global $controlrow;
	if (is_array($controlrow) && isset($controlrow["dj_abates"])) { return $controlrow; }
	return mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
}}

if (!function_exists('doujutsuprogresso')) {
function doujutsuprogresso($row) { // Progresso do Sharingan para o próximo estágio: array("abates"=>[feito, meta], "vitorias"=>[feito, meta]) ou false.
	if (!is_array($row) || (string)$row["doujutsu"] != "sharingan" || intval($row["doujutsu_estagio"]) >= 4) { return false; }
	$c = doujutsucontrole(); $prox = intval($row["doujutsu_estagio"]) + 1;
	$ab = doujutsulimites($c["dj_abates"]); $vi = doujutsulimites($c["dj_vitorias"]);
	return array("proximo" => $prox,
		"abates" => array(intval($row["doujutsu_abates"]), $ab[$prox]),
		"vitorias" => array(max(0, intval($row["duelos_vencidos"]) - intval($row["doujutsu_vitbase"])), $vi[$prox]));
}}

if (!function_exists('doujutsuevoluir')) {
function doujutsuevoluir($id) { // Sobe o Sharingan um estágio se algum caminho (abates/vitórias) foi cumprido. Devolve o novo estágio ou 0.
	global $link;
	$row = mysqli_fetch_array(doquery("SELECT id,doujutsu,doujutsu_estagio,doujutsu_abates,doujutsu_vitbase,duelos_vencidos FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "users"));
	$p = doujutsuprogresso($row);
	if (!$p) { return 0; }
	$ok = ($p["abates"][1] > 0 && $p["abates"][0] >= $p["abates"][1]) || ($p["vitorias"][1] > 0 && $p["vitorias"][0] >= $p["vitorias"][1]);
	if (!$ok) { return 0; }
	$atual = intval($row["doujutsu_estagio"]);
	// Condicionada ao estágio lido: duas páginas ao mesmo tempo sobem um estágio só.
	doquery("UPDATE {{table}} SET doujutsu_estagio='".($atual + 1)."', doujutsu_abates='0', doujutsu_vitbase=duelos_vencidos WHERE id='".intval($id)."' AND doujutsu='sharingan' AND doujutsu_estagio='$atual' LIMIT 1", "users");
	return (mysqli_affected_rows($link) == 1) ? $atual + 1 : 0;
}}

if (!function_exists('doujutsuabate')) {
function doujutsuabate($row, $monsterrow) { // Vitória contra inimigo: conta o abate (Sharingan ativo e inimigo forte) e tenta evoluir. Devolve texto ou "".
	if ((string)$row["doujutsu"] != "sharingan" || intval($row["doujutsu_estagio"]) >= 4 || !doujutsuativo($row)) { return ""; }
	$c = doujutsucontrole();
	if (intval($monsterrow["level"]) < intval($row["level"]) - intval($c["dj_abatemargem"])) { return ""; }
	doquery("UPDATE {{table}} SET doujutsu_abates=LEAST(65535, doujutsu_abates+1) WHERE id='".intval($row["id"])."' AND doujutsu='sharingan' LIMIT 1", "users");
	$novo = doujutsuevoluir($row["id"]);
	return ($novo > 0) ? tf("Seu Sharingan evoluiu para o estágio %s!", $novo) : "";
}}

/* ---------------------------------------------------------------- teleporte do Rinnegan */

if (!function_exists('doujutsucustoteleporte')) {
function doujutsucustoteleporte($row, $lat, $lon) { // array("dist", "tp", "ch", "hp") para ir de onde o jogador está até (lat, lon).
	$c = doujutsucontrole();
	$dist = max(abs(intval($lat) - intval($row["latitude"])), abs(intval($lon) - intval($row["longitude"])));
	$dez = (int)ceil($dist / 10);
	$hppct = intval($c["dj_tele_hpbase"]) + $dez * intval($c["dj_tele_hp10"]);
	return array("dist" => $dist,
		"tp" => intval($c["dj_tele_tpbase"]) + $dez * intval($c["dj_tele_tp10"]),
		"ch" => intval($c["dj_tele_chbase"]) + $dez * intval($c["dj_tele_ch10"]),
		"hp" => (int)ceil(intval($row["maxhp"]) * min(95, $hppct) / 100));
}}

?>
