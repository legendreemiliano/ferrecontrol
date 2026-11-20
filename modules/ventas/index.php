<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('sales.view');

[$from, $to] = date_range_params();
$st = get_str('estado', 10); $qtext = get_str('q', 60); $seller = get_int('vendedor');
$where = ['DATE(s.sale_date) BETWEEN :f AND :t']; $p = [':f' => $from, ':t' => $to];
if (in_array($st, ['pagada', 'parcial', 'pendiente'], true)) { $where[] = "s.payment_status = :ps AND s.status = 'confirmada'"; $p[':ps'] = $st; }
if ($st === 'anulada') $where[] = "s.status = 'anulada'";
if ($seller > 0) { $where[] = 's.user_id = :u'; $p[':u'] = $seller; }
if ($qtext !== '') { $where[] = '(s.number LIKE :q1 OR s.customer_name LIKE :q2)'; $p[':q1'] = $p[':q2'] = "%$qtext%"; }
[$sk, $sd, $order] = sort_params(['date' => 's.sale_date', 'number' => 's.number', 'total' => 's.total', 'customer' => 's.customer_name'], 'date', 'desc');
$pg = paginate('s.*, u.full_name', 'sales s JOIN users u ON u.id = s.user_id WHERE ' . implode(' AND ', $where), $p, "$order, s.id DESC");
$sellers = q_all('SELECT id, full_name FROM users ORDER BY full_name');

$pageTitle = 'Ventas';
$active = 'stk.ventas';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Ventas</h1>
  <?php if (can('sales.create')): ?><a class="btn btn-accent" href="<?= e(url('modules/ventas/pos.php')) ?>"><i class="bi bi-cart-plus"></i> Nueva venta</a><?php endif; ?></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Estado</label><select class="form-select form-select-sm" name="estado"><option value="">Todos</option>
    <?php foreach (['pagada' => 'Pagada', 'parcial' => 'Cobro parcial', 'pendiente' => 'Pendiente de cobro', 'anulada' => 'Anulada'] as $k => $l): ?><option value="<?= $k ?>" <?= $st === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Vendedor</label><select class="form-select form-select-sm" name="vendedor"><?= options($sellers, 'id', 'full_name', $seller, 'Todos') ?></select></div>
  <div class="col-8 col-md-2"><label class="form-label small mb-0">N.º / cliente</label><input class="form-control form-control-sm" name="q" value="<?= e($qtext) ?>"></div>
  <div class="col-4 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button></div>
</form>
<div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
  <thead><tr><?= sort_th('number', 'N.º', $sk, $sd) ?><?= sort_th('date', 'Fecha', $sk, $sd) ?><?= sort_th('customer', 'Cliente', $sk, $sd) ?><th>Vendedor</th><?= sort_th('total', 'Total', $sk, $sd, 'text-end') ?><th class="text-end">Cobrado</th><th class="text-end">Saldo</th><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $s): $saldo = (float)$s['total'] - (float)$s['returned_total'] - (float)$s['paid_total']; ?>
    <tr class="<?= $s['status'] === 'anulada' ? 'text-decoration-line-through text-muted' : '' ?>"><td><a href="<?= e(url('modules/ventas/ver.php?id=' . $s['id'])) ?>"><?= e($s['number']) ?></a></td><td class="text-nowrap"><?= e(datetime_ar($s['sale_date'])) ?></td>
      <td><?= e($s['customer_name'] ?: 'Consumidor final') ?></td><td class="small"><?= e($s['full_name']) ?></td><td class="text-end"><?= e(money($s['total'])) ?></td><td class="text-end"><?= e(money($s['paid_total'])) ?></td>
      <td class="text-end <?= $s['status'] === 'confirmada' && $saldo > 0.004 ? 'text-danger fw-semibold' : '' ?>"><?= $s['status'] === 'confirmada' ? e(money(max(0, $saldo))) : '—' ?></td>
      <td><?= $s['status'] === 'anulada' ? status_badge('anulada') : status_badge($s['payment_status']) ?><?= (float)$s['returned_total'] > 0 ? ' ' . badge('Con devolución', 'info') : '' ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('modules/ventas/ver.php?id=' . $s['id'])) ?>"><i class="bi bi-eye"></i></a></td></tr>
  <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="9" class="text-center text-muted py-4">No hay ventas para los filtros elegidos.</td></tr><?php endif; ?></tbody></table></div></div>
<?= render_pagination($pg) ?>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
