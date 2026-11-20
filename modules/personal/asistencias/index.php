<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_perm('attendance.manage');

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 10);
        if ($action === 'save') {
            attendance_save(post_int('employee_id'), post_date('att_date'), post_int('shift', 1), post_str('status', 10),
                            post_str('check_in', 5) ?: null, post_str('check_out', 5) ?: null, isset($_POST['justified']), post_str('justification', 255));
            audit('asistencia_registrada', 'attendances', null, 'Empleado #' . post_int('employee_id') . ' ' . post_date('att_date'));
            flash('success', 'Asistencia guardada.');
        } elseif ($action === 'delete') {
            $a = q_one('SELECT * FROM attendances WHERE id = ?', [post_int('id')]);
            if (!$a) throw new AppException('El registro no existe.');
            q('DELETE FROM attendances WHERE id = ?', [$a['id']]);
            audit('asistencia_eliminada', 'attendances', (int)$a['id'], 'Empleado #' . $a['employee_id'] . ' ' . $a['att_date'] . ' (corrección de error de carga)');
            flash('success', 'Registro eliminado (quedó constancia en la auditoría).');
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/personal/asistencias/index.php?fecha=' . urlencode(post_date('att_date', get_str('fecha', 10) ?: today())) . '&turno=' . max(1, post_int('shift', 1)));
}

$date = get_date('fecha', today());
$shift = in_array(get_int('turno', 1), [1, 2], true) ? get_int('turno', 1) : 1;
$rows = q_all("SELECT e.id, e.first_name, e.last_name, e.position, a.id AS att_id, a.check_in, a.check_out, a.status, a.justified, a.justification
               FROM employees e LEFT JOIN attendances a ON a.employee_id = e.id AND a.att_date = :d AND a.shift = :s
               WHERE e.status <> 'baja' ORDER BY e.last_name, e.first_name", [':d' => $date, ':s' => $shift]);
// historial reciente
$hFrom = get_date('desde', days_ago(-14)); $hTo = get_date('hasta', today()); $hEmp = get_int('empleado');
$hw = ['a.att_date BETWEEN :f AND :t']; $hp = [':f' => $hFrom, ':t' => $hTo];
if ($hEmp > 0) { $hw[] = 'a.employee_id = :e'; $hp[':e'] = $hEmp; }
$pg = paginate('a.*, e.first_name, e.last_name', 'attendances a JOIN employees e ON e.id = a.employee_id WHERE ' . implode(' AND ', $hw), $hp, 'a.att_date DESC, e.last_name', 15);
$emps = q_all("SELECT id, CONCAT(last_name, ', ', first_name) AS n FROM employees ORDER BY last_name, first_name");

$pageTitle = 'Asistencias';
$active = 'per.asistencias';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Asistencia y presentismo</h1></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-6 col-md-3"><label class="form-label small mb-0">Fecha a cargar</label><input type="date" class="form-control form-control-sm" name="fecha" value="<?= e($date) ?>" max="<?= e(today()) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Turno</label><select class="form-select form-select-sm" name="turno"><option value="1" <?= $shift === 1 ? 'selected' : '' ?>>Turno 1</option><option value="2" <?= $shift === 2 ? 'selected' : '' ?>>Turno 2</option></select></div>
  <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100">Ver día</button></div>
</form>
<div class="fc-card mb-4"><div class="fc-card-h"><?= e(weekday_es($date)) ?> <?= e(date_ar($date)) ?> · Turno <?= $shift ?></div>
<div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr class="small text-muted"><th>Empleado</th><th style="width:120px">Entrada</th><th style="width:120px">Salida</th><th style="width:140px">Estado</th><th>Justificación</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $fid = 'f' . (int)$r['id']; ?>
    <tr><td><?= e($r['last_name'] . ', ' . $r['first_name']) ?><div class="small text-muted"><?= e($r['position']) ?></div></td>
      <td><input form="<?= $fid ?>" class="form-control form-control-sm" type="time" name="check_in" value="<?= e(substr((string)$r['check_in'], 0, 5)) ?>"></td>
      <td><input form="<?= $fid ?>" class="form-control form-control-sm" type="time" name="check_out" value="<?= e(substr((string)$r['check_out'], 0, 5)) ?>"></td>
      <td><select form="<?= $fid ?>" class="form-select form-select-sm" name="status"><?php foreach (['presente', 'ausente', 'tarde', 'licencia', 'franco'] as $s): ?><option value="<?= $s ?>" <?= ($r['status'] ?? 'presente') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></td>
      <td><div class="input-group input-group-sm"><div class="input-group-text"><input form="<?= $fid ?>" class="form-check-input mt-0" type="checkbox" name="justified" title="Justificada" <?= $r['justified'] ? 'checked' : '' ?>></div>
        <input form="<?= $fid ?>" class="form-control" name="justification" maxlength="255" placeholder="Motivo" value="<?= e($r['justification']) ?>"></div></td>
      <td class="text-nowrap"><form method="post" id="<?= $fid ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="employee_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="att_date" value="<?= e($date) ?>"><input type="hidden" name="shift" value="<?= $shift ?>">
        <button class="btn btn-sm <?= $r['att_id'] ? 'btn-outline-primary' : 'btn-primary' ?>"><i class="bi bi-check-lg"></i> <?= $r['att_id'] ? 'Actualizar' : 'Guardar' ?></button></form>
        <?php if ($r['att_id']): ?><form method="post" class="d-inline" data-confirm="¿Eliminar este registro de asistencia? Usalo solo para corregir un error de carga."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['att_id'] ?>"><input type="hidden" name="att_date" value="<?= e($date) ?>"><input type="hidden" name="shift" value="<?= $shift ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<p class="small text-muted">Si cargás «Presente» con una hora de entrada posterior al horario habitual más la tolerancia, el sistema lo marca automáticamente como «Tarde». Hay un solo registro por empleado, día y turno.</p>

<h2 class="h5 mt-4">Historial</h2>
<form class="row g-2 mb-3 filters" method="get"><input type="hidden" name="fecha" value="<?= e($date) ?>"><input type="hidden" name="turno" value="<?= $shift ?>">
  <div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($hFrom) ?>"></div><div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($hTo) ?>"></div>
  <div class="col-8 col-md-4"><select class="form-select form-select-sm" name="empleado"><?= options($emps, 'id', 'n', $hEmp, 'Todos los empleados') ?></select></div><div class="col-4 col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Filtrar</button></div></form>
<div class="fc-card"><div class="table-responsive"><table class="table table-sm mb-0 fc-table"><thead><tr><th>Fecha</th><th>Empleado</th><th>Turno</th><th>Entrada</th><th>Salida</th><th>Estado</th><th>Justificación</th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $a): ?><tr><td><?= e(weekday_es($a['att_date'])) ?> <?= e(date_ar($a['att_date'])) ?></td><td><?= e($a['last_name'] . ', ' . $a['first_name']) ?></td><td><?= (int)$a['shift'] ?></td><td><?= e(substr((string)$a['check_in'], 0, 5) ?: '—') ?></td><td><?= e(substr((string)$a['check_out'], 0, 5) ?: '—') ?></td><td><?= status_badge($a['status']) ?></td><td class="small"><?= $a['justified'] ? 'Justificada. ' : '' ?><?= e($a['justification']) ?></td></tr>
  <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="7" class="text-center text-muted py-3">Sin registros.</td></tr><?php endif; ?></tbody></table></div></div>
<?= render_pagination($pg) ?>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
