<?php // creditos.php :: Página pública de créditos e doação (link "Ajude os desenvolvedores doando" do rodapé).
// O botão de doação usa control.linkdoacao e o link do código-fonte usa control.linkgithub (Configurações gerais do admin).

include('lib.php');
$link = opendb();
include('cookies.php');
$userrow = checkcookies(); // a página também abre sem login.
$controlrow = mysqli_fetch_array(doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control"));
include_once('funcoesdoacao.php'); // botaodoacao()


$t = "t";
// control.linkgithub aceita vários endereços separados por espaço, vírgula ou quebra de linha.
$links = array();
foreach (preg_split('/[\s,]+/', trim(html_entity_decode((string)$controlrow["linkgithub"], ENT_QUOTES, "UTF-8"))) as $url) {
    if ($url != "" && preg_match('#^https?://#i', $url)) { $url = htmlspecialchars($url, ENT_QUOTES); $links[] = "<a href=\"$url\" target=\"_blank\" rel=\"noopener\">$url</a>"; }
}
// O rework (versão atual) vem antes do repositório antigo, em qualquer ordem que estiver na configuração.
usort($links, function ($a, $b) { return (stripos($b, "rework") !== false) <=> (stripos($a, "rework") !== false); });
$linkgithub = count($links) ? implode("<br>", $links) : t("(endereço não configurado)");
$botao = botaodoacao($controlrow);
$voltar = ($userrow != false) ? "<a href=\"index.php\">".t("Voltar ao jogo")."</a>" : "<a href=\"login.php?do=login\">".t("Entrar no jogo")."</a>";
$page = <<<FIM
<style type="text/css">
.crd { max-width: 620px; margin: 0 auto; text-align: left; font: 12px tahoma, "DejaVu Sans Condensed", arial, sans-serif; line-height: 17px; }
.crd h2 { color: #613003; border-bottom: 1px solid #c9a96b; padding-bottom: 3px; margin: 18px 0 8px 0; font-size: 15px; }
.crdcaixa { background: #FFF1C7; border: 2px solid #e4d094; border-radius: 6px; padding: 12px 16px; }
.crdbotao { display: inline-block; background: linear-gradient(#ffb347, #e46b0c); color: #fff !important; font: bold 18px tahoma, arial, sans-serif; text-decoration: none; padding: 12px 34px; border-radius: 8px; border: 2px solid #8a3a00; box-shadow: 0 3px 0 #8a3a00; }
.crdbotao:hover { background: linear-gradient(#ffc56b, #f07a1a); }
.crdbotao.pequeno { font-size: 14px; padding: 8px 20px; }
.crdaviso { background: #fbe3c0; border: 1px dashed #b06000; padding: 8px; color: #6b3a00; }
.crd ul { margin: 4px 0 4px 18px; padding: 0; }
</style>
<div class="crd">
<h2>{$t("Créditos e doação")}</h2>
<div class="crdcaixa">
<p>{$t("Obrigado por jogar! Este jogo e o motor dele vêm sendo desenvolvidos desde 2008, tanto na parte gráfica quanto na lógica, com muito carinho e muitas noites em claro.")}</p>
<p>{$t("Todo o código é aberto: você pode estudar, melhorar e reaproveitar o motor para criar outros jogos de navegador, não só este de Naruto. Se ele te divertiu ou te ensinou algo, considere apoiar quem mantém o projeto vivo.")}</p>
<center style="margin:14px 0">$botao</center>
<p class="small" style="text-align:center">{$t("Qualquer valor ajuda a pagar servidor, arte e novas atualizações. Muito obrigado!")}</p>
</div>
<h2>{$t("Quem fez")}</h2>
<ul>
<li><b>Oyatsumi</b> — {$t("autor original: criação, programação e desenho do jogo e do motor (2008-2010).")}</li>
<li>{$t("Comunidade e jogadores da primeira versão, pelos testes, ideias e relatos de bugs.")}</li>
<li>{$t("Colaboradores da versão 5.0: atualização para PHP 8 e UTF-8, segurança, tradução para o inglês, mapa maior, especializações, alquimia, missões, duelos e PvP.")}</li>
</ul>
<h2>{$t("Código aberto")}</h2>
<p>{$t("Repositório:")} $linkgithub</p>
<p class="small">{$t("Naruto é uma obra de Masashi Kishimoto. Este é um projeto de fãs, sem fins lucrativos e sem ligação com os detentores dos direitos.")}</p>
<p style="text-align:center">$voltar</p>
</div>
FIM;
display($page, t("Créditos e doação"), false, false, false);
?>
