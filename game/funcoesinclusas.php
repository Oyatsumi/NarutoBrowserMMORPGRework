<?php // funcoesinclusas.php :: Funções auxiliares compartilhadas (tempo de treinos, senjutsu, falas de NPCs, elementos, explicação dos itens e bônus de equipamentos).
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

// Consumíveis vendidos nas lojas das vilas (towns.php, "Consumíveis"): código => nome guardado na mochila, preço e valor.
// Texto na mochila: "nome,código,valor,X". O Incenso Repelente dura INCENSO_PASSOS passos OU INCENSO_MINUTOS minutos
// (o que acabar primeiro); colunas users.repelente_passos e users.repelente_ate (UNIX).
if (!defined('INCENSO_PASSOS')) { define('INCENSO_PASSOS', 100); define('INCENSO_MINUTOS', 15); }
if (!function_exists('consumiveisdaloja')){
function consumiveisdaloja(){ // Lista dos consumíveis da loja: código => array(nome, preço em Ryou, valor).
	return array(
		"ret" => array("Pergaminho de Retorno", 150, 1),
		"rep" => array("Incenso Repelente", 200, INCENSO_PASSOS),
	);
}}

if (!function_exists('consumiveisespeciais')){
function consumiveisespeciais(){ // Consumíveis que NÃO são vendidos nas lojas (só caem de inimigos): código => array(nome, preço base em Ryou, valor).
	// "tel" = Pergaminho de Teleporte Aleatório (drop 426, raríssimo): leva para uma vila conhecida aleatória (backpack.php).
	// O preço base serve para a venda nas lojas (metade dele, como os consumíveis da loja).
	return array("tel" => array("Pergaminho de Teleporte Aleatório", 8000, 1));
}}

if (!function_exists('repelenteativo')){
function repelenteativo($row){ // Passos restantes do Incenso Repelente (0 = sem efeito). Vale enquanto houver passos E o prazo não venceu.
	if (intval($row["repelente_passos"]) <= 0 || intval($row["repelente_ate"]) <= time()) { return 0; }
	return intval($row["repelente_passos"]);
}}

/* ------------------------------------------------------------------------
   Pilhas (itens empilháveis) e itens "não vendáveis".
   Um slot da mochila pode ter um 5º campo com a quantidade: "Incenso Repelente,rep,100,X,5" = 5 incensos.
   Sem o 5º campo a quantidade é 1 (todos os textos antigos continuam valendo). Só itens EXATAMENTE iguais
   (mesmos 4 primeiros campos: nome com variante, id, tipo/valor e durabilidade) se juntam numa pilha.
   Quem pode empilhar e até quanto:
     - arma/colete/bandana (id numérico, tipo 1-3) ... items.empilhavel / items.maxpilha;
     - drops (tipo 4-6), poções de drop ("hp"/"mp", achadas pelo nome + drops.consumivel) e pergaminhos de
       jutsu ("jutsu", achados por drops.ensina_jutsu) ... drops.empilhavel / drops.maxpilha;
     - consumíveis da loja ("ret", "rep") ... control.pilhaconsumiveis (0 ou 1 = não empilha).
   items.naoVendavel / drops.naoVendavel = as lojas e compradores não aceitam o item.
   ------------------------------------------------------------------------ */

if (!function_exists('custotpretorno')){
function custotpretorno($vila = null){ // TP do Pergaminho de Retorno: control.tpretornopct % do TP da viagem até a vila natal, no mínimo control.tpretorno.
	global $controlrow, $userrow;
	$min = isset($controlrow["tpretorno"]) ? intval($controlrow["tpretorno"]) : 5;
	$pct = isset($controlrow["tpretornopct"]) ? intval($controlrow["tpretornopct"]) : 50;
	if ($vila === null && !function_exists('vilanataldoalinhamento')) { include_once('cidadesconteudo.php'); }
	if ($vila === null && is_array($userrow) && isset($userrow["alinhamento"])) { $vila = vilanataldoalinhamento($userrow["alinhamento"]); }
	$tp = is_array($vila) && isset($vila["travelpoints"]) ? intval($vila["travelpoints"]) : 0;
	return max($min, (int)ceil($tp * $pct / 100));
}}

/* Custo do "Viajar Para" (menu de cidades; towns.php :: travelto(), lista do lib.php). Valores em naruto_control (admin):
   distância = ceil(raiz(dlat² + dlon²)) da posição atual até a vila;
   TP base   = towns.travelpoints + viagemtpbase + ceil(distância * viagemtpdist / 100);
   - vila natal: TP = maior entre o TP base e viagemnataltppct % do TP do Pergaminho de Retorno, limitado ao TP máximo
     do jogador (com o TP cheio sempre dá para voltar para casa); Ryou = maior entre TP * viagemryou e
     viagemnatalryoupct % do preço do Pergaminho de Retorno. Sem item.
   - outras vilas: TP = TP base; Ryou = viagemoutraryou + TP * viagemryou; e gasta viagemselos unidade(s) do drop
     viagemseloitem (Selo de Viagem, 436). Tudo é cobrado numa só gravação (travelto). */
if (!function_exists('custoviagem')){
function custoviagem($row, $townrow, $natal = null){ // array(tp, ryou, selos, natal, distancia)
	global $controlrow;
	$c = function($k, $p) use ($controlrow) { return isset($controlrow[$k]) ? max(0, intval($controlrow[$k])) : $p; };
	if ($natal === null) {
		if (!function_exists('vilanataldoalinhamento')) { include_once('cidadesconteudo.php'); }
		$natal = vilanataldoalinhamento(isset($row["alinhamento"]) ? $row["alinhamento"] : "");
	}
	$dlat = intval($townrow["latitude"]) - intval($row["latitude"]); $dlon = intval($townrow["longitude"]) - intval($row["longitude"]);
	$dist = (int)ceil(sqrt($dlat * $dlat + $dlon * $dlon));
	$tp = max(0, intval($townrow["travelpoints"])) + $c("viagemtpbase", 10) + (int)ceil($dist * $c("viagemtpdist", 10) / 100);
	$porTP = $c("viagemryou", 5);
	$ehnatal = is_array($natal) && intval($natal["id"]) == intval($townrow["id"]);
	if ($ehnatal) {
		$tp = max($tp, (int)ceil(custotpretorno($natal) * $c("viagemnataltppct", 300) / 100));
		if (isset($row["maxtp"]) && intval($row["maxtp"]) > 0) { $tp = min($tp, intval($row["maxtp"])); }
		$ret = consumiveisdaloja();
		$ryou = max($tp * $porTP, (int)ceil($ret["ret"][1] * $c("viagemnatalryoupct", 400) / 100));
		$selos = 0;
	} else {
		$ryou = $c("viagemoutraryou", 300) + $tp * $porTP;
		$selos = $c("viagemselos", 1);
	}
	return array("tp" => $tp, "ryou" => $ryou, "selos" => $selos, "natal" => $ehnatal, "distancia" => $dist);
}}

if (!function_exists('textocustoviagemlista')){
function textocustoviagemlista($c){ // Texto do custo na dica da lista "Viajar Para" ("33 TP + 600 Ryou (vila natal)").
	$t = tf("%s TP + %s Ryou", $c["tp"], $c["ryou"]);
	if ($c["selos"] > 0) { $t .= " + ".tf("%s× %s", $c["selos"], t("Selo de Viagem")); }
	if ($c["natal"]) { $t .= " ".t("(vila natal)"); }
	return htmlspecialchars($t, ENT_QUOTES);
}}

if (!function_exists('iddoselo')){
function iddoselo(){ // Id do drop "Selo de Viagem" (control.viagemseloitem).
	global $controlrow;
	return isset($controlrow["viagemseloitem"]) ? intval($controlrow["viagemseloitem"]) : 436;
}}

if (!function_exists('contarselos')){
function contarselos($row){ // Unidades do Selo de Viagem na mochila (bp1-bp8; drop "nome,ID,4-6,X[,qtd]").
	$id = iddoselo(); $n = 0;
	for ($i = 1; $i <= 8; $i++) {
		$p = explode(",", (string)(isset($row["bp".$i]) ? $row["bp".$i] : "None"));
		if (isset($p[2]) && $p[1] === (string)$id && intval($p[2]) >= 4 && intval($p[2]) <= 6) { $n += qtditem($row["bp".$i]); }
	}
	return $n;
}}

if (!function_exists('custotpteleporte')){
function custotpteleporte(){ // TP do Pergaminho de Teleporte Aleatório (control.tpteleporte).
	global $controlrow;
	return isset($controlrow["tpteleporte"]) ? intval($controlrow["tpteleporte"]) : 30;
}}

if (!function_exists('qtditem')){
function qtditem($texto){ // Quantidade de um slot ("None" = 0; sem 5º campo = 1).
	if ($texto == "None" || $texto == "") { return 0; }
	$p = explode(",", (string)$texto);
	return (isset($p[4]) && intval($p[4]) > 1) ? intval($p[4]) : 1;
}}

if (!function_exists('itemsemqtd')){
function itemsemqtd($texto){ // Os 4 campos que identificam o item (sem a quantidade).
	return implode(",", array_slice(array_pad(explode(",", (string)$texto), 4, "X"), 0, 4));
}}

if (!function_exists('itemcomqtd')){
function itemcomqtd($texto, $qtd){ // Texto do slot com a quantidade (1 = sem o 5º campo, como sempre foi; 0 = "None").
	$qtd = intval($qtd);
	if ($qtd <= 0) { return "None"; }
	return itemsemqtd($texto).($qtd > 1 ? ",".$qtd : "");
}}

if (!function_exists('marcaitem')){
function marcaitem($texto){ // Marca curta do item de um espaço (sem a quantidade), usada no link da mochila (backpack.php?qual=N&v=marca):
	// um segundo clique (duplo clique) não usa o item que entrou no lugar do primeiro.
	return substr(md5(itemsemqtd($texto)), 0, 8);
}}

if (!function_exists('linhadoitem')){
function linhadoitem($texto, $linha = null){ // Linha de items/drops que define pilha e venda de um slot (ou false). Cache por requisição.
	// $linha (opcional): linha já lida por quem chama (ex.: lista da loja com IN()); só guarda no cache, sem query.
	static $cache = array();
	$p = array_pad(explode(",", (string)$texto), 4, "X");
	$chave = $p[0]."|".$p[1]."|".$p[2];
	if (is_array($linha)) { $cache[$chave] = array_filter($linha, 'is_string', ARRAY_FILTER_USE_KEY); return $cache[$chave]; }
	if (array_key_exists($chave, $cache)) { return $cache[$chave]; }
	$row = false;
	if (is_numeric($p[1])) {
		if (intval($p[1]) <= 0) { $cache[$chave] = false; return false; } // espaço vazio (id 0): não existe linha com id <= 0, sem query.
		$tabela = (intval($p[2]) >= 1 && intval($p[2]) <= 3) ? "items" : "drops";
		$row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($p[1])."' LIMIT 1", $tabela), MYSQLI_ASSOC);
	} elseif ($p[1] == "bomba" || $p[1] == "descanso" || $p[1] == "banco") { // ferramenta ninja, descanso e expansão do banco: o 3º campo é o id do drop.
		$row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($p[2])."' LIMIT 1", "drops"), MYSQLI_ASSOC);
	} elseif ($p[1] == "jutsu") {
		$row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE ensina_jutsu='".intval($p[2])."' ORDER BY id LIMIT 1", "drops"), MYSQLI_ASSOC);
	} elseif (in_array($p[1], array("hp", "mp", "tp", "np", "ep", "hm", "hmt"), true)) { // poções (tp/np/ep: poções pequenas 433-435)
		$nome = separarvariante($p[0])[0];
		$row = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE name='".addslashes($nome)."' AND consumivel='".addslashes($p[1].",".intval($p[2]))."' LIMIT 1", "drops"), MYSQLI_ASSOC);
	}
	$cache[$chave] = $row ? $row : false;
	return $cache[$chave];
}}

if (!function_exists('pilhamaxima')){
function pilhamaxima($texto){ // Máximo de unidades numa pilha deste item (1 = não empilha).
	global $controlrow;
	if ($texto == "None" || $texto == "") { return 1; }
	$p = array_pad(explode(",", (string)$texto), 4, "X");
	if (isset(consumiveisdaloja()[$p[1]]) || isset(consumiveisespeciais()[$p[1]])) { return max(1, isset($controlrow["pilhaconsumiveis"]) ? intval($controlrow["pilhaconsumiveis"]) : 20); }
	$row = linhadoitem($texto);
	if (!$row || empty($row["empilhavel"])) { return 1; }
	return max(1, intval($row["maxpilha"]));
}}

if (!function_exists('itemnaovendavel')){
function itemnaovendavel($texto){ // true se o item tem a marca "não pode ser vendido em loja" (items/drops.naoVendavel).
	if ($texto == "None" || $texto == "") { return false; }
	$row = linhadoitem($texto);
	return ($row && !empty($row["naoVendavel"]));
}}

if (!function_exists('mochilaguardar')){
function mochilaguardar(&$row, $texto, $limite = 8){ // Põe o item (com a quantidade dele) na mochila de $row: primeiro completa
	// pilhas do mesmo item (até o máximo), depois usa espaços livres. Devolve os espaços alterados ("bp3",...) ou
	// false se não couber tudo (nesse caso $row não muda). Quem chama grava com o jogador travado (FOR UPDATE).
	if ($texto == "None" || $texto == "") { return array(); }
	$falta = qtditem($texto);
	$max = pilhamaxima($texto);
	$base = itemsemqtd($texto);
	$novo = $row; $mudou = array();
	if ($max > 1) {
		for ($i = 1; $i <= $limite && $falta > 0; $i++) {
			$s = (string)$novo["bp".$i];
			if ($s == "None" || itemsemqtd($s) !== $base) { continue; }
			$cabe = $max - qtditem($s);
			if ($cabe <= 0) { continue; }
			$poe = min($cabe, $falta);
			$novo["bp".$i] = itemcomqtd($base, qtditem($s) + $poe); $falta -= $poe; $mudou[] = "bp".$i;
		}
	}
	for ($i = 1; $i <= $limite && $falta > 0; $i++) {
		if ($novo["bp".$i] != "None") { continue; }
		$poe = min($max, $falta);
		$novo["bp".$i] = itemcomqtd($base, $poe); $falta -= $poe; $mudou[] = "bp".$i;
	}
	if ($falta > 0) { return false; }
	$row = $novo;
	return array_values(array_unique($mudou));
}}

if (!function_exists('sqlmochila')){
function sqlmochila($row, $antigo, $espacos){ // Trechos SET e WHERE para gravar os espaços alterados conferindo que não mudaram desde a leitura.
	$set = array(); $onde = "";
	foreach ($espacos as $bp) {
		if (!preg_match('/^bp[1-8]$/', $bp)) { continue; }
		$set[] = "$bp='".addslashes((string)$row[$bp])."'";
		$onde .= " AND $bp='".addslashes((string)$antigo[$bp])."'";
	}
	return array(implode(",", $set), $onde);
}}

if (!function_exists('iconecomqtd')){
function iconecomqtd($html, $texto){ // Envolve o <img> 34x34 do item e põe a quantidade da pilha no canto inferior direito.
	$q = qtditem($texto);
	if ($q <= 1) { return $html; }
	return "<span style=\"position:relative;display:inline-block;line-height:0\">".$html."<span class=\"qtdpilha\" style=\"position:absolute;right:1px;bottom:1px;font:bold 10px/10px Verdana,Arial,sans-serif;color:#fff;text-shadow:1px 1px 0 #000,-1px -1px 0 #000,1px -1px 0 #000,-1px 1px 0 #000;pointer-events:none\">".$q."</span></span>";
}}

// Este arquivo é incluído por várias páginas (às vezes mais de uma vez na mesma requisição),
// por isso cada função fica protegida por function_exists() para não ser declarada duas vezes.


/* ------------------------------------------------------------------------
   Mochila e itens.
   Formato de um slot da mochila (bp1..bp8): "nome,id,tipo,durabilidade" (+ ",quantidade" opcional nas pilhas)
     - tipo 1 = arma, 2 = colete, 3 = bandana, 4 a 6 = drops (slots de orbe);
     - id não numérico indica item consumível ("hp" = poção de vida, "mp" = poção de chakra etc.);
     - consumíveis das lojas: "Pergaminho de Retorno,ret,1,X" (volta para a vila natal) e
       "Incenso Repelente,rep,100,X" (sem inimigos aleatórios por 100 passos ou 15 minutos);
     - durabilidade "X" = infinita.
   ------------------------------------------------------------------------ */

if (!function_exists('iconeitemmochila')){
function iconeitemmochila($array, &$img, &$dur){ // Escolhe o ícone (images/*.gif) e o texto de durabilidade de um item da mochila.
	$array = array_pad($array, 4, "X");
	$img = "orb_img";
	if ($array[2] == 1) {$img = "icon_weapon";}
	if ($array[2] == 2) {$img = "icon_armor";}
	if ($array[2] == 3) {$img = "icon_shield";}
	if (is_numeric($array[2]) && ($array[2] > 3) && ($array[2] < 7)) {$img = "orb";}
	if ($array[1] == "mp") {$img = "potion";}
	if ($array[1] == "hp") {$img = "potion";}
	if ($array[1] == "bp") {$img = "backpack_pequena";}
	if ($array[1] == "dia") {$img = "diamond";}
	if ($array[1] == "per") {$img = "parchment";}
	if ($array[1] == "jutsu") {$img = "parchment";} // pergaminho que ensina um jutsu ("nome,jutsu,ID do jutsu,1").
	if ($array[1] == "hm") {$img = "potion";}
	if ($array[1] == "hmt") {$img = "potion";}
	if ($array[1] == "tp") {$img = "potion";}
	if ($array[1] == "np") {$img = "potion";}
	if ($array[1] == "ep") {$img = "potion";}
	if ($array[1] == "bk") {$img = "book";}
	if ($array[1] == "ret") {$img = "parchment";} // Pergaminho de Retorno.
	if ($array[1] == "rep") {$img = "potion";}    // Incenso Repelente.
	if ($array[1] == "tel") {$img = "parchment";} // Pergaminho de Teleporte Aleatório.
	if ($array[3] == "X") {$dur = "INF";}else{$dur = $array[3];}
}}


/* ------------------------------------------------------------------------
   Contagem de tempo dos treinos, missões, jutsu de busca e senjutsu.
   As datas são gravadas no banco como texto: data "d/m/aaaa" e hora "hh:mm:ss".
   A versão original fazia a conta "na mão" considerando todo mês com 30 dias, o que errava
   o tempo na virada de meses de 31 dias (ex.: 31/01 23h -> 01/02 01h dava tempo negativo).
   Agora usamos mktime(), mantendo os mesmos formatos de entrada e de retorno.
   ------------------------------------------------------------------------ */

if (!function_exists('segundosdesde')){
function segundosdesde($data, $hora, $zerarsegundos = false){ // Segundos passados desde "d/m/aaaa" + "hh:mm:ss" (false se a data for inválida).
	$d = explode("/", (string)$data);
	$h = array_pad(explode(":", (string)$hora), 3, 0);
	if (count($d) < 3 || !is_numeric($d[0]) || !is_numeric($d[1]) || !is_numeric($d[2])) { return false; }
	$segundo = ($zerarsegundos) ? 0 : intval($h[2]);
	$inicio = mktime(intval($h[0]), intval($h[1]), $segundo, intval($d[1]), intval($d[0]), intval($d[2]));
	if ($inicio === false) { return false; }
	$agora = time();
	if ($zerarsegundos) { $agora -= intval(date("s", $agora)); }
	return $agora - $inicio;
}}

if (!function_exists('tempojutsu')){
function tempojutsu($data, $hora, $tempoprapassar){ // Retorna "ok" se já passaram $tempoprapassar MINUTOS desde data/hora; senão, quantos minutos ainda faltam.
	// Obs.: o comentário original dizia "segundos", mas a conta sempre foi feita em minutos (os segundos são ignorados).
	$tempoprapassar = intval($tempoprapassar);
	$segundos = segundosdesde($data, $hora, true);
	$quantosmin = ($segundos === false) ? 0 : intdiv($segundos, 60);
	if ($quantosmin >= $tempoprapassar) { return "ok"; }
	return $tempoprapassar - $quantosmin;
}}

if (!function_exists('tempopassarsg')){
function tempopassarsg($data, $hora, $tempoprapassar){ // Versão em segundos. Retorna "faltam-passados": "0-passados" quando o tempo já passou.
	$tempoprapassar = intval($tempoprapassar);
	$quantosseg = segundosdesde($data, $hora);
	if ($quantosseg === false) { $quantosseg = 0; }
	if ($quantosseg >= $tempoprapassar) { return "0-".$quantosseg; }
	return ($tempoprapassar - $quantosseg)."-".$quantosseg;
}}


/* ------------------------------------------------------------------------
   Senjutsu (modo sábio): enquanto o "olho" está aberto, gasta 1 NP a cada 3 segundos.
   senjutsutimer = "d/m/aaaa,hh:mm:ss,1" (momento até onde o NP já foi cobrado)
   O bônus NÃO é mais somado em attackpower/defensepower: ele é calculado na hora do combate
   (classeataquedefesa()/classedojogador() em funcoesjutsus.php) a partir de senjutsuativo(), que desconta o NP
   do tempo passado mesmo sem a página ter sido atualizada. Assim o bônus some exatamente quando o NP acaba
   (também para o adversário de um duelo/PvP) e trocar de equipamento com o modo ativo não deixa sobras.
   atkdefsenjutsu = "ataque,defesa" só existe para contas antigas que ativaram antes desta regra (é devolvido ao desativar).
   ------------------------------------------------------------------------ */

if (!function_exists('senjutsuestado')){
function senjutsuestado($row){ // array("ativo"=>bool, "np"=>NP atual já descontado, "cobrado"=>NP a descontar, "segundos"=>segundos passados) sem gravar nada.
	$r = array("ativo" => false, "np" => max(0, intval($row["currentnp"])), "cobrado" => 0, "segundos" => 0, "passo" => 3);
	if (!isset($row["senjutsuhtml"]) || $row["senjutsuhtml"] != "senjutsu") { return $r; }
	$cont = array_pad(explode(",", (string)$row["senjutsutimer"]), 3, "");
	$seg = segundosdesde($cont[0], $cont[1]);
	$seg = ($seg === false) ? 0 : max(0, $seg);
	$r["segundos"] = $seg;
	$r["passo"] = 3 + (isset($row["treino_sjlento"]) ? max(0, intval($row["treino_sjlento"])) : 0); // segundos por NP (Meditação Natural soma treino_sjlento).
	$r["cobrado"] = min($r["np"], intdiv($seg, $r["passo"]));
	$r["np"] -= $r["cobrado"];
	$r["ativo"] = ($r["np"] > 0);
	return $r;
}}

if (!function_exists('senjutsuativo')){
function senjutsuativo($row){ // O modo sábio está valendo agora? (olho aberto e ainda há NP depois de descontar o tempo passado)
	$e = senjutsuestado($row);
	return $e["ativo"];
}}

if (!function_exists('senjutsubonustexto')){
function senjutsubonustexto($classe, $nivel = 50){ // Lista (traduzida) do bônus do Senjutsu ativo para a especialização (janela de ativação, ajuda e tooltips).
	$m0 = classemods($classe, false); $m = classemods($classe, true, $nivel);
	$rotulos = array("ataque"=>"+%s%% de Poder de Ataque", "defesa"=>"+%s%% de Poder de Defesa", "dano_fisico"=>"+%s%% de dano físico",
		"critico"=>"+%s%% de Chance de Crítico", "dano_elemental"=>"+%s%% de dano dos jutsus com elemento", "economia"=>"-%s%% de Chakra em todos os jutsus",
		"chance_efeito"=>"+%s%% de chance dos efeitos no inimigo", "duracao_efeito"=>"+%s turno(s) de duração dos efeitos no inimigo", "resist_debuff"=>"+%s%% de resistência aos efeitos dos inimigos");
	$linhas = array();
	foreach ($rotulos as $c => $texto) { $d = intval($m[$c]) - intval($m0[$c]); if ($d > 0) { $linhas[] = tf($texto, $d); } }
	return $linhas;
}}

if (!function_exists('senjutsu')){
function senjutsu(){ // Cobra o NP do senjutsu ativo (o tempo continua contando entre as páginas) e fecha o olho quando o NP chega a zero.
	global $userrow;
	if ($userrow["senjutsuhtml"] != "senjutsu") { return; } // não possui ou está desativado.
	$e = senjutsuestado($userrow);
	if ($e["cobrado"] <= 0 && $e["ativo"]) { return; } // nada a cobrar ainda.
	$userrow["currentnp"] = $e["np"];
	if (!$e["ativo"]) { // acabou o NP: fecha o olho (e devolve o bônus antigo gravado, se houver).
		$retir = array_pad(explode(",", (string)$userrow["atkdefsenjutsu"]), 2, 0);
		$userrow["attackpower"] = max(0, intval($userrow["attackpower"]) - intval($retir[0]));
		$userrow["defensepower"] = max(0, intval($userrow["defensepower"]) - intval($retir[1]));
		$userrow["senjutsuhtml"] = "fechado";
		$userrow["senjutsutimer"] = "None";
		$userrow["atkdefsenjutsu"] = "0,0";
	} else { // avança o relógio só pelos NP cobrados (os segundos que sobram continuam valendo).
		$momento = time() - $e["segundos"] + $e["passo"] * $e["cobrado"];
		$userrow["senjutsutimer"] = date("j/n/Y", $momento).",".date("H:i:s", $momento).",1";
	}
	// "AND senjutsuhtml='senjutsu'": se outra página já fechou o olho, não cobra de novo.
	doquery("UPDATE {{table}} SET atkdefsenjutsu='".addslashes($userrow["atkdefsenjutsu"])."',attackpower='".intval($userrow["attackpower"])."',defensepower='".intval($userrow["defensepower"])."',senjutsuhtml='".addslashes($userrow["senjutsuhtml"])."',senjutsutimer='".addslashes($userrow["senjutsutimer"])."',currentnp=GREATEST(0, currentnp-".intval($e["cobrado"]).") WHERE id='".intval($userrow["id"])."' AND senjutsuhtml='senjutsu' LIMIT 1","users");
}}


/* ------------------------------------------------------------------------
   Falas de personagens (NPCs): balão com a imagem do personagem.
   ------------------------------------------------------------------------ */

if (!function_exists('personagemmissao')){
function personagemmissao($cont, $townrow){ // Fala do Kage da vila e em seguida mostra a tela de treinamentos/missões.
	global $userrow, $valorlib;
	$valorlib = 2; // avisa o treinamentoequests.php para não incluir o lib.php de novo.
	$cont = personagemgeral($cont, $townrow["id"], t($townrow["kage"])); // mesmo pergaminho dos outros NPCs.
	include('treinamentoequests.php');
	treinamento($cont);
	die();
}}

if (!function_exists('personagemgeral')){
function personagemgeral($cont, $avatar="1.png", $avatarnome="NPC", $sempular=""){ // Devolve o HTML do balão de fala de um NPC qualquer.
	global $valorlib;
	$valorlib = 2; // mesmo aviso usado em personagemmissao() (algumas páginas incluem o treinamentoequests.php depois).
	if ($sempular == ""){$sempular = "<br>";}else{$sempular = "";}
	// Uma só caixa com três fundos (topo, base e meio repetido): sem emendas entre linhas de tabela, que no Safari
	// com zoom (iPad) apareciam como uma linha horizontal de 1px.
	$cont = "<div class=\"falanpc\" style=\"width:462px;margin:0 auto;box-sizing:border-box;padding:20px 0;background:url(layoutnovo/personagem/cima.jpg) left top no-repeat,url(layoutnovo/personagem/baixo.jpg) left bottom no-repeat,url(layoutnovo/personagem/meio.jpg) left top repeat-y;background-clip:border-box,border-box,content-box;background-origin:padding-box,padding-box,content-box\"><table width=\"100%\"><tr><td width=\"50\"></td><td width=\"*\"><img src=\"layoutnovo/personagem/".$avatar.".png\" align=\"left\"><b>".tf("%s diz:", $avatarnome)."</b><br>
".$cont."
</td><td width=\"8\"></td></tr></table></div>$sempular
";
	return $cont;
}}


/* ------------------------------------------------------------------------
   Elementos: ciclo de vantagens (cada elemento vence o próximo e perde para o anterior)
       água > fogo > vento > raio > terra > água
   Vantagem = dano x 1,5; desvantagem = dano x 0,5; mesmo elemento = resistência (control.elemento_resiste %, padrão 75);
   demais combinações e qualquer lado "neutro" = x 1.
   Jogadores têm até dois elementos (users.elemento = "principal,secundário"; elemento.php). Contra um alvo com dois
   elementos o multiplicador combina os dois: o do principal inteiro e o do secundário com peso control.elemento_peso2 %
   (elementomultcombinado()). Monstros têm um elemento só.
   Cada elemento está ligado a um atributo de distribuição (ver outroseatributos.php):
       água = Sorte, fogo = Determinação, vento = Precisão, raio = Agilidade, terra = Inteligência.
   ------------------------------------------------------------------------ */

if (!function_exists('elementoganhador')){
function elementoganhador($primeiro, $segundo, &$pontosdeelemento){ // Multiplicador de dano do elemento $primeiro (atacante) contra $segundo (defensor).
	// $pontosdeelemento: se quem chamou passou uma variável com valor (ex.: 0), ela recebe o bônus em %
	// do atributo ligado ao elemento do atacante (1% a cada 20 pontos). Se passou uma variável
	// inexistente/null (turno do inimigo), o bônus não é calculado - o atributo é do jogador, não do monstro.
	global $userrow;

	if (($primeiro == "agua") && ($segundo == "fogo")){$win = 3/2; $mult = $userrow['sorte'];}
	elseif (($primeiro == "fogo") && ($segundo == "agua")){$win = 1/2; $mult = $userrow['determinacao'];}
	elseif (($primeiro == "fogo") && ($segundo == "vento")){$win = 3/2; $mult = $userrow['determinacao'];}
	elseif (($primeiro == "vento") && ($segundo == "fogo")){$win = 1/2; $mult = $userrow['precisao'];}
	elseif (($primeiro == "vento") && ($segundo == "raio")){$win = 3/2; $mult = $userrow['precisao'];}
	elseif (($primeiro == "raio") && ($segundo == "vento")){$win = 1/2; $mult = $userrow['agilidade'];}
	elseif (($primeiro == "raio") && ($segundo == "terra")){$win = 3/2; $mult = $userrow['agilidade'];}
	elseif (($primeiro == "terra") && ($segundo == "raio")){$win = 1/2; $mult = $userrow['inteligencia'];}
	elseif (($primeiro == "terra") && ($segundo == "agua")){$win = 3/2; $mult = $userrow['inteligencia'];}
	elseif (($primeiro == "agua") && ($segundo == "terra")){$win = 1/2; $mult = $userrow['sorte'];}
	elseif ($primeiro == $segundo && $primeiro != "" && $primeiro != "neutro") { // mesmo elemento: o alvo resiste.
		global $controlrow;
		$win = max(0, isset($controlrow["elemento_resiste"]) ? intval($controlrow["elemento_resiste"]) : 75) / 100;
		$atrib = array("agua"=>"sorte", "fogo"=>"determinacao", "vento"=>"precisao", "raio"=>"agilidade", "terra"=>"inteligencia");
		$mult = isset($atrib[$primeiro], $userrow[$atrib[$primeiro]]) ? $userrow[$atrib[$primeiro]] : 0;
	}
	else{$win = 1; $mult = 0;}

	// No PHP 8 a comparação antiga ($pontosdeelemento != "") passou a ser verdadeira para 0, e no PHP 5/7
	// era falsa - por isso o teste agora é explícito: só calcula quando recebeu um valor (não null).
	if ($pontosdeelemento !== null){ $pontosdeelemento = ceil(intval($mult)/20); }

	return $win;
}}

if (!function_exists('elementosdo')){
function elementosdo($texto){ // "fogo,vento" / "fogo" / "" -> array(principal, secundário) com "neutro" onde não houver.
	$validos = array("fogo", "agua", "vento", "raio", "terra");
	$p = array_map('trim', explode(",", (string)$texto));
	$a = (isset($p[0]) && in_array($p[0], $validos, true)) ? $p[0] : "neutro";
	$b = (isset($p[1]) && in_array($p[1], $validos, true) && $p[1] != $a) ? $p[1] : "neutro";
	if ($a == "neutro") { $b = "neutro"; }
	return array($a, $b);
}}

if (!function_exists('elementomultcombinado')){
function elementomultcombinado($elataque, $alvo, &$pontosdeelemento){ // Multiplicador do elemento $elataque contra o alvo (string "a,b" ou array).
	// Principal do alvo conta inteiro; o secundário com peso control.elemento_peso2 % sobre o desvio (x1,5 -> +25% com peso 50).
	// Ex.: Água contra Fogo+Vento = 1,5 x (1 + 0) = 1,5; Água contra Vento+Fogo = 1 x (1 + 0,5 x 0,5) = 1,25.
	global $controlrow;
	$els = is_array($alvo) ? array_pad(array_values($alvo), 2, "neutro") : elementosdo($alvo);
	$m1 = elementoganhador((string)$elataque, (string)$els[0], $pontosdeelemento);
	if ((string)$els[1] == "" || $els[1] == "neutro") { return $m1; }
	$nada = null;
	$m2 = elementoganhador((string)$elataque, (string)$els[1], $nada);
	$peso = max(0, min(100, isset($controlrow["elemento_peso2"]) ? intval($controlrow["elemento_peso2"]) : 50)) / 100;
	return $m1 * (1 + ($m2 - 1) * $peso);
}}

if (!function_exists('elementoatributos')){
function elementoatributos($row){ // Pontos do atributo ligado a cada elemento (água = Sorte, fogo = Determinação, vento = Precisão, raio = Agilidade, terra = Inteligência).
	$m = array("agua"=>"sorte", "fogo"=>"determinacao", "vento"=>"precisao", "raio"=>"agilidade", "terra"=>"inteligencia");
	$r = array();
	foreach ($m as $el => $c) { $r[$el] = max(0, intval(is_array($row) && isset($row[$c]) ? $row[$c] : 0)); }
	return $r;
}}

if (!function_exists('descansomult')){
function descansomult($row){ // Multiplicador ativo do Descansar (itens de descanso), em %: 0 = nenhum.
	return (isset($row["descanso_ate"]) && intval($row["descanso_ate"]) > time()) ? intval($row["descanso_mult"]) : 0;
}
function descansomulttexto($pct){ return rtrim(rtrim(number_format(intval($pct) / 100, 2, ",", ""), "0"), ","); } // 150 -> "1,5"
}

if (!function_exists('elementofrase')){
function elementofrase($atk, $def, $mult){ // Explica o efeito do elemento no dano (i18n neutro; "" se não mudou nada).
	$n = array("neutro"=>"Neutro", "fogo"=>"Fogo", "agua"=>"Água", "vento"=>"Vento", "raio"=>"Raio", "terra"=>"Terra");
	$a = isset($n[$atk]) ? $n[$atk] : "Neutro"; $d = isset($n[$def]) ? $n[$def] : "Neutro";
	$pct = (int)round(abs($mult - 1) * 100);
	if ($pct == 0) { return ""; }
	if ($mult > 1) { return i18n("%s vence %s: +%s%% de dano.", $a, $d, $pct); }
	if ($atk == $def) { return i18n("%s resiste a %s: -%s%% de dano.", $a, $d, $pct); }
	return i18n("%s perde para %s: -%s%% de dano.", $a, $d, $pct);
}}

if (!function_exists('elementonome')){
function elementonome($el){ // Nome traduzido do elemento.
	$n = array("neutro"=>"Neutro", "fogo"=>"Fogo", "agua"=>"Água", "vento"=>"Vento", "raio"=>"Raio", "terra"=>"Terra");
	return t(isset($n[$el]) ? $n[$el] : "Neutro");
}}


/* ------------------------------------------------------------------------
   Variantes raras dos itens ("shiny"): a mesma arma/colete/bandana/drop com uma skin especial e um
   bônus pequeno a mais. A variante viaja DENTRO do nome do item, como sufixo ":efeito"
   (ex.: "Kunai:dourado", "Kunai+2:chama"). Assim ela anda junto em todo lugar onde o nome já anda
   (mochila "nome,id,tipo,dur", banco, trocas, weaponname/slot1name...) sem colunas novas, e itens
   antigos (sem ":") continuam iguais.
       efeito   bônus extra                                        ícone
       dourado  +18% do atributo principal (mín. +1)               variantes/dourado/<id>.gif
       brilho   +3% Chance de Crítico e +8% do atributo principal  variantes/brilho/...
       raio     +3% Esquiva e +10% do atributo principal           variantes/raio/...
       chama    +10 Poder de Ataque e +12% do atributo principal   variantes/chama/...
       gelo     +10 Poder de Defesa e +12% do atributo principal   variantes/gelo/...
       sombra   +5% Economia de Chakra e +10% do atributo principal variantes/sombra/...
   (valores padrão de variantesrecomendadas(); o administrador pode mudar em admin.php?do=variantes)
   Atributo principal: ataque (arma) ou defesa (colete/bandana) do item, já com o refino "+N";
   nos drops, o attribute1 (se for positivo).
   ------------------------------------------------------------------------ */

if (!function_exists('variantesitens')){
function variantesitens($somenteativas = false, $recarregar = false){ // Variantes raras lidas da tabela variantes (editável em admin.php?do=variantes): efeito => rótulo (pt), cor, bônus fixo ("" = só % do atributo principal), percentual, % extra (somado ao bônus fixo), peso no sorteio e ativo.
	// Variantes desativadas continuam reconhecidas nos itens que já existem (só não são mais sorteadas). $recarregar: relê a tabela (variantesreaplicar()).
	static $cache = null;
	if ($cache === null || $recarregar) {
		$cache = array();
		$q = doquery("SELECT * FROM {{table}} ORDER BY ordem, efeito", "variantes");
		while ($q && $r = mysqli_fetch_array($q)) {
			$cache[$r["efeito"]] = array("rotulo"=>$r["nome"], "cor"=>$r["cor"], "bonus"=>$r["bonus"], "percentual"=>intval($r["percentual"]), "pct_extra"=>intval(isset($r["pct_extra"]) ? $r["pct_extra"] : 0), "peso"=>intval($r["peso"]), "ativo"=>intval($r["ativo"]));
		}
	}
	if (!$somenteativas) { return $cache; }
	return array_filter($cache, function($v) { return $v["ativo"] == 1 && $v["peso"] > 0; });
}}

if (!function_exists('separarvariante')){
function separarvariante($nome){ // "Kunai+2:chama" -> array("Kunai+2", "chama"). Sem variante -> array($nome, "").
	$nome = (string)$nome;
	$pos = strrpos($nome, ":");
	if ($pos !== false) {
		$efeito = substr($nome, $pos + 1);
		if (isset(variantesitens()[$efeito])) { return array(substr($nome, 0, $pos), $efeito); }
	}
	return array($nome, "");
}}

if (!function_exists('variantedoitem')){
function variantedoitem($nome){ // Só o efeito da variante do nome ("" = item comum).
	$v = separarvariante($nome);
	return $v[1];
}}

if (!function_exists('nomecomvariante')){
function nomecomvariante($nome, $efeito){ // Grava a variante no nome ("Kunai" + "gelo" -> "Kunai:gelo"). Efeito inválido/vazio -> nome comum.
	$v = separarvariante($nome);
	return isset(variantesitens()[$efeito]) ? $v[0].":".$efeito : $v[0];
}}

if (!function_exists('sortearvariante')){
function sortearvariante($chance){ // Sorteia se um drop sai como variante ($chance em %, 0,01 de precisão). Devolve o efeito ou "".
	$chance = min(10000, max(0, (int)round(floatval($chance) * 100)));
	if ($chance <= 0 || rand(1, 10000) > $chance) { return ""; }
	// Sorteio ponderado entre as variantes ativas (coluna peso).
	$ativas = variantesitens(true);
	$total = 0; foreach ($ativas as $v) { $total += $v["peso"]; }
	if ($total <= 0) { return ""; }
	$sorteio = rand(1, $total);
	foreach ($ativas as $efeito => $v) { $sorteio -= $v["peso"]; if ($sorteio <= 0) { return $efeito; } }
	return "";
}}

if (!function_exists('bonusvariante')){
function bonusvariante($nomeitem, $linha, $tabela){ // Bônus extras ("campo,valor") da variante do item. $linha = linha do item em "items" ou "drops".
	$efeito = variantedoitem($nomeitem);
	if ($efeito == "" || !$linha) { return array(); }
	$tab = variantesitens();
	// Com bônus fixo (ex.: Flamejante "attackpower,10"): o fixo + pct_extra% do atributo principal (0 = só o fixo).
	// Sem bônus fixo (ex.: Dourado): +percentual% do atributo principal (arredondado, mínimo +1).
	if ($tab[$efeito]["bonus"] != "") {
		$extra = intval($tab[$efeito]["pct_extra"]);
		return array_merge(array($tab[$efeito]["bonus"]), $extra > 0 ? bonusvariantepct($nomeitem, $linha, $tabela, $extra / 100) : array());
	}
	return bonusvariantepct($nomeitem, $linha, $tabela, max(0, intval($tab[$efeito]["percentual"])) / 100);
}}

if (!function_exists('bonusvariantepct')){
function bonusvariantepct($nomeitem, $linha, $tabela, $pct){ // "+X% do atributo principal" de uma variante (arma/colete/bandana: ataque/defesa com o refino; drop: o 1º atributo, se for positivo).
	if ($tabela == "items") {
		$melhoria = explode("+", separarvariante($nomeitem)[0]);
		$base = intval($linha["attribute"]) + (isset($melhoria[1]) ? intval($melhoria[1]) * 20 : 0);
		$campo = ($linha["type"] == 1) ? "attackpower" : "defensepower";
		return ($base > 0 && $pct > 0) ? array($campo.",".max(1, (int)round($base * $pct))) : array();
	}
	$attr = array_pad(explode(",", (string)$linha["attribute1"]), 2, 0);
	if ($pct > 0 && intval($attr[1]) > 0 && atributovalido(trim($attr[0]))) { return array(trim($attr[0]).",".max(1, (int)round(intval($attr[1]) * $pct))); }
	return array();
}}

if (!function_exists('nomeitemhtml')){
function nomeitemhtml($nome){ // Nome traduzido com o rótulo colorido da variante ("Kunai <b>✦ Dourada</b>"), para HTML.
	$v = separarvariante($nome);
	if ($v[1] == "") { return tnomeitem($nome); }
	$tab = variantesitens();
	return tnomeitem($v[0])." <font color=\"".$tab[$v[1]]["cor"]."\"><b>✦ ".t($tab[$v[1]]["rotulo"])."</b></font>";
}}

if (!function_exists('iconeitem')){
function iconeitem($iditem, $drop, $nomeitem = "", $sufixo = ""){ // Caminho do ícone 34x34 de um item (da variante, se houver e se o arquivo existir).
	// $drop = true para a pasta drops/; $sufixo = "d" para a segunda imagem das armas de duas mãos.
	$arquivo = ($drop ? "drops/" : "").intval($iditem).$sufixo.".gif";
	$efeito = variantedoitem($nomeitem);
	if ($efeito != "" && file_exists("layoutnovo/equipamentos/variantes/".$efeito."/".$arquivo)) { return "layoutnovo/equipamentos/variantes/".$efeito."/".$arquivo; }
	return "layoutnovo/equipamentos/".$arquivo;
}}

if (!function_exists('iconeconsumivel')){
function iconeconsumivel($tipo, $valor, $nome = ""){ // Ícone 34x34 de um consumível da mochila ("Poção,hp,50,X"): layoutnovo/equipamentos/hp50.gif se existir; senão as poções de layoutnovo/equipamentos/novos/.
	// $nome (opcional): nome guardado no slot. Se a linha de drops desse consumível tiver um ícone próprio em
	// layoutnovo/equipamentos/consumiveis/ID.gif (poções pequenas 431-435), ele tem prioridade.
	$tipo = preg_replace("/[^a-z]/", "", (string)$tipo);
	if ($nome != "" && in_array($tipo, array("hp", "mp", "tp", "np", "ep"), true)) {
		$row = linhadoitem($nome.",".$tipo.",".intval($valor).",X");
		if ($row && file_exists("layoutnovo/equipamentos/consumiveis/".intval($row["id"]).".gif")) { return "layoutnovo/equipamentos/consumiveis/".intval($row["id"]).".gif"; }
		// Comidas e outros consumíveis com desenho próprio (ex.: Bolinho de Arroz, Peixe Seco): usa o ícone do drop em vez da poção genérica.
		if ($row && file_exists("layoutnovo/equipamentos/drops/".intval($row["id"]).".gif")) { return "layoutnovo/equipamentos/drops/".intval($row["id"]).".gif"; }
	}
	if ($tipo == "descanso" || $tipo == "banco") { return "layoutnovo/equipamentos/drops/".intval($valor).".gif"; } // item de descanso / expansão do banco (backpack.php).
	if ($tipo == "bomba") { return "layoutnovo/equipamentos/drops/".intval($valor).".gif"; } // ferramenta ninja de arremesso (funcoesjutsus.php).
	if ($tipo == "jutsu") { return iconepergaminho($valor); } // pergaminho de jutsu: cor do elemento do jutsu ensinado.
	if ($tipo == "ret") { return "layoutnovo/equipamentos/novos/pergaminho_retorno.gif"; } // Pergaminho de Retorno.
	if ($tipo == "rep") { return "layoutnovo/equipamentos/novos/incenso_repelente.gif"; } // Incenso Repelente.
	if ($tipo == "tel") { return "layoutnovo/equipamentos/novos/pergaminho_teleporte.gif"; } // Pergaminho de Teleporte Aleatório.
	$arquivo = "layoutnovo/equipamentos/".$tipo.intval($valor).".gif";
	if ($tipo != "" && file_exists($arquivo)) { return $arquivo; }
	$pocoes = array("hp" => "pocao_vermelha", "mp" => "pocao_azul", "tp" => "pocao_verde", "np" => "pocao_verde", "ep" => "pocao_azul", "hm" => "pocao_verde", "hmt" => "pocao_verde");
	if (isset($pocoes[$tipo])) { return "layoutnovo/equipamentos/novos/".$pocoes[$tipo].".gif"; }
	return "images/".($tipo == "bp" ? "backpack_pequena" : ($tipo == "dia" ? "diamond" : ($tipo == "per" ? "parchment" : ($tipo == "bk" ? "parchment" : "potion")))).".gif";
}}

if (!function_exists('iconesequipados')){
function iconesequipados(&$row){ // Preenche {{weaponicone}}...{{slot4icone}} (ícones já com a variante) para as fichas/colunas.
	foreach (array("weapon"=>false, "armor"=>false, "shield"=>false, "slot1"=>true, "slot2"=>true, "slot3"=>true, "slot4"=>true) as $s => $drop) {
		$row[$s."icone"] = iconeitem($row[$s."id"], $drop, $row[$s."name"]);
	}
}}


/* ------------------------------------------------------------------------
   Atributos que um item pode alterar no jogador ("campo,valor" nos campos special/attribute1/attribute2).
   ------------------------------------------------------------------------ */

if (!function_exists('nomedoatributo')){
function nomedoatributo($campo){ // Nome em português do atributo e o sufixo (% para bônus percentuais).
	$nomes = array(
		"defensepower"=>array("Poder de Defesa", ""), "attackpower"=>array("Poder de Ataque", ""),
		"droprate"=>array("Chance de Drop", "%"), "strength"=>array("Força", ""), "dexterity"=>array("Destreza", ""),
		"maxhp"=>array("Max HP", ""), "maxmp"=>array("Max CH", ""), "maxtp"=>array("Max TP", ""),
		"maxnp"=>array("Max NP", ""), "maxep"=>array("Max EP", ""), "determinacao"=>array("Determinação", ""),
		"precisao"=>array("Precisão", ""), "agilidade"=>array("Agilidade", ""), "sorte"=>array("Sorte", ""),
		"inteligencia"=>array("Inteligência", ""), "goldbonus"=>array("Bônus Ryou", "%"), "expbonus"=>array("Bônus Exp.", "%"),
		"critico"=>array("Chance de Crítico", "%"), "esquiva"=>array("Esquiva", "%"), "regenhp"=>array("Regeneração de HP por passo", ""), "regenmp"=>array("Regeneração de CH por passo", ""), "regentp"=>array("Regeneração de TP por passo", ""), "regennp"=>array("Regeneração de NP por passo", ""), "regenep"=>array("Regeneração de EP por passo", ""),
		"economiachakra"=>array("Economia de Chakra", "%"));
	if (isset($nomes[$campo])) { return $nomes[$campo]; }
	return array(ucfirst((string)$campo), "");
}}

if (!function_exists('atributovalido')){
function atributovalido($campo){ // Lista branca dos campos (colunas da tabela users) que um item pode alterar.
	return in_array($campo, array("maxhp","maxmp","maxtp","maxnp","maxep","defensepower","attackpower","strength","dexterity",
		"expbonus","goldbonus","sorte","agilidade","determinacao","precisao","inteligencia","droprate",
		"critico","esquiva","regenhp","regenmp","regentp","regennp","regenep","economiachakra"), true);
}}

if (!function_exists('aplicaratributo')){
function aplicaratributo($atributo, $sinal){ // Soma ($sinal = 1) ou retira ($sinal = -1) do jogador um bônus "campo,valor" ("X" = nenhum).
	global $userrow;
	if ($atributo == "" || $atributo == "X") { return; }
	$partes = explode(",", $atributo);
	if (count($partes) < 2 || !atributovalido($partes[0])) { return; }
	$valor = intval($partes[1]) * $sinal;
	$userrow[$partes[0]] = intval($userrow[$partes[0]]) + $valor;
	// Força e Destreza também mexem no Poder de Ataque e no Poder de Defesa.
	if ($partes[0] == "strength") { $userrow["attackpower"] = intval($userrow["attackpower"]) + $valor; }
	elseif ($partes[0] == "dexterity") { $userrow["defensepower"] = intval($userrow["defensepower"]) + $valor; }
}}

if (!function_exists('bonusdoitem')){
function bonusdoitem($iditem, $nomeitem, $sinal){ // Aplica/retira os bônus de uma arma, colete ou bandana (tabela items). Devolve a linha do item (ou false).
	global $userrow;
	$itemsquery = doquery("SELECT * FROM {{table}} WHERE id='".intval($iditem)."' LIMIT 1", "items");
	$itemsrow = mysqli_fetch_array($itemsquery);
	if (!$itemsrow) { return false; }
	// Itens fundidos/melhorados têm "+N" no nome: cada ponto vale 20 de ataque (arma) ou defesa (colete/bandana).
	// (o comentário original dizia "15 de bônus", mas o valor usado sempre foi 20)
	$melhoria = explode("+", (string)$nomeitem);
	$extra = isset($melhoria[1]) ? intval($melhoria[1]) * 20 : 0;
	$campo = ($itemsrow["type"] == 1) ? "attackpower" : "defensepower";
	$userrow[$campo] = intval($userrow[$campo]) + (intval($itemsrow["attribute"]) + $extra) * $sinal;
	// Até 3 bônus por item: special, special2 e special3 ("X" = vazio).
	foreach (array("special", "special2", "special3") as $c) { if (isset($itemsrow[$c])) { aplicaratributo($itemsrow[$c], $sinal); } }
	foreach (bonusvariante($nomeitem, $itemsrow, "items") as $b) { aplicaratributo($b, $sinal); } // variante rara (sufixo ":efeito").
	return $itemsrow;
}}

if (!function_exists('bonusdodrop')){
function bonusdodrop($iddrop, $sinal, $nomeitem = ""){ // Aplica/retira os atributos de um drop (tabela drops) e da variante do nome. Devolve a linha do drop (ou false).
	$dropquery = doquery("SELECT * FROM {{table}} WHERE id='".intval($iddrop)."' LIMIT 1", "drops");
	$droprow = mysqli_fetch_array($dropquery);
	if (!$droprow) { return false; }
	aplicaratributo($droprow["attribute1"], $sinal);
	aplicaratributo($droprow["attribute2"], $sinal);
	if (isset($droprow["attribute3"])) { aplicaratributo($droprow["attribute3"], $sinal); }
	foreach (bonusvariante($nomeitem, $droprow, "drops") as $b) { aplicaratributo($b, $sinal); }
	return $droprow;
}}

if (!function_exists('variantesrecomendadas')){
function variantesrecomendadas(){ // Valores padrão das variantes: efeito => [bônus fixo, percentual (sem bônus fixo), % extra (com bônus fixo)].
	return array("dourado"=>array("", 18, 0), "brilho"=>array("critico,3", 10, 8), "raio"=>array("esquiva,3", 10, 10),
		"chama"=>array("attackpower,10", 10, 12), "gelo"=>array("defensepower,10", 10, 12), "sombra"=>array("economiachakra,5", 10, 10));
}}

if (!function_exists('variantesreaplicar')){
function variantesreaplicar($mudar){ // Muda a tabela variantes sem bagunçar os atributos de quem já está com itens raros equipados.
	// Os bônus são somados ao equipar e retirados ao desequipar (aplicaratributo); se a regra mudasse no meio, o desequipar tiraria
	// um valor diferente do que entrou. Por isso: tira o bônus antigo de cada peça rara equipada, chama $mudar() (os UPDATEs na tabela),
	// relê as variantes e soma o bônus novo, gravando cada jogador numa transação.
	global $userrow;
	$guardado = $userrow;
	$q = doquery("SELECT * FROM {{table}} WHERE CONCAT_WS('|', weaponname, armorname, shieldname, slot1name, slot2name, slot3name, slot4name) LIKE '%:%'", "users");
	$jogadores = array();
	while ($q && $r = mysqli_fetch_array($q, MYSQLI_ASSOC)) { $jogadores[] = $r; }
	$pecas = function ($u) { // [nome, linha, tabela] de cada peça equipada com variante
		$l = array();
		foreach (array("weapon", "armor", "shield") as $c) {
			if (intval($u[$c."id"]) > 0 && variantedoitem($u[$c."name"]) != "") { $r = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($u[$c."id"])."' LIMIT 1", "items")); if ($r) { $l[] = array($u[$c."name"], $r, "items"); } }
		}
		for ($i = 1; $i <= 4; $i++) {
			if (intval($u["slot".$i."id"]) > 0 && variantedoitem($u["slot".$i."name"]) != "") { $r = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($u["slot".$i."id"])."' LIMIT 1", "drops")); if ($r) { $l[] = array($u["slot".$i."name"], $r, "drops"); } }
		}
		return $l;
	};
	$antes = array();
	foreach ($jogadores as $k => $u) { $l = array(); foreach ($pecas($u) as $p) { $l[] = bonusvariante($p[0], $p[1], $p[2]); } $antes[$k] = $l; }
	$mudar();
	variantesitens(false, true);
	$n = 0;
	foreach ($jogadores as $k => $u) {
		doquery("START TRANSACTION", "users");
		$userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($u["id"])."' LIMIT 1 FOR UPDATE", "users"));
		$pecasagora = $pecas($userrow);
		if (!$userrow || count($pecasagora) != count($antes[$k])) { doquery("ROLLBACK", "users"); continue; } // trocou de equipamento no meio: deixa como está
		foreach ($antes[$k] as $lista) { foreach ($lista as $b) { aplicaratributo($b, -1); } }
		foreach ($pecasagora as $p) { foreach (bonusvariante($p[0], $p[1], $p[2]) as $b) { aplicaratributo($b, 1); } }
		if (function_exists('limitarpontosatuais')) { limitarpontosatuais(); }
		doquery("UPDATE {{table}} SET ".sqlatributosdojogador()." WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
		doquery("COMMIT", "users");
		$n++;
	}
	$userrow = $guardado;
	return $n;
}}

/* Bônus de combate dos equipamentos (colunas da tabela users, somadas pelos itens e drops):
     critico        % extra de "Hit excelente" (dano x2), testado depois da regra antiga de Força/Determinação. Máx. 50%.
     esquiva        % extra de desviar do ataque do inimigo, testado depois da regra antiga de Destreza/Agilidade. Máx. 40%.
     regenhp        HP recuperado a cada passo no mapa (explore.php), sem passar do HP máximo.
     economiachakra % a menos no custo de chakra dos jutsus: custo = arredondar para cima(custo x (100 - economia) / 100),
                    economia máx. 50% e custo mínimo de 1 (para jutsus que custam algo). */

if (!function_exists('bonuscombate')){
function bonuscombate($campo){ // Valor do bônus do jogador já limitado ao máximo da regra (critico, esquiva, regenhp, economiachakra).
	global $userrow;
	$limites = array("critico"=>50, "esquiva"=>40, "regenhp"=>1000, "regenmp"=>1000, "regentp"=>100, "regennp"=>100, "regenep"=>20, "economiachakra"=>50);
	$v = isset($userrow[$campo]) ? intval($userrow[$campo]) : 0;
	if (!isset($limites[$campo])) { return 0; }
	return max(0, min($limites[$campo], $v));
}}

/* Balanceamento dos atributos de distribuição (novembro/2026; ver docs/ATRIBUTOS.md):
     Sorte ........ drop x (1 + √Sorte/10): 25 = x1,5 · 100 = x2 · 400 = x3 (antes 1 + Sorte/100, sem limite).
     Determinação . hit excelente: rand(1,150) <= √Força + Determinação×6/100 (antes ×2/100), teto 75 = 50%.
     Agilidade .... esquiva: rand(1,150) <= √Destreza + Agilidade×6/100 (antes ×2/100 em degraus de 50), teto 60 = 40%.
     Inteligência . +0,05% de dano em todo ninjutsu por ponto, máx. +50% (antes só ajudava contra o sono).
     Precisão ..... no PvP passa a ×2/100 (igual à Agilidade). */
if (!function_exists('nivelregional')){
function nivelregional($lat, $lon){ // Nível máximo dos inimigos na casa: regra antiga (1 a cada 5 casas do centro) ou, se menor,
	// o da vila mais próxima com nível base (towns.nivelbase + 1 a cada 5 casas da vila, até 60 casas dela). Cria bolsões mais fáceis perto das vilas distantes.
	static $vilas = null;
	if ($vilas === null) { $vilas = array(); $q = doquery("SELECT latitude,longitude,nivelbase FROM {{table}} WHERE nivelbase>0", "towns"); while ($v = mysqli_fetch_array($q)) { $vilas[] = $v; } }
	$lat = intval($lat); $lon = intval($lon);
	$nivel = (int)floor(max(abs($lat) + 5, abs($lon) + 5) / 5);
	foreach ($vilas as $v) {
		$d = max(abs($lat - intval($v["latitude"])), abs($lon - intval($v["longitude"])));
		if ($d > 60) { continue; } // a vila só influencia até 60 casas: longe delas (e nos cantos do mapa) vale a regra antiga, até o nível 81.
		$nivel = min($nivel, intval($v["nivelbase"]) + (int)floor($d / 5));
	}
	return max(1, $nivel);
}}

if (!function_exists('fatorsorte')){
function fatorsorte($sorte){ return 1 + sqrt(max(0, floatval($sorte))) / 10; }
}
if (!function_exists('limitecritico')){
function limitecritico($forca, $determinacao){ return min(75, sqrt(max(0, intval($forca))) + max(0, intval($determinacao)) * 6 / 100); }
}
if (!function_exists('limiteesquiva')){
function limiteesquiva($destreza, $agilidade){ return min(60, sqrt(max(0, intval($destreza))) + max(0, intval($agilidade)) * 6 / 100); }
}
if (!function_exists('bonusinteligencia')){
function bonusinteligencia($inteligencia){ return min(50, max(0, intval($inteligencia)) * 0.05); }
}

if (!function_exists('custochakra')){
function custochakra($mp, $spell = null){ // Custo de chakra de um jutsu depois da Economia de Chakra dos equipamentos e, se $spell (linha de spells) vier, da especialização/Senjutsu do jogador global.
	global $userrow;
	$mp = intval($mp);
	if ($mp <= 0) { return 0; }
	$economia = bonuscombate("economiachakra");
	if (is_array($userrow) && isset($userrow["treino_chakra"])) { $economia += max(0, min(50, intval($userrow["treino_chakra"]))); } // Controle de Chakra (treinoslongos.php).
	if (is_array($spell) && is_array($userrow) && isset($userrow["charclass"]) && function_exists('classedojogador')) {
		$m = classedojogador($userrow);
		$economia += $m["economia"]; // Senjutsu (Ninjutsu): todos os jutsus.
		if (((string)$spell["elemento"] == "neutro" || (string)$spell["elemento"] == "") && in_array(intval($spell["type"]), array(2, 4, 5, 6))) { $economia += $m["economia_tecnica"]; } // técnicas sem elemento (Taijutsu).
	}
	// Jutsus de nível alto custam mais: +1% por nível mínimo acima de 1 (teto +60%), antes dos descontos.
	if (is_array($spell) && isset($spell["nivelminimo"])) { $mp = $mp * (100 + min(60, max(0, intval($spell["nivelminimo"]) - 1))) / 100; }
	return max(1, (int)ceil($mp * (100 - max(0, min(75, $economia))) / 100));
}}

if (!function_exists('pocaotipo')){
function pocaotipo($slot){ // "Tipo" de uma poção da mochila ("nome,hp,50,X[,qtd]") = id do drop/item de poção (por nome e efeito); sem cadastro, "n"+crc32 do nome.
	$p = array_pad(explode(",", (string)$slot), 3, "");
	$r = mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE name='".addslashes($p[0])."' AND consumivel='".addslashes($p[1].",".intval($p[2]))."' ORDER BY id LIMIT 1", "drops"));
	return $r ? (string)intval($r["id"]) : "n".sprintf("%u", crc32($p[0]));
}}

if (!function_exists('pocaobloqueada')){
function pocaobloqueada($usadas, $tipo){ // true se a poção desse tipo já chegou ao limite da luta (control.limitepocoesluta; 0 = sem limite).
	global $controlrow;
	$limite = isset($controlrow["limitepocoesluta"]) ? intval($controlrow["limitepocoesluta"]) : 1;
	if ($limite <= 0) { return false; }
	$lista = ((string)$usadas === "") ? array() : explode(",", (string)$usadas);
	return count(array_keys($lista, (string)$tipo, true)) >= $limite;
}}

if (!function_exists('pocaoanotar')){
function pocaoanotar($usadas, $tipo){ // Lista "66,67" das poções usadas na luta atual, com mais esta (cabe em VARCHAR(255)).
	$novo = ((string)$usadas === "") ? (string)$tipo : $usadas.",".$tipo;
	return (strlen($novo) > 255) ? (string)$usadas : $novo;
}}

if (!function_exists('limitarpontosatuais')){
function limitarpontosatuais(){ // Depois de mudar os máximos, garante que HP/CH/TP/NP/EP atuais não passem do máximo.
	global $userrow;
	foreach (array("hp","mp","tp","np","ep") as $p) {
		if (intval($userrow["current".$p]) > intval($userrow["max".$p])) { $userrow["current".$p] = max(0, intval($userrow["max".$p])); }
	}
}}

if (!function_exists('atributosnegativos')){
function atributosnegativos($row){ // Itens de compensação (bônus negativos): true se algum atributo do jogador ficou abaixo de zero (ou HP máximo < 1)
	// depois de somar os bônus. As colunas são UNSIGNED e seriam gravadas como 0: ao desequipar o jogador ganharia pontos
	// do nada. Por isso equipar é recusado nesse caso (mochila, drop direto no slot e banco).
	foreach (array("maxmp","maxtp","maxnp","maxep","attackpower","defensepower","strength","dexterity","sorte","agilidade",
		"determinacao","precisao","inteligencia","droprate","critico","esquiva","regenhp","regenmp","regentp","regennp","regenep","economiachakra") as $c) {
		if (isset($row[$c]) && intval($row[$c]) < 0) { return true; }
	}
	return intval($row["maxhp"]) < 1;
}}

if (!function_exists('sqlatributosdojogador')){
function sqlatributosdojogador(){ // Trecho "campo='valor',..." com todos os atributos que equipar/desequipar pode alterar.
	global $userrow;
	$campos = array("maxhp","maxmp","maxtp","maxnp","maxep","currenthp","currentmp","currenttp","currentnp","currentep",
		"attackpower","defensepower","strength","dexterity","expbonus","goldbonus","sorte","agilidade","determinacao",
		"precisao","inteligencia","droprate","critico","esquiva","regenhp","regenmp","regentp","regennp","regenep","economiachakra");
	$sql = array();
	// expbonus/goldbonus podem ser negativos; os demais são colunas UNSIGNED e nunca ficam abaixo de zero.
	conjuntosrecalcular($userrow); // bônus de conjuntos das peças equipadas agora (os ids do $userrow já devem estar atualizados).
	foreach ($campos as $c) { $v = intval($userrow[$c]); if ($c != "expbonus" && $c != "goldbonus" && $v < 0) { $v = 0; } $sql[] = $c."='".$v."'"; }
	if (array_key_exists("conjuntobonus", $userrow)) { $sql[] = "conjuntobonus='".addslashes((string)$userrow["conjuntobonus"])."'"; }
	return implode(",", $sql);
}}

/* Durabilidade dos equipamentos: "-,arma,colete,bandana,slot1,slot2,slot3,slot4"
   (posição 0 não é usada; número = batalhas restantes; X = infinita ou slot vazio). */

if (!function_exists('slotsequipamento')){
function slotsequipamento(){ // Posição -> prefixo da coluna: 1 arma, 2 colete, 3 bandana, 4-7 acessórios 1 a 4 (mesmas posições da durabilidade e do desequipar.php?qual=N).
	return array(1=>"weapon", 2=>"armor", 3=>"shield", 4=>"slot1", 5=>"slot2", 6=>"slot3", 7=>"slot4");
}}

if (!defined('NUMACESSORIOS')) { define('NUMACESSORIOS', 4); } // Acessórios equipáveis (slot1..slot4).

if (!function_exists('lerdurabilidade')){
function lerdurabilidade($texto){ // Converte o texto de durabilidade em array de 8 posições (números ou "X"); textos antigos de 7 ganham o slot4 = X.
	$dur = array_slice(array_pad(explode(",", (string)$texto), 8, "X"), 0, 8);
	$dur[0] = "-";
	for ($i = 1; $i < 8; $i++) {
		$dur[$i] = trim($dur[$i]);
		$dur[$i] = (is_numeric($dur[$i])) ? intval($dur[$i]) : "X";
	}
	return $dur;
}}

if (!function_exists('juntardurabilidade')){
function juntardurabilidade($dur){ // Faz o caminho inverso de lerdurabilidade().
	$dur = array_slice(array_pad($dur, 8, "X"), 0, 8);
	$dur[0] = "-";
	return implode(",", $dur);
}}

/* Durabilidade máxima por item (docs/DURABILIDADE.md): colunas items.durabilidade e drops.durabilidade
   (vitórias até quebrar; 0 = infinita). Só os itens fortes têm valor; os fracos continuam infinitos.
   Vale para o item que ENTRA no jogo (loja, drop, alquimia, missão, admin); itens que o jogador já tem
   mantêm o que estava guardado (troca e mochila levam a durabilidade atual junto). */

if (!function_exists('durabilidademaxima')){
function durabilidademaxima($tipo, $id){ // Durabilidade máxima do item (tipo/posição 1-3 = items, 4-7 = drops: acessórios 1 a 4); 0 = infinita.
	static $cache = array();
	$tipo = intval($tipo); $id = intval($id);
	if ($id <= 0 || $tipo < 1 || $tipo > 7) { return 0; }
	$tabela = ($tipo <= 3) ? "items" : "drops";
	if (!isset($cache[$tabela][$id])) {
		$row = mysqli_fetch_array(doquery("SELECT durabilidade FROM {{table}} WHERE id='$id' LIMIT 1", $tabela));
		$cache[$tabela][$id] = $row ? max(0, intval($row["durabilidade"])) : 0;
	}
	return $cache[$tabela][$id];
}}

if (!function_exists('durabilidadenova')){
function durabilidadenova($tipo, $id){ // Durabilidade de um item novo: a máxima ou "X" (infinita).
	$m = durabilidademaxima($tipo, $id);
	return ($m > 0) ? $m : "X";
}}

if (!function_exists('durabilidadeativa')){
function durabilidadeativa(){ // control.durabilidade_ativa (padrão 1): as vitórias gastam durabilidade?
	global $controlrow;
	return !isset($controlrow["durabilidade_ativa"]) || intval($controlrow["durabilidade_ativa"]) != 0;
}}

if (!function_exists('durabilidadebaixa')){
function durabilidadebaixa($atual, $max){ // Aviso de desgaste: durabilidade numérica em até 10% da máxima (mínimo 5 vitórias).
	if (!is_numeric($atual) || intval($max) <= 0) { return false; }
	return intval($atual) <= max(5, (int)ceil(intval($max) * 0.10));
}}

if (!function_exists('avisodurabilidade')){
function avisodurabilidade($row){ // Lista HTML dos equipamentos com pouca durabilidade (vazia se nenhum ou se o desgaste estiver desligado).
	if (!durabilidadeativa()) { return ""; }
	$dur = lerdurabilidade($row["durabilidade"]);
	$slots = slotsequipamento();
	$lista = array();
	foreach ($slots as $h => $s) {
		if ($dur[$h] === "X" || (string)$row[$s."name"] == "None") { continue; }
		$max = durabilidademaxima($h, $row[$s."id"]);
		if (durabilidadebaixa($dur[$h], $max)) { $lista[] = tf("%s: %s/%s", nomeitemhtml($row[$s."name"]), intval($dur[$h]), $max); }
	}
	if (!count($lista)) { return ""; }
	return "<div id=\"avisodurabilidade\" style=\"color:#8b1a00;font-size:11px;margin:3px 0\"><b>".t("Desgaste:")."</b> ".implode("; ", $lista).". ".t("Repare na loja de uma vila.")."</div>";
}}

if (!function_exists('tnomeitem')){
function tnomeitem($nome){ // Nome de item traduzido (t()), mantendo o "+N" dos itens melhorados, o "None" dos slots vazios e o "✦ rótulo" das variantes (texto puro).
	$v = separarvariante($nome);
	if ($v[1] != "") { $tab = variantesitens(); return tnomeitem($v[0])." ✦ ".t($tab[$v[1]]["rotulo"]); }
	if ($nome == "None" || $nome == "" || idioma() == "pt") { return $nome; }
	if (preg_match("/^(.*?)\s*\+(\d+)$/", (string)$nome, $partes)) { return t($partes[1])." +".$partes[2]; }
	return t($nome);
}}

if (!function_exists('paginaderetorno')){
function paginaderetorno(){ // Página para onde o jogador volta depois de usar a mochila/desequipar (só páginas do próprio jogo).
	global $userrow;
	$pagina = isset($userrow["pagina"]) ? (string)$userrow["pagina"] : "";
	if ($pagina != "" && preg_match("/^[a-z0-9_]+\.php(\?[^\r\n]*)?$/i", $pagina)) { return "./".$pagina; }
	return "./index.php";
}}


/* ------------------------------------------------------------------------
   Explicação (tooltip) dos itens: monta a chamada JavaScript explicdrop(...) usada no
   onmouseover das imagens dos equipamentos e da mochila.
   ------------------------------------------------------------------------ */

if (!function_exists('textojs')){
function textojs($texto){ // Prepara um texto para ficar dentro de uma string JavaScript com aspas simples.
	return str_replace(array("\r", "\n"), "", addslashes((string)$texto));
}}

if (!function_exists('explicaratributo')){
function explicaratributo($atributo){ // "campo,valor" -> "Nome: +valor" colorido (azul se positivo, vermelho se negativo).
	$novaatr = array_pad(explode(",", $atributo), 2, 0);
	$nome = nomedoatributo($novaatr[0]);
	$valor = intval($novaatr[1]);
	if ($valor > 0){ $valor = "<font color=darkblue>+".$valor.$nome[1]."</font>"; }
	else { return "<font color=red><b>▼ ".t($nome[0]).": ".$valor.$nome[1]."</b></font>"; } // bônus negativo (itens de compensação): a linha inteira em vermelho.
	return t($nome[0]).": ".$valor;
}}

if (!function_exists('textodurabilidade')){
function textodurabilidade($durabilidade, $max){ // "Durabilidade: N/M" (vermelho quando baixa) ou "Durabilidade: INF" no tooltip.
	if (!is_numeric($durabilidade)) { return tf("Durabilidade: %s", $durabilidade); }
	$texto = ($max > 0) ? intval($durabilidade)."/".intval($max) : intval($durabilidade);
	if (durabilidadebaixa($durabilidade, $max)) { return "<font color=darkred><b>".tf("Durabilidade: %s", $texto)."</b></font>"; }
	return tf("Durabilidade: %s", $texto);
}}

if (!function_exists('conteudoexplic')){
function conteudoexplic($iditem, $tipo, $objeto, $durabilidade, $nomeitem = "", $qtd = 1, $comparar = false){ // Tooltip de um item. $tipo: 1-3 = items, 4-6 = drops; id não numérico = consumível. $nomeitem: nome guardado (mostra o refino e a variante). $qtd: unidades da pilha. $comparar: mostra a diferença para o item equipado no mesmo espaço (mochila, loja, drop).
	// O retorno vai dentro de um atributo HTML (onmouseover="..."), por isso sai com htmlspecialchars.
	$conteudo = "";
	if ($durabilidade == '*'){$durabilidade = "INF";}
	// Linhas finais: pilha ("Pilha: 3 / 20") e a marca "Não vendável" (items/drops.naoVendavel).
	$texto = $nomeitem.",".$iditem.",".$tipo.",X";
	$max = pilhamaxima($texto);
	$extra = ($max > 1) ? "<br>".tf("Pilha: %s / %s", max(1, intval($qtd)), $max) : "";
	if (itemnaovendavel($texto)) { $extra .= "<br><font color=darkred>".t("Não vendável")."</font>"; }

	if (!is_numeric($iditem)){ // consumíveis: $tipo guarda a quantidade recuperada.
		$objnome = t("Item");
		if ($iditem == "hp"){$conteudo = tf("HP:<font color=darkblue> + %s</font><br>Quantidade: %s", intval($tipo), $durabilidade); $objnome = t("Poção de Vida");}
		elseif ($iditem == "mp"){$conteudo = tf("CH:<font color=darkblue> + %s</font><br>Quantidade: %s", intval($tipo), $durabilidade); $objnome = t("Poção de Chakra");}
		elseif ($iditem == "tp"){$conteudo = tf("TP:<font color=darkblue> + %s</font><br>Quantidade: %s", intval($tipo), $durabilidade); $objnome = t("Poção de Viagem");}
		elseif ($iditem == "np"){$conteudo = tf("NP:<font color=darkblue> + %s</font><br>Quantidade: %s", intval($tipo), $durabilidade); $objnome = t("Poção Natural");}
		elseif ($iditem == "ep"){$conteudo = tf("EP:<font color=darkblue> + %s</font><br>Quantidade: %s", intval($tipo), $durabilidade); $objnome = t("Poção de Elemento");}
		elseif ($iditem == "ret"){ $objnome = t("Pergaminho de Retorno"); $conteudo = t("Leva você de volta para a sua vila natal.<br>Não funciona em batalhas, duelos ou PvP.<br><i>Clique para usar.</i>"); }
		if ($iditem == "ret"){ $conteudo .= "<br>".tf("Custo: %s TP", custotpretorno()); }
		if ($iditem == "tel"){ $objnome = t("Pergaminho de Teleporte Aleatório"); $conteudo = tf("Leva você para uma vila aleatória, que pode ser uma vila que você ainda não conhece (nunca a vila onde você está nem a sua vila natal).<br>Custo: %s TP. Raríssimo.<br>Não funciona em batalhas, duelos ou PvP.<br><i>Clique para usar.</i>", custotpteleporte()); }
		elseif ($iditem == "rep"){ $objnome = t("Incenso Repelente"); $conteudo = tf("Nenhum inimigo aleatório por %s passos ou %s minutos.<br><i>Clique para acender.</i>", INCENSO_PASSOS, INCENSO_MINUTOS); }
		elseif ($iditem == "descanso"){ // item de descanso: $tipo = id do drop.
			$dr = mysqli_fetch_array(doquery("SELECT descanso_mult, descanso_horas FROM {{table}} WHERE id='".intval($tipo)."' LIMIT 1", "drops"));
			$objnome = t("Item de descanso");
			if ($dr) { $conteudo = tf("Tudo o que você recupera ao descansar fica ×%s por %s horas.", descansomulttexto($dr["descanso_mult"]), max(2, intval($dr["descanso_horas"])))."<br>".t("Use pela mochila.")."<br>".tf("Quantidade: %s", $durabilidade); }
		}
		elseif ($iditem == "banco"){ // expansão do banco: $tipo = id do drop.
			$dr = mysqli_fetch_array(doquery("SELECT banco_limite FROM {{table}} WHERE id='".intval($tipo)."' LIMIT 1", "drops"));
			$objnome = t("Expansão do banco");
			if ($dr) { $conteudo = tf("Aumenta o seu banco para %s itens (para sempre).", intval($dr["banco_limite"]))."<br>".t("Use pela mochila.")."<br>".tf("Quantidade: %s", $durabilidade); }
		}
		elseif ($iditem == "bomba"){ // ferramenta ninja: $tipo = id do drop.
			if (!function_exists('itembatalhalinha')) { include_once(__DIR__.'/funcoesjutsus.php'); }
			$br = itembatalhalinha("x,bomba,".intval($tipo));
			$objnome = t("Ferramenta Ninja");
			if ($br) { $conteudo = str_replace(" · ", "<br>", htmlspecialchars(itembatalharesumo($br), ENT_QUOTES, "UTF-8"))."<br>".t("Arremesse durante uma batalha ou duelo (gasta o turno).")."<br>".tf("Quantidade: %s", $durabilidade); }
		}
		elseif ($iditem == "jutsu"){ // pergaminho de jutsu: $tipo = id do jutsu ensinado.
			$jr = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($tipo)."' LIMIT 1", "spells"));
			$objnome = t("Pergaminho de Jutsu");
			if ($jr) { $conteudo = tf("Ensina: <b>%s</b>", t($jr["name"]))."<br>".implode("<br>", jutsudescricaoefeitos($jr))."<br>".tf("Custo: %s CH", intval($jr["mp"]))."<br>".jutsutextorequisitos($jr)."<i>".t("Clique para aprender.")."</i>"; }
		}
		// Poções: o título é o nome guardado no slot ("Poção Vermelha Pequena"), quando houver.
		if (in_array($iditem, array("hp", "mp", "tp", "np", "ep"), true) && $nomeitem != "" && $nomeitem != "None") { $objnome = nomeitemhtml($nomeitem); }
		// Ferramentas ninja e itens de descanso: o título é o nome do próprio item (guardado no slot, ou o do drop).
		if ($iditem == "bomba" || $iditem == "descanso" || $iditem == "banco") {
			if ($nomeitem != "" && $nomeitem != "None") { $objnome = nomeitemhtml($nomeitem); }
			else { $dn = mysqli_fetch_array(doquery("SELECT name FROM {{table}} WHERE id='".intval($tipo)."' LIMIT 1", "drops")); if ($dn) { $objnome = t($dn["name"]); } }
		}
		$conteudo .= $extra;
		return htmlspecialchars("explicdrop('".textojs($objeto)."', '".textojs($objnome)."', '".textojs($conteudo)."', '1', '1')", ENT_QUOTES, 'UTF-8');
	}
	if (intval($iditem) == 0) { return ""; }

	if ($tipo <= 3){ // arma, colete ou bandana
		// Mesma linha que pilhamaxima()/itemnaovendavel() já leram (cache de linhadoitem(): uma query por item na página).
		$itemsrow = (intval($tipo) >= 1) ? linhadoitem($texto) : mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($iditem)."' LIMIT 1", "items"), MYSQLI_ASSOC);
		if (!$itemsrow) { return ""; }
		$rotulo = ($tipo == 1) ? t("Poder de Ataque") : t("Poder de Defesa");
		if ($itemsrow["attribute"] >= 0){ $conteudo = $rotulo.": <font color=darkblue>+".intval($itemsrow["attribute"])."</font><br>"; }
		else { $conteudo = $rotulo.": <font color=darkred>".intval($itemsrow["attribute"])."</font><br>"; }
		foreach (array("special", "special2", "special3") as $c) {
			if (isset($itemsrow[$c]) && $itemsrow[$c] != "X" && $itemsrow[$c] != ""){ $conteudo .= explicaratributo($itemsrow[$c])."<br>"; }
		}
		$conteudo .= explicarvariante($nomeitem, $itemsrow, "items");
		$conteudo .= textodurabilidade($durabilidade, isset($itemsrow["durabilidade"]) ? intval($itemsrow["durabilidade"]) : 0);
	} elseif ($tipo <= 6){ // drops
		// Mesma linha que pilhamaxima()/itemnaovendavel() já leram (cache de linhadoitem(): uma query por item na página).
		$itemsrow = (intval($tipo) >= 1) ? linhadoitem($texto) : mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($iditem)."' LIMIT 1", "drops"), MYSQLI_ASSOC);
		if (!$itemsrow) { return ""; }
		if ($itemsrow["attribute1"] != "X"){ $conteudo .= explicaratributo($itemsrow["attribute1"]); }
		if ($itemsrow["attribute2"] != "X"){ $conteudo .= "<br>".explicaratributo($itemsrow["attribute2"]); }
		if (isset($itemsrow["attribute3"]) && $itemsrow["attribute3"] != "X" && $itemsrow["attribute3"] != ""){ $conteudo .= "<br>".explicaratributo($itemsrow["attribute3"]); }
		if (strpos($itemsrow["attribute1"].$itemsrow["attribute2"].(isset($itemsrow["attribute3"]) ? $itemsrow["attribute3"] : ""), ",-") !== false) { $conteudo .= "<br><font color=darkred><i>".t("Item de compensação: ganha muito, mas perde algo.")."</i></font>"; }
		// Drop consumível ("hp,50" / "mp,40"): vira poção na mochila.
		$cons = array_pad(explode(",", isset($itemsrow["consumivel"]) ? (string)$itemsrow["consumivel"] : ""), 2, 0);
		if ($cons[0] == "hp"){ $conteudo = tf("HP:<font color=darkblue> + %s</font><br>Consumível (mochila)", intval($cons[1])); }
		elseif ($cons[0] == "mp"){ $conteudo = tf("CH:<font color=darkblue> + %s</font><br>Consumível (mochila)", intval($cons[1])); }
		elseif (in_array($cons[0], array("tp", "np", "ep"), true)){ $conteudo = tf("%s:<font color=darkblue> + %s</font><br>Consumível (mochila)", strtoupper($cons[0]), intval($cons[1])); }
		if (intval($iditem) == iddoselo()) { $conteudo .= ($conteudo != "" ? "<br>" : "").t("Gasto pelo Viajar Para ao ir direto a uma vila que não é a sua vila natal.<br><i>Fica na mochila.</i>"); }
		// Livro de receitas (receitas.livro_drop = id deste drop): lido na Alquimia (alquimia.php).
		$livroq = doquery("SELECT nome FROM {{table}} WHERE livro_drop='".intval($iditem)."' AND ativa='1'", "receitas");
		$livros = array(); while ($livroq && $lr = mysqli_fetch_array($livroq)) { $livros[] = t($lr["nome"]); }
		if (count($livros)) { $conteudo .= ($conteudo != "" ? "<br>" : "").tf("Ensina a receita: %s.<br><i>Leia o livro na Alquimia.</i>", implode(", ", $livros)); }
		$conteudo .= "<br>".explicarvariante($nomeitem, $itemsrow, "drops").textodurabilidade($durabilidade, isset($itemsrow["durabilidade"]) ? intval($itemsrow["durabilidade"]) : 0);
	} else {
		return "";
	}

	// Título: o nome guardado (com refino/variante) quando ele é desse item; senão o nome da tabela.
	$titulo = ($nomeitem != "" && $nomeitem != "None") ? nomeitemhtml($nomeitem) : tnomeitem($itemsrow["name"]);
	$conteudo .= explicarconjunto(($tipo <= 3) ? "item" : "drop", $iditem); // conjunto de equipamento (peças e bônus 2/4).
	if ($comparar) { $conteudo .= compararcomequipado($tipo, $iditem, $nomeitem); }
	$conteudo .= $extra;
	return htmlspecialchars("explicdrop('".textojs($objeto)."', '".textojs($titulo)."', '".textojs($conteudo)."', '1', '1')", ENT_QUOTES, 'UTF-8');
}}

if (!function_exists('explicarvariante')){
function explicarvariante($nomeitem, $linha, $tabela){ // Linhas do tooltip com o bônus da variante ("✦ Variante rara" + bônus), ou "".
	$efeito = variantedoitem($nomeitem);
	if ($efeito == "") { return ""; }
	$tab = variantesitens();
	$texto = "<font color=".$tab[$efeito]["cor"]."><b>✦ ".t("Variante rara")."</b></font><br>";
	foreach (bonusvariante($nomeitem, $linha, $tabela) as $b) { $texto .= explicaratributo($b)."<br>"; }
	return $texto;
}}

/* ------------------------------------------------------------------------
   Conjuntos de equipamento (tabela conjuntos, migração tools/conjuntos.sql).
   pecas = "item:ID;drop:ID;..." (item = arma/colete/bandana, drop = acessório); bonus2/bonus4 = "coluna,valor;..."
   ganhos com 2 e com 4 peças DIFERENTES equipadas (o bônus de 4 soma com o de 2).
   O bônus já somado fica em users.conjuntobonus: conjuntosrecalcular() tira o antigo e soma o novo, então
   pode ser chamada quantas vezes for (sqlatributosdojogador() a chama em todo equipar/desequipar).
   ------------------------------------------------------------------------ */

if (!function_exists('conjuntoslista')){
function conjuntoslista(){ // Todos os conjuntos, com as peças já separadas: array(id => linha + "lista" => array(array("item"|"drop", id))).
	static $lista = null;
	if ($lista !== null) { return $lista; }
	$lista = array();
	$existe = doquery("SHOW TABLES LIKE '{{table}}'", "conjuntos"); // sem a migração o jogo segue sem conjuntos.
	if (!$existe || mysqli_num_rows($existe) == 0) { return $lista; }
	$q = doquery("SELECT * FROM {{table}} ORDER BY id", "conjuntos");
	while ($r = mysqli_fetch_array($q, MYSQLI_ASSOC)) {
		$r["lista"] = array();
		foreach (explode(";", (string)$r["pecas"]) as $p) {
			$p = array_pad(explode(":", trim($p)), 2, "");
			if (($p[0] == "item" || $p[0] == "drop") && intval($p[1]) > 0) { $r["lista"][] = array($p[0], intval($p[1])); }
		}
		$lista[intval($r["id"])] = $r;
	}
	return $lista;
}}

if (!function_exists('conjuntodapeca')){
function conjuntodapeca($tipo, $id){ // Conjunto de uma peça ($tipo "item" ou "drop"), ou false.
	foreach (conjuntoslista() as $c) { foreach ($c["lista"] as $p) { if ($p[0] == $tipo && $p[1] == intval($id)) { return $c; } } }
	return false;
}}

if (!function_exists('conjuntopecaequipada')){
function conjuntopecaequipada($row, $peca){ // A peça array(tipo, id) está equipada em $row?
	if (!is_array($row)) { return false; }
	$campos = ($peca[0] == "item") ? array("weapon", "armor", "shield") : array("slot1", "slot2", "slot3", "slot4");
	foreach ($campos as $c) { if (isset($row[$c."id"]) && intval($row[$c."id"]) == $peca[1]) { return true; } }
	return false;
}}

if (!function_exists('conjuntoquantas')){
function conjuntoquantas($row, $conjunto){ // Quantas peças diferentes do conjunto estão equipadas.
	$n = 0;
	foreach ($conjunto["lista"] as $p) { if (conjuntopecaequipada($row, $p)) { $n++; } }
	return $n;
}}

if (!function_exists('conjuntosbonusativo')){
function conjuntosbonusativo($row){ // Bônus "coluna,valor" de todos os conjuntos ativos em $row (só colunas de atributovalido()).
	$bonus = array();
	foreach (conjuntoslista() as $c) {
		$n = conjuntoquantas($row, $c);
		$textos = array();
		if ($n >= 2) { $textos[] = $c["bonus2"]; }
		if ($n >= 4) { $textos[] = $c["bonus4"]; }
		foreach ($textos as $t) {
			foreach (explode(";", (string)$t) as $b) {
				$p = explode(",", trim($b));
				if (count($p) == 2 && atributovalido(trim($p[0])) && intval($p[1]) != 0) { $bonus[] = trim($p[0]).",".intval($p[1]); }
			}
		}
	}
	return $bonus;
}}

if (!function_exists('conjuntosrecalcular')){
function conjuntosrecalcular(&$row){ // Troca em $row o bônus de conjuntos guardado (conjuntobonus) pelo das peças equipadas agora. Idempotente.
	if (!is_array($row) || !array_key_exists("conjuntobonus", $row)) { return; } // migração ainda não aplicada.
	$delta = array();
	foreach (explode(";", (string)$row["conjuntobonus"]) as $b) { // retira o antigo...
		$p = explode(",", trim($b));
		if (count($p) == 2 && atributovalido($p[0])) { $delta[$p[0]] = (isset($delta[$p[0]]) ? $delta[$p[0]] : 0) - intval($p[1]); }
	}
	$novo = conjuntosbonusativo($row);
	foreach ($novo as $b) { $p = explode(",", $b); $delta[$p[0]] = (isset($delta[$p[0]]) ? $delta[$p[0]] : 0) + intval($p[1]); } // ...e soma o novo.
	// Força e Destreza também mexem no Poder de Ataque e no Poder de Defesa (igual a aplicaratributo()).
	if (isset($delta["strength"])) { $delta["attackpower"] = (isset($delta["attackpower"]) ? $delta["attackpower"] : 0) + $delta["strength"]; }
	if (isset($delta["dexterity"])) { $delta["defensepower"] = (isset($delta["defensepower"]) ? $delta["defensepower"] : 0) + $delta["dexterity"]; }
	foreach ($delta as $c => $v) {
		if ($v == 0 || !array_key_exists($c, $row)) { continue; }
		$row[$c] = intval($row[$c]) + $v;
		// Só bônus de Ryou/Exp. podem ser negativos; os demais nunca ficam abaixo de zero (nem o HP máximo abaixo de 1).
		if ($c != "expbonus" && $c != "goldbonus" && $row[$c] < 0) { $row[$c] = 0; }
		if ($c == "maxhp" && $row[$c] < 1) { $row[$c] = 1; }
	}
	foreach (array("hp","mp","tp","np","ep") as $p) {
		if (isset($row["current".$p], $row["max".$p]) && intval($row["current".$p]) > intval($row["max".$p])) { $row["current".$p] = max(0, intval($row["max".$p])); }
	}
	$row["conjuntobonus"] = implode(";", $novo);
}}

if (!function_exists('sqlconjuntobonus')){
function sqlconjuntobonus($row){ // "conjuntobonus='...'," para os UPDATEs que regravam os atributos ("" sem a coluna).
	return array_key_exists("conjuntobonus", $row) ? "conjuntobonus='".addslashes((string)$row["conjuntobonus"])."'," : "";
}}

if (!function_exists('nomeconjunto')){
function nomeconjunto($c){ return (idioma() != "pt" && trim((string)$c["nome_en"]) != "") ? $c["nome_en"] : $c["nome"]; }
}

if (!function_exists('explicarconjunto')){
function explicarconjunto($tipo, $iditem){ // Linhas do tooltip com o conjunto da peça: nome, peças (equipadas em verde) e bônus 2/4 (ativos em verde).
	global $userrow;
	$c = conjuntodapeca($tipo, $iditem);
	if ($c === false) { return ""; }
	$n = conjuntoquantas($userrow, $c);
	$texto = "<br><font color=#6a3d00><b>◆ ".tf("Conjunto: %s", htmlspecialchars(nomeconjunto($c)))."</b> (".$n."/".count($c["lista"]).")</font>";
	foreach ($c["lista"] as $p) {
		$r = mysqli_fetch_array(doquery("SELECT name FROM {{table}} WHERE id='".intval($p[1])."' LIMIT 1", $p[0] == "item" ? "items" : "drops"));
		if (!$r) { continue; }
		$nome = htmlspecialchars(tnomeitem($r["name"]));
		$texto .= "<br>".(conjuntopecaequipada($userrow, $p) ? "<font color=green>✔ ".$nome."</font>" : "<font color=gray>· ".$nome."</font>");
	}
	foreach (array(2 => "bonus2", 4 => "bonus4") as $qtd => $col) {
		$linhas = array();
		foreach (explode(";", (string)$c[$col]) as $b) { $p = explode(",", trim($b)); if (count($p) == 2 && atributovalido($p[0])) { $nm = nomedoatributo($p[0]); $linhas[] = t($nm[0])." +".intval($p[1]).$nm[1]; } }
		if (!count($linhas)) { continue; }
		$cor = ($n >= $qtd) ? "green" : "gray";
		$texto .= "<br><font color=".$cor.">".tf("(%s peças) %s", $qtd, implode(", ", $linhas))."</font>";
	}
	return $texto;
}}

/* Comparação com o equipado (tooltip de item da mochila, da loja e do drop): diferença atributo a atributo
   entre o item e o que está no mesmo espaço. Acessório: com um espaço livre compara com "nada" (é ali que ele
   entra); com os 4 cheios, compara com o acessório 4, que é o que sai da mochila ao equipar (backpack.php
   empurra slot1 -> slot4 e o 4º volta para a mochila). */

if (!function_exists('vetoratributositem')){
function vetoratributositem($tipo, $id, $nome){ // Atributos que o item soma no jogador: array(coluna => valor). $tipo 1-3 = items, 4+ = drops.
	global $userrow;
	$guardado = $userrow;
	$zero = array();
	foreach (array("maxhp","maxmp","maxtp","maxnp","maxep","attackpower","defensepower","strength","dexterity","expbonus","goldbonus","sorte","agilidade",
		"determinacao","precisao","inteligencia","droprate","critico","esquiva","regenhp","regenmp","regentp","regennp","regenep","economiachakra") as $c) { $zero[$c] = 0; }
	$userrow = $zero;
	if (intval($id) > 0) {
		if (intval($tipo) <= 3) { bonusdoitem($id, $nome, 1); } else { bonusdodrop($id, 1, $nome); }
	}
	$v = $userrow;
	$userrow = $guardado;
	return $v;
}}

if (!function_exists('compararcomequipado')){
function compararcomequipado($tipo, $id, $nome){ // Linhas "▲/▼ atributo" do tooltip, ou "".
	global $userrow;
	if (!is_array($userrow) || !isset($userrow["weaponid"]) || intval($id) <= 0) { return ""; }
	$tipo = intval($tipo);
	if ($tipo >= 1 && $tipo <= 3) {
		$tiporeal = intval(mysqli_fetch_array(doquery("SELECT type FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "items"))["type"] ?? 0);
		if ($tiporeal < 1 || $tiporeal > 3) { return ""; }
		$slots = slotsequipamento(); $campo = $slots[$tiporeal];
		$rotulo = t("Comparado com o equipado");
	} elseif ($tipo >= 4 && $tipo <= 6) {
		$campo = "slot".NUMACESSORIOS;
		for ($s = 1; $s <= NUMACESSORIOS; $s++) { if (intval($userrow["slot".$s."id"]) == 0) { $campo = ""; break; } }
		$rotulo = ($campo == "") ? t("Comparado com o equipado (acessório livre)") : t("Comparado com o acessório 4 (o que sai ao equipar)");
	} else { return ""; }
	$novo = vetoratributositem($tipo, $id, $nome);
	$atual = ($campo == "" || (string)$userrow[$campo."name"] == "None") ? vetoratributositem($tipo, 0, "") : vetoratributositem($tipo, $userrow[$campo."id"], $userrow[$campo."name"]);
	$linhas = array();
	foreach ($novo as $c => $v) {
		$d = intval($v) - intval($atual[$c]);
		if ($d == 0) { continue; }
		$nm = nomedoatributo($c);
		$linhas[] = ($d > 0) ? "<font color=green>▲ ".t($nm[0]).": +".$d.$nm[1]."</font>" : "<font color=red>▼ ".t($nm[0]).": ".$d.$nm[1]."</font>";
	}
	if (!count($linhas)) { $linhas[] = "<font color=gray>".t("Sem diferença de atributos")."</font>"; }
	return "<br><span class=comparacao><b>".$rotulo.":</b><br>".implode("<br>", $linhas)."</span>";
}}

// A antiga função browser() (detecção do Internet Explorer) foi removida: nenhum arquivo a usa mais.

// Jutsus configuráveis (efeitos por turnos, recarga, requisitos, pergaminhos): aplicarjutsu(), jutsufimdoturno() etc.
include_once(__DIR__.'/funcoesjutsus.php');

?>
