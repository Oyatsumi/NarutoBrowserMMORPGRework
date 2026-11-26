<?php // templates/login.php :: Página de login. Nome da conta em campo de texto (autocomplete=username) e senha com autocomplete=current-password,
// para os gerenciadores de senha (Chrome etc.) reconhecerem o par; o nome da conta não é mais escondido (só a senha tem Mostrar/Ocultar).
// O quadro "Informativo" mostra control.loginmsg (o login.php carrega $controlrow antes) e some quando a mensagem está vazia.
global $conteudouser, $controlrow;
$t = "t";
$informativo = "";
if (isset($controlrow["loginmsg"]) && trim($controlrow["loginmsg"]) != "") {
    $informativo = "<center><table bgcolor=\"#452202\"><tr><td><font color=\"white\"><center>".t("Informativo")."</center></font></td></tr><tr><td bgcolor=\"#E4D094\"><center>".nl2br(t($controlrow["loginmsg"]))."</center></td></tr></table></center><br /><br />";
}
// Jogar sem conta (convidado): só aparece se estiver ligado no painel (control.convidado_ativo e convidado_porip > 0).
$botaoconvidado = "";
if ((!isset($controlrow["convidado_ativo"]) || intval($controlrow["convidado_ativo"]) == 1) && intval(isset($controlrow["convidado_porip"]) ? $controlrow["convidado_porip"] : 5) > 0 && intval(isset($controlrow["allowregister"]) ? $controlrow["allowregister"] : 1) == 1) {
    $botaoconvidado = "<div style=\"text-align:center;margin:10px 0 4px\"><a href=\"index.php\" data-semajax=\"1\" id=\"botaoconvidado\" class=\"btnjogo bj-neutro\"><img src=\"images/seta_leste.png\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".t("Jogar como convidado (sem cadastro)")."</a></div>";
}
// Troca de idioma (o texto "Idioma / Language" fica nos dois idiomas de propósito, para quem não entende o idioma atual).
$seletor = seletoridioma();
$template = <<<FIMDOTEMPLATE
<table width="100%"><tr><td width="100%" align="center"><center><img src="images/login.gif" alt="" /></center></td></tr></table>$conteudouser
$informativo<form action="login.php?do=login" method="post" id="formback" name="login" autocomplete="on"><fieldset id="field2"><legend>{$t("Fazer Login")}</legend>
<div align="right" class="small">Idioma / Language: $seletor</div>
<table width="100%">
<tr><td width="30%"><label for="nomeform">{$t("Nome da Conta:")}</label></td><td><input type="text" size="30" maxlength="30" name="username" id="nomeform" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required="required" /></td></tr>
<tr><td><label for="senhaform">{$t("Senha:")}</label></td><td><input type="password" size="30" name="password" id="senhaform" autocomplete="current-password" required="required" /><a href="javascript: mostrarpass('senhaform');"><img src="layoutnovo/dropmenu/b4.gif" title="{$t("Mostrar/Ocultar Senha")}" border="0" alt="" /></a></td></tr>
<tr><td>{$t("Lembrar-me?")}</td><td><input type="checkbox" name="rememberme" value="yes" /> {$t("Sim")}</td></tr>
<tr><td colspan="2"><div class="buttons"><button type="submit" class="positive btnjogo bj-confirmar" name="submit"><img src="images/botao_sim.png" width="16" height="16" alt="" /> {$t("Entrar")}</button><button type="reset" class="negative btnjogo bj-cancelar" name="reset"><img src="images/botao_limpar.png" width="16" height="16" alt="" /> {$t("Resetar")}</button></div></td></tr>
<tr><td colspan="2">{$t("Marcando a opção \"Lembrar-me\" sua informação será guardada em um cookie e você não terá que fazer login da próxima vez que você entrar no jogo.")}
</td></tr>
</table></fieldset>
$botaoconvidado<br />{$t("Quer jogar? Você precisa <a href=\"users.php?do=register\">registrar seu próprio personagem.</a>")}<br /><br />{$t("Você também pode <a href=\"users.php?do=changepassword\">mudar sua senha</a>, ou <a href=\"users.php?do=lostpassword\">requerer uma nova senha</a> se você perdeu a sua.")}
<br /><br />
{$t("Caso você tenha se registrado e não tenha recebido o e-mail de confirmação, você pode <a href=\"ativarconta.php\">pedir o reenvio do código de ativação</a> agora mesmo.")}
</form>
FIMDOTEMPLATE;
?>
