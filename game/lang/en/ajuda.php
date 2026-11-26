<?php // lang/en/ajuda.php :: tradução em inglês dos blocos de texto da página de ajuda (help.php).
// Cada chave é um bloco inteiro da ajuda em português (com %s para os valores do banco e %% para um % literal).
return array(

'<a name="top"></a>
<table width="100%%"><tr><td width="100%%" align="center"><center><img src="images/ajuda_titulo.gif" alt="Ajuda" /></center></td></tr></table>
<center>[ <a href="index.php">Voltar ao Jogo</a> ]</center>

<h3>Índice</h3>
<ul>
<li /><a href="#intro">Introdução</a>
<li /><a href="#personagem">Criando seu Personagem</a>
<li /><a href="#status">Pontos de Status</a>
<li /><a href="#pontos">TP, NP e EP</a>
<li /><a href="#especiais">Jutsus Especiais, Teleportes e Itens de Compensação</a>
<li /><a href="#mapa">O Mapa e a Exploração</a>
<li /><a href="#batalhas">Batalhas</a>
<li /><a href="#elementos">Elementos</a>
<li /><a href="#vilas">As Vilas e seus Serviços</a>
<li /><a href="#trocavila">Trocar de Vila Natal</a>
<li /><a href="#trocadif">Trocar de Dificuldade</a>
<li /><a href="#treinos">Missões, Treinamentos e Graduações</a>
<li /><a href="#itens">Itens, Drops e Mochila</a>
<li /><a href="#alquimia">Alquimia</a>
<li /><a href="#atributos">Distribuição de Atributos</a>
<li /><a href="#trocas">Trocas, Doações e Duelos</a>
<li /><a href="#pvp">PvP no Mundo e Caveiras</a>
<li /><a href="#comunicacao">Chat, Fórum e Mensagens Privadas</a>
<li /><a href="#regras">Regras de Conduta</a>
<li /><a href="#spoilers">Spoilers: Itens, Inimigos, Jutsus e Leveis</a>
<li /><a href="#creditos">Créditos</a>
</ul>

<h3><a name="intro"></a>Introdução</h3>
Bem-vindo ao <b>%s</b>! Aqui você cria o seu próprio ninja e parte em uma jornada pelo mundo de Naruto: explora o mapa,
enfrenta inimigos, aprende jutsus, completa missões nas vilas e se gradua de Estudante da Academia até Jounin.
Tudo acontece no navegador, com um mapa compartilhado com os outros jogadores, com quem você pode conversar, trocar itens e duelar.<br /><br />
Esta página explica como o jogo funciona. Se ainda tiver dúvidas, pergunte no fórum ou no chat — os jogadores mais experientes e a equipe do jogo costumam ajudar.<br /><br />'
=> '<a name="top"></a>
<table width="100%%"><tr><td width="100%%" align="center"><center><img src="images/ajuda_titulo.gif" alt="Help" /></center></td></tr></table>
<center>[ <a href="index.php">Back to the Game</a> ]</center>

<h3>Contents</h3>
<ul>
<li /><a href="#intro">Introduction</a>
<li /><a href="#personagem">Creating your Character</a>
<li /><a href="#status">Status Points</a>
<li /><a href="#pontos">TP, NP and EP</a>
<li /><a href="#especiais">Special Jutsus, Teleports and Trade-off Items</a>
<li /><a href="#mapa">The Map and Exploring</a>
<li /><a href="#batalhas">Battles</a>
<li /><a href="#elementos">Elements</a>
<li /><a href="#vilas">Villages and their Services</a>
<li /><a href="#trocavila">Changing your Home Village</a>
<li /><a href="#trocadif">Changing your Difficulty</a>
<li /><a href="#treinos">Missions, Training and Graduations</a>
<li /><a href="#itens">Items, Drops and Backpack</a>
<li /><a href="#alquimia">Alchemy</a>
<li /><a href="#atributos">Distributing Attributes</a>
<li /><a href="#trocas">Trades, Donations and Duels</a>
<li /><a href="#pvp">Open-world PvP and Skulls</a>
<li /><a href="#comunicacao">Chat, Forum and Private Messages</a>
<li /><a href="#regras">Rules of Conduct</a>
<li /><a href="#spoilers">Spoilers: Items, Enemies, Jutsus and Levels</a>
<li /><a href="#creditos">Credits</a>
</ul>

<h3><a name="intro"></a>Introduction</h3>
Welcome to <b>%s</b>! Here you create your own ninja and set out on a journey through the world of Naruto: explore the map,
fight enemies, learn jutsus, complete missions in the villages and climb the ranks from Academy Student all the way to Jounin.
Everything happens in your browser, on a map you share with other players, whom you can chat with, trade items with and duel.<br /><br />
This page explains how the game works. If you still have questions, ask on the forum or in the chat — experienced players and the game staff are usually happy to help.<br /><br />',

'<h3><a name="personagem"></a>Criando seu Personagem</h3>
Ao criar a conta você escolhe o nome do personagem (apenas letras, números, <i>_</i> e <i>-</i>, sem espaços) e o
<b>nível de dificuldade</b>. A vila natal não é escolhida: todo ninja começa na vila inicial do jogo (normalmente a Vila da Folha, no centro do mapa, onde fica a área de iniciantes).
As duas coisas podem ser trocadas mais tarde, dentro do jogo, por missões: veja <a href="#trocavila">Trocar de Vila Natal</a> e <a href="#trocadif">Trocar de Dificuldade</a>.<br /><br />
<b>Especializações</b><br />
Todo ninja começa como estudante, <b>sem especialização</b>. A partir do level %4$s, em qualquer vila, o botão <b>Escolher Especialização</b> abre a página das três especializações. <b>A escolha é definitiva</b>: ao confirmar, seu HP, Chakra, Força e Destreza são ajustados para a especialização e você aprende os jutsus dela até o seu level atual. Todas usam a mesma curva de experiência.
<ul>
<li /><b>%1$s</b>: o mestre dos elementos. Jutsus com elemento causam mais dano e a vantagem de elemento pesa mais; é quem mais ganha Chakra. Fraqueza: recebe um pouco mais de dano físico.
<li /><b>%2$s</b>: o especialista do corpo a corpo. Ataques físicos mais fortes e mais críticos, técnicas sem elemento mais baratas e o maior HP. Fraqueza: os efeitos de genjutsu de outros ninjas pegam mais forte nele.
<li /><b>%3$s</b>: o mestre do controle. Atordoamentos, venenos, queimaduras e enfraquecimentos pegam mais, duram mais e são mais fortes; dreno de Chakra maior e resistência aos efeitos dos inimigos. Fraqueza: menos dano direto com jutsus.
</ul>
Cada especialização também tem jutsus exclusivos e um bônus próprio quando o <b>Senjutsu</b> está ativo. Os números exatos estão na página de escolha.<br /><br />
Comparação entre as especializações (somando o que se ganha até o level %5$s, sem contar itens e atributos):<br /><br />
%6$s
<br />
<b>Níveis de Dificuldade</b><br />
A dificuldade multiplica o HP e o dano dos inimigos — mas também a experiência e os Ryou que eles dão quando são derrotados. Quanto mais difícil, mais lenta e mais perigosa é a caçada, e maior a recompensa:
<ul>
%7$s</ul>'
=> '<h3><a name="personagem"></a>Creating your Character</h3>
When you create your account you choose your character’s name (letters, numbers, <i>_</i> and <i>-</i> only, no spaces) and your
<b>difficulty level</b>. The home village is not chosen: every ninja starts in the game’s starting village (usually the Leaf Village, at the center of the map, where the beginner area is).
Both can be changed later, in the game, through missions: see <a href="#trocavila">Changing your Home Village</a> and <a href="#trocadif">Changing your Difficulty</a>.<br /><br />
<b>Specializations</b><br />
Every ninja starts as a student <b>without a specialization</b>. From level %4$s on, in any village, the <b>Choose Specialization</b> button opens the page with the three specializations. <b>The choice is permanent</b>: once you confirm, your HP, Chakra, Strength and Dexterity are adjusted to the specialization and you learn its jutsus up to your current level. All of them use the same experience curve.
<ul>
<li /><b>%1$s</b>: master of the elements. Elemental jutsus deal more damage and elemental advantage hits harder; gains the most Chakra. Weakness: takes a little more physical damage.
<li /><b>%2$s</b>: close-combat specialist. Stronger physical attacks with more crits, cheaper non-elemental techniques and the highest HP. Weakness: genjutsu effects from other ninjas hit harder.
<li /><b>%3$s</b>: master of control. Stuns, poisons, burns and weakening effects land more often, last longer and hit harder; stronger Chakra drain and resistance to enemy effects. Weakness: less direct damage from jutsus.
</ul>
Each specialization also has exclusive jutsus and its own bonus while <b>Senjutsu</b> is active. The exact numbers are on the choice page.<br /><br />
How the specializations compare (adding up everything gained up to level %5$s, without items or attributes):<br /><br />
%6$s
<br />
<b>Difficulty Levels</b><br />
The difficulty multiplies the enemies’ HP and damage — but also the experience and Ryou they give when defeated. The harder it is, the slower and more dangerous the hunt, and the bigger the reward:
<ul>
%7$s</ul>',

'<h3><a name="status"></a>Pontos de Status</h3>
As barras do painel da direita mostram os cinco pontos que mudam o tempo todo durante o jogo:
<ul>
<li /><b>HP (Pontos de Vida)</b> — barra vermelha. Se chegar a zero você é derrotado. Recupere na pousada, com jutsus medicinais, descansando ou com a sorte de batalha.
<li /><b>CH (Pontos de Chakra)</b> — barra azul. É gasto para usar jutsus e o Jutsu de Busca. Recupere na pousada, descansando ou com a sorte de batalha.
<li /><b>TP (Pontos de Viagem)</b> — barra amarela. É gasto para viajar direto até uma vila pelo menu de cidades, pelos pergaminhos de Retorno e de Teleporte e pelo Passo Veloz. Recupere na pousada, descansando na cidade ou com a sorte de batalha (veja <a href="#pontos">TP, NP e EP</a>).
<li /><b>NP (Pontos Naturais)</b> — barra verde. É a energia da natureza usada pelo Senjutsu. Você ganha 1 NP a cada passo dado no mapa, ao descansar e com a sorte de batalha. O máximo aumenta 5 a cada level.
<li /><b>EP (Pontos de Elemento)</b> — barra roxa. Cada jutsu de dano com elemento gasta o EP indicado nele; sem EP ele sai como Neutro (dano normal, só gasta Chakra). Recupere na pousada, descansando na cidade ou com a sorte de batalha; o máximo cresce com o level e com itens.
</ul>
Quando o HP ou o CH ficam abaixo de 20%% do máximo, o valor aparece em vermelho no painel.<br /><br />
Além deles, seu personagem tem <b>Força</b> e <b>Destreza</b> (que crescem a cada level), o <b>Poder de Ataque</b> (Força + arma e itens) e o
<b>Poder de Defesa</b> (Destreza + colete, bandana e itens), além dos cinco atributos que você mesmo distribui (veja <a href="#atributos">Distribuição de Atributos</a>).<br /><br />'
=> '<h3><a name="status"></a>Status Points</h3>
The bars on the right panel show the five values that keep changing as you play:
<ul>
<li /><b>HP (Health Points)</b> — red bar. If it reaches zero you are defeated. Restore it at an inn, with medical jutsus, by resting or through battle luck.
<li /><b>CH (Chakra Points)</b> — blue bar. Spent on jutsus and on the Search Jutsu. Restore it at an inn, by resting or through battle luck.
<li /><b>TP (Travel Points)</b> — yellow bar. Spent to travel straight to a village from the village menu, by the Return and Teleport scrolls and by Swift Step. Restore it at an inn, by resting in a village or through battle luck (see <a href="#pontos">TP, NP and EP</a>).
<li /><b>NP (Natural Points)</b> — green bar. The energy of nature used by Senjutsu. You gain 1 NP for every step you take on the map, while resting and through battle luck. Your maximum grows by 5 every level.
<li /><b>EP (Element Points)</b> — purple bar. Every elemental damage jutsu spends the EP shown on it; without EP it goes out as Neutral (normal damage, only spends Chakra). Restore it at an inn, by resting in a village or through battle luck; the maximum grows with your level and with items.
</ul>
When your HP or CH drops below 20%% of the maximum, the value turns red on the panel.<br /><br />
Your character also has <b>Strength</b> and <b>Dexterity</b> (which grow every level), <b>Attack Power</b> (Strength + weapon and items) and
<b>Defense Power</b> (Dexterity + vest, headband and items), plus five attributes you distribute yourself (see <a href="#atributos">Distributing Attributes</a>).<br /><br />',

'<h3><a name="mapa"></a>O Mapa e a Exploração</h3>
O mundo é um grande quadrado de coordenadas, de %sS a %sN e de %sW a %sE. A Vila da Folha fica no centro, em [0N, 0E].
Use os botões <b>Norte</b>, <b>Sul</b>, <b>Leste</b> e <b>Oeste</b> do painel da esquerda para andar uma casa por vez; o mini mapa mostra os arredores e
o link <b>Ver Mapa Completo</b> abre o mapa inteiro, onde você também pode clicar em um ponto para que o personagem ande sozinho até lá.<br /><br />
A cada passo fora das vilas existe uma chance (1 em 4) de encontrar um inimigo. Quanto mais longe do centro, mais fortes eles são: o level máximo
dos inimigos de uma região é a maior distância do centro (em latitude ou longitude) dividida por 5, e aparecem inimigos de até 2 leveis abaixo disso.
Ou seja, até 4 casas da Vila da Folha só aparecem inimigos de level 1; a 100 casas de distância eles já são de level 19 a 21.<br /><br />
Ao pisar na coordenada de uma vila você entra nela e ela passa a fazer parte do seu menu de viagem, sem precisar comprar o mapa.
Enquanto explora, você também vê os outros jogadores que estão na mesma coordenada.<br /><br />
<b>Descansar</b>: fora das vilas, o botão Descansar (nos comandos do personagem) recupera 1 HP, 2 CH e 1 NP a cada 10 segundos. Itens de descanso (só caem de inimigos, como o Chá de Lótus Noturno) multiplicam tudo o que o Descansar recupera por algumas horas (no mínimo 2); use pela mochila.<br /><br />'
=> '<h3><a name="mapa"></a>The Map and Exploring</h3>
The world is one big square of coordinates, from %sS to %sN and from %sW to %sE. The Leaf Village sits in the middle, at [0N, 0E].
Use the <b>North</b>, <b>South</b>, <b>East</b> and <b>West</b> buttons on the left panel to move one square at a time; the mini map shows your surroundings and
the <b>View Full Map</b> link opens the whole map, where you can also click a spot to have your character walk there on their own.<br /><br />
Every step outside the villages has a chance (1 in 4) of running into an enemy. The farther from the center, the stronger they get: the highest enemy level
in an area is your greatest distance from the center (in latitude or longitude) divided by 5, and enemies up to 2 levels below that also show up.
In other words, within 4 squares of the Leaf Village you only meet level 1 enemies; 100 squares away they are already level 19 to 21.<br /><br />
Stepping onto a village’s coordinates takes you inside, and that village is added to your travel menu without having to buy its map.
While exploring you can also see the other players standing on the same coordinates.<br /><br />
<b>Rest</b>: outside the villages, the Rest button (in your character commands) restores 1 HP, 2 CH and 1 NP every 10 seconds. Rest items (enemy drops only, like the Night Lotus Tea) multiply everything Rest restores for a few hours (at least 2); use them from your backpack.<br /><br />',

'<h3><a name="batalhas"></a>Batalhas</h3>
Quando um inimigo aparece, a tela de luta mostra a carta dele com o level, a imunidade, o elemento, o HP, o ataque e a defesa.
Dependendo da sua Destreza, você pode atacar primeiro ou o inimigo pode tomar a iniciativa. Em cada turno você escolhe uma ação:
<ul>
<li /><b>Atacar</b>: um ataque físico. O dano depende do seu Poder de Ataque menos a defesa do inimigo. Às vezes sai um <b>hit excelente</b>, que dobra o dano (mais comum com muita Força e Determinação), e às vezes o inimigo se esquiva e não recebe dano (menos comum com Precisão).
<li /><b>Jutsu</b>: usa um dos jutsus que você aprendeu, gastando Chakra. Jutsus medicinais curam, jutsus de dano ferem o inimigo (alguns inimigos são imunes), genjutsus fazem o inimigo dormir e as técnicas de aumento de ataque ou de defesa valem até o fim da luta.
<li /><b>Ferramentas ninja</b>: kunais, shurikens, bombas e pergaminhos de armas da mochila são arremessados no inimigo (ou no oponente do duelo) e gastam o turno. Cada uma tem dano, chance de acerto (a Precisão ajuda um pouco), às vezes um elemento e um efeito (queimadura, veneno, atordoamento, sono, ataque ou defesa enfraquecidos). As mais fracas são vendidas na loja; as fortes caem de inimigos.
<li /><b>Fugir</b>: tenta escapar da luta. A chance depende da sua Destreza e da força do inimigo; se falhar, o inimigo ataca.
</ul>
Depois da sua ação é a vez do inimigo. Você pode se esquivar do golpe (mais fácil com Destreza e Agilidade) e o seu Poder de Defesa reduz o dano recebido.<br /><br />
<b>Vitória</b>: você ganha experiência e Ryou. Pode ganhar também um item (drop) — a chance aumenta com a Sorte e com itens de Chance de Drop — e,
às vezes, a <b>sorte de batalha</b> recupera um pouco de HP, CH, TP, NP ou EP. Ao juntar experiência suficiente você sobe de level: ganha HP, CH, TP, Força e
Destreza, 5 pontos de atributo para distribuir, 5 de NP máximo e, em alguns leveis, um jutsu novo.<br /><br />
<b>Sorte de batalha</b>: depois de cada vitória um sorteio escolhe a barra (NP 40%%, HP 20%%, CH 20%%, TP 10%%, EP 10%%) e recupera um valor base (HP 1-20, CH 1-10, TP 1-15, NP 1-5, EP 1-2), mais 2%% desse valor por level do inimigo (menos no EP), mais de metade até o total de uma fatia do máximo da barra (HP 5%%, CH 5%%, TP 6%%, NP 4%%, EP 5%%). Assim a sorte cresce junto com as suas barras, sem nunca passar do máximo.<br /><br />
<b>Derrota</b>: se o seu HP chegar a zero você perde <b>metade dos Ryou que carrega</b> e acorda na sua vila com um quarto do HP máximo.
Os Ryou guardados no banco não são perdidos.<br /><br />'
=> '<h3><a name="batalhas"></a>Battles</h3>
When an enemy appears, the battle screen shows its card with its level, immunity, element, HP, attack and defense.
Depending on your Dexterity, you may strike first or the enemy may seize the initiative. Each turn you choose an action:
<ul>
<li /><b>Attack</b>: a physical attack. Damage depends on your Attack Power minus the enemy’s defense. Sometimes you land an <b>excellent hit</b> that doubles the damage (more likely with high Strength and Determination), and sometimes the enemy dodges and takes no damage (less likely with Precision).
<li /><b>Jutsu</b>: uses one of the jutsus you have learned, spending Chakra. Medical jutsus heal, damage jutsus hurt the enemy (some enemies are immune), genjutsus put the enemy to sleep, and attack or defense boosts last until the end of the fight.
<li /><b>Ninja tools</b>: kunai, shuriken, bombs and weapon scrolls from your backpack are thrown at the enemy (or your duel opponent) and use up your turn. Each one has damage, a hit chance (Accuracy helps a little), sometimes an element and an effect (burn, poison, stun, sleep, weakened attack or defense). The weaker ones are sold in the shop; the strong ones drop from enemies.
<li /><b>Run</b>: tries to escape the fight. Your chance depends on your Dexterity and the enemy’s strength; if you fail, the enemy attacks.
</ul>
After your action it is the enemy’s turn. You may dodge the blow (easier with Dexterity and Agility), and your Defense Power reduces the damage you take.<br /><br />
<b>Victory</b>: you earn experience and Ryou. You may also get an item (drop) — the chance goes up with Luck and with Drop Chance items — and
sometimes <b>battle luck</b> restores a little HP, CH, TP, NP or EP. Once you have enough experience you level up: you gain HP, CH, TP, Strength and
Dexterity, 5 attribute points to distribute, 5 maximum NP and, at some levels, a new jutsu.<br /><br />
<b>Battle luck</b>: after each victory a draw picks the bar (NP 40%%, HP 20%%, CH 20%%, TP 10%%, EP 10%%) and restores a base amount (HP 1-20, CH 1-10, TP 1-15, NP 1-5, EP 1-2), plus 2%% of that amount per enemy level (except EP), plus half to all of a slice of the bar’s maximum (HP 5%%, CH 5%%, TP 6%%, NP 4%%, EP 5%%). So luck grows along with your bars, never going above the maximum.<br /><br />
<b>Defeat</b>: if your HP reaches zero you lose <b>half the Ryou you are carrying</b> and wake up in your village with a quarter of your maximum HP.
Ryou kept in the bank is never lost.<br /><br />',

'<h3><a name="elementos"></a>Elementos</h3>
Cada inimigo e cada jutsu pertence a um elemento: <img src="images/fogo.gif" alt="" /> Fogo, <img src="images/agua.gif" alt="" /> Água, <img src="images/vento.gif" alt="" /> Vento,
<img src="images/raio.gif" alt="" /> Raio, <img src="images/terra.gif" alt="" /> Terra ou <img src="images/neutro.gif" alt="" /> Neutro. Os cinco elementos formam um ciclo em que cada um vence o seguinte:
<ul>
<li /><b>Água</b> vence <b>Fogo</b>
<li /><b>Fogo</b> vence <b>Vento</b>
<li /><b>Vento</b> vence <b>Raio</b>
<li /><b>Raio</b> vence <b>Terra</b>
<li /><b>Terra</b> vence <b>Água</b>
</ul>
Um ataque contra o elemento que ele vence é <b>super efetivo</b> e causa 50%% a mais de dano; contra o elemento que o vence é <b>pouco efetivo</b> e causa
metade do dano; contra o <b>mesmo elemento</b> o alvo <b>resiste</b> e recebe só parte do dano. Nos outros casos, e sempre que um dos lados é Neutro, o dano é normal.
A regra vale para os seus jutsus, para os ataques dos inimigos contra você e entre jogadores, nos duelos e no PvP.<br /><br />
<b>Seus elementos.</b> A partir do nível 5, em qualquer vila (<a href="elemento.php">Escolher Elemento</a>), você escolhe um elemento <b>principal</b> e, se quiser,
um <b>secundário</b>. As escolhas são definitivas.
<ul>
<li />Jutsus do seu elemento principal causam mais dano; os do secundário, um pouco mais.
<li />Cada elemento é ligado a um atributo (Água = Sorte, Fogo = Determinação, Vento = Precisão, Raio = Agilidade, Terra = Inteligência): todo jutsu com elemento ganha
+1%% de dano a cada 20 pontos dele. No seu elemento principal esses pontos contam em dobro e no secundário, uma vez e meia.
<li />Na defesa, você passa a ter esses elementos: toma mais dano do elemento que vence o seu e resiste ao seu próprio elemento. Com dois elementos o dano combina os dois:
o principal conta inteiro e o secundário pela metade. Se um deles for fraco contra o ataque, você toma mais dano.
<li />O jutsu só sai com o elemento se você tiver o EP que ele gasta (cada jutsu tem o seu custo). Sem EP ele sai como <b>Neutro</b>: dano normal, gastando só Chakra.
</ul>'
=> '<h3><a name="elementos"></a>Elements</h3>
Every enemy and every jutsu belongs to an element: <img src="images/fogo.gif" alt="" /> Fire, <img src="images/agua.gif" alt="" /> Water, <img src="images/vento.gif" alt="" /> Wind,
<img src="images/raio.gif" alt="" /> Lightning, <img src="images/terra.gif" alt="" /> Earth or <img src="images/neutro.gif" alt="" /> Neutral. The five elements form a cycle in which each one beats the next:
<ul>
<li /><b>Water</b> beats <b>Fire</b>
<li /><b>Fire</b> beats <b>Wind</b>
<li /><b>Wind</b> beats <b>Lightning</b>
<li /><b>Lightning</b> beats <b>Earth</b>
<li /><b>Earth</b> beats <b>Water</b>
</ul>
An attack against the element it beats is <b>super effective</b> and deals 50%% more damage; against the element that beats it, it is <b>not very effective</b> and deals
half damage; against the <b>same element</b> the target <b>resists</b> and takes only part of the damage. In every other case, and whenever either side is Neutral, damage is normal.
The rule applies to your jutsus, to the enemies’ attacks against you and between players, in duels and in PvP.<br /><br />
<b>Your elements.</b> From level 5, in any village (<a href="elemento.php">Choose Element</a>), you choose a <b>main</b> element and, if you want,
a <b>secondary</b> one. The choices are permanent.
<ul>
<li />Jutsus of your main element deal more damage; those of the secondary, a little more.
<li />Each element is linked to an attribute (Water = Luck, Fire = Determination, Wind = Accuracy, Lightning = Agility, Earth = Intelligence): every elemental jutsu gets
+1%% damage per 20 points of it. On your main element those points count double, and one and a half times on the secondary.
<li />On defense, you take on those elements: you take more damage from the element that beats yours and resist your own element. With two elements the damage combines both:
the main one counts in full and the secondary at half. If either is weak against the attack, you take more damage.
<li />A jutsu only carries its element if you have the EP it spends (each jutsu has its own cost). Without EP it goes out as <b>Neutral</b>: normal damage, spending only Chakra.
</ul>',

'<h3><a name="vilas"></a>As Vilas e seus Serviços</h3>
As vilas são os lugares seguros do mapa: nelas não aparecem inimigos. O painel de cada vila tem os quadros <b>Compra e Venda</b>, <b>Administrativos</b> e <b>Outros</b>,
e a página da vila mostra as últimas novidades, os jogadores online e o quadro de conversas.
<ul>
<li /><b>Loja</b> (Comprar Arma/Colete/Bandana): cada vila vende itens diferentes; as vilas mais distantes têm os mais poderosos. Ao comprar um item, o que você usava naquele lugar vai para a mochila (ou para o banco, se ela estiver cheia). Para vender, use <b>Consumíveis e venda</b>: a loja paga 1/4 do preço de loja por armas, coletes e bandanas e 1/3 por consumíveis.
Itens com asterisco vermelho (<span class="highlight">*</span>) dão bônus especiais (HP, Chakra, Força etc.).
<li /><b>Pousada</b> (Descansar numa Pousada): recupera todo o HP, CH, TP e EP. O preço muda de vila para vila.
<li /><b>Mapas</b> (Comprar Mapas): o mapa de uma vila a coloca no seu menu de viagem; depois basta clicar nela para viajar até lá gastando TP. Os mapas das vilas mais secretas não são vendidos: é preciso encontrá-las andando.
<li /><b>Banco</b>: guarda até 30 itens (60 para contas especiais) e parte dos seus Ryou — no máximo 90%% do total entre o que está no banco e o que você carrega. Pelo banco também é possível doar Ryou e enviar itens para outros jogadores.
<li /><b>Missões</b> (Completar Missões): entrega das missões da vila, veja <a href="#treinos">Missões, Treinamentos e Graduações</a>.
<li /><b>Graduação</b>, <b>Alquimia</b>, <b>Senjutsu</b> e <b>Jutsu de Busca</b>: serviços que só existem em algumas vilas (veja a tabela abaixo).
</ul>
%s
<br />
<b>Alquimia</b>: na Montanha Myoboku e na Vila da Areia você pode fundir dois itens específicos da sua mochila para criar um item mais poderoso. As combinações são descobertas jogando — conversar com outros jogadores ajuda.<br /><br />
<b>Fukasaku &amp; Shima</b>: na Montanha Myoboku você pode chamar os dois sapos sábios para o seu grupo (eles passam a aparecer no seu personagem).<br /><br />'
=> '<h3><a name="vilas"></a>Villages and their Services</h3>
Villages are the safe places on the map: no enemies appear inside them. Each village panel has the <b>Buy and Sell</b>, <b>Administrative</b> and <b>Other</b> boxes,
and the village page shows the latest news, the players online and the chat board.
<ul>
<li /><b>Shop</b> (Buy Weapon/Vest/Headband): each village sells different items; the farthest villages have the most powerful ones. When you buy an item, the one you were using in that slot goes to your backpack (or to the bank if the backpack is full). To sell, use <b>Consumables and selling</b>: the shop pays 1/4 of the shop price for weapons, vests and headbands and 1/3 for consumables.
Items with a red asterisk (<span class="highlight">*</span>) give special bonuses (HP, Chakra, Strength etc.).
<li /><b>Inn</b> (Rest at an Inn): restores all your HP, CH, TP and EP. The price changes from village to village.
<li /><b>Maps</b> (Buy Maps): a village’s map adds it to your travel menu; after that, just click it to travel there by spending TP. Maps of the most secret villages are not sold: you have to find them on foot.
<li /><b>Bank</b>: stores up to 30 items (60 for special accounts) and part of your Ryou — at most 90%% of the total between what is in the bank and what you carry. Through the bank you can also donate Ryou and send items to other players.
<li /><b>Missions</b> (Complete Missions): where you hand in the village’s missions, see <a href="#treinos">Missions, Training and Graduations</a>.
<li /><b>Graduation</b>, <b>Alchemy</b>, <b>Senjutsu</b> and <b>Search Jutsu</b>: services found only in some villages (see the table below).
</ul>
%s
<br />
<b>Alchemy</b>: on Mount Myoboku and in the Sand Village you can fuse two specific items from your backpack into a more powerful one. The combinations are discovered by playing — talking to other players helps.<br /><br />
<b>Fukasaku &amp; Shima</b>: on Mount Myoboku you can call the two sage toads to join your party (they start appearing on your character).<br /><br />',

'<h3><a name="treinos"></a>Missões, Treinamentos e Graduações</h3>
<b>Missões</b>: as missões são feitas em sequência. Cada uma pede algo — trazer um item na mochila, derrotar certo inimigo várias vezes seguidas etc. — e deve ser entregue na vila indicada.
Acompanhe a missão atual pelo <b>Painel de Missões</b> (painel da direita). A primeira começa na Vila da Folha.<br /><br />
<b>Treinamentos</b>: alguns jutsus especiais são aprendidos em várias sessões de treino, com um intervalo mínimo entre elas. O andamento fica no <b>Painel de Treinos</b>.
<ul>
<li /><b>Jutsu de Busca</b> (Vila da Areia): 10 treinos, um a cada 2 horas. Depois de aprendido, o link Jutsu de Busca aparece no painel do personagem: por 30 de Chakra ele mostra a coordenada de qualquer jogador e se ele está online.
<li /><b>Senjutsu</b> (Montanha Myoboku): 8 treinos, um a cada 15 horas. Depois de aprendido, um ícone no painel do personagem ativa o modo eremita, com um bônus que depende da sua especialização (Taijutsu: ataque, dano físico e crítico; Ninjutsu: dano elemental e Chakra mais barato; Genjutsu: efeitos mais fortes e resistência; todos ganham defesa; sem especialização: +20%% de Poder de Ataque e de Defesa), gastando 1 NP a cada 3 segundos (mesmo que você não atualize a página) até o NP acabar ou você cancelar.
</ul>
<b>Graduações</b> (Vila da Folha e Vila da Pedra): todo ninja começa como Estudante da Academia.
<ul>
<li /><b>Genin</b>: completar 7 missões. Bônus: 20 pontos de atributo, 20 de NP máximo, 10 de HP e 10 de Chakra.
<li /><b>Chuunin</b>: ser Genin, ter o item <b>Esperança do Chuunin</b> e completar 10 missões. Bônus: 30 de Força, 30 de Destreza e 30 pontos de atributo.
<li /><b>Jounin</b>: ser Chuunin, ter o item <b>Selo de Aprovação Jounin</b> (cai dos chefes de nível 31 a 36), completar 20 missões e ter level 30 ou mais. Bônus: 100 pontos de atributo, 20 de NP máximo, 20 de Força, 20 de Destreza, 5%% de bônus de experiência, 5%% de bônus de Ryou e o item <b>Vitória do Jounin</b>.
</ul>'
=> '<h3><a name="treinos"></a>Missions, Training and Graduations</h3>
<b>Missions</b>: missions are done in order. Each one asks for something — bringing an item in your backpack, defeating a certain enemy several times in a row and so on — and must be handed in at the right village.
Follow your current mission on the <b>Missions Panel</b> (right panel). The first one starts in the Leaf Village.<br /><br />
<b>Training</b>: some special jutsus are learned over several training sessions, with a minimum interval between them. Your progress is shown on the <b>Training Panel</b>.
<ul>
<li /><b>Search Jutsu</b> (Sand Village): 10 sessions, one every 2 hours. Once learned, the Search Jutsu link appears on your character panel: for 30 Chakra it shows the coordinates of any player and whether they are online.
<li /><b>Senjutsu</b> (Mount Myoboku): 8 sessions, one every 15 hours. Once learned, an icon on your character panel activates Sage Mode, with a bonus that depends on your specialization (Taijutsu: attack, physical damage and crits; Ninjutsu: elemental damage and cheaper Chakra; Genjutsu: stronger effects and resistance; all of them gain defense; no specialization: +20%% Attack and Defense Power), spending 1 NP every 3 seconds (even if you don’t refresh the page) until your NP runs out or you cancel it.
</ul>
<b>Graduations</b> (Leaf Village and Stone Village): every ninja starts as an Academy Student.
<ul>
<li /><b>Genin</b>: complete 7 missions. Bonus: 20 attribute points, 20 maximum NP, 10 HP and 10 Chakra.
<li /><b>Chuunin</b>: be a Genin, own the item <b>Chuunin’s Hope</b> and complete 10 missions. Bonus: 30 Strength, 30 Dexterity and 30 attribute points.
<li /><b>Jounin</b>: be a Chuunin, own the item <b>Jounin Approval Seal</b> (dropped by level 31 to 36 bosses), complete 20 missions and be level 30 or higher. Bonus: 100 attribute points, 20 maximum NP, 20 Strength, 20 Dexterity, 5%% experience bonus, 5%% Ryou bonus and the item <b>Jounin’s Victory</b>.
</ul>',

'<h3><a name="itens"></a>Itens, Drops e Mochila</h3>
Seu personagem tem seis lugares de equipamento: <b>arma</b>, <b>colete</b> e <b>bandana</b> (comprados nas lojas) e três <b>slots</b> para os itens dropados pelos inimigos.
A arma aumenta o Poder de Ataque; o colete e a bandana aumentam o Poder de Defesa; os drops dão bônus como HP, Chakra, NP, EP, Força, Destreza, atributos, bônus de experiência e de Ryou ou chance de drop.<br /><br />
Quando um inimigo dropa um item, clique no ponto de exclamação que aparece após a vitória para escolher onde colocá-lo: em um dos slots ou na <b>mochila</b>.
A mochila guarda até 4 itens; passe o mouse sobre eles para ver os bônus e clique para equipá-los. Itens da mochila podem ser guardados no banco, trocados com outros jogadores ou usados na alquimia.<br /><br />
<b>Durabilidade</b>: só os itens fortes se desgastam: as melhores armas, coletes e bandanas das lojas, os drops de nível 45 ou mais e os itens de compensação. Eles saem novos com centenas de vitórias de durabilidade (de 500 a 800, mais para os mais fortes); cada vitória gasta 1 ponto e, ao chegar a zero, o item quebra e some, levando junto os bônus. Passe o mouse sobre o item para ver <i>Durabilidade: N/M</i>; com 10%% ou menos aparece um aviso na coluna da direita e no fim das lutas. Na loja de qualquer vila, <b>Reparar equipamentos</b> deixa os itens equipados e os da mochila como novos, cobrando só o que falta: um reparo completo custa %s%% do valor do item (preço da loja, ou 150 × nível do drop; variante rara vale 3×). Os itens fracos, os marcados com * ou INF e os que você já tinha antes da durabilidade existir são infinitos.<br /><br />
A lista completa de itens e drops está em <a href="help_items.php">Itens &amp; Drops</a>.<br /><br />'
=> '<h3><a name="itens"></a>Items, Drops and Backpack</h3>
Your character has six equipment spots: <b>weapon</b>, <b>vest</b> and <b>headband</b> (bought in shops) and three <b>slots</b> for items dropped by enemies.
The weapon raises your Attack Power; the vest and headband raise your Defense Power; drops give bonuses such as HP, Chakra, NP, EP, Strength, Dexterity, attributes, experience and Ryou bonuses or drop chance.<br /><br />
When an enemy drops an item, click the exclamation mark that appears after the victory to choose where to put it: in one of your slots or in your <b>backpack</b>.
The backpack holds up to 4 items; hover over them to see their bonuses and click to equip them. Backpack items can be stored in the bank, traded with other players or used in alchemy.<br /><br />
<b>Durability</b>: only strong items wear out: the best weapons, vests and headbands from the shops, drops of level 45 or higher and trade-off items. They come new with hundreds of victories of durability (500 to 800, more for the strongest ones); every victory uses up 1 point and, when it reaches zero, the item breaks and disappears, taking its bonuses with it. Hover over the item to see <i>Durability: N/M</i>; at 10%% or less a warning shows up in the right column and at the end of fights. In any village shop, <b>Repair equipment</b> makes your equipped and backpack items as good as new, charging only for what is missing: a full repair costs %s%% of the item value (shop price, or 150 × drop level; a rare variant counts 3×). Weak items, items marked with * or INF and items you already had before durability existed are infinite.<br /><br />
The full list of items and drops is in <a href="help_items.php">Items &amp; Drops</a>.<br /><br />',

'<h3><a name="alquimia"></a>Alquimia</h3>
Nas vilas com alquimista (veja a tabela das vilas) o ícone <a href="alquimia.php?do=fundir">Alquimia</a> abre cinco abas:
<b>Receitas conhecidas</b>, <b>Experimentar</b> (junte itens da mochila livremente: se a combinação for uma receita secreta, você a descobre),
<b>Refinar</b> (3 materiais iguais viram 1 material de nível acima), <b>Infusão</b> (um material raro tenta dar uma variante rara a uma arma, colete, bandana ou drop) e <b>Poções</b> (ervas e materiais viram poções e elixires empilháveis).<br /><br />
Cada receita tem uma chance de sucesso, que sobe com o seu <b>nível de alquimia</b>; o nível sobe com a experiência ganha a cada tentativa (mais no sucesso) e libera receitas novas.
O custo em Ryou é cobrado em toda tentativa e, na <b>falha</b>, você perde %s%% de cada ingrediente (na infusão o item é mantido).
<b>Receitas secretas</b> são aprendidas experimentando ou lendo um <b>livro de receitas</b> dropado pelos inimigos (o livro só pode ir para a mochila; leia-o na aba Receitas conhecidas).
Uma vez por dia, um sucesso pode ser <b>crítico</b> (%s%% de chance) e render o dobro.<br /><br />'
=> '<h3><a name="alquimia"></a>Alchemy</h3>
In villages with an alchemist (see the village table) the <a href="alquimia.php?do=fundir">Alchemy</a> icon opens five tabs:
<b>Known recipes</b>, <b>Experiment</b> (combine backpack items freely: if the combination is a secret recipe, you discover it),
<b>Refine</b> (3 identical materials become 1 material of the next tier), <b>Infusion</b> (a rare material tries to give a rare variant to a weapon, vest, headband or drop) and <b>Potions</b> (herbs and materials become stackable potions and elixirs).<br /><br />
Every recipe has a success chance that grows with your <b>alchemy level</b>; the level rises with the experience earned on every attempt (more on success) and unlocks new recipes.
The Ryou cost is charged on every attempt and, on a <b>failure</b>, you lose %s%% of each ingredient (in an infusion the item is kept).
<b>Secret recipes</b> are learned by experimenting or by reading a <b>recipe book</b> dropped by enemies (books can only go to the backpack; read them in the Known recipes tab).
Once a day, a success may be <b>critical</b> (%s%% chance) and yield double.<br /><br />',

'<h3><a name="atributos"></a>Distribuição de Atributos</h3>
A cada level você ganha 5 pontos de atributo (as graduações dão mais). Distribua-os pelo link <b>Distribuir Pontos</b> do painel da direita. Cada atributo está ligado a um elemento:
<ul>
<li /><b>Agilidade</b> <img src="images/raio.gif" alt="" /> (Raio): aumenta a chance de esquivar dos ataques inimigos (+0,04%% por ponto, até 40%% somando a Destreza).
<li /><b>Sorte</b> <img src="images/agua.gif" alt="" /> (Água): multiplica a chance de drop por 1 + √Sorte/10 (25 pontos = ×1,5, 100 = ×2, 400 = ×3).
<li /><b>Determinação</b> <img src="images/fogo.gif" alt="" /> (Fogo): aumenta a chance de hits excelentes nos ataques físicos (+0,04%% por ponto, até 50%% somando a Força).
<li /><b>Precisão</b> <img src="images/vento.gif" alt="" /> (Vento): diminui a chance de o inimigo escapar dos seus ataques (no duelo, anula a Agilidade do alvo na mesma proporção).
<li /><b>Inteligência</b> <img src="images/terra.gif" alt="" /> (Terra): +0,05%% de dano em todo ninjutsu por ponto (até +50%%) e deixa o inimigo dormindo por mais tempo depois de um genjutsu.
</ul>
Os pontos distribuídos não podem ser retirados depois, então pense bem no estilo de luta do seu ninja.<br /><br />'
=> '<h3><a name="atributos"></a>Distributing Attributes</h3>
Every level you gain 5 attribute points (graduations give more). Spend them through the <b>Distribute Points</b> link on the right panel. Each attribute is tied to an element:
<ul>
<li /><b>Agility</b> <img src="images/raio.gif" alt="" /> (Lightning): raises your chance to dodge enemy attacks (+0.04%% per point, up to 40%% together with Dexterity).
<li /><b>Luck</b> <img src="images/agua.gif" alt="" /> (Water): multiplies the drop chance by 1 + √Luck/10 (25 points = ×1.5, 100 = ×2, 400 = ×3).
<li /><b>Determination</b> <img src="images/fogo.gif" alt="" /> (Fire): raises the chance of excellent hits with physical attacks (+0.04%% per point, up to 50%% together with Strength).
<li /><b>Precision</b> <img src="images/vento.gif" alt="" /> (Wind): lowers the chance that an enemy dodges your attacks (in duels it cancels the target’s Agility at the same rate).
<li /><b>Intelligence</b> <img src="images/terra.gif" alt="" /> (Earth): +0.05%% damage on every ninjutsu per point (up to +50%%) and keeps the enemy asleep longer after a genjutsu.
</ul>
Points you distribute cannot be taken back, so think carefully about your ninja’s fighting style.<br /><br />',

'<h3><a name="trocas"></a>Trocas, Doações e Duelos</h3>
<b>Trocas</b>: pelo ícone de troca nos comandos do personagem (ou pelo ícone ao lado do nome de um jogador na lista de jogadores do mapa) você propõe uma troca de itens
da mochila e Ryou com outro jogador. Os dois precisam estar na <b>mesma coordenada</b> e confirmar a troca.<br /><br />
<b>Doações</b>: pelo banco você pode enviar Ryou e itens para outros jogadores.<br /><br />
<b>Duelos</b>: pelo ícone de duelo você desafia outro jogador que esteja na mesma coordenada. Duelos e doações exigem level %s ou mais.
Durante um duelo você não pode fazer outras ações; se algo der errado, o próprio jogo oferece a opção de cancelar o duelo atual.<br /><br />'
=> '<h3><a name="trocas"></a>Trades, Donations and Duels</h3>
<b>Trades</b>: with the trade icon in your character commands (or the icon next to a player’s name in the list of players on the map) you offer another player a trade of
backpack items and Ryou. Both of you must be on the <b>same coordinates</b> and confirm the trade.<br /><br />
<b>Donations</b>: through the bank you can send Ryou and items to other players.<br /><br />
<b>Duels</b>: with the duel icon you challenge another player standing on the same coordinates. Duels and donations require level %s or higher.
During a duel you cannot do anything else; if something goes wrong, the game itself offers to cancel your current duel.<br /><br />',

'<h3><a name="comunicacao"></a>Chat, Fórum e Mensagens Privadas</h3>
<ul>
<li /><b>Chat global</b>: aberto pelo ícone de conversa nos comandos do personagem, fala com todos os jogadores online.
<li /><b>Quadro de conversas</b>: aparece na página das vilas.
<li /><b>Fórum</b>: o ícone do fórum abre o fórum interno, com tópicos separados por categoria (Dúvidas, Tutoriais, Bate-Papo e Reclamações). Só o autor ou a administração podem apagar uma mensagem, e é preciso esperar alguns segundos entre uma postagem e outra.
<li /><b>Mensagens privadas</b>: use os botões de mensagem no painel da esquerda para ler sua caixa (o ícone pisca quando chega mensagem nova) ou enviar uma mensagem de até 100 caracteres. Clique no nome de um jogador em qualquer lista para ver o perfil ou mandar uma mensagem direto para ele. A caixa guarda as 24 mensagens mais recentes.
</ul>
Os nomes em <font color="green">verde</font> são da administração, em <font color="orange">laranja</font> são tutores e em <font color="blue">azul</font> são GMs.<br /><br />'
=> '<h3><a name="comunicacao"></a>Chat, Forum and Private Messages</h3>
<ul>
<li /><b>Global chat</b>: opened with the chat icon in your character commands, it talks to every player online.
<li /><b>Chat board</b>: shown on the village pages.
<li /><b>Forum</b>: the forum icon opens the in-game forum, with topics sorted by category (Questions, Tutorials, Chit-Chat and Complaints). Only the author or the staff can delete a post, and you have to wait a few seconds between posts.
<li /><b>Private messages</b>: use the message buttons on the left panel to read your inbox (the icon blinks when a new message arrives) or to send a message of up to 100 characters. Click a player’s name in any list to see their profile or message them directly. Your inbox keeps the 24 most recent messages.
</ul>
Names in <font color="green">green</font> belong to the staff, in <font color="orange">orange</font> to tutors and in <font color="blue">blue</font> to GMs.<br /><br />',

'<h3><a name="regras"></a>Regras de Conduta</h3>
Para que o jogo seja divertido para todos:
<ul>
<li />Respeite os outros jogadores. Ofensas, ameaças, preconceito e assédio não são tolerados no chat, no fórum nem nas mensagens privadas.
<li />Não faça flood nem spam: nada de mensagens repetidas, sem sentido, gigantes ou propaganda de outros sites.
<li />Ninguém da equipe vai pedir a sua senha. Não a passe para ninguém e não peça a senha de outros jogadores.
<li />Encontrou um bug? Avise a administração (pelo fórum ou por mensagem privada). Usar falhas do jogo para ganhar vantagem pode levar ao bloqueio da conta.
<li />Trocas e acordos entre jogadores são responsabilidade de quem participa: confira tudo antes de confirmar.
<li />Mensagens que desrespeitarem estas regras podem ser apagadas sem aviso, e contas podem ser bloqueadas pela administração.
</ul>'
=> '<h3><a name="regras"></a>Rules of Conduct</h3>
So that the game is fun for everyone:
<ul>
<li />Respect the other players. Insults, threats, prejudice and harassment are not tolerated in the chat, on the forum or in private messages.
<li />No flooding or spamming: no repeated, meaningless or giant messages, and no advertising other websites.
<li />Nobody from the staff will ever ask for your password. Never give it to anyone and don’t ask other players for theirs.
<li />Found a bug? Tell the staff (on the forum or by private message). Exploiting flaws in the game for an advantage can get your account blocked.
<li />Trades and deals between players are the responsibility of those involved: check everything before you confirm.
<li />Messages that break these rules may be deleted without warning, and accounts may be blocked by the staff.
</ul>',

'<h3><a name="spoilers"></a>Spoilers: Itens, Inimigos, Jutsus e Leveis</h3>
Quer saber tudo antes de descobrir jogando? As listas completas, lidas direto do jogo:
<ul>
<li /><a href="help_items.php">Itens &amp; Drops</a>: tudo o que as lojas vendem e o que os inimigos dropam.
<li /><a href="help_monsters.php">Inimigos</a>: a carta, os status, o level e o elemento de cada inimigo.
<li /><a href="help_spells.php">Jutsus</a>: custo de Chakra, tipo, poder e elemento de cada jutsu.
<li /><a href="help_levels.php">Leveis</a>: a experiência necessária e o que cada especialização ganha em cada level.
</ul>'
=> '<h3><a name="spoilers"></a>Spoilers: Items, Enemies, Jutsus and Levels</h3>
Want to know everything before discovering it in play? The complete lists, read straight from the game:
<ul>
<li /><a href="help_items.php">Items &amp; Drops</a>: everything the shops sell and everything enemies drop.
<li /><a href="help_monsters.php">Enemies</a>: the card, stats, level and element of every enemy.
<li /><a href="help_spells.php">Jutsus</a>: Chakra cost, type, power and element of every jutsu.
<li /><a href="help_levels.php">Levels</a>: the experience required and what each specialization gains at each level.
</ul>',

'<h3><a name="creditos"></a>Créditos</h3>
<b>%s</b> — criado, programado e desenhado por <b>Oyatsumi</b>, com a comunidade Nigeru Animes.<br /><br />
Naruto e seus personagens pertencem a Masashi Kishimoto. Este é um jogo feito por fãs, sem fins lucrativos.<br /><br />
E, claro, obrigado a você por jogar!<br /><br />
[ <a href="#top">Topo</a> ]'
=> '<h3><a name="creditos"></a>Credits</h3>
<b>%s</b> — created, programmed and designed by <b>Oyatsumi</b>, together with the Nigeru Animes community.<br /><br />
Naruto and its characters belong to Masashi Kishimoto. This is a non-profit game made by fans.<br /><br />
And of course, thank you for playing!<br /><br />
[ <a href="#top">Top</a> ]',
    // ajuda: bônus de combate e drops dos inimigos
    "%s (%s%%)" => "%s (%s%%)",
    "Pode dropar: %s" => "May drop: %s",
    "%s inimigo(s), até %s%%" => "%s enemy(ies), up to %s%%",
    "<b>Chance de Crítico:</b> quando o ataque não vira um hit excelente pela Força e Determinação, ainda há esta chance (%) de causar dano dobrado. Máximo de 50%." => "<b>Critical Chance:</b> when an attack does not become an excellent hit through Strength and Determination, there is still this % chance to deal double damage. Maximum 50%.",
    "<b>Economia de Chakra:</b> os jutsus custam essa porcentagem a menos de chakra (arredondado para cima, mínimo 1). Máximo de 50%." => "<b>Chakra Savings:</b> jutsu cost this percentage less chakra (rounded up, minimum 1). Maximum 50%.",
    "<b>Esquiva:</b> quando você não desvia do ataque inimigo pela Destreza e Agilidade, ainda há esta chance (%) de desviar. Máximo de 40%." => "<b>Dodge:</b> when you fail to dodge an enemy attack through Dexterity and Agility, there is still this % chance to dodge. Maximum 40%.",
    "<b>Regeneração de HP por passo:</b> HP recuperado a cada casa andada no mapa, até o HP máximo." => "<b>HP Regen per Step:</b> HP recovered for every tile you walk on the map, up to your max HP.",
    "Atributos" => "Attributes",
    "Bônus de combate dos equipamentos" => "Equipment combat bonuses",
    "Cada item pode ter até 3 bônus; os bônus de itens e drops equipados se somam." => "Each item can have up to 3 bonuses; bonuses from equipped items and drops stack.",
    "Consumível: recupera %s de CH" => "Consumable: restores %s CH",
    "Consumível: recupera %s de HP" => "Consumable: restores %s HP",
    "Inimigos que dropam" => "Dropped by",
    "Chance de Crítico (%)" => "Critical Chance (%)",
    "Esquiva (%)" => "Dodge (%)",
    "Economia de Chakra (%)" => "Chakra Savings (%)",
);
