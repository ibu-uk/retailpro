<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);

$data        = json_decode(file_get_contents('php://input'), true);
$invoice_id  = (int)($data['invoice_id']  ?? 0);
$items       = $data['items']       ?? [];
$reason      = trim($data['reason'] ?? 'Customer return');
$refund_mode = $data['refund_mode'] ?? 'cash';

if (!$invoice_id || empty($items)) json_response(['error' => 'Missing invoice or items'], 400);

$db   = db();
$user = current_user();

// ── Load invoice ──
$inv = $db->prepare("SELECT * FROM invoices WHERE id=?");
$inv->execute([$invoice_id]);
$inv = $inv->fetch();
if (!$inv) json_response(['error' => 'Invoice not found'], 404);

// BUG FIX 1: Block refund on already-refunded invoice
if ($inv['status'] === 'refunded') {
    json_response(['error' => 'This invoice is already fully refunded'], 400);
}

// BUG FIX 2: Only refund up to what was actually paid
// (can't refund credit invoice with cash if nothing was paid)
$max_refundable = (float)$inv['paid_amount'];

$db->beginTransaction();
try {
    $refund_total = 0;
    $return_lines = []; // for journal entry

    foreach ($items as $ri) {
        $item_id    = (int)$ri['item_id'];
        $qty_return = (int)$ri['qty_return'];
        if ($qty_return <= 0) continue;

        // Load invoice item
        $ii = $db->prepare("SELECT ii.*, p.name as product_name
                             FROM invoice_items ii
                             JOIN products p ON p.id = ii.product_id
                             WHERE ii.id=? AND ii.invoice_id=?");
        $ii->execute([$item_id, $invoice_id]);
        $ii = $ii->fetch();
        if (!$ii) continue;

        // BUG FIX 3: cap qty_return to what was originally bought
        if ($qty_return > (int)$ii['qty']) $qty_return = (int)$ii['qty'];

        // Proportional refund based on actual line total (includes item discount)
        $unit_refund  = $ii['qty'] > 0 ? (float)$ii['total'] / (int)$ii['qty'] : 0;
        $line_refund  = round($unit_refund * $qty_return, 3);
        $refund_total += $line_refund;

        // ── Return stock ──
        $db->prepare("UPDATE stock SET qty = qty + ?
                      WHERE product_id=? AND branch_id=?")
           ->execute([$qty_return, $ii['product_id'], $inv['branch_id']]);

        // ── Restore batch qty (reverse FIFO — add back to newest batch first) ──
        if ($ii['batch_id']) {
            $db->prepare("UPDATE stock_batches SET qty_remaining = qty_remaining + ?
                          WHERE id=?")
               ->execute([$qty_return, $ii['batch_id']]);
        }

        // ── Log stock return movement ──
        $db->prepare("INSERT INTO stock_movements
                       (product_id,branch_id,type,qty,reference,notes,user_id,batch_id,supplier_id)
                       VALUES (?,?,'return',?,?,?,?,?,?)")
           ->execute([
               $ii['product_id'], $inv['branch_id'], $qty_return,
               $inv['invoice_number'],
               "Refund: $reason — {$ii['product_name']} x$qty_return",
               $user['id'],
               $ii['batch_id'] ?? null,
               $ii['supplier_id'] ?? null
           ]);

        // ── Update or delete invoice item ──
        $new_qty = (int)$ii['qty'] - $qty_return;
        if ($new_qty <= 0) {
            $db->prepare("DELETE FROM invoice_items WHERE id=?")->execute([$item_id]);
        } else {
            $new_total = round($unit_refund * $new_qty, 3);
            $db->prepare("UPDATE invoice_items SET qty=?, total=? WHERE id=?")
               ->execute([$new_qty, $new_total, $item_id]);
        }

        $return_lines[] = [
            'product'  => $ii['product_name'],
            'qty'      => $qty_return,
            'amount'   => $line_refund,
            'batch_id' => $ii['batch_id'] ?? null,
        ];
    }

    if ($refund_total <= 0) {
        $db->rollBack();
        json_response(['error' => 'No valid items to refund'], 400);
    }

    // BUG FIX 2 continued: cap refund to what was paid
    if ($refund_mode !== 'credit') {
        $refund_total = min($refund_total, $max_refundable);
    }
    if ($refund_total <= 0) {
        $db->rollBack();
        json_response(['error' => 'Nothing to refund — this invoice has no paid amount (credit sale). Use Credit mode to add to customer balance.'], 400);
    }

    // ── Update invoice totals ──
    $remaining = $db->prepare("SELECT COUNT(*) FROM invoice_items WHERE invoice_id=?");
    $remaining->execute([$invoice_id]);
    $has_items = $remaining->fetchColumn() > 0;

    $new_inv_total  = max(0, (float)$inv['total']       - $refund_total);
    $new_paid       = max(0, (float)$inv['paid_amount'] - ($refund_mode !== 'credit' ? $refund_total : 0));
    $new_status     = !$has_items ? 'refunded' : ($new_paid >= $new_inv_total ? 'paid' : $inv['status']);

    $db->prepare("UPDATE invoices SET total=?, paid_amount=?, status=? WHERE id=?")
       ->execute([$new_inv_total, $new_paid, $new_status, $invoice_id]);

    // ── Handle refund mode ──
    if ($refund_mode === 'credit' && $inv['customer_id'] > 1) {
        // Add credit to customer balance (positive = they have advance)
        $db->prepare("UPDATE customers SET balance = balance + ? WHERE id=?")
           ->execute([$refund_total, $inv['customer_id']]);
    } elseif (in_array($inv['status'], ['credit','partial'])) {
        // BUG FIX 4: if original was credit/partial, reduce what customer owes
        $owed_reduction = min($refund_total, max(0, (float)$inv['total'] - (float)$inv['paid_amount']));
        if ($owed_reduction > 0 && $inv['customer_id'] > 1) {
            $db->prepare("UPDATE customers SET balance = balance + ? WHERE id=?")
               ->execute([$owed_reduction, $inv['customer_id']]);
        }
    }

    // ── Log refund in payments ──
    $db->prepare("INSERT INTO payments
                   (type,reference_id,invoice_id,amount,payment_mode,notes,created_by)
                   VALUES ('customer',?,?,?,?,?,?)")
       ->execute([
           $inv['customer_id'],
           $invoice_id,
           $refund_total,
           $refund_mode,
           'Refund: ' . $inv['invoice_number'] . ' - ' . $reason,
           $user['id']
       ]);

    // ── Reverse journal entry ──
    $db->prepare("INSERT INTO journal_entries
                   (entry_date,reference,description,type,debit_account,credit_account,amount,branch_id,created_by)
                   VALUES (CURDATE(),?,?,?,'4000','1000',?,?,?)")
       ->execute([
           'REF-' . $inv['invoice_number'],
           "Refund on {$inv['invoice_number']}: $reason",
           'payment_out',
           $refund_total,
           $inv['branch_id'],
           $user['id']
       ]);

    $db->commit();
    json_response([
        'success'        => true,
        'refund_amount'  => number_format($refund_total, 3),
        'invoice_number' => $inv['invoice_number'],
        'new_status'     => $new_status,
        'lines'          => count($return_lines),
    ]);

} catch (Exception $e) {
    $db->rollBack();
    json_response(['error' => $e->getMessage()], 500);
}
