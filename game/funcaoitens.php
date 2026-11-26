<?php // funcaoitens.php :: Banco do jogo: guardar/retirar Ryou, guardar itens (equipamento e mochila), enviar itens e Ryou para outros jogadores e apagar itens.

/*  FORMATOS DOS CAMPOS DE TEXTO USADOS NESTE ARQUIVO (tabela users)

    bp1 .. bp8 (os 8 espaços da mochila): "nome,id,tipo,durabilidade" ou "None" (espaço vazio).
        nome .......... nome exibido. Equipamentos refinados têm o sufixo "+N" (ex.: "Kunai+2"),
                        que vale N*20 de ataque (arma) ou defesa (colete/bandana).
        id ............ id na tabela items (tipos 1..3) ou drops (tipos 4..6). Itens que só podem ficar na mochila
                        usam um código de texto: hp/mp/tp/hm/hmt = poções, bp = mochila, dia = diamante,
                        per = pergaminho, bk = livro.
        tipo .......... 1 arma, 2 colete, 3 bandana, 4..6 drop de slot (o número do slot de onde ele saiu).
                        Nas poções é o valor recuperado (ex.: "Poção,hp,50,X" recupera 50 de HP).
        durabilidade .. lutas restantes antes de o item quebrar, ou "X" = infinita.

    bancogeral (itens guardados no banco): os itens no mesmo formato acima, cada um terminado por ";"
        ex.: "Kunai,3,1,30;Alma da Areia,36,4,X;"   —   "None" quando o banco está vazio.
        As telas e links do banco usam a POSIÇÃO do item nessa lista (0, 1, 2...) como identificador ("qual").

    durabilidade (itens equipados): "-,a,b,c,d,e,f,g"
        a posição 0 é só um marcador ("-"); 1 arma, 2 colete, 3 bandana, 4 slot1, 5 slot2, 6 slot3, 7 slot4.
        Cada posição guarda as lutas restantes do item equipado ali, ou "X" (slot vazio ou item infinito).

    banco_grana: Ryou guardado no banco (o Ryou "equipado", que o personagem carrega, fica em gold).

    REGRAS DO BANCO
        - O Ryou guardado no banco não pode passar de 90% do Ryou total (banco + personagem).
        - Cada saque é de no máximo 99999 Ryou.
        - O banco guarda até 30 itens (não existem mais contas VIP).
        - Só funciona dentro de uma cidade; doar Ryou exige level 5 ou mais.
*/

include('lib.php');
$link = opendb();

include('cookies.php');
$userrow = checkcookies();

include_once('funcoesinclusas.php'); // iconeitemmochila(): ícone e durabilidade de um item.

$do = pegartexto($_GET, "do");
if ($do == "banco") { banco(); }
elseif ($do == "depositar") { depositar(); }
elseif ($do == "retirar") { retirar(); }
elseif ($do == "enviaritem") { enviaritem(); }
elseif ($do == "depositarbp") { depositarbp(); }
elseif ($do == "diretoparabp") { diretoparabp(); }
elseif ($do == "deletaritem") { deletaritem(); }
elseif ($do == "doarryou") { doarryou(); }
else { header("Location: ./funcaoitens.php?do=banco"); die(); }


/* ------------------------------------------------------------------------
   Funções auxiliares do banco.
   ------------------------------------------------------------------------ */

function bancoverificar() { // Confere se o jogador está logado e dentro de uma cidade (o banco só funciona nas cidades).

    global $userrow;
    if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
    if ($userrow["currentaction"] == "Fighting") { header("Location: ./index.php?do=fight&conteudo=".rawurlencode(t("Voce nao pode acessar essa funcao no meio de uma batalha!"))); die(); }
    if ($userrow["currentaction"] != "In Town") { header("Location: ./index.php?conteudo=".rawurlencode(t("Voce so pode acessar essa funcao dentro de uma cidade!"))); die(); }

}

function bancovoltar($extra = "") { // Volta para a página do banco (com um código "f=N" opcional).

    header("Location: ./funcaoitens.php?do=banco".$extra);
    die();

}

function bancoaviso($campo, $texto) { // Volta para o banco mostrando uma mensagem (bi = área dos itens, br = área do Ryou). O texto já vem traduzido (t/tf).

    bancovoltar("&".$campo."=".urlencode($texto));

}

function bancotrapaca() { // Índice/slot inválido: alguém mexeu na URL.

    display(t("Tentativa de trapaça ou erro detectado."),t("Erro"),false,false,false);
    die();

}

function travarjogadores($ids) { // Abre uma transação e lê os jogadores com bloqueio de linha (SELECT ... FOR UPDATE).

    // Duas requisições ao mesmo tempo (clique duplo, duas abas, script) ficam em fila aqui: a segunda só continua
    // depois que a primeira gravar (COMMIT) e então já enxerga os dados novos. É isso que impede duplicar itens/Ryou.
    // Se a página morrer (die) antes do COMMIT, a conexão fecha e o MariaDB desfaz tudo (ROLLBACK).
    $lista = array();
    foreach ($ids as $id) { $lista[] = intval($id); }
    sort($lista); // sempre na mesma ordem, para dois jogadores não travarem um ao outro (deadlock).
    doquery("START TRANSACTION", "users");
    $query = doquery("SELECT * FROM {{table}} WHERE id IN ('".implode("','", $lista)."') ORDER BY id FOR UPDATE", "users");
    $linhas = array();
    while ($row = mysqli_fetch_array($query)) { $linhas[intval($row["id"])] = $row; }
    return $linhas;

}

function confirmartransacao() { // Grava de vez tudo o que foi feito desde travarjogadores().

    doquery("COMMIT", "users");

}

function travarjogador() { // Relê o jogador logado com bloqueio, para as ações que alteram itens ou Ryou.

    global $userrow;
    $linhas = travarjogadores(array($userrow["id"]));
    $userrow = $linhas[intval($userrow["id"])];

}

function bancoitens($bancogeral) { // "a;b;c;" -> array("a","b","c"). "None" (banco vazio) -> array().

    $lista = array();
    if ($bancogeral == "None") { return $lista; }
    foreach (explode(";", (string)$bancogeral) as $item) {
        if ($item != "" && $item != "None") { $lista[] = $item; } // entrada "None" (dado estragado) não é item: some na próxima gravação.
    }
    return $lista;

}

function bancotexto($lista) { // Inverso de bancoitens(): monta o texto do campo bancogeral.

    if (count($lista) == 0) { return "None"; }
    return implode(";", $lista).";";

}


function bancolimite($row) { // Quantos itens cabem no banco: users.banco_limite (30 de início; 40 e 50 com as expansões de drop,
    // consumivel "banco,ID" + drops.banco_limite, usadas pela mochila em backpack.php).

    $n = isset($row["banco_limite"]) ? intval($row["banco_limite"]) : 30;
    return max(30, min(50, $n)); // 50 = maior expansão (Chave do Grande Cofre).

}

function nomeitem($nome) { // Nome do item para exibir no idioma do jogador ("Kunai+2": traduz "Kunai" e mantém o refino "+2"; variante rara com rótulo colorido).

    if ($nome == "None" || $nome == "") { return $nome; } // "None" é valor interno (espaço vazio), exibido como está.
    $v = separarvariante($nome);
    $partes = explode("+", $v[0], 2);
    $texto = t($partes[0]).(isset($partes[1]) ? "+".$partes[1] : "");
    return ($v[1] == "") ? $texto : $texto." <font color=\"".variantesitens()[$v[1]]["cor"]."\"><b>✦ ".t(variantesitens()[$v[1]]["rotulo"])."</b></font>";

}

function bancotemplate() { // Formulário de depósito/saque (templates/banco.php) com os rótulos no idioma do jogador.

    global $userrow;
    // Botões "Máx": mesmas regras do bancoryou() (banco com até 90% do total; saque de até 99999 por vez).
    $ouro = intval($userrow["gold"]); $banco = intval($userrow["banco_grana"]); $col = 16777215;
    $maxdep = max(0, min($ouro, min(floor(90 * ($ouro + $banco) / 100), $col) - $banco));
    $maxret = max(0, min($banco, 99999, $col - $ouro));
    return strtr(gettemplate("banco"), array("Depositar Ryou:" => t("Depositar Ryou:"), "Retirar Ryou:" => t("Retirar Ryou:"), "MAXDEPOSITO" => intval($maxdep), "MAXRETIRAR" => intval($maxret)));

}

function bancoitemcodigo($item) { // Código curto do item (vai nos links de doar/deletar junto com a posição "qual").

    // A posição muda quando outro item sai do banco (outra aba, outro clique): com o código, um link antigo não
    // apaga/doa o item que passou a ocupar aquela posição.
    return substr(md5((string)$item), 0, 10);

}

function bancoconferircodigo($itens, $qual) { // O item na posição "qual" ainda é o que estava na tela? Senão, trapaça/erro.

    if (pegartexto($_GET, "h") !== bancoitemcodigo($itens[$qual])) { bancotrapaca(); }

}

function bancoguardar(&$itens, $texto, $limite) { // Guarda um item (com a quantidade dele) na lista do banco: completa pilhas
    // iguais até o máximo (pilhamaxima()) e o resto vira uma nova entrada. false se passar do limite de entradas (nada muda).

    $novo = $itens;
    $falta = qtditem($texto); $max = pilhamaxima($texto); $base = itemsemqtd($texto);
    if ($max > 1) {
        foreach ($novo as $k => $s) {
            if ($falta <= 0) { break; }
            if (itemsemqtd($s) !== $base || qtditem($s) >= $max) { continue; }
            $poe = min($max - qtditem($s), $falta);
            $novo[$k] = itemcomqtd($base, qtditem($s) + $poe); $falta -= $poe;
        }
    }
    while ($falta > 0) {
        if (count($novo) >= $limite) { return false; }
        $poe = min($max, $falta);
        $novo[] = itemcomqtd($base, $poe); $falta -= $poe;
    }
    $itens = $novo;
    return true;

}

function separaritem($texto) { // "nome,id,tipo,durabilidade" -> array de 4 posições (sem índices indefinidos).

    return array_slice(array_pad(explode(",", (string)$texto), 4, ""), 0, 4); // a quantidade (5º campo) é lida com qtditem().

}

function mochilalivre($row) { // Primeiro espaço vazio da mochila ("bp1".."bp8") ou "" se estiver cheia.

    for ($i = 1; $i <= 8; $i++) {
        if ($row["bp".$i] == "None") { return "bp".$i; }
    }
    return "";

}

function durabilidadetexto($durab) { // Monta de volta o campo durabilidade "-,a,b,c,d,e,f,g".

    $durab = array_pad($durab, 8, "X");
    return "-,".$durab[1].",".$durab[2].",".$durab[3].",".$durab[4].",".$durab[5].",".$durab[6].",".$durab[7];

}

function colunasstatus() { // Colunas de atributos que os equipamentos podem alterar (lista branca usada no UPDATE).

    return array("goldbonus","expbonus","maxhp","maxmp","maxtp","strength","dexterity","attackpower","defensepower",
        "agilidade","determinacao","precisao","sorte","inteligencia","droprate","maxnp","maxep",
        "critico","esquiva","regenhp","regenmp","regentp","regennp","regenep","economiachakra");

}

function bancobonusitem(&$row, $tipo, $iditem, $nomeitem, $sinal) { // Soma ($sinal = 1) ou retira ($sinal = -1) os bônus de um equipamento.

    // Tipos 1..3 (tabela items): "attribute" é o ataque (arma, type 1) ou a defesa (colete/bandana);
    // "special" é "coluna,valor" ou "X". O refinamento "+N" no nome vale N*20 de ataque/defesa.
    // Até 3 bônus por item: special, special2, special3.
    // Tipos 4..6 (tabela drops): attribute1, attribute2 e attribute3 no formato "coluna,valor" ou "X".
    // Como no resto do jogo, bônus de strength também mexem no ataque e de dexterity na defesa.
    $especiais = array();
    if ($tipo >= 1 && $tipo <= 3) {
        $itemsrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($iditem)."' LIMIT 1", "items"));
        if (!$itemsrow) { return; }
        $refino = explode("+", separarvariante($nomeitem)[0]);
        $bonusrefino = isset($refino[1]) ? intval($refino[1]) * 20 : 0;
        $campo = ($itemsrow["type"] == 1) ? "attackpower" : "defensepower";
        $row[$campo] += $sinal * (intval($itemsrow["attribute"]) + $bonusrefino);
        foreach (array("special", "special2", "special3") as $c) { if (isset($itemsrow[$c])) { $especiais[] = $itemsrow[$c]; } }
    } elseif ($tipo >= 4 && $tipo <= 6) {
        $droprow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($iditem)."' LIMIT 1", "drops"));
        if (!$droprow) { return; }
        foreach (array("attribute1", "attribute2", "attribute3") as $c) { if (isset($droprow[$c])) { $especiais[] = $droprow[$c]; } }
    }
    // Variante rara (sufixo ":efeito" no nome): bônus extra, ver variantesitens() no funcoesinclusas.php.
    if (isset($itemsrow) && $itemsrow) { $especiais = array_merge($especiais, bonusvariante($nomeitem, $itemsrow, "items")); }
    elseif (isset($droprow) && $droprow) { $especiais = array_merge($especiais, bonusvariante($nomeitem, $droprow, "drops")); }

    foreach ($especiais as $especial) {
        if ($especial == "X" || $especial == "") { continue; }
        $atributo = explode(",", $especial);
        $atributo[0] = trim($atributo[0]);
        if (!isset($atributo[1]) || !in_array($atributo[0], colunasstatus())) { continue; }
        $valor = intval($atributo[1]);
        $row[$atributo[0]] += $sinal * $valor;
        if ($atributo[0] == "strength") { $row["attackpower"] += $sinal * $valor; }
        elseif ($atributo[0] == "dexterity") { $row["defensepower"] += $sinal * $valor; }
    }

}

function sqlstatus($row) { // Parte "coluna='valor'," do UPDATE com todos os atributos de equipamento.

    // As colunas são UNSIGNED (exceto os bônus de Ryou/Exp.): um valor negativo derrubaria o UPDATE no modo estrito.
    $sql = "";
    foreach (colunasstatus() as $coluna) {
        $valor = intval($row[$coluna]);
        if ($valor < 0 && $coluna != "goldbonus" && $coluna != "expbonus") { $valor = 0; }
        $sql .= $coluna."='".$valor."',";
    }
    return $sql;

}

function sqlequipamentos($row) { // Parte "coluna='valor'," do UPDATE com mochila, equipamentos e durabilidade.

    $sql = "";
    for ($i = 1; $i <= 8; $i++) { $sql .= "bp".$i."='".addslashes($row["bp".$i])."',"; }
    foreach (array("weapon","armor","shield","slot1","slot2","slot3","slot4") as $slot) {
        $sql .= $slot."name='".addslashes($row[$slot."name"])."',".$slot."id='".intval($row[$slot."id"])."',";
    }
    $sql .= "durabilidade='".addslashes($row["durabilidade"])."',";
    return $sql;

}


/* ------------------------------------------------------------------------
   Página do banco e depósito/saque de Ryou.
   ------------------------------------------------------------------------ */

function bancoryou() { // Formulário "Depositar Ryou" / "Retirar Ryou" (os dois campos vêm no mesmo formulário).

    global $userrow;
    travarjogador();

    $deposito = trim(pegartexto($_POST, "deposito"));
    $retirar = trim(pegartexto($_POST, "retirar"));
    if ($deposito != "" && !is_numeric($deposito)) { bancovoltar("&f=3"); }
    if ($retirar != "" && !is_numeric($retirar)) { bancovoltar("&f=7"); }

    $maximocoluna = 16777215; // maior valor das colunas gold/banco_grana (MEDIUMINT UNSIGNED).
    $ouro = intval($userrow["gold"]);
    $ryoudepositado = intval($userrow["banco_grana"]);
    $depositado = 0;
    $retirado = 0;
    $hacont = 0; // 1 = o depósito foi reduzido pela regra dos 90%.

    // DEPOSITAR
    if ($deposito != "") {
        $deposito = floor((float)$deposito);
        if ($deposito < 1) { bancovoltar("&f=4"); }
        if ($ouro < 1) { bancovoltar("&f=6"); }
        // O banco só pode ter até 90% do Ryou total (o que está no banco + o que está no personagem).
        $porcentagemconta = min(floor(90 * ($ouro + $ryoudepositado) / 100), $maximocoluna);
        if ($ryoudepositado + $deposito > $porcentagemconta) {
            $deposito = $porcentagemconta - $ryoudepositado;
            $hacont = 1;
        }
        if ($deposito < 1) { bancovoltar("&f=5"); }
        if ($deposito > $ouro) { $deposito = $ouro; } // não dá para depositar mais do que se carrega.
        $depositado = intval($deposito);
        $ryoudepositado += $depositado;
        $ouro -= $depositado;
    }

    // RETIRAR
    if ($retirar != "") {
        $retirar = floor((float)$retirar);
        if ($retirar < 1) { bancovoltar("&f=8"); }
        if ($retirar > 99999) { bancoaviso("br", t("Voce nao pode retirar mais que 99999 Ryou.")); }
        if ($retirar > $ryoudepositado) { bancoaviso("br", t("Voce nao pode retirar mais que seu dinheiro no banco.")); }
        if ($ouro + $retirar > $maximocoluna) { bancoaviso("br", tf("Voce nao pode carregar mais que %s Ryou.", $maximocoluna)); }
        $retirado = intval($retirar);
        $ryoudepositado -= $retirado;
        $ouro += $retirado;
    }

    doquery("UPDATE {{table}} SET gold='".$ouro."', banco_grana='".$ryoudepositado."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    confirmartransacao();

    $frase = tf("Voce depositou %s Ryou e retirou %s Ryou do Banco.", $depositado, $retirado);
    if ($hacont == 1) { bancovoltar("&hacont=1&depositovar=".$depositado."&br=".urlencode($frase)); }
    bancoaviso("br", $frase);

}

function banco() { // Página do banco: Ryou, itens equipados, mochila e itens guardados (com os formulários de doar/deletar).

    global $userrow;
    bancoverificar();

    if (isset($_POST["submit"])) { bancoryou(); }

    $f = pegarint($_GET, "f");
    $bi = pegartexto($_GET, "bi");
    $br = pegartexto($_GET, "br");
    $ry = pegartexto($_GET, "ry");
    $hacont = pegartexto($_GET, "hacont");
    $depositovar = pegarint($_GET, "depositovar");
    $enviar = pegartexto($_GET, "enviar");
    $deletar = pegartexto($_GET, "deletar");

    $usuriologadodinheiro = $userrow["gold"];
    $ryoudepositado = $userrow["banco_grana"];
    $porcentagemconta = floor(90 * ($usuriologadodinheiro + $ryoudepositado) / 100);
    $itensbanco = bancoitens($userrow["bancogeral"]);

    // "qual" só vale se apontar para um item que existe no banco.
    $qual = pegarint($_GET, "qual", -1);
    if ($qual < 0 || $qual >= count($itensbanco)) { $qual = -1; }

    // Tabela dos itens equipados (1 arma, 2 colete, 3 bandana, 4..7 acessórios).
    $duraequip = array_pad(explode(",", $userrow["durabilidade"]), 8, "X");
    $tabeladeequipamentos = "<table>
    <tr bgcolor=\"#452202\"><td colspan=3><font color=white>".t("Itens Equipados")."</font></td></tr>
    <tr bgcolor=\"#613003\"><td><font color=white>*</font></td><td><font color=white>".t("Nome")."</font></td><td><font color=white>*</font></td></tr>";
    $slots = slotsequipamento();
    for ($i = 1; $i <= 7; $i++) {
        if ($i == 1) { $img = "icon_weapon"; } elseif ($i == 2) { $img = "icon_armor"; } elseif ($i == 3) { $img = "icon_shield"; } else { $img = "orb"; }
        $bgcolor = ($i % 2 == 0) ? "#E4D094" : "#FFF1C7";
        $dur = ($duraequip[$i] == "X") ? "*" : $duraequip[$i];
        $tabeladeequipamentos .= "<tr bgcolor=\"$bgcolor\"><td><img src=\"images/$img.gif\" title=\"".tf("Durabilidade: %s", $dur)."\" /></td><td>".nomeitem($userrow[$slots[$i]."name"])."</td><td><a href=\"funcaoitens.php?do=depositar&qual=$i\"> <img border=\"0\" src=\"images/setapdireita.gif\" title=\"".t("Depositar Item")."\" alt=\"->\" /></a></td></tr>";
    }
    $tabeladeequipamentos .= "</table>";

    // Tabela da mochila (bp1..bp8).
    $tabelabackpack = "<table>
    <tr bgcolor=\"#452202\"><td colspan=3><font color=white>".t("Mochila")."</font></td></tr>
    <tr bgcolor=\"#613003\"><td><font color=white>*</font></td><td><font color=white>".t("Nome")."</font></td><td><font color=white>*</font></td></tr>";
    for ($j = 1; $j <= 8; $j++) {
        $bgcolor = ($j % 2 == 0) ? "#E4D094" : "#FFF1C7";
        $img = "orb_img"; $dur = "";
        $mochilaseparar = separaritem($userrow["bp".$j]);
        if ($userrow["bp".$j] != "None") { iconeitemmochila($mochilaseparar, $img, $dur); }
        $tabelabackpack .= "<tr bgcolor=\"$bgcolor\"><td><img src=\"images/$img.gif\" title=\"".tf("Durabilidade: %s", $dur)."\"/></td><td>".nomeitem($mochilaseparar[0]).(qtditem($userrow["bp".$j]) > 1 ? " <b>×".qtditem($userrow["bp".$j])."</b>" : "")."</td><td><a href=\"funcaoitens.php?do=depositarbp&qual=$j\"> <img border=\"0\" src=\"images/setapdireita.gif\" title=\"".t("Depositar Item")."\" alt=\"->\" /></a></td></tr>";
    }
    $tabelabackpack .= "</table>";

    // Tabela dos itens guardados no banco: retirar, mandar para a mochila, doar e deletar.
    $tabeladobanco = "<table>
    <tr bgcolor=\"#452202\"><td colspan=6><font color=white>".t("Itens no Banco")." (".count($itensbanco)."/".bancolimite($userrow).")</font></td></tr>
    <tr bgcolor=\"#613003\"><td><font color=white>*</font></td><td><font color=white>*</font></td><td><font color=white>*</font></td><td><font color=white>*</font></td><td><font color=white>*</font></td><td><font color=white>".t("Nome")."</font></td></tr>";
    if (count($itensbanco) > 0) {
        foreach ($itensbanco as $i => $itemtexto) {
            $itemseparado = separaritem($itemtexto);
            $img = "orb_img"; $dur = "";
            iconeitemmochila($itemseparado, $img, $dur);
            $bgcolor = ($i % 2 == 0) ? "#E4D094" : "#FFF1C7";
            $tabeladobanco .= "<tr bgcolor=\"$bgcolor\"><td><a href=\"funcaoitens.php?do=retirar&qual=$i\"><img src=\"images/setapesquerda.gif\" border=\"0\" title=\"".t("Retirar Item")."\" alt=\"<-\" /></a></td><td><a href=\"funcaoitens.php?do=diretoparabp&qual=$i\"><img src=\"images/backpack_pequena.gif\" border=\"0\" title=\"".t("Enviar Para Mochila")."\" alt=\"X\"></a></td>
    <td><a href=\"funcaoitens.php?do=banco&qual=$i&enviar=ok&h=".bancoitemcodigo($itemtexto)."\" title=\"".t("Doar Item")."\"><img src=\"images/doacao.png\" alt=\"X\" border=\"0\" /></a></td><td><a href=\"funcaoitens.php?do=banco&qual=$i&h=".bancoitemcodigo($itemtexto)."&deletar=".urlencode($itemseparado[0])."\"><img src=\"images/deletar.gif\" border=\"0\" title=\"".t("Deletar Item")."\" alt=\"X\"></a></td>
    <td><img src=\"images/$img.gif\" title=\"".tf("Durabilidade: %s", $dur)."\" /></td>
    <td>".nomeitem($itemseparado[0]).(qtditem($itemtexto) > 1 ? " <b>×".qtditem($itemtexto)."</b>" : "")."</td></tr>";
        }
    } else {
        $tabeladobanco .= "<tr bgcolor=\"#E4D094\"><td>*</td><td>*</td><td>*</td><td>*</td><td>*</td><td>".t("Nenhum Item")."</td></tr>";
    }
    $tabeladobanco .= "</table>";

    // Caixa "Doar Item Para (Jogador)".
    if ($qual >= 0 && $enviar != "") {
        $tabeladobanco .= "<br><br><center><table border=\"0\"><tr bgcolor=\"#452202\"><td width=\"*\"><font color=white>".t("Doar Item Para(Jogador):")."</font></td><td width=\"14\"><a href=\"funcaoitens.php?do=banco\"><img border=\"0\" src=\"images/deletar2.jpg\" title=\"".t("Cancelar")."\"></a></td></tr>
    <tr bgcolor=\"#FFF1C7\"><td colspan=\"2\"><form action=\"funcaoitens.php?do=enviaritem&qual=$qual&h=".bancoitemcodigo($itensbanco[$qual])."\" method=\"post\">".csrf_campo()."
    <input type=\"text\" name=\"jogador\" size=\"20\" /> <button type=\"submit\" name=\"submit\" value=\"OK!\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_enviar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Enviar")."</button></form></td></tr></table></center>";
    }

    // Caixa de confirmação para deletar (o nome mostrado vem do banco, não da URL).
    if ($qual >= 0 && $deletar != "") {
        $itemdeletar = separaritem($itensbanco[$qual]);
        $tabeladobanco .= "<br><br><center><table width=\"190\"><tr bgcolor=\"#613003\"><td><font color=white>".t("Deletar")."</font></td></tr>
    <tr bgcolor=\"#FFF1C7\"><td>".tf("Voce tem certeza que deseja deletar o item %s ?", nomeitem($itemdeletar[0]))."<br><center>
    <a href=\"funcaoitens.php?do=deletaritem&qual=$qual&h=".bancoitemcodigo($itensbanco[$qual])."&csrf=".csrf_token()."\"><img border=\"0\" src=\"images/aceitar.gif\" title=\"".t("Deletar Item")."\" alt=\"X\"></a><a href=\"funcaoitens.php?do=banco\"><img border=\"0\" src=\"images/deletar.gif\" title=\"".t("Nao Deletar Item")."\" alt=\"X\"></a></center></td></tr></table></center>";
    }

    // Mensagens (f = código fixo; bi/br = texto da última ação nos itens / no Ryou).
    $mostraritemenviado = "";
    $mostrarpartecimaryou = "";
    if ($f == 1) { $mostraritemenviado = "<center><font color=brown>".t("Seu Item foi Enviado com Sucesso.")."</font></center><br>"; }
    if ($f == 3) { $mostrarpartecimaryou = "<center><font color=brown>".t("A quantidade de Ryou a depositar deve ser um numero.")."</font></center><br>"; }
    if ($f == 4) { $mostrarpartecimaryou = "<center><font color=brown>".t("Voce nao pode depositar menos que 1 Ryou.")."</font></center><br>"; }
    if ($f == 5) { $mostrarpartecimaryou = "<center><font color=brown>".tf("Voce nao pode ter uma quantia maior que %s Ryou no Banco, que representa 90%% do seu Ryou total, o que esta depositado e o que esta no seu personagem.", $porcentagemconta)."</font></center><br>"; }
    if ($f == 6) { $mostrarpartecimaryou = "<center><font color=brown>".t("Voce nao pode depositar mais que a sua quantidade de Ryou.")."</font></center><br>"; }
    if ($f == 7) { $mostrarpartecimaryou = "<center><font color=brown>".t("A quantidade de Ryou a retirar deve ser um numero.")."</font></center><br>"; }
    if ($f == 8) { $mostrarpartecimaryou = "<center><font color=brown>".t("Voce nao pode retirar menos que 1 Ryou.")."</font></center><br>"; }
    if ($f == 10) { $mostraritemenviado = "<center><font color=brown>".t("Item deletado com sucesso.")."</font></center><br>"; }

    if ($hacont == "") {
        if ($f == 0 && $bi != "") { $mostraritemenviado = "<center><font color=brown>$bi</font></center><br>"; }
        if ($f == 0 && $br != "") { $mostrarpartecimaryou = "<center><font color=brown>$br</font></center><br>"; }
    } else {
        // O depósito passou dos 90% e foi reduzido.
        $mostrarpartecimaryou .= "<center><font color=brown>".tf("Voce depositou um valor maior que 90%% de seu Ryou Total<i>(no Banco e Equipado)</i>, portanto foi depositado apenas <font color=red>%s</font> Ryou, que e a quantia maxima que voce pode depositar em seu Banco no momento.", $depositovar)."</font></center><br>";
    }

    // Caixa "Doar Ryou" (o Ryou doado sai do banco e vai para o Ryou equipado do outro jogador).
    $enviarryouaqui = "";
    if ($ry != "") {
        $enviarryouaqui = "<center><form action=\"funcaoitens.php?do=doarryou\" method=\"post\">".csrf_campo()."<table><tr bgcolor=\"#613003\"><td><font color=white>".t("Doar Ryou")."</font></td></tr>
    <tr bgcolor=\"#E4D094\"><td><center>".t("Doar Para(Jogador):")."</center></td></tr>
    <tr bgcolor=\"#FFF1C7\"><td><input type=\"text\" name=\"jogador\" size=\"20\" /></td></tr>
    <tr bgcolor=\"#E4D094\"><td><center>".t("Quantidade(Ryou):")."</center></td></tr>
    <tr bgcolor=\"#FFF1C7\"><td><input type=\"text\" name=\"quantidaderyou\" size=\"20\" /></td></tr>
    <tr bgcolor=\"#E4D094\"><td><center><button type=\"submit\" name=\"submit\" value=\"OK!\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_vender.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Doar")."</button></center></td></tr>
    <tr bgcolor=\"#FFF1C7\"><td><center><a href=\"funcaoitens.php?do=banco\"><img src=\"images/deletar.gif\" border=\"0\" title=\"".t("Fechar essa caixa.")."\" alt=\"X\"></a></center></td></tr>
    </table></form></center>
    ";
    }

    $page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/banco.gif\" /></center></td></tr></table>
    $mostrarpartecimaryou
    <center>
    <table><tr><td>
    <table><tr bgcolor=\"#452202\"><td colspan=\"2\"><font color=white>".t("Ryou Equipado")."</font></td></tr>
    <tr bgcolor=\"#E4D094\"><td width=\"20\"><img src=\"images/ryou.png\" width=\"18\" height=\"18\" title=\"".t("Ryou Equipado")."\"></td><td>$usuriologadodinheiro</td></tr></table>
    </td><td>
    <table><tr bgcolor=\"#452202\"><td colspan=\"3\"><font color=white>".t("Ryou no Banco")."</font></td></tr>
    <tr bgcolor=\"#E4D094\"><td width=\"20\"><a href=\"funcaoitens.php?do=banco&ry=1\"><img src=\"images/doacao.png\" border=\"0\" title=\"".t("Doar Ryou")."\"></a></td><td width=\"20\"><img src=\"images/ryou.png\" width=\"18\" height=\"18\" title=\"".t("Ryou no Banco")."\"></td><td>$ryoudepositado</td></tr></table>
    </td></tr></table>
    </center>

    ".bancotemplate()."$enviarryouaqui
    <table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/bancodeitens.gif\" /></center></td></tr></table>
    $mostraritemenviado
    <center><table><tr><td>
    $tabeladeequipamentos<br>
    $tabelabackpack
    </td><td>
    $tabeladobanco
    </td></tr></table></center>
    ";

    display($page, t("Banco"), false, false, false);

}


/* ------------------------------------------------------------------------
   Itens: equipamento/mochila -> banco, banco -> equipamento/mochila.
   ------------------------------------------------------------------------ */

function depositar() { // Guarda no banco um item que está EQUIPADO (qual = 1 arma, 2 colete, 3 bandana, 4..7 acessórios).

    global $userrow;
    bancoverificar();
    $qual = pegarint($_GET, "qual");
    if ($qual < 1 || $qual > 7) { bancotrapaca(); }
    travarjogador();

    $itens = bancoitens($userrow["bancogeral"]);
    $limite = bancolimite($userrow);

    $slots = slotsequipamento();
    $tipobanco = ($qual <= 3) ? $qual : 4; // acessório guardado como drop (tipo 4), como na mochila.
    $nome = $slots[$qual]."name";
    $id = $slots[$qual]."id";
    if ($userrow[$nome] == "None" || intval($userrow[$id]) == 0) { bancovoltar(); } // nada equipado ali.

    // Tira os bônus do item do personagem.
    bancobonusitem($userrow, $tipobanco, $userrow[$id], $userrow[$nome], -1);

    // Guarda o item no banco junto com a durabilidade que ele tinha e libera a posição na durabilidade.
    $durab = array_pad(explode(",", $userrow["durabilidade"]), 8, "X");
    if (!bancoguardar($itens, $userrow[$nome].",".intval($userrow[$id]).",".$tipobanco.",".$durab[$qual], $limite)) { bancoaviso("bi", tf("Voce nao pode ter mais que %s itens no Banco.", $limite)); }
    $durab[$qual] = "X";
    $userrow["durabilidade"] = durabilidadetexto($durab);
    $userrow["bancogeral"] = bancotexto($itens);
    $userrow[$nome] = "None";
    $userrow[$id] = 0;

    conjuntosrecalcular($userrow); // bônus de conjuntos com as peças equipadas agora.
    doquery("UPDATE {{table}} SET ".sqlstatus($userrow).sqlequipamentos($userrow).sqlconjuntobonus($userrow)." bancogeral='".addslashes($userrow["bancogeral"])."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    confirmartransacao();
    bancovoltar();

}

function retirar() { // Tira um item do banco: equipa se o lugar dele estiver livre, senão manda para a mochila.

    global $userrow;
    bancoverificar();
    travarjogador();

    $itens = bancoitens($userrow["bancogeral"]);
    $qual = pegarint($_GET, "qual", -1);
    if ($qual < 0 || $qual >= count($itens)) { bancotrapaca(); }

    $item = separaritem($itens[$qual]);
    $tipo = intval($item[2]);
    $destino = "";   // prefixo da coluna onde o item será equipado ("weapon", "slot2"...).
    $durpos = 0;     // posição correspondente no campo durabilidade.

    if (qtditem($itens[$qual]) > 1) { // pilha: vai inteira para a mochila (juntando com pilhas iguais).
        $mochilacheia = t("Voce nao possui espacos livres na sua Mochila. Libere algum espaco.");
    } elseif (is_numeric($item[1]) && $tipo >= 1 && $tipo <= 6) { // equipamento.
        if ($tipo <= 3) { // arma, colete ou bandana: cada um tem o seu lugar.
            $slots = array(1 => "weapon", 2 => "armor", 3 => "shield");
            $frases = array(1 => t("Arma"), 2 => t("Colete"), 3 => t("Bandana"));
            if (intval($userrow[$slots[$tipo]."id"]) == 0) { $destino = $slots[$tipo]; $durpos = $tipo; }
            $mochilacheia = tf("Voce ja possui um(a) %s equipado(a) e nao possui espacos livres na sua Mochila. Libere algum espaco.", $frases[$tipo]);
        } else { // drop: vai para o primeiro acessório livre.
            for ($s = 1; $s <= NUMACESSORIOS; $s++) {
                if (intval($userrow["slot".$s."id"]) == 0) { $destino = "slot".$s; $durpos = $s + 3; break; }
            }
            $mochilacheia = t("Voce nao possui Slots Livres para Equipar e tambem nao possui espaco livre em sua Mochila. Libere algum espaco.");
        }
    } else { // item que só fica na mochila (poções, pergaminhos...).
        $mochilacheia = t("Voce nao possui espacos livres na sua Mochila. Libere algum espaco.");
    }

    if ($destino == "") { // não dá para equipar: vai para a mochila (junta numa pilha igual, se houver lugar).
        if (mochilaguardar($userrow, $itens[$qual]) === false) { bancoaviso("bi", $mochilacheia); }
    } else { // equipa o item e soma os bônus dele.
        bancobonusitem($userrow, $tipo, $item[1], $item[0], 1);
        if (atributosnegativos($userrow)) { bancoaviso("bi", t("O lado negativo deste item deixaria um atributo seu abaixo de zero. Fique mais forte antes de equipá-lo.")); } // a transação é desfeita.
        $userrow[$destino."name"] = $item[0];
        $userrow[$destino."id"] = intval($item[1]);
        $durab = array_pad(explode(",", $userrow["durabilidade"]), 8, "X");
        $durab[$durpos] = $item[3];
        $userrow["durabilidade"] = durabilidadetexto($durab);
    }

    unset($itens[$qual]);
    $userrow["bancogeral"] = bancotexto(array_values($itens));

    conjuntosrecalcular($userrow); // bônus de conjuntos com as peças equipadas agora.
    doquery("UPDATE {{table}} SET ".sqlstatus($userrow).sqlequipamentos($userrow).sqlconjuntobonus($userrow)." bancogeral='".addslashes($userrow["bancogeral"])."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    confirmartransacao();
    bancovoltar();

}

function depositarbp() { // Guarda no banco um item da mochila (qual = 1..8).

    global $userrow;
    bancoverificar();
    $qual = pegarint($_GET, "qual");
    if ($qual < 1 || $qual > 8) { bancotrapaca(); }
    travarjogador();

    if ($userrow["bp".$qual] == "None") { bancovoltar(); }
    $itens = bancoitens($userrow["bancogeral"]);
    $limite = bancolimite($userrow);
    // Pilhas: junta com uma pilha igual do banco (até o máximo); o resto ocupa uma entrada nova.
    if (!bancoguardar($itens, $userrow["bp".$qual], $limite)) { bancoaviso("bi", tf("Voce nao pode ter mais que %s itens no Banco.", $limite)); }
    $userrow["bancogeral"] = bancotexto($itens);

    doquery("UPDATE {{table}} SET bancogeral='".addslashes($userrow["bancogeral"])."', bp".$qual."='None' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    confirmartransacao();
    bancovoltar();

}

function diretoparabp() { // Manda um item do banco direto para a mochila (sem equipar).

    global $userrow;
    bancoverificar();
    travarjogador();

    $itens = bancoitens($userrow["bancogeral"]);
    $qual = pegarint($_GET, "qual", -1);
    if ($qual < 0 || $qual >= count($itens)) { bancotrapaca(); }

    $espacos = mochilaguardar($userrow, $itens[$qual]); // junta numa pilha igual ou usa espaços livres.
    if ($espacos === false) { bancoaviso("bi", t("Voce nao possui Slots Livres para Equipar e tambem nao possui espaco livre em sua Mochila. Libere algum espaco.")); }

    unset($itens[$qual]);
    $userrow["bancogeral"] = bancotexto(array_values($itens));

    doquery("UPDATE {{table}} SET ".sqlmochila($userrow, $userrow, $espacos)[0].", bancogeral='".addslashes($userrow["bancogeral"])."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    confirmartransacao();
    bancovoltar();

}

function deletaritem() { // Apaga de vez um item do banco (depois da confirmação na página do banco).

    global $userrow;
    bancoverificar();
    if (!csrf_confere()) { bancotrapaca(); } // Link de exclusão precisa do token (evita apagar itens por link externo).
    travarjogador();

    $itens = bancoitens($userrow["bancogeral"]);
    $qual = pegarint($_GET, "qual", -1);
    if ($qual < 0 || $qual >= count($itens)) { bancotrapaca(); }
    bancoconferircodigo($itens, $qual);

    unset($itens[$qual]);
    $userrow["bancogeral"] = bancotexto(array_values($itens));

    doquery("UPDATE {{table}} SET bancogeral='".addslashes($userrow["bancogeral"])."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    confirmartransacao();
    bancovoltar("&f=10");

}


/* ------------------------------------------------------------------------
   Envios para outros jogadores.
   ------------------------------------------------------------------------ */

function bancobuscarjogador($nome, $campo) { // Procura o destinatário pelo nome; volta ao banco com erro se não existir.

    $nome = trim($nome);
    $query = doquery("SELECT id FROM {{table}} WHERE charname='".$nome."' LIMIT 1", "users");
    if ($nome == "" || mysqli_num_rows($query) != 1) {
        if ($campo == "bi") { bancoaviso("bi", t("Nao existe nenhum Jogador com esse Nome para Doar o Item.")); }
        bancoaviso("br", t("Nao existe nenhum jogador com esse Nome."));
    }
    $row = mysqli_fetch_array($query);
    return intval($row["id"]);

}

function enviaritem() { // Envia um item do MEU banco para o banco de outro jogador (e avisa no histórico dele).

    global $userrow;
    bancoverificar();
    if (!isset($_POST["submit"])) { bancovoltar(); }
    if (!csrf_confere()) { bancotrapaca(); } // Envio para outro jogador: só pelo formulário do próprio banco (CSRF).

    $qual = pegarint($_GET, "qual", -1);
    $idpara = bancobuscarjogador(pegartexto($_POST, "jogador"), "bi");
    if ($idpara == intval($userrow["id"])) { bancoaviso("bi", t("Voce nao pode doar item para voce mesmo.")); }

    // Trava os dois jogadores: o item sai de um e entra no outro na mesma transação.
    $linhas = travarjogadores(array($userrow["id"], $idpara));
    if (!isset($linhas[$idpara])) { bancoaviso("bi", t("Nao existe nenhum Jogador com esse Nome para Doar o Item.")); }
    $userrow = $linhas[intval($userrow["id"])];
    $userpara = $linhas[$idpara];

    $itens = bancoitens($userrow["bancogeral"]);
    if (count($itens) == 0) { bancoaviso("bi", t("Voce nao pode doar Itens, voce nao tem nenhum no Banco.")); }
    if ($qual < 0 || $qual >= count($itens)) { bancotrapaca(); }
    bancoconferircodigo($itens, $qual);

    $itenspara = bancoitens($userpara["bancogeral"]);
    $limite = bancolimite($userpara);

    // Move o item (uma pilha vai inteira e junta com uma pilha igual do outro jogador).
    $itemenviado = $itens[$qual];
    if (!bancoguardar($itenspara, $itemenviado, $limite)) { bancoaviso("bi", tf("O Jogador o qual voce quer enviar o Item, possui mais de %s Itens no Banco.", $limite)); }
    unset($itens[$qual]);
    $userrow["bancogeral"] = bancotexto(array_values($itens));
    $userpara["bancogeral"] = bancotexto($itenspara);

    // Histórico do destinatário: mensagens separadas por ";;" ("None" = vazio).
    $nomeitem = separaritem($itemenviado);
    // Formato neutro i18n:... (quem lê pode estar em outro idioma; a tela do histórico traduz com textoi18n()).
    $aviso = i18n("O jogador %s o enviou o item <font color=\"brown\">%s</font>, que se encontra em seu banco.", $userrow["charname"], str_replace(":", " ✦ ", $nomeitem[0]));
    $historico = ($userpara["historico"] == "None") ? $aviso : $userpara["historico"].";;".$aviso;

    doquery("UPDATE {{table}} SET bancogeral='".addslashes($userrow["bancogeral"])."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    doquery("UPDATE {{table}} SET bancogeral='".addslashes($userpara["bancogeral"])."', historico='".addslashes($historico)."' WHERE id='".intval($userpara["id"])."' LIMIT 1", "users");
    confirmartransacao();
    bancovoltar("&f=1");

}

function doarryou() { // Doa Ryou do MEU banco para o Ryou equipado de outro jogador (só a partir do level 5).

    global $userrow;
    bancoverificar();
    if ($userrow["level"] < 5) { header("Location: ./index.php?conteudo=".rawurlencode(t("Voce nao pode acessar essa funcao se seu level for menor que 5!"))); die(); }
    if (!isset($_POST["submit"])) { bancovoltar(); }
    if (!csrf_confere()) { bancotrapaca(); } // Doação para outro jogador: só pelo formulário do próprio banco (CSRF).

    $idpara = bancobuscarjogador(pegartexto($_POST, "jogador"), "br");
    if ($idpara == intval($userrow["id"])) { bancoaviso("br", t("Voce nao pode doar Ryou para si mesmo.")); }
    $quantidaderyou = trim(pegartexto($_POST, "quantidaderyou"));
    if (!is_numeric($quantidaderyou)) { bancoaviso("br", t("A quantidade de Ryou deve ser um numero.")); }
    $quantidaderyou = floor((float)$quantidaderyou);

    $linhas = travarjogadores(array($userrow["id"], $idpara));
    if (!isset($linhas[$idpara])) { bancoaviso("br", t("Nao existe nenhum jogador com esse Nome.")); }
    $userrow = $linhas[intval($userrow["id"])];
    $userpara = $linhas[$idpara];

    if ($quantidaderyou > $userrow["banco_grana"]) { bancoaviso("br", t("Voce nao pode doar mais do que a sua quantidade de Ryou no banco.")); }
    if ($quantidaderyou < 1) { bancoaviso("br", t("Voce nao pode doar menos que 1 Ryou.")); }
    $quantidaderyou = intval($quantidaderyou);
    if (intval($userpara["gold"]) + $quantidaderyou > 16777215) { bancoaviso("br", t("O outro jogador nao pode carregar tanto Ryou.")); }

    doquery("UPDATE {{table}} SET gold=gold+".$quantidaderyou." WHERE id='".intval($userpara["id"])."' LIMIT 1", "users");
    doquery("UPDATE {{table}} SET banco_grana=banco_grana-".$quantidaderyou." WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    confirmartransacao();
    bancoaviso("br", t("Ryou enviado com sucesso."));

}

?>
