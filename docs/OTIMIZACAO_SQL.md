# Otimização de SQL

Revisão do uso do banco nas páginas mais acessadas, **sem mudar o comportamento do jogo** (mesmo HTML gerado,
mesmas regras, mesmas escritas atômicas). Nada de transações, `SELECT ... FOR UPDATE`, `UPDATE` condicionais ou
código de segurança foi alterado.

## Como foi medido

- Número de queries por página: *general log* do MariaDB (`log_output=TABLE`), uma requisição por vez, contando as
  queries da conexão da página (o mesmo número aparece no rodapé para administradores/GMs: `{{numqueries}}`).
- Tempo: mediana de 20 requisições alternadas entre uma cópia do código original e o código novo, no mesmo banco
  e ao mesmo tempo (servidor embutido do PHP, máquina de desenvolvimento).
- Igualdade da saída: as páginas (pt e en) foram baixadas das duas versões e comparadas byte a byte (fora o
  tempo/queries do rodapé) com jogadores online em volta, GM, caveira de PvP, itens equipados com variante, etc.

## Resultado (jogador sem itens equipados)

| Página | Queries antes | Queries depois | Tempo antes | Tempo depois |
|---|---:|---:|---:|---:|
| index.php (na vila) | 99 | 20 | 44 ms | 13 ms |
| index.php (explorando) | 94 | 16 | 54 ms | 14 ms |
| Movimento (do=move, redireciona) | 9 | 8 | – | – |
| Luta – tela | 98 | 19 | – | – |
| Luta – ataque | 97 | 19 | – | – |
| Pousada (do=inn) | 95 | 17 | 47 ms | 14 ms |
| Loja (do=buy) | 126 | 18 | 62 ms | 15 ms |
| Fórum | 96 | 18 | 34 ms | 11 ms |
| Ranking (rank.php) | 94 | 16 | 40 ms | 12 ms |
| Painel – inimigos (admin.php?do=monsters) | 228 | 8 | 74 ms | 19 ms |
| Painel – usuários / itens / drops | 6 / 7 / 7 | iguais | – | – |

Com arma, colete, bandana e drops equipados, as 4–6 leituras dos itens equipados viram 2 (uma em `items`, uma em `drops`).

## Mudanças

| Arquivo | O que mudou |
|---|---|
| `templates/minimap.php` | O mini mapa 5x5 fazia 3 queries por casa (vila da casa + **duas vezes** a mesma lista de jogadores online): 75 por página. Agora são 2 queries para as 25 casas (vilas e jogadores online da área com `BETWEEN`), agrupadas em PHP por casa, na mesma ordem (vila de menor id; jogadores por nível decrescente). Só as colunas usadas são lidas. |
| `funcoesinclusas.php` | `linhadoitem()` aceita um 2º parâmetro opcional para receber uma linha já lida (só guarda no cache da requisição) e não consulta o banco para id ≤ 0 (espaço vazio). `conteudoexplic()` (tooltips) usa a linha do cache de `linhadoitem()` em vez de ler o item de novo. |
| `towns.php` | Na loja, as linhas do `SELECT ... IN()` da lista vão para o cache, então os tooltips não leem cada item outra vez (33 → 0 queries extras). |
| `templates/rightnav.php` | Level atual e próximo numa query só (`id IN (n, n+1)`); itens equipados lidos com um `IN()` em `items` e outro em `drops` e postos no cache dos tooltips. |
| `index.php` + `templates/towns.php` | O template da vila reaproveita a linha da vila que `dotown()` acabou de ler (`$GLOBALS["cidadeatualrow"]`). |
| `admin.php` | Lista de inimigos: os drops de todos os inimigos listados vêm de uma query (`admdropsdosinimigos()`, com o mesmo filtro e a mesma ordem) em vez de uma por inimigo (N+1). |
| `tools/otimizacao_indices.sql` (incluído pelo `tools/build_sql.py`) e banco | Índices novos, todos com `ADD INDEX IF NOT EXISTS`: `users(currentaction, lutaultimaacao)` (lutas abandonadas, roda em 1 de cada 20 páginas), `users(level, experience)` (ranking), `forum(parent, newpostdate)` e `forum(author, postdate)`, `monster_drops(drop_id)`, `drops(ensina_jutsu)`, `drops(ensina_avatar)`, `drops(name)`, `receitas(livro_drop)`, `missoesextras(recompensa_avatar)`. |

Já existiam (não foram duplicados): `users(username)`, `users(charname)`, `users(email)`, `users(latitude, longitude)`,
`users(onlinetime)`, `towns/npcs/chatmap(latitude, longitude)`, `duelos(desafiante|desafiado, estado)`,
`pvp(atacante|defensor, quando)`, `missoesextras_jogador(usuario_id, missao_id)` e `monster_drops(monster_id)`.

## O que ficou de fora (de propósito)

- A releitura do jogador no `display()` do `lib.php` ("algo pode ter mudado durante a execução") continua: ela é
  necessária para mostrar o estado depois das escritas da página.
- `UNIX_TIMESTAMP(onlinetime) >= ...` não usa o índice de `onlinetime`; trocar por `onlinetime >= FROM_UNIXTIME(...)`
  mudaria o resultado nas horas ambíguas do horário de verão, então ficou como estava.
- Leituras dentro de transações e com `FOR UPDATE` não foram tocadas.

## Testes

`tests/bateria.py` (pt e en): 0 problemas antes e depois. `tests/corrida.py` (servidor com
`PHP_CLI_SERVER_WORKERS=8`): 0 problemas. `tests/jutsus_especiais_teste.py`: nenhum problema.
