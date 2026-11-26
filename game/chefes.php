<?php // chefes.php :: Chefes da Semana: onde fica cada chefe, se o jogador já o derrotou nesta semana e o ranking da semana.
// Regras em funcoeschefes.php (semana ISO no fuso America/Sao_Paulo; 1 abate por jogador/chefe/semana).
include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();
if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."), t("Erro"), false, false, false); die(); }
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
include_once('funcoeschefes.php');
include_once('funcoescartas.php'); // cartaodomonstro(): o mesmo cartão da luta para cada chefe.

$semana = chefes_semana();
// Próxima segunda-feira 00:00 (horário de Brasília): quando os chefes voltam.
$volta = new DateTime("next monday", new DateTimeZone("America/Sao_Paulo"));

$page = "<center>[ <a href=\"index.php\">".t("Voltar ao Jogo")."</a> ]</center><br />";
$page .= "<div class=\"cxb\" id=\"chefeslista\"><div class=\"cxb-cab\">".t("Chefes da Semana")." <span>".tf("Voltam em %s", $volta->format("d/m H:i"))."</span></div><div class=\"cxb-corpo\">";
$page .= "<div class=\"cxb-linha\">".t("Pise na casa de um chefe para enfrentá-lo. Cada chefe pode ser derrotado uma vez por semana e sempre deixa cair o seu item raro.")."</div>";
foreach (chefes_lista() as $c) {
	$feito = chefes_derrotou($userrow["id"], $c["monster_id"], $semana);
	$estado = $feito ? "<b style=\"color:#2e6b12\">".t("Derrotado nesta semana")."</b>" : "<b style=\"color:#8b1a00\">".t("Disponível")."</b>";
	$page .= "<div class=\"cxb-linha chefe\" data-chefe=\"".intval($c["monster_id"])."\" data-feito=\"".($feito ? 1 : 0)."\">"
		.cartaochefe($c["monster_id"])
		."<img src=\"layoutnovo/equipamentos/drops/".intval($c["drop_id"]).".gif\" width=\"24\" height=\"24\" align=\"left\" style=\"margin-right:6px\" title=\"".htmlspecialchars(t($c["dropnome"]), ENT_QUOTES)."\" alt=\"\">"
		."<b>".htmlspecialchars(t($c["name"]), ENT_QUOTES)."</b> <img src=\"images/".htmlspecialchars($c["elemento"], ENT_QUOTES).".gif\" alt=\"\"> &middot; ".tf("Nível %s", intval($c["level"]))
		." &middot; ".tf("Casa [%s,%s]", intval($c["latitude"]), intval($c["longitude"]))." &middot; $estado"
		."<br /><span style=\"font-size:10px\">".tf("Drop garantido: %s", htmlspecialchars(t($c["dropnome"]), ENT_QUOTES))."</span></div>";
}
$page .= "</div></div>";

// Ranking: mais chefes derrotados na semana; empate = quem completou primeiro (último abate mais cedo).
$page .= "<div class=\"cxb\" id=\"chefesranking\"><div class=\"cxb-cab\">".t("Ranking da semana")." <span>".htmlspecialchars($semana, ENT_QUOTES)."</span></div><div class=\"cxb-corpo\">";
$q = doquery("SELECT u.charname, COUNT(*) AS n, MAX(a.data) AS fim, MAX(a.id) AS ultimo FROM {{table}} a JOIN naruto_users u ON u.id=a.jogador WHERE a.semana='".addslashes($semana)."' AND u.authlevel<>'2' GROUP BY a.jogador, u.charname ORDER BY n DESC, fim ASC, ultimo ASC LIMIT 50", "chefes_abates");
if (mysqli_num_rows($q) == 0) { $page .= "<div class=\"cxb-vazio\">".t("Ninguém derrotou um chefe nesta semana ainda.")."</div>"; }
$pos = 0;
while ($r = mysqli_fetch_array($q)) {
	$pos++;
	$nome = htmlspecialchars($r["charname"], ENT_QUOTES);
	$page .= "<div class=\"cxb-linha rankchefe\"><b>$pos.</b> <a href=\"javascript:mostrarchar('".str_replace("'", "\\'", $nome)."')\">$nome</a> &middot; ".tf("%s chefe(s)", intval($r["n"]))." &middot; <span style=\"font-size:10px\">".date("d/m H:i", strtotime($r["fim"]))."</span></div>";
}
$page .= "</div></div>";

function cartaochefe($id) { // Cartão novo do chefe (cartaodomonstro) com o HP cheio, acima da linha de dados.
	$m = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "monsters"));
	return $m ? "<div class=\"chefecarta\" style=\"margin:2px 0 6px\">".cartaodomonstro($m, $m["maxhp"])."</div>" : "";
}

display($page, t("Chefes da Semana"), false, false, false);
?>
