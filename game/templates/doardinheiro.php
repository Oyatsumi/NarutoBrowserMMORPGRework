<?php // templates/doardinheiro.php :: Formulário para doar Ryou a outro jogador (users.php?do=doardinheiro).
$t = "t";
$template = <<<FIMDOTEMPLATE
<form action="users.php?do=doardinheiro" method="post">
<table width="100%">
<tr><td colspan="2">{$t("Complete os campos abaixo corretamente:")}</td></tr>
<tr><td width="20%">{$t("Doar Para(Nome do Jogador):")}</td><td><input type="text" name="jogador" size="30" maxlength="30" /></td></tr>
<tr><td width="20%">{$t("Quantidade de Ryou:")}</td><td><input type="text" name="quantia" size="20" /></td></tr>
<tr><td colspan="2"><input class="btnjogo bj-confirmar" type="submit" name="submit" value="{$t("Doar")}" /> <input class="btnjogo bj-cancelar" type="reset" name="reset" value="{$t("Apagar Campos")}" /></td></tr>
</table>
</form>
FIMDOTEMPLATE;
?>
