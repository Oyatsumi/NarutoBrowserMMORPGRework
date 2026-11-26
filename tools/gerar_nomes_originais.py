#!/usr/bin/env python3
"""Gera tools/nomes_originais.sql a partir de tools/nomes_originais.py (NOMES: nome antigo -> (pt, en)).

A migração é re-executável: cada UPDATE só troca o nome quando o valor antigo ainda está lá.
Os ids dos inimigos ficam fixos em IDS (são os do banco original, os mesmos de uma instalação nova).
Também atualiza a chave de tradução em game/lang/en/conteudo_monstros_drops_jutsus.php (pt -> en).
Uso: python3 tools/gerar_nomes_originais.py [--lang]
"""
import os, re, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from nomes_originais import NOMES

AQUI = os.path.dirname(os.path.abspath(__file__))
LANG = 'game/lang/en/conteudo_monstros_drops_jutsus.php'

# nome antigo -> ids em naruto_monsters ("Kabuto #2" existe duas vezes)
IDS = {
    "Kabuto #1": [25], "Espi": [5], "Haku Ferido": [11], "Haku": [18], "Zaku Abumi": [21], "Kin Tsuchi": [22], "Dosu Kinuta": [23], "Shigure": [26], "Akadou Yoroi": [27], "Chikushodo": [31], "Kankuro": [45], "Mitarashi": [46], "Temari": [51], "Uchiha Renegado": [68], "Senju Renegado": [74], "Invocação da Akatsuki": [69], "Ningendo": [9], "Cobra Espiã de Orochimaru": [16], "Shinobi de Orochimaru": [53], "Espião de Orochimaru": [94], "Amaru": [117], "Gama Bunta": [119], "Manda": [120], "Katsuyu": [121], "Hidan &amp; Jashin": [141], "Uchiha Izuna": [150], "Kabuto #2": [66, 123], "Jiroubou": [77], "Jiroubou Selo 1": [78], "Jiroubou Selo 2": [79],
    "Sakon": [80], "Kidoumaru": [81], "Kidoumaru Selo 1": [82], "Kidoumaru Selo 2": [83], "Tayuya": [84],
    "Tayuya Selo 1": [85], "Tayuya Selo 2": [86], "Sakon Selo 1": [87], "Sakon Selo 2": [88],
    "Clone D'Água do Suigetsu": [89], "Kimimaru": [91], "Kimimaru Selo 1": [92], "Kimimaru Selo 2": [93],
    "Reibi Enfraquecido": [96], "Reibi": [97], "Ichibi Dominado": [98], "Shukaku": [99], "Nibi Suprimido": [100],
    "Nibi": [101], "Sanbi Suprimido": [102], "Sanbi": [103], "Sasori #1": [104], "Kisame Clone": [105],
    "Itachi Clone": [106], "Deidara": [108], "Gakido": [109], "Yonbi Suprimido": [110], "Yonbi": [111],
    "Sasuke Selo 2 - O Corrompido": [112], "Gobi Suprimido": [113], "Gobi": [114], "Kazekage Renascido": [116],
    "Hanzo da Salamandra": [122], "Sasori #2": [124], "Zetsu": [125], "Kyuubi 4 Caudas": [126],
    "Rokubi Suprimido": [127], "Rokubi": [128], "Itachi": [129], "Itachi Sharingan": [130], "Kisame": [131],
    "Jigokudo": [132], "Shichibi": [133], "Sasori #3": [134], "Suigetsu": [135], "Juugo": [136], "Hachibi": [137],
    "Itachi Mangekyou": [138], "Hidan": [139], "Kakuzu": [140], "Kakuzu #2": [142], "Orochimaru &amp; Manda": [143],
    "Konan": [144], "Shurado": [145], "Sasuke (S)": [146], "Nagato": [147], "Tendo": [148],
    "Pein - Os Seis Caminhos": [149], "Kyuubi": [151],
}

# Textos de missões que citavam os nomes antigos: (id, coluna, antigo, novo)
TEXTOS = [
    (13, 'titulo', 'Em Busca de Nibi!', 'Em Busca da Coruja Espectral!'),
    (13, 'titulo_en', 'In Search of Nibi!', 'In Search of the Spectral Owl!'),
    (16, 'titulo', 'Eliminando Pein #1!', 'Eliminando os Seis Corpos #1!'),
    (16, 'titulo_en', 'Taking Down Pain #1!', 'Taking Down the Six Bodies #1!'),
    (20, 'titulo', 'Eliminando Pein #2!', 'Eliminando os Seis Corpos #2!'),
    (20, 'titulo_en', 'Taking Down Pain #2!', 'Taking Down the Six Bodies #2!'),
]
TROCAS = [  # REPLACE dentro de textos (descrições e etapas em JSON)
    ('descricao', 'um dos corpos de Pein', 'um dos Seis Corpos'),
    ('descricao_en', 'one of the Paths of Pain', 'one of the Six Bodies'),
    ('config', 'Clones de Kisame atacam', 'Clones do Espadachim das Marés atacam'),
    ('config', 'Kisame clones attack', 'Tide Swordsman clones attack'),
]
# mesmas chaves em lang/en/missoes.php (pt antigo -> (pt novo, en novo))
LANG_MISSOES = {
    "Em Busca de Nibi!": ("Em Busca da Coruja Espectral!", "In Search of the Spectral Owl!"),
    "Eliminando Pein #1!": ("Eliminando os Seis Corpos #1!", "Taking Down the Six Bodies #1!"),
    "Eliminando Pein #2!": ("Eliminando os Seis Corpos #2!", "Taking Down the Six Bodies #2!"),
    "Derrotar <b>%s</b>, um dos corpos de Pein e vá imediatamente até a Vila da Areia.":
        ("Derrotar <b>%s</b>, um dos Seis Corpos e vá imediatamente até a Vila da Areia.", "Defeat <b>%s</b>, one of the Six Bodies, and go straight to the Sand Village."),
    "Derrotar <b>%s</b>, um dos corpos de Pein e vá imediatamente até a Vila da Folha.":
        ("Derrotar <b>%s</b>, um dos Seis Corpos e vá imediatamente até a Vila da Folha.", "Defeat <b>%s</b>, one of the Six Bodies, and go straight to the Leaf Village."),
}


def db(s):  # nomes no banco ficam com & escapado (como "Orochimaru &amp; Manda")
    return s.replace('&amp;', '&').replace('&', '&amp;')


def q(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"


def gerar():
    assert set(IDS) == set(NOMES), set(IDS) ^ set(NOMES)
    L = ["-- Nomes originais para os inimigos que tinham nome de personagem da obra.",
         "-- GERADO por tools/gerar_nomes_originais.py a partir de tools/nomes_originais.py; re-executável.",
         "SET NAMES utf8mb4;"]
    for velho, (pt, en) in NOMES.items():
        novo = db(pt)
        for i in IDS[velho]:
            L.append(f"UPDATE naruto_monsters SET name={q(novo)} WHERE id={i} AND name={q(velho)};")
        # missões que procuram o inimigo pelo nome e o último inimigo derrotado de cada jogador
        for c in ('alvo', 'descricao_arg', 'mapa_monstro'):
            L.append(f"UPDATE naruto_missoesextras SET {c}={q(novo)} WHERE {c}={q(velho)};")
        L.append(f"UPDATE naruto_users SET ultmonstro=CONCAT({q(novo)}, SUBSTRING(ultmonstro, {len(velho) + 1})) WHERE ultmonstro LIKE {q(velho.replace('%', '\\%').replace('_', '\\_') + ',%')};")
    for i, c, a, n in TEXTOS:
        L.append(f"UPDATE naruto_missoesextras SET {c}={q(n)} WHERE id={i} AND {c}={q(a)};")
    for c, a, n in TROCAS:
        L.append(f"UPDATE naruto_missoesextras SET {c}=REPLACE({c}, {q(a)}, {q(n)}) WHERE {c} LIKE {q('%' + a + '%')};")
    L.append("-- Missão 12 citava a personagem pelo nome.")
    L.append("UPDATE `naruto_missoesextras` SET `descricao`=REPLACE(`descricao`,'que Temari retorne','que a Kunoichi do Leque retorne'), `descricao_en`=REPLACE(`descricao_en`,'needs Temari to come home','needs the Fan Kunoichi to come home') WHERE `id`=12;")
    open(os.path.join(AQUI, 'nomes_originais.sql'), 'w', encoding='utf8').write('\n'.join(L) + '\n')
    print('ok', sum(len(v) for v in IDS.values()), 'inimigos')


def php(s):
    return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('$', '\\$') + '"'


def trocar_lang(arq, mapa):
    txt = open(arq, encoding='utf8').read()
    n = 0
    for velho, (pt, en) in mapa.items():
        r = re.compile(r'^(\s*)' + re.escape(php(velho)) + r'\s*=>\s*"(?:[^"\\]|\\.)*",', re.M)
        txt, k = r.subn(lambda m: m.group(1) + php(pt) + ' => ' + php(en) + ',', txt)
        n += k
    open(arq, 'w', encoding='utf8').write(txt)
    print(arq, n, 'chaves')


if __name__ == '__main__':
    gerar()
    if '--lang' in sys.argv:
        trocar_lang(LANG, {k: (db(pt), db(en)) for k, (pt, en) in NOMES.items()})
        trocar_lang(LANG.replace('conteudo_monstros_drops_jutsus', 'missoes'), LANG_MISSOES)
