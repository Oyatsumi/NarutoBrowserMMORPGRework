<?php // funcoesjutsus.php :: Regras dos jutsus configuráveis (efeitos por turnos, multigolpe, roubo de vida, escudo, buffs/debuffs, recarga, requisitos e pergaminhos). Incluído pelo funcoesinclusas.php.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }

/* ------------------------------------------------------------------------
   Jutsus configuráveis (tabela spells).

   Colunas antigas (continuam valendo como sempre):
     type  1 = cura, 2 = dano, 3 = sono, 4 = +% de dano até o fim da luta, 5 = +% de defesa até o fim da luta,
           6 = suporte (novo: não tem efeito principal, só o efeito extra abaixo).
     attribute = poder do efeito principal; mp = custo de chakra; elemento = neutro/fogo/agua/terra/vento/raio.
   Colunas novas (todas opcionais; com os valores padrão o jutsu funciona exatamente como antes):
     golpes (1-4) ........ jutsu de dano dá N golpes, cada um com 5/6 a 100% do poder.
     roubovida (0-100) ... % do dano causado que volta como HP para quem usou.
     efeito .............. efeito extra (lista em jutsuefeitos()), com efeito_valor, efeito_turnos e
                           efeito_chance (% de o efeito pegar).
     cooldown ............ turnos de recarga depois de usar (0 = pode usar todo turno).
     nivelminimo, classe (0 = qualquer, 1 Ninjutsu, 2 Taijutsu, 3 Genjutsu), graduacao ("" ou Genin/Chuunin/Jounin)
                           = requisitos para APRENDER (pergaminho). O aprendizado por nível segue a tabela levels.
     name_en, descricao, descricao_en ... textos (traduzidos pelo lang/en/conteudo_jutsus_banco.php).
   drops.ensina_jutsu = id do jutsu que o pergaminho ensina (usado pela mochila, backpack.php).

   Estado da luta (users.jutsuestado, JSON, apagado no fim da luta):
     {"efeitos": {lado: {efeito: {"v": valor, "t": turnos restantes, "n": "nome do jutsu"}}},
      "cd": {lado: {id do jutsu: turnos de recarga}}}
   "lado" é qualquer texto que identifique o combatente: no PvE "jog" (jogador) e "ini" (inimigo);
   num duelo pode ser o id de cada jogador.

   Ordem de um turno (rodada): ação de quem age -> turno do adversário -> jutsufimdoturno()
   (dano/cura por turno, contagem dos efeitos e das recargas). Por isso a recarga é gravada como cooldown + 1:
   um jutsu com recarga 2 fica bloqueado nas 2 rodadas seguintes.

   Todas as mensagens saem no formato neutro i18n() ("i18n:Chave|valor"): o PvE mostra com textoi18n()
   e o duelo pode gravar direto no histórico.
   ------------------------------------------------------------------------ */

if (!function_exists('jutsuefeitos')){
function jutsuefeitos(){ // Efeitos extras possíveis: código => rótulo (pt), em quem cai, ícone (images/status/*.png) e texto da descrição.
	// "alvo": "proprio" = quem usou o jutsu, "inimigo" = o alvo, "instantaneo" = acontece na hora (sem turnos).
	return array(
		"queimadura"    => array("rotulo"=>"Queimadura",            "alvo"=>"inimigo",     "icone"=>"queimadura", "texto"=>"Queimadura: %s de dano por turno durante %s turnos"),
		"veneno"        => array("rotulo"=>"Veneno",                "alvo"=>"inimigo",     "icone"=>"veneno",     "texto"=>"Veneno: %s de dano por turno durante %s turnos"),
		"atordoar"      => array("rotulo"=>"Atordoamento",          "alvo"=>"inimigo",     "icone"=>"atordoado",  "texto"=>"Atordoa o inimigo (perde o ataque) por %2\$s turno(s)"),
		"escudo"        => array("rotulo"=>"Escudo",                "alvo"=>"proprio",     "icone"=>"escudo",     "texto"=>"Escudo que absorve até %s de dano por %s turnos"),
		"buff_ataque"   => array("rotulo"=>"Ataque reforçado",      "alvo"=>"proprio",     "icone"=>"buff",       "texto"=>"+%s%% de dano (ataques e jutsus) por %s turnos"),
		"buff_defesa"   => array("rotulo"=>"Defesa reforçada",      "alvo"=>"proprio",     "icone"=>"buff",       "texto"=>"-%s%% de dano recebido por %s turnos"),
		"buff_critico"  => array("rotulo"=>"Foco crítico",          "alvo"=>"proprio",     "icone"=>"buff",       "texto"=>"+%s%% de Chance de Crítico por %s turnos"),
		"buff_esquiva"  => array("rotulo"=>"Esquiva aumentada",     "alvo"=>"proprio",     "icone"=>"buff",       "texto"=>"+%s%% de Esquiva por %s turnos"),
		"debuff_ataque" => array("rotulo"=>"Ataque enfraquecido",   "alvo"=>"inimigo",     "icone"=>"debuff",     "texto"=>"O inimigo causa -%s%% de dano por %s turnos"),
		"debuff_defesa" => array("rotulo"=>"Defesa quebrada",       "alvo"=>"inimigo",     "icone"=>"debuff",     "texto"=>"O inimigo recebe +%s%% de dano por %s turnos"),
		"cura_turnos"   => array("rotulo"=>"Regeneração",           "alvo"=>"proprio",     "icone"=>"regeneracao","texto"=>"Recupera %s de HP por turno durante %s turnos"),
		"dreno_chakra"  => array("rotulo"=>"Dreno de Chakra",       "alvo"=>"instantaneo", "icone"=>"",           "texto"=>"Rouba %s de Chakra do alvo"),
	);
}}

if (!function_exists('jutsutipos')){
function jutsutipos(){ // Tipos de jutsu (spells.type) => nome curto (pt).
	return array(1=>"Medicinal", 2=>"Dano", 3=>"Sono", 4=>"+Dano (%)", 5=>"+Defesa (%)", 6=>"Suporte", 7=>"Taijutsu");
}}

if (!function_exists('jutsugraduacoes')){
function jutsugraduacoes(){ // Graduações em ordem (o requisito vale para a graduação pedida e as seguintes).
	return array("Estudante da Academia", "Genin", "Chuunin", "Jounin");
}}

if (!function_exists('jutsuelementos')){
function jutsuelementos(){ // Elementos (o banco guarda sem acento) => nome exibido (pt).
	return array("neutro"=>"Neutro", "fogo"=>"Fogo", "agua"=>"Água", "terra"=>"Terra", "vento"=>"Vento", "raio"=>"Raio");
}}

if (!function_exists('jutsuestadoler')){
function jutsuestadoler($texto){ // users.jutsuestado (JSON) -> array no formato descrito acima (sempre com "efeitos" e "cd").
	$e = json_decode((string)$texto, true);
	if (!is_array($e)) { $e = array(); }
	if (!isset($e["efeitos"]) || !is_array($e["efeitos"])) { $e["efeitos"] = array(); }
	if (!isset($e["cd"]) || !is_array($e["cd"])) { $e["cd"] = array(); }
	return $e;
}}

if (!function_exists('jutsuestadotexto')){
function jutsuestadotexto($estado){ // Caminho inverso (vazio quando não há nada ativo, para não guardar lixo no banco).
	if (empty($estado["efeitos"]) && empty($estado["cd"])) { return ""; }
	return json_encode($estado, JSON_UNESCAPED_UNICODE);
}}

if (!function_exists('jutsubonus')){
function jutsubonus($estado, $lado, $efeito){ // Valor do efeito ativo em um lado (0 se não houver).
	return isset($estado["efeitos"][$lado][$efeito]) ? intval($estado["efeitos"][$lado][$efeito]["v"]) : 0;
}}

/* ------------------------------------------------------------------------
   Especializações (users.charclass: 0 = ainda sem especialização, 1 Ninjutsu, 2 Taijutsu, 3 Genjutsu).
   Os modificadores de cada classe ficam na tabela classes (editável no Painel do Administrador > Especializações);
   veja docs/ESPECIALIZACOES.md. Todos são % (duracao_efeito em turnos), positivos ou negativos:
     dano_fisico, critico (pontos de %), dano_jutsu, dano_elemental, vantagem_elemento, defesa_fisica,
     chance_efeito, duracao_efeito, valor_efeito, dreno, resist_debuff, economia_tecnica.
   Senjutsu ativo soma as colunas sj_* da classe (ataque/defesa em % do Poder de Ataque/Defesa, dano físico, crítico,
   dano elemental, economia de chakra, chance/duração de efeitos, resistência). Sem classe = bônus antigo (+20%/+20%).
   ------------------------------------------------------------------------ */
if (!function_exists('classecampos')){
function classecampos(){ // Colunas dos modificadores de classe (tabela classes) e do bônus de Senjutsu por classe.
	return array("dano_fisico", "critico", "dano_jutsu", "dano_elemental", "vantagem_elemento", "defesa_fisica", "chance_efeito",
		"duracao_efeito", "valor_efeito", "dreno", "resist_debuff", "economia_tecnica", "buff_proprio");
}}
if (!function_exists('classecampossenjutsu')){
function classecampossenjutsu(){ // Colunas sj_* (bônus do Senjutsu ativo de cada classe).
	return array("sj_ataque", "sj_defesa", "sj_dano_fisico", "sj_critico", "sj_dano_elemental", "sj_economia", "sj_chance_efeito", "sj_duracao_efeito", "sj_resist_debuff");
}}

if (!function_exists('senjutsufator')){
function senjutsufator($nivel){ // Multiplicador do bônus do Senjutsu pelo nível: 0,6 no nível 1, 0,9 no 50 e 1,2 no 100 (continua abaixo dos olhos mais fortes).
	return 0.6 + 0.006 * min(100, max(1, intval($nivel)));
}}

if (!function_exists('classemods')){
function classemods($classe, $senjutsu = false, $nivel = 50){ // Modificadores da classe (0 em tudo para "sem classe"); com $senjutsu soma o bônus de Senjutsu, que cresce com o nível (senjutsufator()).
	static $cache = null;
	if ($cache === null) {
		$cache = array();
		$q = @doquery("SELECT * FROM {{table}}", "classes");
		if ($q) { while ($r = mysqli_fetch_array($q)) { $cache[intval($r["id"])] = $r; } }
	}
	$classe = intval($classe);
	$m = array("ataque" => 0, "defesa" => 0, "economia" => 0);
	foreach (classecampos() as $c) { $m[$c] = isset($cache[$classe][$c]) ? intval($cache[$classe][$c]) : 0; }
	if ($senjutsu) {
		$f = senjutsufator($nivel);
		if (!isset($cache[$classe])) { $m["ataque"] = (int)round(20 * $f); $m["defesa"] = (int)round(20 * $f); return $m; } // sem especialização: 12% (nível 1) a 24% (nível 100).
		// Valores da tabela escalam de 60% (nível 1) a 120% (nível 100) deles. A duração dos efeitos (turnos) não escala.
		$sj = function ($c) use ($cache, $classe, $f) { $v = isset($cache[$classe][$c]) ? intval($cache[$classe][$c]) : 0; return ($c == "sj_duracao_efeito") ? $v : (int)round($v * $f); };
		$m["ataque"] += $sj("sj_ataque"); $m["defesa"] += $sj("sj_defesa"); $m["economia"] += $sj("sj_economia");
		$m["dano_fisico"] += $sj("sj_dano_fisico"); $m["critico"] += $sj("sj_critico"); $m["dano_elemental"] += $sj("sj_dano_elemental");
		$m["chance_efeito"] += $sj("sj_chance_efeito"); $m["duracao_efeito"] += $sj("sj_duracao_efeito"); $m["resist_debuff"] += $sj("sj_resist_debuff");
	}
	return $m;
}}

if (!function_exists('classedojogador')){
function classedojogador($row){ // Modificadores de um jogador (linha de users), já com o Senjutsu e o doujutsu se estiverem ativos agora.
	$m = classemods(intval($row["charclass"]), function_exists('senjutsuativo') && senjutsuativo($row), intval(isset($row["level"]) ? $row["level"] : 50));
	// Doujutsu ativo (funcoesdoujutsu.php): % de ataque/defesa e crítico entram direto; o resto vai em "dj_*"
	// (agilidade, inteligência, sorte, precisão, esquiva, ignora_defesa, droprate...), lido por fight.php e dueloacao().
	include_once(__DIR__.'/funcoesdoujutsu.php');
	$d = (is_array($row) && isset($row["doujutsu"])) ? doujutsumods($row) : doujutsumods(array("doujutsu" => ""));
	$m["ataque"] += $d["ataque"]; $m["defesa"] += $d["defesa"]; $m["critico"] += $d["critico"];
	foreach ($d as $c => $v) { $m["dj_".$c] = $v; }
	// Talentos escolhidos (funcoestalentos.php): somados aqui para valer em PvE, PvP e nos jutsus.
	include_once(__DIR__.'/funcoestalentos.php');
	foreach (talentosmods(is_array($row) ? $row : array()) as $c => $v) { $m[$c] = (isset($m[$c]) ? $m[$c] : 0) + $v; }
	return $m;
}}

if (!function_exists('classeataquedefesa')){
function classeataquedefesa($row){ // array(Poder de Ataque, Poder de Defesa) usados no combate (com o bônus do Senjutsu ativo, calculado na hora).
	$m = classedojogador($row);
	return array((int)floor(intval($row["attackpower"]) * (100 + $m["ataque"]) / 100), (int)floor(intval($row["defensepower"]) * (100 + $m["defesa"]) / 100));
}}

if (!function_exists('classemodlado')){
function classemodlado($combatente, $campo){ // Valor de um modificador para um combatente de aplicarjutsu() (chave "mods"; inimigos não têm).
	return (isset($combatente["mods"]) && isset($combatente["mods"][$campo])) ? intval($combatente["mods"][$campo]) : 0;
}}

if (!function_exists('jutsuatordoado')){
function jutsuatordoado($estado, $lado){ // O lado está atordoado (perde o próximo ataque)?
	return isset($estado["efeitos"][$lado]["atordoar"]) && intval($estado["efeitos"][$lado]["atordoar"]["t"]) > 0;
}}

if (!function_exists('jutsumultdano')){
function jutsumultdano($estado, $ladoatacante, $ladodefensor){ // Multiplicador de dano dos buffs de ataque (atacante) e da defesa quebrada (defensor).
	return (100 + jutsubonus($estado, $ladoatacante, "buff_ataque") + jutsubonus($estado, $ladodefensor, "debuff_defesa")) / 100;
}}

if (!function_exists('jutsureduzirdano')){
function jutsureduzirdano(&$estado, $ladoatacante, $ladodefensor, $dano){ // Dano recebido depois do ataque enfraquecido, da defesa reforçada e do escudo. Devolve array(dano, mensagens i18n).
	$msgs = array();
	$dano = intval($dano);
	if ($dano <= 0) { return array(0, $msgs); }
	$reducao = jutsubonus($estado, $ladoatacante, "debuff_ataque") + jutsubonus($estado, $ladodefensor, "buff_defesa");
	if ($reducao > 0) { $dano = max(1, (int)ceil($dano * max(0, 100 - min(90, $reducao)) / 100)); } // no máximo 90% de redução.
	if (isset($estado["efeitos"][$ladodefensor]["escudo"])) {
		$escudo = intval($estado["efeitos"][$ladodefensor]["escudo"]["v"]);
		$absorvido = min($escudo, $dano);
		$dano -= $absorvido;
		$escudo -= $absorvido;
		if ($escudo <= 0) { unset($estado["efeitos"][$ladodefensor]["escudo"]); $msgs[] = i18n("O escudo absorveu %s de dano e se desfez.", $absorvido); }
		else { $estado["efeitos"][$ladodefensor]["escudo"]["v"] = $escudo; $msgs[] = i18n("O escudo absorveu %s de dano (restam %s).", $absorvido, $escudo); }
	}
	return array($dano, $msgs);
}}

if (!function_exists('jutsurecarga')){
function jutsurecarga($estado, $lado, $idjutsu){ // Turnos que ainda faltam para poder usar o jutsu de novo (0 = pronto).
	return isset($estado["cd"][$lado][$idjutsu]) ? max(0, intval($estado["cd"][$lado][$idjutsu])) : 0;
}}

if (!function_exists('jutsurequisitos')){
function jutsurequisitos($spell, $jogador){ // Erro (traduzido) se o jogador não pode APRENDER o jutsu (nível, classe, graduação), ou "".
	if (intval($spell["nivelminimo"]) > intval($jogador["level"])) { return tf("Você precisa ser nível %s para aprender %s.", intval($spell["nivelminimo"]), t($spell["name"])); }
	if (intval($spell["classe"]) >= 1 && intval($spell["classe"]) <= 3 && intval($spell["classe"]) != intval($jogador["charclass"])) {
		return tf("%s só pode ser aprendido por ninjas de %s.", t($spell["name"]), jutsunomeclasse($spell["classe"]));
	}
	if (trim((string)$spell["graduacao"]) != "") {
		$ordem = jutsugraduacoes();
		$precisa = array_search($spell["graduacao"], $ordem);
		$tem = array_search((string)$jogador["graduacao"], $ordem);
		if ($precisa !== false && ($tem === false || $tem < $precisa)) { return tf("Você precisa ser %s para aprender %s.", t($spell["graduacao"]), t($spell["name"])); }
	}
	return "";
}}

if (!function_exists('jutsunomeclasse')){
function jutsunomeclasse($n){ // nomedaclasse() lendo a tabela control se a página ainda não leu (backpack.php, por exemplo).
	global $controlrow;
	if (!is_array($controlrow)) { $controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control")); }
	return nomedaclasse($n);
}}

if (!function_exists('jutsutextorequisitos')){
function jutsutextorequisitos($spell){ // "Requisitos: nível 30, Taijutsu, Genin<br>" (traduzido) ou "" se não houver requisito.
	$partes = array();
	if (intval($spell["nivelminimo"]) > 0) { $partes[] = tf("nível %s", intval($spell["nivelminimo"])); }
	if (intval($spell["classe"]) >= 1 && intval($spell["classe"]) <= 3) { $partes[] = jutsunomeclasse($spell["classe"]); }
	if (trim((string)$spell["graduacao"]) != "") { $partes[] = t($spell["graduacao"]); }
	return count($partes) ? tf("Requisitos: %s", implode(", ", $partes))."<br>" : "";
}}

if (!function_exists('jutsuconhece')){
function jutsuconhece($jogador, $idjutsu){ // O jogador já aprendeu o jutsu? (users.spells = "0,1,4,...")
	return in_array((string)intval($idjutsu), explode(",", (string)$jogador["spells"]), true);
}}

if (!function_exists('jutsuaprender')){
function jutsuaprender($idjogador, $idjutsu){ // Acrescenta o jutsu na lista do jogador (sem repetir). Devolve true se gravou.
	global $link;
	$idjutsu = intval($idjutsu);
	// FIND_IN_SET no WHERE: dois cliques ao mesmo tempo não gravam o jutsu duas vezes.
	doquery("UPDATE {{table}} SET spells=CONCAT(spells, ',', '$idjutsu') WHERE id='".intval($idjogador)."' AND NOT FIND_IN_SET('$idjutsu', spells) AND CHAR_LENGTH(spells) < 250 LIMIT 1", "users");
	return mysqli_affected_rows($link) == 1;
}}

if (!function_exists('jutsudescricaoefeitos')){
function jutsudescricaoefeitos($spell, $comrecarga = true){ // Lista (já traduzida) do que o jutsu faz: efeito principal, golpes, roubo de vida, efeito extra e recarga ($comrecarga = false quando a recarga tem coluna própria).
	$linhas = array();
	$valor = intval($spell["attribute"]);
	$tipo = intval($spell["type"]);
	$golpes = max(1, min(4, intval(isset($spell["golpes"]) ? $spell["golpes"] : 1)));
	if ($tipo == 1) { $linhas[] = tf("Recupera %s de HP", $valor); }
	elseif ($tipo == 2) { $linhas[] = ($golpes > 1) ? tf("%s golpes de até %s de dano cada", $golpes, $valor) : tf("Causa até %s de dano", $valor); }
	elseif ($tipo == 7) { $linhas[] = ($golpes > 1) ? tf("%s golpes de ataque físico com +%s%% de dano cada", $golpes, $valor) : tf("Ataque físico com +%s%% de dano", $valor); $linhas[] = t("Taijutsu: o dano sai do seu Poder de Ataque, sem elemento; só a imunidade a Taijutsu bloqueia"); }
	elseif ($tipo == 3) { $linhas[] = tf("Sono: o inimigo dorme (%s em 15 de continuar dormindo)", $valor); }
	elseif ($tipo == 4) { $linhas[] = tf("+%s%% de dano até o fim da luta", $valor); }
	elseif ($tipo == 5) { $linhas[] = tf("-%s%% de dano recebido até o fim da luta", $valor); }
	if (isset($spell["roubovida"]) && intval($spell["roubovida"]) > 0) { $linhas[] = tf("Rouba %s%% do dano como HP", intval($spell["roubovida"])); }
	$efeitos = jutsuefeitos();
	if (isset($spell["efeito"]) && isset($efeitos[$spell["efeito"]])) {
		$texto = tf($efeitos[$spell["efeito"]]["texto"], intval($spell["efeito_valor"]), intval($spell["efeito_turnos"]));
		if (intval($spell["efeito_chance"]) < 100) { $texto = tf("%s%% de chance: %s", intval($spell["efeito_chance"]), $texto); }
		$linhas[] = $texto;
	}
	if ($comrecarga && isset($spell["cooldown"]) && intval($spell["cooldown"]) > 0) { $linhas[] = tf("Recarga: %s turno(s)", intval($spell["cooldown"])); }
	return $linhas;
}}

if (!function_exists('jutsuaplicarefeito')){
function jutsuaplicarefeito(&$caster, &$alvo, $spell, &$estado, &$msgs){ // Aplica só o efeito extra do jutsu (rolando a chance). Usado por aplicarjutsu() e pelos tipos antigos 3-5.
	$efeitos = jutsuefeitos();
	$ef = isset($spell["efeito"]) ? (string)$spell["efeito"] : "";
	if (!isset($efeitos[$ef])) { return; }
	$valor = intval($spell["efeito_valor"]);
	$turnos = max(1, intval($spell["efeito_turnos"]));
	$chance = max(0, min(100, intval($spell["efeito_chance"])));
	// Imunidades do inimigo: Ninjutsu (ou o elemento do jutsu) bloqueia o dano por turno; Sono bloqueia o atordoar.
	$imunes = imunidadesdoalvo($alvo);
	$imunedano = (imunejutsu($alvo, isset($spell["elemento"]) ? $spell["elemento"] : "") != "");
	// Especializações: quem usa aumenta chance/duração/valor dos efeitos no alvo e o dreno; o alvo resiste (ou sofre mais).
	if ($efeitos[$ef]["alvo"] != "proprio") {
		if ($chance < 100) { $chance = min(100, $chance + classemodlado($caster, "chance_efeito")); }
		if (in_array($ef, array("queimadura", "veneno", "debuff_ataque", "debuff_defesa"))) {
			$turnos = max(1, $turnos + classemodlado($caster, "duracao_efeito"));
			$valor = max(0, (int)ceil($valor * (100 + classemodlado($caster, "valor_efeito")) / 100));
		}
		if ($ef == "dreno_chakra") { $valor = max(0, (int)ceil($valor * (100 + classemodlado($caster, "dreno")) / 100)); }
	} elseif (in_array($ef, array("escudo", "buff", "regeneracao"))) { // Reforços em si mesmo: Genjutsu (buff_proprio) deixa mais fortes.
		$valor = max(0, (int)ceil($valor * (100 + classemodlado($caster, "buff_proprio")) / 100));
		$resist = classemodlado($alvo, "resist_debuff");
		if ($resist > 0) { $chance = intdiv($chance * max(0, 100 - min(100, $resist)), 100); }
		elseif ($resist < 0) {
			if ($ef == "atordoar") { $chance = min(100, intdiv($chance * (100 - $resist), 100)); }
			elseif ($ef != "dreno_chakra") { $valor = (int)ceil($valor * (100 - $resist) / 100); }
		}
	}
	if ($efeitos[$ef]["alvo"] != "proprio" && imunegenjutsu($alvo, $spell)) { $msgs[] = i18n("%s é imune a %s.", $alvo["nome"], "Genjutsu"); return; }
	if ($efeitos[$ef]["alvo"] != "proprio" && (($imunedano && ($ef == "queimadura" || $ef == "veneno")) || (in_array("sono", $imunes) && $ef == "atordoar"))) {
		$msgs[] = i18n("%s é imune a %s.", $alvo["nome"], $efeitos[$ef]["rotulo"]); return;
	}
	if (rand(1, 100) > $chance) { $msgs[] = i18n("%s não pegou desta vez.", $efeitos[$ef]["rotulo"]); return; }

	if ($ef == "dreno_chakra") { // na hora: tira chakra do alvo (inimigos não têm chakra: o valor inteiro vem da natureza do jutsu) e dá a quem usou.
		$tirado = isset($alvo["mp"]) && $alvo["mp"] !== null ? min($valor, max(0, intval($alvo["mp"]))) : $valor;
		if (isset($alvo["mp"]) && $alvo["mp"] !== null) { $alvo["mp"] = intval($alvo["mp"]) - $tirado; }
		$ganho = min($tirado, max(0, intval($caster["maxmp"]) - intval($caster["mp"])));
		$caster["mp"] = intval($caster["mp"]) + $ganho;
		$msgs[] = i18n("%s drenou %s de Chakra.", $caster["nome"], $ganho);
		return;
	}
	$lado = ($efeitos[$ef]["alvo"] == "proprio") ? $caster["lado"] : $alvo["lado"];
	$quem = ($efeitos[$ef]["alvo"] == "proprio") ? $caster["nome"] : $alvo["nome"];
	if ($ef == "atordoar" && jutsuatordoado($estado, $lado)) { $msgs[] = i18n("%s já está atordoado.", $quem); return; } // não renova: evita prender o inimigo para sempre.
	// O mesmo efeito não acumula: fica o mais forte (e renova a duração).
	if (isset($estado["efeitos"][$lado][$ef]) && intval($estado["efeitos"][$lado][$ef]["v"]) > $valor) { $valor = intval($estado["efeitos"][$lado][$ef]["v"]); }
	$estado["efeitos"][$lado][$ef] = array("v"=>$valor, "t"=>$turnos, "n"=>(string)$spell["name"]);
	$frases = array(
		"queimadura"=>"%s está em chamas (%s por turno, %s turnos).", "veneno"=>"%s foi envenenado (%s por turno, %s turnos).",
		"atordoar"=>"%s ficou atordoado por %3\$s turno(s).", "escudo"=>"%s ergueu um escudo de %s por %s turnos.",
		"buff_ataque"=>"%s ganhou +%s%% de dano por %s turnos.", "buff_defesa"=>"%s recebe -%s%% de dano por %s turnos.",
		"buff_critico"=>"%s ganhou +%s%% de Chance de Crítico por %s turnos.", "buff_esquiva"=>"%s ganhou +%s%% de Esquiva por %s turnos.",
		"debuff_ataque"=>"%s causa -%s%% de dano por %s turnos.", "debuff_defesa"=>"%s recebe +%s%% de dano por %s turnos.",
		"cura_turnos"=>"%s vai recuperar %s de HP por turno durante %s turnos.");
	$msgs[] = i18n($frases[$ef], $quem, $valor, $turnos);
}}

if (!function_exists('aplicarjutsu')){
function aplicarjutsu(&$caster, &$alvo, $spell, &$estado){ // Executa um jutsu (tipos 1, 2 e 6 completos; 3-5 só o efeito extra). Não cobra chakra.
	/* $caster e $alvo: arrays com "lado", "nome", "hp", "maxhp", "mp" (null = não tem chakra), "maxmp",
	   "elemento" (do alvo, para a vantagem de elemento), "imune" (0, 1 = dano, 2 = dano e sono; legado) ou "imunidades" (lista, imunidadesdoinimigo()) e, opcional,
	   "mods" (classedojogador(): modificadores da especialização; inimigos não têm).
	   hp/mp são alterados aqui. Devolve array("dano"=>total causado, "cura"=>HP recuperado, "msgs"=>[i18n...]).
	   A vantagem de elemento usa elementoganhador() com o bônus de atributo do jogador global ($userrow). */
	$res = array("dano"=>0, "cura"=>0, "msgs"=>array());
	$tipo = intval($spell["type"]);
	$valor = intval($spell["attribute"]);
	$nome = (string)$spell["name"];

	if ($tipo == 1) { // Cura.
		$ganho = min($valor, max(0, intval($caster["maxhp"]) - intval($caster["hp"])));
		$caster["hp"] = intval($caster["hp"]) + $ganho;
		$res["cura"] = $ganho;
		$res["msgs"][] = i18n("%s usou %s e recuperou %s HP.", $caster["nome"], $nome, $ganho);
	} elseif ($tipo == 2 || $tipo == 7) { // Dano: N golpes de 5/6 a 100% do poder, com elemento, buffs/debuffs e roubo de vida.
		// Tipo 7 = Taijutsu (golpe físico que gasta chakra): só a imunidade a Taijutsu bloqueia; sem elemento, sem EP e sem
		// o bônus da Inteligência, mas cada golpe é um ataque físico (Poder de Ataque "ap") com o reforço do attribute em %.
		// Sem EP o jutsu sai como Neutro: a imunidade ao elemento dele não se aplica (a imunidade a Ninjutsu, sim).
		$semepimune = (array_key_exists("ep", $caster) && $caster["ep"] === false);
		$imunea = ($tipo == 7) ? (in_array("taijutsu", imunidadesdoalvo($alvo)) ? "Taijutsu" : "") : imunejutsu($alvo, $semepimune ? "neutro" : (string)$spell["elemento"]);
		if ($imunea == "" && $tipo == 2 && imunegenjutsu($alvo, $spell)) { $imunea = "Genjutsu"; }
		if ($imunea != "") {
			$res["msgs"][] = i18n("%s usou %s, mas %s é imune a %s.", $caster["nome"], $nome, $alvo["nome"], $imunea);
		} else {
			$golpes = max(1, min(4, intval(isset($spell["golpes"]) ? $spell["golpes"] : 1)));
			$pontos = 0;
			// Elemento: só sai com elemento se o EP do jutsu foi pago (chave "ep" de jutsucobrarep(): false = sem EP).
			// Sem EP o jutsu causa dano normal, sem vantagem/desvantagem/resistência e sem bônus de elemento.
			global $controlrow;
			$elspell = (string)$spell["elemento"];
			$semep = (array_key_exists("ep", $caster) && $caster["ep"] === false);
			$mult = ($tipo == 7 || $semep) ? 1 : elementomultcombinado($elspell, isset($alvo["elemento"]) ? $alvo["elemento"] : "neutro", $pontos);
			if ($mult > 1 && isset($alvo["classe"]) && intval($alvo["classe"]) == 2) { $mult = 1; } // especialista em Taijutsu não tem fraqueza de elemento.
			if ($semep) { $pontos = 0; $avisoep = i18n("Sem EP: o jutsu saiu sem elemento (dano normal)."); }
			elseif (array_key_exists("ep", $caster) && $caster["ep"] === true) {
				// Bônus de elemento a partir dos pontos do atributo ligado ao elemento do jutsu (+1% a cada 20 pontos), contra
				// qualquer alvo; no elemento principal de quem usa contam em dobro e no secundário x1,5, mais os bônus fixos do painel.
				$meus = elementosdo(isset($caster["elementos"]) ? $caster["elementos"] : "");
				$atrib = isset($caster["elattr"]) ? $caster["elattr"] : array();
				$fatoratrib = 1;
				if ($elspell != "" && $elspell != "neutro") {
					if ($elspell == $meus[0]) { $fatoratrib = 2; $elprincipal = 1; }
					elseif ($elspell == $meus[1]) { $fatoratrib = 1.5; $elprincipal = 2; }
				}
				if (isset($caster["elattr"])) { $pontos = (int)floor(intval(isset($atrib[$elspell]) ? $atrib[$elspell] : 0) / 20 * $fatoratrib); } // sem a chave: regra antiga (só com vantagem/desvantagem).
				$pontos += isset($controlrow["epbonus"]) ? intval($controlrow["epbonus"]) : 15;
				if (isset($elprincipal)) { $pontos += ($elprincipal == 1) ? (isset($controlrow["elemento_bonus"]) ? intval($controlrow["elemento_bonus"]) : 20) : (isset($controlrow["elemento_bonus2"]) ? intval($controlrow["elemento_bonus2"]) : 10); }
			}
			$multjutsu = jutsumultdano($estado, $caster["lado"], $alvo["lado"]);
			// Especialização de quem usa: % em todo jutsu de dano, % a mais com elemento e % a mais na vantagem de elemento.
			$fatorclasse = 100 + classemodlado($caster, "dano_jutsu") + (((string)$spell["elemento"] != "neutro" && (string)$spell["elemento"] != "") ? classemodlado($caster, "dano_elemental") : 0);
			$fatorclasse += classemodlado($caster, ($tipo == 7) ? "tal_taijutsu" : "tal_ninjutsu"); // talentos do nível 20.
			if ($mult > 1.0001) { $fatorclasse = $fatorclasse * (100 + classemodlado($caster, "vantagem_elemento")) / 100; }
			if ($tipo == 2) { $fatorclasse = $fatorclasse * (100 + bonusinteligencia(isset($caster["int"]) ? $caster["int"] : 0)) / 100; } // Inteligência: +0,05% de dano de ninjutsu por ponto (máx. +50%).
			// Taijutsu (tipo 7): cada golpe é um ataque físico (mesma conta do Atacar: 75-100% do Poder de Ataque / 3)
			// com o reforço do jutsu em cima (attribute = +% de dano). Não tem elemento.
			$ap = max(1, intval(isset($caster["ap"]) ? $caster["ap"] : 0));
			$partes = array();
			for ($g = 1; $g <= $golpes; $g++) {
				$d = ($tipo == 7) ? (int)ceil(rand(intval($ap * 0.75), $ap) / 3 * (100 + $valor) / 100) : rand(intval(($valor/6)*5), $valor);
				$d = (int)ceil($d * $mult);
				$d += (int)ceil($d * $pontos/100);
				$d = (int)ceil($d * max(0, $fatorclasse) / 100);
				$d = (int)ceil($d * $multjutsu);
				$res["dano"] += $d;
				$partes[] = $d;
			}
			$alvo["hp"] = intval($alvo["hp"]) - $res["dano"];
			if ($golpes > 1) { $res["msgs"][] = i18n("%s usou %s: %s golpes (%s) = %s de dano.", $caster["nome"], $nome, $golpes, implode(" + ", $partes), $res["dano"]); }
			else { $res["msgs"][] = i18n("%s usou %s e causou %s de dano.", $caster["nome"], $nome, $res["dano"]); }
			if ($mult > 1.0001 || $mult < 0.9999) { // descreve vantagem, desvantagem ou resistência, com o elemento de cada lado e a porcentagem.
				$elsalvo = elementosdo(isset($alvo["elemento"]) ? $alvo["elemento"] : "");
				$res["msgs"][] = elementofrase($elspell, in_array($elspell, $elsalvo) ? $elspell : $elsalvo[0], $mult);
			}
			if (isset($elprincipal)) { $res["msgs"][] = ($elprincipal == 1) ? i18n("Bônus do elemento principal.") : i18n("Bônus do elemento secundário."); }
			if (isset($avisoep)) { $res["msgs"][] = $avisoep; }
			$roubo = max(0, min(100, intval(isset($spell["roubovida"]) ? $spell["roubovida"] : 0)));
			if ($roubo > 0 && $res["dano"] > 0) {
				$ganho = min((int)ceil($res["dano"] * $roubo / 100), max(0, intval($caster["maxhp"]) - intval($caster["hp"])));
				$caster["hp"] = intval($caster["hp"]) + $ganho;
				$res["cura"] += $ganho;
				$res["msgs"][] = i18n("%s roubou %s HP.", $caster["nome"], $ganho);
			}
		}
	} elseif ($tipo == 6) {
		$res["msgs"][] = i18n("%s usou %s.", $caster["nome"], $nome);
	}

	jutsuaplicarefeito($caster, $alvo, $spell, $estado, $res["msgs"]);
	if (intval(isset($spell["cooldown"]) ? $spell["cooldown"] : 0) > 0) { $estado["cd"][$caster["lado"]][intval($spell["id"])] = intval($spell["cooldown"]) + 1; }
	return $res;
}}

if (!function_exists('jutsufimdoturno')){
function jutsufimdoturno(&$estado, &$combatentes){ // Fim da rodada: dano/cura por turno, contagem dos efeitos e das recargas. $combatentes = array(lado => &array com "nome","hp","maxhp"). Devolve mensagens i18n.
	$msgs = array();
	foreach ($estado["efeitos"] as $lado => $lista) {
		foreach ($lista as $ef => $dados) {
			if (isset($combatentes[$lado])) {
				$c = &$combatentes[$lado];
				if (($ef == "queimadura" || $ef == "veneno") && intval($c["hp"]) > 0) {
					$c["hp"] = intval($c["hp"]) - intval($dados["v"]);
					$msgs[] = i18n(($ef == "queimadura") ? "%s sofreu %s de dano da queimadura." : "%s sofreu %s de dano do veneno.", $c["nome"], intval($dados["v"]));
				} elseif ($ef == "cura_turnos" && intval($c["hp"]) > 0) {
					$ganho = min(intval($dados["v"]), max(0, intval($c["maxhp"]) - intval($c["hp"])));
					$c["hp"] = intval($c["hp"]) + $ganho;
					if ($ganho > 0) { $msgs[] = i18n("%s regenerou %s HP.", $c["nome"], $ganho); }
				}
				unset($c);
			}
			$estado["efeitos"][$lado][$ef]["t"] = intval($dados["t"]) - 1;
			if ($estado["efeitos"][$lado][$ef]["t"] <= 0) { unset($estado["efeitos"][$lado][$ef]); }
		}
		if (empty($estado["efeitos"][$lado])) { unset($estado["efeitos"][$lado]); }
	}
	foreach ($estado["cd"] as $lado => $lista) {
		foreach ($lista as $id => $n) {
			if (intval($n) - 1 <= 0) { unset($estado["cd"][$lado][$id]); } else { $estado["cd"][$lado][$id] = intval($n) - 1; }
		}
		if (empty($estado["cd"][$lado])) { unset($estado["cd"][$lado]); }
	}
	return $msgs;
}}

if (!function_exists('jutsuicones')){
function jutsuicones($estado, $lado){ // Ícones 12x12 dos efeitos ativos de um lado (title = efeito e turnos restantes). "" se não houver nenhum.
	$html = "";
	$efeitos = jutsuefeitos();
	if (empty($estado["efeitos"][$lado])) { return ""; }
	foreach ($estado["efeitos"][$lado] as $ef => $d) {
		if (!isset($efeitos[$ef]) || $efeitos[$ef]["icone"] == "") { continue; }
		$titulo = ($ef == "escudo") ? tf("%s: %s restante(s), %s turno(s)", t($efeitos[$ef]["rotulo"]), intval($d["v"]), intval($d["t"]))
			: (($ef == "atordoar") ? tf("%s: %s turno(s)", t($efeitos[$ef]["rotulo"]), intval($d["t"])) : tf("%s %s: %s turno(s)", t($efeitos[$ef]["rotulo"]), intval($d["v"]), intval($d["t"])));
		$html .= "<img src=\"images/status/".$efeitos[$ef]["icone"].".png\" width=\"16\" height=\"16\" title=\"".htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8')."\" alt=\"".htmlspecialchars(t($efeitos[$ef]["rotulo"]), ENT_QUOTES, 'UTF-8')."\" style=\"margin-right:1px;vertical-align:middle\" />";
	}
	return $html;
}}

if (!function_exists('jutsumensagens')){
function jutsumensagens($msgs){ // Mensagens i18n -> HTML (uma por linha) no idioma de quem está vendo.
	$html = "";
	foreach ($msgs as $m) { $html .= textoi18n($m)."<br />"; }
	return $html;
}}

if (!function_exists('jutsupergaminhos')){
function jutsupergaminhos(){ // id do jutsu => lista de drops (pergaminhos) que o ensinam (cache da requisição).
	static $lista = null;
	if ($lista === null) {
		$lista = array();
		$q = doquery("SELECT id,name,ensina_jutsu FROM {{table}} WHERE ensina_jutsu>0", "drops");
		while ($r = mysqli_fetch_array($q)) { $lista[intval($r["ensina_jutsu"])][] = $r; }
	}
	return $lista;
}}

if (!function_exists('iconepergaminho')){
function iconepergaminho($idjutsu){ // Ícone 34x34 do pergaminho de jutsu na cor do elemento (layoutnovo/equipamentos/novos/pergaminho_ELEMENTO.gif).
	static $cache = array();
	$idjutsu = intval($idjutsu);
	if (!isset($cache[$idjutsu])) {
		$r = mysqli_fetch_array(doquery("SELECT elemento FROM {{table}} WHERE id='$idjutsu' LIMIT 1", "spells"));
		$el = ($r && isset(jutsuelementos()[$r["elemento"]])) ? $r["elemento"] : "neutro";
		$arquivo = "layoutnovo/equipamentos/novos/pergaminho_".$el.".gif";
		$cache[$idjutsu] = file_exists($arquivo) ? $arquivo : "layoutnovo/equipamentos/novos/pergaminho_selado.gif";
	}
	return $cache[$idjutsu];
}}


/* ------------------------------------------------------------------------
   Pontos Naturais (NP), Pontos de Elemento (EP) e jutsus especiais (colunas novas da tabela spells):
     uso ............. "batalha" (padrão: luta, duelo e PvP; os medicinais também aparecem na lista rápida)
                       ou "menu" (só na lista de jutsus da coluna da direita, fora de lutas).
     acao_menu ....... jutsu de menu: "" (cura, tipo 1), "suprimentos" (Invocação de Suprimentos) ou "passo" (Passo Veloz).
     requer_senjutsu . 1 = só pode ser usado com o Senjutsu ativo (senjutsuativo()).
     tier ............ nível do jutsu de menu: Suprimentos 1-4 (regras na tabela suprimentos); Passo Veloz 1-2 (casas extras por passo).
     custo_np ........ NP gastos a cada uso (além do Chakra). custo_tp: TP gastos a cada passo do Passo Veloz.
     recarga_min ..... recarga em minutos dos jutsus de menu (users.jutsumenu_cd = "id:horário unix,...").
   EP: cada jutsu de dano COM elemento gasta spells.custo_ep EP (0 = de graça). Com EP ele sai com o elemento: vantagem,
   desvantagem e resistência (elementomultcombinado()), bônus de atributo do elemento, +control.epbonus % e o bônus do
   elemento principal/secundário de quem usa. Sem EP o jutsu sai sem elemento: dano normal, sem nenhum desses.
   ------------------------------------------------------------------------ */

if (!function_exists('jutsuerroespecial')){
function jutsuerroespecial($spell, $row){ // Chave (pt, sem traduzir) do motivo de não poder usar o jutsu agora (Senjutsu/NP), ou "".
	if (intval(isset($spell["requer_senjutsu"]) ? $spell["requer_senjutsu"] : 0) == 1 && !senjutsuativo($row)) { return "Esse jutsu só pode ser usado com o Senjutsu ativo."; }
	$np = intval(isset($spell["custo_np"]) ? $spell["custo_np"] : 0);
	if ($np > 0) { $e = senjutsuestado($row); if ($e["np"] < $np) { return "Você não tem NP suficiente para usar esse jutsu."; } }
	return "";
}}

if (!function_exists('jutsucobrarnp')){
function jutsucobrarnp($idjogador, $spell){ // Desconta o custo_np do jutsu (atômico: só se ainda houver NP). Devolve true se pôde pagar (ou se não custa NP).
	global $link;
	$np = intval(isset($spell["custo_np"]) ? $spell["custo_np"] : 0);
	if ($np <= 0) { return true; }
	doquery("UPDATE {{table}} SET currentnp=currentnp-$np WHERE id='".intval($idjogador)."' AND currentnp>=$np LIMIT 1", "users");
	return mysqli_affected_rows($link) == 1;
}}

if (!function_exists('jutsucustoep')){
function jutsucustoep($spell){ // EP gasto pelo jutsu (spells.custo_ep, editável no admin). 0 em jutsus sem elemento ou que não são de dano.
	$el = (string)$spell["elemento"];
	if (intval($spell["type"]) != 2 || $el == "" || $el == "neutro") { return 0; }
	if (array_key_exists("custo_ep", $spell)) { return max(0, intval($spell["custo_ep"])); }
	global $controlrow; return isset($controlrow["epcusto"]) ? intval($controlrow["epcusto"]) : 1; // banco antigo sem a coluna.
}}

if (!function_exists('jutsucobrarep')){
function jutsucobrarep($idjogador, $spell){ // Jutsu de dano com elemento: gasta o custo de EP do jutsu (jutsucustoep()). Devolve true (pagou), false (sem EP) ou null (jutsu sem elemento/não é de dano).
	global $link, $controlrow;
	$el = (string)$spell["elemento"];
	if (intval($spell["type"]) != 2 || $el == "" || $el == "neutro") { return null; }
	$custo = jutsucustoep($spell);
	if ($custo <= 0) { return true; }
	doquery("UPDATE {{table}} SET currentep=currentep-$custo WHERE id='".intval($idjogador)."' AND currentep>=$custo LIMIT 1", "users");
	return mysqli_affected_rows($link) == 1;
}}

if (!function_exists('jutsurotulo')){
function jutsurotulo($spell, $row){ // Texto da opção do jutsu nas listas de luta/duelo: "Nome _ CH: 30 + NP: 20 [Senjutsu]".
	$txt = t($spell["name"])." _ CH: ".custochakra($spell["mp"], $spell);
	if (intval(isset($spell["custo_np"]) ? $spell["custo_np"] : 0) > 0) { $txt .= " + NP: ".intval($spell["custo_np"]); }
	if (jutsucustoep($spell) > 0) { $txt .= " + EP: ".jutsucustoep($spell); }
	if (intval(isset($spell["requer_senjutsu"]) ? $spell["requer_senjutsu"] : 0) == 1) { $txt .= " [".t("Senjutsu")."]"; }
	return $txt;
}}

if (!function_exists('jutsudisponivel')){
function jutsudisponivel($spell, $row){ // O jutsu aparece habilitado na lista de luta? (Senjutsu ativo e NP suficiente)
	return jutsuerroespecial($spell, $row) == "";
}}

if (!function_exists('jutsumenurecarga')){
function jutsumenurecarga($row, $idjutsu){ // Segundos que faltam para o jutsu de menu poder ser usado de novo (0 = pronto).
	foreach (explode(",", (string)$row["jutsumenu_cd"]) as $p) {
		$x = explode(":", $p);
		if (count($x) == 2 && intval($x[0]) == intval($idjutsu)) { return max(0, intval($x[1]) - time()); }
	}
	return 0;
}}

if (!function_exists('jutsumenunovarecarga')){
function jutsumenunovarecarga($row, $idjutsu, $minutos){ // Texto novo de users.jutsumenu_cd com a recarga deste jutsu (e sem as que já venceram).
	$lista = array();
	foreach (explode(",", (string)$row["jutsumenu_cd"]) as $p) {
		$x = explode(":", $p);
		if (count($x) == 2 && intval($x[0]) != intval($idjutsu) && intval($x[1]) > time()) { $lista[] = intval($x[0]).":".intval($x[1]); }
	}
	if ($minutos > 0) { $lista[] = intval($idjutsu).":".(time() + 60 * intval($minutos)); }
	return substr(implode(",", $lista), 0, 250);
}}

if (!function_exists('jutsudomenu')){
function jutsudomenu($spell){ // O jutsu aparece na lista rápida da coluna da direita? (medicinais de sempre + jutsus de uso "menu")
	return ((string)(isset($spell["uso"]) ? $spell["uso"] : "batalha") == "menu") || intval($spell["type"]) == 1;
}}

if (!function_exists('listarapidajutsus')){
function listarapidajutsus($row){ // HTML da lista rápida de jutsus (coluna da direita): link de cada jutsu de menu, com custo, recarga e estado do Passo Veloz.
	$html = "";
	$q = doquery("SELECT * FROM {{table}} ORDER BY id", "spells");
	$conhecidos = explode(",", (string)$row["spells"]);
	while ($s = mysqli_fetch_array($q)) {
		if (!in_array($s["id"], $conhecidos) || !jutsudomenu($s)) { continue; }
		$custos = array();
		if (intval($s["mp"]) > 0) { $custos[] = custochakra($s["mp"], $s)." CH"; }
		if (intval($s["custo_np"]) > 0) { $custos[] = intval($s["custo_np"])." NP"; }
		if (intval($s["custo_tp"]) > 0) { $custos[] = intval($s["custo_tp"])." TP"; }
		$titulo = implode(" | ", jutsudescricaoefeitos($s, false));
		if ((string)$s["acao_menu"] == "passo") { $titulo = tf("Anda %s casas por passo enquanto estiver ligado.", intval($s["tier"]) + 1)." ".tf("Custo por passo: %s", implode(" + ", $custos)); }
		elseif ((string)$s["acao_menu"] == "suprimentos") { $titulo = tf("Invoca um item aleatório (nível %s); pode falhar.", intval($s["tier"]))." ".tf("Custo: %s", implode(" + ", $custos)); }
		elseif (count($custos)) { $titulo .= " — ".tf("Custo: %s", implode(" + ", $custos)); }
		if (intval($s["requer_senjutsu"]) == 1) { $titulo .= " — ".t("Só com o Senjutsu ativo."); }
		list($rotulo, $icone, $cor) = jutsucategoria($s);
		$extra = "";
		$falta = jutsumenurecarga($row, $s["id"]);
		if ($falta > 0) { $extra = " · ".tf("%s min", (int)ceil($falta / 60)); }
		$ligado = ((string)$s["acao_menu"] == "passo" && intval($row["passo_jutsu"]) == intval($s["id"]));
		if ($ligado) { $extra = " · <b style=\"color:#0a6d0a\">".t("ligado")."</b>"; }
		if (intval($s["requer_senjutsu"]) == 1) { $extra .= " · ".t("Senjutsu"); }
		$hh = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
		// Mesmo cartão-botão dos jutsus da batalha (jutsugrade()), mas como link (usa o jutsu fora da luta).
		$html .= "<a href=\"index.php?do=spell:".intval($s["id"])."&amp;csrf=".csrf_token()."\" class=\"jcard jcardmenu".($falta > 0 ? " jcardespera" : "").($ligado ? " jcardligado" : "")."\" title=\"".$hh($titulo)."\" style=\"border-left-color:$cor\"><img src=\"$icone\" width=\"20\" height=\"17\" alt=\"\"><div><b>".$hh(t($s["name"]))."</b><span>".(count($custos) ? $hh(implode(" + ", $custos)) : $hh($rotulo)).$extra."</span></div></a>";
	}
	if ($html != "") { $html = jutsucartaocss()."<div class=\"jgrade jgrademenu\">".$html."</div>"; }
	return $html;
}}


/* Imunidades dos inimigos (docs/BANCO_DE_DADOS.md, tools/imunidades.sql): monsters.imunidades é uma lista separada por
   vírgula com "ninjutsu" (dano e dano por turno de todos os jutsus), "taijutsu" (ataque físico), um elemento de ninjutsu
   ("fogo", "agua", "terra", "vento", "raio": só os jutsus desse elemento) e "sono" (jutsus de sono e atordoar).
   A coluna antiga monsters.immune continua existindo (0 nada, 1 = ninjutsu, 2 = ninjutsu + sono) e é usada quando a nova
   está vazia ou não existe. */
if (!function_exists("imunidadesvalidas")) {
function imunidadesvalidas() { // chave => rótulo curto (cartão) e rótulo completo ("Imune a ...").
	return array(
		"ninjutsu"=>array("Ninjutsu", "Imune a Ninjutsu"), "taijutsu"=>array("Taijutsu", "Imune a Taijutsu"),
		"fogo"=>array("Fogo", "Imune a Ninjutsu de Fogo"), "agua"=>array("Água", "Imune a Ninjutsu de Água"),
		"terra"=>array("Terra", "Imune a Ninjutsu de Terra"), "vento"=>array("Vento", "Imune a Ninjutsu de Vento"),
		"raio"=>array("Raio", "Imune a Ninjutsu de Raio"), "sono"=>array("Sono", "Imune a Sono"),
		"genjutsu"=>array("Genjutsu", "Imune a Genjutsu")); // genjutsu: jutsus da especialização Genjutsu (spells.classe 3), sono e os efeitos que eles causam.
}
function imunidadesnormalizar($texto) { // texto/array -> lista canônica (ordem fixa, sem repetição, só chaves válidas).
	$itens = is_array($texto) ? $texto : explode(",", strtolower((string)$texto));
	$itens = array_map("trim", $itens);
	$lista = array();
	foreach (array_keys(imunidadesvalidas()) as $k) { if (in_array($k, $itens, true)) { $lista[] = $k; } }
	if (in_array("ninjutsu", $lista)) { $lista = array_values(array_diff($lista, array("fogo", "agua", "terra", "vento", "raio"))); } // ninjutsu já cobre os elementos.
	return $lista;
}
function imunidadesdoinimigo($row) { // Lista de imunidades de uma linha de naruto_monsters (com fallback na coluna immune).
	if (isset($row["imunidades"]) && trim((string)$row["imunidades"]) !== "") { return imunidadesnormalizar($row["imunidades"]); }
	$legado = intval(isset($row["immune"]) ? $row["immune"] : 0);
	return ($legado >= 2) ? array("ninjutsu", "sono") : (($legado == 1) ? array("ninjutsu") : array());
}
function imunidadeslegado($lista) { // Valor da coluna antiga immune equivalente a uma lista.
	return in_array("ninjutsu", $lista) ? (in_array("sono", $lista) ? 2 : 1) : 0;
}
function imunidadesdoalvo($alvo) { // Combatente de aplicarjutsu(): chave "imunidades" (lista) ou o inteiro antigo "imune".
	if (isset($alvo["imunidades"]) && is_array($alvo["imunidades"])) { return $alvo["imunidades"]; }
	return imunidadesdoinimigo(array("immune"=>isset($alvo["imune"]) ? $alvo["imune"] : 0));
}
function imunegenjutsu($alvo, $spell) { // O alvo é imune a Genjutsu e o jutsu é de Genjutsu (especialização 3) ou de sono?
	$gen = (intval(isset($spell["classe"]) ? $spell["classe"] : 0) == 3 || intval(isset($spell["type"]) ? $spell["type"] : 0) == 3);
	return $gen && in_array("genjutsu", imunidadesdoalvo($alvo));
}
function imunejutsu($alvo, $elemento) { // Rótulo traduzível da imunidade que bloqueia um jutsu de dano desse elemento, ou "".
	$lista = imunidadesdoalvo($alvo);
	if (in_array("ninjutsu", $lista)) { return "Ninjutsu"; }
	$elemento = (string)$elemento; $v = imunidadesvalidas();
	if ($elemento != "" && $elemento != "neutro" && isset($v[$elemento]) && in_array($elemento, $lista)) { return "Ninjutsu de ".$v[$elemento][0]; }
	return "";
}
function imunidadestexto($lista, $curto = false, $separador = ", ") { // Texto traduzido ("Imune a Ninjutsu, Imune a Sono" ou "Ninjutsu / Sono").
	$v = imunidadesvalidas(); $partes = array();
	foreach ($lista as $k) { if (isset($v[$k])) { $partes[] = t($v[$k][$curto ? 0 : 1]); } }
	return implode($separador, $partes);
}
}


if (!function_exists('jutsucategoria')){
function jutsucategoria($spell){ // Tipo do jutsu para o seletor da batalha: array(rótulo traduzido, ícone, cor da borda).
	$el = (string)$spell["elemento"]; if ($el == "") { $el = "neutro"; }
	$els = jutsuelementos();
	$cores = array("fogo"=>"#d9532b", "agua"=>"#3d8be0", "terra"=>"#a0703a", "vento"=>"#4a9f3f", "raio"=>"#c9a400", "neutro"=>"#8a7a6a");
	$tipo = intval($spell["type"]);
	if ($tipo == 7) { return array(t("Taijutsu"), "images/jutsu_taijutsu.png", "#c98b5a"); } // golpe físico com chakra.
	if ($tipo == 4 || $tipo == 5) { return array(tf("%s · em você", t($tipo == 4 ? "+Dano" : "+Defesa")), "images/jutsu_".($tipo == 4 ? "dano" : "defesa").".png", ($tipo == 4) ? "#d9432b" : "#3d7bd0"); } // reforço em si mesmo: a imunidade do inimigo não importa.
	if ($tipo == 2) { // dano: é Ninjutsu (bloqueado por "imune a Ninjutsu" e pela imunidade ao elemento).
		return array(($el == "neutro") ? t("Ninjutsu") : tf("Ninjutsu de %s", t($els[$el])), "images/".$el.".gif", $cores[$el]);
	}
	$outros = array(1=>array("Cura", "cura", "#4caf50"), 3=>array("Sono", "sono", "#8a6fd6"), 4=>array("+Dano", "dano", "#d9432b"), 5=>array("+Defesa", "defesa", "#3d7bd0"), 6=>array("Suporte", "suporte", "#c9a400"));
	$o = isset($outros[$tipo]) ? $outros[$tipo] : array("Jutsu", "suporte", "#8a7a6a");
	$rotulo = t($o[0]).(($el != "neutro" && isset($els[$el])) ? " · ".t($els[$el]) : "");
	return array($rotulo, "images/jutsu_".$o[1].".png", $o[2]);
}}

if (!function_exists('jutsugrade')){
function jutsugrade($lista, $imunidades){ // Seletor de jutsus da batalha: um cartão-botão por jutsu (ícone do tipo, nome, custo, recarga e "Imune").
	// $lista = array de array($spellrow, $recarga, $bloqueado); $imunidades = imunidades do inimigo (imunidadesdoinimigo()).
	// Cada cartão é um <button name="userspell" value="ID"> do formulário com spell=1: clicar usa o jutsu direto, sem lista suspensa.
	// O mouse (ou o 1º toque no celular) mostra a caixinha com os efeitos.
	$h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
	$html = jutsucartaocss();
	$html .= "<div class=\"jgrade\">";
	// Ordem: dano (Ninjutsu), sono, reforços, suporte e cura; dentro do tipo, pela ordem do jogo (id).
	$ordem = array(7=>0, 2=>1, 3=>2, 4=>3, 5=>4, 6=>5, 1=>6);
	usort($lista, function ($a, $b) use ($ordem) {
		$oa = isset($ordem[intval($a[0]["type"])]) ? $ordem[intval($a[0]["type"])] : 9; $ob = isset($ordem[intval($b[0]["type"])]) ? $ordem[intval($b[0]["type"])] : 9;
		return ($oa != $ob) ? $oa - $ob : intval($a[0]["id"]) - intval($b[0]["id"]);
	});
	foreach ($lista as $item) {
		list($spell, $recarga, $bloqueado) = $item;
		list($rotulo, $icone, $cor) = jutsucategoria($spell);
		$imune = "";
		if (intval($spell["type"]) == 2) { $imune = imunejutsu(array("imunidades"=>$imunidades), $spell["elemento"]); }
		elseif (intval($spell["type"]) == 3 && in_array("sono", $imunidades)) { $imune = "Sono"; }
		if ($imune == "" && in_array("genjutsu", $imunidades) && (intval($spell["classe"]) == 3 || intval($spell["type"]) == 3) && intval($spell["type"]) != 1 && intval($spell["type"]) != 4 && intval($spell["type"]) != 5) { $imune = "Genjutsu"; }
		elseif (intval($spell["type"]) == 7 && in_array("taijutsu", $imunidades)) { $imune = "Taijutsu"; }
		$custo = "CH ".custochakra($spell["mp"], $spell).(intval(isset($spell["custo_np"]) ? $spell["custo_np"] : 0) > 0 ? " + NP ".intval($spell["custo_np"]) : "").(jutsucustoep($spell) > 0 ? " + EP ".jutsucustoep($spell) : "");
		$extra = ($recarga > 0) ? " · ".tf("recarga: %s", $recarga) : ((intval(isset($spell["requer_senjutsu"]) ? $spell["requer_senjutsu"] : 0) == 1) ? " · ".t("Senjutsu") : "");
		$id = "jutsu".intval($spell["id"]);
		$efeitos = implode("<br>", array_map($h, jutsudescricaoefeitos($spell)));
		if ($imune != "") { $efeitos .= "<br><font color=\"#b3261e\"><b>".$h(tf("O inimigo é imune: %s", t($imune)))."</b></font>"; }
		$titulo = "<b>".$h(t($spell["name"]))."</b><br><span style=\"font-size:10px\">".$h($rotulo)."</span>";
		$dica = "explicdrop('".$id."', ".$h(json_encode($titulo)).", ".$h(json_encode($efeitos)).", 0, 18)";
		$html .= "<button type=\"submit\" name=\"userspell\" value=\"".intval($spell["id"])."\" class=\"jcard\" id=\"$id\" style=\"border-left-color:$cor\"".($bloqueado ? " disabled=\"disabled\"" : "")
			." onmouseover=\"$dica\" onmouseout=\"fecharexplic();\"><img src=\"$icone\" width=\"20\" height=\"17\" alt=\"\"><div><b>".$h(t($spell["name"]))."</b><span>".$h($rotulo)." · ".$h($custo.$extra)."</span></div>"
			.(($imune != "") ? "<i>".$h(t("Imune"))."</i>" : "")."</button>";
	}
	return $html."</div>";
}}

if (!function_exists('jutsucartaocss')){
function jutsucartaocss(){ // Estilo dos cartões-botão de jutsu (batalha e menu de jutsus da coluna direita).
	return "<style>.jgrade{display:flex;flex-wrap:wrap;gap:4px;justify-content:center;max-width:300px;margin:0 auto}"
		.".jcard{position:relative;width:147px;min-height:32px;box-sizing:border-box;display:flex;align-items:center;gap:5px;padding:3px 5px;background:#fff1c7;border:1px solid #8b5a2b;border-left-width:5px;border-radius:3px;cursor:pointer;text-align:left;font:11px tahoma,arial,sans-serif;color:#2a1a0c;transition:transform .08s,background .15s}"
		.".jcard:hover{background:#ffe3a0;transform:translateY(-1px)}.jcard:active{transform:translateY(1px)}"
		.".jcard[disabled]{opacity:.5;cursor:default;transform:none;background:#eadcb4}"
		.".jcard img{flex:none}.jcard b{display:block;font-size:11px;line-height:1.1}.jcard span{display:block;font-size:10px;color:#6b4a26;line-height:1.2}"
		.".jcard i{position:absolute;top:-6px;right:3px;font-style:normal;font-size:9px;font-weight:bold;color:#fff;background:#b3261e;padding:0 4px;border-radius:6px}"
		.".jgrademenu{max-width:none;flex-direction:column;align-items:stretch;margin:4px 0}.jgrademenu .jcard{width:100%;text-decoration:none}.jgrademenu .jcard:hover{text-decoration:none}"
		.".jcardespera{opacity:.6}.jcardligado{background:#dff0c8}</style>";
}}

if (!function_exists('pocaograde')){
function pocaograde($row, $nomebotao){ // Poções de HP/CH da mochila (bp1..bp8) em cartões-botão, no estilo de jutsugrade().
	// Cada cartão é <button name="$nomebotao" value="N"> (N = espaço da mochila), com o sprite da poção (iconeconsumivel()).
	// Devolve "" se não houver poção. Usa as classes .jcard/.jgrade (o CSS vem de jutsugrade() ou daqui, se faltar).
	$h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
	$html = "";
	for ($i = 1; $i <= 8; $i++) {
		$item = array_pad(explode(",", (string)$row["bp".$i]), 4, "X");
		if ($item[1] != "hp" && $item[1] != "mp") { continue; }
		$qtd = qtditem($row["bp".$i]);
		$rotulo = "+".intval($item[2])." ".($item[1] == "mp" ? "CH" : "HP").($qtd > 1 ? " · ×".$qtd : "");
		$cor = ($item[1] == "mp") ? "#3d7bd0" : "#c0392b";
		$html .= "<button type=\"submit\" name=\"".$h($nomebotao)."\" value=\"$i\" class=\"jcard pcard\" id=\"pocao$i\" style=\"border-left-color:$cor\" title=\"".$h(t("Beber"))."\">"
			."<img src=\"".$h(iconeconsumivel($item[1], $item[2], $item[0]))."\" width=\"26\" height=\"26\" alt=\"\"><div><b>".$h(t($item[0]))."</b><span>".$h($rotulo)."</span></div></button>";
	}
	if ($html == "") { return ""; }
	return "<style>.jgrade{display:flex;flex-wrap:wrap;gap:4px;justify-content:center;max-width:300px;margin:0 auto}"
		.".pcard{position:relative;width:147px;min-height:32px;box-sizing:border-box;display:flex;align-items:center;gap:5px;padding:2px 5px;background:#fff1c7;border:1px solid #8b5a2b;border-left-width:5px;border-radius:3px;cursor:pointer;text-align:left;font:11px tahoma,arial,sans-serif;color:#2a1a0c}"
		.".pcard:hover{background:#ffe3a0}.pcard img{flex:none;image-rendering:pixelated}.pcard b{display:block;font-size:11px;line-height:1.1}.pcard span{display:block;font-size:10px;color:#6b4a26}</style>"
		."<div class=\"jgrade\">".$html."</div>";
}}

/* ---------- Ferramentas ninja de arremesso (kunais, shurikens, bombas, pergaminhos de armas) ----------
   Drop com consumivel = "bomba,ID" (ID = o próprio drop) e as colunas drops.bomba_*:
   bomba_dano (dano base), bomba_acerto (% de acertar, ajustável no painel), bomba_elemento (neutro/fogo/agua/vento/raio/terra),
   bomba_efeito ("" ou queimadura/veneno/atordoar/debuff_ataque/debuff_defesa/sono), bomba_efeito_valor, bomba_efeito_turnos, bomba_efeito_chance.
   Na mochila fica "nome,bomba,ID,X[,qtd]". Arremessar gasta o turno (luta contra inimigos e duelo). */
if (!function_exists('itembatalhaefeitos')){
function itembatalhaefeitos(){ // Efeitos que uma ferramenta pode causar => rótulo (chave de tradução).
	return array(""=>"Nenhum", "queimadura"=>"Queimadura", "veneno"=>"Veneno", "atordoar"=>"Atordoamento", "debuff_ataque"=>"Ataque enfraquecido", "debuff_defesa"=>"Defesa quebrada", "sono"=>"Sono");
}}

if (!function_exists('itembatalhalinha')){
function itembatalhalinha($slot){ // Linha de drops da ferramenta de um espaço da mochila, ou false.
	$p = array_pad(explode(",", (string)$slot), 4, "X");
	if ($p[1] != "bomba" || intval($p[2]) <= 0) { return false; }
	static $cache = array();
	$id = intval($p[2]);
	if (!array_key_exists($id, $cache)) { $cache[$id] = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "drops"), MYSQLI_ASSOC) ?: false; }
	return $cache[$id];
}}

if (!function_exists('itembatalhaacerto')){
function itembatalhaacerto($row, $precisao){ // Chance final de acerto: a do item + 1% a cada ~33 de Precisão (teto 99).
	return max(1, min(99, intval($row["bomba_acerto"]) + (int)floor(intval($precisao) * 3 / 100)));
}}

if (!function_exists('itembatalharesumo')){
function itembatalharesumo($row){ // "Dano 16 · Acerto 75%" + efeito, para cartões e tooltips (já traduzido).
	$el = (string)$row["bomba_elemento"];
	$txt = tf("Dano %s", intval($row["bomba_dano"])).(($el != "" && $el != "neutro") ? " (".elementonome($el).")" : "")." · ".tf("Acerto %s%%", intval($row["bomba_acerto"]));
	$efs = itembatalhaefeitos(); $ef = (string)$row["bomba_efeito"];
	if ($ef != "" && isset($efs[$ef])) { $txt .= " · ".t($efs[$ef])." ".intval($row["bomba_efeito_chance"])."%"; }
	return $txt;
}}

if (!function_exists('itembatalhaarremessar')){
function itembatalhaarremessar($row, $precisao, $alvo, $nomeusuario, &$msgs){
	/* Rola o acerto e calcula o dano (sem aplicar). $alvo: "nome", "elemento", "imunidades" (lista; ninjutsu/taijutsu/genjutsu não valem
	   contra ferramentas, só a imunidade ao elemento do item). Devolve array("acertou"=>bool, "dano"=>int, "efeito"=>"" ou o efeito que pegou). */
	$res = array("acertou"=>false, "dano"=>0, "efeito"=>"");
	$nomeitem = (string)$row["name"];
	if (rand(1, 100) > itembatalhaacerto($row, $precisao)) { $msgs[] = i18n("%s arremessou %s, mas errou o alvo!", $nomeusuario, $nomeitem); return $res; }
	$res["acertou"] = true;
	$imunes = isset($alvo["imunidades"]) && is_array($alvo["imunidades"]) ? $alvo["imunidades"] : array();
	$el = (string)$row["bomba_elemento"]; if ($el == "") { $el = "neutro"; }
	$dano = (int)ceil(intval($row["bomba_dano"]) * rand(90, 110) / 100);
	$extra = "";
	if ($el != "neutro" && in_array($el, $imunes)) { $dano = 0; $msgs[] = i18n("%s acertou %s, mas o alvo é imune a %s.", $nomeusuario, $nomeitem, elementonome($el)); }
	else {
		if ($el != "neutro") {
			$nada = null; $m = elementoganhador($el, isset($alvo["elemento"]) ? (string)$alvo["elemento"] : "neutro", $nada);
			if ($m > 1 && isset($alvo["classe"]) && intval($alvo["classe"]) == 2) { $m = 1; } // especialista em Taijutsu: sem fraqueza de elemento.
			$dano = (int)ceil($dano * $m);
			if ($m != 1) { $extra = elementofrase($el, isset($alvo["elemento"]) ? (string)$alvo["elemento"] : "neutro", $m); }
		}
		$dano = max(1, $dano);
		$msgs[] = i18n("%s arremessou %s e acertou %s, causando %s de dano.", $nomeusuario, $nomeitem, $alvo["nome"], $dano);
		if ($extra != "") { $msgs[] = $extra; }
	}
	$res["dano"] = $dano;
	$ef = (string)$row["bomba_efeito"];
	if ($ef != "") {
		if (($ef == "sono" || $ef == "atordoar") && in_array("sono", $imunes)) { $msgs[] = i18n("%s é imune a %s.", $alvo["nome"], $ef == "sono" ? "Sono" : "Atordoamento"); }
		elseif (rand(1, 100) <= max(0, min(100, intval($row["bomba_efeito_chance"])))) { $res["efeito"] = $ef; }
		else { $efs = itembatalhaefeitos(); $msgs[] = i18n("%s não pegou desta vez.", $efs[$ef]); }
	}
	return $res;
}}

if (!function_exists('itembatalhaaplicarefeito')){
function itembatalhaaplicarefeito($row, $ef, $alvo, &$estado, &$msgs){ // Efeito que pegou (menos o sono do inimigo, tratado pelo fight.php) no estado dos jutsus.
	if ($ef == "sono") { $ef = "atordoar"; } // contra jogadores (duelo) o sono vira atordoamento.
	$spell = array("efeito"=>$ef, "efeito_valor"=>intval($row["bomba_efeito_valor"]), "efeito_turnos"=>max(1, intval($row["bomba_efeito_turnos"])), "efeito_chance"=>100,
		"elemento"=>"", "classe"=>0, "type"=>0, "name"=>(string)$row["name"]);
	$alvo["imunidades"] = array(); // as imunidades que valem já foram vistas em itembatalhaarremessar().
	$nada = array("lado"=>"", "nome"=>"", "hp"=>0, "maxhp"=>0, "mp"=>null, "maxmp"=>0);
	jutsuaplicarefeito($nada, $alvo, $spell, $estado, $msgs);
}}

if (!function_exists('itembatalhagrade')){
function itembatalhagrade($row, $nomebotao){ // Ferramentas da mochila em cartões-botão (como pocaograde()). "" se não houver.
	$h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
	$html = "";
	for ($i = 1; $i <= 8; $i++) {
		$d = itembatalhalinha($row["bp".$i]);
		if (!$d) { continue; }
		$qtd = qtditem($row["bp".$i]);
		$item = explode(",", (string)$row["bp".$i]);
		$cor = array("fogo"=>"#d35400", "agua"=>"#2e86c1", "vento"=>"#27ae60", "raio"=>"#c9a400", "terra"=>"#8e5b2b")[(string)$d["bomba_elemento"]] ?? "#5d6d7e";
		$html .= "<button type=\"submit\" name=\"".$h($nomebotao)."\" value=\"$i\" class=\"jcard pcard bcard\" id=\"arremesso$i\" style=\"border-left-color:$cor\" title=\"".$h(t($item[0])." — ".t("Arremessar")." · ".itembatalharesumo($d))."\">"
			."<img src=\"".$h(iconeconsumivel("bomba", intval($d["id"])))."\" width=\"26\" height=\"26\" alt=\"\"><div><b>".$h(t($item[0])).($qtd > 1 ? " ×".$qtd : "")."</b><span>".$h(tf("Dano %s", intval($d["bomba_dano"]))." · ".tf("%s%% acerto", intval($d["bomba_acerto"])))."</span></div></button>";
	}
	if ($html == "") { return ""; }
	return "<style>.jgrade{display:flex;flex-wrap:wrap;gap:4px;justify-content:center;max-width:300px;margin:0 auto}"
		.".pcard{position:relative;width:147px;min-height:32px;box-sizing:border-box;display:flex;align-items:center;gap:5px;padding:2px 5px;background:#fff1c7;border:1px solid #8b5a2b;border-left-width:5px;border-radius:3px;cursor:pointer;text-align:left;font-family:inherit;color:#3d2410}"
		.".pcard:hover{background:#ffe3a0}.pcard img{flex:none;image-rendering:pixelated}.pcard b{display:block;font-size:11px;line-height:1.1}.pcard span{display:block;font-size:10px;color:#6b4a26}</style>"
		."<div class=\"jgrade\">".$html."</div>";
}}
?>
