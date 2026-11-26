<?php
// leftnav.php :: Coluna da esquerda (mini mapa, botões de direção, cidades, mensagens e histórico).

global $userrow;
include('minimap.php');     // Monta $minimap (5x5 ao redor do jogador).
include('historico.php');   // Monta $fimh (últimas ações).
$t = "t"; // Tradução dentro do heredoc: {$t("texto")} (definido depois dos includes, o minimap usa $t como contador).

// Último inimigo derrotado e quantas vezes seguidas ("nome,quantidade").
$monstro = array_pad(explode(",",$userrow["ultmonstro"]), 2, "0");
if ($monstro[0] == "None" || $monstro[0] == "") { $textomonstro = t("Nenhum inimigo derrotado ainda."); }
else { $textomonstro = tf("%s, morto %s vez(es).", t($monstro[0]), $monstro[1]); }

// Ícone de "nova mensagem privada".
if ($userrow["pmsnovas"] == 1){$imagem = "lergif.gif";}else{$imagem = "ler1.jpg";}

// Incenso Repelente aceso (backpack.php): ícone pequeno com os passos e os minutos que restam.
include_once('funcoesinclusas.php');
$repelente = "";
if (isset($userrow["repelente_passos"]) && repelenteativo($userrow) > 0) {
	$minutos = max(1, (int)ceil((intval($userrow["repelente_ate"]) - time()) / 60));
	$textorep = tf("Incenso Repelente: %s passos / %s min", intval($userrow["repelente_passos"]), $minutos);
	$repelente = "<div id=\"repelente\" title=\"".$textorep."\" style=\"margin:4px auto 0 auto;font-size:11px;color:#4b2a6b\"><img src=\"layoutnovo/equipamentos/novos/incenso_repelente.gif\" width=\"17\" height=\"17\" alt=\"\" style=\"vertical-align:middle\" /> ".$textorep."</div>";
}

// Passo Veloz ligado (heal.php/explore.php): aviso verde com as casas por passo, logo abaixo do mini mapa.
if (isset($userrow["passo_jutsu"]) && intval($userrow["passo_jutsu"]) > 0) {
	$sp = mysqli_fetch_array(doquery("SELECT name,tier FROM {{table}} WHERE id='".intval($userrow["passo_jutsu"])."' LIMIT 1", "spells"));
	if ($sp) { $repelente .= "<div id=\"passoveloz\" style=\"margin:4px auto 0 auto;font-size:11px;color:#0a6d0a;font-weight:bold\">» ".tf("%s: %s casas por passo", t($sp["name"]), intval($sp["tier"]) + 1)."</div>"; }
}

include_once('funcoespainel.php'); include_once('funcoesdiarias.php');
$caixamissaol = caixamissaoativa($userrow); $caixadiariasl = function_exists('caixadiarias') ? caixadiarias($userrow) : '';
$template = <<<FIMDOTEMPLATE

<br><br>



<div style="position:relative; width:204px">
<div style="background-image: url(layoutnovo/buttons/coordenada.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-30px;z-index:2">



{$t("Use os botões de direção para se movimentar:")}<br><br>
<center>$minimap</center>
<center><a href="javascript:openmappopup()">{$t("Ver Mapa Completo")}</a>$repelente</center><br>
<form action="index.php?do=move" method="post">
<center>
<!-- Botões de andar com setas (tools/icones_interface.py); explore.php só confere se o campo north/south/east/west veio. -->
<style>.setaandar{width:62px;margin:1px;padding:2px 3px;font:bold 11px tahoma,arial,sans-serif;background:#fff1c7;border:1px solid #8b5a2b;border-radius:3px;cursor:pointer;color:#2a1a0c}.setaandar:hover{background:#ffe3a0}.setaandar img{vertical-align:middle;display:block;margin:0 auto 1px}</style>
<button name="north" type="submit" value="1" class="setaandar" title="{$t("Norte")}"><img src="images/seta_norte.png" width="16" height="16" alt="" />{$t("Norte")}</button><br />
<button name="west" type="submit" value="1" class="setaandar" title="{$t("Oeste")}"><img src="images/seta_oeste.png" width="16" height="16" alt="" />{$t("Oeste")}</button><button name="east" type="submit" value="1" class="setaandar" title="{$t("Leste")}"><img src="images/seta_leste.png" width="16" height="16" alt="" />{$t("Leste")}</button><br />
<button name="south" type="submit" value="1" class="setaandar" title="{$t("Sul")}"><img src="images/seta_sul.png" width="16" height="16" alt="" />{$t("Sul")}</button>
</center><br>
</form><center>
Latitude: {{latitude}}<br />
Longitude: {{longitude}}</center>



</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div>









<div style="position:relative; width:204px">
<div style="background-image: url(layoutnovo/buttons/local.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-25px;z-index:2">



<center>{{townslist}}</center>
<div style="position: absolute; visibility: hidden">
<table width="100%" height="5"><tr><td><img src="images/comandos.jpg"></td><td align="right"><img src="images/viajar.jpg"></td></tr></table></div>
<center>{{adminlink}}<div id="procurarjog"></div>
<a href="pm.php?do=ler"><img title="{$t("Ler Mensagens Privadas")}" border="0" id="msg1" src="images/$imagem" onmouseover="document.getElementById('msg1').src = 'images/ler2.jpg';" onmouseout="document.getElementById('msg1').src = 'images/$imagem';"/></a><a href="mainmsg.php?do2=enviarpm"><img title="{$t("Enviar Mensagens Privadas")}" border="0" id="msg2" src="images/enviar1.jpg" onmouseover="document.getElementById('msg2').src = 'images/enviar2.jpg';" onmouseout="document.getElementById('msg2').src = 'images/enviar1.jpg';"/></a>
</center>



</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div>

<div style="position:relative; width:204px">
<div style="background-image: url(layoutnovo/buttons/missoes.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-25px;z-index:2">
<div style="position:relative;top:-4px;width:180px;margin:0 auto">$caixamissaol$caixadiariasl</div>
</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div>













<div style="position:relative; width:204px">
<div style="background-image: url(layoutnovo/buttons/historico.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-25px;z-index:2">


<div style="position:relative;top:-4px;width:180px;margin:0 auto">
<div class="cxb"><div class="cxb-cab">{$t("Últimos inimigos")}</div><div class="cxb-corpo"><div class="cxb-linha">$textomonstro</div></div></div>
<div class="cxb" id="caixahistorico"><div class="cxb-cab">{$t("Histórico")}<span><a href="javascript:historicoabrir(true)" title="{$t("Mostrar todo o histórico")}">&#9660;</a> <a href="javascript:historicoabrir(false)" title="{$t("Mostrar só as últimas mensagens")}">&#9650;</a></span></div><div class="cxb-corpo">$fimh</div></div>
</div>



</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div>
















FIMDOTEMPLATE;
?>