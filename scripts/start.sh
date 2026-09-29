#!/bin/sh
# Arranca Social Airsoft en local (desarrollo).
# Uso: scripts/start.sh   |   scripts/start.sh stop
export PATH="/home/jmbernabeu/brew/bin:$PATH"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${APP_PORT:-8088}"
BIND="[::]:$PORT"   # dual-stack: acepta IPv4 (127.0.0.1) e IPv6 (::1/localhost)

stop() {
    pid="$(pgrep -f "php -S \[::\]:$PORT" | head -1)"
    if [ -z "$pid" ]; then
        pid="$(pgrep -f "php -S 127.0.0.1:$PORT" | head -1)"
    fi
    [ -n "$pid" ] && kill "$pid" && echo "Servidor web ($PORT) parado" || echo "Servidor web ya estaba parado"
    exit 0
}
[ "$1" = "stop" ] && stop

# 1) MariaDB: limpiamos restos de un apagado brusco y arrancamos en el puerto
#    de la app (3307, fijado en brew/etc/my.cnf.d/socialairsoft.cnf).
HOMEBREW_PREFIX="$(brew --prefix 2>/dev/null || echo "$HOME/brew")"
DB_PORT="${DB_PORT:-3307}"
DB_USER="${DB_USER:-social}"
DB_PASS="${DB_PASS:-social_test_2026}"
rm -f "$HOMEBREW_PREFIX/var/mysql/"*.pid "$HOMEBREW_PREFIX/var/mysql/"*.sock 2>/dev/null
if mysqladmin ping --host=127.0.0.1 --port="$DB_PORT" --user="$DB_USER" --password="$DB_PASS" --silent >/dev/null 2>&1; then
    echo "MariaDB ya está activo en 127.0.0.1:$DB_PORT"
else
    mysql.server start || { echo "ERROR: no pude arrancar MariaDB (mysql.server start). Revisa brew/etc/my.cnf.d/"; exit 1; }
    sleep 2
    mysqladmin ping --host=127.0.0.1 --port="$DB_PORT" --user="$DB_USER" --password="$DB_PASS" --silent >/dev/null 2>&1 \
        || { echo "ERROR: MariaDB no responde en 127.0.0.1:$DB_PORT"; exit 1; }
fi

# 2) Servidor PHP embebido (multiworker + dual-stack)
#    php -S es single-threaded; PHP_CLI_SERVER_WORKERS evita que los navegadores
#    (que abren conexiones paralelas) se cuelen. El bind [::] responde también
#    en 127.0.0.1, así localhost funciona igual en stacks IPv4 e IPv6.
if ! pgrep -f "php -S \[::\]:$PORT" >/dev/null 2>&1 && ! pgrep -f "php -S 127.0.0.1:$PORT" >/dev/null 2>&1; then
    (cd "$ROOT" && PHP_CLI_SERVER_WORKERS=4 nohup php -S "$BIND" >/tmp/opencode/php-server.log 2>&1 &)
    sleep 1
fi
if curl -s -o /dev/null --max-time 3 "http://127.0.0.1:$PORT/"; then
    echo "Servidor web OK en 127.0.0.1:$PORT"
else
    echo "ERROR: el servidor web no responde. Revisa /tmp/opencode/php-server.log"
    exit 1
fi
echo "------------------------------------------"
echo "  ABRE:  http://localhost:$PORT/   (o http://127.0.0.1:$PORT/)"
echo "------------------------------------------"
echo "  Usuarios de prueba ya creados en la BD:"
echo "    admin / admin123   (administrador)"
echo "    sniper1 / sniper123"
echo "    rookie1 / rookie123"
echo ""
echo "  Detener: scripts/start.sh stop"