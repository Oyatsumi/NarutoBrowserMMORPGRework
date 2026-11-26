<?php // funcoeschefes.php :: Chefes semanais (tabelas chefes e chefes_abates; migração tools/chefes_semanais.sql).
// Cada chefe fica numa casa fixa do mapa. Pisou na casa e ainda não o derrotou nesta semana ISO (fuso America/Sao_Paulo):
// a luta começa com ele (explore.php). A vitória grava o abate (1 por jogador/chefe/semana) e dá o drop próprio do chefe.

if (!function_exists('chefes_semana')) {
function chefes_semana($quando = null) { // Semana ISO ("2026-W45") no fuso do jogo, independente do fuso do servidor.
	$d = new DateTime("now", new DateTimeZone("America/Sao_Paulo"));
	if ($quando !== null) { $d->setTimestamp(intval($quando)); }
	return $d->format("o-\\WW");
}}

if (!function_exists('chefes_lista')) {
function chefes_lista() { // Todos os chefes, na ordem, com os dados do inimigo e do drop.
	$lista = array();
	$q = doquery("SELECT c.*, m.name, m.level, m.elemento, d.name AS dropnome FROM {{table}} c JOIN naruto_monsters m ON m.id=c.monster_id JOIN naruto_drops d ON d.id=c.drop_id ORDER BY c.ordem, c.monster_id", "chefes");
	while ($r = mysqli_fetch_array($q)) { $lista[] = $r; }
	return $lista;
}}

if (!function_exists('chefes_derrotou')) {
function chefes_derrotou($jogador, $monsterid, $semana = null) { // O jogador já derrotou esse chefe nesta semana?
	if ($semana === null) { $semana = chefes_semana(); }
	$q = doquery("SELECT id FROM {{table}} WHERE jogador='".intval($jogador)."' AND chefe='".intval($monsterid)."' AND semana='".addslashes($semana)."' LIMIT 1", "chefes_abates");
	return mysqli_num_rows($q) > 0;
}}

if (!function_exists('chefes_pendente')) {
function chefes_pendente($jogador, $lat, $lon) { // Linha do chefe da casa (lat,lon) se ele ainda não foi derrotado pelo jogador nesta semana, senão false.
	$q = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($lat)."' AND longitude='".intval($lon)."' LIMIT 1", "chefes");
	if (!$q || mysqli_num_rows($q) == 0) { return false; }
	$c = mysqli_fetch_array($q);
	return chefes_derrotou($jogador, $c["monster_id"]) ? false : $c;
}}

if (!function_exists('chefes_registrarabate')) {
function chefes_registrarabate($jogador, $monsterid) { // Grava o abate (chamar dentro da transação da vitória). Devolve a linha do drop do chefe ou false (não é chefe / já derrotado nesta semana).
	global $link;
	$q = doquery("SELECT drop_id FROM {{table}} WHERE monster_id='".intval($monsterid)."' LIMIT 1", "chefes");
	if (!$q || mysqli_num_rows($q) == 0) { return false; }
	$c = mysqli_fetch_array($q);
	// A chave única (jogador, chefe, semana) garante 1 abate por semana mesmo com cliques em paralelo.
	doquery("INSERT IGNORE INTO {{table}} SET jogador='".intval($jogador)."', chefe='".intval($monsterid)."', semana='".addslashes(chefes_semana())."', data=NOW()", "chefes_abates");
	if (mysqli_affected_rows($link) != 1) { return false; }
	$droprow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($c["drop_id"])."' LIMIT 1", "drops"));
	if (!$droprow) { return false; }
	$droprow["variante"] = "";
	return $droprow;
}}
?>
