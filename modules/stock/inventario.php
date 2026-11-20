<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('inventory.count');

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 12);
        if ($action === 'create') {
            $date = post_date('count_date', today());
            $counted = $_POST['counted'] ?? []; $reasons = $_POST['reason'] ?? [];
            $items = [];
            foreach ($counted as $pid => $val) {
                if (!is_string($val) || trim($val) === '') continue;
                $n = filter_var($val, FILTER_VALIDATE_INT);
                if ($n === false || $n < 0) throw new AppException('Las cantidades contadas deben ser números enteros iguales o mayores que cero.');
                $items[(int)$pid] = [(int)$n, is_array($reasons) && isset($reasons[$pid]) && is_string($reasons[$pid]) ? mb_substr(trim($reasons[$pid]), 0, 255) : ''];
            }
            if (!$items) throw new AppException('Cargá la cantidad contada de al menos un producto.');
            $id = with_transaction(function () use ($date, $items) {
                q('INSERT INTO inventory_counts (count_date, user_id, status, notes) VALUES (?,?,?,?)', [$date, acting_user_id(), 'pendiente', post_str('notes', 255)]);
                $cid = (int)db()->lastInsertId();
                foreach ($items as $pid => [$cnt, $reason]) {
                    $p = q_one('SELECT stock FROM products WHERE id = ?', [$pid]);
                    if (!$p) throw new AppException('Hay un producto inválido en el recuento.');
                    q('INSERT INTO inventory_count_items (count_id, product_id, system_stock, counted_stock, difference, reason) VALUES (?,?,?,?,?,?)',
                      [$cid, $pid, $p['stock'], $cnt, $cnt - (int)$p['stock'], $reason]);
                }
                audit('inventario_creado', 'inventory_counts', $cid, count($items) . ' productos contados');
                return $cid;
            });
            flash('success', 'Recuento guardado. Queda pendiente de autorización del administrador.');
            redirect('modules/stock/inventario.php?id=' . $id);
        } elseif ($action === 'confirm' || $action === 'reject') {
            require_perm('inventory.approve');
            $cid = post_int('id');
            with_transaction(function () use ($cid, $action) {
                $c = q_one('SELECT * FROM inventory_counts WHERE id = ? FOR UPDATE', [$cid]);
                if (!$c) throw new AppException('El recuento no existe.');
                if ($c['status'] !== 'pendiente') throw new AppException('Este recuento ya fue resuelto.');
                if ($action === 'confirm') {
                    foreach (q_all('SELECT * FROM inventory_count_items WHERE count_id = ?', [$cid]) as $it) {
                        $diff = (int)$it['difference'];
                        if ($diff === 0) continue;
                        stock_apply((int)$it['product_id'], 'inventario_fisico', $diff > 0 ? 'entrada' : 'salida', abs($diff),
                                    'Inventario físico n.º ' . $cid . ($it['reason'] ? ': ' . $it['reason'] : ''), ['count_id' => $cid]);
                    }
                }
                q('UPDATE inventory_counts SET status = ?, confirmed_by = ?, confirmed_at = ? WHERE id = ?',
                  [$action === 'confirm' ? 'confirmado' : 'rechazado', acting_user_id(), now_sql(), $cid]);
                audit($action === 'confirm' ? 'inventario_confirmado' : 'inventario_rechazado', 'inventory_counts', $cid, '');
            });
            flash('success', $action === 'confirm' ? 'Recuento confirmado: el stock fue ajustado.' : 'Recuento rechazado: no se modificó el stock.');
            redirect('modules/stock/inventario.php?id=' . post_int('id'));
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/stock/inventario.php' . (get_int('id') ? '?id=' . get_int('id') : (isset($_GET['nuevo']) ? '?nuevo=1' : '')));
}

$id = get_int('id');
$count = $id ? q_one('SELECT c.*, u.full_name, cu.full_name AS confirmer FROM inventory_counts c JOIN users u ON u.id = c.user_id LEFT JOIN users cu ON cu.id = c.confirmed_by WHERE c.id = ?', [$id]) : null;
$pageTitle = 'Inventario físico';
$active = 'stk.inventario';
include ROOT_DIR . '/includes/header.php';

if ($count):
    $items = q_all('SELECT i.*, p.code, p.name, p.stock AS current_stock FROM inventory_count_items i JOIN products p ON p.id = i.product_id WHERE i.count_id = ? ORDER BY p.name', [$id]); ?>
<div class="fc-page-head"><h1>Recuento n.º <?= (int)$count['id'] ?> <?= status_badge($count['status']) ?></h1><a class="btn btn-light" href="<?= e(url('modules/stock/inventario.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a></div>
<p class="text-muted">Fecha: <?= e(date_ar($count['count_date'])) ?> · Cargado por <?= e($count['full_name']) ?><?= $count['confirmer'] ? ' · Resuelto por ' . e($count['confirmer']) . ' el ' . e(datetime_ar($count['confirmed_at'])) : '' ?><?= $count['notes'] ? ' · ' . e($count['notes']) : '' ?></p>
<div class="fc-card mb-3"><div class="table-responsive"><table class="table table-sm mb-0 fc-table">
  <thead><tr><th>Producto</th><th class="text-end">Stock registrado</th><th class="text-end">Cantidad contada</th><th class="text-end">Diferencia</th><th>Motivo</th></tr></thead><tbody>
  <?php foreach ($items as $i): $d = (int)$i['difference']; ?>
    <tr><td><?= e($i['name']) ?> <small class="text-muted"><?= e($i['code']) ?></small></td><td class="text-end"><?= num($i['system_stock']) ?></td><td class="text-end"><?= num($i['counted_stock']) ?></td>
      <td class="text-end fw-bold <?= $d < 0 ? 'text-danger' : ($d > 0 ? 'text-success' : '') ?>"><?= $d > 0 ? '+' : '' ?><?= num($d) ?></td><td class="small"><?= e($i['reason']) ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php if ($count['status'] === 'pendiente'): ?>
  <?php if (can('inventory.approve')): ?>
    <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$count['id'] ?>"><input type="hidden" name="action" value="confirm">
      <button class="btn btn-primary" data-confirm="¿Autorizar el recuento? Se ajustará el stock con las diferencias indicadas."><i class="bi bi-check2-circle"></i> Confirmar y ajustar stock</button></form>
    <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$count['id'] ?>"><input type="hidden" name="action" value="reject">
      <button class="btn btn-outline-danger" data-confirm="¿Rechazar el recuento? No se modificará el stock."><i class="bi bi-x-circle"></i> Rechazar</button></form>
  <?php else: ?><div class="fc-note">Este recuento espera la autorización del administrador. Hasta entonces el stock no se modifica.</div><?php endif; ?>
<?php endif; ?>

<?php elseif (isset($_GET['nuevo'])):
    $products = q_all('SELECT id, code, name, stock, unit, location FROM products WHERE active = 1 ORDER BY location, name'); ?>
<div class="fc-page-head"><h1>Nuevo recuento físico</h1><a class="btn btn-light" href="<?= e(url('modules/stock/inventario.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <div class="row g-2 mb-3"><div class="col-md-3"><label class="form-label">Fecha del recuento</label><input type="date" class="form-control" name="count_date" value="<?= e(today()) ?>" max="<?= e(today()) ?>"></div>
    <div class="col-md-9"><label class="form-label">Observaciones</label><input class="form-control" name="notes" maxlength="255"></div></div>
  <div class="fc-note mb-3">Completá solo los productos que contaste. Los que dejes vacíos no se incluyen. Las diferencias se calculan contra el stock registrado y requieren autorización del administrador.</div>
  <div class="fc-card"><div class="table-responsive"><table class="table table-sm mb-0 fc-table">
    <thead><tr><th>Ubicación</th><th>Producto</th><th class="text-end">Stock registrado</th><th style="width:140px">Cantidad contada</th><th>Motivo de la diferencia</th></tr></thead><tbody>
    <?php foreach ($products as $p): ?><tr><td><?= e($p['location']) ?></td><td><?= e($p['name']) ?> <small class="text-muted"><?= e($p['code']) ?></small></td><td class="text-end"><?= num($p['stock']) ?></td>
      <td><input class="form-control form-control-sm" type="number" min="0" step="1" name="counted[<?= (int)$p['id'] ?>]"></td><td><input class="form-control form-control-sm" name="reason[<?= (int)$p['id'] ?>]" maxlength="255"></td></tr>
    <?php endforeach; ?></tbody></table></div></div>
  <button class="btn btn-primary mt-3">Guardar recuento</button>
</form>

<?php else:
    $counts = q_all('SELECT c.*, u.full_name, (SELECT COUNT(*) FROM inventory_count_items i WHERE i.count_id = c.id) AS n_items,
                     (SELECT COUNT(*) FROM inventory_count_items i WHERE i.count_id = c.id AND i.difference <> 0) AS n_diff
                     FROM inventory_counts c JOIN users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT 50'); ?>
<div class="fc-page-head"><h1>Inventario físico</h1><a class="btn btn-accent" href="?nuevo=1"><i class="bi bi-plus-lg"></i> Nuevo recuento</a></div>
<div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
  <thead><tr><th>N.º</th><th>Fecha</th><th>Cargado por</th><th class="text-end">Productos</th><th class="text-end">Con diferencia</th><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($counts as $c): ?><tr><td>#<?= (int)$c['id'] ?></td><td><?= e(date_ar($c['count_date'])) ?></td><td><?= e($c['full_name']) ?></td><td class="text-end"><?= (int)$c['n_items'] ?></td><td class="text-end"><?= (int)$c['n_diff'] ?></td>
    <td><?= status_badge($c['status']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?id=<?= (int)$c['id'] ?>">Ver</a></td></tr>
  <?php endforeach; if (!$counts): ?><tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay recuentos.</td></tr><?php endif; ?></tbody></table></div></div>
<?php endif; ?>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
