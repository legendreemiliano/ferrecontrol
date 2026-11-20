<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once ROOT_DIR . '/reports/personal.php';
require_perm('reports.personal');

$types = personal_report_types();
$type = get_str('tipo', 30);
if (!isset($types[$type])) $type = 'nomina';
$from = get_date('desde', date('Y-m-01')); $to = get_date('hasta', date('Y-m-t'));
if ($from > $to) { $t = $from; $from = $to; $to = $t; }
$rep = personal_report($type, $from, $to);
if (get_str('export', 5) === 'csv') report_csv($rep, 'personal_' . $type . '_' . today() . '.csv');

$pageTitle = 'Reportes de personal';
$active = 'per.reportes';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Reportes de Personal</h1>
  <div class="no-print"><a class="btn btn-outline-primary" href="<?= e(qs(['export' => 'csv'])) ?>"><i class="bi bi-filetype-csv"></i> Exportar CSV</a> <button class="btn btn-outline-secondary" data-print><i class="bi bi-printer"></i> Imprimir</button></div></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-12 col-md-4"><label class="form-label small mb-0">Reporte</label><select class="form-select form-select-sm" name="tipo"><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === $type ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-clipboard-data"></i> Generar</button></div>
</form>
<div class="fc-card"><div class="fc-card-h"><?= e($rep['title']) ?></div><div class="fc-card-b"><?php if (!empty($rep['note'])): ?><p class="fc-note"><?= e($rep['note']) ?></p><?php endif; ?><?= render_report($rep) ?></div></div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
