<?php // lang/en/variantes_banco.php :: nomes em inglês das variantes raras lidos da tabela variantes (admin.php?do=variantes).
// Carregado depois de variantes.php (ordem alfabética): o nome digitado no painel tem prioridade.
if (!function_exists('doquery')) { return array(); }
$variantesbanco = array();
$vq = @doquery("SELECT nome,nome_en FROM {{table}}", "variantes");
while ($vq && $vr = mysqli_fetch_array($vq)) {
    if (trim((string)$vr["nome"]) != "" && trim((string)$vr["nome_en"]) != "") { $variantesbanco[$vr["nome"]] = $vr["nome_en"]; }
}
return $variantesbanco;
