<?php // mapaneblina.php :: Imagem do Mapa Global já com a neblina de guerra (index.php?do=showmap).
// Antes o popup mandava o mapa inteiro (images/mapa_grande.png) e só depois as células de neblina carregavam por cima:
// por um instante o jogador via o mapa todo. Aqui a imagem é montada no servidor (GD) com os quadrantes inexplorados
// já cobertos pela textura images/neblina_mapa.png, então o mapa completo nunca chega ao navegador de quem tem neblina.
// Cache em disco por "mapa descoberto" (o mesmo desenho serve para todos os jogadores com os mesmos quadrantes).

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();
if ($userrow == false) { http_response_code(403); die(); }
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control")); // gamesize e neblinaativa (funcoesneblina.php).
include_once('funcoesneblina.php');

$mapa = neblina_mapadovisitante($userrow);
$equipe = ($userrow["authlevel"] == 1 || ehgm($userrow));
$semneblina = intval($controlrow["neblinaativa"]) != 1 || ($equipe && pegarint($_GET, "neblina", 0) != 1);

$pasta = sys_get_temp_dir()."/naruto_mapaneblina";
if (!is_dir($pasta)) { @mkdir($pasta, 0775, true); }
$chave = $semneblina ? "todo" : md5((string)$mapa);
$arquivo = $pasta."/".$chave.".png";

if (!is_file($arquivo)) {
	$fundo = imagecreatefrompng("images/mapa_grande.png");
	if (!$semneblina) {
		$larg = imagesx($fundo); $alt = imagesy($fundo);
		// Textura da neblina esticada para o tamanho do mapa (contínua entre quadrantes, como antes).
		$nevoa = imagecreatefrompng("images/neblina_mapa.png");
		$nevoagrande = imagecreatetruecolor($larg, $alt);
		imagecopyresampled($nevoagrande, $nevoa, 0, 0, 0, 0, $larg, $alt, imagesx($nevoa), imagesy($nevoa));
		for ($k = 0; $k < 400; $k++) {
			if (neblina_temquadrante($mapa, $k)) { continue; }
			$i = intdiv($k, 20); $j = $k % 20;
			$x0 = (int)floor($j * $larg / 20); $x1 = (int)floor(($j + 1) * $larg / 20);
			$y0 = (int)floor($i * $alt / 20); $y1 = (int)floor(($i + 1) * $alt / 20);
			imagecopy($fundo, $nevoagrande, $x0, $y0, $x0, $y0, $x1 - $x0, $y1 - $y0);
		}
		imagedestroy($nevoa); imagedestroy($nevoagrande);
	}
	$temp = $arquivo.".".getmypid().".tmp"; // gravação atômica (rename).
	imagepng($fundo, $temp, 6);
	imagedestroy($fundo);
	@rename($temp, $arquivo);
	if (!is_file($arquivo)) { $arquivo = $temp; }
}

header("Content-Type: image/png");
header("Cache-Control: private, max-age=86400"); // a URL leva a chave (?v=), então muda quando o mapa descoberto muda.
readfile($arquivo);
