<?php // pm.php :: Caixa de mensagens privadas do jogador (leitura). O envio é feito pela janela flutuante (mainmsg.php?do2=enviarpm).

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
if ($userrow["authlevel"] == 2) { die(t("Sua conta foi bloqueada. Por favor tente novamente mais tarde.")); }

if (isset($_GET["do"]) && $_GET["do"] == "enviar") { enviar(); }
else { ler(); }

function ler() { // Mostra as últimas mensagens recebidas (mais novas primeiro) e marca a caixa como lida.

	global $userrow;

	$conteudo = lermsg("conteudo");
	if ($conteudo != "") { $conteudo = "<center><font color=\"brown\">".strip_tags(stripslashes($conteudo))."</font></center><br />"; }

	// users.caixadepm: até 25 mensagens separadas por "@#$%!;;;", da mais antiga para a mais nova.
	// Cada mensagem já foi montada (e escapada) no envio: "[<b><a ...>Remetente</a></b>] texto".
	// A posição 0 é a que será descartada no próximo envio, por isso são exibidas apenas as 24 últimas.
	$mensagens = array_slice(explode("@#$%!;;;", (string)$userrow["caixadepm"]), -24);
	$mensagens = array_reverse($mensagens);

	$paginafinal = "<table width=\"100%\"><tr bgcolor=\"#613003\"><td><font color=\"white\"><center>".t("Últimas Mensagens Privadas")."</center></font></td></tr>";
	$auxiliar = 1;
	foreach ($mensagens as $mensagem) {
		if (trim($mensagem) == "" || $mensagem == "None") { continue; }
		$bgcolor = ($auxiliar % 2 == 0) ? "#E4D094" : "#FFF1C7";
		// Mensagens de sistema podem vir no formato neutro "i18n:..." (textoi18n). Nas mensagens de jogadores só o
		// texto fixo do link (title="Enviar Mensagem") é traduzido aqui; o que o jogador escreveu fica como foi digitado.
		$mensagem = str_replace("title=\"Enviar Mensagem\"", "title=\"".t("Enviar Mensagem")."\"", textoi18n($mensagem));
		$paginafinal .= "<tr bgcolor=\"$bgcolor\"><td style=\"border:1px #000000 solid\">".$mensagem."</td></tr>";
		$auxiliar++;
	}
	if ($auxiliar == 1) { $paginafinal .= "<tr bgcolor=\"#FFF1C7\"><td style=\"border:1px #000000 solid\">".t("Não há mensagens aqui.")."</td></tr>"; }
	$paginafinal .= "</table>";

	// Apaga o aviso de "nova mensagem" do menu da esquerda.
	if ($userrow["pmsnovas"] != 0) { doquery("UPDATE {{table}} SET pmsnovas='0' WHERE id='".intval($userrow["id"])."' LIMIT 1","users"); }

	$page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/pm.gif\" alt=\"".t("Mensagens Privadas")."\" /></center></td></tr></table>
$conteudo
$paginafinal
";
	display($page, t("Mensagens Privadas"), false, false, false);

}

function enviar() { // Rota antiga de envio: abre a janela flutuante de envio (mainmsg.php), que faz todas as validações.

	$nomedochar = stripslashes(pegartexto($_GET, "nomedochar"));
	header("Location: mainmsg.php?do2=enviarpm&nomedochar=".urlencode(html_entity_decode($nomedochar, ENT_QUOTES, 'UTF-8')));
	die();

}

?>
