<?php // chat.php :: Recebe as falas do "Chat do Mapa" (o formulário fica na página de exploração do index.php).
// Cada coordenada do mapa tem o seu próprio chat, que guarda só as 10 últimas falas (tabela chatmap).

include_once('lib.php');
$link = opendb();
include_once('cookies.php');
$userrow = checkcookies();

if ($userrow == false) { header("Location: login.php?do=login"); die(); }
if ($userrow["authlevel"] == 2) { die(t("Sua conta foi bloqueada. Por favor tenta novamente mais tarde.")); }

if (pegartexto($_GET, "do") == "chatmap") { chatmap(); }
header("Location: index.php"); die();

function chatmap() { // Grava a fala na coordenada atual do jogador e apaga as mais antigas.

    global $userrow;

    $maximo = 10;     // Falas guardadas por coordenada.
    $seguidas = 5;    // Anti-flood: máximo de falas seguidas do mesmo jogador na mesma coordenada.
    $tamanhomax = 200; // Mesmo limite do campo do formulário.

    if (!isset($_POST["submit"])) { header("Location: index.php"); die(); }
    if (segundosmudo($userrow) > 0) { header("Location: index.php?conteudo=".rawurlencode(avisomudo($userrow))); die(); } // silenciado por um GM.

    // O lib.php já aplicou addslashes + htmlspecialchars; o texto original é recuperado para medir e cortar no tamanho certo.
    $fala = trim(stripslashes(html_entity_decode(pegartexto($_POST, "fala"), ENT_QUOTES, 'UTF-8')));
    $fala = str_replace(array("\r", "\n", "\t"), " ", $fala);
    if ($fala == "") { header("Location: index.php"); die(); }
    $fala = mb_substr($fala, 0, $tamanhomax, 'UTF-8');
    do { // O texto escapado precisa caber na coluna chatmap.fala (varchar 400).
        $falasegura = htmlspecialchars($fala, ENT_QUOTES, 'UTF-8');
        $fala = mb_substr($fala, 0, mb_strlen($fala, 'UTF-8') - 1, 'UTF-8');
    } while (mb_strlen($falasegura, 'UTF-8') > 400);

    $latitude = intval($userrow["latitude"]);
    $longitude = intval($userrow["longitude"]);
    $nome = addslashes($userrow["charname"]);

    // Anti-flood: não repete a mesma fala e não deixa um jogador ocupar o chat inteiro sozinho.
    $ultimasquery = doquery("SELECT name,fala FROM {{table}} WHERE latitude='$latitude' AND longitude='$longitude' ORDER BY id DESC LIMIT $seguidas", "chatmap");
    $minhas = 0;
    $primeira = true;
    while ($ultima = mysqli_fetch_array($ultimasquery)) {
        if ($ultima["name"] != $userrow["charname"]) { break; }
        if ($primeira && $ultima["fala"] == $falasegura) { header("Location: index.php"); die(); }
        $primeira = false;
        $minhas++;
    }
    if ($minhas >= $seguidas) {
        header("Location: index.php?conteudo=".rawurlencode(t("Você já mandou muitas mensagens seguidas aqui. Espere outro jogador falar."))); die();
    }

    doquery("INSERT INTO {{table}} (name, fala, latitude, longitude) VALUES ('$nome', '".addslashes($falasegura)."', '$latitude', '$longitude')", "chatmap");

    // Mantém só as $maximo falas mais recentes dessa coordenada.
    $contaquery = doquery("SELECT COUNT(*) AS total FROM {{table}} WHERE latitude='$latitude' AND longitude='$longitude'", "chatmap");
    $conta = mysqli_fetch_array($contaquery);
    if ($conta["total"] > $maximo) {
        doquery("DELETE FROM {{table}} WHERE latitude='$latitude' AND longitude='$longitude' ORDER BY id ASC LIMIT ".(intval($conta["total"]) - $maximo), "chatmap");
    }

    header("Location: index.php"); die();

}

?>
