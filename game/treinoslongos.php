<?php // treinoslongos.php :: Mestres dos treinos longos (Domínio de Elemento, Controle de Chakra, Corpo de Ferro,
// Caminhada Ninja, Meditação Natural e Treino de Jutsu Raro). Regras e recompensas: funcoestreinos.php.
//   do=falar&t=CHAVE ...: fala do mestre (na vila do treino) com o link para começar, em cima do Painel de Treinos.
//   do=treinar&t=CHAVE ..: começa o treino (primeira vez, &inicio=1) ou faz uma sessão.

include_once('lib.php');
$link = opendb();
include_once('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php');
include_once('funcoestreinos.php');

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }
if ($userrow["batalha_timer2"] == 5) { display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "),t("Erro"),false,false,false); die(); }

$do = pegartexto($_GET, "do");
$chave = pegartexto($_GET, "t");
$lista = treinoslista();
if (!isset($lista[$chave])) { header('Location: ./index.php?conteudo='.rawurlencode(t("Esse treinamento não existe."))); die(); }
$treino = $lista[$chave];
if ($do == "falar") { treinofalar($treino); }
elseif ($do == "treinar") { treinosessao($treino); }
header('Location: ./index.php'); die();


function treinovoltar($msg) { // Volta ao Painel de Treinos com uma mensagem.

	header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode($msg)); die();

}


function treinocidade($tr) { // A vila onde o jogador está, se for a vila do treino (senão volta com erro).

	global $userrow;
	$q = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
	$townrow = mysqli_fetch_array($q);
	if (!$townrow || intval($townrow["id"]) != intval($tr["vila_id"]) || intval($tr["ativo"]) != 1) {
		treinovoltar(tf("Esse treinamento só pode ser feito na(o) %s.", treinonomevila($tr["vila_id"])));
	}
	return $townrow;

}


function treinofalar($tr) { // Fala do mestre explicando o treino (em cima do Painel de Treinos).

	global $userrow;
	treinocidade($tr);
	$nome = t($tr["nome"]);
	$texto = tf("Olá, %s. Eu ensino o(a) <b>%s</b>: são %s sessões, com um intervalo de %s minutos entre uma e outra.", $userrow["charname"], $nome, intval($tr["sessoes"]), intval($tr["intervalo"]))."<br>"
		."<b>".t("Recompensa:")."</b> ".treinodescricao($tr)."<br>"
		."<b>".t("Requerimentos:")."</b> ".treinorequisitostexto($tr)."<br>";
	$erro = treinoerrorequisito($tr, $userrow);
	if ($erro != "") { $texto .= "<font color=\"#9b1b10\">".$erro."</font>"; }
	else { $texto .= t("Se quiser, podemos <a href=\"treinoslongos.php?do=treinar&t=CHAVE&inicio=1\">começar agora mesmo</a>."); $texto = str_replace("CHAVE", rawurlencode($tr["chave"]), $texto); }

	global $valorlib;
	$valorlib = 2; // o treinamentoequests.php não inclui o lib.php de novo.
	include_once('treinamentoequests.php');
	treinamento(personagemgeral($texto, $tr["avatar"], t($tr["mestre"])));
	die();

}


function treinosessao($tr) { // Começa o treino ou faz uma sessão (gravação atômica: "AND treinamento=...").

	global $userrow, $link;
	treinocidade($tr);
	$nome = $tr["nome"];
	$erro = treinoerrorequisito($tr, $userrow);
	if ($erro != "") { treinovoltar($erro); }

	$inicio = pegartexto($_GET, "inicio");
	$today = date("j/n/Y"); $todayhour = date("H:i:s");
	$intervalo = intval($tr["intervalo"]);

	$antigo = $userrow["treinamento"];
	$treinos = ($antigo == "None" || $antigo == "") ? array() : explode(";", $antigo);
	$novalista = ""; $achou = false; $concluiu = false;
	foreach ($treinos as $item) {
		if ($item == "") { continue; }
		$sub = array_pad(explode(",", $item), 6, "0");
		if ($sub[0] == $nome) {
			$achou = true;
			if ($inicio != "") { treinovoltar(tf("Você já possui o(a) %s na sua lista de treinamento.", t($nome))); }
			if (intval($sub[1]) >= intval($sub[2])) { treinovoltar(tf("Você já completou o treinamento do(a) %s.", t($nome))); }
			$retorno = tempojutsu($sub[3], $sub[4], intval($sub[5]));
			if ($retorno !== "ok") { treinovoltar(tf("Você ainda não pode treinar, é preciso aguardar %s minuto(s) até que você possa treinar o(a) %s novamente.", $retorno, t($nome))); }
			$sub[1] = intval($sub[1]) + 1;
			if ($sub[1] >= intval($sub[2])) { $sub[1] = intval($sub[2]); $concluiu = true; }
			$item = $sub[0].",".$sub[1].",".intval($sub[2]).",".$today.",".$todayhour.",".$intervalo;
			if ($concluiu && treinorepetivel($tr)) { continue; } // doujutsu: sai da lista ao concluir (pode ser refeito).
		}
		$novalista .= $item.";";
	}
	// Primeira vez: entra na lista com 0 sessões e sem espera (o total fica gravado, mesmo que o admin mude depois).
	if (!$achou) { $novalista .= $nome.",0,".max(1, intval($tr["sessoes"])).",".$today.",".$todayhour.",0;"; }

	if ($novalista == "") { $novalista = "None"; } // a lista ficou vazia (treino de doujutsu concluído).
	$extra = $achou ? treinosqlrecompensa($tr, $userrow, $concluiu) : "";
	doquery("UPDATE {{table}} SET treinamento='".addslashes($novalista)."'".$extra." WHERE id='".intval($userrow["id"])."' AND treinamento='".addslashes($antigo)."' LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1) { treinovoltar(t("Seu treinamento já foi registrado.")); }

	if (!$achou) { treinovoltar(tf("Você adicionou o(a) %s à sua tabela de treinamentos.", t($nome))); }
	if ($concluiu && treinorepetivel($tr)) { header("Location: ./doujutsu.php?conteudo=".rawurlencode(tf("Você completou o treinamento do(a) %s. Parabéns!", t($nome)))); die(); } // o treino saiu da lista: mostra o olho.
	if ($concluiu) { treinovoltar(tf("Você completou o treinamento do(a) %s. Parabéns!", t($nome))." ".treinodescricao($tr)); }
	treinovoltar(tf("Você treinou o(a) %s. Você poderá treinar novamente dentro de %s minutos.", t($nome), $intervalo));

}

?>
