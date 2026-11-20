<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('admin');

if (is_post()) {
    csrf_check();
    try {
        $pid = post_int('product_id');
        $method = post_str('method', 10) === 'margen' ? 'margen' : 'recargo';
        $pctVal = post_dec('pct');
        $p = product_by_id($pid);
        if (!$p) throw new AppException('Elegí un producto.');
        $cost = (float)$p['avg_cost'] > 0 ? (float)$p['avg_cost'] : (float)$p['last_cost'];
        $manual = parse_decimal($_POST['price'] ?? null);
        if ($manual !== null && $manual > 0) {
            $price = money_round($manual);                              // precio editado a mano por el administrador
            if ($cost > 0) {
                $pctVal = $method === 'margen' ? ($price - $cost) / $price * 100 : ($price - $cost) / $cost * 100;
            }
        } else {
            $price = calc_price($cost, $method, $pctVal);
            if ($price === null) throw new AppException('No se pudo calcular el precio: el producto no tiene costo cargado o el porcentaje no es válido (el margen debe ser menor a 100 %).');
        }
        q('UPDATE products SET sale_price = ?, markup_type = ?, markup_pct = ? WHERE id = ?', [$price, $method, round(max(0, $pctVal), 2), $pid]);
        audit('precio_actualizado', 'products', $pid, $p['name'] . ': ' . money($p['sale_price']) . ' → ' . money($price) . " ($method " . round($pctVal, 2) . ' %)');
        flash('success', 'Precio de «' . $p['name'] . '» actualizado a ' . money($price) . '.');
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/administracion/precios.php?producto=' . post_int('product_id'));
}

$products = q_all('SELECT id, code, name, avg_cost, last_cost, sale_price, markup_type, markup_pct FROM products WHERE active = 1 ORDER BY name');
$selId = get_int('producto');
$sel = $selId ? product_by_id($selId) : null;
$method = get_str('metodo', 10) === 'margen' ? 'margen' : (get_str('metodo', 10) === 'recargo' ? 'recargo' : ($sel['markup_type'] ?? setting('default_markup_type', 'recargo')));
$pctIn = isset($_GET['pct']) ? (parse_decimal($_GET['pct']) ?? 0) : ($sel ? (float)$sel['markup_pct'] : (float)setting('default_markup_pct', '40'));
if ($pctIn <= 0 && $sel) $pctIn = (float)setting('default_markup_pct', '40');
$cost = $sel ? ((float)$sel['avg_cost'] > 0 ? (float)$sel['avg_cost'] : (float)$sel['last_cost']) : 0;
$priceRecargo = calc_price($cost, 'recargo', $pctIn);
$priceMargen = calc_price($cost, 'margen', $pctIn);
$suggested = $method === 'margen' ? $priceMargen : $priceRecargo;

$pageTitle = 'Precios de venta';
$active = 'adm.precios';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Precios de venta</h1></div>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="fc-card"><div class="fc-card-h">Calcular precio sugerido</div><div class="fc-card-b">
      <form method="get" class="mb-3">
        <label class="form-label required">Producto</label>
        <select class="form-select mb-2" name="producto" onchange="this.form.submit()"><?= options(array_map(function ($p) { return ['id' => $p['id'], 'n' => $p['code'] . ' · ' . $p['name']]; }, $products), 'id', 'n', $selId, 'Elegir…') ?></select>
        <div class="row g-2">
          <div class="col-6"><label class="form-label">Método</label>
            <select class="form-select" name="metodo"><option value="recargo" <?= $method === 'recargo' ? 'selected' : '' ?>>Recargo sobre costo</option><option value="margen" <?= $method === 'margen' ? 'selected' : '' ?>>Margen sobre ventas</option></select></div>
          <div class="col-6"><label class="form-label">Porcentaje (%)</label><input class="form-control" name="pct" type="number" step="0.01" min="0" value="<?= e(round($pctIn, 2)) ?>"></div>
        </div>
        <button class="btn btn-primary mt-2 w-100"><i class="bi bi-calculator"></i> Calcular</button>
      </form>
      <?php if ($sel): ?>
        <dl class="row mb-2">
          <dt class="col-6">Costo promedio</dt><dd class="col-6 text-end"><?= e(money($sel['avg_cost'])) ?></dd>
          <dt class="col-6">Último costo de compra</dt><dd class="col-6 text-end"><?= e(money($sel['last_cost'])) ?></dd>
          <dt class="col-6">Precio de venta actual</dt><dd class="col-6 text-end"><?= e(money($sel['sale_price'])) ?></dd>
        </dl>
        <?php if ($cost <= 0): ?><div class="alert alert-warning">Este producto todavía no tiene costo. Registrá una compra primero.</div><?php else: ?>
        <div class="table-responsive"><table class="table table-sm fc-table">
          <thead><tr><th>Método</th><th>Fórmula</th><th class="text-end">Precio antes de impuestos</th></tr></thead>
          <tbody>
            <tr class="<?= $method === 'recargo' ? 'table-success' : '' ?>"><td>Recargo <?= e(num($pctIn, 2)) ?> % sobre costo</td><td class="small">costo × (1 + %)</td><td class="text-end"><?= e($priceRecargo !== null ? money($priceRecargo) : '—') ?></td></tr>
            <tr class="<?= $method === 'margen' ? 'table-success' : '' ?>"><td>Margen <?= e(num($pctIn, 2)) ?> % sobre ventas</td><td class="small">costo ÷ (1 − %)</td><td class="text-end"><?= e($priceMargen !== null ? money($priceMargen) : '—') ?></td></tr>
          </tbody></table></div>
        <form method="post" class="mt-3"><?= csrf_field() ?>
          <input type="hidden" name="product_id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="method" value="<?= e($method) ?>"><input type="hidden" name="pct" value="<?= e($pctIn) ?>">
          <label class="form-label">Precio a aplicar <span class="text-muted small">(dejalo vacío para usar el sugerido: <?= e($suggested !== null ? money($suggested) : '—') ?>)</span></label>
          <div class="input-group"><span class="input-group-text">$</span><input class="form-control" name="price" type="number" step="0.01" min="0" placeholder="Editar manualmente (opcional)"></div>
          <button class="btn btn-accent mt-2 w-100" data-confirm="¿Aplicar este precio al producto?"><i class="bi bi-check2-circle"></i> Aplicar precio</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
      <p class="fc-note mt-3 mb-0">Los precios se calculan <strong>antes de impuestos</strong>. El sistema no aplica IVA ni otros tributos automáticamente: cada producto puede tener un tratamiento fiscal distinto y debe definirlo el responsable del negocio.</p>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="fc-card"><div class="fc-card-h">Precios y rentabilidad actuales</div>
      <div class="table-responsive" style="max-height:560px"><table class="table table-sm table-hover mb-0 fc-table">
        <thead><tr><th>Producto</th><th class="text-end">Costo prom.</th><th class="text-end">Precio</th><th class="text-end">Recargo real</th><th class="text-end">Margen real</th></tr></thead><tbody>
        <?php foreach ($products as $p): $c = (float)$p['avg_cost'] > 0 ? (float)$p['avg_cost'] : (float)$p['last_cost']; $pr = (float)$p['sale_price']; ?>
          <tr><td><a href="?producto=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></td>
            <td class="text-end"><?= e(money($p['avg_cost'])) ?></td><td class="text-end"><?= e(money($pr)) ?></td>
            <td class="text-end"><?= $c > 0 && $pr > 0 ? e(pct(($pr - $c) / $c * 100)) : '—' ?></td>
            <td class="text-end"><?= $c > 0 && $pr > 0 ? e(pct(($pr - $c) / $pr * 100)) : '—' ?></td></tr>
        <?php endforeach; ?></tbody></table></div></div>
  </div>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
