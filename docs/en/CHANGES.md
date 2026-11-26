# What changed in versions 5.0 and 4.0 (2026)

## Installers (5.1)
- Two installers: **`install/instalar.php`** (in the browser, for hosting without SSH: checks PHP, asks for a key stored in
  `instalar_chave.php`, imports the database, writes `config.php`, creates the administrator and locks itself with
  `instalar.lock`) and **`install/install.sh`** (VM/server with SSH and root). Guides: [HOSTING.md](HOSTING.md) and [INSTALL.md](INSTALL.md).

## Version 5.0 — summary
5.0 gathers everything done after 4.0 (details in each section below):
- **World**: 400-tile map with endgame content, enemies by terrain, fog of war on the world map and villages fully in the database.
- **Combat and items**: new bonuses (critical, dodge, regeneration, chakra savings; up to 3 per item), per-enemy drops,
  rare variants (✦), stacks, 8-slot backpack, shop consumables, the remote buyer and level-scaled death.
- **Progression**: in-game specializations (with per-class Senjutsu), configurable jutsus + 30 new ones and scrolls,
  mission-exclusive jutsus, Alchemy 2.0 and database missions (chain, travel, gather, survive, choice, daily/weekly).
- **PvP**: rebuilt duels, open-world PvP with skulls, GameMasters with their own panel, and panels with re-authentication and audit log.
- **Avatars without VIP**: the VIP/premium account concept is gone (the bank holds 30 items for everyone; the `towns.vip`
  column was dropped). Avatar = portrait + animations; 3 free (Naruto, Sakura, Shikamaru), 8 unlocked by **Disguise Scrolls**
  (rare drops 301–308, 0.25–1% on enemies across level ranges), 6 by **missions** (new 160–162 and the hard 134, 144, 145)
  and #16 through Senjutsu. Table `naruto_avatares` (mode per avatar, editable in `admin_avatares.php`, which can also give an
  avatar to a player, logged in the audit log), `users.avatares_liberados`, `drops.ensina_avatar`, `missoesextras.recompensa_avatar`.
  Existing players keep their current avatar.
- **Public profile** (`mostrarchar.php` and `index.php?do=onlinechar`) share one sheet (`funcoesficha.php`): home village,
  rank (incl. Jounin), specialization (or none), new stats, duels, map discovered, PvP status, staff badge/skull and
  equipment with variants — no e-mail, bank, account or recovery data.
- **Credits and donations**: footer link "Help the developers by donating" → `creditos.php` (credits, repository and a Stripe
  donation button, `control.linkdoacao` / `control.linkgithub`); a donation window in the admin panel every `control.doacaodias`
  days (`users.doacaovista`). Page time and query count in the footer are shown only to administrators and GMs.
- **Languages**: everything in Portuguese and English. **Version** `5.0` (`$version` in lib.php).

## Battle luck and small potions
- **Battle luck** (`fight.php`, normal victory): the odds are unchanged (NP 40%, HP 20%, CH 20%, TP 10%, EP 10%).
  Amount = base (HP 1–20, CH 1–10, TP 1–15, NP 1–5, EP 1–2) + `floor(base × 2% × enemy level)` (except EP, as before)
  **+ `floor(bar maximum × P × rand(50,100)/100)`**, with P = HP 5%, CH 5%, TP 6%, NP 4%, EP 5%; never above the maximum.
  At low levels the new part adds 0–2 points; at level 100 (typical bars) the average goes 31→55 HP, 16→31 CH,
  24→38 TP, 9→23 NP and 1.5→3 EP. Explained in the help page (Battles), in Portuguese and English.
- **Small potions** (drops 431–435, stack up to 20): Small Health Potion (`hp,20`, 15 Ryou), Chakra (`mp,15`, 12),
  Travel (`tp,8`, 10), Nature (`np,10`, 10) and Element (`ep,2`, 12). Sold in the *Consumables and selling* screen of Leaf,
  Myoboku, Stone and Mist (`towns.consumiveislist` + `drops.precoloja`, with quantity) and dropped by enemies up to level 10
  (HP 2.5%, CH 2% and TP/NP/EP 1.5% in rotation). Used from the backpack (`backpack.php`), also mid-fight, like the other
  potions. Own icons in `layoutnovo/equipamentos/drops/43x.gif` and `consumiveis/43x.gif` (`tools/pocoes_pequenas.py`).

## Level-scaled death, abandoned fights, mission jutsus and exclusives
- **Death to map enemies scales with level** (`fight.php :: morrernaluta()/penalidademorte()`), previously half the Ryou
  for everyone: Ryou % = `min(max, base + level × k)`, experience = % of the current level bar (never drops a level),
  chance of losing (destroying) one common backpack item (never variants, scrolls or mission items; bp1..bp8 or bp1..bp4,
  checked at runtime). Up to level 5 only Ryou (11–15%). One transaction with the player locked; the death screen and
  the history show the losses (pt/en). Configurable in the admin panel.
- **Leaving mid-fight**: logging out during a PvE fight (or a fight idle for more than N minutes, default 5, checked on the
  player's next page and by a cheap sweep on 1 in 20 page loads) counts as a death with the configured chance (default
  50%); otherwise the fight is just cancelled with no reward. Logging out during a duel = forfeit (loses, wager included).
  Logout only applies the penalty to real navigation (`Sec-Fetch-Dest: document`): an image pointing at the logout URL in a
  post cannot kill anyone's fight. The outcome is logged in the history (i18n).
- **Missions with a reward jutsu** (`naruto_missoesextras.recompensa_jutsu`): taught on completion if requirements are
  met; if already known or not learnable, the player gets its scroll (backpack/bank); with no scroll, Ryou (200 × minimum
  level, at least 500) and experience (50 × minimum level). Item rewards that are scrolls now use the studyable format
  `name,jutsu,ID,1`.
- **Mission-exclusive jutsus** (`naruto_spells.exclusivo_missao`): 6 new jutsus (50–55) never learned by level or scroll
  (the admin refuses level/scroll for them; the help page shows "Obtained only through missions").
- **6 new missions** (140–145): three mid-level ones (Leaf chain, timed Mist collection, Cloud hunt) and three Rank S ones
  (Badlands Stone Titan, Abyssal Leviathan within 2 h, Ancient Volcano Dragon within 3 h) rewarding the exclusives 53–55 and
  the Thousand Hungry Roots, Pale Serpent’s Lament and Cracked Sky scrolls.
- **Admin**: "loses half the items" toggle for the red-skull automatic ban (`pvpbanirmetade`).
- **Fight**: the player avatar (all 18 drawn facing right) is mirrored with CSS to face the enemy.

## 8-slot backpack, shop consumables and selling items
- **Backpack with 8 slots** (`bp1..bp8`, new columns `bp5..bp8`) shown as two rows of 4 in the right column
  (`images/bpslots.jpg` redrawn at 177x93). Every code path that walked the backpack (equip, unequip, bank, trade,
  alchemy, drops, missions, graduation, duels, PvP, admin panel) now handles the 8 slots.
- Backpack icons measured with Playwright against the frames of `bpslots.jpg` (analysed with PIL,
  `tests/mochila_quadros.py` + `tests/mochila_medir.js`): all 8 34x34 icons, filled or empty, sit exactly on their frames.
- **Return Scroll** (150 Ryou): used from the backpack outside battles/duels, takes you to the home village of your
  alignment. **Monster Repellent** (200 Ryou): no random monster battles for 100 steps or 15 minutes (whichever ends
  first; columns `repelente_passos`/`repelente_ate`), with an indicator in the left column. Sold in every shop.
- **Selling backpack items** in shops (`index.php?do=vender`): any backpack item, with a base price per kind
  (equipment 1/4 of the price + 25 per refinement; drop 10 + 6 × level; potion half of what it restores; variants ×3)
  and a **jutsu scroll buyer** (50 + 20 × the jutsu’s minimum level). Multiplier set in the admin panel
  (`control.vendamochila`, %). Atomic sale (player locked, item re-checked, `LEAST(gold+x, cap)`, CSRF).

## Configurable jutsus, 30 new jutsus and scrolls
- Jutsus with element, multiple hits, life steal, shield, burn/poison, stun, timed boosts and weakenings, regeneration, chakra
  drain and cooldowns; status icons on the enemy card (`images/status/`). See
  [ARCHITECTURE.md](ARCHITECTURE.md#configurable-jutsus-and-scrolls).
- 30 original jutsus (ids 20-49): 15 learned by level and 15 only from **scrolls** dropped by enemies (0.3-0.5%); using the scroll
  in the backpack teaches the jutsu if the player meets the level/class/rank. The jutsu help page shows effects, cooldown and how to learn.
- Database: new columns in `spells` (golpes, roubovida, efeito*, cooldown, nivelminimo, classe, graduacao, name_en, descricao*),
  `drops.ensina_jutsu` and `users.jutsuestado`.

## Open-world PvP, GameMasters and safer panels
- **Open-world PvP** (`pvp.php`, off by default): attack online players on the same or a neighboring tile outside villages. Fights are
  resolved instantly with the duel engine; configurable losses (Ryou, items, protected variants), anti-hunting waits, safe level,
  white/red **skulls** with automatic bans.
- **GameMaster** (`gm.php`): limited panel to help and moderate (unstick, mute, delete messages, unlock password recovery, message as GM), all audited.
- **Panels**: password again (30 min), password confirmation for dangerous actions, audit log (`adminlog`), IP allowlist.
- **Accounts without e-mail** (default): active right away; security questions or a recovery code at sign-up. Passwords hashed with Argon2id.
- **Editable rare variants** (`naruto_variantes`, `admin.php?do=variantes`); variant icons regenerated (thinner glow, aligned).
- Fixes: no trading during a duel; "Send to village" ends the duel; backpack potion icons fall back to `pocao_*.gif`; old duel keys removed.
- Database: new `control`/`users` columns and tables `adminlog`, `pvp`, `variantes`.

## Optional dynamic updates (AJAX)
- New panel (and installer) option: load pages without reloading the whole screen, using `js/navegacao.js`
  (native JavaScript, no jQuery). Off by default; when off, the HTML is identical to before.
  See [ADMINISTRATION.md](ADMINISTRATION.md).

## PvP duels rebuilt
- Lobby (`users.php?do=batalha1`) with online ninjas at the same spot, received/sent challenges (accept, refuse,
  cancel; valid for 5 minutes), challenge by name and your last duels. The challenged player gets a history notice.
- Optional Ryou wager, escrowed from both players on acceptance; the winner takes it all plus 5 x the loser's level in
  experience (if the duel lasted 4 turns or more). No item loss.
- 60 s turns with a countdown; timeout = lose the turn, 3 timeouts = defeat. Actions: attack, jutsu, defend (half
  damage), backpack potion and forfeit. Same formulas as PvE (critical, dodge, elements, Chakra Saving). Nobody dies:
  a player who would drop to 1 HP or less stays at 1 HP and loses (lost HP is not restored).
- Screen with both "player cards" (in the monster-card format), a log of the last 8 actions in each player's own
  language, win/loss record on the character sheet and a **Duel Ranking** (`rank.php?ord=duelos`).

## Missions in the database and extra missions
- The 21 story missions and the 6 map NPCs moved out of the code (`missoes.php`, `treinamentoequests.php`,
  `localpersonagens.php`, `falapersonagens.php`) into the database (`naruto_missoesextras`, `naruto_npcs`), with a
  single engine in `funcoesmissoesextras.php`. Player progress (`users.missao`) is unchanged; the panel and speeches
  are identical (compared step by step in Portuguese and English).
- Optional **extra missions** (`missoesextras.php`, linked from the Mission Panel): 12 new missions across 6 villages
  plus 4 new map NPCs (hand in item, defeat enemy, visit location, pay Ryou, talk to NPC; some are daily).
- Mission and NPC editor in the admin panel (`admin_missoes.php`). NPCs appear on the mini map and the world map.
- "Extra missions" are now simply called **Missions** (player page, Mission Panel link and admin menu).
- **New mission types**: step chains, on-foot trips/escorts, drop collection, survival (wins in a row without healing),
  choices with two outcomes (and different follow-ups), timed missions (with "time left" and failure), daily and
  weekly. The panel shows the current step, count and time left.
- **15 new missions** (levels 1 to 55, across 7 villages and 4 NPCs) and 4 new NPCs: Rensuke the lookout, Suzuran the
  herbalist, Botan the merchant and Itsuha the archaeologist. Among them: the mystery of the bell, escorting the
  apprentice, the salt wagon, the duel at the canyon, the lantern festival, the whispering ruins, the sealed letter
  (with two follow-ups), the mine rescue, the Oni of the volcanic mountain and the castle banquet.

## Items, combat bonuses, per-enemy drops and new enemies
- Items with **up to 3 bonuses** and **4 new combat bonuses**: Critical Chance, Dodge, HP Regen per Step and Chakra
  Savings (formulas in [ARCHITECTURE.md](ARCHITECTURE.md)), in fights, on the map, in tooltips and in the help pages.
- **Per-enemy drop table** with a % chance (`naruto_monster_drops`), editable in the panel and shown in the help page
  ("May drop"). Every enemy got a table based on the old rule (~4.5% equippable drop + potions).
- **Consumable drops** (potions and food that go to the backpack).
- **38 new shop items** (ids 47-84, in every village with a shop), **36 new drops** (51-86: accessories, materials and
  consumables) with original icons, and **20 new enemies** (167-186, levels 3 to 59, some with map regions), balanced
  against their level neighbours. Panel: filters, thumbnails and a Duplicate button for items and drops.
- Fights: victory and "take drop" lock the player (no duplication on parallel clicks); fights longer than 127 turns no
  longer cause a database error.

## Enemies by terrain and a third batch of enemies
- New column `monsters.terrenos` (comma-separated names from the `terrenos` table; empty = any terrain). The enemy only
  appears when the player's tile has one of those terrains (the same terrain drawn on the minimap). If no enemy in the
  level band may appear on the tile's terrain, the restriction is ignored there (there is always an enemy).
- `terrenodacasa()` moved from `templates/minimap.php` to `funcoesterreno.php` (used by the minimap, fights, the panel and help).
- Panel: *Appears only on these terrains* checkboxes on the enemy, terrain column and filter in the list; *Duplicate* copies terrains.
  Enemy help and the enemy world map show "Terrain: Desert, Rocky".
- Generic beasts and creatures got terrains (wolves/boar/bear → forest/grass, serpents/toads/slugs → swamp,
  golem/eagle → desert/rocky...); story and named enemies stay unrestricted.
- **20 new original enemies** (187-206, levels 4 to 61), all with terrains, element and their own drop table
  (with rare-variant chance). Max level 61: with a 300-tile map the farthest band is 59-61.

## Bigger world (400 tiles) and endgame content
- Map enlarged from 300 to **400 tiles per side** (`control.gamesize = 400`). Walking, minimap, world map, admin panel and help
  already used `gamesize`; the minimap tree scatter now uses a fixed seed of 300 (trees in the old areas stay put).
- New world map `images/mapa_grande.png` (667x667, built by `tools/gerar_mapa_grande.py`): the original map is untouched in the
  middle and gets a new outer ring in the same style — glacier to the north, frozen sea with ice floes in the north-east, open
  sea with islands and mangrove islands to the east, volcanic lands to the south, canyon badlands in the south-west and the
  great desert with canyons to the west. The world map and the admin map stretch the image over all coordinates (`background-size`).
- Terrains 12-19 for the ring (snow, coast, swamp, rocky, desert) and the new **volcanic** terrain (`images/terreno_vulcanico.jpg`,
  dark basalt with lava cracks; tiles and decorations — lava rock, smoking vent, dead tree — built by
  `tools/gerar_tiles_terreno.py GAME_DIR vulcanico`).
- **15 original endgame enemies** (207-221, levels 62 to 80) with ring terrains, element and their own drops; three bosses
  immune to damage and sleep (Abyssal Leviathan, Badlands Stone Titan, Ancient Volcano Dragon) with x5 HP and x3 exp/Ryou.
- **15 new drops** (87-101, minimum level 62 to 80), better than the old ones, with up to 3 bonuses (critical, dodge, regeneration,
  chakra saving...), dropped only by those enemies; 3% rare-variant chance (bosses 4-5%).
- Levels 1-100 already existed in `naruto_levels`: players reach level 80 with no change to the experience curve.

## Rare item variants (✦)
Any equippable drop can come out as a **rare variant**: the same item with a special skin and a small extra bonus.
Each row of an enemy's drop table has a variant chance (default 0.5% of drops; potions/food never). The effect is
picked among the 6. The name is shown with a colored label ("Kunai ✦ Golden"), with its own icon in the right column,
backpack, bank, trade, alchemy, character sheets and tooltips (which show "✦ Rare variant" and the bonus). The variant is
stored in the item name itself (`Name:effect`), so it travels along when equipping, unequipping, banking, gifting and
trading. Selling it at the shop warns that it is rare. The monster help shows "✦ rare: 0.5%".

| effect | label | extra bonus | look |
|---|---|---|---|
| `dourado` | ✦ Golden | +10% of the main attribute (min. +1) | recolored in gold + sparkles |
| `brilho` | ✦ Shining | +2% Critical Chance | white/cyan glow + star |
| `raio` | ✦ Electric | +2% Dodge | blue-white lightning around it |
| `chama` | ✦ Blazing | +5 Attack Power | orange flame aura |
| `gelo` | ✦ Frozen | +5 Defense Power | icy blue tint + frost crystals |
| `sombra` | ✦ Shadow | +3% Chakra Economy | purple/black aura |

Icons: `layoutnovo/equipamentos/variantes/<effect>/<id>.gif` and `.../<effect>/drops/<id>.gif`, generated by `tools/variantes_itens.py`.
Also fixed: selling a refined ("+N") item at the shop did not remove the refine bonus.

## Compatibility
- Runs on **PHP 8.x** (previously PHP 5/7). All PHP 8 fatal errors found were fixed: unquoted constants,
  arithmetic on strings, `mysql_*`, `get_magic_quotes_gpc`, `sizeof()` on strings, comparisons whose meaning
  changed (`"" == 0`), division by zero, undefined variables passed to mysqli.
- Runs on **MariaDB/MySQL in strict mode** (inserts with `id=''`, values out of column range, zero dates).
- **Everything in UTF-8** (files, pages and a `utf8mb4` database). Accents that had turned into `?`/`�` in 19 files and
  in the database data were recovered automatically (by comparing with the 2012 version) and reviewed.

## Cleanup of the base system
- Removed every trace of the engine the game was originally based on: table prefix (`dk_` → `naruto_`),
  cookie (`dkgame` → `narutorpg`), English panel texts, translated help, links and credits.

## Refactoring
- Code commented in Portuguese (a header in each file, rules and formats documented), keeping the author's
  procedural style and the names of routes, fields and templates.
- Copy-pasted blocks became helper functions; dead code was removed (e.g. `users.php` 2418 → ~790
  lines, `missoes.php` 1089 → ~350, `funcaoitens.php` 1200 → ~680, `treinamentoequests.php` 874 → ~270).

## Game
- **88 new enemy cards** (previously only 78 of the 151 enemies had a card) and **15 new enemies** (152–166)
  using portraits that were drawn but never used in `monstros.psd`.
- The main story can be completed from start to finish (Missions 3, 7, 10, 12 and 20 were broken; mission 7 had
  an infinite reward loop).
- The NPCs' "Talk" button for stages 6 to 18 works again (an `else` removed the button so only the last NPC worked).
- Fleeing a battle can now actually fail; the Ryou bonus is computed on Ryou (it was computed on experience);
  element attribute bonuses now work; buffs no longer carry over to the next battle.
- Duels no longer leave players stuck; trades and the bank no longer duplicate items; resting no longer speeds up with several tabs.
- Empty equipment slots, new villages and new enemies have default images (no broken images).
- Timers (Senjutsu, training, missions) are correct across month boundaries.
- Help rewritten for the game, with tables read from the database.

## Administrator Panel (new)
- With the game's look, anti-CSRF tokens and validation of every field.
- New operations: summary and warnings, news (edit/delete), message to everyone, player search/filters,
  ban/unlock/heal/delete/change password, chat and forum moderation, maintenance, creating/deleting enemies
  (with card upload), items and drops (with icon), jutsus, villages (with 4 images) and levels.
- New settings: sign-ups open, game-closed message, login notice, starting Ryou, battle chance,
  experience/Ryou multipliers, minimum PvP level, default language.

## Villages fully database-driven and minimap terrains

- Rules hard-coded in PHP by village id are now `naruto_towns` columns: services (`tem_pousada`, `tem_loja`, `tem_mapas`,
  `tem_banco`, `tem_missoes`, `tem_graduacao`, `tem_alquimia`, `tem_senjutsu`, `tem_jutsubusca`), `secreta` (map not sold;
  formerly villages 8-10), `alinhamento` + `vilanatal` (formerly the folha=1, pedra=3, nevoa=4, areia=5, nuvem=6 table in
  fight.php/admin.php), `name_en`, `kage_en`, `descricao`, `descricao_en`.
- Registration now lets the player choose a **home village** (villages with `vilanatal = 1`); dying returns you there (fallback village 1).
- Village page: without a title image the name is rendered as styled text; the description shows under the title.
- Admin: edit every field, upload every image, ready-made icons for new villages, new *Minimap terrains* section.
- Minimap: each tile has a terrain (grass, forest, trail, desert, rocky, snow, coast, swamp) from the new `naruto_terrenos` table.
- Coherent minimap: per-cell 30x30 tiles (no trees/rocks cut at borders), 4 ground variants per terrain, ragged edge and
  corner transitions between terrains and per-biome decorations (trees, pines, rocks, cacti, reeds; none on water).
  `arvores` now toggles the biome decorations (only the coast has them off).
- Shop (towns.php buy3): fixed Ryou duplication when buying while the equipped item was being deposited (transaction with `FOR UPDATE`).

## Languages
- The game is now in **Portuguese and English**. Players switch in the footer, on the login page or in the panel's
  top bar (`?idioma=` + `idioma` cookie); the default language is chosen in the panel (`control.idiomapadrao`).
- Translations in `game/lang/en/*.php` (the Portuguese text is the key), messages stored in the database in a neutral
  format (`i18n:`), images with text translated in `en/` subfolders. See [ARCHITECTURE.md](ARCHITECTURE.md#languages-i18n)
  and [TRANSLATING.md](TRANSLATING.md).

## Security
See [SECURITY.md](SECURITY.md): bcrypt, signed cookie, brute-force lockout, password recovery by link,
removal of `extract($_POST)`, SQL injection fixes, transactions against duplication, CSRF in the panel, safe uploads,
`.htaccess` and an installer with minimal permissions.

## Installation
- `install/install.sh`: from-scratch installation on Debian/Ubuntu (Apache, PHP, MariaDB, database, config, admin,
  optional HTTPS and e-mail), interactive or unattended.
- Clean installation database (no old player data).


## Stacks, unsellable items, trading and the remote buyer (Nov 2026)

- **Stacks**: a backpack slot (and a bank entry) may carry a 5th field with the quantity:
  `Incenso Repelente,rep,100,X,5`. Without it the quantity is 1 (old strings stay valid). Only items whose first 4
  fields match are merged. `items/drops.empilhavel` (0/1) and `maxpilha` (default 20) decide who stacks; drop potions
  (`hp`/`mp`, found by name + `consumivel`) and jutsu scrolls (`ensina_jutsu`) use the `drops` row; the Return Scroll and
  Repellent Incense use `control.pilhaconsumiveis` (1 = no stacking). Helpers in `funcoesinclusas.php`: `qtditem()`,
  `itemsemqtd()`, `itemcomqtd()`, `pilhamaxima()`, `mochilaguardar()`, `sqlmochila()`, `iconecomqtd()`. Using, selling
  (with a quantity), dying and drinking in a duel take 1 unit; buying, drops, unequipping, bank, trades, PvP loot and
  mission rewards merge into stacks. Alchemy refuses stacks.
- **`items/drops.naoVendavel`**: village shops, scroll buyers and the automatic resale when buying new equipment refuse
  the item (in the last case it goes to the backpack). Shown as "Cannot be sold" in tooltips and help.
- **`users.trocaate`**: deadline (UNIX) of a player trade (10 min, renewed on each proposal; 0 = none). The offered item
  may carry a quantity (part of a stack). After the first acceptance both offers are locked.
- **`npcs.papel`**: `comprador_pergaminhos` = NPC that opens `index.php?do=comprador` (Hermit Kaizō, 40N,380W, far
  western canyons). Pays `control.compradorremoto` % (default 200) of the village price; `control.compradorvila` = 0
  closes the village scroll buyers.

## In-game specialization choice and per-class Senjutsu
- Registration no longer asks for a specialization (`charclass = 0`); the **permanent** choice is made in `especializacao.php` from `control.classenivel` (default 5), in any village.
- New **`classes`** table (combat modifiers and Senjutsu bonus per class), editable in Admin > Specializations. New column `control.classenivel`; `users.charclass` now defaults to 0.
- Rebalanced `levels` table (one experience curve for everyone, per-class HP/CH) and 12 exclusive jutsus (56-67). Simulation numbers in [SPECIALIZATIONS.md](SPECIALIZATIONS.md).
- Senjutsu: bonus computed at combat time (gone as soon as NP runs out, also in duels/PvP), per-class bonus, NP drain keeps leftover seconds. Accounts with the old bonus stored in `attackpower/defensepower` were fixed.


## Alchemy 2.0
Recipes moved to the database (`naruto_receitas`, 51 recipes, the 7 old ones migrated unchanged), multi-ingredient quantities, alchemy level/experience, success chance with partial loss, 3→1 refining, rare variant infusion, potions, jutsu scroll copying, secret recipes and a daily critical. See [ALCHEMY.md](ALCHEMY.md).

## Changing the home village
Registration no longer asks for a home village (new ninjas get `control.vilainicial`, default Leaf; a forged field is ignored); choosing the recovery code hides the secret questions; players can change difficulty through the Master of Trials' trials 190-192 (NPC 40: level 15, fights in a row at the new tier, a Ryou offering, 14-day wait `control.trocadif_dias`, atomic delivery + audit log); the History box shows 12 messages (`control.historicomax`, was 5). See [DIFFICULTY_CHANGE.md](DIFFICULTY_CHANGE.md) and `tests/cadastro_dificuldade_teste.py`.

Players can now change their home village mid-game through the **transfer mission** of the new village's ambassador (NPCs 32-36, missions 177-181): level 30, Chuunin, enemies around the village, the border marker and a 20,000 Ryou citizenship fee, then a 30-day wait (`control.trocavila_dias`, admin-tunable). Secret villages and villages without an alignment are refused; the switch is atomic (transaction + `FOR UPDATE`) and recorded in the player history. Death, PvP, Return Scroll, Random Teleport, emblem and character sheet follow the new village. See [TROCA_DE_VILA.md](../TROCA_DE_VILA.md) and `tests/troca_vila_teste.py`.

## More expensive Travel To and the Travel Seal

*Travel To* now costs TP by distance and much more than the Return Scroll (home village: at least 3× the TP and 4× the price of the scroll);
other villages also spend a **Travel Seal** (drop 436: level 20-60 enemies, Alchemy and repeatable missions 111/131). A travel screen shows the cost
before confirming; all values are in the admin panel (`viagemtpbase`, `viagemtpdist`, `viagemnataltppct`, `viagemnatalryoupct`, `viagemoutraryou`,
`viagemselos`, `viagemseloitem`, `viagemryou`). Formula: TP = village TP + base + ceil(distance × per100 / 100). Migration `tools/viagem_custo.sql`; test `tests/viagem_custo_teste.py`.

## Durability of strong items and repair

Durability existed but every item was infinite. Now the best shop weapons, vests and headbands, drops of level 45+ and the
trade-off items 420-425 come with 500 to 800 victories of durability (`items/drops.durabilidade`; 0 = infinite); weak items
and items players already owned stay infinite. Warning at ≤ 10%, breaking removes the bonuses, a *Repair equipment* tab in
village shops (`control.reparo_pct`, default 30% of the item value for a full repair, atomic) and the switch
`control.durabilidade_ativa`. Details (Portuguese) in `docs/DURABILIDADE.md`; migration `tools/durabilidade.sql`
(generated by `tools/durabilidade.py`); test `tests/durabilidade_teste.py`.
