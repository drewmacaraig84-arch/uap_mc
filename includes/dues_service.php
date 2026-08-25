<?php
/**
 * Dues & Payment Business Logic Service
 */

/**
 * Determine the status of a member due based on total paid vs amount due
 *
 * @param float $total_paid
 * @param float $amount_due
 * @return string 'paid'|'partial'|'unpaid'
 */
function calculate_due_status($total_paid, $amount_due) {
    $total_paid = round((float)$total_paid, 2);
    $amount_due = round((float)$amount_due, 2);

    if ($total_paid >= $amount_due && $amount_due > 0) {
        return 'paid';
    } elseif ($total_paid > 0) {
        return 'partial';
    }
    return 'unpaid';
}

/**
 * Generate a sequential official receipt number (UAP-YYYY-XXXXX)
 *
 * @param PDO $pdo
 * @param int|null $year
 * @return string
 */
function generate_receipt_number(PDO $pdo, $year = null) {
    $year = $year ?: date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM receipts WHERE receipt_number LIKE ?");
    $stmt->execute(['UAP-' . $year . '-%']);
    $count = (int)$stmt->fetchColumn();
    return sprintf('UAP-%s-%05d', $year, $count + 1);
}

/**
 * Get remaining balance for a specific member due
 *
 * @param PDO $pdo
 * @param int $member_due_id
 * @return array ['total_due' => float, 'total_paid' => float, 'remaining' => float, 'status' => string]
 */
function get_member_due_summary(PDO $pdo, $member_due_id) {
    $stmt = $pdo->prepare("
        SELECT md.id, 
               COALESCE(md.custom_amount, d.amount) as amount_due,
               COALESCE(SUM(CASE WHEN p.status = 'verified' THEN p.amount_paid ELSE 0 END), 0) as verified_paid
        FROM member_dues md
        JOIN dues d ON md.due_id = d.id
        LEFT JOIN payments p ON p.member_due_id = md.id
        WHERE md.id = ?
        GROUP BY md.id, md.custom_amount, d.amount
    ");
    $stmt->execute([$member_due_id]);
    $row = $stmt->fetch();
    
    if (!$row) {
        return null;
    }

    $amount_due = round((float)$row['amount_due'], 2);
    $verified_paid = round((float)$row['verified_paid'], 2);
    $remaining = max(0, round($amount_due - $verified_paid, 2));

    return [
        'amount_due' => $amount_due,
        'verified_paid' => $verified_paid,
        'remaining' => $remaining,
        'status' => calculate_due_status($verified_paid, $amount_due)
    ];
}
