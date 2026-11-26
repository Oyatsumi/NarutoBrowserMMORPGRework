"""Especializações (Ninjutsu / Taijutsu / Genjutsu): modificadores de classe, crescimento por nível e jutsus exclusivos.
Gera tools/classes_especializacao.sql, usado pelo build_sql.py (instalação) e aplicado no banco ao vivo.

Uso: python3 classes_especializacao.py [saida.sql]
Base: tools/levels_base.tsv (a tabela levels antes desta mudança, já com os jutsus 20-49 de jutsus_novos.sql).
Os números foram ajustados com tests/classes_sim.py (simulação PvE e de duelos; ver docs/ESPECIALIZACOES.md).
Classes: 1 = Ninjutsu, 2 = Taijutsu, 3 = Genjutsu (nomes em control.class1name..class3name).
"""
import sys, os

AQUI = os.path.dirname(os.path.abspath(__file__))
SAIDA = sys.argv[1] if len(sys.argv) > 1 else os.path.join(AQUI, "classes_especializacao.sql")

# ---------------- Modificadores (tabela naruto_classes; todos em %, exceto duracao_efeito em turnos) ----------------
# dano_fisico ...... % no dano do ataque físico (Atacar)          critico ........ pontos de % de crítico no ataque físico
# dano_jutsu ....... % no dano de todos os jutsus de dano          dano_elemental . % a mais nos jutsus com elemento (não neutro)
# vantagem_elemento  % a mais quando o elemento é super efetivo    defesa_fisica .. % a menos de dano físico recebido (negativo = recebe mais)
# chance_efeito .... pontos de % na chance dos efeitos no inimigo  duracao_efeito . turnos a mais de queimadura/veneno/debuffs causados
# valor_efeito ..... % a mais no valor de queimadura/veneno/debuffs causados   dreno .. % a mais no Dreno de Chakra
# resist_debuff .... positivo: % a menos de chance de sofrer efeitos de outro jogador; negativo: os efeitos sofridos ficam |x|% mais fortes
# economia_tecnica . % a menos de chakra nas técnicas sem elemento (jutsus neutros de dano/+dano/+defesa/suporte)
CAMPOS = ["dano_fisico", "critico", "dano_jutsu", "dano_elemental", "vantagem_elemento", "defesa_fisica", "chance_efeito",
          "duracao_efeito", "valor_efeito", "dreno", "resist_debuff", "economia_tecnica"]
MODS = {  # valores finais ajustados com tests/classes_sim.py (busca aleatória + ajuste à mão; ver docs/ESPECIALIZACOES.md)
    1: dict(dano_fisico=0,  critico=0,  dano_jutsu=0,  dano_elemental=26, vantagem_elemento=25, defesa_fisica=-5, chance_efeito=0,
            duracao_efeito=0, valor_efeito=0,  dreno=0,  resist_debuff=0,   economia_tecnica=0),
    2: dict(dano_fisico=6,  critico=8,  dano_jutsu=0,  dano_elemental=0,  vantagem_elemento=0,  defesa_fisica=0,  chance_efeito=0,
            duracao_efeito=0, valor_efeito=0,  dreno=0,  resist_debuff=-20, economia_tecnica=25),
    3: dict(dano_fisico=10, critico=0,  dano_jutsu=-3, dano_elemental=0,  vantagem_elemento=0,  defesa_fisica=10, chance_efeito=37,
            duracao_efeito=1, valor_efeito=36, dreno=60, resist_debuff=21,  economia_tecnica=0),
}
# Senjutsu ativo por classe (colunas sj_*; quem ainda não tem classe recebe o bônus antigo: +20% Ataque e +20% Defesa).
# sj_ataque/sj_defesa = % do Poder de Ataque/Defesa; sj_economia = % de Chakra a menos em TODOS os jutsus; os outros somam no modificador de mesmo nome.
CAMPOS_SJ = ["sj_ataque", "sj_defesa", "sj_dano_fisico", "sj_critico", "sj_dano_elemental", "sj_economia", "sj_chance_efeito", "sj_duracao_efeito", "sj_resist_debuff"]
SJ = {
    1: dict(sj_ataque=8,  sj_defesa=15, sj_dano_fisico=0,  sj_critico=0, sj_dano_elemental=20, sj_economia=20, sj_chance_efeito=0,  sj_duracao_efeito=0, sj_resist_debuff=0),
    2: dict(sj_ataque=15, sj_defesa=15, sj_dano_fisico=10, sj_critico=5, sj_dano_elemental=0,  sj_economia=0,  sj_chance_efeito=0,  sj_duracao_efeito=0, sj_resist_debuff=0),
    3: dict(sj_ataque=0,  sj_defesa=25, sj_dano_fisico=8,  sj_critico=0, sj_dano_elemental=0,  sj_economia=0,  sj_chance_efeito=20, sj_duracao_efeito=1, sj_resist_debuff=25),
}
DESC = {
    1: ("Mestre dos elementos. Jutsus com elemento causam mais dano e a vantagem de elemento pesa mais; é quem mais ganha Chakra a cada nível. Fraqueza: defesa física um pouco menor (recebe mais dano de ataques físicos).",
        "Master of the elements. Elemental jutsus deal more damage and elemental advantage hits harder; gains the most Chakra per level. Weakness: lower physical defense (takes more damage from physical attacks)."),
    2: ("Especialista do corpo a corpo. Ataques físicos causam mais dano e acertam críticos com mais frequência, as técnicas sem elemento custam menos Chakra e é quem mais ganha HP a cada nível. Fraqueza: pouca resistência a genjutsu (efeitos negativos de outros ninjas pegam mais forte).",
        "Close-combat specialist. Physical attacks deal more damage and crit more often, non-elemental techniques cost less Chakra, and gains the most HP per level. Weakness: poor genjutsu resistance (negative effects from other ninjas hit harder)."),
    3: ("Mestre do controle. Atordoamentos, venenos, queimaduras e enfraquecimentos pegam com mais frequência, duram mais e são mais fortes; o Dreno de Chakra rouba mais e resiste aos efeitos dos inimigos. Fraqueza: menos dano direto com jutsus.",
        "Master of control. Stuns, poisons, burns and weakening effects land more often, last longer and hit harder; Chakra Drain steals more and resists enemy effects. Weakness: less direct damage from jutsus."),
}

# ---------------- Crescimento por nível (tabela levels) ----------------
# Experiência: as três classes passam a usar a mesma curva (a antiga de Taijutsu), que também vale para quem ainda
# não escolheu a especialização (charclass 0, "Estudante" — usa as colunas 1_* para HP/CH/Força/Destreza).
# HP: Taijutsu ganha +1 HP a cada 3 níveis, Genjutsu +1 a cada 4 e Ninjutsu perde o HP extra que tinha (fica com o crescimento base).
# Chakra: Ninjutsu ganha +1 CH a cada 3 níveis a partir do 3; Genjutsu +1 a cada 4.
def ler_base():
    linhas = open(os.path.join(AQUI, "levels_base.tsv"), encoding="utf8").read().rstrip("\n").split("\n")
    cab = linhas[0].split("\t")
    return [dict(zip(cab, map(int, l.split("\t")))) for l in linhas[1:]]

def nova_linha(b):
    n = b["id"]
    r = dict(b)
    for c in (1, 3): r[f"{c}_exp"] = b["2_exp"]
    if n >= 3 and n < 100 and n % 3 == 0: r["2_hp"] = b["2_hp"] + 1
    if n >= 3 and n % 3 == 0 and n < 100: r["1_mp"] = b["1_mp"] + 1
    if n >= 4 and n % 4 == 0 and n < 100: r["3_mp"] = b["3_mp"] + 1
    if n >= 2 and n < 100 and n % 4 == 0: r["3_hp"] = b["3_hp"] + 1
    r["1_hp"] = b["2_hp"]
    return r

# ---------------- Jutsus exclusivos novos (ids 56-67, aprendidos por nível na coluna da classe) ----------------
# (id, nome, nome_en, tipo, mp, poder, elemento, golpes, roubovida, efeito, valor, turnos, chance, recarga, nível, classe, descricao, descricao_en)
J = [
 # Taijutsu: técnicas sem elemento (custam metade do Chakra para Taijutsu).
 (56, "Punho de Pedra Viva", "Living Stone Fist", 2, 6, 10, "neutro", 2, 0, "", 0, 0, 100, 0, 8, 2,
  "Dois socos secos, com o punho endurecido pelo chakra, no mesmo ponto do corpo do inimigo.",
  "Two sharp punches, the fist hardened by chakra, landing on the same spot of the enemy's body."),
 (57, "Investida do Javali Cinzento", "Grey Boar Charge", 2, 16, 40, "neutro", 1, 0, "atordoar", 0, 1, 35, 2, 24, 2,
  "Uma arrancada de ombro baixo que derruba o inimigo; às vezes ele fica tonto e perde o próximo ataque.",
  "A low-shoulder charge that knocks the enemy over; sometimes they are left dazed and lose their next attack."),
 (58, "Sequência dos Cem Passos", "Hundred-Step Barrage", 2, 30, 42, "neutro", 4, 0, "", 0, 0, 100, 2, 47, 2,
  "O corpo some e reaparece em volta do inimigo, acertando quatro golpes antes que ele consiga reagir.",
  "Your body vanishes and reappears around the enemy, landing four blows before they can react."),
 (59, "Golpe da Fera Faminta", "Starving Beast Strike", 2, 45, 110, "neutro", 1, 25, "", 0, 0, 100, 3, 66, 2,
  "Um golpe selvagem que arranca a força do inimigo e a devolve como vida para quem ataca.",
  "A savage blow that tears the strength out of the enemy and returns it as life to the attacker."),
 # Ninjutsu: elementos.
 (60, "Lâminas de Vento Gêmeas", "Twin Wind Blades", 2, 9, 12, "vento", 2, 0, "", 0, 0, 100, 0, 8, 1,
  "Duas lâminas de ar comprimido cortam o inimigo em X.",
  "Two blades of compressed air slash the enemy in an X."),
 (61, "Pilar de Rocha Ascendente", "Rising Rock Pillar", 2, 24, 45, "terra", 1, 0, "debuff_defesa", 15, 3, 100, 1, 26, 1,
  "Um pilar de pedra brota do chão sob o inimigo e racha a sua guarda.",
  "A stone pillar bursts from the ground beneath the enemy and cracks their guard."),
 (62, "Serpente de Relâmpago", "Lightning Serpent", 2, 45, 55, "raio", 3, 0, "atordoar", 0, 1, 25, 2, 53, 1,
  "Uma serpente de raios morde três vezes; o choque pode paralisar o alvo.",
  "A serpent of lightning bites three times; the shock may paralyze the target."),
 (63, "Cometa de Brasa Errante", "Wandering Ember Comet", 2, 80, 196, "fogo", 1, 0, "queimadura", 25, 3, 100, 4, 68, 1,
  "Uma esfera de fogo cai do alto como um cometa e deixa o inimigo em chamas.",
  "A sphere of fire falls from above like a comet and leaves the enemy ablaze."),
 # Genjutsu: controle.
 (64, "Eco da Sombra Lenta", "Echo of the Slow Shadow", 2, 8, 24, "neutro", 1, 0, "debuff_ataque", 20, 3, 100, 2, 8, 3,
  "O inimigo passa a ver a própria sombra um instante atrasada e hesita a cada golpe.",
  "The enemy starts seeing their own shadow a moment late and hesitates with every blow."),
 (65, "Labirinto de Espelhos Partidos", "Labyrinth of Broken Mirrors", 6, 22, 0, "neutro", 1, 0, "atordoar", 0, 2, 55, 5, 24, 3,
  "Espelhos quebrados cercam o inimigo; perdido entre os reflexos, ele pode ficar sem atacar por dois turnos.",
  "Broken mirrors surround the enemy; lost among the reflections, they may be unable to attack for two turns."),
 (66, "Pesadelo Rastejante", "Creeping Nightmare", 2, 28, 30, "neutro", 1, 0, "veneno", 20, 4, 100, 2, 46, 3,
  "Um pesadelo entra pela pele do inimigo e continua a feri-lo por dentro durante vários turnos.",
  "A nightmare seeps in through the enemy's skin and keeps hurting them from within for several turns."),
 (67, "Coroa de Espinhos Ilusórios", "Crown of Illusory Thorns", 2, 50, 170, "neutro", 1, 0, "debuff_defesa", 30, 4, 100, 3, 66, 3,
  "Espinhos que só o inimigo vê se cravam na sua cabeça: ele baixa a guarda e passa a sofrer mais dano.",
  "Thorns that only the enemy can see dig into their head: they lower their guard and take more damage."),
]

def q(s): return "'" + str(s).replace("\\", "\\\\").replace("'", "\\'") + "'"

def gerar():
    base = ler_base()
    novas = [nova_linha(b) for b in base]
    porid = {r["id"]: r for r in novas}
    # Coloca cada jutsu novo no nível pedido; se a coluna da classe já ensina outro jutsu nesse nível, usa o próximo livre.
    onde = {}
    for j in J:
        n, c = j[14], j[15]
        while porid[n][f"{c}_spells"] != 0: n += 1
        porid[n][f"{c}_spells"] = j[0]; onde[j[0]] = n
    out = ["-- Especializações (Ninjutsu/Taijutsu/Genjutsu): gerado por tools/classes_especializacao.py. Ver docs/ESPECIALIZACOES.md.",
           "DROP TABLE IF EXISTS `naruto_classes`;",
           "CREATE TABLE `naruto_classes` (",
           "  `id` tinyint(3) UNSIGNED NOT NULL COMMENT '1 Ninjutsu, 2 Taijutsu, 3 Genjutsu (nomes em control.classNname)',"]
    for c in CAMPOS + CAMPOS_SJ: out.append(f"  `{c}` smallint(6) NOT NULL DEFAULT 0,")
    out += ["  `descricao` text NOT NULL,", "  `descricao_en` text NOT NULL,", "  PRIMARY KEY (`id`)", ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
            "DELETE FROM `naruto_classes`;",
            "INSERT INTO `naruto_classes` (`id`," + ",".join(f"`{c}`" for c in CAMPOS + CAMPOS_SJ) + ",`descricao`,`descricao_en`) VALUES"]
    out.append(",\n".join("(%d,%s,%s,%s)" % (c, ",".join([str(MODS[c][k]) for k in CAMPOS] + [str(SJ[c][k]) for k in CAMPOS_SJ]), q(DESC[c][0]), q(DESC[c][1])) for c in (1, 2, 3)) + ";")
    out.append("DELETE FROM `naruto_spells` WHERE id BETWEEN 56 AND 67;")
    out.append("INSERT INTO `naruto_spells` (`id`,`name`,`name_en`,`type`,`mp`,`attribute`,`elemento`,`golpes`,`roubovida`,`efeito`,`efeito_valor`,`efeito_turnos`,`efeito_chance`,`cooldown`,`nivelminimo`,`classe`,`graduacao`,`descricao`,`descricao_en`,`exclusivo_missao`) VALUES")
    vals = []
    for j in J:
        (i, nome, nen, tipo, mp, poder, el, golpes, roubo, ef, v, t, ch, cd, nv, cl, d, den) = j
        vals.append(f"({i},{q(nome)},{q(nen)},{tipo},{mp},{poder},{q(el)},{golpes},{roubo},{q(ef)},{v},{t},{ch},{cd},{onde[i]},{cl},'',{q(d)},{q(den)},0)")
    out.append(",\n".join(vals) + ";")
    cols = [f"{c}_{k}" for c in (1, 2, 3) for k in ("exp", "hp", "mp", "tp", "strength", "dexterity", "spells")]
    for r in novas:
        out.append("UPDATE `naruto_levels` SET " + ", ".join(f"`{k}`={r[k]}" for k in cols) + f" WHERE `id`={r['id']};")
    open(SAIDA, "w", encoding="utf8").write("\n".join(out) + "\n")
    print("ok", SAIDA, "jutsus em", onde)

if __name__ == "__main__":
    gerar()
