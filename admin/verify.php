<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$payment_id = (int)$_POST['payment_id'];
$action = $_POST['action'];

$stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    header('Location: dashboard.php');
    exit;
}

if ($action === 'approve') {
    // Mark payment as verified
    $stmt = $pdo->prepare("UPDATE payments SET status = 'verified', verified_at = NOW(), verified_by = ? WHERE id = ?");
    $stmt->execute([current_user_id(), $payment_id]);

    // Recalculate total verified payments for this member_due
    $total_stmt = $pdo->prepare("SELECT SUM(amount_paid) as total FROM payments WHERE member_due_id = ? AND status = 'verified'");
    $total_stmt->execute([$payment['member_due_id']]);
    $total_paid = (float)($total_stmt->fetch()['total'] ?? 0);

    // Get the full due amount
    $due_stmt = $pdo->prepare("SELECT d.amount FROM member_dues md JOIN dues d ON md.due_id = d.id WHERE md.id = ?");
    $due_stmt->execute([$payment['member_due_id']]);
    $due_amount = (float)($due_stmt->fetch()['amount'] ?? 0);

    // Determine new status (use bcmath for precise decimal comparison to avoid 0.04 shortfalls)
    $total_paid_precise = round($total_paid, 2);
    $due_amount_precise = round($due_amount, 2);
    
    if ($total_paid_precise >= $due_amount_precise) {
        $new_status = 'paid';
    } elseif ($total_paid > 0) {
        $new_status = 'partial';
    } else {
        $new_status = 'unpaid';
    }

    $stmt = $pdo->prepare("UPDATE member_dues SET status = ?, total_paid = ? WHERE id = ?");
    $stmt->execute([$new_status, $total_paid_precise, $payment['member_due_id']]);

    // Generate receipt only if fully paid
    if ($new_status === 'paid') {
        $year = date('Y');
        $count = $pdo->query("SELECT COUNT(*) FROM receipts WHERE receipt_number LIKE 'UAP-$year-%'")->fetchColumn();
        $receipt_number = sprintf('UAP-%s-%05d', $year, $count + 1);
        $stmt = $pdo->prepare("INSERT INTO receipts (payment_id, receipt_number) VALUES (?, ?)");
        $stmt->execute([$payment_id, $receipt_number]);
    }

} elseif ($action === 'reject') {
    $stmt = $pdo->prepare("UPDATE payments SET status = 'rejected', verified_at = NOW(), verified_by = ? WHERE id = ?");
    $stmt->execute([current_user_id(), $payment_id]);

    // Revert member_due status based on remaining verified payments
    $total_stmt = $pdo->prepare("SELECT SUM(amount_paid) as total FROM payments WHERE member_due_id = ? AND status = 'verified'");
    $total_stmt->execute([$payment['member_due_id']]);
    $total_paid = (float)($total_stmt->fetch()['total'] ?? 0);

    $new_status = round($total_paid, 2) > 0 ? 'partial' : 'unpaid';
    $stmt = $pdo->prepare("UPDATE member_dues SET status = ?, total_paid = ? WHERE id = ?");
    $stmt->execute([$new_status, round($total_paid, 2), $payment['member_due_id']]);
}

header('Location: dashboard.php?done=1');
exit;
