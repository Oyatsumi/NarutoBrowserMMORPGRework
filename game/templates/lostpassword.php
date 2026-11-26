<?php // templates/lostpassword.php :: Formulário de senha perdida: pede o e-mail para enviar o link de nova senha (users.php?do=lostpassword).
global $conteudouser;
$t = "t";
$template = <<<FIMDOTEMPLATE
<table width="100%"><tr><td width="100%" align="center"><center><img src="images/minhaconta.gif" alt="" /></center></td></tr></table>$conteudouser
<form action="users.php?do=lostpassword" method="post" id="formback"><fieldset id="field2"><legend>{$t("Recuperar Senha")}</legend>
<table width="100%">
<tr><td colspan="2">{$t("Se você perdeu sua senha, entre com seu e-mail abaixo e você receberá um link para criar uma nova senha.")}</td></tr>
<tr><td width="20%">{$t("Endereço de E-mail:")}</td><td><input type="text" name="email" size="30" maxlength="100" /></td></tr>
<tr><td colspan="2"><center><div class="buttons"><button type="submit" class="positive btnjogo bj-confirmar" name="submit"><img src="images/botao_enviar.png" width="16" height="16" alt="" /> {$t("Enviar Link")}</button>
<button type="reset" class="negative btnjogo bj-cancelar" name="reset"><img src="images/botao_limpar.png" width="16" height="16" alt="" /> {$t("Resetar")}</button>
</div></center></td></tr>
</table></fieldset>
</form>
<center><a href="users.php?do=recuperar">{$t("Não tem acesso ao e-mail? Recuperar pelas perguntas secretas")}</a> &middot; <a href="users.php?do=recuperarcodigo">{$t("Recuperar com o código")}</a></center>
FIMDOTEMPLATE;
?>
