<?php // explore.php :: Movimentação pelo mapa (botões de direção e "andar" automático), chegada em cidades e chance de encontrar inimigos.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

if (!function_exists('move')){
function move() { // Anda 1 casa na direção do botão clicado (formulário Norte/Sul/Leste/Oeste da coluna da esquerda).

    global $userrow, $controlrow;

    if ($userrow["currentaction"] == "Fighting") { header("Location: index.php?do=fight"); die(); }

    // Uma direção por vez (antes, mandar north e east juntos andava na diagonal).
    if (isset($_POST["north"])) { $direcao = "north"; }
    elseif (isset($_POST["south"])) { $direcao = "south"; }
    elseif (isset($_POST["east"])) { $direcao = "east"; }
    elseif (isset($_POST["west"])) { $direcao = "west"; }
    elseif (isset($_POST["sairvila"])) { // Botão "Sair da vila": sorteia um dos lados que não passa da borda do mapa.
        $lim = intval($controlrow["gamesize"]); $la = intval($userrow["latitude"]); $lo = intval($userrow["longitude"]);
        $lados = array();
        if ($la < $lim) { $lados[] = "north"; } if ($la > -$lim) { $lados[] = "south"; }
        if ($lo < $lim) { $lados[] = "east"; } if ($lo > -$lim) { $lados[] = "west"; }
        if (count($lados) == 0) { header("Location: index.php"); die(); }
        $direcao = $lados[mt_rand(0, count($lados) - 1)];
    }
    else { header("Location: index.php"); die(); }

    andarumacasa($direcao, "index.php");

}}

if (!function_exists('andar')){
function andar($lat, $long) { // Andar automático: dá 1 passo em direção à coordenada clicada no mini mapa (o index.php recarrega e chama de novo).

    global $userrow;

    if ($userrow["currentaction"] == "Fighting") { header("Location: index.php?do=fight"); die(); }

    $lat = intval($lat);
    $long = intval($long);
    $latitude = intval($userrow["latitude"]);
    $longitude = intval($userrow["longitude"]);

    // Primeiro acerta a longitude (leste/oeste), depois a latitude (norte/sul).
    $direcao = "";
    if ($lat < $latitude) { $direcao = "south"; }
    if ($lat > $latitude) { $direcao = "north"; }
    if ($long > $longitude) { $direcao = "east"; }
    if ($long < $longitude) { $direcao = "west"; }

    if ($direcao == "") { voltarcommensagem(t("Você chegou em seu destino.")); }

    andarumacasa($direcao, "index.php?re=true&latitude=".$lat."&longitude=".$long, array($lat, $long));

}}

if (!function_exists('andarumacasa')){
function andarumacasa($direcao, $voltarpara, $alvo = null) { // Move o jogador 1 casa (ou mais, com o Passo Veloz): ganha NP, entra na cidade se houver uma ali ou sorteia uma luta. $alvo = array(lat, long) do "andar" automático.

    global $userrow, $controlrow, $link;

    // Passo Veloz: o passo cobra Chakra/TP e anda várias casas, então trava o jogador (SELECT ... FOR UPDATE) e relê a
    // posição: dois passos ao mesmo tempo andam um depois do outro (cada cobrança vale exatamente as casas andadas).
    $travado = false;
    if (intval(isset($userrow["passo_jutsu"]) ? $userrow["passo_jutsu"] : 0) > 0) {
        mysqli_begin_transaction($link); $travado = true;
        $userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
        if ($userrow["currentaction"] == "Fighting") { mysqli_commit($link); header("Location: index.php?do=fight"); die(); }
    }

    // O mapa vai de -gamesize a +gamesize nos dois eixos.
    $limite = intval($controlrow["gamesize"]);
    $latitude = intval($userrow["latitude"]);
    $longitude = intval($userrow["longitude"]);

    // Passo Veloz ligado (users.passo_jutsu): o passo anda tier + 1 casas e cobra o custo do jutsu (passovelozcasas()).
    $avisopasso = "";
    $casas = passovelozcasas($avisopasso);

    // Caveira exposta (lib.php :: bloqueiovila()): quem matou há pouco com caveira não entra em vilas. Se o primeiro passo
    // cairia numa vila, nem anda (e não ganha NP); nos passos seguintes do Passo Veloz, para na casa de antes (abaixo).
    $bloqueio = bloqueiovila($userrow);
    if ($bloqueio != "") {
        $plat = max(-$limite, min($limite, $latitude + ($direcao == "north" ? 1 : 0) - ($direcao == "south" ? 1 : 0)));
        $plon = max(-$limite, min($limite, $longitude + ($direcao == "east" ? 1 : 0) - ($direcao == "west" ? 1 : 0)));
        if (mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE latitude='$plat' AND longitude='$plon' LIMIT 1", "towns")) > 0) {
            if ($travado) { mysqli_commit($link); }
            voltarcommensagem($bloqueio);
        }
    }

    // Cada passo dá 1 ponto natural (NP), até o máximo, e recupera o que os equipamentos dão por passo: regenhp (HP),
    // regenmp (CH), regentp (TP), regennp (NP, além do 1 de sempre) e regenep (EP), cada um até o seu máximo.
    $rg = array();
    foreach (array("hp", "mp", "tp", "np", "ep") as $pt) { $rg[$pt] = max(0, intval(isset($userrow["regen".$pt]) ? $userrow["regen".$pt] : 0)); }
    $rg["np"] += 1;
    include_once('funcoestalentos.php');
    $rg["mp"] += talentosmods($userrow)["tal_regenmp"]; // talento Respiração Ritmada.
    $sets = array();
    foreach ($rg as $pt => $v) { if ($v > 0) { $sets[] = "current$pt=IF(current$pt>=max$pt, current$pt, LEAST(current$pt+'$v', max$pt))"; } }
    doquery("UPDATE {{table}} SET ".implode(", ", $sets)." WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    include_once('funcoesneblina.php');
    include_once('funcoesmissoesextras.php');

    // Anda casa por casa (1 sem o Passo Veloz). Para antes da hora na borda do mapa, ao chegar no destino do "andar",
    // ao pisar numa cidade (entra nela) ou na casa de um NPC (para poder falar com ele).
    for ($k = 1; $k <= $casas; $k++) {
        if ($k > 1 && $alvo !== null) { $direcao = direcaopara($latitude, $longitude, $alvo[0], $alvo[1]); if ($direcao == "") { break; } }
        $novalat = $latitude; $novalong = $longitude;
        if ($direcao == "north") { $novalat++; }
        elseif ($direcao == "south") { $novalat--; }
        elseif ($direcao == "east") { $novalong++; }
        elseif ($direcao == "west") { $novalong--; }
        $novalat = max(-$limite, min($limite, $novalat));
        $novalong = max(-$limite, min($limite, $novalong));
        if ($k > 1 && $novalat == $latitude && $novalong == $longitude) { break; } // borda do mapa.
        if ($k > 1 && $bloqueio != "" && mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE latitude='$novalat' AND longitude='$novalong' LIMIT 1", "towns")) > 0) { $parou = $bloqueio; break; } // vila fechada (caveira).
        if ($k > 1) { missoesextras_posicao(array("id" => $userrow["id"], "latitude" => $latitude, "longitude" => $longitude)); } // casa intermediária (visitar local/viagem).
        $latitude = $novalat; $longitude = $novalong;

        // Neblina do mapa-múndi: entrou num quadrante novo -> ele passa a aparecer no mapa (só faz UPDATE na primeira vez).
        neblina_descobrir($userrow, $latitude, $longitude);
        include_once('funcoesdiarias.php'); diarias_contar($userrow["id"], "andar", 1); // missões diárias: casa andada.

        // Pisou numa cidade: entra nela sem gastar TP (e o mapa dela passa a ser conhecido).
        $townquery = doquery("SELECT id FROM {{table}} WHERE latitude='$latitude' AND longitude='$longitude' LIMIT 1", "towns");
        if (mysqli_num_rows($townquery) > 0) {
            $townrow = mysqli_fetch_array($townquery);
            include_once('towns.php');
            if ($travado) { mysqli_commit($link); }
            travelto($townrow["id"], false);
            die();
        }
        if ($k < $casas && mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE latitude='$latitude' AND longitude='$longitude' AND ativo='1' LIMIT 1", "npcs")) > 0) { break; } // NPC: para aqui.
        include_once('funcoeschefes.php');
        if ($k < $casas && chefes_pendente($userrow["id"], $latitude, $longitude)) { break; } // chefe semanal: o Passo Veloz para na casa dele.
    }
    if (!empty($parou)) { $voltarpara = "index.php"; $avisopasso = trim($avisopasso." ".$parou); } // para o "andar" automático.
    if ($avisopasso != "") { $voltarpara .= ((strpos($voltarpara, "?") === false) ? "?" : "&")."conteudo=".rawurlencode($avisopasso); }

    // Incenso Repelente aceso (backpack.php): o passo gasta 1 dos passos restantes e nenhum inimigo aleatório aparece.
    // O desconto é atômico (repelente_passos>0 no WHERE): dois passos simultâneos não usam o mesmo passo.
    include_once('funcoesinclusas.php');
    $repelido = false;
    if (repelenteativo($userrow) > 0) {
        doquery("UPDATE {{table}} SET repelente_passos=repelente_passos-1 WHERE id='".intval($userrow["id"])."' AND repelente_passos>0 AND repelente_ate>'".time()."' LIMIT 1", "users");
        $repelido = (mysqli_affected_rows($GLOBALS["link"]) == 1);
    }

    // Chance de luta: 1 em "chancetofight" (configurável no painel; 0 ou menos = nunca luta).
    $chancetofight = intval($controlrow["chancetofight"]);
    // Chefe semanal (funcoeschefes.php): na casa dele, ainda não derrotado nesta semana, a luta sempre começa (nem o incenso evita);
    // o fight.php vê a mesma casa no 1º turno e escolhe o chefe em vez de sortear.
    include_once('funcoeschefes.php');
    $chefeaqui = chefes_pendente($userrow["id"], $latitude, $longitude);
    if ($chefeaqui || (!$repelido && $chancetofight > 0 && rand(1, $chancetofight) == 1)) {
        $action = "currentaction='Fighting', currentfight='1', pocoesluta='', roubo_luta='0', lutaultimaacao='".time()."',"; // lutaultimaacao: luta parada demais = abandono (fight.php :: lutasabandonadas()). pocoesluta: limite de poções zera a cada luta.
    } else {
        $action = "currentaction='Exploring',";
    }

    doquery("UPDATE {{table}} SET $action latitude='$latitude', longitude='$longitude', dropcode='0' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    // Missões extras do tipo "visitar_local": marca a coordenada nova (funcoesmissoesextras.php).
    missoesextras_posicao(array("id" => $userrow["id"], "latitude" => $latitude, "longitude" => $longitude));
    if ($travado) { mysqli_commit($link); }
    header("Location: ".$voltarpara);
    die();

}}


if (!function_exists('direcaopara')){
function direcaopara($lat, $long, $alvolat, $alvolong) { // Direção do próximo passo até o alvo (mesma ordem do andar(): longitude primeiro), "" = chegou.
    if ($long > $alvolong) { return "west"; }
    if ($long < $alvolong) { return "east"; }
    if ($lat < $alvolat) { return "north"; }
    if ($lat > $alvolat) { return "south"; }
    return "";
}}

if (!function_exists('passovelozcasas')){
function passovelozcasas(&$aviso) { // Passo Veloz (users.passo_jutsu = id do jutsu): cobra CH (spells.mp, com a Economia de Chakra) e TP (spells.custo_tp)
    // deste passo numa gravação atômica e devolve quantas casas andar (tier + 1). Sem Chakra/TP o jutsu desliga sozinho e o passo é normal.
    // Encontros aleatórios: continua UM sorteio por passo (na casa final), então andar rápido também expõe a menos lutas por casa.
    global $userrow, $link;
    $id = intval(isset($userrow["passo_jutsu"]) ? $userrow["passo_jutsu"] : 0);
    if ($id <= 0) { return 1; }
    include_once('funcoesinclusas.php');
    $s = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "spells"));
    if (!$s || (string)$s["acao_menu"] != "passo" || !jutsuconhece($userrow, $id)) {
        doquery("UPDATE {{table}} SET passo_jutsu='0' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
        return 1;
    }
    $mp = custochakra($s["mp"], $s); $tp = intval($s["custo_tp"]);
    doquery("UPDATE {{table}} SET currentmp=currentmp-$mp, currenttp=currenttp-$tp WHERE id='".intval($userrow["id"])."' AND passo_jutsu='$id' AND currentmp>=$mp AND currenttp>=$tp LIMIT 1", "users");
    if (mysqli_affected_rows($link) != 1) {
        doquery("UPDATE {{table}} SET passo_jutsu='0' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
        $aviso = tf("%s desligou: acabou o Chakra/TP.", t($s["name"]));
        return 1;
    }
    return max(1, min(5, intval($s["tier"]) + 1));
}}

?>
