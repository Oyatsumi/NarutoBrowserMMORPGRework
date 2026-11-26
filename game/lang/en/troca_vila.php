<?php // lang/en/troca_vila.php :: traduções em inglês da troca de vila natal (funcoesmissoesextras.php, admin.php, admin_missoes.php, help.php; docs/TROCA_DE_VILA.md).
return array(

	// ---------------------------------------------------------------- embaixadores (tabela npcs 32-36)
	"Embaixador Tobirō" => "Ambassador Tobirō",
	"Embaixadora Iwame" => "Ambassador Iwame",
	"Embaixador Kirisame" => "Ambassador Kirisame",
	"Embaixadora Sunao" => "Ambassador Sunao",
	"Embaixador Raidō" => "Ambassador Raidō",

	// ---------------------------------------------------------------- jogo (funcoesmissoesextras.php, ficha)
	"Essa vila não está aceitando ninjas de outras vilas no momento." => "This village is not accepting ninja from other villages right now.",
	"Essa já é a sua vila natal." => "This is already your home village.",
	"Você trocou de vila natal há pouco tempo. Poderá trocar de novo em %s." => "You changed your home village recently. You can change again in %s.",
	"%s dia(s) e %s hora(s)" => "%s day(s) and %s hour(s)",
	"%s hora(s) e %s minuto(s)" => "%s hour(s) and %s minute(s)",
	"Você já está fazendo outra transferência de vila (%s). Termine-a ou espere ela falhar antes." => "You are already doing another village transfer (%s). Finish it or let it fail first.",
	"Sua vila natal atual: <b>%s</b>." => "Your current home village: <b>%s</b>.",
	"Próxima troca possível em %s." => "Next change possible in %s.",
	"Depois de uma troca, é preciso esperar %s dia(s) para trocar de novo." => "After a change, you must wait %s day(s) before changing again.",
	"Você trocou de vila natal: %s → %s." => "You changed your home village: %s → %s.",
	"A partir de agora a sua vila natal é a <b>%s</b>: é para lá que você volta ao ser derrotado e com o Pergaminho de Retorno." => "From now on your home village is the <b>%s</b>: that is where you return when defeated and with the Return Scroll.",
	"Sua vila natal acabou de mudar. Atualize a página." => "Your home village has just changed. Refresh the page.",
	"desde %s" => "since %s",
	"Parabéns! Você completou a missão <b>%s</b>." => "Congratulations! You completed the mission <b>%s</b>.",
	"Muda a sua vila natal; depois, é preciso esperar %s dia(s) para trocar de novo." => "Changes your home village; afterwards, you must wait %s day(s) before changing again.",

	// ---------------------------------------------------------------- painel do administrador
	"A vila de destino da troca precisa ser uma vila natal com alinhamento e não secreta." => "The transfer destination must be a home village with an alignment and not secret.",
	"Nenhuma (missão comum)" => "None (regular mission)",
	"Troca de vila natal" => "Home village change",
	"Na entrega, a vila natal do jogador passa a ser esta (com o tempo de espera de Configurações gerais)." => "On delivery, the player's home village becomes this one (with the waiting time from General settings).",
	"Dias de espera entre trocas de vila natal" => "Days to wait between home village changes",
	"Troca de vila natal:" => "Home village change:",
	"Esperar" => "Wait",
	"dia(s) entre duas trocas" => "day(s) between two changes",
	"A troca é feita pelas missões de transferência dos embaixadores (campo Troca de vila natal das Missões extras); nível, graduação e taxa em Ryou ficam nas próprias missões. 0 = sem espera." => "The change is made through the ambassadors' transfer missions (Home village change field of the extra Missions); level, graduation and Ryou fee are set in the missions themselves. 0 = no waiting.",

	// ---------------------------------------------------------------- ajuda (help.php)
	"Trocar de Vila Natal" => "Changing your Home Village",
	<<<'PT'
<h3><a name="trocavila"></a>Trocar de Vila Natal</h3>
Todo ninja começa na vila inicial do jogo (o cadastro não pergunta a vila), mas a vila natal pode ser trocada no meio do jogo — só que não é fácil. Perto de cada vila natal vive um <b>embaixador</b> (um NPC no mapa, ao lado da vila).
Converse com o embaixador da vila para onde você quer ir e aceite a missão de <b>Transferência</b>:
<ul>
<li /><b>Requisitos</b>: level %1$s ou mais e graduação Chuunin. A transferência para a sua vila natal atual não aparece, e só é possível fazer uma transferência por vez.
<li /><b>Tarefas</b>: derrotar inimigos dos arredores da nova vila, visitar o marco da fronteira e voltar ao embaixador para pagar a <b>taxa de cidadania</b> (%2$s Ryou, cobrada na entrega).
<li /><b>Na entrega</b> a vila natal muda na hora: é para ela que você volta ao ser derrotado (lutas e PvP) e com o Pergaminho de Retorno, o emblema ao lado do seu nome muda e o Teleporte Aleatório passa a evitar a nova vila. A troca fica registrada no seu histórico.
<li /><b>Espera</b>: depois de trocar, você precisa esperar <b>%3$s dia(s)</b> para trocar de novo. Vilas secretas e lugares sem vila natal não aceitam transferências.
</ul>
A sua vila natal atual aparece na sua ficha (Todos os Status) e no quadro do embaixador, junto com o tempo que falta para a próxima troca.<br /><br />
PT
	=> <<<'EN'
<h3><a name="trocavila"></a>Changing your Home Village</h3>
Every ninja starts in the game’s starting village (registration does not ask for a village), but your home village can be changed mid-game — it just is not easy. Near each home village lives an <b>ambassador</b> (an NPC on the map, next to the village).
Talk to the ambassador of the village you want to join and accept the <b>Transfer</b> mission:
<ul>
<li /><b>Requirements</b>: level %1$s or higher and the Chuunin graduation. The transfer to your current home village is not shown, and only one transfer can be done at a time.
<li /><b>Tasks</b>: defeat enemies around the new village, visit the border marker and go back to the ambassador to pay the <b>citizenship fee</b> (%2$s Ryou, charged on delivery).
<li /><b>On delivery</b> your home village changes immediately: it is where you return when defeated (fights and PvP) and with the Return Scroll, the emblem next to your name changes and the Random Teleport Scroll starts avoiding the new village. The change is recorded in your history.
<li /><b>Waiting time</b>: after changing, you must wait <b>%3$s day(s)</b> before changing again. Secret villages and places without a home village do not accept transfers.
</ul>
Your current home village is shown on your character sheet (All Stats) and in the ambassador's box, along with the time left until the next change.<br /><br />
EN
,
);
