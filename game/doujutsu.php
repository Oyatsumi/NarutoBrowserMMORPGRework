<?php // doujutsu.php :: Doujutsu (técnicas oculares). Regras: funcoesdoujutsu.php e docs/DOUJUTSU.md.
//
// Rotas:
//   (vazio) / do=ver ....: tela do olho (bônus, custo, evolução do Sharingan, a escada de olhos e o teleporte do Rinnegan);
//   do=ativar ...........: abre o olho (precisa de Chakra >= custo e do Senjutsu desativado);
//   do=desativar ........: fecha o olho (cobrando o Chakra do tempo em que ficou aberto);
//   do=teleporte (POST) .: Rinnegan ativo: vai para a coordenada digitada (custa TP, CH e HP; fora de lutas e duelos);
//   do=usar / do=cancelar: atalho antigo do olho do Senjutsu (repassado ao senjutsu.php).

$doujutsurota = isset($_GET["do"]) ? (string)$_GET["do"] : "";
if ($doujutsurota == "usar" || $doujutsurota == "cancelar") {
	$somenteolho = true; // o senjutsu.php recusa as outras rotas (jutsu, aprendendo2, chamar) quando vem daqui.
	include('senjutsu.php');
	header('Location: ./index.php'); die();
}

include_once('lib.php');
$link = opendb();
include_once('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php');
include_once('funcoesdoujutsu.php');

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));

if ($doujutsurota == "ativar") { doujutsuativar(); }
elseif ($doujutsurota == "desativar") { doujutsudesativar(); }
elseif ($doujutsurota == "teleporte") { doujutsuteleporte(); }
doujutsuver(stripslashes(lermsg("conteudo")));


function doujutsuvoltar($msg = "") { // Volta para a última página do jogo (users.pagina) com uma mensagem.
	global $userrow;
	$pagina = (string)$userrow["pagina"];
	if (!preg_match("/^[a-z0-9_]+\.php(\?[^\r\n]*)?$/i", $pagina) || strpos($pagina, "doujutsu.php") === 0 || strpos($pagina, "senjutsu.php") === 0) { $pagina = "index.php"; }
	if ($msg != "") { $pagina = preg_replace("/([?&])(ms_)?conteudo=[^&]*&?/", "$1", preg_replace("/([?&])(ms_)?conteudo=[^&]*&?/", "$1", $pagina)); $pagina = rtrim($pagina, "?&"); $pagina .= ((strpos($pagina, "?") === false) ? "?" : "&")."conteudo=".rawurlencode($msg); }
	header('Location: ./'.$pagina); die();
}


function doujutsuativar() { // Abre o olho. Atômico: "AND doujutsu_ativo='0' AND senjutsuhtml<>'senjutsu' AND currentmp>=custo".
	global $userrow, $link;
	$olho = doujutsudojogador($userrow);
	if (!$olho) { doujutsuvoltar(t("Você ainda não despertou nenhum doujutsu.")); }
	if (intval($userrow["doujutsu_ativo"]) == 1) { doujutsucobrar(); doujutsuvoltar(); }
	if ($userrow["senjutsuhtml"] == "senjutsu") { senjutsu(); } // cobra o NP e fecha o modo sábio se o NP acabou.
	if ($userrow["senjutsuhtml"] == "senjutsu") { doujutsuvoltar(t("Desative o Senjutsu antes de abrir o doujutsu: os dois não podem ficar ativos ao mesmo tempo.")); }
	$custo = max(1, doujutsucustonivel($olho, $userrow["level"]));
	if (intval($userrow["currentmp"]) < $custo) { doujutsuvoltar(tf("Você precisa de ao menos %s de Chakra para ativar o %s.", $custo, doujutsunome($olho))); }
	doquery("UPDATE {{table}} SET doujutsu_ativo='1', doujutsu_timer='".time()."' WHERE id='".intval($userrow["id"])."' AND doujutsu<>'' AND doujutsu_ativo='0' AND senjutsuhtml<>'senjutsu' AND currentmp>='$custo' LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1) { doujutsuvoltar(t("Não foi possível ativar o doujutsu agora.")); }
	doujutsuvoltar();
}


function doujutsudesativar() { // Fecha o olho, cobrando o Chakra do tempo em que ficou aberto.
	global $userrow;
	if (intval($userrow["doujutsu_ativo"]) != 1) { doujutsuvoltar(t("O doujutsu já está desativado.")); }
	doujutsucobrar(); // cobra (e talvez já feche por falta de Chakra).
	doquery("UPDATE {{table}} SET doujutsu_ativo='0', doujutsu_timer='0' WHERE id='".intval($userrow["id"])."' AND doujutsu_ativo='1' LIMIT 1", "users");
	doujutsuvoltar();
}


function doujutsuteleporte() { // Teleporte do Rinnegan (POST lat/lon). Trava o jogador, confere tudo de novo e grava numa UPDATE condicionada.
	global $userrow, $controlrow, $link;
	if (!isset($_SERVER["REQUEST_METHOD"]) || $_SERVER["REQUEST_METHOD"] != "POST") { header('Location: ./doujutsu.php'); die(); }
	if (!csrf_confere()) { doujutsuver(t("Token de segurança inválido ou expirado. Recarregue a página e tente novamente.")); }
	$lat = pegarint($_POST, "lat"); $lon = pegarint($_POST, "lon");
	$limite = intval($controlrow["gamesize"]);

	mysqli_begin_transaction($link);
	$userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
	$erro = "";
	doujutsucobrar(); // Chakra do tempo aberto (pode fechar o olho).
	$olho = doujutsudojogador($userrow);
	if ($userrow["currentaction"] == "Fighting") { $erro = t("Você não pode se teleportar no meio de uma batalha!"); }
	elseif (intval($userrow["batalha_timer2"]) == 5) { $erro = t("Você não pode se teleportar durante um duelo."); }
	elseif (!$olho || intval($olho["teleporte"]) != 1) { $erro = t("Só o Rinnegan permite o teleporte."); }
	elseif (!doujutsuativo($userrow)) { $erro = t("Ative o Rinnegan antes de se teleportar."); }
	elseif (function_exists('bloqueiovila') && bloqueiovila($userrow) != "") { $erro = bloqueiovila($userrow)." ".t("O Rinnegan também não abre portais enquanto a caveira está exposta."); } // PvP: não serve para fugir (docs/PVP.md).
	elseif (abs($lat) > $limite || abs($lon) > $limite) { $erro = tf("Coordenada fora do mapa (de -%s a %s).", $limite, $limite); }
	elseif ($lat == intval($userrow["latitude"]) && $lon == intval($userrow["longitude"])) { $erro = t("Você já está nessa coordenada."); }
	elseif (mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE latitude='$lat' AND longitude='$lon' LIMIT 1", "towns")) > 0) { $erro = t("O Rinnegan não abre portais dentro de vilas: escolha uma casa vizinha e entre andando."); }
	$custo = doujutsucustoteleporte($userrow, $lat, $lon);
	if ($erro == "" && intval($controlrow["dj_tele_max"]) > 0 && $custo["dist"] > intval($controlrow["dj_tele_max"])) { $erro = tf("Distância máxima do teleporte: %s casas.", intval($controlrow["dj_tele_max"])); }
	elseif ($erro == "" && intval($userrow["currenttp"]) < $custo["tp"]) { $erro = tf("O teleporte precisa de %s TP e você tem %s.", $custo["tp"], intval($userrow["currenttp"])); }
	elseif ($erro == "" && intval($userrow["currentmp"]) < $custo["ch"]) { $erro = tf("O teleporte precisa de %s de Chakra e você tem %s.", $custo["ch"], intval($userrow["currentmp"])); }
	elseif ($erro == "" && intval($userrow["currenthp"]) <= $custo["hp"]) { $erro = tf("O teleporte custa %s HP e você precisa ficar com pelo menos 1.", $custo["hp"]); }
	if ($erro != "") { mysqli_commit($link); doujutsuver($erro); } // commit: guarda só a cobrança do Chakra do olho.

	doquery("UPDATE {{table}} SET latitude='$lat', longitude='$lon', currentaction='Exploring', passo_jutsu='0', currenttp=currenttp-".$custo["tp"].", currentmp=currentmp-".$custo["ch"].", currenthp=currenthp-".$custo["hp"]." WHERE id='".intval($userrow["id"])."' AND currentaction<>'Fighting' AND doujutsu='rinnegan' AND doujutsu_ativo='1' AND currenttp>='".$custo["tp"]."' AND currentmp>='".$custo["ch"]."' AND currenthp>'".$custo["hp"]."' LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1) { mysqli_rollback($link); doujutsuver(t("Não foi possível se teleportar agora.")); }
	mysqli_commit($link);
	if (file_exists('funcoesneblina.php')) { include_once('funcoesneblina.php'); neblina_descobrir($userrow, $lat, $lon); }
	header('Location: ./index.php?conteudo='.rawurlencode(tf("O Rinnegan dobrou o espaço e levou você para %s,%s (%s casas). -%s TP, -%s CH, -%s HP.", $lat, $lon, $custo["dist"], $custo["tp"], $custo["ch"], $custo["hp"])));
	die();
}


function doujutsuver($aviso = "") { // Tela do doujutsu.
	global $userrow, $controlrow;
	doujutsucobrar();
	include_once('funcoestreinos.php');
	$olho = doujutsudojogador($userrow);
	$e = doujutsuestado($userrow);
	$page = "<h3>".t("Doujutsu (técnicas oculares)")."</h3>";
	if ($aviso != "") { $page .= "<div style=\"border:1px solid #9b1b10;background:#fde8d8;padding:5px;margin-bottom:8px\" id=\"aviso\">".$aviso."</div>"; }
	if (!$olho) {
		$page .= "<p>".t("Você ainda não despertou nenhum doujutsu. Os olhos são despertados em treinos longos com mestres de algumas vilas (veja a lista abaixo).")."</p>";
	} else {
		$link = ($e["ativo"]) ? "<a href=\"doujutsu.php?do=desativar\">".t("Desativar")."</a>" : "<a href=\"doujutsu.php?do=ativar\">".t("Ativar")."</a>";
		$page .= "<center><img src=\"images/olhos/".htmlspecialchars($olho["imagem"]).".jpg\" alt=\"\" style=\"".($e["ativo"] ? "" : "opacity:.45")."\" /><br><b>".doujutsunome($olho)."</b> — ".($e["ativo"] ? "<font color=\"darkgreen\">".t("ativo")."</font>" : t("desativado"))." ($link)</center>";
		$page .= "<p><b>".t("Bônus enquanto ativo:")."</b> ".implode(", ", doujutsubonustexto($olho)).".<br><b>".t("Custo:")."</b> ".doujutsucustotexto($olho, $userrow["level"]).". ".($e["ativo"] ? tf("Ainda fica ativo por cerca de %s (com %s de Chakra).", duracaotexto(doujutsuduracao($olho, $e["mp"], $userrow["level"])), $e["mp"]) : tf("Com o Chakra cheio (%s) fica ativo por cerca de %s.", intval($userrow["maxmp"]), duracaotexto(doujutsuduracao($olho, $userrow["maxmp"], $userrow["level"]))))." ".t("O olho fecha sozinho quando o Chakra acaba e não pode ficar ativo junto com o Senjutsu.")."</p>";
		$p = doujutsuprogresso($userrow);
		if ($p) {
			$linhas = array();
			if ($p["abates"][1] > 0) { $linhas[] = tf("abates de inimigos fortes com o Sharingan ativo: %s/%s", $p["abates"][0], $p["abates"][1]); }
			if ($p["vitorias"][1] > 0) { $linhas[] = tf("vitórias em duelos: %s/%s", $p["vitorias"][0], $p["vitorias"][1]); }
			$linhas[] = t("ou concluir o treino Evolução do Sharingan (Vila da Folha)");
			$page .= "<p><b>".tf("Evolução para o estágio %s (qualquer caminho):", $p["proximo"])."</b><br>— ".implode("<br>— ", $linhas)."</p>";
		}
		if (intval($olho["teleporte"]) == 1) {
			$c = $controlrow;
			$page .= "<h3>".t("Teleporte do Rinnegan")."</h3><p>".tf("Você está em %s,%s. Custo: %s TP + %s por 10 casas, %s CH + %s por 10 casas e %s%% do HP máximo + %s%% por 10 casas. Só com o Rinnegan ativo, fora de lutas e duelos; não abre portais dentro de vilas.", intval($userrow["latitude"]), intval($userrow["longitude"]), intval($c["dj_tele_tpbase"]), intval($c["dj_tele_tp10"]), intval($c["dj_tele_chbase"]), intval($c["dj_tele_ch10"]), intval($c["dj_tele_hpbase"]), intval($c["dj_tele_hp10"]))."</p>"
				."<form action=\"doujutsu.php?do=teleporte\" method=\"post\">".csrf_campo().t("Latitude:")." <input type=\"number\" name=\"lat\" value=\"".intval($userrow["latitude"])."\" style=\"width:70px\" /> ".t("Longitude:")." <input type=\"number\" name=\"lon\" value=\"".intval($userrow["longitude"])."\" style=\"width:70px\" /> <input class=\"btnjogo bj-confirmar\" type=\"submit\" value=\"".t("Teleportar")."\" /></form>";
		}
	}
	// Escada dos olhos: onde despertar e requisitos (do mais fácil ao mais difícil).
	$page .= "<h3>".t("Os olhos")."</h3><table width=\"100%\" style=\"font-size:11px\"><tr><td></td><td><b>".t("Olho")."</b></td><td><b>".t("Bônus enquanto ativo")."</b></td><td><b>".t("Onde despertar")."</b></td></tr>";
	$treinos = treinoslista();
	foreach (doujutsulista() as $o) {
		$tr = isset($treinos["olho_".$o["chave"]]) ? $treinos["olho_".$o["chave"]] : false;
		if ((string)$o["chave"] == "sharingan" && intval($o["estagio"]) > 1) { $onde = tf("Evolução do estágio %s", intval($o["estagio"]) - 1); }
		else { $onde = $tr ? tf("%s com %s (%s sessões, %s min). ", t($tr["nome"]), t($tr["mestre"]), intval($tr["sessoes"]), intval($tr["intervalo"])).treinonomevila($tr["vila_id"]).". ".treinorequisitostexto($tr) : "-"; }
		$page .= "<tr><td><img src=\"images/olhos/".htmlspecialchars($o["imagem"]).".jpg\" width=\"82\" height=\"32\" alt=\"\" /></td><td>".t($o["nome"])."<br><span style=\"color:#613003\">".doujutsucustotexto($o, $userrow["level"])."<br>".tf("dura ~%s com Chakra cheio", duracaotexto(doujutsuduracao($o, $userrow["maxmp"], $userrow["level"])))."</span></td><td>".implode(", ", doujutsubonustexto($o))."</td><td>$onde</td></tr>";
	}
	// Senjutsu (senjutsu.php): não é um doujutsu, mas usa o mesmo lugar do olho; aparece na lista só para consulta.
	$page .= "<tr><td><img src=\"images/olhos/senjutsu.jpg\" width=\"82\" height=\"32\" alt=\"\" /></td><td>".t("Senjutsu (modo sábio)")."<br><span style=\"color:#613003\">".t("1 NP a cada 3 s")."<br>".tf("dura ~%s com NP cheio", duracaotexto(intval($userrow["maxnp"]) * (3 + intval(isset($userrow["treino_sjlento"]) ? $userrow["treino_sjlento"] : 0))))."</span></td><td>".t("bônus da especialização, que cresce com o nível. No seu nível:")." ".implode(", ", senjutsubonustexto(intval($userrow["charclass"]), intval($userrow["level"])))."</td><td>".t("12 treinos na Montanha Myoboku (Genin ou acima). Não é um doujutsu e não fica ativo junto com um olho.")."</td></tr>";
	$page .= "</table><p>".t("Você só pode ter um olho: despertar outro substitui o atual (o treino de despertar pode ser refeito para voltar a um olho antigo).")."</p>";
	display($page, t("Doujutsu"));
	die();
}

?>
