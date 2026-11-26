# Troca de vila natal

A vila natal (`users.alinhamento` ↔ `towns.alinhamento`, só vilas com `vilanatal = 1`) não é mais escolhida no cadastro desde nov/2026: todo ninja
novo recebe a **vila inicial** (`control.vilainicial`, padrão Folha; veja [TROCA_DE_DIFICULDADE.md](TROCA_DE_DIFICULDADE.md)). Até
nov/2026 o jogador **não tinha como trocá-la**: o único caminho era o administrador mudar o código de alinhamento de uma
vila (`admin.php?do=editarvila`, que leva junto todos os jogadores do alinhamento). Agora existe a troca pelo lado do
jogador, de propósito difícil: uma missão de **transferência** com o embaixador da nova vila.

## Como funciona para o jogador

1. Perto de cada vila natal há um **embaixador** (NPC 32-36). Conversando com ele aparece um quadro com a vila natal
   atual e o tempo que falta para a próxima troca, e a missão **Transferência: Vila X** (177-181).
2. Requisitos para aceitar (padrão): **level 30, Chuunin**, a vila de destino não pode ser a vila natal atual (a
   transferência para ela nem aparece), nenhuma outra transferência em andamento e a **espera** desde a última troca
   cumprida (`control.trocavila_dias`, padrão 30 dias).
3. Tarefas (cadeia): derrotar inimigos que aparecem nos arredores da nova vila (dois tipos, 5 a 9 vitórias), visitar
   o marco da fronteira e voltar ao embaixador para pagar a **taxa de cidadania** (padrão 20.000 Ryou, cobrada na entrega).
4. Na entrega a vila natal muda na hora e a troca fica no histórico ("Você trocou de vila natal: A → B.").

| Missão | Embaixador (NPC, casa) | Vila de destino | Inimigos | Marco |
|---|---|---|---|---|
| 177 | Embaixador Tobirō (32, -4,-5) | Folha (1) | Lobo Rubro ×6, Tigre ×3 | 10,-10 |
| 178 | Embaixadora Iwame (33, 131,-74) | Pedra (3) | Jounnin Traidor ×3, ANBU Corrompido ×2 | 135,-80 |
| 179 | Embaixador Kirisame (34, 34,176) | Névoa (4) | Mariposa Venenosa Gigante ×3, Clone do Espadachim das Marés ×2 | 40,180 |
| 180 | Embaixadora Sunao (35, -136,-125) | Areia (5) | Escorpião Gigante das Areias ×4, Invocação da Areia ×2 | -140,-140 |
| 181 | Embaixador Raidō (36, 164,176) | Nuvem (6) | Fera Selada da Montanha ×3, Autômato de Combate ×2 | 180,180 |

Os inimigos foram escolhidos entre os que realmente aparecem a poucas casas de cada vila (faixa de nível da distância
do centro + terreno da casa, `fight.php :: escolhermonstro()`).

## O que depende da vila natal (e por que a troca fica coerente)

Tudo lê `users.alinhamento` na hora, por `vilanataldoalinhamento()` / `nomedoalinhamento()` / `vilasnatais()`
(`cidadesconteudo.php`). Nada guarda a vila natal em outro lugar, então trocar a coluna numa única gravação muda tudo junto:

| Onde | Arquivo | Efeito da troca |
|---|---|---|
| Volta ao morrer para um monstro | `fight.php` | nova vila |
| Volta ao perder no PvP do mapa | `pvp.php` | nova vila |
| Destravar personagem (GM) | `gm.php` | nova vila |
| Pergaminho de Retorno (destino e custo em TP) | `backpack.php`, `custotpretorno()` | nova vila |
| Pergaminho de Teleporte Aleatório (nunca leva à vila natal) | `backpack.php` | passa a evitar a nova vila |
| Emblema ao lado do nome (mapa, duelos, perfil) | `index.php`, `users.php` | `images/vila_CODIGO.gif` novo |
| Ficha pública e "Todos os Status" | `funcoesficha.php`, `templates/onlinechar.php`, `templates/showchar.php` | nova vila + "(desde dd/mm/aaaa)" |

Não dependem da vila natal: lojas, mapas, pousada, missões da história e das vilas, treinos, graduações e
especializações (todos usam a vila onde o jogador **está**, `towns.id`). Não existe ranking por vila nem regra de PvP por
alinhamento (o PvP do mapa vale para qualquer jogador), então nada disso precisa ser ajustado.

## Atomicidade

`mx_entregar()` (funcoesmissoesextras.php), quando a missão tem `config.troca_vila`:

1. Confere de novo, na entrega, se a troca ainda é permitida (vila válida, não é a atual, espera cumprida).
2. `START TRANSACTION` + `SELECT alinhamento, vilatroca_em ... FOR UPDATE` na linha do jogador (se mudou desde a leitura: `ROLLBACK`).
3. Marca a missão como concluída com `WHERE estado='andamento'` (só uma requisição consegue).
4. Um único `UPDATE` do jogador grava a taxa (`gold`), `alinhamento`, `vilatroca_em` e o histórico, com o ouro, a mochila,
   o banco, o alinhamento antigo e a `vilatroca_em` antiga conferidos no `WHERE`.
5. Qualquer falha → `ROLLBACK` (a missão volta a "andamento", nada é cobrado); sucesso → `COMMIT`.

O aceite usa a chave primária de `missoesextras_jogador` (`INSERT IGNORE`), e a "uma transferência por vez" é conferida no
aceite; a espera e o alinhamento antigo no `WHERE` impedem duas trocas mesmo com duas transferências entregues ao mesmo tempo.
Testado com 8 aceites e 8 entregas simultâneas (`tests/troca_vila_teste.py`).

## Restrições

- Destino só pode ser vila com `vilanatal = 1`, `alinhamento` preenchido e `secreta = 0` (`mx_vilaparatroca()`); o painel
  não deixa escolher outra e o jogo recusa se a vila deixar de cumprir isso depois.
- Se o admin trocar o código de alinhamento de uma vila, as missões continuam valendo: elas guardam o **id** da vila de
  destino, não o código.

## Administração

- **Configurações gerais → Troca de vila natal**: dias de espera entre duas trocas (`control.trocavila_dias`, 0 = sem espera).
- **Conteúdo do jogo → Missões**: cada transferência é uma missão opcional comum (cadeia) com o campo **Troca de vila natal**
  (vila de destino). Nível, graduação, inimigos, marco e taxa (etapa "Pagar Ryou") são editados ali; dá para criar
  novas transferências ou desativá-las. Salvar a missão mantém o destino. As mudanças ficam no registro de auditoria do painel,
  como as demais ações do admin.
- Cada troca também é gravada no registro de auditoria como `troca_vila` quando a página carregou o painel de segurança,
  e sempre no histórico do jogador.

## Banco de dados

- `users.vilatroca_em` (UNIX da última troca; 0 = nunca trocou).
- `control.trocavila_dias` (padrão 30).
- `npcs` 32-36 (embaixadores) e `missoesextras` 177-181 (`config` com `"troca_vila": id_da_vila`; repetíveis com
  intervalo de 1 hora, o limite real é a espera).
- Migração: `tools/troca_vila.sql` (incluída por `tools/build_sql.py`; pode ser rodada de novo num banco existente, não
  apaga o progresso dos jogadores).

## Arquivos

`funcoesmissoesextras.php` (`mx_trocavila*`, `mx_tempotexto`, aceite/lista/entrega), `admin_missoes.php` (campo de destino),
`admin.php` (espera), `funcoesficha.php`, `index.php` + `templates/showchar.php` (vila natal na ficha), `help.php`
("Trocar de Vila Natal"), `lang/en/troca_vila.php`, `tools/troca_vila.sql`, `tests/troca_vila_teste.py`.
