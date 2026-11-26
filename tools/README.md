# Ferramentas usadas na atualização 4.0

| Script | O que faz |
|---|---|
| `restore_accents.py` | Recupera acentos perdidos (U+FFFD) comparando cada arquivo com a versão antiga intacta. |
| `fix_qmarks.py` | Corrige palavras em que o acento virou `?` (dicionário revisado). |
| `build_sql.py` | Gera `sql/naruto_rpg.sql` a partir do dump original: novo prefixo, utf8mb4/InnoDB, acentos, sem dados de jogadores, inimigos novos. |
| `gerar_neblina.py` | Gera a textura da neblina de guerra do mapa global (`images/neblina_mapa.png`) e o marcador de vila (`images/vila_marcador.png`). |
| `ajustar_mapa_vilas.py` | Desenha terra/gelo embaixo das vilas que ficavam no mar em `images/mapa_grande.png` (Sabedoria, Castelo Real, Vila da Nuvem). |
| `gerar_retratos.py` | Desenha os retratos em silhueta das cartas que não existiam. |
| `montar_cartas.py` | Monta a carta (295x145) inserindo um retrato como camada no `psds/cartas/monstros.psd`. |
| `organizar_psds.py` | Organiza os PSDs por tema e gera as miniaturas. |
| `estilizar_cartas.py` | Converte retratos `<id>.png` (arte própria) para o estilo sépia das cartas e monta as cartas no `monstros.psd`. |
| `extrair_chaves.php` | Lista as frases passadas a `t()`/`tf()`/`i18n()` de um ou mais arquivos (para conferir os arquivos de tradução). |
| `imagens_ingles.py` | Gera as versões em inglês das imagens com texto (`<pasta>/en/`). |
| `doujutsu.sql` | Migração do doujutsu: tabela `naruto_doujutsu`, colunas `users.doujutsu*` e `control.dj_*`, treinos 7-12 (docs/DOUJUTSU.md). |
| `olhos_doujutsu.py` | Desenha os olhos novos `images/olhos/falcao.jpg` e `celeste.jpg` e os ícones `images/24/treino_olho_*.gif` dos mestres. |

Dependências (só para estas ferramentas, não para o jogo): Python 3, Pillow e `psd-tools`.
