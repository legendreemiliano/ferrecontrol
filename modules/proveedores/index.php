<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('suppliers.view');
$canEdit = can('suppliers.edit');

if (is_post()) {
    csrf_check();
    require_perm('suppliers.edit');
    try {
        $action = post_str('action', 10); $id = post_int('id');
        if ($action === 'toggle') {
            $s = q_one('SELECT * FROM suppliers WHERE id = ?', [$id]);
            if (!$s) throw new AppException('El proveedor no existe.');
            q('UPDATE suppliers SET active = ? WHERE id = ?', [$s['active'] ? 0 : 1, $id]);
            audit($s['active'] ? 'proveedor_desactivado' : 'proveedor_activado', 'suppliers', $id, $s['name']);
            flash('success', 'Proveedor ' . ($s['active'] ? 'desactivado' : 'activado') . '.');
        } else {
            $name = post_str('name', 150); $cuit = post_str('cuit', 20); $email = post_str('email', 120);
            if ($name === '') throw new AppException('El nombre o razón social es obligatorio.');
            if ($cuit !== '' && !preg_match('/^[0-9-]{8,15}$/', $cuit)) throw new AppException('El CUIT solo admite números y guiones.');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppException('El correo electrónico no es válido.');
            $vals = [$name, $cuit ?: null, post_str('phone', 40), $email ?: null, post_str('address', 200), post_str('contact', 100), post_str('notes', 1000)];
            if ($action === 'update') {
                if (!q_one('SELECT id FROM suppliers WHERE id = ?', [$id])) throw new AppException('El proveedor no existe.');
                q('UPDATE suppliers SET name=?, cuit=?, phone=?, email=?, address=?, contact=?, notes=? WHERE id=?', array_merge($vals, [$id]));
                audit('proveedor_editado', 'suppliers', $id, $name);
                flash('success', 'Proveedor actualizado.');
            } else {
                q('INSERT INTO suppliers (name, cuit, phone, email, address, contact, notes) VALUES (?,?,?,?,?,?,?)', $vals);
                audit('proveedor_creado', 'suppliers', (int)db()->lastInsertId(), $name);
                flash('success', 'Proveedor agregado.');
            }
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
        redirect('modules/proveedores/index.php' . (post_int('id') ? '?edit=' . post_int('id') : ''));
    }
    redirect('modules/proveedores/index.php');
}

$qtext = get_str('q', 60);
$where = ['1 = 1']; $p = [];
if ($qtext !== '') { $where[] = '(s.name LIKE :q1 OR s.cuit LIKE :q2 OR s.contact LIKE :q3 OR s.email LIKE :q4)'; foreach ([1, 2, 3, 4] as $i) $p[":q$i"] = "%$qtext%"; }
[$sk, $sd, $order] = sort_params(['name' => 's.name', 'contact' => 's.contact'], 'name');
$pg = paginate('s.*, (SELECT COALESCE(SUM(pu.total),0) FROM purchases pu WHERE pu.supplier_id = s.id AND pu.status = \'confirmada\') AS bought',
               'suppliers s WHERE ' . implode(' AND ', $where), $p, $order);
$edit = ($canEdit && get_int('edit')) ? q_one('SELECT * FROM suppliers WHERE id = ?', [get_int('edit')]) : null;
$v = function ($k) use ($edit) { return old($k, $edit[$k] ?? ''); };

$pageTitle = 'Proveedores';
$active = 'stk.proveedores';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Proveedores</h1></div>
<div class="row g-3">
  <?php if ($canEdit): ?>
  <div class="col-lg-4"><div class="fc-card"><div class="fc-card-h"><?= $edit ? 'Editar proveedor' : 'Nuevo proveedor' ?></div><div class="fc-card-b">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="mb-2"><label class="form-label required">Nombre o razón social</label><input class="form-control" name="name" maxlength="150" required value="<?= e($v('name')) ?>"></div>
      <div class="row"><div class="col-6 mb-2"><label class="form-label">CUIT</label><input class="form-control" name="cuit" maxlength="20" value="<?= e($v('cuit')) ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Teléfono</label><input class="form-control" name="phone" maxlength="40" value="<?= e($v('phone')) ?>"></div></div>
      <div class="mb-2"><label class="form-label">Correo electrónico</label><input class="form-control" type="email" name="email" maxlength="120" value="<?= e($v('email')) ?>"></div>
      <div class="mb-2"><label class="form-label">Dirección</label><input class="form-control" name="address" maxlength="200" value="<?= e($v('address')) ?>"></div>
      <div class="mb-2"><label class="form-label">Contacto comercial</label><input class="form-control" name="contact" maxlength="100" value="<?= e($v('contact')) ?>"></div>
      <div class="mb-3"><label class="form-label">Observaciones</label><textarea class="form-control" name="notes" rows="2" maxlength="1000"><?= e($v('notes')) ?></textarea></div>
      <button class="btn btn-primary"><?= $edit ? 'Guardar cambios' : 'Agregar proveedor' ?></button>
      <?php if ($edit): ?><a class="btn btn-light" href="<?= e(url('modules/proveedores/index.php')) ?>">Cancelar</a><?php endif; ?>
    </form></div></div></div>
  <?php endif; ?>
  <div class="<?= $canEdit ? 'col-lg-8' : 'col-12' ?>">
    <form class="row g-2 mb-3 filters" method="get"><div class="col-8"><input class="form-control form-control-sm" name="q" value="<?= e($qtext) ?>" placeholder="Buscar por nombre, CUIT, contacto o correo…"></div><div class="col-4"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Buscar</button></div></form>
    <div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
      <thead><tr><?= sort_th('name', 'Proveedor', $sk, $sd) ?><th>CUIT</th><?= sort_th('contact', 'Contacto', $sk, $sd) ?><th class="text-end">Total comprado</th><th>Estado</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead><tbody>
      <?php foreach ($pg['rows'] as $s): ?>
        <tr class="<?= $s['active'] ? '' : 'text-muted' ?>"><td><?= e($s['name']) ?><div class="small text-muted"><?= e($s['address']) ?></div></td><td><?= e($s['cuit'] ?: '—') ?></td>
          <td><?= e($s['contact'] ?: '—') ?><div class="small text-muted"><?= e($s['phone']) ?> <?= e($s['email']) ?></div></td>
          <td class="text-end"><?= e(money($s['bought'])) ?></td><td><?= yesno_badge((bool)$s['active']) ?></td>
          <?php if ($canEdit): ?><td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$s['id'] ?>"><i class="bi bi-pencil"></i></a>
            <form method="post" class="d-inline" data-confirm="¿Cambiar el estado de este proveedor?"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-sm <?= $s['active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"><i class="bi <?= $s['active'] ? 'bi-slash-circle' : 'bi-check-circle' ?>"></i></button></form></td><?php endif; ?></tr>
      <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="6" class="text-center text-muted py-4">No hay proveedores.</td></tr><?php endif; ?></tbody></table></div></div>
    <?= render_pagination($pg) ?>
  </div>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
