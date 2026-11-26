<?php // jutsudebusca.php :: Jutsu de Busca: treino na Vila da Areia e uso (mostra onde está outro jogador).
//
// Treino (do=aprendendo2): só nas vilas com towns.tem_jutsubusca = 1 (originalmente a Vila da Areia). 10 treinos com 120 minutos (2 horas) de espera.
//   Ao concluir, users.jutsudebuscahtml vira 1 e o link "Jutsu de Busca" aparece na coluna da direita.
//   Formato de users.treinamento: veja o cabeçalho de treinamentoequests.php.
// Uso (do=usar): custa 30 de CH e revela as coordenadas de um jogador e se ele está online
//   (online = acessou o jogo nos últimos 61 segundos). Hoje o link da coluna da direita usa o
//   mainmsg.php?do2=usarjutsubusca; esta rota é a versão antiga, que mostra o resultado no meio da index.

include('lib.php');
$link = opendb();

include('cookies.php');
$userrow = checkcookies();

include_once('funcoesinclusas.php');

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }
if ($userrow["batalha_timer2"] == 5) { display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "),t("Erro"),false,false,false); die(); }

if (isset($_GET["do"])) {
	$do = $_GET["do"];
	if ($do == "jutsu") { jutsu(); }
	elseif ($do == "aprendendo2") { aprendendo2(); }
	elseif ($do == "usar") { unset($_GET["do"]); usar(); } // tira o "do" para a index.php incluída no fim não executar outra ação.
}


function cidadedabusca() { // Cidade onde o jogador está (ou false fora de uma cidade).

	global $userrow;
	$townquery = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
	if (mysqli_num_rows($townquery) == 0) { return false; }
	return mysqli_fetch_array($townquery);

}


function jutsu() { // Fala do Kazekage explicando o Jutsu de Busca (mostrada em cima do Painel de Treinos).

	$townrow = cidadedabusca();
	if (($townrow == false) || (intval($townrow["tem_jutsubusca"]) != 1)) { header('Location: ./index.php?conteudo='.rawurlencode(t("Você não pode usar essa função fora da Vila da Areia."))); die(); }

	// O mestre é o Kage/líder da vila (towns.kage; na Vila da Areia, o Kazekage).
	$conteudodois = "

 ".t("Olá pequeno ninja, você veio até mim à procura do Jutsu de Busca? O Jutsu de busca é um jutsu que precisa ser treinado 10 vezes em intervalos de duas horas à cada treinamento, para então ser aperfeiçoado. A finalidade do Jutsu é revelar as coordenadas do jogador que você está procurando e informar se ele está online ou não.")."<br>
 ".t("Se você ainda quer obter esse jutsu, podemos <a href=\"jutsudebusca.php?do=aprendendo2&inicio=true\">começar agora mesmo</a> o treinamento do Jutsu de Busca.")."

";
	personagemmissao($conteudodois, $townrow);
	die();

}


function aprendendo2() { // Um treino do Jutsu de Busca (o primeiro clique só adiciona o jutsu à tabela de treinos).

	global $userrow, $link;
	$townrow = cidadedabusca();
	if (($townrow == false) || (intval($townrow["tem_jutsubusca"]) != 1)) { header('Location: ./treinamentoequests.php?do=treinamento&conteudo='.rawurlencode(t("Você não pode treinar esse Jutsu fora da Vila da Areia."))); die(); }

	$today = date("j/n/Y");
	$todayhour = date("H:i:s");
	$nomedojutsu = "Jutsu de Busca";
	$totaltreinos = 10;
	$tempoprapassar = 120; // minutos entre um treino e outro.
	$inicio = pegartexto($_GET, "inicio"); // vem do link "começar agora mesmo".

	// Monta a nova lista de treinos, somando 1 ao Jutsu de Busca (ou adicionando-o no fim, com espera 0).
	$antigo = $userrow["treinamento"];
	$treinos = ($antigo == "None") ? array() : explode(";", $antigo);
	$novalista = ""; $achou = false; $concluiu = false;
	foreach ($treinos as $treino) {
		if ($treino == "") { continue; }
		$sub = array_pad(explode(",", $treino), 6, "0");
		if ($sub[0] == $nomedojutsu) {
			$achou = true;
			if ($inicio != "") { header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(tf("Você já possui o(a) %s na sua lista de treinamento.", t($nomedojutsu)))); die(); }
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

	// O que se ganha ao concluir: o Jutsu de Busca liberado.
	$extra = ($concluiu) ? ", jutsudebuscahtml='1'" : "";
	// "AND treinamento=..." evita que dois cliques ao mesmo tempo contem dois treinos.
	doquery("UPDATE {{table}} SET treinamento='".addslashes($novalista)."'".$extra." WHERE id='".intval($userrow["id"])."' AND treinamento='".addslashes($antigo)."' LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1) { header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(t("Seu treinamento já foi registrado."))); die(); }

	if (!$achou) { header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(tf("Você adicionou o(a) %s à sua tabela de treinamentos.", t($nomedojutsu)))); die(); }
	if ($concluiu) { header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(tf("Você completou o treinamento do(a) %s. Parabéns!", t($nomedojutsu)))); die(); }
	header("Location: ./treinamentoequests.php?do=treinamento&conteudo=".rawurlencode(tf("Você treinou o(a) %s. Você poderá treinar novamente dentro de %s minutos.", t($nomedojutsu), $tempoprapassar))); die();

}


function janeladabusca($conteudo) { // Moldura da janelinha "Jutsu de Busca" mostrada no meio da index.php.

	return "<center><table  bgcolor=\"#452202\"><tr><td width=\"18\"></td><td width=\"*\"><center><font color=white>".t("Jutsu de Busca")."</font></center></td><td width=\"18\"><a href=\"index.php\"><img src=\"images/deletar2.jpg\" title=\"".t("Fechar")."\"  alt=\"X\" border=\"0\"></a></td></tr><tr><td background=\"layoutnovo/menumeio/meio2.png\" colspan=\"3\"><font color=\"black\"><center>".$conteudo."</center></font></td></tr></table></center>";

}


function usar() { // Formulário do Jutsu de Busca e resultado da procura (mostrados dentro da index.php).

	global $userrow, $valorlib, $indexconteudo, $controlrow, $link;

	if ($userrow["jutsudebuscahtml"] != 1) { header("Location: ./index.php?conteudo=".rawurlencode(t("Você não pode usar esse jutsu sem ter treinado!"))); die(); }

	if (isset($_POST["submit"])) {
		// O nome já chega com addslashes/htmlspecialchars (tratamento global do lib.php), então pode ir entre aspas.
		$nomedaprocura = pegartexto($_POST, "nomedaprocura");

		$userquery = doquery("SELECT charname, latitude, longitude, UNIX_TIMESTAMP(onlinetime) AS ultimoacesso FROM {{table}} WHERE charname='$nomedaprocura' LIMIT 1", "users");
		if (mysqli_num_rows($userquery) != 1) { header("Location: ./index.php?conteudo=".rawurlencode(t("Não existe nenhuma conta com esse Nome."))); die(); }
		$userpara = mysqli_fetch_array($userquery);

		// Custo: 30 de CH (o "AND currentmp>=30" impede ficar com chakra negativo em cliques simultâneos).
		$pagina = t("Esse jutsu requer 30 de Chakra para ser usado.");
		if ($userrow["currentmp"] >= 30) {
			doquery("UPDATE {{table}} SET currentmp=currentmp-30 WHERE id='".intval($userrow["id"])."' AND currentmp>=30 LIMIT 1", "users");
			if (mysqli_affected_rows($link) == 1) {
				// Coordenadas no formato do jogo: latitude N/S e longitude E/W.
				$lat = intval($userpara["latitude"]); $lon = intval($userpara["longitude"]);
				$lat = ($lat < 0) ? ($lat * -1)."S" : $lat."N";
				$lon = ($lon < 0) ? ($lon * -1)."W" : $lon."E";
				$pagina = tf("O jogador %s está na coordenada: %s, %s.", $userpara["charname"], $lat, $lon);
				if ($userpara["ultimoacesso"] < (time() - 61)) {
					$pagina .= "<br>".t("Este jogador está <font color=red>Offline</font>.")." ";
					$pagina .= "<a href=\"javascript: openmappopup('".$lat."/".$lon."')\"><img src=\"images/maximizar.gif\" border=\"0\" title=\"".t("Mostrar Localização no Mapa")."\"></a>";
				} else {
					$pagina .= "<br>".t("Este jogador está <font color=green>Online</font>.");
				}
			}
		}

		$indexconteudo = janeladabusca("<center>".$pagina."</center>");
		$valorlib = 1; // para a index.php não incluir o lib.php de novo.
		include('index.php');
		die();
	}

	// Formulário (o botão fica escondido: o jogador só aperta Enter).
	$nomebotao = "botaobusca";
	$indexconteudo = "<center><form action=\"jutsudebusca.php?do=usar\" method=\"post\">
".t("Qual jogador você quer procurar? CH: 30.")."<br><input type=\"submit\" id=\"$nomebotao\" name=\"submit\" value=\"\" style=\"height:5px;\"><br>
".t("Nome do Jogador:")."<br> <input type=\"text\" name=\"nomedaprocura\">

</form></center><script type=\"text/javascript\" language=\"JavaScript\">sumirbotao('".$nomebotao."');sumirbotao('".$nomebotao."');</script>";
	$indexconteudo = janeladabusca($indexconteudo);
	$valorlib = 1; // para a index.php não incluir o lib.php de novo.
	include('index.php');
	die();

}

?>
