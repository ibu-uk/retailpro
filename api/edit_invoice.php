<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . BASE . '/reports.php'); exit; }

$invoice_id   = (int)$_POST['invoice_id'];
$payment_mode = $_POST['payment_mode'] ?? 'cash';
$status       = $_POST['status'] ?? 'paid';
$notes        = trim($_POST['notes'] ?? '');
$redirect     = $_POST['redirect'] ?? BASE . '/reports.php';

$db = db();

$stmt = $db->prepare("UPDATE invoices SET payment_mode=?, status=?, notes=? WHERE id=?");
$stmt->execute([$payment_mode, $status, $notes, $invoice_id]);

// If changed to paid, update paid_amount
if ($status === 'paid') {
    $db->prepare("UPDATE invoices SET paid_amount = total WHERE id=? AND paid_amount < total")->execute([$invoice_id]);
}

header('Location: ' . $redirect . '&success=' . urlencode('Invoice updated'));
exit;
