<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
require_role('super_admin', 'manager');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE . '/reports.php');
    exit;
}

$invoice_id   = (int)$_POST['invoice_id'];
$payment_mode = $_POST['payment_mode'] ?? 'cash';
$status       = $_POST['status']       ?? 'paid';
$notes        = trim($_POST['notes']   ?? '');
$paid_amount  = isset($_POST['paid_amount']) ? (float)$_POST['paid_amount'] : null;
$redirect     = $_POST['redirect'] ?? BASE . '/reports.php';

$db = db();
$db->beginTransaction();
try {
    // Get current invoice
    $inv = $db->prepare("SELECT * FROM invoices WHERE id=? FOR UPDATE");
    $inv->execute([$invoice_id]);
    $inv = $inv->fetch();
    if (!$inv) throw new Exception('Invoice not found');

    // Determine new paid_amount
    if ($status === 'paid') {
        $new_paid = $inv['total'];
    } elseif ($paid_amount !== null) {
        $new_paid = min(max(0.0, $paid_amount), $inv['total']);
        if ($new_paid >= $inv['total']) $status = 'paid';
    } else {
        $new_paid = $inv['paid_amount'];
    }

    // If changing from credit to paid, update customer balance (they no longer owe)
    $old_owed = $inv['total'] - $inv['paid_amount'];
    $new_owed = $inv['total'] - $new_paid;
    $balance_diff = $old_owed - $new_owed; // positive = customer paid more

    if ($balance_diff != 0 && $inv['customer_id'] > 1) {
        $db->prepare("UPDATE customers SET balance = balance + ? WHERE id=?")
           ->execute([$balance_diff, $inv['customer_id']]);
    }

    $db->prepare("UPDATE invoices SET payment_mode=?, status=?, notes=?, paid_amount=? WHERE id=?")
       ->execute([$payment_mode, $status, $notes, $new_paid, $invoice_id]);

    $db->commit();
    header('Location: ' . $redirect . '&success=' . urlencode('Invoice updated'));
} catch (Exception $e) {
    $db->rollBack();
    header('Location: ' . $redirect . '&error=' . urlencode($e->getMessage()));
}
exit;
