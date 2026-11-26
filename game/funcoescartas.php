<?php // funcoescartas.php :: Cartão HTML/CSS do monstro (cartas.css), usado na luta (fight.php) e na ajuda de inimigos (help_monsters.php).

if (!function_exists('raridadeconfig')){
function raridadeconfig() { // Bônus por raridade (naruto_control; padrões se a coluna faltar): % de atributos/XP/Ryou e fator de drop.
	global $controlrow;
	$c = is_array($controlrow) ? $controlrow : array();
	$v = function ($k, $pad) use ($c) { return isset($c[$k]) ? max(0, intval($c[$k])) : $pad; };
	return array(1=>array("pct"=>$v("raridade_elite_pct", 15), "drop"=>$v("raridade_drop_elite", 150)),
		2=>array("pct"=>$v("raridade_raro_pct", 30), "drop"=>$v("raridade_drop_raro", 200)),
		"teto"=>min(100, $v("raridade_drop_teto", 60))); // teto da chance de drop por item com o bônus (%)
}
}

if (!function_exists('monstroraridade')){
function monstroraridade($monsterrow) { // Aplica o bônus de elite/raro no HP, ataque e defesa (só na memória: o banco guarda a base que o admin edita). Idempotente.
	if (!is_array($monsterrow) || !empty($monsterrow["_rarbonus"])) { return $monsterrow; }
	$rar = isset($monsterrow["raridade"]) ? intval($monsterrow["raridade"]) : 0;
	$cfg = raridadeconfig();
	$monsterrow["_rarbonus"] = 1;
	if (!isset($cfg[$rar])) { return $monsterrow; } // comum e chefe (regras próprias) ficam como estão.
	$f = 1 + $cfg[$rar]["pct"] / 100;
	foreach (array("maxhp", "maxdam", "armor") as $k) { if (isset($monsterrow[$k])) { $monsterrow[$k] = (int)ceil(intval($monsterrow[$k]) * $f); } }
	return $monsterrow;
}
}

if (!function_exists('raridadetraco')){
function raridadetraco($monsterrow) { // Texto do traço da raridade (já traduzido) ou "".
	$rar = isset($monsterrow["raridade"]) ? intval($monsterrow["raridade"]) : 0;
	if ($rar == 1) { return "&#128737; ".htmlspecialchars(t("Couraça: −10% de dano recebido"), ENT_QUOTES, "UTF-8"); }
	if ($rar == 2) { return "&#10010; ".htmlspecialchars(t("Vigor: +3% HP por turno"), ENT_QUOTES, "UTF-8"); }
	return "";
}
}

if (!function_exists('couracadano')){
function couracadano($monsterrow, $dano) { // Couraça (elite): o inimigo recebe 10% menos dano (mínimo 1 quando houve dano).
	$dano = intval($dano);
	if ($dano <= 0 || !isset($monsterrow["raridade"]) || intval($monsterrow["raridade"]) != 1) { return $dano; }
	return max(1, $dano - (int)floor($dano * 10 / 100));
}
}

if (!function_exists('cartaodomonstro')){
function cartaodomonstro($monsterrow, $hpatual, $icones = "", $destaque = false) { // Cartão HTML/CSS do monstro (cartas.css): retrato, NV, nome, elemento, barra de HP, ataque, defesa e imunidade. $icones: efeitos de jutsu no inimigo; $destaque: HP em vermelho (fim da luta).
	static $cssposto = false;
	if (!function_exists('imunidadesdoinimigo')) { include_once(__DIR__.'/funcoesjutsus.php'); } // páginas fora da luta (chefes.php) não carregam os jutsus.
	$h = function ($x) { return htmlspecialchars($x, ENT_QUOTES, "UTF-8"); };
	// Imunidades (funcoesjutsus.php): rótulos curtos no selo e o texto completo ("Imune a ...") no title.
	$listaimune = imunidadesdoinimigo($monsterrow);
	$immune = count($listaimune) ? "<span class=\"chip\" style=\"cursor:help\" title=\"".$h(imunidadestexto($listaimune))."\">".$h(imunidadestexto($listaimune, true, ", "))."</span>" : "<span class=\"chip ok\">".$h(t("Nenhuma"))."</span>";
	// Retrato: recorte da carta antiga (layoutnovo/monstros/N.jpg); sem carta própria, a padrão.
	$imagem = "layoutnovo/monstros/".intval($monsterrow["id"]).".jpg";
	if (!file_exists($imagem)) { $imagem = "layoutnovo/monstros/padrao.jpg"; }
	// Retrato próprio em alta (layoutnovo/monstros/retratos/N.jpg, 200x300), quando existir: mais nítido que o recorte.
	$retrato = "layoutnovo/monstros/retratos/".intval($monsterrow["id"]).".jpg";
	// Duas camadas: na frente a ilustração na largura do retrato (como antes); atrás a mesma ilustração maior e desfocada, que preenche o espaço em cima/embaixo quando a carta é mais alta que a ilustração.
	$fundoret = "background-image:url('".(file_exists($retrato) ? $retrato : $imagem)."')";
	$estiloret = file_exists($retrato) ? "background-image:url('".$retrato."');background-size:100% auto;background-position:center" : "background-image:url('".$imagem."')";
	$nomes = array("neutro"=>"Neutro", "fogo"=>"Fogo", "agua"=>"Água", "vento"=>"Vento", "raio"=>"Raio", "terra"=>"Terra");
	$el = isset($nomes[$monsterrow["elemento"]]) ? $monsterrow["elemento"] : "neutro";
	$raridades = array(1=>array("elite", "ELITE"), 2=>array("raro", "RARO"), 3=>array("chefe", "CHEFE"));
	$rar = isset($monsterrow["raridade"]) ? intval($monsterrow["raridade"]) : 0;
	$classe = "cartam el-".$el.(isset($raridades[$rar]) ? " r-".$raridades[$rar][0] : "");
	// Linha comum de selos (traço da raridade, "Rouba itens", "Envenena 20%", item do jogador com ele): funcoesroubo.php :: cartaoselos().
	if (!function_exists('cartaoselos')) { include_once(__DIR__.'/funcoesroubo.php'); }
	$selos = cartaoselos($monsterrow, raridadetraco($monsterrow) != "" ? "<span class=\"ctraco\">".raridadetraco($monsterrow)."</span>" : "");
	if ($selos != "") { $classe .= " comtraco comselos"; } // linha extra: cartão um pouco mais alto.
	$selo = isset($raridades[$rar]) ? "<div class=\"cselo\">".$h(t($raridades[$rar][1]))."</div>" : "";
	$max = max(1, intval($monsterrow["maxhp"]));
	$hp = max(0, intval($hpatual));
	$pct = min(100, round($hp * 100 / $max));
	$nome = html_entity_decode(t($monsterrow["name"]), ENT_QUOTES, "UTF-8"); // alguns nomes vêm com &amp; do banco
	$css = ""; // cartas.css já vem no <head> (lib.php scriptcelular), senão a carta "pisca" grande a cada ataque
	$cssposto = true;
	return $css."<div class=\"".$classe."\" id=\"cartamonstro\"><div class=\"cin\">"
		."<div class=\"cret\"><div class=\"cretf\" style=\"".$fundoret."\"></div>".(file_exists($retrato) ? "<img class=\"cretimg\" src=\"".$retrato."\" alt=\"\">" : "<div class=\"creti crecorte\" style=\"".$estiloret."\"></div>")."".$selo."<div class=\"cnv\">".$h(t("NV"))." ".intval($monsterrow["level"])."</div></div>"
		."<div class=\"cdad\"><div class=\"ccab\"><span class=\"cnome".(mb_strlen($nome) > 20 ? " cmic" : (mb_strlen($nome) > 15 ? " cpeq" : ""))."\" title=\"".$h($nome)."\">".$h($nome)."</span>"
		."<span class=\"cel".(mb_strlen($nome) > 15 ? " csotxt" : "")."\" title=\"".$h(tf("Elemento %s", t($nomes[$el])))."\"><img src=\"images/".$el.".gif\" alt=\"\">".$h(t($nomes[$el]))."</span></div>"
		."<div class=\"chp\"><i id=\"barrahpmonstro\" style=\"width:".$pct."%\"></i><span".($destaque ? " style=\"color:#ffd0c8\"" : "").">HP <b id=\"danoalt\">".$hp."</b> / ".$max."</span></div>"
		."<div class=\"cg\"><div>&#9876; ".$h(t("Ataque"))."<b>".intval($monsterrow["maxdam"])."</b></div><div>&#128737; ".$h(t("Defesa"))."<b>".intval($monsterrow["armor"])."</b></div></div>"
		."<div class=\"cim\">".$h(t("Imunidade")).": ".$immune."</div>"
		.$selos
		."</div></div>".(($icones != "") ? "<div class=\"cico\">".$icones."</div>" : "")."</div>";
}
}
?>
