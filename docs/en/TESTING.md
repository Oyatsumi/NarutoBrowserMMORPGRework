# Testing

Tests live in `tests/` and only use what already exists on the development server (PHP CLI, Python 3 and,
for screenshots, Node + Playwright/Chromium). None of this is needed to run the game.

## Setup

1. Install the game locally (you can use `install/install.sh` itself) or run PHP's built-in server:
   ```bash
   cd game && php -S 127.0.0.1:8080
   ```
2. In `game/config.php` set `"debug" => true` **only in the test environment**: PHP notices and errors then
   show up in the HTML so the scripts can detect them (and missing translations are logged as `[traducao-faltando]`).

## Tools

| Script | Purpose |
|---|---|
| `tests/mkuser.php PASTA usuario [campo=valor ...]` | Creates/adjusts a test player directly in the database and prints the login cookie. E.g. `php tests/mkuser.php game ninja1 level=30 currentaction=Fighting currentfight=1` |
| `tests/csrf.php PASTA usuario` | Prints the player's anti-CSRF token (to test panel forms). |
| `tests/fetch.py URL COOKIE pagina [...]` | Opens pages as the player and lists PHP and SQL notices/errors. Accepts `POST:pagina|campo=valor`. |
| `tests/bateria.py URL PASTA` | **Full test suite** (below). |
| `tests/corrida.py URL PASTA [N]` | Race conditions: N simultaneous requests (bank, trade, alchemy, shop, selling backpack items, buying consumables, Return Scroll use vs. sale) and checks that nothing was duplicated. |
| `tests/mochila_quadros.py images/bpslots.jpg` + `tests/mochila_medir.js URL COOKIE out.png FRAMES` | Finds the 8 34x34 frames of the backpack background with PIL and measures in the browser whether every icon (filled or empty) sits exactly on its frame. |
| `tests/shot.js URL saida.png [COOKIE]` | Screenshot of a page (Playwright). |
| `tests/telas.js URL COOKIE PASTA lista.txt` | Screenshots of several screens, with JavaScript errors and broken images (404). |

(`PASTA` = game folder, `usuario` = user, `pagina` = page, `saida` = output.)

## Full suite (`tests/bateria.py`)

```bash
python3 tests/bateria.py http://127.0.0.1:8080 game
```

1. Opens the public pages without logging in (including files that should only be included).
2. Creates 7 players in different situations — new, exploring, in battle, level 60 with everything unlocked, with
   items in the backpack/bank/equipped, at the map edge (lat 300) and administrator — and for each one **follows every
   link in the game** (once per route), looking for Warning/Fatal/Deprecated, SQL errors, blank pages or HTTP 500.
3. Opens every Administrator Panel screen, checks that a regular player can't get in and that forms without a
   CSRF token are rejected.
4. POST flows: inn, buying, movement, a full battle, victory, bank, donation, private message, forum,
   map chat and global chat, point distribution.
5. Malicious input: quotes and SQL in IDs, orderings and names, negative values, text in numeric fields,
   forged fields (`usuariologadonome`), `<script>` in messages.

The per-IP account limit and the login form have `tests/login_ip_teste.py [url] [folder]` (limit, 4th refused, logout and
idle sessions free a slot, 0/1 setting, race of 8 simultaneous logins, form autocomplete); it restores `loginip_max`.
PvP duels have their own end-to-end test with two players (pt and en): `tests/duelo_teste.py` (challenge, refusal,
wager, acceptance and actions with parallel requests, timeouts, forfeit, ranking).

Result in version 5.0 (PHP 8.3 + MariaDB 10.11): **245 requests (pt) and 249 (en), 0 problems**.

Besides the suite, every part of the game was tested in depth during the refactoring (main story from stage 1 to 22,
graduations, Senjutsu, Search Jutsu, full trades between two players, duels, alchemy, password recovery,
login lockout, card upload in the panel, a from-scratch installation with Apache) and with **parallel
requests** to make sure items/Ryou/rewards are never duplicated.

## Testing both languages

Force the language with `&idioma=en` / `&idioma=pt` in the URL (stored in a cookie) or send the cookie `idioma=en`.
With `debug` on, list untranslated texts with `grep traducao-faltando <php log> | sort -u`. See [TRANSLATING.md](TRANSLATING.md).

## Visual review

The main screens were captured with Chromium (see `docs/screenshots/`) and checked one by one:
column alignment, panels, tooltips, floating windows, popups (character sheet, world map, chat) and the
Administrator Panel. No broken images and no JavaScript errors.
