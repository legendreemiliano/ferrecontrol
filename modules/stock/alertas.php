<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('stock.view');
$list = low_stock_products();
$pageTitle = 'Stock bajo';
$active = 'stk.alertas';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Productos que necesitan reposición</h1>
  <?php if (can('purchases.create')): ?><a class="btn btn-accent" href="<?= e(url('modules/compras/nueva.php')) ?>"><i class="bi bi-truck"></i> Registrar compra</a><?php endif; ?></div>
<p class="text-muted">Se muestran los productos activos cuyo stock actual es <strong>igual o menor</strong> al stock mínimo. La lista se actualiza sola después de cada compra, venta o ajuste.</p>
<div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
  <thead><tr><th>Código</th><th>Producto</th><th class="text-end">Stock actual</th><th class="text-end">Stock mínimo</th><th class="text-end">Faltan para el mínimo</th><th>Proveedor habitual</th><th>Estado</th></tr></thead><tbody>
  <?php foreach ($list as $p): $falta = max(0, (int)$p['min_stock'] - (int)$p['stock']); ?>
    <tr><td><code><?= e($p['code']) ?></code></td><td><a href="<?= e(url('modules/stock/producto_ver.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a></td>
      <td class="text-end fw-bold text-danger"><?= num($p['stock']) ?> <small class="text-muted"><?= e($p['unit']) ?></small></td><td class="text-end"><?= num($p['min_stock']) ?></td>
      <td class="text-end"><?= num($falta) ?></td><td><?= e($p['supplier_name'] ?? '—') ?></td>
      <td><?= (int)$p['stock'] === 0 ? badge('Sin stock', 'danger') : badge('Stock bajo', 'warning') ?></td></tr>
  <?php endforeach; if (!$list): ?><tr><td colspan="7" class="text-center text-success py-4"><i class="bi bi-check-circle"></i> Todo el stock está por encima del mínimo.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
