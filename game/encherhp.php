<?php // encherhp.php :: Descansar: recupera HP, CH e NP aos poucos enquanto o jogador fica parado (no mapa ou na cidade).
// A página se recarrega quando o próximo ganho fica liberado (meta refresh). A cada 10s no mapa ou 3s na cidade o jogador recupera
// pontos (mapa: +2 HP, +4 CH, +2 NP; cidade: +4 HP, +6 CH, +5 NP, +1 TP e +1 EP), sem passar do máximo.
// O horário do último ganho fica em users.ultimodescanso; várias abas abertas não recuperam várias vezes.
// Depois o index.php é incluído para mostrar a página normal com a caixa "Descansando" no topo ($indexconteudo).

include_once('lib.php');
$link = opendb();
include_once('cookies.php');
$userrow = checkcookies();

if ($userrow == false) { header("Location: login.php?do=login"); die(); }
if ($userrow["authlevel"] == 2) { die(t("Sua conta foi bloqueada. Por favor tenta novamente mais tarde.")); }
if ($userrow["currentaction"] == "Fighting") { header("Location: index.php?do=fight&conteudo=".rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }
if ($userrow["batalha_timer2"] == 5) {
    display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "),t("Erro"),false,false,false);
}
if ($userrow["currentaction"] != "Exploring" && $userrow["currentaction"] != "In Town") { header("Location: index.php"); die(); }

if (!isset($controlrow) || !is_array($controlrow)) { $controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control")); }
if ($userrow["currentaction"] == "In Town") {
    $espera = 3; $ganhohp = 4; $ganhomp = 6; $ganhonp = 5;
    // Na cidade o descanso também recupera TP e EP (control.tpdescanso / control.epdescanso por intervalo).
    $ganhotp = isset($controlrow["tpdescanso"]) ? intval($controlrow["tpdescanso"]) : 1;
    $ganhoep = isset($controlrow["epdescanso"]) ? intval($controlrow["epdescanso"]) : 1;
} else {
    $espera = 10; $ganhohp = 2; $ganhomp = 4; $ganhonp = 2; $ganhotp = 0; $ganhoep = 0;
}

// Caminhada Ninja (treinoslongos.php): +users.treino_tpregen de TP a cada intervalo, na cidade e no mapa.
$ganhotp += isset($userrow["treino_tpregen"]) ? max(0, intval($userrow["treino_tpregen"])) : 0;

include_once('funcoesinclusas.php'); // descansomult()
// Item de descanso ativo (backpack.php): multiplica todos os ganhos (arredonda para cima).
$multdesc = descansomult($userrow);
if ($multdesc > 100) {
    foreach (array("ganhohp", "ganhomp", "ganhonp", "ganhotp", "ganhoep") as $v) { $$v = (int)ceil($$v * $multdesc / 100); }
}

// O link para descansar (e cada recarga automática) leva o token anti-CSRF.
if (!csrf_confere()) { header("Location: index.php"); die(); }

// O tempo do último ganho fica em users.ultimodescanso (horário Unix). Não usamos mais sleep() no servidor:
// a página se recarrega no fim de cada intervalo e só aplica o ganho quando o intervalo já passou.
$agora = time();
$ultimo = intval($userrow["ultimodescanso"]);
if ($ultimo == 0 || $agora - $ultimo > 60) {
    // Começando a descansar agora (ou depois de muito tempo parado): marca o início do intervalo.
    doquery("UPDATE {{table}} SET ultimodescanso='$agora' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    $mensagem = t("Aguarde... Você está descansando...");
} elseif ($agora - $ultimo >= $espera) {
    // O WHERE com o valor lido garante que duas abas abertas não recuperem duas vezes no mesmo intervalo.
    doquery("UPDATE {{table}} SET currenthp=LEAST(currenthp+$ganhohp, maxhp), currentmp=LEAST(currentmp+$ganhomp, maxmp), currentnp=LEAST(currentnp+$ganhonp, maxnp), currenttp=LEAST(currenttp+$ganhotp, maxtp), currentep=LEAST(currentep+$ganhoep, maxep), ultimodescanso='$agora' WHERE id='".intval($userrow["id"])."' AND ultimodescanso='$ultimo' AND currentaction='".addslashes($userrow["currentaction"])."' LIMIT 1", "users");
    if (mysqli_affected_rows($link) == 1) {
        $mensagem = ($ganhotp > 0 || $ganhoep > 0) ? tf("Você recuperou %s de HP, %s de CH, %s de NP, %s de TP e %s de EP...", $ganhohp, $ganhomp, $ganhonp, $ganhotp, $ganhoep) : tf("Você recuperou %s de HP, %s de CH e %s de NP...", $ganhohp, $ganhomp, $ganhonp);
        if ($multdesc > 100) { $mensagem .= " ".tf("(×%s pelo item de descanso)", descansomulttexto($multdesc)); }
    } else {
        $mensagem = t("Aguarde... Você está descansando...");
    }
} else {
    $mensagem = t("Aguarde... Você está descansando...");
}

// Próxima recarga só quando o próximo ganho estiver liberado (mapa: 10 s, cidade: 3 s), em vez de a cada 1 s:
// bem menos requisições ao servidor e o mesmo ritmo de recuperação.
$ultimoagora = intval(mysqli_fetch_array(doquery("SELECT ultimodescanso FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1", "users"))["ultimodescanso"]);
$recarga = max(1, min($espera, $espera - ($agora - $ultimoagora)));

// Tudo cheio (o que este descanso recupera): para sozinho, sem recarga automática, para não ficar pedindo páginas ao servidor à toa.
$cheio = mysqli_fetch_array(doquery("SELECT currenthp>=maxhp AND currentmp>=maxmp AND currentnp>=maxnp".($ganhotp > 0 ? " AND currenttp>=maxtp" : "").($ganhoep > 0 ? " AND currentep>=maxep" : "")." AS cheio FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1", "users"));
if ($cheio && intval($cheio["cheio"]) == 1) {
    doquery("UPDATE {{table}} SET ultimodescanso='0' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    global $indexconteudo, $valorlib;
    $indexconteudo = "<div style=\"z-index: 2;position:relative;\"><center><table bgcolor=\"#452202\"><tr><td width=\"18\"></td><td width=\"*\"><center><font color=white>".t("Descanso concluído")."</font></center></td><td width=\"18\"><a href=\"index.php\"><img src=\"images/deletar2.jpg\" title=\"".t("Fechar")."\" alt=\"X\" border=\"0\"></a></td></tr><tr><td background=\"layoutnovo/menumeio/meio2.png\" colspan=\"3\"><font color=\"black\"><center>".(($ganhotp > 0 || $ganhoep > 0) ? t("Você está com HP, CH, NP, TP e EP cheios. O descanso terminou.") : t("Você está com HP, CH e NP cheios. O descanso terminou."))."</center></font></td></tr></table></center></div>";
    $valorlib = 1;
    include('index.php');
    die();
}

global $indexconteudo, $valorlib;
$indexconteudo = "<div style=\"z-index: 2;position:relative;\"><center><table  bgcolor=\"#452202\"><tr><td width=\"18\"></td><td width=\"*\"><center><font color=white>".t("Descansando")."</font></center></td><td width=\"18\"><a href=\"index.php\"><img src=\"images/deletar2.jpg\" title=\"".t("Parar de Descansar")."\"  alt=\"X\" border=\"0\"></a></td></tr><tr><td background=\"layoutnovo/menumeio/meio2.png\" colspan=\"3\"><font color=\"black\"><center>".$mensagem."<br><span class=\"small\">".tf("Próxima recuperação em %s s", "<b><span data-contagem=\"".intval($recarga)."\">".intval($recarga)."</span></b>")."</span><meta HTTP-EQUIV='refresh' CONTENT='".$recarga.";URL=encherhp.php?csrf=".csrf_token()."'></center></font></td></tr></table></center></div>";
$valorlib = 1; // Avisa que o lib.php já foi carregado (padrão antigo; o index.php agora usa include_once).
include('index.php');
die();

?>
