<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once ROOT_DIR . '/reports/admin.php';
require_perm('admin');

[$from, $to] = date_range_params();
$s = period_summary($from, $to);
$rep = admin_report('ganancias', $from, $to);
$marginPct = $s['net_sales'] > 0 ? $s['gross_profit'] / $s['net_sales'] * 100 : 0;

$pageTitle = 'Ganancias';
$active = 'adm.ganancias';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Ganancias estimadas</h1>
  <button class="btn btn-outline-secondary no-print" data-print><i class="bi bi-printer"></i> Imprimir</button></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-6 col-md-3"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-3"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Ver</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="fc-stat"><div class="lbl">Ventas netas</div><div class="val"><?= e(money($s['net_sales'])) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="fc-stat"><div class="lbl">Costo de mercadería vendida</div><div class="val"><?= e(money($s['cogs'])) ?></div></div></div>
  <div class="col-6 col-lg-3"><div class="fc-stat accent"><div class="lbl">Ganancia bruta</div><div class="val"><?= e(money($s['gross_profit'])) ?></div><div class="sub"><?= e(pct($marginPct)) ?> de las ventas netas</div></div></div>
  <div class="col-6 col-lg-3"><div class="fc-stat"><div class="lbl">Sueldos pagados</div><div class="val"><?= e(money($s['payroll'])) ?></div><div class="sub">Se cuenta cuando la liquidación se marca como pagada</div></div></div>
  <div class="col-6 col-lg-3"><div class="fc-stat <?= $s['operating_result'] < 0 ? 'danger' : '' ?>"><div class="lbl">Resultado operativo estimado</div><div class="val"><?= e(money($s['operating_result'])) ?></div></div></div>
</div>
<div class="fc-card"><div class="fc-card-h"><?= e($rep['title']) ?></div><div class="fc-card-b"><?= render_report($rep) ?>
  <p class="fc-note mb-0"><?= e($rep['note']) ?></p></div></div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
