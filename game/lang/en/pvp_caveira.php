<?php // lang/en/pvp_caveira.php :: traduções em inglês da caveira exposta e das vilas fechadas (lib.php, pvp.php, index.php,
// explore.php, towns.php, backpack.php, admin.php) e da seção de PvP da ajuda (help.php). Ver docs/PVP.md.
return array(
    "Você está com caveira e matou um jogador há pouco: os portões das vilas estão fechados para você por mais %s minuto(s)." => "You have a skull and killed a player a moment ago: the village gates are closed to you for %s more minute(s).",
    "%s estava offline, mas a caveira o deixou exposto no mapa." => "%s was offline, but the skull left them exposed on the map.",
    "Quem mata com caveira continua atacável por %s minuto(s), mesmo offline, e não entra em vilas nesse tempo." => "Whoever kills with a skull stays attackable for %s minute(s), even offline, and cannot enter villages during that time.",
    "Exceção: quem matou há pouco com caveira pode ser atacado mesmo offline, na última casa onde estava." => "Exception: someone who killed a moment ago with a skull can be attacked even offline, on the last tile they were on.",
    "<br />Você estava offline, mas a sua caveira deixou você exposto no mapa." => "<br />You were offline, but your skull left you exposed on the map.",
    "Você foi atacado enquanto estava offline: a sua caveira deixou você exposto no mapa." => "You were attacked while offline: your skull left you exposed on the map.",
    "(offline, caveira exposta)" => "(offline, exposed skull)",
    "Caveira exposta" => "Exposed skull",
    "Caveira exposta:" => "Exposed skull:",
    "depois de cada morte injusta, quem tem caveira continua atacável por" => "after each unjustified kill, a skulled player stays attackable for",
    "minutos, mesmo offline (fica na última casa do mapa), e não pode entrar em vilas nesse tempo. 0 = desligado." => "minutes, even offline (they stay on their last map tile), and cannot enter villages during that time. 0 = off.",
    "Ligado (padrão)" => "On (default)",
    "Desligado" => "Off",
    "Mochila e itens equipados (padrão)" => "Backpack and equipped items (default)",
    "PvP no Mundo e Caveiras" => "Open-world PvP and Skulls",

'<h3><a name="pvp"></a>PvP no Mundo e Caveiras</h3>
<b>PvP no mundo</b> (neste servidor: <b>%s</b>): fora das vilas você pode atacar outro ninja na mesma casa do mapa ou numa casa vizinha, pela lista "PvP: ninjas por perto". A luta é resolvida na hora, com as regras do duelo.
<ul>
<li /><b>Proteções</b>: ninguém abaixo do level %s ataca nem é atacado; a diferença de level não pode passar de %s (0 = sem limite); espere %s minuto(s) entre um ataque e outro e %s minuto(s) para lutar de novo com o mesmo jogador. <b>Dentro das vilas ninguém pode ser atacado.</b>
<li /><b>Quem perde</b> volta para a vila natal com 1/4 do HP, perde %s%% do Ryou que carrega e, para até %s item(ns), tem %s%% de chance de perder um item (da mochila ou equipado) para o vencedor. Variantes raras ficam protegidas.
<li /><img src="images/caveira_branca.gif" alt="" /> <b>Caveira branca</b>: quem mata sem ser em legítima defesa fica marcado por %s hora(s); se morrer nesse tempo, a chance de perder item aumenta %s%%.
<li /><img src="images/caveira_vermelha.gif" alt="" /> <b>Caveira vermelha</b>: %s mortes dentro do tempo da caveira branca dão a caveira vermelha por %s dia(s); quem morre com ela perde TODOS os itens. Matar de novo com caveira vermelha dá banimento automático de %s dia(s) e a perda de metade dos itens.
<li /><b>Caveira exposta</b>: depois de cada morte injusta, quem tem caveira continua atacável por <b>%s minuto(s)</b>, <b>mesmo se sair do jogo</b>: o personagem fica na última casa do mapa (aparece como "offline, caveira exposta") e a luta é resolvida com os atributos dele. Quando voltar, ele vê o que aconteceu na janela de aviso e no histórico.
<li /><b>Vilas fechadas</b>: nesse mesmo tempo quem tem a caveira exposta não entra em nenhuma vila: nem andando, nem pelo Viajar Para, nem com os pergaminhos de Retorno e de Teleporte. O jogo mostra quantos minutos faltam. Se for derrotado, ele volta para a vila natal normalmente (a morte sempre leva para casa).
</ul>'
=> '<h3><a name="pvp"></a>Open-world PvP and Skulls</h3>
<b>Open-world PvP</b> (on this server: <b>%s</b>): outside the villages you can attack another ninja on the same map tile or on a neighbouring tile, from the "PvP: ninjas nearby" list. The fight is resolved instantly, with the duel rules.
<ul>
<li /><b>Protections</b>: nobody below level %s attacks or is attacked; the level difference cannot exceed %s (0 = no limit); wait %s minute(s) between attacks and %s minute(s) to fight the same player again. <b>Nobody can be attacked inside the villages.</b>
<li /><b>The loser</b> goes back to their home village with 1/4 HP, loses %s%% of the Ryou they carry and, for up to %s item(s), has a %s%% chance to lose an item (from the backpack or equipped) to the winner. Rare variants are protected.
<li /><img src="images/caveira_branca.gif" alt="" /> <b>White skull</b>: whoever kills, except in self-defence, is marked for %s hour(s); dying during that time raises the item-loss chance by %s%%.
<li /><img src="images/caveira_vermelha.gif" alt="" /> <b>Red skull</b>: %s kills within the white skull time give a red skull for %s day(s); dying with it loses ALL items. Killing again with a red skull means an automatic ban of %s day(s) and the loss of half of the items.
<li /><b>Exposed skull</b>: after each unjustified kill, a skulled player stays attackable for <b>%s minute(s)</b>, <b>even if they leave the game</b>: the character stays on their last map tile (shown as "offline, exposed skull") and the fight is resolved with their stats. When they come back, they see what happened in the notice window and in their history.
<li /><b>Closed villages</b>: during that same time a player with an exposed skull cannot enter any village: not by walking, not with Travel To, not with the Return or Teleport scrolls. The game shows how many minutes are left. If defeated, they go back to their home village as usual (death always takes you home).
</ul>',
);
