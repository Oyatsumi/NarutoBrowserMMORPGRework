<?php // templates/batalha1.php :: Saguão do duelo (users.php?do=batalha1): placar, desafios recebidos/enviados, ninjas online no mesmo lugar e desafio pelo nome.
// Aceita ?nomedochar= para o campo do nome já vir preenchido (link "Duelar" da ficha dos jogadores).
$t = "t";
$nomedochar = (isset($_GET['nomedochar']) && !is_array($_GET['nomedochar'])) ? stripslashes($_GET['nomedochar']) : "";
$template = <<<FIMDOTEMPLATE
{{estilo}}{{titulo}}
{{mensagem}}
<div class="duelofaixa">{{placar}} &middot; <a href="rank.php?ord=duelos">{$t("Rank de duelos")}</a></div>
{{recebidos}}
{{enviado}}
{{aqui}}
<div class="duelopainel"><div class="titulo">{$t("Desafiar pelo nome")}</div><div class="corpo">
<form action="users.php?do=batalha1" method="post">{{csrf}}
<table class="duelolista"><tr><td>{$t("Duelar com(Nome do Jogador):")}</td><td><input type="text" name="jogador" size="25" maxlength="30" value="$nomedochar" /></td></tr>
<tr><td>{$t("Aposta:")}</td><td><input type="text" name="aposta" value="0" size="8" maxlength="8" /> Ryou <span class="light">({$t("os dois precisam ter esse valor")})</span></td></tr>
<tr><td></td><td><button type="submit" name="acao" value="desafiar" class="btnjogo bj-ataque"><img src="images/botao_desafiar.png" width="16" height="16" alt="" /> {$t("Desafiar")}</button></td></tr></table>
</form></div></div>
{{ultimos}}
<span class="light">{{regras}}</span>
{{atualizar}}
FIMDOTEMPLATE;
?>
