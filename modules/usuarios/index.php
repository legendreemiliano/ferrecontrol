<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('admin');
$me = current_user();
$roles = ['admin' => 'Administrador', 'deposito' => 'Encargado de depósito y ventas', 'personal' => 'Encargado de personal'];

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 20);
        $id = post_int('id');
        if ($action === 'create' || $action === 'update') {
            $name = post_str('full_name', 120); $user = post_str('username', 50); $role = post_str('role', 10);
            $pw = (string)($_POST['password'] ?? '');
            if ($name === '') throw new AppException('Escribí el nombre completo.');
            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $user)) throw new AppException('El usuario debe tener entre 3 y 50 caracteres (letras, números, punto o guion).');
            if (!isset($roles[$role])) throw new AppException('Elegí un rol válido.');
            if (q_one('SELECT id FROM users WHERE username = ? AND id <> ?', [$user, $id])) throw new AppException('Ya existe un usuario con ese nombre de usuario.');
            if ($action === 'create') {
                if ($p = password_problem($pw)) throw new AppException($p);
                q('INSERT INTO users (full_name, username, password_hash, role) VALUES (?,?,?,?)', [$name, $user, password_hash($pw, PASSWORD_DEFAULT), $role]);
                audit('usuario_creado', 'users', (int)db()->lastInsertId(), $user . ' (' . $role . ')');
                flash('success', 'Usuario creado.');
            } else {
                $target = q_one('SELECT * FROM users WHERE id = ?', [$id]);
                if (!$target) throw new AppException('El usuario no existe.');
                $active = isset($_POST['active']) ? 1 : 0;
                if ($id === (int)$me['id'] && ($role !== 'admin' || !$active)) throw new AppException('No podés quitarte el rol de administrador ni desactivarte a vos mismo.');
                q('UPDATE users SET full_name = ?, username = ?, role = ?, active = ? WHERE id = ?', [$name, $user, $role, $active, $id]);
                if ($pw !== '') {
                    if ($p = password_problem($pw)) throw new AppException($p);
                    q('UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
                    audit('usuario_clave_cambiada', 'users', $id, $user);
                }
                audit('usuario_editado', 'users', $id, $user . ' rol ' . $role . ($active ? '' : ' (inactivo)'));
                flash('success', 'Usuario actualizado.');
            }
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
        redirect('modules/usuarios/index.php' . (post_int('id') ? '?edit=' . post_int('id') : ''));
    }
    redirect('modules/usuarios/index.php');
}

$edit = get_int('edit') ? q_one('SELECT * FROM users WHERE id = ?', [get_int('edit')]) : null;
$users = q_all('SELECT * FROM users ORDER BY active DESC, full_name');

$pageTitle = 'Usuarios';
$active = 'adm.usuarios';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Usuarios y permisos</h1></div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="fc-card"><div class="fc-card-h"><?= $edit ? 'Editar usuario' : 'Nuevo usuario' ?></div><div class="fc-card-b">
      <form method="post" autocomplete="off"><?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
        <div class="mb-2"><label class="form-label required">Nombre completo</label><input class="form-control" name="full_name" maxlength="120" required value="<?= e(old('full_name', $edit['full_name'] ?? '')) ?>"></div>
        <div class="mb-2"><label class="form-label required">Usuario</label><input class="form-control" name="username" maxlength="50" required value="<?= e(old('username', $edit['username'] ?? '')) ?>"></div>
        <div class="mb-2"><label class="form-label required">Rol</label><select class="form-select" name="role"><?php foreach ($roles as $k => $l): ?><option value="<?= $k ?>" <?= ($edit['role'] ?? 'deposito') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label <?= $edit ? '' : 'required' ?>"><?= $edit ? 'Nueva contraseña (dejar vacío para no cambiarla)' : 'Contraseña' ?></label><input class="form-control" type="password" name="password" <?= $edit ? '' : 'required' ?>><div class="form-text">Mínimo 8 caracteres con letras y números.</div></div>
        <?php if ($edit): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="active" id="ua" <?= $edit['active'] ? 'checked' : '' ?>><label class="form-check-label" for="ua">Usuario activo</label></div><?php endif; ?>
        <button class="btn btn-primary"><?= $edit ? 'Guardar cambios' : 'Crear usuario' ?></button>
        <?php if ($edit): ?><a class="btn btn-light" href="<?= e(url('modules/usuarios/index.php')) ?>">Cancelar</a><?php endif; ?>
      </form>
    </div></div>
    <div class="fc-note mt-3"><strong>Roles.</strong> Administrador: acceso total. Encargado de depósito y ventas: productos, stock, compras y ventas (sin sueldos ni usuarios). Encargado de personal: empleados, asistencias, licencias y liquidaciones (sin ventas, precios ni caja).</div>
  </div>
  <div class="col-lg-8">
    <div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
      <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Último ingreso</th><th>Estado</th><th></th></tr></thead><tbody>
      <?php foreach ($users as $u): ?>
        <tr><td><?= e($u['full_name']) ?></td><td><code><?= e($u['username']) ?></code></td><td><?= e($roles[$u['role']] ?? $u['role']) ?></td>
          <td class="small"><?= e(datetime_ar($u['last_login'])) ?></td><td><?= yesno_badge((bool)$u['active']) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$u['id'] ?>"><i class="bi bi-pencil"></i></a></td></tr>
      <?php endforeach; ?></tbody></table></div></div>
  </div>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
