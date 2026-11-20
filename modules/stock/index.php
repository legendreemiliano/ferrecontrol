<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('products.view');

if (is_post()) {
    csrf_check();
    require_perm('products.edit');
    try {
        $id = post_int('id');
        $p = product_by_id($id);
        if (!$p) throw new AppException('El producto no existe.');
        $new = post_str('action', 12) === 'activate' ? 1 : 0;
        q('UPDATE products SET active = ? WHERE id = ?', [$new, $id]);
        audit($new ? 'producto_activado' : 'producto_desactivado', 'products', $id, $p['code'] . ' ' . $p['name']);
        flash('success', 'Producto ' . ($new ? 'activado' : 'desactivado') . '. No se eliminan productos para conservar el historial.');
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/stock/index.php' . qs());
}

$qtext = get_str('q', 60); $cat = get_int('categoria'); $est = get_str('estado', 10) ?: 'activos'; $stk = get_str('stock', 10);
$where = ['1 = 1']; $p = [];
if ($qtext !== '') { $where[] = '(p.name LIKE :q1 OR p.code LIKE :q2 OR p.barcode LIKE :q3 OR p.brand LIKE :q4 OR c.name LIKE :q5)'; foreach ([1, 2, 3, 4, 5] as $i) $p[":q$i"] = "%$qtext%"; }
if ($cat > 0) { $where[] = 'p.category_id = :cat'; $p[':cat'] = $cat; }
if ($est === 'activos') $where[] = 'p.active = 1'; elseif ($est === 'inactivos') $where[] = 'p.active = 0';
if ($stk === 'bajo') $where[] = 'p.stock <= p.min_stock AND p.stock > 0';
if ($stk === 'sin') $where[] = 'p.stock = 0';
[$sk, $sd, $order] = sort_params(['code' => 'p.code', 'name' => 'p.name', 'category' => 'c.name', 'stock' => 'p.stock', 'price' => 'p.sale_price'], 'name');
$pg = paginate('p.*, c.name AS category_name', 'products p JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where), $p, "$order, p.id");
$cats = q_all('SELECT id, name FROM categories WHERE active = 1 ORDER BY name');

$pageTitle = 'Productos';
$active = 'stk.productos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Productos</h1>
  <?php if (can('products.create')): ?><a class="btn btn-accent" href="<?= e(url('modules/stock/producto_form.php')) ?>"><i class="bi bi-plus-lg"></i> Nuevo producto</a><?php endif; ?></div>

<form class="row g-2 mb-3 filters" method="get">
  <div class="col-12 col-md-3"><label class="form-label small mb-0">Buscar</label><input class="form-control form-control-sm" name="q" value="<?= e($qtext) ?>" placeholder="Nombre, código, marca, categoría…"></div>
  <div class="col-6 col-md-3"><label class="form-label small mb-0">Categoría</label><select class="form-select form-select-sm" name="categoria"><?= options($cats, 'id', 'name', $cat, 'Todas') ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Estado</label><select class="form-select form-select-sm" name="estado">
    <?php foreach (['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'] as $k => $l): ?><option value="<?= $k ?>" <?= $est === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Stock</label><select class="form-select form-select-sm" name="stock">
    <option value="">Todos</option><option value="bajo" <?= $stk === 'bajo' ? 'selected' : '' ?>>Stock bajo</option><option value="sin" <?= $stk === 'sin' ? 'selected' : '' ?>>Sin stock</option></select></div>
  <div class="col-6 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Buscar</button></div>
</form>

<div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
  <thead><tr><?= sort_th('code', 'Código', $sk, $sd) ?><?= sort_th('name', 'Producto', $sk, $sd) ?><?= sort_th('category', 'Categoría', $sk, $sd) ?><?= sort_th('stock', 'Stock', $sk, $sd, 'text-end') ?><th class="text-end">Mínimo</th><th class="text-end">Costo prom.</th><?= sort_th('price', 'Precio', $sk, $sd, 'text-end') ?><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($pg['rows'] as $r):
      $low = (int)$r['stock'] <= (int)$r['min_stock']; ?>
    <tr class="<?= $r['active'] ? '' : 'text-muted' ?>">
      <td><code><?= e($r['code']) ?></code></td>
      <td><a href="<?= e(url('modules/stock/producto_ver.php?id=' . $r['id'])) ?>"><?= e($r['name']) ?></a><div class="small text-muted"><?= e($r['brand']) ?> · <?= e($r['unit']) ?></div></td>
      <td><?= e($r['category_name']) ?></td>
      <td class="text-end fw-semibold <?= $low ? 'text-danger' : '' ?>"><?= num($r['stock']) ?> <?= (int)$r['stock'] === 0 ? badge('Sin stock', 'danger') : ($low ? badge('Bajo', 'warning') : '') ?></td>
      <td class="text-end"><?= num($r['min_stock']) ?></td>
      <td class="text-end"><?= e(money($r['avg_cost'])) ?></td>
      <td class="text-end"><?= e(money($r['sale_price'])) ?></td>
      <td><?= yesno_badge((bool)$r['active']) ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('modules/stock/producto_ver.php?id=' . $r['id'])) ?>" title="Ver detalle"><i class="bi bi-eye"></i></a>
        <?php if (can('products.edit')): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= e(url('modules/stock/producto_form.php?id=' . $r['id'])) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline" data-confirm="<?= $r['active'] ? '¿Desactivar este producto? Dejará de aparecer para vender, pero se conserva su historial.' : '¿Volver a activar este producto?' ?>">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="<?= $r['active'] ? 'deactivate' : 'activate' ?>">
            <button class="btn btn-sm <?= $r['active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="<?= $r['active'] ? 'Desactivar' : 'Activar' ?>"><i class="bi <?= $r['active'] ? 'bi-slash-circle' : 'bi-check-circle' ?>"></i></button>
          </form>
        <?php endif; ?>
      </td></tr>
  <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="9" class="text-center text-muted py-4">No se encontraron productos con esos filtros.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= render_pagination($pg) ?>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
