<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('stock.view');

/** Control de stock día por día: Entrada, Salida y Existencia al cierre de cada día (más reciente arriba). */
$products = q_all("SELECT id, CONCAT(code, ' · ', name) AS label FROM products ORDER BY name");
$prodId = get_int('producto');
$from = get_date('desde', days_ago(-30));
$to = get_date('hasta', today());
$prod = $prodId ? product_by_id($prodId) : null;
$rows = [];
if ($prod) {
    $rows = q_all("SELECT d.dia, d.entradas, d.salidas,
            (SELECT sm2.stock_after FROM stock_movements sm2 WHERE sm2.product_id = :p1 AND DATE(sm2.mov_datetime) = d.dia
             ORDER BY sm2.mov_datetime DESC, sm2.id DESC LIMIT 1) AS existencia
        FROM (SELECT DATE(mov_datetime) AS dia,
                     SUM(CASE WHEN direction = 'entrada' THEN quantity ELSE 0 END) AS entradas,
                     SUM(CASE WHEN direction = 'salida' THEN quantity ELSE 0 END) AS salidas
              FROM stock_movements WHERE product_id = :p2 AND DATE(mov_datetime) BETWEEN :f AND :t
              GROUP BY DATE(mov_datetime)) d
        ORDER BY d.dia DESC", [':p1' => $prodId, ':p2' => $prodId, ':f' => $from, ':t' => $to]);
}
$pageTitle = 'Control diario de stock';
$active = 'stk.diario';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Control diario de stock</h1></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-12 col-md-5"><label class="form-label small mb-0">Producto</label><select class="form-select form-select-sm" name="producto"><?= options($products, 'id', 'label', $prodId, 'Elegir un producto…') ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Ver</button></div>
</form>
<?php if (!$prod): ?>
  <div class="fc-note">Elegí un producto para ver, día por día, cuánto <strong>entró</strong>, cuánto <strong>salió</strong> y cuál fue la <strong>existencia</strong> al cierre de cada día.</div>
<?php else: ?>
  <div class="fc-card"><div class="fc-card-h"><?= e($prod['name']) ?> <span class="text-muted small">· existencia actual: <?= num($prod['stock']) ?> <?= e($prod['unit']) ?></span></div>
  <div class="table-responsive"><table class="table table-sm table-striped mb-0 fc-table">
    <thead><tr><th>Día</th><th>Fecha</th><th class="text-end">Entrada</th><th class="text-end">Salida</th><th class="text-end">Existencia</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e(weekday_es($r['dia'])) ?></td><td><?= e(date_ar($r['dia'])) ?></td>
        <td class="text-end text-success"><?= (int)$r['entradas'] > 0 ? num($r['entradas']) : '—' ?></td>
        <td class="text-end text-danger"><?= (int)$r['salidas'] > 0 ? num($r['salidas']) : '—' ?></td>
        <td class="text-end fw-bold"><?= num($r['existencia']) ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">Este producto no tuvo movimientos en el período.</td></tr><?php endif; ?>
  </tbody></table></div></div>
  <p class="small text-muted mt-2">Más reciente arriba. La existencia es la que quedó al cierre del día, después del último movimiento.</p>
<?php endif; ?>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
