<?php // funcoesmissoesextras.php :: Motor das missões guardadas no banco (história + extras) e dos NPCs do mapa.
//
// Tabelas:
//   naruto_missoesextras ......... todas as missões. categoria 'historia' = missões obrigatórias (ordem pela coluna etapa,
//                                  progresso em users.missao "etapa,feitos,total,intervalo" - veja missoes.php);
//                                  categoria 'extra' = missões opcionais (progresso em naruto_missoesextras_jogador).
//   naruto_missoesextras_jogador . progresso das extras: (usuario_id, missao_id) -> progresso, estado, aceita_em, concluida_em.
//   naruto_npcs .................. NPCs desenhados no mapa (botão "Conversar" em falapersonagens.php).
//
// Tipos de missão (coluna tipo) e o significado de alvo / quantidade:
//   entregar_item ..... alvo = nome do item/drop; quantidade = quantos (na mochila, bp1..bp8). Consumidos na entrega.
//   derrotar_monstro .. alvo = id do monstro. Extras: quantidade de vitórias desde que aceitou (contadas em fight.php).
//                       História: o último monstro derrotado (users.ultmonstro) deve ser ele, "quantidade" vezes seguidas.
//   visitar_local ..... alvo = "latitude,longitude" (marcado ao andar no mapa, explore.php). Só para extras.
//   pagar_ryou ........ quantidade = Ryou pagos na entrega.
//   falar_com_npc ..... extras: alvo = id do NPC com quem conversar. História: conversar com o NPC da coluna npc_id.
//   repetir ........... só história: fazer a tarefa "quantidade" vezes com "intervalo_min" minutos entre cada uma.
//   especial .......... só história: regra em PHP, escolhida pela coluna gancho (tabela de ganchos abaixo).
//   --- tipos só das missões opcionais (configuração extra em JSON na coluna config) ---
//   cadeia ............ passos em ordem (config "etapas": tipo/alvo/quantidade/npc/texto de cada passo); progresso do
//                       jogador em etapa_atual + progresso. Entregar item no meio da cadeia exige um NPC (npc) que recebe.
//   viagem ............ escolta/viagem a pé: alvo = "vila:ID" ou "npc:ID" (destino, onde é entregue); quantidade =
//                       limite de passos (0 = sem limite). Morrer ou passar do limite = falhou.
//   coletar ........... alvo = nome do drop; quantidade = quantos drops obtidos depois de aceitar (users.dropcode da
//                       vitória) + chance própria opcional (config "chance" %, "monstro" = id do inimigo, 0 = qualquer).
//   sobreviver ........ quantidade = vitórias seguidas sem descansar, ganhar HP ou morrer (config "nivel" = nível mínimo do inimigo).
//   escolha ........... sem objetivo: na entrega o jogador escolhe uma de duas opções (config "opcoes", 2 itens com
//                       rotulo/texto/exp/ryou/item). Qualquer missão pode ter "opcoes"; outra missão pode exigir
//                       config "requer" (id) + "requer_opcao" ("0"/"1") e só aparece depois daquela escolha.
//   cronometrada ...... qualquer tipo com limite_min > 0: o tempo conta do aceite; vencido = 'falhou' (pode aceitar de novo).
//   Repetíveis: intervalo_horas 24 = "Diária", 168 = "Semanal".
//   troca de vila ..... qualquer missão opcional com config "troca_vila" = id da vila de destino: na entrega a vila natal
//                       (users.alinhamento) passa a ser a dessa vila, numa transação (veja mx_trocavilamotivo e
//                       docs/TROCA_DE_VILA.md). Tempo mínimo entre trocas: control.trocavila_dias (users.vilatroca_em).
//   troca de dificuldade  config "troca_dificuldade" = 1..3: na entrega users.difficulty passa a ser essa, do mesmo jeito
//                       (transação; mx_trocadifmotivo, docs/TROCA_DE_DIFICULDADE.md). Espera: control.trocadif_dias
//                       (users.dificuldadetroca_em).
// Na história, npc_id > 0 significa "também é preciso conversar com esse NPC" (users.missaoswitch = 1).
// Nas extras, npc_id > 0 significa que a missão é aceita/entregue com esse NPC no mapa (e não numa vila).

if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }
include_once('funcoesinclusas.php'); // qtditem(), itemcomqtd(), mochilaguardar(): pilhas da mochila.

if (!function_exists('mx_tabela')) {

function mx_tabela($nome) { // Nome real de uma tabela (para queries com duas tabelas; o doquery só troca uma).

	global $dbsettings;
	return $dbsettings["prefix"]."_".$nome;

}

function mx_apostrofo($texto) { // Mesma troca que o t() faz nos textos em inglês: ' entre letras vira ’ (não quebra JavaScript/atributos).

	return preg_replace("/(?<=\p{L})'(?=\p{L})/u", "’", (string)$texto);

}

function mx_texto($row, $campo) { // Texto de uma coluna no idioma do jogador: coluna _en (se preenchida) ou t() da coluna em português.

	$pt = (string)$row[$campo];
	if ($pt === "") { return ""; }
	if (idioma() == "en" && isset($row[$campo."_en"]) && $row[$campo."_en"] != "") { return mx_apostrofo($row[$campo."_en"]); }
	if (idioma() == "pt") { return $pt; }
	// Textos digitados no painel sem tradução aparecem como foram escritos (sem aviso de tradução faltando).
	$lista = traducoes();
	return isset($lista[$pt]) ? t($pt) : $pt;

}

function mx_comarg($texto, $arg) { // Coloca o nome (item/monstro, traduzido) no lugar do %s do texto.

	if ($arg == "") { return $texto; }
	return str_replace("%s", t($arg), $texto);

}

function mx_titulo($row) { return mx_texto($row, "titulo"); } // Título no idioma do jogador.

/* ------------------------------------------------------------------------
   Missões da história.
   ------------------------------------------------------------------------ */

function mx_historia() { // Todas as missões da história, indexadas pela etapa (lidas uma vez por página).

	static $lista = null;
	if ($lista === null) {
		$lista = array();
		$query = doquery("SELECT * FROM {{table}} WHERE categoria='historia' ORDER BY etapa, id", "missoesextras");
		while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) { if (!isset($lista[intval($row["etapa"])])) { $lista[intval($row["etapa"])] = $row; } }
	}
	return $lista;

}

function mx_iniciomissao($etapa) { // Valor inicial de users.missao para começar a etapa: "etapa,0,repetições,intervalo" (ou "etapa,0,0,0").

	$lista = mx_historia();
	$etapa = intval($etapa);
	if (isset($lista[$etapa]) && $lista[$etapa]["tipo"] == "repetir") {
		return $etapa.",0,".intval($lista[$etapa]["quantidade"]).",".intval($lista[$etapa]["intervalo_min"]);
	}
	return $etapa.",0,0,0";

}

// Ganchos: regras de missões da história que não cabem nas colunas genéricas (coluna gancho, tipo 'especial').
// Cada gancho recebe o jogador e devolve true quando o requisito foi cumprido.
function mx_ganchos() {

	return array(
		// Missão 19 "Ataque na Vila da Folha!": Fukasaku e Shima no grupo (avatar 16, chamado em senjutsu.php?do=chamar).
		"sapos_no_grupo" => function ($userrow) { return intval($userrow["avatar"]) == 16; },
	);

}

function mx_itemcompleto($nome) { // "nome,id,tipo,X" no formato da mochila: drops (tipo 4) ou itens das lojas (tipo 1-3). false se não existir.

	$nome = (string)$nome;
	if ($nome == "") { return false; }
	// Recompensa com variante rara ("Pena do Vendaval:chama"): procura o item comum e devolve com a variante no nome.
	list($base, $efeito) = separarvariante($nome);
	if ($efeito != "") {
		if (!isset(variantesitens()[$efeito])) { return false; }
		$comum = mx_itemcompleto($base);
		if ($comum === false || strpos($comum, ",jutsu,") !== false) { return false; }
		return nomecomvariante($base, $efeito).substr($comum, strlen($base));
	}
	$query = doquery("SELECT id, ensina_jutsu, consumivel FROM {{table}} WHERE name='".addslashes($nome)."' LIMIT 1", "drops");
	if ($row = mysqli_fetch_array($query)) {
		// Pergaminho de jutsu vai para a mochila no formato que o backpack.php estuda: "nome,jutsu,ID do jutsu,1".
		if (intval($row["ensina_jutsu"]) > 0) { return $nome.",jutsu,".intval($row["ensina_jutsu"]).",1"; }
		// Consumível de drop (poção, ferramenta ninja, descanso, expansão do banco...): mesmo formato do drop da luta (fight.php), "nome,tipo,valor,1".
		$c = array_pad(explode(",", (string)$row["consumivel"]), 2, 0);
		if (in_array($c[0], array("hp", "mp", "tp", "np", "ep", "tel", "bomba", "descanso", "banco"), true) && intval($c[1]) > 0) { return $nome.",".$c[0].",".intval($c[1]).",1"; }
		return $nome.",".intval($row["id"]).",4,".durabilidadenova(4, $row["id"]); // durabilidade cheia (drops.durabilidade) ou X.
	}
	$query = doquery("SELECT id, type FROM {{table}} WHERE name='".addslashes($nome)."' LIMIT 1", "items");
	if ($row = mysqli_fetch_array($query)) { return $nome.",".intval($row["id"]).",".intval($row["type"]).",".durabilidadenova(intval($row["type"]), $row["id"]); }
	return false;

}

function mx_monstro($id) { // Linha do monstro (ou false).

	$query = doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' LIMIT 1", "monsters");
	$row = mysqli_fetch_array($query);
	return $row ? $row : false;

}

function mx_argsmapa($row) { // Parâmetros extras do mostrarquest(): item procurado, "lat/lon" do NPC e monstro procurado.

	$item = jsarg($row["mapa_item"]); $local = jsarg($row["mapa_local"]); $monstro = jsarg($row["mapa_monstro"]);
	if ($monstro != "") { return ", '$item', '$local', '$monstro'"; }
	if ($local != "") { return ", '$item', '$local'"; }
	if ($item != "") { return ", '$item'"; }
	return "";

}

/* ------------------------------------------------------------------------
   NPCs do mapa.
   ------------------------------------------------------------------------ */

function mx_npcs() { // NPCs ativos, indexados pelo id.

	static $lista = null;
	if ($lista === null) {
		$lista = array();
		$query = doquery("SELECT * FROM {{table}} WHERE ativo='1' ORDER BY id", "npcs");
		while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) { $lista[intval($row["id"])] = $row; }
	}
	return $lista;

}

function mx_npcsvisiveis($userrow) { // NPCs que o jogador enxerga, indexados por "latitude,longitude".
	// NPC ligado a uma missão da história só aparece na etapa dessa missão (como sempre foi); os outros aparecem sempre.

	static $cache = array();
	$etapa = intval(array_pad(explode(",", (string)$userrow["missao"]), 1, "0")[0]);
	if (isset($cache[$etapa])) { return $cache[$etapa]; }
	$dahistoria = array();
	foreach (mx_historia() as $m) { if (intval($m["npc_id"]) > 0) { $dahistoria[intval($m["npc_id"])] = true; } }
	$historia = mx_historia();
	$npcdaetapa = isset($historia[$etapa]) ? intval($historia[$etapa]["npc_id"]) : 0;
	$visiveis = array();
	foreach (mx_npcs() as $id => $npc) {
		if (isset($dahistoria[$id]) && $id != $npcdaetapa) { continue; }
		$visiveis[intval($npc["latitude"]).",".intval($npc["longitude"])] = $npc;
	}
	$cache[$etapa] = $visiveis;
	return $visiveis;

}

function mx_npcnahistoria($npc, $userrow) { // true se o NPC é o da etapa atual da história do jogador.

	$historia = mx_historia();
	$etapa = intval(array_pad(explode(",", (string)$userrow["missao"]), 1, "0")[0]);
	return isset($historia[$etapa]) && intval($historia[$etapa]["npc_id"]) == intval($npc["id"]);

}

function mx_npcaqui($userrow) { // NPC visível na casa onde o jogador está (ou false).

	$visiveis = mx_npcsvisiveis($userrow);
	$aqui = intval($userrow["latitude"]).",".intval($userrow["longitude"]);
	return isset($visiveis[$aqui]) ? $visiveis[$aqui] : false;

}

function mx_npcsnomapa($topo, $base, $esq, $dir, &$mostrar, &$title) { // Mapa-múndi (index.php?do=showmap): NPCs visíveis dentro do quadrante.
	// Acrescenta "NPC: nome" ao título e, se o quadrante não estiver marcado (azul / você está aqui), mostra o ícone do NPC.

	global $userrow;
	$nomes = array(); $icone = "";
	foreach (mx_npcsvisiveis($userrow) as $coord => $npc) {
		list($la, $lo) = explode(",", $coord);
		if ($la <= $topo && $la >= $base && $lo >= $esq && $lo <= $dir) { $nomes[] = "NPC: ".$npc["nome"]." [".mx_coordenada(intval($la), intval($lo))."]"; if ($icone == "") { $icone = $npc["icone_mapa"]; } }
	}
	if (count($nomes) == 0) { return; }
	$title .= " - ".implode(", ", $nomes);
	if (strpos($mostrar, "gif19") === 0) { $mostrar = "24/".$icone.".gif\" width=\"19\" height=\"19"; } // a <img> do mapa não tem largura: a célula continua com 19px.

}

function mx_falanpc($npc, $nomejogador) { // Fala do NPC no idioma do jogador, com o nome do jogador no lugar do %s.

	$fala = mx_texto($npc, "fala");
	return str_replace("%s", $nomejogador, $fala);

}

/* ------------------------------------------------------------------------
   Missões (opcionais: categoria 'extra').
   ------------------------------------------------------------------------ */

function mx_graduacoes() { return array("Estudante da Academia" => 0, "Genin" => 1, "Chuunin" => 2, "Jounin" => 3); } // Ordem das graduações.

function mx_graduacaook($userrow, $minima) { // O jogador tem a graduação mínima?

	if ($minima == "") { return true; }
	$g = mx_graduacoes();
	$atual = isset($g[$userrow["graduacao"]]) ? $g[$userrow["graduacao"]] : 0;
	return !isset($g[$minima]) || $atual >= $g[$minima];

}

function mx_json($texto) { // JSON de uma coluna (config da missão / dados do jogador) como array; vazio ou inválido = array().

	static $cache = array();
	$texto = (string)$texto;
	if ($texto === "") { return array(); }
	if (!isset($cache[$texto])) { $d = json_decode($texto, true); $cache[$texto] = is_array($d) ? $d : array(); }
	return $cache[$texto];

}

function mx_itensextras($m) { // Itens a mais da recompensa (config "itens_extras": ["Selo de Viagem", "Selo de Viagem", ...]; repetido = mais unidades).
	$c = mx_config($m);
	return (isset($c["itens_extras"]) && is_array($c["itens_extras"])) ? array_values(array_filter(array_map("strval", $c["itens_extras"]))) : array();
}

function mx_config($m) { return mx_json(isset($m["config"]) ? $m["config"] : ""); } // Configuração extra da missão (coluna config).

function mx_cfg($m, $chave, $padrao = 0) { $c = mx_config($m); return isset($c[$chave]) ? $c[$chave] : $padrao; } // Um valor da config.

function mx_dados($estado) { return ($estado && isset($estado["dados"])) ? mx_json($estado["dados"]) : array(); } // Estado próprio do jogador na missão (coluna dados).

function mx_jsonsql($array) { return addslashes(count($array) ? json_encode($array, JSON_UNESCAPED_UNICODE) : ""); } // Array -> texto para gravar no banco.

function mx_estadojogador($usuarioid) { // Progresso do jogador em todas as missões: missao_id => linha. Missões cronometradas vencidas viram 'falhou'.

	$lista = array();
	$tabm = mx_tabela("missoesextras");
	$query = doquery("SELECT j.*, UNIX_TIMESTAMP(j.concluida_em) AS concluida_ts, UNIX_TIMESTAMP(j.aceita_em) AS aceita_ts, m.limite_min FROM {{table}} j LEFT JOIN $tabm m ON m.id=j.missao_id WHERE j.usuario_id='".intval($usuarioid)."'", "missoesextras_jogador");
	while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
		if ($row["estado"] == "andamento" && mx_vencida($row)) { mx_falhar($usuarioid, $row["missao_id"]); $row["estado"] = "falhou"; }
		$lista[intval($row["missao_id"])] = $row;
	}
	return $lista;

}

function mx_vencida($row) { // Missão cronometrada (limite_min > 0) cujo tempo acabou?

	return intval($row["limite_min"]) > 0 && time() > intval($row["aceita_ts"]) + intval($row["limite_min"]) * 60;

}

function mx_falhar($usuarioid, $missaoid) { // Marca a missão como falhou (pode ser aceita de novo, começando do zero).

	doquery("UPDATE {{table}} SET estado='falhou' WHERE usuario_id='".intval($usuarioid)."' AND missao_id='".intval($missaoid)."' AND estado='andamento' LIMIT 1", "missoesextras_jogador");

}

function mx_tempo_restante($m, $estado) { // Minutos que faltam numa missão cronometrada em andamento (ou -1 se não tem limite).

	if (intval($m["limite_min"]) <= 0 || !$estado) { return -1; }
	return max(0, (int)ceil((intval($estado["aceita_ts"]) + intval($m["limite_min"]) * 60 - time()) / 60));

}

function mx_extra($id) { // Uma missão opcional ativa (ou false).

	$query = doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' AND categoria='extra' AND ativa='1' LIMIT 1", "missoesextras");
	$row = mysqli_fetch_array($query, MYSQLI_ASSOC);
	return $row ? $row : false;

}

function mx_extrasdolugar($townrow, $npc) { // Missões ativas oferecidas aqui: na vila (vila_id = vila ou 0, sem NPC) ou com o NPC.

	if ($npc) { $onde = "npc_id='".intval($npc["id"])."'"; }
	elseif ($townrow) { $onde = "npc_id='0' AND (vila_id='0' OR vila_id='".intval($townrow["id"])."')"; }
	else { return array(); }
	$lista = array();
	$query = doquery("SELECT * FROM {{table}} WHERE categoria='extra' AND ativa='1' AND $onde ORDER BY ordem, id", "missoesextras");
	while ($row = mysqli_fetch_array($query, MYSQLI_ASSOC)) { $lista[intval($row["id"])] = $row; }
	return $lista;

}

function mx_destino($m) { // Viagem/escolta: destino (alvo "vila:ID" ou "npc:ID") => tipo, id, lat, lon, nome. false se não existir.

	$partes = explode(":", (string)$m["alvo"]);
	if (count($partes) != 2) { return false; }
	$id = intval($partes[1]);
	if ($partes[0] == "vila") {
		$q = doquery("SELECT id, name, latitude, longitude FROM {{table}} WHERE id='$id' LIMIT 1", "towns");
		$r = mysqli_fetch_array($q);
		return $r ? array("tipo" => "vila", "id" => $id, "lat" => intval($r["latitude"]), "lon" => intval($r["longitude"]), "nome" => t($r["name"])) : false;
	}
	if ($partes[0] == "npc") {
		$n = mx_npcs();
		return isset($n[$id]) ? array("tipo" => "npc", "id" => $id, "lat" => intval($n[$id]["latitude"]), "lon" => intval($n[$id]["longitude"]), "nome" => mx_nome($n[$id]["nome"])) : false;
	}
	return false;

}

function mx_entregaaqui($m, $townrow, $npc) { // A missão é entregue aqui? Viagem: no destino. Demais: onde foi aceita.

	if ($m["tipo"] == "viagem") {
		$d = mx_destino($m);
		if (!$d) { return false; }
		if ($d["tipo"] == "npc") { return $npc && intval($npc["id"]) == $d["id"]; }
		return !$npc && $townrow && intval($townrow["id"]) == $d["id"];
	}
	if (intval($m["npc_id"]) > 0) { return $npc && intval($npc["id"]) == intval($m["npc_id"]); }
	return !$npc && $townrow && (intval($m["vila_id"]) == 0 || intval($m["vila_id"]) == intval($townrow["id"]));

}

/* Jutsu de recompensa (coluna recompensa_jutsu = spells.id, 0 = nenhum). Na entrega da missão:
   1) não conhece e cumpre os requisitos (nível, classe, graduação) -> aprende o jutsu na hora;
   2) já conhece, ou ainda não pode aprender, e existe pergaminho (drops.ensina_jutsu) -> recebe o pergaminho na mochila/banco;
   3) sem pergaminho (jutsus exclusivos de missão, spells.exclusivo_missao=1) -> recebe Ryou e experiência no lugar:
      Ryou = max(500, 200 x nível mínimo do jutsu), experiência = 50 x nível mínimo.
   Devolve array("modo" => "ensinar"|"pergaminho"|"compensacao"|"", "spell" => linha, "item" => pergaminho, "ryou", "exp"). */
function mx_recompensajutsu($m, $userrow) {

	$r = array("modo" => "", "spell" => false, "item" => "", "ryou" => 0, "exp" => 0);
	$id = intval(isset($m["recompensa_jutsu"]) ? $m["recompensa_jutsu"] : 0);
	if ($id <= 0) { return $r; }
	$spell = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1", "spells"));
	if (!$spell) { return $r; }
	$r["spell"] = $spell;
	if (!jutsuconhece($userrow, $id) && jutsurequisitos($spell, $userrow) == "") { $r["modo"] = "ensinar"; return $r; }
	$perg = (intval($spell["exclusivo_missao"]) == 1) ? false : mysqli_fetch_array(doquery("SELECT name FROM {{table}} WHERE ensina_jutsu='$id' ORDER BY id LIMIT 1", "drops"));
	if ($perg) { $r["modo"] = "pergaminho"; $r["item"] = $perg["name"]; return $r; }
	$r["modo"] = "compensacao"; $r["ryou"] = max(500, 200 * intval($spell["nivelminimo"])); $r["exp"] = 50 * intval($spell["nivelminimo"]);
	return $r;

}

function mx_contaritens($userrow, $nome) { // Quantos itens com esse nome estão na mochila (bp1..bp8).

	$total = 0;
	for ($i = 1; $i <= 8; $i++) { $parte = explode(",", (string)$userrow["bp".$i]); if ($parte[0] === $nome) { $total += qtditem($userrow["bp".$i]); } } // pilhas contam todas as unidades.
	return $total;

}

/* Passos (objetivos) de uma missão. Missão comum = 1 passo com o próprio tipo/alvo/quantidade; 'cadeia' = lista de
   passos em ordem (config {"etapas":[{tipo, alvo, quantidade, npc, texto, texto_en, monstro, chance, nivel}, ...]});
   'escolha' = nenhum passo (só a decisão na entrega). O jogador guarda o passo atual em etapa_atual e a contagem
   dele em progresso. Passos "contados" (derrotar, visitar, falar, coletar, sobreviver e entregar a um NPC no meio da
   cadeia) avançam sozinhos; entregar item (sem NPC), pagar Ryou e viagem são conferidos na hora da entrega. */
function mx_passos($m) {

	if ($m["tipo"] == "escolha") { return array(); }
	$padrao = array("tipo" => "", "alvo" => "", "quantidade" => 1, "npc" => 0, "texto" => "", "texto_en" => "", "monstro" => 0, "chance" => 0, "nivel" => 0);
	if ($m["tipo"] == "cadeia") {
		$lista = array();
		$etapas = mx_cfg($m, "etapas", array());
		foreach ((is_array($etapas) ? $etapas : array()) as $e) { if (is_array($e) && isset($e["tipo"]) && $e["tipo"] != "") { $lista[] = array_merge($padrao, $e); } }
		return $lista;
	}
	return array(array_merge($padrao, array("tipo" => $m["tipo"], "alvo" => (string)$m["alvo"], "quantidade" => intval($m["quantidade"]), "monstro" => intval(mx_cfg($m, "monstro")), "chance" => intval(mx_cfg($m, "chance")), "nivel" => intval(mx_cfg($m, "nivel")))));

}

function mx_passocontado($p) { // O passo avança sozinho (contador guardado em progresso)?

	if ($p["tipo"] == "entregar_item") { return intval($p["npc"]) > 0; }
	return in_array($p["tipo"], array("derrotar_monstro", "visitar_local", "falar_com_npc", "coletar", "sobreviver"), true);

}

function mx_qtdpasso($p) { return ($p["tipo"] == "viagem") ? intval($p["quantidade"]) : max(1, intval($p["quantidade"])); } // Quantidade do passo (viagem: limite de passos, 0 = sem limite).

function mx_viagemok($m, $estado, $userrow) { // Viagem: está no destino, andou pelo menos a distância (sem viagem rápida) e dentro do limite de passos?

	$d = mx_destino($m);
	if (!$d || !$estado) { return false; }
	$dados = mx_dados($estado);
	$passos = isset($dados["passos"]) ? intval($dados["passos"]) : 0;
	$dist = isset($dados["dist"]) ? intval($dados["dist"]) : 0;
	$limite = intval($m["quantidade"]);
	if (intval($userrow["latitude"]) != $d["lat"] || intval($userrow["longitude"]) != $d["lon"]) { return false; }
	if ($passos < $dist - 1) { return false; } // o passo que entra numa vila não passa pelo contador.
	return $limite == 0 || $passos <= $limite;

}

function mx_progressopasso($m, $p, $estado, $userrow) { // Contagem atual (0..quantidade) do passo atual.

	$qtd = max(1, mx_qtdpasso($p));
	if ($p["tipo"] == "entregar_item" && intval($p["npc"]) == 0) { return min(mx_contaritens($userrow, $p["alvo"]), $qtd); }
	if ($p["tipo"] == "pagar_ryou") { return min(intval($userrow["gold"]), $qtd); }
	if ($p["tipo"] == "viagem") { return mx_viagemok($m, $estado, $userrow) ? $qtd : 0; }
	return min($estado ? intval($estado["progresso"]) : 0, $qtd);

}

function mx_completa($m, $estado, $userrow) { // Todos os passos cumpridos (pode entregar)?

	$passos = mx_passos($m);
	$n = count($passos);
	if ($n == 0) { return true; }
	$i = $estado ? intval($estado["etapa_atual"]) : 0;
	if ($i >= $n) { return true; }
	if ($i < $n - 1) { return false; }
	return mx_progressopasso($m, $passos[$i], $estado, $userrow) >= max(1, mx_qtdpasso($passos[$i]));

}

function mx_porcentagem($m, $estado, $userrow) { // 0..100 para as pedras de progresso.

	$passos = mx_passos($m);
	$n = count($passos);
	if ($n == 0 || mx_completa($m, $estado, $userrow)) { return 100; }
	$i = min(intval($estado["etapa_atual"]), $n - 1);
	$frac = mx_progressopasso($m, $passos[$i], $estado, $userrow) / max(1, mx_qtdpasso($passos[$i]));
	return (int)floor(($i + $frac) * 100 / $n);

}

function mx_esperarecarga($m, $estado) { // Minutos até a missão repetível poder ser aceita de novo (0 = já pode).

	if (!$estado || $estado["estado"] != "concluida" || intval($m["repetivel"]) != 1) { return 0; }
	$libera = intval($estado["concluida_ts"]) + intval($m["intervalo_horas"]) * 3600;
	return max(0, (int)ceil(($libera - time()) / 60));

}

function mx_repeticaotexto($m) { // "Diária", "Semanal" ou "a cada N horas" (já traduzido).

	$h = intval($m["intervalo_horas"]);
	if ($h == 24) { return t("Diária."); }
	if ($h == 168) { return t("Semanal."); }
	return tf("Repetível a cada %s hora(s).", $h);

}

function mx_nome($nome) { $lista = traducoes(); return isset($lista[$nome]) ? t($nome) : $nome; } // Nome próprio (NPC): traduz só se houver tradução ("Pescador Tomiji" -> "Tomiji the Fisherman").

function mx_nomenpc($id) { $n = mx_npcs(); return isset($n[intval($id)]) ? mx_nome($n[intval($id)]["nome"]) : "?"; } // Nome de um NPC ativo.

function mx_objetivopasso($m, $p) { // Frase do objetivo de um passo (já traduzida). Passo de cadeia com texto próprio: texto + objetivo.

	$qtd = mx_qtdpasso($p);
	$frase = "";
	if ($p["tipo"] == "entregar_item") { $frase = (intval($p["npc"]) > 0) ? tf("Entregar %s x <b>%s</b> para <b>%s</b>.", $qtd, t($p["alvo"]), mx_nomenpc($p["npc"])) : tf("Entregar %s x <b>%s</b>.", $qtd, t($p["alvo"])); }
	elseif ($p["tipo"] == "derrotar_monstro") { $mon = mx_monstro($p["alvo"]); $frase = tf("Derrotar <b>%s</b> %s vez(es).", $mon ? t($mon["name"]) : "?", $qtd); }
	elseif ($p["tipo"] == "visitar_local") { $c = explode(",", $p["alvo"]); $frase = tf("Visitar a coordenada <b>%s</b>.", mx_coordenada(intval($c[0]), intval(isset($c[1]) ? $c[1] : 0))); }
	elseif ($p["tipo"] == "pagar_ryou") { $frase = tf("Pagar <b>%s Ryou</b>.", $qtd); }
	elseif ($p["tipo"] == "falar_com_npc") { $frase = tf("Conversar com <b>%s</b>.", mx_nomenpc($p["alvo"])); }
	elseif ($p["tipo"] == "coletar") {
		$mon = (intval($p["monstro"]) > 0) ? mx_monstro($p["monstro"]) : false;
		$frase = $mon ? tf("Coletar %s x <b>%s</b> derrotando <b>%s</b>.", $qtd, t($p["alvo"]), t($mon["name"])) : tf("Coletar %s x <b>%s</b> derrotando inimigos.", $qtd, t($p["alvo"]));
	}
	elseif ($p["tipo"] == "sobreviver") {
		$frase = tf("Vencer %s lutas seguidas sem descansar, se curar ou morrer.", $qtd);
		if (intval($p["nivel"]) > 0) { $frase .= " ".tf("Só contam inimigos de nível %s ou mais.", intval($p["nivel"])); }
	}
	elseif ($p["tipo"] == "viagem") {
		$d = mx_destino($m);
		$nome = $d ? $d["nome"]." [".mx_coordenada($d["lat"], $d["lon"])."]" : "?";
		$frase = ($qtd > 0) ? tf("Viajar a pé até <b>%s</b> em no máximo %s passos.", $nome, $qtd) : tf("Viajar a pé até <b>%s</b>.", $nome);
	}
	$texto = (idioma() == "en" && $p["texto_en"] != "") ? mx_apostrofo($p["texto_en"]) : (string)$p["texto"];
	return ($texto != "") ? $texto." <i>(".$frase.")</i>" : $frase;

}

function mx_objetivo($m, $estado = false, $userrow = false) { // Objetivo completo (já traduzido): um passo, a lista da cadeia ou a escolha.

	$passos = mx_passos($m);
	if (count($passos) == 0) { return t("Ouvir o pedido e tomar uma decisão."); }
	if (count($passos) == 1) { return mx_objetivopasso($m, $passos[0]); }
	$atual = ($estado && $estado["estado"] == "andamento") ? intval($estado["etapa_atual"]) : -1;
	$linhas = array();
	foreach ($passos as $i => $p) {
		$marca = ($atual > $i || ($atual == $i && $userrow && mx_completa($m, $estado, $userrow))) ? "&#10004;" : (($atual == $i) ? "&#9658;" : "&#9675;");
		$linhas[] = $marca." ".tf("Etapa %s:", $i + 1)." ".mx_objetivopasso($m, $p);
	}
	return implode("<br>", $linhas);

}

function mx_coordenada($lat, $lon) { // "12N,5W" como no mapa do jogo.

	$a = ($lat < 0) ? (-$lat)."S" : $lat."N";
	$b = ($lon < 0) ? (-$lon)."W" : $lon."E";
	return $a.",".$b;

}

function mx_opcoes($m) { // As duas escolhas da missão (config "opcoes"), ou array() se ela não tem escolha.

	$o = mx_cfg($m, "opcoes", array());
	if (!is_array($o) || count($o) != 2) { return array(); }
	$lista = array();
	foreach (array_values($o) as $op) { $lista[] = array_merge(array("rotulo" => "", "rotulo_en" => "", "texto" => "", "texto_en" => "", "exp" => 0, "ryou" => 0, "item" => ""), is_array($op) ? $op : array()); }
	return $lista;

}

function mx_textoopcao($op, $campo) { return (idioma() == "en" && $op[$campo."_en"] != "") ? mx_apostrofo($op[$campo."_en"]) : (string)$op[$campo]; } // Texto de uma opção no idioma do jogador.

function mx_recompensatexto($m) { // "100 de experiência, 50 Ryou, Item: X" (já traduzido). Com escolha: + o que cada opção dá.

	$partes = array();
	if (intval($m["recompensa_exp"]) > 0) { $partes[] = tf("%s de experiência", intval($m["recompensa_exp"])); }
	if (intval($m["recompensa_ryou"]) > 0) { $partes[] = tf("%s Ryou", intval($m["recompensa_ryou"])); }
	// Item principal + extras, agrupados ("Item: 2x Selo de Viagem").
	$todos = array(); foreach (array_merge($m["recompensa_item"] != "" ? array($m["recompensa_item"]) : array(), mx_itensextras($m)) as $it) { $todos[$it] = isset($todos[$it]) ? $todos[$it] + 1 : 1; }
	foreach ($todos as $it => $n) { $partes[] = tf("Item: <b>%s</b>", ($n > 1 ? $n."x " : "").nomeitemhtml($it)); }
	if (intval(isset($m["recompensa_avatar"]) ? $m["recompensa_avatar"] : 0) > 0) { include_once('funcoesavatares.php'); $partes[] = tf("Avatar: <b>%s</b>", avatarnome($m["recompensa_avatar"])); }
	if (intval(isset($m["recompensa_jutsu"]) ? $m["recompensa_jutsu"] : 0) > 0) {
		$sp = mysqli_fetch_array(doquery("SELECT name, exclusivo_missao FROM {{table}} WHERE id='".intval($m["recompensa_jutsu"])."' LIMIT 1", "spells"));
		if ($sp) { $partes[] = intval($sp["exclusivo_missao"]) ? tf("Jutsu exclusivo: <b>%s</b>", t($sp["name"])) : tf("Jutsu: <b>%s</b>", t($sp["name"])); }
	}
	$texto = count($partes) ? implode(", ", $partes)."." : "";
	foreach (mx_opcoes($m) as $op) {
		$p = array();
		if (intval($op["exp"]) > 0) { $p[] = tf("%s de experiência", intval($op["exp"])); }
		if (intval($op["ryou"]) > 0) { $p[] = tf("%s Ryou", intval($op["ryou"])); }
		if ($op["item"] != "") { $p[] = tf("Item: <b>%s</b>", t($op["item"])); }
		$texto .= ($texto != "" ? "<br>" : "").tf("Se escolher \"%s\": %s", mx_textoopcao($op, "rotulo"), count($p) ? implode(", ", $p)."." : t("Nenhuma."));
	}
	return ($texto != "") ? $texto : t("Nenhuma.");

}

function mx_localtexto($m) { // Onde a missão é aceita (e, na viagem, onde é entregue) - já traduzido.

	if (intval($m["npc_id"]) > 0) {
		$n = mx_npcs();
		if (!isset($n[intval($m["npc_id"])])) { return "?"; }
		$npc = $n[intval($m["npc_id"])];
		$texto = tf("%s, na coordenada %s.", mx_nome($npc["nome"]), mx_coordenada(intval($npc["latitude"]), intval($npc["longitude"])));
	} elseif (intval($m["vila_id"]) == 0) { $texto = t("Qualquer vila."); }
	else {
		$query = doquery("SELECT name FROM {{table}} WHERE id='".intval($m["vila_id"])."' LIMIT 1", "towns");
		$row = mysqli_fetch_array($query);
		$texto = $row ? t($row["name"]) : "?";
	}
	if ($m["tipo"] == "viagem") { $d = mx_destino($m); $texto .= " ".tf("Entrega: %s.", $d ? $d["nome"] : "?"); }
	return $texto;

}

function mx_requisitook($m, $estados) { // Missão que só aparece depois de outra (config "requer" = id, "requer_opcao" = "" / "0" / "1").

	$req = intval(mx_cfg($m, "requer"));
	if ($req <= 0) { return true; }
	if (!isset($estados[$req])) { return false; }
	$e = $estados[$req];
	if ($e["estado"] != "concluida" && intval($e["concluida_ts"]) == 0) { return false; } // repetível em andamento de novo: já foi concluída antes.
	$opcao = (string)mx_cfg($m, "requer_opcao", "");
	if ($opcao === "") { return true; }
	$dados = mx_dados($e);
	return isset($dados["opcao"]) && (string)$dados["opcao"] === $opcao;

}

/* ------------------------------------------------------------------------
   Troca de vila natal (missões com config "troca_vila"). A vila natal é users.alinhamento <-> towns.alinhamento; tudo
   o que depende dela (morte, PvP, Pergaminho de Retorno, Teleporte, ficha, emblema) lê esse código na hora, então
   trocar a coluna numa única gravação deixa o jogo inteiro coerente.
   ------------------------------------------------------------------------ */

function mx_trocavilaid($m) { return intval(mx_cfg($m, "troca_vila")); } // Vila de destino da missão (0 = missão comum).

function mx_vilaparatroca($id) { // Vila que aceita ninjas transferidos: vila natal comum (vilanatal = 1, com alinhamento, não secreta). false se não.

	$q = doquery("SELECT * FROM {{table}} WHERE id='".intval($id)."' AND vilanatal='1' AND alinhamento<>'' AND secreta='0' LIMIT 1", "towns");
	$row = mysqli_fetch_array($q, MYSQLI_ASSOC);
	return $row ? $row : false;

}

function mx_trocaviladias() { // Dias de espera entre duas trocas (control.trocavila_dias; lido do banco se a página ainda não carregou o control).

	global $controlrow;
	if (!is_array($controlrow) || !isset($controlrow["trocavila_dias"])) { $controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control")); }
	return isset($controlrow["trocavila_dias"]) ? max(0, intval($controlrow["trocavila_dias"])) : 30;

}

function mx_trocavilaespera($userrow) { // Segundos que faltam para poder trocar de vila natal de novo (0 = já pode).

	$ultima = isset($userrow["vilatroca_em"]) ? intval($userrow["vilatroca_em"]) : 0;
	if ($ultima <= 0) { return 0; }
	return max(0, $ultima + mx_trocaviladias() * 86400 - time());

}

function mx_tempotexto($segundos) { // "3 dia(s) e 4 hora(s)" / "5 hora(s) e 10 minuto(s)" (já traduzido).

	$segundos = max(60, intval($segundos));
	if ($segundos >= 86400) { $segundos = (int)ceil($segundos / 3600) * 3600; } // em dias: arredonda para a hora de cima (30 dias, não 29 dias e 23 horas).
	$d = intdiv($segundos, 86400); $h = intdiv($segundos % 86400, 3600); $min = intdiv($segundos % 3600, 60);
	if ($d > 0) { return tf("%s dia(s) e %s hora(s)", $d, $h); }
	return tf("%s hora(s) e %s minuto(s)", $h, max(1, $min));

}

function mx_trocavilamotivo($m, $userrow, $estados, $naentrega = false) { // "" se a troca é permitida agora; senão o motivo (traduzido).

	$vila = mx_vilaparatroca(mx_trocavilaid($m));
	if (!$vila) { return t("Essa vila não está aceitando ninjas de outras vilas no momento."); }
	if ((string)$vila["alinhamento"] === (string)$userrow["alinhamento"]) { return t("Essa já é a sua vila natal."); }
	$espera = mx_trocavilaespera($userrow);
	if ($espera > 0) { return tf("Você trocou de vila natal há pouco tempo. Poderá trocar de novo em %s.", mx_tempotexto($espera)); }
	if (!$naentrega) {
		foreach ($estados as $id => $e) {
			if ($e["estado"] != "andamento" || intval($id) == intval($m["id"])) { continue; }
			$outra = mx_extra($id);
			if ($outra && mx_trocavilaid($outra) > 0) { return tf("Você já está fazendo outra transferência de vila (%s). Termine-a ou espere ela falhar antes.", mx_titulo($outra)); }
		}
	}
	return "";

}

function mx_trocavilaquadro($userrow) { // Quadro "Sua vila natal: X" mostrado pelos embaixadores (quem oferece missões de troca).

	if (!function_exists('nomedoalinhamento')) { include_once('cidadesconteudo.php'); }
	$texto = tf("Sua vila natal atual: <b>%s</b>.", nomedoalinhamento($userrow["alinhamento"]));
	$espera = mx_trocavilaespera($userrow);
	if ($espera > 0) { $texto .= " ".tf("Próxima troca possível em %s.", mx_tempotexto($espera)); }
	else { $texto .= " ".tf("Depois de uma troca, é preciso esperar %s dia(s) para trocar de novo.", mx_trocaviladias()); }
	return "<div class=\"small\" style=\"border:1px solid #c9b47a;background:#fff1c7;padding:4px;margin:0 0 6px 0;text-align:center\">".$texto."</div>";

}

/* ------------------------------------------------------------------------
   Troca de dificuldade (missões com config "troca_dificuldade" = 1..3; docs/TROCA_DE_DIFICULDADE.md). A dificuldade
   (users.difficulty) só é usada na hora da luta (fight.php: HP e dano do inimigo, experiência e Ryou), nada fica
   guardado multiplicado; a entrega não é possível no meio de uma luta, então trocar a coluna basta.
   ------------------------------------------------------------------------ */

function mx_trocadifid($m) { $d = intval(mx_cfg($m, "troca_dificuldade")); return ($d >= 1 && $d <= 3) ? $d : 0; } // Dificuldade de destino (0 = missão comum).

function mx_nomedificuldade($d) { // Nome (traduzido) da dificuldade 1..3 (control.diffNname).

	global $controlrow;
	mx_trocadifdias(); // garante $controlrow carregado
	$d = intval($d);
	return ($d >= 1 && $d <= 3 && isset($controlrow["diff".$d."name"])) ? t($controlrow["diff".$d."name"]) : "?";

}

function mx_trocadifdias() { // Dias de espera entre duas trocas de dificuldade (control.trocadif_dias, padrão 14).

	global $controlrow;
	if (!is_array($controlrow) || !isset($controlrow["trocadif_dias"])) { $controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control")); }
	return isset($controlrow["trocadif_dias"]) ? max(0, intval($controlrow["trocadif_dias"])) : 14;

}

function mx_trocadifespera($userrow) { // Segundos que faltam para poder trocar de dificuldade de novo (0 = já pode).

	$ultima = isset($userrow["dificuldadetroca_em"]) ? intval($userrow["dificuldadetroca_em"]) : 0;
	if ($ultima <= 0) { return 0; }
	return max(0, $ultima + mx_trocadifdias() * 86400 - time());

}

function mx_trocadifmotivo($m, $userrow, $estados, $naentrega = false) { // "" se a troca de dificuldade é permitida agora; senão o motivo (traduzido).

	$alvo = mx_trocadifid($m);
	if ($alvo == 0) { return t("Essa provação não está disponível no momento."); }
	if ($alvo == intval($userrow["difficulty"])) { return t("Essa já é a sua dificuldade."); }
	if (isset($userrow["currentaction"]) && $userrow["currentaction"] == "Fighting") { return t("Você não pode fazer isso no meio de uma batalha!"); }
	$espera = mx_trocadifespera($userrow);
	if ($espera > 0) { return tf("Você trocou de dificuldade há pouco tempo. Poderá trocar de novo em %s.", mx_tempotexto($espera)); }
	if (!$naentrega) {
		foreach ($estados as $id => $e) {
			if ($e["estado"] != "andamento" || intval($id) == intval($m["id"])) { continue; }
			$outra = mx_extra($id);
			if ($outra && mx_trocadifid($outra) > 0) { return tf("Você já está fazendo outra provação de dificuldade (%s). Termine-a ou espere ela falhar antes.", mx_titulo($outra)); }
		}
	}
	return "";

}

function mx_trocadifquadro($userrow) { // Quadro "Sua dificuldade: X" mostrado pelo Mestre das Provações.

	$texto = tf("Sua dificuldade atual: <b>%s</b>.", mx_nomedificuldade($userrow["difficulty"]));
	$espera = mx_trocadifespera($userrow);
	if ($espera > 0) { $texto .= " ".tf("Próxima troca possível em %s.", mx_tempotexto($espera)); }
	else { $texto .= " ".tf("Depois de uma troca de dificuldade, é preciso esperar %s dia(s) para trocar de novo.", mx_trocadifdias()); }
	return "<div class=\"small\" style=\"border:1px solid #c9b47a;background:#fff1c7;padding:4px;margin:0 0 6px 0;text-align:center\">".$texto."</div>";

}

function mx_podeaceitar($m, $estado, $userrow, $estados = null) { // "" se pode aceitar; senão o motivo (traduzido).

	if (intval($userrow["level"]) < intval($m["nivel_minimo"])) { return tf("Você precisa estar no nível %s.", intval($m["nivel_minimo"])); }
	if (!mx_graduacaook($userrow, $m["graduacao_minima"])) { return tf("Você precisa ser %s.", t($m["graduacao_minima"])); }
	if ($estados === null) { $estados = mx_estadojogador($userrow["id"]); }
	if (!mx_requisitook($m, $estados)) { return t("Essa missão ainda não foi liberada para você."); }
	if ($estado && $estado["estado"] == "andamento") { return t("Você já aceitou essa missão."); }
	if (mx_trocavilaid($m) > 0) { $mt = mx_trocavilamotivo($m, $userrow, $estados); if ($mt != "") { return $mt; } }
	if (mx_trocadifid($m) > 0) { $mt = mx_trocadifmotivo($m, $userrow, $estados); if ($mt != "") { return $mt; } }
	if ($estado && $estado["estado"] == "concluida") {
		if (intval($m["repetivel"]) != 1) { return t("Você já completou essa missão."); }
		$espera = mx_esperarecarga($m, $estado);
		if ($espera > 0) { return tf("Disponível novamente em %s minuto(s).", $espera); }
	}
	return "";

}

function mx_situacao($m, $estado, $userrow) { // Linha curta de situação de uma missão em andamento: etapa, contagem, passos e tempo restante.

	$partes = array();
	$passos = mx_passos($m);
	$n = count($passos);
	if ($n > 0) {
		$i = min(intval($estado["etapa_atual"]), $n - 1);
		$p = $passos[$i];
		if ($n > 1) { $partes[] = tf("Etapa %s de %s", $i + 1, $n); }
		if ($p["tipo"] == "viagem") {
			$d = mx_dados($estado);
			$partes[] = (intval($p["quantidade"]) > 0) ? tf("Passos: %s/%s", isset($d["passos"]) ? intval($d["passos"]) : 0, intval($p["quantidade"])) : tf("Passos: %s", isset($d["passos"]) ? intval($d["passos"]) : 0);
		} elseif ($p["tipo"] == "sobreviver") {
			$partes[] = tf("Vitórias seguidas: %s/%s", mx_progressopasso($m, $p, $estado, $userrow), mx_qtdpasso($p));
		} else {
			$partes[] = tf("Progresso: %s/%s", mx_progressopasso($m, $p, $estado, $userrow), mx_qtdpasso($p));
		}
	}
	$resta = mx_tempo_restante($m, $estado);
	if ($resta >= 0) { $partes[] = tf("Tempo restante: %s minuto(s)", $resta); }
	if (mx_completa($m, $estado, $userrow)) { $partes[] = (count(mx_opcoes($m)) == 2) ? t("Pronta: faça sua escolha onde a missão é entregue.") : t("Pronta para entregar!"); }
	return implode(" &middot; ", $partes);

}

function mx_linha($m, $estado, $userrow, $i, $estados = null, $entregaaqui = true) { // Uma linha do painel de missões (mesmo visual de treinamentoequests.php) com o botão de ação.

	$csrf = csrf_campo();
	$titulo = mx_titulo($m);
	$motivo = mx_podeaceitar($m, $estado, $userrow, $estados);
	$relogio = "relogio2"; $relogiotitle = t("Sem Tempo de Espera para essa Missão");
	$botao = ""; $abaixo = "";
	$andamento = ($estado && $estado["estado"] == "andamento");
	if (intval($m["limite_min"]) > 0) { $relogio = "relogio"; $relogiotitle = tf("Missão cronometrada: %s minuto(s) para completar.", intval($m["limite_min"])); }
	if ($andamento) {
		$porcentagem = mx_porcentagem($m, $estado, $userrow);
		$opcoes = mx_opcoes($m);
		if (mx_completa($m, $estado, $userrow) && count($opcoes) == 2 && $entregaaqui) {
			// Escolha: duas respostas, cada uma com o seu desfecho e recompensa.
			$botao = "<img src=\"images/aceitar_missao.png\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated;vertical-align:middle\" title=\"".t("Escolha uma das opções abaixo")."\" border=\"0\">";
			$abaixo = "<div style=\"border:1px solid #c9b47a;background:#fff1c7;padding:5px;margin:2px 0 0 0\"><center><b>".t("O que você decide?")."</b></center>";
			foreach ($opcoes as $k => $op) {
				$abaixo .= "<form action=\"missoesextras.php?do=entregar\" method=\"post\" style=\"margin:4px 0\">$csrf<input type=\"hidden\" name=\"id\" value=\"".intval($m["id"])."\" /><input type=\"hidden\" name=\"opcao\" value=\"$k\" /><input class=\"btnjogo bj-confirmar\" type=\"submit\" value=\"".htmlspecialchars(strip_tags(mx_textoopcao($op, "rotulo")))."\" /></form>";
			}
			$abaixo .= "</div>";
		} elseif (mx_completa($m, $estado, $userrow) && $entregaaqui) {
			$botao = "<form action=\"missoesextras.php?do=entregar\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"id\" value=\"".intval($m["id"])."\" /><input type=\"image\" src=\"images/aceitar_missao.png\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated;background:none;border:0;padding:0;margin:0;vertical-align:middle\" title=\"".t("Entregar Missão")."\" alt=\"".t("Entregar Missão")."\" /></form>";
		} else {
			$botao = "<img src=\"images/aceitar_missao_off.png\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated\" title=\"".htmlspecialchars(strip_tags(mx_situacao($m, $estado, $userrow)))."\" border=\"0\">";
		}
		$abaixo = "<div class=\"small\" style=\"color:#613003;padding:1px 3px\">".mx_situacao($m, $estado, $userrow)."</div>".$abaixo;
	} elseif ($motivo == "") {
		$porcentagem = 0;
		$botao = "<form action=\"missoesextras.php?do=aceitar\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"id\" value=\"".intval($m["id"])."\" /><input type=\"image\" src=\"images/aceitar_missao.png\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated;background:none;border:0;padding:0;margin:0;vertical-align:middle\" title=\"".t("Aceitar Missão")."\" alt=\"".t("Aceitar Missão")."\" /></form>";
		if ($estado && $estado["estado"] == "falhou") { $abaixo = "<div class=\"small\" style=\"color:brown;padding:1px 3px\">".t("Você falhou nessa missão da última vez. Pode tentar de novo.")."</div>"; }
	} else {
		$concluida = ($estado && $estado["estado"] == "concluida");
		$porcentagem = $concluida ? 100 : 0;
		if ($concluida && intval($m["repetivel"]) == 1) { $relogio = "relogio"; }
		if ($concluida && intval($m["repetivel"]) != 1) { $relogiotitle = t("Missão Concluída"); }
		elseif ($concluida) { $relogiotitle = $motivo; }
		$botao = "<img src=\"images/aceitar_missao_off.png\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated\" title=\"".htmlspecialchars(strip_tags($motivo))."\" border=\"0\">";
	}
	$pedra = pedrasprogresso($porcentagem);

	// Selo de situação ao lado do título e botão grande dentro dos detalhes (mesma ação do ícone pequeno).
	$idm = intval($m["id"]);
	if ($andamento && mx_completa($m, $estado, $userrow) && $entregaaqui && count(mx_opcoes($m)) != 2) {
		$selo = array(t("Pronta para entregar"), "#1f6fb8");
		$acao = "<form action=\"missoesextras.php?do=entregar\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"id\" value=\"$idm\" /><button type=\"submit\" class=\"btnjogo bj-confirmar\"><img src=\"images/aceitar_missao.png\" width=\"16\" height=\"16\" alt=\"\" style=\"image-rendering:pixelated\"> ".t("Entregar missão")."</button></form>";
	} elseif ($andamento && mx_completa($m, $estado, $userrow) && $entregaaqui) {
		$selo = array(t("Pronta para entregar"), "#1f6fb8");
		$acao = "<span class=\"small\">".t("Escolha uma das opções logo abaixo do título para entregar.")."</span>";
	} elseif ($andamento) {
		$selo = mx_completa($m, $estado, $userrow) ? array(t("Pronta para entregar"), "#1f6fb8") : array(t("Em andamento"), "#b8860b");
		$acao = "<span class=\"small\">".mx_situacao($m, $estado, $userrow)."</span>";
	} elseif ($motivo == "") {
		$selo = array(t("Disponível"), "#3f7f2a");
		$acao = "<form action=\"missoesextras.php?do=aceitar\" method=\"post\" style=\"margin:0\">$csrf<input type=\"hidden\" name=\"id\" value=\"$idm\" /><button type=\"submit\" class=\"btnjogo bj-confirmar\"><img src=\"images/aceitar_missao.png\" width=\"16\" height=\"16\" alt=\"\" style=\"image-rendering:pixelated\"> ".t("Aceitar missão")."</button></form>";
	} else {
		$concl = ($estado && $estado["estado"] == "concluida");
		$selo = $concl ? array(t("Concluída"), "#7a6a55") : array(t("Indisponível"), "#8b3a2a");
		$acao = "<span class=\"small\" style=\"color:#7a3d00\">".$motivo."</span>";
	}
	$seloh = "<span class=\"mxselo\" style=\"background:".$selo[1]."\">".htmlspecialchars($selo[0], ENT_QUOTES, "UTF-8")."</span>";
	// O tempo fica escrito junto do botão (o título do relógio não aparece no celular).
	$tempoh = "<div style=\"margin:0 0 6px;color:#613003;font-size:11px\"><img src=\"images/".$relogio.".gif?v=2\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated;vertical-align:-4px\"> <b>".t("Tempo:")."</b> ".$relogiotitle."</div>";
	$blocoacao = "<div id=\"acaox$idm\" class=\"mxacao\" style=\"display:none\">".$tempoh.$acao."</div>";

	// Dados do mostrarquest(): requerimento = história + objetivo; nível = rank + nível mínimo.
	$requerimento = mx_texto($m, "descricao")."<br><br>".mx_objetivo($m, $estado, $userrow);
	if ($andamento) { $requerimento .= "<br>".mx_situacao($m, $estado, $userrow); }
	$nivel = trim(t($m["rank_texto"])." ".tf("Nível mínimo: %s.", intval($m["nivel_minimo"])).($m["graduacao_minima"] != "" ? " ".tf("Graduação: %s.", t($m["graduacao_minima"])) : ""));
	$conclusao = t("Não Obrigatória.");
	if (mx_trocavilaid($m) > 0) { $conclusao .= " ".tf("Muda a sua vila natal; depois, é preciso esperar %s dia(s) para trocar de novo.", mx_trocaviladias()); }
	elseif (mx_trocadifid($m) > 0) { $conclusao .= " ".tf("Muda a sua dificuldade para %s; depois, é preciso esperar %s dia(s) para trocar de novo.", mx_nomedificuldade(mx_trocadifid($m)), mx_trocadifdias()); }
	elseif (intval($m["repetivel"]) == 1) { $conclusao .= " ".mx_repeticaotexto($m); }
	if (intval($m["limite_min"]) > 0) { $conclusao .= " ".tf("Limite de tempo: %s minuto(s).", intval($m["limite_min"])); }
	// Botões de mapa do mostrarquest() a partir do passo atual (item, NPC/coordenada e inimigo).
	$passos = mx_passos($m);
	$p = count($passos) ? $passos[min($andamento ? intval($estado["etapa_atual"]) : 0, count($passos) - 1)] : false;
	$item = ""; $monstro = ""; $local = "";
	if ($p) {
		if ($p["tipo"] == "entregar_item" || $p["tipo"] == "coletar") { $item = $p["alvo"]; }
		$idmon = ($p["tipo"] == "derrotar_monstro") ? intval($p["alvo"]) : (($p["tipo"] == "coletar") ? intval($p["monstro"]) : 0);
		if ($idmon > 0) { $mon = mx_monstro($idmon); $monstro = $mon ? $mon["name"] : ""; }
		if ($p["tipo"] == "visitar_local") { $local = str_replace(",", "/", $p["alvo"]); }
		$npcalvo = ($p["tipo"] == "falar_com_npc") ? intval($p["alvo"]) : intval($p["npc"]);
		if ($npcalvo > 0) { $n = mx_npcs(); if (isset($n[$npcalvo])) { $local = intval($n[$npcalvo]["latitude"])."/".intval($n[$npcalvo]["longitude"]); } }
		if ($p["tipo"] == "viagem") { $d = mx_destino($m); if ($d) { $local = $d["lat"]."/".$d["lon"]; } }
	}
	$extras = mx_argsmapa(array("mapa_item" => $item, "mapa_local" => $local, "mapa_monstro" => $monstro));
	$num = "x".intval($m["id"]);

	$abrir = "mostrarquest('$num', '".jsarg(mx_recompensatexto($m))."', '".jsarg($requerimento)."', '".jsarg(mx_localtexto($m))."', '".jsarg($nivel)."', '".jsarg($conclusao)."'$extras);mxacao('$num',1)";
	return "<table width=\"100%\"><tr bgcolor=\"#613003\"><td width=\"*\" class=\"mxtitulo\" onclick=\"if(document.getElementById('acao$num').style.display=='none'){".$abrir."}else{escondertreino('$num');mxacao('$num',0)}\" title=\"".t("Clique para ver os detalhes")."\"><font color=\"white\">".$titulo."</font> ".$seloh."</td><td width=\"17\"><img src=\"images/".$relogio.".gif?v=2\" width=\"17\" height=\"17\" style=\"image-rendering:pixelated\" title=\"".htmlspecialchars(strip_tags($relogiotitle))."\"></td><td width=\"17\">$botao</td><td width=\"84\">$pedra</td><td width=\"20\"><a href=\"javascript:".$abrir."\"><img src=\"images/setabaixo.gif\" title=\"".t("Mostrar Dados")."\" border=\"0\"></a></td><td width=\"20\"><a href=\"javascript:escondertreino('$num');mxacao('$num',0)\"><img src=\"images/setacima.gif\" title=\"".t("Esconder Dados")."\" border=\"0\"></a></td></tr></table>$abaixo<div id=\"elemento$num\"></div>$blocoacao<br>";

}

function mx_estilo() {
	return "<style>.mxtitulo{cursor:pointer}.mxtitulo:hover font{text-decoration:underline}.mxselo{display:inline-block;margin-left:4px;padding:0 6px;border-radius:8px;color:#fff;font-size:10px;font-weight:bold;line-height:15px;vertical-align:1px;white-space:nowrap}"
		.".mxacao{text-align:center;background:#fff1c7;border:1px solid #c9b47a;border-top:0;padding:6px}</style>"
		."<script>function mxacao(n,v){var d=document.getElementById('acao'+n);if(d){d.style.display=v?'block':'none';}}</script>";
}

function mx_listahtml($userrow, $townrow, $npc) { // Painel com as missões do lugar e as aceitas em outros lugares.

	$estados = mx_estadojogador($userrow["id"]);
	$aqui = mx_extrasdolugar($townrow, $npc);
	$html = "";
	$quadrotroca = false; $quadrodif = false;
	foreach ($aqui as $id => $m) {
		if (!mx_requisitook($m, $estados)) { continue; } // continuação de uma escolha: só aparece depois dela.
		$e = isset($estados[$id]) ? $estados[$id] : false;
		if (mx_trocavilaid($m) > 0) {
			$quadrotroca = true;
			// Transferência para a própria vila natal não aparece (a não ser que já esteja em andamento).
			$destino = mx_vilaparatroca(mx_trocavilaid($m));
			if ((!$e || $e["estado"] != "andamento") && (!$destino || (string)$destino["alinhamento"] === (string)$userrow["alinhamento"])) { continue; }
		}
		if (mx_trocadifid($m) > 0) {
			$quadrodif = true;
			// Provação para a dificuldade atual não aparece (a não ser que já esteja em andamento).
			if ((!$e || $e["estado"] != "andamento") && mx_trocadifid($m) == intval($userrow["difficulty"])) { continue; }
		}
		$html .= mx_linha($m, $e, $userrow, $id, $estados, mx_entregaaqui($m, $townrow, $npc));
	}
	// Missões em andamento aceitas em outro lugar (a viagem é entregue no destino).
	$outras = "";
	foreach ($estados as $id => $e) {
		if (isset($aqui[$id]) || $e["estado"] != "andamento") { continue; }
		$m = mx_extra($id);
		if (!$m) { continue; }
		$ea = mx_entregaaqui($m, $townrow, $npc);
		if ($npc && !$ea) { continue; } // No NPC do mapa, só as missões dele (e as que se entregam a ele); o resto fica no Painel de Missões.
		$outras .= mx_linha($m, $e, $userrow, $id, $estados, $ea);
	}
	if ($quadrotroca) { $html = mx_trocavilaquadro($userrow).$html; }
	if ($quadrodif) { $html = mx_trocadifquadro($userrow).$html; }
	if ($html == "" && $npc == false) { $html = "<center>".t("Não há missões neste lugar no momento.")."</center><br>"; }
	if ($outras != "") { $html .= "<center><b>".t("Missões em andamento aceitas em outro lugar")."</b></center><br>".$outras; }
	// Título clicável, selo de situação e o botão grande de cada missão (aparece junto com os detalhes).
	$html = mx_estilo()
		."<div class=\"small\" style=\"text-align:center;color:#613003;margin:0 0 6px\">".t("Clique no nome de uma missão para ver os detalhes e aceitar ou entregar.")."</div>".$html;
	return $html;

}

function mx_vida($usuarioid) { // HP atual e horário do último descanso (lidos do banco na hora).

	$q = doquery("SELECT currenthp, ultimodescanso, dropcode FROM {{table}} WHERE id='".intval($usuarioid)."' LIMIT 1", "users");
	$r = mysqli_fetch_array($q);
	return $r ? array("hp" => intval($r["currenthp"]), "descanso" => (string)$r["ultimodescanso"], "dropcode" => intval($r["dropcode"])) : array("hp" => 0, "descanso" => "", "dropcode" => 0);

}

function mx_dadosiniciais($m, $p, $userrow) { // Estado inicial do jogador ao começar um passo: viagem (passos, distância) e sobrevivência (HP, descanso).

	$dados = array();
	if ($p && $p["tipo"] == "viagem") {
		$d = mx_destino($m);
		$dados["passos"] = 0;
		$dados["dist"] = $d ? abs($d["lat"] - intval($userrow["latitude"])) + abs($d["lon"] - intval($userrow["longitude"])) : 0;
	}
	if ($p && $p["tipo"] == "sobreviver") { $v = mx_vida($userrow["id"]); $dados["hp"] = $v["hp"]; $dados["descanso"] = $v["descanso"]; }
	return $dados;

}

function mx_aceitar($userrow, $id, $townrow, $npc) { // Aceita uma missão. Devolve a mensagem (traduzida).

	$m = mx_extra($id);
	$aqui = mx_extrasdolugar($townrow, $npc);
	if (!$m || !isset($aqui[intval($id)])) { return t("Essa missão não está disponível aqui."); }
	$estados = mx_estadojogador($userrow["id"]);
	$estado = isset($estados[intval($id)]) ? $estados[intval($id)] : false;
	$motivo = mx_podeaceitar($m, $estado, $userrow, $estados);
	if ($motivo != "") { return $motivo; }
	$uid = intval($userrow["id"]); $mid = intval($m["id"]);
	$passos = mx_passos($m);
	$dados = mx_jsonsql(mx_dadosiniciais($m, count($passos) ? $passos[0] : false, $userrow));
	if ($estado && $estado["estado"] == "falhou") {
		doquery("UPDATE {{table}} SET estado='andamento', progresso='0', etapa_atual='0', dados='$dados', aceita_em=NOW(), concluida_em=NULL WHERE usuario_id='$uid' AND missao_id='$mid' AND estado='falhou' LIMIT 1", "missoesextras_jogador");
	} elseif ($estado) {
		// Repetível: volta para "andamento" só se continuar concluída e o tempo de espera tiver passado (evita duplo clique).
		doquery("UPDATE {{table}} SET estado='andamento', progresso='0', etapa_atual='0', dados='$dados', aceita_em=NOW(), concluida_em=NULL WHERE usuario_id='$uid' AND missao_id='$mid' AND estado='concluida' AND concluida_em <= (NOW() - INTERVAL ".intval($m["intervalo_horas"])." HOUR) LIMIT 1", "missoesextras_jogador");
	} else {
		doquery("INSERT IGNORE INTO {{table}} SET usuario_id='$uid', missao_id='$mid', progresso='0', etapa_atual='0', dados='$dados', estado='andamento', aceita_em=NOW()", "missoesextras_jogador");
	}
	global $link;
	if (mysqli_affected_rows($link) != 1) { return t("Você já aceitou essa missão."); }
	$aviso = (intval($m["limite_min"]) > 0) ? " ".tf("Você tem %s minuto(s)!", intval($m["limite_min"])) : "";
	return tf("Missão <b>%s</b> aceita! Boa sorte.", mx_titulo($m)).$aviso;

}

function mx_entregar($userrow, $id, $townrow, $npc) { // Entrega uma missão: confere, consome itens/Ryou, aplica a escolha e paga a recompensa.

	global $link;
	$m = mx_extra($id);
	if (!$m || !mx_entregaaqui($m, $townrow, $npc)) { return t("Essa missão não é entregue aqui."); }
	$estados = mx_estadojogador($userrow["id"]);
	$estado = isset($estados[intval($id)]) ? $estados[intval($id)] : false;
	if ($estado && $estado["estado"] == "falhou") { return t("O tempo dessa missão acabou. Aceite de novo para tentar outra vez."); }
	if (!$estado || $estado["estado"] != "andamento") { return t("Você não está fazendo essa missão."); }
	if (!mx_completa($m, $estado, $userrow)) { return t("Você ainda não completou o objetivo dessa missão."); }
	// Troca de vila natal: confere de novo (a vila pode ter mudado no painel, ou o jogador trocou por outra missão).
	$trocavila = false;
	if (mx_trocavilaid($m) > 0) {
		$mt = mx_trocavilamotivo($m, $userrow, $estados, true);
		if ($mt != "") { return $mt; }
		$trocavila = mx_vilaparatroca(mx_trocavilaid($m));
	}
	// Troca de dificuldade: idem (a dificuldade pode ter mudado por outra provação ou pelo painel).
	$trocadif = 0;
	if (mx_trocadifid($m) > 0) {
		$mt = mx_trocadifmotivo($m, $userrow, $estados, true);
		if ($mt != "") { return $mt; }
		$trocadif = mx_trocadifid($m);
	}

	// Escolha: a opção (0 ou 1) vem do formulário; a recompensa dela soma com a da missão.
	$opcoes = mx_opcoes($m);
	$escolha = -1; $exp = intval($m["recompensa_exp"]); $ryou = intval($m["recompensa_ryou"]); $itens = array();
	if ($m["recompensa_item"] != "") { $itens[] = $m["recompensa_item"]; }
	foreach (mx_itensextras($m) as $it) { $itens[] = $it; }
	if (count($opcoes) == 2) {
		$op = pegartexto($_POST, "opcao");
		if ($op !== "0" && $op !== "1") { return t("Escolha uma das opções."); }
		$escolha = intval($op);
		$exp += intval($opcoes[$escolha]["exp"]); $ryou += intval($opcoes[$escolha]["ryou"]);
		if ($opcoes[$escolha]["item"] != "") { $itens[] = $opcoes[$escolha]["item"]; }
	}

	// Monta o UPDATE do jogador: itens entregues saem da mochila, Ryou pago sai do bolso (só o último passo é cobrado aqui).
	$passos = mx_passos($m);
	$ultimo = count($passos) ? $passos[count($passos) - 1] : false;
	$sets = array(); $onde = "";
	$bp = array(); for ($i = 1; $i <= 8; $i++) { $bp[$i] = $userrow["bp".$i]; }
	if ($ultimo && $ultimo["tipo"] == "entregar_item" && intval($ultimo["npc"]) == 0) {
		$falta = mx_qtdpasso($ultimo);
		for ($i = 1; $i <= 8 && $falta > 0; $i++) {
			$parte = explode(",", (string)$bp[$i]);
			// Pilha: tira só as unidades que faltam entregar.
			if ($parte[0] === $ultimo["alvo"]) { $tira = min($falta, qtditem($bp[$i])); $onde .= " AND bp$i='".addslashes($bp[$i])."'"; $bp[$i] = itemcomqtd($bp[$i], qtditem($bp[$i]) - $tira); $sets[] = "bp$i='".addslashes($bp[$i])."'"; $falta -= $tira; }
		}
	}
	// Jutsu de recompensa (mx_recompensajutsu): aprende no mesmo UPDATE (FIND_IN_SET no WHERE: não grava duas vezes),
	// ou o pergaminho entra na lista de itens, ou vira Ryou + experiência.
	$rj = mx_recompensajutsu($m, $userrow);
	if ($rj["modo"] == "ensinar") {
		$idj = intval($rj["spell"]["id"]);
		$sets[] = "spells=CONCAT(spells, ',', '$idj')";
		$onde .= " AND NOT FIND_IN_SET('$idj', spells) AND CHAR_LENGTH(spells) < 250";
	} elseif ($rj["modo"] == "pergaminho") { $itens[] = $rj["item"]; }
	elseif ($rj["modo"] == "compensacao") { $ryou += $rj["ryou"]; $exp += $rj["exp"]; }
	$gold = intval($userrow["gold"]);
	if ($ultimo && $ultimo["tipo"] == "pagar_ryou") { $custo = mx_qtdpasso($ultimo); $gold -= $custo; $onde .= " AND gold>='$custo'"; }
	$gold = min($gold + $ryou, 16777215);
	$sets[] = "gold='$gold'";
	if ($exp > 0) { $sets[] = "experience=LEAST(experience+".$exp.", 16777214)"; }
	$avisoitem = "";
	$banco = ($userrow["bancogeral"] == "None") ? "" : $userrow["bancogeral"];
	$nobanco = false;
	foreach ($itens as $nomeitem) {
		$item = mx_itemcompleto($nomeitem);
		if ($item === false) { continue; }
		// Mochila: junta numa pilha igual ou usa um espaço livre (mochilaguardar() numa cópia dos espaços).
		$copia = array(); for ($i = 1; $i <= 8; $i++) { $copia["bp".$i] = $bp[$i]; }
		$espacos = mochilaguardar($copia, $item);
		if ($espacos !== false) {
			foreach ($espacos as $e) { $i = intval(substr($e, 2)); $bp[$i] = $copia[$e]; $sets = array_values(array_filter($sets, function ($x) use ($e) { return strpos($x, $e."=") !== 0; })); $sets[] = "$e='".addslashes($bp[$i])."'"; }
		}
		else {
			$banco .= $item.";"; $nobanco = true;
			$avisoitem .= " ".tf("O item <b>%s</b> foi transferido para seu banco, uma vez que sua mochila já está cheia.", t($nomeitem));
		}
	}
	if ($nobanco) { $sets[] = "bancogeral='".addslashes($banco)."'"; }
	// Trava otimista: a mochila e o banco têm que estar exatamente como foram lidos (evita duplicar/perder item
	// se outra requisição mexer neles ao mesmo tempo); o ouro já é conferido no WHERE abaixo.
	for ($i = 1; $i <= 8; $i++) { if (strpos($onde, " AND bp$i=") === false) { $onde .= " AND bp$i='".addslashes((string)$userrow["bp".$i])."'"; } }
	$onde .= " AND bancogeral='".addslashes((string)$userrow["bancogeral"])."'";
	// Histórico (formato neutro i18n, separado por ";;").
	$aviso = i18n("Missão concluída: %s. Você ganhou %s de experiência e %s Ryou.", $m["titulo"], $exp, $ryou);
	$historico = ($userrow["historico"] == "None" || $userrow["historico"] == "") ? $aviso : $userrow["historico"].";;".$aviso;
	// Troca de vila natal: a vila e a data da troca vão no MESMO UPDATE da recompensa, com o alinhamento antigo e a data
	// da última troca conferidos no WHERE (duplo clique / duas missões ao mesmo tempo: só uma grava).
	$avisotroca = "";
	if ($trocavila) {
		if (!function_exists('vilanataldoalinhamento')) { include_once('cidadesconteudo.php'); }
		$antiga = vilanataldoalinhamento($userrow["alinhamento"]);
		$nomeantiga = $antiga ? $antiga["name"] : (string)$userrow["alinhamento"];
		$sets[] = "alinhamento='".addslashes($trocavila["alinhamento"])."'";
		$sets[] = "vilatroca_em='".time()."'";
		$onde .= " AND alinhamento='".addslashes((string)$userrow["alinhamento"])."' AND vilatroca_em='".intval($userrow["vilatroca_em"])."'";
		$historico .= ";;".i18n("Você trocou de vila natal: %s → %s.", $nomeantiga, $trocavila["name"]);
		$sets = array_values(array_filter($sets, function ($x) { return strpos($x, "historico=") !== 0; }));
		$sets[] = "historico='".addslashes($historico)."'";
		$avisotroca = " ".tf("A partir de agora a sua vila natal é a <b>%s</b>: é para lá que você volta ao ser derrotado e com o Pergaminho de Retorno.", t($trocavila["name"]));
		// Tudo numa transação: a linha do jogador fica travada até o fim (nada de estado pela metade).
		doquery("START TRANSACTION", "users");
		$trava = mysqli_fetch_array(doquery("SELECT alinhamento, vilatroca_em FROM {{table}} WHERE id='".intval($userrow["id"])."' FOR UPDATE", "users"));
		if (!$trava || (string)$trava["alinhamento"] !== (string)$userrow["alinhamento"] || intval($trava["vilatroca_em"]) != intval($userrow["vilatroca_em"])) {
			doquery("ROLLBACK", "users");
			return t("Sua vila natal acabou de mudar. Atualize a página.");
		}
	}

	// Troca de dificuldade: mesma receita da troca de vila (dificuldade antiga, data da última troca e "fora de luta"
	// conferidas no WHERE do UPDATE, tudo numa transação com a linha do jogador travada).
	$avisodif = "";
	if ($trocadif > 0) {
		$sets[] = "difficulty='".$trocadif."'";
		$sets[] = "dificuldadetroca_em='".time()."'";
		$onde .= " AND difficulty='".intval($userrow["difficulty"])."' AND dificuldadetroca_em='".intval($userrow["dificuldadetroca_em"])."' AND currentaction<>'Fighting'";
		global $controlrow; mx_trocadifdias(); // carrega o control (nomes das dificuldades)
		$difantiga = intval($userrow["difficulty"]);
		$historico .= ";;".i18n("Você trocou de dificuldade: %s → %s.", isset($controlrow["diff".$difantiga."name"]) ? $controlrow["diff".$difantiga."name"] : "?", $controlrow["diff".$trocadif."name"]);
		$sets = array_values(array_filter($sets, function ($x) { return strpos($x, "historico=") !== 0; }));
		$sets[] = "historico='".addslashes($historico)."'";
		$avisodif = " ".tf("A partir de agora a sua dificuldade é <b>%s</b>: vale já na próxima luta (HP e dano dos inimigos, experiência e Ryou).", mx_nomedificuldade($trocadif));
		doquery("START TRANSACTION", "users");
		$trava = mysqli_fetch_array(doquery("SELECT difficulty, dificuldadetroca_em FROM {{table}} WHERE id='".intval($userrow["id"])."' FOR UPDATE", "users"));
		if (!$trava || intval($trava["difficulty"]) != intval($userrow["difficulty"]) || intval($trava["dificuldadetroca_em"]) != intval($userrow["dificuldadetroca_em"])) {
			doquery("ROLLBACK", "users");
			return t("Sua dificuldade acabou de mudar. Atualize a página.");
		}
	}
	$transacao = ($trocavila || $trocadif > 0);

	// Primeiro marca a missão como concluída (só uma requisição consegue), depois paga.
	$dados = mx_dados($estado);
	if ($escolha >= 0) { $dados["opcao"] = (string)$escolha; }
	doquery("UPDATE {{table}} SET estado='concluida', concluida_em=NOW(), dados='".mx_jsonsql($dados)."' WHERE usuario_id='".intval($userrow["id"])."' AND missao_id='".intval($m["id"])."' AND estado='andamento' LIMIT 1", "missoesextras_jogador");
	if (mysqli_affected_rows($link) != 1) { if ($transacao) { doquery("ROLLBACK", "users"); } return t("Essa missão já foi entregue."); }
	doquery("UPDATE {{table}} SET ".implode(", ", $sets)." WHERE id='".intval($userrow["id"])."' AND gold='".intval($userrow["gold"])."'$onde LIMIT 1", "users");
	if ($transacao) {
		if (mysqli_affected_rows($link) != 1) { doquery("ROLLBACK", "users"); return t("Você ainda não completou o objetivo dessa missão."); }
		doquery("COMMIT", "users");
		require_once(__DIR__."/painelseguranca.php");
		if ($trocavila) { registrarlog("troca_vila", intval($userrow["id"]), $userrow["charname"].": ".$userrow["alinhamento"]." -> ".$trocavila["alinhamento"]." (missao ".intval($m["id"]).")"); }
		if ($trocadif > 0) { registrarlog("troca_dificuldade", intval($userrow["id"]), $userrow["charname"].": ".intval($userrow["difficulty"])." -> ".$trocadif." (missao ".intval($m["id"]).")"); }
	}
	if (mysqli_affected_rows($link) != 1 && !$transacao) {
		// Mochila/Ryou mudaram no meio do caminho: desfaz a conclusão.
		doquery("UPDATE {{table}} SET estado='andamento', concluida_em=NULL, dados='".addslashes((string)$estado["dados"])."' WHERE usuario_id='".intval($userrow["id"])."' AND missao_id='".intval($m["id"])."' LIMIT 1", "missoesextras_jogador");
		return t("Você ainda não completou o objetivo dessa missão.");
	}
	$desfecho = ($escolha >= 0 && mx_textoopcao($opcoes[$escolha], "texto") != "") ? mx_textoopcao($opcoes[$escolha], "texto")." " : "";
	$recebeu = array();
	if ($exp > 0) { $recebeu[] = tf("%s de experiência", $exp); }
	if ($ryou > 0) { $recebeu[] = tf("%s Ryou", $ryou); }
	foreach (array_count_values($itens) as $nomeitem => $n) { $recebeu[] = tf("Item: <b>%s</b>", ($n > 1 ? $n."x " : "").nomeitemhtml($nomeitem)); }
	if ($rj["modo"] == "ensinar") { $recebeu[] = tf("o jutsu <b>%s</b> (aprendido!)", t($rj["spell"]["name"])); }
	elseif ($rj["modo"] == "pergaminho") { $avisoitem .= " ".(jutsuconhece($userrow, $rj["spell"]["id"]) ? tf("Você já conhecia o jutsu %s, então recebeu o pergaminho dele (pode trocá-lo ou guardá-lo).", t($rj["spell"]["name"])) : tf("Você ainda não pode aprender o jutsu %s, então recebeu o pergaminho dele para estudar depois.", t($rj["spell"]["name"]))); }
	elseif ($rj["modo"] == "compensacao") { $avisoitem .= " ".tf("Você não pôde receber o jutsu %s (já o conhece ou não cumpre os requisitos); no lugar dele ganhou %s Ryou e %s de experiência (já somados acima).", t($rj["spell"]["name"]), $rj["ryou"], $rj["exp"]); }
	// Avatar de recompensa (recompensa_avatar = id em naruto_avatares, funcoesavatares.php). Se já tinha, vira Ryou.
	$idav = intval(isset($m["recompensa_avatar"]) ? $m["recompensa_avatar"] : 0);
	if ($idav > 0) {
		include_once('funcoesavatares.php');
		if (avatarliberar($userrow["id"], $idav)) { $recebeu[] = tf("o avatar <b>%s</b> (liberado!)", avatarnome($idav)); }
		else { $extra = 300 * max(1, intval($m["nivel_minimo"])); doquery("UPDATE {{table}} SET gold=LEAST(gold+'$extra',16777215) WHERE id='".intval($userrow["id"])."' LIMIT 1", "users"); $avisoitem .= " ".tf("Você já tinha o avatar %s; no lugar dele ganhou %s Ryou.", avatarnome($idav), $extra); }
	}
	if ($transacao && count($recebeu) == 0) { return $desfecho.tf("Parabéns! Você completou a missão <b>%s</b>.", mx_titulo($m)).$avisoitem.$avisotroca.$avisodif; } // transferência sem prêmio: sem o "recebeu: Nenhuma."
	return $desfecho.tf("Parabéns! Você completou a missão <b>%s</b> e recebeu: %s", mx_titulo($m), count($recebeu) ? implode(", ", $recebeu)."." : t("Nenhuma.")).$avisoitem.$avisotroca.$avisodif;

}

/* ------------------------------------------------------------------------
   Eventos do jogo que fazem as missões andarem (chamados por fight.php, explore.php e falapersonagens.php).
   ------------------------------------------------------------------------ */

function mx_ativas($usuarioid) { // Missões em andamento do jogador, com as colunas da missão e do progresso juntas (cronometradas vencidas falham aqui).

	$lista = array();
	$tabm = mx_tabela("missoesextras");
	$q = doquery("SELECT j.progresso, j.etapa_atual, j.estado, j.dados, UNIX_TIMESTAMP(j.aceita_em) AS aceita_ts, m.* FROM {{table}} j JOIN $tabm m ON m.id=j.missao_id WHERE j.usuario_id='".intval($usuarioid)."' AND j.estado='andamento' AND m.categoria='extra'", "missoesextras_jogador");
	while ($r = mysqli_fetch_array($q, MYSQLI_ASSOC)) {
		if (mx_vencida($r)) { mx_falhar($usuarioid, $r["id"]); continue; }
		$lista[] = $r;
	}
	return $lista;

}

function mx_gravarpasso($usuarioid, $a, $progresso, $dados, $userrow = null) { // Grava a contagem do passo atual; passo contado e cumprido (e não o último) avança para o próximo.
	// O WHERE com a etapa e o progresso antigos impede que duas requisições ao mesmo tempo contem duas vezes.

	global $link;
	$passos = mx_passos($a);
	$n = count($passos);
	$i = intval($a["etapa_atual"]);
	$etapa = $i;
	$avancou = false;
	if ($i < $n - 1 && mx_passocontado($passos[$i]) && $progresso >= mx_qtdpasso($passos[$i])) {
		$etapa = $i + 1; $progresso = 0; $avancou = true;
		$u = ($userrow !== null) ? $userrow : array("id" => $usuarioid, "latitude" => 0, "longitude" => 0);
		$dados = array_merge(isset($dados["opcao"]) ? array("opcao" => $dados["opcao"]) : array(), mx_dadosiniciais($a, $passos[$etapa], $u));
	}
	doquery("UPDATE {{table}} SET progresso='".intval($progresso)."', etapa_atual='$etapa', dados='".mx_jsonsql($dados)."' WHERE usuario_id='".intval($usuarioid)."' AND missao_id='".intval($a["id"])."' AND estado='andamento' AND etapa_atual='$i' AND progresso='".intval($a["progresso"])."' LIMIT 1", "missoesextras_jogador");
	return (mysqli_affected_rows($link) == 1) ? ($avancou ? "avancou" : "ok") : "";

}

function mx_passoatual($a) { $passos = mx_passos($a); $i = intval($a["etapa_atual"]); return isset($passos[$i]) ? $passos[$i] : false; } // Passo em que o jogador está.

function mx_falouconpc($userrow, $npc) { // Conversar com um NPC: passos "falar_com_npc" dele e entregas de item para ele (cadeia). Devolve aviso.

	global $link;
	$avisos = array();
	foreach (mx_ativas($userrow["id"]) as $a) {
		$p = mx_passoatual($a);
		if (!$p) { continue; }
		$qtd = mx_qtdpasso($p);
		$fez = false;
		if ($p["tipo"] == "falar_com_npc" && intval($p["alvo"]) == intval($npc["id"]) && intval($a["progresso"]) < $qtd) { $fez = true; }
		elseif ($p["tipo"] == "entregar_item" && intval($p["npc"]) == intval($npc["id"]) && intval($a["progresso"]) < $qtd && mx_contaritens($userrow, $p["alvo"]) >= $qtd) {
			// Entrega no meio da cadeia: tira os itens da mochila agora (com conferência no WHERE).
			$sets = array(); $onde = ""; $falta = $qtd;
			for ($i = 1; $i <= 8 && $falta > 0; $i++) {
				$parte = explode(",", (string)$userrow["bp".$i]);
				if ($parte[0] === $p["alvo"]) { $tira = min($falta, qtditem($userrow["bp".$i])); $onde .= " AND bp$i='".addslashes($userrow["bp".$i])."'"; $userrow["bp".$i] = itemcomqtd($userrow["bp".$i], qtditem($userrow["bp".$i]) - $tira); $sets[] = "bp$i='".addslashes($userrow["bp".$i])."'"; $falta -= $tira; }
			}
			doquery("UPDATE {{table}} SET ".implode(", ", $sets)." WHERE id='".intval($userrow["id"])."'$onde LIMIT 1", "users");
			$fez = (mysqli_affected_rows($link) == 1);
			if ($fez) { $avisos[] = tf("Você entregou %s x <b>%s</b> para %s.", $qtd, t($p["alvo"]), mx_nome($npc["nome"])); }
		}
		if (!$fez) { continue; }
		$r = mx_gravarpasso($userrow["id"], $a, $qtd, mx_dados($a), $userrow);
		if ($r == "avancou") {
			$a["etapa_atual"] = intval($a["etapa_atual"]) + 1;
			$avisos[] = tf("Etapa concluída em <b>%s</b>! Próximo objetivo: %s", mx_titulo($a), mx_objetivopasso($a, mx_passoatual($a)));
		} elseif ($r == "ok") {
			$avisos[] = t("Recado entregue! Volte para quem lhe deu a missão.");
		}
	}
	return implode("<br>", $avisos);

}

}

// Ganchos chamados por outros arquivos (definidos fora do bloco acima para o function_exists() deles funcionar).
if (!function_exists('missoesextras_vitoria')) {
function missoesextras_vitoria($userrow, $monsterrow) { // fight.php (vitória): conta derrotas, drops coletados e vitórias seguidas.

	if (!$monsterrow || !isset($monsterrow["id"])) { return; }
	$ativas = mx_ativas($userrow["id"]);
	if (count($ativas) == 0) { return; }
	// Drop desta vitória: o fight.php acabou de gravar users.dropcode (diferente do que havia antes da luta = drop novo).
	// Funciona com qualquer regra de sorteio de drop (tabela drops ou monster_drops), pois só olha o resultado.
	$vida = mx_vida($userrow["id"]);
	$dropnome = "";
	if ($vida["dropcode"] > 0 && $vida["dropcode"] != intval($userrow["dropcode"])) {
		$q = doquery("SELECT name FROM {{table}} WHERE id='".$vida["dropcode"]."' LIMIT 1", "drops");
		if ($d = mysqli_fetch_array($q)) { $dropnome = $d["name"]; }
	}
	$idmon = intval($monsterrow["id"]);
	foreach ($ativas as $a) {
		$p = mx_passoatual($a);
		if (!$p) { continue; }
		$qtd = mx_qtdpasso($p); $prog = intval($a["progresso"]); $dados = mx_dados($a);
		if ($p["tipo"] == "derrotar_monstro" && intval($p["alvo"]) == $idmon && $prog < $qtd) {
			mx_gravarpasso($userrow["id"], $a, $prog + 1, $dados, $userrow);
		} elseif ($p["tipo"] == "coletar" && $prog < $qtd) {
			// Conta o drop de verdade com esse nome; senão, a chance própria da missão (config "chance" %, só do inimigo "monstro" se houver).
			$certo = (intval($p["monstro"]) == 0 || intval($p["monstro"]) == $idmon);
			$achou = ($dropnome !== "" && $dropnome === $p["alvo"] && $certo);
			if (!$achou && $certo && intval($p["chance"]) > 0 && rand(1, 100) <= intval($p["chance"])) { $achou = true; }
			if ($achou) { mx_gravarpasso($userrow["id"], $a, $prog + 1, $dados, $userrow); }
		} elseif ($p["tipo"] == "sobreviver") {
			if (intval($monsterrow["level"]) >= intval($p["nivel"]) && $prog < $qtd) { $prog++; }
			$dados["hp"] = $vida["hp"]; $dados["descanso"] = $vida["descanso"]; // HP depois desta luta: a próxima conferência parte daqui.
			mx_gravarpasso($userrow["id"], $a, $prog, $dados, $userrow);
		} elseif ($p["tipo"] == "viagem") {
			// nada: viagem conta passos (missoesextras_posicao).
		}
	}

}}

if (!function_exists('missoesextras_posicao')) {
function missoesextras_posicao($userrow) { // explore.php (cada passo no mapa): visitar local, passos da viagem e conferência de cura da sobrevivência.

	$ativas = mx_ativas($userrow["id"]);
	if (count($ativas) == 0) { return; }
	$alvo = intval($userrow["latitude"]).",".intval($userrow["longitude"]);
	$vida = null;
	foreach ($ativas as $a) {
		$p = mx_passoatual($a);
		if (!$p) { continue; }
		$dados = mx_dados($a);
		if ($p["tipo"] == "visitar_local" && $p["alvo"] === $alvo && intval($a["progresso"]) < 1) {
			mx_gravarpasso($userrow["id"], $a, mx_qtdpasso($p), $dados, $userrow);
		} elseif ($p["tipo"] == "viagem") {
			$dados["passos"] = (isset($dados["passos"]) ? intval($dados["passos"]) : 0) + 1;
			if (intval($p["quantidade"]) > 0 && $dados["passos"] > intval($p["quantidade"])) { mx_falhar($userrow["id"], $a["id"]); continue; } // passou do limite: a escolta se perdeu.
			mx_gravarpasso($userrow["id"], $a, intval($a["progresso"]), $dados, $userrow);
		} elseif ($p["tipo"] == "sobreviver") {
			// Ganhou HP desde a última vitória (pousada, item, jutsu médico) ou descansou: a sequência volta a zero.
			if ($vida === null) { $vida = mx_vida($userrow["id"]); }
			$curou = !isset($dados["hp"]) || $vida["hp"] > intval($dados["hp"]) || $vida["descanso"] !== (string)(isset($dados["descanso"]) ? $dados["descanso"] : "");
			if ($curou) {
				$dados["hp"] = $vida["hp"]; $dados["descanso"] = $vida["descanso"];
				mx_gravarpasso($userrow["id"], $a, 0, $dados, $userrow);
			}
		}
	}

}}

if (!function_exists('missoesextras_derrota')) {
function missoesextras_derrota($userrow) { // fight.php (morte): zera a sequência de "sobreviver" e faz a viagem/escolta falhar.

	foreach (mx_ativas($userrow["id"]) as $a) {
		$p = mx_passoatual($a);
		if (!$p) { continue; }
		if ($p["tipo"] == "viagem") { mx_falhar($userrow["id"], $a["id"]); }
		elseif ($p["tipo"] == "sobreviver") { $dados = mx_dados($a); unset($dados["hp"], $dados["descanso"]); mx_gravarpasso($userrow["id"], $a, 0, $dados, $userrow); }
	}

}}

?>
