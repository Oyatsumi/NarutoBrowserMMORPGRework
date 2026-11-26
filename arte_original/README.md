# Arte original (fontes)

Arte própria do jogo em alta resolução, de onde saem as imagens pequenas usadas em `game/`.
Os PSDs do layout antigo ficam em [`../psds/`](../psds/README.md).

| Pasta | Conteúdo | Usado em |
|---|---|---|
| `monstros/cartas/` | Cartas finais dos inimigos (aquarela, 295x145). | `game/layoutnovo/monstros/ID.jpg` |
| `monstros/retratos_psd/` | Retratos dos inimigos em camadas, antes do estilo das cartas. | `tools/montar_cartas.py`, `tools/estilizar_cartas.py` |
| `armas/folha_armas_1.png` | Folha 4x4 (Gemini) das armas com defesa na outra mão, ids 85-90 (linhas 1, 2 e 4). | `game/layoutnovo/equipamentos/85.gif` a `90d.gif` |
| `armas/folha_armas_2.png` | Folha 4x4 (Gemini) das armas 91-95 (linhas 1 a 3; a última linha não foi usada). | `game/layoutnovo/equipamentos/91.gif` a `95d.gif`, `drops/459.gif` e `460.gif` |
| `icones_vilas/` | Emblemas das vilas. | `selovila()` (lib.php) |

Recorte: cada célula vira um ícone 34x34 (fundo escuro próprio). A arma vai na mão da esquerda apontando para
a esquerda (`ID.gif`) e o item de defesa na da direita (`IDd.gif`), espelhado quando não é simétrico.
