<?php // ativarconta.php :: Reenvia o código de ativação da conta para o e-mail cadastrado (para quem não recebeu o e-mail do registro).

// Antes esta página ativava qualquer conta só com o e-mail digitado, o que anulava a verificação por e-mail.
// Agora ela apenas manda o código de novo (no idioma em uso); a ativação continua sendo feita em users.php?do=verify.
define('USERS_SOMENTE_FUNCOES', true);
include('users.php'); // lib.php, cookies.php, $controlrow e as funções de e-mail (sendregmail / mymail), sem executar as rotas.

if (isset($_POST["submit"])) {

    $email = pegartexto($_POST, "mail");
    if (is_email($email)) {
        $query = doquery("SELECT id,email,verify FROM {{table}} WHERE email='$email' LIMIT 1", "users");
        if (mysqli_num_rows($query) == 1) {
            $row = mysqli_fetch_array($query);
            // verify = '1' significa conta já ativada; senão o campo guarda o código de 8 letras.
            if ($row["verify"] != "1") {
                $codigo = $row["verify"];
                if (preg_match("/^[A-Z]{8}$/", $codigo) != 1) {
                    $codigo = gerarcodigoverificacao();
                    doquery("UPDATE {{table}} SET verify='$codigo' WHERE id='".intval($row["id"])."' LIMIT 1", "users");
                }
                if (!sendregmail($row["email"], $codigo)) { error_log("Naruto RPG: falha ao reenviar o código de ativação da conta ".intval($row["id"])); }
            }
        }
    }

    // Mesma resposta em todos os casos, para não revelar quais e-mails têm conta (nem se a conta já está ativa).
    header("Location: ativarconta.php?conteudo=".rawurlencode(t("Se houver uma conta ainda não ativada com esse e-mail, o código de ativação foi enviado novamente."))); die();

}

$conteudo = "";
if (lermsg("conteudo") != "") {
    $conteudo = "<font color=\"brown\"><center>".strip_tags(lermsg("conteudo"))."</center></font>";
}

$t = "t";
$page = <<<FIMDAPAGINA
<table width="100%"><tr><td width="100%" align="center"><center><img src="images/ativarconta.gif" alt="" /></center></td></tr></table>$conteudo
<form action="ativarconta.php" method="post" id="formback">
<fieldset id="field2"><legend>{$t("Reenviar Código de Ativação")}</legend>
{$t("Se você se registrou no Naruto! Nigeru RPG e não recebeu o e-mail com o código de ativação, preencha o campo abaixo com o e-mail da sua conta e nós enviaremos o código novamente.")}<br /><br /><center>
<font color="brown">{$t("Depois de receber o código, ative sua conta na <a href=\"users.php?do=verify\">Página de Verificação</a>. Lembre-se de olhar também a pasta de spam do seu e-mail.")}</font></center>
<br />
<center>{$t("Digite seu e-mail:")}<br />
<input type="text" size="40" name="mail" maxlength="100" /><br /><br />
<div class="buttons"><button type="submit" class="positive btnjogo bj-confirmar" name="submit"><img src="images/botao_enviar.png" width="16" height="16" alt="" /> {$t("Reenviar Código")}</button></div></center>
</fieldset></form>
FIMDAPAGINA;
display($page, t("Ativar Conta"), false, false, false, false);

?>
