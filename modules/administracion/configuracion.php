<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('admin');

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 20);
        if ($action === 'settings') {
            $maxDisc = post_dec('max_discount_deposito');
            $tol = post_int('late_tolerance_min');
            $mh = post_dec('monthly_hours');
            $om = post_dec('overtime_multiplier');
            $ad = post_int('absence_day_divisor');
            $dm = post_dec('default_markup_pct');
            $ws = post_str('work_start', 5);
            if ($maxDisc < 0 || $maxDisc > 100) throw new AppException('El descuento máximo debe estar entre 0 y 100.');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $ws)) throw new AppException('La hora de entrada debe tener formato HH:MM.');
            if ($tol < 0 || $mh <= 0 || $om < 1 || $ad < 1 || $dm < 0) throw new AppException('Revisá los valores numéricos: no pueden ser negativos y el multiplicador de horas extra debe ser al menos 1.');
            $vals = [
                'business_name' => post_str('business_name', 120), 'business_cuit' => post_str('business_cuit', 40),
                'business_address' => post_str('business_address', 200), 'business_phone' => post_str('business_phone', 40),
                'receipt_footer' => post_str('receipt_footer', 200),
                'max_discount_deposito' => (string)$maxDisc, 'deposito_can_create_products' => isset($_POST['deposito_can_create_products']) ? '1' : '0',
                'work_start' => $ws, 'late_tolerance_min' => (string)$tol, 'monthly_hours' => (string)$mh, 'overtime_multiplier' => (string)$om,
                'absence_day_divisor' => (string)$ad, 'default_markup_type' => post_str('default_markup_type', 10) === 'margen' ? 'margen' : 'recargo',
                'default_markup_pct' => (string)$dm,
            ];
            if ($vals['business_name'] === '') throw new AppException('El nombre del comercio no puede estar vacío.');
            foreach ($vals as $k => $v) set_setting($k, $v);
            audit('configuracion', 'settings', null, 'Parámetros generales actualizados');
            flash('success', 'Configuración guardada.');
        } elseif ($action === 'pm_add' || $action === 'pm_save') {
            $id = post_int('id'); $name = post_str('name', 60); $com = post_dec('commission_pct');
            if ($name === '') throw new AppException('El medio de pago necesita un nombre.');
            if ($com < 0 || $com > 100) throw new AppException('La comisión debe estar entre 0 y 100 %.');
            $dup = q_one('SELECT id FROM payment_methods WHERE name = ? AND id <> ?', [$name, $id]);
            if ($dup) throw new AppException('Ya existe un medio de pago con ese nombre.');
            $cash = isset($_POST['is_cash']) ? 1 : 0; $act = isset($_POST['active']) ? 1 : 0;
            if ($action === 'pm_add') {
                q('INSERT INTO payment_methods (name, commission_pct, is_cash, active) VALUES (?,?,?,1)', [$name, $com, $cash]);
                audit('medio_pago_creado', 'payment_methods', (int)db()->lastInsertId(), $name);
            } else {
                q('UPDATE payment_methods SET name = ?, commission_pct = ?, is_cash = ?, active = ? WHERE id = ?', [$name, $com, $cash, $act, $id]);
                audit('medio_pago_editado', 'payment_methods', $id, $name);
            }
            flash('success', 'Medio de pago guardado.');
        } elseif ($action === 'ec_add' || $action === 'ec_save') {
            $id = post_int('id'); $name = post_str('name', 80); $ct = post_str('cost_type', 10); $fp = post_dec('fixed_pct', 50);
            if ($name === '') throw new AppException('La categoría necesita un nombre.');
            if (!in_array($ct, ['fijo', 'variable', 'mixto'], true)) throw new AppException('Elegí el tipo de costo.');
            if ($fp < 0 || $fp > 100) throw new AppException('El porcentaje fijo debe estar entre 0 y 100.');
            if (q_one('SELECT id FROM expense_categories WHERE name = ? AND id <> ?', [$name, $id])) throw new AppException('Ya existe una categoría con ese nombre.');
            $act = isset($_POST['active']) ? 1 : 0;
            if ($action === 'ec_add') {
                q('INSERT INTO expense_categories (name, cost_type, fixed_pct) VALUES (?,?,?)', [$name, $ct, $fp]);
                audit('categoria_gasto_creada', 'expense_categories', (int)db()->lastInsertId(), $name);
            } else {
                q('UPDATE expense_categories SET name = ?, cost_type = ?, fixed_pct = ?, active = ? WHERE id = ?', [$name, $ct, $fp, $act, $id]);
                audit('categoria_gasto_editada', 'expense_categories', $id, $name . ' → ' . $ct);
            }
            flash('success', 'Categoría de gasto guardada.');
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/administracion/configuracion.php');
}

$methods = q_all('SELECT * FROM payment_methods ORDER BY id');
$ecats = q_all('SELECT * FROM expense_categories ORDER BY name');
$S = function ($k, $d = '') { return setting($k, $d); };

$pageTitle = 'Configuración';
$active = 'adm.config';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Configuración</h1></div>

<div class="fc-card mb-4"><div class="fc-card-h">Datos del comercio y parámetros generales</div><div class="fc-card-b">
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label required">Nombre del comercio</label><input class="form-control" name="business_name" maxlength="120" required value="<?= e($S('business_name')) ?>"></div>
    <div class="col-md-3"><label class="form-label">CUIT</label><input class="form-control" name="business_cuit" maxlength="40" value="<?= e($S('business_cuit')) ?>"></div>
    <div class="col-md-3"><label class="form-label">Teléfono</label><input class="form-control" name="business_phone" maxlength="40" value="<?= e($S('business_phone')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Dirección</label><input class="form-control" name="business_address" maxlength="200" value="<?= e($S('business_address')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Leyenda del comprobante interno</label><input class="form-control" name="receipt_footer" maxlength="200" value="<?= e($S('receipt_footer')) ?>"></div>
    <div class="col-12"><hr class="my-1"></div>
    <div class="col-md-3"><label class="form-label">Descuento máx. del encargado de depósito (%)</label><input class="form-control" name="max_discount_deposito" type="number" step="0.01" min="0" max="100" value="<?= e($S('max_discount_deposito', '10')) ?>"></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="deposito_can_create_products" id="dcp" <?= $S('deposito_can_create_products', '1') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="dcp">El encargado de depósito puede crear productos</label></div></div>
    <div class="col-md-3"><label class="form-label">Método de precio por defecto</label><select class="form-select" name="default_markup_type"><option value="recargo" <?= $S('default_markup_type') === 'recargo' ? 'selected' : '' ?>>Recargo sobre costo</option><option value="margen" <?= $S('default_markup_type') === 'margen' ? 'selected' : '' ?>>Margen sobre ventas</option></select></div>
    <div class="col-md-3"><label class="form-label">% por defecto</label><input class="form-control" name="default_markup_pct" type="number" step="0.01" min="0" value="<?= e($S('default_markup_pct', '40')) ?>"></div>
    <div class="col-12"><hr class="my-1"><div class="fc-note">Parámetros de personal. Los valores de <strong>horas mensuales</strong> y <strong>multiplicador de hora extra</strong> son <u>ejemplos</u>: deben validarse con el convenio y la normativa laboral aplicable.</div></div>
    <div class="col-md-3"><label class="form-label">Hora de entrada habitual</label><input class="form-control" name="work_start" maxlength="5" placeholder="09:00" value="<?= e($S('work_start', '09:00')) ?>"></div>
    <div class="col-md-3"><label class="form-label">Tolerancia (minutos)</label><input class="form-control" name="late_tolerance_min" type="number" min="0" value="<?= e($S('late_tolerance_min', '10')) ?>"></div>
    <div class="col-md-2"><label class="form-label">Horas mensuales (ref.)</label><input class="form-control" name="monthly_hours" type="number" step="0.01" min="1" value="<?= e($S('monthly_hours', '200')) ?>"></div>
    <div class="col-md-2"><label class="form-label">Mult. hora extra</label><input class="form-control" name="overtime_multiplier" type="number" step="0.01" min="1" value="<?= e($S('overtime_multiplier', '1.50')) ?>"></div>
    <div class="col-md-2"><label class="form-label">Divisor día de ausencia</label><input class="form-control" name="absence_day_divisor" type="number" min="1" value="<?= e($S('absence_day_divisor', '30')) ?>"></div>
  </div>
  <button class="btn btn-primary mt-3">Guardar configuración</button>
</form></div></div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="fc-card"><div class="fc-card-h">Medios de pago</div><div class="fc-card-b">
      <p class="small text-muted">La comisión se descuenta del resultado como costo variable. Los porcentajes de ejemplo deben ajustarse al contrato real con cada procesador.</p>
      <?php foreach ($methods as $m): ?>
      <form method="post" class="row g-2 align-items-center mb-2"><?= csrf_field() ?><input type="hidden" name="action" value="pm_save"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <div class="col-5"><input class="form-control form-control-sm" name="name" maxlength="60" value="<?= e($m['name']) ?>" required></div>
        <div class="col-3"><div class="input-group input-group-sm"><input class="form-control" name="commission_pct" type="number" step="0.01" min="0" max="100" value="<?= e($m['commission_pct']) ?>"><span class="input-group-text">%</span></div></div>
        <div class="col-4 d-flex gap-2 align-items-center">
          <div class="form-check form-check-inline m-0"><input class="form-check-input" type="checkbox" name="is_cash" title="Es efectivo" <?= $m['is_cash'] ? 'checked' : '' ?>><label class="form-check-label small">Efec.</label></div>
          <div class="form-check form-check-inline m-0"><input class="form-check-input" type="checkbox" name="active" title="Activo" <?= $m['active'] ? 'checked' : '' ?>><label class="form-check-label small">Activo</label></div>
          <button class="btn btn-sm btn-outline-primary" title="Guardar"><i class="bi bi-check-lg"></i></button></div>
      </form>
      <?php endforeach; ?>
      <hr><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="pm_add">
        <div class="col-5"><input class="form-control form-control-sm" name="name" placeholder="Nuevo medio de pago" maxlength="60" required></div>
        <div class="col-3"><div class="input-group input-group-sm"><input class="form-control" name="commission_pct" type="number" step="0.01" min="0" max="100" value="0"><span class="input-group-text">%</span></div></div>
        <div class="col-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_cash"><label class="form-check-label small">Efec.</label></div></div>
        <div class="col-2"><button class="btn btn-sm btn-primary w-100">Agregar</button></div></form>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="fc-card"><div class="fc-card-h">Categorías de gasto y clasificación de costos</div><div class="fc-card-b">
      <p class="small text-muted">Elegí si cada categoría es costo <strong>fijo</strong>, <strong>variable</strong> o <strong>mixto</strong> (con el % que se considera fijo). Los gastos nuevos usan esta clasificación por defecto.</p>
      <?php foreach ($ecats as $c): ?>
      <form method="post" class="row g-2 align-items-center mb-2"><?= csrf_field() ?><input type="hidden" name="action" value="ec_save"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <div class="col-5"><input class="form-control form-control-sm" name="name" maxlength="80" value="<?= e($c['name']) ?>" required></div>
        <div class="col-3"><select class="form-select form-select-sm" name="cost_type"><?php foreach (['fijo', 'variable', 'mixto'] as $t): ?><option value="<?= $t ?>" <?= $c['cost_type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select></div>
        <div class="col-2"><input class="form-control form-control-sm" name="fixed_pct" type="number" step="0.01" min="0" max="100" value="<?= e($c['fixed_pct']) ?>" title="% fijo (solo mixto)"></div>
        <div class="col-2 d-flex gap-1 align-items-center"><input class="form-check-input" type="checkbox" name="active" title="Activa" <?= $c['active'] ? 'checked' : '' ?>><button class="btn btn-sm btn-outline-primary" title="Guardar"><i class="bi bi-check-lg"></i></button></div>
      </form>
      <?php endforeach; ?>
      <hr><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="ec_add">
        <div class="col-5"><input class="form-control form-control-sm" name="name" placeholder="Nueva categoría" maxlength="80" required></div>
        <div class="col-3"><select class="form-select form-select-sm" name="cost_type"><option value="fijo">Fijo</option><option value="variable" selected>Variable</option><option value="mixto">Mixto</option></select></div>
        <div class="col-2"><input class="form-control form-control-sm" name="fixed_pct" type="number" step="0.01" min="0" max="100" value="50"></div>
        <div class="col-2"><button class="btn btn-sm btn-primary w-100">Agregar</button></div></form>
    </div></div>
  </div>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
