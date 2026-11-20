<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('stock.view');

[$from, $to] = date_range_params();
$prod = get_int('producto'); $type = get_str('tipo', 30); $dir = get_str('direccion', 10);
$where = ['DATE(m.mov_datetime) BETWEEN :f AND :t']; $p = [':f' => $from, ':t' => $to];
if ($prod > 0) { $where[] = 'm.product_id = :p'; $p[':p'] = $prod; }
if ($type !== '') { $where[] = 'm.mov_type = :ty'; $p[':ty'] = $type; }
if (in_array($dir, ['entrada', 'salida'], true)) { $where[] = 'm.direction = :d'; $p[':d'] = $dir; }
// siempre del más reciente al más antiguo
$pg = paginate('m.*, p.code, p.name AS product_name, u.full_name, pu.number AS purchase_number, s.number AS sale_number',
    'stock_movements m JOIN products p ON p.id = m.product_id JOIN users u ON u.id = m.user_id LEFT JOIN purchases pu ON pu.id = m.purchase_id LEFT JOIN sales s ON s.id = m.sale_id WHERE ' . implode(' AND ', $where),
    $p, 'm.mov_datetime DESC, m.id DESC', 20);
$products = q_all("SELECT id, CONCAT(code, ' · ', name) AS label FROM products ORDER BY name");
$types = ['compra', 'venta', 'devolucion_cliente', 'devolucion_proveedor', 'ajuste_entrada', 'ajuste_salida', 'rotura', 'anulacion_compra', 'anulacion_venta', 'inventario_fisico'];

$pageTitle = 'Movimientos de stock';
$active = 'stk.movimientos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Historial de movimientos de stock</h1></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-12 col-md-3"><label class="form-label small mb-0">Producto</label><select class="form-select form-select-sm" name="producto"><?= options($products, 'id', 'label', $prod, 'Todos') ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Tipo</label><select class="form-select form-select-sm" name="tipo"><option value="">Todos</option>
    <?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(stock_type_label($t)) ?></option><?php endforeach; ?></select></div>
  <div class="col-3 col-md-1"><label class="form-label small mb-0">Sentido</label><select class="form-select form-select-sm" name="direccion"><option value="">Ambos</option><option value="entrada" <?= $dir === 'entrada' ? 'selected' : '' ?>>Entrada</option><option value="salida" <?= $dir === 'salida' ? 'selected' : '' ?>>Salida</option></select></div>
  <div class="col-3 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button></div>
</form>
<p class="small text-muted">Se muestran primero los movimientos más recientes.</p>
<div class="fc-card"><div class="table-responsive"><table class="table table-sm table-hover mb-0 fc-table">
  <thead><tr><th>Fecha y hora</th><th>Producto</th><th>Tipo</th><th class="text-end">Entrada</th><th class="text-end">Salida</th><th class="text-end">Stock anterior</th><th class="text-end">Stock resultante</th><th>Motivo / origen</th><th>Responsable</th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $m): ?>
    <tr><td class="text-nowrap"><?= e(datetime_ar($m['mov_datetime'])) ?></td>
      <td><a href="<?= e(url('modules/stock/producto_ver.php?id=' . $m['product_id'])) ?>"><?= e($m['product_name']) ?></a><div class="small text-muted"><?= e($m['code']) ?></div></td>
      <td><?= e(stock_type_label($m['mov_type'])) ?></td>
      <td class="text-end text-success"><?= $m['direction'] === 'entrada' ? num($m['quantity']) : '' ?></td><td class="text-end text-danger"><?= $m['direction'] === 'salida' ? num($m['quantity']) : '' ?></td>
      <td class="text-end"><?= num($m['stock_before']) ?></td><td class="text-end fw-semibold"><?= num($m['stock_after']) ?></td>
      <td class="small"><?= e($m['reason']) ?>
        <?php if ($m['purchase_id']): ?> <a href="<?= e(url('modules/compras/ver.php?id=' . $m['purchase_id'])) ?>"><?= e($m['purchase_number']) ?></a><?php endif; ?>
        <?php if ($m['sale_id'] && can('sales.view')): ?> <a href="<?= e(url('modules/ventas/ver.php?id=' . $m['sale_id'])) ?>"><?= e($m['sale_number']) ?></a><?php endif; ?></td>
      <td class="small"><?= e($m['full_name']) ?></td></tr>
  <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="9" class="text-center text-muted py-4">No hay movimientos para los filtros elegidos.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?= render_pagination($pg) ?>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
