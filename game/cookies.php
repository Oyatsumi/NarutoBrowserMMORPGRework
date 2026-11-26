<?php // cookies.php :: Valida o cookie de login e devolve a linha do jogador logado.

function checkcookies() {

    global $dbsettings;
    include_once('config.php');

    $row = false;

    if (isset($_COOKIE[$dbsettings["cookiename"]]) && $_COOKIE[$dbsettings["cookiename"]] != "") {

        // FORMATO DO COOKIE:
        // {ID} {USERNAME} {ASSINATURA HMAC} {LEMBRAR-ME}
        $theuser = explode(" ", $_COOKIE[$dbsettings["cookiename"]]);
        if (count($theuser) != 4) { apagarcookie(); return false; }
        $theuser[0] = intval($theuser[0]);
        $query = doquery("SELECT * FROM {{table}} WHERE id='".$theuser[0]."' LIMIT 1", "users");
        if (mysqli_num_rows($query) != 1) { apagarcookie(); return false; }
        $row = mysqli_fetch_array($query);
        // A assinatura depende do hash da senha: trocar a senha invalida todos os cookies antigos.
        if ($row["username"] !== stripslashes($theuser[1]) || !hash_equals(cookie_assinatura($row["id"], $row["username"], $row["password"]), (string)$theuser[2])) {
            apagarcookie(); return false;
        }

        // Cookie válido: renova a validade e atualiza o horário online do jogador.
        $newcookie = $row["id"] . " " . $row["username"] . " " . $theuser[2] . " " . ($theuser[3] == 1 ? 1 : 0);
        if ($theuser[3] == 1) { $expiretime = time()+31536000; } else { $expiretime = 0; }
        gravarcookie($newcookie, $expiretime);
        $onlinequery = doquery("UPDATE {{table}} SET onlinetime=NOW() WHERE id='".$row["id"]."' LIMIT 1", "users");

    }

    // Sem cookie de login: jogador convidado (jogar sem conta), identificado só pela sessão PHP (funcoesconvidado.php).
    include_once('funcoesconvidado.php');
    if ($row == false) {
        $row = convidado_linha();
        // Página principal aberta sem estar logado: já entra como convidado (respeita robôs, limite por IP etc.).
        if ($row == false && convidado_podeautocriar()) { $row = convidado_criar(); }
    }

    if ($row) {

        // Drop pendente só vale na mesma coordenada em que caiu: andou, viajou, teleportou ou voltou à vila, o drop se perde.
        if (intval($row["dropcode"]) != 0 && (intval($row["latitude"]) != intval($row["droplat"]) || intval($row["longitude"]) != intval($row["droplon"]))) {
            doquery("UPDATE {{table}} SET dropcode='0', dropvariante='' WHERE id='".$row["id"]."' AND dropcode='".intval($row["dropcode"])."' LIMIT 1", "users");
            $row["dropcode"] = 0; $row["dropvariante"] = "";
        }

        // Banimento com prazo (ex.: banimento automático da caveira vermelha, users.banidoate em UNIX): acabou o prazo, volta a jogar.
        if (intval($row["authlevel"]) == 2 && intval($row["banidoate"]) > 0 && intval($row["banidoate"]) <= time()) {
            doquery("UPDATE {{table}} SET authlevel='0', banidoate='0' WHERE id='".$row["id"]."' AND authlevel='2' LIMIT 1", "users");
            $row["authlevel"] = 0; $row["banidoate"] = 0;
        }

        // Senha provisória criada por um GM/administrador (users.trocarsenha = 1): só a tela de troca de senha funciona.
        if (!empty($row["trocarsenha"])) {
            $script = basename(isset($_SERVER["SCRIPT_NAME"]) ? $_SERVER["SCRIPT_NAME"] : "");
            $rota = (isset($_GET["do"]) && is_string($_GET["do"])) ? $_GET["do"] : "";
            if (!($script == "users.php" && $rota == "changepassword") && $script != "login.php" && !headers_sent()) {
                header("Location: users.php?do=changepassword&conteudo=".rawurlencode(t("Sua senha foi redefinida pela equipe do jogo. Escolha uma senha nova para continuar (use a senha provisória como senha antiga).")));
                die();
            }
        }

        convidado_aplicarbloqueio($row); // restrições do convidado (troca, PvP, clã, doações, chats...)

    }

    return $row;

}

?>
