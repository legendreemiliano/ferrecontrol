<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_perm('admin');

/** Genera un volcado SQL completo (estructura + datos) usando solo PHP/PDO (no requiere mysqldump). */
function dump_database(): string
{
    $pdo = db();
    $out = "-- FerreControl - copia de seguridad\n-- Generada: " . date('d/m/Y H:i:s') . "\n-- Para restaurar: phpMyAdmin > Importar.\n\n";
    $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '', $t) . '`')->fetch(PDO::FETCH_NUM);
        $out .= "DROP TABLE IF EXISTS `$t`;\n" . $create[1] . ";\n\n";
        $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '', $t) . '`');
        $batch = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $vals = [];
            foreach ($row as $v) $vals[] = $v === null ? 'NULL' : $pdo->quote((string)$v);
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 200) { $out .= "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n"; $batch = []; }
        }
        if ($batch) $out .= "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n";
        $out .= "\n";
    }
    return $out . "SET FOREIGN_KEY_CHECKS = 1;\n";
}

if (is_post()) {
    csrf_check();
    audit('backup', 'sistema', null, 'Copia de seguridad descargada');
    $sql = dump_database();
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ferrecontrol_backup_' . date('Y-m-d_His') . '.sql"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

$pageTitle = 'Copia de seguridad';
$active = 'adm.backup';
include ROOT_DIR . '/includes/header.php';
?>
<div class="fc-page-head"><h1>Copia de seguridad</h1></div>
<div class="fc-card" style="max-width:720px"><div class="fc-card-b">
  <p>Descarga un archivo <code>.sql</code> con <strong>toda la base de datos</strong> (estructura y datos). Guardalo en un pendrive o en la nube.</p>
  <p class="mb-3">Para restaurarlo: abrí phpMyAdmin, elegí la base <code>ferrecontrol</code> y usá <em>Importar</em> con ese archivo.</p>
  <form method="post"><?= csrf_field() ?><button class="btn btn-accent"><i class="bi bi-download"></i> Descargar copia de seguridad</button></form>
  <p class="fc-note mt-3 mb-0">Importar una copia reemplaza las tablas actuales por las del archivo. Hacelo solo si querés volver a ese momento.</p>
</div></div>
<?php include ROOT_DIR . '/includes/footer.php'; ?>
