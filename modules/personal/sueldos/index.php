<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_perm('payroll.view');

$per = get_str('periodo', 7); if ($per !== '' && !valid_period($per)) $per = '';
$emp = get_int('empleado'); $st = get_str('estado', 10);
$where = ['1 = 1']; $p = [];
if ($per !== '') { $where[] = 'p.period = :per'; $p[':per'] = $per; }
if ($emp > 0) { $where[] = 'p.employee_id = :e'; $p[':e'] = $emp; }
if (in_array($st, ['borrador', 'aprobada', 'pagada', 'anulada'], true)) { $where[] = 'p.status = :st'; $p[':st'] = $st; }
$pg = paginate('p.*, e.first_name, e.last_name, e.position', 'payrolls p JOIN employees e ON e.id = p.employee_id WHERE ' . implode(' AND ', $where), $p, 'p.period DESC, e.last_name, p.id DESC');
$emps = q_all("SELECT id, CONCAT(last_name, ', ', first_name) AS n FROM employees ORDER BY last_name, first_name");
$tot = q_one("SELECT COALESCE(SUM(net_total),0) AS net FROM payrolls p WHERE " . implode(' AND ', $where) . " AND p.status <> 'anulada'", $p);

$pageTitle = 'Sueldos';
$active = 'per.sueldos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Liquidación de sueldos (estimada)</h1>
  <?php if (can('payroll.generate')): ?><a class="btn btn-accent" href="<?= e(url('modules/personal/sueldos/nueva.php')) ?>"><i class="bi bi-plus-lg"></i> Nueva liquidación</a><?php endif; ?></div>
<div class="fc-note mb-3">Las liquidaciones son <strong>estimaciones de gestión</strong>, no recibos de sueldo oficiales. Los porcentajes y conceptos se configuran en «Conceptos de sueldo» y deben ser validados por el responsable según la normativa y el convenio aplicables.</div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Período</label><input type="month" class="form-control form-control-sm" name="periodo" value="<?= e($per) ?>"></div>
  <div class="col-6 col-md-4"><label class="form-label small mb-0">Empleado</label><select class="form-select form-select-sm" name="empleado"><?= options($emps, 'id', 'n', $emp, 'Todos') ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Estado</label><select class="form-select form-select-sm" name="estado"><option value="">Todos</option><?php foreach (['borrador', 'aprobada', 'pagada', 'anulada'] as $s): ?><option value="<?= $s ?>" <?= $st === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button></div>
</form>
<div class="row g-3 mb-3"><div class="col-6 col-md-3"><div class="fc-stat"><div class="lbl">Neto estimado (filtro, sin anuladas)</div><div class="val"><?= e(money($tot['net'])) ?></div></div></div></div>
<div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
  <thead><tr><th>Período</th><th>Empleado</th><th class="text-end">Remunerativo</th><th class="text-end">Bruto</th><th class="text-end">Descuentos</th><th class="text-end">Neto estimado</th><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $r): ?><tr class="<?= $r['status'] === 'anulada' ? 'text-decoration-line-through text-muted' : '' ?>"><td><?= e(period_label($r['period'])) ?></td><td><?= e($r['last_name'] . ', ' . $r['first_name']) ?><div class="small text-muted"><?= e($r['position']) ?></div></td>
    <td class="text-end"><?= e(money($r['total_remunerative'])) ?></td><td class="text-end"><?= e(money($r['gross_total'])) ?></td><td class="text-end"><?= e(money($r['total_deductions'])) ?></td><td class="text-end fw-semibold"><?= e(money($r['net_total'])) ?></td>
    <td><?= status_badge($r['status']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('modules/personal/sueldos/ver.php?id=' . $r['id'])) ?>"><i class="bi bi-eye"></i></a></td></tr>
  <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="8" class="text-center text-muted py-4">No hay liquidaciones para los filtros elegidos.</td></tr><?php endif; ?></tbody></table></div></div>
<?= render_pagination($pg) ?>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
