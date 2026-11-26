#!/usr/bin/env bash
# =============================================================================
#  Naruto RPG — backup periódico (para rodar pelo cron) / periodic backup (for cron)
#
#  Uso / Usage:
#    backup.sh [--modo banco|completo] [--jogo /var/www/narutorpg]
#              [--destino /var/backups/narutorpg] [--manter 14]
#
#    --modo banco     só o banco de dados (padrão) / database only (default)
#    --modo completo  banco + arquivos do jogo (inclui config.php e imagens enviadas)
#                     database + game files (includes config.php and uploaded images)
#    --destino        pasta dos backups; PRECISA ficar fora da pasta servida pelo site.
#                     backup folder; MUST be outside the folder served by the website.
#    --manter N       apaga backups com mais de N dias (0 = nunca apaga).
#                     deletes backups older than N days (0 = never).
#
#  Os valores padrão podem ficar em /etc/narutorpg-backup.conf (o instalador cria):
#  Defaults can live in /etc/narutorpg-backup.conf (created by the installer):
#    MODO=banco   JOGO=/var/www/narutorpg   DESTINO=/var/backups/narutorpg   MANTER=14
#
#  Exemplo de cron (todo dia às 4h10) / cron example (every day at 4:10):
#    10 4 * * * root /usr/local/sbin/narutorpg-backup >> /var/log/narutorpg-backup.log 2>&1
#
#  Restaurar / Restore:
#    gunzip -c banco-AAAAMMDD-HHMM.sql.gz | mysql NOME_DO_BANCO
#    tar xzf arquivos-AAAAMMDD-HHMM.tar.gz -C /var/www   (recria a pasta do jogo)
# =============================================================================
set -euo pipefail
umask 077

MODO="banco"; JOGO="/var/www/narutorpg"; DESTINO="/var/backups/narutorpg"; MANTER="14"
CONF="/etc/narutorpg-backup.conf"
# shellcheck disable=SC1090
[ -r "$CONF" ] && . "$CONF"

while [ $# -gt 0 ]; do
    case "$1" in
        --modo) MODO="$2"; shift 2;;
        --jogo) JOGO="$2"; shift 2;;
        --destino) DESTINO="$2"; shift 2;;
        --manter) MANTER="$2"; shift 2;;
        -h|--help) sed -n '2,28p' "$0"; exit 0;;
        *) echo "Opção desconhecida / unknown option: $1" >&2; exit 2;;
    esac
done

agora() { date '+%Y-%m-%d %H:%M:%S'; }
falha() { echo "[$(agora)] ERRO: $*" >&2; exit 1; }

case "$MODO" in banco|completo) ;; *) falha "--modo precisa ser 'banco' ou 'completo'.";; esac
[[ "$MANTER" =~ ^[0-9]+$ ]] || falha "--manter precisa ser um número de dias."
[ -f "$JOGO/config.php" ] || falha "config.php não encontrado em $JOGO (use --jogo)."
command -v mysqldump >/dev/null || falha "mysqldump não está instalado."

# A pasta de destino não pode ficar dentro da pasta do jogo nem da raiz do Apache (seria baixável pelo site).
JOGO_REAL="$(readlink -f "$JOGO")"
DESTINO_REAL="$(readlink -m "$DESTINO")"
for web in "$JOGO_REAL" /var/www /srv/www /usr/share/nginx/html /var/www/html; do
    case "$DESTINO_REAL/" in "$(readlink -f "$web" 2>/dev/null || echo "$web")/"*) falha "o destino $DESTINO_REAL fica dentro de uma pasta do site ($web). Use uma pasta fora dela, ex.: /var/backups/narutorpg.";; esac
done

mkdir -p "$DESTINO_REAL"; chmod 700 "$DESTINO_REAL"

# Só um backup por vez.
exec 9>"$DESTINO_REAL/.trava"
flock -n 9 || falha "já existe um backup rodando."

# Dados do banco lidos do config.php (a senha vai num arquivo temporário só do root, nunca na linha de comando).
LER='include $argv[1]; foreach (array("server","user","pass","name") as $k) { echo str_replace(array("\n","\r"), "", (string)$dbsettings[$k]), "\n"; }'
mapfile -t DB < <(php -r "$LER" "$JOGO_REAL/config.php")
[ "${#DB[@]}" -ge 4 ] && [ -n "${DB[3]}" ] || falha "não consegui ler os dados do banco no config.php."
CNF="$(mktemp)"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nhost=%s\nuser=%s\npassword="%s"\n' "${DB[0]}" "${DB[1]}" "${DB[2]//\"/\\\"}" > "$CNF"

DATA="$(date '+%Y%m%d-%H%M')"
ARQ_BANCO="$DESTINO_REAL/banco-$DATA.sql.gz"
echo "[$(agora)] Backup ($MODO) do banco ${DB[3]} -> $ARQ_BANCO"
mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --routines --triggers \
    --default-character-set=utf8mb4 "${DB[3]}" | gzip -6 > "$ARQ_BANCO.tmp"
mv "$ARQ_BANCO.tmp" "$ARQ_BANCO"

if [ "$MODO" = "completo" ]; then
    ARQ_JOGO="$DESTINO_REAL/arquivos-$DATA.tar.gz"
    echo "[$(agora)] Arquivos de $JOGO_REAL -> $ARQ_JOGO"
    tar czf "$ARQ_JOGO.tmp" -C "$(dirname "$JOGO_REAL")" --exclude='.git' "$(basename "$JOGO_REAL")"
    mv "$ARQ_JOGO.tmp" "$ARQ_JOGO"
fi
chmod 600 "$DESTINO_REAL"/*-"$DATA".*

# Limpa os antigos.
if [ "$MANTER" -gt 0 ]; then
    find "$DESTINO_REAL" -maxdepth 1 -type f \( -name 'banco-*.sql.gz' -o -name 'arquivos-*.tar.gz' \) -mtime +"$MANTER" -print -delete | sed "s/^/[$(agora)] Removido: /"
fi
echo "[$(agora)] Concluído. Espaço usado: $(du -sh "$DESTINO_REAL" | cut -f1)"
