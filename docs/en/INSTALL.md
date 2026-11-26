# Installation

The game has two installers:

- **`install/install.sh`** (this guide): for a **VM or your own server** with **SSH and root** (Debian/Ubuntu).
  It installs Apache, PHP and MariaDB and configures everything.
- **`install/instalar.php`**: for **shared hosting** without SSH (cPanel, Hostinger, InfinityFree...), done in the
  browser. See [HOSTING.md](HOSTING.md).

## 1. Automatic (recommended) — Debian 12 / Ubuntu 22.04 / Ubuntu 24.04

```bash
git clone https://github.com/SEU_USUARIO/NarutoBrowserMmorpg.git
cd NarutoBrowserMmorpg
sudo bash install/install.sh
```

The script asks for:

| Question | Default |
|---|---|
| Server domain or IP | Machine's IP |
| Site path (`/` or e.g. `/narutorpg`) | `/` |
| Files folder | `/var/www/narutorpg` |
| Time zone | `America/Sao_Paulo` |
| Database name, user and password | `narutorpg` / `narutorpg` / random password |
| Table prefix | `naruto` |
| Game name | `Naruto! Nigeru RPG` |
| Administrator account, character, e-mail and password | `admin` / `Hokage` / `admin@dominio` / (required) |
| Install Postfix to send e-mail? | no |
| Enable HTTPS with Let's Encrypt? | no |
| Donation link (credits page and panel window button; `-` = no button) | developers' link (Stripe) |

It then:

1. installs `apache2`, `mariadb-server`, `php` (+ `mysqli`, `gd`, `mbstring`) and, if requested, `postfix` and `certbot`;
2. creates the database in `utf8mb4` and a user with privileges on that database only;
3. imports `sql/naruto_rpg.sql`, replacing the table prefix;
4. copies `game/` to the chosen folder and generates `config.php` with a **random secret** (cookie signature and anti-CSRF tokens);
5. sets permissions: Apache can only **read** the game, and can only **write** to the image folders that receive panel uploads;
6. creates the administrator account (password hashed with `password_hash`);
7. configures Apache (VirtualHost at the root or an Alias on a path), turns off PHP error display and tests the login page;
8. saves a summary to `/root/narutorpg-instalacao.txt` (readable by root only).

If a game already exists in the same database/prefix, the script stops and only drops the tables if you type `APAGAR`.

### Unattended mode (no questions)

```bash
sudo NARUTO_NAO_PERGUNTAR=1 \
     NARUTO_DOMINIO=jogo.exemplo.com.br NARUTO_CAMINHO=/ \
     NARUTO_ADMIN_CONTA=admin NARUTO_ADMIN_PERSONAGEM=Hokage NARUTO_ADMIN_EMAIL=voce@exemplo.com.br \
     NARUTO_ADMIN_SENHA='uma-senha-forte' NARUTO_HTTPS=s \
     bash install/install.sh
```

All variables: `NARUTO_DOMINIO`, `NARUTO_CAMINHO`, `NARUTO_PASTA`, `NARUTO_FUSO`, `NARUTO_DB_NOME`,
`NARUTO_DB_USUARIO`, `NARUTO_DB_SENHA`, `NARUTO_PREFIXO`, `NARUTO_NOME_JOGO`, `NARUTO_ADMIN_CONTA`,
`NARUTO_ADMIN_PERSONAGEM`, `NARUTO_ADMIN_EMAIL`, `NARUTO_ADMIN_SENHA`, `NARUTO_EMAIL` (s/n = yes/no),
`NARUTO_HTTPS` (s/n), `NARUTO_HTTPS_EMAIL`, `NARUTO_LINK_DOACAO` (`-` = no button), `NARUTO_SOBRESCREVER` (`APAGAR` to reinstall over an existing game).

## 2. Manual (any hosting with PHP 8 + MySQL/MariaDB)

1. Create a `utf8mb4` database and import `sql/naruto_rpg.sql` (via phpMyAdmin or `mysql banco < sql/naruto_rpg.sql`).
   For another prefix, replace `` `naruto_ `` with `` `seuprefixo_ `` in the file before importing.
2. Upload the contents of `game/` to the site's public folder.
3. Edit `config.php`: database credentials, `prefix` and a long random `secretword`
   (e.g. `openssl rand -hex 32`). Leave `debug` set to `false`.
4. Create your account through the game itself (Create Account) and make it an administrator in the database:
   ```sql
   UPDATE naruto_users SET authlevel=1, verify='1' WHERE username='sua_conta';
   ```
5. In the Administrator Panel (`admin.php`) set the **Game address** and the **Administrator e-mail**
   (and, if you want, the **default language**).
6. Give the web server write permission on `layoutnovo/monstros`, `layoutnovo/equipamentos`,
   `layoutnovo/equipamentos/drops`, `images`, `images/24` and `layoutnovo/personagem` if you want to upload images through the panel.

### nginx

`.htaccess` does not apply to nginx; use something like this:

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

(The comment in the block means: "folders that receive uploads never execute PHP".)

## 3. E-mail

Sign-up verification and password recovery use PHP's `mail()` function. On your own server install
Postfix (the installer offers it) and configure SPF/DKIM in the domain's DNS. Without e-mail, leave
"Verify e-mail on sign-up" turned off in the panel.


The installer explains the pros and cons and asks whether **game e-mails** are on (`control.emailativo`, default **no**). On is more
secure (only the e-mail owner can recover the account) but needs a working mail server. Off: accounts play right away and players
pick security questions or a recovery code at sign-up; anyone without either asks a GM. It can be changed later (asks for the password).

### Panel IP allowlist (optional)
The installer asks which IPs may open `admin.php`, `admin_missoes.php` and `gm.php` and writes `config.php`:
`"adminips" => "203.0.113.5, 10.0.0.0/8"` (IPv4/IPv6, CIDR). Empty = any IP (the password is asked again anyway).
Behind a reverse proxy/CDN, PHP must see the real IP in `REMOTE_ADDR`.

### Upgrading an existing 4.0 install to 5.0
Run the `ALTER TABLE`s that add the new `control`/`users` columns and the `adminlog`, `pvp` and `variantes` tables (see
`sql/naruto_rpg.sql`). Old accounts keep working and are prompted to pick security questions or a recovery code.
For the 5.0 avatars without VIP, donations and credits just run `mysql narutorpg < tools/avatares.sql` (safe to run again;
existing players keep their current avatar).

## 4. Upgrading from an old version (database with the `dk_` prefix)

Version 4.0 uses the `naruto_` prefix, `password_hash` passwords and a few new columns. To reuse an old
database, rename the tables (`RENAME TABLE dk_users TO naruto_users, ...`) and apply the schema differences
from `sql/naruto_rpg.sql` (new columns in `naruto_control` and `naruto_users`, the `naruto_loginfail` table,
255-character `password` columns and `utf8mb4`). Old MD5 passwords keep working: they are converted to the
new hash on each player's first login.
