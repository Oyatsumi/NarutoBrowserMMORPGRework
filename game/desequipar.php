<?php // desequipar.php :: Tira um equipamento do jogador e o guarda na mochila (desequipar.php?qual=N) ou o destrói quando a durabilidade acaba (incluído pelo fight.php).

// Este arquivo funciona de dois jeitos:
//  1) acesso direto pelo navegador (desequipar.php?qual=N, N de 1 a 7), a partir dos ícones de equipamento
//     da coluna da direita: o item vai para o primeiro espaço livre da mochila;
//  2) incluído pelo fight.php (vitória), quando um item quebra: o item é apenas apagado ($deletar = true).
// "qual": 1 = arma, 2 = colete, 3 = bandana, 4/5/6/7 = acessórios 1/2/3/4.

if (!function_exists('desequiparitem')){
function desequiparitem($qual, $deletar){ // Retira o equipamento "qual" do jogador. Devolve "ok", "invalido", "vazio" ou "mochilacheia".
	global $userrow;
	include_once('funcoesinclusas.php');

	$qual = intval($qual);
	if ($qual < 1 || $qual > 3 + NUMACESSORIOS) { return "invalido"; }

	// Nome dos campos do slot no banco (weaponname/weaponid, armorname/armorid ... slot4name/slot4id).
	$slots = slotsequipamento();
	$campo = $slots[$qual];
	if ($userrow[$campo."name"] == "None") { return "vazio"; }

	// Lugar na mochila: uma pilha igual com espaço (itens empilháveis) ou o primeiro espaço livre.
	// Mochila cheia só impede quando o item vai para ela.
	$variaveldur = lerdurabilidade($userrow["durabilidade"]);
	// Drops (slots 4 a 7) voltam com o tipo 4, como todo drop na mochila (antes iam com 5/6 e não empilhavam com os iguais).
	$itemsaindo = $userrow[$campo."name"].",".intval($userrow[$campo."id"]).",".($qual <= 3 ? $qual : 4).",".$variaveldur[$qual];
	$mochilanova = $userrow;
	if ($deletar == false && mochilaguardar($mochilanova, $itemsaindo) === false) { return "mochilacheia"; }

	// Retira os bônus do item: arma/colete/bandana vêm da tabela items; slots 4 a 7, da tabela drops.
	if ($qual <= 3) { bonusdoitem($userrow[$campo."id"], $userrow[$campo."name"], -1); }
	else { bonusdodrop($userrow[$campo."id"], -1, $userrow[$campo."name"]); } // o nome leva a variante rara (":efeito").
	limitarpontosatuais();

	// Guarda na mochila no formato "nome,id,tipo,durabilidade" (a durabilidade restante vai junto).
	if ($deletar == false){
		for ($i = 1; $i <= 8; $i++) { $userrow["bp".$i] = $mochilanova["bp".$i]; }
	}

	// Slot vazio: durabilidade X, nome None e id 0.
	$variaveldur[$qual] = "X";
	$userrow["durabilidade"] = juntardurabilidade($variaveldur);
	$userrow[$campo."name"] = "None";
	$userrow[$campo."id"] = 0;

	$equipamentos = "";
	foreach ($slots as $s) {
		$equipamentos .= ", ".$s."name='".addslashes($userrow[$s."name"])."', ".$s."id='".intval($userrow[$s."id"])."'";
	}
	doquery("UPDATE {{table}} SET durabilidade='".addslashes($userrow["durabilidade"])."',bp1='".addslashes($userrow["bp1"])."',bp2='".addslashes($userrow["bp2"])."',bp3='".addslashes($userrow["bp3"])."',bp4='".addslashes($userrow["bp4"])."',bp5='".addslashes($userrow["bp5"])."',bp6='".addslashes($userrow["bp6"])."',bp7='".addslashes($userrow["bp7"])."',bp8='".addslashes($userrow["bp8"])."',".sqlatributosdojogador().$equipamentos." WHERE id='".intval($userrow["id"])."' LIMIT 1", "users");

	return "ok";
}}


// Acesso direto pelo navegador (quando incluído pelo fight.php, o lib.php já definiu NARUTO_RPG).
if (!defined('NARUTO_RPG')) {

	include('lib.php');
	$link = opendb();
	include('cookies.php');
	$userrow = checkcookies();
	include_once('funcoesinclusas.php');

	if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false); die(); }
	if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t('Você não pode desequipar um item no meio de uma batalha!'))); die(); }

	// Trava o jogador (SELECT ... FOR UPDATE) e relê os dados: desequiparitem() regrava a mochila e os equipamentos
	// inteiros, e com dados velhos o mesmo item podia acabar na mochila E no banco (desequipar e depositar juntos).
	doquery("START TRANSACTION", "users");
	$userrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='".intval($userrow["id"])."' LIMIT 1 FOR UPDATE", "users"));
	if ($userrow["currentaction"] == "Fighting") { header('Location: ./index.php?do=fight&conteudo='.rawurlencode(t('Você não pode desequipar um item no meio de uma batalha!'))); die(); }
	$resultado = desequiparitem(pegarint($_GET, "qual"), false);
	doquery("COMMIT", "users");

	if ($resultado == "mochilacheia") { header('Location: ./index.php?conteudo='.rawurlencode(t('Sua mochila já está cheia.'))); die(); }
	if ($resultado == "invalido") { header('Location: ./index.php'); die(); }
	header('Location: '.paginaderetorno());
	die();

}

?>
