<?php
/** Servicio de ventas: venta, cobros, devoluciones y anulaciones. */

/** Recalcula el estado de cobro de una venta a partir de lo cobrado y lo devuelto. */
function sale_refresh_payment_status(int $saleId): void
{
    $s = q_one('SELECT total, returned_total, paid_total FROM sales WHERE id = ?', [$saleId]);
    $due = (float)$s['total'] - (float)$s['returned_total'];
    $paid = (float)$s['paid_total'];
    if ($paid >= $due - 0.004) $status = 'pagada';
    elseif ($paid > 0)         $status = 'parcial';
    else                       $status = 'pendiente';
    q('UPDATE sales SET payment_status = ? WHERE id = ?', [$status, $saleId]);
}

/**
 * Registra una venta confirmada. Los precios SIEMPRE se leen de la base de datos:
 * lo que mande el navegador (JavaScript) solo se usa para saber qué producto y cuántas unidades.
 *
 * $d: customer_name, discount_pct, payment_method_id (0 = queda pendiente de cobro),
 *     pay_amount (opcional), received (opcional, efectivo), reference, notes, sale_date (opcional)
 * $items: [ ['product_id'=>, 'quantity'=>], ... ]
 */
function sale_create(array $d, array $items): int
{
    $uid = acting_user_id();
    $when = $d['sale_date'] ?? now_sql();

    $discPct = round((float)($d['discount_pct'] ?? 0), 2);
    if ($discPct < 0 || $discPct > 100) throw new AppException('El descuento debe estar entre 0 y 100 %.');
    if ($discPct > max_discount_pct() + 0.0001) {
        throw new AppException('Tu rol puede aplicar como máximo un ' . num(max_discount_pct(), 2) . ' % de descuento.');
    }

    // unifica líneas repetidas del mismo producto
    $merged = [];
    foreach ($items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (int)($it['quantity'] ?? 0);
        if ($pid <= 0 || $qty <= 0) throw new AppException('Cada línea de la venta necesita un producto y una cantidad entera mayor que cero.');
        $merged[$pid] = ($merged[$pid] ?? 0) + $qty;
    }
    if (!$merged) throw new AppException('El carrito está vacío.');

    $methodId = (int)($d['payment_method_id'] ?? 0);

    return with_transaction(function (PDO $pdo) use ($d, $uid, $when, $discPct, $merged, $methodId) {
        $pdo->prepare('INSERT INTO sales (number, sale_date, user_id, customer_name, discount_pct, status, payment_status, notes) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([uniqid('T'), $when, $uid, mb_substr((string)($d['customer_name'] ?? ''), 0, 120), $discPct, 'confirmada', 'pendiente', (string)($d['notes'] ?? '')]);
        $saleId = (int)$pdo->lastInsertId();
        $number = next_number('V', $saleId);

        $subtotal = 0.0;
        $cogs = 0.0;
        foreach ($merged as $pid => $qty) {
            $st = $pdo->prepare('SELECT id, name, sale_price, avg_cost, last_cost, active FROM products WHERE id = ? FOR UPDATE');
            $st->execute([$pid]);
            $p = $st->fetch();
            if (!$p || (int)$p['active'] !== 1) throw new AppException('Hay un producto inexistente o inactivo en el carrito.');
            $price = money_round($p['sale_price']);
            if ($price <= 0) throw new AppException('El producto «' . $p['name'] . '» no tiene precio de venta cargado.');

            stock_apply($pid, 'venta', 'salida', $qty, 'Venta ' . $number, ['sale_id' => $saleId], $when);   // rechaza si no hay stock

            $unitCost = (float)$p['avg_cost'] > 0 ? (float)$p['avg_cost'] : (float)$p['last_cost'];
            $line = money_round($qty * $price);
            $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, unit_cost, line_total) VALUES (?,?,?,?,?,?)')
                ->execute([$saleId, $pid, $qty, $price, round($unitCost, 4), $line]);
            $subtotal += $line;
            $cogs += money_round($qty * $unitCost);
        }
        $subtotal = money_round($subtotal);
        $discAmount = money_round($subtotal * $discPct / 100);
        $total = money_round($subtotal - $discAmount);
        $pdo->prepare('UPDATE sales SET number = ?, subtotal = ?, discount_amount = ?, total = ?, cogs = ? WHERE id = ?')
            ->execute([$number, $subtotal, $discAmount, $total, money_round($cogs), $saleId]);

        if ($methodId > 0) {
            $payAmount = isset($d['pay_amount']) && $d['pay_amount'] !== null && (float)$d['pay_amount'] > 0
                ? min(money_round($d['pay_amount']), $total) : $total;
            sale_add_payment($saleId, $methodId, $payAmount, $d['received'] ?? null, (string)($d['reference'] ?? ''), $when);
        }
        audit('venta_creada', 'sales', $saleId, $number . ' por ' . money($total));
        return $saleId;
    });
}

/** Registra un cobro (se puede llamar varias veces para pagos parciales). */
function sale_add_payment(int $saleId, int $methodId, float $amount, $received = null, string $reference = '', ?string $when = null): int
{
    $amount = money_round($amount);
    if ($amount <= 0) throw new AppException('El importe del cobro debe ser mayor que cero.');
    $when = $when ?: now_sql();

    return with_transaction(function (PDO $pdo) use ($saleId, $methodId, $amount, $received, $reference, $when) {
        $st = $pdo->prepare('SELECT * FROM sales WHERE id = ? FOR UPDATE');
        $st->execute([$saleId]);
        $s = $st->fetch();
        if (!$s) throw new AppException('La venta no existe.');
        if ($s['status'] !== 'confirmada') throw new AppException('No se pueden registrar cobros en una venta anulada.');

        $due = money_round((float)$s['total'] - (float)$s['returned_total'] - (float)$s['paid_total']);
        if ($due <= 0) throw new AppException('La venta no tiene saldo pendiente de cobro.');
        if ($amount > $due + 0.004) throw new AppException('El cobro (' . money($amount) . ') supera el saldo pendiente (' . money($due) . ').');

        $m = q_one('SELECT * FROM payment_methods WHERE id = ? AND active = 1', [$methodId]);
        if (!$m) throw new AppException('Elegí un medio de pago válido.');

        $recv = null; $change = null;
        if ((int)$m['is_cash'] === 1 && $received !== null && $received !== '') {
            $recv = money_round($received);
            if ($recv < $amount) throw new AppException('El efectivo recibido es menor que el importe a cobrar.');
            $change = money_round($recv - $amount);
        }
        $commission = money_round($amount * (float)$m['commission_pct'] / 100);

        $pdo->prepare("INSERT INTO payments (sale_id, payment_method_id, type, amount, commission, received_amount, change_amount, reference, paid_at, user_id)
                       VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$saleId, $methodId, 'cobro', $amount, $commission, $recv, $change, mb_substr($reference, 0, 80), $when, acting_user_id()]);
        $paymentId = (int)$pdo->lastInsertId();

        // Un único ingreso por cada cobro (payment_id es UNIQUE en cash_movements: no se puede duplicar)
        cash_insert([
            'mov_date' => substr($when, 0, 10), 'type' => 'ingreso', 'concept' => 'Cobro de venta ' . $s['number'] . ' (' . $m['name'] . ')',
            'category' => 'Ventas', 'amount' => $amount, 'status' => 'cobrado', 'payment_method_id' => $methodId,
            'source' => 'venta', 'sale_id' => $saleId, 'payment_id' => $paymentId, 'reference' => $reference,
        ]);
        $pdo->prepare('UPDATE sales SET paid_total = paid_total + ? WHERE id = ?')->execute([$amount, $saleId]);
        sale_refresh_payment_status($saleId);
        audit('cobro_registrado', 'sales', $saleId, money($amount) . ' por ' . $m['name']);
        return $paymentId;
    });
}

/** Registra un reembolso al cliente (pago de tipo "reembolso" + egreso en caja). */
function sale_refund(int $saleId, string $saleNumber, int $methodId, float $amount, string $note, ?string $when = null): int
{
    $amount = money_round($amount);
    $m = q_one('SELECT id, name FROM payment_methods WHERE id = ? AND active = 1', [$methodId]);
    if (!$m) throw new AppException('Elegí el medio de pago con el que se devuelve el dinero.');
    $when = $when ?: now_sql();
    q("INSERT INTO payments (sale_id, payment_method_id, type, amount, commission, reference, paid_at, user_id) VALUES (?,?,?,?,?,?,?,?)",
      [$saleId, $methodId, 'reembolso', $amount, 0, mb_substr($note, 0, 80), $when, acting_user_id()]);
    $paymentId = (int)db()->lastInsertId();
    cash_insert([
        'mov_date' => substr($when, 0, 10), 'type' => 'egreso', 'concept' => 'Reembolso de venta ' . $saleNumber . ' (' . $m['name'] . ')',
        'category' => 'Devoluciones a clientes', 'amount' => $amount, 'status' => 'pagado', 'payment_method_id' => $methodId,
        'cost_type' => 'no_aplica', 'source' => 'devolucion', 'sale_id' => $saleId, 'payment_id' => $paymentId,
        'paid_date' => substr($when, 0, 10), 'reference' => $note,
    ]);
    return $paymentId;
}

/**
 * Devolución (parcial o total) de productos vendidos.
 * $qtyByItem: [sale_item_id => cantidad a devolver]
 * $restock: true si el producto vuelve al stock disponible (false si está dañado).
 */
function sale_return(int $saleId, array $qtyByItem, string $reason, bool $restock, int $refundMethodId = 0, ?string $when = null): int
{
    $reason = trim($reason);
    if ($reason === '') throw new AppException('Indicá el motivo de la devolución.');
    $when = $when ?: now_sql();

    return with_transaction(function (PDO $pdo) use ($saleId, $qtyByItem, $reason, $restock, $refundMethodId, $when) {
        $st = $pdo->prepare('SELECT * FROM sales WHERE id = ? FOR UPDATE');
        $st->execute([$saleId]);
        $s = $st->fetch();
        if (!$s) throw new AppException('La venta no existe.');
        if ($s['status'] !== 'confirmada') throw new AppException('No se pueden devolver productos de una venta anulada.');

        $items = [];
        foreach (q_all('SELECT * FROM sale_items WHERE sale_id = ? FOR UPDATE', [$saleId]) as $it) $items[(int)$it['id']] = $it;

        $factor = 1 - (float)$s['discount_pct'] / 100;
        $value = 0.0; $cogsBack = 0.0; $lines = [];
        foreach ($qtyByItem as $itemId => $qty) {
            $qty = (int)$qty;
            if ($qty <= 0) continue;
            if (!isset($items[(int)$itemId])) throw new AppException('Hay un producto que no pertenece a esta venta.');
            $it = $items[(int)$itemId];
            $max = (int)$it['quantity'] - (int)$it['returned_qty'];
            if ($qty > $max) throw new AppException('No podés devolver más unidades de las vendidas (máximo ' . $max . ' de un producto).');
            $value += money_round($qty * (float)$it['unit_price'] * $factor);
            $cogsBack += money_round($qty * (float)$it['unit_cost']);
            $lines[] = [$it, $qty];
        }
        if (!$lines) throw new AppException('Indicá al menos una unidad a devolver.');

        // Si se devuelve todo lo que quedaba, el valor devuelto es exactamente el saldo (evita diferencias de centavos).
        $remainingAfter = 0;
        foreach ($items as $id => $it) {
            $ret = 0;
            foreach ($lines as $ln) if ((int)$ln[0]['id'] === $id) $ret = $ln[1];
            $remainingAfter += (int)$it['quantity'] - (int)$it['returned_qty'] - $ret;
        }
        if ($remainingAfter === 0) {
            $value = money_round((float)$s['total'] - (float)$s['returned_total']);
        }
        $value = money_round($value);

        $pdo->prepare('INSERT INTO sale_returns (sale_id, return_date, user_id, reason, total, restock) VALUES (?,?,?,?,?,?)')
            ->execute([$saleId, $when, acting_user_id(), mb_substr($reason, 0, 255), $value, $restock ? 1 : 0]);
        $returnId = (int)$pdo->lastInsertId();

        foreach ($lines as [$it, $qty]) {
            $pdo->prepare('INSERT INTO sale_return_items (return_id, sale_item_id, product_id, quantity, unit_price) VALUES (?,?,?,?,?)')
                ->execute([$returnId, $it['id'], $it['product_id'], $qty, $it['unit_price']]);
            $pdo->prepare('UPDATE sale_items SET returned_qty = returned_qty + ? WHERE id = ?')->execute([$qty, $it['id']]);
            if ($restock) {
                stock_apply((int)$it['product_id'], 'devolucion_cliente', 'entrada', $qty, 'Devolución de venta ' . $s['number'], ['sale_id' => $saleId], $when);
            }
        }
        $newCogs = $restock ? max(0, (float)$s['cogs'] - $cogsBack) : (float)$s['cogs'];
        $pdo->prepare('UPDATE sales SET returned_total = returned_total + ?, cogs = ? WHERE id = ?')->execute([$value, money_round($newCogs), $saleId]);

        // ¿hay que devolver dinero? (lo cobrado que excede lo que el cliente realmente debe ahora)
        $fresh = q_one('SELECT total, returned_total, paid_total FROM sales WHERE id = ?', [$saleId]);
        $refund = money_round((float)$fresh['paid_total'] - ((float)$fresh['total'] - (float)$fresh['returned_total']));
        if ($refund > 0) {
            sale_refund($saleId, $s['number'], $refundMethodId, $refund, 'Devolución: ' . $reason, $when);
            $pdo->prepare('UPDATE sales SET paid_total = paid_total - ? WHERE id = ?')->execute([$refund, $saleId]);
            $pdo->prepare('UPDATE sale_returns SET refund_amount = ?, refund_method_id = ? WHERE id = ?')->execute([$refund, $refundMethodId, $returnId]);
        }
        sale_refresh_payment_status($saleId);
        audit('venta_devolucion', 'sales', $saleId, $s['number'] . ' devolución por ' . money($value) . ' (' . ($restock ? 'reingresa al stock' : 'no reingresa') . ')');
        return $returnId;
    });
}

/** Anula una venta completa: devuelve el stock pendiente, reembolsa lo cobrado y conserva el registro. */
function sale_void(int $saleId, string $reason, int $refundMethodId = 0, ?string $when = null): void
{
    $reason = trim($reason);
    if ($reason === '') throw new AppException('Indicá el motivo de la anulación.');
    $when = $when ?: now_sql();

    with_transaction(function (PDO $pdo) use ($saleId, $reason, $refundMethodId, $when) {
        $st = $pdo->prepare('SELECT * FROM sales WHERE id = ? FOR UPDATE');
        $st->execute([$saleId]);
        $s = $st->fetch();
        if (!$s) throw new AppException('La venta no existe.');
        if ($s['status'] === 'anulada') throw new AppException('La venta ya estaba anulada.');

        foreach (q_all('SELECT * FROM sale_items WHERE sale_id = ?', [$saleId]) as $it) {
            $rest = (int)$it['quantity'] - (int)$it['returned_qty'];
            if ($rest > 0) {
                stock_apply((int)$it['product_id'], 'anulacion_venta', 'entrada', $rest, 'Anulación de venta ' . $s['number'], ['sale_id' => $saleId], $when);
            }
        }
        $paid = money_round($s['paid_total']);
        if ($paid > 0) {
            sale_refund($saleId, $s['number'], $refundMethodId, $paid, 'Anulación: ' . $reason, $when);
        }
        $pdo->prepare("UPDATE sales SET status = 'anulada', paid_total = 0, payment_status = 'pendiente', voided_at = ?, voided_by = ?, void_reason = ? WHERE id = ?")
            ->execute([$when, acting_user_id(), mb_substr($reason, 0, 255), $saleId]);
        audit('venta_anulada', 'sales', $saleId, $s['number'] . ': ' . $reason);
    });
}
