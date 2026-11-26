"""Jutsus novos (ids 20-49), pergaminhos que ensinam jutsus (drops 102+) e onde eles caem.
Gera tools/jutsus_novos.sql, usado pelo build_sql.py (instalação) e aplicado no banco ao vivo.
Uso: python3 jutsus_novos.py            (lê naruto_monsters do banco local para escolher os inimigos)
Os inimigos escolhidos ficam gravados no SQL gerado, então a instalação não depende do banco local.
"""
import subprocess, json

# (id, nome, nome_en, tipo, mp, poder, elemento, golpes, roubovida, efeito, ef_valor, ef_turnos, ef_chance,
#  cooldown, nivel, classe, graduacao, aprendizado, descricao, descricao_en)
# tipo: 1 cura, 2 dano, 6 suporte. classe: 0 qualquer, 1 Ninjutsu, 2 Taijutsu, 3 Genjutsu.
# aprendizado: "nivel" (tabela levels, na coluna da classe; classe 0 = as três) ou "pergaminho".
J = [
 (20, "Faísca de Brasa", "Ember Spark", 2, 4, 12, "fogo", 1, 0, "queimadura", 3, 3, 60, 0, 3, 0, "", "nivel",
  "Um estalar de dedos solta uma faísca que gruda na roupa do inimigo e pode deixá-lo queimando por alguns turnos.",
  "A snap of the fingers throws a spark that clings to the enemy's clothes and may leave them burning for a few turns."),
 (21, "Agulhas de Orvalho", "Dewdrop Needles", 2, 6, 6, "agua", 3, 0, "", 0, 0, 100, 0, 6, 1, "", "nivel",
  "Gotas de orvalho endurecidas pelo chakra são disparadas em três rajadas rápidas.",
  "Dewdrops hardened by chakra are fired in three quick bursts."),
 (22, "Pele de Casca", "Bark Skin", 6, 8, 0, "terra", 1, 0, "escudo", 30, 3, 100, 3, 10, 0, "", "nivel",
  "A pele endurece como a casca de uma árvore velha e absorve os próximos golpes.",
  "Your skin hardens like the bark of an old tree and soaks up the next blows."),
 (23, "Passo do Vento Leve", "Light Wind Step", 6, 8, 0, "vento", 1, 0, "buff_esquiva", 20, 3, 100, 3, 13, 2, "", "nivel",
  "O corpo fica leve como uma folha ao vento, tornando mais fácil desviar dos ataques.",
  "Your body becomes as light as a leaf in the wind, making attacks easier to dodge."),
 (24, "Punho Faiscante", "Sparking Fist", 2, 10, 25, "raio", 1, 0, "atordoar", 0, 1, 30, 2, 16, 2, "", "nivel",
  "Um soco carregado de eletricidade que às vezes deixa o inimigo tremendo, sem conseguir atacar.",
  "An electrically charged punch that sometimes leaves the enemy twitching and unable to attack."),
 (25, "Véu de Névoa Fria", "Cold Mist Veil", 6, 12, 0, "agua", 1, 0, "debuff_ataque", 25, 3, 100, 3, 15, 3, "", "nivel",
  "Uma névoa gelada embaça os olhos do inimigo: os golpes dele perdem a força.",
  "A freezing mist clouds the enemy's eyes, and their blows lose their strength."),
 (26, "Toque da Erva Amarga", "Bitter Herb Touch", 2, 12, 10, "neutro", 1, 0, "veneno", 6, 4, 100, 0, 19, 0, "", "nivel",
  "As pontas dos dedos, cobertas de seiva de uma erva amarga, envenenam o alvo ao toque.",
  "Fingertips coated in the sap of a bitter herb poison the target on contact."),
 (27, "Sopro Restaurador", "Restoring Breath", 1, 15, 20, "neutro", 1, 0, "cura_turnos", 10, 3, 100, 2, 20, 0, "", "nivel",
  "Uma respiração profunda guia o chakra pelas feridas, que continuam fechando nos turnos seguintes.",
  "A deep breath guides chakra through your wounds, which keep closing over the next turns."),
 (28, "Lança de Brasa", "Ember Lance", 2, 20, 55, "fogo", 1, 0, "queimadura", 8, 3, 50, 0, 24, 1, "", "nivel",
  "Uma lança de brasas comprimidas atravessa o inimigo e pode deixá-lo em chamas.",
  "A lance of compressed embers pierces the enemy and may set them ablaze."),
 (29, "Correntes de Areia", "Sand Chains", 2, 22, 30, "terra", 1, 0, "atordoar", 0, 1, 50, 3, 27, 0, "", "nivel",
  "Correntes de areia brotam do chão, ferem e podem prender o inimigo por um turno.",
  "Chains of sand burst from the ground, wounding the enemy and possibly holding them for a turn."),
 (30, "Olhar que Pesa", "Weighing Gaze", 6, 18, 0, "neutro", 1, 0, "debuff_defesa", 25, 3, 100, 3, 30, 3, "", "nivel",
  "Uma ilusão faz o inimigo sentir o próprio corpo pesado e lento: ele recebe mais dano.",
  "An illusion makes the enemy feel heavy and sluggish, so they take more damage."),
 (31, "Golpe do Trovão Rasteiro", "Low Thunder Strike", 2, 26, 35, "raio", 2, 0, "", 0, 0, 100, 0, 33, 2, "", "nivel",
  "Duas rasteiras rápidas como um trovão que corre pelo chão.",
  "Two sweeping kicks as fast as thunder running along the ground."),
 (32, "Raiz Sedenta", "Thirsty Root", 2, 28, 50, "terra", 1, 40, "", 0, 0, 100, 1, 37, 0, "", "nivel",
  "Uma raiz sai da terra, perfura o inimigo e devolve a você parte da vida que bebeu.",
  "A root rises from the earth, pierces the enemy and returns part of the life it drank to you."),
 (33, "Foco do Falcão", "Falcon Focus", 6, 20, 0, "vento", 1, 0, "buff_critico", 25, 4, 100, 4, 42, 0, "", "nivel",
  "Você enxerga cada abertura na guarda do inimigo como um falcão vê a presa.",
  "You see every gap in the enemy's guard the way a falcon sees its prey."),
 (34, "Maré Ascendente", "Rising Tide", 2, 38, 95, "agua", 1, 0, "debuff_ataque", 15, 2, 100, 0, 48, 1, "", "nivel",
  "Uma onda sobe do nada, derruba o inimigo e o deixa encharcado e mais fraco.",
  "A wave rises out of nowhere, knocks the enemy down and leaves them soaked and weaker."),
 # ---- só por pergaminho (mais raros e mais fortes) ----
 (35, "Dança das Cinzas Rubras", "Crimson Ash Dance", 2, 14, 14, "fogo", 3, 0, "queimadura", 5, 3, 70, 1, 10, 0, "", "pergaminho",
  "Três giros envoltos em cinzas incandescentes que costumam deixar o alvo queimando.",
  "Three spins wrapped in glowing ash that usually leave the target burning."),
 (36, "Espinhos de Gelo Lento", "Slow Ice Thorns", 2, 14, 30, "agua", 1, 0, "debuff_defesa", 20, 3, 100, 1, 12, 1, "", "pergaminho",
  "Espinhos de gelo se cravam no inimigo e congelam as juntas, abrindo a guarda dele.",
  "Ice thorns sink into the enemy and freeze their joints, opening their guard."),
 (37, "Sussurro Entorpecente", "Numbing Whisper", 6, 20, 0, "neutro", 1, 0, "atordoar", 0, 2, 70, 5, 18, 3, "", "pergaminho",
  "Uma voz que só o inimigo ouve deixa os braços dele dormentes por dois turnos.",
  "A voice only the enemy can hear numbs their arms for two turns."),
 (38, "Muralha de Barro", "Mud Rampart", 6, 24, 0, "terra", 1, 0, "escudo", 90, 4, 100, 5, 22, 0, "", "pergaminho",
  "Uma muralha de barro se ergue à sua frente e segura uma boa quantidade de dano.",
  "A rampart of mud rises in front of you and holds back a good amount of damage."),
 (39, "Seiva Vital", "Vital Sap", 1, 30, 40, "agua", 1, 0, "cura_turnos", 20, 4, 100, 4, 26, 0, "", "pergaminho",
  "O chakra corre como seiva fresca: cura na hora e continua curando por quatro turnos.",
  "Chakra flows like fresh sap, healing at once and for four more turns."),
 (40, "Ventania Cortante", "Cutting Gale", 2, 30, 20, "vento", 4, 0, "", 0, 0, 100, 0, 30, 0, "", "pergaminho",
  "Quatro lâminas de vento cortam o inimigo de todas as direções.",
  "Four blades of wind slash the enemy from every direction."),
 (41, "Mordida da Lua Nova", "New Moon Bite", 2, 32, 70, "neutro", 1, 50, "", 0, 0, 100, 2, 33, 2, "", "pergaminho",
  "Um golpe noturno que arranca a força do inimigo e a transforma na sua vida.",
  "A night strike that tears away the enemy's strength and turns it into your life."),
 (42, "Tempestade de Agulhas Elétricas", "Electric Needle Storm", 2, 40, 30, "raio", 3, 0, "atordoar", 0, 1, 25, 2, 37, 1, "Genin", "pergaminho",
  "Três ondas de agulhas eletrificadas; a descarga pode paralisar o inimigo.",
  "Three waves of electrified needles; the discharge may paralyze the enemy."),
 (43, "Bruma do Esquecimento", "Mist of Forgetting", 2, 25, 40, "agua", 1, 0, "dreno_chakra", 35, 1, 100, 2, 40, 3, "", "pergaminho",
  "Uma bruma confunde o inimigo e devolve a você o chakra que ele deixou escapar.",
  "A mist confuses the enemy and gives you back the chakra they let slip."),
 (44, "Sangue de Ferro", "Iron Blood", 6, 30, 0, "neutro", 1, 0, "buff_defesa", 40, 4, 100, 5, 44, 2, "", "pergaminho",
  "Você endurece o corpo por dentro: os golpes do inimigo perdem boa parte do efeito.",
  "You harden your body from within, and the enemy's blows lose much of their effect."),
 (45, "Fúria do Vulcão Adormecido", "Fury of the Sleeping Volcano", 2, 60, 150, "fogo", 1, 0, "queimadura", 20, 3, 80, 3, 52, 1, "Chuunin", "pergaminho",
  "O chão se abre e cospe lava sobre o inimigo, que continua em chamas.",
  "The ground splits open and spits lava over the enemy, who keeps burning."),
 (46, "Mil Raízes Famintas", "Thousand Hungry Roots", 2, 65, 45, "terra", 3, 30, "", 0, 0, 100, 3, 58, 0, "Chuunin", "pergaminho",
  "Raízes famintas atacam três vezes e repartem com você a vida que sugaram.",
  "Hungry roots strike three times and share the life they drained with you."),
 (47, "Lamento da Serpente Pálida", "Pale Serpent’s Lament", 2, 50, 60, "neutro", 1, 0, "veneno", 30, 5, 100, 3, 63, 3, "", "pergaminho",
  "A ilusão de uma serpente pálida morde o inimigo, e o veneno imaginário dói de verdade.",
  "The illusion of a pale serpent bites the enemy, and the imaginary venom hurts for real."),
 (48, "Céu Rachado", "Cracked Sky", 2, 80, 200, "raio", 1, 0, "atordoar", 0, 1, 40, 4, 70, 0, "Chuunin", "pergaminho",
  "Um relâmpago racha o céu ao meio e cai sobre o inimigo, que pode ficar paralisado.",
  "A lightning bolt splits the sky in two and falls on the enemy, who may be left paralyzed."),
 (49, "Forma do Guerreiro Ancestral", "Ancestral Warrior Form", 6, 70, 0, "neutro", 1, 0, "buff_ataque", 50, 5, 100, 8, 76, 2, "Chuunin", "pergaminho",
  "Você desperta a postura de guerreiros antigos e bate muito mais forte por cinco turnos.",
  "You awaken the stance of ancient warriors and hit much harder for five turns."),
]

def q(x): return "'" + str(x).replace("\\", "\\\\").replace("'", "\\'") + "'"

def monstros():
    out = subprocess.run(["mysql", "-uroot", "--default-character-set=utf8mb4", "narutorpg", "-N", "-e",
                          "SELECT id, level, elemento, immune FROM naruto_monsters ORDER BY level, id"], capture_output=True, text=True).stdout
    return [(int(a), int(b), c, int(d)) for a, b, c, d in (l.split("\t") for l in out.strip().splitlines())]

def main():
    ms = monstros()
    sql = ["-- Jutsus novos (20-49), pergaminhos de jutsu (drops 102-116) e onde caem. Gerado por tools/jutsus_novos.py."]
    cols = "`id`, `name`, `mp`, `attribute`, `type`, `elemento`, `golpes`, `roubovida`, `efeito`, `efeito_valor`, `efeito_turnos`, `efeito_chance`, `cooldown`, `nivelminimo`, `classe`, `graduacao`, `name_en`, `descricao`, `descricao_en`"
    rows = []
    for j in J:
        (i, n, ne, tp, mp, pw, el, g, rv, ef, ev, et, ec, cd, nv, cl, gr, ap, d, de) = j
        rows.append("(" + ", ".join([str(i), q(n), str(mp), str(pw), str(tp), q(el), str(g), str(rv), q(ef), str(ev), str(et), str(ec), str(cd), str(nv), str(cl), q(gr), q(ne), q(d), q(de)]) + ")")
    sql.append(f"INSERT INTO `naruto_spells` ({cols}) VALUES\n" + ",\n".join(rows) + ";")
    # Aprendizado por nível: a coluna da classe (classe 0 = as três colunas) no nível do jutsu.
    for j in J:
        i, nv, cl, ap = j[0], j[14], j[15], j[17]
        if ap != "nivel": continue
        sets = ", ".join(f"`{c}_spells`={i}" for c in ([1, 2, 3] if cl == 0 else [cl]))
        sql.append(f"UPDATE `naruto_levels` SET {sets} WHERE `id`={nv};")
    # Jutsus antigos (1-19): nível mínimo = primeiro nível que os ensina (só para ordenar a ajuda e o painel; o
    # aprendizado por nível continua pela tabela levels).
    out = subprocess.run(["mysql", "-uroot", "narutorpg", "-N", "-e", "SELECT s.id, MIN(l.id) FROM naruto_spells s JOIN naruto_levels l ON (l.1_spells=s.id OR l.2_spells=s.id OR l.3_spells=s.id) WHERE s.id<20 GROUP BY s.id ORDER BY s.id"], capture_output=True, text=True).stdout
    for linha in out.strip().splitlines():
        i, nv = linha.split("\t")
        sql.append(f"UPDATE `naruto_spells` SET `nivelminimo`={nv} WHERE `id`={i};")
    # Pergaminhos: um drop por jutsu de pergaminho; caem de até 4 inimigos do elemento (ou quaisquer) perto do nível.
    drops, md = [], []
    did = 102
    for j in J:
        i, n, el, nv, ap = j[0], j[1], j[6], j[14], j[17]
        if ap != "pergaminho": continue
        lo, hi = max(1, nv - 2), nv + 10
        cand = [m for m in ms if lo <= m[1] <= hi and (m[2] == el or el == "neutro") and m[3] < 2]
        if len(cand) < 2: cand = [m for m in ms if lo <= m[1] <= hi and m[3] < 2]
        cand = sorted(cand, key=lambda m: (abs(m[1] - nv - 3), m[0]))[:4]
        chance = 0.5 if nv < 30 else (0.4 if nv < 55 else 0.3)
        drops.append(f"({did}, {q('Pergaminho: ' + n)}, {lo}, 1, 'X', 'X', 'X', '', {i})")
        for m in cand: md.append(f"({m[0]}, {did}, {chance:.2f}, 0.00)")
        did += 1
    sql.append("INSERT INTO `naruto_drops` (`id`, `name`, `mlevel`, `type`, `attribute1`, `attribute2`, `attribute3`, `consumivel`, `ensina_jutsu`) VALUES\n" + ",\n".join(drops) + ";")
    sql.append("INSERT INTO `naruto_monster_drops` (`monster_id`, `drop_id`, `chance_percent`, `chance_variante_percent`) VALUES\n" + ",\n".join(md) + ";")
    open("tools/jutsus_novos.sql", "w", encoding="utf8").write("\n".join(sql) + "\n")
    print("ok", len(J), "jutsus,", len(drops), "pergaminhos,", len(md), "linhas de drop")

if __name__ == "__main__":
    main()
