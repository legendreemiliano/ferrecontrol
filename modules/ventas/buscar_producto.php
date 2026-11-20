<?php
/** Búsqueda de productos para el punto de venta (devuelve JSON). */
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
if (!can('sales.create')) json_out(['error' => 'Sin permiso'], 403);
$q = get_str('q', 60);
if (mb_strlen($q) < 1) json_out([]);
$like = '%' . $q . '%';
$rows = q_all('SELECT id, code, name, unit, sale_price, stock FROM products
               WHERE active = 1 AND (name LIKE :a OR code LIKE :b OR barcode LIKE :c OR brand LIKE :d)
               ORDER BY (code = :e OR barcode = :f) DESC, name LIMIT 10',
              [':a' => $like, ':b' => $like, ':c' => $like, ':d' => $like, ':e' => $q, ':f' => $q]);
json_out(array_map(function ($r) {
    return ['id' => (int)$r['id'], 'code' => $r['code'], 'name' => $r['name'], 'unit' => $r['unit'], 'price' => (float)$r['sale_price'], 'stock' => (int)$r['stock']];
}, $rows));
