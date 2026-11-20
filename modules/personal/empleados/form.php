<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_perm('employees.edit');
$id = get_int('id');
$emp = $id ? q_one('SELECT * FROM employees WHERE id = ?', [$id]) : null;
if ($id && !$emp) { flash('error', 'El empleado no existe.'); redirect('modules/personal/empleados/index.php'); }
$canSalary = can('payroll.view');   // solo quien ve sueldos puede cargarlos

if (is_post()) {
    csrf_check();
    try {
        $first = post_str('first_name', 80); $last = post_str('last_name', 80); $dni = preg_replace('/\D/', '', post_str('dni', 15));
        $pos = post_str('position', 80); $hire = post_date('hire_date'); $email = post_str('email', 120);
        $contract = post_str('contract_type', 20);
        if ($first === '' || $last === '') throw new AppException('Nombre y apellido son obligatorios.');
        if (!preg_match('/^\d{7,9}$/', $dni)) throw new AppException('El DNI debe tener entre 7 y 9 números.');
        if ($pos === '') throw new AppException('Indicá el puesto.');
        if ($hire === '') throw new AppException('La fecha de ingreso no es válida.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppException('El correo electrónico no es válido.');
        if (!in_array($contract, ['tiempo_completo', 'part_time', 'eventual', 'pasantia'], true)) throw new AppException('Elegí el tipo de contratación.');
        if (q_one('SELECT id FROM employees WHERE dni = ? AND id <> ?', [$dni, $id])) throw new AppException('Ya existe un empleado con ese DNI.');
        $salary = $canSalary ? post_dec('base_salary') : (float)($emp['base_salary'] ?? 0);
        if ($salary < 0) throw new AppException('El sueldo básico no puede ser negativo.');
        $vals = [$first, $last, $dni, $pos, $hire, post_str('phone', 40), $email ?: null, post_str('address', 200), $contract, $salary, post_str('tasks', 1000), post_str('notes', 1000)];
        if ($emp) {
            q('UPDATE employees SET first_name=?, last_name=?, dni=?, position=?, hire_date=?, phone=?, email=?, address=?, contract_type=?, base_salary=?, tasks=?, notes=? WHERE id=?', array_merge($vals, [$id]));
            audit('empleado_editado', 'employees', $id, $last . ', ' . $first);
            flash('success', 'Empleado actualizado.');
            redirect('modules/personal/empleados/ver.php?id=' . $id);
        }
        q('INSERT INTO employees (first_name, last_name, dni, position, hire_date, phone, email, address, contract_type, base_salary, tasks, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', $vals);
        $nid = (int)db()->lastInsertId();
        audit('empleado_creado', 'employees', $nid, $last . ', ' . $first);
        flash('success', 'Empleado registrado.');
        redirect('modules/personal/empleados/ver.php?id=' . $nid);
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
}
$v = function ($k, $d = '') use ($emp) { return old($k, $emp[$k] ?? $d); };
$pageTitle = $emp ? 'Editar empleado' : 'Nuevo empleado';
$active = 'per.empleados';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1><?= e($pageTitle) ?></h1><a class="btn btn-light" href="<?= e(url('modules/personal/empleados/index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a></div>
<form method="post" class="fc-card" style="max-width:900px"><div class="fc-card-b"><?= csrf_field() ?><div class="row g-3">
  <div class="col-md-4"><label class="form-label required">Nombre</label><input class="form-control" name="first_name" maxlength="80" required value="<?= e($v('first_name')) ?>"></div>
  <div class="col-md-4"><label class="form-label required">Apellido</label><input class="form-control" name="last_name" maxlength="80" required value="<?= e($v('last_name')) ?>"></div>
  <div class="col-md-4"><label class="form-label required">DNI (solo números)</label><input class="form-control" name="dni" maxlength="12" required value="<?= e($v('dni')) ?>"></div>
  <div class="col-md-4"><label class="form-label required">Puesto</label><input class="form-control" name="position" maxlength="80" required value="<?= e($v('position')) ?>"></div>
  <div class="col-md-4"><label class="form-label required">Fecha de ingreso</label><input class="form-control" type="date" name="hire_date" required value="<?= e($v('hire_date')) ?>"></div>
  <div class="col-md-4"><label class="form-label required">Tipo de contratación</label><select class="form-select" name="contract_type"><?php foreach (['tiempo_completo', 'part_time', 'eventual', 'pasantia'] as $c): ?><option value="<?= $c ?>" <?= $v('contract_type', 'tiempo_completo') === $c ? 'selected' : '' ?>><?= e(contract_label($c)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><label class="form-label">Teléfono</label><input class="form-control" name="phone" maxlength="40" value="<?= e($v('phone')) ?>"></div>
  <div class="col-md-4"><label class="form-label">Correo electrónico</label><input class="form-control" type="email" name="email" maxlength="120" value="<?= e($v('email')) ?>"></div>
  <div class="col-md-4"><label class="form-label">Dirección</label><input class="form-control" name="address" maxlength="200" value="<?= e($v('address')) ?>"></div>
  <?php if ($canSalary): ?><div class="col-md-4"><label class="form-label">Sueldo básico mensual ($)</label><input class="form-control" type="number" name="base_salary" min="0" step="0.01" value="<?= e($v('base_salary', 0)) ?>"></div><?php endif; ?>
  <div class="col-12"><label class="form-label">Tareas o responsabilidades asignadas</label><textarea class="form-control" name="tasks" rows="2" maxlength="1000"><?= e($v('tasks')) ?></textarea></div>
  <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="notes" rows="2" maxlength="1000"><?= e($v('notes')) ?></textarea></div>
</div><p class="fc-note mt-3">Guardá solo los datos necesarios. Los datos personales son visibles únicamente para usuarios autorizados.</p>
<button class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button></div></form>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
