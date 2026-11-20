<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('admin');

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 10); $id = post_int('id'); $name = post_str('name', 80);
        if ($name === '') throw new AppException('La categoría necesita un nombre.');
        if (q_one('SELECT id FROM categories WHERE name = ? AND id <> ?', [$name, $id])) throw new AppException('Ya existe una categoría con ese nombre.');
        if ($action === 'add') {
            q('INSERT INTO categories (name) VALUES (?)', [$name]);
            audit('categoria_creada', 'categories', (int)db()->lastInsertId(), $name);
            flash('success', 'Categoría agregada.');
        } else {
            $act = isset($_POST['active']) ? 1 : 0;
            q('UPDATE categories SET name = ?, active = ? WHERE id = ?', [$name, $act, $id]);
            audit('categoria_editada', 'categories', $id, $name);
            flash('success', 'Categoría guardada.');
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/stock/categorias.php');
}
$cats = q_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS prod_count FROM categories c ORDER BY c.name');

$pageTitle = 'Categorías de productos';
$active = 'stk.categorias';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Categorías de productos</h1></div>
<div class="row g-3"><div class="col-lg-7"><div class="fc-card"><div class="fc-card-b">
  <?php foreach ($cats as $c): ?>
    <form method="post" class="row g-2 align-items-center mb-2"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <div class="col-6"><input class="form-control form-control-sm" name="name" maxlength="80" value="<?= e($c['name']) ?>" required></div>
      <div class="col-3 small text-muted"><?= (int)$c['prod_count'] ?> producto(s)</div>
      <div class="col-3 d-flex gap-2 align-items-center"><div class="form-check m-0"><input class="form-check-input" type="checkbox" name="active" <?= $c['active'] ? 'checked' : '' ?>><label class="form-check-label small">Activa</label></div>
        <button class="btn btn-sm btn-outline-primary" title="Guardar"><i class="bi bi-check-lg"></i></button></div></form>
  <?php endforeach; ?>
  <hr><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="id" value="0">
    <div class="col-8"><input class="form-control form-control-sm" name="name" maxlength="80" placeholder="Nueva categoría" required></div><div class="col-4"><button class="btn btn-sm btn-primary w-100">Agregar</button></div></form>
</div></div></div>
<div class="col-lg-5"><div class="fc-note">Las categorías no se eliminan: si ya no se usan, desmarcá «Activa» y dejan de ofrecerse al crear productos, pero el historial se conserva.</div></div></div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
