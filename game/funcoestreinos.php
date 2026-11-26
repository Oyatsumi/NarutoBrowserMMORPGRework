<?php // funcoestreinos.php :: Treinos longos do Painel de Treinos (tabela naruto_treinos; páginas em treinoslongos.php).
//
// Cada treino tem um mestre numa vila (treinos.vila_id). Falar com ele (do=falar) mostra a explicação; o primeiro
// "começar" coloca o treino em users.treinamento (formato no cabeçalho de treinamentoequests.php) com 0 sessões.
// Cada sessão (do=treinar) exige estar na vila, cumprir os requisitos e ter passado o intervalo desde a última.
// Recompensas (veja também tools/treinos_novos.sql e docs/TREINOS.md):
//   elemento   +bonus_sessao de EP máximo por sessão, até bonus_final no total (users.treino_ep).
//   chakra     +bonus_sessao de CH máximo por sessão (treino_mp); ao concluir, treino_chakra = bonus_final %
//              a menos no custo dos jutsus (custochakra(), funcoesinclusas.php: batalha, duelo, PvP e jutsus de menu).
//   ferro      +bonus_sessao de HP máximo por sessão (treino_hp); ao concluir, +bonus_final de Poder de Defesa
//              (+bonus_extra para a especialização Taijutsu, charclass 2) (treino_defesa).
//   caminhada  +bonus_sessao de TP máximo por sessão (treino_tp); ao concluir, treino_tpregen = +bonus_final de TP
//              por intervalo de descanso (encherhp.php), também no mapa.
//   meditacao  requer o Senjutsu aprendido; +bonus_sessao de NP máximo por sessão (treino_np); ao concluir,
//              treino_sjlento = bonus_final segundos a mais por NP gasto pelo Senjutsu (senjutsuestado()).
//   jutsuraro  ao concluir, o jutsu jutsu_id entra em users.spells (até lá ele não pode ser usado).
// Os bônus de máximo são SOMADOS direto em maxhp/maxmp/maxtp/maxep/maxnp (o level up, a especialização e os itens
// também só somam diferenças, então nada é perdido nem contado duas vezes); as colunas treino_* guardam quanto veio
// dos treinos. Tudo é gravado numa UPDATE só, condicionada ao users.treinamento lido ("AND treinamento=..."):
// dois cliques ao mesmo tempo contam uma sessão só.

if (!function_exists('treinoslista')) {
function treinoslista() { // Todos os treinos longos (chave => linha), lidos uma vez por página.

	static $lista = null;
	if ($lista === null) {
		$lista = array();
		$q = doquery("SELECT * FROM {{table}} ORDER BY id", "treinos");
		while ($r = mysqli_fetch_array($q)) { $lista[$r["chave"]] = $r; }
	}
	return $lista;

}}

if (!function_exists('treinopelonome')) {
function treinopelonome($nome) { // Linha do treino pelo nome gravado em users.treinamento (ou false).

	foreach (treinoslista() as $tr) { if ($tr["nome"] == $nome) { return $tr; } }
	return false;

}}

if (!function_exists('treinograduacaonumero')) {
function treinograduacaonumero($graduacao) { // Ordem das graduações para comparar requisitos.

	$ordem = array("Estudante da Academia" => 0, "Genin" => 1, "Chuunin" => 2, "Jounin" => 3);
	return isset($ordem[(string)$graduacao]) ? $ordem[(string)$graduacao] : 0;

}}

if (!function_exists('treinonomevila')) {
function treinonomevila($id) { // Nome (traduzido) de uma vila pelo id.

	static $nomes = array();
	$id = intval($id);
	if (!isset($nomes[$id])) {
		$r = mysqli_fetch_array(doquery("SELECT name FROM {{table}} WHERE id='$id' LIMIT 1", "towns"));
		$nomes[$id] = $r ? t($r["name"]) : "?";
	}
	return $nomes[$id];

}}

if (!function_exists('treinodescricao')) {
function treinodescricao($tr) { // Recompensa do treino (texto traduzido, com os valores atuais da tabela).

	$s = intval($tr["bonus_sessao"]); $f = intval($tr["bonus_final"]); $x = intval($tr["bonus_extra"]);
	switch ($tr["chave"]) {
		case "elemento": return tf("+%s de EP máximo a cada sessão (até +%s no total).", $s, $f);
		case "chakra": return tf("+%s de Chakra máximo a cada sessão. Ao concluir: os jutsus custam %s%% menos Chakra (batalha, duelo, PvP e jutsus de menu).", $s, $f);
		case "ferro": return ($x > 0) ? tf("+%s de HP máximo a cada sessão. Ao concluir: +%s de Poder de Defesa (+%s a mais para a especialização Taijutsu).", $s, $f, $x) : tf("+%s de HP máximo a cada sessão. Ao concluir: +%s de Poder de Defesa.", $s, $f);
		case "caminhada": return tf("+%s de TP máximo a cada sessão. Ao concluir: +%s de TP a cada intervalo de descanso (também no mapa).", $s, $f);
		case "meditacao": return tf("+%s de NP máximo a cada sessão. Ao concluir: o Senjutsu ativo gasta 1 NP a cada %s segundos em vez de 3.", $s, 3 + $f);
		case "sharingan_evo": return t("Ao concluir: o Sharingan sobe um estágio (até o 4). O treino pode ser refeito para o estágio seguinte.");
		case "jutsuraro":
			$sp = mysqli_fetch_array(doquery("SELECT name FROM {{table}} WHERE id='".intval($tr["jutsu_id"])."' LIMIT 1", "spells"));
			return tf("Ao concluir: aprende o jutsu exclusivo <b>%s</b> (antes disso ele não pode ser usado).", $sp ? t($sp["name"]) : "?");
	}
	if (strpos((string)$tr["chave"], "olho_") === 0) { // doujutsu (funcoesdoujutsu.php)
		include_once(__DIR__.'/funcoesdoujutsu.php');
		$olho = doujutsuolho(substr($tr["chave"], 5), 1);
		return tf("Ao concluir: desperta o <b>%s</b> (substitui o olho que você tiver). Enquanto ativo: %s; gasta %s.", doujutsunome($olho), implode(", ", doujutsubonustexto($olho)), doujutsucustotexto($olho, isset($GLOBALS["userrow"]["level"]) ? $GLOBALS["userrow"]["level"] : 1));
	}
	return "?";

}}

if (!function_exists('treinorepetivel')) {
function treinorepetivel($tr) { // Treinos de doujutsu saem da lista ao concluir, para poderem ser refeitos (trocar de olho, próximo estágio).

	return (strpos((string)$tr["chave"], "olho_") === 0 || (string)$tr["chave"] == "sharingan_evo");

}}

if (!function_exists('treinorequisitostexto')) {
function treinorequisitostexto($tr) { // Requisitos do treino (texto traduzido).

	$partes = array(tf("Level %s ou mais", intval($tr["nivel_min"])));
	if ($tr["graduacao_min"] != "") { $partes[] = tf("ser ao menos %s", t($tr["graduacao_min"])); }
	if ($tr["requer"] == "senjutsu") { $partes[] = t("ter completado o treino de Senjutsu"); }
	if ($tr["requer"] == "sharingan") { $partes[] = t("estar com o Sharingan (estágio 1 a 3)"); }
	if ($tr["requer"] == "sharingan4") { $partes[] = t("estar com o Sharingan no estágio 4"); }
	return implode(", ", $partes).".";

}}

if (!function_exists('treinoerrorequisito')) {
function treinoerrorequisito($tr, $u) { // "" se o jogador cumpre os requisitos, senão a mensagem (traduzida).

	if (intval($tr["ativo"]) != 1) { return t("Esse treinamento não está disponível no momento."); }
	if (intval($u["level"]) < intval($tr["nivel_min"])) { return tf("Você precisa ter ao menos level %s para fazer esse treinamento.", intval($tr["nivel_min"])); }
	if (treinograduacaonumero($u["graduacao"]) < treinograduacaonumero($tr["graduacao_min"])) { return tf("Você precisa ser ao menos %s para fazer esse treinamento.", t($tr["graduacao_min"])); }
	if ($tr["requer"] == "senjutsu" && (string)$u["senjutsuhtml"] == "") { return t("Você precisa completar o treino de Senjutsu antes de fazer esse treinamento."); }
	$olhoatual = isset($u["doujutsu"]) ? (string)$u["doujutsu"] : ""; $estagio = isset($u["doujutsu_estagio"]) ? intval($u["doujutsu_estagio"]) : 0;
	if ($tr["requer"] == "sharingan" && ($olhoatual != "sharingan" || $estagio >= 4)) { return t("Você precisa estar com o Sharingan (estágio 1 a 3) para fazer esse treinamento."); }
	if ($tr["requer"] == "sharingan4" && ($olhoatual != "sharingan" || $estagio < 4)) { return t("Você precisa estar com o Sharingan no estágio 4 para fazer esse treinamento."); }
	if (strpos((string)$tr["chave"], "olho_") === 0 && $olhoatual == substr($tr["chave"], 5)) { return t("Você já possui esse doujutsu."); }
	return "";

}}

if (!function_exists('treinobonusatual')) {
function treinobonusatual($tr, $u) { // O que o jogador já ganhou com esse treino (texto traduzido, ou "").

	switch ($tr["chave"]) {
		case "elemento": return tf("+%s EP", intval($u["treino_ep"]));
		case "chakra": return tf("+%s CH, -%s%% de Chakra nos jutsus", intval($u["treino_mp"]), intval($u["treino_chakra"]));
		case "ferro": return tf("+%s HP, +%s de Defesa", intval($u["treino_hp"]), intval($u["treino_defesa"]));
		case "caminhada": return tf("+%s TP, +%s TP por descanso", intval($u["treino_tp"]), intval($u["treino_tpregen"]));
		case "meditacao": return tf("+%s NP, +%s s por NP do Senjutsu", intval($u["treino_np"]), intval($u["treino_sjlento"]));
	}
	if (treinorepetivel($tr) && isset($u["doujutsu"]) && (string)$u["doujutsu"] != "") {
		include_once(__DIR__.'/funcoesdoujutsu.php');
		return tf("Olho atual: %s", doujutsunome(doujutsudojogador($u)));
	}
	return "";

}}

if (!function_exists('treinosqlrecompensa')) {
function treinosqlrecompensa($tr, $u, $concluiu) { // Trecho ", campo=..." da recompensa de uma sessão (e da conclusão).

	$s = intval($tr["bonus_sessao"]); $f = intval($tr["bonus_final"]); $sql = "";
	switch ($tr["chave"]) {
		case "elemento":
			$ganho = max(0, min($s, $f - intval($u["treino_ep"]))); // não passa do limite total.
			if ($ganho > 0) { $sql .= ", treino_ep=treino_ep+$ganho, maxep=maxep+$ganho, currentep=currentep+$ganho"; }
			break;
		case "chakra":
			if ($s > 0) { $sql .= ", treino_mp=treino_mp+$s, maxmp=LEAST(32767, maxmp+$s), currentmp=LEAST(32767, currentmp+$s)"; }
			if ($concluiu) { $sql .= ", treino_chakra='".max(0, min(50, $f))."'"; }
			break;
		case "ferro":
			if ($s > 0) { $sql .= ", treino_hp=treino_hp+$s, maxhp=LEAST(32767, maxhp+$s), currenthp=LEAST(32767, currenthp+$s)"; }
			if ($concluiu) {
				$def = $f + ((intval($u["charclass"]) == 2) ? intval($tr["bonus_extra"]) : 0); // 2 = Taijutsu.
				if ($def > 0) { $sql .= ", treino_defesa=treino_defesa+$def, defensepower=LEAST(65535, defensepower+$def)"; }
			}
			break;
		case "caminhada":
			if ($s > 0) { $sql .= ", treino_tp=treino_tp+$s, maxtp=LEAST(32767, maxtp+$s), currenttp=LEAST(32767, currenttp+$s)"; }
			if ($concluiu) { $sql .= ", treino_tpregen='".max(0, min(20, $f))."'"; }
			break;
		case "meditacao":
			if ($s > 0) { $sql .= ", treino_np=treino_np+$s, maxnp=maxnp+$s, currentnp=currentnp+$s"; }
			if ($concluiu) { $sql .= ", treino_sjlento='".max(0, min(30, $f))."'"; }
			break;
		case "sharingan_evo": // só sobe se ainda estiver com o Sharingan (o requisito é conferido de novo a cada sessão).
			if ($concluiu) { $sql .= ", doujutsu_estagio=IF(doujutsu='sharingan', LEAST(4, doujutsu_estagio+1), doujutsu_estagio), doujutsu_abates=IF(doujutsu='sharingan', 0, doujutsu_abates), doujutsu_vitbase=IF(doujutsu='sharingan', duelos_vencidos, doujutsu_vitbase)"; }
			break;
		case "jutsuraro":
			if ($concluiu && intval($tr["jutsu_id"]) > 0) {
				$id = (string)intval($tr["jutsu_id"]);
				$lista = array_filter(explode(",", (string)$u["spells"]), "strlen");
				if (!in_array($id, $lista, true) && strlen((string)$u["spells"]) + strlen($id) + 1 <= 250) { $lista[] = $id; $sql .= ", spells='".addslashes(implode(",", $lista))."'"; }
			}
			break;
	}
	if ($concluiu && strpos((string)$tr["chave"], "olho_") === 0) { // desperta o olho novo, substituindo (e fechando) o atual.
		$sql .= ", doujutsu='".addslashes(substr($tr["chave"], 5))."', doujutsu_estagio='1', doujutsu_ativo='0', doujutsu_timer='0', doujutsu_abates='0', doujutsu_vitbase=duelos_vencidos";
	}
	return $sql;

}}

?>
