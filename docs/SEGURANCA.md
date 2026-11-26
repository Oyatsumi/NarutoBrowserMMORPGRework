# Segurança

Resumo do que foi revisado e corrigido na versão 4.0 e de como o jogo se protege.

## Contas e login
- Senhas com `password_hash()` (bcrypt). Hashes MD5 antigos são aceitos uma vez e convertidos no primeiro login.
- Cookie `narutorpg` = `id conta assinatura lembrar` com assinatura **HMAC-SHA256** (segredo do `config.php` +
  hash da senha): trocar a senha invalida todos os cookies antigos. Flags `HttpOnly`, `SameSite=Lax` e `Secure`
  em HTTPS.
- Bloqueio de força bruta: 5 erros em 15 minutos bloqueiam o IP (login e troca de senha).
- Recuperar senha por **link com token** de uso único e validade de 1 hora (antes, qualquer pessoa trocava a
  senha de qualquer conta sabendo o e-mail). Respostas iguais exista ou não a conta.
- "Ativar conta" só reenvia o código (antes ativava qualquer conta sem verificação).
- Login simultâneo do mesmo personagem de IPs diferentes continua bloqueado.
- **Contas por IP** (`control.loginip_max`, padrão 3, em Configurações gerais; 0 = sem limite): o login pelo formulário é
  recusado com mensagem clara se já houver N contas *logadas* do mesmo IP. "Logada" = `users.loginip` igual ao IP e
  `onlinetime` nos últimos 10 minutos (a mesma janela de "online" do jogo). O IP é gravado no login (`loginip`, junto com
  o `ipadress` antigo); o logout apaga o `loginip` (libera a vaga) e uma sessão parada por 10 minutos sai da conta sozinha.
  Entrar de novo na mesma conta não gasta outra vaga. A checagem roda sob `GET_LOCK` por IP (dois logins ao mesmo tempo não
  passam do limite). O IP vem só de `REMOTE_ADDR`, como `painelip()`: atrás de proxy reverso configure o servidor web
  (ex. `mod_remoteip`/`real_ip`) para o `REMOTE_ADDR` ser o do jogador. Vale para todos, **inclusive a equipe** (não há
  exceção para admin/GM). O limite só atua no login: cookies "Lembrar-me" já gravados continuam valendo. O GM não vê IPs;
  o admin continua vendo só o `ipadress` na ficha. Migração: `tools/login_ip.sql`. Teste: `tests/login_ip_teste.py`.
- Formulário de login para gerenciadores de senha: nome da conta em `type="text"` com `autocomplete="username"` e senha com
  `autocomplete="current-password"` no mesmo `<form method="post">` (login.php fica fora da navegação AJAX). O nome da conta
  deixou de ser um campo de senha escondido (o Chrome não salvava o par); só a senha tem Mostrar/Ocultar. Cadastro e troca de
  senha usam `autocomplete="new-password"`.

## Entrada de dados
- O tratamento global (`addslashes` + `htmlspecialchars` UTF-8) foi mantido e completado com `intval()` em
  números, listas brancas para tudo que vira nome de coluna (slots, atributos de itens, ordenação de listas) e
  validação de faixas (quantias negativas, maiores que o saldo ou que o limite da coluna).
- Todos os `extract($_POST)` foram removidos (permitiam, por exemplo, alterar o ouro de outro jogador).
- SQL injection corrigida em: `ORDER BY` das páginas de ajuda e ranking, mapa global, slot do drop, página
  registrada em `users.pagina` (vinha da URL), fórum, mensagens, chat e vários IDs numéricos sem aspas.
- O parâmetro `?idioma=` e o cookie `idioma` só são aceitos se estiverem em `$idiomasdisponiveis`.

## Regras do jogo (exploits)
- Banco, trocas, doações, alquimia, missões, graduação e vitória em batalha usam `UPDATE` condicional ou
  transações com `SELECT ... FOR UPDATE`: cliques duplos ou requisições paralelas não duplicam itens, Ryou nem
  recompensas (testado com dezenas de requisições simultâneas).
- Trocas exigem a confirmação dos dois lados sobre a **mesma** oferta (hash da oferta no link) e reconferem itens
  e Ryou na hora de executar.
- Duelos: desafio e aceite conferem online, mesmo lugar, fora de luta/troca/outro duelo e Ryou da aposta (de novo no
  aceite, com os dois jogadores travados). A aposta sai das duas contas no aceite com `UPDATE ... AND gold >= aposta`
  (as duas linhas ou nenhuma) e o prêmio é pago uma vez só, na mesma transação que muda o estado para `fim`. Todas as
  ações são POST com token CSRF e número do turno; não dá para jogar fora da vez, nem duelar consigo mesmo. A mochila
  (`backpack.php`) fica bloqueada durante o duelo (poções só na própria vez, pela tela do duelo).

## Condições de corrida (itens e Ryou duplicados)
Duas requisições do mesmo jogador ao mesmo tempo (clique duplo, duas abas, script com dezenas de POSTs em paralelo)
liam o jogador, calculavam o resultado no PHP e regravavam a mochila/os equipamentos inteiros: a última gravação
apagava a outra e o mesmo item ficava em dois lugares (ex.: desequipar a arma e guardá-la no banco juntos deixava a
arma na mochila **e** no banco). As proteções usadas agora:
- **Transação + `SELECT ... FOR UPDATE`** em toda ação que regrava vários campos a partir dos dados lidos: banco
  (`funcaoitens.php`), troca (`troca.php`), mochila (`backpack.php`), desequipar (`desequipar.php`) e alquimia. A
  segunda requisição espera o `COMMIT` da primeira e já lê os dados novos. Os jogadores são travados sempre em ordem
  de id (sem deadlock). Um `die()` antes do `COMMIT` fecha a conexão e o MariaDB desfaz tudo.
- **`UPDATE` condicional** conferindo as linhas afetadas onde basta uma query: `gold=gold-X WHERE gold>=X`
  (doação), `AND bpN='item'` (usar item da mochila), `AND dropcode=...` (drop), `AND currentfight<>'0'` (vitória).
  A doação de Ryou (`users.php`) roda numa transação e só credita se o outro jogador não passar do limite da coluna.
- Links do banco que agem sobre a **posição** de um item (doar, deletar) levam também um código do item: um link
  antigo não pega o item que passou a ocupar aquela posição.
- Teste: `tests/corrida.py URL PASTA [N]` dispara N requisições simultâneas em cada cenário (desequipar x banco,
  mochila x banco, troca concluída pelos dois lados, doações, envio de item, alquimia, entradas como `1e9`, `-0`,
  `0x10`, arrays) e confere no banco que nenhum item ou Ryou foi criado. O servidor de teste precisa atender em
  paralelo (`PHP_CLI_SERVER_WORKERS=8 php -S ...`).

## Painel do administrador
- Acesso só para `authlevel = 1` (removida a trava fixa por nome de personagem). Além disso: senha de novo, confirmação, auditoria e lista de IPs (veja "Painéis da equipe" abaixo).
- Todos os formulários e links de ação com token anti-CSRF.
- Upload de imagens: tipo verificado pelo conteúdo (`getimagesize`), tamanho máximo, regravação pela GD com nome
  fixo (`{id}.jpg`/`.gif`/`.png`) — o nome enviado pelo usuário nunca é usado. As pastas de imagens não executam PHP.

## Servidor
- `config.php`, `templates/`, arquivos `.sql/.md/.psd/.sh` e arquivos ocultos bloqueados pelo `.htaccess`.
- Arquivos que só devem ser incluídos não fazem nada se abertos diretamente.
- Erros do PHP e do banco vão para o log; o jogador vê só uma mensagem genérica (`debug => false`).
- Cabeçalhos `X-Frame-Options`, `X-Content-Type-Options` e `Referrer-Policy`.
- O instalador cria um usuário de banco com permissão apenas no banco do jogo, gera o segredo aleatório e deixa
  os arquivos do jogo somente leitura para o Apache (exceto as pastas de imagens).

## Recomendações
- Use HTTPS (o instalador configura com Let's Encrypt).
- Faça backup do banco (`mysqldump narutorpg > backup.sql`) regularmente.
- Mantenha o sistema atualizado (`apt upgrade`).

## Painéis da equipe (admin.php, admin_missoes.php, gm.php) — `painelseguranca.php`
- **Senha de novo para abrir o painel**: além do login, o painel pede a senha. Fica valendo um cookie de sessão próprio
  (`narutorpg_painel`, `HttpOnly`, `SameSite=Strict`) com HMAC (segredo + hash da senha + cargo): expira ao fechar o
  navegador ou após **30 minutos sem usar o painel**; trocar a senha ou o cargo derruba o painel aberto. "Fechar o painel"
  no menu apaga o cookie sem sair do jogo.
- As senhas erradas nessa tela (e na confirmação) contam na tabela `loginfail`, como o login: 5 erros em 15 minutos bloqueiam o IP.
- **Confirmação com senha** antes de ações perigosas: apagar vila/inimigo/item/drop/jutsu/terreno/conta/missão/NPC, banir,
  mudar cargo/nível de acesso (promover/rebaixar admin ou GM), trocar a senha de alguém, manutenção em massa (resetar duelos,
  restaurar todos, apagar contas...), limpar chats/bloqueios, mensagem para todos e mudar as opções de **PvP no mundo** ou de
  **e-mail**. A tela de confirmação reenvia o mesmo formulário; nada é executado antes da senha certa.
- **Registro de auditoria** (`adminlog`, página `admin.php?do=auditoria` com filtros): entradas no painel, senhas erradas,
  IP bloqueado, cada formulário/ação enviado (campos resumidos, **senhas nunca gravadas**), confirmações, todas as ações do GM,
  banimentos automáticos do PvP e tentativas de recuperação de senha.
- **Lista de IPs** opcional: `config.php` → `"adminips" => "203.0.113.5, 10.0.0.0/8"` (IPv4/IPv6, faixas CIDR). Vazio = qualquer IP.
  O instalador pergunta. Fora da lista: "Acesso negado" (HTTP 403), registrado.
- Revisão: todos os POST dos painéis exigem token CSRF; links GET de ação levam `csrf=`; colunas vindas do usuário passam por
  lista branca; jogador comum e GM são redirecionados de **todas** as rotas do admin.php/admin_missoes.php (testado rota a rota,
  GET e POST). Cabeçalhos `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` conferidos; os painéis mandam também
  `Cache-Control: no-store`.

## Cargos: administrador, GM e banido
- `users.authlevel`: 0 jogador, 1 administrador, **2 banido** (valor histórico, mantido). `users.acesso`: 0 comum (o 1 = VIP foi removido na 5.0), 2 Tutor,
  **3 GameMaster**. GM = `acesso 3` com `authlevel 0` (`ehgm()` em lib.php) — por isso o GM **não** usa authlevel 2.
- O GM nunca vê senha, hash, respostas secretas nem códigos de recuperação (nem o admin vê hashes em lugar nenhum).

## Senhas e recuperação
- `senha_hash()` usa **Argon2id** quando o PHP tem (`password_algos()`), senão bcrypt custo 12. MD5 antigo e bcrypt antigo são
  refeitos no próximo login (`password_needs_rehash`). Respostas secretas e códigos de recuperação usam o mesmo hash.
- Sem e-mail (`control.emailativo = 0`, padrão): no cadastro o jogador escolhe **perguntas secretas** (2, da lista ou próprias;
  respostas normalizadas) **ou** um **código de recuperação** de 32 caracteres (random_bytes), mostrado uma vez.
  - Perguntas: recuperar exige conta + personagem + as 2 respostas + ano de criação + e-mail cadastrado + level atual. Erro genérico.
  - Código: conta + código; ao usar, o código é consumido (UPDATE condicional — dois envios simultâneos não usam o mesmo código)
    e um novo é mostrado.
  - Tudo limitado pela `loginfail` e registrado na auditoria. Contas antigas recebem um aviso para escolher um método
    (`users.php?do=perguntas`, pede a senha atual).
  - O GM pode **liberar a recuperação por 1 hora** (sem as respostas, mas com todos os outros dados); ele não cria nem vê senha.
- Senha definida pelo admin na ficha vira provisória (`trocarsenha = 1`): o jogador só consegue usar a tela de troca de senha.

## PvP no mundo
- Ataque só por POST com CSRF; tudo numa transação com os dois jogadores travados (id crescente) e as regras conferidas de novo
  depois da trava (online, distância, vila, luta/duelo/troca, níveis, esperas com `LOCK IN SHARE MODE`). Itens e Ryou movidos com
  `UPDATE` condicional; testado com 10 ataques simultâneos (1 luta) e o código de recuperação com 5 envios simultâneos.
- troca.php recusa trocar durante um duelo; "Mandar para a vila" (admin) e "Destravar" (GM) encerram o duelo ativo como desistência.
