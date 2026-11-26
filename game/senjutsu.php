<?php // senjutsu.php :: Senjutsu (Arte Eremita / modo sábio) e o chamado de Fukasaku e Shima, na Montanha Myoboku.
//
// Treino (do=aprendendo2): só nas vilas com towns.tem_senjutsu = 1 (originalmente a Montanha Myoboku) e só para quem já é Genin.
//   8 treinos com 900 minutos (15 horas) de espera entre eles. Ao concluir, senjutsuhtml vira "fechado".
// Uso (do=usar / do=cancelar, pelo "olho" da coluna da direita):
//   ativado, dá o bônus da especialização (tabela classes, colunas sj_*; sem especialização: +20% de Poder de
//   Ataque e +20% de Poder de Defesa) e gasta 1 NP a cada 3 segundos, mesmo que o jogador não atualize a página.
//   O bônus é calculado na hora do combate (senjutsuativo(), funcoesinclusas.php): some quando o NP acaba.
// Campos usados:
//   users.senjutsuhtml ...: "" = não aprendeu, "fechado" = aprendido e desativado, "senjutsu" = ativo
//                           (também é o nome da imagem do olho em images/olhos/);
//   users.senjutsutimer ..: "d/m/aaaa,hh:mm:ss,1" = momento da última cobrança de NP (ou "None");
//   users.atkdefsenjutsu .: só contas antigas: "ataque,defesa" somados na ativação pela regra antiga (devolvidos ao desativar);
//   users.treinamento ....: lista de treinos (formato no cabeçalho de treinamentoequests.php).
// Chamar (do=chamar): Fukasaku e Shima entram no grupo (avatar 16), requisito da Missão 19.
//
// O doujutsu.php inclui este arquivo com $somenteolho = true, liberando só usar/cancelar (atalho antigo).
// Senjutsu e doujutsu (funcoesdoujutsu.php) não ficam ativos ao mesmo tempo: usar() recusa com o olho aberto.

include_once('lib.php');
$link = opendb();

include_once('cookies.php');
$userrow = checkcookies();

include_once('funcoesinclusas.php');

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }

if (isset($_GET["do"])) {
	$do = $_GET["do"];
	if (!empty($somenteolho) && ($do != "usar") && ($do != "cancelar")) { header('Location: ./index.php'); die(); }
	if ($do == "jutsu") { jutsu(); }
	elseif ($do == "aprendendo2") { aprendendo2(); }
	elseif ($do == "usar") { usar(); }
	elseif ($do == "cancelar") { cancelar(); }
	elseif ($do == "chamar") { chamar(); }
}


function verificarsenjutsu($cidade = true) { // Checagens: fora de batalha, fora de duelo e (opcional) dentro da Montanha Myoboku.

	global $userrow;
	if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }
	if ($userrow["batalha_timer2"] == 5) { display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "),t("Erro"),false,false,false); die(); }
	if ($cidade == false) { return false; }
	$townquery = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
	if (mysqli_num_rows($townquery) == 0) { return false; }
	return mysqli_fetch_array($townquery);

}


function jutsu() { // Fala de Fukasaku explicando o Senjutsu (mostrada em cima do Painel de Treinos).

	$townrow = verificarsenjutsu();
	if (($townrow == false) || (intval($townrow["tem_senjutsu"]) != 1)) { header('Location: ./treinamentoequests.php?do=treinamento&conteudo='.rawurlencode(t("Você não pode usar essa função fora da Montanha Myoboku."))); die(); }

	// O mestre é o Kage/líder da vila (towns.kage; na Montanha Myoboku, Fukasaku).
	$conteudodois = "
 ".t("Olá pequeno ninja, você veio até mim à procura da Arte Eremita? O Senjutsu é um tipo de jutsu que utiliza a energia da natureza, ou seja, Pontos de Natureza(NP), para chegar à perfeição do Senjutsu você precisa completar 8 treinamentos, com 900 minutos (15 horas) de intervalo entre cada um. Quando ativado, ele fortalece você de acordo com a sua especialização para que você possa enfrentar seu inimigo. Se mesmo assim você ainda não desistiu, podemos <a href=\"senjutsu.php?do=aprendendo2\">começar agora mesmo</a> o treinamento.")."
";
	personagemmissao($conteudodois, $townrow);
	die();

}


function aprendendo2() { // Um treino de Senjutsu (o primeiro clique só adiciona o Senjutsu à tabela de treinos).

	global $userrow, $link;
	$townrow = verificarsenjutsu();
	if (($townrow == false) || (intval($townrow["tem_senjutsu"]) != 1)) { header('Location: ./treinamentoequests.php?do=treinamento&conteudo='.rawurlencode(t("Você não pode treinar esse Jutsu fora da Montanha Myoboku."))); die(); }
	if ($userrow["graduacao"] == "Estudante da Academia") { header('Location: ./treinamentoequests.php?do=treinamento&conteudo='.rawurlencode(t("Você não pode fazer esse treinamento se não for ao menos um Genin."))); die(); }

	$today = date("j/n/Y");
	$todayhour = date("H:i:s");
	$nomedojutsu = "Senjutsu";
	$totaltreinos = 8;
	$tempoprapassar = 900; // minutos entre um treino e outro.
	$inicio = pegartexto($_GET, "inicio"); // vem do link "começar agora mesmo".

	// Monta a nova lista de treinos, somando 1 ao Senjutsu (ou adicionando-o no fim, com espera 0).
	$antigo = $userrow["treinamento"];
	$treinos = ($antigo == "None") ? array() : explode(";", $antigo);
	$novalista = ""; $achou = false; $concluiu = false;
	foreach ($treinos as $treino) {
		if ($treino == "") { continue; }
		$sub = array_pad(explode(",", $treino), 6, "0");
		if ($sub[0] == $nomedojutsu) {
			$achou = true;
			if ($inicio != "") { header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(tf("Você já possui o(a) %s na sua lista de treinamento.", t($nomedojutsu)))); die(); }
			$sub[2] = min(intval($sub[2]), $totaltreinos); // quem começou quando eram 12 treinos passa a precisar de 8.
			if (intval($sub[1]) >= intval($sub[2]) && $userrow["senjutsuhtml"] == "" && $inicio == "") { $sub[1] = intval($sub[2]); $concluiu = true; $novalista .= $sub[0].",".$sub[1].",".$sub[2].",".$today.",".$todayhour.",".$tempoprapassar.";"; continue; } // já passou dos 8: conclui agora.
			if (intval($sub[1]) >= intval($sub[2])) { header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(tf("Você já completou o treinamento do(a) %s.", t($nomedojutsu)))); die(); }
			$retorno = tempojutsu($sub[3], $sub[4], intval($sub[5]));
			if ($retorno !== "ok") { header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(tf("Você ainda não pode treinar, é preciso aguardar %s minuto(s) até que você possa treinar o(a) %s novamente.", $retorno, t($nomedojutsu)))); die(); }
			$sub[1] = intval($sub[1]) + 1;
			if ($sub[1] >= intval($sub[2])) { $sub[1] = intval($sub[2]); $concluiu = true; }
			$treino = $sub[0].",".$sub[1].",".intval($sub[2]).",".$today.",".$todayhour.",".$tempoprapassar;
		}
		$novalista .= $treino.";";
	}
	if (!$achou) { $novalista .= $nomedojutsu.",0,".$totaltreinos.",".$today.",".$todayhour.",0;"; }

	// O que se ganha ao concluir: o Senjutsu aprendido (olho fechado = pronto para ativar).
	$extra = ($concluiu) ? ", senjutsuhtml='fechado'" : "";
	// "AND treinamento=..." evita que dois cliques ao mesmo tempo contem dois treinos.
	doquery("UPDATE {{table}} SET treinamento='".addslashes($novalista)."'".$extra." WHERE id='".intval($userrow["id"])."' AND treinamento='".addslashes($antigo)."' LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1) { header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(t("Seu treinamento já foi registrado."))); die(); }

	if (!$achou) { header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(tf("Você adicionou o(a) %s à sua tabela de treinamentos.", t($nomedojutsu)))); die(); }
	if ($concluiu) { header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(tf("Você completou o treinamento do(a) %s. Parabéns!", t($nomedojutsu)))); die(); }
	header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(tf("Você treinou o(a) %s. Você poderá treinar novamente dentro de %s minutos.", t($nomedojutsu), $tempoprapassar))); die();

}


function voltarpagina() { // Volta para a última página do jogo visitada (users.pagina), só se for uma página do próprio jogo.

	global $userrow;
	$pagina = (string)$userrow["pagina"];
	if (!preg_match("/^[a-z0-9_]+\.php(\?[^\r\n]*)?$/i", $pagina) || (strpos($pagina, "senjutsu.php") === 0) || (strpos($pagina, "doujutsu.php") === 0)) { $pagina = "index.php"; }
	header('Location: ./'.$pagina); die();

}


function desativarsenjutsu() { // Fecha o olho (e tira o bônus de ataque/defesa que a regra antiga somava na ativação, se houver).

	global $userrow;
	$retir = array_pad(explode(",", $userrow["atkdefsenjutsu"]), 2, "0");
	$userrow["attackpower"] = max(0, intval($userrow["attackpower"]) - intval($retir[0]));
	$userrow["defensepower"] = max(0, intval($userrow["defensepower"]) - intval($retir[1]));
	$userrow["senjutsuhtml"] = "fechado";
	$userrow["senjutsutimer"] = "None";
	$userrow["atkdefsenjutsu"] = "0,0";

}


function usar() { // Ativa o Senjutsu (ou, se já estiver ativo, só cobra o NP do tempo que passou).
	global $userrow;
	$estadoantigo = $userrow["senjutsuhtml"];
	if ($estadoantigo == "") { header('Location: ./index.php?conteudo='.rawurlencode(t("Você precisa adquirir o senjutsu antes de usá-lo!"))); die(); }
	if ($estadoantigo != "fechado") { senjutsu(); voltarpagina(); } // já ativo: só cobra o NP (funcoesinclusas.php).
	if (intval($userrow["currentnp"]) <= 0) { header('Location: ./index.php?conteudo='.rawurlencode(t("Você não tem NP suficiente para ativar o Senjutsu!"))); die(); }
	// Senjutsu e doujutsu não ficam ativos ao mesmo tempo (funcoesdoujutsu.php): cobra o Chakra do olho (pode fechá-lo) e confere.
	include_once('funcoesdoujutsu.php');
	doujutsucobrar();
	if (intval($userrow["doujutsu_ativo"]) == 1) { header('Location: ./index.php?conteudo='.rawurlencode(t("Desative o doujutsu antes de ativar o Senjutsu: os dois não podem ficar ativos ao mesmo tempo."))); die(); }
	// Ativar: o bônus não é somado no Poder de Ataque/Defesa (é calculado no combate enquanto houver NP).
	// mainmsg 3 = janela explicando o Senjutsu (templates/mostrarmainmsg.php).
	// "AND senjutsuhtml='fechado'" impede que dois cliques simultâneos ativem duas vezes.
	doquery("UPDATE {{table}} SET atkdefsenjutsu='0,0',senjutsuhtml='senjutsu',senjutsutimer='".date("j/n/Y").",".date("H:i:s").",1',mainmsg='3' WHERE id='".intval($userrow["id"])."' AND senjutsuhtml='fechado' AND currentnp>'0' AND doujutsu_ativo='0' LIMIT 1", "users");
	voltarpagina();
}

function cancelar() { // Desativa o Senjutsu, cobrando o NP do tempo em que ficou ativo.
	global $userrow;
	if ($userrow["senjutsuhtml"] != "senjutsu") { header('Location: ./index.php?conteudo='.rawurlencode(t("O Senjutsu já foi desativado ou você não tem NP suficiente!"))); die(); }
	$e = senjutsuestado($userrow);
	desativarsenjutsu();
	doquery("UPDATE {{table}} SET atkdefsenjutsu='0,0',attackpower='".intval($userrow["attackpower"])."',defensepower='".intval($userrow["defensepower"])."',senjutsuhtml='fechado',senjutsutimer='None',currentnp=GREATEST(0, currentnp-".intval($e["cobrado"]).") WHERE id='".intval($userrow["id"])."' AND senjutsuhtml='senjutsu' LIMIT 1", "users");
	voltarpagina();
}

function chamar() { // Fukasaku e Shima entram no grupo do jogador (o avatar vira o 16). Requisito da Missão 19.

	global $userrow;
	$townrow = verificarsenjutsu();
	if (($townrow == false) || (intval($townrow["tem_senjutsu"]) != 1)) { header('Location: ./index.php?conteudo='.rawurlencode(t("Você não pode usar essa função fora da Montanha Myoboku."))); die(); }
	if ($userrow["acesso"] > 1) { header('Location: ./index.php?conteudo='.rawurlencode(t("Gamemasters não podem trocar seu avatar."))); die(); }

	include_once('funcoesavatares.php');
	avatarliberar($userrow["id"], 16); // o avatar 16 fica liberado na tela de avatares (pode voltar a ele depois).
	doquery("UPDATE {{table}} SET avatar='16' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");

	$page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/grupar.gif\" /></center></td></tr></table>
";
	$page2 = t("Está precisando de mim? Você quer que eu entre no seu grupo? Não há problema, Shima e eu iremos com você em sua jornada. Agora fazemos parte do seu grupo!")."
<br>".t("<a href=\"index.php\">Voltar à pagina de jogo</a>.");
	$page .= personagemgeral($page2, '2', t('Fukasaku'));

	display($page, t("Chamar"), false, false, false);

}

?>
