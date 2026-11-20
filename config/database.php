<?php
/**
 * Configuración de FerreControl (entorno local con XAMPP).
 *
 * Los valores por defecto de XAMPP son: usuario "root" y contraseña vacía.
 * Si en tu instalación el usuario o la contraseña de MySQL son distintos,
 * cambialos acá. No subas este archivo a un servidor público con credenciales reales.
 */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'ferrecontrol');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_TIMEZONE', 'America/Argentina/Buenos_Aires');

// false = mensajes de error genéricos (los detalles quedan en logs/error.log).
// Poné true SOLO mientras desarrollás en tu PC para ver los errores en pantalla.
define('APP_DEBUG', false);

// Dejalo vacío para que se detecte solo (ej: /ferrecontrol).
define('APP_BASE_URL', '');
