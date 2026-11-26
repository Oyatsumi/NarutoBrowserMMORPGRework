<?php // login.php :: Faz o login/logout do jogador e grava o cookie de acesso.

include('lib.php');
include('cookies.php');
if (isset($_GET["do"])) {
    if ($_GET["do"] == "login") { login(); }
    elseif ($_GET["do"] == "logout") { logout(); }
}
header("Location: login.php?do=login"); die();

function tentativasdelogin($ip) { // Quantas tentativas erradas esse IP fez nos últimos 15 minutos (proteção contra força bruta).

    $query = doquery("SELECT tentativas FROM {{table}} WHERE ip='$ip' AND ultima >= (NOW() - INTERVAL 15 MINUTE) LIMIT 1", "loginfail");
    if (mysqli_num_rows($query) != 1) { return 0; }
    $row = mysqli_fetch_array($query);
    return intval($row["tentativas"]);

}

function registrarfalha($ip) { // Registra uma tentativa errada de login.

    doquery("DELETE FROM {{table}} WHERE ultima < (NOW() - INTERVAL 15 MINUTE)", "loginfail");
    doquery("INSERT INTO {{table}} (ip, tentativas, ultima) VALUES ('$ip', 1, NOW()) ON DUPLICATE KEY UPDATE tentativas=tentativas+1, ultima=NOW()", "loginfail");

}

function loginipmax() { // Vagas por IP (control.loginip_max; 0 = sem limite; padrão 3 se a coluna ainda não existir).

    $row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
    return isset($row["loginip_max"]) ? max(0, intval($row["loginip_max"])) : 3;

}

function login() {

    global $dbsettings;
    $link = opendb();
    $ip = addslashes(substr(isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : "", 0, 45)); // mesmo IP do painelip() (painelseguranca.php): só REMOTE_ADDR, sem confiar em X-Forwarded-For.

    if (isset($_POST["submit"])) {

        $username = pegartexto($_POST, "username");
        $password = pegartexto($_POST, "password");

        if (tentativasdelogin($ip) >= 5) { header("Location: login.php?do=login&conteudo=".rawurlencode(t("Muitas tentativas de login erradas. Aguarde 15 minutos e tente novamente."))); die(); }

        $query = doquery("SELECT * FROM {{table}} WHERE username='".$username."' LIMIT 1", "users");
        if (mysqli_num_rows($query) == 1) { $row = mysqli_fetch_array($query); if (!empty($row["convidado"])) { $query = doquery("SELECT id FROM {{table}} WHERE 0", "users"); } mysqli_data_seek($query, 0); } // convidado não entra pelo formulário
        if (mysqli_num_rows($query) != 1) { registrarfalha($ip); header("Location: login.php?do=login&conteudo=".rawurlencode(t("Nome de usuário ou senha inválidos. Por favor tente novamente."))); die(); }
        $row = mysqli_fetch_array($query);
        if (!senha_confere($password, $row["password"])) { registrarfalha($ip); header("Location: login.php?do=login&conteudo=".rawurlencode(t("Nome de usuário ou senha inválidos. Por favor tente novamente."))); die(); }
        doquery("DELETE FROM {{table}} WHERE ip='$ip'", "loginfail");

        // Contas antigas (senha em MD5) são convertidas para o hash moderno no primeiro login.
        if (senha_precisa_atualizar($row["password"])) {
            $row["password"] = senha_hash($password);
            doquery("UPDATE {{table}} SET password='".addslashes($row["password"])."' WHERE id='".$row["id"]."' LIMIT 1", "users");
        }

        // Impede dois logins simultâneos de IPs diferentes na mesma conta.
        $usersqueryd = doquery("SELECT id FROM {{table}} WHERE UNIX_TIMESTAMP(onlinetime) >= '".(time()-61)."' AND id='".$row["id"]."' LIMIT 1", "users");
        if ((mysqli_num_rows($usersqueryd) == 1) && ($row["ipadress"] != "") && ($row["ipadress"] != $_SERVER['REMOTE_ADDR'])) { header("Location: login.php?do=login&conteudo=".rawurlencode(t("Alguém já está logado em sua conta, por favor aguarde um minuto e tente novamente. Caso isso persista, reporte a alguém da equipe."))); die(); }

        // Limite de contas logadas ao mesmo tempo por IP (control.loginip_max, 0 = sem limite; docs/SEGURANCA.md).
        // Ocupa vaga quem fez login pelo formulário neste IP (users.loginip) e continua online (onlinetime nos últimos
        // 10 minutos, a mesma janela de "online" do resto do jogo). Logout apaga o loginip; sessão parada some sozinha.
        // A própria conta não conta (entrar de novo não gasta outra vaga). Vale também para a equipe.
        // GET_LOCK por IP: dois logins ao mesmo tempo não passam juntos do limite.
        $trava = "naruto_loginip_".md5($ip);
        doquery("SELECT GET_LOCK('$trava', 10)", "users");
        $maxip = loginipmax();
        if ($maxip > 0) {
            $ocupadas = mysqli_fetch_array(doquery("SELECT COUNT(*) AS n FROM {{table}} WHERE loginip='$ip' AND id<>'".intval($row["id"])."' AND UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."'", "users"));
            if (intval($ocupadas["n"]) >= $maxip) {
                doquery("SELECT RELEASE_LOCK('$trava')", "users");
                header("Location: login.php?do=login&conteudo=".rawurlencode(tf("Já existem %d contas conectadas a partir da sua rede (IP), que é o máximo permitido. Saia (logout) de uma delas ou aguarde até 10 minutos sem atividade nela e tente novamente.", $maxip)));
                die();
            }
        }

        if (isset($_POST["rememberme"])) { $expiretime = time()+31536000; $rememberme = 1; } else { $expiretime = 0; $rememberme = 0; }
        $cookie = $row["id"] . " " . $row["username"] . " " . cookie_assinatura($row["id"], $row["username"], $row["password"]) . " " . $rememberme;
        gravarcookie($cookie, $expiretime);
        include_once('funcoesconvidado.php');
        convidado_sair(); // entrou numa conta: esquece o convidado desta sessão (ele some no prazo se não for convertido).
        $nova = doquery("UPDATE {{table}} SET ipadress='".$ip."', loginip='".$ip."', onlinetime=NOW() WHERE id='".$row["id"]."' LIMIT 1", "users");
        doquery("SELECT RELEASE_LOCK('$trava')", "users");
        header("Location: index.php");
        die();

    }

    // Se já estiver logado, vai direto para o jogo. O convidado (jogar sem conta) pode abrir o login para entrar numa conta de verdade.
    include_once('funcoesconvidado.php');
    convidado_limpar(); // apaga convidados vencidos (no máximo 1x a cada 10 min)
    $userrow = checkcookies();
    if ($userrow != false && empty($userrow["convidado"])) { header("Location: index.php"); die(); }

    global $conteudouser, $controlrow;
    $controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
    $controlrow = mysqli_fetch_array($controlquery);
    $conteudouser = isset($_GET['conteudo']) ? $_GET['conteudo'] : "";
    $conteudouser = ($conteudouser != "") ? "<font color=\"brown\"><center>".strip_tags($conteudouser)."</center></font><br />" : "";
    $page = gettemplate("login");
    $title = t("Entrar");
    display($page, $title, false, false, false, false);

}


function logout() { // Sai do jogo. Sair no meio de uma luta conta como derrota (ver abaixo).

    // Luta contra monstro: chance (control.mortelutachance) de contar como morte, senão a luta é cancelada (fight.php ::
    // morrernaluta). Duelo: sempre desistência (perde, inclusive a aposta). Só vale para a navegação de verdade
    // (Sec-Fetch-Dest "document" ou navegador sem esse cabeçalho): uma <img src="login.php?do=logout"> num post
    // não consegue derrubar a luta de outro jogador.
    $destino = isset($_SERVER["HTTP_SEC_FETCH_DEST"]) ? (string)$_SERVER["HTTP_SEC_FETCH_DEST"] : "document";
    $userrow = ($destino == "document") ? checkcookies() : false;
    if ($userrow) {
        global $controlrow;
        $controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
        if ($userrow["currentaction"] == "Fighting") {
            $GLOBALS["userrow"] = $userrow;
            include_once('fight.php');
            morrernaluta(intval($userrow["id"]), "logout");
        }
        if (intval($userrow["batalha_timer2"]) == 5 || $userrow["currentaction"] == "Dueling") {
            if (!defined('USERS_SOMENTE_FUNCOES')) { define('USERS_SOMENTE_FUNCOES', true); }
            include_once('users.php');
            dueloforcarderrota(intval($userrow["id"]));
        }
    }
    // Libera a vaga do IP (limite de contas por IP). Só com o cookie válido de verdade (não depende do Sec-Fetch-Dest).
    $dono = ($userrow) ? $userrow : checkcookies();
    if ($dono) { doquery("UPDATE {{table}} SET loginip='' WHERE id='".intval($dono["id"])."' LIMIT 1", "users"); }
    apagarcookie();
    include_once('funcoesconvidado.php');
    convidado_sair(); // sair também esquece o convidado (o personagem some no prazo)
    header("Location: login.php?do=login");
    die();

}

?>
