# Cadastro sem vila, troca de dificuldade e histórico maior

Três mudanças de nov/2026, todas na migração `tools/dificuldade_historico.sql` (incluída por `tools/build_sql.py`; pode ser
rodada de novo num banco existente). Teste: `tests/cadastro_dificuldade_teste.py`.

## 1. Cadastro sem escolha de vila natal

- O formulário (`users.php?do=register`, `templates/register.php`) **não pergunta mais a vila natal**: mostra só um aviso
  ("Todo ninja começa na Vila X...").
- Todo ninja novo recebe o alinhamento da **vila inicial** (`control.vilainicial`, padrão 1 = Vila da Folha, no centro do
  mapa, com a área de iniciantes) — `cidadesconteudo.php :: alinhamentoinicial()`. Um campo `alinhamento` enviado à mão é
  ignorado. Se a vila configurada deixar de ser vila natal comum (apagada, secreta, sem alinhamento), vale a regra antiga
  (`alinhamentodoregistro("")`, vila 1).
- Trocar de vila depois: só pelas transferências dos embaixadores ([TROCA_DE_VILA.md](TROCA_DE_VILA.md)).
- Admin: **Configurações gerais → Vila inicial** (lista só vilas natais com alinhamento e não secretas).

## 2. Recuperação: código x perguntas secretas

No cadastro e em `users.php?do=perguntas` (`camposmetodo()`), escolher **Código de recuperação** esconde e desliga os campos das
perguntas (JavaScript embutido, sem biblioteca). Sem JavaScript tudo aparece e o servidor **ignora** as perguntas quando
`metodo=codigo` (não exige nem grava). Com `metodo=perguntas` (ou sem método) as duas perguntas/respostas continuam obrigatórias.

## 3. Troca de dificuldade no meio do jogo

A dificuldade (`users.difficulty`, 1 a 3, escolhida no cadastro) agora pode ser trocada pelas **provações** do
**Mestre das Provações** (NPC 40, em -9,3, perto da Folha). Mesmo motor e mesma receita da troca de vila: missões opcionais
(cadeia) com `config "troca_dificuldade": 1..3`.

| Missão | Destino | Tarefa (padrão) | Oferenda |
|---|---|---|---|
| 190 Provação: Caminho Suave | dificuldade 1 | 3 vitórias seguidas contra inimigos de nível 10+ | 3.000 Ryou |
| 191 Provação: Caminho do Equilíbrio | dificuldade 2 | 4 vitórias seguidas, nível 14+ | 5.000 Ryou |
| 192 Provação: Caminho Árduo | dificuldade 3 | 5 vitórias seguidas, nível 18+ | 8.000 Ryou |

"Vitórias seguidas" é o passo `sobreviver` (descansar, se curar ou morrer zera a contagem); o nível mínimo do inimigo é o
"patamar" da dificuldade. Requisitos: **level 15**, não estar na dificuldade de destino (a provação nem aparece), uma provação
por vez, fora de luta e a **espera** desde a última troca (`control.trocadif_dias`, padrão 14 dias; `users.dificuldadetroca_em`).

### O que a dificuldade afeta e por que nada precisa ser recalculado

`fight.php` lê `users.difficulty` **na hora**: HP do inimigo no começo da luta, dano do inimigo a cada golpe e experiência/Ryou
da vitória (`control.diffNmod`). Nada fica guardado multiplicado, e a entrega é recusada no meio de uma luta (tanto pelo
`missoesextras.php` quanto pelo `WHERE currentaction<>'Fighting'` do UPDATE), então trocar a coluna basta: vale a partir da
próxima luta. Experiência e Ryou já ganhos não são refeitos.

### Atomicidade (`mx_entregar`)

1. Confere de novo na entrega (destino válido, não é a atual, espera cumprida, fora de luta).
2. `START TRANSACTION` + `SELECT difficulty, dificuldadetroca_em ... FOR UPDATE`; se mudou desde a leitura, `ROLLBACK`.
3. Marca a missão como concluída com `WHERE estado='andamento'` (só uma requisição consegue).
4. Um único `UPDATE` grava a oferenda (`gold`), `difficulty`, `dificuldadetroca_em` e o histórico, com o ouro, a mochila,
   o banco, a dificuldade antiga, a data da última troca e `currentaction<>'Fighting'` no `WHERE`.
5. Falha → `ROLLBACK`; sucesso → `COMMIT` + `registrarlog("troca_dificuldade", id, "nome: 1 -> 3 (missao 192)")`
   (`painelseguranca.php`) e a linha "Você trocou de dificuldade: A → B." no histórico.

Testado com 8 aceites e 8 entregas simultâneas.

### Onde aparece

- Quadro "Sua dificuldade atual" no Mestre (com o tempo para a próxima troca).
- Ficha **Todos os Status** (`index.php?do=showchar`): "Dificuldade: X (desde dd/mm/aaaa)".
- Ajuda: "Trocar de Dificuldade" (valores lidos do painel e das missões).

### Administração

- **Configurações gerais → Troca de dificuldade**: dias de espera (0 = sem espera).
- **Missões**: campo **Troca de dificuldade** (destino 1-3; uma missão não pode trocar vila e dificuldade ao mesmo tempo).
  Nível, inimigos, quantidade e oferenda são editados na própria missão.

## 4. Histórico maior

O quadro **Histórico** da coluna da esquerda (`historico.php`, `users.historico` TEXT separado por `;;`) guardava e mostrava as
5 últimas mensagens; agora são `control.historicomax` (padrão **12**, de 5 a 30, em Configurações gerais). Históricos antigos
continuam valendo (só são cortados quando passam do limite). O GM continua vendo até 15 (`gm.php`).

## Banco de dados

- `users.dificuldadetroca_em` (UNIX, 0 = nunca trocou).
- `control.trocadif_dias` (14), `control.vilainicial` (1), `control.historicomax` (12).
- `npcs` 40 e `missoesextras` 190-192.

## Arquivos

`users.php` (register, camposmetodo), `templates/register.php`, `cidadesconteudo.php` (`alinhamentoinicial`),
`funcoesmissoesextras.php` (`mx_trocadif*`, aceite/lista/entrega), `admin.php`, `admin_missoes.php`, `historico.php`,
`index.php` + `templates/showchar.php`, `help.php`, `lang/en/dificuldade.php` (+ `lang/en/ajuda.php`, `lang/en/troca_vila.php`),
`tools/dificuldade_historico.sql`, `tests/cadastro_dificuldade_teste.py`.
