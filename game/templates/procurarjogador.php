<?php
// procurarjogador.php :: Formulário "Procurar Jogador" (users.php?do=procurarjogador), que abre a ficha pelo index.php?do=onlinechar.
$t = "t"; // Para usar {$t("texto")} dentro do heredoc.
$template = <<<FIMDOTEMPLATE
<center><img src="images/procurar.gif" alt="{$t("Procurar Jogador")}" /></center><br>
<form action="users.php?do=procurarjogador" method="post">
<table width="100%">
<tr><td colspan="2">{$t("Complete o campo abaixo corretamente:")}</td></tr>
<tr><td width="20%">{$t("Procurar(Nome do Jogador):")}</td><td><input type="text" name="username" size="30" maxlength="30" /></td></tr>
<tr><td colspan="2"><input class="btnjogo bj-confirmar" type="submit" name="submit" value="{$t("Procurar")}" /> <input class="btnjogo bj-cancelar" type="reset" name="reset" value="{$t("Apagar Campo")}" /></td></tr>
</table>
</form>
FIMDOTEMPLATE;
?>