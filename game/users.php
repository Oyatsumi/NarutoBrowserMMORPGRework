<?php // users.php :: Funções da conta do jogador (registro, verificação de e-mail, senha perdida e troca de senha) e ações entre jogadores (doar Ryou, duelos PvP e procurar jogador).
// Textos exibidos passam por t()/tf() (tradução: lang/en/contas.php; duelos em lang/en/duelos.php). Valores internos ("In Town", "None", rotas) não são traduzidos.

include_once('lib.php');
$link = opendb();

include_once('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php'); // custochakra(): custo de jutsu com a Economia de Chakra (duelo PvP).

// Configurações gerais do jogo (tabela control): usadas no registro, nos e-mails e nas regras de PvP.
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

// Regras do duelo PvP (explicadas no bloco "Duelo PvP" mais abaixo).
define('DUELO_TEMPOTURNO', 60);     // segundos de cada turno.
define('DUELO_MAXATRASOS', 3);      // tempos esgotados que encerram o duelo.
define('DUELO_TEMPODESAFIO', 300);  // validade de um desafio pendente (segundos).
define('DUELO_TAMANHOREGISTRO', 8); // ações mostradas no registro do duelo.

// O ativarconta.php inclui este arquivo só para aproveitar as funções de e-mail; nesse caso as rotas não são executadas.
if (!defined('USERS_SOMENTE_FUNCOES')) {

    $do = isset($_GET["do"]) ? $_GET["do"] : "";
    // Fecha a temporada de duelo vencida antes de qualquer duelo (fora da transação do duelo).
    if ($do == "batalha1" || $do == "duelo") { include_once('funcoescla.php'); temporadaverificar(); }
    if ($do == "register") {
        // Convidado jogando: "Criar Conta" (menu do topo ou qualquer link) vira a conversão que mantém o personagem.
        include_once('cookies.php'); include_once('funcoesconvidado.php');
        if (!isset($_COOKIE["narutorpg"]) && convidado_temsessao()) { $cv = convidado_linha(); if ($cv) { header("Location: users.php?do=convidadoconta"); die(); } }
        register();
    }
    elseif ($do == "verify") { verify(); }
    elseif ($do == "lostpassword") { lostpassword(); }
    elseif ($do == "resetpassword") { resetpassword(); }
    elseif ($do == "changepassword") { changepassword(); }
    elseif ($do == "perguntas") { perguntas(); }
    elseif ($do == "recuperar") { recuperar(); }
    elseif ($do == "recuperarcodigo") { recuperarcodigo(); }
    elseif ($do == "doardinheiro") { doardinheiro(); }
    elseif ($do == "convidadoconta") { include_once('funcoesconvidado.php'); convidadoconta(); } // jogar sem conta: criar a conta mantendo o personagem
    elseif ($do == "convidadofechar") { include_once('funcoesconvidado.php'); convidado_fecharaviso(); }
    // Rotas antigas: o banco (Ryou e itens) e a doação de itens foram para o funcaoitens.php,
    // e a troca de itens entre jogadores foi refeita no troca.php. Os links antigos são redirecionados.
    elseif ($do == "banco" || $do == "doaritem") { header("Location: funcaoitens.php?do=banco"); die(); }
    elseif ($do == "troca1" || $do == "troca2" || $do == "troca3" || $do == "troca4") { header("Location: troca.php?do=troca"); die(); }
    elseif ($do == "batalha1") { batalha1(); }
    elseif ($do == "resetarduelo") { resetarduelo(); }
    elseif ($do == "duelo") { duelo(); }
    elseif ($do == "procurarjogador") { procurarjogador(); }
    header("Location: index.php"); die();

}

/* ------------------------------------------------------------------------
   Funções auxiliares usadas pelas páginas deste arquivo.
   ------------------------------------------------------------------------ */

function titulopagina($imagem) { // Faixa com a imagem de título que aparece no topo de cada página (ex.: images/criarconta.gif).

    return "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/".$imagem."\" alt=\"\" /></center></td></tr></table>";

}

function mensagemtopo() { // Mensagem curta passada pela URL (?conteudo=...) e mostrada acima do formulário (já chega traduzida).

    if (lermsg("conteudo") == "") { return ""; }
    return "<font color=\"brown\"><center>".strip_tags(lermsg("conteudo"))."</center></font><br />";

}

function voltarcommensagem($pagina, $mensagem) { // Redireciona para uma página do jogo com uma mensagem (?conteudo=), traduzida antes de ir para a URL.

    header("Location: ".$pagina."&conteudo=".rawurlencode(t($mensagem)));
    die();

}

function senhavalida($senha) { // Regras de senha: de 6 a 72 caracteres, qualquer caractere. Devolve "" se estiver tudo certo ou a chave (em português) do erro.

    // O lib.php aplica addslashes + htmlspecialchars em todo POST; o tamanho é medido na senha que o jogador realmente digitou.
    // O hash é sempre feito sobre a versão tratada (a mesma que o login.php recebe), para o login conferir igual.
    $tamanho = mb_strlen(html_entity_decode(stripslashes($senha), ENT_QUOTES, 'UTF-8'), 'UTF-8');
    if (trim($senha) == "") { return "Campo de senha é necessário."; }
    if ($tamanho < 6) { return "A senha deve ter pelo menos 6 caracteres."; }
    if ($tamanho > 72) { return "A senha não pode ter mais que 72 caracteres."; }
    return "";

}

function ipdovisitante() { // IP do visitante, no formato usado pela tabela loginfail (a mesma do login.php).

    return addslashes(substr($_SERVER['REMOTE_ADDR'], 0, 45));

}

function tentativaserradas($ip) { // Quantas senhas erradas esse IP digitou nos últimos 15 minutos (login e troca de senha somam juntos).

    $query = doquery("SELECT tentativas FROM {{table}} WHERE ip='$ip' AND ultima >= (NOW() - INTERVAL 15 MINUTE) LIMIT 1", "loginfail");
    if (mysqli_num_rows($query) != 1) { return 0; }
    $row = mysqli_fetch_array($query);
    return intval($row["tentativas"]);

}

function registrartentativaerrada($ip) { // Conta mais uma senha errada para esse IP.

    doquery("DELETE FROM {{table}} WHERE ultima < (NOW() - INTERVAL 15 MINUTE)", "loginfail");
    doquery("INSERT INTO {{table}} (ip, tentativas, ultima) VALUES ('$ip', 1, NOW()) ON DUPLICATE KEY UPDATE tentativas=tentativas+1, ultima=NOW()", "loginfail");

}

function exigirjogador($somenteemcidade) { // Confere se há um jogador logado que pode usar a função (não banido, verificado, fora de luta e, se pedido, dentro de uma cidade).

    global $userrow, $controlrow;
    if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); }
    if ($userrow["authlevel"] == 2) { display(t("Sua conta foi bloqueada. Por favor tente novamente mais tarde."),t("Erro"),false,false,false); }
    if ($controlrow["gameopen"] == 0 && $userrow["authlevel"] != 1) { display(nl2br(t($controlrow["closedmsg"])),t("Fechado"),false,false,false); }
    if (verificaremail() && $userrow["verify"] != 1) { header("Location: users.php?do=verify"); die(); }
    if ($userrow["currentaction"] == "Fighting") { voltarcommensagem("index.php?do=fight", "Você não pode acessar essa função no meio de uma batalha!"); }
    if ($somenteemcidade && $userrow["currentaction"] != "In Town") { header("Location: index.php?conteudo=".rawurlencode(t("Você só pode acessar essa função dentro de uma cidade!"))); die(); }

}

/* ------------------------------------------------------------------------
   Conta: registro, verificação, senha perdida e troca de senha.
   ------------------------------------------------------------------------ */

function validarnomeconta($username, &$erros, $excetoid = 0) { // Nome da conta: só letras sem acento, números, "_" e "-" (a regex antiga [^A-z] deixava passar [ \ ] ^ e `). Usado no cadastro e na conversão do convidado.
    if ($username == "") { $erros[] = "Nome da conta é necessário."; }
    elseif (preg_match("/[^A-Za-z0-9_\-]/", $username) == 1) { $erros[] = "Nome da sua conta deve ser alfanumérico (não pode conter espaços)."; }
    elseif (strlen($username) > 30) { $erros[] = "Nome da Conta não pode conter mais que 30 caracteres."; }
    elseif (preg_match("/^convidado[0-9]*$/i", $username) == 1) { $erros[] = "Essa conta já existe."; } // "ConvidadoNNN" é reservado aos personagens sem conta
    else {
        $usernamequery = doquery("SELECT id FROM {{table}} WHERE username='$username' AND id<>'".intval($excetoid)."' LIMIT 1","users");
        if (mysqli_num_rows($usernamequery) > 0) { $erros[] = "Essa conta já existe."; }
    }
}

function validarnomepersonagem($charname, &$erros, $excetoid = 0) { // Nome do personagem: mesmas regras do nome da conta (o convidado pode manter o próprio).
    if ($charname == "") { $erros[] = "Campo de Nome do Personagem é necessário."; }
    elseif (preg_match("/[^A-Za-z0-9_\-]/", $charname) == 1) { $erros[] = "Nome do personagem deve ser alfanumérico (não pode conter espaços)."; }
    elseif (strlen($charname) > 30) { $erros[] = "Nome do Personagem não pode conter mais que 30 caracteres."; }
    else {
        $characternamequery = doquery("SELECT id FROM {{table}} WHERE charname='$charname' AND id<>'".intval($excetoid)."' LIMIT 1","users");
        if (mysqli_num_rows($characternamequery) > 0) { $erros[] = "Esse nome de personagem já está em uso."; }
    }
}

function validaremailcadastro($email1, $email2, &$erros, $excetoid = 0) { // E-mail: obrigatório só com os e-mails do jogo ligados (control.emailativo). Desligados, é opcional (se preenchido, é conferido igual).
    global $controlrow;
    if (empty($controlrow["emailativo"]) && $email1 == "" && $email2 == "") { }
    elseif ($email1 == "" || $email2 == "") { $erros[] = "Campo de e-mail é necessário."; }
    elseif ($email1 != $email2) { $erros[] = "Os e-mails não coincidem."; }
    elseif (!is_email($email1) || strlen($email1) > 100) { $erros[] = "O e-mail não é válido."; }
    else {
        $emailquery = doquery("SELECT id FROM {{table}} WHERE email='$email1' AND id<>'".intval($excetoid)."' LIMIT 1","users");
        if (mysqli_num_rows($emailquery) > 0) { $erros[] = "Esse e-mail já está em uso."; }
    }
}

function register() { // Cria uma conta nova. Se o jogo exigir verificação, a conta só pode jogar depois de digitar o código enviado por e-mail.

    global $controlrow;
    $topo = titulopagina("criarconta.gif");

    // O administrador pode fechar o registro de contas novas (control.allowregister = 0).
    if ($controlrow["allowregister"] == 0) {
        display($topo."<form id=\"formback\"><fieldset id=\"field2\"><legend>".t("Dados da Conta")."</legend>".t("O registro de novas contas está fechado no momento. Por favor volte mais tarde.")."<br /><br />".t("Se você já tem uma conta, acesse a <a href=\"login.php?do=login\">Página de Login</a>.")."</fieldset></form>", t("Registrar"), false, false, false);
    }

    if (isset($_POST["submit"])) {

        $username = pegartexto($_POST, "username");
        $charname = pegartexto($_POST, "charname");
        $email1 = pegartexto($_POST, "email1");
        $email2 = pegartexto($_POST, "email2");
        $password1 = pegartexto($_POST, "password1");
        $password2 = pegartexto($_POST, "password2");
        $difficulty = pegarint($_POST, "difficulty");

        $erros = array(); // Chaves (em português) dos erros encontrados; são traduzidas na hora de mostrar.

        validarnomeconta($username, $erros);
        validarnomepersonagem($charname, $erros);
        validaremailcadastro($email1, $email2, $erros);

        // Senha.
        $erro = senhavalida($password1);
        if ($erro != "") { $erros[] = $erro; }
        if ($password1 != $password2) { $erros[] = "Os campos de senha não coincidem."; }

        // A especialização não é mais escolhida aqui: todo ninja começa sem (charclass 0) e escolhe dentro do jogo
        // (especializacao.php, a partir do nível control.classenivel). A dificuldade (1 a 3) vem de um <select>.
        // Recuperação sem e-mail (obrigatória): 2 perguntas secretas OU código de recuperação gerado pelo jogo.
        $codigonovo = "";
        if (pegartexto($_POST, "metodo") == "codigo") { $codigonovo = gerarcodigorecuperacao(); $sqlperguntas = "codigorecuperacao='".addslashes(senha_hash(normalizarcodigo($codigonovo)))."'"; }
        else { $sqlperguntas = lerperguntas($erros); }
        if ($difficulty < 1 || $difficulty > 3) { $erros[] = "Dificuldade inválida."; }

        if (count($erros) == 0) {

            // Campo verify: '1' = conta liberada; senão guarda o código de 8 letras enviado por e-mail.
            if (verificaremail()) { $verifycode = gerarcodigoverificacao(); } else { $verifycode = '1'; }

            include_once('funcoesconvidado.php'); // criarpersonagemnovo(): o mesmo INSERT do convidado
            criarpersonagemnovo($username, $charname, senha_hash($password1), $email1, $difficulty, $verifycode, $sqlperguntas, "");

            if (verificaremail()) {
                if (sendregmail($email1, $verifycode)) {
                    $page = $topo.t("Sua conta foi criada com sucesso.")."<br /><br />".t("Você deve receber um e-mail de confirmação logo. É preciso verificar o código que está presente no seu e-mail para ativar a sua conta. Uma vez recebido o e-mail, por favor visite a <a href=\"users.php?do=verify\">Página de Verificação</a> para ativar sua conta e começar a jogar.");
                } else {
                    $page = $topo.t("Sua conta foi criada com sucesso.")."<br /><br />".t("De qualquer forma, houve um erro durante o envio do seu e-mail de confirmação. Você pode <a href=\"ativarconta.php\">pedir o reenvio do código de ativação</a> ou procurar o Administrador do jogo para resolver seu problema.");
                }
            } elseif ($codigonovo != "") {
                paginacodigo($codigonovo, "login.php", t("Sua conta foi criada com sucesso. Este é o seu código de recuperação:"));
            } else {
                $page = $topo.t("Sua conta foi criada com sucesso.")."<br /><br />".tf("Você pode agora acessar a <a href=\"login.php?do=login\">Página de Login</a> e continuar jogando %s!", $controlrow["gamename"]);
            }

        } else {

            $errorlist = "";
            foreach ($erros as $chave) { $errorlist .= t($chave)."<br />"; }
            $page = $topo."<form id=\"formback\"><fieldset id=\"field2\"><legend>".t("Dados da Conta")."</legend>".t("O(s) seguinte(s) erro(s) ocorreram enquanto sua conta estava sendo feita:")."<br /><span style=\"color:red;\">$errorlist</span><br />".t("Por favor <a href=\"javascript: history.back();\">volte</a> e tente novamente.")."</fieldset></form>";

        }

    } else {

        // Os nomes de especialização e dificuldade vêm da tabela control e são exibidos traduzidos.
        $dados = $controlrow;
        foreach (array("class1name", "class2name", "class3name", "diff1name", "diff2name", "diff3name") as $campo) { $dados[$campo] = t($controlrow[$campo]); }
        $dados["avisoclasse"] = tf("Você escolhe a especialização (%s, %s ou %s) dentro do jogo, numa vila, a partir do nível %s.", $dados["class1name"], $dados["class2name"], $dados["class3name"], max(1, intval(isset($controlrow["classenivel"]) ? $controlrow["classenivel"] : 5)));
        if (verificaremail()) {
            $dados["verifytext"] = "<br /><span class=\"small\">".t("Um código de verificação será enviado para seu e-mail, você não está habilitado a jogar antes de ativar sua conta com o código presente em seu e-mail. Esteja ciente de que é preciso preencher o campo e-mail com um e-mail válido.")."</span>";
        } elseif (empty($controlrow["emailativo"])) {
            $dados["verifytext"] = "<br /><span class=\"small\">".t("O e-mail é opcional, mas se você cadastrar um ele também é pedido para recuperar a senha.")."</span>";
        } else {
            $dados["verifytext"] = "";
        }
        $dados["perguntas"] = camposmetodo();
        include_once('cidadesconteudo.php');
        $vilainicial = vilanataldoalinhamento(alinhamentoinicial());
        $dados["avisovila"] = tf("Todo ninja começa na <b>%s</b>, a vila natal para onde você volta quando é derrotado. Dentro do jogo, mais tarde, é possível trocar de vila pelas missões de transferência dos embaixadores.", $vilainicial ? t($vilainicial["name"]) : "?");
        $page = parsetemplate(gettemplate("register"), $dados);

    }

    display($page, t("Registrar"), false, false, false);

}

function gerarcodigoverificacao() { // Código de ativação: 8 letras maiúsculas sorteadas com gerador seguro.

    $codigo = "";
    for ($i = 0; $i < 8; $i++) { $codigo .= chr(random_int(65, 90)); }
    return $codigo;

}

function verify() { // Ativa a conta com o código de verificação recebido por e-mail.

    $page = titulopagina("minhaconta.gif");

    if (isset($_POST["submit"])) {

        $username = pegartexto($_POST, "username");
        $email = pegartexto($_POST, "email");
        $verify = strtoupper(trim(pegartexto($_POST, "verify")));

        $userquery = doquery("SELECT id,email,verify FROM {{table}} WHERE username='$username' LIMIT 1","users");
        $row = (mysqli_num_rows($userquery) == 1) ? mysqli_fetch_array($userquery) : false;

        if ($row != false && $row["verify"] == "1" && $row["email"] == $email) {
            display($page.t("Sua conta já está verificada.")."<br /><br />".t("Você pode acessar a <a href=\"login.php?do=login\">Página de Login</a> e começar a jogar."),t("Verificar E-mail"),false,false,false);
        }
        // Mesma mensagem para conta inexistente, e-mail errado ou código errado (não revela qual dado está errado).
        // O código válido tem sempre 8 letras: isso impede que o valor padrão '0' da coluna seja aceito.
        if ($row == false || $row["email"] != $email || preg_match("/^[A-Z]{8}$/", $verify) != 1 || !hash_equals((string)$row["verify"], $verify)) {
            display($page."<font color=\"brown\"><center>".t("Nome de usuário, e-mail ou código de verificação incorretos.")."</center></font><br />".gettemplate("verify"),t("Verificar E-mail"),false,false,false);
        }

        doquery("UPDATE {{table}} SET verify='1' WHERE id='".intval($row["id"])."' LIMIT 1","users");
        display($page.t("Sua conta foi ativada com sucesso.")."<br /><br />".t("Você pode acessar a <a href=\"login.php?do=login\">Página de Login</a> e começar a jogar agora mesmo.")."<br /><br />".t("Obrigado por jogar!"),t("Verificar E-mail"),false,false,false);

    }

    display($page.gettemplate("verify"), t("Verificar E-mail"), false, false, false);

}

function lostpassword() { // Senha perdida: envia por e-mail um link (válido por 1 hora) para o jogador criar uma senha nova. Sem e-mails: perguntas secretas (recuperar()).

    global $conteudouser, $controlrow;
    if (empty($controlrow["emailativo"])) { recuperar(); } // Sem e-mails: só as perguntas secretas.

    if (isset($_POST["submit"])) {

        $email = pegartexto($_POST, "email");
        if (is_email($email)) {
            $userquery = doquery("SELECT id,email,(resetexpira IS NOT NULL AND resetexpira > NOW() + INTERVAL 55 MINUTE) AS recente FROM {{table}} WHERE email='$email' LIMIT 1","users");
            if (mysqli_num_rows($userquery) == 1) {
                $row = mysqli_fetch_array($userquery);
                // Um pedido por conta a cada 5 minutos, para ninguém usar o formulário para encher a caixa de e-mail de outra pessoa.
                if ($row["recente"] != 1) {
                    $codigo = bin2hex(random_bytes(16));
                    doquery("UPDATE {{table}} SET resetcode='$codigo', resetexpira=(NOW() + INTERVAL 1 HOUR) WHERE id='".intval($row["id"])."' LIMIT 1","users");
                    $endereco = enderecodojogo()."users.php?do=resetpassword&id=".intval($row["id"])."&code=".$codigo;
                    // O e-mail sai no idioma que o jogador está usando no momento do pedido.
                    if (!sendpassemail($row["email"], $endereco)) { error_log("Naruto RPG: falha ao enviar o e-mail de senha perdida para a conta ".intval($row["id"])); }
                }
            }
        }

        // A resposta é sempre a mesma, exista ou não uma conta com esse e-mail (assim ninguém descobre quais e-mails estão cadastrados).
        display(titulopagina("minhaconta.gif")."<form id=\"formback\"><fieldset id=\"field2\"><legend>".t("Recuperar Senha")."</legend>".t("Se houver uma conta registrada com esse e-mail, enviamos para ele um link para você criar uma nova senha. O link vale por 1 hora.")."<br /><br />".t("Quando recebê-lo, abra o link, escolha a nova senha e depois faça o <a href=\"login.php?do=login\">Log In</a> para continuar jogando.")."<br /><br />".t("Obrigado.")."</fieldset></form>",t("Senha Perdida"),false,false,false);

    }

    $conteudouser = mensagemtopo();
    $page = gettemplate("lostpassword");
    display($page, t("Senha Perdida"), false, false, false);

}

/* ------------------------------------------------------------------------
   Recuperação de senha SEM e-mail: perguntas secretas.
   - No cadastro o jogador escolhe 2 perguntas (da lista ou escritas por ele) e as respostas, guardadas só como hash
     (senha_hash, depois de normalizar: minúsculas e espaços simples). Contas antigas cadastram em users.php?do=perguntas.
   - Recuperar (users.php?do=recuperar, e direto em do=lostpassword quando control.emailativo = 0) exige TUDO certo:
     conta, personagem, as 2 respostas, ano de criação da conta, e-mail cadastrado e level atual do personagem.
     Erro genérico (não diz qual campo falhou), limite de 5 erros em 15 minutos por IP (tabela loginfail) e registro
     de cada tentativa na auditoria (tabela adminlog).
   - Um GM pode "liberar a recuperação" por 1 hora (users.resetliberado) para quem esqueceu as respostas: aí as
     respostas não são pedidas, mas todos os outros dados continuam obrigatórios. O GM nunca vê senha, hash nem respostas.
   ------------------------------------------------------------------------ */

function perguntassecretas() { // Lista de perguntas prontas (texto em português = chave de tradução).

    return array(
        "Qual era o nome do seu primeiro animal de estimação?",
        "Em que cidade você nasceu?",
        "Qual é o nome do meio da sua mãe?",
        "Qual foi a sua primeira escola?",
        "Qual é o seu personagem favorito de Naruto que não é o protagonista?",
        "Qual era o apelido que você tinha na infância?",
        "Qual foi o primeiro jogo que você jogou?",
        "Qual é a comida que você mais odeia?");

}

function normalizarresposta($texto) { // Resposta sem diferença de maiúsculas/acentos de digitação e espaços extras (o hash é feito sobre esta forma).

    $texto = html_entity_decode(stripslashes((string)$texto), ENT_QUOTES, 'UTF-8');
    return mb_strtolower(trim(preg_replace("/\s+/u", " ", $texto)), 'UTF-8');

}

function camposperguntas() { // Campos das 2 perguntas (lista + "outra") e respostas, com o aviso de segurança.

    $t = "t";
    $html = "<tr><td colspan=\"2\"><div style=\"border:1px solid #8b1a1a;background:#fff3cd;padding:6px;margin:6px 0\"><b>".t("Atenção: as perguntas secretas são importantes!")."</b><br />"
        .t("Elas são o único jeito de recuperar a conta se você esquecer a senha (este jogo pode não usar e-mail). Escolha respostas que só você saiba: se forem fáceis de adivinhar ou estiverem nas suas redes sociais, outra pessoa pode tomar a sua conta. Maiúsculas e espaços extras não importam.")."</div></td></tr>";
    for ($n = 1; $n <= 2; $n++) {
        $op = "<option value=\"\">".t("Escolha uma pergunta")."</option>";
        foreach (perguntassecretas() as $i => $p) { $op .= "<option value=\"$i\">".t($p)."</option>"; }
        $op .= "<option value=\"outra\">".t("Outra (escreva abaixo)")."</option>";
        $html .= "<tr><td>".tf("Pergunta secreta %s:", $n)."</td><td><select name=\"pergunta$n\">$op</select><br /><input type=\"text\" name=\"perguntaoutra$n\" size=\"40\" maxlength=\"150\" placeholder=\"".t("Sua própria pergunta (se escolheu Outra)")."\" /></td></tr>"
            ."<tr><td>".tf("Resposta %s:", $n)."</td><td><input type=\"password\" name=\"resposta$n\" size=\"30\" maxlength=\"100\" autocomplete=\"off\" /><br /><br /></td></tr>";
    }
    return $html;

}

function lerperguntas(&$erros) { // Lê e valida as perguntas/respostas do POST. Devolve "pergunta1='..', resposta1='..', ..." para o SQL (ou "" com erro).

    $sql = array(); $respostas = array();
    for ($n = 1; $n <= 2; $n++) {
        $escolha = pegartexto($_POST, "pergunta$n");
        if ($escolha === "outra") { $pergunta = trim(pegartexto($_POST, "perguntaoutra$n")); }
        elseif ($escolha !== "" && is_numeric($escolha) && isset(perguntassecretas()[intval($escolha)])) { $pergunta = addslashes(perguntassecretas()[intval($escolha)]); }
        else { $pergunta = ""; }
        $resposta = normalizarresposta(pegartexto($_POST, "resposta$n"));
        if (mb_strlen(stripslashes($pergunta), 'UTF-8') < 5 || mb_strlen(stripslashes($pergunta), 'UTF-8') > 150) { $erros[] = "Escolha (ou escreva) as duas perguntas secretas."; return ""; }
        if (mb_strlen($resposta, 'UTF-8') < 3 || mb_strlen($resposta, 'UTF-8') > 100) { $erros[] = "As respostas secretas devem ter de 3 a 100 caracteres."; return ""; }
        $perguntas[$n] = $pergunta; $respostas[$n] = $resposta;
        $sql[] = "pergunta$n='$pergunta', resposta$n='".addslashes(senha_hash($resposta))."'";
    }
    if ($perguntas[1] == $perguntas[2]) { $erros[] = "As duas perguntas secretas devem ser diferentes."; return ""; }
    if ($respostas[1] == $respostas[2]) { $erros[] = "As duas respostas secretas devem ser diferentes."; return ""; }
    return implode(", ", $sql);

}

function logrecuperacao($acao, $id, $detalhes) { // Registro de auditoria das tentativas de recuperação (tabela adminlog, sem autor logado).

    doquery("INSERT INTO {{table}} SET quando=NOW(), autor_id='0', autor='', cargo='', ip='".ipdovisitante()."', acao='".addslashes($acao)."', alvo_id='".intval($id)."', detalhes='".addslashes(mb_substr($detalhes, 0, 300, 'UTF-8'))."'", "adminlog");

}

function recuperar() { // Recuperação pelas perguntas secretas (etapa 1: conta + personagem; etapa 2: todo o resto). Nada diz qual dado estava errado.

    $t = "t";
    $topo = titulopagina("minhaconta.gif");
    $ip = ipdovisitante();
    $erro = "";
    $generico = "Os dados informados não conferem. Confira tudo e tente novamente.";
    $username = pegartexto($_POST, "username");
    $charname = pegartexto($_POST, "charname");
    $etapa = pegarint($_POST, "etapa", 0);
    $row = false;

    if ($etapa >= 1) {
        if (tentativaserradas($ip) >= 5) { $erro = "Muitas tentativas erradas. Aguarde 15 minutos e tente novamente."; $etapa = 0; }
        else {
            $row = mysqli_fetch_array(doquery("SELECT id,username,charname,email,level,regdate,pergunta1,resposta1,pergunta2,resposta2,resetliberado FROM {{table}} WHERE username='$username' AND charname='$charname' AND authlevel<>'2' LIMIT 1", "users"));
            $liberado = $row && intval($row["resetliberado"]) > time();
            if (!$row || ($row["pergunta1"] == "" && !$liberado)) {
                registrartentativaerrada($ip); logrecuperacao("recuperar_falha", $row ? $row["id"] : 0, "etapa 1 ".stripslashes($username));
                $erro = ($row && $row["pergunta1"] == "") ? "Esta conta não tem perguntas secretas cadastradas. Peça ajuda a um GameMaster." : $generico;
                $etapa = 0;
            }
        }
    }
    if ($etapa == 2 && $row) {
        $ok = true;
        if (!$liberado) {
            $ok = senha_confere(normalizarresposta(pegartexto($_POST, "resposta1")), $row["resposta1"]) & senha_confere(normalizarresposta(pegartexto($_POST, "resposta2")), $row["resposta2"]);
        }
        $ok = $ok && pegarint($_POST, "ano") == intval(substr($row["regdate"], 0, 4));
        $ok = $ok && mb_strtolower(trim(pegartexto($_POST, "email")), 'UTF-8') === mb_strtolower(trim($row["email"]), 'UTF-8');
        $ok = $ok && pegarint($_POST, "level", -1) == intval($row["level"]);
        $newpass1 = pegartexto($_POST, "newpass1");
        if (!$ok) {
            registrartentativaerrada($ip); logrecuperacao("recuperar_falha", $row["id"], "etapa 2");
            $erro = $generico; $etapa = 0;
        } else {
            $erro = senhavalida($newpass1);
            if ($erro == "" && $newpass1 != pegartexto($_POST, "newpass2")) { $erro = "Novas senhas não coincidem."; }
            if ($erro == "") {
                doquery("DELETE FROM {{table}} WHERE ip='$ip'", "loginfail");
                doquery("UPDATE {{table}} SET password='".addslashes(senha_hash($newpass1))."', resetcode='', resetexpira=NULL, trocarsenha='0', resetliberado='0' WHERE id='".intval($row["id"])."' LIMIT 1","users");
                logrecuperacao("recuperar_ok", $row["id"], $liberado ? "liberado por GM" : "perguntas");
                display($topo."<form id=\"formback\"><fieldset id=\"field2\"><legend>".t("Recuperar Senha")."</legend>".t("Sua senha foi modificada com sucesso.")."<br /><br />".t("Você já pode fazer o <a href=\"login.php?do=login\">Log In</a> com a nova senha e continuar jogando.")."</fieldset></form>",t("Senha Perdida"),false,false,false);
            }
            $etapa = 1;
        }
    }
    $msg = ($erro != "") ? "<font color=\"brown\"><center>".t($erro)."</center></font><br />" : "";

    if ($etapa >= 1 && $row) {
        $u = htmlspecialchars(stripslashes(html_entity_decode($username, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8');
        $c = htmlspecialchars(stripslashes(html_entity_decode($charname, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8');
        $respostas = $liberado ? "<tr><td colspan=\"2\"><i>".t("A equipe do jogo liberou a recuperação desta conta: as respostas secretas não são pedidas desta vez.")."</i></td></tr>"
            : "<tr><td width=\"30%\">".t($row["pergunta1"])."</td><td><input type=\"password\" name=\"resposta1\" size=\"30\" autocomplete=\"off\" /></td></tr><tr><td>".t($row["pergunta2"])."</td><td><input type=\"password\" name=\"resposta2\" size=\"30\" autocomplete=\"off\" /></td></tr>";
        $corpo = "<input type=\"hidden\" name=\"etapa\" value=\"2\" /><input type=\"hidden\" name=\"username\" value=\"$u\" /><input type=\"hidden\" name=\"charname\" value=\"$c\" />"
            ."<tr><td colspan=\"2\">".tf("Conta <b>%s</b>, personagem <b>%s</b>. Responda tudo:", $u, $c)."<br /><br /></td></tr>".$respostas
            ."<tr><td>".t("Ano em que a conta foi criada:")."</td><td><input type=\"number\" name=\"ano\" min=\"2000\" max=\"2100\" style=\"width:80px\" /></td></tr>"
            ."<tr><td>".t("E-mail cadastrado (deixe vazio se não cadastrou):")."</td><td><input type=\"text\" name=\"email\" size=\"30\" maxlength=\"100\" /></td></tr>"
            ."<tr><td>".t("Level atual do personagem:")."</td><td><input type=\"number\" name=\"level\" min=\"1\" style=\"width:80px\" /></td></tr>"
            ."<tr><td>".t("Nova Senha:")."</td><td><input type=\"password\" name=\"newpass1\" size=\"20\" /></td></tr>"
            ."<tr><td>".t("Verificar Nova Senha:")."</td><td><input type=\"password\" name=\"newpass2\" size=\"20\" /><br /><br /></td></tr>";
    } else {
        $corpo = "<input type=\"hidden\" name=\"etapa\" value=\"1\" /><tr><td colspan=\"2\">".t("Para criar uma senha nova sem e-mail você vai precisar de: nome da conta, nome do personagem, as respostas das 2 perguntas secretas, o ano em que criou a conta, o e-mail cadastrado e o level atual do personagem.")."<br /><br />"
            .t("Se você não cadastrou perguntas secretas ou esqueceu as respostas, fale com um GameMaster ou com o administrador do jogo (pelo fórum, chat ou outro jogador): ele pode liberar a recuperação por 1 hora.")."<br /><br />"
            ."<b><a href=\"users.php?do=recuperarcodigo\">".t("Escolheu o código de recuperação? Clique aqui.")."</a></b><br /><br /></td></tr>"
            ."<tr><td width=\"30%\">".t("Nome da Conta:")."</td><td><input type=\"text\" name=\"username\" size=\"30\" maxlength=\"30\" /></td></tr>"
            ."<tr><td>".t("Nome do Personagem:")."</td><td><input type=\"text\" name=\"charname\" size=\"30\" maxlength=\"30\" /><br /><br /></td></tr>";
    }
    $page = $topo.$msg."<form action=\"users.php?do=recuperar\" method=\"post\" id=\"formback\"><fieldset id=\"field2\"><legend>".t("Recuperar Senha")."</legend><table width=\"100%\">".$corpo
        ."<tr><td colspan=\"2\"><center><div class=\"buttons\"><button type=\"submit\" class=\"positive btnjogo bj-confirmar\" name=\"submit\"><img src=\"images/botao_sim.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Continuar")."</button></div></center></td></tr></table></fieldset></form>";
    display($page, t("Senha Perdida"), false, false, false);

}

/* ------------------------------------------------------------------------
   Código de recuperação (alternativa às perguntas secretas): 32 caracteres sorteados com random_bytes, em grupos
   "XXXX-XXXX-...", mostrados UMA vez e guardados só como hash (users.codigorecuperacao). Recuperar com o código
   (users.php?do=recuperarcodigo) = conta + código; o código usado é consumido e um NOVO é gerado e mostrado uma vez.
   ------------------------------------------------------------------------ */

function gerarcodigorecuperacao() { // Código novo (32 caracteres de um alfabeto sem letras/números parecidos, 8 grupos de 4).

    $alfabeto = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    $codigo = "";
    foreach (str_split(random_bytes(32)) as $i => $b) { $codigo .= $alfabeto[ord($b) % 32].(($i % 4 == 3 && $i < 31) ? "-" : ""); }
    return $codigo;

}

function normalizarcodigo($codigo) { // Só letras/números em maiúsculas (o jogador pode digitar sem os tracinhos ou em minúsculas).

    return preg_replace("/[^A-Z0-9]/", "", strtoupper((string)$codigo));

}

function paginacodigo($codigo, $destino, $titulo) { // Tela que mostra o código UMA vez, com botão de copiar e a caixa "anotei" obrigatória.

    $t = "t";
    $page = titulopagina("minhaconta.gif").<<<FIMDAPAGINA
<form action="$destino" method="get" id="formback"><fieldset id="field2"><legend>{$t("Código de recuperação")}</legend>
<p><b>$titulo</b></p>
<div style="border:2px solid #8b1a1a;background:#fff3cd;padding:8px;margin:6px 0"><b>{$t("Guarde este código de recuperação em um lugar seguro, para sempre.")}</b><br />
{$t("Ele aparece só agora e não pode ser mostrado de novo (nem pela equipe do jogo). Sem ele (e sem e-mail) a sua senha pode ficar bloqueada para sempre. Qualquer pessoa que tiver este código pode tomar a sua conta: não mostre a ninguém.")}</div>
<center><code id="codigorec" style="display:inline-block;white-space:nowrap;font-size:15px;letter-spacing:1px;background:#fff;border:1px solid #452202;padding:8px 12px;margin:8px">$codigo</code><br />
<button type="button" class="btnjogo bj-neutro" onclick="var c=document.getElementById('codigorec').textContent;if(navigator.clipboard){navigator.clipboard.writeText(c);}var r=document.createRange();r.selectNode(document.getElementById('codigorec'));window.getSelection().removeAllRanges();window.getSelection().addRange(r);this.innerHTML='{$t("Copiado!")}';">{$t("Copiar código")}</button><br /><br />
<label><input type="checkbox" required="required" /> {$t("Eu anotei o código em um lugar seguro.")}</label><br /><br />
<div class="buttons"><button type="submit" class="positive btnjogo bj-confirmar"><img src="images/botao_sim.png" width="16" height="16" alt="" /> {$t("Continuar")}</button></div></center>
</fieldset></form>
FIMDAPAGINA;
    display($page, t("Código de recuperação"), false, false, false);

}

function recuperarcodigo() { // Senha nova com o código de recuperação (conta + código). O código é consumido e um novo é mostrado.

    $t = "t";
    $topo = titulopagina("minhaconta.gif");
    $erro = "";
    if (isset($_POST["submit"])) {
        $ip = ipdovisitante();
        $username = pegartexto($_POST, "username");
        $codigo = normalizarcodigo(pegartexto($_POST, "codigo"));
        if (tentativaserradas($ip) >= 5) { $erro = "Muitas tentativas erradas. Aguarde 15 minutos e tente novamente."; }
        else {
            $row = mysqli_fetch_array(doquery("SELECT id,codigorecuperacao FROM {{table}} WHERE username='$username' AND authlevel<>'2' LIMIT 1", "users"));
            if (!$row || $row["codigorecuperacao"] == "" || strlen($codigo) != 32 || !senha_confere($codigo, $row["codigorecuperacao"])) {
                registrartentativaerrada($ip); logrecuperacao("recuperar_codigo_falha", $row ? $row["id"] : 0, stripslashes($username));
                $erro = "Os dados informados não conferem. Confira tudo e tente novamente.";
            } else {
                $newpass1 = pegartexto($_POST, "newpass1");
                $erro = senhavalida($newpass1);
                if ($erro == "" && $newpass1 != pegartexto($_POST, "newpass2")) { $erro = "Novas senhas não coincidem."; }
                if ($erro == "") {
                    // Troca a senha e o código juntos (só se o código ainda for o mesmo: dois envios ao mesmo tempo não usam o mesmo código).
                    $novo = gerarcodigorecuperacao();
                    doquery("UPDATE {{table}} SET password='".addslashes(senha_hash($newpass1))."', codigorecuperacao='".addslashes(senha_hash(normalizarcodigo($novo)))."', resetcode='', resetexpira=NULL, trocarsenha='0', resetliberado='0' WHERE id='".intval($row["id"])."' AND codigorecuperacao='".addslashes($row["codigorecuperacao"])."' LIMIT 1", "users");
                    global $link;
                    if (mysqli_affected_rows($link) == 1) {
                        doquery("DELETE FROM {{table}} WHERE ip='$ip'", "loginfail");
                        logrecuperacao("recuperar_codigo_ok", $row["id"], "");
                        paginacodigo($novo, "login.php", t("Sua senha foi modificada. Este é o seu NOVO código de recuperação (o antigo não vale mais)."));
                    }
                    $erro = "Os dados informados não conferem. Confira tudo e tente novamente.";
                }
            }
        }
    }
    $msg = ($erro != "") ? "<font color=\"brown\"><center>".t($erro)."</center></font><br />" : "";
    $page = <<<FIMDAPAGINA
$topo$msg
<form action="users.php?do=recuperarcodigo" method="post" id="formback"><fieldset id="field2"><legend>{$t("Recuperar com o código")}</legend>
<table width="100%">
<tr><td colspan="2">{$t("Digite o nome da conta e o código de recuperação que você anotou (com ou sem os tracinhos). Depois de usar, ele deixa de valer e você recebe um código novo.")}<br /><br /></td></tr>
<tr><td width="30%">{$t("Nome da Conta:")}</td><td><input type="text" name="username" size="30" maxlength="30" /></td></tr>
<tr><td>{$t("Código de recuperação:")}</td><td><input type="text" name="codigo" size="45" maxlength="60" autocomplete="off" style="font-family:monospace" /></td></tr>
<tr><td>{$t("Nova Senha:")}</td><td><input type="password" name="newpass1" size="20" /></td></tr>
<tr><td>{$t("Verificar Nova Senha:")}</td><td><input type="password" name="newpass2" size="20" /><br /><br /></td></tr>
<tr><td colspan="2"><center><div class="buttons"><button type="submit" class="positive btnjogo bj-confirmar" name="submit"><img src="images/botao_sim.png" width="16" height="16" alt="" /> {$t("Mudar Senha")}</button></div></center></td></tr>
</table></fieldset></form>
<center><a href="users.php?do=recuperar">{$t("Recuperar pelas perguntas secretas")}</a></center>
FIMDAPAGINA;
    display($page, t("Senha Perdida"), false, false, false);

}

function camposmetodo() { // Escolha do jeito de recuperar a conta sem e-mail: perguntas secretas ou código de recuperação.
    // Com JavaScript, escolher o código esconde as perguntas (e desliga os campos, que nem são enviados); sem JavaScript
    // tudo aparece e o servidor simplesmente ignora as perguntas quando o método é "codigo" (register() / perguntas()).

    $js = "<script>(function(){function m(){var c=document.querySelector('input[name=metodo][value=codigo]');var b=document.getElementById('blocoperguntas');if(!c||!b)return;b.style.display=c.checked?'none':'';var f=b.querySelectorAll('input,select');for(var i=0;i<f.length;i++)f[i].disabled=c.checked;}"
        ."var r=document.querySelectorAll('input[name=metodo]');for(var i=0;i<r.length;i++)r[i].addEventListener('change',m);var fm=r.length?r[0].form:null;if(fm)fm.addEventListener('reset',function(){setTimeout(m,0);});m();})();</script>";
    return "<tr><td colspan=\"2\"><b>".t("Como você quer recuperar a conta se esquecer a senha?")."</b><br />"
        ."<label><input type=\"radio\" name=\"metodo\" value=\"perguntas\" checked=\"checked\" /> ".t("Perguntas secretas (preencha abaixo)")."</label><br />"
        ."<label><input type=\"radio\" name=\"metodo\" value=\"codigo\" /> ".t("Código de recuperação: o jogo gera um código longo, mostrado uma única vez, que você precisa guardar (as perguntas abaixo são ignoradas)")."</label></td></tr>"
        ."</tbody><tbody id=\"blocoperguntas\">".camposperguntas()."</tbody><tbody><tr><td colspan=\"2\">$js</td></tr>";

}

function perguntas() { // Recuperação da conta (pede a senha atual): cadastrar/trocar as 2 perguntas secretas OU gerar um código de recuperação novo. Um método apaga o outro.

    global $userrow;
    $t = "t";
    exigirjogador(false);
    $topo = titulopagina("minhaconta.gif");
    $mensagem = "";
    if (isset($_POST["submit"])) {
        $ip = ipdovisitante();
        $erros = array();
        if (!csrf_confere()) { $erros[] = "A sessão do formulário expirou. Tente novamente."; }
        elseif (tentativaserradas($ip) >= 5) { $erros[] = "Muitas tentativas erradas. Aguarde 15 minutos e tente novamente."; }
        elseif (!senha_confere(pegartexto($_POST, "senha"), $userrow["password"])) { registrartentativaerrada($ip); $erros[] = "Senha atual incorreta."; }
        elseif (pegartexto($_POST, "metodo") == "codigo") {
            $novo = gerarcodigorecuperacao();
            doquery("UPDATE {{table}} SET codigorecuperacao='".addslashes(senha_hash(normalizarcodigo($novo)))."', pergunta1='', resposta1='', pergunta2='', resposta2='' WHERE id='".intval($userrow["id"])."' LIMIT 1","users");
            paginacodigo($novo, "index.php", t("Este é o seu novo código de recuperação (o antigo e as perguntas secretas não valem mais):"));
        } else {
            $sql = lerperguntas($erros);
            if ($sql != "") {
                doquery("UPDATE {{table}} SET $sql, codigorecuperacao='' WHERE id='".intval($userrow["id"])."' LIMIT 1","users");
                $userrow["pergunta1"] = "x"; $userrow["codigorecuperacao"] = "";
                $erros[] = "Perguntas secretas salvas.";
            }
        }
        foreach ($erros as $e) { $mensagem .= t($e)."<br />"; }
        $mensagem = "<font color=\"brown\"><center>$mensagem</center></font><br />";
    }
    if ($userrow["codigorecuperacao"] != "") { $situacao = t("Hoje a sua conta usa um código de recuperação. Gerar outro invalida o anterior; salvar perguntas apaga o código."); }
    elseif ($userrow["pergunta1"] != "") { $situacao = t("Você já tem perguntas secretas cadastradas. Salvar de novo substitui as antigas."); }
    else { $situacao = t("Sua conta ainda não tem um jeito de recuperar a senha sem e-mail. Escolha um abaixo."); }
    $csrf = csrf_campo();
    $campos = camposmetodo();
    $page = <<<FIMDAPAGINA
$topo$mensagem
<form action="users.php?do=perguntas" method="post" id="formback"><fieldset id="field2"><legend>{$t("Recuperação da conta")}</legend>$csrf
<table width="100%"><tbody>
<tr><td colspan="2"><b>$situacao</b><br /><br /></td></tr>
<tr><td width="30%">{$t("Senha atual:")}</td><td><input type="password" name="senha" size="20" autocomplete="current-password" /></td></tr>
$campos
<tr><td colspan="2"><center><div class="buttons"><button type="submit" class="positive btnjogo bj-confirmar" name="submit"><img src="images/botao_sim.png" width="16" height="16" alt="" /> {$t("Salvar")}</button></div></center></td></tr>
</tbody></table></fieldset></form>
<center><a href="index.php">{$t("Voltar ao jogo")}</a></center>
FIMDAPAGINA;
    display($page, t("Recuperação da conta"), false, false, false);

}

function resetpassword() { // Abre o link enviado pelo lostpassword() e grava a nova senha escolhida pelo jogador.

    $t = "t";
    $topo = titulopagina("minhaconta.gif");
    $id = pegarint($_GET, "id");
    $codigo = pegartexto($_GET, "code");

    // O código é o resetcode da conta (32 caracteres hexadecimais) e só vale até resetexpira.
    $row = false;
    if ($id > 0 && preg_match("/^[0-9a-f]{32}$/", $codigo) == 1) {
        $userquery = doquery("SELECT id,resetcode FROM {{table}} WHERE id='$id' AND resetcode<>'' AND resetexpira > NOW() LIMIT 1","users");
        if (mysqli_num_rows($userquery) == 1) { $row = mysqli_fetch_array($userquery); }
    }
    if ($row == false || !hash_equals((string)$row["resetcode"], $codigo)) {
        display($topo."<form id=\"formback\"><fieldset id=\"field2\"><legend>".t("Recuperar Senha")."</legend>".t("Este link é inválido ou já expirou.")."<br /><br />".t("Você pode <a href=\"users.php?do=lostpassword\">pedir um novo link</a>.")."</fieldset></form>",t("Senha Perdida"),false,false,false);
    }

    $erro = "";
    if (isset($_POST["submit"])) {
        $newpass1 = pegartexto($_POST, "newpass1");
        $newpass2 = pegartexto($_POST, "newpass2");
        $erro = senhavalida($newpass1);
        if ($erro == "" && $newpass1 != $newpass2) { $erro = "Novas senhas não coincidem."; }
        if ($erro == "") {
            // A senha nova invalida os cookies antigos (a assinatura do cookie depende do hash) e o link deixa de valer.
            doquery("UPDATE {{table}} SET password='".addslashes(senha_hash($newpass1))."', resetcode='', resetexpira=NULL WHERE id='".intval($row["id"])."' LIMIT 1","users");
            display($topo."<form id=\"formback\"><fieldset id=\"field2\"><legend>".t("Recuperar Senha")."</legend>".t("Sua senha foi modificada com sucesso.")."<br /><br />".t("Você já pode fazer o <a href=\"login.php?do=login\">Log In</a> com a nova senha e continuar jogando.")."</fieldset></form>",t("Senha Perdida"),false,false,false);
        }
        $erro = "<font color=\"brown\"><center>".t($erro)."</center></font><br />";
    }

    $page = <<<FIMDAPAGINA
$topo$erro
<form action="users.php?do=resetpassword&amp;id=$id&amp;code=$codigo" method="post" id="formback"><fieldset id="field2"><legend>{$t("Recuperar Senha")}</legend>
<table width="100%">
<tr><td colspan="2">{$t("Escolha a sua nova senha. Ela deve ter de 6 a 72 caracteres (pode usar qualquer caractere).")}</td></tr>
<tr><td width="20%">{$t("Nova Senha:")}</td><td><input type="password" name="newpass1" size="20" /></td></tr>
<tr><td>{$t("Verificar Nova Senha:")}</td><td><input type="password" name="newpass2" size="20" /><br /><br /><br /></td></tr>
<tr><td colspan="2"><center><div class="buttons"><button type="submit" class="positive btnjogo bj-confirmar" name="submit"><img src="images/botao_sim.png" width="16" height="16" alt="" /> {$t("Mudar Senha")}</button>
<button type="reset" class="negative btnjogo bj-cancelar" name="reset"><img src="images/botao_limpar.png" width="16" height="16" alt="" /> {$t("Resetar")}</button>
</div></center></td></tr>
</table>
</fieldset></form>
FIMDAPAGINA;
    display($page, t("Senha Perdida"), false, false, false);

}

function changepassword() { // Troca a senha informando o nome da conta e a senha antiga (não precisa estar logado).

    global $conteudouser;

    if (isset($_POST["submit"])) {

        $username = pegartexto($_POST, "username");
        $oldpass = pegartexto($_POST, "oldpass");
        $newpass1 = pegartexto($_POST, "newpass1");
        $newpass2 = pegartexto($_POST, "newpass2");

        // Este formulário também confere senha, então usa o mesmo limite de tentativas do login (senão serviria para adivinhar senhas).
        $ip = ipdovisitante();
        if (tentativaserradas($ip) >= 5) { voltarcommensagem("users.php?do=changepassword", "Muitas tentativas erradas. Aguarde 15 minutos e tente novamente."); }

        $userquery = doquery("SELECT id,password,trocarsenha FROM {{table}} WHERE username='$username' LIMIT 1","users");
        $row = (mysqli_num_rows($userquery) == 1) ? mysqli_fetch_array($userquery) : false;
        if ($row == false || !senha_confere($oldpass, $row["password"])) {
            registrartentativaerrada($ip);
            voltarcommensagem("users.php?do=changepassword", "Nome de usuário ou senha antiga incorretos.");
        }
        $erro = senhavalida($newpass1);
        if ($erro != "") { voltarcommensagem("users.php?do=changepassword", $erro); }
        if ($newpass1 != $newpass2) { voltarcommensagem("users.php?do=changepassword", "Novas senhas não coincidem."); }

        if ($row["trocarsenha"] == 1 && senha_confere($newpass1, $row["password"])) { voltarcommensagem("users.php?do=changepassword", "A nova senha deve ser diferente da antiga."); }

        // A troca também encerra uma senha provisória dada pela equipe (users.trocarsenha).
        doquery("UPDATE {{table}} SET password='".addslashes(senha_hash($newpass1))."', resetcode='', resetexpira=NULL, trocarsenha='0' WHERE id='".intval($row["id"])."' LIMIT 1","users");
        // Com a senha nova, a assinatura do cookie muda: desconecta o jogador para ele entrar de novo.
        apagarcookie();
        display(titulopagina("minhaconta.gif")."<form id=\"formback\"><fieldset id=\"field2\"><legend>".t("Mudar Senha")."</legend>".t("Sua senha foi modificada com sucesso.")."<br /><br />".t("Você foi desconectado do jogo para evitar erros.")."<br /><br />".t("Por favor, <a href=\"login.php?do=login\">faça o login novamente</a> para continuar jogando.")."<br /></fieldset></form>",t("Mudar Senha"),false,false,false);

    }

    $conteudouser = mensagemtopo();
    $page = gettemplate("changepassword");
    // Logado: link para cadastrar as perguntas secretas de recuperação.
    global $userrow;
    if ($userrow != false) { $page .= "<center><a href=\"users.php?do=perguntas\">".t("Recuperação da conta (perguntas secretas ou código)")."</a>".(($userrow["pergunta1"] != "" || $userrow["codigorecuperacao"] != "") ? " <span class=\"light\">(".t("configurada").")</span>" : "")."</center>"; }
    display($page, t("Mudar Senha"), false, false, false);

}

/* ------------------------------------------------------------------------
   E-mails enviados pelo jogo (sempre no idioma em uso no momento do pedido).
   ------------------------------------------------------------------------ */

function enderecodojogo() { // Endereço base do jogo (control.gameurl sem o "index.php" do final), usado nos links dos e-mails.

    global $controlrow;
    $url = trim(html_entity_decode($controlrow["gameurl"], ENT_QUOTES, 'UTF-8'));
    $url = preg_replace("/index\.php$/i", "", $url);
    if (substr($url, -1) != "/") { $url .= "/"; }
    return $url;

}

function textoemail($texto) { // Os textos traduzidos podem ter entidades HTML; o e-mail é texto puro.

    return html_entity_decode($texto, ENT_QUOTES, 'UTF-8');

}

function sendpassemail($emailaddress, $endereco) { // E-mail da senha perdida, com o link para criar a nova senha.

    global $controlrow;
    $gamename = html_entity_decode($controlrow["gamename"], ENT_QUOTES, 'UTF-8');
    $gameurl = html_entity_decode($controlrow["gameurl"], ENT_QUOTES, 'UTF-8');

    $email = tf("Você ou alguém usando a sua conta de e-mail utilizou a função de Senha Perdida do jogo %s, localizado em %s.", $gamename, $gameurl)."\n\n"
        .t("Para criar uma nova senha, acesse o link abaixo (ele vale por 1 hora e só pode ser usado uma vez):")."\n"
        .$endereco."\n\n"
        .t("Se não foi você quem pediu, ignore esta mensagem: sua senha atual continua valendo.")."\n\n"
        .t("Obrigado por jogar.")."\n"
        .$gamename;

    return mymail($emailaddress, tf("%s - Senha Perdida", $gamename), textoemail($email));

}

function sendregmail($emailaddress, $vercode) { // E-mail com o código de ativação da conta (usado no registro e no ativarconta.php).

    global $controlrow;
    $gamename = html_entity_decode($controlrow["gamename"], ENT_QUOTES, 'UTF-8');
    $gameurl = html_entity_decode($controlrow["gameurl"], ENT_QUOTES, 'UTF-8');
    $verurl = enderecodojogo()."users.php?do=verify";

    $email = tf("Você ou alguém usando sua conta de e-mail recentemente se registrou no jogo %s, localizado em %s.", $gamename, $gameurl)."\n\n"
        .t("Esse e-mail é enviado para verificar seu e-mail de registro. Para começar a utilizar a sua conta, você deve verificar o seu e-mail.")."\n"
        .tf("Por favor visite a Página de Verificação (%s) e preencha com o código de ativação abaixo:", $verurl)."\n"
        .tf("Código de Ativação: %s", $vercode)."\n\n"
        .t("Se você não é a pessoa que se registrou no jogo, por favor ignore essa mensagem. Você não receberá outro e-mail.");

    return mymail($emailaddress, tf("%s - Verificação da Conta", $gamename), textoemail($email));

}

function limparcabecalho($texto) { // Remove quebras de linha de um valor que vai para o cabeçalho do e-mail (impede a injeção de cabeçalhos).

    return trim(str_replace(array("\r", "\n", "\0"), "", html_entity_decode((string)$texto, ENT_QUOTES, 'UTF-8')));

}

function mymail($to, $title, $body, $from = '') { // Envia um e-mail em texto puro (UTF-8). Remetente padrão: control.adminemail.

    global $controlrow;

    $to = limparcabecalho($to);
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) { return false; }
    $from = limparcabecalho($from);
    if ($from == "" || filter_var($from, FILTER_VALIDATE_EMAIL) === false) { $from = limparcabecalho($controlrow["adminemail"]); }
    if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) { error_log("Naruto RPG: control.adminemail não é um e-mail válido; e-mail não enviado."); return false; }

    // Textos com acento no cabeçalho (nome do jogo, assunto) precisam da codificação MIME "=?UTF-8?B?...?=".
    $nomejogo = mb_encode_mimeheader(limparcabecalho($controlrow["gamename"]), "UTF-8", "B", "\r\n");
    if (strpos($nomejogo, "=?") === false) { $nomejogo = "\"".addcslashes($nomejogo, "\"\\")."\""; } // Nome sem acento vai entre aspas (pode ter vírgula, dois-pontos etc.).
    $assunto = mb_encode_mimeheader(limparcabecalho($title), "UTF-8", "B", "\r\n");
    $org = mb_encode_mimeheader(limparcabecalho($controlrow["gameurl"]), "UTF-8", "B", "\r\n");

    $head  = "MIME-Version: 1.0\r\n";
    $head .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $head .= "Content-Transfer-Encoding: quoted-printable\r\n";
    $head .= "Date: ".date('r')."\r\n";
    $head .= "Return-Path: <$from>\r\n";
    $head .= "From: $nomejogo <$from>\r\n";
    $head .= "Reply-To: <$from>\r\n";
    $head .= "Organization: $org\r\n";
    $head .= "X-Priority: 3\r\n";
    $head .= "X-Mailer: PHP";

    $body = str_replace("\r\n", "\n", $body);
    $body = quoted_printable_encode(str_replace("\n", "\r\n", $body));

    // O @ evita que um servidor sem sendmail mostre avisos na página; a falha fica registrada no log.
    $enviado = @mail($to, $assunto, $body, $head);
    if (!$enviado) { error_log("Naruto RPG: mail() falhou ao enviar \"".limparcabecalho($title)."\"."); }
    return $enviado;

}

/* ------------------------------------------------------------------------
   Doar Ryou para outro jogador.
   ------------------------------------------------------------------------ */

function doardinheiro() { // Doa Ryou (ouro que o jogador carrega) para outro jogador. Só dentro de uma cidade e a partir do nível mínimo de PvP.

    global $userrow, $controlrow, $link;
    exigirjogador(true);
    $nivelminimo = intval($controlrow["pvplevel"]);
    if ($userrow["level"] < $nivelminimo) { display(tf("Você não pode acessar essa função se seu level for menor que %s!", $nivelminimo),t("Erro"),false,false,false); }

    if (isset($_POST["submit"])) {

        if (!csrf_confere()) { display(t("Formulário expirado. Tente novamente."),t("Erro"),false,false,false); } // CSRF: doação só pelo próprio formulário.
        $jogador = pegartexto($_POST, "jogador");
        $quantia = pegarint($_POST, "quantia", -1);

        $alvoquery = doquery("SELECT id,gold FROM {{table}} WHERE charname='$jogador' LIMIT 1","users");
        if (mysqli_num_rows($alvoquery) != 1) { display(titulopagina("minhaconta.gif").t("Não existe nenhuma conta com esse Nome."),t("Erro"),false,false,false); }
        $alvorow = mysqli_fetch_array($alvoquery);
        if ($alvorow["id"] == $userrow["id"]) { display(t("Você não pode doar Ryou para si mesmo."),t("Erro"),false,false,false); }
        if (!isset($_POST["quantia"]) || !is_numeric($_POST["quantia"])) { display(t("A quantidade de Ryou deve ser um número."),t("Erro"),false,false,false); }
        if ($quantia < 1) { display(t("Você não pode doar menos que 1 Ryou."),t("Erro"),false,false,false); }
        if ($quantia > $userrow["gold"]) { display(t("Você não pode doar mais do que a sua quantidade de Ryou."),t("Erro"),false,false,false); }
        if ($alvorow["gold"] + $quantia > 16777215) { display(t("Esse jogador não pode carregar tanto Ryou."),t("Erro"),false,false,false); } // Limite da coluna gold.

        // Numa transação: retira do doador com a condição gold >= quantia na própria query (duas doações ao mesmo
        // tempo não gastam o mesmo Ryou duas vezes) e só credita se o outro jogador não passar do limite da coluna
        // (antes o LEAST() cortava o excesso e esse Ryou sumia). Se uma das duas falhar, nada é gravado.
        doquery("START TRANSACTION","users");
        doquery("UPDATE {{table}} SET gold=gold-$quantia WHERE id='".intval($userrow["id"])."' AND gold >= $quantia LIMIT 1","users");
        if (mysqli_affected_rows($link) != 1) { doquery("ROLLBACK","users"); display(t("Você não pode doar mais do que a sua quantidade de Ryou."),t("Erro"),false,false,false); }
        doquery("UPDATE {{table}} SET gold=gold+$quantia WHERE id='".intval($alvorow["id"])."' AND gold <= ".(16777215 - $quantia)." LIMIT 1","users");
        if (mysqli_affected_rows($link) != 1) { doquery("ROLLBACK","users"); display(t("Esse jogador não pode carregar tanto Ryou."),t("Erro"),false,false,false); }
        doquery("COMMIT","users");

        display(t("O dinheiro foi retirado da sua conta e doado com sucesso.")."<br /><br />".t("Você pode <a href=\"index.php\">clicar aqui</a> para continuar jogando ou <a href=\"users.php?do=doardinheiro\">doar mais Ryou</a>."),t("Doar Ryou"),false,false,false);

    }

    $page = titulopagina("doaryou.gif")."<b>".t("Seu Ryou")."</b>: ".iconeryou().number_format((float)$userrow["gold"])."<br /><br />".str_replace("</form>", csrf_campo()."</form>", gettemplate("doardinheiro"));
    display($page, t("Doar Ryou"), false, false, false);

}

/* ------------------------------------------------------------------------
   Duelo PvP (jogador contra jogador), por turnos.

   Regras:
   - Desafio: os dois jogadores precisam estar online (últimos 10 minutos), na mesma casa do mapa (ou na mesma
     cidade), fora de luta contra monstros, sem troca em andamento, sem outro duelo, com mais de 1 HP e a partir
     do level mínimo de PvP (control.pvplevel). Cada jogador tem no máximo 1 desafio enviado (um novo cancela o
     anterior); o desafio vale por 5 minutos. O desafiado recebe um aviso no histórico e aceita ou recusa no saguão.
   - Aposta opcional em Ryou: os dois precisam ter o valor. Ao aceitar, o Ryou dos dois sai da conta e fica
     guardado no duelo; o vencedor leva tudo (2 x aposta). Não há perda de itens.
   - Começa quem tiver mais rand(1,10) + raiz(Destreza) (empate: o desafiado).
   - Cada turno dura 60 segundos. Tempo esgotado = perde a vez; 3 tempos esgotados = derrota por abandono.
   - Ações: atacar, jutsu (custo com a Economia de Chakra), defender (metade do dano até a próxima vez),
     poção da mochila (HP ou CH) e desistir (a qualquer momento).
   - Dano: as mesmas fórmulas da luta contra monstros, com os atributos dos dois jogadores:
       ataque = 75% a 100% do Poder de Ataque / 3; hit excelente pela Força/Determinação ou Chance de Crítico (x2);
       bloqueio = 75% a 100% do Poder de Defesa do alvo / 4 (a regra da defesa do jogador na luta PvE);
       esquiva do alvo = rand(1,150) - 2% da Agilidade dele + 3% da Precisão do atacante <= raiz(Destreza dele),
       ou rand(1,100) <= Esquiva dos equipamentos dele (máx. 40);
       jutsu de dano = 5/6 a 100% da força do jutsu; elementos como no PvE (elemento do jutsu, ou elementoatk
       com um jutsu de +Dano ativo e EP, contra o 1º elemento do alvo), com o bônus de pontos do atacante;
       +Dano/+Defesa dos jutsus valem até o fim do duelo; jutsus de sono não aparecem (jogadores são imunes).
   - Jutsus configuráveis (funcoesjutsus.php): tipos 1, 2 e 6 passam pelo aplicarjutsu() (golpes, roubo de vida,
     efeito extra, recarga); nos tipos 4 e 5 só o efeito extra (jutsuaplicarefeito). O dano do jutsu ainda passa
     pela +Defesa, pela guarda e por jutsureduzirdano() (ataque enfraquecido, defesa reforçada, escudo); o ataque
     comum usa também jutsumultdano(), Foco crítico e Esquiva aumentada. Uma "rodada" = 2 turnos: no fim de cada
     turno par roda jutsufimdoturno() (queimadura/veneno/regeneração, contagem dos efeitos e recargas). Atordoar e
     Ataque enfraquecido aplicados num turno par ganham +1 turno (a contagem vem logo em seguida e eles sumiriam
     antes de o alvo jogar). Quem está atordoado perde a vez sozinho (conta como jogada, não como tempo esgotado).
     Dano por turno também não mata: quem chega a 1 HP perde o duelo.
   - HP: o dano é real (o HP perdido continua perdido), mas ninguém morre: quando o HP chegaria a 1 ou menos,
     o jogador fica com 1 HP e perde o duelo.
   - Fim: o vencedor recebe a aposta e um pouco de experiência (5 x level do perdedor, só se o duelo teve pelo
     menos 4 turnos — desistir logo no início não dá experiência). Vitórias/derrotas vão para
     users.duelos_vencidos / duelos_perdidos (ficha do personagem e rank.php?ord=duelos).

   Onde fica o estado:
   - Tabela duelos (uma linha por desafio): desafiante, desafiado, estado (pendente, ativo, fim, recusado,
     cancelado, expirado), aposta, vez (id de quem joga), prazo (fim do turno, UNIX), turno, hpantes1/2 (HP no
     início), dano1/defesa1 e dano2/defesa2 (bônus dos jutsus), guarda1/2 (defendendo), atrasos1/2 (tempos
     esgotados), vencedor, registro (últimas 8 ações, uma por linha, no formato neutro i18n: — cada jogador
     lê no próprio idioma) e jutsuestado (efeitos de jutsu e recargas, JSON do funcoesjutsus.php com lados 1 e 2). O lado 1 é sempre o desafiante e o lado 2 o desafiado.
   - users.batalha_timer2 = 5 enquanto o jogador está num duelo ativo (as outras páginas bloqueiam qualquer
     ação) e users.batalha_nome = nome do oponente.
   - Toda mudança acontece numa transação, com a linha do duelo e as dos dois jogadores travadas
     (SELECT ... FOR UPDATE, sempre nessa ordem: duelo e depois jogadores por id crescente). Os formulários
     levam o número do turno: dois cliques (ou duas abas) não jogam duas vezes nem pagam a aposta duas vezes.
   ------------------------------------------------------------------------ */

function limparduelo($id) { // Tira a marca de duelo do jogador (fim, cancelamento ou marca antiga presa).

    doquery("UPDATE {{table}} SET currentuberdamage='0', currentuberdefense='0', batalha_nome='None', batalha_hp='0', batalha_timer='0', batalha_timer2='0', batalha_id='0', batalha_acao='None' WHERE id='".intval($id)."' LIMIT 1","users");

}

function avisarnohistorico($id, $aviso) { // Acrescenta um aviso (formato i18n:) no histórico de outro jogador sem ler e regravar o campo inteiro.

    $aviso = addslashes($aviso);
    doquery("UPDATE {{table}} SET historico=IF(historico='None' OR historico='', '$aviso', CONCAT(historico, ';;', '$aviso')) WHERE id='".intval($id)."' LIMIT 1","users");

}

function jogadoronline($row) { // true se o jogador fez alguma coisa nos últimos 10 minutos (coluna "online" calculada pelo banco nas consultas do duelo).

    return (isset($row["online"]) && $row["online"] == 1);

}

function duelocarregar($id, $estado, $travar) { // Duelo mais recente do jogador nesse estado (ou false). $travar = SELECT ... FOR UPDATE.

    $id = intval($id);
    $query = doquery("SELECT * FROM {{table}} WHERE (desafiante='$id' OR desafiado='$id') AND estado='".addslashes($estado)."' ORDER BY id DESC LIMIT 1".($travar ? " FOR UPDATE" : ""), "duelos");
    $row = mysqli_fetch_array($query);
    return $row ? $row : false;

}

function duelotravarjogadores($duelo) { // Lê e trava os dois jogadores do duelo (id menor primeiro). Devolve array(1 => desafiante, 2 => desafiado).

    $a = intval($duelo["desafiante"]); $b = intval($duelo["desafiado"]);
    $lista = array();
    $query = doquery("SELECT *, (UNIX_TIMESTAMP(onlinetime) >= UNIX_TIMESTAMP() - 600) AS online FROM {{table}} WHERE id IN ('$a','$b') ORDER BY id FOR UPDATE", "users");
    while ($row = mysqli_fetch_array($query)) { $lista[intval($row["id"])] = $row; }
    if (!isset($lista[$a]) || !isset($lista[$b])) { return false; }
    return array(1 => $lista[$a], 2 => $lista[$b]);

}

function dueloregistrar(&$duelo, $texto) { // Acrescenta uma linha (formato i18n:) ao registro do duelo, guardando só as últimas.

    $linhas = ($duelo["registro"] == "") ? array() : explode("\n", $duelo["registro"]);
    $linhas[] = str_replace(array("\r", "\n"), " ", $texto);
    $duelo["registro"] = implode("\n", array_slice($linhas, -DUELO_TAMANHOREGISTRO));

}

function duelogravar($duelo) { // Grava os campos variáveis do duelo (dentro da transação aberta).

    $campos = array("estado","vez","prazo","turno","dano1","defesa1","dano2","defesa2","guarda1","guarda2","atrasos1","atrasos2","vencedor","jutsuestado");
    $sql = array();
    foreach ($campos as $c) { $sql[] = "$c='".addslashes((string)$duelo[$c])."'"; }
    $sql[] = "registro='".addslashes($duelo["registro"])."'";
    $sql[] = "atualizado='".time()."'";
    doquery("UPDATE {{table}} SET ".implode(",", $sql)." WHERE id='".intval($duelo["id"])."' LIMIT 1", "duelos");

}

function duelopassarvez(&$duelo) { // Passa a vez para o outro jogador e reinicia o relógio do turno.

    $duelo["vez"] = ($duelo["vez"] == $duelo["desafiante"]) ? $duelo["desafiado"] : $duelo["desafiante"];
    $lado = ($duelo["vez"] == $duelo["desafiante"]) ? 1 : 2;
    $duelo["guarda".$lado] = 0; // A defesa vale só até a próxima vez de quem defendeu.
    $duelo["turno"] = intval($duelo["turno"]) + 1;
    $duelo["prazo"] = time() + DUELO_TEMPOTURNO;

}

function duelogravarjogadores($jogadores) { // Grava HP e CH dos dois jogadores (dano por turno, regeneração, dreno de chakra), dentro da transação.

    foreach (array(1, 2) as $l) { doquery("UPDATE {{table}} SET currenthp='".max(1, intval($jogadores[$l]["currenthp"]))."', currentmp='".max(0, intval($jogadores[$l]["currentmp"]))."' WHERE id='".intval($jogadores[$l]["id"])."' LIMIT 1", "users"); }

}

function dueloatordoado($duelo, $lado) { // O jogador desse lado (1 ou 2) está atordoado por um jutsu?

    return jutsuatordoado(jutsuestadoler(isset($duelo["jutsuestado"]) ? $duelo["jutsuestado"] : ""), $lado);

}

function dueloticar(&$duelo, &$jogadores) { // Fim de rodada (depois de cada turno par): jutsufimdoturno() nos dois lados. Devolve o lado que caiu para 1 HP (ou 0).

    if (intval($duelo["turno"]) % 2 != 0) { return 0; }
    $estado = jutsuestadoler(isset($duelo["jutsuestado"]) ? $duelo["jutsuestado"] : "");
    if (empty($estado["efeitos"]) && empty($estado["cd"])) { return 0; }
    $lados = array();
    foreach (array(1, 2) as $l) { $lados[$l] = array("nome"=>$jogadores[$l]["charname"], "hp"=>intval($jogadores[$l]["currenthp"]), "maxhp"=>intval($jogadores[$l]["maxhp"])); }
    foreach (jutsufimdoturno($estado, $lados) as $m) { dueloregistrar($duelo, $m); }
    $duelo["jutsuestado"] = jutsuestadotexto($estado);
    $caiu = 0;
    foreach (array(1, 2) as $l) {
        // Ninguém morre: o dano por turno também para em 1 HP (e quem chega lá perde).
        if ($lados[$l]["hp"] <= 1 && intval($jogadores[$l]["currenthp"]) > 1 && $caiu == 0) { $caiu = $l; }
        $jogadores[$l]["currenthp"] = max(1, $lados[$l]["hp"]);
    }
    return $caiu;

}

function duelofimdavez(&$duelo, &$jogadores) { // Depois de uma jogada (ou de um tempo esgotado): fim de rodada, passa a vez e pula a vez de quem está atordoado. Devolve o lado que caiu (ou 0).

    for ($voltas = 0; $voltas < 6; $voltas++) {
        $caiu = dueloticar($duelo, $jogadores);
        if ($caiu) { return $caiu; }
        duelopassarvez($duelo);
        $lado = ($duelo["vez"] == $duelo["desafiante"]) ? 1 : 2;
        if (!dueloatordoado($duelo, $lado)) { return 0; }
        // Atordoado: perde a vez na hora (conta como jogada, não como tempo esgotado).
        dueloregistrar($duelo, i18n("%s está atordoado e perde a vez.", $jogadores[$lado]["charname"]));
    }
    return 0;

}

function dueloencerrar(&$duelo, $jogadores, $ladovencedor, $motivo) { // Fim do duelo: aposta, experiência, placar, avisos e limpeza (dentro da transação).

    $ladoperdedor = 3 - $ladovencedor;
    $vencedor = $jogadores[$ladovencedor];
    $perdedor = $jogadores[$ladoperdedor];
    $duelo["estado"] = "fim";
    $duelo["vencedor"] = intval($vencedor["id"]);
    $duelo["prazo"] = 0;
    $duelo["jutsuestado"] = ""; // efeitos e recargas acabam com o duelo.

    // Prêmio: as duas apostas (já retiradas no início) e experiência se o duelo foi de verdade (4 turnos ou mais).
    $premio = 2 * intval($duelo["aposta"]);
    $exp = (intval($duelo["turno"]) >= 4) ? 5 * max(1, intval($perdedor["level"])) : 0;
    doquery("UPDATE {{table}} SET gold=LEAST(16777215, gold+$premio), experience=LEAST(16777215, experience+$exp), duelos_vencidos=LEAST(65535, duelos_vencidos+1) WHERE id='".intval($vencedor["id"])."' LIMIT 1", "users");
    doquery("UPDATE {{table}} SET duelos_perdidos=LEAST(65535, duelos_perdidos+1) WHERE id='".intval($perdedor["id"])."' LIMIT 1", "users");
    // Temporada de duelo: pontos de vitória/derrota (funcoescla.php).
    include_once('funcoescla.php'); temporadaduelo($vencedor["id"], $perdedor["id"]);
    limparduelo($vencedor["id"]);
    limparduelo($perdedor["id"]);
    include_once('funcoesdiarias.php'); diarias_contar($vencedor["id"], "pvp", 1); // missões diárias: duelo vencido.

    if ($motivo == "desistencia") { dueloregistrar($duelo, i18n("%s desistiu do duelo.", $perdedor["charname"])); }
    elseif ($motivo == "tempo") { dueloregistrar($duelo, i18n("%s esgotou o tempo %s vezes e perdeu o duelo.", $perdedor["charname"], DUELO_MAXATRASOS)); }
    else { dueloregistrar($duelo, i18n("%s ficou com 1 HP e não pode continuar.", $perdedor["charname"])); }
    dueloregistrar($duelo, i18n("%s venceu o duelo!", $vencedor["charname"]));

    // Avisos no histórico (cada um lê no próprio idioma).
    avisarnohistorico($vencedor["id"], i18n("Você venceu o duelo contra %s e recebeu %s Ryou e %s de experiência.", $perdedor["charname"], $premio, $exp));
    avisarnohistorico($perdedor["id"], i18n("Você perdeu o duelo contra %s.", $vencedor["charname"]));

}

function dueloverificarprazo(&$duelo, &$jogadores) { // Turnos com tempo esgotado: o jogador da vez perde a vez; no 3º, perde o duelo. Devolve true se mudou algo.

    $mudou = false;
    $voltas = 0;
    while ($duelo["estado"] == "ativo" && time() > intval($duelo["prazo"]) && $voltas < 2 * DUELO_MAXATRASOS) {
        $lado = ($duelo["vez"] == $duelo["desafiante"]) ? 1 : 2;
        $duelo["atrasos".$lado] = intval($duelo["atrasos".$lado]) + 1;
        $mudou = true;
        $voltas++;
        if ($duelo["atrasos".$lado] >= DUELO_MAXATRASOS) {
            dueloencerrar($duelo, $jogadores, 3 - $lado, "tempo");
            break;
        }
        dueloregistrar($duelo, i18n("%s não agiu a tempo e perdeu a vez (%s de %s).", $jogadores[$lado]["charname"], $duelo["atrasos".$lado], DUELO_MAXATRASOS));
        // O próximo prazo conta a partir do fim do anterior (assim um duelo abandonado termina sozinho).
        $prazoanterior = intval($duelo["prazo"]);
        $caiu = duelofimdavez($duelo, $jogadores); // o turno perdido também fecha a rodada (efeitos por turno).
        if ($caiu) { duelogravarjogadores($jogadores); dueloencerrar($duelo, $jogadores, 3 - $caiu, "hp"); break; }
        $duelo["prazo"] = $prazoanterior + DUELO_TEMPOTURNO;
    }
    if ($mudou && $duelo["estado"] == "ativo") { duelogravarjogadores($jogadores); }
    return $mudou;

}

function dueloatualizar($id) { // Abre o duelo ativo do jogador e resolve os tempos esgotados (numa transação curta).

    doquery("START TRANSACTION", "duelos");
    $duelo = duelocarregar($id, "ativo", true);
    if ($duelo && time() > intval($duelo["prazo"])) {
        $jogadores = duelotravarjogadores($duelo);
        if ($jogadores && dueloverificarprazo($duelo, $jogadores)) { duelogravar($duelo); }
    }
    doquery("COMMIT", "duelos");

}

function dueloforcarderrota($id) { // Encerra o duelo ativo do jogador como desistência (admin "mandar para a vila", GM "destravar"). Devolve true se havia duelo.

    doquery("START TRANSACTION", "duelos");
    $duelo = duelocarregar($id, "ativo", true);
    if (!$duelo) { doquery("COMMIT", "duelos"); return false; }
    $jogadores = duelotravarjogadores($duelo);
    if (!$jogadores) {
        // Um dos jogadores não existe mais: só cancela o duelo.
        doquery("UPDATE {{table}} SET estado='cancelado', atualizado='".time()."' WHERE id='".intval($duelo["id"])."' LIMIT 1", "duelos");
        doquery("COMMIT", "duelos");
        limparduelo($id);
        return true;
    }
    $lado = ($duelo["desafiante"] == $id) ? 1 : 2;
    dueloencerrar($duelo, $jogadores, 3 - $lado, "desistencia");
    duelogravar($duelo);
    doquery("COMMIT", "duelos");
    return true;

}

function podeduelar($row, $nivelminimo, $proprio) { // "" se o jogador pode entrar num duelo agora, ou a chave (em português) do motivo.

    if ($row["level"] < $nivelminimo) { return $proprio ? "Você ainda não tem o level mínimo para duelar." : "Esse jogador ainda não tem o level mínimo para duelar."; }
    if ($row["currentaction"] == "Fighting") { return $proprio ? "Você não pode duelar no meio de uma batalha." : "Esse jogador está no meio de uma batalha. Tente novamente mais tarde."; }
    if ($row["batalha_timer2"] == 5) { return $proprio ? "Você já está em um duelo." : "Esse jogador já está em um duelo com outra pessoa."; }
    if ($row["trocajogador1"] != "None") { return $proprio ? "Termine ou cancele a sua troca antes de duelar." : "Esse jogador está fazendo uma troca. Tente novamente mais tarde."; }
    if ($row["currenthp"] <= 1) { return $proprio ? "Você precisa de mais de 1 HP para duelar. Descanse antes." : "Esse jogador está sem Pontos de Vida e não pode duelar agora."; }
    if ($row["authlevel"] == 2) { return "Esse jogador não pode duelar."; }
    if (!$proprio && !jogadoronline($row)) { return "Esse jogador não está online."; }
    return "";

}

/* ---------- Partes da tela ---------- */

function cartaodojogador($row, $davez, $icones = "", $semretrato = false) { // Cartão do jogador no duelo (mesmo formato do cartão de monstro): avatar, nome, level, vila, elemento e barras de HP/CH. $icones: efeitos de jutsu ativos (jutsuicones).

    // Mesmo cartão .cartam do monstro (cartas.css; funcoescartas.php :: cartaodomonstro), com as partes do jogador.
    $h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
    $avatar = intval(isset($row["avatar"]) ? $row["avatar"] : 1);
    $imagem = "layoutnovo/avatares/".$avatar.".jpg";
    if (!file_exists($imagem)) { $imagem = "layoutnovo/avatares/1.jpg"; }
    $nomes = array("neutro"=>"Neutro", "fogo"=>"Fogo", "agua"=>"Água", "vento"=>"Vento", "raio"=>"Raio", "terra"=>"Terra");
    $elementos = explode(",", (string)(isset($row["elemento"]) ? $row["elemento"] : ""));
    $elemento = isset($nomes[$elementos[0]]) ? $elementos[0] : "neutro";
    $hp = max(0, intval($row["currenthp"])); $maxhp = max(1, intval($row["maxhp"]));
    $ch = max(0, intval($row["currentmp"])); $maxch = max(1, intval($row["maxmp"]));
    $php = min(100, round($hp / $maxhp * 100));
    $pch = min(100, round($ch / $maxch * 100));
    // Ataque/Defesa de combate (com especialização, Senjutsu, doujutsu e talentos), como no duelo.
    $atk = isset($row["attackpower"]) ? intval($row["attackpower"]) : 0; $def = isset($row["defensepower"]) ? intval($row["defensepower"]) : 0;
    if (isset($row["id"], $row["charclass"]) && function_exists('classeataquedefesa')) { list($atk, $def) = classeataquedefesa($row); }
    $nome = (string)$row["charname"];
    $vila = isset($row["alinhamento"]) ? selovila($row["alinhamento"]) : ""; // lib.php: emblema original da vila.
    // Selos: especialização, título (GM / campeão de temporada) e efeitos ativos do duelo.
    $selos = "";
    $classe = isset($row["charclass"]) ? nomedaclasse($row["charclass"]) : "";
    if ($classe != "") { $selos .= "<span class=\"cselo2 sesp\" title=\"".$h(t("Especialização")).": ".$h($classe)."\">".$h($classe)."</span>"; }
    $titulo = "";
    if (ehgm($row) || (isset($row["authlevel"]) && intval($row["authlevel"]) == 1)) { $titulo = "GM"; }
    elseif (isset($row["id"]) && intval($row["id"]) > 0) {
        $qt = doquery("SELECT temporada FROM {{table}} WHERE usuario='".intval($row["id"])."' AND posicao='1' ORDER BY temporada DESC LIMIT 1", "titulos");
        if ($qt && ($rt = mysqli_fetch_array($qt))) { $titulo = tf("Campeão T%s", intval($rt["temporada"])); }
    }
    if ($titulo != "") { $selos .= "<span class=\"cselo2 stitulo\" title=\"".$h($titulo == "GM" ? t("GameMaster") : $titulo)."\">&#9733; ".$h($titulo)."</span>"; }
    if ($icones != "") { $selos .= "<span class=\"cselo2 sefeito cefeitos\" title=\"".$h(t("Efeitos ativos"))."\">".$icones."</span>"; }
    $cls = "cartam cartaj comselos el-".$elemento.($davez ? " cvez" : "").($titulo != "" ? " ctitulo" : "").($semretrato ? " cartasemret" : "");
    static $cssposto = false;
    $css = ""; // cartas.css já vem no <head> (lib.php scriptcelular), senão a carta "pisca" grande a cada ataque
    $cssposto = true;
    $tam = mb_strlen($nome) > 14 ? " cmin" : (mb_strlen($nome) > 11 ? " cpeq" : "");
    return $css."<div class=\"".$cls."\" data-jogador=\"".intval(isset($row["id"]) ? $row["id"] : 0)."\"><div class=\"cin\">"
        ."<div class=\"cret\"><div class=\"cretf\" style=\"background-image:url('".$imagem."')\"></div><img class=\"cretimg\" src=\"".$imagem."\" alt=\"\">"
        ."<div class=\"cnv\">".$h(t("NV"))." ".intval($row["level"])."</div></div>"
        ."<div class=\"cdad\"><div class=\"ccab\"><span class=\"cnome".$tam."\" title=\"".$h($nome)."\">".$h($nome)."</span>"
        ."<span class=\"cel csotxt\">".($vila != "" ? "<span class=\"cvila\">".$vila."</span>" : "")."<img src=\"images/".$elemento.".gif\" alt=\"\" title=\"".$h(tf("Elemento %s", t($nomes[$elemento])))."\"></span></div>"
        ."<div class=\"chp\"><i class=\"barrahp\" style=\"width:".$php."%\"></i><span>HP <b class=\"hpatual\">".$hp."</b> / ".$maxhp."</span></div>"
        ."<div class=\"cch\"><i style=\"width:".$pch."%\"></i><span>CH <b class=\"chatual\">".$ch."</b> / ".$maxch."</span></div>"
        ."<div class=\"cg\"><div>&#9876; ".$h(t("Ataque"))."<b>".$atk."</b></div><div>&#128737; ".$h(t("Defesa"))."<b>".$def."</b></div></div>"
        .($selos != "" ? "<div class=\"cselos\">".$selos."</div>" : "")
        ."</div></div></div>";

}

function dueloarena($eu, $outro, $ativo, $vencedor, $meuid) { // Cena do duelo: os gifs de batalha dos dois, um virado para o outro, no fundo da tela de luta.
    // Os gifs olham para a direita: o da esquerda (eu) fica como está e o da direita (adversário) é espelhado.
    $lado = function ($row, $espelhar) use ($ativo, $vencedor) {
        $av = intval(isset($row["avatar"]) ? $row["avatar"] : 1);
        $estado = $ativo ? "_stance" : ((intval(isset($row["id"]) ? $row["id"] : 0) == $vencedor) ? "_ganhou" : "_morto");
        $gif = "layoutnovo/graficos/".$av.$estado.".gif";
        if (!file_exists($gif)) { $gif = "layoutnovo/graficos/1".$estado.".gif"; }
        $hp = max(0, intval($row["currenthp"])); $maxhp = max(1, intval($row["maxhp"]));
        return "<div class=\"duelocena\"><div class=\"duelonome\">".htmlspecialchars((string)$row["charname"], ENT_QUOTES, "UTF-8")."</div>"
            ."<div class=\"duelovida\"><i style=\"width:".min(100, round($hp * 100 / $maxhp))."%\"></i></div>"
            ."<img src=\"".$gif."\" alt=\"\"".($espelhar ? " style=\"transform:scaleX(-1)\"" : "")."></div>";
    };
    return "<div class=\"duelorena\">".$lado($eu, false)."<span class=\"duelovs\">VS</span>".$lado($outro, true)."</div>";
}

function dueloestilo() { // CSS das telas de duelo (cartões, painéis e registro), nas cores do jogo.

    return "<style type=\"text/css\">
.duelopainel{border:1px solid #452202;background:#fff1c7;margin-bottom:10px}
.duelorena{display:flex;justify-content:center;align-items:center;gap:6px;margin:4px 0 8px}
.duelocena{position:relative;width:165px;height:175px;background:url(layoutnovo/graficos/fundo.png) no-repeat left top;display:flex;align-items:flex-end;justify-content:center;padding-bottom:22px;box-sizing:border-box}
.duelocena img{display:block}
.duelonome{position:absolute;top:6px;left:0;right:0;text-align:center;font:bold 11px tahoma,arial,sans-serif;color:#452202;text-shadow:0 0 3px #fff8e1}
.duelovida{position:absolute;top:22px;left:22px;right:22px;height:7px;background:#3a1d05;border:1px solid #452202;border-radius:3px;overflow:hidden}
.duelovida i{display:block;height:100%;background:#c0281c}
.cartasemret .cret{display:none}
.duelopainel .titulo{background:#e0d4af;border-bottom:1px solid #452202;padding:3px 6px;font-weight:bold;color:#442800}
.duelopainel .corpo{padding:6px}
.duelovs{font-family:impact,'DejaVu Sans Condensed',sans-serif;font-size:28px;color:#442800;text-shadow:0 0 5px #ffffcc}
.duelofaixa{border:1px solid #452202;background:#e0d4af;padding:5px;text-align:center;color:#442800;margin:10px 0}
.dueloregistro{margin:0;padding:0 0 0 18px}
.dueloregistro li{padding:2px 0;border-bottom:1px dotted #c8b88a}
.dueloregistro li.ultima{font-weight:bold}
.duelolista td{padding:3px 4px;vertical-align:middle}
.duelolista img{vertical-align:middle}
.duelolista select{width:160px}
.duelodetalhe{font-size:11px}
</style>";

}

function duelopainel($titulo, $conteudo) { // Quadro com título no estilo das tabelas do cartão do monstro.

    return "<div class=\"duelopainel\"><div class=\"titulo\">".$titulo."</div><div class=\"corpo\">".$conteudo."</div></div>";

}

function duelotextoregistro($duelo) { // Registro do duelo (mais recente em cima), traduzido para quem está lendo.

    if ($duelo["registro"] == "") { return "<i>".t("Nenhuma ação ainda.")."</i>"; }
    $linhas = array_reverse(explode("\n", $duelo["registro"]));
    $html = "<ol class=\"dueloregistro\" reversed>";
    foreach ($linhas as $i => $linha) { $html .= "<li".($i == 0 ? " class=\"ultima\"" : "").">".textoi18n($linha)."</li>"; }
    return $html."</ol>";

}

function duelocontagem($segundos, $destino) { // Contagem regressiva do turno (o servidor manda os segundos que faltam; recarrega no fim).

    $segundos = max(0, intval($segundos));
    return "<b id=\"duelotempo\">".$segundos."</b>s<script type=\"text/javascript\">(function(){var fim=Date.now()+".$segundos."*1000;if(window.duelorelogio){clearInterval(window.duelorelogio);}"
        ."window.duelorelogio=setInterval(function(){var e=document.getElementById('duelotempo');if(!e){clearInterval(window.duelorelogio);return;}e.innerHTML=Math.max(0,Math.round((fim-Date.now())/1000));},1000);})();</script>"
        ."<meta http-equiv=\"refresh\" content=\"".$destino."\" />";

}

/* ---------- Saguão: desafiar, aceitar, recusar e cancelar ---------- */

function batalha1() { // Saguão do duelo: jogadores que podem ser desafiados aqui, desafios recebidos/enviados e o formulário de desafio (POST acao=...).

    global $userrow, $controlrow, $link;
    $t = "t"; $tf = "tf";
    exigirjogador(false);
    $meuid = intval($userrow["id"]);
    $titulo = t("Realizar Duelo");
    $nivelminimo = intval($controlrow["pvplevel"]);

    // Duelo em andamento: vai direto para a tela dele.
    dueloatualizar($meuid);
    if (duelocarregar($meuid, "ativo", false)) { header("Location: users.php?do=duelo"); die(); }
    if ($userrow["batalha_timer2"] == 5) { limparduelo($meuid); } // Marca antiga (sistema anterior) sem duelo de verdade.

    if (isset($_POST["acao"])) {

        if (!csrf_confere()) { voltarcommensagem("users.php?do=batalha1", "A sessão do formulário expirou. Tente novamente."); }
        $acao = pegartexto($_POST, "acao");

        if ($acao == "desafiar") { // ---------- Enviar desafio ----------

            $jogador = pegartexto($_POST, "jogador");
            $aposta = pegarint($_POST, "aposta", -1);
            if (isset($_POST["aposta"]) && trim((string)$_POST["aposta"]) === "") { $aposta = 0; }
            if ($aposta < 0 || $aposta > 16777215) { voltarcommensagem("users.php?do=batalha1", "A aposta deve ser um número de 0 para cima."); }

            doquery("START TRANSACTION", "users");
            $eu = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$meuid' LIMIT 1 FOR UPDATE", "users"));
            $alvoquery = doquery("SELECT *, (UNIX_TIMESTAMP(onlinetime) >= UNIX_TIMESTAMP() - 600) AS online FROM {{table}} WHERE charname='$jogador' AND convidado='0' LIMIT 1", "users"); // convidado (sem conta) não duela
            if (mysqli_num_rows($alvoquery) != 1) { doquery("ROLLBACK", "users"); voltarcommensagem("users.php?do=batalha1", "Não existe nenhuma conta com esse nome."); }
            $alvo = mysqli_fetch_array($alvoquery);
            if ($alvo["id"] == $meuid) { doquery("ROLLBACK", "users"); voltarcommensagem("users.php?do=batalha1", "Você não pode batalhar com você mesmo."); }
            $erro = podeduelar($eu, $nivelminimo, true);
            if ($erro == "") { $erro = podeduelar($alvo, $nivelminimo, false); }
            if ($erro == "" && ($eu["latitude"] != $alvo["latitude"] || $eu["longitude"] != $alvo["longitude"])) { $erro = "Você só pode desafiar um jogador que está no mesmo lugar do mapa (ou na mesma cidade) que você."; }
            if ($erro == "" && $aposta > intval($eu["gold"])) { $erro = "Você não tem Ryou suficiente para essa aposta."; }
            if ($erro == "" && $aposta > intval($alvo["gold"])) { $erro = "Esse jogador não tem Ryou suficiente para essa aposta."; }
            if ($erro == "") {
                $pendente = mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE desafiante='".intval($alvo["id"])."' AND desafiado='$meuid' AND estado='pendente' LIMIT 1", "duelos"));
                if ($pendente) { $erro = "Esse jogador já desafiou você. Aceite ou recuse o desafio dele abaixo."; }
            }
            if ($erro != "") { doquery("ROLLBACK", "users"); voltarcommensagem("users.php?do=batalha1", $erro); }

            // Só um desafio enviado por vez: o anterior é cancelado.
            doquery("UPDATE {{table}} SET estado='cancelado', atualizado='".time()."' WHERE desafiante='$meuid' AND estado='pendente'", "duelos");
            doquery("INSERT INTO {{table}} SET desafiante='$meuid', desafiado='".intval($alvo["id"])."', estado='pendente', aposta='$aposta', criado='".time()."', atualizado='".time()."', registro=''", "duelos");
            if ($aposta > 0) {
                avisarnohistorico($alvo["id"], i18n("%s desafiou você para um duelo valendo %s Ryou. <a href=\"users.php?do=batalha1\" target=\"_top\">Responder</a>.", $eu["charname"], $aposta));
            } else {
                avisarnohistorico($alvo["id"], i18n("%s desafiou você para um duelo. <a href=\"users.php?do=batalha1\" target=\"_top\">Responder</a>.", $eu["charname"]));
            }
            doquery("COMMIT", "users");
            header("Location: users.php?do=batalha1&conteudo=".rawurlencode(tf("Desafio enviado para %s. Aguarde a resposta.", $alvo["charname"])));
            die();

        } elseif ($acao == "recusar" || $acao == "cancelar") { // ---------- Recusar (desafiado) ou cancelar (desafiante) ----------

            $id = pegarint($_POST, "duelo");
            $coluna = ($acao == "recusar") ? "desafiado" : "desafiante";
            $novoestado = ($acao == "recusar") ? "recusado" : "cancelado";
            $duelorow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' AND $coluna='$meuid' AND estado='pendente' LIMIT 1", "duelos"));
            // UPDATE condicional: só um dos pedidos muda o estado (e só ele avisa o outro jogador).
            doquery("UPDATE {{table}} SET estado='$novoestado', atualizado='".time()."' WHERE id='$id' AND $coluna='$meuid' AND estado='pendente' LIMIT 1", "duelos");
            if ($duelorow && mysqli_affected_rows($link) == 1 && $acao == "recusar") {
                avisarnohistorico($duelorow["desafiante"], i18n("%s recusou o seu desafio de duelo.", $userrow["charname"]));
            }
            voltarcommensagem("users.php?do=batalha1", ($acao == "recusar") ? "Desafio recusado." : "Desafio cancelado.");

        } elseif ($acao == "aceitar") { // ---------- Aceitar: confere tudo de novo, guarda as apostas e começa ----------

            $id = pegarint($_POST, "duelo");
            doquery("START TRANSACTION", "duelos");
            $duelo = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' AND desafiado='$meuid' AND estado='pendente' LIMIT 1 FOR UPDATE", "duelos"));
            if (!$duelo) { doquery("ROLLBACK", "duelos"); voltarcommensagem("users.php?do=batalha1", "Esse desafio não existe mais."); }
            $jogadores = duelotravarjogadores($duelo);
            $erro = "";
            if (!$jogadores || intval($duelo["criado"]) < time() - DUELO_TEMPODESAFIO) { $erro = "Esse desafio não existe mais."; }
            else {
                $desafiante = $jogadores[1]; $eu = $jogadores[2];
                $erro = podeduelar($eu, $nivelminimo, true);
                if ($erro == "") { $erro = podeduelar($desafiante, $nivelminimo, false); }
                if ($erro == "" && ($eu["latitude"] != $desafiante["latitude"] || $eu["longitude"] != $desafiante["longitude"])) { $erro = "Vocês precisam estar no mesmo lugar do mapa (ou na mesma cidade) para duelar."; }
                $aposta = intval($duelo["aposta"]);
                if ($erro == "" && $aposta > intval($eu["gold"])) { $erro = "Você não tem Ryou suficiente para essa aposta."; }
                if ($erro == "" && $aposta > intval($desafiante["gold"])) { $erro = "Esse jogador não tem Ryou suficiente para essa aposta."; }
            }
            if ($erro != "") {
                // O desafio não pode mais começar: fica cancelado para não ser aceito depois por engano.
                doquery("UPDATE {{table}} SET estado='cancelado', atualizado='".time()."' WHERE id='$id' LIMIT 1", "duelos");
                doquery("COMMIT", "duelos");
                voltarcommensagem("users.php?do=batalha1", $erro);
            }

            // Apostas: saem das duas contas agora (ficam guardadas no duelo). A condição gold >= aposta na própria query é a garantia final.
            if ($aposta > 0) {
                doquery("UPDATE {{table}} SET gold=gold-$aposta WHERE id IN ('".intval($desafiante["id"])."','".intval($eu["id"])."') AND gold >= $aposta LIMIT 2", "users");
                if (mysqli_affected_rows($link) != 2) { doquery("ROLLBACK", "duelos"); voltarcommensagem("users.php?do=batalha1", "Esse jogador não tem Ryou suficiente para essa aposta."); }
            }

            // Quem começa: rand(1,10) + raiz(Destreza), como o primeiro ataque da luta contra monstros.
            $iniciativa1 = rand(1,10) + ceil(sqrt(intval($desafiante["dexterity"])));
            $iniciativa2 = rand(1,10) + ceil(sqrt(intval($eu["dexterity"])));
            $duelo["estado"] = "ativo";
            $duelo["vez"] = ($iniciativa1 > $iniciativa2) ? intval($desafiante["id"]) : intval($eu["id"]);
            $duelo["turno"] = 1;
            $duelo["prazo"] = time() + DUELO_TEMPOTURNO;
            $duelo["registro"] = "";
            $duelo["jutsuestado"] = "";
            dueloregistrar($duelo, ($aposta > 0) ? i18n("O duelo entre %s e %s começou, valendo %s Ryou.", $desafiante["charname"], $eu["charname"], 2 * $aposta) : i18n("O duelo entre %s e %s começou.", $desafiante["charname"], $eu["charname"]));
            dueloregistrar($duelo, i18n("%s foi mais rápido e começa.", ($duelo["vez"] == $desafiante["id"]) ? $desafiante["charname"] : $eu["charname"]));
            duelogravar($duelo);
            doquery("UPDATE {{table}} SET hpantes1='".intval($desafiante["currenthp"])."', hpantes2='".intval($eu["currenthp"])."' WHERE id='".intval($duelo["id"])."' LIMIT 1", "duelos");
            // Os dois entram no duelo; os outros desafios pendentes deles são cancelados.
            doquery("UPDATE {{table}} SET batalha_timer2='5', pocoesluta='', batalha_nome='".addslashes($eu["charname"])."', currentuberdamage='0', currentuberdefense='0' WHERE id='".intval($desafiante["id"])."' LIMIT 1", "users");
            doquery("UPDATE {{table}} SET batalha_timer2='5', pocoesluta='', batalha_nome='".addslashes($desafiante["charname"])."', currentuberdamage='0', currentuberdefense='0' WHERE id='".intval($eu["id"])."' LIMIT 1", "users");
            $ids = "'".intval($desafiante["id"])."','".intval($eu["id"])."'";
            doquery("UPDATE {{table}} SET estado='cancelado', atualizado='".time()."' WHERE desafiante IN ($ids) AND estado='pendente'", "duelos");
            doquery("UPDATE {{table}} SET estado='cancelado', atualizado='".time()."' WHERE desafiado IN ($ids) AND estado='pendente'", "duelos");
            avisarnohistorico($desafiante["id"], i18n("%s aceitou o seu desafio! <a href=\"users.php?do=duelo\" target=\"_top\">Ir para o duelo</a>.", $eu["charname"]));
            doquery("COMMIT", "duelos");
            header("Location: users.php?do=duelo");
            die();

        }
        header("Location: users.php?do=batalha1"); die();

    }

    // ---------- Tela do saguão ----------
    // Desafios pendentes antigos do jogador expiram. Só quando existe algum (leitura sem trava antes) e só as linhas dele:
    // um UPDATE que trava linhas a cada visita disputava as travas com o aceite de um desafio feito ao mesmo tempo.
    $limite = time() - DUELO_TEMPODESAFIO;
    if (mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE (desafiante='$meuid' OR desafiado='$meuid') AND estado='pendente' AND criado < '$limite' LIMIT 1", "duelos")) > 0) {
        doquery("UPDATE {{table}} SET estado='expirado', atualizado='".time()."' WHERE desafiante='$meuid' AND estado='pendente' AND criado < '$limite'", "duelos");
        doquery("UPDATE {{table}} SET estado='expirado', atualizado='".time()."' WHERE desafiado='$meuid' AND estado='pendente' AND criado < '$limite'", "duelos");
    }
    $csrf = csrf_campo();
    $recebidos = ""; $enviado = ""; $esperando = false;
    $compendente = array(); // ids dos jogadores com quem já existe um desafio pendente (não mostra o botão Desafiar para eles).
    $query = doquery("SELECT d.*, u.charname, u.level, u.alinhamento FROM {{table}} AS d JOIN ".$GLOBALS["dbsettings"]["prefix"]."_users AS u ON u.id = IF(d.desafiante='$meuid', d.desafiado, d.desafiante) WHERE (d.desafiante='$meuid' OR d.desafiado='$meuid') AND d.estado='pendente' ORDER BY d.id DESC", "duelos");
    while ($row = mysqli_fetch_array($query)) {
        $compendente[] = ($row["desafiante"] == $meuid) ? intval($row["desafiado"]) : intval($row["desafiante"]);
        $restam = max(0, intval($row["criado"]) + DUELO_TEMPODESAFIO - time());
        $restam = floor($restam / 60).":".str_pad($restam % 60, 2, "0", STR_PAD_LEFT);
        $apostatexto = (intval($row["aposta"]) > 0) ? tf("Aposta: %s Ryou", number_format((float)$row["aposta"])) : t("Sem aposta");
        $emblema = selovila($row["alinhamento"])." ";
        if ($row["desafiado"] == $meuid) {
            $recebidos .= "<tr><td>$emblema<b>".$row["charname"]."</b> (Level ".intval($row["level"]).")</td><td>$apostatexto</td><td>".tf("Expira em %s", $restam)."</td><td align=\"right\" nowrap=\"nowrap\">"
                ."<form action=\"users.php?do=batalha1\" method=\"post\" style=\"display:inline\">$csrf<input type=\"hidden\" name=\"duelo\" value=\"".intval($row["id"])."\" /><button type=\"submit\" name=\"acao\" value=\"aceitar\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_sim.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Aceitar")."</button> <button type=\"submit\" name=\"acao\" value=\"recusar\" class=\"btnjogo bj-cancelar\"><img src=\"images/botao_nao.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Recusar")."</button></form></td></tr>";
        } else {
            $esperando = true;
            $enviado .= "<tr><td>$emblema<b>".$row["charname"]."</b> (Level ".intval($row["level"]).")</td><td>$apostatexto</td><td>".tf("Expira em %s", $restam)."</td><td align=\"right\">"
                ."<form action=\"users.php?do=batalha1\" method=\"post\" style=\"display:inline\">$csrf<input type=\"hidden\" name=\"duelo\" value=\"".intval($row["id"])."\" /><button type=\"submit\" name=\"acao\" value=\"cancelar\" class=\"btnjogo bj-cancelar\"><img src=\"images/botao_nao.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Cancelar")."</button></form></td></tr>";
        }
    }

    // Jogadores online aqui (mesma casa do mapa / mesma cidade).
    $aqui = "";
    $query = doquery("SELECT id,charname,level,alinhamento,currentaction,batalha_timer2,currenthp,gold FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' AND id<>'$meuid' AND authlevel<>'2' AND UNIX_TIMESTAMP(onlinetime) >= UNIX_TIMESTAMP() - 600 ORDER BY level DESC, charname LIMIT 30", "users");
    while ($row = mysqli_fetch_array($query)) {
        $emblema = selovila($row["alinhamento"])." ";
        if ($row["level"] < $nivelminimo) { $situacao = "<span class=\"light\">".t("Level baixo")."</span>"; }
        elseif ($row["batalha_timer2"] == 5) { $situacao = "<span class=\"light\">".t("Em duelo")."</span>"; }
        elseif ($row["currentaction"] == "Fighting") { $situacao = "<span class=\"light\">".t("Em batalha")."</span>"; }
        elseif (in_array(intval($row["id"]), $compendente)) { $situacao = "<span class=\"light\">".t("Desafio pendente")."</span>"; }
        else {
            $situacao = "<form action=\"users.php?do=batalha1\" method=\"post\" style=\"display:inline\">$csrf<input type=\"hidden\" name=\"jogador\" value=\"".$row["charname"]."\" />"
                .t("Aposta:")." <input type=\"text\" name=\"aposta\" value=\"0\" size=\"6\" maxlength=\"8\" /> Ryou <button type=\"submit\" name=\"acao\" value=\"desafiar\" class=\"btnjogo bj-ataque\"><img src=\"images/botao_desafiar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Desafiar")."</button></form>";
        }
        $aqui .= "<tr><td>$emblema<a href=\"javascript: mostrarchar('".$row["charname"]."');\">".$row["charname"]."</a></td><td>Level ".intval($row["level"])."</td><td align=\"right\" nowrap=\"nowrap\">$situacao</td></tr>";
    }

    // Últimos duelos do jogador.
    $historicoduelos = "";
    $query = doquery("SELECT d.*, u.charname FROM {{table}} AS d JOIN ".$GLOBALS["dbsettings"]["prefix"]."_users AS u ON u.id = IF(d.desafiante='$meuid', d.desafiado, d.desafiante) WHERE (d.desafiante='$meuid' OR d.desafiado='$meuid') AND d.estado='fim' ORDER BY d.id DESC LIMIT 5", "duelos");
    while ($row = mysqli_fetch_array($query)) {
        $venceu = ($row["vencedor"] == $meuid);
        $resultado = $venceu ? "<font color=\"darkgreen\"><b>".t("Vitória")."</b></font>" : "<font color=\"darkred\"><b>".t("Derrota")."</b></font>";
        $aposta = (intval($row["aposta"]) > 0) ? ($venceu ? "+" : "-").number_format((float)$row["aposta"])." Ryou" : "";
        $historicoduelos .= "<tr><td>$resultado</td><td>".tf("contra %s", $row["charname"])."</td><td>$aposta</td><td align=\"right\"><a href=\"users.php?do=duelo&ver=".intval($row["id"])."\">".t("Ver")."</a></td></tr>";
    }

    $pagearray = array(
        "titulo" => titulopagina("duelo.gif"),
        "estilo" => dueloestilo(),
        "mensagem" => mensagemtopo(),
        "placar" => tf("Seu placar: <b>%s</b> vitórias e <b>%s</b> derrotas.", intval($userrow["duelos_vencidos"]), intval($userrow["duelos_perdidos"])),
        "recebidos" => ($recebidos == "") ? "" : duelopainel(t("Desafios recebidos"), "<table width=\"100%\" class=\"duelolista\">".$recebidos."</table>"),
        "enviado" => ($enviado == "") ? "" : duelopainel(t("Desafio enviado"), "<table width=\"100%\" class=\"duelolista\">".$enviado."</table><span class=\"light\">".t("Esta página é atualizada sozinha; quando o desafio for aceito você vai para o duelo.")."</span>"),
        "aqui" => duelopainel(t("Ninjas online neste lugar"), ($aqui == "") ? "<i>".t("Nenhum outro ninja online neste lugar do mapa agora.")."</i>" : "<table width=\"100%\" class=\"duelolista\">".$aqui."</table>"),
        "ultimos" => ($historicoduelos == "") ? "" : duelopainel(t("Seus últimos duelos"), "<table width=\"100%\" class=\"duelolista\">".$historicoduelos."</table>"),
        "csrf" => $csrf,
        "regras" => tf("Turnos de %s segundos; quem esgotar o tempo %s vezes perde. Ninguém morre: quem chegar a 1 HP perde o duelo. O vencedor leva as duas apostas.", DUELO_TEMPOTURNO, DUELO_MAXATRASOS),
        "atualizar" => ($esperando || $recebidos != "") ? "<meta http-equiv=\"refresh\" content=\"8;URL=users.php?do=batalha1\" />" : "",
    );
    $page = parsetemplate(gettemplate("batalha1"), $pagearray);
    display($page, $titulo, false, false, false);

}

function resetarduelo() { // Link "resetar duelo" das outras páginas: com um duelo de verdade em andamento leva para a tela dele (onde dá para desistir); senão tira a marca presa.

    global $userrow;
    if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); }
    if ($userrow["currentaction"] == "Fighting") { voltarcommensagem("index.php?do=fight", "Você não pode acessar essa função no meio de uma batalha!"); }

    dueloatualizar($userrow["id"]);
    if (duelocarregar($userrow["id"], "ativo", false)) { voltarcommensagem("users.php?do=duelo", "Você está em um duelo. Para sair dele, use o botão Desistir."); }
    limparduelo($userrow["id"]);
    display(titulopagina("duelo.gif").t("Seu duelo atual foi resetado com sucesso."), t("Realizar Duelo"), false, false, false);

}

/* ---------- Tela do duelo e ações ---------- */

function duelodano($atacante, $alvo, $base, $multelemento, $pontos, $bonusdano, $defesaalvo, $guarda) { // Aplica ao dano os bônus/defesas do duelo. Devolve o dano final (mínimo 1).

    $dano = $base;
    if ($bonusdano != 0) { $dano += ceil($dano * ($bonusdano / 100)); }
    $dano = ceil($dano * $multelemento);
    $dano += ceil($dano * $pontos / 100);
    if ($defesaalvo != 0) { $dano -= ceil($dano * ($defesaalvo / 100)); }
    if ($guarda) { $dano = ceil($dano / 2); }
    return max(1, intval($dano));

}

function dueloacao(&$duelo, $jogadores, $meulado, $acao) { // Executa a ação do jogador da vez. Altera $jogadores (HP/CH/mochila) e o registro. Devolve "" ou a chave do erro.

    global $userrow;
    $outrolado = 3 - $meulado;
    $eu = $jogadores[$meulado];
    $alvo = $jogadores[$outrolado];
    // As funções de bônus (bonuscombate, custochakra, elementoganhador) leem o jogador global: aqui ele é o atacante, com os dados travados.
    $userrow = $eu;
    $meunome = $eu["charname"]; $alvonome = $alvo["charname"];
    $alvoelementos = explode(",", (string)$alvo["elemento"]);
    $dano = 0;
    // Efeitos de jutsu e recargas (lados 1 e 2; funcoesjutsus.php). Só é gravado de volta no $duelo se a ação valer.
    $estado = jutsuestadoler(isset($duelo["jutsuestado"]) ? $duelo["jutsuestado"] : "");

    if ($acao == "atacar") {

        // Especialização (dano físico, crítico, defesa física) e Senjutsu ativo (Poder de Ataque/Defesa), calculados na hora para os dois lados.
        $modseu = classedojogador($eu); $modsalvo = classedojogador($alvo);
        $ap = classeataquedefesa($eu)[0];
        $tohit = ceil(rand(intval($ap * .75), $ap) / 3);
        $excelente = (rand(1,150) <= limitecritico($eu["strength"], $eu["determinacao"])) || (rand(1,100) <= bonuscombate("critico") + jutsubonus($estado, $meulado, "buff_critico") + $modseu["critico"]); // + Foco crítico e crítico da especialização.
        if ($excelente) { $tohit *= 2; }
        $dp = classeataquedefesa($alvo)[1];
        $toblock = ceil(rand(intval($dp * .75), $dp) / 4);
        $toblock = (int)ceil($toblock * (100 - min(90, $modseu["dj_ignora_defesa"])) / 100); // doujutsu (funcoesdoujutsu.php): ignora parte da defesa.
        // Precisão de quem ataca (agora 2/100, igual à Agilidade) contra Destreza + Agilidade do alvo (limiteesquiva(): teto de 40%).
        $tododge = rand(1,150) + floor((intval($eu["precisao"]) + $modseu["dj_precisao"]) * 2 / 100); // + doujutsu ativo.
        $esquivaalvo = max(0, min(40, intval($alvo["esquiva"]))) + jutsubonus($estado, $outrolado, "buff_esquiva") + $modsalvo["dj_esquiva"]; // + Esquiva aumentada e doujutsu.
        if ($tododge <= limiteesquiva($alvo["dexterity"], intval($alvo["agilidade"]) + $modsalvo["dj_agilidade"]) || rand(1,100) <= $esquivaalvo) {
            dueloregistrar($duelo, i18n("%s atacou, mas %s desviou do golpe.", $meunome, $alvonome));
        } else {
            // Elemento do ataque físico só com o jutsu de +Dano ativo e EP (mesma regra do PvE).
            $mult = 1; $pontos = 0;
            if ($duelo["dano".$meulado] != 0 && intval($eu["currentep"]) > 0) { $mult = elementoganhador($eu["elementoatk"], $alvoelementos[0], $pontos); } else { $pontos = 0; }
            if ($mult > 1 && intval($alvo["charclass"]) == 2) { $mult = 1; } // especialista em Taijutsu: sem fraqueza de elemento.
            $dano = duelodano($eu, $alvo, max(1, $tohit - $toblock), $mult, $pontos, intval($duelo["dano".$meulado]), intval($duelo["defesa".$outrolado]), $duelo["guarda".$outrolado]);
            // Ataque reforçado / defesa quebrada, depois ataque enfraquecido / defesa reforçada / escudo dos jutsus.
            $dano = max(1, (int)ceil($dano * max(0, 100 + $modseu["dano_fisico"]) / 100 * max(0, 100 - $modsalvo["defesa_fisica"]) / 100)); // especializações.
            $dano = (int)ceil($dano * jutsumultdano($estado, $meulado, $outrolado));
            list($dano, $msgsescudo) = jutsureduzirdano($estado, $meulado, $outrolado, $dano);
            dueloregistrar($duelo, $excelente ? i18n("Golpe crítico! %s acertou um ponto vital de %s e o dano dobrou: %s de dano.", $meunome, $alvonome, $dano) : i18n("%s atacou %s e causou %s de dano.", $meunome, $alvonome, $dano));
            if ($mult != 1) { dueloregistrar($duelo, elementofrase((string)$eu["elementoatk"], (string)$alvoelementos[0], $mult)); }
            foreach ($msgsescudo as $m) { dueloregistrar($duelo, $m); }
        }

    } elseif ($acao == "jutsu") {

        $pickedspell = pegarint($_POST, "userspell");
        $userspells = explode(",", $eu["spells"]);
        if ($pickedspell < 1 || !in_array($pickedspell, $userspells)) { return "Você deve selecionar um Jutsu que já aprendeu."; }
        $spellrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$pickedspell' LIMIT 1", "spells"));
        if (!$spellrow || $spellrow["type"] == 3 || (string)$spellrow["uso"] == "menu") { return "Esse Jutsu não pode ser usado em um duelo."; }
        if (jutsurecarga($estado, $meulado, $pickedspell) > 0) { return "Esse Jutsu ainda está em recarga."; }
        $custo = custochakra($spellrow["mp"], $spellrow);
        if (intval($eu["currentmp"]) < $custo) { return "Você não tem Chakra suficiente para usar esse Jutsu."; }
        // Jutsus de Senjutsu (modo ativo) e custo de NP; jutsu de dano com elemento gasta EP (funcoesjutsus.php).
        $erroespecial = jutsuerroespecial($spellrow, $eu);
        if ($erroespecial != "") { return $erroespecial; }
        if (!jutsucobrarnp($eu["id"], $spellrow)) { return "Você não tem NP suficiente para usar esse jutsu."; }
        $pagouep = jutsucobrarep($eu["id"], $spellrow);
        $jogadores[$meulado]["currentmp"] = intval($eu["currentmp"]) - $custo;
        $valor = intval($spellrow["attribute"]);
        $nomejutsu = $spellrow["name"]; // Gravado em português; o textoi18n() traduz para quem lê.

        $tipo = intval($spellrow["type"]);
        // Os dois lados no formato do aplicarjutsu() (funcoesjutsus.php); jogadores não são imunes.
        $c = array("lado"=>$meulado, "elementos"=>(string)$eu["elemento"], "elattr"=>elementoatributos($eu), "int"=>intval($eu["inteligencia"]), "ap"=>classeataquedefesa($eu)[0], "nome"=>$meunome, "hp"=>intval($eu["currenthp"]), "maxhp"=>intval($eu["maxhp"]), "mp"=>intval($jogadores[$meulado]["currentmp"]), "maxmp"=>intval($eu["maxmp"]), "elemento"=>"", "imune"=>0, "mods"=>classedojogador($eu));
        $a = array("lado"=>$outrolado, "nome"=>$alvonome, "hp"=>intval($alvo["currenthp"]), "maxhp"=>intval($alvo["maxhp"]), "mp"=>intval($alvo["currentmp"]), "maxmp"=>intval($alvo["maxmp"]), "elemento"=>(string)$alvo["elemento"], "imune"=>0, "mods"=>classedojogador($alvo), "classe"=>intval($alvo["charclass"]));
        if ($pagouep !== null) { $c["ep"] = $pagouep; }
        $antes = $estado;
        $msgs = array();
        if ($tipo == 1 || $tipo == 2 || $tipo == 6 || $tipo == 7) { // Cura, dano (golpes, elemento, roubo de vida) e suporte, com o efeito extra e a recarga.
            $res = aplicarjutsu($c, $a, $spellrow, $estado);
            $msgs = $res["msgs"];
            if (($tipo == 2 || $tipo == 7) && $res["dano"] > 0) {
                // Defesas do duelo sobre o total: +Defesa do alvo, guarda e depois os efeitos (enfraquecido, defesa reforçada, escudo).
                $d = intval($res["dano"]);
                if (intval($duelo["defesa".$outrolado]) != 0) { $d -= (int)ceil($d * intval($duelo["defesa".$outrolado]) / 100); }
                if ($duelo["guarda".$outrolado]) { $d = (int)ceil($d / 2); }
                list($dano, $msgsescudo) = jutsureduzirdano($estado, $meulado, $outrolado, max(1, $d));
                if ($dano != intval($res["dano"])) { $msgs[] = i18n("Depois das defesas, %s recebeu %s de dano.", $alvonome, $dano); }
                $msgs = array_merge($msgs, $msgsescudo);
            }
        } else { // Tipos antigos 4 e 5 (bônus até o fim do duelo) + efeito extra e recarga opcionais.
            $valor = (int)ceil($valor * (100 + classemodlado($c, "buff_proprio")) / 100); // Genjutsu reforça os próprios status.
            if ($tipo == 4) {
                $duelo["dano".$meulado] = min(255, $valor);
                $msgs[] = i18n("%s usou %s e ganhou %s%% de dano até o fim do duelo.", $meunome, $nomejutsu, $valor);
            } else {
                $duelo["defesa".$meulado] = min(255, $valor);
                $msgs[] = i18n("%s usou %s e ganhou %s%% de defesa até o fim do duelo.", $meunome, $nomejutsu, $valor);
            }
            jutsuaplicarefeito($c, $a, $spellrow, $estado, $msgs);
            if (intval($spellrow["cooldown"]) > 0) { $estado["cd"][$meulado][intval($spellrow["id"])] = intval($spellrow["cooldown"]) + 1; }
        }
        // Num turno par a rodada fecha logo em seguida: Atordoar/Ataque enfraquecido recém-aplicados ganham +1 turno para durar até o alvo jogar.
        if (intval($duelo["turno"]) % 2 == 0) {
            foreach (array("atordoar", "debuff_ataque") as $ef) {
                if (isset($estado["efeitos"][$outrolado][$ef]) && (!isset($antes["efeitos"][$outrolado][$ef]) || $antes["efeitos"][$outrolado][$ef] != $estado["efeitos"][$outrolado][$ef])) { $estado["efeitos"][$outrolado][$ef]["t"]++; }
            }
        }
        foreach ($msgs as $m) { dueloregistrar($duelo, $m); }
        $jogadores[$meulado]["currenthp"] = $c["hp"];
        $jogadores[$meulado]["currentmp"] = $c["mp"];
        $jogadores[$outrolado]["currentmp"] = max(0, intval($a["mp"])); // dreno de chakra (o HP do alvo sai do $dano abaixo).

    } elseif ($acao == "defender") {

        $duelo["guarda".$meulado] = 1;
        dueloregistrar($duelo, i18n("%s está se defendendo (recebe metade do dano até a próxima vez).", $meunome));

    } elseif ($acao == "pocao") {

        $qual = pegarint($_POST, "qual");
        if ($qual < 1 || $qual > 8) { return "Escolha uma poção da sua mochila."; }
        $item = array_pad(explode(",", (string)$eu["bp".$qual]), 4, "X");
        if ($item[1] != "hp" && $item[1] != "mp") { return "Escolha uma poção da sua mochila."; }
        // Limite por tipo de poção no duelo (control.limitepocoesluta; pocoesluta zera no início do duelo). Os jogadores estão travados.
        $tipopocao = pocaotipo($eu["bp".$qual]);
        if (pocaobloqueada($eu["pocoesluta"], $tipopocao)) { return "Você já usou essa poção o máximo de vezes permitido nesta luta."; }
        $jogadores[$meulado]["pocoesluta"] = pocaoanotar($eu["pocoesluta"], $tipopocao);
        if ($item[1] == "hp") {
            $ganho = min(intval($item[2]), max(0, intval($eu["maxhp"]) - intval($eu["currenthp"])));
            $jogadores[$meulado]["currenthp"] = intval($eu["currenthp"]) + $ganho;
            dueloregistrar($duelo, i18n("%s bebeu %s e recuperou %s HP.", $meunome, $item[0], $ganho));
        } else {
            $ganho = min(intval($item[2]), max(0, intval($eu["maxmp"]) - intval($eu["currentmp"])));
            $jogadores[$meulado]["currentmp"] = intval($eu["currentmp"]) + $ganho;
            dueloregistrar($duelo, i18n("%s bebeu %s e recuperou %s CH.", $meunome, $item[0], $ganho));
        }
        $jogadores[$meulado]["bp".$qual] = itemcomqtd($eu["bp".$qual], qtditem($eu["bp".$qual]) - 1); // pilha: bebe só 1.
        $jogadores[$meulado]["pocaousada"] = "bp".$qual;

    } elseif ($acao == "arremesso") { // Ferramenta ninja (kunai, bomba...): rola o acerto, dano e efeito; gasta o turno.

        $qual = pegarint($_POST, "qual");
        $ferramenta = ($qual >= 1 && $qual <= 8) ? itembatalhalinha($eu["bp".$qual]) : false;
        if (!$ferramenta) { return "Escolha uma ferramenta ninja da sua mochila."; }
        $a = array("lado"=>$outrolado, "nome"=>$alvonome, "hp"=>intval($alvo["currenthp"]), "maxhp"=>intval($alvo["maxhp"]), "mp"=>intval($alvo["currentmp"]), "maxmp"=>intval($alvo["maxmp"]),
            "elemento"=>elementosdo((string)$alvo["elemento"])[0], "imunidades"=>array(), "mods"=>classedojogador($alvo), "classe"=>intval($alvo["charclass"]));
        $msgs = array();
        $antes = $estado;
        $arr = itembatalhaarremessar($ferramenta, intval($eu["precisao"]), $a, $meunome, $msgs);
        if ($arr["dano"] > 0) {
            $d = (int)ceil($arr["dano"] * jutsumultdano($estado, $meulado, $outrolado));
            if (intval($duelo["defesa".$outrolado]) != 0) { $d -= (int)ceil($d * intval($duelo["defesa".$outrolado]) / 100); }
            if ($duelo["guarda".$outrolado]) { $d = (int)ceil($d / 2); }
            list($dano, $msgsescudo) = jutsureduzirdano($estado, $meulado, $outrolado, max(1, $d));
            if ($dano != intval($arr["dano"])) { $msgs[] = i18n("Depois das defesas, %s recebeu %s de dano.", $alvonome, $dano); }
            $msgs = array_merge($msgs, $msgsescudo);
        }
        if ($arr["efeito"] != "") { itembatalhaaplicarefeito($ferramenta, $arr["efeito"], $a, $estado, $msgs); }
        if (intval($duelo["turno"]) % 2 == 0 && isset($estado["efeitos"][$outrolado]["atordoar"]) && (!isset($antes["efeitos"][$outrolado]["atordoar"]) || $antes["efeitos"][$outrolado]["atordoar"] != $estado["efeitos"][$outrolado]["atordoar"])) {
            $estado["efeitos"][$outrolado]["atordoar"]["t"]++; // mesma regra dos jutsus: dura até o alvo jogar.
        }
        foreach ($msgs as $m) { dueloregistrar($duelo, $m); }
        $jogadores[$meulado]["bp".$qual] = itemcomqtd($eu["bp".$qual], qtditem($eu["bp".$qual]) - 1);
        $jogadores[$meulado]["pocaousada"] = "bp".$qual; // grava o espaço da mochila no mesmo UPDATE das poções.

    } else {
        return "Ação inválida.";
    }

    // Dano no alvo: ninguém morre no duelo; com 1 HP ou menos o alvo fica com 1 HP e perde.
    if ($dano > 0) { $jogadores[$outrolado]["currenthp"] = max(1, intval($alvo["currenthp"]) - $dano); }
    $jogadores[$outrolado]["derrotado"] = ($dano > 0 && intval($alvo["currenthp"]) - $dano <= 1);
    $duelo["jutsuestado"] = jutsuestadotexto($estado);
    return $jogadores;

}

function duelo() { // Tela do duelo (cartões, vez, contagem, comandos e registro) e as ações do turno (POST acao=...).

    global $userrow, $link;
    $t = "t"; $tf = "tf";
    exigirjogador(false);
    $meuid = intval($userrow["id"]);
    $titulo = t("Realizar Duelo");

    if (isset($_POST["acao"])) {

        if (!csrf_confere()) { voltarcommensagem("users.php?do=duelo", "A sessão do formulário expirou. Tente novamente."); }
        $acao = pegartexto($_POST, "acao");
        $turnoenviado = pegarint($_POST, "turno", -1);

        doquery("START TRANSACTION", "duelos");
        $duelo = duelocarregar($meuid, "ativo", true);
        if (!$duelo) { doquery("ROLLBACK", "duelos"); header("Location: users.php?do=duelo"); die(); }
        $jogadores = duelotravarjogadores($duelo);
        if (!$jogadores) { doquery("ROLLBACK", "duelos"); header("Location: users.php?do=duelo"); die(); }
        $meulado = ($duelo["desafiante"] == $meuid) ? 1 : 2;
        $mudou = dueloverificarprazo($duelo, $jogadores);

        if ($acao == "desistir" && $duelo["estado"] == "ativo") {
            dueloencerrar($duelo, $jogadores, 3 - $meulado, "desistencia");
            duelogravar($duelo);
            doquery("COMMIT", "duelos");
            header("Location: users.php?do=duelo&ver=".intval($duelo["id"])); die();
        }

        // Só joga quem está na vez, e só uma vez por turno (o formulário traz o número do turno que estava na tela).
        if ($duelo["estado"] != "ativo" || $duelo["vez"] != $meuid || $turnoenviado != intval($duelo["turno"])) {
            if ($mudou) { duelogravar($duelo); doquery("COMMIT", "duelos"); } else { doquery("ROLLBACK", "duelos"); }
            if ($duelo["estado"] != "ativo") { header("Location: users.php?do=duelo&ver=".intval($duelo["id"])); die(); }
            voltarcommensagem("users.php?do=duelo", "Não é a sua vez (ou essa ação já foi feita).");
        }

        $resultado = dueloacao($duelo, $jogadores, $meulado, $acao);
        if (!is_array($resultado)) {
            if ($mudou) { duelogravar($duelo); doquery("COMMIT", "duelos"); } else { doquery("ROLLBACK", "duelos"); }
            voltarcommensagem("users.php?do=duelo", $resultado);
        }
        $jogadores = $resultado;
        $outrolado = 3 - $meulado;

        // Grava os jogadores: HP/CH de quem agiu (e a poção usada, conferindo que ainda estava lá) e o HP do alvo.
        $eu = $jogadores[$meulado];
        $extra = isset($eu["pocaousada"]) ? ", ".$eu["pocaousada"]."='".addslashes($eu[$eu["pocaousada"]])."', pocoesluta='".addslashes($eu["pocoesluta"])."'" : ""; // "None" ou a pilha com 1 a menos (e a poção anotada no limite da luta).
        doquery("UPDATE {{table}} SET currenthp='".intval($eu["currenthp"])."', currentmp='".intval($eu["currentmp"])."'$extra WHERE id='$meuid' LIMIT 1", "users");
        doquery("UPDATE {{table}} SET currenthp='".intval($jogadores[$outrolado]["currenthp"])."', currentmp='".max(0, intval($jogadores[$outrolado]["currentmp"]))."' WHERE id='".intval($jogadores[$outrolado]["id"])."' LIMIT 1", "users");

        if (!empty($jogadores[$outrolado]["derrotado"])) {
            dueloencerrar($duelo, $jogadores, $meulado, "hp");
            duelogravar($duelo);
            doquery("COMMIT", "duelos");
            header("Location: users.php?do=duelo&ver=".intval($duelo["id"])); die();
        }
        // Fim da vez: efeitos por turno (no fim da rodada), passa a vez e pula quem está atordoado.
        $caiu = duelofimdavez($duelo, $jogadores);
        duelogravarjogadores($jogadores);
        if ($caiu) { dueloencerrar($duelo, $jogadores, 3 - $caiu, "hp"); }
        duelogravar($duelo);
        doquery("COMMIT", "duelos");
        header("Location: users.php?do=duelo".($caiu ? "&ver=".intval($duelo["id"]) : "")); die();

    }

    // ---------- Tela ----------
    dueloatualizar($meuid);
    $ver = pegarint($_GET, "ver");
    $duelo = duelocarregar($meuid, "ativo", false);
    if (!$duelo) {
        if ($userrow["batalha_timer2"] == 5) { limparduelo($meuid); }
        // Duelo terminado: o pedido (ver=ID) ou o último que terminou nos últimos 10 minutos.
        if ($ver > 0) {
            $duelo = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$ver' AND (desafiante='$meuid' OR desafiado='$meuid') AND estado='fim' LIMIT 1", "duelos"));
        } else {
            $duelo = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE (desafiante='$meuid' OR desafiado='$meuid') AND estado='fim' AND atualizado >= '".(time() - 600)."' ORDER BY id DESC LIMIT 1", "duelos"));
        }
        if (!$duelo) { header("Location: users.php?do=batalha1"); die(); }
    }

    $meulado = ($duelo["desafiante"] == $meuid) ? 1 : 2;
    $outroid = ($meulado == 1) ? intval($duelo["desafiado"]) : intval($duelo["desafiante"]);
    $eu = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$meuid' LIMIT 1", "users"));
    $outro = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$outroid' LIMIT 1", "users"));
    if (!$outro) { $outro = array("charname"=>"?", "avatar"=>1, "alinhamento"=>"", "elemento"=>"neutro", "level"=>0, "currenthp"=>0, "maxhp"=>1, "currentmp"=>0, "maxmp"=>1); }
    $ativo = ($duelo["estado"] == "ativo");
    $minhavez = ($ativo && $duelo["vez"] == $meuid);
    $restam = max(0, intval($duelo["prazo"]) - time());
    $avatar = intval($eu["avatar"]);
    $aposta = intval($duelo["aposta"]);
    $estadojutsu = jutsuestadoler($duelo["jutsuestado"]); // efeitos ativos (ícones nos cartões) e recargas.

    // Faixa do turno.
    $bonus = array();
    if ($duelo["dano".$meulado] > 0) { $bonus[] = tf("+%s%% de dano", intval($duelo["dano".$meulado])); }
    if ($duelo["defesa".$meulado] > 0) { $bonus[] = tf("+%s%% de defesa", intval($duelo["defesa".$meulado])); }
    if ($duelo["guarda".$meulado]) { $bonus[] = t("defendendo"); }
    $bonustexto = count($bonus) ? " &middot; ".t("Seus bônus:")." ".implode(", ", $bonus) : "";
    $apostatexto = ($aposta > 0) ? " &middot; ".tf("Prêmio: %s Ryou", number_format((float)(2 * $aposta))) : "";
    if ($minhavez) {
        $faixa = "<b>".tf("Turno %s", intval($duelo["turno"]))." &mdash; ".t("Sua vez!")."</b> ".t("Tempo:")." ".duelocontagem($restam, ($restam + 2).";URL=users.php?do=duelo");
    } elseif ($ativo) {
        $faixa = "<b>".tf("Turno %s", intval($duelo["turno"]))." &mdash; ".tf("Vez de %s.", $outro["charname"])."</b> ".t("Tempo:")." ".duelocontagem($restam, "4;URL=users.php?do=duelo");
    } else {
        $venceu = ($duelo["vencedor"] == $meuid);
        $faixa = "<b>".($venceu ? t("Você venceu o duelo!") : t("Você perdeu o duelo."))."</b>";
    }
    // Segunda linha (menor): prêmio, bônus ativos e tempos esgotados do jogador.
    if ($ativo) { $faixa .= "<br /><span class=\"duelodetalhe\">".tf("Tempos esgotados: %s de %s", intval($duelo["atrasos".$meulado]), DUELO_MAXATRASOS).$apostatexto.$bonustexto."</span>"; }
    elseif ($aposta > 0) { $faixa .= "<br /><span class=\"duelodetalhe\">".tf("Prêmio: %s Ryou", number_format((float)(2 * $aposta)))."</span>"; }

    // Comandos (só na minha vez).
    $comandos = "";
    $csrf = csrf_campo();
    $turno = intval($duelo["turno"]);
    if ($minhavez) {
        $userrow = $eu; // custochakra() usa a Economia de Chakra do jogador.
        $magiclist = "";
        $meusjutsus = array(); // cartões (jutsugrade(), funcoesjutsus.php), como na luta contra inimigos.
        $userspells = explode(",", $eu["spells"]);
        $spellquery = doquery("SELECT * FROM {{table}} WHERE type<>'3' AND uso<>'menu' ORDER BY id", "spells");
        while ($spellrow = mysqli_fetch_array($spellquery)) {
            if (in_array($spellrow["id"], $userspells)) {
                // Jutsu em recarga (ou de Senjutsu sem o modo ativo/sem NP) aparece desabilitado, como na luta contra monstros.
                $recarga = jutsurecarga($estadojutsu, $meulado, $spellrow["id"]);
                $bloqueado = ($recarga > 0 || !jutsudisponivel($spellrow, $eu));
                $magiclist .= "<option value=\"".intval($spellrow["id"])."\"".($bloqueado ? " disabled=\"disabled\"" : "").">".jutsurotulo($spellrow, $eu).($recarga > 0 ? " (".tf("recarga: %s", $recarga).")" : "")."</option>\n";
                $meusjutsus[] = array($spellrow, $recarga, $bloqueado);
            }
        }
        $pocoes = "";
        for ($i = 1; $i <= 8; $i++) {
            $item = array_pad(explode(",", (string)$eu["bp".$i]), 4, "X");
            if ($item[1] == "hp" || $item[1] == "mp") { $pocoes .= "<option value=\"$i\">".t($item[0]).(qtditem($eu["bp".$i]) > 1 ? " ×".qtditem($eu["bp".$i]) : "")." (+".intval($item[2])." ".strtoupper($item[1] == "mp" ? "CH" : "HP").")</option>\n"; }
        }
        // Jutsus em cartões: cada cartão envia acao=jutsu com o id (o formulário próprio leva o turno e o CSRF). Jogadores não têm imunidades.
        $jutsus = ($magiclist == "") ? "<span class=\"light\">".t("Você não conhece nenhum Jutsu que funcione em duelos.")."</span>"
            : "<form action=\"users.php?do=duelo\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"turno\" value=\"$turno\" /><input type=\"hidden\" name=\"acao\" value=\"jutsu\" />".jutsugrade($meusjutsus, array())."</form>";
        // Poções em cartões (pocaograde(), funcoesjutsus.php): formulário próprio com acao=pocao; o cartão envia qual=N.
        $gradepocoes = pocaograde($eu, "qual");
        $mochila = ($gradepocoes == "") ? "<center><span class=\"light\">".t("Nenhuma poção na mochila.")."</span></center>"
            : "<form action=\"users.php?do=duelo\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"turno\" value=\"$turno\" /><input type=\"hidden\" name=\"acao\" value=\"pocao\" />$gradepocoes</form>";
        // Ferramentas ninja (itembatalhagrade(), funcoesjutsus.php): acao=arremesso, o cartão envia qual=N.
        $gradearr = itembatalhagrade($eu, "qual");
        if ($gradearr != "") { $mochila .= "<div style=\"margin:8px 0 3px;text-align:center;font-weight:bold;color:#663300;font-size:11px;letter-spacing:1px\">".t("FERRAMENTAS NINJA")."</div><form action=\"users.php?do=duelo\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"turno\" value=\"$turno\" /><input type=\"hidden\" name=\"acao\" value=\"arremesso\" />$gradearr</form>"; }
        $comandos = "<form action=\"users.php?do=duelo\" method=\"post\">$csrf<input type=\"hidden\" name=\"turno\" value=\"$turno\" />"
            ."<table class=\"duelolista\"><tr><td style=\"text-align:center\"><button type=\"submit\" name=\"acao\" value=\"atacar\" class=\"btnjogo bj-ataque\"><img src=\"images/jutsu_taijutsu.png\" class=\"jutsuico\" width=\"20\" height=\"17\" alt=\"\"> ".t("Atacar")."</button> <button type=\"submit\" name=\"acao\" value=\"defender\" class=\"btnjogo bj-defesa\"><img src=\"images/jutsu_defesa.png\" class=\"jutsuico\" width=\"20\" height=\"17\" alt=\"\"> ".t("Defender")."</button></td></tr>"
            ."</table></form>"
            ."<div style=\"margin:8px 0 3px;text-align:center;font-weight:bold;color:#663300;font-size:11px;letter-spacing:1px\">".t("POÇÕES")."</div>".$mochila
            ."<div style=\"margin:8px 0 3px;text-align:center;font-weight:bold;color:#663300;font-size:11px;letter-spacing:1px\">".t("JUTSUS")."</div>".$jutsus;
    } elseif ($ativo) {
        $comandos = "<center><img src=\"layoutnovo/graficos/".$avatar."_stance.gif\" alt=\"\" /><br />".tf("Aguardando o movimento de %s...", $outro["charname"])."</center>";
    } else {
        $venceu = ($duelo["vencedor"] == $meuid);
        $comandos = "<center><img src=\"layoutnovo/graficos/".$avatar.($venceu ? "_ganhou" : "_morto").".gif\" alt=\"\" /><br />";
        if ($venceu) { $comandos .= ($aposta > 0) ? tf("Você recebeu %s Ryou.", number_format((float)(2 * $aposta))) : ""; }
        $comandos .= "<br /><br /><a href=\"users.php?do=batalha1\">".t("Voltar ao saguão de duelos")."</a> &middot; <a href=\"index.php\">".t("Voltar ao jogo")."</a></center>";
    }
    $desistir = $ativo ? "<form action=\"users.php?do=duelo\" method=\"post\" onsubmit=\"return confirm('".textojs(t("Desistir do duelo? Você perde o duelo e a aposta."))."');\">$csrf<input type=\"hidden\" name=\"turno\" value=\"$turno\" /><center><button type=\"submit\" name=\"acao\" value=\"desistir\" class=\"btnjogo bj-cancelar\"><img src=\"images/jutsu_desistir.png\" class=\"jutsuico\" width=\"20\" height=\"17\" alt=\"\"> ".t("Desistir")."</button></center></form>" : "";

    $pagearray = array(
        "titulo" => titulopagina("duelo.gif"),
        "estilo" => dueloestilo(),
        "mensagem" => mensagemtopo(),
        "arena" => dueloarena($eu, $outro, $ativo, intval($duelo["vencedor"]), $meuid),
        "cartao1" => cartaodojogador($eu, $minhavez, jutsuicones($estadojutsu, $meulado), true),
        "cartao2" => cartaodojogador($outro, $ativo && !$minhavez, jutsuicones($estadojutsu, 3 - $meulado), true),
        "faixa" => $faixa,
        "comandos" => duelopainel($minhavez ? t("Comando?") : ($ativo ? t("Aguarde") : t("Fim do duelo")), $comandos),
        "registro" => duelopainel(t("Registro do duelo"), duelotextoregistro($duelo)),
        "desistir" => $desistir,
    );
    $page = parsetemplate(gettemplate("duelo"), $pagearray);
    display($page, $titulo, false, false, false);

}

/* ------------------------------------------------------------------------
   Procurar jogador.
   ------------------------------------------------------------------------ */

function procurarjogador() { // Procura um personagem pelo nome e abre o perfil dele (index.php?do=onlinechar:ID).

    global $userrow;
    exigirjogador(false);

    if (isset($_POST["submit"])) {
        $jogador = pegartexto($_POST, "username");
        $jogadorquery = doquery("SELECT id FROM {{table}} WHERE charname='$jogador' LIMIT 1","users");
        if (mysqli_num_rows($jogadorquery) != 1) { display(t("Não existe nenhuma conta com esse Nome."),t("Erro"),false,false,false); }
        $jogadorrow = mysqli_fetch_array($jogadorquery);
        header("Location: index.php?do=onlinechar:".intval($jogadorrow["id"]));
        die();
    }

    $page = gettemplate("procurarjogador");
    display($page, t("Procurar Jogador"), false, false, false);

}

?>
