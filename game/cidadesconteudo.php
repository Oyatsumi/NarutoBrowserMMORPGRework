<?php // cidadesconteudo.php :: Regras das vilas lidas da tabela towns (serviços, vila natal/alinhamento, cabeçalho) e o painel de ícones da página da cidade.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

// Tudo o que antes era fixo no código pelo id da vila agora vem de colunas da tabela towns, assim uma vila
// nova criada pelo admin funciona sem mexer no PHP:
//   tem_pousada, tem_loja, tem_mapas, tem_banco, tem_missoes  serviços comuns (1 = a vila tem);
//   tem_graduacao (antes: vilas 1 e 3), tem_alquimia (2 e 5), tem_senjutsu (2), tem_jutsubusca (5);
//   secreta = 1   o mapa da vila não é vendido, é preciso descobri-la andando (antes: vilas 8, 9 e 10);
//   alinhamento   código do alinhamento ("folha", "pedra"...) cuja vila natal é esta;
//   vilanatal = 1 aparece como opção no registro e recebe de volta quem morre com esse alinhamento;
//   descricao / descricao_en, name_en, kage_en   textos exibidos (o inglês entra pelo lang/en/conteudo_vilas_banco.php).

if (!function_exists('dadosdacidade')){
function dadosdacidade($cidade) { // Linha completa da vila: aceita a própria linha (array) ou o id. Guarda em cache por requisição.

    static $cache = array();
    if (is_array($cidade)) {
        if (isset($cidade["tem_mapas"])) { return $cidade; } // Já é a linha completa.
        $cidade = isset($cidade["id"]) ? $cidade["id"] : 0;
    }
    $id = intval($cidade);
    if (!isset($cache[$id])) {
        $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "towns");
        $cache[$id] = mysqli_fetch_array($query);
    }
    return $cache[$id];

}}

if (!function_exists('cidadetemservico')){
function cidadetemservico($cidade, $servico) { // A vila oferece o serviço? ($servico = pousada, loja, mapas, banco, missoes, graduacao, alquimia, senjutsu, jutsubusca)

    $permitidos = array("pousada", "loja", "mapas", "banco", "missoes", "graduacao", "alquimia", "senjutsu", "jutsubusca");
    if (!in_array($servico, $permitidos, true)) { return false; }
    $row = dadosdacidade($cidade);
    return ($row && intval($row["tem_".$servico]) == 1);

}}

if (!function_exists('cidadetemlojademapas')){
function cidadetemlojademapas($cidade) { // A cidade tem a loja de mapas? (id ou linha da vila)

    return cidadetemservico($cidade, "mapas");

}}

if (!function_exists('cidadesecreta')){
function cidadesecreta($cidade) { // O mapa dessa cidade não pode ser comprado (precisa ser descoberto explorando)?

    $row = dadosdacidade($cidade);
    return ($row && intval($row["secreta"]) == 1);

}}

if (!function_exists('vilasnatais')){
function vilasnatais() { // Vilas que podem ser escolhidas como alinhamento no registro (vilanatal = 1 e código preenchido).

    $vilas = array();
    $query = doquery("SELECT * FROM {{table}} WHERE vilanatal='1' AND alinhamento<>'' ORDER BY id", "towns");
    while ($row = mysqli_fetch_array($query)) { $vilas[$row["alinhamento"]] = $row; }
    return $vilas;

}}

if (!function_exists('vilanataldoalinhamento')){
function vilanataldoalinhamento($alinhamento) { // Vila para onde volta quem morre com esse alinhamento (sem vila = vila 1; sem vila 1 = a primeira vila).

    $query = doquery("SELECT * FROM {{table}} WHERE alinhamento='".addslashes((string)$alinhamento)."' AND alinhamento<>'' ORDER BY vilanatal DESC, id LIMIT 1", "towns");
    $row = mysqli_fetch_array($query);
    if (!$row) { $row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "towns")); }
    if (!$row) { $row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} ORDER BY id LIMIT 1", "towns")); }
    return $row;

}}

if (!function_exists('alinhamentodoregistro')){
function alinhamentodoregistro($codigo) { // Valida o alinhamento escolhido no registro; inválido/vazio = o da vila 1 (ou da primeira vila natal).

    $vilas = vilasnatais();
    $codigo = (string)$codigo;
    if (isset($vilas[$codigo])) { return $codigo; }
    foreach ($vilas as $alinhamento => $row) { if (intval($row["id"]) == 1) { return $alinhamento; } }
    if (count($vilas) > 0) { return (string)array_key_first($vilas); }
    return "folha"; // Valor padrão da coluna users.alinhamento.

}}

if (!function_exists('alinhamentoinicial')){
function alinhamentoinicial() { // Alinhamento (vila natal) dado a todo ninja novo: o da vila control.vilainicial (padrão 1 = Vila da Folha).
    // O cadastro não pergunta mais a vila; trocar depois só pelas transferências dos embaixadores (docs/TROCA_DE_VILA.md).
    // Vila inválida (apagada, secreta, sem alinhamento ou sem vilanatal) = regra antiga de alinhamentodoregistro("").

    global $controlrow;
    $id = (is_array($controlrow) && isset($controlrow["vilainicial"])) ? intval($controlrow["vilainicial"]) : 1;
    $row = mysqli_fetch_array(doquery("SELECT alinhamento FROM {{table}} WHERE id='$id' AND vilanatal='1' AND alinhamento<>'' AND secreta='0' LIMIT 1", "towns"));
    return $row ? (string)$row["alinhamento"] : alinhamentodoregistro("");

}}

if (!function_exists('opcoesvilasnatais')){
function opcoesvilasnatais() { // <option> das vilas natais para o formulário de registro (rótulo traduzido).

    $html = "";
    $padrao = alinhamentodoregistro("");
    foreach (vilasnatais() as $alinhamento => $row) {
        $marcado = ($alinhamento == $padrao) ? " selected=\"selected\"" : "";
        $html .= "<option value=\"".htmlspecialchars($alinhamento, ENT_QUOTES)."\"$marcado>".t($row["name"])."</option>";
    }
    return $html;

}}

if (!function_exists('nomedoalinhamento')){
function nomedoalinhamento($alinhamento) { // Nome (traduzido) da vila natal de um alinhamento, para títulos e listas.

    static $nomes = null;
    if ($nomes === null) {
        $nomes = array();
        $query = doquery("SELECT alinhamento,name FROM {{table}} WHERE alinhamento<>'' ORDER BY vilanatal, id DESC", "towns");
        while ($row = mysqli_fetch_array($query)) { $nomes[$row["alinhamento"]] = $row["name"]; }
    }
    return isset($nomes[$alinhamento]) ? t($nomes[$alinhamento]) : ucfirst((string)$alinhamento);

}}

if (!function_exists('cabecalhodacidade')){
function cabecalhodacidade($townrow) { // Título da página da vila: a imagem town_ID.gif ou, sem ela, o nome em texto estilizado; mais a descrição (se houver).

    $id = intval($townrow["id"]);
    $boasvindas = tf("Bem-vindo à %s", t($townrow["name"]));
    if (file_exists("images/town_".$id.".gif")) {
        $html = "<img src=\"images/town_".$id.".gif\" alt=\"".$boasvindas."\" title=\"".$boasvindas."\" />";
    } else {
        $html = "<div style=\"display:inline-block;width:300px;height:25px;line-height:25px;background:#452202;border:1px solid #ffefb7;color:#ffefb7;font-family:Georgia,serif;font-size:15px;font-weight:bold;letter-spacing:1px;text-align:center;overflow:hidden;white-space:nowrap\">".t($townrow["name"])."</div>";
    }
    if (isset($townrow["descricao"]) && trim($townrow["descricao"]) != "") {
        $html .= "<div style=\"width:440px;margin:4px auto 0 auto;font-size:11px;font-style:italic\">".nl2br(t($townrow["descricao"]))."</div>";
    }
    return $html;

}}

if (!function_exists('conteudo')){
function conteudo($townrow) { // Monta o painel com os três quadros de opções sobre a imagem da cidade (só os serviços que a vila tem).

    $townrow = dadosdacidade($townrow);
    $id = intval($townrow["id"]);
    $add1 = ""; // Quadro "Compra e Venda".
    $add2 = ""; // Quadro "Administrativos".
    $add3 = ""; // Quadro "Outros".

    if (cidadetemservico($townrow, "loja")) { $add1 .= "<a href=\"index.php?do=buy\"><img src=\"images/24/shop.gif\" border=\"0\" title=\"".t("Comprar Arma/Colete/Bandana")."\"></a>"; }
    if (cidadetemservico($townrow, "mapas")) { $add1 .= "<a href=\"index.php?do=maps\"><img src=\"images/24/mapa.gif\" border=\"0\" title=\"".t("Comprar Mapas")."\"></a>"; }
    if (cidadetemservico($townrow, "banco")) { $add2 .= "<a href=\"funcaoitens.php?do=banco\"><img src=\"images/24/banco.gif\" border=\"0\" title=\"".t("Acessar o Banco")."\"></a>"; }
    if (cidadetemservico($townrow, "missoes")) { $add2 .= "<a href=\"treinamentoequests.php?do=quests\"><img src=\"images/24/missoes.gif\" border=\"0\" title=\"".t("Completar Missoes")."\"></a>"; }
    // Escolher a especialização (todas as vilas), só para quem ainda não tem uma (users.charclass = 0; especializacao.php).
    global $userrow;
    if (is_array($userrow) && isset($userrow["charclass"]) && intval($userrow["charclass"]) == 0) { $add2 .= "<a href=\"especializacao.php\"><img src=\"images/24/especializacao.gif\" border=\"0\" title=\"".t("Escolher Especialização")."\"></a>"; }
    // Escolher os elementos (todas as vilas), enquanto faltar o principal ou o secundário (users.elemento; elemento.php).
    if (is_array($userrow) && isset($userrow["elemento"]) && (strpos((string)$userrow["elemento"], ",") === false || strpos((string)$userrow["elemento"], "neutro") !== false)) { $add2 .= "<a href=\"elemento.php\"><img src=\"images/24/treino_elemento.gif\" border=\"0\" title=\"".t("Escolher Elemento")."\"></a>"; }
    if (cidadetemservico($townrow, "graduacao")) { $add2 .= "<a href=\"graduacao.php?do=graduacao\"><img src=\"images/24/graduacao.gif\" border=\"0\" title=\"".t("Graduar-se")."\"></a>"; }
    if (cidadetemservico($townrow, "pousada")) { $add3 .= "<a href=\"index.php?do=inn\"><img src=\"images/24/pousada.gif\" border=\"0\" title=\"".t("Descansar numa Pousada")."\"></a>"; }
    if (cidadetemservico($townrow, "jutsubusca")) { $add3 .= "<a href=\"jutsudebusca.php?do=jutsu\"><img src=\"images/24/busca.gif\" border=\"0\" title=\"".t("Treinar 'Jutsu de Busca'")."\"></a>"; }
    if (cidadetemservico($townrow, "senjutsu")) { $add3 .= "<a href=\"senjutsu.php?do=jutsu\"><img src=\"images/24/senjutsu.gif\" border=\"0\" title=\"".t("Treinar Senjutsu")."\"></a><a href=\"senjutsu.php?do=chamar\"><img src=\"images/24/chamar.gif\" border=\"0\" title=\"".t("Chamar Fukasaku & Shima")."\"></a>"; }
    // Treinos longos (tabela treinos, funcoestreinos.php): o mestre fica na vila treinos.vila_id.
    include_once('funcoestreinos.php');
    foreach (treinoslista() as $tr) {
        if (intval($tr["ativo"]) == 1 && intval($tr["vila_id"]) == $id) { $add3 .= "<a href=\"treinoslongos.php?do=falar&amp;t=".rawurlencode($tr["chave"])."\"><img src=\"images/24/treino_".htmlspecialchars($tr["chave"]).".gif\" border=\"0\" title=\"".htmlspecialchars(tf("Treinar '%s' com %s", t($tr["nome"]), t($tr["mestre"])), ENT_QUOTES)."\"></a>"; }
    }
    if (cidadetemservico($townrow, "alquimia")) { $add3 .= "<a href=\"alquimia.php?do=fundir\"><img src=\"images/24/alquimia.gif\" border=\"0\" title=\"".t("Alquimia de Itens")."\"></a>"; }

    // Um quadro sem nenhum serviço fica com um espaço vazio (mantém o desenho da página).
    $vazio = "<img src=\"images/24/gif24.gif\" border=\"0\" alt=\"\">";
    if ($add1 == "") { $add1 = $vazio; }
    if ($add2 == "") { $add2 = $vazio; }
    if ($add3 == "") { $add3 = $vazio; }

    $cima = "<table width=\"80%\" style=\"border:1px #452202 solid\" background=\"images/fundocidade.png\"><tr><td bgcolor=\"#452202\"><font color=\"white\">".t("Compra e Venda")."</font></td></tr>
<tr><td>$add1</td></tr></table>";

    $esquerda = "<table width=\"80%\" style=\"border:1px #452202 solid\" background=\"images/fundocidade.png\"><tr><td bgcolor=\"#452202\"><font color=\"white\">".t("Administrativos")."</font></td></tr>
<tr><td>$add2</td></tr></table>";

    $direita = "<table width=\"90%\" style=\"border:1px #452202 solid\" background=\"images/fundocidade.png\"><tr><td bgcolor=\"#452202\"><font color=\"white\">".t("Outros")."</font></td></tr>
<tr><td>$add3</td></tr></table>";

    // Imagem de fundo da cidade (sem imagem própria usa a da vila 1, para a página não ficar vazia).
    $fundo = "";
    if (file_exists("images/meiocidade_".$id.".jpg")) { $fundo = " background=\"images/meiocidade_".$id.".jpg\""; }
    elseif (file_exists("images/meiocidade_1.jpg")) { $fundo = " background=\"images/meiocidade_1.jpg\""; }

    return "<center><table width=\"487\" height=\"284\"".$fundo." style=\"background-repeat:no-repeat;background-position:center center;\"><tr><td><br><br><br><br><br><br>$esquerda</td><td><br><br><br><br><br><br><br><br>$cima</td><td><br><br><br><br><br><br><br><br><br>$direita</td></tr></table></center>";

}}

?>
