<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('purchases.view');

[$from, $to] = date_range_params();
$sup = get_int('proveedor'); $st = get_str('estado', 10); $qtext = get_str('q', 40);
$where = ['p.purchase_date BETWEEN :f AND :t']; $p = [':f' => $from, ':t' => $to];
if ($sup > 0) { $where[] = 'p.supplier_id = :s'; $p[':s'] = $sup; }
if (in_array($st, ['pagada', 'pendiente'], true)) { $where[] = 'p.payment_status = :ps'; $p[':ps'] = $st; }
if ($st === 'anulada') { $where[] = "p.status = 'anulada'"; }
if ($qtext !== '') { $where[] = '(p.number LIKE :q1 OR p.reference LIKE :q2)'; $p[':q1'] = $p[':q2'] = "%$qtext%"; }
[$sk, $sd, $order] = sort_params(['date' => 'p.purchase_date', 'number' => 'p.number', 'total' => 'p.total', 'supplier' => 's.name'], 'date', 'desc');
$pg = paginate('p.*, s.name AS supplier_name, u.full_name', 'purchases p JOIN suppliers s ON s.id = p.supplier_id JOIN users u ON u.id = p.user_id WHERE ' . implode(' AND ', $where), $p, "$order, p.id DESC");
$suppliers = q_all('SELECT id, name FROM suppliers ORDER BY name');

$pageTitle = 'Compras';
$active = 'stk.compras';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Compras de mercadería</h1>
  <?php if (can('purchases.create')): ?><a class="btn btn-accent" href="<?= e(url('modules/compras/nueva.php')) ?>"><i class="bi bi-plus-lg"></i> Nueva compra</a><?php endif; ?></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-6 col-md-3"><label class="form-label small mb-0">Proveedor</label><select class="form-select form-select-sm" name="proveedor"><?= options($suppliers, 'id', 'name', $sup, 'Todos') ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Estado</label><select class="form-select form-select-sm" name="estado"><option value="">Todos</option>
    <?php foreach (['pagada' => 'Pagada', 'pendiente' => 'Pendiente de pago', 'anulada' => 'Anulada'] as $k => $l): ?><option value="<?= $k ?>" <?= $st === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-8 col-md-2"><label class="form-label small mb-0">N.º / referencia</label><input class="form-control form-control-sm" name="q" value="<?= e($qtext) ?>"></div>
  <div class="col-4 col-md-1 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
</form>
<div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
  <thead><tr><?= sort_th('number', 'N.º', $sk, $sd) ?><?= sort_th('date', 'Fecha', $sk, $sd) ?><?= sort_th('supplier', 'Proveedor', $sk, $sd) ?><th>Referencia</th><th>Registró</th><?= sort_th('total', 'Total', $sk, $sd, 'text-end') ?><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $r): ?>
    <tr class="<?= $r['status'] === 'anulada' ? 'text-decoration-line-through text-muted' : '' ?>"><td><a href="<?= e(url('modules/compras/ver.php?id=' . $r['id'])) ?>"><?= e($r['number']) ?></a></td><td><?= e(date_ar($r['purchase_date'])) ?></td>
      <td><?= e($r['supplier_name']) ?></td><td class="small"><?= e($r['reference']) ?></td><td class="small"><?= e($r['full_name']) ?></td><td class="text-end"><?= e(money($r['total'])) ?></td>
      <td><?= $r['status'] === 'anulada' ? status_badge('anulada') : status_badge($r['payment_status']) ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('modules/compras/ver.php?id=' . $r['id'])) ?>"><i class="bi bi-eye"></i></a></td></tr>
  <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="8" class="text-center text-muted py-4">No hay compras para los filtros elegidos.</td></tr><?php endif; ?></tbody></table></div></div>
<?= render_pagination($pg) ?>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
