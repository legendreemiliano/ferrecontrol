<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once ROOT_DIR . '/reports/admin.php';
require_perm('admin');

$types = admin_report_types();
$type = get_str('tipo', 30);
if (!isset($types[$type])) $type = 'ingresos';
[$from, $to] = date_range_params();
$rep = admin_report($type, $from, $to);

if (get_str('export', 5) === 'csv') {
    audit('informe_exportado', 'informes', null, $type . ' ' . $from . ' a ' . $to);
    report_csv($rep, 'informe_' . $type . '_' . $from . '_' . $to . '.csv');
}

$pageTitle = 'Informes';
$active = 'adm.informes';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Informes de Administración</h1>
  <div class="no-print">
    <a class="btn btn-outline-primary" href="<?= e(qs(['export' => 'csv'])) ?>"><i class="bi bi-filetype-csv"></i> Exportar CSV</a>
    <button class="btn btn-outline-secondary" data-print><i class="bi bi-printer"></i> Imprimir</button>
  </div></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-12 col-md-4"><label class="form-label small mb-0">Informe</label>
    <select class="form-select form-select-sm" name="tipo"><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === $type ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-file-earmark-bar-graph"></i> Generar</button></div>
</form>
<div class="fc-card"><div class="fc-card-h"><?= e($rep['title']) ?></div><div class="fc-card-b">
  <?php if (!empty($rep['note'])): ?><p class="fc-note"><?= e($rep['note']) ?></p><?php endif; ?>
  <?= render_report($rep) ?>
</div></div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
