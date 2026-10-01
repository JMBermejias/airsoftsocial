<?php
/**
 * Airsoft Social · Registro de errores
 * ----------
 * Por qué existe: un "502 Bad Gateway" es un error del servidor, no del código.
 * Apache y PHP-FPM devuelven 502 cuando el PHP no puede terminar: se queda sin
 * memoria, se agota el tiempo, o hay un error fatal justo al empezar.
 *
 * El problema es que ese error no se ve en la web (el 502 no lleva texto) y el
 * log del hosting a veces no lo guarda o no se puede abrir desde el navegador.
 * Aquí se guarda en un fichero del propio sitio, que sí se puede descargar.
 *
 * Cada entrada lleva fecha, versión de PHP, memoria que queda y el error.
 */

declare(strict_types=1);

/* Dónde se guarda. uploads/_system es lo que ya usa el actualizador y lleva su
   propio .htaccess con "Require all denied", así que el .log no se puede
   descargar desde fuera; solo se lee con error_log.php.
   Ojo: __DIR__ es includes/, hay que subir un nivel para llegar a la raíz. */
define('AS_LOG_FILE', dirname(__DIR__) . '/uploads/_system/error.log');
define('AS_LOG_MAX', 512 * 1024);   /* 512 KB: se recorta al llegar aquí */

/* No escribir durante la instalación: se llama antes de que exista uploads/. */
function as_log_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    $dir = dirname(AS_LOG_FILE);
    $ready = is_dir($dir) && is_writable($dir);
    if (!$ready) {
        /* Intentar crearlo una vez (uploads/ suele existir ya). */
        $ready = @mkdir($dir, 0775, true) && is_writable($dir);
    }
    return (bool)$ready;
}

/**
 * Escribe una línea en el log.
 * @param string $mensaje
 * @param array  $ctx    Datos extra (ruta, usuario…)
 */
function as_log(string $mensaje, array $ctx = []): void {
    if (!as_log_ready()) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
    $uri = $_SERVER['REQUEST_URI'] ?? '-';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 60);
    $extra = '';
    foreach ($ctx as $k => $v) {
        if (is_scalar($v) || $v === null) $extra .= sprintf(' %s=%s', $k, is_null($v) ? 'null' : (string)$v);
    }
    /* memory_limit devuelve -1 cuando no hay límite: se dice "sin límite",
       que es justo el caso que provoca los 502 por memoria. */
    $lim = (string)ini_get('memory_limit');
    if ($lim === '' || $lim === '-1') $lim = 'sin límite';

    $line = sprintf(
        "[%s] PHP %s | %6.1f MB usados (límite %s) | %s | %s | ua=%s%s\n",
        date('Y-m-d H:i:s'),
        PHP_VERSION,
        memory_get_usage(true) / 1048576,
        $lim,
        str_replace(["\r", "\n"], ' ', $mensaje),
        str_replace(["\r", "\n"], ' ', $uri),
        $ua,
        $extra
    );

    @file_put_contents(AS_LOG_FILE, $line, FILE_APPEND | LOCK_EX);

    /* Recortar si se hace grande. */
    clearstatcache(true, AS_LOG_FILE);
    if (@filesize(AS_LOG_FILE) > AS_LOG_MAX) {
        $lines = @file(AS_LOG_FILE);
        if (is_array($lines) && count($lines) > 50) {
            @file_put_contents(AS_LOG_FILE, array_slice($lines, -200));
        }
    }
}

/**
 * Instala la trampa de errores fatales.
 * Los errores fales (memoria agotada, tiempo, error de PHP) matan el proceso
 * antes de que se pueda escribir nada: con register_shutdown_function sí se
 * detecta, porque PHP la ejecuta aunque esté muriendo.
 */
function as_error_trap(): void {
    static $done = false;
    if ($done) return;
    $done = true;

    /* Errores de PHP que no rompen la ejecución.
     *
     * Importante: el @ de "@file_get_contents(...)" NO evita que el manejador
     * se ejecute (solo pone error_reporting a 0 para esa llamada), así que hay
     * que mirar a mano si el error venía silenciado o no. Si viene de un @ no se
     * anota, que si no el log se llena de ruido de operaciones que el código
     * ha descartado aposta. */
    set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
        /* error_reporting() vale 0 (o un número sin los bits de este error)
           cuando la llamada iba con @. Es la forma de detectarlo. */
        $suppressed = (error_reporting() & $no) === 0;
        if ($suppressed) {
            return false;   /* el código lo silenció aposta: no es un problema */
        }
        /* Solo lo que de verdad puede tumbar la web: avisos y errores.
           Los E_NOTICE y E_DEPRECATED se omiten porque hay muchos y no son
           la causa de un 502. */
        if ($no === E_WARNING || $no === E_ERROR || $no === E_USER_WARNING || $no === E_RECOVERABLE_ERROR) {
            as_log(sprintf('PHP[%d] %s', $no, $str), ['fichero' => basename($file), 'linea' => $line]);
        }
        return false;   /* deja que PHP lo gestione como siempre */
    });

    /* Errores fatales: se anotan y se muestra algo legible en vez de un 502. */
    register_shutdown_function(static function (): void {
        $e = error_get_last();
        if ($e === null) return;
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($e['type'], $fatal, true)) return;

        /* Si ya se han enviado cabeceras no se puede hacer nada: solo log. */
        $sent = headers_sent();
        as_log(sprintf('FATAL PHP[%d] %s', $e['type'], $e['message']), [
            'fichero' => basename($e['file']),
            'linea'   => $e['line'],
        ]);

        if ($sent || PHP_SAPI === 'cli') return;
        @http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        $why = htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
           . '<title>Error del servidor</title></head>'
           . '<body style="font-family:system-ui,sans-serif;max-width:620px;margin:12vh auto;padding:0 20px;'
           . 'background:#14170f;color:#e8eae2">'
           . '<h1 style="color:#c8d64b;font-size:22px;margin:0 0 12px">Error del servidor</h1>'
           . '<p style="line-height:1.6">La web se ha detenido por un error interno. '
           . 'Está anotado en <code>uploads/_system/error.log</code>.</p>'
           . '<p style="color:#8a9080;font-size:13px">Detalle: <code>' . $why . '</code></p>'
           . '</body></html>';
    });

    /* Fallar rápido si no se puede escribir: mejor un aviso claro que un 502. */
    if (!as_log_ready()) {
        error_log('[Airsoft Social] No se puede escribir el log de errores en uploads/_system/');
    }
}