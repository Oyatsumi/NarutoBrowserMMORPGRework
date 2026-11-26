# Long trainings (Training Panel)

Besides **Senjutsu** (Mount Myoboku) and the **Search Jutsu** (Sand Village), the Training Panel
(`treinamentoequests.php?do=treinamento`) has six long trainings taught by masters in the villages
(`treinoslongos.php`, rules in `funcoestreinos.php`; full Portuguese doc: `docs/TREINOS.md`).

Talk to the master through their icon in the village's **Others** box, start the training (no bonus yet), then do each
session in that same village after the interval. Each session and its reward are written by one conditional
`UPDATE ... AND treinamento='<read value>'`, so simultaneous clicks count once.

| Training | Village (master) | Requirements | Sessions / interval | Per session | On completion |
|---|---|---|---|---|---|
| Element Mastery | Village of Wisdom (Sage Kotone) | Level 15, Genin | 10 / 360 min | +1 max EP (cap 10) | — |
| Chakra Control | Leaf Village (Master Hiroshi) | Level 10, Genin | 8 / 240 min | +2 max Chakra | jutsus cost 6% less Chakra |
| Iron Body | Stone Village (Master Ganseki) | Level 20, Genin | 10 / 480 min | +6 max HP | +4 Defense Power (+4 more for Taijutsu) |
| Ninja Trek | Sand Village (Guide Hayato) | Level 8 | 8 / 180 min | +3 max TP | +1 TP per resting interval |
| Nature Meditation | Mount Myoboku (Shima) | Level 30, Genin, Senjutsu done | 10 / 720 min | +5 max NP | Senjutsu spends 1 NP every 4 s instead of 3 |
| Rare Jutsu Training | Cloud Village (Master Raitō) | Level 35, Chuunin | 12 / 600 min | — | learns jutsu 100, Black Lightning Spear |

Max-stat and defense bonuses are added straight to `maxhp/maxmp/maxtp/maxep/maxnp/defensepower` (level up, specialization
and items only add differences, so nothing is lost or double counted) and recorded in `users.treino_*`. The Chakra saving
goes through `custochakra()` (battle, duel, PvP, menu jutsus); the rest bonus through `encherhp.php`; the Senjutsu
saving through `senjutsuestado()`. Admins tune everything in **Admin panel → Long trainings** (`admin.php?do=treinos`);
migration `tools/treinos_novos.sql`; test `tests/treinos_teste.py`.
