<?php
// criar_admin.php :: usado pelo install.sh. Atualiza a tabela control (nome, endereço e e-mail do jogo)
// e cria a conta do administrador com a senha em password_hash. Os dados chegam por variáveis de ambiente
// (assim a senha não aparece na lista de processos).
if (PHP_SAPI != "cli") { die("Somente pela linha de comando.\n"); }
chdir(getenv("NARUTO_PASTA_JOGO"));
$_GET = $_POST = $_COOKIE = array();
include("lib.php");
opendb();
global $link;
$e = function ($v) use (&$link) { return mysqli_real_escape_string($link, htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8")); };

$conta = getenv("NARUTO_ADMIN_CONTA");
$personagem = getenv("NARUTO_ADMIN_PERSONAGEM");
$email = getenv("NARUTO_ADMIN_EMAIL");
$senha = getenv("NARUTO_ADMIN_SENHA");
// A senha passa pelo mesmo tratamento global (addslashes + htmlspecialchars) que o login aplica ao que o jogador digita.
$hash = senha_hash(htmlspecialchars(addslashes($senha), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"));

doquery("UPDATE {{table}} SET gamename='".$e(getenv("NARUTO_NOME_JOGO"))."', gameurl='".$e(getenv("NARUTO_URL_JOGO"))."', adminemail='".$e($email)."', verifyemail='".intval(getenv("NARUTO_VERIFICAR_EMAIL"))."', emailativo='".intval(getenv("NARUTO_VERIFICAR_EMAIL"))."', gameopen='1' WHERE id='1' LIMIT 1", "control");
doquery("INSERT INTO {{table}} SET regdate=NOW(), onlinetime=(NOW() - INTERVAL 1 DAY), verify='1', username='".$e($conta)."', password='".mysqli_real_escape_string($link, $hash)."', email='".$e($email)."', charname='".$e($personagem)."', authlevel='1', charclass='1', difficulty='1', gold='1000', towns='0', spells='0', caixadepm='', mainmsg='None', bancogeral='None', historico='None', questsaux='None', treinamento='None'", "users");
echo "[ok] Jogo configurado e administrador '$conta' criado.\n";
