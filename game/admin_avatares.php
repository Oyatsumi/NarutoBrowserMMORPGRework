<?php // admin_avatares.php :: Painel do Administrador - avatares (modo de cada avatar: livre/drop/missão/especial) e dar um avatar a um jogador.
// Mesmo controle de acesso do admin.php (authlevel = 1) e mesmo layout (admindisplay / templates/admin.php).
// Toda ação é um POST com token anti-CSRF e vai para o registro de auditoria (tabela adminlog).
// Regras dos avatares: veja o cabeçalho de funcoesavatares.php.

include('lib.php');
include('cookies.php');
$link = opendb();
$userrow = checkcookies();
if ($userrow == false) { header("Location: login.php?do=login"); die(); }
if ($userrow["authlevel"] != 1) { header("Location: index.php"); die(); }
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysqli_fetch_array($controlquery);
include_once('funcoesavatares.php');
include_once('painelseguranca.php');
painelexigir("admin");

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_confere()) { admindisplay("<div class=\"erro\">".t("Token de segurança inválido ou expirado. Volte, recarregue a página e tente novamente.")."</div>", t("Erro")); }
    registrarlog("avatares:".preg_replace("/[^a-z0-9_]/i", "", pegartexto($_POST, "acao")), pegarint($_POST, "id"), painelresumopost());
}

avlista();

function avmodos() { return array("livre" => t("Livre (todos têm)"), "drop" => t("Drop (Pergaminho de Disfarce)"), "missao" => t("Recompensa de missão"), "especial" => t("Especial (regra no código)")); } // Modos possíveis.

function avlista() { // Lista/edição dos avatares + formulário "dar avatar a um jogador".

    $mensagem = "";
    $acao = pegartexto($_POST, "acao");
    if ($acao == "salvar") {
        $modos = avmodos();
        foreach (avtodos() as $id => $r) {
            $modo = pegartexto($_POST, "modo".$id, $r["modo"]);
            if (!isset($modos[$modo])) { continue; }
            $nome = mb_substr(trim(pegartexto($_POST, "nome".$id, $r["nome"])), 0, 50, "UTF-8");
            if ($nome == "") { $nome = $r["nome"]; }
            $ativo = isset($_POST["ativo".$id]) ? 1 : 0;
            $ordem = max(-999, min(999, pegarint($_POST, "ordem".$id, intval($r["ordem"]))));
            doquery("UPDATE {{table}} SET nome='$nome', modo='$modo', ativo='$ativo', ordem='$ordem' WHERE id='".intval($id)."' LIMIT 1", "avatares");
        }
        $mensagem = "<div class=\"ok\">".t("Avatares salvos.")."</div>";
    } elseif ($acao == "dar") {
        $jogador = pegartexto($_POST, "jogador");
        $idav = pegarint($_POST, "avatar");
        $alvo = mysqli_fetch_array(doquery("SELECT id,charname FROM {{table}} WHERE charname='$jogador' LIMIT 1", "users"));
        if (!$alvo) { $mensagem = "<div class=\"erro\">".t("Jogador não encontrado.")."</div>"; }
        elseif (!file_exists("layoutnovo/avatares/".$idav.".jpg") || !mysqli_fetch_array(doquery("SELECT id FROM {{table}} WHERE id='$idav' LIMIT 1", "avatares"))) { $mensagem = "<div class=\"erro\">".t("Avatar inválido.")."</div>"; }
        elseif (avatarliberar($alvo["id"], $idav)) {
            registrarlog("avatares:dado", $alvo["id"], tf("Avatar %s liberado para %s", $idav, $alvo["charname"]));
            $mensagem = "<div class=\"ok\">".tf("Avatar <b>%s</b> liberado para <b>%s</b>.", avatarnome($idav), $alvo["charname"])."</div>";
        } else { $mensagem = "<div class=\"aviso\">".tf("%s já tinha esse avatar.", $alvo["charname"])."</div>"; }
    }

    $csrf = csrf_campo();
    $modos = avmodos();
    $linhas = ""; $opav = "";
    foreach (avtodos() as $id => $r) {
        $opmodo = "";
        foreach ($modos as $v => $rot) { $opmodo .= "<option value=\"$v\"".($r["modo"] == $v ? " selected=\"selected\"" : "").">$rot</option>"; }
        $opav .= "<option value=\"$id\">#$id ".t($r["nome"])."</option>";
        $donos = mysqli_fetch_array(doquery("SELECT COUNT(*) AS n FROM {{table}} WHERE avatar='$id' OR FIND_IN_SET('$id', avatares_liberados)", "users"));
        $linhas .= "<tr><td>$id</td><td><img src=\"layoutnovo/avatares/$id.jpg\" width=\"41\" height=\"50\" alt=\"\" /> <img src=\"layoutnovo/graficos/{$id}_stance.gif\" height=\"50\" alt=\"\" /></td>"
            ."<td><input type=\"text\" name=\"nome$id\" value=\"".htmlspecialchars($r["nome"])."\" maxlength=\"50\" style=\"width:130px\" /></td>"
            ."<td><select name=\"modo$id\">$opmodo</select></td>"
            ."<td><input type=\"checkbox\" name=\"ativo$id\" value=\"1\"".($r["ativo"] ? " checked=\"checked\"" : "")." /></td>"
            ."<td><input type=\"number\" name=\"ordem$id\" value=\"".intval($r["ordem"])."\" style=\"width:55px\" /></td>"
            ."<td class=\"small\">".($r["modo"] == "livre" ? "-" : avatarcomoliberar($id))."</td><td>".intval($donos["n"])."</td></tr>";
    }

    $t = "t";
    $page = <<<FIM
<h1>{$t("Avatares")}</h1>$mensagem
<p>{$t("Avatar = retrato (layoutnovo/avatares/N.jpg) + animações de batalha e minimapa (layoutnovo/graficos/N_*.gif). Não há mais contas VIP: cada avatar é livre, cai de inimigos como Pergaminho de Disfarce (itens de drop com \"Libera o avatar\", tabela de drops dos inimigos) ou é recompensa de missão (campo \"Avatar de recompensa\" no editor de missões). O avatar que o jogador está usando sempre continua liberado.")}</p>
<p><a class="botao" href="admin.php?do=drops">{$t("Itens de drop")}</a> <a class="botao" href="admin_missoes.php">{$t("Missões")}</a></p>
<form action="admin_avatares.php" method="post">$csrf<input type="hidden" name="acao" value="salvar" />
<table class="lista"><tr><th>ID</th><th>{$t("Imagens")}</th><th>{$t("Nome")}</th><th>{$t("Como é obtido")}</th><th>{$t("Ativo")}</th><th>{$t("Ordem")}</th><th>{$t("Onde (calculado)")}</th><th>{$t("Jogadores")}</th></tr>
$linhas
</table>
<p><input type="submit" value="{$t("Salvar")}" /></p></form>
<h2>{$t("Dar um avatar a um jogador")}</h2>
<form action="admin_avatares.php" method="post">$csrf<input type="hidden" name="acao" value="dar" />
<p>{$t("Personagem:")} <input type="text" name="jogador" maxlength="30" /> {$t("Avatar:")} <select name="avatar">$opav</select> <input type="submit" value="{$t("Liberar avatar")}" /> <span class="small">{$t("Fica no registro de auditoria.")}</span></p></form>
FIM;
    admindisplay($page, t("Avatares"));

}

function avtodos() { // Todos os avatares do catálogo (inclusive desativados).
    $lista = array();
    $q = doquery("SELECT * FROM {{table}} ORDER BY ordem, id", "avatares");
    while ($r = mysqli_fetch_array($q, MYSQLI_ASSOC)) { $lista[intval($r["id"])] = $r; }
    return $lista;
}

?>
