<?php // alquimia.php :: Alquimia de itens (só nas vilas com tem_alquimia): receitas do banco, experimentar, refinar, infusão de variantes, poções e cópia de pergaminhos.

/*  Os itens da mochila (bp1..bp8) têm o formato "nome,id,tipo,durabilidade[,quantidade]" ("None" = espaço vazio).
    As receitas ficam na tabela receitas (editável em admin.php?do=receitas):
      ingredientes ..... JSON com até 4 linhas {"tipo": "item"|"drop"|"loja", "id": 12|"ret", "qtd": 3};
      resultado_tipo ... "item" (arma/colete/bandana), "drop" (drop, poção de drop ou pergaminho de jutsu), "loja" (consumível
                         da loja: ret/rep), "variante" (infusão: dá a variante resultado_id a um item escolhido) ou
                         "copia" (copia um pergaminho de jutsu escolhido, exceto jutsus exclusivos de missão);
      nivel_minimo ..... nível de ALQUIMIA (users.alquimia_nivel) para ver/usar; nivel_jogador, classe e graduacao opcionais;
      descoberta=1 ..... receita secreta: só aparece depois de descoberta (users.alquimia_receitas = "3,18,") lendo um
                         livro de receitas (drop livro_drop) ou experimentando a combinação certa na aba Experimentar.
    Um ingrediente casa com um espaço da mochila pelo NOME exato (como na alquimia de 2010): itens refinados
    ("Kunai+1") e variantes raras ("Kunai:gelo") não entram. As quantidades somam as pilhas e os espaços repetidos.
    Regras (configuráveis em admin.php?do=main, colunas control.alquimia*):
      chance = chance_sucesso + 3 x (nível de alquimia - nivel_minimo), entre 5% e 95% (receitas de 100% ficam em 100%);
      falha  = perde alquimiaperda% de cada ingrediente (arredondado); nas receitas de nível <= 5 nunca perde tudo;
      Ryou   = o custo é cobrado em toda tentativa (infusões somam alquimiainfusaocusto);
      experiência = (8 + 4 x nivel_minimo) x alquimiaexptaxa% no sucesso, 1/3 na falha; nível sobe a cada 50 x nível;
      crítico = alquimiacritico% de sair o dobro, no máximo uma vez por dia (users.alquimia_critico = AAAAMMDD).
    Tudo acontece numa transação com o jogador travado (FOR UPDATE) e com o "estado" da mochila (hash) enviado
    pelo formulário: se a mochila mudou desde que a página abriu, nada é feito.
*/

include('lib.php');
$link = opendb();

include('cookies.php');
$userrow = checkcookies();
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));

include_once('funcoesinclusas.php'); // pilhas da mochila, ícones, variantes e conteudoexplic() (caixa de explicação do item).

if (!defined('ALQUIMIA_NIVELMAX')) { define('ALQUIMIA_NIVELMAX', 30); }

$do = pegartexto($_GET, "do");
if ($do == "fundir") { fundir(); }
else { header("Location: ./alquimia.php?do=fundir"); die(); }


function alquimiaverificar() { // Logado, fora de batalha/duelo e numa cidade que tem alquimista (coluna towns.tem_alquimia).

    global $userrow;
    if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
    if ($userrow["currentaction"] == "Fighting") { header("Location: ./index.php?do=fight&conteudo=".rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }
    if ($userrow["currentaction"] != "In Town") { header("Location: ./index.php?conteudo=".rawurlencode(t("Você só pode acessar essa função dentro de uma cidade!"))); die(); }
    if ($userrow["batalha_timer2"] == 5) { display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "),t("Erro"),false,false,false); die(); }

    $townquery = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
    if (mysqli_num_rows($townquery) == 0) { display(t("Há um erro com sua conta, ou com os dados da cidade. Por favor tente novamente."),"Error"); die(); }
    $townrow = mysqli_fetch_array($townquery);
    if (intval($townrow["tem_alquimia"]) != 1) { header("Location: index.php?conteudo=".rawurlencode(t("Você não pode usar a alquimia aqui, uma tentativa de trapaça foi detectada!"))); die(); }

}


/* ------------------------------------------------------------------------
   Receitas e ingredientes.
   ------------------------------------------------------------------------ */

function receitasalquimia($todas = false) { // Receitas da tabela receitas (id => linha, com "ing" = ingredientes decodificados). $todas = inclui as desativadas.

    static $cache = null;
    if ($cache === null) {
        $cache = array();
        $q = doquery("SELECT * FROM {{table}} ORDER BY ordem, id", "receitas");
        while ($r = mysqli_fetch_array($q, MYSQLI_ASSOC)) {
            $r["ing"] = alquimialeringredientes($r["ingredientes"]);
            $cache[intval($r["id"])] = $r;
        }
    }
    if ($todas) { return $cache; }
    return array_filter($cache, function($r) { return intval($r["ativa"]) == 1; });

}

function alquimialeringredientes($json) { // JSON da coluna ingredientes -> lista de array(tipo, id, qtd) válidos (no máximo 4).

    $lista = array();
    $dados = json_decode(html_entity_decode((string)$json, ENT_QUOTES, "UTF-8"), true);
    if (!is_array($dados)) { return $lista; }
    foreach ($dados as $d) {
        if (!is_array($d) || !isset($d["tipo"], $d["id"])) { continue; }
        $tipo = (string)$d["tipo"];
        if (!in_array($tipo, array("item", "drop", "loja"), true)) { continue; }
        $id = ($tipo == "loja") ? preg_replace("/[^a-z]/", "", (string)$d["id"]) : intval($d["id"]);
        $qtd = max(1, min(99, intval(isset($d["qtd"]) ? $d["qtd"] : 1)));
        $lista[] = array("tipo" => $tipo, "id" => $id, "qtd" => $qtd);
        if (count($lista) == 4) { break; }
    }
    return $lista;

}

function alquimiatexto($tipo, $id) { // Texto de mochila de UMA unidade de um item/drop/consumível ("Kunai,3,1,X"), ou "" se não existir.

    static $cache = array();
    $chave = $tipo.":".$id;
    if (isset($cache[$chave])) { return $cache[$chave]; }
    $texto = "";
    if ($tipo == "item") {
        $r = mysqli_fetch_array(doquery("SELECT id,name,type FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "items"));
        if ($r) { $texto = $r["name"].",".intval($r["id"]).",".intval($r["type"]).",".durabilidadenova(intval($r["type"]), $r["id"]); } // durabilidade cheia (items.durabilidade) ou X.
    } elseif ($tipo == "drop") {
        $r = mysqli_fetch_array(doquery("SELECT id,name,consumivel,ensina_jutsu FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "drops"));
        if ($r) {
            $cons = array_pad(explode(",", (string)$r["consumivel"]), 2, 0);
            // Mesmo formato do fight.php :: drop(): poção de drop "nome,hp,50,1", pergaminho "nome,jutsu,ID,1", resto "nome,id,4,X".
            if (in_array($cons[0], array("hp", "mp", "tp", "np", "ep"), true) && intval($cons[1]) > 0) { $texto = $r["name"].",".$cons[0].",".intval($cons[1]).",1"; }
            elseif (intval($r["ensina_jutsu"]) > 0) { $texto = $r["name"].",jutsu,".intval($r["ensina_jutsu"]).",1"; }
            else { $texto = $r["name"].",".intval($r["id"]).",4,".durabilidadenova(4, $r["id"]); } // durabilidade cheia (drops.durabilidade) ou X.
        }
    } elseif ($tipo == "loja") {
        $loja = consumiveisdaloja();
        if (isset($loja[$id])) { $texto = $loja[$id][0].",".$id.",".intval($loja[$id][2]).",X"; }
    }
    $cache[$chave] = $texto;
    return $texto;

}

function alquimiacasa($slot, $ing) { // O espaço da mochila ($slot = texto) serve como o ingrediente $ing? Compara o nome exato e o tipo.

    if ($slot == "None" || $slot == "") { return false; }
    $modelo = alquimiatexto($ing["tipo"], $ing["id"]);
    if ($modelo == "") { return false; }
    $m = explode(",", $modelo);
    $s = array_pad(explode(",", (string)$slot), 4, "X");
    if ($s[0] !== $m[0]) { return false; } // nome exato: refino "+N" e variante ":efeito" mudam o nome.
    if (is_numeric($m[1])) {
        if (!is_numeric($s[1])) { return false; }
        return ($ing["tipo"] == "item") ? (intval($s[2]) >= 1 && intval($s[2]) <= 3) : (intval($s[2]) >= 4);
    }
    return ($s[1] === $m[1] && intval($s[2]) == intval($m[2])); // consumível: mesmo código e valor.

}

function alquimiatenho($row, $ing) { // Quantas unidades do ingrediente o jogador tem na mochila.

    $total = 0;
    for ($j = 1; $j <= 8; $j++) { if (alquimiacasa($row["bp".$j], $ing)) { $total += qtditem($row["bp".$j]); } }
    return $total;

}

function alquimiatirar(&$row, $ing, $qtd) { // Retira $qtd unidades do ingrediente da mochila de $row (dos últimos espaços para os primeiros). Devolve os espaços alterados.

    $mudou = array();
    for ($j = 8; $j >= 1 && $qtd > 0; $j--) {
        if (!alquimiacasa($row["bp".$j], $ing)) { continue; }
        $tem = qtditem($row["bp".$j]);
        $tira = min($tem, $qtd);
        $row["bp".$j] = itemcomqtd($row["bp".$j], $tem - $tira);
        $qtd -= $tira; $mudou[] = "bp".$j;
    }
    return $mudou;

}

function alquimiaestado($row) { // "Assinatura" da mochila: o formulário leva esse valor e a ação só vale se a mochila não mudou.

    $texto = "";
    for ($j = 1; $j <= 8; $j++) { $texto .= (string)$row["bp".$j]."|"; }
    return md5($texto);

}

function alquimiaconhecidas($row) { // Receitas secretas já descobertas pelo jogador (users.alquimia_receitas = "3,18,").

    $lista = array();
    foreach (explode(",", (string)$row["alquimia_receitas"]) as $id) { if (intval($id) > 0) { $lista[intval($id)] = true; } }
    return $lista;

}

function alquimiavisivel($receita, $row) { // A receita aparece para o jogador? (ativa e não secreta, ou secreta já descoberta)

    if (intval($receita["ativa"]) != 1) { return false; }
    if (intval($receita["descoberta"]) == 0) { return true; }
    return isset(alquimiaconhecidas($row)[intval($receita["id"])]);

}

function alquimiachance($receita, $row) { // Chance de sucesso (%) com o nível de alquimia do jogador.

    $base = intval($receita["chance_sucesso"]);
    if ($base >= 100) { return 100; }
    $chance = $base + 3 * (intval($row["alquimia_nivel"]) - intval($receita["nivel_minimo"]));
    return max(5, min(95, $chance));

}

function alquimiacusto($receita) { // Ryou cobrado por tentativa (infusões somam o custo geral das infusões).

    global $controlrow;
    $custo = intval($receita["custo"]);
    if ($receita["resultado_tipo"] == "variante") { $custo += intval($controlrow["alquimiainfusaocusto"]); }
    return $custo;

}

function alquimiaexpnecessaria($nivel) { // Experiência de alquimia para passar do nível $nivel para o próximo.

    return 50 * max(1, intval($nivel));

}

function alquimiarequisitos($receita, $row) { // Erro (traduzido) se o jogador não pode fazer a receita agora (fora os ingredientes), ou "".

    if (intval($row["alquimia_nivel"]) < intval($receita["nivel_minimo"])) { return tf("Precisa de alquimia nível %s.", intval($receita["nivel_minimo"])); }
    if (intval($receita["nivel_jogador"]) > intval($row["level"])) { return tf("Precisa ser nível %s.", intval($receita["nivel_jogador"])); }
    if (intval($receita["classe"]) >= 1 && intval($receita["classe"]) <= 3 && intval($receita["classe"]) != intval($row["charclass"])) { return tf("Só para ninjas de %s.", nomedaclasse($receita["classe"])); }
    if (trim((string)$receita["graduacao"]) != "") {
        $ordem = jutsugraduacoes();
        $precisa = array_search($receita["graduacao"], $ordem);
        $tem = array_search((string)$row["graduacao"], $ordem);
        if ($precisa !== false && ($tem === false || $tem < $precisa)) { return tf("Precisa ser %s.", t($receita["graduacao"])); }
    }
    if ($receita["resultado_tipo"] == "variante") {
        $v = variantesitens();
        if (!isset($v[$receita["resultado_id"]]) || intval($v[$receita["resultado_id"]]["ativo"]) != 1) { return t("Esta variante está desativada."); }
    }
    if (in_array($receita["resultado_tipo"], array("item", "drop", "loja"), true) && alquimiatexto($receita["resultado_tipo"], $receita["resultado_id"]) == "") { return t("Receita com resultado inválido."); }
    return "";

}

function alquimiaalvos($receita, $row) { // Espaços da mochila que podem receber a infusão / ser copiados: array(espaço => texto).

    $alvos = array();
    if (!in_array($receita["resultado_tipo"], array("variante", "copia"), true)) { return $alvos; }
    for ($j = 1; $j <= 8; $j++) {
        $s = (string)$row["bp".$j];
        if ($s == "None") { continue; }
        $p = array_pad(explode(",", $s), 4, "X");
        if ($receita["resultado_tipo"] == "copia") {
            // Pergaminho de jutsu ("nome,jutsu,ID,1"): não copia jutsu exclusivo de missão.
            if ($p[1] != "jutsu") { continue; }
            $sp = mysqli_fetch_array(doquery("SELECT exclusivo_missao FROM {{table}} WHERE id='".intval($p[2])."' LIMIT 1", "spells"));
            if ($sp && intval($sp["exclusivo_missao"]) == 0) { $alvos[$j] = $s; }
            continue;
        }
        // Infusão: arma/colete/bandana ou drop com bônus, sem variante e fora de pilha.
        if (!is_numeric($p[1]) || variantedoitem($p[0]) != "" || qtditem($s) > 1) { continue; }
        if (intval($p[2]) >= 1 && intval($p[2]) <= 3) { $alvos[$j] = $s; continue; }
        $d = mysqli_fetch_array(doquery("SELECT attribute1,attribute2,consumivel,ensina_jutsu FROM {{table}} WHERE id='".intval($p[1])."' LIMIT 1", "drops"));
        if ($d && $d["consumivel"] == "" && intval($d["ensina_jutsu"]) == 0 && ($d["attribute1"] != "X" || $d["attribute2"] != "X")) { $alvos[$j] = $s; }
    }
    return $alvos;

}

function alquimiaresultado($receita, $qtd) { // Texto de mochila do resultado com a quantidade (receitas de item/drop/loja).

    $texto = alquimiatexto($receita["resultado_tipo"], $receita["resultado_id"]);
    if ($texto == "") { return ""; }
    // Itens criados pela alquimia saem com a durabilidade cheia do item (items/drops.durabilidade; 0 = infinita "X").
    return itemcomqtd($texto, $qtd);

}


/* ------------------------------------------------------------------------
   A ação (com o jogador já travado por quem chama).
   ------------------------------------------------------------------------ */

function alquimiaexecutar(&$row, $receita, $alvo = 0) { // Tenta a receita: cobra, sorteia, consome e produz. Grava no banco. Devolve array(mensagem, texto do resultado ou "", sucesso).

    global $controlrow;
    $erro = alquimiarequisitos($receita, $row);
    if ($erro != "") { return array($erro, "", false); }
    $custo = alquimiacusto($receita);
    if (intval($row["gold"]) < $custo) { return array(tf("Você precisa de %s Ryou para tentar esta receita.", $custo), "", false); }
    foreach ($receita["ing"] as $ing) {
        if (alquimiatenho($row, $ing) < $ing["qtd"]) { return array(t("Você não tem todos os ingredientes."), "", false); }
    }
    if (count($receita["ing"]) == 0) { return array(t("Receita com resultado inválido."), "", false); }

    // Alvo da infusão/cópia: precisa continuar na lista de alvos válidos (e não pode ser um dos ingredientes).
    $textoalvo = "";
    if (in_array($receita["resultado_tipo"], array("variante", "copia"), true)) {
        $alvos = alquimiaalvos($receita, $row);
        if (!isset($alvos[$alvo])) { return array(t("Escolha um item válido da mochila."), "", false); }
        $textoalvo = $alvos[$alvo];
        foreach ($receita["ing"] as $ing) { if (alquimiacasa($textoalvo, $ing)) { return array(t("Escolha um item válido da mochila."), "", false); } }
    }

    $antes = $row;
    $chance = alquimiachance($receita, $row);
    $sucesso = (rand(1, 100) <= $chance);
    $mudou = array();
    $resultado = ""; $critico = false;
    $hoje = intval(date("Ymd"));

    if ($sucesso) {
        foreach ($receita["ing"] as $ing) { $mudou = array_merge($mudou, alquimiatirar($row, $ing, $ing["qtd"])); }
        if ($receita["resultado_tipo"] == "variante") {
            // A variante vai no nome do item ("Kunai:gelo"); bônus aplicados quando ele for equipado (bonusvariante()).
            $p = explode(",", $textoalvo);
            $p[0] = nomecomvariante($p[0], $receita["resultado_id"]);
            $row["bp".$alvo] = implode(",", $p);
            $mudou[] = "bp".$alvo; $resultado = $row["bp".$alvo];
        } else {
            $qtd = intval($receita["qtd"]);
            // Crítico (uma vez por dia): o dobro do resultado. A cópia de pergaminho não dobra.
            if ($receita["resultado_tipo"] != "copia" && intval($row["alquimia_critico"]) != $hoje && rand(1, 10000) <= (int)round(floatval($controlrow["alquimiacritico"]) * 100)) { $critico = true; $qtd *= 2; }
            $resultado = ($receita["resultado_tipo"] == "copia") ? itemcomqtd($textoalvo, 1) : alquimiaresultado($receita, $qtd);
            $espacos = mochilaguardar($row, $resultado);
            if ($espacos === false) { $row = $antes; return array(t("Sua mochila está cheia. Libere um espaço para receber o resultado."), "", false); }
            $mudou = array_merge($mudou, $espacos);
        }
    } else {
        // Falha: perde parte de cada ingrediente. Receitas de nível baixo (até 5) nunca perdem tudo.
        $perda = max(0, min(100, intval($controlrow["alquimiaperda"])));
        foreach ($receita["ing"] as $ing) {
            $perde = (int)round($ing["qtd"] * $perda / 100);
            if (intval($receita["nivel_minimo"]) <= 5) { $perde = min($perde, $ing["qtd"] - 1); }
            if ($perde > 0) { $mudou = array_merge($mudou, alquimiatirar($row, $ing, $perde)); }
        }
    }

    // Experiência e nível de alquimia.
    $ganho = (int)round((8 + 4 * intval($receita["nivel_minimo"])) * max(0, intval($controlrow["alquimiaexptaxa"])) / 100);
    if (!$sucesso) { $ganho = (int)floor($ganho / 3); }
    $nivel = max(1, intval($row["alquimia_nivel"])); $exp = intval($row["alquimia_exp"]) + $ganho; $subiu = false;
    while ($nivel < ALQUIMIA_NIVELMAX && $exp >= alquimiaexpnecessaria($nivel)) { $exp -= alquimiaexpnecessaria($nivel); $nivel++; $subiu = true; }
    if ($nivel >= ALQUIMIA_NIVELMAX) { $exp = min($exp, alquimiaexpnecessaria($nivel)); }
    $row["alquimia_nivel"] = $nivel; $row["alquimia_exp"] = min($exp, 16000000);
    if ($critico) { $row["alquimia_critico"] = $hoje; }
    $row["gold"] = intval($row["gold"]) - $custo;

    list($set, $onde) = sqlmochila($row, $antes, array_values(array_unique($mudou)));
    doquery("UPDATE {{table}} SET ".($set != "" ? $set."," : "")."gold=gold-'".intval($custo)."', alquimia_nivel='".intval($nivel)."', alquimia_exp='".intval($row["alquimia_exp"])."', alquimia_critico='".intval($row["alquimia_critico"])."' WHERE id='".intval($row["id"])."' AND gold>='".intval($custo)."' $onde LIMIT 1", "users");

    $nome = t($receita["nome"]);
    if ($sucesso) {
        $nomeres = tnomeitem(explode(",", $resultado)[0]);
        $msg = ($receita["resultado_tipo"] == "variante") ? tf("Infusão bem-sucedida! Seu item agora é %s.", $nomeres) : tf("Sucesso! Você criou %s x%s.", $nomeres, qtditem($resultado));
        if ($critico) { $msg .= " <b>".t("Sucesso crítico! O resultado saiu em dobro.")."</b>"; }
    } else {
        $msg = tf("A alquimia falhou (%s%% de chance). Parte dos ingredientes de %s se perdeu.", $chance, $nome);
    }
    if ($subiu) { $msg .= "<br><b>".tf("Sua alquimia subiu para o nível %s!", $nivel)."</b>"; }
    return array($msg, $sucesso ? $resultado : "", $sucesso);

}

function alquimiadescobrir(&$row, $idreceita) { // Acrescenta a receita secreta à lista do jogador (grava; o jogador já está travado).

    $lista = alquimiaconhecidas($row);
    if (isset($lista[intval($idreceita)])) { return false; }
    $lista[intval($idreceita)] = true;
    $row["alquimia_receitas"] = implode(",", array_keys($lista)).",";
    doquery("UPDATE {{table}} SET alquimia_receitas='".addslashes($row["alquimia_receitas"])."' WHERE id='".intval($row["id"])."' LIMIT 1", "users");
    return true;

}

function alquimialivro($texto) { // Receitas ensinadas pelo livro da mochila ($texto = espaço), ou array().

    $p = array_pad(explode(",", (string)$texto), 4, "X");
    if (!is_numeric($p[1]) || intval($p[2]) < 4) { return array(); }
    $lista = array();
    foreach (receitasalquimia() as $r) { if (intval($r["livro_drop"]) == intval($p[1]) && intval($r["livro_drop"]) > 0) { $lista[] = $r; } }
    return $lista;

}


/* ------------------------------------------------------------------------
   Página.
   ------------------------------------------------------------------------ */

function fundir() { // Tela da alquimia (abas) e as ações: criar receita, experimentar (fusão livre), ler livro de receitas.

    global $userrow;
    alquimiaverificar();

    $abas = array("receitas", "experimentar", "refinar", "infusao", "pocoes");
    $aba = pegartexto($_GET, "aba", "receitas");
    if (!in_array($aba, $abas, true)) { $aba = "receitas"; }
    $frase = "";
    $resultado = "";

    if (isset($_POST["submit"]) || isset($_POST["criar"]) || isset($_POST["ler"])) {

        // Trava o jogador: dois envios ao mesmo tempo não podem usar os mesmos itens duas vezes.
        doquery("START TRANSACTION", "users");
        $userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"), MYSQLI_ASSOC);
        $estado = pegartexto($_POST, "estado");

        if (isset($_POST["criar"])) { // Receita escolhida numa das abas.
            $receitas = receitasalquimia();
            $id = pegarint($_POST, "receita");
            if ($estado !== alquimiaestado($userrow)) { $frase = t("Sua mochila mudou desde que a página foi aberta. Confira e tente de novo."); }
            elseif (!isset($receitas[$id]) || !alquimiavisivel($receitas[$id], $userrow)) { $frase = t("Receita desconhecida."); }
            else { list($frase, $resultado) = alquimiaexecutar($userrow, $receitas[$id], pegarint($_POST, "alvo")); }

        } elseif (isset($_POST["ler"])) { // Livro de receitas: ensina a(s) receita(s) e some (1 unidade).
            $slot = pegarint($_POST, "ler");
            if ($estado !== alquimiaestado($userrow) || $slot < 1 || $slot > 8) { $frase = t("Sua mochila mudou desde que a página foi aberta. Confira e tente de novo."); }
            else {
                $novas = array();
                foreach (alquimialivro($userrow["bp".$slot]) as $r) { if (alquimiadescobrir($userrow, $r["id"])) { $novas[] = t($r["nome"]); } }
                if (count($novas) == 0) { $frase = t("Você já conhece tudo o que este livro ensina."); }
                else {
                    $antes = $userrow["bp".$slot];
                    $userrow["bp".$slot] = itemcomqtd($antes, qtditem($antes) - 1);
                    doquery("UPDATE {{table}} SET bp".$slot."='".addslashes($userrow["bp".$slot])."' WHERE id='".intval($userrow["id"])."' AND bp".$slot."='".addslashes($antes)."' LIMIT 1", "users");
                    $frase = tf("Você aprendeu a receita: %s!", implode(", ", $novas));
                }
            }

        } else { // Experimentar: os itens marcados (o valor do checkbox é o texto do espaço, conferido com a mochila atual).
            $aba = "experimentar";
            $marcados = array();
            for ($j = 1; $j <= 8; $j++) {
                $enviado = pegartexto($_POST, "bpnome".$j);
                if ($enviado == "") { continue; }
                if ($enviado !== html_deep(addslashes_deep($userrow["bp".$j]))) { doquery("ROLLBACK", "users"); header("Location: ./alquimia.php?do=fundir&aba=experimentar&frase=".rawurlencode(t("Tentativa de trapaça detectada."))); die(); }
                if ($userrow["bp".$j] != "None") { $marcados[$j] = $userrow["bp".$j]; }
            }
            $achou = false;
            if (count($marcados) >= 1 && count($marcados) <= 4) {
                foreach (receitasalquimia() as $r) {
                    if (in_array($r["resultado_tipo"], array("variante", "copia"), true) || count($r["ing"]) == 0) { continue; }
                    // A combinação vale se cada item marcado é um ingrediente e cada ingrediente foi marcado.
                    $ok = true;
                    foreach ($marcados as $s) { $serve = false; foreach ($r["ing"] as $ing) { if (alquimiacasa($s, $ing)) { $serve = true; } } if (!$serve) { $ok = false; break; } }
                    foreach ($r["ing"] as $ing) { $tem = false; foreach ($marcados as $s) { if (alquimiacasa($s, $ing)) { $tem = true; } } if (!$tem) { $ok = false; } }
                    if (!$ok) { continue; }
                    $achou = true;
                    $descobriu = (intval($r["descoberta"]) == 1 && alquimiadescobrir($userrow, $r["id"]));
                    list($frase, $resultado) = alquimiaexecutar($userrow, $r);
                    if ($descobriu) { $frase = "<b>".tf("Você descobriu uma receita secreta: %s!", t($r["nome"]))."</b><br>".$frase; }
                    break;
                }
            }
            if (!$achou) { $frase = t("Infelizmente você não pode fundir esse(s) iten(s)..."); }
        }
        doquery("COMMIT", "users");
    }
    if ($frase == "") { $frase = lermsg("frase"); }

    display(alquimiapagina($aba, $abas, $frase, $resultado), t("Alquimia"), false, false, false);

}

function alquimiaicone($texto) { // Caminho do ícone 34x34 de um texto de mochila.

    $p = array_pad(explode(",", (string)$texto), 4, "X");
    if (is_numeric($p[1])) { return iconeitem($p[1], intval($p[2]) > 3, $p[0]); }
    return iconeconsumivel($p[1], $p[2], $p[0]);

}

function alquimiaimg($texto, $qtd = 0, $legenda = "") { // <img> do item com a caixa de explicação e (opcional) a quantidade no canto.

    static $n = 0; $n++;
    $p = array_pad(explode(",", (string)$texto), 4, "X");
    $explic = conteudoexplic($p[1], $p[2], "alq".$n, ($p[3] == "X") ? "*" : $p[3], $p[0], max(1, $qtd));
    $html = "<img src=\"".alquimiaicone($texto)."\" id=\"alq$n\" width=\"34\" height=\"34\" style=\"border:2px #e4d094 solid;vertical-align:middle\" onmouseover=\"$explic\" onmouseout=\"fecharexplic();\" alt=\"\">";
    if ($qtd > 1) { $html = iconecomqtd($html, itemcomqtd($texto, $qtd)); }
    return $html.$legenda;

}

function alquimiacartao($r, $row, $estado) { // Uma receita: resultado, ingredientes (tenho/preciso), chance, custo e o botão.

    $t = "t";
    $erro = alquimiarequisitos($r, $row);
    $falta = false;
    $ings = "";
    foreach ($r["ing"] as $ing) {
        $tenho = alquimiatenho($row, $ing);
        $cor = ($tenho >= $ing["qtd"]) ? "#1d6b1d" : "#b01010";
        if ($tenho < $ing["qtd"]) { $falta = true; }
        $txt = alquimiatexto($ing["tipo"], $ing["id"]);
        if ($txt == "") { $falta = true; continue; }
        $ings .= "<span style=\"display:inline-block;text-align:center;margin:2px 4px;font-size:10px\">".alquimiaimg($txt)."<br><font color=\"$cor\"><b>$tenho/".$ing["qtd"]."</b></font></span>";
    }
    $alvos = alquimiaalvos($r, $row);
    $selalvo = "";
    if (in_array($r["resultado_tipo"], array("variante", "copia"), true)) {
        if (count($alvos) == 0) { $falta = true; $selalvo = "<br><font color=\"#b01010\">".(($r["resultado_tipo"] == "copia") ? t("Nenhum pergaminho de jutsu copiável na mochila.") : t("Nenhum item que aceite infusão na mochila."))."</font>"; }
        else {
            $selalvo = "<br><select name=\"alvo\" style=\"max-width:100%;width:100%;margin-top:3px\">";
            foreach ($alvos as $j => $s) { $selalvo .= "<option value=\"$j\">".tf("Espaço %s", $j).": ".tnomeitem(explode(",", $s)[0])."</option>"; }
            $selalvo .= "</select>";
        }
    }
    // Resultado: figura (com explicação) ou a variante.
    if ($r["resultado_tipo"] == "variante") {
        $v = variantesitens();
        $ef = $r["resultado_id"];
        $icone = file_exists("layoutnovo/equipamentos/variantes/$ef/1.gif") ? "<img src=\"layoutnovo/equipamentos/variantes/$ef/1.gif\" width=\"34\" height=\"34\" style=\"border:2px #e4d094 solid\" alt=\"\">" : "";
        $res = $icone."<br><font color=\"".(isset($v[$ef]) ? $v[$ef]["cor"] : "#000")."\"><b>✦ ".(isset($v[$ef]) ? t($v[$ef]["rotulo"]) : $ef)."</b></font>";
    } elseif ($r["resultado_tipo"] == "copia") {
        $res = "<img src=\"layoutnovo/equipamentos/novos/pergaminho_neutro.gif\" width=\"34\" height=\"34\" style=\"border:2px #e4d094 solid\" alt=\"\"><br><b>x1</b>";
    } else {
        $txt = alquimiaresultado($r, 1);
        $res = ($txt == "") ? "?" : alquimiaimg($txt, intval($r["qtd"]));
    }
    $chance = alquimiachance($r, $row);
    $custo = alquimiacusto($r);
    $pode = ($erro == "" && !$falta && intval($row["gold"]) >= $custo);
    $botao = $pode ? "<input class=\"btnjogo bj-confirmar\" type=\"submit\" name=\"criar\" value=\"".t("Criar")."\">" : "<input class=\"btnjogo bj-confirmar\" type=\"submit\" value=\"".t("Criar")."\" disabled>";
    $aviso = ($erro != "") ? "<br><font color=\"#b01010\">$erro</font>" : ((intval($row["gold"]) < $custo) ? "<br><font color=\"#b01010\">".t("Ryou insuficiente.")."</font>" : "");
    $secreta = (intval($r["descoberta"]) == 1) ? " <font color=\"#7a3db8\" title=\"".t("Receita secreta descoberta")."\">✧</font>" : "";
    $corchance = ($chance >= 70) ? "#1d6b1d" : (($chance >= 40) ? "#9a6a00" : "#b01010");
    return "<tr bgcolor=\"#FFF1C7\"><td align=\"center\" width=\"70\">$res</td>"
        ."<td><b>".t($r["nome"])."</b>$secreta <span style=\"font-size:10px;color:#613003\">".tf("Alquimia nv. %s", intval($r["nivel_minimo"]))."</span><br>$ings$aviso</td>"
        ."<td align=\"center\" width=\"95\" style=\"font-size:11px\">".t("Chance")."<br><font color=\"$corchance\" size=\"3\"><b>$chance%</b></font><br>".tf("%s Ryou", $custo)."</td>"
        ."<td align=\"center\" width=\"120\"><form action=\"alquimia.php?do=fundir&amp;aba=".alquimiaabadareceita($r)."\" method=\"post\" style=\"margin:0\"><input type=\"hidden\" name=\"receita\" value=\"".intval($r["id"])."\"><input type=\"hidden\" name=\"estado\" value=\"$estado\">$botao$selalvo</form></td></tr>";

}

function alquimiaabadareceita($r) { // Aba onde a receita aparece (pela categoria).

    if ($r["categoria"] == "refino") { return "refinar"; }
    if ($r["categoria"] == "infusao" || $r["resultado_tipo"] == "variante") { return "infusao"; }
    if ($r["categoria"] == "pocao") { return "pocoes"; }
    return "receitas";

}

function alquimiapagina($aba, $abas, $frase, $resultado) { // Monta o HTML da página (cabeçalho, nível, abas e conteúdo da aba).

    global $userrow;
    $t = "t";
    $row = $userrow;
    $estado = alquimiaestado($row);
    $rotulos = array("receitas" => t("Receitas conhecidas"), "experimentar" => t("Experimentar"), "refinar" => t("Refinar"), "infusao" => t("Infusão"), "pocoes" => t("Poções"));

    // Barra do nível de alquimia.
    $nivel = intval($row["alquimia_nivel"]);
    $prec = alquimiaexpnecessaria($nivel);
    $pct = ($nivel >= ALQUIMIA_NIVELMAX) ? 100 : min(100, (int)floor(100 * intval($row["alquimia_exp"]) / $prec));
    $barra = "<div style=\"display:inline-block;width:220px;height:12px;border:1px solid #452202;background:#FFF1C7;vertical-align:middle\"><div style=\"width:$pct%;height:12px;background:linear-gradient(#e0a020,#b06a00)\"></div></div>";
    $textonivel = ($nivel >= ALQUIMIA_NIVELMAX) ? t("nível máximo") : intval($row["alquimia_exp"])." / ".$prec;
    $cab = "<table width=\"100%\" cellspacing=\"0\" cellpadding=\"4\"><tr bgcolor=\"#452202\"><td><font color=\"white\"><b>".tf("Alquimia nível %s", $nivel)."</b></font> $barra <font color=\"white\" size=\"1\">$textonivel</font></td><td align=\"right\"><font color=\"white\">".tf("%s Ryou", intval($row["gold"]))."</font></td></tr></table>";

    $menu = "";
    foreach ($abas as $a) {
        $estilo = ($a == $aba) ? "background:#613003;color:#fff;" : "background:#E4D094;color:#452202;";
        $menu .= "<a href=\"alquimia.php?do=fundir&amp;aba=$a\" style=\"{$estilo}display:inline-block;padding:5px 10px;margin:0 2px;border:1px solid #452202;border-bottom:0;text-decoration:none;font-weight:bold\">".$rotulos[$a]."</a>";
    }

    if ($frase != "") { $frase = "<div style=\"margin:6px 0;padding:6px;border:1px solid #c9a95c;background:#fff8df\"><center><font color=brown>".$frase."</font></center></div>"; }
    if ($resultado != "") { $frase .= "<center>".alquimiaimg($resultado, qtditem($resultado))."</center>"; }

    $conteudo = "";
    if ($aba == "experimentar") {
        $conteudo = alquimiaexperimentar($row);
    } else {
        $filtro = array("receitas" => array("equipamento", "material", "pergaminho"), "refinar" => array("refino"), "infusao" => array("infusao"), "pocoes" => array("pocao"));
        $explica = array(
            "receitas" => t("Receitas que você conhece. Os números mostram quantas unidades você tem na mochila / quantas a receita pede. Passe o mouse sobre as figuras para ver os detalhes."),
            "refinar" => t("Junte 3 materiais iguais para conseguir 1 material de nível acima."),
            "infusao" => t("Infunda um material raro numa arma, colete, bandana ou drop da mochila para tentar dar a ele uma variante rara. Custa caro e a chance é baixa; na falha o item é mantido."),
            "pocoes" => t("Ervas e materiais viram poções, elixires e consumíveis empilháveis."));
        $linhas = ""; $secretas = 0;
        foreach (receitasalquimia() as $r) {
            $categoria = in_array($r["categoria"], array("equipamento", "material", "pergaminho", "refino", "infusao", "pocao"), true) ? $r["categoria"] : "equipamento";
            if (!in_array($categoria, $filtro[$aba], true)) { continue; }
            if (!alquimiavisivel($r, $row)) { $secretas++; continue; }
            $linhas .= alquimiacartao($r, $row, $estado);
        }
        if ($linhas == "") { $linhas = "<tr bgcolor=\"#FFF1C7\"><td colspan=\"4\" align=\"center\">".t("Nenhuma receita aqui ainda.")."</td></tr>"; }
        $rodape = ($secretas > 0) ? "<p style=\"font-size:11px;color:#613003\">".tf("Ainda há %s receita(s) secreta(s) nesta aba. Encontre livros de receitas ou experimente combinações.", $secretas)."</p>" : "";
        $livros = ($aba == "receitas") ? alquimialivros($row, $estado) : "";
        $conteudo = "<p style=\"font-size:11px\">".$explica[$aba]."</p>$livros<table width=\"100%\" cellspacing=\"1\" cellpadding=\"4\" bgcolor=\"#c9a95c\"><tr bgcolor=\"#613003\"><td><font color=white>".t("Resultado")."</font></td><td><font color=white>".t("Receita e ingredientes")."</font></td><td align=\"center\"><font color=white>".t("Chance / custo")."</font></td><td></td></tr>$linhas</table>$rodape";
    }

    return "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/alquimia.gif\" alt=\"\" /></center></td></tr></table>
$cab
$frase
<div style=\"margin-top:8px;border-bottom:2px solid #452202\">$menu</div>
<div style=\"padding:8px;background:rgba(255,248,225,.35);border:1px solid #452202;border-top:0\">$conteudo</div>";

}

function alquimialivros($row, $estado) { // Livros de receitas da mochila, com o botão "Ler".

    $html = "";
    for ($j = 1; $j <= 8; $j++) {
        $ensina = alquimialivro($row["bp".$j]);
        if (count($ensina) == 0) { continue; }
        $nomes = array(); foreach ($ensina as $r) { $nomes[] = t($r["nome"]); }
        $html .= "<tr bgcolor=\"#FFF1C7\"><td width=\"50\">".alquimiaimg($row["bp".$j], qtditem($row["bp".$j]))."</td><td>".tnomeitem(explode(",", $row["bp".$j])[0])."<br><span style=\"font-size:10px\">".tf("Ensina: %s", implode(", ", $nomes))."</span></td>"
            ."<td width=\"80\"><form action=\"alquimia.php?do=fundir&amp;aba=receitas\" method=\"post\" style=\"margin:0\"><input type=\"hidden\" name=\"estado\" value=\"$estado\"><input type=\"hidden\" name=\"ler\" value=\"$j\"><input class=\"btnjogo bj-confirmar\" type=\"submit\" value=\"".t("Ler")."\"></form></td></tr>";
    }
    return ($html == "") ? "" : "<table width=\"100%\" cellspacing=\"1\" cellpadding=\"4\" bgcolor=\"#c9a95c\" style=\"margin-bottom:8px\"><tr bgcolor=\"#613003\"><td colspan=\"3\"><font color=white>".t("Livros de receitas na mochila")."</font></td></tr>$html</table>";

}

function alquimiaexperimentar($row) { // Aba Experimentar: a fusão livre de 2010 (marque de 1 a 4 itens da mochila).

    $tabela = "<form action=\"alquimia.php?do=fundir&amp;aba=experimentar\" method=\"post\"><table style=\"width:100%\">
    <tr bgcolor=\"#452202\"><td colspan=3><font color=white>".t("Mochila")."</font></td></tr>
    <tr bgcolor=\"#613003\"><td><font color=white>*</font></td><td><font color=white>*</font></td><td><font color=white>".t("Nome")."</font></td></tr>";
    for ($j = 1; $j <= 8; $j++) {
        $bgcolor = ($j % 2 == 0) ? "#E4D094" : "#FFF1C7";
        $img = ($row["bp".$j] != "None") ? alquimiaimg($row["bp".$j], qtditem($row["bp".$j])) : "<img src=\"images/bpalquimia_vazio.png\" width=\"34\" height=\"34\" style=\"border:2px #e4d094 solid\" alt=\"\">";
        $nome = ($row["bp".$j] != "None") ? nomeitemhtml(explode(",", $row["bp".$j])[0]) : "-";
        $caixa = ($row["bp".$j] != "None") ? "<input type=\"checkbox\" name=\"bpnome".$j."\" value=\"".htmlspecialchars($row["bp".$j], ENT_QUOTES, 'UTF-8')."\">" : "";
        $tabela .= "<tr bgcolor=\"$bgcolor\"><td>$caixa</td><td>$img</td><td>$nome</td></tr>";
    }
    $tabela .= "<tr><td colspan=\"3\"><center><input class=\"btnjogo bj-confirmar\" type=\"submit\" name=\"submit\" value=\"".t("Fundir Itens")."\" /></center></td></tr></table></form>";
    return "<p style=\"font-size:11px\">".t("Marque de 1 a 4 itens da mochila e tente fundi-los. Se a combinação for de uma receita (mesmo secreta), você a descobre e faz a tentativa com as quantidades da receita. Combinações erradas não custam nada.")."</p>
<div style=\"display:flex;flex-wrap:wrap;justify-content:center;align-items:flex-start;gap:8px\"><div style=\"flex:none;background:url(images/alquimiafundo.png?v=5) no-repeat center;width:194px;height:194px;position:relative\"><img src=\"images/alquimiagif.gif\" alt=\"\" style=\"position:absolute;left:80px;top:80px\"></div><div style=\"flex:1 1 280px;min-width:0\">$tabela</div></div>"; // no celular o círculo fica em cima e a mochila embaixo.

}

?>
