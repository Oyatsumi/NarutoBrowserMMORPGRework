<?php // backpack.php :: Usa um item da mochila (backpack.php?qual=N, N de 1 a 8): poções recuperam HP/CH e equipamentos são trocados com o que está equipado.

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php');
if (!isset($controlrow) || !is_array($controlrow)) { $controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control")); } // custos de TP dos pergaminhos.

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
// Durante um duelo PvP as poções só podem ser usadas na própria vez, pela tela do duelo (users.php?do=duelo).
if ($userrow["batalha_timer2"] == 5) { header('Location: ./users.php?do=duelo&conteudo='.rawurlencode(t('Durante um duelo, use as poções pela tela do duelo, na sua vez.'))); die(); }

// Trava o jogador até o fim da página (SELECT ... FOR UPDATE): este arquivo regrava a mochila e os equipamentos
// inteiros, então outra ação ao mesmo tempo (guardar no banco, desequipar, trocar) usaria dados velhos e o mesmo
// item podia ficar em dois lugares. A outra requisição espera o COMMIT e já lê a mochila nova.
// Qualquer die() antes do COMMIT fecha a conexão e o MariaDB desfaz a transação.
doquery("START TRANSACTION", "users");
$userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));

// Slot da mochila (bp1..bp8).
$qual = pegarint($_GET, "qual");
if ($qual < 1 || $qual > 8) { header('Location: ./index.php'); die(); }
$variavelbp = "bp".$qual;
$itemoriginal = $userrow[$variavelbp];
if ($itemoriginal == "None") { header('Location: ./index.php'); die(); }
// Link da mochila com a marca do item (v=marcaitem()): se o espaço já tem outro item (ex.: duplo clique, o primeiro
// clique trocou o item do espaço pelo que estava equipado), não faz nada. Sem "v" (links antigos) segue como antes.
if (isset($_GET["v"]) && (string)$_GET["v"] !== marcaitem($itemoriginal)) { doquery("ROLLBACK", "users"); header('Location: '.paginaderetorno()); die(); }

// Formato do slot: "nome,id,tipo,durabilidade[,quantidade]" (ver funcoesinclusas.php).
// Numa pilha, usar consome só 1 unidade: o espaço fica com a quantidade - 1 (ou "None" quando era a última).
$itemseparadobp = array_pad(explode(",", $itemoriginal), 4, "X");
$restante = itemcomqtd($itemoriginal, qtditem($itemoriginal) - 1);
$slots = slotsequipamento(); // 1 arma, 2 colete, 3 bandana, 4-7 acessórios 1 a 4.

// Limite de poções por luta (control.limitepocoesluta usos de cada tipo; users.pocoesluta zera no início da luta, explore.php).
// O jogador está travado (FOR UPDATE) e a lista é gravada no mesmo UPDATE da mochila: cliques paralelos não passam do limite.
if ($userrow["currentaction"] == "Fighting" && in_array($itemseparadobp[1], array("hp", "mp", "tp", "np", "ep"), true)) {
	$tipopocao = pocaotipo($itemoriginal);
	if (pocaobloqueada($userrow["pocoesluta"], $tipopocao)) { doquery("ROLLBACK", "users"); header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t('Você já usou essa poção o máximo de vezes permitido nesta luta.'))); die(); }
	$userrow["pocoesluta"] = pocaoanotar($userrow["pocoesluta"], $tipopocao);
}

if ($itemseparadobp[1] == "bomba") { // Ferramenta ninja: só se usa arremessando na tela da luta/duelo.
	doquery("ROLLBACK", "users");
	header('Location: ./'.($userrow["currentaction"] == "Fighting" ? 'index.php?do=fight&' : 'index.php?').'conteudo='.rawurlencode(t('Ferramentas ninja são arremessadas na tela da batalha ou do duelo (gastam o turno).'))); die();
}
if ($itemseparadobp[1] == "hp"){ // Poção de vida: o 3º campo é quanto de HP ela recupera.
	$userrow["currenthp"] = min(intval($userrow["maxhp"]), intval($userrow["currenthp"]) + intval($itemseparadobp[2]));
	$userrow[$variavelbp] = $restante;
}
elseif ($itemseparadobp[1] == "mp"){ // Poção de chakra.
	$userrow["currentmp"] = min(intval($userrow["maxmp"]), intval($userrow["currentmp"]) + intval($itemseparadobp[2]));
	$userrow[$variavelbp] = $restante;
}
elseif (in_array($itemseparadobp[1], array("tp", "np", "ep"), true)){ // Poções pequenas de TP, NP e EP (drops 433-435): mesma regra das de HP/CH.
	$campo = $itemseparadobp[1];
	$userrow["current".$campo] = min(intval($userrow["max".$campo]), intval($userrow["current".$campo]) + intval($itemseparadobp[2]));
	$userrow[$variavelbp] = $restante;
}
elseif ($itemseparadobp[1] == "jutsu"){ // Pergaminho de jutsu ("nome,jutsu,ID do jutsu,1"): ensina o jutsu se o jogador cumprir os requisitos e some.
	$idjutsu = intval($itemseparadobp[2]);
	$jutsurow = ($idjutsu > 0) ? mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$idjutsu' LIMIT 1", "spells")) : false;
	if (!$jutsurow) { header('Location: ./index.php?conteudo='.rawurlencode(t('Este pergaminho está ilegível: o jutsu não existe mais.'))); die(); }
	if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t('Você não pode estudar um pergaminho no meio de uma batalha!'))); die(); }
	// Já conhece: o pergaminho continua na mochila (pode ser trocado ou guardado no banco).
	if (jutsuconhece($userrow, $idjutsu)) { header('Location: ./index.php?conteudo='.rawurlencode(tf('Você já conhece o jutsu %s. Guarde o pergaminho ou troque-o com outro jogador.', t($jutsurow["name"])))); die(); }
	$erro = jutsurequisitos($jutsurow, $userrow);
	if ($erro != "") { header('Location: ./index.php?conteudo='.rawurlencode($erro)); die(); }
	// Aprende e consome o pergaminho na mesma transação (o jogador está travado pelo FOR UPDATE acima).
	doquery("UPDATE {{table}} SET $variavelbp='".addslashes($restante)."' WHERE id='".intval($userrow["id"])."' AND $variavelbp='".addslashes($itemoriginal)."' LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1 || !jutsuaprender($userrow["id"], $idjutsu)) { doquery("ROLLBACK", "users"); header('Location: ./index.php'); die(); }
	doquery("COMMIT", "users");
	header('Location: ./index.php?conteudo='.rawurlencode(tf('Você estudou o pergaminho e aprendeu o jutsu %s!', t($jutsurow["name"]))));
	die();
}
elseif ($itemseparadobp[1] == "banco"){ // Expansão do banco ("nome,banco,ID do drop,X"): users.banco_limite passa a drops.banco_limite (só aumenta).
	if ($userrow["currentaction"] == "Fighting") { doquery("ROLLBACK", "users"); header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t('Você não pode usar esse item no meio de uma batalha!'))); die(); }
	$dr = mysqli_fetch_array(doquery("SELECT name, banco_limite FROM {{table}} WHERE id='".intval($itemseparadobp[2])."' LIMIT 1", "drops"));
	$novo = $dr ? min(50, intval($dr["banco_limite"])) : 0;
	if ($novo <= 30) { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(t('Esse item não pode ser usado pela mochila.'))); die(); }
	// Não gasta o item se o banco já tem esse tamanho ou mais (dá para guardar, trocar ou vender).
	if (intval($userrow["banco_limite"]) >= $novo) { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(tf('Seu banco já guarda %s itens; este item não aumenta mais.', intval($userrow["banco_limite"])))); die(); }
	doquery("UPDATE {{table}} SET $variavelbp='".addslashes($restante)."', banco_limite='$novo' WHERE id='".intval($userrow["id"])."' AND $variavelbp='".addslashes($itemoriginal)."' LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1) { doquery("ROLLBACK", "users"); header('Location: ./index.php'); die(); }
	doquery("COMMIT", "users");
	header('Location: ./index.php?conteudo='.rawurlencode(tf('Você usou %s: agora o seu banco guarda %s itens.', t($dr["name"]), $novo)));
	die();
}
elseif ($itemseparadobp[1] == "descanso"){ // Item de descanso ("nome,descanso,ID do drop,X"): multiplica o ganho do Descansar por algumas horas.
	if ($userrow["currentaction"] == "Fighting") { doquery("ROLLBACK", "users"); header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t('Você não pode usar esse item no meio de uma batalha!'))); die(); }
	$dr = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($itemseparadobp[2])."' LIMIT 1", "drops"));
	if (!$dr || intval($dr["descanso_mult"]) <= 100) { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(t('Esse item não pode ser usado pela mochila.'))); die(); }
	$novomult = intval($dr["descanso_mult"]); $horas = max(2, intval($dr["descanso_horas"]));
	// Um efeito de cada vez: um mais forte ainda ativo não é trocado por um mais fraco (o item não é gasto).
	if (descansomult($userrow) > $novomult) { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(tf('Você já tem um efeito de descanso mais forte ativo (×%s).', descansomulttexto(descansomult($userrow))))); die(); }
	doquery("UPDATE {{table}} SET $variavelbp='".addslashes($restante)."', descanso_mult='$novomult', descanso_ate='".(time() + 3600 * $horas)."' WHERE id='".intval($userrow["id"])."' AND $variavelbp='".addslashes($itemoriginal)."' LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1) { doquery("ROLLBACK", "users"); header('Location: ./index.php'); die(); }
	doquery("COMMIT", "users");
	header('Location: ./index.php?conteudo='.rawurlencode(tf('Você usou %s: tudo o que você recupera ao descansar fica ×%s por %s horas.', t($dr["name"]), descansomulttexto($novomult), $horas)));
	die();
}
elseif ($itemseparadobp[1] == "ret" || $itemseparadobp[1] == "rep" || $itemseparadobp[1] == "tel"){ // Pergaminho de Retorno, Incenso Repelente (loja) e Pergaminho de Teleporte Aleatório (drop).
	// Fora de batalhas: a luta contra monstros prende o jogador (o duelo já foi barrado acima; o PvP do mundo é resolvido na hora).
	if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t('Você não pode usar esse item no meio de uma batalha!'))); die(); }
	// Caveira exposta (lib.php :: bloqueiovila()): os pergaminhos que levam para uma vila não funcionam (o pergaminho não é gasto).
	if ($itemseparadobp[1] != "rep" && bloqueiovila($userrow) != "") { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(bloqueiovila($userrow))); die(); }
	if ($itemseparadobp[1] == "ret") {
		// Volta para a vila natal do alinhamento (a mesma da morte), consumindo o pergaminho na mesma gravação.
		include_once('cidadesconteudo.php');
		$vila = vilanataldoalinhamento($userrow["alinhamento"]);
		if (!$vila) { header('Location: ./index.php'); die(); }
		$towns = $userrow["towns"];
		if (!in_array(intval($vila["id"]), array_map('intval', explode(",", (string)$towns))) && strlen($towns.",".intval($vila["id"])) <= 255) { $towns .= ",".intval($vila["id"]); }
		// Custa TP (custotpretorno(): control.tpretornopct % do TP da viagem até a vila natal, mínimo control.tpretorno),
		// para não ficar mais barato que o "Viajar Para". O "currenttp>=" no WHERE abaixo impede pagar duas vezes.
		$custotp = custotpretorno($vila);
		if (intval($userrow["currenttp"]) < $custotp) { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(tf('O Pergaminho de Retorno precisa de %s TP e você só tem %s.', $custotp, intval($userrow["currenttp"])))); die(); }
		$sets = "currentaction='In Town', towns='".addslashes($towns)."', latitude='".intval($vila["latitude"])."', longitude='".intval($vila["longitude"])."', currenttp=currenttp-$custotp, passo_jutsu='0'";
		$extraonde = " AND currenttp>=$custotp";
		$msg = tf('Você abriu o Pergaminho de Retorno e voltou para %s.', t($vila["name"]))." ".tf("(-%s TP)", $custotp);
	} elseif ($itemseparadobp[1] == "tel") {
		// Teleporte Aleatório: sorteia entre as vilas que o jogador já conhece (users.towns), menos a vila onde ele está
		// e a vila natal (para isso existe o Pergaminho de Retorno). Sem destino possível o pergaminho não é gasto.
		include_once('cidadesconteudo.php');
		$natal = vilanataldoalinhamento($userrow["alinhamento"]);
		$conhecidas = array_filter(array_map('intval', explode(",", (string)$userrow["towns"])));
		$opcoes = array();
		// Qualquer vila serve, inclusive as que o jogador ainda não conhece (a graça do pergaminho); vilas secretas ficam de fora.
		$q = doquery("SELECT id,name,latitude,longitude FROM {{table}} ORDER BY id", "towns");
		while ($v = mysqli_fetch_array($q)) {
			if (cidadesecreta(intval($v["id"]))) { continue; }
			if ($natal && intval($v["id"]) == intval($natal["id"])) { continue; }
			if (intval($v["latitude"]) == intval($userrow["latitude"]) && intval($v["longitude"]) == intval($userrow["longitude"])) { continue; }
			$opcoes[] = $v;
		}
		if (count($opcoes) == 0) { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(t('O pergaminho não encontrou nenhuma vila para onde levar você (a vila atual e a vila natal não contam).'))); die(); }
		$custotp = custotpteleporte();
		if (intval($userrow["currenttp"]) < $custotp) { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(tf('O Pergaminho de Teleporte Aleatório precisa de %s TP e você só tem %s.', $custotp, intval($userrow["currenttp"])))); die(); }
		$vila = $opcoes[array_rand($opcoes)];
		$descobriu = !in_array(intval($vila["id"]), $conhecidas); // só avisa; o mapa da vila continua sendo comprado na loja
		$sets = "currentaction='In Town', latitude='".intval($vila["latitude"])."', longitude='".intval($vila["longitude"])."', currenttp=currenttp-$custotp, passo_jutsu='0'";
		$extraonde = " AND currenttp>=$custotp";
		$msg = tf('O selo girou e o Pergaminho de Teleporte Aleatório levou você para %s!', t($vila["name"]))." ".tf("(-%s TP)", $custotp);
		if ($descobriu) { $msg .= " ".t("Você chegou a uma vila que ainda não conhecia!"); }
	} else {
		// Incenso: renova (não soma) os passos e o prazo.
		$sets = "repelente_passos='".INCENSO_PASSOS."', repelente_ate='".(time() + 60 * INCENSO_MINUTOS)."'";
		$msg = tf('Você acendeu o Incenso Repelente: nenhum inimigo aleatório por %s passos ou %s minutos.', INCENSO_PASSOS, INCENSO_MINUTOS);
	}
	// O jogador está travado (FOR UPDATE) e o "AND bpN=..." confere que o espaço ainda tem o mesmo item.
	if (!isset($extraonde)) { $extraonde = ""; }
	doquery("UPDATE {{table}} SET $variavelbp='".addslashes($restante)."', $sets WHERE id='".intval($userrow["id"])."' AND $variavelbp='".addslashes($itemoriginal)."' AND currentaction<>'Fighting'$extraonde LIMIT 1", "users");
	if (mysqli_affected_rows($link) != 1) { doquery("ROLLBACK", "users"); header('Location: ./index.php'); die(); }
	doquery("COMMIT", "users");
	header('Location: ./index.php?conteudo='.rawurlencode($msg));
	die();
}
elseif (!is_numeric($itemseparadobp[1])){ // Outros consumíveis (diamante, pergaminho...) são usados em outras telas.
	header('Location: ./index.php?conteudo='.rawurlencode(t('Esse item não pode ser usado pela mochila.'))); die();
}
else { // Equipamento.

	if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t('Você não pode equipar um item no meio de uma batalha!'))); die(); }

	$tipo = intval($itemseparadobp[2]);
	// Selo de Viagem (control.viagemseloitem): não é equipável; fica na mochila e é gasto pelo "Viajar Para" (towns.php).
	if ($tipo >= 4 && intval($itemseparadobp[1]) == iddoselo()) { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(t('O Selo de Viagem fica na mochila: ele é gasto ao usar o Viajar Para para ir a uma vila que não é a sua vila natal.'))); die(); }
	if ($tipo < 1 || $tipo > 6) { display(t("Tentativa de trapaça ou erro detectado."),t("Erro"),false,false,false); die(); }

	// Confere se o item existe e descobre o slot certo.
	// Arma/colete/bandana: o tipo verdadeiro vem da tabela items (não confia só no texto da mochila); o item novo
	// troca de lugar com o que estiver equipado naquele slot.
	// Drops (tipo 4, 5 ou 6): vão para o PRIMEIRO acessório vazio (slot1 a slot4) e nada sai.
	// Só com os 4 cheios os slots andam (slot1 -> slot2 -> slot3 -> slot4), o novo entra no slot1 e o que estava no slot4
	// (o mais antigo) volta para a mochila. Antes o drop sempre empurrava os slots, mesmo com um slot vazio:
	// com [vazio, A, B] o B saía sem necessidade.
	$anda = false;
	if ($tipo <= 3) {
		$novoquery = doquery("SELECT type FROM {{table}} WHERE id='".intval($itemseparadobp[1])."' LIMIT 1", "items");
		$novorow = mysqli_fetch_array($novoquery);
		if (!$novorow || $novorow["type"] < 1 || $novorow["type"] > 3) { display(t("Tentativa de trapaça ou erro detectado."),t("Erro"),false,false,false); die(); }
		$tipo = intval($novorow["type"]);
		$saida = $tipo;
		$novaposicao = $tipo;
	} else {
		$novoquery = doquery("SELECT id FROM {{table}} WHERE id='".intval($itemseparadobp[1])."' LIMIT 1", "drops");
		if (mysqli_num_rows($novoquery) == 0) { display(t("Tentativa de trapaça ou erro detectado."),t("Erro"),false,false,false); die(); }
		$tipo = 4;
		$novaposicao = 0;
		for ($s = 4; $s <= 3 + NUMACESSORIOS; $s++) { if ($userrow[$slots[$s]."name"] == "None" || $userrow[$slots[$s]."name"] == "") { $novaposicao = $s; break; } }
		if ($novaposicao == 0) { $anda = true; $saida = 3 + NUMACESSORIOS; $novaposicao = 4; }
		else { $saida = $novaposicao; } // slot vazio: nada sai.
	}
	$campo = $slots[$saida];
	$durab = lerdurabilidade($userrow["durabilidade"]);

	// 1) Retira o item que sai do slot (se houver) e o coloca no lugar do novo, na mochila.
	//    Se o novo vinha de uma pilha, o espaço fica com o resto da pilha e o item que sai procura outro lugar
	//    (outra pilha igual ou um espaço livre); sem lugar, nada é equipado.
	//    Na mochila o drop volta com o tipo 4 (como fight.php/loja gravam), não 5/6: senão não empilha com os iguais.
	$userrow[$variavelbp] = $restante;
	if ($userrow[$campo."name"] != "None" && $userrow[$campo."name"] != ""){
		$sai = $userrow[$campo."name"].",".intval($userrow[$campo."id"]).",".($saida <= 3 ? $saida : 4).",".$durab[$saida];
		// Espaço liberado (não era pilha): o que sai fica nele, na mesma posição; se o que sai empilha, junta antes
		// numa pilha igual (não espalha unidades soltas pela mochila).
		if ($restante == "None" && pilhamaxima($sai) <= 1) { $userrow[$variavelbp] = $sai; }
		elseif (mochilaguardar($userrow, $sai) === false) { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(t('Sua mochila está cheia. Libere um espaço para trocar esse item.'))); die(); }
		if ($saida <= 3) { bonusdoitem($userrow[$campo."id"], $userrow[$campo."name"], -1); }
		else { bonusdodrop($userrow[$campo."id"], -1, $userrow[$campo."name"]); }
	}
	$userrow[$campo."name"] = "None"; $userrow[$campo."id"] = 0;
	$durab[$saida] = "X";

	// 2) Só com os 4 acessórios cheios: anda com os slots e com a durabilidade deles (slot1 -> slot2 -> slot3 -> slot4).
	if ($anda){
		for ($s = 3 + NUMACESSORIOS; $s > 4; $s--) {
			$userrow[$slots[$s]."id"] = $userrow[$slots[$s - 1]."id"];
			$userrow[$slots[$s]."name"] = $userrow[$slots[$s - 1]."name"];
			$durab[$s] = $durab[$s - 1];
		}
	}

	// 3) Equipa o item novo com a durabilidade que ele tinha na mochila.
	$novocampo = $slots[$novaposicao];
	if ($tipo <= 3) { bonusdoitem($itemseparadobp[1], $itemseparadobp[0], 1); }
	else { bonusdodrop($itemseparadobp[1], 1, $itemseparadobp[0]); } // o nome leva a variante rara (":efeito").
	if (atributosnegativos($userrow)) { doquery("ROLLBACK", "users"); header('Location: ./index.php?conteudo='.rawurlencode(t('O lado negativo deste item deixaria um atributo seu abaixo de zero. Fique mais forte antes de equipá-lo.'))); die(); }
	$userrow[$novocampo."name"] = $itemseparadobp[0];
	$userrow[$novocampo."id"] = intval($itemseparadobp[1]);
	$durab[$novaposicao] = is_numeric($itemseparadobp[3]) ? intval($itemseparadobp[3]) : "X";
	$userrow["durabilidade"] = juntardurabilidade($durab);

}

limitarpontosatuais();

// Grava tudo de uma vez. O "AND bpN=..." evita usar o mesmo item duas vezes com cliques simultâneos.
$equipamentos = "";
foreach ($slots as $s) {
	$equipamentos .= ", ".$s."name='".addslashes($userrow[$s."name"])."', ".$s."id='".intval($userrow[$s."id"])."'";
}
doquery("UPDATE {{table}} SET pocoesluta='".addslashes($userrow["pocoesluta"])."',durabilidade='".addslashes($userrow["durabilidade"])."',bp1='".addslashes($userrow["bp1"])."',bp2='".addslashes($userrow["bp2"])."',bp3='".addslashes($userrow["bp3"])."',bp4='".addslashes($userrow["bp4"])."',bp5='".addslashes($userrow["bp5"])."',bp6='".addslashes($userrow["bp6"])."',bp7='".addslashes($userrow["bp7"])."',bp8='".addslashes($userrow["bp8"])."',".sqlatributosdojogador().$equipamentos." WHERE id='".intval($userrow["id"])."' AND $variavelbp='".addslashes($itemoriginal)."' LIMIT 1", "users");
if (mysqli_affected_rows($link) != 1) { doquery("ROLLBACK", "users"); header('Location: ./index.php'); die(); }
doquery("COMMIT", "users");

header('Location: '.paginaderetorno());
die();

?>
