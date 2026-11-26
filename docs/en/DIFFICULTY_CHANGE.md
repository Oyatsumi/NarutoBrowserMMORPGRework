# Registration without a village, difficulty change and a longer history

Three changes from Nov/2026, all in the migration `tools/dificuldade_historico.sql` (included by `tools/build_sql.py`; safe to
re-run on an existing database). Test: `tests/cadastro_dificuldade_teste.py`. Portuguese version: [TROCA_DE_DIFICULDADE.md](../TROCA_DE_DIFICULDADE.md).

## 1. Registration no longer asks for a home village

- The form (`users.php?do=register`) only shows a notice; every new ninja gets the alignment of the **starting village**
  (`control.vilainicial`, default 1 = Leaf Village, map center with the beginner area) via `cidadesconteudo.php :: alinhamentoinicial()`.
  A hand-posted `alinhamento` field is ignored; an invalid configured village falls back to village 1.
- Players change village later only through the ambassadors' transfer missions (`TROCA_DE_VILA.md`).
- Admin: **General settings → Starting village** (only non-secret home villages with an alignment).

## 2. Recovery: code vs. secret questions

Choosing **Recovery code** hides and disables the secret-question fields (inline JS). Without JS everything is shown and the
server ignores the questions when `metodo=codigo`. With `metodo=perguntas` (or no method) both questions remain required.

## 3. Changing difficulty mid-game

The **Master of Trials** (NPC 40 at -9,3, near the Leaf) offers trials 190-192 (`config "troca_dificuldade": 1..3`):
level 15; win 3/4/5 fights in a row (no rest/heal/death) against enemies of level 10+/14+/18+; offering of 3,000/5,000/8,000 Ryou
paid on delivery; one trial at a time; not during a fight; wait `control.trocadif_dias` (default 14) days between changes
(`users.dificuldadetroca_em`). The trial for the current difficulty is hidden.

`fight.php` reads `users.difficulty` at fight time only (enemy HP/damage, experience and Ryou via `control.diffNmod`); nothing is
stored pre-multiplied, so changing the column is enough and applies from the next fight. Delivery runs in a transaction
(`FOR UPDATE`, old difficulty/date/not-fighting checked in the `WHERE`), writes a history line and an audit-log entry
(`troca_dificuldade`). The current difficulty (with "since" date) is shown on the All Stats sheet and in the Master's box.
Admin: **General settings → Difficulty change** (waiting days) and the **Difficulty change** field in the mission editor.

## 4. Longer history

The left-column **History** box now keeps/shows `control.historicomax` messages (default 12, range 5-30; it used to be 5).
Older histories keep working.
