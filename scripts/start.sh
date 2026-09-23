#!/bin/sh
# Arranca Social Airsoft en local (desarrollo).
# Uso: scripts/start.sh   |   scripts/start.sh stop
export PATH="/home/jmbernabeu/brew/bin:$PATH"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${APP_PORT:-8088}"

stop() {
    pid="$(pgrep -f "php -S 127.0.0.1:$PORT" | head -1)"
    [ -n "$pid" ] && kill "$pid" && echo "Servidor web ($PORT) parado" || echo "Servidor web ya estaba parado"
    exit 0
}
[ "$1" = "stop" ] && stop

# 1) MariaDB
mysql.server status >/dev/null 2>&1 || mysql.server start || { echo "ERROR: no pude arrancar MariaDB (mysql.server start)"; exit 1; }

# 2) Servidor PHP embebido
if ! pgrep -f "php -S 127.0.0.1:$PORT" >/dev/null 2>&1; then
    (cd "$ROOT" && nohup php -S 127.0.0.1:$PORT >/tmp/opencode/php-server.log 2>&1 &)
    sleep 1
fi
echo "------------------------------------------"
echo "  ABRE:  http://127.0.0.1:$PORT/"
echo "------------------------------------------"
echo "  Usuarios de prueba ya creados en la BD:"
echo "    admin / admin123   (administrador)"
echo "    sniper1 / sniper123"
echo "    rookie1 / rookie123"
echo ""
echo "  Detener: scripts/start.sh stop"