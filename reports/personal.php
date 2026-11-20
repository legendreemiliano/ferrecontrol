<?php
/** Informes de Personal. Los informes salariales solo se ofrecen a quien tiene permiso payroll.view. */

function personal_report_types(): array
{
    $t = [
        'nomina'        => 'Nómina de empleados',
        'asistencias'   => 'Asistencias y puntualidad',
        'ausencias'     => 'Ausencias',
        'licencias'     => 'Licencias',
        'horas'         => 'Horas trabajadas',
        'productividad' => 'Indicadores de productividad',
    ];
    if (can('payroll.view')) {
        $t['liquidaciones']   = 'Liquidaciones';
        $t['costos_personal'] = 'Evolución de los costos de personal';
    }
    return $t;
}

function personal_report(string $type, string $from, string $to): array
{
    $types = personal_report_types();
    if (!isset($types[$type])) $type = 'nomina';
    $title = $types[$type] . ($type === 'nomina' ? '' : ' · ' . date_ar($from) . ' al ' . date_ar($to));

    switch ($type) {
        case 'nomina':
            $rows = q_all("SELECT CONCAT(last_name, ', ', first_name) AS name, dni, position, hire_date, contract_type, status, base_salary FROM employees ORDER BY last_name, first_name");
            foreach ($rows as &$r) $r['contract'] = contract_label($r['contract_type']);
            unset($r);
            $cols = [['k' => 'name', 'l' => 'Empleado', 't' => 'text'], ['k' => 'dni', 'l' => 'DNI', 't' => 'text'], ['k' => 'position', 'l' => 'Puesto', 't' => 'text'], ['k' => 'hire_date', 'l' => 'Ingreso', 't' => 'date'],
                     ['k' => 'contract', 'l' => 'Contratación', 't' => 'text'], ['k' => 'status', 'l' => 'Estado', 't' => 'text']];
            $foot = [];
            if (can('payroll.view')) {
                $cols[] = ['k' => 'base_salary', 'l' => 'Sueldo básico', 't' => 'money'];
                $sum = 0; foreach ($rows as $r) if ($r['status'] !== 'baja') $sum += (float)$r['base_salary'];
                $foot = ['name' => 'TOTAL (sin bajas)', 'base_salary' => $sum];
            }
            return ['title' => $title, 'note' => '', 'cols' => $cols, 'rows' => $rows, 'foot' => $foot];

        case 'asistencias':
        case 'productividad':
            $rows = q_all("SELECT e.id, e.user_id, CONCAT(e.last_name, ', ', e.first_name) AS name, e.tasks,
                              COALESCE(SUM(a.status IN ('presente','tarde')), 0) AS pres, COALESCE(SUM(a.status = 'tarde'), 0) AS late,
                              COALESCE(SUM(a.status = 'ausente'), 0) AS abs_, COALESCE(SUM(a.status = 'licencia'), 0) AS lic,
                              COALESCE(SUM(a.status = 'franco'), 0) AS franco, COUNT(a.id) AS total,
                              COALESCE(SUM(CASE WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL THEN TIME_TO_SEC(TIMEDIFF(a.check_out, a.check_in)) ELSE 0 END), 0) / 3600 AS hours
                            FROM employees e LEFT JOIN attendances a ON a.employee_id = e.id AND a.att_date BETWEEN ? AND ?
                            WHERE e.status <> 'baja' GROUP BY e.id, e.user_id, e.last_name, e.first_name, e.tasks ORDER BY e.last_name, e.first_name", [$from, $to]);
            foreach ($rows as &$r) {
                $due = (int)$r['total'] - (int)$r['franco'] - (int)$r['lic'];
                $r['att_pct'] = $due > 0 ? (int)$r['pres'] / $due * 100 : null;
                $r['punct_pct'] = (int)$r['pres'] > 0 ? ((int)$r['pres'] - (int)$r['late']) / (int)$r['pres'] * 100 : null;
                if ($type === 'productividad') {
                    $s = $r['user_id'] ? q_one("SELECT COUNT(*) AS c, COALESCE(SUM(total - returned_total),0) AS t FROM sales WHERE status = 'confirmada' AND user_id = ? AND DATE(sale_date) BETWEEN ? AND ?", [$r['user_id'], $from, $to]) : null;
                    $r['sales_count'] = $s ? (int)$s['c'] : null; $r['sales_total'] = $s ? (float)$s['t'] : null;
                }
            }
            unset($r);
            if ($type === 'asistencias') {
                return ['title' => $title, 'note' => 'Asistencia % = presentes (incluye tardes) ÷ días laborables registrados (sin francos ni licencias). Puntualidad % = presentes que llegaron a horario.',
                    'cols' => [['k' => 'name', 'l' => 'Empleado', 't' => 'text'], ['k' => 'pres', 'l' => 'Presentes', 't' => 'int'], ['k' => 'late', 'l' => 'Tardes', 't' => 'int'], ['k' => 'abs_', 'l' => 'Ausencias', 't' => 'int'],
                               ['k' => 'lic', 'l' => 'Licencias', 't' => 'int'], ['k' => 'att_pct', 'l' => 'Asistencia %', 't' => 'pct'], ['k' => 'punct_pct', 'l' => 'Puntualidad %', 't' => 'pct']], 'rows' => $rows];
            }
            return ['title' => $title, 'note' => 'Son indicadores de gestión, no una medida absoluta del desempeño de una persona. Las ventas solo se muestran si el empleado tiene un usuario del sistema asociado.',
                'cols' => [['k' => 'name', 'l' => 'Empleado', 't' => 'text'], ['k' => 'att_pct', 'l' => 'Asistencia %', 't' => 'pct'], ['k' => 'punct_pct', 'l' => 'Puntualidad %', 't' => 'pct'], ['k' => 'hours', 'l' => 'Horas registradas', 't' => 'dec'],
                           ['k' => 'sales_count', 'l' => 'Ventas', 't' => 'int'], ['k' => 'sales_total', 'l' => 'Monto vendido', 't' => 'money'], ['k' => 'tasks', 'l' => 'Tareas asignadas', 't' => 'text']], 'rows' => $rows];

        case 'ausencias':
            $rows = q_all("SELECT a.att_date, CONCAT(e.last_name, ', ', e.first_name) AS name, a.shift, a.justified, a.justification FROM attendances a JOIN employees e ON e.id = a.employee_id
                           WHERE a.status = 'ausente' AND a.att_date BETWEEN ? AND ? ORDER BY a.att_date DESC, e.last_name", [$from, $to]);
            foreach ($rows as &$r) $r['just'] = $r['justified'] ? 'Sí' : 'No';
            unset($r);
            return ['title' => $title, 'note' => '', 'cols' => [['k' => 'att_date', 'l' => 'Fecha', 't' => 'date'], ['k' => 'name', 'l' => 'Empleado', 't' => 'text'], ['k' => 'shift', 'l' => 'Turno', 't' => 'int'], ['k' => 'just', 'l' => 'Justificada', 't' => 'text'], ['k' => 'justification', 'l' => 'Detalle', 't' => 'text']], 'rows' => $rows];

        case 'licencias':
            $rows = q_all("SELECT CONCAT(e.last_name, ', ', e.first_name) AS name, l.leave_type, l.start_date, l.end_date, DATEDIFF(l.end_date, l.start_date) + 1 AS days, l.status
                           FROM leaves l JOIN employees e ON e.id = l.employee_id WHERE l.start_date <= ? AND l.end_date >= ? ORDER BY l.start_date DESC", [$to, $from]);
            return ['title' => $title, 'note' => 'Incluye las licencias que se superponen con el período.', 'cols' => [['k' => 'name', 'l' => 'Empleado', 't' => 'text'], ['k' => 'leave_type', 'l' => 'Tipo', 't' => 'text'], ['k' => 'start_date', 'l' => 'Desde', 't' => 'date'], ['k' => 'end_date', 'l' => 'Hasta', 't' => 'date'], ['k' => 'days', 'l' => 'Días', 't' => 'int'], ['k' => 'status', 'l' => 'Estado', 't' => 'text']], 'rows' => $rows];

        case 'horas':
            $rows = q_all("SELECT CONCAT(e.last_name, ', ', e.first_name) AS name, COUNT(a.id) AS days,
                             COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(a.check_out, a.check_in))), 0) / 3600 AS hours
                           FROM employees e LEFT JOIN attendances a ON a.employee_id = e.id AND a.att_date BETWEEN ? AND ? AND a.check_in IS NOT NULL AND a.check_out IS NOT NULL
                           WHERE e.status <> 'baja' GROUP BY e.id, e.last_name, e.first_name ORDER BY e.last_name", [$from, $to]);
            foreach ($rows as &$r) $r['avg'] = (int)$r['days'] > 0 ? (float)$r['hours'] / (int)$r['days'] : null;
            unset($r);
            return ['title' => $title, 'note' => 'Solo cuenta los registros con hora de entrada y de salida.', 'cols' => [['k' => 'name', 'l' => 'Empleado', 't' => 'text'], ['k' => 'days', 'l' => 'Días con horario', 't' => 'int'], ['k' => 'hours', 'l' => 'Horas totales', 't' => 'dec'], ['k' => 'avg', 'l' => 'Promedio por día', 't' => 'dec']], 'rows' => $rows];

        case 'liquidaciones':
            if (!can('payroll.view')) return ['title' => $title, 'note' => 'Sin permiso.', 'cols' => [], 'rows' => []];
            $rows = q_all("SELECT p.period, CONCAT(e.last_name, ', ', e.first_name) AS name, p.total_remunerative, p.gross_total, p.total_deductions, p.net_total, p.status
                           FROM payrolls p JOIN employees e ON e.id = p.employee_id WHERE p.period BETWEEN ? AND ? ORDER BY p.period DESC, e.last_name", [substr($from, 0, 7), substr($to, 0, 7)]);
            $g = 0; $n = 0; foreach ($rows as $r) if ($r['status'] !== 'anulada') { $g += (float)$r['gross_total']; $n += (float)$r['net_total']; }
            return ['title' => $title, 'note' => 'Estimaciones de gestión. Los totales no incluyen liquidaciones anuladas.', 'cols' => [['k' => 'period', 'l' => 'Período', 't' => 'text'], ['k' => 'name', 'l' => 'Empleado', 't' => 'text'], ['k' => 'total_remunerative', 'l' => 'Remunerativo', 't' => 'money'], ['k' => 'gross_total', 'l' => 'Bruto', 't' => 'money'], ['k' => 'total_deductions', 'l' => 'Descuentos', 't' => 'money'], ['k' => 'net_total', 'l' => 'Neto estimado', 't' => 'money'], ['k' => 'status', 'l' => 'Estado', 't' => 'text']],
                'rows' => $rows, 'foot' => ['name' => 'TOTALES', 'gross_total' => $g, 'net_total' => $n]];

        case 'costos_personal':
            if (!can('payroll.view')) return ['title' => $title, 'note' => 'Sin permiso.', 'cols' => [], 'rows' => []];
            $rows = q_all("SELECT period, COUNT(*) AS cnt, SUM(gross_total) AS gross, SUM(net_total) AS net FROM payrolls WHERE status <> 'anulada' AND period BETWEEN ? AND ? GROUP BY period ORDER BY period", [substr($from, 0, 7), substr($to, 0, 7)]);
            foreach ($rows as &$r) $r['label'] = period_label($r['period']);
            unset($r);
            return ['title' => $title, 'note' => 'Costo de personal estimado = suma de los brutos liquidados. No incluye cargas patronales, que deben cargarse como egresos.', 'cols' => [['k' => 'label', 'l' => 'Mes', 't' => 'text'], ['k' => 'cnt', 'l' => 'Liquidaciones', 't' => 'int'], ['k' => 'gross', 'l' => 'Total bruto', 't' => 'money'], ['k' => 'net', 'l' => 'Total neto', 't' => 'money']], 'rows' => $rows];
    }
    return ['title' => $title, 'note' => '', 'cols' => [], 'rows' => []];
}
