<?php // lang/en/treinos.php :: traduções em inglês dos treinos longos (funcoestreinos.php, treinoslongos.php, admin.php?do=treinos, help.php).
return array(

	// ---------------------------------------------------------------- nomes dos treinos e dos mestres (tabela treinos)
	"Domínio de Elemento" => "Element Mastery",
	"Controle de Chakra" => "Chakra Control",
	"Corpo de Ferro" => "Iron Body",
	"Caminhada Ninja" => "Ninja Trek",
	"Meditação Natural" => "Nature Meditation",
	"Treino de Jutsu Raro" => "Rare Jutsu Training",
	"Sábia Kotone" => "Sage Kotone",
	"Mestre Hiroshi" => "Master Hiroshi",
	"Mestre Ganseki" => "Master Ganseki",
	"Guia Hayato" => "Guide Hayato",
	"Mestre Raitō" => "Master Raitō",

	// ---------------------------------------------------------------- recompensas e requisitos (funcoestreinos.php)
	"+%s de EP máximo a cada sessão (até +%s no total)." => "+%s max EP per session (up to +%s in total).",
	"+%s de Chakra máximo a cada sessão. Ao concluir: os jutsus custam %s%% menos Chakra (batalha, duelo, PvP e jutsus de menu)." => "+%s max Chakra per session. On completion: jutsus cost %s%% less Chakra (battles, duels, PvP and menu jutsus).",
	"+%s de HP máximo a cada sessão. Ao concluir: +%s de Poder de Defesa (+%s a mais para a especialização Taijutsu)." => "+%s max HP per session. On completion: +%s Defense Power (+%s more for the Taijutsu specialization).",
	"+%s de HP máximo a cada sessão. Ao concluir: +%s de Poder de Defesa." => "+%s max HP per session. On completion: +%s Defense Power.",
	"+%s de TP máximo a cada sessão. Ao concluir: +%s de TP a cada intervalo de descanso (também no mapa)." => "+%s max TP per session. On completion: +%s TP every resting interval (on the map too).",
	"+%s de NP máximo a cada sessão. Ao concluir: o Senjutsu ativo gasta 1 NP a cada %s segundos em vez de 3." => "+%s max NP per session. On completion: active Senjutsu spends 1 NP every %s seconds instead of 3.",
	"Ao concluir: aprende o jutsu exclusivo <b>%s</b> (antes disso ele não pode ser usado)." => "On completion: you learn the exclusive jutsu <b>%s</b> (it can’t be used before that).",
	"Level %s ou mais" => "Level %s or higher",
	"ser ao menos %s" => "be at least %s",
	"ter completado o treino de Senjutsu" => "have completed the Senjutsu training",
	"Esse treinamento não está disponível no momento." => "This training is not available right now.",
	"Você precisa ter ao menos level %s para fazer esse treinamento." => "You need to be at least level %s to take this training.",
	"Você precisa ser ao menos %s para fazer esse treinamento." => "You need to be at least %s to take this training.",
	"Você precisa completar o treino de Senjutsu antes de fazer esse treinamento." => "You need to complete the Senjutsu training before taking this one.",
	"+%s EP" => "+%s EP",
	"+%s CH, -%s%% de Chakra nos jutsus" => "+%s CH, -%s%% Chakra on jutsus",
	"+%s HP, +%s de Defesa" => "+%s HP, +%s Defense",
	"+%s TP, +%s TP por descanso" => "+%s TP, +%s TP per rest",
	"+%s NP, +%s s por NP do Senjutsu" => "+%s NP, +%s s per Senjutsu NP",

	// ---------------------------------------------------------------- mestres e painel (treinoslongos.php, treinamentoequests.php, cidadesconteudo.php)
	"Esse treinamento não existe." => "This training does not exist.",
	"Esse treinamento só pode ser feito na(o) %s." => "This training can only be done in the %s.",
	"Olá, %s. Eu ensino o(a) <b>%s</b>: são %s sessões, com um intervalo de %s minutos entre uma e outra." => "Hello, %s. I teach <b>%s</b>: it takes %s sessions, with %s minutes between one and the next.",
	"Requerimentos:" => "Requirements:",
	"Se quiser, podemos <a href=\"treinoslongos.php?do=treinar&t=CHAVE&inicio=1\">começar agora mesmo</a>." => "If you like, we can <a href=\"treinoslongos.php?do=treinar&t=CHAVE&inicio=1\">start right now</a>.",
	"(Obtido até agora: %s.)" => "(Earned so far: %s.)",
	"%s, com %s." => "%s, with %s.",
	"Sessões: %s, a cada %s minutos." => "Sessions: %s, every %s minutes.",
	"Treinar '%s' com %s" => "Train '%s' with %s",
	"Obtido no treino %s (%s)" => "Earned in the %s training (%s)",

	// ---------------------------------------------------------------- painel do administrador (admin.php?do=treinos)
	"Treinos longos" => "Long trainings",
	"Vila (id)" => "Village (id)",
	"Level mínimo" => "Minimum level",
	"Sessões" => "Sessions",
	"Intervalo (minutos)" => "Interval (minutes)",
	"Bônus por sessão" => "Bonus per session",
	"Bônus final / limite" => "Final bonus / cap",
	"Bônus extra (Taijutsu)" => "Extra bonus (Taijutsu)",
	"Jutsu ensinado (id)" => "Jutsu taught (id)",
	"Ativo (1/0)" => "Active (1/0)",
	"Mestre" => "Master",
	"Graduação mínima" => "Minimum graduation",
	"Recompensa" => "Reward",
	"Treinos salvos. Quem já começou um treino continua com o número de sessões gravado no início." => "Trainings saved. Players who already started a training keep the number of sessions recorded when they started.",
	"Treinos do Painel de Treinos ensinados por mestres nas vilas (treinoslongos.php). O sentido dos bônus muda com o treino: Domínio de Elemento (EP por sessão, até o limite), Controle de Chakra (Chakra máximo por sessão; % de economia de Chakra ao concluir, máx. 50), Corpo de Ferro (HP por sessão; defesa ao concluir, mais o extra para Taijutsu), Caminhada Ninja (TP por sessão; TP por descanso ao concluir), Meditação Natural (NP por sessão; segundos a mais por NP do Senjutsu ao concluir) e Treino de Jutsu Raro (jutsu ensinado ao concluir). Bônus já dados aos jogadores não mudam." => "Training Panel trainings taught by masters in the villages (treinoslongos.php). The meaning of the bonuses depends on the training: Element Mastery (EP per session, up to the cap), Chakra Control (max Chakra per session; % Chakra saving on completion, max 50), Iron Body (HP per session; defense on completion, plus the extra for Taijutsu), Ninja Trek (TP per session; TP per rest on completion), Nature Meditation (NP per session; extra seconds per Senjutsu NP on completion) and Rare Jutsu Training (jutsu taught on completion). Bonuses already given to players don’t change.",

	// ---------------------------------------------------------------- ajuda (help.php)
	"%s sessões, uma a cada %s minutos." => "%s sessions, one every %s minutes.",
	"Requisitos: %s" => "Requirements: %s",
	'<h3><a name="treinoslongos"></a>Treinos Longos</h3>
Além do Senjutsu e do Jutsu de Busca, mestres espalhados pelas vilas ensinam treinos longos. Fale com o mestre pelo ícone dele no quadro <b>Outros</b> da vila: o primeiro clique coloca o treino no seu <b>Painel de Treinos</b>, e cada sessão seguinte só pode ser feita na mesma vila e depois do intervalo indicado.
A maioria dos treinos já dá um pequeno bônus a cada sessão; o bônus maior vem ao concluir. Os bônus são permanentes e continuam valendo depois de subir de level.
<ul>
%s</ul>' => '<h3><a name="treinoslongos"></a>Long Trainings</h3>
Besides Senjutsu and the Search Jutsu, masters spread across the villages teach long trainings. Talk to a master through their icon in the village’s <b>Others</b> box: the first click adds the training to your <b>Training Panel</b>, and each following session can only be done in that same village after the stated interval.
Most trainings already give a small bonus every session; the bigger bonus comes on completion. The bonuses are permanent and keep working after you level up.
<ul>
%s</ul>',

    "Treino" => "Training",
    "Onde começar" => "Where to start",
    "Requisito" => "Requirement",
    "Nível %s" => "Level %s",
    "Os treinos são começados conversando com os mestres nas vilas (ícones no quadro \"Outros\" de cada vila). Depois de começar, eles aparecem aqui." => "Trainings are started by talking to the masters in the villages (icons in each village’s \"Others\" box). Once started, they show up here.",
);
