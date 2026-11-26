<?php // funcoesneblina.php :: Neblina de guerra do mapa-múndi: quais dos 20x20 quadrantes cada jogador já descobriu.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

// Formato do campo users.mapadescoberto: 100 caracteres hexadecimais = 400 bits, um por quadrante do mapa global
// (os mesmos 20x20 quadrantes do popup do index.php?do=showmap). O quadrante k = linha*20 + coluna (linha 0 = norte,
// coluna 0 = oeste) fica no caractere floor(k/4), bit 8>>(k%4). Campo vazio ou curto = nada descoberto (completa com '0').
// A neblina pode ser desligada para todos no painel do administrador (control.neblinaativa = 0).
// Usado por explore.php (cada passo), index.php (mapa global e perfil) e towns.php/help (indiretamente).

if (!function_exists('neblina_limites')){
function neblina_limites() { // Limites dos 20 quadrantes: array(linhas => [[topo,base],...], colunas => [[esq,dir],...]). Mesma conta do showmap().

	global $controlrow;
	static $limites = null;
	if ($limites !== null) { return $limites; }
	$tamanhomapa = intval($controlrow["gamesize"]);
	$lado = floor($tamanhomapa*2/20);
	$sobra = $tamanhomapa*2 - ($lado*20);
	$linhas = array(); $colunas = array();
	$sobrai = $sobra; $addfim = 0;
	for ($i = 0; $i <= 19; $i++) {
		$add = ($sobrai > 0) ? 1 : 0; if ($sobrai > 0) { $sobrai--; }
		$aux = $tamanhomapa - ($lado * $i) - $addfim;
		$linhas[] = array(($i != 0) ? $aux - 1 : $aux, $aux - $lado - $add);
		$aux = -$tamanhomapa + ($lado * $i) + $addfim;
		$colunas[] = array(($i != 0) ? $aux + 1 : $aux, $aux + $lado + $add);
		$addfim += $add;
	}
	$limites = array("linhas" => $linhas, "colunas" => $colunas);
	return $limites;

}}

if (!function_exists('neblina_quadrante')){
function neblina_quadrante($lat, $long) { // Número (0..399) do quadrante que contém a casa [lat,long]; fora do mapa vai para a borda mais próxima.

	$limites = neblina_limites();
	$lat = intval($lat); $long = intval($long);
	$linha = 19; $coluna = 19;
	foreach ($limites["linhas"] as $i => $l) { if ($lat >= $l[1]) { $linha = $i; break; } }
	foreach ($limites["colunas"] as $j => $c) { if ($long <= $c[1]) { $coluna = $j; break; } }
	return $linha*20 + $coluna;

}}

if (!function_exists('neblina_temquadrante')){
function neblina_temquadrante($mapa, $k) { // true se o bit do quadrante $k está ligado no texto hexadecimal $mapa.

	$k = intval($k);
	if ($k < 0 || $k > 399) { return false; }
	$c = substr((string)$mapa, intdiv($k, 4), 1);
	if ($c === "" || $c === false) { return false; }
	return (hexdec($c) & (8 >> ($k % 4))) != 0;

}}

if (!function_exists('neblina_ligar')){
function neblina_ligar($mapa, $k) { // Devolve o texto $mapa com o bit do quadrante $k ligado (usado na migração e no mapa).

	$mapa = str_pad(substr((string)$mapa, 0, 100), 100, "0");
	$p = intdiv($k, 4);
	$mapa[$p] = dechex(hexdec($mapa[$p]) | (8 >> ($k % 4)));
	return $mapa;

}}

if (!function_exists('neblina_descobrir')){
function neblina_descobrir(&$row, $lat, $long) { // Marca o quadrante de [lat,long] como descoberto pelo jogador $row (só faz UPDATE se for novo).

	if (!isset($row["mapadescoberto"])) { return; }
	$k = neblina_quadrante($lat, $long);
	if (neblina_temquadrante($row["mapadescoberto"], $k)) { return; } // caso comum de cada passo: nenhuma query.
	// Liga o bit direto no SQL (atômico: dois passos simultâneos não apagam a descoberta um do outro).
	$p = intdiv($k, 4) + 1; $bit = 8 >> ($k % 4);
	$col = "RPAD(mapadescoberto,100,'0')";
	doquery("UPDATE {{table}} SET mapadescoberto=CONCAT(LEFT($col,".($p-1)."), LOWER(HEX(CONV(SUBSTRING($col,$p,1),16,10) | $bit)), SUBSTRING($col,".($p+1).")) WHERE id='".intval($row["id"])."' LIMIT 1", "users");
	$row["mapadescoberto"] = neblina_ligar($row["mapadescoberto"], $k);

}}

if (!function_exists('neblina_percentual')){
function neblina_percentual($mapa) { // Porcentagem dos 400 quadrantes já descobertos, já formatada ("12,5" em pt / "12.5" em en).

	$total = 0;
	$mapa = substr((string)$mapa, 0, 100);
	for ($p = 0; $p < strlen($mapa); $p++) { $total += substr_count(decbin(hexdec($mapa[$p])), "1"); }
	$valor = floor($total * 1000 / 400) / 10; // 1 casa decimal, para baixo (100% só com tudo descoberto).
	return number_format($valor, ($valor == floor($valor)) ? 0 : 1, (idioma() == "en") ? "." : ",", "");

}}

if (!function_exists('neblina_mapadovisitante')){
function neblina_mapadovisitante($row) { // Mapa descoberto "efetivo": o gravado + o quadrante atual + os quadrantes das vilas que ele conhece (users.towns).

	$mapa = neblina_ligar(isset($row["mapadescoberto"]) ? $row["mapadescoberto"] : "", neblina_quadrante($row["latitude"], $row["longitude"]));
	$conhecidas = array_map('intval', explode(",", (string)$row["towns"]));
	$query = doquery("SELECT id,latitude,longitude FROM {{table}}", "towns");
	while ($town = mysqli_fetch_array($query)) {
		if (in_array(intval($town["id"]), $conhecidas, true)) { $mapa = neblina_ligar($mapa, neblina_quadrante($town["latitude"], $town["longitude"])); }
	}
	return $mapa;

}}
