<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('sales.view');
$id = get_int('id');
$s = q_one('SELECT s.*, u.full_name, vu.full_name AS voider FROM sales s JOIN users u ON u.id = s.user_id LEFT JOIN users vu ON vu.id = s.voided_by WHERE s.id = ?', [$id]);
if (!$s) { flash('error', 'La venta no existe.'); redirect('modules/ventas/index.php'); }

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 10);
        if ($action === 'pay') {
            require_perm('sales.collect');
            sale_add_payment($id, post_int('payment_method_id'), post_dec('amount'), parse_decimal($_POST['received'] ?? null), post_str('reference', 80));
            flash('success', 'Cobro registrado.');
        } elseif ($action === 'return') {
            require_perm('sales.return');
            $qty = [];
            foreach (($_POST['ret'] ?? []) as $itemId => $n) { $n = filter_var($n, FILTER_VALIDATE_INT); if ($n && $n > 0) $qty[(int)$itemId] = $n; }
            sale_return($id, $qty, post_str('reason', 255), post_str('restock', 3) === 'si', post_int('refund_method_id'));
            flash('success', 'Devolución registrada.');
        } elseif ($action === 'void') {
            require_perm('sales.void');
            sale_void($id, post_str('reason', 255), post_int('refund_method_id'));
            flash('success', 'Venta anulada. Se devolvió el stock y se registró el reembolso; el historial se conserva.');
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/ventas/ver.php?id=' . $id);
}

$items = q_all('SELECT si.*, p.code, p.name, p.unit FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id', [$id]);
$pays = q_all('SELECT p.*, m.name AS method_name, u.full_name FROM payments p JOIN payment_methods m ON m.id = p.payment_method_id JOIN users u ON u.id = p.user_id WHERE p.sale_id = ? ORDER BY p.paid_at, p.id', [$id]);
$rets = q_all('SELECT r.*, u.full_name FROM sale_returns r JOIN users u ON u.id = r.user_id WHERE r.sale_id = ? ORDER BY r.id', [$id]);
$methods = q_all('SELECT id, name, is_cash FROM payment_methods WHERE active = 1 ORDER BY id');
$due = round((float)$s['total'] - (float)$s['returned_total'] - (float)$s['paid_total'], 2);
$canReturnSomething = false; foreach ($items as $i) if ((int)$i['quantity'] > (int)$i['returned_qty']) $canReturnSomething = true;

$pageTitle = 'Venta ' . $s['number'];
$active = 'stk.ventas';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head no-print"><h1>Venta <?= e($s['number']) ?> <?= $s['status'] === 'anulada' ? status_badge('anulada') : status_badge($s['payment_status']) ?></h1>
  <div><a class="btn btn-light" href="<?= e(url('modules/ventas/index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a>
  <?php if (can('sales.create')): ?><a class="btn btn-accent" href="<?= e(url('modules/ventas/pos.php')) ?>"><i class="bi bi-cart-plus"></i> Nueva venta</a><?php endif; ?>
  <button class="btn btn-outline-secondary" data-print><i class="bi bi-printer"></i> Imprimir comprobante</button></div></div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="receipt">
      <div class="text-center mb-3"><h4 class="mb-0"><?= e(setting('business_name', 'FerreControl')) ?></h4>
        <div class="small"><?= e(setting('business_address', '')) ?> · <?= e(setting('business_phone', '')) ?></div><div class="small">CUIT: <?= e(setting('business_cuit', '')) ?></div>
        <div class="fw-bold mt-2">COMPROBANTE INTERNO N.º <?= e($s['number']) ?></div><div class="small text-danger">No válido como factura</div></div>
      <div class="row small mb-2"><div class="col-6">Fecha: <?= e(datetime_ar($s['sale_date'])) ?></div><div class="col-6 text-end">Vendedor: <?= e($s['full_name']) ?></div>
        <div class="col-12">Cliente: <?= e($s['customer_name'] ?: 'Consumidor final') ?></div></div>
      <table class="table table-sm"><thead><tr><th>Descripción</th><th class="text-end">Cant.</th><th class="text-end">Precio</th><th class="text-end">Importe</th></tr></thead><tbody>
        <?php foreach ($items as $i): ?><tr><td><?= e($i['name']) ?><?= (int)$i['returned_qty'] > 0 ? ' <small class="text-danger">(devueltas: ' . (int)$i['returned_qty'] . ')</small>' : '' ?></td><td class="text-end"><?= num($i['quantity']) ?></td><td class="text-end"><?= e(money($i['unit_price'])) ?></td><td class="text-end"><?= e(money($i['line_total'])) ?></td></tr><?php endforeach; ?></tbody>
        <tfoot><tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end"><?= e(money($s['subtotal'])) ?></td></tr>
          <?php if ((float)$s['discount_amount'] > 0): ?><tr><td colspan="3" class="text-end">Descuento (<?= e(num($s['discount_pct'], 2)) ?> %)</td><td class="text-end">- <?= e(money($s['discount_amount'])) ?></td></tr><?php endif; ?>
          <tr class="fw-bold"><td colspan="3" class="text-end">TOTAL</td><td class="text-end"><?= e(money($s['total'])) ?></td></tr>
          <?php if ((float)$s['returned_total'] > 0): ?><tr><td colspan="3" class="text-end">Devoluciones</td><td class="text-end">- <?= e(money($s['returned_total'])) ?></td></tr><?php endif; ?></tfoot></table>
      <div class="small">Cobrado: <strong><?= e(money($s['paid_total'])) ?></strong><?= $s['status'] === 'confirmada' && $due > 0.004 ? ' · Saldo pendiente: <strong class="text-danger">' . e(money($due)) . '</strong>' : '' ?></div>
      <?php if ($s['status'] === 'anulada'): ?><div class="alert alert-danger mt-2 mb-0 py-1 small">ANULADA el <?= e(datetime_ar($s['voided_at'])) ?> por <?= e($s['voider']) ?>: <?= e($s['void_reason']) ?></div><?php endif; ?>
      <div class="text-center small text-muted mt-3"><?= e(setting('receipt_footer', '')) ?></div>
    </div>
  </div>

  <div class="col-lg-5 no-print">
    <div class="fc-card mb-3"><div class="fc-card-h">Pagos y reembolsos</div><div class="table-responsive"><table class="table table-sm mb-0 fc-table">
      <thead><tr><th>Fecha</th><th>Medio</th><th>Tipo</th><th class="text-end">Importe</th></tr></thead><tbody>
      <?php foreach ($pays as $p): ?><tr><td class="text-nowrap"><?= e(datetime_ar($p['paid_at'])) ?></td><td><?= e($p['method_name']) ?><?= $p['received_amount'] !== null ? '<div class="small text-muted">Recibido ' . e(money($p['received_amount'])) . ' · vuelto ' . e(money($p['change_amount'])) . '</div>' : '' ?></td>
        <td><?= $p['type'] === 'cobro' ? badge('Cobro', 'success') : badge('Reembolso', 'danger') ?></td><td class="text-end"><?= $p['type'] === 'reembolso' ? '- ' : '' ?><?= e(money($p['amount'])) ?></td></tr>
      <?php endforeach; if (!$pays): ?><tr><td colspan="4" class="text-center text-muted py-3">Sin pagos registrados.</td></tr><?php endif; ?></tbody></table></div></div>

    <?php if ($s['status'] === 'confirmada' && $due > 0.004 && can('sales.collect')): ?>
    <div class="fc-card mb-3"><div class="fc-card-h">Registrar cobro</div><div class="fc-card-b"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pay">
      <div class="mb-2"><label class="form-label required">Medio de pago</label><select class="form-select" name="payment_method_id" required><?= options($methods, 'id', 'name', null, 'Elegir…') ?></select></div>
      <div class="row"><div class="col-6 mb-2"><label class="form-label required">Importe</label><input class="form-control" type="number" name="amount" min="0.01" max="<?= e($due) ?>" step="0.01" value="<?= e($due) ?>" required></div>
        <div class="col-6 mb-2"><label class="form-label">Efectivo recibido</label><input class="form-control" type="number" name="received" min="0" step="0.01"></div></div>
      <div class="mb-2"><label class="form-label">Referencia</label><input class="form-control" name="reference" maxlength="80"></div>
      <button class="btn btn-primary w-100">Registrar cobro</button></form></div></div>
    <?php endif; ?>

    <?php if ($s['status'] === 'confirmada' && $canReturnSomething && can('sales.return')): ?>
    <div class="fc-card mb-3"><div class="fc-card-h">Devolución de productos</div><div class="fc-card-b"><form method="post" data-confirm="¿Registrar la devolución?"><?= csrf_field() ?><input type="hidden" name="action" value="return">
      <?php foreach ($items as $i): $max = (int)$i['quantity'] - (int)$i['returned_qty']; if ($max <= 0) continue; ?>
        <div class="row g-2 align-items-center mb-1"><div class="col-8 small"><?= e($i['name']) ?> <span class="text-muted">(máx. <?= $max ?>)</span></div>
          <div class="col-4"><input class="form-control form-control-sm" type="number" name="ret[<?= (int)$i['id'] ?>]" min="0" max="<?= $max ?>" step="1" value="0"></div></div>
      <?php endforeach; ?>
      <div class="mb-2 mt-2"><label class="form-label required">Motivo</label><input class="form-control" name="reason" maxlength="255" required></div>
      <div class="mb-2"><label class="form-label">¿El producto vuelve al stock?</label><select class="form-select" name="restock"><option value="si">Sí, está en condiciones de venta</option><option value="no">No (dañado, no se reingresa)</option></select></div>
      <div class="mb-2"><label class="form-label">Medio de reembolso (si corresponde devolver dinero)</label><select class="form-select" name="refund_method_id"><?= options($methods, 'id', 'name', null, '—') ?></select></div>
      <button class="btn btn-outline-primary w-100">Registrar devolución</button></form></div></div>
    <?php endif; ?>

    <?php if ($s['status'] === 'confirmada' && can('sales.void')): ?>
    <div class="fc-card mb-3"><div class="fc-card-h text-danger">Anular venta completa</div><div class="fc-card-b"><form method="post" data-confirm="¿Anular esta venta? Se devuelve el stock y se reembolsa lo cobrado."><?= csrf_field() ?><input type="hidden" name="action" value="void">
      <div class="mb-2"><label class="form-label required">Motivo</label><input class="form-control" name="reason" maxlength="255" required></div>
      <div class="mb-2"><label class="form-label">Medio de reembolso <?= (float)$s['paid_total'] > 0 ? '<span class="text-danger">(obligatorio: hay ' . e(money($s['paid_total'])) . ' cobrados)</span>' : '' ?></label><select class="form-select" name="refund_method_id"><?= options($methods, 'id', 'name', null, '—') ?></select></div>
      <button class="btn btn-outline-danger w-100">Anular venta</button></form></div></div>
    <?php endif; ?>

    <?php if ($rets): ?><div class="fc-card"><div class="fc-card-h">Devoluciones registradas</div><ul class="list-group list-group-flush small">
      <?php foreach ($rets as $r): ?><li class="list-group-item"><?= e(datetime_ar($r['return_date'])) ?> · <?= e(money($r['total'])) ?> · <?= $r['restock'] ? 'reingresó al stock' : 'no reingresó' ?><br><span class="text-muted"><?= e($r['reason']) ?> (<?= e($r['full_name']) ?>)</span></li><?php endforeach; ?></ul></div><?php endif; ?>
  </div>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
