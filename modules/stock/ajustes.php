<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('stock.adjust');

$types = [
    'ajuste_entrada'       => ['Ajuste de entrada (sobrante, error de carga)', 'entrada'],
    'ajuste_salida'        => ['Ajuste de salida (faltante, error de carga)', 'salida'],
    'rotura'               => ['Rotura o pérdida', 'salida'],
    'devolucion_proveedor' => ['Devolución al proveedor', 'salida'],
];
if (is_post()) {
    csrf_check();
    try {
        $pid = post_int('product_id'); $type = post_str('type', 30); $qty = post_int('quantity'); $reason = post_str('reason', 255);
        if (!isset($types[$type])) throw new AppException('Elegí el tipo de ajuste.');
        if (!q_one('SELECT id FROM products WHERE id = ?', [$pid])) throw new AppException('Elegí un producto.');
        if ($qty <= 0) throw new AppException('La cantidad debe ser un entero mayor que cero.');
        if ($reason === '') throw new AppException('Indicá el motivo del ajuste: queda registrado para auditoría.');
        $r = stock_apply($pid, $type, $types[$type][1], $qty, $reason);
        audit('ajuste_stock', 'products', $pid, $r['name'] . ' ' . $types[$type][1] . ' ' . $qty . ' (' . $reason . ')');
        flash('success', 'Ajuste registrado. Stock de «' . $r['name'] . '»: ' . $r['before'] . ' → ' . $r['after'] . '.');
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/stock/ajustes.php');
}
$products = q_all("SELECT id, CONCAT(code, ' · ', name, ' (stock: ', stock, ')') AS label FROM products WHERE active = 1 ORDER BY name");
$recent = q_all("SELECT m.*, p.name AS product_name, u.full_name FROM stock_movements m JOIN products p ON p.id = m.product_id JOIN users u ON u.id = m.user_id
                 WHERE m.mov_type IN ('ajuste_entrada','ajuste_salida','rotura','devolucion_proveedor') ORDER BY m.mov_datetime DESC, m.id DESC LIMIT 12");

$pageTitle = 'Ajustes de stock';
$active = 'stk.ajustes';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Ajustes de stock</h1></div>
<div class="row g-3">
  <div class="col-lg-4"><div class="fc-card"><div class="fc-card-h">Registrar ajuste</div><div class="fc-card-b">
    <form method="post"><?= csrf_field() ?>
      <div class="mb-2"><label class="form-label required">Producto</label><select class="form-select" name="product_id" required><?= options($products, 'id', 'label', old('product_id'), 'Elegir…') ?></select></div>
      <div class="mb-2"><label class="form-label required">Tipo</label><select class="form-select" name="type" required><option value="">Elegir…</option>
        <?php foreach ($types as $k => $t): ?><option value="<?= $k ?>"><?= e($t[0]) ?></option><?php endforeach; ?></select></div>
      <div class="mb-2"><label class="form-label required">Cantidad</label><input class="form-control" type="number" name="quantity" min="1" step="1" required></div>
      <div class="mb-3"><label class="form-label required">Motivo</label><input class="form-control" name="reason" maxlength="255" required placeholder="Ej: caja rota en el depósito"></div>
      <button class="btn btn-primary w-100" data-confirm="¿Confirmar el ajuste de stock?">Registrar ajuste</button>
    </form>
    <p class="fc-note mt-3 mb-0">El sistema no permite dejar el stock en negativo. Para corregir diferencias encontradas al contar, usá <a href="<?= e(url('modules/stock/inventario.php')) ?>">Inventario físico</a>.</p>
  </div></div></div>
  <div class="col-lg-8"><div class="fc-card"><div class="fc-card-h">Últimos ajustes</div><div class="table-responsive"><table class="table table-sm mb-0 fc-table">
    <thead><tr><th>Fecha</th><th>Producto</th><th>Tipo</th><th class="text-end">Cant.</th><th>Stock</th><th>Motivo</th><th>Responsable</th></tr></thead><tbody>
    <?php foreach ($recent as $m): ?><tr><td class="text-nowrap"><?= e(datetime_ar($m['mov_datetime'])) ?></td><td><?= e($m['product_name']) ?></td><td><?= e(stock_type_label($m['mov_type'])) ?></td>
      <td class="text-end <?= $m['direction'] === 'entrada' ? 'text-success' : 'text-danger' ?>"><?= $m['direction'] === 'entrada' ? '+' : '−' ?><?= num($m['quantity']) ?></td>
      <td><?= num($m['stock_before']) ?> → <?= num($m['stock_after']) ?></td><td class="small"><?= e($m['reason']) ?></td><td class="small"><?= e($m['full_name']) ?></td></tr>
    <?php endforeach; if (!$recent): ?><tr><td colspan="7" class="text-center text-muted py-3">Todavía no hay ajustes.</td></tr><?php endif; ?></tbody></table></div></div></div>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
