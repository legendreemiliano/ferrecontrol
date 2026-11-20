<?php
/**
 * Carga de datos de demostración. Usa las MISMAS funciones del sistema (compras, ventas,
 * cobros, devoluciones...) en vez de insertar filas "a mano", así el stock, los costos
 * promedio y la caja quedan consistentes entre sí.
 */
function demo_seed_run(array $sellerIds): array
{
    $data = json_decode(file_get_contents(ROOT_DIR . '/includes/demo_data.json'), true);
    if (!is_array($data)) throw new RuntimeException('No se pudo leer includes/demo_data.json');
    $admin = $sellerIds[0];
    $GLOBALS['ACTING_USER_ID'] = $admin;

    $codes = [];
    foreach (q_all('SELECT id, code FROM products') as $r) $codes[$r['code']] = (int)$r['id'];
    $pid = function (string $code) use ($codes): int {
        if (!isset($codes[$code])) throw new RuntimeException('Producto de demo inexistente: ' . $code);
        return $codes[$code];
    };
    $count = ['compras' => 0, 'ventas' => 0, 'gastos' => 0, 'asistencias' => 0, 'liquidaciones' => 0];

    // ---- compras (en orden cronológico para que el costo promedio sea correcto)
    $purchases = $data['purchases'];
    usort($purchases, function ($a, $b) { return $a['day'] <=> $b['day']; });
    foreach ($purchases as $p) {
        $items = [];
        foreach ($p['items'] as $it) $items[] = ['product_id' => $pid($it['code']), 'quantity' => $it['qty'], 'unit_cost' => $it['cost']];
        purchase_create([
            'supplier_id' => $p['supplier'], 'purchase_date' => days_ago($p['day']), 'discount' => $p['discount'], 'extra_costs' => $p['extra'],
            'payment_status' => $p['paid'] ? 'pagada' : 'pendiente', 'payment_method_id' => $p['method'], 'reference' => $p['ref'],
            'notes' => 'Compra de demostración',
        ], $items);
        $count['compras']++;
    }

    // ---- ventas
    $saleIds = [];
    foreach ($data['sales'] as $i => $s) {
        $GLOBALS['ACTING_USER_ID'] = $sellerIds[$s['seller'] % count($sellerIds)];
        $items = [];
        foreach ($s['items'] as $it) $items[] = ['product_id' => $pid($it['code']), 'quantity' => $it['qty']];
        $pay = $s['pay'];
        $saleIds[$i] = sale_create([
            'customer_name' => $s['customer'], 'discount_pct' => $s['disc'],
            'payment_method_id' => $pay[0] === 'none' ? 0 : $pay[1],
            'pay_amount' => $pay[0] === 'partial' ? $pay[2] : null,
            'sale_date' => days_ago($s['day']) . ' ' . $s['time'] . ':00',
            'notes' => 'Venta de demostración',
        ], $items);
        $count['ventas']++;
    }
    $GLOBALS['ACTING_USER_ID'] = $admin;

    foreach ($data['later_payments'] as $lp) {
        sale_add_payment($saleIds[$lp['sale']], $lp['method'], $lp['amount'], null, 'Cobro de saldo', days_ago($lp['day']) . ' 11:00:00');
    }
    foreach ($data['returns'] as $r) {
        $sid = $saleIds[$r['sale']];
        $qty = [];
        foreach ($r['items'] as $it) {
            $itemId = (int)q_val('SELECT id FROM sale_items WHERE sale_id = ? AND product_id = ?', [$sid, $pid($it['code'])]);
            $qty[$itemId] = $it['qty'];
        }
        sale_return($sid, $qty, $r['reason'], (bool)$r['restock'], $r['method'], days_ago($r['day']) . ' 16:00:00');
    }
    foreach ($data['voids'] as $v) {
        sale_void($saleIds[$v['sale']], $v['reason'], $v['method'], days_ago($v['day']) . ' 18:00:00');
    }

    // ---- gastos manuales y otros ingresos
    $cats = [];
    foreach (q_all('SELECT name, cost_type, fixed_pct FROM expense_categories') as $c) $cats[$c['name']] = $c;
    foreach ($data['expenses'] as $x) {
        $c = $cats[$x['category']];
        $paid = $x['status'] === 'pagado';
        cash_insert([
            'mov_date' => days_ago($x['day']), 'type' => 'egreso', 'concept' => $x['concept'], 'category' => $x['category'],
            'amount' => $x['amount'], 'status' => $paid ? 'pagado' : 'pendiente', 'payment_method_id' => $x['method'],
            'cost_type' => $c['cost_type'], 'fixed_pct' => $c['cost_type'] === 'mixto' ? $c['fixed_pct'] : null,
            'source' => 'manual', 'reference' => $x['ref'], 'paid_date' => $paid ? days_ago($x['day']) : null,
        ]);
        $count['gastos']++;
    }
    foreach ($data['other_income'] as $o) {
        cash_insert([
            'mov_date' => days_ago($o['day']), 'type' => 'ingreso', 'concept' => $o['concept'], 'category' => $o['category'],
            'amount' => $o['amount'], 'status' => 'cobrado', 'payment_method_id' => $o['method'], 'source' => 'manual',
        ]);
    }

    // ---- personal: asistencias de los últimos días hábiles
    $emps = q_all("SELECT id FROM employees ORDER BY id");
    for ($d = -14; $d <= -1; $d++) {
        $date = days_ago($d);
        if ((int)date('N', strtotime($date)) >= 6) continue;           // sábado y domingo no se cargan
        foreach ($emps as $k => $e) {
            $eid = (int)$e['id'];
            if ($eid === 2 && $d === -9)  { attendance_save($eid, $date, 1, 'presente', '09:32', '18:00', false, ''); }
            elseif ($eid === 3 && $d === -6) { attendance_save($eid, $date, 1, 'ausente', null, null, true, 'Certificado médico presentado'); }
            elseif ($eid === 5 && $d === -4) { attendance_save($eid, $date, 1, 'ausente', null, null, false, ''); }
            else { attendance_save($eid, $date, 1, 'presente', '08:' . str_pad((string)(50 + ($k * 2 + abs($d)) % 9), 2, '0', STR_PAD_LEFT), '18:00', false, ''); }
            $count['asistencias']++;
        }
    }
    q("INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason, status, approved_by, user_id) VALUES (5, 'Vacaciones', ?, ?, 'Vacaciones programadas', 'aprobada', ?, ?)",
      [days_ago(7), days_ago(14), $admin, $admin]);
    q("INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason, status, user_id) VALUES (2, 'Estudio', ?, ?, 'Examen final', 'pendiente', ?)",
      [days_ago(3), days_ago(3), $admin]);

    // ---- liquidaciones del mes anterior (estimadas)
    $period = date('Y-m', strtotime('first day of last month'));
    foreach ($emps as $k => $e) {
        $calc = payroll_calculate((int)$e['id'], $period, ['concept_ids' => [], 'absence_discount' => true]);
        $id = payroll_save($calc, 'Liquidación de demostración');
        if ($k <= 3) payroll_approve($id);
        if ($k <= 2) payroll_pay($id, 2, days_ago(-3));
        $count['liquidaciones']++;
    }
    unset($GLOBALS['ACTING_USER_ID']);
    return $count;
}
