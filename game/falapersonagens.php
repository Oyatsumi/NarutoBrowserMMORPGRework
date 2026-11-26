<?php // falapersonagens.php :: Conversa com os NPCs espalhados pelo mapa (tabela npcs).
//
// O botão "Conversar" aparece no mapa (localpersonagens.php) quando o jogador está em cima de um NPC visível.
//  - NPC da etapa atual da história: mostra a fala e liga users.missaoswitch = 1, que é o requisito "falar com o NPC"
//    das missões da história que têm npc_id (6, 9, 12, 15, 18 e 21 no jogo original; conferido em missoes.php).
//  - Outros NPCs: mostram a fala, completam as missões extras "falar_com_npc" que apontam para eles e listam as
//    missões extras que eles oferecem (aceitar/entregar em missoesextras.php, com token anti-CSRF).
//  - NPC com npcs.papel = "comprador_pergaminhos": abre a tela do comprador de pergaminhos distante (index.php?do=comprador).

include('lib.php');
$link = opendb();

include('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php');
include_once('funcoesmissoesextras.php');

if ($userrow == false) { header("Location: login.php?do=login"); die(); }
if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }

$nome = $userrow['charname'];
$npc = mx_npcaqui($userrow);

if ($npc !== false && mx_npcnahistoria($npc, $userrow)) {
	$indexconteudo = personagemgeral(mx_falanpc($npc, $nome), $npc["avatar"], $npc["nome"], 'sem<br>');
	$valorlib = 1; // para a index.php não incluir o lib.php de novo.
	doquery("UPDATE {{table}} SET missaoswitch='1' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
	include('index.php');
	die();
}

// NPC com papel especial: o comprador de pergaminhos distante abre direto a tela de venda (towns.php, compradorremoto()).
if ($npc !== false && $npc["papel"] == "comprador_pergaminhos") { header("Location: index.php?do=comprador"); die(); }

if ($npc !== false) {
	// Funções do painel de missões (pedrasprogresso, jsarg...) sem incluir o lib.php de novo.
	$valorlib = 2;
	include_once('treinamentoequests.php');
	$aviso = mx_falouconpc($userrow, $npc);
	$retorno = lermsg("msg");
	$texto = mx_falanpc($npc, $nome);
	if ($aviso != "") { $texto .= "<br><br><font color=\"brown\">".$aviso."</font>"; }
	if ($retorno != "") { $texto .= "<br><br><font color=\"brown\">".strip_tags($retorno, "<b>")."</font>"; }
	$missoes = mx_listahtml($userrow, false, $npc);
	$indexconteudo = personagemgeral($texto, $npc["avatar"], $npc["nome"], 'sem<br>').($missoes != "" ? "<div style=\"width:462px;margin:0 auto\">".$missoes."</div>" : "");
	$valorlib = 1;
	include('index.php');
	die();
}

// Ninguém para conversar aqui (etapa errada ou fora do local do NPC).
header("Location: index.php");

?>
