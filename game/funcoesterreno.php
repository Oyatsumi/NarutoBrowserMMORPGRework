<?php // funcoesterreno.php :: Terrenos do mapa (tabela terrenos): terreno de cada casa e terrenos em que cada inimigo aparece.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

// Usado pelo mini mapa (templates/minimap.php, chão de cada casa), pela luta (fight.php, sorteio do inimigo pelo terreno
// da casa do jogador), pelo painel do administrador e pela ajuda dos inimigos.
// Cada função fica protegida por function_exists() porque o arquivo pode ser incluído por mais de uma página.

if (!function_exists('terrenosdomapa')){
function terrenosdomapa() { // Lista de terrenos (tabela terrenos), da maior para a menor prioridade. Lida uma vez por página.

	static $terrenos = null;
	if ($terrenos === null) {
		$terrenos = array();
		$query = doquery("SELECT * FROM {{table}} ORDER BY prioridade DESC, id DESC", "terrenos");
		while ($row = mysqli_fetch_array($query)) { $terrenos[] = $row; }
	}
	return $terrenos;

}}

if (!function_exists('terrenodacasa')){
function terrenodacasa($lat, $lon) { // Terreno da casa [lat,lon]: array(imagem, decoracoes 0/1, nome, prioridade); grama padrão se nenhum retângulo a contiver.

	$lat = intval($lat); $lon = intval($lon);
	// "Sorteio" fixo pela coordenada: o mesmo número sempre para a mesma casa.
	$h = (($lat * 73856093) ^ ($lon * 19349663)) & 0x7fffffff;
	$tremlat = ($h % 5) - 2;
	$tremlon = (intdiv($h, 5) % 5) - 2;
	foreach (terrenosdomapa() as $row) {
		$la = $lat + $tremlat; $lo = $lon + $tremlon;
		if ($la >= $row["latmin"] && $la <= $row["latmax"] && $lo >= $row["lonmin"] && $lo <= $row["lonmax"]) {
			$imagem = preg_replace("/[^a-z0-9_.\-]/i", "", $row["imagem"]);
			$nome = preg_replace("/[^a-z0-9_\-]/i", "", $row["nome"]);
			if ($imagem != "" && file_exists("images/".$imagem)) { return array("images/".$imagem, intval($row["arvores"]), $nome, intval($row["prioridade"])); }
		}
	}
	return array("images/background_minimapa.jpg", 1, "grama", -1);

}}

if (!function_exists('nomesdosterrenos')){
function nomesdosterrenos() { // Nomes distintos dos terrenos cadastrados (ex.: "costa" aparece uma vez mesmo tendo 3 retângulos), mais "grama" (o padrão).

	$nomes = array("grama");
	foreach (terrenosdomapa() as $row) {
		$nome = preg_replace("/[^a-z0-9_\-]/i", "", $row["nome"]);
		if ($nome != "" && !in_array($nome, $nomes, true)) { $nomes[] = $nome; }
	}
	sort($nomes);
	return $nomes;

}}

if (!function_exists('nomeexibidoterreno')){
function nomeexibidoterreno($nome) { // Nome do terreno para exibir ("pantano" -> "Pântano"), já traduzido. Terrenos novos do painel: primeira letra maiúscula.

	$conhecidos = array("grama"=>"Grama", "floresta"=>"Floresta", "trilha"=>"Trilha", "deserto"=>"Deserto", "rochoso"=>"Rochoso", "neve"=>"Neve", "costa"=>"Costa", "pantano"=>"Pântano", "vulcanico"=>"Vulcânico");
	return isset($conhecidos[$nome]) ? t($conhecidos[$nome]) : ucfirst(str_replace("_", " ", $nome));

}}

if (!function_exists('terrenosdoinimigo')){
function terrenosdoinimigo($texto) { // Coluna monsters.terrenos ("deserto,rochoso") -> array de nomes; vazia = qualquer terreno (array vazio).

	$lista = array();
	foreach (explode(",", (string)$texto) as $nome) {
		$nome = preg_replace("/[^a-z0-9_\-]/i", "", trim($nome));
		if ($nome != "" && !in_array($nome, $lista, true)) { $lista[] = $nome; }
	}
	return $lista;

}}

if (!function_exists('textoterrenosdoinimigo')){
function textoterrenosdoinimigo($texto) { // "Deserto, Rochoso" (traduzido) ou "" quando o inimigo aparece em qualquer terreno.

	$nomes = array();
	foreach (terrenosdoinimigo($texto) as $nome) { $nomes[] = nomeexibidoterreno($nome); }
	return implode(", ", $nomes);

}}
?>
