<?php // painelseguranca.php :: Segurança dos painéis da equipe (admin.php, admin_missoes.php e gm.php).
// - Lista de IPs liberados (config.php: "adminips"), opcional.
// - Senha de novo para abrir o painel: cookie de sessão próprio assinado com HMAC, que expira após 30 minutos sem uso.
//   As senhas erradas contam na mesma tabela loginfail do login (5 erros em 15 minutos bloqueiam o IP).
// - Confirmação com a senha antes de ações perigosas (apagar, ações em massa, mudar PvP/e-mail, promover equipe).
// - Registro de auditoria (tabela adminlog): quem, quando, IP, ação e detalhes.
// Só pode ser incluído (os painéis chamam painelexigir() logo depois de conferir o cargo do jogador).

if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

define('PAINEL_OCIOSO', 1800);      // segundos sem usar o painel até pedir a senha de novo.
define('PAINEL_MAXERROS', 5);       // senhas erradas (por IP, em 15 minutos) até bloquear, como no login.

function painelip() { // IP do visitante (no tamanho da coluna).

    return substr(isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : "", 0, 45);

}

function painelipnafaixa($ip, $regra) { // O IP bate com a regra? Regra = IP exato ou faixa CIDR ("10.0.0.0/8", "2001:db8::/32").

    $regra = trim($regra);
    if ($regra == "") { return false; }
    if (strpos($regra, "/") === false) { return inet_pton($ip) !== false && inet_pton($ip) === @inet_pton($regra); }
    list($rede, $bits) = explode("/", $regra, 2);
    $ipbin = @inet_pton($ip); $redebin = @inet_pton($rede); $bits = intval($bits);
    if ($ipbin === false || $redebin === false || strlen($ipbin) != strlen($redebin)) { return false; }
    $bytes = intdiv($bits, 8); $resto = $bits % 8;
    if ($bytes > strlen($ipbin)) { return false; }
    if (substr($ipbin, 0, $bytes) !== substr($redebin, 0, $bytes)) { return false; }
    if ($resto == 0) { return true; }
    $mascara = chr((0xFF << (8 - $resto)) & 0xFF);
    return ((ord($ipbin[$bytes]) & ord($mascara)) === (ord($redebin[$bytes]) & ord($mascara)));

}

function painelipliberado() { // Lista branca de IPs dos painéis (config.php "adminips", separados por vírgula/espaço). Vazia = qualquer IP.

    global $dbsettings;
    $lista = isset($dbsettings["adminips"]) ? trim((string)$dbsettings["adminips"]) : "";
    if ($lista == "") { return true; }
    foreach (preg_split("/[\s,;]+/", $lista) as $regra) { if (painelipnafaixa(painelip(), $regra)) { return true; } }
    return false;

}

function painelcookie() { // Nome do cookie do painel (separado do cookie de login).

    global $dbsettings;
    return $dbsettings["cookiename"]."_painel";

}

function painelassinatura($row, $expira) { // HMAC do token: muda se a senha ou o cargo mudarem (trocar a senha derruba o painel aberto).

    global $dbsettings;
    return hash_hmac('sha256', "painel|".intval($row["id"])."|".intval($expira)."|".$row["password"]."|".intval($row["authlevel"])."|".intval($row["acesso"]), $dbsettings["secretword"]);

}

function painelgravartoken($row) { // Grava (ou renova) o token: cookie de sessão (some ao fechar o navegador), validade de 30 minutos a partir de agora.

    $expira = time() + PAINEL_OCIOSO;
    $seguro = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
    if (headers_sent()) { return; }
    setcookie(painelcookie(), intval($row["id"]).":".$expira.":".painelassinatura($row, $expira), array("expires" => 0, "path" => "/", "secure" => $seguro, "httponly" => true, "samesite" => "Strict"));

}

function painelapagartoken() { // Fecha o painel (sem sair do jogo).

    if (headers_sent()) { return; }
    setcookie(painelcookie(), "", array("expires" => time() - 100000, "path" => "/", "httponly" => true, "samesite" => "Strict"));

}

function paineltokenvalido($row) { // O cookie do painel é deste jogador, foi assinado por nós e ainda não expirou?

    $nome = painelcookie();
    if (!isset($_COOKIE[$nome]) || !is_string($_COOKIE[$nome])) { return false; }
    $partes = explode(":", $_COOKIE[$nome]);
    if (count($partes) != 3 || intval($partes[0]) != intval($row["id"]) || intval($partes[1]) < time()) { return false; }
    return hash_equals(painelassinatura($row, intval($partes[1])), (string)$partes[2]);

}

function painelfalhas($ip) { // Senhas erradas desse IP nos últimos 15 minutos (mesma tabela e regra do login.php).

    $query = doquery("SELECT tentativas FROM {{table}} WHERE ip='".addslashes($ip)."' AND ultima >= (NOW() - INTERVAL 15 MINUTE) LIMIT 1", "loginfail");
    $row = mysqli_fetch_array($query);
    return $row ? intval($row["tentativas"]) : 0;

}

function painelregistrarfalha($ip) { // Conta mais uma senha errada para o IP.

    doquery("DELETE FROM {{table}} WHERE ultima < (NOW() - INTERVAL 15 MINUTE)", "loginfail");
    doquery("INSERT INTO {{table}} (ip, tentativas, ultima) VALUES ('".addslashes($ip)."', 1, NOW()) ON DUPLICATE KEY UPDATE tentativas=tentativas+1, ultima=NOW()", "loginfail");

}

function painelcargo($row) { // "adm" ou "gm" (para o registro).

    return (intval($row["authlevel"]) == 1) ? "adm" : (ehgm($row) ? "gm" : "");

}

function registrarlog($acao, $alvoid = 0, $detalhes = "") { // Grava uma linha no registro de auditoria (tabela adminlog) em nome do jogador logado.

    global $userrow;
    $autorid = is_array($userrow) ? intval($userrow["id"]) : 0;
    $autor = is_array($userrow) ? $userrow["charname"] : "";
    $detalhes = mb_substr((string)$detalhes, 0, 2000, 'UTF-8');
    doquery("INSERT INTO {{table}} SET quando=NOW(), autor_id='$autorid', autor='".addslashes($autor)."', cargo='".painelcargo($userrow)."', ip='".addslashes(painelip())."', acao='".addslashes(mb_substr($acao, 0, 60, 'UTF-8'))."', alvo_id='".intval($alvoid)."', detalhes='".addslashes($detalhes)."'", "adminlog");

}

function painelvalorcru($v) { // Desfaz o tratamento global do lib.php (htmlspecialchars + addslashes) para reenviar ou registrar o valor original.

    if (is_array($v)) { return array_map('painelvalorcru', $v); }
    return stripslashes(html_entity_decode((string)$v, ENT_QUOTES, 'UTF-8'));

}

function painelresumopost() { // Campos enviados no POST, em texto curto, para o registro (sem senhas nem token).

    $partes = array();
    foreach ($_POST as $campo => $valor) {
        if (in_array($campo, array("csrf", "senhapainel", "senhaconfirmar"), true) || stripos($campo, "senha") !== false || stripos($campo, "pass") !== false) {
            if ($campo != "csrf" && $valor !== "") { $partes[] = $campo."=***"; }
            continue;
        }
        $valor = is_array($valor) ? json_encode(painelvalorcru($valor), JSON_UNESCAPED_UNICODE) : painelvalorcru($valor);
        $partes[] = $campo."=".mb_substr(str_replace(array("\r", "\n"), " ", $valor), 0, 80, 'UTF-8');
    }
    return implode("; ", $partes);

}

function paineltela($conteudo, $titulo) { // Página do painel sem o menu (tela de senha, acesso negado, confirmação).

    $GLOBALS["paineltipo"] = "entrar";
    admindisplay($conteudo, $titulo);

}

function painelexigir($painel) { // Porteiro dos painéis: IP liberado, senha digitada de novo há menos de 30 min. $painel = "admin" ou "gm".

    global $userrow;
    $t = "t";
    if (!headers_sent()) { header("Cache-Control: no-store, private"); }
    $GLOBALS["paineltipo"] = $painel;

    if (!painelipliberado()) {
        registrarlog("painel_ip_bloqueado", 0, $painel." ".(isset($_SERVER["REQUEST_URI"]) ? substr($_SERVER["REQUEST_URI"], 0, 200) : ""));
        if (!headers_sent()) { http_response_code(403); }
        paineltela("<h1>".t("Acesso negado")."</h1><div class=\"erro\">".t("Este painel só pode ser aberto a partir dos endereços de IP liberados pelo administrador do servidor.")."</div><p><a href=\"index.php\">".t("Voltar ao jogo")."</a></p>", t("Acesso negado"));
    }

    // Sair do painel (sem sair do jogo).
    if (isset($_GET["do"]) && $_GET["do"] == "sairpainel") {
        registrarlog("painel_sair", 0, $painel);
        painelapagartoken();
        header("Location: index.php"); die();
    }

    $erro = "";
    if (isset($_POST["senhapainel"])) {
        $ip = painelip();
        if (painelfalhas($ip) >= PAINEL_MAXERROS) {
            $erro = t("Muitas tentativas de login erradas. Aguarde 15 minutos e tente novamente.");
            registrarlog("painel_bloqueado", 0, $painel);
        } elseif (!csrf_confere() || !senha_confere(pegartexto($_POST, "senhapainel"), $userrow["password"])) {
            painelregistrarfalha($ip);
            registrarlog("painel_senha_errada", 0, $painel);
            $erro = t("Senha incorreta.");
        } else {
            doquery("DELETE FROM {{table}} WHERE ip='".addslashes($ip)."'", "loginfail");
            painelgravartoken($userrow);
            registrarlog("painel_entrar", 0, $painel);
            // Volta para a mesma página por GET (o POST da senha não é repetido pelo navegador).
            $destino = ($painel == "gm") ? "gm.php" : ((basename($_SERVER["SCRIPT_NAME"]) == "admin_missoes.php") ? "admin_missoes.php" : "admin.php");
            $do = isset($_GET["do"]) && preg_match("/^[a-z0-9_:]{1,40}$/i", (string)$_GET["do"]) ? "?do=".$_GET["do"] : "";
            header("Location: ".$destino.$do); die();
        }
    }

    if ($erro == "" && paineltokenvalido($userrow)) { painelgravartoken($userrow); return; } // Usou o painel: mais 30 minutos.

    // Tela de senha (GET de qualquer rota do painel ou POST sem token válido: nada é executado).
    $acao = htmlspecialchars(isset($_SERVER["REQUEST_URI"]) ? basename(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)) . (isset($_GET["do"]) && preg_match("/^[a-z0-9_:]{1,40}$/i", (string)$_GET["do"]) ? "?do=".$_GET["do"] : "") : "admin.php", ENT_QUOTES, 'UTF-8');
    $nomepainel = ($painel == "gm") ? t("Painel do GameMaster") : t("Painel do Administrador");
    $msgerro = ($erro != "") ? "<div class=\"erro\">$erro</div>" : "";
    $csrf = csrf_campo();
    $nome = $userrow["charname"];
    $imagem = ehgm($userrow) ? "<img src=\"layoutnovo/graficos/gm_minimap.gif\" alt=\"\" style=\"vertical-align:middle\" /> " : "";
    $page = <<<FIMDATELA
<div style="max-width:460px;margin:30px auto;background:#fff1c7;border:2px solid #452202;padding:18px 22px">
<h1>$imagem$nomepainel</h1>
$msgerro
<p>{$t("Por segurança, digite a sua senha de novo para abrir o painel.")}<br /><span class="small">{$t("O acesso vale até você fechar o navegador ou ficar 30 minutos sem usar o painel.")}</span></p>
<form action="$acao" method="post">$csrf
<table><tr><td>{$t("Personagem:")}</td><td><b>$nome</b></td></tr>
<tr><td>{$t("Senha:")}</td><td><input type="password" name="senhapainel" size="24" autofocus="autofocus" autocomplete="current-password" /></td></tr>
<tr><td></td><td><input class="btnjogo bj-confirmar" type="submit" value="{$t("Abrir o painel")}" /> &nbsp; <a href="index.php">{$t("Voltar ao jogo")}</a></td></tr></table>
</form>
<p class="small">{$t("Senhas erradas contam no bloqueio de login: 5 erros em 15 minutos bloqueiam o seu IP.")}</p>
</div>
FIMDATELA;
    paineltela($page, t("Senha"));

}

function painelconfirmar($descricao) { // Ação perigosa: exige a senha no próprio POST (campo senhaconfirmar). Sem ela, mostra a tela de confirmação, que reenvia o mesmo formulário.

    global $userrow;
    $t = "t";
    $erro = "";
    if (isset($_POST["senhaconfirmar"])) {
        $ip = painelip();
        if (painelfalhas($ip) >= PAINEL_MAXERROS) { $erro = t("Muitas tentativas de login erradas. Aguarde 15 minutos e tente novamente."); }
        elseif (senha_confere(pegartexto($_POST, "senhaconfirmar"), $userrow["password"])) {
            registrarlog("confirmado", 0, $descricao);
            unset($_POST["senhaconfirmar"]);
            return true;
        } else {
            painelregistrarfalha($ip);
            registrarlog("confirmacao_senha_errada", 0, $descricao);
            $erro = t("Senha incorreta.");
        }
    }

    // Reenvia todos os campos do formulário original (com os valores originais) mais a senha.
    $campos = "";
    $escrever = function($nome, $valor) use (&$campos, &$escrever) {
        if (is_array($valor)) { foreach ($valor as $k => $v) { $escrever($nome."[".$k."]", $v); } return; }
        $campos .= "<input type=\"hidden\" name=\"".htmlspecialchars($nome, ENT_QUOTES, 'UTF-8')."\" value=\"".htmlspecialchars($valor, ENT_QUOTES, 'UTF-8')."\" />\n";
    };
    foreach ($_POST as $nome => $valor) { if ($nome != "senhaconfirmar") { $escrever($nome, painelvalorcru($valor)); } }
    if (!isset($_POST["csrf"])) { $campos .= csrf_campo(); }
    $acao = htmlspecialchars(basename(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)).(($q = parse_url($_SERVER["REQUEST_URI"], PHP_URL_QUERY)) ? "?".$q : ""), ENT_QUOTES, 'UTF-8');
    $msgerro = ($erro != "") ? "<div class=\"erro\">$erro</div>" : "";
    $page = <<<FIMDATELA
<div style="max-width:520px;margin:30px auto;background:#fff1c7;border:2px solid #8b1a1a;padding:18px 22px">
<h1>{$t("Confirmar ação")}</h1>
$msgerro
<div class="aviso"><b>$descricao</b></div>
<p>{$t("Esta ação é perigosa ou não pode ser desfeita. Digite a sua senha para confirmar.")}</p>
<form action="$acao" method="post">
$campos<input type="password" name="senhaconfirmar" size="24" autofocus="autofocus" autocomplete="current-password" />
<input type="submit" value="{$t("Confirmar")}" class="botaoperigo btnjogo bj-cancelar" /> &nbsp; <a href="javascript:history.back()">{$t("Cancelar")}</a>
</form>
</div>
FIMDATELA;
    paineltela($page, t("Confirmar ação"));

}

?>
