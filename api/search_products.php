<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$user      = current_user();
$branch_id = (int)($user['branch_id'] ?? 1);
$is_super  = ($user['role'] === 'super_admin');

$db = db();

$barcode = trim($_GET['barcode'] ?? '');
$q       = trim($_GET['q'] ?? '');
$cat     = (int)($_GET['cat'] ?? 0);
$limit   = min(100, max(1, (int)($_GET['limit'] ?? 50)));

$product_params = [$branch_id, $branch_id];
$product_where  = "p.is_active = 1";

if (!$is_super) {
    try {
        $has_bc_stmt = $db->prepare("SELECT COUNT(*) FROM branch_categories WHERE branch_id = ?");
        $has_bc_stmt->execute([$branch_id]);
        $has_bc = $has_bc_stmt->fetchColumn();
        if ($has_bc > 0) {
            $product_where .= " AND p.category_id IN (SELECT category_id FROM branch_categories WHERE branch_id = ?)";
            $product_params[] = $branch_id;
        }
    } catch (Exception $e) {
        // branch_categories table may not exist yet
    }
}

$search_params = [];
if ($barcode !== '') {
    $product_where .= " AND (UPPER(p.barcode) = UPPER(?) OR UPPER(p.sku) = UPPER(?))";
    $search_params = [$barcode, $barcode];
    $limit = 1;
} elseif ($q !== '') {
    $product_where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
    $search_params = ["%$q%", "%$q%", "%$q%"];
}

if ($cat > 0) {
    $product_where .= " AND p.category_id = ?";
    $search_params[] = $cat;
}

$product_params = array_merge($product_params, $search_params);

$stmt = $db->prepare("
    SELECT p.id, p.name, COALESCE(p.name_ar,'') as name_ar,
           COALESCE(p.emoji,'📦') as emoji,
           p.sku, COALESCE(p.barcode,'') as barcode,
           p.category_id,
           COALESCE(c.name,'') as category,
           COALESCE(c.name_ar,'') as category_ar,
           COALESCE(c.emoji,'📦') as cat_emoji,
           p.sub_category_id,
           COALESCE(sc.name,'') as sub_category,
           COALESCE(sc.emoji,'') as sub_cat_emoji,
           p.retail_price, p.wholesale_price,
           p.piece_price, p.piece_wholesale_price,
           p.box_price, p.box_wholesale_price,
           p.unit_type, p.default_pack_size,
           COALESCE(s.qty,0) as stock,
           p.has_expiry,
           COALESCE(p.expiry_alert_days, 90) as expiry_alert_days,
           (SELECT MIN(sb.expiry_date) FROM stock_batches sb
            WHERE sb.product_id = p.id AND sb.branch_id = ?
            AND sb.qty_remaining > 0 AND sb.expiry_date IS NOT NULL) as earliest_expiry
    FROM products p
    LEFT JOIN categories c  ON c.id = p.category_id
    LEFT JOIN categories sc ON sc.id = p.sub_category_id
    LEFT JOIN stock s ON s.product_id = p.id AND s.branch_id = ?
    WHERE $product_where
    ORDER BY c.name, sc.name, p.name
    LIMIT ?
");

$params = array_merge($product_params, [$limit]);
$stmt->execute($params);
$products = $stmt->fetchAll();

$product_ids = array_column($products, 'id');
$branch_stock_map = [];
if (!empty($product_ids)) {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $bs_stmt = $db->prepare("SELECT s.product_id, b.name as branch_name, s.qty
        FROM stock s
        JOIN branches b ON b.id = s.branch_id
        WHERE s.product_id IN ($placeholders) AND s.qty > 0
        ORDER BY s.product_id, s.qty DESC");
    $bs_stmt->execute($product_ids);
    foreach ($bs_stmt->fetchAll() as $row) {
        $branch_stock_map[(int)$row['product_id']][] = [
            'branch' => $row['branch_name'],
            'qty' => (int)$row['qty']
        ];
    }
}

$out = array_map(function($p) use ($branch_stock_map) {
    $unit = $p['unit_type'] ?? 'pc';
    $pack = max(1, (int)($p['default_pack_size'] ?? 1));
    $isBox  = $unit === 'box';
    $isPair = $unit === 'pr';
    $isDoz  = $unit === 'doz';
    return [
        'id'        => (int)$p['id'],
        'name'      => $p['name'],
        'name_ar'   => $p['name_ar'] ?? '',
        'sku'       => $p['sku'],
        'cat'       => $p['category'],
        'subcat'    => $p['sub_category'] ?? '',
        'cat_id'    => (int)$p['category_id'],
        'price'     => $isBox ? (float)($p['piece_price'] ?: $p['retail_price'])
                              : (float)$p['retail_price'],
        'wholesale' => $isBox ? (float)($p['piece_wholesale_price'] ?: $p['wholesale_price'])
                              : (float)$p['wholesale_price'],
        'box_price'        => $isBox ? (float)$p['box_price']           : 0,
        'box_wholesale'    => $isBox ? (float)$p['box_wholesale_price'] : 0,
        'unit'      => $unit,
        'pack_size' => $pack,
        'is_pack'   => $isBox || $isPair || $isDoz,
        'unit_label'=> $isPair ? 'pair' : ($isDoz ? 'dozen' : ($isBox ? "box({$pack}pcs)" : $unit)),
        'stock'     => (int)$p['stock'],
        'branch_stock' => $branch_stock_map[(int)$p['id']] ?? [],
        'emoji'     => $p['emoji'] ?: ($p['sub_cat_emoji'] ?: ($p['cat_emoji'] ?: '📦')),
        'bg'        => 'rgba(67,97,238,0.08)',
        'barcode'      => $p['barcode'] ?? '',
        'has_expiry'   => (int)($p['has_expiry'] ?? 0),
        'alert_days'   => (int)($p['expiry_alert_days'] ?? 90),
        'expiry_date'  => $p['earliest_expiry'] ?? null,
    ];
}, $products);

json_response(['products' => $out]);
