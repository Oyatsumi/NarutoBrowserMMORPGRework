# Painel do Administrador

Acesse `admin.php` com uma conta de `authlevel = 1` (a conta criada pelo instalador já é administradora).
O link "Painel do Administrador" também aparece na coluna da esquerda do jogo para administradores.
A barra do topo do painel tem o seletor de idioma "Português | English".

| Seção | O que dá para fazer |
|---|---|
| **Início** | Números do jogo (cadastros, online, banidos, em batalha), top 10, últimos cadastros e avisos (jogo fechado, modo debug ligado, pasta `install/` esquecida no servidor, inimigos sem carta, mapa maior que o nível dos inimigos). |
| **Configurações gerais** | Abrir/fechar o jogo e a mensagem de fechado; cadastros abertos; verificação de e-mail; aviso da tela de login; nome, endereço e e-mail do jogo; tamanho do mapa; Ryou inicial; chance de batalha ao andar; multiplicador (%) do preço que as lojas pagam pelos itens da mochila (`vendamochila`, 100 = preço base, 0 = não compram); multiplicadores de experiência e Ryou (eventos); nível mínimo de duelos/doações; nomes das especializações e dificuldades (e seus multiplicadores); notícias/online/chat nas vilas; tipo de fórum; compressão gzip; idioma padrão do jogo (português ou inglês); link de doação (Stripe), endereço do repositório e intervalo (dias) da janela de doação do painel; neblina do mapa-múndi ligada/desligada (`neblinaativa`). |
| **Notícias** | Publicar, editar e apagar. A mais recente aparece nas vilas. |
| **Mensagem para todos** | Abre a janela flutuante do jogo para todos (ou só os online) com título, imagem e texto. |
| **Jogadores** | Busca por conta/personagem/e-mail, filtros (online, banidos, equipe, não verificados, em batalha), paginação. Na ficha: todos os campos, nova senha, cargo (tutor/GM), "mandar para a vila / destravar", "restaurar HP/Chakra/TP", banir/desbanir e apagar conta (com confirmação pelo nome). |
| **Moderação** | Apagar mensagens do chat global e do chat do mapa (ou limpar tudo), apagar tópicos do fórum, ver e limpar bloqueios de login. |
| **Manutenção** | Encerrar batalhas abandonadas, resetar duelos, cancelar trocas presas, restaurar status de todos, apagar contas não verificadas há 7 dias; versões do PHP/banco e tamanho das tabelas. |
| **Inimigos e cartas** | Lista com a carta de cada inimigo; criar, editar, apagar e **enviar a carta**. |
| **Itens das lojas / Itens de drop** | Criar, editar, apagar e enviar o ícone (34x34). O campo especial é validado. |
| **Jutsus** | Criar, editar e apagar: tipo (cura, dano, sono, +ataque, +defesa, suporte), chakra, valor, elemento, golpes, roubo de vida, efeito extra (valor/turnos/chance), recarga, requisitos (nível, classe, graduação), nome e descrição em inglês; botão **Pré-visualizar efeito**; filtros por elemento/tipo/classe/forma de aprender; "Como é aprendido" e **Criar um pergaminho que ensina este jutsu**. Nos **Drops**, o campo "Ensina o jutsu" transforma o drop em pergaminho. |
| **Vilas** | Criar, editar e apagar; posição, preços, TP, itens à venda e as quatro imagens da vila. |
| **Avatares** (`admin_avatares.php`) | Modo de cada avatar (livre, drop/Pergaminho de Disfarce, recompensa de missão ou especial), nome, ativo e ordem; onde cada um é obtido (calculado das tabelas de drops e das missões) e quantos jogadores o têm; **dar um avatar a um jogador** (fica na auditoria). Nos **Drops**, o campo "Libera o avatar" transforma o drop em Pergaminho de Disfarce; nas **Missões**, "Avatar de recompensa". |
| **Níveis** | Ver a tabela inteira e editar cada nível para as três especializações. |

Todos os formulários do painel exigem um token anti-CSRF e todos os valores são validados no servidor.

## Atualização dinâmica das páginas (AJAX)
Em **Configurações gerais → Atualização dinâmica das páginas (AJAX)** (campo `control.atualizacaodinamica`):
- **Desligada** (padrão): cada clique recarrega a página inteira, exatamente como sempre foi (o HTML não muda nada).
- **Ligada**: o layout inclui `js/navegacao.js` (JavaScript puro, sem bibliotecas). Os links e formulários do jogo
  são buscados em segundo plano e só a coluna da esquerda, o conteúdo, a coluna da direita, a janela de mensagens e
  o título são trocados. O endereço da barra muda normalmente e o botão voltar/avançar funciona. Scripts dentro
  do conteúdo (luta, missões) rodam de novo e o recarregamento automático (andar automático, descansar, duelo,
  troca) continua funcionando.
- Sempre recarregam a página inteira: painel do administrador, login/logout, troca de idioma, envio de arquivos
  (avatar), links externos ou com `target`, popups (chat, mapa, perfil continuam em `window.open`), e qualquer
  resposta inesperada ou erro de rede. Não há atualização periódica (polling): as colunas só mudam ao navegar.
- Prós: navegação mais rápida e sem tela branca. Contras: depende de JavaScript (sem ele vira o modo clássico
  sozinho), é mais difícil de depurar e páginas novas com muito JavaScript próprio devem ser testadas.
- Na instalação: pergunta do `install.sh` ou `NARUTO_AJAX=s|n` (padrão `n`).

## Criando a carta de um inimigo

A carta tem 295x145 pixels. Existem três caminhos:

1. **Pelo painel**: em *Inimigos e cartas* → editar → *Enviar nova carta*. Qualquer JPG/PNG/GIF é ajustado para
   295x145 (precisa da extensão GD do PHP, instalada pelo instalador).
2. **Pelo Photoshop**: abra `psds/cartas/monstros.psd`. Ele tem a moldura e **todas as camadas de retrato**
   (ocultas). Coloque o novo retrato como camada em modo *Multiplicação* abaixo das camadas da moldura, dentro do
   quadro da esquerda (67x99 a partir de x=6, y=37), e exporte como `ID.jpg`. O nome, o nível e os números são
   escritos pelo jogo por cima da carta.
3. **Por script**: `tools/montar_cartas.py` faz o mesmo que o item 2 automaticamente
   (`python3 tools/montar_cartas.py psds/cartas/monstros.psd saida 200:retrato.png`).

As 15 cartas dos inimigos 152 a 166 foram feitas com retratos que já estavam desenhados (e ocultos) no
`monstros.psd`. As cartas 79 a 151, que não existiam, foram geradas como silhuetas no mesmo estilo
(`tools/gerar_retratos.py`) para o jogo não mostrar cartas vazias — substitua pelas artes definitivas quando quiser.

## Criando uma vila nova

Tudo o que define uma vila fica na tabela `naruto_towns` — nada é fixo no código, então uma vila nova funciona sem editar PHP.

1. *Vilas* → *Adicionar vila*: nome (e nome em inglês), Kage/líder (e em inglês), descrição (opcional, pt/en), posição, preços, TP e itens à venda.
2. **Serviços**: marque o que a vila oferece — pousada, loja de itens, loja de mapas, banco, missões, graduação, alquimia,
   treino de Senjutsu (e chamar Fukasaku & Shima), treino do Jutsu de Busca. *Mapa secreto* = o mapa não é vendido
   (é preciso descobrir a vila andando).
3. **Alinhamento / vila natal**: um código curto (`folha`, `pedra`...; letras minúsculas, números, `_`). Com *Vila natal*
   marcada, ela pode ser a vila inicial (Configurações gerais → Vila inicial; o registro não pergunta mais a vila) e quem morre com esse alinhamento volta para ela. Alinhamento sem vila = vila 1.
   Trocar o código leva junto os jogadores do alinhamento antigo.
4. **Imagens** (todas opcionais): ícone 24x24 (ou *Escolher ícone pronto* de `images/24/vilas_novas/`), ícone cinza
   (gerado do ícone se não for enviado), título 300x25 (sem ele o nome aparece em texto estilizado), fundo 487x284,
   Kage 115x113 e emblema do alinhamento 20x17 (`images/vila_CODIGO.gif`, depois de salvar o alinhamento).
5. Apagar: não é possível apagar a vila 1, uma vila com jogadores dentro ou onde missões são entregues. Jogadores do
   alinhamento apagado passam para o alinhamento da vila 1. As imagens da vila são apagadas.
6. Lembre que inimigos sobem 1 nível a cada 5 casas do centro: uma vila em (200, 0) fica numa área de nível ~40.

## Terrenos do mini mapa

*Vilas* → *Terrenos do mini mapa* (`admin.php?do=terrenos`): cada terreno é um retângulo de coordenadas com uma imagem de chão
`images/terreno_NOME.jpg` (155x155) e uma prioridade. Cada casa do mini mapa usa o terreno de maior prioridade que a contém
(as bordas são levemente irregulares, de forma fixa pela coordenada). Desmarque *Árvores* em deserto, neve, costa etc.
Terrenos iniciais: grama (todo o mapa, prioridade 0), floresta, trilha, deserto, rochoso, neve, costa, pântano e vulcânico (anel externo).

Cada inimigo pode ser limitado a terrenos: *Inimigos* → editar → *Aparece apenas nestes terrenos* (nenhum marcado = qualquer
terreno). Marque só terrenos que existem na faixa de distância do nível (ex.: neve fica a 210+ casas do centro, nível ~42+);
se nenhum inimigo da faixa puder aparecer no terreno da casa, a restrição é ignorada ali. A lista de inimigos filtra por terreno.

## Tamanho do mapa e anel externo
O mapa vai de -`gamesize` a +`gamesize` (*Configurações* → *Tamanho do mapa*, hoje **400**). Inimigos sobem 1 nível a cada
5 casas, então 400 casas pedem inimigos até o nível 80 (há inimigos 207-221 de nível 62 a 80 no anel 300-400). Se mudar o
tamanho de novo:
- o mapa global (`images/mapa_grande.png`) é esticado para as coordenadas novas; para desenhar outro anel, ajuste e rode
  `python3 tools/gerar_mapa_grande.py ORIGINAL_300.jpg game/images/mapa_grande.png [folha.png]` (o original de 300 casas é
  `images/mapateste.jpg`);
- a neblina de guerra do mapa global usa os mesmos 20x20 quadrantes (`funcoesneblina.php`); se o tamanho mudar, os quadrantes
  já descobertos (`users.mapadescoberto`) passam a cobrir outras coordenadas — zere a coluna ou rode de novo a migração.
- crie retângulos de terreno para as áreas novas (*Terrenos do mini mapa*; fora de qualquer retângulo a casa é grama) e
  confira os inimigos da faixa de nível nova (o painel avisa se faltar nível).
Terrenos do anel: neve (norte), costa (nordeste e leste, com ilhas de pântano), vulcânico (sul), rochoso (sudoeste e cânions
do oeste) e deserto (oeste). Um terreno novo precisa de `images/terreno_NOME.jpg` (155x155) e dos ladrilhos de
`tools/gerar_tiles_terreno.py PASTA_DO_JOGO NOME` (sem eles a casa usa a imagem inteira).

## Missões (história e opcionais) e NPCs do mapa
Menu **Conteúdo do jogo → Missões** (`admin_missoes.php`; para o jogador, página **Missões** em `missoesextras.php`). Todas as missões ficam na tabela
`naruto_missoesextras`:
- **História** (`categoria = historia`): as 21 missões obrigatórias, na ordem da coluna `etapa`. O progresso de cada
  jogador continua em `users.missao` (`etapa,feitos,total,intervalo`), então mudar a etapa, o tipo ou a quantidade
  afeta quem está nela agora (o editor avisa). Elas não podem ser apagadas nem desativadas pelo painel.
- **Opcionais** (`categoria = extra`, rótulo "Opcional" no painel): aceitas e entregues numa vila (`vila_id`, 0 = qualquer vila) ou com um
  NPC do mapa (`npc_id`). Podem ser repetíveis (intervalo em horas). Progresso em `naruto_missoesextras_jogador`.
- Tipos: entregar item (consumido na entrega, até 4), derrotar inimigo (contado a cada vitória), visitar local
  (marcado ao andar), pagar Ryou, falar com NPC; só na história: repetir tarefa e "especial" (regra em PHP na
  função `mx_ganchos()` de `funcoesmissoesextras.php`, hoje só a Missão 19).
- **Tipos novos (só opcionais; configuração em JSON na coluna `config`)** — o formulário muda conforme o tipo:
  - *Cadeia de etapas*: 2 a 6 etapas em ordem, cada uma de um tipo (falar com NPC, derrotar, visitar, coletar,
    sobreviver, entregar item, pagar Ryou), com texto pt/en próprio. Entregar item no meio da cadeia exige o NPC que
    recebe (os itens saem da mochila ao conversar com ele); pagar Ryou só na última etapa. O jogador vê a lista de
    etapas (✔ feita, ► atual) e "Etapa X de N".
  - *Viagem / escolta*: destino = vila ou NPC; começa onde é aceita e é entregue no destino. Limite de passos (0 = sem
    limite). Só vale a pé: precisa andar pelo menos a distância (viagem rápida não conta); passar do limite ou morrer = falha.
  - *Coletar drops*: N drops com aquele nome obtidos depois de aceitar (lê o `dropcode` da vitória, então funciona com a
    tabela `naruto_monster_drops` ou com a regra antiga) + uma chance própria opcional (%) por vitória, só contra o
    inimigo escolhido (ou qualquer um).
  - *Sobreviver*: N vitórias seguidas sem descansar, ganhar HP (pousada, item, jutsu médico) ou morrer; nível mínimo do
    inimigo opcional. Cura é detectada a cada passo (HP maior que depois da última vitória ou novo descanso).
  - *Escolha (diálogo)*: duas opções (botão, desfecho, exp/Ryou/item) escolhidas na entrega. Qualquer missão pode ter as
    duas opções; o campo *Só aparece depois de* (+ opção A/B) cria continuações diferentes para cada escolha.
  - *Cronometrada*: qualquer tipo com *Limite de tempo (minutos)*; o tempo conta do aceite e mostra "Tempo restante".
    Vencido, a missão fica `falhou` e pode ser aceita de novo do zero. Filtro "Cronometradas" na lista.
  - *Diárias/semanais*: repetível com 24 h ("Diária") ou 168 h ("Semanal"); atalhos no formulário.
  - *Troca de vila natal*: campo **Troca de vila natal** (vila de destino, só vilas natais não secretas). Na entrega a
    vila natal do jogador passa a ser essa, numa transação; a espera entre trocas fica em *Configurações gerais → Troca
    de vila natal* (`control.trocavila_dias`). Missões 177-181 dos embaixadores. Veja [TROCA_DE_VILA.md](TROCA_DE_VILA.md).
- Progresso do jogador: `naruto_missoesextras_jogador` (`estado` andamento/concluida/falhou, `etapa_atual`, `progresso`,
  `dados` em JSON com passos da viagem, HP da sobrevivência e a opção escolhida). Os eventos vêm de `fight.php`
  (vitória e morte), `explore.php` (cada passo) e `falapersonagens.php` (conversa). Entregas usam trava otimista
  (mochila, banco e Ryou conferidos no `WHERE`): cliques duplos não pagam duas vezes.
- O editor valida tudo (item/inimigo/NPC precisam existir, quantidades maiores que zero, coordenadas dentro do mapa
  e fora das vilas), mostra só o campo de alvo do tipo escolhido e tem prévia da fala do NPC.
- **Textos**: aparecem exatamente como digitados. Os campos em inglês (`titulo_en`, `descricao_en`...) são opcionais;
  vazios, quem joga em inglês vê o português. As missões de exemplo também têm tradução em `lang/en/missoes_extras.php`.
- **NPCs do mapa** (aba "NPCs do mapa", tabela `naruto_npcs`): nome, avatar (`layoutnovo/personagem/*.png`), ícone no
  mapa (`images/24/arvore_personagem*.gif`), coordenada (não pode ser vila nem casa de outro NPC), fala e fala em
  inglês (`%s` = nome do jogador). NPCs usados por missões da história só aparecem na etapa da missão.

## Itens, drops e tabelas de drops dos inimigos

- **Itens das lojas** (`Itens`): filtros por tipo, preço e nome; miniatura do ícone; coluna "Vendido em" (vilas cujo
  `itemslist` contém o item); botão **Duplicar** (copia o item e o ícone). No editor há três campos **Especial**
  (`atributo,valor` ou `X`); o mesmo atributo não pode aparecer duas vezes.
- **Drops** (`Drops`): filtros por nível mínimo, atributo e nome, três atributos, campo **Consumível** (poção de HP ou CH:
  vai para a mochila e recupera o valor indicado), lista dos inimigos que soltam o drop e botão Duplicar.
  Apagar um drop o remove também das tabelas dos inimigos.
- Atributos aceitos nos campos especiais: os antigos mais `critico` (% extra de hit excelente, máx. 50), `esquiva`
  (% extra de desviar, máx. 40), `regenhp` (HP por passo no mapa) e `economiachakra` (% a menos de chakra nos jutsus, máx. 50).
- **Tabela de drops do inimigo** (`Inimigos` > editar > Drops): uma linha por drop com a chance em % (0,01 a 100).
  Use "+ Adicionar linha" e "Remover". Sem linhas, o inimigo usa a regra antiga (1 em 25). A lista de inimigos e a
  ajuda dos jogadores mostram "Pode dropar: X (y%)". Sorte e Chance de Drop do jogador multiplicam as chances.
  Duplicar um inimigo copia a tabela; apagar o inimigo apaga a tabela.

## Variantes raras (✦)
- **Inimigos > editar > Drops**: a coluna **Variante rara** é a % dos drops daquela linha que saem como variante
  (vazio = 0,5%; 0 = nunca; 100 = sempre, útil para testes). Poções e comidas nunca saem como variante.
- **Drops > editar**: mostra o ícone comum e as 6 variantes com o bônus de cada uma, e o formulário
  **Dar este drop ao jogador** (comum ou uma variante), que coloca o item no banco de itens do jogador.
- Na edição de jogadores, uma variante aparece no nome como `Nome:efeito` (ex.: `Kunai:dourado`); pode ser digitada
  assim nos campos de nome, na mochila ou no banco para dar uma variante de arma/colete/bandana.
- Depois de trocar o ícone de um item ou drop, rode `python3 tools/variantes_itens.py /caminho/do/jogo` para gerar de novo
  os ícones das variantes (sem eles, a variante usa o ícone comum).

| efeito | rótulo | bônus extra | visual |
|---|---|---|---|
| `dourado` | ✦ Dourada | +10% do atributo principal (mín. +1) | item recolorido em ouro + brilhos |
| `brilho` | ✦ Brilhante | +2% Chance de Crítico | aura branca/ciano + estrela |
| `raio` | ✦ Elétrica | +2% Esquiva | raios azul-branco em volta |
| `chama` | ✦ Flamejante | +5 Poder de Ataque | aura de fogo laranja |
| `gelo` | ✦ Congelada | +5 Poder de Defesa | tom azul gelado + cristais |
| `sombra` | ✦ Sombria | +3% Economia de Chakra | aura roxa/preta |

## Dicas

- **Evento de experiência em dobro**: Configurações → multiplicador de experiência = 2 → Mensagem para todos.
- **Jogador travado** (luta, duelo, troca): ficha do jogador → *Mandar para a vila / destravar*.
- **Manutenção**: feche o jogo (só administradores entram), faça o que precisa e abra de novo.

## Entrar no painel, confirmações e auditoria
- Ao abrir `admin.php`, `admin_missoes.php` ou `gm.php` o painel pede a sua senha de novo (vale até fechar o navegador ou 30
  minutos sem uso; "Fechar o painel" no menu encerra). Ações perigosas (apagar, banir, promover/rebaixar, manutenção em massa,
  PvP/e-mail) pedem a senha mais uma vez.
- **Registro de auditoria** (`admin.php?do=auditoria`): filtre por autor, ação, ID do alvo, cargo e período.
- **Lista de IPs** (opcional) em `config.php`: `"adminips" => "203.0.113.5, 10.0.0.0/8"`.

## GameMasters (gm.php)
- Para promover: ficha do jogador → **Cargo: GameMaster** (`acesso = 3`, nível de acesso "Jogador"). Para rebaixar, volte o cargo
  para "Nenhum". As duas mudanças pedem a sua senha e ficam na auditoria. O GM aparece com selo dourado ao lado do nome
  (perfil, listas, chats) e com ícone próprio no mini mapa.
- O GM **pode**: moderar o fórum in-game (apagar tópicos e respostas de qualquer jogador; cada exclusão fica na auditoria como `forum_apagar`, com autor e trecho do texto); ver jogadores online; ver a ficha de um jogador (situação, histórico, lutas PvP, ações da equipe sobre ele);
  **destravar** (encerra duelo ativo como desistência, cancela troca e luta, manda para a vila natal); **silenciar** por 1 a 10080
  minutos e liberar a fala (chat global, chat do mapa, fórum, mensagens privadas); apagar falas dos chats e mensagens/tópicos do
  fórum; **liberar a recuperação de senha por 1 hora** (o jogador cria a senha nova sozinho, sem as respostas secretas, provando
  conta, personagem, ano, e-mail e level); mandar **mensagem privada como GM**; ver as próprias ações.
- Também **pode**: mandar a **mensagem para todos** (a mesma janela do admin, com auditoria `gm_mensagem_todos`); ver as
  **caveiras** do PvP (só leitura: limpar caveira e banir continuam com o administrador); **teleportar** um jogador para uma
  coordenada (encerra luta, duelo e troca; uma vila na coordenada deixa o jogador dentro dela; auditoria `gm_teleportar`); ver o
  **Mapa do jogo** (sem os links de edição). Teleporte e mapa podem ser desligados em **Configurações gerais → Painel do GM**
  (`control.gm_teleporte` / `control.gm_mapa`, ligados por padrão).
- O administrador vê os dois painéis: no jogo aparecem os links "Painel do Administrador" e "Painel do GameMaster", e cada
  painel tem um atalho para o outro. As permissões acima não valem para o administrador.
- O GM **não pode**: ver/definir senhas, hashes, respostas ou códigos; dar ou tirar itens, Ryou, experiência ou níveis; editar
  fichas, conteúdo (inimigos, itens, vilas, missões) ou configurações; banir ou apagar contas; agir sobre administradores ou outros
  GMs; abrir o `admin.php`; participar do PvP no mundo (nem atacar nem ser atacado).

## PvP no mundo e caveiras
Configurações gerais → **PvP no mundo** (ligado por padrão desde `tools/pvp_caveira.sql`; mudar pede a senha; regras completas em [PVP.md](PVP.md)):

| Opção | Padrão | Efeito |
|---|---|---|
| PvP no mundo | Ligado | Liga o link "Atacar" (lista "PvP: ninjas por perto" no explorar) e o `pvp.php`. |
| Diferença máxima de nível | 30 | 0 = sem limite. |
| Nível seguro | 15 | Abaixo dele ninguém ataca nem é atacado (o nível mínimo de duelos também vale). |
| Espera para o mesmo jogador | 60 min | Nos dois sentidos. |
| Espera entre ataques | 2 min | Do mesmo atacante, a qualquer alvo. |
| Ryou perdido | 10% | Do Ryou carregado; vai para o vencedor até o limite que ele pode carregar (nada some). |
| Chance de perder item / máximo | 25% / 1 | Por item. Só equipamentos e poções (itens de missão e especiais nunca). |
| De onde saem os itens | Mochila e equipados | Ou só a mochila (o bônus do item equipado perdido sai do derrotado). |
| Variantes raras | Protegidas | Ou podem ser perdidas. |
| Caveira branca | 24 h, +25% | Quem mata sem ser em legítima defesa (a vítima não o atacou nos últimos 15 min). Se morrer, chance de perder item maior. |
| Caveira vermelha | 3 mortes, 14 dias | 3 mortes injustas dentro do tempo da branca. Se morrer, perde **todos** os itens (mochila e equipados; o banco só se marcado). |
| Banimento automático | 30 dias | Quem mata de novo com caveira vermelha: banido com prazo e perde metade dos itens (sorteados); registrado. |
| Caveira exposta | 30 min | Depois de cada morte injusta quem tem caveira continua atacável **mesmo offline** (na última casa do mapa) e não entra em vilas. 0 = desligado. |

- O item perdido vai para a mochila do vencedor **só se houver espaço**; senão fica com o derrotado (caveira vermelha: é destruído).
- O derrotado volta para a vila natal com 1/4 do HP (como na morte do PvE). O defensor é avisado (janela + histórico).
- **Caveiras (PvP)** no menu: lista de quem tem caveira ou banimento com prazo, com "Limpar caveira" e "Banir (dias)".

## E-mails do jogo
Configurações gerais → **E-mails do jogo** (desligados por padrão; mudar pede a senha). Ligados: verificação no cadastro (opção
"Verificar e-mail") e link de nova senha por e-mail; precisa de servidor de e-mail. Desligados: conta liberada na hora, e-mail
opcional; recuperação pelas perguntas secretas ou pelo código de recuperação escolhidos no cadastro, ou pelo GM.

## Variantes raras: editar os efeitos
`admin.php?do=variantes`: nome pt/en, cor, bônus (`atributo,valor` ou vazio = percentual do atributo principal), percentual,
peso no sorteio e ativa. Os 6 efeitos são fixos (cada um tem ícones em `layoutnovo/equipamentos/variantes/EFEITO/`, gerados por
`tools/variantes_itens.py`). Desativar (ou peso 0) só impede novos sorteios; itens existentes continuam valendo.

## Morte para inimigos e saída no meio da luta (Configurações principais)
- **Ryou perdido**: base % + nível × % por nível, com máximo (padrão 10 + 1×nível, máx. 50%).
- **Experiência perdida**: nível × % da barra do nível atual, com máximo (padrão 0,5×nível, máx. 25%); nunca desce de nível.
- **Chance de perder um item comum da mochila**: nível × %, com máximo (padrão 0,25×nível, máx. 15%).
- **Nível protegido** (padrão 5): até ele só o Ryou é cobrado. A tela mostra uma tabela com os valores atuais nos níveis 1–80.
- **Sair no meio da luta**: minutos para considerar a luta abandonada (padrão 5; 0 = não verifica) e a chance (%) de
  logout/abandono contar como morte (padrão 50; 100 = sempre; 0 = nunca, só cancela). Duelos: sempre derrota.
- **Caveiras**: duração da branca (horas), mortes para a vermelha, duração da vermelha (dias), banco de itens, dias do
  banimento automático e se ele também destrói metade dos itens. Todos validados por faixa; mudar pede a senha.

## Jutsus de missão
- Editor de missões: campo **Jutsu de recompensa** (missões opcionais). Editor de jutsus: **Exclusivo de missão** — o
  painel recusa colocar um jutsu exclusivo na tabela de níveis ou num pergaminho.


## Durabilidade e reparo

Configurações principais → **Durabilidade**: ativa/desligada (`control.durabilidade_ativa`) e preço do reparo em % do valor
do item por um reparo completo (`control.reparo_pct`, padrão 30). Nos editores de item e de drop, o campo **Durabilidade**
é o número de vitórias até quebrar (0 = infinita); vale para os itens que entrarem no jogo depois. Regra e números em
[DURABILIDADE.md](DURABILIDADE.md).

## Pilhas, venda e comprador distante

No editor de itens e de drops: caixas **Não pode ser vendido em loja** e **Empilhável** + máximo da pilha. Em Configuração principal: pilha dos consumíveis da loja, comprador de pergaminhos nas vilas (sim/não) e % pago pelo comprador distante. Em NPCs do mapa: campo **Papel** (Comprador de pergaminhos).

## Especializações (admin.php?do=classes)
Modificadores de cada classe, bônus do Senjutsu por classe, descrições pt/en e o nível para escolher. A escolha do jogador é definitiva; em `edituser` o administrador pode trocar a especialização (0-3) em casos excepcionais — fica no registro de auditoria e não recalcula status nem jutsus. Simule antes de mudar: `tests/classes_sim.py`.
