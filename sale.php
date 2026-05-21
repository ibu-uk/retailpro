<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'Method not allowed'], 405);

$data = json_decode(file_get_contents('php://input'), true);
$cart          = $data['cart']         ?? [];
$pay_mode      = $data['payment_mode'] ?? 'cash';
$disc_type     = $data['discount_type'] ?? 'pct';
$disc_value    = (float)($data['discount_value'] ?? 0);
$customer_id   = (int)($data['customer_id'] ?? 1);
$offer_id      = !empty($data['offer_id']) ? (int)$data['offer_id'] : null;
$promo_disc    = (float)($data['promo_discount'] ?? 0);
$partial_paid  = isset($data['paid_amount']) ? (float)$data['paid_amount'] : null;
$user          = current_user();
$branch_id     = $user['branch_id'] ?? 1;

if (empty($cart)) json_response(['error' => 'Cart is empty'], 400);

$db = db();
$db->beginTransaction();

try {
    $subtotal = 0;
    $item_disc_total = 0;
    foreach ($cart as $item) {
        $line = (float)$item['price'] * (int)$item['qty'];
        $subtotal += $line;
        $item_disc_total += $line * ((float)($item['disc'] ?? 0) / 100);
    }
    $after_item_disc = $subtotal - $item_disc_total;
    $after_promo = $after_item_disc - $promo_disc;
    $global_disc = ($disc_type === 'pct') ? $after_promo * ($disc_value / 100) : min($disc_value, $after_promo);
    $discount = $item_disc_total + $promo_disc + $global_disc;
    $total    = $subtotal - $discount;

    // Validate stock FIRST — before creating invoice number or inserting anything
    $chk = $db->prepare("SELECT qty, (SELECT name FROM products WHERE id=?) as pname FROM stock WHERE product_id=? AND branch_id=?");
    foreach ($cart as $item) {
        $pid = (int)$item['id'];
        $chk->execute([$pid, $pid, $branch_id]);
        $row = $chk->fetch();
        $available = $row ? (int)$row['qty'] : 0;
        if ($available < (int)$item['qty']) {
            $pname = $row['pname'] ?? ('Product #' . $pid);
            throw new Exception('Insufficient stock for "' . $pname . '" — available: ' . $available . ', requested: ' . (int)$item['qty']);
        }
    }

    $inv_num = next_invoice_number();

    // Determine status and paid amount
    if ($pay_mode === 'credit') {
        $status = 'credit';
        $paid   = 0;
    } elseif ($pay_mode === 'partial') {
        $paid   = min($partial_paid ?? 0, $total);
        $status = ($paid >= $total) ? 'paid' : 'partial';
    } else {
        $status = 'paid';
        $paid   = $total;
    }

    // Insert invoice
    $stmt = $db->prepare("
        INSERT INTO invoices (invoice_number,customer_id,branch_id,payment_mode,subtotal,discount,vat,total,paid_amount,status,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([$inv_num, $customer_id, $branch_id, $pay_mode, $subtotal, $discount, 0, $total, $paid, $status, $user['id']]);
    $inv_id = $db->lastInsertId();

    // Insert items + deduct stock
    $item_stmt  = $db->prepare("INSERT INTO invoice_items (invoice_id,product_id,qty,unit_price,total) VALUES (?,?,?,?,?)");
    $stock_stmt = $db->prepare("UPDATE stock SET qty = qty - ? WHERE product_id = ? AND branch_id = ? AND qty >= ?");
    $move_stmt  = $db->prepare("INSERT INTO stock_movements (product_id,branch_id,type,qty,reference,user_id) VALUES (?,?,'out',?,?,?)");

    foreach ($cart as $item) {
        $pid   = (int)$item['id'];
        $qty   = (int)$item['qty'];
        $price = (float)$item['price'];
        $iDisc = (float)($item['disc'] ?? 0);
        $lineTotal = $price * $qty;
        $lineNet   = $lineTotal - ($lineTotal * $iDisc / 100);
        $item_stmt->execute([$inv_id, $pid, $qty, $price, $lineNet]);
        $stock_stmt->execute([$qty, $pid, $branch_id, $qty]);
        if ($stock_stmt->rowCount() === 0) {
            throw new Exception('Stock conflict for product ID ' . $pid . ' — please refresh and retry.');
        }
        $move_stmt->execute([$pid, $branch_id, $qty, $inv_num, $user['id']]);
    }

    // Update offer usage count
    if ($offer_id && $promo_disc > 0) {
        $db->prepare("UPDATE offers SET usage_count = usage_count + 1 WHERE id = ?")->execute([$offer_id]);
    }

    // Update customer balance for credit/partial sales (unpaid portion goes on account)
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
