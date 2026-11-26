<?php
// towns.php (template) :: Página principal da cidade. Preenchido pela função dotown() do index.php.
// O título vem de cabecalhodacidade() (cidadesconteudo.php): imagem images/town_ID.gif ou, se a vila ainda
// não tiver essa imagem, o nome em texto estilizado; mais a descrição da vila (towns.descricao), se houver.
global $userrow;
include_once('cidadesconteudo.php');
$cabecalhocidade = "{{imagemcidade}}";
if (isset($GLOBALS["cidadeatualrow"]) && is_array($GLOBALS["cidadeatualrow"])) {
    $cidaderow = $GLOBALS["cidadeatualrow"]; // linha lida por dotown() (index.php) nesta mesma requisição.
    if ($cidaderow) { $cabecalhocidade = cabecalhodacidade($cidaderow); }
} elseif (isset($userrow["latitude"])) {
    $cidadequery = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
    $cidaderow = mysqli_fetch_array($cidadequery);
    if ($cidaderow) { $cabecalhocidade = cabecalhodacidade($cidaderow); }
}
$t = "t"; // {$t("...")} traduz dentro do heredoc.
$template = <<<FIMDOTEMPLATE
<table width="100%">
<tr><td align="center">
<center>$cabecalhocidade</center>
{{indexconteudo}}
{{htmlnapag}}
<br>
{{fimconteudo}}
<!-- Sair da vila: um passo para um lado sorteado (explore.php :: sairvila). -->
<form action="index.php?do=move" method="post" style="margin:8px 0 2px;text-align:center"><button type="submit" name="sairvila" value="1" id="botaosairvila" class="btnjogo bj-neutro" title="{$t("Sai da vila andando para um lado sorteado")}"><img src="images/seta_leste.png" width="16" height="16" alt="" style="vertical-align:middle"> {$t("Sair da vila")}</button></form>

</td></tr>
<tr><td><center>
{{news}}
<table width="95%">
<tr><td width="50%">
<center>{{whosonline}}</center>
</td></tr><tr><td>
{{babblebox}}
</td></tr>
</table>
</td></tr>
</table>
FIMDOTEMPLATE;
?>
