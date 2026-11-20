<?php
/** Informes de Producción y Stock. */

function stock_report_types(): array
{
    return [
        'stock_actual'   => 'Stock actual',
        'sin_stock'      => 'Productos sin stock',
        'stock_bajo'     => 'Productos con stock bajo',
        'mas_vendidos'   => 'Productos más vendidos',
        'rotacion'       => 'Rotación de productos',
        'sin_movimiento' => 'Productos sin movimientos recientes',
        'historial'      => 'Historial de entradas y salidas',
        'compras_prov'   => 'Compras por proveedor',
        'ventas_periodo' => 'Ventas por período',
        'valorizacion'   => 'Valorización del inventario',
    ];
}

function stock_report(string $type, string $from, string $to): array
{
    $types = stock_report_types();
    if (!isset($types[$type])) $type = 'stock_actual';
    $title = $types[$type] . (in_array($type, ['stock_actual', 'sin_stock', 'stock_bajo', 'valorizacion'], true) ? ' · al ' . date_ar(today()) : ' · ' . date_ar($from) . ' al ' . date_ar($to));
    $pcols = [['k' => 'code', 'l' => 'Código', 't' => 'text'], ['k' => 'name', 'l' => 'Producto', 't' => 'text'], ['k' => 'category', 'l' => 'Categoría', 't' => 'text']];

    switch ($type) {
        case 'stock_actual':
            $rows = q_all('SELECT p.code, p.name, c.name AS category, p.unit, p.stock, p.min_stock, p.location FROM products p JOIN categories c ON c.id = p.category_id WHERE p.active = 1 ORDER BY p.name');
            return ['title' => $title, 'note' => '', 'cols' => array_merge($pcols, [['k' => 'unit', 'l' => 'Unidad', 't' => 'text'], ['k' => 'stock', 'l' => 'Stock', 't' => 'int'], ['k' => 'min_stock', 'l' => 'Mínimo', 't' => 'int'], ['k' => 'location', 'l' => 'Ubicación', 't' => 'text']]), 'rows' => $rows];
        case 'sin_stock':
            $rows = q_all('SELECT p.code, p.name, c.name AS category, p.min_stock, s.name AS supplier FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE p.active = 1 AND p.stock = 0 ORDER BY p.name');
            return ['title' => $title, 'note' => '', 'cols' => array_merge($pcols, [['k' => 'min_stock', 'l' => 'Mínimo', 't' => 'int'], ['k' => 'supplier', 'l' => 'Proveedor habitual', 't' => 'text']]), 'rows' => $rows];
        case 'stock_bajo':
            $rows = q_all('SELECT p.code, p.name, c.name AS category, p.stock, p.min_stock, GREATEST(p.min_stock - p.stock, 0) AS missing, s.name AS supplier
                           FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE p.active = 1 AND p.stock <= p.min_stock ORDER BY (p.stock - p.min_stock), p.name');
            return ['title' => $title, 'note' => 'Stock igual o menor al mínimo.', 'cols' => array_merge($pcols, [['k' => 'stock', 'l' => 'Stock', 't' => 'int'], ['k' => 'min_stock', 'l' => 'Mínimo', 't' => 'int'], ['k' => 'missing', 'l' => 'Faltan', 't' => 'int'], ['k' => 'supplier', 'l' => 'Proveedor', 't' => 'text']]), 'rows' => $rows];
        case 'mas_vendidos':
            $rows = q_all("SELECT p.code, p.name, c.name AS category, SUM(si.quantity - si.returned_qty) AS qty,
                             SUM((si.quantity - si.returned_qty) * si.unit_price * (1 - s.discount_pct / 100)) AS revenue
                           FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id JOIN categories c ON c.id = p.category_id
                           WHERE s.status = 'confirmada' AND DATE(s.sale_date) BETWEEN ? AND ?
                           GROUP BY p.id, p.code, p.name, c.name HAVING qty > 0 ORDER BY qty DESC LIMIT 30", [$from, $to]);
            return ['title' => $title, 'note' => 'Top 30. No cuenta ventas anuladas ni unidades devueltas.', 'cols' => array_merge($pcols, [['k' => 'qty', 'l' => 'Unidades vendidas', 't' => 'int'], ['k' => 'revenue', 'l' => 'Ingreso', 't' => 'money']]), 'rows' => $rows];
        case 'rotacion':
            $rows = q_all("SELECT p.code, p.name, c.name AS category, p.stock,
                             COALESCE((SELECT SUM(si.quantity - si.returned_qty) FROM sale_items si JOIN sales s ON s.id = si.sale_id
                                       WHERE si.product_id = p.id AND s.status = 'confirmada' AND DATE(s.sale_date) BETWEEN :f AND :t), 0) AS sold
                           FROM products p JOIN categories c ON c.id = p.category_id WHERE p.active = 1 ORDER BY sold DESC, p.name", [':f' => $from, ':t' => $to]);
            $days = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);
            foreach ($rows as &$r) {
                $r['turnover'] = (float)$r['stock'] > 0 ? (float)$r['sold'] / (float)$r['stock'] : null;
                $r['cover'] = (float)$r['sold'] > 0 ? (float)$r['stock'] / ((float)$r['sold'] / $days) : null;
            }
            unset($r);
            return ['title' => $title, 'note' => 'Rotación = unidades vendidas ÷ stock actual. Cobertura = días que alcanzaría el stock actual al ritmo de ventas del período.',
                'cols' => array_merge($pcols, [['k' => 'stock', 'l' => 'Stock', 't' => 'int'], ['k' => 'sold', 'l' => 'Vendidas', 't' => 'int'], ['k' => 'turnover', 'l' => 'Rotación', 't' => 'dec'], ['k' => 'cover', 'l' => 'Cobertura (días)', 't' => 'dec']]), 'rows' => $rows];
        case 'sin_movimiento':
            $rows = q_all("SELECT p.code, p.name, c.name AS category, p.stock,
                             (SELECT MAX(DATE(m.mov_datetime)) FROM stock_movements m WHERE m.product_id = p.id AND m.mov_type = 'venta') AS last_sale
                           FROM products p JOIN categories c ON c.id = p.category_id
                           WHERE p.active = 1 AND p.stock > 0 AND NOT EXISTS (SELECT 1 FROM stock_movements m WHERE m.product_id = p.id AND DATE(m.mov_datetime) BETWEEN ? AND ?)
                           ORDER BY p.name", [$from, $to]);
            return ['title' => $title, 'note' => 'Productos con stock que no tuvieron ningún movimiento en el período elegido (posible stock inmovilizado).', 'cols' => array_merge($pcols, [['k' => 'stock', 'l' => 'Stock', 't' => 'int'], ['k' => 'last_sale', 'l' => 'Última venta', 't' => 'date']]), 'rows' => $rows];
        case 'historial':
            $rows = q_all('SELECT m.mov_datetime, p.code, p.name, m.mov_type, m.direction, m.quantity, m.stock_before, m.stock_after, m.reason FROM stock_movements m JOIN products p ON p.id = m.product_id
                           WHERE DATE(m.mov_datetime) BETWEEN ? AND ? ORDER BY m.mov_datetime DESC, m.id DESC LIMIT 2000', [$from, $to]);
            foreach ($rows as &$r) { $r['type_label'] = stock_type_label($r['mov_type']); $r['in'] = $r['direction'] === 'entrada' ? $r['quantity'] : null; $r['out'] = $r['direction'] === 'salida' ? $r['quantity'] : null; }
            unset($r);
            return ['title' => $title, 'note' => 'Más reciente primero (máximo 2000 filas).', 'cols' => [['k' => 'mov_datetime', 'l' => 'Fecha y hora', 't' => 'datetime'], ['k' => 'code', 'l' => 'Código', 't' => 'text'], ['k' => 'name', 'l' => 'Producto', 't' => 'text'], ['k' => 'type_label', 'l' => 'Tipo', 't' => 'text'],
                ['k' => 'in', 'l' => 'Entrada', 't' => 'int'], ['k' => 'out', 'l' => 'Salida', 't' => 'int'], ['k' => 'stock_before', 'l' => 'Stock anterior', 't' => 'int'], ['k' => 'stock_after', 'l' => 'Stock resultante', 't' => 'int'], ['k' => 'reason', 'l' => 'Motivo', 't' => 'text']], 'rows' => $rows];
        case 'compras_prov':
            $rows = q_all("SELECT s.name, COUNT(p.id) AS cnt, SUM(p.total) AS total FROM purchases p JOIN suppliers s ON s.id = p.supplier_id WHERE p.status = 'confirmada' AND p.purchase_date BETWEEN ? AND ? GROUP BY s.id, s.name ORDER BY total DESC", [$from, $to]);
            return ['title' => $title, 'note' => '', 'cols' => [['k' => 'name', 'l' => 'Proveedor', 't' => 'text'], ['k' => 'cnt', 'l' => 'Compras', 't' => 'int'], ['k' => 'total', 'l' => 'Total', 't' => 'money']], 'rows' => $rows];
        case 'ventas_periodo':
            $rows = q_all("SELECT DATE(sale_date) AS dia, COUNT(*) AS cnt, SUM(total) AS total, SUM(returned_total) AS ret, SUM(total - returned_total) AS net FROM sales
                           WHERE status = 'confirmada' AND DATE(sale_date) BETWEEN ? AND ? GROUP BY DATE(sale_date) ORDER BY dia DESC", [$from, $to]);
            $t = 0; $n = 0; foreach ($rows as $r) { $t += (float)$r['net']; $n += (int)$r['cnt']; }
            return ['title' => $title, 'note' => '', 'cols' => [['k' => 'dia', 'l' => 'Día', 't' => 'date'], ['k' => 'cnt', 'l' => 'Ventas', 't' => 'int'], ['k' => 'total', 'l' => 'Total vendido', 't' => 'money'], ['k' => 'ret', 'l' => 'Devuelto', 't' => 'money'], ['k' => 'net', 'l' => 'Neto', 't' => 'money']],
                'rows' => $rows, 'foot' => ['dia' => 'TOTAL', 'cnt' => $n, 'net' => $t]];
        case 'valorizacion':
            $rows = q_all('SELECT p.code, p.name, c.name AS category, p.stock, p.avg_cost, p.stock * p.avg_cost AS value FROM products p JOIN categories c ON c.id = p.category_id WHERE p.stock > 0 ORDER BY value DESC');
            $sum = 0; foreach ($rows as $r) $sum += (float)$r['value'];
            return ['title' => $title, 'note' => 'Método de valorización: costo promedio ponderado. Valor = stock × costo promedio vigente de cada producto.',
                'cols' => array_merge($pcols, [['k' => 'stock', 'l' => 'Stock', 't' => 'int'], ['k' => 'avg_cost', 'l' => 'Costo promedio', 't' => 'money'], ['k' => 'value', 'l' => 'Valor', 't' => 'money']]), 'rows' => $rows, 'foot' => ['name' => 'TOTAL DEL INVENTARIO', 'value' => $sum]];
    }
    return ['title' => $title, 'note' => '', 'cols' => [], 'rows' => []];
}
