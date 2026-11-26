<?php // missoes.php :: Missões obrigatórias (história principal) entregues ao Kage de cada vila.
// As missões ficam no banco (tabela missoesextras, categoria 'historia', ordem pela coluna etapa) e podem ser
// editadas no painel (admin_missoes.php). Este arquivo aplica as regras genéricas de cada tipo; veja o cabeçalho de
// funcoesmissoesextras.php. As únicas regras em PHP ficam em mx_ganchos() (ex.: Missão 19, Fukasaku e Shima no grupo).
//
// Formato do campo users.missao: "etapa,feitos,total,intervalo"
//   - etapa ....: número da missão atual (a missão N só é entregue quando etapa = N; etapa 22 = todas concluídas);
//   - feitos ...: quantas vezes a parte repetitiva da missão já foi feita (só nas missões "repetitivas");
//   - total ....: quantas vezes é preciso repetir (0 = missão sem parte repetitiva);
//   - intervalo : minutos de espera entre uma repetição e outra.
// Campos auxiliares:
//   - users.missaoultdata: "d/m/aaaa;hh:mm:ss" da última repetição (ou "None");
//   - users.missaoswitch: vira 1 quando o jogador conversa com o NPC da etapa (falapersonagens.php);
//   - users.ultmonstro: "nome do último monstro derrotado,vitórias seguidas contra ele" (gravado no fight.php).
// Itens de missão são procurados pelo nome exato da tabela de drops (ex.: "Força Inútil", "Alma da Névoa").
// A mochila tem 8 espaços (bp1..bp8), cada um no formato "nome,id,tipo,durabilidade" (ou "None").

include('lib.php');
$link = opendb();

include('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php'); // tempojutsu() e personagemgeral().
include_once('funcoesmissoesextras.php'); // missões da história guardadas no banco (mx_historia() etc.).

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }

if (isset($_GET["do"])) {
	$do = $_GET["do"];
	// "missaotempo" e "fazermissao" eram rotas antigas que chamavam funções inexistentes/sem parâmetros
	// (erro fatal); agora todas levam à entrega da missão atual.
	if (($do == "missao") || ($do == "missaotempo") || ($do == "fazermissao")) { missao(); }
}


function passarconteudo($cont, $townrow) { // Mostra a fala do Kage da cidade dentro do Painel de Missões e encerra a página.

	global $userrow, $valorlib;
	$valorlib = 2; // para o treinamentoequests.php não incluir o lib.php novamente.
	$cont = personagemgeral($cont, $townrow["id"], t($townrow["kage"]));
	include_once('treinamentoequests.php');
	quests($cont);
	die();

}


function itemnamochila($nome) { // Procura um item pelo nome na mochila (bp1..bp8). Retorna o número do espaço ou 0.

	global $userrow;
	$achou = 0;
	for ($i = 1; $i <= 8; $i++) {
		$parcial = explode(",", $userrow["bp".$i]);
		if ($parcial[0] == $nome) { $achou = $i; }
	}
	return $achou;

}


function itemequipado($nome) { // Procura um item pelo nome nos 4 acessórios equipados. Retorna o slot ou 0.

	global $userrow;
	for ($i = 1; $i <= NUMACESSORIOS; $i++) {
		if ($userrow["slot".$i."name"] == $nome) { return $i; }
	}
	return 0;

}


function exigiritem($nome, $missaoexplode, $townrow) { // Exige que o item esteja na mochila; retorna o espaço da mochila onde ele está.

	$bp = itemnamochila($nome);
	if ($bp > 0) { return $bp; }
	if (itemequipado($nome) > 0) { passarconteudo(tf('Primeiro coloque o item <b>%s</b> na sua mochila.', t($nome)), $townrow); }
	passarconteudo(tf('Você ainda não completou/possui o requerimento da Missão %s.', intval($missaoexplode[0])), $townrow);

}


function matouultimo($nome, $vezes = 1) { // Confere se o último monstro derrotado foi $nome (e quantas vezes seguidas).

	global $userrow;
	$ultimo = array_pad(explode(",", $userrow["ultmonstro"]), 2, "0");
	return (($ultimo[0] == $nome) && (intval($ultimo[1]) >= $vezes));

}


function concluirmissao($proxima, $ryou, $extras, $mensagem, $townrow) { // Entrega a recompensa e avança para a próxima etapa.

	global $userrow, $link;
	// Ryou é mediumint unsigned no banco (máximo 16.777.215).
	$novogold = min(intval($userrow["gold"]) + $ryou, 16777215);
	// O "AND missao=..." garante que a recompensa só é paga uma vez, mesmo com dois cliques/requisições ao mesmo tempo.
	doquery("UPDATE {{table}} SET missao='$proxima', gold='$novogold', missaoswitch='0', missaoultdata='None'".$extras." WHERE id='".intval($userrow["id"])."' AND missao='".addslashes($userrow["missao"])."' LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1) { passarconteudo(t('Essa missão já foi entregue.'), $townrow); }
	passarconteudo($mensagem, $townrow);

}


function missaorepetitiva($missaoexplode, $townrow, $ryou, $proxima, $exigenpc = false, $extras = "") { // Missões feitas várias vezes com intervalo de espera.

	global $userrow, $link;
	$numero = $missaoexplode[0];
	$feitos = intval($missaoexplode[1]);
	$total = intval($missaoexplode[2]);
	$npcok = (!$exigenpc || $userrow["missaoswitch"] == 1);
	$mensagemfim = tf('Parabéns! Você completou a Missão %s com sucesso e ganhou sua recompensa.', $numero);

	// Repetições já concluídas (acontece na Missão 12, se o jogador ainda não tinha falado com o NPC).
	if ($feitos >= $total) {
		if ($npcok) { concluirmissao($proxima, $ryou, $extras, $mensagemfim, $townrow); }
		passarconteudo(t('Você ainda não completou todo o requerimento da Missão.'), $townrow);
	}

	$novosdados = fazermissao($missaoexplode);
	$novosexplode = explode(",", $novosdados);

	if (($novosexplode[1] == $novosexplode[2]) && $npcok) {
		concluirmissao($proxima, $ryou, $extras, $mensagemfim, $townrow);
	}

	// Ainda não terminou: grava o progresso e a hora desta repetição.
	doquery("UPDATE {{table}} SET missao='$novosdados', missaoultdata='".date("j/n/Y").";".date("H:i:s")."' WHERE id='".intval($userrow["id"])."' AND missao='".addslashes($userrow["missao"])."' LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1) { passarconteudo(t('Essa parte da missão já foi registrada.'), $townrow); }
	if ($novosexplode[1] != $novosexplode[2]) {
		passarconteudo(tf('Você completou mais uma parte da Missão %s, e em breve poderá aumentar sua porcentagem de conclusão novamente.', $numero), $townrow);
	}
	passarconteudo(t('Você ainda não completou todo o requerimento da Missão.'), $townrow);

}


function fazermissao($missaorow) { // Faz mais uma repetição da missão, se o tempo de espera já passou. Retorna o novo "etapa,feitos,total,intervalo".

	global $userrow;

	if ($missaorow[1] >= $missaorow[2]) { header("Location: ./treinamentoequests.php?do=quests&conteudo=".rawurlencode(tf("Você já completou a Missão %s.", intval($missaorow[0])))); die(); }

	// Tempo de espera: tempojutsu() devolve "ok" ou quantos minutos ainda faltam.
	if ($userrow["missaoultdata"] != "None") {
		$ultimavez = array_pad(explode(";", $userrow["missaoultdata"]), 2, "0:0:0");
		$retorno = tempojutsu($ultimavez[0], $ultimavez[1], intval($missaorow[3]));
		if ($retorno !== "ok") { header("Location: ./treinamentoequests.php?do=quests&conteudo=".rawurlencode(tf("Você ainda não pode praticar/fazer a missão, é preciso aguardar %s minuto(s) até que você possa praticar/fazer a missão novamente.", $retorno))); die(); }
	}

	$missaorow[1] = intval($missaorow[1]) + 1;
	if ($missaorow[1] > $missaorow[2]) { $missaorow[1] = $missaorow[2]; }
	return intval($missaorow[0]).",".$missaorow[1].",".intval($missaorow[2]).",".intval($missaorow[3]);

}


function missao() { // Entrega da missão atual ao Kage da cidade onde o jogador está (regras lidas do banco).

	global $userrow;

	if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }
	if ($userrow["currentaction"] != "In Town") { header('Location: ./treinamentoequests.php?do=quests&conteudo='.rawurlencode(t("Você só pode acessar essa função dentro de uma cidade!"))); die(); }
	if ($userrow["batalha_timer2"] == 5) { display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "),t("Erro"),false,false,false); die(); }

	$townquery = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
	if (mysqli_num_rows($townquery) == 0) { display(t("Há um erro com sua conta, ou com os dados da cidade. Por favor tente novamente."),t("Erro")); die(); }
	$townrow = mysqli_fetch_array($townquery);

	$missaoexplode = array_pad(explode(",", $userrow["missao"]), 4, "0");
	$etapa = intval($missaoexplode[0]);

	// A missão da etapa atual (tabela missoesextras, categoria 'historia'); vila_id = cidade onde ela é entregue.
	$historia = mx_historia();
	if (!isset($historia[$etapa]) || ($townrow["id"] != $historia[$etapa]["vila_id"])) {
		passarconteudo(t('Não há missões nessa vila/cidade no momento.'), $townrow);
	}
	$m = $historia[$etapa];
	$naocompletou = tf('Você ainda não completou/possui o requerimento da Missão %s.', $etapa);
	$sucesso = tf('Parabéns! Você completou a Missão %s com sucesso!', $etapa);
	$proxima = mx_iniciomissao($etapa + 1);  // "etapa,0,repetições,intervalo" da próxima missão.
	$ryou = intval($m["recompensa_ryou"]);
	$comnpc = (intval($m["npc_id"]) > 0);    // também é preciso conversar com o NPC do mapa (users.missaoswitch = 1).
	$extras = (intval($m["recompensa_exp"]) > 0) ? ", experience=LEAST(experience+".intval($m["recompensa_exp"]).", 16777214)" : "";
	$itemrecompensa = ($m["recompensa_item"] != "") ? mx_itemcompleto($m["recompensa_item"]) : false;

	// Nível e graduação mínimos (opcionais).
	if (intval($userrow["level"]) < intval($m["nivel_minimo"])) { passarconteudo(tf("Você precisa estar no nível %s.", intval($m["nivel_minimo"])), $townrow); }
	if (!mx_graduacaook($userrow, $m["graduacao_minima"])) { passarconteudo(tf("Você precisa ser %s.", t($m["graduacao_minima"])), $townrow); }

	// Missões repetitivas (ex.: 2, 4, 8, 11, 17; a 12 também exige o NPC).
	if ($m["tipo"] == "repetir") {
		missaorepetitiva($missaoexplode, $townrow, $ryou, $proxima, $comnpc, $extras);
	}

	// Entregar item (ex.: 1, 5, 10; com NPC: 15, 18, 21). O item é consumido e o item de recompensa (se houver) fica no mesmo espaço.
	if ($m["tipo"] == "entregar_item") {
		if ($comnpc) {
			$bp = itemnamochila($m["alvo"]);
			if ($bp == 0) { passarconteudo(tf('Primeiro coloque o item <b>%s</b> na sua mochila e complete o resto do requerimento da missão.', t($m["alvo"])), $townrow); }
			if ($userrow["missaoswitch"] != 1) { passarconteudo($naocompletou, $townrow); }
		} else {
			$bp = exigiritem($m["alvo"], $missaoexplode, $townrow);
		}
		// Pilha: entrega só 1 unidade; o resto fica no espaço e a recompensa procura outro lugar (mochila ou banco).
		$texto = (string)$userrow["bp".$bp];
		if (qtditem($texto) > 1) {
			$extrarecompensa = ""; $msgrecompensa = "";
			if ($itemrecompensa !== false) { $extrarecompensa = recompensaitem($itemrecompensa, $msgrecompensa); }
			concluirmissao($proxima, $ryou, $extras.", bp".$bp."='".addslashes(itemcomqtd($texto, qtditem($texto) - 1))."'".$extrarecompensa, $sucesso.$msgrecompensa, $townrow);
		}
		concluirmissao($proxima, $ryou, $extras.", bp".$bp."='".addslashes($itemrecompensa !== false ? $itemrecompensa : "None")."'", $sucesso, $townrow);
	}

	// Demais tipos: confere o requisito; o item de recompensa vai para a mochila (ou para o banco, se ela estiver cheia).
	if ($m["tipo"] == "derrotar_monstro") { // último monstro derrotado, "quantidade" vezes seguidas (ex.: 3, 7, 13, 14, 16, 20).
		$monstro = mx_monstro($m["alvo"]);
		if (!$monstro || !matouultimo($monstro["name"], max(1, intval($m["quantidade"])))) { passarconteudo($naocompletou, $townrow); }
	} elseif ($m["tipo"] == "falar_com_npc") { // conversar com o NPC do mapa (ex.: 6, 9).
		if ($userrow["missaoswitch"] != 1) { passarconteudo($naocompletou, $townrow); }
	} elseif ($m["tipo"] == "pagar_ryou") {
		if (intval($userrow["gold"]) < intval($m["quantidade"])) { passarconteudo($naocompletou, $townrow); }
		$ryou -= intval($m["quantidade"]);
	} elseif ($m["tipo"] == "especial") { // regra em PHP (ex.: 19), veja mx_ganchos().
		$ganchos = mx_ganchos();
		if (!isset($ganchos[$m["gancho"]]) || !$ganchos[$m["gancho"]]($userrow)) { passarconteudo($naocompletou, $townrow); }
	} else {
		passarconteudo(t('Não há missões nessa vila/cidade no momento.'), $townrow);
	}
	if ($comnpc && $m["tipo"] != "falar_com_npc" && $userrow["missaoswitch"] != 1) { passarconteudo($naocompletou, $townrow); }
	$msg = "";
	if ($itemrecompensa !== false) { $extras .= recompensaitem($itemrecompensa, $msg); }
	concluirmissao($proxima, $ryou, $extras, $sucesso.$msg, $townrow);

}


function recompensaitem($item, &$msg) { // Coloca o item de recompensa no primeiro espaço livre da mochila ou, se ela estiver cheia, no banco.

	global $userrow;
	$msg = "";
	for ($i = 1; $i <= 8; $i++) {
		if ($userrow["bp".$i] == "None") { return ", bp".$i."='".addslashes($item)."'"; }
	}
	// Banco: lista "nome,id,tipo,durabilidade;" repetida, ou "None" quando vazio.
	$banco = ($userrow["bancogeral"] == "None") ? "" : $userrow["bancogeral"];
	$nome = explode(",", $item);
	$msg = " ".tf("O item <b>%s</b> foi transferido para seu banco, uma vez que sua mochila já está cheia.", t($nome[0]));
	return ", bancogeral='".addslashes($banco.$item.";")."'";

}

?>
