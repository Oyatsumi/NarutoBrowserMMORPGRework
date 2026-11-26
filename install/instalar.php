<?php
/* =============================================================================
 *  instalar.php :: Instalador pelo navegador, para hospedagens comuns (cPanel, Hostinger, InfinityFree...),
 *  onde não há acesso SSH/root. Em VM/servidor próprio com SSH use o install/install.sh.
 *
 *  Como usar (detalhes em docs/HOSPEDAGEM.md):
 *    1. Crie no painel da hospedagem um banco MySQL/MariaDB vazio (nome, usuário e senha).
 *    2. Envie para a pasta pública do site (public_html/, htdocs/...): o CONTEÚDO da pasta game/,
 *       este instalar.php e o sql/naruto_rpg.sql (pode ficar ao lado deste arquivo).
 *    3. Abra https://seu-site/instalar.php e siga a tela.
 *
 *  O que ele faz: confere o PHP; pede a chave de instalação (gravada no arquivo instalar_chave.php, que só quem
 *  tem acesso aos arquivos do site consegue ler); importa o naruto_rpg.sql num banco sem tabelas do jogo;
 *  grava o config.php com um segredo aleatório; ajusta nome/endereço/e-mail/idioma do jogo e cria a conta de
 *  administrador. No fim grava instalar.lock (o instalador não roda de novo) e tenta apagar a si mesmo, a chave
 *  e o .sql. Não altera nenhum arquivo do jogo além do config.php.
 * ============================================================================= */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
@set_time_limit(300);
header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$DIR = __DIR__;
$PREFIXO = 'naruto'; // o naruto_rpg.sql cria as tabelas com esse prefixo
$TRAVA = $DIR . '/instalar.lock';
$ARQCHAVE = $DIR . '/instalar_chave.php'; // .php: aberto pelo navegador não mostra nada; só o gerenciador de arquivos mostra a chave

/* ---------- idioma (pt/en, pela escolha na tela ou pelo navegador) ---------- */
$idioma = isset($_GET['lang']) && in_array($_GET['lang'], array('pt', 'en'), true) ? $_GET['lang']
    : (isset($_POST['lang']) && in_array($_POST['lang'], array('pt', 'en'), true) ? $_POST['lang']
    : (stripos((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 'pt') === 0 || stripos((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 'pt') !== false ? 'pt' : 'en'));
function L(string $pt, string $en): string { global $idioma; return $idioma === 'en' ? $en : $pt; }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ---------- página ---------- */
function pagina(string $titulo, string $corpo): void {
    global $idioma;
    $outro = $idioma === 'en' ? '<a href="?lang=pt">Português</a>' : '<a href="?lang=en">English</a>';
    echo '<!doctype html><html lang="' . ($idioma === 'en' ? 'en' : 'pt-BR') . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . h($titulo) . '</title><style>'
        . 'body{margin:0;background:#2b1a0c;color:#2a1a0c;font:15px/1.5 system-ui,Segoe UI,Roboto,Arial,sans-serif}'
        . '.cx{max-width:720px;margin:24px auto;background:#fff1c7;border:2px solid #452202;border-radius:8px;padding:18px 22px}'
        . 'h1{margin:0 0 4px;color:#452202;font-size:22px}h2{color:#613003;font-size:17px;margin:20px 0 6px;border-bottom:1px solid #c9b47a}'
        . 'label{display:block;margin:8px 0 2px;font-weight:600}input[type=text],input[type=password],input[type=email],input[type=url],select{width:100%;box-sizing:border-box;padding:7px;border:1px solid #8a6a3a;border-radius:4px;background:#fffaf0;font-size:15px}'
        . '.dica{font-size:12px;color:#6b4b25}.ok{color:#1d6b1d}.erro{color:#a40000}.av{background:#ffe3e3;border:1px solid #a40000;padding:8px 10px;border-radius:5px;margin:10px 0}'
        . '.bom{background:#e6f6e1;border:1px solid #2e7d32;padding:8px 10px;border-radius:5px;margin:10px 0}'
        . 'button{margin-top:16px;padding:10px 18px;font-size:16px;font-weight:700;background:#452202;color:#fff1c7;border:0;border-radius:6px;cursor:pointer}'
        . 'table{border-collapse:collapse;width:100%}td{padding:3px 6px;border-bottom:1px solid #e4d094}code{background:#f3e2b0;padding:1px 4px;border-radius:3px}'
        . '.lg{float:right;font-size:13px}.lg a{color:#613003}</style></head><body><div class="cx"><div class="lg">' . $outro . '</div>'
        . '<h1>' . h($titulo) . '</h1>' . $corpo . '</div></body></html>';
    exit;
}

/* ---------- já instalado? ---------- */
if (is_file($TRAVA)) {
    pagina(L('Jogo já instalado', 'Game already installed'),
        '<div class="av">' . L('Este instalador já foi usado (existe o arquivo <code>instalar.lock</code>). Por segurança, apague <code>instalar.php</code> do servidor.',
            'This installer has already been used (the file <code>instalar.lock</code> exists). For safety, delete <code>instalar.php</code> from the server.') . '</div>'
        . '<p><a href="index.php">' . L('Ir para o jogo', 'Go to the game') . '</a></p>');
}

/* ---------- requisitos ---------- */
$req = array(
    array('PHP 8.0+', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION),
    array('mysqli', extension_loaded('mysqli'), ''),
    array('mbstring', extension_loaded('mbstring'), ''),
    array('gd', extension_loaded('gd'), ''),
    array('json', function_exists('json_encode'), ''),
    array(L('Arquivos do jogo nesta pasta (index.php, lib.php)', 'Game files in this folder (index.php, lib.php)'), is_file($DIR . '/index.php') && is_file($DIR . '/lib.php'), ''),
    array(L('Permissão para gravar o config.php', 'Permission to write config.php'), is_file($DIR . '/config.php') ? is_writable($DIR . '/config.php') : is_writable($DIR), ''),
);
$arqsql = '';
foreach (array($DIR . '/naruto_rpg.sql', $DIR . '/sql/naruto_rpg.sql', dirname($DIR) . '/sql/naruto_rpg.sql') as $c) { if (is_file($c)) { $arqsql = $c; break; } }
$req[] = array(L('Arquivo naruto_rpg.sql', 'File naruto_rpg.sql'), $arqsql !== '', $arqsql !== '' ? basename(dirname($arqsql)) . '/' . basename($arqsql) : L('envie o sql/naruto_rpg.sql para esta pasta', 'upload sql/naruto_rpg.sql to this folder'));
$reqok = true;
$tabreq = '<table>';
foreach ($req as $r) { $reqok = $reqok && $r[1]; $tabreq .= '<tr><td>' . h($r[0]) . '</td><td class="' . ($r[1] ? 'ok">✔' : 'erro">✘') . '</td><td class="dica">' . h($r[2]) . '</td></tr>'; }
$tabreq .= '</table>';

/* ---------- chave de instalação (prova de que quem instala tem acesso aos arquivos) ---------- */
if (!is_file($ARQCHAVE)) { @file_put_contents($ARQCHAVE, "<?php exit; ?>\nCHAVE: " . bin2hex(random_bytes(8)) . "\n"); @chmod($ARQCHAVE, 0600); }
$chave = (is_file($ARQCHAVE) && preg_match('/CHAVE: ([0-9a-f]{16})/', (string)@file_get_contents($ARQCHAVE), $m)) ? $m[1] : '';
if ($chave === '') { $reqok = false; $tabreq .= '<div class="av">' . L('Não foi possível criar o arquivo instalar_chave.php: dê permissão de escrita a esta pasta.', 'Could not create instalar_chave.php: give write permission to this folder.') . '</div>'; }

/* ---------- valores do formulário ---------- */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$urlpadrao = ($https ? 'https://' : 'http://') . preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'))
    . rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/';
$v = array('db_servidor' => 'localhost', 'db_nome' => '', 'db_usuario' => '', 'db_senha' => '', 'nome_jogo' => 'Naruto! Nigeru RPG',
    'url_jogo' => $urlpadrao, 'fuso' => 'America/Sao_Paulo', 'idioma_jogo' => $idioma, 'admin_email' => '', 'admin_conta' => '', 'admin_personagem' => '',
    'admin_senha' => '', 'admin_senha2' => '', 'verificar_email' => '0', 'chave' => '');
foreach ($v as $k => $padrao) { if (isset($_POST[$k]) && is_string($_POST[$k])) { $v[$k] = trim($_POST[$k]); } }

$erros = array();
$resultado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reqok) {
    /* ---------- validação ---------- */
    if (!hash_equals($chave, $v['chave'])) { $erros[] = L('Chave de instalação errada. Ela está no arquivo instalar_chave.php, nesta mesma pasta do site.', 'Wrong installation key. It is in the file instalar_chave.php, in this same site folder.'); }
    foreach (array('db_servidor', 'db_nome', 'db_usuario') as $k) { if ($v[$k] === '') { $erros[] = L('Preencha os dados do banco de dados.', 'Fill in the database details.'); break; } }
    if (!preg_match('#^https?://[^\s"\'<>]+$#i', $v['url_jogo'])) { $erros[] = L('O endereço do jogo deve começar com http:// ou https://.', 'The game address must start with http:// or https://.'); }
    if (!in_array($v['fuso'], timezone_identifiers_list(), true)) { $erros[] = L('Fuso horário inválido.', 'Invalid time zone.'); }
    if (!filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) { $erros[] = L('E-mail do administrador inválido.', 'Invalid administrator e-mail.'); }
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $v['admin_conta'])) { $erros[] = L('Conta do administrador: 3 a 30 letras, números ou _.', 'Administrator account: 3 to 30 letters, digits or _.'); }
    if (!preg_match('/^[A-Za-z0-9_ ]{3,30}$/', $v['admin_personagem'])) { $erros[] = L('Nome do personagem: 3 a 30 letras, números, espaço ou _.', 'Character name: 3 to 30 letters, digits, space or _.'); }
    if (strlen($v['admin_senha']) < 8) { $erros[] = L('A senha do administrador precisa ter pelo menos 8 caracteres.', 'The administrator password must have at least 8 characters.'); }
    elseif ($v['admin_senha'] !== $v['admin_senha2']) { $erros[] = L('As duas senhas do administrador não são iguais.', 'The two administrator passwords do not match.'); }
    if ($v['nome_jogo'] === '' || mb_strlen($v['nome_jogo']) > 60) { $erros[] = L('Nome do jogo: 1 a 60 caracteres.', 'Game name: 1 to 60 characters.'); }
    $idiomajogo = in_array($v['idioma_jogo'], array('pt', 'en'), true) ? $v['idioma_jogo'] : 'pt';

    /* ---------- banco ---------- */
    $db = null;
    if (!$erros) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = @mysqli_connect($v['db_servidor'], $v['db_usuario'], $_POST['db_senha'] ?? '', $v['db_nome']);
        if (!$db) { $erros[] = L('Não conectou no banco: ', 'Could not connect to the database: ') . h(mysqli_connect_error()); }
        else {
            mysqli_set_charset($db, 'utf8mb4');
            $r = mysqli_query($db, "SHOW TABLES LIKE '" . $PREFIXO . "\\_%'");
            if ($r && mysqli_num_rows($r) > 0) { $erros[] = L('Este banco já tem tabelas do jogo (naruto_...). Use um banco vazio, ou apague as tabelas antigas no phpMyAdmin antes.', 'This database already has game tables (naruto_...). Use an empty database, or drop the old tables in phpMyAdmin first.'); }
        }
    }

    /* ---------- importação ---------- */
    if (!$erros) {
        $sql = (string)file_get_contents($arqsql);
        if (!mysqli_multi_query($db, $sql)) { $erros[] = L('Erro ao importar: ', 'Import error: ') . h(mysqli_error($db)); }
        else {
            do { if ($res = mysqli_store_result($db)) { mysqli_free_result($res); } } while (mysqli_more_results($db) && mysqli_next_result($db));
            if (mysqli_errno($db)) { $erros[] = L('Erro ao importar (parte do arquivo): ', 'Import error (part of the file): ') . h(mysqli_error($db)); }
        }
        unset($sql);
        if (!$erros) {
            $r = mysqli_query($db, "SHOW TABLES LIKE '" . $PREFIXO . "\\_%'");
            $ntab = $r ? mysqli_num_rows($r) : 0;
            if ($ntab < 30 || !mysqli_query($db, "SELECT id FROM {$PREFIXO}_control LIMIT 1")) { $erros[] = L('A importação terminou incompleta (', 'The import finished incomplete (') . $ntab . L(' tabelas). Apague as tabelas e tente de novo.', ' tables). Drop the tables and try again.'); }
        }
    }

    /* ---------- config.php ---------- */
    if (!$erros) {
        $s = function (string $x): string { return addcslashes($x, "\\\"\$"); };
        $config = "<?php // config.php :: Variáveis de baixo nível do jogo (conexão com o banco e segredos).\n"
            . "// Gerado pelo instalar.php em " . date('d/m/Y H:i') . ". Não publique este arquivo.\n\n"
            . "\$dbsettings = Array(\n"
            . "        \"server\"        => \"" . $s($v['db_servidor']) . "\",\n"
            . "        \"user\"          => \"" . $s($v['db_usuario']) . "\",\n"
            . "        \"pass\"          => \"" . $s((string)($_POST['db_senha'] ?? '')) . "\",\n"
            . "        \"name\"          => \"" . $s($v['db_nome']) . "\",\n"
            . "        \"prefix\"        => \"" . $PREFIXO . "\",\n"
            . "        \"secretword\"    => \"" . bin2hex(random_bytes(32)) . "\",\n"
            . "        \"cookiename\"    => \"narutorpg\",\n"
            . "        \"timezone\"      => \"" . $s($v['fuso']) . "\",\n"
            . "        \"adminips\"      => \"\",  // IPs/faixas que podem abrir admin.php, admin_missoes.php e gm.php (vazio = qualquer um).\n"
            . "        \"debug\"         => false);\n\n?>\n";
        if (@file_put_contents($DIR . '/config.php', $config, LOCK_EX) === false) { $erros[] = L('Não foi possível gravar o config.php (permissão de escrita).', 'Could not write config.php (write permission).'); }
        else { @chmod($DIR . '/config.php', 0640); if (function_exists('opcache_invalidate')) { @opcache_invalidate($DIR . '/config.php', true); } }
    }

    /* ---------- configurações e administrador (igual ao install/criar_admin.php) ---------- */
    if (!$erros) {
        $e = function (string $x) use ($db): string { return mysqli_real_escape_string($db, htmlspecialchars($x, ENT_QUOTES, 'UTF-8')); };
        $vem = intval($v['verificar_email'] === '1');
        mysqli_query($db, "UPDATE {$PREFIXO}_control SET gamename='" . $e($v['nome_jogo']) . "', gameurl='" . $e($v['url_jogo']) . "', adminemail='" . $e($v['admin_email'])
            . "', verifyemail='$vem', emailativo='$vem', gameopen='1', idiomapadrao='$idiomajogo' WHERE id='1' LIMIT 1");
        // A senha passa pelo mesmo tratamento que o login aplica ao que o jogador digita (addslashes + htmlspecialchars)
        // e usa o mesmo hash do jogo (senha_algoritmo() no lib.php: Argon2id quando existe, senão bcrypt 12).
        $alg = (defined('PASSWORD_ARGON2ID') && in_array(PASSWORD_ARGON2ID, password_algos(), true)) ? array(PASSWORD_ARGON2ID, array()) : array(PASSWORD_BCRYPT, array('cost' => 12));
        $hash = password_hash(htmlspecialchars(addslashes((string)$_POST['admin_senha']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $alg[0], $alg[1]);
        $ok = mysqli_query($db, "INSERT INTO {$PREFIXO}_users SET regdate=NOW(), onlinetime=(NOW() - INTERVAL 1 DAY), verify='1', username='" . $e($v['admin_conta'])
            . "', password='" . mysqli_real_escape_string($db, $hash) . "', email='" . $e($v['admin_email']) . "', charname='" . $e($v['admin_personagem'])
            . "', authlevel='1', charclass='1', difficulty='1', gold='1000', towns='0', spells='0', caixadepm='', mainmsg='None', bancogeral='None', historico='None', questsaux='None', treinamento='None'");
        if (!$ok) { $erros[] = L('O jogo foi importado, mas a conta de administrador não foi criada: ', 'The game was imported, but the administrator account was not created: ') . h(mysqli_error($db)); }
    }

    /* ---------- fim ---------- */
    if (!$erros) {
        @file_put_contents($TRAVA, 'instalado em ' . date('c') . "\n");
        $apagados = array();
        foreach (array($ARQCHAVE, $arqsql, __FILE__) as $f) { if (@unlink($f)) { $apagados[] = basename($f); } }
        $resta = array_diff(array('instalar_chave.php', basename($arqsql), 'instalar.php'), $apagados);
        $resultado = '<div class="bom"><b>' . L('Instalação concluída!', 'Installation complete!') . '</b></div>'
            . '<p>' . L('Entre no jogo com a conta', 'Log in to the game with the account') . ' <b>' . h($v['admin_conta']) . '</b> ' . L('e abra o <b>Painel do Administrador</b> no menu.', 'and open the <b>Administrator Panel</b> from the menu.') . '</p>'
            . ($resta ? '<div class="av">' . L('Apague estes arquivos do servidor pelo gerenciador de arquivos/FTP: ', 'Delete these files from the server with the file manager/FTP: ') . '<b>' . h(implode(', ', $resta)) . '</b></div>' : '')
            . '<p>' . L('Próximos passos (veja docs/HOSPEDAGEM.md): HTTPS no <code>.htaccess</code>, permissões das pastas de imagens e backups pelo cron do painel.', 'Next steps (see docs/en/HOSTING.md): HTTPS in <code>.htaccess</code>, image folder permissions and backups via the panel cron.') . '</p>'
            . '<p><a href="index.php"><b>' . L('Abrir o jogo', 'Open the game') . ' »</b></a> &nbsp; <a href="verificar.php">' . L('Conferir a instalação (verificar.php)', 'Check the installation (verificar.php)') . '</a></p>';
        pagina(L('Naruto RPG — instalação', 'Naruto RPG — installation'), $resultado);
    }
}

/* ---------- formulário ---------- */
$c = '<p>' . L('Instalador para hospedagens sem SSH. Em servidor/VM com SSH use <code>sudo bash install/install.sh</code>.', 'Installer for hosting without SSH. On a server/VM with SSH use <code>sudo bash install/install.sh</code>.') . '</p>';
$c .= '<h2>' . L('1. Requisitos', '1. Requirements') . '</h2>' . $tabreq;
if (!$reqok) { pagina(L('Naruto RPG — instalação', 'Naruto RPG — installation'), $c . '<div class="av">' . L('Corrija os itens marcados com ✘ e recarregue a página.', 'Fix the items marked with ✘ and reload the page.') . '</div>'); }
if ($erros) { $c .= '<div class="av">' . implode('<br>', $erros) . '</div>'; }
$campo = function (string $nome, string $rot, string $tipo = 'text', string $dica = '', string $extra = '') use ($v): string {
    $valor = ($tipo === 'password') ? '' : ' value="' . h($v[$nome]) . '"';
    return '<label for="' . $nome . '">' . $rot . '</label><input type="' . $tipo . '" id="' . $nome . '" name="' . $nome . '"' . $valor . ' ' . $extra . '>' . ($dica ? '<div class="dica">' . $dica . '</div>' : '');
};
$c .= '<form method="post" autocomplete="off"><input type="hidden" name="lang" value="' . h($idioma) . '">';
$c .= '<h2>' . L('2. Chave de instalação', '2. Installation key') . '</h2>'
    . $campo('chave', L('Chave', 'Key'), 'text', L('Abra o arquivo <code>instalar_chave.php</code> (criado agora nesta pasta do site) pelo gerenciador de arquivos e copie o código da linha <code>CHAVE:</code>. Isso impede que outra pessoa instale no seu lugar.', 'Open the file <code>instalar_chave.php</code> (just created in this site folder) with the file manager and copy the code from the <code>CHAVE:</code> line. This prevents someone else from installing in your place.'), 'required maxlength="40"');
$c .= '<h2>' . L('3. Banco de dados', '3. Database') . '</h2><div class="dica">' . L('Crie no painel da hospedagem um banco vazio e um usuário com todas as permissões nele.', 'Create an empty database and a user with all privileges on it in your hosting panel.') . '</div>'
    . $campo('db_servidor', L('Servidor', 'Server'), 'text', L('Normalmente <code>localhost</code>; algumas hospedagens dão um endereço como <code>sql123.exemplo.com</code>.', 'Usually <code>localhost</code>; some hosts give an address like <code>sql123.example.com</code>.'), 'required')
    . $campo('db_nome', L('Nome do banco', 'Database name'), 'text', '', 'required')
    . $campo('db_usuario', L('Usuário', 'User'), 'text', '', 'required')
    . $campo('db_senha', L('Senha', 'Password'), 'password');
$c .= '<h2>' . L('4. Jogo', '4. Game') . '</h2>'
    . $campo('nome_jogo', L('Nome do jogo', 'Game name'), 'text', '', 'required maxlength="60"')
    . $campo('url_jogo', L('Endereço do jogo', 'Game address'), 'url', L('Com a barra no fim. Detectado automaticamente.', 'With the trailing slash. Detected automatically.'), 'required')
    . $campo('fuso', L('Fuso horário', 'Time zone'), 'text', L('Ex.: America/Sao_Paulo, Europe/Lisbon.', 'E.g. America/Sao_Paulo, Europe/London.'), 'required')
    . '<label for="idioma_jogo">' . L('Idioma padrão', 'Default language') . '</label><select id="idioma_jogo" name="idioma_jogo"><option value="pt"' . ($v['idioma_jogo'] === 'pt' ? ' selected' : '') . '>Português</option><option value="en"' . ($v['idioma_jogo'] === 'en' ? ' selected' : '') . '>English</option></select>'
    . '<label for="verificar_email">' . L('Confirmar o e-mail no cadastro', 'Confirm the e-mail on sign-up') . '</label><select id="verificar_email" name="verificar_email"><option value="0"' . ($v['verificar_email'] !== '1' ? ' selected' : '') . '>' . L('Não (recomendado se a hospedagem não envia e-mails)', 'No (recommended if the host does not send e-mails)') . '</option><option value="1"' . ($v['verificar_email'] === '1' ? ' selected' : '') . '>' . L('Sim', 'Yes') . '</option></select>';
$c .= '<h2>' . L('5. Administrador', '5. Administrator') . '</h2>'
    . $campo('admin_conta', L('Conta (login)', 'Account (login)'), 'text', '', 'required maxlength="30"')
    . $campo('admin_personagem', L('Nome do personagem', 'Character name'), 'text', '', 'required maxlength="30"')
    . $campo('admin_email', 'E-mail', 'email', '', 'required')
    . $campo('admin_senha', L('Senha (mín. 8)', 'Password (min. 8)'), 'password', L('Também é pedida de novo para abrir os painéis.', 'It is also asked again to open the panels.'), 'required minlength="8"')
    . $campo('admin_senha2', L('Repita a senha', 'Repeat the password'), 'password', '', 'required minlength="8"');
$c .= '<button type="submit">' . L('Instalar', 'Install') . '</button><div class="dica">' . L('A importação leva alguns segundos.', 'The import takes a few seconds.') . '</div></form>';
pagina(L('Naruto RPG — instalação', 'Naruto RPG — installation'), $c);
