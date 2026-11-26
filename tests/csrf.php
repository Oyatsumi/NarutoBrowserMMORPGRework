<?php
// Uso: php csrf.php <pasta_do_jogo> <usuario>  -> imprime o token anti-CSRF do jogador.
chdir($argv[1]); $_GET = $_POST = $_COOKIE = array();
include('lib.php'); opendb();
$userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE username='".addslashes($argv[2])."' LIMIT 1", "users"));
echo csrf_token(), "\n";
