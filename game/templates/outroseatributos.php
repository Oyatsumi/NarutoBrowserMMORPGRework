<?php // templates/outroseatributos.php :: Formulário de distribuição de pontos e legenda dos atributos (continua a tabela aberta em outroseatributos.php).
$t = "t"; // {$t("...")} traduz dentro do heredoc.
$template = <<<FIMDOTEMPLATE
<td><form action="outroseatributos.php?do=atributos" method="post">
<table>
<tr  bgcolor="#452202"><td colspan="2"><center><font color="white">{$t("Distribuir Pontos")}</font></center></td></tr>
<tr bgcolor="#FFF1C7"><td >{$t("Agilidade")}<img src="images/raio.gif" title="{$t("Elemento Relâmpago")}"> </td><td>{$t("Adicionar Pontos:")} <input type="text" name="agilidadep" size="10" maxlength="30" /></td></tr>
<tr bgcolor="#E4D094"><td >{$t("Sorte")}<img src="images/agua.gif" title="{$t("Elemento Água")}"> </td><td>{$t("Adicionar Pontos:")} <input type="text" name="sortep" size="10" maxlength="30" /></td></tr>
<tr bgcolor="#FFF1C7"><td>{$t("Determinação")}<img src="images/fogo.gif" title="{$t("Elemento Fogo")}"> </td><td>{$t("Adicionar Pontos:")} <input type="text" name="determinacaop" size="10" maxlength="30" /></td></tr>
<tr bgcolor="#E4D094"><td  >{$t("Precisão")}<img src="images/vento.gif" title="{$t("Elemento Vento")}"> </td><td>{$t("Adicionar Pontos:")} <input type="text" name="precisaop" size="10" maxlength="30" /></td></tr>
<tr bgcolor="#FFF1C7"><td  >{$t("Inteligência")}<img src="images/terra.gif" title="{$t("Elemento Terra")}"> </td><td>{$t("Adicionar Pontos:")} <input type="text" name="inteligenciap" size="10" maxlength="30" /></td></tr>
<tr bgcolor="#E4D094"><td colspan="2"><div class="buttons" style="margin-left: 3px;"><center><button type="submit" class="positive btnjogo bj-confirmar" name="submit"><img src="images/botao_criar.png" width="16" height="16" alt="" /> {$t("Adicionar")}</button>
<button type="reset" class="negative btnjogo bj-cancelar" name="reset"><img src="images/botao_limpar.png" width="16" height="16" alt="" /> {$t("Apagar")}</button>
</center></div></td></tr>
</table></form></td></tr></table></center>
<b>{$t("Legenda")}</b>:
<ul>
<li />{$t("<b>Agilidade</b>: Aumenta a probabilidade de fuga de um ataque inimigo.")}
<li />{$t("<b>Sorte</b>: Aumenta a probabilidade de drop de um item.")}
<li />{$t("<b>Precisão</b>: Aumenta a probabilidade de acerto de ataque no inimigo.")}
<li />{$t("<b>Inteligência</b>: Aumenta o dano de todo ninjutsu e a probabilidade do inimigo continuar dormindo.")}
<li />{$t("<b>Determinação</b>: Aumenta a probabilidade de ataques críticos para <font color=red>ataques <b>físicos</b></font>.")}
<li />{$t("Todos os atributos aumentam a força de jutsus de seus elementos correspondentes.")}
</ul>
FIMDOTEMPLATE;
?>