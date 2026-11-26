<?php
// showchar.php :: Ficha completa do próprio jogador (popup "Todos os Status"). Preenchido pela função showchar() do index.php,
// que prepara os campos ({{adm}}, {{nextlevel}}, {{durabm1}}...{{durabm6}}, {{magiclist}} etc.).
$t = "t"; // Para usar {$t("texto")} dentro do heredoc.
$template = <<<FIMDOTEMPLATE
<table width="215">

<tr><td>

<table border="0" cellpadding=0 cellspacing=0 background="layoutnovo/menuslados/meio.png">
<tr><td colspan="3" width="224" height="34" background="layoutnovo/buttons/personagem.png"></td></tr><tr background="layoutnovo/menuslados/meio.png">
<td width="5"></td>
<td>
<center><img src="layoutnovo/avatares/{{avatar}}.jpg"></center><br>
<b>{{charname}}</b><br />{{adm}}

{$t("Dificuldade")}: {{difficulty}}<br />
{$t("Especialização")}: {{charclass}}<br />
{$t("Graduação")}: {{graduacao}}<br />
{$t("Vila natal")}: {{vilanatal}}<br>
{$t("Elementos")}: {{elementos}}<br><br>

Level: {{level}}<br />
{$t("Experiência")}: {{experience}} {{plusexp}}<br />
{$t("Próximo Level")}: {{nextlevel}}<br />
Ryou: {{gold}} {{plusgold}}<br />
{$t("Pontos de Vida")}: {{currenthp}} / {{maxhp}}<br />
Chakra: {{currentmp}} / {{maxmp}}<br />
{$t("Pontos de Viagem")}: {{currenttp}} / {{maxtp}}<br />
{$t("Pontos Naturais")}: {{currentnp}} / {{maxnp}}<br />
{$t("Pontos Elementais")}: {{currentep}} / {{maxep}}<br />
{$t("Chance de Drop")}: <font color="gray">+{{droprate}}%</font><br />
{$t("Duelos")}: {{duelos_vencidos}} {$t("vitórias")} / {{duelos_perdidos}} {$t("derrotas")}<br />
{$t("Mapa descoberto")}: <a href="javascript:openmappopup();" title="{$t("Abrir o mapa-múndi")}">{{mapadescobertopct}}%</a><br /><br />

{$t("Força")}: {{strength}}<br />
{$t("Destreza")}: {{dexterity}}<br />
{$t("Poder de Ataque")}: {{attackpower}}<br />
{$t("Poder de Defesa")}: {{defensepower}}<br /><br>

{$t("Agilidade")}: {{agilidade}}<br>
{$t("Sorte")}: {{sorte}}<br>
{$t("Determinação")}: {{determinacao}}<br>
{$t("Precisão")}: {{precisao}}<br>
{$t("Inteligência")}: {{inteligencia}}<br>

</td>
<td width="5"></td></td>
</tr><tr><td colspan="3" width="224" height="21" background="layoutnovo/menuslados/baixo.png"></td></tr></table>

</td></tr>
</table><br />

<table width="215">

<tr><td>

<table border="0" cellpadding=0 cellspacing=0 background="layoutnovo/menuslados/meio.png">
<tr><td colspan="3" width="224" height="34" background="layoutnovo/buttons/inventario.png"></td></tr><tr background="layoutnovo/menuslados/meio.png">
<td width="5"></td>
<td>


<center>
<div style="position:relative;background-image:url(layoutnovo/equipamentos/equipamentos.png);width:168px;height:116px;background-repeat:no-repeat">
<div style="position:absolute;top:14px;left:66px;width:34px;height:34px;background:url({{shieldicone}}) no-repeat left top"></div>
<div style="position:absolute;top:52px;left:29px;width:34px;height:34px;background:url({{weaponicone}}) no-repeat left top"></div>
<div style="position:absolute;top:52px;left:66px;width:34px;height:34px;background:url({{armoricone}}) no-repeat left top"></div>
<div style="position:absolute;top:52px;left:103px;width:34px;height:34px;background:url({{segundaarmafundo}}) no-repeat left top"></div>
</div>

</center>

<table width="100%">
<tr><td><img src="images/icon_weapon.gif" alt="{$t("Arma")}" title="{$t("Durabilidade")}: {{durabm1}}" /></td><td width="100%">{$t("Arma")}: {{weaponname}}</td></tr>
<tr><td><img src="images/icon_armor.gif" alt="{$t("Colete")}" title="{$t("Durabilidade")}: {{durabm2}}" /></td><td width="100%">{$t("Colete")}: {{armorname}}</td></tr>
<tr><td><img src="images/icon_shield.gif" alt="{$t("Bandana")}" title="{$t("Durabilidade")}: {{durabm3}}" /></td><td width="100%">{$t("Bandana")}: {{shieldname}}</td></tr>
</table>

<center>
<div style="width:150px;margin:3px 0 2px;border-top:1px solid #8b5a2b;padding-top:2px;font-size:11px;font-weight:bold;color:#663300;letter-spacing:1px">{$t("ACESSÓRIOS")}</div>
<!-- Acessórios (fundo4_1..4.png): x = 3, 43, 83, 123 e y = 4, com a moldura clara dos quadros da mochila. -->
<div id="acessorios4" style="position:relative;width:161px;height:41px;margin:2px auto 3px;text-align:left">
<div data-slot="1" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:3px;top:4px;width:34px;height:34px;background:url({{slot1icone}}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_1.png) 0 0 / 100% 100% no-repeat"></div>
<div data-slot="2" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:43px;top:4px;width:34px;height:34px;background:url({{slot2icone}}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_2.png) 0 0 / 100% 100% no-repeat"></div>
<div data-slot="3" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:83px;top:4px;width:34px;height:34px;background:url({{slot3icone}}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_3.png) 0 0 / 100% 100% no-repeat"></div>
<div data-slot="4" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:123px;top:4px;width:34px;height:34px;background:url({{slot4icone}}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_4.png) 0 0 / 100% 100% no-repeat"></div>
</div>
</center>

<table width="100%">
<tr><td><img src="images/orb.gif" alt="{$t("Item Adicional")}" title="{$t("Durabilidade")}: {{durabm4}}" /></td><td width="100%">{$t("Acessório")} 1: {{slot1name}}</td></tr>
<tr><td><img src="images/orb.gif" alt="{$t("Item Adicional")}" title="{$t("Durabilidade")}: {{durabm5}}" /></td><td width="100%">{$t("Acessório")} 2: {{slot2name}}</td></tr>
<tr><td><img src="images/orb.gif" alt="{$t("Item Adicional")}" title="{$t("Durabilidade")}: {{durabm6}}" /></td><td width="100%">{$t("Acessório")} 3: {{slot3name}}</td></tr>
<tr><td><img src="images/orb.gif" alt="{$t("Item Adicional")}" title="{$t("Durabilidade")}: {{durabm7}}" /></td><td width="100%">{$t("Acessório")} 4: {{slot4name}}</td></tr>
</table>

</td>
<td width="5"></td></td>
</tr><tr><td colspan="3" width="224" height="21" background="layoutnovo/menuslados/baixo.png"></td></tr></table>

</td></tr>
</table><br />

<table width="215">

<tr><td>

<table border="0" cellpadding=0 cellspacing=0 background="layoutnovo/menuslados/meio.png">
<tr><td colspan="3" width="224" height="34" background="layoutnovo/buttons/jutsus.png"></td></tr><tr background="layoutnovo/menuslados/meio.png">
<td width="5"></td>
<td>

{{magiclist}}

</td>
<td width="5"></td></td>
</tr><tr><td colspan="3" width="224" height="21" background="layoutnovo/menuslados/baixo.png"></td></tr></table>

</td></tr>
</table><br />
FIMDOTEMPLATE;
?>