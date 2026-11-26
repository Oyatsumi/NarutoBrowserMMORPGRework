# Treinos longos (Painel de Treinos)

Além do **Senjutsu** (Montanha Myoboku, `senjutsu.php`) e do **Jutsu de Busca** (Vila da Areia, `jutsudebusca.php`),
o Painel de Treinos (`treinamentoequests.php?do=treinamento`) tem seis treinos longos ensinados por mestres nas vilas.

## Como funcionam

1. Na vila do treino, o ícone do mestre aparece no quadro **Outros** (`cidadesconteudo.php`, ícones `images/24/treino_CHAVE.gif`,
   gerados por `tools/icones_treinos.py`). O ícone leva a `treinoslongos.php?do=falar&t=CHAVE`: a fala do mestre com recompensa,
   requisitos e o link "começar agora mesmo".
2. Começar (`do=treinar&t=CHAVE&inicio=1`) coloca o treino em `users.treinamento` (`Nome,0,total,data,hora,0;`) sem dar bônus.
3. Cada sessão (`do=treinar&t=CHAVE`, também pelo botão do painel) exige: estar na vila do treino, cumprir os requisitos
   (level, graduação, Senjutsu) e ter passado o intervalo desde a última sessão. O total de sessões fica gravado no início:
   se o admin mudar depois, quem já começou mantém o seu.
4. A sessão e a recompensa são gravadas numa única `UPDATE ... WHERE id=... AND treinamento='<lido>'` (como em `senjutsu.php`):
   dois cliques ao mesmo tempo contam uma sessão só e dão o bônus uma vez só.
5. `dadosdotreino()` mostra no painel a recompensa, o que já foi obtido, os requisitos, a vila, o mestre e as sessões.

## Os seis treinos (valores padrão)

| Treino | Vila (mestre) | Requisitos | Sessões / intervalo | Por sessão | Ao concluir |
|---|---|---|---|---|---|
| Domínio de Elemento (`elemento`) | Vila da Sabedoria (Sábia Kotone) | Level 15, Genin | 10 / 360 min | +1 EP máximo (limite total 10) | — |
| Controle de Chakra (`chakra`) | Vila da Folha (Mestre Hiroshi) | Level 10, Genin | 8 / 240 min | +2 Chakra máximo | jutsus custam 6% menos Chakra |
| Corpo de Ferro (`ferro`) | Vila da Pedra (Mestre Ganseki) | Level 20, Genin | 10 / 480 min | +6 HP máximo | +4 Poder de Defesa (+4 a mais para Taijutsu) |
| Caminhada Ninja (`caminhada`) | Vila da Areia (Guia Hayato) | Level 8 | 8 / 180 min | +3 TP máximo | +1 TP por intervalo de descanso (cidade e mapa) |
| Meditação Natural (`meditacao`) | Montanha Myoboku (Shima) | Level 30, Genin, Senjutsu concluído | 10 / 720 min | +5 NP máximo | Senjutsu ativo gasta 1 NP a cada 4 s (em vez de 3) |
| Treino de Jutsu Raro (`jutsuraro`) | Vila da Nuvem (Mestre Raitō) | Level 35, Chuunin | 12 / 600 min | — | aprende o jutsu 100, **Lança do Relâmpago Negro** |

Totais com os valores padrão: +10 EP, +16 CH e -6% de Chakra, +60 HP e +4/+8 de defesa, +24 TP e +1 TP/descanso,
+50 NP e Senjutsu 25% mais econômico, e um jutsu de raio exclusivo (48 CH, poder 125, 60% de chance de baixar a defesa
do inimigo em 20% por 2 turnos, recarga 3, exige Chuunin; `exclusivo_missao=1`, nunca por nível nem pergaminho).

## Onde cada recompensa vale

- **Máximos (HP/CH/TP/EP/NP) e defesa**: somados direto em `maxhp`, `maxmp`, `maxtp`, `maxep`, `maxnp` e `defensepower`
  (e nos valores atuais). O level up (`fight.php`), a escolha da especialização (`especializacao.php`), a graduação e os
  itens só somam diferenças a esses campos, nunca recalculam do zero: o bônus não se perde nem é contado duas vezes.
  O quanto veio de treinos fica nas colunas `users.treino_hp`, `treino_mp`, `treino_tp`, `treino_ep`, `treino_np` e
  `treino_defesa` (para o painel e para auditoria). Se o admin editar os máximos de um jogador na mão
  (`admin.php?do=edituser`), o valor digitado já é o total.
- **Controle de Chakra**: `users.treino_chakra` (%) é somado à economia em `custochakra()` (`funcoesinclusas.php`), que é usado
  em todos os lugares onde jutsu cobra Chakra: batalha (`fight.php`), cura/jutsus de menu (`heal.php`, `explore.php`),
  duelo (`users.php`) e PvP (`pvp.php`). Limite de 50% no treino e 75% somando tudo.
- **Caminhada Ninja**: `users.treino_tpregen` é somado ao TP de cada intervalo do descanso (`encherhp.php`), também no mapa.
- **Meditação Natural**: `users.treino_sjlento` segundos a mais por NP em `senjutsuestado()`/`senjutsu()`.
- **Treino de Jutsu Raro**: o id `treinos.jutsu_id` entra em `users.spells` só na última sessão. A ajuda de jutsus
  (`help_spells.php`) mostra "Obtido no treino ...".

## Banco de dados e administração

- Tabela `naruto_treinos` (uma linha por treino): `chave`, `nome` (é o nome gravado em `users.treinamento`; não mude),
  `vila_id`, `mestre`, `avatar`, `nivel_min`, `graduacao_min`, `requer` (`senjutsu`), `sessoes`, `intervalo` (minutos),
  `bonus_sessao`, `bonus_final`, `bonus_extra`, `jutsu_id`, `ativo`.
- Colunas novas em `naruto_users`: `treino_hp`, `treino_mp`, `treino_tp`, `treino_ep`, `treino_np`, `treino_defesa`,
  `treino_chakra`, `treino_tpregen`, `treino_sjlento`.
- Migração: `tools/treinos_novos.sql` (incluída pelo `tools/build_sql.py`; pode ser rodada de novo num banco já instalado).
- Painel do Administrador → **Treinos longos** (`admin.php?do=treinos`): vila, mestre, level, graduação, sessões,
  intervalo, bônus, jutsu e ativo de cada treino. Bônus já dados aos jogadores não mudam.
- Textos: português no código com `t()`/`tf()`, inglês em `lang/en/treinos.php`. Ajuda: bloco "Treinos Longos" em `help.php`.

## Testes

`python3 tests/treinos_teste.py http://127.0.0.1:8080 /caminho/do/jogo` testa por HTTP, para cada treino: requisitos (level,
graduação, Senjutsu, vila errada), fala e ícone do mestre, aquisição, primeira sessão e bônus, espera obrigatória,
8 cliques simultâneos (uma sessão e um bônus só), conclusão e recompensa final, clique depois de concluído, efeitos
(custo de Chakra, TP no descanso, gasto de NP do Senjutsu, jutsu só depois de concluir) e painel/ajuda em pt e en.

## Treinos de doujutsu

Os treinos 7-12 (`olho_falcao`, `olho_sharingan`, `sharingan_evo`, `olho_byakugan`, `olho_celeste`, `olho_rinnegan`) despertam
ou evoluem um olho e saem da lista ao concluir, para poderem ser refeitos. Regras em `docs/DOUJUTSU.md`;
`tests/treinos_teste.py` pula esses treinos (testados em `tests/doujutsu_teste.py`).
