<?php
/** Funciones compartidas: formato, seguridad, formularios, paginación, CSV. */

// ------------------------------------------------------------------ salida / formato
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money($n): string { return '$ ' . number_format((float)$n, 2, ',', '.'); }
function num($n, int $dec = 0): string { return number_format((float)$n, $dec, ',', '.'); }
function pct($n): string { return number_format((float)$n, 2, ',', '.') . ' %'; }
function money_round($n): float { return round((float)$n, 2, PHP_ROUND_HALF_UP); }

function date_ar(?string $d): string
{
    if (!$d || strpos($d, '0000') === 0) return '—';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : '—';
}
function datetime_ar(?string $d): string
{
    if (!$d || strpos($d, '0000') === 0) return '—';
    $t = strtotime($d);
    return $t ? date('d/m/Y H:i', $t) : '—';
}
function weekday_es(string $date): string
{
    $n = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    return $n[(int)date('w', strtotime($date))];
}
function month_es(int $m): string
{
    $n = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return $n[$m] ?? '';
}
function period_label(string $period): string  // '2026-09' => 'Septiembre 2026'
{
    return month_es((int)substr($period, 5, 2)) . ' ' . substr($period, 0, 4);
}
function today(): string { return date('Y-m-d'); }
function now_sql(): string { return date('Y-m-d H:i:s'); }
function valid_date(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x !== false && $x->format('Y-m-d') === $d;
}
function valid_period(string $p): bool { return (bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p); }
function days_ago(int $n): string { return date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days')); }

// ------------------------------------------------------------------ URLs
function base_url(): string
{
    static $b = null;
    if ($b !== null) return $b;
    if (defined('APP_BASE_URL') && APP_BASE_URL !== '') {
        return $b = rtrim(APP_BASE_URL, '/');
    }
    $doc  = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $doc  = $doc ? str_replace('\\', '/', $doc) : '';
    $root = str_replace('\\', '/', ROOT_DIR);
    if ($doc !== '' && strncasecmp($root, $doc, strlen($doc)) === 0) {
        $b = substr($root, strlen($doc));
    } else {
        $b = '/ferrecontrol';
    }
    return $b = rtrim($b, '/');
}
function url(string $path = ''): string { return base_url() . '/' . ltrim($path, '/'); }
function redirect(string $path): void
{
    $loc = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $loc);
    exit;
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }

/** Query string actual con cambios (para links de orden y paginación). */
function qs(array $override = [], array $drop = []): string
{
    $q = $_GET;
    foreach ($override as $k => $v) $q[$k] = $v;
    foreach ($drop as $k) unset($q[$k]);
    $q = array_filter($q, function ($v) { return $v !== '' && $v !== null; });
    return '?' . http_build_query($q);
}

// ------------------------------------------------------------------ mensajes
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['t' => $type, 'm' => $msg]; }
function flash_render(): string
{
    if (empty($_SESSION['flash'])) return '';
    $map = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $h = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = $map[$f['t']] ?? 'info';
        $h .= '<div class="alert alert-' . $cls . ' alert-dismissible fade show fc-flash" role="alert">'
            . e($f['m']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>';
    }
    unset($_SESSION['flash']);
    return $h;
}

// ------------------------------------------------------------------ CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(419);
        exit('La sesión del formulario venció o el pedido no es válido. Volvé atrás, recargá la página e intentá de nuevo.');
    }
}

// ------------------------------------------------------------------ entradas
function post_str(string $k, int $max = 255): string
{
    $v = $_POST[$k] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}
function get_str(string $k, int $max = 255): string
{
    $v = $_GET[$k] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}
function post_int(string $k, int $default = 0): int
{
    $v = filter_var($_POST[$k] ?? null, FILTER_VALIDATE_INT);
    return $v === false || $v === null ? $default : (int)$v;
}
function get_int(string $k, int $default = 0): int
{
    $v = filter_var($_GET[$k] ?? null, FILTER_VALIDATE_INT);
    return $v === false || $v === null ? $default : (int)$v;
}
/** Convierte "1.234,56", "1234,56" o "1234.56" en float. Devuelve null si no es un número. */
function parse_decimal($v): ?float
{
    if (is_int($v) || is_float($v)) return (float)$v;
    if (!is_string($v)) return null;
    $s = str_replace([' ', '$'], '', trim($v));
    if ($s === '') return null;
    $c = strrpos($s, ','); $p = strrpos($s, '.');
    if ($c !== false && $p !== false) {
        $s = $c > $p ? str_replace(',', '.', str_replace('.', '', $s)) : str_replace(',', '', $s);
    } elseif ($c !== false) {
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float)$s : null;
}
function post_dec(string $k, float $default = 0.0): float
{
    $v = parse_decimal($_POST[$k] ?? null);
    return $v === null ? $default : $v;
}
function get_date(string $k, string $default): string
{
    $v = get_str($k, 10);
    return valid_date($v) ? $v : $default;
}
function post_date(string $k, string $default = ''): string
{
    $v = post_str($k, 10);
    return valid_date($v) ? $v : $default;
}
/** Valor anterior de un campo de formulario (para no perder lo escrito si hay error). */
function old(string $k, $default = '')
{
    return isset($_POST[$k]) && is_string($_POST[$k]) ? $_POST[$k] : $default;
}

// ------------------------------------------------------------------ HTML helpers
function options(array $rows, string $valueKey, string $labelKey, $selected = null, ?string $blank = null): string
{
    $h = $blank !== null ? '<option value="">' . e($blank) . '</option>' : '';
    foreach ($rows as $r) {
        $sel = ((string)$r[$valueKey] === (string)$selected) ? ' selected' : '';
        $h .= '<option value="' . e($r[$valueKey]) . '"' . $sel . '>' . e($r[$labelKey]) . '</option>';
    }
    return $h;
}
function badge(string $text, string $class = 'secondary'): string
{
    return '<span class="badge text-bg-' . e($class) . '">' . e($text) . '</span>';
}
function status_badge(string $status): string
{
    $map = [
        'pagada' => 'success', 'pagado' => 'success', 'cobrado' => 'success', 'confirmada' => 'success', 'confirmado' => 'success',
        'aprobada' => 'success', 'presente' => 'success', 'activo' => 'success',
        'pendiente' => 'warning', 'parcial' => 'warning', 'borrador' => 'secondary', 'tarde' => 'warning', 'licencia' => 'info', 'franco' => 'secondary',
        'anulada' => 'danger', 'anulado' => 'danger', 'rechazada' => 'danger', 'rechazado' => 'danger', 'ausente' => 'danger', 'baja' => 'dark',
    ];
    return badge(ucfirst($status), $map[$status] ?? 'secondary');
}
function yesno_badge(bool $v): string { return $v ? badge('Activo', 'success') : badge('Inactivo', 'secondary'); }

// ------------------------------------------------------------------ paginación y orden
function paginate(string $select, string $from, array $params, string $orderBy, int $perPage = 15): array
{
    $total = (int)q_val("SELECT COUNT(*) FROM $from", $params);
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, get_int('page', 1)), $pages);
    $off   = ($page - 1) * $perPage;
    $rows  = q_all("SELECT $select FROM $from ORDER BY $orderBy LIMIT $perPage OFFSET $off", $params);
    return ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage];
}
function render_pagination(array $pg): string
{
    $cur = $pg['page']; $last = $pg['pages'];
    $from = $pg['total'] === 0 ? 0 : (($cur - 1) * $pg['perPage'] + 1);
    $to = min($pg['total'], $cur * $pg['perPage']);
    $h = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">'
       . '<small class="text-muted">Mostrando ' . $from . '–' . $to . ' de ' . $pg['total'] . ' registros</small>';
    if ($last > 1) {
        $h .= '<nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">';
        $h .= '<li class="page-item' . ($cur <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e(qs(['page' => max(1, $cur - 1)])) . '">&laquo;</a></li>';
        for ($i = max(1, $cur - 2); $i <= min($last, $cur + 2); $i++) {
            $h .= '<li class="page-item' . ($i === $cur ? ' active' : '') . '"><a class="page-link" href="' . e(qs(['page' => $i])) . '">' . $i . '</a></li>';
        }
        $h .= '<li class="page-item' . ($cur >= $last ? ' disabled' : '') . '"><a class="page-link" href="' . e(qs(['page' => min($last, $cur + 1)])) . '">&raquo;</a></li>';
        $h .= '</ul></nav>';
    }
    return $h . '</div>';
}
/** $allowed = ['clave' => 'columna SQL']. Devuelve [clave, dirección, "columna DIR"]. */
function sort_params(array $allowed, string $defKey, string $defDir = 'asc'): array
{
    $k = $_GET['sort'] ?? $defKey;
    if (!is_string($k) || !isset($allowed[$k])) $k = $defKey;
    $d = (($_GET['dir'] ?? $defDir) === 'desc') ? 'desc' : 'asc';
    return [$k, $d, $allowed[$k] . ' ' . strtoupper($d)];
}
function sort_th(string $key, string $label, string $cur, string $dir, string $class = ''): string
{
    $next = ($cur === $key && $dir === 'asc') ? 'desc' : 'asc';
    $icon = $cur === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<th class="' . e($class) . '"><a class="text-decoration-none text-reset" href="'
         . e(qs(['sort' => $key, 'dir' => $next, 'page' => 1])) . '">' . e($label) . $icon . '</a></th>';
}

// ------------------------------------------------------------------ configuración y auditoría
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q_all('SELECT setting_key, setting_value FROM settings') as $r) $cache[$r['setting_key']] = $r['setting_value'];
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}
function set_setting(string $key, string $value): void
{
    q('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
}
function acting_user_id(): ?int
{
    if (!empty($GLOBALS['ACTING_USER_ID'])) return (int)$GLOBALS['ACTING_USER_ID'];
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}
function audit(string $action, string $entity = '', ?int $entityId = null, string $details = ''): void
{
    try {
        q('INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip) VALUES (?,?,?,?,?,?)',
          [acting_user_id(), $action, $entity, $entityId, mb_substr($details, 0, 2000), $_SERVER['REMOTE_ADDR'] ?? 'cli']);
    } catch (Throwable $e) {
        app_log('audit: ' . $e->getMessage());
    }
}
function next_number(string $prefix, int $id): string { return $prefix . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT); }

// ------------------------------------------------------------------ informes y CSV
/**
 * Un informe es un array: ['title','note','cols'=>[['k','l','t']...],'rows'=>[[k=>v]...],'foot'=>[k=>v]]
 * Tipos de columna: text, date, datetime, money, int, dec, pct.
 */
function fmt_cell($v, string $type): string
{
    if ($v === null || $v === '') return '—';
    switch ($type) {
        case 'money':    return money($v);
        case 'int':      return num($v);
        case 'dec':      return num($v, 2);
        case 'pct':      return pct($v);
        case 'date':     return date_ar($v);
        case 'datetime': return datetime_ar($v);
        default:         return (string)$v;
    }
}
function render_report(array $r): string
{
    $h = '<div class="table-responsive"><table class="table table-sm table-striped align-middle fc-table"><thead><tr>';
    foreach ($r['cols'] as $c) {
        $num = in_array($c['t'], ['money', 'int', 'dec', 'pct'], true);
        $h .= '<th' . ($num ? ' class="text-end"' : '') . '>' . e($c['l']) . '</th>';
    }
    $h .= '</tr></thead><tbody>';
    if (!$r['rows']) {
        $h .= '<tr><td colspan="' . count($r['cols']) . '" class="text-center text-muted py-4">No hay datos para los filtros elegidos.</td></tr>';
    }
    foreach ($r['rows'] as $row) {
        $h .= '<tr>';
        foreach ($r['cols'] as $c) {
            $num = in_array($c['t'], ['money', 'int', 'dec', 'pct'], true);
            $h .= '<td' . ($num ? ' class="text-end"' : '') . '>' . e(fmt_cell($row[$c['k']] ?? null, $c['t'])) . '</td>';
        }
        $h .= '</tr>';
    }
    $h .= '</tbody>';
    if (!empty($r['foot'])) {
        $h .= '<tfoot><tr class="fw-bold">';
        foreach ($r['cols'] as $c) {
            $num = in_array($c['t'], ['money', 'int', 'dec', 'pct'], true);
            $v = $r['foot'][$c['k']] ?? '';
            $h .= '<td' . ($num ? ' class="text-end"' : '') . '>' . ($v === '' ? '' : e(is_string($v) && !is_numeric($v) ? $v : fmt_cell($v, $c['t']))) . '</td>';
        }
        $h .= '</tr></tfoot>';
    }
    return $h . '</table></div>';
}
function csv_cell($v, string $type): string
{
    if ($v === null) return '';
    if (in_array($type, ['money', 'dec', 'pct'], true)) return number_format((float)$v, 2, ',', '');
    if ($type === 'date') return date_ar($v);
    if ($type === 'datetime') return datetime_ar($v);
    return (string)$v;
}
function report_csv(array $r, string $filename): void
{
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, array_map(function ($c) { return $c['l']; }, $r['cols']), ';', '"', '\\');
    foreach ($r['rows'] as $row) {
        $line = [];
        foreach ($r['cols'] as $c) $line[] = csv_cell($row[$c['k']] ?? null, $c['t']);
        fputcsv($out, $line, ';', '"', '\\');
    }
    if (!empty($r['foot'])) {
        $line = [];
        foreach ($r['cols'] as $c) {
            $v = $r['foot'][$c['k']] ?? '';
            $line[] = $v === '' ? '' : csv_cell($v, $c['t']);
        }
        fputcsv($out, $line, ';', '"', '\\');
    }
    fclose($out);
    exit;
}
function json_out($data, int $code = 200): void
{
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Filtros de fecha comunes (por defecto: mes actual). Devuelve [desde, hasta]. */
function date_range_params(): array
{
    $from = get_date('desde', date('Y-m-01'));
    $to   = get_date('hasta', date('Y-m-t'));
    if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }
    return [$from, $to];
}
