<?php
/** Servicio de compras: alta, anulación. Todo dentro de una transacción. */

/**
 * Registra una compra confirmada.
 * $d: supplier_id, purchase_date, discount, extra_costs, payment_status (pagada|pendiente),
 *     payment_method_id, reference, notes
 * $items: [ ['product_id'=>, 'quantity'=>, 'unit_cost'=>], ... ]
 * Devuelve el id de la compra.
 */
function purchase_create(array $d, array $items): int
{
    $uid = acting_user_id();
    $supplier = q_one('SELECT id, name FROM suppliers WHERE id = ? AND active = 1', [(int)($d['supplier_id'] ?? 0)]);
    if (!$supplier) throw new AppException('Elegí un proveedor válido y activo.');

    $date = (string)($d['purchase_date'] ?? '');
    if (!valid_date($date)) throw new AppException('La fecha de la compra no es válida.');
    if ($date > today()) throw new AppException('La fecha de la compra no puede ser futura.');

    $discount = money_round($d['discount'] ?? 0);
    $extra    = money_round($d['extra_costs'] ?? 0);
    if ($discount < 0 || $extra < 0) throw new AppException('El descuento y los otros costos no pueden ser negativos.');

    $payStatus = ($d['payment_status'] ?? 'pagada') === 'pendiente' ? 'pendiente' : 'pagada';
    $methodId = null;
    if ($payStatus === 'pagada') {
        $methodId = (int)($d['payment_method_id'] ?? 0);
        if (!q_one('SELECT id FROM payment_methods WHERE id = ? AND active = 1', [$methodId])) {
            throw new AppException('Elegí el medio de pago de la compra.');
        }
    }

    if (!$items) throw new AppException('La compra debe tener al menos un producto.');
    $lines = [];
    $subtotal = 0.0;
    foreach ($items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (int)($it['quantity'] ?? 0);
        $cost = money_round($it['unit_cost'] ?? 0);
        if ($pid <= 0 || !q_one('SELECT id FROM products WHERE id = ?', [$pid])) throw new AppException('Hay un producto inválido en la compra.');
        if ($qty <= 0) throw new AppException('Todas las cantidades deben ser números enteros mayores que cero.');
        if ($cost <= 0) throw new AppException('Todos los costos unitarios deben ser mayores que cero.');
        $line = money_round($qty * $cost);
        $lines[] = ['product_id' => $pid, 'qty' => $qty, 'cost' => $cost, 'line' => $line];
        $subtotal += $line;
    }
    $subtotal = money_round($subtotal);
    if ($discount > $subtotal) throw new AppException('El descuento no puede superar el subtotal.');
    $total = money_round($subtotal - $discount + $extra);
    if ($total <= 0) throw new AppException('El total de la compra debe ser mayor que cero.');
    $factor = $subtotal > 0 ? $total / $subtotal : 1.0;   // reparte descuento y otros costos entre las líneas

    return with_transaction(function (PDO $pdo) use ($d, $supplier, $date, $discount, $extra, $payStatus, $methodId, $lines, $subtotal, $total, $factor, $uid) {
        $pdo->prepare('INSERT INTO purchases (number, supplier_id, purchase_date, user_id, subtotal, discount, extra_costs, total, payment_status, status, reference, notes)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([uniqid('T'), $supplier['id'], $date, $uid, $subtotal, $discount, $extra, $total, $payStatus, 'confirmada',
                       mb_substr((string)($d['reference'] ?? ''), 0, 80), (string)($d['notes'] ?? '')]);
        $purchaseId = (int)$pdo->lastInsertId();
        $number = next_number('C', $purchaseId);
        $pdo->prepare('UPDATE purchases SET number = ? WHERE id = ?')->execute([$number, $purchaseId]);

        $when = $date . ' ' . date('H:i:s');
        foreach ($lines as $ln) {
            $eff = round($ln['cost'] * $factor, 4);
            // Costo promedio ponderado: (valor anterior + valor compra) / (cantidad anterior + cantidad comprada)
            $st = $pdo->prepare('SELECT stock, avg_cost FROM products WHERE id = ? FOR UPDATE');
            $st->execute([$ln['product_id']]);
            $p = $st->fetch();
            $stockBefore = (int)$p['stock'];
            $newAvg = $stockBefore > 0
                ? round(($stockBefore * (float)$p['avg_cost'] + $ln['qty'] * $eff) / ($stockBefore + $ln['qty']), 4)
                : $eff;
            $pdo->prepare('UPDATE products SET avg_cost = ?, last_cost = ? WHERE id = ?')->execute([$newAvg, $ln['cost'], $ln['product_id']]);

            stock_apply($ln['product_id'], 'compra', 'entrada', $ln['qty'], 'Compra ' . $number, ['purchase_id' => $purchaseId], $when);

            $pdo->prepare('INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, line_total, effective_unit_cost) VALUES (?,?,?,?,?,?)')
                ->execute([$purchaseId, $ln['product_id'], $ln['qty'], $ln['cost'], $ln['line'], $eff]);
        }

        cash_insert([
            'mov_date' => $date, 'type' => 'egreso', 'concept' => 'Compra ' . $number . ' - ' . $supplier['name'],
            'category' => 'Compra de mercadería', 'amount' => $total,
            'status' => $payStatus === 'pagada' ? 'pagado' : 'pendiente',
            'payment_method_id' => $methodId, 'cost_type' => 'no_aplica', 'source' => 'compra',
            'purchase_id' => $purchaseId, 'supplier_id' => $supplier['id'], 'reference' => (string)($d['reference'] ?? ''),
            'paid_date' => $payStatus === 'pagada' ? $date : null,
        ]);
        audit('compra_creada', 'purchases', $purchaseId, $number . ' por ' . money($total));
        return $purchaseId;
    });
}

/**
 * Anula una compra: revierte el stock (si todavía hay unidades suficientes),
 * ajusta el costo promedio y marca el egreso como anulado. La compra queda registrada.
 */
function purchase_void(int $purchaseId, string $reason): void
{
    $reason = trim($reason);
    if ($reason === '') throw new AppException('Indicá el motivo de la anulación.');
    with_transaction(function (PDO $pdo) use ($purchaseId, $reason) {
        $st = $pdo->prepare('SELECT * FROM purchases WHERE id = ? FOR UPDATE');
        $st->execute([$purchaseId]);
        $p = $st->fetch();
        if (!$p) throw new AppException('La compra no existe.');
        if ($p['status'] === 'anulada') throw new AppException('La compra ya estaba anulada.');

        $items = q_all('SELECT * FROM purchase_items WHERE purchase_id = ?', [$purchaseId]);
        foreach ($items as $it) {
            $st = $pdo->prepare('SELECT stock, avg_cost FROM products WHERE id = ? FOR UPDATE');
            $st->execute([$it['product_id']]);
            $pr = $st->fetch();
            $stock = (int)$pr['stock'];
            $newStock = $stock - (int)$it['quantity'];
            if ($newStock < 0) {
                throw new AppException('No se puede anular: ya se vendieron o descontaron unidades de esta compra y el stock quedaría negativo.');
            }
            if ($newStock > 0) {
                $avg = ($stock * (float)$pr['avg_cost'] - (int)$it['quantity'] * (float)$it['effective_unit_cost']) / $newStock;
                $pdo->prepare('UPDATE products SET avg_cost = ? WHERE id = ?')->execute([max(0, round($avg, 4)), $it['product_id']]);
            }
            stock_apply((int)$it['product_id'], 'anulacion_compra', 'salida', (int)$it['quantity'], 'Anulación de compra ' . $p['number'], ['purchase_id' => $purchaseId]);
        }
        $pdo->prepare("UPDATE purchases SET status = 'anulada', voided_at = ?, voided_by = ?, void_reason = ? WHERE id = ?")
            ->execute([now_sql(), acting_user_id(), mb_substr($reason, 0, 255), $purchaseId]);
        $pdo->prepare("UPDATE cash_movements SET status = 'anulado' WHERE purchase_id = ? AND source = 'compra'")->execute([$purchaseId]);
        audit('compra_anulada', 'purchases', $purchaseId, $p['number'] . ': ' . $reason);
    });
}
