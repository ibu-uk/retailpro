<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);

$data          = json_decode(file_get_contents('php://input'), true);
$cart          = $data['cart']          ?? [];
$pay_mode      = $data['payment_mode']  ?? 'cash';
$disc_type     = $data['discount_type'] ?? 'pct';
$disc_value    = (float)($data['discount_value']  ?? 0);
$customer_id   = (int)($data['customer_id']        ?? 1);
$offer_id      = !empty($data['offer_id']) ? (int)$data['offer_id'] : null;
$promo_disc    = (float)($data['promo_discount']   ?? 0);
$partial_paid  = isset($data['paid_amount']) ? (float)$data['paid_amount'] : null;
$user          = current_user();
$branch_id     = $user['branch_id'] ?? 1;

if (empty($cart)) json_response(['error' => 'Cart is empty'], 400);

$db = db();
$db->beginTransaction();

try {
    // ── Totals ────────────────────────────────────────────────────────────
    $subtotal        = 0;
    $item_disc_total = 0;
    foreach ($cart as $item) {
        $line             = (float)$item['price'] * (int)$item['qty'];
        $subtotal        += $line;
        $item_disc_total += $line * ((float)($item['disc'] ?? 0) / 100);
    }
    $after_item_disc = $subtotal - $item_disc_total;
    $after_promo     = $after_item_disc - $promo_disc;
    $global_disc     = ($disc_type === 'pct') ? $after_promo * ($disc_value / 100) : min($disc_value, $after_promo);
    $discount        = $item_disc_total + $promo_disc + $global_disc;
    $total           = $subtotal - $discount;

    $inv_num = next_invoice_number();

    if ($pay_mode === 'credit') {
        $status = 'credit'; $paid = 0;
    } elseif ($pay_mode === 'partial') {
        $paid   = min($partial_paid ?? 0, $total);
        $status = ($paid >= $total) ? 'paid' : 'partial';
    } else {
        $status = 'paid'; $paid = $total;
    }

    // ── Insert invoice ────────────────────────────────────────────────────
    $db->prepare("
        INSERT INTO invoices
          (invoice_number,customer_id,branch_id,payment_mode,subtotal,discount,vat,total,paid_amount,status,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ")->execute([$inv_num, $customer_id, $branch_id, $pay_mode, $subtotal, $discount, 0, $total, $paid, $status, $user['id']]);
    $inv_id = $db->lastInsertId();

    // ── Prepared statements ───────────────────────────────────────────────
    $item_stmt  = $db->prepare("
        INSERT INTO invoice_items
          (invoice_id,product_id,qty,unit_price,total,unit_label,stock_deduct,batch_id,supplier_id)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $stock_stmt = $db->prepare("UPDATE stock SET qty = qty - ? WHERE product_id = ? AND branch_id = ?");
    $move_stmt  = $db->prepare("
        INSERT INTO stock_movements
          (product_id,branch_id,type,qty,reference,notes,user_id,batch_id,supplier_id,expiry_date)
        VALUES (?,?,'out',?,?,?,?,?,?,?)
    ");
    // FIFO: get active batches for a product, oldest first
    $batch_stmt = $db->prepare("
        SELECT id, supplier_id, qty_remaining, expiry_date
        FROM   stock_batches
        WHERE  product_id = ? AND branch_id = ?
          AND  qty_remaining > 0 AND status != 'expired'
        ORDER  BY received_date ASC, id ASC
    ");
    $batch_deduct_stmt  = $db->prepare("UPDATE stock_batches SET qty_remaining = qty_remaining - ? WHERE id = ?");
    $batch_deplete_stmt = $db->prepare("UPDATE stock_batches SET status = 'depleted' WHERE id = ? AND qty_remaining <= 0");

    // ── Process each cart item ────────────────────────────────────────────
    foreach ($cart as $item) {
        $pid        = (int)$item['id'];
        $qty        = (int)$item['qty'];          // selling units (pairs, boxes, etc.)
        $price      = (float)$item['price'];
        $iDisc      = (float)($item['disc'] ?? 0);
        $unit_label = $item['unit_label'] ?? '';
        $pack_size  = (int)($item['pack_size'] ?? 1);
        $sell_mode  = $item['sell_mode']   ?? 'unit';

        // Actual pieces to deduct from stock
        $stock_deduct = ($sell_mode === 'box') ? $qty * $pack_size : $qty;

        $lineTotal = $price * $qty;
        $lineNet   = $lineTotal - ($lineTotal * $iDisc / 100);

        // ── FIFO batch deduction ──────────────────────────────────────────
        $batch_stmt->execute([$pid, $branch_id]);
        $batches = $batch_stmt->fetchAll();

        $remaining_to_deduct = $stock_deduct;
        $primary_batch_id    = null;
        $primary_supplier_id = null;
        $primary_expiry      = null;
        $first_batch         = true;

        foreach ($batches as $batch) {
            if ($remaining_to_deduct <= 0) break;

            $take = min($remaining_to_deduct, (int)$batch['qty_remaining']);

            $batch_deduct_stmt->execute([$take, $batch['id']]);
            $batch_deplete_stmt->execute([$batch['id']]);

            $move_note = "Sold {$qty} {$unit_label}" . ($stock_deduct !== $qty ? " (={$stock_deduct} pcs)" : '') . " — Batch #{$batch['id']}";
            $move_stmt->execute([
                $pid, $branch_id, -$take, $inv_num,
                $move_note,
                $user['id'],
                $batch['id'],
                $batch['supplier_id'],
                $batch['expiry_date']
            ]);

            // Record the primary (first/largest) batch for the invoice line
            if ($first_batch) {
                $primary_batch_id    = $batch['id'];
                $primary_supplier_id = $batch['supplier_id'];
                $primary_expiry      = $batch['expiry_date'];
                $first_batch         = false;
            }

            $remaining_to_deduct -= $take;
        }

        // If no batches exist (batch system not used), fall back to plain stock deduct
        if ($first_batch) {
            // No batches found — just log the movement without batch reference
            $move_stmt->execute([
                $pid, $branch_id, -$stock_deduct, $inv_num,
                "Sold {$qty} {$unit_label}" . ($stock_deduct !== $qty ? " (={$stock_deduct} pcs)" : ''),
                $user['id'], null, null, null
            ]);
        }

        // Deduct from main stock table
        $stock_stmt->execute([$stock_deduct, $pid, $branch_id]);

        // Insert invoice item with batch reference
        $item_stmt->execute([
            $inv_id, $pid, $qty, $price, $lineNet,
            $unit_label, $stock_deduct,
            $primary_batch_id,
            $primary_supplier_id
        ]);
    }

    // ── Offers / promotions ───────────────────────────────────────────────
    if ($offer_id && $promo_disc > 0) {
        $db->prepare("UPDATE offers SET usage_count = usage_count + 1 WHERE id = ?")->execute([$offer_id]);
    }

    // ── Customer balance (credit/partial) ────────────────────────────────
    $unpaid = $total - $paid;
    if ($unpaid > 0 && $customer_id > 1) {
        $db->prepare("UPDATE customers SET balance = balance - ? WHERE id = ?")->execute([$unpaid, $customer_id]);
    }

    $db->commit();
    json_response(['success' => true, 'invoice_id' => $inv_id, 'invoice_number' => $inv_num, 'total' => number_format($total, 3)]);

} catch (Exception $e) {
    $db->rollBack();
    json_response(['error' => $e->getMessage()], 500);
}
