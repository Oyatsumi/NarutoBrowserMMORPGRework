<?php // elemento.php :: Escolha dos elementos do ninja (principal e, se quiser, secundário), numa vila.
//
// users.elemento = "principal,secundário" ("neutro" = não escolhido). A partir do nível control.elemento_nivel, em qualquer
// vila (ícone "Escolher Elemento" nos serviços da vila), o jogador escolhe o elemento PRINCIPAL e, se quiser, um SECUNDÁRIO
// diferente (na mesma hora ou depois). As duas escolhas são DEFINITIVAS (o administrador pode corrigir em admin.php edituser).
//
// Efeitos (funcoesinclusas.php :: elementoganhador()/elementomultcombinado(); funcoesjutsus.php :: aplicarjutsu()):
//   - jutsu de dano do elemento principal: +control.elemento_bonus % (padrão 20); do secundário: +control.elemento_bonus2 % (10);
//     só quando o EP do jutsu foi pago (sem EP o jutsu sai como Neutro);
//   - o ninja passa a ter esses elementos na defesa: toma x1,5 do elemento que vence o seu, x0,5 do que perde para o seu e
//     resiste (control.elemento_resiste %, padrão 75) ao próprio elemento. O principal pesa inteiro; o secundário com peso
//     control.elemento_peso2 % (padrão 50). Vale contra inimigos, em duelos e no PvP. Neutro: nem vantagem nem desvantagem.
// Rotas: (nenhuma) = página; POST el (+ qual) = confirmação; POST confirmar = grava (CSRF + caixa "definitiva").

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php');
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."), t("Erro"), false, false, false); die(); }
if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }

elementopagina();

function elementociclo() { // Quem vence quem: elemento => array(vence, perde para, atributo ligado).
	return array(
		"agua"  => array("fogo", "terra", "Sorte"),
		"fogo"  => array("vento", "agua", "Determinação"),
		"vento" => array("raio", "fogo", "Precisão"),
		"raio"  => array("terra", "vento", "Agilidade"),
		"terra" => array("agua", "raio", "Inteligência"),
	);
}

function elementoicone($el, $tam = 16) {
	return file_exists("images/".$el.".gif") ? "<img src=\"images/".$el.".gif\" width=\"".$tam."\" height=\"".(int)round($tam * 14 / 16)."\" alt=\"\" style=\"vertical-align:-2px\" />" : "";
}

function cartaoelemento($el, $modo, $qual) { // Cartão de um elemento. $modo: "botao" (pode escolher), "travado" (só leitura) ou "seu1"/"seu2".
	global $controlrow, $userrow;
	$c = elementociclo(); list($vence, $perde, $atrib) = $c[$el];
	$t = "t"; $tf = "tf";
	$b1 = intval($controlrow["elemento_bonus"] ?? 20); $b2 = intval($controlrow["elemento_bonus2"] ?? 10); $res = intval($controlrow["elemento_resiste"] ?? 75);
	$conhece = 0; $existem = 0;
	$q = doquery("SELECT id FROM {{table}} WHERE type='2' AND elemento='".$el."' AND exclusivo_missao='0'", "spells");
	$meus = explode(",", (string)$userrow["spells"]);
	while ($s = mysqli_fetch_array($q)) { $existem++; if (in_array($s["id"], $meus)) { $conhece++; } }
	// Bônus nos jutsus desse elemento (com EP): fixo do painel + pontos do atributo ligado (dobro no principal, x1,5 no secundário).
	$bonus = ($qual == 2) ? tf("Como secundário: +%s%% de dano nos jutsus de %s, e cada %s pontos de %s dão +1%%", $b2, elementonome($el), "13", t($atrib))
	                      : tf("Como principal: +%s%% de dano nos jutsus de %s, e cada %s pontos de %s dão +1%%", $b1, elementonome($el), "10", t($atrib));
	$selo = ""; $borda = "";
	if ($modo == "seu1") { $selo = "<span style=\"color:#9be08a\">&#10004; ".t("Principal")."</span>"; $borda = " style=\"box-shadow:0 0 0 2px #c98b5a\""; }
	if ($modo == "seu2") { $selo = "<span style=\"color:#9be08a\">&#10004; ".t("Secundário")."</span>"; $borda = " style=\"box-shadow:0 0 0 2px #c98b5a\""; }
	$botao = "";
	if ($modo == "botao") { $botao = "<div style=\"text-align:center;padding:6px 2px 2px\"><button type=\"submit\" name=\"el\" value=\"$el\" id=\"escolher_$el\" class=\"btnjogo bj-confirmar\">".elementoicone($el)." ".tf("Escolher %s", elementonome($el))."</button></div>"; }
	return "<div class=\"elcartao\" style=\"flex:1 1 150px;min-width:0;max-width:100%\"><div class=\"cxb\" id=\"elemento_$el\"$borda><div class=\"cxb-cab\"><span style=\"font-weight:bold\">".elementoicone($el, 18)." ".elementonome($el)."</span>$selo</div><div class=\"cxb-corpo\">"
		."<div class=\"cxb-linha\"><b>".$bonus."</b></div>"
		."<div class=\"cxb-linha\"><span style=\"color:#2e7d1a\">&#9650;</span> ".tf("Forte contra %s (x1,5)", elementoicone($vence)." ".elementonome($vence))."</div>"
		."<div class=\"cxb-linha\"><span style=\"color:#b3261e\">&#9660;</span> ".tf("Fraco contra %s (x0,5)", elementoicone($perde)." ".elementonome($perde))."</div>"
		."<div class=\"cxb-linha\"><span style=\"color:#2f6fd0\">&#9632;</span> ".tf("Resiste a %s: recebe %s%% do dano", elementonome($el), $res)."</div>"
		."<div class=\"cxb-linha small\">".tf("Atributo ligado: %s (fora do principal/secundário, +1%% a cada 20 pontos)", t($atrib))."</div>"
		."<div class=\"cxb-linha small\">".tf("Jutsus de dano de %s: você conhece %s de %s", elementonome($el), $conhece, $existem)."</div>"
		.$botao."</div></div></div>";
}

function elementopagina() {
	global $userrow, $controlrow;
	$t = "t"; $tf = "tf";
	list($p1, $p2) = elementosdo($userrow["elemento"]);
	$todos = array_keys(elementociclo());
	$topo = "<table width=\"100%\"><tr><td align=\"center\"><h2 style=\"color:#452202;margin:4px\">".t("Elementos do Ninja")."</h2></td></tr></table>";
	$voltar = "<div style=\"text-align:center;margin-top:8px\"><a href=\"index.php\" id=\"botaovoltarvila\" class=\"btnjogo bj-neutro\"><img src=\"images/seta_oeste.png\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".t("Voltar para a vila")."</a></div>";
	$qual = ($p1 == "neutro") ? 1 : (($p2 == "neutro") ? 2 : 0); // qual escolha está aberta (0 = as duas já feitas).
	$cartoes = function ($modo) use ($todos, $p1, $p2, $qual) {
		$h = "<div id=\"elementos\" style=\"display:flex;flex-wrap:wrap;gap:6px;align-items:flex-start;text-align:left\">";
		foreach ($todos as $el) {
			if ($el == $p1) { $m = "seu1"; } elseif ($el == $p2) { $m = "seu2"; } else { $m = $modo; }
			$h .= cartaoelemento($el, $m, ($el == $p1) ? 1 : (($el == $p2) ? 2 : max(1, $qual)));
		}
		return $h."</div>";
	};
	$explica = "<div class=\"cxb\" style=\"max-width:560px;margin:0 auto 8px\"><div class=\"cxb-cab\"><span>".t("Como funciona")."</span></div><div class=\"cxb-corpo\">"
		."<div class=\"cxb-linha\">".t("Água vence Fogo, Fogo vence Vento, Vento vence Raio, Raio vence Terra e Terra vence Água.")."</div>"
		."<div class=\"cxb-linha\">".tf("Você escolhe um elemento <b>principal</b> (+%s%% de dano nos jutsus dele) e, se quiser, um <b>secundário</b> (+%s%%).", intval($controlrow["elemento_bonus"] ?? 20), intval($controlrow["elemento_bonus2"] ?? 10))."</div>"
		."<div class=\"cxb-linha\">".t("Cada elemento é ligado a um atributo (Água = Sorte, Fogo = Determinação, Vento = Precisão, Raio = Agilidade, Terra = Inteligência): todo jutsu com elemento ganha +1% de dano a cada 20 pontos dele. No seu elemento principal esses pontos contam em dobro e no secundário, uma vez e meia.")."</div>"
		."<div class=\"cxb-linha\">".t("Seus elementos também valem na defesa, contra inimigos, em duelos e no PvP: você toma mais dano do elemento que vence o seu e resiste ao seu próprio elemento. O secundário pesa a metade.")."</div>"
		."<div class=\"cxb-linha\">".t("O jutsu só sai com o elemento se você tiver o EP dele. Sem EP ele sai como Neutro e gasta só Chakra.")."</div>"
		."<div class=\"cxb-linha\"><b>".t("As escolhas são definitivas.")."</b></div></div></div>";

	if ($qual == 0) { // já escolheu os dois.
		display($topo."<center>".tf("Seu elemento principal é <b>%s</b> e o secundário é <b>%s</b>.", elementonome($p1), elementonome($p2))."</center><br />".$explica.$cartoes("travado").$voltar, t("Elementos"));
	}
	$cidade = mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns")) > 0;
	$nivel = max(1, intval($controlrow["elemento_nivel"] ?? 5));
	$aviso = "";
	if (!$cidade) { $aviso = t("Você só pode escolher os seus elementos dentro de uma vila."); }
	elseif (intval($userrow["level"]) < $nivel) { $aviso = tf("Você poderá escolher os seus elementos a partir do nível %s.", $nivel); }

	$esc = pegartexto($_POST, "el");
	$valido = in_array($esc, $todos, true) && $esc != $p1;
	$caixa = t("Marque a caixa confirmando que você entende que a escolha é definitiva.");
	$expirou = t("A sessão do formulário expirou. Tente de novo.");
	if ($aviso == "" && isset($_POST["el"]) && !$valido) { $aviso = t("Escolha um dos elementos."); }
	elseif ($aviso == "" && isset($_POST["confirmar"])) {
		if (!csrf_confere()) { $aviso = $expirou; }
		elseif (pegartexto($_POST, "definitiva") != "1") { $aviso = $caixa; }
		else {
			global $link;
			if ($qual == 1) { doquery("UPDATE {{table}} SET elemento='".$esc.",neutro' WHERE id='".intval($userrow["id"])."' AND (elemento='' OR elemento LIKE 'neutro%') LIMIT 1", "users"); }
			else { doquery("UPDATE {{table}} SET elemento='".$p1.",".$esc."' WHERE id='".intval($userrow["id"])."' AND elemento='".$p1.",neutro' LIMIT 1", "users"); }
			if (mysqli_affected_rows($link) == 1) {
				$m = ($qual == 1) ? tf("Seu elemento principal agora é %s!", elementonome($esc)) : tf("Seu elemento secundário agora é %s!", elementonome($esc));
				header("Location: elemento.php?conteudo=".rawurlencode($m)); die();
			}
			$aviso = t("Essa escolha já foi feita.");
		}
	}
	$avisoconf = "";
	if (isset($_POST["confirmar"]) && $valido && in_array($aviso, array($caixa, $expirou), true)) { $avisoconf = $aviso; $aviso = ""; }
	if ($aviso == "" && $valido) { // confirmação.
		$csrf = csrf_campo();
		$nome = elementonome($esc);
		$tipo = ($qual == 1) ? t("principal") : t("secundário");
		$avisoconfhtml = $avisoconf != "" ? "<div class=\"cxb-linha\" id=\"avisoconf\" style=\"color:#9b1b10;font-weight:bold\">&#9888; ".$avisoconf."</div>" : "";
		$page = $topo.<<<END
<form action="elemento.php" method="post">$csrf<input type="hidden" name="el" value="$esc" />
<div class="cxb" id="confirmarelemento" style="max-width:460px;margin:0 auto 10px;box-shadow:0 0 0 2px #9b1b10"><div class="cxb-cab" style="background:#9b1b10"><span>{$t("Atenção: a escolha é definitiva!")}</span></div><div class="cxb-corpo">
<div class="cxb-linha">{$tf("<b>%s</b> será o seu elemento <b>%s</b>. Depois de confirmar, <b>não será possível trocar</b>.", $nome, $tipo)}</div>
$avisoconfhtml<div class="cxb-linha"><label style="cursor:pointer"><input type="checkbox" name="definitiva" value="1" /> <b>{$t("Entendo que essa escolha é definitiva.")}</b></label></div>
<div style="text-align:center;padding:8px 4px" class="buttons btnlinha"><button class="btnjogo bj-confirmar" type="submit" name="confirmar" value="1" id="confirmarelemento_btn"><img src="images/botao_sim.png" width="16" height="16" alt="" style="vertical-align:middle"> {$tf("Confirmar: %s", $nome)}</button> <a href="elemento.php" class="btnjogo bj-neutro" id="pensarmelhor"><img src="images/seta_oeste.png" width="16" height="16" alt="" style="vertical-align:middle"> {$t("Voltar e pensar melhor")}</a></div>
</div></div></form>
END;
		display($page.$cartoes("travado"), t("Elementos"));
	}

	$msg = strip_tags(lermsg("conteudo"));
	$page = $topo;
	if ($msg != "") { $page .= "<center><font color=\"darkgreen\"><b>$msg</b></font></center><br />"; }
	if ($qual == 1) { $page .= "<center>".t("Escolha o seu elemento <b>principal</b>.")."</center><br />"; }
	else { $page .= "<center>".tf("Seu elemento principal é <b>%s</b>. Se quiser, escolha agora um elemento <b>secundário</b> (bônus menor). Você também pode ficar só com um.", elementonome($p1))."</center><br />"; }
	if ($aviso != "") { $page .= "<center><font color=\"#9b1b10\"><b>$aviso</b></font></center><br />"; }
	$page .= $explica;
	$pode = $cidade && intval($userrow["level"]) >= $nivel;
	$page .= $pode ? "<form action=\"elemento.php\" method=\"post\" style=\"margin:0\">".$cartoes("botao")."</form>".$voltar : $cartoes("travado").$voltar;
	display($page, t("Elementos"));
}
