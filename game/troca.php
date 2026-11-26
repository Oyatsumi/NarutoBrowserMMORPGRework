<?php // troca.php :: Troca de um item da mochila e/ou Ryou entre dois jogadores, com confirmação dos dois lados.

/*  COMO A TROCA FUNCIONA (campos da tabela users)

    trocajogador1 = a oferta que EU fiz:       "NomeDoOutro;item;ryou;espaço"
    trocajogador2 = a oferta que EU recebi:    "MeuNome;item;ryou;espaço"
        item ..... o texto do item da mochila de quem oferece ("nome,id,tipo,durabilidade") ou "None" (só Ryou).
        ryou ..... quantidade de Ryou oferecida (0 = nenhum).
        espaço ... espaço da mochila de quem RECEBE que estava livre na hora da proposta ("bp1".."bp8") ou "None".
    "None" nos dois campos = nenhuma troca em andamento.

    Quando A propõe para B, a mesma oferta é gravada em A.trocajogador1 e em B.trocajogador2. B responde com a
    oferta dele (B.trocajogador1 = A.trocajogador2). A troca está montada quando as duas ofertas batem:
    A.trocajogador1 == B.trocajogador2  e  A.trocajogador2 == B.trocajogador1.

    trocaswitch: 0 = não confirmou;  1 = confirmou a troca que estava na tela;
                 2 = a troca foi concluída pelo outro jogador (só para avisar "Troca realizada com sucesso").
    trocaate ... prazo da troca (UNIX), gravado nos dois jogadores a cada proposta: depois de TROCA_MINUTOS sem
                 concluir, a troca expira (0 = sem prazo, trocas montadas antes desta coluna existir).

    Pilhas: o item oferecido pode levar a quantidade ("Incenso Repelente,rep,100,X,3" = 3 de uma pilha maior).
    Na conclusão, sai essa quantidade da pilha de quem oferece (o resto fica) e quem recebe junta numa pilha igual
    ou usa um espaço livre (mochilaguardar()). O "espaço" da oferta continua sendo gravado, só como referência.

    Trava depois do aceite: depois que um dos dois aceitou, nenhum dos dois pode mudar a oferta (troca2 recusa);
    para mudar, é preciso cancelar e propor de novo. Assim ninguém aceita uma coisa e recebe outra.

    Passo a passo: troca (tela) -> troca2 (envia a proposta) -> troca&html=1 (mostra as duas ofertas)
                   -> troca3 (aceitar) -> troca&html=2 (espera o outro aceitar) -> troca4 (executa a troca).

    Segurança: toda ação que grava trava as linhas dos dois jogadores (SELECT ... FOR UPDATE) e confere tudo de
    novo (itens ainda na mochila, Ryou suficiente, espaço livre). Qualquer nova proposta zera as confirmações e
    o "aceitar" leva um código da oferta que estava na tela, para o outro não trocar a oferta na última hora.
*/

include('lib.php');
$link = opendb();

include('cookies.php');
$userrow = checkcookies();

include_once('funcoesinclusas.php'); // ícones, pilhas (qtditem, mochilaguardar) e caixa de explicação dos itens.

if (!defined('TROCA_MINUTOS')) { define('TROCA_MINUTOS', 10); } // prazo (minutos) de uma troca sem conclusão.

$do = pegartexto($_GET, "do");
if ($do == "troca") { troca(); }
elseif ($do == "troca2") { troca2(); }
elseif ($do == "troca3") { troca3(); }
elseif ($do == "troca4") { troca4(); }
else { header("Location: ./troca.php?do=troca"); die(); }


/* ------------------------------------------------------------------------
   Funções auxiliares da troca.
   ------------------------------------------------------------------------ */

function trocaverificar() { // Confere se o jogador está logado e fora de batalha.

    global $userrow;
    if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
    if ($userrow["currentaction"] == "Fighting") { header("Location: ./index.php?do=fight&conteudo=".rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }
    if (emduelo($userrow)) { header("Location: ./users.php?do=duelo&conteudo=".rawurlencode(t("Você não pode trocar itens durante um duelo."))); die(); }

}

function emduelo($row) { // true se o jogador está num duelo PvP (marca batalha_timer2 = 5 ou duelo ativo na tabela duelos).

    if (intval($row["batalha_timer2"]) == 5) { return true; }
    $id = intval($row["id"]);
    return mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE (desafiante='$id' OR desafiado='$id') AND estado='ativo' LIMIT 1", "duelos")) > 0;

}

function trocamsg($texto, $extra = "") { // Volta para a tela de troca mostrando uma mensagem (o texto em português é traduzido aqui).

    header("Location: ./troca.php?do=troca".$extra."&msg=".rawurlencode(t($texto)));
    die();

}

function travarjogadores($ids) { // Abre uma transação e lê os jogadores com bloqueio de linha (SELECT ... FOR UPDATE).

    // As duas confirmações (ou dois cliques) chegando juntas ficam em fila aqui: a segunda só continua depois que a
    // primeira gravar (COMMIT), e aí já encontra a troca concluída. Se a página morrer antes do COMMIT, nada é gravado.
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

function separaroferta($texto) { // "nome;item;ryou;espaço" -> array de 4 posições.

    return array_pad(explode(";", (string)$texto, 4), 4, "None");

}

function nomeitem($nome) { // Nome do item para exibir no idioma do jogador ("Kunai+2": traduz "Kunai" e mantém o refino "+2").

    if ($nome == "None" || $nome == "") { return $nome; } // "None" é valor interno (espaço vazio), exibido como está.
    $v = separarvariante($nome); // variante rara: "Nome:efeito" (ver funcoesinclusas.php).
    $partes = explode("+", $v[0], 2);
    $texto = t($partes[0]).(isset($partes[1]) ? "+".$partes[1] : "");
    return ($v[1] == "") ? $texto : $texto." <font color=\"".variantesitens()[$v[1]]["cor"]."\"><b>✦ ".t(variantesitens()[$v[1]]["rotulo"])."</b></font>";

}

function separaritem($texto) { // "nome,id,tipo,durabilidade" -> array de 4 posições (sem índices indefinidos).

    return array_pad(explode(",", (string)$texto, 4), 4, "");

}

function trocaexpirou($row) { // true se a troca do jogador passou do prazo (trocaate > 0 e já vencido).

    return intval($row["trocaate"]) > 0 && intval($row["trocaate"]) < time();

}

function trocaicone($texto, $id) { // Ícone 34x34 do item oferecido (com a quantidade da pilha e a caixa de explicação) ou o ícone vazio.

    if ($texto == "None" || $texto == "") { return "<img src=\"images/gif.gif\" width=\"34\" height=\"34\" style=\"display:block;border:2px #e4d094 solid\" alt=\"\" />"; }
    $item = array_pad(explode(",", $texto), 4, "X");
    $icone = is_numeric($item[1]) ? iconeitem($item[1], intval($item[2]) > 3, $item[0]) : iconeconsumivel($item[1], $item[2], $item[0]);
    $explic = conteudoexplic($item[1], $item[2], $id, ($item[3] == "X" ? "*" : $item[3]), $item[0], qtditem($texto));
    return iconecomqtd("<img src=\"$icone\" width=\"34\" height=\"34\" style=\"display:block;border:2px #e4d094 solid\" id=\"$id\" onmouseover=\"$explic\" onmouseout=\"fecharexplic();\" alt=\"\" />", $texto);

}

function trocaorigem($row, $texto) { // Espaço da mochila de onde sai o item oferecido: mesmo item e quantidade suficiente ("" = não tem mais).

    $base = itemsemqtd($texto); $q = qtditem($texto);
    for ($i = 1; $i <= 8; $i++) {
        if ($row["bp".$i] != "None" && itemsemqtd($row["bp".$i]) === $base && qtditem($row["bp".$i]) >= $q) { return "bp".$i; }
    }
    return "";

}

function trocaacharparceiro($row) { // Jogador com quem eu estou trocando (nome gravado na minha oferta) ou false.

    if ($row["trocajogador1"] == "None") { return false; }
    $minha = separaroferta($row["trocajogador1"]);
    $query = doquery("SELECT * FROM {{table}} WHERE charname='".addslashes($minha[0])."' LIMIT 1", "users");
    $para = mysqli_fetch_array($query);
    if (!$para || $para["id"] == $row["id"]) { return false; }
    return $para;

}

function trocabate($eu, $para) { // true se as duas ofertas existem e são as mesmas nos dois jogadores.

    if (!$eu || !$para) { return false; }
    if ($eu["trocajogador1"] == "None" || $eu["trocajogador2"] == "None") { return false; }
    $minha = separaroferta($eu["trocajogador1"]);
    $recebida = separaroferta($eu["trocajogador2"]);
    if ($minha[0] != $para["charname"] || $recebida[0] != $eu["charname"]) { return false; }
    if (trocaexpirou($eu) || trocaexpirou($para)) { return false; } // passou do prazo (TROCA_MINUTOS).
    return ($eu["trocajogador1"] == $para["trocajogador2"] && $eu["trocajogador2"] == $para["trocajogador1"]);

}

function trocacodigo($row) { // Código da troca que está na tela (vai no link "aceitar"; muda se alguma oferta mudar).

    return md5($row["trocajogador1"]."|".$row["trocajogador2"]);

}

function trocadesfazer($eu, $para, $mensagem) { // Apaga a troca dos dois jogadores (dentro da transação aberta) e avisa.

    doquery("UPDATE {{table}} SET trocajogador1='None', trocajogador2='None', trocaswitch='0', trocaate='0' WHERE id IN ('".intval($eu["id"])."','".intval($para["id"])."') LIMIT 2", "users");
    confirmartransacao();
    trocamsg($mensagem);

}

function trocacancelar() { // Botão "cancelar": apaga a minha troca e a parte dela que está gravada no outro jogador.

    global $userrow;
    $para = trocaacharparceiro($userrow);
    $ids = array($userrow["id"]);
    if ($para) { $ids[] = $para["id"]; }
    $linhas = travarjogadores($ids);
    $eu = $linhas[intval($userrow["id"])];

    if ($para && isset($linhas[intval($para["id"])])) {
        $para = $linhas[intval($para["id"])];
        // Só mexe no outro jogador no que for desta troca (ele pode estar negociando com outra pessoa).
        $novo1 = ($para["trocajogador1"] == $eu["trocajogador2"]) ? "None" : $para["trocajogador1"];
        $novo2 = ($para["trocajogador2"] == $eu["trocajogador1"]) ? "None" : $para["trocajogador2"];
        if ($novo1 != $para["trocajogador1"] || $novo2 != $para["trocajogador2"]) {
            doquery("UPDATE {{table}} SET trocajogador1='".addslashes($novo1)."', trocajogador2='".addslashes($novo2)."', trocaswitch='0' WHERE id='".intval($para["id"])."' LIMIT 1", "users");
        }
    }
    doquery("UPDATE {{table}} SET trocajogador1='None', trocajogador2='None', trocaswitch='0' WHERE id='".intval($eu["id"])."' LIMIT 1", "users");
    confirmartransacao();
    trocamsg("A troca foi cancelada.");

}


/* ------------------------------------------------------------------------
   Telas e passos da troca.
   ------------------------------------------------------------------------ */

function troca() { // Tela da troca: formulário da proposta, ofertas montadas (html=1) e espera da confirmação (html=2).

    global $userrow;
    trocaverificar();

    if (pegartexto($_GET, "cancelar") != "") { trocacancelar(); }

    $jogador = pegartexto($_GET, "jogador");
    $msg = lermsg("msg");
    $html = pegarint($_GET, "html");
    if ($jogador != "") { $msg = tf("Para realizar uma troca com o jogador %s, pressione o botão Realizar Nova Troca.", $jogador); }

    $ryounomomento = $userrow["gold"];
    $htmlmais = "";

    // Troca vencida (TROCA_MINUTOS sem concluir): apaga a minha parte e avisa.
    if (($userrow["trocajogador1"] != "None" || $userrow["trocajogador2"] != "None") && trocaexpirou($userrow)) {
        doquery("UPDATE {{table}} SET trocajogador1='None', trocajogador2='None', trocaswitch='0', trocaate='0' WHERE id='".intval($userrow["id"])."' AND trocaate='".intval($userrow["trocaate"])."' LIMIT 1", "users");
        trocamsg("A troca expirou. Proponha de novo, se quiser.");
    }

    // Itens da mochila que podem ser oferecidos (pilhas: escolha quantas unidades).
    $tabelamochila = "";
    $g = 1;
    for ($i = 1; $i <= 8; $i++) {
        if ($userrow["bp".$i] == "None") { continue; }
        $itemseparado = separaritem($userrow["bp".$i]);
        $q = qtditem($userrow["bp".$i]);
        $g += 1;
        $bgcolor = ($g % 2 == 0) ? "#E4D094" : "#FFF1C7";
        $campoqtd = ($q > 1) ? " <input type=\"number\" name=\"qtd$i\" value=\"$q\" min=\"1\" max=\"$q\" style=\"width:42px\" title=\"".t("Quantidade")."\" /> / $q" : "";
        $tabelamochila .= "<tr bgcolor=\"$bgcolor\"><td><input type=\"radio\" id=\"qual$i\" name=\"qual\" value=\"$i\"></td>
    <td>".trocaicone($userrow["bp".$i], "trbp$i")."</td>
    <td><label for=\"qual$i\">".nomeitem($itemseparado[0])."</label>$campoqtd</td></tr>";
    }
    if ($tabelamochila == "") { $tabelamochila = "<tr><td></td><td></td><td>".t("Nenhum Item")."</td></tr>"; }
    else { $tabelamochila = "<tr bgcolor=\"#FFF1C7\"><td><input type=\"radio\" id=\"qual0\" name=\"qual\" value=\"0\" checked></td><td></td><td><label for=\"qual0\">".t("Nenhum item (só Ryou)")."</label></td></tr>".$tabelamochila; }

    // html=1: as duas ofertas estão montadas? Então mostra as duas lado a lado e pergunta se aceita; senão continua esperando.
    if ($html == 1) {
        $para = trocaacharparceiro($userrow);
        if (trocabate($userrow, $para)) {
            // A espera acabou (as duas ofertas chegaram): some o aviso "Aguardando...".
            if ($msg == t("Aguardando o outro jogador no processo de troca...") || $msg == t("Aguardando o outro jogador no processo de troca.")) { $msg = ""; }
            $minha = separaroferta($userrow["trocajogador1"]);
            $recebida = separaroferta($userrow["trocajogador2"]);
            $nome1 = ($minha[1] != "None") ? nomeitem(separaritem($minha[1])[0]).(qtditem($minha[1]) > 1 ? " ×".qtditem($minha[1]) : "") : "<font color=gray>".t("Nenhum Item")."</font>";
            $nome2 = ($recebida[1] != "None") ? nomeitem(separaritem($recebida[1])[0]).(qtditem($recebida[1]) > 1 ? " ×".qtditem($recebida[1]) : "") : "<font color=gray>".t("Nenhum Item")."</font>";
            $estado1 = (intval($userrow["trocaswitch"]) == 1) ? "<font color=darkgreen><b>".t("Aceitou")."</b></font>" : "<font color=gray>".t("Ainda não aceitou")."</font>";
            $estado2 = (intval($para["trocaswitch"]) == 1) ? "<font color=darkgreen><b>".t("Aceitou")."</b></font>" : "<font color=gray>".t("Ainda não aceitou")."</font>";
            $prazo = (intval($userrow["trocaate"]) > 0) ? "<tr><td colspan=\"5\" class=\"small\"><center>".tf("A troca expira em %s minuto(s) se não for concluída.", max(1, (int)ceil((intval($userrow["trocaate"]) - time()) / 60)))."</center></td></tr>" : "";
            $aviso = (intval($userrow["trocaswitch"]) == 1 || intval($para["trocaswitch"]) == 1) ? "<tr><td colspan=\"5\" class=\"small\"><center>".t("Depois do primeiro aceite as ofertas ficam travadas: para mudar algo, cancele a troca.")."</center></td></tr>" : "";

            $htmlmais = "<br><center><table><tr bgcolor=\"#613003\"><td colspan=\"5\"><font color=white><center>".t("Confira as duas ofertas")."</center></font></td></tr>
            <tr bgcolor=\"#613003\"><td colspan=\"2\"><font color=white>".tf("%s oferece:", $userrow["charname"])."</font></td><td></td><td colspan=\"2\"><font color=white>".tf("%s oferece:", $para["charname"])."</font></td></tr>
            <tr bgcolor=\"#FFF1C7\"><td>".trocaicone($minha[1], "troferta1")."</td><td>".$nome1."</td><td><img src=\"images/setadupla.gif\"></td><td>".trocaicone($recebida[1], "troferta2")."</td><td>".$nome2."</td></tr>
            <tr bgcolor=\"#E4D094\"><td><img src=\"images/ryou.png\" width=\"18\" height=\"18\"></td><td>".tf("%s Ryou", intval($minha[2]))."</td><td><img src=\"images/setadupla.gif\"></td><td><img src=\"images/ryou.png\" width=\"18\" height=\"18\"></td><td>".tf("%s Ryou", intval($recebida[2]))."</td></tr>
            <tr bgcolor=\"#FFF1C7\"><td colspan=\"2\">".$estado1."</td><td></td><td colspan=\"2\">".$estado2."</td></tr>
            $prazo$aviso
            <tr><td colspan=\"5\"><center><a href=\"troca.php?do=troca3&h=".trocacodigo($userrow)."&csrf=".csrf_token()."\"><img src=\"images/aceitar.gif\" border=\"0\" title=\"".t("Aceitar Troca")."\" alt=\"".t("ACEITAR")."\"></a><a href=\"troca.php?do=troca&cancelar=1\"><img border=\"0\" src=\"images/deletar.gif\" title=\"".t("Cancelar Troca")."\" alt=\"".t("CANCELAR")."\"></a></center></td></tr></table></center>";
        } else {
            $htmlmais = "<center><a href=\"troca.php?do=troca&cancelar=1\">".t("Cancelar Troca")."</a></center><meta HTTP-EQUIV='refresh' CONTENT='2;URL=troca.php?html=1&do=troca&msg=".rawurlencode(t("Aguardando o outro jogador no processo de troca."))."&ms_msg=".msgassinatura("msg", t("Aguardando o outro jogador no processo de troca."))."'>";
        }
    }

    // html=2: eu já aceitei; espera o outro aceitar. Quando ele aceitar (ou a troca acabar), vai para o troca4.
    if ($html == 2) {
        $para = trocaacharparceiro($userrow);
        if ($userrow["trocajogador1"] != "None" && trocabate($userrow, $para) && intval($userrow["trocaswitch"]) != 1) {
            trocamsg("A troca foi alterada. Confira as ofertas antes de aceitar.", "&html=1"); // minha confirmação caiu (proposta nova).
        }
        if ($userrow["trocajogador1"] != "None" && trocabate($userrow, $para) && $para["trocaswitch"] != 1) {
            $htmlmais = "<center><a href=\"troca.php?do=troca&cancelar=1\">".t("Cancelar Troca")."</a></center><meta HTTP-EQUIV='refresh' CONTENT='2;URL=troca.php?html=2&do=troca&msg=".rawurlencode(t("Aguardando o outro jogador aceitar a troca."))."&ms_msg=".msgassinatura("msg", t("Aguardando o outro jogador aceitar a troca."))."'>";
        } else {
            header("Location: ./troca.php?do=troca4"); die();
        }
    }

    $mostrarmsg = ($msg != "") ? "<center><font color=brown>$msg</font></center><br>" : "";

    $page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/troca.gif\" /></center></td></tr></table>
    $mostrarmsg
             <form action=\"troca.php?do=troca2\" method=\"post\">
             <center><table><tr><td>

             <table bgcolor=\"#613003\"><tr><td><font color=white>".t("Nome do Jogador:")."</font></td></tr>
             <tr bgcolor=\"#E4D094\"><td><input type=\"text\" name=\"jogador\" size=\"20\" value=\"$jogador\"/></td></tr>
             </table>

             <br>
             <table bgcolor=\"#613003\"><tr><td><font color=white>".t("Ryou para Trocar:")."</font></td></tr>
             <tr bgcolor=\"#FFF1C7\"><td valign=\"middle\"><center>".tf("Meu Ryou: %s", iconeryou().$ryounomomento)."</center></td></tr>
             <tr bgcolor=\"#E4D094\"><td><input type=\"text\" name=\"ryoutroca\" size=\"20\" /></td></tr>
             </table>

             </td><td width=\"20\"></td><td>
             <table><tr bgcolor=\"#613003\"><td colspan=\"3\"><font color=white>".t("Escolha o Item(Mochila)")."</font></td></tr>
             <tr bgcolor=\"#613003\"><td><font color=white>*</font></td><td><font color=white>*</font></td><td><font color=white>".t("Nome")."</font></td></tr>
             $tabelamochila
             </table><br>
             <center><button type=\"submit\" name=\"submit\" value=\"".t("Realizar Nova Troca")."\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_criar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Realizar Nova Troca")."</button></center>
             </td></tr></table></form></center>

             $htmlmais";

    display($page, t("Troca"), false, false, false);

}

function troca2() { // Envia (ou refaz) a minha proposta: um item da mochila (qual = 1..8, 0 = nenhum; qtdN = unidades da pilha) e/ou Ryou.

    global $userrow;
    trocaverificar();
    if (!isset($_POST["submit"])) { header("Location: ./troca.php?do=troca"); die(); }

    $jogador = trim(pegartexto($_POST, "jogador"));
    $ryoutroca = trim(pegartexto($_POST, "ryoutroca"));
    $qual = pegarint($_POST, "qual", 0);

    // Confere o jogador e trava os dois (as checagens abaixo usam os dados travados).
    $query = doquery("SELECT id FROM {{table}} WHERE charname='".$jogador."' AND convidado='0' LIMIT 1", "users"); // convidado (sem conta) não troca
    if ($jogador == "" || mysqli_num_rows($query) != 1) { trocamsg("Não existe nenhum jogador com esse nome para realizar uma troca."); }
    $achado = mysqli_fetch_array($query);
    if ($achado["id"] == $userrow["id"]) { trocamsg("Você não pode realizar uma troca com você mesmo."); }
    $linhas = travarjogadores(array($userrow["id"], $achado["id"]));
    $userrow = $linhas[intval($userrow["id"])];
    $userpara = $linhas[intval($achado["id"])];
    if (intval($userrow["batalha_timer2"]) == 5) { trocamsg("Você não pode trocar itens durante um duelo."); }
    if (intval($userpara["batalha_timer2"]) == 5) { trocamsg("Esse jogador está em um duelo. Tente novamente mais tarde."); }

    // Quantidade de Ryou (em branco = 0).
    if ($ryoutroca == "") { $ryoutroca = "0"; }
    if (!is_numeric($ryoutroca)) { trocamsg("O campo quantidade de Ryou precisa ser um número ou precisa ser deixado em branco."); }
    $ryoutroca = floor((float)$ryoutroca);
    if ($ryoutroca < 0) { trocamsg("O campo quantidade de Ryou precisa ser maior que 0."); }
    if ($ryoutroca > $userrow["gold"]) { trocamsg("Você não pode trocar mais Ryou que você possui."); }
    $ryoutroca = intval($ryoutroca);

    // Item da mochila (numa pilha, a quantidade escolhida; padrão = a pilha inteira).
    if ($qual < 0 || $qual > 8) { trocamsg("Erro ou tentativa de trapaça."); }
    if ($qual > 0 && $userrow["bp".$qual] == "None") { trocamsg("Você não possui nenhum item no slot da backpack que selecionou."); }
    $itemoferta = "None";
    if ($qual > 0) {
        $tem = qtditem($userrow["bp".$qual]);
        $qtd = pegarint($_POST, "qtd".$qual, $tem);
        if ($qtd < 1 || $qtd > $tem) { trocamsg("Quantidade inválida para esse item."); }
        $itemoferta = itemcomqtd($userrow["bp".$qual], $qtd);
    }

    // Trava depois do aceite: se já existe uma troca entre nós dois e alguém aceitou, a oferta não muda mais.
    $atual = separaroferta($userrow["trocajogador1"]);
    if ($userrow["trocajogador1"] != "None" && $atual[0] == $userpara["charname"] && trocabate($userrow, $userpara) && (intval($userrow["trocaswitch"]) == 1 || intval($userpara["trocaswitch"]) == 1)) {
        trocamsg("A troca já foi aceita e as ofertas estão travadas. Cancele a troca para mudar a oferta.", "&html=1");
    }

    // Os dois precisam estar no mesmo mapa.
    if ($userrow["latitude"] != $userpara["latitude"] || $userrow["longitude"] != $userpara["longitude"]) {
        trocamsg("Você precisa estar no mesmo mapa que o outro jogador para realizar uma troca.");
    }

    if ($qual > 0) {
        // O outro jogador precisa ter lugar na mochila para receber o item (pilha igual com espaço ou espaço livre).
        $copia = $userpara;
        $espacos = mochilaguardar($copia, $itemoferta);
        if ($espacos === false) { trocamsg("O jogador o qual você quer realizar uma troca, não possui espaços livres em sua Mochila. Peça-o para esvaziar um espaço."); }
        $bpcerto = count($espacos) ? $espacos[0] : "None";
        $oferta = $userpara["charname"].";".$itemoferta.";".$ryoutroca.";".$bpcerto;
    } else {
        $oferta = $userpara["charname"].";None;".$ryoutroca.";None";
    }
    // Os campos trocajogador1/2 têm no máximo 160 caracteres.
    if (mb_strlen($oferta, "UTF-8") > 160 || substr_count($oferta, ";") != 3) { trocamsg("Não foi possível montar essa troca: o nome do item ou do jogador é grande demais."); }

    // Aviso no histórico do outro jogador (mensagens separadas por ";;").
    // Formato neutro i18n:... (o outro jogador pode usar outro idioma; a tela do histórico traduz com textoi18n()).
    $aviso = i18n("O jogador %s está te chamando para realizar uma troca. <a href=\"troca.php?do=troca&jogador=%s\" target=\"_top\">ACEITAR</a>.", $userrow["charname"], $userrow["charname"]);
    $historico = ($userpara["historico"] == "None") ? $aviso : $userpara["historico"].";;".$aviso;

    // Uma proposta nova zera as confirmações dos dois: ninguém fica "aceitando" uma oferta que mudou.
    // Cada proposta renova o prazo da troca nos dois jogadores (TROCA_MINUTOS).
    $prazo = time() + 60 * TROCA_MINUTOS;
    doquery("UPDATE {{table}} SET trocajogador1='".addslashes($oferta)."', trocaswitch='0', trocaate='$prazo' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
    doquery("UPDATE {{table}} SET trocajogador2='".addslashes($oferta)."', trocaswitch='0', trocaate='$prazo', historico='".addslashes($historico)."' WHERE id='".intval($userpara["id"])."' LIMIT 1", "users");
    confirmartransacao();

    trocamsg("Aguardando o outro jogador no processo de troca...", "&html=1");

}

function troca3() { // Aceitar: marca que eu concordo com a troca que estava na tela (trocaswitch = 1).

    global $userrow;
    trocaverificar();
    if (!csrf_confere()) { trocamsg("Link de confirmação inválido. Abra a troca novamente e clique em aceitar."); }

    $para = trocaacharparceiro($userrow);
    if (!trocabate($userrow, $para)) { trocamsg("Houve um erro na conclusão da troca, por favor recomece a troca novamente."); }
    $linhas = travarjogadores(array($userrow["id"], $para["id"]));
    $eu = $linhas[intval($userrow["id"])];
    $para = $linhas[intval($para["id"])];
    if (!trocabate($eu, $para)) { trocamsg("Houve um erro na conclusão da troca, por favor recomece a troca novamente."); }

    // A oferta mudou depois que a tela foi aberta: mostra de novo antes de aceitar.
    if (pegartexto($_GET, "h") !== trocacodigo($eu)) { trocamsg("A troca foi alterada. Confira as ofertas antes de aceitar.", "&html=1"); }

    if ($eu["latitude"] != $para["latitude"] || $eu["longitude"] != $para["longitude"]) {
        trocamsg("Você precisa estar no mesmo mapa que o outro jogador para concluir essa troca.");
    }

    doquery("UPDATE {{table}} SET trocaswitch='1' WHERE id='".intval($eu["id"])."' LIMIT 1", "users");
    confirmartransacao();

    trocamsg("Aguardando o outro jogador aceitar a troca.", "&html=2");

}

function troca4() { // Executa a troca quando os dois aceitaram. Tudo é conferido de novo com os dois jogadores travados.

    global $userrow;
    trocaverificar();

    // Sem troca em andamento: ou o outro jogador já concluiu (trocaswitch = 2) ou ela foi cancelada.
    if ($userrow["trocajogador1"] == "None" || $userrow["trocajogador2"] == "None") {
        if ($userrow["trocaswitch"] == 2) {
            doquery("UPDATE {{table}} SET trocaswitch='0' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
            trocamsg("Troca realizada com sucesso.");
        }
        trocamsg("A troca foi cancelada.");
    }

    $para = trocaacharparceiro($userrow);
    if (!$para) { trocamsg("Houve um erro na conclusão da troca, por favor recomece a troca novamente."); }
    $linhas = travarjogadores(array($userrow["id"], $para["id"]));
    $eu = $linhas[intval($userrow["id"])];
    $para = $linhas[intval($para["id"])];

    // Enquanto esperava o bloqueio, a troca pode ter sido concluída (pelo outro jogador ou por um clique repetido).
    if ($eu["trocajogador1"] == "None" || $eu["trocajogador2"] == "None") {
        if ($eu["trocaswitch"] == 2) { doquery("UPDATE {{table}} SET trocaswitch='0' WHERE id='".intval($eu["id"])."' LIMIT 1", "users"); }
        confirmartransacao();
        trocamsg("Troca realizada com sucesso.");
    }
    if (!trocabate($eu, $para)) { trocamsg("Houve um erro na conclusão da troca, por favor recomece a troca novamente."); }
    // Os DOIS precisam ter aceitado esta mesma troca.
    if ($eu["trocaswitch"] != 1) { trocamsg("Confira as ofertas e aceite a troca.", "&html=1"); }
    if ($para["trocaswitch"] != 1) { trocamsg("Aguardando o outro jogador aceitar a troca.", "&html=2"); }
    if ($eu["latitude"] != $para["latitude"] || $eu["longitude"] != $para["longitude"]) {
        trocamsg("Você precisa estar no mesmo mapa que o outro jogador para concluir essa troca.");
    }
    if ($para["currentaction"] == "Fighting") { trocamsg("O outro jogador está em uma batalha. Tente concluir a troca quando ela acabar."); }
    // Duelo PvP (os dois travados): ninguém troca itens no meio de um duelo.
    if (intval($eu["batalha_timer2"]) == 5) { trocamsg("Você não pode trocar itens durante um duelo."); }
    if (intval($para["batalha_timer2"]) == 5) { trocamsg("Esse jogador está em um duelo. Tente novamente mais tarde."); }

    $minha = separaroferta($eu["trocajogador1"]);     // o que eu dou
    $recebida = separaroferta($eu["trocajogador2"]);  // o que eu recebo
    $erro = "Houve um erro na conclusão da troca, por favor recomece a troca novamente.";

    // Ryou: cada um precisa ainda ter o que ofereceu, e ninguém pode passar do máximo da coluna gold.
    $ryoudou = intval($minha[2]);
    $ryourecebo = intval($recebida[2]);
    if ($ryoudou < 0 || $ryourecebo < 0 || $eu["gold"] < $ryoudou || $para["gold"] < $ryourecebo) { trocadesfazer($eu, $para, $erro); }
    $eu["gold"] = intval($eu["gold"]) - $ryoudou + $ryourecebo;
    $para["gold"] = intval($para["gold"]) - $ryourecebo + $ryoudou;
    if ($eu["gold"] > 16777215 || $para["gold"] > 16777215) { trocadesfazer($eu, $para, $erro); }

    // Itens: primeiro tira cada item da mochila de quem oferece (ele precisa continuar lá, com a quantidade oferecida;
    // numa pilha maior só sai essa quantidade)...
    if ($minha[1] != "None") {
        $origem1 = trocaorigem($eu, $minha[1]);
        if ($origem1 == "") { trocadesfazer($eu, $para, $erro); }
        $eu[$origem1] = itemcomqtd($eu[$origem1], qtditem($eu[$origem1]) - qtditem($minha[1]));
    }
    if ($recebida[1] != "None") {
        $origem2 = trocaorigem($para, $recebida[1]);
        if ($origem2 == "") { trocadesfazer($eu, $para, $erro); }
        $para[$origem2] = itemcomqtd($para[$origem2], qtditem($para[$origem2]) - qtditem($recebida[1]));
    }
    // ...depois guarda na mochila de quem recebe: junta numa pilha igual ou usa espaços livres (sem lugar, desfaz).
    if ($minha[1] != "None" && mochilaguardar($para, $minha[1]) === false) { trocadesfazer($eu, $para, $erro); }
    if ($recebida[1] != "None" && mochilaguardar($eu, $recebida[1]) === false) { trocadesfazer($eu, $para, $erro); }

    // Grava os dois e encerra a troca (o outro jogador fica com trocaswitch = 2 para ver o aviso de sucesso).
    doquery("UPDATE {{table}} SET trocaswitch='0',trocaate='0',gold='".$eu["gold"]."',trocajogador1='None',trocajogador2='None',bp1='".addslashes($eu["bp1"])."',bp2='".addslashes($eu["bp2"])."',bp3='".addslashes($eu["bp3"])."',bp4='".addslashes($eu["bp4"])."',bp5='".addslashes($eu["bp5"])."',bp6='".addslashes($eu["bp6"])."',bp7='".addslashes($eu["bp7"])."',bp8='".addslashes($eu["bp8"])."' WHERE id='".intval($eu["id"])."' LIMIT 1", "users");
    doquery("UPDATE {{table}} SET trocaswitch='2',trocaate='0',gold='".$para["gold"]."',trocajogador1='None',trocajogador2='None',bp1='".addslashes($para["bp1"])."',bp2='".addslashes($para["bp2"])."',bp3='".addslashes($para["bp3"])."',bp4='".addslashes($para["bp4"])."',bp5='".addslashes($para["bp5"])."',bp6='".addslashes($para["bp6"])."',bp7='".addslashes($para["bp7"])."',bp8='".addslashes($para["bp8"])."' WHERE id='".intval($para["id"])."' LIMIT 1", "users");
    confirmartransacao();

    trocamsg("Troca realizada com sucesso.");

}

?>
