<?php // missoesextras.php :: Missões (opcionais) aceitas e entregues nas vilas ou com NPCs do mapa.
//
// Rotas:
//   missoesextras.php                 painel com as missões da vila atual (e as aceitas em outros lugares)
//   missoesextras.php?do=aceitar      (POST id + csrf) aceita uma missão da vila atual ou do NPC onde o jogador está
//   missoesextras.php?do=entregar     (POST id + csrf) entrega: consome itens/Ryou e paga experiência, Ryou e item
// As regras e o formato das tabelas estão em funcoesmissoesextras.php.

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php');
$valorlib = 2; // o treinamentoequests.php (pedrasprogresso, jsarg, verificarpainel, cidadeatual) não inclui o lib.php de novo.
include_once('treinamentoequests.php');
include_once('funcoesmissoesextras.php');

verificarpainel();

$do = pegartexto($_GET, "do");
if ($do == "aceitar" || $do == "entregar") { acaomissaoextra($do); }
painelextras();


function voltarextras($mensagem, $npc) { // Volta para o painel da vila ou para a conversa com o NPC, com a mensagem.

	$mensagem = strip_tags($mensagem);
	if ($npc) { header("Location: falapersonagens.php?msg=".rawurlencode($mensagem)); die(); }
	header("Location: missoesextras.php?conteudo=".rawurlencode($mensagem)); die();

}


function acaomissaoextra($acao) { // Aceitar/entregar (somente POST com token anti-CSRF).

	global $userrow;
	$townrow = cidadeatual();
	$npc = mx_npcaqui($userrow);
	if ($townrow && $userrow["currentaction"] != "In Town") { $townrow = false; }
	if ($npc && mx_npcnahistoria($npc, $userrow)) { $npc = false; }
	if (!isset($_SERVER["REQUEST_METHOD"]) || $_SERVER["REQUEST_METHOD"] != "POST" || !csrf_confere()) {
		voltarextras(t("Link inválido ou expirado. Tente novamente pelo próprio jogo."), $npc);
	}
	$id = pegarint($_POST, "id");
	if ($townrow == false && $npc == false) { voltarextras(t("Você precisa estar numa vila ou ao lado do NPC da missão."), false); }
	$mensagem = ($acao == "aceitar") ? mx_aceitar($userrow, $id, $townrow, $npc) : mx_entregar($userrow, $id, $townrow, $npc);
	voltarextras($mensagem, $npc);

}


function painelextras() { // Painel no mesmo visual do Painel de Missões.

	global $userrow;
	$conteudo = lermsg("conteudo");
	$townrow = cidadeatual();
	if ($townrow && $userrow["currentaction"] != "In Town") { $townrow = false; }

	if ($townrow != false) {
		$fala = ($conteudo != "") ? $conteudo : tf("Olá %s! Além das missões da vila, sempre há alguém precisando de ajuda por aqui. Veja os pedidos abaixo.", $userrow["charname"]);
		$topo = personagemgeral($fala, $townrow["id"], t($townrow["kage"]));
	} else {
		$topo = ($conteudo != "") ? "<center><font color=brown>".$conteudo."</font></center><br>" : "";
		$topo .= "<center>".t("Você está fora de uma vila. Aqui aparecem apenas as missões que você já aceitou; algumas missões são oferecidas por NPCs no mapa.")."</center><br>";
	}
	$html = mx_listahtml($userrow, $townrow, false);

	$page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/missao.gif\" /></center></td></tr></table>
	<center><b>".t("Missões")."</b></center><br>
	".$topo."
	$html
	<center><a href=\"treinamentoequests.php?do=quests\" class=\"btnjogo bj-neutro\" style=\"text-decoration:none\"><img src=\"images/aceitar_missao.png\" width=\"16\" height=\"16\" alt=\"\" style=\"image-rendering:pixelated;vertical-align:-3px\"> ".t("Painel de Missões")."</a></center><br>";

	display($page, t("Missões"), false, false, false);
	die();

}

?>
