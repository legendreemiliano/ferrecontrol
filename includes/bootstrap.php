<?php
/**
 * Arranque común de todas las páginas.
 * Las páginas públicas (login, setup) definen PUBLIC_PAGE antes de incluir este archivo.
 */
define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/config/database.php';

date_default_timezone_set(APP_TIMEZONE);
mb_internal_encoding('UTF-8');

// ---- errores: se registran en logs/error.log; en pantalla solo si APP_DEBUG es true
ini_set('log_errors', '1');
ini_set('error_log', ROOT_DIR . '/logs/error.log');
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

require_once ROOT_DIR . '/includes/db.php';
require_once ROOT_DIR . '/includes/functions.php';

set_exception_handler(function (Throwable $e) {
    app_log(get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    while (ob_get_level()) ob_end_clean();
    echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>Error</title>'
       . '<body style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:0 16px">'
       . '<h2>Ocurrió un error inesperado</h2><p>La operación no se completó. Probá de nuevo y, si el problema sigue, avisá al administrador.</p>';
    if (APP_DEBUG) {
        echo '<pre style="background:#f4f4f4;padding:12px;white-space:pre-wrap">' . e($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>';
    }
    echo '<p><a href="' . e(url('dashboard.php')) . '">Volver al inicio</a></p></body></html>';
    exit;
});

require_once ROOT_DIR . '/includes/auth.php';
require_once ROOT_DIR . '/includes/permissions.php';
require_once ROOT_DIR . '/includes/services/stock_service.php';
require_once ROOT_DIR . '/includes/services/accounting_service.php';
require_once ROOT_DIR . '/includes/services/purchase_service.php';
require_once ROOT_DIR . '/includes/services/sale_service.php';
require_once ROOT_DIR . '/includes/services/payroll_service.php';

auth_start_session();
ob_start();   // permite redirigir / descargar CSV después de haber incluido archivos

if (!defined('PUBLIC_PAGE')) {
    require_login();
}
