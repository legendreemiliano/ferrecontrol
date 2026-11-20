<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('sales.create');

$methods = q_all('SELECT id, name, is_cash FROM payment_methods WHERE active = 1 ORDER BY id');

if (is_post()) {
    csrf_check();
    try {
        $cart = json_decode((string)($_POST['cart_json'] ?? '[]'), true);
        if (!is_array($cart) || !$cart) throw new AppException('El carrito está vacío: agregá al menos un producto.');
        $items = [];
        foreach ($cart as $line) {
            if (!is_array($line)) continue;
            $items[] = ['product_id' => (int)($line['p'] ?? 0), 'quantity' => (int)($line['q'] ?? 0)];   // el precio NUNCA viene del navegador
        }
        $received = parse_decimal($_POST['received'] ?? null);
        $payAmount = parse_decimal($_POST['pay_amount'] ?? null);
        $id = sale_create([
            'customer_name' => post_str('customer_name', 120), 'discount_pct' => post_dec('discount_pct'),
            'payment_method_id' => post_int('payment_method_id'), 'pay_amount' => $payAmount, 'received' => $received,
            'reference' => post_str('reference', 80),
        ], $items);
        flash('success', 'Venta registrada correctamente.');
        redirect('modules/ventas/ver.php?id=' . $id . '&nueva=1');
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
}
$prevCart = [];
if (is_post()) {
    $pc = json_decode((string)($_POST['cart_json'] ?? '[]'), true);
    if (is_array($pc)) {
        foreach ($pc as $line) {
            if (!is_array($line)) continue;
            $r = q_one('SELECT id, code, name, sale_price, stock FROM products WHERE id = ? AND active = 1', [(int)($line['p'] ?? 0)]);
            if ($r) $prevCart[] = ['id' => (int)$r['id'], 'code' => $r['code'], 'name' => $r['name'], 'price' => (float)$r['sale_price'], 'stock' => (int)$r['stock'], 'qty' => max(1, (int)($line['q'] ?? 1))];
        }
    }
}
$extraJs = ['assets/js/pos.js'];
$inlineJs = 'var FC_PREV_CART=' . json_encode($prevCart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';var FC_SEARCH_URL=' . json_encode(url('modules/ventas/buscar_producto.php')) . ';var FC_MAX_DISC=' . json_encode(max_discount_pct()) . ';';

$pageTitle = 'Nueva venta';
$active = 'stk.pos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Punto de venta</h1></div>
<form method="post" id="pos-form" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="cart_json" id="cart_json">
<div class="row g-3">
  <div class="col-lg-8">
    <div class="fc-card mb-3"><div class="fc-card-b position-relative">
      <label class="form-label">Buscar producto (nombre, código o código de barras)</label>
      <input class="form-control form-control-lg" id="pos-search" placeholder="Escribí para buscar y presioná Enter para agregar…" autofocus>
      <div class="list-group shadow" id="pos-results" style="display:none"></div>
    </div></div>
    <div class="fc-card"><div class="table-responsive"><table class="table mb-0 align-middle">
      <thead><tr><th>Producto</th><th class="text-end" style="width:130px">Precio</th><th style="width:120px">Cantidad</th><th class="text-end" style="width:140px">Subtotal</th><th style="width:50px"></th></tr></thead>
      <tbody id="pos-cart"><tr class="empty-row"><td colspan="5" class="text-center text-muted py-4">El carrito está vacío.</td></tr></tbody></table></div></div>
  </div>
  <div class="col-lg-4">
    <div class="fc-card"><div class="fc-card-b">
      <div class="mb-2"><label class="form-label">Cliente (opcional)</label><input class="form-control" name="customer_name" maxlength="120" value="<?= e(old('customer_name')) ?>"></div>
      <div class="mb-3"><label class="form-label">Descuento (%) <small class="text-muted">máx. <?= e(num(max_discount_pct(), 2)) ?> %</small></label><input class="form-control" type="number" name="discount_pct" id="pos-discount" min="0" max="<?= e(max_discount_pct()) ?>" step="0.01" value="<?= e(old('discount_pct', 0)) ?>"></div>
      <dl class="row mb-1"><dt class="col-6">Subtotal</dt><dd class="col-6 text-end" id="pos-subtotal">$ 0,00</dd><dt class="col-6">Descuento</dt><dd class="col-6 text-end" id="pos-disc-amount">- $ 0,00</dd></dl>
      <div class="d-flex justify-content-between align-items-center border-top pt-2 mb-3"><span class="fs-5">TOTAL</span><span class="pos-total" id="pos-total">$ 0,00</span></div>
      <div class="mb-2"><label class="form-label">Medio de pago</label>
        <select class="form-select" name="payment_method_id" id="pos-method"><option value="0" data-cash="0">Pendiente de cobro (a cuenta)</option>
          <?php foreach ($methods as $m): ?><option value="<?= (int)$m['id'] ?>" data-cash="<?= (int)$m['is_cash'] ?>" <?= (int)old('payment_method_id', (int)($methods[0]['id'] ?? 0)) === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-2" id="pos-amount-wrap"><label class="form-label">Importe a cobrar ahora <small class="text-muted">(vacío = total)</small></label><input class="form-control" type="number" name="pay_amount" id="pos-pay-amount" min="0" step="0.01"></div>
      <div class="mb-2" id="pos-received-wrap"><label class="form-label">Efectivo recibido</label><input class="form-control" type="number" name="received" id="pos-received" min="0" step="0.01">
        <div class="mt-1">Vuelto: <strong id="pos-change">$ 0,00</strong></div></div>
      <div class="mb-3"><label class="form-label">Referencia (opcional)</label><input class="form-control" name="reference" maxlength="80"></div>
      <button class="btn btn-accent btn-lg w-100" id="pos-confirm"><i class="bi bi-check2-circle"></i> Confirmar venta</button>
      <p class="small text-muted mt-2 mb-0">Los precios y el total se calculan en el servidor al confirmar. Si no alcanza el stock, la venta se rechaza completa.</p>
    </div></div>
  </div>
</div>
</form>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
