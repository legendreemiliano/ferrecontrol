<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('purchases.view');
$id = get_int('id');
$p = q_one('SELECT p.*, s.name AS supplier_name, s.cuit, u.full_name, vu.full_name AS voider FROM purchases p JOIN suppliers s ON s.id = p.supplier_id JOIN users u ON u.id = p.user_id LEFT JOIN users vu ON vu.id = p.voided_by WHERE p.id = ?', [$id]);
if (!$p) { flash('error', 'La compra no existe.'); redirect('modules/compras/index.php'); }

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 10);
        if ($action === 'void') {
            require_perm('purchases.void');
            purchase_void($id, post_str('reason', 255));
            flash('success', 'Compra anulada. El stock y el costo promedio fueron revertidos y el registro se conserva.');
        } elseif ($action === 'pay') {
            require_perm('admin');
            $cash = q_one("SELECT id FROM cash_movements WHERE purchase_id = ? AND source = 'compra' AND status = 'pendiente'", [$id]);
            if (!$cash) throw new AppException('Esta compra no tiene un pago pendiente.');
            expense_mark_paid((int)$cash['id'], post_int('payment_method_id'), post_date('paid_date', today()));
            flash('success', 'Pago registrado.');
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/compras/ver.php?id=' . $id);
}
$items = q_all('SELECT pi.*, pr.code, pr.name, pr.unit FROM purchase_items pi JOIN products pr ON pr.id = pi.product_id WHERE pi.purchase_id = ? ORDER BY pi.id', [$id]);
$methods = q_all('SELECT id, name FROM payment_methods WHERE active = 1 ORDER BY id');

$pageTitle = 'Compra ' . $p['number'];
$active = 'stk.compras';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Compra <?= e($p['number']) ?> <?= $p['status'] === 'anulada' ? status_badge('anulada') : status_badge($p['payment_status']) ?></h1>
  <div class="no-print"><a class="btn btn-light" href="<?= e(url('modules/compras/index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a> <button class="btn btn-outline-secondary" data-print><i class="bi bi-printer"></i></button></div></div>
<div class="row g-3">
  <div class="col-lg-8"><div class="fc-card"><div class="table-responsive"><table class="table table-sm mb-0 fc-table">
    <thead><tr><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Costo unitario</th><th class="text-end">Costo efectivo*</th><th class="text-end">Subtotal</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td><?= e($i['name']) ?> <small class="text-muted"><?= e($i['code']) ?></small></td><td class="text-end"><?= num($i['quantity']) ?> <?= e($i['unit']) ?></td><td class="text-end"><?= e(money($i['unit_cost'])) ?></td>
      <td class="text-end"><?= e(money($i['effective_unit_cost'])) ?></td><td class="text-end"><?= e(money($i['line_total'])) ?></td></tr><?php endforeach; ?></tbody>
    <tfoot><tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end"><?= e(money($p['subtotal'])) ?></td></tr>
      <tr><td colspan="4" class="text-end">Descuento</td><td class="text-end">- <?= e(money($p['discount'])) ?></td></tr>
      <tr><td colspan="4" class="text-end">Otros costos</td><td class="text-end"><?= e(money($p['extra_costs'])) ?></td></tr>
      <tr class="fw-bold"><td colspan="4" class="text-end">TOTAL</td><td class="text-end"><?= e(money($p['total'])) ?></td></tr></tfoot></table></div></div>
    <p class="small text-muted mt-2">* Costo efectivo: costo unitario con el descuento y los otros costos repartidos proporcionalmente. Es el que se usa para el costo promedio ponderado.</p></div>
  <div class="col-lg-4"><div class="fc-card"><div class="fc-card-b"><dl class="row mb-0 small">
    <dt class="col-5">Proveedor</dt><dd class="col-7"><?= e($p['supplier_name']) ?></dd><dt class="col-5">Fecha</dt><dd class="col-7"><?= e(date_ar($p['purchase_date'])) ?></dd>
    <dt class="col-5">Referencia</dt><dd class="col-7"><?= e($p['reference'] ?: '—') ?></dd><dt class="col-5">Registró</dt><dd class="col-7"><?= e($p['full_name']) ?></dd>
    <dt class="col-5">Cargada</dt><dd class="col-7"><?= e(datetime_ar($p['created_at'])) ?></dd><?php if ($p['notes']): ?><dt class="col-5">Notas</dt><dd class="col-7"><?= e($p['notes']) ?></dd><?php endif; ?>
    <?php if ($p['status'] === 'anulada'): ?><dt class="col-5 text-danger">Anulada</dt><dd class="col-7"><?= e(datetime_ar($p['voided_at'])) ?> por <?= e($p['voider']) ?><br><?= e($p['void_reason']) ?></dd><?php endif; ?></dl></div></div>
    <?php if ($p['status'] === 'confirmada' && $p['payment_status'] === 'pendiente' && can('admin')): ?>
      <div class="fc-card mt-3 no-print"><div class="fc-card-h">Registrar pago</div><div class="fc-card-b"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pay">
        <div class="mb-2"><label class="form-label">Fecha de pago</label><input type="date" class="form-control" name="paid_date" value="<?= e(today()) ?>"></div>
        <div class="mb-2"><label class="form-label required">Medio de pago</label><select class="form-select" name="payment_method_id" required><?= options($methods, 'id', 'name', null, 'Elegir…') ?></select></div>
        <button class="btn btn-primary w-100">Marcar como pagada</button></form></div></div>
    <?php endif; ?>
    <?php if ($p['status'] === 'confirmada' && can('purchases.void')): ?>
      <div class="fc-card mt-3 no-print"><div class="fc-card-h text-danger">Anular compra</div><div class="fc-card-b"><form method="post" data-confirm="¿Anular la compra? Se descontarán del stock las unidades ingresadas."><?= csrf_field() ?><input type="hidden" name="action" value="void">
        <div class="mb-2"><label class="form-label required">Motivo</label><input class="form-control" name="reason" maxlength="255" required></div>
        <button class="btn btn-outline-danger w-100">Anular compra</button></form>
        <p class="small text-muted mt-2 mb-0">Solo se puede anular si las unidades siguen en stock.</p></div></div>
    <?php endif; ?>
  </div>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
