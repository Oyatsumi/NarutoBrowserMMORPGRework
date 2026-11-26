<?php // index.php :: Programa principal do jogo: carrega o jogador logado e encaminha cada ação (?do=acao:parametro) para a função certa.
// Este arquivo também é incluído por outros scripts (encherhp.php, falapersonagens.php, jutsudebusca.php, funcaoitens.php...)
// que já carregaram o lib.php e preencheram $indexconteudo com uma mensagem para aparecer no topo da página.
// Às vezes essa inclusão acontece dentro de uma função, por isso as variáveis compartilhadas são declaradas como globais.

global $valorlib, $link, $userrow, $controlrow, $indexconteudo, $htmlnapag;
include_once('lib.php');      // include_once: quem inclui o index.php normalmente já carregou o lib.php ($valorlib era usado para isso).
include_once('cookies.php');
$link = opendb();
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);
include_once('funcoesconvidado.php');
convidado_limpar(); // apaga convidados vencidos (no máximo 1x a cada 10 min)

// Mensagem curta passada pela URL (?conteudo=...). Só é usada se o script que incluiu o index.php não mandou outra.
if (!isset($indexconteudo)) { $indexconteudo = ""; }
if (!isset($htmlnapag)) { $htmlnapag = ""; }
$textodaurl = lermsg("conteudo");
if ($textodaurl != "") {
    $indexconteudo = "<div style=\"position:relative; z-index: 2\"><center><font color=\"brown\">".strip_tags($textodaurl)."</font></center></div>";
}

// "Andar" automático (clique numa casa do mini mapa): index.php?re=true&latitude=X&longitude=Y.
// Enquanto o jogador não chega ao destino, a página se recarrega sozinha a cada 1 segundo dando mais um passo (do=andar).
$andarlat = pegarint($_GET, "latitude", null);
$andarlong = pegarint($_GET, "longitude", null);
if (pegartexto($_GET, "re") != "" && $andarlat !== null && $andarlong !== null) {
    $htmlnapag = "<meta http-equiv=\"refresh\" content=\"1;URL=index.php?do=andar&amp;latitude=".$andarlat."&amp;longitude=".$andarlong."&amp;csrf=TOKENCSRF&amp;conteudo=".rawurlencode(t("Seu personagem está indo até a coordenada."))."&amp;ms_conteudo=".msgassinatura("conteudo", t("Seu personagem está indo até a coordenada."))."\" />";
}

// Quem não está logado vai para a tela de login.
$userrow = checkcookies();
if ($userrow == false) {
    if (pegartexto($_GET, "do") == "verify") { header("Location: users.php?do=verify"); die(); }
    header("Location: login.php?do=login"); die();
}

// Chegou ao destino do andar automático: para de recarregar e avisa.
if ($htmlnapag != "" && $indexconteudo == "" && $userrow["latitude"] == $andarlat && $userrow["longitude"] == $andarlong) {
    $htmlnapag = "";
    $indexconteudo = "<center><font color=\"brown\">".t("Você chegou em seu destino.")."</font></center><br />";
}

// Jogo fechado pelo administrador (a mensagem é configurada no painel: naruto_control.closedmsg).
if ($controlrow["gameopen"] == 0 && $userrow["authlevel"] != 1) {
    display(nl2br(t($controlrow["closedmsg"])), t("Fechado"));
}
// Obriga a verificar o e-mail, se essa opção estiver ligada.
if (verificaremail() && $userrow["verify"] != 1) { header("Location: users.php?do=verify"); die(); }
// Conta bloqueada (banida).
if ($userrow["authlevel"] == 2) { die(t("Sua conta foi bloqueada. Por favor tenta novamente mais tarde.")); }
// Luta abandonada (parada há mais de control.mortelutaabandono minutos): conta como derrota com a chance configurada
// (fight.php :: lutasabandonadas). De vez em quando também resolve as lutas abandonadas de outros jogadores.
if ($userrow["currentaction"] == "Fighting" || mt_rand(1, 20) == 1) {
    include_once('fight.php');
    if (lutasabandonadas($userrow["currentaction"] == "Fighting" ? intval($userrow["id"]) : 0) !== false) {
        $userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1", "users"));
    }
}
// Conta sem perguntas secretas (contas antigas): lembrete no topo até o jogador cadastrar (users.php?do=perguntas).
if ($userrow["pergunta1"] == "" && $userrow["codigorecuperacao"] == "" && $indexconteudo == "" && empty($userrow["convidado"])) {
    $indexconteudo = "<center><font color=\"brown\">".t("Sua conta ainda não tem perguntas secretas nem código de recuperação: sem eles você não consegue recuperar a senha sozinho.")." <a href=\"users.php?do=perguntas\">".t("Cadastrar agora")."</a></font></center><br />";
}
// Não deixa fazer nada enquanto estiver em um duelo (batalha_timer2 = 5 indica duelo em andamento).
if ($userrow["batalha_timer2"] == 5) {
    display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "),t("Erro"),false,false,false);
}

// O token do "andar" automático só pode ser colocado depois que o jogador foi identificado.
$htmlnapag = str_replace("TOKENCSRF", csrf_token(), $htmlnapag);

if (isset($_GET["do"])) {
    // Formato: ?do=acao ou ?do=acao:numero (ex.: buy2:5, gotown:3, spell:1, onlinechar:12).
    $do = explode(":", pegartexto($_GET, "do"));
    $parametro = (isset($do[1])) ? intval($do[1]) : 0;

    // Ações feitas por link (viajar, jutsu medicinal, andar pelo mini mapa) exigem o token anti-CSRF:
    // assim um link vindo de outro site não consegue fazer o jogador viajar ou gastar chakra.
    if (in_array($do[0], array("gotown", "spell", "andar")) && !csrf_confere()) {
        header("Location: index.php?conteudo=".rawurlencode(t("Link inválido ou expirado. Tente novamente pelo próprio jogo."))); die();
    }

    // Funções da cidade.
    if ($do[0] == "inn") { include_once('towns.php'); inn(); }
    elseif ($do[0] == "buy") { include_once('towns.php'); buy(); }
    elseif ($do[0] == "buy2") { include_once('towns.php'); buy2($parametro); }
    elseif ($do[0] == "buy3") { include_once('towns.php'); buy3($parametro); }
    elseif ($do[0] == "sell") { include_once('towns.php'); buy(); } // O equipamento antigo é vendido ao comprar um novo (itens da mochila: do=vender).
    elseif ($do[0] == "vender") { include_once('towns.php'); vender(); }
    elseif ($do[0] == "consumiveis") { include_once('towns.php'); vender("consumiveis"); } // Aba "Consumíveis" da loja. // Consumíveis da loja e venda dos itens da mochila.
    elseif ($do[0] == "reparar") { include_once('towns.php'); reparar(); } // Reparo dos equipamentos com desgaste (durabilidade).
    elseif ($do[0] == "comprador") { include_once('towns.php'); compradorremoto(); } // Comprador de pergaminhos distante (NPC no mapa).
    elseif ($do[0] == "maps") { include_once('towns.php'); maps(); }
    elseif ($do[0] == "maps2") { include_once('towns.php'); maps2($parametro); }
    elseif ($do[0] == "maps3") { include_once('towns.php'); maps3($parametro); }
    elseif ($do[0] == "gotown") { include_once('towns.php'); travelto($parametro); }

    // Funções de exploração.
    elseif ($do[0] == "move") { include_once('explore.php'); move(); }
    elseif ($do[0] == "andar") {
        if ($andarlat === null || $andarlong === null) { header("Location: index.php"); die(); }
        include_once('explore.php'); andar($andarlat, $andarlong);
    }

    // Funções de luta.
    elseif ($do[0] == "fight") { include_once('fight.php'); fight(); }
    elseif ($do[0] == "diarias") { // Resgate de missão diária (POST com CSRF; funcoesdiarias.php).
        if ($_SERVER["REQUEST_METHOD"] != "POST" || !csrf_confere()) { header("Location: index.php?conteudo=".rawurlencode(t("Link inválido ou expirado. Tente novamente pelo próprio jogo."))); die(); }
        include_once('funcoesinclusas.php'); include_once('funcoesdiarias.php');
        header("Location: index.php?conteudo=".rawurlencode(diarias_resgatar($userrow, isset($_POST["missao"]) ? intval($_POST["missao"]) : -1))); die();
    }
    elseif ($do[0] == "victory") { include_once('fight.php'); victory(); }
    elseif ($do[0] == "drop") { include_once('fight.php'); drop(); }
    elseif ($do[0] == "dead") { include_once('fight.php'); dead(); }
    elseif ($do[0] == "duelo") { header("Location: users.php?do=batalha1"); die(); } // Os duelos ficam no users.php.

    // Outras funções.
    elseif ($do[0] == "verify") { header("Location: users.php?do=verify"); die(); }
    elseif ($do[0] == "spell") { include_once('heal.php'); healspells($parametro); }
    elseif ($do[0] == "showchar") { showchar(); }
    elseif ($do[0] == "onlinechar") { onlinechar($parametro); }
    elseif ($do[0] == "showmap") { showmap(); }
    elseif ($do[0] == "babblebox") { babblebox(); }
    elseif ($do[0] == "chatmap") { $_GET["do"] = "chatmap"; include('chat.php'); die(); } // Chat do Mapa enviado pelo index.php (alguns firewalls de hospedagem bloqueiam POST direto no chat.php).
    elseif ($do[0] == "babbleboxpage") { // Formulário do Chat Global que aparece na página da cidade.
        if (pegarint($_GET, "mapa", 0) == 1) { $_POST["fala"] = isset($_POST["babble"]) ? $_POST["babble"] : ""; $_GET["do"] = "chatmap"; include('chat.php'); die(); } // Chat do Mapa: mesmo endereço/campo do chat da cidade (o firewall da hospedagem bloqueava o outro).
        if (isset($_POST["babble"])) { gravarbabble(pegartexto($_POST, "babble")); }
        header("Location: index.php"); die();
    }
    else { donothing(); }

} else { donothing(); }

function donothing() { // Página principal: mostra a cidade, o mapa ou manda para a luta, conforme a ação atual do jogador.

    global $userrow;

    if ($userrow["currentaction"] == "In Town") {
        $page = dotown();
        $title = t("Na Cidade");
    } elseif ($userrow["currentaction"] == "Fighting") {
        dofight();
    } else {
        $page = doexplore();
        $title = t("Explorando");
    }

    display($page, $title);

}

function voltarcommensagem($texto, $pagina = "index.php") { // Redireciona para uma página do jogo mostrando uma mensagem (?conteudo=...) no topo.

    $separador = (strpos($pagina, "?") === false) ? "?" : "&";
    header("Location: ".$pagina.$separador."conteudo=".rawurlencode($texto));
    die();

}

function textocru($texto) { // Desfaz o addslashes + htmlspecialchars que o lib.php aplica em todo $_GET/$_POST (para medir, cortar ou buscar o texto original).

    return stripslashes(html_entity_decode((string)$texto, ENT_QUOTES, 'UTF-8'));

}

function cargodojogador($row) { // Cargo de um jogador: "adm" (authlevel 1), "tutor" (acesso 2), "gm" (acesso 3) ou "" (jogador comum).

    if (!$row) { return ""; }
    if ($row["authlevel"] == 1) { return "adm"; }
    if ($row["acesso"] == 2) { return "tutor"; }
    if ($row["acesso"] == 3) { return "gm"; }
    return "";

}

function cargosdosjogadores($nomes) { // Busca numa só query o cargo de vários jogadores (usado para colorir os nomes nos chats).

    $cargos = array();
    $lista = array();
    foreach (array_unique($nomes) as $nome) { $lista[] = "'".addslashes($nome)."'"; }
    if (count($lista) == 0) { return $cargos; }
    $query = doquery("SELECT charname,authlevel,acesso,caveira,caveiraate FROM {{table}} WHERE charname IN (".implode(",", $lista).")", "users");
    while ($row = mysqli_fetch_array($query)) {
        $cargos[mb_strtolower($row["charname"], 'UTF-8')] = cargodojogador($row);
        $GLOBALS["caveirasdosnomes"][mb_strtolower($row["charname"], 'UTF-8')] = selocaveira($row); // ícone da caveira PvP (ou "").
    }
    return $cargos;

}

function dotown() { // Página principal da cidade: imagem, painel de opções, notícias, quem está online e o Chat Global.

    global $userrow, $controlrow, $indexconteudo, $htmlnapag;

    $townquery = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
    if (mysqli_num_rows($townquery) == 0) { display(t("Há um erro com sua conta, ou com os dados da cidade. Por favor tente novamente."),t("Error")); }
    $townrow = mysqli_fetch_array($townquery);
    $GLOBALS["cidadeatualrow"] = $townrow; // o template towns.php usa a mesma linha (sem ler a vila de novo).

    // Imagem do título da cidade (cidades novas criadas pelo admin podem ainda não ter imagem).
    if (file_exists("images/town_".intval($townrow["id"]).".gif")) {
        $townrow["imagemcidade"] = "<img src=\"images/town_".intval($townrow["id"]).".gif\" alt=\"".tf("Bem-vindo à %s", t($townrow["name"]))."\" title=\"".tf("Bem-vindo à %s", t($townrow["name"]))."\" />";
    } else {
        $townrow["imagemcidade"] = "<span style=\"font-size:18px;font-weight:bold;\">".tf("Bem-vindo à %s", t($townrow["name"]))."</span>";
    }

    // Última notícia.
    $townrow["news"] = "";
    if ($controlrow["shownews"] == 1) {
        $newsquery = doquery("SELECT * FROM {{table}} ORDER BY id DESC LIMIT 1", "news");
        $newsrow = mysqli_fetch_array($newsquery);
        if ($newsrow) {
            $townrow["news"] = "<table width=\"95%\"><tr><td align=\"center\"><center><img src=\"images/ultimas.gif\"></center></td></tr><tr><td>\n";
            $townrow["news"] .= "<span class=\"light\">[".prettydate($newsrow["postdate"])."]</span><br />".nl2br(t($newsrow["content"])) /* Notícias novas do admin ficam em português até ganharem tradução. */;
            $townrow["news"] .= "</td></tr></table>\n";
        }
    }

    // Quem está online (jogadores ativos nos últimos 10 minutos), com a cor do cargo.
    $townrow["whosonline"] = "";
    if ($controlrow["showonline"] == 1) {
        // clatag: tag do clã (cla.php) mostrada antes do nome.
        $onlinequery = doquery("SELECT charname,authlevel,acesso,caveira,caveiraate,convidado,(SELECT c.tag FROM naruto_clas_membros m JOIN naruto_clas c ON c.id=m.cla WHERE m.usuario={{table}}.id) AS clatag FROM {{table}} WHERE UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."' ORDER BY charname", "users");
        $nomes = array(); $convidados = 0; // convidados entram na contagem, mas não na lista de nomes.
        while ($onlinerow = mysqli_fetch_array($onlinequery)) {
            if (intval($onlinerow["convidado"]) == 1) { $convidados++; continue; }
            $cargo = cargodojogador($onlinerow);
            $estilo = ($cargo != "") ? " id=\"".$cargo."\" " : "";
            $nomes[] = ($cargo == "gm" ? selogm() : "").selocaveira($onlinerow).($onlinerow["clatag"] != "" ? "<span style=\"color:#7a3d00;font-weight:bold\">[".htmlspecialchars($onlinerow["clatag"])."]</span> " : "")."<a href=\"javascript: opcaochar('".$onlinerow["charname"]."')\" ".$estilo.">".$onlinerow["charname"]."</a>";
        }
        $townrow["whosonline"] = "<table width=\"95%\"><tr><td align=\"center\"><center><img src=\"images/online.gif\"></center></td></tr><tr><td>\n";
        $townrow["whosonline"] .= tf("Há <b>%s</b> usuários online nos últimos 10 minutos: ", count($nomes) + $convidados).implode(", ", $nomes)
            .($convidados > 0 ? (count($nomes) > 0 ? ", " : "")."<i>".tf("%s convidado(s)", $convidados)."</i>" : "");
        $townrow["whosonline"] .= "</td></tr></table>\n";
    }

    // Chat Global (as 10 últimas falas).
    $townrow["babblebox"] = "";
    if ($controlrow["showbabble"] == 1) {
        $townrow["babblebox"] = "<table width=\"100%\"><tr><td align=\"center\"><center><img src=\"images/texto.gif\"></center></td></tr></table></center>";
        $townrow["babblebox"] .= babbleboxpage(460, 10);
    }

    // Mensagens no topo da página (ver início do arquivo).
    $townrow["indexconteudo"] = $indexconteudo;
    $townrow["htmlnapag"] = $htmlnapag;

    // Painel de ícones da cidade (loja, mapas, banco, missões, pousada e opções especiais de cada vila).
    include_once('cidadesconteudo.php');
    $townrow["fimconteudo"] = conteudo($townrow);

    $page = gettemplate("towns");
    $page = parsetemplate($page, $townrow);

    return $page;

}

function doexplore() { // Página do mapa: opções do mapa, jogadores na mesma coordenada e o Chat do Mapa.

    global $userrow, $indexconteudo, $htmlnapag, $opcoesnovas;
    $tokencsrf = csrf_token(); // Usado no link "Descansar".
    $t = "t"; // Para usar {$t("texto")} dentro do heredoc.

    $latitude = intval($userrow["latitude"]);
    $longitude = intval($userrow["longitude"]);

    // Jogadores online na mesma coordenada, cada um com o emblema da vila e ícones de ação (só as ações que já existem:
    // ficha, mensagem, troca, duelo, PvP e convite do clã). A ação impossível aparece apagada, com o motivo no title.
    // A tabela fica num <template> e o mostrarjogadores() copia o conteúdo (assim os formulários com CSRF não precisam ir numa string JS).
    global $controlrow;
    $usuariosmapa = "";
    $contagem = 0;
    $meuid = intval($userrow["id"]);
    $euconvidado = !empty($userrow["convidado"]);
    $csrfcampo = csrf_campo();
    $nivelduelo = intval($controlrow["pvplevel"]);
    $nivelpvp = max(intval($controlrow["pvplevel"]), intval($controlrow["pvpnivelseguro"]));
    // Desafios de duelo pendentes (users.php :: batalha1 não deixa repetir) e o meu clã (convite só para líder/oficial).
    $compendente = array();
    $qd = doquery("SELECT desafiante,desafiado FROM {{table}} WHERE (desafiante='$meuid' OR desafiado='$meuid') AND estado='pendente' AND criado >= '".(time() - 300)."'", "duelos");
    while ($d = mysqli_fetch_array($qd)) { $compendente[] = (intval($d["desafiante"]) == $meuid) ? intval($d["desafiado"]) : intval($d["desafiante"]); }
    include_once('funcoescla.php');
    $meucla = $euconvidado ? false : cladojogador($meuid);
    $possoconvidar = $meucla && ($meucla["cargo"] == "lider" || $meucla["cargo"] == "oficial");
    $casacomvila = mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE latitude='$latitude' AND longitude='$longitude' LIMIT 1", "towns")) > 0;
    // Ícone de ação: link/formulário quando possível; apagado com o motivo quando não.
    $icone = function ($img, $titulo, $motivo, $acao) {
        $figura = "<img src=\"images/acao_".$img.".png\" width=\"16\" height=\"16\" alt=\"".htmlspecialchars($titulo, ENT_QUOTES, "UTF-8")."\" />";
        if ($motivo != "") { return "<span class=\"jmicone jmoff\" data-acao=\"$img\" title=\"".htmlspecialchars($titulo.": ".$motivo, ENT_QUOTES, "UTF-8")."\">$figura</span>"; }
        return str_replace("{FIG}", $figura, str_replace("{TIT}", htmlspecialchars($titulo, ENT_QUOTES, "UTF-8"), $acao));
    };
    $usersquery = doquery("SELECT id,charname,level,alinhamento,authlevel,acesso,caveira,caveiraate,convidado,currentaction,batalha_timer2,trocajogador1,trocajogador2,currenthp,weaponid,weaponname,armorid,armorname,shieldid,shieldname,slot1id,slot1name,slot2id,slot2name,slot3id,slot3name,slot4id,slot4name,(UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."') AS online FROM {{table}} WHERE longitude='$longitude' AND latitude='$latitude' AND (UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."' OR (currentaction<>'In Town' AND ".sqlcaveiraexposta().")) AND id!='$meuid' ORDER BY level DESC", "users");
    $linhas = array(); $ids = array();
    while ($usersrow = mysqli_fetch_array($usersquery)) { $linhas[] = $usersrow; $ids[] = intval($usersrow["id"]); }
    $comcla = array();
    if ($possoconvidar && count($ids) > 0) {
        $qc = doquery("SELECT usuario FROM {{table}}_membros WHERE usuario IN (".implode(",", $ids).")", "clas");
        while ($c = mysqli_fetch_array($qc)) { $comcla[] = intval($c["usuario"]); }
    }
    $semconta = t("Crie a sua conta para usar esta ação.");
    foreach ($linhas as $usersrow) {
        $bgcolor = ($contagem % 2 == 0) ? "#E4D094" : "#FFF1C7"; // Linhas zebradas.
        $contagem++;
        $cargo = cargodojogador($usersrow);
        $estilo = ($cargo != "") ? " id=\"".$cargo."\" " : "";
        $nome = $usersrow["charname"];
        $nomehtml = htmlspecialchars($nome, ENT_QUOTES, "UTF-8");
        $alvoid = intval($usersrow["id"]);
        $alvoconvidado = !empty($usersrow["convidado"]);
        $online = !empty($usersrow["online"]);
        // Ficha e mensagem.
        $acoes = $icone("ficha", t("Ver ficha"), "", "<a class=\"jmicone\" data-acao=\"ficha\" href=\"javascript:mostrarchar('".$nomehtml."')\" title=\"{TIT}\">{FIG}</a>");
        $acoes .= $icone("mensagem", t("Enviar Mensagem"), $euconvidado ? $semconta : "", "<a class=\"jmicone\" data-acao=\"mensagem\" href=\"mainmsg.php?do2=enviarpm&amp;nomedochar=".urlencode($nome)."\" title=\"{TIT}\">{FIG}</a>");
        // Troca (troca.php: convidado não troca, nem durante um duelo).
        $motivo = $euconvidado ? $semconta : ($alvoconvidado ? t("Esse jogador é convidado (sem conta).") : (intval($usersrow["batalha_timer2"]) == 5 ? t("Esse jogador está em um duelo. Tente novamente mais tarde.") : (!$online ? t("Esse jogador não está online.") : "")));
        $acoes .= $icone("troca", t("Propor troca"), $motivo, "<a class=\"jmicone\" data-acao=\"troca\" href=\"troca.php?do=troca&amp;jogador=".urlencode($nome)."\" title=\"{TIT}\">{FIG}</a>");
        // Duelo (mesmas regras de users.php :: podeduelar(); o servidor confere tudo de novo).
        $motivo = "";
        if ($euconvidado) { $motivo = $semconta; }
        elseif ($alvoconvidado) { $motivo = t("Esse jogador é convidado (sem conta)."); }
        elseif (intval($userrow["level"]) < $nivelduelo) { $motivo = t("Você ainda não tem o level mínimo para duelar."); }
        elseif (intval($usersrow["level"]) < $nivelduelo) { $motivo = t("Esse jogador ainda não tem o level mínimo para duelar."); }
        elseif (intval($userrow["batalha_timer2"]) == 5) { $motivo = t("Você já está em um duelo."); }
        elseif ($userrow["trocajogador1"] != "None") { $motivo = t("Termine ou cancele a sua troca antes de duelar."); }
        elseif (intval($userrow["currenthp"]) <= 1) { $motivo = t("Você precisa de mais de 1 HP para duelar. Descanse antes."); }
        elseif (intval($usersrow["authlevel"]) == 2) { $motivo = t("Esse jogador não pode duelar."); }
        elseif (!$online) { $motivo = t("Esse jogador não está online."); }
        elseif ($usersrow["currentaction"] == "Fighting") { $motivo = t("Esse jogador está no meio de uma batalha. Tente novamente mais tarde."); }
        elseif (intval($usersrow["batalha_timer2"]) == 5) { $motivo = t("Esse jogador já está em um duelo com outra pessoa."); }
        elseif ($usersrow["trocajogador1"] != "None") { $motivo = t("Esse jogador está fazendo uma troca. Tente novamente mais tarde."); }
        elseif (intval($usersrow["currenthp"]) <= 1) { $motivo = t("Esse jogador está sem Pontos de Vida e não pode duelar agora."); }
        elseif (in_array($alvoid, $compendente)) { $motivo = t("Desafio pendente"); }
        $acoes .= $icone("duelo", t("Desafiar para duelo (sem aposta)"), $motivo, "<form class=\"jmform\" action=\"users.php?do=batalha1\" method=\"post\">".$csrfcampo."<input type=\"hidden\" name=\"jogador\" value=\"".$nomehtml."\" /><input type=\"hidden\" name=\"aposta\" value=\"0\" /><button type=\"submit\" name=\"acao\" value=\"desafiar\" class=\"jmicone\" data-acao=\"duelo\" title=\"{TIT}\">{FIG}</button></form>");
        // PvP no mundo (só com control.pvpmundo ligado; pvp.php?alvo mostra a confirmação e confere todas as regras).
        if (!empty($controlrow["pvpmundo"])) {
            $motivo = "";
            if ($euconvidado) { $motivo = $semconta; }
            elseif ($alvoconvidado) { $motivo = t("Esse jogador é convidado (sem conta)."); }
            elseif (ehgm($userrow) || ehgm($usersrow)) { $motivo = t("GameMasters não participam do PvP."); }
            elseif (intval($usersrow["authlevel"]) == 2) { $motivo = t("Esse jogador não pode ser atacado."); }
            elseif (intval($userrow["level"]) < $nivelpvp) { $motivo = tf("Você só pode atacar outros jogadores a partir do level %s.", $nivelpvp); }
            elseif (intval($usersrow["level"]) < $nivelpvp) { $motivo = tf("Jogadores abaixo do level %s estão protegidos do PvP.", $nivelpvp); }
            elseif (intval($controlrow["pvpdiflevel"]) > 0 && abs(intval($userrow["level"]) - intval($usersrow["level"])) > intval($controlrow["pvpdiflevel"])) { $motivo = tf("A diferença de level entre vocês é maior que %s.", intval($controlrow["pvpdiflevel"])); }
            elseif ($casacomvila) { $motivo = t("Não é permitido lutar dentro das vilas."); }
            elseif ($usersrow["currentaction"] == "In Town") { $motivo = t("Esse jogador está dentro de uma vila."); }
            $acoes .= $icone("pvp", t("Atacar"), $motivo, "<a class=\"jmicone\" data-acao=\"pvp\" href=\"pvp.php?alvo=".$alvoid."\" title=\"{TIT}\">{FIG}</a>");
        }
        // Convite do clã (cla.php?do=convidar): só aparece para líder/oficial; o alvo não pode ter clã nem ser convidado.
        if ($possoconvidar) {
            $motivo = $alvoconvidado ? t("Esse jogador é convidado (sem conta).") : (in_array($alvoid, $comcla) ? t("Esse jogador já está em um clã.") : "");
            $acoes .= $icone("cla", t("Convidar para o clã"), $motivo, "<form class=\"jmform\" action=\"cla.php?do=convidar\" method=\"post\">".$csrfcampo."<input type=\"hidden\" name=\"nome\" value=\"".$nomehtml."\" /><button type=\"submit\" class=\"jmicone\" data-acao=\"cla\" title=\"{TIT}\">{FIG}</button></form>");
        }
        // Equipamentos (arma, colete, bandana e acessórios): ícones do jogo (iconeitem) em 16x16, com o nome no title.
        include_once('funcoesinclusas.php');
        $itens = "";
        foreach (array("weapon"=>false, "armor"=>false, "shield"=>false, "slot1"=>true, "slot2"=>true, "slot3"=>true, "slot4"=>true) as $sl => $dropsl) {
            if (intval($usersrow[$sl."id"]) <= 0 || $usersrow[$sl."name"] == "None" || $usersrow[$sl."name"] == "") { continue; }
            $tit = htmlspecialchars(strip_tags(nomeitemhtml($usersrow[$sl."name"])), ENT_QUOTES, "UTF-8");
            $itens .= "<span class=\"jmicone jmitem\" title=\"".$tit."\"><img src=\"".iconeitem($usersrow[$sl."id"], $dropsl, $usersrow[$sl."name"])."\" width=\"16\" height=\"16\" alt=\"".$tit."\" /></span>";
        }
        if ($itens != "") { $itens = "<div class=\"jmitens\">".$itens."</div>"; }
        $usuariosmapa .= "<tr bgcolor=\"$bgcolor\" class=\"jmlinha\" data-jogador=\"".$nomehtml."\"><td width=\"22\">".selovila($usersrow["alinhamento"])."</td><td class=\"jmnome\">".($cargo == "gm" ? selogm() : "").selocaveira($usersrow)."<a href=\"javascript:opcaochar('".$nomehtml."')\" title=\"".tf("Level: %s", intval($usersrow["level"]))."\" $estilo>".$nomehtml."</a>".$itens."</td><td class=\"jmacoes\">".$acoes."</td></tr>";
    }
    if ($usuariosmapa == "") { $usuariosmapa = "<tr><td bgcolor=\"#E4D094\">".t("Nenhum")."</td></tr>"; }

    // PvP no mundo (control.pvpmundo): jogadores online na casa atual e nas 8 vizinhas, com o link "Atacar" (pvp.php confere as regras).
    global $controlrow;
    $pvplista = "";
    if (!empty($controlrow["pvpmundo"])) {
        // Caveira exposta (lib.php :: sqlcaveiraexposta()): quem matou há pouco com caveira aparece mesmo offline, na última casa (marcado "offline").
        $exposta = sqlcaveiraexposta();
        $pvpquery = doquery("SELECT id,charname,level,alinhamento,authlevel,acesso,latitude,longitude,caveira,caveiraate,(UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."') AS online FROM {{table}} WHERE latitude BETWEEN '".($latitude - 1)."' AND '".($latitude + 1)."' AND longitude BETWEEN '".($longitude - 1)."' AND '".($longitude + 1)."' AND (currentaction='Exploring' OR (currentaction='Fighting' AND $exposta AND UNIX_TIMESTAMP(onlinetime) < '".(time()-600)."')) AND authlevel<>'2' AND convidado='0' AND (UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."' OR $exposta) AND id!='".intval($userrow["id"])."' ORDER BY level DESC LIMIT 15", "users");
        while ($p = mysqli_fetch_array($pvpquery)) {
            $selo = (ehgm($p) ? selogm() : "").selocaveira($p);
            $acao = ehgm($p) ? "<span class=\"light\">".t("GM")."</span>" : "<a href=\"pvp.php?alvo=".intval($p["id"])."\"><img src=\"images/pvp_atacar.gif\" border=\"0\" alt=\"\" style=\"vertical-align:middle\" /> ".t("Atacar")."</a>";
            $pvplista .= "<tr bgcolor=\"#FFF1C7\"><td>$selo<a href=\"javascript: opcaochar('".$p["charname"]."')\">".$p["charname"]."</a> <span class=\"light\">Lv ".intval($p["level"])." [".intval($p["latitude"]).",".intval($p["longitude"])."]".(empty($p["online"]) ? " ".t("(offline, caveira exposta)") : "")."</span></td><td align=\"right\" nowrap=\"nowrap\">$acao</td></tr>";
        }
        if ($pvplista == "") { $pvplista = "<tr bgcolor=\"#FFF1C7\"><td>".t("Ninguém por perto.")."</td></tr>"; }
        $pvplista = "<br><table width=\"100%\" bgcolor=\"#452202\"><tr><td><font color=\"white\">".t("PvP: ninjas por perto")."</font></td></tr></table><table width=\"100%\">".$pvplista."</table>";
    }
    $usuariosmapa = "<table width=\"100%\" class=\"jmtabela\">".$usuariosmapa."</table>";

    // Imagem do personagem correndo.
    $nome = intval($userrow["avatar"])."_run.gif";
    if (!function_exists('mx_coordenada')) { include_once('funcoesmissoesextras.php'); }
    $coordexplorar = mx_coordenada(intval($userrow["latitude"]), intval($userrow["longitude"])); // "12N,5W" como no mapa.
    $pctvida = max(0, min(100, round(intval($userrow["currenthp"]) * 100 / max(1, intval($userrow["maxhp"])))));

    // Botão para resgatar o drop da última luta (dropcode != 0).
    $drop = "";
    if ($userrow["dropcode"] != 0) { $drop = "<a href=\"index.php?do=drop\"><img src=\"images/24/exclamacao.gif\" title=\"".t("Resgatar Drop")."\" alt=\"X\" border=\"0\"></a>"; }

    // NPCs das missões ($opcoesnovas recebe o botão "Conversar" quando o jogador está em cima de um NPC).
    include('localpersonagens.php');

    // Chat do Mapa: cada coordenada tem o seu (as 10 últimas falas, gravadas pelo chat.php).
    $chatquery = doquery("SELECT * FROM {{table}} WHERE latitude='$latitude' AND longitude='$longitude' ORDER BY id", "chatmap");
    $falas = array();
    $autores = array();
    while ($chatrow = mysqli_fetch_array($chatquery)) { $falas[] = $chatrow; $autores[] = $chatrow["name"]; }
    $cargos = cargosdosjogadores($autores);
    $coresdocargo = array("adm" => "green", "tutor" => "orange", "gm" => "blue");
    $chat = "<br><br><br><center><table width=\"90%\"><tr bgcolor=\"#452202\"><td width=\"*\"><center><font color=\"white\">".t("Chat do Mapa")."</font></center></td></tr></table>";
    $chat .= "<table width=\"90%\" >";
    foreach ($falas as $i => $chatrow) {
        $bgcolor = ($i % 2 == 1) ? "#E4D094" : "#FFF1C7";
        $chave = mb_strtolower($chatrow["name"], 'UTF-8');
        $cargo = isset($cargos[$chave]) ? $cargos[$chave] : "";
        $estilo = ""; $cor = ""; $cor2 = "";
        if ($cargo != "") { $estilo = " id=\"".$cargo."\" "; $cor = "<font color=\"".$coresdocargo[$cargo]."\">"; $cor2 = "</font>"; }
        $chat .= "<tr bgcolor=\"$bgcolor\" ><td style=\"border:1px #000000 solid\">[".($cargo == "gm" ? selogm() : "").(isset($GLOBALS["caveirasdosnomes"][$chave]) ? $GLOBALS["caveirasdosnomes"][$chave] : "")."<b><a href=\"javascript: opcaochar('".$chatrow['name']."')\" $estilo>".$chatrow['name']."</a></b>] $cor".$chatrow['fala']."$cor2</td></tr>";
    }
    $chat .= "</table></center>";

$page = <<<END
<table width="100%">
<tr><td align="center"><center><img src="images/title_exploring.gif" alt="{$t("Explorando")}" /></center>
$indexconteudo
$htmlnapag
</td></tr>
<tr><td>

<div style="position: relative; top: -25px; margin-bottom: -30px;">
<table><tr><td width="310">
<br><br>
<table width="100%" bgcolor="#452202"><tr><td><font color="white">{$t("Opções do Mapa")}</font></td></tr>
<tr><td background="layoutnovo/menumeio/meio2.png">$drop<a href="encherhp.php?csrf=$tokencsrf"><img src="images/24/descansar.gif" border="0" title="{$t("Descansar")}" alt="X"></a>$opcoesnovas</td></tr>
</table>
<br>
<table width="100%" bgcolor="#452202"><tr><td width="*"><font color="white">{$t("Jogadores Online no Mesmo Mapa")}</font></td><td width="20"><a href="javascript: mostrarjogadores(document.getElementById('jogadoresmapa_modelo').innerHTML);" id="jogadoresmapa_abrir"><img src="images/setabaixo.gif" title="{$t("Mostrar Jogadores")}" alt="X" border="0"></a></td><td width="20"><a href="javascript: fecharjogadores();"><img src="images/setacima.gif" title="{$t("Fechar Jogadores")}" alt="X" border="0"></a></td></tr></table>
<div id="jogadoresmapa"></div>
<template id="jogadoresmapa_modelo">$usuariosmapa</template>
<style>
.jmtabela td { vertical-align: middle; }
.jmnome { word-break: break-word; }
.jmacoes { text-align: right; white-space: nowrap; }
.jmicone { display: inline-block; vertical-align: middle; margin: 0 1px; padding: 0; border: 0; background: none; cursor: pointer; line-height: 0; }
.jmicone img { width: 16px; height: 16px; image-rendering: pixelated; }
.jmoff { cursor: help; opacity: .35; filter: grayscale(1); }
.jmform { display: inline; margin: 0; }
.jmitens { margin-top: 1px; line-height: 0; }
.jmitem { cursor: help; margin: 0 1px 0 0; }
html.modocel .jmacoes { white-space: normal; }
html.modocel .jmicone img { width: 18px; height: 18px; }
html.modocel .jmnome { white-space: nowrap; word-break: normal; }
html.modocel .jmform { display: inline-block; }
html.modocel button.jmicone { min-height: 0; padding: 0; font-size: 0; }
html.modocel .jmicone { margin: 1px; }
/* Celular: a coluna é estreita e os ícones de ação eram espremidos até sumir; vão para uma linha própria abaixo do nome. */
html.modocel tr.jmlinha { display: flex; flex-wrap: wrap; align-items: center; }
html.modocel tr.jmlinha > td.jmnome { flex: 1 1 auto; min-width: 0; }
html.modocel tr.jmlinha > td.jmacoes { flex: 1 0 100%; text-align: left; padding: 2px 4px 4px; box-sizing: border-box; }
html.modocel .jmicone img, html.modocel .jmitem img { max-width: none; }
</style>
$pvplista

</td><td valign="middle">

<div style="z-index: 1; position:relative">
<div style="width:165px;margin:0 auto"><!-- Barra de vida do personagem acima da imagem. -->
<div class="vidaexplorar" title="HP {$userrow["currenthp"]}/{$userrow["maxhp"]}" style="width:140px;margin:0 auto -14px;position:relative;top:14px;z-index:2;font:bold 10px tahoma,arial,sans-serif;color:#452202"><div style="display:flex;justify-content:space-between"><span>HP {$userrow["currenthp"]}/{$userrow["maxhp"]}</span><span id="coordexplorar" title="Latitude, Longitude">&#128205; $coordexplorar</span></div><div style="height:8px;background:#3a1d05;border:1px solid #452202;border-radius:3px;overflow:hidden"><div style="height:100%;width:{$pctvida}%;background:#c0281c"></div></div></div>
<table width="165" height="175" background="layoutnovo/graficos/fundo2.png" style="background-repeat:no-repeat;;background-position:left top"><tr height="10%"><td></td></tr><tr ><td valign="bottom"><center><img src="layoutnovo/graficos/$nome"></center>
</td></tr><tr><td></td></tr></table></div></div>
<!-- Setas no formato das setas do teclado (↑ em cima; ← ↓ → embaixo). Setas pequenas de andar logo abaixo do personagem (mesmo form do menu esquerdo; útil no celular). -->
<form action="index.php?do=move" method="post" class="setasmini" style="display:grid;grid-template-columns:repeat(3,26px);grid-template-rows:repeat(2,26px);gap:2px;justify-content:center;margin:-34px auto 0;position:relative;z-index:2">
<style>.setasmini button{width:26px;height:26px;min-height:0;padding:0;margin:0;background:#fff1c7;border:1px solid #8b5a2b;border-radius:4px;cursor:pointer;display:flex;align-items:center;justify-content:center}.setasmini button:hover{background:#ffe3a0}.setasmini img{display:block}</style>
<button name="north" type="submit" value="1" title="{$t("Norte")}" style="grid-column:2;grid-row:1"><img src="images/seta_norte.png" width="16" height="16" alt="{$t("Norte")}" /></button>
<button name="west" type="submit" value="1" title="{$t("Oeste")}" style="grid-column:1;grid-row:2"><img src="images/seta_oeste.png" width="16" height="16" alt="{$t("Oeste")}" /></button>
<button name="east" type="submit" value="1" title="{$t("Leste")}" style="grid-column:3;grid-row:2"><img src="images/seta_leste.png" width="16" height="16" alt="{$t("Leste")}" /></button>
<button name="south" type="submit" value="1" title="{$t("Sul")}" style="grid-column:2;grid-row:2"><img src="images/seta_sul.png" width="16" height="16" alt="{$t("Sul")}" /></button>
</form>

</td></tr></table>

{$t("Você está explorando o mapa, nada aconteceu. Continue explorando usando os botões de direção ou o menu Viajar.")}
$chat
<form action="index.php?do=babbleboxpage&amp;mapa=1" method="post" style="display:flex;gap:4px;width:90%;margin:4px auto 0;box-sizing:border-box"><input type="text" name="babble" maxlength="200" style="flex:1;min-width:0;box-sizing:border-box;padding:3px 5px;border:1px solid #452202;background:#fff9e3"><button type="submit" id="submit2" name="submit" value="OK" class="btnjogo bj-confirmar" style="flex:none;margin:0"><img src="images/botao_falar.png" width="16" height="16" alt="" /> OK</button></form>
</div></td></tr>
</table>
END;

    return $page;

}

function dofight() { // Quem está numa luta é sempre mandado para a tela de luta.

    header("Location: index.php?do=fight");
    die();

}

function fichadopersonagem($row, $comexplicacao = false) { // Prepara a linha de um jogador para os templates de ficha (showchar/onlinechar).

    global $controlrow;

    $row["experience"] = number_format((float)$row["experience"]);
    // Quanto do mapa-múndi o jogador já descobriu (neblina de guerra, funcoesneblina.php).
    include_once('funcoesneblina.php');
    $row["mapadescobertopct"] = isset($row["mapadescoberto"]) ? neblina_percentual(neblina_mapadovisitante($row)) : "0";
    $row["gold"] = number_format((float)$row["gold"]);

    // Bônus de experiência / ouro (vêm de itens especiais), ex.: "(+10%)".
    $row["plusexp"] = "";
    if ($row["expbonus"] > 0) { $row["plusexp"] = "<span class=\"light\">(+".intval($row["expbonus"])."%)</span>"; }
    elseif ($row["expbonus"] < 0) { $row["plusexp"] = "<span class=\"light\">(".intval($row["expbonus"])."%)</span>"; }
    $row["plusgold"] = "";
    if ($row["goldbonus"] > 0) { $row["plusgold"] = "<span class=\"light\">(+".intval($row["goldbonus"])."%)</span>"; }
    elseif ($row["goldbonus"] < 0) { $row["plusgold"] = "<span class=\"light\">(".intval($row["goldbonus"])."%)</span>"; }

    // Experiência do próximo level: a tabela levels tem uma coluna por classe (1_exp, 2_exp, 3_exp).
    $classe = intval($row["charclass"]);
    $row["nextlevel"] = "<span class=\"light\">None</span>";
    if ($classe >= 0 && $classe <= 3) { // sem especialização (0) usa a mesma curva (colunas 1_*).
        $colunaexp = max(1, $classe);
        $levelquery = doquery("SELECT ".$colunaexp."_exp FROM {{table}} WHERE id='".(intval($row["level"])+1)."' LIMIT 1", "levels");
        $levelrow = mysqli_fetch_array($levelquery);
        if ($levelrow) { $row["nextlevel"] = number_format((float)$levelrow[$colunaexp."_exp"]); }
    }

    $row["charclass"] = ($classe >= 1) ? t(nomedaclasse($classe)) : t("Sem especialização (Estudante)");
    $dificuldade = intval($row["difficulty"]);
    $row["difficulty"] = ($dificuldade >= 1 && $dificuldade <= 3) ? t($controlrow["diff".$dificuldade."name"]) : "";
    // Trocou de dificuldade (provação do Mestre das Provações, docs/TROCA_DE_DIFICULDADE.md): mostra desde quando.
    if (isset($row["dificuldadetroca_em"]) && intval($row["dificuldadetroca_em"]) > 0) { $row["difficulty"] .= " <span class=\"small\">(".tf("desde %s", date("d/m/Y", intval($row["dificuldadetroca_em"]))).")</span>"; }

    // Nomes vindos do banco (itens, drops e graduação) são exibidos no idioma do jogador.
    // Os nomes dos equipamentos podem ter a variante rara (":efeito"): o nome guardado fica em "...namebruto"
    // (para os tooltips), {{...icone}} recebe o ícone da variante e o nome exibido ganha o rótulo colorido.
    include_once('funcoesinclusas.php');
    iconesequipados($row);
    foreach (array("weaponname", "armorname", "shieldname", "slot1name", "slot2name", "slot3name", "slot4name") as $campo) { $row[$campo."bruto"] = $row[$campo]; $row[$campo] = nomeitemhtml($row[$campo]); }
    $row["graduacao"] = t($row["graduacao"]);

    // Cargo na equipe do jogo.
    $cargos = array("adm" => "<font color=green>".t("Administrador")."</font><br>", "tutor" => "<font color=orange>".t("Tutor")."</font><br>", "gm" => selogm()."<font color=blue>".t("GameMaster")."</font><br>");
    $cargo = cargodojogador($row);
    $row["adm"] = (($cargo != "") ? $cargos[$cargo] : "").(caveiraativa($row) != "" ? selocaveira($row).(caveiraativa($row) == "branca" ? t("Caveira branca") : "<font color=\"darkred\">".t("Caveira vermelha")."</font>")."<br>" : "");

    // Durabilidade dos equipamentos: "-,arma,colete,bandana,slot1,slot2,slot3,slot4" ("X" = item sem desgaste, mostrado como "*").
    $durabm = array_pad(explode(",", (string)$row["durabilidade"]), 8, "X");
    for ($i = 1; $i <= 7; $i++) {
        if ($durabm[$i] == "X") { $durabm[$i] = "*"; }
        $row["durabm".$i] = $durabm[$i];
    }

    // Fundo da segunda imagem da arma (armas de duas mãos), usado no template showchar.
    $row["segundaarmafundo"] = file_exists("layoutnovo/equipamentos/".intval($row["weaponid"])."d.gif") ? iconeitem($row["weaponid"], false, $row["weaponnamebruto"], "d") : "images/gif.gif";

    // Tooltips dos equipamentos (usados no template onlinechar).
    if ($comexplicacao == true) {
        include_once('funcoesinclusas.php');
        $row["armaatr"] = conteudoexplic($row["weaponid"], '1', 'armaatr', $durabm[1], $row["weaponnamebruto"]);
        $row["segundaarma"] = file_exists("layoutnovo/equipamentos/".intval($row["weaponid"])."d.gif") ? "<img src=\"".iconeitem($row["weaponid"], false, $row["weaponnamebruto"], "d")."\" border=\"0\" onMouseOver=\"".$row["armaatr"]."\" onmouseout=\"fecharexplic();\" alt=\"\" />" : ""; // Segunda imagem (armas de duas mãos).
        $row["armoratr"] = conteudoexplic($row["armorid"], '2', 'armoratr', $durabm[2], $row["armornamebruto"]);
        $row["shieldatr"] = conteudoexplic($row["shieldid"], '3', 'shieldatr', $durabm[3], $row["shieldnamebruto"]);
        $row["slot1atr"] = conteudoexplic($row["slot1id"], '4', 'slot1atr', $durabm[4], $row["slot1namebruto"]);
        $row["slot2atr"] = conteudoexplic($row["slot2id"], '4', 'slot2atr', $durabm[5], $row["slot2namebruto"]);
        $row["slot3atr"] = conteudoexplic($row["slot3id"], '4', 'slot3atr', $durabm[6], $row["slot3namebruto"]);
        $row["slot4atr"] = conteudoexplic($row["slot4id"], '4', 'slot4atr', $durabm[7], $row["slot4namebruto"]);
    }

    // Lista de jutsus aprendidos (users.spells = "0,1,4,..."), mais Senjutsu e Jutsu de Busca se tiverem sido treinados.
    $spellquery = doquery("SELECT id,name FROM {{table}} ORDER BY id", "spells");
    $userspells = explode(",", (string)$row["spells"]);
    $row["magiclist"] = "";
    while ($spellrow = mysqli_fetch_array($spellquery)) {
        if (in_array($spellrow["id"], $userspells)) { $row["magiclist"] .= t($spellrow["name"])."<br />"; }
    }
    if ($row["magiclist"] == "") { $row["magiclist"] = "None"; }
    if ($row["senjutsuhtml"] != "") { $row["magiclist"] = "<font color=darkgreen>".t("Senjutsu")."</font><br>".$row["magiclist"]; }
    if ($row["jutsudebuscahtml"] != "") { $row["magiclist"] = "<font color=darkgreen>".t("Jutsu de Busca")."</font><br>".$row["magiclist"]; }

    return $row;

}

function enderecodojogo() { // Endereço base do jogo (naruto_control.gameurl sem o "index.php" do final), ex.: "http://site.com/narutorpg/".

    global $controlrow;
    $endereco = preg_replace("/index\.php$/i", "", trim((string)$controlrow["gameurl"]));
    if ($endereco != "" && substr($endereco, -1) != "/") { $endereco .= "/"; }
    return $endereco;

}

function cabecalhopopup() { // Cabeçalho XHTML das janelas popup (ficha, mapa e chat).

    return "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n"
    . "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"".idioma()."\" lang=\"".idioma()."\">\n";

}

function showchar() { // Popup com a ficha completa do próprio jogador, mais o link público do perfil.

    global $userrow;

    $ficha = fichadopersonagem($userrow);
    // Vila natal (e desde quando, se trocou): mesmo texto da ficha pública (funcoesficha.php).
    include_once('funcoesficha.php');
    $publica = fichapublica($userrow);
    $ficha["vilanatal"] = $publica["vilanatal"];
    $ficha["elementos"] = $publica["natureza"]; // "Fogo / Vento" com ícones (elemento.php).
    $linkperfil = enderecodojogo()."mostrarchar.php?nomechar=".rawurlencode(html_entity_decode($userrow["charname"], ENT_QUOTES, 'UTF-8'));
    $embaixo = "<center><font color=\"white\">".t("Link do Personagem:")."</font><br><input type=\"text\" size=\"20\" value=\"".htmlspecialchars($linkperfil, ENT_QUOTES, 'UTF-8')."\"></center>";

    $page = cabecalhopopup() . gettemplate("minimal") . $embaixo;
    $array = array("content"=>parsetemplate(gettemplate("showchar"), $ficha), "title"=>t("Informação do Personagem"));
    echo parsetemplate($page, $array);
    die();

}

function onlinechar($id) { // Popup com a ficha de outro jogador, pelo id (usado pela busca de jogadores do users.php).

    $userquery = doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "users");
    if (mysqli_num_rows($userquery) != 1) { echo "<font color=white>".t("Nenhum usuário")."</font>."; die(); }
    include_once('funcoesficha.php'); // só dados públicos (fichapublica), igual ao mostrarchar.php.
    $ficha = fichapublica(mysqli_fetch_array($userquery));

    $page = cabecalhopopup() . gettemplate("minimal");
    $array = array("content"=>parsetemplate(gettemplate("onlinechar"), $ficha), "title"=>t("Informação do Personagem"));
    echo parsetemplate($page, $array);
    die();

}

function showmap() { // Popup com o mapa-múndi dividido em 20x20 quadrantes, marcando onde o jogador está.

    // Parâmetros opcionais (GET), usados pelas missões, graduações e ajuda:
    //   lugarazul=lat/long             -> pinta de azul o quadrante dessa coordenada;
    //   resp=lat1/long1,lat2/long2     -> pinta de azul os quadrantes que tocam o retângulo (lat1,long1)-(lat2,long2);
    //   item=Nome do drop              -> mesma coisa, num quadrado centrado em [0,0] calculado pelo nível do drop (mlevel);
    //   monstro=Nome do inimigo        -> só a "borda" desse quadrado (o anel onde o inimigo aparece), pelo level dele.
    //                                     Se o inimigo tem região "apenas nesta região" (painel do admin), marca só os quadrantes
    //                                     que tocam a região E a faixa de distância do level dele.
    // Regra do jogo por trás disso: o level dos inimigos cresce com a distância do centro do mapa (+1 level a cada 5 casas).

    global $userrow, $controlrow, $link;

    // Alvo azul ("lat/long").
    $alvolat = null; $alvolong = null;
    $lugarazul = explode("/", pegartexto($_GET, "lugarazul"));
    if (count($lugarazul) == 2 && is_numeric($lugarazul[0]) && is_numeric($lugarazul[1])) { $alvolat = intval($lugarazul[0]); $alvolong = intval($lugarazul[1]); }

    // Região: $regiao = array(latitude de cima, longitude da esquerda, latitude de baixo, longitude da direita).
    $regiao = null;
    $regiaomonstro = null; // Região do inimigo procurado: array(latmin, latmax, lonmin, lonmax, exclusiva).
    $textoterrenos = "";   // Terrenos do mini mapa em que o inimigo procurado aparece ("Deserto, Rochoso").
    $faixamin = 0; $faixamax = 0;
    $resp = preg_split("/[\/,]/", pegartexto($_GET, "resp"));
    if (count($resp) == 4 && is_numeric($resp[0]) && is_numeric($resp[1]) && is_numeric($resp[2]) && is_numeric($resp[3])) {
        $regiao = array(intval($resp[0]), intval($resp[1]), intval($resp[2]), intval($resp[3]));
    }

    // Os nomes vêm do JavaScript das missões; o texto original é recuperado e escapado de novo para a query e para o HTML.
    $item = textocru(pegartexto($_GET, "item"));
    $monstro = textocru(pegartexto($_GET, "monstro"));
    $var = ""; // Sufixo das imagens: "_2" troca as cores quando a busca é de um item.

    if ($item != "") {
        $var = "_2";
        $monstro = "";
        $itemquery = doquery("SELECT mlevel FROM {{table}} WHERE name='".mysqli_real_escape_string($link, $item)."' LIMIT 1", "drops");
        if (mysqli_num_rows($itemquery) == 0) { die(t("Houve um erro ao pesquisar o item.")); }
        $droprow = mysqli_fetch_array($itemquery);
        $raio = 5*intval($droprow["mlevel"]) - 5;
        $regiao = array($raio, -$raio, -$raio, $raio);
    } elseif ($monstro != "") {
        $mquery = doquery("SELECT level,regiao_latmin,regiao_latmax,regiao_lonmin,regiao_lonmax,regiao_exclusiva,terrenos FROM {{table}} WHERE name='".mysqli_real_escape_string($link, $monstro)."' LIMIT 1", "monsters");
        if (mysqli_num_rows($mquery) == 0) { die(t("Houve um erro ao pesquisar o inimigo.")); }
        $mrow = mysqli_fetch_array($mquery);
        $mlevel = (intval($mrow["level"]) == 1) ? 2 : intval($mrow["level"]);
        $raio = 5*$mlevel - 7; // 7 (e não 5) porque estava havendo variação.
        $regiao = array($raio, -$raio, -$raio, $raio);
        include_once('funcoesterreno.php'); $textoterrenos = textoterrenosdoinimigo($mrow["terrenos"]); // terrenos em que ele aparece (vazio = qualquer).
        if ($mrow["regiao_latmin"] !== null && $mrow["regiao_latmax"] !== null && $mrow["regiao_lonmin"] !== null && $mrow["regiao_lonmax"] !== null) {
            $regiaomonstro = array(intval($mrow["regiao_latmin"]), intval($mrow["regiao_latmax"]), intval($mrow["regiao_lonmin"]), intval($mrow["regiao_lonmax"]), intval($mrow["regiao_exclusiva"]));
            // Faixa de distância (maior entre |lat| e |long|) em que o level do inimigo é sorteado pelo fight.php.
            $faixamin = max(0, 5*intval($mrow["level"]) - 5);
            $faixamax = 5*intval($mrow["level"]) + 9;
        }
    }
    $item = htmlspecialchars($item, ENT_QUOTES, 'UTF-8');
    $monstro = htmlspecialchars($monstro, ENT_QUOTES, 'UTF-8');
    $nomeitem = ($item != "") ? t($item) : "";         // Nomes exibidos (traduzidos); $item/$monstro continuam com o valor original.
    $nomemonstro = ($monstro != "") ? t($monstro) : "";
    // Frases dos quadrantes (traduzidas uma vez só, fora do laço de 400 células).
    $frases = array(
        "azulaqui" => ($monstro != "") ? tf("Você pode encontrar o(a) %s aqui, no seu quadrante.", $nomemonstro) : t("O que/quem você procura está no seu quadrante."),
        "azul" => ($monstro != "") ? tf("Você pode encontrar o(a) %s aqui.", $nomemonstro) : t("O que/quem você procura está aqui."),
        "aqui" => t("Você está aqui."),
        "dropa" => tf("%s pode ser dropado(a) aqui.", $nomeitem),
        "dropaaqui" => tf("%s pode ser dropado(a) aqui e você está aqui.", $nomeitem),
        "ate" => t("[%s,%s] até [%s,%s]"));

    // Tamanho do mapa: as coordenadas vão de -gamesize a +gamesize. Cada um dos 20 quadrantes tem $lado coordenadas;
    // quando a divisão não é exata, os primeiros $sobra quadrantes de cada linha/coluna ganham 1 coordenada a mais.
    $tamanhomapa = intval($controlrow["gamesize"]);
    $lado = floor($tamanhomapa*2/20);
    $sobra = $tamanhomapa*2 - ($lado*20);
    $encolher = $tamanhomapa/20 * 2; // Para inimigos: o miolo do quadrado (encolhido 1 quadrante) não é marcado.
    $userlat = intval($userrow["latitude"]);
    $userlong = intval($userrow["longitude"]);

    // Neblina de guerra (funcoesneblina.php): quadrantes que o jogador ainda não pisou ficam cobertos (sem marcações nem NPCs).
    // Desligável para todos no painel (control.neblinaativa). Admin e GM veem o mapa todo, com um link para ver como o jogador.
    include_once('funcoesneblina.php');
    neblina_descobrir($userrow, $userlat, $userlong);
    $equipe = ($userrow["authlevel"] == 1 || ehgm($userrow));
    $verneblina = pegarint($_GET, "neblina", 0);
    $neblina = (intval($controlrow["neblinaativa"]) == 1) && (!$equipe || $verneblina == 1);
    // Olho Celeste ativo (naruto_doujutsu.ver_mapa): o mapa geral aparece inteiro, sem neblina.
    include_once('funcoesdoujutsu.php');
    $djmapa = doujutsumods($userrow);
    if ($neblina && intval($djmapa["ver_mapa"]) == 1) { $neblina = false; }
    $mapavisivel = neblina_mapadovisitante($userrow);
    $tituloneblina = t("Região inexplorada. Caminhe até ela para revelá-la no mapa.");

    // Fundo: images/mapa_grande.png (mapa de 400 casas: o original de 300 no meio + anel novo; tools/gerar_mapa_grande.py),
    // esticado para cobrir a tabela inteira, assim o desenho sempre acompanha as coordenadas do gamesize.
    // Com neblina, o fundo vem de mapaneblina.php: a imagem já chega com os quadrantes inexplorados cobertos (o mapa inteiro
    // não é mais enviado e não "pisca" antes da neblina carregar). A chave ?v= muda quando o jogador descobre um quadrante.
    $fundomapa = $neblina ? "mapaneblina.php?v=".md5((string)$mapavisivel).($verneblina == 1 ? "&amp;neblina=1" : "") : "images/mapa_grande.png";
    $pagina = "<center><div style=\"position:relative; display:inline-block\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"background:#2a1a0c url($fundomapa) no-repeat; background-size:100% 100%\">";
    $sobrai = $sobra; $addfimi = 0;
    for ($i = 0; $i <= 19; $i++) {
        $pagina .= "<tr>";
        $addi = ($sobrai > 0) ? 1 : 0; if ($sobrai > 0) { $sobrai--; }
        $aux0 = $tamanhomapa - ($lado * $i) - $addfimi;
        $topo = ($i != 0) ? $aux0 - 1 : $aux0;   // Latitude de cima do quadrante.
        $base = $aux0 - $lado - $addi;           // Latitude de baixo.
        $sobraj = $sobra; $addfimj = 0;          // (Corrigido: antes não reiniciava a cada linha e deslocava as colunas.)
        for ($j = 0; $j <= 19; $j++) {
            $addj = ($sobraj > 0) ? 1 : 0; if ($sobraj > 0) { $sobraj--; }
            $aux1 = ($tamanhomapa * -1) + ($lado * $j) + $addfimj;
            $esq = ($j != 0) ? $aux1 + 1 : $aux1;  // Longitude da esquerda.
            $dir = $aux1 + $lado + $addj;          // Longitude da direita.
            $coordenadas = vsprintf($frases["ate"], array($topo, $esq, $base, $dir));

            // O quadrante toca a região procurada? Então ele vira o alvo azul.
            if ($regiao !== null && $regiao[0] >= $base && $regiao[1] <= $dir && $regiao[2] <= $topo && $regiao[3] >= $esq) {
                $alvolat = $topo; $alvolong = $esq;
            }
            // Inimigo: se o quadrante toca o miolo do quadrado, ele deixa de ser alvo (sobra só o anel da borda).
            if ($monstro != "" && $regiao !== null && ($regiao[0] - $encolher) >= $base && ($regiao[1] + $encolher) <= $dir && ($regiao[2] + $encolher) <= $topo && ($regiao[3] - $encolher) >= $esq) {
                $alvolat = 5000; $alvolong = 5000;
            }

            // Inimigo só de uma região: o quadrante precisa tocar o retângulo da região e a faixa de distância do level.
            if ($regiaomonstro !== null && $regiaomonstro[4] == 1) {
                $alvolat = 5000; $alvolong = 5000;
                if ($regiaomonstro[0] <= $topo && $regiaomonstro[1] >= $base && $regiaomonstro[2] <= $dir && $regiaomonstro[3] >= $esq) {
                    $minlat = ($base <= 0 && $topo >= 0) ? 0 : min(abs($base), abs($topo));
                    $minlon = ($esq <= 0 && $dir >= 0) ? 0 : min(abs($esq), abs($dir));
                    $dmin = max($minlat, $minlon);
                    $dmax = max(abs($base), abs($topo), abs($esq), abs($dir));
                    if ($dmin <= $faixamax && $dmax >= $faixamin) { $alvolat = $topo; $alvolong = $esq; }
                }
            }

            $azul = ($alvolat !== null && $alvolat <= $topo && $alvolat >= $base && $alvolong >= $esq && $alvolong <= $dir);
            $voceaqui = ($userlat <= $topo && $userlat >= $base && $userlong >= $esq && $userlong <= $dir);

            if ($azul && $voceaqui) {
                $mostrar = "certoazulmeio19$var.gif"; $title = $coordenadas." ".$frases["azulaqui"];
            } elseif ($azul) {
                $mostrar = "certoazul19$var.gif"; $title = $coordenadas." ".$frases["azul"];
            } elseif ($voceaqui) {
                $mostrar = "certo19$var.gif"; $title = $coordenadas." ".$frases["aqui"];
            } else {
                $mostrar = "gif19$var.gif"; $title = $coordenadas;
            }

            // Busca de item: as cores ficam invertidas (o item é dropado FORA do quadrado azul, por inimigos mais fortes).
            if ($var == "_2") {
                $titulonovo = "";
                if ($mostrar == "gif19$var.gif") { $titulonovo = " ".$frases["dropa"]; }
                elseif ($mostrar == "certo19$var.gif") { $titulonovo = " ".$frases["dropaaqui"]; }
                elseif ($mostrar == "certoazulmeio19$var.gif") { $titulonovo = " ".$frases["aqui"]; }
                $title = $coordenadas.$titulonovo;
            }
            if ($neblina && !neblina_temquadrante($mapavisivel, $i*20 + $j)) {
                // Quadrante inexplorado: a neblina já vem desenhada no fundo (mapaneblina.php); aqui só a borda e o aviso.
                $pagina .= "<td style=\"border:1px #2a1a0c solid\"><img src=\"images/gif19.gif\" title=\"".$coordenadas." ".$tituloneblina."\" border=\"0\"></td>";
            } else {
                if (!function_exists('mx_npcsnomapa')) { include_once('funcoesmissoesextras.php'); } mx_npcsnomapa($topo, $base, $esq, $dir, $mostrar, $title); // NPCs do mapa (tabela npcs).
                $pagina .= "<td style=\"border:1px #000000 solid\"><img src=\"images/$mostrar\" title=\"$title\" border=\"0\"></td>";
            }

            $addfimj += $addj;
        }
        $pagina .= "</tr>";
        $addfimi += $addi;
    }
    $pagina .= "</table>";
    include_once('cidadesconteudo.php');
    // Vilas por cima da tabela (losango vermelho na posição exata), só as que ficam em quadrantes visíveis. Vilas secretas não aparecem.
    $townquery = doquery("SELECT id,name,latitude,longitude FROM {{table}} ORDER BY id", "towns");
    while ($townrow = mysqli_fetch_array($townquery)) {
        if (!$equipe && cidadesecreta($townrow["id"]) && !in_array(intval($townrow["id"]), array_map("intval", explode(",", $userrow["towns"])), true)) { continue; } // vila secreta: só depois de encontrada.
        if ($neblina && !neblina_temquadrante($mapavisivel, neblina_quadrante($townrow["latitude"], $townrow["longitude"]))) { continue; }
        // Posição em % do mapa (a célula pode ter padding do tema, então não dá para contar em pixels).
        $x = round(($townrow["longitude"] + $tamanhomapa) / (2*$tamanhomapa) * 100, 2);
        $y = round(($tamanhomapa - $townrow["latitude"]) / (2*$tamanhomapa) * 100, 2);
        $nomevila = htmlspecialchars(t($townrow["name"]), ENT_QUOTES, 'UTF-8')." [".intval($townrow["latitude"]).",".intval($townrow["longitude"])."]";
        $pagina .= "<img src=\"images/vila_marcador.png\" title=\"$nomevila\" alt=\"\" style=\"position:absolute; left:{$x}%; top:{$y}%; margin:-5px 0 0 -5px\" />";
    }
    // Chefes semanais (funcoeschefes.php): marcador roxo na casa de cada um, só em quadrantes visíveis.
    include_once('funcoeschefes.php');
    foreach (chefes_lista() as $chefe) {
        if ($neblina && !neblina_temquadrante($mapavisivel, neblina_quadrante($chefe["latitude"], $chefe["longitude"]))) { continue; }
        $x = round(($chefe["longitude"] + $tamanhomapa) / (2*$tamanhomapa) * 100, 2);
        $y = round(($tamanhomapa - $chefe["latitude"]) / (2*$tamanhomapa) * 100, 2);
        $nomechefe = htmlspecialchars(tf("Chefe da Semana: %s (nível %s)", t($chefe["name"]), intval($chefe["level"])), ENT_QUOTES, 'UTF-8')." [".intval($chefe["latitude"]).",".intval($chefe["longitude"])."]";
        $pagina .= "<img class=\"chefemapa\" src=\"images/chefe_marcador.png\" title=\"$nomechefe\" alt=\"\" style=\"position:absolute; left:{$x}%; top:{$y}%; margin:-5px 0 0 -5px\" />";
    }
    $pagina .= "</div>";
    $pagina .= "<br /><div style=\"display:inline-block; margin-top:4px; padding:2px 8px; background:#f3e6c4; border:1px solid #452202; color:#2a1a0c\"><b>".tf("Mapa descoberto: %s%%", neblina_percentual($mapavisivel))."</b>";
    if ($equipe && intval($controlrow["neblinaativa"]) == 1) {
        // Link que alterna a visão do GM/admin, mantendo os outros parâmetros da busca.
        $parametros = $_GET; unset($parametros["neblina"]);
        foreach ($parametros as $chave => $valor) { $parametros[$chave] = textocru($valor); }
        if ($verneblina != 1) { $parametros["neblina"] = 1; }
        $pagina .= " &middot; <a href=\"index.php?".htmlspecialchars(http_build_query($parametros), ENT_QUOTES, 'UTF-8')."\">".($verneblina == 1 ? t("Ver o mapa todo (equipe)") : t("Ver com a neblina do jogador"))."</a>";
    }
    $pagina .= "</div>";
    if ($regiaomonstro !== null) {
        $textoregiao = tf("Região: [%s,%s] até [%s,%s]", $regiaomonstro[1], $regiaomonstro[2], $regiaomonstro[0], $regiaomonstro[3]);
        $textoregiao .= ($regiaomonstro[4] == 1) ? " ".t("(aparece apenas nesta região)") : " ".t("(aparece com mais frequência nesta região)");
        $pagina .= "<br /><b>".$textoregiao."</b>";
    }
    if ($textoterrenos != "") { $pagina .= "<br /><b>".tf("Terreno: %s", $textoterrenos)."</b>"; }
    $pagina .= "</center>";

    $page = cabecalhopopup() . gettemplate("minimal");
    $array = array("content"=>$pagina, "title"=>t("Mapa Global - 20x20"));
    echo parsetemplate($page, $array);
    die();

}

function gravarbabble($texto) { // Grava uma fala no Chat Global (com proteção contra flood). Usada pelo popup e pela caixa da cidade.

    global $userrow;

    $intervalo = 3;   // Segundos mínimos entre duas falas do mesmo jogador.
    $tamanhomax = 120; // Mesmo limite do campo do formulário.

    if (segundosmudo($userrow) > 0) { return false; } // silenciado por um GM.
    $texto = trim(textocru($texto));
    if ($texto == "") { return false; }
    // Corta no tamanho máximo e garante que o texto já escapado caiba na coluna (varchar 255).
    $texto = mb_substr($texto, 0, $tamanhomax, 'UTF-8');
    do {
        $safecontent = makesafe(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'));
        $texto = mb_substr($texto, 0, mb_strlen($texto, 'UTF-8') - 1, 'UTF-8');
    } while (mb_strlen($safecontent, 'UTF-8') > 255);
    if (trim($safecontent) == "") { return false; }

    // Anti-flood: ignora a fala se for igual à última do jogador ou se veio poucos segundos depois dela.
    $ultimaquery = doquery("SELECT babble, TIME_TO_SEC(TIMEDIFF(CURTIME(), posttime)) AS segundos FROM {{table}} WHERE author='".addslashes($userrow["charname"])."' ORDER BY id DESC LIMIT 1", "babble");
    $ultima = mysqli_fetch_array($ultimaquery);
    if ($ultima) {
        if ($ultima["babble"] == $safecontent) { return false; }
        if ($ultima["segundos"] !== null && $ultima["segundos"] >= 0 && $ultima["segundos"] < $intervalo) { return false; }
    }

    doquery("INSERT INTO {{table}} SET posttime=NOW(),author='".addslashes($userrow["charname"])."',babble='".addslashes($safecontent)."'", "babble");
    return true;

}

function linhasbabble($quantidade, $idlinha) { // Monta as últimas $quantidade falas do Chat Global (a mais nova embaixo), com a cor do cargo de cada autor.

    $babblequery = doquery("SELECT * FROM {{table}} ORDER BY id DESC LIMIT ".intval($quantidade), "babble");
    $falas = array();
    $autores = array();
    while ($babblerow = mysqli_fetch_array($babblequery)) { $falas[] = $babblerow; $autores[] = $babblerow["author"]; }
    $falas = array_reverse($falas);
    $cargos = cargosdosjogadores($autores);
    $coresdocargo = array("adm" => "darkgreen", "tutor" => "darkorange", "gm" => "darkblue", "" => "black");

    $conteudo = "";
    foreach ($falas as $n => $babblerow) {
        $chave = mb_strtolower($babblerow["author"], 'UTF-8');
        $cargo = isset($cargos[$chave]) ? $cargos[$chave] : "";
        $link = "<font color=".$coresdocargo[$cargo].">"; $link2 = "</font>";
        $linkn = ($cargo != "") ? " id=\"".$cargo."\" " : "";
        // Fundo alternado, contado a partir da fala mais nova (que fica sempre cinza).
        $fundo = ((count($falas) - 1 - $n) % 2 == 0) ? "#eeeeee" : "#ffffff";
        $conteudo .= "<div style=\"width:98%; background-color:".$fundo.";\"".$idlinha.">[".($cargo == "gm" ? selogm() : "").(isset($GLOBALS["caveirasdosnomes"][$chave]) ? $GLOBALS["caveirasdosnomes"][$chave] : "")."<a href=\"javascript: mostrarchar('".$babblerow["author"]."');\" $linkn title=\"".t("Visualizar Perfil")."\">$link<b>".$babblerow["author"]."</b>$link2</a>] $link".$babblerow["babble"]."$link2</div>\n";
    }
    return $conteudo;

}

function babblebox() { // Popup do Chat Global (ícone "Abrir Chat Global"): mostra as 120 últimas falas.

    // Largura do campo de texto (?tamanho=): o popup usa 55, que vira 350px.
    $tamanho = pegarint($_GET, "tamanho", 0);
    if ($tamanho >= 300 || $tamanho == 55) { $tamanho = 350; }
    if ($tamanho <= 0) { $tamanho = 400; } // Sem o parâmetro (ou inválido): 400px.
    $colocar = ($tamanho != 15) ? "&tamanho=55" : "";

    if (isset($_POST["babble"])) {
        gravarbabble(pegartexto($_POST, "babble"));
        header("Location: index.php?do=babblebox".$colocar);
        die();
    }

    $babblebox = array("content" => linhasbabble(120, ""));
    $babblebox["content"] .= "<center><form action=\"index.php?do=babblebox".htmlspecialchars($colocar)."\" method=\"post\"><input type=\"text\" name=\"babble\" style=\"width:".$tamanho."px\" maxlength=\"120\" /><br /><div class=\"buttons\" style=\"padding-left: 9px;\"><center><button type=\"submit\" class=\"positive btnjogo bj-confirmar\" name=\"submit\"><img src=\"images/botao_falar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Falar")."</button><button type=\"button\" class=\"standard btnjogo bj-neutro\" name=\"atualizar\" onclick=\"location.href='index.php?do=babblebox".htmlspecialchars($colocar)."'\"><img src=\"images/botao_atualizar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Atualizar")."</button>
</center></div></form></center>";

    $page = cabecalhopopup() . gettemplate("babblebox");
    echo parsetemplate($page, $babblebox);
    die();

}

function babbleboxpage($tamanho, $quantidade) { // Caixa do Chat Global que aparece dentro da página da cidade (o envio vai para ?do=babbleboxpage).

    $tamanho = intval($tamanho); if ($tamanho <= 0) { $tamanho = 400; }
    $quantidade = intval($quantidade); if ($quantidade <= 0) { $quantidade = 10; }

    $conteudo = linhasbabble($quantidade, " id=\"divchat\"");
    // Campo e botão OK lado a lado (mesmo formulário do Chat do Mapa); Enter também envia.
    $conteudo .= "<form action=\"index.php?do=babbleboxpage\" method=\"post\" style=\"display:flex;gap:4px;width:".$tamanho."px;max-width:95%;margin:4px auto 0;box-sizing:border-box\"><input type=\"text\" name=\"babble\" maxlength=\"120\" style=\"flex:1;min-width:0;box-sizing:border-box;padding:3px 5px;border:1px solid #452202;background:#fff9e3\" /><button type=\"submit\" name=\"submit\" value=\"OK\" class=\"btnjogo bj-confirmar\" style=\"flex:none;margin:0\"><img src=\"images/botao_falar.png\" width=\"16\" height=\"16\" alt=\"\" /> OK</button></form>";

    return $conteudo;

}

?>
