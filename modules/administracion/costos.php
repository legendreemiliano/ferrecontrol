<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once ROOT_DIR . '/reports/admin.php';
require_perm('admin');

[$from, $to] = date_range_params();
$s = period_summary($from, $to);
$rep = admin_report('costos', $from, $to);

$pageTitle = 'Costos del período';
$active = 'adm.costos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Costos del período</h1></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-6 col-md-3"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-3"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-calculator"></i> Calcular</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="fc-stat"><div class="lbl">Costos fijos</div><div class="val"><?= e(money($s['cost_fixed'])) ?></div><div class="sub">Alquiler, sueldos, servicios fijos…</div></div></div>
  <div class="col-6 col-lg-3"><div class="fc-stat accent"><div class="lbl">Costos variables</div><div class="val"><?= e(money($s['cost_variable'])) ?></div><div class="sub">Gastos variables + mercadería vendida + comisiones</div></div></div>
  <div class="col-12 col-lg-3"><div class="fc-stat danger"><div class="lbl">Costo total del período</div><div class="val"><?= e(money($s['cost_total'])) ?></div><div class="sub">Fijos + variables</div></div></div>
  <div class="col-12 col-lg-3"><div class="fc-stat"><div class="lbl">Compras de inventario</div><div class="val"><?= e(money($s['purchases_total'])) ?></div><div class="sub">Informativo: no se suma al costo</div></div></div>
</div>

<div class="fc-card mb-3"><div class="fc-card-h">Cómo se calcula</div><div class="fc-card-b small">
  <p class="mb-1"><strong>Costo total del período = costos fijos + costos variables.</strong></p>
  <ul class="mb-2">
    <li>Gastos operativos fijos: <?= e(money($s['opex_fixed'])) ?> · Gastos operativos variables: <?= e(money($s['opex_variable'])) ?></li>
    <li>Costo de la mercadería vendida (se reconoce al vender, con el costo promedio de ese momento): <?= e(money($s['cogs'])) ?></li>
    <li>Comisiones de medios de pago: <?= e(money($s['commissions'])) ?></li>
  </ul>
  <p class="mb-0 text-muted">Las <strong>compras</strong> de mercadería no son un costo del período: aumentan el inventario. Si se sumaran además del costo de lo vendido, el mismo dinero se contaría dos veces. Los gastos «mixtos» se reparten según el porcentaje fijo configurado.</p>
</div></div>

<div class="fc-card"><div class="fc-card-h"><?= e($rep['title']) ?></div><div class="fc-card-b"><?= render_report($rep) ?></div></div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
