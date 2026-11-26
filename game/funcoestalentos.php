<?php // funcoestalentos.php :: Árvore de talentos (talentos.php).
//
// A cada 10 níveis (10..80) o jogador escolhe UM de dois talentos pequenos daquele nível. A escolha é definitiva.
// users.talentos guarda "nivel:opcao,..." (ex.: "10:1,20:2"). Os efeitos entram em classedojogador() (funcoesjutsus.php),
// então valem na luta (fight.php), no duelo (users.php) e nos jutsus (aplicarjutsu) sem mexer em cada lugar:
//   critico / dj_esquiva / dano_jutsu / dano_elemental / dano_fisico / chance_efeito / resist_debuff / economia / ataque / defesa
//   = as mesmas chaves da especialização; tal_ninjutsu / tal_taijutsu = % de dano só nos jutsus tipo 2 / tipo 7;
//   tal_regenmp = CH recuperado a mais por passo (explore.php).
// HP/CH máximo (+5%): somado UMA vez em maxhp/maxmp na mesma transação que grava a escolha (o "AND talentos=<antigo>"
// do UPDATE impede somar duas vezes); a porcentagem é calculada sobre o máximo do momento da escolha.

if (!function_exists('talentoslista')){
function talentoslista(){ // nivel => array(1 => talento, 2 => talento). Textos em pt (t() traduz).
	return array(
		10 => array(1 => array("nome"=>"Olhos de Falcão", "desc"=>"+5% de Chance de Crítico", "mods"=>array("critico"=>5)),
		            2 => array("nome"=>"Pés de Pluma", "desc"=>"+5% de Esquiva", "mods"=>array("dj_esquiva"=>5))),
		20 => array(1 => array("nome"=>"Selos Afiados", "desc"=>"+3% de dano dos jutsus de Ninjutsu", "mods"=>array("tal_ninjutsu"=>3)),
		            2 => array("nome"=>"Punhos Calejados", "desc"=>"+3% de dano dos jutsus de Taijutsu", "mods"=>array("tal_taijutsu"=>3))),
		30 => array(1 => array("nome"=>"Corpo Resistente", "desc"=>"+5% de HP máximo", "mods"=>array(), "maxhp"=>5),
		            2 => array("nome"=>"Reserva Profunda", "desc"=>"+5% de CH máximo", "mods"=>array(), "maxmp"=>5)),
		40 => array(1 => array("nome"=>"Fluxo Contido", "desc"=>"-5% no custo de Chakra dos jutsus", "mods"=>array("economia"=>5)),
		            2 => array("nome"=>"Respiração Ritmada", "desc"=>"+3 de CH recuperado a cada passo no mapa", "mods"=>array("tal_regenmp"=>3))),
		50 => array(1 => array("nome"=>"Mão Precisa", "desc"=>"+5% de chance de aplicar efeitos (atordoar, queimar...)", "mods"=>array("chance_efeito"=>5)),
		            2 => array("nome"=>"Mente Firme", "desc"=>"+5% de resistência aos efeitos de outros ninjas", "mods"=>array("resist_debuff"=>5))),
		60 => array(1 => array("nome"=>"Afinidade Elemental", "desc"=>"+4% de dano dos jutsus com elemento", "mods"=>array("dano_elemental"=>4)),
		            2 => array("nome"=>"Golpe Pesado", "desc"=>"+4% de dano nos ataques físicos", "mods"=>array("dano_fisico"=>4))),
		70 => array(1 => array("nome"=>"Espírito Ofensivo", "desc"=>"+3% de Poder de Ataque", "mods"=>array("ataque"=>3)),
		            2 => array("nome"=>"Guarda Sólida", "desc"=>"+3% de Poder de Defesa", "mods"=>array("defesa"=>3))),
		80 => array(1 => array("nome"=>"Domínio dos Jutsus", "desc"=>"+4% de dano em todos os jutsus de dano", "mods"=>array("dano_jutsu"=>4)),
		            2 => array("nome"=>"Instinto de Sobrevivência", "desc"=>"+3% de Chance de Crítico e +3% de Esquiva", "mods"=>array("critico"=>3, "dj_esquiva"=>3))),
	);
}}

if (!function_exists('talentosescolhidos')){
function talentosescolhidos($row){ // nivel => opcao (só pares válidos de talentoslista()).
	$lista = talentoslista();
	$r = array();
	$texto = (is_array($row) && isset($row["talentos"])) ? (string)$row["talentos"] : "";
	foreach (explode(",", $texto) as $par) {
		$p = explode(":", trim($par));
		if (count($p) != 2) { continue; }
		$n = intval($p[0]); $o = intval($p[1]);
		if (isset($lista[$n][$o]) && !isset($r[$n])) { $r[$n] = $o; }
	}
	return $r;
}}

if (!function_exists('talentosmods')){
function talentosmods($row){ // Soma dos modificadores dos talentos escolhidos.
	$lista = talentoslista();
	$m = array("tal_ninjutsu"=>0, "tal_taijutsu"=>0, "tal_regenmp"=>0);
	foreach (talentosescolhidos($row) as $n => $o) {
		foreach ($lista[$n][$o]["mods"] as $c => $v) { $m[$c] = (isset($m[$c]) ? $m[$c] : 0) + $v; }
	}
	return $m;
}}

if (!function_exists('talentospendentes')){
function talentospendentes($row){ // Níveis de talento já alcançados e ainda sem escolha.
	$feitos = talentosescolhidos($row);
	$r = array();
	foreach (talentoslista() as $n => $ops) { if (intval($row["level"]) >= $n && !isset($feitos[$n])) { $r[] = $n; } }
	return $r;
}}

if (!function_exists('talentoescolher')){
function talentoescolher($id, $nivel, $opcao){ // Grava a escolha (definitiva). Devolve "" ou a chave do erro (pt).
	global $link;
	$lista = talentoslista();
	$nivel = intval($nivel); $opcao = intval($opcao);
	if (!isset($lista[$nivel][$opcao])) { return "Talento inválido."; }
	doquery("START TRANSACTION", "users");
	$u = mysqli_fetch_array(doquery("SELECT id, level, talentos, maxhp, maxmp FROM {{table}} WHERE id='".intval($id)."' LIMIT 1 FOR UPDATE", "users"));
	if (!$u) { doquery("ROLLBACK", "users"); return "Talento inválido."; }
	if (intval($u["level"]) < $nivel) { doquery("ROLLBACK", "users"); return "Você ainda não tem nível para esse talento."; }
	$feitos = talentosescolhidos($u);
	if (isset($feitos[$nivel])) { doquery("ROLLBACK", "users"); return "Você já escolheu o talento desse nível. A escolha é definitiva."; }
	$feitos[$nivel] = $opcao;
	ksort($feitos);
	$partes = array();
	foreach ($feitos as $n => $o) { $partes[] = $n.":".$o; }
	$novo = implode(",", $partes);
	$tal = $lista[$nivel][$opcao];
	$extra = "";
	// LEAST(65535): maxhp/maxmp são smallint unsigned.
	if (!empty($tal["maxhp"])) { $v = (int)ceil(intval($u["maxhp"]) * $tal["maxhp"] / 100); $extra .= ", currenthp=LEAST(65535, currenthp+'$v'), maxhp=LEAST(65535, maxhp+'$v')"; }
	if (!empty($tal["maxmp"])) { $v = (int)ceil(intval($u["maxmp"]) * $tal["maxmp"] / 100); $extra .= ", currentmp=LEAST(65535, currentmp+'$v'), maxmp=LEAST(65535, maxmp+'$v')"; }
	// "AND talentos=<antigo>": um segundo pedido em paralelo (ou repetido) não grava nem soma de novo.
	doquery("UPDATE {{table}} SET talentos='".addslashes($novo)."'$extra WHERE id='".intval($id)."' AND talentos='".addslashes((string)$u["talentos"])."' AND level>='$nivel' LIMIT 1", "users");
	$ok = (mysqli_affected_rows($link) == 1);
	doquery($ok ? "COMMIT" : "ROLLBACK", "users");
	return $ok ? "" : "Você já escolheu o talento desse nível. A escolha é definitiva.";
}}

if (!function_exists('avisotalento')){
function avisotalento($row){ // Aviso do painel quando há talento para escolher (ou "").
	if (!is_array($row) || !isset($row["talentos"])) { return ""; }
	$p = talentospendentes($row);
	if (!count($p)) { return ""; }
	return "<div style=\"margin:4px 6px 2px;padding:4px 6px;background:#613003;border:1px solid #d6aa3c;color:#fff6d8;text-align:center\">".t("Talento disponível!")."<br><a href=\"talentos.php\" style=\"color:#f3d98a\">".t("Escolher talento")." &raquo;</a></div>";
}}
?>
