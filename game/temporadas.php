<?php // temporadas.php :: Ranking de duelo por temporada (?t=numero para ver uma anterior). Regras: funcoescla.php.
// O fechamento da temporada vencida é feito aqui (e no users.php dos duelos) no primeiro acesso depois da data.
include_once('lib.php');
$link = opendb();
include_once('cookies.php');
$userrow = checkcookies();
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
include_once('funcoescla.php');

temporadaverificar();
$atual = temporadaaberta();
$num = pegarint($_GET, "t", intval($atual["numero"]));
$tem = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE numero='".intval($num)."' LIMIT 1", "temporadas"));
if (!$tem) { $tem = $atual; }

$page = "<center><h3>".t("Temporadas de duelo")."</h3></center>";
$cab = intval($tem["fechada"]) ? tf("Temporada %s (encerrada)", intval($tem["numero"])) : tf("Temporada %s (atual)", intval($tem["numero"]));
$info = "<div class=\"cxb-linha\">".tf("De %s até %s.", htmlspecialchars($tem["inicio"]), htmlspecialchars($tem["fim"]))." ".tf("Vitória: +%s pontos; derrota: -%s.", intval($controlrow["temporada_vitoria"]), intval($controlrow["temporada_derrota"]))." ".t("Os 3 primeiros ganham um título na ficha.")."</div>";
$q = doquery("SELECT p.pontos, p.vitorias, p.derrotas, u.charname FROM {{table}}_pontos p JOIN naruto_users u ON u.id=p.usuario WHERE p.temporada='".intval($tem["id"])."' AND u.authlevel='0' ORDER BY p.pontos DESC, p.vitorias DESC, p.derrotas ASC, p.usuario ASC LIMIT 100", "temporada");
$linhas = ""; $pos = 0;
while ($r = mysqli_fetch_array($q)) {
	$pos++;
	$linhas .= "<div class=\"cxb-linha\"><b>$pos.</b> <a href=\"javascript:mostrarchar('".htmlspecialchars($r["charname"])."')\">".htmlspecialchars($r["charname"])."</a> - ".tf("%s pontos (%s V / %s D)", intval($r["pontos"]), intval($r["vitorias"]), intval($r["derrotas"]))."</div>";
}
$page .= "<div class=\"cxb\"><div class=\"cxb-cab\"><b>$cab</b></div><div class=\"cxb-corpo\">$info".($linhas != "" ? $linhas : "<div class=\"cxb-vazio\">".t("Ninguém pontuou nesta temporada ainda.")."</div>")."</div></div>";

// Temporadas anteriores e os campeões.
$q = doquery("SELECT numero FROM {{table}} WHERE fechada='1' ORDER BY numero DESC LIMIT 24", "temporadas");
$linhas = "";
while ($r = mysqli_fetch_array($q)) {
	$tops = array();
	$qt = doquery("SELECT t.posicao, u.charname FROM {{table}} t JOIN naruto_users u ON u.id=t.usuario WHERE t.temporada='".intval($r["numero"])."' ORDER BY t.posicao", "titulos");
	while ($x = mysqli_fetch_array($qt)) { $tops[] = intval($x["posicao"])."º ".htmlspecialchars($x["charname"]); }
	$linhas .= "<div class=\"cxb-linha\"><a href=\"temporadas.php?t=".intval($r["numero"])."\">".tf("Temporada %s", intval($r["numero"]))."</a>: ".($tops ? implode(", ", $tops) : t("sem campeões"))."</div>";
}
$page .= "<div class=\"cxb\"><div class=\"cxb-cab\"><b>".t("Temporadas anteriores")."</b></div><div class=\"cxb-corpo\">".($linhas != "" ? $linhas : "<div class=\"cxb-vazio\">".t("Nenhuma temporada encerrada ainda.")."</div>")."</div></div>";
if ($userrow) { $page .= "<center>[ <a href=\"index.php\">".t("Voltar ao Jogo")."</a> ]</center>"; display($page, t("Temporadas de duelo")); }
else { display($page, t("Temporadas de duelo"), false, false, false); }
