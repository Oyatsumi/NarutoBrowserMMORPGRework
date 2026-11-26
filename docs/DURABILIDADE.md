# Durabilidade dos itens

O jogo sempre teve durabilidade (campo `users.durabilidade` = `"-,arma,colete,bandana,slot1,slot2,slot3"`, slot da mochila
`"nome,id,tipo,durabilidade[,qtd]"`, `"X"` = infinita), mas todo item entrava como `"X"`, então ela não fazia nada.
Agora **só os itens fortes** se desgastam, com durabilidade generosa (centenas de vitórias) e reparo barato:
é um pequeno escoadouro de Ryou no fim do jogo, não uma punição.

## Regra (tools/durabilidade.py → tools/durabilidade.sql)

Colunas novas `naruto_items.durabilidade` e `naruto_drops.durabilidade` (vitórias até quebrar; **0 = infinita**).

| Grupo | Critério | Durabilidade |
|---|---|---|
| Armas da loja | Poder de Ataque ≥ 100 | 300 + 2 × atributo |
| Coletes da loja | Poder de Defesa ≥ 110 | 300 + 2 × atributo |
| Bandanas da loja | Poder de Defesa ≥ 50 | 300 + 4 × atributo |
| Drops equipáveis | nível 45 a 99, ou itens de compensação 420-425 | 400 + 5 × nível |
| Todo o resto | itens fracos/de começo, drops < 45, drops de missão/alquimia (nível ≥ 100), consumíveis, pergaminhos, empilháveis | 0 (infinita) |

Valores arredondados para baixo em múltiplos de 50. Resultado: 29 itens de loja e 30 drops finitos, entre 500 e 800 vitórias.
Exemplos: Espadas de Rai 500, Katana Dupla 650, Arma Ninja Sagrada 800, Proteção do Kage 750, Chapéu do Hokage 700,
Amuleto do Berserker Rubro 600, Jóia da Determinação 650, Coração do Dragão Ancestral 800.
**Variantes raras** (✦) usam a durabilidade do item base. Para refazer os valores: `python3 tools/durabilidade.py`
(lê o banco local e regrava o .sql) ou edite item a item no painel.

## Onde o item recebe a durabilidade

`durabilidadenova(tipo, id)` (funcoesinclusas.php) devolve a máxima ou `"X"`. É usada quando um item **entra** no jogo:
compra na loja (towns.php :: buy3), drop no slot ou na mochila (fight.php :: drop), Alquimia (alquimiatexto), recompensas
de missão (mx_itemcompleto), Invocação de Suprimentos (heal.php) e "dar drop" do painel. Troca, banco, mochila, PvP e
equipar/desequipar **levam a durabilidade atual junto**.

**Itens que os jogadores já tinham não mudam** (continuam `"X"`): nada quebra retroativamente. O mesmo vale ao mudar o valor
no painel: só os itens novos saem com o valor novo (o reparo usa a máxima atual).

## Desgaste, aviso e quebra

- Cada vitória contra inimigos gasta 1 de cada item equipado finito (fight.php :: victory). Duelos e PvP não gastam.
- Tooltip: *Durabilidade: N/M* (vermelho com ≤ 10%). Loja: *Durabilidade: 800 vitórias*.
- Aviso de desgaste com ≤ 10% da máxima (mínimo 5): na coluna da direita (bloco `avisodurabilidade`) e no fim da luta.
- Em 0 o item quebra: `desequiparitem($h, true)` (desequipar.php) retira os bônus do item (refino, specials,
  variante rara; drops por `bonusdodrop` com o nome com variante), limita os pontos atuais e apaga o espaço. Testado.

## Reparo (towns.php :: reparar, `index.php?do=reparar`)

Na loja de qualquer vila (aba *Reparar equipamentos*). Repara de uma vez os equipados e os da mochila até a máxima atual.

    custo do item = ceil(valor × control.reparo_pct / 100 × falta / máxima), mínimo 1 Ryou
    valor = preço da loja (arma/colete/bandana) ou 150 × nível do drop; variante rara 3×

Com o padrão de 30%: Arma Ninja Sagrada (50.000) gasta 400 de 800 → 7.500 Ryou (≈ 19 Ryou por vitória; um inimigo de
nível 70 dá ≈ 3.400 Ryou). Coração do Dragão Ancestral (nível 80 → 12.000) reparo completo 3.600; variante 10.800.

Atômico: transação com o jogador `FOR UPDATE`, o total mostrado vai no formulário (se mudou, recusa), CSRF, e um `UPDATE`
condicional (`durabilidade`, espaços da mochila e `gold>=total` como lidos). Pedidos simultâneos cobram uma vez só.

## Painel do administrador

- Configurações principais: **Durabilidade** ativa/desligada (`control.durabilidade_ativa`, padrão 1) e preço do reparo
  (`control.reparo_pct`, padrão 30%). Desligada: as vitórias não gastam e não há avisos; os itens novos continuam saindo
  com a durabilidade (prontos para quando ligar de novo) e o reparo continua disponível.
- Editores de item e drop: campo **Durabilidade** (0 = infinita).

## Testes

`tests/durabilidade_teste.py URL PASTA` — instalação nova, compra, vitórias, aviso, chave, drops (slot com variante e mochila),
quebra com atributos de volta, itens antigos, reparo (custo, recusas, CSRF, corrida de 6 pedidos, inglês), missão e funções.
