<?php // funcoescla.php :: Clãs (tag, missão semanal) e temporadas do ranking de duelo. Páginas: cla.php e temporadas.php.
//
// Tabelas (tools/clas_temporadas.sql): clas, clas_membros (um clã por jogador), clas_convites, clas_log (Ryou/itens do
// banco do clã), clas_semana/clas_contrib (missão semanal), temporadas, temporada_pontos e titulos (top 3 de cada temporada).
// Ordem das travas (evita deadlock): linha do clã primeiro, depois os jogadores por id crescente.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }
include_once('funcoesinclusas.php'); // qtditem(), pilhamaxima(), itemsemqtd(), itemcomqtd().

if (!function_exists('cladojogador')) {

define('CLA_BANCOLIMITE', 50); // entradas no banco do clã.

function cladojogador($id) { // Clã do jogador (linha do clã + "cargo") ou false.
	$r = mysqli_fetch_array(doquery("SELECT c.*, m.cargo FROM {{table}}_membros m JOIN {{table}} c ON c.id=m.cla WHERE m.usuario='".intval($id)."' LIMIT 1", "clas"));
	return $r ? $r : false;
}

function clatag($id) { // "[TAG] " do clã do jogador (para a ficha e a lista de online) ou "".
	$r = mysqli_fetch_array(doquery("SELECT c.tag FROM {{table}}_membros m JOIN {{table}} c ON c.id=m.cla WHERE m.usuario='".intval($id)."' LIMIT 1", "clas"));
	return $r ? "<span class=\"clatag\" style=\"color:#7a3d00;font-weight:bold\">[".htmlspecialchars($r["tag"])."]</span> " : "";
}

function clatagsporcharname($nomes) { // charname => "[TAG] " (uma consulta só, para a lista de online).
	$lista = array(); $saida = array();
	foreach ($nomes as $n) { $lista[] = "'".addslashes($n)."'"; }
	if (!$lista) { return $saida; }
	$q = doquery("SELECT u.charname, c.tag FROM naruto_users u JOIN {{table}}_membros m ON m.usuario=u.id JOIN {{table}} c ON c.id=m.cla WHERE u.charname IN (".implode(",", $lista).")", "clas");
	while ($r = mysqli_fetch_array($q)) { $saida[$r["charname"]] = "<span style=\"color:#7a3d00;font-weight:bold\">[".htmlspecialchars($r["tag"])."]</span> "; }
	return $saida;
}

function clasemana() { // Semana atual (YEARWEEK com segunda-feira como início), igual à do MariaDB.
	$r = mysqli_fetch_array(doquery("SELECT YEARWEEK(NOW(), 1) AS s", "control"));
	return intval($r["s"]);
}

function clavitoria($userid) { // Gancho do fight.php victory(): +1 inimigo para a missão semanal do clã (dentro da transação da vitória).
	$m = mysqli_fetch_array(doquery("SELECT cla FROM {{table}}_membros WHERE usuario='".intval($userid)."' LIMIT 1", "clas"));
	if (!$m) { return; }
	$cla = intval($m["cla"]);
	// Só tabelas da missão (nunca a linha do clã nem outros jogadores), para não cruzar travas com o pagamento.
	doquery("INSERT INTO {{table}}_semana (cla, semana, total) VALUES ('$cla', YEARWEEK(NOW(),1), 1) ON DUPLICATE KEY UPDATE total=total+1", "clas");
	doquery("INSERT INTO {{table}}_contrib (cla, semana, usuario, abates) VALUES ('$cla', YEARWEEK(NOW(),1), '".intval($userid)."', 1) ON DUPLICATE KEY UPDATE abates=abates+1", "clas");
}

function clamissaopagar($cla) { // Paga a missão semanal (semana atual ou anteriores) que bateu a meta e ainda não foi paga. Fora de outra transação.
	global $controlrow, $link;
	$cla = intval($cla);
	$meta = max(1, intval($controlrow["cla_meta"])); $premio = intval($controlrow["cla_premio"]);
	doquery("START TRANSACTION", "clas");
	$c = mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE id='$cla' LIMIT 1 FOR UPDATE", "clas")); // fila: um pagamento por vez.
	if (!$c) { doquery("ROLLBACK", "clas"); return; }
	$pagas = array();
	$q = doquery("SELECT semana, total FROM {{table}}_semana WHERE cla='$cla' AND pago='0' AND total>='$meta'", "clas");
	while ($s = mysqli_fetch_array($q)) { $pagas[] = $s; }
	foreach ($pagas as $s) {
		$semana = intval($s["semana"]);
		// Só quem ainda é membro recebe; a divisão é proporcional aos inimigos derrotados.
		$qc = doquery("SELECT k.usuario, k.abates FROM {{table}}_contrib k JOIN {{table}}_membros m ON m.usuario=k.usuario AND m.cla=k.cla WHERE k.cla='$cla' AND k.semana='$semana' AND k.abates>0 ORDER BY k.usuario", "clas");
		$quem = array(); $soma = 0;
		while ($k = mysqli_fetch_array($qc)) { $quem[intval($k["usuario"])] = intval($k["abates"]); $soma += intval($k["abates"]); }
		foreach ($quem as $uid => $ab) {
			$parte = ($soma > 0) ? (int)floor($premio * $ab / $soma) : 0;
			if ($parte > 0) { doquery("UPDATE {{table}} SET gold=LEAST(16777215, gold+$parte) WHERE id='$uid' LIMIT 1", "users"); }
			avisarcla($uid, i18n("Missão semanal do clã cumprida! Você recebeu %s Ryou por %s inimigos derrotados.", $parte, $ab));
		}
		doquery("UPDATE {{table}}_semana SET pago='1' WHERE cla='$cla' AND semana='$semana' AND pago='0' LIMIT 1", "clas");
	}
	doquery("COMMIT", "clas");
}

function avisarcla($id, $aviso) { // Aviso no histórico do jogador (mesmo formato do avisarnohistorico do users.php).
	$aviso = addslashes($aviso);
	doquery("UPDATE {{table}} SET historico=IF(historico='None' OR historico='', '$aviso', CONCAT(historico, ';;', '$aviso')) WHERE id='".intval($id)."' LIMIT 1", "users");
}

// --- Banco do clã: mesmo formato do banco do jogador ("nome,id,tipo,durabilidade[,qtd];...", "None" = vazio). ---
function clabancoitens($texto) {
	$lista = array();
	if ($texto == "None" || $texto == "") { return $lista; }
	foreach (explode(";", (string)$texto) as $i) { if ($i != "" && $i != "None") { $lista[] = $i; } } // "None" solto não é item.
	return $lista;
}
function clabancotexto($lista) { return count($lista) ? implode(";", $lista).";" : "None"; }
function clabancocodigo($item) { return substr(md5((string)$item), 0, 10); } // como bancoitemcodigo(): link velho não pega outro item.
function clabancoguardar(&$itens, $texto, $limite) { // Igual ao bancoguardar() do funcaoitens.php (completa pilhas; false se lotar).
	$novo = $itens;
	$falta = qtditem($texto); $max = pilhamaxima($texto); $base = itemsemqtd($texto);
	if ($max > 1) {
		foreach ($novo as $k => $s) {
			if ($falta <= 0) { break; }
			if (itemsemqtd($s) !== $base || qtditem($s) >= $max) { continue; }
			$poe = min($max - qtditem($s), $falta);
			$novo[$k] = itemcomqtd($base, qtditem($s) + $poe); $falta -= $poe;
		}
	}
	while ($falta > 0) {
		if (count($novo) >= $limite) { return false; }
		$poe = min($max, $falta);
		$novo[] = itemcomqtd($base, $poe); $falta -= $poe;
	}
	$itens = $novo;
	return true;
}

// --- Temporadas de duelo ---
function temporadaaberta() { // Linha da temporada aberta (cria a primeira se não houver nenhuma).
	$r = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE fechada='0' ORDER BY numero DESC LIMIT 1", "temporadas"));
	if (!$r) {
		global $controlrow; $dias = max(1, intval($controlrow["temporada_dias"]));
		$u = mysqli_fetch_array(doquery("SELECT IFNULL(MAX(numero),0)+1 AS n FROM {{table}}", "temporadas"));
		doquery("INSERT IGNORE INTO {{table}} (numero, inicio, fim, fechada) VALUES ('".intval($u["n"])."', NOW(), NOW() + INTERVAL $dias DAY, 0)", "temporadas");
		$r = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE fechada='0' ORDER BY numero DESC LIMIT 1", "temporadas"));
	}
	return $r;
}

function temporadaverificar() { // Fecha (uma única vez) a temporada vencida e abre a próxima. Chamar fora de outra transação.
	global $controlrow;
	$r = temporadaaberta();
	if (!$r || strtotime($r["fim"]) > time()) { return; }
	doquery("START TRANSACTION", "temporadas");
	// Trava: a segunda requisição espera aqui e depois enxerga fechada=1.
	$r = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($r["id"])."' AND fechada='0' AND fim<=NOW() LIMIT 1 FOR UPDATE", "temporadas"));
	if (!$r) { doquery("ROLLBACK", "temporadas"); return; }
	$tid = intval($r["id"]); $num = intval($r["numero"]);
	doquery("UPDATE {{table}} SET fechada='1' WHERE id='$tid' AND fechada='0' LIMIT 1", "temporadas");
	$q = doquery("SELECT p.usuario, p.pontos FROM {{table}}_pontos p JOIN naruto_users u ON u.id=p.usuario WHERE p.temporada='$tid' AND u.authlevel='0' AND p.vitorias>0 ORDER BY p.pontos DESC, p.vitorias DESC, p.derrotas ASC, p.usuario ASC LIMIT 3", "temporada");
	$pos = 0;
	while ($p = mysqli_fetch_array($q)) {
		$pos++;
		doquery("INSERT IGNORE INTO {{table}} (usuario, temporada, posicao, pontos) VALUES ('".intval($p["usuario"])."', '$num', '$pos', '".intval($p["pontos"])."')", "titulos");
		avisarcla($p["usuario"], i18n("A Temporada %s de duelos terminou: você ficou em %sº lugar e ganhou um título!", $num, $pos));
	}
	$dias = max(1, intval($controlrow["temporada_dias"]));
	doquery("INSERT IGNORE INTO {{table}} (numero, inicio, fim, fechada) VALUES ('".($num + 1)."', NOW(), NOW() + INTERVAL $dias DAY, 0)", "temporadas");
	doquery("COMMIT", "temporadas");
}

function temporadaduelo($vencedor, $perdedor) { // Gancho do fim do duelo (users.php dueloencerrar, dentro da transação): pontos da temporada aberta.
	global $controlrow;
	$v = intval(isset($controlrow["temporada_vitoria"]) ? $controlrow["temporada_vitoria"] : 10);
	$d = intval(isset($controlrow["temporada_derrota"]) ? $controlrow["temporada_derrota"] : 3);
	$t = mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE fechada='0' ORDER BY numero DESC LIMIT 1", "temporadas"));
	if (!$t) { return; }
	$tid = intval($t["id"]);
	doquery("INSERT INTO {{table}} (temporada, usuario, pontos, vitorias) VALUES ('$tid', '".intval($vencedor)."', '$v', 1) ON DUPLICATE KEY UPDATE pontos=pontos+$v, vitorias=vitorias+1", "temporada_pontos");
	// Derrota tira pontos, sem ficar negativo.
	doquery("INSERT INTO {{table}} (temporada, usuario, pontos, derrotas) VALUES ('$tid', '".intval($perdedor)."', 0, 1) ON DUPLICATE KEY UPDATE pontos=IF(pontos>$d, pontos-$d, 0), derrotas=derrotas+1", "temporada_pontos");
}

function titulotexto($posicao, $numero) { // Nome do título no idioma de quem vê.
	if ($posicao == 1) { return tf("Campeão da Temporada %s", $numero); }
	if ($posicao == 2) { return tf("Vice-campeão da Temporada %s", $numero); }
	return tf("3º lugar da Temporada %s", $numero);
}

function titulosficha($id) { // Molduras dos títulos de temporada para a ficha pública (mais recentes primeiro).
	$cores = array(1 => array("#b8860b", "#fff4c2"), 2 => array("#7d7d7d", "#f1f1f1"), 3 => array("#8c4a1c", "#f7e1cf"));
	$html = "";
	$q = doquery("SELECT posicao, temporada FROM {{table}} WHERE usuario='".intval($id)."' ORDER BY temporada DESC, posicao ASC LIMIT 5", "titulos");
	while ($r = mysqli_fetch_array($q)) {
		$c = $cores[max(1, min(3, intval($r["posicao"])))];
		$html .= "<div class=\"titulotemporada\" style=\"margin:2px auto;padding:1px 3px;border:2px double ".$c[0].";background:".$c[1].";color:#3a2000;font-size:10px;font-weight:bold\">&#9733; ".titulotexto(intval($r["posicao"]), intval($r["temporada"]))."</div>";
	}
	return $html;
}

}
