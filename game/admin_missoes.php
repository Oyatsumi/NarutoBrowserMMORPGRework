<?php // admin_missoes.php :: Painel do Administrador - editor das missões (história e opcionais) e dos NPCs do mapa.
// Mesmo controle de acesso do admin.php (authlevel = 1) e mesmo layout (admindisplay / templates/admin.php).
// Toda ação que altera dados é um POST com token anti-CSRF (conferido logo abaixo, antes de qualquer rota).
//
// Rotas (admin_missoes.php?do=...):
//   (vazio)           lista de missões com filtros e estatísticas; duplicar, ativar/desativar e apagar (POST)
//   editar:N          criar (N = 0) ou editar a missão N
//   npcs              lista dos NPCs do mapa; apagar (POST)
//   editarnpc:N       criar (N = 0) ou editar o NPC N
// Formato das tabelas e regras de cada tipo de missão: veja o cabeçalho de funcoesmissoesextras.php.

include('lib.php');
include('cookies.php');
$link = opendb();
$userrow = checkcookies();
if ($userrow == false) { header("Location: login.php?do=login"); die(); }
if ($userrow["authlevel"] != 1) { header("Location: index.php"); die(); }
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);
include_once('funcoesinclusas.php');        // personagemgeral() para a prévia do NPC.
include_once('funcoesmissoesextras.php');

// IP liberado, senha digitada de novo, confirmação das exclusões e registro de auditoria: painelseguranca.php.
include_once('painelseguranca.php');
painelexigir("admin");

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && !csrf_confere()) {
    admindisplay(ammsg(t("Token de segurança inválido ou expirado. Volte, recarregue a página e tente novamente."), "erro"), t("Erro"));
}
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    if (pegartexto($_POST, "acao") == "apagar" || isset($_POST["apagar"])) { painelconfirmar(tf("Apagar a missão/NPC %s", pegarint($_POST, "id"))); }
    registrarlog("missoes:".preg_replace("/[^a-z0-9_]/i", "", isset($_GET["do"]) ? explode(":", (string)$_GET["do"])[0] : "lista"), pegarint($_POST, "id"), painelresumopost());
}

$do = explode(":", isset($_GET["do"]) ? $_GET["do"] : "");
$id = isset($do[1]) ? intval($do[1]) : 0;

if ($do[0] == "editar") { ameditar($id); }
elseif ($do[0] == "npcs") { amnpcs(); }
elseif ($do[0] == "editarnpc") { amEditarnpc($id); }
else { amlista(); }


/* ------------------------------------------------------------------------
   Auxiliares (cópias pequenas das do admin.php, que não pode ser incluído porque executa as rotas dele).
   ------------------------------------------------------------------------ */

function ammsg($texto, $tipo = "ok") { return "<div class=\"$tipo\">$texto</div>"; } // Caixa de mensagem (ok / erro / aviso).

function amopcoes($opcoes, $atual) { // <option> de um <select> a partir de valor => rótulo.

    $html = "";
    foreach ($opcoes as $valor => $rotulo) { $html .= "<option value=\"".htmlspecialchars((string)$valor)."\"".(((string)$valor === (string)$atual) ? " selected=\"selected\"" : "").">$rotulo</option>"; }
    return $html;

}

function amattr($v) { return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); } // Valor seguro dentro de atributo/textarea.

function amabas($ativa) { // Abas do editor.

    return "<p><a href=\"admin_missoes.php\" class=\"botao\"".($ativa == "m" ? " style=\"background:#452202\"" : "").">".t("Missões")."</a> <a href=\"admin_missoes.php?do=npcs\" class=\"botao\"".($ativa == "n" ? " style=\"background:#452202\"" : "").">".t("NPCs do mapa")."</a></p>";

}

function amtextorico($campo, $maximo, $rotulo, &$erros, $obrigatorio) { // Lê um texto do POST: aspas voltam ao normal e <b>, <i>, <br>, <font color=...> são permitidos.

    $v = isset($_POST[$campo]) && !is_array($_POST[$campo]) ? trim(stripslashes($_POST[$campo])) : "";
    $v = str_replace(array("&#039;", "&quot;"), array("'", "\""), $v);
    $v = preg_replace('/&lt;(\/?)(b|i|br|font)&gt;/i', '<$1$2>', $v);
    $v = preg_replace('/&lt;font color="?([a-z#0-9]+)"?&gt;/i', '<font color=$1>', $v);
    $v = str_replace(array("\r\n", "\r", "\n"), " ", $v);
    if ($obrigatorio && $v == "") { $erros .= tf("%s é obrigatório.", $rotulo)."<br />"; }
    if (mb_strlen($v, "UTF-8") > $maximo) { $erros .= tf("%s pode ter no máximo %s caracteres.", $rotulo, $maximo)."<br />"; }
    if (strpos($v, "|") !== false || strpos($v, ";;") !== false) { $erros .= tf("%s não pode conter | nem ;;.", $rotulo)."<br />"; }
    return $v;

}

function amnum($campo, $rotulo, &$erros, $min, $max) { // Lê e valida um inteiro do POST.

    $v = isset($_POST[$campo]) && !is_array($_POST[$campo]) ? trim($_POST[$campo]) : "";
    if ($v === "" || !preg_match('/^-?[0-9]+$/', $v)) { $erros .= tf("%s deve ser um número.", $rotulo)."<br />"; return 0; }
    $v = intval($v);
    if ($v < $min) { $erros .= tf("%s deve ser no mínimo %s.", $rotulo, $min)."<br />"; }
    if ($v > $max) { $erros .= tf("%s deve ser no máximo %s.", $rotulo, $max)."<br />"; }
    return $v;

}

function amavatares() { // Imagens de layoutnovo/personagem/*.png (sem a extensão).

    $lista = array();
    foreach (glob("layoutnovo/personagem/*.png") ?: array() as $arq) { $lista[] = basename($arq, ".png"); }
    natsort($lista);
    return array_values($lista);

}

function amicones() { // Ícones 24px de NPC no mapa (images/24/arvore_personagem*.gif).

    $lista = array();
    foreach (glob("images/24/arvore_personagem*.gif") ?: array() as $arq) { $lista[] = basename($arq, ".gif"); }
    natsort($lista);
    return array_values($lista);

}

function amvilas() { // id => nome das vilas.

    $lista = array();
    $q = doquery("SELECT id, name FROM {{table}} ORDER BY id", "towns");
    while ($r = mysqli_fetch_array($q)) { $lista[intval($r["id"])] = $r["name"]; }
    return $lista;

}

function amtodosnpcs() { // id => linha de todos os NPCs (ativos ou não).

    $lista = array();
    $q = doquery("SELECT * FROM {{table}} ORDER BY id", "npcs");
    while ($r = mysqli_fetch_array($q, MYSQLI_ASSOC)) { $lista[intval($r["id"])] = $r; }
    return $lista;

}

function amtipos() { // Tipos de missão e rótulos.

    return array("entregar_item" => t("Entregar item"), "derrotar_monstro" => t("Derrotar inimigo"), "visitar_local" => t("Visitar local"), "pagar_ryou" => t("Pagar Ryou"), "falar_com_npc" => t("Falar com NPC"), "repetir" => t("Repetir tarefa (história)"), "especial" => t("Especial (história, regra em PHP)"), "cadeia" => t("Cadeia de etapas"), "viagem" => t("Viagem / escolta"), "coletar" => t("Coletar drops"), "sobreviver" => t("Sobreviver (vitórias seguidas)"), "escolha" => t("Escolha (diálogo)"));

}

function amtiposvalidos($categoria) { // Tipos aceitos em cada categoria.

    if ($categoria == "historia") { return array("entregar_item", "derrotar_monstro", "falar_com_npc", "repetir", "pagar_ryou", "especial"); }
    return array("entregar_item", "derrotar_monstro", "visitar_local", "pagar_ryou", "falar_com_npc", "cadeia", "viagem", "coletar", "sobreviver", "escolha");

}

function amtipospasso() { // Tipos aceitos em cada etapa de uma cadeia.

    return array("falar_com_npc" => t("Falar com NPC"), "derrotar_monstro" => t("Derrotar inimigo"), "visitar_local" => t("Visitar local"), "coletar" => t("Coletar drops"), "sobreviver" => t("Sobreviver (vitórias seguidas)"), "entregar_item" => t("Entregar item"), "pagar_ryou" => t("Pagar Ryou"));

}

function amitemtexto($campo) { return trim(stripslashes(html_entity_decode(pegartexto($_POST, $campo), ENT_QUOTES, "UTF-8"))); } // Nome de item/drop digitado (aspas normais).

function amvalidapasso($p, $num, $ultimo, $tamanho, $npcs, &$erros) { // Confere uma etapa da cadeia (ou o alvo de um tipo simples). Devolve o passo limpo.

    $pre = ($num > 0) ? tf("Etapa %s:", $num)." " : "";
    $tipo = $p["tipo"];
    if ($tipo == "entregar_item" || $tipo == "coletar") {
        if (mx_itemcompleto($p["alvo"]) === false) { $erros .= $pre.t("O item não existe (use o nome exato de um item de loja ou de drop).")."<br />"; }
        if ($tipo == "coletar") { $q = doquery("SELECT id FROM {{table}} WHERE name='".addslashes($p["alvo"])."' LIMIT 1", "drops"); if (mysqli_num_rows($q) == 0) { $erros .= $pre.t("Coletar só funciona com itens de drop.")."<br />"; } }
        if ($tipo == "entregar_item" && $p["quantidade"] > 4) { $erros .= $pre.t("A mochila tem só 4 espaços: a quantidade de itens a entregar deve ser no máximo 4.")."<br />"; }
        if ($tipo == "entregar_item" && intval($p["npc"]) == 0 && !$ultimo) { $erros .= $pre.t("Entregar item no meio da cadeia precisa de um NPC que recebe (ou deve ser a última etapa).")."<br />"; }
        if ($tipo == "coletar" && intval($p["monstro"]) > 0 && !mx_monstro($p["monstro"])) { $erros .= $pre.t("Escolha um inimigo que exista.")."<br />"; }
        if ($tipo == "coletar" && ($p["chance"] < 0 || $p["chance"] > 100)) { $erros .= $pre.t("A chance deve ficar entre 0 e 100.")."<br />"; }
    } elseif ($tipo == "derrotar_monstro") {
        $p["alvo"] = (string)intval($p["alvo"]);
        if (!mx_monstro($p["alvo"])) { $erros .= $pre.t("Escolha um inimigo que exista.")."<br />"; }
    } elseif ($tipo == "visitar_local") {
        $c = explode(",", str_replace(" ", "", $p["alvo"]));
        if (count($c) != 2 || !preg_match('/^-?[0-9]+$/', $c[0]) || !preg_match('/^-?[0-9]+$/', $c[1]) || abs(intval($c[0])) > $tamanho || abs(intval($c[1])) > $tamanho) { $erros .= $pre.tf("A coordenada deve estar no formato latitude,longitude (de -%s a %s).", $tamanho, $tamanho)."<br />"; }
        else {
            $p["alvo"] = intval($c[0]).",".intval($c[1]);
            $qt = doquery("SELECT id FROM {{table}} WHERE latitude='".intval($c[0])."' AND longitude='".intval($c[1])."' LIMIT 1", "towns");
            if (mysqli_num_rows($qt) > 0) { $erros .= $pre.t("A coordenada a visitar não pode ser uma vila (o jogador entra na vila ao pisar nela).")."<br />"; }
        }
        $p["quantidade"] = 1;
    } elseif ($tipo == "falar_com_npc") {
        $p["alvo"] = (string)intval($p["alvo"]);
        if (!isset($npcs[intval($p["alvo"])])) { $erros .= $pre.t("Escolha o NPC com quem conversar.")."<br />"; }
        $p["quantidade"] = 1;
    } elseif ($tipo == "pagar_ryou") {
        if (!$ultimo) { $erros .= $pre.t("Pagar Ryou só pode ser a última etapa.")."<br />"; }
    } elseif ($tipo == "sobreviver") {
        if ($p["quantidade"] > 100) { $erros .= $pre.t("Sobreviver: no máximo 100 vitórias seguidas.")."<br />"; }
    }
    if ($p["quantidade"] < 1) { $erros .= $pre.t("A quantidade deve ser no mínimo 1.")."<br />"; }
    return $p;

}


/* ------------------------------------------------------------------------
   Lista de missões.
   ------------------------------------------------------------------------ */

function amlista() {

    global $link;
    $mensagem = "";
    $acao = pegartexto($_POST, "acao");
    $alvo = pegarint($_POST, "id");
    if ($acao != "" && $alvo > 0) {
        $q = doquery("SELECT * FROM {{table}} WHERE id='$alvo' LIMIT 1", "missoesextras");
        $r = mysqli_fetch_array($q, MYSQLI_ASSOC);
        if (!$r) { $mensagem = ammsg(t("Missão não encontrada."), "erro"); }
        elseif ($acao == "duplicar") {
            $campos = array();
            foreach ($r as $c => $v) {
                if ($c == "id") { continue; }
                if ($c == "titulo") { $v = mb_substr(tf("%s (cópia)", $v), 0, 150, "UTF-8"); }
                if ($c == "ativa") { $v = 0; } // a cópia nasce desativada para o admin revisar.
                if ($c == "categoria") { $v = "extra"; }
                if ($c == "etapa") { $v = 0; }
                $campos[] = "`$c`='".addslashes((string)$v)."'";
            }
            doquery("INSERT INTO {{table}} SET ".implode(", ", $campos), "missoesextras");
            $novo = mysqli_insert_id($link);
            $mensagem = ammsg(tf("Cópia criada com o ID %s (como missão opcional desativada). <a href=\"admin_missoes.php?do=editar:%s\">Editar a cópia</a>", $novo, $novo));
        } elseif ($acao == "ativar" || $acao == "desativar") {
            if ($r["categoria"] == "historia") { $mensagem = ammsg(t("Missões da história ficam sempre ativas (desativar travaria os jogadores nessa etapa)."), "erro"); }
            else {
                doquery("UPDATE {{table}} SET ativa='".($acao == "ativar" ? 1 : 0)."' WHERE id='$alvo' LIMIT 1", "missoesextras");
                $mensagem = ammsg($acao == "ativar" ? t("Missão ativada.") : t("Missão desativada."));
            }
        } elseif ($acao == "apagar") {
            if ($r["categoria"] == "historia") { $mensagem = ammsg(t("Missões da história não podem ser apagadas aqui (os jogadores nessa etapa ficariam sem missão). Edite o texto ou as regras."), "erro"); }
            else {
                doquery("DELETE FROM {{table}} WHERE id='$alvo' LIMIT 1", "missoesextras");
                doquery("DELETE FROM {{table}} WHERE missao_id='$alvo'", "missoesextras_jogador");
                $mensagem = ammsg(tf("Missão <b>%s</b> apagada (com o progresso dos jogadores nela).", $r["titulo"]));
            }
        }
    }

    // Filtros (GET).
    $categoria = pegartexto($_GET, "categoria"); if (!in_array($categoria, array("historia", "extra"))) { $categoria = ""; }
    $tipo = pegartexto($_GET, "tipo"); $tipos = amtipos(); if (!isset($tipos[$tipo]) && $tipo != "cronometrada") { $tipo = ""; }
    $vila = pegartexto($_GET, "vila"); $vila = is_numeric($vila) ? intval($vila) : "";
    $ativa = pegartexto($_GET, "ativa"); if ($ativa !== "0" && $ativa !== "1") { $ativa = ""; }
    $busca = pegartexto($_GET, "busca");
    $onde = "1";
    if ($categoria != "") { $onde .= " AND categoria='$categoria'"; }
    if ($tipo == "cronometrada") { $onde .= " AND limite_min>0"; } elseif ($tipo != "") { $onde .= " AND tipo='$tipo'"; }
    if ($vila !== "") { $onde .= " AND vila_id='$vila'"; }
    if ($ativa !== "") { $onde .= " AND ativa='$ativa'"; }
    if ($busca != "") { $b = str_replace(array("%", "_"), array("\\%", "\\_"), $busca); $onde .= " AND (titulo LIKE '%$b%' OR titulo_en LIKE '%$b%' OR npc_nome LIKE '%$b%')"; }

    // Estatísticas: extras -> aceitas (em andamento) / concluídas; história -> jogadores nessa etapa / que já passaram dela.
    $stats = array();
    $q = doquery("SELECT missao_id, estado, COUNT(*) AS n FROM {{table}} GROUP BY missao_id, estado", "missoesextras_jogador");
    while ($r = mysqli_fetch_array($q)) { $stats[intval($r["missao_id"])][$r["estado"]] = intval($r["n"]); }
    $etapas = array();
    $q = doquery("SELECT CAST(SUBSTRING_INDEX(missao, ',', 1) AS UNSIGNED) AS etapa, COUNT(*) AS n FROM {{table}} GROUP BY etapa", "users");
    while ($r = mysqli_fetch_array($q)) { $etapas[intval($r["etapa"])] = intval($r["n"]); }

    $vilas = amvilas(); $npcs = amtodosnpcs();
    $opvila = "<option value=\"\">".t("Todas")."</option><option value=\"0\"".($vila === 0 ? " selected=\"selected\"" : "").">".t("Qualquer vila / NPC")."</option>".amopcoes(array_map("t", $vilas), $vila);
    $page = "<h1>".t("Missões")."</h1>".amabas("m").$mensagem;
    $page .= "<p>".t("Missões da <b>história</b> são obrigatórias e seguem a ordem da coluna <b>etapa</b> (o progresso fica em users.missao). Missões <b>opcionais</b> são aceitas numa vila ou com um NPC do mapa (cadeias, viagens, coletas, sobrevivência, escolhas, cronometradas, diárias e semanais).")." ".t("Os textos digitados aqui aparecem exatamente como foram escritos; preencha os campos em inglês (opcionais) para os jogadores que usam o jogo em inglês.")."</p>";
    $page .= "<a href=\"admin_missoes.php?do=editar:0\" class=\"botao\">".t("+ Adicionar missão")."</a><br /><br />";
    $page .= "<form action=\"admin_missoes.php\" method=\"get\">".t("Categoria:")." <select name=\"categoria\">".amopcoes(array("" => t("Todas"), "historia" => t("História"), "extra" => t("Opcional")), $categoria)."</select> ".t("Tipo:")." <select name=\"tipo\"><option value=\"\">".t("Todos")."</option>".amopcoes($tipos + array("cronometrada" => t("Cronometradas (qualquer tipo)")), $tipo)."</select> ".t("Vila:")." <select name=\"vila\">$opvila</select> ".t("Situação:")." <select name=\"ativa\">".amopcoes(array("" => t("Todas"), "1" => t("Ativas"), "0" => t("Desativadas")), $ativa)."</select> ".t("Busca:")." <input type=\"text\" name=\"busca\" value=\"".amattr(stripslashes(html_entity_decode($busca, ENT_QUOTES, "UTF-8")))."\" size=\"16\" /> <input type=\"submit\" value=\"".t("Filtrar")."\" /> <a href=\"admin_missoes.php\">".t("Limpar filtros")."</a></form><br />";
    $page .= "<table class=\"lista\"><tr><th>".t("ID")."</th><th>".t("Categoria")."</th><th>".t("Etapa/Ordem")."</th><th>".t("Título")."</th><th>".t("Tipo")."</th><th>".t("Onde")."</th><th>".t("Nível")."</th><th>".t("Recompensa")."</th><th>".t("Jogadores")."</th><th>".t("Situação")."</th><th></th></tr>";
    $csrf = csrf_campo();
    $q = doquery("SELECT * FROM {{table}} WHERE $onde ORDER BY categoria DESC, etapa, ordem, id", "missoesextras");
    $total = 0;
    while ($r = mysqli_fetch_array($q, MYSQLI_ASSOC)) {
        $total++;
        $mid = intval($r["id"]);
        $hist = ($r["categoria"] == "historia");
        if ($hist) {
            $agora = isset($etapas[intval($r["etapa"])]) ? $etapas[intval($r["etapa"])] : 0;
            $depois = 0; foreach ($etapas as $e => $n) { if ($e > intval($r["etapa"])) { $depois += $n; } }
            $estat = tf("%s nesta etapa, %s já passaram", $agora, $depois);
        } else {
            $estat = tf("%s aceita(s), %s concluída(s)", isset($stats[$mid]["andamento"]) ? $stats[$mid]["andamento"] : 0, isset($stats[$mid]["concluida"]) ? $stats[$mid]["concluida"] : 0);
        }
        $onde2 = (intval($r["npc_id"]) > 0 && isset($npcs[intval($r["npc_id"])])) ? "NPC: ".$npcs[intval($r["npc_id"])]["nome"] : "";
        $vilatexto = (intval($r["vila_id"]) == 0) ? t("Qualquer vila") : (isset($vilas[intval($r["vila_id"])]) ? t($vilas[intval($r["vila_id"])]) : "?");
        if (!$hist && $onde2 != "") { $vilatexto = $onde2; } elseif ($onde2 != "") { $vilatexto .= "<br /><span class=\"small\">+ ".$onde2."</span>"; }
        $recomp = array();
        if (intval($r["recompensa_exp"]) > 0) { $recomp[] = intval($r["recompensa_exp"])." ".t("exp."); }
        if (intval($r["recompensa_ryou"]) > 0) { $recomp[] = intval($r["recompensa_ryou"])." Ryou"; }
        if ($r["recompensa_item"] != "") { $recomp[] = nomeitemhtml($r["recompensa_item"]); }
        if (intval($r["recompensa_jutsu"]) > 0) { $recomp[] = tf("Jutsu #%s", intval($r["recompensa_jutsu"])); }
        if (intval(isset($r["recompensa_avatar"]) ? $r["recompensa_avatar"] : 0) > 0) { $recomp[] = tf("Avatar #%s", intval($r["recompensa_avatar"])); }
        $botoes = "<form action=\"admin_missoes.php\" method=\"post\" style=\"margin:0;display:inline\">$csrf<input type=\"hidden\" name=\"id\" value=\"$mid\" /><button type=\"submit\" name=\"acao\" value=\"duplicar\">".t("Duplicar")."</button></form> ";
        if (!$hist) {
            $botoes .= "<form action=\"admin_missoes.php\" method=\"post\" style=\"margin:0;display:inline\">$csrf<input type=\"hidden\" name=\"id\" value=\"$mid\" /><button type=\"submit\" name=\"acao\" value=\"".($r["ativa"] ? "desativar" : "ativar")."\">".($r["ativa"] ? t("Desativar") : t("Ativar"))."</button></form> ";
            $botoes .= "<form action=\"admin_missoes.php\" method=\"post\" style=\"margin:0;display:inline\" onsubmit=\"return confirm('".textojs(t("Apagar esta missão e o progresso dos jogadores nela?"))."')\">$csrf<input type=\"hidden\" name=\"id\" value=\"$mid\" /><button type=\"submit\" name=\"acao\" value=\"apagar\" class=\"botaoperigo\">".t("Apagar")."</button></form>";
        }
        $tipotexto = isset($tipos[$r["tipo"]]) ? $tipos[$r["tipo"]] : $r["tipo"];
        if (intval($r["limite_min"]) > 0) { $tipotexto .= "<br /><span class=\"small\">".tf("Cronometrada: %s min", intval($r["limite_min"]))."</span>"; }
        if (!$hist && intval($r["repetivel"]) == 1) { $tipotexto .= "<br /><span class=\"small\">".mx_repeticaotexto($r)."</span>"; }
        $page .= "<tr><td>$mid</td><td>".($hist ? t("História") : t("Opcional"))."</td><td>".($hist ? tf("Etapa %s", intval($r["etapa"])) : intval($r["ordem"]))."</td><td><a href=\"admin_missoes.php?do=editar:$mid\">".$r["titulo"]."</a>".($r["titulo_en"] != "" ? "<br /><span class=\"small\">".$r["titulo_en"]."</span>" : "")."</td><td>$tipotexto</td><td>$vilatexto</td><td>".intval($r["nivel_minimo"])."</td><td>".implode(", ", $recomp)."</td><td class=\"small\">$estat</td><td>".($r["ativa"] ? t("Ativa") : "<span class=\"highlight\">".t("Desativada")."</span>")."</td><td>$botoes</td></tr>";
    }
    if ($total == 0) { $page .= "<tr><td colspan=\"11\">".t("Nenhuma missão encontrada.")."</td></tr>"; }
    $page .= "</table>";
    admindisplay($page, t("Missões"));

}


/* ------------------------------------------------------------------------
   Criar / editar missão.
   ------------------------------------------------------------------------ */

function ameditar($id) {

    global $controlrow, $link;
    $mensagem = ""; $erros = "";
    $tamanho = intval($controlrow["gamesize"]);
    $vilas = amvilas(); $npcs = amtodosnpcs(); $avatares = amavatares(); $tipos = amtipos(); $tiposetapa = amtipospasso();
    $graduacoes = array("" => t("Nenhuma"));
    foreach (mx_graduacoes() as $g => $n) { $graduacoes[$g] = t($g); }
    $ganchos = array("" => t("Nenhum")); foreach (mx_ganchos() as $g => $f) { $ganchos[$g] = $g; }

    // Valores atuais (ou padrão de uma missão nova).
    if ($id > 0) {
        $q = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "missoesextras");
        $r = mysqli_fetch_array($q, MYSQLI_ASSOC);
        if (!$r) { admindisplay(ammsg(t("Missão não encontrada."), "erro").amabas("m"), t("Missões")); }
    } else {
        $r = array("categoria" => "extra", "etapa" => 0, "titulo" => "", "titulo_en" => "", "descricao" => "", "descricao_en" => "", "descricao_arg" => "", "recompensa_texto" => "", "recompensa_texto_en" => "", "recompensa_arg" => "", "rank_texto" => "Rank D.", "local_texto" => "", "mapa_item" => "", "mapa_local" => "", "mapa_monstro" => "", "npc_nome" => "", "npc_avatar" => "personagem1", "npc_id" => 0, "vila_id" => 1, "nivel_minimo" => 1, "graduacao_minima" => "", "tipo" => "derrotar_monstro", "alvo" => "", "quantidade" => 1, "intervalo_min" => 0, "gancho" => "", "recompensa_exp" => 0, "recompensa_ryou" => 0, "recompensa_item" => "", "recompensa_jutsu" => 0, "repetivel" => 0, "intervalo_horas" => 24, "ativa" => 1, "ordem" => 0, "config" => "", "limite_min" => 0);
    }
    $cfg = mx_config($r);

    if (isset($_POST["salvar"])) {
        $n = array();
        $n["categoria"] = (pegartexto($_POST, "categoria") == "historia") ? "historia" : "extra";
        $hist = ($n["categoria"] == "historia");
        $n["titulo"] = amtextorico("titulo", 150, t("Título"), $erros, true);
        $n["titulo_en"] = amtextorico("titulo_en", 150, t("Título (inglês)"), $erros, false);
        $n["descricao"] = amtextorico("descricao", 2000, t("Texto da missão"), $erros, true);
        $n["descricao_en"] = amtextorico("descricao_en", 2000, t("Texto da missão (inglês)"), $erros, false);
        $n["rank_texto"] = amtextorico("rank_texto", 30, t("Rank"), $erros, false);
        $n["npc_nome"] = amtextorico("npc_nome", 50, t("Nome de quem pede"), $erros, false);
        $n["npc_avatar"] = pegartexto($_POST, "npc_avatar");
        if (!in_array($n["npc_avatar"], $avatares, true)) { $erros .= t("Escolha um avatar da lista.")."<br />"; }
        $n["vila_id"] = amnum("vila_id", t("Vila"), $erros, 0, 65535);
        if ($n["vila_id"] > 0 && !isset($vilas[$n["vila_id"]])) { $erros .= t("Vila inexistente.")."<br />"; }
        $n["npc_id"] = amnum("npc_id", t("NPC"), $erros, 0, 4294967295);
        if ($n["npc_id"] > 0 && !isset($npcs[$n["npc_id"]])) { $erros .= t("NPC inexistente.")."<br />"; }
        $n["nivel_minimo"] = amnum("nivel_minimo", t("Nível mínimo"), $erros, 1, 200);
        $n["graduacao_minima"] = pegartexto($_POST, "graduacao_minima");
        if (!isset($graduacoes[$n["graduacao_minima"]])) { $erros .= t("Graduação inválida.")."<br />"; $n["graduacao_minima"] = ""; }
        $n["tipo"] = pegartexto($_POST, "tipo");
        if (!in_array($n["tipo"], amtiposvalidos($n["categoria"]), true)) { $erros .= t("Esse tipo de missão não é aceito nessa categoria.")."<br />"; $n["tipo"] = "entregar_item"; }
        $n["quantidade"] = amnum("quantidade", t("Quantidade"), $erros, 1, 16777215);
        $n["recompensa_exp"] = amnum("recompensa_exp", t("Experiência"), $erros, 0, 1000000);
        $n["recompensa_ryou"] = amnum("recompensa_ryou", t("Ryou"), $erros, 0, 1000000);
        $n["recompensa_item"] = trim(stripslashes(html_entity_decode(pegartexto($_POST, "recompensa_item"), ENT_QUOTES, "UTF-8")));
        if ($n["recompensa_item"] != "" && mx_itemcompleto($n["recompensa_item"]) === false) { $erros .= t("O item de recompensa não existe (use o nome exato de um item de loja ou de drop).")."<br />"; }
        // Jutsu de recompensa (spells.id; 0 = nenhum). Só as missões extras pagam (mx_entregar): ensina, ou dá o pergaminho, ou Ryou.
        $n["recompensa_jutsu"] = $hist ? 0 : amnum("recompensa_jutsu", t("Jutsu"), $erros, 0, 65535);
        if ($n["recompensa_jutsu"] > 0 && !mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE id='".intval($n["recompensa_jutsu"])."' LIMIT 1", "spells"))) { $erros .= t("O jutsu de recompensa não existe.")."<br />"; }
        $n["recompensa_avatar"] = $hist ? 0 : pegarint($_POST, "recompensa_avatar"); // avatar liberado na entrega (naruto_avatares).
        if ($n["recompensa_avatar"] > 0 && !mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE id='".intval($n["recompensa_avatar"])."' LIMIT 1", "avatares"))) { $erros .= t("O avatar de recompensa não existe.")."<br />"; }
        $n["ativa"] = (pegarint($_POST, "ativa") == 1 || $hist) ? 1 : 0;
        $n["ordem"] = amnum("ordem", t("Ordem"), $erros, -100000, 100000);
        $n["gancho"] = ""; $n["intervalo_min"] = 0; $n["alvo"] = "";
        $cfg = array(); // config JSON (tipos novos, escolha, requisito)

        // Alvo conforme o tipo (cada tipo tem o seu campo no formulário).
        if ($n["tipo"] == "entregar_item") {
            $n["alvo"] = trim(stripslashes(html_entity_decode(pegartexto($_POST, "alvo_item"), ENT_QUOTES, "UTF-8")));
            if (mx_itemcompleto($n["alvo"]) === false) { $erros .= t("O item a entregar não existe (use o nome exato de um item de loja ou de drop).")."<br />"; }
            if ($n["quantidade"] > 4) { $erros .= t("A mochila tem só 4 espaços: a quantidade de itens a entregar deve ser no máximo 4.")."<br />"; }
        } elseif ($n["tipo"] == "derrotar_monstro") {
            $n["alvo"] = (string)pegarint($_POST, "alvo_monstro");
            if (!mx_monstro($n["alvo"])) { $erros .= t("Escolha um inimigo que exista.")."<br />"; }
        } elseif ($n["tipo"] == "visitar_local") {
            $la = amnum("alvo_lat", t("Latitude"), $erros, -$tamanho, $tamanho);
            $lo = amnum("alvo_lon", t("Longitude"), $erros, -$tamanho, $tamanho);
            $n["alvo"] = $la.",".$lo;
            $qt = doquery("SELECT id FROM {{table}} WHERE latitude='$la' AND longitude='$lo' LIMIT 1", "towns");
            if (mysqli_num_rows($qt) > 0) { $erros .= t("A coordenada a visitar não pode ser uma vila (o jogador entra na vila ao pisar nela).")."<br />"; }
            $n["quantidade"] = 1;
        } elseif ($n["tipo"] == "falar_com_npc") {
            if ($hist) { if ($n["npc_id"] == 0) { $erros .= t("Na história, \"Falar com NPC\" usa o NPC escolhido no campo NPC.")."<br />"; } }
            else {
                $n["alvo"] = (string)pegarint($_POST, "alvo_npc");
                if (!isset($npcs[intval($n["alvo"])])) { $erros .= t("Escolha o NPC com quem conversar.")."<br />"; }
                elseif (intval($n["alvo"]) == $n["npc_id"]) { $erros .= t("O NPC com quem conversar deve ser diferente do NPC que dá a missão.")."<br />"; }
            }
            $n["quantidade"] = 1;
        } elseif ($n["tipo"] == "coletar") {
            $p = amvalidapasso(array("tipo" => "coletar", "alvo" => amitemtexto("alvo_item"), "quantidade" => $n["quantidade"], "npc" => 0, "monstro" => pegarint($_POST, "cfg_monstro"), "chance" => amnum("cfg_chance", t("Chance da missão (%)"), $erros, 0, 100)), 0, true, $tamanho, $npcs, $erros);
            $n["alvo"] = $p["alvo"];
            if (intval($p["monstro"]) > 0) { $cfg["monstro"] = intval($p["monstro"]); }
            if (intval($p["chance"]) > 0) { $cfg["chance"] = intval($p["chance"]); }
        } elseif ($n["tipo"] == "sobreviver") {
            if ($n["quantidade"] > 100) { $erros .= t("Sobreviver: no máximo 100 vitórias seguidas.")."<br />"; }
            $nv = amnum("cfg_nivel", t("Nível mínimo do inimigo"), $erros, 0, 200);
            if ($nv > 0) { $cfg["nivel"] = $nv; }
        } elseif ($n["tipo"] == "viagem") {
            $dest = pegartexto($_POST, "alvo_destino");
            $n["alvo"] = $dest;
            if (!preg_match('/^(vila|npc):[0-9]+$/', $dest) || !mx_destino(array("alvo" => $dest))) { $erros .= t("Escolha o destino da viagem.")."<br />"; }
            $n["quantidade"] = amnum("viagem_passos", t("Limite de passos"), $erros, 0, 100000);
        } elseif ($n["tipo"] == "escolha") {
            $n["quantidade"] = 1;
        } elseif ($n["tipo"] == "cadeia") {
            $etapas = array();
            $brutas = array();
            for ($k = 1; $k <= 6; $k++) {
                $tp = pegartexto($_POST, "etapa{$k}_tipo");
                if ($tp == "") { continue; }
                if (!isset($tiposetapa[$tp])) { $erros .= tf("Etapa %s:", $k)." ".t("Tipo inválido.")."<br />"; continue; }
                $brutas[] = array("tipo" => $tp, "alvo" => amitemtexto("etapa{$k}_alvo"), "quantidade" => amnum("etapa{$k}_qtd", tf("Etapa %s: quantidade", $k), $erros, 1, 100000), "npc" => pegarint($_POST, "etapa{$k}_npc"), "monstro" => pegarint($_POST, "etapa{$k}_monstro"), "chance" => pegarint($_POST, "etapa{$k}_chance"), "nivel" => 0, "texto" => amtextorico("etapa{$k}_texto", 300, tf("Etapa %s: texto", $k), $erros, false), "texto_en" => amtextorico("etapa{$k}_texto_en", 300, tf("Etapa %s: texto (inglês)", $k), $erros, false));
            }
            if (count($brutas) < 2) { $erros .= t("Uma cadeia precisa de pelo menos 2 etapas.")."<br />"; }
            foreach ($brutas as $k => $p) {
                if (intval($p["npc"]) > 0 && !isset($npcs[intval($p["npc"])])) { $erros .= tf("Etapa %s:", $k + 1)." ".t("NPC inexistente.")."<br />"; }
                if ($p["tipo"] != "entregar_item") { $p["npc"] = 0; }
                if ($p["tipo"] == "sobreviver") { $p["nivel"] = intval($p["monstro"]); $p["monstro"] = 0; }
                if ($p["tipo"] != "coletar") { $p["monstro"] = ($p["tipo"] == "sobreviver") ? 0 : 0; $p["chance"] = 0; }
                $p = amvalidapasso($p, $k + 1, $k == count($brutas) - 1, $tamanho, $npcs, $erros);
                $limpo = array("tipo" => $p["tipo"], "alvo" => (string)$p["alvo"], "quantidade" => intval($p["quantidade"]));
                foreach (array("npc", "monstro", "chance", "nivel") as $c) { if (intval($p[$c]) > 0) { $limpo[$c] = intval($p[$c]); } }
                if ($p["texto"] != "") { $limpo["texto"] = $p["texto"]; }
                if ($p["texto_en"] != "") { $limpo["texto_en"] = $p["texto_en"]; }
                $etapas[] = $limpo;
            }
            $cfg["etapas"] = $etapas;
            $n["quantidade"] = max(1, count($etapas));
        } elseif ($n["tipo"] == "repetir") {
            $n["intervalo_min"] = amnum("intervalo_min", t("Intervalo entre repetições (minutos)"), $erros, 0, 100000);
        } elseif ($n["tipo"] == "especial") {
            $n["gancho"] = pegartexto($_POST, "gancho");
            if ($n["gancho"] == "" || !isset($ganchos[$n["gancho"]])) { $erros .= t("Escolha a regra especial (gancho).")."<br />"; }
        }

        // Escolha (duas opções), requisito (missão anterior) e limite de tempo: só missões opcionais.
        $n["limite_min"] = 0;
        if (!$hist) {
            $n["limite_min"] = amnum("limite_min", t("Limite de tempo (minutos)"), $erros, 0, 100000);
            $temop = (pegartexto($_POST, "op0_rotulo") != "" || pegartexto($_POST, "op1_rotulo") != "");
            if ($n["tipo"] == "escolha" && !$temop) { $erros .= t("Uma missão de escolha precisa das duas opções.")."<br />"; }
            if ($temop) {
                $ops = array();
                for ($k = 0; $k <= 1; $k++) {
                    $rot = tf("Opção %s", $k == 0 ? "A" : "B");
                    foreach (array("exp", "ryou") as $c) { if (!isset($_POST["op{$k}_$c"]) || $_POST["op{$k}_$c"] === "") { $_POST["op{$k}_$c"] = "0"; } } // vazio = sem esse prêmio.
                    $op = array("rotulo" => amtextorico("op{$k}_rotulo", 60, $rot.": ".t("Botão"), $erros, true), "rotulo_en" => amtextorico("op{$k}_rotulo_en", 60, $rot.": ".t("Botão (inglês)"), $erros, false),
                        "texto" => amtextorico("op{$k}_texto", 500, $rot.": ".t("Desfecho"), $erros, false), "texto_en" => amtextorico("op{$k}_texto_en", 500, $rot.": ".t("Desfecho (inglês)"), $erros, false),
                        "exp" => amnum("op{$k}_exp", $rot.": ".t("Experiência"), $erros, 0, 1000000), "ryou" => amnum("op{$k}_ryou", $rot.": ".t("Ryou"), $erros, 0, 1000000), "item" => amitemtexto("op{$k}_item"));
                    if ($op["item"] != "" && mx_itemcompleto($op["item"]) === false) { $erros .= $rot.": ".t("O item de recompensa não existe (use o nome exato de um item de loja ou de drop).")."<br />"; }
                    $ops[] = $op;
                }
                $cfg["opcoes"] = $ops;
            }
            $req = pegarint($_POST, "cfg_requer");
            if ($req > 0) {
                $qr = doquery("SELECT id FROM {{table}} WHERE id='$req' AND categoria='extra' AND id<>'".intval($id)."' LIMIT 1", "missoesextras");
                if (mysqli_num_rows($qr) == 0) { $erros .= t("A missão exigida não existe.")."<br />"; }
                $cfg["requer"] = $req;
                $ro = pegartexto($_POST, "cfg_requer_opcao");
                if ($ro === "0" || $ro === "1") { $cfg["requer_opcao"] = $ro; }
            }
        }
        // Troca de vila natal (config "troca_vila" = id da vila de destino; docs/TROCA_DE_VILA.md): só missões opcionais,
        // só vilas natais comuns (vilanatal = 1, com alinhamento e não secretas).
        $trv = $hist ? 0 : pegarint($_POST, "cfg_troca_vila");
        if ($trv > 0) {
            if (!mx_vilaparatroca($trv)) { $erros .= t("A vila de destino da troca precisa ser uma vila natal com alinhamento e não secreta.")."<br />"; }
            else { $cfg["troca_vila"] = $trv; }
        }
        // Troca de dificuldade (config "troca_dificuldade" = 1..3; docs/TROCA_DE_DIFICULDADE.md): só missões opcionais.
        $trd = $hist ? 0 : pegarint($_POST, "cfg_troca_dificuldade");
        if ($trd > 0) {
            if ($trd > 3) { $erros .= t("A dificuldade de destino precisa ser 1, 2 ou 3.")."<br />"; }
            elseif (isset($cfg["troca_vila"])) { $erros .= t("Uma missão não pode trocar a vila natal e a dificuldade ao mesmo tempo.")."<br />"; }
            else { $cfg["troca_dificuldade"] = $trd; }
        }
        $n["config"] = count($cfg) ? json_encode($cfg, JSON_UNESCAPED_UNICODE) : "";

        if ($hist) {
            $n["etapa"] = amnum("etapa", t("Etapa"), $erros, 1, 1000);
            $qe = doquery("SELECT id FROM {{table}} WHERE categoria='historia' AND etapa='".intval($n["etapa"])."' AND id<>'".intval($id)."' LIMIT 1", "missoesextras");
            if (mysqli_num_rows($qe) > 0) { $erros .= tf("Já existe outra missão da história na etapa %s.", intval($n["etapa"]))."<br />"; }
            if ($n["vila_id"] == 0) { $erros .= t("Missões da história são entregues numa vila: escolha a vila.")."<br />"; }
            $n["repetivel"] = 0; $n["intervalo_horas"] = 0;
            $n["descricao_arg"] = trim(stripslashes(html_entity_decode(pegartexto($_POST, "descricao_arg"), ENT_QUOTES, "UTF-8")));
            $n["recompensa_texto"] = amtextorico("recompensa_texto", 255, t("Texto da recompensa"), $erros, false);
            $n["recompensa_texto_en"] = amtextorico("recompensa_texto_en", 255, t("Texto da recompensa (inglês)"), $erros, false);
            $n["recompensa_arg"] = trim(stripslashes(html_entity_decode(pegartexto($_POST, "recompensa_arg"), ENT_QUOTES, "UTF-8")));
            $n["local_texto"] = amtextorico("local_texto", 100, t("Local de conclusão (texto)"), $erros, false);
            $n["mapa_item"] = trim(stripslashes(html_entity_decode(pegartexto($_POST, "mapa_item"), ENT_QUOTES, "UTF-8")));
            $n["mapa_local"] = pegartexto($_POST, "mapa_local");
            if ($n["mapa_local"] != "" && !preg_match('/^-?[0-9]{1,4}\/-?[0-9]{1,4}$/', $n["mapa_local"])) { $erros .= t("Local no mapa deve estar no formato latitude/longitude (ex.: -101/102).")."<br />"; }
            $n["mapa_monstro"] = trim(stripslashes(html_entity_decode(pegartexto($_POST, "mapa_monstro"), ENT_QUOTES, "UTF-8")));
            if ($n["mapa_item"] != "" && mx_itemcompleto($n["mapa_item"]) === false) { $erros .= t("O item do mapa de drops não existe.")."<br />"; }
            if ($n["mapa_monstro"] != "") { $qm = doquery("SELECT id FROM {{table}} WHERE name='".addslashes($n["mapa_monstro"])."' LIMIT 1", "monsters"); if (mysqli_num_rows($qm) == 0) { $erros .= t("O inimigo do mapa de respawn não existe.")."<br />"; } }
            foreach (array("descricao", "descricao_en", "recompensa_texto", "recompensa_texto_en") as $c) { if (substr_count($n[$c], "%s") > 1) { $erros .= t("Use no máximo um %s em cada texto.")."<br />"; } }
        } else {
            $n["etapa"] = 0;
            $n["repetivel"] = (pegarint($_POST, "repetivel") == 1) ? 1 : 0;
            $n["intervalo_horas"] = amnum("intervalo_horas", t("Intervalo para repetir (horas)"), $erros, $n["repetivel"] ? 1 : 0, 8760);
            foreach (array("descricao_arg", "recompensa_texto", "recompensa_texto_en", "recompensa_arg", "local_texto", "mapa_item", "mapa_local", "mapa_monstro") as $c) { $n[$c] = ""; }
            if ($n["npc_id"] > 0) { $n["vila_id"] = 0; }
        }

        if ($erros == "") {
            $sets = array();
            foreach ($n as $c => $v) { $sets[] = "`$c`='".addslashes((string)$v)."'"; }
            if ($id > 0) {
                doquery("UPDATE {{table}} SET ".implode(", ", $sets)." WHERE id='$id' LIMIT 1", "missoesextras");
                $mensagem = ammsg(t("Missão salva.")." <a href=\"admin_missoes.php\">".t("Voltar para a lista")."</a>");
            } else {
                doquery("INSERT INTO {{table}} SET ".implode(", ", $sets), "missoesextras");
                $id = mysqli_insert_id($link);
                $mensagem = ammsg(tf("Missão criada com o ID %s.", $id)." <a href=\"admin_missoes.php\">".t("Voltar para a lista")."</a>");
            }
            $q = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "missoesextras");
            $r = mysqli_fetch_array($q, MYSQLI_ASSOC);
            $cfg = mx_config($r);
        } else {
            $mensagem = ammsg("<b>".t("Corrija os erros abaixo:")."</b><br />".$erros, "erro");
            $r = array_merge($r, $n); // mostra de novo o que foi digitado.
            $cfg = mx_json($n["config"]);
            if ($r["tipo"] == "derrotar_monstro") { $r["alvo"] = (string)pegarint($_POST, "alvo_monstro"); }
        }
    }

    // ---------------- Formulário.
    $hist = ($r["categoria"] == "historia");
    $alvo = (string)$r["alvo"];
    $coord = explode(",", $alvo);
    $alvolat = ($r["tipo"] == "visitar_local" && isset($coord[1])) ? intval($coord[0]) : 0;
    $alvolon = ($r["tipo"] == "visitar_local" && isset($coord[1])) ? intval($coord[1]) : 0;
    $opmonstros = "";
    $q = doquery("SELECT id, name, level FROM {{table}} ORDER BY level, name", "monsters");
    while ($m = mysqli_fetch_array($q)) { $opmonstros .= "<option value=\"".intval($m["id"])."\"".(($r["tipo"] == "derrotar_monstro" && $alvo === (string)$m["id"]) ? " selected=\"selected\"" : "").">".tf("Nível %s", intval($m["level"]))." - ".$m["name"]." (ID ".intval($m["id"]).")</option>"; }
    $itens = "";
    $q = doquery("SELECT name, mlevel FROM {{table}} ORDER BY name", "drops");
    while ($i = mysqli_fetch_array($q)) { $itens .= "<option value=\"".amattr($i["name"])."\">".t("Drop")." - ".tf("nível %s", intval($i["mlevel"]))."</option>"; }
    $q = doquery("SELECT name, buycost FROM {{table}} ORDER BY name", "items");
    while ($i = mysqli_fetch_array($q)) { $itens .= "<option value=\"".amattr($i["name"])."\">".t("Loja")." - ".intval($i["buycost"])." Ryou</option>"; }
    $opnpcs = array(0 => t("Nenhum"));
    foreach ($npcs as $nid => $npc) { $opnpcs[$nid] = $npc["nome"]." [".intval($npc["latitude"]).",".intval($npc["longitude"])."]".($npc["ativo"] ? "" : " (".t("desativado").")"); }
    $opnpcalvo = $opnpcs; unset($opnpcalvo[0]);
    $opvilas = array(0 => t("Qualquer vila"));
    foreach ($vilas as $vid => $vn) { $opvilas[$vid] = t($vn); }
    $avatarhtml = "";
    foreach ($avatares as $a) {
        $marcado = ($a === $r["npc_avatar"]) ? " checked=\"checked\"" : "";
        $avatarhtml .= "<label style=\"display:inline-block;text-align:center;margin:2px;cursor:pointer\"><img src=\"layoutnovo/personagem/".amattr($a).".png\" height=\"48\" alt=\"\" style=\"border:1px solid #c9b47a;background:#fff1c7\" /><br /><input type=\"radio\" name=\"npc_avatar\" value=\"".amattr($a)."\"$marcado onchange=\"amprevia()\" /> <span class=\"small\">".amattr($a)."</span></label>";
    }
    $previa = personagemgeral("<span id=\"previatexto\">".$r["descricao"]."</span>", amattr($r["npc_avatar"]), "<span id=\"previanome\">".($r["npc_nome"] != "" ? $r["npc_nome"] : "NPC")."</span>", "sem");
    $previa = str_replace("src=\"layoutnovo/personagem/".amattr($r["npc_avatar"]).".png\"", "id=\"previaimg\" src=\"layoutnovo/personagem/".amattr($r["npc_avatar"]).".png\"", $previa);

    $titulo = ($id > 0) ? tf("Editar missão #%s", $id) : t("Nova missão");
    $page = "<h1>$titulo</h1>".amabas("m").$mensagem;
    if ($hist) { $page .= ammsg(t("<b>Atenção:</b> esta é uma missão da história. Mudar a etapa, o tipo ou a quantidade afeta os jogadores que estão nela agora (o progresso deles fica em users.missao, no formato \"etapa,feitos,total,intervalo\"). Prefira mudar só os textos e as recompensas."), "aviso"); }
    $page .= "<p class=\"small\">".t("Os textos aparecem para os jogadores exatamente como digitados (é permitido &lt;b&gt;, &lt;i&gt;, &lt;br&gt; e &lt;font color=...&gt;). Os campos em inglês são opcionais: se estiverem vazios, quem joga em inglês vê o texto em português.")."</p>";
    $f = "<form action=\"admin_missoes.php?do=editar:$id\" method=\"post\">".csrf_campo()."<table>";
    $f .= "<tr><td width=\"200\"><b>".t("Categoria")."</b></td><td><select name=\"categoria\" id=\"categoria\" onchange=\"amtipo()\">".amopcoes(array("extra" => t("Opcional"), "historia" => t("História (obrigatória)")), $r["categoria"])."</select></td></tr>";
    $f .= "<tr class=\"sohist\"><td><b>".t("Etapa")."</b></td><td><input type=\"number\" name=\"etapa\" value=\"".intval($r["etapa"])."\" style=\"width:70px\" /> <span class=\"small\">".t("Número da missão na história (1, 2, 3...). A próxima etapa começa quando esta é entregue.")."</span></td></tr>";
    $f .= "<tr><td><b>".t("Título")."</b></td><td><input type=\"text\" name=\"titulo\" value=\"".amattr($r["titulo"])."\" size=\"60\" maxlength=\"150\" /></td></tr>";
    $f .= "<tr><td>".t("Título (inglês)")."</td><td><input type=\"text\" name=\"titulo_en\" value=\"".amattr($r["titulo_en"])."\" size=\"60\" maxlength=\"150\" /></td></tr>";
    $f .= "<tr><td><b>".t("Texto da missão")."</b><br /><span class=\"small\">".t("História contada pelo NPC / requerimento.")."</span></td><td><textarea name=\"descricao\" id=\"descricao\" rows=\"4\" cols=\"70\" oninput=\"amprevia()\">".amattr($r["descricao"])."</textarea></td></tr>";
    $f .= "<tr><td>".t("Texto da missão (inglês)")."</td><td><textarea name=\"descricao_en\" rows=\"4\" cols=\"70\">".amattr($r["descricao_en"])."</textarea></td></tr>";
    $f .= "<tr class=\"sohist\"><td>".t("Nome no lugar do %s do texto")."</td><td><input type=\"text\" name=\"descricao_arg\" value=\"".amattr($r["descricao_arg"])."\" size=\"30\" list=\"listaitens\" /> <span class=\"small\">".t("Nome de item/inimigo (traduzido automaticamente).")."</span></td></tr>";
    $f .= "<tr class=\"sohist\"><td>".t("Texto da recompensa")."</td><td><input type=\"text\" name=\"recompensa_texto\" value=\"".amattr($r["recompensa_texto"])."\" size=\"50\" /> ".t("inglês:")." <input type=\"text\" name=\"recompensa_texto_en\" value=\"".amattr($r["recompensa_texto_en"])."\" size=\"40\" /> %s = <input type=\"text\" name=\"recompensa_arg\" value=\"".amattr($r["recompensa_arg"])."\" size=\"20\" list=\"listaitens\" /></td></tr>";
    $f .= "<tr class=\"sohist\"><td>".t("Local de conclusão (texto)")."</td><td><input type=\"text\" name=\"local_texto\" value=\"".amattr($r["local_texto"])."\" size=\"30\" /> <span class=\"small\">".t("Ex.: Vila da Folha.")."</span></td></tr>";
    $f .= "<tr class=\"sohist\"><td>".t("Botões de mapa do painel")."</td><td>".t("Item (mapa de drops):")." <input type=\"text\" name=\"mapa_item\" value=\"".amattr($r["mapa_item"])."\" size=\"18\" list=\"listaitens\" /> ".t("Local do NPC (lat/lon):")." <input type=\"text\" name=\"mapa_local\" value=\"".amattr($r["mapa_local"])."\" size=\"9\" /> ".t("Inimigo (respawn):")." <input type=\"text\" name=\"mapa_monstro\" value=\"".amattr($r["mapa_monstro"])."\" size=\"18\" /></td></tr>";
    $f .= "<tr><td>".t("Rank")."</td><td><input type=\"text\" name=\"rank_texto\" value=\"".amattr($r["rank_texto"])."\" size=\"10\" maxlength=\"30\" /> <span class=\"small\">".t("Ex.: Rank D.")."</span></td></tr>";
    $f .= "<tr><td><b>".t("Nome de quem pede")."</b></td><td><input type=\"text\" name=\"npc_nome\" id=\"npc_nome\" value=\"".amattr($r["npc_nome"])."\" size=\"30\" maxlength=\"50\" oninput=\"amprevia()\" /></td></tr>";
    $f .= "<tr><td><b>".t("Avatar")."</b></td><td>$avatarhtml</td></tr>";
    $f .= "<tr><td>".t("Prévia da fala")."</td><td style=\"background:#fff1c7\">$previa</td></tr>";
    $f .= "<tr><td><b>".t("Vila")."</b></td><td><select name=\"vila_id\">".amopcoes($opvilas, intval($r["vila_id"]))."</select> <span class=\"small\">".t("Onde a missão é aceita e entregue (extras com NPC ignoram a vila).")."</span></td></tr>";
    $f .= "<tr><td><b>".t("NPC")."</b></td><td><select name=\"npc_id\">".amopcoes($opnpcs, intval($r["npc_id"]))."</select> <span class=\"small\">".t("Extras: a missão é aceita/entregue conversando com esse NPC no mapa. História: também é preciso conversar com ele.")."</span> <a href=\"admin_missoes.php?do=npcs\">".t("NPCs do mapa")."</a></td></tr>";
    $f .= "<tr><td>".t("Nível mínimo")."</td><td><input type=\"number\" name=\"nivel_minimo\" value=\"".intval($r["nivel_minimo"])."\" style=\"width:70px\" /> ".t("Graduação mínima:")." <select name=\"graduacao_minima\">".amopcoes($graduacoes, $r["graduacao_minima"])."</select></td></tr>";
    $f .= "<tr><td><b>".t("Tipo")."</b></td><td><select name=\"tipo\" id=\"tipo\" onchange=\"amtipo()\">".amopcoes($tipos, $r["tipo"])."</select></td></tr>";
    $f .= "<tr id=\"l_item\"><td>".t("Item a entregar")."</td><td><input type=\"text\" name=\"alvo_item\" value=\"".amattr($r["tipo"] == "entregar_item" ? $alvo : "")."\" size=\"30\" list=\"listaitens\" /> <span class=\"small\">".t("Nome exato de um item de loja ou de drop. Ele precisa estar na mochila e é consumido na entrega.")."</span></td></tr>";
    $f .= "<tr id=\"l_monstro\"><td>".t("Inimigo")."</td><td><select name=\"alvo_monstro\">$opmonstros</select></td></tr>";
    $f .= "<tr id=\"l_local\"><td>".t("Coordenada")."</td><td>".t("Latitude")." <input type=\"number\" name=\"alvo_lat\" value=\"$alvolat\" style=\"width:70px\" /> ".t("Longitude")." <input type=\"number\" name=\"alvo_lon\" value=\"$alvolon\" style=\"width:70px\" /> <span class=\"small\">".tf("De -%s a %s; não pode ser uma vila.", $tamanho, $tamanho)."</span></td></tr>";
    $f .= "<tr id=\"l_npc\"><td>".t("Conversar com")."</td><td><select name=\"alvo_npc\">".amopcoes($opnpcalvo, ($r["tipo"] == "falar_com_npc") ? intval($alvo) : 0)."</select></td></tr>";
    // Campos dos tipos novos (config JSON).
    $opdest = array("" => t("Escolha..."));
    foreach ($vilas as $vid => $vn) { $opdest["vila:".$vid] = t("Vila").": ".t($vn); }
    foreach ($npcs as $nid => $npc) { $opdest["npc:".$nid] = "NPC: ".$npc["nome"]." [".intval($npc["latitude"]).",".intval($npc["longitude"])."]"; }
    $opmon0 = "<option value=\"0\">".t("Qualquer inimigo")."</option>".str_replace(" selected=\"selected\"", "", $opmonstros);
    $f .= "<tr id=\"l_destino\"><td>".t("Destino")."</td><td><select name=\"alvo_destino\">".amopcoes($opdest, $r["tipo"] == "viagem" ? $alvo : "")."</select> ".t("Limite de passos:")." <input type=\"number\" name=\"viagem_passos\" value=\"".($r["tipo"] == "viagem" ? intval($r["quantidade"]) : 0)."\" style=\"width:70px\" /> <span class=\"small\">".t("0 = sem limite. A viagem começa onde a missão é aceita e é entregue no destino; viagem rápida (TP) não vale, e morrer faz a missão falhar.")."</span></td></tr>";
    $f .= "<tr id=\"l_coleta\"><td>".t("Coleta")."</td><td>".t("Inimigo:")." <select name=\"cfg_monstro\">".str_replace("value=\"".intval(isset($cfg["monstro"]) ? $cfg["monstro"] : 0)."\">", "value=\"".intval(isset($cfg["monstro"]) ? $cfg["monstro"] : 0)."\" selected=\"selected\">", $opmon0)."</select> ".t("Chance da missão (%)")." <input type=\"number\" name=\"cfg_chance\" value=\"".intval(isset($cfg["chance"]) ? $cfg["chance"] : 0)."\" style=\"width:60px\" /> <span class=\"small\">".t("Conta os drops com esse nome obtidos depois de aceitar; a chance (0-100) é uma sorte extra a cada vitória sobre o inimigo escolhido.")."</span></td></tr>";
    $f .= "<tr id=\"l_nivel\"><td>".t("Nível mínimo do inimigo")."</td><td><input type=\"number\" name=\"cfg_nivel\" value=\"".intval(isset($cfg["nivel"]) ? $cfg["nivel"] : 0)."\" style=\"width:60px\" /> <span class=\"small\">".t("Vitórias seguidas sem descansar, ganhar HP (pousada, item, jutsu médico) ou morrer. 0 = qualquer inimigo.")."</span></td></tr>";
    $etapas = (isset($cfg["etapas"]) && is_array($cfg["etapas"])) ? array_values($cfg["etapas"]) : array();
    $cad = "<table class=\"lista\"><tr><th>#</th><th>".t("Tipo")."</th><th>".t("Alvo")."</th><th>".t("Qtd.")."</th><th>".t("NPC que recebe / inimigo / nível")."</th><th>".t("Texto (pt / inglês)")."</th></tr>";
    $opnpc0 = array(0 => t("Nenhum")) + array_slice($opnpcs, 1, null, true);
    for ($k = 1; $k <= 6; $k++) {
        $e = isset($etapas[$k - 1]) ? $etapas[$k - 1] : array();
        $ev = function ($c, $p = "") use ($e) { return isset($e[$c]) ? $e[$c] : $p; };
        $extra = ($ev("tipo") == "sobreviver") ? intval($ev("nivel", 0)) : intval($ev("monstro", 0));
        $cad .= "<tr><td>$k</td><td><select name=\"etapa{$k}_tipo\" onchange=\"amdica($k)\" id=\"etapa{$k}_tipo\"><option value=\"\">-</option>".amopcoes($tiposetapa, $ev("tipo"))."</select></td><td><input type=\"text\" name=\"etapa{$k}_alvo\" id=\"etapa{$k}_alvo\" value=\"".amattr($ev("alvo"))."\" size=\"18\" list=\"listaitens\" /></td><td><input type=\"number\" name=\"etapa{$k}_qtd\" value=\"".intval($ev("quantidade", 1))."\" style=\"width:55px\" /></td><td><select name=\"etapa{$k}_npc\">".amopcoes($opnpc0, intval($ev("npc", 0)))."</select> <input type=\"number\" name=\"etapa{$k}_monstro\" value=\"$extra\" style=\"width:55px\" title=\"".t("Coletar: ID do inimigo (0 = qualquer). Sobreviver: nível mínimo do inimigo.")."\" /> % <input type=\"number\" name=\"etapa{$k}_chance\" value=\"".intval($ev("chance", 0))."\" style=\"width:45px\" title=\"".t("Chance da missão (%)")."\" /></td><td><input type=\"text\" name=\"etapa{$k}_texto\" value=\"".amattr($ev("texto"))."\" size=\"24\" /><br /><input type=\"text\" name=\"etapa{$k}_texto_en\" value=\"".amattr($ev("texto_en"))."\" size=\"24\" /></td></tr>";
    }
    $cad .= "</table><span class=\"small\" id=\"dicaetapa\">".t("Alvo de cada tipo: Falar com NPC = ID do NPC; Derrotar inimigo = ID do inimigo; Visitar local = latitude,longitude; Coletar / Entregar item = nome exato do item; Pagar Ryou e Sobreviver = vazio. Entregar item no meio da cadeia precisa do NPC que recebe; Pagar Ryou só na última etapa.")."</span>";
    $f .= "<tr id=\"l_cadeia\"><td>".t("Etapas da cadeia")."<br /><span class=\"small\">".t("Em ordem; deixe o tipo vazio nas que não usar (2 a 6 etapas).")."</span></td><td>$cad</td></tr>";
    $f .= "<tr id=\"l_gancho\"><td>".t("Regra especial (gancho)")."</td><td><select name=\"gancho\">".amopcoes($ganchos, $r["gancho"])."</select> <span class=\"small\">".t("Regras programadas em mx_ganchos() (funcoesmissoesextras.php).")."</span></td></tr>";
    $f .= "<tr id=\"l_qtd\"><td><b>".t("Quantidade")."</b></td><td><input type=\"number\" name=\"quantidade\" value=\"".intval($r["quantidade"])."\" style=\"width:90px\" /> <span class=\"small\" id=\"dicaqtd\"></span></td></tr>";
    $f .= "<tr id=\"l_intervalo\"><td>".t("Intervalo entre repetições (minutos)")."</td><td><input type=\"number\" name=\"intervalo_min\" value=\"".intval($r["intervalo_min"])."\" style=\"width:70px\" /></td></tr>";
    $f .= "<tr><td><b>".t("Recompensa")."</b></td><td>".t("Experiência")." <input type=\"number\" name=\"recompensa_exp\" value=\"".intval($r["recompensa_exp"])."\" style=\"width:80px\" /> ".t("Ryou")." <input type=\"number\" name=\"recompensa_ryou\" value=\"".intval($r["recompensa_ryou"])."\" style=\"width:80px\" /> ".t("Item")." <input type=\"text\" name=\"recompensa_item\" value=\"".amattr($r["recompensa_item"])."\" size=\"26\" list=\"listaitens\" /></td></tr>";
    // Jutsu de recompensa: lista dos jutsus (com nível mínimo e se existe pergaminho que o ensina).
    $opjutsus = array(0 => t("Nenhum"));
    $pergs = jutsupergaminhos();
    $qj = doquery("SELECT id, name, nivelminimo FROM {{table}} ORDER BY id", "spells");
    while ($j = mysqli_fetch_array($qj)) { $opjutsus[intval($j["id"])] = "#".intval($j["id"])." ".t($j["name"])." (".tf("nível %s", intval($j["nivelminimo"])).(isset($pergs[intval($j["id"])]) ? ", ".t("tem pergaminho") : "").")"; }
    $f .= "<tr class=\"soextra\"><td>".t("Jutsu de recompensa")."</td><td><select name=\"recompensa_jutsu\">".amopcoes($opjutsus, intval(isset($r["recompensa_jutsu"]) ? $r["recompensa_jutsu"] : 0))."</select><br /><span class=\"small\">".t("Na entrega: ensina o jutsu se o jogador cumpre os requisitos; se já conhece (ou ainda não pode aprender), recebe o pergaminho dele na mochila (ou no banco, se a mochila estiver cheia); se o jutsu não tem pergaminho (exclusivo de missão), recebe Ryou e experiência no lugar (Ryou = 200 × nível mínimo do jutsu, no mínimo 500; experiência = 50 × nível mínimo).")."</span></td></tr>";
    $opav = array(0 => t("Nenhum")); $qav = doquery("SELECT id,nome,modo FROM {{table}} ORDER BY ordem,id", "avatares");
    while ($av = mysqli_fetch_array($qav)) { $opav[intval($av["id"])] = "#".intval($av["id"])." ".t($av["nome"]).($av["modo"] != "missao" ? " (".$av["modo"].")" : ""); }
    $f .= "<tr class=\"soextra\"><td>".t("Avatar de recompensa")."</td><td><select name=\"recompensa_avatar\">".amopcoes($opav, intval(isset($r["recompensa_avatar"]) ? $r["recompensa_avatar"] : 0))."</select><br /><span class=\"small\">".t("Na entrega o avatar é liberado na tela de avatares; se o jogador já o tiver, ganha 300 Ryou por nível mínimo da missão. Configure os avatares em <a href=\"admin_avatares.php\">Avatares</a>.")."</span></td></tr>";
    $f .= "<tr class=\"soextra\"><td>".t("Repetível")."</td><td><select name=\"repetivel\">".amopcoes(array(0 => t("Não"), 1 => t("Sim")), intval($r["repetivel"]))."</select> ".t("a cada")." <input type=\"number\" name=\"intervalo_horas\" id=\"intervalo_horas\" value=\"".intval($r["intervalo_horas"])."\" style=\"width:60px\" /> ".t("hora(s)")." <a href=\"javascript:document.getElementById('intervalo_horas').value=24;void(0)\">".t("Diária (24)")."</a> | <a href=\"javascript:document.getElementById('intervalo_horas').value=168;void(0)\">".t("Semanal (168)")."</a></td></tr>";
    $f .= "<tr class=\"soextra\"><td>".t("Limite de tempo (minutos)")."</td><td><input type=\"number\" name=\"limite_min\" value=\"".intval($r["limite_min"])."\" style=\"width:70px\" /> <span class=\"small\">".t("Missão cronometrada: o tempo conta a partir do aceite; se acabar, a missão falha e pode ser aceita de novo. 0 = sem limite.")."</span></td></tr>";
    $ops = (isset($cfg["opcoes"]) && is_array($cfg["opcoes"])) ? array_values($cfg["opcoes"]) : array();
    $ophtml = "";
    for ($k = 0; $k <= 1; $k++) {
        $o = isset($ops[$k]) ? $ops[$k] : array();
        $ov = function ($c, $p = "") use ($o) { return isset($o[$c]) ? $o[$c] : $p; };
        $ophtml .= "<b>".tf("Opção %s", $k == 0 ? "A" : "B")."</b> ".t("Botão:")." <input type=\"text\" name=\"op{$k}_rotulo\" value=\"".amattr($ov("rotulo"))."\" size=\"22\" maxlength=\"60\" /> ".t("inglês:")." <input type=\"text\" name=\"op{$k}_rotulo_en\" value=\"".amattr($ov("rotulo_en"))."\" size=\"22\" maxlength=\"60\" /><br />".t("Desfecho:")." <input type=\"text\" name=\"op{$k}_texto\" value=\"".amattr($ov("texto"))."\" size=\"40\" /> ".t("inglês:")." <input type=\"text\" name=\"op{$k}_texto_en\" value=\"".amattr($ov("texto_en"))."\" size=\"40\" /><br />".t("Experiência")." <input type=\"number\" name=\"op{$k}_exp\" value=\"".intval($ov("exp", 0))."\" style=\"width:70px\" /> ".t("Ryou")." <input type=\"number\" name=\"op{$k}_ryou\" value=\"".intval($ov("ryou", 0))."\" style=\"width:70px\" /> ".t("Item")." <input type=\"text\" name=\"op{$k}_item\" value=\"".amattr($ov("item"))."\" size=\"20\" list=\"listaitens\" /><br /><br />";
    }
    $f .= "<tr class=\"soextra\"><td>".t("Escolha (duas opções)")."<br /><span class=\"small\">".t("Obrigatória no tipo Escolha; opcional nos outros (vazio = sem escolha). A recompensa da opção soma com a da missão.")."</span></td><td>$ophtml</td></tr>";
    $opreq = array(0 => t("Nenhuma"));
    $qq = doquery("SELECT id, titulo FROM {{table}} WHERE categoria='extra' ORDER BY id", "missoesextras");
    while ($mm = mysqli_fetch_array($qq)) { if (intval($mm["id"]) != $id) { $opreq[intval($mm["id"])] = "#".intval($mm["id"])." ".$mm["titulo"]; } }
    $optroca = array(0 => t("Nenhuma (missão comum)"));
    foreach ($vilas as $vid => $vnome) { if (mx_vilaparatroca($vid)) { $optroca[$vid] = t($vnome); } }
    $f .= "<tr class=\"soextra\"><td>".t("Troca de vila natal")."</td><td><select name=\"cfg_troca_vila\">".amopcoes($optroca, intval(isset($cfg["troca_vila"]) ? $cfg["troca_vila"] : 0))."</select> <span class=\"small\">".t("Na entrega, a vila natal do jogador passa a ser esta (com o tempo de espera de Configurações gerais).")."</span></td></tr>";
    global $controlrow;
    $opdif = array(0 => t("Nenhuma (missão comum)"), 1 => t($controlrow["diff1name"]), 2 => t($controlrow["diff2name"]), 3 => t($controlrow["diff3name"]));
    $f .= "<tr class=\"soextra\"><td>".t("Troca de dificuldade")."</td><td><select name=\"cfg_troca_dificuldade\">".amopcoes($opdif, intval(isset($cfg["troca_dificuldade"]) ? $cfg["troca_dificuldade"] : 0))."</select> <span class=\"small\">".t("Na entrega, a dificuldade do jogador passa a ser esta (com o tempo de espera de Configurações gerais).")."</span></td></tr>";
    $f .= "<tr class=\"soextra\"><td>".t("Só aparece depois de")."</td><td><select name=\"cfg_requer\">".amopcoes($opreq, intval(isset($cfg["requer"]) ? $cfg["requer"] : 0))."</select> ".t("com a opção:")." <select name=\"cfg_requer_opcao\">".amopcoes(array("" => t("Qualquer"), "0" => "A", "1" => "B"), isset($cfg["requer_opcao"]) ? (string)$cfg["requer_opcao"] : "")."</select> <span class=\"small\">".t("Para continuar uma história conforme a escolha do jogador.")."</span></td></tr>";
    $f .= "<tr class=\"soextra\"><td>".t("Ativa")."</td><td><select name=\"ativa\">".amopcoes(array(1 => t("Sim"), 0 => t("Não")), intval($r["ativa"]))."</select> ".t("Ordem na lista:")." <input type=\"number\" name=\"ordem\" value=\"".intval($r["ordem"])."\" style=\"width:60px\" /></td></tr>";
    $f .= "<tr><td></td><td><input type=\"submit\" name=\"salvar\" value=\"".t("Salvar")."\" /> <a href=\"admin_missoes.php\">".t("Cancelar")."</a></td></tr></table><datalist id=\"listaitens\">$itens</datalist></form>";

    // JavaScript: mostra só o campo de alvo do tipo escolhido, os campos da categoria e atualiza a prévia do NPC.
    $dicas = array("entregar_item" => t("Quantos itens (1 a 4)."), "derrotar_monstro" => t("Extras: vitórias depois de aceitar. História: vitórias seguidas (último inimigo derrotado)."), "pagar_ryou" => t("Ryou cobrados na entrega."), "repetir" => t("Quantas repetições."), "visitar_local" => "", "falar_com_npc" => "", "especial" => "", "coletar" => t("Quantos drops coletar."), "sobreviver" => t("Quantas vitórias seguidas."), "viagem" => "", "escolha" => "", "cadeia" => "");
    $f .= "<script type=\"text/javascript\">
var dicas = {".implode(",", array_map(function ($k, $v) { return "'".$k."':'".textojs($v)."'"; }, array_keys($dicas), $dicas))."};
function mostrar(id, sim) { var e = document.getElementById(id); if (e) { e.style.display = sim ? '' : 'none'; } }
function amtipo() {
  var tipo = document.getElementById('tipo').value, hist = document.getElementById('categoria').value == 'historia';
  mostrar('l_item', tipo == 'entregar_item' || tipo == 'coletar'); mostrar('l_destino', tipo == 'viagem'); mostrar('l_coleta', tipo == 'coletar'); mostrar('l_nivel', tipo == 'sobreviver'); mostrar('l_cadeia', tipo == 'cadeia' && !hist); mostrar('l_monstro', tipo == 'derrotar_monstro'); mostrar('l_local', tipo == 'visitar_local');
  mostrar('l_npc', tipo == 'falar_com_npc' && !hist); mostrar('l_gancho', tipo == 'especial'); mostrar('l_intervalo', tipo == 'repetir');
  mostrar('l_qtd', tipo != 'visitar_local' && tipo != 'falar_com_npc' && tipo != 'especial' && tipo != 'viagem' && tipo != 'escolha' && tipo != 'cadeia');
  document.getElementById('dicaqtd').innerHTML = dicas[tipo] || '';
  var linhas = document.getElementsByTagName('tr');
  for (var i = 0; i < linhas.length; i++) { if (linhas[i].className == 'sohist') { linhas[i].style.display = hist ? '' : 'none'; } if (linhas[i].className == 'soextra') { linhas[i].style.display = hist ? 'none' : ''; } }
}
function amprevia() {
  var r = document.getElementsByName('npc_avatar'), a = '';
  for (var i = 0; i < r.length; i++) { if (r[i].checked) { a = r[i].value; } }
  if (a != '') { document.getElementById('previaimg').src = 'layoutnovo/personagem/' + a + '.png'; }
  document.getElementById('previanome').textContent = document.getElementById('npc_nome').value || 'NPC';
  document.getElementById('previatexto').textContent = document.getElementById('descricao').value;
}
function amdica(k) { var tp = document.getElementById('etapa' + k + '_tipo').value, a = document.getElementById('etapa' + k + '_alvo'); a.placeholder = {'falar_com_npc':'ID NPC','derrotar_monstro':'ID','visitar_local':'lat,lon','coletar':'drop','entregar_item':'item'}[tp] || ''; }
for (var k = 1; k <= 6; k++) { amdica(k); }
amtipo();
</script>";
    admindisplay($page.$f, $titulo);

}


/* ------------------------------------------------------------------------
   NPCs do mapa.
   ------------------------------------------------------------------------ */

function amnpcs() {

    $mensagem = "";
    if (pegartexto($_POST, "acao") == "apagar") {
        $nid = pegarint($_POST, "id");
        $q = doquery("SELECT id, titulo FROM {{table}} WHERE npc_id='$nid' OR (tipo='falar_com_npc' AND alvo='$nid')", "missoesextras");
        if (mysqli_num_rows($q) > 0) {
            $lista = array(); while ($r = mysqli_fetch_array($q)) { $lista[] = "#".intval($r["id"])." ".$r["titulo"]; }
            $mensagem = ammsg(tf("Esse NPC é usado pelas missões: %s. Troque o NPC delas antes de apagar.", implode(", ", $lista)), "erro");
        } else {
            doquery("DELETE FROM {{table}} WHERE id='$nid' LIMIT 1", "npcs");
            $mensagem = ammsg(t("NPC apagado."));
        }
    }
    $usos = array();
    $q = doquery("SELECT npc_id, COUNT(*) AS n FROM {{table}} WHERE npc_id>0 GROUP BY npc_id", "missoesextras");
    while ($r = mysqli_fetch_array($q)) { $usos[intval($r["npc_id"])] = intval($r["n"]); }

    $page = "<h1>".t("NPCs do mapa")."</h1>".amabas("n").$mensagem;
    $page .= "<p>".t("Os NPCs aparecem no mini mapa e no mapa-múndi; o jogador conversa com eles pelo botão \"Conversar\" ao pisar na casa do NPC. NPCs usados por missões da história só aparecem para quem está na etapa dessa missão.")."</p>";
    $page .= "<a href=\"admin_missoes.php?do=editarnpc:0\" class=\"botao\">".t("+ Adicionar NPC")."</a><br /><br />";
    $page .= "<table class=\"lista\"><tr><th>".t("ID")."</th><th>".t("Ícone")."</th><th>".t("Avatar")."</th><th>".t("Nome")."</th><th>".t("Coordenada")."</th><th>".t("Missões")."</th><th>".t("Situação")."</th><th></th></tr>";
    $csrf = csrf_campo();
    foreach (amtodosnpcs() as $nid => $n) {
        $apagar = "<form action=\"admin_missoes.php?do=npcs\" method=\"post\" style=\"margin:0\" onsubmit=\"return confirm('".textojs(t("Apagar este NPC?"))."')\">$csrf<input type=\"hidden\" name=\"id\" value=\"$nid\" /><button type=\"submit\" name=\"acao\" value=\"apagar\" class=\"botaoperigo\">".t("Apagar")."</button></form>";
        $page .= "<tr><td>$nid</td><td><img src=\"images/24/".amattr($n["icone_mapa"]).".gif\" alt=\"\" /></td><td><img src=\"layoutnovo/personagem/".amattr($n["avatar"]).".png\" height=\"40\" alt=\"\" /></td><td><a href=\"admin_missoes.php?do=editarnpc:$nid\">".$n["nome"]."</a></td><td>".intval($n["latitude"]).", ".intval($n["longitude"])."</td><td>".(isset($usos[$nid]) ? $usos[$nid] : 0)."</td><td>".($n["ativo"] ? t("Ativo") : "<span class=\"highlight\">".t("Desativado")."</span>").($n["papel"] == "comprador_pergaminhos" ? "<br /><span class=\"small\">".t("Comprador de pergaminhos")."</span>" : "")."</td><td>$apagar</td></tr>";
    }
    $page .= "</table>";
    admindisplay($page, t("NPCs do mapa"));

}

function amEditarnpc($id) {

    global $controlrow, $link;
    $mensagem = ""; $erros = "";
    $tamanho = intval($controlrow["gamesize"]);
    $avatares = amavatares(); $icones = amicones();
    if ($id > 0) {
        $q = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "npcs");
        $r = mysqli_fetch_array($q, MYSQLI_ASSOC);
        if (!$r) { admindisplay(ammsg(t("NPC não encontrado."), "erro").amabas("n"), t("NPCs do mapa")); }
    } else {
        $r = array("nome" => "", "avatar" => "personagem1", "icone_mapa" => "arvore_personagem1", "latitude" => 10, "longitude" => 10, "fala" => "", "fala_en" => "", "ativo" => 1, "papel" => "");
    }
    // Papel especial do NPC (npcs.papel): "" = só conversa/missões; "comprador_pergaminhos" = comprador de pergaminhos distante.
    $papeis = array("" => t("Nenhum (conversa e missões)"), "comprador_pergaminhos" => t("Comprador de pergaminhos (paga mais)"));
    if (isset($_POST["salvar"])) {
        $n = array();
        $n["nome"] = amtextorico("nome", 50, t("Nome"), $erros, true);
        $n["avatar"] = pegartexto($_POST, "avatar");
        if (!in_array($n["avatar"], $avatares, true)) { $erros .= t("Escolha um avatar da lista.")."<br />"; }
        $n["icone_mapa"] = pegartexto($_POST, "icone_mapa");
        if (!in_array($n["icone_mapa"], $icones, true)) { $erros .= t("Escolha um ícone da lista.")."<br />"; }
        $n["latitude"] = amnum("latitude", t("Latitude"), $erros, -$tamanho, $tamanho);
        $n["longitude"] = amnum("longitude", t("Longitude"), $erros, -$tamanho, $tamanho);
        $qt = doquery("SELECT name FROM {{table}} WHERE latitude='".intval($n["latitude"])."' AND longitude='".intval($n["longitude"])."' LIMIT 1", "towns");
        if ($t = mysqli_fetch_array($qt)) { $erros .= tf("Essa coordenada é a vila %s: escolha outra casa.", t($t["name"]))."<br />"; }
        $qn = doquery("SELECT nome FROM {{table}} WHERE latitude='".intval($n["latitude"])."' AND longitude='".intval($n["longitude"])."' AND id<>'".intval($id)."' LIMIT 1", "npcs");
        if ($o = mysqli_fetch_array($qn)) { $erros .= tf("Já existe o NPC %s nessa coordenada.", $o["nome"])."<br />"; }
        $n["fala"] = amtextorico("fala", 2000, t("Fala"), $erros, true);
        $n["fala_en"] = amtextorico("fala_en", 2000, t("Fala (inglês)"), $erros, false);
        $n["ativo"] = (pegarint($_POST, "ativo") == 1) ? 1 : 0;
        $n["papel"] = pegartexto($_POST, "papel");
        if (!array_key_exists($n["papel"], $papeis)) { $erros .= t("Papel inválido.")."<br />"; $n["papel"] = ""; }
        if ($erros == "") {
            $sets = array(); foreach ($n as $c => $v) { $sets[] = "`$c`='".addslashes((string)$v)."'"; }
            if ($id > 0) { doquery("UPDATE {{table}} SET ".implode(", ", $sets)." WHERE id='$id' LIMIT 1", "npcs"); $mensagem = ammsg(t("NPC salvo.")." <a href=\"admin_missoes.php?do=npcs\">".t("Voltar para a lista")."</a>"); }
            else { doquery("INSERT INTO {{table}} SET ".implode(", ", $sets), "npcs"); $id = mysqli_insert_id($link); $mensagem = ammsg(tf("NPC criado com o ID %s.", $id)." <a href=\"admin_missoes.php?do=npcs\">".t("Voltar para a lista")."</a>"); }
            $r = $n;
        } else {
            $mensagem = ammsg("<b>".t("Corrija os erros abaixo:")."</b><br />".$erros, "erro");
            $r = array_merge($r, $n);
        }
    }
    $avatarhtml = "";
    foreach ($avatares as $a) { $avatarhtml .= "<label style=\"display:inline-block;text-align:center;margin:2px;cursor:pointer\"><img src=\"layoutnovo/personagem/".amattr($a).".png\" height=\"48\" alt=\"\" style=\"border:1px solid #c9b47a;background:#fff1c7\" /><br /><input type=\"radio\" name=\"avatar\" value=\"".amattr($a)."\"".($a === $r["avatar"] ? " checked=\"checked\"" : "")." /> <span class=\"small\">".amattr($a)."</span></label>"; }
    $iconehtml = "";
    foreach ($icones as $a) { $iconehtml .= "<label style=\"display:inline-block;text-align:center;margin:2px;cursor:pointer\"><img src=\"images/24/".amattr($a).".gif\" alt=\"\" /><br /><input type=\"radio\" name=\"icone_mapa\" value=\"".amattr($a)."\"".($a === $r["icone_mapa"] ? " checked=\"checked\"" : "")." /></label>"; }
    $titulo = ($id > 0) ? tf("Editar NPC #%s", $id) : t("Novo NPC");
    $page = "<h1>$titulo</h1>".amabas("n").$mensagem;
    $page .= "<form action=\"admin_missoes.php?do=editarnpc:$id\" method=\"post\">".csrf_campo()."<table>";
    $page .= "<tr><td width=\"160\"><b>".t("Nome")."</b></td><td><input type=\"text\" name=\"nome\" value=\"".amattr($r["nome"])."\" size=\"30\" maxlength=\"50\" /></td></tr>";
    $page .= "<tr><td><b>".t("Avatar")."</b></td><td>$avatarhtml</td></tr>";
    $page .= "<tr><td><b>".t("Ícone no mapa")."</b></td><td>$iconehtml</td></tr>";
    $page .= "<tr><td><b>".t("Coordenada")."</b></td><td>".t("Latitude")." <input type=\"number\" name=\"latitude\" value=\"".intval($r["latitude"])."\" style=\"width:70px\" /> ".t("Longitude")." <input type=\"number\" name=\"longitude\" value=\"".intval($r["longitude"])."\" style=\"width:70px\" /> <span class=\"small\">".tf("De -%s a %s; não pode ser uma vila nem a casa de outro NPC.", $tamanho, $tamanho)."</span></td></tr>";
    $page .= "<tr><td><b>".t("Fala")."</b><br /><span class=\"small\">".t("%s = nome do jogador.")."</span></td><td><textarea name=\"fala\" rows=\"4\" cols=\"70\">".amattr($r["fala"])."</textarea></td></tr>";
    $page .= "<tr><td>".t("Fala (inglês)")."</td><td><textarea name=\"fala_en\" rows=\"4\" cols=\"70\">".amattr($r["fala_en"])."</textarea></td></tr>";
    $page .= "<tr><td>".t("Ativo")."</td><td><select name=\"ativo\">".amopcoes(array(1 => t("Sim"), 0 => t("Não")), intval($r["ativo"]))."</select></td></tr>";
    $page .= "<tr><td>".t("Papel")."</td><td><select name=\"papel\">".amopcoes($papeis, (string)$r["papel"])."</select> <span class=\"small\">".t("O comprador de pergaminhos abre a tela de venda ao conversar; o preço é configurado em Configuração principal.")."</span></td></tr>";
    $page .= "<tr><td></td><td><input type=\"submit\" name=\"salvar\" value=\"".t("Salvar")."\" /> <a href=\"admin_missoes.php?do=npcs\">".t("Cancelar")."</a></td></tr></table></form>";
    admindisplay($page, $titulo);

}

?>
