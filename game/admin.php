<?php // admin.php :: Painel do Administrador do Naruto RPG.
// Só jogadores com authlevel = 1 entram aqui (e só depois de digitar a senha de novo: painelseguranca.php). Todas as ações que alteram dados exigem o token anti-CSRF
// (csrf_campo() nos formulários e &csrf=... nos links de exclusão).
//
// Rotas (admin.php?do=...):
//   (vazio)                  resumo do jogo e avisos
//   main                     configurações gerais (tabela control)
//   news / editnews:N        notícias (adicionar, editar, apagar)
//   mensagemglobal           janela de mensagem para todos os jogadores
//   users / edituser:N       jogadores (busca, edição, banir, mandar para a vila, apagar)
//   moderacao                chat global, chat do mapa, fórum e bloqueios de login
//   manutencao               ações de manutenção (destravar batalhas, duelos etc.)
//   monsters / editmonster:N inimigos + upload da carta (layoutnovo/monstros/N.jpg), filtros, duplicar, região do mapa e terrenos
//   items / edititem:N       itens das lojas + ícone
//   drops / editdrop:N       itens de drop + ícone
//   spells / editspell:N     jutsus
//   towns / edittown:N       vilas + imagens + itens à venda (apagar só vila vazia)
//   terrenos / editterreno:N terrenos do mini mapa (tabela terrenos + images/terreno_NOME.jpg)
//   mapajogo                 mapa do jogo (somente leitura) com vilas e regiões dos inimigos
//   levels / editlevel       tabela de níveis
//   classes                  especializações: modificadores de cada classe, bônus de Senjutsu e nível para escolher (tabela classes)
//   variantes                o que cada variante rara faz (tabela variantes)
//   receitas / editreceita:N receitas da alquimia (tabela receitas): ingredientes, resultado, chance, custo, requisitos
//   caveiras                 jogadores com caveira do PvP / banidos com prazo (limpar, banir)
//   auditoria                registro de auditoria (tabela adminlog)
//   sairpainel               fecha o painel (painelseguranca.php); toda rota pede a senha de novo após 30 min sem uso
// Para adicionar um registro use o id 0 (ex.: editmonster:0).

include('lib.php');
include('cookies.php');
$link = opendb();
$userrow = checkcookies();
if ($userrow == false) { header("Location: login.php?do=login"); die(); }
if ($userrow["authlevel"] != 1) { header("Location: index.php"); die(); }
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

// IP liberado e senha digitada de novo (cookie do painel, 30 minutos sem uso): painelseguranca.php.
include_once('painelseguranca.php');
include_once('funcoesmapajogo.php'); // admregiao(), admretangulo(), admmapa(), mapajogohtml().
painelexigir("admin");

// Nenhum formulário do painel é aceito sem o token (protege contra sites que tentem enviar formulários em nome do admin).
if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && !csrf_confere()) {
    admindisplay(admmsg(t("Token de segurança inválido ou expirado. Volte, recarregue a página e tente novamente."), "erro"), t("Erro"));
}

$do = explode(":", isset($_GET["do"]) ? $_GET["do"] : "");
$id = isset($do[1]) ? intval($do[1]) : 0;

// Ações perigosas pedem a senha de novo (painelconfirmar) e toda ação que muda dados vai para o registro de auditoria.
$perigosa = admacaoperigosa($do[0], $id);
if ($perigosa != "") { painelconfirmar($perigosa); }
if ((isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") || isset($_GET["csrf"])) {
    registrarlog("admin:".preg_replace("/[^a-z0-9_]/i", "", $do[0]), $id, painelresumopost().(isset($_GET["acao"]) ? " acao=".pegartexto($_GET, "acao") : "").(isset($_GET["apagar"]) ? " apagar=".pegarint($_GET, "apagar") : ""));
}

if ($do[0] == "main") { main(); }
elseif ($do[0] == "auditoria") { auditoria(); }
elseif ($do[0] == "variantes") { variantes(); }
elseif ($do[0] == "receitas") { receitas(); }
elseif ($do[0] == "editreceita") { editreceita($id); }
elseif ($do[0] == "caveiras") { caveiras(); }
elseif ($do[0] == "news") { news(); }
elseif ($do[0] == "editnews") { editnews($id); }
elseif ($do[0] == "mensagemglobal") { mensagemglobal(); }
elseif ($do[0] == "users") { users(); }
elseif ($do[0] == "edituser") { edituser($id); }
elseif ($do[0] == "moderacao") { moderacao(); }
elseif ($do[0] == "manutencao") { manutencao(); }
elseif ($do[0] == "monsters") { monsters(); }
elseif ($do[0] == "editmonster") { editmonster($id); }
elseif ($do[0] == "items") { items(); }
elseif ($do[0] == "edititem") { edititem($id); }
elseif ($do[0] == "drops") { drops(); }
elseif ($do[0] == "editdrop") { editdrop($id); }
elseif ($do[0] == "spells") { spells(); }
elseif ($do[0] == "editspell") { editspell($id); }
elseif ($do[0] == "suprimentos") { suprimentos(); }
elseif ($do[0] == "towns") { towns(); }
elseif ($do[0] == "edittown") { edittown($id); }
elseif ($do[0] == "terrenos") { terrenos(); }
elseif ($do[0] == "editterreno") { editterreno($id); }
elseif ($do[0] == "mapajogo") { admindisplay(mapajogohtml("admin"), t("Mapa do jogo")); }
elseif ($do[0] == "levels") { levels(); }
elseif ($do[0] == "classes") { classes(); }
elseif ($do[0] == "treinos") { treinosadmin(); }
elseif ($do[0] == "editlevel") { editlevel(); }
else { donothing(); }


/* ------------------------------------------------------------------------
   Funções auxiliares do painel.
   ------------------------------------------------------------------------ */

function admacaoperigosa($rota, $id) { // Descrição da ação se ela for perigosa (pede a senha), ou "" se não for.

    global $controlrow;
    $post = (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST");
    $nomes = array("editmonster" => "Apagar o inimigo %s", "edititem" => "Apagar o item %s", "editdrop" => "Apagar o drop %s", "editspell" => "Apagar o jutsu %s", "edittown" => "Apagar a vila %s", "editterreno" => "Apagar o terreno %s", "editreceita" => "Apagar a receita %s");
    if ($post && isset($nomes[$rota]) && isset($_POST["apagar"])) { return tf($nomes[$rota], $id); }
    if ($rota == "edituser" && $post) {
        $acao = pegartexto($_POST, "acao");
        if ($acao == "apagar") { return tf("Apagar a conta do jogador %s", $id); }
        if ($acao == "banir") { return tf("Banir o jogador %s", $id); }
        if (isset($_POST["submit"])) {
            $r = mysqli_fetch_array(doquery("SELECT authlevel,acesso FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "users"));
            if ($r && (pegarint($_POST, "authlevel") != intval($r["authlevel"]) || pegarint($_POST, "acesso") != intval($r["acesso"]))) { return tf("Mudar o cargo/nível de acesso do jogador %s (promover ou rebaixar administrador/GM)", $id); }
            if (pegartexto($_POST, "novasenha") != "") { return tf("Trocar a senha do jogador %s", $id); }
        }
    }
    if ($rota == "caveiras" && $post && pegartexto($_POST, "acao") == "banir") { return tf("Banir o jogador %s", pegarint($_POST, "alvo")); }
    if ($rota == "manutencao" && $post && isset($_POST["acao"])) { return tf("Manutenção em massa: %s", pegartexto($_POST, "acao")); }
    if ($rota == "mensagemglobal" && $post && isset($_POST["submit"])) { return t("Enviar uma mensagem para todos os jogadores"); }
    if ($rota == "moderacao" && in_array(pegartexto($_GET, "acao"), array("limparbabble", "limparchatmap", "limparlogin"), true)) { return tf("Moderação em massa: %s", pegartexto($_GET, "acao")); }
    if ($rota == "main" && $post && isset($_POST["submit"])) {
        // Só as configurações sensíveis (PvP no mundo e e-mails) pedem a senha.
        $sensiveis = array("pvpmundo","pvpdiflevel","pvpnivelseguro","pvpesperaalvo","pvpesperaataque","pvpchancedrop","pvpondedrop","pvpprotegervariantes","pvpmaxitens","pvpperdaryou","emailativo","verifyemail","pvpbrancahoras","pvpbrancachance","pvpvermelhamortes","pvpvermelhadias","pvpvermelhabanco","pvpbanirdias","pvpbanirmetade","pvpcaveiraminutos","pvpbanirniveis","pvpvermelhaperdamin","pvpvermelhaperdamax");
        foreach ($sensiveis as $c) { if (isset($_POST[$c]) && (string)pegarint($_POST, $c) !== (string)intval($controlrow[$c])) { return t("Mudar as configurações de PvP no mundo ou de e-mail"); } }
    }
    return "";

}

function admmsg($texto, $tipo = "ok") { // Caixa de mensagem (ok / erro / aviso).

    return "<div class=\"$tipo\">$texto</div>";

}

function admsel($valor, $opcao) { // Devolve selected="selected" quando o valor é a opção atual.

    return ((string)$valor === (string)$opcao) ? "selected=\"selected\"" : "";

}

function admopcoes($opcoes, $atual) { // Monta as <option> de um <select> a partir de um array valor => rótulo.

    $html = "";
    foreach ($opcoes as $valor => $rotulo) { $html .= "<option value=\"$valor\" ".admsel($atual, $valor).">$rotulo</option>"; }
    return $html;

}

function admnumero($campo, $rotulo, &$erros, $minimo = null, $maximo = null) { // Lê e valida um número do formulário.

    $valor = isset($_POST[$campo]) ? trim($_POST[$campo]) : "";
    if ($valor === "" || !is_numeric($valor)) { $erros .= tf("%s deve ser um número.", $rotulo)."<br />"; return 0; }
    $valor = $valor + 0;
    if ($minimo !== null && $valor < $minimo) { $erros .= tf("%s deve ser no mínimo %s.", $rotulo, $minimo)."<br />"; }
    if ($maximo !== null && $valor > $maximo) { $erros .= tf("%s deve ser no máximo %s.", $rotulo, $maximo)."<br />"; }
    return $valor;

}

function admtexto($campo, $rotulo, &$erros, $obrigatorio = true, $maximo = 255) { // Lê e valida um texto do formulário (já vem escapado pelo lib.php).

    $valor = isset($_POST[$campo]) && !is_array($_POST[$campo]) ? trim($_POST[$campo]) : "";
    if ($obrigatorio && $valor == "") { $erros .= tf("%s é obrigatório.", $rotulo)."<br />"; }
    if (mb_strlen(stripslashes($valor), 'UTF-8') > $maximo) { $erros .= tf("%s pode ter no máximo %s caracteres.", $rotulo, $maximo)."<br />"; }
    return $valor;

}

function admlinkcsrf($url) { // Acrescenta o token anti-CSRF num link de ação (ex.: apagar).

    return $url . "&amp;csrf=" . csrf_token();

}

function admgetcsrf() { // Confere o token de ações feitas por link (GET).

    if (!csrf_confere()) { admindisplay(admmsg(t("Token de segurança inválido. Volte e tente novamente."), "erro"), t("Erro")); }

}

function admfuncoesduelo() { // Carrega as funções do duelo PvP do users.php (sem executar as rotas dele).

    global $userrow, $controlrow, $link;
    if (!defined('USERS_SOMENTE_FUNCOES')) { define('USERS_SOMENTE_FUNCOES', true); }
    $eu = $userrow;
    include_once('users.php');
    $userrow = $eu;

}

function admelementos() { // Elementos existentes no jogo.

    return array("neutro"=>t("Neutro"), "fogo"=>t("Fogo"), "agua"=>t("Água"), "terra"=>t("Terra"), "vento"=>t("Vento"), "raio"=>t("Relâmpago"));

}

function admcodigoespecial($codigo, $rotulo, &$erros, $permitirX = true) { // Valida códigos "atributo,valor" usados em itens e drops.
    // O atributo vira NOME DE COLUNA no UPDATE dos jogadores, por isso só aceitamos esta lista.
    $permitidos = array("maxhp","maxmp","maxtp","maxnp","maxep","goldbonus","expbonus","strength","dexterity","attackpower","defensepower","sorte","agilidade","determinacao","precisao","inteligencia","droprate","critico","esquiva","regenhp","regenmp","regentp","regennp","regenep","economiachakra");
    $codigo = str_replace(" ", "", $codigo);
    if ($codigo == "" || $codigo == "X") {
        if ($permitirX) { return "X"; }
        $erros .= tf("%s é obrigatório.", $rotulo)."<br />"; return "X";
    }
    $partes = explode(",", $codigo);
    if (count($partes) != 2 || !in_array($partes[0], $permitidos) || !preg_match("/^-?[0-9]+$/", $partes[1])) {
        $erros .= tf("%s deve estar no formato <b>atributo,valor</b> (ex.: maxhp,50) usando um dos atributos: %s.", $rotulo, implode(", ", $permitidos))."<br />";
    }
    return $codigo;

}

function admbonusrepetidos($codigos, &$erros) { // Não deixa o mesmo atributo em dois campos especiais do mesmo item/drop.

    $vistos = array();
    foreach ($codigos as $codigo) {
        if ($codigo == "X" || $codigo == "") { continue; }
        $atributo = explode(",", $codigo)[0];
        if (isset($vistos[$atributo])) { $erros .= tf("O atributo %s aparece mais de uma vez; some os valores num campo só.", $atributo)."<br />"; }
        $vistos[$atributo] = true;
    }

}

function admdropsdoinimigo($idmonstro) { // Linhas da tabela monster_drops de um inimigo: array de (drop_id, nome, chance, chance de variante rara).

    global $dbsettings;
    $lista = array();
    $tabeladrops = $dbsettings["prefix"]."_drops"; // a tabela de drops entra no JOIN com o mesmo prefixo.
    $query = doquery("SELECT md.drop_id, md.chance_percent, md.chance_variante_percent, d.name FROM {{table}} AS md LEFT JOIN `$tabeladrops` AS d ON d.id=md.drop_id WHERE md.monster_id='".intval($idmonstro)."' ORDER BY md.id", "monster_drops");
    while ($row = mysqli_fetch_array($query)) { $lista[] = array(intval($row["drop_id"]), ($row["name"] === null ? "#".intval($row["drop_id"]) : $row["name"]), floatval($row["chance_percent"]), floatval($row["chance_variante_percent"])); }
    return $lista;

}

function admdropsdosinimigos($onde) { // admdropsdoinimigo() de vários inimigos numa query só (lista de inimigos): id do inimigo => lista.

    global $dbsettings;
    $mapa = array();
    $tabeladrops = $dbsettings["prefix"]."_drops";
    $tabelamonstros = $dbsettings["prefix"]."_monsters";
    $query = doquery("SELECT md.monster_id, md.drop_id, md.chance_percent, md.chance_variante_percent, d.name FROM {{table}} AS md LEFT JOIN `$tabeladrops` AS d ON d.id=md.drop_id WHERE md.monster_id IN (SELECT id FROM `$tabelamonstros` WHERE $onde) ORDER BY md.monster_id, md.id", "monster_drops");
    while ($row = mysqli_fetch_array($query)) { $mapa[intval($row["monster_id"])][] = array(intval($row["drop_id"]), ($row["name"] === null ? "#".intval($row["drop_id"]) : $row["name"]), floatval($row["chance_percent"]), floatval($row["chance_variante_percent"])); }
    return $mapa;

}

function admtextodrops($lista) { // "Nome (2%), Nome (1,5%)" para listas e páginas de ajuda.

    $partes = array();
    foreach ($lista as $d) { $partes[] = tf("%s (%s%%)", $d[1], rtrim(rtrim(number_format($d[2], 2, ",", ""), "0"), ",")); }
    return implode(", ", $partes);

}

function admimagemmonstro($id, $tam) { // Imagem principal do inimigo (retrato da carta da luta) para as telas do admin; sem retrato, a carta antiga ou a padrão.
    $ret = "layoutnovo/monstros/retratos/".intval($id).".jpg";
    if ($id > 0 && file_exists($ret)) { return "<img src=\"".$ret."?".filemtime($ret)."\" style=\"width:".intval($tam)."px;height:".intval($tam)."px;object-fit:cover;object-position:center top;border:1px solid #452202;border-radius:4px\" alt=\"\" />"; }
    $velha = "layoutnovo/monstros/".intval($id).".jpg";
    if ($id > 0 && file_exists($velha)) { return "<img src=\"".$velha."\" style=\"width:".intval($tam)."px;height:".intval($tam)."px;object-fit:cover\" alt=\"\" />"; }
    return "<span class=\"highlight small\">".t("sem imagem")."</span>";
}

function admupload($campo, $destino, $largura, $altura, $formato) { // Recebe uma imagem enviada pelo formulário e grava no tamanho certo.
    // Devolve "" se deu certo (ou se nenhum arquivo foi enviado) e a mensagem de erro caso contrário.

    if (!isset($_FILES[$campo]) || $_FILES[$campo]["error"] == UPLOAD_ERR_NO_FILE) { return ""; }
    if ($_FILES[$campo]["error"] != UPLOAD_ERR_OK) { return tf("Falha no envio da imagem (código %s).", intval($_FILES[$campo]["error"])); }
    if ($_FILES[$campo]["size"] > 3 * 1024 * 1024) { return t("A imagem deve ter no máximo 3 MB."); }
    $info = @getimagesize($_FILES[$campo]["tmp_name"]);
    if ($info === false || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF))) { return t("O arquivo precisa ser uma imagem JPG, PNG ou GIF."); }
    $pasta = dirname($destino);
    if (!is_writable($pasta)) { return tf("A pasta %s não tem permissão de escrita para o servidor web.", $pasta); }

    if (function_exists("imagecreatefromstring")) {
        // Com a extensão GD: redimensiona preenchendo o quadro (corta o excesso) e salva no formato do jogo.
        $original = @imagecreatefromstring(file_get_contents($_FILES[$campo]["tmp_name"]));
        if (!$original) { return t("Não foi possível ler a imagem."); }
        $lo = imagesx($original); $ao = imagesy($original);
        $escala = max($largura / $lo, $altura / $ao);
        $lc = (int)ceil($largura / $escala); $ac = (int)ceil($altura / $escala);
        $nova = imagecreatetruecolor($largura, $altura);
        if ($formato == "gif") { $fundo = imagecolorallocate($nova, 255, 241, 199); imagefill($nova, 0, 0, $fundo); }
        imagecopyresampled($nova, $original, 0, 0, (int)(($lo - $lc) / 2), (int)(($ao - $ac) / 2), $largura, $altura, $lc, $ac);
        if ($formato == "jpg") { $ok = imagejpeg($nova, $destino, 90); }
        elseif ($formato == "png") { $ok = imagepng($nova, $destino); }
        else { $ok = imagegif($nova, $destino); }
        imagedestroy($nova); imagedestroy($original);
        return $ok ? "" : tf("Não foi possível gravar %s.", $destino);
    }

    // Sem GD: aceita apenas a imagem já no tamanho e formato exatos.
    $tipos = array("jpg"=>IMAGETYPE_JPEG, "png"=>IMAGETYPE_PNG, "gif"=>IMAGETYPE_GIF);
    if ($info[0] != $largura || $info[1] != $altura || $info[2] != $tipos[$formato]) {
        return tf("Envie um arquivo %s de exatamente %sx%s pixels (a extensão GD do PHP não está instalada para redimensionar).", strtoupper($formato), $largura, $altura);
    }
    return move_uploaded_file($_FILES[$campo]["tmp_name"], $destino) ? "" : tf("Não foi possível gravar %s.", $destino);

}

function admversaocinza($origem, $destino) { // Cria a versão "apagada" (cinza) do ícone de uma vila desconhecida.

    if (!function_exists("imagecreatefromgif") || !file_exists($origem)) { return; }
    $img = @imagecreatefromgif($origem);
    if (!$img) { return; }
    imagefilter($img, IMG_FILTER_GRAYSCALE);
    imagefilter($img, IMG_FILTER_BRIGHTNESS, -40);
    imagegif($img, $destino);
    imagedestroy($img);

}

function admlerpilhavenda(&$erros) { // Lê do formulário de item/drop: "não vendável", "empilhável" e o máximo da pilha. Devolve o trecho do SET.

    $naovendavel = (pegarint($_POST, "naoVendavel") == 1) ? 1 : 0;
    $empilhavel = (pegarint($_POST, "empilhavel") == 1) ? 1 : 0;
    $maxpilha = pegarint($_POST, "maxpilha", 20);
    if ($maxpilha < 2 || $maxpilha > 999) { $erros .= t("O máximo da pilha deve ficar entre 2 e 999.")."<br />"; $maxpilha = 20; }
    // Durabilidade máxima (vitórias até quebrar; 0 = infinita). Vale para os itens que entrarem no jogo daqui em diante.
    $durabilidade = pegarint($_POST, "durabilidade", 0);
    if ($durabilidade < 0 || $durabilidade > 65535) { $erros .= t("A durabilidade deve ficar entre 0 e 65535.")."<br />"; $durabilidade = 0; }
    return ", naoVendavel='$naovendavel', empilhavel='$empilhavel', maxpilha='$maxpilha', durabilidade='$durabilidade'";

}

function admcampospilhavenda($r) { // Linhas do formulário de item/drop com as caixas "Não pode ser vendido em loja" e "Empilhável" + máximo.

    $nv = !empty($r["naoVendavel"]) ? " checked=\"checked\"" : "";
    $em = !empty($r["empilhavel"]) ? " checked=\"checked\"" : "";
    $max = isset($r["maxpilha"]) ? intval($r["maxpilha"]) : 20;
    $dur = isset($r["durabilidade"]) ? intval($r["durabilidade"]) : pegarint($_POST, "durabilidade", 0);
    return "<tr><td>".t("Durabilidade:")."</td><td><input type=\"number\" name=\"durabilidade\" min=\"0\" max=\"65535\" value=\"$dur\" style=\"width:70px\" /> <span class=\"small\">".t("Vitórias até quebrar (0 = infinita). Só os itens novos (loja, drop, alquimia, missão) saem com esse valor; os que os jogadores já têm não mudam.")."</span></td></tr>"
        ."<tr><td>".t("Venda:")."</td><td><label><input type=\"checkbox\" name=\"naoVendavel\" value=\"1\"$nv /> ".t("Não pode ser vendido em loja")."</label> <span class=\"small\">".t("Lojas e compradores de pergaminhos recusam o item; ao comprar outro equipamento, ele vai para a mochila em vez de ser vendido.")."</span></td></tr>"
        ."<tr><td>".t("Pilha:")."</td><td><label><input type=\"checkbox\" name=\"empilhavel\" value=\"1\"$em /> ".t("Empilhável")."</label> ".t("máximo")." <input type=\"number\" name=\"maxpilha\" min=\"2\" max=\"999\" value=\"$max\" style=\"width:60px\" /> <span class=\"small\">".t("Unidades iguais ocupam um só espaço da mochila (a quantidade aparece no ícone).")."</span></td></tr>";

}

function admcontar($tabela, $onde = "1") { // Conta registros de uma tabela.

    $row = mysqli_fetch_array(doquery("SELECT COUNT(*) AS total FROM {{table}} WHERE $onde", $tabela));
    return intval($row["total"]);

}


/* ------------------------------------------------------------------------
   Início: resumo do jogo.
   ------------------------------------------------------------------------ */

function donothing() {

    global $controlrow, $dbsettings;

    $page = "<h1>".t("Bem-vindo ao Painel do Administrador")."</h1>";

    // Avisos importantes.
    if ($controlrow["gameopen"] == 0) { $page .= admmsg(t("O jogo está <b>FECHADO</b> para os jogadores. Abra em <a href=\"admin.php?do=main\">Configurações gerais</a>."), "aviso"); }
    if ($controlrow["allowregister"] == 0) { $page .= admmsg(t("Novos cadastros estão desativados."), "aviso"); }
    if (!empty($dbsettings["debug"])) { $page .= admmsg(t("O modo <b>debug</b> está ligado no config.php: erros detalhados aparecem para os jogadores. Desligue em produção."), "aviso"); }
    if (is_dir("install")) { $page .= admmsg(t("A pasta <b>install/</b> ainda está dentro do jogo. Apague-a do servidor."), "aviso"); }

    $semcarta = array();
    $query = doquery("SELECT id FROM {{table}} ORDER BY id", "monsters");
    while ($row = mysqli_fetch_array($query)) { if (!file_exists("layoutnovo/monstros/retratos/".$row["id"].".jpg")) { $semcarta[] = $row["id"]; } }
    if (count($semcarta) > 0) { $page .= admmsg(tf("Inimigos sem imagem própria: %s. Envie as imagens em <a href=\"admin.php?do=monsters\">Inimigos e cartas</a>.", implode(", ", $semcarta)), "aviso"); }

    $nivelmaximo = mysqli_fetch_array(doquery("SELECT MAX(level) AS nivel FROM {{table}}", "monsters"));
    if (floor($controlrow["gamesize"] / 5) > intval($nivelmaximo["nivel"])) {
        $page .= admmsg(tf("O mapa tem %s casas para cada lado; inimigos sobem 1 nível a cada 5 casas, então o jogo precisa de inimigos até o nível %s (o mais forte hoje é nível %s). Nas áreas mais distantes aparecerão os inimigos de nível mais alto disponíveis.", $controlrow["gamesize"], floor($controlrow["gamesize"] / 5), intval($nivelmaximo["nivel"])), "aviso");
    }

    // Números do jogo.
    $page .= "<h2>".t("Jogadores")."</h2><div class=\"caixas\">";
    $page .= "<div class=\"caixa\"><b>".admcontar("users")."</b>".t("cadastrados")."</div>";
    $page .= "<div class=\"caixa\"><b>".admcontar("users", "UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."'")."</b>".t("online (10 min)")."</div>";
    $page .= "<div class=\"caixa\"><b>".admcontar("users", "regdate >= (NOW() - INTERVAL 7 DAY)")."</b>".t("novos (7 dias)")."</div>";
    $page .= "<div class=\"caixa\"><b>".admcontar("users", "authlevel='2'")."</b>".t("banidos")."</div>";
    $page .= "<div class=\"caixa\"><b>".admcontar("users", "currentaction='Fighting'")."</b>".t("em batalha")."</div>";
    $page .= "</div><h2>".t("Conteúdo")."</h2><div class=\"caixas\">";
    $page .= "<div class=\"caixa\"><b>".admcontar("monsters")."</b><a href=\"admin.php?do=monsters\">".t("inimigos")."</a></div>";
    $page .= "<div class=\"caixa\"><b>".admcontar("items")."</b><a href=\"admin.php?do=items\">".t("itens de loja")."</a></div>";
    $page .= "<div class=\"caixa\"><b>".admcontar("drops")."</b><a href=\"admin.php?do=drops\">".t("drops")."</a></div>";
    $page .= "<div class=\"caixa\"><b>".admcontar("spells")."</b><a href=\"admin.php?do=spells\">".t("jutsus")."</a></div>";
    $page .= "<div class=\"caixa\"><b>".admcontar("towns")."</b><a href=\"admin.php?do=towns\">".t("vilas")."</a></div>";
    $page .= "</div>";

    // Jogadores mais fortes e últimos cadastrados.
    $page .= "<h2>".t("Top 10 (nível / experiência)")."</h2><table class=\"lista\"><tr><th>".t("Personagem")."</th><th>".t("Nível")."</th><th>".t("Exp.")."</th><th>".t("Ryou")."</th><th>".t("Vila")."</th></tr>";
    $query = doquery("SELECT id,charname,level,experience,gold,alinhamento FROM {{table}} ORDER BY level DESC, experience DESC LIMIT 10", "users");
    while ($row = mysqli_fetch_array($query)) {
        $page .= "<tr><td><a href=\"admin.php?do=edituser:".$row["id"]."\">".$row["charname"]."</a></td><td>".$row["level"]."</td><td>".number_format($row["experience"])."</td><td>".number_format($row["gold"])."</td><td>".ucfirst($row["alinhamento"])."</td></tr>";
    }
    $page .= "</table>";

    $page .= "<h2>".t("Últimos cadastros")."</h2><table class=\"lista\"><tr><th>".t("Conta")."</th><th>".t("Personagem")."</th><th>".t("E-mail")."</th><th>".t("Cadastro")."</th></tr>";
    $query = doquery("SELECT id,username,charname,email,regdate FROM {{table}} ORDER BY id DESC LIMIT 10", "users");
    while ($row = mysqli_fetch_array($query)) {
        $page .= "<tr><td>".$row["username"]."</td><td><a href=\"admin.php?do=edituser:".$row["id"]."\">".$row["charname"]."</a></td><td>".$row["email"]."</td><td>".padraodate($row["regdate"])."</td></tr>";
    }
    $page .= "</table>";

    admindisplay($page, t("Resumo"));

}


/* ------------------------------------------------------------------------
   Configurações gerais (tabela control).
   ------------------------------------------------------------------------ */

function main() {

    global $controlrow, $idiomasdisponiveis;
    $mensagem = "";

    if (isset($_POST["submit"])) {
        $erros = "";
        $gamename = admtexto("gamename", t("Nome do jogo"), $erros, true, 50);
        $gameurl = admtexto("gameurl", t("Endereço do jogo"), $erros, true, 200);
        $adminemail = admtexto("adminemail", t("E-mail do administrador"), $erros, true, 100);
        $closedmsg = admtexto("closedmsg", t("Mensagem de jogo fechado"), $erros, true, 500);
        $loginmsg = admtexto("loginmsg", t("Aviso da página de login"), $erros, false, 1000);
        $forumaddress = admtexto("forumaddress", t("Endereço do fórum externo"), $erros, false, 200);
        $class1name = admtexto("class1name", t("Especialização 1"), $erros, true, 50);
        $class2name = admtexto("class2name", t("Especialização 2"), $erros, true, 50);
        $class3name = admtexto("class3name", t("Especialização 3"), $erros, true, 50);
        $diff1name = admtexto("diff1name", t("Dificuldade 1"), $erros, true, 50);
        $diff2name = admtexto("diff2name", t("Dificuldade 2"), $erros, true, 50);
        $diff3name = admtexto("diff3name", t("Dificuldade 3"), $erros, true, 50);
        $gamesize = admnumero("gamesize", t("Tamanho do mapa"), $erros, 5, 30000);
        $diff1mod = admnumero("diff1mod", t("Multiplicador da dificuldade 1"), $erros, 0.1, 10);
        $diff2mod = admnumero("diff2mod", t("Multiplicador da dificuldade 2"), $erros, 0.1, 10);
        $diff3mod = admnumero("diff3mod", t("Multiplicador da dificuldade 3"), $erros, 0.1, 10);
        $startgold = admnumero("startgold", t("Ryou inicial"), $erros, 0, 16777215);
        $chancetofight = admnumero("chancetofight", t("Chance de batalha"), $erros, 0, 100);
        $vendamochila = admnumero("vendamochila", t("Multiplicador de venda da mochila"), $erros, 0, 1000); // % do preço base pago pelas lojas (towns.php :: precovendaitem()).
        $pilhaconsumiveis = admnumero("pilhaconsumiveis", t("Pilha dos consumíveis da loja"), $erros, 1, 999); // máximo da pilha do Pergaminho de Retorno e do Incenso Repelente (1 = não empilha).
        // Pontos de viagem e de elemento (TP/EP): custos dos pergaminhos de viagem, taxa do "Viajar Para", EP dos jutsus e ganhos.
        $cfgpontos = array();
        foreach (array("tpretorno"=>array("TP mínimo do Pergaminho de Retorno", 0, 1000), "tpretornopct"=>array("% do TP da viagem até a vila natal (Retorno)", 0, 255),
            "tpteleporte"=>array("TP do Pergaminho de Teleporte Aleatório", 0, 1000), "fugapenalidade"=>array("% do Ryou perdido ao fugir de uma luta", 0, 100), "morteexpbase"=>array("Morte: experiência base (%)", 0, 100), "viagemryou"=>array("Ryou por TP no Viajar Para", 0, 1000),
            "viagemtpbase"=>array("Viajar Para: TP fixo", 0, 1000), "viagemtpdist"=>array("Viajar Para: TP a cada 100 casas", 0, 1000),
            "viagemnataltppct"=>array("Viajar Para (vila natal): % do TP do Pergaminho de Retorno", 0, 5000), "viagemnatalryoupct"=>array("Viajar Para (vila natal): % do preço do Pergaminho de Retorno", 0, 10000),
            "viagemoutraryou"=>array("Viajar Para (outras vilas): taxa fixa em Ryou", 0, 1000000), "viagemselos"=>array("Viajar Para (outras vilas): Selos de Viagem gastos", 0, 10),
            "viagemseloitem"=>array("Id do drop Selo de Viagem", 1, 16777215),
            "epcusto"=>array("EP gasto por jutsu com elemento", 0, 100), "epbonus"=>array("Bônus de dano (%) do jutsu com elemento pago com EP", 0, 200),
            "elemento_bonus"=>array("Bônus (%) nos jutsus do elemento principal", 0, 500), "elemento_bonus2"=>array("Bônus (%) nos jutsus do elemento secundário", 0, 500),
            "elemento_resiste"=>array("Dano (%) recebido de um jutsu do mesmo elemento", 0, 100), "elemento_peso2"=>array("Peso (%) do elemento secundário do alvo", 0, 100),
            "elemento_nivel"=>array("Nível para escolher os elementos", 1, 1000),
            "epnivel"=>array("+1 EP máximo a cada N níveis", 0, 100), "tpdescanso"=>array("TP por intervalo de descanso na cidade", 0, 100),
            "epdescanso"=>array("EP por intervalo de descanso na cidade", 0, 100), "trocavila_dias"=>array("Dias de espera entre trocas de vila natal", 0, 3650),
            "trocadif_dias"=>array("Dias de espera entre trocas de dificuldade", 0, 3650), "historicomax"=>array("Mensagens no histórico do jogador", 5, 30), "loginip_max"=>array("Contas logadas ao mesmo tempo por IP", 0, 1000), "convidado_ativo"=>array("Jogar sem conta (convidado) ativo", 0, 1), "convidado_horas"=>array("Horas até o personagem de convidado sumir", 1, 720), "convidado_porip"=>array("Convidados novos por IP por hora", 0, 1000), "durabilidade_ativa"=>array("Durabilidade ativa", 0, 1), "reparo_pct"=>array("Preço do reparo (% do valor do item)", 0, 1000), "limitepocoesluta"=>array("Usos de cada tipo de poção por luta (0 = sem limite)", 0, 99), "lutalog_linhas"=>array("Ações no registro da luta", 1, 50),
            "gm_teleporte"=>array("GM pode teleportar jogadores", 0, 1), "gm_mapa"=>array("GM pode ver o Mapa do jogo", 0, 1),
            "roubo_ativo"=>array("Roubo de itens ativo", 0, 1), "roubo_chance_mochila"=>array("Roubo: chance base por turno (mochila, %)", 0, 100), "roubo_chance_equipado"=>array("Roubo: chance base por turno (equipado, %)", 0, 100), "roubo_ryou_chance"=>array("Roubo de Ryou: chance base por turno (%)", 0, 100), "roubo_ryou_min"=>array("Roubo de Ryou: mínimo (% do Ryou)", 1, 100), "roubo_ryou_max"=>array("Roubo de Ryou: máximo (% do Ryou)", 1, 100), "roubo_ryou_teto"=>array("Roubo de Ryou: teto por nível do inimigo", 0, 100000), "status_monstros_ativo"=>array("Status causados por inimigos ativos", 0, 1), "status_monstros_mult"=>array("Status de inimigos: multiplicador da chance (%)", 0, 1000),
            "raridade_elite_pct"=>array("Elite: bônus de HP/ataque/defesa/XP/Ryou (%)", 0, 500), "raridade_raro_pct"=>array("Raro: bônus de HP/ataque/defesa/XP/Ryou (%)", 0, 500), "raridade_drop_elite"=>array("Elite: chance de drop (% da normal)", 0, 1000), "raridade_drop_raro"=>array("Raro: chance de drop (% da normal)", 0, 1000), "raridade_drop_teto"=>array("Teto da chance de drop com bônus de raridade (%)", 1, 100),
            "cla_custo"=>array("Custo para criar um clã (Ryou)", 0, 16777215), "cla_maxmembros"=>array("Máximo de membros por clã", 1, 500), "cla_meta"=>array("Missão semanal do clã: inimigos", 1, 1000000), "cla_premio"=>array("Missão semanal do clã: recompensa (Ryou)", 0, 16777215),
            "temporada_dias"=>array("Duração da temporada de duelo (dias)", 1, 365), "temporada_vitoria"=>array("Temporada: pontos por vitória", 0, 1000), "temporada_derrota"=>array("Temporada: pontos perdidos por derrota", 0, 1000)) as $campo => $cfg) {
            $cfgpontos[$campo] = intval(admnumero($campo, t($cfg[0]), $erros, $cfg[1], $cfg[2]));
        }
        // Vila inicial (control.vilainicial): vila natal dos ninjas novos; só vila natal comum (vilanatal = 1, com alinhamento, não secreta).
        $vilainicial = pegarint($_POST, "vilainicial");
        $qvi = doquery("SELECT id FROM {{table}} WHERE id='".intval($vilainicial)."' AND vilanatal='1' AND alinhamento<>'' AND secreta='0' LIMIT 1", "towns");
        if (mysqli_num_rows($qvi) != 1) { $erros .= t("A vila inicial precisa ser uma vila natal com alinhamento e não secreta.")."<br />"; }
        else { $cfgpontos["vilainicial"] = intval($vilainicial); }
        $compradorremoto = admnumero("compradorremoto", t("Preço do comprador de pergaminhos distante"), $erros, 0, 1000); // % do preço da vila pago pelo NPC comprador_pergaminhos.
        $compradorvila = (pegarint($_POST, "compradorvila") == 1) ? 1 : 0;
        $expmultiplier = admnumero("expmultiplier", t("Multiplicador de experiência"), $erros, 0.1, 10);
        $goldmultiplier = admnumero("goldmultiplier", t("Multiplicador de Ryou"), $erros, 0.1, 10);
        $pvplevel = admnumero("pvplevel", t("Nível mínimo para duelos/doações"), $erros, 1, 1000);
        if (($gamesize % 5) != 0) { $erros .= t("O tamanho do mapa deve ser divisível por 5.")."<br />"; }
        if (!preg_match("#^https?://#i", html_entity_decode($gameurl))) { $erros .= t("O endereço do jogo deve começar com http:// ou https://.")."<br />"; }
        if (!is_email($adminemail)) { $erros .= t("O e-mail do administrador não é válido.")."<br />"; }
        $forumtype = pegarint($_POST, "forumtype"); if ($forumtype < 0 || $forumtype > 2) { $forumtype = 0; }
        if ($forumtype == 2 && $forumaddress == "") { $erros .= t("Informe o endereço do fórum externo.")."<br />"; }
        // Idioma padrão (para quem ainda não escolheu): só aceita os idiomas existentes.
        $idiomapadrao = pegartexto($_POST, "idiomapadrao", "pt");
        if (!isset($idiomasdisponiveis[$idiomapadrao])) { $erros .= t("Escolha um idioma padrão válido.")."<br />"; $idiomapadrao = "pt"; }
        // Idiomas ligados: os marcados mais o padrão (que não pode ficar desligado).
        $idiomasativos = array();
        foreach ($idiomasdisponiveis as $codigo => $nome) { if ($codigo == $idiomapadrao || (isset($_POST["idiomaativo"]) && is_array($_POST["idiomaativo"]) && in_array($codigo, $_POST["idiomaativo"], true))) { $idiomasativos[] = $codigo; } }
        $idiomasativos = implode(",", $idiomasativos);
        // Atualização dinâmica (AJAX): 0 = recarrega a página inteira (clássico), 1 = troca só as regiões da página.
        $atualizacaodinamica = (pegarint($_POST, "atualizacaodinamica") == 1) ? 1 : 0;
        $neblinaativa = (pegarint($_POST, "neblinaativa") == 1) ? 1 : 0; // neblina de guerra do mapa-múndi (funcoesneblina.php).
        // E-mails do jogo (control.emailativo): 0 = sem e-mail (conta liberada na hora, senha perdida pela frase secreta ou pela equipe).
        $emailativo = (pegarint($_POST, "emailativo") == 1) ? 1 : 0;
        // PvP no mundo (pvp.php): desligado por padrão. Números conferidos dentro de faixas.
        $pvpmundo = (pegarint($_POST, "pvpmundo") == 1) ? 1 : 0;
        $pvpdiflevel = admnumero("pvpdiflevel", t("PvP: diferença máxima de nível"), $erros, 0, 1000);
        $pvpnivelseguro = admnumero("pvpnivelseguro", t("PvP: nível seguro"), $erros, 0, 1000);
        $pvpesperaalvo = admnumero("pvpesperaalvo", t("PvP: espera para atacar o mesmo jogador"), $erros, 0, 10000);
        $pvpesperaataque = admnumero("pvpesperaataque", t("PvP: espera entre ataques"), $erros, 0, 10000);
        $pvpchancedrop = admnumero("pvpchancedrop", t("PvP: chance de perder item"), $erros, 0, 100);
        $pvpondedrop = (pegarint($_POST, "pvpondedrop") == 1) ? 1 : 0;
        $pvpprotegervariantes = (pegarint($_POST, "pvpprotegervariantes") == 1) ? 1 : 0;
        $pvpmaxitens = admnumero("pvpmaxitens", t("PvP: máximo de itens perdidos"), $erros, 0, 8);
        $pvpperdaryou = admnumero("pvpperdaryou", t("PvP: Ryou perdido (%)"), $erros, 0, 100);
        // Caveiras (pvp.php :: pvpcaveiras()).
        $pvpbrancahoras = admnumero("pvpbrancahoras", t("Caveira branca: duração"), $erros, 1, 10000);
        $pvpbrancachance = admnumero("pvpbrancachance", t("Caveira branca: chance extra"), $erros, 0, 100);
        $pvpvermelhamortes = admnumero("pvpvermelhamortes", t("Caveira vermelha: mortes"), $erros, 1, 100);
        $pvpvermelhadias = admnumero("pvpvermelhadias", t("Caveira vermelha: duração"), $erros, 1, 1000);
        $pvpvermelhabanco = (pegarint($_POST, "pvpvermelhabanco") == 1) ? 1 : 0;
        $pvpbanirdias = admnumero("pvpbanirdias", t("Banimento automático"), $erros, 1, 3650);
        $pvpbanirmetade = (pegarint($_POST, "pvpbanirmetade") == 1) ? 1 : 0;
        $pvpbanirniveis = admnumero("pvpbanirniveis", t("Níveis perdidos no banimento"), $erros, 0, 99);
        $pvpvermelhaperdamin = admnumero("pvpvermelhaperdamin", t("Níveis perdidos ao morrer com caveira vermelha (mínimo)"), $erros, 0, 99);
        $pvpvermelhaperdamax = admnumero("pvpvermelhaperdamax", t("Níveis perdidos ao morrer com caveira vermelha (máximo)"), $erros, 0, 99);
        if ($pvpvermelhaperdamax < $pvpvermelhaperdamin) { $erros .= t("O máximo de níveis perdidos com caveira vermelha não pode ser menor que o mínimo.")."<br />"; }
        $pvpcaveiraminutos = admnumero("pvpcaveiraminutos", t("Caveira exposta"), $erros, 0, 10000); // lib.php :: caveiraexposta()/bloqueiovila()
        // Morte para inimigos do mapa (fight.php :: penalidademorte): percentuais por nível, com tetos.
        $morteryoubase = admnumero("morteryoubase", t("Morte: Ryou base (%)"), $erros, 0, 100);
        $morteryounivel = admnumero("morteryounivel", t("Morte: Ryou por nível (%)"), $erros, 0, 100);
        $morteryoumax = admnumero("morteryoumax", t("Morte: Ryou máximo (%)"), $erros, 0, 100);
        $morteexpnivel = admnumero("morteexpnivel", t("Morte: experiência por nível (%)"), $erros, 0, 100);
        $morteexpmax = admnumero("morteexpmax", t("Morte: experiência máxima (%)"), $erros, 0, 100);
        $morteitemnivel = admnumero("morteitemnivel", t("Morte: chance de item por nível (%)"), $erros, 0, 100);
        $morteitemmax = admnumero("morteitemmax", t("Morte: chance máxima de item (%)"), $erros, 0, 100);
        $mortenivelseguro = admnumero("mortenivelseguro", t("Morte: nível protegido"), $erros, 0, 1000);
        $mortelutaabandono = admnumero("mortelutaabandono", t("Luta abandonada (minutos)"), $erros, 0, 1440);
        $mortelutachance = admnumero("mortelutachance", t("Chance de derrota ao sair da luta (%)"), $erros, 0, 100);
        // Alquimia (alquimia.php): perda na falha, ritmo da experiência, crítico diário e custo extra das infusões.
        $alquimiaperda = admnumero("alquimiaperda", t("Alquimia: perda na falha (%)"), $erros, 0, 100);
        $alquimiaexptaxa = admnumero("alquimiaexptaxa", t("Alquimia: ganho de experiência (%)"), $erros, 0, 1000);
        $alquimiacritico = admnumero("alquimiacritico", t("Alquimia: chance de crítico (%)"), $erros, 0, 100);
        $alquimiainfusaocusto = admnumero("alquimiainfusaocusto", t("Alquimia: custo das infusões"), $erros, 0, 16000000);
        // Doação e créditos (creditos.php, rodapé e janela do painel): link do Stripe (vazio = sem botão), repositório e intervalo da janela.
        $linkdoacao = admtexto("linkdoacao", t("Link de doação"), $erros, false, 255);
        $linkgithub = admtexto("linkgithub", t("Repositório do código"), $erros, false, 255);
        $doacaodias = admnumero("doacaodias", t("Intervalo da janela de doação (dias)"), $erros, 0, 3650);
        if ($linkdoacao != "" && !preg_match("#^https://#i", html_entity_decode($linkdoacao))) { $erros .= t("O link de doação deve começar com https://.")."<br />"; }
        if ($linkgithub != "" && !preg_match("#^https?://#i", html_entity_decode($linkgithub))) { $erros .= t("O endereço do repositório deve começar com http:// ou https://.")."<br />"; }
        if ($morteryoubase > $morteryoumax) { $erros .= t("O Ryou base perdido na morte não pode ser maior que o máximo.")."<br />"; }
        $simnao = array();
        foreach (array("gameopen","allowregister","verifyemail","compression","shownews","showonline","showbabble") as $campo) { $simnao[$campo] = (pegarint($_POST, $campo) == 1) ? 1 : 0; }

        if ($erros == "") {
            // Coluna nova (jogar sem conta ligado/desligado): cria sozinha se o banco ainda não tiver (sem precisar rodar SQL na hospedagem).
            if (mysqli_num_rows(doquery("SHOW COLUMNS FROM {{table}} LIKE 'convidado_ativo'", "control")) == 0) { doquery("ALTER TABLE {{table}} ADD COLUMN convidado_ativo TINYINT UNSIGNED NOT NULL DEFAULT 1", "control"); }
            $sqlpontos = array(); foreach ($cfgpontos as $campo => $valor) { $sqlpontos[] = "$campo='$valor'"; }
            doquery("UPDATE {{table}} SET ".implode(", ", $sqlpontos)." WHERE id='1' LIMIT 1", "control");
            doquery("UPDATE {{table}} SET gamename='$gamename', gamesize='$gamesize', gameopen='".$simnao["gameopen"]."', gameurl='$gameurl', adminemail='$adminemail',
                forumtype='$forumtype', forumaddress='$forumaddress', class1name='$class1name', class2name='$class2name', class3name='$class3name',
                diff1name='$diff1name', diff1mod='$diff1mod', diff2name='$diff2name', diff2mod='$diff2mod', diff3name='$diff3name', diff3mod='$diff3mod',
                compression='".$simnao["compression"]."', verifyemail='".$simnao["verifyemail"]."', shownews='".$simnao["shownews"]."', showbabble='".$simnao["showbabble"]."',
                showonline='".$simnao["showonline"]."', allowregister='".$simnao["allowregister"]."', closedmsg='$closedmsg', loginmsg='$loginmsg', startgold='$startgold',
                chancetofight='$chancetofight', vendamochila='".intval($vendamochila)."', pilhaconsumiveis='".intval($pilhaconsumiveis)."', compradorremoto='".intval($compradorremoto)."', compradorvila='$compradorvila', expmultiplier='$expmultiplier', goldmultiplier='$goldmultiplier', pvplevel='$pvplevel', idiomapadrao='$idiomapadrao', idiomasativos='$idiomasativos', idiomaporip='".((pegarint($_POST, "idiomaporip") == 1) ? 1 : 0)."', atualizacaodinamica='$atualizacaodinamica', neblinaativa='$neblinaativa', emailativo='$emailativo',
                pvpmundo='$pvpmundo', pvpdiflevel='".intval($pvpdiflevel)."', pvpnivelseguro='".intval($pvpnivelseguro)."', pvpesperaalvo='".intval($pvpesperaalvo)."', pvpesperaataque='".intval($pvpesperaataque)."',
                pvpchancedrop='".intval($pvpchancedrop)."', pvpondedrop='$pvpondedrop', pvpprotegervariantes='$pvpprotegervariantes', pvpmaxitens='".intval($pvpmaxitens)."', pvpperdaryou='".intval($pvpperdaryou)."',
                pvpbrancahoras='".intval($pvpbrancahoras)."', pvpbrancachance='".intval($pvpbrancachance)."', pvpvermelhamortes='".intval($pvpvermelhamortes)."', pvpvermelhadias='".intval($pvpvermelhadias)."', pvpvermelhabanco='$pvpvermelhabanco', pvpbanirdias='".intval($pvpbanirdias)."', pvpbanirmetade='$pvpbanirmetade', pvpbanirniveis='".intval($pvpbanirniveis)."', pvpvermelhaperdamin='".intval($pvpvermelhaperdamin)."', pvpvermelhaperdamax='".intval($pvpvermelhaperdamax)."', pvpcaveiraminutos='".intval($pvpcaveiraminutos)."',
                morteryoubase='".intval($morteryoubase)."', morteryounivel='".round((float)$morteryounivel, 2)."', morteryoumax='".intval($morteryoumax)."', morteexpnivel='".round((float)$morteexpnivel, 2)."', morteexpmax='".intval($morteexpmax)."',
                morteitemnivel='".round((float)$morteitemnivel, 2)."', morteitemmax='".intval($morteitemmax)."', mortenivelseguro='".intval($mortenivelseguro)."', mortelutaabandono='".intval($mortelutaabandono)."', mortelutachance='".intval($mortelutachance)."',
                alquimiaperda='".intval($alquimiaperda)."', alquimiaexptaxa='".intval($alquimiaexptaxa)."', alquimiacritico='".round((float)$alquimiacritico, 2)."', alquimiainfusaocusto='".intval($alquimiainfusaocusto)."' WHERE id='1' LIMIT 1", "control");
            doquery("UPDATE {{table}} SET linkdoacao='$linkdoacao', linkgithub='$linkgithub', doacaodias='".intval($doacaodias)."' WHERE id='1' LIMIT 1", "control");
            $controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
            $mensagem = admmsg(t("Configurações salvas."));
        } else {
            $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro");
        }
    }

    $c = $controlrow;
    $opvilainicial = array();
    $qvi = doquery("SELECT id, name FROM {{table}} WHERE vilanatal='1' AND alinhamento<>'' AND secreta='0' ORDER BY id", "towns");
    while ($rvi = mysqli_fetch_array($qvi)) { $opvilainicial[intval($rvi["id"])] = t($rvi["name"]); }
    $opvilainicial = admopcoes($opvilainicial, isset($c["vilainicial"]) ? intval($c["vilainicial"]) : 1);
    $c["trocadif_dias"] = isset($c["trocadif_dias"]) ? intval($c["trocadif_dias"]) : 14;
    $c["historicomax"] = isset($c["historicomax"]) ? intval($c["historicomax"]) : 12;
    $seldur1 = (!isset($c["durabilidade_ativa"]) || intval($c["durabilidade_ativa"]) == 1) ? " selected=\"selected\"" : ""; $seldur0 = ($seldur1 == "") ? " selected=\"selected\"" : "";
    $c["reparo_pct"] = isset($c["reparo_pct"]) ? intval($c["reparo_pct"]) : 30;
    $selconv1 = (!isset($c["convidado_ativo"]) || intval($c["convidado_ativo"]) == 1) ? " selected=\"selected\"" : ""; $selconv0 = ($selconv1 == "") ? " selected=\"selected\"" : "";
    $c["convidado_horas"] = isset($c["convidado_horas"]) ? intval($c["convidado_horas"]) : 24; $c["convidado_porip"] = isset($c["convidado_porip"]) ? intval($c["convidado_porip"]) : 5; // jogar sem conta (funcoesconvidado.php)
    $c["limitepocoesluta"] = isset($c["limitepocoesluta"]) ? intval($c["limitepocoesluta"]) : 1;
    $c["lutalog_linhas"] = isset($c["lutalog_linhas"]) ? intval($c["lutalog_linhas"]) : 15;
    foreach (array("gm_teleporte"=>1, "gm_mapa"=>1, "roubo_ativo"=>1, "roubo_chance_mochila"=>3, "roubo_chance_equipado"=>1, "roubo_ryou_chance"=>4, "roubo_ryou_min"=>5, "roubo_ryou_max"=>15, "roubo_ryou_teto"=>50, "status_monstros_ativo"=>1, "status_monstros_mult"=>100) as $k => $pad) { $c[$k] = isset($c[$k]) ? intval($c[$k]) : $pad; } // roubo e status de inimigos (funcoesroubo.php).
    $selgmtp1 = ($c["gm_teleporte"] == 1) ? " selected=\"selected\"" : ""; $selgmtp0 = ($selgmtp1 == "") ? " selected=\"selected\"" : "";
    $selgmmapa1 = ($c["gm_mapa"] == 1) ? " selected=\"selected\"" : ""; $selgmmapa0 = ($selgmmapa1 == "") ? " selected=\"selected\"" : "";
    $selroubo1 = ($c["roubo_ativo"] == 1) ? " selected=\"selected\"" : ""; $selroubo0 = ($selroubo1 == "") ? " selected=\"selected\"" : "";
    $selstat1 = ($c["status_monstros_ativo"] == 1) ? " selected=\"selected\"" : ""; $selstat0 = ($selstat1 == "") ? " selected=\"selected\"" : "";
    foreach (array("raridade_elite_pct"=>15, "raridade_raro_pct"=>30, "raridade_drop_elite"=>150, "raridade_drop_raro"=>200, "raridade_drop_teto"=>60) as $k => $pad) { $c[$k] = isset($c[$k]) ? intval($c[$k]) : $pad; }
    $selvila1 = (intval($c["compradorvila"]) == 1) ? " selected=\"selected\"" : ""; $selvila0 = ($selvila1 == "") ? " selected=\"selected\"" : "";
    $simnao = array(1=>t("Sim"), 0=>t("Não"));
    $op = array();
    foreach (array("gameopen","allowregister","verifyemail","compression","shownews","showonline","showbabble") as $campo) { $op[$campo] = admopcoes($simnao, $c[$campo]); }
    $opforum = admopcoes(array(0=>t("Desativado"), 1=>t("Interno (fórum do jogo)"), 2=>t("Externo (link)")), $c["forumtype"]);
    $ipligado = (!isset($c["idiomaporip"]) || intval($c["idiomaporip"]) == 1);
    $opidiomaip = "<option value=\"1\"".($ipligado ? " selected" : "").">".t("Ligado (Brasil = português, fora = inglês)")."</option><option value=\"0\"".($ipligado ? "" : " selected").">".t("Desligado (usa o idioma padrão)")."</option>";
    $ligados = explode(",", isset($c["idiomasativos"]) ? $c["idiomasativos"] : "pt,en");
    $caixasidioma = "";
    foreach ($idiomasdisponiveis as $codigo => $nome) { $caixasidioma .= "<label style=\"margin-right:12px\"><input type=\"checkbox\" name=\"idiomaativo[]\" value=\"$codigo\"".(in_array($codigo, $ligados) ? " checked" : "")."> <img src=\"images/bandeiras/$codigo.png\" width=\"16\" height=\"11\" alt=\"\"> $nome</label>"; }
    $opidioma = admopcoes($idiomasdisponiveis, isset($c["idiomapadrao"]) ? $c["idiomapadrao"] : "pt");
    $opneblina = admopcoes(array(1=>t("Ligada (cada jogador só vê as regiões por onde já andou)"), 0=>t("Desligada (o mapa inteiro aparece para todos)")), isset($c["neblinaativa"]) ? $c["neblinaativa"] : 1);
    $opdinamica = admopcoes(array(0=>t("Desligada (recarrega a página inteira, como sempre foi)"), 1=>t("Ligada (carrega só o conteúdo, sem piscar a tela)")), isset($c["atualizacaodinamica"]) ? $c["atualizacaodinamica"] : 0);
    $opemail = admopcoes(array(0=>t("Desligados (padrão): conta liberada na hora, sem e-mail"), 1=>t("Ligados: verificação e recuperação de senha por e-mail")), $c["emailativo"]);
    $oppvp = admopcoes(array(1=>t("Ligado (padrão)"), 0=>t("Desligado")), $c["pvpmundo"]);
    $opondedrop = admopcoes(array(1=>t("Mochila e itens equipados (padrão)"), 0=>t("Só a mochila")), $c["pvpondedrop"]);
    $opbanco = admopcoes(array(0=>t("Não (padrão)"), 1=>t("Sim, o banco de itens também")), $c["pvpvermelhabanco"]);
    $opbanirmetade = admopcoes(array(1=>t("Sim (padrão)"), 0=>t("Não, só o banimento")), $c["pvpbanirmetade"]);
    // Exemplos da penalidade de morte com os valores atuais (níveis 1, 5, 10, 20, 40, 80).
    include_once('fight.php');
    $exmorte = "";
    foreach (array(1, 5, 10, 20, 40, 80) as $nv) { $pm = penalidademorte($nv); $exmorte .= "<tr><td>".$nv."</td><td>".$pm["ryou"]."%</td><td>".$pm["exp"]."%</td><td>".$pm["item"]."%</td></tr>"; }
    $opvariantes = admopcoes(array(1=>t("Nunca (variantes raras ficam protegidas)"), 0=>t("Podem ser perdidas")), $c["pvpprotegervariantes"]);
    $csrf = csrf_campo();

$t = "t"; $tf = "tf";
$page = <<<END
<h1>{$t("Configurações gerais")}</h1>
$mensagem
<form action="admin.php?do=main" method="post">$csrf
<h2>{$t("Funcionamento")}</h2>
<table width="100%">
<tr><td width="230"><span class="highlight">{$t("Jogo aberto:")}</span></td><td><select name="gameopen">{$op["gameopen"]}</select><br /><span class="small">{$t("Feche o jogo durante atualizações. Só administradores conseguem jogar com o jogo fechado.")}</span></td></tr>
<tr><td>{$t("Mensagem de jogo fechado:")}</td><td><textarea name="closedmsg" rows="2" cols="70">{$c["closedmsg"]}</textarea></td></tr>
<tr><td>{$t("Novos cadastros:")}</td><td><select name="allowregister">{$op["allowregister"]}</select></td></tr>
<tr><td>{$t("E-mails do jogo:")}</td><td><select name="emailativo">$opemail</select><br /><span class="small">{$t("Ligados: mais seguro (confirmação do e-mail e link para criar senha nova), mas o servidor precisa enviar e-mails (Postfix/sendmail). Desligados: a conta já pode jogar ao ser criada; quem perder a senha responde as perguntas secretas (com conta, personagem, ano de criação, e-mail e level) ou pede ajuda a um GM. Mudar pede a sua senha.")}</span></td></tr>
<tr><td>{$t("Verificar e-mail no cadastro:")}</td><td><select name="verifyemail">{$op["verifyemail"]}</select><br /><span class="small">{$t("Só vale com os e-mails ligados. Exige que o servidor consiga enviar e-mails (função mail() do PHP / sendmail).")}</span></td></tr>
<tr><td>{$t("Aviso da página de login:")}</td><td><textarea name="loginmsg" rows="3" cols="70">{$c["loginmsg"]}</textarea><br /><span class="small">{$t("Aparece no quadro \"Informativo\" da tela de login. Deixe vazio para esconder.")}</span></td></tr>
<tr><td>{$t("Idioma padrão do jogo:")}</td><td><select name="idiomapadrao">$opidioma</select><br /><span class="small">{$t("Usado para visitantes e jogadores que ainda não escolheram um idioma.")}</span></td></tr>
<tr><td>{$t("Idioma pelo IP:")}</td><td><select name="idiomaporip">$opidiomaip</select><br /><span class="small">{$t("Vale no primeiro acesso, enquanto o jogador não escolhe um idioma no seletor; a escolha dele fica salva e sempre tem prioridade.")}</span></td></tr>
<tr><td>{$t("Idiomas ativos:")}</td><td>$caixasidioma<br /><span class="small">{$t("Só os idiomas marcados aparecem para os jogadores. O idioma padrão fica sempre ativo; com um único idioma ativo o seletor some.")}</span></td></tr>
<tr><td>{$t("Neblina do mapa-múndi:")}</td><td><select name="neblinaativa">$opneblina</select><br /><span class="small">{$t("O mapa completo é dividido em 20x20 regiões; cada uma só aparece depois que o jogador pisa nela. Admins e GMs sempre veem tudo (com um link para ver como o jogador).")}</span></td></tr>
<tr><td>{$t("Atualização dinâmica das páginas (AJAX):")}</td><td><select name="atualizacaodinamica">$opdinamica</select><br /><span class="small">{$t("Ligada: navegação mais rápida, sem a tela branca entre as páginas, e o botão voltar continua funcionando. Contras: depende de JavaScript (sem ele o jogo volta sozinho ao modo clássico), fica mais difícil de depurar e páginas novas com muito JavaScript próprio precisam ser testadas. O painel do administrador, o login e os envios de arquivo sempre recarregam a página inteira.")}</span></td></tr>
<tr><td>{$t("Compressão das páginas (gzip):")}</td><td><select name="compression">{$op["compression"]}</select></td></tr>
</table>
<h2>{$t("Identificação")}</h2>
<table width="100%">
<tr><td width="230">{$t("Nome do jogo:")}</td><td><input type="text" name="gamename" size="40" maxlength="50" value="{$c["gamename"]}" /></td></tr>
<tr><td>{$t("Endereço do jogo:")}</td><td><input type="text" name="gameurl" size="60" maxlength="200" value="{$c["gameurl"]}" /><br /><span class="small">{$t("Endereço completo da página inicial, ex.: https://www.seusite.com.br/narutorpg/index.php (usado nos e-mails de cadastro e recuperação de senha).")}</span></td></tr>
<tr><td>{$t("Link de doação:")}</td><td><input type="text" name="linkdoacao" size="60" maxlength="255" value="{$c["linkdoacao"]}" /><br /><span class="small">{$t("Link do Stripe (ou outro, https://) do botão de doação da página de créditos e da janela do painel. Vazio = sem botão.")}</span></td></tr>
<tr><td>{$t("Repositório do código:")}</td><td><input type="text" name="linkgithub" size="60" maxlength="255" value="{$c["linkgithub"]}" /></td></tr>
<tr><td>{$t("Janela de doação no painel:")}</td><td>{$t("a cada")} <input type="number" name="doacaodias" min="0" max="3650" value="{$c["doacaodias"]}" style="width:60px" /> {$t("dias (0 = toda vez que abrir o painel numa sessão nova)")}</td></tr>
<tr><td>{$t("E-mail do administrador:")}</td><td><input type="text" name="adminemail" size="40" maxlength="100" value="{$c["adminemail"]}" /><br /><span class="small">{$t("Remetente dos e-mails enviados pelo jogo.")}</span></td></tr>
</table>
<h2>{$t("Regras do jogo")}</h2>
<table width="100%">
<tr><td width="230">{$t("Tamanho do mapa:")}</td><td><input type="number" name="gamesize" min="5" max="30000" step="5" value="{$c["gamesize"]}" /><br /><span class="small">{$t("Casas para cada lado a partir do centro (0,0). Inimigos sobem 1 nível a cada 5 casas.")}</span></td></tr>
<tr><td>{$t("Ryou inicial:")}</td><td><input type="number" name="startgold" min="0" value="{$c["startgold"]}" /></td></tr>
<tr><td>{$t("Chance de batalha ao andar:")}</td><td>{$t("1 em")} <input type="number" name="chancetofight" min="0" max="100" value="{$c["chancetofight"]}" style="width:60px" /> {$t("passos")}<br /><span class="small">{$t("Padrão 4 (25%). Use 0 para desativar batalhas aleatórias.")}</span></td></tr>
<tr><td>{$t("Venda de itens da mochila:")}</td><td><input type="number" name="vendamochila" min="0" max="1000" value="{$c["vendamochila"]}" style="width:70px" /> % <span class="small">{$t("Multiplicador do preço que as lojas pagam pelos itens da mochila (100 = preço base, 0 = as lojas não compram).")}</span></td></tr>
<tr><td>{$t("Poções por luta:")}</td><td><input type="number" name="limitepocoesluta" min="0" max="99" value="{$c["limitepocoesluta"]}" style="width:60px" /> <span class="small">{$t("Quantas vezes cada tipo de poção pode ser usado numa mesma luta ou duelo (0 = sem limite).")}</span></td></tr>
<tr><td>{$t("Registro da luta:")}</td><td><input type="number" name="lutalog_linhas" min="1" max="50" value="{$c["lutalog_linhas"]}" style="width:60px" /> <span class="small">{$t("Quantas ações (suas e do inimigo) o Registro da luta guarda e mostra (1 a 50; padrão 15).")}</span></td></tr>
<tr><td>{$t("Durabilidade:")}</td><td><select name="durabilidade_ativa"><option value="1"{$seldur1}>{$t("Ativa")}</option><option value="0"{$seldur0}>{$t("Desligada")}</option></select> {$t("reparo:")} <input type="number" name="reparo_pct" min="0" max="1000" value="{$c["reparo_pct"]}" style="width:60px" /> % <span class="small">{$t("Ativa: cada vitória gasta 1 de durabilidade dos itens fortes (items/drops.durabilidade > 0) e o item quebra no zero. Reparo: % do valor do item (preço da loja ou 150 × nível do drop) cobrado por um reparo completo, proporcional ao que falta.")}</span></td></tr>
<tr><td>{$t("Inimigos elite e raros:")}</td><td>{$t("elite")} +<input type="number" name="raridade_elite_pct" min="0" max="500" value="{$c["raridade_elite_pct"]}" style="width:55px" />% {$t("drop")} <input type="number" name="raridade_drop_elite" min="0" max="1000" value="{$c["raridade_drop_elite"]}" style="width:60px" />% &nbsp; {$t("raro")} +<input type="number" name="raridade_raro_pct" min="0" max="500" value="{$c["raridade_raro_pct"]}" style="width:55px" />% {$t("drop")} <input type="number" name="raridade_drop_raro" min="0" max="1000" value="{$c["raridade_drop_raro"]}" style="width:60px" />% &nbsp; {$t("teto do drop")} <input type="number" name="raridade_drop_teto" min="1" max="100" value="{$c["raridade_drop_teto"]}" style="width:55px" />% <span class="small">{$t("Bônus sobre a base de naruto_monsters (HP, ataque, defesa, XP e Ryou) e chance de drop em % da normal (150 = ×1,5; com teto por item). Elite tem Couraça (−10% de dano recebido); raro tem Vigor (+3% do HP por turno). Chefes não mudam.")}</span></td></tr>
<tr><td>{$t("Painel do GM:")}</td><td>{$t("teleportar jogadores")} <select name="gm_teleporte"><option value="1"{$selgmtp1}>{$t("Permitido")}</option><option value="0"{$selgmtp0}>{$t("Não")}</option></select> &nbsp; {$t("ver o Mapa do jogo")} <select name="gm_mapa"><option value="1"{$selgmmapa1}>{$t("Permitido")}</option><option value="0"{$selgmmapa0}>{$t("Não")}</option></select></td></tr>
<tr><td>{$t("Roubo de itens:")}</td><td><select name="roubo_ativo"><option value="1"{$selroubo1}>{$t("Ativo")}</option><option value="0"{$selroubo0}>{$t("Desligado")}</option></select> {$t("mochila")} <input type="number" name="roubo_chance_mochila" min="0" max="100" value="{$c["roubo_chance_mochila"]}" style="width:55px" />% {$t("equipado")} <input type="number" name="roubo_chance_equipado" min="0" max="100" value="{$c["roubo_chance_equipado"]}" style="width:55px" />% <span class="small">{$t("Chance base por turno de um inimigo que rouba levar um item; a coluna Rouba de cada inimigo ajusta (50 = a base, 100 = o dobro). No máximo um roubo por luta. Recuperação definida em cada inimigo.")}</span></td></tr>
<tr><td>{$t("Roubo de Ryou:")}</td><td>{$t("chance")} <input type="number" name="roubo_ryou_chance" min="0" max="100" value="{$c["roubo_ryou_chance"]}" style="width:55px" />% {$t("leva de")} <input type="number" name="roubo_ryou_min" min="1" max="100" value="{$c["roubo_ryou_min"]}" style="width:55px" />% {$t("a")} <input type="number" name="roubo_ryou_max" min="1" max="100" value="{$c["roubo_ryou_max"]}" style="width:55px" />% {$t("do Ryou, no máximo")} <input type="number" name="roubo_ryou_teto" min="0" max="100000" value="{$c["roubo_ryou_teto"]}" style="width:70px" /> {$t("× nível do inimigo")} <span class="small">{$t("Sorteia a porcentagem entre o mínimo e o máximo. A coluna Rouba Ryou de cada inimigo ajusta a chance (50 = a base, 100 = o dobro). Teto 0 = sem teto. Derrotar o inimigo devolve o Ryou (mesma regra de recuperação dos itens).")}</span></td></tr>
<tr><td>{$t("Status de inimigos:")}</td><td><select name="status_monstros_ativo"><option value="1"{$selstat1}>{$t("Ativo")}</option><option value="0"{$selstat0}>{$t("Desligado")}</option></select> {$t("multiplicador")} <input type="number" name="status_monstros_mult" min="0" max="1000" value="{$c["status_monstros_mult"]}" style="width:60px" />% <span class="small">{$t("Veneno, queimadura, sono, atordoar e dreno que os inimigos causam no jogador (chance de cada inimigo × este %; 100 = normal).")}</span></td></tr>
<tr><td>{$t("Clãs:")}</td><td>{$t("custo")} <input type="number" name="cla_custo" min="0" max="16777215" value="{$c["cla_custo"]}" style="width:80px" /> {$t("membros")} <input type="number" name="cla_maxmembros" min="1" max="500" value="{$c["cla_maxmembros"]}" style="width:55px" /> {$t("meta semanal")} <input type="number" name="cla_meta" min="1" max="1000000" value="{$c["cla_meta"]}" style="width:70px" /> {$t("recompensa")} <input type="number" name="cla_premio" min="0" max="16777215" value="{$c["cla_premio"]}" style="width:80px" /> <span class="small">{$t("Custo em Ryou para criar, máximo de membros, inimigos que o clã precisa derrotar na semana e Ryou dividido entre quem contribuiu.")}</span></td></tr>
<tr><td>{$t("Temporada de duelo:")}</td><td><input type="number" name="temporada_dias" min="1" max="365" value="{$c["temporada_dias"]}" style="width:55px" /> {$t("dias")}, {$t("vitória")} +<input type="number" name="temporada_vitoria" min="0" max="1000" value="{$c["temporada_vitoria"]}" style="width:55px" /> {$t("derrota")} -<input type="number" name="temporada_derrota" min="0" max="1000" value="{$c["temporada_derrota"]}" style="width:55px" /> <span class="small">{$t("A duração vale para a próxima temporada aberta. Os 3 primeiros ganham um título na ficha.")}</span></td></tr>
<tr><td>{$t("Comprador de pergaminhos:")}</td><td><select name="compradorvila"><option value="1"{$selvila1}>{$t("Também nas vilas (preço normal)")}</option><option value="0"{$selvila0}>{$t("Só o comprador distante")}</option></select> {$t("O comprador distante paga")} <input type="number" name="compradorremoto" min="0" max="1000" value="{$c["compradorremoto"]}" style="width:70px" /> % <span class="small">{$t("do preço da vila (padrão 200 = o dobro). O comprador distante é o NPC com o papel \"Comprador de pergaminhos\" (NPCs do mapa).")}</span></td></tr>
<tr><td>{$t("Pilha dos consumíveis da loja:")}</td><td><input type="number" name="pilhaconsumiveis" min="1" max="999" value="{$c["pilhaconsumiveis"]}" style="width:70px" /> <span class="small">{$t("Máximo de Pergaminhos de Retorno ou Incensos Repelentes num espaço da mochila (1 = não empilha).")}</span></td></tr>
<tr><td>{$t("Pontos de viagem (TP):")}</td><td>{$t("Retorno: mínimo")} <input type="number" name="tpretorno" min="0" max="1000" value="{$c["tpretorno"]}" style="width:60px" /> {$t("ou % do TP da vila natal:")} <input type="number" name="tpretornopct" min="0" max="255" value="{$c["tpretornopct"]}" style="width:60px" /> &nbsp; {$t("Teleporte Aleatório:")} <input type="number" name="tpteleporte" min="0" max="1000" value="{$c["tpteleporte"]}" style="width:60px" /> TP<br />{$t("Viajar Para: Ryou por TP")} <input type="number" name="viagemryou" min="0" max="1000" value="{$c["viagemryou"]}" style="width:60px" /> &nbsp; {$t("Descanso na cidade: TP")} <input type="number" name="tpdescanso" min="0" max="100" value="{$c["tpdescanso"]}" style="width:50px" /> {$t("EP")} <input type="number" name="epdescanso" min="0" max="100" value="{$c["epdescanso"]}" style="width:50px" /><br />{$t("Fugir de uma luta custa (% do Ryou carregado):")} <input type="number" name="fugapenalidade" min="0" max="100" value="{$c["fugapenalidade"]}" style="width:50px" /> %<br /><span class="small">{$t("O Pergaminho de Retorno custa o maior entre o mínimo e a % do TP da viagem até a vila natal. A taxa em Ryou do Viajar Para é TP da viagem x Ryou por TP (0 = sem taxa).")}</span></td></tr>
<tr><td>{$t("Custo do Viajar Para:")}</td><td>{$t("TP fixo")} <input type="number" name="viagemtpbase" min="0" max="1000" value="{$c["viagemtpbase"]}" style="width:60px" /> {$t("TP a cada 100 casas")} <input type="number" name="viagemtpdist" min="0" max="1000" value="{$c["viagemtpdist"]}" style="width:60px" /><br />{$t("Vila natal: TP mínimo (% do Retorno)")} <input type="number" name="viagemnataltppct" min="0" max="5000" value="{$c["viagemnataltppct"]}" style="width:60px" /> {$t("Ryou mínimo (% do preço do Retorno)")} <input type="number" name="viagemnatalryoupct" min="0" max="10000" value="{$c["viagemnatalryoupct"]}" style="width:60px" /><br />{$t("Outras vilas: taxa fixa em Ryou")} <input type="number" name="viagemoutraryou" min="0" max="1000000" value="{$c["viagemoutraryou"]}" style="width:70px" /> {$t("Selos de Viagem gastos")} <input type="number" name="viagemselos" min="0" max="10" value="{$c["viagemselos"]}" style="width:50px" /> {$t("Id do drop Selo de Viagem")} <input type="number" name="viagemseloitem" min="1" max="16777215" value="{$c["viagemseloitem"]}" style="width:60px" /><br /><span class="small">{$t("TP = TP da vila + TP fixo + distância x TP a cada 100 casas / 100. Vila natal: no mínimo a % do TP do Pergaminho de Retorno (nunca mais que o TP máximo do jogador), Ryou = maior entre TP x Ryou por TP e a % do preço do Retorno, sem selo. Outras vilas: Ryou = taxa fixa + TP x Ryou por TP, e gasta os Selos de Viagem.")}</span></td></tr>
<tr><td>{$t("Troca de vila natal:")}</td><td>{$t("Esperar")} <input type="number" name="trocavila_dias" min="0" max="3650" value="{$c["trocavila_dias"]}" style="width:60px" /> {$t("dia(s) entre duas trocas")}<br /><span class="small">{$t("A troca é feita pelas missões de transferência dos embaixadores (campo Troca de vila natal das Missões extras); nível, graduação e taxa em Ryou ficam nas próprias missões. 0 = sem espera.")}</span></td></tr>
<tr><td>{$t("Vila inicial:")}</td><td><select name="vilainicial">$opvilainicial</select><br /><span class="small">{$t("Vila natal dada a todo ninja novo (o cadastro não pergunta a vila). Padrão: a Vila da Folha, no centro do mapa, com a área de iniciantes. Depois o jogador só troca pelas missões de transferência dos embaixadores.")}</span></td></tr>
<tr><td>{$t("Troca de dificuldade:")}</td><td>{$t("Esperar")} <input type="number" name="trocadif_dias" min="0" max="3650" value="{$c["trocadif_dias"]}" style="width:60px" /> {$t("dia(s) entre duas trocas")}<br /><span class="small">{$t("A troca é feita pelas provações do Mestre das Provações (campo Troca de dificuldade das Missões extras); nível, inimigos e taxa em Ryou ficam nas próprias missões. 0 = sem espera.")}</span></td></tr>
<tr><td>{$t("Histórico do jogador:")}</td><td><input type="number" name="historicomax" min="5" max="30" value="{$c["historicomax"]}" style="width:60px" /> {$t("mensagens")}<br /><span class="small">{$t("Quantas mensagens recentes ficam guardadas e aparecem no quadro Histórico (5 a 30, padrão 12).")}</span></td></tr>
<tr><td>{$t("Contas por IP:")}</td><td>{$t("No máximo")} <input type="number" name="loginip_max" min="0" max="1000" value="{$c["loginip_max"]}" style="width:60px" /> {$t("conta(s) logada(s) ao mesmo tempo pelo mesmo IP")}<br /><span class="small">{$t("Um login a mais vindo do mesmo IP é recusado. A vaga é liberada no logout ou depois de 10 minutos sem atividade. Vale para todos, inclusive a equipe. 0 = sem limite (padrão 3).")}</span></td></tr>
<tr><td>{$t("Jogar sem conta:")}</td><td><select name="convidado_ativo"><option value="1"{$selconv1}>{$t("Ativo")}</option><option value="0"{$selconv0}>{$t("Desligado")}</option></select> &middot; {$t("O personagem de convidado some depois de")} <input type="number" name="convidado_horas" min="1" max="720" value="{$c["convidado_horas"]}" style="width:60px" /> {$t("hora(s)")}; {$t("no máximo")} <input type="number" name="convidado_porip" min="0" max="1000" value="{$c["convidado_porip"]}" style="width:60px" /> {$t("convidado(s) novo(s) por IP por hora")}<br /><span class="small">{$t("Convidado: jogador sem conta identificado só pela sessão do navegador. Se não criar a conta dentro do prazo, o personagem e tudo ligado a ele é apagado. Desligado: ninguém novo entra como convidado e a página principal manda para o login; os convidados que já existem continuam até o prazo. 0 convidados por IP também desliga.")}</span></td></tr>
<tr><td>{$t("Pontos de elemento (EP):")}</td><td>{$t("EP por jutsu com elemento:")} <input type="number" name="epcusto" min="0" max="100" value="{$c["epcusto"]}" style="width:50px" /> {$t("Bônus de dano (%):")} <input type="number" name="epbonus" min="0" max="200" value="{$c["epbonus"]}" style="width:50px" /> {$t("+1 EP máximo a cada")} <input type="number" name="epnivel" min="0" max="100" value="{$c["epnivel"]}" style="width:50px" /> {$t("níveis")}<br /><span class="small">{$t("Sem EP o jutsu com elemento funciona, mas sem o bônus de atributo do elemento e sem o bônus de dano acima.")}</span></td></tr>
<tr><td>{$t("Elementos do ninja:")}</td><td>{$t("Principal:")} +<input type="number" name="elemento_bonus" min="0" max="500" value="{$c["elemento_bonus"]}" style="width:50px" />% {$t("Secundário:")} +<input type="number" name="elemento_bonus2" min="0" max="500" value="{$c["elemento_bonus2"]}" style="width:50px" />% {$t("Mesmo elemento recebe")} <input type="number" name="elemento_resiste" min="0" max="100" value="{$c["elemento_resiste"]}" style="width:50px" />% {$t("Peso do secundário do alvo:")} <input type="number" name="elemento_peso2" min="0" max="100" value="{$c["elemento_peso2"]}" style="width:50px" />% {$t("Escolha a partir do nível")} <input type="number" name="elemento_nivel" min="1" max="1000" value="{$c["elemento_nivel"]}" style="width:50px" /><br /><span class="small">{$t("Bônus de dano nos jutsus do elemento principal/secundário do jogador (com EP), somado aos pontos do atributo ligado (que contam em dobro no principal e x1,5 no secundário). Mesmo elemento: o alvo resiste. Peso do secundário: quanto o segundo elemento de um jogador conta na vantagem/desvantagem (100 = igual ao principal).")}</span></td></tr>
<tr><td>{$t("Multiplicador de experiência:")}</td><td><input type="text" name="expmultiplier" size="5" value="{$c["expmultiplier"]}" /> <span class="small">{$t("1 = normal, 2 = evento de experiência em dobro.")}</span></td></tr>
<tr><td>{$t("Multiplicador de Ryou:")}</td><td><input type="text" name="goldmultiplier" size="5" value="{$c["goldmultiplier"]}" /> <span class="small">{$t("1 = normal.")}</span></td></tr>
<tr><td>{$t("Nível mínimo para duelos e doações:")}</td><td><input type="number" name="pvplevel" min="1" value="{$c["pvplevel"]}" /></td></tr>
<tr><td>{$t("Especializações:")}</td><td><input type="text" name="class1name" size="15" maxlength="50" value="{$c["class1name"]}" /> <input type="text" name="class2name" size="15" maxlength="50" value="{$c["class2name"]}" /> <input type="text" name="class3name" size="15" maxlength="50" value="{$c["class3name"]}" /></td></tr>
<tr><td>{$t("Dificuldade 1:")}</td><td><input type="text" name="diff1name" size="15" maxlength="50" value="{$c["diff1name"]}" /> {$t("multiplicador")} <input type="text" name="diff1mod" size="4" value="{$c["diff1mod"]}" /></td></tr>
<tr><td>{$t("Dificuldade 2:")}</td><td><input type="text" name="diff2name" size="15" maxlength="50" value="{$c["diff2name"]}" /> {$t("multiplicador")} <input type="text" name="diff2mod" size="4" value="{$c["diff2mod"]}" /></td></tr>
<tr><td>{$t("Dificuldade 3:")}</td><td><input type="text" name="diff3name" size="15" maxlength="50" value="{$c["diff3name"]}" /> {$t("multiplicador")} <input type="text" name="diff3mod" size="4" value="{$c["diff3mod"]}" /><br /><span class="small">{$t("O multiplicador aumenta HP e dano dos inimigos e também a experiência e o Ryou ganhos.")}</span></td></tr>
</table>
<h2>{$t("PvP no mundo")}</h2>
<p class="small">{$t("Com o PvP ligado, um jogador pode atacar outro que esteja online na mesma casa do mapa ou numa casa vizinha (fora das vilas). A luta é resolvida na hora com as regras do duelo. Quem perde volta para a vila natal com 1/4 do HP, pode perder Ryou e itens. Mudar estas opções pede a sua senha.")}</p>
<table width="100%">
<tr><td width="230"><span class="highlight">{$t("PvP no mundo:")}</span></td><td><select name="pvpmundo">$oppvp</select></td></tr>
<tr><td>{$t("Diferença máxima de nível:")}</td><td><input type="number" name="pvpdiflevel" min="0" max="1000" value="{$c["pvpdiflevel"]}" style="width:70px" /> <span class="small">{$t("0 = sem limite.")}</span></td></tr>
<tr><td>{$t("Nível seguro:")}</td><td>{$t("abaixo do nível")} <input type="number" name="pvpnivelseguro" min="0" max="1000" value="{$c["pvpnivelseguro"]}" style="width:70px" /> <span class="small">{$t("ninguém ataca nem é atacado (0 = desligado). O nível mínimo de duelos também vale.")}</span></td></tr>
<tr><td>{$t("Espera para atacar o mesmo jogador:")}</td><td><input type="number" name="pvpesperaalvo" min="0" max="10000" value="{$c["pvpesperaalvo"]}" style="width:70px" /> {$t("minutos")} <span class="small">{$t("Vale nos dois sentidos (evita perseguir a mesma pessoa).")}</span></td></tr>
<tr><td>{$t("Espera entre ataques:")}</td><td><input type="number" name="pvpesperaataque" min="0" max="10000" value="{$c["pvpesperaataque"]}" style="width:70px" /> {$t("minutos")} <span class="small">{$t("Tempo mínimo entre dois ataques do mesmo jogador (a qualquer alvo).")}</span></td></tr>
<tr><td>{$t("Ryou perdido pelo derrotado:")}</td><td><input type="number" name="pvpperdaryou" min="0" max="100" value="{$c["pvpperdaryou"]}" style="width:70px" /> % <span class="small">{$t("Do Ryou que ele carrega; vai para o vencedor (até o limite que ele pode carregar).")}</span></td></tr>
<tr><td>{$t("Chance de perder item:")}</td><td><input type="number" name="pvpchancedrop" min="0" max="100" value="{$c["pvpchancedrop"]}" style="width:70px" /> % {$t("por item, até")} <input type="number" name="pvpmaxitens" min="0" max="8" value="{$c["pvpmaxitens"]}" style="width:50px" /> {$t("item(ns)")}</td></tr>
<tr><td>{$t("De onde saem os itens:")}</td><td><select name="pvpondedrop">$opondedrop</select> &nbsp; {$t("Variantes raras:")} <select name="pvpprotegervariantes">$opvariantes</select><br /><span class="small">{$t("O item perdido vai para a mochila do vencedor só se ele tiver um espaço livre; senão o item fica com o derrotado.")}</span></td></tr>
<tr><td><img src="images/caveira_branca.gif" alt="" /> {$t("Caveira branca:")}</td><td>{$t("quem mata (sem ser em legítima defesa) fica marcado por")} <input type="number" name="pvpbrancahoras" min="1" max="10000" value="{$c["pvpbrancahoras"]}" style="width:60px" /> {$t("horas; se morrer, a chance de perder item aumenta")} <input type="number" name="pvpbrancachance" min="0" max="100" value="{$c["pvpbrancachance"]}" style="width:55px" /> %</td></tr>
<tr><td><img src="images/caveira_vermelha.gif" alt="" /> {$t("Caveira vermelha:")}</td><td><input type="number" name="pvpvermelhamortes" min="1" max="100" value="{$c["pvpvermelhamortes"]}" style="width:55px" /> {$t("mortes dentro do tempo da caveira branca dão a caveira vermelha por")} <input type="number" name="pvpvermelhadias" min="1" max="1000" value="{$c["pvpvermelhadias"]}" style="width:55px" /> {$t("dias: se morrer, perde TODOS os itens (mochila e equipados). Banco de itens também:")} <select name="pvpvermelhabanco">$opbanco</select></td></tr>
<tr><td>{$t("Banimento automático:")}</td><td>{$t("quem matar de novo com caveira vermelha é banido por")} <input type="number" name="pvpbanirdias" min="1" max="3650" value="{$c["pvpbanirdias"]}" style="width:60px" /> {$t("dias.")} {$t("Perde metade dos itens (sorteados):")} <select name="pvpbanirmetade">$opbanirmetade</select> {$t("Perde")} <input type="number" name="pvpbanirniveis" min="0" max="99" value="{$c["pvpbanirniveis"]}" style="width:50px" /> {$t("níveis na hora (0 = nenhum).")} <a href="admin.php?do=caveiras">{$t("Ver jogadores com caveira")}</a></td></tr>
<tr><td>{$t("Morte com caveira vermelha:")}</td><td>{$t("quem morre no PvP com caveira vermelha perde de")} <input type="number" name="pvpvermelhaperdamin" min="0" max="99" value="{$c["pvpvermelhaperdamin"]}" style="width:50px" /> {$t("a")} <input type="number" name="pvpvermelhaperdamax" min="0" max="99" value="{$c["pvpvermelhaperdamax"]}" style="width:50px" /> {$t("níveis (sorteado). 0 a 0 = não perde níveis.")}</td></tr>
<tr><td>{$t("Caveira exposta:")}</td><td>{$t("depois de cada morte injusta, quem tem caveira continua atacável por")} <input type="number" name="pvpcaveiraminutos" min="0" max="10000" value="{$c["pvpcaveiraminutos"]}" style="width:60px" /> {$t("minutos, mesmo offline (fica na última casa do mapa), e não pode entrar em vilas nesse tempo. 0 = desligado.")}</td></tr>
</table>
<h2>{$t("Morte para inimigos do mapa")}</h2>
<p class="small">{$t("Ryou perdido = mínimo entre o máximo e (base + nível × por nível) % do Ryou que carrega. Experiência perdida = nível × por nível % da barra do nível atual (nunca desce de nível), até o máximo. Chance de perder (destruir) um item comum da mochila = nível × por nível %, até o máximo; variantes, pergaminhos e itens de missão nunca são perdidos. Até o nível protegido só o Ryou é cobrado. O jogador volta para a vila natal com 1/4 do HP.")}</p>
<table width="100%">
<tr><td width="230">{$t("Ryou perdido:")}</td><td>{$t("base")} <input type="number" name="morteryoubase" min="0" max="100" value="{$c["morteryoubase"]}" style="width:55px" /> % + {$t("nível ×")} <input type="number" name="morteryounivel" min="0" max="100" step="0.01" value="{$c["morteryounivel"]}" style="width:65px" /> %, {$t("máximo")} <input type="number" name="morteryoumax" min="0" max="100" value="{$c["morteryoumax"]}" style="width:55px" /> %</td></tr>
<tr><td>{$t("Experiência perdida:")}</td><td>{$t("base")} <input type="number" name="morteexpbase" min="0" max="100" value="{$c["morteexpbase"]}" style="width:55px" /> % + {$t("nível ×")} <input type="number" name="morteexpnivel" min="0" max="100" step="0.01" value="{$c["morteexpnivel"]}" style="width:65px" /> % {$t("da barra do nível")}, {$t("máximo")} <input type="number" name="morteexpmax" min="0" max="100" value="{$c["morteexpmax"]}" style="width:55px" /> %</td></tr>
<tr><td>{$t("Chance de perder um item:")}</td><td>{$t("nível ×")} <input type="number" name="morteitemnivel" min="0" max="100" step="0.01" value="{$c["morteitemnivel"]}" style="width:65px" /> %, {$t("máximo")} <input type="number" name="morteitemmax" min="0" max="100" value="{$c["morteitemmax"]}" style="width:55px" /> %</td></tr>
<tr><td>{$t("Nível protegido:")}</td><td>{$t("até o nível")} <input type="number" name="mortenivelseguro" min="0" max="1000" value="{$c["mortenivelseguro"]}" style="width:65px" /> <span class="small">{$t("só perde Ryou (sem experiência nem item).")}</span></td></tr>
<tr><td>{$t("Sair no meio da luta:")}</td><td>{$t("logout ou luta parada por mais de")} <input type="number" name="mortelutaabandono" min="0" max="1440" value="{$c["mortelutaabandono"]}" style="width:60px" /> {$t("minutos (0 = não verifica) conta como derrota com")} <input type="number" name="mortelutachance" min="0" max="100" value="{$c["mortelutachance"]}" style="width:55px" /> {$t("% de chance; senão a luta só é cancelada (sem recompensa). Em duelos é sempre derrota.")}</td></tr>
<tr><td>{$t("Com os valores atuais:")}</td><td><table class="small"><tr><th>{$t("Nível")}</th><th>Ryou</th><th>{$t("Experiência")}</th><th>{$t("Item")}</th></tr>$exmorte</table></td></tr>
</table>
<h2>{$t("Alquimia")}</h2>
<p class="small">{$t("Chance = chance da receita + 3 por nível de alquimia acima do nível da receita (5% a 95%; receitas de 100% não falham). Receitas em")} <a href="admin.php?do=receitas">{$t("Receitas da alquimia")}</a>.</p>
<table width="100%">
<tr><td width="230">{$t("Perda na falha:")}</td><td><input type="number" name="alquimiaperda" min="0" max="100" value="{$c["alquimiaperda"]}" style="width:60px" /> % <span class="small">{$t("de cada ingrediente (arredondado). Receitas até o nível 5 de alquimia nunca perdem tudo.")}</span></td></tr>
<tr><td>{$t("Ganho de experiência:")}</td><td><input type="number" name="alquimiaexptaxa" min="0" max="1000" value="{$c["alquimiaexptaxa"]}" style="width:60px" /> % <span class="small">{$t("100 = normal: (8 + 4 × nível da receita) no sucesso, 1/3 na falha; o nível sobe a cada 50 × nível.")}</span></td></tr>
<tr><td>{$t("Sucesso crítico:")}</td><td><input type="number" name="alquimiacritico" min="0" max="100" step="0.01" value="{$c["alquimiacritico"]}" style="width:70px" /> % <span class="small">{$t("chance de o resultado sair em dobro, no máximo uma vez por dia por jogador.")}</span></td></tr>
<tr><td>{$t("Custo extra das infusões:")}</td><td><input type="number" name="alquimiainfusaocusto" min="0" max="16000000" value="{$c["alquimiainfusaocusto"]}" style="width:90px" /> Ryou <span class="small">{$t("somado ao custo de cada receita de infusão de variante.")}</span></td></tr>
</table>
<h2>{$t("Páginas das vilas")}</h2>
<table width="100%">
<tr><td width="230">{$t("Mostrar notícias:")}</td><td><select name="shownews">{$op["shownews"]}</select></td></tr>
<tr><td>{$t("Mostrar quem está online:")}</td><td><select name="showonline">{$op["showonline"]}</select></td></tr>
<tr><td>{$t("Mostrar chat global:")}</td><td><select name="showbabble">{$op["showbabble"]}</select></td></tr>
<tr><td>{$t("Fórum:")}</td><td><select name="forumtype">$opforum</select> <input type="text" name="forumaddress" size="40" maxlength="200" value="{$c["forumaddress"]}" placeholder="{$t("https://... (só para fórum externo)")}" /></td></tr>
</table>
<br /><input type="submit" name="submit" value="{$t("Salvar configurações")}" />
</form>
END;

    admindisplay($page, t("Configurações gerais"));

}


/* ------------------------------------------------------------------------
   Notícias.
   ------------------------------------------------------------------------ */

function news() {

    $mensagem = "";
    if (isset($_POST["submit"])) {
        $erros = "";
        $content = admtexto("content", t("Texto da notícia"), $erros, true, 5000);
        if ($erros == "") { doquery("INSERT INTO {{table}} SET postdate=NOW(), content='$content'", "news"); $mensagem = admmsg(t("Notícia publicada.")); }
        else { $mensagem = admmsg($erros, "erro"); }
    }
    if (isset($_GET["apagar"])) {
        admgetcsrf();
        doquery("DELETE FROM {{table}} WHERE id='".pegarint($_GET, "apagar")."' LIMIT 1", "news");
        $mensagem = admmsg(t("Notícia apagada."));
    }

    $csrf = csrf_campo();
    $page = "<h1>".t("Notícias")."</h1>$mensagem<p>".t("A notícia mais recente aparece na página das vilas (se ativado nas configurações).")."</p>";
    $page .= "<form action=\"admin.php?do=news\" method=\"post\">$csrf<textarea name=\"content\" rows=\"5\" cols=\"80\"></textarea><br /><input type=\"submit\" name=\"submit\" value=\"".t("Publicar notícia")."\" /></form>";
    $page .= "<h2>".t("Notícias publicadas")."</h2><table class=\"lista\"><tr><th width=\"110\">".t("Data")."</th><th>".t("Texto")."</th><th width=\"120\">".t("Ações")."</th></tr>";
    $query = doquery("SELECT * FROM {{table}} ORDER BY id DESC", "news");
    while ($row = mysqli_fetch_array($query)) {
        $page .= "<tr><td>".padraodate($row["postdate"])."</td><td>".nl2br($row["content"])."</td><td><a href=\"admin.php?do=editnews:".$row["id"]."\">".t("Editar")."</a> | <a href=\"".admlinkcsrf("admin.php?do=news&amp;apagar=".$row["id"])."\" onclick=\"return confirm('".t("Apagar esta notícia?")."');\">".t("Apagar")."</a></td></tr>";
    }
    if (mysqli_num_rows($query) == 0) { $page .= "<tr><td colspan=\"3\">".t("Nenhuma notícia.")."</td></tr>"; }
    $page .= "</table>";
    admindisplay($page, t("Notícias"));

}

function editnews($id) {

    $mensagem = "";
    if (isset($_POST["submit"])) {
        $erros = "";
        $content = admtexto("content", t("Texto da notícia"), $erros, true, 5000);
        if ($erros == "") { doquery("UPDATE {{table}} SET content='$content' WHERE id='$id' LIMIT 1", "news"); $mensagem = admmsg(t("Notícia atualizada. <a href=\"admin.php?do=news\">Voltar</a>")); }
        else { $mensagem = admmsg($erros, "erro"); }
    }
    $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "news");
    if (mysqli_num_rows($query) != 1) { admindisplay(admmsg(t("Notícia não encontrada."), "erro"), t("Notícias")); }
    $row = mysqli_fetch_array($query);
    $csrf = csrf_campo();
    $page = "<h1>".t("Editar notícia")."</h1>$mensagem<form action=\"admin.php?do=editnews:$id\" method=\"post\">$csrf<textarea name=\"content\" rows=\"8\" cols=\"80\">".$row["content"]."</textarea><br /><input type=\"submit\" name=\"submit\" value=\"".t("Salvar")."\" /> <a href=\"admin.php?do=news\">".t("Cancelar")."</a></form>";
    admindisplay($page, t("Notícias"));

}


/* ------------------------------------------------------------------------
   Mensagem para todos os jogadores (janela flutuante, campo users.mainmsg).
   Formato do campo: "imagem#$%;TÍTULO#$%;conteúdo" (lido por templates/mostrarmainmsg.php).
   ------------------------------------------------------------------------ */

function mensagemglobal() {

    $mensagem = "";
    $imagens = array();
    foreach (glob("layoutnovo/dropmenu/grande/avatares/*.jpg") as $arquivo) { $imagens[basename($arquivo, ".jpg")] = ucfirst(basename($arquivo, ".jpg")); }

    if (isset($_POST["submit"])) {
        $erros = "";
        $titulo = admtexto("titulo", t("Título"), $erros, true, 40);
        $texto = admtexto("texto", t("Mensagem"), $erros, true, 1500);
        $imagem = pegartexto($_POST, "imagem", "naruto"); if (!isset($imagens[$imagem])) { $imagem = "naruto"; }
        $somenteonline = (pegarint($_POST, "somenteonline") == 1);
        if ($erros == "") {
            $onde = $somenteonline ? "WHERE UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."'" : "";
            doquery("UPDATE {{table}} SET mainmsg='".$imagem."#$%;".$titulo."#$%;".nl2br($texto)."' $onde", "users");
            global $link;
            $mensagem = admmsg(tf("Mensagem enviada para %s jogador(es). Ela aparece uma única vez, na próxima página que cada um abrir.", mysqli_affected_rows($link)));
        } else { $mensagem = admmsg($erros, "erro"); }
    }

    $opimagens = admopcoes($imagens, "naruto");
    $csrf = csrf_campo();
$t = "t"; $tf = "tf";
$page = <<<END
<h1>{$t("Mensagem para todos")}</h1>
$mensagem
<p>{$t("Abre a janela flutuante de mensagens do jogo para os jogadores (útil para avisos de manutenção ou eventos).")}</p>
<form action="admin.php?do=mensagemglobal" method="post">$csrf
<table>
<tr><td width="120">{$t("Título:")}</td><td><input type="text" name="titulo" size="40" maxlength="40" /></td></tr>
<tr><td>{$t("Imagem:")}</td><td><select name="imagem">$opimagens</select></td></tr>
<tr><td>{$t("Mensagem:")}</td><td><textarea name="texto" rows="5" cols="70"></textarea></td></tr>
<tr><td>{$t("Enviar para:")}</td><td><select name="somenteonline"><option value="0">{$t("Todos os jogadores")}</option><option value="1">{$t("Somente quem está online")}</option></select></td></tr>
</table>
<input type="submit" name="submit" value="{$t("Enviar mensagem")}" />
</form>
END;
    admindisplay($page, t("Mensagem para todos"));

}


/* ------------------------------------------------------------------------
   Jogadores.
   ------------------------------------------------------------------------ */

function users() {

    $busca = pegartexto($_GET, "busca");
    $filtro = pegartexto($_GET, "filtro", "todos");
    $pagina = max(1, pegarint($_GET, "pagina", 1));
    $porpagina = 50;

    $onde = "1";
    if ($busca != "") { $onde .= " AND (username LIKE '%$busca%' OR charname LIKE '%$busca%' OR email LIKE '%$busca%')"; }
    if ($filtro == "online") { $onde .= " AND UNIX_TIMESTAMP(onlinetime) >= '".(time()-600)."'"; }
    elseif ($filtro == "banidos") { $onde .= " AND authlevel='2'"; }
    elseif ($filtro == "admins") { $onde .= " AND (authlevel='1' OR acesso IN ('2','3'))"; }
    elseif ($filtro == "naoverificados") { $onde .= " AND verify<>'1'"; }
    elseif ($filtro == "batalha") { $onde .= " AND (currentaction='Fighting' OR batalha_timer2<>'0')"; }

    $total = admcontar("users", $onde);
    $paginas = max(1, ceil($total / $porpagina));
    if ($pagina > $paginas) { $pagina = $paginas; }
    $inicio = ($pagina - 1) * $porpagina;

    $opfiltro = admopcoes(array("todos"=>t("Todos"), "online"=>t("Online agora"), "banidos"=>t("Banidos"), "admins"=>t("Equipe (admin/tutor/GM)"), "naoverificados"=>t("E-mail não verificado"), "batalha"=>t("Em batalha/duelo")), $filtro);
$t = "t"; $tf = "tf";
$page = <<<END
<h1>{$t("Jogadores")}</h1>
<form action="admin.php" method="get"><input type="hidden" name="do" value="users" />
{$t("Buscar (conta, personagem ou e-mail):")} <input type="text" name="busca" value="$busca" size="25" /> <select name="filtro">$opfiltro</select> <input type="submit" value="{$t("Filtrar")}" />
</form><br />
<b>$total</b> {$t("jogador(es) encontrado(s).")}<br /><br />
<table class="lista"><tr><th>{$t("ID")}</th><th>{$t("Conta")}</th><th>{$t("Personagem")}</th><th>{$t("Nível")}</th><th>{$t("Vila")}</th><th>{$t("Situação")}</th><th>{$t("Último acesso")}</th></tr>
END;
    $query = doquery("SELECT id,username,charname,level,alinhamento,authlevel,acesso,currentaction,onlinetime,verify FROM {{table}} WHERE $onde ORDER BY id DESC LIMIT $inicio, $porpagina", "users");
    while ($row = mysqli_fetch_array($query)) {
        if ($row["authlevel"] == 1) { $situacao = "<span style=\"color:green\">".t("Administrador")."</span>"; }
        elseif ($row["authlevel"] == 2) { $situacao = "<span class=\"highlight\">".t("Banido")."</span>"; }
        elseif ($row["acesso"] == 2) { $situacao = "Tutor"; }
        elseif ($row["acesso"] == 3) { $situacao = "GameMaster"; }
        else { $situacao = t("Jogador"); }
        if ($row["verify"] != 1) { $situacao .= " <span class=\"small\">".t("(não verificado)")."</span>"; }
        $acao = array("In Town"=>t("na vila"), "Exploring"=>t("explorando"), "Fighting"=>t("em batalha"));
        $page .= "<tr><td>".$row["id"]."</td><td>".$row["username"]."</td><td><a href=\"admin.php?do=edituser:".$row["id"]."\">".$row["charname"]."</a></td><td>".$row["level"]."</td><td>".ucfirst($row["alinhamento"])."</td><td>$situacao <span class=\"small\">".(isset($acao[$row["currentaction"]]) ? $acao[$row["currentaction"]] : "")."</span></td><td>".padraodate($row["onlinetime"])."</td></tr>";
    }
    if ($total == 0) { $page .= "<tr><td colspan=\"7\">".t("Nenhum jogador encontrado.")."</td></tr>"; }
    $page .= "</table><br />".t("Página:")." ";
    for ($p = 1; $p <= $paginas; $p++) {
        $page .= ($p == $pagina) ? "<b>[$p]</b> " : "<a href=\"admin.php?do=users&amp;busca=".urlencode(html_entity_decode($busca))."&amp;filtro=$filtro&amp;pagina=$p\">$p</a> ";
    }
    admindisplay($page, t("Jogadores"));

}

function edituser($id) {

    global $userrow, $controlrow;
    $mensagem = "";

    $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "users");
    if (mysqli_num_rows($query) != 1) { admindisplay(admmsg(t("Jogador não encontrado."), "erro"), t("Jogadores")); }
    $row = mysqli_fetch_array($query);

    // Ações rápidas (botões no topo da ficha).
    if (isset($_POST["acao"])) {
        $acao = pegartexto($_POST, "acao");
        if ($acao == "vila") {
            // Manda o jogador para a vila em que ele está alinhado e encerra batalhas/duelos travados.
            include_once('cidadesconteudo.php'); // Vila natal do alinhamento lida da tabela towns (sem vila = vila 1).
            $vila = vilanataldoalinhamento($row["alinhamento"]);
            if (!$vila) { $vila = mysqli_fetch_array(doquery("SELECT latitude,longitude,name FROM {{table}} ORDER BY id LIMIT 1", "towns")); }
            // Duelo ativo: termina como desistência deste jogador (o outro recebe a aposta e é liberado), antes de limpar as marcas.
            admfuncoesduelo();
            $haviaduelo = dueloforcarderrota($id);
            doquery("UPDATE {{table}} SET currentaction='In Town', currentfight='0', currentmonster='0', currentmonsterhp='0', currentmonstersleep='0', currentmonsterimmune='0', currentuberdamage='0', currentuberdefense='0', batalha_nome='None', batalha_id='0', batalha_hp='0', batalha_timer='0', batalha_timer2='0', batalha_acao='None', latitude='".$vila["latitude"]."', longitude='".$vila["longitude"]."' WHERE id='$id' LIMIT 1", "users");
            $mensagem = admmsg(tf("Jogador enviado para %s e destravado de batalhas/duelos.", $vila["name"]).($haviaduelo ? " ".t("O duelo ativo dele foi encerrado como desistência.") : ""));
        } elseif ($acao == "banir" && $id != $userrow["id"]) {
            doquery("UPDATE {{table}} SET authlevel='2', banidoate='0' WHERE id='$id' LIMIT 1", "users"); // banimento manual: sem prazo.
            $mensagem = admmsg(t("Jogador banido. Ele não consegue mais entrar no jogo."));
        } elseif ($acao == "desbanir") {
            doquery("UPDATE {{table}} SET authlevel='0' WHERE id='$id' AND authlevel='2' LIMIT 1", "users");
            $mensagem = admmsg(t("Banimento removido."));
        } elseif ($acao == "curar") {
            doquery("UPDATE {{table}} SET currenthp=maxhp, currentmp=maxmp, currenttp=maxtp, currentnp=maxnp, currentep=maxep WHERE id='$id' LIMIT 1", "users");
            $mensagem = admmsg(t("HP, chakra, TP, NP e EP restaurados."));
        } elseif ($acao == "apagar") {
            if ($id == $userrow["id"]) { $mensagem = admmsg(t("Você não pode apagar a própria conta."), "erro"); }
            elseif (pegartexto($_POST, "confirmar") != $row["charname"]) { $mensagem = admmsg(t("Para apagar, digite o nome do personagem exatamente como está."), "erro"); }
            else {
                doquery("DELETE FROM {{table}} WHERE id='$id' LIMIT 1", "users");
                admindisplay(admmsg(tf("Conta <b>%s</b> (personagem %s) apagada. <a href=\"admin.php?do=users\">Voltar para a lista</a>", $row["username"], $row["charname"])), t("Jogadores"));
            }
        }
        $row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "users"));
    }

    // Salvar a ficha completa.
    if (isset($_POST["submit"])) {
        $erros = "";
        $numeros = array(
            "authlevel"=>array(t("Nível de acesso"), 0, 2), "acesso"=>array(t("Cargo"), 0, 3), "latitude"=>array(t("Latitude"), -32000, 32000), "longitude"=>array(t("Longitude"), -32000, 32000),
            "difficulty"=>array(t("Dificuldade"), 1, 3), "charclass"=>array(t("Especialização"), 0, 3), // 0 = ainda sem especialização. A escolha do jogador é definitiva; trocar aqui só em casos excepcionais (fica no registro de auditoria) e não recalcula status nem jutsus.
            "currentfight"=>array(t("Turno da batalha"), 0, 255), "currentmonster"=>array(t("Inimigo atual"), 0, 65535),
            "currentmonsterhp"=>array(t("HP do inimigo"), 0, 16000000), "currentmonstersleep"=>array(t("Sono do inimigo"), 0, 255), "currentmonsterimmune"=>array(t("Imunidade do inimigo"), 0, 2),
            "currentuberdamage"=>array(t("Bônus de dano"), 0, 255), "currentuberdefense"=>array(t("Bônus de defesa"), 0, 255),
            "currenthp"=>array(t("HP atual"), 0, 65535), "currentmp"=>array(t("Chakra atual"), 0, 65535), "currenttp"=>array(t("TP atual"), 0, 65535), "currentnp"=>array(t("NP atual"), 0, 16000000), "currentep"=>array(t("EP atual"), 0, 16000000),
            "maxhp"=>array(t("HP máximo"), 1, 65535), "maxmp"=>array(t("Chakra máximo"), 0, 65535), "maxtp"=>array(t("TP máximo"), 1, 65535), "maxnp"=>array(t("NP máximo"), 0, 16000000), "maxep"=>array(t("EP máximo"), 0, 16000000),
            "level"=>array(t("Nível"), 1, 65535), "gold"=>array(t("Ryou"), 0, 16777215), "banco_grana"=>array(t("Ryou no banco"), 0, 16777215), "experience"=>array(t("Experiência"), 0, 16777215),
            "goldbonus"=>array(t("Bônus de Ryou"), -1000, 1000), "expbonus"=>array(t("Bônus de experiência"), -1000, 1000), "strength"=>array(t("Força"), 0, 65535), "dexterity"=>array(t("Destreza"), 0, 65535),
            "attackpower"=>array(t("Poder de ataque"), 0, 65535), "defensepower"=>array(t("Poder de defesa"), 0, 65535), "weaponid"=>array(t("ID da arma"), 0, 65535), "armorid"=>array(t("ID do colete"), 0, 65535),
            "shieldid"=>array(t("ID da bandana"), 0, 65535), "slot1id"=>array(t("ID do slot 1"), 0, 65535), "slot2id"=>array(t("ID do slot 2"), 0, 65535), "slot3id"=>array(t("ID do slot 3"), 0, 65535), "slot4id"=>array(t("ID do slot 4"), 0, 65535),
            "dropcode"=>array(t("Drop pendente"), 0, 16000000), "avatar"=>array(t("Avatar"), 1, 65535), "agilidade"=>array(t("Agilidade"), 0, 16000000), "sorte"=>array(t("Sorte"), 0, 16000000),
            "inteligencia"=>array(t("Inteligência"), 0, 16000000), "precisao"=>array(t("Precisão"), 0, 16000000), "determinacao"=>array(t("Determinação"), 0, 16000000), "pontoatributos"=>array(t("Pontos a distribuir"), 0, 16000000),
            "droprate"=>array(t("Chance de drop"), 0, 65535), "diamante"=>array(t("Diamantes"), 0, 4000000000), "pergaminho"=>array(t("Pergaminhos"), 0, 4000000000));
        $textos = array(
            "email"=>array(t("E-mail"), 100), "verify"=>array(t("Código de verificação"), 8), "charname"=>array(t("Personagem"), 30), "currentaction"=>array(t("Ação atual"), 30),
            "weaponname"=>array(t("Nome da arma"), 50), "armorname"=>array(t("Nome do colete"), 50), "shieldname"=>array(t("Nome da bandana"), 50), "slot1name"=>array(t("Nome do slot 1"), 50),
            "slot2name"=>array(t("Nome do slot 2"), 50), "slot3name"=>array(t("Nome do slot 3"), 50), "slot4name"=>array(t("Nome do slot 4"), 50), "spells"=>array(t("Jutsus"), 255), "towns"=>array(t("Vilas conhecidas"), 255),
            "graduacao"=>array(t("Graduação"), 30), "missao"=>array(t("Missão"), 20), "senjutsutimer"=>array(t("Senjutsu"), 30), "durabilidade"=>array(t("Durabilidade"), 40),
            "elemento"=>array(t("Elementos"), 15), "alinhamento"=>array(t("Vila (alinhamento)"), 10), "bancogeral"=>array(t("Banco de itens"), 10000),
            "bp1"=>array(t("Mochila 1"), 60), "bp2"=>array(t("Mochila 2"), 60), "bp3"=>array(t("Mochila 3"), 60), "bp4"=>array(t("Mochila 4"), 60),
            "bp5"=>array(t("Mochila 5"), 60), "bp6"=>array(t("Mochila 6"), 60), "bp7"=>array(t("Mochila 7"), 60), "bp8"=>array(t("Mochila 8"), 60));
        $sets = array();
        foreach ($numeros as $campo => $regra) { $sets[] = "$campo='".intval(admnumero($campo, $regra[0], $erros, $regra[1], $regra[2]))."'"; }
        foreach ($textos as $campo => $regra) { $sets[] = "$campo='".admtexto($campo, $regra[0], $erros, false, $regra[1])."'"; }
        if (!preg_match("/^[A-Za-z0-9_\-]+$/", pegartexto($_POST, "charname"))) { $erros .= t("O nome do personagem deve ter apenas letras, números, _ ou -.")."<br />"; }
        if (!is_email(pegartexto($_POST, "email"))) { $erros .= t("E-mail inválido.")."<br />"; }
        if (!in_array(pegartexto($_POST, "currentaction"), array("In Town","Exploring","Fighting"))) { $erros .= t("A ação atual deve ser In Town, Exploring ou Fighting.")."<br />"; }
        $outro = doquery("SELECT id FROM {{table}} WHERE charname='".pegartexto($_POST, "charname")."' AND id<>'$id' LIMIT 1", "users");
        if (mysqli_num_rows($outro) > 0) { $erros .= t("Já existe outro personagem com esse nome.")."<br />"; }
        if ($id == $userrow["id"] && pegarint($_POST, "authlevel") != 1) { $erros .= t("Você não pode tirar o seu próprio acesso de administrador.")."<br />"; }
        $novasenha = pegartexto($_POST, "novasenha");
        if ($novasenha != "") {
            if (strlen($novasenha) < 6) { $erros .= t("A nova senha deve ter pelo menos 6 caracteres.")."<br />"; }
            else { $sets[] = "password='".addslashes(senha_hash($novasenha))."'"; $sets[] = "trocarsenha='1'"; } // senha dada pelo admin é provisória: troca obrigatória no próximo login.
        }
        if ($erros == "") {
            doquery("UPDATE {{table}} SET ".implode(", ", $sets)." WHERE id='$id' LIMIT 1", "users");
            $mensagem = admmsg(t("Jogador atualizado.").($novasenha != "" ? " ".t("A senha foi trocada.") : ""));
            $row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "users"));
        } else {
            $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro");
        }
    }

    $r = $row;
    $opauth = admopcoes(array(0=>t("Jogador"), 1=>t("Administrador"), 2=>t("Banido")), $r["authlevel"]);
    $opacesso = admopcoes(array(0=>t("Nenhum"), 2=>t("Tutor (nome em laranja)"), 3=>t("GameMaster (painel gm.php, nome em azul)")), $r["acesso"]);
    $opdiff = admopcoes(array(1=>$controlrow["diff1name"], 2=>$controlrow["diff2name"], 3=>$controlrow["diff3name"]), $r["difficulty"]);
    $opclasse = admopcoes(array(0=>t("Sem especialização (Estudante)"), 1=>$controlrow["class1name"], 2=>$controlrow["class2name"], 3=>$controlrow["class3name"]), $r["charclass"]);
    $opacao = admopcoes(array("In Town"=>t("In Town (na vila)"), "Exploring"=>t("Exploring (explorando)"), "Fighting"=>t("Fighting (em batalha)")), $r["currentaction"]);
    $csrf = csrf_campo();
    $regdate = padraodate($r["regdate"]);
    $onlinetime = padraodate($r["onlinetime"]);
    $botaobanir = ($r["authlevel"] == 2) ? "<button type=\"submit\" name=\"acao\" value=\"desbanir\">".t("Remover banimento")."</button>" : "<button type=\"submit\" name=\"acao\" value=\"banir\" class=\"botaoperigo\" onclick=\"return confirm('".t("Banir este jogador?")."');\">".t("Banir")."</button>";

$t = "t"; $tf = "tf";
$page = <<<END
<h1>{$tf("Jogador: %s", $r["charname"])}</h1>
$mensagem
<form action="admin.php?do=edituser:$id" method="post">$csrf
<button type="submit" name="acao" value="vila">{$t("Mandar para a vila / destravar")}</button>
<button type="submit" name="acao" value="curar">{$t("Restaurar HP/Chakra/TP")}</button>
$botaobanir
</form>
<h2>{$t("Ficha completa")}</h2>
<form action="admin.php?do=edituser:$id" method="post">$csrf
<table width="100%">
<tr><td width="220">{$t("ID / Conta:")}</td><td>{$r["id"]} / <b>{$r["username"]}</b></td></tr>
<tr><td>{$t("Cadastro / último acesso:")}</td><td>$regdate / $onlinetime &nbsp; <span class="small">IP: {$r["ipadress"]}</span></td></tr>
<tr><td>{$t("E-mail:")}</td><td><input type="text" name="email" size="35" maxlength="100" value="{$r["email"]}" /></td></tr>
<tr><td>{$t("Código de verificação:")}</td><td><input type="text" name="verify" size="10" maxlength="8" value="{$r["verify"]}" /> <span class="small">{$t("1 = e-mail verificado.")}</span></td></tr>
<tr><td>{$t("Nova senha:")}</td><td><input type="password" name="novasenha" size="20" autocomplete="new-password" /> <span class="small">{$t("Deixe vazio para manter a atual.")}</span></td></tr>
<tr><td>{$t("Personagem:")}</td><td><input type="text" name="charname" size="30" maxlength="30" value="{$r["charname"]}" /></td></tr>
<tr><td>{$t("Nível de acesso:")}</td><td><select name="authlevel">$opauth</select> {$t("Cargo:")} <select name="acesso">$opacesso</select></td></tr>
<tr><td>{$t("Especialização / dificuldade:")}</td><td><select name="charclass">$opclasse</select> <select name="difficulty">$opdiff</select></td></tr>
<tr><td>{$t("Vila (alinhamento) / graduação:")}</td><td><input type="text" name="alinhamento" size="10" maxlength="10" value="{$r["alinhamento"]}" /> <input type="text" name="graduacao" size="25" maxlength="30" value="{$r["graduacao"]}" /> <span class="small">{$t("Código do alinhamento de uma vila natal (veja Vilas).")}</span></td></tr>
</table>
<h2>{$t("Posição e batalha")}</h2>
<table width="100%">
<tr><td width="220">{$t("Latitude / longitude:")}</td><td><input type="text" name="latitude" size="6" value="{$r["latitude"]}" /> <input type="text" name="longitude" size="6" value="{$r["longitude"]}" /></td></tr>
<tr><td>{$t("Ação atual:")}</td><td><select name="currentaction">$opacao</select></td></tr>
<tr><td>{$t("Turno / inimigo / HP do inimigo:")}</td><td><input type="text" name="currentfight" size="4" value="{$r["currentfight"]}" /> <input type="text" name="currentmonster" size="5" value="{$r["currentmonster"]}" /> <input type="text" name="currentmonsterhp" size="6" value="{$r["currentmonsterhp"]}" /></td></tr>
<tr><td>{$t("Sono / imunidade do inimigo:")}</td><td><input type="text" name="currentmonstersleep" size="4" value="{$r["currentmonstersleep"]}" /> <input type="text" name="currentmonsterimmune" size="4" value="{$r["currentmonsterimmune"]}" /></td></tr>
<tr><td>{$t("Bônus de dano / defesa (%):")}</td><td><input type="text" name="currentuberdamage" size="4" value="{$r["currentuberdamage"]}" /> <input type="text" name="currentuberdefense" size="4" value="{$r["currentuberdefense"]}" /></td></tr>
<tr><td>{$t("Drop pendente (ID):")}</td><td><input type="text" name="dropcode" size="6" value="{$r["dropcode"]}" /></td></tr>
</table>
<h2>{$t("Status")}</h2>
<table width="100%">
<tr><td width="220">{$t("HP atual / máximo:")}</td><td><input type="text" name="currenthp" size="6" value="{$r["currenthp"]}" /> / <input type="text" name="maxhp" size="6" value="{$r["maxhp"]}" /></td></tr>
<tr><td>{$t("Chakra atual / máximo:")}</td><td><input type="text" name="currentmp" size="6" value="{$r["currentmp"]}" /> / <input type="text" name="maxmp" size="6" value="{$r["maxmp"]}" /></td></tr>
<tr><td>{$t("TP atual / máximo:")}</td><td><input type="text" name="currenttp" size="6" value="{$r["currenttp"]}" /> / <input type="text" name="maxtp" size="6" value="{$r["maxtp"]}" /></td></tr>
<tr><td>{$t("NP atual / máximo:")}</td><td><input type="text" name="currentnp" size="6" value="{$r["currentnp"]}" /> / <input type="text" name="maxnp" size="6" value="{$r["maxnp"]}" /></td></tr>
<tr><td>{$t("EP atual / máximo:")}</td><td><input type="text" name="currentep" size="6" value="{$r["currentep"]}" /> / <input type="text" name="maxep" size="6" value="{$r["maxep"]}" /></td></tr>
<tr><td>{$t("Nível / experiência:")}</td><td><input type="text" name="level" size="5" value="{$r["level"]}" /> <input type="text" name="experience" size="10" value="{$r["experience"]}" /></td></tr>
<tr><td>{$t("Ryou / Ryou no banco:")}</td><td><input type="text" name="gold" size="10" value="{$r["gold"]}" /> <input type="text" name="banco_grana" size="10" value="{$r["banco_grana"]}" /></td></tr>
<tr><td>{$t("Bônus de Ryou / experiência (%):")}</td><td><input type="text" name="goldbonus" size="5" value="{$r["goldbonus"]}" /> <input type="text" name="expbonus" size="5" value="{$r["expbonus"]}" /></td></tr>
<tr><td>{$t("Força / destreza:")}</td><td><input type="text" name="strength" size="5" value="{$r["strength"]}" /> <input type="text" name="dexterity" size="5" value="{$r["dexterity"]}" /></td></tr>
<tr><td>{$t("Poder de ataque / defesa:")}</td><td><input type="text" name="attackpower" size="5" value="{$r["attackpower"]}" /> <input type="text" name="defensepower" size="5" value="{$r["defensepower"]}" /></td></tr>
<tr><td>{$t("Agilidade / sorte / inteligência:")}</td><td><input type="text" name="agilidade" size="5" value="{$r["agilidade"]}" /> <input type="text" name="sorte" size="5" value="{$r["sorte"]}" /> <input type="text" name="inteligencia" size="5" value="{$r["inteligencia"]}" /></td></tr>
<tr><td>{$t("Precisão / determinação:")}</td><td><input type="text" name="precisao" size="5" value="{$r["precisao"]}" /> <input type="text" name="determinacao" size="5" value="{$r["determinacao"]}" /></td></tr>
<tr><td>{$t("Pontos a distribuir / chance de drop:")}</td><td><input type="text" name="pontoatributos" size="5" value="{$r["pontoatributos"]}" /> <input type="text" name="droprate" size="5" value="{$r["droprate"]}" /></td></tr>
<tr><td>{$t("Diamantes / pergaminhos:")}</td><td><input type="text" name="diamante" size="5" value="{$r["diamante"]}" /> <input type="text" name="pergaminho" size="5" value="{$r["pergaminho"]}" /></td></tr>
<tr><td>{$t("Elementos (natural,ataque):")}</td><td><input type="text" name="elemento" size="15" maxlength="15" value="{$r["elemento"]}" /></td></tr>
<tr><td>{$t("Avatar (número):")}</td><td><input type="text" name="avatar" size="4" value="{$r["avatar"]}" /></td></tr>
</table>
<h2>{$t("Equipamentos e itens")}</h2>
<table width="100%">
<tr><td width="220">{$t("Arma (ID / nome):")}</td><td><input type="text" name="weaponid" size="4" value="{$r["weaponid"]}" /> <input type="text" name="weaponname" size="30" value="{$r["weaponname"]}" /></td></tr>
<tr><td>{$t("Colete (ID / nome):")}</td><td><input type="text" name="armorid" size="4" value="{$r["armorid"]}" /> <input type="text" name="armorname" size="30" value="{$r["armorname"]}" /></td></tr>
<tr><td>{$t("Bandana (ID / nome):")}</td><td><input type="text" name="shieldid" size="4" value="{$r["shieldid"]}" /> <input type="text" name="shieldname" size="30" value="{$r["shieldname"]}" /></td></tr>
<tr><td>{$t("Slot 1 (ID / nome):")}</td><td><input type="text" name="slot1id" size="4" value="{$r["slot1id"]}" /> <input type="text" name="slot1name" size="30" value="{$r["slot1name"]}" /></td></tr>
<tr><td>{$t("Slot 2 (ID / nome):")}</td><td><input type="text" name="slot2id" size="4" value="{$r["slot2id"]}" /> <input type="text" name="slot2name" size="30" value="{$r["slot2name"]}" /></td></tr>
<tr><td>{$t("Slot 3 (ID / nome):")}</td><td><input type="text" name="slot3id" size="4" value="{$r["slot3id"]}" /> <input type="text" name="slot3name" size="30" value="{$r["slot3name"]}" /></td></tr>
<tr><td>{$t("Slot 4 (ID / nome):")}</td><td><input type="text" name="slot4id" size="4" value="{$r["slot4id"]}" /> <input type="text" name="slot4name" size="30" value="{$r["slot4name"]}" /></td></tr>
<tr><td>{$t("Durabilidade:")}</td><td><input type="text" name="durabilidade" size="24" maxlength="40" value="{$r["durabilidade"]}" /> <span class="small">{$t("Formato -,arma,colete,bandana,slot1,slot2,slot3,slot4 (X = infinita).")}</span></td></tr>
<tr><td>{$t("Mochila 1 a 8:")}</td><td><input type="text" name="bp1" size="28" value="{$r["bp1"]}" /> <input type="text" name="bp2" size="28" value="{$r["bp2"]}" /><br /><input type="text" name="bp3" size="28" value="{$r["bp3"]}" /> <input type="text" name="bp4" size="28" value="{$r["bp4"]}" /><br /><input type="text" name="bp5" size="28" value="{$r["bp5"]}" /> <input type="text" name="bp6" size="28" value="{$r["bp6"]}" /><br /><input type="text" name="bp7" size="28" value="{$r["bp7"]}" /> <input type="text" name="bp8" size="28" value="{$r["bp8"]}" /><br /><span class="small">{$t("Formato nome,id,tipo,durabilidade ou None.")}</span></td></tr>
<tr><td>{$t("Banco de itens:")}</td><td><textarea name="bancogeral" rows="2" cols="70">{$r["bancogeral"]}</textarea></td></tr>
<tr><td>{$t("Jutsus (IDs):")}</td><td><input type="text" name="spells" size="40" maxlength="255" value="{$r["spells"]}" /></td></tr>
<tr><td>{$t("Vilas conhecidas (IDs):")}</td><td><input type="text" name="towns" size="40" maxlength="255" value="{$r["towns"]}" /></td></tr>
<tr><td>{$t("Missão (etapa,...):")}</td><td><input type="text" name="missao" size="15" maxlength="20" value="{$r["missao"]}" /></td></tr>
<tr><td>{$t("Senjutsu:")}</td><td><input type="text" name="senjutsutimer" size="25" maxlength="30" value="{$r["senjutsutimer"]}" /> <span class="small">{$t("None = padrão.")}</span></td></tr>
</table>
<br /><input type="submit" name="submit" value="{$t("Salvar jogador")}" />
</form>
<h2>{$t("Apagar conta")}</h2>
<form action="admin.php?do=edituser:$id" method="post">$csrf
{$tf("Digite o nome do personagem (<b>%s</b>) para confirmar:", $r["charname"])} <input type="text" name="confirmar" size="20" />
<button type="submit" name="acao" value="apagar" class="botaoperigo" onclick="return confirm('{$t("Apagar definitivamente esta conta?")}');">{$t("Apagar conta")}</button>
</form>
END;

    admindisplay($page, tf("Jogador %s", $r["charname"]));

}


/* ------------------------------------------------------------------------
   Moderação: chat global, chat do mapa, fórum e bloqueios de login.
   ------------------------------------------------------------------------ */

function moderacao() {

    $mensagem = "";
    if (isset($_GET["acao"])) {
        admgetcsrf();
        $acao = pegartexto($_GET, "acao");
        $alvo = pegarint($_GET, "alvo");
        if ($acao == "apagarbabble") { doquery("DELETE FROM {{table}} WHERE id='$alvo' LIMIT 1", "babble"); $mensagem = admmsg(t("Mensagem apagada do chat global.")); }
        elseif ($acao == "limparbabble") { doquery("DELETE FROM {{table}}", "babble"); $mensagem = admmsg(t("Chat global limpo.")); }
        elseif ($acao == "apagarchatmap") { doquery("DELETE FROM {{table}} WHERE id='$alvo' LIMIT 1", "chatmap"); $mensagem = admmsg(t("Mensagem apagada do chat do mapa.")); }
        elseif ($acao == "limparchatmap") { doquery("DELETE FROM {{table}}", "chatmap"); $mensagem = admmsg(t("Chats do mapa limpos.")); }
        elseif ($acao == "apagartopico") { doquery("DELETE FROM {{table}} WHERE id='$alvo' OR parent='$alvo'", "forum"); $mensagem = admmsg(t("Tópico (e respostas) apagado do fórum.")); }
        elseif ($acao == "limparlogin") { doquery("DELETE FROM {{table}}", "loginfail"); $mensagem = admmsg(t("Bloqueios de login removidos.")); }
    }

    $page = "<h1>".t("Moderação")."</h1>$mensagem";

    $page .= "<h2>".t("Chat global (últimas 100 mensagens)")."</h2><a href=\"".admlinkcsrf("admin.php?do=moderacao&amp;acao=limparbabble")."\" onclick=\"return confirm('".t("Apagar TODO o chat global?")."');\">".t("Limpar todo o chat global")."</a><br /><br /><table class=\"lista\"><tr><th width=\"60\">".t("Hora")."</th><th width=\"120\">".t("Autor")."</th><th>".t("Mensagem")."</th><th width=\"60\"></th></tr>";
    $query = doquery("SELECT * FROM {{table}} ORDER BY id DESC LIMIT 100", "babble");
    while ($row = mysqli_fetch_array($query)) {
        $page .= "<tr><td>".$row["posttime"]."</td><td>".$row["author"]."</td><td>".$row["babble"]."</td><td><a href=\"".admlinkcsrf("admin.php?do=moderacao&amp;acao=apagarbabble&amp;alvo=".$row["id"])."\">".t("Apagar")."</a></td></tr>";
    }
    if (mysqli_num_rows($query) == 0) { $page .= "<tr><td colspan=\"4\">".t("Nenhuma mensagem.")."</td></tr>"; }
    $page .= "</table>";

    $page .= "<h2>".t("Chat do mapa (últimas 100 falas)")."</h2><a href=\"".admlinkcsrf("admin.php?do=moderacao&amp;acao=limparchatmap")."\" onclick=\"return confirm('".t("Apagar todas as falas dos mapas?")."');\">".t("Limpar todos os chats do mapa")."</a><br /><br /><table class=\"lista\"><tr><th width=\"90\">".t("Local")."</th><th width=\"120\">".t("Autor")."</th><th>".t("Fala")."</th><th width=\"60\"></th></tr>";
    $query = doquery("SELECT * FROM {{table}} ORDER BY id DESC LIMIT 100", "chatmap");
    while ($row = mysqli_fetch_array($query)) {
        $page .= "<tr><td>[".$row["latitude"].",".$row["longitude"]."]</td><td>".$row["name"]."</td><td>".$row["fala"]."</td><td><a href=\"".admlinkcsrf("admin.php?do=moderacao&amp;acao=apagarchatmap&amp;alvo=".$row["id"])."\">".t("Apagar")."</a></td></tr>";
    }
    if (mysqli_num_rows($query) == 0) { $page .= "<tr><td colspan=\"4\">".t("Nenhuma fala.")."</td></tr>"; }
    $page .= "</table>";

    $page .= "<h2>".t("Fórum (tópicos)")."</h2><table class=\"lista\"><tr><th width=\"110\">".t("Data")."</th><th width=\"120\">".t("Autor")."</th><th>".t("Título")."</th><th width=\"70\">".t("Respostas")."</th><th width=\"60\"></th></tr>";
    $query = doquery("SELECT * FROM {{table}} WHERE parent='0' ORDER BY newpostdate DESC LIMIT 100", "forum");
    while ($row = mysqli_fetch_array($query)) {
        $page .= "<tr><td>".padraodate($row["postdate"])."</td><td>".$row["author"]."</td><td><a href=\"forum.php?do=thread:".$row["id"].":0\" target=\"_blank\">".$row["title"]."</a></td><td>".$row["replies"]."</td><td><a href=\"".admlinkcsrf("admin.php?do=moderacao&amp;acao=apagartopico&amp;alvo=".$row["id"])."\" onclick=\"return confirm('".t("Apagar o tópico e todas as respostas?")."');\">".t("Apagar")."</a></td></tr>";
    }
    if (mysqli_num_rows($query) == 0) { $page .= "<tr><td colspan=\"5\">".t("Nenhum tópico.")."</td></tr>"; }
    $page .= "</table>";

    $page .= "<h2>".t("Bloqueios de login (força bruta)")."</h2><p>".t("IPs com 5 ou mais senhas erradas ficam 15 minutos sem conseguir entrar.")."</p><table class=\"lista\"><tr><th>".t("IP")."</th><th>".t("Tentativas")."</th><th>".t("Última")."</th></tr>";
    $query = doquery("SELECT * FROM {{table}} ORDER BY ultima DESC LIMIT 100", "loginfail");
    while ($row = mysqli_fetch_array($query)) { $page .= "<tr><td>".htmlspecialchars($row["ip"])."</td><td>".$row["tentativas"]."</td><td>".padraodate($row["ultima"])."</td></tr>"; }
    if (mysqli_num_rows($query) == 0) { $page .= "<tr><td colspan=\"3\">".t("Nenhum bloqueio.")."</td></tr>"; }
    $page .= "</table><br /><a href=\"".admlinkcsrf("admin.php?do=moderacao&amp;acao=limparlogin")."\">".t("Remover todos os bloqueios")."</a>";

    admindisplay($page, t("Moderação"));

}


/* ------------------------------------------------------------------------
   Registro de auditoria (tabela adminlog): toda ação dos painéis admin/GM, entradas no painel e senhas erradas.
   ------------------------------------------------------------------------ */

function auditoria() {

    $autor = pegartexto($_GET, "autor");
    $acao = pegartexto($_GET, "acao");
    $alvo = pegarint($_GET, "alvo");
    $cargo = pegartexto($_GET, "cargo"); if (!in_array($cargo, array("", "adm", "gm"), true)) { $cargo = ""; }
    $dias = pegarint($_GET, "dias", 30); if ($dias < 1 || $dias > 3650) { $dias = 30; }
    $pagina = max(1, pegarint($_GET, "pagina", 1));
    $porpagina = 100;

    $onde = "quando >= (NOW() - INTERVAL $dias DAY)";
    if ($autor != "") { $onde .= " AND autor LIKE '%$autor%'"; }
    if ($acao != "") { $onde .= " AND acao LIKE '%$acao%'"; }
    if ($alvo > 0) { $onde .= " AND alvo_id='$alvo'"; }
    if ($cargo != "") { $onde .= " AND cargo='$cargo'"; }
    $total = admcontar("adminlog", $onde);
    $paginas = max(1, ceil($total / $porpagina));
    if ($pagina > $paginas) { $pagina = $paginas; }
    $inicio = ($pagina - 1) * $porpagina;
    $opcargo = admopcoes(array(""=>t("Todos"), "adm"=>t("Administradores"), "gm"=>"GameMasters"), $cargo);

$t = "t"; $tf = "tf";
$page = <<<END
<h1>{$t("Registro de auditoria")}</h1>
<p>{$t("Tudo o que a equipe faz nos painéis (admin.php, admin_missoes.php e gm.php): entradas no painel, senhas erradas, ações confirmadas com senha e cada formulário enviado. As senhas nunca são gravadas.")}</p>
<form action="admin.php" method="get"><input type="hidden" name="do" value="auditoria" />
{$t("Autor:")} <input type="text" name="autor" value="$autor" size="14" /> {$t("Ação:")} <input type="text" name="acao" value="$acao" size="14" /> {$t("ID do alvo:")} <input type="number" name="alvo" value="{$alvo}" style="width:70px" />
{$t("Cargo:")} <select name="cargo">$opcargo</select> {$t("Últimos")} <input type="number" name="dias" value="$dias" style="width:60px" /> {$t("dias")} <input type="submit" value="{$t("Filtrar")}" />
</form><br />
<b>$total</b> {$t("registro(s).")}<br /><br />
<table class="lista"><tr><th width="120">{$t("Quando")}</th><th>{$t("Quem")}</th><th>{$t("IP")}</th><th>{$t("Ação")}</th><th>{$t("Alvo")}</th><th>{$t("Detalhes")}</th></tr>
END;
    $query = doquery("SELECT * FROM {{table}} WHERE $onde ORDER BY id DESC LIMIT $inicio, $porpagina", "adminlog");
    while ($row = mysqli_fetch_array($query)) {
        $quem = htmlspecialchars($row["autor"], ENT_QUOTES, 'UTF-8').($row["cargo"] == "gm" ? " ".selogm() : "");
        $alvotexto = (intval($row["alvo_id"]) > 0) ? "<a href=\"admin.php?do=auditoria&amp;alvo=".intval($row["alvo_id"])."\">".intval($row["alvo_id"])."</a>" : "";
        $page .= "<tr><td>".padraodate($row["quando"])."</td><td>$quem</td><td class=\"small\">".htmlspecialchars($row["ip"], ENT_QUOTES, 'UTF-8')."</td><td><b>".htmlspecialchars($row["acao"], ENT_QUOTES, 'UTF-8')."</b></td><td>$alvotexto</td><td class=\"small\">".htmlspecialchars($row["detalhes"], ENT_QUOTES, 'UTF-8')."</td></tr>";
    }
    if ($total == 0) { $page .= "<tr><td colspan=\"6\">".t("Nenhum registro.")."</td></tr>"; }
    $page .= "</table><br />".t("Página:")." ";
    for ($p = 1; $p <= $paginas && $p <= 50; $p++) {
        $page .= ($p == $pagina) ? "<b>[$p]</b> " : "<a href=\"admin.php?do=auditoria&amp;autor=".urlencode(html_entity_decode($autor))."&amp;acao=".urlencode(html_entity_decode($acao))."&amp;alvo=$alvo&amp;cargo=$cargo&amp;dias=$dias&amp;pagina=$p\">$p</a> ";
    }
    admindisplay($page, t("Registro de auditoria"));

}


/* ------------------------------------------------------------------------
   Variantes raras dos itens (tabela variantes, lida por funcoesinclusas.php :: variantesitens()).
   Os 6 efeitos são fixos (cada um tem ícones próprios em layoutnovo/equipamentos/variantes/EFEITO/); aqui se muda
   o nome (pt/en), a cor, o bônus, o peso no sorteio e se a variante ainda pode ser sorteada.
   ------------------------------------------------------------------------ */

function caveiras() { // Jogadores com caveira (PvP) ativa, banidos com prazo; limpar a caveira ou banir na mão.

    global $link;
    $mensagem = "";
    if (isset($_POST["acao"])) {
        $alvo = pegarint($_POST, "alvo");
        $acao = pegartexto($_POST, "acao");
        if ($acao == "limpar") { doquery("UPDATE {{table}} SET caveira='', caveiraate='0' WHERE id='$alvo' LIMIT 1", "users"); $mensagem = admmsg(t("Caveira removida.")); }
        elseif ($acao == "banir") {
            $erros = "";
            $dias = admnumero("dias", t("Dias"), $erros, 1, 3650);
            doquery("UPDATE {{table}} SET authlevel='2', banidoate='".(time() + 86400 * max(1, intval($dias)))."' WHERE id='$alvo' AND authlevel='0' LIMIT 1", "users");
            $mensagem = admmsg(mysqli_affected_rows($link) ? tf("Jogador banido por %s dias.", intval($dias)) : t("Não foi possível banir (administrador ou já banido)."));
        }
    }
    $csrf = csrf_campo();
    $page = "<h1>".t("Caveiras (PvP)")."</h1>$mensagem<p>".t("Jogadores que mataram outros no PvP do mundo. As caveiras expiram sozinhas; banimentos automáticos têm prazo e acabam sozinhos no próximo acesso depois do prazo.")."</p>";
    $page .= "<table class=\"lista\"><tr><th>".t("Personagem")."</th><th>".t("Caveira")."</th><th>".t("Até")."</th><th>".t("Situação")."</th><th></th></tr>";
    $q = doquery("SELECT id,charname,caveira,caveiraate,authlevel,banidoate FROM {{table}} WHERE (caveira<>'' AND caveiraate > UNIX_TIMESTAMP()) OR (authlevel='2' AND banidoate > 0) ORDER BY caveiraate DESC LIMIT 200", "users");
    $n = 0;
    while ($r = mysqli_fetch_array($q)) {
        $n++;
        $sit = ($r["authlevel"] == 2) ? "<span class=\"highlight\">".tf("banido até %s", date("d-m-Y H:i", intval($r["banidoate"])))."</span>" : "";
        $page .= "<tr><td><a href=\"admin.php?do=edituser:".intval($r["id"])."\">".$r["charname"]."</a></td><td>".selocaveira($r).(caveiraativa($r) != "" ? t(caveiraativa($r) == "branca" ? "Caveira branca" : "Caveira vermelha") : "-")."</td><td>".(caveiraativa($r) != "" ? date("d-m-Y H:i", intval($r["caveiraate"])) : "")."</td><td>$sit</td>"
            ."<td><form action=\"admin.php?do=caveiras\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"alvo\" value=\"".intval($r["id"])."\" /><button type=\"submit\" name=\"acao\" value=\"limpar\">".t("Limpar caveira")."</button> <input type=\"number\" name=\"dias\" value=\"30\" min=\"1\" style=\"width:55px\" /> <button type=\"submit\" name=\"acao\" value=\"banir\" class=\"botaoperigo\">".t("Banir (dias)")."</button></form></td></tr>";
    }
    if ($n == 0) { $page .= "<tr><td colspan=\"5\">".t("Ninguém com caveira agora.")."</td></tr>"; }
    admindisplay($page."</table>", t("Caveiras (PvP)"));

}

/* ------------------------------------------------------------------------
   Receitas da alquimia (tabela receitas, usada por alquimia.php).
   ingredientes = JSON [{"tipo":"item"|"drop"|"loja","id":12,"qtd":1}, ...] (até 4); resultado_tipo item/drop/loja/variante/copia.
   ------------------------------------------------------------------------ */

function admopcoesingrediente($atual, $comvazio = true, $resultado = false) { // <option> de itens, drops e consumíveis da loja ("tipo:id"); no resultado também variantes e cópia.
    $html = $comvazio ? "<option value=\"\">-</option>" : "";
    $grupos = array("item" => array(t("Itens das lojas"), "items"), "drop" => array(t("Itens de drop"), "drops"));
    foreach ($grupos as $tipo => $g) {
        $html .= "<optgroup label=\"".$g[0]."\">";
        $q = doquery("SELECT id,name FROM {{table}} ORDER BY name", $g[1]);
        while ($r = mysqli_fetch_array($q)) { $v = $tipo.":".intval($r["id"]); $html .= "<option value=\"$v\"".admsel($atual, $v).">".$r["name"]." (#".intval($r["id"]).")</option>"; }
        $html .= "</optgroup>";
    }
    $html .= "<optgroup label=\"".t("Consumíveis da loja")."\">";
    foreach (consumiveisdaloja() as $cod => $c) { $html .= "<option value=\"loja:$cod\"".admsel($atual, "loja:".$cod).">".$c[0]."</option>"; }
    $html .= "</optgroup>";
    if ($resultado) {
        $html .= "<optgroup label=\"".t("Infusão de variante (item escolhido pelo jogador)")."\">";
        foreach (variantesitens() as $ef => $v) { $html .= "<option value=\"variante:$ef\"".admsel($atual, "variante:".$ef).">✦ ".$v["rotulo"]."</option>"; }
        $html .= "</optgroup><option value=\"copia:\"".admsel($atual, "copia:").">".t("Cópia de pergaminho de jutsu (escolhido pelo jogador)")."</option>";
    }
    return $html;
}

function admvalorvalido($valor, $resultado = false) { // Confere "tipo:id" contra o banco (lista branca). Devolve array(tipo, id) ou false.
    $p = explode(":", (string)$valor, 2);
    if (count($p) != 2) { return false; }
    if ($p[0] == "item" || $p[0] == "drop") {
        if (!ctype_digit($p[1])) { return false; }
        $r = mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE id='".intval($p[1])."' LIMIT 1", ($p[0] == "item") ? "items" : "drops"));
        return $r ? array($p[0], (string)intval($p[1])) : false;
    }
    if ($p[0] == "loja") { return isset(consumiveisdaloja()[$p[1]]) ? array("loja", $p[1]) : false; }
    if ($resultado && $p[0] == "variante") { return isset(variantesitens()[$p[1]]) ? array("variante", $p[1]) : false; }
    if ($resultado && $p[0] == "copia") { return array("copia", ""); }
    return false;
}

function admcategoriasreceita() { // Categorias (definem a aba da alquimia).
    return array("equipamento" => t("Equipamento (Receitas conhecidas)"), "material" => t("Material (Receitas conhecidas)"), "pergaminho" => t("Pergaminho (Receitas conhecidas)"), "refino" => t("Refino"), "pocao" => t("Poções"), "infusao" => t("Infusão"));
}

function receitas() {
    include_once('funcoesinclusas.php'); // consumiveisdaloja(), variantesitens(), jutsugraduacoes().
    $linhas = "";
    $q = doquery("SELECT * FROM {{table}} ORDER BY ordem, id", "receitas");
    $cats = admcategoriasreceita();
    while ($r = mysqli_fetch_array($q)) {
        $ings = array();
        foreach ((array)json_decode(html_entity_decode($r["ingredientes"], ENT_QUOTES, "UTF-8"), true) as $i) {
            if (!is_array($i) || !isset($i["tipo"], $i["id"])) { continue; }
            $nome = $i["tipo"].":".$i["id"];
            if ($i["tipo"] == "item" || $i["tipo"] == "drop") { $x = mysqli_fetch_array(doquery("SELECT name FROM {{table}} WHERE id='".intval($i["id"])."' LIMIT 1", ($i["tipo"] == "item") ? "items" : "drops")); if ($x) { $nome = $x["name"]; } }
            elseif (isset(consumiveisdaloja()[$i["id"]])) { $nome = consumiveisdaloja()[$i["id"]][0]; }
            $ings[] = intval($i["qtd"])."× ".$nome;
        }
        $estado = (intval($r["ativa"]) == 1 ? t("Ativa") : "<font color=red>".t("Desativada")."</font>").(intval($r["descoberta"]) == 1 ? " · ".t("Secreta") : "");
        $linhas .= "<tr><td>".intval($r["id"])."</td><td><a href=\"admin.php?do=editreceita:".intval($r["id"])."\">".$r["nome"]."</a></td><td>".(isset($cats[$r["categoria"]]) ? $cats[$r["categoria"]] : $r["categoria"])."</td><td class=\"small\">".implode("<br />", $ings)."</td><td>".$r["resultado_tipo"].":".$r["resultado_id"]." ×".intval($r["qtd"])."</td><td>".intval($r["chance_sucesso"])."%</td><td>".intval($r["custo"])."</td><td>".intval($r["nivel_minimo"])."</td><td>$estado</td></tr>";
    }
    $page = "<h1>".t("Receitas da alquimia")."</h1><p>".t("Receitas usadas na Alquimia (vilas com o serviço de alquimia). Secretas só aparecem depois de descobertas (livro de receitas ou experimentando a combinação). As regras gerais (perda na falha, experiência, crítico, custo das infusões) ficam em")." <a href=\"admin.php?do=main\">".t("Configurações gerais")."</a>.</p>"
        ."<p><a href=\"admin.php?do=editreceita:0\">".t("Adicionar receita")."</a></p><table class=\"lista\"><tr><th>ID</th><th>".t("Nome")."</th><th>".t("Categoria")."</th><th>".t("Ingredientes")."</th><th>".t("Resultado")."</th><th>".t("Chance")."</th><th>Ryou</th><th>".t("Nível")."</th><th>".t("Situação")."</th></tr>$linhas</table>";
    admindisplay($page, t("Receitas da alquimia"));
}

function editreceita($id) {
    global $controlrow;
    include_once('funcoesinclusas.php'); // consumiveisdaloja(), variantesitens(), jutsugraduacoes().
    $mensagem = "";
    $r = ($id > 0) ? mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "receitas"), MYSQLI_ASSOC) : false;
    if ($id > 0 && !$r) { admindisplay(admmsg(t("Receita não encontrada."), "erro"), t("Receitas da alquimia")); }
    if ($id > 0 && isset($_POST["apagar"])) {
        doquery("DELETE FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "receitas");
        admindisplay(admmsg(t("Receita apagada.")." <a href=\"admin.php?do=receitas\">".t("Voltar")."</a>"), t("Receitas da alquimia"));
    }
    if (!$r) { $r = array("id"=>0, "nome"=>"", "nome_en"=>"", "categoria"=>"equipamento", "ingredientes"=>"[]", "resultado_tipo"=>"item", "resultado_id"=>"", "qtd"=>1, "chance_sucesso"=>80, "custo"=>0, "nivel_minimo"=>1, "nivel_jogador"=>0, "classe"=>0, "graduacao"=>"", "descoberta"=>0, "livro_drop"=>0, "ativa"=>1, "ordem"=>0); }

    if (isset($_POST["submit"])) {
        $erros = "";
        $nome = admtexto("nome", t("Nome"), $erros, true, 60);
        $nomeen = admtexto("nome_en", t("Nome em inglês"), $erros, false, 60);
        $categoria = pegartexto($_POST, "categoria");
        if (!isset(admcategoriasreceita()[$categoria])) { $erros .= t("Escolha uma categoria válida.")."<br />"; $categoria = "equipamento"; }
        $ings = array(); $vistos = array();
        for ($i = 1; $i <= 4; $i++) {
            $v = pegartexto($_POST, "ing".$i);
            if ($v == "") { continue; }
            $ok = admvalorvalido($v);
            if (!$ok) { $erros .= tf("Ingrediente %s inválido.", $i)."<br />"; continue; }
            if (isset($vistos[$v])) { $erros .= tf("Ingrediente %s repetido: some as quantidades numa linha só.", $i)."<br />"; continue; }
            $vistos[$v] = true;
            $qtd = admnumero("qtd".$i, tf("Quantidade do ingrediente %s", $i), $erros, 1, 99);
            $ings[] = array("tipo" => $ok[0], "id" => ($ok[0] == "loja") ? $ok[1] : intval($ok[1]), "qtd" => intval($qtd));
        }
        if (count($ings) == 0) { $erros .= t("A receita precisa de pelo menos um ingrediente.")."<br />"; }
        $res = admvalorvalido(pegartexto($_POST, "resultado"), true);
        if (!$res) { $erros .= t("Escolha um resultado válido.")."<br />"; $res = array("item", ""); }
        if (in_array($res[0], array("variante", "copia"), true) && isset($vistos[pegartexto($_POST, "resultado")])) { $erros .= t("Escolha um resultado válido.")."<br />"; }
        if ($res[0] == "variante" && $categoria != "infusao") { $erros .= t("Receitas de variante devem ter a categoria Infusão.")."<br />"; }
        $qtdres = admnumero("qtd", t("Quantidade produzida"), $erros, 1, 99);
        $chance = admnumero("chance_sucesso", t("Chance de sucesso"), $erros, 1, 100);
        $custo = admnumero("custo", t("Custo em Ryou"), $erros, 0, 16000000);
        $nivel = admnumero("nivel_minimo", t("Nível de alquimia"), $erros, 1, 30);
        $niveljog = admnumero("nivel_jogador", t("Nível do jogador"), $erros, 0, 1000);
        $classe = pegarint($_POST, "classe"); if ($classe < 0 || $classe > 3) { $classe = 0; }
        $grad = pegartexto($_POST, "graduacao"); if ($grad != "" && !in_array($grad, jutsugraduacoes(), true)) { $erros .= t("Graduação inválida.")."<br />"; $grad = ""; }
        $livro = pegarint($_POST, "livro_drop");
        if ($livro > 0 && !mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE id='".intval($livro)."' LIMIT 1", "drops"))) { $erros .= t("O livro de receitas precisa ser um drop existente.")."<br />"; }
        $ordem = admnumero("ordem", t("Ordem"), $erros, 0, 65000);
        $descoberta = (pegarint($_POST, "descoberta") == 1) ? 1 : 0;
        $ativa = (pegarint($_POST, "ativa") == 1) ? 1 : 0;
        if ($erros == "") {
            $json = addslashes(json_encode($ings, JSON_UNESCAPED_UNICODE));
            $campos = "nome='$nome', nome_en='$nomeen', categoria='".addslashes($categoria)."', ingredientes='$json', resultado_tipo='".addslashes($res[0])."', resultado_id='".addslashes($res[1])."', qtd='".intval($qtdres)."', chance_sucesso='".intval($chance)."', custo='".intval($custo)."', nivel_minimo='".intval($nivel)."', nivel_jogador='".intval($niveljog)."', classe='$classe', graduacao='".addslashes($grad)."', descoberta='$descoberta', livro_drop='".intval($livro)."', ativa='$ativa', ordem='".intval($ordem)."'";
            if ($id > 0) { doquery("UPDATE {{table}} SET $campos WHERE id='".intval($id)."' LIMIT 1", "receitas"); }
            else { doquery("INSERT INTO {{table}} SET $campos", "receitas"); global $link; $id = mysqli_insert_id($link); }
            $r = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "receitas"), MYSQLI_ASSOC);
            $mensagem = admmsg(t("Receita salva.")." <a href=\"admin.php?do=receitas\">".t("Voltar para a lista")."</a>");
        } else {
            $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro");
        }
    }

    $ingatual = (array)json_decode(html_entity_decode($r["ingredientes"], ENT_QUOTES, "UTF-8"), true);
    $linhasing = "";
    for ($i = 1; $i <= 4; $i++) {
        $a = isset($ingatual[$i-1]["tipo"]) ? $ingatual[$i-1]["tipo"].":".$ingatual[$i-1]["id"] : "";
        $q = isset($ingatual[$i-1]["qtd"]) ? intval($ingatual[$i-1]["qtd"]) : 1;
        $linhasing .= "<tr><td>".tf("Ingrediente %s:", $i)."</td><td><select name=\"ing$i\">".admopcoesingrediente($a)."</select> × <input type=\"number\" name=\"qtd$i\" min=\"1\" max=\"99\" value=\"$q\" style=\"width:55px\" /></td></tr>";
    }
    $opres = admopcoesingrediente($r["resultado_tipo"].":".$r["resultado_id"], false, true);
    $opcat = admopcoes(admcategoriasreceita(), $r["categoria"]);
    $opclasse = admopcoes(array(0=>t("Qualquer"), 1=>nomedaclasse(1), 2=>nomedaclasse(2), 3=>nomedaclasse(3)), $r["classe"]);
    $grads = array("" => t("Qualquer")); foreach (jutsugraduacoes() as $g) { if ($g != "") { $grads[$g] = t($g); } }
    $opgrad = admopcoes($grads, $r["graduacao"]);
    $opsec = admopcoes(array(0=>t("Não (visível para todos)"), 1=>t("Sim (escondida até ser descoberta)")), $r["descoberta"]);
    $opativa = admopcoes(array(1=>t("Sim"), 0=>t("Não")), $r["ativa"]);
    $csrf = csrf_campo();
    $apagar = ($id > 0) ? "<form action=\"admin.php?do=editreceita:".intval($id)."\" method=\"post\" onsubmit=\"return confirm('".t("Apagar esta receita?")."');\">$csrf<input type=\"submit\" name=\"apagar\" value=\"".t("Apagar receita")."\" /></form>" : "";
    $titulo = ($id > 0) ? tf("Editar receita #%s", intval($id)) : t("Adicionar receita");
    $t = "t";
    $page = <<<END
<h1>$titulo</h1>
$mensagem
<form action="admin.php?do=editreceita:{$id}" method="post">$csrf
<table width="100%">
<tr><td width="200">{$t("Nome (pt / en):")}</td><td><input type="text" name="nome" size="30" maxlength="60" value="{$r["nome"]}" /> <input type="text" name="nome_en" size="30" maxlength="60" value="{$r["nome_en"]}" /></td></tr>
<tr><td>{$t("Categoria (aba):")}</td><td><select name="categoria">$opcat</select></td></tr>
$linhasing
<tr><td>{$t("Resultado:")}</td><td><select name="resultado">$opres</select> × <input type="number" name="qtd" min="1" max="99" value="{$r["qtd"]}" style="width:55px" /><br /><span class="small">{$t("Itens das lojas saem com durabilidade infinita. Consumíveis e drops empilháveis entram em pilhas.")}</span></td></tr>
<tr><td>{$t("Chance de sucesso:")}</td><td><input type="number" name="chance_sucesso" min="1" max="100" value="{$r["chance_sucesso"]}" style="width:60px" /> % <span class="small">{$t("+3 por nível de alquimia acima do nível da receita (5% a 95%; 100 = nunca falha).")}</span></td></tr>
<tr><td>{$t("Custo em Ryou:")}</td><td><input type="number" name="custo" min="0" value="{$r["custo"]}" style="width:90px" /></td></tr>
<tr><td>{$t("Nível de alquimia:")}</td><td><input type="number" name="nivel_minimo" min="1" max="30" value="{$r["nivel_minimo"]}" style="width:60px" /> &nbsp; {$t("Nível do jogador:")} <input type="number" name="nivel_jogador" min="0" value="{$r["nivel_jogador"]}" style="width:60px" /></td></tr>
<tr><td>{$t("Especialização / graduação:")}</td><td><select name="classe">$opclasse</select> <select name="graduacao">$opgrad</select></td></tr>
<tr><td>{$t("Secreta:")}</td><td><select name="descoberta">$opsec</select> &nbsp; {$t("Livro que ensina (ID do drop):")} <input type="number" name="livro_drop" min="0" value="{$r["livro_drop"]}" style="width:70px" /></td></tr>
<tr><td>{$t("Ativa:")}</td><td><select name="ativa">$opativa</select> &nbsp; {$t("Ordem:")} <input type="number" name="ordem" min="0" value="{$r["ordem"]}" style="width:60px" /></td></tr>
</table>
<br /><input type="submit" name="submit" value="{$t("Salvar receita")}" />
</form><br />$apagar
END;
    admindisplay($page, t("Receitas da alquimia"));
}


function variantes() {

    $mensagem = "";
    if (isset($_POST["recomendados"])) { // Valores padrão da versão atual (os mesmos do tools/variantes_v2.sql do instalador).
        $padrao = variantesrecomendadas();
        $n = variantesreaplicar(function () use ($padrao) { foreach ($padrao as $e => $v) { doquery("UPDATE {{table}} SET bonus='".addslashes($v[0])."', percentual='".intval($v[1])."', pct_extra='".intval($v[2])."' WHERE efeito='".addslashes($e)."' LIMIT 1", "variantes"); } });
        $mensagem = admmsg(t("Valores recomendados aplicados.")." ".tf("Bônus atualizados em %s jogador(es) com itens raros equipados.", $n));
    }
    if (isset($_POST["submit"])) {
        $erros = "";
        $tab = mysqli_fetch_all(doquery("SELECT efeito FROM {{table}}", "variantes"), MYSQLI_ASSOC);
        $sets = array();
        foreach ($tab as $v) {
            $e = $v["efeito"];
            $nome = admtexto("nome_$e", tf("Nome (%s)", $e), $erros, true, 40);
            $nomeen = admtexto("nome_en_$e", tf("Nome em inglês (%s)", $e), $erros, false, 40);
            $cor = pegartexto($_POST, "cor_$e");
            if (!preg_match("/^#[0-9a-fA-F]{6}$/", $cor)) { $erros .= tf("Cor inválida (%s): use #RRGGBB.", $e)."<br />"; }
            $bonus = admcodigoespecial(pegartexto($_POST, "bonus_$e"), tf("Bônus (%s)", $e), $erros, true);
            if ($bonus == "X") { $bonus = ""; }
            $pct = admnumero("percentual_$e", tf("Percentual (%s)", $e), $erros, 0, 100);
            $pctextra = admnumero("pct_extra_$e", tf("Percentual extra (%s)", $e), $erros, 0, 100);
            $peso = admnumero("peso_$e", tf("Peso (%s)", $e), $erros, 0, 1000);
            $ativo = (pegarint($_POST, "ativo_$e") == 1) ? 1 : 0;
            $sets[] = "UPDATE {{table}} SET nome='$nome', nome_en='$nomeen', cor='".addslashes($cor)."', bonus='".addslashes($bonus)."', percentual='".intval($pct)."', pct_extra='".intval($pctextra)."', peso='".intval($peso)."', ativo='$ativo' WHERE efeito='".addslashes($e)."' LIMIT 1";
        }
        // variantesreaplicar(): tira o bônus antigo de quem está com itens raros equipados, salva e soma o novo (senão desequipar tiraria outro valor).
        if ($erros == "") { $n = variantesreaplicar(function () use ($sets) { foreach ($sets as $q) { doquery($q, "variantes"); } }); $mensagem = admmsg(t("Variantes salvas.")." ".tf("Bônus atualizados em %s jogador(es) com itens raros equipados.", $n)); }
        else { $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro"); }
    }
    $linhas = "";
    $q = doquery("SELECT * FROM {{table}} ORDER BY ordem, efeito", "variantes");
    while ($r = mysqli_fetch_array($q)) {
        $e = $r["efeito"];
        $icone = file_exists("layoutnovo/equipamentos/variantes/$e/1.gif") ? "<img src=\"layoutnovo/equipamentos/variantes/$e/1.gif\" width=\"34\" height=\"34\" alt=\"\" />" : "";
        $opativo = admopcoes(array(1=>t("Sim"), 0=>t("Não")), $r["ativo"]);
        $bonus = ($r["bonus"] == "") ? "" : $r["bonus"];
        $linhas .= "<tr><td>$icone<br /><b>$e</b></td><td><input type=\"text\" name=\"nome_$e\" size=\"12\" maxlength=\"40\" value=\"".$r["nome"]."\" /><br /><input type=\"text\" name=\"nome_en_$e\" size=\"12\" maxlength=\"40\" value=\"".$r["nome_en"]."\" /></td>"
            ."<td><input type=\"text\" name=\"cor_$e\" size=\"7\" maxlength=\"7\" value=\"".$r["cor"]."\" /> <span style=\"color:".$r["cor"].";font-weight:bold\">&#10022; ".$r["nome"]."</span></td>"
            ."<td><input type=\"text\" name=\"bonus_$e\" size=\"16\" maxlength=\"40\" value=\"$bonus\" placeholder=\"".t("vazio = percentual")."\" /></td>"
            ."<td><input type=\"number\" name=\"percentual_$e\" min=\"0\" max=\"100\" value=\"".intval($r["percentual"])."\" style=\"width:55px\" /> %</td>"
            ."<td><input type=\"number\" name=\"pct_extra_$e\" min=\"0\" max=\"100\" value=\"".intval(isset($r["pct_extra"]) ? $r["pct_extra"] : 0)."\" style=\"width:55px\" /> %</td>"
            ."<td><input type=\"number\" name=\"peso_$e\" min=\"0\" max=\"1000\" value=\"".intval($r["peso"])."\" style=\"width:60px\" /></td><td><select name=\"ativo_$e\">$opativo</select></td></tr>";
    }
    $csrf = csrf_campo();
    $page = "<h1>".t("Variantes raras")."</h1>$mensagem<p>".t("Itens de drop podem sair como variante rara (chance por inimigo em <a href=\"admin.php?do=monsters\">Inimigos</a>). Aqui você define o que cada variante faz. <b>Bônus</b> no formato atributo,valor (ex.: critico,2) é somado ao item; vazio = o item ganha o <b>percentual</b> do seu atributo principal (regra da Dourada). <b>Peso</b> = chance relativa no sorteio (0 ou desativada = não sai mais, mas os itens que já existem continuam valendo).")."</p>"
        ."<form action=\"admin.php?do=variantes\" method=\"post\">$csrf<table class=\"lista\"><tr><th>".t("Efeito")."</th><th>".t("Nome pt / en")."</th><th>".t("Cor")."</th><th>".t("Bônus")."</th><th>".t("Percentual")."</th><th>".t("% extra (com bônus fixo)")."</th><th>".t("Peso")."</th><th>".t("Ativa")."</th></tr>$linhas</table><br /><input type=\"submit\" name=\"submit\" value=\"".t("Salvar variantes")."\" /></form>";
    $page .= "<form action=\"admin.php?do=variantes\" method=\"post\" style=\"margin-top:10px\">".csrf_campo()."<input type=\"submit\" name=\"recomendados\" value=\"".t("Aplicar os valores recomendados")."\" onclick=\"return confirm('".t("Trocar bônus e percentuais de todas as variantes pelos valores recomendados?")."');\" /> <span class=\"small\">".t("Dourada +18% do atributo principal; as outras dão o bônus fixo mais um percentual diferente para cada uma.")."</span></form>";
    admindisplay($page, t("Variantes raras"));

}


/* ------------------------------------------------------------------------
   Manutenção.
   ------------------------------------------------------------------------ */

function manutencao() {

    global $link, $dbsettings;
    $mensagem = "";
    if (isset($_POST["acao"])) {
        $acao = pegartexto($_POST, "acao");
        if ($acao == "destravarbatalhas") {
            // Batalhas abandonadas: jogador offline há mais de 30 minutos ainda marcado como "Fighting".
            doquery("UPDATE {{table}} SET currentaction='Exploring', currentfight='0', currentmonster='0', currentmonsterhp='0', currentmonstersleep='0', currentmonsterimmune='0', currentuberdamage='0', currentuberdefense='0' WHERE currentaction='Fighting' AND onlinetime < (NOW() - INTERVAL 30 MINUTE)", "users");
            $mensagem = admmsg(tf("%s batalha(s) abandonada(s) encerrada(s).", mysqli_affected_rows($link)));
        } elseif ($acao == "resetarduelos") {
            // Duelos PvP (tabela duelos): devolve as apostas guardadas dos duelos ativos e cancela os ativos e pendentes.
            $px = $dbsettings["prefix"];
            doquery("UPDATE {$px}_users AS u JOIN {{table}} AS d ON (u.id=d.desafiante OR u.id=d.desafiado) SET u.gold=LEAST(16777215, u.gold+d.aposta) WHERE d.estado='ativo' AND d.aposta>0", "duelos");
            doquery("UPDATE {{table}} SET estado='cancelado', atualizado=UNIX_TIMESTAMP() WHERE estado IN ('pendente','ativo')", "duelos");
            doquery("UPDATE {{table}} SET batalha_nome='None', batalha_id='0', batalha_hp='0', batalha_timer='0', batalha_timer2='0', batalha_acao='None' WHERE batalha_timer2<>'0' OR batalha_nome<>'None'", "users");
            $mensagem = admmsg(tf("%s duelo(s) resetado(s).", mysqli_affected_rows($link)));
        } elseif ($acao == "cancelartrocas") {
            doquery("UPDATE {{table}} SET trocajogador1='None', trocajogador2='None', trocaswitch='0' WHERE trocajogador1<>'None' OR trocajogador2<>'None' OR trocaswitch<>'0'", "users");
            $mensagem = admmsg(tf("%s troca(s) pendente(s) cancelada(s).", mysqli_affected_rows($link)));
        } elseif ($acao == "apagarnaoverificados") {
            doquery("DELETE FROM {{table}} WHERE verify<>'1' AND authlevel<>'1' AND regdate < (NOW() - INTERVAL 7 DAY)", "users");
            $mensagem = admmsg(tf("%s conta(s) não verificada(s) apagada(s).", mysqli_affected_rows($link)));
        } elseif ($acao == "restaurartodos") {
            doquery("UPDATE {{table}} SET currenthp=maxhp, currentmp=maxmp, currenttp=maxtp WHERE currentaction<>'Fighting'", "users");
            $mensagem = admmsg(tf("HP, chakra e TP de %s jogador(es) restaurados.", mysqli_affected_rows($link)));
        }
    }

    // Tamanho das tabelas do jogo.
    global $dbsettings;
    $tabelas = "<table class=\"lista\"><tr><th>".t("Tabela")."</th><th>".t("Registros")."</th><th>".t("Tamanho")."</th></tr>";
    $query = mysqli_query($link, "SHOW TABLE STATUS LIKE '".addslashes($dbsettings["prefix"])."\\_%'");
    while ($query && $row = mysqli_fetch_array($query)) {
        $tabelas .= "<tr><td>".$row["Name"]."</td><td>".number_format((float)$row["Rows"])."</td><td>".number_format(($row["Data_length"] + $row["Index_length"]) / 1024, 1)." KB</td></tr>";
    }
    $tabelas .= "</table>";
    $versaophp = PHP_VERSION;
    $versaobanco = mysqli_get_server_info($link);
    $gd = function_exists("imagecreatefromstring") ? t("instalada") : t("<span class=\"highlight\">não instalada</span> (uploads de imagens precisam já estar no tamanho certo)");
    $csrf = csrf_campo();

$t = "t"; $tf = "tf";
$page = <<<END
<h1>{$t("Manutenção")}</h1>
$mensagem
<form action="admin.php?do=manutencao" method="post">$csrf
<table class="lista">
<tr><th>{$t("Ação")}</th><th>{$t("O que faz")}</th></tr>
<tr><td><button type="submit" name="acao" value="destravarbatalhas">{$t("Encerrar batalhas abandonadas")}</button></td><td>{$t("Jogadores marcados \"em batalha\" que estão offline há mais de 30 minutos voltam a explorar.")}</td></tr>
<tr><td><button type="submit" name="acao" value="resetarduelos">{$t("Resetar duelos")}</button></td><td>{$t("Cancela todos os duelos entre jogadores em andamento.")}</td></tr>
<tr><td><button type="submit" name="acao" value="cancelartrocas">{$t("Cancelar trocas pendentes")}</button></td><td>{$t("Desfaz propostas de troca de itens que ficaram presas.")}</td></tr>
<tr><td><button type="submit" name="acao" value="restaurartodos">{$t("Restaurar HP/Chakra/TP de todos")}</button></td><td>{$t("Útil depois de manutenção ou como evento.")}</td></tr>
<tr><td><button type="submit" name="acao" value="apagarnaoverificados" class="botaoperigo" onclick="return confirm('{$t("Apagar contas não verificadas há mais de 7 dias?")}');">{$t("Apagar contas não verificadas")}</button></td><td>{$t("Remove cadastros que nunca confirmaram o e-mail (mais de 7 dias).")}</td></tr>
</table>
</form>
<h2>{$t("Servidor")}</h2>
PHP $versaophp &nbsp;|&nbsp; {$t("Banco de dados")} $versaobanco &nbsp;|&nbsp; {$t("Extensão GD:")} $gd<br /><br />
$tabelas
END;
    admindisplay($page, t("Manutenção"));

}


/* ------------------------------------------------------------------------
   Inimigos (monstros) e suas cartas.
   Regra do jogo (fight.php): na coordenada [lat,long] a distância é d = maior entre |lat| e |long|;
   o nível máximo sorteado é floor((d+5)/5) e o mínimo é 2 abaixo. Assim um inimigo de nível N aparece
   com d entre 5N-5 e 5N+9. Região opcional (colunas regiao_*): retângulo de latitude/longitude; com
   regiao_exclusiva = 1 ele só aparece lá dentro, com 0 ele tem preferência lá dentro.
   ------------------------------------------------------------------------ */





function admfaixanivel($level) { // Faixa de distância do centro (maior entre |lat| e |long|) em que o nível aparece naturalmente.

    global $controlrow;
    $tamanho = intval($controlrow["gamesize"]);
    $minimo = max(0, 5 * intval($level) - 5);
    $maximo = 5 * intval($level) + 9;
    // O inimigo de nível mais alto também cobre as áreas mais distantes (o fight.php usa o nível mais alto disponível).
    $topo = mysqli_fetch_array(doquery("SELECT MAX(level) AS nivel FROM {{table}}", "monsters"));
    if (intval($level) >= intval($topo["nivel"])) { $maximo = $tamanho; }
    return array(min($minimo, $tamanho + 1), min($maximo, $tamanho));

}

function admregiaonafaixa($regiao, $faixa) { // A região toca a faixa de distância do nível? (senão um inimigo exclusivo nunca aparece)

    $minlat = ($regiao[0] <= 0 && $regiao[1] >= 0) ? 0 : min(abs($regiao[0]), abs($regiao[1]));
    $minlon = ($regiao[2] <= 0 && $regiao[3] >= 0) ? 0 : min(abs($regiao[2]), abs($regiao[3]));
    $dmin = max($minlat, $minlon);
    $dmax = max(abs($regiao[0]), abs($regiao[1]), abs($regiao[2]), abs($regiao[3]));
    return ($dmin <= $faixa[1] && $dmax >= $faixa[0]);

}





function monsters() {

    global $controlrow;
    $imune = array(0=>t("Nenhuma"), 1=>t("Dano"), 2=>t("Dano e sono"));
    $elementos = admelementos();
    $mensagem = isset($_GET["apagado"]) ? admmsg(t("Inimigo apagado.")) : "";

    // Duplicar: cria uma cópia (novo ID) com os mesmos dados e a mesma carta. O token vem no POST (conferido no topo).
    if (isset($_POST["duplicar"])) {
        $origem = pegarint($_POST, "duplicar"); include_once('funcoesjutsus.php'); // imunidadesdoinimigo(): antes só era incluída abaixo e duplicar dava Fatal error.
        $query = doquery("SELECT * FROM {{table}} WHERE id='$origem' LIMIT 1", "monsters");
        if (mysqli_num_rows($query) != 1) { $mensagem = admmsg(t("Inimigo não encontrado."), "erro"); }
        else {
            $r = mysqli_fetch_array($query);
            $nome = addslashes(mb_substr(tf("%s (cópia)", $r["name"]), 0, 50, 'UTF-8'));
            $regiao = admregiao($r);
            $camposregiao = ($regiao === null) ? "regiao_latmin=NULL, regiao_latmax=NULL, regiao_lonmin=NULL, regiao_lonmax=NULL, regiao_exclusiva='0'"
                : "regiao_latmin='".$regiao[0]."', regiao_latmax='".$regiao[1]."', regiao_lonmin='".$regiao[2]."', regiao_lonmax='".$regiao[3]."', regiao_exclusiva='".$regiao[4]."'";
            doquery("INSERT INTO {{table}} SET name='$nome', maxhp='".intval($r["maxhp"])."', maxdam='".intval($r["maxdam"])."', armor='".intval($r["armor"])."', level='".intval($r["level"])."', maxexp='".intval($r["maxexp"])."', maxgold='".intval($r["maxgold"])."', immune='".intval($r["immune"])."', imunidades='".addslashes(implode(",", imunidadesdoinimigo($r)))."', elemento='".addslashes($r["elemento"])."', raridade='".intval($r["raridade"])."', rouba='".intval($r["rouba"])."', aparecimento='".intval(isset($r["aparecimento"]) ? $r["aparecimento"] : 100)."', roubo_modo='".intval($r["roubo_modo"])."', efeito='".addslashes($r["efeito"])."', efeito_chance='".intval($r["efeito_chance"])."', terrenos='".addslashes($r["terrenos"])."', $camposregiao", "monsters");
            global $link; $novo = mysqli_insert_id($link);
            if (file_exists("layoutnovo/monstros/$origem.jpg") && is_writable("layoutnovo/monstros")) { @copy("layoutnovo/monstros/$origem.jpg", "layoutnovo/monstros/$novo.jpg"); }
            if (file_exists("layoutnovo/monstros/retratos/$origem.jpg") && is_writable("layoutnovo/monstros/retratos")) { @copy("layoutnovo/monstros/retratos/$origem.jpg", "layoutnovo/monstros/retratos/$novo.jpg"); }
            foreach (admdropsdoinimigo($origem) as $d) { doquery("INSERT INTO {{table}} SET monster_id='".intval($novo)."', drop_id='".$d[0]."', chance_percent='".$d[2]."', chance_variante_percent='".sprintf("%.2f", $d[3])."'", "monster_drops"); } // a cópia leva a tabela de drops.
            $mensagem = admmsg(tf("Cópia criada com o ID %s. <a href=\"admin.php?do=editmonster:%s\">Editar a cópia</a>", $novo, $novo));
        }
    }

    // Filtros (GET): faixa de nível, elemento e parte do nome.
    $nivelmin = pegartexto($_GET, "nivelmin"); $nivelmax = pegartexto($_GET, "nivelmax");
    $nivelmin = is_numeric($nivelmin) ? intval($nivelmin) : ""; $nivelmax = is_numeric($nivelmax) ? intval($nivelmax) : "";
    $elemento = pegartexto($_GET, "elemento"); if (!isset($elementos[$elemento])) { $elemento = ""; }
    $busca = pegartexto($_GET, "busca");
    // Terreno: "qualquer" = só os sem restrição; um nome = os que aparecem nesse terreno (restritos a ele ou sem restrição).
    include_once('funcoesterreno.php'); include_once('funcoesjutsus.php'); // imunidades dos inimigos.
    $opcoesterreno = array("qualquer"=>t("Sem restrição (qualquer terreno)"));
    foreach (nomesdosterrenos() as $nometer) { $opcoesterreno[$nometer] = nomeexibidoterreno($nometer); }
    $terreno = pegartexto($_GET, "terreno"); if (!isset($opcoesterreno[$terreno])) { $terreno = ""; }
    $onde = "1";
    if ($nivelmin !== "") { $onde .= " AND level>='$nivelmin'"; }
    if ($nivelmax !== "") { $onde .= " AND level<='$nivelmax'"; }
    if ($elemento != "") { $onde .= " AND elemento='$elemento'"; }
    if ($terreno == "qualquer") { $onde .= " AND terrenos=''"; }
    elseif ($terreno != "") { $onde .= " AND (terrenos='' OR FIND_IN_SET('$terreno', terrenos)>0)"; }
    if ($busca != "") { $onde .= " AND name LIKE '%".str_replace(array("%", "_"), array("\\%", "\\_"), $busca)."%'"; }
    $opelemento = "<option value=\"\">".t("Todos")."</option>".admopcoes($elementos, $elemento);

    $t = "t";
    $page = "<h1>".t("Inimigos e cartas")."</h1>$mensagem<p>".t("Os inimigos aparecem conforme a distância do centro do mapa: 1 nível a cada 5 casas (o jogo sorteia entre o nível da área e 2 níveis abaixo). A imagem de cada inimigo (usada na carta da luta) é <b>layoutnovo/monstros/retratos/ID.jpg</b>; envie ou troque na edição do inimigo.")." ".t("Cada inimigo também pode ter uma região do mapa (veja o <a href=\"admin.php?do=mapajogo\">Mapa do jogo</a>).")."</p>";
    $page .= "<a href=\"admin.php?do=editmonster:0\" class=\"botao\">".t("+ Adicionar inimigo")."</a><br /><br />";
    $page .= "<form action=\"admin.php\" method=\"get\"><input type=\"hidden\" name=\"do\" value=\"monsters\" />".t("Nível de")." <input type=\"number\" name=\"nivelmin\" value=\"$nivelmin\" style=\"width:60px\" /> ".t("até")." <input type=\"number\" name=\"nivelmax\" value=\"$nivelmax\" style=\"width:60px\" /> ".t("Elemento:")." <select name=\"elemento\">$opelemento</select> ".t("Terreno:")." <select name=\"terreno\"><option value=\"\">".t("Todos")."</option>".admopcoes($opcoesterreno, $terreno)."</select> ".t("Nome:")." <input type=\"text\" name=\"busca\" value=\"$busca\" size=\"20\" /> <input type=\"submit\" value=\"".t("Filtrar")."\" /> <a href=\"admin.php?do=monsters\">".t("Limpar filtros")."</a></form><br />";
    $page .= "<b>".admcontar("monsters", $onde)."</b> ".t("inimigo(s) encontrado(s).")."<br /><br />";
    $page .= "<table class=\"lista\"><tr><th>".t("ID")."</th><th>".t("Imagem")."</th><th>".t("Nome")."</th><th>".t("Nível")."</th><th>".t("HP")."</th><th>".t("Dano")."</th><th>".t("Defesa")."</th><th>".t("Exp.")."</th><th>".t("Ryou")."</th><th>".t("Imunidade")."</th><th>".t("Elemento")."</th><th>".t("Região")."</th><th>".t("Terreno")."</th><th title=\"".t("Chance de aparecer (peso)")."\">".t("Peso")."</th><th>".t("Drops")."</th><th></th></tr>";
    $csrf = csrf_campo();
    $dropsdetodos = admdropsdosinimigos($onde); // uma query para os drops de todos os inimigos listados (antes era uma por inimigo).
    $query = doquery("SELECT * FROM {{table}} WHERE $onde ORDER BY level, id", "monsters");
    while ($row = mysqli_fetch_array($query)) {
        $carta = admimagemmonstro(intval($row["id"]), 60);
        $regiao = admregiao($row);
        $textoregiao = ($regiao === null) ? "<span class=\"light\">".t("pelo nível")."</span>" : "<span class=\"small\">".admtextoregiao($regiao)."</span>";
        $textoterreno = textoterrenosdoinimigo($row["terrenos"]);
        $textoterreno = ($textoterreno == "") ? "<span class=\"light\">".t("qualquer")."</span>" : "<span class=\"small\">$textoterreno</span>";
        $dropsdele = isset($dropsdetodos[intval($row["id"])]) ? $dropsdetodos[intval($row["id"])] : array();
        $textodrops = count($dropsdele) ? tf("Pode dropar: %s", admtextodrops($dropsdele)) : "<span class=\"light\">".t("regra antiga")."</span>";
        $duplicar = "<form action=\"admin.php?do=monsters\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"duplicar\" value=\"".$row["id"]."\" /><input type=\"submit\" value=\"".t("Duplicar")."\" /></form>";
        $page .= "<tr><td>".$row["id"]."</td><td>$carta</td><td><a href=\"admin.php?do=editmonster:".$row["id"]."\">".$row["name"]."</a></td><td>".$row["level"]."</td><td>".$row["maxhp"]."</td><td>".$row["maxdam"]."</td><td>".$row["armor"]."</td><td>".$row["maxexp"]."</td><td>".$row["maxgold"]."</td><td>".(count(imunidadesdoinimigo($row)) ? "<span class=\"small\">".imunidadestexto(imunidadesdoinimigo($row), true, ", ")."</span>" : $imune[0])."</td><td>".(isset($elementos[$row["elemento"]]) ? $elementos[$row["elemento"]] : $row["elemento"])."</td><td>$textoregiao</td><td>$textoterreno</td><td class=\"small\">".intval($row["aparecimento"])."</td><td class=\"small\">$textodrops</td><td>$duplicar</td></tr>";
    }
    if (mysqli_num_rows($query) == 0) { $page .= "<tr><td colspan=\"16\">".t("Nenhum inimigo encontrado.")."</td></tr>"; }
    $page .= "</table>";
    admindisplay($page, t("Inimigos"));

}

function editmonster($id) {

    global $controlrow;
    $mensagem = "";
    $elementos = admelementos();
    $tamanho = intval($controlrow["gamesize"]);
    include_once('funcoesterreno.php'); include_once('funcoesjutsus.php'); // imunidades dos inimigos.
    $terrenosdomapa = nomesdosterrenos(); // nomes distintos da tabela terrenos (checkboxes "Aparece apenas nestes terrenos").
    $camposregiao = array("regiao_latmax"=>t("Latitude norte (máx.)"), "regiao_lonmin"=>t("Longitude oeste (mín.)"), "regiao_latmin"=>t("Latitude sul (mín.)"), "regiao_lonmax"=>t("Longitude leste (máx.)"));

    if (isset($_POST["apagar"]) && $id > 0) {
        // Quem estiver lutando contra esse inimigo volta a explorar.
        doquery("UPDATE {{table}} SET currentaction='Exploring', currentfight='0', currentmonster='0', currentmonsterhp='0' WHERE currentmonster='$id' AND currentaction='Fighting'", "users");
        doquery("DELETE FROM {{table}} WHERE id='$id' LIMIT 1", "monsters");
        doquery("DELETE FROM {{table}} WHERE monster_id='$id'", "monster_drops");
        if (file_exists("layoutnovo/monstros/$id.jpg") && is_writable("layoutnovo/monstros/$id.jpg")) { @unlink("layoutnovo/monstros/$id.jpg"); }
        if (file_exists("layoutnovo/monstros/retratos/$id.jpg") && is_writable("layoutnovo/monstros/retratos/$id.jpg")) { @unlink("layoutnovo/monstros/retratos/$id.jpg"); }
        header("Location: admin.php?do=monsters&apagado=1"); die();
    }

    $postado = null; // Valores digitados (para reexibir o formulário quando houver erro).
    if (isset($_POST["submit"])) {
        $erros = "";
        $name = admtexto("name", t("Nome"), $erros, true, 50);
        $maxhp = intval(admnumero("maxhp", t("HP máximo"), $erros, 1, 65535));
        $maxdam = intval(admnumero("maxdam", t("Dano máximo"), $erros, 0, 65535));
        $armor = intval(admnumero("armor", t("Defesa"), $erros, 0, 65535));
        $level = intval(admnumero("level", t("Nível"), $erros, 1, 65535));
        $maxexp = intval(admnumero("maxexp", t("Experiência máxima"), $erros, 0, 65535));
        $maxgold = intval(admnumero("maxgold", t("Ryou máximo"), $erros, 0, 65535));
        // Imunidades (checkboxes imunidades[]): lista canônica + a coluna antiga immune em sincronia (funcoesjutsus.php).
        $listaimune = imunidadesnormalizar(isset($_POST["imunidades"]) && is_array($_POST["imunidades"]) ? array_map("strval", $_POST["imunidades"]) : array());
        if (in_array("ninjutsu", $listaimune) && in_array("taijutsu", $listaimune)) { $erros .= t("Um inimigo não pode ser imune a Ninjutsu e a Taijutsu ao mesmo tempo: sempre precisa sobrar um jeito de causar dano.")."<br />"; }
        if (in_array("taijutsu", $listaimune) && in_array("ninjutsu", $listaimune)) { $erros .= t("Um inimigo não pode ser imune a Taijutsu e a Ninjutsu ao mesmo tempo (ficaria impossível de derrotar).")."<br />"; }
        $imunidades = addslashes(implode(",", $listaimune));
        $immune = imunidadeslegado($listaimune);
        $elemento = pegartexto($_POST, "elemento"); if (!isset($elementos[$elemento])) { $elemento = "neutro"; }
        $roubaryou = max(0, min(100, pegarint($_POST, "rouba_ryou"))); // roubo de Ryou (funcoesroubo.php), mesma escala do "rouba".
        $rouba = max(0, min(100, pegarint($_POST, "rouba"))); $roubomodo = (pegarint($_POST, "roubo_modo") == 2) ? 2 : 1; $aparecimento = max(0, min(1000, pegarint($_POST, "aparecimento"))); // roubo de itens (funcoesroubo.php); aparecimento = peso no sorteio (fight.php)
        $efeitomon = pegartexto($_POST, "efeito"); if (!in_array($efeitomon, array("", "veneno", "queimadura", "sono", "atordoar", "dreno"), true)) { $efeitomon = ""; }
        $efeitochance = ($efeitomon == "") ? 0 : max(0, min(100, pegarint($_POST, "efeito_chance")));
        $chancefuga = (trim(pegartexto($_POST, "chancefuga")) === "") ? -1 : max(0, min(100, pegarint($_POST, "chancefuga"))); // vazio = fórmula normal
        $raridade = intval(pegartexto($_POST, "raridade")); if ($raridade < 0 || $raridade > 3) { $raridade = 0; } // 0 comum, 1 elite, 2 raro, 3 chefe (moldura do cartão)

        // Região: os 4 campos vazios = sem região; senão os 4 são obrigatórios, dentro do mapa e com mínimo <= máximo.
        $preenchidos = 0; $reg = array();
        foreach ($camposregiao as $campo => $rotulo) { if (trim(pegartexto($_POST, $campo)) !== "") { $preenchidos++; } }
        $exclusiva = (pegarint($_POST, "regiao_exclusiva") == 1) ? 1 : 0;
        if ($preenchidos == 0) {
            $regiao = null;
            if ($exclusiva == 1) { $erros .= t("Para marcar \"apenas nesta região\", preencha os quatro limites da região.")."<br />"; }
        } elseif ($preenchidos < 4) {
            $regiao = null; $erros .= t("Preencha os quatro limites da região ou deixe todos vazios.")."<br />";
        } else {
            foreach ($camposregiao as $campo => $rotulo) { $reg[$campo] = intval(admnumero($campo, $rotulo, $erros, -$tamanho, $tamanho)); }
            if ($reg["regiao_latmin"] > $reg["regiao_latmax"]) { $erros .= t("A latitude sul deve ser menor ou igual à latitude norte.")."<br />"; }
            if ($reg["regiao_lonmin"] > $reg["regiao_lonmax"]) { $erros .= t("A longitude oeste deve ser menor ou igual à longitude leste.")."<br />"; }
            $regiao = array($reg["regiao_latmin"], $reg["regiao_latmax"], $reg["regiao_lonmin"], $reg["regiao_lonmax"], $exclusiva);
        }

        // Terrenos: checkboxes terrenos[]; só nomes que existem na tabela terrenos (lista branca). Nenhum = qualquer terreno.
        $terrenos = array();
        $terrenospostados = (isset($_POST["terrenos"]) && is_array($_POST["terrenos"])) ? $_POST["terrenos"] : array();
        foreach ($terrenospostados as $nometer) {
            if (is_array($nometer) || !in_array($nometer, $terrenosdomapa, true)) { $erros .= t("Terreno inválido.")."<br />"; continue; }
            if (!in_array($nometer, $terrenos, true)) { $terrenos[] = $nometer; }
        }
        if (count($terrenos) >= count($terrenosdomapa)) { $terrenos = array(); } // todos marcados = qualquer terreno.
        $terrenos = implode(",", $terrenos);

        // Tabela de drops: linhas dropid[] + chance[] (linha com drop vazio é ignorada = removida).
        $novosdrops = array();
        $dropids = (isset($_POST["dropid"]) && is_array($_POST["dropid"])) ? $_POST["dropid"] : array();
        $dropchances = (isset($_POST["dropchance"]) && is_array($_POST["dropchance"])) ? $_POST["dropchance"] : array();
        // dropvariante[]: % dos drops da linha que saem como variante rara (0 a 100; vazio = 0,5%).
        $dropvariantes = (isset($_POST["dropvariante"]) && is_array($_POST["dropvariante"])) ? $_POST["dropvariante"] : array();
        $novasvariantes = array();
        if (count($dropids) > 30) { $erros .= t("No máximo 30 drops por inimigo.")."<br />"; $dropids = array(); }
        foreach ($dropids as $k => $did) {
            $did = intval($did);
            if ($did <= 0) { continue; }
            $chance = isset($dropchances[$k]) && !is_array($dropchances[$k]) ? str_replace(",", ".", trim($dropchances[$k])) : "";
            if (mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE id='$did' LIMIT 1", "drops")) != 1) { $erros .= tf("O drop #%s não existe.", $did)."<br />"; continue; }
            if (!is_numeric($chance) || $chance < 0.01 || $chance > 100) { $erros .= tf("A chance do drop #%s deve ser um número de 0,01 a 100.", $did)."<br />"; continue; }
            if (isset($novosdrops[$did])) { $erros .= tf("O drop #%s aparece duas vezes na tabela.", $did)."<br />"; continue; }
            $variante = isset($dropvariantes[$k]) && !is_array($dropvariantes[$k]) ? str_replace(",", ".", trim($dropvariantes[$k])) : "";
            if ($variante === "") { $variante = "0.5"; }
            if (!is_numeric($variante) || $variante < 0 || $variante > 100) { $erros .= tf("A chance de variante rara do drop #%s deve ser um número de 0 a 100.", $did)."<br />"; continue; }
            $novosdrops[$did] = round($chance + 0, 2);
            $novasvariantes[$did] = round($variante + 0, 2);
        }

        if ($erros == "") {
            $sqlregiao = ($regiao === null) ? "regiao_latmin=NULL, regiao_latmax=NULL, regiao_lonmin=NULL, regiao_lonmax=NULL, regiao_exclusiva='0'"
                : "regiao_latmin='".$regiao[0]."', regiao_latmax='".$regiao[1]."', regiao_lonmin='".$regiao[2]."', regiao_lonmax='".$regiao[3]."', regiao_exclusiva='".$regiao[4]."'";
            $campos = "name='$name', maxhp='$maxhp', maxdam='$maxdam', armor='$armor', level='$level', maxexp='$maxexp', maxgold='$maxgold', immune='$immune', imunidades='$imunidades', elemento='$elemento', raridade='$raridade', rouba='$rouba', rouba_ryou='$roubaryou', roubo_modo='$roubomodo', aparecimento='$aparecimento', efeito='$efeitomon', efeito_chance='$efeitochance', chancefuga='$chancefuga', terrenos='$terrenos', $sqlregiao";
            if ($id == 0) {
                doquery("INSERT INTO {{table}} SET $campos", "monsters");
                global $link; $id = mysqli_insert_id($link);
                $mensagem = admmsg(tf("Inimigo criado com o ID %s.", $id));
            } else {
                doquery("UPDATE {{table}} SET $campos WHERE id='$id' LIMIT 1", "monsters");
                $mensagem = admmsg(t("Inimigo atualizado."));
            }
            doquery("DELETE FROM {{table}} WHERE monster_id='$id'", "monster_drops");
            foreach ($novosdrops as $did => $chance) { doquery("INSERT INTO {{table}} SET monster_id='$id', drop_id='$did', chance_percent='".sprintf("%.2f", $chance)."', chance_variante_percent='".sprintf("%.2f", $novasvariantes[$did])."'", "monster_drops"); }
            // A carta da luta (funcoescartas.php) usa o retrato layoutnovo/monstros/retratos/ID.jpg; a carta antiga (295x145) não é mais usada.
            $erroupload = admupload("carta", "layoutnovo/monstros/retratos/$id.jpg", 240, 240, "jpg");
            if ($erroupload != "") { $mensagem .= admmsg(tf("A imagem não foi salva: %s", $erroupload), "erro"); }
            elseif (isset($_FILES["carta"]) && $_FILES["carta"]["error"] == UPLOAD_ERR_OK) { $mensagem .= admmsg(t("Imagem enviada.")); }
        } else {
            $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro");
            $postado = array("name"=>stripslashes($name), "maxhp"=>pegartexto($_POST, "maxhp"), "maxdam"=>pegartexto($_POST, "maxdam"), "armor"=>pegartexto($_POST, "armor"), "level"=>pegartexto($_POST, "level"), "maxexp"=>pegartexto($_POST, "maxexp"), "maxgold"=>pegartexto($_POST, "maxgold"), "immune"=>$immune, "imunidades"=>implode(",", $listaimune), "elemento"=>$elemento, "raridade"=>$raridade, "rouba"=>$rouba, "rouba_ryou"=>$roubaryou, "roubo_modo"=>$roubomodo, "aparecimento"=>$aparecimento, "efeito"=>$efeitomon, "efeito_chance"=>$efeitochance, "chancefuga"=>$chancefuga, "regiao_exclusiva"=>$exclusiva, "terrenos"=>$terrenos);
            foreach ($camposregiao as $campo => $rotulo) { $postado[$campo] = pegartexto($_POST, $campo); }
            $dropspostados = array();
            foreach ($dropids as $k => $did) { if (intval($did) > 0) { $dropspostados[] = array(intval($did), "", isset($dropchances[$k]) && !is_array($dropchances[$k]) ? $dropchances[$k] : "", isset($dropvariantes[$k]) && !is_array($dropvariantes[$k]) ? $dropvariantes[$k] : ""); } }
        }
    }

    if ($id > 0) {
        $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "monsters");
        if (mysqli_num_rows($query) != 1) { admindisplay(admmsg(t("Inimigo não encontrado."), "erro"), t("Inimigos")); }
        $r = mysqli_fetch_array($query);
        $titulo = tf("Editar inimigo #%s", $id);
    } else {
        $r = array("name"=>"", "maxhp"=>10, "maxdam"=>5, "armor"=>2, "level"=>1, "maxexp"=>5, "maxgold"=>5, "immune"=>0, "imunidades"=>"", "elemento"=>"neutro", "raridade"=>0, "rouba"=>0, "rouba_ryou"=>0, "roubo_modo"=>1, "aparecimento"=>100, "efeito"=>"", "efeito_chance"=>0, "chancefuga"=>-1, "regiao_latmin"=>null, "regiao_latmax"=>null, "regiao_lonmin"=>null, "regiao_lonmax"=>null, "regiao_exclusiva"=>0, "terrenos"=>"");
        $titulo = t("Novo inimigo");
    }
    if ($postado !== null) { $r = array_merge($r, $postado); }

    // Tabela de drops do formulário: linhas atuais (ou as digitadas, se houve erro) + 2 linhas vazias.
    $linhasdrops = isset($dropspostados) ? $dropspostados : (($id > 0) ? admdropsdoinimigo($id) : array());
    $listadrops = array();
    $dq = doquery("SELECT id, name, mlevel FROM {{table}} ORDER BY mlevel, id", "drops");
    while ($drow = mysqli_fetch_array($dq)) { $listadrops[intval($drow["id"])] = $drow["name"]." (".tf("nível %s", $drow["mlevel"]).")"; }
    $linhasdrops[] = array(0, "", "", ""); $linhasdrops[] = array(0, "", "", "");
    $htmldrops = "";
    foreach ($linhasdrops as $d) {
        $op = "<option value=\"0\">".t("— nenhum —")."</option>".admopcoes($listadrops, $d[0]);
        $chance = ($d[0] > 0 && is_numeric($d[2])) ? floatval($d[2]) : $d[2];
        $variante = isset($d[3]) ? (($d[0] > 0 && is_numeric($d[3])) ? floatval($d[3]) : $d[3]) : "";
        $htmldrops .= "<tr><td><select name=\"dropid[]\">$op</select></td><td><input type=\"text\" name=\"dropchance[]\" size=\"6\" value=\"".htmlspecialchars((string)$chance, ENT_QUOTES, 'UTF-8')."\" /> %</td><td><input type=\"text\" name=\"dropvariante[]\" size=\"5\" value=\"".htmlspecialchars((string)$variante, ENT_QUOTES, 'UTF-8')."\" /> %</td><td><input type=\"button\" value=\"".t("Remover")."\" onclick=\"var l=this.parentNode.parentNode; l.getElementsByTagName('select')[0].value='0'; l.style.display='none';\" /></td></tr>";
    }
    $carta = admimagemmonstro($id, 180);
    $atuaisimune = imunidadesdoinimigo($r); $opimune = "";
    foreach (imunidadesvalidas() as $k => $rot) { $opimune .= "<label style=\"white-space:nowrap;margin-right:8px\"><input type=\"checkbox\" name=\"imunidades[]\" value=\"$k\"".(in_array($k, $atuaisimune) ? " checked=\"checked\"" : "")." /> ".t($rot[1])."</label> "; }
    $opelemento = admopcoes($elementos, $r["elemento"]);
    $opefeito = admopcoes(array(""=>t("Nenhum"), "veneno"=>t("Veneno"), "queimadura"=>t("Queimadura"), "sono"=>t("Sono"), "atordoar"=>t("Atordoar"), "dreno"=>t("Dreno de Chakra")), isset($r["efeito"]) ? (string)$r["efeito"] : "");
    $oproubomodo = admopcoes(array(1=>t("1 - só derrotando este inimigo nesta luta (senão perde)"), 2=>t("2 - vencendo qualquer inimigo igual a ele")), isset($r["roubo_modo"]) ? intval($r["roubo_modo"]) : 1);
    $vrouba = isset($r["rouba"]) ? intval($r["rouba"]) : 0; $vroubaryou = isset($r["rouba_ryou"]) ? intval($r["rouba_ryou"]) : 0; $vaparece = isset($r["aparecimento"]) ? intval($r["aparecimento"]) : 100; $vefch = isset($r["efeito_chance"]) ? intval($r["efeito_chance"]) : 0;
    $vfuga = (isset($r["chancefuga"]) && $r["chancefuga"] !== "" && intval($r["chancefuga"]) >= 0) ? intval($r["chancefuga"]) : "";
    $opraridade = admopcoes(array(0=>t("Comum"), 1=>t("Elite"), 2=>t("Raro"), 3=>t("Chefe")), intval($r["raridade"]));
    $csrf = csrf_campo();
    $botaoapagar = ($id > 0) ? "<form action=\"admin.php?do=editmonster:$id\" method=\"post\">$csrf<input type=\"submit\" name=\"apagar\" value=\"".t("Apagar este inimigo")."\" class=\"botaoperigo\" onclick=\"return confirm('".t("Apagar este inimigo? Missões que pedem esse inimigo pelo nome podem ficar impossíveis.")."');\" /></form>" : "";

    // "Onde aparece no mapa": faixa de distância do nível + região opcional, com mini mapa.
    $nivel = max(1, intval($r["level"]));
    $faixa = admfaixanivel($nivel);
    if ($faixa[0] > $tamanho) { $textofaixa = admmsg(tf("Com nível %s este inimigo fica além da borda do mapa (as casas vão até %s): ele só aparece se não houver inimigos mais próximos do nível da área.", $nivel, $tamanho), "aviso"); }
    else { $textofaixa = tf("Pelo nível (%s), aparece onde a maior distância do centro (maior entre |latitude| e |longitude|) está entre <b>%s</b> e <b>%s</b> casas.", $nivel, $faixa[0], $faixa[1]); }
    $regiaoatual = admregiao($r);
    if ($regiaoatual !== null && $postado === null) {
        $textofaixa .= "<br /><b>".admtextoregiao($regiaoatual)."</b>";
        if (!admregiaonafaixa($regiaoatual, $faixa)) {
            $textofaixa .= admmsg(($regiaoatual[4] == 1) ? t("A região não toca a faixa de distância do nível: com \"apenas nesta região\" marcado, este inimigo nunca vai aparecer. Mude a região ou o nível.") : t("A região não toca a faixa de distância do nível: a preferência pela região não terá efeito."), "aviso");
        }
    }
    $px = 250;
    $camadas = admretangulo(-$faixa[1], $faixa[1], -$faixa[1], $faixa[1], $px, "border:2px dashed #ffd400;");
    if ($faixa[0] > 0) { $camadas .= admretangulo(-$faixa[0] + 1, $faixa[0] - 1, -$faixa[0] + 1, $faixa[0] - 1, $px, "border:2px dashed #ffd400;"); }
    $prev = ($regiaoatual !== null) ? $regiaoatual : array(0, 0, 0, 0, 0);
    $camadas .= admretangulo($prev[0], $prev[1], $prev[2], $prev[3], $px, "background:rgba(220,0,0,0.35); border:2px solid #c00;".(($regiaoatual === null) ? " display:none;" : ""), "", "previaregiao");
    $minimapa = admmapa($px, $camadas);
    $vr = array();
    foreach ($camposregiao as $campo => $rotulo) { $vr[$campo] = ($r[$campo] === null) ? "" : $r[$campo]; }
    $marcado = ($r["regiao_exclusiva"] == 1) ? "checked=\"checked\"" : "";
    $terrenosdele = terrenosdoinimigo($r["terrenos"]);
    $checkterrenos = "";
    foreach ($terrenosdomapa as $nometer) {
        $checkterrenos .= "<label style=\"margin-right:12px; white-space:nowrap\"><input type=\"checkbox\" name=\"terrenos[]\" value=\"$nometer\"".(in_array($nometer, $terrenosdele, true) ? " checked=\"checked\"" : "")." /> ".nomeexibidoterreno($nometer)."</label> ";
    }
    $escala = $px / ($tamanho * 2 + 1);

$t = "t"; $tf = "tf";
$page = <<<END
<h1>$titulo</h1>
$mensagem
<form action="admin.php?do=editmonster:$id" method="post" enctype="multipart/form-data">$csrf
<table width="100%">
<tr><td width="200">{$t("Imagem do inimigo:")}</td><td>$carta</td></tr>
<tr><td>{$t("Enviar nova imagem:")}</td><td><input type="file" name="carta" accept="image/jpeg,image/png,image/gif" /><br /><span class="small">{$t("JPG/PNG/GIF. É a ilustração usada na carta da luta e na lista de inimigos; será ajustada para 240x240 (o centro da imagem é mantido).")}</span></td></tr>
<tr><td>{$t("Nome:")}</td><td><input type="text" name="name" size="35" maxlength="50" value="{$r["name"]}" /></td></tr>
<tr><td>{$t("Nível:")}</td><td><input type="text" name="level" size="5" value="{$r["level"]}" /> <span class="small">{$t("Define onde aparece (nível x 5 casas do centro) e quais drops pode soltar.")}</span></td></tr>
<tr><td>{$t("HP máximo:")}</td><td><input type="text" name="maxhp" size="6" value="{$r["maxhp"]}" /></td></tr>
<tr><td>{$t("Dano máximo:")}</td><td><input type="text" name="maxdam" size="6" value="{$r["maxdam"]}" /> <span class="small">{$t("Comparado com o poder de defesa do jogador.")}</span></td></tr>
<tr><td>{$t("Defesa:")}</td><td><input type="text" name="armor" size="6" value="{$r["armor"]}" /> <span class="small">{$t("Comparada com o poder de ataque do jogador.")}</span></td></tr>
<tr><td>{$t("Experiência máxima:")}</td><td><input type="text" name="maxexp" size="6" value="{$r["maxexp"]}" /></td></tr>
<tr><td>{$t("Ryou máximo:")}</td><td><input type="text" name="maxgold" size="6" value="{$r["maxgold"]}" /></td></tr>
<tr><td>{$t("Imunidade:")}</td><td>$opimune<br /><span class="small">{$t("Ninjutsu já inclui todos os elementos. Não marque Taijutsu e Ninjutsu juntos.")}</span></td></tr>
<tr><td>{$t("Raridade:")}</td><td><select name="raridade">$opraridade</select> <span class="small">{$t("Elite: moldura prateada; Raro: dourada; Chefe: roxa com coroa. A cor de fundo da moldura vem do elemento.")}</span></td></tr>
<tr><td>{$t("Rouba itens:")}</td><td><input type="number" name="rouba" min="0" max="100" value="{$vrouba}" style="width:55px" /> {$t("Recuperação:")} <select name="roubo_modo">$oproubomodo</select> <span class="small">{$t("0 = não rouba; 50 = chance base das Configurações gerais; 100 = o dobro.")}</span></td></tr>
<tr><td>{$t("Rouba Ryou:")}</td><td><input type="number" name="rouba_ryou" min="0" max="100" value="{$vroubaryou}" style="width:55px" /> <span class="small">{$t("0 = não rouba; 50 = chance base do roubo de Ryou (Configurações); 100 = o dobro. Usa a mesma Recuperação do roubo de itens.")}</span></td></tr>
<tr><td>{$t("Chance de aparecer (peso):")}</td><td><input type="number" name="aparecimento" min="0" max="1000" value="{$vaparece}" style="width:60px" /> <span class="small">{$t("100 = normal; 200 = o dobro; 0 = nunca")}</span></td></tr>
<tr><td>{$t("Efeito no jogador:")}</td><td><select name="efeito">$opefeito</select> {$t("chance")} <input type="number" name="efeito_chance" min="0" max="100" value="{$vefch}" style="width:55px" />% <span class="small">{$t("Cada ataque que acerta pode causar o efeito (dura poucos turnos e acaba no fim da luta). Sono/atordoar: o jogador perde o turno.")}</span></td></tr>
<tr><td>{$t("Chance de fuga:")}</td><td><input type="number" name="chancefuga" min="0" max="100" value="{$vfuga}" placeholder="auto" style="width:55px" />% <span class="small">{$t("Vazio = normal (depende da Destreza do jogador e do dano do inimigo). 0 = impede a fuga; 100 = a fuga sempre funciona. O Rinnegan foge de qualquer jeito.")}</span></td></tr>
<tr><td>{$t("Elemento:")}</td><td><select name="elemento">$opelemento</select> <span class="small">{$t("Água &gt; Fogo &gt; Vento &gt; Relâmpago &gt; Terra &gt; Água.")}</span></td></tr>
</table>
<h2>{$t("Onde aparece no mapa")}</h2>
<table width="100%"><tr><td>
<p>$textofaixa</p>
<p class="small">{$t("Região opcional: um retângulo de coordenadas. Deixe os quatro campos vazios para usar só a regra do nível. Dentro da região, os inimigos com região da faixa de nível da área são sorteados primeiro; se não houver nenhum, vale a lista normal.")}</p>
<table>
<tr><td>{$t("Latitude norte (máx.):")}</td><td><input type="number" name="regiao_latmax" id="regiao_latmax" min="-$tamanho" max="$tamanho" value="{$vr["regiao_latmax"]}" style="width:70px" /></td></tr>
<tr><td>{$t("Latitude sul (mín.):")}</td><td><input type="number" name="regiao_latmin" id="regiao_latmin" min="-$tamanho" max="$tamanho" value="{$vr["regiao_latmin"]}" style="width:70px" /></td></tr>
<tr><td>{$t("Longitude oeste (mín.):")}</td><td><input type="number" name="regiao_lonmin" id="regiao_lonmin" min="-$tamanho" max="$tamanho" value="{$vr["regiao_lonmin"]}" style="width:70px" /></td></tr>
<tr><td>{$t("Longitude leste (máx.):")}</td><td><input type="number" name="regiao_lonmax" id="regiao_lonmax" min="-$tamanho" max="$tamanho" value="{$vr["regiao_lonmax"]}" style="width:70px" /></td></tr>
<tr><td colspan="2"><label><input type="checkbox" name="regiao_exclusiva" value="1" $marcado /> {$t("apenas nesta região")}</label><br /><span class="small">{$t("Marcado: fora da região este inimigo nunca aparece. Desmarcado: aparece normalmente pelo nível e com preferência dentro da região.")}</span></td></tr>
</table>
<p><b>{$t("Aparece apenas nestes terrenos:")}</b><br />$checkterrenos<br /><span class="small">{$t("Nenhum marcado = aparece em qualquer terreno. O terreno é o da casa do jogador no mini mapa. Se nenhum inimigo da faixa de nível puder aparecer no terreno da casa, a restrição é ignorada ali.")}</span></p>
<span class="small">{$tf("O mapa vai de -%s a %s nas duas direções; latitude positiva é o norte (em cima) e longitude positiva é o leste (direita).", $tamanho, $tamanho)}</span>
</td><td width="260">
$minimapa
<span class="small">{$t("Tracejado: faixa do nível. Vermelho: região.")}</span>
</td></tr></table>
<script type="text/javascript">
// Prévia da região no mini mapa enquanto os campos são digitados.
(function () {
    var tam = $tamanho, esc = $escala, ids = ["regiao_latmin", "regiao_latmax", "regiao_lonmin", "regiao_lonmax"];
    function atualizar() {
        var v = {}, ok = true;
        for (var i = 0; i < ids.length; i++) { var s = document.getElementById(ids[i]).value; if (s === "" || isNaN(s)) { ok = false; } v[ids[i]] = parseInt(s, 10); }
        var d = document.getElementById("previaregiao");
        if (!ok || v.regiao_latmin > v.regiao_latmax || v.regiao_lonmin > v.regiao_lonmax) { d.style.display = "none"; return; }
        d.style.display = "block";
        d.style.left = Math.round((v.regiao_lonmin + tam) * esc) + "px";
        d.style.top = Math.round((tam - v.regiao_latmax) * esc) + "px";
        d.style.width = Math.max(2, Math.round((v.regiao_lonmax - v.regiao_lonmin + 1) * esc)) + "px";
        d.style.height = Math.max(2, Math.round((v.regiao_latmax - v.regiao_latmin + 1) * esc)) + "px";
    }
    for (var i = 0; i < ids.length; i++) { document.getElementById(ids[i]).oninput = atualizar; }
})();
</script>
<h2>{$t("Drops")}</h2>
<p class="small">{$t("Cada linha é sorteada separadamente na vitória com a chance indicada (0,01 a 100%). A Sorte e a Chance de Drop do jogador multiplicam as chances. Só cabe 1 drop por vitória: as linhas são testadas na ordem da lista (a ordem em que foram associadas: a de cima é a primeira) e vale a primeira que acertar. Sem nenhuma linha, vale a regra antiga (1 chance em 25, drop qualquer com nível mínimo menor ou igual ao nível do inimigo).")}</p>
<p class="small">{$t("Variante rara: % dos drops da linha que saem como versão rara do item (skin dourada, brilhante, elétrica, flamejante, congelada ou sombria, sorteada entre as 6, com um bônus pequeno a mais). Vazio = 0,5%; 0 = nunca. Poções e comidas nunca saem como variante.")}</p>
<table id="tabeladrops"><tr><th>{$t("Drop")}</th><th>{$t("Chance")}</th><th>{$t("Variante rara")}</th><th></th></tr>$htmldrops</table>
<input type="button" value="{$t("+ Adicionar linha")}" onclick="var tb=document.getElementById('tabeladrops'); var l=tb.rows[tb.rows.length-1].cloneNode(true); l.style.display=''; l.getElementsByTagName('select')[0].value='0'; l.getElementsByTagName('input')[0].value=''; l.getElementsByTagName('input')[1].value=''; tb.tBodies[0].appendChild(l);" />
<br /><input type="submit" name="submit" value="{$t("Salvar")}" /> <a href="admin.php?do=monsters">{$t("Voltar")}</a>
</form><br />
$botaoapagar
END;
    admindisplay($page, $titulo);

}


/* ------------------------------------------------------------------------
   Itens das lojas (armas, coletes e bandanas).
   ------------------------------------------------------------------------ */

function items() {

    $tipos = array(1=>t("Arma"), 2=>t("Colete"), 3=>t("Bandana/Chapéu"));
    $mensagem = isset($_GET["apagado"]) ? admmsg(t("Item apagado.")) : "";

    // Duplicar: cria uma cópia (novo ID) com os mesmos dados e o mesmo ícone. O token vem no POST (conferido no topo).
    if (isset($_POST["duplicar"])) {
        $origem = pegarint($_POST, "duplicar");
        $query = doquery("SELECT * FROM {{table}} WHERE id='$origem' LIMIT 1", "items");
        if (mysqli_num_rows($query) != 1) { $mensagem = admmsg(t("Item não encontrado."), "erro"); }
        else {
            $r = mysqli_fetch_array($query);
            $nome = addslashes(mb_substr($r["name"]." (cópia)", 0, 50, 'UTF-8')); // gravado em português (o nome do banco é a chave da tradução).
            doquery("INSERT INTO {{table}} SET name='$nome', type='".intval($r["type"])."', buycost='".intval($r["buycost"])."', attribute='".intval($r["attribute"])."', special='".addslashes($r["special"])."', special2='".addslashes($r["special2"])."', special3='".addslashes($r["special3"])."'", "items");
            global $link; $novo = mysqli_insert_id($link);
            if (file_exists("layoutnovo/equipamentos/$origem.gif") && is_writable("layoutnovo/equipamentos")) { @copy("layoutnovo/equipamentos/$origem.gif", "layoutnovo/equipamentos/$novo.gif"); }
            $mensagem = admmsg(tf("Cópia criada com o ID %s. <a href=\"admin.php?do=edititem:%s\">Editar a cópia</a>", $novo, $novo));
        }
    }

    // Filtros (GET): tipo, faixa de preço e parte do nome.
    $tipo = pegarint($_GET, "tipo"); if (!isset($tipos[$tipo])) { $tipo = 0; }
    $precomin = pegartexto($_GET, "precomin"); $precomax = pegartexto($_GET, "precomax");
    $precomin = is_numeric($precomin) ? intval($precomin) : ""; $precomax = is_numeric($precomax) ? intval($precomax) : "";
    $busca = pegartexto($_GET, "busca");
    $onde = "1";
    if ($tipo > 0) { $onde .= " AND type='$tipo'"; }
    if ($precomin !== "") { $onde .= " AND buycost>='$precomin'"; }
    if ($precomax !== "") { $onde .= " AND buycost<='$precomax'"; }
    if ($busca != "") { $onde .= " AND name LIKE '%".str_replace(array("%", "_"), array("\\%", "\\_"), $busca)."%'"; }
    $optipo = "<option value=\"0\">".t("Todos")."</option>".admopcoes($tipos, $tipo);

    $page = "<h1>".t("Itens das lojas")."</h1>$mensagem<p>".t("Para vender um item numa vila, coloque o ID dele na lista de itens da vila (<a href=\"admin.php?do=towns\">Vilas</a>). O ícone fica em <b>layoutnovo/equipamentos/ID.gif</b> (34x34).")."</p>";
    $page .= "<a href=\"admin.php?do=edititem:0\" class=\"botao\">".t("+ Adicionar item")."</a><br /><br />";
    $page .= "<form action=\"admin.php\" method=\"get\"><input type=\"hidden\" name=\"do\" value=\"items\" />".t("Tipo:")." <select name=\"tipo\">$optipo</select> ".t("Preço de")." <input type=\"number\" name=\"precomin\" value=\"$precomin\" style=\"width:80px\" /> ".t("até")." <input type=\"number\" name=\"precomax\" value=\"$precomax\" style=\"width:80px\" /> ".t("Nome:")." <input type=\"text\" name=\"busca\" value=\"$busca\" size=\"20\" /> <input type=\"submit\" value=\"".t("Filtrar")."\" /> <a href=\"admin.php?do=items\">".t("Limpar filtros")."</a></form><br />";
    $page .= "<b>".admcontar("items", $onde)."</b> ".t("item(ns) encontrado(s).")."<br /><br />";
    $page .= "<table class=\"lista\"><tr><th>".t("ID")."</th><th></th><th>".t("Nome")."</th><th>".t("Tipo")."</th><th>".t("Preço")."</th><th>".t("Atributo")."</th><th>".t("Especiais")."</th><th>".t("Vendido em")."</th><th></th></tr>";
    // Vilas que vendem cada item (itemslist = "id,id,id").
    $vendidoem = array();
    $tquery = doquery("SELECT id, name, itemslist FROM {{table}} ORDER BY id", "towns");
    while ($trow = mysqli_fetch_array($tquery)) {
        foreach (explode(",", (string)$trow["itemslist"]) as $iditem) { if (trim($iditem) !== "") { $vendidoem[intval($iditem)][] = $trow["name"]; } }
    }
    $csrf = csrf_campo();
    $query = doquery("SELECT * FROM {{table}} WHERE $onde ORDER BY type, buycost", "items");
    while ($row = mysqli_fetch_array($query)) {
        $icone = file_exists("layoutnovo/equipamentos/".$row["id"].".gif") ? "<img src=\"layoutnovo/equipamentos/".$row["id"].".gif\" width=\"34\" height=\"34\" alt=\"\" />" : "<span class=\"highlight small\">".t("sem ícone")."</span>";
        $especiais = array();
        foreach (array("special", "special2", "special3") as $c) { if ($row[$c] != "X" && $row[$c] != "") { $especiais[] = $row[$c]; } }
        $vilas = isset($vendidoem[intval($row["id"])]) ? implode(", ", $vendidoem[intval($row["id"])]) : "<span class=\"light\">".t("nenhuma")."</span>";
        $duplicar = "<form action=\"admin.php?do=items\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"duplicar\" value=\"".$row["id"]."\" /><input type=\"submit\" value=\"".t("Duplicar")."\" /></form>";
        $page .= "<tr><td>".$row["id"]."</td><td>$icone</td><td><a href=\"admin.php?do=edititem:".$row["id"]."\">".$row["name"]."</a></td><td>".(isset($tipos[$row["type"]]) ? $tipos[$row["type"]] : $row["type"])."</td><td>".number_format($row["buycost"])."</td><td>".$row["attribute"]."</td><td class=\"small\">".(count($especiais) ? implode("<br />", $especiais) : "X")."</td><td class=\"small\">$vilas</td><td>$duplicar</td></tr>";
    }
    if (mysqli_num_rows($query) == 0) { $page .= "<tr><td colspan=\"9\">".t("Nenhum item encontrado.")."</td></tr>"; }
    $page .= "</table>";
    admindisplay($page, t("Itens das lojas"));

}

function edititem($id) {

    $mensagem = "";
    if (isset($_POST["apagar"]) && $id > 0) {
        doquery("DELETE FROM {{table}} WHERE id='$id' LIMIT 1", "items");
        header("Location: admin.php?do=items&apagado=1"); die();
    }
    if (isset($_POST["submit"])) {
        $erros = "";
        $name = admtexto("name", t("Nome"), $erros, true, 50);
        $type = pegarint($_POST, "type"); if ($type < 1 || $type > 3) { $type = 1; }
        $buycost = intval(admnumero("buycost", t("Preço"), $erros, 0, 16777215));
        $attribute = intval(admnumero("attribute", t("Atributo"), $erros, 0, 65535));
        $special = admcodigoespecial(pegartexto($_POST, "special"), t("Especial 1"), $erros, true);
        $special2 = admcodigoespecial(pegartexto($_POST, "special2"), t("Especial 2"), $erros, true);
        $special3 = admcodigoespecial(pegartexto($_POST, "special3"), t("Especial 3"), $erros, true);
        admbonusrepetidos(array($special, $special2, $special3), $erros);
        $pilhavenda = admlerpilhavenda($erros);
        if ($erros == "") {
            $campos = "name='$name', type='$type', buycost='$buycost', attribute='$attribute', special='$special', special2='$special2', special3='$special3'".$pilhavenda;
            if ($id == 0) { doquery("INSERT INTO {{table}} SET $campos", "items"); global $link; $id = mysqli_insert_id($link); $mensagem = admmsg(tf("Item criado com o ID %s.", $id)); }
            else { doquery("UPDATE {{table}} SET $campos WHERE id='$id' LIMIT 1", "items"); $mensagem = admmsg(t("Item atualizado.")); }
            $erroupload = admupload("icone", "layoutnovo/equipamentos/$id.gif", 34, 34, "gif");
            if ($erroupload != "") { $mensagem .= admmsg(tf("O ícone não foi salvo: %s", $erroupload), "erro"); }
        } else {
            $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro");
            $postado = array("name"=>stripslashes($name), "type"=>$type, "buycost"=>pegartexto($_POST, "buycost"), "attribute"=>pegartexto($_POST, "attribute"), "special"=>pegartexto($_POST, "special"), "special2"=>pegartexto($_POST, "special2"), "special3"=>pegartexto($_POST, "special3"), "naoVendavel"=>pegarint($_POST, "naoVendavel"), "empilhavel"=>pegarint($_POST, "empilhavel"), "maxpilha"=>pegarint($_POST, "maxpilha", 20));
        }
    }

    if ($id > 0) {
        $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "items");
        if (mysqli_num_rows($query) != 1) { admindisplay(admmsg(t("Item não encontrado."), "erro"), t("Itens")); }
        $r = mysqli_fetch_array($query); $titulo = tf("Editar item #%s", $id);
    } else { $r = array("name"=>"", "type"=>1, "buycost"=>100, "attribute"=>5, "special"=>"X", "special2"=>"X", "special3"=>"X", "naoVendavel"=>0, "empilhavel"=>0, "maxpilha"=>20); $titulo = t("Novo item"); }
    if (isset($postado)) { $r = array_merge($r, $postado); }
    $linhaspilha = admcampospilhavenda($r);
    $optipo = admopcoes(array(1=>t("Arma (soma no poder de ataque)"), 2=>t("Colete (soma na defesa)"), 3=>t("Bandana/Chapéu (soma na defesa)")), $r["type"]);
    $icone = ($id > 0 && file_exists("layoutnovo/equipamentos/$id.gif")) ? "<img src=\"layoutnovo/equipamentos/$id.gif?".filemtime("layoutnovo/equipamentos/$id.gif")."\" alt=\"\" />" : "<span class=\"small\">".t("sem ícone")."</span>";
    $csrf = csrf_campo();
    $botaoapagar = ($id > 0) ? "<form action=\"admin.php?do=edititem:$id\" method=\"post\">$csrf<input type=\"submit\" name=\"apagar\" value=\"".t("Apagar este item")."\" class=\"botaoperigo\" onclick=\"return confirm('".t("Apagar este item? Jogadores que já o possuem continuam com ele, mas ele some das lojas.")."');\" /></form>" : "";

$t = "t"; $tf = "tf";
$page = <<<END
<h1>$titulo</h1>
$mensagem
<form action="admin.php?do=edititem:$id" method="post" enctype="multipart/form-data">$csrf
<table width="100%">
<tr><td width="200">{$t("Nome:")}</td><td><input type="text" name="name" size="35" maxlength="50" value="{$r["name"]}" /></td></tr>
<tr><td>{$t("Tipo:")}</td><td><select name="type">$optipo</select></td></tr>
<tr><td>{$t("Preço:")}</td><td><input type="text" name="buycost" size="8" value="{$r["buycost"]}" /> {$t("Ryou")}</td></tr>
<tr><td>{$t("Atributo:")}</td><td><input type="text" name="attribute" size="5" value="{$r["attribute"]}" /> <span class="small">{$t("Quanto soma no poder de ataque (armas) ou de defesa (coletes e bandanas).")}</span></td></tr>
<tr><td>{$t("Especial 1:")}</td><td><input type="text" name="special" size="25" maxlength="50" value="{$r["special"]}" /> <span class="small">{$t("X para nenhum ou <b>atributo,valor</b> (ex.: maxhp,50 ou expbonus,-10).")}</span></td></tr>
<tr><td>{$t("Especial 2:")}</td><td><input type="text" name="special2" size="25" maxlength="50" value="{$r["special2"]}" /> <span class="small">{$t("Opcional (X para nenhum).")}</span></td></tr>
<tr><td>{$t("Especial 3:")}</td><td><input type="text" name="special3" size="25" maxlength="50" value="{$r["special3"]}" /> <span class="small">{$t("Opcional (X para nenhum).")}</span></td></tr>
$linhaspilha
<tr><td>{$t("Ícone:")}</td><td>$icone <input type="file" name="icone" accept="image/*" /> <span class="small">{$t("Ajustado para 34x34 GIF.")}</span></td></tr>
</table>
<input type="submit" name="submit" value="{$t("Salvar")}" /> <a href="admin.php?do=items">{$t("Voltar")}</a>
</form><br />
$botaoapagar
<h2>{$t("Atributos aceitos no campo Especial")}</h2>
<span class="small">{$t("maxhp (HP máx.), maxmp (chakra máx.), maxtp (TP máx.), maxnp, maxep, goldbonus (% de Ryou), expbonus (% de experiência), strength (força, também soma no ataque), dexterity (destreza, também soma na defesa), attackpower, defensepower, sorte, agilidade, determinacao, precisao, inteligencia, droprate.")}</span><br />
<span class="small">{$t("Bônus de combate: critico (% extra de hit excelente, máx. 50), esquiva (% extra de desviar, máx. 40), regenhp (HP recuperado a cada passo no mapa), economiachakra (% a menos no custo dos jutsus, máx. 50).")}</span>
END;
    admindisplay($page, $titulo);

}


/* ------------------------------------------------------------------------
   Itens de drop (fragmentos, pedras, jóias...).
   ------------------------------------------------------------------------ */

function drops() {

    $mensagem = isset($_GET["apagado"]) ? admmsg(t("Drop apagado.")) : "";

    // Duplicar: cria uma cópia (novo ID) com os mesmos atributos e o mesmo ícone (sem entrar na tabela de drops dos inimigos).
    if (isset($_POST["duplicar"])) {
        $origem = pegarint($_POST, "duplicar");
        $query = doquery("SELECT * FROM {{table}} WHERE id='$origem' LIMIT 1", "drops");
        if (mysqli_num_rows($query) != 1) { $mensagem = admmsg(t("Drop não encontrado."), "erro"); }
        else {
            $r = mysqli_fetch_array($query);
            $nome = addslashes(mb_substr($r["name"]." (cópia)", 0, 50, 'UTF-8')); // gravado em português (o nome do banco é a chave da tradução).
            doquery("INSERT INTO {{table}} SET name='$nome', mlevel='".intval($r["mlevel"])."', type='1', attribute1='".addslashes($r["attribute1"])."', attribute2='".addslashes($r["attribute2"])."', attribute3='".addslashes($r["attribute3"])."', consumivel='".addslashes($r["consumivel"])."', ensina_jutsu='".intval($r["ensina_jutsu"])."'", "drops");
            global $link; $novo = mysqli_insert_id($link);
            if (file_exists("layoutnovo/equipamentos/drops/$origem.gif") && is_writable("layoutnovo/equipamentos/drops")) { @copy("layoutnovo/equipamentos/drops/$origem.gif", "layoutnovo/equipamentos/drops/$novo.gif"); }
            $mensagem = admmsg(tf("Cópia criada com o ID %s. <a href=\"admin.php?do=editdrop:%s\">Editar a cópia</a>", $novo, $novo));
        }
    }

    // Filtros (GET): faixa de nível mínimo, atributo e parte do nome.
    $nivelmin = pegartexto($_GET, "nivelmin"); $nivelmax = pegartexto($_GET, "nivelmax");
    $nivelmin = is_numeric($nivelmin) ? intval($nivelmin) : ""; $nivelmax = is_numeric($nivelmax) ? intval($nivelmax) : "";
    $atributo = pegartexto($_GET, "atributo"); if (!preg_match("/^[a-z]{0,20}$/", $atributo)) { $atributo = ""; }
    $busca = pegartexto($_GET, "busca");
    $onde = "1";
    if ($nivelmin !== "") { $onde .= " AND mlevel>='$nivelmin'"; }
    if ($nivelmax !== "") { $onde .= " AND mlevel<='$nivelmax'"; }
    if ($atributo != "") { $onde .= " AND (attribute1 LIKE '$atributo,%' OR attribute2 LIKE '$atributo,%' OR attribute3 LIKE '$atributo,%')"; }
    if ($busca != "") { $onde .= " AND name LIKE '%".str_replace(array("%", "_"), array("\\%", "\\_"), $busca)."%'"; }

    // Quantos inimigos têm cada drop na tabela de drops.
    $inimigos = array();
    $mq = doquery("SELECT drop_id, COUNT(*) AS total FROM {{table}} GROUP BY drop_id", "monster_drops");
    while ($mrow = mysqli_fetch_array($mq)) { $inimigos[intval($mrow["drop_id"])] = intval($mrow["total"]); }

    $page = "<h1>".t("Itens de drop")."</h1>$mensagem<p>".t("Cada inimigo tem a sua tabela de drops com a chance de cada item (edite em <a href=\"admin.php?do=monsters\">Inimigos</a>). Inimigo sem tabela usa a regra antiga: 1 chance em 25 de soltar um drop qualquer com nível mínimo menor ou igual ao nível dele. O ícone fica em <b>layoutnovo/equipamentos/drops/ID.gif</b> (34x34).")."</p>";
    $page .= "<a href=\"admin.php?do=editdrop:0\" class=\"botao\">".t("+ Adicionar drop")."</a><br /><br />";
    $page .= "<form action=\"admin.php\" method=\"get\"><input type=\"hidden\" name=\"do\" value=\"drops\" />".t("Nível mínimo de")." <input type=\"number\" name=\"nivelmin\" value=\"$nivelmin\" style=\"width:60px\" /> ".t("até")." <input type=\"number\" name=\"nivelmax\" value=\"$nivelmax\" style=\"width:60px\" /> ".t("Atributo:")." <input type=\"text\" name=\"atributo\" value=\"$atributo\" size=\"12\" /> ".t("Nome:")." <input type=\"text\" name=\"busca\" value=\"$busca\" size=\"20\" /> <input type=\"submit\" value=\"".t("Filtrar")."\" /> <a href=\"admin.php?do=drops\">".t("Limpar filtros")."</a></form><br />";
    $page .= "<b>".admcontar("drops", $onde)."</b> ".t("drop(s) encontrado(s).")."<br /><br />";
    $page .= "<table class=\"lista\"><tr><th>".t("ID")."</th><th></th><th>".t("Nome")."</th><th>".t("Nível mín.")."</th><th>".t("Atributo 1")."</th><th>".t("Atributo 2")."</th><th>".t("Atributo 3")."</th><th>".t("Inimigos")."</th><th></th></tr>";
    $csrf = csrf_campo();
    $query = doquery("SELECT * FROM {{table}} WHERE $onde ORDER BY mlevel, id", "drops");
    while ($row = mysqli_fetch_array($query)) {
        $icone = file_exists("layoutnovo/equipamentos/drops/".$row["id"].".gif") ? "<img src=\"layoutnovo/equipamentos/drops/".$row["id"].".gif\" width=\"34\" height=\"34\" alt=\"\" />" : "<span class=\"highlight small\">".t("sem ícone")."</span>";
        $qtd = isset($inimigos[intval($row["id"])]) ? $inimigos[intval($row["id"])] : 0;
        $duplicar = "<form action=\"admin.php?do=drops\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"duplicar\" value=\"".$row["id"]."\" /><input type=\"submit\" value=\"".t("Duplicar")."\" /></form>";
        $page .= "<tr><td>".$row["id"]."</td><td>$icone</td><td><a href=\"admin.php?do=editdrop:".$row["id"]."\">".$row["name"]."</a></td><td>".$row["mlevel"]."</td><td>".$row["attribute1"]."</td><td>".$row["attribute2"]."</td><td>".$row["attribute3"].(($row["consumivel"] != "") ? "<br /><span class=\"small\">".tf("consumível: %s", $row["consumivel"])."</span>" : "").((intval($row["ensina_jutsu"]) > 0) ? "<br /><span class=\"small\">".tf("ensina o jutsu <a href=\"admin.php?do=editspell:%s\">#%s</a>", intval($row["ensina_jutsu"]), intval($row["ensina_jutsu"]))."</span>" : "")."</td><td>$qtd</td><td>$duplicar</td></tr>";
    }
    if (mysqli_num_rows($query) == 0) { $page .= "<tr><td colspan=\"9\">".t("Nenhum drop encontrado.")."</td></tr>"; }
    $page .= "</table>";
    admindisplay($page, t("Itens de drop"));

}

function editdrop($id) {

    $mensagem = "";
    if (isset($_POST["apagar"]) && $id > 0) {
        doquery("DELETE FROM {{table}} WHERE id='$id' LIMIT 1", "drops");
        doquery("DELETE FROM {{table}} WHERE drop_id='$id'", "monster_drops"); // sai também das tabelas de drops dos inimigos.
        doquery("UPDATE {{table}} SET dropcode='0' WHERE dropcode='$id'", "users");
        header("Location: admin.php?do=drops&apagado=1"); die();
    }
    // Dar este drop (comum ou variante rara) a um jogador: vai para o banco de itens dele ("nome[:efeito],id,4,X;").
    // O jogador é travado (FOR UPDATE) como no funcaoitens.php, para não perder um depósito/saque feito ao mesmo tempo.
    if ($id > 0 && isset($_POST["darvariante"])) {
        include_once('funcoesinclusas.php');
        $efeito = pegartexto($_POST, "efeito");
        if ($efeito != "" && !isset(variantesitens()[$efeito])) { $efeito = ""; }
        $jogador = trim(pegartexto($_POST, "jogador"));
        $drow = mysqli_fetch_array(doquery("SELECT name FROM {{table}} WHERE id='$id' LIMIT 1", "drops"));
        doquery("START TRANSACTION", "users");
        $alvo = mysqli_fetch_array(doquery("SELECT id, charname, bancogeral, acesso FROM {{table}} WHERE charname='$jogador' LIMIT 1 FOR UPDATE", "users"));
        if (!csrf_confere() || !$drow) { doquery("ROLLBACK", "users"); $mensagem = admmsg(t("Tentativa de trapaça ou erro detectado."), "erro"); }
        elseif (!$alvo || $jogador == "") { doquery("ROLLBACK", "users"); $mensagem = admmsg(t("Nao existe nenhum jogador com esse Nome."), "erro"); }
        else {
            $banco = ($alvo["bancogeral"] == "None" || $alvo["bancogeral"] == "") ? "" : $alvo["bancogeral"];
            $limite = max(30, min(50, isset($alvo["banco_limite"]) ? intval($alvo["banco_limite"]) : 30)); // mesmo limite do bancolimite() (funcaoitens.php).
            if (count(array_filter(explode(";", $banco))) >= $limite) { doquery("ROLLBACK", "users"); $mensagem = admmsg(tf("O banco de %s está cheio (%s itens).", $alvo["charname"], $limite), "erro"); }
            else {
                $item = nomecomvariante($drow["name"], $efeito).",".$id.",4,".durabilidadenova(4, $id); // durabilidade cheia (drops.durabilidade) ou X.
                doquery("UPDATE {{table}} SET bancogeral='".addslashes($banco.$item.";")."' WHERE id='".intval($alvo["id"])."' LIMIT 1", "users");
                doquery("COMMIT", "users");
                $mensagem = admmsg(tf("%s foi colocado no banco de %s.", nomeitemhtml(nomecomvariante($drow["name"], $efeito)), $alvo["charname"]));
            }
        }
    }
    if (isset($_POST["submit"])) {
        $erros = "";
        $name = admtexto("name", t("Nome"), $erros, true, 50);
        $mlevel = intval(admnumero("mlevel", t("Nível mínimo"), $erros, 1, 65535));
        // Consumível (poção/comida): "hp,valor" ou "mp,valor"; nesse caso os atributos podem ficar todos em X.
        include_once("funcoesjutsus.php"); $constipo = pegartexto($_POST, "constipo"); if (!in_array($constipo, array("", "hp", "mp", "tp", "np", "ep", "bomba", "descanso"), true)) { $constipo = ""; }
        $consumivel = "";
        $camposbomba = "";
        if ($constipo == "bomba") { // Ferramenta ninja de arremesso: consumivel = "bomba,ID" (gravado depois de ter o id) e as colunas bomba_*.
            $efs = itembatalhaefeitos(); $els = array("neutro", "fogo", "agua", "vento", "raio", "terra");
            $bel = pegartexto($_POST, "bomba_elemento"); if (!in_array($bel, $els, true)) { $bel = "neutro"; }
            $bef = pegartexto($_POST, "bomba_efeito"); if (!isset($efs[$bef])) { $bef = ""; }
            $camposbomba = ", bomba_dano='".intval(admnumero("bomba_dano", t("Dano"), $erros, 0, 60000))."', bomba_acerto='".intval(admnumero("bomba_acerto", t("Chance de acerto (%)"), $erros, 1, 100))."'"
                .", bomba_elemento='$bel', bomba_efeito='$bef', bomba_efeito_valor='".intval(admnumero("bomba_efeito_valor", t("Valor do efeito"), $erros, 0, 60000))."'"
                .", bomba_efeito_turnos='".intval(admnumero("bomba_efeito_turnos", t("Turnos do efeito"), $erros, 0, 99))."', bomba_efeito_chance='".intval(admnumero("bomba_efeito_chance", t("Chance do efeito (%)"), $erros, 0, 100))."'";
            $consumivel = "bomba,".max(0, $id);
        } elseif ($constipo == "descanso") { // Item de descanso: consumivel = "descanso,ID"; multiplicador em % (>100) e horas (mín. 2).
            $camposbomba = ", descanso_mult='".intval(admnumero("descanso_mult", t("Multiplicador do descanso (%)"), $erros, 101, 1000))."', descanso_horas='".intval(admnumero("descanso_horas", t("Duração (horas)"), $erros, 2, 72))."'";
            $consumivel = "descanso,".max(0, $id);
        } elseif ($constipo != "") { $consumivel = $constipo.",".intval(admnumero("consvalor", t("Quanto recupera"), $erros, 1, 30000)); }
        // Pergaminho de jutsu (ensina_jutsu = id do jutsu): vai só para a mochila e ensina o jutsu ao ser usado; atributos em X.
        $ensina = pegarint($_POST, "ensina_jutsu");
        if ($ensina < 0 || ($ensina > 0 && mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE id='$ensina' LIMIT 1", "spells")) != 1)) { $erros .= t("O jutsu escolhido não existe.")."<br />"; $ensina = 0; }
        if ($ensina > 0 && mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE id='$ensina' AND exclusivo_missao='1' LIMIT 1", "spells")) == 1) { $erros .= t("Esse jutsu é exclusivo de missão: não pode ter pergaminho.")."<br />"; $ensina = 0; }
        if ($ensina > 0 && $constipo != "") { $erros .= t("Um drop não pode ser poção e pergaminho de jutsu ao mesmo tempo.")."<br />"; }
        // Pergaminho de Disfarce (ensina_avatar = id do avatar, funcoesavatares.php): libera o avatar ao ser pego; atributos em X.
        $ensinaavatar = pegarint($_POST, "ensina_avatar");
        if ($ensinaavatar < 0 || ($ensinaavatar > 0 && mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE id='$ensinaavatar' LIMIT 1", "avatares")) != 1)) { $erros .= t("Avatar inválido.")."<br />"; $ensinaavatar = 0; }
        if ($ensinaavatar > 0 && ($ensina > 0 || $constipo != "")) { $erros .= t("Um Pergaminho de Disfarce não pode ser também poção ou pergaminho de jutsu.")."<br />"; }
        $pilhavenda = admlerpilhavenda($erros);
        $attribute1 = admcodigoespecial(pegartexto($_POST, "attribute1"), t("Atributo 1"), $erros, ($constipo != "" || $ensina > 0 || $ensinaavatar > 0));
        $attribute2 = admcodigoespecial(pegartexto($_POST, "attribute2"), t("Atributo 2"), $erros, true);
        $attribute3 = admcodigoespecial(pegartexto($_POST, "attribute3"), t("Atributo 3"), $erros, true);
        admbonusrepetidos(array($attribute1, $attribute2, $attribute3), $erros);
        if (mb_strlen($attribute1) > 30 || mb_strlen($attribute2) > 30 || mb_strlen($attribute3) > 30) { $erros .= t("Cada atributo pode ter no máximo 30 caracteres.")."<br />"; }
        if ($erros == "") {
            $campos = "name='$name', mlevel='$mlevel', type='1', attribute1='$attribute1', attribute2='$attribute2', attribute3='$attribute3', consumivel='$consumivel', ensina_jutsu='$ensina', ensina_avatar='$ensinaavatar'".$pilhavenda.$camposbomba;
            if ($id == 0) { doquery("INSERT INTO {{table}} SET $campos", "drops"); global $link; $id = mysqli_insert_id($link); $mensagem = admmsg(tf("Drop criado com o ID %s.", $id)); if ($constipo == "bomba" || $constipo == "descanso") { doquery("UPDATE {{table}} SET consumivel='$constipo,$id' WHERE id='$id' LIMIT 1", "drops"); } }
            else { doquery("UPDATE {{table}} SET $campos WHERE id='$id' LIMIT 1", "drops"); $mensagem = admmsg(t("Drop atualizado.")); }
            $erroupload = admupload("icone", "layoutnovo/equipamentos/drops/$id.gif", 34, 34, "gif");
            if ($erroupload != "") { $mensagem .= admmsg(tf("O ícone não foi salvo: %s", $erroupload), "erro"); }
        } else {
            $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro");
            $postado = array("name"=>stripslashes($name), "mlevel"=>pegartexto($_POST, "mlevel"), "attribute1"=>pegartexto($_POST, "attribute1"), "attribute2"=>pegartexto($_POST, "attribute2"), "attribute3"=>pegartexto($_POST, "attribute3"), "consumivel"=>($constipo != "" ? $constipo.",".pegartexto($_POST, "consvalor") : ""), "ensina_jutsu"=>$ensina, "ensina_avatar"=>$ensinaavatar, "naoVendavel"=>pegarint($_POST, "naoVendavel"), "empilhavel"=>pegarint($_POST, "empilhavel"), "maxpilha"=>pegarint($_POST, "maxpilha", 20));
        }
    }

    if ($id > 0) {
        $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "drops");
        if (mysqli_num_rows($query) != 1) { admindisplay(admmsg(t("Drop não encontrado."), "erro"), t("Drops")); }
        $r = mysqli_fetch_array($query); $titulo = tf("Editar drop #%s", $id);
    } else { $r = array("name"=>"", "mlevel"=>1, "attribute1"=>"maxhp,10", "attribute2"=>"X", "attribute3"=>"X", "consumivel"=>"", "ensina_jutsu"=>0, "ensina_avatar"=>0, "naoVendavel"=>0, "empilhavel"=>0, "maxpilha"=>20); $titulo = t("Novo drop"); }
    if (isset($postado)) { $r = array_merge($r, $postado); }
    $linhaspilha = admcampospilhavenda($r);
    $cons = array_pad(explode(",", (string)$r["consumivel"]), 2, "");
    include_once("funcoesjutsus.php"); $opcons = admopcoes(array(""=>t("Não (equipável)"), "hp"=>t("Poção de HP"), "mp"=>t("Poção de CH"), "tp"=>t("Poção de TP"), "np"=>t("Poção de NP"), "ep"=>t("Poção de EP"), "bomba"=>t("Ferramenta ninja (arremesso em batalha)"), "descanso"=>t("Item de descanso (multiplica o Descansar)")), $cons[0]);
    // Campos da ferramenta ninja (drops.bomba_*): dano, chance de acerto, elemento e o efeito no alvo.
    $bv = function ($c, $pad) use ($r) { return htmlspecialchars((string)(isset($r[$c]) ? $r[$c] : $pad), ENT_QUOTES, "UTF-8"); };
    $opbel = admopcoes(array("neutro"=>t("Neutro"), "fogo"=>t("Fogo"), "agua"=>t("Água"), "vento"=>t("Vento"), "raio"=>t("Raio"), "terra"=>t("Terra")), isset($r["bomba_elemento"]) ? $r["bomba_elemento"] : "neutro");
    $opbef = admopcoes(array_map("t", itembatalhaefeitos()), isset($r["bomba_efeito"]) ? $r["bomba_efeito"] : "");
    $linhabomba = "<tr><td>".t("Ferramenta ninja:")."</td><td>".t("Dano")." <input type=\"text\" name=\"bomba_dano\" size=\"5\" value=\"".$bv("bomba_dano", 0)."\" /> "
        .t("Chance de acerto (%)")." <input type=\"text\" name=\"bomba_acerto\" size=\"3\" value=\"".$bv("bomba_acerto", 80)."\" /> ".t("Elemento")." <select name=\"bomba_elemento\">$opbel</select><br />"
        .t("Efeito")." <select name=\"bomba_efeito\">$opbef</select> ".t("valor")." <input type=\"text\" name=\"bomba_efeito_valor\" size=\"4\" value=\"".$bv("bomba_efeito_valor", 0)."\" /> "
        .t("turnos")." <input type=\"text\" name=\"bomba_efeito_turnos\" size=\"2\" value=\"".$bv("bomba_efeito_turnos", 0)."\" /> ".t("chance (%)")." <input type=\"text\" name=\"bomba_efeito_chance\" size=\"3\" value=\"".$bv("bomba_efeito_chance", 100)."\" />"
        ."<br />".t("Item de descanso:")." ".t("multiplicador (%)")." <input type=\"text\" name=\"descanso_mult\" size=\"4\" value=\"".$bv("descanso_mult", 150)."\" /> ".t("horas")." <input type=\"text\" name=\"descanso_horas\" size=\"2\" value=\"".$bv("descanso_horas", 2)."\" /> <span class=\"small\">".t("(150 = ×1,5; mínimo 2 horas)")."</span>"
        ."<br /><span class=\"small\">".t("Só vale com Consumível = Ferramenta ninja. Arremessar gasta o turno na luta e no duelo; a Precisão do jogador soma um pouco na chance de acerto. Valor: dano por turno (queimadura/veneno), % (ataque/defesa) ou força do sono.")."</span></td></tr>";
    $consvalor = htmlspecialchars((string)$cons[1], ENT_QUOTES, 'UTF-8');
    // Jutsus que o pergaminho pode ensinar.
    $listajutsus = array(0=>t("Não (drop comum)"));
    $jq = doquery("SELECT id,name,nivelminimo FROM {{table}} WHERE exclusivo_missao='0' ORDER BY nivelminimo, id", "spells"); // exclusivos de missão não têm pergaminho.
    while ($jrow = mysqli_fetch_array($jq)) { $listajutsus[intval($jrow["id"])] = "#".$jrow["id"]." ".$jrow["name"].(intval($jrow["nivelminimo"]) > 0 ? " (".tf("nível %s", intval($jrow["nivelminimo"])).")" : ""); }
    $opensina = admopcoes($listajutsus, intval($r["ensina_jutsu"]));
    $listaavatares = array(0 => t("Nenhum")); $qav = doquery("SELECT id,nome FROM {{table}} ORDER BY ordem,id", "avatares");
    while ($av = mysqli_fetch_array($qav)) { $listaavatares[intval($av["id"])] = "#".intval($av["id"])." ".t($av["nome"]); }
    $opavatar = admopcoes($listaavatares, intval(isset($r["ensina_avatar"]) ? $r["ensina_avatar"] : 0));
    $linkjutsu = (intval($r["ensina_jutsu"]) > 0) ? " <a href=\"admin.php?do=editspell:".intval($r["ensina_jutsu"])."\">".t("Editar o jutsu")."</a>" : "";
    // Inimigos que podem soltar este drop (tabela monster_drops).
    $quemdropa = "";
    if ($id > 0) {
        $iq = doquery("SELECT monster_id, chance_percent FROM {{table}} WHERE drop_id='$id' ORDER BY chance_percent DESC", "monster_drops");
        $partes = array();
        while ($irow = mysqli_fetch_array($iq)) {
            $mrow = mysqli_fetch_array(doquery("SELECT name, level FROM {{table}} WHERE id='".intval($irow["monster_id"])."' LIMIT 1", "monsters"));
            if ($mrow) { $partes[] = "<a href=\"admin.php?do=editmonster:".intval($irow["monster_id"])."\">".$mrow["name"]."</a> (".tf("nível %s", $mrow["level"]).", ".floatval($irow["chance_percent"])."%)"; }
        }
        $quemdropa = "<h2>".t("Inimigos que soltam este drop")."</h2><p class=\"small\">".(count($partes) ? implode(", ", $partes) : t("Nenhum inimigo tem este drop na tabela (ele ainda pode cair pela regra antiga de inimigos sem tabela)."))."</p>";
    }
    $icone = ($id > 0 && file_exists("layoutnovo/equipamentos/drops/$id.gif")) ? "<img src=\"layoutnovo/equipamentos/drops/$id.gif?".filemtime("layoutnovo/equipamentos/drops/$id.gif")."\" alt=\"\" />" : "<span class=\"small\">".t("sem ícone")."</span>";
    $csrf = csrf_campo();
    // Prévia das 6 variantes raras (ícone + rótulo + bônus) e formulário para dar o drop a um jogador.
    $variantes = "";
    if ($id > 0 && trim((string)$r["consumivel"]) == "" && intval($r["ensina_jutsu"]) == 0) { // consumíveis e pergaminhos de jutsu não têm variante.
        include_once('funcoesinclusas.php');
        $celulas = ""; $opefeito = "<option value=\"\">".t("Comum (sem variante)")."</option>";
        foreach (variantesitens() as $ef => $v) {
            $nomev = nomecomvariante($r["name"], $ef);
            $bonus = array();
            foreach (bonusvariante($nomev, $r, "drops") as $b) { $bonus[] = explicaratributo($b); }
            $celulas .= "<td align=\"center\" class=\"small\"><img src=\"".iconeitem($id, true, $nomev)."\" width=\"34\" height=\"34\" alt=\"\" /><br /><font color=\"".$v["cor"]."\"><b>✦ ".t($v["rotulo"])."</b></font><br />".(count($bonus) ? implode("<br />", $bonus) : "—")."</td>";
            $opefeito .= "<option value=\"$ef\">✦ ".t($v["rotulo"])."</option>";
        }
        $variantes = "<h2>".t("Variantes raras")."</h2><table><tr><td align=\"center\" class=\"small\">$icone<br />".t("Comum")."</td>$celulas</tr></table>"
            ."<form action=\"admin.php?do=editdrop:$id\" method=\"post\">$csrf".t("Dar este drop ao jogador:")." <input type=\"text\" name=\"jogador\" size=\"18\" /> <select name=\"efeito\">$opefeito</select> <input type=\"submit\" name=\"darvariante\" value=\"".t("Colocar no banco do jogador")."\" /></form>"
            ."<p class=\"small\">".t("Os ícones das variantes ficam em layoutnovo/equipamentos/variantes/<efeito>/drops/ e são gerados por tools/variantes_itens.py (rode de novo depois de trocar o ícone).")."</p>";
    }
    $botaoapagar = ($id > 0) ? "<form action=\"admin.php?do=editdrop:$id\" method=\"post\">$csrf<input type=\"submit\" name=\"apagar\" value=\"".t("Apagar este drop")."\" class=\"botaoperigo\" onclick=\"return confirm('".t("Apagar este drop?")."');\" /></form>" : "";

$t = "t"; $tf = "tf";
$page = <<<END
<h1>$titulo</h1>
$mensagem
<form action="admin.php?do=editdrop:$id" method="post" enctype="multipart/form-data">$csrf
<table width="100%">
<tr><td width="200">{$t("Nome:")}</td><td><input type="text" name="name" size="35" maxlength="50" value="{$r["name"]}" /></td></tr>
<tr><td>{$t("Nível mínimo do inimigo:")}</td><td><input type="text" name="mlevel" size="5" value="{$r["mlevel"]}" /></td></tr>
<tr><td>{$t("Atributo 1:")}</td><td><input type="text" name="attribute1" size="25" maxlength="30" value="{$r["attribute1"]}" /> <span class="small">{$t("Obrigatório, formato atributo,valor (ex.: maxhp,25).")}</span></td></tr>
<tr><td>{$t("Atributo 2:")}</td><td><input type="text" name="attribute2" size="25" maxlength="30" value="{$r["attribute2"]}" /> <span class="small">{$t("X para nenhum.")}</span></td></tr>
<tr><td>{$t("Atributo 3:")}</td><td><input type="text" name="attribute3" size="25" maxlength="30" value="{$r["attribute3"]}" /> <span class="small">{$t("X para nenhum.")}</span></td></tr>
<tr><td>{$t("Consumível:")}</td><td><select name="constipo">$opcons</select> {$t("recupera")} <input type="text" name="consvalor" size="6" value="$consvalor" /> <span class="small">{$t("Consumível vai para a mochila e é usado como as poções (recupera HP ou CH). Os atributos podem ficar em X.")}</span></td></tr>
$linhabomba
<tr><td>{$t("Libera o avatar:")}</td><td><select name="ensina_avatar">$opavatar</select> <span class="small">{$t("Pergaminho de Disfarce: não ocupa espaço; ao pegar o drop o avatar é liberado (se já tinha, vira Ryou). Configure em")} <a href="admin_avatares.php">{$t("Avatares")}</a>.</span></td></tr>
<tr><td>{$t("Ensina o jutsu:")}</td><td><select name="ensina_jutsu">$opensina</select>$linkjutsu <span class="small">{$t("Pergaminho de jutsu: vai para a mochila e, ao ser usado, ensina o jutsu (se o jogador cumprir os requisitos). Os atributos podem ficar em X. O ícone na mochila é o pergaminho na cor do elemento do jutsu.")}</span></td></tr>
$linhaspilha
<tr><td>{$t("Ícone:")}</td><td>$icone <input type="file" name="icone" accept="image/*" /> <span class="small">{$t("Ajustado para 34x34 GIF.")}</span></td></tr>
</table>
<input type="submit" name="submit" value="{$t("Salvar")}" /> <a href="admin.php?do=drops">{$t("Voltar")}</a>
</form><br />
$botaoapagar
$quemdropa
$variantes
<span class="small">{$t("Atributos aceitos: os mesmos do campo Especial dos itens (inclusive critico, esquiva, regenhp e economiachakra).")}</span>
END;
    admindisplay($page, $titulo);

}


/* ------------------------------------------------------------------------
   Jutsus.
   ------------------------------------------------------------------------ */

function admjutsuaprendizado() { // Como cada jutsu é aprendido: array(id => array("niveis" => [nível => classes], "pergaminhos" => [linhas de drops])).

    $lista = array();
    $lq = doquery("SELECT id,1_spells,2_spells,3_spells FROM {{table}} ORDER BY id", "levels");
    while ($lr = mysqli_fetch_array($lq)) {
        for ($c = 1; $c <= 3; $c++) { if (intval($lr[$c."_spells"]) > 0) { $lista[intval($lr[$c."_spells"])]["niveis"][intval($lr["id"])][] = $c; } }
    }
    $dq = doquery("SELECT id,name,ensina_jutsu FROM {{table}} WHERE ensina_jutsu>0 ORDER BY id", "drops");
    while ($dr = mysqli_fetch_array($dq)) { $lista[intval($dr["ensina_jutsu"])]["pergaminhos"][] = $dr; }
    return $lista;

}

function spells() {

    include_once('funcoesinclusas.php'); // jutsudescricaoefeitos(), jutsuefeitos()... (funcoesjutsus.php)
    $tipos = array(1=>t("Cura"), 2=>t("Dano"), 3=>t("Sono (genjutsu)"), 4=>t("+ Ataque"), 5=>t("+ Defesa"), 6=>t("Suporte"), 7=>t("Taijutsu"));
    $elementos = admelementos();
    $classes = array(0=>t("Qualquer"), 1=>nomedaclasse(1), 2=>nomedaclasse(2), 3=>nomedaclasse(3));
    $mensagem = isset($_GET["apagado"]) ? admmsg(t("Jutsu apagado.")) : "";

    // Filtros (GET, lista branca): elemento, tipo, classe e forma de aprender.
    $felemento = pegartexto($_GET, "elemento"); if (!isset($elementos[$felemento])) { $felemento = ""; }
    $ftipo = pegarint($_GET, "tipo"); if (!isset($tipos[$ftipo])) { $ftipo = 0; }
    $fclasse = pegartexto($_GET, "classe"); if (!in_array($fclasse, array("", "0", "1", "2", "3"), true)) { $fclasse = ""; }
    $faprender = pegartexto($_GET, "aprender"); if (!in_array($faprender, array("", "nivel", "pergaminho", "nenhum"), true)) { $faprender = ""; }
    $onde = "1";
    if ($felemento != "") { $onde .= " AND elemento='$felemento'"; }
    if ($ftipo > 0) { $onde .= " AND type='$ftipo'"; }
    if ($fclasse !== "") { $onde .= " AND classe='".intval($fclasse)."'"; }
    $aprendizado = admjutsuaprendizado();

    $page = "<h1>".t("Jutsus")."</h1>$mensagem<p>".t("Os jogadores aprendem jutsus ao subir de nível (coluna \"jutsu\" da tabela de <a href=\"admin.php?do=levels\">Níveis</a>) ou usando um pergaminho de jutsu da mochila (drop com \"Ensina o jutsu\" preenchido, ver <a href=\"admin.php?do=drops\">Drops</a>).")."</p>";
    $page .= "<a href=\"admin.php?do=editspell:0\" class=\"botao\">".t("+ Adicionar jutsu")."</a><br /><br />";
    $page .= "<form action=\"admin.php\" method=\"get\"><input type=\"hidden\" name=\"do\" value=\"spells\" />"
        .t("Elemento:")." <select name=\"elemento\">".admopcoes(array(""=>t("Todos")) + $elementos, $felemento)."</select> "
        .t("Tipo:")." <select name=\"tipo\">".admopcoes(array(0=>t("Todos")) + $tipos, $ftipo)."</select> "
        .t("Classe:")." <select name=\"classe\">".admopcoes(array(""=>t("Todas")) + $classes, $fclasse)."</select> "
        .t("Aprendido por:")." <select name=\"aprender\">".admopcoes(array(""=>t("Qualquer forma"), "nivel"=>t("Nível"), "pergaminho"=>t("Pergaminho"), "nenhum"=>t("Nenhuma (não ensinado)")), $faprender)."</select> "
        ."<input type=\"submit\" value=\"".t("Filtrar")."\" /> <a href=\"admin.php?do=spells\">".t("Limpar filtros")."</a></form><br />";
    $page .= "<table class=\"lista\"><tr><th>".t("ID")."</th><th>".t("Nome")."</th><th>".t("Tipo")."</th><th>".t("Chakra")."</th><th>".t("Valor")."</th><th>".t("Elemento")."</th><th>".t("Efeitos")."</th><th>".t("Classe")."</th><th>".t("Aprendido por")."</th></tr>";
    $query = doquery("SELECT * FROM {{table}} WHERE $onde ORDER BY id", "spells");
    $total = 0;
    while ($row = mysqli_fetch_array($query)) {
        $id = intval($row["id"]);
        $niveis = isset($aprendizado[$id]["niveis"]) ? $aprendizado[$id]["niveis"] : array();
        $pergs = isset($aprendizado[$id]["pergaminhos"]) ? $aprendizado[$id]["pergaminhos"] : array();
        if ($faprender == "nivel" && !count($niveis)) { continue; }
        if ($faprender == "pergaminho" && !count($pergs)) { continue; }
        if ($faprender == "nenhum" && (count($niveis) || count($pergs))) { continue; }
        $como = array();
        foreach ($niveis as $nivel => $cls) { $como[] = tf("nível %s", $nivel).((count($cls) < 3) ? " (".implode(", ", array_map("nomedaclasse", $cls)).")" : ""); }
        foreach ($pergs as $p) { $como[] = "<a href=\"admin.php?do=editdrop:".intval($p["id"])."\">".tf("pergaminho #%s", intval($p["id"]))."</a>"; }
        $page .= "<tr><td>$id</td><td><a href=\"admin.php?do=editspell:$id\">".$row["name"]."</a></td><td>".(isset($tipos[$row["type"]]) ? $tipos[$row["type"]] : $row["type"])."</td><td>".$row["mp"]."</td><td>".$row["attribute"]."</td><td>".(isset($elementos[$row["elemento"]]) ? $elementos[$row["elemento"]] : $row["elemento"])."</td><td class=\"small\">".implode("<br />", jutsudescricaoefeitos($row))."</td><td>".(isset($classes[intval($row["classe"])]) ? $classes[intval($row["classe"])] : "-").(trim((string)$row["graduacao"]) != "" ? "<br /><span class=\"small\">".t($row["graduacao"])."</span>" : "")."</td><td class=\"small\">".(count($como) ? implode("<br />", $como) : "<span class=\"highlight\">".t("ninguém aprende")."</span>")."</td></tr>";
        $total++;
    }
    if ($total == 0) { $page .= "<tr><td colspan=\"9\">".t("Nenhum jutsu encontrado.")."</td></tr>"; }
    $page .= "</table>";
    admindisplay($page, t("Jutsus"));

}

function admvalidarjutsu($r, &$erros) { // Confere as regras entre os campos de um jutsu (já lidos e convertidos). Devolve o array corrigido.

    $efeitos = jutsuefeitos();
    if ($r["type"] == 3 && ($r["attribute"] < 1 || $r["attribute"] > 15)) { $erros .= t("Para jutsus de sono o valor vai de 1 a 15 (chance do inimigo continuar dormindo).")."<br />"; }
    if (in_array($r["type"], array(4, 5)) && $r["attribute"] > 255) { $erros .= t("Para +Ataque/+Defesa o valor é uma porcentagem de no máximo 255.")."<br />"; }
    if (in_array($r["type"], array(1, 2)) && $r["attribute"] < 1) { $erros .= t("Jutsus de cura e de dano precisam de um valor maior que zero.")."<br />"; }
    if ($r["type"] != 2 && $r["golpes"] > 1) { $erros .= t("Só jutsus de dano podem ter mais de um golpe.")."<br />"; }
    if ($r["type"] != 2 && $r["roubovida"] > 0) { $erros .= t("Só jutsus de dano podem ter roubo de vida.")."<br />"; }
    if ($r["type"] == 6 && $r["efeito"] == "" && (!isset($r["acao_menu"]) || $r["acao_menu"] == "")) { $erros .= t("Um jutsu de suporte precisa de um efeito extra.")."<br />"; }
    if ($r["efeito"] == "") { $r["efeito_valor"] = 0; $r["efeito_turnos"] = 0; $r["efeito_chance"] = 100; return $r; }
    if ($r["efeito"] == "atordoar") { $r["efeito_valor"] = 0; }
    elseif ($r["efeito_valor"] < 1) { $erros .= t("O efeito extra precisa de um valor maior que zero.")."<br />"; }
    if (in_array($r["efeito"], array("buff_ataque", "buff_defesa", "buff_critico", "buff_esquiva", "debuff_ataque", "debuff_defesa")) && $r["efeito_valor"] > 90) { $erros .= t("Reforços e enfraquecimentos são porcentagens de 1 a 90.")."<br />"; }
    if ($r["efeito"] == "dreno_chakra") { $r["efeito_turnos"] = 1; }
    elseif ($r["efeito_turnos"] < 1 || $r["efeito_turnos"] > 10) { $erros .= t("O efeito extra dura de 1 a 10 turnos.")."<br />"; }
    if ($r["efeito"] == "atordoar" && $r["efeito_turnos"] > 3) { $erros .= t("O atordoamento dura no máximo 3 turnos.")."<br />"; }
    if ($r["efeito"] == "atordoar" && $r["cooldown"] < $r["efeito_turnos"]) { $erros .= t("Um jutsu que atordoa precisa de recarga maior ou igual à duração do atordoamento (senão o inimigo nunca ataca).")."<br />"; }
    return $r;

}

function suprimentos() { // Regras da Invocação de Suprimentos (tabela suprimentos, uma linha por tier): falha, preços/níveis máximos, pesos e exclusões.

    $mensagem = "";
    $campos = array("chance_falha"=>array("Falha (%)", 0, 100), "preco_max"=>array("Preço máx. (loja)", 0, 16777215), "nivel_max_drop"=>array("Nível máx. (drop)", 0, 99),
        "pocao_max"=>array("Poção máx.", 0, 65535), "peso_loja"=>array("Peso loja", 0, 255), "peso_drop"=>array("Peso drop", 0, 255), "peso_pocao"=>array("Peso poção", 0, 255));
    if (isset($_POST["submit"])) {
        $erros = "";
        $linhas = array();
        for ($tier = 1; $tier <= 4; $tier++) {
            foreach ($campos as $c => $cfg) { $linhas[$tier][$c] = intval(admnumero($c."_".$tier, tf("%s (tier %s)", t($cfg[0]), $tier), $erros, $cfg[1], $cfg[2])); }
            $ex = preg_replace('/\s+/', '', pegartexto($_POST, "excluir_".$tier));
            if ($ex != "" && !preg_match('/^[id]\d+(,[id]\d+)*$/', $ex)) { $erros .= tf("Exclusões do tier %s: use o formato i12,d45.", $tier)."<br />"; }
            $linhas[$tier]["excluir"] = substr($ex, 0, 255);
        }
        if ($erros == "") {
            foreach ($linhas as $tier => $l) {
                $sets = array(); foreach ($l as $c => $v) { $sets[] = "$c='".addslashes((string)$v)."'"; }
                doquery("INSERT INTO {{table}} SET tier='$tier', ".implode(", ", $sets)." ON DUPLICATE KEY UPDATE ".implode(", ", $sets), "suprimentos");
            }
            $mensagem = admmsg(t("Regras da Invocação de Suprimentos salvas."));
        } else { $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro"); }
    }
    $atual = array();
    $q = doquery("SELECT * FROM {{table}} ORDER BY tier", "suprimentos");
    while ($r = mysqli_fetch_array($q)) { $atual[intval($r["tier"])] = $r; }
    $csrf = csrf_campo();
    $tabela = "<tr><th>".t("Tier")."</th>";
    foreach ($campos as $c => $cfg) { $tabela .= "<th>".t($cfg[0])."</th>"; }
    $tabela .= "<th>".t("Excluir (i12,d45)")."</th></tr>";
    for ($tier = 1; $tier <= 4; $tier++) {
        $tabela .= "<tr><td><b>$tier</b></td>";
        foreach ($campos as $c => $cfg) { $tabela .= "<td><input type=\"number\" name=\"{$c}_$tier\" value=\"".(isset($atual[$tier]) ? intval($atual[$tier][$c]) : 0)."\" min=\"".$cfg[1]."\" max=\"".$cfg[2]."\" style=\"width:70px\" /></td>"; }
        $tabela .= "<td><input type=\"text\" name=\"excluir_$tier\" size=\"16\" value=\"".(isset($atual[$tier]) ? htmlspecialchars($atual[$tier]["excluir"], ENT_QUOTES, 'UTF-8') : "")."\" /></td></tr>";
    }
    $jutsus = "";
    $q = doquery("SELECT id,name,tier,mp,recarga_min FROM {{table}} WHERE acao_menu='suprimentos' ORDER BY tier", "spells");
    while ($r = mysqli_fetch_array($q)) { $jutsus .= "<li><a href=\"admin.php?do=editspell:".intval($r["id"])."\">".$r["name"]."</a> — ".tf("tier %s, %s CH, recarga %s min", intval($r["tier"]), intval($r["mp"]), intval($r["recarga_min"]))."</li>"; }
    $page = "<h1>".t("Invocação de Suprimentos")."</h1>$mensagem<p>".t("Cada jutsu de Invocação de Suprimentos usa a linha do seu tier. Ao usar: primeiro a chance de falha (nada vem, o Chakra é gasto); depois a categoria é sorteada pelos pesos (loja, drop ou poção, só entre as que têm itens) e um item dela: itens de loja com preço entre 1/8 do máximo e o máximo, drops equipáveis com nível entre a metade do máximo e o máximo (nunca drops de missão, pergaminhos, itens de compensação ou variantes raras) e poções que recuperam entre 1/4 do máximo e o máximo. Itens não vendáveis e os ids da coluna Excluir nunca saem.")."</p>"
        ."<form action=\"admin.php?do=suprimentos\" method=\"post\">$csrf<table class=\"lista\">$tabela</table><br /><input type=\"submit\" name=\"submit\" value=\"".t("Salvar")."\" /></form>"
        ."<h2>".t("Jutsus de invocação")."</h2><ul>$jutsus</ul><p class=\"small\">".t("Custo de Chakra, recarga em minutos, nível e requisitos ficam no editor de cada jutsu.")."</p>";
    admindisplay($page, t("Invocação de Suprimentos"));

}

function editspell($id) {

    include_once('funcoesinclusas.php'); // funções dos jutsus (funcoesjutsus.php).
    $mensagem = "";
    $previa = "";
    $elementos = admelementos();
    $efeitos = jutsuefeitos();
    if (isset($_POST["apagar"]) && $id > 0) {
        doquery("DELETE FROM {{table}} WHERE id='$id' LIMIT 1", "spells");
        header("Location: admin.php?do=spells&apagado=1"); die();
    }
    // Criar um pergaminho (drop) que ensina este jutsu: "Pergaminho: Nome", sem atributos, nível mínimo = nível do jutsu.
    if (isset($_POST["criarpergaminho"]) && $id > 0) {
        $sr = mysqli_fetch_array(doquery("SELECT name,nivelminimo,exclusivo_missao FROM {{table}} WHERE id='$id' LIMIT 1", "spells"));
        if ($sr && intval($sr["exclusivo_missao"]) == 1) { $mensagem = admmsg(t("Este jutsu é exclusivo de missão: não pode ter pergaminho."), "erro"); }
        elseif ($sr) {
            $nome = addslashes(mb_substr("Pergaminho: ".stripslashes($sr["name"]), 0, 50, 'UTF-8'));
            doquery("INSERT INTO {{table}} SET name='$nome', mlevel='".max(1, intval($sr["nivelminimo"]))."', type='1', attribute1='X', attribute2='X', attribute3='X', consumivel='', ensina_jutsu='$id'", "drops");
            global $link; $novo = mysqli_insert_id($link);
            $mensagem = admmsg(tf("Pergaminho criado com o ID %s. <a href=\"admin.php?do=editdrop:%s\">Editar o pergaminho</a> ou coloque-o na tabela de drops de um <a href=\"admin.php?do=monsters\">inimigo</a>.", $novo, $novo));
        }
    }
    if (isset($_POST["submit"]) || isset($_POST["previa"])) {
        $erros = "";
        $r = array();
        $r["name"] = admtexto("name", t("Nome"), $erros, true, 50);
        $r["name_en"] = admtexto("name_en", t("Nome em inglês"), $erros, false, 50);
        $r["descricao"] = admtexto("descricao", t("Descrição"), $erros, false, 1000);
        $r["descricao_en"] = admtexto("descricao_en", t("Descrição em inglês"), $erros, false, 1000);
        $r["mp"] = intval(admnumero("mp", t("Chakra"), $erros, 0, 65535));
        $r["attribute"] = intval(admnumero("attribute", t("Valor"), $erros, 0, 65535));
        $r["type"] = pegarint($_POST, "type"); if ($r["type"] < 1 || $r["type"] > 7) { $erros .= t("Tipo inválido.")."<br />"; $r["type"] = 2; }
        $r["elemento"] = pegartexto($_POST, "elemento"); if (!isset($elementos[$r["elemento"]])) { $erros .= t("Elemento inválido.")."<br />"; $r["elemento"] = "neutro"; }
        $r["golpes"] = intval(admnumero("golpes", t("Golpes"), $erros, 1, 4));
        $r["roubovida"] = intval(admnumero("roubovida", t("Roubo de vida"), $erros, 0, 100));
        $r["efeito"] = pegartexto($_POST, "efeito"); if ($r["efeito"] != "" && !isset($efeitos[$r["efeito"]])) { $erros .= t("Efeito inválido.")."<br />"; $r["efeito"] = ""; }
        $r["efeito_valor"] = intval(admnumero("efeito_valor", t("Valor do efeito"), $erros, 0, 65535));
        $r["efeito_turnos"] = intval(admnumero("efeito_turnos", t("Turnos do efeito"), $erros, 0, 10));
        $r["efeito_chance"] = intval(admnumero("efeito_chance", t("Chance do efeito"), $erros, 1, 100));
        $r["cooldown"] = intval(admnumero("cooldown", t("Recarga"), $erros, 0, 20));
        $r["nivelminimo"] = intval(admnumero("nivelminimo", t("Nível mínimo"), $erros, 0, 1000));
        $r["classe"] = pegarint($_POST, "classe"); if ($r["classe"] < 0 || $r["classe"] > 3) { $erros .= t("Classe inválida.")."<br />"; $r["classe"] = 0; }
        $r["graduacao"] = pegartexto($_POST, "graduacao"); if ($r["graduacao"] != "" && !in_array($r["graduacao"], jutsugraduacoes(), true)) { $erros .= t("Graduação inválida.")."<br />"; $r["graduacao"] = ""; }
        // Exclusivo de missão: só é obtido como recompensa de missão (sem nível na tabela levels, sem pergaminho).
        $r["exclusivo_missao"] = (pegarint($_POST, "exclusivo_missao") == 1) ? 1 : 0;
        if ($r["exclusivo_missao"] && $id > 0) {
            if (mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE ensina_jutsu='$id' LIMIT 1", "drops")) > 0) { $erros .= t("Um jutsu exclusivo de missão não pode ter pergaminho: apague ou mude os pergaminhos que o ensinam.")."<br />"; }
            if (mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE 1_spells='$id' OR 2_spells='$id' OR 3_spells='$id' LIMIT 1", "levels")) > 0) { $erros .= t("Um jutsu exclusivo de missão não pode ser ensinado pela tabela de níveis.")."<br />"; }
        }
        // Jutsus especiais: onde é usado, ação de menu, Senjutsu, tier, custos de NP/TP e recarga em minutos (funcoesjutsus.php).
        $r["uso"] = (pegartexto($_POST, "uso") == "menu") ? "menu" : "batalha";
        $r["acao_menu"] = pegartexto($_POST, "acao_menu"); if (!in_array($r["acao_menu"], array("", "suprimentos", "passo"), true)) { $erros .= t("Ação de menu inválida.")."<br />"; $r["acao_menu"] = ""; }
        if ($r["acao_menu"] != "" && $r["uso"] != "menu") { $erros .= t("Suprimentos e Passo Veloz precisam do uso \"Lista rápida (menu)\".")."<br />"; }
        $r["requer_senjutsu"] = (pegarint($_POST, "requer_senjutsu") == 1) ? 1 : 0;
        $r["tier"] = intval(admnumero("tier", t("Tier"), $erros, 0, 4));
        $r["custo_np"] = intval(admnumero("custo_np", t("Custo de NP"), $erros, 0, 65535));
        $r["custo_ep"] = intval(admnumero("custo_ep", t("Custo de EP"), $erros, 0, 65535));
        $r["custo_tp"] = intval(admnumero("custo_tp", t("Custo de TP"), $erros, 0, 65535));
        $r["recarga_min"] = intval(admnumero("recarga_min", t("Recarga (minutos)"), $erros, 0, 10000));
        $r = admvalidarjutsu($r, $erros);
        if ($erros == "" && isset($_POST["submit"])) {
            $campos = array();
            foreach ($r as $c => $v) { $campos[] = "$c='$v'"; } // textos já vêm escapados pelo lib.php (addslashes); números são int; listas fechadas validadas acima.
            $campos = implode(", ", $campos);
            if ($id == 0) { doquery("INSERT INTO {{table}} SET $campos", "spells"); global $link; $id = mysqli_insert_id($link); $mensagem = admmsg(tf("Jutsu criado com o ID %s.", $id)); }
            else { doquery("UPDATE {{table}} SET $campos WHERE id='$id' LIMIT 1", "spells"); $mensagem = admmsg(t("Jutsu atualizado.")); }
        } elseif ($erros != "") {
            $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro");
            $postado = $r;
        } else { // só a prévia: mostra o texto do efeito sem gravar.
            $postado = $r;
            $previa = admmsg("<b>".t("Prévia (não salvo):")."</b><br />".implode("<br />", jutsudescricaoefeitos($r)), "aviso");
        }
        if (isset($postado)) { foreach (array("name", "name_en", "descricao", "descricao_en") as $c) { $postado[$c] = stripslashes($postado[$c]); } }
    }

    if ($id > 0) {
        $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "spells");
        if (mysqli_num_rows($query) != 1) { admindisplay(admmsg(t("Jutsu não encontrado."), "erro"), t("Jutsus")); }
        $r = mysqli_fetch_array($query); $titulo = tf("Editar jutsu #%s", $id);
    } else { $r = array("name"=>"", "name_en"=>"", "descricao"=>"", "descricao_en"=>"", "mp"=>10, "attribute"=>10, "type"=>2, "elemento"=>"neutro", "golpes"=>1, "roubovida"=>0, "efeito"=>"", "efeito_valor"=>0, "efeito_turnos"=>0, "efeito_chance"=>100, "cooldown"=>0, "nivelminimo"=>0, "classe"=>0, "graduacao"=>"", "exclusivo_missao"=>0, "uso"=>"batalha", "acao_menu"=>"", "requer_senjutsu"=>0, "tier"=>0, "custo_np"=>0, "custo_ep"=>1, "custo_tp"=>0, "recarga_min"=>0); $titulo = t("Novo jutsu"); }
    if (isset($postado)) { $r = array_merge($r, $postado); }
    if ($previa == "") { $previa = admmsg("<b>".t("Efeito atual:")."</b><br />".implode("<br />", jutsudescricaoefeitos($r)), "aviso"); }
    foreach (array("name", "name_en", "descricao", "descricao_en") as $c) { $r[$c] = htmlspecialchars((string)$r[$c], ENT_QUOTES, 'UTF-8', false); }
    $optipo = admopcoes(array(1=>t("Cura: recupera [valor] de HP"), 2=>t("Dano: causa até [valor] de dano"), 3=>t("Sono: inimigo dorme ([valor] em 15 de continuar dormindo)"), 4=>t("+ Ataque: +[valor]% de dano até o fim da luta"), 5=>t("+ Defesa: +[valor]% de defesa até o fim da luta"), 6=>t("Suporte: só o efeito extra")), $r["type"]);
    $opelemento = admopcoes($elementos, $r["elemento"]);
    $listaefeitos = array(""=>t("Nenhum"));
    foreach ($efeitos as $k => $e) { $listaefeitos[$k] = t($e["rotulo"])." — ".tf($e["texto"], "X", "N"); }
    $opefeito = admopcoes($listaefeitos, $r["efeito"]);
    $opclasse = admopcoes(array(0=>t("Qualquer"), 1=>nomedaclasse(1), 2=>nomedaclasse(2), 3=>nomedaclasse(3)), intval($r["classe"]));
    $graduacoes = array(""=>t("Nenhuma"));
    foreach (jutsugraduacoes() as $g) { if ($g != "Estudante da Academia") { $graduacoes[$g] = t($g); } }
    $opgraduacao = admopcoes($graduacoes, $r["graduacao"]);
    $opexclusivo = admopcoes(array(0=>t("Não"), 1=>t("Sim: obtido apenas por missão")), intval($r["exclusivo_missao"]));
    $opuso = admopcoes(array("batalha"=>t("Batalha (luta, duelo e PvP)"), "menu"=>t("Lista rápida (menu da coluna da direita, fora de lutas)")), (string)$r["uso"]);
    $opacao = admopcoes(array(""=>t("Nenhuma (cura, se for do tipo Cura)"), "suprimentos"=>t("Invocação de Suprimentos (sorteia um item pelo tier)"), "passo"=>t("Passo Veloz (anda tier + 1 casas por passo)")), (string)$r["acao_menu"]);
    $opsenjutsu = admopcoes(array(0=>t("Não"), 1=>t("Sim: só com o Senjutsu ativo")), intval($r["requer_senjutsu"]));
    $csrf = csrf_campo();
    $botaoapagar = ($id > 0) ? "<form action=\"admin.php?do=editspell:$id\" method=\"post\">$csrf<input type=\"submit\" name=\"apagar\" value=\"".t("Apagar este jutsu")."\" class=\"botaoperigo\" onclick=\"return confirm('".t("Apagar este jutsu?")."');\" /></form>" : "";
    // Como é aprendido: níveis da tabela levels e pergaminhos (drops.ensina_jutsu).
    $comoaprende = "";
    if ($id > 0) {
        $ap = admjutsuaprendizado();
        $partes = array();
        if (isset($ap[$id]["niveis"])) { foreach ($ap[$id]["niveis"] as $nivel => $cls) { $partes[] = tf("Nível %s", $nivel)." (".implode(", ", array_map("nomedaclasse", $cls)).")"; } }
        $pergs = array();
        if (isset($ap[$id]["pergaminhos"])) { foreach ($ap[$id]["pergaminhos"] as $p) { $pergs[] = "<img src=\"".iconepergaminho($id)."\" width=\"20\" height=\"20\" style=\"vertical-align:middle\" alt=\"\" /> <a href=\"admin.php?do=editdrop:".intval($p["id"])."\">".$p["name"]." (#".intval($p["id"]).")</a>"; } }
        $comoaprende = "<h2>".t("Como é aprendido")."</h2><p>".t("Por nível:")." ".(count($partes) ? implode(", ", $partes) : t("nenhum (edite a tabela de <a href=\"admin.php?do=levels\">Níveis</a>)"))."<br />"
            .t("Pergaminho que ensina:")." ".(count($pergs) ? implode(", ", $pergs) : t("nenhum"))."</p>"
            ."<form action=\"admin.php?do=editspell:$id\" method=\"post\">$csrf<input type=\"submit\" name=\"criarpergaminho\" value=\"".t("Criar um pergaminho que ensina este jutsu")."\" /></form>";
    }

$t = "t"; $tf = "tf";
$page = <<<END
<h1>$titulo</h1>
$mensagem
$previa
<form action="admin.php?do=editspell:$id" method="post">$csrf
<table width="100%">
<tr><td width="200">{$t("Nome:")}</td><td><input type="text" name="name" size="35" maxlength="50" value="{$r["name"]}" /> {$t("Inglês:")} <input type="text" name="name_en" size="30" maxlength="50" value="{$r["name_en"]}" /></td></tr>
<tr><td>{$t("Descrição:")}</td><td><textarea name="descricao" rows="2" cols="60">{$r["descricao"]}</textarea></td></tr>
<tr><td>{$t("Descrição em inglês:")}</td><td><textarea name="descricao_en" rows="2" cols="60">{$r["descricao_en"]}</textarea></td></tr>
<tr><td>{$t("Tipo:")}</td><td><select name="type">$optipo</select></td></tr>
<tr><td>{$t("Chakra necessário:")}</td><td><input type="text" name="mp" size="5" value="{$r["mp"]}" /></td></tr>
<tr><td>{$t("Valor:")}</td><td><input type="text" name="attribute" size="5" value="{$r["attribute"]}" /> <span class="small">{$t("Poder do efeito principal (dano de cada golpe, cura, força do sono ou % do +Ataque/+Defesa). Suporte: 0.")}</span></td></tr>
<tr><td>{$t("Elemento:")}</td><td><select name="elemento">$opelemento</select></td></tr>
<tr><td>{$t("Golpes:")}</td><td><input type="text" name="golpes" size="3" value="{$r["golpes"]}" /> <span class="small">{$t("1 a 4 (só dano).")}</span> &nbsp; {$t("Roubo de vida (%):")} <input type="text" name="roubovida" size="3" value="{$r["roubovida"]}" /> <span class="small">{$t("0 a 100 (só dano).")}</span></td></tr>
<tr><td>{$t("Efeito extra:")}</td><td><select name="efeito">$opefeito</select><br />{$t("Valor:")} <input type="text" name="efeito_valor" size="5" value="{$r["efeito_valor"]}" /> {$t("Turnos:")} <input type="text" name="efeito_turnos" size="3" value="{$r["efeito_turnos"]}" /> {$t("Chance (%):")} <input type="text" name="efeito_chance" size="3" value="{$r["efeito_chance"]}" /></td></tr>
<tr><td>{$t("Recarga (turnos):")}</td><td><input type="text" name="cooldown" size="3" value="{$r["cooldown"]}" /> <span class="small">{$t("0 = pode usar todo turno.")}</span></td></tr>
<tr><td>{$t("Requisitos para aprender:")}</td><td>{$t("Nível mínimo:")} <input type="text" name="nivelminimo" size="4" value="{$r["nivelminimo"]}" /> {$t("Classe:")} <select name="classe">$opclasse</select> {$t("Graduação:")} <select name="graduacao">$opgraduacao</select><br /><span class="small">{$t("Valem para os pergaminhos. O aprendizado por nível segue a tabela de Níveis.")}</span></td></tr>
<tr><td>{$t("Exclusivo de missão:")}</td><td><select name="exclusivo_missao">$opexclusivo</select><br /><span class="small">{$t("Só pode ser ganho como recompensa de missão (campo Jutsu de recompensa). Não pode ter pergaminho nem ser ensinado por nível.")}</span></td></tr>
<tr><td>{$t("Uso:")}</td><td><select name="uso">$opuso</select> {$t("Ação de menu:")} <select name="acao_menu">$opacao</select> {$t("Tier:")} <input type="text" name="tier" size="2" value="{$r["tier"]}" /><br /><span class="small">{$t("Jutsus de Cura de batalha também aparecem na lista rápida. Suprimentos: o tier escolhe a linha da tabela de <a href=\"admin.php?do=suprimentos\">Suprimentos</a>; Passo Veloz: casas por passo = tier + 1.")}</span></td></tr>
<tr><td>{$t("Senjutsu:")}</td><td><select name="requer_senjutsu">$opsenjutsu</select> {$t("Custo de NP:")} <input type="text" name="custo_np" size="4" value="{$r["custo_np"]}" /> {$t("Custo de EP:")} <input type="text" name="custo_ep" size="4" value="{$r["custo_ep"]}" title="{$t("EP gasto por uso nos jutsus de dano com elemento. Sem EP o jutsu sai como Neutro (só gasta Chakra). 0 = sai com o elemento de graça.")}" /> {$t("Custo de TP por passo:")} <input type="text" name="custo_tp" size="4" value="{$r["custo_tp"]}" /> {$t("Recarga (minutos):")} <input type="text" name="recarga_min" size="4" value="{$r["recarga_min"]}" /><br /><span class="small">{$t("A recarga em minutos vale para os jutsus da lista rápida; a recarga em turnos vale nas lutas.")}</span></td></tr>
</table>
<input type="submit" name="previa" value="{$t("Pré-visualizar efeito")}" /> <input type="submit" name="submit" value="{$t("Salvar")}" /> <a href="admin.php?do=spells">{$t("Voltar")}</a>
</form><br />
$botaoapagar
$comoaprende
END;
    admindisplay($page, $titulo);

}


/* ------------------------------------------------------------------------
   Vilas.
   Tudo o que define uma vila está na tabela towns (nada é fixo no código): serviços (tem_*), mapa secreto,
   alinhamento/vila natal (para onde volta quem morre, opções do registro), textos em inglês e imagens:
     images/24/cidade_ID.gif (ícone 24x24) e cidade_IDp.gif (versão cinza, vila desconhecida),
     images/town_ID.gif (título 300x25; sem ele o nome aparece em texto), images/meiocidade_ID.jpg (fundo 487x284),
     layoutnovo/personagem/ID.png (Kage 115x113) e images/vila_ALINHAMENTO.gif (emblema 20x17 do alinhamento).
   A vila 1 é a vila padrão (quem tem um alinhamento sem vila volta para ela) e não pode ser apagada.
   ------------------------------------------------------------------------ */

function admvilasalinhamento() { // ID da vila => alinhamento, das vilas natais (lido da tabela towns).

    $vilas = array();
    $query = doquery("SELECT id,alinhamento FROM {{table}} WHERE vilanatal='1' AND alinhamento<>'' ORDER BY id", "towns");
    while ($row = mysqli_fetch_array($query)) { $vilas[intval($row["id"])] = $row["alinhamento"]; }
    return $vilas;

}

function admservicosvila() { // Serviços que uma vila pode ter: coluna => rótulo.

    return array("tem_pousada"=>t("Pousada"), "tem_loja"=>t("Loja de itens"), "tem_mapas"=>t("Loja de mapas"), "tem_banco"=>t("Banco"), "tem_missoes"=>t("Missões"),
        "tem_graduacao"=>t("Graduação"), "tem_alquimia"=>t("Alquimia"), "tem_senjutsu"=>t("Treino de Senjutsu e chamar Fukasaku & Shima"), "tem_jutsubusca"=>t("Treino do Jutsu de Busca"));

}

function admiconesprontos() { // Ícones prontos para vilas novas (images/24/vilas_novas/NOME.gif + NOMEp.gif).

    $lista = array();
    foreach (glob("images/24/vilas_novas/*.gif") ?: array() as $arquivo) {
        $nome = basename($arquivo, ".gif");
        if (substr($nome, -1) != "p" || !file_exists("images/24/vilas_novas/".substr($nome, 0, -1).".gif")) {
            if (preg_match("/^[a-z0-9_]+$/", $nome) && file_exists("images/24/vilas_novas/".$nome."p.gif")) { $lista[] = $nome; }
        }
    }
    return $lista;

}

function towns() {

    $mensagem = isset($_GET["apagado"]) ? admmsg(t("Vila apagada.")) : "";
    $page = "<h1>".t("Vilas")."</h1>$mensagem<p>".t("Tudo sobre as vilas fica no banco: serviços, vila natal (alinhamento), textos em inglês e imagens. Uma vila nova funciona sem mexer no código.")."</p>";
    $page .= "<a href=\"admin.php?do=edittown:0\" class=\"botao\">".t("+ Adicionar vila")."</a> <a href=\"admin.php?do=mapajogo\">".t("Ver no mapa do jogo")."</a> | <a href=\"admin.php?do=terrenos\">".t("Terrenos do mini mapa")."</a><br /><br /><table class=\"lista\"><tr><th>".t("ID")."</th><th></th><th>".t("Nome")."</th><th>".t("Kage/Líder")."</th><th>".t("Local")."</th><th>".t("Alinhamento")."</th><th>".t("Pousada")."</th><th>".t("Mapa")."</th><th>".t("TP")."</th><th>".t("Serviços")."</th><th>".t("Jogadores aqui")."</th></tr>";
    $servicos = admservicosvila();
    $query = doquery("SELECT * FROM {{table}} ORDER BY id", "towns");
    while ($row = mysqli_fetch_array($query)) {
        $icone = file_exists("images/24/cidade_".$row["id"].".gif") ? "<img src=\"images/24/cidade_".$row["id"].".gif\" alt=\"\" />" : "";
        $jogadores = admcontar("users", "latitude='".intval($row["latitude"])."' AND longitude='".intval($row["longitude"])."'");
        $tem = array();
        foreach ($servicos as $coluna => $rotulo) { if (intval($row[$coluna]) == 1) { $tem[] = $rotulo; } }
        $alinhamento = ($row["alinhamento"] != "") ? $row["alinhamento"].((intval($row["vilanatal"]) == 1) ? " <span class=\"small\">(".t("vila natal").")</span>" : "") : "-";
        $mapa = (intval($row["secreta"]) == 1) ? t("secreto") : number_format($row["mapprice"]);
        $page .= "<tr><td>".$row["id"]."</td><td>$icone</td><td><a href=\"admin.php?do=edittown:".$row["id"]."\">".$row["name"]."</a><br /><span class=\"small\">".$row["name_en"]."</span></td><td>".$row["kage"]."</td><td>[".$row["latitude"].",".$row["longitude"]."]</td><td>$alinhamento</td><td>".$row["innprice"]."</td><td>$mapa</td><td>".$row["travelpoints"]."</td><td class=\"small\">".implode(", ", $tem)."</td><td>$jogadores</td></tr>";
    }
    $page .= "</table>";
    admindisplay($page, t("Vilas"));

}

function admimagensvila($id, $alinhamento) { // Imagens de uma vila: arquivo => (rótulo, campo do formulário, largura, altura, formato).

    $imagens = array(
        "images/24/cidade_$id.gif" => array(t("Ícone (24x24)"), "icone", 24, 24, "gif"),
        "images/24/cidade_".$id."p.gif" => array(t("Ícone cinza da vila desconhecida (24x24; sem ele é gerado do ícone)"), "iconecinza", 24, 24, "gif"),
        "images/town_$id.gif" => array(t("Título (300x25; sem ele o nome aparece em texto)"), "titulo", 300, 25, "gif"),
        "images/meiocidade_$id.jpg" => array(t("Fundo da página da vila (487x284)"), "fundo", 487, 284, "jpg"),
        "layoutnovo/personagem/$id.png" => array(t("Kage (115x113)"), "kageimg", 115, 113, "png"));
    if ($alinhamento != "") { $imagens["images/vila_".$alinhamento.".gif"] = array(t("Emblema do alinhamento (20x17, lista de jogadores no mapa)"), "emblema", 20, 17, "gif"); }
    return $imagens;

}

function edittown($id) {

    global $controlrow, $link;
    $mensagem = "";
    $servicos = admservicosvila();

    if (isset($_POST["apagar"]) && $id > 0) {
        // Só apaga se não for a vila 1 (vila padrão), se nenhum jogador estiver nela agora e se nenhuma missão for entregue nela.
        $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "towns");
        if (mysqli_num_rows($query) == 1) {
            $vila = mysqli_fetch_array($query);
            $nela = admcontar("users", "latitude='".intval($vila["latitude"])."' AND longitude='".intval($vila["longitude"])."'");
            $missoes = admcontar("missoesextras", "vila_id='$id'");
            if ($id == 1) { $mensagem = admmsg(t("A vila 1 é a vila padrão (para onde volta quem tem um alinhamento sem vila) e não pode ser apagada."), "erro"); }
            elseif ($nela > 0) { $mensagem = admmsg(tf("A vila não pode ser apagada: %s jogador(es) estão nela agora. Mova-os antes (ficha do jogador &gt; Mandar para a vila).", $nela), "erro"); }
            elseif ($missoes > 0) { $mensagem = admmsg(tf("A vila não pode ser apagada: %s missão(ões) são aceitas/entregues nela. Mude a vila dessas missões antes.", $missoes), "erro"); }
            else {
                doquery("DELETE FROM {{table}} WHERE id='$id' LIMIT 1", "towns");
                // Jogadores do alinhamento desta vila passam para o alinhamento da vila padrão (morrendo, voltam para ela).
                $outra = ($vila["alinhamento"] != "") ? admcontar("towns", "alinhamento='".addslashes($vila["alinhamento"])."'") : 1;
                if ($vila["alinhamento"] != "" && $outra == 0) {
                    $padrao = mysqli_fetch_array(doquery("SELECT alinhamento FROM {{table}} WHERE id='1' LIMIT 1", "towns"));
                    $novo = ($padrao && $padrao["alinhamento"] != "") ? $padrao["alinhamento"] : "folha";
                    doquery("UPDATE {{table}} SET alinhamento='".addslashes($novo)."' WHERE alinhamento='".addslashes($vila["alinhamento"])."'", "users");
                }
                // Apaga as imagens próprias, para que uma futura vila com o mesmo ID não as herde.
                $arquivos = array_keys(admimagensvila($id, ($outra == 0) ? $vila["alinhamento"] : ""));
                foreach ($arquivos as $arquivo) {
                    if (file_exists($arquivo) && is_writable($arquivo)) { @unlink($arquivo); }
                }
                header("Location: admin.php?do=towns&apagado=1"); die();
            }
        }
    }

    $postado = null;
    if (isset($_POST["submit"])) {
        $erros = "";
        $tamanho = intval($controlrow["gamesize"]);
        $name = admtexto("name", t("Nome"), $erros, true, 30);
        $name_en = admtexto("name_en", t("Nome em inglês"), $erros, false, 30);
        $kage = admtexto("kage", t("Kage/líder"), $erros, false, 20);
        $kage_en = admtexto("kage_en", t("Kage/líder em inglês"), $erros, false, 20);
        $descricao = admtexto("descricao", t("Descrição"), $erros, false, 1000);
        $descricao_en = admtexto("descricao_en", t("Descrição em inglês"), $erros, false, 1000);
        $latitude = intval(admnumero("latitude", t("Latitude"), $erros, -$tamanho, $tamanho));
        $longitude = intval(admnumero("longitude", t("Longitude"), $erros, -$tamanho, $tamanho));
        $innprice = intval(admnumero("innprice", t("Preço da pousada"), $erros, 0, 32000));
        $nivelbase = intval(admnumero("nivelbase", t("Nível base dos inimigos ao redor"), $erros, 0, 200));
        $mapprice = intval(admnumero("mapprice", t("Preço do mapa"), $erros, 0, 8388607));
        $travelpoints = intval(admnumero("travelpoints", t("TP para viajar"), $erros, 0, 65535));
        // Alinhamento: código curto (letras minúsculas, números e "_"); vira valor de users.alinhamento e nome de imagem.
        $alinhamento = strtolower(admtexto("alinhamento", t("Alinhamento"), $erros, false, 10));
        if ($alinhamento != "" && !preg_match("/^[a-z0-9_]{1,10}$/", $alinhamento)) { $erros .= t("O alinhamento deve ter só letras minúsculas sem acento, números ou \"_\" (até 10).")."<br />"; }
        $vilanatal = (pegarint($_POST, "vilanatal") == 1) ? 1 : 0;
        if ($vilanatal == 1 && $alinhamento == "") { $erros .= t("Uma vila natal precisa de um código de alinhamento.")."<br />"; }
        if ($alinhamento != "" && admcontar("towns", "alinhamento='".addslashes($alinhamento)."' AND id<>'$id'") > 0) { $erros .= t("Já existe outra vila com esse alinhamento.")."<br />"; }
        $secreta = (pegarint($_POST, "secreta") == 1) ? 1 : 0;
        $tem = array();
        foreach ($servicos as $coluna => $rotulo) { $tem[$coluna] = (pegarint($_POST, $coluna) == 1) ? 1 : 0; }
        // Itens à venda: caixas de seleção (itens[]); só IDs que existem na tabela de itens.
        $escolhidos = (isset($_POST["itens"]) && is_array($_POST["itens"])) ? array_unique(array_map("intval", $_POST["itens"])) : array();
        $validos = array();
        if (count($escolhidos) > 0) {
            $query = doquery("SELECT id FROM {{table}} WHERE id IN (".implode(",", $escolhidos).") ORDER BY id", "items");
            while ($row = mysqli_fetch_array($query)) { $validos[] = intval($row["id"]); }
        }
        $itemslist = implode(",", $validos);
        $outra = doquery("SELECT id,name FROM {{table}} WHERE latitude='$latitude' AND longitude='$longitude' AND id<>'$id' LIMIT 1", "towns");
        if (mysqli_num_rows($outra) > 0) { $outrarow = mysqli_fetch_array($outra); $erros .= tf("Já existe uma vila nessa posição (%s).", $outrarow["name"])."<br />"; }
        $mesmonome = doquery("SELECT id FROM {{table}} WHERE name='$name' AND id<>'$id' LIMIT 1", "towns");
        if ($name != "" && mysqli_num_rows($mesmonome) > 0) { $erros .= t("Já existe uma vila com esse nome.")."<br />"; }
        if ($id == 0 && admcontar("towns") >= 255) { $erros .= t("O jogo já tem o máximo de 255 vilas.")."<br />"; }
        if ($erros == "") {
            $campos = "name='$name', name_en='$name_en', kage='$kage', kage_en='$kage_en', descricao='$descricao', descricao_en='$descricao_en', latitude='$latitude', longitude='$longitude', innprice='$innprice', nivelbase='$nivelbase', mapprice='$mapprice', travelpoints='$travelpoints', itemslist='$itemslist', alinhamento='$alinhamento', vilanatal='$vilanatal', secreta='$secreta'";
            foreach ($tem as $coluna => $valor) { $campos .= ", $coluna='$valor'"; }
            if ($id == 0) {
                doquery("INSERT INTO {{table}} SET $campos", "towns"); $id = mysqli_insert_id($link);
                $mensagem = admmsg(tf("Vila criada com o ID %s.", $id));
            } else {
                $antiga = mysqli_fetch_array(doquery("SELECT latitude,longitude,alinhamento FROM {{table}} WHERE id='$id' LIMIT 1", "towns"));
                doquery("UPDATE {{table}} SET $campos WHERE id='$id' LIMIT 1", "towns");
                // Jogadores que estavam na vila (na coordenada antiga) vão junto com ela.
                if ($antiga && ($antiga["latitude"] != $latitude || $antiga["longitude"] != $longitude)) {
                    doquery("UPDATE {{table}} SET latitude='$latitude', longitude='$longitude' WHERE currentaction='In Town' AND latitude='".intval($antiga["latitude"])."' AND longitude='".intval($antiga["longitude"])."'", "users");
                }
                // Código do alinhamento trocado: os jogadores do alinhamento antigo passam para o novo (e o emblema vai junto).
                if ($antiga && $antiga["alinhamento"] != "" && $alinhamento != "" && $antiga["alinhamento"] != $alinhamento) {
                    doquery("UPDATE {{table}} SET alinhamento='$alinhamento' WHERE alinhamento='".addslashes($antiga["alinhamento"])."'", "users");
                    $emblemaantigo = "images/vila_".$antiga["alinhamento"].".gif";
                    if (file_exists($emblemaantigo) && !file_exists("images/vila_".$alinhamento.".gif")) { @copy($emblemaantigo, "images/vila_".$alinhamento.".gif"); }
                }
                $mensagem = admmsg(t("Vila atualizada."));
            }
            // Imagens da vila (todas opcionais).
            foreach (admimagensvila($id, $alinhamento) as $arquivo => $u) {
                $erroupload = admupload($u[1], $arquivo, $u[2], $u[3], $u[4]);
                if ($erroupload != "") { $mensagem .= admmsg(tf("Imagem %s não salva: %s", $u[0], $erroupload), "erro"); }
            }
            // Ícone pronto escolhido (images/24/vilas_novas/NOME.gif e NOMEp.gif): copiado para o ícone da vila.
            $pronto = pegartexto($_POST, "iconepronto");
            if ($pronto != "" && in_array($pronto, admiconesprontos(), true)) {
                @copy("images/24/vilas_novas/$pronto.gif", "images/24/cidade_$id.gif");
                @copy("images/24/vilas_novas/".$pronto."p.gif", "images/24/cidade_".$id."p.gif");
            }
            // Ícone novo sem ícone cinza enviado junto: a versão cinza é gerada a partir dele.
            $enviou = function ($campo) { return isset($_FILES[$campo]) && $_FILES[$campo]["error"] == UPLOAD_ERR_OK; };
            if ($enviou("icone") && !$enviou("iconecinza")) { admversaocinza("images/24/cidade_$id.gif", "images/24/cidade_".$id."p.gif"); }
            // Sem imagem própria, o ícone, o Kage e o emblema usam cópias das imagens padrão (o título não: vira texto).
            $padroes = array("images/24/cidade_$id.gif"=>"images/24/cidade_1.gif", "images/24/cidade_".$id."p.gif"=>"images/24/cidade_1p.gif", "layoutnovo/personagem/$id.png"=>"layoutnovo/personagem/1.png");
            if ($alinhamento != "") { $padroes["images/vila_".$alinhamento.".gif"] = "images/vila_folha.gif"; }
            foreach ($padroes as $destino => $origem) {
                if (!file_exists($destino) && file_exists($origem) && is_writable(dirname($destino))) { @copy($origem, $destino); }
            }
        } else {
            $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro");
            $postado = array("name"=>stripslashes($name), "name_en"=>stripslashes($name_en), "kage"=>stripslashes($kage), "kage_en"=>stripslashes($kage_en), "descricao"=>stripslashes($descricao), "descricao_en"=>stripslashes($descricao_en), "latitude"=>pegartexto($_POST, "latitude"), "longitude"=>pegartexto($_POST, "longitude"), "innprice"=>pegartexto($_POST, "innprice"), "nivelbase"=>pegartexto($_POST, "nivelbase"), "mapprice"=>pegartexto($_POST, "mapprice"), "travelpoints"=>pegartexto($_POST, "travelpoints"), "itemslist"=>$itemslist, "alinhamento"=>stripslashes($alinhamento), "vilanatal"=>$vilanatal, "secreta"=>$secreta) + $tem;
        }
    }

    if ($id > 0) {
        $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "towns");
        if (mysqli_num_rows($query) != 1) { admindisplay(admmsg(t("Vila não encontrada."), "erro"), t("Vilas")); }
        $r = mysqli_fetch_array($query); $titulo = tf("Editar vila #%s", $id);
    } else {
        $r = array("name"=>"", "name_en"=>"", "kage"=>"", "kage_en"=>"", "descricao"=>"", "descricao_en"=>"", "latitude"=>0, "longitude"=>0, "innprice"=>10, "nivelbase"=>0, "mapprice"=>1000, "travelpoints"=>10, "itemslist"=>"", "alinhamento"=>"", "vilanatal"=>0, "secreta"=>0,
            "tem_pousada"=>1, "tem_loja"=>1, "tem_mapas"=>1, "tem_banco"=>1, "tem_missoes"=>1, "tem_graduacao"=>0, "tem_alquimia"=>0, "tem_senjutsu"=>0, "tem_jutsubusca"=>0);
        $titulo = t("Nova vila");
    }
    if ($postado !== null) { $r = array_merge($r, $postado); }

    // Seletor dos itens à venda: todos os itens da loja, agrupados pelo tipo, com os da vila marcados.
    $tipos = array(1=>t("Arma"), 2=>t("Colete"), 3=>t("Bandana/Chapéu"));
    $navila = array_map("intval", explode(",", $r["itemslist"]));
    $seletor = ""; $tipoatual = -1;
    $query = doquery("SELECT id,type,name,buycost FROM {{table}} ORDER BY type, buycost, id", "items");
    while ($row = mysqli_fetch_array($query)) {
        if ($row["type"] != $tipoatual) { $tipoatual = $row["type"]; $seletor .= "<div style=\"font-weight:bold; margin-top:4px\">".(isset($tipos[$tipoatual]) ? $tipos[$tipoatual] : $tipoatual)."</div>"; }
        $marcado = in_array(intval($row["id"]), $navila, true) ? "checked=\"checked\"" : "";
        $seletor .= "<label style=\"display:inline-block; width:220px\"><input type=\"checkbox\" name=\"itens[]\" value=\"".$row["id"]."\" $marcado /> #".$row["id"]." ".$row["name"]." <span class=\"small\">(".number_format($row["buycost"]).")</span></label>";
    }

    // Caixas dos serviços.
    $caixas = "";
    foreach ($servicos as $coluna => $rotulo) {
        $caixas .= "<label style=\"display:inline-block; width:260px\"><input type=\"checkbox\" name=\"$coluna\" value=\"1\" ".((intval($r[$coluna]) == 1) ? "checked=\"checked\"" : "")." /> $rotulo</label>";
    }
    $marcadonatal = (intval($r["vilanatal"]) == 1) ? "checked=\"checked\"" : "";
    $marcadosecreta = (intval($r["secreta"]) == 1) ? "checked=\"checked\"" : "";

    $imagens = "";
    foreach (admimagensvila($id, $r["alinhamento"]) as $arquivo => $u) {
        $imagens .= "<tr><td>".$u[0].":</td><td>".(($id > 0 && file_exists($arquivo)) ? "<img src=\"$arquivo?".filemtime($arquivo)."\" alt=\"\" style=\"max-width:300px\" /><br />" : "<span class=\"small\">".t("não enviada (usa imagem padrão)")."</span><br />");
        $imagens .= "<input type=\"file\" name=\"".$u[1]."\" accept=\"image/*\" /></td></tr>";
    }
    $prontos = "<label style=\"display:inline-block;margin-right:8px\"><input type=\"radio\" name=\"iconepronto\" value=\"\" checked=\"checked\" /> ".t("manter")."</label>";
    foreach (admiconesprontos() as $nome) { $prontos .= "<label style=\"display:inline-block;margin-right:8px\"><input type=\"radio\" name=\"iconepronto\" value=\"$nome\" /> <img src=\"images/24/vilas_novas/$nome.gif\" alt=\"$nome\" title=\"$nome\" /></label>"; }
    $imagens .= "<tr><td>".t("Escolher ícone pronto:")."</td><td>$prontos<br /><span class=\"small\">".t("Substitui o ícone e a versão cinza da vila.")."</span></td></tr>";
    if ($r["alinhamento"] == "") { $imagens .= "<tr><td></td><td><span class=\"small\">".t("Preencha o alinhamento e salve para enviar o emblema do alinhamento.")."</span></td></tr>"; }
    $jogadoresaqui = ($id > 0) ? admcontar("users", "latitude='".intval($r["latitude"])."' AND longitude='".intval($r["longitude"])."'") : 0;
    $doalinhamento = ($id > 0 && $r["alinhamento"] != "") ? admcontar("users", "alinhamento='".addslashes($r["alinhamento"])."'") : 0;
    $csrf = csrf_campo();
    if ($id == 0) { $botaoapagar = ""; }
    elseif ($id == 1) { $botaoapagar = "<span class=\"small\">".t("A vila 1 é a vila padrão e não pode ser apagada.")."</span>"; }
    elseif ($jogadoresaqui > 0) { $botaoapagar = "<span class=\"small\">".tf("Para apagar esta vila, ela precisa estar vazia (%s jogador(es) nela agora).", $jogadoresaqui)."</span>"; }
    else {
        $aviso = ($doalinhamento > 0) ? " ".tf("%s jogador(es) deste alinhamento passarão para o alinhamento da vila 1.", $doalinhamento) : "";
        $botaoapagar = "<form action=\"admin.php?do=edittown:$id\" method=\"post\">$csrf<input type=\"submit\" name=\"apagar\" value=\"".t("Apagar esta vila")."\" class=\"botaoperigo\" onclick=\"return confirm('".t("Apagar esta vila? Os mapas comprados dela deixam de valer.").$aviso."');\" /></form>";
    }

$t = "t"; $tf = "tf";
$page = <<<END
<h1>$titulo</h1>
$mensagem
<form action="admin.php?do=edittown:$id" method="post" enctype="multipart/form-data">$csrf
<table width="100%">
<tr><td width="200">{$t("Nome:")}</td><td><input type="text" name="name" size="30" maxlength="30" value="{$r["name"]}" /> {$t("em inglês:")} <input type="text" name="name_en" size="30" maxlength="30" value="{$r["name_en"]}" /></td></tr>
<tr><td>{$t("Kage/líder:")}</td><td><input type="text" name="kage" size="20" maxlength="20" value="{$r["kage"]}" /> {$t("em inglês:")} <input type="text" name="kage_en" size="20" maxlength="20" value="{$r["kage_en"]}" /> <span class="small">{$t("NPC que fala com o jogador nas missões e treinos da vila.")}</span></td></tr>
<tr><td>{$t("Descrição:")}</td><td><textarea name="descricao" rows="2" cols="60">{$r["descricao"]}</textarea><br />{$t("em inglês:")}<br /><textarea name="descricao_en" rows="2" cols="60">{$r["descricao_en"]}</textarea><br /><span class="small">{$t("Aparece abaixo do título na página da vila e na Ajuda (opcional).")}</span></td></tr>
<tr><td>{$t("Latitude / longitude:")}</td><td><input type="text" name="latitude" size="6" value="{$r["latitude"]}" /> <input type="text" name="longitude" size="6" value="{$r["longitude"]}" /> <span class="small">{$t("Os inimigos ao redor terão nível ~ (maior distância do centro / 5).")} {$t("Jogadores que estão na vila acompanham a mudança de posição.")}</span></td></tr>
<tr><td>{$t("Preço da pousada:")}</td><td><input type="text" name="innprice" size="6" value="{$r["innprice"]}" /> {$t("Ryou")}</td></tr>
<tr><td>{$t("Nível base dos inimigos ao redor:")}</td><td><input type="text" name="nivelbase" size="6" value="{$r["nivelbase"]}" /> <span class="small">{$t("Até 60 casas da vila, os inimigos têm este nível + 1 a cada 5 casas (se for menor que a regra do centro). 0 = sem efeito.")}</span></td></tr>
<tr><td>{$t("Preço do mapa:")}</td><td><input type="text" name="mapprice" size="8" value="{$r["mapprice"]}" /> {$t("Ryou")} <span class="small">{$t("(comprado em outras vilas para poder viajar até esta)")}</span> <label><input type="checkbox" name="secreta" value="1" $marcadosecreta /> {$t("Mapa secreto (não é vendido: é preciso descobrir a vila andando)")}</label></td></tr>
<tr><td>{$t("TP para viajar:")}</td><td><input type="text" name="travelpoints" size="5" value="{$r["travelpoints"]}" /></td></tr>
<tr><td>{$t("Serviços da vila:")}</td><td>$caixas</td></tr>
<tr><td>{$t("Itens à venda:")}</td><td><div style="max-height:220px; overflow:auto; border:1px solid #c9b47a; padding:4px; background:#fffbea">$seletor</div></td></tr>
<tr><td>{$t("Alinhamento:")}</td><td><input type="text" name="alinhamento" size="10" maxlength="10" value="{$r["alinhamento"]}" /> <label><input type="checkbox" name="vilanatal" value="1" $marcadonatal /> {$t("Vila natal (aparece no registro e recebe de volta quem morre com esse alinhamento)")}</label><br /><span class="small">{$t("Código curto gravado em users.alinhamento (ex.: folha, pedra, nevoa). Jogadores com um alinhamento sem vila voltam para a vila 1.")} {$tf("Jogadores com este alinhamento: %s.", $doalinhamento)}</span></td></tr>
$imagens
</table>
<input type="submit" name="submit" value="{$t("Salvar")}" /> <a href="admin.php?do=towns">{$t("Voltar")}</a>
</form><br />
$botaoapagar
END;
    admindisplay($page, $titulo);

}


/* ------------------------------------------------------------------------
   Terrenos do mini mapa (tabela terrenos).
   Cada terreno é um retângulo de coordenadas com uma imagem de chão (images/terreno_NOME.jpg, 155x155) e uma
   prioridade: a casa do mapa usa o terreno de maior prioridade que a contém (templates/minimap.php).
   "arvores" = 0 tira as decorações sorteadas (água...). Ladrilhos por casa: images/terreno/ (tools/gerar_tiles_terreno.py).
   ------------------------------------------------------------------------ */

function terrenos() {

    $mensagem = isset($_GET["apagado"]) ? admmsg(t("Terreno apagado.")) : "";
    $cores = array("#e00000", "#0050e0", "#00a000", "#b000b0", "#e07000", "#008080", "#806000", "#e00080");
    $page = "<h1>".t("Terrenos do mini mapa")."</h1>$mensagem<p>".t("O chão de cada casa do mini mapa vem do terreno de maior prioridade cujo retângulo contém a casa (as bordas são levemente irregulares). A imagem fica em <b>images/terreno_NOME.jpg</b> (155x155); os ladrilhos 30x30 de cada casa (chão, bordas e decorações) ficam em <b>images/terreno/</b> e são gerados dela por <b>tools/gerar_tiles_terreno.py</b> (rode de novo após trocar a imagem; sem ladrilhos, a imagem inteira é usada).")."</p>";
    $page .= "<a href=\"admin.php?do=editterreno:0\" class=\"botao\">".t("+ Adicionar terreno")."</a> <a href=\"admin.php?do=towns\">".t("Vilas")."</a><br /><br />";
    $page .= "<table class=\"lista\"><tr><th>".t("ID")."</th><th></th><th>".t("Nome")."</th><th>".t("Região")."</th><th>".t("Prioridade")."</th><th>".t("Árvores")."</th></tr>";
    $camadas = ""; $n = 0;
    $query = doquery("SELECT * FROM {{table}} ORDER BY prioridade, id", "terrenos");
    while ($row = mysqli_fetch_array($query)) {
        $cor = $cores[$n % count($cores)]; $n++;
        $imagem = file_exists("images/".$row["imagem"]) ? "<img src=\"images/".$row["imagem"]."\" alt=\"\" style=\"width:31px;height:31px\" />" : "<span class=\"small\">".t("sem imagem")."</span>";
        $page .= "<tr><td>".$row["id"]."</td><td>$imagem</td><td><a href=\"admin.php?do=editterreno:".$row["id"]."\" style=\"color:$cor\">".$row["nome"]."</a></td><td>[".$row["latmax"].",".$row["lonmin"]."] ".t("até")." [".$row["latmin"].",".$row["lonmax"]."]</td><td>".$row["prioridade"]."</td><td>".((intval($row["arvores"]) == 1) ? t("Sim") : t("Não"))."</td></tr>";
        if (intval($row["prioridade"]) > 0) { $camadas .= admretangulo($row["latmin"], $row["latmax"], $row["lonmin"], $row["lonmax"], 401, "border:2px solid $cor; background:".$cor."22", "<span style=\"font-size:10px; color:$cor; font-weight:bold\">".$row["nome"]."</span>"); }
    }
    $page .= "</table><br />".admmapa(401, $camadas);
    admindisplay($page, t("Terrenos do mini mapa"));

}

function editterreno($id) {

    global $controlrow, $link;
    $mensagem = "";

    if (isset($_POST["apagar"]) && $id > 0) {
        doquery("DELETE FROM {{table}} WHERE id='$id' LIMIT 1", "terrenos");
        header("Location: admin.php?do=terrenos&apagado=1"); die();
    }

    $postado = null;
    if (isset($_POST["submit"])) {
        $erros = "";
        $tamanho = intval($controlrow["gamesize"]);
        // O nome vira parte do nome do arquivo (images/terreno_NOME.jpg): só letras minúsculas, números e "_".
        $nome = strtolower(admtexto("nome", t("Nome"), $erros, true, 20));
        if ($nome != "" && !preg_match("/^[a-z0-9_]{1,20}$/", $nome)) { $erros .= t("O nome deve ter só letras minúsculas sem acento, números ou \"_\" (até 20).")."<br />"; }
        $latmin = intval(admnumero("latmin", t("Latitude mínima"), $erros, -$tamanho, $tamanho));
        $latmax = intval(admnumero("latmax", t("Latitude máxima"), $erros, -$tamanho, $tamanho));
        $lonmin = intval(admnumero("lonmin", t("Longitude mínima"), $erros, -$tamanho, $tamanho));
        $lonmax = intval(admnumero("lonmax", t("Longitude máxima"), $erros, -$tamanho, $tamanho));
        if ($latmin > $latmax || $lonmin > $lonmax) { $erros .= t("O mínimo de cada coordenada deve ser menor ou igual ao máximo.")."<br />"; }
        $prioridade = intval(admnumero("prioridade", t("Prioridade"), $erros, -1000, 1000));
        $arvores = (pegarint($_POST, "arvores") == 1) ? 1 : 0;
        $imagem = "terreno_".$nome.".jpg";
        if ($erros == "") {
            $campos = "nome='$nome', imagem='$imagem', latmin='$latmin', latmax='$latmax', lonmin='$lonmin', lonmax='$lonmax', prioridade='$prioridade', arvores='$arvores'";
            if ($id == 0) { doquery("INSERT INTO {{table}} SET $campos", "terrenos"); $id = mysqli_insert_id($link); $mensagem = admmsg(tf("Terreno criado com o ID %s.", $id)); }
            else { doquery("UPDATE {{table}} SET $campos WHERE id='$id' LIMIT 1", "terrenos"); $mensagem = admmsg(t("Terreno atualizado.")); }
            $erroupload = admupload("imagem", "images/".$imagem, 155, 155, "jpg");
            if ($erroupload != "") { $mensagem .= admmsg(tf("Imagem %s não salva: %s", $imagem, $erroupload), "erro"); }
            if (!file_exists("images/".$imagem)) { $mensagem .= admmsg(tf("A imagem images/%s ainda não existe: enquanto isso essas casas usam a grama padrão.", $imagem), "aviso"); }
        } else {
            $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro");
            $postado = array("nome"=>stripslashes($nome), "latmin"=>pegartexto($_POST, "latmin"), "latmax"=>pegartexto($_POST, "latmax"), "lonmin"=>pegartexto($_POST, "lonmin"), "lonmax"=>pegartexto($_POST, "lonmax"), "prioridade"=>pegartexto($_POST, "prioridade"), "arvores"=>$arvores, "imagem"=>$imagem);
        }
    }

    if ($id > 0) {
        $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "terrenos");
        if (mysqli_num_rows($query) != 1) { admindisplay(admmsg(t("Terreno não encontrado."), "erro"), t("Terrenos do mini mapa")); }
        $r = mysqli_fetch_array($query); $titulo = tf("Editar terreno #%s", $id);
    } else { $r = array("nome"=>"", "imagem"=>"", "latmin"=>-10, "latmax"=>10, "lonmin"=>-10, "lonmax"=>10, "prioridade"=>10, "arvores"=>1); $titulo = t("Novo terreno"); }
    if ($postado !== null) { $r = array_merge($r, $postado); }

    $imagematual = ($r["imagem"] != "" && file_exists("images/".$r["imagem"])) ? "<img src=\"images/".$r["imagem"]."?".filemtime("images/".$r["imagem"])."\" alt=\"\" /><br />" : "<span class=\"small\">".t("não enviada (usa a grama padrão)")."</span><br />";
    $marcadoarvores = (intval($r["arvores"]) == 1) ? "checked=\"checked\"" : "";
    $mapa = admmapa(401, admretangulo($r["latmin"], $r["latmax"], $r["lonmin"], $r["lonmax"], 401, "border:2px solid #e00000; background:#e0000033"));
    $csrf = csrf_campo();
    $botaoapagar = ($id > 0) ? "<form action=\"admin.php?do=editterreno:$id\" method=\"post\">$csrf<input type=\"submit\" name=\"apagar\" value=\"".t("Apagar este terreno")."\" class=\"botaoperigo\" onclick=\"return confirm('".t("Apagar este terreno?")."');\" /></form>" : "";

$t = "t";
$page = <<<END
<h1>$titulo</h1>
$mensagem
<form action="admin.php?do=editterreno:$id" method="post" enctype="multipart/form-data">$csrf
<table width="100%">
<tr><td width="200">{$t("Nome:")}</td><td><input type="text" name="nome" size="20" maxlength="20" value="{$r["nome"]}" /> <span class="small">{$t("Letras minúsculas sem acento (ex.: deserto). A imagem será images/terreno_NOME.jpg.")}</span></td></tr>
<tr><td>{$t("Latitude (mín. / máx.):")}</td><td><input type="text" name="latmin" size="6" value="{$r["latmin"]}" /> <input type="text" name="latmax" size="6" value="{$r["latmax"]}" /></td></tr>
<tr><td>{$t("Longitude (mín. / máx.):")}</td><td><input type="text" name="lonmin" size="6" value="{$r["lonmin"]}" /> <input type="text" name="lonmax" size="6" value="{$r["lonmax"]}" /></td></tr>
<tr><td>{$t("Prioridade:")}</td><td><input type="text" name="prioridade" size="6" value="{$r["prioridade"]}" /> <span class="small">{$t("Onde dois terrenos se cruzam, vale o de maior prioridade.")}</span></td></tr>
<tr><td>{$t("Árvores:")}</td><td><label><input type="checkbox" name="arvores" value="1" $marcadoarvores /> {$t("Mostrar as decorações do bioma neste terreno (árvores, pinheiros, pedras, cactos, juncos)")}</label></td></tr>
<tr><td>{$t("Imagem (155x155):")}</td><td>$imagematual<input type="file" name="imagem" accept="image/*" /></td></tr>
</table>
<input type="submit" name="submit" value="{$t("Salvar")}" /> <a href="admin.php?do=terrenos">{$t("Voltar")}</a>
</form><br />
$botaoapagar
<br />$mapa
END;
    admindisplay($page, $titulo);

}


/* ------------------------------------------------------------------------
   Mapa do jogo (somente leitura): vilas e regiões dos inimigos sobre a imagem do mapa global.
   ------------------------------------------------------------------------ */




/* ------------------------------------------------------------------------
   Níveis (experiência necessária e ganhos por especialização).
   ------------------------------------------------------------------------ */

function levels() {

    global $controlrow;
    $page = "<h1>".t("Níveis")."</h1><p>".t("Cada nível define a experiência total necessária e quanto o personagem ganha ao chegar nele, para cada especialização. A coluna \"jutsu\" é o ID do jutsu aprendido naquele nível (0 = nenhum).")."</p>";
    $page .= "<form action=\"admin.php?do=editlevel\" method=\"post\">".csrf_campo().t("Editar o nível:")." <select name=\"level\">";
    $query = doquery("SELECT id FROM {{table}} ORDER BY id", "levels");
    while ($row = mysqli_fetch_array($query)) { $page .= "<option value=\"".$row["id"]."\">".$row["id"]."</option>"; }
    $page .= "</select> <input type=\"submit\" name=\"go\" value=\"".t("Editar")."\" /></form><br />";
    $page .= "<table class=\"lista\"><tr><th>".t("Nível")."</th><th>".tf("Exp. (%s)", $controlrow["class1name"])."</th><th>".tf("Exp. (%s)", $controlrow["class2name"])."</th><th>".tf("Exp. (%s)", $controlrow["class3name"])."</th><th>".t("Jutsus")."</th></tr>";
    $query = doquery("SELECT * FROM {{table}} ORDER BY id", "levels");
    while ($row = mysqli_fetch_array($query)) {
        $page .= "<tr><td>".$row["id"]."</td><td>".number_format($row["1_exp"])."</td><td>".number_format($row["2_exp"])."</td><td>".number_format($row["3_exp"])."</td><td>".$row["1_spells"]." / ".$row["2_spells"]." / ".$row["3_spells"]."</td></tr>";
    }
    $page .= "</table>";
    admindisplay($page, t("Níveis"));

}

function editlevel() {

    global $controlrow;
    $id = pegarint($_POST, "level");
    $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "levels");
    if (mysqli_num_rows($query) != 1) { admindisplay(admmsg(t("Nível não encontrado."), "erro"), t("Níveis")); }
    $mensagem = "";
    $colunas = array("exp"=>array(t("Experiência total"), 16777215), "hp"=>array(t("HP ganho"), 65535), "mp"=>array(t("Chakra ganho"), 65535), "tp"=>array(t("TP ganho"), 65535), "strength"=>array(t("Força ganha"), 65535), "dexterity"=>array(t("Destreza ganha"), 65535), "spells"=>array(t("Jutsu aprendido (ID)"), 255));

    if (isset($_POST["submit"])) {
        $erros = "";
        $sets = array();
        for ($classe = 1; $classe <= 3; $classe++) {
            foreach ($colunas as $coluna => $regra) {
                $valor = intval(admnumero($classe."_".$coluna, $regra[0]." (".$controlrow["class".$classe."name"].")", $erros, 0, $regra[1]));
                if ($coluna == "spells" && $valor > 0 && mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE id='$valor' AND exclusivo_missao='1' LIMIT 1", "spells")) == 1) { $erros .= tf("O jutsu %s é exclusivo de missão e não pode ser ensinado por nível.", $valor)."<br />"; }
                $sets[] = "`".$classe."_".$coluna."`='$valor'";
            }
        }
        if ($erros == "") { doquery("UPDATE {{table}} SET ".implode(", ", $sets)." WHERE id='$id' LIMIT 1", "levels"); $mensagem = admmsg(tf("Nível %s atualizado.", $id)); }
        else { $mensagem = admmsg("<b>".t("Corrija os erros abaixo:")."</b><br />$erros", "erro"); }
        $query = doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "levels");
    }
    $r = mysqli_fetch_array($query);

    $tabela = "<table class=\"lista\"><tr><th></th><th>".$controlrow["class1name"]."</th><th>".$controlrow["class2name"]."</th><th>".$controlrow["class3name"]."</th></tr>";
    foreach ($colunas as $coluna => $regra) {
        $tabela .= "<tr><td>".$regra[0]."</td>";
        for ($classe = 1; $classe <= 3; $classe++) { $tabela .= "<td><input type=\"text\" name=\"".$classe."_".$coluna."\" size=\"10\" value=\"".$r[$classe."_".$coluna]."\" /></td>"; }
        $tabela .= "</tr>";
    }
    $tabela .= "</table>";
    $csrf = csrf_campo();

$t = "t"; $tf = "tf";
$page = <<<END
<h1>{$tf("Editar nível %s", $id)}</h1>
$mensagem
<form action="admin.php?do=editlevel" method="post">$csrf<input type="hidden" name="level" value="$id" />
$tabela<br />
<span class="small">{$t("A experiência é o TOTAL acumulado para chegar neste nível (não a diferença para o anterior).")}</span><br /><br />
<input type="submit" name="submit" value="{$t("Salvar nível")}" /> <a href="admin.php?do=levels">{$t("Voltar")}</a>
</form>
END;
    admindisplay($page, tf("Nível %s", $id));

}

/* ------------------------------------------------------------------------
   Especializações (tabela classes): modificadores de combate de cada classe, bônus do Senjutsu ativo e o nível
   mínimo para escolher (control.classenivel). Regras em funcoesjutsus.php (classemods) e docs/ESPECIALIZACOES.md.
   ------------------------------------------------------------------------ */

function classes() {

    global $controlrow;
    include_once('funcoesinclusas.php');
    $t = "t";
    $csrf = csrf_campo();
    $mensagem = "";
    $campos = array_merge(classecampos(), classecampossenjutsu());
    $rotulos = array("dano_fisico"=>t("Dano físico %"), "critico"=>t("Crítico (pontos)"), "dano_jutsu"=>t("Dano de jutsus %"), "dano_elemental"=>t("Dano elemental %"),
        "vantagem_elemento"=>t("Vantagem de elemento %"), "defesa_fisica"=>t("Defesa física %"), "chance_efeito"=>t("Chance de efeitos (pontos)"), "duracao_efeito"=>t("Duração de efeitos (turnos)"),
        "valor_efeito"=>t("Força de efeitos %"), "dreno"=>t("Dreno de Chakra %"), "resist_debuff"=>t("Resistência a efeitos %"), "economia_tecnica"=>t("Economia em técnicas sem elemento %"), "buff_proprio"=>t("Força dos reforços em si mesmo %"),
        "sj_ataque"=>t("Senjutsu: Poder de Ataque %"), "sj_defesa"=>t("Senjutsu: Poder de Defesa %"), "sj_dano_fisico"=>t("Senjutsu: dano físico %"), "sj_critico"=>t("Senjutsu: crítico"),
        "sj_dano_elemental"=>t("Senjutsu: dano elemental %"), "sj_economia"=>t("Senjutsu: economia de Chakra %"), "sj_chance_efeito"=>t("Senjutsu: chance de efeitos"),
        "sj_duracao_efeito"=>t("Senjutsu: duração de efeitos"), "sj_resist_debuff"=>t("Senjutsu: resistência %"));
    if (isset($_POST["submit"])) {
        $erros = "";
        $nivel = admnumero("classenivel", t("Nível para escolher"), $erros, 1, 100);
        $sql = array();
        for ($c = 1; $c <= 3; $c++) {
            $sets = array();
            foreach ($campos as $campo) { $sets[] = "`$campo`='".intval(admnumero("c{$c}_$campo", $rotulos[$campo], $erros, -100, 300))."'"; }
            $sets[] = "descricao='".admtexto("c{$c}_descricao", t("Descrição"), $erros, false, 2000)."'";
            $sets[] = "descricao_en='".admtexto("c{$c}_descricao_en", t("Descrição"), $erros, false, 2000)."'";
            $sql[$c] = implode(",", $sets);
        }
        if ($erros == "") {
            doquery("UPDATE {{table}} SET classenivel='".intval($nivel)."' WHERE id='1' LIMIT 1", "control");
            foreach ($sql as $c => $sets) { doquery("UPDATE {{table}} SET $sets WHERE id='".intval($c)."' LIMIT 1", "classes"); }
            $controlrow["classenivel"] = intval($nivel);
            $mensagem = admmsg(t("Especializações salvas."));
        } else { $mensagem = admmsg($erros, "erro"); }
    }
    $linhas = array();
    $q = doquery("SELECT * FROM {{table}} ORDER BY id", "classes");
    while ($r = mysqli_fetch_array($q)) { $linhas[intval($r["id"])] = $r; }
    $cab = "<tr><th></th>";
    for ($c = 1; $c <= 3; $c++) { $cab .= "<th>".t($controlrow["class".$c."name"])."</th>"; }
    $tabela = "<table class=\"lista\">$cab</tr>";
    foreach ($campos as $campo) {
        $tabela .= "<tr><td>".$rotulos[$campo]."</td>";
        for ($c = 1; $c <= 3; $c++) { $v = isset($linhas[$c][$campo]) ? intval($linhas[$c][$campo]) : 0; $tabela .= "<td><input type=\"number\" name=\"c{$c}_$campo\" value=\"$v\" min=\"-100\" max=\"300\" style=\"width:70px\" /></td>"; }
        $tabela .= "</tr>";
    }
    foreach (array("descricao"=>t("Descrição (pt)"), "descricao_en"=>t("Descrição (en)")) as $campo => $rot) {
        $tabela .= "<tr><td>$rot</td>";
        for ($c = 1; $c <= 3; $c++) { $tabela .= "<td><textarea name=\"c{$c}_$campo\" rows=\"5\" cols=\"28\">".(isset($linhas[$c][$campo]) ? $linhas[$c][$campo] : "")."</textarea></td>"; }
        $tabela .= "</tr>";
    }
    $tabela .= "</table>";
    $nivel = intval(isset($controlrow["classenivel"]) ? $controlrow["classenivel"] : 5);
    $page = <<<END
<h1>{$t("Especializações")}</h1>$mensagem
<p>{$t("Os nomes das especializações ficam em Configurações gerais. Todo ninja novo começa sem especialização e escolhe uma, de forma definitiva, em qualquer vila a partir do nível abaixo (especializacao.php). Os valores são porcentagens (crítico e chance em pontos de %, duração em turnos); negativos são fraquezas. As linhas \"Senjutsu\" valem enquanto o modo sábio está ativo (quem ainda não tem especialização recebe +20% de Ataque e Defesa). Use tests/classes_sim.py para simular o equilíbrio antes de mudar.")}</p>
<form action="admin.php?do=classes" method="post">$csrf
{$t("Nível para escolher a especialização:")} <input type="number" name="classenivel" value="$nivel" min="1" max="100" style="width:60px" /><br /><br />
$tabela<br />
<input type="submit" name="submit" value="{$t("Salvar")}" />
</form>
END;
    admindisplay($page, t("Especializações"));

}

function treinosadmin() { // Treinos longos do Painel de Treinos (tabela treinos; funcoestreinos.php e docs/TREINOS.md).

    include_once('funcoestreinos.php');
    $t = "t";
    $csrf = csrf_campo();
    $mensagem = "";
    $numeros = array("vila_id"=>array(t("Vila (id)"), 1, 65535), "nivel_min"=>array(t("Level mínimo"), 1, 1000), "sessoes"=>array(t("Sessões"), 1, 100),
        "intervalo"=>array(t("Intervalo (minutos)"), 0, 10080), "bonus_sessao"=>array(t("Bônus por sessão"), 0, 1000), "bonus_final"=>array(t("Bônus final / limite"), 0, 1000),
        "bonus_extra"=>array(t("Bônus extra (Taijutsu)"), 0, 1000), "jutsu_id"=>array(t("Jutsu ensinado (id)"), 0, 65535), "ativo"=>array(t("Ativo (1/0)"), 0, 1));
    $graduacoes = array(""=>t("Nenhuma"), "Genin"=>t("Genin"), "Chuunin"=>t("Chuunin"), "Jounin"=>t("Jounin"));
    $lista = array();
    $q = doquery("SELECT * FROM {{table}} ORDER BY id", "treinos");
    while ($r = mysqli_fetch_array($q)) { $lista[intval($r["id"])] = $r; }
    if (isset($_POST["submit"])) {
        $erros = ""; $sql = array();
        foreach ($lista as $id => $r) {
            $sets = array();
            foreach ($numeros as $campo => $info) { $sets[] = "`$campo`='".intval(admnumero("t{$id}_$campo", t($r["nome"]).": ".$info[0], $erros, $info[1], $info[2]))."'"; }
            $g = pegartexto($_POST, "t{$id}_graduacao_min");
            if (!isset($graduacoes[$g])) { $g = ""; }
            $sets[] = "graduacao_min='".addslashes($g)."'";
            $sets[] = "mestre='".admtexto("t{$id}_mestre", t("Mestre"), $erros, true, 40)."'";
            $sql[$id] = implode(",", $sets);
        }
        if ($erros == "") {
            foreach ($sql as $id => $sets) { doquery("UPDATE {{table}} SET $sets WHERE id='".intval($id)."' LIMIT 1", "treinos"); }
            $mensagem = admmsg(t("Treinos salvos. Quem já começou um treino continua com o número de sessões gravado no início."));
            $lista = array(); $q = doquery("SELECT * FROM {{table}} ORDER BY id", "treinos");
            while ($r = mysqli_fetch_array($q)) { $lista[intval($r["id"])] = $r; }
        } else { $mensagem = admmsg($erros, "erro"); }
    }
    $tabela = "<table class=\"lista\"><tr><th></th>";
    foreach ($lista as $r) { $tabela .= "<th>".t($r["nome"])."</th>"; }
    $tabela .= "</tr><tr><td>".t("Mestre")."</td>";
    foreach ($lista as $id => $r) { $tabela .= "<td><input type=\"text\" name=\"t{$id}_mestre\" value=\"".htmlspecialchars($r["mestre"], ENT_QUOTES)."\" size=\"14\" maxlength=\"40\" /></td>"; }
    $tabela .= "</tr><tr><td>".t("Graduação mínima")."</td>";
    foreach ($lista as $id => $r) { $tabela .= "<td><select name=\"t{$id}_graduacao_min\">".admopcoes($graduacoes, $r["graduacao_min"])."</select></td>"; }
    $tabela .= "</tr>";
    foreach ($numeros as $campo => $info) {
        $tabela .= "<tr><td>".$info[0]."</td>";
        foreach ($lista as $id => $r) { $tabela .= "<td><input type=\"number\" name=\"t{$id}_$campo\" value=\"".intval($r[$campo])."\" min=\"".$info[1]."\" max=\"".$info[2]."\" style=\"width:70px\" /></td>"; }
        $tabela .= "</tr>";
    }
    $tabela .= "<tr><td>".t("Recompensa")."</td>";
    foreach ($lista as $r) { $tabela .= "<td class=\"small\">".treinodescricao($r)."</td>"; }
    $tabela .= "</tr></table>";
    $page = <<<END
<h1>{$t("Treinos longos")}</h1>$mensagem
<p>{$t("Treinos do Painel de Treinos ensinados por mestres nas vilas (treinoslongos.php). O sentido dos bônus muda com o treino: Domínio de Elemento (EP por sessão, até o limite), Controle de Chakra (Chakra máximo por sessão; % de economia de Chakra ao concluir, máx. 50), Corpo de Ferro (HP por sessão; defesa ao concluir, mais o extra para Taijutsu), Caminhada Ninja (TP por sessão; TP por descanso ao concluir), Meditação Natural (NP por sessão; segundos a mais por NP do Senjutsu ao concluir) e Treino de Jutsu Raro (jutsu ensinado ao concluir). Bônus já dados aos jogadores não mudam.")}</p>
<form action="admin.php?do=treinos" method="post">$csrf
$tabela<br />
<input type="submit" name="submit" value="{$t("Salvar")}" />
</form>
END;
    admindisplay($page, t("Treinos longos"));

}

?>
