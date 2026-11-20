<?php
/** Servicio de stock: TODA modificación de existencias pasa por stock_apply(). */

/**
 * Aplica un movimiento de stock y lo registra en stock_movements.
 * - Bloquea la fila del producto (FOR UPDATE) para evitar condiciones de carrera.
 * - Rechaza el movimiento si el stock resultante sería negativo.
 * - Se ejecuta dentro de una transacción: si algo falla, no queda nada a medias.
 *
 * $ref admite: purchase_id, sale_id, count_id.
 */
function stock_apply(int $productId, string $type, string $dir, int $qty, string $reason = '', array $ref = [], ?string $when = null): array
{
    if ($qty <= 0) {
        throw new AppException('La cantidad debe ser mayor que cero.');
    }
    if (!in_array($dir, ['entrada', 'salida'], true)) {
        throw new InvalidArgumentException('Dirección de movimiento inválida.');
    }
    $when = $when ?: now_sql();
    $uid = acting_user_id();

    return with_transaction(function (PDO $pdo) use ($productId, $type, $dir, $qty, $reason, $ref, $when, $uid) {
        $st = $pdo->prepare('SELECT id, name, stock FROM products WHERE id = ? FOR UPDATE');
        $st->execute([$productId]);
        $p = $st->fetch();
        if (!$p) {
            throw new AppException('El producto indicado no existe.');
        }
        $before = (int)$p['stock'];
        $after  = $dir === 'entrada' ? $before + $qty : $before - $qty;
        if ($after < 0) {
            throw new AppException(sprintf('Stock insuficiente de «%s»: disponible %d, solicitado %d.', $p['name'], $before, $qty));
        }
        $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$after, $productId]);
        $pdo->prepare('INSERT INTO stock_movements
            (product_id, mov_type, direction, quantity, stock_before, stock_after, reason, purchase_id, sale_id, inventory_count_id, user_id, mov_datetime)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $productId, $type, $dir, $qty, $before, $after, mb_substr($reason, 0, 255),
                $ref['purchase_id'] ?? null, $ref['sale_id'] ?? null, $ref['count_id'] ?? null, $uid, $when,
            ]);
        return ['before' => $before, 'after' => $after, 'name' => $p['name']];
    });
}

/** Etiquetas legibles de los tipos de movimiento. */
function stock_type_label(string $t): string
{
    $m = [
        'compra' => 'Compra', 'venta' => 'Venta', 'devolucion_cliente' => 'Devolución de cliente',
        'devolucion_proveedor' => 'Devolución a proveedor', 'ajuste_entrada' => 'Ajuste (entrada)',
        'ajuste_salida' => 'Ajuste (salida)', 'rotura' => 'Rotura / pérdida', 'anulacion_compra' => 'Anulación de compra',
        'anulacion_venta' => 'Anulación de venta', 'inventario_fisico' => 'Inventario físico',
    ];
    return $m[$t] ?? $t;
}

/** Productos activos con stock igual o menor al mínimo. */
function low_stock_products(int $limit = 0): array
{
    $sql = 'SELECT p.id, p.code, p.name, p.stock, p.min_stock, p.unit, s.name AS supplier_name
            FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.active = 1 AND p.stock <= p.min_stock
            ORDER BY (p.stock - p.min_stock) ASC, p.name ASC' . ($limit > 0 ? ' LIMIT ' . (int)$limit : '');
    return q_all($sql);
}

function product_by_id(int $id): ?array
{
    return q_one('SELECT * FROM products WHERE id = ?', [$id]);
}
