<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_perm('payroll.view');
$id = get_int('id');
$p = q_one('SELECT p.*, e.first_name, e.last_name, e.dni, e.position, g.full_name AS generator, a.full_name AS approver FROM payrolls p JOIN employees e ON e.id = p.employee_id JOIN users g ON g.id = p.generated_by LEFT JOIN users a ON a.id = p.approved_by WHERE p.id = ?', [$id]);
if (!$p) { flash('error', 'La liquidación no existe.'); redirect('modules/personal/sueldos/index.php'); }

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 10);
        if ($action === 'approve') { require_perm('payroll.approve'); payroll_approve($id); flash('success', 'Liquidación aprobada.'); }
        elseif ($action === 'pay') { require_perm('payroll.pay'); payroll_pay($id, post_int('payment_method_id'), post_date('paid_date', today())); flash('success', 'Pago registrado: el egreso «Sueldos» ya figura en Administración.'); }
        elseif ($action === 'void') { require_perm('payroll.pay'); payroll_void($id); flash('success', 'Liquidación anulada. Queda en el historial.'); }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/personal/sueldos/ver.php?id=' . $id);
}
$items = q_all('SELECT * FROM payroll_items WHERE payroll_id = ? ORDER BY kind, id', [$id]);
$methods = q_all('SELECT id, name FROM payment_methods WHERE active = 1 ORDER BY id');

$pageTitle = 'Liquidación ' . period_label($p['period']);
$active = 'per.sueldos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head no-print"><h1>Liquidación estimada <?= status_badge($p['status']) ?></h1>
  <div><a class="btn btn-light" href="<?= e(url('modules/personal/sueldos/index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a> <button class="btn btn-outline-secondary" data-print><i class="bi bi-printer"></i> Imprimir</button></div></div>
<div class="row g-3"><div class="col-lg-8"><div class="receipt">
  <div class="text-center mb-3"><h5 class="mb-0"><?= e(setting('business_name', 'FerreControl')) ?></h5><div class="fw-bold mt-1">DETALLE DE LIQUIDACIÓN ESTIMADA</div><div class="small text-danger">Documento de gestión interna. No es un recibo de sueldo oficial.</div></div>
  <div class="row small mb-2"><div class="col-6">Empleado: <strong><?= e($p['last_name'] . ', ' . $p['first_name']) ?></strong></div><div class="col-6 text-end">Período: <strong><?= e(period_label($p['period'])) ?></strong></div>
    <div class="col-6">DNI: <?= e($p['dni']) ?></div><div class="col-6 text-end">Puesto: <?= e($p['position']) ?></div></div>
  <table class="table table-sm"><thead><tr><th>Concepto</th><th>Detalle</th><th class="text-end">Remunerativo</th><th class="text-end">No remun.</th><th class="text-end">Descuentos</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td><?= e($i['concept']) ?></td><td class="small text-muted"><?= e($i['detail']) ?></td>
      <td class="text-end"><?= $i['kind'] === 'remunerativo' ? e(money($i['amount'])) : '' ?></td><td class="text-end"><?= $i['kind'] === 'no_remunerativo' ? e(money($i['amount'])) : '' ?></td><td class="text-end"><?= $i['kind'] === 'descuento' ? e(money($i['amount'])) : '' ?></td></tr><?php endforeach; ?></tbody>
    <tfoot><tr class="fw-semibold"><td colspan="2" class="text-end">Totales</td><td class="text-end"><?= e(money($p['total_remunerative'])) ?></td><td class="text-end"><?= e(money($p['total_non_remunerative'])) ?></td><td class="text-end"><?= e(money($p['total_deductions'])) ?></td></tr>
      <tr><td colspan="4" class="text-end">Total bruto</td><td class="text-end"><?= e(money($p['gross_total'])) ?></td></tr><tr class="fw-bold fs-6"><td colspan="4" class="text-end">NETO ESTIMADO</td><td class="text-end"><?= e(money($p['net_total'])) ?></td></tr></tfoot></table>
  <div class="small text-muted">Generada el <?= e(datetime_ar($p['generated_at'])) ?> por <?= e($p['generator']) ?><?= $p['approver'] ? ' · Aprobada por ' . e($p['approver']) : '' ?><?= $p['paid_at'] ? ' · Pagada el ' . e(datetime_ar($p['paid_at'])) : '' ?></div>
  <?php if ($p['notes']): ?><div class="small mt-1">Observaciones: <?= e($p['notes']) ?></div><?php endif; ?>
</div></div>
<div class="col-lg-4 no-print">
  <?php if ($p['status'] === 'borrador' && can('payroll.approve')): ?><div class="fc-card mb-3"><div class="fc-card-b"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><button class="btn btn-primary w-100" data-confirm="¿Aprobar esta liquidación?"><i class="bi bi-check2-circle"></i> Aprobar liquidación</button></form></div></div><?php endif; ?>
  <?php if ($p['status'] === 'aprobada' && can('payroll.pay')): ?><div class="fc-card mb-3"><div class="fc-card-h">Registrar pago</div><div class="fc-card-b"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pay">
    <div class="mb-2"><label class="form-label">Fecha</label><input type="date" class="form-control" name="paid_date" value="<?= e(today()) ?>" max="<?= e(today()) ?>"></div>
    <div class="mb-2"><label class="form-label required">Medio de pago</label><select class="form-select" name="payment_method_id" required><?= options($methods, 'id', 'name', null, 'Elegir…') ?></select></div>
    <button class="btn btn-accent w-100" data-confirm="¿Registrar el pago? Se generará el egreso en Administración.">Marcar como pagada</button></form></div></div><?php endif; ?>
  <?php if (in_array($p['status'], ['borrador', 'aprobada', 'pagada'], true) && can('payroll.pay')): ?><div class="fc-card"><div class="fc-card-b"><form method="post" data-confirm="¿Anular la liquidación? <?= $p['status'] === 'pagada' ? 'También se anulará su egreso en Administración.' : '' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="void"><button class="btn btn-outline-danger w-100">Anular liquidación</button></form></div></div><?php endif; ?>
  <?php if ($p['status'] === 'borrador' && !can('payroll.approve')): ?><div class="fc-note">Esta liquidación espera la aprobación del administrador.</div><?php endif; ?>
</div></div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
