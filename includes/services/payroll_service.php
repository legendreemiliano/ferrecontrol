<?php
/**
 * Servicio de personal: asistencia y liquidación ESTIMADA de sueldos.
 *
 * IMPORTANTE: no se inventan porcentajes legales. Los conceptos (antigüedad, retenciones, etc.)
 * se cargan en payroll_concepts y deben ser validados por el Administrador.
 * El resultado es una estimación de gestión, NO un recibo de sueldo oficial.
 */

function employee_full_name(array $e): string { return $e['last_name'] . ', ' . $e['first_name']; }

function contract_label(string $c): string
{
    $m = ['tiempo_completo' => 'Tiempo completo', 'part_time' => 'Part-time', 'eventual' => 'Eventual', 'pasantia' => 'Pasantía'];
    return $m[$c] ?? $c;
}

/** Guarda (o actualiza) la asistencia de un empleado en un día/turno. */
function attendance_save(int $employeeId, string $date, int $shift, string $status, ?string $in, ?string $out, bool $justified, string $justification): void
{
    if (!valid_date($date)) throw new AppException('La fecha no es válida.');
    if (!in_array($status, ['presente', 'ausente', 'tarde', 'licencia', 'franco'], true)) throw new AppException('Estado de asistencia inválido.');
    if ($shift < 1 || $shift > 2) throw new AppException('El turno debe ser 1 o 2.');
    if (!q_one("SELECT id FROM employees WHERE id = ? AND status <> 'baja'", [$employeeId])) throw new AppException('El empleado no existe o está dado de baja.');
    $in  = ($in !== null && preg_match('/^\d{2}:\d{2}$/', $in)) ? $in . ':00' : null;
    $out = ($out !== null && preg_match('/^\d{2}:\d{2}$/', $out)) ? $out . ':00' : null;
    if ($in && $out && $out <= $in) throw new AppException('La hora de salida debe ser posterior a la de entrada.');

    // Si figura "presente" pero llegó después del horario + tolerancia, se marca "tarde".
    if ($status === 'presente' && $in) {
        $limit = strtotime($date . ' ' . setting('work_start', '09:00') . ':00') + 60 * (int)setting('late_tolerance_min', '10');
        if (strtotime($date . ' ' . $in) > $limit) $status = 'tarde';
    }
    if (in_array($status, ['ausente', 'licencia', 'franco'], true)) { $in = null; $out = null; }

    $exists = q_one('SELECT id FROM attendances WHERE employee_id = ? AND att_date = ? AND shift = ?', [$employeeId, $date, $shift]);
    if ($exists) {
        q('UPDATE attendances SET check_in = ?, check_out = ?, status = ?, justified = ?, justification = ?, user_id = ? WHERE id = ?',
          [$in, $out, $status, $justified ? 1 : 0, mb_substr($justification, 0, 255), acting_user_id(), $exists['id']]);
    } else {
        q('INSERT INTO attendances (employee_id, att_date, shift, check_in, check_out, status, justified, justification, user_id) VALUES (?,?,?,?,?,?,?,?,?)',
          [$employeeId, $date, $shift, $in, $out, $status, $justified ? 1 : 0, mb_substr($justification, 0, 255), acting_user_id()]);
    }
}

function unjustified_absences(int $employeeId, string $period): int
{
    $from = $period . '-01';
    $to = date('Y-m-t', strtotime($from));
    return (int)q_val("SELECT COUNT(*) FROM attendances WHERE employee_id = ? AND status = 'ausente' AND justified = 0 AND att_date BETWEEN ? AND ?", [$employeeId, $from, $to]);
}

/**
 * Calcula (sin guardar) la liquidación estimada.
 * $in: concept_ids[] (conceptos a aplicar), overtime_hours, overtime_rate, bonus, non_rem,
 *      absence_discount (bool), other_deduction, other_deduction_label
 */
function payroll_calculate(int $employeeId, string $period, array $in): array
{
    $e = q_one('SELECT * FROM employees WHERE id = ?', [$employeeId]);
    if (!$e) throw new AppException('El empleado no existe.');
    if (!valid_period($period)) throw new AppException('El período debe tener formato AAAA-MM.');

    $basic = money_round($e['base_salary']);
    $items = [];
    $warnings = [];
    $items[] = ['concept' => 'Sueldo básico', 'kind' => 'remunerativo', 'amount' => $basic, 'detail' => 'Según ficha del empleado'];

    $concepts = [];
    $ids = array_values(array_filter(array_map('intval', $in['concept_ids'] ?? [])));
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $concepts = q_all("SELECT * FROM payroll_concepts WHERE active = 1 AND id IN ($ph) ORDER BY id", $ids);
    }
    foreach ($concepts as $c) {
        if ((int)$c['validated'] !== 1) $warnings[] = 'El concepto «' . $c['name'] . '» todavía no fue validado por el Administrador.';
    }

    // 1) conceptos remunerativos
    foreach ($concepts as $c) {
        if ($c['kind'] !== 'remunerativo') continue;
        if ($c['calc_type'] === 'fijo') { $amt = money_round($c['value']); $det = 'Monto fijo'; }
        else { $amt = money_round($basic * (float)$c['value'] / 100); $det = num($c['value'], 2) . ' % del básico'; }
        $items[] = ['concept' => $c['name'], 'kind' => 'remunerativo', 'amount' => $amt, 'detail' => $det];
    }
    $otHours = max(0.0, (float)($in['overtime_hours'] ?? 0));
    $otRate  = max(0.0, (float)($in['overtime_rate'] ?? 0));
    if ($otHours > 0 && $otRate > 0) {
        $items[] = ['concept' => 'Horas extra autorizadas', 'kind' => 'remunerativo', 'amount' => money_round($otHours * $otRate),
                    'detail' => num($otHours, 2) . ' h × ' . money($otRate)];
    }
    $bonus = money_round(max(0.0, (float)($in['bonus'] ?? 0)));
    if ($bonus > 0) $items[] = ['concept' => 'Bonificación remunerativa', 'kind' => 'remunerativo', 'amount' => $bonus, 'detail' => 'Ingresada manualmente'];

    $totalRem = 0.0;
    foreach ($items as $i) if ($i['kind'] === 'remunerativo') $totalRem += $i['amount'];
    $totalRem = money_round($totalRem);

    // 2) no remunerativos
    foreach ($concepts as $c) {
        if ($c['kind'] !== 'no_remunerativo') continue;
        $amt = $c['calc_type'] === 'fijo' ? money_round($c['value'])
             : money_round(($c['calc_type'] === 'porcentaje_basico' ? $basic : $totalRem) * (float)$c['value'] / 100);
        $items[] = ['concept' => $c['name'], 'kind' => 'no_remunerativo', 'amount' => $amt, 'detail' => $c['calc_type'] === 'fijo' ? 'Monto fijo' : num($c['value'], 2) . ' %'];
    }
    $nonRem = money_round(max(0.0, (float)($in['non_rem'] ?? 0)));
    if ($nonRem > 0) $items[] = ['concept' => 'Otros conceptos no remunerativos', 'kind' => 'no_remunerativo', 'amount' => $nonRem, 'detail' => 'Ingresado manualmente'];

    // 3) descuentos y retenciones
    foreach ($concepts as $c) {
        if ($c['kind'] !== 'descuento') continue;
        if ($c['calc_type'] === 'fijo') { $amt = money_round($c['value']); $det = 'Monto fijo'; }
        elseif ($c['calc_type'] === 'porcentaje_basico') { $amt = money_round($basic * (float)$c['value'] / 100); $det = num($c['value'], 2) . ' % del básico'; }
        else { $amt = money_round($totalRem * (float)$c['value'] / 100); $det = num($c['value'], 2) . ' % del total remunerativo'; }
        $items[] = ['concept' => $c['name'], 'kind' => 'descuento', 'amount' => $amt, 'detail' => $det];
    }
    if (!empty($in['absence_discount'])) {
        $n = unjustified_absences($employeeId, $period);
        $div = max(1, (int)setting('absence_day_divisor', '30'));
        if ($n > 0) {
            $items[] = ['concept' => 'Ausencias injustificadas', 'kind' => 'descuento', 'amount' => money_round($basic / $div * $n),
                        'detail' => $n . ' día(s) × (básico ÷ ' . $div . ')'];
        }
    }
    $od = money_round(max(0.0, (float)($in['other_deduction'] ?? 0)));
    if ($od > 0) {
        $label = trim((string)($in['other_deduction_label'] ?? '')) ?: 'Otros descuentos';
        $items[] = ['concept' => mb_substr($label, 0, 100), 'kind' => 'descuento', 'amount' => $od, 'detail' => 'Ingresado manualmente'];
    }

    $t = ['rem' => 0.0, 'non_rem' => 0.0, 'ded' => 0.0];
    foreach ($items as $i) {
        if ($i['kind'] === 'remunerativo') $t['rem'] += $i['amount'];
        elseif ($i['kind'] === 'no_remunerativo') $t['non_rem'] += $i['amount'];
        else $t['ded'] += $i['amount'];
    }
    $rem = money_round($t['rem']); $non = money_round($t['non_rem']); $ded = money_round($t['ded']);
    return [
        'employee' => $e, 'period' => $period, 'items' => $items, 'warnings' => $warnings,
        'basic' => $basic, 'total_rem' => $rem, 'total_non_rem' => $non, 'total_ded' => $ded,
        'gross' => money_round($rem + $non), 'net' => money_round($rem + $non - $ded),
    ];
}

/** Guarda la liquidación calculada como borrador. */
function payroll_save(array $calc, string $notes = ''): int
{
    $e = $calc['employee'];
    if (q_one("SELECT id FROM payrolls WHERE employee_id = ? AND period = ? AND status <> 'anulada'", [$e['id'], $calc['period']])) {
        throw new AppException('Ya existe una liquidación vigente de ese empleado para ' . period_label($calc['period']) . '. Anulala primero si querés rehacerla.');
    }
    if ($calc['net'] < 0) throw new AppException('El neto estimado no puede ser negativo. Revisá los descuentos.');
    return with_transaction(function (PDO $pdo) use ($calc, $e, $notes) {
        $pdo->prepare('INSERT INTO payrolls (employee_id, period, basic_salary, total_remunerative, total_non_remunerative, total_deductions, gross_total, net_total, status, notes, generated_by, generated_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$e['id'], $calc['period'], $calc['basic'], $calc['total_rem'], $calc['total_non_rem'], $calc['total_ded'], $calc['gross'], $calc['net'],
                       'borrador', $notes, acting_user_id(), now_sql()]);
        $id = (int)$pdo->lastInsertId();
        foreach ($calc['items'] as $i) {
            $pdo->prepare('INSERT INTO payroll_items (payroll_id, concept, kind, amount, detail) VALUES (?,?,?,?,?)')
                ->execute([$id, $i['concept'], $i['kind'], $i['amount'], $i['detail']]);
        }
        audit('liquidacion_generada', 'payrolls', $id, employee_full_name($e) . ' ' . $calc['period'] . ' neto ' . money($calc['net']));
        return $id;
    });
}

function payroll_approve(int $id): void
{
    $p = q_one('SELECT status FROM payrolls WHERE id = ?', [$id]);
    if (!$p) throw new AppException('La liquidación no existe.');
    if ($p['status'] !== 'borrador') throw new AppException('Solo se pueden aprobar liquidaciones en borrador.');
    q("UPDATE payrolls SET status = 'aprobada', approved_by = ?, approved_at = ? WHERE id = ?", [acting_user_id(), now_sql(), $id]);
    audit('liquidacion_aprobada', 'payrolls', $id, '');
}

/** Marca como pagada y registra el egreso "Sueldos" en Administración (una sola vez). */
function payroll_pay(int $id, int $methodId, ?string $date = null): void
{
    $date = ($date && valid_date($date)) ? $date : today();
    with_transaction(function () use ($id, $methodId, $date) {
        $p = q_one("SELECT p.*, e.first_name, e.last_name FROM payrolls p JOIN employees e ON e.id = p.employee_id WHERE p.id = ? FOR UPDATE", [$id]);
        if (!$p) throw new AppException('La liquidación no existe.');
        if ($p['status'] !== 'aprobada') throw new AppException('Solo se pueden pagar liquidaciones aprobadas.');
        if (!q_one('SELECT id FROM payment_methods WHERE id = ? AND active = 1', [$methodId])) throw new AppException('Elegí el medio de pago.');
        if ((float)$p['net_total'] <= 0) throw new AppException('El neto a pagar debe ser mayor que cero.');
        $cashId = cash_insert([
            'mov_date' => $date, 'type' => 'egreso', 'concept' => 'Sueldo ' . period_label($p['period']) . ' - ' . $p['last_name'] . ', ' . $p['first_name'],
            'category' => 'Sueldos', 'amount' => $p['net_total'], 'status' => 'pagado', 'payment_method_id' => $methodId,
            'cost_type' => 'fijo', 'source' => 'sueldo', 'payroll_id' => $id, 'paid_date' => $date,
        ]);
        q("UPDATE payrolls SET status = 'pagada', paid_at = ?, cash_movement_id = ? WHERE id = ?", [$date . ' ' . date('H:i:s'), $cashId, $id]);
        audit('liquidacion_pagada', 'payrolls', $id, money($p['net_total']));
    });
}

function payroll_void(int $id): void
{
    with_transaction(function () use ($id) {
        $p = q_one('SELECT * FROM payrolls WHERE id = ? FOR UPDATE', [$id]);
        if (!$p) throw new AppException('La liquidación no existe.');
        if ($p['status'] === 'anulada') throw new AppException('La liquidación ya estaba anulada.');
        if ($p['cash_movement_id']) q("UPDATE cash_movements SET status = 'anulado' WHERE id = ?", [$p['cash_movement_id']]);
        q("UPDATE payrolls SET status = 'anulada' WHERE id = ?", [$id]);
        audit('liquidacion_anulada', 'payrolls', $id, 'Estado previo: ' . $p['status']);
    });
}
