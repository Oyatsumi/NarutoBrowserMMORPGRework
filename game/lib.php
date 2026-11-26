<?php // lib.php :: Funções comuns usadas em todo o jogo (banco de dados, templates, segurança e montagem da página).

// Constante usada pelos arquivos que só podem ser incluídos (nunca acessados diretamente pelo navegador).
if (!defined('NARUTO_RPG')) { define('NARUTO_RPG', true); }

$starttime = getmicrotime();
$numqueries = 0;
$version = "5.0";
$build = "";

// Configurações de exibição de erros: em produção os erros vão apenas para o log do servidor.
include_once('config.php');
if (!empty($dbsettings["debug"])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
date_default_timezone_set(!empty($dbsettings["timezone"]) ? $dbsettings["timezone"] : "America/Sao_Paulo");

// Tratamento global das entradas do usuário (padrão do jogo desde a primeira versão):
// tudo que chega por GET/POST/COOKIE recebe addslashes (proteção das queries entre aspas)
// e htmlspecialchars (proteção contra XSS ao reexibir o texto na página).
// Valores numéricos devem SEMPRE passar por intval()/floor() antes de ir para a query.
$_POST = array_map('addslashes_deep', $_POST);
$_POST = array_map('html_deep', $_POST);
$_GET = array_map('addslashes_deep', $_GET);
$_GET = array_map('html_deep', $_GET);
$_COOKIE = array_map('addslashes_deep', $_COOKIE);
$_COOKIE = array_map('html_deep', $_COOKIE);

/* ------------------------------------------------------------------------
   Idiomas (português e inglês).
   O código continua escrito em português: o texto em português É a chave da tradução.
   Para traduzir uma frase basta envolver com t("Frase em português") ou, quando há valores
   no meio, tf("Você ganhou %s de experiência.", $exp). As traduções ficam em lang/en/*.php
   (cada arquivo devolve um array "português" => "english"). Sem tradução, o texto sai em português.
   O idioma vem de ?idioma=xx (grava um cookie), do cookie "idioma" ou do padrão do jogo (tabela control).
   ------------------------------------------------------------------------ */

$idiomasdisponiveis = array("pt" => "Português", "en" => "English");
$idiomaescolhido = "";
if (isset($_GET["idioma"]) && is_string($_GET["idioma"]) && isset($idiomasdisponiveis[$_GET["idioma"]])) {
    $idiomaescolhido = $_GET["idioma"];
    if (!headers_sent()) { setcookie("idioma", $idiomaescolhido, array("expires" => time()+31536000, "path" => "/", "httponly" => true, "samesite" => "Lax")); }
} elseif (isset($_COOKIE["idioma"]) && is_string($_COOKIE["idioma"]) && isset($idiomasdisponiveis[$_COOKIE["idioma"]])) {
    $idiomaescolhido = $_COOKIE["idioma"];
}

function idiomasativos() { // Idiomas ligados no painel (control.idiomasativos, ex. "pt,en"); o idioma padrão está sempre ligado.

    global $controlrow, $idiomasdisponiveis;
    static $ativos = null;
    if ($ativos !== null) { return $ativos; }
    if (!isset($controlrow) || !is_array($controlrow)) {
        $controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
    }
    $lista = (is_array($controlrow) && isset($controlrow["idiomasativos"])) ? explode(",", $controlrow["idiomasativos"]) : array_keys($idiomasdisponiveis);
    $padrao = (is_array($controlrow) && isset($controlrow["idiomapadrao"]) && isset($idiomasdisponiveis[$controlrow["idiomapadrao"]])) ? $controlrow["idiomapadrao"] : "pt";
    $ativos = array();
    foreach ($idiomasdisponiveis as $codigo => $nome) { if ($codigo == $padrao || in_array($codigo, $lista)) { $ativos[$codigo] = $nome; } }
    return $ativos;

}

function ipdobrasil($ip) { // true = IP do Brasil, false = de fora, null = não dá para saber (IP local/privado ou sem dados).
    // Faixas em dados/ip_br4.bin e dados/ip_br6.bin (tools/gerar_ip_brasil.py): pares ordenados início/fim, busca binária no arquivo.
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return null; }
    $bin = @inet_pton($ip); if ($bin === false) { return null; }
    $v6 = (strlen($bin) == 16);
    $arq = __DIR__."/dados/".($v6 ? "ip_br6.bin" : "ip_br4.bin");
    $tam = $v6 ? 16 : 4;
    $fh = @fopen($arq, "rb"); if (!$fh) { return null; }
    $n = intval(filesize($arq) / (2 * $tam)); $lo = 0; $hi = $n - 1; $achou = false;
    while ($lo <= $hi) { // strcmp em binário big-endian = comparação numérica
        $meio = ($lo + $hi) >> 1; fseek($fh, $meio * 2 * $tam); $par = fread($fh, 2 * $tam);
        $ini = substr($par, 0, $tam); $fim = substr($par, $tam, $tam);
        if (strcmp($bin, $ini) < 0) { $hi = $meio - 1; } elseif (strcmp($bin, $fim) > 0) { $lo = $meio + 1; } else { $achou = true; break; }
    }
    fclose($fh);
    return $achou;
}

function idioma() { // Código do idioma em uso ("pt" ou "en").

    global $idiomaescolhido, $controlrow, $idiomasdisponiveis;
    if ($idiomaescolhido != "" && isset(idiomasativos()[$idiomaescolhido])) { return $idiomaescolhido; }
    // Sem escolha (ou o idioma escolhido foi desativado no painel) do jogador: usa o padrão configurado no Painel do Administrador.
    if (!isset($controlrow) || !is_array($controlrow)) {
        $controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
        $controlrow = mysqli_fetch_array($controlquery);
    }
    $padrao = (is_array($controlrow) && isset($controlrow["idiomapadrao"])) ? $controlrow["idiomapadrao"] : "pt";
    // Detecção automática pelo IP (control.idiomaporip, padrão ligado): Brasil = português, fora do Brasil = inglês.
    // Só vale enquanto o jogador não escolheu um idioma (o cookie "idioma" da escolha manual sempre tem prioridade).
    // O resultado fica no cookie "idiomaip" para não consultar as faixas de IP a cada página.
    if (!is_array($controlrow) || !isset($controlrow["idiomaporip"]) || intval($controlrow["idiomaporip"]) == 1) {
        $auto = (isset($_COOKIE["idiomaip"]) && in_array($_COOKIE["idiomaip"], array("pt", "en"), true)) ? $_COOKIE["idiomaip"] : "";
        if ($auto == "") {
            $br = ipdobrasil(isset($_SERVER["REMOTE_ADDR"]) ? (string)$_SERVER["REMOTE_ADDR"] : "");
            if ($br === null) {
                $auto = "en"; // IP local/privado (ex.: 127.0.0.1, 192.168.x.x) ou desconhecido: inglês.
            } else {
                $auto = $br ? "pt" : "en";
                if (!headers_sent()) { setcookie("idiomaip", $auto, array("expires" => time()+2592000, "path" => "/", "httponly" => true, "samesite" => "Lax")); }
            }
        }
        if ($auto != "" && isset(idiomasativos()[$auto])) { $idiomaescolhido = $auto; return $idiomaescolhido; }
    }
    $idiomaescolhido = isset($idiomasdisponiveis[$padrao]) ? $padrao : "pt";
    return $idiomaescolhido;

}

function traducoes() { // Carrega (uma vez) todas as traduções do idioma atual.

    static $carregadas = array();
    $codigo = idioma();
    if (!isset($carregadas[$codigo])) {
        $carregadas[$codigo] = array();
        foreach (glob(__DIR__ . "/lang/" . $codigo . "/*.php") ?: array() as $arquivo) {
            $lista = include($arquivo);
            if (is_array($lista)) { $carregadas[$codigo] = array_merge($carregadas[$codigo], $lista); }
        }
    }
    return $carregadas[$codigo];

}

function t($texto) { // Traduz um texto escrito em português para o idioma atual.

    if (idioma() == "pt" || $texto === "" || $texto === null) { return $texto; }
    $lista = traducoes();
    $chave = (string)$texto;
    if (isset($lista[$chave])) { $traduzido = $lista[$chave]; }
    else {
        // Algumas chaves podem chegar escapadas (vindas do banco ou de formulários): tenta a versão sem escape.
        $semescape = html_entity_decode(stripslashes($chave), ENT_QUOTES, "UTF-8");
        if (isset($lista[$semescape])) { $traduzido = $lista[$semescape]; }
        elseif (preg_match('/^(.+?)( ?\+[0-9]+)$/u', $semescape, $refino) && isset($lista[$refino[1]])) { $traduzido = $lista[$refino[1]] . $refino[2]; } // Itens refinados ("Kunai +2").
        elseif (preg_match('/^(.+?)(:[a-z]+)$/u', $semescape, $variante)) { return t($variante[1]) . $variante[2]; } // Variante rara ("Kunai:dourado"): traduz o nome e mantém o sufixo.
        else {
            global $dbsettings;
            if (!empty($dbsettings["debug"])) { error_log("[traducao-faltando] " . $chave); }
            return $texto;
        }
    }
    // Apóstrofos do inglês (don't, player's) viram ’ para não quebrar strings JavaScript e atributos HTML.
    return preg_replace("/(?<=\p{L})'(?=\p{L})/u", "’", $traduzido);

}

function tf($texto) { // Igual ao t(), mas com valores no lugar dos %s (estilo sprintf). Use %% para um % literal.

    $argumentos = func_get_args();
    array_shift($argumentos);
    return vsprintf(t($texto), $argumentos);

}

function textoi18n($texto) { // Exibe mensagens gravadas no banco no formato neutro "i18n:Chave com %s|valor1|valor2".

    $texto = (string)$texto;
    if (strpos($texto, "i18n:") !== 0) { return $texto; }
    $partes = explode("|", substr($texto, 5));
    $chave = array_shift($partes);
    // Valores que também têm tradução (nomes de itens, vilas...) são traduzidos; nomes de jogadores ficam como estão.
    if (idioma() != "pt") {
        $lista = traducoes();
        foreach ($partes as $k => $v) {
            $limpo = html_entity_decode(stripslashes($v), ENT_QUOTES, "UTF-8");
            if (isset($lista[$v])) { $partes[$k] = $lista[$v]; } elseif (isset($lista[$limpo])) { $partes[$k] = $lista[$limpo]; }
        }
    }
    // Avisos de elemento (elementofrase()): ícone e cor de cada elemento, e a frase colorida (vantagem em vermelho, redução em azul).
    $ehelemento = ($chave == "%s vence %s: +%s%% de dano.") ? "#b03a1e" : (($chave == "%s perde para %s: -%s%% de dano." || $chave == "%s resiste a %s: -%s%% de dano.") ? "#1f5f9e" : "");
    if ($ehelemento != "") {
        $cores = array("Neutro"=>array("neutro", "#6b5b45"), "Fogo"=>array("fogo", "#d35400"), "Água"=>array("agua", "#1f6fb8"), "Vento"=>array("vento", "#2e8b57"), "Raio"=>array("raio", "#b8860b"), "Terra"=>array("terra", "#8e5b2b"));
        foreach (array(0, 1) as $k) {
            $orig = isset($partes[$k]) ? $partes[$k] : "";
            $pt = array_search($orig, array_map(function ($n) { return t($n); }, array_combine(array_keys($cores), array_keys($cores))));
            if ($pt === false && isset($cores[$orig])) { $pt = $orig; }
            if ($pt !== false && isset($cores[$pt])) {
                $partes[$k] = "<img src=\"images/".$cores[$pt][0].".gif\" width=\"14\" height=\"14\" alt=\"\" style=\"vertical-align:-2px\"> <b style=\"color:".$cores[$pt][1]."\">".htmlspecialchars($orig, ENT_QUOTES, "UTF-8")."</b>";
            }
        }
        $resultado = @vsprintf(t($chave), $partes);
        return "<span class=\"avisoelemento\" style=\"color:$ehelemento\">".(($resultado === false) ? $chave : $resultado)."</span>";
    }
    $resultado = @vsprintf(t($chave), $partes);
    return ($resultado === false) ? $chave : $resultado;

}

function i18n($chave) { // Monta o texto neutro para gravar no banco: i18n("Você recebeu %s de %s.", $item, $nome).

    $argumentos = func_get_args();
    array_shift($argumentos);
    $argumentos = array_map(function ($v) { return str_replace("|", "/", (string)$v); }, $argumentos);
    return "i18n:" . $chave . (count($argumentos) ? "|" . implode("|", $argumentos) : "");

}

function linkidioma($codigo) { // Link para trocar o idioma mantendo o jogador na mesma tela (sem repetir ações).

    $pagina = basename(isset($_SERVER["SCRIPT_NAME"]) ? $_SERVER["SCRIPT_NAME"] : "index.php");
    if (!preg_match("/^[a-z0-9_]+\.php$/i", $pagina)) { $pagina = "index.php"; }
    $parametros = array();
    parse_str(isset($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : "", $parametros);
    $manter = array();
    $telas = array("login","register","lostpassword","changepassword","verify","banco","troca","quests","treinamento","missao","graduacao","fundir","jutsu","atributos","avatar","ler","msg","inn","buy","maps","fight","drop","showchar","showmap","babblebox");
    if (isset($parametros["do"]) && is_string($parametros["do"]) && ($pagina == "admin.php" || in_array($parametros["do"], $telas))) { $manter["do"] = $parametros["do"]; }
    foreach (array("nomechar", "ord") as $campo) { if (isset($parametros[$campo]) && is_string($parametros[$campo])) { $manter[$campo] = $parametros[$campo]; } }
    $manter["idioma"] = $codigo;
    return htmlspecialchars($pagina . "?" . http_build_query($manter), ENT_QUOTES, "UTF-8");

}

function seletoridioma() { // Links "Português | English" usados no rodapé, na tela de login e no painel.

    $links = array();
    if (count(idiomasativos()) < 2) { return ""; } // um só idioma ligado: nada para escolher
    foreach (idiomasativos() as $codigo => $nome) {
        if (is_file(__DIR__."/images/bandeiras/$codigo.png")) { $nome = "<img src=\"images/bandeiras/$codigo.png\" width=\"16\" height=\"11\" alt=\"\" style=\"vertical-align:-1px;margin-right:3px;border:1px solid #8a7a5a\">$nome"; }
        if ($codigo == idioma()) { $links[] = "<b>$nome</b>"; }
        else { $links[] = "<a href=\"".linkidioma($codigo)."\" lang=\"$codigo\">$nome</a>"; }
    }
    return implode(" | ", $links);

}

function localizarimagens($pagina) { // Troca imagens com texto em português pela versão do idioma (images/en/..., layoutnovo/.../en/...).

    // Animações dos avatares: versão na URL para o navegador não usar a cópia antiga em cache depois de ajustes de velocidade.
    $pagina = preg_replace('#(layoutnovo/graficos/[A-Za-z0-9_]+\.gif)(?![?A-Za-z0-9_])#', '$1?v=5', $pagina);

    if (idioma() == "pt") { return $pagina; }
    static $mapa = null;
    if ($mapa === null) {
        $mapa = array();
        foreach (array("images", "layoutnovo/buttons", "layoutnovo/menuslados", "layoutnovo/titulo", "images/24") as $pasta) {
            foreach (glob(__DIR__ . "/" . $pasta . "/" . idioma() . "/*") ?: array() as $arquivo) {
                $nome = basename($arquivo);
                $mapa[$pasta . "/" . $nome] = $pasta . "/" . idioma() . "/" . $nome;
            }
        }
    }
    if (count($mapa) == 0) { return $pagina; }
    // Só troca caminhos completos (entre aspas, parênteses ou após "url("), nunca pedaços de outros nomes.
    return preg_replace_callback('#(?<=["\'(=])(' . implode("|", array_map("preg_quote", array_keys($mapa))) . ')(?=["\')\s?])#', function ($m) use ($mapa) { return $mapa[$m[1]]; }, $pagina);

}

function stripslashes_deep($value) {

   $value = is_array($value) ?
               array_map('stripslashes_deep', $value) :
               stripslashes((string)$value);
   return $value;

}

function addslashes_deep($value) {

   $value = is_array($value) ?
               array_map('addslashes_deep', $value) :
               addslashes((string)$value);
   return $value;

}

function html_deep($value) {

   $value = is_array($value) ?
               array_map('html_deep', $value) :
               htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   return $value;

}

function opendb() { // Abre a conexão com o banco de dados (uma única vez por requisição).

    global $link, $dbsettings;
    if (isset($link) && $link instanceof mysqli) { return $link; }
    include_once('config.php');
    mysqli_report(MYSQLI_REPORT_OFF);
    $link = @mysqli_connect($dbsettings["server"], $dbsettings["user"], $dbsettings["pass"], $dbsettings["name"]);
    if (!$link) {
        error_log("Naruto RPG: falha ao conectar no banco: " . mysqli_connect_error());
        die(t("Não foi possível conectar ao banco de dados. Tente novamente em alguns instantes."));
    }
    mysqli_set_charset($link, "utf8mb4");
    // Desliga o modo estrito (MySQL 5.7+/8 e MariaDB 10.2+ vêm com STRICT_TRANS_TABLES): o código antigo grava
    // textos vazios em colunas numéricas e datas zeradas, que no modo estrito viram erro.
    @mysqli_query($link, "SET SESSION sql_mode = ''");
    return $link;

}

function doquery($query, $table) { // Pequena camada de abstração do banco: troca {{table}} pelo nome real da tabela (prefixo_tabela).

    global $numqueries, $link, $dbsettings;
    if (!isset($link) || !($link instanceof mysqli)) { opendb(); }
    $sql = str_replace("{{table}}", $dbsettings["prefix"] . "_" . $table, $query);
    $sqlquery = mysqli_query($link, $sql);
    if ($sqlquery === false) {
        error_log("Naruto RPG: erro SQL [" . mysqli_error($link) . "] na query: " . $sql);
        if (!empty($dbsettings["debug"])) { die("Erro SQL: " . htmlspecialchars(mysqli_error($link)) . "<br />" . htmlspecialchars($sql)); }
        die(t("Ocorreu um erro ao acessar o banco de dados. A equipe foi notificada pelo log do servidor."));
    }
    $numqueries++;
    return $sqlquery;

}

function gettemplate($templatename) { // Carrega um template da pasta templates/ (somente nomes simples, sem caminhos).

    $templatename = preg_replace("/[^a-z0-9_]/i", "", $templatename);
    $filename = "templates/" . $templatename . ".php";
    $template = "";
    include($filename);
    return $template;

}

function parsetemplate($template, $array) { // Substitui as marcações {{chave}} do template pelos valores do array.

    if (!headers_sent()) { header("Content-Type: text/html; charset=UTF-8", true); }
    if (!is_array($array)) { return $template; }
    foreach($array as $a => $b) {
        if (is_array($b)) { continue; }
        $template = str_replace("{{{$a}}}", (string)$b, $template);
    }
    return $template;

}

if (!function_exists("iconeryou")) {
function iconeryou() { // Ícone único do Ryou (o mesmo do banco), posto antes do valor de Ryou do jogador em todas as telas.
    return "<img src=\"images/ryou.png\" width=\"18\" height=\"18\" alt=\"\" class=\"icoryou\" style=\"vertical-align:middle;margin-right:2px\">";
}
}

function getmicrotime() { // Usado para medir o tempo de execução da página.

    list($usec, $sec) = explode(" ",microtime());
    return ((float)$usec + (float)$sec);

}

function prettydate($uglydate) { // Converte a data do MySQL (AAAA-MM-DD) para o formato brasileiro.

    $time = strtotime((string)$uglydate);
    if ($time === false) { return $uglydate; }
    return date("d/m/Y", $time);

}

function padraodate($uglydate) { // Converte "AAAA-MM-DD HH:MM:SS" para "DD-MM-AAAA / HH:MM:SS".

    $var1 = explode(' ', (string)$uglydate);
    $var2 = explode('-', $var1[0]);
    if (count($var2) < 3) { return $uglydate; }
    return $var2[2].'-'.$var2[1].'-'.$var2[0].' / '.(isset($var1[1]) ? $var1[1] : '');

}

function prettyforumdate($uglydate) { // Mesma formatação usada nas datas do fórum.

    return prettydate($uglydate);

}

function is_email($email) { // Validação de e-mail usando o filtro nativo do PHP.

    return (filter_var(html_entity_decode((string)$email, ENT_QUOTES, 'UTF-8'), FILTER_VALIDATE_EMAIL) !== false);

}

function makesafe($d) { // Limpa textos curtos de chat (sem tabs, quebras de linha nem tags).

    $d = str_replace("\t","",$d);
    $d = str_replace("<","&#60;",$d);
    $d = str_replace(">","&#62;",$d);
    $d = str_replace("\n","",$d);
    $d = str_replace("\r","",$d);
    $d = str_replace("|","??",$d);
    $d = str_replace("  "," &nbsp;",$d);
    return $d;

}

/* ------------------------------------------------------------------------
   Segurança: senhas, cookie de login e token anti-CSRF.
   ------------------------------------------------------------------------ */

function senha_algoritmo() { // Algoritmo dos hashes: Argon2id quando o PHP tem (password_algos()), senão bcrypt com custo 12. Devolve array(algoritmo, opções).

    if (defined('PASSWORD_ARGON2ID') && in_array(PASSWORD_ARGON2ID, password_algos(), true)) { return array(PASSWORD_ARGON2ID, array()); }
    return array(PASSWORD_BCRYPT, array("cost" => 12));

}

function senha_hash($senha) { // Gera o hash seguro (Argon2id ou bcrypt 12) da senha. Também usado nas respostas secretas.

    $alg = senha_algoritmo();
    return password_hash((string)$senha, $alg[0], $alg[1]);

}

function senha_confere($senha, $hash) { // Confere a senha; aceita hashes MD5 antigos (contas importadas de versões anteriores).

    $hash = (string)$hash;
    if (strlen($hash) == 32 && ctype_xdigit($hash)) { return hash_equals($hash, md5((string)$senha)); }
    return password_verify((string)$senha, $hash);

}

function senha_precisa_atualizar($hash) { // Indica se o hash deve ser refeito (MD5 antigo ou algoritmo desatualizado).

    $hash = (string)$hash;
    if (strlen($hash) == 32 && ctype_xdigit($hash)) { return true; }
    $alg = senha_algoritmo();
    return password_needs_rehash($hash, $alg[0], $alg[1]); // bcrypt antigo (custo 10) vira Argon2id/bcrypt 12 no próximo login.

}

function cookie_assinatura($id, $username, $passwordhash) { // Assinatura HMAC do cookie de login (muda sempre que a senha muda).

    global $dbsettings;
    return hash_hmac('sha256', $id . "|" . $username . "|" . $passwordhash, $dbsettings["secretword"]);

}

function gravarcookie($valor, $expiretime) { // Grava o cookie de login com as flags de segurança (HttpOnly / SameSite / Secure).

    global $dbsettings;
    $seguro = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off');
    setcookie($dbsettings["cookiename"], $valor, array(
        "expires" => $expiretime,
        "path" => "/",
        "secure" => $seguro,
        "httponly" => true,
        "samesite" => "Lax"));

}

function apagarcookie() { // Remove o cookie de login.

    gravarcookie("", time() - 100000);

}

function csrf_token() { // Token anti-CSRF ligado ao jogador logado (não precisa de sessão no servidor).

    global $userrow, $dbsettings;
    if (empty($userrow) || !is_array($userrow)) { return ""; }
    return hash_hmac('sha256', "csrf|" . $userrow["id"] . "|" . $userrow["password"], $dbsettings["secretword"]);

}

function csrf_campo() { // Campo hidden pronto para ser colocado dentro de um <form>.

    return "<input type=\"hidden\" name=\"csrf\" value=\"" . csrf_token() . "\" />";

}

function csrf_confere() { // Valida o token enviado pelo formulário (POST) ou pela URL (GET).

    $enviado = "";
    if (isset($_POST["csrf"])) { $enviado = $_POST["csrf"]; } elseif (isset($_GET["csrf"])) { $enviado = $_GET["csrf"]; }
    $esperado = csrf_token();
    return ($esperado != "" && is_string($enviado) && hash_equals($esperado, $enviado));

}

function pegarint($array, $chave, $padrao = 0) { // Lê um inteiro de $_GET/$_POST com segurança.

    if (!isset($array[$chave]) || is_array($array[$chave])) { return $padrao; }
    return (is_numeric($array[$chave])) ? (int)floor((float)$array[$chave]) : $padrao;

}

// Mensagens na URL (?conteudo=..., ?msg=..., ?frase=...): o jogo redireciona com o texto na URL, então qualquer um poderia montar um link
// com um texto falso ("o GM pediu sua senha..."). Por isso toda URL de redirecionamento do jogo leva uma assinatura
// (ms_conteudo / ms_msg = HMAC do texto com o secretword) e lermsg() só mostra o texto se a assinatura conferir.
function msgassinatura($chave, $valor) {
    global $dbsettings;
    return substr(hash_hmac('sha256', "msgurl|".$chave."|".$valor, isset($dbsettings["secretword"]) ? $dbsettings["secretword"] : ""), 0, 20);
}
function msgassinarurl($url) { // Acrescenta ms_<param> para conteudo/msg presentes na URL (o texto já vem com rawurlencode).
    $q = strpos($url, "?"); if ($q === false) { return $url; }
    $frag = ""; $h = strpos($url, "#"); if ($h !== false) { $frag = substr($url, $h); $url = substr($url, 0, $h); }
    $partes = explode("&", substr($url, $q + 1)); $extra = "";
    foreach ($partes as $p) {
        $kv = explode("=", $p, 2);
        $k = urldecode($kv[0]);
        if (in_array($k, array("conteudo", "msg", "frase"), true) && isset($kv[1]) && $kv[1] !== "") { $extra .= "&ms_".$k."=".msgassinatura($k, urldecode($kv[1])); }
        if (in_array($k, array("ms_conteudo", "ms_msg", "ms_frase"), true)) { return $url.$frag; } // já assinada
    }
    return $url.$extra.$frag;
}
function lermsg($chave) { // Texto de ?conteudo=/?msg= só se veio de um redirecionamento do próprio jogo (assinado); senão "".
    if (!isset($_GET[$chave]) || is_array($_GET[$chave]) || $_GET[$chave] === "") { return ""; }
    $bruto = array(); parse_str(isset($_SERVER["QUERY_STRING"]) ? $_SERVER["QUERY_STRING"] : "", $bruto);
    if (!isset($bruto[$chave]) || is_array($bruto[$chave]) || !isset($bruto["ms_".$chave]) || is_array($bruto["ms_".$chave])) { return ""; }
    if (!hash_equals(msgassinatura($chave, (string)$bruto[$chave]), (string)$bruto["ms_".$chave])) { return ""; }
    return (string)$_GET[$chave]; // versão já escapada pelo tratamento global de entradas
}
// Todo header("Location: ...") do jogo sai com a assinatura (sem precisar mexer em cada redirecionamento).
if (function_exists('header_register_callback')) {
    header_register_callback(function () {
        foreach (headers_list() as $hd) {
            if (stripos($hd, "Location:") === 0 && preg_match('/[?&](conteudo|msg|frase)=/', $hd)) {
                header("Location: ".msgassinarurl(trim(substr($hd, 9))), true);
            }
        }
    });
}

function pegartexto($array, $chave, $padrao = "") { // Lê um texto de $_GET/$_POST (já tratado pelo addslashes/htmlspecialchars global).

    if (!isset($array[$chave]) || is_array($array[$chave])) { return $padrao; }
    return (string)$array[$chave];

}

function cabecalhosseguranca() { // Cabeçalhos HTTP de segurança enviados em todas as páginas.

    if (headers_sent()) { return; }
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: same-origin");

}

/* ------------------------------------------------------------------------
   Cargos da equipe, conta silenciada e e-mail.
   - users.authlevel: 0 = jogador, 1 = Administrador, 2 = banido (valor histórico, não muda).
   - users.acesso: 0 = comum (1 era VIP, removido na 5.0), 2 = Tutor (nome laranja), 3 = GameMaster (nome azul).
   Um GM é uma conta com acesso = 3 que não é administrador nem banida: ele abre o gm.php (painel limitado).
   ------------------------------------------------------------------------ */

function ehgm($row) { // true se a conta é um GameMaster (acesso 3, authlevel 0).

    return is_array($row) && intval($row["authlevel"]) == 0 && intval($row["acesso"]) == 3;

}

function selogm() { // Selo dourado "GM" (images/gm_badge.gif) mostrado antes do nome de um GameMaster em listas e chats.

    return "<img src=\"images/gm_badge.gif\" width=\"14\" height=\"14\" alt=\"GM\" title=\"".t("GameMaster")."\" style=\"vertical-align:middle;margin-right:2px\" />";

}

function selovila($alinhamento) { // Emblema da vila (images/vila_ALINHAMENTO.gif, 20x17, arte original de tools/icones_vilas.py); sem arquivo: vila_neutro.gif.

    $vila = preg_replace("/[^a-z0-9_]/", "", (string)$alinhamento);
    if (!function_exists("nomedoalinhamento")) { include_once(__DIR__."/cidadesconteudo.php"); }
    $nome = ($vila == "") ? t("Sem vila") : (function_exists("nomedoalinhamento") ? nomedoalinhamento($vila) : ucfirst($vila));
    $arquivo = ($vila != "" && file_exists(__DIR__."/images/vila_".$vila.".gif")) ? "vila_".$vila.".gif" : "vila_neutro.gif";
    return "<img src=\"images/".$arquivo."\" width=\"20\" height=\"17\" alt=\"\" title=\"".htmlspecialchars($nome, ENT_QUOTES, "UTF-8")."\" style=\"vertical-align:middle\" />";

}

function caveiraativa($row) { // Caveira do PvP ainda valendo: "branca", "vermelha" ou "" (users.caveira + users.caveiraate em UNIX; expira sozinha).

    if (!is_array($row) || !isset($row["caveira"]) || $row["caveira"] == "") { return ""; }
    return (intval($row["caveiraate"]) > time() && in_array($row["caveira"], array("branca", "vermelha"), true)) ? $row["caveira"] : "";

}

function selocaveira($row) { // Ícone da caveira (images/caveira_branca.gif / caveira_vermelha.gif) para mostrar ao lado do nome, ou "".

    $c = caveiraativa($row);
    if ($c == "") { return ""; }
    $titulo = ($c == "branca") ? t("Caveira branca: matou outro jogador recentemente") : t("Caveira vermelha: assassino (perde todos os itens se morrer)");
    return "<img src=\"images/caveira_".$c.".gif\" width=\"12\" height=\"12\" alt=\"\" title=\"".$titulo."\" style=\"vertical-align:middle;margin-right:2px\" />";

}

function caveiraminutos() { // Minutos da "caveira exposta" depois de uma morte injusta (control.pvpcaveiraminutos, padrão 30; 0 = desligado ou PvP desligado).

    global $controlrow;
    if (empty($controlrow["pvpmundo"])) { return 0; }
    return isset($controlrow["pvpcaveiraminutos"]) ? max(0, intval($controlrow["pvpcaveiraminutos"])) : 30;

}

function caveiraexposta($row) { // Segundos que faltam da caveira exposta (0 = não está): quem tem caveira e matou há menos de caveiraminutos()
    // continua atacável na última casa do mapa mesmo offline (pvp.php) e não entra em vilas (bloqueiovila()). users.pvpmatouem = UNIX.

    if (caveiraativa($row) == "" || empty($row["pvpmatouem"])) { return 0; }
    return max(0, intval($row["pvpmatouem"]) + 60 * caveiraminutos() - time());

}

function sqlcaveiraexposta() { // A mesma regra de caveiraexposta() como condição SQL (listas do mapa / alvos do PvP).

    $m = caveiraminutos();
    if ($m <= 0) { return "0"; }
    return "(caveira IN ('branca','vermelha') AND caveiraate > UNIX_TIMESTAMP() AND pvpmatouem > UNIX_TIMESTAMP() - ".(60 * $m).")";

}

function bloqueiovila($row) { // "" ou a mensagem (traduzida) que impede quem está com a caveira exposta de entrar numa vila.
    // Vale para andar até a vila, Viajar Para, Pergaminho de Retorno/Teleporte e qualquer teleporte para vila. A morte NÃO é
    // barrada: o derrotado sempre volta para a vila natal (senão ficaria preso no mapa sem HP). Ver docs/PVP.md.

    $s = caveiraexposta($row);
    if ($s <= 0) { return ""; }
    return tf("Você está com caveira e matou um jogador há pouco: os portões das vilas estão fechados para você por mais %s minuto(s).", (int)ceil($s / 60));

}

function segundosmudo($row) { // Segundos que faltam para o jogador poder falar de novo (0 = não está silenciado). users.mudoate = UNIX.

    if (!is_array($row) || !isset($row["mudoate"])) { return 0; }
    return max(0, intval($row["mudoate"]) - time());

}

function avisomudo($row) { // Texto "Você foi silenciado por um GM..." (ou "" se pode falar).

    $s = segundosmudo($row);
    return ($s > 0) ? tf("Você foi silenciado pela equipe do jogo e não pode enviar mensagens por mais %s minuto(s).", ceil($s / 60)) : "";

}

function verificaremail() { // O cadastro exige confirmar o e-mail? Só com os e-mails ligados (control.emailativo) e a verificação ligada.

    global $controlrow;
    return !empty($controlrow["emailativo"]) && !empty($controlrow["verifyemail"]);

}

function nomedaclasse($numero) { // Nome da classe do personagem (Ninjutsu/Taijutsu/Genjutsu) a partir do número.

    global $controlrow;
    $numero = (int)$numero;
    if ($numero >= 1 && $numero <= 3) { return t($controlrow["class".$numero."name"]); }
    return "";

}

function admindisplay($content, $title) { // Monta e envia a página do painel administrativo.

    global $numqueries, $userrow, $controlrow, $starttime, $version, $build;
    if (!isset($controlrow)) {
        $controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
        $controlrow = mysqli_fetch_array($controlquery);
    }

    $template = gettemplate("admin");

    $xml = "<!DOCTYPE html>\n<html lang=\"".(idioma() == "pt" ? "pt-br" : idioma())."\">\n";

    $finalarray = array(
        "title"=>$title,
        "content"=>$content,
        "gamename"=>$controlrow["gamename"],
        "adminname"=>(isset($userrow["charname"]) ? $userrow["charname"] : ""),
        "totaltime"=>round(getmicrotime() - $starttime, 4),
        "numqueries"=>$numqueries,
        "version"=>$version,
        "build"=>$build);
    cabecalhosseguranca();
    $page = parsetemplate($template, $finalarray);
    $page = localizarimagens($xml . $page);

    if ($controlrow["compression"] == 1 && !ini_get('zlib.output_compression')) { ob_start("ob_gzhandler"); }
    echo $page;
    die();

}

function barradestatus($atual, $maximo, $cor, $titulo) { // Desenha uma das barras verticais (HP, CH, TP, NP, EP) da coluna da direita.

    $porcento = ($maximo > 0) ? ceil($atual / $maximo * 100) : 0;
    if ($porcento < 0) { $porcento = 0; }
    if ($porcento > 100) { $porcento = 100; }
    if ($porcento >= 66) { $nivel = 1; } elseif ($porcento >= 33) { $nivel = 2; } else { $nivel = 3; }
    // O título leva os valores ("Pontos de Vida: 120 / 500") e fica na barra inteira (também na parte vazia): no toque e no mouse.
    $titulo = htmlspecialchars($titulo.": ".intval($atual)." / ".intval($maximo), ENT_QUOTES, "UTF-8");
    $barra = "<table cellspacing=\"0\" cellpadding=\"0\"><tr><td title=\"$titulo\" style=\"padding:0px; width:15px; height:100px; border:solid 1px black; vertical-align:bottom;\">\n";
    $barra .= "<div style=\"padding:0px; height:".$porcento."px; border-top:solid 1px black; background-image:url(images/barra_".$cor.$nivel.".gif);\"><img src=\"images/barra_".$cor.$nivel.".gif\" title=\"$titulo\" alt=\"\" /></div>";
    $barra .= "</td></tr></table>";
    return $barra;

}


function semlinkvoltar($content) { // Com o botão "Voltar ao jogo" na tela, tira as frases que só tinham um link de voltar (ficam repetidas).
    $frases = array("Se você mudou de ideia, pode retornar à <a href=\"index.php\">cidade</a>.", "Se você mudou de ideia, você pode retornar à <a href=\"index.php\">cidade</a>.",
        "Você pode continuar <a href=\"index.php\">explorando</a>.", "<a href=\"index.php\">Voltar à pagina de jogo</a>.");
    foreach ($frases as $f) {
        foreach (array_unique(array(t($f), $f)) as $x) { $content = str_replace(array("<br />".$x, "<br>".$x, $x), "", $content); }
    }
    // Frases com outro link útil: ficam só com ele.
    $trocas = array(
        "Você pode retornar à <a href=\"index.php\">cidade</a>, <a href=\"index.php?do=maps\">shop</a>, ou usar os botões de direção para continuar explorando." => "Você pode voltar à <a href=\"index.php?do=maps\">loja de mapas</a>.",
        "Você pode retornar à <a href=\"index.php\">cidade</a>, <a href=\"index.php?do=buy\">shop</a>, ou usar os botões de direção para continuar explorando." => "Você pode voltar à <a href=\"index.php?do=buy\">loja</a>.");
    foreach ($trocas as $de => $para) { $content = str_replace(array(t($de), $de), t($para), $content); }
    // Ajuda: "[ Voltar à ajuda | Voltar ao jogo ]" fica só com a ajuda.
    $content = str_replace(" | <a href=\"index.php\">".t("Voltar ao jogo")."</a>", "", $content);
    foreach (array("Voltar ao mapa", "Voltar", "Voltar ao jogo") as $r) { $content = str_replace("<a href=\"index.php\">".t($r)."</a>", "", $content); } // links soltos de voltar
    return $content;
}

function comvoltarjogo($content) { // Botão "Voltar ao jogo" (.btnjogo) nas telas de aviso/erro e nas que oferecem "continuar explorando"/"retornar à cidade".
    global $userrow;
    if (empty($userrow) || !is_array($userrow)) { return $content; }
    $script = basename(isset($_SERVER["SCRIPT_NAME"]) ? $_SERVER["SCRIPT_NAME"] : "");
    $acao = explode(":", pegartexto($_GET, "do")); // index.php?do=move:north etc.
    if ($script == "index.php" && in_array($acao[0], array("", "move"))) { return $content; } // já é a tela de jogo
    if (isset($userrow["currentaction"]) && $userrow["currentaction"] == "Exploring" && strpos($content, "id=\"jogadoresmapa\"") !== false) { return $content; } // tela do mapa (explorando)
    if (strpos($content, "id=\"botaovoltarjogo\"") !== false || strpos($content, "id=\"botaoentrarvila\"") !== false || strpos($content, "id=\"botaovoltarvila\"") !== false || strpos($content, ">".t("Voltar ao jogo")."<") !== false) { return (strpos($content, "id=\"botaovoltarjogo\"") !== false) ? semlinkvoltar($content) : $content; }
    if (strpos($script, "admin") === 0) { return $content; } // painéis do administrador têm navegação própria
    // Painéis do menu do jogador que sempre levam o botão (Distribuir Pontos, Missões, Treinos, Clã e Talentos).
    $sempre = array("outroseatributos.php", "treinamentoequests.php", "cla.php", "talentos.php");
    if (in_array($script, $sempre, true)) { $gatilhos = array(""); $achou = true; }
    $gatilhos = array("href=\"index.php\"", t("Você já está nessa cidade."), t("Seu duelo atual foi resetado com sucesso."), t("Tentativa de trapaça detectada."), t("Tentativa de trapaça ou erro detectado."), "trapaça", "cheat");
    if (!isset($achou)) { $achou = false; }
    foreach ($gatilhos as $g) { if ($g != "" && stripos($content, $g) !== false) { $achou = true; break; } }
    if (!$achou) { return $content; }
    return semlinkvoltar($content)."<div style=\"text-align:center;margin:12px 0 6px\"><a href=\"index.php\" id=\"botaovoltarjogo\" class=\"btnjogo bj-neutro\"><img src=\"images/seta_oeste.png\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".t("Voltar ao jogo")."</a></div>";
}

function display($content, $title, $topnav=true, $leftnav=true, $rightnav=true, $badstart=false) { // Monta a página final do jogo e envia para o navegador.

    global $numqueries, $userrow, $controlrow, $version, $build;
    if (!isset($controlrow)) {
        $controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
        $controlrow = mysqli_fetch_array($controlquery);
    }
    if ($badstart == false) { global $starttime; } else { $starttime = $badstart; }
    $content = comvoltarjogo($content);

    // Cabeçalho do documento (mantido em XHTML Transitional: o layout em tabelas foi desenhado para ele).
    $xml = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n"
    . "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"".idioma()."\" lang=\"".idioma()."\">\n";

    $template = gettemplate("primary");

    // As colunas laterais só aparecem para quem está logado.
    if ($userrow == true) { $rightnav = gettemplate("rightnav"); $leftnav = gettemplate("leftnav"); }
    if ($userrow == false) { $topnav = ""; $rightnav = ""; $leftnav = ""; }

    if (isset($userrow) && $userrow != false) {

        // Busca o jogador novamente, pois algo pode ter mudado durante a execução.
        $userquery = doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
        unset($userrow);
        $userrow = mysqli_fetch_array($userquery);

        // Nome da cidade / situação atual.
        if ($userrow["currentaction"] == "In Town") {
            $townquery = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
            $townrow = mysqli_fetch_array($townquery);
            $nomecidade = ($townrow) ? $townrow["name"] : "";
            $userrow["currenttown"] = "<div id=\"naruto\">".tf("Bem-vindo a(o) %s.", t($nomecidade))."</div>";
        } elseif ($userrow["currentaction"] == "Exploring") {
            $userrow["currenttown"] = "<div id=\"naruto\">".t("Você está explorando o mapa.")."</div>";
        } else {
            $userrow["currenttown"] = "<div id=\"naruto\">".t("Você está em uma batalha.")."</div>";
        }

        // Nomes dos equipamentos no idioma do jogador.
        foreach (array("weaponname", "armorname", "shieldname", "slot1name", "slot2name", "slot3name", "slot4name") as $campo) {
            if (isset($userrow[$campo])) { $userrow[$campo] = t($userrow[$campo]); }
        }

        if ($controlrow["forumtype"] == 0) { $userrow["forumslink"] = ""; }
        elseif ($controlrow["forumtype"] == 1) { $userrow["forumslink"] = "<a href=\"forum.php\">".t("Fórum")."</a><br />"; }
        elseif ($controlrow["forumtype"] == 2) { $userrow["forumslink"] = "<a href=\"".$controlrow["forumaddress"]."\">".t("Fórum")."</a><br />"; }

        // Formatação de alguns campos do jogador.
        if ($userrow["latitude"] < 0) { $userrow["latitude"] = $userrow["latitude"] * -1 . "S"; } else { $userrow["latitude"] .= "N"; }
        if ($userrow["longitude"] < 0) { $userrow["longitude"] = $userrow["longitude"] * -1 . "W"; } else { $userrow["longitude"] .= "E"; }
        $userrow["experience"] = number_format((float)$userrow["experience"]);
        $userrow["gold"] = number_format((float)$userrow["gold"]);
        if ($userrow["authlevel"] == 1) { $userrow["adminlink"] = "<a href=\"admin.php\">".t("Painel do Administrador")."</a><br /><a href=\"gm.php\">".t("Painel do GameMaster")."</a><br />"; } elseif (ehgm($userrow)) { $userrow["adminlink"] = "<a href=\"gm.php\">".t("Painel do GameMaster")."</a><br />"; } else { $userrow["adminlink"] = ""; }

        // Barras de HP / CH / TP / NP / EP.
        $stattable = "<table width=\"150\"><tr>\n";
        $stattable .= "<td width=\"20%\">".barradestatus($userrow["currenthp"], $userrow["maxhp"], "vermelho", t("Pontos de Vida"))."</td>\n";
        $stattable .= "<td width=\"20%\">".barradestatus($userrow["currentmp"], $userrow["maxmp"], "azul", t("Pontos de Chakra"))."</td>\n";
        $stattable .= "<td width=\"20%\">".barradestatus($userrow["currenttp"], $userrow["maxtp"], "amarelo", t("Pontos de Viagem"))."</td>\n";
        $stattable .= "<td width=\"20%\">".barradestatus($userrow["currentnp"], $userrow["maxnp"], "verde", t("Pontos Naturais"))."</td>\n";
        $stattable .= "<td width=\"20%\">".barradestatus($userrow["currentep"], $userrow["maxep"], "roxa", t("Pontos de Elemento"))."</td>\n";
        $stattable .= "</tr><tr><td>HP</td><td>CH</td><td>TP</td><td>NP</td><td>EP</td></tr></table>\n";
        $userrow["statbars"] = $stattable;

        // Destaca em vermelho os valores baixos.
        if ($userrow["currenthp"] <= ($userrow["maxhp"]/5)) { $userrow["currenthp"] = "<span style=\'color:red\'><b>".$userrow["currenthp"]."</b></span>"; } // \' porque o valor vai dentro de uma string JavaScript (tooltip).
        if ($userrow["currentmp"] <= ($userrow["maxmp"]/5)) { $userrow["currentmp"] = "<span style=\'color:red\'><b>".$userrow["currentmp"]."</b></span>"; }

        // Lista rápida de jutsus (medicinais e jutsus de menu: Suprimentos, Passo Veloz, Senjutsu): listarapidajutsus() em funcoesjutsus.php.
        include_once('funcoesinclusas.php');
        $userrow["magiclist"] = listarapidajutsus($userrow);
        if ($userrow["magiclist"] == "") { $userrow["magiclist"] = t("Nenhum"); }

        // Comandos do personagem (troca, duelo, descanso, chat, procurar jogador e fórum).
        $comandospersonagem = "<div id=\"comandosdentro\" style=\"z-index: 3;\"><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" background=\"images/fundoviajar.png\" style=\"border:1px #000000 solid\"><tr style=\"border:1px #ffefb7 solid\"><td style=\"border:1px #ffefb7 solid\"><a href=\"troca.php?do=troca\"><img src=\"images/24/troca.gif\" title=\"".t("Realizar uma Troca")."\" border=\"0\" alt=\"\" /></a></td><td style=\"border:1px #ffefb7 solid\"><a href=\"users.php?do=batalha1\"><img src=\"images/24/duelar.gif\" title=\"".t("Realizar um Duelo")."\" border=\"0\" alt=\"\" /></a></td><td style=\"border:1px #ffefb7 solid\"><a href=\"encherhp.php?csrf=".csrf_token()."\"><img src=\"images/24/descansar.gif\" title=\"".t("Descansar")."\" border=\"0\" alt=\"\" /></a></td><td style=\"border:1px #ffefb7 solid\"><a href=\"javascript:openchatpopup()\"><img src=\"images/24/conversar.gif\" title=\"".t("Abrir Chat Global")."\" border=\"0\" alt=\"\" /></a></td><td style=\"border:1px #ffefb7 solid\"><a href=\"javascript: procurarjogador();\"><img src=\"images/24/visualizar.gif\" title=\"".t("Visualizar um Personagem")."\" border=\"0\" alt=\"\" /></a></td></tr>";
        $comandospersonagem .= "<tr style=\"border:1px #ffefb7 solid\">";
        $comandospersonagem .= "<td style=\"border:1px #ffefb7 solid\"><a href=\"forum.php?do=msg\" title=\"".t("Abrir o Fórum In-Game")."\"><img src=\"images/24/forum.gif\" border=\"0\" alt=\"\" /></a></td>";
        for ($h = 1; $h <= 4; $h++) {
            $comandospersonagem .= "<td style=\"border:1px #ffefb7 solid\"><img src=\"images/24/gif24.gif\" alt=\"\" /></td>";
        }
        $comandospersonagem .= "</tr></table></div><div style=\"height:20px;font-size:0;line-height:0\"></div>"; // Espaço exato para as abas "comandos/cidades" do fundo encostarem na grade das vilas.

        include_once('funcoesinclusas.php');

        // Lista "Viajar Para": cidades conhecidas (coloridas) e desconhecidas (apagadas).
        $townslist = explode(",",$userrow["towns"]);
        $townquery2 = doquery("SELECT * FROM {{table}} ORDER BY id", "towns");
        include_once('cidadesconteudo.php');
        $natalviagem = vilanataldoalinhamento($userrow["alinhamento"]); // custo do "Viajar Para" (custoviagem(), funcoesinclusas.php)
        $userrow["townslist"] = "<center><div style=\"width:179px;background-image:url('images/naruto.jpg');background-repeat:no-repeat; background-position: 1px 1px;\"><table><tr><td><div style=\"padding-right: 11px;padding-left: 15px;padding-bottom: 77px; padding-top: 12px;\" id=\"editar\"><center><table><tr><td height=\"35\" valign=\"middle\"><center>".$userrow["currenttown"]."</center></td></tr></table></center></div></td></tr><tr><td><center><div id=\"cidadesfora\">$comandospersonagem<div id=\"cidadesdentro\" style=\"z-index: 2; display: block;\"><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" background=\"images/fundoviajar.png\" style=\"border:1px #000000 solid\"><tr style=\"border:1px #000000 solid\">";
        $quantas = 0;
        while ($townrow2 = mysqli_fetch_array($townquery2)) {
            $town = in_array($townrow2["id"], $townslist);
            if ($quantas > 0 && ($quantas % 5) == 0) { $userrow["townslist"] .= "</tr><tr style=\"border:1px #ffefb7 solid\">"; }
            $imagemcidade = (file_exists("images/24/cidade_".$townrow2["id"].".gif")) ? "cidade_".$townrow2["id"] : "gif24";
            if ($town == true) {
                if ($townrow2['latitude'] < 0) { $lat = ($townrow2['latitude'] * -1)."S"; } else { $lat = $townrow2['latitude']."N"; }
                if ($townrow2['longitude'] < 0) { $log = ($townrow2['longitude'] * -1)."W"; } else { $log = $townrow2['longitude']."E"; }
                $userrow["townslist"] .= "<td style=\"border:1px #ffefb7 solid\"><a href=\"index.php?do=gotown:".$townrow2["id"]."&amp;csrf=".csrf_token()."\"><img src=\"images/24/".$imagemcidade.".gif\" title=\"[$lat,$log] ".t($townrow2["name"])." - ".textocustoviagemlista(custoviagem($userrow, $townrow2, $natalviagem))."\" border=\"0\" alt=\"\" /></a></td>";
            } else {
                $imagemapagada = (file_exists("images/24/cidade_".$townrow2["id"]."p.gif")) ? "cidade_".$townrow2["id"]."p" : "gif24";
                $userrow["townslist"] .= "<td style=\"border:1px #ffefb7 solid\"><img src=\"images/24/".$imagemapagada.".gif\" title=\"".tf("%s (desconhecida)", t($townrow2["name"]))."\" border=\"0\" alt=\"\" /></td>";
            }
            $quantas++;
        }
        // Completa a grade de 5 colunas com espaços vazios.
        while (($quantas % 5) != 0 || $quantas < 10) {
            if ($quantas > 0 && ($quantas % 5) == 0) { $userrow["townslist"] .= "</tr><tr style=\"border:1px #ffefb7 solid\">"; }
            $userrow["townslist"] .= "<td style=\"border:1px #ffefb7 solid\" background=\"images/fundocasa.jpg\"><img src=\"images/24/gif24.gif\" border=\"0\" alt=\"\" /></td>";
            $quantas++;
        }
        $userrow["townslist"] .= "</tr></table></div></div></center></td></tr></table></div></center>";

    } else {
        $userrow = array();
    }

    $finalarray = array(
        "gamename"=>$controlrow["gamename"],
        "title"=>$title,
        "content"=>$content.((!empty($userrow["convidado"]) && function_exists('convidado_banner')) ? convidado_banner($userrow) : ""), // faixa/aviso do jogador sem conta
        "rightnav"=>parsetemplate($rightnav,$userrow),
        "leftnav"=>parsetemplate($leftnav,$userrow),
        "topnav"=>$topnav,
        "totaltime"=>round(getmicrotime() - $starttime, 4),
        "numqueries"=>$numqueries,
        "version"=>$version,
        "build"=>$build);
    cabecalhosseguranca();
    $page = parsetemplate($template, $finalarray);

    $page = localizarimagens($xml . $page);

    echo $page;
    die();

}

function scripttoque() { // <script> do js/toque.js (dicas por toque em celulares/tablets) com os textos traduzidos e o aviso único por conta.

    global $userrow;
    $h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); };
    $logado = is_array($userrow) && !empty($userrow["id"]);
    $visto = ($logado && isset($userrow["avisotoque"]) && intval($userrow["avisotoque"]) == 1) ? "1" : "0"; // fora do login: o aviso não aparece (sem csrf).
    return "\n<script type=\"text/javascript\" src=\"js/toque.js?v=2\" data-toque=\"1\" data-visto=\"$visto\" data-csrf=\"".($logado ? $h(csrf_token()) : "")."\""
        ." data-aviso=\"".$h(t("Toque uma vez para ver os detalhes, toque de novo para usar."))."\""
        ." data-avisoextra=\"".$h(t("O primeiro toque abre a mesma caixinha de detalhes que aparece com o mouse no computador; o segundo toque confirma a ação. Toque fora para fechar."))."\""
        ." data-avisolista=\"".$h(implode("|", array(
            t("Item da mochila ou equipamento: 1º toque mostra os atributos e a durabilidade; 2º toque usa, equipa ou desequipa."),
            t("Barras de HP, CH, TP, NP, EP e a de nível: um toque mostra os valores."),
            t("Ícone de uma vila no mapa: um toque mostra o nome da vila."),
            t("Jutsus e itens na batalha: 1º toque mostra o efeito; 2º toque escolhe."),
            t("Minimapa, setas de direção, foto do avatar, pegar o drop, ajuda e fechar: um toque só, age direto."))))."\""
        ." data-entendi=\"".$h(t("Entendi"))."\" data-fechar=\"".$h(t("Fechar"))."\" data-detalhes=\"".$h(t("Detalhes"))."\" defer=\"defer\"></script>";

}

if (!function_exists('scriptcelular')) {
function scriptcelular() { // Modo celular (só reorganiza a tela): decide cedo, no <head>, para não "piscar" o layout de computador.
    // Cookie modocelular=1/0 vale sempre; sem ele (1ª visita) detecta tela pequena + toque e grava o cookie.
    // No modo celular escreve o <meta viewport> e põe a classe "modocel" no <html>; todo o CSS fica em celular.css.
    $h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, "UTF-8"); };
    return "\n<link rel=\"stylesheet\" type=\"text/css\" href=\"cartas.css?v=19\" />\n<link rel=\"stylesheet\" type=\"text/css\" href=\"celular.css?v=22\" />"
        // celular.css é estático: o banner com o menu escrito (layoutnovo/titulo/cima.jpg) é trocado pela versão do idioma aqui.
        .(idioma() != "pt" && is_file(__DIR__."/layoutnovo/titulo/".idioma()."/cima.jpg") ? "\n<style type=\"text/css\">html.modocel td.celtopo, html.modocel #celtopo { background-image: url(layoutnovo/titulo/".idioma()."/cima.jpg) !important; }</style>" : "")
        ."\n<script type=\"text/javascript\">(function(){var d=document.documentElement,m=document.cookie.match(/(?:^|; )modocelular=([01])/),c;"
        ."if(m){c=m[1]==\"1\";}else{var w=Math.min(screen.width||9999,screen.height||9999),t=(\"ontouchstart\" in window)||(navigator.maxTouchPoints>0);"
        ."c=t&&w<=600;document.cookie=\"modocelular=\"+(c?1:0)+\"; path=/; max-age=31536000; SameSite=Lax\";}"
        ."if(c){d.className+=\" modocel celmontando\";document.write('<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />');}})();</script>"
        ."\n<script type=\"text/javascript\" src=\"js/celular.js?v=8\" data-celular=\"1\" data-vcel=\"".$h(t("Versão celular"))."\" data-vpc=\"".$h(t("Versão computador"))."\""
        ." data-nav=\"".$h(t("Mapa e navegação"))."\" data-pers=\"".$h(t("Personagem"))."\" defer=\"defer\"></script>";
}
}

?>
