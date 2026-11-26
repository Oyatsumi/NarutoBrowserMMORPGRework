<?php // templates/verify.php :: Formulário de ativação da conta com o código recebido por e-mail (users.php?do=verify).
$t = "t";
$template = <<<FIMDOTEMPLATE
<form action="users.php?do=verify" method="post">
<table width="80%">
<tr><td colspan="2">{$t("Obrigado por registrar seu personagem. Por favor entre com o seu nome de usuário, e-mail e código de verificação que foi enviado por e-mail para desbloquear seu personagem.")}<br />{$t("Não recebeu o e-mail? Você pode <a href=\"ativarconta.php\">pedir o reenvio do código</a>.")}<br /><br /></td></tr>
<tr><td width="20%">{$t("Nome de Usuário:")}</td><td><input type="text" name="username" size="30" maxlength="30" /></td></tr>
<tr><td>{$t("E-mail:")}</td><td><input type="text" name="email" size="30" maxlength="100" /></td></tr>
<tr><td>{$t("Código de Verificação:")}</td><td><input type="text" name="verify" size="10" maxlength="8" /><br /><br /><br /></td></tr>
<tr><td colspan="2"><input class="btnjogo bj-confirmar" type="submit" name="submit" value="{$t("Enviar")}" /> <input class="btnjogo bj-cancelar" type="reset" name="reset" value="{$t("Resetar")}" /></td></tr>
</table>
</form>
FIMDOTEMPLATE;
?>
