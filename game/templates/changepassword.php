<?php // templates/changepassword.php :: Formulário para mudar a senha informando a senha antiga (users.php?do=changepassword).
global $conteudouser;
$t = "t";
$template = <<<FIMDOTEMPLATE
<table width="100%"><tr><td width="100%" align="center"><center><img src="images/minhaconta.gif" alt="" /></center></td></tr></table>$conteudouser
<form action="users.php?do=changepassword" method="post" id="formback"><fieldset id="field2"><legend>{$t("Mudar Senha")}</legend>
<table width="100%">
<tr><td colspan="2">{$t("Use o formulário abaixo para mudar sua senha. Todos os campos são requeridos. A nova senha deve ter de 6 a 72 caracteres (pode usar qualquer caractere).")}</td></tr>
<tr><td width="20%">{$t("Nome de Usuário:")}</td><td><input type="text" name="username" size="30" maxlength="30" autocomplete="username" /></td></tr>
<tr><td>{$t("Antiga Senha:")}</td><td><input type="password" name="oldpass" size="20" autocomplete="current-password" /></td></tr>
<tr><td>{$t("Nova Senha:")}</td><td><input type="password" name="newpass1" size="20" autocomplete="new-password" /></td></tr>
<tr><td>{$t("Verificar Nova Senha:")}</td><td><input type="password" name="newpass2" size="20" autocomplete="new-password" /><br /><br /><br /></td></tr>
<tr><td colspan="2"><center><div class="buttons"><button type="submit" class="positive btnjogo bj-confirmar" name="submit"><img src="images/botao_sim.png" width="16" height="16" alt="" /> {$t("Mudar Senha")}</button>
<button type="reset" class="negative btnjogo bj-cancelar" name="reset"><img src="images/botao_limpar.png" width="16" height="16" alt="" /> {$t("Resetar")}</button>
</div></center></td></tr>
</table>
</fieldset></form>
FIMDOTEMPLATE;
?>
