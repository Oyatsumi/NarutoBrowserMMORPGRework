# Como traduzir o jogo (guia para colaboradores)

O jogo tem dois idiomas: português (`pt`, padrão) e inglês (`en`). O código **continua em português**:
**o próprio texto em português é a chave da tradução** (estilo gettext). A infraestrutura está no início do
`game/lib.php` e é explicada em [ARQUITETURA.md](ARQUITETURA.md#idiomas-i18n).

## Ferramentas

- `t("Texto em português")` → devolve o texto no idioma do jogador (ou o próprio português se não houver tradução).
- `tf("Você ganhou %s de experiência e %s Ryou.", $exp, $gold)` → igual, com valores no lugar de `%s` (vsprintf).
  Em chaves usadas com `tf()`, um `%` literal precisa ser escrito `%%` (ex.: `tf("Bônus de %s%%", $x)`).
- `idioma()` → `"pt"` ou `"en"`. `seletoridioma()` → links "Português | English".
- Dentro de heredoc use variáveis-função: no início da função declare `$t = "t"; $tf = "tf";` e escreva
  `{$t("Texto")}` ou `{$tf("Você tem %s Ryou", $gold)}`.
- Traduções: arquivos `game/lang/en/<area>.php` que retornam um array `"chave em português" => "English text"`.
  Prefira editar o arquivo da área que você está traduzindo. Formato:
  ```php
  <?php // lang/en/nucleo.php :: traduções em inglês das telas da vila, mapa, lojas e viagens.
  return array(
      "Você chegou em seu destino." => "You have arrived at your destination.",
      "Você ganhou %s de experiência." => "You gained %s experience.",
  );
  ```
  A chave tem que ser EXATAMENTE igual ao texto passado ao `t()`/`tf()` (mesmos acentos, espaços, pontuação e HTML).

## Regras

1. **Tudo que o jogador vê** deve passar por `t()`/`tf()`: textos das páginas, títulos (2º parâmetro do `display()`),
   botões (`value="..."`), `title=`/`alt=` visíveis, mensagens de erro, textos de `header("Location: ...?conteudo=...")`
   (traduza ANTES de colocar na URL: `rawurlencode(t("..."))`), textos de e-mail, tooltips (`explicdrop`) e textos
   gerados em JavaScript inline.
2. **Não traduza** valores internos que o código compara ou grava: `currentaction` (`In Town`, `Exploring`,
   `Fighting`), `None`, `X`, nomes de campos/colunas, rotas `do=`, valores de `<option value>`/`name=`, chaves
   de arrays, nomes de arquivos de imagem. Só o texto exibido.
3. **Frases com variáveis**: nunca concatene pedaços traduzidos separadamente se a ordem das palavras muda em outro
   idioma. Use uma frase inteira com `%s`: `tf("O jogador %s está na coordenada %s.", $nome, $coord)`.
   Pode manter HTML dentro da chave (`"Você <b>morreu</b>."`), mas prefira chaves curtas e estáveis.
4. **Nomes vindos do banco** (inimigos, itens, drops, jutsus, vilas, kages, graduações como "Genin"): exiba com
   `t($row["name"])`. As traduções desses nomes ficam em arquivos próprios (`lang/en/conteudo_*.php`).
   Para comparar/gravar continue usando o valor original (em português) — só a exibição é traduzida.
5. **Mensagens gravadas no banco para OUTRO jogador ler depois** (histórico, mensagens privadas, janela
   flutuante `mainmsg`): o idioma de quem vai ler não é conhecido na hora de gravar. Grave no formato neutro
   `i18n:Chave em português com %s|valor1|valor2` usando `i18n("Chave com %s", $valor)` (os valores não podem
   conter `|`) e exiba com `textoi18n($texto)`: se o texto começar com `i18n:` ela traduz a chave e aplica os
   valores; senão devolve o texto como está (mensagens antigas).
6. **Inglês**: natural, de jogo (não tradução literal). Termos do universo Naruto ficam como no anime em inglês:
   Ryou, Chakra (CH), jutsu, Senjutsu, Genin/Chuunin/Jounin, Kage, Hokage, Akatsuki, Byakugan etc.
   "Vila da Folha" → "Leaf Village", "Vila da Névoa" → "Mist Village", "Vila da Areia" → "Sand Village",
   "Vila da Pedra" → "Stone Village", "Vila da Nuvem" → "Cloud Village", "Montanha Myoboku" → "Mount Myoboku".
   HP, CH (chakra), TP (travel points), NP (natural points), EP (element points) mantêm as siglas.
   Evite aspas retas simples em inglês (o `t()` já troca `'` entre letras por `’`).
7. Não mude regras do jogo, layout, nomes de arquivos/rotas. Mantenha `php -l` limpo.
8. **Teste nos dois idiomas**: o idioma pode ser forçado na URL (`&idioma=en` / `&idioma=pt`, grava cookie)
   ou com o cookie `idioma=en`. Use `tests/fetch.py` e os screenshots (veja [TESTES.md](TESTES.md)). Com
   `"debug" => true`, cada texto sem tradução aparece no log do servidor PHP como `[traducao-faltando] ...`:
   `grep traducao-faltando /tmp/php_PORTA.log | sort -u`. O objetivo é **zero** faltando nas páginas que você mexeu.
   Também procure palavras em português sobrando no HTML em inglês (você, não, está, ção, ões...).

## Ao enviar uma contribuição

Descreva os arquivos alterados, os arquivos de tradução criados/editados e quantas chaves, e o que ficou sem
traduzir e por quê.

## Idioma automático pelo IP
No primeiro acesso, enquanto o jogador não escolheu um idioma no seletor, o jogo olha o IP: **Brasil = português, fora do Brasil = inglês**
(`lib.php :: ipdobrasil()`, faixas em `game/dados/ip_br4.bin` e `ip_br6.bin`, busca binária no arquivo, sem consulta externa).
O resultado fica no cookie `idiomaip` (30 dias). A escolha manual (cookie `idioma`, 1 ano) sempre tem prioridade. IP local/privado ou sem dados = inglês.
Liga/desliga no painel: Configurações gerais > "Idioma pelo IP" (`control.idiomaporip`, padrão ligado).
Para atualizar as faixas: `npm pack @ip-location-db/geo-whois-asn-country` (CC0; dados do NRO, CC BY 4.0 — atribuição: nro.net),
extraia e rode `python3 tools/gerar_ip_brasil.py pasta/package game/dados`.
