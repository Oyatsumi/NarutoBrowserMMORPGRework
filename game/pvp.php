<?php // pvp.php :: PvP no mundo: atacar outro jogador online na mesma casa do mapa ou numa casa vizinha (fora das vilas).
// Desligado por padrão (control.pvpmundo = 0); as regras ficam no Painel do Administrador (Configurações gerais > PvP no mundo).
//
// Por que a luta é resolvida na hora (e não por turnos como o duelo): o defensor não escolheu lutar e pode nem estar olhando
// a tela; um combate por turnos travaria os dois jogadores esperando por quem não respondeu. A luta usa o MESMO motor do duelo
// (users.php :: dueloacao(): ataque, esquiva, crítico, elementos, bônus de jutsus), com os dois lados jogados automaticamente:
// cada um usa o jutsu de dano mais forte que o chakra permite (metade das vezes) ou ataca. Quem chegaria a 1 HP perde.
// Depois de 30 ações sem vencedor, perde quem tem a menor porcentagem de HP.
//
// Rotas: pvp.php?alvo=ID   tela de confirmação (cartões dos dois e as regras)   | POST acao=atacar (alvo, csrf)
//        pvp.php?ver=ID    resultado de uma luta (só para os dois jogadores)
//
// Consequências para quem perde (as mesmas da morte no PvE, com as perdas configuráveis):
// - volta para a vila natal do alinhamento (vilanataldoalinhamento()) com 1/4 do HP máximo;
// - perde pvpperdaryou% do Ryou que carrega, que vai para o vencedor (só o que cabe no limite da coluna: nada some);
// - para cada um de até pvpmaxitens itens, chance de pvpchancedrop%: um item sorteado da mochila (ou da mochila e dos
//   equipamentos, pvpondedrop = 1) vai para a mochila do vencedor SE ele tiver espaço livre; senão o item fica com o derrotado.
//   Só equipamentos e poções entram no sorteio (itens de missão, diamantes, pergaminhos etc. nunca); com
//   pvpprotegervariantes = 1 as variantes raras (":efeito" no nome) também ficam protegidas.
// Travas: tudo numa transação, com os dois jogadores travados (SELECT ... FOR UPDATE, id crescente) e as regras conferidas
// de novo depois da trava; cliques repetidos ou ataques simultâneos de várias pessoas não duplicam itens nem Ryou.

define('USERS_SOMENTE_FUNCOES', true);
include_once('users.php'); // lib.php, cookies.php, $userrow, $controlrow e o motor do duelo.
include_once('funcoesinclusas.php');
include_once('cidadesconteudo.php');

define('PVP_MAXACOES', 30); // ações da luta automática até decidir pelos pontos de vida.

exigirjogador(false);
if (empty($controlrow["pvpmundo"])) { header("Location: index.php?conteudo=".rawurlencode(t("O PvP no mundo está desligado neste servidor."))); die(); }

if (isset($_POST["acao"]) && $_POST["acao"] == "atacar") { pvpatacar(); }
elseif (isset($_GET["ver"])) { pvpver(pegarint($_GET, "ver")); }
else { pvpconfirmar(pegarint($_GET, "alvo")); }
header("Location: index.php"); die();


/* ------------------------------------------------------------------------
   Regras.
   ------------------------------------------------------------------------ */

function pvpcasatemvila($lat, $lon) { // A casa do mapa tem uma vila?

    return mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE latitude='".intval($lat)."' AND longitude='".intval($lon)."' LIMIT 1", "towns")) > 0;

}

function pvpmotivo($eu, $alvo, $travado) { // "" se $eu pode atacar $alvo agora, ou a chave (em português) do motivo. $travado: conferência final dentro da transação.

    global $controlrow;
    $nivelminimo = max(intval($controlrow["pvplevel"]), intval($controlrow["pvpnivelseguro"]));
    if (!$alvo) { return "Esse jogador não existe."; }
    if ($alvo["id"] == $eu["id"]) { return "Você não pode atacar você mesmo."; }
    if (ehgm($eu) || ehgm($alvo)) { return "GameMasters não participam do PvP."; }
    if ($alvo["authlevel"] == 2) { return "Esse jogador não pode ser atacado."; }
    if ($eu["level"] < $nivelminimo) { return tf("Você só pode atacar outros jogadores a partir do level %s.", $nivelminimo); }
    if ($alvo["level"] < $nivelminimo) { return tf("Jogadores abaixo do level %s estão protegidos do PvP.", $nivelminimo); }
    $dif = intval($controlrow["pvpdiflevel"]);
    if ($dif > 0 && abs(intval($eu["level"]) - intval($alvo["level"])) > $dif) { return tf("A diferença de level entre vocês é maior que %s.", $dif); }
    if ($eu["currentaction"] != "Exploring") { return "Você precisa estar explorando o mapa (fora das vilas) para atacar."; }
    // Caveira exposta (lib.php :: caveiraexposta()): quem matou há pouco com caveira continua atacável offline na última casa,
    // inclusive se saiu no meio de uma luta contra um inimigo (a derrota limpa essa luta, como a morte no fight.php).
    $exposto = !jogadoronline($alvo) && caveiraexposta($alvo) > 0;
    if ($alvo["currentaction"] != "Exploring" && !($exposto && $alvo["currentaction"] == "Fighting")) { return ($alvo["currentaction"] == "Fighting") ? "Esse jogador está no meio de uma batalha. Tente novamente mais tarde." : "Esse jogador está dentro de uma vila."; }
    if (intval($eu["batalha_timer2"]) == 5) { return "Você já está em um duelo."; }
    if (intval($alvo["batalha_timer2"]) == 5) { return "Esse jogador já está em um duelo com outra pessoa."; }
    if ($eu["trocajogador1"] != "None" || $eu["trocajogador2"] != "None") { return "Termine ou cancele a sua troca antes de atacar."; }
    if ($alvo["trocajogador1"] != "None" || $alvo["trocajogador2"] != "None") { return "Esse jogador está fazendo uma troca. Tente novamente mais tarde."; }
    if (intval($eu["currenthp"]) <= 1) { return "Você precisa de mais de 1 HP para atacar. Descanse antes."; }
    if (intval($alvo["currenthp"]) <= 1) { return "Esse jogador está sem Pontos de Vida e não pode ser atacado agora."; }
    if (!jogadoronline($alvo) && !$exposto) { return "Esse jogador não está online."; }
    if (max(abs(intval($eu["latitude"]) - intval($alvo["latitude"])), abs(intval($eu["longitude"]) - intval($alvo["longitude"]))) > 1) { return "Esse jogador está longe demais. Ele precisa estar na mesma casa do mapa ou numa casa vizinha."; }
    if (pvpcasatemvila($eu["latitude"], $eu["longitude"]) || pvpcasatemvila($alvo["latitude"], $alvo["longitude"])) { return "Não é permitido lutar dentro das vilas."; }
    // Esperas (anti-perseguição): entre ataques do mesmo jogador e entre os mesmos dois jogadores (nos dois sentidos).
    $a = intval($eu["id"]); $b = intval($alvo["id"]);
    $trava = $travado ? " LOCK IN SHARE MODE" : "";
    $espera = intval($controlrow["pvpesperaataque"]) * 60;
    if ($espera > 0) {
        $r = mysqli_fetch_array(doquery("SELECT MAX(quando) AS q FROM {{table}} WHERE atacante='$a'$trava", "pvp"));
        if ($r && intval($r["q"]) > time() - $espera) { return tf("Aguarde %s minuto(s) antes de atacar de novo.", ceil((intval($r["q"]) + $espera - time()) / 60)); }
    }
    $espera = intval($controlrow["pvpesperaalvo"]) * 60;
    if ($espera > 0) {
        $r = mysqli_fetch_array(doquery("SELECT MAX(quando) AS q FROM {{table}} WHERE ((atacante='$a' AND defensor='$b') OR (atacante='$b' AND defensor='$a'))$trava", "pvp"));
        if ($r && intval($r["q"]) > time() - $espera) { return tf("Vocês lutaram há pouco tempo. Aguarde %s minuto(s) para lutar com esse jogador de novo.", ceil((intval($r["q"]) + $espera - time()) / 60)); }
    }
    return "";

}

function pvpalvo($id) { // Linha do jogador alvo com a coluna "online" (sem trava).

    return mysqli_fetch_array(doquery("SELECT *, (UNIX_TIMESTAMP(onlinetime) >= UNIX_TIMESTAMP() - 600) AS online FROM {{table}} WHERE id='".intval($id)."' AND convidado='0' LIMIT 1", "users")); // convidado (sem conta) não entra no PvP

}

function pvpvizinhos($eu) { // Jogadores online (ou com a caveira exposta, mesmo offline) nas 9 casas em volta (para a lista do explorar): array de linhas com "motivo" ("" = pode atacar).

    $lista = array();
    if (empty($GLOBALS["controlrow"]["pvpmundo"])) { return $lista; }
    $lat = intval($eu["latitude"]); $lon = intval($eu["longitude"]);
    $query = doquery("SELECT *, (UNIX_TIMESTAMP(onlinetime) >= UNIX_TIMESTAMP() - 600) AS online FROM {{table}} WHERE latitude BETWEEN '".($lat - 1)."' AND '".($lat + 1)."' AND longitude BETWEEN '".($lon - 1)."' AND '".($lon + 1)."' AND id<>'".intval($eu["id"])."' AND convidado='0' AND (UNIX_TIMESTAMP(onlinetime) >= UNIX_TIMESTAMP() - 600 OR ".sqlcaveiraexposta().") ORDER BY level DESC LIMIT 20", "users");
    while ($row = mysqli_fetch_array($query)) { $row["motivo"] = pvpmotivo($eu, $row, false); $lista[] = $row; }
    return $lista;

}

/* ------------------------------------------------------------------------
   Luta automática (motor do duelo).
   ------------------------------------------------------------------------ */

function pvpescolheracao($row) { // "jutsu" (com $_POST["userspell"] preenchido) ou "atacar".

    global $userrow;
    $userrow = $row; // custochakra() usa a Economia de Chakra de quem age.
    if (rand(0, 1) == 0) { return "atacar"; }
    $melhor = 0; $forca = 0;
    $userspells = explode(",", (string)$row["spells"]);
    $query = doquery("SELECT id,mp,attribute,type,elemento FROM {{table}} WHERE type='2' ORDER BY attribute DESC", "spells");
    while ($s = mysqli_fetch_array($query)) {
        if (in_array($s["id"], $userspells) && custochakra($s["mp"], $s) <= intval($row["currentmp"]) && intval($s["attribute"]) > $forca) { $melhor = intval($s["id"]); $forca = intval($s["attribute"]); }
    }
    if ($melhor == 0) { return "atacar"; }
    $_POST["userspell"] = $melhor;
    return "jutsu";

}

function pvplutar($jogadores, &$registro) { // Resolve a luta. Devolve array(lado vencedor, jogadores com HP/CH finais). Lado 1 = atacante.

    global $userrow;
    $guardado = $userrow;
    $duelo = array("desafiante" => $jogadores[1]["id"], "desafiado" => $jogadores[2]["id"], "dano1" => 0, "dano2" => 0, "defesa1" => 0, "defesa2" => 0, "guarda1" => 0, "guarda2" => 0, "registro" => "", "turno" => 0, "jutsuestado" => "");
    $registro = array();
    // Quem começa: rand(1,10) + raiz(Destreza), como no duelo (empate: o defensor).
    $lado = (rand(1,10) + ceil(sqrt(intval($jogadores[1]["dexterity"]))) > rand(1,10) + ceil(sqrt(intval($jogadores[2]["dexterity"])))) ? 1 : 2;
    $registro[] = i18n("%s atacou %s!", $jogadores[1]["charname"], $jogadores[2]["charname"]);
    $registro[] = i18n("%s foi mais rápido e começa.", $jogadores[$lado]["charname"]);
    $vencedor = 0;
    for ($n = 0; $n < PVP_MAXACOES && $vencedor == 0; $n++) {
        $duelo["turno"] = $n + 1; // turnos pares fecham a rodada dos efeitos de jutsu, como no duelo.
        if (dueloatordoado($duelo, $lado)) { // atordoado por um jutsu: perde a vez.
            dueloregistrar($duelo, i18n("%s está atordoado e perde a vez.", $jogadores[$lado]["charname"]));
        } else {
            $acao = pvpescolheracao($jogadores[$lado]);
            $resultado = dueloacao($duelo, $jogadores, $lado, $acao);
            if (!is_array($resultado)) { $resultado = dueloacao($duelo, $jogadores, $lado, "atacar"); } // jutsu recusado (recarga, chakra): ataque comum.
            $jogadores = $resultado;
            if (!empty($jogadores[3 - $lado]["derrotado"])) { $vencedor = $lado; }
        }
        // Fim da rodada: queimadura/veneno/regeneração e contagem dos efeitos e recargas (dueloticar, users.php).
        if ($vencedor == 0) { $caiu = dueloticar($duelo, $jogadores); if ($caiu) { $vencedor = 3 - $caiu; } }
        foreach (explode("\n", $duelo["registro"]) as $linha) { if ($linha != "") { $registro[] = $linha; } }
        $duelo["registro"] = "";
        $lado = 3 - $lado;
    }
    unset($_POST["userspell"]);
    if ($vencedor == 0) {
        // Ninguém caiu: perde quem tem a menor porcentagem de HP.
        $p1 = intval($jogadores[1]["currenthp"]) / max(1, intval($jogadores[1]["maxhp"]));
        $p2 = intval($jogadores[2]["currenthp"]) / max(1, intval($jogadores[2]["maxhp"]));
        $vencedor = ($p1 >= $p2) ? 1 : 2;
        $registro[] = i18n("A luta se arrastou e %s recuou, ferido.", $jogadores[3 - $vencedor]["charname"]);
    } else {
        $registro[] = i18n("%s caiu e não pode continuar.", $jogadores[3 - $vencedor]["charname"]);
    }
    $registro[] = i18n("%s venceu a luta!", $jogadores[$vencedor]["charname"]);
    $userrow = $guardado;
    return array($vencedor, $jogadores);

}

function pvpitemelegivel($texto, $protegervariantes) { // Item (no formato da mochila "nome,id,tipo,dur") pode ser perdido no PvP?

    $item = array_pad(explode(",", (string)$texto), 4, "X");
    if ($texto == "None" || $texto == "") { return false; }
    if ($protegervariantes && variantedoitem($item[0]) != "") { return false; }
    if (is_numeric($item[1]) && is_numeric($item[2]) && intval($item[2]) >= 1 && intval($item[2]) <= 6) { return true; } // equipamento
    return in_array($item[1], array("hp", "mp", "tp"), true); // poções; itens de missão e especiais nunca.

}

function pvpperdas(&$vencedor, &$perdedor) { // Sorteia e move os itens (dentro da transação, jogadores travados). Devolve a lista de nomes movidos e dos que ficaram.

    global $controlrow, $userrow;
    $movidos = array(); $ficaram = array();
    $chance = intval($controlrow["pvpchancedrop"]);
    $maximo = intval($controlrow["pvpmaxitens"]);
    $proteger = !empty($controlrow["pvpprotegervariantes"]);
    $todos = false;
    // Caveiras: branca = chance maior; vermelha = perde TODOS os itens (mochila e equipados, variantes também; o que não
    // couber na mochila do vencedor é destruído) e, se configurado, o banco de itens.
    $caveira = caveiraativa($perdedor);
    if ($caveira == "branca") { $chance = min(100, $chance + intval($controlrow["pvpbrancachance"])); }
    if ($caveira == "vermelha") {
        $todos = true; $chance = 100; $maximo = 10; $proteger = false;
        if (!empty($controlrow["pvpvermelhabanco"])) { doquery("UPDATE {{table}} SET bancogeral='None' WHERE id='".intval($perdedor["id"])."' LIMIT 1", "users"); $perdedor["bancogeral"] = "None"; }
    }
    if ($chance <= 0 || $maximo <= 0) { return array($movidos, $ficaram); }

    // Monte de itens: "bpN" (mochila) e, se configurado, 1..7 (equipados: arma, colete, bandana, acessórios 1-4).
    $slots = slotsequipamento();
    $monte = array();
    for ($i = 1; $i <= 8; $i++) { if ($todos ? $perdedor["bp".$i] != "None" : pvpitemelegivel($perdedor["bp".$i], $proteger)) { $monte[] = "bp".$i; } }
    if (!empty($controlrow["pvpondedrop"]) || $todos) {
        $dur = lerdurabilidade($perdedor["durabilidade"]);
        foreach ($slots as $q => $campo) {
            if ($perdedor[$campo."name"] != "None" && intval($perdedor[$campo."id"]) > 0 && pvpitemelegivel($perdedor[$campo."name"].",".intval($perdedor[$campo."id"]).",".($q <= 3 ? $q : 4).",".$dur[$q], $proteger)) { $monte[] = $q; }
        }
    }
    shuffle($monte);

    for ($n = 0; $n < $maximo && count($monte) > 0; $n++) {
        if (rand(1, 100) > $chance) { continue; }
        $origem = array_shift($monte);
        if (is_string($origem)) { $texto = $perdedor[$origem]; } // uma pilha vai inteira.
        else { $dur = lerdurabilidade($perdedor["durabilidade"]); $texto = $perdedor[$slots[$origem]."name"].",".intval($perdedor[$slots[$origem]."id"]).",".($origem <= 3 ? $origem : 4).",".$dur[$origem]; } // acessórios vão para a mochila como drop (tipo 4).
        // Lugar na mochila do vencedor: pilha igual com espaço ou espaço livre (calculado numa cópia).
        $vencedornovo = $vencedor;
        $espacos = mochilaguardar($vencedornovo, $texto);
        $livre = ($espacos === false) ? 0 : 1;
        $nome = array_pad(explode(",", $texto), 1, "")[0];
        if ($livre == 0 && !$todos) { $ficaram[] = $nome; continue; } // Mochila do vencedor cheia: o item fica com o derrotado.

        if (is_string($origem)) {
            $perdedor[$origem] = "None";
            doquery("UPDATE {{table}} SET $origem='None' WHERE id='".intval($perdedor["id"])."' LIMIT 1", "users");
        } else {
            // Equipado: desequiparitem() retira os bônus do derrotado e apaga o item do slot (lê e grava o $userrow global).
            include_once('desequipar.php');
            $guardado = $userrow;
            $userrow = $perdedor;
            desequiparitem($origem, true);
            $perdedor = $userrow;
            $userrow = $guardado;
        }
        if ($livre == 0) { $ficaram[] = $nome; continue; } // caveira vermelha: sem espaço no vencedor, o item é destruído.
        list($set, $onde) = sqlmochila($vencedornovo, $vencedor, $espacos);
        doquery("UPDATE {{table}} SET $set WHERE id='".intval($vencedor["id"])."'$onde LIMIT 1", "users");
        $vencedor = $vencedornovo;
        $movidos[] = $nome;
    }
    return array($movidos, $ficaram);

}

/* ------------------------------------------------------------------------
   Telas e ações.
   ------------------------------------------------------------------------ */

function pvpatacar() { // POST acao=atacar: confere, trava os dois, luta, aplica as consequências e mostra o resultado.

    global $userrow, $controlrow, $link;
    if (!csrf_confere()) { voltarcommensagem("index.php?pvp=1", "A sessão do formulário expirou. Tente novamente."); }
    $alvoid = pegarint($_POST, "alvo");
    $meuid = intval($userrow["id"]);
    if ($alvoid < 1 || $alvoid == $meuid) { voltarcommensagem("index.php?pvp=1", "Você não pode atacar você mesmo."); }

    doquery("START TRANSACTION", "users");
    $travados = duelotravarjogadores(array("desafiante" => $meuid, "desafiado" => $alvoid)); // id crescente, FOR UPDATE.
    if (!$travados) { doquery("ROLLBACK", "users"); voltarcommensagem("index.php?pvp=1", "Esse jogador não existe."); }
    $erro = pvpmotivo($travados[1], $travados[2], true);
    if ($erro != "") { doquery("ROLLBACK", "users"); voltarcommensagem("pvp.php?alvo=".$alvoid, $erro); }

    $registro = array();
    list($ladovencedor, $jogadores) = pvplutar($travados, $registro);
    $vencedor = $jogadores[$ladovencedor];
    $perdedor = $jogadores[3 - $ladovencedor];

    // HP/CH depois da luta (o vencedor fica como terminou; o derrotado é levado para a vila com 1/4 do HP máximo, como na morte do PvE).
    doquery("UPDATE {{table}} SET currenthp='".max(1, intval($vencedor["currenthp"]))."', currentmp='".max(0, intval($vencedor["currentmp"]))."' WHERE id='".intval($vencedor["id"])."' LIMIT 1", "users");
    doquery("UPDATE {{table}} SET currentmp='".max(0, intval($perdedor["currentmp"]))."' WHERE id='".intval($perdedor["id"])."' LIMIT 1", "users");

    // Itens (antes de mexer no Ryou/posição: desequiparitem() regrava atributos a partir da linha travada).
    list($movidos, $ficaram) = pvpperdas($vencedor, $perdedor);

    // Ryou: pvpperdaryou% do que o derrotado carrega, limitado ao que o vencedor ainda pode carregar (nada some).
    $ryou = (int)floor(intval($perdedor["gold"]) * intval($controlrow["pvpperdaryou"]) / 100);
    $ryou = max(0, min($ryou, 16777215 - intval($vencedor["gold"])));
    if ($ryou > 0) {
        doquery("UPDATE {{table}} SET gold=gold-$ryou WHERE id='".intval($perdedor["id"])."' AND gold >= $ryou LIMIT 1", "users");
        if (mysqli_affected_rows($link) != 1) { doquery("ROLLBACK", "users"); voltarcommensagem("pvp.php?alvo=".$alvoid, "Houve um erro, tente novamente."); }
        doquery("UPDATE {{table}} SET gold=gold+$ryou WHERE id='".intval($vencedor["id"])."' LIMIT 1", "users");
    }

    // Derrotado: vila natal, 1/4 do HP, fora de luta (mesmos campos da morte no fight.php).
    $vila = vilanataldoalinhamento($perdedor["alinhamento"]);
    $novohp = (int)ceil(intval($perdedor["maxhp"]) / 4);
    doquery("UPDATE {{table}} SET currenthp='$novohp', currentaction='In Town', currentmonster='0', currentmonsterhp='0', currentmonstersleep='0', currentmonsterimmune='0', currentfight='0', currentuberdamage='0', currentuberdefense='0', latitude='".intval($vila["latitude"])."', longitude='".intval($vila["longitude"])."' WHERE id='".intval($perdedor["id"])."' LIMIT 1", "users");
    include_once('funcoesmissoesextras.php'); if (function_exists('missoesextras_derrota')) { missoesextras_derrota($perdedor); }
    // Morreu com caveira vermelha: perde de pvpvermelhaperdamin a pvpvermelhaperdamax níveis (sorteado).
    $niveisperdidos = 0;
    if (caveiraativa($perdedor) == "vermelha") {
        $nmin = max(0, intval(isset($controlrow["pvpvermelhaperdamin"]) ? $controlrow["pvpvermelhaperdamin"] : 1));
        $nmax = max($nmin, intval(isset($controlrow["pvpvermelhaperdamax"]) ? $controlrow["pvpvermelhaperdamax"] : 5));
        $niveisperdidos = pvpperderniveis($perdedor["id"], random_int($nmin, $nmax));
    }

    if (count($movidos)) { $registro[] = i18n("%s tomou de %s: %s.", $vencedor["charname"], $perdedor["charname"], implode(", ", $movidos)); }
    if (count($ficaram) && caveiraativa($perdedor) == "vermelha") { $registro[] = i18n("%s tinha caveira vermelha e perdeu tudo; sem espaço na mochila de %s, foram destruídos: %s.", $perdedor["charname"], $vencedor["charname"], implode(", ", $ficaram)); }
    elseif (count($ficaram)) { $registro[] = i18n("A mochila de %s estava cheia; ficou com %s: %s.", $vencedor["charname"], $perdedor["charname"], implode(", ", $ficaram)); }
    if ($ryou > 0) { $registro[] = i18n("%s ganhou %s Ryou de %s.", $vencedor["charname"], $ryou, $perdedor["charname"]); }
    $registro[] = i18n("%s voltou para %s com 1/4 do HP.", $perdedor["charname"], $vila["name"]);
    if ($niveisperdidos > 0) { $registro[] = i18n("%s morreu com caveira vermelha e perdeu %s nível(is).", $perdedor["charname"], $niveisperdidos); }
    if (!jogadoronline($travados[2])) { $registro[] = i18n("%s estava offline, mas a caveira o deixou exposto no mapa.", $travados[2]["charname"]); }

    // Caveiras (só para quem atacou e matou sem ser em legítima defesa): pvpcaveiras() muda a caveira, ou bane quem já era vermelho.
    $injusto = ($vencedor["id"] == $meuid) ? pvpcaveiras($travados[1], $travados[2], $registro) : 0;

    $texto = implode("\n", array_map(function($l) { return str_replace(array("\r", "\n"), " ", $l); }, $registro));
    doquery("INSERT INTO {{table}} SET atacante='$meuid', defensor='$alvoid', vencedor='".intval($vencedor["id"])."', quando='".time()."', injusto='$injusto', ryou='$ryou', itens='".addslashes(mb_substr(implode("|", $movidos), 0, 250, 'UTF-8'))."', registro='".addslashes($texto)."'", "pvp");
    $idluta = mysqli_insert_id($link);

    // Avisos: histórico dos dois e a janela flutuante do defensor (formato i18n: cada um lê no próprio idioma).
    $atacante = $travados[1]; $defensor = $travados[2];
    $venceuatacante = ($vencedor["id"] == $meuid);
    $ver = " <a href=\"pvp.php?ver=%s\" target=\"_top\">"; // o id da luta entra como valor (a chave da tradução não muda).
    avisarnohistorico($defensor["id"], $venceuatacante
        ? i18n("%s atacou você e venceu. Você perdeu %s Ryou e %s item(ns).".$ver."Ver a luta</a>.", $atacante["charname"], $ryou, count($movidos), $idluta)
        : i18n("%s atacou você, mas você venceu e ganhou %s Ryou e %s item(ns).".$ver."Ver a luta</a>.", $atacante["charname"], $ryou, count($movidos), $idluta));
    avisarnohistorico($meuid, $venceuatacante
        ? i18n("Você atacou %s e venceu: %s Ryou e %s item(ns).", $defensor["charname"], $ryou, count($movidos))
        : i18n("Você atacou %s e perdeu.", $defensor["charname"]));
    $janela = "naruto#$%;".i18n("ATACADO!")."#$%;".($venceuatacante
        ? i18n("<br />%s atacou você e venceu a luta! Você voltou para a sua vila.".$ver."Ver a luta</a>", $atacante["charname"], $idluta)
        : i18n("<br />%s atacou você, mas você venceu a luta!".$ver."Ver a luta</a>", $atacante["charname"], $idluta));
    if (!jogadoronline($defensor)) { // atacado offline (caveira exposta): avisa no login, pela janela e pelo histórico.
        $janela .= i18n("<br />Você estava offline, mas a sua caveira deixou você exposto no mapa.");
        avisarnohistorico($defensor["id"], i18n("Você foi atacado enquanto estava offline: a sua caveira deixou você exposto no mapa."));
    }
    doquery("UPDATE {{table}} SET mainmsg='".addslashes($janela)."' WHERE id='".intval($defensor["id"])."' LIMIT 1", "users");
    doquery("COMMIT", "users");

    header("Location: pvp.php?ver=".$idluta);
    die();

}

function pvpcaveiras($atacante, $vitima, &$registro) { // Sistema de caveiras (estilo Tibia). Devolve 1 se a morte foi injusta (conta para a caveira).

    global $controlrow, $userrow;
    $a = intval($atacante["id"]); $v = intval($vitima["id"]);
    // Legítima defesa: a vítima tinha atacado o atacante nos últimos 15 minutos.
    $defesa = mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE atacante='$v' AND defensor='$a' AND quando >= '".(time() - 900)."' LIMIT 1", "pvp")) > 0;
    if ($defesa) { $registro[] = i18n("%s agiu em legítima defesa (sem caveira).", $atacante["charname"]); return 0; }

    if (caveiraativa($atacante) == "vermelha") {
        // Matou de novo com caveira vermelha: banimento automático e perda de metade dos itens (sorteados, destruídos).
        $dias = max(1, intval($controlrow["pvpbanirdias"]));
        $perdidos = !empty($controlrow["pvpbanirmetade"]) ? pvpperdermetade($atacante["id"]) : array(); // control.pvpbanirmetade: perde metade dos itens (padrão) ou só é banido.
        $niveis = pvpperderniveis($a, max(0, intval(isset($controlrow["pvpbanirniveis"]) ? $controlrow["pvpbanirniveis"] : 5))); // control.pvpbanirniveis: perde níveis antes do banimento.
        doquery("UPDATE {{table}} SET authlevel='2', banidoate='".(time() + 86400 * $dias)."' WHERE id='$a' AND authlevel='0' LIMIT 1", "users");
        if ($niveis > 0) { $registro[] = i18n("%s perdeu %s nível(is) pela caveira vermelha.", $atacante["charname"], $niveis); }
        include_once('painelseguranca.php');
        registrarlog("pvp_banimento_automatico", $a, $atacante["charname"]." matou ".$vitima["charname"]." com caveira vermelha: ".$dias." dias; níveis perdidos: ".$niveis."; itens perdidos: ".implode(", ", $perdidos));
        $registro[] = !empty($controlrow["pvpbanirmetade"]) ? i18n("%s matou com caveira vermelha e foi banido por %s dias, perdendo metade dos itens.", $atacante["charname"], $dias) : i18n("%s matou com caveira vermelha e foi banido por %s dias.", $atacante["charname"], $dias);
        return 1;
    }
    // Mortes injustas dentro do período da caveira branca (contando esta): N ou mais = caveira vermelha.
    $horas = max(1, intval($controlrow["pvpbrancahoras"]));
    $r = mysqli_fetch_array(doquery("SELECT COUNT(*) AS n FROM {{table}} WHERE atacante='$a' AND vencedor='$a' AND injusto='1' AND quando >= '".(time() - 3600 * $horas)."'", "pvp"));
    $mortes = intval($r["n"]) + 1;
    if ($mortes >= max(1, intval($controlrow["pvpvermelhamortes"]))) {
        doquery("UPDATE {{table}} SET caveira='vermelha', pvpmatouem='".time()."', caveiraate='".(time() + 86400 * max(1, intval($controlrow["pvpvermelhadias"])))."' WHERE id='$a' LIMIT 1", "users");
        $registro[] = i18n("%s ganhou uma caveira vermelha (%s mortes).", $atacante["charname"], $mortes);
    } else {
        doquery("UPDATE {{table}} SET caveira='branca', pvpmatouem='".time()."', caveiraate='".(time() + 3600 * $horas)."' WHERE id='$a' LIMIT 1", "users");
        $registro[] = i18n("%s ganhou uma caveira branca.", $atacante["charname"]);
    }
    return 1;

}

function pvpperderniveis($id, $n) { // Tira $n níveis do jogador (dentro da transação): desfaz os ganhos de cada level up (fight.php :: victory()).
    // HP/Chakra/TP máximos, Força/Destreza (e o Ataque/Defesa que elas deram), +5 NP e +5 pontos de distribuição por nível,
    // +1 EP a cada control.epnivel níveis; a experiência fica no início do novo nível. Pontos já distribuídos não voltam
    // (só os ainda livres são descontados). Nunca abaixo do level 1. CAST AS SIGNED: as colunas são UNSIGNED. Devolve quantos níveis tirou.

    global $controlrow;
    $n = intval($n);
    if ($n <= 0) { return 0; }
    $row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' LIMIT 1 FOR UPDATE", "users"));
    if (!$row) { return 0; }
    $nivel = intval($row["level"]);
    $alvo = max(1, $nivel - $n);
    if ($alvo >= $nivel) { return 0; }
    $c = intval($row["charclass"]); if ($c < 1 || $c > 3) { $c = 1; }
    $hp = 0; $mp = 0; $tp = 0; $for = 0; $des = 0; $ep = 0;
    $epnivel = isset($controlrow["epnivel"]) ? intval($controlrow["epnivel"]) : 2;
    $q = doquery("SELECT * FROM {{table}} WHERE id > '$alvo' AND id <= '$nivel'", "levels");
    while ($l = mysqli_fetch_array($q)) {
        $hp += intval($l[$c."_hp"]); $mp += intval($l[$c."_mp"]); $tp += intval($l[$c."_tp"]);
        $for += intval($l[$c."_strength"]); $des += intval($l[$c."_dexterity"]);
        if ($epnivel > 0 && intval($l["id"]) % $epnivel == 0) { $ep++; }
    }
    $base = mysqli_fetch_array(doquery("SELECT ".$c."_exp AS e FROM {{table}} WHERE id='$alvo' LIMIT 1", "levels"));
    $exp = ($base && $alvo > 1) ? intval($base["e"]) : 0;
    $tirou = $nivel - $alvo;
    doquery("UPDATE {{table}} SET level='$alvo', experience='$exp', maxhp=GREATEST(1, CAST(maxhp AS SIGNED)-$hp), maxmp=GREATEST(0, CAST(maxmp AS SIGNED)-$mp), maxtp=GREATEST(0, CAST(maxtp AS SIGNED)-$tp),"
        ." strength=GREATEST(0, CAST(strength AS SIGNED)-$for), dexterity=GREATEST(0, CAST(dexterity AS SIGNED)-$des), attackpower=GREATEST(0, CAST(attackpower AS SIGNED)-$for), defensepower=GREATEST(0, CAST(defensepower AS SIGNED)-$des),"
        ." maxnp=GREATEST(0, CAST(maxnp AS SIGNED)-".(5 * $tirou)."), pontoatributos=GREATEST(0, CAST(pontoatributos AS SIGNED)-".(5 * $tirou)."), maxep=GREATEST(0, CAST(maxep AS SIGNED)-$ep) WHERE id='".intval($id)."' LIMIT 1", "users");
    doquery("UPDATE {{table}} SET currenthp=LEAST(currenthp, maxhp), currentmp=LEAST(currentmp, maxmp), currenttp=LEAST(currenttp, maxtp), currentnp=LEAST(currentnp, maxnp), currentep=LEAST(currentep, maxep) WHERE id='".intval($id)."' LIMIT 1", "users");
    return $tirou;

}

function pvpperdermetade($id) { // Punição do banimento automático: metade (arredondada para cima) dos itens da mochila e equipados, sorteados, é destruída.

    global $userrow;
    $row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' LIMIT 1 FOR UPDATE", "users"));
    $slots = slotsequipamento();
    $monte = array();
    for ($i = 1; $i <= 8; $i++) { if ($row["bp".$i] != "None") { $monte[] = "bp".$i; } }
    foreach ($slots as $q => $c) { if ($row[$c."name"] != "None" && intval($row[$c."id"]) > 0) { $monte[] = $q; } }
    shuffle($monte);
    $perdidos = array();
    foreach (array_slice($monte, 0, (int)ceil(count($monte) / 2)) as $origem) {
        if (is_string($origem)) {
            $perdidos[] = explode(",", $row[$origem])[0];
            doquery("UPDATE {{table}} SET $origem='None' WHERE id='".intval($id)."' LIMIT 1", "users");
            $row[$origem] = "None";
        } else {
            $perdidos[] = $row[$slots[$origem]."name"];
            include_once('desequipar.php');
            $guardado = $userrow; $userrow = $row;
            desequiparitem($origem, true);
            $row = $userrow; $userrow = $guardado;
        }
    }
    return $perdidos;

}

function pvpregras() { // Resumo das regras configuradas (mostrado na confirmação).

    global $controlrow;
    $c = $controlrow;
    $onde = !empty($c["pvpondedrop"]) ? t("da mochila ou equipado") : t("da mochila");
    $texto = tf("Quem perder volta para a vila natal com 1/4 do HP e perde %s%% do Ryou que carrega.", intval($c["pvpperdaryou"]));
    if (intval($c["pvpchancedrop"]) > 0 && intval($c["pvpmaxitens"]) > 0) {
        $texto .= " ".tf("Também pode perder até %s item(ns) %s (%s%% de chance cada) para o vencedor, se ele tiver espaço na mochila.", intval($c["pvpmaxitens"]), $onde, intval($c["pvpchancedrop"]));
        if (!empty($c["pvpprotegervariantes"])) { $texto .= " ".t("Variantes raras nunca são perdidas."); }
    }
    $nmin = intval(isset($c["pvpvermelhaperdamin"]) ? $c["pvpvermelhaperdamin"] : 1); $nmax = max($nmin, intval(isset($c["pvpvermelhaperdamax"]) ? $c["pvpvermelhaperdamax"] : 5));
    if ($nmax > 0) { $texto .= " ".($nmin == $nmax ? tf("Quem morre com caveira vermelha perde %s nível(is).", $nmax) : tf("Quem morre com caveira vermelha perde de %s a %s níveis.", $nmin, $nmax)); }
    $nban = intval(isset($c["pvpbanirniveis"]) ? $c["pvpbanirniveis"] : 5);
    if ($nban > 0) { $texto .= " ".tf("Quem mata de novo com caveira vermelha perde %s nível(is) e é banido.", $nban); }
    if (caveiraminutos() > 0) { $texto .= " ".tf("Quem mata com caveira continua atacável por %s minuto(s), mesmo offline, e não entra em vilas nesse tempo.", caveiraminutos()); }
    return $texto;

}

function pvpconfirmar($alvoid) { // Tela "Atacar": os dois cartões, o motivo se não puder, e o botão.

    global $userrow;
    $alvo = pvpalvo($alvoid);
    $eu = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1", "users"));
    if (!$alvo) { voltarcommensagem("index.php?pvp=1", "Esse jogador não existe."); }
    $motivo = pvpmotivo($eu, $alvo, false);
    $csrf = csrf_campo();
    if ($motivo == "") {
        $comandos = "<form action=\"pvp.php\" method=\"post\" onsubmit=\"this.querySelector('button').disabled=true;\">$csrf<input type=\"hidden\" name=\"alvo\" value=\"".intval($alvo["id"])."\" />"
            ."<center><img src=\"layoutnovo/graficos/".intval($eu["avatar"])."_stance.gif\" alt=\"\" /><br /><br />"
            ."<button type=\"submit\" name=\"acao\" value=\"atacar\" class=\"btnjogo bj-ataque\"><img src=\"images/jutsu_taijutsu.png\" class=\"jutsuico\" width=\"20\" height=\"17\" alt=\"\"> ".tf("Atacar %s", $alvo["charname"])."</button></center></form>";
    } else {
        $comandos = "<center><span class=\"highlight\">".t($motivo)."</span></center>";
    }
    $comandos .= "<br /><center><a href=\"index.php\">".t("Voltar ao jogo")."</a></center>";
    $pagearray = array(
        "titulo" => titulopagina("duelo.gif"),
        "estilo" => dueloestilo(),
        "mensagem" => mensagemtopo(),
        "cartao1" => cartaodojogador($eu, true),
        "cartao2" => cartaodojogador($alvo, false),
        "faixa" => "<b>".t("PvP no mundo")."</b><br /><span class=\"duelodetalhe\">".t("A luta é resolvida na hora, com as regras do duelo.")." ".pvpregras()."</span>",
        "comandos" => duelopainel(t("Atacar?"), $comandos),
        "registro" => duelopainel(t("Regras"), "<ul class=\"dueloregistro\"><li>".t("Os dois precisam estar online, explorando o mapa (fora das vilas), na mesma casa ou numa casa vizinha.")."</li>".(caveiraminutos() > 0 ? "<li>".t("Exceção: quem matou há pouco com caveira pode ser atacado mesmo offline, na última casa onde estava.")."</li>" : "")."<li>".t("Ninguém em batalha, duelo ou troca pode atacar nem ser atacado.")."</li><li>".t("Depois de uma luta há um tempo de espera para atacar de novo (e para lutar com o mesmo jogador).")."</li></ul>"),
        "desistir" => "",
    );
    display(parsetemplate(gettemplate("duelo"), $pagearray), t("PvP no mundo"), false, false, false);

}

function pvpver($id) { // Resultado de uma luta (só para quem participou).

    global $userrow;
    $meuid = intval($userrow["id"]);
    $luta = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' AND (atacante='$meuid' OR defensor='$meuid') LIMIT 1", "pvp"));
    if (!$luta) { header("Location: index.php"); die(); }
    $a = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($luta["atacante"])."' LIMIT 1", "users"));
    $d = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($luta["defensor"])."' LIMIT 1", "users"));
    $vazio = array("charname"=>"?", "avatar"=>1, "alinhamento"=>"", "elemento"=>"neutro", "level"=>0, "currenthp"=>0, "maxhp"=>1, "currentmp"=>0, "maxmp"=>1);
    if (!$a) { $a = $vazio; } if (!$d) { $d = $vazio; }
    $venceu = ($luta["vencedor"] == $meuid);
    $eu = ($luta["atacante"] == $meuid) ? $a : $d;
    $linhas = array_reverse(explode("\n", (string)$luta["registro"]));
    $log = "<ol class=\"dueloregistro\" reversed>";
    foreach ($linhas as $i => $l) { $log .= "<li".($i == 0 ? " class=\"ultima\"" : "").">".textoi18n($l)."</li>"; }
    $log .= "</ol>";
    $itens = ($luta["itens"] != "") ? implode(", ", array_map('tnomeitem', explode("|", $luta["itens"]))) : t("nenhum");
    $comandos = "<center><img src=\"layoutnovo/graficos/".intval($eu["avatar"]).($venceu ? "_ganhou" : "_morto").".gif\" alt=\"\" /><br /><b>".($venceu ? t("Você venceu a luta!") : t("Você perdeu a luta."))."</b><br />"
        .tf("Ryou: %s", number_format((float)$luta["ryou"]))." &middot; ".tf("Itens: %s", $itens)."<br /><br /><a href=\"index.php\">".t("Voltar ao jogo")."</a></center>";
    $pagearray = array(
        "titulo" => titulopagina("duelo.gif"),
        "estilo" => dueloestilo(),
        "mensagem" => mensagemtopo(),
        "cartao1" => cartaodojogador($a, $luta["vencedor"] == $luta["atacante"]),
        "cartao2" => cartaodojogador($d, $luta["vencedor"] == $luta["defensor"]),
        "faixa" => "<b>".tf("%s atacou %s", $a["charname"], $d["charname"])."</b><br /><span class=\"duelodetalhe\">".padraodate(date("Y-m-d H:i:s", intval($luta["quando"])))."</span>",
        "comandos" => duelopainel(t("Resultado"), $comandos),
        "registro" => duelopainel(t("Registro da luta"), $log),
        "desistir" => "",
    );
    display(parsetemplate(gettemplate("duelo"), $pagearray), t("PvP no mundo"), false, false, false);

}

?>
