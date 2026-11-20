<?php
/**
 * Servicio de Administración: movimientos de caja, resumen económico, costos.
 *
 * Criterios (se aclaran también en pantalla):
 *  - Ventas netas = ventas confirmadas - devoluciones.
 *  - Costo de mercadería vendida (CMV) = costo promedio vigente al momento de cada venta.
 *  - Las COMPRAS de mercadería NO son un gasto del período: aumentan el inventario.
 *    El costo recién se reconoce cuando se vende (CMV). Así no se cuenta dos veces.
 *  - Gastos operativos = egresos manuales + sueldos pagados (no incluye compras ni reembolsos).
 *  - Es una estimación de gestión, no el balance contable ni impositivo.
 */

function cash_insert(array $m): int
{
    $d = array_merge([
        'category' => '', 'status' => 'cobrado', 'payment_method_id' => null, 'cost_type' => 'no_aplica', 'fixed_pct' => null,
        'source' => 'manual', 'sale_id' => null, 'purchase_id' => null, 'payroll_id' => null, 'payment_id' => null,
        'supplier_id' => null, 'reference' => '', 'paid_date' => null, 'notes' => '',
    ], $m);
    q('INSERT INTO cash_movements
        (mov_date, type, concept, category, amount, status, payment_method_id, cost_type, fixed_pct, source,
         sale_id, purchase_id, payroll_id, payment_id, supplier_id, reference, paid_date, user_id, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$d['mov_date'], $d['type'], mb_substr($d['concept'], 0, 200), mb_substr($d['category'], 0, 80), money_round($d['amount']),
       $d['status'], $d['payment_method_id'], $d['cost_type'], $d['fixed_pct'], $d['source'],
       $d['sale_id'], $d['purchase_id'], $d['payroll_id'], $d['payment_id'], $d['supplier_id'],
       mb_substr((string)$d['reference'], 0, 80), $d['paid_date'], acting_user_id(), $d['notes']]);
    return (int)db()->lastInsertId();
}

/** Marca como pagado un egreso pendiente (cuenta por pagar). */
function expense_mark_paid(int $cashId, ?int $methodId, ?string $date = null): void
{
    with_transaction(function () use ($cashId, $methodId, $date) {
        $m = q_one("SELECT * FROM cash_movements WHERE id = ? AND type = 'egreso' FOR UPDATE", [$cashId]);
        if (!$m) throw new AppException('El egreso no existe.');
        if ($m['status'] !== 'pendiente') throw new AppException('Solo se pueden pagar egresos que estén pendientes.');
        if ($methodId !== null && !q_one('SELECT id FROM payment_methods WHERE id = ? AND active = 1', [$methodId])) {
            throw new AppException('El medio de pago no es válido.');
        }
        $date = ($date && valid_date($date)) ? $date : today();
        q("UPDATE cash_movements SET status = 'pagado', paid_date = ?, payment_method_id = ? WHERE id = ?", [$date, $methodId, $cashId]);
        if ($m['purchase_id']) {
            q("UPDATE purchases SET payment_status = 'pagada' WHERE id = ?", [$m['purchase_id']]);
        }
        audit('egreso_pagado', 'cash_movements', $cashId, 'Pago de ' . $m['concept'] . ' por ' . money($m['amount']));
    });
}

/** Resumen económico de un período (fechas Y-m-d inclusive). */
function period_summary(string $from, string $to): array
{
    $s = [];
    $r = q_one("SELECT COALESCE(SUM(total),0) AS gross, COALESCE(SUM(returned_total),0) AS ret,
                       COALESCE(SUM(cogs),0) AS cogs, COUNT(*) AS cnt
                FROM sales WHERE status = 'confirmada' AND DATE(sale_date) BETWEEN ? AND ?", [$from, $to]);
    $s['gross_sales'] = (float)$r['gross'];
    $s['returns']     = (float)$r['ret'];
    $s['net_sales']   = $s['gross_sales'] - $s['returns'];
    $s['cogs']        = (float)$r['cogs'];
    $s['sales_count'] = (int)$r['cnt'];
    $s['gross_profit'] = $s['net_sales'] - $s['cogs'];

    $s['commissions'] = (float)q_val("SELECT COALESCE(SUM(p.commission),0) FROM payments p JOIN sales s ON s.id = p.sale_id
                                      WHERE p.type = 'cobro' AND s.status = 'confirmada' AND DATE(p.paid_at) BETWEEN ? AND ?", [$from, $to]);

    $e = q_one("SELECT
        COALESCE(SUM(CASE cost_type WHEN 'fijo' THEN amount WHEN 'mixto' THEN amount * COALESCE(fixed_pct,50) / 100 ELSE 0 END),0) AS fixed,
        COALESCE(SUM(CASE cost_type WHEN 'variable' THEN amount WHEN 'mixto' THEN amount * (100 - COALESCE(fixed_pct,50)) / 100 ELSE 0 END),0) AS variable
        FROM cash_movements
        WHERE type = 'egreso' AND status <> 'anulado' AND source IN ('manual','sueldo') AND mov_date BETWEEN ? AND ?", [$from, $to]);
    $s['opex_fixed']    = (float)$e['fixed'];
    $s['opex_variable'] = (float)$e['variable'];
    $s['opex']          = $s['opex_fixed'] + $s['opex_variable'];
    // sueldos pagados en el período (están incluidos en los gastos fijos; se muestran aparte para verlos claros)
    $s['payroll'] = (float)q_val("SELECT COALESCE(SUM(amount),0) FROM cash_movements
                                  WHERE type = 'egreso' AND status <> 'anulado' AND source = 'sueldo' AND mov_date BETWEEN ? AND ?", [$from, $to]);

    $s['other_income'] = (float)q_val("SELECT COALESCE(SUM(amount),0) FROM cash_movements
                                       WHERE type = 'ingreso' AND source = 'manual' AND status = 'cobrado' AND mov_date BETWEEN ? AND ?", [$from, $to]);

    $s['operating_result'] = $s['gross_profit'] + $s['other_income'] - $s['commissions'] - $s['opex'];

    // costo total del período = fijos + variables (incluye CMV y comisiones)
    $s['cost_fixed']    = $s['opex_fixed'];
    $s['cost_variable'] = $s['opex_variable'] + $s['cogs'] + $s['commissions'];
    $s['cost_total']    = $s['cost_fixed'] + $s['cost_variable'];

    $s['purchases_total'] = (float)q_val("SELECT COALESCE(SUM(total),0) FROM purchases WHERE status = 'confirmada' AND purchase_date BETWEEN ? AND ?", [$from, $to]);

    $s['income_collected'] = (float)q_val("SELECT COALESCE(SUM(amount),0) FROM cash_movements WHERE type='ingreso' AND status='cobrado' AND mov_date BETWEEN ? AND ?", [$from, $to]);
    $s['expenses_paid']    = (float)q_val("SELECT COALESCE(SUM(amount),0) FROM cash_movements WHERE type='egreso' AND status='pagado' AND mov_date BETWEEN ? AND ?", [$from, $to]);
    $s['expenses_pending'] = (float)q_val("SELECT COALESCE(SUM(amount),0) FROM cash_movements WHERE type='egreso' AND status='pendiente' AND mov_date BETWEEN ? AND ?", [$from, $to]);
    $s['receivable'] = (float)q_val("SELECT COALESCE(SUM(total - returned_total - paid_total),0) FROM sales
                                     WHERE status = 'confirmada' AND payment_status <> 'pagada' AND DATE(sale_date) BETWEEN ? AND ?", [$from, $to]);
    return $s;
}

/** Valor del inventario según el costo promedio ponderado. */
function inventory_value(): float
{
    return (float)q_val('SELECT COALESCE(SUM(stock * avg_cost),0) FROM products');
}

/** Cálculo de precio a partir del costo. $method: 'recargo' o 'margen'. Devuelve null si no es posible. */
function calc_price(float $cost, string $method, float $pct): ?float
{
    if ($cost <= 0 || $pct < 0) return null;
    if ($method === 'margen') {
        if ($pct >= 100) return null;
        return money_round($cost / (1 - $pct / 100));
    }
    return money_round($cost * (1 + $pct / 100));
}
