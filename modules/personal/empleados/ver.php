<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_perm('employees.view');
$id = get_int('id');
$e = q_one('SELECT e.*, u.username FROM employees e LEFT JOIN users u ON u.id = e.user_id WHERE e.id = ?', [$id]);
if (!$e) { flash('error', 'El empleado no existe.'); redirect('modules/personal/empleados/index.php'); }

$month = date('Y-m');
$from = $month . '-01'; $to = date('Y-m-t');
$att = q_all('SELECT * FROM attendances WHERE employee_id = ? ORDER BY att_date DESC, shift DESC LIMIT 15', [$id]);
$stats = q_one("SELECT SUM(status IN ('presente','tarde')) AS pres, SUM(status = 'tarde') AS late, SUM(status = 'ausente') AS abs_, COUNT(*) AS total FROM attendances WHERE employee_id = ? AND att_date BETWEEN ? AND ?", [$id, $from, $to]);
$leaves = can('leaves.manage') ? q_all('SELECT * FROM leaves WHERE employee_id = ? ORDER BY start_date DESC LIMIT 8', [$id]) : [];
$pays = can('payroll.view') ? q_all('SELECT * FROM payrolls WHERE employee_id = ? ORDER BY period DESC LIMIT 6', [$id]) : [];

$pageTitle = employee_full_name($e);
$active = 'per.empleados';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1><?= e(employee_full_name($e)) ?> <?= status_badge($e['status']) ?></h1>
  <div><a class="btn btn-light" href="<?= e(url('modules/personal/empleados/index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a>
  <?php if (can('employees.edit')): ?><a class="btn btn-primary" href="<?= e(url('modules/personal/empleados/form.php?id=' . $e['id'])) ?>"><i class="bi bi-pencil"></i> Editar</a><?php endif; ?></div></div>
<div class="row g-3">
  <div class="col-lg-4"><div class="fc-card"><div class="fc-card-h">Datos</div><div class="fc-card-b"><dl class="row mb-0 small">
    <dt class="col-5">DNI</dt><dd class="col-7"><?= e($e['dni']) ?></dd><dt class="col-5">Puesto</dt><dd class="col-7"><?= e($e['position']) ?></dd>
    <dt class="col-5">Ingreso</dt><dd class="col-7"><?= e(date_ar($e['hire_date'])) ?></dd><dt class="col-5">Contratación</dt><dd class="col-7"><?= e(contract_label($e['contract_type'])) ?></dd>
    <dt class="col-5">Teléfono</dt><dd class="col-7"><?= e($e['phone'] ?: '—') ?></dd><dt class="col-5">Correo</dt><dd class="col-7"><?= e($e['email'] ?: '—') ?></dd>
    <dt class="col-5">Dirección</dt><dd class="col-7"><?= e($e['address'] ?: '—') ?></dd>
    <?php if (can('payroll.view')): ?><dt class="col-5">Sueldo básico</dt><dd class="col-7"><?= e(money($e['base_salary'])) ?></dd><?php endif; ?>
    <dt class="col-5">Usuario del sistema</dt><dd class="col-7"><?= e($e['username'] ?: '—') ?></dd></dl>
    <?php if ($e['tasks']): ?><hr><div class="small"><strong>Tareas:</strong><br><?= nl2br(e($e['tasks'])) ?></div><?php endif; ?>
    <?php if ($e['notes']): ?><div class="small mt-2"><strong>Observaciones:</strong><br><?= nl2br(e($e['notes'])) ?></div><?php endif; ?></div></div>
    <div class="fc-card mt-3"><div class="fc-card-h">Este mes (<?= e(period_label($month)) ?>)</div><div class="fc-card-b small">
      Presencias: <strong><?= (int)$stats['pres'] ?></strong> · Llegadas tarde: <strong><?= (int)$stats['late'] ?></strong> · Ausencias: <strong><?= (int)$stats['abs_'] ?></strong> · Registros: <?= (int)$stats['total'] ?></div></div></div>
  <div class="col-lg-8">
    <div class="fc-card mb-3"><div class="fc-card-h">Últimas asistencias</div><div class="table-responsive"><table class="table table-sm mb-0 fc-table">
      <thead><tr><th>Fecha</th><th>Turno</th><th>Entrada</th><th>Salida</th><th>Estado</th><th>Justificación</th></tr></thead><tbody>
      <?php foreach ($att as $a): ?><tr><td><?= e(weekday_es($a['att_date'])) ?> <?= e(date_ar($a['att_date'])) ?></td><td><?= (int)$a['shift'] ?></td><td><?= e(substr((string)$a['check_in'], 0, 5) ?: '—') ?></td><td><?= e(substr((string)$a['check_out'], 0, 5) ?: '—') ?></td><td><?= status_badge($a['status']) ?></td><td class="small"><?= $a['justified'] ? 'Justificada. ' : '' ?><?= e($a['justification']) ?></td></tr>
      <?php endforeach; if (!$att): ?><tr><td colspan="6" class="text-center text-muted py-3">Sin asistencias cargadas.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php if (can('leaves.manage')): ?><div class="fc-card mb-3"><div class="fc-card-h">Licencias</div><div class="table-responsive"><table class="table table-sm mb-0 fc-table"><thead><tr><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Estado</th></tr></thead><tbody>
      <?php foreach ($leaves as $l): ?><tr><td><?= e($l['leave_type']) ?></td><td><?= e(date_ar($l['start_date'])) ?></td><td><?= e(date_ar($l['end_date'])) ?></td><td><?= status_badge($l['status']) ?></td></tr>
      <?php endforeach; if (!$leaves): ?><tr><td colspan="4" class="text-center text-muted py-3">Sin licencias.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
    <?php if (can('payroll.view')): ?><div class="fc-card"><div class="fc-card-h">Liquidaciones</div><div class="table-responsive"><table class="table table-sm mb-0 fc-table"><thead><tr><th>Período</th><th class="text-end">Bruto</th><th class="text-end">Neto estimado</th><th>Estado</th><th></th></tr></thead><tbody>
      <?php foreach ($pays as $p): ?><tr><td><?= e(period_label($p['period'])) ?></td><td class="text-end"><?= e(money($p['gross_total'])) ?></td><td class="text-end"><?= e(money($p['net_total'])) ?></td><td><?= status_badge($p['status']) ?></td><td class="text-end"><a href="<?= e(url('modules/personal/sueldos/ver.php?id=' . $p['id'])) ?>">Ver</a></td></tr>
      <?php endforeach; if (!$pays): ?><tr><td colspan="5" class="text-center text-muted py-3">Sin liquidaciones.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>
  </div>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
