<?php // templates/banco.php :: Quadros "Depositar Ryou" e "Retirar Ryou" do banco (usado pelo funcaoitens.php?do=banco). Campo e OK lado a lado (flex), sem largura fixa que corte o texto no celular.
$t = "t";
$template = <<<FIMDOTEMPLATE
<form action="funcaoitens.php?do=banco" method="post">
<center><table><tr><td>
<table>
<tr bgcolor="#452202"><td><font color="white">{$t("Depositar Ryou:")}</font></td></tr>
<tr bgcolor="#E4D094">
<td><div style="display:flex;gap:4px;align-items:stretch"><input type="text" name="deposito" size="14" style="flex:1 1 90px;width:90px;min-width:0;box-sizing:border-box" /><button type="button" class="btnjogo bj-neutro" style="flex:none;margin:0;padding-left:6px;padding-right:6px" title="{$t("Preencher com o valor máximo")}" onclick="this.form.elements['deposito'].value='MAXDEPOSITO'">{$t("Máx")}</button><button type="submit" name="submit" value="OK" class="btnjogo bj-confirmar" style="flex:none;margin:0" id="botao1"><img src="images/botao_depositar.png" width="16" height="16" alt="" /> {$t("Depositar")}</button></div></td></tr>
</table>
</td>
<td>
<table>
<tr bgcolor="#452202"><td><font color="white">{$t("Retirar Ryou:")}</font></td></tr>
<tr bgcolor="#E4D094">
<td><div style="display:flex;gap:4px;align-items:stretch"><input type="text" name="retirar" size="14" style="flex:1 1 90px;width:90px;min-width:0;box-sizing:border-box" /><button type="button" class="btnjogo bj-neutro" style="flex:none;margin:0;padding-left:6px;padding-right:6px" title="{$t("Preencher com o valor máximo")}" onclick="this.form.elements['retirar'].value='MAXRETIRAR'">{$t("Máx")}</button><button type="submit" name="submit" value="OK" class="btnjogo bj-neutro" style="flex:none;margin:0" id="botao2"><img src="images/botao_retirar.png" width="16" height="16" alt="" /> {$t("Retirar")}</button></div></td></tr>
</table>
</td>
</tr>
</table>
</form>
</center>
FIMDOTEMPLATE;
?>
