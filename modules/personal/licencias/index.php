<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_perm('leaves.manage');
$types = ['Vacaciones', 'Enfermedad', 'Estudio / examen', 'Asuntos personales', 'Maternidad / paternidad', 'Duelo', 'Otra'];

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 10);
        if ($action === 'create') {
            $emp = post_int('employee_id'); $type = post_str('leave_type', 60); $s = post_date('start_date'); $e2 = post_date('end_date');
            if (!q_one("SELECT id FROM employees WHERE id = ? AND status <> 'baja'", [$emp])) throw new AppException('Elegí un empleado vigente.');
            if (!in_array($type, $types, true)) throw new AppException('Elegí el tipo de licencia.');
            if ($s === '' || $e2 === '') throw new AppException('Las fechas de inicio y fin son obligatorias.');
            if ($e2 < $s) throw new AppException('La fecha de fin no puede ser anterior a la de inicio.');
            if (q_one("SELECT id FROM leaves WHERE employee_id = ? AND status <> 'rechazada' AND start_date <= ? AND end_date >= ?", [$emp, $e2, $s])) throw new AppException('El empleado ya tiene una licencia que se superpone con esas fechas.');
            q('INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason, status, user_id, notes) VALUES (?,?,?,?,?,?,?,?)',
              [$emp, $type, $s, $e2, post_str('reason', 255), 'pendiente', acting_user_id(), post_str('notes', 500)]);
            audit('licencia_creada', 'leaves', (int)db()->lastInsertId(), $type . ' ' . $s . ' a ' . $e2);
            flash('success', 'Licencia registrada como pendiente de aprobación.');
        } elseif ($action === 'approve' || $action === 'reject') {
            $l = q_one('SELECT * FROM leaves WHERE id = ?', [post_int('id')]);
            if (!$l) throw new AppException('La licencia no existe.');
            if ($l['status'] !== 'pendiente') throw new AppException('Esta licencia ya fue resuelta.');
            q('UPDATE leaves SET status = ?, approved_by = ? WHERE id = ?', [$action === 'approve' ? 'aprobada' : 'rechazada', acting_user_id(), $l['id']]);
            audit('licencia_' . ($action === 'approve' ? 'aprobada' : 'rechazada'), 'leaves', (int)$l['id'], '');
            flash('success', 'Licencia ' . ($action === 'approve' ? 'aprobada' : 'rechazada') . '.');
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/personal/licencias/index.php');
}

$emp = get_int('empleado'); $st = get_str('estado', 10); $from = get_date('desde', date('Y-01-01')); $to = get_date('hasta', date('Y-12-31'));
$where = ['l.start_date <= :t AND l.end_date >= :f']; $p = [':f' => $from, ':t' => $to];
if ($emp > 0) { $where[] = 'l.employee_id = :e'; $p[':e'] = $emp; }
if (in_array($st, ['pendiente', 'aprobada', 'rechazada'], true)) { $where[] = 'l.status = :st'; $p[':st'] = $st; }
$pg = paginate('l.*, e.first_name, e.last_name, a.full_name AS approver', 'leaves l JOIN employees e ON e.id = l.employee_id LEFT JOIN users a ON a.id = l.approved_by WHERE ' . implode(' AND ', $where), $p, 'l.start_date DESC, l.id DESC');
$emps = q_all("SELECT id, CONCAT(last_name, ', ', first_name) AS n FROM employees WHERE status <> 'baja' ORDER BY last_name, first_name");
$allEmps = q_all("SELECT id, CONCAT(last_name, ', ', first_name) AS n FROM employees ORDER BY last_name, first_name");

$pageTitle = 'Licencias';
$active = 'per.licencias';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Licencias</h1></div>
<div class="row g-3">
  <div class="col-lg-4"><div class="fc-card"><div class="fc-card-h">Registrar licencia</div><div class="fc-card-b"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="mb-2"><label class="form-label required">Empleado</label><select class="form-select" name="employee_id" required><?= options($emps, 'id', 'n', old('employee_id'), 'Elegir…') ?></select></div>
    <div class="mb-2"><label class="form-label required">Tipo</label><select class="form-select" name="leave_type" required><?php foreach ($types as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
    <div class="row"><div class="col-6 mb-2"><label class="form-label required">Desde</label><input type="date" class="form-control" name="start_date" required></div><div class="col-6 mb-2"><label class="form-label required">Hasta</label><input type="date" class="form-control" name="end_date" required></div></div>
    <div class="mb-2"><label class="form-label">Motivo o referencia</label><input class="form-control" name="reason" maxlength="255"></div>
    <div class="mb-3"><label class="form-label">Observaciones</label><textarea class="form-control" name="notes" rows="2" maxlength="500"></textarea></div>
    <button class="btn btn-primary w-100">Registrar licencia</button></form></div></div></div>
  <div class="col-lg-8">
    <form class="row g-2 mb-3 filters" method="get"><div class="col-6 col-md-3"><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div><div class="col-6 col-md-3"><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
      <div class="col-6 col-md-3"><select class="form-select form-select-sm" name="empleado"><?= options($allEmps, 'id', 'n', $emp, 'Todos') ?></select></div>
      <div class="col-4 col-md-2"><select class="form-select form-select-sm" name="estado"><option value="">Estado</option><?php foreach (['pendiente', 'aprobada', 'rechazada'] as $s): ?><option value="<?= $s ?>" <?= $st === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
      <div class="col-2 col-md-1"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div></form>
    <div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table"><thead><tr><th>Empleado</th><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Estado</th><th></th></tr></thead><tbody>
      <?php foreach ($pg['rows'] as $l): ?><tr><td><?= e($l['last_name'] . ', ' . $l['first_name']) ?><div class="small text-muted"><?= e($l['reason']) ?></div></td><td><?= e($l['leave_type']) ?></td><td><?= e(date_ar($l['start_date'])) ?></td><td><?= e(date_ar($l['end_date'])) ?></td>
        <td><?= status_badge($l['status']) ?><?= $l['approver'] ? '<div class="small text-muted">' . e($l['approver']) . '</div>' : '' ?></td>
        <td class="text-end text-nowrap"><?php if ($l['status'] === 'pendiente'): ?>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-outline-success" title="Aprobar"><i class="bi bi-check-lg"></i></button></form>
          <form method="post" class="d-inline" data-confirm="¿Rechazar esta licencia?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-outline-danger" title="Rechazar"><i class="bi bi-x-lg"></i></button></form><?php endif; ?></td></tr>
      <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="6" class="text-center text-muted py-4">No hay licencias para los filtros elegidos.</td></tr><?php endif; ?></tbody></table></div></div>
    <?= render_pagination($pg) ?>
  </div>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
