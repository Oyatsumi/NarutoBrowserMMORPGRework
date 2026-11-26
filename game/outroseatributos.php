<?php // outroseatributos.php :: Distribuição dos pontos de atributo (Agilidade, Sorte, Determinação, Precisão e Inteligência).

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();

if (isset($_GET["do"])) {
	$do = $_GET["do"];
	if ($do == "atributos") { atributos(); }
}

function atributos() { // Mostra os atributos do jogador e recebe o formulário de distribuição dos pontos.
	global $topvar, $userrow;
	$topvar = true;

	if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
	if ($userrow["batalha_timer2"] == 5) { display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "),t("Erro"),false,false,false); die(); }
	if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t('Você não pode acessar essa função no meio de uma batalha!'))); die(); }

	// Valores atuais. Cada nível ganho dá 5 pontos para distribuir (ver victory() no fight.php).
	// Cada atributo melhora uma fórmula da luta e o bônus de um elemento:
	// Agilidade (raio) = esquiva, Sorte (água) = drops, Determinação (fogo) = hit excelente,
	// Precisão (vento) = acertar o inimigo, Inteligência (terra) = inimigo continuar dormindo.
	$agilidade = intval($userrow["agilidade"]);
	$determinacao = intval($userrow["determinacao"]);
	$sorte = intval($userrow["sorte"]);
	$precisao = intval($userrow["precisao"]);
	$inteligencia = intval($userrow["inteligencia"]);
	$pontoatributos = intval($userrow["pontoatributos"]);

	if (isset($_POST["submit"])) {

		// Lê cada campo do formulário (antes era extract($_POST), que deixava o jogador sobrescrever qualquer variável).
		// Campo em branco = 0; texto, número negativo ou soma acima dos pontos disponíveis são recusados.
		$campos = array("agilidadep"=>"agilidade", "sortep"=>"sorte", "determinacaop"=>"determinação", "precisaop"=>"precisão", "inteligenciap"=>"inteligência");
		$adicionar = array();
		foreach ($campos as $campo => $nome) {
			$valor = trim(pegartexto($_POST, $campo));
			if ($valor == "") { $valor = 0; }
			if (!is_numeric($valor)) { header('Location: ./outroseatributos.php?do=atributos&conteudo='.rawurlencode(tf("O campo de %s deve ser um número ou deixado em branco.", t($nome)))); die(); }
			if ($valor < 0) { header('Location: ./outroseatributos.php?do=atributos&conteudo='.rawurlencode(tf("O campo de %s não pode ser negativo.", t($nome)))); die(); }
			if ($valor > $pontoatributos) { $valor = $pontoatributos + 1; } // evita números gigantes (a soma abaixo já recusa).
			$adicionar[$campo] = (int)floor((float)$valor);
		}
		$pontostotal = array_sum($adicionar);

		if ($pontoatributos <= 0) { header('Location: ./outroseatributos.php?do=atributos&conteudo='.rawurlencode(t('Você não possui pontos para distribuição.'))); die(); }
		if ($pontostotal > $pontoatributos) { header('Location: ./outroseatributos.php?do=atributos&conteudo='.rawurlencode(tf("Você não pode distribuir mais pontos que %s pontos.", $pontoatributos))); die(); }

		$pontosrestantes = $pontoatributos - $pontostotal;
		$agilidade += $adicionar["agilidadep"];
		$sorte += $adicionar["sortep"];
		$determinacao += $adicionar["determinacaop"];
		$precisao += $adicionar["precisaop"];
		$inteligencia += $adicionar["inteligenciap"];

		// Uma única query; o "AND pontoatributos=..." impede gastar os mesmos pontos duas vezes com envios simultâneos.
		doquery("UPDATE {{table}} SET agilidade='$agilidade', determinacao='$determinacao', precisao='$precisao', inteligencia='$inteligencia', sorte='$sorte', pontoatributos='$pontosrestantes' WHERE id='".intval($userrow["id"])."' AND pontoatributos='$pontoatributos' LIMIT 1","users");

		header('Location: ./outroseatributos.php?do=atributos&conteudo='.rawurlencode(t('Seus pontos foram distribuidos corretamente.'))); die();
	}

	// Efeito real de cada atributo agora (mesmas fórmulas da luta: funcoesinclusas.php :: limiteesquiva(), limitecritico(), fatorsorte(), bonusinteligencia()).
	include_once('funcoesinclusas.php');
	$pct = function ($x) { return rtrim(rtrim(number_format($x, 1, ",", "."), "0"), ","); };
	$efeitos = array(
		"agilidade" => tf("Esquiva: %s%% (teto 40%%)", $pct(limiteesquiva($userrow["dexterity"], $agilidade) / 150 * 100)),
		"sorte" => tf("Drops ×%s", $pct(fatorsorte($sorte))),
		"determinacao" => tf("Hit excelente: %s%% (teto 50%%)", $pct(limitecritico($userrow["strength"], $determinacao) / 150 * 100)),
		"precisao" => tf("Esquiva do inimigo −%s%%", $pct(floor($precisao * 3 / 100) / 2)),
		"inteligencia" => tf("Dano de ninjutsu +%s%% (máx. 50%%)", $pct(bonusinteligencia($inteligencia))));
	$conteudo = strip_tags(lermsg("conteudo"));
	if ($conteudo != ""){ $conteudo = "<center><font color=\"brown\">".$conteudo."</font></center><br>"; }
	$page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/distribuir.gif\" /></center></td></tr></table>
	$conteudo
	".($pontoatributos > 0
		? "<div style=\"margin:6px auto 8px;max-width:300px;text-align:center;background:#fff1c7;border:2px solid #c25b00;border-radius:8px;padding:6px 8px;color:#452202;box-shadow:0 0 0 3px #f5c66a\"><div style=\"font-size:12px;font-weight:bold\">".t("Pontos para Distribuir")."</div><div style=\"font-size:30px;line-height:34px;font-weight:bold;color:#c25b00\">".intval($pontoatributos)."</div><div style=\"font-size:11px\">".t("Escolha abaixo onde colocar seus pontos.")."</div></div>"
		: "<div style=\"margin:6px auto 8px;max-width:300px;text-align:center;font-size:11px;color:#7a5a30\">".t("Você não tem pontos para distribuir agora. Cada nível ganho dá 5 pontos.")."</div>")."
	<center><table cellpadding=\"0\" cellspacing=\"0\"><tr><td><table><tr><td colspan=\"3\" bgcolor=\"#452202\"><center><font color=\"white\">".t("Meus Pontos")."</font></center></td></tr>
	<tr bgcolor=\"#E4D094\"><td>".t("Pontos para Distribuir")." </td><td colspan=\"2\"><b style=\"color:#c25b00;font-size:13px\">$pontoatributos</b></td></tr>
	".linhaefeito("#FFF1C7", t("Agilidade"), "raio", t("Elemento Relâmpago"), $agilidade, $efeitos["agilidade"])."
	".linhaefeito("#E4D094", t("Sorte"), "agua", t("Elemento Água"), $sorte, $efeitos["sorte"])."
	".linhaefeito("#FFF1C7", t("Determinação"), "fogo", t("Elemento Fogo"), $determinacao, $efeitos["determinacao"])."
	".linhaefeito("#E4D094", t("Precisão"), "vento", t("Elemento Vento"), $precisao, $efeitos["precisao"])."
	".linhaefeito("#FFF1C7", t("Inteligência"), "terra", t("Elemento Terra"), $inteligencia, $efeitos["inteligencia"])."
	<tr><td colspan=\"3\" class=\"small\" style=\"max-width:300px;color:#6b4a26\">".t("Cada atributo também dá +1% de dano a cada 20 pontos nos jutsus do seu elemento (com EP); no seu elemento principal contam em dobro e no secundário, x1,5.")."</td></tr>
</table></td>".gettemplate("outroseatributos")
	."<div style=\"text-align:center;margin-top:10px\"><a href=\"index.php\" id=\"botaovoltarjogo\" class=\"btnjogo bj-neutro\"><img src=\"images/seta_oeste.png\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".t("Voltar ao jogo")."</a></div>";
	display($page, t("Distribuir Pontos"), false, false, false);
}

function linhaefeito($cor, $nome, $icone, $titulo, $valor, $efeito) { // Linha da tabela "Meus Pontos": atributo, valor e o efeito real dele agora.
	return "<tr bgcolor=\"$cor\"><td>".$nome."<img src=\"images/".$icone.".gif\" title=\"".$titulo."\"></td><td>$valor</td><td class=\"small\" style=\"color:#4b2a0c\">".$efeito."</td></tr>";
}

?>
