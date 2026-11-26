# Doujutsu (técnicas oculares)

Cada jogador pode ter **um** olho. O olho é despertado num treino longo, ativado pelo ícone da coluna da direita,
gasta Chakra com o tempo e dá bônus **temporários** (só enquanto ativo, calculados na hora, nunca gravados nos atributos).

Código: `funcoesdoujutsu.php` (regras), `doujutsu.php` (tela, ativar/desativar, teleporte), `admin_doujutsu.php` (painel),
`templates/rightnav.php` (ícone), `funcoestreinos.php`/`treinoslongos.php` (treinos de despertar), `fight.php`,
`users.php` (`dueloacao()`, usado também pelo PvP) e `funcoesjutsus.php` (`classedojogador()`).
Migração: `tools/doujutsu.sql` (incluída pelo `tools/build_sql.py`; pode ser rodada de novo). Arte: `tools/olhos_doujutsu.py`.

## Os olhos (valores padrão)

Cada olho tem um estilo próprio, para o jogador escolher o que combina com ele (não é uma escada em que o último é sempre melhor).
O Rinnegan **não** é evolução do Sharingan. Ver os drops do inimigo é exclusivo do Byakugan.

| Olho | Onde despertar (treino / mestre) | Requisitos | Sessões | Custo | Estilo e destaques enquanto ativo |
|---|---|---|---|---|---|
| Olho do Falcão (`falcao`) | Vila da Areia, Arqueira Tsubame | Level 12, Genin | 6 × 240 min | 1 CH / 4 s | +90 Precisão, 5% crítico, ignora 8% da defesa |
| Sharingan 1-4 (`sharingan1..4`) | Vila da Folha, Mestre Kagerou | Level 20, Genin | 10 × 480 min | 1-2 CH / 3-4 s | Agi +80/+130/+190/+260, Int +40/+70/+100/+140, esquiva 4/7/10/14%; estágio 4: +8% ataque e **15% de chance de refletir o golpe do inimigo** (o inimigo leva o dano, nunca abaixo de 1 HP) |
| Byakugan | Vila da Névoa, Anciã Shiori | Level 30, Chuunin | 12 × 600 min | 1 CH / 3 s | +120 Precisão, ignora 25% da defesa, +15 Chance de Drop, **vê os drops do inimigo** |
| Olho Celeste (`celeste`) | Vilarejo Céu e Terra, Monge Getsuei | Level 45, Jounin | 15 × 720 min | 2 CH / 3 s | +18% defesa, +120 Sorte, +10 Chance de Drop, **mapa geral sem neblina** |
| Rinnegan | Esconderijo Akatsuki (vila secreta), Eremita Rikuu | Level 60, Jounin | 20 × 900 min | **8 CH / 2 s** | +140 Int, +6% ataque, +6% defesa, **teleporte** e **fuga garantida sem perdas**; gasta Chakra muito rápido |

O custo de Chakra da tabela é o do level 1 e cresce com o level do jogador (x2 no level 50, x3 no level 100).

O **Senjutsu** (modo sábio, `senjutsu.php`) aparece no fim da lista da tela do Doujutsu só para consulta: não é um olho,
gasta NP e dá o bônus da especialização, que cresce com o nível (60% no level 1, 90% no 50 e 120% no 100 dos valores da
tabela `classes`; sem especialização, de +12% a +24% de ataque e defesa).
A tela também mostra quanto tempo cada olho fica aberto com o Chakra cheio e, para o olho ativo, quanto tempo ainda resta.

Tudo isso (bônus, custo, intervalo) é editável em **Painel do Administrador → Doujutsu** (`admin_doujutsu.php`);
vila, mestre, level, graduação, sessões e intervalo dos treinos ficam em **Treinos longos** (`admin.php?do=treinos`).

## Regras

- **Um olho só** (`users.doujutsu`, `doujutsu_estagio`). Concluir o treino de outro olho **substitui** o atual (e o fecha).
  Os treinos de doujutsu (`olho_*` e `sharingan_evo`) saem do Painel de Treinos ao serem concluídos (`treinorepetivel()`),
  então podem ser refeitos para voltar a um olho antigo. Não é possível começar o treino do olho que já se tem.
- **Ativar/desativar**: ícone embaixo do avatar (apagado = desativado) ou a tela `doujutsu.php`. Ativar exige Chakra ≥ custo.
  Aberto, gasta `custo_ch` a cada `intervalo` segundos mesmo sem atualizar a página (`doujutsu_timer` = momento até onde já
  foi cobrado; `doujutsuestado()` calcula sem gravar, `doujutsucobrar()` grava com `AND doujutsu_timer=<lido>`, então páginas
  simultâneas cobram uma vez só). Com o Chakra no fim, o olho fecha sozinho e os bônus somem na mesma hora (também para o
  adversário de um duelo/PvP, que lê o estado calculado).
- **Senjutsu × doujutsu**: **não** ficam ativos ao mesmo tempo. A ativação do olho tem `AND senjutsuhtml<>'senjutsu'` e a do
  Senjutsu (`senjutsu.php?do=usar`) tem `AND doujutsu_ativo='0'` na própria UPDATE. Na coluna da direita aparecem os dois
  ícones (Senjutsu em cima, olho embaixo, com o link "Doujutsu" para a tela).
- **Bônus** (`doujutsumods()`): entram em `classedojogador()` — ataque/defesa em % (via `classeataquedefesa()`), crítico, e
  as chaves `dj_*` lidas por `fight.php` (inteligência no sono do inimigo, agilidade e esquiva na esquiva, precisão e
  `ignora_defesa` no ataque, sorte e chance de drop no drop) e por `dueloacao()` em `users.php` (agilidade/esquiva do alvo,
  precisão e `ignora_defesa` do atacante; vale para duelos e PvP). `ver_inimigo`: a tela de luta mostra os drops possíveis do inimigo.

## Evolução do Sharingan (estágios 1 → 4), qualquer caminho

1. **Abates**: vitória contra inimigo com level ≥ level do jogador − `control.dj_abatemargem` (5), com o Sharingan ativo,
   soma `doujutsu_abates`; metas em `control.dj_abates` ("40,120,300" para os estágios 2, 3 e 4).
2. **Vitórias em duelos** desde o estágio atual (`duelos_vencidos − doujutsu_vitbase`); metas em `control.dj_vitorias` ("5,15,40").
   Conferido a cada página (coluna da direita). 0 numa meta desliga o caminho para aquele estágio.
3. **Treino "Evolução do Sharingan"** (Vila da Folha, level 25, 14 × 720 min): +1 estágio ao concluir; refeito a cada estágio.

A subida é `UPDATE ... WHERE doujutsu='sharingan' AND doujutsu_estagio=<lido>` (uma subida só por vez) e zera os contadores.

## Teleporte do Rinnegan (`doujutsu.php?do=teleporte`, POST com token anti-CSRF)

Leva a qualquer coordenada dentro de `±control.gamesize`. Distância = maior entre |Δlat| e |Δlong|; `d10 = ⌈dist/10⌉`.
Custo: TP = `dj_tele_tpbase` + d10 × `dj_tele_tp10` (20 + 4), CH = `dj_tele_chbase` + d10 × `dj_tele_ch10` (30 + 5),
HP = (`dj_tele_hpbase` + d10 × `dj_tele_hp10`)% do HP máximo (5% + 1%; precisa sobrar 1 HP); `dj_tele_max` limita a distância (0 = sem limite).
Recusado: Rinnegan fechado, em luta, em duelo, **caveira exposta** (`bloqueiovila()`, para não servir de fuga no PvP),
fora do mapa, mesma casa e **casas de vila** (o Rinnegan não abre portais dentro de vilas: assim não contorna as regras de
entrada em vilas nem as vilas secretas; para entrar, teleporte para a casa vizinha e ande). O jogador é travado
(`SELECT ... FOR UPDATE`) e a UPDATE confere de novo TP/CH/HP, luta e olho ativo: cliques simultâneos fazem um teleporte só.

## Banco de dados

- `naruto_doujutsu` (uma linha por olho/estágio): `chave`, `estagio`, `nome`, `imagem` (`images/olhos/<imagem>.jpg`), `ordem`,
  `custo_ch`, `intervalo`, `agilidade`, `inteligencia`, `sorte`, `precisao`, `esquiva`, `critico`, `ataque`, `defesa`,
  `ignora_defesa`, `droprate`, `ver_inimigo`, `teleporte`.
- `naruto_users`: `doujutsu`, `doujutsu_estagio`, `doujutsu_ativo`, `doujutsu_timer`, `doujutsu_abates`, `doujutsu_vitbase`.
- `naruto_control`: `dj_abates`, `dj_abatemargem`, `dj_vitorias`, `dj_tele_tpbase`, `dj_tele_tp10`, `dj_tele_chbase`,
  `dj_tele_ch10`, `dj_tele_hpbase`, `dj_tele_hp10`, `dj_tele_max`.
- `naruto_treinos` ids 7-12: `olho_falcao`, `olho_sharingan`, `sharingan_evo` (`requer='sharingan'`), `olho_byakugan`,
  `olho_celeste`, `olho_rinnegan` (sem pré-requisito de olho). Ícones `images/24/treino_<chave>.gif`.

## Textos e testes

Português no código com `t()`/`tf()`, inglês em `lang/en/doujutsu.php`. Ajuda: seção "Doujutsu" em `help.php` (valores atuais do banco).
`python3 tests/doujutsu_teste.py http://127.0.0.1:8080 /caminho/do/jogo` (49 verificações): aquisição, ativação/gasto/fechar
sozinho e corrida, exclusão com o Senjutsu, efeitos na luta, os três caminhos de evolução, troca de olho, teleporte (custos,
recusas, caveira, corrida) e telas pt/en.
