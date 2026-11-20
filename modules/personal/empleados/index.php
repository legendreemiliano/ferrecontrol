<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_perm('employees.view');

if (is_post()) {
    csrf_check();
    require_perm('employees.edit');
    try {
        $id = post_int('id');
        $e = q_one('SELECT * FROM employees WHERE id = ?', [$id]);
        if (!$e) throw new AppException('El empleado no existe.');
        $new = post_str('action', 10) === 'reactivate' ? 'activo' : 'baja';
        q('UPDATE employees SET status = ? WHERE id = ?', [$new, $id]);
        audit($new === 'baja' ? 'empleado_baja' : 'empleado_reactivado', 'employees', $id, employee_full_name($e));
        flash('success', $new === 'baja' ? 'Empleado dado de baja. Se conserva su historial de asistencias y liquidaciones.' : 'Empleado reactivado.');
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/personal/empleados/index.php' . qs());
}

$qtext = get_str('q', 60); $st = get_str('estado', 10) ?: 'vigentes';
$where = ['1 = 1']; $p = [];
if ($qtext !== '') { $where[] = '(e.first_name LIKE :q1 OR e.last_name LIKE :q2 OR e.dni LIKE :q3 OR e.position LIKE :q4)'; foreach ([1, 2, 3, 4] as $i) $p[":q$i"] = "%$qtext%"; }
if ($st === 'vigentes') $where[] = "e.status <> 'baja'"; elseif (in_array($st, ['activo', 'licencia', 'baja'], true)) { $where[] = 'e.status = :st'; $p[':st'] = $st; }
[$sk, $sd, $order] = sort_params(['name' => 'e.last_name', 'position' => 'e.position', 'hire' => 'e.hire_date', 'salary' => 'e.base_salary'], 'name');
$pg = paginate('e.*', 'employees e WHERE ' . implode(' AND ', $where), $p, "$order, e.first_name");
$canEdit = can('employees.edit');

$pageTitle = 'Empleados';
$active = 'per.empleados';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Empleados</h1><?php if ($canEdit): ?><a class="btn btn-accent" href="<?= e(url('modules/personal/empleados/form.php')) ?>"><i class="bi bi-plus-lg"></i> Nuevo empleado</a><?php endif; ?></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-12 col-md-5"><input class="form-control form-control-sm" name="q" value="<?= e($qtext) ?>" placeholder="Buscar por nombre, apellido, DNI o puesto…"></div>
  <div class="col-6 col-md-3"><select class="form-select form-select-sm" name="estado"><?php foreach (['vigentes' => 'Vigentes (sin bajas)', 'activo' => 'Activos', 'licencia' => 'En licencia', 'baja' => 'Dados de baja', 'todos' => 'Todos'] as $k => $l): ?><option value="<?= $k ?>" <?= $st === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Buscar</button></div>
</form>
<div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
  <thead><tr><?= sort_th('name', 'Empleado', $sk, $sd) ?><th>DNI</th><?= sort_th('position', 'Puesto', $sk, $sd) ?><th>Contratación</th><?= sort_th('hire', 'Ingreso', $sk, $sd) ?><?php if (can('payroll.view')): ?><?= sort_th('salary', 'Sueldo básico', $sk, $sd, 'text-end') ?><?php endif; ?><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $r): ?>
    <tr class="<?= $r['status'] === 'baja' ? 'text-muted' : '' ?>"><td><a href="<?= e(url('modules/personal/empleados/ver.php?id=' . $r['id'])) ?>"><?= e(employee_full_name($r)) ?></a></td><td><?= e($r['dni']) ?></td><td><?= e($r['position']) ?></td>
      <td><?= e(contract_label($r['contract_type'])) ?></td><td><?= e(date_ar($r['hire_date'])) ?></td>
      <?php if (can('payroll.view')): ?><td class="text-end"><?= e(money($r['base_salary'])) ?></td><?php endif; ?>
      <td><?= status_badge($r['status']) ?></td>
      <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('modules/personal/empleados/ver.php?id=' . $r['id'])) ?>"><i class="bi bi-eye"></i></a>
        <?php if ($canEdit): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('modules/personal/empleados/form.php?id=' . $r['id'])) ?>"><i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline" data-confirm="<?= $r['status'] === 'baja' ? '¿Reactivar a este empleado?' : '¿Dar de baja a este empleado? Se conserva todo su historial.' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="<?= $r['status'] === 'baja' ? 'reactivate' : 'deactivate' ?>">
          <button class="btn btn-sm <?= $r['status'] === 'baja' ? 'btn-outline-success' : 'btn-outline-danger' ?>"><i class="bi <?= $r['status'] === 'baja' ? 'bi-person-check' : 'bi-person-dash' ?>"></i></button></form><?php endif; ?></td></tr>
  <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="8" class="text-center text-muted py-4">No se encontraron empleados.</td></tr><?php endif; ?></tbody></table></div></div>
<?= render_pagination($pg) ?>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
