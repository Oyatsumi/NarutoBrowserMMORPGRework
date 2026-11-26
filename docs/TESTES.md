# Testes

Os testes ficam em `tests/` e usam só o que já existe no servidor de desenvolvimento (PHP CLI, Python 3 e,
para screenshots, Node + Playwright/Chromium). Nada disso é necessário para rodar o jogo.

## Preparação

1. Instale o jogo localmente (pode ser com o próprio `install/install.sh`) ou rode o servidor embutido do PHP:
   ```bash
   cd game && php -S 127.0.0.1:8080
   ```
2. No `game/config.php` coloque `"debug" => true` **apenas no ambiente de teste**: assim avisos e erros do PHP
   aparecem no HTML e os scripts conseguem detectá-los.

## Ferramentas

| Script | Para que serve |
|---|---|
| `tests/mkuser.php PASTA usuario [campo=valor ...]` | Cria/ajusta um jogador de teste direto no banco e imprime o cookie de login. Ex.: `php tests/mkuser.php game ninja1 level=30 currentaction=Fighting currentfight=1` |
| `tests/csrf.php PASTA usuario` | Imprime o token anti-CSRF do jogador (para testar formulários do painel). |
| `tests/fetch.py URL COOKIE pagina [...]` | Abre páginas como o jogador e lista avisos/erros do PHP e de SQL. Aceita `POST:pagina|campo=valor`. |
| `tests/bateria.py URL PASTA` | **Bateria completa** (abaixo). |
| `tests/equipar_teste.py URL PASTA` | Equipar pela mochila/desequipar: primeiro slot de drop vazio e depois anda, armas, pilhas (N -> N-1), mochila cheia, variantes, durabilidade por slot, atributos exatos após idas e voltas, 60 passos aleatórios sem duplicar/perder itens, cliques paralelos e marca do link. |
| `tests/corrida.py URL PASTA [N]` | Condições de corrida: N requisições simultâneas (banco, troca, alquimia, loja, venda de itens da mochila, compra de consumíveis, uso do Pergaminho de Retorno x venda) e conferência de que nada duplicou. |
| `tests/mochila_quadros.py images/bpslots.jpg` + `tests/mochila_medir.js URL COOKIE saida.png QUADROS` | Acha os 8 quadros 34x34 do fundo da mochila com PIL e mede, no navegador, se cada ícone (cheio ou vazio) está exatamente sobre o seu quadro. |
| `tests/shot.js URL saida.png [COOKIE]` | Screenshot de uma página (Playwright). |
| `tests/telas.js URL COOKIE PASTA lista.txt` | Screenshots de várias telas, com erros de JavaScript e imagens quebradas (404). |

## Bateria completa (`tests/bateria.py`)

```bash
python3 tests/bateria.py http://127.0.0.1:8080 game
```

1. Abre as páginas públicas sem login (inclusive arquivos que só deveriam ser incluídos).
2. Cria 7 jogadores em situações diferentes — novo, explorando, em batalha, nível 60 com tudo liberado, com itens
   na mochila/banco/equipados, na borda do mapa (lat 300) e administrador — e para cada um **segue todos os links
   do jogo** (uma vez por rota), procurando Warning/Fatal/Deprecated, erro de SQL, página em branco ou HTTP 500.
3. Abre todas as telas do painel do administrador, confere que um jogador comum não entra e que formulários sem
   token CSRF são recusados.
4. Fluxos com POST: pousada, compra, movimento, luta até o fim, vitória, banco, doação, mensagem privada, fórum,
   chat do mapa e chat global, distribuição de pontos.
5. Entradas maliciosas: aspas e SQL em IDs, ordenações e nomes, valores negativos, texto em campos numéricos,
   campos forjados (`usuariologadonome`), `<script>` em mensagens.

O limite de contas por IP e o formulário de login têm `tests/login_ip_teste.py [url] [pasta]` (limite, 4ª recusada, logout e
sessão parada liberam, configuração 0/1, corrida de 8 logins simultâneos, autocomplete do formulário); restaura `loginip_max`.
A durabilidade dos itens fortes tem `tests/durabilidade_teste.py [url] [pasta]` (instalação nova, compra, desgaste, aviso,
quebra com atributos de volta, reparo com corrida de 6 pedidos, chave liga/desliga); restaura `durabilidade_ativa`/`reparo_pct`.
O cadastro sem vila, a recuperação por código, a troca de dificuldade e o histórico têm `tests/cadastro_dificuldade_teste.py`.
A troca de vila natal tem `tests/troca_vila_teste.py` (recusas, fluxo completo pelos ganchos reais de vitória/posição,
aceite e entrega com 8 requisições paralelas, espera entre trocas, Pergaminho de Retorno/morte/ficha/histórico depois da
troca, painel e ajuda nos dois idiomas).

O duelo PvP tem um teste próprio de ponta a ponta com dois jogadores (pt e en): `tests/duelo_teste.py` (desafio,
recusa, aposta, aceite e ações com requisições paralelas, tempo esgotado, desistência, rank).

Resultado na versão 5.0 (PHP 8.3 + MariaDB 10.11): **245 requisições (pt) e 249 (en), 0 problemas**.

Além da bateria, cada parte do jogo foi testada a fundo durante a refatoração (história principal da etapa 1 à 22,
graduações, Senjutsu, Jutsu de Busca, trocas completas entre dois jogadores, duelos, alquimia, recuperação de
senha, bloqueio de login, upload de cartas no painel, instalador do zero com Apache) e com **requisições
paralelas** para garantir que não há duplicação de itens/Ryou/recompensas.

## Revisão visual

As telas principais foram capturadas com o Chromium (veja `docs/screenshots/`) e conferidas uma a uma:
alinhamento das colunas, painéis, tooltips, janelas flutuantes, popups (ficha, mapa global, chat) e o painel do
administrador. Nenhuma imagem quebrada nem erro de JavaScript.

> Alguns testes (durabilidade, viagem, imunidades, caveira) remontam o instalador com o `tools/build_sql.py` a partir do
> banco original de 2010 (`databaseName.sql` do repositório antigo). Aponte a variável `SQL_ORIGINAL` para esse arquivo,
> ou copie-o para `sql/original/databaseName.sql`.
