<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('products.view');
$id = get_int('id');
$p = q_one('SELECT p.*, c.name AS category_name, s.name AS supplier_name FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?', [$id]);
if (!$p) { flash('error', 'El producto no existe.'); redirect('modules/stock/index.php'); }

$moves = q_all('SELECT m.*, u.full_name FROM stock_movements m JOIN users u ON u.id = m.user_id WHERE m.product_id = ? ORDER BY m.mov_datetime DESC, m.id DESC LIMIT 15', [$id]);
$buys = q_all("SELECT pi.quantity, pi.unit_cost, pi.effective_unit_cost, pu.number, pu.id AS purchase_id, pu.purchase_date, su.name AS supplier_name, pu.status
               FROM purchase_items pi JOIN purchases pu ON pu.id = pi.purchase_id JOIN suppliers su ON su.id = pu.supplier_id
               WHERE pi.product_id = ? ORDER BY pu.purchase_date DESC, pu.id DESC LIMIT 10", [$id]);
$sold = q_one("SELECT COALESCE(SUM(si.quantity - si.returned_qty),0) AS qty FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = ? AND s.status = 'confirmada' AND DATE(s.sale_date) >= ?", [$id, days_ago(-30)]);
$low = (int)$p['stock'] <= (int)$p['min_stock'];

$pageTitle = $p['name'];
$active = 'stk.productos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1><?= e($p['name']) ?> <small class="text-muted fs-6"><?= e($p['code']) ?></small></h1>
  <div><a class="btn btn-light" href="<?= e(url('modules/stock/index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a>
  <?php if (can('products.edit')): ?><a class="btn btn-primary" href="<?= e(url('modules/stock/producto_form.php?id=' . $p['id'])) ?>"><i class="bi bi-pencil"></i> Editar</a><?php endif; ?></div></div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="fc-stat <?= $low ? 'danger' : '' ?>"><div class="lbl">Stock actual</div><div class="val"><?= num($p['stock']) ?> <small class="fs-6"><?= e($p['unit']) ?></small></div><div class="sub">Mínimo: <?= num($p['min_stock']) ?> <?= $low ? '· STOCK BAJO' : '' ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="fc-stat"><div class="lbl">Costo promedio</div><div class="val"><?= e(money($p['avg_cost'])) ?></div><div class="sub">Último costo: <?= e(money($p['last_cost'])) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="fc-stat accent"><div class="lbl">Precio de venta</div><div class="val"><?= e(money($p['sale_price'])) ?></div><div class="sub">Antes de impuestos</div></div></div>
  <div class="col-6 col-lg-3"><div class="fc-stat"><div class="lbl">Vendidas (30 días)</div><div class="val"><?= num($sold['qty']) ?></div><div class="sub">Valor en stock: <?= e(money($p['stock'] * $p['avg_cost'])) ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="fc-card"><div class="fc-card-h">Ficha</div><div class="fc-card-b"><dl class="row mb-0 small">
      <dt class="col-5">Categoría</dt><dd class="col-7"><?= e($p['category_name']) ?></dd>
      <dt class="col-5">Marca</dt><dd class="col-7"><?= e($p['brand'] ?: '—') ?></dd>
      <dt class="col-5">Código de barras</dt><dd class="col-7"><?= e($p['barcode'] ?: '—') ?></dd>
      <dt class="col-5">Ubicación</dt><dd class="col-7"><?= e($p['location'] ?: '—') ?></dd>
      <dt class="col-5">Proveedor</dt><dd class="col-7"><?= e($p['supplier_name'] ?: '—') ?></dd>
      <dt class="col-5">Estado</dt><dd class="col-7"><?= yesno_badge((bool)$p['active']) ?></dd>
      <dt class="col-5">Creado</dt><dd class="col-7"><?= e(datetime_ar($p['created_at'])) ?></dd>
      <dt class="col-5">Actualizado</dt><dd class="col-7"><?= e(datetime_ar($p['updated_at'])) ?></dd>
    </dl><?php if ($p['description']): ?><hr><p class="small mb-0"><?= nl2br(e($p['description'])) ?></p><?php endif; ?></div></div>
    <div class="fc-card mt-3"><div class="fc-card-h">Últimas compras</div><div class="table-responsive"><table class="table table-sm mb-0 fc-table">
      <thead><tr><th>Compra</th><th>Fecha</th><th class="text-end">Cant.</th><th class="text-end">Costo</th></tr></thead><tbody>
      <?php foreach ($buys as $b): ?><tr class="<?= $b['status'] === 'anulada' ? 'text-decoration-line-through text-muted' : '' ?>"><td><a href="<?= e(url('modules/compras/ver.php?id=' . $b['purchase_id'])) ?>"><?= e($b['number']) ?></a></td><td><?= e(date_ar($b['purchase_date'])) ?></td><td class="text-end"><?= num($b['quantity']) ?></td><td class="text-end"><?= e(money($b['unit_cost'])) ?></td></tr>
      <?php endforeach; if (!$buys): ?><tr><td colspan="4" class="text-center text-muted py-3">Sin compras registradas.</td></tr><?php endif; ?></tbody></table></div></div>
  </div>
  <div class="col-lg-8">
    <div class="fc-card"><div class="fc-card-h d-flex justify-content-between"><span>Últimos movimientos de stock</span>
      <a class="small" href="<?= e(url('modules/stock/movimientos.php?producto=' . $p['id'])) ?>">Ver todos</a></div>
      <div class="table-responsive"><table class="table table-sm mb-0 fc-table">
      <thead><tr><th>Fecha y hora</th><th>Tipo</th><th class="text-end">Entrada</th><th class="text-end">Salida</th><th class="text-end">Antes</th><th class="text-end">Después</th><th>Responsable</th></tr></thead><tbody>
      <?php foreach ($moves as $m): ?><tr><td class="text-nowrap"><?= e(datetime_ar($m['mov_datetime'])) ?></td><td><?= e(stock_type_label($m['mov_type'])) ?><div class="small text-muted"><?= e($m['reason']) ?></div></td>
        <td class="text-end text-success"><?= $m['direction'] === 'entrada' ? num($m['quantity']) : '' ?></td><td class="text-end text-danger"><?= $m['direction'] === 'salida' ? num($m['quantity']) : '' ?></td>
        <td class="text-end"><?= num($m['stock_before']) ?></td><td class="text-end fw-semibold"><?= num($m['stock_after']) ?></td><td class="small"><?= e($m['full_name']) ?></td></tr>
      <?php endforeach; if (!$moves): ?><tr><td colspan="7" class="text-center text-muted py-3">Sin movimientos.</td></tr><?php endif; ?></tbody></table></div></div>
  </div>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
