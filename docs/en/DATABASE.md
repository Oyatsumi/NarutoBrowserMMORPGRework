# Database

All tables use the prefix from `config.php` (default `naruto_`), InnoDB and `utf8mb4`.
The full schema and the initial data are in [`sql/naruto_rpg.sql`](../../sql/naruto_rpg.sql).

| Table | Contents |
|---|---|
| `control` | General settings (one row, id = 1): name, map size, game open/closed, e-mail, specializations, difficulties, sign-ups open, notices, starting Ryou, battle chance, exp/Ryou multipliers, minimum PvP level, default language (`idiomapadrao`), sell multiplier for backpack items in shops (`vendamochila`, %). |
| `users` | Players (account, password, character and the whole game state). `batalha_timer2 = 5` marks a player in an active duel; `duelos_vencidos`/`duelos_perdidos` hold the PvP record. |
| `monsters` | Enemies: HP, damage, defense, level, max exp/Ryou, immunity (0 none, 1 damage, 2 damage+sleep) and element. Optional: region (`regiao_*`) and `terrenos` (e.g. `deserto,rochoso`; empty = any terrain). |
| `items` | Shop items: type (1 weapon, 2 vest, 3 headband), price, attribute and special `atributo,valor`. |
| `drops` | Items dropped by enemies: minimum level and two attributes `atributo,valor` (or `X`). |
| `spells` | Jutsus: chakra, value, type (1 heal, 2 damage, 3 sleep, 4 +attack, 5 +defense, 6 support), element, `golpes` (hits), `roubovida` (life steal), extra effect (`efeito`, `efeito_valor`, `efeito_turnos`, `efeito_chance`), `cooldown`, requirements (`nivelminimo`, `classe`, `graduacao`) and texts `name_en`/`descricao`/`descricao_en`. `drops.ensina_jutsu` = jutsu taught by the scroll. |
| `levels` | Total experience and per-level gains for the three specializations (`1_*`, `2_*`, `3_*`). |
| `towns` | Villages: position, inn and map prices, TP to travel, items for sale, kage. |
| `news` | Village news. |
| `babble` | Global chat. |
| `chatmap` | Per-coordinate chat (last 10 lines per place). |
| `forum` | Forum (topics have `parent = 0`; replies point to the topic). |
| `duelos` | PvP duels: challenger (`desafiante`), challenged (`desafiado`), `estado` (`pendente`, `ativo`, `fim`, `recusado`, `cancelado`, `expirado`), escrowed wager per player (`aposta`), `vez` (id of the player to move), turn deadline `prazo` (UNIX), `turno`, jutsu bonuses (`dano1/2`, `defesa1/2`), defending flags (`guarda1/2`), timeouts (`atrasos1/2`), `vencedor`, `registro` (last 8 actions in `i18n:` format) and `jutsuestado` (jutsu effects and cooldowns, JSON with sides `1`/`2`). Side 1 = challenger. |
| `loginfail` | Failed login attempts per IP (15-minute lockout after 5 failures). |

## `users` fields stored as text

| Field | Format | Example |
|---|---|---|
| `spells` | IDs of learned jutsus | `0,1,6,11` |
| `jutsuestado` | active jutsu effects and cooldowns of the current fight (JSON, cleared when the fight ends) | `{"efeitos":{"ini":{"veneno":{"v":6,"t":3,"n":"..."}}},"cd":{"jog":{"29":2}}}` |
| `towns` | IDs of villages whose map the player owns | `0,1,4` |
| `missao` | `etapa,progresso,meta,minutos` (stage, progress, goal, minutes) of the main story | `12,3,5,60` |
| `durabilidade` | `-,arma,colete,bandana,slot1,slot2,slot3` (weapon, vest, headband, slots; `X` = empty/infinite) | `-,40,X,X,12,X,X` |
| `bp1`..`bp8` (backpack, 8 slots) | `nome,id,tipo,durabilidade` or `None` (`tipo` 1-3 equipment, 4 drop; text ids: `hp`/`mp` potions, `jutsu` jutsu scroll, `ret` Return Scroll, `rep` Monster Repellent) | `Kunai+1,3,1,X` |
| `repelente_passos`, `repelente_ate` | Monster Repellent: steps left and end of the effect (UNIX); active while both last | `100`, `1793600000` |
| `bancogeral` | items stored in the bank, `nome,id,tipo,durabilidade;` repeated | `Shuriken,4,1,X;Pedra Sannin,20,4,X;` |
| `trocajogador1/2` | offer made / offer received: `Nome;item;ryou;espaço` (name; item; Ryou; slot) | `Sasuke;Kunai;100;2` |
| `elemento` | `natural,ataque` (natural, attack) | `neutro,neutro` |
| `ultmonstro` | `last enemy,times in a row` | `Lobo,3` |
| `historico` | last 5 messages separated by `;;` (may use the `i18n:` format, see [ARCHITECTURE.md](ARCHITECTURE.md#messages-stored-in-the-database)) | |
| `caixadepm` | up to 25 private messages separated by `@#$%!;;;` | |
| `mainmsg` | floating window: `imagem#$%;TÍTULO#$%;conteúdo` (image, title, content), or `1` (search jutsu), `2,,nome` (send PM), `3` (senjutsu), `4` (forum), or `None` | |
| `senjutsutimer` / `missaoultdata` | dates as text used by timers | `1/11/2026,13:05:10,60` |
| `currentaction` | `In Town`, `Exploring` or `Fighting` | |
| `authlevel` / `acesso` | 0 player, 1 administrator, 2 banned / 2 tutor, 3 GameMaster | |

## Items: special codes

The `special` field (items) and `attribute1/2` (drops) use `atributo,valor`, and the attribute is a **`users` column**
that receives the value when equipping (and loses it when unequipping). Accepted attributes: `maxhp`, `maxmp`, `maxtp`,
`maxnp`, `maxep`, `goldbonus`, `expbonus`, `strength`, `dexterity`, `attackpower`, `defensepower`, `sorte`,
`agilidade`, `determinacao`, `precisao`, `inteligencia`, `droprate`. The panel rejects anything else (these become
column names in SQL, so the whitelist is a safeguard).

Since 2026 there are `special2`/`special3` (items) and `attribute3` (drops), the attributes `critico`, `esquiva`,
`regenhp` and `economiachakra` (`users` columns), `drops.consumivel` (`hp,N`/`mp,N`) and the `naruto_monster_drops`
table (`monster_id`, `drop_id`, `chance_percent`) — see [ARCHITECTURE.md](ARCHITECTURE.md).

Small potions (`tools/pocoes_pequenas.sql`): `drops.consumivel` also accepts `tp,N`, `np,N` and `ep,N`; `drops.precoloja`
(price in the consumables shop, 0 = not sold) and `towns.consumiveislist` (drop ids sold in that village's shop).
Drops 431–435 (Small Health/Chakra/Travel/Nature/Element Potion), sold in villages 1–4 and dropped by enemies up to level 10.


## Images tied to the database

| Image | Source |
|---|---|
| `layoutnovo/monstros/{id}.jpg` | enemy card (295x145); falls back to `padrao.jpg` |
| `layoutnovo/equipamentos/{id}.gif` | shop item icon (34x34) |
| `layoutnovo/equipamentos/drops/{id}.gif` | drop icon (34x34) |
| `images/24/cidade_{id}.gif` / `cidade_{id}p.gif` | known / unknown village icon (24x24) |
| `images/town_{id}.gif` | village title (300x25) |
| `images/meiocidade_{id}.jpg` | village background (487x284) |
| `layoutnovo/personagem/{id}.png` | kage portrait (115x113) |
| `layoutnovo/avatares/{n}.jpg`, `layoutnovo/graficos/{n}_*.gif` | character avatar and sprites |

Images with text may have a translated copy under `<folder>/en/` (see [ARCHITECTURE.md](ARCHITECTURE.md#images-with-text)).


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

## Specializations
- **`classes`** (id 1 Ninjutsu, 2 Taijutsu, 3 Genjutsu): combat modifiers and active-Senjutsu bonus (`sj_*`) per class, plus `descricao`/`descricao_en`. See [SPECIALIZATIONS.md](SPECIALIZATIONS.md).
- `control.classenivel`: minimum level to choose a specialization. `users.charclass`: 0 = none yet (default), 1-3 = class.


## Alchemy
`naruto_receitas`, `users.alquimia_*`, `control.alquimia*`. See [ALCHEMY.md](ALCHEMY.md).

## Avatars, donations and credits (5.0)
- `naruto_avatares` (`id`, `nome`, `modo` = `livre` (free)/`drop`/`missao` (mission)/`especial` (special), `ativo`, `ordem`):
  avatar catalog (portrait `layoutnovo/avatares/{id}.jpg` + animations `layoutnovo/graficos/{id}_*.gif`). VIP accounts no
  longer exist (`users.acesso = 1` became 0 and `towns.vip` was dropped).
- `users.avatares_liberados` = `"6,9,4"` (free avatars and the current one always count); `users.avatar` now defaults to 6 (Naruto).
- `drops.ensina_avatar` = avatar unlocked by a Disguise Scroll (drops 301–308, unsellable, only through drop tables).
- `missoesextras.recompensa_avatar` = avatar unlocked when the mission is delivered (if already owned, 300 Ryou × minimum level).
- `control.linkdoacao` (donation button; empty = hidden), `control.linkgithub` (repository), `control.doacaodias`
  (panel donation window interval) and `users.doacaovista` (when the admin last saw the window, UNIX).
- Generated by `tools/avatares.sql` (included by `tools/build_sql.py`; safe to run again on an existing database). Scroll icons:
  `tools/avatares.py`.
