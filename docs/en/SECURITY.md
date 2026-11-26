# Security

A summary of what was reviewed and fixed in version 4.0 and how the game protects itself.

## Accounts and login
- Passwords with `password_hash()` (bcrypt). Old MD5 hashes are accepted once and converted on the first login.
- Cookie `narutorpg` = `id conta assinatura lembrar` (id, account, signature, remember) with an **HMAC-SHA256**
  signature (secret from `config.php` + password hash): changing the password invalidates all old cookies.
  `HttpOnly`, `SameSite=Lax` and `Secure` (on HTTPS) flags.
- Brute-force lockout: 5 failures in 15 minutes lock the IP (login and password change).
- Password recovery via a **single-use token link** valid for 1 hour (previously anyone could change the password
  of any account by knowing its e-mail). Responses are identical whether or not the account exists.
- "Activate account" only resends the code (previously it activated any account without verification).
- Simultaneous login of the same character from different IPs is still blocked.
- **Accounts per IP** (`control.loginip_max`, default 3, in General settings; 0 = unlimited): a form login is refused with a
  clear message when N accounts are already *logged in* from the same IP. "Logged in" = `users.loginip` equal to the IP and
  `onlinetime` within the last 10 minutes (the game's usual "online" window). The IP is stored at login (`loginip`, plus the
  old `ipadress`); logout clears `loginip` (frees the slot) and a session idle for 10 minutes frees it on its own. Logging
  into the same account again does not use another slot. The check runs under a per-IP `GET_LOCK` (simultaneous logins
  cannot exceed the limit). The IP comes from `REMOTE_ADDR` only, like `painelip()`: behind a reverse proxy configure the web
  server (e.g. `mod_remoteip`/`real_ip`). Applies to everyone, **staff included** (no admin/GM exemption). Only logins are
  limited: existing "Remember me" cookies keep working. GMs see no IPs. Migration: `tools/login_ip.sql`. Test:
  `tests/login_ip_teste.py`.
- Login form for password managers: account name is `type="text"` with `autocomplete="username"`, password has
  `autocomplete="current-password"`, both in one `<form method="post">` (login.php is outside AJAX navigation). The account
  name is no longer a hidden password-type field (Chrome would not save the pair); only the password has Show/Hide.
  Registration and password change use `autocomplete="new-password"`.

## Input handling
- The global sanitization (`addslashes` + UTF-8 `htmlspecialchars`) was kept and completed with `intval()` on
  numbers, whitelists for anything that becomes a column name (slots, item attributes, list ordering) and
  range validation (negative amounts, amounts above the balance or the column limit).
- All `extract($_POST)` calls were removed (they allowed, for example, changing another player's gold).
- SQL injection fixed in: `ORDER BY` of the help and ranking pages, world map, drop slot, page stored in
  `users.pagina` (came from the URL), forum, messages, chat and several unquoted numeric IDs.
- The `?idioma=` parameter and the `idioma` cookie are only accepted if they are in `$idiomasdisponiveis`.

## Game rules (exploits)
- Bank, trades, donations, alchemy, missions, graduation and battle victory use conditional `UPDATE`s or
  transactions with `SELECT ... FOR UPDATE`: double clicks or parallel requests do not duplicate items, Ryou or
  rewards (tested with dozens of simultaneous requests).
- Trades require both sides to confirm the **same** offer (offer hash in the link) and re-check items and Ryou
  at execution time.
- Duels: challenge and acceptance check online status, same place, not in a fight/trade/another duel and the wager
  Ryou (again on acceptance, with both players locked). The wager leaves both accounts on acceptance with
  `UPDATE ... AND gold >= wager` (both rows or none) and the prize is paid exactly once, in the same transaction that
  sets the state to `fim`. All actions are POSTs with a CSRF token and the turn number; nobody can act out of turn or
  duel themselves. The backpack (`backpack.php`) is locked during a duel (potions only on your turn, from the duel screen).

## Race conditions (duplicated items and Ryou)
Two requests from the same player at the same time (double click, two tabs, a script firing dozens of parallel
POSTs) used to read the player, compute the result in PHP and rewrite the whole backpack/equipment: the last write
wiped the other one and the same item ended up in two places (e.g. unequipping a weapon while depositing it in the
bank left it in the backpack **and** in the bank). Protections now in place:
- **Transaction + `SELECT ... FOR UPDATE`** in every action that rewrites several fields from the data it read: bank
  (`funcaoitens.php`), trade (`troca.php`), backpack (`backpack.php`), unequip (`desequipar.php`) and alchemy. The
  second request waits for the first one's `COMMIT` and reads fresh data. Players are always locked in id order (no
  deadlocks). A `die()` before `COMMIT` closes the connection and MariaDB rolls everything back.
- **Conditional `UPDATE`** checking affected rows where one query is enough: `gold=gold-X WHERE gold>=X` (donation),
  `AND bpN='item'` (using a backpack item), `AND dropcode=...` (drops), `AND currentfight<>'0'` (victory). The Ryou
  donation (`users.php`) runs in a transaction and only credits if the receiver stays under the column limit.
- Bank links that act on an item's **position** (give, delete) also carry a code of the item, so an old link cannot
  hit the item that has since moved into that position.
- Test: `tests/corrida.py URL FOLDER [N]` fires N simultaneous requests per scenario (unequip vs bank, backpack vs
  bank, trade completed from both sides, donations, item sending, alchemy, inputs such as `1e9`, `-0`, `0x10`,
  arrays) and checks in the database that no item or Ryou was created. The test server must serve requests in
  parallel (`PHP_CLI_SERVER_WORKERS=8 php -S ...`).

## Administrator Panel
- Access only for `authlevel = 1` (the hard-coded lock by character name was removed).
- Every form and action link carries an anti-CSRF token.
- Image uploads: type checked by content (`getimagesize`), maximum size, re-encoded by GD with a fixed name
  (`{id}.jpg`/`.gif`/`.png`) — the uploaded file name is never used. Image folders do not execute PHP.

## Server
- `config.php`, `templates/`, `.sql/.md/.psd/.sh` files and hidden files are blocked by `.htaccess`.
- Include-only files do nothing when opened directly.
- PHP and database errors go to the log; the player only sees a generic message (`debug => false`).
- `X-Frame-Options`, `X-Content-Type-Options` and `Referrer-Policy` headers.
- The installer creates a database user with privileges on the game's database only, generates the random secret
  and leaves the game files read-only for Apache (except the image folders).

## Recommendations
- Use HTTPS (the installer sets it up with Let's Encrypt).
- Back up the database (`mysqldump narutorpg > backup.sql`) regularly.
- Keep the system up to date (`apt upgrade`).

## Staff panels (admin.php, admin_missoes.php, gm.php) — `painelseguranca.php`
- **Password again to open the panel**: besides the login, the panel asks for the password. A separate session cookie
  (`narutorpg_painel`, `HttpOnly`, `SameSite=Strict`) with an HMAC (secret + password hash + role) then lasts until the browser is
  closed or **30 minutes without using the panel**; changing the password or role drops the open panel. "Close the panel" deletes it.
- Wrong passwords there (and in confirmations) count in the `loginfail` table like the login: 5 errors in 15 minutes block the IP.
- **Password confirmation** before dangerous actions: deleting towns/enemies/items/drops/jutsus/terrains/accounts/missions/NPCs,
  banning, changing role/access level (promote/demote admin or GM), setting someone's password, mass maintenance, clearing chats/locks,
  message to everyone and changing the **open-world PvP** or **e-mail** settings. The confirmation screen re-posts the same form.
- **Audit log** (`adminlog`, `admin.php?do=auditoria` with filters): panel logins, wrong passwords, blocked IPs, every submitted
  form/action (summarized, **passwords never stored**), confirmations, all GM actions, automatic PvP bans and password-recovery attempts.
- Optional **IP allowlist**: `config.php` → `"adminips" => "203.0.113.5, 10.0.0.0/8"` (IPv4/IPv6, CIDR). Empty = any IP. The installer asks.
- Review: all panel POSTs require the CSRF token; GET action links carry `csrf=`; user-supplied column names are whitelisted;
  regular players and GMs are redirected from **every** admin.php/admin_missoes.php route (tested route by route, GET and POST).
  Security headers verified; panels also send `Cache-Control: no-store`.

## Roles: administrator, GM and banned
- `users.authlevel`: 0 player, 1 administrator, **2 banned** (historic value, kept). `users.acesso`: 0 regular (1 = VIP was removed in 5.0), 2 Tutor, **3 GameMaster**.
  GM = `acesso 3` with `authlevel 0` (`ehgm()` in lib.php) — that is why GMs do **not** use authlevel 2.
- GMs never see passwords, hashes, security answers or recovery codes (hashes are not shown anywhere, not even to admins).

## Passwords and recovery
- `senha_hash()` uses **Argon2id** when available (`password_algos()`), else bcrypt cost 12. Old MD5 and bcrypt hashes are rehashed
  on the next login. Security answers and recovery codes use the same hash.
- Without e-mail (`control.emailativo = 0`, default) the player picks at sign-up either **security questions** (2) or a 32-character
  **recovery code** (random_bytes) shown once. Questions: account + character + both answers + creation year + registered e-mail +
  current level, generic error. Code: account + code; the code is consumed (conditional UPDATE) and a new one is shown.
  Rate limited via `loginfail` and audited. Old accounts are prompted to choose a method. A GM can **unlock recovery for 1 hour**
  (no answers needed, everything else still required) without ever seeing a credential.
- A password set by an admin on the player sheet is temporary (`trocarsenha = 1`): the player must change it on the next login.

## Open-world PvP
- Attacks only by POST with CSRF; one transaction with both players locked (ascending id) and every rule re-checked after locking.
  Items and Ryou move with conditional UPDATEs; tested with 10 simultaneous attacks (1 fight).
- troca.php refuses trading during a duel; admin "Send to village" and GM "Unstick" end an active duel as a forfeit.
