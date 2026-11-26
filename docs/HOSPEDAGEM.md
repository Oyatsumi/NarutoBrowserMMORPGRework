# Instalando numa hospedagem (sem acesso SSH/root)

Este guia é para hospedagens comuns (painel cPanel, Hostinger, InfinityFree, etc.), onde você só tem um
gerenciador de arquivos/FTP e o phpMyAdmin. Em uma **VM ou servidor próprio com SSH**, use o outro instalador
(`sudo bash install/install.sh`, veja [INSTALACAO.md](INSTALACAO.md)).

Há duas formas: o **instalador pelo navegador** (`install/instalar.php`, recomendado) ou a **instalação manual**
pelo phpMyAdmin. As duas terminam no mesmo ponto; depois siga "Depois de instalar".

## O que você precisa

- PHP 8.0 ou mais novo, com as extensões `mysqli`, `mbstring`, `gd` e `json` (quase todas as hospedagens já têm).
- Um banco MySQL 5.7+/8 ou MariaDB 10.3+.
- Os arquivos deste repositório: a pasta `game/`, o arquivo `sql/naruto_rpg.sql` e (para a Opção A) o `install/instalar.php`.

## Opção A: instalador pelo navegador (`instalar.php`)

1. No painel da hospedagem, abra **Bancos de dados MySQL** e crie um banco **vazio**, um usuário e uma senha, e dê ao
   usuário todas as permissões nesse banco. Anote o **servidor** (muitas vezes `localhost`), o **nome do banco**, o
   **usuário** e a **senha**.
2. Envie para a pasta pública do site (normalmente `public_html/` ou `htdocs/`):
   - **o conteúdo** da pasta `game/` (não a pasta em si; pelo gerenciador de arquivos você pode enviar um `.zip` e extrair lá);
   - o arquivo `install/instalar.php`;
   - o arquivo `sql/naruto_rpg.sql` (ao lado do `instalar.php`).
3. Abra `https://seu-site/instalar.php` no navegador. A tela:
   - confere a versão do PHP, as extensões, a permissão para gravar o `config.php` e se o `.sql` está lá;
   - cria na pasta o arquivo **`instalar_chave.php`**: abra-o pelo gerenciador de arquivos (editar/visualizar) e cole o código da linha
     `CHAVE:` no campo **Chave** (aberto pelo navegador ele não mostra nada). Isso garante que só quem tem acesso aos arquivos do site consegue instalar;
   - pede os dados do banco, o nome e o endereço do jogo, o fuso horário, o idioma padrão, se o cadastro confirma
     e-mail (deixe **Não** se a hospedagem não envia e-mails) e a conta do administrador (conta, personagem,
     e-mail e senha de pelo menos 8 caracteres).
4. Clique em **Instalar**. O instalador importa as 41 tabelas do jogo (o banco precisa estar sem tabelas `naruto_`),
   grava o `config.php` com um segredo aleatório e cria a conta de administrador.
5. No fim ele grava `instalar.lock` (não roda de novo) e tenta apagar `instalar.php`, `instalar_chave.php` e
   `naruto_rpg.sql`. Se a tela avisar que algum não pôde ser apagado, **apague pelo gerenciador de arquivos**.
6. Entre no jogo com a conta criada: o **Painel do Administrador** aparece no menu. Abra `https://seu-site/verificar.php`
   para conferir a instalação (tudo em verde).

> Se a importação parar por tempo (hospedagens muito limitadas), apague as tabelas `naruto_` pelo phpMyAdmin e use a
> Opção B, que importa pelo próprio phpMyAdmin.

## Opção B: instalação manual (phpMyAdmin)

### 1. Criar o banco de dados

1. No painel da hospedagem, abra **Bancos de dados MySQL** e crie um banco, um usuário e uma senha.
   Anote os quatro dados: **servidor** (muitas vezes `localhost`, mas algumas hospedagens dão um endereço
   como `sql123.exemplo.com`), **nome do banco**, **usuário** e **senha**.
2. Dê ao usuário todas as permissões nesse banco.

### 2. Importar o jogo no banco

1. Abra o **phpMyAdmin** e clique no banco criado (à esquerda).
2. Aba **Importar** → escolha `sql/naruto_rpg.sql` → **Executar**.
3. Ao terminar devem aparecer 41 tabelas começando com `naruto_`. O arquivo já traz tudo do jogo
   (inimigos, itens, armas, vilas, jutsus, missões...) e nenhum jogador.

> Se a importação parar por tempo ou tamanho, tente de novo marcando "Permitir interrupção" ou peça para
> o suporte aumentar o limite de upload. O arquivo tem cerca de 300 KB.

### 3. Enviar os arquivos

1. Envie **o conteúdo** da pasta `game/` (não a pasta em si) para a pasta pública do site — normalmente
   `public_html/` ou `htdocs/`. Pelo gerenciador de arquivos você pode enviar um `.zip` e extrair lá.
2. Confira que `index.php` ficou direto em `public_html/` (ou na subpasta que você quiser usar).

### 4. Configurar o `config.php`

Abra `config.php` (na pasta pública) e preencha:

| Campo | O que colocar |
|---|---|
| `server` | Servidor do banco (passo 1). |
| `user`, `pass`, `name` | Usuário, senha e nome do banco. |
| `prefix` | Deixe `naruto`. |
| `secretword` | Uma frase longa e aleatória (pelo menos 32 caracteres). Não compartilhe. |
| `cookiename` | Pode deixar `narutorpg`. |
| `timezone` | Ex.: `America/Sao_Paulo`. |
| `debug` | `false` (use `true` só para investigar um erro). |

Depois abra `https://seu-site/verificar.php`: a página confere a versão do PHP, as extensões, as tabelas e as
colunas do banco. Tudo deve aparecer em verde.

### 5. Criar a conta de administrador

1. No site, crie uma conta normal (**Criar conta**).
2. No phpMyAdmin, aba **SQL** do banco, rode (trocando `sua_conta` pelo nome da conta):
   ```sql
   UPDATE naruto_users SET authlevel=1, verify='1' WHERE username='sua_conta';
   ```
3. Entre de novo no jogo: o link do **Painel do Administrador** (`admin.php`) aparece no menu.

## Depois de instalar (as duas opções)

### HTTPS (opcional)

O `.htaccess` do jogo já traz um bloco que força o `https://`, mas **desligado**. Se o seu site abre em `https://`
(a maioria das hospedagens dá certificado grátis), abra o `.htaccess` e tire o `#` do começo das 6 linhas entre
`# FORCAR_HTTPS_INICIO` e `# FORCAR_HTTPS_FIM` (não tire das duas linhas de marcação). Sem certificado, não ligue:
o site ficaria inacessível.

### Ajustes no painel

Em `admin.php` → **Configurações**:

- **Nome do jogo**, **Endereço do jogo** (a URL do site) e **E-mail do administrador**.
- **Jogar sem conta (convidado)**: ligado por padrão; desligue se quiser exigir conta para jogar.
- Se a hospedagem não envia e-mails, deixe desligada a verificação de e-mail no cadastro.

### Permissões de pasta (opcional)

Para enviar imagens pelo painel (inimigos, itens, avatares), dê permissão de escrita (755 ou 775) às pastas
`layoutnovo/monstros`, `layoutnovo/equipamentos`, `layoutnovo/equipamentos/drops`, `images`, `images/24` e
`layoutnovo/personagem`.

### Backups automáticos (cron)

O script `install/backup.sh` faz o backup do banco (e, se quiser, dos arquivos do jogo) e apaga os antigos.

1. Envie `install/backup.sh` para uma pasta **fora** do `public_html`, por exemplo `/home/SEU_USUARIO/narutorpg-backup.sh`.
2. No cPanel, abra **Cron Jobs** e adicione, por exemplo, todo dia às 4h10:

```
10 4 * * * bash /home/SEU_USUARIO/narutorpg-backup.sh --modo banco --jogo /home/SEU_USUARIO/public_html --destino /home/SEU_USUARIO/backups --manter 14
```

- `--modo banco`: só o banco de dados (pequeno; recomendado todo dia).
- `--modo completo`: banco + arquivos do jogo (inclui o `config.php` e as imagens enviadas pelo painel).
- `--destino`: pasta dos backups. Precisa ficar fora do `public_html`, senão os arquivos ficariam baixáveis pela internet (o script se recusa a gravar dentro da pasta do jogo).
- `--manter 14`: apaga backups com mais de 14 dias (`0` = nunca apaga).

Para restaurar o banco, importe o `banco-AAAAMMDD-HHMM.sql.gz` pelo phpMyAdmin (ele aceita `.gz`).
Os arquivos voltam extraindo o `arquivos-AAAAMMDD-HHMM.tar.gz` pelo Gerenciador de Arquivos.

## Atualizando para uma versão nova

1. Faça um backup do banco (phpMyAdmin → **Exportar**) e dos arquivos.
2. Envie os arquivos novos de `game/` por cima dos antigos, **sem** substituir o seu `config.php`.
3. Se a versão nova mudou o banco, a forma mais simples é instalar de novo num banco vazio (Opção A, ou os passos 1 e 2 da Opção B) —
   isso apaga os jogadores. Para manter os jogadores, compare as tabelas com `verificar.php` e crie só as
   colunas que faltarem.

## Problemas comuns

- **Página branca ou erro 500**: ligue `debug` no `config.php` por um momento para ver a mensagem.
- **"Access denied" no banco**: confira servidor, usuário e senha do passo 1.
- **Erro 403 ao mandar mensagens no chat**: algumas hospedagens têm firewall (ModSecurity) que bloqueia certos
  envios; o jogo já evita os casos conhecidos, mas o suporte da hospedagem pode liberar o site.
- **Acentos estranhos**: o banco precisa ser `utf8mb4` (o arquivo de importação já cria as tabelas assim).
