# TP, NP, EP e jutsus especiais

## Pontos
- **TP (Pontos de Viagem)**: combustível das viagens rápidas. Gasto em *Viajar Para* (por distância, ver abaixo), no Pergaminho de Retorno (`tpretornopct`% do TP até a vila natal, mínimo `tpretorno`), no Pergaminho de Teleporte Aleatório (`tpteleporte`) e no Passo Veloz: Cometa (1 TP por passo). Recupera na pousada, descansando na vila e com a sorte de batalha.
- **NP (Pontos Naturais)**: energia do Senjutsu. O modo ativo gasta 1 NP a cada 3 s; os jutsus de Senjutsu custam NP. Recupera 1 por passo no mapa, descansando e com a sorte de batalha (a pousada não recupera NP).
- **EP (Pontos de Elemento)**: cada jutsu de dano com elemento gasta `epcusto` EP e, com EP, recebe o bônus de atributo do elemento e +`epbonus`% de dano. Sem EP o jutsu funciona sem esses bônus. O máximo sobe 1 a cada `epnivel` níveis. Recupera na pousada, descansando e com a sorte de batalha.

Todos os valores ficam em *Configurações gerais* do painel.

**Sorte de batalha** (`fight.php`): após cada vitória, rand(1,10) escolhe a barra (1 = EP, 2 = TP, 3–4 = CH, 5–6 = HP, 7–10 = NP)
e recupera `base + floor(base × 2% × nível do inimigo)` (o EP não ganha essa parte) `+ floor(máximo da barra × P × rand(50,100)/100)`,
com base HP 1–20, CH 1–10, TP 1–15, NP 1–5, EP 1–2 e P = HP 5%, CH 5%, TP 6%, NP 4%, EP 5%; limitado ao máximo.
**Poções pequenas** (drops 431–435) recuperam TP, NP e EP pela mochila (`tp,8`, `np,10`, `ep,2`), além de HP e CH.

## Pergaminhos
- **Retorno**: volta à vila natal; custa TP (não é gasto se faltar TP).
- **Teleporte Aleatório** (drop 426, raro, inimigos nível 55–100): leva a uma vila aleatória (pode ser uma que o jogador ainda não conhece; vilas secretas não entram), nunca a atual nem a natal. Não funciona em batalha, duelo ou PvP.

## Jutsus especiais
- **Passo Veloz** (90 Pena: 2 casas/passo, Chakra; 91 Cometa: 3 casas/passo, Chakra + 1 TP). Desliga ao acabar os pontos ou ao entrar numa vila. Obtidos pelas missões 175/176.
- **Invocação de Suprimentos** (92–95, quatro níveis): invoca itens na mochila; pode falhar; custo de Chakra e recarga. Nível 4 só pela missão exclusiva 174. Tabela `naruto_suprimentos`, editável em `admin.php?do=suprimentos`.
- **Só com Senjutsu** (96–99): Rugido (atordoa), Chuva de Pétalas (rouba vida), Casca de Carvalho (escudo) e uma cura que custa NP. Missões 170–173.

## Itens com contrapartida
Drops 420–425: bônus alto com um atributo negativo (em vermelho com ▼ no tooltip). Não equipa se algum atributo ficar abaixo de zero.

## Tabelas e colunas
`naruto_spells`: `uso`, `acao_menu`, `requer_senjutsu`, `tier`, `custo_np`, `custo_tp`, `recarga_min`. `naruto_users`: `passo_jutsu`, `jutsumenu_cd`. `naruto_control`: `tpretorno`, `tpretornopct`, `tpteleporte`, `viagemryou`, `epcusto`, `epbonus`, `epnivel`, `tpdescanso`, `epdescanso`. Migração: `tools/pontos_jutsus_especiais.sql`.

## Custo do Viajar Para e Selo de Viagem

O *Viajar Para* (menu de cidades, `towns.php :: travelto()`) é bem mais caro que o Pergaminho de Retorno e cobra TP pela distância.
Fórmula em `funcoesinclusas.php :: custoviagem()`; todos os valores ficam em `naruto_control` (painel → Configurações gerais → "Custo do Viajar Para"):

- distância = `ceil(√(Δlat² + Δlon²))` da posição atual até a vila;
- TP = `towns.travelpoints` + `viagemtpbase` (10) + `ceil(distância × viagemtpdist (10) / 100)`;
- **vila natal**: TP = maior entre o TP acima e `viagemnataltppct` (300)% do TP do Pergaminho de Retorno, limitado ao TP máximo do jogador (com o TP cheio sempre dá para voltar); Ryou = maior entre TP × `viagemryou` (5) e `viagemnatalryoupct` (400)% do preço do Retorno (150 → 600 Ryou). Sem item;
- **outras vilas**: Ryou = `viagemoutraryou` (300) + TP × `viagemryou`, e gasta `viagemselos` (1) **Selo de Viagem** (drop `viagemseloitem` = 436).

Exemplos (padrões): Névoa → Folha (natal) 33 TP + 600 Ryou (Retorno: 5 TP + 150 Ryou); ao lado da Folha 16 TP + 600 Ryou;
Folha → Pedra 40 TP + 500 Ryou + 1 selo; Folha → Névoa 58 TP + 590 Ryou + 1 selo; Pedra → Névoa 66 TP + 630 Ryou + 1 selo.

O **Selo de Viagem** (drop 436, empilha até 10, vendável, não equipável) vem de: inimigos de nível 20 a 60 (0,8%, `naruto_monster_drops`),
receita de Alquimia "Selo de Viagem" (Pergaminho em Branco + 2 Pó de Ouro + Pena de Tengu, 400 Ryou, 75%, alquimia nível 6) e recompensa das
missões repetíveis 111 (Correntes da Costa, a cada 12 h) e 131 (Caçada Semanal). Ícone: `tools/selo_viagem.py`.

Clicar numa vila abre a tela de viagem com distância, TP, Ryou e selos (e o que falta); "Viajar" (`gotown:ID&confirmar=1`, com CSRF) cobra tudo
numa transação com o jogador travado (`FOR UPDATE`) e um único `UPDATE` (TP, Ryou e o espaço da mochila conferidos no `WHERE`): sem cobrança parcial
nem gasto duplo. A dica de cada vila na lista mostra o custo. O bloqueio da caveira exposta (`bloqueiovila()`) continua valendo antes de tudo.
Migração: `tools/viagem_custo.sql` (também muda `viagemryou` de 2 para 5 se o administrador não tinha mexido). Teste: `tests/viagem_custo_teste.py`.
