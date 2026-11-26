<?php
// onlinechar.php :: Ficha pública de um jogador (popup), usada por index.php?do=onlinechar e pelo mostrarchar.php (campos montados por fichapublica(), funcoesficha.php).
// Os campos {{armaatr}}, {{slot1atr}}... são os tooltips dos equipamentos (função conteudoexplic do funcoesinclusas.php).
$t = "t"; // Para usar {$t("texto")} dentro do heredoc.
$template = <<<FIMDOTEMPLATE
<center>
<div style="position:relative; width:204px;text-align:left;font-family:tahoma, 'DejaVu Sans Condensed', arial, sans-serif;font-size:12px">
<div style="background-image: url(layoutnovo/buttons/personagem.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-25px;z-index:2">

<center><img src="layoutnovo/avatares/{{avatar}}.jpg" alt="" style="max-width:123px"></center>
<div style="text-align:center">{{clatag}}<b style="font-size:13px">{{charname}}</b><br />{{adm}}{{titulos}}</div>
<style>.secaoficha{display:flex;align-items:center;gap:6px;margin:9px 0 3px;font-weight:bold;color:#663300;font-size:10px;letter-spacing:1px;white-space:nowrap}.secaoficha:before,.secaoficha:after{content:"";flex:1;height:2px;background:linear-gradient(90deg,transparent,#a0703c 25%,#a0703c 75%,transparent)}</style>
<table width="100%" cellspacing="0" cellpadding="1" style="font-size:11px">
<tr><td><img src="images/24/especializacao.gif" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Especialização")}:</td><td style="text-align:right">{{charclass}}</td></tr>
<tr><td><img src="images/24/graduacao.gif" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Graduação")}:</td><td style="text-align:right">{{graduacao}}</td></tr>
<tr><td><img src="images/vila_marcador.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Vila natal")}:</td><td style="text-align:right">{{vilanatal}}</td></tr>
<tr><td><img src="images/24/treino_elemento.gif" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Elementos")}:</td><td style="text-align:right">{{natureza}}</td></tr>
<tr><td colspan="2"><img src="images/24/treino_olho_sharingan.gif" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Doujutsu")}:<div style="text-align:center;padding:2px 0 1px">{{doujutsu}}</div></td></tr>
<tr><td><img src="images/relogio.gif" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Ninja desde")}:</td><td style="text-align:right">{{desde}}</td></tr>
<tr><td colspan="2"><div class="secaoficha">{$t("NÍVEL E VITAIS")}</div></td></tr>
<tr><td><img src="images/painel_pontos.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />Level:</td><td style="text-align:right">{{level}}</td></tr>
<tr><td><img src="images/ficha_experiencia.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Experiência")}:</td><td style="text-align:right">{{experience}} {{plusexp}}</td></tr>
<tr><td><img src="images/ficha_proxnivel.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Próximo Level")}:</td><td style="text-align:right">{{nextlevel}}</td></tr>
<tr><td><img src="images/ficha_hp.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />HP:</td><td style="text-align:right">{{currenthp}} / {{maxhp}}</td></tr>
<tr><td><img src="images/ficha_ch.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />Chakra:</td><td style="text-align:right">{{currentmp}} / {{maxmp}}</td></tr>
<tr><td><img src="images/ficha_tp.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />TP:</td><td style="text-align:right">{{currenttp}} / {{maxtp}}</td></tr>
<tr><td><img src="images/ficha_np.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />NP:</td><td style="text-align:right">{{currentnp}} / {{maxnp}}</td></tr>
<tr><td><img src="images/ficha_ep.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />EP:</td><td style="text-align:right">{{currentep}} / {{maxep}}</td></tr>
<tr><td colspan="2"><div class="secaoficha">{$t("ATRIBUTOS")}</div></td></tr>
<tr><td><img src="images/jutsu_taijutsu.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Força")}:</td><td style="text-align:right">{{strength}}</td></tr>
<tr><td><img src="images/jutsu_correr.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Destreza")}:</td><td style="text-align:right">{{dexterity}}</td></tr>
<tr><td><img src="images/ficha_ataque.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Poder de Ataque")}:</td><td style="text-align:right">{{attackpower}}</td></tr>
<tr><td><img src="images/ficha_defesa.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Poder de Defesa")}:</td><td style="text-align:right">{{defensepower}}</td></tr>
<tr><td><img src="images/ficha_agilidade.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Agilidade")}:</td><td style="text-align:right">{{agilidade}}</td></tr>
<tr><td><img src="images/ficha_sorte.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Sorte")}:</td><td style="text-align:right">{{sorte}}</td></tr>
<tr><td><img src="images/status/escudo.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Determinação")}:</td><td style="text-align:right">{{determinacao}}</td></tr>
<tr><td><img src="images/ficha_precisao.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Precisão")}:</td><td style="text-align:right">{{precisao}}</td></tr>
<tr><td><img src="images/ficha_inteligencia.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Inteligência")}:</td><td style="text-align:right">{{inteligencia}}</td></tr>
<tr><td colspan="2"><div class="secaoficha">{$t("BÔNUS")}</div></td></tr>
<tr><td><img src="images/jutsu_dano.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Chance de Crítico")}:</td><td style="text-align:right">{{critico}}%</td></tr>
<tr><td><img src="images/jutsu_defesa.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Esquiva")}:</td><td style="text-align:right">{{esquiva}}%</td></tr>
<tr><td><img src="images/ficha_regenhp.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Regeneração de HP")}:</td><td style="text-align:right">{{regenhp}}/{$t("passo")}</td></tr>
<tr><td><img src="images/ficha_regen.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Regeneração de CH / TP / NP / EP")}:</td><td style="text-align:right">{{regenmp}} / {{regentp}} / {{regennp}} / {{regenep}}</td></tr>
<tr><td><img src="images/24/treino_chakra.gif" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Economia de Chakra")}:</td><td style="text-align:right">{{economiachakra}}%</td></tr>
<tr><td><img src="images/ficha_drop.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Chance de Drop")}:</td><td style="text-align:right">+{{droprate}}%</td></tr>
<tr><td colspan="2"><div class="secaoficha">{$t("HISTÓRICO")}</div></td></tr>
<tr><td><img src="images/acao_duelo.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Duelos")}:</td><td style="text-align:right">{{duelos_vencidos}} {$t("V")} / {{duelos_perdidos}} {$t("D")}</td></tr>
<tr><td><img src="images/painel_missoes.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Missões concluídas")}:</td><td style="text-align:right">{{missoes}}</td></tr>
<tr><td><img src="images/24/alquimia.gif" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Nível de Alquimia")}:</td><td style="text-align:right">{{alquimia}}</td></tr>
<tr><td><img src="images/24/mapa.gif" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Mapa descoberto")}:</td><td style="text-align:right">{{mapadescobertopct}}</td></tr>
<tr><td><img src="images/acao_pvp.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />PvP:</td><td style="text-align:right">{{pvpstatus}}</td></tr>
<tr><td colspan="2"><div class="secaoficha">{$t("TALENTOS E CONJUNTOS")}</div></td></tr>
<tr><td colspan="2"><img src="images/painel_talentos.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Talentos")}:<div style="padding:1px 0 3px 8px">{{talentos}}</div></td></tr>
<tr><td colspan="2"><img src="images/ficha_conjuntos.png" width="16" height="16" alt="" style="vertical-align:-3px;margin-right:3px;image-rendering:pixelated" />{$t("Conjuntos")}:<div style="padding:1px 0 0 8px">{{conjuntos}}</div></td></tr>
</table>

</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div></center>

<center>
<div style="position:relative; width:204px;text-align:left;font-family:tahoma, 'DejaVu Sans Condensed', arial, sans-serif;font-size:12px">
<div style="background-image: url(layoutnovo/buttons/inventario.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-25px;z-index:2">

<center>
<div style="position:relative;background-image:url(layoutnovo/equipamentos/equipamentos.png);width:168px;height:116px;background-repeat:no-repeat">
<div style="position:absolute;top:14px;left:66px"><img src="{{shieldicone}}" onMouseOver="{{shieldatr}}" onmouseout="fecharexplic();" id="shieldatr" border="0"></div>
<div style="position:absolute;top:52px;left:29px"><img src="{{weaponicone}}" border="0" onMouseOver="{{armaatr}}" onmouseout="fecharexplic();" id="armaatr"></div>
<div style="position:absolute;top:52px;left:103px">{{segundaarma}}</div>
<div style="position:absolute;top:52px;left:66px"><img src="{{armoricone}}" border="0" onMouseOver="{{armoratr}}" onmouseout="fecharexplic();" id="armoratr"></div>
</div>

</center>

<table width="100%" style="font-family:tahoma, 'DejaVu Sans Condensed', arial, sans-serif;font-size:12px">
<tr><td><img src="images/icon_weapon.gif" alt="{$t("Arma")}" title="{$t("Durabilidade")}: {{durabm1}}" /></td><td width="100%">{$t("Arma")}: {{weaponname}}</td></tr>
<tr><td><img src="images/icon_armor.gif" alt="{$t("Colete")}" title="{$t("Durabilidade")}: {{durabm2}}" /></td><td width="100%">{$t("Colete")}: {{armorname}}</td></tr>
<tr><td><img src="images/icon_shield.gif" alt="{$t("Bandana")}" title="{$t("Durabilidade")}: {{durabm3}}" /></td><td width="100%">{$t("Bandana")}: {{shieldname}}</td></tr>
</table>

<center>
<div style="width:150px;margin:3px 0 2px;border-top:1px solid #8b5a2b;padding-top:2px;font-size:11px;font-weight:bold;color:#663300;letter-spacing:1px">{$t("ACESSÓRIOS")}</div>
<!-- Acessórios (fundo4_1..4.png): x = 3, 43, 83, 123 e y = 4, com a moldura clara dos quadros da mochila. -->
<div id="acessorios4" style="position:relative;width:161px;height:41px;margin:2px auto 3px;text-align:left">
<div data-slot="1" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:3px;top:4px;width:34px;height:34px;background:url({{slot1icone}}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_1.png) 0 0 / 100% 100% no-repeat"><img src="images/gif30.gif" width="34" height="34" onMouseOver="{{slot1atr}}" onmouseout="fecharexplic();" id="slot1atr" border="0" style="display:block"></div>
<div data-slot="2" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:43px;top:4px;width:34px;height:34px;background:url({{slot2icone}}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_2.png) 0 0 / 100% 100% no-repeat"><img src="images/gif30.gif" width="34" height="34" onMouseOver="{{slot2atr}}" onmouseout="fecharexplic();" id="slot2atr" border="0" style="display:block"></div>
<div data-slot="3" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:83px;top:4px;width:34px;height:34px;background:url({{slot3icone}}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_3.png) 0 0 / 100% 100% no-repeat"><img src="images/gif30.gif" width="34" height="34" onMouseOver="{{slot3atr}}" onmouseout="fecharexplic();" id="slot3atr" border="0" style="display:block"></div>
<div data-slot="4" style="box-shadow:0 0 0 2px rgba(208,196,147,.9),0 0 3px 2px rgba(208,196,147,.45);border-radius:1px;position:absolute;left:123px;top:4px;width:34px;height:34px;background:url({{slot4icone}}) no-repeat left top, url(layoutnovo/equipamentos/drops/fundo4_4.png) 0 0 / 100% 100% no-repeat"><img src="images/gif30.gif" width="34" height="34" onMouseOver="{{slot4atr}}" onmouseout="fecharexplic();" id="slot4atr" border="0" style="display:block"></div>
</div>
</center>

<table width="100%" style="font-family:tahoma, 'DejaVu Sans Condensed', arial, sans-serif;font-size:12px">
<tr><td><img src="images/orb.gif" alt="{$t("Item Adicional")}" title="{$t("Durabilidade")}: {{durabm4}}" /></td><td width="100%">{$t("Acessório")} 1: {{slot1name}}</td></tr>
<tr><td><img src="images/orb.gif" alt="{$t("Item Adicional")}" title="{$t("Durabilidade")}: {{durabm5}}" /></td><td width="100%">{$t("Acessório")} 2: {{slot2name}}</td></tr>
<tr><td><img src="images/orb.gif" alt="{$t("Item Adicional")}" title="{$t("Durabilidade")}: {{durabm6}}" /></td><td width="100%">{$t("Acessório")} 3: {{slot3name}}</td></tr>
<tr><td><img src="images/orb.gif" alt="{$t("Item Adicional")}" title="{$t("Durabilidade")}: {{durabm7}}" /></td><td width="100%">{$t("Acessório")} 4: {{slot4name}}</td></tr>
</table>

</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div></center>

<center>
<div style="position:relative; width:204px;text-align:left;font-family:tahoma, 'DejaVu Sans Condensed', arial, sans-serif;font-size:12px">
<div style="background-image: url(layoutnovo/buttons/jutsus.png);height:97px;z-index:1"></div>
<div style="z-index:0;margin-top:-2px;background-image: url(layoutnovo/buttons/meio.png)">
<div style="position:relative;padding-left:7px;padding-right:7px;top:-32px;z-index:2"><center>
{{magiclist}}
</center>
</div>
</div>
<div style="position:relative;top:-32px;z-index:1;background-image: url(layoutnovo/buttons/fim.png);height:51px;"></div>
</div></center>
FIMDOTEMPLATE;
?>