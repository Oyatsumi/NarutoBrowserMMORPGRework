#!/usr/bin/env bash
# =============================================================================
#  Naruto! Nigeru RPG :: instalador para servidor Linux (Debian/Ubuntu) — VM ou servidor próprio com SSH/root.
#  (Em hospedagem comum sem SSH, use o install/instalar.php pelo navegador: docs/HOSPEDAGEM.md.)
#
#  Instala do zero: Apache + PHP + MariaDB, cria o banco e o usuário do banco,
#  importa as tabelas do jogo, gera o config.php, cria a conta de administrador,
#  configura o site no Apache e (opcional) HTTPS com Let's Encrypt e envio de e-mail.
#
#  Uso (como root, a partir da pasta do repositório):
#      sudo bash install/install.sh
#
#  Idioma: o instalador pergunta pt/en no início (ou use NARUTO_IDIOMA=en); o mesmo idioma
#  vira o idioma padrão do jogo (pode ser mudado depois no painel).
#
#  Modo automático (sem perguntas): defina as variáveis abaixo antes de rodar e use
#  NARUTO_NAO_PERGUNTAR=1. Ex.:
#      sudo NARUTO_NAO_PERGUNTAR=1 NARUTO_DOMINIO=jogo.exemplo.com \
#           NARUTO_ADMIN_SENHA='minhaSenhaForte' bash install/install.sh
# =============================================================================
set -Eeuo pipefail

VERDE='\033[1;32m'; AMARELO='\033[1;33m'; VERMELHO='\033[1;31m'; AZUL='\033[1;36m'; NORMAL='\033[0m'
info()  { echo -e "${AZUL}==>${NORMAL} $*"; }
ok()    { echo -e "${VERDE}[ok]${NORMAL} $*"; }
aviso() { echo -e "${AMARELO}[aviso]${NORMAL} $*"; }
erro()  { echo -e "${VERMELHO}[erro]${NORMAL} $*" >&2; exit 1; }
# L "texto em português" "text in English": escolhe o texto no idioma do instalador.
L() { if [ "${NARUTO_IDIOMA:-pt}" = "en" ]; then printf '%s' "$2"; else printf '%s' "$1"; fi; }
trap 'erro "$(L "Falha na linha $LINENO. Nada do que já foi feito foi desfeito; corrija o problema e rode o instalador de novo." "Failure at line $LINENO. Nothing already done was undone; fix the problem and run the installer again.")"' ERR

# Idioma do instalador e idioma padrão do jogo (pt ou en).
if [ -z "${NARUTO_IDIOMA:-}" ]; then
    if [ "${NARUTO_NAO_PERGUNTAR:-0}" = "1" ]; then NARUTO_IDIOMA="pt"; else read -r -p "Idioma / Language (pt/en) [pt]: " NARUTO_IDIOMA || true; fi
fi
case "${NARUTO_IDIOMA,,}" in en|english|ingles|inglês) NARUTO_IDIOMA="en";; *) NARUTO_IDIOMA="pt";; esac

PASTA_REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
[ -f "$PASTA_REPO/game/index.php" ] || erro "$(L "Não encontrei a pasta game/ do jogo em $PASTA_REPO." "Could not find the game/ folder in $PASTA_REPO.")"
[ -f "$PASTA_REPO/sql/naruto_rpg.sql" ] || erro "$(L "Não encontrei sql/naruto_rpg.sql em $PASTA_REPO." "Could not find sql/naruto_rpg.sql in $PASTA_REPO.")"
[ "$(id -u)" -eq 0 ] || erro "$(L "Rode como root (ex.: sudo bash install/install.sh)." "Run as root (e.g. sudo bash install/install.sh).")"
command -v apt-get >/dev/null || erro "$(L "Este instalador é para Debian/Ubuntu (apt-get). Veja docs/INSTALACAO.md para instalação manual." "This installer is for Debian/Ubuntu (apt-get). See docs/en/INSTALL.md for a manual install.")"

NAO_PERGUNTAR="${NARUTO_NAO_PERGUNTAR:-0}"

# pergunta "texto" VARIAVEL "padrão"  (respeita a variável de ambiente se já estiver definida)
pergunta() {
    local texto="$1" var="$2" padrao="${3:-}" resposta
    if [ -n "${!var:-}" ]; then return; fi
    if [ "$NAO_PERGUNTAR" = "1" ]; then printf -v "$var" '%s' "$padrao"; return; fi
    read -r -p "$texto [${padrao}]: " resposta || true
    printf -v "$var" '%s' "${resposta:-$padrao}"
}
# pergunta_senha "texto" VARIAVEL  (vazio = gera uma senha aleatória quando permitido)
pergunta_senha() {
    local texto="$1" var="$2" gerar="${3:-nao}" s1 s2
    if [ -n "${!var:-}" ]; then return; fi
    if [ "$NAO_PERGUNTAR" = "1" ]; then
        [ "$gerar" = "sim" ] || erro "$(L "Defina $var para o modo automático." "Set $var for unattended mode.")"
        printf -v "$var" '%s' "$(gerasenha 24)"; return
    fi
    while true; do
        read -r -s -p "$texto: " s1 || true; echo
        if [ -z "$s1" ] && [ "$gerar" = "sim" ]; then s1="$(gerasenha 24)"; echo "   $(L "(senha gerada automaticamente)" "(password generated automatically)")"; printf -v "$var" '%s' "$s1"; return; fi
        if [ ${#s1} -lt 6 ]; then aviso "$(L "A senha precisa ter pelo menos 6 caracteres." "The password must have at least 6 characters.")"; continue; fi
        read -r -s -p "$(L "Repita a senha: " "Repeat the password: ")" s2 || true; echo
        [ "$s1" = "$s2" ] && break
        aviso "$(L "As senhas não conferem, tente de novo." "Passwords do not match, try again.")"
    done
    printf -v "$var" '%s' "$s1"
}
gerasenha() { head -c 96 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | cut -c1-"${1:-24}"; }
sim() { case "${1,,}" in s|sim|y|yes) return 0;; *) return 1;; esac; }

echo -e "${VERDE}"
cat <<'BANNER'
  _   _                 _          ____  ____   ____
 | \ | | __ _ _ __ _   _| |_ ___   |  _ \|  _ \ / ___|
 |  \| |/ _` | '__| | | | __/ _ \  | |_) | |_) | |  _
 | |\  | (_| | |  | |_| | || (_) | |  _ <|  __/| |_| |
 |_| \_|\__,_|_|   \__,_|\__\___/  |_| \_\_|    \____|   installer
BANNER
echo -e "${NORMAL}"

# -----------------------------------------------------------------------------
# 1. Perguntas
# -----------------------------------------------------------------------------
info "$(L "Configuração do servidor" "Server settings")"
IP_PADRAO="$(hostname -I 2>/dev/null | awk '{print $1}')"
pergunta "$(L "Domínio ou IP do servidor (ex.: jogo.meusite.com.br)" "Server domain or IP (e.g. game.mysite.com)")" NARUTO_DOMINIO "${IP_PADRAO:-localhost}"
pergunta "$(L "Caminho no site (/ para a raiz, ou ex.: /narutorpg)" "Path on the site (/ for the root, or e.g. /narutorpg)")" NARUTO_CAMINHO "/"
pergunta "$(L "Pasta de instalação dos arquivos do jogo" "Folder for the game files")" NARUTO_PASTA "/var/www/narutorpg"
pergunta "$(L "Fuso horário do jogo" "Game time zone")" NARUTO_FUSO "America/Sao_Paulo"

info "$(L "Banco de dados (MariaDB)" "Database (MariaDB)")"
pergunta "$(L "Nome do banco de dados" "Database name")" NARUTO_DB_NOME "narutorpg"
pergunta "$(L "Usuário do banco de dados" "Database user")" NARUTO_DB_USUARIO "narutorpg"
pergunta_senha "$(L "Senha do usuário do banco (Enter = gerar automaticamente)" "Database user password (Enter = generate automatically)")" NARUTO_DB_SENHA sim
pergunta "$(L "Prefixo das tabelas" "Table prefix")" NARUTO_PREFIXO "naruto"

info "$(L "Jogo e conta do administrador" "Game and administrator account")"
pergunta "$(L "Nome do jogo" "Game name")" NARUTO_NOME_JOGO "Naruto! Nigeru RPG"
pergunta "$(L "Nome da conta do administrador (login)" "Administrator account name (login)")" NARUTO_ADMIN_CONTA "admin"
pergunta "$(L "Nome do personagem do administrador" "Administrator character name")" NARUTO_ADMIN_PERSONAGEM "Hokage"
pergunta "$(L "E-mail do administrador" "Administrator e-mail")" NARUTO_ADMIN_EMAIL "admin@${NARUTO_DOMINIO}"
pergunta_senha "$(L "Senha do administrador (mín. 6 caracteres)" "Administrator password (min. 6 characters)")" NARUTO_ADMIN_SENHA nao

info "$(L "Opcionais" "Optional")"
# E-mails do jogo (control.emailativo): explica prós e contras. Padrão "n" (sem e-mail: conta liberada na hora;
# senha perdida pelas perguntas secretas ou pelo código de recuperação, ou com ajuda de um GM).
if [ "$NAO_PERGUNTAR" != "1" ] && [ -z "${NARUTO_EMAIL:-}" ]; then
    echo
    echo "$(L "E-mails do jogo (confirmação do cadastro e link para criar senha nova):" "Game e-mails (sign-up confirmation and a link to create a new password):")"
    echo "   + $(L "mais seguro: só o dono do e-mail recupera a conta;" "more secure: only the owner of the e-mail can recover the account;")"
    echo "   - $(L "precisa de um servidor de e-mail funcionando (o instalador configura o Postfix), e muitos provedores mandam para o spam;" "needs a working mail server (the installer sets up Postfix), and many providers send it to spam;")"
    echo "  $(L "Sem e-mail (padrão): a conta já joga ao ser criada; quem esquecer a senha usa as perguntas secretas ou o código de recuperação escolhidos no cadastro, ou pede ajuda a um GM." "Without e-mail (default): the account plays right away; whoever forgets the password uses the security questions or the recovery code chosen at sign-up, or asks a GM for help.")"
    echo "  $(L "Pode ser mudado depois no Painel do Administrador (Configurações gerais)." "It can be changed later in the Administrator Panel (General settings).")"
fi
pergunta "$(L "Ligar os e-mails do jogo e instalar o Postfix? (s/n)" "Turn on game e-mails and install Postfix? (y/n)")" NARUTO_EMAIL "n"
# Lista de IPs que podem abrir os painéis admin/GM (config.php "adminips"). Vazio = qualquer IP (a senha é pedida de novo de qualquer jeito).
pergunta "$(L "IPs liberados para o painel do administrador/GM (separados por vírgula, aceita faixas como 10.0.0.0/8; Enter = qualquer IP)" "IPs allowed to open the administrator/GM panel (comma separated, ranges like 10.0.0.0/8 allowed; Enter = any IP)")" NARUTO_ADMIN_IPS ""
pergunta "$(L "Ativar HTTPS gratuito com Let's Encrypt (precisa de domínio apontando para este servidor)? (s/n)" "Enable free HTTPS with Let's Encrypt (needs a domain pointing to this server)? (y/n)")" NARUTO_HTTPS "n"
if sim "$NARUTO_HTTPS"; then pergunta "$(L "E-mail para avisos do Let's Encrypt" "E-mail for Let's Encrypt notices")" NARUTO_HTTPS_EMAIL "$NARUTO_ADMIN_EMAIL"; fi
# Forçar HTTPS no .htaccess (http:// vira https://). Só funciona se o site tiver certificado; sem ele o jogo fica inacessível.
pergunta "$(L "Forçar HTTPS (redirecionar todo acesso http:// para https://)? Só ative se o site terá certificado. (s/n)" "Force HTTPS (redirect every http:// access to https://)? Only enable it if the site will have a certificate. (y/n)")" NARUTO_FORCAR_HTTPS "n"

# Atualização dinâmica (AJAX): explica prós e contras antes de perguntar. Padrão "n" (modo clássico).
if [ "$NAO_PERGUNTAR" != "1" ] && [ -z "${NARUTO_AJAX:-}" ]; then
    echo
    echo "$(L "Atualização dinâmica das páginas (AJAX): em vez de recarregar a página inteira a cada clique," "Dynamic page updates (AJAX): instead of reloading the whole page on every click,")"
    echo "$(L "o jogo troca só o conteúdo e as colunas laterais (o servidor continua igual)." "the game swaps only the content and the side columns (the server stays the same).")"
    echo "  $(L "Prós:" "Pros:")"
    echo "   + $(L "navegação mais rápida, sem a tela branca piscando entre as páginas;" "faster feel, no white flash between pages;")"
    echo "   + $(L "menos coisa redesenhada a cada clique (só as regiões que mudaram);" "less redrawn on every click (only the regions that changed);")"
    echo "   + $(L "o botão voltar/avançar do navegador continua funcionando." "the browser back/forward buttons still work.")"
    echo "  $(L "Contras:" "Cons:")"
    echo "   - $(L "depende de JavaScript (sem ele o jogo volta sozinho ao modo clássico);" "needs JavaScript (without it the game falls back to classic mode by itself);")"
    echo "   - $(L "extensões e navegadores antigos podem se comportar diferente;" "browser extensions and old browsers may behave differently;")"
    echo "   - $(L "é mais difícil de depurar;" "it is harder to debug;")"
    echo "   - $(L "páginas novas com muito JavaScript próprio precisam ser testadas;" "new pages with a lot of their own JavaScript need testing;")"
    echo "   - $(L "ferramentas de estatística que contam visualizações de página podem contar diferente." "analytics that count page views may count differently.")"
    echo "  $(L "Pode ser mudado depois no Painel do Administrador (Configurações gerais)." "It can be changed later in the Administrator Panel (General settings).")"
fi
pergunta "$(L "Ligar a atualização dinâmica das páginas (AJAX)? (s/n)" "Turn on dynamic page updates (AJAX)? (y/n)")" NARUTO_AJAX "n"
# Link de doação (control.linkdoacao): botão da página creditos.php e da janela do painel do admin. "-" = sem botão.
pergunta "$(L "Link de doação (Stripe; Enter = link dos desenvolvedores, - = sem botão)" "Donation link (Stripe; Enter = developers' link, - = no button)")" NARUTO_LINK_DOACAO "https://donate.stripe.com/7sY4gz51905J1nRd8egQE00"
[ "$NARUTO_LINK_DOACAO" = "-" ] && NARUTO_LINK_DOACAO=""

# Backups automáticos pelo cron (install/backup.sh), gravados FORA da pasta do site.
echo
echo "  $(L "Backups automáticos: n = nenhum; banco = só o banco de dados (pequeno, recomendado); completo = banco + arquivos do jogo (inclui imagens enviadas pelo painel)." "Automatic backups: n = none; banco = database only (small, recommended); completo = database + game files (includes images uploaded in the panel).")"
pergunta "$(L "Backups automáticos (n/banco/completo)" "Automatic backups (n/banco/completo)")" NARUTO_BACKUP "banco"
case "${NARUTO_BACKUP,,}" in n|nao|não|no|-) NARUTO_BACKUP="n";; banco|db|database) NARUTO_BACKUP="banco";; completo|full) NARUTO_BACKUP="completo";; *) erro "$(L "Opção de backup inválida (use n, banco ou completo)." "Invalid backup option (use n, banco or completo).")";; esac
if [ "$NARUTO_BACKUP" != "n" ]; then
    pergunta "$(L "Pasta dos backups (fora da pasta do site)" "Backup folder (outside the website folder)")" NARUTO_BACKUP_PASTA "/var/backups/narutorpg"
    pergunta "$(L "Horário do backup diário (HH:MM)" "Daily backup time (HH:MM)")" NARUTO_BACKUP_HORA "04:10"
    pergunta "$(L "Manter os backups por quantos dias (0 = para sempre)" "Keep backups for how many days (0 = forever)")" NARUTO_BACKUP_DIAS "14"
fi

# Validações simples.
[[ -z "$NARUTO_LINK_DOACAO" || "$NARUTO_LINK_DOACAO" =~ ^https://[A-Za-z0-9./?=_%:@\&+~#-]+$ ]] || erro "$(L "Link de doação inválido (precisa começar com https://)." "Invalid donation link (must start with https://).")"
[[ -z "$NARUTO_ADMIN_IPS" || "$NARUTO_ADMIN_IPS" =~ ^[0-9A-Fa-f.:/,\ ]+$ ]] || erro "$(L "Lista de IPs inválida (use IPs ou faixas CIDR separados por vírgula)." "Invalid IP list (use IPs or CIDR ranges separated by commas).")"
[[ "$NARUTO_DB_NOME" =~ ^[A-Za-z0-9_]+$ ]] || erro "$(L "Nome do banco inválido (use letras, números e _)." "Invalid database name (use letters, numbers and _).")"
[[ "$NARUTO_DB_USUARIO" =~ ^[A-Za-z0-9_]+$ ]] || erro "$(L "Usuário do banco inválido (use letras, números e _)." "Invalid database user (use letters, numbers and _).")"
[[ "$NARUTO_PREFIXO" =~ ^[A-Za-z0-9]+$ ]] || erro "$(L "Prefixo inválido (use só letras e números)." "Invalid prefix (use only letters and numbers).")"
[[ "$NARUTO_ADMIN_CONTA" =~ ^[A-Za-z0-9_-]{1,30}$ ]] || erro "$(L "Conta do administrador inválida (letras, números, _ ou -)." "Invalid administrator account (letters, numbers, _ or -).")"
[[ "$NARUTO_ADMIN_PERSONAGEM" =~ ^[A-Za-z0-9_-]{1,30}$ ]] || erro "$(L "Personagem do administrador inválido (letras, números, _ ou -)." "Invalid administrator character (letters, numbers, _ or -).")"
[[ "$NARUTO_DOMINIO" =~ ^[A-Za-z0-9.-]+$ ]] || erro "$(L "Domínio/IP inválido." "Invalid domain/IP.")"
[ ${#NARUTO_ADMIN_SENHA} -ge 6 ] || erro "$(L "A senha do administrador precisa ter pelo menos 6 caracteres." "The administrator password must have at least 6 characters.")"
if [ "$NARUTO_BACKUP" != "n" ]; then
    [[ "$NARUTO_BACKUP_HORA" =~ ^([01]?[0-9]|2[0-3]):[0-5][0-9]$ ]] || erro "$(L "Horário do backup inválido (use HH:MM)." "Invalid backup time (use HH:MM).")"
    [[ "$NARUTO_BACKUP_DIAS" =~ ^[0-9]+$ ]] || erro "$(L "Dias de backup inválido." "Invalid number of backup days.")"
    [[ "$NARUTO_BACKUP_PASTA" =~ ^/[A-Za-z0-9._/-]+$ ]] || erro "$(L "Pasta de backup inválida (caminho absoluto)." "Invalid backup folder (absolute path).")"
    case "${NARUTO_BACKUP_PASTA%/}/" in "${NARUTO_PASTA%/}/"*|/var/www/*) erro "$(L "A pasta de backup não pode ficar dentro da pasta do site (ficaria baixável pela internet)." "The backup folder cannot be inside the website folder (it would be downloadable from the internet).")";; esac
fi
NARUTO_CAMINHO="/${NARUTO_CAMINHO#/}"; NARUTO_CAMINHO="${NARUTO_CAMINHO%/}"; [ -z "$NARUTO_CAMINHO" ] && NARUTO_CAMINHO="/"
[[ "$NARUTO_CAMINHO" =~ ^/[A-Za-z0-9_/-]*$ ]] || erro "$(L "Caminho inválido." "Invalid path.")"
PROTOCOLO="http"; sim "$NARUTO_HTTPS" && PROTOCOLO="https"
URL_JOGO="${PROTOCOLO}://${NARUTO_DOMINIO}${NARUTO_CAMINHO%/}/index.php"

echo
info "$(L "Resumo" "Summary")"
echo "  $(L "Endereço do jogo ....." "Game address .........."): $URL_JOGO"
echo "  $(L "Pasta dos arquivos ..." "Game files folder ....."): $NARUTO_PASTA"
echo "  $(L "Banco / usuário ......" "Database / user ......."): $NARUTO_DB_NOME / $NARUTO_DB_USUARIO ($(L "prefixo" "prefix") ${NARUTO_PREFIXO}_)"
echo "  $(L "Administrador ........" "Administrator ........."): $NARUTO_ADMIN_CONTA ($(L "personagem" "character") $NARUTO_ADMIN_PERSONAGEM, $NARUTO_ADMIN_EMAIL)"
echo "  $(L "Idioma padrão ........" "Default language ......"): $NARUTO_IDIOMA"
echo "  $(L "Backups ..............." "Backups ..............."): $([ "$NARUTO_BACKUP" = "n" ] && L "nenhum" "none" || echo "$NARUTO_BACKUP, $NARUTO_BACKUP_HORA, $NARUTO_BACKUP_PASTA, $NARUTO_BACKUP_DIAS $(L "dias" "days")")"
echo "  $(L "Atualização dinâmica ." "Dynamic updates (AJAX)."): $(sim "$NARUTO_AJAX" && L "ligada" "on" || L "desligada (clássico)" "off (classic)")"
if [ "$NAO_PERGUNTAR" != "1" ]; then
    read -r -p "$(L "Continuar com a instalação? (s/n) [s]: " "Continue with the installation? (y/n) [y]: ")" CONTINUAR || true
    sim "${CONTINUAR:-s}" || erro "$(L "Instalação cancelada." "Installation cancelled.")"
fi

# -----------------------------------------------------------------------------
# 2. Pacotes
# -----------------------------------------------------------------------------
info "$(L "Instalando pacotes (Apache, PHP, MariaDB)..." "Installing packages (Apache, PHP, MariaDB)...")"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq apache2 mariadb-server libapache2-mod-php php php-cli php-mysql php-gd php-mbstring openssl >/dev/null
if sim "$NARUTO_EMAIL"; then
    echo "postfix postfix/main_mailer_type select Internet Site" | debconf-set-selections
    echo "postfix postfix/mailname string $NARUTO_DOMINIO" | debconf-set-selections
    apt-get install -y -qq postfix >/dev/null && ok "$(L "Postfix instalado (configure SPF/DKIM no seu domínio para os e-mails não caírem no spam)." "Postfix installed (set up SPF/DKIM on your domain so e-mails do not end up in spam).")"
fi
ok "$(L "Pacotes instalados." "Packages installed.") $(php -r 'echo "PHP ".PHP_VERSION;')"

# Inicia os serviços (com ou sem systemd).
iniciar() { if command -v systemctl >/dev/null && [ -d /run/systemd/system ]; then systemctl enable --now "$1" >/dev/null 2>&1 || systemctl restart "$1"; else service "$1" start >/dev/null 2>&1 || true; fi; }
recarregar() { if command -v systemctl >/dev/null && [ -d /run/systemd/system ]; then systemctl reload "$1" || systemctl restart "$1"; else service "$1" reload >/dev/null 2>&1 || service "$1" restart; fi; }
iniciar mariadb || iniciar mysql
for i in $(seq 1 30); do mysqladmin ping >/dev/null 2>&1 && break; sleep 1; done
mysqladmin ping >/dev/null 2>&1 || erro "$(L "O MariaDB não iniciou." "MariaDB did not start.")"

# -----------------------------------------------------------------------------
# 3. Banco de dados
# -----------------------------------------------------------------------------
info "$(L "Criando o banco de dados..." "Creating the database...")"
EXISTE=$(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${NARUTO_DB_NOME}' AND table_name='${NARUTO_PREFIXO}_users';")
if [ "$EXISTE" != "0" ]; then
    aviso "$(L "Já existe um jogo instalado no banco ${NARUTO_DB_NOME} (tabela ${NARUTO_PREFIXO}_users)." "A game is already installed in database ${NARUTO_DB_NOME} (table ${NARUTO_PREFIXO}_users).")"
    APAGAR="n"
    [ "$NAO_PERGUNTAR" = "1" ] && APAGAR="${NARUTO_SOBRESCREVER:-n}" || read -r -p "$(L "Apagar TODAS as tabelas ${NARUTO_PREFIXO}_* e instalar de novo? (digite APAGAR para confirmar): " "Delete ALL ${NARUTO_PREFIXO}_* tables and install again? (type APAGAR to confirm): ")" APAGAR || true
    if [ "$APAGAR" = "APAGAR" ] || [ "$APAGAR" = "s" ]; then
        for t in $(mysql -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='${NARUTO_DB_NOME}' AND table_name LIKE '${NARUTO_PREFIXO}\\_%';"); do mysql -e "DROP TABLE \`${NARUTO_DB_NOME}\`.\`$t\`;"; done
    else
        erro "$(L "Instalação interrompida para não apagar dados existentes." "Installation stopped so existing data is not deleted.")"
    fi
fi
SENHA_SQL="${NARUTO_DB_SENHA//\\/\\\\}"; SENHA_SQL="${SENHA_SQL//\'/\\\'}"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${NARUTO_DB_NOME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${NARUTO_DB_USUARIO}'@'localhost' IDENTIFIED BY '${SENHA_SQL}';
ALTER USER '${NARUTO_DB_USUARIO}'@'localhost' IDENTIFIED BY '${SENHA_SQL}';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP ON \`${NARUTO_DB_NOME}\`.* TO '${NARUTO_DB_USUARIO}'@'localhost';
FLUSH PRIVILEGES;
SQL
sed "s/\`naruto_/\`${NARUTO_PREFIXO}_/g" "$PASTA_REPO/sql/naruto_rpg.sql" | mysql "$NARUTO_DB_NOME"
ok "$(L "Banco" "Database") ${NARUTO_DB_NOME}: $(mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${NARUTO_DB_NOME}' AND table_name LIKE '${NARUTO_PREFIXO}\\_%';") $(L "tabelas criadas." "tables created.")"
mysql "$NARUTO_DB_NOME" -e "UPDATE ${NARUTO_PREFIXO}_control SET idiomapadrao='${NARUTO_IDIOMA}' WHERE id=1;"
AJAX_VALOR=0; sim "$NARUTO_AJAX" && AJAX_VALOR=1
mysql "$NARUTO_DB_NOME" -e "UPDATE ${NARUTO_PREFIXO}_control SET atualizacaodinamica=${AJAX_VALOR} WHERE id=1;"
mysql "$NARUTO_DB_NOME" -e "UPDATE ${NARUTO_PREFIXO}_control SET linkdoacao='${NARUTO_LINK_DOACAO}' WHERE id=1;"

# -----------------------------------------------------------------------------
# 4. Arquivos do jogo
# -----------------------------------------------------------------------------
info "$(L "Copiando os arquivos do jogo para $NARUTO_PASTA..." "Copying the game files to $NARUTO_PASTA...")"
mkdir -p "$NARUTO_PASTA"
cp -a "$PASTA_REPO/game/." "$NARUTO_PASTA/"
rm -rf "$NARUTO_PASTA/install"
SEGREDO="$(openssl rand -hex 32)"
esc_php() { local v="${1//\\/\\\\}"; printf '%s' "${v//\"/\\\"}" | sed 's/\$/\\$/g'; }
cat > "$NARUTO_PASTA/config.php" <<PHP
<?php // config.php :: Variáveis de baixo nível do jogo (conexão com o banco e segredos).
// Gerado pelo instalador em $(date '+%d/%m/%Y %H:%M'). Não publique este arquivo.

\$dbsettings = Array(
        "server"        => "localhost",
        "user"          => "$(esc_php "$NARUTO_DB_USUARIO")",
        "pass"          => "$(esc_php "$NARUTO_DB_SENHA")",
        "name"          => "$(esc_php "$NARUTO_DB_NOME")",
        "prefix"        => "$(esc_php "$NARUTO_PREFIXO")",
        "secretword"    => "$SEGREDO",
        "cookiename"    => "narutorpg",
        "timezone"      => "$(esc_php "$NARUTO_FUSO")",
        "adminips"      => "$NARUTO_ADMIN_IPS",  // IPs/faixas que podem abrir admin.php, admin_missoes.php e gm.php (vazio = qualquer um).
        "debug"         => false);

?>
PHP
# Permissões: o servidor web só lê o jogo; pode escrever apenas nas pastas de imagens (uploads do painel).
chown -R root:www-data "$NARUTO_PASTA"
find "$NARUTO_PASTA" -type d -exec chmod 755 {} +
find "$NARUTO_PASTA" -type f -exec chmod 644 {} +
chmod 640 "$NARUTO_PASTA/config.php"
for d in layoutnovo/monstros layoutnovo/equipamentos layoutnovo/equipamentos/drops images images/24 layoutnovo/personagem; do
    [ -d "$NARUTO_PASTA/$d" ] && chown www-data:www-data "$NARUTO_PASTA/$d" && chmod 775 "$NARUTO_PASTA/$d"
done
ok "$(L "Arquivos copiados e permissões ajustadas." "Files copied and permissions set.")"

# -----------------------------------------------------------------------------
# 5. Configuração do jogo e conta do administrador
# -----------------------------------------------------------------------------
info "$(L "Configurando o jogo e criando o administrador..." "Configuring the game and creating the administrator...")"
NARUTO_URL_JOGO="$URL_JOGO" NARUTO_PASTA_JOGO="$NARUTO_PASTA" NARUTO_NOME_JOGO="$NARUTO_NOME_JOGO" \
NARUTO_ADMIN_CONTA="$NARUTO_ADMIN_CONTA" NARUTO_ADMIN_PERSONAGEM="$NARUTO_ADMIN_PERSONAGEM" \
NARUTO_ADMIN_EMAIL="$NARUTO_ADMIN_EMAIL" NARUTO_ADMIN_SENHA="$NARUTO_ADMIN_SENHA" NARUTO_VERIFICAR_EMAIL="$(sim "$NARUTO_EMAIL" && echo 1 || echo 0)" \
    php "$PASTA_REPO/install/criar_admin.php"

# -----------------------------------------------------------------------------
# 6. Apache
# -----------------------------------------------------------------------------
info "$(L "Configurando o Apache..." "Configuring Apache...")"
a2enmod -q rewrite headers >/dev/null
CONF=/etc/apache2/sites-available/narutorpg.conf
if [ "$NARUTO_CAMINHO" = "/" ]; then
    cat > "$CONF" <<APACHE
# Naruto RPG (gerado por install.sh)
<VirtualHost *:80>
    ServerName ${NARUTO_DOMINIO}
    DocumentRoot ${NARUTO_PASTA}
    <Directory ${NARUTO_PASTA}>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/narutorpg_erro.log
    CustomLog \${APACHE_LOG_DIR}/narutorpg_acesso.log combined
</VirtualHost>
APACHE
    a2dissite -q 000-default >/dev/null 2>&1 || true
else
    cat > "$CONF" <<APACHE
# Naruto RPG (gerado por install.sh) - publicado em ${NARUTO_CAMINHO}
Alias ${NARUTO_CAMINHO} ${NARUTO_PASTA}
<Directory ${NARUTO_PASTA}>
    AllowOverride All
    Require all granted
</Directory>
APACHE
fi
a2ensite -q narutorpg >/dev/null
# Ajustes de PHP para o jogo (não mostrar erros, limite de upload das imagens do painel).
PHPINI_DIR="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
if [ -d "/etc/php/${PHPINI_DIR}/apache2/conf.d" ]; then
    cat > "/etc/php/${PHPINI_DIR}/apache2/conf.d/99-narutorpg.ini" <<INI
; Naruto RPG
display_errors = Off
log_errors = On
expose_php = Off
upload_max_filesize = 4M
post_max_size = 8M
session.cookie_httponly = 1
INI
fi
apache2ctl configtest >/dev/null 2>&1 || erro "$(L "Configuração do Apache inválida (rode apache2ctl configtest)." "Invalid Apache configuration (run apache2ctl configtest).")"
iniciar apache2; recarregar apache2
if command -v ufw >/dev/null && ufw status 2>/dev/null | grep -q "Status: active"; then ufw allow 'Apache Full' >/dev/null && ok "$(L "Firewall (ufw) liberado para HTTP/HTTPS." "Firewall (ufw) opened for HTTP/HTTPS.")"; fi
ok "$(L "Apache configurado." "Apache configured.")"

# -----------------------------------------------------------------------------
# 7. HTTPS (opcional)
# -----------------------------------------------------------------------------
if sim "$NARUTO_HTTPS"; then
    info "$(L "Obtendo certificado HTTPS (Let's Encrypt)..." "Getting the HTTPS certificate (Let's Encrypt)...")"
    apt-get install -y -qq certbot python3-certbot-apache >/dev/null
    if certbot --apache -n --agree-tos -m "$NARUTO_HTTPS_EMAIL" -d "$NARUTO_DOMINIO" --redirect; then
        ok "$(L "HTTPS ativado." "HTTPS enabled.")"
    else
        aviso "$(L "Não foi possível obter o certificado agora (o domínio aponta para este servidor?). O jogo continua em http://. Tente depois: certbot --apache -d $NARUTO_DOMINIO" "Could not get the certificate now (does the domain point to this server?). The game stays on http://. Try later: certbot --apache -d $NARUTO_DOMINIO")"
        mysql "$NARUTO_DB_NOME" -e "UPDATE ${NARUTO_PREFIXO}_control SET gameurl=REPLACE(gameurl,'https://','http://') WHERE id=1;"
        URL_JOGO="${URL_JOGO/https:/http:}"
    fi
fi
if sim "$NARUTO_FORCAR_HTTPS"; then
    if [ "${URL_JOGO#https:}" != "$URL_JOGO" ] || ! sim "$NARUTO_HTTPS"; then
        # Descomenta o bloco entre FORCAR_HTTPS_INICIO e FORCAR_HTTPS_FIM do .htaccess.
        sed -i '/# FORCAR_HTTPS_INICIO/,/# FORCAR_HTTPS_FIM/{/^#\(<\|    \)/s/^#//}' "$NARUTO_PASTA/.htaccess"
        ok "$(L "HTTPS forçado no .htaccess." "HTTPS forced in .htaccess.")"
    else
        aviso "$(L "O certificado não foi obtido: o HTTPS não foi forçado (o jogo ficaria inacessível)." "The certificate was not obtained: HTTPS was not forced (the game would be unreachable).")"
    fi
fi

# -----------------------------------------------------------------------------
# 7b. Backups automáticos (cron)
# -----------------------------------------------------------------------------
if [ "$NARUTO_BACKUP" != "n" ]; then
    install -m 700 -o root -g root "$PASTA_REPO/install/backup.sh" /usr/local/sbin/narutorpg-backup
    cat > /etc/narutorpg-backup.conf <<CONF
# Naruto RPG: opções do backup (/usr/local/sbin/narutorpg-backup). MODO=banco ou completo.
MODO=$NARUTO_BACKUP
JOGO=$NARUTO_PASTA
DESTINO=$NARUTO_BACKUP_PASTA
MANTER=$NARUTO_BACKUP_DIAS
CONF
    chmod 600 /etc/narutorpg-backup.conf
    H="${NARUTO_BACKUP_HORA%%:*}"; M="${NARUTO_BACKUP_HORA##*:}"
    echo "$((10#$M)) $((10#$H)) * * * root /usr/local/sbin/narutorpg-backup >> /var/log/narutorpg-backup.log 2>&1" > /etc/cron.d/narutorpg-backup
    chmod 644 /etc/cron.d/narutorpg-backup
    if /usr/local/sbin/narutorpg-backup >> /var/log/narutorpg-backup.log 2>&1; then
        ok "$(L "Backup automático configurado (primeiro backup feito em $NARUTO_BACKUP_PASTA)." "Automatic backup configured (first backup saved in $NARUTO_BACKUP_PASTA).")"
    else
        aviso "$(L "O backup foi agendado, mas o teste falhou: veja /var/log/narutorpg-backup.log" "The backup was scheduled, but the test failed: see /var/log/narutorpg-backup.log")"
    fi
fi

# -----------------------------------------------------------------------------
# 8. Teste final
# -----------------------------------------------------------------------------
info "$(L "Testando..." "Testing...")"
TESTE_URL="http://127.0.0.1${NARUTO_CAMINHO%/}/login.php?do=login&idioma=pt"
CODIGO="$(php -r '$c=stream_context_create(["http"=>["header"=>"Host: '"$NARUTO_DOMINIO"'","ignore_errors"=>true]]); $r=@file_get_contents("'"$TESTE_URL"'",false,$c); echo isset($http_response_header[0])?$http_response_header[0]:"sem resposta"; echo (strpos((string)$r,"Fazer Login")!==false)?" (página de login OK)":"";')"
echo "  $TESTE_URL -> $CODIGO"

cat > /root/narutorpg-instalacao.txt <<TXT
Naruto RPG instalado em $(date '+%d/%m/%Y %H:%M')
Endereço: $URL_JOGO
Pasta: $NARUTO_PASTA
Banco: $NARUTO_DB_NOME  Usuário: $NARUTO_DB_USUARIO  Senha: $NARUTO_DB_SENHA  Prefixo: ${NARUTO_PREFIXO}_
Administrador: $NARUTO_ADMIN_CONTA (personagem $NARUTO_ADMIN_PERSONAGEM)
TXT
chmod 600 /root/narutorpg-instalacao.txt

echo
echo -e "${VERDE}$(L "Instalação concluída!" "Installation complete!")${NORMAL}"
echo "  $(L "Jogo ...................." "Game ...................."): $URL_JOGO"
echo "  $(L "Painel do administrador ." "Admin panel ............."): ${URL_JOGO%index.php}admin.php  ($(L "entre com a conta" "log in with the account") $NARUTO_ADMIN_CONTA)"
echo "  $(L "Dados da instalação ....." "Installation details ...."): /root/narutorpg-instalacao.txt ($(L "só o root lê" "readable by root only"))"
echo "  $(L "Logs de erro ............" "Error logs .............."): /var/log/apache2/"
[ "$NARUTO_BACKUP" != "n" ] && echo "  $(L "Backups ................." "Backups ................."): $NARUTO_BACKUP_PASTA  ($(L "log" "log"): /var/log/narutorpg-backup.log; $(L "rodar agora" "run now"): narutorpg-backup)"
echo
echo "$(L "Próximos passos: entre no painel, revise as Configurações gerais e publique uma notícia de boas-vindas." "Next steps: log in to the admin panel, review the General settings and publish a welcome news item.")"
