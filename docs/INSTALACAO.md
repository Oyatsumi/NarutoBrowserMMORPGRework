# Instalação

O jogo tem dois instaladores:

- **`install/install.sh`** (este guia): para **VM ou servidor próprio** com acesso **SSH e root** (Debian/Ubuntu).
  Instala Apache, PHP e MariaDB e deixa tudo configurado.
- **`install/instalar.php`**: para **hospedagens comuns** sem SSH (cPanel, Hostinger, InfinityFree...), feito pelo
  navegador. Veja [HOSPEDAGEM.md](HOSPEDAGEM.md).

## 1. Automática (recomendada) — Debian 12 / Ubuntu 22.04 / Ubuntu 24.04

```bash
git clone https://github.com/SEU_USUARIO/NarutoBrowserMmorpg.git
cd NarutoBrowserMmorpg
sudo bash install/install.sh
```

O script pergunta:

| Pergunta | Padrão |
|---|---|
| Domínio ou IP do servidor | IP da máquina |
| Caminho no site (`/` ou ex. `/narutorpg`) | `/` |
| Pasta dos arquivos | `/var/www/narutorpg` |
| Fuso horário | `America/Sao_Paulo` |
| Nome do banco, usuário e senha do banco | `narutorpg` / `narutorpg` / senha aleatória |
| Prefixo das tabelas | `naruto` |
| Nome do jogo | `Naruto! Nigeru RPG` |
| Conta, personagem, e-mail e senha do administrador | `admin` / `Hokage` / `admin@dominio` / (obrigatória) |
| Instalar Postfix para enviar e-mails? | não |
| Ativar HTTPS com Let's Encrypt? | não |
| Link de doação (botão da página de créditos e da janela do painel; `-` = sem botão) | link dos desenvolvedores (Stripe) |

E então:

1. instala `apache2`, `mariadb-server`, `php` (+ `mysqli`, `gd`, `mbstring`) e, se pedido, `postfix` e `certbot`;
2. cria o banco em `utf8mb4` e um usuário com permissão só nesse banco;
3. importa `sql/naruto_rpg.sql` trocando o prefixo das tabelas;
4. copia `game/` para a pasta escolhida e gera o `config.php` com um **segredo aleatório** (assinatura do cookie e tokens anti-CSRF);
5. ajusta permissões: o Apache só **lê** o jogo, e só pode **escrever** nas pastas de imagens que recebem uploads do painel;
6. cria a conta do administrador (senha com `password_hash`);
7. configura o Apache (VirtualHost na raiz ou Alias num caminho), desliga a exibição de erros do PHP e testa a página de login;
8. salva um resumo em `/root/narutorpg-instalacao.txt` (legível só pelo root).

Se já existir um jogo no mesmo banco/prefixo, o script para e só apaga as tabelas se você digitar `APAGAR`.

### Modo automático (sem perguntas)

```bash
sudo NARUTO_NAO_PERGUNTAR=1 \
     NARUTO_DOMINIO=jogo.exemplo.com.br NARUTO_CAMINHO=/ \
     NARUTO_ADMIN_CONTA=admin NARUTO_ADMIN_PERSONAGEM=Hokage NARUTO_ADMIN_EMAIL=voce@exemplo.com.br \
     NARUTO_ADMIN_SENHA='uma-senha-forte' NARUTO_HTTPS=s \
     bash install/install.sh
```

Todas as variáveis: `NARUTO_DOMINIO`, `NARUTO_CAMINHO`, `NARUTO_PASTA`, `NARUTO_FUSO`, `NARUTO_DB_NOME`,
`NARUTO_DB_USUARIO`, `NARUTO_DB_SENHA`, `NARUTO_PREFIXO`, `NARUTO_NOME_JOGO`, `NARUTO_ADMIN_CONTA`,
`NARUTO_ADMIN_PERSONAGEM`, `NARUTO_ADMIN_EMAIL`, `NARUTO_ADMIN_SENHA`, `NARUTO_EMAIL` (s/n),
`NARUTO_HTTPS` (s/n), `NARUTO_HTTPS_EMAIL`, `NARUTO_LINK_DOACAO` (`-` = sem botão), `NARUTO_SOBRESCREVER` (`APAGAR` para reinstalar por cima).

## 2. Manual (qualquer hospedagem com PHP 8 + MySQL/MariaDB)

Passo a passo pelo painel da hospedagem: [HOSPEDAGEM.md](HOSPEDAGEM.md).

1. Crie um banco `utf8mb4` e importe `sql/naruto_rpg.sql` (pelo phpMyAdmin ou `mysql banco < sql/naruto_rpg.sql`).
   Para outro prefixo, troque `` `naruto_ `` por `` `seuprefixo_ `` no arquivo antes de importar.
2. Envie o conteúdo de `game/` para a pasta pública do site.
3. Edite `config.php`: dados do banco, `prefix` e um `secretword` longo e aleatório
   (ex.: `openssl rand -hex 32`). Deixe `debug` em `false`.
4. Crie a sua conta pelo próprio jogo (Criar Conta) e torne-a administradora no banco:
   ```sql
   UPDATE naruto_users SET authlevel=1, verify='1' WHERE username='sua_conta';
   ```
5. No Painel do Administrador (`admin.php`) ajuste o **Endereço do jogo** e o **E-mail do administrador**.
6. Dê permissão de escrita ao servidor web em `layoutnovo/monstros`, `layoutnovo/equipamentos`,
   `layoutnovo/equipamentos/drops`, `images`, `images/24` e `layoutnovo/personagem` se quiser enviar imagens pelo painel.

### nginx

O `.htaccess` não vale no nginx; use algo assim:

```nginx
server {
    listen 80;
    server_name jogo.exemplo.com.br;
    root /var/www/narutorpg;
    index index.php;

    location ~ /\.            { deny all; }
    location = /config.php    { deny all; }
    location ^~ /templates/   { deny all; }
    location ~* \.(sql|md|psd|sh|log|ini)$ { deny all; }
    # Pastas que recebem uploads nunca executam PHP.
    location ~* ^/(images|layoutnovo)/.*\.php$ { deny all; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
}
```

## 3. E-mails

Cadastro com verificação e recuperação de senha usam a função `mail()` do PHP. Em servidor próprio instale o
Postfix (o instalador oferece) e configure SPF/DKIM no DNS do domínio.

O instalador explica os prós e contras e pergunta se os **e-mails do jogo** ficam ligados (`control.emailativo`, padrão: **não**).
Ligados é mais seguro (só o dono do e-mail recupera a conta), mas exige um servidor de e-mail funcionando. Desligados, a conta já
joga ao ser criada e o jogador escolhe no cadastro perguntas secretas ou um código de recuperação; quem não tiver nenhum pede ajuda
a um GM. Dá para mudar depois em Configurações gerais (pede a senha).

### Lista de IPs do painel (opcional)
O instalador pergunta quais IPs podem abrir `admin.php`, `admin_missoes.php` e `gm.php` e grava em `config.php`:
`"adminips" => "203.0.113.5, 10.0.0.0/8"` (IPv4/IPv6 e faixas CIDR). Vazio = qualquer IP (a senha é pedida de novo de qualquer forma).
Atenção ao usar proxy reverso/CDN: o PHP precisa ver o IP real em `REMOTE_ADDR`.

### Atualizando uma instalação 4.0 já existente para a 5.0
Rode os `ALTER TABLE` que criam as colunas novas de `control`/`users` e as tabelas `adminlog`, `pvp` e `variantes` (veja
`sql/naruto_rpg.sql`). Contas antigas continuam funcionando; ao entrar, recebem um aviso para escolher perguntas secretas ou código.
O banco completo e atualizado fica só em `sql/naruto_rpg.sql`; `verificar.php` mostra as tabelas e colunas que faltam.

## 4. Atualizando de uma versão antiga (banco com prefixo `dk_`)

A versão 4.0 usa o prefixo `naruto_`, senhas com `password_hash` e algumas colunas novas. Para aproveitar um banco
antigo, renomeie as tabelas (`RENAME TABLE dk_users TO naruto_users, ...`) e aplique as diferenças de estrutura
de `sql/naruto_rpg.sql` (colunas novas em `naruto_control` e `naruto_users`, tabela `naruto_loginfail`,
colunas `password` com 255 caracteres e `utf8mb4`). As senhas MD5 antigas continuam funcionando: são convertidas
para o hash novo no primeiro login de cada jogador.
