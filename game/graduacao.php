<?php // graduacao.php :: Graduações ninja (Estudante da Academia -> Genin -> Chuunin -> Jounin).
//
// Só é possível se graduar nas vilas com towns.tem_graduacao = 1 (originalmente a Vila da Folha e a Vila da Pedra).
// O campo users.graduacao guarda o nome da graduação atual. Os requisitos de missões contam as missões
// obrigatórias já concluídas: users.missao começa com a etapa atual, então "concluídas = etapa - 1".
//
// Requisitos e bônus:
//   Genin ..: 7 missões concluídas. Bônus: +20 pontos de distribuição, +20 NP (máx. e atual), +10 HP e +10 CH (máx. e atual).
//   Chuunin : item Esperança do Chuunin na mochila (é consumido) e 10 missões concluídas.
//             Bônus: +30 pontos de distribuição, +30 de Força e +30 de Destreza (e o mesmo em Ataque/Defesa).
//   Jounin .: item Selo de Aprovação Jounin (cai dos chefes nível 31-36; a antiga Força Inútil ainda vale) na mochila, level 30 e 20 missões concluídas. Bônus: +100 pontos de distribuição,
//             +20 NP, +20 de Força, +20 de Destreza, +5% de bônus de Exp. e de Ryou e o item Vitória do Jounin
//             (que fica no lugar do Selo).

include('lib.php');
$link = opendb();

include('cookies.php');
$userrow = checkcookies();

include_once('funcoesinclusas.php');

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }

if (!function_exists('jsarg')) {
function jsarg($texto) { // Prepara um texto para ir entre aspas simples num href="javascript:...('texto')" (igual ao treinamentoequests.php).

	return str_replace(array("\\", "'", "\"", "%"), array("\\\\", "\\'", "&quot;", "%25"), (string)$texto);

}
}


// Só pode se graduar numa vila com o serviço de graduação (towns.tem_graduacao).
$townquery = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
if (mysqli_num_rows($townquery) == 0) { display(t("Há um erro com sua conta, ou com os dados da cidade. Por favor tente novamente."),t("Erro")); die(); }
$townrow = mysqli_fetch_array($townquery);
if (intval($townrow['tem_graduacao']) != 1) { header('Location: index.php?conteudo='.rawurlencode(t("Você não pode se graduar aqui, uma tentativa de trapaça foi detectada!"))); die(); }

// Sem do= (ou com um valor desconhecido) mostra a tela da graduação, em vez de uma página em branco.
$do = isset($_GET["do"]) ? $_GET["do"] : "graduacao";
if ($do == "graduar") { graduar(); }
else { graduacao(); }


function verificargraduacao() { // Checagens comuns: estar dentro da cidade, fora de batalha e fora de duelo.

	global $userrow;
	if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t("Você só pode acessar essa função dentro de uma cidade!"))); die(); }
	if ($userrow["currentaction"] != "In Town") { header('Location: ./index.php?conteudo='.rawurlencode(t("Você só pode acessar essa função dentro de uma cidade!"))); die(); }
	if ($userrow["batalha_timer2"] == 5) { display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "),t("Erro"),false,false,false); die(); }

}


function itemdagraduacao($nome) { // Onde está o item exigido: 4-11 = mochila (bp1..bp8), 1 = só equipado num acessório (1 a 4), 0 = não possui.

	global $userrow;
	// A variante rara do item ("Força Inútil:chama") também vale. A mochila vem primeiro: com o item nos dois lugares,
	// a graduação usa o da mochila.
	for ($i = 1; $i <= 8; $i++) {
		$bp = explode(",", (string)$userrow["bp".$i]);
		if (separarvariante($bp[0])[0] == $nome) { return $i + 3; }
	}
	for ($i = 1; $i <= NUMACESSORIOS; $i++) {
		if (separarvariante((string)$userrow["slot".$i."name"])[0] == $nome) { return 1; }
	}
	return 0;

}


function parargraduacao($texto) { // Mostra a fala do Kage com o motivo de não poder se graduar e encerra.

	global $townrow;
	graduacao(personagemgeral($texto, $townrow['id'], t($townrow['kage'])));
	die();

}


function listaganhos($graduacao, $ganhos) { // Texto de parabéns com a lista de bônus da nova graduação (já traduzido).

	$texto = tf("Parabéns! Agora você é um <b>%s</b>!", t($graduacao))."<br>\n".t("Você ganhou:")."<br>\n<ul>\n";
	foreach ($ganhos as $ganho) { $texto .= "<li />".$ganho."\n"; } // $ganhos já vem traduzido.
	return $texto."</ul>";

}




function graduar() { // Faz a graduação seguinte, se o jogador tiver os requisitos.

	global $userrow, $townrow, $link;
	verificargraduacao();
	if ($_SERVER["REQUEST_METHOD"] != "POST" || !csrf_confere()) { header("Location: graduacao.php"); die(); } // só pelo botão (POST + CSRF)

	$graduacao = $userrow["graduacao"];
	$missao2 = explode(",", $userrow["missao"]);
	$concluidas = intval($missao2[0]) - 1; // a etapa atual ainda não foi concluída.
	$graduacaoaberta = "";
	$sql = ""; $novagraduacao = ""; $ondeextra = "";

	// Estudante da Academia -> Genin.
	if ($graduacao == "Estudante da Academia") {

		if ($concluidas < 7) { parargraduacao(t('Você precisa completar 7 missões ninjas para graduar-se um Genin.')); }

		$graduacaoaberta = listaganhos("Genin", array_map("t", array("20 Pontos de Distribuição.", "20 Pontos de Natureza.", "10 Pontos de Vida.", "10 Pontos de Chakra.")));
		$sql = "pontoatributos=pontoatributos+20, maxhp=maxhp+10, currenthp=currenthp+10, maxmp=maxmp+10, currentmp=currentmp+10, maxnp=maxnp+20, currentnp=currentnp+20, graduacao='Genin'";
		$novagraduacao = "Genin";
	}

	// Genin -> Chuunin.
	elseif ($graduacao == "Genin") {

		$foi = itemdagraduacao("Esperança do Chuunin");
		if ($foi == 0) { parargraduacao(tf('Você precisa trazer o item %s para graduar-se um Chuunin.', t("Esperança do Chuunin"))); }
		if ($foi <= 3) { parargraduacao(tf('Primeiro coloque o item <b>%s</b> na sua mochila.', t("Esperança do Chuunin"))); }
		if ($concluidas < 10) { parargraduacao(t('Você precisa completar 10 missões ninjas para graduar-se um Chuunin.')); }
		if (elementosdo($userrow["elemento"])[0] == "neutro") { parargraduacao(t('Você precisa escolher o seu <a href="elemento.php">elemento principal</a> para graduar-se um Chuunin.')); }

		$graduacaoaberta = listaganhos("Chuunin", array_map("t", array("30 Pontos de Distribuição.", "30 de Força.", "30 de Destreza.")));
		// O item sai da mochila. (A Força entra direto no Poder de Ataque e a Destreza no Poder de Defesa.)
		// Pilha: só 1 unidade é consumida.
		$bpfoi = "bp".($foi - 3);
		$sql = "pontoatributos=pontoatributos+30, strength=strength+30, dexterity=dexterity+30, attackpower=attackpower+30, defensepower=defensepower+30, graduacao='Chuunin', $bpfoi='".addslashes(itemcomqtd($userrow[$bpfoi], qtditem($userrow[$bpfoi]) - 1))."'";
		$ondeextra = " AND $bpfoi='".addslashes($userrow[$bpfoi])."'";
		$novagraduacao = "Chuunin";
	}

	// Chuunin -> Jounin.
	elseif ($graduacao == "Chuunin") {

		$foi = itemdagraduacao("Selo de Aprovação Jounin");
		if ($foi == 0) { $foi = itemdagraduacao("Força Inútil"); } // item antigo: continua valendo para quem já o tinha.
		if ($foi == 0) { parargraduacao(tf('Você ainda não possui o item <b>%s</b> para graduar-se um Jounin.', t("Selo de Aprovação Jounin"))); }
		if ($foi <= 3) { parargraduacao(tf('Primeiro coloque o item <b>%s</b> na sua mochila.', t("Selo de Aprovação Jounin"))); }
		if ($userrow['level'] < 30) { parargraduacao(t('Você precisa ter level 30 para graduar-se um Jounin.')); }
		if ($concluidas < 20) { parargraduacao(t('Você precisa completar 20 missões ninjas para graduar-se um Jounin.')); }
		if (elementosdo($userrow["elemento"])[0] == "neutro") { parargraduacao(t('Você precisa escolher o seu <a href="elemento.php">elemento principal</a> para graduar-se um Jounin.')); }

		$graduacaoaberta = listaganhos("Jounin", array_merge(array_map("t", array("100 Pontos de Distribuição.", "20 Pontos de Natureza.", "20 de Força.", "20 de Destreza.", "5% de Bônus de Experiência.", "5% de Bônus de Ryou.")), array(tf("Você ganhou o Item: <font color=red>%s</font>.", t("Vitória do Jounin")))));
		// O Selo entregue dá lugar à Vitória do Jounin. Se ela estava numa pilha, sai 1 unidade e a Vitória
		// procura outro lugar na mochila (precisa de espaço).
		$bpfoi = "bp".($foi - 3);
		$ondeextra = " AND $bpfoi='".addslashes($userrow[$bpfoi])."'";
		if (qtditem($userrow[$bpfoi]) > 1) {
			$copia = $userrow;
			$copia[$bpfoi] = itemcomqtd($userrow[$bpfoi], qtditem($userrow[$bpfoi]) - 1);
			$espacos = mochilaguardar($copia, "Vitória do Jounin,43,4,X");
			if ($espacos === false) { parargraduacao(t('Libere um espaço na mochila para receber a Vitória do Jounin.')); }
			$espacos[] = $bpfoi;
			$trocamochila = sqlmochila($copia, $userrow, $espacos)[0];
		} else {
			$trocamochila = "$bpfoi='Vitória do Jounin,43,4,X'";
		}
		$sql = "pontoatributos=pontoatributos+100, goldbonus=goldbonus+5, expbonus=expbonus+5, maxnp=maxnp+20, currentnp=currentnp+20, strength=strength+20, dexterity=dexterity+20, attackpower=attackpower+20, defensepower=defensepower+20, graduacao='Jounin', ".$trocamochila;
		$novagraduacao = "Jounin";
	}

	if ($sql != "") {
		// O "AND graduacao=..." impede que a mesma graduação seja paga duas vezes (cliques repetidos/simultâneos).
		// O $ondeextra confere que o item exigido ainda está no mesmo espaço da mochila (não foi trocado/vendido ao mesmo tempo).
		doquery("UPDATE {{table}} SET ".$sql." WHERE id='".intval($userrow["id"])."' AND graduacao='".addslashes($graduacao)."'$ondeextra LIMIT 1", "users");
		if (mysqli_affected_rows($link) != 1) { $graduacaoaberta = ""; }
		else { $userrow["graduacao"] = $novagraduacao; } // o painel já aparece com a nova graduação.
	}

	if ($graduacaoaberta == "") { $graduacaoaberta = tf("Desculpe %s, mas não há graduações disponíveis no momento ou você não tem os requerimentos para graduar-se.", $userrow['charname']); }

	graduacao(personagemgeral($graduacaoaberta, $townrow['id'], t($townrow['kage'])));
	die();

}


function graduacao($fala="") { // Painel de Graduação: lista as graduações com requisitos e bônus.

	global $userrow, $townrow;
	verificargraduacao();

	$graduacao = $userrow["graduacao"];
	$prox = "";
	if ($graduacao == "Estudante da Academia") { $prox = "Genin"; }
	elseif ($graduacao == "Genin") { $prox = "Chuunin"; }
	elseif ($graduacao == "Chuunin") { $prox = "Jounin"; }

	if ($fala == "") {
		if ($prox != "") { $fala = personagemgeral(tf('Olá %s! A próxima graduação disponível para você é a graduação de %s, caso você possua os requerimentos, você pode se graduar abaixo agora mesmo. As graduações te darão bônus para que você se torne um shinobi ainda mais completo.', $userrow['charname'], t($prox)), $townrow['id'], t($townrow['kage'])); }
		else { $fala = personagemgeral(tf('Olá %s! Você já concluiu todas as graduações disponíveis.', $userrow['charname']), $townrow['id'], t($townrow['kage'])); }
	}

	$page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/graduacao.gif\" /></center></td></tr></table>
$fala
";

	// Ordem das graduações, para saber o que já foi concluído.
	$ordem = array("Estudante da Academia" => 1, "Genin" => 2, "Chuunin" => 3, "Jounin" => 4);
	$atual = isset($ordem[$graduacao]) ? $ordem[$graduacao] : 1;
	$missao2 = explode(",", (string)$userrow["missao"]);
	$concluidas = max(0, intval($missao2[0]) - 1);
	$temitem = function ($nome) { return itemdagraduacao($nome) >= 4; }; // só vale na mochila
	// Requisitos: [texto, cumprido?, item para o mapa de drops (nome no banco) ou ""].
	$temel = (elementosdo($userrow["elemento"])[0] != "neutro"); // Chuunin e Jounin exigem o elemento principal escolhido (elemento.php).
	$reqel = array(t("Elemento principal escolhido")." <small>(<a href=\"elemento.php\">".($temel ? elementonome(elementosdo($userrow["elemento"])[0]) : t("escolher"))."</a>)</small>", $temel, "");
	$req = function ($n) use ($concluidas) { return array(tf("%s missões ninjas concluídas", $n)." <small>(".$concluidas."/".$n.")</small>", $concluidas >= $n, ""); };
	$linhas = array(
		1 => array("Estudante da Academia", array(), array()),
		2 => array("Genin", array($req(7)), array(t("20 Pontos de Distribuição"), t("20 Pontos de Natureza"), t("10 Pontos de Vida"), t("10 Pontos de Chakra"))),
		3 => array("Chuunin", array(array(tf("Item <b>%s</b> na mochila", t("Esperança do Chuunin")), $temitem("Esperança do Chuunin"), "Esperança do Chuunin"), $req(10), $reqel), array(t("30 de Força"), t("30 de Destreza"), t("30 Pontos de Distribuição"))),
		4 => array("Jounin", array(array(tf("Item <b>%s</b> na mochila", t("Selo de Aprovação Jounin")), $temitem("Selo de Aprovação Jounin") || $temitem("Força Inútil"), "Selo de Aprovação Jounin"), $req(20), $reqel, array(t("Level 30 ou mais")." <small>(".intval($userrow["level"]).")</small>", intval($userrow["level"]) >= 30, "")),
			array(t("100 Pontos de Distribuição"), t("20 Pontos de Natureza"), t("20 de Força"), t("20 de Destreza"), t("5% de Bônus de Experiência"), t("5% de Bônus de Ryou"), tf("Item: <b>%s</b>", t("Vitória do Jounin")))),
	);
	$h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); };
	$page .= "<div id=\"graduacoes\" style=\"max-width:420px;margin:8px auto 0\">";
	foreach ($linhas as $i => $linha) {
		list($nome, $reqs, $bonus) = $linha;
		if ($i <= $atual) { $status = "<span style=\"color:#9be08a\">&#10004; ".t("Concluído")."</span>"; }
		elseif ($i == $atual + 1) { $status = "<span style=\"color:#ffd76a\">&#9733; ".t("Próxima")."</span>"; }
		else { $status = "<span style=\"color:#d9c39a\">".t("Indisponível")."</span>"; }
		$corpo = "";
		if ($i > 1) {
			$corpo .= "<div class=\"cxb-linha\"><b>".t("Requisitos:")."</b></div>";
			foreach ($reqs as $r) {
				$marca = ($i <= $atual || $r[1]) ? "<span style=\"color:#2e7d1a\">&#10004;</span>" : "<span style=\"color:#b0281c\">&#10008;</span>";
				$mapa = ($r[2] != "" && $i > $atual) ? " <a href=\"javascript:openmaprespawn('".$h(jsarg($r[2]))."')\" title=\"".$h(t("Mostrar Onde Dropa o Item"))."\"><img src=\"images/maximizar.gif\" border=\"0\" alt=\"\" style=\"vertical-align:middle\"></a>" : "";
				$corpo .= "<div class=\"cxb-linha\">".$marca." ".$r[0].$mapa."</div>";
			}
			$corpo .= "<div class=\"cxb-linha\"><b>".t("Bônus:")."</b> ".implode(", ", $bonus).".</div>";
		} else {
			$corpo .= "<div class=\"cxb-linha\">".t("Graduação inicial de todo ninja.")."</div>";
		}
		if ($i == $atual + 1) {
			$pronto = true; foreach ($reqs as $r) { if (!$r[1]) { $pronto = false; } }
			$corpo .= "<div style=\"text-align:center;padding:6px\"><form action=\"graduacao.php?do=graduar\" method=\"post\" style=\"margin:0\">".csrf_campo()
				."<button type=\"submit\" id=\"botaograduar\" class=\"btnjogo ".($pronto ? "bj-confirmar" : "bj-neutro")."\"><img src=\"images/24/graduacao.gif\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".tf("Graduar-se %s", t($nome))."</button></form>"
				.($pronto ? "" : "<small style=\"color:#7a5a30\">".t("Faltam requisitos (marcados com &#10008;).")."</small>")."</div>";
		}
		$page .= "<div class=\"cxb\" id=\"graduacao$i\"".($i == $atual + 1 ? " style=\"box-shadow:0 0 0 2px #c98b5a\"" : ($i > $atual + 1 ? " style=\"opacity:.75\"" : ""))."><div class=\"cxb-cab\"><span>".$i.". ".t($nome)."</span>".$status."</div><div class=\"cxb-corpo\">".$corpo."</div></div>";
	}
	$page .= "</div><div style=\"text-align:center;margin-top:8px\"><a href=\"index.php\" id=\"botaovoltarvila\" class=\"btnjogo bj-neutro\"><img src=\"images/seta_oeste.png\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".t("Voltar para a vila")."</a></div>";

	display($page, t("Graduação"), false, false, false);

}

?>
