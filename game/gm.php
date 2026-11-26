<?php // gm.php :: Painel do GameMaster (conta com users.acesso = 3). Mesmo layout do painel do administrador, com menu limitado.
// O GM ajuda os jogadores e modera, mas NÃO mexe no conteúdo nem na economia do jogo:
//   PODE: ver jogadores online; ver o perfil de um jogador com o histórico e o registro de auditoria dele; liberar por 1 hora
//         a recuperação de senha sem as perguntas secretas (o GM nunca vê nem define senha/hash/respostas: o próprio jogador
//         cria a senha nova em users.php?do=recuperar provando conta, personagem, ano, e-mail e level); destravar um jogador (encerra
//         duelo ativo como desistência, cancela troca e luta, manda para a vila natal); silenciar/liberar um jogador por X
//         minutos (chat global, chat do mapa, fórum e mensagens privadas); apagar mensagens do chat global, do chat do mapa
//         e tópicos/respostas do fórum; mandar uma mensagem privada como GM; mandar a janela de mensagem para todos os
//         jogadores; ver a lista de caveiras (PvP); teleportar um jogador para uma coordenada e ver o Mapa do jogo (essas duas
//         só se o administrador permitir em Configurações gerais: control.gm_teleporte / control.gm_mapa); ver as próprias ações.
//   NÃO PODE: dar/tirar itens, Ryou, experiência ou níveis; editar fichas, inimigos, itens, vilas, missões ou configurações;
//         banir/apagar contas; agir sobre administradores ou outros GMs; abrir o admin.php.
// Toda ação vai para o registro de auditoria (tabela adminlog), visto pelo administrador em admin.php?do=auditoria.
//
// Rotas: (vazio)/online  jogadores online | jogador / jogador:ID  procurar e ver um jogador (ações por POST)
//        chat  moderação dos chats e do fórum | mensagem  mensagem privada como GM | registro  minhas ações
//        mensagemglobal  janela de mensagem para todos | caveiras  caveiras (somente leitura) | mapa  Mapa do jogo

define('USERS_SOMENTE_FUNCOES', true);
include_once('users.php'); // lib.php, cookies.php, $userrow, $controlrow e dueloforcarderrota()/limparduelo().
if ($userrow == false) { header("Location: login.php?do=login"); die(); }
if (!(intval($userrow["authlevel"]) == 1 || ehgm($userrow))) { header("Location: index.php"); die(); }
include_once('painelseguranca.php');
painelexigir("gm");

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && !csrf_confere()) {
    admindisplay(gmmsg(t("Token de segurança inválido ou expirado. Volte, recarregue a página e tente novamente."), "erro"), t("Erro"));
}

$do = explode(":", isset($_GET["do"]) ? (string)$_GET["do"] : "");
$id = isset($do[1]) ? intval($do[1]) : 0;
if ($do[0] == "jogador") { gmjogador($id); }
elseif ($do[0] == "chat") { gmchat(); }
elseif ($do[0] == "mensagem") { gmmensagem(); }
elseif ($do[0] == "registro") { gmregistro(); }
elseif ($do[0] == "mensagemglobal") { gmmensagemglobal(); }
elseif ($do[0] == "caveiras") { gmcaveiras(); }
elseif ($do[0] == "mapa") { gmmapa(); }
else { gmonline(); }


function gmmsg($texto, $tipo = "ok") { return "<div class=\"$tipo\">$texto</div>"; } // Caixa de mensagem.

function gmpodeagir($alvo) { // O GM pode agir sobre essa conta? Nunca sobre administradores ou outros GMs (o admin pode sobre jogadores e GMs).

    global $userrow;
    if (!$alvo || $alvo["id"] == $userrow["id"]) { return false; }
    if (intval($alvo["authlevel"]) == 1) { return false; }
    if (ehgm($alvo) && intval($userrow["authlevel"]) != 1) { return false; }
    return true;

}

function gmnome($row) { // Nome com o selo de GM e o link para a ficha.

    return (ehgm($row) ? selogm() : "")."<a href=\"gm.php?do=jogador:".intval($row["id"])."\">".$row["charname"]."</a>";

}

function gmonline() { // Jogadores online (últimos 10 minutos).

    $acoes = array("In Town"=>t("na vila"), "Exploring"=>t("explorando"), "Fighting"=>t("em batalha"));
    $page = "<h1>".t("Jogadores online")."</h1><p>".t("Jogadores que fizeram alguma coisa nos últimos 10 minutos.")."</p>";
    $page .= "<table class=\"lista\"><tr><th>".t("Personagem")."</th><th>".t("Nível")."</th><th>".t("Local")."</th><th>".t("Situação")."</th><th>".t("Último acesso")."</th></tr>";
    $query = doquery("SELECT id,charname,level,latitude,longitude,currentaction,batalha_timer2,trocajogador1,authlevel,acesso,mudoate,onlinetime FROM {{table}} WHERE UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."' ORDER BY charname LIMIT 300", "users");
    $n = 0;
    while ($row = mysqli_fetch_array($query)) {
        $n++;
        $sit = isset($acoes[$row["currentaction"]]) ? $acoes[$row["currentaction"]] : "";
        if ($row["batalha_timer2"] == 5) { $sit .= ", ".t("em duelo"); }
        if ($row["trocajogador1"] != "None") { $sit .= ", ".t("trocando"); }
        if (segundosmudo($row) > 0) { $sit .= ", <span class=\"highlight\">".t("silenciado")."</span>"; }
        $page .= "<tr><td>".gmnome($row)."</td><td>".intval($row["level"])."</td><td>[".intval($row["latitude"]).",".intval($row["longitude"])."]</td><td>$sit</td><td>".padraodate($row["onlinetime"])."</td></tr>";
    }
    if ($n == 0) { $page .= "<tr><td colspan=\"5\">".t("Ninguém online.")."</td></tr>"; }
    $page .= "</table>";
    admindisplay($page, t("Jogadores online"));

}

function gmjogador($id) { // Procura e ficha de um jogador, com as ações permitidas ao GM.

    global $userrow, $link;
    $t = "t"; $tf = "tf";
    $mensagem = "";
    $busca = pegartexto($_GET, "busca");
    if ($id == 0 && $busca != "") {
        $r = mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE charname='$busca' OR username='$busca' LIMIT 1", "users"));
        if ($r) { header("Location: gm.php?do=jogador:".intval($r["id"])); die(); }
        $mensagem = gmmsg(t("Jogador não encontrado."), "erro");
    }
    if ($id == 0) {
        $page = "<h1>".t("Procurar jogador")."</h1>$mensagem<form action=\"gm.php\" method=\"get\"><input type=\"hidden\" name=\"do\" value=\"jogador\" />".t("Personagem ou conta:")." <input type=\"text\" name=\"busca\" size=\"25\" value=\"$busca\" /> <input type=\"submit\" value=\"".t("Procurar")."\" /></form>";
        admindisplay($page, t("Procurar jogador"));
    }

    $alvo = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "users"));
    if (!$alvo) { admindisplay(gmmsg(t("Jogador não encontrado."), "erro"), t("Procurar jogador")); }

    if (isset($_POST["acao"])) {
        $acao = pegartexto($_POST, "acao");
        if (!gmpodeagir($alvo)) { $mensagem = gmmsg(t("Você não pode agir sobre essa conta (administradores e outros GMs)."), "erro"); registrarlog("gm_negado", $id, $acao); }
        elseif ($acao == "liberar") {
            // O GM NUNCA vê nem cria uma senha: ele só libera, por 1 hora, a recuperação sem as respostas secretas
            // (o jogador ainda precisa acertar conta, personagem, ano de criação, e-mail e level em users.php?do=recuperar).
            doquery("UPDATE {{table}} SET resetliberado='".(time() + 3600)."' WHERE id='$id' LIMIT 1", "users");
            registrarlog("gm_liberar_recuperacao", $id, $alvo["charname"]);
            $mensagem = gmmsg(tf("Recuperação liberada por 1 hora para %s. Diga ao jogador para abrir <b>Senha Perdida</b> (users.php?do=recuperar) e informar conta, personagem, ano de criação da conta, e-mail cadastrado e level atual; as perguntas secretas não serão pedidas.", $alvo["charname"]), "aviso");
        } elseif ($acao == "destravar") {
            $haviaduelo = dueloforcarderrota($id);
            include_once('cidadesconteudo.php');
            $vila = vilanataldoalinhamento($alvo["alinhamento"]);
            // Troca: apaga a minha parte e as ofertas gravadas no parceiro que citam este jogador.
            $px = $GLOBALS["dbsettings"]["prefix"];
            $nome = addslashes($alvo["charname"]);
            doquery("UPDATE {{table}} SET trocajogador1='None', trocajogador2='None', trocaswitch='0' WHERE trocajogador1 LIKE '".$nome.";%' OR trocajogador2 LIKE '".$nome.";%'", "users");
            doquery("UPDATE {{table}} SET currentaction='In Town', currentfight='0', currentmonster='0', currentmonsterhp='0', currentmonstersleep='0', currentmonsterimmune='0', currentuberdamage='0', currentuberdefense='0', batalha_nome='None', batalha_id='0', batalha_hp='0', batalha_timer='0', batalha_timer2='0', batalha_acao='None', trocajogador1='None', trocajogador2='None', trocaswitch='0', latitude='".intval($vila["latitude"])."', longitude='".intval($vila["longitude"])."' WHERE id='$id' LIMIT 1", "users");
            registrarlog("gm_destravar", $id, $alvo["charname"].($haviaduelo ? " (duelo encerrado)" : ""));
            $mensagem = gmmsg(tf("%s foi destravado e enviado para %s.", $alvo["charname"], t($vila["name"])).($haviaduelo ? " ".t("O duelo ativo dele foi encerrado como desistência.") : ""));
        } elseif ($acao == "teleportar") {
            global $controlrow;
            $lim = intval($controlrow["gamesize"]);
            $lat = pegarint($_POST, "lat"); $lon = pegarint($_POST, "lon");
            if (intval($userrow["authlevel"]) != 1 && intval(isset($controlrow["gm_teleporte"]) ? $controlrow["gm_teleporte"] : 1) != 1) { $mensagem = gmmsg(t("O administrador não permite que o GM teleporte jogadores."), "erro"); }
            elseif (abs($lat) > $lim || abs($lon) > $lim) { $mensagem = gmmsg(tf("Coordenada fora do mapa (de -%s a %s).", $lim, $lim), "erro"); }
            else {
                // Igual ao destravar: encerra duelo/troca/luta antes de mover (senão o jogador ficaria preso numa luta longe dali).
                $haviaduelo = dueloforcarderrota($id);
                $nome = addslashes($alvo["charname"]);
                doquery("UPDATE {{table}} SET trocajogador1='None', trocajogador2='None', trocaswitch='0' WHERE trocajogador1 LIKE '".$nome.";%' OR trocajogador2 LIKE '".$nome.";%'", "users");
                $vila = mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE latitude='$lat' AND longitude='$lon' LIMIT 1", "towns"));
                $acaonova = $vila ? "In Town" : "Exploring";
                doquery("UPDATE {{table}} SET currentaction='$acaonova', currentfight='0', currentmonster='0', currentmonsterhp='0', currentmonstersleep='0', currentmonsterimmune='0', currentuberdamage='0', currentuberdefense='0', batalha_nome='None', batalha_id='0', batalha_hp='0', batalha_timer='0', batalha_timer2='0', batalha_acao='None', trocajogador1='None', trocajogador2='None', trocaswitch='0', latitude='$lat', longitude='$lon' WHERE id='$id' LIMIT 1", "users");
                registrarlog("gm_teleportar", $id, $alvo["charname"]." [".$alvo["latitude"].",".$alvo["longitude"]."] -> [$lat,$lon]".($haviaduelo ? " (duelo encerrado)" : ""));
                avisarnohistorico($id, i18n("A equipe do jogo levou você para a coordenada %s.", "[$lat,$lon]"));
                $mensagem = gmmsg(tf("%s foi teleportado para [%s,%s].", $alvo["charname"], $lat, $lon).($haviaduelo ? " ".t("O duelo ativo dele foi encerrado como desistência.") : ""));
            }
        } elseif ($acao == "mutar") {
            $minutos = pegarint($_POST, "minutos");
            if ($minutos < 1 || $minutos > 10080) { $mensagem = gmmsg(t("Escolha de 1 a 10080 minutos (7 dias)."), "erro"); }
            else {
                doquery("UPDATE {{table}} SET mudoate='".(time() + 60 * $minutos)."' WHERE id='$id' LIMIT 1", "users");
                $motivo = mb_substr(painelvalorcru(pegartexto($_POST, "motivo")), 0, 200, 'UTF-8');
                registrarlog("gm_silenciar", $id, $alvo["charname"]." ".$minutos."min ".$motivo);
                avisarnohistorico($id, i18n("Você foi silenciado pela equipe do jogo por %s minuto(s).", $minutos));
                $mensagem = gmmsg(tf("%s está silenciado por %s minuto(s).", $alvo["charname"], $minutos));
            }
        } elseif ($acao == "desmutar") {
            doquery("UPDATE {{table}} SET mudoate='0' WHERE id='$id' LIMIT 1", "users");
            registrarlog("gm_liberar_fala", $id, $alvo["charname"]);
            $mensagem = gmmsg(tf("%s pode falar de novo.", $alvo["charname"]));
        }
        $alvo = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "users"));
    }

    // Ficha (somente leitura).
    $acoes = array("In Town"=>t("na vila"), "Exploring"=>t("explorando"), "Fighting"=>t("em batalha"));
    $situacao = isset($acoes[$alvo["currentaction"]]) ? $acoes[$alvo["currentaction"]] : $alvo["currentaction"];
    if ($alvo["batalha_timer2"] == 5) { $situacao .= ", ".t("em duelo"); }
    if ($alvo["trocajogador1"] != "None" || $alvo["trocajogador2"] != "None") { $situacao .= ", ".t("trocando"); }
    if ($alvo["authlevel"] == 2) { $situacao .= ", <span class=\"highlight\">".t("Banido")."</span>"; }
    $mudo = segundosmudo($alvo) > 0 ? "<span class=\"highlight\">".tf("silenciado por mais %s minuto(s)", ceil(segundosmudo($alvo) / 60))."</span>" : t("não");
    $cargo = (intval($alvo["authlevel"]) == 1) ? t("Administrador") : (ehgm($alvo) ? selogm().t("GameMaster") : t("Jogador"));
    $online = (strtotime($alvo["onlinetime"]) >= time() - 600) ? "<span style=\"color:green\">".t("online")."</span>" : t("offline");

    // Histórico do jogador (últimos avisos) e registro de auditoria sobre ele.
    $hist = "";
    if ($alvo["historico"] != "None" && $alvo["historico"] != "") {
        foreach (array_slice(array_reverse(explode(";;", $alvo["historico"])), 0, 15) as $h) { $hist .= "<li>".textoi18n($h)."</li>"; }
    }
    $hist = ($hist == "") ? "<i>".t("Vazio.")."</i>" : "<ul>$hist</ul>";
    $logs = "";
    $q = doquery("SELECT * FROM {{table}} WHERE alvo_id='$id' ORDER BY id DESC LIMIT 20", "adminlog");
    while ($r = mysqli_fetch_array($q)) { $logs .= "<tr><td>".padraodate($r["quando"])."</td><td>".htmlspecialchars($r["autor"], ENT_QUOTES, 'UTF-8')."</td><td><b>".htmlspecialchars($r["acao"], ENT_QUOTES, 'UTF-8')."</b></td><td class=\"small\">".htmlspecialchars($r["detalhes"], ENT_QUOTES, 'UTF-8')."</td></tr>"; }
    $logs = ($logs == "") ? "<i>".t("Nenhuma ação da equipe sobre este jogador.")."</i>" : "<table class=\"lista\">$logs</table>";
    $lutas = "";
    $q = doquery("SELECT p.*, a.charname AS an, d.charname AS dn FROM {{table}} p LEFT JOIN ".$GLOBALS["dbsettings"]["prefix"]."_users a ON a.id=p.atacante LEFT JOIN ".$GLOBALS["dbsettings"]["prefix"]."_users d ON d.id=p.defensor WHERE p.atacante='$id' OR p.defensor='$id' ORDER BY p.id DESC LIMIT 10", "pvp");
    while ($r = mysqli_fetch_array($q)) { $lutas .= "<tr><td>".date("d-m-Y H:i", intval($r["quando"]))."</td><td>".tf("%s atacou %s", $r["an"], $r["dn"])."</td><td>".tf("Vencedor: %s", $r["vencedor"] == $r["atacante"] ? $r["an"] : $r["dn"])."</td><td>".intval($r["ryou"])." Ryou ".htmlspecialchars(str_replace("|", ", ", $r["itens"]), ENT_QUOTES, 'UTF-8')."</td></tr>"; }
    $lutas = ($lutas == "") ? "<i>".t("Nenhuma.")."</i>" : "<table class=\"lista\">$lutas</table>";

    $csrf = csrf_campo();
    global $controlrow;
    $formteleporte = (intval($userrow["authlevel"]) == 1 || intval(isset($controlrow["gm_teleporte"]) ? $controlrow["gm_teleporte"] : 1) == 1)
        ? "<form action=\"gm.php?do=jogador:$id\" method=\"post\" style=\"margin:0 0 8px 0\">$csrf".t("Teleportar para:")." ".t("latitude")." <input type=\"number\" name=\"lat\" value=\"".intval($alvo["latitude"])."\" style=\"width:70px\" /> ".t("longitude")." <input type=\"number\" name=\"lon\" value=\"".intval($alvo["longitude"])."\" style=\"width:70px\" /> <button type=\"submit\" name=\"acao\" value=\"teleportar\" onclick=\"return confirm('".t("Teleportar este jogador para essa coordenada? Luta, duelo e troca em andamento são encerrados.")."');\">".t("Teleportar")."</button> <span class=\"small\">".t("Positivo = norte/leste; uma vila na coordenada deixa o jogador dentro dela.")."</span></form>"
        : "";
    $botoes = gmpodeagir($alvo) ? <<<FIMBOTOES
<form action="gm.php?do=jogador:$id" method="post" style="margin:0 0 8px 0">$csrf
<button type="submit" name="acao" value="destravar" onclick="return confirm('{$t("Destravar este jogador e mandá-lo para a vila natal?")}');">{$t("Destravar / mandar para a vila")}</button>
<button type="submit" name="acao" value="liberar" onclick="return confirm('{$t("Liberar a recuperação de senha deste jogador por 1 hora (sem as perguntas secretas)?")}');">{$t("Liberar recuperação de senha (1 hora)")}</button>
</form>
$formteleporte<form action="gm.php?do=jogador:$id" method="post" style="margin:0">$csrf
{$t("Silenciar por")} <input type="number" name="minutos" value="30" min="1" max="10080" style="width:70px" /> {$t("minutos")} &nbsp; {$t("Motivo:")} <input type="text" name="motivo" size="30" maxlength="200" />
<button type="submit" name="acao" value="mutar">{$t("Silenciar")}</button> <button type="submit" name="acao" value="desmutar">{$t("Liberar a fala")}</button>
</form>
FIMBOTOES
    : gmmsg(t("Você não pode agir sobre essa conta (administradores e outros GMs)."), "aviso");

    $nomeperfil = gmnome($alvo);
    $page = <<<FIMDAPAGINA
<h1>{$tf("Jogador: %s", $alvo["charname"])}</h1>
$mensagem
<h2>{$t("Ações do GM")}</h2>
$botoes
<h2>{$t("Perfil")}</h2>
<table width="100%">
<tr><td width="200">{$t("Personagem / conta:")}</td><td>$nomeperfil / {$alvo["username"]} (ID {$alvo["id"]}) &middot; $online</td></tr>
<tr><td>{$t("Cargo:")}</td><td>$cargo</td></tr>
<tr><td>{$t("Nível / graduação:")}</td><td>{$alvo["level"]} / {$t($alvo["graduacao"])}</td></tr>
<tr><td>{$t("Local / situação:")}</td><td>[{$alvo["latitude"]},{$alvo["longitude"]}] &middot; $situacao</td></tr>
<tr><td>{$t("HP / Chakra:")}</td><td>{$alvo["currenthp"]}/{$alvo["maxhp"]} &middot; {$alvo["currentmp"]}/{$alvo["maxmp"]}</td></tr>
<tr><td>{$t("Silenciado:")}</td><td>$mudo</td></tr>
<tr><td>{$t("Cadastro / último acesso:")}</td><td>{$alvo["regdate"]} / {$alvo["onlinetime"]}</td></tr>
<tr><td>{$t("Duelos:")}</td><td>{$alvo["duelos_vencidos"]} {$t("vitórias")} / {$alvo["duelos_perdidos"]} {$t("derrotas")}</td></tr>
</table>
<h2>{$t("Histórico recente do jogador")}</h2>$hist
<h2>{$t("Lutas PvP recentes")}</h2>$lutas
<h2>{$t("Ações da equipe sobre este jogador")}</h2>$logs
FIMDAPAGINA;
    admindisplay($page, tf("Jogador %s", $alvo["charname"]));

}

function gmchat() { // Moderação: apagar falas do chat global, do chat do mapa e tópicos/respostas do fórum (link com token).

    $mensagem = "";
    if (isset($_GET["acao"])) {
        if (!csrf_confere()) { admindisplay(gmmsg(t("Token de segurança inválido. Volte e tente novamente."), "erro"), t("Erro")); }
        $acao = pegartexto($_GET, "acao");
        $alvo = pegarint($_GET, "alvo");
        $tabelas = array("babble" => "babble", "chatmap" => "chatmap", "forum" => "forum");
        if (isset($tabelas[$acao]) && $alvo > 0) {
            $row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$alvo' LIMIT 1", $tabelas[$acao]));
            if ($row) {
                $texto = ($acao == "babble") ? $row["author"].": ".$row["babble"] : (($acao == "chatmap") ? $row["name"].": ".$row["fala"] : $row["author"].": ".$row["title"]." ".mb_substr(strip_tags($row["content"]), 0, 120, 'UTF-8'));
                if ($acao == "forum") {
                    doquery("DELETE FROM {{table}} WHERE id='$alvo' OR parent='$alvo'", "forum");
                    if (intval($row["parent"]) > 0) { doquery("UPDATE {{table}} SET replies=IF(replies>0, replies-1, 0) WHERE id='".intval($row["parent"])."' LIMIT 1", "forum"); }
                } else {
                    doquery("DELETE FROM {{table}} WHERE id='$alvo' LIMIT 1", $tabelas[$acao]);
                }
                registrarlog("gm_apagar_".$acao, $alvo, html_entity_decode($texto, ENT_QUOTES, 'UTF-8'));
                $mensagem = gmmsg(t("Mensagem apagada."));
            }
        }
    }
    $apagar = function($tipo, $id) { return "<a href=\"gm.php?do=chat&amp;acao=$tipo&amp;alvo=".intval($id)."&amp;csrf=".csrf_token()."\" onclick=\"return confirm('".t("Apagar?")."');\">".t("Apagar")."</a>"; };
    $page = "<h1>".t("Chats e fórum")."</h1>$mensagem<p>".t("Para silenciar alguém, abra a ficha do jogador (clique no nome).")."</p>";
    $page .= "<h2>".t("Chat global (últimas 50 mensagens)")."</h2><table class=\"lista\"><tr><th width=\"60\">".t("Hora")."</th><th width=\"120\">".t("Autor")."</th><th>".t("Mensagem")."</th><th width=\"60\"></th></tr>";
    $q = doquery("SELECT * FROM {{table}} ORDER BY id DESC LIMIT 50", "babble");
    while ($r = mysqli_fetch_array($q)) { $page .= "<tr><td>".$r["posttime"]."</td><td><a href=\"gm.php?do=jogador&amp;busca=".urlencode($r["author"])."\">".$r["author"]."</a></td><td>".$r["babble"]."</td><td>".$apagar("babble", $r["id"])."</td></tr>"; }
    $page .= "</table><h2>".t("Chat do mapa (últimas 50 falas)")."</h2><table class=\"lista\"><tr><th width=\"90\">".t("Local")."</th><th width=\"120\">".t("Autor")."</th><th>".t("Fala")."</th><th width=\"60\"></th></tr>";
    $q = doquery("SELECT * FROM {{table}} ORDER BY id DESC LIMIT 50", "chatmap");
    while ($r = mysqli_fetch_array($q)) { $page .= "<tr><td>[".$r["latitude"].",".$r["longitude"]."]</td><td><a href=\"gm.php?do=jogador&amp;busca=".urlencode($r["name"])."\">".$r["name"]."</a></td><td>".$r["fala"]."</td><td>".$apagar("chatmap", $r["id"])."</td></tr>"; }
    $page .= "</table><h2>".t("Fórum (últimas 50 mensagens)")."</h2><table class=\"lista\"><tr><th width=\"110\">".t("Data")."</th><th width=\"120\">".t("Autor")."</th><th>".t("Título / texto")."</th><th width=\"60\"></th></tr>";
    $q = doquery("SELECT * FROM {{table}} ORDER BY id DESC LIMIT 50", "forum");
    while ($r = mysqli_fetch_array($q)) { $page .= "<tr><td>".padraodate($r["postdate"])."</td><td><a href=\"gm.php?do=jogador&amp;busca=".urlencode($r["author"])."\">".$r["author"]."</a></td><td>".(intval($r["parent"]) == 0 ? "<b>".$r["title"]."</b> " : "&#8627; ").mb_substr(strip_tags($r["content"]), 0, 150, 'UTF-8')."</td><td>".$apagar("forum", $r["id"])."</td></tr>"; }
    $page .= "</table>";
    admindisplay($page, t("Chats e fórum"));

}

function gmmensagem() { // Mensagem privada em nome da equipe (aparece com o selo GM na caixa do jogador).

    global $userrow;
    $t = "t";
    $mensagem = "";
    $para = pegartexto($_GET, "para");
    if (isset($_POST["enviar"])) {
        $para = pegartexto($_POST, "para");
        $texto = trim(stripslashes(pegartexto($_POST, "texto")));
        $texto = str_replace("@#$%!;;;", "", $texto);
        if ($texto == "" || mb_strlen($texto, 'UTF-8') > 1000) { $mensagem = gmmsg(t("Escreva uma mensagem de até 1000 caracteres."), "erro"); }
        else {
            doquery("START TRANSACTION", "users");
            $alvo = mysqli_fetch_array(doquery("SELECT id,charname,caixadepm FROM {{table}} WHERE charname='$para' LIMIT 1 FOR UPDATE", "users"));
            if (!$alvo) { doquery("ROLLBACK", "users"); $mensagem = gmmsg(t("Jogador não encontrado."), "erro"); }
            else {
                // Mesmo formato do mainmsg.php :: enviarpm() (caixa de 25 mensagens separadas por "@#$%!;;;").
                $caixa = ($alvo["caixadepm"] == "None") ? "" : (string)$alvo["caixadepm"];
                $msgs = explode("@#$%!;;;", $caixa);
                if (count($msgs) < 25) { $msgs = array_merge(array_fill(0, 25 - count($msgs), ""), $msgs); }
                $msgs = array_slice($msgs, -24);
                $msgs[] = "<!--pm:".intval($userrow["id"]).":".time()."-->[".selogm()."<font color=\"blue\"><b><a href=\"mainmsg.php?do2=enviarpm&amp;nomedochar=".urlencode($userrow["charname"])."\" id=\"gm\">GM ".htmlspecialchars($userrow["charname"], ENT_QUOTES, 'UTF-8')."</a></b></font>] <font color=\"blue\">".nl2br($texto)."</font>";
                doquery("UPDATE {{table}} SET caixadepm='".addslashes(implode("@#$%!;;;", $msgs))."', pmsnovas='1' WHERE id='".intval($alvo["id"])."' LIMIT 1", "users");
                doquery("COMMIT", "users");
                registrarlog("gm_mensagem", $alvo["id"], $alvo["charname"].": ".mb_substr(html_entity_decode($texto, ENT_QUOTES, 'UTF-8'), 0, 300, 'UTF-8'));
                $mensagem = gmmsg(tf("Mensagem enviada para %s.", $alvo["charname"]));
            }
        }
    }
    $csrf = csrf_campo();
    $page = <<<FIMDAPAGINA
<h1>{$t("Mensagem privada como GM")}</h1>
$mensagem
<p>{$t("A mensagem chega na caixa de mensagens privadas do jogador com o selo de GameMaster. Ela fica registrada na auditoria.")}</p>
<form action="gm.php?do=mensagem" method="post">$csrf
<table><tr><td>{$t("Para (personagem):")}</td><td><input type="text" name="para" size="25" maxlength="30" value="$para" /></td></tr>
<tr><td>{$t("Mensagem:")}</td><td><textarea name="texto" rows="5" cols="60" maxlength="1000"></textarea></td></tr>
<tr><td></td><td><input type="submit" name="enviar" value="{$t("Enviar mensagem")}" /></td></tr></table>
</form>
FIMDAPAGINA;
    admindisplay($page, t("Mensagem privada como GM"));

}

function gmregistro() { // Últimas ações do próprio GM (o administrador vê tudo em admin.php?do=auditoria).

    global $userrow;
    $page = "<h1>".t("Minhas ações")."</h1><p>".t("Tudo o que você faz no painel fica registrado e pode ser visto pelo administrador.")."</p><table class=\"lista\"><tr><th width=\"120\">".t("Quando")."</th><th>".t("Ação")."</th><th>".t("Alvo")."</th><th>".t("Detalhes")."</th></tr>";
    $q = doquery("SELECT * FROM {{table}} WHERE autor_id='".intval($userrow["id"])."' ORDER BY id DESC LIMIT 100", "adminlog");
    while ($r = mysqli_fetch_array($q)) { $page .= "<tr><td>".padraodate($r["quando"])."</td><td><b>".htmlspecialchars($r["acao"], ENT_QUOTES, 'UTF-8')."</b></td><td>".(intval($r["alvo_id"]) > 0 ? "<a href=\"gm.php?do=jogador:".intval($r["alvo_id"])."\">".intval($r["alvo_id"])."</a>" : "")."</td><td class=\"small\">".htmlspecialchars($r["detalhes"], ENT_QUOTES, 'UTF-8')."</td></tr>"; }
    $page .= "</table>";
    admindisplay($page, t("Minhas ações"));

}



function gmmensagemglobal() { // Janela de mensagem para todos (igual ao admin.php?do=mensagemglobal), registrada na auditoria.

    global $link;
    $mensagem = "";
    $imagens = array();
    foreach (glob("layoutnovo/dropmenu/grande/avatares/*.jpg") as $arquivo) { $imagens[basename($arquivo, ".jpg")] = ucfirst(basename($arquivo, ".jpg")); }
    $padrao = isset($imagens["naruto"]) ? "naruto" : (count($imagens) ? array_key_first($imagens) : "");
    if (isset($_POST["enviar"])) {
        $titulo = mb_substr(trim(painelvalorcru(pegartexto($_POST, "titulo"))), 0, 40, 'UTF-8');
        $texto = mb_substr(trim(painelvalorcru(pegartexto($_POST, "texto"))), 0, 1500, 'UTF-8');
        $imagem = pegartexto($_POST, "imagem", $padrao); if (!isset($imagens[$imagem])) { $imagem = $padrao; }
        $somenteonline = (pegarint($_POST, "somenteonline") == 1);
        if ($titulo == "" || $texto == "") { $mensagem = gmmsg(t("Preencha o título e a mensagem."), "erro"); }
        else {
            $h = function ($x) { return addslashes(htmlspecialchars($x, ENT_QUOTES, 'UTF-8')); };
            $onde = $somenteonline ? "WHERE UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."'" : "";
            doquery("UPDATE {{table}} SET mainmsg='".$imagem."#$%;".$h($titulo)."#$%;".nl2br($h($texto))."' $onde", "users");
            $n = mysqli_affected_rows($link);
            registrarlog("gm_mensagem_todos", 0, ($somenteonline ? "online: " : "todos: ").$titulo);
            $mensagem = gmmsg(tf("Mensagem enviada para %s jogador(es). Ela aparece uma única vez, na próxima página que cada um abrir.", $n));
        }
    }
    $op = ""; foreach ($imagens as $k => $v) { $op .= "<option value=\"$k\"".($k == $padrao ? " selected=\"selected\"" : "").">$v</option>"; }
    $page = "<h1>".t("Mensagem para todos")."</h1>$mensagem<p>".t("Abre a janela flutuante de mensagens do jogo para os jogadores (útil para avisos de manutenção ou eventos).")."</p>"
        ."<form action=\"gm.php?do=mensagemglobal\" method=\"post\">".csrf_campo()."<table>"
        ."<tr><td width=\"120\">".t("Título:")."</td><td><input type=\"text\" name=\"titulo\" size=\"40\" maxlength=\"40\" /></td></tr>"
        ."<tr><td>".t("Imagem:")."</td><td><select name=\"imagem\">$op</select></td></tr>"
        ."<tr><td>".t("Mensagem:")."</td><td><textarea name=\"texto\" rows=\"5\" cols=\"70\" maxlength=\"1500\"></textarea></td></tr>"
        ."<tr><td>".t("Enviar para:")."</td><td><select name=\"somenteonline\"><option value=\"0\">".t("Todos os jogadores")."</option><option value=\"1\">".t("Somente quem está online")."</option></select></td></tr>"
        ."</table><input type=\"submit\" name=\"enviar\" value=\"".t("Enviar mensagem")."\" onclick=\"return confirm('".t("Enviar esta mensagem para os jogadores?")."');\" /></form>";
    admindisplay($page, t("Mensagem para todos"));

}

function gmcaveiras() { // Caveiras (PvP) ativas e banimentos com prazo: só leitura (limpar caveira e banir são do administrador).

    $page = "<h1>".t("Caveiras (PvP)")."</h1><p>".t("Jogadores que mataram outros no PvP do mundo. As caveiras expiram sozinhas; banimentos automáticos têm prazo e acabam sozinhos no próximo acesso depois do prazo.")."</p>";
    $page .= "<table class=\"lista\"><tr><th>".t("Personagem")."</th><th>".t("Caveira")."</th><th>".t("Até")."</th><th>".t("Situação")."</th></tr>";
    $q = doquery("SELECT id,charname,caveira,caveiraate,authlevel,banidoate,acesso FROM {{table}} WHERE (caveira<>'' AND caveiraate > UNIX_TIMESTAMP()) OR (authlevel='2' AND banidoate > 0) ORDER BY caveiraate DESC LIMIT 200", "users");
    $n = 0;
    while ($r = mysqli_fetch_array($q)) {
        $n++;
        $cav = caveiraativa($r);
        $sit = ($r["authlevel"] == 2) ? "<span class=\"highlight\">".tf("banido até %s", date("d-m-Y H:i", intval($r["banidoate"])))."</span>" : "";
        $page .= "<tr><td>".gmnome($r)."</td><td>".selocaveira($r).($cav != "" ? t($cav == "branca" ? "Caveira branca" : "Caveira vermelha") : "-")."</td><td>".($cav != "" ? date("d-m-Y H:i", intval($r["caveiraate"])) : "-")."</td><td>$sit</td></tr>";
    }
    if ($n == 0) { $page .= "<tr><td colspan=\"4\">".t("Ninguém com caveira agora.")."</td></tr>"; }
    admindisplay($page."</table>", t("Caveiras (PvP)"));

}

function gmmapa() { // Mapa do jogo (vilas e regiões dos inimigos), sem links de edição; o administrador pode desligar (control.gm_mapa).

    global $controlrow, $userrow;
    if (intval($userrow["authlevel"]) != 1 && intval(isset($controlrow["gm_mapa"]) ? $controlrow["gm_mapa"] : 1) != 1) { admindisplay(gmmsg(t("O administrador não permite que o GM veja o Mapa do jogo."), "erro"), t("Mapa do jogo")); }
    include_once('funcoesmapajogo.php');
    admindisplay(mapajogohtml("gm"), t("Mapa do jogo"));

}
