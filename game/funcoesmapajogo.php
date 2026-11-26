<?php // funcoesmapajogo.php :: "Mapa do jogo" (vilas e regiões dos inimigos) usado no admin.php (do=mapajogo) e no gm.php (do=mapa).
// Funções movidas do admin.php; no painel do GM a página é a mesma, só sem os links de edição.

function admregiao($r) { // Região de um inimigo: array(latmin, latmax, lonmin, lonmax, exclusiva) ou null se não tiver.

    if (!isset($r["regiao_latmin"]) || $r["regiao_latmin"] === null || $r["regiao_latmax"] === null || $r["regiao_lonmin"] === null || $r["regiao_lonmax"] === null) { return null; }
    return array(intval($r["regiao_latmin"]), intval($r["regiao_latmax"]), intval($r["regiao_lonmin"]), intval($r["regiao_lonmax"]), intval($r["regiao_exclusiva"]));

}

function admtextoregiao($regiao) { // Texto "Região: [lat,long] até [lat,long]" (canto de cima/esquerda até o de baixo/direita, como no mapa global).

    if ($regiao === null) { return ""; }
    $texto = tf("Região: [%s,%s] até [%s,%s]", $regiao[1], $regiao[2], $regiao[0], $regiao[3]);
    return $texto." ".(($regiao[4] == 1) ? t("(aparece apenas nesta região)") : t("(aparece com mais frequência nesta região)"));

}

function admretangulo($latmin, $latmax, $lonmin, $lonmax, $px, $estilo, $conteudo = "", $id = "") { // Div posicionada sobre o mini mapa (coordenadas do jogo -> pixels).

    global $controlrow;
    $tamanho = max(1, intval($controlrow["gamesize"]));
    $escala = $px / ($tamanho * 2 + 1);
    $esq = round(($lonmin + $tamanho) * $escala); $topo = round(($tamanho - $latmax) * $escala);
    $largura = max(2, round(($lonmax - $lonmin + 1) * $escala)); $altura = max(2, round(($latmax - $latmin + 1) * $escala));
    $id = ($id != "") ? " id=\"$id\"" : "";
    return "<div$id style=\"position:absolute; left:{$esq}px; top:{$topo}px; width:{$largura}px; height:{$altura}px; box-sizing:border-box; $estilo\">$conteudo</div>";

}

function admmapa($px, $camadas) { // Mini mapa do jogo (mesma imagem do mapa global) com camadas posicionadas por cima.

    return "<div style=\"position:relative; width:{$px}px; height:{$px}px; background:url(images/mapa_grande.png) no-repeat; background-size:{$px}px {$px}px; border:2px solid #452202; overflow:hidden\">$camadas</div>";

}

function mapajogohtml($painel = "admin") { // HTML da página "Mapa do jogo"; $painel "gm" = sem links de edição (só leitura).

    global $controlrow;
    $tamanho = intval($controlrow["gamesize"]);
    $px = 501;
    $cores = array("#e00000", "#0050e0", "#00a000", "#b000b0", "#e07000", "#008080", "#806000", "#e00080");
    $camadas = ""; $legenda = ""; $n = 0;

    $query = doquery("SELECT * FROM {{table}} WHERE regiao_latmin IS NOT NULL AND regiao_latmax IS NOT NULL AND regiao_lonmin IS NOT NULL AND regiao_lonmax IS NOT NULL ORDER BY level, id", "monsters");
    while ($row = mysqli_fetch_array($query)) {
        $regiao = admregiao($row);
        $cor = $cores[$n % count($cores)]; $n++;
        $titulo = $row["name"]." (".tf("nível %s", $row["level"]).") - ".admtextoregiao($regiao);
        $camadas .= admretangulo($regiao[0], $regiao[1], $regiao[2], $regiao[3], $px, "border:2px solid $cor; background:".$cor."33;", ($painel == "gm" ? "<a" : "<a href=\"admin.php?do=editmonster:".$row["id"]."\"")." title=\"$titulo\" style=\"display:block; width:100%; height:100%\"></a>");
        $legenda .= "<tr><td><span style=\"display:inline-block; width:12px; height:12px; background:$cor\"></span></td><td>".($painel == "gm" ? $row["name"] : "<a href=\"admin.php?do=editmonster:".$row["id"]."\">".$row["name"]."</a>")."</td><td>".$row["level"]."</td><td>".admtextoregiao($regiao)."</td></tr>";
    }
    if ($legenda == "") { $legenda = "<tr><td colspan=\"4\">".t("Nenhum inimigo tem região definida: todos aparecem só pela regra do nível.")."</td></tr>"; }

    $vilas = "";
    $query = doquery("SELECT id,name,latitude,longitude FROM {{table}} ORDER BY id", "towns");
    while ($row = mysqli_fetch_array($query)) {
        $lat = intval($row["latitude"]); $lon = intval($row["longitude"]);
        $icone = file_exists("images/24/cidade_".$row["id"].".gif") ? "<img src=\"images/24/cidade_".$row["id"].".gif\" width=\"16\" height=\"16\" alt=\"\" style=\"border:0\" />" : "&#9632;";
        $camadas .= admretangulo($lat, $lat, $lon, $lon, $px, "overflow:visible;", ($painel == "gm" ? "<a" : "<a href=\"admin.php?do=edittown:".$row["id"]."\"")." title=\"".$row["name"]." [$lat,$lon]\" style=\"position:absolute; left:-8px; top:-8px; white-space:nowrap; color:#fff; text-shadow:0 0 3px #000\">$icone ".$row["name"]."</a>");
        $vilas .= "<tr><td>".$row["id"]."</td><td>".($painel == "gm" ? $row["name"] : "<a href=\"admin.php?do=edittown:".$row["id"]."\">".$row["name"]."</a>")."</td><td>[$lat,$lon]</td></tr>";
    }

    // Faixas de nível a cada 50 casas (ajuda a ver qual nível aparece em cada parte do mapa).
    for ($d = 50; $d <= $tamanho; $d += 50) {
        $camadas .= admretangulo(-$d, $d, -$d, $d, $px, "border:1px dashed rgba(255,255,255,0.6); pointer-events:none;", "<span style=\"position:absolute; right:2px; top:0; color:#fff; font-size:10px; text-shadow:0 0 2px #000\">".tf("nível %s", floor(($d + 5) / 5))."</span>");
    }

    $mapa = admmapa($px, $camadas);
$t = "t"; $tf = "tf";
$page = <<<END
<h1>{$t("Mapa do jogo")}</h1>
<p>{$tf("Visão geral (somente leitura) das vilas e das regiões dos inimigos. O mapa vai de -%s a %s; norte (latitude positiva) em cima e leste (longitude positiva) à direita. Os quadrados tracejados mostram o nível dos inimigos pela distância do centro. Clique numa vila ou região para editar.", $tamanho, $tamanho)}</p>
$mapa
<h2>{$t("Regiões dos inimigos")}</h2>
<table class="lista"><tr><th></th><th>{$t("Inimigo")}</th><th>{$t("Nível")}</th><th>{$t("Região")}</th></tr>$legenda</table>
<h2>{$t("Vilas")}</h2>
<table class="lista"><tr><th>{$t("ID")}</th><th>{$t("Nome")}</th><th>{$t("Local")}</th></tr>$vilas</table>
END;
    return $page;

}
