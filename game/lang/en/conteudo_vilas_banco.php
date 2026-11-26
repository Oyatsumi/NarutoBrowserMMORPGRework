<?php // lang/en/conteudo_vilas_banco.php :: traduções em inglês das vilas lidas do próprio banco (towns.name_en, kage_en, descricao_en).
// Diferente dos outros arquivos de tradução, este não tem uma lista fixa: o admin digita o nome, o Kage e a descrição
// em inglês ao criar/editar a vila (admin.php?do=edittown) e o t() passa a traduzir t($townrow["name"]) em todo o jogo
// (lista de viagem, mini mapa, missões...) sem precisar editar um arquivo. Como é carregado depois de
// conteudo_itens_vilas.php (ordem alfabética), o texto do banco tem prioridade sobre a lista fixa antiga.
if (!function_exists('doquery')) { return array(); }
$vilasbanco = array();
$vilasquery = doquery("SELECT name,name_en,kage,kage_en,descricao,descricao_en FROM {{table}}", "towns");
while ($vilarow = mysqli_fetch_array($vilasquery)) {
    foreach (array("name", "kage", "descricao") as $coluna) {
        if (trim((string)$vilarow[$coluna]) != "" && trim((string)$vilarow[$coluna."_en"]) != "") {
            // O texto em inglês fica como foi gravado (já escapado na entrada); a chave vale com e sem escape.
            $vilasbanco[html_entity_decode(stripslashes($vilarow[$coluna]), ENT_QUOTES, "UTF-8")] = $vilarow[$coluna."_en"];
            $vilasbanco[$vilarow[$coluna]] = $vilarow[$coluna."_en"];
        }
    }
}
return $vilasbanco;
