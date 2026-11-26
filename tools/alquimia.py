# alquimia.py :: gera tools/alquimia.sql (tabela receitas, colunas da alquimia em users/control, materiais novos 117-135,
# livros de receitas nas tabelas de drops dos inimigos) e os ícones 34x34 dos drops novos (layoutnovo/equipamentos/drops/).
# Uso: python3 alquimia.py [pasta do jogo]   (o build_sql.py inclui o alquimia.sql; aplique no banco ao vivo com mysql < alquimia.sql)
import sys, json, colorsys
from PIL import Image, ImageEnhance
JOGO = sys.argv[1] if len(sys.argv) > 1 else 'game'
EQ = JOGO + '/layoutnovo/equipamentos/'

# id, nome, nome_en, attribute1, attribute2, consumivel, ícone base, matiz (graus, None = mantém), saturação, brilho
DROPS = [
 (117, 'Pergaminho em Branco', 'Blank Scroll', 'X', 'X', '', 'novos/pergaminho_selado.gif', None, 0.15, 1.35),
 (118, 'Pó de Ouro', 'Gold Dust', 'X', 'X', '', 'novos/minerio_ferro.gif', 45, 1.8, 1.25),
 (119, 'Fragmento Prismático', 'Prismatic Shard', 'X', 'X', '', 'novos/cristal_azul.gif', 180, 1.6, 1.2),
 (120, 'Pedra-Trovão', 'Thunderstone', 'X', 'X', '', 'novos/minerio_ferro.gif', 225, 2.2, 1.15),
 (121, 'Brasa Eterna', 'Eternal Ember', 'X', 'X', '', 'novos/minerio_ferro.gif', 15, 2.5, 1.2),
 (122, 'Lágrima de Gelo', 'Ice Tear', 'X', 'X', '', 'novos/cristal_azul.gif', None, 0.5, 1.45),
 (123, 'Essência Sombria', 'Shadow Essence', 'X', 'X', '', 'novos/pocao_azul.gif', 280, 1.4, 0.8),
 (124, 'Tônico de Ervas', 'Herbal Tonic', 'X', 'X', 'hp,120', 'novos/pocao_verde.gif', None, 1.2, 1.0),
 (125, 'Elixir de Chakra', 'Chakra Elixir', 'X', 'X', 'mp,120', 'novos/pocao_azul.gif', 200, 1.5, 1.1),
 (126, 'Grande Elixir de Chakra', 'Greater Chakra Elixir', 'X', 'X', 'mp,300', 'novos/elixir_dourado.gif', 220, 1.3, 1.0),
 (127, 'Liga de Aço Ninja', 'Ninja Steel Alloy', 'defensepower,14', 'critico,1', '', 'novos/lingote_prata.gif', 210, 0.6, 0.9),
 (128, 'Escama Endurecida', 'Hardened Scale', 'defensepower,45', 'esquiva,1', '', 'novos/escamas.gif', 150, 1.4, 0.9),
 (129, 'Pena de Tengu', 'Tengu Feather', 'agilidade,30', 'esquiva,2', '', 'novos/pena_grande.gif', 0, 1.6, 0.85),
 (130, 'Essência de Ervas', 'Herbal Essence', 'regenhp,2', 'maxhp,15', '', 'novos/ervas.gif', 100, 1.6, 1.1),
 (131, 'Cristal Ressonante', 'Resonant Crystal', 'critico,5', 'esquiva,3', '', 'novos/cristal_azul.gif', 300, 1.6, 1.1),
 (132, 'Livro de Receitas: Elixires', 'Recipe Book: Elixirs', 'X', 'X', '', 'novos/pergaminho_selado.gif', 200, 1.3, 0.9),
 (133, 'Livro de Receitas: Forja Rúnica', 'Recipe Book: Runic Forging', 'X', 'X', '', 'novos/pergaminho_selado.gif', 0, 1.6, 0.9),
 (134, 'Livro de Receitas: Selos Copistas', 'Recipe Book: Copyist Seals', 'X', 'X', '', 'novos/pergaminho_selado.gif', 120, 1.3, 0.9),
 (135, 'Livro de Receitas: Chama Eterna', 'Recipe Book: Eternal Flame', 'X', 'X', '', 'novos/pergaminho_selado.gif', 20, 2.0, 1.0),
]

def icone(base, matiz, sat, brilho, destino):
    im = Image.open(EQ + base).convert('RGB')
    if matiz is not None:
        px = im.load()
        for y in range(im.height):
            for x in range(im.width):
                r, g, b = [c / 255 for c in px[x, y]]
                h, l, s = colorsys.rgb_to_hls(r, g, b)
                if s > 0.08 or l > 0.25:
                    r, g, b = colorsys.hls_to_rgb(matiz / 360, l, max(s, 0.35))
                px[x, y] = (int(r * 255), int(g * 255), int(b * 255))
    im = ImageEnhance.Color(im).enhance(sat)
    im = ImageEnhance.Brightness(im).enhance(brilho)
    im.convert('P', palette=Image.ADAPTIVE).save(destino)

def ing(*lista):  # ('drop'|'item'|'loja', id, qtd)
    return json.dumps([{"tipo": t, "id": i, "qtd": q} for t, i, q in lista], separators=(',', ':'))

D, I = 'drop', 'item'
# id, nome, nome_en, categoria, ingredientes, resultado_tipo, resultado_id, qtd, chance, custo, nível de alquimia, secreta, livro
R = [
 # Receitas antigas (receitasalquimia() de 2010): 100%, grátis, nível 1, visíveis.
 (1, 'Espadas Raiga', 'Raiga Swords', 'equipamento', ing((I,12,1),(D,31,1)), 'item', '34', 1, 100, 0, 1, 0, 0),
 (2, 'Protetor da Areia', 'Sand Headband', 'equipamento', ing((I,29,1),(D,36,1)), 'item', '36', 1, 100, 0, 1, 0, 0),
 (3, 'Protetor da Névoa', 'Mist Headband', 'equipamento', ing((I,29,1),(D,38,1)), 'item', '37', 1, 100, 0, 1, 0, 0),
 (4, 'Protetor do Som', 'Sound Headband', 'equipamento', ing((I,29,1),(D,37,1)), 'item', '38', 1, 100, 0, 1, 0, 0),
 (5, 'Máscara Oinin', 'Oinin Mask', 'equipamento', ing((I,31,1),(D,21,1)), 'item', '41', 1, 100, 0, 1, 0, 0),
 (6, 'Blusa Espírito da Névoa', 'Mist Spirit Shirt', 'equipamento', ing((I,17,1),(D,39,1)), 'item', '42', 1, 100, 0, 1, 0, 0),
 (7, 'Protetor de Honra de Konoha', 'Konoha Honor Headband', 'equipamento', ing((I,30,1),(D,46,1)), 'item', '43', 1, 100, 0, 1, 0, 0),
 # Equipamentos novos
 (8, 'Colete de Couro Reforçado', 'Reinforced Leather Vest', 'equipamento', ing((I,18,1),(D,73,1)), 'item', '55', 1, 90, 50, 1, 0, 0),
 (9, 'Bolsa de Ferramentas', 'Tool Pouch', 'equipamento', ing((D,85,2),(D,78,1),(D,79,2)), 'drop', '64', 1, 75, 300, 3, 0, 0),
 (10, 'Elmo de Ferro Batido', 'Hammered Iron Helm', 'equipamento', ing((I,61,1),(D,127,2)), 'item', '64', 1, 70, 700, 5, 0, 0),
 (11, 'Garras de Ferro', 'Iron Claws', 'equipamento', ing((I,2,1),(D,72,2),(D,75,2)), 'item', '51', 1, 65, 600, 6, 0, 0),
 (12, 'Protetor de Honra da Névoa', 'Mist Honor Headband', 'equipamento', ing((I,37,1),(D,128,1),(D,122,1)), 'item', '46', 1, 60, 800, 8, 0, 0),
 (13, 'Leque de Ferro Condutor', 'Conductive Iron Fan', 'equipamento', ing((I,10,1),(D,127,2),(D,120,1)), 'item', '52', 1, 55, 1500, 10, 0, 0),
 (14, 'Samehada', 'Samehada', 'equipamento', ing((I,34,1),(D,55,2),(D,128,1)), 'item', '14', 1, 45, 3000, 15, 0, 0),
 (15, 'Capa de Viagem das Sombras', 'Shadow Travel Cloak', 'equipamento', ing((I,79,1),(D,123,1),(D,78,2)), 'item', '59', 1, 50, 2000, 18, 0, 0),
 (16, 'Amuleto de Jade Errante', 'Wandering Jade Amulet', 'equipamento', ing((D,57,1),(D,130,1),(D,59,1)), 'drop', '60', 1, 40, 4000, 22, 1, 0),
 (17, 'Cota de Malha Negra', 'Black Chainmail', 'equipamento', ing((I,58,1),(D,127,3),(D,123,1)), 'item', '60', 1, 35, 8000, 25, 1, 0),
 (18, 'Kunai Rúnica de Elite', 'Elite Runic Kunai', 'equipamento', ing((I,8,1),(D,131,1),(D,86,2)), 'drop', '96', 1, 30, 6000, 30, 1, 133),
 # Refino: 3 iguais -> 1 do nível acima
 (19, 'Refinar Fragmento de Vida', 'Refine Life Fragment', 'refino', ing((D,1,3)), 'drop', '2', 1, 85, 50, 1, 0, 0),
 (20, 'Refinar Fragmento Médio de Vida', 'Refine Medium Life Fragment', 'refino', ing((D,2,3)), 'drop', '3', 1, 70, 300, 6, 0, 0),
 (21, 'Refinar Fragmento de Chakra', 'Refine Chakra Fragment', 'refino', ing((D,4,3)), 'drop', '5', 1, 85, 50, 1, 0, 0),
 (22, 'Refinar Fragmento Médio de Chakra', 'Refine Medium Chakra Fragment', 'refino', ing((D,5,3)), 'drop', '6', 1, 70, 300, 6, 0, 0),
 (23, 'Liga de Aço Ninja', 'Ninja Steel Alloy', 'refino', ing((D,75,3)), 'drop', '127', 1, 90, 30, 1, 0, 0),
 (24, 'Essência de Ervas', 'Herbal Essence', 'refino', ing((D,51,3)), 'drop', '130', 1, 85, 40, 1, 0, 0),
 (25, 'Escama Endurecida', 'Hardened Scale', 'refino', ing((D,73,3)), 'drop', '128', 1, 75, 150, 4, 0, 0),
 (26, 'Pena de Tengu', 'Tengu Feather', 'refino', ing((D,74,3)), 'drop', '129', 1, 75, 150, 4, 0, 0),
 (27, 'Garra de Fera Alfa', 'Alpha Beast Claw', 'refino', ing((D,72,3)), 'drop', '56', 1, 70, 250, 5, 0, 0),
 (28, 'Chakra Ventania', 'Gale Chakra', 'refino', ing((D,21,3)), 'drop', '22', 1, 65, 400, 8, 0, 0),
 (29, 'Cristal Ressonante', 'Resonant Crystal', 'refino', ing((D,59,3)), 'drop', '131', 1, 55, 1500, 14, 0, 0),
 # Poções e consumíveis (empilháveis)
 (30, 'Poção Vermelha', 'Red Potion', 'pocao', ing((D,51,1),(D,69,1)), 'drop', '66', 2, 95, 10, 1, 0, 0),
 (31, 'Poção Azul', 'Blue Potion', 'pocao', ing((D,51,1),(D,77,1)), 'drop', '67', 2, 95, 10, 1, 0, 0),
 (32, 'Tônico de Ervas', 'Herbal Tonic', 'pocao', ing((D,130,1),(D,70,1)), 'drop', '124', 2, 85, 60, 3, 0, 0),
 (33, 'Incenso Repelente', 'Repellent Incense', 'pocao', ing((D,51,2),(D,53,1)), 'loja', 'rep', 2, 85, 50, 2, 0, 0),
 (34, 'Pergaminho de Retorno', 'Return Scroll', 'pocao', ing((D,117,1),(D,74,1)), 'loja', 'ret', 2, 85, 40, 2, 0, 0),
 (35, 'Elixir de Chakra', 'Chakra Elixir', 'pocao', ing((D,77,2),(D,130,1)), 'drop', '125', 2, 80, 80, 5, 0, 0),
 (36, 'Elixir Dourado', 'Golden Elixir', 'pocao', ing((D,124,2),(D,118,1)), 'drop', '68', 1, 60, 400, 12, 0, 0),
 (37, 'Grande Elixir de Chakra', 'Greater Chakra Elixir', 'pocao', ing((D,125,2),(D,59,1)), 'drop', '126', 1, 55, 500, 15, 1, 132),
 # Materiais raros e pergaminhos
 (38, 'Pergaminho em Branco', 'Blank Scroll', 'material', ing((D,78,1),(D,83,1)), 'drop', '117', 2, 90, 30, 1, 0, 0),
 (39, 'Pó de Ouro', 'Gold Dust', 'material', ing((D,79,3),(D,76,1)), 'drop', '118', 1, 60, 300, 6, 0, 0),
 (40, 'Fragmento Prismático', 'Prismatic Shard', 'material', ing((D,59,1),(D,73,2)), 'drop', '119', 1, 55, 400, 8, 0, 0),
 (41, 'Pedra-Trovão', 'Thunderstone', 'material', ing((D,75,3),(D,52,1)), 'drop', '120', 1, 55, 400, 8, 0, 0),
 (42, 'Lágrima de Gelo', 'Ice Tear', 'material', ing((D,59,1),(D,55,1)), 'drop', '122', 1, 50, 500, 10, 0, 0),
 (43, 'Brasa Eterna', 'Eternal Ember', 'material', ing((D,86,2),(D,52,1)), 'drop', '121', 1, 50, 500, 10, 1, 135),
 (44, 'Essência Sombria', 'Shadow Essence', 'material', ing((D,77,3),(D,53,1)), 'drop', '123', 1, 45, 600, 12, 0, 0),
 (45, 'Cópia de Pergaminho de Jutsu', 'Jutsu Scroll Copy', 'pergaminho', ing((D,117,1),(D,54,1),(D,83,1)), 'copia', '', 1, 20, 1500, 10, 1, 134),
 # Infusão de variante rara (item escolhido + material raro)
 (46, 'Infusão Dourada', 'Golden Infusion', 'infusao', ing((D,118,2),(D,76,1)), 'variante', 'dourado', 1, 25, 1000, 10, 0, 0),
 (47, 'Infusão Brilhante', 'Shining Infusion', 'infusao', ing((D,119,2)), 'variante', 'brilho', 1, 25, 1000, 10, 0, 0),
 (48, 'Infusão Elétrica', 'Electric Infusion', 'infusao', ing((D,120,2)), 'variante', 'raio', 1, 25, 1000, 10, 0, 0),
 (49, 'Infusão Flamejante', 'Blazing Infusion', 'infusao', ing((D,121,2)), 'variante', 'chama', 1, 25, 1200, 12, 0, 0),
 (50, 'Infusão Congelada', 'Frozen Infusion', 'infusao', ing((D,122,2)), 'variante', 'gelo', 1, 25, 1200, 12, 0, 0),
 (51, 'Infusão Sombria', 'Shadow Infusion', 'infusao', ing((D,123,2)), 'variante', 'sombra', 1, 20, 1500, 15, 0, 0),
]
# Drops que viram materiais empilháveis (ingredientes) e os drops novos.
EMPILHAR = sorted({i["id"] for r in R for i in json.loads(r[4]) if i["tipo"] == 'drop'} | {d[0] for d in DROPS})
# Livros de receitas e materiais raros nas tabelas de drops: (inimigo, drop, chance %)
MONSTROS = [(43, 132, 1.5), (90, 133, 1.0), (192, 134, 1.0), (72, 135, 1.0),
            (172, 118, 2.0), (114, 119, 1.5), (200, 120, 2.0), (206, 121, 2.0), (193, 122, 2.0), (177, 123, 2.0)]

def q(x): return "'" + str(x).replace("\\", "\\\\").replace("'", "\\'") + "'"
L = ["-- alquimia.sql :: Alquimia 2.0 (gerado por tools/alquimia.py). Receitas no banco, nível de alquimia do jogador,",
     "-- materiais/poções/livros novos (drops 117-135) e configurações (control.alquimia*).",
     """CREATE TABLE IF NOT EXISTS `naruto_receitas` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(60) NOT NULL DEFAULT '',
  `nome_en` varchar(60) NOT NULL DEFAULT '',
  `categoria` varchar(15) NOT NULL DEFAULT 'equipamento',
  `ingredientes` text NOT NULL,
  `resultado_tipo` varchar(10) NOT NULL DEFAULT 'item',
  `resultado_id` varchar(20) NOT NULL DEFAULT '',
  `qtd` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `chance_sucesso` tinyint(3) UNSIGNED NOT NULL DEFAULT 100,
  `custo` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `nivel_minimo` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `nivel_jogador` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `classe` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `graduacao` varchar(30) NOT NULL DEFAULT '',
  `descoberta` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `livro_drop` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `ativa` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `ordem` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;""",
     "ALTER TABLE `naruto_users` ADD COLUMN IF NOT EXISTS `alquimia_nivel` smallint(5) UNSIGNED NOT NULL DEFAULT 1, ADD COLUMN IF NOT EXISTS `alquimia_exp` mediumint(8) UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN IF NOT EXISTS `alquimia_receitas` text NOT NULL DEFAULT '', ADD COLUMN IF NOT EXISTS `alquimia_critico` int(10) UNSIGNED NOT NULL DEFAULT 0;",
     "ALTER TABLE `naruto_control` ADD COLUMN IF NOT EXISTS `alquimiaperda` tinyint(3) UNSIGNED NOT NULL DEFAULT 50, ADD COLUMN IF NOT EXISTS `alquimiaexptaxa` smallint(5) UNSIGNED NOT NULL DEFAULT 100, ADD COLUMN IF NOT EXISTS `alquimiacritico` decimal(5,2) UNSIGNED NOT NULL DEFAULT 3.00, ADD COLUMN IF NOT EXISTS `alquimiainfusaocusto` mediumint(8) UNSIGNED NOT NULL DEFAULT 1500;",
     "DELETE FROM `naruto_drops` WHERE `id` BETWEEN 117 AND 135;",
     "INSERT INTO `naruto_drops` (`id`, `name`, `mlevel`, `type`, `attribute1`, `attribute2`, `attribute3`, `consumivel`, `ensina_jutsu`, `empilhavel`, `maxpilha`) VALUES"]
L.append(",\n".join(f"({d[0]}, {q(d[1])}, 100, 1, {q(d[3])}, {q(d[4])}, 'X', {q(d[5])}, 0, 1, 20)" for d in DROPS) + ";")
L.append("UPDATE `naruto_drops` SET `empilhavel`=1 WHERE `id` IN (" + ",".join(map(str, EMPILHAR)) + ");")
L.append("DELETE FROM `naruto_receitas`;")
L.append("INSERT INTO `naruto_receitas` (`id`, `nome`, `nome_en`, `categoria`, `ingredientes`, `resultado_tipo`, `resultado_id`, `qtd`, `chance_sucesso`, `custo`, `nivel_minimo`, `descoberta`, `livro_drop`, `ativa`, `ordem`) VALUES")
L.append(",\n".join(f"({r[0]}, {q(r[1])}, {q(r[2])}, {q(r[3])}, {q(r[4])}, {q(r[5])}, {q(r[6])}, {r[7]}, {r[8]}, {r[9]}, {r[10]}, {r[11]}, {r[12]}, 1, {r[0]})" for r in R) + ";")
L.append("DELETE FROM `naruto_monster_drops` WHERE `drop_id` BETWEEN 117 AND 135;")
L.append("INSERT INTO `naruto_monster_drops` (`monster_id`, `drop_id`, `chance_percent`, `chance_variante_percent`) VALUES")
L.append(",\n".join(f"({m}, {d}, {c:.2f}, 0.00)" for m, d, c in MONSTROS) + ";")
open(__file__.rsplit('/', 1)[0] + '/alquimia.sql', 'w', encoding='utf8').write("\n".join(L) + "\n")
for d in DROPS: icone(d[6], d[7], d[8], d[9], EQ + f'drops/{d[0]}.gif')
# Traduções dos nomes dos drops novos (para colar em lang/en/alquimia.php)
print("\n".join(f'    "{d[1]}" => "{d[2]}",' for d in DROPS))
print(len(R), 'receitas')
