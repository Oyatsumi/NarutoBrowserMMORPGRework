<?php
// babblebox.php :: Popup do Chat Global (index.php?do=babblebox). {{content}} traz as falas e o formulário.
$t = "t"; // Para usar {$t("texto")} dentro do heredoc.
$template = <<<FIMDOTEMPLATE
<head>
<title>{$t("Chat Global")}</title>
<style type="text/css" media="screen">@import "novobotao.css?v=2";/* Botão padrão do jogo (mesmo visual de Atacar/Correr da luta): fundo creme, borda marrom e faixa colorida à
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
button:not([class]):hover { background-color: #E4D094; }
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
/* Popup sem celular.css: em tela de toque os botões ficam com altura de dedo. */
@media (pointer: coarse) { .btnjogo, .buttons button.btnjogo { min-height: 36px; font-size: 14px; } }
</style>
<style type="text/css">
body {
  background-image: url(layoutnovo/menumeio/meio2.png);
  color: black;
  font: 11px verdana;
  margins: 0px;
  padding: 0px;
}
div {
    padding: 2px;
    border: solid 1px black;
    margin: 2px;
    text-align: left;
}
.buttons{
	 border: solid 0px black;
}
a {
    color: #663300;
    text-decoration: none;
    font-weight: bold;
}
a:hover {
    color: #330000;
}
input {
border:1px #000000 solid;
background-color:#FBF5DB;
 }
</style>
<script>
function mostrarchar(nome){
var popurl="mostrarchar.php?nomechar=" + nome;
winpops=window.open(popurl,"","width=250,height=500,scrollbars")
}
</script>
</head>
<body onload="window.scrollTo(0,99999)">
{{content}}
</body>
</html>
FIMDOTEMPLATE;
?>