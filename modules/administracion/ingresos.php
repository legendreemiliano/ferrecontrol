<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('admin');

$methods = q_all('SELECT id, name FROM payment_methods WHERE active = 1 ORDER BY id');

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 20);
        if ($action === 'add') {
            $date = post_date('mov_date');
            $concept = post_str('concept', 200);
            $category = post_str('category', 80) ?: 'Otros ingresos';
            $amount = post_dec('amount');
            $methodId = post_int('payment_method_id');
            if (!$date) throw new AppException('La fecha no es válida.');
            if ($concept === '') throw new AppException('Escribí el concepto del ingreso.');
            if ($amount <= 0) throw new AppException('El importe debe ser mayor que cero.');
            if (!q_one('SELECT id FROM payment_methods WHERE id = ? AND active = 1', [$methodId])) throw new AppException('Elegí el medio de pago.');
            $id = cash_insert(['mov_date' => $date, 'type' => 'ingreso', 'concept' => $concept, 'category' => $category, 'amount' => $amount,
                               'status' => 'cobrado', 'payment_method_id' => $methodId, 'source' => 'manual', 'notes' => post_str('notes', 500)]);
            audit('ingreso_manual', 'cash_movements', $id, $concept . ' ' . money($amount));
            flash('success', 'Ingreso registrado.');
        } elseif ($action === 'void') {
            $id = post_int('id');
            $m = q_one("SELECT * FROM cash_movements WHERE id = ? AND type = 'ingreso'", [$id]);
            if (!$m) throw new AppException('El ingreso no existe.');
            if ($m['source'] !== 'manual') throw new AppException('Los ingresos generados por ventas se corrigen desde la venta (anulación o devolución), no desde acá.');
            q("UPDATE cash_movements SET status = 'anulado' WHERE id = ?", [$id]);
            audit('ingreso_anulado', 'cash_movements', $id, $m['concept']);
            flash('success', 'Ingreso anulado. Queda en el historial.');
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/administracion/ingresos.php' . qs());
}

[$from, $to] = date_range_params();
$src = get_str('origen', 10);
$st  = get_str('estado', 10);
$qtext = get_str('q', 60);
$where = ["c.type = 'ingreso'", 'c.mov_date BETWEEN :f AND :t'];
$p = [':f' => $from, ':t' => $to];
if (in_array($src, ['venta', 'manual'], true)) { $where[] = 'c.source = :src'; $p[':src'] = $src; }
if (in_array($st, ['cobrado', 'anulado'], true)) { $where[] = 'c.status = :st'; $p[':st'] = $st; }
if ($qtext !== '') { $where[] = '(c.concept LIKE :q1 OR c.category LIKE :q2 OR c.reference LIKE :q3)'; $p[':q1'] = $p[':q2'] = $p[':q3'] = "%$qtext%"; }
$w = implode(' AND ', $where);

[$sk, $sd, $order] = sort_params(['date' => 'c.mov_date', 'concept' => 'c.concept', 'amount' => 'c.amount'], 'date', 'desc');
$pg = paginate('c.*, pm.name AS method_name, u.full_name AS user_name, s.number AS sale_number',
    "cash_movements c LEFT JOIN payment_methods pm ON pm.id = c.payment_method_id JOIN users u ON u.id = c.user_id LEFT JOIN sales s ON s.id = c.sale_id WHERE $w", $p, "$order, c.id DESC");
$tot = (float)q_val("SELECT COALESCE(SUM(c.amount),0) FROM cash_movements c WHERE $w AND c.status = 'cobrado'", $p);
$pending = (float)q_val("SELECT COALESCE(SUM(total - returned_total - paid_total),0) FROM sales WHERE status = 'confirmada' AND payment_status <> 'pagada' AND DATE(sale_date) BETWEEN ? AND ?", [$from, $to]);

$pageTitle = 'Ingresos';
$active = 'adm.ingresos';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head">
  <h1>Ingresos</h1>
  <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#mAdd"><i class="bi bi-plus-lg"></i> Ingreso manual</button>
</div>

<form class="row g-2 mb-3 filters" method="get">
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Origen</label>
    <select class="form-select form-select-sm" name="origen"><option value="">Todos</option><option value="venta" <?= $src === 'venta' ? 'selected' : '' ?>>Ventas</option><option value="manual" <?= $src === 'manual' ? 'selected' : '' ?>>Manuales</option></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Estado</label>
    <select class="form-select form-select-sm" name="estado"><option value="">Todos</option><option value="cobrado" <?= $st === 'cobrado' ? 'selected' : '' ?>>Cobrado</option><option value="anulado" <?= $st === 'anulado' ? 'selected' : '' ?>>Anulado</option></select></div>
  <div class="col-8 col-md-2"><label class="form-label small mb-0">Buscar</label><input class="form-control form-control-sm" name="q" value="<?= e($qtext) ?>" placeholder="Concepto, categoría…"></div>
  <div class="col-4 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="fc-stat"><div class="lbl">Ingresos cobrados (filtro)</div><div class="val"><?= e(money($tot)) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="fc-stat danger"><div class="lbl">Ventas pendientes de cobro</div><div class="val"><?= e(money($pending)) ?></div><div class="sub">En el período, no es ingreso todavía</div></div></div>
</div>

<div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
  <thead><tr><?= sort_th('date', 'Fecha', $sk, $sd) ?><?= sort_th('concept', 'Concepto', $sk, $sd) ?><th>Categoría</th><th>Origen</th><th>Medio</th><th>Registró</th><?= sort_th('amount', 'Importe', $sk, $sd, 'text-end') ?><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($pg['rows'] as $r): ?>
    <tr class="<?= $r['status'] === 'anulado' ? 'text-decoration-line-through text-muted' : '' ?>">
      <td><?= e(date_ar($r['mov_date'])) ?></td>
      <td><?= e($r['concept']) ?><?php if ($r['sale_id']): ?> <a class="small" href="<?= e(url('modules/ventas/ver.php?id=' . $r['sale_id'])) ?>">(ver venta <?= e($r['sale_number']) ?>)</a><?php endif; ?></td>
      <td><?= e($r['category']) ?></td>
      <td><?= $r['source'] === 'venta' ? badge('Venta', 'primary') : badge('Manual', 'secondary') ?></td>
      <td><?= e($r['method_name'] ?? '—') ?></td><td class="small"><?= e($r['user_name']) ?></td>
      <td class="text-end"><?= e(money($r['amount'])) ?></td><td><?= status_badge($r['status']) ?></td>
      <td class="text-end"><?php if ($r['source'] === 'manual' && $r['status'] !== 'anulado'): ?>
        <form method="post" class="d-inline" data-confirm="¿Anular este ingreso? Quedará registrado como anulado."><?= csrf_field() ?><input type="hidden" name="action" value="void"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="btn btn-sm btn-outline-danger" title="Anular"><i class="bi bi-x-circle"></i></button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="9" class="text-center text-muted py-4">No hay ingresos para los filtros elegidos.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= render_pagination($pg) ?>

<div class="modal fade" id="mAdd" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form method="post" class="modal-content">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="modal-header"><h5 class="modal-title">Registrar ingreso manual</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="fc-note">Los cobros de ventas se generan solos. Usá esto solo para otros ingresos (servicios, intereses, ajustes de caja).</p>
    <div class="mb-2"><label class="form-label required">Fecha</label><input type="date" class="form-control" name="mov_date" value="<?= e(today()) ?>" max="<?= e(today()) ?>" required></div>
    <div class="mb-2"><label class="form-label required">Concepto</label><input class="form-control" name="concept" maxlength="200" required></div>
    <div class="row"><div class="col-6 mb-2"><label class="form-label">Categoría</label><input class="form-control" name="category" list="cats" maxlength="80" placeholder="Otros ingresos">
      <datalist id="cats"><option>Servicios</option><option>Otros ingresos</option><option>Intereses</option><option>Ajuste de caja</option></datalist></div>
    <div class="col-6 mb-2"><label class="form-label required">Importe</label><input class="form-control" name="amount" type="number" step="0.01" min="0.01" required></div></div>
    <div class="mb-2"><label class="form-label required">Medio de pago</label><select class="form-select" name="payment_method_id" required><?= options($methods, 'id', 'name', null, 'Elegir…') ?></select></div>
    <div class="mb-2"><label class="form-label">Observaciones</label><textarea class="form-control" name="notes" rows="2" maxlength="500"></textarea></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar ingreso</button></div>
</form></div></div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
