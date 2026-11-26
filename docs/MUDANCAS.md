# O que mudou nas versões 5.0 e 4.0 (2026)

## Instaladores (5.1)
- Dois instaladores: **`install/instalar.php`** (pelo navegador, para hospedagens sem SSH: confere o PHP, pede uma chave
  gravada em `instalar_chave.php`, importa o banco, grava o `config.php`, cria o administrador e se trava com `instalar.lock`)
  e **`install/install.sh`** (VM/servidor com SSH e root). Guias: [HOSPEDAGEM.md](HOSPEDAGEM.md) e [INSTALACAO.md](INSTALACAO.md).

## Versão 5.0 — resumo
A 5.0 junta tudo o que foi feito depois da 4.0 (detalhes em cada seção abaixo):
- **Mundo**: mapa de 400 casas com conteúdo de fim de jogo, inimigos por terreno, neblina de guerra no mapa-múndi e vilas 100% no banco.
- **Combate e itens**: bônus novos (crítico, esquiva, regeneração, economia de chakra; até 3 por item), drops por inimigo,
  variantes raras (✦), pilhas, mochila de 8 espaços, consumíveis da loja, comprador distante e morte proporcional ao nível.
- **Progressão**: especializações escolhidas no jogo (com Senjutsu por classe), jutsus configuráveis + 30 novos e pergaminhos,
  jutsus exclusivos de missão, Alquimia 2.0 e missões no banco (cadeia, viagem, coleta, sobrevivência, escolha, diárias/semanais).
- **PvP**: duelos refeitos, PvP no mundo com caveiras, GameMasters com painel próprio e painéis com re-autenticação e auditoria.
- **Avatares sem VIP**: o conceito de conta VIP/premium foi removido (o banco guarda 30 itens para todos; a coluna `towns.vip`
  saiu). Avatar = retrato + animações; 3 livres (Naruto, Sakura, Shikamaru), 8 liberados por **Pergaminhos de Disfarce**
  (drops raros 301–308, 0,25–1% em inimigos de cada faixa de nível), 6 por **missões** (novas 160–162 e as difíceis 134, 144, 145)
  e o 16 pelo Senjutsu. Tabela `naruto_avatares` (modo por avatar, editável em `admin_avatares.php`, que também dá um avatar a um
  jogador com registro na auditoria), `users.avatares_liberados`, `drops.ensina_avatar`, `missoesextras.recompensa_avatar`.
  Quem já jogava mantém o avatar atual.
- **Perfil público** (`mostrarchar.php` e `index.php?do=onlinechar`) com a mesma ficha (`funcoesficha.php`): vila natal,
  graduação (inclui Jounin), especialização (ou nenhuma), atributos novos, duelos, mapa descoberto, situação no PvP, cargo/caveira
  e equipamentos com variantes — sem e-mail, banco, conta ou dados de recuperação.
- **Créditos e doação**: rodapé com "Ajude os desenvolvedores doando" → `creditos.php` (créditos, repositório e botão de doação do
  Stripe, `control.linkdoacao` / `control.linkgithub`); janela de doação no painel do admin a cada `control.doacaodias` dias
  (`users.doacaovista`). O tempo da página e o número de queries no rodapé só aparecem para administradores e GMs.
- **Idiomas**: tudo em português e inglês. **Versão** `5.0` (`$version` no lib.php).

## Equipar pela mochila e desequipar (ordem dos slots de drop)

- **Drop equipado pela mochila vai para o primeiro slot de drop vazio** (slot1, slot2, slot3) e nada sai. Só com os
  3 cheios os slots andam (slot1 -> slot2 -> slot3), o novo entra no slot1 e o do slot3 (o mais antigo) volta para a
  mochila, no lugar do item usado. Antes o drop sempre empurrava os slots: com `[vazio, A, B]` o B saía sem necessidade
  (parecia que "saía mais de um item" e a ordem ficava estranha).
- **Clique duplo**: o link da mochila leva a marca do item (`backpack.php?qual=N&v=marcaitem()`); se o espaço já tem
  outro item (o que acabou de sair do equipamento), o segundo clique não faz nada. Antes ele equipava de volta o item
  que tinha saído, empurrando os slots de novo e tirando outro drop.
- Drop que volta para a mochila (equipar ou desequipar) fica com o tipo 4, como os drops de batalha/loja; antes ia com
  5/6 e não empilhava com os iguais. Um item empilhável que sai junta numa pilha igual em vez de ficar solto.
- Teste: `tests/equipar_teste.py URL PASTA`.

## Sorte de batalha e poções pequenas
- **Sorte de batalha** (`fight.php`, vitória normal): as probabilidades continuam (NP 40%, HP 20%, CH 20%, TP 10%, EP 10%).
  Valor = base (HP 1–20, CH 1–10, TP 1–15, NP 1–5, EP 1–2) + `floor(base × 2% × nível do inimigo)` (menos no EP, como antes)
  **+ `floor(máximo da barra × P × rand(50,100)/100)`**, com P = HP 5%, CH 5%, TP 6%, NP 4%, EP 5%; nunca passa do máximo.
  Nos primeiros níveis a parte nova dá 0–2 pontos; no nível 100 (barras típicas) a média vai de 31→55 HP, 16→31 CH,
  24→38 TP, 9→23 NP e 1,5→3 EP. Explicada na ajuda (Batalhas), em português e inglês.
- **Poções pequenas** (drops 431–435, empilháveis até 20): Poção Vermelha Pequena (`hp,20`, 15 Ryou), Azul (`mp,15`, 12),
  Amarela (`tp,8`, 10), Verde (`np,10`, 10) e Roxa (`ep,2`, 12). Vendidas na tela *Consumíveis e venda* da Folha, Myoboku,
  Pedra e Névoa (`towns.consumiveislist` + `drops.precoloja`, com quantidade) e caindo dos inimigos até o nível 10
  (HP 2,5%, CH 2% e TP/NP/EP 1,5% em rodízio). Usadas pela mochila (`backpack.php`), também no meio da luta, como as outras
  poções. Ícones próprios em `layoutnovo/equipamentos/drops/43x.gif` e `consumiveis/43x.gif` (`tools/pocoes_pequenas.py`).

## Alquimia 2.0
- Receitas no banco (`naruto_receitas`, 51 receitas; as 7 antigas migradas iguais), até 4 ingredientes com quantidade (usa pilhas),
  nível/experiência de alquimia, chance de sucesso com perda parcial na falha, refino 3→1, infusão de variantes raras,
  poções/consumíveis, cópia de pergaminhos de jutsu, receitas secretas (livros de receitas ou experimentando) e crítico diário.
  Abas na tela da alquimia (pt/en), CRUD no painel (`admin.php?do=receitas`) e configurações em *Configurações gerais*.
  Drops novos 117–135. Detalhes em [ALQUIMIA.md](ALQUIMIA.md).

## Morte por nível, luta abandonada, jutsus de missão e exclusivos
- **Morte para inimigos do mapa proporcional ao nível** (`fight.php :: morrernaluta()/penalidademorte()`), antes metade do
  Ryou para todos: Ryou % = `min(máx, base + nível × k)`, experiência = % da barra do nível atual (nunca desce de nível),
  chance de perder (destruir) 1 item comum da mochila (nunca variantes, pergaminhos nem itens de missão; bp1..bp8 ou
  bp1..bp4, conferido em tempo de execução). Até o nível 5 só Ryou (11–15%). Tudo numa transação com o jogador travado;
  a tela de morte e o histórico mostram as perdas (pt/en). Configurável no painel.
- **Sair no meio da luta**: logout durante uma luta PvE (ou luta parada há mais de N minutos, padrão 5, conferida na
  próxima página do jogador e numa varredura barata em 1 de cada 20 páginas) conta como morte com a chance configurada
  (padrão 50%); senão a luta é só cancelada, sem recompensa. Logout durante um duelo = desistência (perde, inclusive a aposta).
  O logout só aplica a penalidade em navegação de verdade (`Sec-Fetch-Dest: document`): uma imagem apontando para o
  logout num post não derruba a luta de ninguém. Resultado registrado no histórico (i18n).
- **Missões com jutsu de recompensa** (`naruto_missoesextras.recompensa_jutsu`): ensina na entrega se cumprir os
  requisitos; se já conhece ou não pode aprender, recebe o pergaminho (mochila/banco); sem pergaminho, recebe Ryou
  (200 × nível mínimo, mín. 500) e experiência (50 × nível mínimo). Recompensa de item que é pergaminho agora vai no
  formato estudável `nome,jutsu,ID,1`.
- **Jutsus exclusivos de missão** (`naruto_spells.exclusivo_missao`): 6 jutsus novos (50–55: Selo das Mil Lanternas,
  Abraço da Maré Funda, Voz do Trovão Distante, Pele do Titã de Basalto, Coração do Leviatã Abissal, Rugido do Dragão de
  Cinzas), nunca aprendidos por nível nem por pergaminho (o painel recusa nível/pergaminho para eles; a ajuda mostra
  "Obtido apenas por missão").
- **6 missões novas** (140–145): três médias (cadeia na Vila da Folha, coleta cronometrada na Névoa, caçada na Nuvem) e
  três difíceis Rank S (Titã de Pedra dos Ermos, Leviatã das Profundezas com 2 h, Dragão Ancestral do Vulcão com 3 h),
  que dão os exclusivos 53–55 e os pergaminhos de Mil Raízes Famintas, Lamento da Serpente Pálida e Céu Rachado.
- **Painel**: opção "perde metade dos itens" do banimento automático da caveira vermelha (`pvpbanirmetade`).
- **Luta**: o avatar do jogador (todos os 18 desenhados virados para a direita) é espelhado por CSS para encarar o inimigo.

## Mochila de 8 espaços, consumíveis da loja e venda de itens
- **Mochila com 8 espaços** (`bp1..bp8`, colunas novas `bp5..bp8`) em duas fileiras de 4 na coluna da direita
  (`images/bpslots.jpg` refeito com 177x93). Todos os caminhos que percorriam a mochila (equipar, desequipar, banco,
  troca, alquimia, drops, missões, graduação, duelos, PvP, painel) agora tratam os 8 espaços.
- Ícones da mochila medidos com Playwright contra os quadros do `bpslots.jpg` (analisado com PIL,
  `tests/mochila_quadros.py` + `tests/mochila_medir.js`): os 8 ícones de 34x34, cheios ou vazios, ficam exatamente
  sobre os quadros.
- **Pergaminho de Retorno** (150 Ryou): usado pela mochila fora de batalhas/duelos, leva para a vila natal do
  alinhamento. **Incenso Repelente** (200 Ryou): nenhum inimigo aleatório por 100 passos ou 15 minutos (o que acabar
  primeiro; colunas `repelente_passos`/`repelente_ate`), com indicador na coluna da esquerda. Vendidos em todas as lojas.
- **Venda de itens da mochila** nas lojas (`index.php?do=vender`): qualquer item da mochila, com preço base por tipo
  (equipamento 1/4 do preço + 25 por refino; drop 10 + 6 × nível; poção metade do que recupera; variantes ×3) e um
  **comprador de pergaminhos de jutsu** (50 + 20 × nível mínimo do jutsu). Multiplicador configurável no painel
  (`control.vendamochila`, %). Venda atômica (jogador travado, item conferido, `LEAST(gold+x, limite)`, CSRF).

## Jutsus configuráveis, 30 jutsus novos e pergaminhos
- Jutsus com elemento, golpes múltiplos, roubo de vida, escudo, queimadura/veneno, atordoamento, reforços e enfraquecimentos por
  turnos, regeneração, dreno de chakra e recarga; ícones de status no cartão do inimigo (`images/status/`). Veja
  [ARQUITETURA.md](ARQUITETURA.md#jutsus-configuráveis-e-pergaminhos).
- 30 jutsus originais (ids 20-49): 15 aprendidos por nível e 15 só por **pergaminhos** que caem de inimigos (0,3-0,5%); usar o
  pergaminho na mochila ensina o jutsu se o jogador tiver nível/classe/graduação. Ajuda dos jutsus com efeitos, recarga e como aprender.
- Banco: colunas novas em `spells` (golpes, roubovida, efeito*, cooldown, nivelminimo, classe, graduacao, name_en, descricao*),
  `drops.ensina_jutsu` e `users.jutsuestado`.

## PvP no mundo, GameMasters e painéis mais seguros
- **PvP no mundo** (`pvp.php`, desligado por padrão): atacar quem está online na mesma casa ou numa vizinha, fora das vilas. Luta
  resolvida na hora com o motor do duelo; perdas configuráveis (Ryou, itens, variantes protegidas), espera anti-perseguição,
  nível seguro, **caveiras** branca/vermelha com banimento automático. Ícones novos: `images/caveira_branca.gif`, `caveira_vermelha.gif`,
  `pvp_atacar.gif`.
- **GameMaster** (`gm.php`): painel limitado para ajudar e moderar (destravar, silenciar, apagar mensagens, liberar a recuperação
  de senha, mensagem como GM), tudo na auditoria. Selo `images/gm_badge.gif` e ícone `layoutnovo/graficos/gm_minimap.gif`.
- **Painéis**: senha de novo (30 min), confirmação com senha nas ações perigosas, registro de auditoria (`adminlog`), lista de IPs.
- **Contas sem e-mail** (padrão): conta liberada na hora; perguntas secretas ou código de recuperação no cadastro. Senhas em Argon2id.
- **Variantes raras editáveis** (`naruto_variantes`, `admin.php?do=variantes`); ícones das variantes regerados (brilho mais fino, alinhados).
- Correções: troca recusada durante duelo; "Mandar para a vila" encerra o duelo; ícones das poções da mochila (`hp50.gif`...) usam
  `layoutnovo/equipamentos/novos/pocao_*.gif`; chaves antigas do duelo removidas de `lang/en/contas.php`.
- Banco: colunas novas em `control` (pvp*, emailativo) e `users` (mudoate, trocarsenha, pergunta1/2, resposta1/2, codigorecuperacao,
  resetliberado, banidoate, caveira, caveiraate); tabelas `adminlog`, `pvp`, `variantes`.

## Atualização dinâmica opcional (AJAX)
- Nova opção no painel (e no instalador): carregar as páginas sem recarregar a tela inteira, com `js/navegacao.js`
  (JavaScript nativo, sem jQuery). Desligada por padrão; desligada, o HTML é idêntico ao de antes.
  Veja [ADMINISTRACAO.md](ADMINISTRACAO.md).

## Duelos PvP refeitos
- Saguão (`users.php?do=batalha1`) com os ninjas online no mesmo lugar, desafios recebidos/enviados (aceitar, recusar,
  cancelar; validade de 5 minutos), desafio pelo nome e os últimos duelos. O desafiado recebe aviso no histórico.
- Aposta opcional em Ryou, guardada dos dois no aceite; o vencedor leva tudo, mais 5 x level do perdedor de
  experiência (se o duelo teve 4 turnos ou mais). Não há perda de itens.
- Turnos de 60 s com relógio; tempo esgotado = perde a vez, 3 vezes = derrota. Ações: atacar, jutsu, defender
  (metade do dano), poção da mochila e desistir. Mesmas fórmulas da luta PvE (crítico, esquiva, elementos, Economia
  de Chakra). Ninguém morre: quem chegaria a 1 HP fica com 1 HP e perde (o HP perdido não volta).
- Tela com os dois "cartões do jogador" (no formato do cartão de monstro), registro das 8 últimas ações no idioma de
  cada jogador, placar de vitórias/derrotas na ficha e **Rank de Duelos** (`rank.php?ord=duelos`).

## Missões no banco de dados e missões extras
- As 21 missões da história e os 6 NPCs do mapa saíram do código (`missoes.php`, `treinamentoequests.php`,
  `localpersonagens.php`, `falapersonagens.php`) e foram para o banco (`naruto_missoesextras`, `naruto_npcs`), com um
  único motor em `funcoesmissoesextras.php`. O progresso dos jogadores (`users.missao`) não mudou; o painel e as falas
  ficaram idênticos (comparados etapa por etapa em português e inglês).
- **Missões extras** opcionais (`missoesextras.php`, link no Painel de Missões): 12 missões novas em 6 vilas e com
  4 NPCs novos no mapa (entregar item, derrotar inimigo, visitar local, pagar Ryou, falar com NPC; algumas diárias).
- Editor de missões e NPCs no painel (`admin_missoes.php`). NPCs aparecem no mini mapa e no mapa-múndi.
- "Missões extras" passou a se chamar só **Missões** (página do jogador, link do Painel de Missões e menu do painel).
- **Tipos novos de missão**: cadeia de etapas, viagem/escolta a pé, coletar drops, sobreviver (vitórias seguidas sem
  se curar), escolha com dois desfechos (e continuações diferentes), cronometradas (com "tempo restante" e falha),
  diárias e semanais. O painel mostra etapa atual, contagem e tempo restante.
- **15 missões novas** (níveis 1 a 55, em 7 vilas e 4 NPCs) e 4 NPCs novos: a vigia Rensuke, a herborista Suzuran, o
  mercador Botan e a arqueóloga Itsuha. Entre elas: o mistério do sino, a escolta do aprendiz, a carroça de sal, o duelo
  no desfiladeiro, o festival das lanternas, as ruínas que sussurram, a carta selada (com duas continuações), o resgate
  na mina, o Oni da montanha vulcânica e o banquete do castelo.

## Itens, bônus de combate, drops por inimigo e inimigos novos
- Itens com **até 3 bônus** e **4 bônus de combate novos**: Chance de Crítico, Esquiva, Regeneração de HP por passo e
  Economia de Chakra (fórmulas em [ARQUITETURA.md](ARQUITETURA.md)), nas lutas, no mapa, nos tooltips e na ajuda.
- **Tabela de drops por inimigo** com chance em % (`naruto_monster_drops`), editável no painel e mostrada na ajuda
  ("Pode dropar"). Todos os inimigos receberam uma tabela baseada na regra antiga (~4,5% de drop equipável + poções).
- **Drops consumíveis** (poções e comida que vão para a mochila).
- **38 itens novos nas lojas** (ids 47-84, em todas as vilas com loja), **36 drops novos** (51-86: acessórios,
  materiais e consumíveis) com ícones originais, e **20 inimigos novos** (167-186, níveis 3 a 59, alguns com região do
  mapa), equilibrados pelos vizinhos de nível. Painel: filtros, miniaturas e botão Duplicar em itens e drops.
- Luta: a vitória e o "pegar drop" travam o jogador (sem duplicação em cliques paralelos); lutas com mais de 127 turnos
  não dão mais erro de banco.

## Inimigos por terreno e terceiro lote de inimigos
- Coluna nova `monsters.terrenos` (nomes da tabela `terrenos` separados por vírgula; vazia = qualquer terreno). O inimigo só
  aparece quando a casa do jogador é de um desses terrenos (o mesmo terreno desenhado no mini mapa). Se nenhum inimigo da
  faixa de nível puder aparecer no terreno da casa, a restrição é ignorada ali (nunca falta inimigo).
- `terrenodacasa()` saiu de `templates/minimap.php` para `funcoesterreno.php` (usado pelo mini mapa, pela luta, pelo painel e pela ajuda).
- Painel: caixas *Aparece apenas nestes terrenos* no inimigo, coluna e filtro de terreno na lista; *Duplicar* copia os terrenos.
  Ajuda dos inimigos e mapa global do inimigo mostram "Terreno: Deserto, Rochoso".
- Feras e criaturas genéricas ganharam terrenos (lobos/javali/urso → floresta/grama, serpentes/sapos/lesmas → pântano,
  golem/águia → deserto/rochoso...); inimigos da história e com nome continuam sem restrição.
- **20 inimigos originais novos** (187-206, níveis 4 a 61), todos com terrenos, elemento e tabela de drops própria
  (com chance de variante rara). Nível máximo 61: com o mapa de 300 casas a faixa mais distante é 59-61.

## Mundo maior (400 casas) e conteúdo de fim de jogo
- Mapa aumentado de 300 para **400 casas para cada lado** (`control.gamesize = 400`). Andar, mini mapa, mapa global, painel e
  ajuda já usavam o `gamesize`; o sorteio das árvores do mini mapa passou a usar a semente fixa 300 (as árvores das áreas
  antigas não mudam de lugar).
- Mapa global novo `images/mapa_grande.png` (667x667, gerado por `tools/gerar_mapa_grande.py`): o mapa original fica intacto no
  meio e ganha um anel novo no mesmo estilo — geleira ao norte, mar gelado com blocos de gelo no nordeste, mar aberto com
  ilhas e ilhas de mangue a leste, terras vulcânicas ao sul, ermos de cânions no sudoeste e o grande deserto com cânions a
  oeste. O mapa global e o mapa do painel esticam a imagem para cobrir todas as coordenadas (`background-size`).
- Terrenos 12-19 para o anel (neve, costa, pântano, rochoso, deserto) e o terreno novo **vulcânico** (`images/terreno_vulcanico.jpg`,
  basalto escuro com rachaduras de lava; ladrilhos e decorações — rocha com lava, fenda fumegante, árvore morta — gerados por
  `tools/gerar_tiles_terreno.py PASTA vulcanico`).
- **15 inimigos originais de fim de jogo** (207-221, níveis 62 a 80) com terrenos do anel, elemento e drops próprios; três chefes
  imunes a dano e sono (Leviatã das Profundezas, Titã de Pedra dos Ermos, Dragão Ancestral do Vulcão) com HP x5 e exp/Ryou x3.
- **15 drops novos** (87-101, nível mínimo 62 a 80) melhores que os antigos, com até 3 bônus (crítico, esquiva, regeneração,
  economia de chakra...), que só caem desses inimigos; variante rara de 3% (chefes 4-5%).
- Os níveis 1-100 já existiam em `naruto_levels`: o jogador chega ao nível 80 sem mudança na curva de experiência.

## Variantes raras dos itens (✦)
Qualquer drop equipável pode sair como **variante rara**: o mesmo item com uma skin especial e um bônus pequeno a mais.
Cada linha da tabela de drops de um inimigo tem a chance de variante (padrão 0,5% dos drops; poções/comidas nunca).
O efeito é sorteado entre os 6. Nome exibido com rótulo colorido ("Kunai ✦ Dourada"), ícone próprio na coluna da direita,
mochila, banco, troca, alquimia, fichas e tooltips (que mostram "✦ Variante rara" e o bônus). A variante é guardada no
próprio nome do item (`Nome:efeito`), por isso anda junto ao equipar, desequipar, guardar/retirar do banco, doar e trocar.
Vender o item na loja avisa que ele é raro. A ajuda dos monstros mostra "✦ raro: 0,5%".

| efeito | rótulo | bônus extra | visual |
|---|---|---|---|
| `dourado` | ✦ Dourada | +10% do atributo principal (mín. +1) | item recolorido em ouro + brilhos |
| `brilho` | ✦ Brilhante | +2% Chance de Crítico | aura branca/ciano + estrela |
| `raio` | ✦ Elétrica | +2% Esquiva | raios azul-branco em volta |
| `chama` | ✦ Flamejante | +5 Poder de Ataque | aura de fogo laranja |
| `gelo` | ✦ Congelada | +5 Poder de Defesa | tom azul gelado + cristais |
| `sombra` | ✦ Sombria | +3% Economia de Chakra | aura roxa/preta |

Ícones: `layoutnovo/equipamentos/variantes/<efeito>/<id>.gif` e `.../<efeito>/drops/<id>.gif`, gerados por `tools/variantes_itens.py`.
Também corrigido: vender na loja um item refinado ("+N") não retirava o bônus do refino.

## Compatibilidade
- Roda em **PHP 8.x** (antes: PHP 5/7). Corrigidos todos os erros fatais do PHP 8 encontrados: constantes sem
  aspas, aritmética com texto, `mysql_*`, `get_magic_quotes_gpc`, `sizeof()` em texto, comparações que mudaram de
  sentido (`"" == 0`), divisão por zero, variáveis inexistentes passadas ao mysqli.
- Roda em **MariaDB/MySQL em modo estrito** (inserts com `id=''`, valores fora da faixa das colunas, datas zeradas).
- **Tudo em UTF-8** (arquivos, páginas e banco `utf8mb4`). Os acentos que tinham virado `?`/`�` em 19 arquivos e
  nos dados do banco foram recuperados automaticamente (comparando com a versão de 2012) e revisados.

## Limpeza do sistema base
- Removidos todos os vestígios do motor em que o jogo foi baseado no início: prefixo das tabelas (`dk_` → `naruto_`),
  cookie (`dkgame` → `narutorpg`), textos do painel em inglês, ajuda traduzida, links e créditos.

## Refatoração
- Código comentado em português (cabeçalho em cada arquivo, regras e formatos documentados), mantendo o estilo
  procedural, os nomes das rotas, campos e templates do autor.
- Blocos copiados e colados viraram funções auxiliares; código morto removido (ex.: `users.php` 2418 → ~790
  linhas, `missoes.php` 1089 → ~350, `funcaoitens.php` 1200 → ~680, `treinamentoequests.php` 874 → ~270).

## Jogo
- **88 cartas de inimigos novas** (antes só 78 dos 151 inimigos tinham carta) e **15 inimigos novos** (152–166)
  com retratos que estavam desenhados e nunca usados no `monstros.psd`.
- História principal pode ser concluída do começo ao fim (Missões 3, 7, 10, 12 e 20 estavam quebradas; a 7 tinha
  um ciclo infinito de recompensas).
- O botão "Conversar" dos NPCs das etapas 6 a 18 voltou a funcionar (um `else` apagava o botão e só o último NPC funcionava).
- Correr da luta agora pode falhar de verdade; bônus de Ryou calculado sobre o Ryou (era sobre a experiência);
  bônus de atributos por elemento passou a funcionar; buffs não passam para a próxima luta.
- Duelos não deixam mais jogadores presos; trocas e banco não duplicam itens; descanso não acelera com várias abas.
- Equipamento vazio, cidades novas e inimigos novos têm imagens padrão (nada de imagem quebrada).
- Temporizadores (Senjutsu, treinos, missões) corretos na virada do mês.
- Ajuda reescrita para o jogo, com tabelas lidas do banco.

## Painel do Administrador (novo)
- Em português, com o visual do jogo, token anti-CSRF e validação de todos os campos.
- Novas operações: resumo e avisos, notícias (editar/apagar), mensagem para todos, busca/filtros de jogadores,
  banir/destravar/curar/apagar/trocar senha, moderação de chats e fórum, manutenção, criar/apagar inimigos
  (com upload de carta), itens e drops (com ícone), jutsus, vilas (com 4 imagens) e níveis.
- Novas configurações: cadastros abertos, mensagem de jogo fechado, aviso do login, Ryou inicial, chance de batalha,
  multiplicadores de experiência/Ryou, nível mínimo de PvP.

## Vilas 100% no banco de dados e terrenos do mini mapa

- Regras que estavam fixas no PHP pelo id da vila agora são colunas de `naruto_towns`: serviços (`tem_pousada`, `tem_loja`,
  `tem_mapas`, `tem_banco`, `tem_missoes`, `tem_graduacao`, `tem_alquimia`, `tem_senjutsu`, `tem_jutsubusca`), `secreta`
  (mapa não vendido; antes vilas 8-10), `alinhamento` + `vilanatal` (antes a tabela folha=1, pedra=3, névoa=4, areia=5,
  nuvem=6 em fight.php e admin.php), `name_en`, `kage_en`, `descricao`, `descricao_en`.
- O registro ganhou a escolha da **vila natal** (vilas com `vilanatal = 1`); morrer leva de volta à vila natal (sem ela, vila 1).
- Página da vila: sem a imagem de título o nome aparece em texto estilizado; a descrição aparece abaixo do título.
- Admin: edição de todos os campos, upload de todas as imagens, ícones prontos para vilas novas, nova seção *Terrenos do mini mapa*.
- Mini mapa: cada casa tem um terreno (grama, floresta, trilha, deserto, rochoso, neve, costa, pântano) pela tabela
  nova `naruto_terrenos`; deserto/neve/costa não têm árvores.
- Mini mapa coerente: ladrilhos 30x30 por casa (sem árvores/pedras cortadas na borda), 4 variantes de chão por terreno,
  bordas e cantos irregulares entre terrenos e decorações por bioma (árvores, pinheiros, pedras, cactos, juncos; nada na
  água). `arvores` agora liga/desliga as decorações do bioma (só a costa vem desligada).
- Loja (towns.php buy3): corrigida duplicação de Ryou ao comprar enquanto o item equipado era guardado no banco (transação com `FOR UPDATE`).

## Segurança
Veja [SEGURANCA.md](SEGURANCA.md): bcrypt, cookie assinado, bloqueio de força bruta, recuperação de senha por link,
fim do `extract($_POST)`, correção de SQL injection, transações contra duplicação, CSRF no painel, uploads seguros,
`.htaccess` e instalador com permissões mínimas.

## Idiomas
- O jogo agora está em **português e inglês**. O jogador troca no rodapé, na tela de login ou no topo do painel
  (`?idioma=` + cookie `idioma`); o idioma padrão é escolhido no painel (`control.idiomapadrao`).
- Traduções em `game/lang/en/*.php` (o texto em português é a chave), mensagens gravadas no banco em formato neutro
  (`i18n:`), imagens com texto traduzidas em subpastas `en/`. Veja [ARQUITETURA.md](ARQUITETURA.md#idiomas-i18n)
  e [TRADUCAO.md](TRADUCAO.md).

## Instalação
- `install/install.sh`: instalação do zero em Debian/Ubuntu (Apache, PHP, MariaDB, banco, config, admin,
  HTTPS e e-mail opcionais), interativo ou automático.
- Banco de instalação limpo (sem dados de jogadores antigos).


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

## Especializações escolhidas no jogo e Senjutsu por classe
- Cadastro sem especialização (`charclass = 0`); escolha **definitiva** em `especializacao.php` a partir de `control.classenivel` (padrão 5), em qualquer vila.
- Tabela nova **`classes`** (modificadores de combate e bônus de Senjutsu por classe), editável em Admin > Especializações. Coluna nova `control.classenivel`; `users.charclass` passa a ter padrão 0.
- Tabela `levels` rebalanceada (mesma curva de experiência para todos, HP/CH por classe) e 12 jutsus exclusivos (56-67). Números da simulação em [ESPECIALIZACOES.md](ESPECIALIZACOES.md).
- Senjutsu: bônus calculado na hora do combate (some quando o NP acaba, também no duelo/PvP), bônus próprio por classe, cobrança de NP sem perder os segundos que sobram. Contas com bônus antigo gravado em `attackpower/defensepower` foram corrigidas (subtraído `atkdefsenjutsu`).

## Neblina de guerra no mapa-múndi
- O mapa global (`index.php?do=showmap`) é dividido nos mesmos 20x20 quadrantes de antes; cada quadrante só aparece depois que o
  jogador pisa nele (`explore.php` liga o bit em `users.mapadescoberto` com um único UPDATE atômico, só na primeira vez).
  Os outros ficam cobertos por uma textura de pergaminho escuro (`images/neblina_mapa.png`, `tools/gerar_neblina.py`).
  As buscas de inimigo/item e os NPCs só marcam quadrantes descobertos. Vilas aparecem como losangos vermelhos na posição exata
  quando o quadrante delas foi descoberto ou o jogador já tem o mapa delas (`users.towns`).
- Porcentagem descoberta embaixo do mapa e na ficha (`showchar`). Ajuda atualizada.
- Admin/GM veem o mapa todo, com o link "Ver com a neblina do jogador". *Configurações gerais → Neblina do mapa-múndi*
  (`control.neblinaativa`) desliga para todos.
- Jogadores existentes: migrados com o quadrante atual + os 8 vizinhos + os quadrantes das vilas que já conheciam.
- Arte do mapa: Vila da Sabedoria [300,0], Castelo Real [239,151] e Vila da Nuvem [170,170] ficavam desenhadas no mar; o mapa
  ganhou uma língua de gelo e duas ilhas embaixo delas (`tools/ajustar_mapa_vilas.py`). Coordenadas das vilas não mudaram.

## Cadastro sem vila, troca de dificuldade e histórico maior (nov/2026)
- O cadastro **não pergunta mais a vila natal**: todo ninja novo nasce na vila inicial (`control.vilainicial`, padrão Folha);
  um campo forjado é ignorado. Trocar depois só pelas transferências dos embaixadores.
- Escolher o **código de recuperação** esconde as perguntas secretas (JS; sem JS o servidor as ignora).
- **Troca de dificuldade**: provações 190-192 do Mestre das Provações (NPC 40): level 15, vitórias seguidas contra inimigos do
  patamar da nova dificuldade, oferenda em Ryou e espera de 14 dias (`control.trocadif_dias`). Entrega atômica, histórico e
  registro de auditoria; a ficha mostra a dificuldade e desde quando.
- O **Histórico** mostra 12 mensagens (antes 5; `control.historicomax`). Veja [TROCA_DE_DIFICULDADE.md](TROCA_DE_DIFICULDADE.md);
  teste `tests/cadastro_dificuldade_teste.py`.

## Troca de vila natal (nov/2026)
- Antes só o administrador podia mudar a vila natal (trocando o código de alinhamento da vila inteira). Agora o jogador
  pode trocar pela **missão de transferência** do embaixador da nova vila (NPCs 32-36, missões 177-181): level 30,
  Chuunin, inimigos dos arredores da vila, marco da fronteira e taxa de cidadania de 20.000 Ryou; depois, 30 dias de espera
  (`control.trocavila_dias`). A transferência para a vila atual não aparece; vilas secretas e sem alinhamento não aceitam.
- A troca é atômica (transação + `FOR UPDATE` + `WHERE` com o alinhamento e a data antigos) e fica no histórico. Morte, PvP,
  Pergaminho de Retorno, Teleporte Aleatório, emblema e ficha seguem a nova vila (todos leem `users.alinhamento` na hora).
- Vila natal (e "desde dd/mm/aaaa") na ficha "Todos os Status"; ajuda "Trocar de Vila Natal" em pt/en; campo de destino no
  editor de missões. Colunas `users.vilatroca_em`, `control.trocavila_dias` (`tools/troca_vila.sql`). Detalhes em
  [TROCA_DE_VILA.md](TROCA_DE_VILA.md); teste `tests/troca_vila_teste.py`.

## Viajar Para mais caro e Selo de Viagem

O *Viajar Para* agora custa TP pela distância e bem mais que o Pergaminho de Retorno (vila natal: no mínimo 3× o TP e 4× o preço do pergaminho);
para outras vilas também gasta um **Selo de Viagem** (drop 436: inimigos de nível 20-60, Alquimia e missões repetíveis 111/131). Tela de viagem com o
custo antes de confirmar; valores no painel. Detalhes em `docs/PONTOS_E_JUTSUS.md`; migração `tools/viagem_custo.sql`; teste `tests/viagem_custo_teste.py`.

## Durabilidade dos itens fortes e reparo

A durabilidade existia mas todo item era infinito. Agora as melhores armas, coletes e bandanas, os drops de nível 45+ e os
itens de compensação 420-425 saem com 500 a 800 vitórias de durabilidade (`items/drops.durabilidade`; 0 = infinita);
itens fracos e os que os jogadores já tinham continuam infinitos. Aviso com ≤ 10%, quebra que retira os bônus, aba
*Reparar equipamentos* nas lojas (`control.reparo_pct`, padrão 30% do valor por um reparo completo, atômico) e chave
`control.durabilidade_ativa`. Detalhes em [DURABILIDADE.md](DURABILIDADE.md); migração `tools/durabilidade.sql`
(gerada por `tools/durabilidade.py`); teste `tests/durabilidade_teste.py`.
