<?php

global $userrow;

if ($userrow == false){
	$widthall = 700;
	$widthp = 0;
	$widths = 0;
}else{
	$widthall = 990;
	$widthp = 204;
	$widths = 204;
}

//Variável da div da janela transparente de mensagens.
global $mainmsg;
include('mostrarmainmsg.php');



include('getheader.php');

// Tradução: dentro do heredoc usamos {$t("texto")}.
$t = "t";
$seletoridioma = seletoridioma();
// Rodapé: o tempo da página e o número de queries só aparecem para administradores e GameMasters.
$rodapetempo = (is_array($userrow) && (intval($userrow["authlevel"]) == 1 || ehgm($userrow))) ? "<td width=\"20%\" align=\"center\">{{totaltime}} ".t("Segundos").", {{numqueries}} Queries</td>" : "";

// Atualização dinâmica (AJAX), opção control.atualizacaodinamica: quando ligada, o js/navegacao.js troca só as
// regiões marcadas com id (colunas, conteúdo e janela de mensagens). Desligada, o HTML sai exatamente como antes.
global $controlrow;
$scriptnavegacao = ""; $idesquerda = ""; $idconteudo = ""; $iddireita = "";
if (is_array($controlrow) && !empty($controlrow["atualizacaodinamica"])) {
	$scriptnavegacao = "\n<script type=\"text/javascript\" src=\"js/navegacao.js?v=3\" data-carregando=\"".htmlspecialchars(t("Carregando..."), ENT_QUOTES, "UTF-8")."\" defer=\"defer\"></script>";
	$idesquerda = " id=\"regiaoesquerda\""; $idconteudo = " id=\"regiaoconteudo\""; $iddireita = " id=\"regiaodireita\"";
}

//para os icons funcionarem, tem que escrever ,progress;
$scripttoque = scripttoque().scriptcelular(); // Dicas por toque em celulares/tablets (js/toque.js).
$template = <<<FIMDOTEMPLATE

<head>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<meta name="color-scheme" content="only light"><meta name="darkreader-lock"><!-- impede o "modo escuro automático" do Edge/Chrome de clarear os textos -->
<title>{{gamename}} :: {{title}}</title>
<style type="text/css" media="screen">@import "novobotao.css?v=2";</style>$scriptnavegacao$scripttoque
<style type="text/css">
body {
  background-image: url(images/background.jpg);
  color: #000000;
  font: 11px tahoma, "DejaVu Sans Condensed", arial, sans-serif;
  margin-top : 0px;
  cursor: url(kunaitransp.cur);

}
div {
	  font: 12px tahoma, "DejaVu Sans Condensed", arial, sans-serif;
}
table {
  border-style: none;
  padding: 0px;
  font: 12px tahoma, "DejaVu Sans Condensed", arial, sans-serif;
  }
#divchat {
    padding: 2px;
    border: solid 1px black;
    margin: 2px;
    text-align: left;
}
input {
border:1px #000000 solid;
background-color:#FBF5DB;
 }
 textarea {
border:1px #000000 solid;
background-color:#FBF5DB;
 }
 select {
border:1px #000000 solid;
background-color:#FBF5DB;
 }
 #formback{
	 display:block;
	 background-image: url("layoutnovo/dropmenu/fundo.jpg");margin-top:	13px;margin-left:30px;margin-right:30px;
 }
 fieldset {padding:3px;padding-bottom:6px;padding-top:15px;background-image: url("layoutnovo/dropmenu/narutoformback.jpg");background-repeat: no-repeat;background-position: bottom right;	border: solid 1px black; display:block;position:relative;

}
td {
  border-style: none;
  padding: 3px;
  vertical-align: top;
  text-align: left;
}
td.top {
  border-bottom: solid 2px black;
}
td.left {
  width: 180px;
  border-right: solid 2px black;

  }
  

td.right {
  width: 180px;
  border-left: solid 2px black;
}
a {
    color: #663300;
    text-decoration: none;
    font-weight: bold;
	cursor: url(kunai.cur);
}
#java {
    color: #c8beb1;

}
#adm{
 	color: #0a9214;
}
#gm{
 	color: #0430bc;
}
#vermelho{
 	color: #ff0000;
}
#brancofino{
 	color: #ffffff;
	font-weight: normal;
}
#tutor{
 	color: #c5a111;
}
#dropid{
 	color: #000000;
	font-weight: normal;
}
	
		legend {  
		position:absolute;
		text-transform:		uppercase;
		font-size:			1.3em;
		padding:			5px;
		margin-left:		1em;
		color:				#ffffff;
		background:			#452202;
		display:block;
		margin-top:-30px;
	}

a:hover {
    color: #330000;
}
.small {
  font: 11px tahoma, "DejaVu Sans Condensed", arial, sans-serif;
}
.highlight {
  color: red;
}
.light {
  color: #999999;
}
.title {
  border: solid 1px black;
  background-color: #eeeeee;
  font-weight: bold;
  padding: 5px;
  margin: 3px;
}
.copyright {
  border: solid 1px black;
  background-color: #eeeeee;
  font: 11px tahoma, "DejaVu Sans Condensed", arial, sans-serif;
}
/* Listas e campos longos não podem alargar a coluna do meio (quebrava o layout). */
/* Largura fixa da coluna do meio. Usa a classe (sempre presente), não o id #regiaoconteudo, que só existe com a
   atualização dinâmica ligada: sem ela o conteúdo encolhia e ficava encostado à esquerda da moldura. */
.conteudomeio { width: 525px; min-width: 525px; max-width: 525px; box-sizing: border-box; margin: 0 auto; }
/* Padrão visual "B" das caixas (escolhido para o jogo todo): faixa marrom-escura com o título em branco e corpo creme
   com borda marrom, linhas alternadas (#fff1c7 / #e4d094). Use: <div class="cxb"><div class="cxb-cab">Título <span>extra</span></div>
   <div class="cxb-corpo"><div class="cxb-linha">...</div></div></div>. Mesmo visual de "Opções do Mapa" e "Chat do Mapa". */
:root { color-scheme: only light; }
.cxb { margin: 0 0 6px 0; text-align: left; font-size: 11px; }
.cxb-cab { display: flex; justify-content: space-between; align-items: center; gap: 4px; padding: 4px 6px; background: #452202; color: #fff; font-size: 12px; }
.cxb-cab a { color: #fff; text-decoration: none; }
.cxb-corpo { background: #fff1c7; border: 2px solid #452202; border-top: 0; color: #2a1a0c; }
.cxb > .cxb-corpo:first-child { border-top: 2px solid #452202; } /* caixa sem cabeçalho (só a mensagem) */
.cxb-linha { padding: 3px 6px; border-bottom: 1px dashed #c9a96a; }
.cxb-linha:nth-child(even) { background: #e4d094; }
.cxb-linha:last-child { border-bottom: 0; }
.cxb-vazio { padding: 4px 6px; color: #7a5a30; font-style: italic; }
.conteudomeio select { max-width: 300px; }
.conteudomeio input[type=text], .conteudomeio input[type=email], .conteudomeio input[type=password] { max-width: 100%; box-sizing: border-box; }
/* Botões e campos iguais em todos os navegadores: o Safari/iPad aplica o estilo nativo (pílula branca, texto
   invisível) quando só a borda/fundo são definidos. */
input[type=submit], input[type=button], input[type=reset], button {
  -webkit-appearance: none; -moz-appearance: none; appearance: none;
  background-color: #FBF5DB; color: #000000; border: 1px solid #000000; border-radius: 0;
  font: 13px tahoma, "DejaVu Sans Condensed", arial, sans-serif; line-height: normal;
  padding: 1px 6px; margin: 0; cursor: pointer; -webkit-text-fill-color: #000000; opacity: 1;
}
input[type=submit]:hover, input[type=button]:hover, input[type=reset]:hover { background-color: #E4D094; }
input[type=submit]:disabled, input[type=button]:disabled, button:disabled { color: #808080; -webkit-text-fill-color: #808080; }
/* Botão padrão do jogo (mesmo visual de Atacar/Correr da luta): fundo creme, borda marrom e faixa colorida à
   esquerda que diz o tipo da ação. Seletores com "button."/".buttons" para vencer o novobotao.css antigo. */
.btnjogo, button.btnjogo, input.btnjogo, a.btnjogo, .buttons button.btnjogo, .buttons a.btnjogo {
  display: inline-flex; align-items: center; justify-content: center; gap: 5px; vertical-align: middle; box-sizing: border-box;
  max-width: 100%; white-space: normal; text-align: center; text-decoration: none; overflow: visible; width: auto;
  font: bold 12px tahoma, "DejaVu Sans Condensed", arial, sans-serif; line-height: 16px;
  padding: 4px 14px; margin: 2px 3px; background: #fff1c7; color: #2a1a0c; -webkit-text-fill-color: #2a1a0c;
  border: 1px solid #8b5a2b; border-left: 5px solid #b08a4a; border-radius: 3px; cursor: pointer; box-shadow: 0 1px 0 rgba(69,34,2,.25);
}
input.btnjogo { display: inline-block; }
button.btnjogo:hover, input.btnjogo:hover, a.btnjogo:hover, .buttons button.btnjogo:hover, .buttons a.btnjogo:hover { background: #ffe9a8; color: #2a1a0c; border-top-color: #6b3f15; border-right-color: #6b3f15; border-bottom-color: #6b3f15; } /* a faixa da esquerda (cor do tipo) não muda */
button:not([class]):hover { background-color: #E4D094; } /* botões simples (setas do mapa, admin): mesmo destaque dos input[type=submit] */
button.btnjogo:active, input.btnjogo:active, a.btnjogo:active { background: #e4d094; box-shadow: none; }
button.btnjogo:disabled, input.btnjogo:disabled { opacity: .6; cursor: default; }
.btnjogo img, .buttons button.btnjogo img { margin: 0 !important; width: 16px; height: 16px; vertical-align: middle; image-rendering: pixelated; flex: none; }
.btnjogo img.jutsuico, .buttons button.btnjogo img.jutsuico { width: 20px; height: 17px; }
.btnjogo.bj-ataque, .buttons .btnjogo.bj-ataque { border-left-color: #c98b5a; }
.btnjogo.bj-defesa, .buttons .btnjogo.bj-defesa { border-left-color: #3d7bd0; }
.btnjogo.bj-fuga, .buttons .btnjogo.bj-fuga { border-left-color: #5a8cc8; }
.btnjogo.bj-confirmar, .buttons .btnjogo.bj-confirmar { border-left-color: #4a9a3a; }
.btnjogo.bj-cancelar, .buttons .btnjogo.bj-cancelar { border-left-color: #9b2a1a; }
.btnjogo.bj-neutro, .buttons .btnjogo.bj-neutro { border-left-color: #b08a4a; }
.buttons.btnlinha { display: flex; flex-wrap: wrap; justify-content: center; gap: 4px; }
input[type=text], input[type=email], input[type=password], input[type=number], input:not([type]), textarea {
  -webkit-appearance: none; appearance: none; border-radius: 0; color: #000000; -webkit-text-fill-color: #000000;
  font: 13px tahoma, "DejaVu Sans Condensed", arial, sans-serif; padding: 1px 2px;
}
select { color: #000000; font: 13px tahoma, "DejaVu Sans Condensed", arial, sans-serif; border-radius: 0; }
</style>
<script>
// Cronômetros [data-contagem] (ex.: "Próxima recuperação em N s" do descanso): contam até 0, também nas páginas trocadas por AJAX.
setInterval(function(){ var l = document.querySelectorAll('[data-contagem]'); for (var i = 0; i < l.length; i++) { var n = Math.max(0, parseInt(l[i].getAttribute('data-contagem'), 10) - 1); l[i].setAttribute('data-contagem', n); l[i].textContent = n; } }, 1000);
function historicoabrir(abrir){ // setas do quadro Histórico: todas as mensagens ou só as 3 mais recentes (cookie histaberto lembra a escolha).
	var l = document.querySelectorAll('.histextra'); for (var i = 0; i < l.length; i++) { l[i].style.display = abrir ? '' : 'none'; }
	var m = document.getElementById('histmais'); if (m) { m.style.display = abrir ? 'none' : ''; }
	document.cookie = 'histaberto=' + (abrir ? '1' : '0') + ';path=/;max-age=31536000;SameSite=Lax';
}
function irpara(u){ if (window.navegarjogo) { window.navegarjogo(u); } else { location.href = u; } } // botões que navegam: com AJAX quando ligado.
function opencharpopup(){
var soteste = document.location.href;
var popurl="index.php?do=showchar"
winpops=window.open(popurl,"","width=250,height=500,scrollbars")
}
function mostrarchar(nome){
var popurl="mostrarchar.php?nomechar=" + encodeURIComponent(nome);
winpops=window.open(popurl,"","width=250,height=500,scrollbars")
}
function openmappopup(azul){
if (azul == undefined){
var popurl="index.php?do=showmap"}else{
var popurl="index.php?do=showmap&lugarazul="+encodeURIComponent(azul);}
winpops=window.open(popurl,"","width=559,height=559,scrollbars")
}
function openmaprespawn(item){
if (item == undefined){
var popurl="index.php?do=showmap"}else{
var popurl="index.php?do=showmap&item="+encodeURIComponent(item);}	
winpops=window.open(popurl,"","width=559,height=559,scrollbars")
}
function openmapmonster(nome){
if (nome == undefined){
var popurl="index.php?do=showmap"}else{
var popurl="index.php?do=showmap&monstro="+encodeURIComponent(nome);}	
winpops=window.open(popurl,"","width=559,height=559,scrollbars")
}
function openchatpopup(){
var popurl="index.php?do=babblebox&tamanho=55"
winpops=window.open(popurl,"","width=400,height=400,scrollbars")
}
function mostrarjogadores(passandoconteudo){
document.getElementById('jogadoresmapa').innerHTML=passandoconteudo;
}
function fecharjogadores(){
document.getElementById('jogadoresmapa').innerHTML="";
}
function procurarjogador(){
document.getElementById('procurarjog').innerHTML = "<center><form onsubmit=\"var nome = document.getElementById('campo').value; mostrarchar(nome);\"><input type='text' name='jogador' id='campo'><br><input type='submit' id='blahblah' name='submit' value='' style='height:5px;'></form></center>"; sumirbotao('blahblah');
document.getElementById('naruto').innerHTML = "{$t("Escreva o nome do jogador no campo.")}";
}
function mostrartreino(numero, recompensa, requerimento, localtreino){
nome = "elemento" + numero;
document.getElementById(nome).innerHTML="<table width=\"100%\"><tr bgcolor=\"#E4D094\"><td width=\"110\">{$t("Requerimento:")}</td><td>" + requerimento + "</td></tr><tr bgcolor=\"#FFF1C7\"><td>{$t("Recompensa:")}</td><td>" + recompensa + "</td></tr><tr bgcolor=\"#E4D094\"><td>{$t("Local de Treino:")}</td><td>" + localtreino + "</td></tr></table>";
}
function mostrarquest(numero, recompensa, requerimento, localtreino, nivel, conclusao, itemprocurado, local, monstroprocurado){
nome = "elemento" + numero;
if ((local == undefined) || (local == '')) {local = "";}else{local = " <a href=\"javascript: openmappopup('" + local + "')\"><span style=\"display:inline-block;margin:2px 0;padding:1px 6px 1px 3px;background:#fff1c7;border:1px solid #a8844a;border-radius:9px;color:#613003;font-size:10px;font-weight:bold;white-space:nowrap;line-height:14px\" title=\"{$t("Mostrar Local no Mapa")}\"><img src=\"images/dica_mapa.png\" border=\"0\" style=\"vertical-align:-3px\"> {$t("Ver no mapa")}</span></a>";}
if ((monstroprocurado == undefined) || (monstroprocurado == '')){monstroprocurado = "";}else{monstroprocurado = "<a href=\"javascript: openmapmonster('" + monstroprocurado + "')\"><span style=\"display:inline-block;margin:2px 0;padding:1px 6px 1px 3px;background:#fff1c7;border:1px solid #a8844a;border-radius:9px;color:#613003;font-size:10px;font-weight:bold;white-space:nowrap;line-height:14px\" title=\"{$t("Mostrar Respawn do")} " + monstroprocurado + "\"><img src=\"images/dica_mapa.png\" border=\"0\" style=\"vertical-align:-3px\"> {$t("Ver no mapa")}</span></a>";}
if ((itemprocurado == undefined) || (itemprocurado == "")){
document.getElementById(nome).innerHTML="<table width=\"100%\"><tr bgcolor=\"#E4D094\"><td width=\"130\">{$t("Requerimento:")} " + monstroprocurado + local + "</td><td>" + requerimento + "</td></tr><tr bgcolor=\"#FFF1C7\"><td>{$t("Recompensa:")}</td><td>" + recompensa + "</td></tr><tr bgcolor=\"#E4D094\"><td>{$t("Local de Conclusão:")}</td><td>" + localtreino + "</td></tr><tr bgcolor=\"#FFF1C7\"><td>{$t("Nível:")}</td><td>" + nivel + "</td></tr><tr bgcolor=\"#E4D094\"><td>{$t("Conclusão:")}</td><td>" + conclusao + "</td></tr></table>";}
else{document.getElementById(nome).innerHTML="<table width=\"100%\"><tr bgcolor=\"#E4D094\"><td width=\"130\">{$t("Requerimento:")} " + monstroprocurado + local + "<a href=\"javascript: openmaprespawn('" + itemprocurado + "')\"><span style=\"display:inline-block;margin:2px 0;padding:1px 6px 1px 3px;background:#fff1c7;border:1px solid #a8844a;border-radius:9px;color:#613003;font-size:10px;font-weight:bold;white-space:nowrap;line-height:14px\" title=\"{$t("Mostrar Onde Dropa o(a)")} " + itemprocurado + "\"><img src=\"images/dica_mapa.png\" border=\"0\" style=\"vertical-align:-3px\"> {$t("Ver no mapa")}</span></a></td><td>" + requerimento + "</td></tr><tr bgcolor=\"#FFF1C7\"><td>{$t("Recompensa:")}</td><td>" + recompensa + "</td></tr><tr bgcolor=\"#E4D094\"><td>{$t("Local de Conclusão:")}</td><td>" + localtreino + "</td></tr><tr bgcolor=\"#FFF1C7\"><td>{$t("Nível:")}</td><td>" + nivel + "</td></tr><tr bgcolor=\"#E4D094\"><td>{$t("Conclusão:")}</td><td>" + conclusao + "</td></tr></table>";}
}
function mostrargraduacao(nomeelemento, requerimento, ganhos, itemprocurado){
if (itemprocurado == undefined) {itemprocurado = "";}else{itemprocurado = " <a href=\"javascript: openmaprespawn('" + itemprocurado + "')\"><span style=\"display:inline-block;margin:2px 0;padding:1px 6px 1px 3px;background:#fff1c7;border:1px solid #a8844a;border-radius:9px;color:#613003;font-size:10px;font-weight:bold;white-space:nowrap;line-height:14px\" title=\"{$t("Mostrar Onde Dropa o Item")}\"><img src=\"images/dica_mapa.png\" border=\"0\" style=\"vertical-align:-3px\"> {$t("Ver no mapa")}</span></a>";}
document.getElementById(nomeelemento).innerHTML = "<table width=\"100%\"><tr bgcolor=\"#E4D094\"><td width=\"130\">{$t("Requerimento:")} " + itemprocurado + "</td><td>" + requerimento + "</td></tr><tr bgcolor=\"#FFF1C7\"><td>{$t("Bônus:")}</td><td>" + ganhos + "</td></tr></table>";
}
function escondertreino(numero){
nome = "elemento" + numero;
document.getElementById(nome).innerHTML="";
}
function sumirbotao(id){
	document.getElementById(id).style.visibility = 'hidden';
}


var tempX = 0;
var tempY = 0;
var IE = document.all?true:false;
if (!IE) {document.captureEvents(Event.MOUSEMOVE);}
document.onmousemove = getMouseXY;

function getMouseXY(e) {
  if (IE) { // grab the x-y pos.s if browser is IE
  if (document.documentElement && !document.documentElement.scrollTop){    tempX = event.clientX + document.documentElement.scrollLeft
    tempY = event.clientY + document.documentElement.scrollTop}
// IE6 +4.01 but no scrolling going on
	else if (document.documentElement && document.documentElement.scrollTop){    tempX = event.clientX + document.documentElement.scrollLeft
    tempY = event.clientY + document.documentElement.scrollTop}
// IE6 +4.01 and user has scrolled
	else if (document.body && document.body.scrollTop){    tempX = event.clientX + document.body.scrollLeft
    tempY = event.clientY + document.body.scrollTop}
// IE5 or DTD 3.2 

  } else {  // grab the x-y pos.s if browser is NS
    tempX = e.pageX
    tempY = e.pageY
  }  
  // catch possible negative values in NS4
  if (tempX < 0){tempX = 0}
  if (tempY < 0){tempY = 0}  
  // show the position values in the form named Show
  // in the text fields named MouseX and MouseY

  return true
}

function menudrop(objeto, titulo, conteudo, x, y){
	document.getElementById(objeto).onclick = getMouseXY;

	var localx = parseInt(tempX) + parseInt(x) - 175;
	var localy = parseInt(tempY) + parseInt(y);
	document.getElementById("dropmenu").style.left = localx + "px";
	document.getElementById("dropmenu").style.top = localy + "px";
	document.getElementById('dropmenu').innerHTML = "<div style=\"position:relative;width:174px;background-image: url(layoutnovo/dropmenu/fundotabela.gif);z-index:0;background-repeat:repeat-y;\"><div style=\"background-image:url(layoutnovo/dropmenu/cima.gif);height:27px;z-index:1\"><div style=\"padding-top:10px;padding-right:14px\"><center>" + titulo + "</center><div style=\"position:absolute; left:153px;top:11px\"><a href=\"javascript:fechardrop();\"><img src=\"layoutnovo/dropmenu/direita/fechar.gif\" border=\"0\"></a></div></div></div><div style=\"background-image: url(layoutnovo/dropmenu/fundotabela.gif);z-index:0;background-repeat:repeat-y;padding-top:3px\"><div style=\"padding-left:11px;padding-right:11px;padding-top:3px;padding-bottom:2px\">" + conteudo + "</div><div style=\"background-image:url(layoutnovo/dropmenu/fim.gif);height:7px\"></div></div></div>";
}

function menudropdir(objeto, titulo, conteudo, x, y){
	document.getElementById(objeto).onclick = getMouseXY;

	var localx = parseInt(tempX) + parseInt(x) + 20;
	var localy = parseInt(tempY) + parseInt(y);
	document.getElementById("dropmenu").style.left = localx + "px";
	document.getElementById("dropmenu").style.top = localy + "px";
	document.getElementById('dropmenu').innerHTML = "<div style=\"position:relative;width:174px;background-image: url(layoutnovo/dropmenu/direita/fundotabela.gif);z-index:0;background-repeat:repeat-y;\"><div style=\"background-image:url(layoutnovo/dropmenu/direita/cima.gif);height:27px;z-index:1\"><div style=\"padding-top:10px;padding-right:14px\"><center>" + titulo + "</center><div style=\"position:absolute; left:155px;top:11px\"><a href=\"javascript:fechardrop();\"><img src=\"layoutnovo/dropmenu/direita/fechar.gif\" border=\"0\"></a></div></div></div><div style=\"background-image: url(layoutnovo/dropmenu/direita/fundotabela.gif);z-index:0;background-repeat:repeat-y;padding-top:3px\"><div style=\"padding-left:11px;padding-right:11px;padding-top:3px;padding-bottom:2px\">" + conteudo + "</div><div style=\"background-image:url(layoutnovo/dropmenu/direita/fim.gif);height:7px\"></div></div></div>";
}
function menuprincipal(conteudo, x, y){
	document.getElementById("menuprincipal").style.left = x + "px";
	document.getElementById("menuprincipal").style.top = y + "px";
	document.getElementById('menuprincipal').innerHTML = "<div style=\"position:relative;z-index:0\"><div style=\"background-image:url(layoutnovo/dropmenu/menucima/cima.png);height:5px;z-index:1;background-repeat:no-repeat\"></div><div style=\"background-image:url(layoutnovo/dropmenu/menucima/meio.png);z-index:0;padding-top:0px\"><div style=\"padding-left:6px;padding-right:11px;padding-top:3px;padding-bottom:5px\">" + conteudo + "</div></div></div>";
}
function explicdrop(objeto, titulo, conteudo, x, y){
	document.getElementById(objeto).onclick = getMouseXY;

	// Posição: presa ao próprio item (à esquerda dele, alinhada pelo topo) em vez da última posição do mouse, que podia
	// estar velha (clique/toque sem mousemove) e deixava a caixa longe do item. Sem espaço à esquerda, vai para a direita.
	var menuex = document.getElementById("explicmenu"), alvoex = document.getElementById(objeto);
	var sx = window.pageXOffset, sy = window.pageYOffset, localx, localy;
	if (alvoex && alvoex.getBoundingClientRect) {
		var ra = alvoex.getBoundingClientRect();
		localx = ra.left + sx + parseInt(x) - 179;
		localy = ra.top + sy + parseInt(y) - 1;
		if (localx < sx + 4) { localx = ra.right + sx + 4; }
	} else {
		localx = parseInt(tempX) + parseInt(x) - 175;
		localy = parseInt(tempY) + parseInt(y) - 5;
	}
	// A caixa é "absolute" dentro do pai dela (o <body>): converte de coordenadas da página para as do pai.
	var paiex = menuex.offsetParent;
	if (paiex) { var rp = paiex.getBoundingClientRect(); localx -= rp.left + sx; localy -= rp.top + sy; }
	menuex.style.left = localx + "px";
	menuex.style.top = localy + "px";
	document.getElementById('explicmenu').innerHTML = "<div style=\"position:relative;width:174px\"><div style=\"background-image:url(layoutnovo/dropmenu/transparente/cima.png);height:2px;z-index:1\"></div><div style=\"background-image: url(layoutnovo/dropmenu/transparente/fundotabela.png);z-index:0;background-repeat:repeat-y;padding-top:3px\"><center>" + titulo + "</center><div style=\"padding-left:11px;padding-right:11px;padding-top:7px;padding-bottom:2px\">" + conteudo + "</div><div style=\"background-image:url(layoutnovo/dropmenu/transparente/fim.png);height:7px\"></div></div></div>";
}

function yesorno(objeto, conteudo, yes, no){
	document.getElementById(objeto).onclick = getMouseXY;

	var localx = parseInt(tempX) - 80;
	var localy = parseInt(tempY);
	document.getElementById("explicmenu").style.left = localx + "px";
	document.getElementById("explicmenu").style.top = localy + "px";
	document.getElementById('explicmenu').innerHTML = "<table width=\"174\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" background=\"layoutnovo/dropmenu/transparente/fundotabela.png\" style=\"background-repeat:no-repeat;;background-position:center center\" ><tr><td background=\"layoutnovo/dropmenu/transparente/cima.png\" style=\"background-repeat:no-repeat;\" height=\"20\"><center><div style=\"padding-top: 3px\">" + conteudo + "</div></center></td></tr><tr><td><center><table><tr><td></td><td><center><a href=\"" + yes + "\" title=\"{$t("Sim")}\"><img src=\"images/aceitar.gif\" border=\"0\"></a><a href=\"" + no + "\" title=\"{$t("Não")}\"><img src=\"images/deletar.gif\" border=\"0\"></a></center></td></tr></table></center></td></tr><tr><td background=\"layoutnovo/dropmenu/transparente/fim.png\" height=\"7\"></td></tr></table>";
}


function fecharexplic(){
	document.getElementById('explicmenu').innerHTML = "";
}

function fecharmenuprincipal(){
	document.getElementById('menuprincipal').innerHTML = "";
}

function fechardrop(){
	document.getElementById('dropmenu').innerHTML = "";
}

function fechargrande(){
	document.getElementById('mainmsg').innerHTML = "";
}

function mostrarpass(id){
	if (document.getElementById(id).type == "password"){
		document.getElementById(id).type = "text";}
	else{
		document.getElementById(id).type = "password";
	}
}	

function opcaochar(nome){
	menudropdir("char", nome, "<img src=\"images/seta.gif\"><a href=\"javascript: mostrarchar('" + nome + "')\" id=\"dropid\">{$t("Visualizar Perfil")}</a><br><img src=\"images/seta.gif\"><a href=\"mainmsg.php?do2=enviarpm&nomedochar=" + nome + "\" id=\"dropid\">{$t("Enviar Mensagem")}</a>", "1", "1");
}

</script>
</head>
<body style="position:relative;left: -10px;" class="celprimary" onclick="fecharmenuprincipal()">
<div style="z-index: 1;">
    <center>
<table cellpadding="0" cellspacing="0" border="0" width="$widthall" background="layoutnovo/titulo/cima.jpg" style="background-repeat:no-repeat;background-position:center top;"><tr><td><center>

<table cellspacing="0" cellpadding="0" border="0" class="celcolunas"><tr><td width="$widthp" class="celesq"><center$idesquerda>{{leftnav}}</center></td><td class="celmeio">
<table width="551" class="celmoldura" cellspacing="0" cellpadding="0" border="0" ><tr><td height="214" class="celtopo">

<div style="position:relative" class="celtopomenu">
<div style="position:absolute;top:183px;left:471px"><a href="index.php"><img src="images/24/gif24.gif" border="0" height="19" width="20" title="{$t("Voltar")}"/></a></div>
<div style="position:absolute;top:183px;left:496px"><a href="javascript:menuprincipal('<a id=\'brancofino\' href=\'help.php\'>{$t("Geral")}</a><br><a href=\'help_monsters.php\' id=\'brancofino\'>{$t("Inimigos")}</a><br><a href=\'help_items.php\' id=\'brancofino\'>{$t("Itens")}</a><br><a href=\'help_spells.php\' id=\'brancofino\'>{$t("Jutsus")}</a><br><a href=\'help_levels.php\' id=\'brancofino\'>{$t("Level")}</a>', '497', '205')"><img src="images/24/gif24.gif" border="0" height="19" width="20" title="{$t("Ajuda")}"/></a></div>
<div style="position:absolute;top:183px;left:521px"><a href="login.php?do=logout"><img src="images/24/gif24.gif" border="0" height="19" width="20" title="{$t("Sair")}"/></a></div>

<div style="position:absolute;top:12px;left:120px"><a href="javascript:menuprincipal('<img src=\'images/setabranca.gif\'><a id=\'brancofino\' href=\'index.php\'>{$t("Home")}</a><br><img src=\'images/setabranca.gif\'><a href=\'rank.php\' id=\'brancofino\'>{$t("Rank")}</a>', '126', '28')"><img src="images/24/gif24.gif" border="0" height="20" width="43"/></a></div>

<div style="position:absolute;top:12px;left:168px"><a href="javascript:menuprincipal('<img src=\'images/setabranca.gif\'><a id=\'brancofino\' href=\'users.php?do=register\'>{$t("Criar Conta")}</a><br><img src=\'images/setabranca.gif\'><a href=\'ativarconta.php\' id=\'brancofino\'>{$t("Ativar Conta")}</a>', '200', '28')"><img src="images/24/gif24.gif" border="0" height="20" width="110"/></a></div>

<div style="position:absolute;top:12px;left:280px"><a href="javascript:menuprincipal('<img src=\'images/setabranca.gif\'><a id=\'brancofino\' href=\'users.php?do=changepassword\'>{$t("Mudar Senha")}</a><br><img src=\'images/setabranca.gif\'><a href=\'users.php?do=lostpassword\' id=\'brancofino\'>{$t("Requerer Nova Senha")}</a><br><img src=\'images/setabranca.gif\'><a href=\'login.php?do=login\' id=\'brancofino\'>{$t("Entrar")}</a>', '300', '28')"><img src="images/24/gif24.gif" border="0" height="20" width="100"/></a></div>

<div style="position:absolute;top:12px;left:383px"><a href="javascript:menuprincipal('<img src=\'images/setabranca.gif\'><a id=\'brancofino\' href=\'login.php?do=logout\'>{$t("Deslogar")}</a>', '390', '28')"><img src="images/24/gif24.gif" border="0" height="20" width="45"/></a></div>

<div id="menuprincipal" style="position:absolute;z-index:10"></div>
</div>


<!-- lugar das divs !-->



</td></tr>

<tr background="layoutnovo/titulo/meio.jpg" width="551"><td background="layoutnovo/titulo/meio.jpg">
<table cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td width="2"></td><td><div class="conteudomeio" style="position:relative;top:-10px"$idconteudo>{{content}}</div>
<br />
<center><table width="90%"><tr>
<td width="35%" align="center"><a href="creditos.php">{$t("Ajude os desenvolvedores doando")}</a></td><td width="25%" align="center">&copy; 2008-2026 by Oyatsumi</td>$rodapetempo<td width="20%" align="center">{$t("Versão")} {{version}} {{build}}</td>
</tr>
<tr><td colspan="4" align="center" style="padding-top:4px">Idioma / Language: $seletoridioma &nbsp;|&nbsp; <a href="javascript:void(0)" class="botaomodocel" onclick="trocarmodocel()"><span class="sopc">{$t("Versão celular")}</span><span class="socel">{$t("Versão computador")}</span></a></td></tr></table></center>
</td><td></td></tr></table>

</td></tr>
<tr background="layoutnovo/titulo/fim.jpg" height="12" width="551"><td background="layoutnovo/titulo/fim.jpg" ></td></tr>
</table>


</td><td width="$widths" class="celdir"><center$iddireita>{{rightnav}}</center></td></tr></table>

</center>
</td>

</tr></table></center></div>

<div id="dropmenu" style="z-index:6;position:absolute;left:1px;top:1px" align="left"></div>
<div id="explicmenu" style="z-index:2147483000;position:absolute;left:1px;top:1px;pointer-events:none" align="left"></div><!-- pointer-events:none: a caixinha de detalhes nunca "rouba" o clique do item que está embaixo dela -->
<div id="char" style="z-index:5;position:absolute;left:1px;top:1px" align="left"></div>
<div id="mainmsg" style="z-index:7;position:fixed;left:50%;top:32%;transform:translateX(-50%)" align="left">$mainmsg</div>








</body>

</html>



FIMDOTEMPLATE;
?>