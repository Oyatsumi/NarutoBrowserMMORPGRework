<?php // templates/duelo.php :: Tela do duelo PvP (users.php?do=duelo): os dois cartões lado a lado, a faixa do turno com o relógio, os comandos e o registro das últimas ações.
$t = "t";
$template = <<<FIMDOTEMPLATE
{{estilo}}{{titulo}}
{{mensagem}}
{{arena}}
<div class="duelocartas"><div class="duelolado">{{cartao1}}</div><span class="duelovs">VS</span><div class="duelolado">{{cartao2}}</div></div>
<div class="duelofaixa">{{faixa}}</div>
<table width="100%" cellspacing="0" cellpadding="0"><tr>
<td width="49%" valign="top">{{comandos}}{{desistir}}</td>
<td width="2%"></td>
<td width="49%" valign="top">{{registro}}</td>
</tr></table>
FIMDOTEMPLATE;
?>
