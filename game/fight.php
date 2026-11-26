<?php // fight.php :: Luta contra monstros (PvE): escolha do monstro, turno do jogador (atacar, jutsu ou fugir), turno do inimigo, morte, vitória (experiência, Ryou, level up, durabilidade) e drops.
if (!defined('NARUTO_RPG')) { header("Location: index.php"); die(); }
include_once('funcoesinclusas.php');
include_once('funcoescartas.php'); // cartaodomonstro()
include_once('funcoesroubo.php'); // roubo de itens e status que inimigos causam no jogador.

/* ------------------------------------------------------------------------
   Resumo das regras da luta:

   - Nível do monstro: 1 nível a cada 5 casas de distância do centro do mapa (0,0); sorteia um monstro
     entre (nível - 2) e o nível da região.
   - Quem ataca primeiro: rand(1,10) + raiz(Destreza) do jogador contra rand(1,7) + raiz(dano máx.) do monstro.
   - Ataque do jogador: 75% a 100% do Poder de Ataque, dividido por 3.
       Hit excelente (dano x2): rand(1,150) <= raiz(Força) + 2% da Determinação.
       Bloqueio do monstro: 75% a 100% da armadura dele, dividido por 3.
       Esquiva do monstro: rand(1,200) + 3% da Precisão <= raiz(armadura do monstro).
       Dano mínimo de 1.
   - Ataque do monstro: 50% a 100% do dano máximo dele, multiplicado pela dificuldade.
       Bloqueio do jogador: 75% a 100% do Poder de Defesa, dividido por 4.
       Esquiva do jogador: rand(1,150) - 2% da Agilidade <= raiz(Destreza).
       Dano mínimo de 1 (antes e depois da defesa extra).
   - Dificuldade (campo difficulty do jogador): 2 e 3 multiplicam HP e dano do monstro e também a
     experiência e o Ryou ganhos por diff2mod/diff3mod (tabela control).
   - Jutsus (tabela spells, campo type): 1 = cura, 2 = dano, 3 = sono, 4 = +% de dano, 5 = +% de defesa.
       Dano extra (uberdamage) e defesa extra (uberdefense) valem até o fim da batalha.
       Monstro imune 1 = imune a jutsus de dano; imune 2 = imune a dano e a sono.
       Sono: o monstro fica "currentmonstersleep" = força do jutsu e a cada turno tenta acordar com
       rand(1,15) - 1 ponto a cada 100 de Inteligência; acorda se o resultado passar da força do sono.
       Jutsus configuráveis (funcoesjutsus.php): tipo 6 = suporte, golpes (2-4), roubo de vida, efeito extra
       (queimadura/veneno por turno, atordoar, escudo, buffs, debuffs no inimigo, regeneração, dreno de chakra)
       e recarga em turnos. Os efeitos ativos ficam em users.jutsuestado (JSON, lados "jog"/"ini"), contam no fim
       de cada rodada (jutsufimdoturno) e são apagados no fim da luta. Ícones 12x12 em images/status/.
   - Elementos (ver elementoganhador() em funcoesinclusas.php): vantagem x1,5 e desvantagem x0,5, mais
     1% de bônus a cada 20 pontos do atributo ligado ao elemento do jogador.
   - Bônus dos equipamentos (ver bonuscombate() em funcoesinclusas.php):
       Crítico: se o hit excelente da regra acima falhar, rand(1,100) <= Chance de Crítico (máx. 50) também dobra o dano.
       Esquiva: se a esquiva da regra acima falhar, rand(1,100) <= Esquiva (máx. 40) também desvia do ataque.
       Economia de Chakra: o jutsu custa ceil(CH x (100 - economia)/100), mínimo 1 (máx. 50% de economia).
   - Drops (victory): se o inimigo tem linhas em monster_drops, cada linha é sorteada com
       chance = chance_percent x multiplicador, multiplicador = 25 x (1 + Sorte/100) / (25 - 22% da Chance de Drop),
       que é exatamente quantas vezes a regra antiga (1 em 25) fica mais fácil com Sorte e Chance de Drop.
       As linhas são testadas na ordem em que foram associadas ao inimigo (monster_drops.id): a primeira que acertar é o drop (só cabe 1 por vitória).
       Inimigo sem linhas: regra antiga (rand(1, 25 - 22% da Chance de Drop) <= 1 + Sorte/100 e um drop
       qualquer com nível mínimo <= nível do inimigo).
   - Fuga: rand(4,10) + raiz(Destreza) contra rand(1,5) + raiz(dano máx.); se falhar, o monstro ataca.
   - Morte: perde metade do Ryou e volta para a vila do alinhamento com 1/4 do HP máximo.
   ------------------------------------------------------------------------ */


function lutaloglinhas() { // Quantas ações o registro guarda/mostra (control.lutalog_linhas, 1 a 50; padrão 15).
	global $controlrow;
	$n = isset($controlrow["lutalog_linhas"]) ? intval($controlrow["lutalog_linhas"]) : 15;
	return max(1, min(50, $n));
}

function lutalogler($texto) { // Registro da luta (users.lutalog, JSON): lista de array("q" => "jog"|"ini", "t" => texto).
	$log = json_decode((string)$texto, true);
	return is_array($log) ? array_slice(array_values(array_filter($log, "is_array")), -lutaloglinhas()) : array();
}

function lutalogadd(&$log, $quem, $html) { // Acrescenta uma ação (texto sem HTML) e guarda só as N últimas (lutaloglinhas()).
	$txt = html_entity_decode(strip_tags(str_ireplace(array("<br />", "<br>", "<br/>"), " ", (string)$html)), ENT_QUOTES, "UTF-8");
	$txt = trim(preg_replace("/\s+/u", " ", $txt));
	if ($txt == "") { return; }
	$log[] = array("q" => $quem, "t" => mb_substr($txt, 0, 240));
	$log = array_slice($log, -lutaloglinhas()); // 50 × 240 caracteres cabem folgado na coluna TEXT
}

function lutalogcurto($t) { // Versão curta de cada ação para a caixa (pt e en); o que não casar fica como está.
	$r = array(
		'/^Você atacou o inimigo provocando (\d+) de dano\.?/u' => 'Ataque: $1 de dano.',
		'/^O inimigo atacou você, provocando (\d+) de dano\.?/u' => 'Ataque: $1 de dano.',
		'/^Você usou: (.+?) e causou (\d+) de dano\.?/u' => '$1: $2 de dano.',
		'/^Você usou: (.+?) e ganhou (\d+) Pontos de Vida\.?/u' => '$1: +$2 HP.',
		'/^Você usou: (.+?) e ganhou (\d+)% de (dano|defesa) até o fim da batalha\.?/u' => '$1: +$2% $3.',
		'/^Você usou: (.+?), mas o inimigo é imune a (?:ele|seu Jutsu)\.?/u' => '$1: imune.',
		'/^Você usou: (.+?)\. O inimigo está dormindo\.?/u' => '$1: inimigo dormindo.',
		'/^Você bebeu (.+?) e recuperou (\d+) (HP|CH)\.?/u' => '$1: +$2 $3.',
		'/^You attacked the enemy and dealt (\d+) damage\.?/u' => 'Attack: $1 damage.',
		'/^The enemy attacked you, dealing (\d+) damage\.?/u' => 'Attack: $1 damage.',
		'/^You used (.+?) and dealt (\d+) damage\.?/u' => '$1: $2 damage.',
		'/^You used (.+?) and recovered (\d+) Hit Points\.?/u' => '$1: +$2 HP.',
		'/^You used (.+?) and gained (\d+)% (damage|defense) until the end of the battle\.?/u' => '$1: +$2% $3.',
		'/^You used (.+?), but the enemy is immune to (?:it|your Jutsu)\.?/u' => '$1: immune.',
		'/^You drank (.+?) and recovered (\d+) (HP|CH)\.?/u' => '$1: +$2 $3.',
		// Status e roubo causados pelo inimigo (funcoesroubo.php).
		'/Você foi envenenado \((\d+) por turno, \d+ turnos\)\.?/u' => 'Veneno ($1/turno).',
		'/Você está em chamas \((\d+) por turno, \d+ turnos\)\.?/u' => 'Queimadura ($1/turno).',
		'/Você adormeceu e vai perder o próximo turno\.?/u' => 'Sono: perde o turno.',
		'/Você ficou atordoado e vai perder o próximo turno\.?/u' => 'Atordoado: perde o turno.',
		'/O inimigo drenou (\d+) do seu Chakra\.?/u' => 'Dreno: -$1 CH.',
		'/O inimigo roubou seu item (.+?)!.*$/u' => 'Roubou: $1!',
		'/^Você está (dormindo|atordoado) e perdeu o turno\.?/u' => 'Perdeu o turno ($1).',
		'/You were poisoned \((\d+) per turn, \d+ turns\)\.?/u' => 'Poison ($1/turn).',
		'/You are on fire \((\d+) per turn, \d+ turns\)\.?/u' => 'Burn ($1/turn).',
		'/You fell asleep and will lose your next turn\.?/u' => 'Sleep: turn lost.',
		'/You were stunned and will lose your next turn\.?/u' => 'Stunned: turn lost.',
		'/The enemy drained (\d+) of your Chakra\.?/u' => 'Drain: -$1 CH.',
		'/The enemy stole your item (.+?)!.*$/u' => 'Stole: $1!',
		'/^You are (asleep|stunned) and lost your turn\.?/u' => 'Turn lost ($1).',
	);
	return preg_replace(array_keys($r), array_values($r), $t);
}

function lutalogcaixa($log) { // Caixa .cxb "Registro da luta" com as últimas ações (mais antiga em cima).
	$h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, "UTF-8"); };
	$linhas = "";
	foreach ($log as $e) {
		$jog = (isset($e["q"]) && $e["q"] == "jog");
		$linhas .= "<div class=\"cxb-linha\"><b style=\"color:".($jog ? "#1f5a1f" : "#8b1a1a")."\">".$h($jog ? t("Você:") : t("Inimigo:"))."</b> ".$h(lutalogcurto(isset($e["t"]) ? $e["t"] : ""))."</div>";
	}
	if ($linhas == "") { $linhas = "<div class=\"cxb-vazio\">".$h(t("Nenhuma ação ainda nesta luta."))."</div>"; }
	return "<div class=\"cxb\" id=\"registroluta\" style=\"width:190px;margin:6px auto 0\"><div class=\"cxb-cab\">".$h(t("Registro da luta"))." <span style=\"font-size:10px\">".$h(tf("últimas %s", lutaloglinhas()))."</span></div><div class=\"cxb-corpo\" style=\"max-height:260px;overflow-y:auto\">".$linhas."</div></div>"; // muitas linhas: rolagem interna, a caixa não estica a página
}

function escolhermonstro($minlevel, $maxlevel, $lat = null, $lon = null) { // Sorteia um monstro da faixa de níveis; se a faixa não tiver monstros, usa o nível mais próximo abaixo.
	// Regiões do mapa (colunas regiao_* do monstro, definidas no painel do administrador):
	//  1) se o jogador está dentro da região de algum monstro da faixa de níveis, sorteia primeiro entre esses monstros;
	//  2) senão, usa a lista normal da faixa, tirando os monstros "apenas nesta região" (regiao_exclusiva=1) cuja região
	//     não contém o jogador — fora da sua região um monstro exclusivo nunca aparece.
	// Monstros sem região (colunas NULL) seguem só a regra do nível, como sempre foi.
	// Terrenos (coluna terrenos, ex.: "deserto,rochoso"; vazia = qualquer terreno): o monstro só aparece se a casa do
	// jogador for de um desses terrenos (o mesmo terreno que o mini mapa desenha, funcoesterreno.php). Se nenhum monstro
	// da faixa puder aparecer no terreno da casa, o terreno é ignorado (o jogador nunca fica sem inimigo).

	$nafaixa = "level>='".intval($minlevel)."' AND level<='".intval($maxlevel)."'";
	// Peso aparecimento (admin): sorteio ponderado; 0 = nunca sai no sorteio aleatório.
	$permitidos = "aparecimento>0";
	$noterreno = "1";
	if ($lat !== null && $lon !== null) {
		$lat = intval($lat); $lon = intval($lon);
		include_once('funcoesterreno.php');
		$casa = terrenodacasa($lat, $lon);
		$noterreno = "(terrenos='' OR FIND_IN_SET('".preg_replace("/[^a-z0-9_\-]/i", "", $casa[2])."', terrenos)>0)";
		$dentro = "regiao_latmin IS NOT NULL AND regiao_latmin<='$lat' AND regiao_latmax>='$lat' AND regiao_lonmin<='$lon' AND regiao_lonmax>='$lon'";
		$monsterquery = doquery("SELECT * FROM {{table}} WHERE $nafaixa AND $dentro AND $noterreno AND aparecimento>0 ORDER BY -LN(1-RAND())/aparecimento LIMIT 1", "monsters");
		if (mysqli_num_rows($monsterquery) > 0) { return mysqli_fetch_array($monsterquery); }
		$permitidos = "aparecimento>0 AND (regiao_exclusiva='0' OR regiao_latmin IS NULL OR ($dentro))";
	}

	$monsterquery = doquery("SELECT * FROM {{table}} WHERE $nafaixa AND $permitidos AND $noterreno ORDER BY -LN(1-RAND())/aparecimento LIMIT 1", "monsters");
	if (mysqli_num_rows($monsterquery) == 0 && $noterreno != "1") {
		// Nenhum monstro da faixa aparece neste terreno: vale a faixa inteira, sem a restrição de terreno.
		$monsterquery = doquery("SELECT * FROM {{table}} WHERE $nafaixa AND $permitidos ORDER BY -LN(1-RAND())/aparecimento LIMIT 1", "monsters");
	}
	if (mysqli_num_rows($monsterquery) == 0) {
		// Regiões muito distantes (ex.: nível 60) não têm monstros cadastrados: pega o nível mais alto que exista abaixo
		// (ou, se não houver nenhum abaixo, o mais baixo que existir).
		$nivelquery = doquery("SELECT level FROM {{table}} WHERE level<='".intval($maxlevel)."' AND $permitidos ORDER BY level DESC LIMIT 1", "monsters");
		if (mysqli_num_rows($nivelquery) == 0) { $nivelquery = doquery("SELECT level FROM {{table}} WHERE $permitidos ORDER BY level ASC LIMIT 1", "monsters"); }
		if (mysqli_num_rows($nivelquery) == 0) { return false; }
		$nivelrow = mysqli_fetch_array($nivelquery);
		$monsterquery = doquery("SELECT * FROM {{table}} WHERE level='".intval($nivelrow["level"])."' AND $permitidos AND $noterreno ORDER BY -LN(1-RAND())/aparecimento LIMIT 1", "monsters");
		if (mysqli_num_rows($monsterquery) == 0) { $monsterquery = doquery("SELECT * FROM {{table}} WHERE level='".intval($nivelrow["level"])."' AND $permitidos ORDER BY -LN(1-RAND())/aparecimento LIMIT 1", "monsters"); }
	}
	return mysqli_fetch_array($monsterquery);

}

function encerrarluta($mensagem) { // Tira o jogador da luta sem recompensa (monstro inexistente) e volta para o mapa.

	global $userrow;
	doquery("UPDATE {{table}} SET currentaction='Exploring',currentfight='0',currentmonster='0',currentmonsterhp='0',currentmonstersleep='0',currentmonsterimmune='0',currentuberdamage='0',currentuberdefense='0',jutsuestado='' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
	header("Location: index.php?conteudo=".rawurlencode(t($mensagem)));
	die();

}

/* Morte para inimigos do mapa (e luta abandonada / logout no meio da luta). Penalidade proporcional ao nível,
   configurada no painel (admin.php, Configurações principais; colunas morte* da tabela control):
     Ryou perdido (% do que carrega)  = min(morteryoumax, morteryoubase + nível × morteryounivel)
     Experiência perdida (% da barra do nível atual) = min(morteexpmax, morteexpbase + nível × morteexpnivel); nunca desce de nível
     Chance de perder 1 item da mochila (%) = min(morteitemmax, nível × morteitemnivel); o item é destruído.
   Até o nível mortenivelseguro só o Ryou e a base de experiência (morteexpbase) são cobrados, sem item. Itens que nunca se perdem:
   variantes raras, pergaminhos de jutsu, itens de missão e itens especiais (só equipamento e poções comuns).
   Padrões: base 10%, +1%/nível, máx. 50%; experiência 0,5%/nível até 25%; item 0,25%/nível até 15%; seguro até o 5. */
function penalidademorte($nivel) { // Percentuais da penalidade para um nível: array("ryou" =>, "exp" =>, "item" =>).

	global $controlrow;
	$c = $controlrow;
	$nivel = max(1, intval($nivel));
	$seguro = ($nivel <= intval($c["mortenivelseguro"]));
	$ryou = min(floatval($c["morteryoumax"]), floatval($c["morteryoubase"]) + $nivel * floatval($c["morteryounivel"]));
	// Experiência: uma base pequena vale para todos (até no nível seguro), mais a parte por nível fora do nível seguro.
	$expbase = isset($c["morteexpbase"]) ? floatval($c["morteexpbase"]) : 3;
	$exp = min(floatval($c["morteexpmax"]), $expbase + ($seguro ? 0 : $nivel * floatval($c["morteexpnivel"])));
	$item = $seguro ? 0 : min(floatval($c["morteitemmax"]), $nivel * floatval($c["morteitemnivel"]));
	return array("ryou" => max(0, round($ryou, 2)), "exp" => max(0, round($exp, 2)), "item" => max(0, round($item, 2)));

}

function itemperdivelnamorte($texto) { // Item da mochila ("nome,id,tipo,dur") que pode ser perdido ao morrer: equipamento comum ou poção.

	if ($texto == "None" || $texto == "") { return false; }
	$item = array_pad(explode(",", (string)$texto), 4, "X");
	if (variantedoitem($item[0]) != "") { return false; } // variantes raras: nunca.
	if (!((is_numeric($item[1]) && is_numeric($item[2]) && intval($item[2]) >= 1 && intval($item[2]) <= 6) || in_array($item[1], array("hp", "mp", "tp"), true))) { return false; } // pergaminhos ("jutsu") e especiais: nunca.
	// Itens usados por missões (entregar/coletar/recompensa): nunca.
	$nome = addslashes($item[0]);
	if (mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE alvo='$nome' OR recompensa_item='$nome' OR mapa_item='$nome' OR descricao_arg='$nome' OR config LIKE '%\"".addslashes(addcslashes($item[0], "%_"))."\"%' LIMIT 1", "missoesextras")) > 0) { return false; }
	return true;

}

function morrernaluta($idjogador = 0, $motivo = "luta") { // O jogador morreu (ou abandonou a luta): penalidade por nível e volta para a vila natal com 1/4 do HP. Devolve as perdas (array) ou false se ele não estava lutando.

	// $motivo: "luta" (o inimigo venceu), "logout" (saiu do jogo no meio da luta) ou "abandono" (ficou parado na luta).
	global $userrow, $controlrow;
	if (!is_array($controlrow)) { $controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control")); }
	$id = ($idjogador > 0) ? intval($idjogador) : intval($userrow["id"]);
	include_once('cidadesconteudo.php');
	include_once('funcoesinclusas.php');

	// Tudo numa transação com o jogador travado: duas mortes ao mesmo tempo (turno + logout) só cobram uma vez.
	doquery("START TRANSACTION", "users");
	$u = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='$id' LIMIT 1 FOR UPDATE", "users"));
	if (!$u || $u["currentaction"] != "Fighting") { doquery("COMMIT", "users"); return false; }
	// Logout/abandono contra monstro: conta como morte só com a chance "mortelutachance" (control, %); senão a luta é
	// apenas cancelada (volta a explorar, sem recompensa). Contra jogadores (duelo) é sempre derrota (users.php).
	if ($motivo != "luta" && mt_rand(1, 100) > intval($controlrow["mortelutachance"])) {
		$aviso = ($motivo == "logout") ? i18n("Você saiu do jogo no meio de uma batalha; ela foi cancelada sem penalidade (e sem recompensa).") : i18n("Você abandonou uma batalha; ela foi cancelada sem penalidade (e sem recompensa).");
		$historico = ($u["historico"] == "None" || $u["historico"] == "") ? $aviso : $u["historico"].";;".$aviso;
		doquery("UPDATE {{table}} SET currentaction='Exploring',currentmonster='0',currentmonsterhp='0',currentmonstersleep='0',currentmonsterimmune='0',currentfight='0',currentuberdamage='0',currentuberdefense='0',jutsuestado='',lutaultimaacao='0',historico='".addslashes($historico)."' WHERE id='$id' LIMIT 1", "users");
		doquery("COMMIT", "users");
		return array("cancelada" => true, "motivo" => $motivo);
	}
	$townrowali = vilanataldoalinhamento($u["alinhamento"]);
	if (!$townrowali) { doquery("ROLLBACK", "users"); display(t("Erro 101: Houve um problema com o seu alinhamento."),"Error"); }

	$p = penalidademorte($u["level"]);
	$perdas = array("ryou" => 0, "exp" => 0, "item" => "", "pct" => $p, "motivo" => $motivo);
	$perdas["ryou"] = (int)floor(intval($u["gold"]) * $p["ryou"] / 100);
	// Experiência: % da barra do nível atual (do início do nível até o próximo), sem cair de nível.
	$classe = max(1, min(3, intval($u["charclass"])));
	$base = mysqli_fetch_array(doquery("SELECT ".$classe."_exp AS e FROM {{table}} WHERE id='".intval($u["level"])."' LIMIT 1", "levels"));
	$prox = mysqli_fetch_array(doquery("SELECT ".$classe."_exp AS e FROM {{table}} WHERE id='".(intval($u["level"]) + 1)."' LIMIT 1", "levels"));
	if ($base && $prox && $p["exp"] > 0) {
		$barra = max(0, intval($prox["e"]) - intval($base["e"]));
		$perdas["exp"] = min((int)floor($barra * $p["exp"] / 100), max(0, intval($u["experience"]) - intval($base["e"])));
	}
	// Item: chance de perder (destruir) um item sorteado entre os elegíveis da mochila (bp1..bp8 se existirem, senão bp1..bp4).
	$sets = array();
	if ($p["item"] > 0 && mt_rand(1, 10000) <= $p["item"] * 100) {
		$nslots = array_key_exists("bp8", $u) ? 8 : 4;
		$elegiveis = array();
		for ($i = 1; $i <= $nslots; $i++) { if (itemperdivelnamorte($u["bp".$i])) { $elegiveis[] = $i; } }
		if (count($elegiveis)) {
			$slot = $elegiveis[array_rand($elegiveis)];
			$perdas["item"] = explode(",", (string)$u["bp".$slot])[0];
			// Pilha: perde só 1 unidade.
			$sets[] = "bp$slot='".addslashes(itemcomqtd($u["bp".$slot], qtditem($u["bp".$slot]) - 1))."'";
		}
	}
	$newhp = (int)ceil(intval($u["maxhp"]) / 4);
	$aviso = mensagemmortei18n($perdas);
	$historico = ($u["historico"] == "None" || $u["historico"] == "") ? $aviso : $u["historico"].";;".$aviso;
	$sets[] = "historico='".addslashes($historico)."'";
	if ($motivo != "luta") { $sets[] = "mainmsg='".addslashes("naruto#$%;".i18n("Você morreu")."#$%;".$aviso)."'"; } // aviso na próxima página.
	doquery("UPDATE {{table}} SET currenthp='$newhp',currentaction='In Town',currentmonster='0',currentmonsterhp='0',currentmonstersleep='0',currentmonsterimmune='0',currentfight='0',currentuberdamage='0',currentuberdefense='0',jutsuestado='',lutaultimaacao='0',latitude='".intval($townrowali["latitude"])."',longitude='".intval($townrowali["longitude"])."',gold=GREATEST(0, gold-".intval($perdas["ryou"])."),experience=GREATEST(0, experience-".intval($perdas["exp"])."),".implode(",", $sets)." WHERE id='$id' LIMIT 1", "users");
	doquery("COMMIT", "users");
	if (function_exists('roubopurgar')) { roubopurgar($id); } // roubo_modo 1: morreu = o item roubado nesta luta se perde.
	// Missões "sobreviver" (sequência volta a zero) e viagem/escolta (falha): funcoesmissoesextras.php.
	include_once('funcoesmissoesextras.php'); if (function_exists('missoesextras_derrota')) { missoesextras_derrota($u); }
	return $perdas;

}

function mensagemmortei18n($perdas) { // Texto das perdas no formato neutro i18n (histórico/janela: cada um lê no seu idioma).

	$chave = array("luta" => "Você morreu em batalha", "logout" => "Você saiu do jogo no meio de uma batalha e ela contou como derrota", "abandono" => "Você abandonou uma batalha e ela contou como derrota");
	$m = isset($chave[$perdas["motivo"]]) ? $chave[$perdas["motivo"]] : $chave["luta"];
	if ($perdas["item"] != "") { return i18n($m.": perdeu %s Ryou (%s%%), %s de experiência (%s%% da barra) e o item %s.", $perdas["ryou"], $perdas["pct"]["ryou"], $perdas["exp"], $perdas["pct"]["exp"], $perdas["item"]); }
	return i18n($m.": perdeu %s Ryou (%s%%) e %s de experiência (%s%% da barra).", $perdas["ryou"], $perdas["pct"]["ryou"], $perdas["exp"], $perdas["pct"]["exp"]);

}

function lutasabandonadas($meuid = 0) { // Luta parada há mais de "mortelutaabandono" minutos (control) conta como morte. Confere o próprio jogador e, de vez em quando, alguns outros.

	global $controlrow;
	$minutos = intval($controlrow["mortelutaabandono"]);
	if ($minutos <= 0) { return false; }
	$limite = time() - 60 * $minutos;
	$minha = false;
	if ($meuid > 0) {
		$r = mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE id='".intval($meuid)."' AND currentaction='Fighting' AND lutaultimaacao>'0' AND lutaultimaacao<'$limite' LIMIT 1", "users"));
		if ($r) { $minha = morrernaluta(intval($r["id"]), "abandono"); }
	}
	// Varredura barata (até 5 jogadores): quem fechou o navegador no meio da luta.
	if ($meuid == 0 || mt_rand(1, 20) == 1) { // index.php já sorteia 1 em 20 quando chama sem jogador.
		$q = doquery("SELECT id FROM {{table}} WHERE currentaction='Fighting' AND lutaultimaacao>'0' AND lutaultimaacao<'$limite' LIMIT 5", "users");
		$ids = array(); while ($r = mysqli_fetch_array($q)) { $ids[] = intval($r["id"]); }
		foreach ($ids as $outro) { morrernaluta($outro, "abandono"); }
	}
	return $minha;

}

function turnodoinimigo($monsterrow, &$pagearray, $textos, $comelemento) { // Turno do inimigo (acordar, atacar, esquiva/defesa, elemento). Devolve true se o jogador morreu.

	// $textos: frases de cada situação ("dormindo", "desvio" e "dano"; esta com dois %s: o dano e o efeito do elemento).
	// As frases são chaves de tradução (t()/tf()).
	// O autor escreveu frases diferentes para cada momento da luta, e elas foram mantidas.
	global $userrow, $controlrow;

	// Sono: tenta acordar. A Inteligência diminui a chance de acordar.
	// (antes, nos turnos de atacar e de fugir, a Inteligência era SOMADA - ajudava o monstro a acordar,
	//  ao contrário do que diz a legenda da tela de atributos e dos turnos de jutsu.)
	if ($userrow["currentmonstersleep"] != 0) {
		$chancetowake = rand(1,15) - floor((intval($userrow["inteligencia"]) + classedojogador($userrow)["dj_inteligencia"])/100); // + doujutsu ativo.
		if ($chancetowake > $userrow["currentmonstersleep"]) {
			$userrow["currentmonstersleep"] = 0;
			$pagearray["monsterturn"] .= t("O inimigo acordou.<br />");
		} else {
			$pagearray["monsterturn"] .= t($textos["dormindo"]);
		}
	}
	if ($userrow["currentmonstersleep"] != 0) { return false; } // dormindo não ataca.
	// Jutsus (users.jutsuestado, ver funcoesjutsus.php): inimigo atordoado perde o ataque.
	global $estadojutsu;
	if (!is_array($estadojutsu)) { $estadojutsu = jutsuestadoler(""); }
	if (jutsuatordoado($estadojutsu, "ini")) { $pagearray["monsterturn"] .= t("O inimigo está atordoado e não consegue atacar.<br />"); return false; }

	// Ataque do inimigo (50% a 100% do dano máximo), ajustado pela dificuldade.
	$tohit = ceil(rand(intval($monsterrow["maxdam"]*.5), intval($monsterrow["maxdam"])));
	if ($userrow["difficulty"] == 2) { $tohit = ceil($tohit * $controlrow["diff2mod"]); }
	if ($userrow["difficulty"] == 3) { $tohit = ceil($tohit * $controlrow["diff3mod"]); }
	// Defesa do jogador (75% a 100% do Poder de Defesa / 4) e esquiva (Destreza e Agilidade).
	// Poder de Defesa com o Senjutsu ativo (calculado na hora) e a defesa física da especialização.
	$modsclasse = classedojogador($userrow);
	list($ataquereal, $defesareal) = classeataquedefesa($userrow);
	$toblock = ceil(rand(intval($defesareal*.75), intval($defesareal))/4);
	// Esquiva: Destreza + Agilidade (balanceamento: Agilidade 3x mais forte, sem degraus, teto de 40% — limiteesquiva()).
	$tododge = rand(1,150);
	// Esquiva extra dos equipamentos (% direto), testada só quando a esquiva normal falha (+ esquiva do doujutsu ativo).
	if ($tododge <= limiteesquiva($userrow["dexterity"], intval($userrow["agilidade"]) + $modsclasse["dj_agilidade"]) || rand(1,100) <= bonuscombate("esquiva") + jutsubonus($estadojutsu, "jog", "buff_esquiva") + $modsclasse["dj_esquiva"]) { // + Esquiva aumentada dos jutsus.
		$pagearray["monsterturn"] .= t($textos["desvio"]);
		$persondamage = 0;
	} else {
		$persondamage = $tohit - $toblock;
		if ($persondamage < 1) { $persondamage = 1; }
		// Defesa extra dos jutsus de defesa (% do dano que é absorvido).
		if ($userrow["currentuberdefense"] != 0) {
			$persondamage -= ceil($persondamage * ($userrow["currentuberdefense"]/100));
		}
		if ($persondamage < 1) { $persondamage = 1; }
		$persondamage = max(1, (int)ceil($persondamage * max(0, 100 - $modsclasse["defesa_fisica"]) / 100)); // especialização: defesa física.
	}

	// Elemento do inimigo contra os elementos do jogador (principal inteiro + secundário com peso; elementomultcombinado()):
	// vale em todo ataque do inimigo. Mesmo elemento = o jogador resiste. $sembonus null: o bônus de atributo é só do jogador.
	$cont = "";
	$sembonus = null;
	$monsterdamagemult = elementomultcombinado((string)$monsterrow["elemento"], (string)$userrow["elemento"], $sembonus);
	if ($monsterdamagemult > 1 && intval($userrow["charclass"]) == 2) { $monsterdamagemult = 1; } // especialista em Taijutsu: sem fraqueza de elemento.
	if ($monsterdamagemult > 1.0001 || $monsterdamagemult < 0.9999) {
		$elsjog = elementosdo($userrow["elemento"]); $elm = (string)$monsterrow["elemento"];
		$cont = " ".textoi18n(elementofrase($elm, in_array($elm, $elsjog, true) ? $elm : $elsjog[0], $monsterdamagemult));
	}
	$persondamage = max(1, (int)ceil($persondamage * $monsterdamagemult));

	// Ataque enfraquecido (inimigo), defesa reforçada e escudo (jogador) dos jutsus.
	if ($persondamage > 0) {
		$reduzido = jutsureduzirdano($estadojutsu, "ini", "jog", $persondamage);
		$persondamage = $reduzido[0];
		$pagearray["monsterturn"] .= jutsumensagens($reduzido[1]);
	}
	// Sharingan desperto (naruto_doujutsu.reflete): chance de devolver o golpe ao inimigo. O jogador não sofre dano e o
	// inimigo recebe o mesmo dano (fica com pelo menos 1 de HP: o reflexo não termina a luta sozinho).
	if ($persondamage > 0 && intval(isset($modsclasse["dj_reflete"]) ? $modsclasse["dj_reflete"] : 0) > 0 && rand(1, 100) <= intval($modsclasse["dj_reflete"])) {
		$refletido = min($persondamage, max(0, intval($userrow["currentmonsterhp"]) - 1));
		$userrow["currentmonsterhp"] = intval($userrow["currentmonsterhp"]) - $refletido;
		$pagearray["monsterturn"] .= tf("Seu Sharingan previu o golpe e o refletiu: o inimigo sofreu %s de dano e você nada.", $refletido)."<br /><br />";
		return false;
	}
	$pagearray["monsterturn"] .= tf($textos["dano"], $persondamage, $cont);
	$userrow["currenthp"] -= $persondamage;
	if ($userrow["currenthp"] <= 0) {
		$GLOBALS["perdasmorte"] = morrernaluta(); // perdas mostradas na tela da luta (fight()).
		return true;
	}
	// Ataque que acertou: efeito do inimigo no jogador e chance de roubo (funcoesroubo.php).
	if ($persondamage > 0) { statusaplicar($monsterrow, $pagearray, $estadojutsu); }
	roubotentar($monsterrow, $pagearray);
	return false;

}

function fight() { // Um turno da luta: escolhe o monstro (1º turno), faz a ação do jogador e o turno do inimigo, e mostra a tela.

	global $userrow, $controlrow, $indexconteudo;
	if ($userrow["currentaction"] != "Fighting") { display(t("Tentativa de trapaça detectada.")."<br>", "Error"); }
	include_once('funcoesdoujutsu.php');
	doujutsucobrar(); // Chakra do doujutsu aberto até agora (pode fechar o olho antes do turno).

	$avisochakra = strip_tags(pegartexto($_GET, "avisochakra")); // aviso de jutsu (chakra insuficiente etc.)
	// Efeitos de jutsu ativos e recargas desta luta (users.jutsuestado; lados "jog" e "ini"; ver funcoesjutsus.php).
	global $estadojutsu;
	$estadojutsu = jutsuestadoler(($userrow["currentfight"] == 1) ? "" : $userrow["jutsuestado"]);
	// Registro da luta (últimas 5 ações): começa vazio no primeiro turno de cada luta.
	$lutalog = ($userrow["currentfight"] == 1) ? array() : lutalogler(isset($userrow["lutalog"]) ? $userrow["lutalog"] : "");
	$acaojogador = false; // o jogador agiu nesta página (atacar, jutsu, poção ou fugir): entra no registro.
	$houveturno = false; // passou uma rodada (ação do jogador ou ataque surpresa): conta os efeitos no fim.
	$avatar = intval($userrow["avatar"]);
	$playerisdead = false;
	$pagearray = array(
		"grafico"=>$avatar."_stance.gif", // animação do avatar: _stance, _ataque, _jutsu ou _morto.
		"indexconteudo"=>(isset($indexconteudo) ? $indexconteudo : ""), // frase vinda do index.php (?conteudo=)
		"monstername"=>"", "dados"=>"", "yourturn"=>"", "monsterturn"=>"", "monsterhp"=>"", "command"=>"", "porcent"=>"30%");

	// Jutsus que o jogador conhece (campo spells = "id,id,id").
	$magiclist = "";
	$meusjutsus = array(); // array($spellrow, $recarga, $bloqueado) para o seletor em cartões (jutsugrade()).
	$userspells = explode(",", $userrow["spells"]);
	$spellquery = doquery("SELECT * FROM {{table}} WHERE uso<>'menu'", "spells");
	while ($spellrow = mysqli_fetch_array($spellquery)) {
		if (in_array($spellrow["id"], $userspells)) {
			// Jutsu em recarga (ou de Senjutsu sem o modo ativo/sem NP) aparece desabilitado.
			$recarga = jutsurecarga($estadojutsu, "jog", $spellrow["id"]);
			$bloqueado = ($recarga > 0 || !jutsudisponivel($spellrow, $userrow));
			$magiclist .= "<option value=\"".$spellrow["id"]."\"".($bloqueado ? " disabled=\"disabled\"" : "").">".jutsurotulo($spellrow, $userrow).($recarga > 0 ? " (".tf("recarga: %s", $recarga).")" : "")."</option>\n";
			$meusjutsus[] = array($spellrow, $recarga, $bloqueado);
		}
	}
	if ($magiclist == "") { $magiclist = "<option value=\"0\">None</option>\n"; }

	// Primeiro turno (currentfight = 1, gravado pelo explore.php): escolhe o monstro.
	$chancetoswingfirst = 1;
	$monsterrow = false;
	if ($userrow["currentfight"] == 1) {
		// Nível por região (funcoesinclusas.php :: nivelregional()): 1 nível a cada 5 casas do centro ou, se menor, da vila
		// mais próxima com nível base (towns.nivelbase) — perto das vilas distantes os inimigos são mais fracos.
		$maxlevel = nivelregional($userrow["latitude"], $userrow["longitude"]);
		$minlevel = $maxlevel - 2;
		if ($minlevel < 1) { $minlevel = 1; }

		// Chefe semanal na casa (ainda não derrotado nesta semana): ele é o inimigo, sem sorteio (funcoeschefes.php).
		include_once('funcoeschefes.php');
		$chefecasa = chefes_pendente($userrow["id"], intval($userrow["latitude"]), intval($userrow["longitude"]));
		if ($chefecasa) { $monsterrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($chefecasa["monster_id"])."' LIMIT 1", "monsters")); }
		else { $monsterrow = escolhermonstro($minlevel, $maxlevel, intval($userrow["latitude"]), intval($userrow["longitude"])); }
		if (!$monsterrow) { encerrarluta("Nenhum inimigo apareceu. Você pode continuar explorando."); }
		$monsterrow = monstroraridade($monsterrow); // elite/raro: HP/ataque/defesa com bônus (o HP sorteado abaixo já sai com ele, uma vez só).
		$userrow["currentmonster"] = $monsterrow["id"];
		$userrow["currentmonsterhp"] = rand(intval(($monsterrow["maxhp"]/5)*4), intval($monsterrow["maxhp"])); // 80% a 100% do HP máximo.
		if ($userrow["difficulty"] == 2) { $userrow["currentmonsterhp"] = ceil($userrow["currentmonsterhp"] * $controlrow["diff2mod"]); }
		if ($userrow["difficulty"] == 3) { $userrow["currentmonsterhp"] = ceil($userrow["currentmonsterhp"] * $controlrow["diff3mod"]); }
		$userrow["currentmonstersleep"] = 0;
		$userrow["currentmonsterimmune"] = $monsterrow["immune"];
		roubopurgar($userrow["id"]); // luta nova: item de roubo_modo 1 de uma luta anterior que não foi vencida se perde.
		// Dano/defesa extra dos jutsus valem só "até o fim da batalha": antes eles continuavam ativos na
		// próxima luta quando o jogador fugia ou morria.
		$userrow["currentuberdamage"] = 0;
		$userrow["currentuberdefense"] = 0;

		// Quem ataca primeiro.
		$chancetoswingfirst = rand(1,10) + ceil(sqrt(intval($userrow["dexterity"])));
		if ($chancetoswingfirst > (rand(1,7) + ceil(sqrt(intval($monsterrow["maxdam"]))))) { $chancetoswingfirst = 1; } else { $chancetoswingfirst = 0; }
	}

	// Dados do monstro atual.
	if (!$monsterrow) {
		$monsterquery = doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["currentmonster"])."' LIMIT 1", "monsters");
		$monsterrow = mysqli_fetch_array($monsterquery);
		if (!$monsterrow) { encerrarluta("O inimigo desapareceu. Você pode continuar explorando."); }
		$monsterrow = monstroraridade($monsterrow); // mesmos atributos do 1º turno (o HP atual já está em users.currentmonsterhp).
	}

	// Atordoado/dormindo por um inimigo (funcoesroubo.php): qualquer ação só passa a vez e o inimigo ataca.
	$perdeturno = statusperdeturno($estadojutsu);
	// Rinnegan ativo (olho com teleporte): fugir sempre funciona, mesmo atordoado/dormindo, e não se perde nada
	// (sem Ryou caído; itens que este inimigo roubou nesta luta voltam como numa vitória).
	if (isset($_POST["run"]) && function_exists('doujutsuativo') && doujutsuativo($userrow) && intval(doujutsudojogador($userrow)["teleporte"]) == 1) {
		doquery("START TRANSACTION", "users");
		$voltou = roubodevolver($userrow["id"], $userrow["currentmonster"]);
		doquery("UPDATE {{table}} SET currentaction='Exploring',jutsuestado='' WHERE id='".intval($userrow["id"])."' AND currentaction='Fighting' LIMIT 1", "users");
		doquery("COMMIT", "users");
		header("Location: index.php?conteudo=".rawurlencode(t("O Rinnegan dobrou o espaço e você saiu da luta sem perder nada.").($voltou != "" ? " ".t("Os itens que o inimigo tinha roubado voltaram para você.") : "")));
		die();
	}
	if ($perdeturno != "" && (isset($_POST["run"]) || isset($_POST["fight"]) || isset($_POST["spell"]) || isset($_POST["pocao"]) || isset($_POST["arremesso"]))) {
		$acaojogador = true; $houveturno = true;
		$pagearray["yourturn"] = (($perdeturno == "sono") ? t("Você está dormindo e perdeu o turno.") : t("Você está atordoado e perdeu o turno."))."<br /><br />";
		$playerisdead = turnodoinimigo($monsterrow, $pagearray, array(
			"dormindo"=>"O inimigo ainda está dormindo.<br />",
			"desvio"=>"Você fugiu do ataque inimigo. Nenhum dano foi causado.<br />",
			"dano"=>"O inimigo te atacou, causando %s de dano.%s<br /><br />"), false);
	} elseif (isset($_POST["run"])) { // ---------- Fugir ----------
		$acaojogador = true;

		// Chance de fuga do próprio inimigo (painel do admin, monsters.chancefuga): -1 = fórmula normal; 0..100 = chance fixa (0 = impede).
		$cf = isset($monsterrow["chancefuga"]) ? intval($monsterrow["chancefuga"]) : -1;
		if ($cf >= 0) { $fugiu = (mt_rand(1, 100) <= $cf); }
		else { $chancetorun = rand(4,10) + ceil(sqrt(intval($userrow["dexterity"]))); $fugiu = ($chancetorun > (rand(1,5) + ceil(sqrt(intval($monsterrow["maxdam"]))))); }
		if ($fugiu) {
			// Fuga com sucesso tem um custo (control.fugapenalidade = % do Ryou carregado; 0 = sem penalidade).
			global $controlrow;
			$pct = isset($controlrow["fugapenalidade"]) ? max(0, min(100, intval($controlrow["fugapenalidade"]))) : 5;
			$perda = (int)floor(intval($userrow["gold"]) * $pct / 100);
			doquery("UPDATE {{table}} SET currentaction='Exploring',jutsuestado='',gold=GREATEST(0,gold-$perda) WHERE id='".intval($userrow["id"])."' AND currentaction='Fighting' LIMIT 1", "users");
			roubopurgar($userrow["id"]); // roubo_modo 1: fugiu = o item roubado nesta luta se perde.
			header("Location: index.php?conteudo=".rawurlencode($perda > 0 ? tf("Você fugiu da luta, mas deixou cair %s Ryou na correria.", $perda) : t("Você fugiu da luta.")));
			die();
		}
		// Fuga bloqueada: o inimigo ataca. (No original o jogador saía da luta de qualquer jeito,
		// a mensagem abaixo nunca aparecia e o dano recebido era descartado.)
		$houveturno = true;
		$pagearray["yourturn"] = ($cf == 0 ? t("Este inimigo não deixa ninguém fugir! Você foi cercado.") : t("Você tentou fugir mas foi bloqueado pelo inimigo!"))."<br /><br />";
		$playerisdead = turnodoinimigo($monsterrow, $pagearray, array(
			"dormindo"=>"O inimigo continua dormindo.<br />",
			"desvio"=>"Você fugiu de um ataque. Nenhum dano foi recebido.<br />",
			"dano"=>"O inimigo provocou no total, %s de dano.%s<br /><br />"), false);

	} elseif (isset($_POST["fight"])) { // ---------- Atacar ----------
		$acaojogador = true;

		$pagearray["grafico"] = $avatar."_ataque.gif";
		// Especialização (dano físico, crítico) e Senjutsu ativo (Poder de Ataque), calculados na hora (funcoesjutsus.php).
		$modsclasse = classedojogador($userrow);
		list($ataquereal, $defesareal) = classeataquedefesa($userrow);
		$tohit = ceil(rand(intval($ataquereal*.75), intval($ataquereal))/3);
		// Hit excelente: Força e Determinação.
		$toexcellent = rand(1,150);
		$determinacao = limitecritico($userrow["strength"], $userrow["determinacao"]); // Determinação 3x mais forte, teto de 50% (funcoesinclusas.php).
		// Chance de Crítico dos equipamentos (% direto): segunda chance de hit excelente.
		$houveturno = true; $critico = false;
		if ($toexcellent <= $determinacao || rand(1,100) <= bonuscombate("critico") + jutsubonus($estadojutsu, "jog", "buff_critico") + $modsclasse["critico"]) { $tohit *= 2; $critico = true; } // + Foco crítico dos jutsus.
		$toblock = ceil(rand(intval($monsterrow["armor"]*.75), intval($monsterrow["armor"]))/3);
		$toblock = (int)ceil($toblock * (100 - min(90, $modsclasse["dj_ignora_defesa"])) / 100); // doujutsu: ignora parte da defesa.
		// Esquiva do inimigo: a Precisão do jogador (+ doujutsu ativo) diminui a chance.
		$tododge = rand(1,200) + floor((intval($userrow["precisao"]) + $modsclasse["dj_precisao"])*3/100);
		$cont = "";
		if ($tododge <= sqrt(intval($monsterrow["armor"]))) {
			$pagearray["yourturn"] .= t("O inimigo está fugindo. Nenhum dano foi recebido por ele.<br />");
			$monsterdamage = 0;
		} elseif (in_array("taijutsu", imunidadesdoinimigo($monsterrow))) { // Imune a Taijutsu: o ataque físico não causa dano.
			$pagearray["yourturn"] = t("O inimigo é imune a Taijutsu: seu ataque físico não causou dano. Use ninjutsu!<br /><br />");
			$monsterdamage = -1;
		} else {
			$monsterdamage = $tohit - $toblock;
			if ($monsterdamage < 1) { $monsterdamage = 1; }
			if ($userrow["currentuberdamage"] != 0) {
				// Dano extra do jutsu de +Dano. O elemento do ataque físico (elementoatk) só é aplicado aqui
				// dentro, ou seja, só com um jutsu de +Dano ativo - e só se o jogador tiver EP.
				$multiplicacaopontos = 0;
				$monsterdamagemult = elementoganhador($userrow['elementoatk'], $monsterrow['elemento'], $multiplicacaopontos);
				if ($userrow['currentep'] == 0){ $monsterdamagemult = 1; $multiplicacaopontos = 0; }
				if ($monsterdamagemult != 1) { $cont = " ".textoi18n(elementofrase((string)$userrow['elementoatk'], (string)$monsterrow['elemento'], $monsterdamagemult)); }
				$monsterdamage += ceil($monsterdamage * ($userrow["currentuberdamage"]/100));
				$monsterdamage = ceil($monsterdamage * $monsterdamagemult);
				$monsterdamage += ceil($monsterdamage * $multiplicacaopontos/100);
			}
			// Ataque reforçado (jogador) e defesa quebrada (inimigo) dos jutsus novos.
			$monsterdamage = (int)ceil($monsterdamage * jutsumultdano($estadojutsu, "jog", "ini"));
			$monsterdamage = max(1, (int)ceil($monsterdamage * max(0, 100 + $modsclasse["dano_fisico"]) / 100)); // especialização: dano físico.
		}
		if ($monsterdamage > 0) { $monsterdamage = couracadano($monsterrow, $monsterdamage); } // elite: Couraça.
		if ($monsterdamage < 0) { $monsterdamage = 0; } elseif ($critico) { $pagearray["yourturn"] .= "<b style=\"color:#c0392b\">".t("Golpe crítico!")."</b> ".tf("Você acertou um ponto vital do inimigo e o dano dobrou: %s de dano.%s<br /><br />", $monsterdamage, $cont); }
		else { $pagearray["yourturn"] .= tf("Você atacou o inimigo provocando %s de dano.%s<br /><br />", $monsterdamage, $cont); }
		$userrow["currentmonsterhp"] -= $monsterdamage;
		if ($userrow["currentmonsterhp"] <= 0) {
			doquery("UPDATE {{table}} SET currentmonsterhp='0' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
			header("Location: index.php?do=victory");
			die();
		}
		$playerisdead = turnodoinimigo($monsterrow, $pagearray, array(
			"dormindo"=>"O inimigo continua a dormir.<br />",
			"desvio"=>"Você fugiu do ataque inimigo. ",
			"dano"=>"O inimigo atacou você, provocando %s de dano.%s<br /><br />"), false);

	} elseif (isset($_POST["spell"])) { // ---------- Usar jutsu ----------

		$pagearray["grafico"] = $avatar."_jutsu.gif";
		$pickedspell = pegarint($_POST, "userspell");
		if ($pickedspell == 0) { header("Location: ./index.php?do=fight&avisochakra=".rawurlencode(t("Você deve selecionar um Jutsu primeiro."))); die(); }
		if (!in_array($pickedspell, $userspells)) { display(t("Você ainda não aprendeu esse Jutsu. Por favor volte e tente novamente."), "Error"); die(); }
		$newspellquery = doquery("SELECT * FROM {{table}} WHERE id='$pickedspell' LIMIT 1", "spells");
		$newspellrow = mysqli_fetch_array($newspellquery);
		if (!$newspellrow || (string)$newspellrow["uso"] == "menu") { display(t("Você ainda não aprendeu esse Jutsu. Por favor volte e tente novamente."), "Error"); die(); }
		// Jutsus de Senjutsu e com custo de NP (funcoesjutsus.php).
		$erroespecial = jutsuerroespecial($newspellrow, $userrow);
		if ($erroespecial != "") { header("Location: ./index.php?do=fight&avisochakra=".rawurlencode(t($erroespecial))); die(); }
		$newspellrow["mp"] = custochakra($newspellrow["mp"], $newspellrow); // custo já com a Economia de Chakra dos equipamentos e da especialização.
		if ($userrow["currentmp"] < $newspellrow["mp"]) { header("Location: ./index.php?do=fight&avisochakra=".rawurlencode(tf("Você não tem chakra suficiente para usar o(a) %s.", t($newspellrow["name"]))));die(); }

		// Recarga (cooldown) dos jutsus novos.
		$recarga = jutsurecarga($estadojutsu, "jog", $pickedspell);
		if ($recarga > 0) { header("Location: ./index.php?do=fight&avisochakra=".rawurlencode(tf("%s ainda está em recarga (%s turno(s)).", t($newspellrow["name"]), $recarga))); die(); }
		if (!jutsucobrarnp($userrow["id"], $newspellrow)) { header("Location: ./index.php?do=fight&avisochakra=".rawurlencode(t("Você não tem NP suficiente para usar esse jutsu."))); die(); }
		$houveturno = true; $acaojogador = true;
		include_once('funcoesdiarias.php'); diarias_jutsu($userrow, $newspellrow); // missões diárias: jutsu usado.

		// Os dois lados no formato de aplicarjutsu() (funcoesjutsus.php). O inimigo não tem chakra (mp null).
		$eu = array("lado"=>"jog", "elementos"=>(string)$userrow["elemento"], "elattr"=>elementoatributos($userrow), "int"=>intval($userrow["inteligencia"]), "ap"=>classeataquedefesa($userrow)[0], "nome"=>$userrow["charname"], "hp"=>intval($userrow["currenthp"]), "maxhp"=>intval($userrow["maxhp"]), "mp"=>intval($userrow["currentmp"]) - $newspellrow["mp"], "maxmp"=>intval($userrow["maxmp"]), "elemento"=>"", "imune"=>0, "mods"=>classedojogador($userrow));
		$ini = array("lado"=>"ini", "nome"=>$monsterrow["name"], "hp"=>intval($userrow["currentmonsterhp"]), "maxhp"=>intval($monsterrow["maxhp"]), "mp"=>null, "maxmp"=>0, "elemento"=>$monsterrow["elemento"], "imune"=>intval($userrow["currentmonsterimmune"]), "imunidades"=>imunidadesdoinimigo($monsterrow));
		$pagouep = jutsucobrarep($userrow["id"], $newspellrow); // jutsu de dano com elemento: gasta EP (null = não usa EP).
		if ($pagouep !== null) { $eu["ep"] = $pagouep; }
		if (in_array(intval($newspellrow["type"]), array(1, 2, 6, 7))) { // Cura, dano (golpes, roubo de vida), suporte e Taijutsu (7): tudo pelo aplicarjutsu().
			$resultado = aplicarjutsu($eu, $ini, $newspellrow, $estadojutsu);
			$userrow["currenthp"] = $eu["hp"];
			$userrow["currentmp"] = $eu["mp"];
			// Couraça (elite): devolve 10% do dano direto do jutsu.
			$danojutsu = intval($userrow["currentmonsterhp"]) - intval($ini["hp"]);
			$userrow["currentmonsterhp"] = $ini["hp"];
			$pagearray["yourturn"] = jutsumensagens($resultado["msgs"])."<br />";
			if ($danojutsu > 0 && couracadano($monsterrow, $danojutsu) < $danojutsu) {
				$userrow["currentmonsterhp"] += $danojutsu - couracadano($monsterrow, $danojutsu);
				$pagearray["yourturn"] .= tf("A Couraça do inimigo absorveu %s de dano.", $danojutsu - couracadano($monsterrow, $danojutsu))."<br /><br />";
			}
		} elseif ($newspellrow["type"] == 3) { // Sono: força do sono = attribute do jutsu.
			if (!in_array("sono", imunidadesdoinimigo($monsterrow)) && !in_array("genjutsu", imunidadesdoinimigo($monsterrow))) {
				$userrow["currentmonstersleep"] = intval($newspellrow["attribute"]);
				$pagearray["yourturn"] = tf("Você usou: %s. O inimigo está dormindo.<br /><br />", t($newspellrow["name"]));
			} else {
				$pagearray["yourturn"] = tf("Você usou: %s, mas o inimigo é imune a ele.<br /><br />", t($newspellrow["name"]));
			}
			$userrow["currentmp"] -= $newspellrow["mp"];
		} elseif ($newspellrow["type"] == 4 || $newspellrow["type"] == 5) { // Reforço em si mesmo: a especialização Genjutsu (buff_proprio) aumenta o valor.
			$newspellrow["attribute"] = (int)ceil(intval($newspellrow["attribute"]) * (100 + intval(classedojogador($userrow)["buff_proprio"] ?? 0)) / 100);
		}
		if ($newspellrow["type"] == 4) { // +Dano (%) até o fim da batalha.
			$userrow["currentuberdamage"] = intval($newspellrow["attribute"]);
			$userrow["currentmp"] -= $newspellrow["mp"];
			$pagearray["yourturn"] = tf("Você usou: %s e ganhou %s%% de dano até o fim da batalha.<br /><br />", t($newspellrow["name"]), $newspellrow["attribute"]);
		} elseif ($newspellrow["type"] == 5) { // +Defesa (%) até o fim da batalha.
			$userrow["currentuberdefense"] = intval($newspellrow["attribute"]);
			$userrow["currentmp"] -= $newspellrow["mp"];
			$pagearray["yourturn"] = tf("Você usou: %s e ganhou %s%% de defesa até o fim da batalha.<br /><br />", t($newspellrow["name"]), $newspellrow["attribute"]);
		}
		if (in_array(intval($newspellrow["type"]), array(3, 4, 5))) { // tipos antigos: o efeito extra e a recarga opcionais também valem.
			$extras = array();
			$eu["mp"] = intval($userrow["currentmp"]);
			jutsuaplicarefeito($eu, $ini, $newspellrow, $estadojutsu, $extras);
			$userrow["currentmp"] = $eu["mp"];
			if (intval($newspellrow["cooldown"]) > 0) { $estadojutsu["cd"]["jog"][intval($newspellrow["id"])] = intval($newspellrow["cooldown"]) + 1; }
			if (count($extras)) { $pagearray["yourturn"] .= jutsumensagens($extras)."<br />"; }
		}

		if ($userrow["currentmonsterhp"] <= 0) {
			doquery("UPDATE {{table}} SET currentmonsterhp='0',currenthp='".intval($userrow["currenthp"])."',currentmp='".intval($userrow["currentmp"])."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
			header("Location: index.php?do=victory");
			die();
		}
		$playerisdead = turnodoinimigo($monsterrow, $pagearray, array(
			"dormindo"=>"O inimigo ainda está dormindo.<br />",
			"desvio"=>"Você fugiu do ataque inimigo. Não houve um dano.<br />",
			"dano"=>"O inimigo te atacou, causando %s de dano.%s<br /><br />"), true);

	} elseif (isset($_POST["pocao"])) { // ---------- Beber poção (gasta o turno) ----------

		$qual = pegarint($_POST, "pocao");
		$original = ($qual >= 1 && $qual <= 8) ? (string)$userrow["bp".$qual] : "";
		$item = array_pad(explode(",", $original), 4, "X");
		if ($item[1] != "hp" && $item[1] != "mp") { header("Location: ./index.php?do=fight&avisochakra=".rawurlencode(t("Escolha uma poção da sua mochila."))); die(); }
		// Limite de poções por luta (pocaobloqueada(), funcoesinclusas.php; mesma regra da mochila e do duelo).
		$usadasantes = (string)$userrow["pocoesluta"];
		$tipopocao = pocaotipo($original);
		if (pocaobloqueada($usadasantes, $tipopocao)) { header("Location: ./index.php?do=fight&avisochakra=".rawurlencode(t("Você já usou essa poção o máximo de vezes permitido nesta luta."))); die(); }
		$usadasdepois = pocaoanotar($usadasantes, $tipopocao);
		// Tira 1 da pilha e anota a poção só se o espaço e a lista ainda forem os mesmos (cliques paralelos: só um passa).
		$restante = itemcomqtd($original, qtditem($original) - 1);
		doquery("UPDATE {{table}} SET bp$qual='".addslashes($restante)."', pocoesluta='".addslashes($usadasdepois)."' WHERE id='".intval($userrow["id"])."' AND bp$qual='".addslashes($original)."' AND pocoesluta='".addslashes($usadasantes)."' AND currentaction='Fighting' LIMIT 1", "users");
		if (mysqli_affected_rows($GLOBALS["link"]) != 1) { header("Location: ./index.php?do=fight&avisochakra=".rawurlencode(t("Essa poção não está mais na sua mochila."))); die(); }
		$userrow["bp".$qual] = $restante; $userrow["pocoesluta"] = $usadasdepois;
		$acaojogador = true; $houveturno = true;
		if ($item[1] == "hp") {
			$ganho = min(intval($item[2]), max(0, intval($userrow["maxhp"]) - intval($userrow["currenthp"])));
			$userrow["currenthp"] = intval($userrow["currenthp"]) + $ganho;
			$pagearray["yourturn"] = tf("Você bebeu %s e recuperou %s HP.", t($item[0]), $ganho)."<br /><br />";
		} else {
			$ganho = min(intval($item[2]), max(0, intval($userrow["maxmp"]) - intval($userrow["currentmp"])));
			$userrow["currentmp"] = intval($userrow["currentmp"]) + $ganho;
			$pagearray["yourturn"] = tf("Você bebeu %s e recuperou %s CH.", t($item[0]), $ganho)."<br /><br />";
		}
		$playerisdead = turnodoinimigo($monsterrow, $pagearray, array(
			"dormindo"=>"O inimigo ainda está dormindo.<br />",
			"desvio"=>"Você fugiu do ataque inimigo. Nenhum dano foi causado.<br />",
			"dano"=>"O inimigo te atacou, causando %s de dano.%s<br /><br />"), false);

	} elseif (isset($_POST["arremesso"])) { // ---------- Arremessar ferramenta ninja (gasta o turno; funcoesjutsus.php) ----------

		$qual = pegarint($_POST, "arremesso");
		$original = ($qual >= 1 && $qual <= 8) ? (string)$userrow["bp".$qual] : "";
		$ferramenta = itembatalhalinha($original);
		if (!$ferramenta) { header("Location: ./index.php?do=fight&avisochakra=".rawurlencode(t("Escolha uma ferramenta ninja da sua mochila."))); die(); }
		$restante = itemcomqtd($original, qtditem($original) - 1);
		doquery("UPDATE {{table}} SET bp$qual='".addslashes($restante)."' WHERE id='".intval($userrow["id"])."' AND bp$qual='".addslashes($original)."' AND currentaction='Fighting' LIMIT 1", "users");
		if (mysqli_affected_rows($GLOBALS["link"]) != 1) { header("Location: ./index.php?do=fight&avisochakra=".rawurlencode(t("Esse item não está mais na sua mochila."))); die(); }
		$userrow["bp".$qual] = $restante;
		$acaojogador = true; $houveturno = true;
		$pagearray["grafico"] = $avatar."_jutsu.gif";
		$alvoini = array("lado"=>"ini", "nome"=>$monsterrow["name"], "hp"=>intval($userrow["currentmonsterhp"]), "maxhp"=>intval($monsterrow["maxhp"]), "mp"=>null, "maxmp"=>0, "elemento"=>$monsterrow["elemento"], "imunidades"=>imunidadesdoinimigo($monsterrow));
		$msgsarr = array();
		$arr = itembatalhaarremessar($ferramenta, intval($userrow["precisao"]), $alvoini, $userrow["charname"], $msgsarr);
		if ($arr["dano"] > 0) {
			$danoarr = (int)ceil($arr["dano"] * jutsumultdano($estadojutsu, "jog", "ini")); // defesa quebrada / ataque reforçado valem também
			$danoarr = couracadano($monsterrow, max(1, $danoarr));
			$userrow["currentmonsterhp"] = intval($userrow["currentmonsterhp"]) - $danoarr;
		}
		if ($arr["efeito"] == "sono") {
			$userrow["currentmonstersleep"] = max(intval($userrow["currentmonstersleep"]), max(1, intval($ferramenta["bomba_efeito_valor"])));
			$msgsarr[] = i18n("%s caiu no sono!", $monsterrow["name"]);
		} elseif ($arr["efeito"] != "") { itembatalhaaplicarefeito($ferramenta, $arr["efeito"], $alvoini, $estadojutsu, $msgsarr); }
		$pagearray["yourturn"] = jutsumensagens($msgsarr)."<br />";
		if ($userrow["currentmonsterhp"] <= 0) {
			doquery("UPDATE {{table}} SET currentmonsterhp='0', jutsuestado='".addslashes(jutsuestadotexto($estadojutsu))."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
			header("Location: index.php?do=victory");
			die();
		}
		$playerisdead = turnodoinimigo($monsterrow, $pagearray, array(
			"dormindo"=>"O inimigo ainda está dormindo.<br />",
			"desvio"=>"Você fugiu do ataque inimigo. Nenhum dano foi causado.<br />",
			"dano"=>"O inimigo te atacou, causando %s de dano.%s<br /><br />"), false);

	} elseif ($chancetoswingfirst == 0) { // ---------- O inimigo atacou primeiro ----------

		$houveturno = true;
		$pagearray["yourturn"] = t("O inimigo te atacou antes que estivesse preparado!<br /><br />");
		$playerisdead = turnodoinimigo($monsterrow, $pagearray, array(
			"dormindo"=>"O inimigo ainda está dormindo.<br />",
			"desvio"=>"Você fugiu do ataque inimigo. Nenhum dano foi causado.<br />",
			"dano"=>"O inimigo te atacou, causando %s de dano.%s<br /><br />"), false);

	}

	// Registro da luta: a ação do jogador e a resposta do inimigo desta rodada.
	if ($houveturno) {
		if ($acaojogador) { lutalogadd($lutalog, "jog", $pagearray["yourturn"]); } // sem ação (ataque surpresa): só a linha do inimigo.
		lutalogadd($lutalog, "ini", $pagearray["monsterturn"]);
	}

	// Fim da rodada: queimadura/veneno no inimigo, regeneração do jogador, contagem dos efeitos e das recargas.
	if ($houveturno && !$playerisdead) {
		$lados = array("jog"=>array("nome"=>$userrow["charname"], "hp"=>intval($userrow["currenthp"]), "maxhp"=>intval($userrow["maxhp"])),
			"ini"=>array("nome"=>$monsterrow["name"], "hp"=>intval($userrow["currentmonsterhp"]), "maxhp"=>intval($monsterrow["maxhp"])));
		$cdantes = isset($estadojutsu["cd"]["jog"]) ? $estadojutsu["cd"]["jog"] : array();
		$fim = jutsufimdoturno($estadojutsu, $lados);
		if (count($fim)) { $pagearray["monsterturn"] .= jutsumensagens($fim)."<br />"; }
		// Aviso curto: jutsu que saiu da recarga nesta rodada.
		foreach ($cdantes as $idcd => $n) {
			if (jutsurecarga($estadojutsu, "jog", $idcd) > 0) { continue; }
			$spcd = mysqli_fetch_array(doquery("SELECT name FROM {{table}} WHERE id='".intval($idcd)."' LIMIT 1", "spells"));
			if ($spcd) { $pagearray["monsterturn"] .= "<span class=\"small avisorecarga\" style=\"color:#2f7a2f\">".htmlspecialchars(tf("%s está pronto de novo.", t($spcd["name"])), ENT_QUOTES, "UTF-8")."</span><br />"; }
		}
		$userrow["currenthp"] = max(1, $lados["jog"]["hp"]); // veneno/queimadura de inimigo não mata (deixa com 1 HP).
		$userrow["currentmonsterhp"] = $lados["ini"]["hp"];
		// Vigor (raro): ainda vivo no fim da rodada, regenera 3% do HP máximo (até o máximo).
		if ($userrow["currentmonsterhp"] > 0 && intval($monsterrow["raridade"]) == 2 && $userrow["currentmonsterhp"] < intval($monsterrow["maxhp"])) {
			$vigor = min(intval($monsterrow["maxhp"]) - intval($userrow["currentmonsterhp"]), max(1, (int)ceil(intval($monsterrow["maxhp"]) * 3 / 100)));
			$userrow["currentmonsterhp"] += $vigor;
			$txtvigor = tf("Vigor: o inimigo regenerou %s de HP.", $vigor);
			$pagearray["monsterturn"] .= "<span class=\"small\" style=\"color:#2f7a2f\">".htmlspecialchars($txtvigor, ENT_QUOTES, "UTF-8")."</span><br />";
			lutalogadd($lutalog, "ini", $txtvigor);
		}
		if ($userrow["currentmonsterhp"] <= 0) { // o dano por turno derrotou o inimigo.
			doquery("UPDATE {{table}} SET currentmonsterhp='0',currenthp='".intval($userrow["currenthp"])."',currentmp='".intval($userrow["currentmp"])."',jutsuestado='' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
			header("Location: index.php?do=victory");
			die();
		}
	}

	// Cartão do monstro com o HP atual (o script também atualiza o número na tela) e os ícones dos efeitos nele.
	$pagearray["monstername"] = t($monsterrow["name"]);
	$pagearray["dados"] = cartaodomonstro($monsterrow, $userrow["currentmonsterhp"], jutsuicones($estadojutsu, "ini").((intval($userrow["currentmonstersleep"]) > 0) ? "<img src=\"images/status/sono.png\" width=\"16\" height=\"16\" title=\"".htmlspecialchars(t("Dormindo"), ENT_QUOTES, "UTF-8")."\" alt=\"\">" : ""))."<br>";
	$iconesjog = jutsuicones($estadojutsu, "jog");
	if (!$playerisdead) { $pagearray["dados"] .= statusjogadorcaixa($estadojutsu); } // ícone, nome e turnos de cada efeito (funcoesroubo.php).
	$pagearray["monsterhp"] = "<script language=\"javascript\">document.getElementById('danoalt').innerHTML = '".intval($userrow["currentmonsterhp"])."';</script>";
	// Doujutsu com "ver_inimigo" (Byakugan, Olho Celeste, Rinnegan) ativo: mostra os itens que o inimigo pode dropar.
	if (!$playerisdead && classedojogador($userrow)["dj_ver_inimigo"] == 1) {
		$visao = array();
		$qv = doquery("SELECT d.name, m.chance_percent FROM {{table}} m JOIN ".$GLOBALS["dbsettings"]["prefix"]."_drops d ON d.id=m.drop_id WHERE m.monster_id='".intval($monsterrow["id"])."' ORDER BY m.chance_percent DESC", "monster_drops");
		while ($v = mysqli_fetch_array($qv)) { $visao[] = htmlspecialchars(t($v["name"]), ENT_QUOTES)." (".rtrim(rtrim(number_format(floatval($v["chance_percent"]), 2, ".", ""), "0"), ".")."%)"; }
		$pagearray["dados"] .= "<center class=\"small\" id=\"visaodoujutsu\">".t("Seu doujutsu vê o que o inimigo carrega:")." ".(count($visao) ? implode(", ", $visao) : t("nada"))."</center>";
	}

	if (!$playerisdead) {
		$t = "t"; // para usar {$t("...")} dentro do heredoc.
		$avisochakra2 = ($avisochakra != "") ? "<font color=red>".$avisochakra."</font><br><br>" : "";
		// Jutsus em cartões (sem lista suspensa): ícone do tipo/elemento, custo, recarga e aviso "Imune" contra este inimigo.
		$imunidadesinimigo = imunidadesdoinimigo($monsterrow);
		$gradejutsus = count($meusjutsus) ? jutsugrade($meusjutsus, $imunidadesinimigo) : "<div class=\"light\" style=\"text-align:center\">".t("Você ainda não conhece nenhum jutsu.")."</div>";
		// Poções de HP/CH da mochila em cartões (pocaograde(), funcoesjutsus.php): beber gasta o turno.
		$gradepocoes = pocaograde($userrow, "pocao");
		$blocopocoes = ($gradepocoes == "") ? "" : "<div class=\"secaoluta\">".t("POÇÕES")."</div><form action=\"index.php?do=fight\" method=\"post\" style=\"margin:0\">".$gradepocoes."</form>";
		// Ferramentas ninja de arremesso (kunais, bombas...) em cartões: gastam o turno.
		$gradearr = itembatalhagrade($userrow, "arremesso");
		$blocopocoes .= ($gradearr == "") ? "" : "<div class=\"secaoluta\">".t("FERRAMENTAS NINJA")."</div><form action=\"index.php?do=fight\" method=\"post\" style=\"margin:0\">".$gradearr."</form>";
		$caixalog = lutalogcaixa($lutalog);
		$taijutsuimune = in_array("taijutsu", $imunidadesinimigo) ? " <span style=\"color:#b3261e;font-weight:bold;font-size:10px\">(".t("o inimigo é imune a Taijutsu").")</span>" : "";
		$pagearray["command"] = <<<END
<div class="secaoluta">{$t("AÇÕES")}</div>
<form action="index.php?do=fight" method="post" style="margin:0">
<center><button type="submit" name="fight" value="1" class="jataque btnjogo bj-ataque"><img src="images/jutsu_taijutsu.png" class="jutsuico" width="20" height="17" alt=""> {$t("Atacar")} <span style="font-size:10px">({$t("Taijutsu")})</span></button>$taijutsuimune</center>
</form>
<style>.secaoluta{display:flex;align-items:center;gap:8px;margin:14px 4px 6px;font-weight:bold;color:#663300;font-size:11px;letter-spacing:1px}.secaoluta:before,.secaoluta:after{content:"";flex:1;height:2px;background:linear-gradient(90deg,transparent,#a0703c 25%,#a0703c 75%,transparent)}.secaoluta.sl-vazia{margin:14px 4px 8px}.secaoluta.sl-vazia:before{flex:1}.secaoluta.sl-vazia:after{display:none}</style>
<div class="secaoluta">{$t("JUTSUS")}</div>
<form action="index.php?do=fight" method="post" style="margin:0"><input type="hidden" name="spell" value="1" />
$gradejutsus
</form>
$blocopocoes
<div class="secaoluta sl-vazia"></div>
$avisochakra2
<form action="index.php?do=fight" method="post" style="margin:0"><center><button type="submit" name="run" value="1" class="jcorrer btnjogo bj-fuga"><img src="images/jutsu_correr.png" class="jutsuico" width="20" height="17" alt=""> {$t("Correr")}</button></center></form>
END;
		$pagearray["command"] = statusavisobotao($estadojutsu, $pagearray["command"]); // atordoado/dormindo: aviso e "Passar o turno".
		$pagearray["registro"] = $caixalog; // fica embaixo do avatar, na coluna da direita (templates/fight.php)
		// Grava o estado da luta (currentfight conta os turnos; 1 = primeiro turno). Limitado a 100: a coluna é
		// tinyint (máx. 127) e lutas longas davam erro de banco no 128º turno.
		doquery("UPDATE {{table}} SET currentaction='Fighting',currenthp='".intval($userrow["currenthp"])."',currentmp='".intval($userrow["currentmp"])."',currentfight='".min(100, intval($userrow["currentfight"]) + 1)."',currentmonster='".intval($userrow["currentmonster"])."',currentmonsterhp='".intval($userrow["currentmonsterhp"])."',currentmonstersleep='".intval($userrow["currentmonstersleep"])."',currentmonsterimmune='".intval($userrow["currentmonsterimmune"])."',currentuberdamage='".intval($userrow["currentuberdamage"])."',currentuberdefense='".intval($userrow["currentuberdefense"])."',lutaultimaacao='".time()."',jutsuestado='".addslashes(jutsuestadotexto($estadojutsu))."',lutalog='".addslashes(json_encode($lutalog, JSON_UNESCAPED_UNICODE))."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
	} else {
		$pagearray["grafico"] = $avatar."_morto.gif";
		$pagearray["porcent"] = "50%";
		$perdas = isset($GLOBALS["perdasmorte"]) ? $GLOBALS["perdasmorte"] : false;
		$pagearray["command"] = (is_array($perdas) ? textoperdasmorte($perdas) : t("<b>Você morreu.</b>"));
		$pagearray["registro"] = lutalogcaixa($lutalog);
	}

	if (!isset($pagearray["registro"])) { $pagearray["registro"] = ""; }
	// Barra de vida do jogador acima do personagem (como na tela de explorar).
	$pagearray["vidajogador"] = barravidajogador($userrow);
	$template = gettemplate("fight");
	$page = parsetemplate($template, $pagearray);
	display($page, t("Lutando"));

}

function fatorsortevariante() { // Sorte também ajuda, de leve, a variante rara: +0,25% (relativo) por ponto, no máximo +50%.
	global $userrow;
	return 1 + min(200, max(0, intval($userrow["sorte"]))) * 0.25 / 100;
}

function sortreardrop($monsterrow, $sorte, $droprat) { // Sorteia o drop da vitória (linha da tabela drops) ou false se não caiu nada.
	// A linha devolvida ganha o campo "variante": efeito da variante rara sorteada (chance_variante_percent) ou "".

	// $sorte = 1 + Sorte/100 e $droprat = 25 - 22% da Chance de Drop (regra antiga: rand(1,$droprat) <= $sorte).
	$mult = 25 * $sorte / max(1, $droprat); // 1 sem bônus nenhum.
	// Elite/raro: chance multiplicada (raridade_drop_* em %), mas sem passar do teto por item (padrão 60%) (salvo se a base já for maior).
	$cfgrar = raridadeconfig();
	$multrar = isset($cfgrar[intval($monsterrow["raridade"])]) ? $cfgrar[intval($monsterrow["raridade"])]["drop"] / 100 : 1;
	$teto = isset($cfgrar["teto"]) ? $cfgrar["teto"] * 100 : 6000; // teto por item, em centésimos de % (naruto_control.raridade_drop_teto)
	$tabela = doquery("SELECT drop_id, chance_percent, chance_variante_percent FROM {{table}} WHERE monster_id='".intval($monsterrow["id"])."' ORDER BY id", "monster_drops");
	if (mysqli_num_rows($tabela) > 0) {
		while ($linha = mysqli_fetch_array($tabela)) {
			// Chance em centésimos de % (0,01% de precisão), limitada a 100%.
			$chance = min(10000, (int)round(floatval($linha["chance_percent"]) * 100 * $mult));
			if ($multrar != 1) { $chance = max(0, min(max($chance, $teto), (int)round($chance * $multrar))); }
			if (rand(1, 10000) <= $chance) {
				$dropquery = doquery("SELECT * FROM {{table}} WHERE id='".intval($linha["drop_id"])."' LIMIT 1", "drops");
				$droprow = mysqli_fetch_array($dropquery);
				if ($droprow) { $droprow["variante"] = (trim((string)$droprow["consumivel"]) == "" && intval($droprow["ensina_jutsu"]) == 0 && intval($droprow["ensina_avatar"]) == 0) ? sortearvariante(floatval($linha["chance_variante_percent"]) * fatorsortevariante()) : ""; return $droprow; } // poções/comidas e pergaminhos de jutsu não têm variante.
			}
		}
		return false;
	}
	// Sem tabela própria: regra antiga.
	if (rand(1, 10000) > min($multrar > 1 ? max($teto, 1) : 10000, (int)round(10000 * $sorte / max(1, $droprat) * $multrar))) { return false; } // = rand(1,$droprat) <= $sorte, aceitando Sorte fracionada
	$dropquery = doquery("SELECT * FROM {{table}} WHERE mlevel <= '".intval($monsterrow["level"])."' AND mlevel < '100' AND ensina_jutsu='0' AND ensina_avatar='0' ORDER BY RAND() LIMIT 1", "drops"); // pergaminhos de jutsu só caem pela tabela do inimigo.
	$droprow = mysqli_fetch_array($dropquery);
	if ($droprow) { $droprow["variante"] = (trim((string)$droprow["consumivel"]) == "") ? sortearvariante(0.5 * fatorsortevariante()) : ""; } // regra antiga: chance padrão de 0,5%.
	return $droprow ? $droprow : false;

}

// Caixas .cxb da tela de vitória (vitória, sorte de batalha e passou de nível).
function barravidajogador($userrow) { // Barra de vida do jogador acima do personagem (luta e vitória; a de explorar fica no index.php).
	$hpatual = max(0, intval($userrow["currenthp"])); $hpmax = max(1, intval($userrow["maxhp"]));
	return "<div class=\"vidaexplorar\" title=\"HP ".$hpatual."/".$hpmax."\" style=\"width:140px;margin:0 auto -14px;position:relative;top:14px;z-index:2;font:bold 10px tahoma,arial,sans-serif;color:#452202\">HP ".$hpatual."/".$hpmax."<div style=\"height:8px;background:#3a1d05;border:1px solid #452202;border-radius:3px;overflow:hidden\"><div style=\"height:100%;width:".min(100, round($hpatual * 100 / $hpmax))."%;background:#c0281c\"></div></div></div>";
}
function linhavit($rotulo, $valor, $extra = "") { return "<div class=\"cxb-linha\" style=\"display:flex;align-items:center;gap:6px\">".$rotulo."<b style=\"margin-left:auto;color:#2e7d1a;white-space:nowrap\">".$valor."</b>".$extra."</div>"; }
function corbarra($r) { $c = array("HP" => "#c0281c", "CH" => "#2a5bd0", "TP" => "#d8a800", "NP" => "#3f9b2b", "EP" => "#8a3fc0"); return isset($c[$r]) ? $c[$r] : "#888"; }
function quadradocor($r) { return "<span style=\"display:inline-block;width:9px;height:9px;background:".corbarra($r)."\"></span> "; }
function caixavitoria($monsterrow, $exp, $gold, $warnexp, $warngold, $msgfim) {
	return "<div class=\"cxb\" id=\"caixavitoria\" style=\"width:280px;margin:0 auto 6px\"><div class=\"cxb-cab\"><span>".t("Vitória!")."</span><span>".htmlspecialchars(t($monsterrow["name"]), ENT_QUOTES)." &middot; Lv ".intval($monsterrow["level"])."</span></div><div class=\"cxb-corpo\">"
		.linhavit(t("Experiência"), "+".intval($exp))
		.linhavit(iconeryou()." Ryou", "+".intval($gold))
		.(($warnexp.$warngold) != "" ? "<div class=\"cxb-linha\">".trim($warnexp." ".$warngold)."</div>" : "")
		.($msgfim != "" ? "<div class=\"cxb-linha\">".preg_replace('/^(<br\s*\/?>)+/i', "", $msgfim)."</div>" : "")
		."</div></div>";
}
function caixasorte($rotulo, $ganho, $antes, $depois, $max) {
	$max = max(1, $max); $pa = min(100, round($antes * 100 / $max)); $pd = min(100, round($depois * 100 / $max));
	return "<div class=\"cxb\" id=\"caixasorte\" style=\"width:280px;margin:0 auto 6px\"><div class=\"cxb-cab\"><span><span style=\"color:#ffd75e\">&#9733;</span> ".t("Sorte de batalha")."</span><span>".$rotulo."</span></div><div class=\"cxb-corpo\">"
		."<div class=\"cxb-linha\" style=\"display:flex;align-items:center;gap:6px\"><b style=\"color:#2e7d1a;white-space:nowrap\">+".intval($ganho)." ".$rotulo."</b>"
		."<div style=\"flex:1;height:9px;background:#3a1d05;border:1px solid #452202;position:relative\"><i style=\"position:absolute;left:0;top:0;bottom:0;width:".$pa."%;background:".corbarra($rotulo)."\"></i><i style=\"position:absolute;top:0;bottom:0;left:".$pa."%;width:".($pd - $pa)."%;background:".corbarra($rotulo).";opacity:.55\"></i></div>"
		."<span style=\"white-space:nowrap\">".intval($depois)."/".intval($max)."</span></div></div></div>";
}
function caixanivel($userrow, $levelrow, $classe, $monsterrow, $exp, $gold, $warnexp, $warngold, $pontos, $ep, $extra) {
	$novo = intval($levelrow["id"]);
	$h = "<div class=\"cxb\" id=\"caixanivel\" style=\"text-align:left\"><div class=\"cxb-cab\"><span>".tf("Nível %s!", $novo)."</span><span>".($novo - 1)." &rarr; ".$novo."</span></div><div class=\"cxb-corpo\">"
		."<div class=\"cxb-linha\">".tf("Vitória contra <b>%s</b>", htmlspecialchars(t($monsterrow["name"]), ENT_QUOTES)).": <b style=\"color:#2e7d1a\">+".intval($exp)." ".t("exp")."</b>, ".iconeryou()." <b style=\"color:#2e7d1a\">+".intval($gold)."</b>".trim(" ".$warnexp." ".$warngold)."</div>"
		.linhavit(quadradocor("HP")."HP", "+".intval($levelrow[$classe."_hp"]))
		.linhavit(quadradocor("CH")."CH", "+".intval($levelrow[$classe."_mp"]))
		.linhavit(quadradocor("TP")."TP", "+".intval($levelrow[$classe."_tp"]))
		.linhavit(quadradocor("NP")."NP", "+5")
		.($ep ? linhavit(quadradocor("EP")."EP", "+".intval($ep)) : "")
		.linhavit(t("Ataque"), "+".intval($levelrow[$classe."_strength"]))
		.linhavit(t("Defesa"), "+".intval($levelrow[$classe."_dexterity"]));
	$x = preg_replace('/^(<br\s*\/?>)+/i', "", $extra);
	if ($x != "") { $h .= "<div class=\"cxb-linha\">".$x."</div>"; }
	$h .= "<div class=\"cxb-linha\" style=\"display:flex;align-items:center;gap:6px;background:#ffe08a\"><b>".tf("+%s Pontos de Distribuição", intval($pontos))."</b><a href=\"outroseatributos.php?do=atributos\" style=\"margin-left:auto\">".t("Distribuir")."</a></div>";
	return $h."</div></div>";
}

function victory() { // Vitória: experiência, Ryou, level up ou drop/sorte de batalha, contador de monstros e desgaste dos itens.

	global $userrow, $controlrow, $link;

	if ($userrow["currentmonsterhp"] != 0) { header("Location: index.php?do=fight"); die(); }
	if ($userrow["currentfight"] == 0 || $userrow["currentaction"] != "Fighting") { header("Location: index.php"); die(); }

	// Trava o jogador (SELECT ... FOR UPDATE) até o COMMIT no fim da função e relê os dados: a recompensa, o drop,
	// o level up e os itens quebrados (desequiparitem regrava os atributos inteiros) são calculados sobre dados atuais.
	// Qualquer die() antes do COMMIT fecha a conexão e o MariaDB desfaz a transação.
	doquery("START TRANSACTION", "users");
	$userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
	if (!$userrow || $userrow["currentfight"] == 0 || $userrow["currentaction"] != "Fighting" || $userrow["currentmonsterhp"] != 0) { doquery("ROLLBACK", "users"); header("Location: index.php"); die(); }

	$monsterquery = doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["currentmonster"])."' LIMIT 1", "monsters");
	$monsterrow = mysqli_fetch_array($monsterquery);
	if (!$monsterrow) { encerrarluta("O inimigo desapareceu. Você pode continuar explorando."); }
	$monsterrow = monstroraridade($monsterrow);
	$avatar = intval($userrow["avatar"]);
	$dadosmonstro = cartaodomonstro($monsterrow, $userrow["currentmonsterhp"], "", true);

	// Experiência e Ryou: entre 5/6 e 100% do máximo do monstro (mínimo 1), vezes a dificuldade,
	// mais o bônus em % dos itens (expbonus / goldbonus).
	$exp = rand(intval(($monsterrow["maxexp"]/6)*5), intval($monsterrow["maxexp"]));
	if ($exp < 1) { $exp = 1; }
	if ($userrow["difficulty"] == 2) { $exp = ceil($exp * $controlrow["diff2mod"]); }
	if ($userrow["difficulty"] == 3) { $exp = ceil($exp * $controlrow["diff3mod"]); }
	if ($userrow["expbonus"] != 0) { $exp += ceil(($userrow["expbonus"]/100)*$exp); }
	$gold = rand(intval(($monsterrow["maxgold"]/6)*5), intval($monsterrow["maxgold"]));
	if ($gold < 1) { $gold = 1; }
	if ($userrow["difficulty"] == 2) { $gold = ceil($gold * $controlrow["diff2mod"]); }
	if ($userrow["difficulty"] == 3) { $gold = ceil($gold * $controlrow["diff3mod"]); }
	// (o original calculava o bônus de Ryou em cima da EXPERIÊNCIA ganha - bug antigo)
	if ($userrow["goldbonus"] != 0) { $gold += ceil(($userrow["goldbonus"]/100)*$gold); }
	// Multiplicadores de evento definidos no Painel do Administrador (1 = normal, 2 = em dobro...).
	if (isset($controlrow["expmultiplier"]) && $controlrow["expmultiplier"] > 0) { $exp = max(1, (int)ceil($exp * $controlrow["expmultiplier"])); }
	if (isset($controlrow["goldmultiplier"]) && $controlrow["goldmultiplier"] > 0) { $gold = max(1, (int)ceil($gold * $controlrow["goldmultiplier"])); }
	// Elite/raro: XP e Ryou com o mesmo % dos atributos (naruto_control).
	$cfgrar = raridadeconfig();
	if (isset($cfgrar[intval($monsterrow["raridade"])])) { $prar = $cfgrar[intval($monsterrow["raridade"])]["pct"]; $exp = (int)ceil($exp * (100 + $prar) / 100); $gold = (int)ceil($gold * (100 + $prar) / 100); }
	// Limite das colunas experience/gold (mediumint unsigned = 16777215).
	if ($userrow["experience"] + $exp < 16777215) { $newexp = intval($userrow["experience"]) + $exp; $warnexp = ""; } else { $newexp = intval($userrow["experience"]); $exp = 0; $warnexp = t("Você atingiu o limite de pontos de experiência."); }
	if ($userrow["gold"] + $gold < 16777215) { $newgold = intval($userrow["gold"]) + $gold; $warngold = ""; } else { $newgold = intval($userrow["gold"]); $gold = 0; $warngold = t("Você atingiu o limite de Ryou."); }

	// Próximo nível (tabela levels: colunas "classe_exp", "classe_hp" ... para cada uma das 3 classes).
	$classe = intval($userrow["charclass"]);
	if ($classe < 1 || $classe > 3) { $classe = 1; }
	$levelrow = false;
	$levelquery = doquery("SELECT * FROM {{table}} WHERE id='".(intval($userrow["level"])+1)."' LIMIT 1", "levels");
	if (mysqli_num_rows($levelquery) == 1) { $levelrow = mysqli_fetch_array($levelquery); }

	// Contador do último monstro derrotado ("nome,quantidade"), usado pelas missões de caça.
	$mnstexpl = array_pad(explode(",", $userrow['ultmonstro']), 2, 0);
	if ($mnstexpl[0] == $monsterrow["name"]){ $userrow['ultmonstro'] = $monsterrow["name"].",".(intval($mnstexpl[1]) + 1); }
	else { $userrow['ultmonstro'] = $monsterrow["name"].",1"; }

	// Durabilidade: cada vitória gasta 1 ponto de cada equipamento que não seja infinito (X).
	// Ao chegar a 0 o item quebra e é destruído (mais abaixo, pelo desequipar.php).
	$nomesslots = array(1=>"weaponname", 2=>"armorname", 3=>"shieldname", 4=>"slot1name", 5=>"slot2name", 6=>"slot3name", 7=>"slot4name");
	$durabilidade = lerdurabilidade($userrow["durabilidade"]);
	$quebrados = array();
	$msgfim = "";
	$desgaste = durabilidadeativa(); // control.durabilidade_ativa = 0: nenhuma vitória gasta durabilidade.
	for ($h = 1; $h < 8; $h++){
		if (!$desgaste) { break; }
		if ($durabilidade[$h] === "X") { continue; }
		if ($userrow[$nomesslots[$h]] == "None") { $durabilidade[$h] = "X"; continue; } // slot vazio com durabilidade perdida.
		$durabilidade[$h] -= 1;
		if ($durabilidade[$h] <= 0) {
			$quebrados[] = $h;
			$msgfim .= "<br><font color=\"brown\">".tf("Seu item <b>%s</b> quebrou.", tnomeitem($userrow[$nomesslots[$h]]))."</font>";
		} elseif (durabilidadebaixa($durabilidade[$h], durabilidademaxima($h, $userrow[str_replace("name", "id", $nomesslots[$h])]))) {
			// Aviso de desgaste (até 10% da máxima): dá tempo de reparar na loja de uma vila.
			$msgfim .= "<br><font color=\"#8b1a00\">".tf("Seu item <b>%s</b> está se desgastando: %s vitória(s) restante(s). Repare na loja de uma vila.", tnomeitem($userrow[$nomesslots[$h]]), intval($durabilidade[$h]))."</font>";
		}
	}

	// Valores que podem mudar (os máximos só mudam no level up).
	$newlevel = intval($userrow["level"]);
	$newhp = intval($userrow["maxhp"]); $newmp = intval($userrow["maxmp"]); $newtp = intval($userrow["maxtp"]);
	$newstrength = intval($userrow["strength"]); $newdexterity = intval($userrow["dexterity"]);
	$newattack = intval($userrow["attackpower"]); $newdefense = intval($userrow["defensepower"]);
	$novospontosdedistrubuicao = intval($userrow["pontoatributos"]);
	$novopontonatural = intval($userrow["maxnp"]);
	$novoep = 0; // EP máximo ganho neste level up.
	$newspell = "";
	$dropcode = "";
	// Chefe semanal: grava o abate desta semana (desfeito pelo ROLLBACK se a vitória não for gravada) e o drop dele é garantido.
	include_once('funcoeschefes.php');
	$chefedrop = chefes_registrarabate($userrow["id"], $monsterrow["id"]);

	if ($levelrow && $newlevel < 100 && $newexp >= $levelrow[$classe."_exp"]) { // ---------- Level up ----------

		$newhp += intval($levelrow[$classe."_hp"]);
		$newmp += intval($levelrow[$classe."_mp"]);
		$newtp += intval($levelrow[$classe."_tp"]);
		$newstrength += intval($levelrow[$classe."_strength"]);
		$newdexterity += intval($levelrow[$classe."_dexterity"]);
		$newattack += intval($levelrow[$classe."_strength"]);
		$newdefense += intval($levelrow[$classe."_dexterity"]);
		$newlevel = intval($levelrow["id"]);
		// Cada nível dá 5 pontos de distribuição (outroseatributos.php) e +5 de NP máximo.
		$novospontosamostrar = 5;
		$novospontosdedistrubuicao += $novospontosamostrar;
		$novopontonatural += 5;
		// +1 EP máximo a cada control.epnivel níveis (0 = desligado); o EP atual também sobe 1.
		$epnivel = isset($controlrow["epnivel"]) ? intval($controlrow["epnivel"]) : 2;
		if ($epnivel > 0 && intval($levelrow["id"]) % $epnivel == 0) { $novoep = 1; }

		// Jutsu exclusivo de missão (spells.exclusivo_missao) nunca é aprendido por nível, mesmo se a tabela levels apontar para ele.
		// Sem especialização (charclass 0): usa as colunas 1_* e só aprende jutsus sem classe (spells.classe = 0).
		$exclusivo = ($levelrow[$classe."_spells"] != 0) && mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE id='".intval($levelrow[$classe."_spells"])."' AND (exclusivo_missao='1'".((intval($userrow["charclass"]) < 1) ? " OR classe<>'0'" : "").") LIMIT 1", "spells")) > 0;
		if ($levelrow[$classe."_spells"] != 0 && !$exclusivo) { // alguns níveis ensinam um jutsu novo.
			$newspell = "spells='".addslashes($userrow["spells"].",".intval($levelrow[$classe."_spells"]))."',";
			$spelltext = "<br>".t("Você aprendeu um novo Jutsu.");
		} else { $spelltext = ""; }

		$page = "
<table width=\"100%\">
<tr><td align=\"center\"><center><img src=\"images/title_fighting.gif\" alt=\"Fighting\" /></center></td></tr></table>
<table><tr><td width=\"310\" valign=\"middle\">".$dadosmonstro."
</td><td>
<div style=\"position:relative; top: -10px;\"><div style=\"z-index: 1; position: relative\">
<div style=\"width:165px;margin:0 auto\">".barravidajogador(array_merge($userrow, array("maxhp" => $newhp)))."<table width=\"165\" height=\"175\" background=\"layoutnovo/graficos/fundo.png\" style=\"background-repeat:no-repeat;;background-position:left top\"><tr height=\"30%\"><td></td></tr><tr><td><center><img src=\"layoutnovo/graficos/".$avatar."_ganhou.gif\" style=\"transform:scaleX(-1)\"></center>
</td></tr><tr  height=\"15\"><td></td></tr></table></div>
</div></div>
</td></tr></table>

<center><div style=\"max-width:340px;margin:0 auto\">".caixanivel($userrow, $levelrow, $classe, $monsterrow, $exp, $gold, $warnexp, $warngold, $novospontosamostrar, isset($novoep) ? $novoep : 0, $msgfim.$spelltext)."</div></center>

<center>".t("Você pode continuar <a href=\"index.php\">explorando</a>.")."</center>
";
		$title = t("Você passou de nível!");

	} else { // ---------- Vitória normal: chance de drop e "sorte de batalha" ----------

		// Drop: chance = (1 + Sorte/100) / (25 - 22% da Chance de Drop dos itens).
		// (sem bônus nenhum: 1 chance em 25)
		$mostrarexc = "";
		// Sorte gradual e sutil: +1% de chance de drop por ponto (100 de Sorte = chance dobrada). Antes era em degraus de 50.
		$modsdj = classedojogador($userrow); // doujutsu ativo: Sorte e Chance de Drop a mais (funcoesdoujutsu.php).
		$sorte = fatorsorte(intval($userrow["sorte"]) + $modsdj["dj_sorte"]); // retorno decrescente: 1 + √Sorte/10 (funcoesinclusas.php).
		$droprat = (int)ceil(25 - (22*(intval($userrow["droprate"]) + $modsdj["dj_droprate"])/100));
		if ($droprat < 1) { $droprat = 1; }
		$pagedrop = "<br><center>".t("Você pode continuar <a href=\"index.php\">explorando</a>.")."</center>";
		$droprow = $chefedrop ? $chefedrop : sortreardrop($monsterrow, $sorte, $droprat);
		if ($droprow) {
			// dropvariante guarda o efeito da variante rara ("" = item comum) até o jogador pegar o drop.
			$dropcode = "dropcode='".intval($droprow["id"])."',dropvariante='".addslashes($droprow["variante"])."',droplat='".intval($userrow["latitude"])."',droplon='".intval($userrow["longitude"])."',";
			$pagedrop = "<br><center>".t("Esse inimigo dropou um item. Clique no botão acima para pegar o item, ou você pode continuar <a href=\"index.php\">explorando</a> e ignorar o item.")."</center>";
			// Botão grande e pulsando, com o nome do item, para o drop não passar despercebido.
			$nomedrop = htmlspecialchars(t($droprow["name"]), ENT_QUOTES);
			$mostrarexc = "<style>@keyframes pulsodrop{0%,100%{box-shadow:0 0 0 0 rgba(214,170,60,.9)}50%{box-shadow:0 0 10px 4px rgba(214,170,60,.9)}}.botaodrop{display:inline-block;padding:6px 14px 6px 8px;background:#613003;border:2px solid #d6aa3c;border-radius:6px;color:#fff6d8 !important;font:bold 13px tahoma,arial,sans-serif;text-decoration:none;animation:pulsodrop 1.6s ease-in-out infinite}.botaodrop:hover{background:#8a4a0c}.botaodrop img{vertical-align:middle;margin-right:6px}.botaodrop small{display:block;font-weight:normal;color:#f3d98a;margin-left:30px}</style>"
				."<center><a class=\"botaodrop\" href=\"index.php?do=drop\" title=\"".t("Pegar o Drop")."\"><img border=\"0\" src=\"images/drop_botao.png\" width=\"28\" height=\"28\" alt=\"!\">".t("Pegar o Drop")."<small>$nomedrop</small></a></center><br>";
		}

		// Sorte de batalha (rand 1-10): 1 = EP, 2 = TP, 3-4 = CH, 5-6 = HP, 7-10 = NP (probabilidades de sempre).
		// Valor = base + bônus do nível do monstro + parte do máximo da barra:
		//   base ............ EP 1-2, TP 1-15, CH 1-10, HP 1-20, NP 1-5 (sorteio uniforme);
		//   nível ........... + floor(base × 2% × nível do monstro) (menos no EP), como antes;
		//   % do máximo ..... + floor(máximo da barra × P × rand(50,100)/100), com P = HP 5%, CH 5%, TP 6%, NP 4%, EP 5%
		//                     (ou seja, de metade de P até P inteiro do máximo).
		// Nos primeiros níveis a parte do máximo dá 0-2 pontos (fica quase igual a antes); nos níveis altos ela cresce
		// com a barra, sem passar de ~5% do máximo por vitória. O total nunca passa do máximo.
		$sorterand = rand(1,10);
		if ($sorterand <= 1) { $campo = "ep"; $randomizado = rand(1,2); $pctmax = 5; $imagem = "sorte_ep"; $rotulo = "EP"; }
		elseif ($sorterand == 2) { $campo = "tp"; $randomizado = rand(1,15); $pctmax = 6; $imagem = "sorte_tp"; $rotulo = "TP"; }
		elseif ($sorterand <= 4) { $campo = "mp"; $randomizado = rand(1,10); $pctmax = 5; $imagem = "sorte_mp"; $rotulo = "CH"; }
		elseif ($sorterand <= 6) { $campo = "hp"; $randomizado = rand(1,20); $pctmax = 5; $imagem = "sorte_hp"; $rotulo = "HP"; }
		else { $campo = "np"; $randomizado = rand(1,5); $pctmax = 4; $imagem = "sorte_np"; $rotulo = "NP"; }
		if ($campo != "ep") { $randomizado += floor(($monsterrow["level"] * 2 * $randomizado)/100); } // o EP não ganha o bônus de nível.
		$randomizado += (int)floor(intval($userrow["max".$campo]) * $pctmax * rand(50,100) / 10000);
		$antes = intval($userrow["current".$campo]);
		$userrow["current".$campo] = min(intval($userrow["max".$campo]), intval($userrow["current".$campo]) + $randomizado);
		$sortebatalha = caixasorte($rotulo, $randomizado, $antes, intval($userrow["current".$campo]), intval($userrow["max".$campo]));

		$page = "
<table width=\"100%\">
<tr><td align=\"center\"><center><img src=\"images/title_fighting.gif\" alt=\"Fighting\" /></center></td></tr></table>
<table><tr><td width=\"310\" valign=\"middle\">".$dadosmonstro."<br><center>
".caixavitoria($monsterrow, $exp, $gold, $warnexp, $warngold, $msgfim).$sortebatalha."
</center>
</td><td>
<div style=\"z-index: 1; position:relative\">
<div style=\"width:165px;margin:0 auto\">".barravidajogador($userrow)."<table width=\"165\" height=\"175\" background=\"layoutnovo/graficos/fundo.png\" style=\"background-repeat:no-repeat;;background-position:left top\"><tr height=\"30%\"><td></td></tr><tr><td><center><img src=\"layoutnovo/graficos/".$avatar."_ganhou.gif\" style=\"transform:scaleX(-1)\"></center>
</td></tr><tr  height=\"15\"><td></td></tr></table></div></div>
<center>$mostrarexc</center>


</td></tr></table>
".$pagedrop;
		$title = t("Vitória!");

	}

	// Chefe derrotado numa vitória com level up (que normalmente não tem drop): o drop garantido vai assim mesmo.
	if ($chefedrop && $dropcode == "") {
		$dropcode = "dropcode='".intval($chefedrop["id"])."',dropvariante='',droplat='".intval($userrow["latitude"])."',droplon='".intval($userrow["longitude"])."',";
		$page = "<center><a class=\"botaodrop\" style=\"display:inline-block;padding:6px 14px;background:#613003;border:2px solid #d6aa3c;border-radius:6px;color:#fff6d8;font:bold 13px tahoma,arial,sans-serif;text-decoration:none\" href=\"index.php?do=drop\">".t("Pegar o Drop")." - ".htmlspecialchars(t($chefedrop["name"]), ENT_QUOTES)."</a></center>".$page;
	}
	if ($chefedrop) { $page = "<div class=\"cxb\" id=\"chefevencido\" style=\"margin:4px auto;max-width:460px\"><div class=\"cxb-cab\">".t("Chefe da Semana")."</div><div class=\"cxb-corpo\"><div class=\"cxb-linha\">".tf("Você derrotou o chefe <b>%s</b> nesta semana! Ele deixou cair <b>%s</b>.", t($monsterrow["name"]), t($chefedrop["name"]))." <a href=\"chefes.php\">".t("Ver o ranking")."</a></div></div></div>".$page; }

	// Grava tudo numa query só. O "AND currentfight<>'0'" impede receber a recompensa duas vezes
	// (dois cliques/abas abrindo index.php?do=victory ao mesmo tempo).
	doquery("UPDATE {{table}} SET currentaction='Exploring',level='$newlevel',maxhp='$newhp',maxmp='$newmp',maxtp='$newtp',strength='$newstrength',dexterity='$newdexterity',attackpower='$newattack',defensepower='$newdefense', $newspell pontoatributos='$novospontosdedistrubuicao',maxnp='$novopontonatural',maxep=maxep+$novoep,currentfight='0',currentmonster='0',currentmonsterhp='0',currenthp='".intval($userrow['currenthp'])."',currentmp='".intval($userrow['currentmp'])."',currenttp='".intval($userrow['currenttp'])."',currentep='".(intval($userrow['currentep'])+$novoep)."',currentnp='".intval($userrow['currentnp'])."',currentmonstersleep='0',currentmonsterimmune='0',currentuberdamage='0',currentuberdefense='0',jutsuestado='',$dropcode experience=LEAST(experience+'".intval($exp)."',16777215),gold=LEAST(gold+'".intval($gold)."',16777215),ultmonstro='".addslashes($userrow['ultmonstro'])."' WHERE id='".intval($userrow["id"])."' AND currentfight<>'0' LIMIT 1", "users");
	if (mysqli_affected_rows($link) < 1) { doquery("ROLLBACK", "users"); header("Location: index.php"); die(); }
	// Missões extras "derrotar_monstro": conta esta vitória (funcoesmissoesextras.php).
	if (!function_exists('missoesextras_vitoria')) { include_once('funcoesmissoesextras.php'); } if (function_exists('missoesextras_vitoria')) { missoesextras_vitoria($userrow, $monsterrow); }
	// Missões diárias: vitória e elemento do inimigo (funcoesdiarias.php).
	include_once('funcoesdiarias.php'); diarias_vitoria($userrow, $monsterrow);
	// Clã: +1 inimigo para a missão semanal (funcoescla.php). Ainda dentro da transação.
	include_once('funcoescla.php'); clavitoria($userrow["id"]);
	// Doujutsu: abate de inimigo forte com o Sharingan ativo conta para a evolução (funcoesdoujutsu.php). Ainda dentro da transação.
	include_once('funcoesdoujutsu.php');
	$evoluiu = doujutsuabate($userrow, $monsterrow);
	if ($evoluiu != "") { $page = "<center><div style=\"border:2px solid #9b1b10;background:#fde8d8;padding:6px;margin:6px;font-weight:bold\" id=\"evolucaodoujutsu\">".$evoluiu."</div></center>".$page; }

	// Itens quebrados: são destruídos pelo desequipar.php (modo $deletar = true: não vão para a mochila).
	if (count($quebrados) > 0) {
		$uquery = doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow['id'])."' LIMIT 1 FOR UPDATE", "users"); // ainda dentro da transação.
		$userrow = mysqli_fetch_array($uquery);
		include_once('desequipar.php');
		foreach ($quebrados as $h) {
			desequiparitem($h, true);
			$durabilidade[$h] = "X";
		}
	}
	doquery("UPDATE {{table}} SET durabilidade='".addslashes(juntardurabilidade($durabilidade))."' WHERE id='".intval($userrow["id"])."' LIMIT 1","users");
	$voltou = roubodevolver($userrow["id"], $monsterrow["id"]); // itens e Ryou que este inimigo roubou voltam (funcoesroubo.php), ainda na transação.
	if ($voltou != "") { $page = $voltou.preg_replace('#<span class="cselo2 sitem"[^>]*>.*?</span>#s', '', $page); } // o cartão (montado antes) não mostra mais "Roubou..."
	doquery("COMMIT", "users");

	display($page, $title);

}

function drop() { // Tela do item dropado: equipar num slot de drop (1-3) ou guardar na mochila (4-11 = bp1-bp8).

	global $userrow, $link;

	$dropcodeatual = intval($userrow["dropcode"]);
	if ($dropcodeatual == 0) { header("Location: index.php"); die(); }

	$dropquery = doquery("SELECT * FROM {{table}} WHERE id='$dropcodeatual' LIMIT 1", "drops");
	$droprow = mysqli_fetch_array($dropquery);
	if (!$droprow) { // drop apagado pelo admin.
		doquery("UPDATE {{table}} SET dropcode='0' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
		header("Location: index.php"); die();
	}

	// Pergaminho de Disfarce (drops.ensina_avatar = id do avatar, funcoesavatares.php): não ocupa espaço; ao abrir o drop
	// o avatar é liberado na hora. Se o jogador já tinha esse avatar, o pergaminho é vendido sozinho por Ryou.
	if (intval(isset($droprow["ensina_avatar"]) ? $droprow["ensina_avatar"] : 0) > 0) {
		include_once('funcoesavatares.php');
		doquery("UPDATE {{table}} SET dropcode='0',dropvariante='' WHERE id='".intval($userrow["id"])."' AND dropcode='$dropcodeatual' LIMIT 1", "users");
		if (mysqli_affected_rows($link) < 1) { header("Location: index.php"); die(); }
		$idav = intval($droprow["ensina_avatar"]);
		if (avatarliberar($userrow["id"], $idav)) {
			$texto = tf("Você abriu o <b>%s</b> e aprendeu um novo disfarce: o avatar <b>%s</b> foi liberado!", t($droprow["name"]), avatarnome($idav))."<br><br><a href=\"outros.php?do=avatar\">".t("Ir para a tela de avatares")."</a>";
		} else {
			$ryou = 500 + 50 * intval($userrow["level"]);
			doquery("UPDATE {{table}} SET gold=LEAST(gold+'$ryou',16777215) WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
			$texto = tf("Você já conhecia o disfarce do <b>%s</b>. O pergaminho foi vendido por %s Ryou.", t($droprow["name"]), $ryou);
		}
		$page = "<table width=\"100%\"><tr><td align=\"center\"><center><img src=\"images/drop.gif\" /></center></td></tr></table><center><table><tr><td><img src=\"".iconeitem($droprow["id"], true)."\" style=\"border:2px #e4d094 solid\"></td><td><img src=\"layoutnovo/avatares/".$idav.".jpg\" width=\"62\" height=\"75\"></td><td style=\"padding-left:8px;max-width:260px\">".$texto."</td></tr></table><br>".t("Você pode continuar <a href=\"index.php\">explorando</a>.")."</center>";
		display($page, t("Pergaminho de Disfarce"));
	}

	// Drop consumível (campo consumivel = "hp,50" ou "mp,40"): só pode ir para a mochila, onde vira uma poção
	// comum ("nome,hp,50,1"), usada pelo backpack.php como as poções de sempre.
	$consumivel = array_pad(explode(",", (string)$droprow["consumivel"]), 2, 0);
	$econsumivel = in_array($consumivel[0], array("hp", "mp", "tp", "np", "ep", "tel", "bomba", "descanso", "banco"), true) && intval($consumivel[1]) > 0; // "tel" = Pergaminho de Teleporte Aleatório (backpack.php).
	// Pergaminho de jutsu (drops.ensina_jutsu = id do jutsu): também só vai para a mochila, como "nome,jutsu,ID,1";
	// usado pela mochila (backpack.php), ensina o jutsu e some.
	$jutsudoscroll = intval($droprow["ensina_jutsu"]);
	if ($jutsudoscroll > 0) { $econsumivel = true; $consumivel = array("jutsu", $jutsudoscroll); }
	// Livro de receitas da Alquimia (receitas.livro_drop = id deste drop): continua um item de drop ("nome,id,4,X"),
	// mas, como os pergaminhos e consumíveis, só pode ir para a mochila (não é equipável nos slots 1-3).
	// Drop de arma (drops.arma_item = items.id, tools/armas_escudo.sql): vai para a mochila como arma ("nome,ID,1,X"),
	// sem variante e sem desgaste; equipa-se pela mochila como qualquer arma.
	$armadrop = intval(isset($droprow["arma_item"]) ? $droprow["arma_item"] : 0);
	if ($armadrop > 0) { $econsumivel = true; }
	$somochila = $econsumivel || intval($droprow["id"]) == iddoselo() || mysqli_num_rows(doquery("SELECT id FROM {{table}} WHERE livro_drop='".intval($droprow["id"])."' LIMIT 1", "receitas")) > 0;

	if (isset($_POST["submit"])) {

		// Slot escolhido: 1 a 3 = acessórios 1 a 3; 13 = acessório 4; 4 a 11 = mochila (bp1 a bp8); 12 = "guardar na mochila"
		// (junta numa pilha do mesmo item ou usa o primeiro espaço livre). Nada além disso.
		$slot = pegarint($_POST, "slot");
		$acessorio = ($slot >= 1 && $slot <= 3) ? $slot : (($slot == 13) ? 4 : 0); // número do acessório (slotNname), 0 = mochila.
		if ($slot < 1 || $slot > 13 || ($somochila && $acessorio > 0)) { header("Location: ./index.php?do=drop&conteudo=".rawurlencode(t("Por favor selecione um slot do inventário para guardar o item."))); die(); } // volta à tela do drop (com um slot já marcado) em vez de uma página de erro sem saída.

		// Trava o jogador e relê os dados (sqlatributosdojogador() regrava todos os atributos: com dados velhos,
		// um equipar/desequipar paralelo seria desfeito). Depois zera o dropcode: se o formulário for enviado
		// duas vezes, só o primeiro envio vale.
		doquery("START TRANSACTION", "users");
		$userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
		if (!$userrow || intval($userrow["dropcode"]) != $dropcodeatual) { doquery("ROLLBACK", "users"); header("Location: index.php"); die(); }
		doquery("UPDATE {{table}} SET dropcode='0',dropvariante='' WHERE id='".intval($userrow["id"])."' AND dropcode='$dropcodeatual' LIMIT 1", "users");
		if (mysqli_affected_rows($link) < 1) { doquery("ROLLBACK", "users"); header("Location: index.php"); die(); }

		// Variante rara sorteada na vitória: vai no nome ("Nome:efeito"). Lida da linha travada.
		$nomecompleto = nomecomvariante($droprow["name"], $econsumivel ? "" : (string)$userrow["dropvariante"]);
		$newname = addslashes($nomecompleto);
		if ($acessorio > 0) {
			// Equipa: tira os bônus do item antigo do slot (que é descartado) e soma os do novo.
			if ($userrow["slot".$acessorio."id"] != 0) { bonusdodrop($userrow["slot".$acessorio."id"], -1, $userrow["slot".$acessorio."name"]); }
			bonusdodrop($droprow["id"], 1, $nomecompleto);
			if (atributosnegativos($userrow)) { doquery("ROLLBACK", "users"); header("Location: index.php?do=drop&conteudo=".rawurlencode(t("O lado negativo deste item deixaria um atributo seu abaixo de zero. Guarde-o na mochila."))); die(); }
			limitarpontosatuais();
			// O drop novo sai com a durabilidade cheia (drops.durabilidade; 0 = infinita "X"). Não herda a do item antigo.
			$durab = lerdurabilidade($userrow["durabilidade"]);
			$durab[3 + $acessorio] = durabilidadenova(4, $droprow["id"]);
			// Ids no $userrow antes de sqlatributosdojogador(): o bônus de conjunto é calculado pelas peças equipadas.
			$userrow["slot".$acessorio."id"] = intval($droprow["id"]); $userrow["slot".$acessorio."name"] = $nomecompleto;
			doquery("UPDATE {{table}} SET slot".$acessorio."name='$newname',slot".$acessorio."id='".intval($droprow["id"])."',".sqlatributosdojogador().",durabilidade='".addslashes(juntardurabilidade($durab))."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
		} else {
			// Mochila: "nome,id,4,durabilidade" (tipo 4 = drop; durabilidade cheia ou X). Se o espaço estava ocupado por outro item,
			// o item antigo é descartado; se era uma pilha do mesmo item com lugar, soma 1 nela.
			$itempronto = ($armadrop > 0) ? $nomecompleto.",".$armadrop.",1,X" : ($econsumivel ? $nomecompleto.",".$consumivel[0].",".intval($consumivel[1]).",1" : $nomecompleto.",".intval($droprow["id"]).",4,".durabilidadenova(4, $droprow["id"]));
			if ($slot == 12) {
				$antes = $userrow;
				$espacos = mochilaguardar($userrow, $itempronto);
				if ($espacos === false) { doquery("ROLLBACK", "users"); header("Location: ./index.php?do=drop&conteudo=".rawurlencode(t("Sua mochila está cheia. Escolha um espaço para substituir."))); die(); }
				list($set, $onde) = sqlmochila($userrow, $antes, $espacos);
				doquery("UPDATE {{table}} SET $set WHERE id='".intval($userrow["id"])."' $onde LIMIT 1", "users");
			} else {
				$bp = $slot - 3;
				$atual = (string)$userrow["bp".$bp];
				if ($atual != "None" && itemsemqtd($atual) === itemsemqtd($itempronto) && qtditem($atual) < pilhamaxima($itempronto)) { $itempronto = itemcomqtd($atual, qtditem($atual) + 1); }
				doquery("UPDATE {{table}} SET bp".$bp."='".addslashes($itempronto)."' WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");
			}
		}
		doquery("COMMIT", "users");

		header("Location: ./index.php?conteudo=".rawurlencode(t("O item foi equipado ou adicionado à mochila com sucesso.")));
		die();

	}

	$attributearray = array("maxhp"=>"Max HP",
							"maxmp"=>"Max CH",
							"maxtp"=>"Max TP",
							"defensepower"=>"Poder de Defesa",
							"attackpower"=>"Poder de Ataque",
							"strength"=>"Força",
							"dexterity"=>"Armadura",
							"expbonus"=>"Bônus de Experiência",
							"goldbonus"=>"Bônus de Ryou",
							"sorte"=>"Sorte",
							"agilidade"=>"Agilidade",
							"determinacao"=>"Determinação",
							"precisao"=>"Precisão",
							"inteligencia"=>"Inteligência",
							"droprate"=>"Chance de Drop",
							"maxnp"=>"Max NP",
							"maxep"=>"Max EP",
							"critico"=>"Chance de Crítico",
							"esquiva"=>"Esquiva",
							"regenhp"=>"Regeneração de HP por passo", "regenmp"=>"Regeneração de CH por passo", "regentp"=>"Regeneração de TP por passo", "regennp"=>"Regeneração de NP por passo", "regenep"=>"Regeneração de EP por passo",
							"economiachakra"=>"Economia de Chakra");
	$porcento = array("critico"=>"%", "esquiva"=>"%", "economiachakra"=>"%");

	// Variante rara pendente (users.dropvariante): nome, aviso, bônus e ícone da variante.
	$nomedrop = nomecomvariante($droprow["name"], $econsumivel ? "" : (string)$userrow["dropvariante"]);
	$efeitodrop = variantedoitem($nomedrop);
	$avisorara = ($efeitodrop != "") ? "<font color=\"".variantesitens()[$efeitodrop]["cor"]."\"><b>✦ ".t("Variante rara!")."</b></font><br>" : "";
	$page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/drop.gif\" /></center></td></tr></table><center><table><tr><td width=\"50%\"><center>".t("O inimigo dropou o seguinte item:")." <br>".$avisorara."<br><table><tr><td><table><tr bgcolor=\"#613003\"><td><center><font color=white><b>".tnomeitem($nomedrop)."</b></font></center></td></tr>";

	// Atributos do item ("campo,valor"; attribute2 pode ser "X" = nenhum).
	$cores = array(1=>"#E4D094", 2=>"#FFF1C7");
	foreach (array(1=>"attribute1", 2=>"attribute2", 3=>"attribute3") as $n => $coluna) {
		if (!isset($droprow[$coluna]) || $droprow[$coluna] == "X" || $droprow[$coluna] == "") { continue; }
		$atributo = array_pad(explode(",", $droprow[$coluna]), 2, 0);
		$nome = isset($attributearray[$atributo[0]]) ? t($attributearray[$atributo[0]]) : $atributo[0];
		$valor = intval($atributo[1]);
		$sufixo = isset($porcento[$atributo[0]]) ? $porcento[$atributo[0]] : "";
		$linha = $nome.(($valor > 0) ? " +".$valor : " ".$valor).$sufixo;
		if ($valor < 0) { $linha = "<font color=\"red\"><b>▼ ".$linha."</b></font>"; } // item de compensação: o lado negativo em vermelho.
		$page .= "<tr bgcolor=\"".$cores[2 - $n % 2]."\"><td>".$linha."</td></tr>";
	}

	if ($armadrop > 0) { // arma rara: vai para a mochila; equipa-se de lá.
		$page .= "<tr bgcolor=\"#FFF1C7\"><td>".t("Arma rara e indestrutível (vai para a mochila; equipe pela mochila).")."</td></tr>";
	} elseif ($econsumivel) { // poção/comida: mostra quanto recupera.
		if ($consumivel[0] == "jutsu") { // pergaminho: o jutsu que ensina, os efeitos e os requisitos.
			$jr = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($consumivel[1])."' LIMIT 1", "spells"));
			if ($jr) { $page .= "<tr bgcolor=\"#FFF1C7\"><td>".tf("Ensina o jutsu <b>%s</b> (vai para a mochila).", t($jr["name"]))."<br><span class=\"small\">".implode("<br>", jutsudescricaoefeitos($jr))."<br>".jutsutextorequisitos($jr).(jutsuconhece($userrow, $jr["id"]) ? "<font color=\"darkred\">".t("Você já conhece este jutsu.")."</font>" : "")."</span></td></tr>"; }
		} else
		if ($consumivel[0] == "tel") { $page .= "<tr bgcolor=\"#FFF1C7\"><td>".t("Leva você para uma vila aleatória, talvez uma que você ainda não conhece (vai para a mochila).")."</td></tr>"; } else
		$page .= "<tr bgcolor=\"#FFF1C7\"><td>".tf("Recupera %s de %s (vai para a mochila).", intval($consumivel[1]), ($consumivel[0] == "mp") ? "CH" : strtoupper($consumivel[0]))."</td></tr>";
	}
	foreach (bonusvariante($nomedrop, $droprow, "drops") as $b) { $page .= "<tr bgcolor=\"#FFF1C7\"><td>✦ ".explicaratributo($b)."</td></tr>"; }
	$agorajava = ($armadrop > 0) ? conteudoexplic($armadrop, '1', 'idatr', '*', $nomedrop, 1, true) : conteudoexplic($droprow["id"], '4', 'idatr', (intval($droprow["durabilidade"]) > 0 ? intval($droprow["durabilidade"]) : '*'), $nomedrop, 1, true); // compara com o acessório equipado.
	$page .= "</table></td><td style=\"min-width:38px;vertical-align:top\"><div style=\"padding-top: 3px\"><img src=\"".($jutsudoscroll > 0 ? iconepergaminho($jutsudoscroll) : iconeitem($droprow["id"], true, $nomedrop))."\" width=\"34\" height=\"34\" style=\"border:2px #e4d094 solid;width:34px;height:34px;max-width:none;display:block\" id=\"idatr\" onmouseout=\"fecharexplic();\" onmouseover=\"$agorajava\"></div></td></tr></table></center><br>";

	// Espaços da mochila (valores 4 a 11 do formulário; 12 = guardar juntando na pilha, se o item empilha).
	$itemnovo = ($armadrop > 0) ? $nomedrop.",".$armadrop.",1,X" : ($econsumivel ? $nomedrop.",".$consumivel[0].",".intval($consumivel[1]).",1" : $nomedrop.",".intval($droprow["id"]).",4,".durabilidadenova(4, $droprow["id"]));
	$maxpilha = pilhamaxima($itemnovo);
	// Slot pré-marcado: "guardar na mochila" (se empilha); senão o primeiro slot de drop livre; senão o primeiro espaço
	// livre da mochila. Sem nenhum livre, nada é marcado (para não descartar um item sem o jogador escolher).
	$marcado = ($maxpilha > 1) ? 12 : 0;
	if ($marcado == 0 && !$somochila) { for ($h = 1; $h <= NUMACESSORIOS; $h++) { if ($userrow["slot".$h."name"] == "None") { $marcado = ($h == 4) ? 13 : $h; break; } } }
	if ($marcado == 0) { for ($h = 1; $h <= 8; $h++) { if ($userrow["bp".$h] == "None") { $marcado = 3 + $h; break; } } }
	$chk = function($v) use ($marcado) { return ($v == $marcado) ? " checked" : ""; };
	// Lista "Onde guardar?" em dois grupos com título: os 4 acessórios (equipam o item na hora) e os 8 espaços da mochila.
	$vazio = "<i style=\"color:#8a7a5a\">".t("vazio")."</i>";
	$titulo = function($txt, $dica) { return "<tr bgcolor=\"#613003\"><td colspan=\"3\" style=\"padding:3px 6px;color:#fff6d8;font-weight:bold;font-size:11px;letter-spacing:1px\">".$txt." <span style=\"font-weight:normal;letter-spacing:0;color:#f3d98a\">".$dica."</span></td></tr>"; };
	$linha = function($v, $icone, $rotulo, $nome, $cor) use ($chk) { return "<tr bgcolor=\"$cor\"><td width=\"10\"><input type=\"radio\" id=\"slot$v\" name=\"slot\" value=\"$v\"".$chk($v)."></td><td width=\"20\"><center><img src=\"$icone\" alt=\"\"></center></td><td><label for=\"slot$v\" style=\"cursor:pointer;display:block\"><b style=\"color:#663300\">$rotulo:</b> $nome</label></td></tr>"; };
	$botao = "";
	if (!$somochila) {
		$botao .= $titulo(t("ACESSÓRIOS EQUIPADOS"), t("(equipa agora)"));
		for ($h = 1; $h <= NUMACESSORIOS; $h++) { $botao .= $linha(($h == 4) ? 13 : $h, "images/orb.gif", tf("Acessório %s", $h), ($userrow["slot".$h."name"] == "None") ? $vazio : nomeitemhtml($userrow["slot".$h."name"]), ($h % 2) ? "#E4D094" : "#FFF1C7"); }
	}
	$botao .= $titulo(t("MOCHILA"), t("(só guarda)"));
	if ($maxpilha > 1) { $botao .= "<tr bgcolor=\"#FFF1C7\"><td width=\"10\"><input type=\"radio\" id=\"slot12\" name=\"slot\" value=\"12\"".$chk(12)."></td><td width=\"20\"><img src=\"images/backpack_pequena.gif\" alt=\"\"></td><td><label for=\"slot12\" style=\"cursor:pointer;display:block\"><b>".tf("Guardar na mochila (empilha até %s)", $maxpilha)."</b></label></td></tr>"; }
	for ($h = 1; $h <= 8; $h++) {
		if ($userrow["bp".$h] != "None") {
			$bpitem = explode(",", $userrow["bp".$h]);
			$nomebp = nomeitemhtml($bpitem[0]).((qtditem($userrow["bp".$h]) > 1) ? " ×".qtditem($userrow["bp".$h]) : "");
			if ($maxpilha > 1 && itemsemqtd($userrow["bp".$h]) === itemsemqtd($itemnovo) && qtditem($userrow["bp".$h]) < $maxpilha) { $nomebp .= " <font color=\"darkgreen\">".t("(+1 na pilha)")."</font>"; }
		} else { $nomebp = $vazio; }
		$botao .= $linha(3 + $h, "images/backpack_pequena.gif", tf("Mochila %s", $h), $nomebp, ($h % 2) ? "#FFF1C7" : "#E4D094");
	}

	$page .= "<center>".t("Escolha onde guardar o item: num <b>acessório</b> (ele é equipado na hora) ou num espaço da <b>mochila</b>. <font color=\"red\">Se o espaço já tiver um item, o item antigo será descartado</font>.<br><br>Os espaços escritos <i>vazio</i> estão livres.")."</center></td><td>";
	$page .= "<center><form action=\"index.php?do=drop\" method=\"post\"><table cellspacing=\"1\" style=\"min-width:220px\"><tr bgcolor=\"#613003\"><td colspan=\"3\"><center><font color=white>".t("Onde guardar?")."</font></center></td></tr>
	$botao
	</table>
 <button type=\"submit\" name=\"submit\" value=\"OK!\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_sim.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Guardar")."</button></form></center></td></tr></table><br>";
	$page .= "<center>".t("Você também pode continuar <a href=\"index.php\">explorando</a> e descartar esse item.")."</center>";

	display($page, "Item Drop");

}

function textoperdasmorte($perdas) { // Tela de morte com as perdas (já traduzida): caixa centralizada no mesmo estilo da caixa da vitória.

	$seta = "<img src=\"images/setabranca.gif\" alt=\"\" style=\"vertical-align:middle;margin-right:3px\">";
	$linhas = $seta.tf("Ryou perdido: <b>%s</b> (%s%% do que você carregava)", number_format($perdas["ryou"]), $perdas["pct"]["ryou"])."<br />"
		.$seta.tf("Experiência perdida: <b>%s</b> (%s%% da barra do seu nível; você nunca cai de nível)", number_format($perdas["exp"]), $perdas["pct"]["exp"])."<br />"
		.$seta.(($perdas["item"] != "") ? tf("Item perdido: <b>%s</b>", t($perdas["item"])) : t("Nenhum item perdido."));
	return "<center><div id=\"caixamorte\" style=\"width:290px;box-sizing:border-box;text-align:left;background:#3b1a0e;border:2px solid #8b2a1a;border-radius:6px;color:#fbe9d0;padding:9px 11px;margin:6px 0;font-size:12px;line-height:1.45\">"
		."<div style=\"text-align:center;font-size:15px;color:#ffb39c;margin-bottom:6px\">".t("<b>Você morreu.</b>")."</div>"
		.$linhas
		."<div style=\"margin-top:7px;padding-top:6px;border-top:1px solid #7a3a22\">".t("Você voltou para a sua vila natal com um quarto dos Pontos de Vida.")."</div>"
		."<div style=\"text-align:center;margin-top:8px\"><a href=\"index.php\" id=\"botaovoltarvila\" class=\"btnjogo bj-neutro\"><img src=\"images/seta_oeste.png\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".t("Voltar para a vila")."</a></div>"
		."</div></center>";

}

function dead() { // Página de morte (index.php?do=dead). Mostra a penalidade conforme o nível do jogador.

	global $userrow;
	$p = penalidademorte($userrow["level"]);
	$page = t("<b>Você morreu.</b>")."<br /><br />".tf("No seu nível, morrer custa %s%% do Ryou que você carrega, %s%% da barra de experiência do nível e tem %s%% de chance de perder um item comum da mochila.", $p["ryou"], $p["exp"], $p["item"])."<div style=\"text-align:center;margin-top:10px\"><a href=\"index.php\" id=\"botaovoltarvila\" class=\"btnjogo bj-neutro\"><img src=\"images/seta_oeste.png\" width=\"16\" height=\"16\" alt=\"\" style=\"vertical-align:middle\"> ".t("Voltar para a vila")."</a></div>";
	display($page, t("Você morreu"));

}

?>
