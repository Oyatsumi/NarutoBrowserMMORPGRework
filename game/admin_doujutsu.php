<?php // admin_doujutsu.php :: Painel do Administrador - Doujutsu (olhos): bônus e custo de cada olho/estágio, evolução do
// Sharingan e custos do teleporte do Rinnegan. Regras: funcoesdoujutsu.php e docs/DOUJUTSU.md.
// Os treinos que despertam os olhos (vila, mestre, level, graduação, sessões, intervalo) ficam em admin.php?do=treinos.
// Mesmo controle de acesso do admin.php (authlevel = 1); POST com token anti-CSRF e registro de auditoria.

include('lib.php');
include('cookies.php');
$link = opendb();
$userrow = checkcookies();
if ($userrow == false) { header("Location: login.php?do=login"); die(); }
if ($userrow["authlevel"] != 1) { header("Location: index.php"); die(); }
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
include_once('funcoesdoujutsu.php');
include_once('painelseguranca.php');
painelexigir("admin");

$mensagem = "";
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_confere()) { admindisplay("<div class=\"erro\">".t("Token de segurança inválido ou expirado. Volte, recarregue a página e tente novamente.")."</div>", t("Erro")); }
    registrarlog("doujutsu:salvar", 0, painelresumopost());
    $mensagem = djsalvar();
    $controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
}
djpagina($mensagem);


function djnumeros() { // Colunas numéricas de naruto_doujutsu => array(rótulo, mínimo, máximo).
    return array("custo_ch" => array(t("CH por intervalo"), 0, 1000), "intervalo" => array(t("Intervalo (s)"), 1, 3600),
        "agilidade" => array(t("Agilidade"), 0, 5000), "inteligencia" => array(t("Inteligência"), 0, 5000), "sorte" => array(t("Sorte"), 0, 5000),
        "precisao" => array(t("Precisão"), 0, 5000), "esquiva" => array(t("Esquiva (%)"), 0, 40), "critico" => array(t("Crítico (%)"), 0, 50),
        "ataque" => array(t("Ataque (%)"), 0, 100), "defesa" => array(t("Defesa (%)"), 0, 100), "ignora_defesa" => array(t("Ignora defesa (%)"), 0, 90),
        "droprate" => array(t("Chance de Drop"), 0, 100), "ver_inimigo" => array(t("Vê os drops do inimigo (1/0)"), 0, 1), "teleporte" => array(t("Teleporte (1/0)"), 0, 1), "reflete" => array(t("Chance de refletir o ataque (%)"), 0, 50), "ver_mapa" => array(t("Vê o mapa inteiro sem neblina (1/0)"), 0, 1));
}

function djgerais() { // Colunas dj_* de control => array(rótulo, mínimo, máximo) (as listas "a,b,c" ficam à parte).
    return array("dj_abatemargem" => array(t("Inimigo forte: level do inimigo >= level do jogador menos"), -100, 100),
        "dj_tele_tpbase" => array(t("Teleporte: TP fixo"), 0, 1000), "dj_tele_tp10" => array(t("Teleporte: TP por 10 casas"), 0, 1000),
        "dj_tele_chbase" => array(t("Teleporte: CH fixo"), 0, 5000), "dj_tele_ch10" => array(t("Teleporte: CH por 10 casas"), 0, 5000),
        "dj_tele_hpbase" => array(t("Teleporte: % do HP máximo fixo"), 0, 90), "dj_tele_hp10" => array(t("Teleporte: % do HP máximo por 10 casas"), 0, 90),
        "dj_tele_max" => array(t("Teleporte: distância máxima (0 = sem limite)"), 0, 10000));
}

function djlista3($texto) { // "40, 120,300" -> "40,120,300" (três inteiros >= 0) ou false.
    $p = array_map('trim', explode(",", (string)$texto));
    if (count($p) != 3) { return false; }
    foreach ($p as $v) { if (!preg_match('/^\d{1,5}$/', $v)) { return false; } }
    return implode(",", array_map('intval', $p));
}

function djsalvar() { // Grava tudo (só se não houver erro).
    $erros = array(); $sets = array();
    foreach (doujutsulista() as $o) {
        $id = intval($o["id"]); $s = array();
        foreach (djnumeros() as $c => $info) {
            $v = isset($_POST["o{$id}_$c"]) ? trim((string)$_POST["o{$id}_$c"]) : (string)$o[$c];
            if (!preg_match('/^-?\d+$/', $v) || intval($v) < $info[1] || intval($v) > $info[2]) { $erros[] = tf("%s: %s deve ficar entre %s e %s.", t($o["nome"]), $info[0], $info[1], $info[2]); continue; }
            $s[] = "`$c`='".intval($v)."'";
        }
        $nome = mb_substr(trim(pegartexto($_POST, "o{$id}_nome", $o["nome"])), 0, 60, "UTF-8");
        if ($nome != "") { $s[] = "nome='".$nome."'"; }
        $sets[$id] = implode(",", $s);
    }
    $ctl = array();
    foreach (djgerais() as $c => $info) {
        $v = trim(pegartexto($_POST, $c));
        if (!preg_match('/^-?\d+$/', $v) || intval($v) < $info[1] || intval($v) > $info[2]) { $erros[] = tf("%s deve ficar entre %s e %s.", $info[0], $info[1], $info[2]); continue; }
        $ctl[] = "`$c`='".intval($v)."'";
    }
    foreach (array("dj_abates" => t("Abates por estágio"), "dj_vitorias" => t("Vitórias por estágio")) as $c => $rot) {
        $v = djlista3(pegartexto($_POST, $c));
        if ($v === false) { $erros[] = tf("%s: use três números separados por vírgula (estágios 2, 3 e 4).", $rot); continue; }
        $ctl[] = "`$c`='$v'";
    }
    if (count($erros) > 0) { return "<div class=\"erro\">".implode("<br>", $erros)."</div>"; }
    foreach ($sets as $id => $s) { if ($s != "") { doquery("UPDATE {{table}} SET $s WHERE id='".intval($id)."' LIMIT 1", "doujutsu"); } }
    doquery("UPDATE {{table}} SET ".implode(",", $ctl)." WHERE id='1' LIMIT 1", "control");
    return "<div class=\"ok\">".t("Doujutsu salvo. Os bônus valem na hora para quem está com o olho ativo.")."</div>";
}

function djpagina($mensagem) {
    global $controlrow;
    // Relê a lista (pode ter mudado no POST).
    $lista = array(); $q = doquery("SELECT * FROM {{table}} ORDER BY ordem, estagio", "doujutsu");
    while ($r = mysqli_fetch_array($q, MYSQLI_ASSOC)) { $lista[] = $r; }
    $tab = "<table class=\"lista\"><tr><th></th>";
    foreach ($lista as $o) { $tab .= "<th><img src=\"images/olhos/".htmlspecialchars($o["imagem"]).".jpg\" width=\"82\" height=\"32\" alt=\"\" /><br>".htmlspecialchars(t($o["nome"]))."</th>"; }
    $tab .= "</tr><tr><td>".t("Nome")."</td>";
    foreach ($lista as $o) { $tab .= "<td><input type=\"text\" name=\"o".intval($o["id"])."_nome\" value=\"".htmlspecialchars($o["nome"], ENT_QUOTES)."\" size=\"12\" maxlength=\"60\" /></td>"; }
    $tab .= "</tr>";
    foreach (djnumeros() as $c => $info) {
        $tab .= "<tr><td>".$info[0]."</td>";
        foreach ($lista as $o) { $tab .= "<td><input type=\"number\" name=\"o".intval($o["id"])."_$c\" value=\"".intval($o[$c])."\" min=\"".$info[1]."\" max=\"".$info[2]."\" style=\"width:60px\" /></td>"; }
        $tab .= "</tr>";
    }
    $tab .= "</table>";
    $ger = "<table class=\"lista\">";
    $ger .= "<tr><td>".t("Sharingan: abates de inimigos fortes (com o olho ativo) para os estágios 2, 3 e 4 (0 = caminho desligado)")."</td><td><input type=\"text\" name=\"dj_abates\" value=\"".htmlspecialchars($controlrow["dj_abates"])."\" size=\"14\" /></td></tr>";
    $ger .= "<tr><td>".t("Sharingan: vitórias em duelos (desde o estágio atual) para os estágios 2, 3 e 4 (0 = caminho desligado)")."</td><td><input type=\"text\" name=\"dj_vitorias\" value=\"".htmlspecialchars($controlrow["dj_vitorias"])."\" size=\"14\" /></td></tr>";
    foreach (djgerais() as $c => $info) { $ger .= "<tr><td>".$info[0]."</td><td><input type=\"number\" name=\"$c\" value=\"".intval($controlrow[$c])."\" min=\"".$info[1]."\" max=\"".$info[2]."\" style=\"width:80px\" /></td></tr>"; }
    $ger .= "</table>";
    $usados = mysqli_fetch_array(doquery("SELECT COUNT(*) AS n, SUM(doujutsu_ativo) AS a FROM {{table}} WHERE doujutsu<>''", "users"));
    $csrf = csrf_campo();
    $t = "t";
    $page = <<<FIM
<h1>{$t("Doujutsu")}</h1>$mensagem
<p>{$t("Olhos que os jogadores despertam em treinos longos (um olho por jogador; despertar outro substitui o atual). Enquanto ativo, o olho gasta Chakra a cada intervalo e soma os bônus abaixo só no combate (lutas, duelos e PvP); nada é gravado nos atributos. Os treinos (vila, mestre, level, graduação, sessões e intervalo) ficam em")} <a href="admin.php?do=treinos">{$t("Treinos longos")}</a>.</p>
<p>{$t("Jogadores com olho:")} <b>{$usados["n"]}</b> ({$t("ativos agora:")} <b>{$usados["a"]}</b>)</p>
<form action="admin_doujutsu.php" method="post">$csrf
<h3>{$t("Olhos e estágios")}</h3>$tab
<h3>{$t("Evolução do Sharingan e teleporte do Rinnegan")}</h3>$ger
<p><input type="submit" value="{$t("Salvar")}" /></p></form>
FIM;
    admindisplay($page, t("Doujutsu"));
}

?>
