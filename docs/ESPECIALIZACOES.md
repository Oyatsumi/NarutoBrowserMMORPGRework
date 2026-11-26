# Especializações (Ninjutsu, Taijutsu, Genjutsu) e Senjutsu por classe

## Como funciona
- Todo ninja novo começa **sem especialização** (`users.charclass = 0`, "Estudante"). O cadastro não pergunta mais a classe.
- Enquanto estudante, sobe de nível com as colunas `1_*` da tabela `levels` e só aprende jutsus **sem classe** (`spells.classe = 0`).
- A partir do nível `control.classenivel` (padrão 5), em **qualquer vila**, o ícone *Escolher Especialização* (quadro
  "Administrativos", só aparece para quem tem `charclass = 0`) abre `especializacao.php`: três quadros com descrição,
  pontos fortes/fracos (lidos da tabela `classes`), crescimento até o nível 80, jutsus exclusivos e o bônus do Senjutsu.
- Escolha em **duas etapas** (escolher → página de confirmação com aviso vermelho + caixa "Entendo que essa escolha é
  definitiva" + token CSRF). **A escolha é definitiva**: não há troca pelo jogador. Só o administrador pode corrigir em
  casos excepcionais (`admin.php?do=edituser`, opção 0-3; a ação fica no registro de auditoria e não recalcula status).
- Ao confirmar (`escolherclasse()`, transação + `FOR UPDATE` + `AND charclass='0'`): HP/CH/TP máximos, Força, Destreza e
  Poder de Ataque/Defesa recebem a diferença entre a classe nova e as colunas `1_*` do nível 2 ao atual (itens e
  atributos ficam intactos); o jogador aprende os jutsus que a tabela `levels` ensina à classe até o nível atual
  (exceto exclusivos de missão); grava no histórico (i18n).
- Jogadores antigos mantêm a classe que já tinham.

## Modificadores (tabela `classes`, editável em Admin > Especializações)
Aplicados em `funcoesjutsus.php` (`classemods()`, `classedojogador()`, `classeataquedefesa()`), usados por
`fight.php` (ataque físico, turno do inimigo, jutsus), `users.php :: dueloacao()` (duelo) e, por ele, `pvp.php`.
`custochakra($mp, $spell)` (funcoesinclusas.php) aplica a economia da classe/Senjutsu.

| Campo | Ninjutsu | Taijutsu | Genjutsu | Efeito |
|---|---|---|---|---|
| dano_fisico | 0 | +6 | +4 | % no dano do Atacar |
| critico | 0 | +8 | 0 | pontos de % de crítico físico |
| dano_jutsu | 0 | 0 | -3 | % em todo jutsu de dano |
| dano_elemental | +26 | 0 | 0 | % a mais em jutsu com elemento |
| vantagem_elemento | +65 | 0 | 0 | % a mais quando o elemento é super efetivo (só pesa no PvE: jogadores não têm elemento no duelo) |
| defesa_fisica | -5 | 0 | 0 | % a menos de dano físico recebido |
| chance_efeito | 0 | 0 | +37 | pontos de % na chance dos efeitos no alvo |
| duracao_efeito | 0 | 0 | +1 | turnos a mais em queimadura/veneno/debuffs |
| valor_efeito | 0 | 0 | +36 | % na força de queimadura/veneno/debuffs |
| dreno | 0 | 0 | +60 | % no Dreno de Chakra |
| resist_debuff | 0 | -20 | +21 | + = % a menos de chance de sofrer efeitos de jogadores; − = os efeitos sofridos ficam mais fortes (atordoamento: mais chance) |
| economia_tecnica | 0 | 25 | 0 | % de chakra a menos nas técnicas neutras (tipos 2/4/5/6) |

Crescimento (`levels`): as três classes usam **a mesma curva de experiência** (a antiga de Taijutsu); Taijutsu +1 HP a
cada 3 níveis, Genjutsu +1 HP a cada 4 e +1 CH a cada 4, Ninjutsu +1 CH a cada 3 (e perdeu o HP extra que tinha).
Força continua maior em Taijutsu e Destreza em Genjutsu.

Jutsus exclusivos novos (por nível, efeitos do sistema existente; nomes originais):
- Taijutsu: Punho de Pedra Viva (11), Investida do Javali Cinzento (24, atordoa 35%), Sequência dos Cem Passos (47, 4 golpes), Golpe da Fera Faminta (66, roubo de vida 25%).
- Ninjutsu: Lâminas de Vento Gêmeas (8), Pilar de Rocha Ascendente (26, defesa quebrada 11%), Serpente de Relâmpago (53, 3 golpes de 63 + atordoar), Cometa de Brasa Errante (68, poder 260, queimadura).
- Genjutsu: Eco da Sombra Lenta (9, poder 22, ataque enfraquecido), Labirinto de Espelhos Partidos (24, 18 CH, atordoa 2 turnos 55%), Pesadelo Rastejante (46, veneno), Coroa de Espinhos Ilusórios (66, defesa quebrada).

Jutsus da base remanejados por classe (`MOVER` no gerador; equilíbrio dos duelos dos níveis 10-45):

| Classe | Jutsu | Nível antes → depois |
|---|---|---|
| Ninjutsu | Escudo de Chakra | 14 → 10 |
| Ninjutsu | Pele de Casca | 10 → 13 |
| Ninjutsu | Konoha Senpuu | 12 → 21 |
| Ninjutsu | Lança de Brasa | 24 → 32 |
| Ninjutsu | Correntes de Areia | 27 → 33 |
| Ninjutsu | Jutsu de Revitalização | 45 → 52 |
| Taijutsu | Jutsu Medicinal de Batalha | 11 → 9 (Punho de Pedra Viva passa para o 11) |
| Genjutsu | Véu de Névoa Fria | 15 → 21 |
| Genjutsu | Toque da Erva Amarga (veneno) | 19 → 25 |
| Genjutsu | Sopro Restaurador | 20 → 28 |
| Genjutsu | Jutsu de Revitalização | 45 → 36 |

O `nivelminimo` dos jutsus de classe remanejados (Véu de Névoa Fria, Lança de Brasa) acompanha o nível novo.

Geração: `tools/classes_especializacao.py` → `tools/classes_especializacao.sql` (a partir de `tools/levels_base.tsv`), incluído pelo `build_sql.py`.

## Senjutsu por classe (colunas `sj_*`)
O bônus **não é mais somado** em `attackpower/defensepower`: é calculado na hora do combate a partir de
`senjutsuativo($row)` (NP atual = NP gravado − tempo/3 s), para o jogador e para o adversário de duelo/PvP. Sem classe: +20% Ataque/+20% Defesa (regra antiga).

| | Ninjutsu | Taijutsu | Genjutsu |
|---|---|---|---|
| Poder de Ataque / Defesa | +8% / +15% | +15% / +15% | 0 / +25% |
| Outros | +20% dano elemental, −20% Chakra em todos os jutsus | +10% dano físico, +5 crítico | +8% dano físico, +20 chance de efeitos, +1 turno, +25 resistência |

Ganho médio de vitórias no PvE com o Senjutsu ativo (simulação, média dos níveis 10/30/60/80): Ninjutsu +8,6 pp,
Taijutsu +7,8 pp, Genjutsu +7,4 pp. O bônus genérico antigo (+20%/+20% para todos) daria +11,2 / +7,7 / +10,7 — favorecia
Ninjutsu e Genjutsu no nível 80 (+22 e +16 pp) e quase nada para Taijutsu (+1 pp).

## Simulação (`tests/classes_sim.py`)
`[SIMDB=banco] python3 tests/classes_sim.py [--sem-mods] [--senjutsu|--senjutsu-generico] [--n 3000]`. Reproduz as
fórmulas de fight.php e dueloacao(); equipamento genérico igual para todos; PvE = sequências de 5 lutas contra inimigos do
nível de referência (10→10, 30→29, 60→45, 80→55); duelo = mesmo nível, IA de valor esperado igual para todos.

Rodada da redistribuição de jutsus (`--n 3000`, níveis 10/20/30/45/60/80; "antes" = modificadores e tabela levels da
primeira versão das especializações):

| PvE (% de lutas vencidas) | Antes N / T / G (spread) | Depois N / T / G (spread) |
|---|---|---|
| nível 10 | 61,3 / 65,3 / 63,3 (4,0) | 62,3 / 63,4 / 61,4 (2,0) |
| nível 20 | 60,3 / 66,7 / 70,8 (10,4) | 62,5 / 67,1 / 68,0 (5,6) |
| nível 30 | 61,2 / 61,0 / 56,2 (4,9) | 54,2 / 61,2 / 55,2 (7,0) |
| nível 45 | 61,4 / 63,5 / 67,9 (6,5) | 60,7 / 62,7 / 65,8 (5,1) |
| nível 60 | 58,2 / 57,6 / 60,5 (2,9) | 61,1 / 57,9 / 60,5 (3,2) |
| nível 80 | 50,6 / 55,7 / 53,6 (5,1) | 49,2 / 55,2 / 55,1 (6,0) |

| Duelos (% do 1º) | Antes N×T / N×G / T×G | Depois N×T / N×G / T×G |
|---|---|---|
| nível 10 | 30,5 / 18,4 / 26,6 | 60,1 / 44,5 / 42,9 |
| nível 20 | 63,7 / 0,7 / 3,4 | 57,2 / 53,0 / 54,7 |
| nível 30 | 85,4 / 78,3 / 66,0 | 43,1 / 47,2 / 59,4 |
| nível 45 | 84,9 / 89,2 / 71,3 | **65,4** / 44,1 / 53,3 |
| nível 60 | 39,9 / 56,6 / 51,7 | 51,8 / 57,1 / 51,7 |
| nível 80 | 67,4 / 53,4 / 49,9 | **64,3** / 59,2 / 52,6 |

Leitura: 15 dos 18 confrontos ficam entre 40% e 60% (antes, 4; o N×T do nível 10 fica no limite, 60,1%). Ninjutsu ainda leva vantagem sobre Taijutsu nos
níveis 45 e 80 (~65%). No PvE, cada classe fica a no máximo 4,3 pp da média do nível; entre a melhor e a pior a
diferença passa de 5 pp nos níveis 20/30/80 (Ninjutsu um pouco atrás no 30 e no 80).
Por que não dá para zerar só movendo jutsus: o PvE de Genjutsu vem quase todo da Destreza (o inimigo erra/bloqueia
mais) — tirar qualquer jutsu dele muda < 3 pp; por isso Genjutsu perdeu a defesa física extra (+10% → 0) e parte do
dano físico (+10% → +4%), e Ninjutsu ganhou vantagem elemental maior (+25% → +65%), que só vale contra inimigos.
O duelo da simulação é quase determinístico (mesma IA dos dois lados), então pequenas mudanças de custo/poder viram o
resultado de 10% para 90%: as mudanças foram escolhidas por busca local (mover jutsus ±3-10 níveis, ±15% de poder/custo
nos exclusivos) e conferidas com sementes diferentes; trate diferenças de ±5 pp como ruído.

Versão anterior (primeira rodada, níveis 10/30/60/80):

| PvE (% de lutas vencidas) | Antes N / T / G | Depois N / T / G |
|---|---|---|
| nível 10 | 56,2 / 58,7 / 55,8 | 61,3 / 65,3 / 63,3 |
| nível 30 | 54,9 / 53,2 / 48,2 | 59,1 / 61,1 / 57,4 |
| nível 60 | 55,1 / 51,7 / 40,5 | 58,3 / 58,6 / 60,7 |
| nível 80 | 35,9 / 52,3 / **6,5** | 49,1 / 56,2 / 53,7 |

| Duelos (% do 1º) | Antes N×T / N×G / T×G | Depois |
|---|---|---|
| nível 10 | 99,7 / 99,1 / 12,8 | 27,6 / 18,3 / 25,8 |
| nível 30 | 97,1 / 99,4 / 95,8 | 84,3 / 80,9 / 65,4 |
| nível 60 | 89,9 / 75,3 / 20,1 | 40,9 / 56,6 / 49,5 |
| nível 80 | 75,4 / 68,9 / 39,3 | 67,4 / 55,0 / 54,3 |

Leitura da primeira rodada: antes, Genjutsu era muito mais fraco no fim do jogo (6,5% contra os inimigos do nível 55 no nível 80, e subia de
nível 46% mais devagar) e Ninjutsu vencia quase todos os duelos. Depois, o PvE fica a ±5 pp na maioria dos pontos (nível 80: 7 pp)
e os duelos dos níveis 60/80 ficam perto de 50%. Os duelos de níveis baixos/médios ainda dependem de qual jutsu cada classe
aprende naquele nível (a tabela `levels`); o próximo ajuste fino é mover jutsus de nível, não os modificadores.
