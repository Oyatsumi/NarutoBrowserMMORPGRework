<?php // especializacao.php :: Escolha da especialização (Ninjutsu, Taijutsu ou Genjutsu) dentro do jogo, numa vila.
//
// Todo ninja novo começa sem especialização (users.charclass = 0, "Estudante"): sobe de nível com as colunas 1_* da
// tabela levels e só aprende os jutsus sem classe. A partir do nível control.classenivel, em qualquer vila, ele abre
// esta página (ícone "Escolher Especialização" nos serviços da vila) e escolhe UMA vez. A escolha é DEFINITIVA:
// não há troca pelo jogador (só o administrador pode corrigir em casos excepcionais, em admin.php edituser, com registro).
//
// Ao escolher (escolherclasse()):
//   - HP/CH/TP máximos, Força e Destreza (e Poder de Ataque/Defesa) recebem a diferença entre o que a classe nova
//     teria ganhado do nível 2 ao nível atual e o que foi ganho com as colunas 1_* (itens e atributos ficam intactos);
//   - o jogador aprende os jutsus que a tabela levels ensina para a classe até o nível atual (menos os de missão);
//   - tudo numa transação com o jogador travado e "AND charclass='0'" no UPDATE (dois cliques não escolhem duas vezes).
// Rotas: (nenhuma) = página com as três classes; POST escolher = confirmação; POST confirmar = grava.
// Os modificadores de cada classe vêm da tabela classes (funcoesjutsus.php :: classemods(); docs/ESPECIALIZACOES.md).

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies();
include_once('funcoesinclusas.php');
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control")); // nomes das classes e nível para escolher.

if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."), t("Erro"), false, false, false); die(); }
if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }
if ($userrow["batalha_timer2"] == 5) { display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "), t("Erro"), false, false, false); die(); }

especializacao();

function nivelparaescolher() { // Nível mínimo para escolher a especialização (control.classenivel, padrão 5).
	global $controlrow;
	return max(1, intval(isset($controlrow["classenivel"]) ? $controlrow["classenivel"] : 5));
}

function classetextomods($classe) { // Pontos fortes e fracos da classe: array de array(texto traduzido, true = vantagem / false = fraqueza).
	$m = classemods($classe);
	$r = array();
	$t = array(
		"dano_fisico" => "%s%% de dano nos ataques físicos", "critico" => "%s%% de Chance de Crítico nos ataques físicos",
		"dano_jutsu" => "%s%% de dano em todos os jutsus de dano", "dano_elemental" => "%s%% de dano nos jutsus com elemento",
		"vantagem_elemento" => "%s%% de dano a mais quando o elemento é super efetivo", "defesa_fisica" => "%s%% de defesa contra ataques físicos",
		"chance_efeito" => "%s%% de chance de atordoar, envenenar, queimar ou enfraquecer", "duracao_efeito" => "%s turno(s) de duração em venenos, queimaduras e enfraquecimentos",
		"valor_efeito" => "%s%% de força em venenos, queimaduras e enfraquecimentos", "dreno" => "%s%% no Dreno de Chakra",
		"resist_debuff" => "%s%% de resistência aos efeitos de outros ninjas", "economia_tecnica" => "%s%% de Chakra a menos nas técnicas sem elemento", "buff_proprio" => "%s%% de força nos jutsus que reforçam você (ataque, defesa, escudo, regeneração)");
	foreach ($t as $campo => $texto) {
		$v = intval($m[$campo]);
		if ($v == 0) { continue; }
		$sinal = ($campo == "economia_tecnica") ? ($v > 0 ? "-" : "+") : ($v > 0 ? "+" : "");
		$r[] = array(tf($texto, $sinal.(($campo == "economia_tecnica") ? abs($v) : $v)), $v > 0);
	}
	return $r;
}

function classecrescimento($classe, $nivel) { // Soma de HP/CH/Força/Destreza que a tabela levels dá à classe do nível 1 até $nivel.
	$c = max(1, min(3, intval($classe)));
	$r = mysqli_fetch_array(doquery("SELECT SUM(".$c."_hp) AS hp, SUM(".$c."_mp) AS mp, SUM(".$c."_strength) AS forca, SUM(".$c."_dexterity) AS destreza FROM {{table}} WHERE id<='".intval($nivel)."'", "levels"));
	return $r ? $r : array("hp"=>0, "mp"=>0, "forca"=>0, "destreza"=>0);
}

function classejutsus($classe) { // Jutsus exclusivos da classe (spells.classe) com o nível em que são aprendidos (tabela levels) ou "pergaminho".
	$c = intval($classe);
	$lista = array();
	$niveis = array();
	$q = doquery("SELECT id, ".$c."_spells AS s FROM {{table}} WHERE ".$c."_spells<>'0'", "levels");
	while ($r = mysqli_fetch_array($q)) { $niveis[intval($r["s"])] = intval($r["id"]); }
	$q = doquery("SELECT * FROM {{table}} WHERE classe='$c' ORDER BY nivelminimo, id", "spells");
	while ($s = mysqli_fetch_array($q)) {
		$s["nivelaprende"] = isset($niveis[intval($s["id"])]) ? $niveis[intval($s["id"])] : 0;
		$lista[] = $s;
	}
	usort($lista, function ($a, $b) { $na = $a["nivelaprende"] ?: 999; $nb = $b["nivelaprende"] ?: 999; return $na <=> $nb; });
	return $lista;
}

function cartaoclasse($classe, $selecionavel) { // Quadro de uma especialização (no estilo das tabelas da vila).
	global $controlrow, $userrow;
	$c = intval($classe);
	$desc = mysqli_fetch_array(doquery("SELECT descricao, descricao_en FROM {{table}} WHERE id='$c' LIMIT 1", "classes"));
	$texto = $desc ? ((idioma() == "en" && trim($desc["descricao_en"]) != "") ? $desc["descricao_en"] : $desc["descricao"]) : "";
	$mods = "";
	foreach (classetextomods($c) as $l) { $mods .= "<li style=\"color:".($l[1] ? "#1d5c12" : "#9b1b10")."\">".($l[1] ? "&#9650; " : "&#9660; ").$l[0]."</li>"; }
	$cr = classecrescimento($c, 80);
	$jutsus = "";
	foreach (classejutsus($c) as $s) {
		$quando = ($s["nivelaprende"] > 0) ? tf("nível %s", $s["nivelaprende"]) : t("pergaminho");
		$explic = htmlspecialchars(implode(" · ", jutsudescricaoefeitos($s)), ENT_QUOTES, 'UTF-8');
		$jutsus .= "<li title=\"$explic\"><b>".t($s["name"])."</b> <span class=\"small\">($quando, ".tf("%s CH", intval($s["mp"])).")</span></li>";
	}
	$sj = implode(", ", senjutsubonustexto($c, intval($userrow["level"])));
	// Botão próprio em cada cartão (envia a classe direto para a confirmação); desabilitado fora da vila/sem nível.
	$nome = nomedaclasse($c);
	if ($selecionavel === "botao") { $escolha = "<div style=\"text-align:center;padding:8px 4px 4px\"><button type=\"submit\" name=\"classe\" value=\"$c\" id=\"escolher$c\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_sim.png\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".tf("Ser %s", $nome)."</button></div>"; }
	elseif ($selecionavel) { $escolha = "<div style=\"text-align:center;padding:8px 4px 4px\"><button type=\"button\" disabled=\"disabled\" class=\"btnjogo bj-neutro\" style=\"opacity:.6;cursor:not-allowed\">".tf("Ser %s", $nome)."</button></div>"; }
	else { $escolha = ""; }
	$ativa = (intval($userrow["charclass"]) == $c) ? " style=\"box-shadow:0 0 0 2px #c98b5a\"" : "";
	$selo = (intval($userrow["charclass"]) == $c) ? "<span style=\"color:#9be08a\">&#10004; ".t("Sua")."</span>" : "";
	$t = "t"; $tf = "tf";
	return <<<END
<div class="espcartao" style="flex:1 1 150px;min-width:0;max-width:100%">
<div class="cxb" id="classe$c"$ativa><div class="cxb-cab"><span style="font-size:14px;font-weight:bold">{$nome}</span>$selo</div><div class="cxb-corpo">
<div class="cxb-linha">$texto</div>
<div class="cxb-linha"><b>{$t("Pontos fortes e fracos")}</b><ul style="margin:3px 0 0 14px;padding:0">$mods</ul></div>
<div class="cxb-linha"><b>{$t("Crescimento até o nível 80")}</b><br>{$tf("HP %s · CH %s · Força %s · Destreza %s", $cr["hp"], $cr["mp"], $cr["forca"], $cr["destreza"])}</div>
<div class="cxb-linha"><b>{$t("Jutsus exclusivos")}</b><ul style="margin:3px 0 0 14px;padding:0">$jutsus</ul></div>
<div class="cxb-linha"><b>{$t("Senjutsu ativo")}</b><br>$sj</div>
$escolha</div></div></div>
END;
}

function especializacao() { // Página principal, confirmação e gravação da escolha.
	global $userrow, $controlrow;
	$t = "t"; $tf = "tf";
	$topo = "<table width=\"100%\"><tr><td align=\"center\"><h2 style=\"color:#452202;margin:4px\">".t("Especialização Ninja")."</h2></td></tr></table>";
	$classe = intval($userrow["charclass"]);
	$voltar = "<div style=\"text-align:center;margin-top:8px\"><a href=\"index.php\" id=\"botaovoltarvila\" class=\"btnjogo bj-neutro\"><img src=\"images/seta_oeste.png\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".t("Voltar para a vila")."</a></div>";
	$cartoes = function ($modo) { return "<div id=\"especializacoes\" style=\"display:flex;flex-wrap:wrap;gap:6px;align-items:flex-start;text-align:left\">".cartaoclasse(1, $modo).cartaoclasse(2, $modo).cartaoclasse(3, $modo)."</div>"; };

	if ($classe >= 1 && $classe <= 3) { // já escolheu: só informação.
		display($topo."<center>".tf("Sua especialização é <b>%s</b>. Essa escolha é definitiva.", nomedaclasse($classe))."</center><br />".$cartoes(false).$voltar, t("Especialização"));
	}
	$cidade = mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns")) > 0;
	$nivel = nivelparaescolher();
	$aviso = "";
	if (!$cidade) { $aviso = t("Você só pode escolher a sua especialização dentro de uma vila."); }
	elseif (intval($userrow["level"]) < $nivel) { $aviso = tf("Você poderá escolher a sua especialização a partir do nível %s. Até lá, conheça cada uma delas:", $nivel); }

	$escolhida = pegarint($_POST, "classe");
	if ($aviso == "" && (isset($_POST["classe"]) || isset($_POST["confirmar"])) && ($escolhida < 1 || $escolhida > 3)) { $aviso = t("Escolha uma das três especializações."); }
	elseif ($aviso == "" && isset($_POST["confirmar"])) {
		if (!csrf_confere()) { $aviso = t("A sessão do formulário expirou. Tente de novo."); }
		elseif (pegartexto($_POST, "definitiva") != "1") { $aviso = t("Marque a caixa confirmando que você entende que a escolha é definitiva."); }
		else {
			$erro = escolherclasse(intval($userrow["id"]), $escolhida);
			if ($erro == "") { header("Location: especializacao.php?conteudo=".rawurlencode(tf("Agora você é um ninja de %s!", nomedaclasse($escolhida)))); die(); }
			$aviso = t($erro);
		}
	}
	$avisoconf = ""; // checkbox não marcada / formulário expirado: volta para a confirmação com o aviso.
	if (isset($_POST["confirmar"]) && $escolhida >= 1 && $escolhida <= 3 && in_array($aviso, array(t("Marque a caixa confirmando que você entende que a escolha é definitiva."), t("A sessão do formulário expirou. Tente de novo.")), true)) { $avisoconf = $aviso; $aviso = ""; }
	if ($aviso == "" && isset($_POST["classe"]) && $escolhida >= 1 && $escolhida <= 3) { // etapa de confirmação.
		$csrf = csrf_campo();
		$nome = nomedaclasse($escolhida);
		$avisoconfhtml = $avisoconf != "" ? "<div class=\"cxb-linha\" id=\"avisoconf\" style=\"color:#9b1b10;font-weight:bold\">&#9888; ".$avisoconf."</div>" : "";
		$page = $topo.<<<END
<form action="especializacao.php" method="post">$csrf<input type="hidden" name="classe" value="$escolhida" />
<div class="cxb" id="confirmarclasse" style="max-width:460px;margin:0 auto 10px;box-shadow:0 0 0 2px #9b1b10"><div class="cxb-cab" style="background:#9b1b10"><span>{$t("Atenção: a escolha é definitiva!")}</span></div><div class="cxb-corpo">
<div class="cxb-linha">{$tf("Você está prestes a se tornar um ninja de <b>%s</b>. Depois de confirmar, <b>não será possível trocar</b> de especialização.", $nome)}</div>
<div class="cxb-linha">{$t("Seu HP, Chakra, Força e Destreza serão ajustados para a nova especialização e você aprenderá os jutsus dela até o seu nível atual.")}</div>
$avisoconfhtml<div class="cxb-linha"><label style="cursor:pointer"><input type="checkbox" name="definitiva" value="1" /> <b>{$t("Entendo que essa escolha é definitiva.")}</b></label></div>
<div style="text-align:center;padding:8px 4px" class="buttons btnlinha"><button class="btnjogo bj-confirmar" type="submit" name="confirmar" value="1" id="confirmarespec"><img src="images/botao_sim.png" width="16" height="16" alt="" style="vertical-align:middle"> {$tf("Confirmar: ser %s", $nome)}</button> <a href="especializacao.php" class="btnjogo bj-neutro" id="pensarmelhor"><img src="images/seta_oeste.png" width="16" height="16" alt="" style="vertical-align:middle"> {$t("Voltar e pensar melhor")}</a></div>
</div></div></form>
END;
		display($page.$cartoes(false), t("Especialização"));
	}

	$msg = strip_tags(lermsg("conteudo"));
	$page = $topo;
	if ($msg != "") { $page .= "<center><font color=\"darkgreen\"><b>$msg</b></font></center><br />"; }
	$page .= "<center>".t("Todo ninja começa como estudante, sem especialização. Escolha o caminho que combina com o seu jeito de lutar: <b>a escolha é definitiva</b>.")."</center><br />";
	if ($aviso != "") { $page .= "<center><font color=\"#9b1b10\"><b>$aviso</b></font></center><br />"; }
	$pode = $cidade && intval($userrow["level"]) >= $nivel; // na vila e com nível: mostra o formulário (com o aviso de erro, se houver).
	if ($pode) {
		$page .= "<form action=\"especializacao.php\" method=\"post\" style=\"margin:0\">".$cartoes("botao")."</form>".$voltar;
	} else {
		$page .= $cartoes(true).$voltar;
	}
	display($page, t("Especialização"));
}

function escolherclasse($id, $classe) { // Grava a especialização escolhida (charclass 0 -> 1..3), ajustando status e jutsus. Devolve "" ou a chave do erro.
	global $link;
	$classe = intval($classe);
	if ($classe < 1 || $classe > 3) { return "Escolha uma das três especializações."; }
	doquery("START TRANSACTION", "users");
	$u = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' LIMIT 1 FOR UPDATE", "users"));
	if (!$u || intval($u["charclass"]) != 0) { doquery("ROLLBACK", "users"); return "Você já escolheu a sua especialização."; }
	if (intval($u["level"]) < nivelparaescolher()) { doquery("ROLLBACK", "users"); return "Você ainda não tem nível para escolher a especialização."; }
	$nivel = intval($u["level"]);
	// Diferença entre a classe nova e as colunas 1_* usadas enquanto era estudante (do nível 2 ao atual).
	$d = mysqli_fetch_array(doquery("SELECT CAST(SUM(".$classe."_hp) AS SIGNED) - CAST(SUM(1_hp) AS SIGNED) AS hp, CAST(SUM(".$classe."_mp) AS SIGNED) - CAST(SUM(1_mp) AS SIGNED) AS mp, CAST(SUM(".$classe."_tp) AS SIGNED) - CAST(SUM(1_tp) AS SIGNED) AS tp, CAST(SUM(".$classe."_strength) AS SIGNED) - CAST(SUM(1_strength) AS SIGNED) AS forca, CAST(SUM(".$classe."_dexterity) AS SIGNED) - CAST(SUM(1_dexterity) AS SIGNED) AS destreza FROM {{table}} WHERE id>='2' AND id<='$nivel'", "levels"));
	$dhp = intval($d["hp"]); $dmp = intval($d["mp"]); $dtp = intval($d["tp"]); $dfo = intval($d["forca"]); $dde = intval($d["destreza"]);
	// Jutsus da classe até o nível atual (sem os exclusivos de missão e sem repetir os que já conhece).
	$conhecidos = array_filter(explode(",", (string)$u["spells"]), "strlen");
	$exclusivos = array();
	$qe = doquery("SELECT id FROM {{table}} WHERE exclusivo_missao='1'", "spells");
	while ($r = mysqli_fetch_array($qe)) { $exclusivos[] = (string)intval($r["id"]); }
	$q = doquery("SELECT ".$classe."_spells AS s FROM {{table}} WHERE id<='$nivel' AND ".$classe."_spells<>'0' ORDER BY id", "levels");
	$novos = array();
	while ($r = mysqli_fetch_array($q)) { $s = (string)intval($r["s"]); if (!in_array($s, $conhecidos, true) && !in_array($s, $novos, true) && !in_array($s, $exclusivos, true)) { $novos[] = $s; } }
	$spells = implode(",", array_merge($conhecidos, $novos));
	if (strlen($spells) > 250) { $spells = (string)$u["spells"]; $novos = array(); } // limite da coluna: mantém como está.
	global $controlrow;
	$aviso = i18n("Você escolheu a especialização %s.", $controlrow["class".$classe."name"]); // nome em português; o textoi18n() traduz para quem lê.
	$historico = ($u["historico"] == "None" || $u["historico"] == "") ? $aviso : $u["historico"].";;".$aviso;
	doquery("UPDATE {{table}} SET charclass='$classe',
		currenthp=LEAST(GREATEST(1, CAST(currenthp AS SIGNED)+($dhp)), GREATEST(1, CAST(maxhp AS SIGNED)+($dhp))), maxhp=GREATEST(1, CAST(maxhp AS SIGNED)+($dhp)),
		currentmp=LEAST(GREATEST(0, CAST(currentmp AS SIGNED)+($dmp)), GREATEST(0, CAST(maxmp AS SIGNED)+($dmp))), maxmp=GREATEST(0, CAST(maxmp AS SIGNED)+($dmp)),
		currenttp=LEAST(GREATEST(0, CAST(currenttp AS SIGNED)+($dtp)), GREATEST(0, CAST(maxtp AS SIGNED)+($dtp))), maxtp=GREATEST(0, CAST(maxtp AS SIGNED)+($dtp)),
		strength=GREATEST(1, CAST(strength AS SIGNED)+($dfo)), attackpower=GREATEST(1, CAST(attackpower AS SIGNED)+($dfo)),
		dexterity=GREATEST(1, CAST(dexterity AS SIGNED)+($dde)), defensepower=GREATEST(1, CAST(defensepower AS SIGNED)+($dde)),
		spells='".addslashes($spells)."', historico='".addslashes($historico)."'
		WHERE id='".intval($id)."' AND charclass='0' LIMIT 1", "users");
	$ok = (mysqli_affected_rows($link) == 1);
	doquery($ok ? "COMMIT" : "ROLLBACK", "users");
	return $ok ? "" : "Você já escolheu a sua especialização.";
}
?>
