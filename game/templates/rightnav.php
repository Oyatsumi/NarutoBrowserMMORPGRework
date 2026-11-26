<?php
// rightnav.php :: Coluna da direita (personagem, barras de status, equipamentos, mochila e histórico).
global $userrow;
$t = "t"; // Tradução dentro do heredoc: {$t("texto")}.
include_once('funcoesinclusas.php');
$jutsudebuscahtml = ""; $olhosenjutsu = ""; $bpcodigo = array();

// Slots da mochila (bp1..bp8). Formato de cada slot: "nome,id,tipo,durabilidade[,quantidade]".
for ($i = 1; $i <= 8; $i ++){
	if ($userrow["bp".$i] != "None"){
		$varbackpack[$i] = array_pad(explode(",",$userrow["bp".$i]), 4, "X");
		$pastadoslot = "";
		if (is_numeric($varbackpack[$i][2]) && $varbackpack[$i][2] > 3) {$pastadoslot = "drops/";}
		if ($varbackpack[$i][3] == "X"){$varbackpack[$i][3] = "*";}
		$nomebp = $varbackpack[$i][0]; // nome guardado (pode ter a variante rara ":efeito").
		$varbackpack[$i][0] = conteudoexplic($varbackpack[$i][1], $varbackpack[$i][2], 'bp'.$i.'atr', $varbackpack[$i][3], $nomebp, qtditem($userrow["bp".$i]), true); // compara com o equipado no mesmo espaço.
		// Consumível (id não numérico: "hp", "mp"...): ícone hp50.gif se existir, senão a poção da cor do tipo (iconeconsumivel()).
		$iconebp = is_numeric($varbackpack[$i][1]) ? iconeitem($varbackpack[$i][1], $pastadoslot != "", $nomebp) : iconeconsumivel($varbackpack[$i][1], $varbackpack[$i][2], $nomebp);
		$bpcodigo[$i] = "<a href=\"backpack.php?qual=$i&amp;v=".marcaitem($userrow["bp".$i])."\"><img src=\"".$iconebp."\" width=\"34\" height=\"34\" border=\"0\" style=\"display:block\" onmouseover=\"".$varbackpack[$i][0]."\" onmouseout=\"fecharexplic();\"  id=\"bp".$i."atr\" alt=\"\" /></a>";
		// Pilha: a quantidade aparece pequena no canto inferior direito do ícone (5º campo do slot).
		$bpcodigo[$i] = iconecomqtd($bpcodigo[$i], $userrow["bp".$i]);
	}else{
		$bpcodigo[$i] = "<img src=\"images/gif.gif\" width=\"34\" height=\"34\" border=\"0\" style=\"display:block\" alt=\"\" />";
	}
}

// Cada espaço num div absoluto de 34x34 (line-height 0 evita o espaço de texto abaixo da imagem).
$bpquadros = "";
for ($i = 1; $i <= 8; $i ++){
	$bpquadros .= "<div style=\"position:absolute;left:".(9 + 40 * (($i - 1) % 4))."px;top:".($i <= 4 ? 10 : 50)."px;width:34px;height:34px;line-height:0;font-size:0\">".$bpcodigo[$i]."</div>";
}

// Link do Jutsu de Busca (só aparece depois de treinado).
if ($userrow['jutsudebuscahtml'] == 1){
	$jutsudebuscahtml = "<a href=\"mainmsg.php?do2=usarjutsubusca\" id=\"adm\" title=\"".t("Jutsu Ocular")."\">".t("Jutsu de Busca")."</a><br>";
}

// Durabilidade dos 7 equipamentos (formato "-,arma,colete,bandana,slot1,slot2,slot3,slot4"; X = infinita).
$durabm = array_pad(explode(",",$userrow["durabilidade"]), 8, "X");
for ($i = 1; $i < 8; $i ++){
	if ($durabm[$i] == "X"){$durabm[$i] = "*";}
}

// Lista de jutsus vazia.
if (!isset($userrow["magiclist"]) || $userrow["magiclist"] == "None" || $userrow["magiclist"] == "Nenhum" || $userrow["magiclist"] == t("Nenhum")){$userrow["magiclist"] = t("Nenhum Jutsu.");}

// Olho do Senjutsu (ativar/desativar o modo sábio).
// O título mostra o bônus da especialização (senjutsubonustexto(), funcoesinclusas.php).
$bonussenjutsu = ($userrow["senjutsuhtml"] != "") ? " — ".htmlspecialchars(implode(", ", senjutsubonustexto(intval($userrow["charclass"]), intval($userrow["level"]))), ENT_QUOTES, 'UTF-8') : "";
if ($userrow["senjutsuhtml"] == "senjutsu"){ senjutsu(); } // cobra o NP; com NP 0 o olho fecha aqui mesmo.
if ($userrow["senjutsuhtml"] == "fechado"){
	$olhosenjutsu = "<center><a href=\"senjutsu.php?do=usar\"><img src=\"images/olhos/".$userrow["senjutsuhtml"].".jpg\" border=\"0\" title=\"".t("Ativar Senjutsu (1NP/3s)").$bonussenjutsu."\" alt=\"\" /></a></center>";
}elseif($userrow["senjutsuhtml"] == "senjutsu"){
	$titulo = t("Desativar Senjutsu").$bonussenjutsu;
	$olhosenjutsu = "<center><a href=\"senjutsu.php?do=cancelar\"><img src=\"images/olhos/".$userrow["senjutsuhtml"].".jpg\" border=\"0\" title=\"$titulo\" alt=\"\" /></a></center>";
}

// Olho do doujutsu (funcoesdoujutsu.php): ativar/desativar pelo ícone (apagado = desativado); "Doujutsu" abre a tela do olho.
// Fica embaixo do olho do Senjutsu; os dois não ficam ativos ao mesmo tempo.
include_once('funcoesdoujutsu.php');
if (isset($userrow["doujutsu"]) && (string)$userrow["doujutsu"] != "") {
	doujutsucobrar(); // cobra o Chakra; sem Chakra o olho fecha aqui mesmo.
	if ((string)$userrow["doujutsu"] == "sharingan" && intval($userrow["doujutsu_estagio"]) < 4 && doujutsuevoluir($userrow["id"]) > 0) { $userrow["doujutsu_estagio"] = intval($userrow["doujutsu_estagio"]) + 1; } // caminho das vitórias em duelos.
	$olhodj = doujutsudojogador($userrow);
	if ($olhodj) {
		$djativo = doujutsuativo($userrow);
		$djtitulo = htmlspecialchars(($djativo ? t("Desativar") : t("Ativar"))." ".doujutsunome($olhodj)." (".doujutsucustotexto($olhodj, $userrow["level"]).") — ".implode(", ", doujutsubonustexto($olhodj)), ENT_QUOTES, 'UTF-8');
		$olhosenjutsu .= "<center id=\"olhodoujutsu\"><a href=\"doujutsu.php?do=".($djativo ? "desativar" : "ativar")."\"><img src=\"images/olhos/".htmlspecialchars($olhodj["imagem"]).".jpg\" border=\"0\" title=\"$djtitulo\" alt=\"\"".($djativo ? "" : " style=\"opacity:.45\"")." /></a><br><a href=\"doujutsu.php\" style=\"font-size:10px\">".t("Doujutsu")."</a></center>";
	}
}

// Linhas dos itens equipados lidas juntas (1 query em items e 1 em drops, em vez de uma por espaço) e guardadas no
// cache de linhadoitem(), que é de onde conteudoexplic() lê cada linha.
include_once('funcoesinclusas.php');
foreach (array("items" => array("weapon"=>"1", "shield"=>"3", "armor"=>"2"), "drops" => array("slot1"=>"4", "slot2"=>"4", "slot3"=>"4", "slot4"=>"4")) as $tabelaequip => $espacosequip) {
	$idsequip = array();
	foreach ($espacosequip as $s => $tp) { if (is_numeric($userrow[$s."id"]) && intval($userrow[$s."id"]) > 0) { $idsequip[] = intval($userrow[$s."id"]); } }
	if (count($idsequip) == 0) { continue; }
	$linhasequip = array();
	$qequip = doquery("SELECT * FROM {{table}} WHERE id IN (".implode(",", array_unique($idsequip)).")", $tabelaequip);
	while ($requip = mysqli_fetch_array($qequip, MYSQLI_ASSOC)) { $linhasequip[intval($requip["id"])] = $requip; }
	foreach ($espacosequip as $s => $tp) {
		if (isset($linhasequip[intval($userrow[$s."id"])])) { linhadoitem($userrow[$s."name"].",".$userrow[$s."id"].",".$tp, $linhasequip[intval($userrow[$s."id"])]); }
	}
}

// Caixas "Efeitos ativos" e "Missão ativa" (funcoespainel.php), no padrão visual B.
include_once('funcoespainel.php');
$caixaefeitos = caixaefeitosativos($userrow); // Missão ativa e Missões diárias ficam no painel "Missões" (leftnav.php).
// Missões diárias e aviso de mochila cheia (funcoesdiarias.php).
include_once('funcoesdiarias.php');

$avisodrop = avisomochilacheia($userrow);

// Aviso de desgaste (durabilidade em até 10% da máxima), com o caminho para o reparo (towns.php :: reparar()).
$avisodurab = avisodurabilidade($userrow);
// Talento disponível (funcoestalentos.php): vai junto do aviso de desgaste.
include_once('funcoestalentos.php');
$avisodurab .= avisotalento($userrow);
// Drop pendente (a última luta deixou um item e o jogador ainda não o pegou): aviso com link para a tela do drop, mostrado logo abaixo da mochila.
$avisodrop = isset($avisodrop) ? $avisodrop : ""; // já pode ter o aviso de mochila cheia.
if (intval($userrow["dropcode"]) != 0) {
	$dpr = mysqli_fetch_array(doquery("SELECT name FROM {{table}} WHERE id='".intval($userrow["dropcode"])."' LIMIT 1", "drops"));
	if ($dpr) {
		$avisodrop .= "<div style=\"margin:4px 6px 2px;padding:4px 6px;background:#613003;border:1px solid #d6aa3c;color:#fff6d8;text-align:center\">".t("Drop pendente:")." <b>".htmlspecialchars(t($dpr["name"]), ENT_QUOTES, "UTF-8")."</b><br><a href=\"index.php?do=drop\" style=\"color:#f3d98a\">".t("Pegar o Drop")." &raquo;</a></div>";
	}
}

// Explicação (tooltip) dos atributos de cada item equipado.
$armaatr = conteudoexplic($userrow["weaponid"], '1', 'armaatr', $durabm[1], $userrow["weaponname"]);
$shieldatr = conteudoexplic($userrow["shieldid"], '3', 'shieldatr', $durabm[3], $userrow["shieldname"]);
$armoratr = conteudoexplic($userrow["armorid"], '2', 'armoratr', $durabm[2], $userrow["armorname"]);
$slot1atr = conteudoexplic($userrow["slot1id"], '4', 'slot1atr', $durabm[4], $userrow["slot1name"]);
$slot2atr = conteudoexplic($userrow["slot2id"], '4', 'slot2atr', $durabm[5], $userrow["slot2name"]);
$slot3atr = conteudoexplic($userrow["slot3id"], '4', 'slot3atr', $durabm[6], $userrow["slot3name"]);
$slot4atr = conteudoexplic($userrow["slot4id"], '4', 'slot4atr', $durabm[7], $userrow["slot4name"]);

// Ícones (da variante rara, se houver) e nomes com o rótulo colorido da variante. Os nomes são montados aqui
// porque o display() do lib.php troca {$nomeequip["weapon"]}... por t(nome), que não conhece o sufixo ":efeito".
$icone = array(); $nomeequip = array();
foreach (array("weapon"=>false, "armor"=>false, "shield"=>false, "slot1"=>true, "slot2"=>true, "slot3"=>true, "slot4"=>true) as $s => $drop) {
	$icone[$s] = iconeitem($userrow[$s."id"], $drop, $userrow[$s."name"]);
	$nomeequip[$s] = nomeitemhtml($userrow[$s."name"]);
}

// Barra de experiência do level atual.
$classe = intval($userrow['charclass']); if ($classe < 1 || $classe > 3) { $classe = 1; }
// Level atual e o próximo numa query só (id é a chave primária: no máximo uma linha de cada).
$lvlrow = null; $lvlrow2 = null;
$lvlquery = doquery("SELECT id, ".$classe."_exp FROM {{table}} WHERE id IN ('".intval($userrow['level'])."', '".(intval($userrow['level']) + 1)."')", "levels");
while ($lvlaux = mysqli_fetch_array($lvlquery)) {
	if (intval($lvlaux["id"]) == intval($userrow['level'])) { $lvlrow = $lvlaux; } else { $lvlrow2 = $lvlaux; }
}
$expatual = intval(str_replace(",", "", $userrow['experience']));
$expinicio = ($lvlrow) ? intval($lvlrow[$classe."_exp"]) : 0;
if ($lvlrow2) {
	$xpproxlvl = intval($lvlrow2[$classe."_exp"]) - $expinicio;
	if ($xpproxlvl <= 0) { $xpproxlvl = 1; }
	$porcconcluida = floor((($expatual - $expinicio) * 100) / $xpproxlvl);
	$quantofaltaxp = $xpproxlvl - ($expatual - $expinicio);
} else { // Level máximo.
	$porcconcluida = 100; $quantofaltaxp = 0;
}
if ($porcconcluida < 0) { $porcconcluida = 0; } if ($porcconcluida > 100) { $porcconcluida = 100; }
// Barra de level só com <span> + background (sem <img>): o CSS do celular redimensiona imagens (height:auto) e esticava a barra.
$widthbar = round((155 * $porcconcluida)/100);
$iconeryou = iconeryou(); // mesmo ícone do banco
$ryoucarregado = number_format(intval($userrow["gold"]), 0, ",", ".");
$barrahtml = "<div style=\"display: block; width:160px; height:37px; position:relative; margin:0 auto\" onmouseover=\"explicdrop('qualquer', '".t("Dados do Level")."', '".t("XP Atual:")." ".$userrow['experience']."<br>".t("XP P/ Lvl Up:")." ".$quantofaltaxp."<br>".t("Porc. Concluída:")." ".$porcconcluida."%','1','1');\" onmouseout=\"fecharexplic();\"><span style=\"position:absolute;left:0;top:0;z-index:2;display:block;width:{$widthbar}px;height:36px;background:url(images/levelbarin.jpg) repeat-x 0 0\"></span><span id=\"qualquer\" style=\"position:absolute;left:0;top:0;z-index:3;display:block;width:160px;height:37px;background:url(images/levelbar.png) no-repeat 0 0\"></span><b style=\"position:absolute;z-index:4;left:44px;top:7px;height:17px;line-height:17px;font-size:15px;font-family:arial,sans-serif;color:#452202;text-align:left\">".$userrow['level']."</b></div>";

// Segunda imagem da arma (armas que ocupam as duas mãos).
if (file_exists('layoutnovo/equipamentos/'.intval($userrow['weaponid']).'d.gif')){
	$segundaarmaimagem = '<a href="desequipar.php?qual=1"><img src="'.iconeitem($userrow['weaponid'], false, $userrow['weaponname'], 'd').'" border="0" onMouseOver="'.$armaatr.'" onmouseout="fecharexplic();" id="armaatr2" alt="" /></a>';
}else{$segundaarmaimagem = '';}


$template = <<<FIMDOTEMPLATE
<br><br>

<div style="position:relative; width:204px">
<div style="background-image: url(layoutnovo/buttons/personagem.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-25px;z-index:2">



<center><a href="outros.php?do=avatar"><img src="layoutnovo/avatares/{{avatar}}.jpg" border="0" title="{$t("Selecionar Avatar")}"></a><br>
</center>$olhosenjutsu
<center><font color="#452708"><b>{{charname}}</b></font>$barrahtml</center>
<center><div onmouseover="explicdrop('qualquer', '{$t("Barras de Status")}', '<center>' +
 '<div style=\'margin-top:0px;color:white;background-color:#780404;padding-top:3px;padding-left:3px;padding-right:3px;padding-bottom:3px;display:block\'>HP: {{currenthp}}/{{maxhp}}</div>' +
 '<div style=\'margin-top:0px;color:white;background-color:#024179;padding-top:3px;padding-left:3px;padding-right:3px;padding-bottom:3px;display:block\'>CH:  {{currentmp}}/{{maxmp}}</div>' +
  '<div style=\'margin-top:0px;color:white;background-color:#766402;padding-top:3px;padding-left:3px;padding-right:3px;padding-bottom:3px;display:block\'>TP: {{currenttp}}/{{maxtp}}</div>' +
   '<div style=\'margin-top:0px;color:white;background-color:#0d6504;padding-top:3px;padding-left:3px;padding-right:3px;padding-bottom:3px;display:block\'>NP: {{currentnp}}/{{maxnp}}</div>' +
    '<div style=\'margin-top:0px;color:white;background-color:#6f0576;padding-top:3px;padding-left:3px;padding-right:3px;padding-bottom:3px;display:block\'>EP: {{currentep}}/{{maxep}}</div></center>',
    '1','1')" onmouseout='fecharexplic();'>{{statbars}}</div></center>
<div style="width:176px;margin:6px auto 0">$caixaefeitos</div>
<center><div style="margin-top:5px;font:bold 12px tahoma,arial,sans-serif;color:#452708" title="{$t("Ryou que você está carregando (o que está no banco não conta)")}">{$t("Ryou:")} $iconeryou<span style="color:#8a5a00;vertical-align:middle">$ryoucarregado</span></div></center>
<!-- Links do painel com ícone 16x16 no lugar do marcador da lista (tools/icones_interface.py). -->
<ul class="painellinks" style="margin:6px 0 0 0;padding-left:14px;text-align:left;list-style:none;line-height:18px">
<li><a href="javascript:mostrarchar('{{charname}}')"><img src="images/painel_status.png" width="16" height="16" alt="" style="vertical-align:middle;margin-right:3px">{$t("Todos os Status")}</a></li>
<li><a href="outroseatributos.php?do=atributos"><img src="images/painel_pontos.png" width="16" height="16" alt="" style="vertical-align:middle;margin-right:3px">{$t("Distribuir Pontos")}</a></li>
<li><a href="treinamentoequests.php?do=quests"><img src="images/painel_missoes.png" width="16" height="16" alt="" style="vertical-align:middle;margin-right:3px">{$t("Painel de Missões")}</a></li>
<li><a href="treinamentoequests.php?do=treinamento"><img src="images/painel_treinos.png" width="16" height="16" alt="" style="vertical-align:middle;margin-right:3px">{$t("Painel de Treinos")}</a></li>
<li><a href="cla.php"><img src="images/painel_cla.png" width="16" height="16" alt="" style="vertical-align:middle;margin-right:3px">{$t("Clã")}</a></li>
<li><a href="temporadas.php"><img src="images/painel_temporadas.png" width="16" height="16" alt="" style="vertical-align:middle;margin-right:3px">{$t("Temporadas de duelo")}</a></li>
<li><a href="chefes.php"><img src="images/painel_chefes.png" width="16" height="16" alt="" style="vertical-align:middle;margin-right:3px">{$t("Chefes da Semana")}</a></li>
<li><a href="talentos.php"><img src="images/painel_talentos.png" width="16" height="16" alt="" style="vertical-align:middle;margin-right:3px">{$t("Talentos")}</a></li>
</ul>



</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div>





<div style="position:relative; width:204px">
<div style="background-image: url(layoutnovo/buttons/inventario.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-25px;z-index:2">



<center>
<div style="position:relative;background-image:url(layoutnovo/equipamentos/equipamentos.png);width:168px;height:116px;background-repeat:no-repeat">
<div style="position:absolute;top:14px;left:66px"><a href="desequipar.php?qual=3"><img src="{$icone["shield"]}" onMouseOver="$shieldatr" onmouseout="fecharexplic();" id="shieldatr" border="0"></a></div>
<div style="position:absolute;top:52px;left:29px"><a href="desequipar.php?qual=1"><img src="{$icone["weapon"]}" border="0" onMouseOver="$armaatr" onmouseout="fecharexplic();" id="armaatr"></a></div>
<div style="position:absolute;top:52px;left:103px">$segundaarmaimagem</div>
<div style="position:absolute;top:52px;left:66px"><a href="desequipar.php?qual=2"><img src="{$icone["armor"]}" border="0" onMouseOver="$armoratr" onmouseout="fecharexplic();" id="armoratr"></a></div>
</div>



</center>

<table width="178">
<tr><td><img src="images/icon_weapon.gif" alt="" title="{$t("Durabilidade:")} $durabm[1]" /></td><td width="100%">{$t("Arma:")} {$nomeequip["weapon"]}</td></tr>
<tr><td><img src="images/icon_armor.gif" alt="" title="{$t("Durabilidade:")} $durabm[2]" /></td><td width="100%">{$t("Colete:")} {$nomeequip["armor"]}</td></tr>
<tr><td><img src="images/icon_shield.gif" alt="" title="{$t("Durabilidade:")} $durabm[3]" /></td><td width="100%">{$t("Bandana:")} {$nomeequip["shield"]}</td></tr>
</table>

<center>
<div style="width:150px;margin:3px 0 2px;border-top:1px solid #8b5a2b;padding-top:2px;font-size:11px;font-weight:bold;color:#663300;letter-spacing:1px">{$t("ACESSÓRIOS")}</div>
<!-- Os 4 quadros de acessório (fundo4_1..4.png, 34x34) ficam em x = 3, 43, 83, 123 (6px de vão, como a mochila) e y = 4,
     com a mesma moldura clara suave dos quadros da mochila (box-shadow). -->
<div id="acessorios4" style="position:relative;width:161px;height:41px;margin:2px auto 3px;text-align:left">
<div data-slot="1" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:3px;top:4px;width:34px;height:34px;background:url({$icone["slot1"]}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_1.png) 0 0 / 100% 100% no-repeat"><a href="desequipar.php?qual=4" style="display:block"><img src="images/gif30.gif" width="34" height="34" onMouseOver="$slot1atr" onmouseout="fecharexplic();" id="slot1atr" border="0" style="display:block"></a></div>
<div data-slot="2" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:43px;top:4px;width:34px;height:34px;background:url({$icone["slot2"]}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_2.png) 0 0 / 100% 100% no-repeat"><a href="desequipar.php?qual=5" style="display:block"><img src="images/gif30.gif" width="34" height="34" onMouseOver="$slot2atr" onmouseout="fecharexplic();" id="slot2atr" border="0" style="display:block"></a></div>
<div data-slot="3" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:83px;top:4px;width:34px;height:34px;background:url({$icone["slot3"]}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_3.png) 0 0 / 100% 100% no-repeat"><a href="desequipar.php?qual=6" style="display:block"><img src="images/gif30.gif" width="34" height="34" onMouseOver="$slot3atr" onmouseout="fecharexplic();" id="slot3atr" border="0" style="display:block"></a></div>
<div data-slot="4" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:123px;top:4px;width:34px;height:34px;background:url({$icone["slot4"]}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_4.png) 0 0 / 100% 100% no-repeat"><a href="desequipar.php?qual=7" style="display:block"><img src="images/gif30.gif" width="34" height="34" onMouseOver="$slot4atr" onmouseout="fecharexplic();" id="slot4atr" border="0" style="display:block"></a></div>
</div>
</center>

<table width="178">
<tr><td><img src="images/orb.gif" alt="" title="{$t("Durabilidade:")} $durabm[4]" /></td><td width="100%">{$t("Acessório")} 1: {$nomeequip["slot1"]}</td></tr>
<tr><td><img src="images/orb.gif" alt="" title="{$t("Durabilidade:")} $durabm[5]" /></td><td width="100%">{$t("Acessório")} 2: {$nomeequip["slot2"]}</td></tr>
<tr><td><img src="images/orb.gif" alt="" title="{$t("Durabilidade:")} $durabm[6]" /></td><td width="100%">{$t("Acessório")} 3: {$nomeequip["slot3"]}</td></tr>
<tr><td><img src="images/orb.gif" alt="" title="{$t("Durabilidade:")} $durabm[7]" /></td><td width="100%">{$t("Acessório")} 4: {$nomeequip["slot4"]}</td></tr>
</table>
$avisodurab






</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div>







<div style="position:relative; width:204px">
<div style="background-image: url(layoutnovo/buttons/mochila.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-25px;z-index:2">




<center><a href="javascript: menudrop('bp', '{$t("Mochila")}', '{$t("Passe o mouse sobre um item para ver os detalhes; clique para usar ou equipar.")}', '1', '1')" ><img src="images/backpack1.png" width="105" height="105" border="0" id="bp" alt=""></a><br>
<!-- Os 8 quadros escuros de images/bpslots.jpg (177x93, duas fileiras de 4) ficam em x = 9, 49, 89, 129 e
     y = 10 (espaços 1-4) e y = 50 (espaços 5-8), 34x34 cada (medidos no próprio jpg). Cada ícone, cheio ou vazio,
     fica num div absoluto exatamente sobre o seu quadro. -->
<div style="position:relative;width:177px;height:93px;background:url(images/bpslots.jpg) no-repeat left top;text-align:left">
$bpquadros
</div>
</center>
$avisodrop


</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div>






<div style="position:relative; width:204px">
<div style="background-image: url(layoutnovo/buttons/jutsus.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-25px;z-index:2">


<center><img src="layoutnovo/menuslados/jutsu.png"></center>
$jutsudebuscahtml
{{magiclist}}
</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div>
FIMDOTEMPLATE;
?>