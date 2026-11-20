<?php
/** Autenticación: sesiones seguras, login con bloqueo por intentos, logout. */

const SESSION_IDLE_SECONDS = 7200;   // 2 horas sin actividad
const MAX_FAILED_ATTEMPTS  = 5;
const LOCK_MINUTES         = 10;

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('FERRECONTROL');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'][] = ['t' => 'warning', 'm' => 'Tu sesión venció por inactividad. Ingresá de nuevo.'];
    }
    $_SESSION['last_activity'] = time();
}

/** Usuario logueado (se vuelve a leer de la BD en cada pedido: si lo desactivan, pierde el acceso). */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (!empty($_SESSION['uid'])) {
        $u = q_one('SELECT id, full_name, username, role, active FROM users WHERE id = ?', [(int)$_SESSION['uid']]);
        if ($u && (int)$u['active'] === 1) {
            $cache = $u;
        } else {
            unset($_SESSION['uid']);
        }
    }
    return $cache;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
}

function role_label(string $role): string
{
    $m = ['admin' => 'Administrador', 'deposito' => 'Encargado de depósito y ventas', 'personal' => 'Encargado de personal'];
    return $m[$role] ?? $role;
}

/** Devuelve null si el login fue correcto, o un mensaje de error. */
function auth_login(string $username, string $password): ?string
{
    $generic = 'Usuario o contraseña incorrectos.';
    $u = q_one('SELECT * FROM users WHERE username = ?', [$username]);
    if (!$u) return $generic;

    if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
        return 'Demasiados intentos fallidos. Esperá unos minutos antes de volver a probar.';
    }
    if (!password_verify($password, $u['password_hash'])) {
        $failed = (int)$u['failed_attempts'] + 1;
        if ($failed >= MAX_FAILED_ATTEMPTS) {
            q('UPDATE users SET failed_attempts = 0, locked_until = ? WHERE id = ?',
              [date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60), $u['id']]);
        } else {
            q('UPDATE users SET failed_attempts = ? WHERE id = ?', [$failed, $u['id']]);
        }
        $GLOBALS['ACTING_USER_ID'] = (int)$u['id'];
        audit('login_fallido', 'users', (int)$u['id'], 'Intento n.º ' . $failed);
        unset($GLOBALS['ACTING_USER_ID']);
        return $generic;
    }
    if ((int)$u['active'] !== 1) {
        return 'El usuario está inactivo. Consultá con el administrador.';
    }

    session_regenerate_id(true);               // evita fijación de sesión
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = ? WHERE id = ?', [now_sql(), $u['id']]);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    audit('login', 'users', (int)$u['id'], 'Inicio de sesión');
    return null;
}

function auth_logout(): void
{
    audit('logout', 'users', acting_user_id(), 'Cierre de sesión');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function password_problem(string $pw): ?string
{
    if (mb_strlen($pw) < 8) return 'La contraseña debe tener al menos 8 caracteres.';
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'La contraseña debe combinar letras y números.';
    return null;
}
