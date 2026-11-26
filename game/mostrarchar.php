<?php // mostrarchar.php :: Perfil público de um jogador (popup), procurado pelo nome: mostrarchar.php?nomechar=Nome.
// Abre também para quem não está logado (é o "Link do Personagem" que os jogadores divulgam).
// A ficha é montada por fichapublica() (funcoesficha.php), também usada pelo index.php?do=onlinechar.

include_once('lib.php');
$link = opendb();
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);

// O nome já chega escapado pelo lib.php, então pode ir entre aspas na query.
$nomechar = pegartexto($_GET, "nomechar");
$userquery = doquery("SELECT * FROM {{table}} WHERE charname='$nomechar' LIMIT 1", "users");
if (mysqli_num_rows($userquery) != 1) { echo t("Nenhum usuário."); die(); }
$userrow = mysqli_fetch_array($userquery);
// Campos da ficha (só dados públicos): fichapublica() do funcoesficha.php, a mesma do index.php?do=onlinechar.
include_once('funcoesficha.php');
$ficha = fichapublica($userrow);

// Link público do perfil, montado a partir do endereço do jogo cadastrado no painel (naruto_control.gameurl).
$endereco = preg_replace("/index\.php$/i", "", trim((string)$controlrow["gameurl"]));
if ($endereco != "" && substr($endereco, -1) != "/") { $endereco .= "/"; }
$linkperfil = $endereco."mostrarchar.php?nomechar=".rawurlencode(html_entity_decode($userrow["charname"], ENT_QUOTES, 'UTF-8'));
$embaixo = "<center><font color=\"white\">".t("Link do Personagem:")."</font><br><input type=\"text\" size=\"20\" value=\"".htmlspecialchars($linkperfil, ENT_QUOTES, 'UTF-8')."\"></center>";

$xml = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n"
. "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"".idioma()."\" lang=\"".idioma()."\">\n";
$page = $xml . gettemplate("minimal") . $embaixo;
$array = array("content"=>parsetemplate(gettemplate("onlinechar"), $ficha), "title"=>t("Informação do Personagem"));
echo localizarimagens(parsetemplate($page, $array)); // imagens com texto na versão do idioma (layoutnovo/.../en/).
die();

?>
