<?php
// mostrarmainmsg.php :: Janela flutuante de mensagens (jutsu de busca, mensagem privada, senjutsu, fórum...).
// O conteúdo vem do campo users.mainmsg e é apagado (vira "None") assim que é exibido uma vez.
global $mainmsg, $userrow;
$mainmsg = ""; $nomeimagem = "naruto"; $titulojanela = "";
$textomainmsg = (is_array($userrow) && isset($userrow['mainmsg'])) ? (string)$userrow['mainmsg'] : "None";
if ($textomainmsg == "") { $textomainmsg = "None"; }
$explodirmsg = explode(",,",$textomainmsg);

//Justu de busca, formulário de preenchimento do nome.
if ($explodirmsg[0] == 1){
					

					if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false);
					die(); }
							
			if ($userrow["jutsudebuscahtml"] != 1) {header("Location: ./index.php?conteudo=".rawurlencode(t("Você não pode usar esse jutsu sem ter treinado!")));die();}
							
				$nomebotao = "botaobusca";
				$mainmsg = "<center><form action=\"mainmsg.php?do2=usarjutsubusca\" method=\"post\">
			<br>".t("Qual jogador você quer procurar?")." <br><img src=\"images/seta.gif\">".t("Custo")." (<font color=\"darkblue\">CH: 30</font>).<br><br>
			".t("Nome do Jogador:")."<br> <input type=\"text\" name=\"nomedaprocura\" style=\"width:150px;box-sizing:border-box;vertical-align:middle\">
			<button type=\"submit\" id=\"$nomebotao\" name=\"submit\" value=\"OK\" class=\"btnjogo bj-confirmar\"><img src=\"images/botao_procurar.png\" width=\"16\" height=\"16\" alt=\"\" /> OK</button>";
				$nomeimagem = "byakugan";
				$titulojanela = t("ATIVANDO O JUTSU DE BUSCA");
						
		
}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
//Enviar pm.
elseif ($explodirmsg[0] == 2){
	
		if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false);
		die(); }
		
	$nomebotao = "botaomsg3";
    $mainmsg = "<center><form action=\"mainmsg.php?do2=enviarpm\" method=\"post\">
".t("Nome do Jogador:")."<br>
<input type=\"text\" name=\"nomedaprocura\" style=\"width:180px;box-sizing:border-box\" value=\"".(isset($explodirmsg[1]) ? $explodirmsg[1] : "")."\"><br><br>
".t("Mensagem para o Jogador:")."<br><input name=\"txtmensagem\" type=\"text\" maxlength=\"100\" style=\"width:180px;box-sizing:border-box\"><br>
<button type=\"submit\" name=\"submit\" id=\"$nomebotao\" value=\"OK\" class=\"btnjogo bj-confirmar\" style=\"margin-top:4px\"><img src=\"images/botao_enviar.png\" width=\"16\" height=\"16\" alt=\"\" /> ".t("Enviar")."</button><br>
<font color=brown>".t("Você só pode enviar mensagens com até 100 caracteres.")."</font>
</form></center>";
		$nomeimagem = "naruto";
		$titulojanela = t("ENVIAR MENSAGEM PRIVADA");
						
		
}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
//Senjutsu mensagem.
elseif ($explodirmsg[0] == 3){
	
		if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false);
		die(); }
		
    include_once(__DIR__.'/../funcoesinclusas.php'); // senjutsubonustexto(): bônus do Senjutsu da especialização.
    $mainmsg = t("Você ativou o senjutsu e como consequência obteve:")."<br><br><img src=\"images/seta.gif\">".implode("<br><img src=\"images/seta.gif\">", senjutsubonustexto(intval($userrow["charclass"]), intval($userrow["level"])))."<br><br>".t("Com duração até o fim de seus <font color=\"darkgreen\">NP (Natural Points)</font>.")."<br><br>".t("Ao ativar o senjutsu você gastará 1 <font color=\"darkgreen\">NP</font> a cada 3 segundos; o tempo continua contando mesmo que você não atualize sua página de jogo.");
		$nomeimagem = "senjutsu";
		$titulojanela = t("Você ativou o Senjutsu");
						
		
}
	
	
	
	
	
	
	
	
	
	
	
//Mensagem do fórum.
elseif ($explodirmsg[0] == 4){
	
		if ($userrow == false) { display(t("Por favor faça o <a href=\"login.php?do=login\">log in</a> no jogo antes de executar essa ação."),t("Erro"),false,false,false);
		die(); }
		
    $mainmsg = t("Ao utilizar o fórum você está sob as condições de regra do mesmo. Evite postar coisas sem sentido ou sem conteúdo, evite os posts iguais e o flood. Aqui seu tópico pode ser apagado sem nenhum aviso prévio. Você pode e deve postar qualquer tipo de tópico que tenha como sentido e conteúdo o próprio RPG. Muito obrigado pela sua compreensão.");
		$nomeimagem = "sasuke";
		$titulojanela = t("Usando o fórum in-game");
						
		
}




	
	
	
	

elseif($explodirmsg[0] == "None"){
	
	$mainmsg = "";
	
	}

else{
	$variavelaux = explode("#$%;",$textomainmsg);
	$nomeimagem = preg_replace("/[^a-z0-9_]/i", "", $variavelaux[0]);
	// Título e texto podem ter sido gravados com i18n(): são traduzidos no idioma de quem lê.
	$titulojanela = isset($variavelaux[1]) ? textoi18n($variavelaux[1]) : "";
	$mainmsg = isset($variavelaux[2]) ? textoi18n(preg_replace("~^(\\s*<br\\s*/?>)+~i", "", $variavelaux[2])) : "";
}
	
	
	
	
		
	
	

if (($textomainmsg != "None")  && ($userrow != false)){$updatequery = doquery("UPDATE {{table}} SET mainmsg='None' WHERE id='".intval($userrow['id'])."' LIMIT 1","users");
$titulojanela = mb_strtoupper($titulojanela, 'UTF-8');
$mainmsg = "<table width=\"330\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:#e4d094;border-collapse:collapse\" background=\"layoutnovo/dropmenu/grande/meio.gif\"><tr><td background=\"layoutnovo/dropmenu/grande/cima.gif\" height=\"28\" style=\"background-repeat:no-repeat;;background-position:left top\"><table cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tr><td width=\"262\"><div style=\"position: relative; padding-left:6px;\"><font size=\"+1\" face=\"impact,DejaVu Sans Condensed,sans-serif\">$titulojanela</font></div></td><td width=\"*\"><img src=\"layoutnovo/dropmenu/grande/arrastar.gif\" border=\"0\"><a href=\"javascript: fechargrande()\" title=\"".t("Fechar Janela")."\"><img src=\"layoutnovo/dropmenu/grande/fechar.jpg\" border=\"0\"></a></td></tr></table></td></tr><tr><td background=\"layoutnovo/dropmenu/grande/meio.gif\"><div style=\"padding-left:10px; padding-right:10px;\"><img src=\"layoutnovo/dropmenu/grande/avatares/$nomeimagem.jpg\" align=\"left\"><center>$mainmsg</center><div style=\"clear:both;height:5px;line-height:0;font-size:0\"></div></div></td></tr><tr><td style=\"height:6px;line-height:0;font-size:0;padding:0;background:url(layoutnovo/dropmenu/grande/fim.gif) no-repeat left top\"></td></tr></table>";
}
?>