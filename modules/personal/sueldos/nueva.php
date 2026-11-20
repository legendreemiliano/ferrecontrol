<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_perm('payroll.generate');

$emps = q_all("SELECT id, CONCAT(last_name, ', ', first_name, ' · ', position) AS n FROM employees WHERE status <> 'baja' ORDER BY last_name, first_name");
$concepts = q_all('SELECT * FROM payroll_concepts WHERE active = 1 ORDER BY kind, id');
$calc = null;

if (is_post()) {
    csrf_check();
    try {
        $empId = post_int('employee_id'); $period = post_str('period', 7);
        $input = [
            'concept_ids' => array_map('intval', (array)($_POST['concept_ids'] ?? [])),
            'overtime_hours' => post_dec('overtime_hours'), 'overtime_rate' => post_dec('overtime_rate'),
            'bonus' => post_dec('bonus'), 'non_rem' => post_dec('non_rem'),
            'absence_discount' => isset($_POST['absence_discount']),
            'other_deduction' => post_dec('other_deduction'), 'other_deduction_label' => post_str('other_deduction_label', 100),
        ];
        if (!q_one("SELECT id FROM employees WHERE id = ? AND status <> 'baja'", [$empId])) throw new AppException('Elegí un empleado vigente.');
        $calc = payroll_calculate($empId, $period, $input);
        if (post_str('action', 10) === 'save') {
            $id = payroll_save($calc, post_str('notes', 500));
            flash('success', 'Liquidación guardada como borrador.');
            redirect('modules/personal/sueldos/ver.php?id=' . $id);
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
        $calc = null;
    }
}
$selEmp = (int)old('employee_id', 0);
$suggestedRate = null;
if ($selEmp) {
    $b = q_val('SELECT base_salary FROM employees WHERE id = ?', [$selEmp]);
    if ($b) $suggestedRate = money_round((float)$b / max(1.0, (float)setting('monthly_hours', '200')) * (float)setting('overtime_multiplier', '1.5'));
}
$checked = (array)($_POST['concept_ids'] ?? []);
$kindLabel = ['remunerativo' => 'Remunerativos', 'no_remunerativo' => 'No remunerativos', 'descuento' => 'Descuentos y retenciones'];

$pageTitle = 'Nueva liquidación';
$active = 'per.sueldos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Nueva liquidación estimada</h1><a class="btn btn-light" href="<?= e(url('modules/personal/sueldos/index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a></div>
<form method="post"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="fc-card mb-3"><div class="fc-card-b">
      <div class="mb-2"><label class="form-label required">Empleado</label><select class="form-select" name="employee_id" required><?= options($emps, 'id', 'n', $selEmp, 'Elegir…') ?></select></div>
      <div class="mb-2"><label class="form-label required">Período</label><input type="month" class="form-control" name="period" required value="<?= e(old('period', date('Y-m', strtotime('first day of last month')))) ?>"></div>
      <hr>
      <div class="row"><div class="col-6 mb-2"><label class="form-label">Horas extra autorizadas</label><input class="form-control" type="number" name="overtime_hours" min="0" step="0.25" value="<?= e(old('overtime_hours', 0)) ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Valor por hora extra ($)</label><input class="form-control" type="number" name="overtime_rate" min="0" step="0.01" value="<?= e(old('overtime_rate', 0)) ?>"></div></div>
      <?php if ($suggestedRate): ?><div class="form-text mb-2">Referencia: básico ÷ <?= e(setting('monthly_hours', '200')) ?> h × <?= e(setting('overtime_multiplier', '1.50')) ?> = <?= e(money($suggestedRate)) ?> (valores de ejemplo: validar con el convenio).</div><?php endif; ?>
      <div class="row"><div class="col-6 mb-2"><label class="form-label">Bonificación remunerativa ($)</label><input class="form-control" type="number" name="bonus" min="0" step="0.01" value="<?= e(old('bonus', 0)) ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Otros no remunerativos ($)</label><input class="form-control" type="number" name="non_rem" min="0" step="0.01" value="<?= e(old('non_rem', 0)) ?>"></div></div>
      <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="absence_discount" id="abs" <?= !is_post() || isset($_POST['absence_discount']) ? 'checked' : '' ?>><label class="form-check-label" for="abs">Descontar ausencias injustificadas registradas en el período</label></div>
      <div class="row"><div class="col-6 mb-2"><label class="form-label">Otro descuento ($)</label><input class="form-control" type="number" name="other_deduction" min="0" step="0.01" value="<?= e(old('other_deduction', 0)) ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Detalle</label><input class="form-control" name="other_deduction_label" maxlength="100" value="<?= e(old('other_deduction_label')) ?>"></div></div>
    </div></div>
    <div class="fc-card"><div class="fc-card-h">Conceptos configurados</div><div class="fc-card-b">
      <?php foreach ($kindLabel as $k => $lbl): $list = array_filter($concepts, function ($c) use ($k) { return $c['kind'] === $k; }); if (!$list) continue; ?>
        <div class="small fw-semibold mt-2"><?= e($lbl) ?></div>
        <?php foreach ($list as $c): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="concept_ids[]" value="<?= (int)$c['id'] ?>" id="c<?= (int)$c['id'] ?>" <?= in_array((string)$c['id'], array_map('strval', $checked), true) ? 'checked' : '' ?>>
          <label class="form-check-label" for="c<?= (int)$c['id'] ?>"><?= e($c['name']) ?> <span class="text-muted small">(<?= $c['calc_type'] === 'fijo' ? e(money($c['value'])) : e(num($c['value'], 2)) . ' %' ?>)</span> <?= $c['validated'] ? '' : badge('Sin validar', 'warning') ?></label></div><?php endforeach; ?>
      <?php endforeach; if (!$concepts): ?><span class="text-muted small">No hay conceptos activos.</span><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="d-flex gap-2 mb-3"><button class="btn btn-primary" name="action" value="preview"><i class="bi bi-calculator"></i> Calcular vista previa</button></div>
    <?php if ($calc): ?>
      <div class="fc-card"><div class="fc-card-h">Vista previa · <?= e(employee_full_name($calc['employee'])) ?> · <?= e(period_label($calc['period'])) ?></div>
        <?php foreach ($calc['warnings'] as $w): ?><div class="alert alert-warning m-3 mb-0 py-2 small"><?= e($w) ?></div><?php endforeach; ?>
        <div class="table-responsive"><table class="table table-sm mb-0 fc-table"><thead><tr><th>Concepto</th><th>Tipo</th><th>Detalle</th><th class="text-end">Importe</th></tr></thead><tbody>
          <?php foreach ($calc['items'] as $i): ?><tr><td><?= e($i['concept']) ?></td><td><?= e(str_replace('_', ' ', $i['kind'])) ?></td><td class="small text-muted"><?= e($i['detail']) ?></td><td class="text-end <?= $i['kind'] === 'descuento' ? 'text-danger' : '' ?>"><?= $i['kind'] === 'descuento' ? '- ' : '' ?><?= e(money($i['amount'])) ?></td></tr><?php endforeach; ?></tbody>
          <tfoot><tr><td colspan="3" class="text-end">Total remunerativo</td><td class="text-end"><?= e(money($calc['total_rem'])) ?></td></tr><tr><td colspan="3" class="text-end">No remunerativo</td><td class="text-end"><?= e(money($calc['total_non_rem'])) ?></td></tr>
            <tr><td colspan="3" class="text-end">Total bruto</td><td class="text-end"><?= e(money($calc['gross'])) ?></td></tr><tr><td colspan="3" class="text-end">Descuentos y retenciones</td><td class="text-end">- <?= e(money($calc['total_ded'])) ?></td></tr>
            <tr class="fw-bold fs-6"><td colspan="3" class="text-end">NETO ESTIMADO</td><td class="text-end"><?= e(money($calc['net'])) ?></td></tr></tfoot></table></div>
        <div class="fc-card-b"><div class="mb-2"><label class="form-label">Observaciones</label><input class="form-control" name="notes" maxlength="500"></div>
          <button class="btn btn-accent" name="action" value="save" data-confirm="¿Guardar la liquidación como borrador?"><i class="bi bi-save"></i> Guardar liquidación</button>
          <p class="fc-note mt-3 mb-0">Neto estimado = remunerativo + no remunerativo − descuentos y retenciones. Es una estimación de gestión: el cálculo definitivo depende de las normas laborales y fiscales, el convenio y la situación de cada empleado.</p></div></div>
    <?php else: ?><div class="fc-note">Completá los datos y presioná «Calcular vista previa». Recién al guardar se crea la liquidación (como borrador).</div><?php endif; ?>
  </div>
</div></form>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
