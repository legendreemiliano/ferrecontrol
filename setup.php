<?php
/**
 * Instalación inicial: crea el usuario administrador (con contraseña hasheada) y,
 * si se pide, usuarios de demostración y datos de ejemplo.
 * Solo funciona desde la misma PC (localhost) y solo si todavía no existe ningún usuario.
 */
define('PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/bootstrap.php';
require_once ROOT_DIR . '/includes/demo_seed.php';
@set_time_limit(180);

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('La instalación inicial solo puede ejecutarse desde la propia PC (localhost).');
}

$schemaOk = true; $userCount = 0;
try {
    $userCount = (int)q_val('SELECT COUNT(*) FROM users');
} catch (Throwable $e) {
    $schemaOk = false;
}

$errors = []; $done = null;
if ($schemaOk && $userCount === 0 && is_post()) {
    csrf_check();
    $name = post_str('full_name', 120);
    $user = post_str('username', 50);
    $pw   = (string)($_POST['password'] ?? '');
    $pw2  = (string)($_POST['password2'] ?? '');
    $demoUsers = isset($_POST['demo_users']);
    $demoData  = isset($_POST['demo_data']);
    $demoPw    = (string)($_POST['demo_password'] ?? '');

    if ($name === '') $errors[] = 'Escribí el nombre completo del administrador.';
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $user)) $errors[] = 'El usuario debe tener entre 3 y 50 caracteres (letras, números, punto, guion).';
    if ($pw !== $pw2) $errors[] = 'Las contraseñas no coinciden.';
    if ($p = password_problem($pw)) $errors[] = $p;
    if (($demoUsers || $demoData) && ($p = password_problem($demoPw))) $errors[] = 'Contraseña de usuarios de demostración: ' . $p;
    if ($demoData && !$demoUsers) $demoUsers = true;

    if (!$errors) {
        try {
            $sellers = [];
            with_transaction(function () use ($name, $user, $pw, $demoUsers, $demoPw, &$sellers) {
                q("INSERT INTO users (full_name, username, password_hash, role) VALUES (?,?,?, 'admin')", [$name, $user, password_hash($pw, PASSWORD_DEFAULT)]);
                $sellers[] = (int)db()->lastInsertId();
                if ($demoUsers) {
                    $h = password_hash($demoPw, PASSWORD_DEFAULT);
                    q("INSERT INTO users (full_name, username, password_hash, role) VALUES ('Julián Acosta (demo)', 'deposito', ?, 'deposito')", [$h]);
                    $dep = (int)db()->lastInsertId();
                    q("INSERT INTO users (full_name, username, password_hash, role) VALUES ('Paula Ferreyra (demo)', 'personal', ?, 'personal')", [$h]);
                    $per = (int)db()->lastInsertId();
                    q('UPDATE employees SET user_id = ? WHERE dni = ?', [$dep, '30000003']);
                    q('UPDATE employees SET user_id = ? WHERE dni = ?', [$per, '30000004']);
                    $sellers[] = $dep;
                }
            });
            $GLOBALS['ACTING_USER_ID'] = $sellers[0];
            audit('instalacion', 'users', $sellers[0], 'Usuario administrador creado desde setup.php');
            $done = ['users' => $demoUsers, 'seed' => null];
            if ($demoData) {
                $done['seed'] = demo_seed_run($sellers);
                audit('datos_demo', 'sistema', null, 'Datos de demostración cargados');
            }
            unset($GLOBALS['ACTING_USER_ID']);
            $userCount = 1;
        } catch (Throwable $e) {
            app_log('setup: ' . $e->getMessage());
            $errors[] = ($e instanceof AppException ? $e->getMessage() : 'No se pudo completar la instalación: ' . (APP_DEBUG ? $e->getMessage() : 'revisá logs/error.log'))
                      . ' Si quedaron datos a medias, volvé a importar database/ferrecontrol.sql (borra y recrea todo) y repetí este paso.';
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalación · FerreControl</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="fc-login-wrap">
  <div class="fc-login-card" style="max-width:520px">
    <h1 class="h3 mb-1"><i class="bi bi-tools"></i> FerreControl</h1>
    <p class="text-muted">Instalación inicial</p>

    <?php if (!$schemaOk): ?>
      <div class="alert alert-danger">No se encontraron las tablas. Primero importá <code>database/ferrecontrol.sql</code> en phpMyAdmin y recargá esta página.</div>
    <?php elseif ($done): ?>
      <div class="alert alert-success">
        <strong>Listo.</strong> El administrador se creó correctamente.
        <?php if ($done['users']): ?><br>También se crearon los usuarios <code>deposito</code> y <code>personal</code>.<?php endif; ?>
        <?php if ($done['seed']): ?><br>Datos de demostración: <?= (int)$done['seed']['compras'] ?> compras, <?= (int)$done['seed']['ventas'] ?> ventas,
          <?= (int)$done['seed']['gastos'] ?> gastos, <?= (int)$done['seed']['asistencias'] ?> asistencias y <?= (int)$done['seed']['liquidaciones'] ?> liquidaciones.<?php endif; ?>
      </div>
      <a class="btn btn-primary w-100" href="<?= e(url('login.php')) ?>">Ir a iniciar sesión</a>
    <?php elseif ($userCount > 0): ?>
      <div class="alert alert-info">El sistema ya está configurado: existe al menos un usuario.</div>
      <a class="btn btn-primary w-100" href="<?= e(url('login.php')) ?>">Ir a iniciar sesión</a>
    <?php else: ?>
      <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label required">Nombre completo del administrador</label>
          <input class="form-control" name="full_name" maxlength="120" required value="<?= e(old('full_name')) ?>"></div>
        <div class="mb-3"><label class="form-label required">Usuario</label>
          <input class="form-control" name="username" maxlength="50" required value="<?= e(old('username', 'admin')) ?>"></div>
        <div class="row">
          <div class="col-md-6 mb-3"><label class="form-label required">Contraseña</label><input class="form-control" type="password" name="password" required></div>
          <div class="col-md-6 mb-3"><label class="form-label required">Repetir contraseña</label><input class="form-control" type="password" name="password2" required></div>
        </div>
        <div class="form-text mb-3">Mínimo 8 caracteres, con letras y números. Se guarda con <code>password_hash()</code>; nunca en texto plano.</div>
        <hr>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="demo_users" id="du" <?= isset($_POST['demo_users']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="du">Crear usuarios de demostración (<code>deposito</code> y <code>personal</code>)</label></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="demo_data" id="dd" <?= !is_post() || isset($_POST['demo_data']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="dd">Cargar datos de demostración (compras, ventas, gastos, asistencias, liquidaciones)</label></div>
        <div class="mb-3"><label class="form-label">Contraseña de los usuarios de demostración</label>
          <input class="form-control" type="password" name="demo_password" placeholder="Obligatoria si marcaste alguna opción de arriba"></div>
        <button class="btn btn-primary w-100" type="submit">Crear administrador</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
