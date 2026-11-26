<?php
// templates/admin.php :: Layout do Painel do Administrador (mesma paleta de cores do jogo).
$t = "t";
$seletoridioma = seletoridioma();
// $paineltipo (global): "admin" = menu completo, "gm" = menu limitado do gm.php, "entrar" = sem menu (senha/confirmação).
$paineltipo = isset($GLOBALS["paineltipo"]) ? $GLOBALS["paineltipo"] : "admin";
$nomedopainel = ($paineltipo == "gm" || (isset($GLOBALS["userrow"]) && ehgm($GLOBALS["userrow"]))) ? t("Painel do GameMaster") : t("Painel do Administrador");
if ($paineltipo == "gm") {
    $menupainel = "<td id=\"admmenu\"><a href=\"gm.php\">".t("Início / Online")."</a>"
        ."<div class=\"grupo\">".t("Jogadores")."</div><a href=\"gm.php?do=jogador\">".t("Procurar jogador")."</a><a href=\"gm.php?do=online\">".t("Jogadores online")."</a>"
        ."<div class=\"grupo\">".t("Moderação")."</div><a href=\"gm.php?do=chat\">".t("Chats e fórum")."</a><a href=\"gm.php?do=mensagem\">".t("Mensagem privada como GM")."</a><a href=\"gm.php?do=mensagemglobal\">".t("Mensagem para todos")."</a><a href=\"gm.php?do=caveiras\">".t("Caveiras (PvP)")."</a>"
        .((intval($GLOBALS["userrow"]["authlevel"]) == 1 || intval(isset($GLOBALS["controlrow"]["gm_mapa"]) ? $GLOBALS["controlrow"]["gm_mapa"] : 1) == 1) ? "<div class=\"grupo\">".t("Mundo")."</div><a href=\"gm.php?do=mapa\">".t("Mapa do jogo")."</a>" : "")
        ."<div class=\"grupo\">".t("Registro")."</div><a href=\"gm.php?do=registro\">".t("Minhas ações")."</a>"
        .(intval($GLOBALS["userrow"]["authlevel"]) == 1 ? "<div class=\"grupo\">".t("Administrador")."</div><a href=\"admin.php\">".t("Painel do Administrador")."</a>" : "")
        ."<br /><a href=\"gm.php?do=sairpainel\">".t("Fechar o painel")."</a></td>";
} elseif ($paineltipo == "entrar") {
    $menupainel = "";
} else {
    $menupainel = "<td id=\"admmenu\"><a href=\"admin.php\">".t("Início / Resumo")."</a>"
        ."<div class=\"grupo\">".t("Jogo")."</div><a href=\"admin.php?do=main\">".t("Configurações gerais")."</a><a href=\"admin.php?do=news\">".t("Notícias")."</a><a href=\"admin.php?do=mensagemglobal\">".t("Mensagem para todos")."</a>"
        ."<div class=\"grupo\">".t("Jogadores")."</div><a href=\"admin.php?do=users\">".t("Jogadores")."</a><a href=\"admin.php?do=moderacao\">".t("Moderação (chats/fórum)")."</a><a href=\"admin.php?do=manutencao\">".t("Manutenção")."</a><a href=\"admin.php?do=caveiras\">".t("Caveiras (PvP)")."</a><a href=\"admin.php?do=auditoria\">".t("Registro de auditoria")."</a><a href=\"gm.php\">".t("Painel do GameMaster")."</a>"
        ."<div class=\"grupo\">".t("Conteúdo do jogo")."</div><a href=\"admin.php?do=monsters\">".t("Inimigos e cartas")."</a><a href=\"admin.php?do=items\">".t("Itens das lojas")."</a><a href=\"admin.php?do=drops\">".t("Itens de drop")."</a><a href=\"admin.php?do=variantes\">".t("Variantes raras")."</a><a href=\"admin.php?do=receitas\">".t("Receitas da alquimia")."</a><a href=\"admin.php?do=spells\">".t("Jutsus")."</a><a href=\"admin.php?do=suprimentos\">".t("Invocação de Suprimentos")."</a><a href=\"admin.php?do=towns\">".t("Vilas")."</a><a href=\"admin.php?do=mapajogo\">".t("Mapa do jogo")."</a><a href=\"admin.php?do=levels\">".t("Níveis")."</a><a href=\"admin.php?do=classes\">".t("Especializações")."</a><a href=\"admin.php?do=treinos\">".t("Treinos longos")."</a><a href=\"admin_doujutsu.php\">".t("Doujutsu")."</a><a href=\"admin_missoes.php\">".t("Missões")."</a><a href=\"admin_avatares.php\">".t("Avatares")."</a>"
        ."<div class=\"grupo\">".t("GameMaster")."</div><a href=\"gm.php\">".t("Painel do GameMaster")."</a>"
        ."<br /><a href=\"admin.php?do=sairpainel\">".t("Fechar o painel")."</a></td>";
}
// Janela de doação (só administradores, painel completo): aparece na primeira página do painel em cada sessão do navegador,
// no máximo uma vez a cada control.doacaodias dias (users.doacaovista = quando foi vista; 0 dias = toda sessão). Fecha no X.
$janeladoacao = "";
if ($paineltipo == "admin" && isset($GLOBALS["userrow"]["authlevel"]) && intval($GLOBALS["userrow"]["authlevel"]) == 1 && empty($_COOKIE["doacaosessao"])) {
    $cr = isset($GLOBALS["controlrow"]["doacaodias"]) ? $GLOBALS["controlrow"] : mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
    $dias = isset($cr["doacaodias"]) ? intval($cr["doacaodias"]) : 7;
    $vista = intval(isset($GLOBALS["userrow"]["doacaovista"]) ? $GLOBALS["userrow"]["doacaovista"] : 0);
    if (!headers_sent()) { setcookie("doacaosessao", "1", array("expires" => 0, "path" => "/", "httponly" => true, "samesite" => "Lax")); }
    if (time() - $vista >= $dias * 86400) {
        doquery("UPDATE {{table}} SET doacaovista='".time()."' WHERE id='".intval($GLOBALS["userrow"]["id"])."' LIMIT 1", "users");
        include_once('funcoesdoacao.php');
        $botaojanela = botaodoacao($cr, false);
        $janeladoacao = "<div id=\"admdoacao\" style=\"position:fixed;inset:0;background:rgba(30,15,0,.55);z-index:1000;display:flex;align-items:center;justify-content:center\">"
            ."<div style=\"position:relative;background:#fff1c7;border:3px solid #452202;border-radius:8px;max-width:460px;margin:16px;padding:18px 22px;text-align:center;box-shadow:0 6px 24px rgba(0,0,0,.5)\">"
            ."<a href=\"#\" onclick=\"document.getElementById('admdoacao').style.display='none';return false;\" title=\"".t("Fechar")."\" style=\"position:absolute;right:10px;top:4px;font:bold 20px tahoma;color:#452202;text-decoration:none\">&times;</a>"
            ."<h2 style=\"margin:4px 0 10px 0;color:#613003\">".t("Apoie o projeto")."</h2>"
            ."<p>".t("Este jogo e o seu motor são desenvolvidos desde 2008 e são código aberto. Se você usa o motor no seu servidor, considere fazer uma doação para ajudar os desenvolvedores a continuar.")."</p>"
            ."<p style=\"margin:14px 0\">".str_replace("class=\"crdbotao pequeno\"", "style=\"display:inline-block;background:linear-gradient(#ffb347,#e46b0c);color:#fff;font:bold 15px tahoma;text-decoration:none;padding:9px 24px;border-radius:7px;border:2px solid #8a3a00\"", $botaojanela)."</p>"
            ."<p class=\"small\">".tf("Esta janela aparece de novo a cada %s dias (ajuste em Configurações gerais). Créditos: <a href=\"creditos.php\" target=\"_blank\">creditos.php</a>", $dias)."</p></div></div>";
    }
}
$template = <<<FIMDOTEMPLATE
<head>
<meta name="color-scheme" content="only light"><meta name="darkreader-lock">
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
<meta name="robots" content="noindex, nofollow" />
<title>{{gamename}} :: $nomedopainel :: {{title}}</title>
<style type="text/css">
body {
  color: #2b1600;
  background: #1b0d02 url(images/background.jpg);
  font: 12px tahoma, "DejaVu Sans Condensed", arial, sans-serif;
  margin: 0;
}
#admtopo {
  background: #452202;
  color: #fff1c7;
  padding: 10px 18px;
  border-bottom: 3px solid #e4d094;
}
#admtopo a { color: #e4d094; }
#admtopo b { font: bold 18px impact, "DejaVu Sans Condensed", sans-serif; letter-spacing: 1px; }
#admcorpo { width: 96%; max-width: 1180px; margin: 14px auto; border-collapse: collapse; }
#admmenu {
  width: 190px;
  background: #fff1c7;
  border: 2px solid #452202;
  vertical-align: top;
  padding: 10px;
}
#admmenu a { display: block; padding: 3px 4px; }
#admmenu a:hover { background: #e4d094; }
#admmenu .grupo { margin: 12px 0 4px 0; padding: 3px 4px; background: #613003; color: #fff; font-weight: bold; }
#admconteudo {
  background: #fbf5db;
  border: 2px solid #452202;
  vertical-align: top;
  padding: 14px 18px;
}
table { font: 12px tahoma, "DejaVu Sans Condensed", arial, sans-serif; }
td { vertical-align: top; padding: 3px; }
a { color: #663300; text-decoration: none; font-weight: bold; }
a:hover { color: #330000; }
h1 { font: 22px impact, "DejaVu Sans Condensed", sans-serif; color: #452202; margin: 0 0 10px 0; letter-spacing: 1px; }
h2 { font: bold 14px tahoma, verdana, sans-serif; color: #613003; border-bottom: 1px solid #c9b47a; margin: 18px 0 8px 0; }
input[type=text], input[type=number], input[type=password], textarea, select {
  border: 1px solid #452202; background: #fffbea; padding: 2px; font: 12px tahoma, verdana, sans-serif;
}
input[type=submit], button, .botao {
  border: 1px solid #452202; background: #613003; color: #fff; padding: 3px 10px; cursor: pointer; font-weight: bold;
}
input[type=submit]:hover, .botao:hover { background: #452202; color: #fff; }
.botaoperigo { background: #8b1a1a !important; }
.lista { border-collapse: collapse; width: 100%; }
.lista th { background: #613003; color: #fff; text-align: left; padding: 4px 6px; }
.lista td { padding: 3px 6px; border-bottom: 1px solid #e4d094; }
.lista tr:nth-child(even) td { background: #fff1c7; }
.small { font-size: 11px; color: #5a4320; }
.highlight { color: #b00000; }
.light { color: #999999; }
.ok { background: #e3f3d5; border: 1px solid #5f8f3a; padding: 8px; margin-bottom: 10px; }
.erro { background: #f7dddd; border: 1px solid #a33; padding: 8px; margin-bottom: 10px; color: #800; }
.aviso { background: #fff3cd; border: 1px solid #c9a227; padding: 8px; margin-bottom: 10px; }
.caixas { display: flex; flex-wrap: wrap; gap: 10px; }
.caixa { flex: 1 1 140px; background: #fff1c7; border: 1px solid #c9b47a; padding: 10px; text-align: center; }
.caixa b { display: block; font: 24px impact, "DejaVu Sans Condensed", sans-serif; color: #452202; }
.carta { border: 1px solid #452202; }
#admmenu + #admconteudo { border-left: 0; }
#admrodape { text-align: center; color: #c9b47a; font-size: 11px; margin: 10px 0 20px 0; }
</style>
</head>
<body>
<div id="admtopo"><b>{{gamename}}</b> &nbsp;::&nbsp; $nomedopainel &nbsp;::&nbsp; {{title}}
<span style="float:right">{$t("Idioma:")} $seletoridioma &nbsp;|&nbsp; {$t("Logado como")} <b style="font:bold 12px tahoma">{{adminname}}</b> &nbsp;|&nbsp; <a href="index.php">{$t("Voltar ao jogo")}</a> &nbsp;|&nbsp; <a href="login.php?do=logout">{$t("Sair")}</a></span></div>
<table id="admcorpo"><tr>
$menupainel
<td id="admconteudo">
{{content}}
</td></tr></table>
$janeladoacao
<div id="admrodape">{{gamename}} &copy; 2008-2026 by Oyatsumi &nbsp;|&nbsp; <a href="creditos.php" style="color:#c9b47a">{$t("Ajude os desenvolvedores doando")}</a> &nbsp;|&nbsp; {{totaltime}} {$t("segundos")}, {{numqueries}} queries &nbsp;|&nbsp; {$t("Versão")} {{version}} {{build}}</div>
</body>
</html>
FIMDOTEMPLATE;
?>
