<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('admin');

$methods = q_all('SELECT id, name FROM payment_methods WHERE active = 1 ORDER BY id');
$cats = q_all('SELECT * FROM expense_categories WHERE active = 1 ORDER BY name');
$suppliers = q_all('SELECT id, name FROM suppliers WHERE active = 1 ORDER BY name');

if (is_post()) {
    csrf_check();
    try {
        $action = post_str('action', 20);
        if ($action === 'add') {
            $date = post_date('mov_date');
            $concept = post_str('concept', 200);
            $catId = post_int('category_id');
            $amount = post_dec('amount');
            $status = post_str('status', 10) === 'pendiente' ? 'pendiente' : 'pagado';
            $methodId = post_int('payment_method_id');
            $cat = q_one('SELECT * FROM expense_categories WHERE id = ? AND active = 1', [$catId]);
            if (!$date) throw new AppException('La fecha no es válida.');
            if ($concept === '') throw new AppException('Escribí la descripción del gasto.');
            if (!$cat) throw new AppException('Elegí una categoría de gasto.');
            if ($amount <= 0) throw new AppException('El importe debe ser mayor que cero.');
            if ($status === 'pagado' && !q_one('SELECT id FROM payment_methods WHERE id = ? AND active = 1', [$methodId])) throw new AppException('Elegí el medio de pago.');
            $ct = post_str('cost_type', 10);
            if (!in_array($ct, ['fijo', 'variable', 'mixto'], true)) $ct = $cat['cost_type'];
            $fixedPct = null;
            if ($ct === 'mixto') {
                $fixedPct = post_dec('fixed_pct', (float)$cat['fixed_pct']);
                if ($fixedPct < 0 || $fixedPct > 100) throw new AppException('El porcentaje fijo debe estar entre 0 y 100.');
            }
            $sup = post_int('supplier_id');
            $id = cash_insert(['mov_date' => $date, 'type' => 'egreso', 'concept' => $concept, 'category' => $cat['name'], 'amount' => $amount,
                'status' => $status, 'payment_method_id' => $status === 'pagado' ? $methodId : null, 'cost_type' => $ct, 'fixed_pct' => $fixedPct,
                'source' => 'manual', 'supplier_id' => $sup > 0 ? $sup : null, 'reference' => post_str('reference', 80),
                'paid_date' => $status === 'pagado' ? $date : null, 'notes' => post_str('notes', 500)]);
            audit('egreso_manual', 'cash_movements', $id, $concept . ' ' . money($amount));
            flash('success', 'Egreso registrado.');
        } elseif ($action === 'pay') {
            expense_mark_paid(post_int('id'), post_int('payment_method_id') ?: null, post_date('paid_date', today()));
            flash('success', 'Egreso marcado como pagado.');
        } elseif ($action === 'void') {
            $id = post_int('id');
            $m = q_one("SELECT * FROM cash_movements WHERE id = ? AND type = 'egreso'", [$id]);
            if (!$m) throw new AppException('El egreso no existe.');
            if ($m['source'] !== 'manual') throw new AppException('Las compras se anulan desde Compras, los sueldos desde Sueldos y los reembolsos no se pueden anular.');
            q("UPDATE cash_movements SET status = 'anulado' WHERE id = ?", [$id]);
            audit('egreso_anulado', 'cash_movements', $id, $m['concept']);
            flash('success', 'Egreso anulado. Queda en el historial.');
        }
    } catch (AppException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('modules/administracion/egresos.php' . qs());
}

[$from, $to] = date_range_params();
$st = get_str('estado', 10); $cat = get_str('categoria', 80); $src = get_str('origen', 10); $qtext = get_str('q', 60);
$where = ["c.type = 'egreso'", 'c.mov_date BETWEEN :f AND :t'];
$p = [':f' => $from, ':t' => $to];
if (in_array($st, ['pagado', 'pendiente', 'anulado'], true)) { $where[] = 'c.status = :st'; $p[':st'] = $st; }
if ($cat !== '') { $where[] = 'c.category = :cat'; $p[':cat'] = $cat; }
if (in_array($src, ['manual', 'compra', 'sueldo', 'devolucion'], true)) { $where[] = 'c.source = :src'; $p[':src'] = $src; }
if ($qtext !== '') { $where[] = '(c.concept LIKE :q1 OR c.reference LIKE :q2)'; $p[':q1'] = $p[':q2'] = "%$qtext%"; }
$w = implode(' AND ', $where);

[$sk, $sd, $order] = sort_params(['date' => 'c.mov_date', 'concept' => 'c.concept', 'amount' => 'c.amount'], 'date', 'desc');
$pg = paginate('c.*, pm.name AS method_name, su.name AS supplier_name',
    "cash_movements c LEFT JOIN payment_methods pm ON pm.id = c.payment_method_id LEFT JOIN suppliers su ON su.id = c.supplier_id WHERE $w", $p, "$order, c.id DESC");
$paid = (float)q_val("SELECT COALESCE(SUM(c.amount),0) FROM cash_movements c WHERE $w AND c.status = 'pagado'", $p);
$pend = (float)q_val("SELECT COALESCE(SUM(c.amount),0) FROM cash_movements c WHERE $w AND c.status = 'pendiente'", $p);
$catNames = q_all("SELECT DISTINCT category FROM cash_movements WHERE type = 'egreso' ORDER BY category");

$pageTitle = 'Egresos';
$active = 'adm.egresos';
$inlineJs = "document.getElementById('f-cat').addEventListener('change',function(){var o=this.options[this.selectedIndex];var t=document.getElementById('f-ct');var f=document.getElementById('f-fp');if(o.dataset.ct){t.value=o.dataset.ct;f.value=o.dataset.fp;}});"
          . "document.getElementById('f-st').addEventListener('change',function(){document.getElementById('f-pm-wrap').style.display=this.value==='pagado'?'':'none';});";
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head">
  <h1>Egresos</h1>
  <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#mAdd"><i class="bi bi-plus-lg"></i> Registrar gasto</button>
</div>

<form class="row g-2 mb-3 filters" method="get">
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Estado</label><select class="form-select form-select-sm" name="estado"><option value="">Todos</option>
    <?php foreach (['pagado', 'pendiente', 'anulado'] as $o): ?><option value="<?= $o ?>" <?= $st === $o ? 'selected' : '' ?>><?= ucfirst($o) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Categoría</label><select class="form-select form-select-sm" name="categoria"><option value="">Todas</option>
    <?php foreach ($catNames as $c): ?><option <?= $cat === $c['category'] ? 'selected' : '' ?>><?= e($c['category']) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Origen</label><select class="form-select form-select-sm" name="origen"><option value="">Todos</option>
    <?php foreach (['manual' => 'Manual', 'compra' => 'Compra', 'sueldo' => 'Sueldo', 'devolucion' => 'Reembolso'] as $k => $v): ?><option value="<?= $k ?>" <?= $src === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="fc-stat accent"><div class="lbl">Pagado (filtro)</div><div class="val"><?= e(money($paid)) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="fc-stat danger"><div class="lbl">Pendiente de pago</div><div class="val"><?= e(money($pend)) ?></div></div></div>
</div>
<p class="fc-note">Las <strong>compras de mercadería</strong> se registran desde el módulo Compras y aparecen acá automáticamente: no las cargues de nuevo como gasto porque se duplicarían.</p>

<div class="fc-card"><div class="table-responsive"><table class="table table-hover mb-0 fc-table">
  <thead><tr><?= sort_th('date', 'Fecha', $sk, $sd) ?><?= sort_th('concept', 'Descripción', $sk, $sd) ?><th>Categoría</th><th>Tipo de costo</th><th>Origen</th><?= sort_th('amount', 'Importe', $sk, $sd, 'text-end') ?><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($pg['rows'] as $r): ?>
    <tr class="<?= $r['status'] === 'anulado' ? 'text-decoration-line-through text-muted' : '' ?>">
      <td><?= e(date_ar($r['mov_date'])) ?></td>
      <td><?= e($r['concept']) ?><?php if ($r['supplier_name']): ?><div class="small text-muted"><?= e($r['supplier_name']) ?></div><?php endif; ?><?php if ($r['reference']): ?><div class="small text-muted">Ref.: <?= e($r['reference']) ?></div><?php endif; ?></td>
      <td><?= e($r['category']) ?></td>
      <td><?= $r['cost_type'] === 'no_aplica' ? '—' : e(ucfirst($r['cost_type'])) . ($r['cost_type'] === 'mixto' ? ' (' . num($r['fixed_pct'], 0) . ' % fijo)' : '') ?></td>
      <td><?= e(ucfirst($r['source'])) ?><?php if ($r['purchase_id']): ?> <a class="small" href="<?= e(url('modules/compras/ver.php?id=' . $r['purchase_id'])) ?>">ver</a><?php endif; ?></td>
      <td class="text-end"><?= e(money($r['amount'])) ?></td><td><?= status_badge($r['status']) ?></td>
      <td class="text-end text-nowrap">
        <?php if ($r['status'] === 'pendiente'): ?>
          <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#mPay" data-id="<?= (int)$r['id'] ?>" data-concept="<?= e($r['concept']) ?>" data-amount="<?= e(money($r['amount'])) ?>" title="Marcar como pagado"><i class="bi bi-check2-circle"></i></button>
        <?php endif; ?>
        <?php if ($r['source'] === 'manual' && $r['status'] !== 'anulado'): ?>
          <form method="post" class="d-inline" data-confirm="¿Anular este egreso? Quedará registrado como anulado."><?= csrf_field() ?><input type="hidden" name="action" value="void"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger" title="Anular"><i class="bi bi-x-circle"></i></button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="8" class="text-center text-muted py-4">No hay egresos para los filtros elegidos.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= render_pagination($pg) ?>

<div class="modal fade" id="mAdd" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form method="post" class="modal-content">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="modal-header"><h5 class="modal-title">Registrar gasto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row"><div class="col-6 mb-2"><label class="form-label required">Fecha</label><input type="date" class="form-control" name="mov_date" value="<?= e(today()) ?>" required></div>
      <div class="col-6 mb-2"><label class="form-label required">Importe</label><input class="form-control" name="amount" type="number" step="0.01" min="0.01" required></div></div>
    <div class="mb-2"><label class="form-label required">Descripción</label><input class="form-control" name="concept" maxlength="200" required></div>
    <div class="mb-2"><label class="form-label required">Categoría</label>
      <select class="form-select" name="category_id" id="f-cat" required><option value="">Elegir…</option>
        <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" data-ct="<?= e($c['cost_type']) ?>" data-fp="<?= e($c['fixed_pct']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="row"><div class="col-6 mb-2"><label class="form-label">Tipo de costo</label>
      <select class="form-select" name="cost_type" id="f-ct"><option value="">Según categoría</option><option value="fijo">Fijo</option><option value="variable">Variable</option><option value="mixto">Mixto</option></select></div>
      <div class="col-6 mb-2"><label class="form-label">% fijo (si es mixto)</label><input class="form-control" name="fixed_pct" id="f-fp" type="number" step="0.01" min="0" max="100" value="50"></div></div>
    <div class="row"><div class="col-6 mb-2"><label class="form-label">Estado de pago</label><select class="form-select" name="status" id="f-st"><option value="pagado">Pagado</option><option value="pendiente">Pendiente de pago</option></select></div>
      <div class="col-6 mb-2" id="f-pm-wrap"><label class="form-label">Medio de pago</label><select class="form-select" name="payment_method_id"><?= options($methods, 'id', 'name', null, 'Elegir…') ?></select></div></div>
    <div class="row"><div class="col-6 mb-2"><label class="form-label">Proveedor (opcional)</label><select class="form-select" name="supplier_id"><?= options($suppliers, 'id', 'name', null, '—') ?></select></div>
      <div class="col-6 mb-2"><label class="form-label">Comprobante / referencia</label><input class="form-control" name="reference" maxlength="80"></div></div>
    <div class="mb-2"><label class="form-label">Observaciones</label><textarea class="form-control" name="notes" rows="2" maxlength="500"></textarea></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar gasto</button></div>
</form></div></div>

<div class="modal fade" id="mPay" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form method="post" class="modal-content">
  <?= csrf_field() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="id" id="pay-id">
  <div class="modal-header"><h5 class="modal-title">Registrar pago</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="mb-2"><strong id="pay-concept"></strong><br><span class="text-muted">Importe: <span id="pay-amount"></span></span></p>
    <div class="mb-2"><label class="form-label required">Fecha de pago</label><input type="date" class="form-control" name="paid_date" value="<?= e(today()) ?>" required></div>
    <div class="mb-2"><label class="form-label required">Medio de pago</label><select class="form-select" name="payment_method_id" required><?= options($methods, 'id', 'name', null, 'Elegir…') ?></select></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Confirmar pago</button></div>
</form></div></div>
<?php
$inlineJs .= "document.getElementById('mPay').addEventListener('show.bs.modal',function(ev){var b=ev.relatedTarget;document.getElementById('pay-id').value=b.dataset.id;document.getElementById('pay-concept').textContent=b.dataset.concept;document.getElementById('pay-amount').textContent=b.dataset.amount;});";
include ROOT_DIR . '/includes/footer.php'; ?>
