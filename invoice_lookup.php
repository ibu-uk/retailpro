<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$q = trim($_GET['q'] ?? '');
if (!$q) json_response(['error' => 'Enter an invoice number'], 400);

$db   = db();
$user = current_user();
$is_super = ($user['role'] === 'super_admin' && !$user['branch_id']);

// BUG FIX: branch-scoped lookup — cashier can only look up their branch invoices
$branch_filter = $is_super ? "" : "AND i.branch_id = " . (int)$user['branch_id'];

$stmt = $db->prepare("
    SELECT i.*, c.name as customer_name, b.name as branch_name
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN branches  b ON b.id = i.branch_id
    WHERE (i.invoice_number LIKE ? OR i.id = ?)
    $branch_filter
    ORDER BY i.created_at DESC
    LIMIT 1
");
$stmt->execute(["%$q%", (int)$q]);
$inv = $stmt->fetch();

if (!$inv) json_response(['error' => "Invoice \"$q\" not found"], 404);
if ($inv['status'] === 'refunded') json_response(['error' => 'Invoice ' . $inv['invoice_number'] . ' is already fully refunded'], 400);

// Load items with batch + supplier info
$items = $db->prepare("
    SELECT ii.*,
           p.name as product_name,
           COALESCE(p.name_ar,'') as product_name_ar,
           COALESCE(p.emoji,'📦') as emoji,
           COALESCE(sb.batch_number,'') as batch_number,
           COALESCE(sb.expiry_date,'') as expiry_date,
           COALESCE(s.company,'') as supplier_name
    FROM invoice_items ii
    JOIN products p ON p.id = ii.product_id
    LEFT JOIN stock_batches sb ON sb.id = ii.batch_id
    LEFT JOIN suppliers s ON s.id = COALESCE(ii.supplier_id, sb.supplier_id)
    WHERE ii.invoice_id = ?
");
$items->execute([$inv['id']]);
$items = $items->fetchAll();

// Check if any items were already partially refunded
// (by seeing if qty in invoice_items < original qty — tracked via refunds)
// We pass current qty as max returnable

json_response([
    'invoice' => [
        'id'             => (int)$inv['id'],
        'invoice_number' => $inv['invoice_number'],
        'customer_name'  => $inv['customer_name'] ?? 'Walk-in',
        'branch_name'    => $inv['branch_name'],
        'payment_mode'   => $inv['payment_mode'],
        'subtotal'       => (float)$inv['subtotal'],
        'discount'       => (float)$inv['discount'],
        'total'          => (float)$inv['total'],
        'paid_amount'    => (float)$inv['paid_amount'],
        'status'         => $inv['status'],
        'created_at'     => date('d M Y H:i', strtotime($inv['created_at'])),
    ],
    'items' => array_map(fn($i) => [
        'id'             => (int)$i['id'],
        'product_id'     => (int)$i['product_id'],
        'product_name'   => $i['product_name'],
        'product_name_ar'=> $i['product_name_ar'],
        'emoji'          => $i['emoji'],
        'qty'            => (int)$i['qty'],
        'unit_price'     => (float)$i['unit_price'],
        'disc_pct'       => (float)($i['disc_pct'] ?? 0),
        'total'          => (float)$i['total'],
        'batch_number'   => $i['batch_number'],
        'expiry_date'    => $i['expiry_date'],
        'supplier_name'  => $i['supplier_name'],
    ], $items),
]);
