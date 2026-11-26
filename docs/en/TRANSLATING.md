# Translating the game (contributor guide)

The game has two languages: Portuguese (`pt`, default) and English (`en`). The code **stays in Portuguese**:
**the Portuguese text itself is the translation key** (gettext style). The infrastructure is at the top of
`game/lib.php` and is explained in [ARCHITECTURE.md](ARCHITECTURE.md#languages-i18n).

## Tools

- `t("Texto em português")` → returns the text in the player's language (or the Portuguese text itself if there is no translation).
- `tf("Você ganhou %s de experiência e %s Ryou.", $exp, $gold)` → the same, with values replacing `%s` (vsprintf).
  In keys used with `tf()`, a literal `%` must be written `%%` (e.g. `tf("Bônus de %s%%", $x)`).
- `idioma()` → `"pt"` or `"en"`. `seletoridioma()` → the "Português | English" links.
- Inside heredocs use variable functions: at the start of the function declare `$t = "t"; $tf = "tf";` and write
  `{$t("Texto")}` or `{$tf("Você tem %s Ryou", $gold)}`.
- Translations: files `game/lang/en/<area>.php` that return an array `"Portuguese key" => "English text"`.
  Prefer editing the file for the area you are translating. Format:
  ```php
  <?php // lang/en/nucleo.php :: traduções em inglês das telas da vila, mapa, lojas e viagens.
  return array(
      "Você chegou em seu destino." => "You have arrived at your destination.",
      "Você ganhou %s de experiência." => "You gained %s experience.",
  );
  ```
  The key must be EXACTLY the text passed to `t()`/`tf()` (same accents, spaces, punctuation and HTML).

## Rules

1. **Everything the player sees** must go through `t()`/`tf()`: page text, titles (2nd parameter of `display()`),
   buttons (`value="..."`), visible `title=`/`alt=`, error messages, texts in `header("Location: ...?conteudo=...")`
   (translate BEFORE putting it in the URL: `rawurlencode(t("..."))`), e-mail texts, tooltips (`explicdrop`) and
   text generated in inline JavaScript.
2. **Do not translate** internal values the code compares or stores: `currentaction` (`In Town`, `Exploring`,
   `Fighting`), `None`, `X`, field/column names, `do=` routes, `<option value>`/`name=` values, array keys,
   image file names. Only displayed text.
3. **Sentences with variables**: never concatenate separately translated fragments if word order changes in another
   language. Use a whole sentence with `%s`: `tf("O jogador %s está na coordenada %s.", $nome, $coord)`.
   HTML may stay inside the key (`"Você <b>morreu</b>."`), but prefer short, stable keys.
4. **Names coming from the database** (enemies, items, drops, jutsus, villages, kages, ranks such as "Genin"):
   display them with `t($row["name"])`. Their translations live in dedicated files (`lang/en/conteudo_*.php`).
   Keep using the original (Portuguese) value for comparisons and writes — only the display is translated.
5. **Messages stored in the database for ANOTHER player to read later** (history, private messages, the
   `mainmsg` floating window): the reader's language is unknown at write time. Store them in the neutral format
   `i18n:Portuguese key with %s|value1|value2` using `i18n("Chave com %s", $valor)` (values cannot contain `|`)
   and display them with `textoi18n($texto)`: if the text starts with `i18n:` it translates the key and applies
   the values; otherwise it returns the text unchanged (old messages).
6. **English**: natural game English (not a literal translation). Naruto-universe terms stay as in the English anime:
   Ryou, Chakra (CH), jutsu, Senjutsu, Genin/Chuunin/Jounin, Kage, Hokage, Akatsuki, Byakugan etc.
   "Vila da Folha" → "Leaf Village", "Vila da Névoa" → "Mist Village", "Vila da Areia" → "Sand Village",
   "Vila da Pedra" → "Stone Village", "Vila da Nuvem" → "Cloud Village", "Montanha Myoboku" → "Mount Myoboku".
   HP, CH (chakra), TP (travel points), NP (natural points), EP (element points) keep their abbreviations.
   Avoid straight single quotes in English (`t()` already turns `'` between letters into `’`).
7. Do not change game rules, layout, or file/route names. Keep `php -l` clean.
8. **Test in both languages**: force the language in the URL (`&idioma=en` / `&idioma=pt`, stored in a cookie)
   or with the cookie `idioma=en`. Use `tests/fetch.py` and screenshots (see [TESTING.md](TESTING.md)). With
   `"debug" => true`, every untranslated text appears in the PHP server log as `[traducao-faltando] ...`:
   `grep traducao-faltando /tmp/php_PORTA.log | sort -u`. The goal is **zero** missing on the pages you touched.
   Also look for leftover Portuguese words in the English HTML (você, não, está, ção, ões...).

## Adding a new language

Copy `game/lang/en/` to `game/lang/xx/`, translate the values, add `"xx" => "Name"` to `$idiomasdisponiveis` in
`lib.php`, and optionally create translated images in `images/xx/`, `layoutnovo/buttons/xx/` etc.
Details in [ARCHITECTURE.md](ARCHITECTURE.md#adding-a-new-language).

## When submitting a contribution

Describe the files changed, the translation files created/edited and how many keys, and what was left
untranslated and why.
