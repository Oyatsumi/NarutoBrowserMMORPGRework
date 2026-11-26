<?php // lang/en/conteudo_jutsus_banco.php :: traduções em inglês dos jutsus lidas do próprio banco (spells.name_en, descricao_en) e dos pergaminhos de jutsu ("Pergaminho: Nome" -> "Scroll: Name").
// Como o conteudo_vilas_banco.php, este arquivo não tem lista fixa: o admin digita o nome e a descrição em inglês ao criar/editar
// o jutsu (admin.php?do=editspell) e o t() passa a traduzir t($spellrow["name"]) em todo o jogo. Jutsus sem name_en continuam
// traduzidos pela lista fixa antiga (conteudo_monstros_drops_jutsus.php).
if (!function_exists('doquery')) { return array(); }
$jutsusbanco = array();
$jutsusquery = doquery("SELECT id,name,name_en,descricao,descricao_en FROM {{table}}", "spells");
$nomesen = array();
while ($jutsurow = mysqli_fetch_array($jutsusquery)) {
    foreach (array("name", "descricao") as $coluna) {
        if (trim((string)$jutsurow[$coluna]) != "" && trim((string)$jutsurow[$coluna."_en"]) != "") {
            // A chave vale com e sem escape (o texto do admin chega escapado pelo lib.php).
            $jutsusbanco[html_entity_decode(stripslashes($jutsurow[$coluna]), ENT_QUOTES, "UTF-8")] = $jutsurow[$coluna."_en"];
            $jutsusbanco[$jutsurow[$coluna]] = $jutsurow[$coluna."_en"];
        }
    }
    if (trim((string)$jutsurow["name_en"]) != "") { $nomesen[intval($jutsurow["id"])] = $jutsurow["name_en"]; }
}
// Pergaminhos de jutsu (drops.ensina_jutsu): "Pergaminho: <nome do jutsu>" vira "Scroll: <nome em inglês>".
$pergquery = doquery("SELECT name,ensina_jutsu FROM {{table}} WHERE ensina_jutsu>0", "drops");
while ($pergrow = mysqli_fetch_array($pergquery)) {
    if (isset($nomesen[intval($pergrow["ensina_jutsu"])]) && strpos($pergrow["name"], "Pergaminho: ") === 0) {
        $jutsusbanco[html_entity_decode(stripslashes($pergrow["name"]), ENT_QUOTES, "UTF-8")] = "Scroll: ".$nomesen[intval($pergrow["ensina_jutsu"])];
    }
}
return $jutsusbanco;
