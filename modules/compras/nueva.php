<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('purchases.create');

$suppliers = q_all('SELECT id, name FROM suppliers WHERE active = 1 ORDER BY name');
$products = q_all('SELECT id, code, name, unit, last_cost FROM products WHERE active = 1 ORDER BY name');
$methods = q_all('SELECT id, name FROM payment_methods WHERE active = 1 ORDER BY id');

if (is_post()) {
    csrf_check();
    try {
        $items = [];
        $pids = $_POST['item_product'] ?? []; $qtys = $_POST['item_qty'] ?? []; $costs = $_POST['item_cost'] ?? [];
        if (is_array($pids)) {
            foreach ($pids as $i => $pid) {
                $pid = (int)$pid; $qty = $qtys[$i] ?? ''; $cost = parse_decimal($costs[$i] ?? null);
                if ($pid <= 0 && trim((string)$qty) === '' && $cost === null) continue;   // línea vacía
                $q = filter_var($qty, FILTER_VALIDATE_INT);
                $items[] = ['product_id' => $pid, 'quantity' => $q === false ? 0 : $q, 'unit_cost' => $cost ?? 0];
            }
        }
        $id = purchase_create([
            'supplier_id' => post_int('supplier_id'), 'purchase_date' => post_date('purchase_date'),
            'discount' => post_dec('discount'), 'extra_costs' => post_dec('extra_costs'),
            'payment_status' => post_str('payment_status', 10), 'payment_method_id' => post_int('payment_method_id'),
            'reference' => post_str('reference', 80), 'notes' => post_str('notes', 1000),
        ], $items);
        flash('success', 'Compra registrada. El stock y el costo promedio ya fueron actualizados.');
        redirect('modules/compras/ver.php?id=' . $id);
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
}
$prodJson = json_encode(array_map(function ($p) { return ['id' => (int)$p['id'], 'label' => $p['code'] . ' · ' . $p['name'] . ' (' . $p['unit'] . ')', 'cost' => (float)$p['last_cost']]; }, $products), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
$prev = [];
if (is_post() && !empty($_POST['item_product']) && is_array($_POST['item_product'])) {
    foreach ($_POST['item_product'] as $i => $pid) {
        $prev[] = ['p' => (int)$pid, 'q' => (string)($_POST['item_qty'][$i] ?? ''), 'c' => (string)($_POST['item_cost'][$i] ?? '')];
    }
}
$inlineJs = 'var FC_PRODUCTS=' . $prodJson . ';var FC_PREV=' . json_encode($prev, JSON_HEX_TAG | JSON_HEX_AMP) . ';';
$extraJs = ['assets/js/compras.js'];

$pageTitle = 'Nueva compra';
$active = 'stk.compras';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Registrar compra de mercadería</h1><a class="btn btn-light" href="<?= e(url('modules/compras/index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a></div>
<form method="post" id="purchase-form"><?= csrf_field() ?>
  <div class="fc-card mb-3"><div class="fc-card-b"><div class="row g-3">
    <div class="col-md-4"><label class="form-label required">Proveedor</label><select class="form-select" name="supplier_id" required><?= options($suppliers, 'id', 'name', old('supplier_id'), 'Elegir…') ?></select></div>
    <div class="col-md-3"><label class="form-label required">Fecha</label><input type="date" class="form-control" name="purchase_date" value="<?= e(old('purchase_date', today())) ?>" max="<?= e(today()) ?>" required></div>
    <div class="col-md-5"><label class="form-label">Comprobante / referencia del proveedor</label><input class="form-control" name="reference" maxlength="80" value="<?= e(old('reference')) ?>"></div>
  </div></div></div>

  <div class="fc-card mb-3"><div class="fc-card-h d-flex justify-content-between align-items-center"><span>Productos</span><button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="bi bi-plus-lg"></i> Agregar línea</button></div>
    <div class="table-responsive"><table class="table mb-0 align-middle" id="lines">
      <thead><tr><th style="min-width:280px">Producto</th><th style="width:120px">Cantidad</th><th style="width:160px">Costo unitario</th><th class="text-end" style="width:150px">Subtotal</th><th style="width:50px"></th></tr></thead><tbody></tbody></table></div></div>

  <div class="row g-3">
    <div class="col-lg-6"><div class="fc-card"><div class="fc-card-b"><div class="row g-3">
      <div class="col-6"><label class="form-label">Descuento ($)</label><input class="form-control" name="discount" id="discount" type="number" min="0" step="0.01" value="<?= e(old('discount', 0)) ?>"></div>
      <div class="col-6"><label class="form-label">Otros costos atribuibles ($)</label><input class="form-control" name="extra_costs" id="extra" type="number" min="0" step="0.01" value="<?= e(old('extra_costs', 0)) ?>"></div>
      <div class="col-6"><label class="form-label">Estado de pago</label><select class="form-select" name="payment_status" id="pstatus"><option value="pagada">Pagada</option><option value="pendiente" <?= old('payment_status') === 'pendiente' ? 'selected' : '' ?>>Pendiente de pago</option></select></div>
      <div class="col-6" id="pm-wrap"><label class="form-label">Medio de pago</label><select class="form-select" name="payment_method_id"><?= options($methods, 'id', 'name', old('payment_method_id'), 'Elegir…') ?></select></div>
      <div class="col-12"><label class="form-label">Observaciones</label><textarea class="form-control" name="notes" rows="2" maxlength="1000"><?= e(old('notes')) ?></textarea></div>
    </div></div></div></div>
    <div class="col-lg-6"><div class="fc-card"><div class="fc-card-b">
      <dl class="row mb-2"><dt class="col-6">Subtotal</dt><dd class="col-6 text-end" id="t-sub">$ 0,00</dd><dt class="col-6">Descuento</dt><dd class="col-6 text-end" id="t-disc">$ 0,00</dd><dt class="col-6">Otros costos</dt><dd class="col-6 text-end" id="t-extra">$ 0,00</dd>
        <dt class="col-6 fs-5">Total</dt><dd class="col-6 text-end fs-5 fw-bold" id="t-total">$ 0,00</dd></dl>
      <p class="fc-note">Al confirmar: se guarda la compra, aumenta el stock, se actualiza el <strong>costo promedio ponderado</strong> y se registra el egreso (o la cuenta por pagar) en Administración. El descuento y los otros costos se reparten proporcionalmente entre los productos.</p>
      <button class="btn btn-primary w-100" data-confirm="¿Confirmar la compra? Se actualizará el stock.">Confirmar compra</button>
    </div></div></div>
  </div>
</form>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
