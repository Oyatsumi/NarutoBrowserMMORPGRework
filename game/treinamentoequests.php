<?php // treinamentoequests.php :: Painel de Treinos (jutsus treinados com tempo de espera) e Painel de Missões.
//
// Também é incluído por missoes.php (passarconteudo) e por funcoesinclusas.php (personagemmissao), que
// mostram a fala de um personagem em cima do painel. Nesses casos $valorlib já vem preenchido e o lib.php
// não é incluído de novo.
//
// Formato de users.treinamento e users.questsaux (missões auxiliares): uma lista terminada em ";"
//   "nome,feitos,total,data,hora,intervalo;nome,feitos,total,data,hora,intervalo;"   (ou "None")
//   - data no formato d/m/aaaa e hora hh:mm:ss do último treino; intervalo em minutos até o próximo treino.
// Formato de users.missao (missões obrigatórias): veja o cabeçalho de missoes.php.

if (empty($valorlib)) { // valor para não declarar o lib.php duas vezes.
	include('lib.php');
	$link = opendb();
	include('cookies.php');
	$userrow = checkcookies();
}
include_once('funcoesinclusas.php');
include_once('funcoestreinos.php'); // treinos longos (dadosdotreino).
include_once('funcoesmissoesextras.php'); // mx_estilo().

if (!function_exists('jsarg')) {
function jsarg($texto) { // Prepara um texto para ir entre aspas simples num href="javascript:...('texto')".

	// \' e \\ para o JavaScript, &quot; para o atributo HTML e %25 porque o navegador decodifica "%xx" em URLs javascript:.
	return str_replace(array("\\", "'", "\"", "%"), array("\\\\", "\\'", "&quot;", "%25"), (string)$texto);

}
}


if (isset($_GET["do"])) {
	$do = $_GET["do"];
	if ($do == "treinamento") { treinamento(); }
	elseif ($do == "quests") { quests(""); }
}


function verificarpainel() { // Checagens comuns aos dois painéis: logado, fora de batalha e fora de duelo.

	global $userrow;
	if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
	if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t("Você não pode acessar essa função no meio de uma batalha!"))); die(); }
	if ($userrow["batalha_timer2"] == 5) { display(t("Você não pode fazer nenhum movimento enquanto estiver em um duelo. Clique <a href=\"users.php?do=resetarduelo\">aqui</a>, para resetar seu Duelo atual. "),t("Erro"),false,false,false); die(); }

}


function cidadeatual() { // Cidade onde o jogador está (ou false se ele estiver fora de uma cidade).

	global $userrow;
	$townquery = doquery("SELECT * FROM {{table}} WHERE latitude='".intval($userrow["latitude"])."' AND longitude='".intval($userrow["longitude"])."' LIMIT 1", "towns");
	if (mysqli_num_rows($townquery) == 0) { return false; }
	return mysqli_fetch_array($townquery);

}


function pedrasprogresso($porcentagem) { // Desenha as 6 pedras de progresso (cada pedra cheia = 20%, a sexta só aos 100%).

	if ($porcentagem < 0) { $porcentagem = 0; }
	if ($porcentagem > 100) { $porcentagem = 100; }
	if ($porcentagem == 0) { $cheias = 0; }
	elseif ($porcentagem == 100) { $cheias = 6; }
	else { $cheias = floor($porcentagem / 20) + 1; }
	$pedra = "";
	for ($p = 1; $p <= 6; $p++) {
		$imagem = ($p <= $cheias) ? "pedracheia" : "pedravazia";
		$pedra .= "<img src=\"images/".$imagem.".gif\" title=\"".tf("Porcentagem Concluída: %s%%", $porcentagem)."\">";
	}
	return $pedra;

}


function falanopainel($conteudodois, $conteudo, $townrow, $saudacao) { // Fala do Kage em cima do painel (ou a mensagem solta, fora da cidade).

	if ($conteudodois == "") {
		if ($townrow == false) {
			$conteudodois = "<br>";
		} elseif ($conteudo != "") {
			$conteudodois = personagemgeral($conteudo, $townrow["id"], t($townrow["kage"]));
			$conteudo = "";
		} else {
			$conteudodois = personagemgeral($saudacao, $townrow["id"], t($townrow["kage"]));
		}
	}
	if ($conteudo != "") { $conteudo = "<center><font color=brown>".strip_tags($conteudo)."</font></center><br>"; }
	return $conteudo.$conteudodois;

}





function dadosdotreino($nome, $townrow, $painel) { // Descrição dos treinos conhecidos e da cidade onde cada um é feito (já traduzida).

	// array(recompensa, requerimento, local, id da cidade, link para treinar)
	if ($nome == "Jutsu de Busca") {
		$dados = array(t("Utilização do Jutsu de Busca."), ($painel == "treino") ? t("Possuir o Byakugan como Jutsu Ocular.") : t("Nenhum."), t("Vila da Areia."), 5, "jutsudebusca.php?do=aprendendo2");
	} elseif ($nome == "Senjutsu") {
		$dados = array(t("Aquisição da Arte Eremita, o Senjutsu. Quando ativado, lhe concederá 20% de aumento de Ataque e Defesa."), t("Ser um Genin."), t("Montanha Myoboku."), 2, "senjutsu.php?do=aprendendo2");
	} elseif ($tr = treinopelonome($nome)) { // treinos longos (tabela treinos, funcoestreinos.php).
		global $userrow;
		$recompensa = treinodescricao($tr);
		$atual = is_array($userrow) ? treinobonusatual($tr, $userrow) : "";
		if ($atual != "") { $recompensa .= " ".tf("(Obtido até agora: %s.)", $atual); }
		$local = tf("%s, com %s.", treinonomevila($tr["vila_id"]), t($tr["mestre"]))." ".tf("Sessões: %s, a cada %s minutos.", intval($tr["sessoes"]), intval($tr["intervalo"]));
		$dados = array($recompensa, treinorequisitostexto($tr), $local, intval($tr["vila_id"]), "treinoslongos.php?do=treinar&t=".rawurlencode($tr["chave"]));
	} else {
		$dados = array("?", "?", "?", 0, "");
	}
	$dados[5] = (($townrow != false) && ($townrow["id"] == $dados[3])); // está na cidade certa?
	return $dados;

}


function treinamento($conteudodois="") { // Painel de Treinos: um por linha, com relógio de espera e pedras de progresso.

	global $userrow;
	verificarpainel();
	$conteudo = lermsg("conteudo");
	$townrow = cidadeatual();
	$html = "";
	$mostrar = t("Mostrar Dados"); $esconder = t("Esconder Dados");

	$treinamento = ($userrow["treinamento"] == "None") ? array() : explode(";", $userrow["treinamento"]);
	foreach ($treinamento as $i => $treino) {
		if ($treino == "") { continue; }
		$subtreino = array_pad(explode(",", $treino), 6, "0");
		$feitos = intval($subtreino[1]); $total = intval($subtreino[2]);
		list($recompensa, $requerimento, $localtreino, $cidade, $link, $nacidade) = dadosdotreino($subtreino[0], $townrow, "treino");

		$off = ""; $frase = t("Treinar Agora");
		$linkdotreino = ($link != "") ? "<a href=\"".$link."\">" : ""; $linkdotreino2 = ($link != "") ? "</a>" : "";
		if (!$nacidade) { $off = "2"; $frase = t("Treino não Disponível"); }

		// Porcentagem (sem arredondar, como no original).
		$porcentagem = ($total > 0) ? ($feitos * 100) / $total : 0;
		$pedra = pedrasprogresso($porcentagem);

		// Relógio: tempo de espera desde o último treino.
		$retorno = tempojutsu($subtreino[3], $subtreino[4], intval($subtreino[5]));
		if ($retorno === "ok") { $relogio = "relogio2"; $relogiotitle = t("Tempo de Espera Concluído"); }
		else { $relogio = "relogio"; $relogiotitle = tf("Restam %s Minutos de Espera até o Próximo Treinamento", $retorno); $off = "2"; $frase = t("Treino não Disponível"); }

		if (($total > 0) && ($feitos >= $total)) {
			$relogio = "relogio2"; $relogiotitle = t("Treinamento Concluído"); $off = "2"; $frase = t("Treinamento Concluído");
			$linkdotreino = ""; $linkdotreino2 = "";
		}

		$html .= "<table width=\"100%\"><tr bgcolor=\"#613003\"><td width=\"*\" class=\"mxtitulo\" title=\"".t("Clique para ver os detalhes")."\" onclick=\"var a=this.parentNode.getElementsByTagName('a');var d=document.getElementById('acao$i');if(d&&d.style.display=='block'){a[a.length-1].click();}else{a[a.length-2].click();}\"><font color=\"white\">".t($subtreino[0])."</font></td><td width=\"17\"><img src=\"images/".$relogio.".gif?v=2\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated\" title=\"".$relogiotitle."\"></td><td width=\"17\">$linkdotreino<img src=\"images/aceitar_missao".($off == "2" ? "_off" : "").".png\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated;vertical-align:middle\" title=\"$frase\" border=\"0\">$linkdotreino2</td><td width=\"84\">$pedra</td><td width=\"20\"><a href=\"javascript:mxacao('$i',1);mostrartreino('$i', '".jsarg($recompensa)."', '".jsarg($requerimento)."', '".jsarg($localtreino)."')\"><img src=\"images/setabaixo.gif\" title=\"$mostrar\" border=\"0\"></a></td><td width=\"20\"><a href=\"javascript:mxacao('$i',0);escondertreino('$i')\"><img src=\"images/setacima.gif\" title=\"$esconder\" border=\"0\"></a></td></tr></table><div id=\"elemento$i\"></div>".tq_bloco("$i", $relogio, $relogiotitle, $off, $frase, $linkdotreino, $linkdotreino2)."<br>";
	}

	// Nenhum treino e nenhuma fala para mostrar: volta para o jogo.
	// Sem nenhum treino: em vez de voltar para o jogo sem explicar nada, mostra onde cada treino pode ser começado.
	if ($html == "") {
		$lista = "<tr bgcolor=\"#613003\"><td><font color=\"white\">".t("Treino")."</font></td><td><font color=\"white\">".t("Onde começar")."</font></td><td><font color=\"white\">".t("Requisito")."</font></td></tr>";
		$lista .= "<tr bgcolor=\"#FFF1C7\"><td>".t("Senjutsu")."</td><td>".t("Montanha Myoboku.")."</td><td>".t("Ser um Genin.")."</td></tr>";
		$lista .= "<tr bgcolor=\"#E4D094\"><td>".t("Jutsu de Busca")."</td><td>".t("Vila da Areia.")."</td><td>".t("Possuir o Byakugan como Jutsu Ocular.")."</td></tr>";
		$cor = 0;
		$q = doquery("SELECT t.nome, t.mestre, t.nivel_min, t.graduacao_min, v.name AS vila FROM {{table}} t LEFT JOIN ".$GLOBALS["dbsettings"]["prefix"]."_towns v ON v.id=t.vila_id WHERE t.ativo=1 ORDER BY t.nivel_min, t.id", "treinos");
		while ($r = mysqli_fetch_array($q)) {
			$req = tf("Nível %s", intval($r["nivel_min"])).((string)$r["graduacao_min"] != "" ? ", ".t($r["graduacao_min"]) : "");
			$lista .= "<tr bgcolor=\"".(($cor++ % 2) ? "#E4D094" : "#FFF1C7")."\"><td>".t($r["nome"])."</td><td>".t((string)$r["vila"])." (".t($r["mestre"]).")</td><td>".$req."</td></tr>";
		}
		$conteudodois .= "<center><b>".t("Você ainda não adquiriu nenhum treinamento.")."</b><br>".t("Os treinos são começados conversando com os mestres nas vilas (ícones no quadro \"Outros\" de cada vila). Depois de começar, eles aparecem aqui.")."</center><br><table width=\"100%\">$lista</table>";
	}

	$saudacao = ($townrow != false) ? tf('Olá %s! você está na(o) %s, pretende treinar algum Jutsu? Posso ajudar em alguma coisa? Use as opções abaixo para completar treinamentos.', $userrow['charname'], t($townrow['name'])) : '';
	$page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/treinamento.gif\" /></center></td></tr></table>
	".falanopainel($conteudodois, $conteudo, $townrow, $saudacao)."
	$html";

	display($page, t("Treinamento"), false, false, false);
	die();

}


function quests($conteudodois="") { // Painel de Missões: missões auxiliares (questsaux) e as missões obrigatórias até a etapa atual.

	global $userrow;
	verificarpainel();
	$conteudo = lermsg("conteudo");
	$townrow = cidadeatual();
	$html = "";
	$mostrar = t("Mostrar Dados"); $esconder = t("Esconder Dados");

	// Missões auxiliares (não obrigatórias), no mesmo formato do treinamento.
	$quests = ($userrow["questsaux"] == "None") ? array() : explode(";", $userrow["questsaux"]);
	foreach ($quests as $i => $quest) {
		if ($quest == "") { continue; }
		$subtreino = array_pad(explode(",", $quest), 6, "0");
		$feitos = intval($subtreino[1]); $total = intval($subtreino[2]);
		list($recompensa, $requerimento, $localtreino, $cidade, $link, $nacidade) = dadosdotreino($subtreino[0], $townrow, "quest");
		$nivel = t("Rank ?");
		$conclusao = t("Não Obrigatória.");

		$off = ""; $frase = t("Fazer Missão Agora");
		$linkdotreino = ($link != "") ? "<a href=\"".$link."\">" : ""; $linkdotreino2 = ($link != "") ? "</a>" : "";
		if (!$nacidade) { $off = "2"; $frase = t("Não Disponível"); }

		$porcentagem = ($total > 0) ? ceil(($feitos * 100) / $total) : 0;
		$pedra = pedrasprogresso($porcentagem);

		$retorno = tempojutsu($subtreino[3], $subtreino[4], intval($subtreino[5]));
		if ($retorno === "ok") { $relogio = "relogio2"; $relogiotitle = t("Tempo de Espera Concluído"); }
		else { $relogio = "relogio"; $relogiotitle = tf("Restam %s Minutos de Espera", $retorno); $off = "2"; $frase = t("Não Disponível"); }

		if (($total > 0) && ($feitos >= $total)) {
			$relogio = "relogio2"; $relogiotitle = t("Missão Concluída"); $off = "2"; $frase = t("Missão Concluída");
			$linkdotreino = ""; $linkdotreino2 = "";
		}
		if (($feitos == 0) && ($total == 0)) { // missão sem parte repetitiva.
			$relogio = "relogio2"; $relogiotitle = t("Sem Tempo de Espera para essa Missão"); $off = ""; $frase = t("Completar Missão");
		}

		$html .= "<table width=\"100%\"><tr bgcolor=\"#613003\"><td width=\"*\" class=\"mxtitulo\" title=\"".t("Clique para ver os detalhes")."\" onclick=\"var a=this.parentNode.getElementsByTagName('a');var d=document.getElementById('acao{$i}aux');if(d&&d.style.display=='block'){a[a.length-1].click();}else{a[a.length-2].click();}\"><font color=\"white\">".t($subtreino[0])."</font></td><td width=\"17\"><img src=\"images/".$relogio.".gif?v=2\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated\" title=\"".$relogiotitle."\"></td><td width=\"17\">$linkdotreino<img src=\"images/aceitar_missao".($off == "2" ? "_off" : "").".png\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated;vertical-align:middle\" title=\"$frase\" border=\"0\">$linkdotreino2</td><td width=\"84\">$pedra</td><td width=\"20\"><a href=\"javascript:mxacao('{$i}aux',1);mostrarquest('{$i}aux', '".jsarg($recompensa)."', '".jsarg($requerimento)."', '".jsarg($localtreino)."', '".jsarg($nivel)."', '".jsarg($conclusao)."')\"><img src=\"images/setabaixo.gif\" title=\"$mostrar\" border=\"0\"></a></td><td width=\"20\"><a href=\"javascript:mxacao('{$i}aux',0);escondertreino('{$i}aux')\"><img src=\"images/setacima.gif\" title=\"$esconder\" border=\"0\"></a></td></tr></table><div id=\"elemento{$i}aux\"></div>".tq_bloco("{$i}aux", $relogio, $relogiotitle, $off, $frase, $linkdotreino, $linkdotreino2)."<br>";
	}

	// Lê de novo a missão do banco: o missoes.php pode ter acabado de alterá-la antes de mostrar o painel.
	$atualizar = doquery("SELECT missao, missaoultdata FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
	if (mysqli_num_rows($atualizar) == 0) { display(t("Há um erro com sua conta. Por favor tente novamente."), t("Erro")); die(); }
	$atualizardados = mysqli_fetch_array($atualizar);
	$userrow["missao"] = $atualizardados["missao"];
	$userrow["missaoultdata"] = $atualizardados["missaoultdata"];

	// Missões obrigatórias: vêm do banco (naruto_missoesextras, categoria 'historia', ordenadas pela etapa).
	// Textos em português nas colunas normais e em inglês nas colunas _en; nomes de itens/monstros (colunas *_arg)
	// continuam em português no banco e são traduzidos com t(), igual em todo o jogo.
	// mx_argsmapa() monta os parâmetros extras do mostrarquest(): item procurado (mapa de drop), "latitude/longitude"
	// do NPC e monstro procurado (mapa de respawn); eles NÃO são traduzidos, pois o mapa procura pelo nome no banco.
	include_once('funcoesmissoesextras.php');
	$missoes = mx_historia();

	$subtreino = array_pad(explode(",", $userrow["missao"]), 4, "0");
	$etapa = intval($subtreino[0]);
	$feitos = intval($subtreino[1]); $total = intval($subtreino[2]);
	$ultimavez = array_pad(explode(";", $userrow["missaoultdata"]), 2, "0:0:0");
	$conclusao = jsarg(t("Obrigatória."));

	// Mostra todas as missões já liberadas (da primeira até a etapa atual).
	foreach ($missoes as $i => $m) {
		if ($i > $etapa) { break; }
		$titulo = tf("Missão %s: %s", $i, mx_titulo($m));
		$recompensa = jsarg(mx_comarg(mx_texto($m, "recompensa_texto"), $m["recompensa_arg"]));
		$requerimento = jsarg(mx_comarg(mx_texto($m, "descricao"), $m["descricao_arg"]));
		$localtreino = jsarg(t($m["local_texto"]));
		$nivel = jsarg(t($m["rank_texto"]));
		$itemprocurado = mx_argsmapa($m);
		$cidade = intval($m["vila_id"]);
		$linkdotreino = "<a href=\"missoes.php?do=missao\">"; $linkdotreino2 = "</a>";

		if ($i < $etapa) { // missão já concluída.
			$pedra = pedrasprogresso(100);
			$linkdotreino = ""; $linkdotreino2 = "";
			$off = "2"; $frase = t("Missão Concluída"); $relogio = "relogio2"; $relogiotitle = t("Missão Concluída");
		} else { // missão atual.
			$pedra = pedrasprogresso(($total > 0) ? ceil(($feitos * 100) / $total) : 0);
			$off = ""; $frase = t("Fazer Missão Agora"); $relogio = "relogio2"; $relogiotitle = t("Tempo de Espera Concluído");
			if ($total == 0) {
				$relogiotitle = t("Sem Tempo de Espera para essa Missão"); $frase = t("Completar Missão");
			} elseif ($feitos >= $total) { // repetições feitas, falta o resto do requerimento (ex.: Missão 12).
				$frase = t("Completar Missão");
			} elseif ($userrow["missaoultdata"] != "None") {
				$retorno = tempojutsu($ultimavez[0], $ultimavez[1], intval($subtreino[3]));
				if ($retorno !== "ok") { $relogio = "relogio"; $relogiotitle = tf("Restam %s Minutos de Espera", $retorno); $off = "2"; $frase = t("Não Disponível"); }
			}
			if (($townrow == false) || ($townrow["id"] != $cidade)) { $off = "2"; $frase = t("Não Disponível"); }
		}

		$html .= "<table width=\"100%\"><tr bgcolor=\"#613003\"><td width=\"*\" class=\"mxtitulo\" title=\"".t("Clique para ver os detalhes")."\" onclick=\"var a=this.parentNode.getElementsByTagName('a');var d=document.getElementById('acao$i');if(d&&d.style.display=='block'){a[a.length-1].click();}else{a[a.length-2].click();}\"><font color=\"white\">".$titulo."</font></td><td width=\"17\"><img src=\"images/".$relogio.".gif?v=2\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated\" title=\"".$relogiotitle."\"></td><td width=\"17\">$linkdotreino<img src=\"images/aceitar_missao".($off == "2" ? "_off" : "").".png\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated;vertical-align:middle\" title=\"$frase\" border=\"0\">$linkdotreino2</td><td width=\"84\">$pedra</td><td width=\"20\"><a href=\"javascript:mxacao('$i',1);mostrarquest('$i', '$recompensa', '$requerimento', '$localtreino', '$nivel', '$conclusao'$itemprocurado)\"><img src=\"images/setabaixo.gif\" title=\"$mostrar\" border=\"0\"></a></td><td width=\"20\"><a href=\"javascript:mxacao('$i',0);escondertreino('$i')\"><img src=\"images/setacima.gif\" title=\"$esconder\" border=\"0\"></a></td></tr></table><div id=\"elemento$i\"></div>".tq_bloco("$i", $relogio, $relogiotitle, $off, $frase, $linkdotreino, $linkdotreino2)."<br>";
	}

	// Se não houver nada.
	if (($html == "") && ($conteudodois == "")) { header('Location: ./index.php?conteudo='.rawurlencode(t("Você ainda não adquiriu nenhuma missão."))); die(); }

	$saudacao = ($townrow != false) ? tf('Olá %s! Você está na(o) %s, pretende completar alguma missão? Posso ajudar em alguma coisa? Use as opções abaixo para completar missões.', $userrow['charname'], t($townrow['name'])) : '';
	$page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/missao.gif\" /></center></td></tr></table>
	".falanopainel($conteudodois, $conteudo, $townrow, $saudacao)."
	$html
	<center><div class=\"small\" style=\"color:#613003;margin:0 0 4px\">".t("Mais missões dos moradores de cada vila:")."</div><a href=\"missoesextras.php\" class=\"btnjogo bj-confirmar\" style=\"text-decoration:none\"><img src=\"images/24/missoes.gif\" width=\"20\" height=\"20\" border=\"0\" alt=\"\" style=\"vertical-align:-5px\" /> ".t("Ver outras missões")."</a></center><br>";

	display($page, t("Missões"), false, false, false);
	die();

}

// Bloco escondido que aparece junto com os detalhes: tempo escrito e o botão grande da ação (igual em todas as listas de missões).
function tq_bloco($k, $relogio, $relogiotitle, $off, $frase, $l1, $l2) {
	static $css = false;
	$h = "";
	if (!$css) { $css = true; $h .= mx_estilo(); }
	$h .= "<div id=\"acao$k\" class=\"mxacao\" style=\"display:none\"><div style=\"margin:0 0 6px;color:#613003;font-size:11px\"><img src=\"images/".$relogio.".gif?v=2\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated;vertical-align:-4px\"> <b>".t("Tempo:")."</b> ".$relogiotitle."</div>";
	if ($off == "" && $l1 != "") { $h .= str_replace("<a ", "<a class=\"btnjogo bj-confirmar\" style=\"text-decoration:none\" ", $l1)."<img src=\"images/aceitar_missao.png\" width=\"16\" height=\"16\" alt=\"\" style=\"image-rendering:pixelated;vertical-align:-3px\"> ".$frase.$l2; }
	else { $h .= "<span class=\"small\" style=\"color:#7a3d00\">".$frase."</span>"; }
	return $h."</div>";
}
?>
