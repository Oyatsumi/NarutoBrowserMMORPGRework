"""Pontos TP/NP/EP, teleportes e jutsus especiais (gerado para o build_sql.py e aplicado no banco ao vivo).
- colunas novas: spells (uso, acao_menu, requer_senjutsu, tier, custo_np, custo_tp, recarga_min), users (passo_jutsu,
  jutsumenu_cd), control (tpretorno, tpretornopct, tpteleporte, viagemryou, epcusto, epbonus, epnivel, tpdescanso, epdescanso);
- tabela naruto_suprimentos (regras de cada nível da Invocação de Suprimentos);
- jutsus 90-99 (Passo Veloz, Invocação de Suprimentos, jutsus de Senjutsu);
- drops 420-430 (itens de compensação, Pergaminho de Teleporte Aleatório, pergaminhos dos jutsus novos) e suas chances;
- NPCs 30-31 e missões 170-176 (missões exclusivas em lugares remotos).
Saída: tools/pontos_jutsus_especiais.sql"""
import json
def q(x): return "'" + str(x).replace("\\", "\\\\").replace("'", "\\'") + "'"
def v(x): return q(x) if isinstance(x, str) else str(x)

out = ["-- pontos_jutsus_especiais.sql :: TP/NP/EP, teleportes, jutsus de menu/Senjutsu, itens de compensação e missões exclusivas (gerado por tools/pontos_jutsus_especiais.py)."]
W = out.append
W("ALTER TABLE `naruto_spells` ADD COLUMN IF NOT EXISTS `uso` varchar(10) NOT NULL DEFAULT 'batalha' COMMENT 'batalha = luta/duelo (cura também na lista rápida); menu = só na lista de jutsus da coluna da direita', "
  "ADD COLUMN IF NOT EXISTS `acao_menu` varchar(20) NOT NULL DEFAULT '' COMMENT 'jutsu de menu: vazio/cura, suprimentos, passo', "
  "ADD COLUMN IF NOT EXISTS `requer_senjutsu` tinyint(1) UNSIGNED NOT NULL DEFAULT 0, "
  "ADD COLUMN IF NOT EXISTS `tier` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'nível do jutsu de menu (suprimentos 1-4, passo 1-2 = casas extras)', "
  "ADD COLUMN IF NOT EXISTS `custo_np` smallint(5) UNSIGNED NOT NULL DEFAULT 0, "
  "ADD COLUMN IF NOT EXISTS `custo_tp` smallint(5) UNSIGNED NOT NULL DEFAULT 0, "
  "ADD COLUMN IF NOT EXISTS `recarga_min` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'recarga em minutos dos jutsus de menu';")
W("ALTER TABLE `naruto_users` ADD COLUMN IF NOT EXISTS `passo_jutsu` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'id do Passo Veloz ligado (0 = desligado)', "
  "ADD COLUMN IF NOT EXISTS `jutsumenu_cd` varchar(255) NOT NULL DEFAULT '' COMMENT 'recarga dos jutsus de menu: id:unix,id:unix';")
W("ALTER TABLE `naruto_control` ADD COLUMN IF NOT EXISTS `tpretorno` smallint(5) UNSIGNED NOT NULL DEFAULT 5, "
  "ADD COLUMN IF NOT EXISTS `tpretornopct` tinyint(3) UNSIGNED NOT NULL DEFAULT 50, "
  "ADD COLUMN IF NOT EXISTS `tpteleporte` smallint(5) UNSIGNED NOT NULL DEFAULT 30, "
  "ADD COLUMN IF NOT EXISTS `viagemryou` smallint(5) UNSIGNED NOT NULL DEFAULT 2, "
  "ADD COLUMN IF NOT EXISTS `epcusto` tinyint(3) UNSIGNED NOT NULL DEFAULT 1, "
  "ADD COLUMN IF NOT EXISTS `epbonus` tinyint(3) UNSIGNED NOT NULL DEFAULT 15, "
  "ADD COLUMN IF NOT EXISTS `epnivel` tinyint(3) UNSIGNED NOT NULL DEFAULT 2, "
  "ADD COLUMN IF NOT EXISTS `tpdescanso` tinyint(3) UNSIGNED NOT NULL DEFAULT 1, "
  "ADD COLUMN IF NOT EXISTS `epdescanso` tinyint(3) UNSIGNED NOT NULL DEFAULT 1;")
W("""CREATE TABLE IF NOT EXISTS `naruto_suprimentos` (
  `tier` tinyint(3) UNSIGNED NOT NULL,
  `chance_falha` tinyint(3) UNSIGNED NOT NULL DEFAULT 40 COMMENT '% de a invocação não trazer nada',
  `preco_max` mediumint(8) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'itens de loja com buycost até este valor (0 = nenhum)',
  `nivel_max_drop` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'drops com mlevel até este nível (0 = nenhum)',
  `pocao_max` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'poções de drop que recuperam até este valor (0 = nenhuma)',
  `peso_loja` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `peso_drop` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `peso_pocao` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `excluir` varchar(255) NOT NULL DEFAULT '' COMMENT 'ids fora do sorteio: i12,d45 (i = items, d = drops)',
  PRIMARY KEY (`tier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;""")
W("REPLACE INTO `naruto_suprimentos` (`tier`,`chance_falha`,`preco_max`,`nivel_max_drop`,`pocao_max`,`peso_loja`,`peso_drop`,`peso_pocao`,`excluir`) VALUES "
  "(1,45,300,10,50,2,2,5,''),(2,35,1500,25,100,3,3,4,''),(3,25,6000,45,200,3,4,3,''),(4,15,20000,65,400,4,4,2,'i27,i16');")
W("UPDATE `naruto_users` SET `maxep`=`maxep`+5+FLOOR(`level`/2), `currentep`=LEAST(`currentep`+5+FLOOR(`level`/2), `maxep`+5+FLOOR(`level`/2)) WHERE `maxep`<5+FLOOR(`level`/2);")
W("ALTER TABLE `naruto_users` ALTER COLUMN `maxep` SET DEFAULT 5, ALTER COLUMN `currentep` SET DEFAULT 5;")

# id, nome, nome_en, mp, attr, type, elemento, golpes, roubo, efeito, ev, et, ec, cd, nivel, grad, uso, acao, req_sj, tier, np, tp, rec_min, exclusivo, desc, desc_en
J = [
 (90,'Passo Veloz: Pluma','Swift Step: Feather',3,0,6,'vento',1,0,'',0,0,100,0,30,'','menu','passo',0,1,0,0,0,0,
  'O chakra se concentra na sola dos pés e o corpo fica leve como uma pluma: cada passo cobre duas casas do mapa. Fica ligado (gastando Chakra a cada passo) até você desligar.',
  'Chakra gathers in the soles of your feet and your body turns light as a feather: every step covers two map tiles. It stays on (spending Chakra on each step) until you turn it off.'),
 (91,'Passo Veloz: Cometa','Swift Step: Comet',5,0,6,'vento',1,0,'',0,0,100,0,0,'Chuunin','menu','passo',0,2,0,1,0,1,
  'Um rastro de vento segue o ninja como a cauda de um cometa: cada passo cobre três casas. Gasta Chakra e Pontos de Viagem a cada passo até ser desligado.',
  'A trail of wind follows the ninja like a comet tail: every step covers three tiles. It spends Chakra and Travel Points on each step until it is turned off.'),
 (92,'Invocação de Suprimentos: Bolsa','Supply Summoning: Pouch',15,0,6,'neutro',1,0,'',0,0,100,0,8,'','menu','suprimentos',0,1,0,0,10,0,
  'Um selo desenhado na palma abre um pequeno depósito esquecido: às vezes sai uma poção ou um equipamento simples, às vezes só poeira.',
  'A seal drawn on the palm opens a small forgotten storeroom: sometimes a potion or a simple piece of gear comes out, sometimes only dust.'),
 (93,'Invocação de Suprimentos: Baú','Supply Summoning: Chest',30,0,6,'neutro',1,0,'',0,0,100,0,25,'','menu','suprimentos',0,2,0,0,20,0,
  'O selo alcança os baús de viagem dos mercadores ambulantes. Mais chance de encontrar algo útil.',
  'The seal reaches into the travel chests of wandering merchants. A better chance of finding something useful.'),
 (94,'Invocação de Suprimentos: Armazém','Supply Summoning: Storehouse',55,0,6,'neutro',1,0,'',0,0,100,0,45,'','menu','suprimentos',0,3,0,0,40,0,
  'Um círculo de selos se abre no chão e puxa caixas de um armazém distante. Itens melhores, mas o chakra cobrado é alto.',
  'A circle of seals opens on the ground and pulls crates out of a distant storehouse. Better items, but the chakra cost is high.'),
 (95,'Invocação de Suprimentos: Cofre do Clã','Supply Summoning: Clan Vault',90,0,6,'neutro',1,0,'',0,0,100,0,0,'Chuunin','menu','suprimentos',0,4,0,0,60,1,
  'O selo esquecido do clã dos guardiões abre o cofre onde gerações guardaram armas e amuletos. Só quem venceu a provação do cofre conhece os sinais.',
  'The forgotten seal of the warden clan opens the vault where generations stored weapons and amulets. Only those who passed the vault trial know the signs.'),
 (96,'Rugido da Montanha Adormecida','Roar of the Sleeping Mountain',70,260,2,'terra',1,0,'atordoar',0,1,40,4,0,'Chuunin','batalha','',1,0,25,0,0,1,
  'Só no modo Senjutsu: a energia natural da terra sobe pelas pernas e explode num rugido que racha o chão sob o inimigo, que pode ficar atordoado.',
  'Sage Mode only: natural energy rises from the earth through your legs and bursts into a roar that splits the ground under the enemy, which may be stunned.'),
 (97,'Chuva de Pétalas do Bosque Antigo','Petal Rain of the Old Grove',60,115,2,'vento',3,15,'',0,0,100,3,0,'Chuunin','batalha','',1,0,20,0,0,1,
  'Só no modo Senjutsu: pétalas afiadas como lâminas cortam o inimigo três vezes e devolvem parte da vida a quem as chamou.',
  'Sage Mode only: razor-sharp petals slash the enemy three times and return part of the life to the one who called them.'),
 (98,'Casca do Carvalho Milenar','Bark of the Thousand-Year Oak',50,0,6,'terra',1,0,'escudo',700,3,100,5,0,'Chuunin','batalha','',1,0,30,0,0,1,
  'Só no modo Senjutsu: uma casca grossa de carvalho cresce sobre a pele e absorve uma grande quantidade de dano.',
  'Sage Mode only: thick oak bark grows over the skin and soaks up a large amount of damage.'),
 (99,'Respiração da Terra Viva','Breath of the Living Earth',0,600,1,'neutro',1,0,'',0,0,100,0,0,'Chuunin','menu','',1,0,60,0,15,1,
  'Só no modo Senjutsu, fora de lutas: o ninja respira junto com a terra e as feridas se fecham. Gasta Pontos Naturais em vez de Chakra.',
  'Sage Mode only, outside fights: the ninja breathes together with the earth and wounds close. Costs Natural Points instead of Chakra.'),
]
W("DELETE FROM `naruto_spells` WHERE id BETWEEN 90 AND 99;")
cols = "`id`,`name`,`name_en`,`mp`,`attribute`,`type`,`elemento`,`golpes`,`roubovida`,`efeito`,`efeito_valor`,`efeito_turnos`,`efeito_chance`,`cooldown`,`nivelminimo`,`graduacao`,`uso`,`acao_menu`,`requer_senjutsu`,`tier`,`custo_np`,`custo_tp`,`recarga_min`,`exclusivo_missao`,`descricao`,`descricao_en`"
W("INSERT INTO `naruto_spells` (" + cols + ") VALUES\n" + ",\n".join("(" + ",".join(v(x) for x in j) + ")" for j in J) + ";")

# Drops 420-430: itens de compensação (um atributo negativo, ganhos acima da média), teleporte e pergaminhos.
D = [
 # id, nome, mlevel, a1, a2, a3, consumivel, ensina, empilhavel
 (420,'Amuleto do Berserker Rubro',45,'attackpower,150','critico,4','defensepower,-70','',0,0),
 (421,'Selo da Lâmina Faminta',50,'critico,9','attackpower,70','maxhp,-90','',0,0),
 (422,'Pluma do Vento Inquieto',48,'esquiva,7','agilidade,45','maxmp,-60','',0,0),
 (423,'Coração de Rocha Fria',52,'defensepower,170','maxhp,130','attackpower,-60','',0,0),
 (424,'Olho do Asceta Faminto',55,'economiachakra,15','maxmp,130','maxhp,-80','',0,0),
 (425,'Brasão do Mercador Sombrio',40,'goldbonus,25','droprate,8','expbonus,-15','',0,0),
 (426,'Pergaminho de Teleporte Aleatório',60,'X','X','X','tel,1',0,1),
 (427,'Pergaminho: Passo Veloz: Pluma',40,'X','X','X','',90,1),
 (428,'Pergaminho: Invocação de Suprimentos: Bolsa',8,'X','X','X','',92,1),
 (429,'Pergaminho: Invocação de Suprimentos: Baú',25,'X','X','X','',93,1),
 (430,'Pergaminho: Invocação de Suprimentos: Armazém',45,'X','X','X','',94,1),
]
W("DELETE FROM `naruto_monster_drops` WHERE drop_id BETWEEN 420 AND 430;")
W("DELETE FROM `naruto_drops` WHERE id BETWEEN 420 AND 430;")
W("INSERT INTO `naruto_drops` (`id`,`name`,`mlevel`,`type`,`attribute1`,`attribute2`,`attribute3`,`consumivel`,`ensina_jutsu`,`naoVendavel`,`empilhavel`,`maxpilha`) VALUES\n" +
  ",\n".join("(%d,%s,%d,1,%s,%s,%s,%s,%d,0,%d,%d)" % (i, q(n), m, q(a1), q(a2), q(a3), q(c), e, emp, 10 if emp else 1) for (i, n, m, a1, a2, a3, c, e, emp) in D) + ";")
# Chances (por vitória, % já multiplicada pela Sorte/Chance de Drop do jogador): inimigos por faixa de level.
CH = [(420,38,55,0.35),(421,42,60,0.30),(422,40,58,0.35),(423,45,62,0.30),(424,48,66,0.30),(425,32,50,0.35),
      (426,55,100,0.08),(427,40,72,0.20),(428,8,22,1.20),(429,25,42,0.60),(430,45,65,0.30)]
for (d, a, b, c) in CH:
    W("INSERT INTO `naruto_monster_drops` (`monster_id`,`drop_id`,`chance_percent`,`chance_variante_percent`) SELECT `id`, %d, %.2f, %s FROM `naruto_monsters` WHERE `level` BETWEEN %d AND %d;" % (d, c, "0.50" if d <= 425 else "0", a, b))

# NPCs remotos e missões exclusivas.
W("DELETE FROM `naruto_npcs` WHERE id IN (30,31);")
W("INSERT INTO `naruto_npcs` (`id`,`nome`,`avatar`,`icone_mapa`,`latitude`,`longitude`,`fala`,`fala_en`,`ativo`,`papel`) VALUES "
  "(30,'Anciã Kokemushi','personagem9','arvore_personagem6',-372,344,%s,%s,1,''),"
  "(31,'Mestre Tsumuji','personagem4','arvore_personagem5',366,-352,%s,%s,1,'');" % (
   q('Shh... %s, você ouve? A montanha respira devagar. Quem anda com a energia da natureza no peito pode aprender a respirar com ela.'),
   q('Shh... %s, can you hear it? The mountain breathes slowly. Those who walk with natural energy in their chest can learn to breathe with it.'),
   q('Chegou ofegante, %s? Aqui no fim do mundo o vento ensina quem tem pressa. E os cofres esquecidos ensinam quem tem paciência.'),
   q('Out of breath, %s? Here at the edge of the world the wind teaches those in a hurry. And the forgotten vaults teach those with patience.')))
M = [
 (170,'O Sono da Montanha','The Mountain’s Slumber',
  'A Anciã Kokemushi diz que a montanha só ensina o seu rugido a quem a acorda sem raiva. Derrote as feras de pedra que roncam nos ermos, medite no topo do pico gelado e volte com o silêncio no peito.',
  'Elder Kokemushi says the mountain only teaches its roar to those who wake it without anger. Defeat the stone beasts snoring in the badlands, meditate on the frozen peak and come back with silence in your chest.',
  'Rank S.','Anciã Kokemushi','personagem9',30,0,58,'Chuunin','cadeia','',4,14000,6000,'',96,
  {"etapas":[{"tipo":"derrotar_monstro","alvo":"206","quantidade":3,"texto":"Os golens de obsidiana roncam nos ermos: acalme-os à força.","texto_en":"The obsidian golems snore in the badlands: calm them down by force."},
             {"tipo":"visitar_local","alvo":"-390,380","quantidade":1,"texto":"Medite no topo do pico gelado, no canto do mundo.","texto_en":"Meditate on top of the frozen peak, at the corner of the world."},
             {"tipo":"derrotar_monstro","alvo":"219","quantidade":1,"texto":"O Titã de Pedra dos Ermos é o sono mais pesado da montanha. Derrote-o.","texto_en":"The Badlands Stone Titan is the mountain’s heaviest sleep. Defeat it."},
             {"tipo":"falar_com_npc","alvo":"30","quantidade":1,"texto":"Volte até a Anciã Kokemushi.","texto_en":"Return to Elder Kokemushi."}]},0),
 (171,'Pétalas que Cortam','Petals That Cut',
  'No bosque mais antigo do continente as flores só se abrem para quem já sentiu a energia natural. Colha pétalas da hidra do manguezal, sobreviva à tempestade de areia viva e prove que a sua lâmina é tão leve quanto uma flor.',
  'In the oldest grove on the continent, the flowers only open for those who have felt natural energy. Gather petals from the mangrove hydra, survive the living sandstorm and prove your blade is as light as a flower.',
  'Rank S.','Anciã Kokemushi','personagem9',30,0,60,'Chuunin','cadeia','',3,15000,7000,'',97,
  {"etapas":[{"tipo":"coletar","alvo":"Pétala do Manguezal","quantidade":3,"monstro":213,"chance":40,"texto":"Colha três Pétalas do Manguezal das hidras.","texto_en":"Gather three Mangrove Petals from the hydras."},
             {"tipo":"derrotar_monstro","alvo":"215","quantidade":2,"texto":"Atravesse a Tempestade de Areia Viva duas vezes.","texto_en":"Cross the Living Sandstorm twice."},
             {"tipo":"falar_com_npc","alvo":"30","quantidade":1,"texto":"Leve as pétalas à Anciã Kokemushi.","texto_en":"Bring the petals to Elder Kokemushi."}]},0),
 (172,'A Casca de Mil Invernos','The Bark of a Thousand Winters',
  'O carvalho que a Anciã plantou há mil invernos está doente. As raízes chegam até a geleira, onde o mamute de guerra pisoteia tudo. Afaste as feras do gelo, pague os jardineiros do bosque e o carvalho lhe dará a sua casca.',
  'The oak the Elder planted a thousand winters ago is sick. Its roots reach the glacier, where the war mammoth tramples everything. Drive off the ice beasts, pay the grove gardeners and the oak will give you its bark.',
  'Rank S.','Anciã Kokemushi','personagem9',30,0,64,'Chuunin','cadeia','',3,18000,0,'',98,
  {"etapas":[{"tipo":"derrotar_monstro","alvo":"207","quantidade":3,"texto":"Afaste os lobos gigantes da geleira.","texto_en":"Drive off the giant glacier wolves."},
             {"tipo":"derrotar_monstro","alvo":"220","quantidade":1,"texto":"Derrote o Mamute de Guerra da Tundra.","texto_en":"Defeat the Tundra War Mammoth."},
             {"tipo":"pagar_ryou","alvo":"","quantidade":12000,"texto":"Pague os jardineiros que vão tratar as raízes.","texto_en":"Pay the gardeners who will tend the roots."}]},0),
 (173,'O Fôlego da Terra','The Breath of the Earth',
  'A última lição da Anciã: respirar junto com a terra. Visite os três santuários de musgo nos confins do mapa, sem pressa, e volte para ouvir o que eles disseram.',
  'The Elder’s last lesson: breathe together with the earth. Visit the three moss shrines at the far ends of the map, unhurried, and come back to hear what they said.',
  'Rank S.','Anciã Kokemushi','personagem9',30,0,66,'Chuunin','cadeia','',4,20000,0,'',99,
  {"etapas":[{"tipo":"visitar_local","alvo":"-395,-395","quantidade":1,"texto":"Santuário de musgo do sudoeste.","texto_en":"Southwest moss shrine."},
             {"tipo":"visitar_local","alvo":"395,395","quantidade":1,"texto":"Santuário de musgo do nordeste.","texto_en":"Northeast moss shrine."},
             {"tipo":"visitar_local","alvo":"395,-395","quantidade":1,"texto":"Santuário de musgo do noroeste.","texto_en":"Northwest moss shrine."},
             {"tipo":"falar_com_npc","alvo":"30","quantidade":1,"texto":"Conte à Anciã Kokemushi o que os santuários disseram.","texto_en":"Tell Elder Kokemushi what the shrines said."}]},0),
 (174,'O Cofre dos Guardiões','The Wardens’ Vault',
  'O Mestre Tsumuji guarda o último sinal do cofre do clã dos guardiões. Para merecê-lo, derrote o dragão que dorme sobre o ouro do vulcão, a hidra que guarda a chave afogada e pague o selo de abertura. Ninguém faz isso duas vezes.',
  'Master Tsumuji keeps the last sign of the warden clan vault. To earn it, defeat the dragon sleeping on the volcano gold, the hydra guarding the drowned key, and pay for the opening seal. Nobody does it twice.',
  'Rank S.','Mestre Tsumuji','personagem4',31,0,72,'Chuunin','cadeia','',4,30000,0,'',95,
  {"etapas":[{"tipo":"derrotar_monstro","alvo":"213","quantidade":2,"texto":"A Hidra do Manguezal engoliu a chave do cofre.","texto_en":"The Mangrove Hydra swallowed the vault key."},
             {"tipo":"derrotar_monstro","alvo":"221","quantidade":1,"texto":"Derrote o Dragão Ancestral do Vulcão que dorme sobre o ouro.","texto_en":"Defeat the Ancient Volcano Dragon sleeping on the gold."},
             {"tipo":"pagar_ryou","alvo":"","quantidade":25000,"texto":"Pague o selo de abertura do cofre.","texto_en":"Pay for the vault opening seal."},
             {"tipo":"falar_com_npc","alvo":"31","quantidade":1,"texto":"Volte até o Mestre Tsumuji.","texto_en":"Return to Master Tsumuji."}]},0),
 (175,'A Trilha do Cometa','The Comet Trail',
  'O Mestre Tsumuji corre de um canto do mundo ao outro antes do chá esfriar. Acompanhe o ritmo dele: toque os quatro cantos do mapa em menos de duas horas e volte para buscar o segredo do cometa.',
  'Master Tsumuji runs from one corner of the world to the other before his tea goes cold. Keep up with him: touch the four corners of the map in under two hours and come back for the comet’s secret.',
  'Rank S.','Mestre Tsumuji','personagem4',31,0,55,'Chuunin','cadeia','',5,12000,0,'',91,
  {"etapas":[{"tipo":"visitar_local","alvo":"399,399","quantidade":1,"texto":"Canto nordeste.","texto_en":"Northeast corner."},
             {"tipo":"visitar_local","alvo":"-399,399","quantidade":1,"texto":"Canto sudeste.","texto_en":"Southeast corner."},
             {"tipo":"visitar_local","alvo":"-399,-399","quantidade":1,"texto":"Canto sudoeste.","texto_en":"Southwest corner."},
             {"tipo":"visitar_local","alvo":"399,-399","quantidade":1,"texto":"Canto noroeste.","texto_en":"Northwest corner."},
             {"tipo":"falar_com_npc","alvo":"31","quantidade":1,"texto":"Volte até o Mestre Tsumuji.","texto_en":"Return to Master Tsumuji."}]},120),
 (176,'Pés de Pluma','Feather Feet',
  'Antes do cometa, a pluma. O Mestre Tsumuji quer ver você atravessar o deserto inteiro sem perder o fôlego: derrote os escorpiões das areias e chegue até ele.',
  'Before the comet, the feather. Master Tsumuji wants to see you cross the whole desert without losing your breath: defeat the sand scorpions and reach him.',
  'Rank A.','Mestre Tsumuji','personagem4',31,0,35,'','cadeia','',2,5000,2000,'',90,
  {"etapas":[{"tipo":"derrotar_monstro","alvo":"194","quantidade":4,"texto":"Derrote quatro Escorpiões Gigantes das Areias.","texto_en":"Defeat four Giant Sand Scorpions."},
             {"tipo":"falar_com_npc","alvo":"31","quantidade":1,"texto":"Volte até o Mestre Tsumuji.","texto_en":"Return to Master Tsumuji."}]},0),
]
W("DELETE FROM `naruto_missoesextras` WHERE id BETWEEN 170 AND 176;")
rows = []
for m in M:
    (i, t, te, d, de, rk, npcn, npca, npci, vila, niv, grad, tipo, alvo, qtd, exp, ry, item, jut, cfg, lim) = m
    rows.append("(" + ",".join([str(i), "'extra'", "0", q(t), q(te), q(d), q(de), q(rk), q(npcn), q(npca), str(npci), str(vila), str(niv), q(grad), q(tipo), q(alvo), str(qtd), str(exp), str(ry), q(item), str(jut), "0", q(json.dumps(cfg, ensure_ascii=False) if cfg else ""), str(lim), str(i), "1"]) + ")")
W("INSERT INTO `naruto_missoesextras` (`id`,`categoria`,`etapa`,`titulo`,`titulo_en`,`descricao`,`descricao_en`,`rank_texto`,`npc_nome`,`npc_avatar`,`npc_id`,`vila_id`,`nivel_minimo`,`graduacao_minima`,`tipo`,`alvo`,`quantidade`,`recompensa_exp`,`recompensa_ryou`,`recompensa_item`,`recompensa_jutsu`,`repetivel`,`config`,`limite_min`,`ordem`,`ativa`) VALUES\n" + ",\n".join(rows) + ";")
open("tools/pontos_jutsus_especiais.sql", "w", encoding="utf8").write("\n".join(out) + "\n")
print("ok")
