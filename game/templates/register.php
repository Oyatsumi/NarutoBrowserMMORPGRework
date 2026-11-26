<?php // templates/register.php :: Formulário de criação de conta (users.php?do=register). As marcações {{...}} vêm da tabela control (já traduzidas pelo users.php).
$t = "t";
// A vila natal não é mais escolhida aqui: todo ninja novo recebe a vila inicial (control.vilainicial), ver users.php :: register().
$template = <<<FIMDOTEMPLATE
<style>
/* O formulário não pode ser mais largo que a coluna do meio (campos e listas longas empurravam o layout). */
#formback { max-width: 100%; }
#formback fieldset { min-width: 0; margin: 0; }
#formback table { table-layout: fixed; width: 100%; }
#formback td { word-wrap: break-word; overflow-wrap: break-word; }
#formback input[type=text], #formback input[type=email], #formback input[type=password], #formback select { max-width: 92%; box-sizing: border-box; }
</style>
<table width="100%"><tr><td width="100%" align="center"><center><img src="images/criarconta.gif" alt="" /></center></td></tr></table>
<form action="users.php?do=register" method="post" id="formback"><fieldset id="field2"><legend>{$t("Dados da Conta")}</legend>
<table width="100%"><tbody>
<tr><td width="30%">{$t("Nome da Conta:")}</td><td><input type="text" name="username" size="30" maxlength="30" id="nomedaconta1" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" /><br />{$t("O nome da conta deve conter 30 caracteres ou menos e ser alfanumérico (letras sem acento, números, \"_\" ou \"-\"; não pode conter espaços). É aconselhável que você use o nome da sua conta diferente do nome do seu personagem, para garantir uma segurança maior.")}<br /><br /><br /></td></tr>
<tr><td>{$t("Senha:")}</td><td><input id="p1senha" type="password" name="password1" size="30" maxlength="72" autocomplete="new-password" /><a href="javascript: mostrarpass('p1senha');"><img src="layoutnovo/dropmenu/b4.gif" title="{$t("Mostrar/Ocultar Senha")}" border="0" alt="" /></a></td></tr>
<tr><td>{$t("Verificar Senha:")}</td><td><input type="password" name="password2" size="30" maxlength="72" id="p2senha" autocomplete="new-password" /><a href="javascript: mostrarpass('p2senha');"><img src="layoutnovo/dropmenu/b4.gif" title="{$t("Mostrar/Ocultar Senha")}" border="0" alt="" /></a><br />{$t("A senha deve ter de 6 a 72 caracteres (pode usar qualquer caractere).")}<br /><br /><br /></td></tr>
<tr><td>{$t("E-mail:")}</td><td><input type="email" name="email1" size="30" maxlength="100" autocomplete="email" /></td></tr>
<tr><td>{$t("Verificar E-Mail:")}</td><td><input type="text" name="email2" size="30" maxlength="100" />{{verifytext}}<br /><br /><br /></td></tr>
<tr><td>{$t("Nome do Personagem:")}</td><td><input type="text" name="charname" size="30" maxlength="30" /></td></tr>
<tr><td>{$t("Especialização do Personagem:")}</td><td>{{avisoclasse}}</td></tr>
<tr><td>{$t("Vila Natal:")}</td><td>{{avisovila}}</td></tr>
<tr><td>{$t("Dificuldade:")}</td><td><select name="difficulty"><option value="1">{{diff1name}}</option><option value="2">{{diff2name}}</option><option value="3">{{diff3name}}</option></select><br /><span class="small">{$t("Pode ser trocada mais tarde, no jogo, pelas provações do Mestre das Provações (perto da vila inicial).")}</span></td></tr>
{{perguntas}}
<tr><td colspan="2">{$t("Veja a <a href=\"help.php\">Ajuda</a> para mais informações sobre Especialização dos Personagens e sobre os leveis de dificuldade.")}<br /><br /></td></tr>
<tr><td colspan="2">
<center><div class="buttons"><button type="submit" class="positive btnjogo bj-confirmar" name="submit"><img src="images/botao_criar.png" width="16" height="16" alt="" /> {$t("Criar Conta")}</button>
<button type="reset" class="negative btnjogo bj-cancelar" name="reset"><img src="images/botao_limpar.png" width="16" height="16" alt="" /> {$t("Resetar")}</button>
</div></center>
</td></tr>
</tbody></table>
</fieldset></form>
FIMDOTEMPLATE;
?>
