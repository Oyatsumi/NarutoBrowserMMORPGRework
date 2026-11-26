<?php // Extrai as chaves literais de t()/tf()/{$t()}/i18n() dos arquivos passados (usa o tokenizer do PHP).
$chaves = array();
foreach (array_slice($argv, 1) as $arq) {
    $tok = token_get_all(file_get_contents($arq));
    $n = count($tok);
    for ($i = 0; $i < $n; $i++) {
        $x = $tok[$i];
        $nome = null;
        if (is_array($x) && $x[0] == T_STRING && in_array($x[1], array("t", "tf", "i18n"))) { $nome = $x[1]; }
        if (is_array($x) && $x[0] == T_VARIABLE && in_array($x[1], array('$t', '$tf'))) { $nome = $x[1]; }
        if ($nome === null) continue;
        $j = $i + 1; while ($j < $n && is_array($tok[$j]) && $tok[$j][0] == T_WHITESPACE) $j++;
        if ($j >= $n || $tok[$j] !== "(") continue;
        $j++; while ($j < $n && is_array($tok[$j]) && $tok[$j][0] == T_WHITESPACE) $j++;
        if ($j < $n && is_array($tok[$j]) && $tok[$j][0] == T_CONSTANT_ENCAPSED_STRING) {
            $k = $tok[$j+1] ?? null;
            if ($k === ")" || $k === ",") { $chaves[eval("return ".$tok[$j][1].";")] = $arq; }
        }
    }
}
foreach ($chaves as $c => $a) { echo $c, "\n"; }
