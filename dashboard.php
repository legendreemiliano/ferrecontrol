<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_perm('dashboard');

$pageTitle = 'Inicio';
$active = 'dashboard';
$u = current_user();
$today = today();

include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head">
  <h1>Hola, <?= e(explode(' ', $u['full_name'])[0]) ?></h1>
  <span class="text-muted"><?= e(role_label($u['role'])) ?></span>
</div>

<div class="row g-3 mb-4">
<?php if (can('sales.view')):
    $sd = q_one("SELECT COUNT(*) AS c, COALESCE(SUM(total - returned_total),0) AS t FROM sales WHERE status = 'confirmada' AND DATE(sale_date) = ?", [$today]); ?>
  <div class="col-6 col-lg-3"><div class="fc-stat"><div class="lbl">Ventas de hoy</div><div class="val"><?= (int)$sd['c'] ?></div><div class="sub"><?= e(money($sd['t'])) ?></div></div></div>
<?php endif; ?>
<?php if (can('stock.view')):
    $low = low_stock_products(); ?>
  <div class="col-6 col-lg-3"><div class="fc-stat <?= $low ? 'danger' : '' ?>"><div class="lbl">Productos con stock bajo</div><div class="val"><?= count($low) ?></div><div class="sub"><a href="<?= e(url('modules/stock/alertas.php')) ?>">Ver reposición</a></div></div></div>
<?php endif; ?>
<?php if (can('admin')):
    $m = period_summary(date('Y-m-01'), date('Y-m-t')); ?>
  <div class="col-6 col-lg-3"><div class="fc-stat accent"><div class="lbl">Ingresos del mes</div><div class="val"><?= e(money($m['income_collected'])) ?></div><div class="sub">Cobrado</div></div></div>
  <div class="col-6 col-lg-3"><div class="fc-stat"><div class="lbl">Resultado operativo (mes)</div><div class="val"><?= e(money($m['operating_result'])) ?></div><div class="sub">Estimación de gestión</div></div></div>
<?php endif; ?>
<?php if (can('employees.view')):
    $ea = (int)q_val("SELECT COUNT(*) FROM employees WHERE status <> 'baja'");
    $pres = (int)q_val("SELECT COUNT(*) FROM attendances WHERE att_date = ? AND status IN ('presente','tarde')", [$today]); ?>
  <div class="col-6 col-lg-3"><div class="fc-stat"><div class="lbl">Empleados activos</div><div class="val"><?= $ea ?></div><div class="sub"><?= $pres ?> presentes hoy</div></div></div>
<?php endif; ?>
<?php if (can('leaves.manage')):
    $lv = (int)q_val("SELECT COUNT(*) FROM leaves WHERE status = 'pendiente'"); ?>
  <div class="col-6 col-lg-3"><div class="fc-stat accent"><div class="lbl">Licencias por aprobar</div><div class="val"><?= $lv ?></div><div class="sub"><a href="<?= e(url('modules/personal/licencias/index.php')) ?>">Ver licencias</a></div></div></div>
<?php endif; ?>
</div>

<div class="row g-3">
<?php if (can('stock.view') && !empty($low)): ?>
  <div class="col-lg-6">
    <div class="fc-card"><div class="fc-card-h"><i class="bi bi-exclamation-triangle text-warning"></i> Necesitan reposición</div>
      <div class="table-responsive"><table class="table table-sm mb-0 fc-table">
        <thead><tr><th>Producto</th><th class="text-end">Stock</th><th class="text-end">Mínimo</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($low, 0, 8) as $p): ?>
          <tr><td><?= e($p['name']) ?></td><td class="text-end text-danger fw-semibold"><?= num($p['stock']) ?></td><td class="text-end"><?= num($p['min_stock']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
  </div>
<?php endif; ?>
<?php if (can('sales.view')):
    $recent = q_all("SELECT s.id, s.number, s.sale_date, s.total, s.status, s.payment_status FROM sales s ORDER BY s.sale_date DESC, s.id DESC LIMIT 6"); ?>
  <div class="col-lg-6">
    <div class="fc-card"><div class="fc-card-h"><i class="bi bi-receipt"></i> Últimas ventas</div>
      <div class="table-responsive"><table class="table table-sm mb-0 fc-table">
        <thead><tr><th>N.º</th><th>Fecha</th><th class="text-end">Total</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $s): ?>
          <tr><td><a href="<?= e(url('modules/ventas/ver.php?id=' . $s['id'])) ?>"><?= e($s['number']) ?></a></td>
              <td><?= e(datetime_ar($s['sale_date'])) ?></td><td class="text-end"><?= e(money($s['total'])) ?></td>
              <td><?= $s['status'] === 'anulada' ? status_badge('anulada') : status_badge($s['payment_status']) ?></td></tr>
        <?php endforeach; if (!$recent): ?><tr><td colspan="4" class="text-center text-muted py-3">Todavía no hay ventas.</td></tr><?php endif; ?>
        </tbody></table></div></div>
  </div>
<?php endif; ?>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
