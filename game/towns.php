<?php // towns.php :: Ações dentro das cidades: pousada, loja de itens (compra/troca), consumíveis e venda de itens da mochila, loja de mapas e viagem entre cidades.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }
// Incluído pelo index.php (que já validou login, banimento e duelo) e pelo explore.php ao pisar numa cidade.
include_once('funcoesinclusas.php');
include_once('funcoesmissoesextras.php'); // mx_coordenada() (posição do comprador distante).
include_once('funcoesjutsus.php'); // itembatalharesumo() (ferramentas ninja na loja).
include_once('cidadesconteudo.php'); // Regras de cada cidade lidas da tabela towns (cidadetemservico(), cidadesecreta(), cidadetemlojademapas()).

if (!function_exists('cidadedojogador')){
function cidadedojogador($campos) { // Cidade onde o jogador está agora. Quem está lutando vai para a luta; quem não está numa cidade recebe erro.

    global $userrow;

    if ($userrow["currentaction"] == "Fighting") { header("Location: index.php?do=fight"); die(); }
    $townquery = doquery("SELECT $campos FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
    if (mysqli_num_rows($townquery) != 1) { display(t("Tentativa de trapaça detectada.")."<br>", t("Error")); }
    return mysqli_fetch_array($townquery);

}}

if (!function_exists('listadeids')){
function listadeids($texto) { // Converte um campo "1,2,3" (itemslist, towns...) num array de inteiros válidos.

    $ids = array();
    foreach (explode(",", (string)$texto) as $parte) {
        if (is_numeric(trim($parte)) && intval($parte) > 0) { $ids[] = intval($parte); }
    }
    return array_values(array_unique($ids));

}}

if (!function_exists('recarregarjogador')){
function recarregarjogador() { // Lê o jogador de novo do banco (depois de uma compra, para a lista da loja não ficar desatualizada).

    global $userrow;
    $userquery = doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    $userrow = mysqli_fetch_array($userquery);

}}

function inn() { // Pousada: paga o preço da cidade e enche HP, CH, TP e EP até o máximo (NP só se ganha andando e descansando).

    global $userrow;

    $townrow = cidadedojogador("id,name,innprice");
    if (!cidadetemservico($townrow["id"], "pousada")) { header("Location: index.php"); die(); } // Vila sem pousada (coluna towns.tem_pousada).
    $preco = max(0, intval($townrow["innprice"]));

    if ($userrow["gold"] < $preco) { voltarcommensagem(t("Você não tem Ryou suficiente para passar a noite numa pousada.")); }

    if (isset($_POST["submit"])) {

        if (!csrf_confere()) { voltarcommensagem(t("Formulário expirado. Tente novamente.")); } // CSRF: só o botão "Sim" da própria página.
        // O "gold>=" no WHERE impede pagar com saldo que já foi gasto em outra aba.
        doquery("UPDATE {{table}} SET gold=gold-$preco,currenthp=maxhp,currentmp=maxmp,currenttp=maxtp,currentep=maxep WHERE id='".intval($userrow["id"])."' AND gold>=$preco LIMIT 1", "users");
        voltarcommensagem(t("Você descansou e acordou se sentindo regenarado e pronto pra ação."));

    } elseif (isset($_POST["cancel"])) {

        header("Location: index.php"); die();

    }

    $title = t("Pousada");
    $page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/descansar.gif\" /></center></td></tr></table>".personagemgeral(t("Descansar numa pousada vai encher o seu HP, CH, TP e EP ao seu máximo.")."
<br />".tf("Uma noite de sono na pousada vai te custar: <b>%s Ryou</b>. Está tudo bem?", $preco)."<br /><br /><form action=\"index.php?do=inn\" method=\"post\">".csrf_campo()."<div class=\"buttons\"><button type=\"submit\" class=\"positive btnjogo bj-confirmar\" name=\"submit\"><img src=\"images/botao_sim.png\" width=\"16\" height=\"16\" alt=\"\"> ".t("Sim")."</button><button type=\"reset\" class=\"negative btnjogo bj-cancelar\" name=\"reset\" onclick=\"irpara('index.php')\"><img src=\"images/botao_nao.png\" width=\"16\" height=\"16\" alt=\"\"> ".t("Não")."</button></div></form>", 'personagem4', "Mike");

    display($page, $title);

}

// Cada tipo de item ocupa um espaço do personagem. "durab" é a posição no campo durabilidade ("-,arma,colete,bandana,slot1,slot2,slot3,slot4")
// e "poder" é o atributo que o item soma (arma = ataque; colete e bandana = defesa). Os textos variam um pouco por tipo (originais do jogo).
if (!function_exists('espacodoitem')){
function espacodoitem($tipo) {

    $espacos = array(
        1 => array("id"=>"weaponid", "nome"=>"weaponname", "durab"=>1, "poder"=>"attackpower",  "trocar"=>"Você está comprando o(a) %s, então eu vou comprar o seu <b>%s</b> por %s Ryou. Tudo bem?", "novo"=>"Você está comprando o(a) %s, está tudo certo?"),
        2 => array("id"=>"armorid",  "nome"=>"armorname",  "durab"=>2, "poder"=>"defensepower", "trocar"=>"Você está comprando o(a) %s, então eu vou comprar o seu: <b>%s</b> por %s Ryou. Está tudo bem?", "novo"=>"Você está comprando o(a) %s, está tudo certo?"),
        3 => array("id"=>"shieldid", "nome"=>"shieldname", "durab"=>3, "poder"=>"defensepower", "trocar"=>"Você está comprando o(a) %s, então eu vou comprar o seu: <b>%s</b> por %s Ryou. Está bem?", "novo"=>"Você está comprando o(a) %s, está bem?"));
    $tipo = intval($tipo);
    return isset($espacos[$tipo]) ? $espacos[$tipo] : false;

}}

if (!function_exists('itemdaloja')){
function itemdaloja($id) { // Item que está à venda na cidade atual (ou erro de trapaça se não estiver).

    $townrow = cidadedojogador("id,name,itemslist");
    if (!cidadetemservico($townrow["id"], "loja")) { header("Location: index.php"); die(); } // Vila sem loja (towns.tem_loja).
    $id = intval($id);
    if (!in_array($id, listadeids($townrow["itemslist"]))) { display(t("Tentativa de trapaça detectada.")."<br>", t("Error")); }
    $itemsquery = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "items");
    $itemsrow = mysqli_fetch_array($itemsquery);
    if (!$itemsrow || espacodoitem($itemsrow["type"]) === false) { display(t("Tentativa de trapaça detectada.")."<br>", t("Error")); }
    return $itemsrow;

}}

if (!function_exists('itemequipado')){
function itemequipado($espaco) { // Item que o jogador usa hoje no espaço (ou um item "vazio" se não tiver nenhum).

    global $userrow;
    $vazio = array("name"=>"None", "attribute"=>0, "buycost"=>0, "special"=>"X");
    if (intval($userrow[$espaco["id"]]) == 0) { return false; }
    $itemsquery = doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow[$espaco["id"]])."' LIMIT 1", "items");
    $itemsrow = mysqli_fetch_array($itemsquery);
    return ($itemsrow) ? $itemsrow : $vazio;

}}

if (!function_exists('destinoantigo')){
function destinoantigo($row, $texto) { // Para onde vai o item que sai do espaço na compra: "mochila", "banco" (mochila cheia) ou false (os dois cheios).

    $copia = $row;
    if (mochilaguardar($copia, $texto) !== false) { return "mochila"; }
    $banco = (string)$row["bancogeral"];
    $n = ($banco == "None" || $banco == "") ? 0 : count(array_filter(explode(";", $banco), "strlen"));
    $lim = max(30, min(50, isset($row["banco_limite"]) ? intval($row["banco_limite"]) : 30)); // = bancolimite() do funcaoitens.php.
    return ($n < $lim) ? "banco" : false;

}}

if (!function_exists('mensagemloja')){
function abasloja($ativa) { // Botões das seções da loja: equipamentos, consumíveis, venda e reparo (a ativa fica destacada).
    $abas = array("buy"=>array(t("Equipamentos"), "images/ficha_ataque.png"), "consumiveis"=>array(t("Consumíveis"), "images/ficha_hp.png"),
        "vender"=>array(t("Vender"), "images/botao_vender.png"), "reparar"=>array(t("Reparar"), "images/botao_reparar.png"));
    $h = "<style>.abasloja{display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin:6px auto 12px;max-width:520px}"
        .".abasloja a{flex:1 1 0;min-width:0;white-space:nowrap;display:flex;align-items:center;justify-content:center;gap:6px;padding:7px 8px;border:2px solid #8b5a2b;border-radius:5px;background:#fff1c7;color:#452202;font-weight:bold;text-decoration:none;box-shadow:0 2px 0 #8b5a2b}"
        .".abasloja a:hover{background:#ffe3a0}.abasloja a.ativa{background:#452202;color:#fff1c7;border-color:#452202;box-shadow:none;transform:translateY(2px)}"
        .".abasloja img{width:16px;height:16px;image-rendering:pixelated}@media (max-width:480px){.abasloja a{flex:1 1 40%}}</style><div class=\"abasloja\">";
    foreach ($abas as $do => $a) {
        $h .= "<a href=\"index.php?do=$do\"".($do == $ativa ? " class=\"ativa\"" : "")."><img src=\"".$a[1]."\" alt=\"\"> ".htmlspecialchars($a[0], ENT_QUOTES, "UTF-8")."</a>";
    }
    return $h."</div>";
}

function mensagemloja($texto) { // Fala da vendedora Anari (aparece acima da lista de itens).

    return personagemgeral($texto, 'personagem1', 'Anari');

}}

function buy() { // Loja: lista os itens vendidos na cidade atual.

    global $userrow, $conteudo;

    $townrow = cidadedojogador("id,name,itemslist");
    if (!cidadetemservico($townrow["id"], "loja")) { header("Location: index.php"); die(); } // Vila sem loja (towns.tem_loja).
    $ids = listadeids($townrow["itemslist"]);

    if (!isset($conteudo) || $conteudo == "") { $conteudo = mensagemloja(t("Comprando armas, o seu Poder de Ataque aumentará. Comprando coletes e bandanas, o seu Poder de Defesa aumentará.")."<br /><br />".t("Clique em <b>Comprar</b> ao lado de um item. Os itens que têm um <font color=\"red\">*</font> ao lado do nome, também possuem outros atributos além do original.")); }

    $page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/shop.gif\" /></center></td></tr></table>".$conteudo."<center>".abasloja("buy")."".t("Os seguintes itens estão à disposição nessa cidade:")."<br /><br />\n";
    $page .= "<table class=\"lojaitens\" width=\"85%\" style=\"border:1px #000000 solid;text-align:left\" cellspacing=\"0\" cellpadding=\"2\">
	<tr bgcolor=\"#452202\"><td colspan=\"5\"><font color=white>".t("Armas, coletes e bandanas")."</font></td></tr>";

    $icones = array(1 => "<img src=\"images/icon_weapon.gif\" alt=\"weapon\" />", 2 => "<img src=\"images/icon_armor.gif\" alt=\"armor\" />", 3 => "<img src=\"images/icon_shield.gif\" alt=\"shield\" />");
    $fundo = 0;
    if (count($ids) > 0) {
        $itemsquery = doquery("SELECT * FROM {{table}} WHERE id IN (".implode(",", $ids).") ORDER BY id", "items");
        while ($itemsrow = mysqli_fetch_array($itemsquery)) {
            $fundo++;
            $bgcolor = ($fundo % 2 == 0) ? "#E4D094" : "#FFF1C7";
            $attrib = ($itemsrow["type"] == 1) ? t("Poder de Ataque").":" : t("Poder de Defesa").":";
            $nomeitem = t($itemsrow["name"]);
            $icone = isset($icones[$itemsrow["type"]]) ? $icones[$itemsrow["type"]] : "";
            if (intval($itemsrow["type"]) >= 1 && intval($itemsrow["type"]) <= 3) { linhadoitem(",".$itemsrow["id"].",".$itemsrow["type"], $itemsrow); } // a linha já lida vai para o cache (o tooltip não lê de novo).
            $equipadoja = ($userrow["weaponid"] == $itemsrow["id"] || $userrow["armorid"] == $itemsrow["id"] || $userrow["shieldid"] == $itemsrow["id"]);
            $agorajava = conteudoexplic($itemsrow["id"], $itemsrow["type"], 'idatr'.$itemsrow["id"], (intval($itemsrow["durabilidade"]) > 0 ? intval($itemsrow["durabilidade"]) : '*'), "", 1, !$equipadoja); // compara com o equipado no mesmo espaço (menos o próprio equipado). Tooltip com os atributos do item (e a durabilidade com que ele sai da loja).
            // Mesmo desenho da tabela de consumíveis: ícone à esquerda, nome e atributo no meio, preço e botão Comprar à direita.
            $imagem = "<td width=\"40\" style=\"min-width:40px\"><img src=\"layoutnovo/equipamentos/".$itemsrow["id"].".gif\" width=\"34\" height=\"34\" style=\"display:block;border:2px #e4d094 solid;width:34px;height:34px;max-width:none\" onmouseover=\"$agorajava\" id=\"idatr".$itemsrow["id"]."\" onmouseout=\"fecharexplic();\" alt=\"\"></td>";
            $durtexto = (intval($itemsrow["durabilidade"]) > 0) ? " · ".tf("Durabilidade: %s vitórias", intval($itemsrow["durabilidade"])) : "";
            $equipado = ($userrow["weaponid"] == $itemsrow["id"] || $userrow["armorid"] == $itemsrow["id"] || $userrow["shieldid"] == $itemsrow["id"]);
            $page .= "<tr bgcolor=\"$bgcolor\" class=\"lojalinha\">".$imagem;
            if ($equipado) {
                $page .= "<td><span class=\"light\">".$icone." ".$nomeitem."</span><br /><span class=\"small light\">$attrib ".$itemsrow["attribute"].$durtexto."</span></td><td><span class=\"light\">".tf("Preço: <b>%s Ryou</b>", iconeryou().$itemsrow["buycost"])."</span></td><td><span class=\"light\">".t("Já Equipado")."</span></td></tr>\n";
            } else {
                $specialdot = ($itemsrow["special"] != "X" || (isset($itemsrow["special2"]) && $itemsrow["special2"] != "X") || (isset($itemsrow["special3"]) && $itemsrow["special3"] != "X")) ? "<span class=\"highlight\">&#42;</span>" : ""; // * = item com atributo especial.
                $page .= "<td>".$icone." <b>".$nomeitem."</b>$specialdot<br /><span class=\"small\">$attrib <b>".$itemsrow["attribute"]."</b>".$durtexto."</span></td><td>".tf("Preço: <b>%s Ryou</b>", iconeryou().$itemsrow["buycost"])."</td><td><form action=\"index.php?do=buy2:".$itemsrow["id"]."\" method=\"post\" style=\"margin:0\"><button type=\"submit\" name=\"ver\" value=\"".t("Comprar")."\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_comprar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Comprar")."</button></form></td></tr>\n";
            }
        }
    }
    $page .= "</table><br />";
    $page .= t("Se você mudou de ideia, pode retornar à <a href=\"index.php\">cidade</a>.")."</center>";

    display($page, t("Comprar Itens"));

}

function buy2($id) { // Confirmação da compra (preço e para onde vai o item que está no espaço: mochila ou banco).

    global $userrow, $conteudo;

    $itemsrow = itemdaloja($id);
    $espaco = espacodoitem($itemsrow["type"]);

    if ($userrow["gold"] < $itemsrow["buycost"]) {
        $conteudo = mensagemloja(t("Você não tem Ryou suficiente para comprar esse item.")."<br /><br />".t("Você pode retornar à <a href=\"index.php\">cidade</a>, <a href=\"index.php?do=buy\">shop</a>, ou usar os botões de direção para continuar explorando."));
        buy();
    }
    if ($userrow[$espaco["id"]] == $itemsrow["id"]) { $conteudo = mensagemloja(t("Você já está usando esse item.")); buy(); }

    // O item que está no espaço não é mais vendido: vai para a mochila ou, com ela cheia, para o banco (destinoantigo()).
    $pergunta = tf("Você está comprando o(a) <b>%s</b> por <b>%s Ryou</b>. Está tudo certo?", t($itemsrow["name"]), intval($itemsrow["buycost"]));
    if (itemequipado($espaco) !== false) {
        $destino = destinoantigo($userrow, $userrow[$espaco["nome"]].",".intval($userrow[$espaco["id"]]).",".intval($itemsrow["type"]).",X");
        if ($destino === false) { $conteudo = mensagemloja(tf("A sua mochila e o seu banco estão cheios: não tenho onde guardar o seu <b>%s</b>. Libere um espaço e volte aqui.", nomeitemhtml($userrow[$espaco["nome"]]))); buy(); }
        $pergunta .= "<br>".tf(($destino == "mochila") ? "O seu <b>%s</b> vai para a sua <b>mochila</b>." : "A sua mochila está cheia, então o seu <b>%s</b> vai para o seu <b>banco</b>.", nomeitemhtml($userrow[$espaco["nome"]]));
    }
    $aviso = isset($GLOBALS["avisoexpirado"]) ? "<b style=\"color:#a01010\">".$GLOBALS["avisoexpirado"]."</b><br /><br />" : "";
    $conteudo = mensagemloja($aviso.$pergunta."<br /><br /><center><form action=\"index.php?do=buy3:".intval($itemsrow["id"])."\" method=\"post\">".csrf_campo()."<div class=\"buttons\"><button type=\"submit\" class=\"positive btnjogo bj-confirmar\" name=\"submit\"><img src=\"images/botao_sim.png\" width=\"16\" height=\"16\" alt=\"\"> ".t("Sim")."</button>
<button type=\"reset\" class=\"negative btnjogo bj-cancelar\" name=\"reset\" onclick=\"irpara('index.php?do=buy')\"><img src=\"images/botao_nao.png\" width=\"16\" height=\"16\" alt=\"\"> ".t("Não")."</button>
</div></form>");
    buy();

}

function buy3($id) { // Compra de fato: guarda o item antigo do espaço (mochila ou banco), equipa o novo e recalcula os atributos.

    global $userrow, $conteudo, $link;

    if (isset($_POST["cancel"])) { header("Location: index.php"); die(); }
    if (!isset($_POST["submit"])) { buy2($id); } // A compra só acontece pelo botão "Sim" (POST) da confirmação.
    if (!csrf_confere()) { $GLOBALS["avisoexpirado"] = t("Formulário expirado. Tente novamente."); buy2($id); } // CSRF: mostra a confirmação de novo, com o aviso

    // Transação com o jogador travado (FOR UPDATE): outra requisição ao mesmo tempo (ex.: guardar no banco o item
    // equipado) espera esta terminar. Sem isso o item antigo era "vendido" por 1/4 do preço e também ia para o banco.
    mysqli_begin_transaction($link);
    $travado = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
    if (!$travado) { mysqli_rollback($link); header("Location: index.php"); die(); }
    $userrow = $travado;

    $itemsrow = itemdaloja($id);
    $espaco = espacodoitem($itemsrow["type"]);

    if ($userrow["gold"] < $itemsrow["buycost"]) {
        $conteudo = mensagemloja(t("Você não tem Ryou suficiente para comprar esse item.")."<br /><br />".t("Você pode retornar à <a href=\"index.php\">cidade</a>, <a href=\"index.php?do=buy\">shop</a>, ou usar os botões de direção para continuar explorando."));
        buy();
    }
    if ($userrow[$espaco["id"]] == $itemsrow["id"]) { $conteudo = mensagemloja(t("Você já está usando esse item.")); buy(); }

    $antigo = itemequipado($espaco);
    if ($antigo === false) { $antigo = array("name"=>"None", "attribute"=>0, "buycost"=>0, "special"=>"X", "special2"=>"X", "special3"=>"X"); }
    // O item antigo não é vendido: vai para a mochila (com a durabilidade que tinha) ou, com a mochila cheia, para o banco.
    // Sem lugar nos dois, a compra é recusada sem mudar nada.
    $mochilaantes = $userrow; $espacosmochila = array(); $destino = ""; $bancoantes = (string)$userrow["bancogeral"];
    if ((string)$userrow[$espaco["nome"]] != "None" && intval($userrow[$espaco["id"]]) > 0) {
        $durabantigo = lerdurabilidade($userrow["durabilidade"]);
        $textoantigo = $userrow[$espaco["nome"]].",".intval($userrow[$espaco["id"]]).",".intval($itemsrow["type"]).",".$durabantigo[$espaco["durab"]];
        $destino = destinoantigo($userrow, $textoantigo);
        if ($destino === false) { mysqli_rollback($link); $conteudo = mensagemloja(tf("A sua mochila e o seu banco estão cheios: não tenho onde guardar o seu <b>%s</b>. Libere um espaço e volte aqui.", nomeitemhtml($userrow[$espaco["nome"]]))); buy(); }
        if ($destino == "mochila") { $espacosmochila = mochilaguardar($userrow, $textoantigo); }
        else { $userrow["bancogeral"] = (($bancoantes == "None" || $bancoantes == "") ? "" : $bancoantes).$textoantigo.";"; }
    }

    // Atributos especiais (campo items.special = "coluna,valor", ex.: "maxhp,5"; "X" = nenhum).
    // O do item novo é somado e o do antigo é retirado. Força soma no ataque e destreza na defesa.
    $campos = array();
    // Cada item pode ter até 3 bônus (special, special2, special3); só colunas da lista branca atributovalido().
    $especiais = array();
    foreach (array("special", "special2", "special3") as $c) {
        if (isset($itemsrow[$c])) { $especiais[] = array($itemsrow[$c], 1); }
        if (isset($antigo[$c])) { $especiais[] = array($antigo[$c], -1); }
    }
    foreach ($especiais as $especial) {
        if ($especial[0] == "X" || $especial[0] == "") { continue; }
        $special = explode(",", $especial[0]);
        $tochange = trim($special[0]);
        if (!isset($special[1]) || !atributovalido($tochange) || !array_key_exists($tochange, $userrow)) { continue; }
        $valor = intval($special[1]) * $especial[1];
        $userrow[$tochange] = intval($userrow[$tochange]) + $valor;
        $campos[$tochange] = $userrow[$tochange];
        if ($tochange == "strength") { $userrow["attackpower"] += $valor; }
        if ($tochange == "dexterity") { $userrow["defensepower"] += $valor; }
    }

    // O item antigo vendido leva junto o refino "+N" (N*20 de ataque/defesa) e o bônus da variante rara (":efeito").
    // Antes o refino ficava "grudado" no jogador depois da venda.
    $nomeantigo = (string)$userrow[$espaco["nome"]];
    if ($nomeantigo != "None" && isset($antigo["id"])) {
        $refinoantigo = explode("+", separarvariante($nomeantigo)[0]);
        if (isset($refinoantigo[1])) { $userrow[$espaco["poder"]] = intval($userrow[$espaco["poder"]]) - intval($refinoantigo[1]) * 20; }
        foreach (bonusvariante($nomeantigo, $antigo, "items") as $b) {
            $partes = explode(",", $b);
            $userrow[$partes[0]] = intval($userrow[$partes[0]]) - intval($partes[1]);
            $campos[$partes[0]] = $userrow[$partes[0]];
        }
    }

    // Novo ouro (limitado ao máximo da coluna) e novo poder de ataque/defesa.
    $campos["gold"] = intval($userrow["gold"]) - intval($itemsrow["buycost"]); // preço cheio, o mesmo mostrado na lista (nada é vendido).
    foreach ($espacosmochila as $bp) { $campos[$bp] = $userrow[$bp]; }
    if ($destino == "banco") { $campos["bancogeral"] = $userrow["bancogeral"]; }
    $userrow[$espaco["poder"]] = intval($userrow[$espaco["poder"]]) + intval($itemsrow["attribute"]) - intval($antigo["attribute"]);
    // Os dois são gravados: antes um colete com "strength" perdia o bônus de ataque (só a defesa era salva).
    $campos["attackpower"] = $userrow["attackpower"];
    $campos["defensepower"] = $userrow["defensepower"];
    $campos[$espaco["id"]] = intval($itemsrow["id"]);
    $campos[$espaco["nome"]] = $itemsrow["name"];

    // Bônus de conjuntos: calculado numa cópia com o item novo no espaço ($userrow mantém o id antigo para o WHERE).
    $simulado = $userrow;
    $simulado[$espaco["id"]] = intval($itemsrow["id"]); $simulado[$espaco["nome"]] = $itemsrow["name"];
    conjuntosrecalcular($simulado);
    if (array_key_exists("conjuntobonus", $simulado)) {
        foreach ($simulado as $coluna => $valor) {
            if (is_int($coluna) || $coluna == $espaco["id"] || $coluna == $espaco["nome"] || !(atributovalido($coluna) || $coluna == "conjuntobonus")) { continue; }
            if ((string)$valor !== (string)$userrow[$coluna]) { $userrow[$coluna] = $valor; $campos[$coluna] = $valor; }
        }
    }

    // Se o item antigo aumentava algum máximo, os pontos atuais não podem ficar acima do novo máximo.
    foreach (array("hp", "mp", "tp", "np", "ep") as $ponto) {
        $campos["current".$ponto] = min(intval($userrow["current".$ponto]), intval($userrow["max".$ponto]));
    }

    // Item comprado na loja sai com a durabilidade cheia (items.durabilidade; 0 = infinita "X", o caso dos itens fracos).
    $durab = lerdurabilidade($userrow["durabilidade"]); // 8 posições (o acessório 4 não pode se perder aqui).
    $durab[$espaco["durab"]] = durabilidadenova($itemsrow["type"], $itemsrow["id"]);
    $campos["durabilidade"] = juntardurabilidade($durab);

    // O "gold=" no WHERE garante que nada mudou desde a leitura (duas compras em abas paralelas não saem pelo preço de uma).
    $set = array();
    foreach ($campos as $coluna => $valor) { $set[] = "`".$coluna."`='".addslashes((string)$valor)."'"; }
    // O WHERE também confere o item antigo do espaço (id e nome): se ele saiu do espaço nesse meio tempo, nada é vendido.
    $ondemochila = sqlmochila($userrow, $mochilaantes, $espacosmochila)[1].(($destino == "banco") ? " AND bancogeral='".addslashes($bancoantes)."'" : "");
    doquery("UPDATE {{table}} SET ".implode(",", $set)." WHERE id='".intval($userrow["id"])."'$ondemochila AND gold='".intval($userrow["gold"])."' AND `".$espaco["id"]."`='".intval($userrow[$espaco["id"]])."' AND `".$espaco["nome"]."`='".addslashes($userrow[$espaco["nome"]])."' LIMIT 1", "users");
    $comprou = (mysqli_affected_rows($link) == 1);
    if ($comprou) { mysqli_commit($link); } else { mysqli_rollback($link); }

    recarregarjogador();
    if ($comprou) {
        $fala = t('O negócio foi realizado com sucesso. Muito obrigado(a) por comprar esse item!');
        if ($destino == "mochila") { $fala .= "<br>".tf("Guardei o seu <b>%s</b> na sua mochila.", nomeitemhtml($nomeantigo)); }
        elseif ($destino == "banco") { $fala .= "<br>".tf("A sua mochila estava cheia, então mandei o seu <b>%s</b> para o seu banco.", nomeitemhtml($nomeantigo)); }
        $conteudo = mensagemloja($fala);
    }
    else { $conteudo = mensagemloja(t('O negócio não pôde ser realizado. Por favor tente novamente.')); }
    buy();

}


/* ------------------------------------------------------------------------
   Consumíveis da loja e venda de itens da mochila (index.php?do=consumivel / do=vender).
   Preço base de venda de um item da mochila ("nome,id,tipo,durabilidade"), antes do multiplicador
   control.vendamochila (%, configurado no painel do administrador; 0 = as lojas não compram nada):
     - arma/colete/bandana (tipo 1-3) ... 1/4 do preço da loja + 25 por refino "+N";
     - drop (tipo 4-6) ................. 10 + 6 × nível do drop (drops.mlevel);
     - poções (hp, mp, tp, np, ep, hm, hmt) 1/3 do preço da loja (drops.precoloja); se nenhuma loja vende, metade do que recuperam;
     - pergaminho de jutsu ............. 50 + 20 × nível mínimo do jutsu (spells.nivelminimo), no comprador de pergaminhos;
     - Pergaminho de Retorno / Incenso . 1/3 do preço da loja (Teleporte Aleatório: 1/3 do preço base);
     - outros especiais (dia, per, bk...) 50.
   Variantes raras (":efeito" no nome) valem 3×. Resultado final = max(1, floor(base × multiplicador / 100)).
   ------------------------------------------------------------------------ */

function precovendaitem($texto, $remoto = false) { // Preço (Ryou) que a loja paga por UMA unidade de um item da mochila; 0 = a loja não compra.
    // $remoto = true: comprador de pergaminhos distante (NPC com papel "comprador_pergaminhos"): só compra
    // pergaminhos de jutsu e paga control.compradorremoto % do preço normal (padrão 200% = o dobro).
    // Na vila, control.compradorvila = 0 desliga o comprador de pergaminhos (só o distante compra).

    global $controlrow;
    if ($texto == "None" || $texto == "") { return 0; }
    $mult = isset($controlrow["vendamochila"]) ? intval($controlrow["vendamochila"]) : 100;
    if ($mult <= 0) { return 0; }
    if (itemnaovendavel($texto)) { return 0; } // marcado no painel: "não pode ser vendido em loja".
    $item = array_pad(explode(",", (string)$texto), 4, "X");
    if ($remoto && $item[1] != "jutsu") { return 0; }
    if (!$remoto && $item[1] == "jutsu" && isset($controlrow["compradorvila"]) && intval($controlrow["compradorvila"]) == 0) { return 0; }
    $base = 0;
    if (is_numeric($item[1])) {
        $tipo = intval($item[2]);
        if ($tipo >= 1 && $tipo <= 3) {
            $row = mysqli_fetch_array(doquery("SELECT buycost FROM {{table}} WHERE id='".intval($item[1])."' LIMIT 1", "items"));
            if (!$row) { return 0; }
            $refino = explode("+", separarvariante($item[0])[0]);
            $base = (int)ceil(intval($row["buycost"]) / 4) + (isset($refino[1]) ? 25 * max(0, intval($refino[1])) : 0);
        } elseif ($tipo >= 4 && $tipo <= 6) {
            $row = mysqli_fetch_array(doquery("SELECT mlevel FROM {{table}} WHERE id='".intval($item[1])."' LIMIT 1", "drops"));
            if (!$row) { return 0; }
            $base = 10 + 6 * intval($row["mlevel"]);
        } else { return 0; }
    } elseif ($item[1] == "descanso" || $item[1] == "banco") { // item de descanso / expansão do banco (só de drop): 15 + 2 por nível mínimo.
        $br = linhadoitem($texto);
        $base = $br ? 15 + 2 * intval($br["mlevel"]) : 0;
    } elseif ($item[1] == "bomba") { // ferramenta ninja: 1/3 do preço da loja; só de drop: 4 + 2 por nível mínimo.
        $br = linhadoitem($texto);
        $base = !$br ? 0 : (intval($br["precoloja"]) > 0 ? (int)ceil(intval($br["precoloja"]) / 3) : 4 + 2 * intval($br["mlevel"]));
    } elseif (in_array($item[1], array("hp", "mp", "tp", "np", "ep", "hm", "hmt"), true)) {
        // Poção vendida em alguma loja (drops.precoloja > 0): 1/3 desse preço. Senão: metade do que recupera.
        $pl = mysqli_fetch_array(doquery("SELECT precoloja FROM {{table}} WHERE name='".mysqli_real_escape_string($GLOBALS["link"], separarvariante($item[0])[0])."' AND precoloja>0 LIMIT 1", "drops"));
        $base = $pl ? (int)ceil(intval($pl["precoloja"]) / 3) : (int)ceil(max(0, intval($item[2])) / 2);
    } elseif ($item[1] == "jutsu") {
        $row = mysqli_fetch_array(doquery("SELECT nivelminimo FROM {{table}} WHERE id='".intval($item[2])."' LIMIT 1", "spells"));
        $base = 50 + 20 * ($row ? intval($row["nivelminimo"]) : 0);
        if ($remoto) { $base = (int)floor($base * max(0, isset($controlrow["compradorremoto"]) ? intval($controlrow["compradorremoto"]) : 200) / 100); }
    } else {
        $loja = consumiveisdaloja() + consumiveisespeciais(); // especiais (Teleporte Aleatório) só caem de inimigos, mas a loja compra.
        $base = isset($loja[$item[1]]) ? (int)ceil($loja[$item[1]][1] / 3) : 50; // consumíveis da loja: 1/3 do preço
    }
    if ($base <= 0) { return 0; }
    if (variantedoitem($item[0]) != "") { $base *= 3; }
    return max(1, (int)floor(max(1, $base) * $mult / 100));

}

function codigodoitemvenda($texto) { // Código curto do texto do item: o formulário de venda só vale se o espaço ainda tiver o mesmo item.

    return substr(hash("sha256", "venda|".(string)$texto), 0, 16);

}

function lojacidade() { // Cidade atual, que precisa ter loja (towns.tem_loja); senão volta para a cidade.

    $townrow = cidadedojogador("id,name,itemslist,consumiveislist");
    if (!cidadetemservico($townrow["id"], "loja")) { header("Location: index.php"); die(); }
    return $townrow;

}

function travarjogadorloja() { // Começa a transação e relê o jogador travado (SELECT ... FOR UPDATE).

    global $userrow, $link;
    mysqli_begin_transaction($link);
    $travado = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
    if (!$travado) { mysqli_rollback($link); header("Location: index.php"); die(); }
    $userrow = $travado;

}

function comprarconsumivel() { // POST codigo=ret|rep: compra um consumível da loja; junta numa pilha igual ou usa o primeiro espaço livre.

    global $userrow, $conteudo, $link;
    if (!isset($_POST["codigo"])) { header("Location: index.php?do=consumiveis"); die(); }
    if (!csrf_confere()) { $conteudo = mensagemloja(t("Formulário expirado. Tente novamente.")); vender("consumiveis"); }
    lojacidade();
    $loja = consumiveisdaloja();
    $codigo = pegartexto($_POST, "codigo");
    if (!isset($loja[$codigo])) { display(t("Tentativa de trapaça detectada.")."<br>", t("Error")); }
    list($nome, $preco, $valor) = $loja[$codigo];

    travarjogadorloja();
    if (intval($userrow["gold"]) < $preco) { mysqli_rollback($link); $conteudo = mensagemloja(t("Você não tem Ryou suficiente para comprar esse item.")); vender("consumiveis"); }
    $antes = $userrow;
    $espacos = mochilaguardar($userrow, $nome.",".$codigo.",".intval($valor).",X");
    if ($espacos === false) { mysqli_rollback($link); $conteudo = mensagemloja(t("Sua mochila está cheia. Libere um espaço para comprar esse item.")); vender("consumiveis"); }
    // O WHERE confere os espaços como estavam na leitura e o Ryou: dois cliques não compram pelo preço de um.
    list($set, $onde) = sqlmochila($userrow, $antes, $espacos);
    doquery("UPDATE {{table}} SET $set, gold=gold-$preco WHERE id='".intval($userrow["id"])."' $onde AND gold>=$preco LIMIT 1", "users");
    $ok = (mysqli_affected_rows($link) == 1);
    if ($ok) { mysqli_commit($link); } else { mysqli_rollback($link); }
    recarregarjogador();
    $conteudo = mensagemloja($ok ? tf("Você comprou %s por %s Ryou. Ele está na sua mochila.", t($nome), $preco) : t("O negócio não pôde ser realizado. Por favor tente novamente."));
    vender("consumiveis");

}

function pocoesdaloja($townrow) { // Poções vendidas na loja desta vila (towns.consumiveislist, ids de drops com precoloja > 0): id => linha de drops.

    $ids = array_filter(array_map('intval', explode(",", (string)(isset($townrow["consumiveislist"]) ? $townrow["consumiveislist"] : ""))));
    $lista = array();
    if (!count($ids)) { return $lista; }
    $q = doquery("SELECT * FROM {{table}} WHERE id IN (".implode(",", $ids).") AND precoloja>0 AND consumivel<>'' ORDER BY id", "drops");
    while ($row = mysqli_fetch_array($q, MYSQLI_ASSOC)) {
        $c = array_pad(explode(",", (string)$row["consumivel"]), 2, 0);
        if (!in_array($c[0], array("hp", "mp", "tp", "np", "ep", "bomba"), true) || intval($c[1]) <= 0) { continue; }
        linhadoitem($row["name"].",".$c[0].",".intval($c[1]).",1", $row); // guarda a linha no cache (pilha/tooltip sem outra query).
        $row["texto"] = $row["name"].",".$c[0].",".intval($c[1]).",1"; // mesmo texto do drop (fight.php): junta na mesma pilha.
        $lista[intval($row["id"])] = $row;
    }
    return $lista;

}

function comprarpocao() { // POST pocao=ID (drop da lista da vila), qtd=N: compra N poções; junta em pilhas iguais ou usa espaços livres.

    global $userrow, $conteudo, $link;
    if (!csrf_confere()) { $conteudo = mensagemloja(t("Formulário expirado. Tente novamente.")); vender("consumiveis"); }
    $townrow = lojacidade();
    $pocoes = pocoesdaloja($townrow);
    $id = pegarint($_POST, "pocao");
    if (!isset($pocoes[$id])) { display(t("Tentativa de trapaça detectada.")."<br>", t("Error")); }
    $qtd = max(1, min(20, pegarint($_POST, "qtd", 1)));
    $row = $pocoes[$id];
    $preco = intval($row["precoloja"]) * $qtd;

    travarjogadorloja();
    if (intval($userrow["gold"]) < $preco) { mysqli_rollback($link); $conteudo = mensagemloja(t("Você não tem Ryou suficiente para comprar esse item.")); vender("consumiveis"); }
    $antes = $userrow;
    $espacos = mochilaguardar($userrow, itemcomqtd($row["texto"], $qtd));
    if ($espacos === false) { mysqli_rollback($link); $conteudo = mensagemloja(t("Sua mochila está cheia. Libere um espaço para comprar esse item.")); vender("consumiveis"); }
    // Mesma gravação atômica dos outros consumíveis: confere os espaços e o Ryou no WHERE.
    list($set, $onde) = sqlmochila($userrow, $antes, $espacos);
    doquery("UPDATE {{table}} SET $set, gold=gold-$preco WHERE id='".intval($userrow["id"])."' $onde AND gold>=$preco LIMIT 1", "users");
    $ok = (mysqli_affected_rows($link) == 1);
    if ($ok) { mysqli_commit($link); } else { mysqli_rollback($link); }
    recarregarjogador();
    $nome = ($qtd > 1) ? $qtd."× ".t($row["name"]) : t($row["name"]);
    $conteudo = mensagemloja($ok ? tf("Você comprou %s por %s Ryou. Ele está na sua mochila.", $nome, $preco) : t("O negócio não pôde ser realizado. Por favor tente novamente."));
    vender("consumiveis");

}

function venderitem($remoto = false) { // POST qual=N, h=código do item, qtd=unidades (pilhas), csrf: vende do espaço N da mochila.
    // $remoto = true: venda ao comprador de pergaminhos distante (index.php?do=comprador), que paga mais.

    global $userrow, $conteudo, $link;
    $tela = $remoto ? "compradorremoto" : "vender";
    $falar = $remoto ? "mensagemcomprador" : "mensagemloja";
    if (!csrf_confere()) { $conteudo = $falar(t("Formulário expirado. Tente novamente.")); $tela(); }
    if ($remoto) { compradoraqui(); } else { lojacidade(); }
    $qual = pegarint($_POST, "qual");
    if ($qual < 1 || $qual > 8) { display(t("Tentativa de trapaça detectada.")."<br>", t("Error")); }
    $codigo = pegartexto($_POST, "h");
    $qtd = pegarint($_POST, "qtd", 1);

    // Jogador travado: o espaço tem que ter exatamente o item mostrado no formulário (cliques repetidos, outra aba
    // guardando o item no banco ou trocando com outro jogador não vendem o mesmo item duas vezes).
    travarjogadorloja();
    $texto = (string)$userrow["bp".$qual];
    $preco = precovendaitem($texto, $remoto);
    if ($texto == "None" || !hash_equals(codigodoitemvenda($texto), $codigo) || $preco <= 0) {
        mysqli_rollback($link); $conteudo = $falar(t("Esse item não está mais na sua mochila.")); $tela();
    }
    $tem = qtditem($texto);
    if ($qtd < 1 || $qtd > $tem) { mysqli_rollback($link); $conteudo = $falar(tf("Quantidade inválida: escolha de 1 a %s.", $tem)); $tela(); }
    $total = $preco * $qtd;
    if (!isset($_POST["confirmado"])) { // Confirmação (igual à da compra): só vende pelo botão "Sim".
        mysqli_rollback($link);
        $nomec = array_pad(explode(",", $texto), 1, "")[0];
        $vendc = ($qtd > 1) ? $qtd."× ".nomeitemhtml($nomec) : nomeitemhtml($nomec);
        $voltar = $remoto ? "index.php?do=comprador" : "index.php?do=vender";
        $conteudo = $falar(tf("Você está vendendo %s por <b>%s Ryou</b>. Está tudo certo?", $vendc, $total)."<br /><br /><center><form action=\"index.php?do=".($remoto ? "comprador" : "vender")."\" method=\"post\">".csrf_campo()
            ."<input type=\"hidden\" name=\"qual\" value=\"$qual\" /><input type=\"hidden\" name=\"h\" value=\"".htmlspecialchars($codigo, ENT_QUOTES, "UTF-8")."\" /><input type=\"hidden\" name=\"qtd\" value=\"$qtd\" /><input type=\"hidden\" name=\"confirmado\" value=\"1\" />"
            ."<div class=\"buttons\"><button type=\"submit\" class=\"positive btnjogo bj-confirmar\" name=\"vender\" value=\"1\"><img src=\"images/botao_sim.png\" width=\"16\" height=\"16\" alt=\"\"> ".t("Sim")."</button>\n"
            ."<button type=\"reset\" class=\"negative btnjogo bj-cancelar\" name=\"reset\" onclick=\"irpara('$voltar')\"><img src=\"images/botao_nao.png\" width=\"16\" height=\"16\" alt=\"\"> ".t("Não")."</button></div></form></center>");
        $tela();
    }
    $resto = itemcomqtd($texto, $tem - $qtd);
    doquery("UPDATE {{table}} SET bp$qual='".addslashes($resto)."', gold=LEAST(gold+$total, 16777215) WHERE id='".intval($userrow["id"])."' AND bp$qual='".addslashes($texto)."' LIMIT 1", "users");
    $ok = (mysqli_affected_rows($link) == 1);
    if ($ok) { mysqli_commit($link); } else { mysqli_rollback($link); }
    $nome = array_pad(explode(",", $texto), 1, "")[0];
    $ganho = min($total, max(0, 16777215 - intval($userrow["gold"])));
    recarregarjogador();
    $vendido = ($qtd > 1) ? $qtd."× ".nomeitemhtml($nome) : nomeitemhtml($nome);
    $conteudo = $falar($ok ? tf("Você vendeu %s por %s Ryou.", $vendido, $ganho).(($ganho < $total) ? " ".t("Você atingiu o limite de Ryou.") : "") : t("O negócio não pôde ser realizado. Por favor tente novamente."));
    $tela();

}

function linhavenda($i, $texto, $preco, $csrf, $acao, $bgcolor) { // Linha da tabela de venda: ícone (com a pilha), nome, preço e botão (com a quantidade, se for pilha).

    $item = array_pad(explode(",", $texto), 4, "X");
    $q = qtditem($texto);
    $icone = is_numeric($item[1]) ? iconeitem($item[1], intval($item[2]) > 3, $item[0]) : iconeconsumivel($item[1], $item[2], $item[0]);
    $explic = conteudoexplic($item[1], $item[2], "venda".$i, ($item[3] == "X" ? "*" : $item[3]), $item[0], $q);
    $campoqtd = ($q > 1) ? "<input type=\"number\" name=\"qtd\" value=\"1\" min=\"1\" max=\"$q\" style=\"width:42px\" title=\"".t("Quantidade")."\" /> " : "<input type=\"hidden\" name=\"qtd\" value=\"1\" />";
    if ($preco > 0) { $botao = "<form action=\"index.php?do=$acao\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"qual\" value=\"$i\" /><input type=\"hidden\" name=\"h\" value=\"".codigodoitemvenda($texto)."\" />$campoqtd<button type=\"submit\" name=\"vender\" value=\"".t("Vender")."\" class=\"btnjogo bj-neutro\"><img src=\"images/botao_vender.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Vender")."</button></form>"; }
    else { $botao = "<span class=\"light\">".(itemnaovendavel($texto) ? t("Não vendável") : t("Não compro"))."</span>"; }
    $img = iconecomqtd("<img src=\"$icone\" width=\"34\" height=\"34\" style=\"display:block;border:2px #e4d094 solid\" id=\"venda$i\" onmouseover=\"$explic\" onmouseout=\"fecharexplic();\" alt=\"\" />", $texto);
    return "<tr bgcolor=\"$bgcolor\"><td width=\"40\">$img</td><td>".tf("Mochila %s:", $i)." <b>".nomeitemhtml($item[0])."</b>".($q > 1 ? " ×".$q : "")."</td><td>".($preco > 0 ? tf("Pago: <b>%s Ryou</b>", iconeryou().$preco).($q > 1 ? " ".t("(cada)") : "") : "-")."</td><td>$botao</td></tr>";

}

function vender($modo = "vender") { // Telas "Consumíveis" ($modo = "consumiveis") e "Vender": compra dos consumíveis, venda de itens da mochila e comprador de pergaminhos de jutsu.

    global $userrow, $conteudo, $controlrow;
    // As ações mostram esta mesma tela no fim (com a mensagem): o campo é retirado do $_POST antes, para não repetir a ação.
    if (isset($_POST["vender"])) { unset($_POST["vender"]); venderitem(); }
    if (isset($_POST["comprar"])) { unset($_POST["comprar"]); comprarconsumivel(); }
    if (isset($_POST["comprarpocao"])) { unset($_POST["comprarpocao"]); comprarpocao(); }
    $townrow = lojacidade();
    $csrf = csrf_campo();
    $compradorvila = !isset($controlrow["compradorvila"]) || intval($controlrow["compradorvila"]) != 0;
    if (!isset($conteudo) || $conteudo == "") { $conteudo = mensagemloja($compradorvila ? t("Também vendo itens úteis para a viagem e compro qualquer coisa da sua mochila. Pergaminhos de jutsu ficam com o comprador de pergaminhos, logo abaixo.") : t("Também vendo itens úteis para a viagem e compro quase tudo da sua mochila. Pergaminhos de jutsu, só com o eremita que compra pergaminhos, longe daqui.")); }

    $page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/shop.gif\" /></center></td></tr></table>".$conteudo."<center>";
    $page .= abasloja($modo);

    if ($modo == "consumiveis") {
    // 1) Consumíveis à venda.
    $page .= "<table width=\"85%\" style=\"border:1px #000000 solid\" cellspacing=\"0\" cellpadding=\"2\"><tr bgcolor=\"#452202\"><td colspan=\"4\"><font color=white>".t("Consumíveis")."</font></td></tr>";
    $fundo = 0;
    foreach (consumiveisdaloja() as $codigo => $c) {
        $fundo++; $bgcolor = ($fundo % 2 == 0) ? "#E4D094" : "#FFF1C7";
        $explic = conteudoexplic($codigo, $c[2], "cons".$codigo, "*", $c[0]);
        $page .= "<tr bgcolor=\"$bgcolor\"><td width=\"40\"><img src=\"".iconeconsumivel($codigo, $c[2])."\" width=\"34\" height=\"34\" style=\"display:block;border:2px #e4d094 solid\" id=\"cons$codigo\" onmouseover=\"$explic\" onmouseout=\"fecharexplic();\" alt=\"\" /></td><td><b>".t($c[0])."</b><br /><span class=\"small\">".($codigo == "ret" ? t("Volta para a sua vila natal (fora de batalhas, duelos e PvP).")." ".tf("Custo: %s TP", custotpretorno()) : tf("Nenhum inimigo aleatório por %s passos ou %s minutos.", INCENSO_PASSOS, INCENSO_MINUTOS))."</span></td><td>".tf("Preço: <b>%s Ryou</b>", iconeryou().$c[1])."</td><td><form action=\"index.php?do=consumiveis\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"codigo\" value=\"$codigo\" /><button type=\"submit\" name=\"comprar\" value=\"".t("Comprar")."\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_comprar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Comprar")."</button></form></td></tr>";
    }
    // Poções pequenas e outras poções da lista desta vila (towns.consumiveislist).
    foreach (pocoesdaloja($townrow) as $id => $row) {
        $fundo++; $bgcolor = ($fundo % 2 == 0) ? "#E4D094" : "#FFF1C7";
        $c = explode(",", $row["consumivel"]);
        $explic = conteudoexplic($c[0], $c[1], "pocao".$id, "1", $row["name"]);
        $page .= "<tr bgcolor=\"$bgcolor\"><td width=\"40\"><img src=\"".iconeconsumivel($c[0], $c[1], $row["name"])."\" width=\"34\" height=\"34\" style=\"display:block;border:2px #e4d094 solid\" id=\"pocao$id\" onmouseover=\"$explic\" onmouseout=\"fecharexplic();\" alt=\"\" /></td><td><b>".t($row["name"])."</b><br /><span class=\"small\">".(($c[0] == "bomba") ? htmlspecialchars(itembatalharesumo($row), ENT_QUOTES, "UTF-8").". ".t("Arremesse durante uma batalha ou duelo (gasta o turno).") : tf("Recupera %s de %s. Use pela mochila.", intval($c[1]), ($c[0] == "mp") ? "CH" : strtoupper($c[0])))."</span></td><td>".tf("Preço: <b>%s Ryou</b>", iconeryou().intval($row["precoloja"]))."</td><td><form action=\"index.php?do=consumiveis\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"pocao\" value=\"$id\" /><input type=\"number\" name=\"qtd\" value=\"1\" min=\"1\" max=\"20\" style=\"width:42px\" title=\"".t("Quantidade")."\" /> <button type=\"submit\" name=\"comprarpocao\" value=\"".t("Comprar")."\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_comprar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Comprar")."</button></form></td></tr>";
    }
    $page .= "</table><br />";
    } else {

    // 2) Venda dos itens da mochila e 3) comprador de pergaminhos de jutsu.
    $mult = isset($controlrow["vendamochila"]) ? intval($controlrow["vendamochila"]) : 100;
    $linhas = array("item" => "", "jutsu" => "");
    $fundo = array("item" => 0, "jutsu" => 0);
    for ($i = 1; $i <= 8; $i++) {
        $texto = (string)$userrow["bp".$i];
        if ($texto == "None") { continue; }
        $item = array_pad(explode(",", $texto), 4, "X");
        $secao = ($item[1] == "jutsu") ? "jutsu" : "item";
        $fundo[$secao]++; $bgcolor = ($fundo[$secao] % 2 == 0) ? "#E4D094" : "#FFF1C7";
        $linhas[$secao] .= linhavenda($i, $texto, precovendaitem($texto), $csrf, "vender", $bgcolor);
    }
    $page .= "<table width=\"85%\" style=\"border:1px #000000 solid\" cellspacing=\"0\" cellpadding=\"2\"><tr bgcolor=\"#452202\"><td colspan=\"4\"><font color=white>".t("Vender itens da mochila")."</font></td></tr>";
    $page .= ($linhas["item"] != "") ? $linhas["item"] : "<tr bgcolor=\"#FFF1C7\"><td colspan=\"4\">".t("Nenhum item para vender na mochila.")."</td></tr>";
    $page .= "<tr bgcolor=\"#E4D094\"><td colspan=\"4\" class=\"small\">".tf("Preço: equipamento = 1/4 do preço da loja (+25 por refino); drop = 10 + 6 × nível do drop; poção = metade do que recupera. Variantes raras valem 3×. Multiplicador da loja: %s%%.", $mult)." ".t("Pilhas: escolha quantas unidades vender.")."</td></tr></table><br />";
    $page .= "<table width=\"85%\" style=\"border:1px #000000 solid\" cellspacing=\"0\" cellpadding=\"2\"><tr bgcolor=\"#452202\"><td colspan=\"4\"><font color=white>".t("Comprador de pergaminhos de jutsu")."</font></td></tr>";
    $page .= ($linhas["jutsu"] != "") ? $linhas["jutsu"] : "<tr bgcolor=\"#FFF1C7\"><td colspan=\"4\">".t("Você não tem pergaminhos de jutsu na mochila.")."</td></tr>";
    $remoto = compradorremotolocal();
    $dica = $remoto ? " ".tf("O eremita %s, em %s, paga %s%% desse preço.", $remoto["nome"], mx_coordenada(intval($remoto["latitude"]), intval($remoto["longitude"])), isset($controlrow["compradorremoto"]) ? intval($controlrow["compradorremoto"]) : 200) : "";
    $page .= "<tr bgcolor=\"#E4D094\"><td colspan=\"4\" class=\"small\">".($compradorvila ? tf("Preço: 50 + 20 × nível mínimo do jutsu. Multiplicador da loja: %s%%.", $mult) : t("O comprador de pergaminhos desta vila está fechado."))."$dica</td></tr></table><br />";
    }
    $page .= t("Se você mudou de ideia, pode retornar à <a href=\"index.php\">cidade</a>.")."</center>";

    display($page, ($modo == "consumiveis") ? t("Consumíveis") : t("Vender"));

}


/* ------------------------------------------------------------------------
   Reparo de equipamentos (index.php?do=reparar), na loja de qualquer vila (docs/DURABILIDADE.md).
   Restaura a durabilidade dos itens finitos equipados e da mochila até a máxima atual (items/drops.durabilidade).
   Custo de cada item = ceil(valor × control.reparo_pct/100 × falta/máxima), no mínimo 1 Ryou:
     valor = preço da loja (arma/colete/bandana) ou 150 × nível do drop; variante rara vale 3×.
   Tudo numa transação com o jogador travado e um UPDATE condicional (durabilidade, mochila e Ryou como lidos).
   ------------------------------------------------------------------------ */

function valorreparo($tipo, $id, $nome) { // Valor de referência do item para o reparo (antes do control.reparo_pct).

    $tipo = intval($tipo); $id = intval($id);
    if ($tipo >= 1 && $tipo <= 3) {
        $row = mysqli_fetch_array(doquery("SELECT buycost FROM {{table}} WHERE id='$id' LIMIT 1", "items"));
        $valor = $row ? intval($row["buycost"]) : 0;
    } elseif ($tipo >= 4 && $tipo <= 7) { // 4-6 = tipos de drop; 7 = posição do acessório 4.
        $row = mysqli_fetch_array(doquery("SELECT mlevel FROM {{table}} WHERE id='$id' LIMIT 1", "drops"));
        $valor = $row ? 150 * intval($row["mlevel"]) : 0;
    } else { return 0; }
    if (variantedoitem((string)$nome) != "") { $valor *= 3; }
    return $valor;

}

function custoreparo($atual, $max, $valor) { // Ryou para levar a durabilidade $atual até $max (0 = nada a reparar).

    global $controlrow;
    if (!is_numeric($atual) || $max <= 0 || intval($atual) >= $max) { return 0; }
    $pct = isset($controlrow["reparo_pct"]) ? max(0, intval($controlrow["reparo_pct"])) : 30;
    return max(1, (int)ceil($valor * $pct / 100 * ($max - intval($atual)) / $max));

}

function itensparareparar($row) { // Itens com desgaste: lista de array(lugar, nome, atual, max, custo). lugar: 1-7 = equipado, "bp1".."bp8" = mochila.

    $lista = array();
    $dur = lerdurabilidade($row["durabilidade"]);
    $slots = slotsequipamento();
    foreach ($slots as $h => $s) {
        if ($dur[$h] === "X" || (string)$row[$s."name"] == "None") { continue; }
        $max = durabilidademaxima($h, $row[$s."id"]);
        $custo = custoreparo($dur[$h], $max, valorreparo($h, $row[$s."id"], $row[$s."name"]));
        if ($custo > 0) { $lista[] = array($h, $row[$s."name"], intval($dur[$h]), $max, $custo); }
    }
    for ($i = 1; $i <= 8; $i++) {
        $p = array_pad(explode(",", (string)$row["bp".$i]), 4, "X");
        if ($row["bp".$i] == "None" || !is_numeric($p[1]) || !is_numeric($p[3]) || intval($p[2]) < 1 || intval($p[2]) > 6) { continue; }
        $max = durabilidademaxima(intval($p[2]), $p[1]);
        $custo = custoreparo($p[3], $max, valorreparo(intval($p[2]), $p[1], $p[0]));
        if ($custo > 0) { $lista[] = array("bp".$i, $p[0], intval($p[3]), $max, $custo); }
    }
    return $lista;

}

function reparar() { // Tela do reparo e o POST "reparar" (repara tudo de uma vez).

    global $userrow, $conteudo, $link;
    lojacidade();

    if (isset($_POST["reparar"])) {
        unset($_POST["reparar"]);
        if (!csrf_confere()) { $conteudo = mensagemloja(t("Formulário expirado. Tente novamente.")); }
        else {
            travarjogadorloja();
            $lista = itensparareparar($userrow);
            $total = 0; foreach ($lista as $l) { $total += $l[4]; }
            $esperado = pegarint($_POST, "total");
            if (!count($lista)) { mysqli_rollback($link); $conteudo = mensagemloja(t("Nenhum equipamento precisa de reparo.")); }
            elseif ($total != $esperado) { mysqli_rollback($link); $conteudo = mensagemloja(t("Os seus itens mudaram. Confira o novo preço e tente de novo.")); }
            elseif (intval($userrow["gold"]) < $total) { mysqli_rollback($link); $conteudo = mensagemloja(t("Você não tem Ryou suficiente para o reparo.")); }
            else {
                $dur = lerdurabilidade($userrow["durabilidade"]);
                $set = array(); $onde = " AND durabilidade='".addslashes($userrow["durabilidade"])."'";
                foreach ($lista as $l) {
                    if (is_int($l[0])) { $dur[$l[0]] = $l[3]; continue; }
                    $p = explode(",", (string)$userrow[$l[0]]);
                    $p[3] = $l[3];
                    $set[] = $l[0]."='".addslashes(implode(",", $p))."'";
                    $onde .= " AND ".$l[0]."='".addslashes($userrow[$l[0]])."'";
                }
                $set[] = "durabilidade='".addslashes(juntardurabilidade($dur))."'";
                doquery("UPDATE {{table}} SET ".implode(",", $set).", gold=gold-$total WHERE id='".intval($userrow["id"])."'$onde AND gold>=$total LIMIT 1", "users");
                $ok = (mysqli_affected_rows($link) == 1);
                if ($ok) { mysqli_commit($link); } else { mysqli_rollback($link); }
                recarregarjogador();
                $conteudo = mensagemloja($ok ? tf("Pronto! %s item(ns) reparado(s) por %s Ryou.", count($lista), $total) : t("O negócio não pôde ser realizado. Por favor tente novamente."));
            }
        }
    }

    if (!isset($conteudo) || $conteudo == "") { $conteudo = mensagemloja(t("Armas, coletes, bandanas e drops fortes se desgastam a cada vitória. Eu deixo tudo como novo, cobrando só pelo que falta.")); }
    $page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/shop.gif\" /></center></td></tr></table>".$conteudo."<center>";
    $page .= abasloja("reparar");
    $page .= "<table width=\"85%\" style=\"border:1px #000000 solid\" cellspacing=\"0\" cellpadding=\"2\" id=\"tabelareparo\"><tr bgcolor=\"#452202\"><td><font color=white>".t("Item")."</font></td><td><font color=white>".t("Durabilidade")."</font></td><td><font color=white>".t("Preço")."</font></td></tr>";
    $lista = itensparareparar($userrow);
    $total = 0; $fundo = 0;
    foreach ($lista as $l) {
        $fundo++; $bgcolor = ($fundo % 2 == 0) ? "#E4D094" : "#FFF1C7";
        $onde = is_int($l[0]) ? t("Equipado") : tf("Mochila %s:", substr($l[0], 2));
        $page .= "<tr bgcolor=\"$bgcolor\"><td>".$onde." <b>".nomeitemhtml($l[1])."</b></td><td>".$l[2]."/".$l[3]."</td><td>".tf("%s Ryou", $l[4])."</td></tr>";
        $total += $l[4];
    }
    if (!count($lista)) { $page .= "<tr bgcolor=\"#FFF1C7\"><td colspan=\"3\">".t("Nenhum equipamento precisa de reparo.")."</td></tr>"; }
    else {
        $page .= "<tr bgcolor=\"#E4D094\"><td colspan=\"2\"><b>".t("Total")."</b></td><td><b>".tf("%s Ryou", iconeryou().$total)."</b></td></tr>";
        $page .= "<tr bgcolor=\"#FFF1C7\"><td colspan=\"3\" align=\"center\"><form action=\"index.php?do=reparar\" method=\"post\" style=\"margin:0\">".csrf_campo()."<input type=\"hidden\" name=\"total\" value=\"$total\" /><button type=\"submit\" name=\"reparar\" value=\"".t("Reparar tudo")."\" class=\"btnjogo bj-confirmar\"".(intval($userrow["gold"]) < $total ? " disabled=\"disabled\"" : "")."><img src=\"images/botao_sim.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Reparar tudo")."</button></form></td></tr>";
    }
    $page .= "</table><br /><span class=\"small\">".t("Itens com durabilidade infinita (INF) nunca precisam de reparo.")."</span><br /><br />";
    $page .= t("Se você mudou de ideia, pode retornar à <a href=\"index.php\">cidade</a>.")."</center>";
    display($page, t("Reparar equipamentos"));

}


/* ------------------------------------------------------------------------
   Comprador de pergaminhos distante (index.php?do=comprador).
   É um NPC da tabela npcs com papel = "comprador_pergaminhos" (editável em admin_missoes.php?do=npcs), num canto
   remoto do mapa. Conversar com ele (falapersonagens.php) leva a esta tela, que só aceita pergaminhos de jutsu e
   paga control.compradorremoto % do preço da vila (padrão 200%). A venda usa venderitem(true): mesma trava e
   mesmas conferências da loja.
   ------------------------------------------------------------------------ */

function compradorremotolocal() { // Primeiro NPC ativo com o papel de comprador de pergaminhos (ou false).

    static $npc = null;
    if ($npc === null) {
        $npc = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE papel='comprador_pergaminhos' AND ativo='1' ORDER BY id LIMIT 1", "npcs"));
        if (!$npc) { $npc = false; }
    }
    return $npc;

}

function compradoraqui() { // O jogador está na casa do comprador distante e fora de batalha? Devolve o NPC; senão volta ao mapa.

    global $userrow;
    if ($userrow["currentaction"] == "Fighting") { header("Location: index.php?do=fight"); die(); }
    $npc = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE papel='comprador_pergaminhos' AND ativo='1' AND latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "npcs"));
    if (!$npc) { header("Location: index.php?conteudo=".rawurlencode(t("Não há nenhum comprador de pergaminhos aqui."))); die(); }
    return $npc;

}

function mensagemcomprador($texto) { // Fala do comprador distante (balão de NPC com o avatar dele).

    $npc = compradoraqui();
    return personagemgeral($texto, $npc["avatar"], $npc["nome"], 'sem<br>');

}

function compradorremoto() { // Tela do comprador distante: lista os pergaminhos de jutsu da mochila com o preço maior.

    global $userrow, $conteudo, $controlrow;
    if (isset($_POST["vender"])) { unset($_POST["vender"]); venderitem(true); }
    $npc = compradoraqui();
    $csrf = csrf_campo();
    $pct = isset($controlrow["compradorremoto"]) ? intval($controlrow["compradorremoto"]) : 200;
    if (!isset($conteudo) || $conteudo == "") { $conteudo = mensagemcomprador(tf("Poucos chegam até aqui, %s. Pergaminhos de jutsu valem ouro para quem os estuda longe das vilas: pago %s%% do que o comprador da vila pagaria.", $userrow["charname"], $pct)); }
    $linhas = ""; $fundo = 0;
    for ($i = 1; $i <= 8; $i++) {
        $texto = (string)$userrow["bp".$i];
        if ($texto == "None" || array_pad(explode(",", $texto), 2, "")[1] != "jutsu") { continue; }
        $fundo++;
        $linhas .= linhavenda($i, $texto, precovendaitem($texto, true), $csrf, "comprador", ($fundo % 2 == 0) ? "#E4D094" : "#FFF1C7");
    }
    $page = $conteudo."<center><table width=\"85%\" style=\"border:1px #000000 solid\" cellspacing=\"0\" cellpadding=\"2\"><tr bgcolor=\"#452202\"><td colspan=\"4\"><font color=white>".tf("Comprador de pergaminhos: %s", $npc["nome"])."</font></td></tr>";
    $page .= ($linhas != "") ? $linhas : "<tr bgcolor=\"#FFF1C7\"><td colspan=\"4\">".t("Você não tem pergaminhos de jutsu na mochila.")."</td></tr>";
    $page .= "<tr bgcolor=\"#E4D094\"><td colspan=\"4\" class=\"small\">".tf("Preço: (50 + 20 × nível mínimo do jutsu) × %s%%, depois o multiplicador da loja (%s%%).", $pct, isset($controlrow["vendamochila"]) ? intval($controlrow["vendamochila"]) : 100)."</td></tr></table><br />";
    $page .= "<a href=\"index.php\">".t("Voltar ao mapa")."</a></center>";
    display($page, t("Comprador de pergaminhos"));

}

if (!function_exists('mensagemmapas')){
function mensagemmapas($texto) { // Fala do vendedor de mapas Hanashi.

    return personagemgeral($texto, "personagem2", "Hanashi");

}}

if (!function_exists('mapaavenda')){
function mapaavenda($id) { // Cidade cujo mapa pode ser comprado aqui (existe, não é secreta e o jogador ainda não tem). Senão volta para a lista.

    global $userrow;

    $townrow = cidadedojogador("id");
    if (!cidadetemlojademapas($townrow["id"])) { header("Location: index.php"); die(); }
    $id = intval($id);
    $townquery = doquery("SELECT id,name,mapprice FROM {{table}} WHERE id='$id' LIMIT 1", "towns");
    $townrow = mysqli_fetch_array($townquery);
    if (!$townrow || cidadesecreta($id) || in_array($id, listadeids($userrow["towns"]))) { header("Location: index.php?do=maps"); die(); }
    $townrow["mapprice"] = max(0, intval($townrow["mapprice"]));
    return $townrow;

}}

function maps() { // Loja de mapas: lista as cidades. Comprar o mapa libera a viagem para ela pelo menu "Viajar Para" (gastando TP).

    global $userrow, $conteudo;

    $townrow = cidadedojogador("id");
    if (!cidadetemlojademapas($townrow["id"])) { header("Location: index.php"); die(); }
    $mappedtowns = listadeids($userrow["towns"]);

    if (!isset($conteudo) || $conteudo == "") { $conteudo = mensagemmapas(t("Ao comprar um mapa, você poderá viajar para essa cidade pelo menu <b>Viajar</b>. A viagem custa TP (Pontos de Viagem) conforme a distância e Ryou; para vilas que não são a sua vila natal, também gasta um <b>Selo de Viagem</b>. Para voltar para casa, o Pergaminho de Retorno sai bem mais barato.")); }

    $page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/comprarmapas.gif\" /></center></td></tr></table>".$conteudo;
    // Cartões das vilas: ícone, nome, distância e custo da viagem daqui; comprar (com confirmação) ou "Mapa comprado".
    $h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
    $page .= "<style>.mapas{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:8px;max-width:620px;margin:8px auto 12px;text-align:left}"
        .".mapa{display:flex;gap:8px;align-items:center;background:#fff1c7;border:2px solid #8b5a2b;border-radius:6px;padding:7px;box-shadow:0 2px 0 #c9a46a}"
        .".mapa.tem{background:#efe6cc;border-color:#b9a27a;box-shadow:none;opacity:.85}.mapa img.vila{width:34px;height:34px;flex:none;image-rendering:pixelated}"
        .".mapa .info{flex:1;min-width:0;font-size:12px;color:#5a3a1a;line-height:1.35}.mapa .info b{display:block;font-size:14px;color:#3d2410}"
        .".mapa .acao{flex:none;text-align:center}.mapa .acao .preco{display:block;font-weight:bold;color:#3d2410;margin-bottom:3px;white-space:nowrap}"
        .".mapa .selo{display:inline-block;padding:3px 7px;border-radius:10px;background:#5b8a3a;color:#fff;font-size:11px;font-weight:bold}"
        .".mapa .btnjogo{padding:4px 8px}</style>";
    $page .= "<center>".t("Ao comprar o mapa, a vila entra no menu <b>Viajar</b>.")."</center><div class=\"mapas\">";
    $townquery = doquery("SELECT * FROM {{table}} ORDER BY id", "towns");
    while ($townrow = mysqli_fetch_array($townquery)) {
        $mapped = in_array(intval($townrow["id"]), $mappedtowns);
        if ($mapped == false && cidadesecreta($townrow)) { continue; } // Cidades secretas só aparecem depois de descobertas.
        $lat = ($townrow["latitude"] >= 0) ? $townrow["latitude"]."N" : ($townrow["latitude"] * -1)."S";
        $lon = ($townrow["longitude"] >= 0) ? $townrow["longitude"]."E" : ($townrow["longitude"] * -1)."W";
        $aqui = (intval($townrow["latitude"]) == intval($userrow["latitude"]) && intval($townrow["longitude"]) == intval($userrow["longitude"]));
        $cv = custoviagem($userrow, $townrow);
        $icone = file_exists("images/24/cidade_".intval($townrow["id"]).".gif") ? "images/24/cidade_".intval($townrow["id"]).".gif" : "images/24/cidade_1.gif";
        $detalhe = $aqui ? t("Você está aqui.") : tf("Viagem daqui: %s TP", intval($cv["tp"])).(intval($cv["ryou"]) > 0 ? " · ".tf("%s Ryou", intval($cv["ryou"])) : "");
        $page .= "<div class=\"mapa".($mapped ? " tem" : "")."\"><img class=\"vila\" src=\"$icone\" alt=\"\"><div class=\"info\"><b>".$h(t($townrow["name"]))."</b>"
            .($mapped ? $h($lat.", ".$lon)."<br />".$h($detalhe) : t("Localização desconhecida"))."</div><div class=\"acao\">";
        if ($mapped) {
            $page .= "<span class=\"selo\">&#10003; ".t("Comprado")."</span>";
        } else {
            $falta = intval($userrow["gold"]) < intval($townrow["mapprice"]);
            $page .= "<span class=\"preco\">".iconeryou().intval($townrow["mapprice"])."</span><a class=\"btnjogo bj-confirmar\" href=\"index.php?do=maps2:".intval($townrow["id"])."\"".($falta ? " style=\"opacity:.6\" title=\"".$h(t("Ryou insuficiente"))."\"" : "")."><img src=\"images/botao_comprar.png\" width=\"16\" height=\"16\" alt=\"\"> ".t("Comprar")."</a>";
        }
        $page .= "</div></div>";
    }
    $page .= "</div><center>".t("Se você mudou de ideia, você pode retornar à <a href=\"index.php\">cidade</a>.")."</center>";

    display($page, t("Comprar Mapas"));

}

function maps2($id) { // Confirmação da compra de um mapa.

    global $userrow, $conteudo;

    $townrow = mapaavenda($id);

    if ($userrow["gold"] < $townrow["mapprice"]) {
        $conteudo = mensagemmapas(t("Você não tem Ryou suficiente para comprar esse mapa.")."<br /><br />".t("Você pode retornar à <a href=\"index.php\">cidade</a>, <a href=\"index.php?do=maps\">shop</a>, ou usar os botões de direção para continuar explorando."));
        maps();
    }

    $aviso = isset($GLOBALS["avisoexpirado"]) ? "<b style=\"color:#a01010\">".$GLOBALS["avisoexpirado"]."</b><br /><br />" : "";
    $conteudo = mensagemmapas($aviso.tf("Você está comprando o mapa de %s. Tudo certo?", t($townrow["name"]))."<br /><br /><form action=\"index.php?do=maps3:".$townrow["id"]."\" method=\"post\">".csrf_campo()."<div class=\"buttons\"><button type=\"submit\" class=\"positive btnjogo bj-confirmar\" name=\"submit\"><img src=\"images/botao_sim.png\" width=\"16\" height=\"16\" alt=\"\"> ".t("Sim")."</button>
<button type=\"reset\" class=\"negative btnjogo bj-cancelar\" name=\"reset\" onclick=\"irpara('index.php?do=maps')\"><img src=\"images/botao_nao.png\" width=\"16\" height=\"16\" alt=\"\"> ".t("Não")."</button>
</div></form>");
    maps();

}

function maps3($id) { // Compra o mapa: a cidade entra na lista users.towns ("0,1,4,...") e o preço é descontado.

    global $userrow, $conteudo;

    if (isset($_POST["cancel"])) { header("Location: index.php"); die(); }
    if (!isset($_POST["submit"])) { maps2($id); } // A compra só acontece pelo botão "Sim" (POST) da confirmação.
    if (!csrf_confere()) { $GLOBALS["avisoexpirado"] = t("Formulário expirado. Tente novamente."); maps2($id); } // CSRF: mostra a confirmação de novo, com o aviso

    $townrow = mapaavenda($id);

    if ($userrow["gold"] < $townrow["mapprice"]) {
        $conteudo = mensagemmapas(t("Você não tem dinheiro suficiente para comprar esse mapa.")."<br /><br />".t("Você pode retornar à <a href=\"index.php\">cidade</a>, <a href=\"index.php?do=maps\">shop</a>, ou usar os botões de direção para continuar explorando."));
        maps();
    }

    $mappedtowns = $userrow["towns"].",".intval($townrow["id"]);
    if (strlen($mappedtowns) > 255) { // Limite da coluna users.towns (varchar 255).
        $conteudo = mensagemmapas(t("Você não consegue carregar mais mapas."));
        maps();
    }

    // O "gold>=" e o "towns=" no WHERE impedem pagar duas vezes com duas abas abertas.
    doquery("UPDATE {{table}} SET towns='".addslashes($mappedtowns)."',gold=gold-".$townrow["mapprice"]." WHERE id='".intval($userrow["id"])."' AND gold>=".$townrow["mapprice"]." AND towns='".addslashes($userrow["towns"])."' LIMIT 1", "users");

    recarregarjogador();
    $conteudo = mensagemmapas(t("Obrigado por comprar esse mapa.")."<br /><br />".t("Você pode retornar à <a href=\"index.php\">cidade</a>, <a href=\"index.php?do=maps\">shop</a>, ou usar os botões de direção para continuar explorando."));
    maps();

}

function custoviagemryou($townrow) { // Taxa em Ryou do "Viajar Para" a partir da posição atual (custoviagem() em funcoesinclusas.php).

    global $userrow;
    include_once('funcoesinclusas.php');
    $c = custoviagem($userrow, $townrow);
    return $c["ryou"];

}

function textocustoviagem($c) { // "33 TP + 600 Ryou + 1 Selo de Viagem" (já traduzido).

    $t = tf("%s TP + %s Ryou", $c["tp"], $c["ryou"]);
    if ($c["selos"] > 0) { $t .= " + ".tf("%s× %s", $c["selos"], t("Selo de Viagem")); }
    return $t;

}

function travelto($id, $usepoints=true) { // Leva o jogador até uma cidade: pelo menu "Viajar Para" (gasta TP, Ryou e, fora da vila natal, Selo de Viagem; exige o mapa) ou andando até ela ($usepoints=false).

    global $userrow, $link;

    if ($userrow["currentaction"] == "Fighting") { header("Location: index.php?do=fight"); die(); }

    $id = intval($id);
    $townquery = doquery("SELECT id,name,travelpoints,latitude,longitude FROM {{table}} WHERE id='$id' LIMIT 1", "towns");
    $townrow = mysqli_fetch_array($townquery);
    $mapped = listadeids($userrow["towns"]);

    if ($usepoints == true && (!$townrow || !in_array($id, $mapped))) { display(t("Tentativa de trapaça detectada.")."<br>", t("Error")); }
    if (!$townrow) { header("Location: index.php"); die(); }

    $bloqueio = bloqueiovila($userrow); // caveira exposta: vilas fechadas (lib.php; a morte não passa por aqui).
    if ($bloqueio != "" && !(($userrow["latitude"] == $townrow["latitude"]) && ($userrow["longitude"] == $townrow["longitude"]))) { voltarcommensagem($bloqueio); }
    if (($userrow["latitude"] == $townrow["latitude"]) && ($userrow["longitude"] == $townrow["longitude"])) { display(t("Você já está nessa cidade.")."<br>", t("Viajar Para")); }

    $custo = 0; $ryou = 0; $selos = 0;
    $sets = ""; $onde = "";
    if ($usepoints == true) {
        include_once('funcoesinclusas.php');
        // Tela de viagem: mostra o custo e pede confirmação (o link de confirmação leva confirmar=1 e o token CSRF).
        if (!isset($_GET["confirmar"])) {
            $c = custoviagem($userrow, $townrow);
            $tem = contarselos($userrow);
            $page = "<center><b>".tf("Viajar para %s", t($townrow["name"]))."</b><br /><br /><table cellpadding=\"3\" style=\"border:1px #000000 solid\">";
            $page .= "<tr bgcolor=\"#FFF1C7\"><td>".t("Distância")."</td><td>".tf("%s casas", $c["distancia"])."</td></tr>";
            $page .= "<tr bgcolor=\"#E4D094\"><td>".t("Pontos de viagem (TP):")."</td><td><b>".$c["tp"]."</b> (".tf("você tem %s", intval($userrow["currenttp"])).")</td></tr>";
            $page .= "<tr bgcolor=\"#FFF1C7\"><td>Ryou</td><td>".iconeryou()."<b>".$c["ryou"]."</b> (".tf("você tem %s", intval($userrow["gold"])).")</td></tr>";
            if ($c["selos"] > 0) { $page .= "<tr bgcolor=\"#E4D094\"><td>".t("Selo de Viagem")."</td><td><b>".$c["selos"]."</b> (".tf("você tem %s", $tem).")</td></tr>"; }
            $page .= "</table><br /><span class=\"small\">".($c["natal"] ? t("Esta é a sua vila natal: o Pergaminho de Retorno faz a mesma viagem muito mais barato.") : t("Viajar direto para uma vila que não é a sua vila natal gasta um Selo de Viagem (cai de inimigos de nível médio, sai de missões repetíveis e da Alquimia)."))."</span><br /><br />";
            $falta = array();
            if (intval($userrow["currenttp"]) < $c["tp"]) { $falta[] = t("TP"); }
            if (intval($userrow["gold"]) < $c["ryou"]) { $falta[] = "Ryou"; }
            if ($tem < $c["selos"]) { $falta[] = t("Selo de Viagem"); }
            if (count($falta)) { $page .= "<span style=\"color:#a00000\">".tf("Falta: %s.", implode(", ", $falta))."</span><br /><br /><a href=\"index.php\" class=\"btnjogo bj-neutro\" style=\"text-decoration:none\">".t("Voltar")."</a>"; }
            else { $page .= "<a href=\"index.php?do=gotown:".$id."&amp;confirmar=1&amp;csrf=".csrf_token()."\" class=\"btnjogo bj-confirmar\" style=\"text-decoration:none\">".t("Viajar")."</a> <a href=\"index.php\" class=\"btnjogo bj-cancelar\" style=\"text-decoration:none\">".t("Cancelar")."</a>"; }
            $page .= "</center>";
            display($page, t("Viajar Para"));
        }
        // Cobrança: jogador travado (FOR UPDATE), custo recalculado com os dados travados; TP, Ryou e selo saem na mesma gravação.
        mysqli_begin_transaction($link);
        $travado = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
        if (!$travado || $travado["currentaction"] == "Fighting") { mysqli_rollback($link); header("Location: index.php"); die(); }
        $userrow = $travado;
        if (bloqueiovila($userrow) != "") { mysqli_rollback($link); voltarcommensagem(bloqueiovila($userrow)); }
        if (($userrow["latitude"] == $townrow["latitude"]) && ($userrow["longitude"] == $townrow["longitude"])) { mysqli_rollback($link); header("Location: index.php"); die(); }
        $c = custoviagem($userrow, $townrow);
        $custo = $c["tp"]; $ryou = $c["ryou"]; $selos = $c["selos"];
        if (intval($userrow["currenttp"]) < $custo) { mysqli_rollback($link); voltarcommensagem(tf("Você não tem TP suficiente para viajar até lá (precisa de %s TP, você tem %s).", $custo, intval($userrow["currenttp"]))); }
        if (intval($userrow["gold"]) < $ryou) { mysqli_rollback($link); voltarcommensagem(tf("A viagem até lá custa %s Ryou (carroças e barqueiros) além do TP. Você não tem Ryou suficiente.", $ryou)); }
        if ($selos > 0) {
            if (contarselos($userrow) < $selos) { mysqli_rollback($link); voltarcommensagem(tf("Viajar direto para uma vila que não é a sua vila natal gasta %s× Selo de Viagem, e você não tem.", $selos)); }
            $falta = $selos; $idselo = (string)iddoselo();
            for ($i = 1; $i <= 8 && $falta > 0; $i++) {
                $p = explode(",", (string)$userrow["bp".$i]);
                if (!isset($p[2]) || $p[1] !== $idselo || intval($p[2]) < 4 || intval($p[2]) > 6) { continue; }
                $q = qtditem($userrow["bp".$i]); $tira = min($q, $falta); $falta -= $tira;
                $sets .= ",bp$i='".addslashes(itemcomqtd($userrow["bp".$i], $q - $tira))."'";
                $onde .= " AND bp$i='".addslashes($userrow["bp".$i])."'";
            }
        }
    }

    // Chegando andando, a cidade entra na lista de mapas conhecidos (se ainda couber na coluna).
    $towns = $userrow["towns"];
    if (!in_array($id, $mapped) && strlen($towns.",".$id) <= 255) { $towns .= ",".$id; }

    doquery("UPDATE {{table}} SET currentaction='In Town',towns='".addslashes($towns)."',currenttp=currenttp-$custo,gold=gold-$ryou$sets,passo_jutsu='0',latitude='".intval($townrow["latitude"])."',longitude='".intval($townrow["longitude"])."' WHERE id='".intval($userrow["id"])."'".(($custo > 0) ? " AND currenttp>=$custo" : "").(($ryou > 0) ? " AND gold>=$ryou" : "")."$onde AND NOT ".sqlcaveiraexposta()." LIMIT 1", "users");
    $afetadas = mysqli_affected_rows($link);
    if ($usepoints == true) { if ($afetadas == 1) { mysqli_commit($link); } else { mysqli_rollback($link); } }
    if ($afetadas != 1) { // caveira ganha entre a leitura e a gravação (outra aba): confere de novo.
        $relido = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1", "users"));
        if (bloqueiovila($relido) != "") { voltarcommensagem(bloqueiovila($relido)); }
    }
    if ($afetadas != 1 && $usepoints == true) { // Algo mudou entre a leitura e a gravação (outra aba).
        voltarcommensagem(t("Você não tem TP suficiente para viajar até lá. Por favor tente novamente quando você tiver mais TP."));
    }

    $page = tf("Você chegou a(o) %s. Agora você pode <a href=\"index.php\">entrar nessa cidade</a>.", t($townrow["name"]));
    if ($usepoints == true) { $page .= " (-".textocustoviagem($c).")"; }
    // Botão para entrar na vila (o link do texto continua).
    $page .= "<div style=\"text-align:center;margin:12px 0 6px\"><a href=\"index.php\" id=\"botaoentrarvila\" class=\"btnjogo bj-confirmar\"><img src=\"images/seta_norte.png\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".t("Entrar na vila")."</a></div>";
    display($page, t("Cidade"));

}

?>
