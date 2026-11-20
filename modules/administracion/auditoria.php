<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('admin');

[$from, $to] = date_range_params();
$action = get_str('accion', 60); $uid = get_int('usuario'); $qtext = get_str('q', 60);
$where = ['DATE(a.created_at) BETWEEN :f AND :t']; $p = [':f' => $from, ':t' => $to];
if ($action !== '') { $where[] = 'a.action = :ac'; $p[':ac'] = $action; }
if ($uid > 0) { $where[] = 'a.user_id = :u'; $p[':u'] = $uid; }
if ($qtext !== '') { $where[] = '(a.details LIKE :q1 OR a.entity LIKE :q2)'; $p[':q1'] = $p[':q2'] = "%$qtext%"; }
$pg = paginate('a.*, u.full_name, u.username', 'audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $where), $p, 'a.created_at DESC, a.id DESC', 25);
$actions = q_all('SELECT DISTINCT action FROM audit_logs ORDER BY action');
$users = q_all('SELECT id, full_name FROM users ORDER BY full_name');

$pageTitle = 'Auditoría';
$active = 'adm.auditoria';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Auditoría de operaciones</h1></div>
<form class="row g-2 mb-3 filters" method="get">
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Desde</label><input type="date" class="form-control form-control-sm" name="desde" value="<?= e($from) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label small mb-0">Hasta</label><input type="date" class="form-control form-control-sm" name="hasta" value="<?= e($to) ?>"></div>
  <div class="col-6 col-md-3"><label class="form-label small mb-0">Acción</label><select class="form-select form-select-sm" name="accion"><option value="">Todas</option>
    <?php foreach ($actions as $a): ?><option <?= $action === $a['action'] ? 'selected' : '' ?>><?= e($a['action']) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-3"><label class="form-label small mb-0">Usuario</label><select class="form-select form-select-sm" name="usuario"><?= options($users, 'id', 'full_name', $uid, 'Todos') ?></select></div>
  <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filtrar</button></div>
</form>
<div class="fc-card"><div class="table-responsive"><table class="table table-sm table-hover mb-0 fc-table">
  <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Detalle</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($pg['rows'] as $r): ?>
    <tr><td class="text-nowrap"><?= e(datetime_ar($r['created_at'])) ?></td><td><?= e($r['full_name'] ?? '—') ?></td><td><code><?= e($r['action']) ?></code></td>
      <td><?= e($r['entity']) ?><?= $r['entity_id'] ? ' #' . (int)$r['entity_id'] : '' ?></td><td class="small"><?= e($r['details']) ?></td><td class="small text-muted"><?= e($r['ip']) ?></td></tr>
  <?php endforeach; if (!$pg['rows']): ?><tr><td colspan="6" class="text-center text-muted py-4">Sin registros para los filtros elegidos.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?= render_pagination($pg) ?>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
