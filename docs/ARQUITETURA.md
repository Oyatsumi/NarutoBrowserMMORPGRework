# Arquitetura do código

O jogo é PHP **procedural**, sem framework, no estilo dos jogos de navegador do fim dos anos 2000.
Cada arquivo `.php` da raiz é uma "página" (ou um conjunto de ações) e escolhe o que fazer pelo parâmetro `do`
da URL. Foi mantido assim de propósito: a versão 4.0 refatorou e documentou o código sem mudar o estilo do autor.

## Ciclo de uma requisição

```
navegador ──► index.php?do=gotown:3
               │ include lib.php        (helpers, conexão, tratamento global da entrada)
               │ include cookies.php    (checkcookies() valida o cookie e devolve $userrow)
               │ doquery("SELECT * FROM {{table}} ...", "control")  → $controlrow
               │ roteamento: if ($do[0] == "gotown") { include('towns.php'); travelto(3); }
               ▼
             função da ação monta $page (string HTML / heredoc)
               ▼
             display($page, "Título")   (lib.php)
               ├─ templates/primary.php   layout geral (topo, colunas, rodapé, JavaScript)
               ├─ templates/leftnav.php   mini mapa, direção, cidades, mensagens, histórico
               ├─ templates/rightnav.php  personagem, barras, equipamentos, mochila, jutsus
               └─ parsetemplate(): troca {{chave}} pelos valores e envia a página
```

Ações que só mudam dados (comprar, andar, guardar item...) gravam no banco e fazem `header("Location: ...")`
de volta para a página certa, às vezes com `?conteudo=mensagem` para mostrar um aviso.

## Convenções

- **Banco**: `doquery("SELECT ... FROM {{table}} WHERE ...", "users")` — `{{table}}` vira `prefixo_users`
  (prefixo definido no `config.php`). Resultados com `mysqli_fetch_array`.
- **Entrada do usuário**: o `lib.php` aplica `addslashes` + `htmlspecialchars` em todo `$_GET`, `$_POST` e
  `$_COOKIE` logo no início (padrão original do jogo). Texto pode ir entre aspas no SQL e ser mostrado na
  página; números sempre passam por `intval()`/`pegarint()`.
- **Templates**: `templates/*.php` definem `$template` com um heredoc `FIMDOTEMPLATE`; as marcações `{{campo}}`
  são preenchidas por `parsetemplate($template, $array)`.
- **Arquivos só de inclusão** (`fight.php`, `explore.php`, `towns.php`, `funcoesinclusas.php`...) começam com
  `if (!defined('NARUTO_RPG')) ...` e não fazem nada se forem abertos direto no navegador.
- **Estado do jogador** fica todo na linha da tabela `users` — inclusive listas em texto no formato `a,b,c`
  (veja [BANCO_DE_DADOS.md](BANCO_DE_DADOS.md)). Não há sessão no servidor.
- Comentários e código em português; os textos exibidos passam por `t()`/`tf()` (veja [Idiomas](#idiomas-i18n)).

## Arquivos

| Arquivo | Papel |
|---|---|
| `lib.php` | Conexão (`opendb`), `doquery`, templates, `display()`/`admindisplay()`, tratamento da entrada, senhas (`senha_hash`/`senha_confere`), cookie assinado, CSRF, cabeçalhos de segurança. |
| `config.php` | Dados do banco, prefixo, segredo, fuso e modo debug (gerado pelo instalador). |
| `cookies.php` | `checkcookies()`: valida o cookie `narutorpg` (id, conta, assinatura HMAC, lembrar-me). |
| `login.php` | Login/logout, bloqueio de força bruta (tabela `loginfail`), migração de senhas MD5. |
| `users.php` | Cadastro, verificação de e-mail, recuperar/mudar senha, doar Ryou e duelos PvP. |
| `ativarconta.php` | Reenvio do código de ativação. |
| `index.php` | Página principal: vila, exploração, roteador das ações de vila/mapa/luta, ficha, mapa global, chat global. |
| `towns.php` | Pousada, lojas (comprar), mapas, viajar entre vilas. |
| `explore.php` | Andar pelo mapa (botões e "andar até" no mini mapa) e chance de encontrar inimigos. |
| `fight.php` | Batalhas contra inimigos, vitória, drops, morte, level up e durabilidade. |
| `heal.php` | Jutsus medicinais fora da batalha. |
| `funcoesinclusas.php` | Funções compartilhadas: ícones da mochila, tempo dos jutsus, Senjutsu, falas de NPC, elementos, tooltips dos itens. |
| `desequipar.php` / `backpack.php` | Desequipar itens e usar/equipar itens da mochila. |
| `funcaoitens.php` | Banco: Ryou e itens guardados, enviar item, mochila. |
| `troca.php` | Troca de itens/Ryou entre jogadores (confirmação dos dois lados). |
| `alquimia.php` | Alquimia: receitas do banco (`naruto_receitas`), experimentar, refino, infusão de variantes, poções, cópia de pergaminhos, nível de alquimia ([ALQUIMIA.md](ALQUIMIA.md)). |
| `missoes.php` / `treinamentoequests.php` | História principal (21 missões), painel de missões e de treinos. |
| `graduacao.php` | Graduação (Genin, Chuunin, Jounin). |
| `especializacao.php` | Escolha (definitiva) da especialização no jogo, a partir de `control.classenivel`, com confirmação. Regras em [ESPECIALIZACOES.md](ESPECIALIZACOES.md). |
| `senjutsu.php` / `doujutsu.php` / `jutsudebusca.php` | Treino e uso do Senjutsu, olhos e Jutsu de Busca. |
| `falapersonagens.php` / `localpersonagens.php` | NPCs das missões no mapa. |
| `cidadesconteudo.php` | Botões de serviços de cada vila. |
| `outros.php` / `outroseatributos.php` | Escolha de avatar e distribuição de pontos. |
| `chat.php` | Chat da coordenada do mapa. |
| `forum.php` / `pm.php` / `mainmsg.php` | Fórum interno, mensagens privadas e janela flutuante de mensagens. |
| `rank.php` / `mostrarchar.php` | Ranking e perfil público. |
| `help*.php` | Ajuda do jogo (geral, inimigos, itens, jutsus, níveis) — listas lidas do banco. |
| `admin.php` | Painel do Administrador. |
| `painelseguranca.php` | Segurança dos painéis: lista de IPs, senha de novo (cookie de 30 min), confirmação de ações perigosas, auditoria (`adminlog`). |
| `gm.php` | Painel do GameMaster (`acesso = 3`): destravar, silenciar, moderar chats/fórum, liberar recuperação de senha, mensagem como GM. |
| `pvp.php` | PvP no mundo: confirmação, luta automática com o motor do duelo (`dueloacao()`), perdas, caveiras e resultado. |
| `templates/` | Layout (primary, leftnav, rightnav, minimap, admin...) e formulários. |
| `images/`, `layoutnovo/` | Imagens. Cartas dos inimigos em `layoutnovo/monstros/{id}.jpg`. |

## Vilas e terrenos (dados, não código)

- `cidadesconteudo.php` concentra as regras das vilas lidas de `naruto_towns`: `dadosdacidade()`, `cidadetemservico($vila, "loja")`,
  `cidadesecreta()`, `vilasnatais()`, `vilanataldoalinhamento()` (usada por fight.php e admin), `alinhamentodoregistro()` /
  `opcoesvilasnatais()` (registro), `cabecalhodacidade()` (título em imagem ou texto) e `conteudo()` (painel de serviços).
  towns.php, graduacao.php, senjutsu.php e jutsudebusca.php conferem as colunas `tem_*` em vez de ids.
- `lang/en/conteudo_vilas_banco.php` é um arquivo de tradução que lê `name_en`/`kage_en`/`descricao_en` do banco, então
  `t($townrow["name"])` traduz vilas criadas pelo admin em qualquer tela.
- `funcoesterreno.php`: `terrenodacasa()`, `nomesdosterrenos()`, `nomeexibidoterreno()` e `terrenosdoinimigo()`; usado pelo
  mini mapa e por `escolhermonstro()` (fight.php), que filtra os inimigos pela coluna `monsters.terrenos`.
- `templates/minimap.php`: `terrenodacasa($lat, $lon)` escolhe o chão de cada casa pela tabela `naruto_terrenos`
  (maior prioridade; coordenada "tremida" ±2 por um hash fixo para bordas irregulares).
  Cada casa (30x30) usa ladrilhos de `images/terreno/` (`fundodacasa()`): chão `NOME_0..3.png` sorteado por hash, bordas
  `NOME_b_{n,s,l,o}` e cantos `NOME_c_{no,nl,so,sl}` do vizinho de maior prioridade (máscara irregular pontilhada) e uma
  decoração `deco_NOME_K.png` do bioma (onde o sorteio de árvores cai; só em casa sem vila/NPC/jogador). Os ladrilhos são
  gerados de `images/terreno_NOME.jpg` por `tools/gerar_tiles_terreno.py` (Pillow + numpy).

## Comunicação entre páginas sem sessão

- **`users.pagina`** guarda a última página de visualização do jogador (gravada por `templates/getheader.php`);
  ações rápidas (desequipar, mochila, mensagem) devolvem o jogador para lá.
- **`users.mainmsg`** é uma mensagem "de uma vez só": a ação grava, a próxima página mostra a janela flutuante
  (`templates/mostrarmainmsg.php`) e zera o campo.
- **Trocas** são um protocolo guardado nas linhas dos dois jogadores; as telas de espera usam `<meta refresh>`.
- **Duelos PvP** (`users.php?do=batalha1` = saguão, `do=duelo` = luta) ficam na tabela `duelos`. Toda ação abre uma
  transação que trava a linha do duelo e depois os dois jogadores (id crescente); o formulário leva o número do turno,
  então cliques duplos/duas abas não jogam duas vezes. O tempo de cada turno (60 s) é conferido "preguiçosamente" a
  cada página de qualquer um dos dois (tempo esgotado = perde a vez; 3 = derrota). A tela de espera usa `<meta refresh>`
  e um relógio em JavaScript. Regras completas no comentário do bloco "Duelo PvP" do `users.php`.
  **Jutsus no duelo** (e no PvP do mundo, que usa o mesmo `dueloacao()`): estado em `duelos.jutsuestado` (JSON do
  `funcoesjutsus.php`, lados `1`/`2`). Tipos 1/2/6 via `aplicarjutsu()`, 4/5 via `jutsuaplicarefeito()`; o dano passa pela
  +Defesa, guarda e `jutsureduzirdano()`; o ataque usa `jutsumultdano()`, Foco crítico e Esquiva aumentada. Rodada = 2 turnos:
  depois de cada turno par `dueloticar()` roda `jutsufimdoturno()` (dano por turno também para em 1 HP = derrota). Atordoado perde
  a vez sozinho em `duelofimdavez()` (conta como jogada, não como atraso). Recarga bloqueia no servidor e aparece desabilitada no
  select; ícones de status sobre o avatar (`cartaodojogador(..., $icones)`).

## Itens, bônus de combate e drops

- **Até 3 bônus por item**: `items.special`, `special2`, `special3` e `drops.attribute1`, `attribute2`, `attribute3`
  (formato `atributo,valor`, `X` = vazio). Equipar/desequipar passa por `bonusdoitem()`/`bonusdodrop()`
  (`funcoesinclusas.php`); a loja (`towns.php` buy3) soma/retira os três campos. A lista branca de colunas está em
  `atributovalido()` e em `admcodigoespecial()` (admin.php).
- **Bônus de combate** (colunas novas de `users`, somadas pelos equipamentos; limites em `bonuscombate()`):

  | Coluna | Efeito | Fórmula / onde |
  |---|---|---|
  | `critico` | % de crítico | se o hit excelente antigo (rand(1,150) <= raiz(Força) + 2% Determinação) falhar, `rand(1,100) <= critico` também dobra o dano; máx. 50 (fight.php) |
  | `esquiva` | % de esquiva | se a esquiva antiga (rand(1,150) - 2% Agilidade <= raiz(Destreza)) falhar, `rand(1,100) <= esquiva` também desvia; máx. 40 (turnodoinimigo) |
  | `regenhp` | HP por passo | cada casa andada: `currenthp = min(currenthp + regenhp, maxhp)` (explore.php andarumacasa) |
  | `economiachakra` | % de chakra a menos | custo = `ceil(CH x (100 - economia)/100)`, mínimo 1, economia máx. 50 (`custochakra()`; fight.php e heal.php) |

- **Drops por inimigo** (`naruto_monster_drops`: `monster_id`, `drop_id`, `chance_percent`): na vitória (sem level up),
  `sortreardrop()` testa as linhas na ordem em que foram associadas (monster_drops.id; a primeira associada é sorteada primeiro) com chance `chance_percent x M`, onde
  `M = 25 x (1 + 2% Sorte) / (25 - 22% Chance de Drop)` — o mesmo fator que a regra antiga dava à Sorte e à Chance
  de Drop. A primeira linha sorteada é o drop (só cabe um, em `users.dropcode`). Inimigo sem linhas = regra antiga
  (1 em 25, drop qualquer com `mlevel` <= nível; drops de missão com `mlevel` >= 100 ficam de fora).
- **Drops consumíveis** (`drops.consumivel` = `hp,50` / `mp,40`): ao pegar, vão só para a mochila como uma poção comum
  (`nome,hp,50,1`), usada pelo `backpack.php` como as poções de sempre.
- A vitória e o "pegar drop" travam o jogador (`START TRANSACTION` + `SELECT ... FOR UPDATE`) e gravam Ryou/experiência
  de forma relativa (`gold=LEAST(gold+x, ...)`), para que requisições paralelas não dupliquem nem desfaçam nada.

## Jutsus configuráveis e pergaminhos
- **Tabela `spells`**: além de `type` (1 cura, 2 dano, 3 sono, 4 +dano, 5 +defesa, **6 suporte**), `mp`, `attribute` e `elemento`,
  cada jutsu pode ter `golpes` (1-4), `roubovida` (%), um **efeito extra** (`efeito`, `efeito_valor`, `efeito_turnos`,
  `efeito_chance`), `cooldown` (turnos), requisitos para aprender por pergaminho (`nivelminimo`, `classe` 0/1/2/3, `graduacao`)
  e textos `name_en`, `descricao`, `descricao_en` (traduzidos por `lang/en/conteudo_jutsus_banco.php`, lido do banco).
  Com os valores padrão um jutsu antigo funciona exatamente como antes.
- **Efeitos** (`jutsuefeitos()` em `funcoesjutsus.php`, incluído pelo `funcoesinclusas.php`): `queimadura`/`veneno` (dano por turno),
  `atordoar` (o inimigo perde o ataque; não renova enquanto ativo; inimigo imune 2 não é afetado), `escudo` (absorve X de dano),
  `buff_ataque`/`buff_defesa`/`buff_critico`/`buff_esquiva` (%), `debuff_ataque`/`debuff_defesa` no inimigo (%), `cura_turnos` e
  `dreno_chakra` (na hora). O mesmo efeito não acumula: fica o valor mais forte e a duração é renovada.
- **Estado da luta**: `users.jutsuestado` (JSON `{"efeitos": {lado: {efeito: {v, t, n}}}, "cd": {lado: {id: turnos}}}`, lados
  `jog`/`ini` no PvE). Zerado no 1º turno, na vitória, na morte, na fuga e em `encerrarluta()`. Uma rodada = ação do jogador →
  `turnodoinimigo()` (atordoamento, esquiva extra, `jutsureduzirdano()`: ataque enfraquecido, defesa reforçada e escudo) →
  `jutsufimdoturno()` (dano/cura por turno, contagem). O ataque físico usa `jutsumultdano()` e o crítico extra. Ícones 12x12 em
  `images/status/` (gerados por `tools/icones_status.py`) no cartão do inimigo (`cartaodomonstro(..., $icones)`) e em "Seus efeitos".
- **Funções também usadas pelo duelo** (`users.php`, ver acima): `aplicarjutsu(&$caster, &$alvo, $spell, &$estado)` (cura, dano com golpes,
  elemento e roubo de vida, efeito extra e recarga; não cobra chakra; devolve `dano`, `cura` e `msgs` no formato `i18n:`),
  `jutsuaplicarefeito()` (só o efeito, para os tipos 3-5), `jutsurecarga()`, `jutsuatordoado()`, `jutsubonus()`, `jutsumultdano()`,
  `jutsureduzirdano()`, `jutsufimdoturno()`, `jutsuicones()`, `jutsuestadoler()`/`jutsuestadotexto()`, `jutsudescricaoefeitos()`.
- **Aprender**: por nível (tabela `levels`, colunas `N_spells`, como sempre) ou por **pergaminho**: drop com `drops.ensina_jutsu`
  = id do jutsu. Na vitória ele só vai para a mochila como `"Pergaminho: Nome,jutsu,ID,1"` (sem variante rara); clicar nele na
  mochila (`backpack.php`) confere `jutsurequisitos()` (nível, classe, graduação), grava com `jutsuaprender()` e some. Quem já
  conhece o jutsu fica com o pergaminho (pode guardar no banco ou trocar). Fora da tabela do inimigo (regra antiga) pergaminhos
  nunca caem. Ícone: `layoutnovo/equipamentos/novos/pergaminho_ELEMENTO.gif` (`iconepergaminho()`, `tools/pergaminhos_elementos.py`).
- **Conteúdo**: jutsus 20-49 (15 por nível, 15 só por pergaminho, drops 102-116 em `naruto_monster_drops` com 0,3-0,5%),
  gerados por `tools/jutsus_novos.py` → `tools/jutsus_novos.sql` (usado pelo `build_sql.py`).

## Variantes raras dos itens
- Formato: sufixo `:efeito` no **nome** do item (`weaponname`, `slot1name`..., 1º campo de `bp1..bp8`, do banco e das
  trocas): `Kunai:dourado`, `Kunai+2:chama`. Itens antigos não têm `:` e continuam iguais. Como todo código que move
  itens já copia o nome inteiro, a variante sobrevive a equipar/desequipar/banco/troca sem colunas novas; as travas
  (`SELECT ... FOR UPDATE`) desses fluxos valem também para ela.
- `funcoesinclusas.php`: `variantesitens()` (tabela), `separarvariante()`, `variantedoitem()`, `nomecomvariante()`,
  `sortearvariante($chance)`, `bonusvariante($nome, $linha, "items"|"drops")` (lista "campo,valor"), `nomeitemhtml()`
  (rótulo colorido), `iconeitem($id, $drop, $nome, $sufixo)` (ícone da variante se o arquivo existir), `iconesequipados()`.
  `bonusdoitem()` já recebia o nome; `bonusdodrop($id, $sinal, $nome = "")` e `conteudoexplic(..., $nome = "")` ganharam
  um parâmetro opcional. `tnomeitem()` devolve "Nome ✦ Rótulo" em texto puro.
- Sorteio: `fight.php :: sortreardrop()` lê `monster_drops.chance_variante_percent` e grava o efeito em
  `users.dropvariante` junto com o `dropcode`; `drop()` monta o nome com a variante ao pegar o item.
- Banco/loja: `funcaoitens.php :: bancobonusitem()` soma/retira o bônus; `towns.php :: buy3()` retira o bônus (e o refino)
  do item vendido.
- Ícones: `layoutnovo/equipamentos/variantes/<efeito>/[drops/]<id>[d].gif`, gerados por `tools/variantes_itens.py`
  (Pillow + numpy; silhueta separada do fundo do quadrinho por inundação a partir dos cantos).
- Templates `showchar`/`onlinechar` usam `{{weaponicone}}`...`{{slot3icone}}`; `rightnav.php` monta nomes/ícones
  em variáveis próprias (o `display()` do lib.php troca `{{weaponname}}` por `t(nome)`).

## Idiomas (i18n)

O jogo tem dois idiomas: **português (`pt`, padrão)** e **inglês (`en`)**. O código continua em português e
**o próprio texto em português é a chave da tradução** (estilo gettext). Toda a infraestrutura fica no início do
`lib.php`.

### Como o idioma é escolhido

1. Parâmetro `?idioma=pt` / `?idioma=en` em qualquer página: vale na hora e é gravado no cookie `idioma`
   (1 ano, `HttpOnly`, `SameSite=Lax`).
2. Senão, o cookie `idioma`.
3. Senão, o **idioma padrão do jogo**, definido em *Configurações gerais* no Painel do Administrador
   (coluna `control.idiomapadrao`).

O jogador troca de idioma pelos links "Português | English" (`seletoridioma()`) que aparecem no **rodapé** do
jogo, na **tela de login** e na **barra do topo do painel do administrador**. `linkidioma()` monta o link mantendo
o jogador na mesma tela, sem repetir ações.

### Funções

| Função | O que faz |
|---|---|
| `idioma()` | Código do idioma em uso (`"pt"` ou `"en"`). |
| `t("Texto")` | Devolve o texto no idioma do jogador; sem tradução, devolve o próprio português. |
| `tf("Você ganhou %s Ryou.", $gold)` | Igual ao `t()`, com valores no lugar de `%s` (`vsprintf`). Um `%` literal é `%%`. |
| `i18n("Chave com %s", $valor)` | Monta o texto neutro `i18n:Chave com %s\|valor` para **gravar no banco**. |
| `textoi18n($texto)` | Exibe um texto gravado no banco: se começar com `i18n:`, traduz a chave e aplica os valores; senão devolve como está. |
| `seletoridioma()` | Links "Português \| English". |
| `localizarimagens($pagina)` | Troca imagens com texto em português pela versão do idioma, se existir. |

Dentro de heredoc use variáveis-função: `$t = "t"; $tf = "tf";` e depois `{$t("Texto")}`.

### Arquivos de tradução

`game/lang/en/*.php` — cada arquivo devolve um array `"texto em português" => "English text"`; todos os
arquivos da pasta são carregados e juntados. A chave tem que ser **exatamente** igual ao texto passado ao
`t()`/`tf()`. Nomes vindos do banco (inimigos, itens, jutsus, vilas...) são exibidos com `t($row["name"])` e
traduzidos em `lang/en/conteudo_*.php`; o banco continua guardando o nome em português.

### Mensagens gravadas no banco

Histórico, mensagens privadas e a janela flutuante (`mainmsg`) são lidos depois por outro jogador, cujo idioma não
se conhece na hora de gravar. Por isso são gravados no formato neutro `i18n:Chave em português|valor1|valor2`
(gerado por `i18n()`; `|` dentro dos valores vira `/`) e exibidos com `textoi18n()`. Mensagens antigas, sem o
prefixo, aparecem como foram gravadas.

### Imagens com texto

Imagens que têm texto escrito (botões, títulos, menus) podem ter uma versão por idioma numa subpasta com o código
do idioma: por exemplo `images/en/`, `images/24/en/`, `layoutnovo/buttons/en/`, `layoutnovo/menuslados/en/` e
`layoutnovo/titulo/en/`, com o **mesmo nome de arquivo** da original. `localizarimagens()` troca os caminhos na página
gerada; sem a versão traduzida, a imagem original é usada.

### Adicionando um idioma novo

1. Copie `game/lang/en/` para `game/lang/xx/` (`xx` = código do idioma) e traduza os valores (as chaves continuam
   em português).
2. Acrescente o idioma em `$idiomasdisponiveis` no `lib.php`: `array("pt" => "Português", "en" => "English", "xx" => "Nome")`.
3. Se quiser, crie as imagens traduzidas em `images/xx/`, `layoutnovo/buttons/xx/` etc.

O idioma passa a aparecer no seletor e na escolha do idioma padrão do painel.

### Encontrando traduções que faltam

Com `"debug" => true` no `config.php` (só em teste), cada texto sem tradução é registrado no log de erros do PHP
como `[traducao-faltando] texto`:

```bash
grep traducao-faltando /var/log/apache2/error.log | sort -u
```

Para contribuir com traduções, veja [TRADUCAO.md](TRADUCAO.md).

## Morte, luta abandonada e jutsus de missão
- `fight.php :: penalidademorte($nivel)` devolve os percentuais (`control.morte*`):
  `ryou = min(morteryoumax, morteryoubase + nível × morteryounivel)`; `exp = min(morteexpmax, nível × morteexpnivel)` % da
  barra (`levels[nível+1].N_exp − levels[nível].N_exp`, sem cair abaixo de `levels[nível].N_exp`);
  `item = min(morteitemmax, nível × morteitemnivel)` % de chance de destruir 1 item elegível (`itemperdivelnamorte()`:
  equipamento comum ou poção; nunca variante, pergaminho, item citado em missões). Até `mortenivelseguro`, exp e item = 0.
  Padrões: nível 1 → 11% Ryou; 10 → 20%/5%/2,5%; 20 → 30%/10%/5%; 40+ → 50%/20%/10%; 80 → 50%/25%/15%.
- `morrernaluta($id = 0, $motivo = "luta"|"logout"|"abandono")`: transação com `SELECT ... FOR UPDATE`; só age se
  `currentaction='Fighting'` (duas mortes simultâneas cobram uma vez). Logout/abandono: `mt_rand(1,100) <= mortelutachance`
  → morte; senão a luta é cancelada (Exploring). Grava o histórico (i18n) e, fora da luta, a janela `mainmsg`.
- `users.lutaultimaacao` (UNIX) é gravada ao começar a luta (explore.php) e a cada turno; `lutasabandonadas()` é chamada
  pelo index.php quando o jogador está lutando (e em 1 de 20 páginas para varrer até 5 outros jogadores).
  `login.php :: logout()` chama `morrernaluta(id, "logout")` e `dueloforcarderrota()` (users.php) para o duelo.
- Missões: `funcoesmissoesextras.php :: mx_recompensajutsu()` decide ensinar / pergaminho / compensação; o aprendizado
  entra no mesmo UPDATE da entrega (`spells=CONCAT(...)` com `NOT FIND_IN_SET` no WHERE). `spells.exclusivo_missao=1`
  é respeitado no level up (fight.php), no editor de níveis/drops/jutsus (admin.php) e na ajuda (help_spells.php).
