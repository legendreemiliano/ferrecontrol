<?php
/**
 * Permisos por rol. Se verifican en PHP en cada página y en cada operación protegida
 * (ocultar un botón NO alcanza: el servidor vuelve a comprobar).
 */
function role_permissions(): array
{
    return [
        'admin' => ['*'],
        'deposito' => [
            'dashboard',
            'products.view', 'products.create', 'products.edit',
            'suppliers.view',
            'purchases.view', 'purchases.create',
            'sales.view', 'sales.create', 'sales.collect', 'sales.return',
            'stock.view', 'stock.adjust', 'inventory.count', 'reports.stock',
        ],
        'personal' => [
            'dashboard',
            'employees.view', 'employees.edit',
            'attendance.manage', 'leaves.manage',
            'payroll.view', 'payroll.generate',
            'reports.personal',
        ],
    ];
}

function can(string $perm): bool
{
    $u = current_user();
    if (!$u) return false;
    $list = role_permissions()[$u['role']] ?? [];
    if (in_array('*', $list, true)) return true;
    if ($perm === 'products.create' && $u['role'] === 'deposito' && setting('deposito_can_create_products', '1') !== '1') return false;
    return in_array($perm, $list, true);
}

function require_perm(string $perm): void
{
    if (can($perm)) return;
    audit('acceso_denegado', 'permiso', null, $perm . ' en ' . ($_SERVER['REQUEST_URI'] ?? ''));
    http_response_code(403);
    $pageTitle = 'Acceso denegado';
    $active = '';
    include ROOT_DIR . '/includes/header.php';
    echo '<div class="alert alert-danger"><h4 class="alert-heading">Acceso denegado</h4>'
       . '<p class="mb-0">Tu rol no tiene permiso para realizar esta operación. Si creés que es un error, consultá con el administrador.</p></div>';
    include ROOT_DIR . '/includes/footer.php';
    exit;
}

/** Descuento máximo (%) que el usuario actual puede aplicar en una venta. */
function max_discount_pct(): float
{
    $u = current_user();
    if (!$u || $u['role'] === 'admin') return 100.0;
    return max(0.0, min(100.0, (float)setting('max_discount_deposito', '10')));
}
