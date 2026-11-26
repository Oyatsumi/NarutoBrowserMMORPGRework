# Banco de dados

Todas as tabelas usam o prefixo do `config.php` (padrão `naruto_`), InnoDB e `utf8mb4`.
A estrutura completa e os dados iniciais estão em [`sql/naruto_rpg.sql`](../sql/naruto_rpg.sql).

| Tabela | Conteúdo |
|---|---|
| `control` | Configuração geral (uma linha, id = 1): nome, tamanho do mapa, jogo aberto/fechado, e-mail, especializações, dificuldades, cadastro aberto, avisos, Ryou inicial, chance de batalha, multiplicadores de exp/Ryou, nível mínimo de PvP, idioma padrão (`idiomapadrao`), multiplicador de venda dos itens da mochila nas lojas (`vendamochila`, %). |
| `users` | Jogadores (conta, senha, personagem e todo o estado do jogo). `batalha_timer2 = 5` marca quem está num duelo ativo; `duelos_vencidos`/`duelos_perdidos` são o placar de PvP. |
| `monsters` | Inimigos: HP, dano, defesa, nível, exp/Ryou máximos, imunidade (0 nenhuma, 1 dano, 2 dano+sono) e elemento. Opcionais: região (`regiao_*`) e `terrenos` (ex.: `deserto,rochoso`; vazio = qualquer terreno). |
| `items` | Itens das lojas: tipo (1 arma, 2 colete, 3 bandana), preço, atributo e especial `atributo,valor`. |
| `drops` | Itens que os inimigos soltam: nível mínimo e dois atributos `atributo,valor` (ou `X`). |
| `spells` | Jutsus: chakra, valor, tipo (1 cura, 2 dano, 3 sono, 4 +ataque, 5 +defesa, 6 suporte), elemento, `golpes`, `roubovida`, efeito extra (`efeito`, `efeito_valor`, `efeito_turnos`, `efeito_chance`), `cooldown`, requisitos (`nivelminimo`, `classe`, `graduacao`) e textos `name_en`/`descricao`/`descricao_en`. `drops.ensina_jutsu` = jutsu ensinado pelo pergaminho. |
| `levels` | Experiência total e ganhos por nível para as três especializações (`1_*`, `2_*`, `3_*`). |
| `towns` | Vilas: posição, preços da pousada e do mapa, TP para viajar, itens à venda, kage. |
| `news` | Notícias das vilas. |
| `babble` | Chat global. |
| `chatmap` | Chat de cada coordenada (últimas 10 falas por lugar). |
| `forum` | Fórum (tópicos têm `parent = 0`; respostas apontam para o tópico). |
| `duelos` | Duelos PvP: desafiante, desafiado, estado (`pendente`, `ativo`, `fim`, `recusado`, `cancelado`, `expirado`), aposta guardada de cada jogador, `vez` (id de quem joga), `prazo` do turno (UNIX), `turno`, bônus dos jutsus (`dano1/2`, `defesa1/2`), defesa (`guarda1/2`), tempos esgotados (`atrasos1/2`), `vencedor`, `registro` (últimas 8 ações no formato `i18n:`) e `jutsuestado` (efeitos de jutsu e recargas, JSON com lados `1`/`2`). Lado 1 = desafiante. |
| `loginfail` | Tentativas erradas de login por IP (bloqueio de 15 minutos após 5 erros). |

## Campos de `users` guardados como texto

| Campo | Formato | Exemplo |
|---|---|---|
| `spells` | IDs dos jutsus aprendidos | `0,1,6,11` |
| `jutsuestado` | efeitos de jutsu ativos e recargas da luta atual (JSON, apagado no fim da luta) | `{"efeitos":{"ini":{"veneno":{"v":6,"t":3,"n":"..."}}},"cd":{"jog":{"29":2}}}` |
| `towns` | IDs das vilas cujo mapa o jogador tem | `0,1,4` |
| `missao` | `etapa,progresso,meta,minutos` da história principal | `12,3,5,60` |
| `durabilidade` | `-,arma,colete,bandana,slot1,slot2,slot3` (`X` = vazio/infinita) | `-,40,X,X,12,X,X` |
| `bp1`..`bp8` (mochila, 8 espaços) | `nome,id,tipo,durabilidade` ou `None` (`tipo` 1-3 equipamento, 4 drop; ids de texto: `hp`/`mp` poções, `jutsu` pergaminho de jutsu, `ret` Pergaminho de Retorno, `rep` Incenso Repelente) | `Kunai+1,3,1,X` |
| `repelente_passos`, `repelente_ate` | Incenso Repelente: passos restantes e fim do efeito (UNIX); vale enquanto os dois durarem | `100`, `1793600000` |
| `mapadescoberto` | Neblina do mapa-múndi: 100 caracteres hexadecimais = 400 bits, um por quadrante 20x20 (k = linha*20 + coluna, linha 0 = norte; caractere k/4, bit 8>>(k%4)). Vazio = nada descoberto. `control.neblinaativa` (1/0) liga/desliga para todos | `00…0e0000e…` |
| `bancogeral` | itens guardados no banco, `nome,id,tipo,durabilidade;` repetido | `Shuriken,4,1,X;Pedra Sannin,20,4,X;` |
| `trocajogador1/2` | oferta feita / oferta recebida: `Nome;item;ryou;espaço` | `Sasuke;Kunai;100;2` |
| `elemento` | `natural,ataque` | `neutro,neutro` |
| `ultmonstro` | `último inimigo,vezes seguidas` | `Lobo,3` |
| `historico` | últimas 5 mensagens separadas por `;;` | |
| `caixadepm` | até 25 mensagens privadas separadas por `@#$%!;;;` | |
| `mainmsg` | janela flutuante: `imagem#$%;TÍTULO#$%;conteúdo`, ou `1` (jutsu de busca), `2,,nome` (enviar MP), `3` (senjutsu), `4` (fórum), ou `None` | |
| `senjutsutimer` / `missaoultdata` | datas em texto usadas pelos temporizadores | `1/11/2026,13:05:10,60` |
| `currentaction` | `In Town`, `Exploring` ou `Fighting` | |
| `authlevel` / `acesso` | 0 jogador, 1 administrador, 2 banido / 2 tutor, 3 GameMaster | |

## Itens: códigos especiais

O campo `special` (itens) e `attribute1/2` (drops) usam `atributo,valor`, e o atributo é uma **coluna de `users`**
que recebe o valor ao equipar (e perde ao desequipar). Atributos aceitos: `maxhp`, `maxmp`, `maxtp`, `maxnp`,
`maxep`, `goldbonus`, `expbonus`, `strength`, `dexterity`, `attackpower`, `defensepower`, `sorte`, `agilidade`,
`determinacao`, `precisao`, `inteligencia`, `droprate`. O painel recusa qualquer outro (eles viram nome de
coluna no SQL, então a lista branca é uma proteção).

Desde 2026 há `special2`/`special3` (itens) e `attribute3` (drops), os atributos `critico`, `esquiva`, `regenhp` e
`economiachakra` (colunas de `users`), `drops.consumivel` (`hp,N`/`mp,N`) e a tabela `naruto_monster_drops`
(`monster_id`, `drop_id`, `chance_percent`) — veja [ARQUITETURA.md](ARQUITETURA.md).

Durabilidade (`tools/durabilidade.sql`, [DURABILIDADE.md](DURABILIDADE.md)): `items.durabilidade` e `drops.durabilidade` =
vitórias até o item novo quebrar (0 = infinita, `X` no jogador); `control.durabilidade_ativa` (1/0) e `control.reparo_pct` (% do valor por um reparo completo).

Poções pequenas (`tools/pocoes_pequenas.sql`): `drops.consumivel` aceita também `tp,N`, `np,N` e `ep,N`; `drops.precoloja`
(preço na loja de consumíveis, 0 = não vendido) e `towns.consumiveislist` (ids de drops vendidos na loja daquela vila).
Drops 431–435 (Poção Vermelha/Azul/Amarela/Verde/Roxa Pequena), vendidos nas vilas 1–4 e caindo dos inimigos até o nível 10.


## Imagens ligadas ao banco

| Imagem | Origem |
|---|---|
| `layoutnovo/monstros/{id}.jpg` | carta do inimigo (295x145); sem ela usa `padrao.jpg` |
| `layoutnovo/equipamentos/{id}.gif` | ícone do item de loja (34x34) |
| `layoutnovo/equipamentos/drops/{id}.gif` | ícone do drop (34x34) |
| `images/24/cidade_{id}.gif` / `cidade_{id}p.gif` | ícone da vila conhecida / desconhecida (24x24) |
| `images/town_{id}.gif` | título da vila (300x25) |
| `images/meiocidade_{id}.jpg` | fundo da vila (487x284) |
| `layoutnovo/personagem/{id}.png` | retrato do kage (115x113) |
| `layoutnovo/avatares/{n}.jpg`, `layoutnovo/graficos/{n}_*.gif` | avatar e sprites do personagem |


## Pilhas, itens não vendáveis, troca e comprador distante (nov/2026)

- **Pilhas**: um espaço da mochila (e uma entrada do banco) pode ter um 5º campo com a quantidade:
  `Incenso Repelente,rep,100,X,5`. Sem o 5º campo a quantidade é 1 (textos antigos continuam válidos). Só itens
  com os 4 primeiros campos iguais se juntam. `items/drops.empilhavel` (0/1) e `maxpilha` (padrão 20) definem quem
  empilha; poções de drop (`hp`/`mp`, achadas pelo nome + `consumivel`) e pergaminhos de jutsu (`ensina_jutsu`) usam a
  linha de `drops`; Pergaminho de Retorno e Incenso Repelente usam `control.pilhaconsumiveis` (1 = não empilha).
  Funções em `funcoesinclusas.php`: `qtditem()`, `itemsemqtd()`, `itemcomqtd()`, `pilhamaxima()`, `mochilaguardar()`
  (junta em pilhas e usa espaços livres), `sqlmochila()` (SET + WHERE de conferência) e `iconecomqtd()` (número no
  canto do ícone). Usar, vender (com quantidade), morrer e beber no duelo consomem 1 unidade; comprar, pegar drop,
  desequipar, banco, troca, PvP e recompensas de missão juntam na pilha. A alquimia consome unidades das pilhas (ingredientes com quantidade).
- **`items/drops.naoVendavel`**: a loja da vila, o comprador de pergaminhos e a venda automática ao comprar outro
  equipamento recusam o item (nesse último caso ele vai para a mochila). Aparece como "Não vendável" no tooltip e na ajuda.
- **`users.trocaate`**: prazo (UNIX) da troca entre jogadores (10 min, renovado a cada proposta; 0 = sem prazo).
  O item oferecido pode levar a quantidade (parte de uma pilha). Depois do primeiro aceite as ofertas ficam travadas.
- **`npcs.papel`**: `comprador_pergaminhos` = NPC que abre a tela `index.php?do=comprador` (Eremita Kaizō, 40N,380W,
  nos cânions do extremo oeste). Paga `control.compradorremoto` % (padrão 200) do preço da vila; `control.compradorvila`
  = 0 fecha o comprador das vilas.

## Especializações
- **`classes`** (id 1 Ninjutsu, 2 Taijutsu, 3 Genjutsu): modificadores `dano_fisico`, `critico`, `dano_jutsu`, `dano_elemental`, `vantagem_elemento`, `defesa_fisica`, `chance_efeito`, `duracao_efeito`, `valor_efeito`, `dreno`, `resist_debuff`, `economia_tecnica`; bônus do Senjutsu ativo `sj_ataque`, `sj_defesa`, `sj_dano_fisico`, `sj_critico`, `sj_dano_elemental`, `sj_economia`, `sj_chance_efeito`, `sj_duracao_efeito`, `sj_resist_debuff`; `descricao`/`descricao_en`. Ver [ESPECIALIZACOES.md](ESPECIALIZACOES.md).
- `control.classenivel`: nível mínimo para escolher a especialização. `users.charclass`: 0 = sem especialização (padrão), 1-3 = classe.
- `users.atkdefsenjutsu`: só para contas antigas (bônus que a regra antiga somava em attackpower/defensepower); hoje fica `0,0`.


## Alquimia
`naruto_receitas` (receitas), `users.alquimia_nivel/alquimia_exp/alquimia_receitas/alquimia_critico` e `control.alquimiaperda/alquimiaexptaxa/alquimiacritico/alquimiainfusaocusto`. Veja [ALQUIMIA.md](ALQUIMIA.md).

## Avatares, doação e créditos (5.0)
- `naruto_avatares` (`id`, `nome`, `modo` = `livre`/`drop`/`missao`/`especial`, `ativo`, `ordem`): catálogo dos avatares
  (retrato `layoutnovo/avatares/{id}.jpg` + animações `layoutnovo/graficos/{id}_*.gif`). Não existem mais contas VIP
  (`users.acesso = 1` virou 0 e `towns.vip` foi removida).
- `users.avatares_liberados` = `"6,9,4"` (os livres e o avatar atual sempre contam); `users.avatar` agora começa em 6 (Naruto).
- `drops.ensina_avatar` = id do avatar liberado pelo Pergaminho de Disfarce (drops 301–308, não vendáveis, só pelas tabelas de drop).
- `missoesextras.recompensa_avatar` = avatar liberado na entrega da missão (se já tinha, 300 Ryou × nível mínimo).
- `control.linkdoacao` (botão de doação; vazio = escondido), `control.linkgithub` (repositório), `control.doacaodias`
  (intervalo da janela de doação do painel) e `users.doacaovista` (quando o admin viu a janela, UNIX).
- Gerado por `tools/avatares.sql` (incluído pelo `tools/build_sql.py`; pode ser rodado de novo num banco existente). Ícones dos
  pergaminhos: `tools/avatares.py`.

## Troca de vila natal
`users.vilatroca_em` (UNIX da última troca de vila natal, 0 = nunca), `control.trocavila_dias` (espera entre trocas, padrão 30),
embaixadores `npcs` 32-36 e transferências `missoesextras` 177-181 (`config` com `"troca_vila": id da vila`). Gerado por
`tools/troca_vila.sql`. Veja [TROCA_DE_VILA.md](TROCA_DE_VILA.md).

## Vila inicial, troca de dificuldade e histórico
`users.dificuldadetroca_em` (UNIX da última troca de dificuldade, 0 = nunca), `control.trocadif_dias` (padrão 14),
`control.vilainicial` (vila natal dos ninjas novos, padrão 1), `control.historicomax` (mensagens do histórico, padrão 12),
Mestre das Provações `npcs` 40 e provações `missoesextras` 190-192 (`"troca_dificuldade": 1..3`). Gerado por
`tools/dificuldade_historico.sql`. Veja [TROCA_DE_DIFICULDADE.md](TROCA_DE_DIFICULDADE.md).

## Imunidades dos inimigos (`naruto_monsters.imunidades`)

Lista separada por vírgula: `ninjutsu` (nenhum jutsu causa dano, nem queimadura/veneno), `taijutsu` (ataque físico causa 0),
`fogo`/`agua`/`terra`/`vento`/`raio` (só ninjutsu daquele elemento), `sono` (jutsus de sono e atordoar). A coluna antiga
`immune` (0, 1 = ninjutsu, 2 = ninjutsu + sono) continua em sincronia e é usada quando `imunidades` está vazia.
Nenhum inimigo pode ser imune a `taijutsu` e `ninjutsu` juntos (o painel recusa). Migração: `tools/imunidades.sql`
(também cria os inimigos 222-235). Funções: `imunidadesdoinimigo()`, `imunejutsu()`, `imunidadestexto()` em `funcoesjutsus.php`.
Teste: `tests/imunidades_teste.py`.
