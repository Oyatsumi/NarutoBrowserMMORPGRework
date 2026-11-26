<?php // localpersonagens.php :: NPCs (personagens das missões) espalhados pelo mapa.
// Os NPCs ficam na tabela npcs (editável em admin_missoes.php). Um NPC ligado a uma missão da história só aparece
// quando o jogador está na etapa dessa missão (users.missao, 1º número); os demais NPCs ativos aparecem sempre.
// Este arquivo é incluído em dois lugares:
//   - index.php (doexplore): define $opcoesnovas com o botão "Conversar" quando o jogador está em cima do NPC;
//   - templates/minimap.php: desenha o NPC no mini mapa (usa $i/$j da célula que está sendo desenhada).
// A lista é lida do banco uma única vez por página (cache em mx_npcsvisiveis()).

global $opcoesnovas, $userrow;
include_once('funcoesmissoesextras.php');

// "latitude,longitude" => linha do NPC (nome, icone_mapa em images/24/, avatar...).
$npcsdomapa = mx_npcsvisiveis($userrow);

$opcoesnovas = "";
// Botão "Conversar" se o jogador estiver exatamente no local de um NPC.
if (isset($npcsdomapa[intval($userrow['latitude']).",".intval($userrow['longitude'])])) {
	$opcoesnovas = "<a href=\"falapersonagens.php\"><img src=\"images/24/conversar.gif\" border=\"0\" title=\"".t("Conversar")."\" alt=\"X\" /></a>";
}

// Desenho do NPC no mini mapa (só quando incluído pelo minimap.php).
if (isset($i) && isset($j) && isset($npcsdomapa[intval($i).",".intval($j)])) {
	$npc = $npcsdomapa[intval($i).",".intval($j)];
	$matriz[$i][$j]["imagem"] = "images/24/".$npc["icone_mapa"].".gif";
	$matriz[$i][$j]["title2"] = "NPC: ".$npc["nome"];
	$matriz[$i][$j]["imagemtd"] = "";
}

?>
