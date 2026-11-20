<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('admin');

$from = date('Y-m-01'); $to = date('Y-m-t'); $today = today();
$m = period_summary($from, $to);
$incomeToday = (float)q_val("SELECT COALESCE(SUM(amount),0) FROM cash_movements WHERE type='ingreso' AND status='cobrado' AND mov_date = ?", [$today]);
$invValue = inventory_value();

// gráfico 1: ingresos vs egresos por mes (últimos 6 meses)
$since = date('Y-m-01', strtotime('-5 months'));
$byMonth = q_all("SELECT DATE_FORMAT(mov_date, '%Y-%m') AS ym,
        SUM(CASE WHEN type = 'ingreso' AND status = 'cobrado' THEN amount ELSE 0 END) AS ing,
        SUM(CASE WHEN type = 'egreso' AND status = 'pagado' THEN amount ELSE 0 END) AS egr
    FROM cash_movements WHERE mov_date >= ? GROUP BY ym ORDER BY ym", [$since]);
$monthMap = [];
foreach ($byMonth as $r) $monthMap[$r['ym']] = $r;
$labels1 = []; $ing = []; $egr = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01'))));
    $labels1[] = month_es((int)substr($ym, 5, 2)) . ' ' . substr($ym, 2, 2);
    $ing[] = round((float)($monthMap[$ym]['ing'] ?? 0), 2);
    $egr[] = round((float)($monthMap[$ym]['egr'] ?? 0), 2);
}
// gráfico 2: productos más vendidos (30 días)
$top = q_all("SELECT p.name, SUM(si.quantity - si.returned_qty) AS qty FROM sale_items si
    JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
    WHERE s.status = 'confirmada' AND DATE(s.sale_date) >= ? GROUP BY p.id, p.name ORDER BY qty DESC LIMIT 6", [days_ago(-30)]);
// gráfico 3: evolución de ventas diarias (30 días)
$daily = q_all("SELECT DATE(sale_date) AS d, SUM(total - returned_total) AS t FROM sales
    WHERE status = 'confirmada' AND DATE(sale_date) >= ? GROUP BY d ORDER BY d", [days_ago(-30)]);

$charts = [
    'flow'  => ['labels' => $labels1, 'ing' => $ing, 'egr' => $egr],
    'top'   => ['labels' => array_column($top, 'name'), 'data' => array_map('intval', array_column($top, 'qty'))],
    'daily' => ['labels' => array_map(function ($r) { return date_ar($r['d']); }, $daily), 'data' => array_map(function ($r) { return round((float)$r['t'], 2); }, $daily)],
];
$useCharts = true;
$inlineJs = 'var CH=' . json_encode($charts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';' . <<<'JS'
new Chart(document.getElementById('chFlow'), {type:'bar', data:{labels:CH.flow.labels, datasets:[
  {label:'Ingresos cobrados', data:CH.flow.ing, backgroundColor:'#1f4d3a'},
  {label:'Egresos pagados', data:CH.flow.egr, backgroundColor:'#e8731a'}]}, options:{responsive:true, maintainAspectRatio:false}});
new Chart(document.getElementById('chTop'), {type:'bar', data:{labels:CH.top.labels, datasets:[{label:'Unidades vendidas', data:CH.top.data, backgroundColor:'#2f6b52'}]},
  options:{indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}}});
new Chart(document.getElementById('chDaily'), {type:'line', data:{labels:CH.daily.labels, datasets:[{label:'Ventas netas', data:CH.daily.data, borderColor:'#e8731a', backgroundColor:'rgba(232,115,26,.15)', fill:true, tension:.25}]},
  options:{responsive:true, maintainAspectRatio:false}});
JS;

$pageTitle = 'Panel económico';
$active = 'adm.panel';
include ROOT_DIR . '/includes/header.php';

function stat_card(string $label, string $value, string $sub = '', string $cls = ''): void
{
    echo '<div class="col-6 col-md-4 col-xl-3"><div class="fc-stat ' . $cls . '"><div class="lbl">' . e($label) . '</div><div class="val">' . e($value) . '</div>'
       . ($sub !== '' ? '<div class="sub">' . e($sub) . '</div>' : '') . '</div></div>';
}
?>
<div class="fc-page-head">
  <h1>Administración · Panel económico</h1>
  <span class="text-muted">Mes en curso: <?= e(date_ar($from)) ?> al <?= e(date_ar($to)) ?></span>
</div>

<div class="row g-3 mb-3">
<?php
stat_card('Ingresos del día', money($incomeToday), 'Cobrado hoy');
stat_card('Ingresos del mes', money($m['income_collected']), 'Cobrado', 'accent');
stat_card('Egresos del mes', money($m['expenses_paid']), 'Efectivamente pagado');
stat_card('Compras de mercadería', money($m['purchases_total']), 'Del mes (aumentan el inventario)');
stat_card('Gastos fijos del mes', money($m['opex_fixed']));
stat_card('Gastos variables del mes', money($m['opex_variable']));
stat_card('Ganancia bruta estimada', money($m['gross_profit']), 'Ventas netas − costo de mercadería', 'accent');
stat_card('Resultado operativo estimado', money($m['operating_result']), 'Estimación de gestión', $m['operating_result'] < 0 ? 'danger' : '');
stat_card('Cantidad de ventas', num($m['sales_count']), 'Confirmadas del mes');
stat_card('Valor del inventario', money($invValue), 'A costo promedio ponderado');
stat_card('Ventas pendientes de cobro', money($m['receivable']), 'Del mes', $m['receivable'] > 0 ? 'danger' : '');
stat_card('Gastos pendientes de pago', money($m['expenses_pending']), 'Del mes');
?>
</div>

<div class="row g-3">
  <div class="col-lg-7"><div class="fc-card"><div class="fc-card-h">Ingresos y egresos por mes</div><div class="fc-card-b"><div class="chart-box"><canvas id="chFlow"></canvas></div></div></div></div>
  <div class="col-lg-5"><div class="fc-card"><div class="fc-card-h">Productos más vendidos (30 días)</div><div class="fc-card-b"><div class="chart-box"><canvas id="chTop"></canvas></div></div></div></div>
  <div class="col-12"><div class="fc-card"><div class="fc-card-h">Evolución de las ventas (últimos 30 días)</div><div class="fc-card-b"><div class="chart-box"><canvas id="chDaily"></canvas></div></div></div></div>
</div>
<p class="fc-note mt-3">Los resultados son <strong>estimaciones de gestión</strong> calculadas con los datos cargados en el sistema. No reemplazan el balance contable ni la liquidación de impuestos.</p>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
