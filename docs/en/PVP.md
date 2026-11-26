# Open-world PvP, skulls, exposed skull and closed villages

Files: `pvp.php` (attack, automatic fight, losses, skulls), `lib.php` (`caveiraativa()`, `caveiraminutos()`, `caveiraexposta()`,
`sqlcaveiraexposta()`, `bloqueiovila()`), `index.php` (explore lists), `explore.php`, `towns.php`, `backpack.php` (closed villages),
`admin.php` (General settings → Open-world PvP), `help.php` ("PvP no Mundo e Caveiras" section), translations in
`lang/en/pvp_gm_seguranca.php` and `lang/en/pvp_caveira.php`. Database: `tools/pvp_caveira.sql`. Test: `tests/pvp_caveira_teste.py`.

## Defaults (fresh install)
Defaults are the `DEFAULT`s of the `naruto_control` columns (`tools/build_sql.py` and `ALTER ... SET DEFAULT` in `tools/pvp_caveira.sql`).
Applying `pvp_caveira.sql` to an existing install does **not** touch the settings row: the administrator's choices stay.

| Panel | Column | Default |
|---|---|---|
| Open-world PvP | `pvpmundo` | 1 (on) |
| Maximum level difference | `pvpdiflevel` | 30 |
| Safe level (below) | `pvpnivelseguro` | 15 |
| Wait to attack the same player | `pvpesperaalvo` | 60 min |
| Wait between attacks | `pvpesperaataque` | 2 min |
| Ryou lost by the loser | `pvpperdaryou` | 10% |
| Item-loss chance / up to | `pvpchancedrop` / `pvpmaxitens` | 25% per item / 1 item |
| Where items come from | `pvpondedrop` | 1 = backpack and equipped items |
| Rare variants | `pvpprotegervariantes` | 1 = never (protected) |
| White skull | `pvpbrancahoras` / `pvpbrancachance` | 24 h / +25% item-loss chance |
| Red skull | `pvpvermelhamortes` / `pvpvermelhadias` / `pvpvermelhabanco` | 3 kills / 14 days / item bank: no |
| Automatic ban | `pvpbanirdias` / `pvpbanirmetade` / `pvpbanirniveis` | 30 days / loses half of the items: yes / loses 5 levels instantly |
| Death with a red skull | `pvpvermelhaperdamin` / `pvpvermelhaperdamax` | loses 1 to 5 levels (random; 0 to 0 = none) |
| Exposed skull (new) | `pvpcaveiraminutos` | 30 min (0 = off) |

### Level loss (red skull)
Whoever **kills again with a red skull** loses `pvpbanirniveis` levels (default 5) **before** being banned; whoever **dies in PvP
with a red skull** loses a random number between `pvpvermelhaperdamin` and `pvpvermelhaperdamax` (default 1 to 5). The loss
(`pvp.php :: pvpperderniveis()`) undoes each level up: max HP/Chakra/TP, Strength/Dexterity and the Attack/Defense they gave,
+5 NP and +5 distribution points per level (only the unspent ones) and EP; experience goes to the start of the new level and
the level never goes below 1. Adjust it in **General settings → World PvP**. Test: `tests/pvp_niveis_teste.py`.


## Exposed skull (attackable even offline)
- Every **unjustified** kill (one that gives or renews the white/red skull; self-defence does not count) stores `users.pvpmatouem = now`.
- While the skull is active **and** `pvpmatouem > now - pvpcaveiraminutos`, the player is "exposed" (`caveiraexposta()` in PHP and
  `sqlcaveiraexposta()` in SQL share the rule). With PvP off nobody is exposed.
- Exposed and offline (last access over 10 min ago), they stay on their **last map tile**: listed in "Players on this tile" and in
  "PvP: ninjas nearby" (marked *offline, exposed skull*) and attackable there, also if they left in the middle of a monster fight
  (the defeat clears that fight, like death in `fight.php`).
- The fight is the usual automatic PvP fight (duel engine, with the player's stored stats). All other rules still apply.
- Atomicity: same as regular PvP — one transaction, both players locked (`FOR UPDATE`, ascending id), rules re-checked after the
  lock (including exposure, read from the locked row). Several simultaneous attackers = 1 fight; Ryou and items never duplicate or vanish.
- When they come back they see the "ATTACKED!" window (with "You were offline, but your skull left you exposed on the map"), a history
  entry and the fight at `pvp.php?ver=ID`; the fight log also says they were offline.

## Closed villages for exposed players
Nobody can be attacked inside villages. But while exposed (the same `pvpcaveiraminutos` since the last unjustified kill) the
player **cannot enter any village**; the message shows the minutes left (`bloqueiovila()`).

| Path | Behaviour |
|---|---|
| Walking (buttons, auto-walk, Swift Step) — `explore.php :: andarumacasa()` | First step into a village: no move (and no NP). Swift Step stops on the tile before. Auto-walk stops. |
| Travel To / `index.php?do=gotown` — `towns.php :: travelto()` | Refused without charging TP/Ryou. The write also has `AND NOT <exposed>` in its `WHERE`. |
| Return and Random Teleport scrolls — `backpack.php` | Refused; the scroll is **not** used up. Incense still works. |
| Any future teleport to a village (e.g. doujutsu) | Must call `bloqueiovila($userrow)` before moving; `travelto()` already checks. |
| **Death** (PvE `fight.php`, PvP `pvp.php`) | **Not blocked**: the loser always respawns in the home village. Blocking would strand a 0-HP player on the map, and the defeat is already the punishment. |
| GM/admin actions (unstick, move) | Not blocked (staff fixes). |

After the window villages open normally; the white/red skull itself lasts until its own deadline.

## Test
`python3 tests/pvp_caveira_teste.py http://127.0.0.1:8080 game` — see the Portuguese doc for the full list of checks.
