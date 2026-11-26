<?php // funcoesavatares.php :: Avatares do personagem (catálogo naruto_avatares, avatares liberados por jogador e como liberar cada um).
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

/* Avatar = retrato (layoutnovo/avatares/<n>.jpg) + animações de batalha/minimapa (layoutnovo/graficos/<n>_*.gif).
   Não existe mais conta VIP/premium. Cada avatar do catálogo (tabela avatares) tem um modo:
     livre    -> todos os jogadores têm;
     drop     -> liberado ao pegar um "Pergaminho de Disfarce" (drops.ensina_avatar = id do avatar) que cai de inimigos;
     missao   -> recompensa de missão (missoesextras.recompensa_avatar = id do avatar);
     especial -> regra própria em PHP (16: Fukasaku e Shima entram no grupo, senjutsu.php).
   users.avatares_liberados = "6,9,4" (lista dos ids liberados). O avatar que o jogador está usando sempre conta como liberado
   (jogadores antigos mantêm o avatar que tinham). */

if (!function_exists('avatarescatalogo')) {

function avatarescatalogo() { // Todos os avatares ativos: id => linha (lida uma vez por página).
	static $cache = null;
	if ($cache === null) {
		$cache = array();
		$q = doquery("SELECT * FROM {{table}} WHERE ativo='1' ORDER BY ordem, id", "avatares");
		while ($r = mysqli_fetch_array($q, MYSQLI_ASSOC)) { $cache[intval($r["id"])] = $r; }
	}
	return $cache;
}

function avatareslivres() { // Ids dos avatares livres.
	$lista = array();
	foreach (avatarescatalogo() as $id => $r) { if ($r["modo"] == "livre") { $lista[] = $id; } }
	return $lista;
}

function avataresdojogador($row) { // Ids liberados para o jogador: livres + lista gravada + avatar atual.
	$lista = avatareslivres();
	foreach (explode(",", isset($row["avatares_liberados"]) ? (string)$row["avatares_liberados"] : "") as $a) { if (intval($a) > 0) { $lista[] = intval($a); } }
	if (isset($row["avatar"]) && intval($row["avatar"]) > 0) { $lista[] = intval($row["avatar"]); }
	return array_values(array_unique($lista));
}

function avatartemliberado($row, $id) { return in_array(intval($id), avataresdojogador($row), true); } // O jogador já tem esse avatar?

function avatarliberar($usuarioid, $id) { // Acrescenta o avatar à lista do jogador. true se liberou agora, false se já tinha (ou não existe).
	$id = intval($id); $usuarioid = intval($usuarioid);
	if ($id <= 0 || !file_exists("layoutnovo/avatares/".$id.".jpg")) { return false; }
	$row = mysqli_fetch_array(doquery("SELECT id,avatar,avatares_liberados FROM {{table}} WHERE id='$usuarioid' LIMIT 1", "users"));
	if (!$row || avatartemliberado($row, $id)) { return false; }
	// O WHERE com o valor lido impede gravar duas vezes (ou perder outro avatar liberado ao mesmo tempo).
	$novo = trim($row["avatares_liberados"].",".$id, ",");
	doquery("UPDATE {{table}} SET avatares_liberados='".addslashes($novo)."' WHERE id='$usuarioid' AND avatares_liberados='".addslashes($row["avatares_liberados"])."' LIMIT 1", "users");
	global $link;
	return mysqli_affected_rows($link) > 0;
}

function avatarnome($id) { // Nome do avatar (do catálogo) ou "#id".
	$c = avatarescatalogo();
	return isset($c[intval($id)]) ? t($c[intval($id)]["nome"]) : "#".intval($id);
}

function avatarcomoliberar($id) { // Texto (já traduzido) de como liberar um avatar bloqueado.
	global $dbsettings;
	$id = intval($id);
	$c = avatarescatalogo();
	if (!isset($c[$id])) { return ""; }
	$modo = $c[$id]["modo"];
	if ($modo == "livre") { return t("Livre para todos."); }
	if ($modo == "especial") {
		if ($id == 16) { return t("Chame Fukasaku e Shima para o seu grupo na Montanha Myoboku (Senjutsu)."); }
		return t("Liberado por um evento especial.");
	}
	if ($modo == "drop") {
		$nomes = array();
		$q = doquery("SELECT m.name, m.level FROM ".$dbsettings["prefix"]."_monster_drops AS md, ".$dbsettings["prefix"]."_monsters AS m, {{table}} AS d WHERE d.ensina_avatar='$id' AND md.drop_id=d.id AND m.id=md.monster_id ORDER BY m.level", "drops");
		while ($r = mysqli_fetch_array($q)) { $nomes[] = tf("%s (nível %s)", t($r["name"]), intval($r["level"])); }
		if (!count($nomes)) { return t("Pergaminho de Disfarce: no momento nenhum inimigo o solta."); }
		return tf("Pergaminho de Disfarce (raro) dos inimigos: %s.", implode(", ", $nomes));
	}
	$nomes = array();
	$q = doquery("SELECT id, titulo, titulo_en, nivel_minimo FROM {{table}} WHERE recompensa_avatar='$id' AND ativa='1' ORDER BY nivel_minimo", "missoesextras");
	while ($r = mysqli_fetch_array($q)) { $nomes[] = tf("%s (nível %s)", (idioma() == "en" && $r["titulo_en"] != "") ? $r["titulo_en"] : t($r["titulo"]), intval($r["nivel_minimo"])); }
	if (!count($nomes)) { return t("Recompensa de missão: no momento nenhuma missão ativa dá este avatar."); }
	return tf("Recompensa da missão: %s.", implode(", ", $nomes));
}

}
?>
