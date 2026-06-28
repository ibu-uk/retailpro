<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'products';
$page_title   = __('product_management');

$db       = db();
$currency = get_setting('currency', 'KWD');

// ── PACK UNIT HELPER ──────────────────────────────────────────────────────
// Returns fixed pack_size for pair/dozen, or the posted value for box
function pack_size_for(string $unit, int $posted): int {
    if ($unit === 'pr')  return 2;
    if ($unit === 'doz') return 12;
    if ($unit === 'box') return max(1, $posted);
    return 1; // pc, kg, g, m — no pack logic
}

// ── ADD ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $unit  = trim($_POST['unit_type'] ?? 'pc');
    $psize = pack_size_for($unit, (int)($_POST['default_pack_size'] ?? 1));

    $stmt = $db->prepare("
        INSERT INTO products
          (name,name_ar,emoji,sku,barcode,category_id,sub_category_id,brand,origin_country,
           cost_price,retail_price,wholesale_price,
           box_price,box_wholesale_price,piece_price,piece_wholesale_price,
           default_pack_size,color,size,unit_type,description)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        trim($_POST['name']),
        trim($_POST['name_ar']   ?? ''),
        trim($_POST['emoji']     ?? ''),
        trim($_POST['sku']),
        trim($_POST['barcode']   ?? ''),
        (int)$_POST['category_id'],
        !empty($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : null,
        trim($_POST['brand']          ?? ''),
        trim($_POST['origin_country'] ?? ''),
        (float)($_POST['cost_price']  ?? 0),
        (float)($_POST['retail_price']  ?? 0),   // for box: piece retail price; for pair/doz: pack price
        (float)($_POST['wholesale_price'] ?? 0), // for box: piece wholesale; for pair/doz: pack wholesale
        (float)($_POST['box_price']             ?? 0),
        (float)($_POST['box_wholesale_price']   ?? 0),
        (float)($_POST['piece_price']           ?? 0),
        (float)($_POST['piece_wholesale_price'] ?? 0),
        $psize,
        trim($_POST['color'] ?? ''),
        trim($_POST['size']  ?? ''),
        $unit,
        trim($_POST['description'] ?? ''),
    ]);
    $pid = $db->lastInsertId();

    // Initial stock — always in pieces for box unit
    $init = (int)($_POST['initial_stock'] ?? 0);
    if ($init > 0) {
        $branches = $db->query("SELECT id FROM branches WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($branches as $bid) {
            $db->prepare("INSERT INTO stock (product_id,branch_id,qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty=qty+?")
               ->execute([$pid, $bid, $init, $init]);
        }
    }
    audit_log('create_product', 'products', (int)$pid, null, [
        'name' => trim($_POST['name']),
        'sku' => trim($_POST['sku']),
        'category_id' => (int)$_POST['category_id']
    ]);
    header('Location: ' . BASE . '/products.php?success=' . urlencode('Product added successfully'));
    exit;
}

// ── EDIT ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $unit  = trim($_POST['unit_type'] ?? 'pc');
    $psize = pack_size_for($unit, (int)($_POST['default_pack_size'] ?? 1));
    $pid = (int)$_POST['product_id'];

    $old_stmt = $db->prepare("SELECT * FROM products WHERE id=?");
    $old_stmt->execute([$pid]);
    $old_product = $old_stmt->fetch();

    $db->prepare("
        UPDATE products SET
          name=?,name_ar=?,emoji=?,sku=?,barcode=?,category_id=?,sub_category_id=?,brand=?,origin_country=?,
          cost_price=?,retail_price=?,wholesale_price=?,
          box_price=?,box_wholesale_price=?,piece_price=?,piece_wholesale_price=?,
          default_pack_size=?,color=?,size=?,unit_type=?,description=?
        WHERE id=?
    ")->execute([
        trim($_POST['name']),
        trim($_POST['name_ar']   ?? ''),
        trim($_POST['emoji']     ?? ''),
        trim($_POST['sku']),
        trim($_POST['barcode']   ?? ''),
        (int)$_POST['category_id'],
        !empty($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : null,
        trim($_POST['brand']          ?? ''),
        trim($_POST['origin_country'] ?? ''),
        (float)($_POST['cost_price']  ?? 0),
        (float)($_POST['retail_price']  ?? 0),
        (float)($_POST['wholesale_price'] ?? 0),
        (float)($_POST['box_price']             ?? 0),
        (float)($_POST['box_wholesale_price']   ?? 0),
        (float)($_POST['piece_price']           ?? 0),
        (float)($_POST['piece_wholesale_price'] ?? 0),
        $psize,
        trim($_POST['color'] ?? ''),
        trim($_POST['size']  ?? ''),
        $unit,
        trim($_POST['description'] ?? ''),
        $pid,
    ]);
    audit_log('update_product', 'products', $pid, $old_product, [
        'name' => trim($_POST['name']),
        'sku' => trim($_POST['sku']),
        'category_id' => (int)$_POST['category_id']
    ]);
    header('Location: ' . BASE . '/products.php?success=' . urlencode('Product updated'));
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pid = (int)$_POST['product_id'];
    $has_sales = $db->prepare("SELECT COUNT(*) FROM invoice_items WHERE product_id=?");
    $has_sales->execute([$pid]);
    if ($has_sales->fetchColumn() > 0) {
        header('Location: ' . BASE . '/products.php?error=' . urlencode('Cannot delete: product has sales history. Disable it instead.'));
        exit;
    }
    $old_stmt = $db->prepare("SELECT * FROM products WHERE id=?");
    $old_stmt->execute([$pid]);
    $old_product = $old_stmt->fetch();
    $db->prepare("DELETE FROM stock_movements WHERE product_id=?")->execute([$pid]);
    $db->prepare("DELETE FROM stock WHERE product_id=?")->execute([$pid]);
    $db->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
    audit_log('delete_product', 'products', $pid, $old_product, null);
    header('Location: ' . BASE . '/products.php?success=' . urlencode('Product deleted'));
    exit;
}

// ── TOGGLE STATUS ─────────────────────────────────────────────────────────
if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE products SET is_active = 1 - is_active WHERE id = ?")->execute([(int)$_GET['toggle']]);
    header('Location: ' . BASE . '/products.php?success=' . urlencode('Status updated'));
    exit;
}

// ── LIST ──────────────────────────────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);
$page_num   = max(1, (int)($_GET['p'] ?? 1));
$per_page   = 15;
$offset     = ($page_num - 1) * $per_page;

$where  = "WHERE p.is_active IN (0,1)";
$params = [];
if ($search)     { $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($cat_filter) { $where .= " AND p.category_id = ?"; $params[] = $cat_filter; }

$total_count = $db->prepare("SELECT COUNT(*) FROM products p $where");
$total_count->execute($params);
$total_rows  = $total_count->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$stmt = $db->prepare("
    SELECT p.*, c.name as cat_name, c.name_ar as cat_name_ar, c.emoji as cat_emoji,
           sc.name as sub_category_name, sc.emoji as sub_category_emoji,
           COALESCE(SUM(s.qty),0) as total_stock
    FROM products p
    LEFT JOIN categories c  ON c.id = p.category_id
    LEFT JOIN categories sc ON sc.id = p.sub_category_id
    LEFT JOIN stock s       ON s.product_id = p.id
    $where
    GROUP BY p.id ORDER BY p.name LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories  = $db->query("SELECT c.*, pc.name as parent_name FROM categories c LEFT JOIN categories pc ON pc.id=c.parent_id WHERE c.is_active=1 ORDER BY COALESCE(c.parent_id,c.id), c.parent_id IS NOT NULL, c.name")->fetchAll();
$units       = $db->query("SELECT * FROM units WHERE is_active=1 ORDER BY name")->fetchAll();

// Unit label helper for display
function unit_badge_label(string $unit, int $pack): string {
    if ($unit === 'pr')  return 'Pair (×2)';
    if ($unit === 'doz') return 'Dozen (×12)';
    if ($unit === 'box') return "Box (×$pack)";
    return strtoupper($unit);
}

require __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-error"   style="margin-bottom:16px">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="inv-filters">
  <form method="GET" style="display:flex;gap:8px;flex:1;flex-wrap:wrap">
    <input class="search-input" name="search" placeholder="<?= __('search_products') ?>" style="width:250px" value="<?= htmlspecialchars($search) ?>">
    <select class="search-input" name="cat" style="width:200px" onchange="this.form.submit()">
      <option value=""><?= __('all_categories') ?></option>
      <?php foreach ($categories as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= $cat_filter == $cat['id'] ? 'selected' : '' ?>><?= $cat['parent_id'] ? '&nbsp;&nbsp;↳ ' : '' ?><?= $cat['emoji'] ?> <?= htmlspecialchars($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-ghost"><?= __('search') ?></button>
    <a href="<?= BASE ?>/products.php" class="btn btn-ghost">Reset</a>
  </form>
  <button class="btn btn-primary" onclick="openAddProduct()">+ <?= __('add_product') ?></button>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th><?= __('product') ?></th><th><?= __('sku') ?></th><th><?= __('category') ?></th>
          <th>Unit</th><th><?= __('cost_price') ?></th>
          <th>Retail Price</th><th>Wholesale Price</th>
          <th><?= __('stock') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p):
          $unit  = $p['unit_type'] ?: 'pc';
          $pack  = (int)($p['default_pack_size'] ?? 1) ?: 1;
          $isPack = in_array($unit, ['pair','pr','box','doz','dozen']);
        ?>
        <tr>
          <td style="font-weight:500">
            <?= htmlspecialchars(($p['emoji'] ? $p['emoji'] . ' ' : '') . $p['name']) ?>
            <?php if ($p['name_ar']): ?><br><span style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($p['name_ar']) ?></span><?php endif; ?>
          </td>
          <td class="font-mono" style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($p['sku']) ?></td>
          <td><span class="badge badge-gray"><?= htmlspecialchars($p['cat_name'] ?? '') ?></span></td>
          <td>
            <span class="badge <?= $isPack ? 'badge-amber' : 'badge-blue' ?>" style="font-size:10px">
              <?= htmlspecialchars(unit_badge_label($unit, $pack)) ?>
            </span>
          </td>
          <td class="text-muted" style="font-size:11px"><?= fmt_money($p['cost_price']) ?></td>
          <td style="font-size:11px">
            <?php if ($unit === 'box'): ?>
              <span style="color:var(--green);font-weight:600"><?= fmt_money((float)($p['piece_price'] ?? 0)) ?>/pc</span><br>
              <span style="color:var(--text3);font-size:10px"><?= fmt_money((float)($p['box_price'] ?? 0)) ?>/box</span>
            <?php elseif ($unit === 'pr'): ?>
              <span style="color:var(--green);font-weight:600"><?= fmt_money($p['retail_price']) ?>/pair</span>
            <?php elseif ($unit === 'doz'): ?>
              <span style="color:var(--green);font-weight:600"><?= fmt_money($p['retail_price']) ?>/doz</span>
            <?php else: ?>
              <span style="color:var(--green);font-weight:600"><?= fmt_money($p['retail_price']) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:11px;color:var(--blue)">
            <?php if ($unit === 'box'): ?>
              <?= fmt_money($p['piece_wholesale_price']) ?>/pc<br>
              <span style="font-size:10px;color:var(--text3)"><?= fmt_money($p['box_wholesale_price']) ?>/box</span>
            <?php else: ?>
              <?= fmt_money($p['wholesale_price']) ?>
            <?php endif; ?>
          </td>
          <td style="color:<?= $p['total_stock']<=5?'var(--red)':($p['total_stock']<=10?'var(--amber)':'var(--text2)') ?>;font-weight:600">
            <?= $p['total_stock'] ?>
            <span style="font-size:10px;color:var(--text3);font-weight:400">
              <?= $unit === 'box' ? 'pcs' : ($unit === 'pr' ? 'pairs' : ($unit === 'doz' ? 'doz' : '')) ?>
            </span>
          </td>
          <td>
            <span class="badge <?= $p['is_active'] ? 'badge-green' : 'badge-gray' ?>">
              <span class="dot"></span><?= $p['is_active'] ? __('active') : __('inactive') ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn btn-ghost btn-sm" onclick='editProduct(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
              <a href="<?= BASE ?>/products.php?toggle=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" onclick="return confirm('Toggle status?')"><?= $p['is_active'] ? '🔴' : '🟢' ?></a>
              <button class="btn btn-ghost btn-sm" onclick="window.open('<?= BASE ?>/barcode.php?product_id=<?= $p['id'] ?>','_blank')">🏷️</button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this product?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($products)): ?>
        <tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text3)"><?= __('no_data') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--text3)">
    <span><?= __('showing') ?> <?= count($products) ?> <?= __('of') ?> <?= $total_rows ?></span>
    <div class="pagination">
      <?php for ($i=1; $i<=$total_pages; $i++): ?>
      <a href="?p=<?= $i ?>&search=<?= urlencode($search) ?>&cat=<?= $cat_filter ?>" class="page-link <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PRODUCT MODAL — Add / Edit
     ══════════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="product-modal">
  <div class="modal" style="width:720px;max-width:96vw">
    <div class="modal-header">
      <div class="modal-title" id="prod-modal-title"><?= __('add_product') ?></div>
      <button class="modal-close" onclick="closeModal('product-modal')">✕</button>
    </div>
    <form method="POST" id="prod-form">
      <input type="hidden" name="action"     value="add" id="prod-action">
      <input type="hidden" name="product_id" value=""    id="prod-id">
      <div class="modal-body" style="max-height:75vh;overflow-y:auto">

        <!-- Name / SKU -->
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('product_name') ?> *</label><input class="form-input" name="name" id="prod-name" required placeholder="e.g. Chanel Mini Bag"></div>
          <div class="form-group"><label class="form-label"><?= __('sku') ?> *</label><input class="form-input" name="sku" id="prod-sku" required placeholder="e.g. BAG-2841" style="font-family:var(--mono)"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('barcode') ?></label><input class="form-input" name="barcode" id="prod-barcode" placeholder="e.g. 1234567890123" style="font-family:var(--mono)"></div>
        <div class="form-group"><label class="form-label"><?= __('product_name_ar') ?></label><input class="form-input" name="name_ar" id="prod-name-ar" placeholder="مثال: حقيبة شنيل مصغرة" style="direction:rtl"></div>
        <div class="form-group"><label class="form-label">Emoji</label><input class="form-input" name="emoji" id="prod-emoji" placeholder="e.g. 👜 👗 💍" style="font-size:24px;text-align:center"></div>

        <!-- Category / Brand / Origin -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label"><?= __('category') ?></label>
            <select class="form-select" name="category_id" id="prod-cat">
              <option value="">-- Select Category --</option>
              <?php foreach ($categories as $cat): ?>
              <?php if (!$cat['parent_id']): ?>
              <option value="<?= $cat['id'] ?>"><?= $cat['emoji'] ?> <?= htmlspecialchars($cat['name']) ?></option>
              <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Sub-Category</label>
            <select class="form-select" name="sub_category_id" id="prod-subcat">
              <option value="">-- Select Sub-Category --</option>
              <?php foreach ($categories as $cat): ?>
              <?php if ($cat['parent_id']): ?>
              <option value="<?= $cat['id'] ?>"><?= $cat['emoji'] ?> <?= htmlspecialchars($cat['name']) ?> (<?= $cat['parent_name'] ?>)</option>
              <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label"><?= __('brand') ?></label><input class="form-input" name="brand" id="prod-brand" placeholder="e.g. Chanel"></div>
          <div class="form-group">
            <label class="form-label">Origin Country</label>
            <select class="form-select" name="origin_country" id="prod-origin">
              <option value="">-- Select Country --</option>
              <?php foreach (['Afghanistan','Albania','Algeria','Argentina','Armenia','Australia','Austria','Azerbaijan','Bahrain','Bangladesh','Belgium','Brazil','Bulgaria','Cambodia','Canada','China','Colombia','Croatia','Cyprus','Czech Republic','Denmark','Egypt','Finland','France','Germany','Greece','Hong Kong','Hungary','India','Indonesia','Iran','Iraq','Ireland','Italy','Japan','Jordan','Kazakhstan','Kuwait','Lebanon','Malaysia','Mexico','Morocco','Netherlands','New Zealand','Nigeria','Norway','Oman','Pakistan','Philippines','Poland','Portugal','Qatar','Romania','Russia','Saudi Arabia','Singapore','South Africa','South Korea','Spain','Sweden','Switzerland','Syria','Taiwan','Thailand','Turkey','Ukraine','United Arab Emirates','United Kingdom','United States','Vietnam','Yemen'] as $c): ?>
              <option value="<?= $c ?>"><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Color / Size -->
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('color') ?></label><input class="form-input" name="color" id="prod-color" placeholder="e.g. Black, Red"></div>
          <div class="form-group"><label class="form-label"><?= __('size') ?></label><input class="form-input" name="size" id="prod-size" placeholder="e.g. S, M, L, XL"></div>
        </div>

        <!-- Unit selector -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Unit Type *</label>
            <select class="form-select" name="unit_type" id="prod-unit" onchange="onUnitChange(this.value)">
              <?php foreach ($units as $u): ?>
              <option value="<?= htmlspecialchars($u['abbreviation']) ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['abbreviation']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" id="prod-stock-wrap">
            <label class="form-label"><?= __('initial_stock') ?> <span id="stock-unit-label">(pieces)</span></label>
            <input class="form-input" name="initial_stock" id="prod-stock" type="number" min="0" value="0">
          </div>
        </div>

        <!-- Cost price (always shown) -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" id="lbl-cost">Cost Price (<?= $currency ?>) *</label>
            <input class="form-input" name="cost_price" id="prod-cost" type="number" step="0.001" min="0" value="0.000" required oninput="calcPackPricing()">
          </div>
          <div class="form-group" id="wrap-pack-size" style="display:none">
            <label class="form-label" id="lbl-pack-size">Pieces per box</label>
            <input class="form-input" name="default_pack_size" id="prod-pack-size" type="number" min="1" value="1" oninput="calcPackPricing()">
          </div>
        </div>

        <!-- ── PIECE unit: standard 3-price row ── -->
        <div id="prices-standard">
          <div class="form-row-3">
            <div class="form-group">
              <label class="form-label" id="lbl-retail">Retail Price (<?= $currency ?>) *</label>
              <input class="form-input" name="retail_price" id="prod-retail" type="number" step="0.001" min="0" value="0.000" required>
            </div>
            <div class="form-group">
              <label class="form-label" id="lbl-wholesale">Wholesale Price (<?= $currency ?>) *</label>
              <input class="form-input" name="wholesale_price" id="prod-wholesale" type="number" step="0.001" min="0" value="0.000" required>
            </div>
            <div class="form-group" id="wrap-cost-info" style="display:none">
              <label class="form-label" style="color:var(--text3)">Cost per piece <span style="font-size:10px">(auto)</span></label>
              <input class="form-input" id="prod-cost-per-pc" type="text" readonly style="background:var(--bg3);color:var(--text3)">
            </div>
          </div>
        </div>

        <!-- ── BOX unit: piece prices + box prices panel ── -->
        <div id="prices-box" style="display:none">
          <!-- info bar -->
          <div style="background:var(--bg3);border-radius:6px;padding:10px 14px;margin-bottom:12px;font-size:12px;color:var(--text3);border-left:3px solid var(--amber)">
            💡 Stock is counted in <strong>pieces</strong>. Enter both the <strong>piece price</strong> (for selling loose pieces) and the <strong>box price</strong> (for selling the whole box).
          </div>
          <div style="font-weight:600;font-size:12px;margin-bottom:8px;color:var(--text2)">📦 Per-piece prices (when selling individual pieces)</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Piece Retail Price (<?= $currency ?>)</label>
              <input class="form-input" name="piece_price" id="prod-piece-retail" type="number" step="0.001" min="0" value="0.000">
              <input type="hidden" name="retail_price" id="prod-retail-hidden" value="0">
            </div>
            <div class="form-group">
              <label class="form-label">Piece Wholesale Price (<?= $currency ?>)</label>
              <input class="form-input" name="piece_wholesale_price" id="prod-piece-wholesale" type="number" step="0.001" min="0" value="0.000">
              <input type="hidden" name="wholesale_price" id="prod-wholesale-hidden" value="0">
            </div>
            <div class="form-group">
              <label class="form-label" style="color:var(--text3)">Cost per piece <span style="font-size:10px">(auto)</span></label>
              <input class="form-input" id="prod-box-cpp" type="text" readonly style="background:var(--bg3);color:var(--text3)">
            </div>
          </div>
          <div style="font-weight:600;font-size:12px;margin:10px 0 8px;color:var(--text2)">🛍️ Full-box prices (when selling the whole box)</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Box Retail Price (<?= $currency ?>)</label>
              <input class="form-input" name="box_price" id="prod-box-retail" type="number" step="0.001" min="0" value="0.000">
            </div>
            <div class="form-group">
              <label class="form-label">Box Wholesale Price (<?= $currency ?>)</label>
              <input class="form-input" name="box_wholesale_price" id="prod-box-wholesale" type="number" step="0.001" min="0" value="0.000">
            </div>
          </div>
        </div>

        <!-- hidden fields for box unit (retail_price/wholesale_price are piece prices in box mode) -->
        <input type="hidden" name="box_price"             id="h-box-price"     value="0">
        <input type="hidden" name="box_wholesale_price"   id="h-box-ws"        value="0">
        <input type="hidden" name="piece_price"           id="h-piece-price"   value="0">
        <input type="hidden" name="piece_wholesale_price" id="h-piece-ws"      value="0">

        <div class="form-group"><label class="form-label"><?= __('description') ?></label><textarea class="form-textarea" name="description" id="prod-desc" placeholder="Product description..."></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('product-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary" id="prod-submit-btn"><?= __('save_product') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$cur_js = json_encode($currency);
ob_start(); ?>
<script>
const CURRENCY = <?= $cur_js ?>;

// ── Unit change handler ────────────────────────────────────────────────────
function onUnitChange(unit) {
  const isBox  = unit === 'box';
  const isPair = unit === 'pr';
  const isDoz  = unit === 'doz';
  const isPack = isBox || isPair || isDoz;

  // Show/hide pack size field
  document.getElementById('wrap-pack-size').style.display = isPack ? '' : 'none';
  document.getElementById('lbl-pack-size').textContent =
    isPair ? 'Pieces per pair (locked = 2)' :
    isDoz  ? 'Pieces per dozen (locked = 12)' :
    'Pieces per box';

  const packInput = document.getElementById('prod-pack-size');
  if (isPair) { packInput.value = 2;  packInput.readOnly = true; }
  else if (isDoz) { packInput.value = 12; packInput.readOnly = true; }
  else { packInput.readOnly = false; }

  // Show/hide price panels
  document.getElementById('prices-standard').style.display = isBox ? 'none' : '';
  document.getElementById('prices-box').style.display      = isBox ? '' : 'none';

  // Show cost-per-piece info for pair/dozen
  document.getElementById('wrap-cost-info').style.display = (isPair || isDoz) ? '' : 'none';

  // Update stock label
  const stockLabel = document.getElementById('stock-unit-label');
  stockLabel.textContent =
    isBox  ? '(pieces total)' :
    isPair ? '(pairs)'        :
    isDoz  ? '(dozens)'       : '(pieces)';

  // Update retail/wholesale labels for pair/dozen
  const lblR = document.getElementById('lbl-retail');
  const lblW = document.getElementById('lbl-wholesale');
  if (lblR) {
    lblR.textContent = isPair ? `Retail Price per pair (${CURRENCY}) *`   :
                       isDoz  ? `Retail Price per dozen (${CURRENCY}) *`  :
                       `Retail Price (${CURRENCY}) *`;
    lblW.textContent = isPair ? `Wholesale Price per pair (${CURRENCY}) *` :
                       isDoz  ? `Wholesale Price per dozen (${CURRENCY}) *`:
                       `Wholesale Price (${CURRENCY}) *`;
  }

  calcPackPricing();
}

function calcPackPricing() {
  const unit  = document.getElementById('prod-unit').value;
  const cost  = parseFloat(document.getElementById('prod-cost').value) || 0;
  const pack  = parseInt(document.getElementById('prod-pack-size')?.value) || 1;

  if (unit === 'box') {
    const cpp = pack > 0 ? cost / pack : 0;
    document.getElementById('prod-box-cpp').value = cpp.toFixed(3) + ' ' + CURRENCY;
  }
  if (unit === 'pr' || unit === 'doz') {
    const lockPack = unit === 'pr' ? 2 : 12;
    const cpp = cost / lockPack;
    document.getElementById('prod-cost-per-pc').value = cpp.toFixed(3) + ' ' + CURRENCY;
  }
}

// ── Sync box hidden fields before submit ──────────────────────────────────
document.getElementById('prod-form').addEventListener('submit', function() {
  const unit = document.getElementById('prod-unit').value;
  if (unit === 'box') {
    // retail_price and wholesale_price fields must equal piece prices for box
    document.getElementById('prod-retail-hidden').value    = document.getElementById('prod-piece-retail').value;
    document.getElementById('prod-wholesale-hidden').value = document.getElementById('prod-piece-wholesale').value;
    document.getElementById('h-box-price').value           = document.getElementById('prod-box-retail').value;
    document.getElementById('h-box-ws').value              = document.getElementById('prod-box-wholesale').value;
    document.getElementById('h-piece-price').value         = document.getElementById('prod-piece-retail').value;
    document.getElementById('h-piece-ws').value            = document.getElementById('prod-piece-wholesale').value;
  }
});

// ── Open add modal ─────────────────────────────────────────────────────────
function openAddProduct() {
  document.getElementById('prod-action').value = 'add';
  document.getElementById('prod-id').value     = '';
  document.getElementById('prod-form').reset();
  document.getElementById('prod-submit-btn').textContent = '<?= __('add_product') ?>';
  document.getElementById('prod-stock-wrap').style.display = 'block';
  onUnitChange(document.getElementById('prod-unit').value);
  openModal('product-modal');
}

// ── Open edit modal ────────────────────────────────────────────────────────
function editProduct(p) {
  document.getElementById('prod-action').value   = 'edit';
  document.getElementById('prod-id').value       = p.id;
  document.getElementById('prod-name').value     = p.name;
  document.getElementById('prod-name-ar').value  = p.name_ar  || '';
  document.getElementById('prod-emoji').value    = p.emoji    || '';
  document.getElementById('prod-sku').value      = p.sku;
  document.getElementById('prod-barcode').value  = p.barcode  || '';
  document.getElementById('prod-cat').value      = p.category_id || '';
  document.getElementById('prod-subcat').value   = p.sub_category_id || '';
  document.getElementById('prod-brand').value    = p.brand    || '';
  document.getElementById('prod-origin').value   = p.origin_country || '';
  document.getElementById('prod-cost').value     = parseFloat(p.cost_price  || 0).toFixed(3);
  document.getElementById('prod-color').value    = p.color    || '';
  document.getElementById('prod-size').value     = p.size     || '';
  document.getElementById('prod-desc').value     = p.description || '';

  const unit = p.unit_type || 'pc';
  document.getElementById('prod-unit').value = unit;
  onUnitChange(unit);

  const pack = parseInt(p.default_pack_size) || 1;
  if (document.getElementById('prod-pack-size')) {
    document.getElementById('prod-pack-size').value = pack;
  }

  if (unit === 'box') {
    document.getElementById('prod-piece-retail').value    = parseFloat(p.piece_price           || p.retail_price    || 0).toFixed(3);
    document.getElementById('prod-piece-wholesale').value = parseFloat(p.piece_wholesale_price || p.wholesale_price || 0).toFixed(3);
    document.getElementById('prod-box-retail').value      = parseFloat(p.box_price             || 0).toFixed(3);
    document.getElementById('prod-box-wholesale').value   = parseFloat(p.box_wholesale_price   || 0).toFixed(3);
  } else {
    document.getElementById('prod-retail').value    = parseFloat(p.retail_price    || 0).toFixed(3);
    document.getElementById('prod-wholesale').value = parseFloat(p.wholesale_price || 0).toFixed(3);
  }

  document.getElementById('prod-submit-btn').textContent = '<?= __('save_product') ?>';
  document.getElementById('prod-stock-wrap').style.display = 'none'; // hide initial stock on edit
  calcPackPricing();
  openModal('product-modal');
}
</script>
<?php
$extra_js = ob_get_clean();
require __DIR__ . '/includes/footer.php';
?>
