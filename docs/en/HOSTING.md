# Installing on shared hosting (no SSH/root access)

This guide is for regular web hosting (cPanel, Hostinger, InfinityFree, etc.), where you only have a file
manager/FTP and phpMyAdmin. On a **VM or your own server with SSH**, use the other installer
(`sudo bash install/install.sh`, see [INSTALL.md](INSTALL.md)).

There are two ways: the **browser installer** (`install/instalar.php`, recommended) or the **manual install**
through phpMyAdmin. Both end at the same point; then follow "After installing".

## Requirements

- PHP 8.0 or newer with the `mysqli`, `mbstring`, `gd` and `json` extensions.
- MySQL 5.7+/8 or MariaDB 10.3+.
- From this repository: the `game/` folder, `sql/naruto_rpg.sql` and (for option A) `install/instalar.php`.

## Option A: browser installer (`instalar.php`)

1. In the hosting panel create an **empty** MySQL database, a user and a password, and give the user all privileges on it.
   Write down the **server** (often `localhost`), **database name**, **user** and **password**.
2. Upload to the public folder of the site (usually `public_html/` or `htdocs/`): **the contents** of `game/`,
   `install/instalar.php` and `sql/naruto_rpg.sql` (next to `instalar.php`).
3. Open `https://your-site/instalar.php`. The page checks PHP, the extensions, write permission for `config.php` and
   the `.sql` file; it creates **`instalar_chave.php`** in the folder — open it with the file manager (edit/view) and paste the code
   from the `CHAVE:` line into the **Key** field (opened in the browser it shows nothing) (only someone with access to the site files can install); then it asks for the database,
   game name and address, time zone, default language, whether sign-up confirms the e-mail (choose **No** if the host
   does not send e-mails) and the administrator account (password with 8+ characters). There is a Português/English switch.
4. Click **Install**: it imports the 41 game tables (the database must have no `naruto_` tables), writes `config.php`
   with a random secret and creates the administrator.
5. It then writes `instalar.lock` (it will not run again) and tries to delete `instalar.php`, `instalar_chave.php` and
   `naruto_rpg.sql`. If the page says one could not be deleted, **delete it with the file manager**.
6. Log in with the new account: the **Admin Panel** appears in the menu. Open `https://your-site/verificar.php` to check.

> If the import times out (very limited hosts), drop the `naruto_` tables in phpMyAdmin and use option B.

## Option B: manual install (phpMyAdmin)

### 1. Create the database

In the hosting panel, create a MySQL database, a user and a password, and give the user all privileges on
that database. Write down the **server** (often `localhost`), **database name**, **user** and **password**.

### 2. Import the game into the database

phpMyAdmin → click the database → **Import** → choose `sql/naruto_rpg.sql` → **Go**. You should get 41
tables starting with `naruto_`. The file has all game data (enemies, items, weapons, villages, jutsus,
missions...) and no players.

### 3. Upload the files

Upload **the contents** of `game/` to the public folder of the site (usually `public_html/` or `htdocs/`).
`index.php` must end up directly in that folder (or in the subfolder you want to use).

### 4. Edit `config.php`

Fill in `server`, `user`, `pass`, `name` (step 1), keep `prefix` as `naruto`, set `secretword` to a long
random phrase (32+ characters), set your `timezone` and keep `debug` as `false`.
Then open `https://your-site/verificar.php`: it checks PHP, extensions, tables and columns.

### 5. Create the administrator

1. Create a normal account on the site (**Create account**).
2. In phpMyAdmin → **SQL**, run (with your account name):
   ```sql
   UPDATE naruto_users SET authlevel=1, verify='1' WHERE username='your_account';
   ```
3. Log in again: the **Admin Panel** (`admin.php`) link shows in the menu.

## After installing (both options)

### HTTPS (optional)

The game's `.htaccess` has a block that forces `https://`, **turned off**. If your site opens on `https://`, edit
`.htaccess` and remove the `#` at the start of the 6 lines between `# FORCAR_HTTPS_INICIO` and `# FORCAR_HTTPS_FIM`
(keep the two marker lines commented). Without a certificate, leave it off or the site becomes unreachable.

### Panel settings

`admin.php` → **Settings**: game name, game address (URL), admin e-mail, and **Play without account (guest)**
(on by default). If your host does not send e-mail, keep e-mail verification off.

### Folder permissions (optional)

To upload images from the panel, make `layoutnovo/monstros`, `layoutnovo/equipamentos`,
`layoutnovo/equipamentos/drops`, `images`, `images/24` and `layoutnovo/personagem` writable (755 or 775).

### Automatic backups (cron)

`install/backup.sh` backs up the database (and, optionally, the game files) and deletes old copies.

1. Upload `install/backup.sh` to a folder **outside** `public_html`, e.g. `/home/YOUR_USER/narutorpg-backup.sh`.
2. In cPanel, open **Cron Jobs** and add, for example, every day at 4:10:

```
10 4 * * * bash /home/YOUR_USER/narutorpg-backup.sh --modo banco --jogo /home/YOUR_USER/public_html --destino /home/YOUR_USER/backups --manter 14
```

- `--modo banco`: database only (small; recommended daily). `--modo completo`: database + game files.
- `--destino`: backup folder; must be outside `public_html` (the script refuses to write inside the game folder).
- `--manter 14`: deletes backups older than 14 days (`0` = never).

Restore the database by importing `banco-YYYYMMDD-HHMM.sql.gz` in phpMyAdmin; extract `arquivos-…tar.gz` for the files.

## Updating to a new version

Back up the database and files, upload the new `game/` files over the old ones **without** replacing your
`config.php`. If the database changed, the simplest path is a fresh install on an empty database (this
erases players); to keep players, use `verificar.php` to see which columns are missing and add only those.
