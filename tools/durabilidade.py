"""Gera tools/durabilidade.sql: durabilidade máxima (em vitórias) dos itens fortes.

Regra (docs/DURABILIDADE.md):
  - 0 = infinita. Itens fracos e de começo de jogo continuam infinitos.
  - Lojas (naruto_items), só o topo de cada tipo:
      arma   (type 1) com Poder de Ataque >= 100 ...... 300 + 2 x atributo
      colete (type 2) com Poder de Defesa >= 110 ...... 300 + 2 x atributo
      bandana(type 3) com Poder de Defesa >= 50 ....... 300 + 4 x atributo
  - Drops equipáveis (sem consumível, pergaminho, avatar; não empilháveis; com atributo):
      nível 45 a 99, ou os itens de compensação 420-425 ... 400 + 5 x nível
      (drops de missão/alquimia têm nível >= 100 e continuam infinitos).
  - Tudo arredondado para baixo em múltiplos de 50. Variantes raras usam a durabilidade do item base.
Uso: python3 tools/durabilidade.py   (lê o banco local `narutorpg` pelo socket e regrava tools/durabilidade.sql)
"""
import subprocess, os

def q(sql):
    out = subprocess.run(["mysql", "narutorpg", "-N", "--default-character-set=utf8mb4", "-e", sql], capture_output=True, text=True, check=True).stdout
    return [l.split("\t") for l in out.splitlines() if l.strip()]

def r50(x):
    return int(x) // 50 * 50

LIMITE = {1: 100, 2: 110, 3: 50}
FATOR = {1: 2, 2: 2, 3: 4}

itens = []
for id_, tipo, nome, attr in q("SELECT id,type,name,attribute FROM naruto_items ORDER BY type,attribute"):
    tipo, attr = int(tipo), int(attr)
    if tipo in LIMITE and attr >= LIMITE[tipo]:
        itens.append((int(id_), nome, r50(300 + FATOR[tipo] * attr)))

drops = []
for id_, nome, nivel in q("SELECT id,name,mlevel FROM naruto_drops WHERE consumivel='' AND ensina_jutsu=0 AND ensina_avatar=0 AND empilhavel=0 "
                          "AND attribute1<>'X' AND ((mlevel BETWEEN 45 AND 99) OR id BETWEEN 420 AND 425) ORDER BY mlevel,id"):
    drops.append((int(id_), nome, r50(400 + 5 * int(nivel))))

L = []
W = L.append
W("-- durabilidade.sql :: durabilidade máxima dos itens fortes (gerado por tools/durabilidade.py; regra em docs/DURABILIDADE.md).")
W("-- items/drops.durabilidade = vitórias até quebrar (0 = infinita). Itens que os jogadores JÁ têm não mudam (continuam \"X\").")
W("-- control.durabilidade_ativa (1 = vitórias gastam durabilidade) e control.reparo_pct (% do valor do item por um reparo completo).")
W("ALTER TABLE `naruto_items` ADD COLUMN IF NOT EXISTS `durabilidade` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vitórias até quebrar (0 = infinita)';")
W("ALTER TABLE `naruto_drops` ADD COLUMN IF NOT EXISTS `durabilidade` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'vitórias até quebrar (0 = infinita)';")
W("ALTER TABLE `naruto_control` ADD COLUMN IF NOT EXISTS `durabilidade_ativa` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = cada vitória gasta 1 de durabilidade dos itens finitos';")
W("ALTER TABLE `naruto_control` ADD COLUMN IF NOT EXISTS `reparo_pct` smallint(5) UNSIGNED NOT NULL DEFAULT 30 COMMENT '% do valor do item cobrado por um reparo completo (proporcional ao que falta)';")
for id_, nome, d in itens:
    W(f"UPDATE `naruto_items` SET `durabilidade`={d} WHERE `id`={id_}; -- {nome}")
for id_, nome, d in drops:
    W(f"UPDATE `naruto_drops` SET `durabilidade`={d} WHERE `id`={id_}; -- {nome}")
open(os.path.join(os.path.dirname(os.path.abspath(__file__)), "durabilidade.sql"), "w", encoding="utf8").write("\n".join(L) + "\n")
print(len(itens), "itens de loja e", len(drops), "drops com durabilidade")
