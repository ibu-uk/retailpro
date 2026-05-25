<?php
/**
 * invoice_lookup.php  —  Search an invoice for the refund screen
 * Returns full invoice + line items with batch, supplier, expiry data
 */
require_once __DIR__ . '/../includes/config.php';
require_login();

$q = trim($_GET['q'] ?? '');
if (!$q) json_response(['error' => 'No search query'], 400);

$db = db();

// ── Find invoice ──────────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT i.*, c.name as customer_name, b.name as branch_name
    FROM   invoices  i
    LEFT JOIN customers c ON c.id = i.customer_id
    LEFT JOIN branches  b ON b.id = i.branch_id
    WHERE  i.invoice_number LIKE ?
    ORDER  BY i.created_at DESC LIMIT 1
");
$stmt->execute(["%$q%"]);
$inv = $stmt->fetch();

if (!$inv)                       json_response(['error' => 'Invoice not found: ' . $q], 404);
if ($inv['status'] === 'refunded') json_response(['error' => 'This invoice is already fully refunded'], 400);

// ── Load items with full batch + supplier info ────────────────────────────
$items_stmt = $db->prepare("
    SELECT
        ii.id,
        ii.invoice_id,
        ii.product_id,
        ii.qty,
        ii.unit_price,
        ii.total,
        COALESCE(ii.unit_label,'')         as unit_label,
        COALESCE(ii.stock_deduct, ii.qty)  as stock_deduct,
        ii.batch_id,
        ii.supplier_id,

        -- Product info
        p.name                             as product_name,
        COALESCE(p.name_ar,'')             as product_name_ar,
        COALESCE(p.emoji,'📦')             as emoji,

        -- Batch info (from invoice_items.batch_id)
        sb.batch_number,
        sb.lot_number,
        sb.expiry_date,
        sb.received_date,
        sb.qty_remaining                   as batch_qty_remaining,

        -- Supplier info
        s.company                          as supplier_name,
        COALESCE(s.phone,'')               as supplier_phone

    FROM invoice_items ii
    LEFT JOIN products     p  ON p.id  = ii.product_id
    LEFT JOIN stock_batches sb ON sb.id = ii.batch_id
    LEFT JOIN suppliers    s  ON s.id  = ii.supplier_id

    WHERE ii.invoice_id = ?
    ORDER BY ii.id
");
$items_stmt->execute([$inv['id']]);
$items = $items_stmt->fetchAll();

json_response([
    'invoice' => [
        'id'             => (int)$inv['id'],
        'invoice_number' => $inv['invoice_number'],
        'customer_name'  => $inv['customer_name']  ?? 'Walk-in',
        'branch_name'    => $inv['branch_name']    ?? '',
        'payment_mode'   => $inv['payment_mode'],
        'subtotal'       => $inv['subtotal'],
        'discount'       => $inv['discount'],
        'total'          => $inv['total'],
        'paid_amount'    => $inv['paid_amount'] ?? 0,
        'status'         => $inv['status'],
        'created_at'     => $inv['created_at'],
    ],
    'items' => array_map(function($i) {
        return [
            'id'              => (int)$i['id'],
            'product_id'      => (int)$i['product_id'],
            'product_name'    => $i['product_name'],
            'product_name_ar' => $i['product_name_ar'] ?? '',
            'emoji'           => $i['emoji'] ?? '📦',
            'qty'             => (int)$i['qty'],
            'unit_price'      => (float)$i['unit_price'],
            'total'           => (float)$i['total'],
            'unit_label'      => $i['unit_label']   ?? '',
            'stock_deduct'    => (int)($i['stock_deduct'] ?? $i['qty']),
            // Batch
            'batch_id'        => $i['batch_id']     ? (int)$i['batch_id'] : null,
            'batch_number'    => $i['batch_number'] ?? null,
            'lot_number'      => $i['lot_number']   ?? null,
            'expiry_date'     => $i['expiry_date']  ?? null,
            'received_date'   => $i['received_date'] ?? null,
            'batch_remaining' => $i['batch_id'] ? (int)$i['batch_qty_remaining'] : null,
            // Supplier
            'supplier_id'     => $i['supplier_id']   ? (int)$i['supplier_id'] : null,
            'supplier_name'   => $i['supplier_name'] ?? null,
            'supplier_phone'  => $i['supplier_phone'] ?? null,
        ];
    }, $items),
]);
