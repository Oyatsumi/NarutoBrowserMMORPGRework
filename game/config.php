<?php // config.php :: Variáveis de baixo nível do jogo (conexão com o banco e segredos).
// Este arquivo é gerado pelo instalador (install/install.sh). NÃO publique a versão com senhas reais.

$dbsettings = Array(
        "server"        => "localhost",          // Servidor MySQL/MariaDB.
        "user"          => "narutorpg",          // Usuário do banco.
        "pass"          => "TROQUE_ESTA_SENHA",  // Senha do banco.
        "name"          => "narutorpg",          // Nome do banco.
        "prefix"        => "naruto",             // Prefixo das tabelas (naruto_users, naruto_monsters...).
        "secretword"    => "TROQUE_ESTA_PALAVRA_SECRETA", // Segredo usado para assinar o cookie de login e os tokens anti-CSRF.
        "cookiename"    => "narutorpg",          // Nome do cookie de login.
        "timezone"      => "America/Sao_Paulo",  // Fuso horário usado nas datas do jogo.
        "debug"         => false);               // true mostra erros detalhados na tela (use só em desenvolvimento).

?>
