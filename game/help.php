<?php // help.php :: Página de ajuda do Naruto! Nigeru RPG (guia do jogo). Os números que dependem do banco
// (especializações, dificuldades, vilas, tamanho do mapa) são lidos das tabelas, para a ajuda nunca ficar desatualizada.

include('lib.php');
$link = opendb();
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

include('cookies.php');
$userrow = checkcookies();

function ajuda_numero($valor) { // Número no formato do idioma: 1.234 / 1,5 em português e 1,234 / 1.5 em inglês.

	$valor = (float)$valor;
	$milhar = (idioma() == "en") ? "," : ".";
	$decimal = (idioma() == "en") ? "." : ",";
	return ($valor == floor($valor)) ? number_format($valor, 0, $decimal, $milhar) : number_format($valor, 1, $decimal, $milhar);

}

function ajuda_topo() { // Link de volta ao índice, no fim de cada seção.

	return "[ <a href=\"#top\">".t("Topo")."</a> ]\n\n<br /><br />\n";

}

/* ------------------------------------------------------------------------
   Especializações: resumo calculado a partir da tabela levels (colunas 1_*, 2_*, 3_*)
   e dos tipos de jutsu (spells.type: 1 medicinal, 2 dano, 3 sono, 4 +dano, 5 +destreza).
   ------------------------------------------------------------------------ */
$levelreferencia = 50;
$tiposjutsu = array(1=>"Jutsus medicinais (cura)", 2=>"Jutsus de dano", 3=>"Genjutsus de sono", 4=>"Jutsus de aumento de ataque", 5=>"Jutsus de aumento de defesa");
$jutsus = array();
$spellsquery = doquery("SELECT id,name,type FROM {{table}}", "spells");
while ($spellsrow = mysqli_fetch_array($spellsquery)) { $jutsus[$spellsrow["id"]] = $spellsrow; }

$resumo = array();
for ($c = 1; $c <= 3; $c++) {
	$resumo[$c] = array("exp"=>0, "hp"=>0, "mp"=>0, "strength"=>0, "dexterity"=>0, "tipos"=>array_fill(1, 5, 0));
	$levelsquery = doquery("SELECT id,{$c}_exp,{$c}_hp,{$c}_mp,{$c}_strength,{$c}_dexterity,{$c}_spells FROM {{table}} ORDER BY id", "levels");
	while ($levelsrow = mysqli_fetch_array($levelsquery)) {
		// Os status de cada linha são o ganho ao chegar no level: somando até o level de referência temos o total.
		if ($levelsrow["id"] <= $levelreferencia) {
			$resumo[$c]["hp"] += $levelsrow[$c."_hp"];
			$resumo[$c]["mp"] += $levelsrow[$c."_mp"];
			$resumo[$c]["strength"] += $levelsrow[$c."_strength"];
			$resumo[$c]["dexterity"] += $levelsrow[$c."_dexterity"];
		}
		if ($levelsrow["id"] == $levelreferencia) { $resumo[$c]["exp"] = $levelsrow[$c."_exp"]; }
		$idjutsu = intval($levelsrow[$c."_spells"]);
		if ($idjutsu != 0 && isset($jutsus[$idjutsu]) && isset($resumo[$c]["tipos"][$jutsus[$idjutsu]["type"]])) { $resumo[$c]["tipos"][$jutsus[$idjutsu]["type"]]++; }
	}
}

$tabelaclasses = "<table width=\"100%\" style=\"border: solid 1px black\" cellspacing=\"0\" cellpadding=\"0\">\n<tr><td bgcolor=\"#ffffff\"><b>".t("Especialização")."</b></td>";
for ($c = 1; $c <= 3; $c++) { $tabelaclasses .= "<td bgcolor=\"#ffffff\"><b>".t($controlrow["class".$c."name"])."</b></td>"; }
$tabelaclasses .= "</tr>\n";
$linhas = array(
	"exp"=>tf("Experiência para o level %s", $levelreferencia),
	"hp"=>tf("HP no level %s", $levelreferencia),
	"mp"=>tf("CH no level %s", $levelreferencia),
	"strength"=>tf("Força no level %s", $levelreferencia),
	"dexterity"=>tf("Destreza no level %s", $levelreferencia));
$cor = true;
foreach ($linhas as $campo => $nome) {
	$tabelaclasses .= "<tr><td".($cor ? "" : " bgcolor=\"#ffffff\"").">$nome</td>";
	for ($c = 1; $c <= 3; $c++) { $tabelaclasses .= "<td".($cor ? "" : " bgcolor=\"#ffffff\"").">".ajuda_numero($resumo[$c][$campo])."</td>"; }
	$tabelaclasses .= "</tr>\n";
	$cor = !$cor;
}
foreach ($tiposjutsu as $tipo => $nome) {
	$tabelaclasses .= "<tr><td".($cor ? "" : " bgcolor=\"#ffffff\"").">".t($nome)."</td>";
	for ($c = 1; $c <= 3; $c++) { $tabelaclasses .= "<td".($cor ? "" : " bgcolor=\"#ffffff\"").">".$resumo[$c]["tipos"][$tipo]."</td>"; }
	$tabelaclasses .= "</tr>\n";
	$cor = !$cor;
}
$tabelaclasses .= "</table>";

/* ------------------------------------------------------------------------
   Dificuldades (multiplicadores da tabela control).
   ------------------------------------------------------------------------ */
$listadificuldades = "";
for ($d = 1; $d <= 3; $d++) {
	$mod = (float)$controlrow["diff".$d."mod"];
	$extra = ($mod > 1) ? " ".tf("(ou seja, %s%% a mais)", ajuda_numero(round(($mod - 1) * 100, 1))) : " ".t("(valores normais)");
	$listadificuldades .= "<li />".t($controlrow["diff".$d."name"]).": <b>&times;".ajuda_numero($mod)."</b>$extra\n";
}

/* ------------------------------------------------------------------------
   Vilas: lidas da tabela towns, inclusive os serviços (colunas tem_*), o mapa secreto (secreta)
   e a vila natal de cada alinhamento (vilanatal/alinhamento). Ver cidadesconteudo.php.
   ------------------------------------------------------------------------ */
include_once('cidadesconteudo.php'); include_once('funcoesinclusas.php');
$tabelavilas = "<table width=\"100%\" style=\"border: solid 1px black\" cellspacing=\"0\" cellpadding=\"0\">\n<tr><td bgcolor=\"#ffffff\"><b>".t("Vila")."</b></td><td bgcolor=\"#ffffff\"><b>".t("Coordenada")."</b></td><td bgcolor=\"#ffffff\"><b>".t("Pousada")."</b></td><td bgcolor=\"#ffffff\"><b>".t("Mapa")."</b></td><td bgcolor=\"#ffffff\"><b>".t("Viagem")."</b></td><td bgcolor=\"#ffffff\"><b>".t("Serviços especiais")."</b></td></tr>\n";
$townsquery = doquery("SELECT * FROM {{table}} ORDER BY id", "towns");
$cor = true;
while ($townsrow = mysqli_fetch_array($townsquery)) {
	$lat = ($townsrow["latitude"] < 0) ? ($townsrow["latitude"] * -1)."S" : $townsrow["latitude"]."N";
	$lon = ($townsrow["longitude"] < 0) ? ($townsrow["longitude"] * -1)."W" : $townsrow["longitude"]."E";
	$mapa = (intval($townsrow["secreta"]) != 1) ? ajuda_numero($townsrow["mapprice"])." Ryou" : "<span class=\"light\">".t("Descobrir andando")."</span>";
	$pousada = (intval($townsrow["tem_pousada"]) == 1) ? ajuda_numero($townsrow["innprice"])." Ryou" : "<span class=\"light\">-</span>";
	// Serviços que só algumas vilas têm (e a falta dos serviços comuns).
	$servicos = array();
	if (intval($townsrow["vilanatal"]) == 1 && $townsrow["alinhamento"] != "") { $servicos[] = t("Vila natal"); }
	if (intval($townsrow["tem_graduacao"]) == 1) { $servicos[] = t("Graduação"); }
	if (intval($townsrow["tem_senjutsu"]) == 1) { $servicos[] = t("Treino de Senjutsu, chamar Fukasaku &amp; Shima"); }
	if (intval($townsrow["tem_jutsubusca"]) == 1) { $servicos[] = t("Treino do Jutsu de Busca"); }
	if (intval($townsrow["tem_alquimia"]) == 1) { $servicos[] = t("Alquimia"); }
	if (intval($townsrow["tem_mapas"]) != 1) { $servicos[] = t("Não vende mapas"); }
	if (intval($townsrow["tem_loja"]) != 1) { $servicos[] = t("Sem loja"); }
	if (intval($townsrow["tem_banco"]) != 1) { $servicos[] = t("Sem banco"); }
	if (intval($townsrow["tem_missoes"]) != 1) { $servicos[] = t("Sem missões"); }
	$especial = (count($servicos) > 0) ? implode(", ", $servicos) : "<span class=\"light\">-</span>";
	$fundo = $cor ? "" : " bgcolor=\"#ffffff\"";
	$tabelavilas .= "<tr><td$fundo>".t($townsrow["name"])."</td><td$fundo>[$lat, $lon]</td><td$fundo>$pousada</td><td$fundo>$mapa</td><td$fundo>".ajuda_numero($townsrow["travelpoints"])." TP</td><td$fundo>$especial</td></tr>\n";
	$cor = !$cor;
}
$tabelavilas .= "</table>";

$tamanhomapa = intval($controlrow["gamesize"]);
$nivelpvp = intval($controlrow["pvplevel"]);
$nomedojogo = $controlrow["gamename"];
$classe1 = t($controlrow["class1name"]); $classe2 = t($controlrow["class2name"]); $classe3 = t($controlrow["class3name"]);
$topo = ajuda_topo();

// Texto da ajuda dividido em blocos (um por seção). O texto em português de cada bloco é a chave da tradução
// (lang/en/ajuda.php): os valores lidos do banco entram nos %s e um % literal é escrito %%.
$blocos = array();
$blocos[] = array(<<<'BLOCO'
<a name="top"></a>
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
Esta página explica como o jogo funciona. Se ainda tiver dúvidas, pergunte no fórum ou no chat — os jogadores mais experientes e a equipe do jogo costumam ajudar.<br /><br />
BLOCO
, array($nomedojogo));
$blocos[] = array(<<<'BLOCO'
<h3><a name="personagem"></a>Criando seu Personagem</h3>
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
%7$s</ul>
BLOCO
, array($classe1, $classe2, $classe3, max(1, intval(isset($controlrow["classenivel"]) ? $controlrow["classenivel"] : 5)), $levelreferencia, $tabelaclasses, $listadificuldades));
$blocos[] = array(<<<'BLOCO'
<h3><a name="status"></a>Pontos de Status</h3>
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
<b>Poder de Defesa</b> (Destreza + colete, bandana e itens), além dos cinco atributos que você mesmo distribui (veja <a href="#atributos">Distribuição de Atributos</a>).<br /><br />
BLOCO
, array());
$blocos[] = array(<<<'BLOCO'
<h3><a name="mapa"></a>O Mapa e a Exploração</h3>
O mundo é um grande quadrado de coordenadas, de %sS a %sN e de %sW a %sE. A Vila da Folha fica no centro, em [0N, 0E].
Use os botões <b>Norte</b>, <b>Sul</b>, <b>Leste</b> e <b>Oeste</b> do painel da esquerda para andar uma casa por vez; o mini mapa mostra os arredores e
o link <b>Ver Mapa Completo</b> abre o mapa inteiro, onde você também pode clicar em um ponto para que o personagem ande sozinho até lá.<br /><br />
A cada passo fora das vilas existe uma chance (1 em 4) de encontrar um inimigo. Quanto mais longe do centro, mais fortes eles são: o level máximo
dos inimigos de uma região é a maior distância do centro (em latitude ou longitude) dividida por 5, e aparecem inimigos de até 2 leveis abaixo disso.
Ou seja, até 4 casas da Vila da Folha só aparecem inimigos de level 1; a 100 casas de distância eles já são de level 19 a 21.<br /><br />
Ao pisar na coordenada de uma vila você entra nela e ela passa a fazer parte do seu menu de viagem, sem precisar comprar o mapa.
Enquanto explora, você também vê os outros jogadores que estão na mesma coordenada.<br /><br />
<b>Descansar</b>: fora das vilas, o botão Descansar (nos comandos do personagem) recupera 1 HP, 2 CH e 1 NP a cada 10 segundos. Itens de descanso (só caem de inimigos, como o Chá de Lótus Noturno) multiplicam tudo o que o Descansar recupera por algumas horas (no mínimo 2); use pela mochila.<br /><br />
BLOCO
, array($tamanhomapa, $tamanhomapa, $tamanhomapa, $tamanhomapa));
// Neblina de guerra do mapa-múndi (funcoesneblina.php); só aparece na ajuda se estiver ligada no painel.
if (intval($controlrow["neblinaativa"]) == 1) {
$blocos[] = array(<<<'BLOCO'
<b>Neblina do mapa</b>: o mapa completo é dividido em 20x20 regiões, e no começo só a região onde você está aparece; as outras ficam cobertas
por uma neblina. Cada região em que você pisa é revelada para sempre, junto com as vilas que há nela (vilas cujo mapa você já tem também
aparecem). As buscas de inimigos e de itens só marcam as regiões já descobertas. A porcentagem do mapa descoberto aparece embaixo do mapa
e na ficha do personagem.<br /><br />
BLOCO
, array(), true); // true = continuação do bloco anterior (sem o link "topo" entre os dois).
}
$blocos[] = array(<<<'BLOCO'
<h3><a name="batalhas"></a>Batalhas</h3>
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
Os Ryou guardados no banco não são perdidos.<br /><br />
BLOCO
, array());
$blocos[] = array(<<<'BLOCO'
<h3><a name="elementos"></a>Elementos</h3>
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
</ul>
BLOCO
, array());
$blocos[] = array(<<<'BLOCO'
<h3><a name="pontos"></a>TP, NP e EP</h3>
<b>TP — Pontos de Viagem</b> (barra amarela): o "combustível" das viagens rápidas.
<ul>
<li /><b>Gasto</b>: no menu de cidades (Viajar Para), a viagem custa TP pela distância (TP da vila + %10$s + %11$s a cada 100 casas) e %1$s Ryou por TP (carroças e barqueiros).
Para a <b>vila natal</b> ela custa no mínimo %12$s%% do TP do Pergaminho de Retorno e %13$s Ryou, bem mais que o pergaminho (nunca mais TP que o seu máximo).
Para <b>outras vilas</b> custa mais %14$s Ryou e gasta %15$s <b>Selo de Viagem</b>, que cai de inimigos de nível 20 a 60, é recompensa de algumas missões repetíveis e é feito na Alquimia.
A dica de cada vila e a tela de viagem mostram o custo exato;
o <b>Pergaminho de Retorno</b> custa %2$s%% do TP da viagem até a sua vila natal (no mínimo %3$s TP); o raro <b>Pergaminho de Teleporte Aleatório</b> custa %4$s TP;
o Passo Veloz: Cometa gasta TP a cada passo.
<li /><b>Recuperado</b>: na pousada (tudo), descansando dentro de uma vila (+%5$s TP a cada 3 segundos) e com a sorte de batalha. O máximo cresce com o level e com itens.
</ul>
<b>NP — Pontos Naturais</b> (barra verde): a energia da natureza.
<ul>
<li /><b>Gasto</b>: o Senjutsu ativo consome 1 NP a cada 3 segundos; os jutsus de Senjutsu também custam NP a cada uso.
<li /><b>Recuperado</b>: 1 NP a cada passo no mapa, descansando (fora ou dentro das vilas) e com a sorte de batalha. A pousada <b>não</b> recupera NP. O máximo sobe 5 a cada level e com as graduações.
</ul>
<b>EP — Pontos de Elemento</b> (barra roxa): a afinidade com os elementos.
<ul>
<li /><b>Gasto</b>: cada jutsu de dano com elemento (fogo, água, terra, vento ou raio) gasta %6$s EP. Com EP o jutsu recebe o bônus de atributo do elemento e mais %7$s%% de dano;
sem EP ele funciona, mas sem esses bônus. Os ataques físicos com um jutsu de +Dano ativo só recebem o bônus de elemento enquanto você tiver EP.
<li /><b>Recuperado</b>: na pousada (tudo), descansando dentro de uma vila (+%8$s EP a cada 3 segundos) e com a sorte de batalha. O máximo sobe 1 a cada %9$s levels e com itens.
</ul>
BLOCO
, array(intval(isset($controlrow["viagemryou"]) ? $controlrow["viagemryou"] : 2), intval(isset($controlrow["tpretornopct"]) ? $controlrow["tpretornopct"] : 50), intval(isset($controlrow["tpretorno"]) ? $controlrow["tpretorno"] : 5),
	intval(isset($controlrow["tpteleporte"]) ? $controlrow["tpteleporte"] : 30), intval(isset($controlrow["tpdescanso"]) ? $controlrow["tpdescanso"] : 1), intval(isset($controlrow["epcusto"]) ? $controlrow["epcusto"] : 1),
	intval(isset($controlrow["epbonus"]) ? $controlrow["epbonus"] : 15), intval(isset($controlrow["epdescanso"]) ? $controlrow["epdescanso"] : 1), max(1, intval(isset($controlrow["epnivel"]) ? $controlrow["epnivel"] : 2)),
	intval(isset($controlrow["viagemtpbase"]) ? $controlrow["viagemtpbase"] : 10), intval(isset($controlrow["viagemtpdist"]) ? $controlrow["viagemtpdist"] : 10),
	intval(isset($controlrow["viagemnataltppct"]) ? $controlrow["viagemnataltppct"] : 300), (int)ceil(consumiveisdaloja()["ret"][1] * (isset($controlrow["viagemnatalryoupct"]) ? intval($controlrow["viagemnatalryoupct"]) : 400) / 100),
	intval(isset($controlrow["viagemoutraryou"]) ? $controlrow["viagemoutraryou"] : 300), intval(isset($controlrow["viagemselos"]) ? $controlrow["viagemselos"] : 1)));
$blocos[] = array(<<<'BLOCO'
<h3><a name="especiais"></a>Jutsus Especiais, Teleportes e Itens de Compensação</h3>
Alguns jutsus não são usados em lutas, mas pela lista de <b>Jutsus</b> no canto inferior direito da tela (fora de batalhas):
<ul>
<li /><b>Invocação de Suprimentos</b> (Bolsa, Baú, Armazém e Cofre do Clã): gasta Chakra e tem recarga em minutos. Sorteia um item (equipamento de loja, drop ou poção) que vai para a mochila; os níveis mais altos trazem itens melhores e falham menos, mas às vezes o selo só traz poeira. As três primeiras são aprendidas com pergaminhos raros; o Cofre do Clã só com uma missão muito difícil num canto remoto do mapa.
<li /><b>Passo Veloz</b> (Pluma e Cometa): clique para ligar e de novo para desligar. Ligado, cada passo anda 2 (Pluma) ou 3 (Cometa) casas e cobra Chakra (e TP, no Cometa) a cada passo; desliga sozinho quando os pontos acabam ou quando você entra numa vila. O passo para antes na borda do mapa, ao chegar numa vila (entra nela) ou na casa de um NPC. Continua existindo <b>uma</b> chance de encontrar um inimigo por passo (na casa onde você para).
<li /><b>Jutsus de Senjutsu</b>: só funcionam com o Senjutsu ativo e custam NP além do Chakra. Alguns são usados em lutas, duelos e PvP; outros, pela lista de Jutsus. São ensinados por mestres que vivem nos confins do mapa.
</ul>
<b>Pergaminho de Retorno</b> (lojas): leva você para a sua vila natal, gastando TP. <b>Pergaminho de Teleporte Aleatório</b> (raríssimo, só cai de inimigos fortes): leva você para uma vila aleatória, que pode ser uma que você ainda não conhece, nunca a vila onde você está nem a sua vila natal. Nenhum dos dois funciona em batalhas, duelos ou PvP. As lojas compram o Teleporte por um bom preço.<br /><br />
<b>Itens de compensação</b>: alguns drops raros dão muito mais que os itens comuns, mas tiram algo em troca (por exemplo, muito ataque e menos defesa). O lado negativo aparece em <font color="red"><b>vermelho</b></font> na descrição. Se ele deixasse algum atributo seu abaixo de zero, o item não pode ser equipado.<br /><br />
BLOCO
, array());
$blocos[] = array(<<<'BLOCO'
<h3><a name="vilas"></a>As Vilas e seus Serviços</h3>
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
<b>Fukasaku &amp; Shima</b>: na Montanha Myoboku você pode chamar os dois sapos sábios para o seu grupo (eles passam a aparecer no seu personagem).<br /><br />
BLOCO
, array($tabelavilas));
// Troca de vila natal (docs/TROCA_DE_VILA.md): valores atuais do painel e das missões de transferência.
include_once('funcoesmissoesextras.php');
$trocamin = 30; $trocataxa = 20000;
$qtroca = doquery("SELECT * FROM {{table}} WHERE categoria='extra' AND ativa='1' AND config LIKE '%troca_vila%' ORDER BY nivel_minimo LIMIT 1", "missoesextras");
if ($rtroca = mysqli_fetch_array($qtroca, MYSQLI_ASSOC)) {
	$trocamin = intval($rtroca["nivel_minimo"]);
	foreach (mx_passos($rtroca) as $ptroca) { if ($ptroca["tipo"] == "pagar_ryou") { $trocataxa = intval($ptroca["quantidade"]); } }
}
$blocos[] = array(<<<'BLOCO'
<h3><a name="trocavila"></a>Trocar de Vila Natal</h3>
Todo ninja começa na vila inicial do jogo (o cadastro não pergunta a vila), mas a vila natal pode ser trocada no meio do jogo — só que não é fácil. Perto de cada vila natal vive um <b>embaixador</b> (um NPC no mapa, ao lado da vila).
Converse com o embaixador da vila para onde você quer ir e aceite a missão de <b>Transferência</b>:
<ul>
<li /><b>Requisitos</b>: level %1$s ou mais e graduação Chuunin. A transferência para a sua vila natal atual não aparece, e só é possível fazer uma transferência por vez.
<li /><b>Tarefas</b>: derrotar inimigos dos arredores da nova vila, visitar o marco da fronteira e voltar ao embaixador para pagar a <b>taxa de cidadania</b> (%2$s Ryou, cobrada na entrega).
<li /><b>Na entrega</b> a vila natal muda na hora: é para ela que você volta ao ser derrotado (lutas e PvP) e com o Pergaminho de Retorno, o emblema ao lado do seu nome muda e o Teleporte Aleatório passa a evitar a nova vila. A troca fica registrada no seu histórico.
<li /><b>Espera</b>: depois de trocar, você precisa esperar <b>%3$s dia(s)</b> para trocar de novo. Vilas secretas e lugares sem vila natal não aceitam transferências.
</ul>
A sua vila natal atual aparece na sua ficha (Todos os Status) e no quadro do embaixador, junto com o tempo que falta para a próxima troca.<br /><br />
BLOCO
, array($trocamin, (idioma() == "en" ? number_format($trocataxa) : number_format($trocataxa, 0, ",", ".")), mx_trocaviladias()));
// Troca de dificuldade (docs/TROCA_DE_DIFICULDADE.md): valores atuais do painel e das provações.
$difmin = 15; $diftaxas = array();
$qdif = doquery("SELECT * FROM {{table}} WHERE categoria='extra' AND ativa='1' AND config LIKE '%troca_dificuldade%' ORDER BY nivel_minimo", "missoesextras");
$primeira = true;
while ($rdif = mysqli_fetch_array($qdif, MYSQLI_ASSOC)) {
	if (mx_trocadifid($rdif) == 0) { continue; }
	if ($primeira) { $difmin = intval($rdif["nivel_minimo"]); $primeira = false; }
	foreach (mx_passos($rdif) as $pdif) { if ($pdif["tipo"] == "pagar_ryou") { $diftaxas[mx_trocadifid($rdif)] = intval($pdif["quantidade"]); } }
}
ksort($diftaxas);
$listataxas = array();
foreach ($diftaxas as $d => $v) { $listataxas[] = mx_nomedificuldade($d).": ".(idioma() == "en" ? number_format($v) : number_format($v, 0, ",", "."))." Ryou"; }
$blocos[] = array(<<<'BLOCO'
<h3><a name="trocadif"></a>Trocar de Dificuldade</h3>
A dificuldade escolhida no cadastro pode ser trocada no meio do jogo. Perto da Vila da Folha vive o <b>Mestre das Provações</b> (um NPC no mapa). Converse com ele e aceite a <b>Provação</b> da dificuldade que você quer:
<ul>
<li /><b>Requisitos</b>: level %1$s ou mais. A provação da sua dificuldade atual não aparece, e só é possível fazer uma provação por vez.
<li /><b>Tarefas</b>: vencer algumas lutas seguidas, sem descansar, se curar ou morrer, contra inimigos do patamar da nova dificuldade (quanto mais difícil, mais lutas e inimigos de nível mais alto), e voltar ao Mestre para entregar a <b>oferenda</b> em Ryou, cobrada na entrega (%2$s).
<li /><b>Na entrega</b> a dificuldade muda na hora e vale a partir da próxima luta: HP e dano dos inimigos, experiência e Ryou ganhos (veja <a href="#personagem">Níveis de Dificuldade</a>). Nada do que você já ganhou é refeito. A troca fica registrada no seu histórico.
<li /><b>Espera</b>: depois de trocar, você precisa esperar <b>%3$s dia(s)</b> para trocar de novo.
</ul>
A sua dificuldade atual aparece na sua ficha (Todos os Status) e no quadro do Mestre, junto com o tempo que falta para a próxima troca.<br /><br />
BLOCO
, array($difmin, count($listataxas) ? implode(", ", $listataxas) : "-", mx_trocadifdias()));
$blocos[] = array(<<<'BLOCO'
<h3><a name="treinos"></a>Missões, Treinamentos e Graduações</h3>
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
</ul>
BLOCO
, array());
// Treinos longos (tabela treinos, funcoestreinos.php): a lista sai do banco, com os valores atuais.
include_once('funcoestreinos.php');
$listatreinos = "";
foreach (treinoslista() as $trh) {
	if (intval($trh["ativo"]) != 1) { continue; }
	$listatreinos .= "<li /><b>".t($trh["nome"])."</b> (".treinonomevila($trh["vila_id"]).", ".t($trh["mestre"])."): ".tf("%s sessões, uma a cada %s minutos.", intval($trh["sessoes"]), intval($trh["intervalo"]))." ".tf("Requisitos: %s", treinorequisitostexto($trh))." ".treinodescricao($trh)."\n";
}
$blocos[] = array(<<<'BLOCO'
<h3><a name="treinoslongos"></a>Treinos Longos</h3>
Além do Senjutsu e do Jutsu de Busca, mestres espalhados pelas vilas ensinam treinos longos. Fale com o mestre pelo ícone dele no quadro <b>Outros</b> da vila: o primeiro clique coloca o treino no seu <b>Painel de Treinos</b>, e cada sessão seguinte só pode ser feita na mesma vila e depois do intervalo indicado.
A maioria dos treinos já dá um pequeno bônus a cada sessão; o bônus maior vem ao concluir. Os bônus são permanentes e continuam valendo depois de subir de level.
<ul>
%s</ul>
BLOCO
, array($listatreinos), true);
// Doujutsu (funcoesdoujutsu.php, docs/DOUJUTSU.md): olhos, bônus e custos atuais do banco.
include_once('funcoesdoujutsu.php');
$listaolhos = "";
foreach (doujutsulista() as $ohl) {
	$listaolhos .= "<li /><b>".t($ohl["nome"])."</b> (".doujutsucustotexto($ohl, 1)."): ".implode(", ", doujutsubonustexto($ohl)).".\n";
}
$djc = doujutsucontrole();
$blocos[] = array(<<<'BLOCO'
<h3><a name="doujutsu"></a>Doujutsu (Técnicas Oculares)</h3>
Alguns mestres ensinam a despertar um <b>doujutsu</b>, uma técnica ocular. Os olhos são despertados em treinos longos (veja acima): Olho do Falcão (Vila da Areia, level 12), Sharingan (Vila da Folha, level 20), Byakugan (Vila da Névoa, level 30, Chuunin), Olho Celeste (Vilarejo Céu e Terra, level 45, Jounin) e Rinnegan (Esconderijo Akatsuki, level 60, Jounin). Cada olho tem um estilo: Falcão = precisão, crítico e ignora um pouco da defesa; Sharingan = agilidade, inteligência e esquiva, com ataque e chance de refletir o golpe do inimigo no estágio 4 (evolui em 4 estágios); Byakugan = precisão, ignora defesa, mais drops e vê o que o inimigo carrega; Celeste = defesa, sorte, drops e o mapa geral inteiro sem neblina; Rinnegan = inteligência e um pouco de ataque e defesa, com teleporte e fuga garantida, mas gasta Chakra muito rápido. O gasto de Chakra de todos os olhos cresce com o level. O Rinnegan não é uma evolução do Sharingan.
<ul>
<li /><b>Um olho por vez</b>: despertar outro olho substitui o atual. O treino de despertar sai do Painel de Treinos ao ser concluído e pode ser refeito para voltar a um olho antigo.
<li /><b>Ativar</b>: clique no olho embaixo do seu avatar (apagado = desativado). Ativo, ele gasta Chakra a cada poucos segundos, mesmo sem você atualizar a página, e fecha sozinho quando o Chakra acaba. O doujutsu e o Senjutsu não podem ficar ativos ao mesmo tempo.
<li /><b>Bônus</b>: só valem enquanto o olho está aberto, em lutas, duelos e PvP (o olho do adversário também conta). Nada é somado de forma permanente aos seus atributos.
<li /><b>Evolução do Sharingan</b> (estágios 1 a 4), por qualquer um dos caminhos: abates de inimigos fortes (level de no mínimo o seu menos %2$s) com o Sharingan ativo (%3$s para os estágios 2, 3 e 4); vitórias em duelos desde o estágio atual (%4$s); ou o treino <b>Evolução do Sharingan</b> na Vila da Folha, refeito a cada estágio.
<li /><b>Teleporte do Rinnegan</b> (tela Doujutsu): leva você a qualquer coordenada do mapa, com o Rinnegan ativo e fora de lutas e duelos. Custa %5$s TP + %6$s por 10 casas, %7$s de Chakra + %8$s por 10 casas e %9$s%% do HP máximo + %10$s%% por 10 casas (é preciso sobrar 1 HP). Não abre portais dentro de vilas.
</ul>
Os olhos e o que cada um faz enquanto ativo:
<ul>
%1$s</ul>
BLOCO
, array($listaolhos, intval($djc["dj_abatemargem"]), str_replace(",", "/", $djc["dj_abates"]), str_replace(",", "/", $djc["dj_vitorias"]), intval($djc["dj_tele_tpbase"]), intval($djc["dj_tele_tp10"]), intval($djc["dj_tele_chbase"]), intval($djc["dj_tele_ch10"]), intval($djc["dj_tele_hpbase"]), intval($djc["dj_tele_hp10"])), true);
$blocos[] = array(<<<'BLOCO'
<h3><a name="itens"></a>Itens, Drops e Mochila</h3>
Seu personagem tem seis lugares de equipamento: <b>arma</b>, <b>colete</b> e <b>bandana</b> (comprados nas lojas) e três <b>slots</b> para os itens dropados pelos inimigos.
A arma aumenta o Poder de Ataque; o colete e a bandana aumentam o Poder de Defesa; os drops dão bônus como HP, Chakra, NP, EP, Força, Destreza, atributos, bônus de experiência e de Ryou ou chance de drop.<br /><br />
Quando um inimigo dropa um item, clique no ponto de exclamação que aparece após a vitória para escolher onde colocá-lo: em um dos slots ou na <b>mochila</b>.
A mochila guarda até 4 itens; passe o mouse sobre eles para ver os bônus e clique para equipá-los. Itens da mochila podem ser guardados no banco, trocados com outros jogadores ou usados na alquimia.<br /><br />
<b>Durabilidade</b>: só os itens fortes se desgastam: as melhores armas, coletes e bandanas das lojas, os drops de nível 45 ou mais e os itens de compensação. Eles saem novos com centenas de vitórias de durabilidade (de 500 a 800, mais para os mais fortes); cada vitória gasta 1 ponto e, ao chegar a zero, o item quebra e some, levando junto os bônus. Passe o mouse sobre o item para ver <i>Durabilidade: N/M</i>; com 10%% ou menos aparece um aviso na coluna da direita e no fim das lutas. Na loja de qualquer vila, <b>Reparar equipamentos</b> deixa os itens equipados e os da mochila como novos, cobrando só o que falta: um reparo completo custa %s%% do valor do item (preço da loja, ou 150 × nível do drop; variante rara vale 3×). Os itens fracos, os marcados com * ou INF e os que você já tinha antes da durabilidade existir são infinitos.<br /><br />
A lista completa de itens e drops está em <a href="help_items.php">Itens &amp; Drops</a>.<br /><br />
BLOCO
, array(isset($controlrow["reparo_pct"]) ? intval($controlrow["reparo_pct"]) : 30));
$blocos[] = array(<<<'BLOCO'
<h3><a name="alquimia"></a>Alquimia</h3>
Nas vilas com alquimista (veja a tabela das vilas) o ícone <a href="alquimia.php?do=fundir">Alquimia</a> abre cinco abas:
<b>Receitas conhecidas</b>, <b>Experimentar</b> (junte itens da mochila livremente: se a combinação for uma receita secreta, você a descobre),
<b>Refinar</b> (3 materiais iguais viram 1 material de nível acima), <b>Infusão</b> (um material raro tenta dar uma variante rara a uma arma, colete, bandana ou drop) e <b>Poções</b> (ervas e materiais viram poções e elixires empilháveis).<br /><br />
Cada receita tem uma chance de sucesso, que sobe com o seu <b>nível de alquimia</b>; o nível sobe com a experiência ganha a cada tentativa (mais no sucesso) e libera receitas novas.
O custo em Ryou é cobrado em toda tentativa e, na <b>falha</b>, você perde %s%% de cada ingrediente (na infusão o item é mantido).
<b>Receitas secretas</b> são aprendidas experimentando ou lendo um <b>livro de receitas</b> dropado pelos inimigos (o livro só pode ir para a mochila; leia-o na aba Receitas conhecidas).
Uma vez por dia, um sucesso pode ser <b>crítico</b> (%s%% de chance) e render o dobro.<br /><br />
BLOCO
, array(ajuda_numero($controlrow["alquimiaperda"]), ajuda_numero(floatval($controlrow["alquimiacritico"]))));
$blocos[] = array(<<<'BLOCO'
<h3><a name="atributos"></a>Distribuição de Atributos</h3>
A cada level você ganha 5 pontos de atributo (as graduações dão mais). Distribua-os pelo link <b>Distribuir Pontos</b> do painel da direita. Cada atributo está ligado a um elemento:
<ul>
<li /><b>Agilidade</b> <img src="images/raio.gif" alt="" /> (Raio): aumenta a chance de esquivar dos ataques inimigos (+0,04%% por ponto, até 40%% somando a Destreza).
<li /><b>Sorte</b> <img src="images/agua.gif" alt="" /> (Água): multiplica a chance de drop por 1 + √Sorte/10 (25 pontos = ×1,5, 100 = ×2, 400 = ×3).
<li /><b>Determinação</b> <img src="images/fogo.gif" alt="" /> (Fogo): aumenta a chance de hits excelentes nos ataques físicos (+0,04%% por ponto, até 50%% somando a Força).
<li /><b>Precisão</b> <img src="images/vento.gif" alt="" /> (Vento): diminui a chance de o inimigo escapar dos seus ataques (no duelo, anula a Agilidade do alvo na mesma proporção).
<li /><b>Inteligência</b> <img src="images/terra.gif" alt="" /> (Terra): +0,05%% de dano em todo ninjutsu por ponto (até +50%%) e deixa o inimigo dormindo por mais tempo depois de um genjutsu.
</ul>
Os pontos distribuídos não podem ser retirados depois, então pense bem no estilo de luta do seu ninja.<br /><br />
BLOCO
, array());
$blocos[] = array(<<<'BLOCO'
<h3><a name="trocas"></a>Trocas, Doações e Duelos</h3>
<b>Trocas</b>: pelo ícone de troca nos comandos do personagem (ou pelo ícone ao lado do nome de um jogador na lista de jogadores do mapa) você propõe uma troca de itens
da mochila e Ryou com outro jogador. Os dois precisam estar na <b>mesma coordenada</b> e confirmar a troca.<br /><br />
<b>Doações</b>: pelo banco você pode enviar Ryou e itens para outros jogadores.<br /><br />
<b>Duelos</b>: pelo ícone de duelo você desafia outro jogador que esteja na mesma coordenada. Duelos e doações exigem level %s ou mais.
Durante um duelo você não pode fazer outras ações; se algo der errado, o próprio jogo oferece a opção de cancelar o duelo atual.<br /><br />
BLOCO
, array($nivelpvp));
$blocos[] = array(<<<'BLOCO'
<h3><a name="pvp"></a>PvP no Mundo e Caveiras</h3>
<b>PvP no mundo</b> (neste servidor: <b>%s</b>): fora das vilas você pode atacar outro ninja na mesma casa do mapa ou numa casa vizinha, pela lista "PvP: ninjas por perto". A luta é resolvida na hora, com as regras do duelo.
<ul>
<li /><b>Proteções</b>: ninguém abaixo do level %s ataca nem é atacado; a diferença de level não pode passar de %s (0 = sem limite); espere %s minuto(s) entre um ataque e outro e %s minuto(s) para lutar de novo com o mesmo jogador. <b>Dentro das vilas ninguém pode ser atacado.</b>
<li /><b>Quem perde</b> volta para a vila natal com 1/4 do HP, perde %s%% do Ryou que carrega e, para até %s item(ns), tem %s%% de chance de perder um item (da mochila ou equipado) para o vencedor. Variantes raras ficam protegidas.
<li /><img src="images/caveira_branca.gif" alt="" /> <b>Caveira branca</b>: quem mata sem ser em legítima defesa fica marcado por %s hora(s); se morrer nesse tempo, a chance de perder item aumenta %s%%.
<li /><img src="images/caveira_vermelha.gif" alt="" /> <b>Caveira vermelha</b>: %s mortes dentro do tempo da caveira branca dão a caveira vermelha por %s dia(s); quem morre com ela perde TODOS os itens. Matar de novo com caveira vermelha dá banimento automático de %s dia(s) e a perda de metade dos itens.
<li /><b>Caveira exposta</b>: depois de cada morte injusta, quem tem caveira continua atacável por <b>%s minuto(s)</b>, <b>mesmo se sair do jogo</b>: o personagem fica na última casa do mapa (aparece como "offline, caveira exposta") e a luta é resolvida com os atributos dele. Quando voltar, ele vê o que aconteceu na janela de aviso e no histórico.
<li /><b>Vilas fechadas</b>: nesse mesmo tempo quem tem a caveira exposta não entra em nenhuma vila: nem andando, nem pelo Viajar Para, nem com os pergaminhos de Retorno e de Teleporte. O jogo mostra quantos minutos faltam. Se for derrotado, ele volta para a vila natal normalmente (a morte sempre leva para casa).
</ul>
BLOCO
, array(t(empty($controlrow["pvpmundo"]) ? "Desligado" : "Ligado"), max(intval($controlrow["pvplevel"]), intval($controlrow["pvpnivelseguro"])), intval($controlrow["pvpdiflevel"]), intval($controlrow["pvpesperaataque"]), intval($controlrow["pvpesperaalvo"]),
	intval($controlrow["pvpperdaryou"]), intval($controlrow["pvpmaxitens"]), intval($controlrow["pvpchancedrop"]), intval($controlrow["pvpbrancahoras"]), intval($controlrow["pvpbrancachance"]),
	intval($controlrow["pvpvermelhamortes"]), intval($controlrow["pvpvermelhadias"]), intval($controlrow["pvpbanirdias"]), isset($controlrow["pvpcaveiraminutos"]) ? intval($controlrow["pvpcaveiraminutos"]) : 30)); // docs/PVP.md
$blocos[] = array(<<<'BLOCO'
<h3><a name="comunicacao"></a>Chat, Fórum e Mensagens Privadas</h3>
<ul>
<li /><b>Chat global</b>: aberto pelo ícone de conversa nos comandos do personagem, fala com todos os jogadores online.
<li /><b>Quadro de conversas</b>: aparece na página das vilas.
<li /><b>Fórum</b>: o ícone do fórum abre o fórum interno, com tópicos separados por categoria (Dúvidas, Tutoriais, Bate-Papo e Reclamações). Só o autor ou a administração podem apagar uma mensagem, e é preciso esperar alguns segundos entre uma postagem e outra.
<li /><b>Mensagens privadas</b>: use os botões de mensagem no painel da esquerda para ler sua caixa (o ícone pisca quando chega mensagem nova) ou enviar uma mensagem de até 100 caracteres. Clique no nome de um jogador em qualquer lista para ver o perfil ou mandar uma mensagem direto para ele. A caixa guarda as 24 mensagens mais recentes.
</ul>
Os nomes em <font color="green">verde</font> são da administração, em <font color="orange">laranja</font> são tutores e em <font color="blue">azul</font> são GMs.<br /><br />
BLOCO
, array());
// Contas por conexão (limite de contas logadas por IP, control.loginip_max; login.php, docs/SEGURANCA.md). Texto já traduzido aqui.
$loginipmax = isset($controlrow["loginip_max"]) ? intval($controlrow["loginip_max"]) : 3;
$blocos[] = array(str_replace("%", "%%", "<h3><a name=\"contasip\"></a>".t("Contas por conexão")."</h3>\n".($loginipmax > 0 ? tf("Cada rede (IP) pode ter no máximo %d contas logadas ao mesmo tempo. Para liberar uma vaga, clique em Sair na conta que não está usando (ou espere 10 minutos sem atividade nela). O navegador pode lembrar o nome da conta e a senha: use o gerenciador de senhas dele quando ele oferecer.", $loginipmax) : t("Cada rede (IP) pode ter várias contas logadas ao mesmo tempo (sem limite neste servidor). O navegador pode lembrar o nome da conta e a senha: use o gerenciador de senhas dele quando ele oferecer."))."<br /><br />"), array());
$blocos[] = array(<<<'BLOCO'
<h3><a name="regras"></a>Regras de Conduta</h3>
Para que o jogo seja divertido para todos:
<ul>
<li />Respeite os outros jogadores. Ofensas, ameaças, preconceito e assédio não são tolerados no chat, no fórum nem nas mensagens privadas.
<li />Não faça flood nem spam: nada de mensagens repetidas, sem sentido, gigantes ou propaganda de outros sites.
<li />Ninguém da equipe vai pedir a sua senha. Não a passe para ninguém e não peça a senha de outros jogadores.
<li />Encontrou um bug? Avise a administração (pelo fórum ou por mensagem privada). Usar falhas do jogo para ganhar vantagem pode levar ao bloqueio da conta.
<li />Trocas e acordos entre jogadores são responsabilidade de quem participa: confira tudo antes de confirmar.
<li />Mensagens que desrespeitarem estas regras podem ser apagadas sem aviso, e contas podem ser bloqueadas pela administração.
</ul>
BLOCO
, array());
$blocos[] = array(<<<'BLOCO'
<h3><a name="spoilers"></a>Spoilers: Itens, Inimigos, Jutsus e Leveis</h3>
Quer saber tudo antes de descobrir jogando? As listas completas, lidas direto do jogo:
<ul>
<li /><a href="help_items.php">Itens &amp; Drops</a>: tudo o que as lojas vendem e o que os inimigos dropam.
<li /><a href="help_monsters.php">Inimigos</a>: a carta, os status, o level e o elemento de cada inimigo.
<li /><a href="help_spells.php">Jutsus</a>: custo de Chakra, tipo, poder e elemento de cada jutsu.
<li /><a href="help_levels.php">Leveis</a>: a experiência necessária e o que cada especialização ganha em cada level.
</ul>
BLOCO
, array());
$blocos[] = array(<<<'BLOCO'
<h3><a name="creditos"></a>Créditos</h3>
<b>%s</b> — criado, programado e desenhado por <b>Oyatsumi</b>, com a comunidade Nigeru Animes.<br /><br />
Naruto e seus personagens pertencem a Masashi Kishimoto. Este é um jogo feito por fãs, sem fins lucrativos.<br /><br />
E, claro, obrigado a você por jogar!<br /><br />
[ <a href="#top">Topo</a> ]
BLOCO
, array($nomedojogo));
$page = "";
foreach ($blocos as $numero => $bloco) {
	$page .= vsprintf(t($bloco[0]), $bloco[1])."\n";
	if ($numero < count($blocos) - 1 && empty($blocos[$numero + 1][2])) { $page .= $topo; }
}

display($page, t("Ajuda"), false, false, false);

?>
