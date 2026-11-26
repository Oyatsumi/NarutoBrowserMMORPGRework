<?php // lang/en/dificuldade.php :: traduções em inglês do cadastro sem vila, da troca de dificuldade e do histórico maior (users.php, funcoesmissoesextras.php, admin.php, admin_missoes.php, help.php; docs/TROCA_DE_DIFICULDADE.md).
return array(
	'Mestre das Provações' => 'Master of Trials',
	'Provação: Caminho Suave' => 'Trial: Gentle Path',
	'Provação: Caminho do Equilíbrio' => 'Trial: Balanced Path',
	'Provação: Caminho Árduo' => 'Trial: Hard Path',
	'Essa provação não está disponível no momento.' => 'This trial is not available right now.',
	'Essa já é a sua dificuldade.' => 'This is already your difficulty.',
	'Você trocou de dificuldade há pouco tempo. Poderá trocar de novo em %s.' => 'You changed your difficulty recently. You can change again in %s.',
	'Você já está fazendo outra provação de dificuldade (%s). Termine-a ou espere ela falhar antes.' => 'You are already doing another difficulty trial (%s). Finish it or let it fail first.',
	'Sua dificuldade atual: <b>%s</b>.' => 'Your current difficulty: <b>%s</b>.',
	'Depois de uma troca de dificuldade, é preciso esperar %s dia(s) para trocar de novo.' => 'After a difficulty change, you must wait %s day(s) before changing again.',
	'Muda a sua dificuldade para %s; depois, é preciso esperar %s dia(s) para trocar de novo.' => 'Changes your difficulty to %s; afterwards, you must wait %s day(s) before changing again.',
	'Você trocou de dificuldade: %s → %s.' => 'You changed your difficulty: %s → %s.',
	'A partir de agora a sua dificuldade é <b>%s</b>: vale já na próxima luta (HP e dano dos inimigos, experiência e Ryou).' => 'From now on your difficulty is <b>%s</b>: it applies from the next fight on (enemies’ HP and damage, experience and Ryou).',
	'Sua dificuldade acabou de mudar. Atualize a página.' => 'Your difficulty has just changed. Refresh the page.',
	'A dificuldade de destino precisa ser 1, 2 ou 3.' => 'The destination difficulty must be 1, 2 or 3.',
	'Uma missão não pode trocar a vila natal e a dificuldade ao mesmo tempo.' => 'A mission cannot change the home village and the difficulty at the same time.',
	'Troca de dificuldade' => 'Difficulty change',
	'Na entrega, a dificuldade do jogador passa a ser esta (com o tempo de espera de Configurações gerais).' => 'On delivery, the player\'s difficulty becomes this one (with the waiting time from General settings).',
	'Dias de espera entre trocas de dificuldade' => 'Days to wait between difficulty changes',
	'Mensagens no histórico do jogador' => 'Messages in the player history',
	'A vila inicial precisa ser uma vila natal com alinhamento e não secreta.' => 'The starting village must be a home village with an alignment and not secret.',
	'Vila inicial:' => 'Starting village:',
	'Vila natal dada a todo ninja novo (o cadastro não pergunta a vila). Padrão: a Vila da Folha, no centro do mapa, com a área de iniciantes. Depois o jogador só troca pelas missões de transferência dos embaixadores.' => 'Home village given to every new ninja (registration does not ask for a village). Default: the Leaf Village, at the center of the map, with the beginner area. Afterwards the player can only change it through the ambassadors\' transfer missions.',
	'Troca de dificuldade:' => 'Difficulty change:',
	'A troca é feita pelas provações do Mestre das Provações (campo Troca de dificuldade das Missões extras); nível, inimigos e taxa em Ryou ficam nas próprias missões. 0 = sem espera.' => 'The change is made through the Master of Trials\' trials (Difficulty change field of the extra Missions); level, enemies and Ryou fee are set in the missions themselves. 0 = no waiting.',
	'Histórico do jogador:' => 'Player history:',
	'mensagens' => 'messages',
	'Quantas mensagens recentes ficam guardadas e aparecem no quadro Histórico (5 a 30, padrão 12).' => 'How many recent messages are kept and shown in the History box (5 to 30, default 12).',
	'Todo ninja começa na <b>%s</b>, a vila natal para onde você volta quando é derrotado. Dentro do jogo, mais tarde, é possível trocar de vila pelas missões de transferência dos embaixadores.' => 'Every ninja starts in the <b>%s</b>, the home village you return to when defeated. Later, in the game, you can change villages through the ambassadors\' transfer missions.',
	'Pode ser trocada mais tarde, no jogo, pelas provações do Mestre das Provações (perto da vila inicial).' => 'Can be changed later, in the game, through the Master of Trials\' trials (near the starting village).',
	'Trocar de Dificuldade' => 'Changing your Difficulty',
	'<h3><a name="trocadif"></a>Trocar de Dificuldade</h3>
A dificuldade escolhida no cadastro pode ser trocada no meio do jogo. Perto da Vila da Folha vive o <b>Mestre das Provações</b> (um NPC no mapa). Converse com ele e aceite a <b>Provação</b> da dificuldade que você quer:
<ul>
<li /><b>Requisitos</b>: level %1$s ou mais. A provação da sua dificuldade atual não aparece, e só é possível fazer uma provação por vez.
<li /><b>Tarefas</b>: vencer algumas lutas seguidas, sem descansar, se curar ou morrer, contra inimigos do patamar da nova dificuldade (quanto mais difícil, mais lutas e inimigos de nível mais alto), e voltar ao Mestre para entregar a <b>oferenda</b> em Ryou, cobrada na entrega (%2$s).
<li /><b>Na entrega</b> a dificuldade muda na hora e vale a partir da próxima luta: HP e dano dos inimigos, experiência e Ryou ganhos (veja <a href="#personagem">Níveis de Dificuldade</a>). Nada do que você já ganhou é refeito. A troca fica registrada no seu histórico.
<li /><b>Espera</b>: depois de trocar, você precisa esperar <b>%3$s dia(s)</b> para trocar de novo.
</ul>
A sua dificuldade atual aparece na sua ficha (Todos os Status) e no quadro do Mestre, junto com o tempo que falta para a próxima troca.<br /><br />' => '<h3><a name="trocadif"></a>Changing your Difficulty</h3>
The difficulty chosen at registration can be changed mid-game. Near the Leaf Village lives the <b>Master of Trials</b> (an NPC on the map). Talk to him and accept the <b>Trial</b> for the difficulty you want:
<ul>
<li /><b>Requirements</b>: level %1$s or higher. The trial for your current difficulty is not shown, and only one trial can be done at a time.
<li /><b>Tasks</b>: win a few fights in a row, without resting, healing or dying, against enemies of the new difficulty’s tier (the harder it is, the more fights and the higher the enemies’ level), and go back to the Master to hand over the <b>offering</b> in Ryou, charged on delivery (%2$s).
<li /><b>On delivery</b> your difficulty changes immediately and applies from the next fight on: enemies’ HP and damage, experience and Ryou earned (see <a href="#personagem">Difficulty Levels</a>). Nothing you have already earned is recalculated. The change is recorded in your history.
<li /><b>Waiting time</b>: after changing, you must wait <b>%3$s day(s)</b> before changing again.
</ul>
Your current difficulty is shown on your character sheet (All Stats) and in the Master’s box, along with the time left until the next change.<br /><br />',
);
