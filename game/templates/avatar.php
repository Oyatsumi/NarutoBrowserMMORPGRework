<?php // templates/avatar.php :: Tela de avatares (outros.php?do=avatar). A grade ({{grade}}) é montada em outros.php a partir do catálogo naruto_avatares.
$t = "t"; // {$t("...")} traduz dentro do heredoc.
$template = <<<FIMDOTEMPLATE
<style type="text/css">
.avgrade { border-collapse: separate; border-spacing: 10px 8px; }
.avcel { width: 132px; vertical-align: top; text-align: center; font: 11px tahoma, "DejaVu Sans Condensed", arial, sans-serif; }
.avcel img.avret { display: block; margin: 0 auto 3px auto; border: 0; }
.avbox { position: relative; display: block; width: 98px; height: 120px; margin: 0 auto 3px auto; }
.avbox img { position: absolute; left: 0; top: 0; margin: 0; }
.avcel.bloq img.avmiolo { filter: grayscale(100%) brightness(0.9); clip-path: inset(17% 16% 16% 14%); } /* só dentro da moldura preta */
.avcel.atual img.avret { outline: 3px solid #d6aa3c; }
.avnome { font-weight: bold; color: #613003; }
.avcomo { color: #6b5a3a; font-size: 10px; line-height: 12px; margin-top: 2px; }
.avcadeado { color: #8b0000; font-weight: bold; }
</style>
<center><img src="images/avatar.gif" alt=""></center>
<center><p style="margin:4px 20px">{$t("O avatar é o seu retrato e as suas animações na batalha e no mapa. Alguns são livres; os outros são liberados por Pergaminhos de Disfarce, que caem raramente de inimigos, ou como recompensa de missões.")}</p>
<p><b>{{contagem}}</b></p>
{{grade}}
</center>
FIMDOTEMPLATE;
?>
