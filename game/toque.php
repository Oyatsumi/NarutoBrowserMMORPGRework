<?php // toque.php :: Marca que o jogador já viu o aviso das dicas por toque (celular/tablet). Chamado por js/toque.js (POST com csrf).
// Grava users.avisotoque = 1 para o aviso aparecer uma única vez por conta (tools/aviso_toque.sql).
include_once('lib.php');
$link = opendb();
include_once('cookies.php');
$userrow = checkcookies();
header("Content-Type: text/plain; charset=UTF-8");
if ($userrow == false || $_SERVER["REQUEST_METHOD"] !== "POST" || !csrf_confere()) { http_response_code(403); die("erro"); }
doquery("UPDATE {{table}} SET avisotoque='1' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
echo "ok";
