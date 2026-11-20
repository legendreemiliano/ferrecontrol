<?php
/** Informes de Administración. Cada informe devuelve el formato descripto en render_report(). */

function admin_report_types(): array
{
    return [
        'ingresos'          => 'Ingresos',
        'egresos'           => 'Egresos',
        'ventas'            => 'Ventas',
        'ganancias'         => 'Ganancias estimadas',
        'gastos_categoria'  => 'Gastos por categoría',
        'costos'            => 'Costos fijos y variables',
        'compras_proveedor' => 'Compras a proveedores',
        'rentables'         => 'Productos más rentables',
        'evolucion'         => 'Evolución mensual',
    ];
}

function admin_report(string $type, string $from, string $to): array
{
    $types = admin_report_types();
    if (!isset($types[$type])) $type = 'ingresos';
    $title = $types[$type] . ' · ' . date_ar($from) . ' al ' . date_ar($to);

    switch ($type) {
        case 'ingresos':
            $rows = q_all("SELECT c.mov_date, c.concept, c.category, c.source, pm.name AS method, c.status, c.amount
                           FROM cash_movements c LEFT JOIN payment_methods pm ON pm.id = c.payment_method_id
                           WHERE c.type = 'ingreso' AND c.mov_date BETWEEN ? AND ? ORDER BY c.mov_date, c.id", [$from, $to]);
            $sum = 0; foreach ($rows as $r) if ($r['status'] === 'cobrado') $sum += (float)$r['amount'];
            return ['title' => $title, 'note' => 'El total suma solo los ingresos cobrados (no anulados).',
                'cols' => [['k' => 'mov_date', 'l' => 'Fecha', 't' => 'date'], ['k' => 'concept', 'l' => 'Concepto', 't' => 'text'], ['k' => 'category', 'l' => 'Categoría', 't' => 'text'],
                           ['k' => 'source', 'l' => 'Origen', 't' => 'text'], ['k' => 'method', 'l' => 'Medio de pago', 't' => 'text'], ['k' => 'status', 'l' => 'Estado', 't' => 'text'], ['k' => 'amount', 'l' => 'Importe', 't' => 'money']],
                'rows' => $rows, 'foot' => ['concept' => 'TOTAL COBRADO', 'amount' => $sum]];

        case 'egresos':
            $rows = q_all("SELECT c.mov_date, c.concept, c.category, c.cost_type, c.source, c.status, c.amount
                           FROM cash_movements c WHERE c.type = 'egreso' AND c.mov_date BETWEEN ? AND ? ORDER BY c.mov_date, c.id", [$from, $to]);
            $paid = 0; $pend = 0;
            foreach ($rows as $r) { if ($r['status'] === 'pagado') $paid += (float)$r['amount']; elseif ($r['status'] === 'pendiente') $pend += (float)$r['amount']; }
            return ['title' => $title, 'note' => 'Total pagado: ' . money($paid) . ' · Pendiente de pago: ' . money($pend) . ' (los anulados no se suman).',
                'cols' => [['k' => 'mov_date', 'l' => 'Fecha', 't' => 'date'], ['k' => 'concept', 'l' => 'Concepto', 't' => 'text'], ['k' => 'category', 'l' => 'Categoría', 't' => 'text'],
                           ['k' => 'cost_type', 'l' => 'Tipo de costo', 't' => 'text'], ['k' => 'source', 'l' => 'Origen', 't' => 'text'], ['k' => 'status', 'l' => 'Estado', 't' => 'text'], ['k' => 'amount', 'l' => 'Importe', 't' => 'money']],
                'rows' => $rows, 'foot' => ['concept' => 'TOTAL (pagado + pendiente)', 'amount' => $paid + $pend]];

        case 'ventas':
            $rows = q_all("SELECT s.number, s.sale_date, u.full_name AS seller, s.customer_name, s.total, s.returned_total, s.paid_total, s.payment_status, s.status
                           FROM sales s JOIN users u ON u.id = s.user_id WHERE DATE(s.sale_date) BETWEEN ? AND ? ORDER BY s.sale_date, s.id", [$from, $to]);
            $tot = 0; $ret = 0; $paid = 0;
            foreach ($rows as &$r) {
                $r['estado'] = $r['status'] === 'anulada' ? 'anulada' : $r['payment_status'];
                if ($r['status'] === 'confirmada') { $tot += (float)$r['total']; $ret += (float)$r['returned_total']; $paid += (float)$r['paid_total']; }
            }
            unset($r);
            return ['title' => $title, 'note' => 'Los totales no incluyen ventas anuladas.',
                'cols' => [['k' => 'number', 'l' => 'N.º', 't' => 'text'], ['k' => 'sale_date', 'l' => 'Fecha', 't' => 'datetime'], ['k' => 'seller', 'l' => 'Vendedor', 't' => 'text'],
                           ['k' => 'customer_name', 'l' => 'Cliente', 't' => 'text'], ['k' => 'total', 'l' => 'Total', 't' => 'money'], ['k' => 'returned_total', 'l' => 'Devuelto', 't' => 'money'],
                           ['k' => 'paid_total', 'l' => 'Cobrado', 't' => 'money'], ['k' => 'estado', 'l' => 'Estado', 't' => 'text']],
                'rows' => $rows, 'foot' => ['sale_date' => 'TOTALES', 'total' => $tot, 'returned_total' => $ret, 'paid_total' => $paid]];

        case 'ganancias':
            $s = period_summary($from, $to);
            $rows = [
                ['c' => 'Ventas brutas', 'a' => $s['gross_sales']],
                ['c' => '(-) Devoluciones', 'a' => $s['returns']],
                ['c' => '= Ventas netas', 'a' => $s['net_sales']],
                ['c' => '(-) Costo de la mercadería vendida', 'a' => $s['cogs']],
                ['c' => '= GANANCIA BRUTA', 'a' => $s['gross_profit']],
                ['c' => '(+) Otros ingresos cobrados', 'a' => $s['other_income']],
                ['c' => '(-) Comisiones de medios de pago', 'a' => $s['commissions']],
                ['c' => '(-) Sueldos del personal (liquidaciones pagadas)', 'a' => $s['payroll']],
                ['c' => '(-) Otros gastos fijos (alquiler, servicios, impuestos…)', 'a' => $s['opex_fixed'] - $s['payroll']],
                ['c' => '(-) Gastos variables', 'a' => $s['opex_variable']],
                ['c' => '= RESULTADO OPERATIVO ESTIMADO', 'a' => $s['operating_result']],
            ];
            return ['title' => $title, 'note' => 'Ganancia bruta = ventas netas − costo de mercadería vendida. Resultado operativo = ganancia bruta − gastos operativos. Es una estimación de gestión; no equivale al resultado contable ni impositivo.',
                'cols' => [['k' => 'c', 'l' => 'Concepto', 't' => 'text'], ['k' => 'a', 'l' => 'Importe', 't' => 'money']], 'rows' => $rows];

        case 'gastos_categoria':
            $rows = q_all("SELECT category, COUNT(*) AS cnt, SUM(amount) AS total FROM cash_movements
                           WHERE type = 'egreso' AND status <> 'anulado' AND source <> 'devolucion' AND mov_date BETWEEN ? AND ?
                           GROUP BY category ORDER BY total DESC", [$from, $to]);
            $sum = 0; foreach ($rows as $r) $sum += (float)$r['total'];
            foreach ($rows as &$r) $r['pct'] = $sum > 0 ? (float)$r['total'] / $sum * 100 : 0;
            unset($r);
            return ['title' => $title, 'note' => 'Incluye compras de mercadería (que son inventario, no gasto del período) y excluye reembolsos a clientes.',
                'cols' => [['k' => 'category', 'l' => 'Categoría', 't' => 'text'], ['k' => 'cnt', 'l' => 'Operaciones', 't' => 'int'], ['k' => 'total', 'l' => 'Total', 't' => 'money'], ['k' => 'pct', 'l' => '% del total', 't' => 'pct']],
                'rows' => $rows, 'foot' => ['category' => 'TOTAL', 'total' => $sum]];

        case 'costos':
            $raw = q_all("SELECT category,
                            SUM(CASE cost_type WHEN 'fijo' THEN amount WHEN 'mixto' THEN amount * COALESCE(fixed_pct,50) / 100 ELSE 0 END) AS fixed_part,
                            SUM(CASE cost_type WHEN 'variable' THEN amount WHEN 'mixto' THEN amount * (100 - COALESCE(fixed_pct,50)) / 100 ELSE 0 END) AS variable_part
                          FROM cash_movements
                          WHERE type = 'egreso' AND status <> 'anulado' AND source IN ('manual','sueldo') AND mov_date BETWEEN ? AND ?
                          GROUP BY category ORDER BY category", [$from, $to]);
            $s = period_summary($from, $to);
            $raw[] = ['category' => 'Costo de la mercadería vendida', 'fixed_part' => 0, 'variable_part' => $s['cogs']];
            $raw[] = ['category' => 'Comisiones de medios de pago', 'fixed_part' => 0, 'variable_part' => $s['commissions']];
            $f = 0; $v = 0;
            foreach ($raw as &$r) { $r['total'] = (float)$r['fixed_part'] + (float)$r['variable_part']; $f += (float)$r['fixed_part']; $v += (float)$r['variable_part']; }
            unset($r);
            return ['title' => $title, 'note' => 'Costo total = costos fijos + costos variables. Las compras de mercadería (' . money($s['purchases_total']) . ' en el período) no se suman: aumentan el inventario y se reconocen como costo cuando se venden.',
                'cols' => [['k' => 'category', 'l' => 'Concepto', 't' => 'text'], ['k' => 'fixed_part', 'l' => 'Fijo', 't' => 'money'], ['k' => 'variable_part', 'l' => 'Variable', 't' => 'money'], ['k' => 'total', 'l' => 'Total', 't' => 'money']],
                'rows' => $raw, 'foot' => ['category' => 'TOTAL', 'fixed_part' => $f, 'variable_part' => $v, 'total' => $f + $v]];

        case 'compras_proveedor':
            $rows = q_all("SELECT s.name, COUNT(p.id) AS cnt, SUM(p.total) AS total FROM purchases p JOIN suppliers s ON s.id = p.supplier_id
                           WHERE p.status = 'confirmada' AND p.purchase_date BETWEEN ? AND ? GROUP BY s.id, s.name ORDER BY total DESC", [$from, $to]);
            $sum = 0; $n = 0; foreach ($rows as $r) { $sum += (float)$r['total']; $n += (int)$r['cnt']; }
            return ['title' => $title, 'note' => 'No incluye compras anuladas.',
                'cols' => [['k' => 'name', 'l' => 'Proveedor', 't' => 'text'], ['k' => 'cnt', 'l' => 'Compras', 't' => 'int'], ['k' => 'total', 'l' => 'Total comprado', 't' => 'money']],
                'rows' => $rows, 'foot' => ['name' => 'TOTAL', 'cnt' => $n, 'total' => $sum]];

        case 'rentables':
            $rows = q_all("SELECT p.code, p.name, SUM(si.quantity - si.returned_qty) AS qty,
                             SUM((si.quantity - si.returned_qty) * si.unit_price * (1 - s.discount_pct / 100)) AS revenue,
                             SUM((si.quantity - si.returned_qty) * si.unit_cost) AS cost
                           FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
                           WHERE s.status = 'confirmada' AND DATE(s.sale_date) BETWEEN ? AND ?
                           GROUP BY p.id, p.code, p.name HAVING qty > 0", [$from, $to]);
            foreach ($rows as &$r) {
                $r['profit'] = (float)$r['revenue'] - (float)$r['cost'];
                $r['margin'] = (float)$r['revenue'] > 0 ? $r['profit'] / (float)$r['revenue'] * 100 : 0;
            }
            unset($r);
            usort($rows, function ($a, $b) { return $b['profit'] <=> $a['profit']; });
            return ['title' => $title, 'note' => 'Ganancia = ingreso neto de descuentos − costo promedio al momento de la venta. Descuenta las unidades devueltas.',
                'cols' => [['k' => 'code', 'l' => 'Código', 't' => 'text'], ['k' => 'name', 'l' => 'Producto', 't' => 'text'], ['k' => 'qty', 'l' => 'Unidades', 't' => 'int'],
                           ['k' => 'revenue', 'l' => 'Ingreso', 't' => 'money'], ['k' => 'cost', 'l' => 'Costo', 't' => 'money'], ['k' => 'profit', 'l' => 'Ganancia', 't' => 'money'], ['k' => 'margin', 'l' => 'Margen s/ventas', 't' => 'pct']],
                'rows' => $rows];

        case 'evolucion':
            $rows = [];
            $cur = strtotime(date('Y-m-01', strtotime($from)));
            $end = strtotime(date('Y-m-01', strtotime($to)));
            $guard = 0;
            while ($cur <= $end && $guard++ < 36) {
                $ms = max($from, date('Y-m-01', $cur));
                $me = min($to, date('Y-m-t', $cur));
                $s = period_summary($ms, $me);
                $rows[] = ['month' => month_es((int)date('n', $cur)) . ' ' . date('Y', $cur), 'net' => $s['net_sales'], 'cogs' => $s['cogs'], 'gp' => $s['gross_profit'],
                           'opex' => $s['opex'] + $s['commissions'], 'result' => $s['operating_result'], 'purchases' => $s['purchases_total']];
                $cur = strtotime('+1 month', $cur);
            }
            return ['title' => $title, 'note' => 'Cada fila resume un mes (recortado al rango elegido). Gastos = operativos + comisiones.',
                'cols' => [['k' => 'month', 'l' => 'Mes', 't' => 'text'], ['k' => 'net', 'l' => 'Ventas netas', 't' => 'money'], ['k' => 'cogs', 'l' => 'Costo mercadería', 't' => 'money'],
                           ['k' => 'gp', 'l' => 'Ganancia bruta', 't' => 'money'], ['k' => 'opex', 'l' => 'Gastos', 't' => 'money'], ['k' => 'result', 'l' => 'Resultado', 't' => 'money'], ['k' => 'purchases', 'l' => 'Compras (informativo)', 't' => 'money']],
                'rows' => $rows];
    }
    return ['title' => $title, 'note' => '', 'cols' => [], 'rows' => []];
}
