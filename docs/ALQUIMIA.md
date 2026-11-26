# Alquimia 2.0

A alquimia (`alquimia.php`, vilas com `towns.tem_alquimia = 1`: Montanha Myoboku e Vila da Areia) deixou de ser uma
lista fixa de 7 fusões e virou um sistema de receitas no banco, com nível de alquimia, chance de sucesso, refino,
infusão de variantes, poções, cópia de pergaminhos e receitas secretas. As 7 receitas de 2010 foram migradas
exatamente (mesmos itens, 100% de chance, grátis, nível 1, visíveis).

## Telas (abas)
- **Receitas conhecidas** — equipamentos, materiais e pergaminhos; livros de receitas da mochila com o botão *Ler*.
- **Experimentar** — a fusão livre de 2010 (marque 1 a 4 itens). Se a combinação for de uma receita, mesmo secreta,
  ela é descoberta e a tentativa é feita com as quantidades da receita. Combinação errada não custa nada.
- **Refinar** — 3 materiais iguais → 1 material de nível acima.
- **Infusão** — material raro + arma/colete/bandana/drop escolhido → chance de dar a variante rara (dourado, brilho,
  raio, chama, gelo, sombra; só variantes ativas em `naruto_variantes`). Na falha o item é mantido.
- **Poções** — poções, elixires, Incenso Repelente e Pergaminho de Retorno (empilháveis).

Cada receita mostra o resultado (com a caixa de explicação), os ingredientes com ícone e *tenho/preciso* (somando pilhas
e espaços repetidos da mochila de 8 espaços), chance, custo e requisitos. O topo mostra a barra do nível de alquimia.

## Regras
| Regra | Fórmula | Configuração |
|---|---|---|
| Chance | `chance_sucesso + 3 × (nível de alquimia − nivel_minimo)`, entre 5% e 95%; receitas de 100% nunca falham | — |
| Falha | perde `round(qtd × perda%)` de cada ingrediente; receitas de nível ≤ 5 nunca perdem tudo | `control.alquimiaperda` (50) |
| Custo | Ryou cobrado em toda tentativa; infusões somam o custo extra | `control.alquimiainfusaocusto` (1500) |
| Experiência | `(8 + 4 × nivel_minimo) × taxa%` no sucesso, 1/3 na falha; nível sobe a cada `50 × nível`, máximo 30 | `control.alquimiaexptaxa` (100) |
| Crítico | chance de sair o dobro (não vale para cópia/infusão), no máximo 1 vez por dia (`users.alquimia_critico` = AAAAMMDD) | `control.alquimiacritico` (3%) |
| Cópia | pergaminho em branco + materiais → +1 do pergaminho de jutsu escolhido (nunca jutsu `exclusivo_missao`) | — |

Um ingrediente casa com o espaço da mochila pelo **nome exato** e pelo tipo (como em 2010): itens refinados (`+N`) e
variantes (`:efeito`) não servem de ingrediente.

**Atomicidade:** toda ação abre uma transação, trava o jogador (`SELECT ... FOR UPDATE`) e confere o *estado* da
mochila (md5 de bp1..bp8 enviado pelo formulário); se mudou, nada acontece. Consumo, produção, Ryou e experiência são
gravados no mesmo `UPDATE` (com `sqlmochila()` conferindo os espaços). Testes em `tests/corrida.py` (`alquimia_*`).

## Banco
- `naruto_receitas`: `id, nome, nome_en, categoria (equipamento/material/pergaminho/refino/pocao/infusao), ingredientes`
  (JSON `[{"tipo":"item|drop|loja","id":12,"qtd":1}]`, até 4), `resultado_tipo (item/drop/loja/variante/copia), resultado_id,
  qtd, chance_sucesso, custo, nivel_minimo` (alquimia), `nivel_jogador, classe, graduacao, descoberta` (1 = secreta),
  `livro_drop` (drop que ensina), `ativa, ordem`.
- `naruto_users`: `alquimia_nivel, alquimia_exp, alquimia_receitas` ("3,18,"), `alquimia_critico`.
- `naruto_control`: `alquimiaperda, alquimiaexptaxa, alquimiacritico, alquimiainfusaocusto`.
- Drops novos 117–135 (materiais, materiais raros de infusão, Tônico/Elixires, 4 livros de receitas), todos empilháveis;
  os drops usados como ingrediente passaram a ser empilháveis. Livros e materiais raros entraram nas tabelas de drops
  de alguns inimigos (`naruto_monster_drops`). Gerado por `tools/alquimia.py` → `tools/alquimia.sql` (incluído pelo `build_sql.py`).

## Painel
`admin.php?do=receitas` / `editreceita:N`: lista e edição com seletor de ingredientes (itens, drops e consumíveis da
loja), resultado (inclui variantes e cópia), validação (ids existentes, sem repetidos, variante só em Infusão, faixas),
token CSRF, senha de novo para apagar e registro na auditoria. As regras gerais ficam em *Configurações gerais → Alquimia*.

## Receitas
| # | Receita | Ingredientes | Resultado | Chance | Ryou | Alq. | Secreta |
|---|---|---|---|---|---|---|---|
| 1 | Espadas Raiga | 1× Espadas de Rai, 1× Memória de Konoha | 1× Espadas Raiga | 100% | 0 | 1 |  |
| 2 | Protetor da Areia | 1× Protetor Branco, 1× Alma da Areia | 1× Protetor da Areia | 100% | 0 | 1 |  |
| 3 | Protetor da Névoa | 1× Protetor Branco, 1× Alma da Névoa | 1× Protetor da Névoa | 100% | 0 | 1 |  |
| 4 | Protetor do Som | 1× Protetor Branco, 1× Alma do Som | 1× Protetor do Som | 100% | 0 | 1 |  |
| 5 | Máscara Oinin | 1× Máscara ANBU, 1× Chakra da Brisa | 1× Máscara Oinin | 100% | 0 | 1 |  |
| 6 | Blusa Espírito da Névoa | 1× Blusa Simples, 1× Espírito da Névoa | 1× Blusa Espírito da Névoa | 100% | 0 | 1 |  |
| 7 | Protetor de Honra de Konoha | 1× Protetor de Konoha, 1× Destreza Súbita | 1× Protetor de Honra de Konoha | 100% | 0 | 1 |  |
| 8 | Colete de Couro Reforçado | 1× Blusa da Folha, 1× Escamas Reluzentes | 1× Colete de Couro Reforçado | 90% | 50 | 1 |  |
| 9 | Bolsa de Ferramentas | 2× Corda de Cânhamo, 1× Carretel de Seda, 2× Moeda de Bronze Antiga | 1× Bolsa de Ferramentas | 75% | 300 | 3 |  |
| 10 | Elmo de Ferro Batido | 1× Faixa de Testa Reforçada, 2× Liga de Aço Ninja | 1× Elmo de Ferro Batido | 70% | 700 | 5 |  |
| 11 | Garras de Ferro | 1× Luva Ninja, 2× Garra de Fera, 2× Minério de Ferro | 1× Garras de Ferro | 65% | 600 | 6 |  |
| 12 | Protetor de Honra da Névoa | 1× Protetor da Névoa, 1× Escama Endurecida, 1× Lágrima de Gelo | 1× Protetor de Honra da Névoa | 60% | 800 | 8 |  |
| 13 | Leque de Ferro Condutor | 1× Leque do Vento, 2× Liga de Aço Ninja, 1× Pedra-Trovão | 1× Leque de Ferro Condutor | 55% | 1500 | 10 |  |
| 14 | Samehada | 1× Espadas Raiga, 2× Escama de Serpente do Pântano, 1× Escama Endurecida | 1× Samehada | 45% | 3000 | 15 |  |
| 15 | Capa de Viagem das Sombras | 1× Túnica Camuflada, 1× Essência Sombria, 2× Carretel de Seda | 1× Capa de Viagem das Sombras | 50% | 2000 | 18 |  |
| 16 | Amuleto de Jade Errante | 1× Colar de Contas de Chakra, 1× Essência de Ervas, 1× Cristal Azul de Eco | 1× Amuleto de Jade Errante | 40% | 4000 | 22 | sim |
| 17 | Cota de Malha Negra | 1× Armadura Lamelar, 3× Liga de Aço Ninja, 1× Essência Sombria | 1× Cota de Malha Negra | 35% | 8000 | 25 | sim |
| 18 | Kunai Rúnica de Elite | 1× Kunai Explosiva, 1× Cristal Ressonante, 2× Papel Explosivo | 1× Kunai Rúnica de Elite | 30% | 6000 | 30 | sim (livro #133) |
| 19 | Refinar Fragmento de Vida | 3× Fragmento de Vida | 1× Fragmento Médio de Vida | 85% | 50 | 1 |  |
| 20 | Refinar Fragmento Médio de Vida | 3× Fragmento Médio de Vida | 1× Pedra da Resistência | 70% | 300 | 6 |  |
| 21 | Refinar Fragmento de Chakra | 3× Fragmento de Chakra | 1× Fragmento Médio de Chakra | 85% | 50 | 1 |  |
| 22 | Refinar Fragmento Médio de Chakra | 3× Fragmento Médio de Chakra | 1× Runa de Chakra | 70% | 300 | 6 |  |
| 23 | Liga de Aço Ninja | 3× Minério de Ferro | 1× Liga de Aço Ninja | 90% | 30 | 1 |  |
| 24 | Essência de Ervas | 3× Erva Medicinal Seca | 1× Essência de Ervas | 85% | 40 | 1 |  |
| 25 | Escama Endurecida | 3× Escamas Reluzentes | 1× Escama Endurecida | 75% | 150 | 4 |  |
| 26 | Pena de Tengu | 3× Pena Grande | 1× Pena de Tengu | 75% | 150 | 4 |  |
| 27 | Garra de Fera Alfa | 3× Garra de Fera | 1× Garra de Fera Alfa | 70% | 250 | 5 |  |
| 28 | Chakra Ventania | 3× Chakra da Brisa | 1× Chakra Ventania | 65% | 400 | 8 |  |
| 29 | Cristal Ressonante | 3× Cristal Azul de Eco | 1× Cristal Ressonante | 55% | 1500 | 14 |  |
| 30 | Poção Vermelha | 1× Erva Medicinal Seca, 1× Bolinho de Arroz | 2× Poção Vermelha | 95% | 10 | 1 |  |
| 31 | Poção Azul | 1× Erva Medicinal Seca, 1× Cogumelo Roxo | 2× Poção Azul | 95% | 10 | 1 |  |
| 32 | Tônico de Ervas | 1× Essência de Ervas, 1× Peixe Seco | 2× Tônico de Ervas | 85% | 60 | 3 |  |
| 33 | Incenso Repelente | 2× Erva Medicinal Seca, 1× Bomba de Fumaça | 2× Incenso Repelente | 85% | 50 | 2 |  |
| 34 | Pergaminho de Retorno | 1× Pergaminho em Branco, 1× Pena Grande | 2× Pergaminho de Retorno | 85% | 40 | 2 |  |
| 35 | Elixir de Chakra | 2× Cogumelo Roxo, 1× Essência de Ervas | 2× Elixir de Chakra | 80% | 80 | 5 |  |
| 36 | Elixir Dourado | 2× Tônico de Ervas, 1× Pó de Ouro | 1× Elixir Dourado | 60% | 400 | 12 |  |
| 37 | Grande Elixir de Chakra | 2× Elixir de Chakra, 1× Cristal Azul de Eco | 1× Grande Elixir de Chakra | 55% | 500 | 15 | sim (livro #132) |
| 38 | Pergaminho em Branco | 1× Carretel de Seda, 1× Lanterna de Papel | 2× Pergaminho em Branco | 90% | 30 | 1 |  |
| 39 | Pó de Ouro | 3× Moeda de Bronze Antiga, 1× Lingote de Prata | 1× Pó de Ouro | 60% | 300 | 6 |  |
| 40 | Fragmento Prismático | 1× Cristal Azul de Eco, 2× Escamas Reluzentes | 1× Fragmento Prismático | 55% | 400 | 8 |  |
| 41 | Pedra-Trovão | 3× Minério de Ferro, 1× Pó de Ferro Meteórico | 1× Pedra-Trovão | 55% | 400 | 8 |  |
| 42 | Lágrima de Gelo | 1× Cristal Azul de Eco, 1× Escama de Serpente do Pântano | 1× Lágrima de Gelo | 50% | 500 | 10 |  |
| 43 | Brasa Eterna | 2× Papel Explosivo, 1× Pó de Ferro Meteórico | 1× Brasa Eterna | 50% | 500 | 10 | sim (livro #135) |
| 44 | Essência Sombria | 3× Cogumelo Roxo, 1× Bomba de Fumaça | 1× Essência Sombria | 45% | 600 | 12 |  |
| 45 | Cópia de Pergaminho de Jutsu | 1× Pergaminho em Branco, 1× Pergaminho Selado, 1× Lanterna de Papel | cópia do pergaminho escolhido | 20% | 1500 | 10 | sim (livro #134) |
| 46 | Infusão Dourada | 2× Pó de Ouro, 1× Lingote de Prata | variante dourado | 25% | 1000 | 10 |  |
| 47 | Infusão Brilhante | 2× Fragmento Prismático | variante brilho | 25% | 1000 | 10 |  |
| 48 | Infusão Elétrica | 2× Pedra-Trovão | variante raio | 25% | 1000 | 10 |  |
| 49 | Infusão Flamejante | 2× Brasa Eterna | variante chama | 25% | 1200 | 12 |  |
| 50 | Infusão Congelada | 2× Lágrima de Gelo | variante gelo | 25% | 1200 | 12 |  |
| 51 | Infusão Sombria | 2× Essência Sombria | variante sombra | 20% | 1500 | 15 |  |