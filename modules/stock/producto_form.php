<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
$id = get_int('id');
require_perm($id ? 'products.edit' : 'products.create');

$cats = q_all('SELECT id, name FROM categories WHERE active = 1 ORDER BY name');
$sups = q_all('SELECT id, name FROM suppliers WHERE active = 1 ORDER BY name');
$units = ['unidad', 'caja', 'bolsa', 'kg', 'metro', 'litro', 'rollo', 'par', 'lata', 'balde', 'juego'];
$prod = $id ? product_by_id($id) : null;
if ($id && !$prod) { flash('error', 'El producto no existe.'); redirect('modules/stock/index.php'); }
$canPrice = can('prices.edit');

if (is_post()) {
    csrf_check();
    try {
        $code = strtoupper(post_str('code', 30)); $barcode = post_str('barcode', 40); $name = post_str('name', 150);
        $desc = post_str('description', 1000); $catId = post_int('category_id'); $brand = post_str('brand', 60);
        $unit = post_str('unit', 20) ?: 'unidad'; $min = post_int('min_stock'); $loc = post_str('location', 60); $supId = post_int('supplier_id');
        if ($code === '' || !preg_match('/^[A-Z0-9._-]{2,30}$/', $code)) throw new AppException('El código interno es obligatorio (letras, números, punto o guion; entre 2 y 30 caracteres).');
        if ($name === '') throw new AppException('El nombre es obligatorio.');
        if (!q_one('SELECT id FROM categories WHERE id = ?', [$catId])) throw new AppException('Elegí una categoría.');
        if ($min < 0) throw new AppException('El stock mínimo no puede ser negativo.');
        if (q_one('SELECT id FROM products WHERE code = ? AND id <> ?', [$code, $id])) throw new AppException('Ya existe un producto con ese código interno.');
        if ($barcode !== '' && q_one('SELECT id FROM products WHERE barcode = ? AND id <> ?', [$barcode, $id])) throw new AppException('Ya existe un producto con ese código de barras.');
        $supVal = $supId > 0 ? $supId : null;
        $barVal = $barcode !== '' ? $barcode : null;

        if ($prod) {
            $price = $canPrice ? post_dec('sale_price') : (float)$prod['sale_price'];
            if ($price < 0) throw new AppException('El precio no puede ser negativo.');
            q('UPDATE products SET code=?, barcode=?, name=?, description=?, category_id=?, brand=?, unit=?, min_stock=?, location=?, supplier_id=?, sale_price=? WHERE id=?',
              [$code, $barVal, $name, $desc, $catId, $brand, $unit, $min, $loc, $supVal, $price, $id]);
            if ($canPrice && abs($price - (float)$prod['sale_price']) > 0.004) {
                audit('precio_actualizado', 'products', $id, $name . ': ' . money($prod['sale_price']) . ' → ' . money($price));
            }
            audit('producto_editado', 'products', $id, $code . ' ' . $name);
            flash('success', 'Producto actualizado.');
            redirect('modules/stock/producto_ver.php?id=' . $id);
        }

        // alta: el precio inicial lo puede cargar quien crea el producto; después solo el administrador lo cambia
        $price = post_dec('sale_price'); $initStock = post_int('initial_stock'); $initCost = post_dec('initial_cost');
        if ($price < 0) throw new AppException('El precio no puede ser negativo.');
        if ($initStock < 0) throw new AppException('El stock inicial no puede ser negativo.');
        if ($initStock > 0 && $initCost <= 0) throw new AppException('Si cargás stock inicial, indicá también el costo unitario.');
        $newId = with_transaction(function () use ($code, $barVal, $name, $desc, $catId, $brand, $unit, $min, $loc, $supVal, $price, $initStock, $initCost) {
            q('INSERT INTO products (code, barcode, name, description, category_id, brand, unit, min_stock, location, supplier_id, sale_price, last_cost, avg_cost) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [$code, $barVal, $name, $desc, $catId, $brand, $unit, $min, $loc, $supVal, $price, $initStock > 0 ? $initCost : 0, $initStock > 0 ? $initCost : 0]);
            $nid = (int)db()->lastInsertId();
            if ($initStock > 0) stock_apply($nid, 'ajuste_entrada', 'entrada', $initStock, 'Stock inicial al crear el producto');
            audit('producto_creado', 'products', $nid, $code . ' ' . $name);
            return $nid;
        });
        flash('success', 'Producto creado.');
        redirect('modules/stock/producto_ver.php?id=' . $newId);
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
}

$v = function ($k, $d = '') use ($prod) { return old($k, $prod[$k] ?? $d); };
$pageTitle = $prod ? 'Editar producto' : 'Nuevo producto';
$active = 'stk.productos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1><?= e($pageTitle) ?></h1><a class="btn btn-light" href="<?= e(url('modules/stock/index.php')) ?>"><i class="bi bi-arrow-left"></i> Volver</a></div>
<form method="post" class="fc-card" style="max-width:900px"><div class="fc-card-b"><?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-3"><label class="form-label required">Código interno</label><input class="form-control" name="code" maxlength="30" required value="<?= e($v('code')) ?>"></div>
    <div class="col-md-3"><label class="form-label">Código de barras</label><input class="form-control" name="barcode" maxlength="40" value="<?= e($v('barcode')) ?>"></div>
    <div class="col-md-6"><label class="form-label required">Nombre</label><input class="form-control" name="name" maxlength="150" required value="<?= e($v('name')) ?>"></div>
    <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="description" rows="2" maxlength="1000"><?= e($v('description')) ?></textarea></div>
    <div class="col-md-4"><label class="form-label required">Categoría</label><select class="form-select" name="category_id" required><?= options($cats, 'id', 'name', $v('category_id'), 'Elegir…') ?></select></div>
    <div class="col-md-4"><label class="form-label">Marca</label><input class="form-control" name="brand" maxlength="60" value="<?= e($v('brand')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Unidad de medida</label><select class="form-select" name="unit"><?php foreach ($units as $un): ?><option <?= $v('unit', 'unidad') === $un ? 'selected' : '' ?>><?= e($un) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Stock mínimo</label><input class="form-control" type="number" name="min_stock" min="0" step="1" value="<?= e($v('min_stock', 0)) ?>"></div>
    <div class="col-md-4"><label class="form-label">Ubicación en el depósito</label><input class="form-control" name="location" maxlength="60" value="<?= e($v('location')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Proveedor habitual</label><select class="form-select" name="supplier_id"><?= options($sups, 'id', 'name', $v('supplier_id'), '—') ?></select></div>
    <div class="col-md-4"><label class="form-label">Precio de venta (antes de impuestos)</label>
      <input class="form-control" type="number" name="sale_price" min="0" step="0.01" value="<?= e($v('sale_price', 0)) ?>" <?= ($prod && !$canPrice) ? 'disabled' : '' ?>>
      <?php if ($prod && !$canPrice): ?><div class="form-text">Solo el administrador puede modificar precios.</div><?php endif; ?></div>
    <?php if (!$prod): ?>
      <div class="col-md-4"><label class="form-label">Stock inicial (opcional)</label><input class="form-control" type="number" name="initial_stock" min="0" step="1" value="<?= e(old('initial_stock', 0)) ?>"></div>
      <div class="col-md-4"><label class="form-label">Costo unitario del stock inicial</label><input class="form-control" type="number" name="initial_cost" min="0" step="0.01" value="<?= e(old('initial_cost', 0)) ?>"></div>
    <?php else: ?>
      <div class="col-12"><div class="fc-note">El stock (<strong><?= num($prod['stock']) ?></strong>) y el costo promedio (<strong><?= e(money($prod['avg_cost'])) ?></strong>) no se editan a mano: cambian con compras, ventas y ajustes, y quedan registrados.</div></div>
    <?php endif; ?>
  </div>
  <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button> <a class="btn btn-light" href="<?= e(url('modules/stock/index.php')) ?>">Cancelar</a></div>
</div></form>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
