<?php // heal.php :: Jutsus da "Lista Rápida" da coluna da direita (fora de luta): medicinais, Invocação de Suprimentos, Passo Veloz e jutsus de Senjutsu de menu. Os outros jutsus são usados no fight.php.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

function healspells($id) { // Usa um jutsu da lista rápida (index.php?do=spell:ID). Escolhe a ação pela coluna spells.acao_menu.

    global $userrow;

    $id = intval($id);
    if ($userrow["currentaction"] == "Fighting") { voltarcommensagem(t("Você não pode usar os Jutsus da Lista Rápida de Jutsus durante uma batalha. Por favor selecione o Jutsu Medicinal que você gostaria de usar pela seleção de Jutsus na janela principal de luta para continuar."), "index.php?do=fight"); }

    // O jogador precisa ter aprendido o jutsu (users.spells = "0,1,4,...").
    $userspells = explode(",", (string)$userrow["spells"]);
    if ($id <= 0 || !in_array($id, $userspells)) { voltarcommensagem(t("Você ainda não aprendeu esse Jutsu.")); }

    include_once('funcoesinclusas.php');
    $spellrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "spells"));
    if (!$spellrow || !jutsudomenu($spellrow)) { voltarcommensagem(t("Esse jutsu não pode ser usado pela lista rápida.")); }

    // Passo Veloz: liga/desliga (o custo é cobrado a cada passo, no explore.php). Desligar é sempre permitido.
    if ((string)$spellrow["acao_menu"] == "passo") { passoveloz($spellrow); }

    // Senjutsu ativo, NP e recarga em minutos (funcoesjutsus.php).
    $erro = jutsuerroespecial($spellrow, $userrow);
    if ($erro != "") { voltarcommensagem(t($erro)); }
    $falta = jutsumenurecarga($userrow, $id);
    if ($falta > 0) { voltarcommensagem(tf("%s ainda está em recarga (%s min).", t($spellrow["name"]), (int)ceil($falta / 60))); }
    $mp = custochakra($spellrow["mp"], $spellrow); // Economia de Chakra dos equipamentos/especialização.
    if (intval($userrow["currentmp"]) < $mp) { voltarcommensagem(t("Você não tem Chakra suficiente para usar esse Jutsu.")); }

    if ((string)$spellrow["acao_menu"] == "suprimentos") { invocarsuprimentos($spellrow, $mp); }

    // Medicinal (tipo 1): cura até completar o HP máximo.
    if (intval($spellrow["type"]) != 1) { voltarcommensagem(t("Esse não é um Jutsu medicinal.")); }
    if ($userrow["currenthp"] >= $userrow["maxhp"]) { voltarcommensagem(t("Seus Pontos de Vida já estão cheios. Você não precisa de um Jutsu Medicinal agora.")); }
    $cura = min(intval($spellrow["attribute"]), intval($userrow["maxhp"]) - intval($userrow["currenthp"]));
    $np = intval($spellrow["custo_np"]);
    $cd = jutsumenunovarecarga($userrow, $id, intval($spellrow["recarga_min"]));
    // Tudo numa gravação atômica: o WHERE confere Chakra, NP e a recarga lidos (dois cliques não curam duas vezes de graça).
    doquery("UPDATE {{table}} SET currenthp=LEAST(maxhp, currenthp+$cura), currentmp=currentmp-$mp, currentnp=currentnp-$np, jutsumenu_cd='".addslashes($cd)."' WHERE id='".intval($userrow["id"])."' AND currentmp>=$mp AND currentnp>=$np AND jutsumenu_cd='".addslashes((string)$userrow["jutsumenu_cd"])."' LIMIT 1", "users");
    if (mysqli_affected_rows($GLOBALS["link"]) != 1) { voltarcommensagem(t("Você não tem Chakra suficiente para usar esse Jutsu.")); }

    voltarcommensagem(tf("Você usou o %s e ganhou %s Pontos de Vida.", t($spellrow["name"]), $cura));

}

function passoveloz($spellrow) { // Liga ou desliga o Passo Veloz (users.passo_jutsu). Só um fica ligado por vez.

    global $userrow;
    $id = intval($spellrow["id"]);
    if (intval($userrow["passo_jutsu"]) == $id) {
        doquery("UPDATE {{table}} SET passo_jutsu='0' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
        voltarcommensagem(tf("%s desligado.", t($spellrow["name"])));
    }
    $erro = jutsuerroespecial($spellrow, $userrow);
    if ($erro != "") { voltarcommensagem(t($erro)); }
    // Precisa ter recursos para pelo menos um passo.
    if (intval($userrow["currentmp"]) < custochakra($spellrow["mp"], $spellrow) || intval($userrow["currenttp"]) < intval($spellrow["custo_tp"])) {
        voltarcommensagem(t("Você não tem Chakra/TP suficiente para nem um passo veloz."));
    }
    doquery("UPDATE {{table}} SET passo_jutsu='$id' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    $custos = custochakra($spellrow["mp"], $spellrow)." CH".(intval($spellrow["custo_tp"]) > 0 ? " + ".intval($spellrow["custo_tp"])." TP" : "");
    voltarcommensagem(tf("%s ligado: cada passo anda %s casas e custa %s. Clique de novo no jutsu para desligar.", t($spellrow["name"]), intval($spellrow["tier"]) + 1, $custos));

}

/* Invocação de Suprimentos (spells.acao_menu = "suprimentos"; spells.tier = linha da tabela suprimentos):
   1) rola control/tabela: chance_falha % = nada vem (o Chakra é gasto mesmo assim);
   2) escolhe a categoria pelos pesos (peso_loja, peso_drop, peso_pocao) entre as que têm algum item;
   3) sorteia um item da categoria, sempre sem variante rara:
        loja .... items com buycost entre preco_max/8 e preco_max, que podem ser vendidos (naoVendavel = 0);
        drop .... drops equipáveis (sem consumivel/pergaminho/livro) com mlevel entre nivel_max_drop/2 e nivel_max_drop
                  (drops de missão têm mlevel >= 100 e nunca entram);
        poção ... drops com consumivel "hp,X"/"mp,X" e X entre pocao_max/4 e pocao_max;
      os ids da coluna excluir ("i12,d45") ficam fora do sorteio (relíquias e itens de evento).
   A mochila precisa ter espaço ANTES (senão nada é gasto). Jogador travado (FOR UPDATE) do começo ao fim. */
function invocarsuprimentos($spellrow, $mp) {

    global $userrow, $link;
    $tier = intval($spellrow["tier"]);
    $regra = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE tier='$tier' LIMIT 1", "suprimentos"));
    if (!$regra) { voltarcommensagem(t("Esse jutsu de invocação não está configurado.")); }

    mysqli_begin_transaction($link);
    $u = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
    if (!$u || $u["currentaction"] == "Fighting") { mysqli_rollback($link); voltarcommensagem(t("Você não pode usar esse jutsu agora.")); }
    if (intval($u["currentmp"]) < $mp || jutsumenurecarga($u, $spellrow["id"]) > 0 || jutsuerroespecial($spellrow, $u) != "") { mysqli_rollback($link); voltarcommensagem(t("Você não tem Chakra suficiente para usar esse Jutsu.")); }
    $livre = false;
    for ($i = 1; $i <= 8; $i++) { if ($u["bp".$i] == "None") { $livre = true; break; } }
    if (!$livre) { mysqli_rollback($link); voltarcommensagem(t("Sua mochila está cheia. Libere um espaço antes de invocar suprimentos.")); }

    $item = (rand(1, 100) > intval($regra["chance_falha"])) ? sortearsuprimento($regra) : "";
    $antes = $u;
    $espacos = ($item != "") ? mochilaguardar($u, $item) : array();
    if ($espacos === false) { $item = ""; $espacos = array(); }
    $sets = "";
    foreach ($espacos as $e) { $sets .= ", $e='".addslashes($u[$e])."'"; }
    $cd = jutsumenunovarecarga($antes, $spellrow["id"], intval($spellrow["recarga_min"]));
    $np = intval($spellrow["custo_np"]);
    doquery("UPDATE {{table}} SET currentmp=currentmp-$mp, currentnp=currentnp-$np, jutsumenu_cd='".addslashes($cd)."'$sets WHERE id='".intval($u["id"])."' AND currentmp>=$mp AND currentnp>=$np LIMIT 1", "users");
    if (mysqli_affected_rows($link) != 1) { mysqli_rollback($link); voltarcommensagem(t("Você não tem Chakra suficiente para usar esse Jutsu.")); }
    mysqli_commit($link);

    if ($item == "") { voltarcommensagem(tf("Você usou %s, mas o selo só trouxe poeira e folhas secas. Nada desta vez.", t($spellrow["name"]))); }
    $p = explode(",", $item);
    voltarcommensagem(tf("Você usou %s e o selo trouxe: %s! (foi para a mochila)", t($spellrow["name"]), tnomeitem($p[0])));

}

function sortearsuprimento($regra) { // Texto do item sorteado para a mochila ("nome,id,tipo,dur" ou "nome,hp,50,1"), ou "" se nenhuma categoria tiver itens.

    $excluiritens = array(0); $excluirdrops = array(0);
    foreach (explode(",", (string)$regra["excluir"]) as $x) {
        $x = trim($x);
        if (preg_match('/^i(\d+)$/', $x, $m)) { $excluiritens[] = intval($m[1]); }
        if (preg_match('/^d(\d+)$/', $x, $m)) { $excluirdrops[] = intval($m[1]); }
    }
    $ei = implode(",", $excluiritens); $ed = implode(",", $excluirdrops);
    $preco = intval($regra["preco_max"]); $nivel = intval($regra["nivel_max_drop"]); $pocao = intval($regra["pocao_max"]);
    $sqls = array(
        "loja"  => ($preco > 0) ? array("SELECT id,name,type FROM {{table}} WHERE buycost>='".intdiv($preco, 8)."' AND buycost<='$preco' AND naoVendavel='0' AND id NOT IN ($ei) ORDER BY RAND() LIMIT 1", "items") : null,
        "drop"  => ($nivel > 0) ? array("SELECT id,name FROM {{table}} WHERE mlevel>='".intdiv($nivel, 2)."' AND mlevel<='$nivel' AND mlevel<100 AND consumivel='' AND ensina_jutsu='0' AND naoVendavel='0' AND (attribute1<>'X' OR attribute2<>'X') AND attribute1 NOT LIKE '%,-%' AND attribute2 NOT LIKE '%,-%' AND attribute3 NOT LIKE '%,-%' AND id NOT IN ($ed) ORDER BY RAND() LIMIT 1", "drops") : null,
        "pocao" => ($pocao > 0) ? array("SELECT id,name,consumivel FROM {{table}} WHERE (consumivel LIKE 'hp,%' OR consumivel LIKE 'mp,%') AND CAST(SUBSTRING_INDEX(consumivel, ',', -1) AS UNSIGNED) BETWEEN '".intdiv($pocao, 4)."' AND '$pocao' AND id NOT IN ($ed) ORDER BY RAND() LIMIT 1", "drops") : null,
    );
    $pesos = array("loja" => intval($regra["peso_loja"]), "drop" => intval($regra["peso_drop"]), "pocao" => intval($regra["peso_pocao"]));
    // Tenta as categorias em ordem sorteada pelos pesos; uma categoria vazia passa a vez para a próxima.
    while (count($pesos) > 0) {
        $total = array_sum($pesos);
        if ($total <= 0) { break; }
        $r = rand(1, $total); $cat = "";
        foreach ($pesos as $c => $w) { $r -= $w; if ($r <= 0) { $cat = $c; break; } }
        unset($pesos[$cat]);
        if ($sqls[$cat] === null) { continue; }
        $row = mysqli_fetch_array(doquery($sqls[$cat][0], $sqls[$cat][1]));
        if (!$row) { continue; }
        if ($cat == "loja") { return $row["name"].",".intval($row["id"]).",".intval($row["type"]).",".durabilidadenova(intval($row["type"]), $row["id"]); } // durabilidade cheia ou X.
        if ($cat == "drop") { return $row["name"].",".intval($row["id"]).",4,".durabilidadenova(4, $row["id"]); }
        $c = explode(",", $row["consumivel"]);
        return $row["name"].",".$c[0].",".intval($c[1]).",1";
    }
    return "";

}

?>
