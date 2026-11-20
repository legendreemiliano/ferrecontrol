<?php
define('PUBLIC_PAGE', true);
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect('dashboard.php');

// Si todavía no hay usuarios, se manda a la instalación inicial.
try {
    $hasUsers = (int)q_val('SELECT COUNT(*) FROM users') > 0;
} catch (Throwable $e) {
    $hasUsers = true;
    flash('error', 'Falta importar la base de datos (database/ferrecontrol.sql). Revisá el README.');
}
if (!$hasUsers) redirect('setup.php');

$error = null;
if (is_post()) {
    csrf_check();
    $error = auth_login(post_str('username', 50), (string)($_POST['password'] ?? ''));
    if ($error === null) redirect('dashboard.php');
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ingresar · FerreControl</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="fc-login-wrap">
  <div class="fc-login-card">
    <div class="text-center mb-4">
      <h1 class="h2 mb-1"><i class="bi bi-tools"></i> FerreControl</h1>
      <div class="text-muted">Sistema de gestión para ferreterías</div>
    </div>
    <?= flash_render() ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="username">Usuario</label>
        <input class="form-control" id="username" name="username" maxlength="50" required autofocus value="<?= e(old('username')) ?>">
      </div>
      <div class="mb-4">
        <label class="form-label" for="password">Contraseña</label>
        <input class="form-control" id="password" name="password" type="password" required>
      </div>
      <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Ingresar</button>
    </form>
  </div>
</div>
</body>
</html>
