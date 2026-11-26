# PvP no mundo, caveiras, caveira exposta e vilas fechadas

Arquivos: `pvp.php` (ataque, luta automática, perdas, caveiras), `lib.php` (`caveiraativa()`, `caveiraminutos()`,
`caveiraexposta()`, `sqlcaveiraexposta()`, `bloqueiovila()`), `index.php` (listas do explorar), `explore.php`, `towns.php`,
`backpack.php` (vilas fechadas), `admin.php` (Configurações gerais → PvP no mundo), `help.php` (seção "PvP no Mundo e Caveiras"),
traduções em `lang/en/pvp_gm_seguranca.php` e `lang/en/pvp_caveira.php`. Banco: `tools/pvp_caveira.sql`. Teste: `tests/pvp_caveira_teste.py`.

## Padrões (instalação nova)
Os padrões ficam nos `DEFAULT` das colunas de `naruto_control` (`tools/build_sql.py` e `ALTER ... SET DEFAULT` em `tools/pvp_caveira.sql`).
Aplicar `pvp_caveira.sql` numa instalação existente **não** muda a linha de configuração: as escolhas do administrador ficam.

| Painel | Coluna | Padrão |
|---|---|---|
| PvP no mundo | `pvpmundo` | 1 (ligado) |
| Diferença máxima de nível | `pvpdiflevel` | 30 |
| Nível seguro (abaixo de) | `pvpnivelseguro` | 15 |
| Espera para atacar o mesmo jogador | `pvpesperaalvo` | 60 min |
| Espera entre ataques | `pvpesperaataque` | 2 min |
| Ryou perdido pelo derrotado | `pvpperdaryou` | 10% |
| Chance de perder item / até | `pvpchancedrop` / `pvpmaxitens` | 25% por item / 1 item |
| De onde saem os itens | `pvpondedrop` | 1 = mochila e itens equipados |
| Variantes raras | `pvpprotegervariantes` | 1 = nunca (protegidas) |
| Caveira branca | `pvpbrancahoras` / `pvpbrancachance` | 24 h / +25% de chance de perder item |
| Caveira vermelha | `pvpvermelhamortes` / `pvpvermelhadias` / `pvpvermelhabanco` | 3 mortes / 14 dias / banco de itens: não |
| Banimento automático | `pvpbanirdias` / `pvpbanirmetade` / `pvpbanirniveis` | 30 dias / perde metade dos itens: sim / perde 5 níveis na hora |
| Morte com caveira vermelha | `pvpvermelhaperdamin` / `pvpvermelhaperdamax` | perde de 1 a 5 níveis (sorteado; 0 a 0 = não perde) |
| Caveira exposta (novo) | `pvpcaveiraminutos` | 30 min (0 = desligado) |

### Perda de níveis (caveira vermelha)
Quem **mata de novo com caveira vermelha** perde `pvpbanirniveis` níveis (padrão 5) **antes** de ser banido; quem **morre no PvP
com caveira vermelha** perde um número sorteado entre `pvpvermelhaperdamin` e `pvpvermelhaperdamax` (padrão 1 a 5). A perda
(`pvp.php :: pvpperderniveis()`) desfaz os ganhos de cada level up: HP/Chakra/TP máximos, Força/Destreza e o Ataque/Defesa que elas
deram, +5 NP e +5 pontos de distribuição por nível (só os ainda livres) e o EP; a experiência fica no início do novo nível e o level
nunca fica abaixo de 1. Ajuste em **Configurações gerais → PvP no mundo**. Teste: `tests/pvp_niveis_teste.py`.


## Caveira exposta (atacável mesmo offline)
- Cada morte **injusta** (que dá ou renova a caveira branca/vermelha; legítima defesa não conta) grava `users.pvpmatouem = agora`.
- Enquanto a caveira estiver ativa **e** `pvpmatouem > agora - pvpcaveiraminutos`, o jogador está "exposto"
  (`caveiraexposta()` em PHP e `sqlcaveiraexposta()` em SQL usam a mesma regra). Com o PvP desligado ninguém fica exposto.
- Exposto e offline (último acesso há mais de 10 min), ele continua na **última casa do mapa**: aparece em "Jogadores nesta casa"
  e em "PvP: ninjas por perto" (marcado *offline, caveira exposta*) e pode ser atacado ali. Também vale se saiu no meio de uma luta
  contra um inimigo (`currentaction = Fighting`): a derrota limpa essa luta, como a morte no `fight.php`.
- A luta é a mesma luta automática do PvP (motor do duelo, com os atributos gravados do jogador). Todas as outras regras continuam
  (vila, nível, esperas, duelo, troca, HP > 1, distância).
- Atomicidade: igual ao PvP comum — uma transação, os dois jogadores travados (`FOR UPDATE`, id crescente), regras conferidas de
  novo depois da trava (inclusive a exposição, lida da linha travada). Vários atacantes ao mesmo tempo = 1 luta (o primeiro leva o
  derrotado para a vila e os outros são recusados); Ryou e itens nunca duplicam nem somem.
- Quando voltar, o jogador vê a janela "ATACADO!" (com a linha "Você estava offline, mas a sua caveira deixou você exposto no mapa"),
  o aviso no histórico e a luta em `pvp.php?ver=ID`; o registro da luta também diz que ele estava offline.

## Vilas fechadas para quem está exposto
Dentro das vilas continua proibido atacar. Mas durante a exposição (os mesmos `pvpcaveiraminutos` desde a última morte injusta)
o jogador **não entra em nenhuma vila**. A mensagem diz quantos minutos faltam (`bloqueiovila()`).

| Caminho | Comportamento |
|---|---|
| Andar (botões, "andar" automático, Passo Veloz) — `explore.php :: andarumacasa()` | Primeiro passo numa vila: não anda (nem ganha NP). Passo Veloz: para na casa de antes. O "andar" automático para. |
| Viajar Para / `index.php?do=gotown` — `towns.php :: travelto()` | Recusado sem cobrar TP/Ryou. A gravação também tem `AND NOT <exposta>` no `WHERE` (outra aba que ganhou caveira no meio). |
| Pergaminho de Retorno e de Teleporte Aleatório — `backpack.php` | Recusado e o pergaminho **não** é gasto. O Incenso continua funcionando. |
| Qualquer teleporte futuro para vila (ex.: doujutsu) | Deve chamar `bloqueiovila($userrow)` antes de mover; `travelto()` já confere. |
| **Morte** (PvE `fight.php`, PvP `pvp.php`) | **Não é barrada**: o derrotado sempre volta para a vila natal. Barrar deixaria o jogador sem HP preso no mapa, e a derrota já é a punição. Ele fica na vila, protegido. |
| Ações de GM/administrador ("destravar", mover) | Não são barradas (são correções da equipe). |

Depois do prazo a vila abre normalmente; a caveira branca/vermelha continua até o próprio prazo (24 h / 14 dias).

## Teste
`python3 tests/pvp_caveira_teste.py http://127.0.0.1:8080 game` — padrões numa instalação nova (gera o SQL com a mesma chamada do
`empacotar.sh` e carrega num banco temporário), migração reaplicável sem sobrescrever o admin, ajuda pt/en, caveira exposta offline nas
listas, ataque ao offline (perdas, Ryou conservado, aviso), fim da exposição, vilas fechadas (andar, Viajar Para, Retorno, Teleporte,
10 passos simultâneos), fim do bloqueio, caveira vermelha, tempo ajustável e 6 ataques simultâneos ao mesmo offline = 1 luta.
O `tests/pvp_gm_teste.py` continua cobrindo as regras antigas (e volta a configuração aos padrões no fim).
