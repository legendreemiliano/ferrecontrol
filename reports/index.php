<?php
/** Centro de informes: acceso rápido a todos los informes que el rol puede ver. */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once ROOT_DIR . '/reports/admin.php';
require_once ROOT_DIR . '/reports/stock.php';
require_once ROOT_DIR . '/reports/personal.php';

$groups = [];
if (can('admin')) $groups['Administración'] = ['url' => 'modules/administracion/informes.php', 'items' => admin_report_types()];
if (can('reports.stock')) $groups['Producción y Stock'] = ['url' => 'modules/stock/reportes.php', 'items' => stock_report_types()];
if (can('reports.personal')) $groups['Personal'] = ['url' => 'modules/personal/reportes.php', 'items' => personal_report_types()];

$pageTitle = 'Informes';
$active = '';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Centro de informes</h1></div>
<?php if (!$groups): ?><div class="alert alert-info">Tu rol no tiene informes disponibles.</div><?php endif; ?>
<div class="row g-3">
<?php foreach ($groups as $name => $g): ?>
  <div class="col-lg-4"><div class="fc-card h-100"><div class="fc-card-h"><?= e($name) ?></div><div class="list-group list-group-flush">
    <?php foreach ($g['items'] as $k => $label): ?><a class="list-group-item list-group-item-action" href="<?= e(url($g['url'] . '?tipo=' . $k)) ?>"><?= e($label) ?></a><?php endforeach; ?></div></div></div>
<?php endforeach; ?>
</div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
