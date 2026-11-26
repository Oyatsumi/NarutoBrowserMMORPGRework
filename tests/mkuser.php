<?php
// Uso (CLI): php mkuser.php <pasta_do_jogo> <usuario> [campo=valor ...]
// Cria/atualiza um jogador de teste diretamente no banco e imprime o valor do cookie de login.
chdir($argv[1]);
$_GET = $_POST = $_COOKIE = array();
include('lib.php');
opendb();
$u = $argv[2];
$q = doquery("SELECT * FROM {{table}} WHERE username='$u' LIMIT 1", "users");
if (mysqli_num_rows($q) == 0) {
    $hash = senha_hash("senha123");
    doquery("INSERT INTO {{table}} SET regdate=NOW(), verify='1', username='$u', password='$hash', email='$u@teste.local', charname='$u', charclass='1', difficulty='1', towns='0', spells='0', caixadepm='', mainmsg='', bancogeral='None', historico='None', questsaux='None', treinamento='None'", "users");
}
$sets = array();
for ($i = 3; $i < count($argv); $i++) { list($k, $v) = explode("=", $argv[$i], 2); $sets[] = "`$k`='" . addslashes($v) . "'"; }
if ($sets) doquery("UPDATE {{table}} SET " . implode(",", $sets) . " WHERE username='$u' LIMIT 1", "users");
$row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE username='$u' LIMIT 1", "users"));
echo $row["id"] . " " . $row["username"] . " " . cookie_assinatura($row["id"], $row["username"], $row["password"]) . " 0\n";
