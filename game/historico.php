<?php // historico.php :: Monta o histórico das últimas ações do jogador (exibido na coluna da esquerda).
// O histórico fica em users.historico (TEXT), com as mensagens separadas por ";;". Guardamos e mostramos as
// control.historicomax mais recentes (padrão 12, de 5 a 30; antes eram 5 fixas — históricos antigos continuam valendo).
// Mensagens gravadas com i18n() ("i18n:Chave|valor") são traduzidas na hora de mostrar, no idioma de quem lê.

global $userrow, $controlrow;
$historicomax = (is_array($controlrow) && isset($controlrow["historicomax"])) ? max(5, min(30, intval($controlrow["historicomax"]))) : 12;
$fimh = ""; // Linhas no padrão das caixas "cxb" (templates/primary.php): uma .cxb-linha por mensagem, a mais nova primeiro.

if ($userrow["historico"] != "None" && $userrow["historico"] != ""){
    $historico = explode(";;",$userrow["historico"]);
    // Guarda apenas as $historicomax mensagens mais recentes.
    if (count($historico) > $historicomax) {
        $historico = array_slice($historico, -$historicomax);
        $userrow["historico"] = implode(";;", $historico);
        $updatequery = doquery("UPDATE {{table}} SET historico='".addslashes($userrow["historico"])."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    }
    // Clicável: as setas ⬇/⬆ do quadro (leftnav.php) abrem todas as mensagens ou fecham deixando só as 3 mais recentes.
    // A escolha fica no cookie "histaberto" (o servidor já desenha aberto/fechado, sem piscar).
    $histaberto = isset($_COOKIE["histaberto"]) && $_COOKIE["histaberto"] === "1";
    $quantos = count($historico) - 1;
    $n = 0;
    for ($i = $quantos; $i > -1; $i--){
        $n++;
        $extra = ($n > 3) ? " histextra\"".($histaberto ? "" : " style=\"display:none\"") : "\"";
        $fimh .= "<div class=\"cxb-linha".$extra.">".textoi18n($historico[$i])."</div>";
    }
    if ($quantos + 1 > 3) { $fimh .= "<div id=\"histmais\" class=\"cxb-linha\" style=\"text-align:center;font-size:10px".($histaberto ? ";display:none" : "")."\"><a href=\"javascript:historicoabrir(true)\">".tf("+%s mensagens antigas", $quantos + 1 - 3)."</a></div>"; }
}else{
    $fimh .= "<div class=\"cxb-vazio\">".t("Não há mensagens no histórico.")."</div>";
}

?>
