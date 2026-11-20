<?php
/** Menú lateral. Cada ítem se muestra solo si el usuario tiene el permiso necesario. */
function menu_items(): array
{
    return [
        ['title' => null, 'items' => [
            ['dashboard', 'Inicio', 'bi-speedometer2', 'dashboard.php', 'dashboard'],
            ['reports', 'Centro de informes', 'bi-journal-text', 'reports/index.php', 'dashboard'],
        ]],
        ['title' => 'Administración', 'items' => [
            ['adm.panel',     'Panel económico',    'bi-graph-up-arrow', 'modules/administracion/index.php', 'admin'],
            ['adm.ingresos',  'Ingresos',           'bi-cash-coin',      'modules/administracion/ingresos.php', 'admin'],
            ['adm.egresos',   'Egresos',            'bi-wallet2',        'modules/administracion/egresos.php', 'admin'],
            ['adm.costos',    'Costos del período', 'bi-calculator',     'modules/administracion/costos.php', 'admin'],
            ['adm.precios',   'Precios de venta',   'bi-tags',           'modules/administracion/precios.php', 'admin'],
            ['adm.ganancias', 'Ganancias',          'bi-piggy-bank',     'modules/administracion/ganancias.php', 'admin'],
            ['adm.informes',  'Informes',           'bi-file-earmark-bar-graph', 'modules/administracion/informes.php', 'admin'],
            ['adm.usuarios',  'Usuarios',           'bi-people',         'modules/usuarios/index.php', 'admin'],
            ['adm.config',    'Configuración',      'bi-gear',           'modules/administracion/configuracion.php', 'admin'],
            ['adm.auditoria', 'Auditoría',          'bi-shield-check',   'modules/administracion/auditoria.php', 'admin'],
            ['adm.backup',    'Copia de seguridad', 'bi-database-down',  'modules/administracion/backup.php', 'admin'],
        ]],
        ['title' => 'Producción y Stock', 'items' => [
            ['stk.pos',         'Nueva venta',       'bi-cart-plus',     'modules/ventas/pos.php', 'sales.create'],
            ['stk.ventas',      'Ventas',            'bi-receipt',       'modules/ventas/index.php', 'sales.view'],
            ['stk.compras',     'Compras',           'bi-truck',         'modules/compras/index.php', 'purchases.view'],
            ['stk.productos',   'Productos',         'bi-box-seam',      'modules/stock/index.php', 'products.view'],
            ['stk.categorias',  'Categorías',        'bi-diagram-3',     'modules/stock/categorias.php', 'admin'],
            ['stk.proveedores', 'Proveedores',       'bi-building',      'modules/proveedores/index.php', 'suppliers.view'],
            ['stk.movimientos', 'Movimientos',       'bi-arrow-left-right', 'modules/stock/movimientos.php', 'stock.view'],
            ['stk.diario',      'Control diario',    'bi-calendar-day',  'modules/stock/diario.php', 'stock.view'],
            ['stk.ajustes',     'Ajustes de stock',  'bi-sliders',       'modules/stock/ajustes.php', 'stock.adjust'],
            ['stk.inventario',  'Inventario físico', 'bi-clipboard-check', 'modules/stock/inventario.php', 'inventory.count'],
            ['stk.alertas',     'Stock bajo',        'bi-exclamation-triangle', 'modules/stock/alertas.php', 'stock.view'],
            ['stk.reportes',    'Reportes de stock', 'bi-bar-chart',     'modules/stock/reportes.php', 'reports.stock'],
        ]],
        ['title' => 'Personal', 'items' => [
            ['per.empleados',   'Empleados',    'bi-person-vcard',  'modules/personal/empleados/index.php', 'employees.view'],
            ['per.asistencias', 'Asistencias',  'bi-calendar-check', 'modules/personal/asistencias/index.php', 'attendance.manage'],
            ['per.licencias',   'Licencias',    'bi-calendar-x',    'modules/personal/licencias/index.php', 'leaves.manage'],
            ['per.sueldos',     'Sueldos',      'bi-currency-dollar', 'modules/personal/sueldos/index.php', 'payroll.view'],
            ['per.conceptos',   'Conceptos de sueldo', 'bi-list-check', 'modules/personal/sueldos/conceptos.php', 'admin'],
            ['per.reportes',    'Reportes de personal', 'bi-clipboard-data', 'modules/personal/reportes.php', 'reports.personal'],
        ]],
    ];
}

function render_menu(string $active): void
{
    foreach (menu_items() as $group) {
        $visible = array_filter($group['items'], function ($it) { return can($it[4]); });
        if (!$visible) continue;
        if ($group['title']) echo '<div class="fc-menu-title">' . e($group['title']) . '</div>';
        foreach ($visible as $it) {
            $cls = $it[0] === $active ? ' active' : '';
            echo '<a class="fc-menu-link' . $cls . '" href="' . e(url($it[3])) . '"><i class="bi ' . e($it[2]) . '"></i><span>' . e($it[1]) . '</span></a>';
        }
    }
}
