"""Gera o SQL de instalação limpo do Naruto RPG a partir do dump original de 2018.
- prefixo dk_ -> naruto_ ; InnoDB + utf8mb4
- remove dados de jogadores (usuários, chats, fórum)
- restaura acentos perdidos ('?') nos dados do jogo
- adiciona novos monstros (152-166) com cartas desenhadas a partir do monstros.psd
- inimigos 167-206 e terrenos dos inimigos (coluna monsters.terrenos)
"""
import re, sys, collections
SRC, OUT = sys.argv[1], sys.argv[2]
s = open(SRC, encoding='utf8').read()

FIX = {
 'F?cil':'Fácil','M?dio':'Médio','Dif?cil':'Difícil','Resist?ncia':'Resistência','Prote??o':'Proteção','Esperan?a':'Esperança',
 'For?a':'Força','Vital?cia':'Vitalícia','Invoca??o':'Invocação','J?ia':'Jóia','Top?zio':'Topázio','Determina?':'Determinação',
 'Mem?ria':'Memória','Motiva??o':'Motivação','N?voa':'Névoa','Esp?rito':'Espírito','Vit?ria':'Vitória','In?til':'Inútil',
 'S?bita':'Súbita','M?scara':'Máscara','Chap?u':'Chapéu','C?o':'Cão','?gua':'Água','s?nico':'sônico','Espi?':'Espiã',
 'Indom?vel':'Indomável','Espi?o':'Espião','Ex?rcito':'Exército','Prim?rio':'Primário','Avan?ado':'Avançado',
 'Revitaliza?':'Revitalização','L?tus':'Lótus','Prim?ria':'Primária','C?u':'Céu','D\\\'?gua':'D\\\'Água',
}
def fix(txt):
    for k in sorted(FIX, key=len, reverse=True):
        txt = txt.replace(k, FIX[k])
    return txt

def inserts(table):
    m = re.search(r"INSERT INTO `dk_%s` (\(.*?\)) VALUES\n(.*?);\n" % table, s, re.S)
    return m.group(1), m.group(2)

out = []
W = out.append
W("""-- ---------------------------------------------------------------------------
-- Naruto RPG :: banco de dados de instalação (estrutura + dados do jogo)
-- Gerado por tools/build_sql.py. Codificação: UTF-8 (utf8mb4).
-- O instalador troca o prefixo `naruto_` se você escolher outro.
-- ---------------------------------------------------------------------------
SET NAMES utf8mb4;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-03:00";
""")

SCHEMA = r"""
-- Chat global (babble box).
CREATE TABLE `naruto_babble` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `posttime` time NOT NULL DEFAULT '00:00:00',
  `author` varchar(30) NOT NULL DEFAULT '',
  `babble` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat de cada coordenada do mapa (guarda as últimas 10 falas por lugar).
CREATE TABLE `naruto_chatmap` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(40) NOT NULL DEFAULT '',
  `fala` varchar(400) NOT NULL DEFAULT '',
  `latitude` mediumint(5) NOT NULL DEFAULT 0,
  `longitude` mediumint(5) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `local` (`latitude`,`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configurações gerais do jogo (uma única linha, id=1). Editadas pelo Painel do Administrador.
CREATE TABLE `naruto_control` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `gamename` varchar(50) NOT NULL DEFAULT '',
  `gamesize` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `gameopen` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `gameurl` varchar(200) NOT NULL DEFAULT '',
  `adminemail` varchar(100) NOT NULL DEFAULT '',
  `forumtype` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `forumaddress` varchar(200) NOT NULL DEFAULT '',
  `class1name` varchar(50) NOT NULL DEFAULT '',
  `class2name` varchar(50) NOT NULL DEFAULT '',
  `class3name` varchar(50) NOT NULL DEFAULT '',
  `diff1name` varchar(50) NOT NULL DEFAULT '',
  `diff1mod` float UNSIGNED NOT NULL DEFAULT 0,
  `diff2name` varchar(50) NOT NULL DEFAULT '',
  `diff2mod` float UNSIGNED NOT NULL DEFAULT 0,
  `diff3name` varchar(50) NOT NULL DEFAULT '',
  `diff3mod` float UNSIGNED NOT NULL DEFAULT 0,
  `compression` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `verifyemail` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `shownews` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `showbabble` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `showonline` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `allowregister` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `closedmsg` varchar(500) NOT NULL DEFAULT 'O jogo está fechado para manutenção. Por favor volte mais tarde e desculpe o transtorno.',
  `loginmsg` varchar(1000) NOT NULL DEFAULT '',
  `startgold` mediumint(8) UNSIGNED NOT NULL DEFAULT 100,
  `chancetofight` tinyint(3) UNSIGNED NOT NULL DEFAULT 4,
  `expmultiplier` float UNSIGNED NOT NULL DEFAULT 1,
  `goldmultiplier` float UNSIGNED NOT NULL DEFAULT 1,
  `pvplevel` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
  `idiomapadrao` varchar(5) NOT NULL DEFAULT 'pt',
  `atualizacaodinamica` tinyint(1) NOT NULL DEFAULT 1,
  -- Neblina de guerra do mapa-múndi (funcoesneblina.php): 1 = cada jogador só vê os quadrantes por onde andou.
  `neblinaativa` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  -- E-mails do jogo: 0 = sem e-mail (conta liberada na hora; senha perdida pelas perguntas secretas/código de recuperação).
  `emailativo` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  -- PvP no mundo (pvp.php), ligado por padrão (padrões em tools/pvp_caveira.sql). Esperas em minutos, chances em %.
  `pvpmundo` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `pvpdiflevel` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `pvpnivelseguro` smallint(5) UNSIGNED NOT NULL DEFAULT 15,
  `pvpesperaalvo` smallint(5) UNSIGNED NOT NULL DEFAULT 60,
  `pvpesperaataque` smallint(5) UNSIGNED NOT NULL DEFAULT 2,
  `pvpchancedrop` tinyint(3) UNSIGNED NOT NULL DEFAULT 25,
  `pvpondedrop` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0 = só mochila, 1 = mochila e equipados',
  `pvpprotegervariantes` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `pvpmaxitens` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `pvpperdaryou` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  -- Caveiras: branca (horas, chance extra de perder item), vermelha (mortes, dias, banco), banimento automático (dias).
  `pvpbrancahoras` smallint(5) UNSIGNED NOT NULL DEFAULT 24,
  `pvpbrancachance` tinyint(3) UNSIGNED NOT NULL DEFAULT 25,
  `pvpvermelhamortes` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `pvpvermelhadias` smallint(5) UNSIGNED NOT NULL DEFAULT 14,
  `pvpvermelhabanco` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `pvpbanirdias` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `pvpbanirmetade` tinyint(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'banimento automático também destrói metade dos itens',
  -- Morte para inimigos do mapa (fight.php :: penalidademorte): Ryou % = min(max, base + nível x por nível);
  -- experiência % da barra do nível = min(max, nível x por nível); chance de item % = min(max, nível x por nível);
  -- até mortenivelseguro só Ryou. Logout/luta parada (mortelutaabandono min) = derrota com mortelutachance %, senão cancela.
  `morteryoubase` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `morteryounivel` decimal(5,2) UNSIGNED NOT NULL DEFAULT 1.00,
  `morteryoumax` tinyint(3) UNSIGNED NOT NULL DEFAULT 50,
  `morteexpnivel` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.50,
  `morteexpmax` tinyint(3) UNSIGNED NOT NULL DEFAULT 25,
  `morteitemnivel` decimal(5,2) UNSIGNED NOT NULL DEFAULT 0.25,
  `morteitemmax` tinyint(3) UNSIGNED NOT NULL DEFAULT 15,
  `mortenivelseguro` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
  `mortelutaabandono` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
  `mortelutachance` tinyint(3) UNSIGNED NOT NULL DEFAULT 50,
  -- Venda de itens da mochila nas lojas: multiplicador (%) aplicado ao preço base de cada item.
  `vendamochila` smallint(5) UNSIGNED NOT NULL DEFAULT 100,
  -- Máximo da pilha do Pergaminho de Retorno e do Incenso Repelente na mochila (1 = não empilha).
  `pilhaconsumiveis` smallint(5) UNSIGNED NOT NULL DEFAULT 20,
  -- Comprador de pergaminhos de jutsu nas vilas (1 = sim) e % pago pelo comprador distante (NPC papel comprador_pergaminhos).
  `compradorvila` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `compradorremoto` smallint(5) UNSIGNED NOT NULL DEFAULT 200,
  `classenivel` smallint(5) UNSIGNED NOT NULL DEFAULT 5 COMMENT 'nível mínimo para escolher a especialização (especializacao.php)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Itens de drop (fragmentos, pedras e jóias equipados nos slots 1-3 ou guardados na mochila).
CREATE TABLE `naruto_drops` (
  `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `mlevel` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `type` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `attribute1` varchar(30) NOT NULL DEFAULT '',
  `attribute2` varchar(30) NOT NULL DEFAULT '',
  `attribute3` varchar(30) NOT NULL DEFAULT 'X',
  -- Consumível: "hp,50" ou "mp,40" = vai para a mochila como poção ("nome,hp,50,1") em vez de ser equipado. Vazio = drop equipável.
  `consumivel` varchar(20) NOT NULL DEFAULT '',
  -- Pergaminho de jutsu: id do jutsu (spells.id) que ele ensina ao ser usado na mochila ("nome,jutsu,ID,1"). 0 = drop comum.
  `ensina_jutsu` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  -- 1 = não pode ser vendido em loja; empilhável (1) e máximo de unidades por espaço da mochila.
  `naoVendavel` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `empilhavel` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `maxpilha` smallint(5) UNSIGNED NOT NULL DEFAULT 20,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fórum interno.
CREATE TABLE `naruto_forum` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `postdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `newpostdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `author` varchar(30) NOT NULL DEFAULT '',
  `parent` int(11) NOT NULL DEFAULT 0,
  `replies` int(11) NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL DEFAULT '',
  `content` text NOT NULL,
  `categoria` varchar(15) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Itens das lojas (type 1 = arma, 2 = colete, 3 = bandana/chapéu).
CREATE TABLE `naruto_items` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `name` varchar(50) NOT NULL DEFAULT '',
  `buycost` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `attribute` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `special` varchar(50) NOT NULL DEFAULT '',
  -- Até 3 bônus "atributo,valor" por item (special2/special3 = "X" quando vazios).
  `special2` varchar(50) NOT NULL DEFAULT 'X',
  `special3` varchar(50) NOT NULL DEFAULT 'X',
  -- 1 = não pode ser vendido em loja; empilhável (1) e máximo de unidades por espaço da mochila.
  `naoVendavel` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `empilhavel` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `maxpilha` smallint(5) UNSIGNED NOT NULL DEFAULT 20,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de níveis: experiência necessária e ganhos de cada especialização (1, 2 e 3).
CREATE TABLE `naruto_levels` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `1_exp` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `1_hp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `1_mp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `1_tp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `1_strength` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `1_dexterity` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `1_spells` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `2_exp` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `2_hp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `2_mp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `2_tp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `2_strength` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `2_dexterity` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `2_spells` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `3_exp` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `3_hp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `3_mp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `3_tp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `3_strength` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `3_dexterity` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `3_spells` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tentativas de login erradas por IP (proteção contra força bruta).
CREATE TABLE `naruto_loginfail` (
  `ip` varchar(45) NOT NULL,
  `tentativas` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `ultima` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inimigos. A carta de cada um fica em layoutnovo/monstros/{id}.jpg (295x145).
CREATE TABLE `naruto_monsters` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `maxhp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `maxdam` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `armor` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `level` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `maxexp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `maxgold` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `immune` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `elemento` varchar(10) NOT NULL DEFAULT 'neutro',
  -- Região opcional do mapa (retângulo de latitude/longitude). NULL = sem região (só a regra do nível).
  -- regiao_exclusiva = 1: o inimigo só aparece dentro do retângulo; 0: aparece normalmente e tem preferência dentro dele.
  `regiao_latmin` int(11) NULL DEFAULT NULL,
  `regiao_latmax` int(11) NULL DEFAULT NULL,
  `regiao_lonmin` int(11) NULL DEFAULT NULL,
  `regiao_lonmax` int(11) NULL DEFAULT NULL,
  `regiao_exclusiva` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  -- Terrenos do mini mapa em que o inimigo aparece (nomes da tabela terrenos separados por vírgula, ex.: 'deserto,rochoso').
  -- Vazio = qualquer terreno. Se nenhum inimigo da faixa de nível puder aparecer no terreno da casa, a restrição é ignorada.
  `terrenos` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de drops de cada inimigo: cada linha é sorteada na vitória com chance_percent (0,01 a 100).
-- Inimigo sem linhas usa a regra antiga (1 chance em 25, drop aleatório com nível mínimo <= nível do inimigo).
CREATE TABLE `naruto_monster_drops` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `monster_id` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `drop_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `chance_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  -- % dos drops desta linha que saem como VARIANTE rara (skin dourado/brilho/raio/chama/gelo/sombra + bônus pequeno).
  `chance_variante_percent` decimal(5,2) NOT NULL DEFAULT 0.50,
  PRIMARY KEY (`id`),
  KEY `monster_id` (`monster_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notícias exibidas nas cidades.
CREATE TABLE `naruto_news` (
  `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
  `postdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `content` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jutsus (type 1 = cura, 2 = dano, 3 = sono/genjutsu, 4 = +ataque, 5 = +defesa, 6 = suporte).
-- Campos configuráveis (funcoesjutsus.php): golpes, roubo de vida, efeito extra (queimadura, veneno, atordoar, escudo,
-- buff_*/debuff_*, cura_turnos, dreno_chakra) com valor/turnos/chance, recarga e requisitos para aprender por pergaminho.
CREATE TABLE `naruto_spells` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `mp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `attribute` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `type` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `elemento` varchar(10) NOT NULL DEFAULT 'neutro',
  `golpes` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `roubovida` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `efeito` varchar(20) NOT NULL DEFAULT '',
  `efeito_valor` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `efeito_turnos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `efeito_chance` tinyint(3) UNSIGNED NOT NULL DEFAULT 100,
  `cooldown` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `nivelminimo` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `classe` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `graduacao` varchar(30) NOT NULL DEFAULT '',
  `name_en` varchar(50) NOT NULL DEFAULT '',
  `descricao` text NOT NULL DEFAULT '',
  `descricao_en` text NOT NULL DEFAULT '',
  `exclusivo_missao` tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = só como recompensa de missão (sem nível, sem pergaminho)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vilas/cidades do mapa.
CREATE TABLE `naruto_towns` (
  `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL DEFAULT '',
  `latitude` smallint(6) NOT NULL DEFAULT 0,
  `longitude` smallint(6) NOT NULL DEFAULT 0,
  `innprice` smallint(5) NOT NULL DEFAULT 0,
  `mapprice` mediumint(10) NOT NULL DEFAULT 0,
  `travelpoints` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `itemslist` text NOT NULL,
  `vip` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `kage` varchar(20) NOT NULL DEFAULT '',
  `name_en` varchar(30) NOT NULL DEFAULT '',
  `kage_en` varchar(20) NOT NULL DEFAULT '',
  `descricao` text NOT NULL DEFAULT '',
  `descricao_en` text NOT NULL DEFAULT '',
  `alinhamento` varchar(10) NOT NULL DEFAULT '' COMMENT 'código do alinhamento (users.alinhamento) cuja vila natal é esta',
  `vilanatal` tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = opção no registro e destino de quem morre com esse alinhamento',
  `secreta` tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = mapa não vendido (descobrir andando)',
  `tem_pousada` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `tem_loja` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `tem_mapas` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `tem_banco` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `tem_missoes` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `tem_graduacao` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `tem_alquimia` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `tem_senjutsu` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `tem_jutsubusca` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `local` (`latitude`,`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Terrenos do mini mapa: retângulo de coordenadas -> imagem de chão (images/terreno_NOME.jpg, 155x155).
-- A casa usa o terreno de maior prioridade que a contém; arvores = 0 desliga as decorações do bioma (árvores, pinheiros,
-- pedras, cactos, juncos). Os ladrilhos 30x30 ficam em images/terreno/NOME_V.png (tools/gerar_tiles_terreno.py).
CREATE TABLE `naruto_terrenos` (
  `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(20) NOT NULL DEFAULT '',
  `imagem` varchar(60) NOT NULL DEFAULT '',
  `latmin` smallint(6) NOT NULL DEFAULT 0,
  `latmax` smallint(6) NOT NULL DEFAULT 0,
  `lonmin` smallint(6) NOT NULL DEFAULT 0,
  `lonmax` smallint(6) NOT NULL DEFAULT 0,
  `prioridade` smallint(6) NOT NULL DEFAULT 0,
  `arvores` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Duelos PvP (users.php?do=batalha1 / do=duelo). Uma linha por desafio; lado 1 = desafiante, lado 2 = desafiado.
-- estado: pendente, ativo, fim, recusado, cancelado, expirado. vez = id de quem joga; prazo = fim do turno (UNIX).
-- aposta = Ryou guardado de CADA jogador (o vencedor recebe 2 x aposta). dano/defesa = bônus dos jutsus (%);
-- guarda = defendendo; atrasos = tempos esgotados; registro = últimas 8 ações (uma por linha, formato i18n:);
-- jutsuestado = efeitos de jutsu ativos e recargas (JSON de funcoesjutsus.php, lados 1 e 2).
CREATE TABLE `naruto_duelos` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `desafiante` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `desafiado` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `estado` varchar(10) NOT NULL DEFAULT 'pendente',
  `aposta` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `vez` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `prazo` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `turno` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `hpantes1` smallint(6) UNSIGNED NOT NULL DEFAULT 0,
  `hpantes2` smallint(6) UNSIGNED NOT NULL DEFAULT 0,
  `dano1` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `defesa1` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `dano2` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `defesa2` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `guarda1` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `guarda2` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `atrasos1` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `atrasos2` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `vencedor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `registro` text NOT NULL DEFAULT '',
  `jutsuestado` text NOT NULL DEFAULT '' COMMENT 'efeitos de jutsu e recargas (JSON, lados 1/2; ver funcoesjutsus.php)',
  `criado` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `atualizado` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `desafiante` (`desafiante`,`estado`),
  KEY `desafiado` (`desafiado`,`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registro de auditoria dos painéis (admin.php, admin_missoes.php, gm.php) e das recuperações de senha.
CREATE TABLE `naruto_adminlog` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `quando` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `autor_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `autor` varchar(30) NOT NULL DEFAULT '',
  `cargo` varchar(10) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `acao` varchar(60) NOT NULL DEFAULT '',
  `alvo_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `detalhes` text NOT NULL DEFAULT '',
  PRIMARY KEY (`id`), KEY `quando` (`quando`), KEY `autor` (`autor_id`), KEY `alvo` (`alvo_id`), KEY `acao` (`acao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lutas do PvP no mundo (pvp.php): esperas, legítima defesa, caveiras (injusto = 1) e a tela do resultado.
CREATE TABLE `naruto_pvp` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `atacante` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `defensor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `vencedor` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `quando` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `injusto` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `ryou` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `itens` varchar(255) NOT NULL DEFAULT '',
  `registro` text NOT NULL DEFAULT '',
  PRIMARY KEY (`id`), KEY `atacante` (`atacante`,`quando`), KEY `defensor` (`defensor`,`quando`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Variantes raras dos itens (admin.php?do=variantes). bonus = "atributo,valor" ou vazio (= percentual do atributo principal).
CREATE TABLE `naruto_variantes` (
  `efeito` varchar(20) NOT NULL,
  `nome` varchar(40) NOT NULL DEFAULT '',
  `nome_en` varchar(40) NOT NULL DEFAULT '',
  `cor` varchar(7) NOT NULL DEFAULT '#b8860b',
  `bonus` varchar(40) NOT NULL DEFAULT '',
  `percentual` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `peso` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `ativo` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `ordem` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`efeito`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `naruto_variantes` (`efeito`, `nome`, `nome_en`, `cor`, `bonus`, `percentual`, `peso`, `ativo`, `ordem`) VALUES
('dourado', 'Dourada', 'Golden', '#b8860b', '', 10, 1, 1, 1),
('brilho', 'Brilhante', 'Shining', '#0e9fbf', 'critico,2', 10, 1, 1, 2),
('raio', 'Elétrica', 'Electric', '#2f62e0', 'esquiva,2', 10, 1, 1, 3),
('chama', 'Flamejante', 'Blazing', '#d9480f', 'attackpower,5', 10, 1, 1, 4),
('gelo', 'Congelada', 'Frozen', '#2b8fc7', 'defensepower,5', 10, 1, 1, 5),
('sombra', 'Sombria', 'Shadow', '#8e3cc8', 'economiachakra,3', 10, 1, 1, 6);

-- Jogadores. Muitos campos guardam listas no formato "a,b,c" (missão, durabilidade, mochila...).
CREATE TABLE `naruto_users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `verify` varchar(8) NOT NULL DEFAULT '0',
  `resetcode` varchar(64) NOT NULL DEFAULT '',
  `resetexpira` datetime DEFAULT NULL,
  `charname` varchar(30) NOT NULL DEFAULT '',
  `regdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `onlinetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `authlevel` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `latitude` smallint(6) NOT NULL DEFAULT 0,
  `longitude` smallint(6) NOT NULL DEFAULT 0,
  `difficulty` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `charclass` tinyint(4) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = sem especialização (escolhe no jogo, especializacao.php), 1 Ninjutsu, 2 Taijutsu, 3 Genjutsu',
  `currentaction` varchar(30) NOT NULL DEFAULT 'In Town',
  `currentfight` tinyint(4) UNSIGNED NOT NULL DEFAULT 0,
  `currentmonster` smallint(6) UNSIGNED NOT NULL DEFAULT 0,
  `currentmonsterhp` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `currentmonstersleep` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `currentmonsterimmune` tinyint(4) NOT NULL DEFAULT 0,
  `currentuberdamage` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `currentuberdefense` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `currenthp` smallint(6) UNSIGNED NOT NULL DEFAULT 15,
  `currentmp` smallint(6) UNSIGNED NOT NULL DEFAULT 0,
  `currenttp` smallint(6) UNSIGNED NOT NULL DEFAULT 10,
  `maxhp` smallint(6) UNSIGNED NOT NULL DEFAULT 15,
  `maxmp` smallint(6) UNSIGNED NOT NULL DEFAULT 0,
  `maxtp` smallint(6) UNSIGNED NOT NULL DEFAULT 10,
  `level` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `gold` mediumint(8) UNSIGNED NOT NULL DEFAULT 100,
  `experience` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `goldbonus` smallint(5) NOT NULL DEFAULT 0,
  `expbonus` smallint(5) NOT NULL DEFAULT 0,
  `strength` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
  `dexterity` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
  `attackpower` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
  `defensepower` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
  `weaponid` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `armorid` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `shieldid` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `slot1id` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `slot2id` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `slot3id` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `weaponname` varchar(50) NOT NULL DEFAULT 'None',
  `armorname` varchar(50) NOT NULL DEFAULT 'None',
  `shieldname` varchar(50) NOT NULL DEFAULT 'None',
  `slot1name` varchar(50) NOT NULL DEFAULT 'None',
  `slot2name` varchar(50) NOT NULL DEFAULT 'None',
  `slot3name` varchar(50) NOT NULL DEFAULT 'None',
  `dropcode` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `dropvariante` varchar(10) NOT NULL DEFAULT '',
  `spells` varchar(255) NOT NULL DEFAULT '0',
  -- Efeitos de jutsu ativos e recargas da luta atual (JSON; funcoesjutsus.php), apagado no fim da luta.
  `jutsuestado` text NOT NULL DEFAULT '',
  `lutaultimaacao` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'UNIX da última ação na luta PvE (luta abandonada)',
  `towns` varchar(255) NOT NULL DEFAULT '0',
  `batalha_nome` varchar(30) NOT NULL DEFAULT 'None',
  `batalha_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `batalha_hp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `batalha_timer` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `banco_grana` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `batalha_timer2` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `batalha_acao` varchar(255) NOT NULL DEFAULT 'None',
  `duelos_vencidos` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'duelos PvP vencidos (rank.php?ord=duelos)',
  `duelos_perdidos` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'duelos PvP perdidos',
  `avatar` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `imagem` varchar(30) NOT NULL DEFAULT 'nacidade.png',
  `recuperarvida` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `ultimodescanso` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `agilidade` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `sorte` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `inteligencia` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `precisao` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `determinacao` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `pontoatributos` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `missao` varchar(20) NOT NULL DEFAULT '1,0,0,0',
  `missaoswitch` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `jutsudebusca` int(20) UNSIGNED NOT NULL DEFAULT 7200,
  `jutsudebuscaswitch` smallint(2) UNSIGNED NOT NULL DEFAULT 0,
  `jutsudebuscahtml` varchar(100) NOT NULL DEFAULT '',
  `caixadepm` text NOT NULL DEFAULT '',
  `graduacao` varchar(30) NOT NULL DEFAULT 'Estudante da Academia',
  `senjutsuhtml` varchar(20) NOT NULL DEFAULT '',
  `bancogeral` text NOT NULL DEFAULT 'None',
  `historico` text NOT NULL DEFAULT 'None',
  `bp1` varchar(60) NOT NULL DEFAULT 'None',
  `bp2` varchar(60) NOT NULL DEFAULT 'None',
  `bp3` varchar(60) NOT NULL DEFAULT 'None',
  `bp4` varchar(60) NOT NULL DEFAULT 'None',
  `bp5` varchar(60) NOT NULL DEFAULT 'None',
  `bp6` varchar(60) NOT NULL DEFAULT 'None',
  `bp7` varchar(60) NOT NULL DEFAULT 'None',
  `bp8` varchar(60) NOT NULL DEFAULT 'None',
  `bpimagem` varchar(10) NOT NULL DEFAULT 'backpack1',
  `trocajogador1` varchar(160) NOT NULL DEFAULT 'None',
  `trocajogador2` varchar(160) NOT NULL DEFAULT 'None',
  `trocaswitch` smallint(3) UNSIGNED NOT NULL DEFAULT 0,
  -- Prazo (UNIX) da troca em andamento; 0 = sem prazo.
  `trocaate` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `durabilidade` varchar(30) NOT NULL DEFAULT '-,X,X,X,X,X,X',
  `diamante` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pergaminho` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `acesso` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `ultimoinimigo` varchar(50) NOT NULL DEFAULT 'None',
  `treinamento` text NOT NULL DEFAULT 'None',
  `droprate` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `currentep` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `maxep` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `maxnp` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `currentnp` mediumint(10) UNSIGNED NOT NULL DEFAULT 0,
  `elemento` varchar(15) NOT NULL DEFAULT 'neutro,neutro',
  `senjutsutimer` varchar(30) NOT NULL DEFAULT 'None',
  `atkdefsenjutsu` varchar(20) NOT NULL DEFAULT '0,0',
  `alinhamento` varchar(10) NOT NULL DEFAULT 'folha',
  `questsaux` text NOT NULL DEFAULT 'None',
  `pagina` varchar(200) NOT NULL DEFAULT 'index.php',
  `ipadress` varchar(45) NOT NULL DEFAULT '',
  `missaoultdata` varchar(30) NOT NULL DEFAULT 'None',
  `elementoatk` varchar(10) NOT NULL DEFAULT 'neutro',
  `ultmonstro` varchar(60) NOT NULL DEFAULT 'None,0',
  `chancecaptura` int(6) UNSIGNED NOT NULL DEFAULT 0,
  `pmsnovas` smallint(1) UNSIGNED NOT NULL DEFAULT 0,
  `mainmsg` text NOT NULL DEFAULT 'None',
  -- Bônus de equipamentos (somados/retirados ao equipar): % de crítico, % de esquiva, HP por passo no mapa, % de economia de chakra.
  `critico` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `esquiva` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `regenhp` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `economiachakra` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  -- Moderação e contas: silenciado até (UNIX), troca de senha obrigatória, recuperação sem e-mail (hashes), liberação pelo GM,
  -- banimento com prazo (UNIX) e caveira do PvP ('branca'/'vermelha' até caveiraate).
  `mudoate` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `trocarsenha` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `pergunta1` varchar(150) NOT NULL DEFAULT '',
  `resposta1` varchar(255) NOT NULL DEFAULT '',
  `pergunta2` varchar(150) NOT NULL DEFAULT '',
  `resposta2` varchar(255) NOT NULL DEFAULT '',
  `codigorecuperacao` varchar(255) NOT NULL DEFAULT '',
  `resetliberado` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `banidoate` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `caveira` varchar(10) NOT NULL DEFAULT '',
  `caveiraate` int(10) UNSIGNED NOT NULL DEFAULT 0,
  -- Incenso Repelente: sem inimigos aleatórios até repelente_ate (UNIX) ou enquanto restarem repelente_passos.
  `repelente_ate` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `repelente_passos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  -- Quadrantes (20x20) do mapa-múndi já descobertos: 100 caracteres hexadecimais = 400 bits (funcoesneblina.php).
  `mapadescoberto` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `charname` (`charname`),
  KEY `email` (`email`),
  KEY `local` (`latitude`,`longitude`),
  KEY `onlinetime` (`onlinetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
"""
W(SCHEMA)

# ---- dados -----------------------------------------------------------------
W("-- Configuração inicial (o instalador atualiza nome, URL e e-mail).")
W("INSERT INTO `naruto_control` (`id`, `gamename`, `gamesize`, `gameopen`, `gameurl`, `adminemail`, `forumtype`, `forumaddress`, `class1name`, `class2name`, `class3name`, `diff1name`, `diff1mod`, `diff2name`, `diff2mod`, `diff3name`, `diff3mod`, `compression`, `verifyemail`, `shownews`, `showbabble`, `showonline`, `allowregister`, `loginmsg`) VALUES\n"
  "(1, 'Naruto! Nigeru RPG', 400, 1, 'http://localhost/index.php', 'admin@localhost', 1, '', 'Ninjutsu', 'Taijutsu', 'Genjutsu', 'Fácil', 1, 'Médio', 1.2, 'Difícil', 1.5, 0, 0, 1, 1, 1, 1, 'Bem-vindo ao Naruto! Nigeru RPG. Crie sua conta, escolha sua especialização e comece sua jornada ninja!');\n")

for t in ['drops','items','levels','spells','towns']:
    cols, vals = inserts(t)
    vals = fix(vals)
    if t == 'towns':
        vals = vals.replace("'HIHI'", "'Guardião Real'").replace("'29.30'", "'29,30'").replace("'2,3,4,5,18,19,20,29.30'", "'2,3,4,5,18,19,20,29,30'")
    if t == 'items':
        vals = vals.replace("'maxhp, 30'", "'maxhp,30'")
    W(f"INSERT INTO `naruto_{t}` {cols} VALUES\n{vals};\n")
    if t == 'towns':
        W("""-- Regras das vilas que antes eram fixas no código (cidadesconteudo.php, fight.php, graduacao.php...).
UPDATE `naruto_towns` SET `name_en`=ELT(`id`,'Leaf Village','Mount Myoboku','Stone Village','Mist Village','Sand Village','Cloud Village','Heaven and Earth Hamlet','Akatsuki Hideout','Village of Wisdom','Royal Castle'), `kage_en`=ELT(`id`,'Hokage','Fukasaku','Tsuchikage','Mizukage','Kazekage','Raikage','Shodaime','Uchiha Madara','Shinigure','Royal Guardian') WHERE `id` BETWEEN 1 AND 10;
UPDATE `naruto_towns` SET `alinhamento`=ELT(`id`,'folha','','pedra','nevoa','areia','nuvem'), `vilanatal`=1 WHERE `id` IN (1,3,4,5,6);
UPDATE `naruto_towns` SET `tem_graduacao`=1 WHERE `id` IN (1,3);
UPDATE `naruto_towns` SET `tem_alquimia`=1 WHERE `id` IN (2,5);
UPDATE `naruto_towns` SET `tem_senjutsu`=1 WHERE `id`=2;
UPDATE `naruto_towns` SET `tem_jutsubusca`=1 WHERE `id`=5;
UPDATE `naruto_towns` SET `tem_mapas`=0 WHERE `id`=8;
UPDATE `naruto_towns` SET `secreta`=1 WHERE `id` IN (8,9,10);

INSERT INTO `naruto_terrenos` (`id`, `nome`, `imagem`, `latmin`, `latmax`, `lonmin`, `lonmax`, `prioridade`, `arvores`) VALUES
(1, 'grama', 'terreno_grama.jpg', -300, 300, -300, 300, 0, 1),
(2, 'floresta', 'terreno_floresta.jpg', -70, 90, -80, 130, 10, 1),
(3, 'deserto', 'terreno_deserto.jpg', -300, -60, -300, -50, 20, 1),
(4, 'rochoso', 'terreno_rochoso.jpg', 70, 210, -300, -20, 20, 1),
(5, 'neve', 'terreno_neve.jpg', 210, 300, -300, -20, 25, 1),
(6, 'costa', 'terreno_costa.jpg', -60, 120, 140, 300, 15, 0),
(7, 'costa', 'terreno_costa.jpg', 230, 300, 30, 160, 15, 0),
(8, 'costa', 'terreno_costa.jpg', -300, -220, -10, 190, 15, 0),
(9, 'pantano', 'terreno_pantano.jpg', -180, -60, 40, 170, 20, 1),
(10, 'trilha', 'terreno_trilha.jpg', -2, 2, -120, 120, 12, 1),
(11, 'trilha', 'terreno_trilha.jpg', -150, 150, -2, 2, 12, 1),
-- Anel externo do mapa de 400 casas (300 a 400): geleira ao norte, mar gelado no nordeste, mar com ilhas a leste
-- (e ilhas de mangue), terras vulcânicas ao sul, ermos de cânions no sudoeste e o grande deserto com cânions a oeste.
(12, 'neve', 'terreno_neve.jpg', 300, 400, -400, 250, 25, 1),
(13, 'costa', 'terreno_costa.jpg', 300, 400, 250, 400, 15, 0),
(14, 'costa', 'terreno_costa.jpg', -300, 300, 300, 400, 15, 0),
(15, 'pantano', 'terreno_pantano.jpg', -200, -120, 315, 385, 18, 1),
(16, 'vulcanico', 'terreno_vulcanico.jpg', -400, -300, -250, 400, 30, 1),
(17, 'rochoso', 'terreno_rochoso.jpg', -400, -250, -400, -250, 22, 1),
(18, 'deserto', 'terreno_deserto.jpg', -250, 300, -400, -300, 20, 1),
(19, 'rochoso', 'terreno_rochoso.jpg', -100, 150, -400, -335, 22, 1);
""")

# Monstros originais + novos
cols, vals = inserts('monsters')
vals = fix(vals)
rows = re.findall(r"\((\d+), '((?:[^'\\]|\\.)*)', (\d+), (\d+), (\d+), (\d+), (\d+), (\d+), (\d+), '(\w+)'\)", vals)
bylevel = collections.defaultdict(list)
for r in rows:
    if r[8] != '2': bylevel[int(r[5])].append([int(x) for x in (r[2], r[3], r[4], r[6], r[7])])
NOVOS = [  # (id, nome, level, elemento, imune)
 (152, "Rouga, o Domador de Feras", 13, 'terra', 0),
 (153, "Kunoichi Errante da Névoa", 18, 'agua', 0),
 (154, "Kunoichi Desertora da Folha", 11, 'fogo', 0),
 (155, "Águia Invocada do Deserto", 22, 'vento', 0),
 (156, "Yuurei, a Kunoichi Sombria", 24, 'neutro', 1),
 (157, "Monge Guerreiro do Templo", 14, 'terra', 0),
 (158, "Fera Selada da Montanha", 33, 'terra', 0),
 (159, "Marionete Ancestral", 39, 'neutro', 1),
 (160, "Kunoichi Mestra de Armas", 5, 'neutro', 0),
 (161, "Guerreiro-Pássaro de Myoboku", 27, 'vento', 0),
 (162, "Urso Pardo das Montanhas", 4, 'terra', 0),
 (163, "Mercenário Costurado", 35, 'raio', 0),
 (164, "Sacerdotisa do Templo do Fogo", 29, 'fogo', 0),
 (165, "Prodígio Renegado", 45, 'raio', 0),
 (166, "Kunoichi Médica Errante", 9, 'agua', 0),
]
novos_sql = []
for (i, nome, lv, el, im) in NOVOS:
    ref = bylevel[lv]
    avg = [round(sum(c[k] for c in ref) / len(ref)) for k in range(5)]
    hp, dam, arm, exp, gold = avg
    # pequena variação para cada monstro ter personalidade
    hp = round(hp * 1.05); dam = round(dam * 1.02)
    if im: exp = round(exp * 1.1)
    novos_sql.append(f"({i}, '{nome}', {hp}, {dam}, {arm}, {lv}, {exp}, {gold}, {im}, '{el}')")
vals = vals.rstrip() + ",\n" + ",\n".join(novos_sql)
W(f"-- Monstros 1-151 (originais) e 152-166 (novos, com cartas a partir do monstros.psd).\nINSERT INTO `naruto_monsters` {cols} VALUES\n{vals};\n")

# ---- Itens, drops e inimigos novos (2026) + tabela de drops por inimigo ------------------------------
# Itens 47-66: equipamentos de loja com os bônus novos (critico, esquiva, regenhp, economiachakra; até 3 por item).
ITENS_NOVOS = [  # (id, tipo, nome, preço, ataque/defesa, special, special2, special3)
 (47, 1, "Faca de Aço Temperado", 120, 10, "critico,2", "X", "X"),
 (48, 1, "Shuriken Serrilhada", 260, 18, "critico,3", "X", "X"),
 (49, 1, "Corrente com Foice", 650, 36, "critico,3", "precisao,10", "X"),
 (50, 1, "Tantō de Lâmina Negra", 1500, 52, "critico,5", "agilidade,10", "X"),
 (51, 1, "Garras de Ferro", 2600, 70, "critico,6", "esquiva,2", "X"),
 (52, 1, "Leque de Ferro Condutor", 4500, 95, "economiachakra,15", "maxmp,30", "X"),
 (53, 1, "Lâmina Dentada de Guerra", 12000, 140, "critico,8", "strength,20", "X"),
 (54, 1, "Espada Longa do Andarilho", 35000, 190, "critico,10", "regenhp,3", "expbonus,5"),
 (55, 2, "Colete de Couro Reforçado", 150, 15, "regenhp,1", "X", "X"),
 (56, 2, "Gibão Acolchoado", 600, 40, "esquiva,2", "X", "X"),
 (57, 2, "Túnica do Monge Errante", 1400, 60, "economiachakra,10", "regenhp,1", "X"),
 (58, 2, "Armadura Lamelar", 3800, 140, "maxhp,40", "regenhp,2", "X"),
 (59, 2, "Capa de Viagem das Sombras", 6000, 130, "esquiva,6", "agilidade,20", "X"),
 (60, 2, "Cota de Malha Negra", 45000, 210, "regenhp,5", "maxhp,60", "esquiva,3"),
 (61, 3, "Faixa de Testa Reforçada", 150, 7, "critico,1", "X", "X"),
 (62, 3, "Chapéu de Palha do Viajante", 400, 12, "regenhp,1", "maxtp,5", "X"),
 (63, 3, "Máscara de Ferro", 1800, 30, "esquiva,3", "X", "X"),
 (64, 3, "Elmo de Ferro Batido", 3000, 45, "maxhp,25", "X", "X"),
 (65, 3, "Tiara de Concentração", 8000, 55, "economiachakra,20", "inteligencia,15", "X"),
 (66, 3, "Kabuto do Comandante", 28000, 105, "critico,4", "esquiva,3", "maxhp,30"),
 # 67-84: segundo lote (ícones originais em layoutnovo/equipamentos/novos/).
 (67, 1, "Adagas Gêmeas", 350, 22, "critico,2", "agilidade,5", "X"),
 (68, 1, "Lança de Borla Vermelha", 900, 40, "precisao,15", "X", "X"),
 (69, 1, "Corrente com Peso", 1100, 44, "esquiva,1", "strength,10", "X"),
 (70, 1, "Bastão de Ferro", 1800, 58, "maxhp,20", "regenhp,1", "X"),
 (71, 1, "Lâmina Curva do Deserto", 3500, 85, "critico,4", "X", "X"),
 (72, 1, "Estrela de Três Pontas", 80, 7, "precisao,5", "X", "X"),
 (73, 1, "Estrela de Duas Pontas", 60, 6, "critico,1", "X", "X"),
 (74, 1, "Besta Compacta", 7000, 115, "precisao,30", "critico,3", "X"),
 (75, 1, "Soqueira de Aço", 450, 25, "strength,10", "X", "X"),
 (76, 1, "Clava de Osso", 2200, 66, "maxhp,30", "critico,2", "X"),
 (77, 1, "Adaga de Obsidiana", 18000, 160, "critico,7", "economiachakra,10", "X"),
 (78, 2, "Jaqueta Acolchoada", 250, 20, "maxhp,10", "X", "X"),
 (79, 2, "Túnica Camuflada", 2500, 95, "esquiva,4", "X", "X"),
 (80, 2, "Robe de Seda", 5000, 110, "economiachakra,15", "maxmp,40", "X"),
 (81, 2, "Casaco de Pele", 1600, 70, "regenhp,2", "maxhp,20", "X"),
 (82, 2, "Armadura de Bambu", 700, 45, "esquiva,1", "regenhp,1", "X"),
 (83, 3, "Capuz Negro", 4500, 50, "esquiva,4", "critico,2", "X"),
 (84, 3, "Escudo de Madeira", 300, 14, "maxhp,15", "X", "X"),
]
# Onde cada item novo é vendido (id da vila => itens acrescentados ao itemslist).
LOJAS_NOVAS = {1: "47,55,61,72,73,78", 2: "57,62,69,82", 3: "48,56,68,75,81,84", 4: "49,58,63,76,79", 5: "50,59,70,71,83", 6: "51,64,66,74,80", 7: "52,53,65,77", 8: "54,60"}
# Drops 51-60: materiais equipáveis nos slots de drop (até 3 atributos).
DROPS_NOVOS = [  # (id, nome, nível mínimo, atributo1, atributo2, atributo3[, consumível])
 (51, "Erva Medicinal Seca", 1, "regenhp,1", "X", "X"),
 (52, "Pó de Ferro Meteórico", 5, "critico,1", "strength,5", "X"),
 (53, "Bomba de Fumaça", 8, "esquiva,1", "agilidade,5", "X"),
 (54, "Pergaminho Selado", 12, "economiachakra,5", "X", "X"),
 (55, "Escama de Serpente do Pântano", 18, "defensepower,15", "esquiva,1", "X"),
 (56, "Garra de Fera Alfa", 22, "attackpower,20", "critico,2", "X"),
 (57, "Colar de Contas de Chakra", 28, "economiachakra,8", "maxmp,30", "X"),
 (58, "Poção Verde de Soldado", 32, "regenhp,3", "maxhp,30", "X"),
 (59, "Cristal Azul de Eco", 40, "critico,3", "esquiva,2", "precisao,20"),
 (60, "Amuleto de Jade Errante", 48, "economiachakra,10", "regenhp,4", "maxmp,60"),
 # 61-86: segundo lote. Acessórios e materiais equipáveis; 66-70 são consumíveis (poção de HP/CH na mochila).
 (61, "Anel de Rubi", 30, "maxhp,60", "critico,2", "X"),
 (62, "Braçadeiras de Couro", 10, "defensepower,10", "esquiva,1", "X"),
 (63, "Botas de Couro", 6, "maxtp,5", "esquiva,1", "X"),
 (64, "Bolsa de Ferramentas", 15, "droprate,3", "goldbonus,3", "X"),
 (65, "Estojo de Couro", 20, "maxmp,25", "economiachakra,3", "X"),
 (66, "Poção Vermelha", 1, "X", "X", "X", "hp,50"),
 (67, "Poção Azul", 1, "X", "X", "X", "mp,40"),
 (68, "Elixir Dourado", 30, "X", "X", "X", "hp,300"),
 (69, "Bolinho de Arroz", 1, "X", "X", "X", "hp,25"),
 (70, "Peixe Seco", 5, "X", "X", "X", "hp,35"),
 (71, "Presa de Lobo", 8, "attackpower,8", "X", "X"),
 (72, "Garra de Fera", 14, "attackpower,12", "critico,1", "X"),
 (73, "Escamas Reluzentes", 18, "defensepower,18", "X", "X"),
 (74, "Pena Grande", 12, "agilidade,10", "esquiva,1", "X"),
 (75, "Minério de Ferro", 3, "defensepower,4", "X", "X"),
 (76, "Lingote de Prata", 26, "goldbonus,8", "X", "X"),
 (77, "Cogumelo Roxo", 7, "maxmp,15", "maxhp,-5", "X"),
 (78, "Carretel de Seda", 16, "dexterity,15", "X", "X"),
 (79, "Moeda de Bronze Antiga", 2, "goldbonus,3", "X", "X"),
 (80, "Baú Pequeno", 35, "goldbonus,10", "droprate,5", "X"),
 (81, "Gazuas", 11, "precisao,10", "sorte,10", "X"),
 (82, "Bússola", 9, "maxtp,10", "X", "X"),
 (83, "Lanterna de Papel", 13, "inteligencia,10", "regenhp,1", "X"),
 (84, "Gancho com Corda", 24, "agilidade,15", "maxtp,5", "X"),
 (85, "Corda de Cânhamo", 4, "dexterity,5", "X", "X"),
 (86, "Papel Explosivo", 22, "attackpower,15", "critico,2", "X"),
]
DROPS_NOVOS = [d if len(d) == 7 else d + ("",) for d in DROPS_NOVOS]
# Inimigos 167-186: atributos = mediana dos inimigos originais dos níveis vizinhos (L-1..L+1);
# acima do nível 50 (sem vizinhos) cresce a partir do nível 49. Imunes a dano: -5% de HP e +10% de exp.
# Região: (latmin, latmax, lonmin, lonmax, exclusiva) dentro da faixa de distância do nível ou None.
INIMIGOS_NOVOS = [  # (id, nome, nível, elemento, imune, região)
 (167, "Bandido de Estrada", 3, 'neutro', 0, None),
 (168, "Javali de Presas Largas", 6, 'terra', 0, (-39, 39, -39, -25, 0)),
 (169, "Batedor Desertor", 8, 'vento', 0, None),
 (170, "Lobo da Nevasca", 11, 'agua', 0, (50, 64, -64, 64, 0)),
 (171, "Espírito do Lampião", 14, 'fogo', 1, None),
 (172, "Contrabandista do Porto", 17, 'agua', 0, (-94, 94, 80, 94, 0)),
 (173, "Serpente do Pântano", 20, 'agua', 0, None),
 (174, "Ronin da Lâmina Partida", 23, 'vento', 0, None),
 (175, "Golem de Argila", 26, 'terra', 1, (-139, -125, -139, 139, 0)),
 (176, "Caçador de Recompensas", 29, 'raio', 0, None),
 (177, "Mariposa Venenosa Gigante", 32, 'vento', 0, None),
 (178, "Espectro da Floresta Antiga", 35, 'neutro', 1, (-184, 184, -184, -170, 0)),
 (179, "Assassino de Aluguel", 38, 'fogo', 0, None),
 (180, "Tigre de Listras Brancas", 41, 'raio', 0, None),
 (181, "Guardião de Pedra do Santuário", 44, 'terra', 1, None),
 (182, "Ninja Renegado das Cinzas", 47, 'fogo', 0, None),
 (183, "Dragão de Água do Lago", 50, 'agua', 0, (245, 259, -60, 60, 1)),
 (184, "Oni da Montanha Vulcânica", 53, 'fogo', 0, None),
 (185, "Espírito da Tempestade", 56, 'raio', 1, None),
 (186, "Senhor da Guerra Sem Vila", 59, 'neutro', 0, None),
]
def sqlq(x): return x.replace("'", "\\'")
def mediana(v):
    v = sorted(v); n = len(v)
    return v[n//2] if n % 2 else (v[n//2-1] + v[n//2]) / 2
NOVOS_SQL = ["-- Itens, drops e inimigos novos (ids 47-84, 51-86 e 167-186).",
 "INSERT INTO `naruto_items` (`id`, `type`, `name`, `buycost`, `attribute`, `special`, `special2`, `special3`) VALUES\n" +
 ",\n".join(f"({i}, {tp}, '{sqlq(n)}', {c}, {a}, '{s1}', '{s2}', '{s3}')" for (i, tp, n, c, a, s1, s2, s3) in ITENS_NOVOS) + ";",
 "INSERT INTO `naruto_drops` (`id`, `name`, `mlevel`, `type`, `attribute1`, `attribute2`, `attribute3`, `consumivel`) VALUES\n" +
 ",\n".join(f"({i}, '{sqlq(n)}', {lv}, 1, '{a1}', '{a2}', '{a3}', '{cs}')" for (i, n, lv, a1, a2, a3, cs) in DROPS_NOVOS) + ";"]
for vila, ids in LOJAS_NOVAS.items():
    NOVOS_SQL.append(f"UPDATE `naruto_towns` SET `itemslist`=TRIM(BOTH ',' FROM CONCAT(`itemslist`, ',{ids}')) WHERE `id`={vila};")
mons = []
todos_monstros = {}  # id => (nível, imune) de todos os inimigos (para a tabela de drops)
for r in rows: todos_monstros[int(r[0])] = (int(r[5]), int(r[8]))
for (i, nome, lv, el, im) in NOVOS: todos_monstros[i] = (lv, im)
for (i, nome, lv, el, im, reg) in INIMIGOS_NOVOS:
    viz = [c for l in range(lv-1, lv+2) for c in bylevel.get(l, [])]
    if lv <= 50 and viz:
        hp, dam, arm, exp, gold = [round(mediana([c[k] for c in viz])) for k in range(5)]
    else:  # além do nível 50: +12 HP, +10 dano, +8 defesa, +130 exp e +40 Ryou por nível acima do 49.
        base = [round(mediana([c[k] for c in bylevel[49]])) for k in range(5)]
        d = lv - 49
        hp, dam, arm, exp, gold = base[0] + 12*d, base[1] + 10*d, base[2] + 8*d, base[3] + 130*d, base[4] + 40*d
    if im: hp = round(hp * 0.95); exp = round(exp * 1.1)
    regsql = "NULL, NULL, NULL, NULL, 0" if reg is None else f"{reg[0]}, {reg[1]}, {reg[2]}, {reg[3]}, {reg[4]}"
    mons.append(f"({i}, '{sqlq(nome)}', {hp}, {dam}, {arm}, {lv}, {exp}, {gold}, {im}, '{el}', {regsql})")
    todos_monstros[i] = (lv, im)
NOVOS_SQL.append("INSERT INTO `naruto_monsters` (`id`, `name`, `maxhp`, `maxdam`, `armor`, `level`, `maxexp`, `maxgold`, `immune`, `elemento`, `regiao_latmin`, `regiao_latmax`, `regiao_lonmin`, `regiao_lonmax`, `regiao_exclusiva`) VALUES\n" + ",\n".join(mons) + ";")
# Tabela de drops: baseada na regra antiga (chance total ~4% por vitória, drops com nível mínimo <= nível do inimigo).
# Cada inimigo recebe até 3 drops entre os 6 de nível mínimo mais próximo do dele (escolha fixa pelo id),
# com 2%, 1,5% e 1% (total 4,5%). Chefes (imune 2) têm o dobro. Drops de missão (nível >= 100) ficam de fora.
drops_niveis = [(int(m.group(1)), int(m.group(2))) for m in re.finditer(r"\((\d+), '(?:[^'\\]|\\.)*', (\d+), \d+, '", fix(inserts('drops')[1]))]
drops_niveis += [(i, lv) for (i, n, lv, a1, a2, a3, cs) in DROPS_NOVOS]
md = []
for mid in sorted(todos_monstros):
    lv, im = todos_monstros[mid]
    cand = sorted([d for d in drops_niveis if d[1] <= lv and d[1] < 100], key=lambda d: (-d[1], d[0]))[:6]
    if not cand: continue
    n = len(cand); esc = []
    for k in (mid, mid*7+3, mid*13+5):
        d = cand[k % n][0]
        if d not in esc: esc.append(d)
    for d, ch in zip(esc, (2.0, 1.5, 1.0)):
        md.append(f"({mid}, {d}, {ch * (2 if im == 2 else 1):.2f})")
# Consumíveis e o Baú Pequeno entram por faixa de nível (fora da escolha acima, que prefere drops do nível do inimigo):
# Bolinho de Arroz 3% (nível <= 10), Poção Vermelha 3% e Poção Azul 2% (nível < 30), Elixir Dourado 1,5% (nível >= 30),
# Baú Pequeno 0,5% (nível >= 35).
for mid in sorted(todos_monstros):
    lv, im = todos_monstros[mid]
    extras = []
    if lv <= 10: extras.append((69, 3.0))
    if lv < 30: extras += [(66, 3.0), (67, 2.0)]
    else: extras.append((68, 1.5))
    if lv >= 35: extras.append((80, 0.5))
    for d, ch in extras: md.append(f"({mid}, {d}, {ch:.2f})")
NOVOS_SQL.append("-- Drops por inimigo (editáveis no painel: Inimigos > editar > Drops).\nINSERT INTO `naruto_monster_drops` (`monster_id`, `drop_id`, `chance_percent`) VALUES\n" + ",\n".join(md) + ";")
W("\n".join(NOVOS_SQL) + "\n")

# ---- Terrenos dos inimigos + terceiro lote de inimigos originais (187-206) ----------------------------------
# Inimigos genéricos (feras, criaturas) ganham terrenos; os da história/com nome continuam sem restrição.
# Só terrenos que existem dentro da faixa de distância do nível (nível x 5 casas do centro), senão nunca apareceriam.
TERRENOS_ANTIGOS = {
 1: "floresta,grama,trilha", 2: "floresta,grama", 3: "floresta,grama", 4: "floresta,grama", 32: "floresta,grama",
 49: "floresta,grama", 50: "floresta,grama", 162: "floresta,grama", 168: "floresta,grama", 167: "trilha,grama", 19: "trilha,grama",
 44: "floresta,pantano", 57: "floresta,pantano", 64: "floresta,pantano", 65: "floresta,pantano", 173: "pantano",
 177: "pantano,grama", 155: "deserto,rochoso", 175: "deserto,rochoso", 158: "neve,rochoso", 180: "neve,rochoso",
 181: "rochoso", 184: "rochoso",
}
TERRENOS_SQL = ["-- Terrenos em que os inimigos genéricos aparecem (coluna terrenos; vazia = qualquer terreno)."]
for mid, ter in sorted(TERRENOS_ANTIGOS.items()):
    TERRENOS_SQL.append(f"UPDATE `naruto_monsters` SET `terrenos`='{ter}' WHERE `id`={mid};")
# Terceiro lote: atributos pela mesma regra dos 167-186 (mediana dos níveis vizinhos; acima do 50 cresce a partir do 49).
# Nível máximo 61: com o mapa de 300 casas a faixa de nível mais distante é 59-61, então 62+ nunca apareceria.
INIMIGOS_NOVOS2 = [  # (id, nome, nível, elemento, imune, terrenos, drops [(drop, chance %, variante rara %)])
 (187, "Matilha de Cães Ninja", 4, 'neutro', 0, "floresta,grama,trilha", [(85, 2.0, 0.5), (75, 1.5, 0.5), (1, 1.0, 0.5)]),
 (188, "Caranguejo Gigante do Rio", 7, 'agua', 0, "floresta,grama", [(70, 3.0, 0), (75, 1.5, 0.5), (52, 1.0, 0.5)]),
 (189, "Raposa Espiritual do Bambuzal", 10, 'fogo', 1, "floresta", [(77, 2.0, 0.5), (9, 1.5, 1.0), (5, 1.0, 0.5)]),
 (190, "Saqueador das Dunas", 13, 'terra', 0, "deserto", [(36, 2.0, 0.5), (81, 1.5, 0.5), (82, 1.0, 1.0)]),
 (191, "Emboscador do Pântano Enevoado", 16, 'agua', 0, "pantano", [(53, 2.0, 0.5), (54, 1.5, 0.5), (38, 1.0, 0.5)]),
 (192, "Operativo Mascarado", 19, 'neutro', 0, "floresta,rochoso,trilha", [(53, 2.0, 0.5), (78, 1.5, 0.5), (64, 1.0, 0.5)]),
 (193, "Kappa do Brejo", 21, 'agua', 0, "pantano", [(70, 3.0, 0), (55, 2.0, 0.5), (65, 1.0, 0.5)]),
 (194, "Escorpião Gigante das Areias", 24, 'terra', 0, "deserto", [(73, 2.0, 1.0), (56, 1.5, 0.5), (36, 1.0, 0.5)]),
 (195, "Sapo de Guerra Gigante", 27, 'agua', 0, "pantano,costa", [(70, 3.0, 0), (39, 2.0, 0.5), (3, 1.5, 0.5)]),
 (196, "Falcão de Guerra", 30, 'vento', 0, "rochoso,deserto", [(74, 3.0, 1.0), (22, 1.5, 0.5), (84, 1.0, 0.5)]),
 (197, "Autômato de Combate", 33, 'neutro', 1, "rochoso,deserto,grama", [(78, 2.0, 0.5), (76, 1.5, 0.5), (58, 1.0, 0.5)]),
 (198, "Lesma Encouraçada", 36, 'agua', 0, "pantano,costa", [(73, 2.0, 0.5), (58, 1.5, 0.5), (19, 1.0, 0.5)]),
 (199, "Espírito do Pergaminho Selado", 39, 'raio', 1, "grama,rochoso", [(54, 3.0, 1.0), (14, 1.5, 0.5), (57, 1.0, 0.5)]),
 (200, "Sentinela do Pico das Nuvens", 42, 'raio', 0, "rochoso,neve", [(84, 2.0, 0.5), (34, 1.5, 0.5), (59, 1.0, 0.5)]),
 (201, "Armadura Amaldiçoada", 45, 'terra', 1, "rochoso,grama", [(62, 2.0, 1.0), (15, 1.5, 0.5), (16, 1.0, 0.5)]),
 (202, "Fera Peluda das Neves", 48, 'agua', 0, "neve", [(56, 2.0, 0.5), (24, 1.5, 0.5), (60, 1.0, 0.5)]),
 (203, "Serpente Gigante da Maré", 51, 'agua', 0, "costa", [(73, 2.0, 1.0), (30, 1.5, 0.5), (28, 1.0, 0.5)]),
 (204, "Mercenário da Tundra", 54, 'vento', 0, "neve", [(29, 1.5, 0.5), (61, 1.0, 0.5), (80, 1.0, 0.5)]),
 (205, "Tartaruga Colossal da Costa", 57, 'agua', 0, "costa", [(25, 1.5, 0.5), (73, 2.0, 1.0), (26, 1.0, 0.5)]),
 (206, "Golem de Obsidiana", 61, 'fogo', 1, "rochoso,grama,neve", [(20, 1.5, 0.5), (33, 1.0, 1.0), (27, 1.0, 0.5)]),
]
mons2, md2 = [], []
for (i, nome, lv, el, im, ter, drops) in INIMIGOS_NOVOS2:
    viz = [c for l in range(lv-1, lv+2) for c in bylevel.get(l, [])]
    if lv <= 50 and viz:
        hp, dam, arm, exp, gold = [round(mediana([c[k] for c in viz])) for k in range(5)]
    else:
        base = [round(mediana([c[k] for c in bylevel[49]])) for k in range(5)]
        d = lv - 49
        hp, dam, arm, exp, gold = base[0] + 12*d, base[1] + 10*d, base[2] + 8*d, base[3] + 130*d, base[4] + 40*d
    if im: hp = round(hp * 0.95); exp = round(exp * 1.1)
    mons2.append(f"({i}, '{sqlq(nome)}', {hp}, {dam}, {arm}, {lv}, {exp}, {gold}, {im}, '{el}', '{ter}')")
    # Drops temáticos + consumíveis/Baú pela mesma regra de faixa de nível dos outros inimigos.
    extras = [(69, 3.0, 0)] if lv <= 10 else []
    extras += [(66, 3.0, 0), (67, 2.0, 0)] if lv < 30 else [(68, 1.5, 0)]
    if lv >= 35: extras.append((80, 0.5, 0.5))
    vistos = set()
    for (d, ch, var) in drops + extras:
        if d in vistos: continue
        vistos.add(d)
        md2.append(f"({i}, {d}, {ch:.2f}, {var:.2f})")
TERRENOS_SQL.append("-- Inimigos 187-206 (terceiro lote, originais; a carta usa padrao.jpg até ser desenhada).\n"
 "INSERT INTO `naruto_monsters` (`id`, `name`, `maxhp`, `maxdam`, `armor`, `level`, `maxexp`, `maxgold`, `immune`, `elemento`, `terrenos`) VALUES\n" + ",\n".join(mons2) + ";")
TERRENOS_SQL.append("INSERT INTO `naruto_monster_drops` (`monster_id`, `drop_id`, `chance_percent`, `chance_variante_percent`) VALUES\n" + ",\n".join(md2) + ";")
W("\n".join(TERRENOS_SQL) + "\n")

# ---- Quarto lote: conteúdo de fim de jogo para o anel externo do mapa de 400 casas (níveis 62-80) ------------
# Com gamesize 400 a faixa mais distante é 79-81 (nível máximo floor((400+5)/5) = 81). Inimigos originais (sem
# personagens/clãs/técnicas de obras existentes). Atributos: mesma reta dos 167-206 a partir do nível 49
# (+12 HP, +10 dano, +8 defesa, +130 exp e +40 Ryou por nível), e acima do 61 HP/defesa crescem mais 1% por nível,
# exp 3% e Ryou 2% (recompensa de fim de jogo). Imune 1: -5% HP, +10% exp. Chefes (imune 2): HP x5, exp e Ryou x3.
DROPS_NOVOS3 = [  # (id, nome, nível mínimo, atributo1, atributo2, atributo3) — só caem dos inimigos 207-221
 (87, "Presa da Geleira Eterna", 62, "attackpower,170", "critico,4", "X"),
 (88, "Lâmina das Dunas Brancas", 63, "attackpower,160", "precisao,40", "critico,3"),
 (89, "Carapaça do Caranguejo-Rei", 64, "defensepower,170", "maxhp,80", "X"),
 (90, "Coração de Magma", 65, "strength,170", "regenhp,5", "X"),
 (91, "Diadema de Gelo Eterno", 66, "maxmp,180", "economiachakra,12", "inteligencia,25"),
 (92, "Elmo do Guardião do Cânion", 67, "defensepower,150", "esquiva,4", "maxhp,100"),
 (93, "Escama da Hidra do Manguezal", 68, "maxhp,200", "regenhp,6", "X"),
 (94, "Manto do Senhor da Guerra", 70, "defensepower,190", "critico,4", "expbonus,10"),
 (95, "Olho da Tempestade de Areia", 71, "esquiva,6", "agilidade,40", "dexterity,120"),
 (96, "Kunai Rúnica de Elite", 72, "attackpower,200", "critico,6", "X"),
 (97, "Pena de Fogo Eterno", 74, "regenhp,8", "maxhp,150", "economiachakra,10"),
 (98, "Pérola Abissal", 75, "maxmp,250", "economiachakra,15", "droprate,8"),
 (99, "Punho do Titã", 77, "strength,220", "attackpower,150", "critico,5"),
 (100, "Presa de Marfim do Mamute", 78, "attackpower,180", "maxhp,150", "esquiva,3"),
 (101, "Coração do Dragão Ancestral", 80, "attackpower,220", "defensepower,220", "critico,6"),
]
# (id, nome, nível, elemento, imune, terrenos, drops [(drop, chance %, variante rara %)])
INIMIGOS_NOVOS3 = [
 (207, "Lobo Gigante da Geleira Eterna", 62, 'agua', 0, "neve,costa,rochoso", [(87, 1.5, 3.0), (91, 0.5, 3.0), (25, 1.5, 2.0)]),
 (208, "Esquadrão Renegado das Dunas Brancas", 63, 'vento', 0, "deserto,rochoso,vulcanico", [(88, 1.5, 3.0), (95, 0.5, 3.0), (28, 1.5, 2.0)]),
 (209, "Caranguejo-Rei dos Recifes", 64, 'agua', 0, "costa,pantano", [(89, 1.5, 3.0), (93, 0.5, 3.0), (30, 1.5, 2.0)]),
 (210, "Salamandra de Magma", 65, 'fogo', 0, "vulcanico,rochoso,deserto", [(90, 1.5, 3.0), (97, 0.5, 3.0), (27, 1.5, 2.0)]),
 (211, "Elemental de Gelo Colossal", 66, 'agua', 1, "neve,costa", [(91, 1.5, 3.0), (87, 0.75, 3.0), (26, 1.5, 2.0)]),
 (212, "Guardião Ancestral do Cânion", 67, 'terra', 1, "rochoso,deserto,vulcanico", [(92, 1.5, 3.0), (88, 0.75, 3.0), (30, 1.5, 2.0)]),
 (213, "Hidra do Manguezal", 68, 'agua', 0, "pantano,costa,neve", [(93, 1.5, 3.0), (89, 0.75, 3.0), (25, 1.5, 2.0)]),
 (214, "Senhor da Guerra Amaldiçoado", 70, 'neutro', 1, "vulcanico,rochoso,neve,deserto", [(94, 1.5, 3.0), (90, 0.75, 3.0), (29, 1.5, 2.0)]),
 (215, "Tempestade de Areia Viva", 71, 'vento', 1, "deserto,rochoso,pantano", [(95, 1.5, 3.0), (92, 0.75, 3.0), (28, 1.5, 2.0)]),
 (216, "Esquadrão de Elite dos Mares Gelados", 72, 'raio', 0, "costa,neve,pantano", [(96, 1.5, 3.0), (94, 0.75, 3.0), (29, 1.5, 2.0)]),
 (217, "Ave de Fogo das Cinzas", 74, 'fogo', 0, "vulcanico,deserto,rochoso", [(97, 1.5, 3.0), (96, 0.75, 3.0), (27, 1.5, 2.0)]),
 (218, "Leviatã das Profundezas", 75, 'agua', 2, "costa,pantano", [(98, 3.0, 5.0), (93, 1.5, 4.0), (26, 2.0, 2.0)]),
 (219, "Titã de Pedra dos Ermos", 77, 'terra', 2, "rochoso,deserto,vulcanico,neve", [(99, 3.0, 5.0), (92, 1.5, 4.0), (30, 2.0, 2.0)]),
 (220, "Mamute de Guerra da Tundra", 78, 'terra', 0, "neve,costa,rochoso,pantano", [(100, 1.5, 3.0), (96, 0.75, 3.0), (25, 1.5, 2.0)]),
 (221, "Dragão Ancestral do Vulcão", 80, 'fogo', 2, "vulcanico,rochoso,deserto,costa,neve", [(101, 3.0, 5.0), (99, 1.0, 4.0), (97, 1.5, 4.0)]),
]
def atributos_fim_de_jogo(lv, im):
    """(hp, dano, defesa, exp, Ryou) de um inimigo de fim de jogo (nível 62+)."""
    d, e = lv - 49, max(0, lv - 61)
    hp, dam, arm, exp, gold = 330 + 12 * d, 300 + 10 * d, 250 + 8 * d, 2000 + 130 * d, 667 + 40 * d
    hp, arm, exp, gold = hp * (1 + 0.01 * e), arm * (1 + 0.01 * e), exp * (1 + 0.03 * e), gold * (1 + 0.02 * e)
    if im == 1: hp, exp = hp * 0.95, exp * 1.1
    if im == 2: hp, exp, gold = hp * 5, exp * 3, gold * 3
    return [round(v) for v in (hp, dam, arm, exp, gold)]
mons3, md3 = [], []
for (i, nome, lv, el, im, ter, drops) in INIMIGOS_NOVOS3:
    hp, dam, arm, exp, gold = atributos_fim_de_jogo(lv, im)
    mons3.append(f"({i}, '{sqlq(nome)}', {hp}, {dam}, {arm}, {lv}, {exp}, {gold}, {im}, '{el}', '{ter}')")
    for (dd, ch, var) in drops + [(68, 1.5, 0), (80, 0.5, 2.0)]:  # + Elixir Dourado e Baú Pequeno (regra de faixa)
        md3.append(f"({i}, {dd}, {ch:.2f}, {var:.2f})")
FIM_SQL = ["-- Quarto lote (fim de jogo, níveis 62-80, anel externo do mapa de 400 casas): drops 87-101 e inimigos 207-221.",
 "INSERT INTO `naruto_drops` (`id`, `name`, `mlevel`, `type`, `attribute1`, `attribute2`, `attribute3`, `consumivel`) VALUES\n" +
 ",\n".join(f"({i}, '{sqlq(n)}', {lv}, 1, '{a1}', '{a2}', '{a3}', '')" for (i, n, lv, a1, a2, a3) in DROPS_NOVOS3) + ";",
 "INSERT INTO `naruto_monsters` (`id`, `name`, `maxhp`, `maxdam`, `armor`, `level`, `maxexp`, `maxgold`, `immune`, `elemento`, `terrenos`) VALUES\n" + ",\n".join(mons3) + ";",
 "INSERT INTO `naruto_monster_drops` (`monster_id`, `drop_id`, `chance_percent`, `chance_variante_percent`) VALUES\n" + ",\n".join(md3) + ";"]
W("\n".join(FIM_SQL) + "\n")
if len(sys.argv) > 3: open(sys.argv[3], 'w', encoding='utf8').write("SET NAMES utf8mb4;\n" + "\n".join(FIM_SQL) + "\n")  # só o quarto lote (banco já instalado)

W("""-- Notícia inicial.
INSERT INTO `naruto_news` (`id`, `postdate`, `content`) VALUES
(1, NOW(), 'Bem-vindos ao Naruto! Nigeru RPG! Explore o mapa, complete missões, aprenda jutsus, gradue-se na sua vila e enfrente os ninjas mais poderosos do mundo shinobi. Bom jogo!');
""")
# Missões no banco (história 1-21 migrada do código + 12 extras de exemplo) e NPCs do mapa (1-6 da história, 7-10 das extras).
# Gerado a partir de missoes.php/treinamentoequests.php/falapersonagens.php + lang/en/missoes.php (colunas _en).
MISSOES_SQL = r"""
-- Missões (história e extras) guardadas no banco. categoria 'historia' = missões obrigatórias (users.missao = "etapa,feitos,total,intervalo"),
-- categoria 'extra' = missões opcionais (progresso em naruto_missoesextras_jogador).
CREATE TABLE IF NOT EXISTS `naruto_missoesextras` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `categoria` enum('historia','extra') NOT NULL DEFAULT 'extra',
  `etapa` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `titulo` varchar(150) NOT NULL DEFAULT '',
  `titulo_en` varchar(150) NOT NULL DEFAULT '',
  `descricao` text NOT NULL,
  `descricao_en` text NOT NULL,
  `descricao_arg` varchar(100) NOT NULL DEFAULT '',
  `recompensa_texto` varchar(255) NOT NULL DEFAULT '',
  `recompensa_texto_en` varchar(255) NOT NULL DEFAULT '',
  `recompensa_arg` varchar(100) NOT NULL DEFAULT '',
  `rank_texto` varchar(30) NOT NULL DEFAULT '',
  `local_texto` varchar(100) NOT NULL DEFAULT '',
  `mapa_item` varchar(60) NOT NULL DEFAULT '',
  `mapa_local` varchar(20) NOT NULL DEFAULT '',
  `mapa_monstro` varchar(60) NOT NULL DEFAULT '',
  `npc_nome` varchar(50) NOT NULL DEFAULT '',
  `npc_avatar` varchar(30) NOT NULL DEFAULT '1',
  `npc_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `vila_id` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `nivel_minimo` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `graduacao_minima` varchar(30) NOT NULL DEFAULT '',
  `tipo` enum('entregar_item','derrotar_monstro','visitar_local','pagar_ryou','falar_com_npc','repetir','especial','cadeia','viagem','coletar','sobreviver','escolha') NOT NULL DEFAULT 'entregar_item',
  `alvo` varchar(100) NOT NULL DEFAULT '',
  `quantidade` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `intervalo_min` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `gancho` varchar(30) NOT NULL DEFAULT '',
  `recompensa_exp` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `recompensa_ryou` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `recompensa_item` varchar(60) NOT NULL DEFAULT '',
  `recompensa_jutsu` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'spells.id: ensina, ou pergaminho, ou Ryou+exp (mx_recompensajutsu)',
  `repetivel` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `intervalo_horas` int(10) UNSIGNED NOT NULL DEFAULT 24,
  `ativa` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `config` text NOT NULL DEFAULT '',
  `limite_min` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `categoria` (`categoria`,`etapa`),
  KEY `vila` (`vila_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Progresso de cada jogador nas missões extras.
CREATE TABLE IF NOT EXISTS `naruto_missoesextras_jogador` (
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `missao_id` int(10) UNSIGNED NOT NULL,
  `progresso` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `etapa_atual` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `estado` enum('andamento','concluida','falhou') NOT NULL DEFAULT 'andamento',
  `aceita_em` datetime NOT NULL,
  `concluida_em` datetime DEFAULT NULL,
  `dados` text NOT NULL DEFAULT '',
  PRIMARY KEY (`usuario_id`,`missao_id`),
  KEY `missao` (`missao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NPCs do mapa (falam com o jogador pelo botão "Conversar" na casa onde estão).
CREATE TABLE IF NOT EXISTS `naruto_npcs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL DEFAULT '',
  `avatar` varchar(30) NOT NULL DEFAULT 'personagem1',
  `icone_mapa` varchar(40) NOT NULL DEFAULT 'arvore_personagem1',
  `latitude` smallint(6) NOT NULL DEFAULT 0,
  `longitude` smallint(6) NOT NULL DEFAULT 0,
  `fala` text NOT NULL,
  `fala_en` text NOT NULL,
  `ativo` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  -- Papel especial: '' = conversa/missões; 'comprador_pergaminhos' = comprador de pergaminhos distante.
  `papel` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `local` (`latitude`,`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NPCs 1-6: personagens das missões da história; 7-10: personagens das missões extras.
INSERT INTO `naruto_npcs` (`id`, `nome`, `avatar`, `icone_mapa`, `latitude`, `longitude`, `fala`, `fala_en`, `ativo`) VALUES
(1, 'Mila', 'personagem5', 'arvore_personagem1', -101, 102, 'Olá %s! Você veio até aqui procurando algo sobre a arte eremita? Está falando com a pessoa certa! Muitos tentaram mas nunca conseguiram adquirir tamanho controle de energia natural, ao adquirir os poderes da arte eremita, você certamente estará em um nível superior... Uma hora será a sua vez! Treine árduamente até seu chakra esgotar e você será um sábio eremita algum dia! Leve essa informação até a Vila da Folha para completar a sua missão.', 'Hello, %s! Did you come all this way to learn about the sage arts? Then you’re talking to the right person! Many have tried, but few ever gain that kind of control over natural energy. Once you master the power of the sage arts, you’ll reach a whole new level... Your time will come! Train hard until your chakra runs dry, and one day you’ll become a true sage! Take this news back to the Leaf Village to complete your mission.', 1),
(2, 'Nakima', 'personagem3', 'arvore_personagem2', 124, -75, 'Olá %s! Gostaria de obter informações da Espada Raiga? Bem... Ao utilizar as Espadas Raiga, a mesma invoca a energia da naruteza Raiton. Sendo assim, seu golpe possui uma força muito elevada perante a todos os outros ataques Raiton por usar a fonte natural do próprio Raio. Ouvi rumores de que as Espadas Raiga podem ser obtidas por Alquimia de uma outra espada e um item de Konoha...', 'Hello, %s! So you want to know about the Raiga Swords? Well... when you wield them, they call upon the Raiton nature itself. That makes their strikes far stronger than any other Raiton attack, because they draw on the raw power of lightning. I’ve heard rumors that the Raiga Swords can be made through Alchemy, from another sword and an item from Konoha...', 1),
(3, 'Temari', 'temari', 'arvore_personagem3', 5, -8, 'Olá %s! Estão precisando da minha ajuda na Vila da Areia? Tudo bem! Já estou voltando até a Vila, vá na frente e avise a meu irmão que em breve estarei lá.', 'Hello, %s! They need my help back in the Sand Village? All right! I’m heading home now. Go on ahead and tell my brother I’ll be there soon.', 1),
(4, 'Shinomori', 'personagem6', 'arvore_personagem4', 171, 171, 'Olá %s! Você já ouviu falar sobre Alquimia? A alquimia é uma arte onde é possível de certa forma, fundir materiais e originar um novo. Alguns itens com o prefixo \'Alma..\' são possíveis de serem fundidos pela alquimia... Leve essa informação até a Vila da Folha para ajudar na finalização de sua missão.', 'Hello, %s! Have you ever heard of Alchemy? It is an art that lets you fuse materials together to create something new. Some items whose names start with “Soul...” can be fused through alchemy... Take this news back to the Leaf Village to help finish your mission.', 1),
(5, 'Hikaru', 'personagem9', 'arvore_personagem5', 99, 26, 'Olá %s! Eu ouvi rumores de que o cajado usado pelo terceiro Hokage ainda existe, e que ele possui poderes extraordinários... Você pode retornar até a Vila da Folha para completar sua missão...', 'Hello, %s! I’ve heard rumors that the staff wielded by the Third Hokage still exists, and that it holds extraordinary power... You can head back to the Leaf Village to complete your mission...', 1),
(6, 'Mishigan', 'personagem8', 'arvore_personagem6', 100, -120, 'Olá %s! Aparentemente eu não me sentia muito bem na Vila da Névoa, há muita corrupção por lá e muito mistério... Quer dizer que a Mizukage está me procurando então? ... Vou voltar a Vila para ter conhecimento sobre o que ela quer comigo... Você pode voltar até a Vila para receber sua recompensa.', 'Hello, %s! To be honest, I never felt at ease in the Mist Village. There’s so much corruption there, so many secrets... So the Mizukage is looking for me? ... I’ll go back to the village and find out what she wants from me... You can return to the village to collect your reward.', 1),
(7, 'Pescador Tomiji', 'personagem1', 'arvore_personagem10', 8, 6, 'Ah, %s! Sente-se um pouco. O rio anda generoso, mas os bichos da mata também perceberam... Se puder me dar uma mão, eu pago em Ryou e em peixe assado.', 'Ah, %s! Sit down for a bit. The river has been generous lately, but the forest critters noticed too... If you can lend me a hand, I pay in Ryou and grilled fish.', 1),
(8, 'Ferreira Okaya', 'personagem2', 'arvore_personagem2', 118, -62, 'Hmpf. %s, não é? Minha forja não esfria nem com a chuva da montanha. Se tiver material bom, eu tenho trabalho para você.', 'Hmph. %s, right? My forge doesn’t cool down even in the mountain rain. If you have good material, I have work for you.', 1),
(9, 'Monge Hōsen', 'personagem4', 'arvore_personagem4', -94, 93, 'Silêncio, %s... escute o vento entre as pedras. Cada passo seu nesta trilha é uma oração. Talvez você possa levar uma das minhas.', 'Quiet, %s... listen to the wind between the stones. Every step you take on this trail is a prayer. Perhaps you can carry one of mine.', 1),
(10, 'Cartógrafa Nayumi', 'personagem7', 'arvore_personagem3', 24, 160, 'Olá, %s! Estou desenhando as correntes desta costa, mas a névoa apaga tudo que eu anoto. Preciso de olhos jovens no mar aberto!', 'Hello, %s! I’m charting the currents along this coast, but the mist erases everything I write. I need young eyes out on the open sea!', 1);

-- Missões da história (etapas 1-21). O progresso continua em users.missao ("etapa,feitos,total,intervalo").
INSERT INTO `naruto_missoesextras` (`id`, `categoria`, `etapa`, `titulo`, `titulo_en`, `descricao`, `descricao_en`, `descricao_arg`, `recompensa_texto`, `recompensa_texto_en`, `recompensa_arg`, `rank_texto`, `local_texto`, `mapa_item`, `mapa_local`, `mapa_monstro`, `npc_nome`, `npc_avatar`, `npc_id`, `vila_id`, `nivel_minimo`, `graduacao_minima`, `tipo`, `alvo`, `quantidade`, `intervalo_min`, `gancho`, `recompensa_exp`, `recompensa_ryou`, `recompensa_item`, `repetivel`, `intervalo_horas`, `ativa`, `ordem`) VALUES
(1, 'historia', 1, 'Fartura do Fogo!', 'Bounty of Fire!', 'Levar o item <b>%s</b> até a Vila da Folha.', 'Bring the <b>%s</b> item to the Leaf Village.', 'Fartura do Fogo', 'Nenhuma.', 'None.', '', 'Rank E.', 'Vila da Folha.', 'Fartura do Fogo', '', '', 'Hokage', '1', 0, 1, 1, '', 'entregar_item', 'Fartura do Fogo', 1, 0, '', 0, 0, '', 0, 0, 1, 1),
(2, 'historia', 2, 'Lixos no Caminho!', 'Trash in the Streets!', 'Konoha está precisando de ajuda comunitária para limpeza da cidade. Colete o lixo 10 vezes a cada 1 minuto para completar essa missão.', 'Konoha needs volunteers to help clean up the village. Pick up trash 10 times, once every minute, to complete this mission.', '', '50 Ryou.', '50 Ryou.', '', 'Rank E.', 'Vila da Folha.', '', '', '', 'Hokage', '1', 0, 1, 1, '', 'repetir', '', 10, 1, '', 0, 50, '', 0, 0, 1, 2),
(3, 'historia', 3, 'Konoha Precisa de Ajuda!', 'Konoha Needs Help!', 'Procure pelo <b>%s</b> que está causando problemas aos arredores da Vila da Folha, mate-o e venha imediatamente até a Vila da Folha.', 'Hunt down the <b>%s</b> that has been causing trouble around the Leaf Village, defeat it and come straight back to the Leaf Village.', 'Lobo Rubro', '100 Ryou.', '100 Ryou.', '', 'Rank E.', 'Vila da Folha.', '', '', 'Lobo Rubro', 'Hokage', '1', 0, 1, 1, '', 'derrotar_monstro', '3', 1, 0, '', 0, 100, '', 0, 0, 1, 3),
(4, 'historia', 4, 'Bagunça Total!', 'Total Mess!', 'Seu quarto está bagunçado. Arrume o seu quarto 5 vezes a cada 1 minuto para completar essa missão.', 'Your room is a mess. Tidy up your room 5 times, once every minute, to complete this mission.', '', '30 Ryou.', '30 Ryou.', '', 'Rank E.', 'Vila da Folha.', '', '', '', 'Hokage', '1', 0, 1, 1, '', 'repetir', '', 5, 1, '', 0, 30, '', 0, 0, 1, 4),
(5, 'historia', 5, 'Boneca Perdida!', 'The Lost Doll!', 'Uma criança da vila perdeu sua boneca. Você precisa levar o item <b>%s</b> até a Vila da Folha.', 'A child from the village has lost her doll. Bring the <b>%s</b> item to the Leaf Village.', 'Boneca Perdida', '100 Ryou.', '100 Ryou.', '', 'Rank D.', 'Vila da Folha.', 'Boneca Perdida', '', '', 'Hokage', '1', 0, 1, 1, '', 'entregar_item', 'Boneca Perdida', 1, 0, '', 0, 100, '', 0, 0, 1, 5),
(6, 'historia', 6, 'Arte Eremita!', 'Sage Arts!', 'Procure nas proximidades da Montanha Myoboku, alguém que possa te informar um pouco mais sobre a arte eremita, e então, traga o seu novo conhecimento até a Vila da Folha.', 'Search the area around Mount Myoboku for someone who can tell you more about the sage arts, then bring what you learned back to the Leaf Village.', '', '300 Ryou.', '300 Ryou.', '', 'Rank D.', 'Vila da Folha.', '', '-101/102', '', 'Hokage', '1', 1, 1, 1, '', 'falar_com_npc', '', 1, 0, '', 0, 300, '', 0, 0, 1, 6),
(7, 'historia', 7, 'Cabeça de Zabuza Momochi!', 'The Head of Zabuza Momochi!', 'A Vila da Folha precisa que você procure e mate <b>%s</b> e então volte imediatamente até a Vila da Folha para pegar sua recompensa.', 'The Leaf Village needs you to track down and defeat <b>%s</b>, then come straight back to the Leaf Village to collect your reward.', 'Zabuza', '350 Ryou.', '350 Ryou.', '', 'Rank C.', 'Vila da Folha.', '', '', '', 'Hokage', '1', 0, 1, 1, '', 'derrotar_monstro', '24', 1, 0, '', 0, 350, '', 0, 0, 1, 7),
(8, 'historia', 8, 'Perigo a Solta!', 'Danger on the Loose!', 'Há uma infestação de roedores espalhados pela Montanha Myoboku, ajude a acabar com essa praga. Você deve realizar esse processo 10 vezes em intervalos de 6 minutos para completar essa missão.', 'Mount Myoboku is infested with rodents. Help get rid of the pests! You must do this 10 times, 6 minutes apart, to complete this mission.', '', '500 Ryou.', '500 Ryou.', '', 'Rank D.', 'Montanha Myoboku.', '', '', '', 'Fukasaku', '2', 0, 2, 1, '', 'repetir', '', 10, 6, '', 0, 500, '', 0, 0, 1, 8),
(9, 'historia', 9, 'Espadas Raiga!', 'The Raiga Swords!', 'Procure Nakima nas proximidades da Vila da Pedra, recolha informações sobre as Espadas Raiga que ela possui e então leve as informações até a Vila da Pedra para reconhecimento do Kage.', 'Find Nakima near the Stone Village, learn what she knows about the Raiga Swords she owns, then take that information to the Stone Village so the Kage can review it.', '', '300 Ryou.', '300 Ryou.', '', 'Rank D.', 'Vila da Pedra.', '', '124/-75', '', 'Tsuchikage', '3', 2, 3, 1, '', 'falar_com_npc', '', 1, 0, '', 0, 300, '', 0, 0, 1, 9),
(10, 'historia', 10, 'Rastro de Hachibi!', 'Hachibi’s Trail!', 'Leve até a Vila da Nuvem o item <b>%s</b>.', 'Take the <b>%s</b> item to the Cloud Village.', 'Rastro de Hachibi', '1000 Ryou, <font color=grey>(x1)</font>%s.', '1000 Ryou, <font color=grey>(x1)</font>%s.', 'Máscara do Ritual', 'Rank B.', 'Vila da Nuvem.', 'Rastro de Hachibi', '', '', 'Raikage', '6', 0, 6, 1, '', 'entregar_item', 'Rastro de Hachibi', 1, 0, '', 0, 1000, 'Máscara do Ritual', 0, 0, 1, 10),
(11, 'historia', 11, 'Passando Conhecimento!', 'Passing On Knowledge!', 'Ajude um dos estudantes da academia a aprender um novo jutsu. Você deve fazer isso 5 vezes em intervalos de 12 minutos.', 'Help one of the Academy students learn a new jutsu. You must do this 5 times, 12 minutes apart.', '', '400 Ryou.', '400 Ryou.', '', 'Rank D.', 'Vila da Nuvem.', '', '', '', 'Raikage', '6', 0, 6, 1, '', 'repetir', '', 5, 12, '', 0, 400, '', 0, 0, 1, 11),
(12, 'historia', 12, 'Compondo a Vila da Areia!', 'Reinforcing the Sand Village!', 'A Vila da Areia está precisando que Temari retorne até a mesma. Encontre-a na proximidade da Vila da Folha. Para completar essa missão, também é necessário ajudar na missão de expedição é um campo de batalha da Vila da Areia, portanto, mantenha total suporte realizando essa missão 5 vezes em intervalos de 60 minutos.', 'The Sand Village needs Temari to come home. Find her near the Leaf Village. To complete this mission you must also support the Sand Village’s expedition to the battlefield, so give it your full support by doing this mission 5 times, 60 minutes apart.', '', '5000 Ryou.', '5000 Ryou.', '', 'Rank B.', 'Vila da Areia.', '', '5/-8', '', 'Kazekage', '5', 3, 5, 1, '', 'repetir', '', 5, 60, '', 0, 5000, '', 0, 0, 1, 12),
(13, 'historia', 13, 'Em Busca de Nibi!', 'In Search of Nibi!', 'Procure por <b>%s</b> próximo à Vila da Névoa, mate-o e venha imediatamente até a Vila.', 'Look for <b>%s</b> near the Mist Village, defeat it and come straight back to the village.', 'Nibi', '400 Ryou.', '400 Ryou.', '', 'Rank B.', 'Vila da Névoa.', '', '', 'Nibi', 'Mizukage', '4', 0, 4, 1, '', 'derrotar_monstro', '101', 1, 0, '', 0, 400, '', 0, 0, 1, 13),
(14, 'historia', 14, 'Vingança do Som!', 'Revenge of the Sound!', 'Mate <b>%s</b> três vezes seguidas e venha imediatamente até a Vila da Pedra.', 'Defeat <b>%s</b> three times in a row and come straight to the Stone Village.', 'Jiroubou Selo 1', '400 Ryou.', '400 Ryou.', '', 'Rank C.', 'Vila da Pedra.', '', '', 'Jiroubou Selo 1', 'Tsuchikage', '3', 0, 3, 1, '', 'derrotar_monstro', '78', 3, 0, '', 0, 400, '', 0, 0, 1, 14),
(15, 'historia', 15, 'Flauta Surpresa!', 'A Surprising Flute!', 'Obter o item <b>%s</b>, procurar por Shinomori próximo à Vila da Nuvem e trazer as informações disponibilizadas por ela até a Vila da Folha.', 'Get the <b>%s</b> item, find Shinomori near the Cloud Village and bring what she tells you back to the Leaf Village.', 'Runa de Chakra', '1000 Ryou, Item: <b>%s</b>.', '1000 Ryou, Item: <b>%s</b>.', 'Flauta de Tayuya', 'Rank B.', 'Vila da Folha.', 'Runa de Chakra', '171/171', '', 'Hokage', '1', 4, 1, 1, '', 'entregar_item', 'Runa de Chakra', 1, 0, '', 0, 1000, 'Flauta de Tayuya', 0, 0, 1, 15),
(16, 'historia', 16, 'Eliminando Pein #1!', 'Taking Down Pain #1!', 'Derrotar <b>%s</b>, um dos corpos de Pein e vá imediatamente até a Vila da Areia.', 'Defeat <b>%s</b>, one of the Paths of Pain, and go straight to the Sand Village.', 'Ningendo', '5000 Ryou.', '5000 Ryou.', '', 'Rank A.', 'Vila da Areia.', '', '', 'Ningendo', 'Kazekage', '5', 0, 5, 1, '', 'derrotar_monstro', '9', 1, 0, '', 0, 5000, '', 0, 0, 1, 16),
(17, 'historia', 17, 'Ajuda ao Kazekage!', 'Helping the Kazekage!', 'Ajude o Kazekage a organizar a função de todos os jounins da vila. Você deve realizar essa operação 10 vezes em intervalos de 6 minutos.', 'Help the Kazekage assign duties to all of the village’s Jounin. You must do this 10 times, 6 minutes apart.', '', '2000 Ryou.', '2000 Ryou.', '', 'Rank C.', 'Vila da Areia.', '', '', '', 'Kazekage', '5', 0, 5, 1, '', 'repetir', '', 10, 6, '', 0, 2000, '', 0, 0, 1, 17),
(18, 'historia', 18, 'Cajado Enma!', 'The Enma Staff!', 'Procure por Hikaru para adquirir informações sobre o <b>%s</b> e retorne até a Vila da Folha.', 'Find Hikaru to learn about the <b>%s</b>, then return to the Leaf Village.', 'Cajado Enma', 'Item: <b>%s</b>.', 'Item: <b>%s</b>.', 'Cajado Enma', 'Rank C.', 'Vila da Folha.', 'Força Inútil', '99/26', '', 'Hokage', '1', 5, 1, 1, '', 'entregar_item', 'Força Inútil', 1, 0, '', 0, 0, 'Cajado Enma', 0, 0, 1, 18),
(19, 'historia', 19, 'Ataque na Vila da Folha!', 'Attack on the Leaf Village!', 'A Vila da Folha precisa de ajuda, vá até a Montanha Myoboku e traga Fukasaku e Shima até a Vila da Folha.', 'The Leaf Village needs help! Go to Mount Myoboku and bring Fukasaku and Shima back to the Leaf Village.', '', 'Item: <b>%s</b>.', 'Item: <b>%s</b>.', 'Amuleto de Sapo', 'Rank B.', 'Vila da Folha.', '', '', '', 'Hokage', '1', 0, 1, 1, '', 'especial', '', 1, 0, 'sapos_no_grupo', 0, 0, 'Amuleto de Sapo', 0, 0, 1, 19),
(20, 'historia', 20, 'Eliminando Pein #2!', 'Taking Down Pain #2!', 'Derrotar <b>%s</b>, um dos corpos de Pein e vá imediatamente até a Vila da Folha.', 'Defeat <b>%s</b>, one of the Paths of Pain, and go straight to the Leaf Village.', 'Gakido', '7000 Ryou, Item: <b>%s</b>.', '7000 Ryou, Item: <b>%s</b>.', 'Anel da Sorte', 'Rank A.', 'Vila da Folha.', '', '', 'Gakido', 'Hokage', '1', 0, 1, 1, '', 'derrotar_monstro', '109', 1, 0, '', 0, 7000, 'Anel da Sorte', 0, 0, 1, 20),
(21, 'historia', 21, 'Em Busca do Desaparecido!', 'Searching for the Missing Ninja!', 'Localize Mishigan, converse com ele sobre o seu desaparecimento da Vila da Névoa e então volte até a Vila para receber sua recompensa, traga consigo o item <b>%s</b>.', 'Find Mishigan and ask him why he disappeared from the Mist Village, then return to the village for your reward. Bring the <b>%s</b> item with you.', 'Alma da Névoa', 'Item: <b>%s</b>.', 'Item: <b>%s</b>.', 'Protetor de Honra da Névoa', 'Rank C.', 'Vila da Névoa.', 'Alma da Névoa', '', '', 'Mizukage', '4', 6, 4, 1, '', 'entregar_item', 'Alma da Névoa', 1, 0, '', 0, 0, 'Protetor de Honra da Névoa', 0, 0, 1, 21);

-- Missões extras de exemplo (opcionais, repetíveis ou não).
INSERT INTO `naruto_missoesextras` (`id`, `categoria`, `etapa`, `titulo`, `titulo_en`, `descricao`, `descricao_en`, `descricao_arg`, `recompensa_texto`, `recompensa_texto_en`, `recompensa_arg`, `rank_texto`, `local_texto`, `mapa_item`, `mapa_local`, `mapa_monstro`, `npc_nome`, `npc_avatar`, `npc_id`, `vila_id`, `nivel_minimo`, `graduacao_minima`, `tipo`, `alvo`, `quantidade`, `intervalo_min`, `gancho`, `recompensa_exp`, `recompensa_ryou`, `recompensa_item`, `repetivel`, `intervalo_horas`, `ativa`, `ordem`) VALUES
(101, 'extra', 0, 'Peixes Fugitivos', 'Runaway Fish', 'Toda madrugada uma alcateia de lobos invade o acampamento do velho Tomiji e foge com o peixe do dia. Ele jura que um deles até usa o chapéu de palha que sumiu do varal. Derrote os lobos da mata e devolva a paz ao pescador.', 'Every dawn a pack of wolves raids old Tomiji’s camp and runs off with the day’s catch. He swears one of them is even wearing the straw hat that vanished from his clothesline. Defeat the forest wolves and bring the fisherman some peace.', '', '', '', '', 'Rank E.', '', '', '', '', 'Pescador Tomiji', 'personagem1', 7, 0, 1, '', 'derrotar_monstro', '2', 5, 0, '', 15, 40, '', 1, 24, 1, 1),
(102, 'extra', 0, 'O Chá da Vovó Ume', 'Granny Ume’s Tea', 'Vovó Ume prepara o chá mais famoso da vila, mas a receita secreta pede um Fragmento de Vida moído na hora. Seus joelhos já não aguentam a caminhada até a mata. Traga o fragmento e ganhe uma xícara de brinde (e algumas moedas).', 'Granny Ume brews the most famous tea in the village, but the secret recipe calls for a freshly ground Life Fragment. Her knees can’t handle the walk to the woods anymore. Bring her the fragment and earn a free cup (and a few coins).', '', '', '', '', 'Rank E.', '', '', '', '', 'Vovó Ume', 'personagem10', 0, 1, 3, '', 'entregar_item', 'Fragmento de Vida', 1, 0, '', 20, 120, '', 0, 24, 1, 2),
(103, 'extra', 0, 'A Trilha Esquecida', 'The Forgotten Trail', 'O cartógrafo da vila encontrou um mapa antigo com uma trilha que ninguém usa há gerações. Vá até o ponto marcado, confira se a velha pedra-guia ainda está de pé e volte para contar o que viu.', 'The village cartographer found an old map showing a trail nobody has walked in generations. Go to the marked spot, check whether the old guide stone is still standing and come back to report what you saw.', '', '', '', '', 'Rank D.', '', '', '', '', 'Cartógrafo Shinpei', 'personagem2', 0, 1, 5, '', 'visitar_local', '20,-15', 1, 0, '', 60, 150, '', 0, 24, 1, 3),
(104, 'extra', 0, 'A Ponte do Desfiladeiro', 'The Canyon Bridge', 'Um deslizamento levou a ponte que liga a Vila da Pedra às minas. Os pedreiros já têm as cordas, mas falta dinheiro para as tábuas de cedro. Contribua com a reconstrução e seu nome será entalhado no primeiro degrau.', 'A landslide swept away the bridge connecting the Stone Village to the mines. The masons already have the ropes, but they lack money for the cedar planks. Help fund the rebuilding and your name will be carved into the first step.', '', '', '', '', 'Rank D.', '', '', '', '', 'Mestre de Obras Gantetsu', 'personagem3', 0, 3, 8, '', 'pagar_ryou', '', 500, 0, '', 150, 0, 'Fragmento Médio de Vida', 0, 24, 1, 4),
(105, 'extra', 0, 'Pedra para a Forja', 'Stone for the Forge', 'A ferreira Okaya quer forjar uma lâmina que não lasque nem contra rocha. Para isso ela precisa de uma Pedra da Resistência, que só cai de inimigos fortes. Traga a pedra e ela paga muito bem pelo seu esforço.', 'Okaya the blacksmith wants to forge a blade that won’t chip even against rock. For that she needs a Stone of Endurance, which only drops from strong enemies. Bring her the stone and she will pay you handsomely for the trouble.', '', '', '', '', 'Rank B.', '', '', '', '', 'Ferreira Okaya', 'personagem2', 8, 0, 25, '', 'entregar_item', 'Pedra da Resistência', 1, 0, '', 600, 1500, '', 0, 24, 1, 5),
(106, 'extra', 0, 'Bandidos na Estrada', 'Bandits on the Road', 'As caravanas que trazem água para a Vila da Areia estão sendo assaltadas por bandidos armados. O conselho da vila oferece uma recompensa a quem derrotar quatro deles e deixar a estrada segura de novo.', 'The caravans that bring water to the Sand Village are being robbed by armed bandits. The village council offers a reward to whoever defeats four of them and makes the road safe again.', '', '', '', '', 'Rank D.', '', '', '', '', 'Conselheira Sabaku', 'personagem6', 0, 5, 10, '', 'derrotar_monstro', '19', 4, 0, '', 200, 180, '', 0, 24, 1, 6),
(107, 'extra', 0, 'Patrulha na Névoa', 'Patrol in the Mist', 'Kunoichis errantes estão usando a névoa da costa para espionar a vila. A guarda precisa de reforço todos os dias: derrote três delas e volte com o relatório da patrulha.', 'Rogue kunoichi are using the coastal mist to spy on the village. The guard needs reinforcements every day: defeat three of them and come back with the patrol report.', '', '', '', '', 'Rank C.', '', '', '', '', 'Capitão Mizuchi', 'personagem8', 0, 4, 18, '', 'derrotar_monstro', '153', 3, 0, '', 450, 160, '', 1, 24, 1, 7),
(108, 'extra', 0, 'Uma Oração pelo Rio', 'A Prayer for the River', 'O monge Hōsen escreveu uma oração de agradecimento ao rio que corre perto da Vila da Folha. Ele quer que ela chegue ao pescador que vive à beira d\'água. Encontre o pescador, converse com ele e volte até o monge.', 'Monk Hōsen wrote a prayer of thanks to the river that flows near the Leaf Village. He wants it delivered to the fisherman who lives by the water. Find the fisherman, talk to him and return to the monk.', '', '', '', '', 'Rank C.', '', '', '', '', 'Monge Hōsen', 'personagem4', 9, 0, 12, '', 'falar_com_npc', '7', 1, 0, '', 250, 200, '', 0, 24, 1, 8),
(109, 'extra', 0, 'O Selo Rachado', 'The Cracked Seal', 'Os anciãos da montanha sentiram o selo de pedra tremer durante a noite. Feras seladas estão escapando pelas rachaduras. Derrote duas delas antes que o selo se parta por completo.', 'The mountain elders felt the stone seal shake during the night. Sealed beasts are slipping out through the cracks. Defeat two of them before the seal breaks completely.', '', '', '', '', 'Rank A.', '', '', '', '', 'Anciã Gamako', 'personagem9', 0, 2, 32, '', 'derrotar_monstro', '158', 2, 0, '', 1200, 500, 'Pedra da Força Leal', 0, 24, 1, 9),
(110, 'extra', 0, 'Tributo ao Trovão', 'Tribute to the Thunder', 'Uma vez por ano os ninjas da Vila da Nuvem oferecem um tributo ao trovão para garantir boas colheitas. Quem contribui com uma oferta generosa recebe uma bênção de chakra do santuário.', 'Once a year the ninja of the Cloud Village offer a tribute to the thunder to ensure good harvests. Whoever makes a generous offering receives a chakra blessing from the shrine.', '', '', '', '', 'Rank B.', '', '', '', '', 'Sacerdote Raimon', 'personagem5', 0, 6, 30, '', 'pagar_ryou', '', 3000, 0, '', 800, 0, 'Chakra Ventania', 0, 24, 1, 10),
(111, 'extra', 0, 'Correntes da Costa', 'Coastal Currents', 'A cartógrafa Nayumi precisa saber se a corrente fria ainda passa pelo recife do leste. Vá até o recife, observe a água e volte com as anotações antes que a névoa as apague.', 'Nayumi the cartographer needs to know whether the cold current still runs past the eastern reef. Go to the reef, watch the water and come back with your notes before the mist erases them.', '', '', '', '', 'Rank C.', '', '', '', '', 'Cartógrafa Nayumi', 'personagem7', 10, 0, 20, '', 'visitar_local', '45,185', 1, 0, '', 500, 300, '', 1, 12, 1, 11),
(112, 'extra', 0, 'O Último Fantoche', 'The Last Puppet', 'Uma marionete ancestral, abandonada por um mestre esquecido, ainda vaga pelo deserto obedecendo a ordens que ninguém mais lembra. Encerre a vigília dela e traga paz ao velho ateliê da Areia.', 'An ancient puppet, abandoned by a forgotten master, still roams the desert obeying orders nobody remembers. End its endless watch and bring peace to the Sand Village’s old workshop.', '', '', '', '', 'Rank S.', '', '', '', '', 'Artesã Kugutsu', 'personagem6', 0, 5, 38, 'Chuunin', 'derrotar_monstro', '159', 1, 0, '', 2000, 900, 'Pedra Sannin', 0, 24, 1, 12);
"""
W(MISSOES_SQL)
# Missões 120-134 (tipos novos: cadeia, viagem, coletar, sobreviver, escolha, cronometradas, diárias/semanais) e NPCs 11-14.
# Gerado pelo script das missões novas (mesmos valores gravados no banco ao vivo).
MISSOES_NOVAS_SQL = r"""
-- NPCs 11-14 e missões 120-134 (configuração dos tipos novos em JSON na coluna config).
INSERT INTO `naruto_npcs` (`id`, `nome`, `avatar`, `icone_mapa`, `latitude`, `longitude`, `fala`, `fala_en`, `ativo`) VALUES
(11, 'Vigia Rensuke', 'personagem10', 'arvore_personagem5', -118, -122, 'Mais um viajante, %s? Daqui da torre eu vejo cada tempestade de areia chegando... e cada bandido escondido nela. Se tiver fôlego, preciso de ajuda.', 'Another traveler, %s? From this tower I can see every sandstorm coming... and every bandit hiding inside it. If you have the stamina, I could use some help.', 1),
(12, 'Herborista Suzuran', 'personagem5', 'arvore_personagem1', -6, 12, 'Cuidado onde pisa, %s! Essas folhas aí são para o remédio de tosse da vila inteira. Já que está aqui, que tal me ajudar?', 'Watch your step, %s! Those leaves are for the whole village’s cough medicine. Since you’re here, how about giving me a hand?', 1),
(13, 'Mercador Botan', 'personagem2', 'arvore_personagem10', 62, -36, '%s! Meu amigo! Sal, seda, pimenta, histórias... tudo tem preço na estrada, menos a companhia de alguém confiável.', '%s! My friend! Salt, silk, pepper, stories... everything on the road has a price, except the company of someone you can trust.', 1),
(14, 'Arqueóloga Itsuha', 'personagem7', 'arvore_personagem6', 38, 152, 'Psiu, %s, fale baixo. As ruínas ali adiante escutam. Juro que ontem à noite uma das estátuas virou a cabeça para me olhar.', 'Shh, %s, keep your voice down. The ruins over there are listening. I swear one of the statues turned its head to look at me last night.', 1);
INSERT INTO `naruto_missoesextras` (`id`, `categoria`, `etapa`, `titulo`, `titulo_en`, `descricao`, `descricao_en`, `rank_texto`, `npc_nome`, `npc_avatar`, `npc_id`, `vila_id`, `nivel_minimo`, `tipo`, `alvo`, `quantidade`, `recompensa_exp`, `recompensa_ryou`, `recompensa_item`, `repetivel`, `intervalo_horas`, `config`, `limite_min`, `ordem`, `ativa`) VALUES
(120, 'extra', 0, 'O Sino que Toca Sozinho', 'The Bell That Rings by Itself', 'Toda noite, à meia-noite, o velho sino do portão leste toca sozinho. Os guardas juram que não há ninguém lá. A herborista Suzuran diz ter visto penas negras em volta do sino... Investigue o mistério do começo ao fim.', 'Every night at midnight the old bell by the east gate rings all by itself. The guards swear nobody is there. Suzuran the herbalist says she saw black feathers around the bell... Get to the bottom of the mystery.', 'Rank D.', 'Guarda Hiroshi', 'personagem6', 0, 1, 3, 'cadeia', '', 4, 300, 350, '', 0, 24, '{"etapas": [{"tipo": "falar_com_npc", "alvo": "12", "quantidade": 1, "texto": "Pergunte à herborista Suzuran o que ela viu.", "texto_en": "Ask Suzuran the herbalist what she saw."}, {"tipo": "derrotar_monstro", "alvo": "6", "quantidade": 3, "texto": "Os corvos fazem ninho no sino: espante-os.", "texto_en": "Crows are nesting in the bell: drive them off."}, {"tipo": "visitar_local", "alvo": "14,-9", "quantidade": 1, "texto": "Siga as penas até o velho carvalho.", "texto_en": "Follow the feathers to the old oak."}, {"tipo": "falar_com_npc", "alvo": "7", "quantidade": 1, "texto": "No carvalho há uma corda de pesca amarrada ao sino. Pergunte ao pescador Tomiji.", "texto_en": "There is a fishing line tied from the oak to the bell. Ask Tomiji the fisherman."}]}', 0, 120, 1),
(121, 'extra', 0, 'Ervas Antes do Anoitecer', 'Herbs Before Nightfall', 'O remédio de tosse precisa de ervas colhidas no mesmo dia, senão perde o efeito. Suzuran pede três maços de Erva Medicinal Seca, e rápido: em meia hora o sol se põe e as ervas murcham.', 'The cough medicine needs herbs picked the same day or it loses its effect. Suzuran asks for three bundles of Dried Medicinal Herb, and quickly: in half an hour the sun sets and the herbs wilt.', 'Rank E.', 'Herborista Suzuran', 'personagem5', 12, 0, 2, 'coletar', 'Erva Medicinal Seca', 3, 120, 150, '', 1, 24, '{"chance": 35}', 30, 121, 1),
(122, 'extra', 0, 'Carroça de Sal', 'The Salt Wagon', 'Uma carroça de sal precisa chegar ao mercador Botan, na estrada para a Vila da Pedra. O cocheiro tem medo de ir sozinho e não aceita atalhos: vá a pé, ao lado da carroça, sem dar voltas demais, ou o sal empedra com a umidade.', 'A wagon of salt has to reach Botan the merchant on the road to the Stone Village. The driver is afraid to go alone and won’t take shortcuts: walk beside the wagon, without wandering too much, or the salt will clump in the damp air.', 'Rank D.', 'Cocheiro Jiro', 'personagem1', 0, 1, 8, 'viagem', 'npc:13', 130, 500, 600, 'Kunai Invertida', 0, 24, '', 0, 122, 1),
(123, 'extra', 0, 'Escolta do Aprendiz', 'Escorting the Apprentice', 'O neto do pescador Tomiji quer conhecer a Vila da Folha, mas se perde até no próprio quintal. Leve o menino a pé até a vila em poucos passos, antes que ele se distraia com uma borboleta e suma de novo.', 'Tomiji the fisherman’s grandson wants to see the Leaf Village, but he gets lost even in his own backyard. Walk the boy to the village in just a few steps, before a butterfly distracts him and he wanders off again.', 'Rank E.', 'Pescador Tomiji', 'personagem1', 7, 0, 1, 'viagem', 'vila:1', 20, 80, 120, '', 0, 24, '', 15, 123, 1),
(124, 'extra', 0, 'Duelo no Desfiladeiro', 'Duel at the Canyon', 'Tsubame, uma jovem de olhos afiados da Vila da Pedra, afirma ser a ninja mais resistente da região. Ela desafia você: vença cinco lutas seguidas contra inimigos de verdade, sem descansar e sem se curar. Se conseguir, ela admite a derrota em público.', 'Tsubame, a sharp-eyed young ninja from the Stone Village, claims to be the toughest ninja in the region. She challenges you: win five fights in a row against real enemies, without resting or healing. If you manage it, she will admit defeat in public.', 'Rank C.', 'Tsubame', 'personagem3', 0, 3, 22, 'sobreviver', '', 5, 1400, 900, '', 0, 24, '{"nivel": 15}', 0, 124, 1),
(125, 'extra', 0, 'O Festival das Lanternas', 'The Lantern Festival', 'Falta uma semana para o Festival das Lanternas da Vila da Nuvem e nada está pronto. O comitê precisa de juncos do lago, pergaminhos para as lanternas e dinheiro para os fogos. Ajude a montar a festa mais bonita das montanhas.', 'The Cloud Village’s Lantern Festival is a week away and nothing is ready. The committee needs reeds from the lake, scrolls for the lanterns and money for the fireworks. Help put together the most beautiful festival in the mountains.', 'Rank B.', 'Senhora Chikage', 'personagem7', 0, 6, 30, 'cadeia', '', 3, 2200, 1500, '', 0, 24, '{"etapas": [{"tipo": "visitar_local", "alvo": "164,158", "quantidade": 1, "texto": "Corte juncos na margem do lago.", "texto_en": "Cut reeds on the lake shore."}, {"tipo": "coletar", "alvo": "Pergaminho Selado", "quantidade": 2, "chance": 30, "texto": "Consiga pergaminhos selados para pintar as lanternas.", "texto_en": "Get sealed scrolls to paint the lanterns."}, {"tipo": "pagar_ryou", "alvo": "", "quantidade": 800, "texto": "Pague o mestre dos fogos de artifício.", "texto_en": "Pay the fireworks master."}]}', 0, 125, 1),
(126, 'extra', 0, 'Ruínas que Sussurram', 'The Whispering Ruins', 'A arqueóloga Itsuha copiou metade de uma inscrição nas ruínas da costa antes de fugir dos espectros que guardam o lugar. Volte lá com ela, afaste os espectros, recupere um amuleto de jade para acalmar as pedras e leve a cópia para a cartógrafa Nayumi decifrar.', 'Itsuha the archaeologist copied half of an inscription in the coastal ruins before fleeing the specters that guard the place. Go back there, drive the specters away, recover a jade amulet to calm the stones and take the copy to Nayumi the cartographer to decipher.', 'Rank B.', 'Arqueóloga Itsuha', 'personagem7', 14, 0, 35, 'cadeia', '', 4, 3200, 2500, 'Cristal Azul de Eco', 0, 24, '{"etapas": [{"tipo": "visitar_local", "alvo": "44,141", "quantidade": 1, "texto": "Entre no pátio das estátuas.", "texto_en": "Enter the courtyard of statues."}, {"tipo": "derrotar_monstro", "alvo": "178", "quantidade": 2, "texto": "Os espectros despertaram.", "texto_en": "The specters have awakened."}, {"tipo": "coletar", "alvo": "Amuleto de Jade Errante", "quantidade": 1, "chance": 40, "texto": "Recupere um amuleto de jade para acalmar as pedras.", "texto_en": "Recover a jade amulet to calm the stones."}, {"tipo": "falar_com_npc", "alvo": "10", "quantidade": 1, "texto": "Leve a cópia da inscrição à cartógrafa Nayumi.", "texto_en": "Take the copy of the inscription to Nayumi the cartographer."}]}', 0, 126, 1),
(127, 'extra', 0, 'A Carta Selada', 'The Sealed Letter', 'Um mensageiro ferido chega à Vila da Areia e entrega a você uma carta com o selo do conselho, antes de desmaiar. O selo está rachado. Você pode levá-la intacta ao conselho... ou dar uma olhada no que está escrito.', 'A wounded messenger staggers into the Sand Village and hands you a letter bearing the council’s seal before passing out. The seal is cracked. You could take it to the council untouched... or take a peek at what it says.', 'Rank C.', 'Mensageiro Ferido', 'personagem4', 0, 5, 15, 'escolha', '', 1, 0, 0, '', 0, 24, '{"opcoes": [{"rotulo": "Entregar a carta ao conselho", "rotulo_en": "Deliver the letter to the council", "texto": "O conselho agradece a sua honestidade e lhe confia uma nova tarefa.", "texto_en": "The council thanks you for your honesty and trusts you with a new task.", "exp": 700, "ryou": 0, "item": ""}, {"rotulo": "Abrir a carta", "rotulo_en": "Open the letter", "texto": "A carta fala de um carregamento escondido perto da torre de vigia. Um informante paga bem pelo segredo.", "texto_en": "The letter mentions a hidden shipment near the watchtower. An informant pays well for the secret.", "exp": 0, "ryou": 900, "item": ""}]}', 0, 127, 1),
(128, 'extra', 0, 'Lealdade ao Conselho', 'Loyal to the Council', 'Confiando em você, o conselho revela que forasteiros estão vigiando as rotas de água. Derrote três deles e mostre que a carta estava em boas mãos.', 'Trusting you, the council reveals that outsiders are watching the water routes. Defeat three of them and prove the letter was in good hands.', 'Rank C.', 'Conselheira Akane', 'personagem5', 0, 5, 15, 'derrotar_monstro', '37', 3, 1100, 700, '', 0, 24, '{"requer": 127, "requer_opcao": "0"}', 0, 128, 1),
(129, 'extra', 0, 'O Segredo da Carta', 'The Letter’s Secret', 'A carta dizia: "o carregamento parte da torre ao entardecer". Corra a pé até a torre de vigia de Rensuke antes que a carga suma, sem pegar atalhos pelo céu.', 'The letter said: "the shipment leaves the tower at dusk". Run on foot to Rensuke’s watchtower before the cargo disappears, and no shortcuts through the sky.', 'Rank C.', 'Informante', 'personagem8', 0, 5, 15, 'viagem', 'npc:11', 30, 900, 1200, '', 0, 24, '{"requer": 127, "requer_opcao": "1"}', 20, 129, 1),
(130, 'extra', 0, 'Resgate na Mina', 'Rescue at the Mine', 'Dois mineiros ficaram presos depois que emboscadores derrubaram a entrada de uma galeria. A ferreira Okaya sabe onde fica a saída de ar. Siga as instruções dela, afaste os emboscadores e leve algo para curar os feridos.', 'Two miners are trapped after ambushers collapsed the entrance to a tunnel. Okaya the blacksmith knows where the air shaft is. Follow her directions, drive off the ambushers and bring something to heal the wounded.', 'Rank C.', 'Capataz Genzo', 'personagem2', 0, 3, 12, 'cadeia', '', 4, 900, 800, '', 0, 24, '{"etapas": [{"tipo": "falar_com_npc", "alvo": "8", "quantidade": 1, "texto": "Peça à ferreira Okaya o caminho da saída de ar.", "texto_en": "Ask Okaya the blacksmith for the way to the air shaft."}, {"tipo": "derrotar_monstro", "alvo": "33", "quantidade": 2, "texto": "Os emboscadores ainda rondam a mina.", "texto_en": "The ambushers are still prowling the mine."}, {"tipo": "entregar_item", "alvo": "Fragmento de Vida", "quantidade": 1, "npc": 8, "texto": "Leve um Fragmento de Vida para Okaya tratar os mineiros.", "texto_en": "Bring a Life Fragment to Okaya so she can treat the miners."}, {"tipo": "visitar_local", "alvo": "121,-60", "quantidade": 1, "texto": "Abra a saída de ar.", "texto_en": "Open the air shaft."}]}', 0, 130, 1),
(131, 'extra', 0, 'Caçada Semanal: Escamas do Pântano', 'Weekly Hunt: Swamp Scales', 'Os curtumes da Vila da Névoa pagam caro por escamas de serpente do pântano, que viram botas à prova d\'água. Toda semana o pedido se renova.', 'The Mist Village tanneries pay well for swamp serpent scales, which become waterproof boots. The order is renewed every week.', 'Rank C.', 'Curtidor Ibuki', 'personagem9', 0, 4, 20, 'coletar', 'Escama de Serpente do Pântano', 3, 1000, 1100, '', 1, 168, '{"monstro": 173, "chance": 50}', 0, 131, 1),
(132, 'extra', 0, 'Plantão na Torre Solar', 'Shift at the Sun Tower', 'Rensuke precisa dormir um pouco. Assuma o plantão da torre: enfrente três ameaças seguidas sem abandonar o posto para descansar.', 'Rensuke needs some sleep. Take over the tower watch: face three threats in a row without leaving your post to rest.', 'Rank C.', 'Vigia Rensuke', 'personagem10', 11, 0, 18, 'sobreviver', '', 3, 800, 600, '', 1, 24, '{"nivel": 10}', 0, 132, 1),
(133, 'extra', 0, 'O Oni da Montanha Vulcânica', 'The Oni of the Volcanic Mountain', 'O Oni desceu da montanha vulcânica e as cinzas dele estão matando as plantações do vilarejo. Os anciãos acenderam um incenso de quarenta e cinco minutos: derrote o Oni duas vezes antes que ele se apague, ou a bênção se perde.', 'The Oni has come down from the volcanic mountain and its ashes are killing the village crops. The elders lit a forty-five-minute incense stick: defeat the Oni twice before it burns out, or the blessing is lost.', 'Rank A.', 'Ancião Tetsuo', 'personagem4', 0, 7, 52, 'derrotar_monstro', '184', 2, 9000, 6000, 'Poção Verde de Soldado', 0, 24, '', 45, 133, 1),
(134, 'extra', 0, 'O Banquete do Castelo', 'The Castle Banquet', 'O rei oferece um banquete aos heróis do continente e todos devem levar um presente. Uma joia rara e um bom dinheiro para o joalheiro fazem um presente digno. Mas um mercador do porto oferece uma fortuna pela mesma joia...', 'The king is throwing a banquet for the heroes of the continent, and everyone must bring a gift. A rare jewel and a good sum for the jeweler make a worthy present. But a merchant at the port offers a fortune for that same jewel...', 'Rank S.', 'Mordomo Kuroda', 'personagem6', 0, 10, 55, 'cadeia', '', 2, 0, 0, '', 0, 24, '{"etapas": [{"tipo": "coletar", "alvo": "Rubi", "quantidade": 1, "chance": 25, "texto": "Encontre um rubi digno do rei.", "texto_en": "Find a ruby worthy of the king."}, {"tipo": "pagar_ryou", "alvo": "", "quantidade": 5000, "texto": "Pague o joalheiro para lapidar a joia.", "texto_en": "Pay the jeweler to cut the gem."}], "opcoes": [{"rotulo": "Presentear o rei", "rotulo_en": "Present it to the king", "texto": "O salão inteiro aplaude. O rei grava o seu nome na mesa dos heróis.", "texto_en": "The whole hall applauds. The king carves your name into the heroes’ table.", "exp": 15000, "ryou": 0, "item": ""}, {"rotulo": "Vender ao mercador do porto", "rotulo_en": "Sell it to the port merchant", "texto": "Você chega ao banquete de mãos vazias, mas com os bolsos cheios.", "texto_en": "You arrive at the banquet empty-handed, but with full pockets.", "exp": 0, "ryou": 16000, "item": ""}]}', 0, 134, 1);
"""
W(MISSOES_NOVAS_SQL)
# Comprador de pergaminhos distante (cânions do extremo oeste) e poções empilháveis.
W("""
INSERT INTO `naruto_npcs` (`id`, `nome`, `avatar`, `icone_mapa`, `latitude`, `longitude`, `fala`, `fala_en`, `ativo`, `papel`) VALUES
(15, 'Eremita Kaizō', 'personagem9', 'arvore_personagem6', 40, -380, 'Poucos chegam até estes cânions, %s. Pergaminhos de jutsu valem ouro para quem os estuda longe das vilas.', 'Few ever reach these canyons, %s. Jutsu scrolls are worth gold to those who study them far from the villages.', 1, 'comprador_pergaminhos');
""")
# Jutsus 20-49, pergaminhos de jutsu (drops 102-116) e as linhas deles nas tabelas de drops (gerado por tools/jutsus_novos.py).
W(open(__file__.rsplit('/', 1)[0] + '/jutsus_novos.sql', encoding='utf8').read())
# Jutsus exclusivos de missão 50-55 e missões 140-145 com jutsu de recompensa (gerado por tools/jutsus_missoes_exclusivos.py).
W(open(__file__.rsplit('/', 1)[0] + '/jutsus_missoes_exclusivos.sql', encoding='utf8').read())
# Especializações: tabela classes (modificadores e Senjutsu por classe), jutsus exclusivos 56-67 e tabela levels rebalanceada (gerado por tools/classes_especializacao.py).
W(open(__file__.rsplit('/', 1)[0] + '/classes_especializacao.sql', encoding='utf8').read())
# Alquimia 2.0: tabela receitas, colunas users/control.alquimia*, materiais/poções/livros 117-135 (gerado por tools/alquimia.py).
W(open(__file__.rsplit('/', 1)[0] + '/alquimia.sql', encoding='utf8').read())
W("UPDATE `naruto_drops` SET `empilhavel`=1 WHERE `consumivel`<>'';\n")
# Avatares 5.0 (sem contas VIP): catálogo naruto_avatares, Pergaminhos de Disfarce 301-308, missões 160-162, link de doação/GitHub (tools/avatares.sql).
W(open(__file__.rsplit('/', 1)[0] + '/avatares.sql', encoding='utf8').read())
# TP/NP/EP, teleportes, jutsus de menu/Senjutsu 90-99, itens de compensação e drops 420-430, NPCs 30-31 e missões 170-176 (tools/pontos_jutsus_especiais.py).
W(open(__file__.rsplit('/', 1)[0] + '/pontos_jutsus_especiais.sql', encoding='utf8').read())
# Poções pequenas de começo de jogo (drops 431-435), colunas drops.precoloja/towns.consumiveislist e drops nos inimigos até o nível 10 (tools/pocoes_pequenas.sql).
W(open(__file__.rsplit('/', 1)[0] + '/pocoes_pequenas.sql', encoding='utf8').read())
# Índices da otimização de SQL (docs/OTIMIZACAO_SQL.md): só ADD INDEX IF NOT EXISTS (tools/otimizacao_indices.sql).
W(open(__file__.rsplit('/', 1)[0] + '/otimizacao_indices.sql', encoding='utf8').read())
# Ajuste temático dos terrenos do mini mapa: Folha cercada de floresta (trilhas começam a 6 casas), Myoboku, Nuvem, Névoa e Castelo (tools/terrenos_ajuste.sql).
W(open(__file__.rsplit('/', 1)[0] + '/terrenos_ajuste.sql', encoding='utf8').read())
# Treinos longos do Painel de Treinos: tabela naruto_treinos, colunas users.treino_* e o jutsu 100 (tools/treinos_novos.sql, docs/TREINOS.md).
W(open(__file__.rsplit('/', 1)[0] + '/treinos_novos.sql', encoding='utf8').read())
# Troca de vila natal: colunas users.vilatroca_em / control.trocavila_dias, embaixadores 32-36 e transferências 177-181 (tools/troca_vila.sql, docs/TROCA_DE_VILA.md).
W(open(__file__.rsplit('/', 1)[0] + '/troca_vila.sql', encoding='utf8').read())
# Cadastro sem escolha de vila (control.vilainicial), troca de dificuldade (users.dificuldadetroca_em, control.trocadif_dias, NPC 40, provações 190-192) e histórico maior (control.historicomax) (tools/dificuldade_historico.sql, docs/TROCA_DE_DIFICULDADE.md).
W(open(__file__.rsplit('/', 1)[0] + '/dificuldade_historico.sql', encoding='utf8').read())
# PvP no mundo ligado por padrão, caveira exposta depois de matar (mesmo offline) e vilas fechadas para quem tem caveira (tools/pvp_caveira.sql, docs/PVP.md).
W(open(__file__.rsplit('/', 1)[0] + '/pvp_caveira.sql', encoding='utf8').read())
# Doujutsu (técnicas oculares): tabela naruto_doujutsu, colunas users.doujutsu*, control.dj_* e treinos 7-12 (tools/doujutsu.sql, docs/DOUJUTSU.md).
W(open(__file__.rsplit('/', 1)[0] + '/doujutsu.sql', encoding='utf8').read())
# Nomes originais para os inimigos que tinham nome de personagem da obra (tools/nomes_originais.py -> tools/gerar_nomes_originais.py -> tools/nomes_originais.sql).
W(open(__file__.rsplit('/', 1)[0] + '/nomes_originais.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/fuga.sql', encoding='utf8').read())
# Viajar Para mais caro que o Pergaminho de Retorno, custo por distância e Selo de Viagem (drop 436) para outras vilas (tools/viagem_custo.sql).
W(open(__file__.rsplit('/', 1)[0] + '/viagem_custo.sql', encoding='utf8').read())
# Limite de contas logadas ao mesmo tempo por IP: users.loginip e control.loginip_max (tools/login_ip.sql, docs/SEGURANCA.md).
W(open(__file__.rsplit('/', 1)[0] + '/login_ip.sql', encoding='utf8').read())
# Durabilidade dos itens fortes: items/drops.durabilidade, control.durabilidade_ativa/reparo_pct (tools/durabilidade.py -> tools/durabilidade.sql, docs/DURABILIDADE.md).
W(open(__file__.rsplit('/', 1)[0] + '/durabilidade.sql', encoding='utf8').read())
# Imunidades dos inimigos por tipo: monsters.imunidades (ninjutsu, taijutsu, elementos, sono) e imunidades temáticas (tools/imunidades.sql).
W(open(__file__.rsplit('/', 1)[0] + '/imunidades.sql', encoding='utf8').read())
# Missões de começo de jogo no quadro da Vila da Folha (200-206) (tools/missoes_konoha.sql, tests/missoes_konoha_teste.py).
W(open(__file__.rsplit('/', 1)[0] + '/missoes_konoha.sql', encoding='utf8').read())
# Dicas por toque em celulares e tablets: users.avisotoque (aviso mostrado uma vez por conta; tools/aviso_toque.sql, js/toque.js).
W(open(__file__.rsplit('/', 1)[0] + '/aviso_toque.sql', encoding='utf8').read())
# Inimigos novos 236-250 (tools/inimigos_novos2.py -> tools/inimigos_novos2.sql).
W(open(__file__.rsplit('/', 1)[0] + '/inimigos_novos2.sql', encoding='utf8').read())
# Quarto acessório: users.slot4id/slot4name e durabilidade com 8 posições (tools/quarto_acessorio.sql).
W(open(__file__.rsplit('/', 1)[0] + '/quarto_acessorio.sql', encoding='utf8').read())
# Regeneração de CH/TP/NP/EP por passo e os drops 437-444 (tools/itens_regeneracao.sql).
W(open(__file__.rsplit('/', 1)[0] + '/itens_regeneracao.sql', encoding='utf8').read())
# Imunidades a elemento nos inimigos do começo (tools/imunidades_comeco.sql).
W(open(__file__.rsplit('/', 1)[0] + '/imunidades_comeco.sql', encoding='utf8').read())
# Taijutsu (spells.type 7): Lótus viram ataques, reforços renomeados, 6 taijutsus novos e pergaminhos 445-453 (tools/taijutsu.sql).
W(open(__file__.rsplit('/', 1)[0] + '/taijutsu.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/nivel_regional.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/missoes_diarias.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/chefes_semanais.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/conjuntos.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/talentos.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/clas_temporadas.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/balanco_xp_chakra_pocoes.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/icones_log.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/cartas_raridade.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/raridade_bonus.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/raridade_teto.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/roubo_status.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/aparecimento.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/convidado.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/ajustes_visuais.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/armas_escudo.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/ajuste_rinnegan.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/pocoes_drop_iniciais.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/jounin_selo.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/pvp_protecao10.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/chance_fuga.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/chance_fuga_inimigos.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/elemento_principal.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/itens_ep.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/inimigos_novos3.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/genjutsu_imunidade.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/itens_batalha.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/variantes_distribuicao.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/itens_descanso.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/genjutsu_buff.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/jutsus_novos4.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/taijutsu_fisico.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/taijutsu_nomes.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/roubo_ryou.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/inimigos_novos4.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/inimigos_novos5.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/inimigos_novos6.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/drops_variados.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/ferramentas_ajuste.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/ferramentas_fortes.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/genjutsu_status_longos.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/equip_drop_ajuste.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/selo_viagem_inicio.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/missao_selo_viagem.sql', encoding='utf8').read())
# Drop pendente preso à coordenada da luta: users.droplat/droplon (tools/drop_coordenada.sql, cookies.php).
W(open(__file__.rsplit('/', 1)[0] + '/drop_coordenada.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/alquimia_pocoes_qtd.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/selo_viagem_alquimia.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/missao_selo_viagem2.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/gm_permissoes.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/variantes_v2.sql', encoding='utf8').read())
W(open(__file__.rsplit('/', 1)[0] + '/itens_v51.sql', encoding='utf8').read())
open(OUT, 'w', encoding='utf8').write('\n'.join(out))
print('ok', len(rows), 'monstros originais +', len(NOVOS))
