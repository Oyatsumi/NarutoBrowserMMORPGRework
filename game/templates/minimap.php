<?php
// minimap.php :: Mini mapa 5x5 ao redor do jogador (árvores, cidades, NPCs e outros jogadores).
// As árvores são "sorteadas" de forma determinística por quadrante de 5x5, assim o mapa é sempre o mesmo.
// O chão de cada casa vem da tabela terrenos (grama, floresta, deserto, neve...): retângulos de coordenadas com
// prioridade; a casa usa o terreno de maior prioridade que a contém. Perto da borda de um retângulo a coordenada é
// "tremida" de -2 a +2 casas (também de forma determinística), para a fronteira entre terrenos não ficar reta.
// Cada casa usa ladrilhos 30x30 de images/terreno/ (chão sorteado entre variantes, bordas/cantos do terreno vizinho de
// maior prioridade e uma decoração do bioma), gerados por tools/gerar_tiles_terreno.py a partir de images/terreno_NOME.jpg.
global $userrow, $opcoesnovas;
global $arvore;
$matriz = array();
$opcoesnovasjogador = $opcoesnovas;
			//novo quadrante arvores
			$arvore[1]["i"] = 5;
			$arvore[1]["j"] = 2;
			$arvore[2]["i"] = 3;
			$arvore[2]["j"] = 4;
			$arvore[3]["i"] = 1;
			$arvore[3]["j"] = 3;
			$arvore[4]["i"] = 3;
			$arvore[4]["j"] = 1;
			$arvore[5]["i"] = 4;
			$arvore[5]["j"] = 5;
			$arvore[6]["i"] = 2;
			$arvore[6]["j"] = 5;

if (!function_exists('resetar')){
function resetar(){
	global $arvore;
			$arvore[1]["i"] = 5;
			$arvore[1]["j"] = 2;
			$arvore[2]["i"] = 3;
			$arvore[2]["j"] = 4;
			$arvore[3]["i"] = 1;
			$arvore[3]["j"] = 3;
			$arvore[4]["i"] = 3;
			$arvore[4]["j"] = 1;
			$arvore[5]["i"] = 4;
			$arvore[5]["j"] = 5;
			$arvore[6]["i"] = 2;
			$arvore[6]["j"] = 5;
}
			
function arvore(){
	global $arvore;
		
				$arvore[1]["i"] += 1; if ($arvore[1]["i"] > 5){$arvore[1]["i"] = 1;}
				$arvore[1]["j"] += 1; if ($arvore[1]["j"] > 5){$arvore[1]["j"] = 1;}
				$arvore[2]["i"] += 1; if ($arvore[2]["i"] > 5){$arvore[2]["i"] = 1;}
				$arvore[2]["j"] += 1; if ($arvore[2]["j"] > 5){$arvore[2]["j"] = 1;}
				$arvore[3]["i"] += 1; if ($arvore[3]["i"] > 5){$arvore[3]["i"] = 1;}
				$arvore[3]["j"] += 1; if ($arvore[3]["j"] > 5){$arvore[3]["j"] = 1;}	
				$arvore[4]["i"] += 1; if ($arvore[4]["i"] > 5){$arvore[4]["i"] = 1;}
				$arvore[4]["j"] += 1; if ($arvore[4]["j"] > 5){$arvore[4]["j"] = 1;}
				$arvore[5]["i"] += 1; if ($arvore[5]["i"] > 5){$arvore[5]["i"] = 1;}
				$arvore[5]["j"] += 1; if ($arvore[5]["j"] > 5){$arvore[5]["j"] = 1;}
				$arvore[6]["i"] += 1; if ($arvore[6]["i"] > 5){$arvore[6]["i"] = 1;}
				$arvore[6]["j"] += 1; if ($arvore[6]["j"] > 5){$arvore[6]["j"] = 1;}
}}
			// Semente fixa do sorteio das árvores: era o gamesize (300). Com o mapa de 400 casas as árvores mudariam de
			// lugar no mapa todo, então o cálculo continua usando 300 (só muda a posição das árvores, não o tamanho do mapa).
			$tamanhomapa = 300;
			
			for ($g = -10; $g <=10; $g += 5){
				for ($t = -10; $t <=10; $t += 5){
					resetar();
					$latitude = $userrow["latitude"] + $g;
					$longitude =  $userrow["longitude"] + $t;
					$proxquadi= $latitude % 5; $proxquadi = $latitude -($proxquadi - 1);
					$proxquadj = $longitude % 5; $proxquadj = $longitude -($proxquadj - 1);
					$quantosquadrantespassaram = (($tamanhomapa*2/5) * (($proxquadi - 1)/5)) + (($proxquadj - 1)/5) + ($tamanhomapa*2/5);
					$divisao = $quantosquadrantespassaram % 5;
					for ($u = 1; $u <= $divisao; $u++){
						
						arvore();
						
					}
					$matriz[($proxquadi - 1) + $arvore[1]["i"]][($proxquadj - 1) + $arvore[1]["j"]]["imagemtd"] = "images/24/arvore_random".$arvore[1]["i"].".gif";
					$matriz[($proxquadi - 1) + $arvore[2]["i"]][($proxquadj - 1) + $arvore[2]["j"]]["imagemtd"] = "images/24/arvore_random".$arvore[2]["i"].".gif";
					$matriz[($proxquadi - 1) + $arvore[3]["i"]][($proxquadj - 1) + $arvore[3]["j"]]["imagemtd"] = "images/24/arvore_random".$arvore[3]["i"].".gif";
					$matriz[($proxquadi - 1) + $arvore[4]["i"]][($proxquadj - 1) + $arvore[4]["j"]]["imagemtd"] = "images/24/arvore_random".$arvore[4]["i"].".gif";
					$matriz[($proxquadi - 1) + $arvore[5]["i"]][($proxquadj - 1) + $arvore[5]["j"]]["imagemtd"] = "images/24/arvore_random".$arvore[5]["i"].".gif";
					$matriz[($proxquadi - 1) + $arvore[6]["i"]][($proxquadj - 1) + $arvore[6]["j"]]["imagemtd"] = "images/24/arvore_random".$arvore[6]["i"].".gif";
				}
			}
			//fim quadrante arvores



// Vilas e jogadores online das 25 casas lidos de uma vez (antes eram 3 queries por casa: 75 por página).
// A ordem (vila de menor id; jogadores por nível decrescente) é a mesma que as queries por casa davam.
$minilat = intval($userrow["latitude"]); $minilon = intval($userrow["longitude"]);
$vilasmini = array(); $jogadoresmini = array();
$miniq = doquery("SELECT id, name, latitude, longitude FROM {{table}} WHERE latitude BETWEEN '".($minilat - 2)."' AND '".($minilat + 2)."' AND longitude BETWEEN '".($minilon - 2)."' AND '".($minilon + 2)."' ORDER BY id", "towns");
while ($minirow = mysqli_fetch_array($miniq)) {
	$chave = intval($minirow["latitude"]).",".intval($minirow["longitude"]);
	if (!isset($vilasmini[$chave])) { $vilasmini[$chave] = $minirow; }
}
$miniq = doquery("SELECT latitude, longitude, charname, avatar, authlevel, acesso, caveira, caveiraate FROM {{table}} WHERE latitude BETWEEN '".($minilat - 2)."' AND '".($minilat + 2)."' AND longitude BETWEEN '".($minilon - 2)."' AND '".($minilon + 2)."' AND UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."' ORDER BY level DESC", "users");
while ($minirow = mysqli_fetch_array($miniq)) { $jogadoresmini[intval($minirow["latitude"]).",".intval($minirow["longitude"])][] = $minirow; }

for ($i = ($userrow["latitude"] + 2); $i >= ($userrow["latitude"] - 2); $i--){
	for ($j = ($userrow["longitude"] - 2); $j <= ($userrow["longitude"] + 2); $j++){
			//coordenadas
			if ($j > 0) {$jcoord = $j."E";}
			if ($i > 0) {$icoord = $i."N";}
			if ($j < 0) {$jcoord = $j * -1; $jcoord .= "W";}
			if ($i < 0) {$icoord = $i * -1; $icoord .= "S";}
			if ($i == 0) {$icoord = $i."N";}
			if ($j == 0) {$jcoord = $j."E";}
			//fim coordenadas
			
			//adicionando personagens
			if (!isset($matriz[$i][$j]["imagemtd"])) { $matriz[$i][$j]["imagemtd"] = ""; }
			$matriz[$i][$j]["imagem"] = isset($matriz[$i][$j]["imagem"]) ? $matriz[$i][$j]["imagem"] : "";
			$matriz[$i][$j]["title2"] = "";
			$true = false;
			include('localpersonagens.php');
			
			$numjogadores = 0;
			if (!isset($vilasmini[$i.",".$j])) {
				if ($matriz[$i][$j]["imagem"] == ""){
					$matriz[$i][$j]["imagem"] = "images/24/gif24.gif";
				}
				$matriz[$i][$j]["title"] = "";
			}else{
				$townrow = $vilasmini[$i.",".$j];
				$matriz[$i][$j]["imagem"] = file_exists("images/24/cidade_".intval($townrow["id"]).".gif") ? "images/24/cidade_".intval($townrow["id"]).".gif" : "images/24/cidade_1.gif"; // Vila nova sem ícone usa o da vila 1.
				$matriz[$i][$j]["title"] = "[".$icoord.",".$jcoord."]".t($townrow["name"]);
			}
			
			//jogadores no mesmo mapa:
			$jogadoresdacasa = isset($jogadoresmini[$i.",".$j]) ? $jogadoresmini[$i.",".$j] : array();
			$numjogadores = count($jogadoresdacasa);

			foreach ($jogadoresdacasa as $usuariorow) {
					$true = false;
					//variavel auxiliar pro icone das arvores
					$varaux = explode("_",$matriz[$i][$j]["imagem"]);
					if ((($matriz[$i][$j]["imagem"] == "images/24/gif24.gif") || ($varaux[0] == "images/24/arvore")) && ($numjogadores > 1)){
							$matriz[$i][$j]["imagem"] = "images/24/acampamento.gif";
							$matriz[$i][$j]["title"] = t("Acampamento");			
							$matriz[$i][$j]["imagemtd"] = "";		
					}elseif (($numjogadores == 1) && (($matriz[$i][$j]["imagem"] == "images/24/gif24.gif") || ($varaux[0] == "images/24/arvore"))){
					// GameMaster (acesso 3): ícone próprio com aura dourada (layoutnovo/graficos/gm_minimap.gif).
					$matriz[$i][$j]["imagem"] = ehgm($usuariorow) ? "layoutnovo/graficos/gm_minimap.gif" : "layoutnovo/graficos/".$usuariorow["avatar"]."_minimap.gif";
					$matriz[$i][$j]["title"] = "[".$icoord.",".$jcoord."]".$usuariorow["charname"].(ehgm($usuariorow) ? " (GM)" : "");
					$matriz[$i][$j]["caveira"] = caveiraativa($usuariorow); // caveira do PvP desenhada por cima do boneco.
					$true = true;
					}
			 }
			//var aux para cidade
			$cidadeaux = explode("_", $matriz[$i][$j]["imagem"]);
			if ($matriz[$i][$j]["title"] == ""){$matriz[$i][$j]["title"] .= "[".$icoord.",".$jcoord."]".$matriz[$i][$j]["title2"];}elseif ((($true != true) && ($numjogadores != 1)) || ($cidadeaux[0] == "images/24/cidade")) {
				$matriz[$i][$j]["title"] .= " - ".tf("%s Jogador(es) no Mapa.", $numjogadores);}
				
			
		
	}//fim for
}//fim for
//fim contas e acertamento dos vetores.







// localpersonagens.php também mexe em $opcoesnovas; devolvemos o valor calculado para a posição do jogador.
$opcoesnovas = $opcoesnovasjogador;


// terrenosdomapa() e terrenodacasa() ficam em funcoesterreno.php (também usadas pela luta para sortear o inimigo pelo terreno).
include_once('funcoesterreno.php');

if (!function_exists('ladrilhosdoterreno')){
function ladrilhosdoterreno($nome) { // Ladrilhos gerados por tools/gerar_tiles_terreno.py: array(chãos, decorações, detalhes), vazio se não existirem.

	static $cache = array();
	if (!isset($cache[$nome])) {
		$chaos = array(); $decos = array();
		for ($v = 0; $v < 8 && file_exists("images/terreno/".$nome."_".$v.".png"); $v++) { $chaos[] = "images/terreno/".$nome."_".$v.".png"; }
		for ($v = 0; $v < 12 && file_exists("images/terreno/deco_".$nome."_".$v.".png"); $v++) { $decos[] = "images/terreno/deco_".$nome."_".$v.".png"; }
		$detalhes = array(); // Detalhes sutis (ondas, vitórias-régias, pedrinhas, flocos...): valem até com arvores = 0.
		for ($v = 0; $v < 12 && file_exists("images/terreno/detalhe_".$nome."_".$v.".png"); $v++) { $detalhes[] = "images/terreno/detalhe_".$nome."_".$v.".png"; }
		$cache[$nome] = array($chaos, $decos, $detalhes);
	}
	return $cache[$nome];

}}

if (!function_exists('fundodacasa')){
function corsolo($arquivo) { // Cor média de uma imagem de chão (cache em memória e em disco por arquivo).
	static $mem = null; $cache = sys_get_temp_dir()."/naruto_corsolo.json";
	if ($mem === null) { $mem = is_file($cache) ? (array)json_decode((string)@file_get_contents($cache), true) : array(); }
	if (isset($mem[$arquivo])) { return $mem[$arquivo]; }
	$cor = "#5a6b3a";
	if (is_file($arquivo) && function_exists('imagecreatefromstring')) {
		$im = @imagecreatefromstring((string)file_get_contents($arquivo));
		if ($im) { $p = imagecreatetruecolor(1, 1); imagecopyresampled($p, $im, 0, 0, 0, 0, 1, 1, imagesx($im), imagesy($im)); $c = imagecolorat($p, 0, 0); $cor = sprintf("#%02x%02x%02x", ($c >> 16) & 255, ($c >> 8) & 255, $c & 255); }
	}
	$mem[$arquivo] = $cor; @file_put_contents($cache.".".getmypid(), json_encode($mem)); @rename($cache.".".getmypid(), $cache);
	return $cor;
}

function fundodacasa($i, $j, $grade, $temarvore) { // CSS "background" da casa: decoração, bordas/cantos dos terrenos vizinhos e o chão.

	$ter = $grade[$i][$j];
	$lad = ladrilhosdoterreno($ter[2]);
	if (count($lad[0]) == 0) { return "url('".$ter[0]."') center center repeat"; } // Sem ladrilhos: imagem antiga inteira.
	$h = (($i * 2654435761) ^ ($j * 40503) ^ 0x5bd1e995) & 0x7fffffff; // Outro sorteio fixo (variação do chão e da decoração).
	$camadas = array();
	// Decoração inteira dentro da casa: onde o sorteio de árvores caiu (e, na floresta, em mais 1 de cada 3 casas).
	if ($ter[1] == 1 && count($lad[1]) > 0 && ($temarvore || ($ter[2] == "floresta" && $h % 3 == 0))) {
		$camadas[] = "url('".$lad[1][intdiv($h, 7) % count($lad[1])]."') center center no-repeat";
	} elseif (count($lad[2]) > 0 && intdiv($h, 13) % 5 < 2) {
		// Sem decoração: ~40% das casas ganham um detalhe sutil do terreno (mesmo sorteio fixo), para a água, a areia, a neve
		// e as trilhas não ficarem só com o chão liso. Mesmo formato da decoração, então também some em casa ocupada.
		$camadas[] = "url('".$lad[2][intdiv($h, 97) % count($lad[2])]."') center center no-repeat";
	}
	// Terreno vizinho de maior prioridade "invade" a casa por uma borda (ou só pelo canto) irregular.
	$lados = array("n" => array(1, 0), "s" => array(-1, 0), "o" => array(0, -1), "l" => array(0, 1));
	$outro = function($v) use ($ter) { return $v[2] != $ter[2] && $v[3] > $ter[3] && count(ladrilhosdoterreno($v[2])[0]) > 0; };
	foreach (array("no" => array(1, -1, "n", "o"), "nl" => array(1, 1, "n", "l"), "so" => array(-1, -1, "s", "o"), "sl" => array(-1, 1, "s", "l")) as $c => $d) {
		$v = $grade[$i + $d[0]][$j + $d[1]];
		$a = $grade[$i + $lados[$d[2]][0]][$j]; $b = $grade[$i][$j + $lados[$d[3]][1]];
		if ($outro($v) && $a[2] != $v[2] && $b[2] != $v[2]) { $camadas[] = "url('images/terreno/".$v[2]."_c_".$c.".png') no-repeat"; }
	}
	foreach ($lados as $l => $d) {
		$v = $grade[$i + $d[0]][$j + $d[1]];
		if ($outro($v)) { $camadas[] = "url('images/terreno/".$v[2]."_b_".$l.".png') no-repeat"; }
	}
	$chao = $lad[0][$h % count($lad[0])];
	$camadas[] = "url('".$chao."') no-repeat";
	// Cor média do chão por baixo de tudo: enquanto o ladrilho carrega, a casa não pisca no verde do fundo da tabela.
	return implode(", ", $camadas)." ".corsolo($chao);

}}

//html para o minimap. Cada casa tem 30x30 (ícone 24x24 + 3px de padding) e recebe ladrilhos próprios do seu terreno.
$grade = array(); // Terrenos das casas visíveis e de uma casa a mais em volta (para as bordas).
for ($i = ($userrow["latitude"] + 3); $i >= ($userrow["latitude"] - 3); $i--){
	for ($j = ($userrow["longitude"] - 3); $j <= ($userrow["longitude"] + 3); $j++){ $grade[$i][$j] = terrenodacasa($i, $j); }
}
$minimap = "<table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" background=\"images/background_minimapa.jpg\" style=\"border:1px #000000 solid\">";
for ($i = ($userrow["latitude"] + 2); $i >= ($userrow["latitude"] - 2); $i--){
	$minimap .= "<tr>";
	for ($j = ($userrow["longitude"] - 2); $j <= ($userrow["longitude"] + 2); $j++){
		// Decoração só em casa livre (vila, NPC e jogadores ficam sozinhos e legíveis).
		$temarvore = ($matriz[$i][$j]["imagemtd"] != "" && $matriz[$i][$j]["imagem"] == "images/24/gif24.gif");
		$livre = ($matriz[$i][$j]["imagem"] == "images/24/gif24.gif");
		$fundo = fundodacasa($i, $j, $grade, $temarvore);
		if (!$livre) { $fundo = preg_replace("/^url\('images\/terreno\/(deco|detalhe)_[^']*'\) center center no-repeat, /", "", $fundo); }
		$caveira = !empty($matriz[$i][$j]["caveira"]) ? "<img src=\"images/caveira_".$matriz[$i][$j]["caveira"].".gif\" style=\"position:absolute;right:-2px;top:-2px\" alt=\"\" border=\"0\">" : "";
		$minimap .= "<td style=\"border:1px #ffefb7 solid;width:24px;height:24px;background:".$fundo."\"><a href=\"index.php?do=andar&amp;csrf=".csrf_token()."&amp;latitude=".$i."&amp;longitude=".$j."\" style=\"position:relative;display:inline-block\"><img src=\"".$matriz[$i][$j]["imagem"]."\" title=\"".$matriz[$i][$j]["title"]."\" border=\"0\">$caveira</a></td>";
	}
	$minimap .= "</tr>";
}
$minimap .= "</table>";

?>