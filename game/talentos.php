<?php // talentos.php :: Árvore de talentos: a cada 10 níveis (10..80) o jogador escolhe 1 de 2 talentos. Escolha definitiva.
// Regras e efeitos em funcoestalentos.php. Rotas: (nenhuma) = árvore; POST escolher = confirmação; POST confirmar = grava (CSRF).

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php');
include_once('funcoestalentos.php');
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."), t("Erro"), false, false, false); die(); }
if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }
if ($userrow["batalha_timer2"] == 5) { display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "), t("Erro"), false, false, false); die(); }

talentos();

function talentos() {
	global $userrow;
	$lista = talentoslista();
	$aviso = "";
	$nivel = pegarint($_POST, "nivel");
	$opcao = pegarint($_POST, "opcao");
	$h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

	if (isset($_POST["escolher"]) || isset($_POST["confirmar"])) {
		$feitos = talentosescolhidos($userrow);
		if (!isset($lista[$nivel][$opcao])) { $aviso = t("Talento inválido."); }
		elseif (intval($userrow["level"]) < $nivel) { $aviso = t("Você ainda não tem nível para esse talento."); }
		elseif (isset($feitos[$nivel])) { $aviso = t("Você já escolheu o talento desse nível. A escolha é definitiva."); }
		elseif (isset($_POST["confirmar"])) {
			if (!csrf_confere()) { $aviso = t("A sessão do formulário expirou. Tente de novo."); }
			elseif (pegartexto($_POST, "definitiva") != "1") { $aviso = t("Marque a caixa confirmando que você entende que a escolha é definitiva."); }
			else {
				$erro = talentoescolher(intval($userrow["id"]), $nivel, $opcao);
				if ($erro == "") { header("Location: talentos.php?conteudo=".rawurlencode(tf("Talento escolhido: %s.", t($lista[$nivel][$opcao]["nome"])))); die(); }
				$aviso = t($erro);
			}
		} else { // etapa de confirmação.
			$tal = $lista[$nivel][$opcao];
			$csrf = csrf_campo();
			$t = "t"; $tf = "tf";
			$nome = $h(t($tal["nome"])); $desc = $h(t($tal["desc"]));
			$page = <<<END
<div class="cxb"><div class="cxb-cab">{$t("Confirmar talento")}</div><div class="cxb-corpo">
<form action="talentos.php" method="post">$csrf<input type="hidden" name="nivel" value="$nivel" /><input type="hidden" name="opcao" value="$opcao" />
<div class="cxb-linha" style="text-align:center"><font color="#9b1b10"><b>{$t("Atenção: a escolha é definitiva!")}</b></font></div>
<div class="cxb-linha" style="text-align:center">{$tf("Talento do nível %s:", $nivel)} <b>$nome</b> ($desc)<br />{$t("Depois de confirmar, não será possível trocar.")}</div>
<div class="cxb-linha" style="text-align:center"><label><input type="checkbox" name="definitiva" value="1" /> {$t("Entendo que essa escolha é definitiva.")}</label><br /><br />
<input class="btnjogo bj-confirmar" type="submit" name="confirmar" value="{$t("Confirmar talento")}" /> &nbsp; <a href="talentos.php">{$t("Voltar e pensar melhor")}</a></div>
</form></div></div>
END;
			display($page, t("Talentos"));
		}
	}

	$msg = strip_tags(lermsg("conteudo"));
	$feitos = talentosescolhidos($userrow);
	$page = "<div class=\"cxb\"><div class=\"cxb-cab\">".t("Árvore de Talentos")."</div><div class=\"cxb-corpo\">";
	$page .= "<div class=\"cxb-linha\">".t("A cada 10 níveis você escolhe 1 de 2 talentos. <b>A escolha é definitiva.</b>")."</div>";
	if ($msg != "") { $page .= "<div class=\"cxb-linha\"><font color=\"darkgreen\"><b>".$h($msg)."</b></font></div>"; }
	if ($aviso != "") { $page .= "<div class=\"cxb-linha\"><font color=\"#9b1b10\"><b>".$h($aviso)."</b></font></div>"; }
	$page .= "</div></div>";
	foreach ($lista as $n => $ops) {
		$tem = isset($feitos[$n]);
		$pode = !$tem && intval($userrow["level"]) >= $n;
		$estado = $tem ? t("escolhido") : ($pode ? t("disponível") : tf("a partir do nível %s", $n));
		$page .= "<div class=\"cxb\"><div class=\"cxb-cab\"><span>".tf("Nível %s", $n)."</span><span>".$estado."</span></div><div class=\"cxb-corpo\">";
		foreach ($ops as $o => $tal) {
			$linha = "<b>".$h(t($tal["nome"]))."</b>: ".$h(t($tal["desc"]));
			if ($tem && $feitos[$n] == $o) { $linha = "&#10004; ".$linha; }
			elseif ($tem) { $linha = "<span style=\"color:#8a7a5a\">".$linha."</span>"; }
			if ($pode) {
				$linha = "<form action=\"talentos.php\" method=\"post\" style=\"margin:0\"><input type=\"hidden\" name=\"nivel\" value=\"$n\" /><input type=\"hidden\" name=\"opcao\" value=\"$o\" />".$linha." <input class=\"btnjogo bj-confirmar\" type=\"submit\" name=\"escolher\" value=\"".t("Escolher")."\" /></form>";
			}
			$page .= "<div class=\"cxb-linha\">".$linha."</div>";
		}
		$page .= "</div></div>";
	}
	display($page, t("Talentos"));
}
?>
