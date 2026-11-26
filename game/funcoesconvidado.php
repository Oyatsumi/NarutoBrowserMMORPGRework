<?php // funcoesconvidado.php :: Jogar sem conta (convidado): sessão PHP, criação, restrições, banner, conversão e limpeza.
// O convidado é uma linha normal de naruto_users com convidado=1 e convidado_expira (UNIX). Ele é identificado SÓ pela
// sessão PHP do servidor (cookie "narutosessao" = id aleatório da sessão); nada do personagem vai em cookie.
// Configuração: control.convidado_ativo (1/0), control.convidado_horas (prazo) e control.convidado_porip (novos por IP por hora; 0 = desligado).

if (!function_exists('convidado_sessao')) {

function criarpersonagemnovo($username, $charname, $hashsenha, $email, $difficulty, $verifycode, $sqlperguntas, $sqlextra) { // INSERT do ninja novo (cadastro e convidado usam o mesmo). Devolve o id.
    global $controlrow, $link;
    $startgold = intval($controlrow["startgold"]);
    if ($startgold < 0) { $startgold = 0; }
    if ($startgold > 16777215) { $startgold = 16777215; } // Limite da coluna gold (MEDIUMINT UNSIGNED).
    // Os campos TEXT não têm valor padrão confiável em todas as instalações, então são preenchidos aqui.
    // onlinetime fica no passado: o login.php recusa a entrada se a conta parecer "online" (último acesso há menos de 61s) em outro IP,
    // e o valor padrão da coluna (agora) bloquearia o primeiro login logo depois do registro.
    // Alinhamento = vila natal: o cadastro não pergunta mais. Todo ninja novo nasce na vila inicial (control.vilainicial,
    // padrão Vila da Folha); um campo "alinhamento" enviado à mão é ignorado. Trocar depois só pelas transferências.
    include_once('cidadesconteudo.php');
    $alinhamento = addslashes(alinhamentoinicial());
    $password = addslashes($hashsenha);
    $difficulty = intval($difficulty);
    doquery("INSERT INTO {{table}} SET regdate=NOW(), onlinetime=(NOW() - INTERVAL 2 MINUTE), verify='$verifycode', username='$username', password='$password', email='$email', charname='$charname', charclass='0', difficulty='$difficulty', gold='$startgold', alinhamento='$alinhamento', caixadepm='', mainmsg='None', bancogeral='None', historico='None', questsaux='None', treinamento='None', $sqlperguntas".($sqlextra != "" ? ", ".$sqlextra : ""), "users");
    return mysqli_insert_id($link);
}

function convidado_sessaonome() { return "narutosessao"; }

function convidado_sessao() { // Abre a sessão PHP com cookie seguro (HttpOnly, SameSite=Lax, Secure no https). Pasta própria: o prazo não depende do php.ini.
    if (session_status() == PHP_SESSION_ACTIVE) { return true; }
    if (headers_sent()) { return false; }
    global $controlrow;
    $horas = (is_array($controlrow) && isset($controlrow["convidado_horas"])) ? max(1, intval($controlrow["convidado_horas"])) : 24;
    $pasta = sys_get_temp_dir()."/naruto_sessoes";
    if (!is_dir($pasta)) { @mkdir($pasta, 0700, true); }
    if (is_dir($pasta) && is_writable($pasta)) { session_save_path($pasta); }
    ini_set("session.use_strict_mode", "1"); // id de sessão inventado pelo navegador é recusado (fixação de sessão).
    ini_set("session.use_only_cookies", "1");
    ini_set("session.gc_maxlifetime", (string)($horas * 3600 + 3600));
    session_name(convidado_sessaonome());
    session_set_cookie_params(array("lifetime" => $horas * 3600, "path" => "/",
        "secure" => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off'), "httponly" => true, "samesite" => "Lax"));
    return @session_start();
}

function convidado_temsessao() { return isset($_COOKIE[convidado_sessaonome()]) && $_COOKIE[convidado_sessaonome()] != ""; }

function convidado_sair() { // Esquece o convidado desta sessão e apaga o cookie da sessão.
    if (session_status() != PHP_SESSION_ACTIVE && convidado_temsessao()) { convidado_sessao(); }
    if (session_status() == PHP_SESSION_ACTIVE) {
        $_SESSION = array();
        @session_destroy();
    }
    if (!headers_sent()) { setcookie(convidado_sessaonome(), "", array("expires" => time() - 100000, "path" => "/", "httponly" => true, "samesite" => "Lax")); }
}

function convidado_assinatura($row) { // Liga a sessão à linha: se a senha mudar (conversão), a sessão de convidado deixa de valer.
    global $dbsettings;
    return hash_hmac('sha256', "convidado|".$row["id"]."|".$row["password"], $dbsettings["secretword"]);
}

function convidado_podeautocriar() { // Só num GET normal da página principal (index.php sem ação), navegação de documento, sem AJAX.
    global $controlrow;
    if (basename(isset($_SERVER["SCRIPT_NAME"]) ? $_SERVER["SCRIPT_NAME"] : "") != "index.php") { return false; }
    // Parâmetros que não são ações do jogo não impedem: "i" é posto pela checagem anti-robô da hospedagem gratuita
    // (a 1ª visita passa por uma página que grava o cookie __test e volta para "/?i=1"); os outros vêm de links externos.
    $ignorar = array("i", "idioma", "fbclid", "gclid", "ref");
    $acoes = array_filter(array_keys($_GET), function ($k) use ($ignorar) { return !in_array($k, $ignorar, true) && strpos($k, "utm_") !== 0; });
    if ($_SERVER["REQUEST_METHOD"] != "GET" || count($acoes) > 0) { return false; }
    if (isset($_SERVER["HTTP_X_REQUESTED_WITH"])) { return false; }
    if (isset($_SERVER["HTTP_SEC_FETCH_DEST"]) && $_SERVER["HTTP_SEC_FETCH_DEST"] != "document") { return false; }
    if (isset($_SERVER["HTTP_SEC_FETCH_MODE"]) && $_SERVER["HTTP_SEC_FETCH_MODE"] != "navigate") { return false; }
    // Robôs/rastreadores também ganham convidado (decisão do dono); o limite por IP segura o abuso.
    if (is_array($controlrow) && isset($controlrow["convidado_ativo"]) && intval($controlrow["convidado_ativo"]) == 0) { return false; } // desligado na configuração
    if (!is_array($controlrow) || intval(isset($controlrow["convidado_porip"]) ? $controlrow["convidado_porip"] : 5) <= 0 || intval($controlrow["allowregister"]) == 0) { return false; }
    return true;
}

function convidado_ip() { return addslashes(substr(isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : "", 0, 45)); }

function convidado_criar() { // Cria o personagem convidado e entra pela sessão. Devolve a linha ou false (limite do IP / falha).
    global $controlrow;
    if (!convidado_sessao()) { return false; }
    $porip = intval(isset($controlrow["convidado_porip"]) ? $controlrow["convidado_porip"] : 5);
    if ($porip <= 0) { return false; }
    if (isset($controlrow["convidado_ativo"]) && intval($controlrow["convidado_ativo"]) == 0) { return false; } // desligado na configuração
    // Limite por IP e por hora. GET_LOCK por IP: duas abas ao mesmo tempo não passam juntas do limite.
    $ip = convidado_ip();
    $trava = "naruto_convidado_".md5($ip);
    doquery("SELECT GET_LOCK('$trava', 10)", "users");
    $n = mysqli_fetch_array(doquery("SELECT COUNT(*) AS n FROM {{table}} WHERE convidado_ip='$ip' AND regdate >= (NOW() - INTERVAL 1 HOUR)", "users"));
    if (intval($n["n"]) >= $porip) { doquery("SELECT RELEASE_LOCK('$trava')", "users"); return false; }
    // Nomes automáticos únicos (ConvidadoNNNNNN). Senha: hash de bytes aleatórios jogados fora (ninguém consegue logar com ela).
    $nome = "";
    for ($i = 0; $i < 20 && $nome == ""; $i++) {
        $tenta = "Convidado".random_int(100000, 999999);
        if (mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE username='$tenta' OR charname='$tenta' LIMIT 1", "users")) == 0) { $nome = $tenta; }
    }
    if ($nome == "") { doquery("SELECT RELEASE_LOCK('$trava')", "users"); return false; }
    $horas = max(1, intval(isset($controlrow["convidado_horas"]) ? $controlrow["convidado_horas"] : 24));
    $id = criarpersonagemnovo($nome, $nome, senha_hash(bin2hex(random_bytes(32))), "", 1, "1", "codigorecuperacao=''",
        "convidado='1', convidado_expira='".(time() + $horas * 3600)."', convidado_ip='$ip'");
    doquery("SELECT RELEASE_LOCK('$trava')", "users");
    $row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "users"));
    session_regenerate_id(true);
    $_SESSION["convidado_id"] = intval($row["id"]);
    $_SESSION["convidado_sig"] = convidado_assinatura($row);
    return $row;
}

function convidado_fecharaviso() { // POST users.php?do=convidadofechar (csrf): o aviso de boas-vindas não aparece mais nesta sessão.
    global $userrow;
    $userrow = checkcookies();
    if ($userrow && intval($userrow["convidado"]) == 1 && $_SERVER["REQUEST_METHOD"] == "POST" && csrf_confere()) { $_SESSION["convidado_avisofechado"] = 1; echo "ok"; } else { http_response_code(403); echo "erro"; }
    die();
}

function convidado_linha() { // Chamada pelo checkcookies() sem cookie de login: devolve a linha do convidado desta sessão (ou false).
    if (!convidado_temsessao()) { return false; }
    if (!convidado_sessao() || empty($_SESSION["convidado_id"])) { return false; }
    $id = intval($_SESSION["convidado_id"]);
    $q = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "users");
    $row = (mysqli_num_rows($q) == 1) ? mysqli_fetch_array($q) : false;
    $sig = isset($_SESSION["convidado_sig"]) ? (string)$_SESSION["convidado_sig"] : "";
    if (!$row || intval($row["convidado"]) != 1 || !hash_equals(convidado_assinatura($row), $sig) || intval($row["convidado_expira"]) <= time()) {
        convidado_sair();
        $script = basename(isset($_SERVER["SCRIPT_NAME"]) ? $_SERVER["SCRIPT_NAME"] : "");
        if (!headers_sent() && $script != "login.php") {
            header("Location: login.php?do=login&conteudo=".rawurlencode(t("O tempo do seu personagem sem conta acabou e ele foi apagado. Crie uma conta para guardar o próximo.")));
            die();
        }
        return false;
    }
    doquery("UPDATE {{table}} SET onlinetime=NOW() WHERE id='$id' LIMIT 1", "users");
    return $row;
}

function convidado_bloqueado($row) { // Restrições do convidado: nada que transfira itens/Ryou a outros, PvP/duelo, clã, troca e chats públicos.
    if (empty($row) || intval($row["convidado"]) != 1) { return false; }
    $script = basename(isset($_SERVER["SCRIPT_NAME"]) ? $_SERVER["SCRIPT_NAME"] : "");
    $do = (isset($_GET["do"]) && is_string($_GET["do"])) ? $_GET["do"] : "";
    $do0 = explode(":", $do); $do0 = $do0[0];
    if (in_array($script, array("troca.php", "pvp.php", "cla.php", "chat.php", "forum.php", "pm.php"))) { return true; }
    if ($script == "users.php" && in_array($do, array("doardinheiro", "batalha1", "duelo", "banco", "doaritem", "troca1", "troca2", "troca3", "troca4", "perguntas", "changepassword"))) { return true; }
    if ($script == "funcaoitens.php" && in_array($do, array("enviaritem", "doarryou"))) { return true; }
    if ($script == "mainmsg.php" && isset($_GET["do2"]) && $_GET["do2"] == "enviarpm") { return true; }
    if ($script == "index.php" && in_array($do0, array("chatmap", "duelo")) ) { return true; }
    if ($script == "index.php" && in_array($do0, array("babblebox", "babbleboxpage")) && $_SERVER["REQUEST_METHOD"] == "POST") { return true; }
    return false;
}

function convidado_aplicarbloqueio($row) {
    if (!convidado_bloqueado($row) || headers_sent()) { return; }
    $msg = t("Isso não está disponível para personagens sem conta. Crie sua conta para liberar (você mantém o personagem).");
    if (isset($_SERVER["HTTP_X_REQUESTED_WITH"]) || (isset($_SERVER["HTTP_ACCEPT"]) && strpos($_SERVER["HTTP_ACCEPT"], "application/json") !== false && strpos($_SERVER["HTTP_ACCEPT"], "text/html") === false)) {
        // Resposta de um envio por AJAX: a navegação dinâmica mostra a página inteira; vai com os links para voltar e criar a conta.
        http_response_code(403); header("Content-Type: text/html; charset=utf-8");
        echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>".htmlspecialchars(t("Conta necessária"), ENT_QUOTES, "UTF-8")."</title></head>"
            ."<body style=\"background:#f3e3b5;font:15px tahoma,arial,sans-serif;color:#2a1a0c;margin:0;padding:40px 16px;text-align:center\">"
            ."<div style=\"max-width:460px;margin:0 auto;background:#fff1c7;border:2px solid #452202;border-radius:6px;padding:18px\"><p>".htmlspecialchars($msg, ENT_QUOTES, "UTF-8")."</p>"
            ."<p><a href=\"index.php\" style=\"display:inline-block;margin:4px;padding:8px 14px;background:#452202;color:#fff;border-radius:4px;text-decoration:none;font-weight:bold\">".htmlspecialchars(t("Voltar ao jogo"), ENT_QUOTES, "UTF-8")."</a>"
            ."<a href=\"users.php?do=convidadoconta\" style=\"display:inline-block;margin:4px;padding:8px 14px;background:#3f7f2a;color:#fff;border-radius:4px;text-decoration:none;font-weight:bold\">".htmlspecialchars(t("Criar conta e manter personagem"), ENT_QUOTES, "UTF-8")."</a>"
            ."<a href=\"login.php?do=login\" style=\"display:inline-block;margin:4px;padding:8px 14px;background:#2e5f8a;color:#fff;border-radius:4px;text-decoration:none;font-weight:bold\">".htmlspecialchars(t("Já tenho conta: entrar"), ENT_QUOTES, "UTF-8")."</a></p></div></body></html>";
        die();
    }
    header("Location: index.php?conteudo=".rawurlencode($msg)); die();
}

function convidado_banner($row) { // Faixa fixa discreta com o tempo restante e o link para criar a conta.
    if (!is_array($row) || empty($row["convidado"]) || intval($row["convidado"]) != 1) { return ""; }
    $resta = max(0, intval($row["convidado_expira"]) - time());
    $h = intdiv($resta, 3600); $m = intdiv($resta % 3600, 60);
    $aviso = "";
    if (session_status() == PHP_SESSION_ACTIVE && empty($_SESSION["convidado_avisofechado"]) && basename(isset($_SERVER["SCRIPT_NAME"]) ? $_SERVER["SCRIPT_NAME"] : "") != "users.php") { // não cobre o próprio formulário de criar conta
        $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, "UTF-8");
        $aviso = "<div id=\"convidadoaviso\" style=\"position:fixed;left:0;top:0;right:0;bottom:0;z-index:10000;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:16px;box-sizing:border-box\">"
            ."<div class=\"cxb\" style=\"max-width:420px;width:100%;position:relative\"><div class=\"cxb-cab\">".t("Você está jogando como convidado")
            ."<a href=\"#\" id=\"convidadofechar\" title=\"".t("Fechar")."\" style=\"position:absolute;right:8px;top:2px;color:#fff;text-decoration:none;font-weight:bold\">X</a></div>"
            ."<div class=\"cxb-corpo\" style=\"padding:8px;text-align:center\">".tf("Seu personagem foi criado sem conta e fica guardado só neste navegador. Se você não criar a conta, ele <b>some em %dh %02dmin</b>. Algumas funções (trocas, envio de itens e Ryou, duelos, PvP, clãs, chats e fórum) só liberam com a conta.", $h, $m)
            ."<br /><br /><div class=\"buttons\" style=\"display:flex;flex-wrap:wrap;justify-content:center;gap:6px\"><a class=\"positive\" href=\"users.php?do=convidadoconta\" id=\"convidadocriar\">".t("Criar conta e manter personagem")."</a> <a href=\"login.php?do=login\" id=\"convidadoentrar\">".t("Já tenho conta: entrar")."</a> <a href=\"#\" id=\"convidadojogar\">".t("Jogar agora sem cadastrar")."</a></div></div></div></div>"
            ."<script type=\"text/javascript\">(function(){var d=document.getElementById('convidadoaviso');try{if(localStorage.getItem('convidadoavisofechado')=='1'){d.style.display='none';}}catch(e){}"
            ."document.getElementById('convidadojogar').onclick=function(ev){document.getElementById('convidadofechar').onclick(ev);};document.getElementById('convidadofechar').onclick=function(ev){ev.preventDefault();d.style.display='none';try{localStorage.setItem('convidadoavisofechado','1');}catch(e){}"
            ."var x=new XMLHttpRequest();x.open('POST','users.php?do=convidadofechar',true);x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');x.send('csrf=$csrf');};})();</script>";
    }
    return "<div id=\"convidadobanner\" style=\"position:fixed;left:0;right:0;bottom:0;z-index:9999;background:#452202;color:#fff1c7;font-size:12px;text-align:center;padding:4px 8px;border-top:2px solid #e4d094\">"
        .tf("Personagem sem conta: some em %dh %02dmin.", $h, $m)." <a href=\"users.php?do=convidadoconta\" style=\"color:#ffd75e;font-weight:bold\">".t("Criar conta e manter personagem")."</a> · <a href=\"login.php?do=login\" style=\"color:#ffd75e\">".t("Já tenho conta: entrar")."</a></div>".$aviso;
}

function convidado_limpar($forcar = false) { // Apaga convidados vencidos e tudo ligado a eles. No máximo 1x a cada 10 min (control.convidado_ultimalimpeza).
    $agora = time();
    if (!$forcar) {
        doquery("UPDATE {{table}} SET convidado_ultimalimpeza='$agora' WHERE id='1' AND convidado_ultimalimpeza < '".($agora - 600)."' LIMIT 1", "control");
        global $link;
        if (mysqli_affected_rows($link) != 1) { return 0; } // outra requisição já está limpando (ou limpou há pouco).
    }
    $ids = array();
    $q = doquery("SELECT id FROM {{table}} WHERE convidado='1' AND convidado_expira < '$agora' LIMIT 500", "users");
    while ($r = mysqli_fetch_array($q)) { $ids[] = intval($r["id"]); }
    if (count($ids) == 0) { return 0; }
    $lista = implode(",", $ids);
    // Tabelas com o id do jogador (convidado não entra em clã/duelo/PvP, mas apaga tudo por garantia).
    $ligadas = array("itens_roubados" => "jogador", "chefes_abates" => "jogador", "clas_membros" => "usuario", "clas_convites" => "usuario",
        "clas_contrib" => "usuario", "missoesextras_jogador" => "usuario_id", "temporada_pontos" => "usuario", "titulos" => "usuario");
    foreach ($ligadas as $tabela => $coluna) { doquery("DELETE FROM {{table}} WHERE $coluna IN ($lista)", $tabela); }
    doquery("DELETE FROM {{table}} WHERE desafiante IN ($lista) OR desafiado IN ($lista)", "duelos");
    doquery("DELETE FROM {{table}} WHERE atacante IN ($lista) OR defensor IN ($lista)", "pvp");
    doquery("DELETE FROM {{table}} WHERE id IN ($lista) AND convidado='1' AND convidado_expira < '$agora'", "users"); // conferido de novo: quem converteu agora fica.
    return count($ids);
}

function convidadoconta() { // users.php?do=convidadoconta: cria a conta mantendo o MESMO personagem (mesma linha de naruto_users).
    global $userrow, $controlrow;
    $userrow = checkcookies();
    if (!$userrow || intval($userrow["convidado"]) != 1) { header("Location: login.php?do=login"); die(); }
    $topo = titulopagina("criarconta.gif");
    $erros = array();
    $charname = $userrow["charname"];
    if (isset($_POST["submit"])) {
        if (!csrf_confere()) { $erros[] = "Formulário expirado. Tente novamente."; }
        $username = pegartexto($_POST, "username");
        $charname = pegartexto($_POST, "charname");
        $email1 = pegartexto($_POST, "email1"); $email2 = pegartexto($_POST, "email2");
        $password1 = pegartexto($_POST, "password1"); $password2 = pegartexto($_POST, "password2");
        // Mesmas regras do cadastro (users.php); o próprio convidado pode manter o nome do personagem.
        validarnomeconta($username, $erros, intval($userrow["id"]));
        validarnomepersonagem($charname, $erros, intval($userrow["id"]));
        validaremailcadastro($email1, $email2, $erros, intval($userrow["id"]));
        $erro = senhavalida($password1); if ($erro != "") { $erros[] = $erro; }
        if ($password1 != $password2) { $erros[] = "Os campos de senha não coincidem."; }
        $codigonovo = "";
        if (pegartexto($_POST, "metodo") == "codigo") { $codigonovo = gerarcodigorecuperacao(); $sqlperguntas = "codigorecuperacao='".addslashes(senha_hash(normalizarcodigo($codigonovo)))."'"; }
        else { $sqlperguntas = lerperguntas($erros); }
        if (count($erros) == 0) {
            $verifycode = verificaremail() ? gerarcodigoverificacao() : '1';
            $hash = senha_hash($password1);
            global $link;
            doquery("START TRANSACTION", "users");
            $eu = mysqli_fetch_array(doquery("SELECT id, convidado FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
            $ok = false;
            if ($eu && intval($eu["convidado"]) == 1) {
                // UPDATE condicional: só converte se ainda for convidado. Nome repetido (corrida com outro cadastro) bate no índice único.
                $sql = "UPDATE naruto_users SET convidado='0', convidado_expira='0', username='$username', charname='$charname', password='".addslashes($hash)."', email='$email1', verify='$verifycode', $sqlperguntas WHERE id='".intval($userrow["id"])."' AND convidado='1' LIMIT 1";
                $r = mysqli_query($link, $sql);
                $ok = ($r !== false && mysqli_affected_rows($link) == 1);
                if ($r === false && mysqli_errno($link) != 1062) { error_log("Naruto RPG: erro SQL [".mysqli_error($link)."] na conversão de convidado"); }
            }
            doquery($ok ? "COMMIT" : "ROLLBACK", "users");
            if (!$ok) {
                if ($eu && intval($eu["convidado"]) != 1) { header("Location: login.php?do=login"); die(); }
                $erros[] = "Essa conta já existe.";
            } else {
                $row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1", "users"));
                convidado_sair();
                if (verificaremail()) {
                    sendregmail($email1, $verifycode);
                    display($topo.t("Sua conta foi criada com sucesso.")."<br /><br />".t("Você deve receber um e-mail de confirmação logo. É preciso verificar o código que está presente no seu e-mail para ativar a sua conta. Uma vez recebido o e-mail, por favor visite a <a href=\"users.php?do=verify\">Página de Verificação</a> para ativar sua conta e começar a jogar."), t("Registrar"), false, false, false);
                }
                // Login normal (o mesmo cookie do login.php), já ocupando a vaga do IP.
                $ip = convidado_ip();
                gravarcookie($row["id"]." ".$row["username"]." ".cookie_assinatura($row["id"], $row["username"], $row["password"])." 0", 0);
                doquery("UPDATE {{table}} SET ipadress='$ip', loginip='$ip', onlinetime=NOW() WHERE id='".intval($row["id"])."' LIMIT 1", "users");
                if ($codigonovo != "") { paginacodigo($codigonovo, "index.php", t("Sua conta foi criada com sucesso. Este é o seu código de recuperação:")); }
                header("Location: index.php?conteudo=".rawurlencode(t("Sua conta foi criada com sucesso. Seu personagem foi mantido.")));
                die();
            }
        }
    }
    $h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); };
    $t = "t";
    $msg = "";
    if (count($erros) > 0) { $msg = "<div style=\"color:red\">"; foreach ($erros as $e) { $msg .= t($e)."<br />"; } $msg .= "</div><br />"; }
    $emailtxt = empty($controlrow["emailativo"]) ? "<br /><span class=\"small\">".t("O e-mail é opcional, mas se você cadastrar um ele também é pedido para recuperar a senha.")."</span>" : "";
    $csrf = csrf_campo();
    $metodo = camposmetodo();
    $vchar = $h(html_entity_decode($charname, ENT_QUOTES, "UTF-8"));
    $page = $topo.$msg.<<<FIM
<form action="users.php?do=convidadoconta" method="post" id="formback"><fieldset id="field2"><legend>{$t("Criar conta e manter personagem")}</legend>$csrf
<p class="small">{$t("Seu personagem atual (nível, itens e Ryou) passa para a conta nova. Depois disso ele não some mais.")} <a href="login.php?do=login"><b>{$t("Já tenho conta: entrar")}</b></a></p>
<table width="100%">
<tr><td width="30%">{$t("Nome da Conta:")}</td><td><input type="text" name="username" size="30" maxlength="30" autocomplete="username" required="required" /></td></tr>
<tr><td>{$t("Nome do Personagem:")}</td><td><input type="text" name="charname" size="30" maxlength="30" value="$vchar" required="required" /></td></tr>
<tr><td>{$t("Senha:")}</td><td><input type="password" name="password1" size="30" autocomplete="new-password" required="required" /></td></tr>
<tr><td>{$t("Confirmar Senha:")}</td><td><input type="password" name="password2" size="30" autocomplete="new-password" required="required" /></td></tr>
<tr><td>{$t("E-mail:")}</td><td><input type="email" name="email1" size="30" maxlength="100" />$emailtxt</td></tr>
<tr><td>{$t("Confirmar E-mail:")}</td><td><input type="email" name="email2" size="30" maxlength="100" /></td></tr>
$metodo
</table>
<center><div class="buttons btnlinha"><button type="submit" class="positive btnjogo bj-confirmar" name="submit" id="criarcontaconvidado"><img src="images/botao_criar.png" width="16" height="16" alt="" /> {$t("Criar Conta")}</button> <a href="index.php" id="botaovoltarjogo" class="btnjogo bj-neutro"><img src="images/seta_oeste.png" width="16" height="16" alt="" /> {$t("Voltar ao jogo")}</a></div></center>
</fieldset></form>
FIM;
    display($page, t("Criar conta e manter personagem"), false, false, false);
}

}
?>
