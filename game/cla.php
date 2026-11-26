<?php // cla.php :: Clãs: criar, convites, membros e cargos, banco compartilhado (itens e Ryou) e missão semanal. Regras: funcoescla.php.
//
// Todas as ações são POST com CSRF (do=...). Gravações em transação: linha do clã travada primeiro (FOR UPDATE), depois
// o jogador; assim dois cliques ao mesmo tempo ficam em fila e o segundo vê o banco já alterado (nada se duplica).
// Cargos: lider (tudo), oficial (convida e retira Ryou), membro (deposita/retira itens e deposita Ryou).
include_once('lib.php');
$link = opendb();
include_once('cookies.php');
$userrow = checkcookies();
if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."), t("Erro"), false, false, false); die(); }
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
include_once('funcoescla.php');

$do = pegartexto($_GET, "do");
if ($do != "") {
	if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrf_confere()) { clavoltar("csrf"); }
	$acoes = array("criar","aceitar","recusar","convidar","sair","expulsar","cargo","transferir","dissolver","depryou","saqryou","depitem","saqitem");
	if (in_array($do, $acoes, true)) { $f = "cla_".$do; $f(); }
	clavoltar();
}
clapagina();


function clavoltar($msg = "") { // Volta para a página do clã (mensagem pelo código, nunca texto livre).
	doquery("ROLLBACK", "clas"); // inofensivo se não houver transação aberta.
	header("Location: cla.php".($msg != "" ? "?m=".rawurlencode($msg) : "")); die();
}

function claok($msg) { doquery("COMMIT", "clas"); header("Location: cla.php?m=".rawurlencode($msg)); die(); }

function clamensagens() {
	global $controlrow;
	return array(
		"csrf" => t("Sessão expirada. Tente de novo."),
		"nome" => t("O nome do clã precisa ter de 3 a 20 letras, números ou espaços."),
		"tag" => t("A tag precisa ter de 2 a 4 letras (A-Z)."),
		"repetido" => t("Já existe um clã com esse nome ou essa tag."),
		"ryou" => tf("Criar um clã custa %s Ryou (carregados).", number_format(intval($controlrow["cla_custo"]))),
		"jatem" => t("Você já está em um clã."),
		"criado" => t("Clã criado! Você é o líder."),
		"semcla" => t("Você não está em um clã."),
		"semconvite" => t("Convite não encontrado."),
		"cheio" => tf("O clã está cheio (máximo de %s membros).", intval($controlrow["cla_maxmembros"])),
		"entrou" => t("Você entrou no clã."),
		"recusado" => t("Convite recusado."),
		"permissao" => t("Seu cargo não permite essa ação."),
		"naoachou" => t("Jogador não encontrado."),
		"outrocla" => t("Esse jogador já está em um clã."),
		"convidado" => t("Convite enviado."),
		"lidersai" => t("O líder não pode sair: transfira a liderança antes (ou dissolva o clã se estiver sozinho)."),
		"saiu" => t("Você saiu do clã."),
		"naomembro" => t("Esse jogador não é membro do clã."),
		"expulso" => t("Membro expulso."),
		"cargook" => t("Cargo alterado."),
		"transferido" => t("Liderança transferida."),
		"naosozinho" => t("Só dá para dissolver o clã quando o líder está sozinho nele."),
		"bancocheio" => t("Esvazie o banco do clã (itens e Ryou) antes de dissolver."),
		"dissolvido" => t("Clã dissolvido."),
		"valor" => t("Valor inválido."),
		"semryou" => t("Você não tem esse Ryou."),
		"clasemryou" => t("O clã não tem esse Ryou."),
		"limite" => t("Você não pode carregar tanto Ryou."),
		"depositado" => t("Depósito feito."),
		"retirado" => t("Retirada feita."),
		"item" => t("Esse item não está mais lá (a lista mudou). Tente de novo."),
		"clacheio" => tf("O banco do clã guarda no máximo %s itens.", CLA_BANCOLIMITE),
		"meucheio" => t("Seu banco está cheio."),
	);
}

function clatravar() { // Clã do jogador travado (FOR UPDATE) e o jogador travado em seguida. Volta se não tiver clã.
	global $userrow;
	doquery("START TRANSACTION", "clas");
	$m = mysqli_fetch_array(doquery("SELECT cla FROM {{table}}_membros WHERE usuario='".intval($userrow["id"])."' LIMIT 1", "clas"));
	if (!$m) { clavoltar("semcla"); }
	$c = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($m["cla"])."' LIMIT 1 FOR UPDATE", "clas"));
	// Relê a filiação depois da trava: pode ter sido expulso enquanto esperava.
	$m = mysqli_fetch_array(doquery("SELECT cargo FROM {{table}}_membros WHERE usuario='".intval($userrow["id"])."' AND cla='".intval($c ? $c["id"] : 0)."' LIMIT 1 FOR UPDATE", "clas"));
	if (!$c || !$m) { clavoltar("semcla"); }
	$c["cargo"] = $m["cargo"];
	$userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
	return $c;
}

function clalog($cla, $acao, $valor, $item = "") {
	global $userrow;
	doquery("INSERT INTO {{table}}_log (cla, usuario, charname, acao, valor, item, quando) VALUES ('".intval($cla)."', '".intval($userrow["id"])."', '".addslashes($userrow["charname"])."', '".addslashes($acao)."', '".intval($valor)."', '".addslashes($item)."', NOW())", "clas");
}

function claalvo($cla) { // Membro escolhido no formulário (usuario=id), travado; volta se não for do clã.
	$id = pegarint($_POST, "usuario");
	$m = mysqli_fetch_array(doquery("SELECT m.usuario, m.cargo, u.charname FROM {{table}}_membros m JOIN naruto_users u ON u.id=m.usuario WHERE m.usuario='".intval($id)."' AND m.cla='".intval($cla)."' LIMIT 1 FOR UPDATE", "clas"));
	if (!$m) { clavoltar("naomembro"); }
	return $m;
}

function cla_criar() {
	global $userrow, $controlrow;
	$nome = trim(preg_replace("/\s+/u", " ", html_entity_decode(stripslashes(pegartexto($_POST, "nome")), ENT_QUOTES, "UTF-8")));
	$tag = strtoupper(trim(stripslashes(pegartexto($_POST, "tag"))));
	$tam = mb_strlen($nome, "UTF-8");
	if ($tam < 3 || $tam > 20 || !preg_match("/^[\p{L}0-9 ]+$/u", $nome)) { clavoltar("nome"); }
	if (!preg_match("/^[A-Z]{2,4}$/", $tag)) { clavoltar("tag"); }
	$custo = intval($controlrow["cla_custo"]);
	doquery("START TRANSACTION", "users");
	$u = mysqli_fetch_array(doquery("SELECT id, gold FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
	if (mysqli_num_rows(doquery("SELECT usuario FROM {{table}}_membros WHERE usuario='".intval($u["id"])."' LIMIT 1 FOR UPDATE", "clas")) > 0) { clavoltar("jatem"); }
	if (intval($u["gold"]) < $custo) { clavoltar("ryou"); }
	doquery("INSERT IGNORE INTO {{table}} (nome, tag, lider, ryou, banco, criado) VALUES ('".addslashes($nome)."', '$tag', '".intval($u["id"])."', 0, 'None', NOW())", "clas");
	if (mysqli_affected_rows($GLOBALS["link"]) != 1) { clavoltar("repetido"); }
	$id = mysqli_insert_id($GLOBALS["link"]);
	doquery("INSERT IGNORE INTO {{table}}_membros (usuario, cla, cargo, entrou) VALUES ('".intval($u["id"])."', '$id', 'lider', NOW())", "clas");
	if (mysqli_affected_rows($GLOBALS["link"]) != 1) { clavoltar("jatem"); }
	doquery("UPDATE {{table}} SET gold=gold-$custo WHERE id='".intval($u["id"])."' AND gold>='$custo' LIMIT 1", "users");
	if (mysqli_affected_rows($GLOBALS["link"]) != 1) { clavoltar("ryou"); }
	doquery("DELETE FROM {{table}}_convites WHERE usuario='".intval($u["id"])."'", "clas");
	claok("criado");
}

function cla_aceitar() {
	global $userrow, $controlrow;
	$cla = pegarint($_POST, "cla");
	doquery("START TRANSACTION", "clas");
	$c = mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE id='".intval($cla)."' LIMIT 1 FOR UPDATE", "clas"));
	if (!$c || mysqli_num_rows(doquery("SELECT cla FROM {{table}}_convites WHERE cla='".intval($cla)."' AND usuario='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "clas")) == 0) { clavoltar("semconvite"); }
	$n = mysqli_fetch_array(doquery("SELECT COUNT(*) AS n FROM {{table}}_membros WHERE cla='".intval($cla)."'", "clas"));
	if (intval($n["n"]) >= max(1, intval($controlrow["cla_maxmembros"]))) { clavoltar("cheio"); }
	doquery("INSERT IGNORE INTO {{table}}_membros (usuario, cla, cargo, entrou) VALUES ('".intval($userrow["id"])."', '".intval($cla)."', 'membro', NOW())", "clas");
	if (mysqli_affected_rows($GLOBALS["link"]) != 1) { clavoltar("jatem"); }
	doquery("DELETE FROM {{table}}_convites WHERE usuario='".intval($userrow["id"])."'", "clas");
	claok("entrou");
}

function cla_recusar() {
	global $userrow;
	doquery("DELETE FROM {{table}}_convites WHERE cla='".pegarint($_POST, "cla")."' AND usuario='".intval($userrow["id"])."'", "clas");
	clavoltar("recusado");
}

function cla_convidar() {
	$c = clatravar();
	if ($c["cargo"] != "lider" && $c["cargo"] != "oficial") { clavoltar("permissao"); }
	$nome = stripslashes(pegartexto($_POST, "nome"));
	$a = mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE charname='".addslashes($nome)."' LIMIT 1", "users"));
	if (!$a) { clavoltar("naoachou"); }
	if (mysqli_num_rows(doquery("SELECT usuario FROM {{table}}_membros WHERE usuario='".intval($a["id"])."' LIMIT 1", "clas")) > 0) { clavoltar("outrocla"); }
	doquery("INSERT IGNORE INTO {{table}}_convites (cla, usuario, criado) VALUES ('".intval($c["id"])."', '".intval($a["id"])."', NOW())", "clas");
	avisarcla($a["id"], i18n("Você foi convidado para o clã %s. Veja na página do Clã.", $c["nome"]));
	claok("convidado");
}

function cla_sair() {
	global $userrow;
	$c = clatravar();
	if ($c["cargo"] == "lider") { clavoltar("lidersai"); }
	doquery("DELETE FROM {{table}}_membros WHERE usuario='".intval($userrow["id"])."' AND cla='".intval($c["id"])."' LIMIT 1", "clas");
	claok("saiu");
}

function cla_expulsar() {
	$c = clatravar();
	if ($c["cargo"] != "lider") { clavoltar("permissao"); }
	$a = claalvo($c["id"]);
	if ($a["cargo"] == "lider") { clavoltar("permissao"); }
	doquery("DELETE FROM {{table}}_membros WHERE usuario='".intval($a["usuario"])."' AND cla='".intval($c["id"])."' LIMIT 1", "clas");
	avisarcla($a["usuario"], i18n("Você foi expulso do clã %s.", $c["nome"]));
	claok("expulso");
}

function cla_cargo() {
	$c = clatravar();
	if ($c["cargo"] != "lider") { clavoltar("permissao"); }
	$a = claalvo($c["id"]);
	$novo = (pegartexto($_POST, "cargo") == "oficial") ? "oficial" : "membro";
	if ($a["cargo"] == "lider") { clavoltar("permissao"); }
	doquery("UPDATE {{table}}_membros SET cargo='$novo' WHERE usuario='".intval($a["usuario"])."' AND cla='".intval($c["id"])."' LIMIT 1", "clas");
	claok("cargook");
}

function cla_transferir() {
	global $userrow;
	$c = clatravar();
	if ($c["cargo"] != "lider") { clavoltar("permissao"); }
	$a = claalvo($c["id"]);
	if (intval($a["usuario"]) == intval($userrow["id"])) { clavoltar("naomembro"); }
	doquery("UPDATE {{table}}_membros SET cargo='oficial' WHERE usuario='".intval($userrow["id"])."' AND cla='".intval($c["id"])."' LIMIT 1", "clas");
	doquery("UPDATE {{table}}_membros SET cargo='lider' WHERE usuario='".intval($a["usuario"])."' AND cla='".intval($c["id"])."' LIMIT 1", "clas");
	doquery("UPDATE {{table}} SET lider='".intval($a["usuario"])."' WHERE id='".intval($c["id"])."' LIMIT 1", "clas");
	avisarcla($a["usuario"], i18n("Você agora é o líder do clã %s.", $c["nome"]));
	claok("transferido");
}

function cla_dissolver() {
	$c = clatravar();
	if ($c["cargo"] != "lider") { clavoltar("permissao"); }
	$n = mysqli_fetch_array(doquery("SELECT COUNT(*) AS n FROM {{table}}_membros WHERE cla='".intval($c["id"])."'", "clas"));
	if (intval($n["n"]) > 1) { clavoltar("naosozinho"); }
	if (intval($c["ryou"]) > 0 || count(clabancoitens($c["banco"])) > 0) { clavoltar("bancocheio"); }
	foreach (array("_membros", "_convites", "_semana", "_contrib", "_log", "") as $tab) { doquery("DELETE FROM {{table}}$tab WHERE ".($tab == "" ? "id" : "cla")."='".intval($c["id"])."'", "clas"); }
	claok("dissolvido");
}

function cla_depryou() {
	global $userrow;
	$v = pegarint($_POST, "valor");
	$c = clatravar();
	if ($v < 1 || $v > 16777215) { clavoltar("valor"); }
	if (intval($userrow["gold"]) < $v) { clavoltar("semryou"); }
	doquery("UPDATE {{table}} SET gold=gold-$v WHERE id='".intval($userrow["id"])."' AND gold>='$v' LIMIT 1", "users");
	if (mysqli_affected_rows($GLOBALS["link"]) != 1) { clavoltar("semryou"); }
	doquery("UPDATE {{table}} SET ryou=ryou+$v WHERE id='".intval($c["id"])."' LIMIT 1", "clas");
	clalog($c["id"], "depryou", $v);
	claok("depositado");
}

function cla_saqryou() {
	global $userrow;
	$v = pegarint($_POST, "valor");
	$c = clatravar();
	if ($c["cargo"] != "lider" && $c["cargo"] != "oficial") { clavoltar("permissao"); }
	if ($v < 1) { clavoltar("valor"); }
	if (intval($c["ryou"]) < $v) { clavoltar("clasemryou"); }
	if (intval($userrow["gold"]) + $v > 16777215) { clavoltar("limite"); }
	doquery("UPDATE {{table}} SET ryou=ryou-$v WHERE id='".intval($c["id"])."' AND ryou>='$v' LIMIT 1", "clas");
	if (mysqli_affected_rows($GLOBALS["link"]) != 1) { clavoltar("clasemryou"); }
	doquery("UPDATE {{table}} SET gold=gold+$v WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
	clalog($c["id"], "saqryou", $v);
	claok("retirado");
}

function cla_depitem() { // Do banco do jogador para o banco do clã (a entrada inteira, com a quantidade).
	global $userrow;
	$c = clatravar();
	$meus = clabancoitens($userrow["bancogeral"]);
	$qual = pegarint($_POST, "qual", -1);
	if ($qual < 0 || $qual >= count($meus) || pegartexto($_POST, "h") !== clabancocodigo($meus[$qual])) { clavoltar("item"); }
	$item = $meus[$qual];
	$dele = clabancoitens($c["banco"]);
	if (!clabancoguardar($dele, $item, CLA_BANCOLIMITE)) { clavoltar("clacheio"); }
	unset($meus[$qual]);
	doquery("UPDATE {{table}} SET bancogeral='".addslashes(clabancotexto(array_values($meus)))."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
	doquery("UPDATE {{table}} SET banco='".addslashes(clabancotexto($dele))."' WHERE id='".intval($c["id"])."' LIMIT 1", "clas");
	clalog($c["id"], "depitem", qtditem($item), $item);
	claok("depositado");
}

function cla_saqitem() { // Do banco do clã para o banco do jogador.
	global $userrow;
	$c = clatravar();
	$dele = clabancoitens($c["banco"]);
	$qual = pegarint($_POST, "qual", -1);
	if ($qual < 0 || $qual >= count($dele) || pegartexto($_POST, "h") !== clabancocodigo($dele[$qual])) { clavoltar("item"); }
	$item = $dele[$qual];
	$meus = clabancoitens($userrow["bancogeral"]);
	if (!clabancoguardar($meus, $item, max(30, min(50, isset($userrow["banco_limite"]) ? intval($userrow["banco_limite"]) : 30)))) { clavoltar("meucheio"); } // = bancolimite() do funcaoitens.php.
	unset($dele[$qual]);
	doquery("UPDATE {{table}} SET bancogeral='".addslashes(clabancotexto($meus))."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
	doquery("UPDATE {{table}} SET banco='".addslashes(clabancotexto(array_values($dele)))."' WHERE id='".intval($c["id"])."' LIMIT 1", "clas");
	clalog($c["id"], "saqitem", qtditem($item), $item);
	claok("retirado");
}

function claitemnome($item) { // "Kunai+2 x3" no idioma do jogador.
	$p = explode(",", (string)$item);
	$partes = explode("+", $p[0], 2);
	$q = qtditem($item);
	return htmlspecialchars(t($partes[0]).(isset($partes[1]) ? "+".$partes[1] : "")).($q > 1 ? " x".$q : "");
}

function clabotao($do, $rotulo, $campos = array(), $confirma = "") {
	$h = "<form method=\"post\" action=\"cla.php?do=$do\" style=\"display:inline\">".csrf_campo();
	foreach ($campos as $k => $v) { $h .= "<input type=\"hidden\" name=\"$k\" value=\"".htmlspecialchars($v)."\" />"; }
	// Botões .btnjogo: ações que tiram alguém/desfazem em vermelho, cargos em neutro, o resto em verde.
	$cor = in_array($do, array("recusar", "expulsar", "sair", "dissolver")) ? "bj-cancelar" : (in_array($do, array("cargo", "transferir")) ? "bj-neutro" : "bj-confirmar");
	return $h."<input type=\"submit\" class=\"btnjogo $cor\" value=\"".htmlspecialchars($rotulo)."\"".($confirma != "" ? " onclick=\"return confirm('".htmlspecialchars(addslashes($confirma))."')\"" : "")." /></form>";
}

function clacaixa($titulo, $corpo, $extra = "") {
	return "<div class=\"cxb\"><div class=\"cxb-cab\"><b>$titulo</b><span>$extra</span></div><div class=\"cxb-corpo\">$corpo</div></div>";
}

function clapagina() {
	global $userrow, $controlrow;
	$msgs = clamensagens();
	$m = pegartexto($_GET, "m");
	$page = "<center><h3>".t("Clã")."</h3></center>";
	if ($m != "" && isset($msgs[$m])) { $page .= "<div class=\"cxb\"><div class=\"cxb-corpo\"><div class=\"cxb-linha\" id=\"clamsg\"><b>".$msgs[$m]."</b></div></div></div>"; }
	$c = cladojogador($userrow["id"]);
	if (!$c) {
		// Convites recebidos e formulário de criação.
		$q = doquery("SELECT c.id, c.nome, c.tag FROM {{table}}_convites v JOIN {{table}} c ON c.id=v.cla WHERE v.usuario='".intval($userrow["id"])."' ORDER BY v.criado DESC", "clas");
		$linhas = "";
		while ($r = mysqli_fetch_array($q)) { $linhas .= "<div class=\"cxb-linha\">[".htmlspecialchars($r["tag"])."] ".htmlspecialchars($r["nome"])." ".clabotao("aceitar", t("Aceitar"), array("cla"=>$r["id"]))." ".clabotao("recusar", t("Recusar"), array("cla"=>$r["id"]))."</div>"; }
		$page .= clacaixa(t("Convites"), $linhas != "" ? $linhas : "<div class=\"cxb-vazio\">".t("Nenhum convite.")."</div>");
		$form = "<div class=\"cxb-linha\"><form method=\"post\" action=\"cla.php?do=criar\">".csrf_campo().t("Nome:")." <input type=\"text\" name=\"nome\" maxlength=\"20\" size=\"18\" /> ".t("Tag:")." <input type=\"text\" name=\"tag\" maxlength=\"4\" size=\"4\" /> <button type=\"submit\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_criar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Criar clã")."</button></form></div>";
		$form .= "<div class=\"cxb-linha\">".tf("Custo: %s Ryou. Nome com 3 a 20 caracteres; tag com 2 a 4 letras.", number_format(intval($controlrow["cla_custo"])))."</div>";
		$page .= clacaixa(t("Criar um clã"), $form);
		display($page, t("Clã")); die();
	}
	clamissaopagar($c["id"]); // missão semanal batida e ainda não paga: paga agora (preguiçoso).
	$c = cladojogador($userrow["id"]);
	$cargo = $c["cargo"]; $lider = ($cargo == "lider"); $oficial = ($cargo == "oficial");
	$cargos = array("lider" => t("Líder"), "oficial" => t("Oficial"), "membro" => t("Membro"));

	// Membros.
	$q = doquery("SELECT m.usuario, m.cargo, u.charname, u.level FROM {{table}}_membros m JOIN naruto_users u ON u.id=m.usuario WHERE m.cla='".intval($c["id"])."' ORDER BY FIELD(m.cargo,'lider','oficial','membro'), u.charname", "clas");
	$linhas = ""; $n = 0;
	while ($r = mysqli_fetch_array($q)) {
		$n++;
		$acoes = "";
		if ($lider && $r["cargo"] != "lider") {
			$acoes .= ($r["cargo"] == "oficial") ? clabotao("cargo", t("Rebaixar"), array("usuario"=>$r["usuario"], "cargo"=>"membro")) : clabotao("cargo", t("Promover a oficial"), array("usuario"=>$r["usuario"], "cargo"=>"oficial"));
			$acoes .= " ".clabotao("expulsar", t("Expulsar"), array("usuario"=>$r["usuario"]), t("Expulsar este membro?"));
			$acoes .= " ".clabotao("transferir", t("Passar liderança"), array("usuario"=>$r["usuario"]), t("Passar a liderança do clã?"));
		}
		$linhas .= "<div class=\"cxb-linha\"><a href=\"javascript:mostrarchar('".htmlspecialchars($r["charname"])."')\">".htmlspecialchars($r["charname"])."</a> Lv ".intval($r["level"])." - <i>".$cargos[$r["cargo"]]."</i> $acoes</div>";
	}
	$max = intval($controlrow["cla_maxmembros"]);
	$extra = $lider && $n == 1 ? clabotao("dissolver", t("Dissolver"), array(), t("Dissolver o clã?")) : (!$lider ? clabotao("sair", t("Sair do clã"), array(), t("Sair do clã?")) : "");
	$page .= clacaixa("[".htmlspecialchars($c["tag"])."] ".htmlspecialchars($c["nome"])." - ".$cargos[$cargo], $linhas, tf("%s/%s membros", $n, $max)." ".$extra);
	if ($lider || $oficial) {
		$page .= clacaixa(t("Convidar"), "<div class=\"cxb-linha\"><form method=\"post\" action=\"cla.php?do=convidar\">".csrf_campo().t("Nome do personagem:")." <input type=\"text\" name=\"nome\" size=\"16\" /> <button type=\"submit\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_enviar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Convidar")."</button></form></div>");
	}

	// Missão semanal.
	$sem = clasemana();
	$s = mysqli_fetch_array(doquery("SELECT total, pago FROM {{table}}_semana WHERE cla='".intval($c["id"])."' AND semana='$sem' LIMIT 1", "clas"));
	$total = $s ? intval($s["total"]) : 0; $meta = max(1, intval($controlrow["cla_meta"]));
	$linhas = "<div class=\"cxb-linha\" id=\"clamissao\">".tf("Derrotem %s inimigos nesta semana: %s/%s.", $meta, min($total, $meta), $meta)." ".($s && intval($s["pago"]) ? "<b>".t("Cumprida e paga!")."</b>" : "")."</div>";
	$linhas .= "<div class=\"cxb-linha\">".tf("Recompensa: %s Ryou divididos entre quem contribuiu (proporcional aos inimigos derrotados).", number_format(intval($controlrow["cla_premio"])))."</div>";
	$qk = doquery("SELECT k.abates, u.charname FROM {{table}}_contrib k JOIN naruto_users u ON u.id=k.usuario WHERE k.cla='".intval($c["id"])."' AND k.semana='$sem' ORDER BY k.abates DESC LIMIT 15", "clas");
	while ($k = mysqli_fetch_array($qk)) { $linhas .= "<div class=\"cxb-linha\">".htmlspecialchars($k["charname"]).": ".intval($k["abates"])."</div>"; }
	$page .= clacaixa(t("Missão semanal do clã"), $linhas);

	// Banco: Ryou.
	$linhas = "<div class=\"cxb-linha\">".tf("Ryou do clã: <b>%s</b> (você carrega %s).", iconeryou().number_format(intval($c["ryou"])), iconeryou().number_format(intval($userrow["gold"])))."</div>";
	$linhas .= "<div class=\"cxb-linha\"><form method=\"post\" action=\"cla.php?do=depryou\" style=\"display:inline\">".csrf_campo()."<input type=\"number\" name=\"valor\" min=\"1\" style=\"width:90px\" /> <button type=\"submit\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_depositar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Depositar Ryou")."</button></form>";
	if ($lider || $oficial) { $linhas .= " <form method=\"post\" action=\"cla.php?do=saqryou\" style=\"display:inline\">".csrf_campo()."<input type=\"number\" name=\"valor\" min=\"1\" style=\"width:90px\" /> <button type=\"submit\" class=\"btnjogo bj-neutro\"><img src=\"images/botao_retirar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Retirar Ryou")."</button></form>"; }
	else { $linhas .= " <span class=\"light\">".t("(só o líder e os oficiais retiram Ryou)")."</span>"; }
	$linhas .= "</div>";
	$page .= clacaixa(t("Banco do clã: Ryou"), $linhas);

	// Banco: itens (do clã e do banco pessoal).
	$dele = clabancoitens($c["banco"]); $linhas = "";
	foreach ($dele as $i => $it) { $linhas .= "<div class=\"cxb-linha\">".claitemnome($it)." ".clabotao("saqitem", t("Retirar para o meu banco"), array("qual"=>$i, "h"=>clabancocodigo($it)))."</div>"; }
	$page .= clacaixa(t("Banco do clã: itens"), $linhas != "" ? $linhas : "<div class=\"cxb-vazio\">".t("O banco do clã está vazio.")."</div>", count($dele)."/".CLA_BANCOLIMITE);
	$meus = clabancoitens($userrow["bancogeral"]); $linhas = "";
	foreach ($meus as $i => $it) { $linhas .= "<div class=\"cxb-linha\">".claitemnome($it)." ".clabotao("depitem", t("Depositar no clã"), array("qual"=>$i, "h"=>clabancocodigo($it)))."</div>"; }
	$page .= clacaixa(t("Meu banco"), $linhas != "" ? $linhas : "<div class=\"cxb-vazio\">".t("Seu banco está vazio.")."</div>", count($meus)."/30");

	// Registro do banco (quem pôs/tirou).
	$acoesl = array("depryou" => t("depositou %s Ryou"), "saqryou" => t("retirou %s Ryou"), "depitem" => t("depositou %s"), "saqitem" => t("retirou %s"));
	$q = doquery("SELECT * FROM {{table}}_log WHERE cla='".intval($c["id"])."' ORDER BY id DESC LIMIT 30", "clas");
	$linhas = "";
	while ($r = mysqli_fetch_array($q)) {
		if (!isset($acoesl[$r["acao"]])) { continue; }
		$o = (substr($r["acao"], 3) == "ryou") ? number_format(intval($r["valor"])) : claitemnome($r["item"]);
		$linhas .= "<div class=\"cxb-linha\"><span class=\"light\">".htmlspecialchars($r["quando"])."</span> ".htmlspecialchars($r["charname"])." ".sprintf($acoesl[$r["acao"]], $o)."</div>";
	}
	$page .= clacaixa(t("Registro do banco"), $linhas != "" ? $linhas : "<div class=\"cxb-vazio\">".t("Nada registrado ainda.")."</div>");
	display($page, t("Clã"));
}
