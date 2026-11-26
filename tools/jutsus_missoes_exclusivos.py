"""Jutsus exclusivos de missão (spells 50-55, exclusivo_missao=1) e as 6 missões que os dão (missoesextras 140-145).
Gera tools/jutsus_missoes_exclusivos.sql (usado pelo build_sql.py e aplicado no banco ao vivo)."""
import json
def q(x): return "'" + str(x).replace("\\", "\\\\").replace("'", "\\'") + "'"
J = [
 # id, nome, nome_en, mp, attr, type, elemento, golpes, roubo, efeito, ev, et, ec, cd, nivel, classe, grad, desc, desc_en
 (50,'Selo das Mil Lanternas','Seal of a Thousand Lanterns',38,45,2,'fogo',3,0,'queimadura',15,3,70,3,30,0,'',
  'Centenas de pequenas chamas presas em papel de selo voam em espiral e explodem uma a uma contra o inimigo, deixando-o em brasa.',
  'Hundreds of tiny flames trapped in sealing paper spiral through the air and burst one by one against the enemy, leaving it smouldering.'),
 (51,'Abraço da Maré Funda','Embrace of the Deep Tide',50,260,1,'agua',1,0,'cura_turnos',40,3,100,4,42,0,'',
  'Uma onda de chakra frio envolve o corpo e fecha as feridas; a maré continua subindo por alguns turnos.',
  'A wave of cold chakra wraps the body and closes its wounds; the tide keeps rising for a few turns.'),
 (52,'Voz do Trovão Distante','Voice of the Distant Thunder',60,170,2,'raio',1,0,'debuff_defesa',35,3,100,3,50,0,'',
  'Um estrondo que chega antes do raio: o golpe racha a guarda do inimigo, que passa a receber mais dano.',
  'A rumble that arrives before the lightning: the blow cracks the enemy guard, which then takes more damage.'),
 (53,'Pele do Titã de Basalto','Basalt Titan Hide',55,0,6,'terra',1,0,'escudo',500,3,100,5,60,0,'Chuunin',
  'Placas de rocha vulcânica crescem sobre a pele como a carapaça do Titã de Pedra, absorvendo os golpes.',
  'Plates of volcanic rock grow over the skin like the Stone Titan shell, soaking up incoming blows.'),
 (54,'Coração do Leviatã Abissal','Heart of the Abyssal Leviathan',90,240,2,'agua',1,20,'atordoar',0,1,50,5,68,0,'Chuunin',
  'A pressão do fundo do mar esmaga o inimigo de uma vez: parte da força volta para quem usou e o alvo pode ficar atordoado.',
  'The pressure of the ocean floor crushes the enemy at once: some of the strength flows back to the user and the target may be stunned.'),
 (55,'Rugido do Dragão de Cinzas','Roar of the Ash Dragon',95,130,2,'fogo',2,25,'queimadura',30,3,100,4,76,0,'Chuunin',
  'Dois jatos de fogo negro em forma de dragão mordem o inimigo, roubam vida e deixam cinzas ardendo por vários turnos.',
  'Two jets of black dragon-shaped fire bite the enemy, steal life and leave embers burning for several turns.'),
]
M = [
 (140,'As Lanternas que Não Apagam','The Lanterns That Never Go Out',
  'Desde a última lua cheia, lanternas acesas aparecem flutuando sobre o rio da Vila da Folha, e quem tenta apagá-las volta queimado. A Hokage quer saber quem está por trás disso.',
  'Since the last full moon, lit lanterns have been drifting over the Leaf Village river, and anyone who tries to put them out comes back burned. The Hokage wants to know who is behind it.',
  'Rank B.','Hokage','1',0,1,30,'','cadeia','',3,2600,1800,'',50,0,
  {"etapas":[{"tipo":"falar_com_npc","alvo":"12","quantidade":1,"texto":"A herborista Suzuran tratou os queimados: pergunte o que ela viu.","texto_en":"Suzuran the herbalist treated the burned: ask what she saw."},
             {"tipo":"derrotar_monstro","alvo":"179","quantidade":3,"texto":"Assassinos de aluguel soltam as lanternas para distrair os guardas. Derrote-os.","texto_en":"Hired assassins release the lanterns to distract the guards. Defeat them."},
             {"tipo":"visitar_local","alvo":"20,-20","quantidade":1,"texto":"Encontre a oficina escondida rio acima e tome o selo das lanternas.","texto_en":"Find the hidden workshop upriver and take the lantern seal."}]},0),
 (141,'Pérolas da Maré Funda','Pearls of the Deep Tide',
  'Os curandeiros da Vila da Névoa usam cristais que ecoam o som do mar para preparar um remédio raro. O tubarão que guarda o recife os engole. Traga três antes que a maré vire.',
  'The Mist Village healers use crystals that echo the sound of the sea to brew a rare remedy. The shark guarding the reef swallows them. Bring back three before the tide turns.',
  'Rank B.','Mizukage','4',0,4,42,'','coletar','Cristal Azul de Eco',3,3600,2200,'',51,0,
  {"monstro":131,"chance":40},60),
 (142,'O Eco do Trovão','The Echo of Thunder',
  'Os sentinelas de pedra do pico estão despertando a cada tempestade e descendo até as trilhas da Vila da Nuvem. Derrube cinco deles e escute o que o trovão tem a ensinar.',
  'The stone sentinels on the peak wake with every storm and come down onto the Cloud Village trails. Bring down five of them and listen to what the thunder has to teach.',
  'Rank B.','Raikage','6',0,6,50,'','derrotar_monstro','200',5,5200,3200,'',52,0,{},0),
 (143,'A Pele do Titã','The Hide of the Titan',
  'A ferreira Okaya diz que só a carapaça do Titã de Pedra dos Ermos aguenta a fornalha nova da Vila da Pedra. É uma caçada longa: limpe o cânion, derrube o Titã e pague o fogo da forja.',
  'Okaya the blacksmith says only the shell of the Badlands Stone Titan can withstand the Stone Village new furnace. It is a long hunt: clear the canyon, bring down the Titan and pay for the forge fire.',
  'Rank S.','Tsuchikage','3',0,3,62,'Chuunin','cadeia','',4,16000,9000,'Pergaminho: Mil Raízes Famintas',53,0,
  {"etapas":[{"tipo":"falar_com_npc","alvo":"8","quantidade":1,"texto":"Ouça o plano da ferreira Okaya.","texto_en":"Hear out Okaya the blacksmith plan."},
             {"tipo":"derrotar_monstro","alvo":"212","quantidade":3,"texto":"Os guardiões do cânion protegem o caminho do Titã.","texto_en":"The canyon guardians protect the path to the Titan."},
             {"tipo":"derrotar_monstro","alvo":"219","quantidade":1,"texto":"Derrote o Titã de Pedra dos Ermos.","texto_en":"Defeat the Badlands Stone Titan."},
             {"tipo":"pagar_ryou","alvo":"","quantidade":5000,"texto":"Pague o carvão e os ajudantes da forja.","texto_en":"Pay for the coal and the forge helpers."}]},0),
 (144,'O Coração do Leviatã','The Heart of the Leviathan',
  'A cartógrafa Nayumi marcou o ponto onde o Leviatã das Profundezas sobe para respirar. A janela é curta: em duas horas a corrente muda e ele some por meses.',
  'Nayumi the cartographer has marked the spot where the Abyssal Leviathan surfaces to breathe. The window is short: in two hours the current shifts and it vanishes for months.',
  'Rank S.','Mizukage','4',0,4,70,'Chuunin','cadeia','',4,23000,13000,'Pergaminho: Lamento da Serpente Pálida',54,0,
  {"etapas":[{"tipo":"falar_com_npc","alvo":"10","quantidade":1,"texto":"Pegue o mapa das correntes com a cartógrafa Nayumi.","texto_en":"Get the current chart from Nayumi the cartographer."},
             {"tipo":"visitar_local","alvo":"40,190","quantidade":1,"texto":"Vá até o ponto marcado no mar aberto.","texto_en":"Go to the marked spot on the open sea."},
             {"tipo":"derrotar_monstro","alvo":"216","quantidade":2,"texto":"Um esquadrão de elite também caça o Leviatã: tire-os do caminho.","texto_en":"An elite squad is hunting the Leviathan too: get them out of the way."},
             {"tipo":"derrotar_monstro","alvo":"218","quantidade":1,"texto":"Derrote o Leviatã das Profundezas.","texto_en":"Defeat the Abyssal Leviathan."}]},120),
 (145,'Rugido nas Cinzas','Roar in the Ashes',
  'A arqueóloga Itsuha decifrou uma tábua que fala de um dragão que dorme sob o vulcão e acorda a cada século. O século acabou. Enfrente as criaturas de fogo, o senhor da guerra que quer domá-lo e, por fim, o próprio dragão, antes que o vulcão desperte.',
  'Itsuha the archaeologist deciphered a tablet about a dragon that sleeps beneath the volcano and wakes once a century. The century is over. Face the fire creatures, the warlord who wants to tame it and finally the dragon itself, before the volcano erupts.',
  'Rank S.','Arqueóloga Itsuha','personagem7',14,0,76,'Chuunin','cadeia','',4,32000,19000,'Pergaminho: Céu Rachado',55,0,
  {"etapas":[{"tipo":"derrotar_monstro","alvo":"217","quantidade":3,"texto":"Aves de fogo rondam a cratera: derrube três.","texto_en":"Fire birds circle the crater: bring down three."},
             {"tipo":"derrotar_monstro","alvo":"214","quantidade":2,"texto":"O Senhor da Guerra Amaldiçoado quer domar o dragão. Impeça-o.","texto_en":"The Cursed Warlord wants to tame the dragon. Stop him."},
             {"tipo":"derrotar_monstro","alvo":"221","quantidade":1,"texto":"Derrote o Dragão Ancestral do Vulcão.","texto_en":"Defeat the Ancient Volcano Dragon."},
             {"tipo":"falar_com_npc","alvo":"14","quantidade":1,"texto":"Volte até Itsuha com a escama do dragão.","texto_en":"Return to Itsuha with the dragon scale."}]},180),
]
out = ["-- Jutsus exclusivos de missão (exclusivo_missao=1: sem nível, sem pergaminho) e as missões 140-145 que os dão (recompensa_jutsu).",
       "DELETE FROM `naruto_spells` WHERE id BETWEEN 50 AND 55;",
       "INSERT INTO `naruto_spells` (`id`,`name`,`name_en`,`mp`,`attribute`,`type`,`elemento`,`golpes`,`roubovida`,`efeito`,`efeito_valor`,`efeito_turnos`,`efeito_chance`,`cooldown`,`nivelminimo`,`classe`,`graduacao`,`descricao`,`descricao_en`,`exclusivo_missao`) VALUES"]
out.append(",\n".join("("+",".join(q(v) if isinstance(v,str) else str(v) for v in j)+",1)" for j in J)+";")
out.append("DELETE FROM `naruto_missoesextras` WHERE id BETWEEN 140 AND 145;")
out.append("INSERT INTO `naruto_missoesextras` (`id`,`categoria`,`etapa`,`titulo`,`titulo_en`,`descricao`,`descricao_en`,`rank_texto`,`npc_nome`,`npc_avatar`,`npc_id`,`vila_id`,`nivel_minimo`,`graduacao_minima`,`tipo`,`alvo`,`quantidade`,`recompensa_exp`,`recompensa_ryou`,`recompensa_item`,`recompensa_jutsu`,`repetivel`,`config`,`limite_min`,`ordem`,`ativa`) VALUES")
rows=[]
for m in M:
    (i,t,te,d,de,rk,npcn,npca,npci,vila,niv,grad,tipo,alvo,qtd,exp,ry,item,jut,rep,cfg,lim)=m
    rows.append("("+",".join([str(i),"'extra'","0",q(t),q(te),q(d),q(de),q(rk),q(npcn),q(npca),str(npci),str(vila),str(niv),q(grad),q(tipo),q(alvo),str(qtd),str(exp),str(ry),q(item),str(jut),str(rep),q(json.dumps(cfg,ensure_ascii=False) if cfg else ""),str(lim),str(i),"1"])+")")
out.append(",\n".join(rows)+";")
open("tools/jutsus_missoes_exclusivos.sql","w",encoding="utf8").write("\n".join(out)+"\n")
