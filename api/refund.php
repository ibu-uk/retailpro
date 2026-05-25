<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);

$data = json_decode(file_get_contents('php://input'), true);
$invoice_id = (int)($data['invoice_id'] ?? 0);
$items      = $data['items'] ?? []; // [{item_id, qty_return}]
$reason     = trim($data['reason'] ?? 'Customer return');
$refund_mode = $data['refund_mode'] ?? 'cash'; // cash, knet, credit

if (!$invoice_id || empty($items)) json_response(['error' => 'Missing invoice or items'], 400);

$db = db();
$user = current_user();

// Get invoice
$stmt = $db->prepare("SELECT * FROM invoices WHERE id=?");
$stmt->execute([$invoice_id]);
$inv = $stmt->fetch();
if (!$inv) json_response(['error' => 'Invoice not found'], 404);

// ── Refund period check ───────────────────────────────────────────────────
$refund_days = (int)get_setting('refund_period_days', '0');
if ($refund_days > 0) {
    $days_since = (int)((time() - strtotime($inv['created_at'])) / 86400);
    if ($days_since > $refund_days) {
        $inv_date_fmt = date('d M Y', strtotime($inv['created_at']));
        json_response(['error' => "Refund period expired. Returns are only accepted within {$refund_days} days of purchase (Invoice date: {$inv_date_fmt})"], 400);
    }
}

$db->beginTransaction();
try {
    $refund_total = 0;

    foreach ($items as $ri) {
        $item_id    = (int)$ri['item_id'];
        $qty_return = (int)$ri['qty_return'];
        if ($qty_return <= 0) continue;

        // Get invoice item
        $ii_stmt = $db->prepare("SELECT * FROM invoice_items WHERE id=? AND invoice_id=?");
        $ii_stmt->execute([$item_id, $invoice_id]);
        $ii = $ii_stmt->fetch();
        if (!$ii) continue;
        if ($qty_return > $ii['qty']) $qty_return = $ii['qty'];

        // Calculate refund amount (proportional)
        $item_refund = ($ii['total'] / $ii['qty']) * $qty_return;
        $refund_total += $item_refund;

        // Update invoice item qty (reduce)
        $new_qty = $ii['qty'] - $qty_return;
        if ($new_qty <= 0) {
            $db->prepare("DELETE FROM invoice_items WHERE id=?")->execute([$item_id]);
        } else {
            $new_total = ($ii['total'] / $ii['qty']) * $new_qty;
            $db->prepare("UPDATE invoice_items SET qty=?, total=? WHERE id=?")->execute([$new_qty, $new_total, $item_id]);
        }

        // Return stock — use stock_deduct if available (pack unit system)
        // stock_deduct = actual pieces deducted during sale (e.g. 1 box sold = 12 pieces deducted)
        $original_qty     = $ii['qty'];  // selling units (pairs, boxes, etc.)
        $original_deduct  = (int)($ii['stock_deduct'] ?? $original_qty); // pieces actually taken
        // Proportional pieces to restore: returning 1 of 2 boxes sold = restore half the pieces
        $pieces_to_restore = $original_qty > 0
            ? (int)round(($qty_return / $original_qty) * $original_deduct)
            : $qty_return;
        if ($pieces_to_restore < 1) $pieces_to_restore = $qty_return;

        $db->prepare("UPDATE stock SET qty = qty + ? WHERE product_id = ? AND branch_id = ?")
           ->execute([$pieces_to_restore, $ii['product_id'], $inv['branch_id']]);

        // Log stock movement with clear note
        $move_note = $pieces_to_restore !== $qty_return
            ? "{$reason} — returned {$qty_return} units = {$pieces_to_restore} pieces back to stock"
            : $reason;
        $db->prepare("INSERT INTO stock_movements (product_id,branch_id,type,qty,reference,notes,user_id) VALUES (?,?,'return',?,?,?,?)")
           ->execute([$ii['product_id'], $inv['branch_id'], $pieces_to_restore, $inv['invoice_number'], $move_note, $user['id']]);
    }

    if ($refund_total <= 0) {
        $db->rollBack();
        json_response(['error' => 'No valid items to refund'], 400);
    }

    // Update invoice totals
    $new_total = $inv['total'] - $refund_total;
    $remaining_items = $db->prepare("SELECT COUNT(*) FROM invoice_items WHERE invoice_id=?");
    $remaining_items->execute([$invoice_id]);
    $has_items = $remaining_items->fetchColumn() > 0;

    if (!$has_items) {
        // Full refund
        $db->prepare("UPDATE invoices SET status='refunded', total=0, paid_amount=0 WHERE id=?")->execute([$invoice_id]);
    } else {
        // Partial refund
        $db->prepare("UPDATE invoices SET total=?, paid_amount=LEAST(paid_amount,?) WHERE id=?")->execute([$new_total, $new_total, $invoice_id]);
    }

    // Record refund payment
    if ($refund_mode === 'credit' && $inv['customer_id'] > 1) {
        // Add credit to customer balance
        $db->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?")->execute([$refund_total, $inv['customer_id']]);
    }

    // Log the refund in payments table
    $db->prepare("INSERT INTO payments (type,reference_id,invoice_id,amount,payment_mode,notes,created_by) VALUES ('customer',?,?,?,?,?,?)")->execute([
        $inv['customer_id'], $invoice_id, $refund_total,
        in_array($refund_mode, ['cash','knet','wamd','transfer']) ? $refund_mode : 'cash',
        'Refund: ' . $inv['invoice_number'] . ' - ' . $reason, $user['id']
    ]);

    $db->commit();
    json_response([
        'success' => true,
        'refund_amount' => number_format($refund_total, 3),
        'invoice_number' => $inv['invoice_number']
    ]);
} catch (Exception $e) {
    $db->rollBack();
    json_response(['error' => $e->getMessage()], 500);
}
