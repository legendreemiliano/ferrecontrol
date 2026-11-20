<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_perm('payroll.config');

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 10); $id = post_int('id'); $name = post_str('name', 100);
        $kind = post_str('kind', 20); $calc = post_str('calc_type', 30); $value = post_dec('value');
        if ($name === '') throw new AppException('El concepto necesita un nombre.');
        if (!in_array($kind, ['remunerativo', 'no_remunerativo', 'descuento'], true)) throw new AppException('Tipo de concepto inválido.');
        if (!in_array($calc, ['fijo', 'porcentaje_basico', 'porcentaje_remunerativo'], true)) throw new AppException('Forma de cálculo inválida.');
        if ($value < 0 || ($calc !== 'fijo' && $value > 100)) throw new AppException('El valor no es válido (los porcentajes van de 0 a 100).');
        if (q_one('SELECT id FROM payroll_concepts WHERE name = ? AND id <> ?', [$name, $id])) throw new AppException('Ya existe un concepto con ese nombre.');
        if ($action === 'add') {
            q('INSERT INTO payroll_concepts (name, kind, calc_type, value, validated, notes) VALUES (?,?,?,?,0,?)', [$name, $kind, $calc, $value, post_str('notes', 255)]);
            audit('concepto_sueldo_creado', 'payroll_concepts', (int)db()->lastInsertId(), $name);
            flash('success', 'Concepto agregado (sin validar).');
        } else {
            $old = q_one('SELECT * FROM payroll_concepts WHERE id = ?', [$id]);
            if (!$old) throw new AppException('El concepto no existe.');
            $validate = isset($_POST['validated']) ? 1 : 0;
            $changed = $old['kind'] !== $kind || $old['calc_type'] !== $calc || abs((float)$old['value'] - $value) > 0.0001;
            if ($changed) $validate = isset($_POST['validated']) && (int)$old['validated'] === 0 ? 1 : 0;   // si cambia el valor, hay que volver a validarlo explícitamente
            q('UPDATE payroll_concepts SET name=?, kind=?, calc_type=?, value=?, active=?, notes=?, validated=?, validated_by=?, validated_at=? WHERE id=?',
              [$name, $kind, $calc, $value, isset($_POST['active']) ? 1 : 0, post_str('notes', 255), $validate, $validate ? acting_user_id() : null, $validate ? now_sql() : null, $id]);
            audit('concepto_sueldo_editado', 'payroll_concepts', $id, $name . ' ' . $calc . ' ' . $value . ($validate ? ' (validado)' : ' (sin validar)'));
            flash('success', 'Concepto guardado' . ($validate ? ' y validado.' : '.'));
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/personal/sueldos/conceptos.php');
}
$list = q_all('SELECT c.*, u.full_name AS validator FROM payroll_concepts c LEFT JOIN users u ON u.id = c.validated_by ORDER BY c.kind, c.id');
$kinds = ['remunerativo' => 'Remunerativo', 'no_remunerativo' => 'No remunerativo', 'descuento' => 'Descuento / retención'];
$calcs = ['fijo' => 'Monto fijo ($)', 'porcentaje_basico' => '% del básico', 'porcentaje_remunerativo' => '% del total remunerativo'];

$pageTitle = 'Conceptos de sueldo';
$active = 'per.conceptos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Conceptos de sueldo</h1></div>
<div class="fc-note mb-3"><strong>Importante:</strong> el sistema no trae porcentajes legales precargados. Cargá cada valor según la normativa y el convenio vigentes y marcá «Validado» cuando el responsable lo haya revisado. Si cambiás un valor, hay que volver a validarlo. Una liquidación que use un concepto sin validar muestra una advertencia.</div>
<div class="fc-card"><div class="fc-card-b">
<?php foreach ($list as $c): ?>
  <form method="post" class="row g-2 align-items-center mb-2 pb-2 border-bottom"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="col-md-3"><input class="form-control form-control-sm" name="name" maxlength="100" value="<?= e($c['name']) ?>" required></div>
    <div class="col-md-2"><select class="form-select form-select-sm" name="kind"><?php foreach ($kinds as $k => $l): ?><option value="<?= $k ?>" <?= $c['kind'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select class="form-select form-select-sm" name="calc_type"><?php foreach ($calcs as $k => $l): ?><option value="<?= $k ?>" <?= $c['calc_type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-1"><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="value" value="<?= e($c['value']) ?>"></div>
    <div class="col-md-2 small"><div class="form-check d-inline-block me-2"><input class="form-check-input" type="checkbox" name="validated" <?= $c['validated'] ? 'checked' : '' ?>><label class="form-check-label">Validado</label></div>
      <div class="form-check d-inline-block"><input class="form-check-input" type="checkbox" name="active" <?= $c['active'] ? 'checked' : '' ?>><label class="form-check-label">Activo</label></div>
      <?php if ($c['validated']): ?><div class="text-muted"><?= e($c['validator']) ?> · <?= e(date_ar($c['validated_at'])) ?></div><?php endif; ?></div>
    <div class="col-md-2"><div class="input-group input-group-sm"><input class="form-control" name="notes" maxlength="255" placeholder="Notas" value="<?= e($c['notes']) ?>"><button class="btn btn-outline-primary" title="Guardar"><i class="bi bi-check-lg"></i></button></div></div>
  </form>
<?php endforeach; ?>
<h2 class="h6 mt-4">Agregar concepto</h2>
<form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="col-md-3"><input class="form-control form-control-sm" name="name" maxlength="100" placeholder="Nombre" required></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="kind"><?php foreach ($kinds as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="calc_type"><?php foreach ($calcs as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-1"><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="value" value="0"></div>
  <div class="col-md-2"><input class="form-control form-control-sm" name="notes" maxlength="255" placeholder="Notas"></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Agregar</button></div></form>
</div></div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
