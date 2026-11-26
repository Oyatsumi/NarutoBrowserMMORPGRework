<?php // templates/fight.php :: Tela da luta: cartão do monstro, mensagens do turno, comandos e a animação do avatar ({{grafico}}).
$t = "t"; // {$t("...")} traduz dentro do heredoc.
// Os 18 avatares (layoutnovo/graficos/N_*.gif) foram desenhados virados para a DIREITA; aqui o avatar fica à
// direita do cartão do monstro, então o espelhamos via CSS (scaleX(-1)) para encarar o inimigo, sem alterar a arte.
$template = <<<FIMDOTEMPLATE
<table width="100%">
<tr><td align="center"><center><img src="images/title_fighting.gif" alt="Fighting" /></center>{{indexconteudo}}</td></tr>
<tr><td>
<div style="position: relative; top: -15px; margin-bottom: -30px;">
<table><tr><td width="310"><br>
{{dados}}
<center>{$t("Você está lutando com:")} <b>{{monstername}}</b>.<br /><br></center>
<style>.secaoluta{display:flex;align-items:center;gap:8px;margin:14px 4px 6px;font-weight:bold;color:#663300;font-size:11px;letter-spacing:1px}.secaoluta:before,.secaoluta:after{content:"";flex:1;height:2px;background:linear-gradient(90deg,transparent,#a0703c 25%,#a0703c 75%,transparent)}.secaoluta.sl-vazia{margin:14px 4px 8px}.secaoluta.sl-vazia:before{flex:1}.secaoluta.sl-vazia:after{display:none}</style>
<div class="secaoluta" style="margin-top:4px">{$t("TURNO")}</div>
<div style="text-align:center">{{yourturn}}
{{monsterturn}}</div>
{{monsterhp}}
{{command}}
</td><td valign="top">
<div style="width:165px;margin:0 auto">{{vidajogador}}
<table width="165" height="175" background="layoutnovo/graficos/fundo.png" style="background-repeat:no-repeat;;background-position:left top"><tr height="{{porcent}}"><td></td></tr><tr ><td valign="bottom"><center><img src="layoutnovo/graficos/{{grafico}}" style="transform:scaleX(-1)"></center>
</td></tr><tr><td></td></tr></table></div>
{{registro}}
</td></tr></table>
</div>
</td></tr>
</table>
FIMDOTEMPLATE;
?>