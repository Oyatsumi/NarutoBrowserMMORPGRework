# Administrator Panel

Open `admin.php` with an account that has `authlevel = 1` (the account created by the installer is already an administrator).
The "Administrator Panel" link also appears in the game's left column for administrators.
The panel's top bar has the "Português | English" language selector.

| Section | What you can do |
|---|---|
| **Home** | Game numbers (sign-ups, online, banned, in battle), top 10, latest sign-ups and warnings (game closed, debug mode on, `install/` folder left on the server, enemies without a card, map larger than the enemy levels). |
| **General settings** | Open/close the game and the closed message; sign-ups open; e-mail verification; login page notice; game name, address and e-mail; map size; starting Ryou; battle chance when walking; multiplier (%) for the price shops pay for backpack items (`vendamochila`, 100 = base price, 0 = shops do not buy); experience and Ryou multipliers (events); minimum level for duels/donations; names of specializations and difficulties (and their multipliers); news/online/chat in villages; forum type; gzip compression; default game language (Portuguese or English). |
| **News** | Publish, edit and delete. The most recent one shows in the villages. |
| **Message to everyone** | Opens the game's floating window for everyone (or only those online) with a title, image and text. |
| **Players** | Search by account/character/e-mail, filters (online, banned, staff, unverified, in battle), pagination. On the player sheet: all fields, new password, role (tutor/GM), "send to village / unlock", "restore HP/Chakra/TP", ban/unban and delete account (confirmed by typing the name). |
| **Moderation** | Delete global chat and map chat messages (or clear everything), delete forum topics, view and clear login lockouts. |
| **Maintenance** | End abandoned battles, reset duels, cancel stuck trades, restore everyone's status, delete accounts unverified for 7 days; PHP/database versions and table sizes. |
| **Enemies and cards** | List with each enemy's card; create, edit, delete and **upload the card**. |
| **Shop items / Drop items** | Create, edit, delete and upload the icon (34x34). The special field is validated. |
| **Jutsus** | Create, edit and delete: type (heal, damage, sleep, +attack, +defense, support), chakra, value, element, hits, life steal, extra effect (value/turns/chance), cooldown, requirements (level, class, rank), English name and description; **Preview effect** button; filters by element/type/class/learning method; "How it is learned" and **Create a scroll that teaches this jutsu**. In **Drops**, the "Teaches jutsu" field turns the drop into a scroll. |
| **Villages** | Create, edit and delete; position, prices, TP, items for sale and the village's four images. |
| **Avatars** (`admin_avatares.php`) | Mode of each avatar (free, drop/Disguise Scroll, mission reward or special), name, active and order; where each one is obtained (calculated from drop tables and missions) and how many players own it; **give an avatar to a player** (logged in the audit log). In **Drops**, the "Unlocks avatar" field turns a drop into a Disguise Scroll; in **Missions**, "Reward avatar". |
| **Levels** | View the whole table and edit each level for the three specializations. |

Every panel form requires an anti-CSRF token and all values are validated on the server.

## Dynamic page updates (AJAX)
In **General settings → Dynamic page updates (AJAX)** (field `control.atualizacaodinamica`):
- **Off** (default): every click reloads the whole page, exactly as it always did (the HTML is unchanged).
- **On**: the layout includes `js/navegacao.js` (plain JavaScript, no libraries). Game links and forms are fetched
  in the background and only the left column, the content, the right column, the message window and the title are
  swapped. The address bar updates normally and back/forward work. Scripts inside the content (fights, missions)
  run again, and automatic refreshes (auto-walk, resting, duels, trades) keep working.
- Always a full page reload: administrator panel, login/logout, language switch, file uploads (avatar), external
  links or links with `target`, popups (chat, map, profile still use `window.open`), and any unexpected response or
  network error. There is no polling: the columns only change when the player navigates.
- Pros: faster navigation, no white flash. Cons: needs JavaScript (falls back to classic mode by itself without it),
  harder to debug, and new pages with a lot of their own JavaScript should be tested.
- At install time: the `install.sh` question or `NARUTO_AJAX=y|n` (default `n`).

## Creating an enemy card

The card is 295x145 pixels. There are three ways:

1. **Through the panel**: *Enemies and cards* → edit → *Upload new card*. Any JPG/PNG/GIF is resized to
   295x145 (requires PHP's GD extension, installed by the installer).
2. **With Photoshop**: open `psds/cartas/monstros.psd`. It has the frame and **all the portrait layers**
   (hidden). Place the new portrait as a layer in *Multiply* mode below the frame layers, inside the left
   box (67x99 starting at x=6, y=37), and export as `ID.jpg`. The name, level and numbers are drawn by the game
   on top of the card.
3. **With a script**: `tools/montar_cartas.py` does the same as item 2 automatically
   (`python3 tools/montar_cartas.py psds/cartas/monstros.psd saida 200:retrato.png`).

The 15 cards for enemies 152 to 166 were made from portraits that were already drawn (and hidden) in
`monstros.psd`. Cards 79 to 151, which didn't exist, were generated as silhouettes in the same style
(`tools/gerar_retratos.py`) so the game never shows empty cards — replace them with final artwork whenever you like.

Enemy names are stored in Portuguese; to show them in English add the name to `game/lang/en/conteudo_monstros_drops_jutsus.php`
(the same applies to items, jutsus and villages — see [TRANSLATING.md](TRANSLATING.md)).

## Creating a new village

Everything that defines a village lives in `naruto_towns` — nothing is hard-coded, so a new village works without editing PHP.

1. *Villages* → *Add village*: name (and English name), Kage/leader (and English), optional description (pt/en), position, prices, TP and items for sale.
2. **Services**: tick what the village offers — inn, item shop, map shop, bank, missions, ranking up, alchemy, Senjutsu training
   (and calling Fukasaku & Shima), Search Jutsu training. *Secret map* = its map is not sold (must be found on foot).
3. **Alignment / home village**: a short code (`folha`, `pedra`...; lowercase letters, digits, `_`). With *Home village* ticked,
   it can be the starting village (General settings → Starting village; registration no longer asks) and players with that alignment respawn there when they die. Alignment with no village = village 1.
   Renaming the code moves the players of the old alignment along.
4. **Images** (all optional): 24x24 icon (or *Pick a ready-made icon* from `images/24/vilas_novas/`), grey icon (generated if
   not uploaded), 300x25 title (without it the name is shown as styled text), 487x284 background, 115x113 Kage and the
   20x17 alignment emblem (`images/vila_CODE.gif`, after saving the alignment).
5. Deleting: village 1, a village with players inside or where missions are turned in cannot be deleted. Players of the
   deleted alignment move to village 1's alignment. The village images are removed.
6. Enemies gain 1 level every 5 tiles from the center: a village at (200, 0) sits in a level ~40 area.

## Minimap terrains

*Villages* → *Minimap terrains* (`admin.php?do=terrenos`): each terrain is a coordinate rectangle with a ground image
`images/terreno_NAME.jpg` (155x155) and a priority. Each minimap tile uses the highest-priority terrain containing it (edges
are slightly ragged, fixed by coordinate). Untick *Trees* for desert, snow, coast etc. Initial terrains: grass (whole map,
priority 0), forest, trail, desert, rocky, snow, coast, swamp and volcanic (outer ring).

Each enemy can be limited to terrains: *Enemies* → edit → *Appears only on these terrains* (none checked = any terrain).
Only tick terrains that exist within the level's distance band (e.g. snow is 210+ tiles from the centre, level ~42+);
if no enemy in the band may appear on the tile's terrain, the restriction is ignored there. The enemy list filters by terrain.

## Map size and the outer ring
The map goes from -`gamesize` to +`gamesize` (*Settings* → *Map size*, now **400**). Enemies gain 1 level every 5 tiles, so
400 tiles call for enemies up to level 80 (enemies 207-221, levels 62 to 80, live in the 300-400 ring). If you change the size again:
- the world map (`images/mapa_grande.png`) is stretched over the new coordinates; to draw another ring, adjust and run
  `python3 tools/gerar_mapa_grande.py ORIGINAL_300.jpg game/images/mapa_grande.png [sheet.png]` (the original 300-tile map is
  `images/mapateste.jpg`);
- create terrain rectangles for the new areas (*Minimap terrains*; outside every rectangle a tile is grass) and check the
  enemies of the new level band (the panel warns when a level is missing).
Ring terrains: snow (north), coast (north-east and east, with swamp islands), volcanic (south), rocky (south-west and the
western canyons) and desert (west). A new terrain needs `images/terreno_NAME.jpg` (155x155) and the tiles from
`tools/gerar_tiles_terreno.py GAME_DIR NAME` (without them the tile uses the whole image).

## Missions (story and optional) and map NPCs
Menu **Game content → Missions** (`admin_missoes.php`; for players, the **Missions** page at `missoesextras.php`). All missions live in the `naruto_missoesextras` table:
- **Story** (`categoria = historia`): the 21 required missions, ordered by the `etapa` (step) column. Each player's
  progress is still stored in `users.missao` (`step,done,total,interval`), so changing the step, type or quantity
  affects players currently on it (the editor warns about it). They can't be deleted or disabled from the panel.
- **Optional** (`categoria = extra`, labelled "Optional" in the panel): accepted and turned in at a village (`vila_id`, 0 = any village) or with a
  map NPC (`npc_id`). They can be repeatable (interval in hours). Progress is in `naruto_missoesextras_jogador`.
- Types: hand in item (consumed on turn-in, up to 4), defeat enemy (counted on every win), visit location (marked
  while walking), pay Ryou, talk to NPC; story only: repeat task and "special" (a PHP rule in `mx_ganchos()` in
  `funcoesmissoesextras.php`, currently only Mission 19).
- **New types (optional missions only; settings stored as JSON in the `config` column)** — the form adapts to the type:
  - *Step chain*: 2 to 6 ordered steps, each of one type (talk to NPC, defeat, visit, collect, survive, hand in item,
    pay Ryou), with its own pt/en text. Handing in an item mid-chain needs the receiving NPC (items leave the backpack
    when you talk to them); Pay Ryou only as the last step. Players see the step list (✔ done, ► current) and "Step X of N".
  - *Trip / escort*: destination = village or NPC; starts where accepted, turned in at the destination. Step limit (0 = no
    limit). On foot only: you must walk at least the distance (fast travel doesn’t count); exceeding the limit or dying = failed.
  - *Collect drops*: N drops with that name obtained after accepting (reads the victory’s `dropcode`, so it works with the
    `naruto_monster_drops` table or the old rule) + an optional mission chance (%) per win, against the chosen enemy (or any).
  - *Survive*: N wins in a row without resting, gaining HP (inn, item, medical jutsu) or dying; optional minimum enemy
    level. Healing is detected on every step (HP above what it was after the last win, or a new rest).
  - *Choice (dialogue)*: two options (button, outcome, exp/Ryou/item) picked at turn-in. Any mission can have the two
    options; the *Only appears after* field (+ option A/B) creates different follow-ups for each choice.
  - *Timed*: any type with a *Time limit (minutes)*; the clock starts on accept and shows "Time left". When it runs out
    the mission becomes `falhou` (failed) and can be accepted again from scratch. "Timed" filter in the list.
  - *Daily/weekly*: repeatable every 24 h ("Daily") or 168 h ("Weekly"); shortcuts in the form.
- Player progress: `naruto_missoesextras_jogador` (`estado` andamento/concluida/falhou, `etapa_atual`, `progresso`, JSON
  `dados` with trip steps, survival HP and the chosen option). Events come from `fight.php` (win and death),
  `explore.php` (each step) and `falapersonagens.php` (talking). Turn-ins use an optimistic lock (backpack, bank and
  Ryou checked in the `WHERE`): double clicks never pay twice.
- The editor validates everything (item/enemy/NPC must exist, quantities above zero, coordinates inside the map and
  outside villages), shows only the target field for the chosen type and previews the NPC speech box.
- **Texts** are shown exactly as typed. The English fields (`titulo_en`, `descricao_en`...) are optional; when empty,
  English players see the Portuguese text. The sample missions are also translated in `lang/en/missoes_extras.php`.
- **Map NPCs** ("Map NPCs" tab, `naruto_npcs` table): name, avatar (`layoutnovo/personagem/*.png`), map icon
  (`images/24/arvore_personagem*.gif`), coordinate (not a village or another NPC's tile), speech and English speech
  (`%s` = player name). NPCs used by story missions only appear on that mission's step.

## Items, drops and enemy drop tables

- **Shop items** (`Items`): filters by type, price and name; icon thumbnail; "Sold in" column (villages whose
  `itemslist` contains the item); **Duplicate** button (copies the item and its icon). The editor has three **Special**
  fields (`attribute,value` or `X`); the same attribute cannot appear twice.
- **Drops** (`Drops`): filters by minimum level, attribute and name, three attributes, a **Consumable** field (HP or CH
  potion: goes to the backpack and restores the given amount), the list of enemies that drop it and a Duplicate button.
  Deleting a drop also removes it from the enemy tables.
- Attributes accepted in the special fields: the old ones plus `critico` (extra % excellent hit, max 50), `esquiva`
  (extra % dodge, max 40), `regenhp` (HP per map step) and `economiachakra` (% less jutsu chakra, max 50).
- **Enemy drop table** (`Enemies` > edit > Drops): one row per drop with the chance in % (0.01 to 100). Use
  "+ Add row" and "Remove". With no rows the enemy uses the old rule (1 in 25). The enemy list and the players’ help
  page show "May drop: X (y%)". The player’s Luck and Drop Chance multiply the chances. Duplicating an enemy copies the
  table; deleting the enemy deletes it.

## Rare variants (✦)
- **Enemies > edit > Drops**: the **Rare variant** column is the % of that row's drops that come out as a variant
  (empty = 0.5%; 0 = never; 100 = always, handy for testing). Potions and food never come out as variants.
- **Drops > edit**: shows the common icon and the 6 variants with each bonus, plus the **Give this drop to player** form
  (common or a variant), which puts the item in the player's item bank.
- In the player editor a variant shows in the name as `Name:effect` (e.g. `Kunai:dourado`); you can type it like that in the
  name fields, backpack or bank to give a weapon/vest/headband variant.
- After changing an item or drop icon, run `python3 tools/variantes_itens.py /path/to/game` to regenerate the variant icons
  (without them the variant uses the common icon).

| effect | label | extra bonus | look |
|---|---|---|---|
| `dourado` | ✦ Golden | +10% of the main attribute (min. +1) | recolored in gold + sparkles |
| `brilho` | ✦ Shining | +2% Critical Chance | white/cyan glow + star |
| `raio` | ✦ Electric | +2% Dodge | blue-white lightning around it |
| `chama` | ✦ Blazing | +5 Attack Power | orange flame aura |
| `gelo` | ✦ Frozen | +5 Defense Power | icy blue tint + frost crystals |
| `sombra` | ✦ Shadow | +3% Chakra Economy | purple/black aura |

## Tips

- **Double experience event**: Settings → experience multiplier = 2 → Message to everyone.
- **Stuck player** (battle, duel, trade): player sheet → *Send to village / unlock*.
- **Maintenance**: close the game (only administrators can log in), do what you need and open it again.

## Opening the panel, confirmations and audit
- Opening `admin.php`, `admin_missoes.php` or `gm.php` asks for your password again (valid until the browser closes or 30 minutes
  idle; "Close the panel" ends it). Dangerous actions (delete, ban, promote/demote, mass maintenance, PvP/e-mail) ask once more.
- **Audit log** (`admin.php?do=auditoria`), filterable by author, action, target ID, role and period.
- Optional **IP allowlist** in `config.php`: `"adminips" => "203.0.113.5, 10.0.0.0/8"`.

## GameMasters (gm.php)
- To promote: player sheet → **Role: GameMaster** (`acesso = 3`, access level "Player"). To demote, set the role back to "None".
  Both ask for your password and are audited. GMs show a golden badge next to their name and their own minimap icon.
- A GM **can**: view online players; view a player's sheet (status, history, PvP fights, staff actions on them); **unstick**
  (ends an active duel as a forfeit, cancels trade and fight, sends home); **mute** for 1–10080 minutes and unmute (global chat, map
  chat, forum, private messages); delete chat lines and forum posts/threads; **unlock password recovery for 1 hour** (the player sets
  the new password alone, without the security answers, proving account, character, year, e-mail and level); send a **private
  message as GM**; view their own actions.
- A GM **cannot**: see/set passwords, hashes, answers or codes; give or take items, Ryou, experience or levels; edit sheets, content
  or settings; ban or delete accounts; act on administrators or other GMs; open `admin.php`; take part in open-world PvP.

- The GM can also send the **message to everyone** (same window as the admin, audited as `gm_mensagem_todos`), see the PvP
  **skulls** (read only), **teleport** a player to a coordinate (ends fights, duels and trades; audited as `gm_teleportar`) and see
  the **Game map** (without edit links). Teleport and map can be turned off in **General settings → GM panel**
  (`control.gm_teleporte` / `control.gm_mapa`, on by default). The administrator sees both panels, with a shortcut between them.

## Open-world PvP and skulls
General settings → **Open-world PvP** (on by default since `tools/pvp_caveira.sql`; changing it asks for your password; full rules in [PVP.md](PVP.md)). Defaults: level difference 30, safe
level 15, same-player wait 60 min (both ways), attacker wait 2 min, Ryou lost 10% (goes to the winner up to their limit), item
chance 25% / max 1 (equipment and potions only), backpack and equipped items, rare variants protected, white skull 24 h (+25% item chance),
red skull after 3 unjustified kills for 14 days (loses **all** items on death; bank optional, default no), automatic ban 30 days
(plus half of the items, random) for killing again with a red skull. Self-defense (victim attacked you in the last 15 min) gives no skull.
Exposed skull: 30 min (attackable even offline after each unjustified kill, and no village entry; 0 = off).
- A lost item goes to the winner's backpack **only if there is room**; otherwise it stays with the loser (red skull: destroyed).
- The loser respawns in the home village with 1/4 HP. The defender is notified (popup + history).
- **Skulls (PvP)** menu: list of skulled/temporarily banned players with "Clear skull" and "Ban (days)".

## Game e-mails
General settings → **Game e-mails** (off by default). On: sign-up verification and e-mailed reset links (needs a mail server).
Off: accounts play right away, e-mail optional; recovery through the security questions or recovery code chosen at sign-up, or a GM.

## Rare variants: editing the effects
`admin.php?do=variantes`: pt/en name, color, bonus (`attribute,value` or empty = percentage of the main attribute), percentage,
draw weight and active. The 6 effects are fixed (icons in `layoutnovo/equipamentos/variantes/EFFECT/`, made by `tools/variantes_itens.py`).

## Death to enemies and leaving mid-fight (Main settings)
- **Ryou lost**: base % + level × % per level, capped (default 10 + 1×level, max 50%).
- **Experience lost**: level × % of the current level bar, capped (default 0.5×level, max 25%); never drops a level.
- **Chance of losing one common backpack item**: level × %, capped (default 0.25×level, max 15%).
- **Protected level** (default 5): only Ryou up to it. The page shows a table with the current values for levels 1–80.
- **Leaving mid-fight**: minutes before a fight counts as abandoned (default 5; 0 = off) and the chance (%) that a
  logout/abandonment counts as a death (default 50; 100 = always; 0 = never, just cancels). Duels: always a defeat.
- **Skulls**: white duration (hours), kills for red, red duration (days), item bank, automatic ban days and whether the
  ban also destroys half the items. All range-validated; changing them asks for the password.

## Mission jutsus
- Mission editor: **Reward jutsu** field (optional missions). Jutsu editor: **Mission exclusive** — the panel refuses to
  put an exclusive jutsu in the level table or on a scroll.


## Stacks, selling and the remote buyer

Item and drop editors: **Cannot be sold in shops** and **Stackable** + stack maximum. Main settings: shop consumables stack, village scroll buyer (yes/no) and % paid by the remote buyer. Map NPCs: **Role** field (Scroll buyer).

## Specializations (admin.php?do=classes)
Per-class modifiers, per-class Senjutsu bonus, pt/en descriptions and the level to choose. The player's choice is permanent; in `edituser` an admin may change it (0-3) in exceptional cases — it is audit-logged and does not recalculate stats or jutsus. Simulate first: `tests/classes_sim.py`.
