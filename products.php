<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'products';
$page_title   = __('product_management');

$db = db();

// Handle add product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $stmt = $db->prepare("INSERT INTO products (name,name_ar,emoji,sku,barcode,category_id,sub_category_id,brand,origin_country,cost_price,retail_price,wholesale_price,color,size,unit_type,description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        trim($_POST['name']), trim($_POST['name_ar'] ?? ''), trim($_POST['emoji'] ?? ''), trim($_POST['sku']), trim($_POST['barcode'] ?? ''), (int)$_POST['category_id'], 
        !empty($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : null,
        trim($_POST['brand']), trim($_POST['origin_country'] ?? ''), (float)$_POST['cost_price'], (float)$_POST['retail_price'],
        (float)$_POST['wholesale_price'], trim($_POST['color']), trim($_POST['size']),
        $_POST['unit_type'] ?? 'pc', trim($_POST['description'])
    ]);
    $pid = $db->lastInsertId();
    // Add initial stock to each branch
    $branches = $db->query("SELECT id FROM branches WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
    $init_stock = (int)($_POST['initial_stock'] ?? 0);
    foreach ($branches as $bid) {
        $db->prepare("INSERT INTO stock (product_id,branch_id,qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty=qty+?")->execute([$pid,$bid,$init_stock,$init_stock]);
    }
    header('Location: ' . BASE . '/products.php?success=' . urlencode('Product added successfully'));
    exit;
}

// Handle edit product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $stmt = $db->prepare("UPDATE products SET name=?,name_ar=?,emoji=?,sku=?,barcode=?,category_id=?,sub_category_id=?,brand=?,origin_country=?,cost_price=?,retail_price=?,wholesale_price=?,color=?,size=?,unit_type=?,description=? WHERE id=?");
    $stmt->execute([
        trim($_POST['name']), trim($_POST['name_ar'] ?? ''), trim($_POST['emoji'] ?? ''), trim($_POST['sku']), trim($_POST['barcode'] ?? ''), (int)$_POST['category_id'],
        !empty($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : null,
        trim($_POST['brand']), trim($_POST['origin_country'] ?? ''), (float)$_POST['cost_price'], (float)$_POST['retail_price'],
        (float)$_POST['wholesale_price'], trim($_POST['color']), trim($_POST['size']),
        $_POST['unit_type'] ?? 'pc', trim($_POST['description']), (int)$_POST['product_id']
    ]);
    header('Location: ' . BASE . '/products.php?success=' . urlencode('Product updated'));
    exit;
}

// Handle delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pid = (int)$_POST['product_id'];
    // Check if product has invoice items
    $has_sales = $db->prepare("SELECT COUNT(*) FROM invoice_items WHERE product_id=?");
    $has_sales->execute([$pid]);
    if ($has_sales->fetchColumn() > 0) {
        header('Location: ' . BASE . '/products.php?error=' . urlencode('Cannot delete: product has sales history. Disable it instead.'));
        exit;
    }
    $db->prepare("DELETE FROM stock_movements WHERE product_id=?")->execute([$pid]);
    $db->prepare("DELETE FROM stock WHERE product_id=?")->execute([$pid]);
    $db->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
    header('Location: ' . BASE . '/products.php?success=' . urlencode('Product deleted'));
    exit;
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE products SET is_active = 1 - is_active WHERE id = ?")->execute([(int)$_GET['toggle']]);
    header('Location: ' . BASE . '/products.php?success=' . urlencode('Status updated'));
    exit;
}

// Search & filter
$search   = trim($_GET['search'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);
$page_num = max(1, (int)($_GET['p'] ?? 1));
$per_page = 15;
$offset   = ($page_num - 1) * $per_page;

$where = "WHERE p.is_active IN (0,1)";
$params = [];
if ($search) { $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($cat_filter) { $where .= " AND p.category_id = ?"; $params[] = $cat_filter; }

$total_count = $db->prepare("SELECT COUNT(*) FROM products p $where");
$total_count->execute($params);
$total_rows = $total_count->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$stmt = $db->prepare("
    SELECT p.*, c.name as cat_name, c.name_ar as cat_name_ar, c.emoji as cat_emoji, sc.name as sub_category_name, sc.emoji as sub_category_emoji,
           COALESCE(SUM(s.qty),0) as total_stock
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN categories sc ON sc.id = p.sub_category_id
    LEFT JOIN stock s ON s.product_id = p.id
    $where
    GROUP BY p.id ORDER BY p.name LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $db->query("SELECT c.*, pc.name as parent_name FROM categories c LEFT JOIN categories pc ON pc.id=c.parent_id WHERE c.is_active=1 ORDER BY COALESCE(c.parent_id,c.id), c.parent_id IS NOT NULL, c.name")->fetchAll();
$parent_cats = array_filter($categories, fn($c) => !$c['parent_id']);
$units = $db->query("SELECT * FROM units WHERE is_active=1 ORDER BY name")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

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
        <tr><th><?= __('product') ?></th><th><?= __('sku') ?></th><th><?= __('barcode') ?></th><th><?= __('category') ?></th><th><?= __('unit') ?></th><th><?= __('cost_price') ?></th><th><?= __('retail_price') ?></th><th><?= __('wholesale_price') ?></th><th><?= __('stock') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($p['emoji'] . ' ' . $p['name']) ?><?php if ($p['name_ar']) echo '<br><span style="font-size:11px;color:var(--text3)">' . htmlspecialchars($p['name_ar']) . '</span>'; ?></td>
          <td class="font-mono" style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($p['sku']) ?></td>
          <td class="font-mono" style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($p['barcode'] ?: '-') ?></td>
          <td><span class="badge badge-gray"><?= htmlspecialchars($p['cat_name']) ?><?php if ($p['cat_name_ar']) echo ' / ' . htmlspecialchars($p['cat_name_ar']); ?></span></td>
          <td><span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($p['unit_type'] ?: 'pc') ?></span></td>
          <td class="text-muted"><?= fmt_money($p['cost_price']) ?></td>
          <td class="text-green" style="font-weight:600"><?= fmt_money($p['retail_price']) ?></td>
          <td class="text-blue"><?= fmt_money($p['wholesale_price']) ?></td>
          <td style="color:<?= $p['total_stock']<=5?'var(--red)':($p['total_stock']<=10?'var(--amber)':'var(--text2)') ?>;font-weight:600"><?= $p['total_stock'] ?></td>
          <td>
            <span class="badge <?= $p['is_active'] ? 'badge-green' : 'badge-gray' ?>">
              <span class="dot"></span><?= $p['is_active'] ? __('active') : __('inactive') ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn btn-ghost btn-sm" onclick='editProduct(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
              <a href="<?= BASE ?>/products.php?toggle=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" onclick="return confirm('Toggle status?')"><?= $p['is_active'] ? '🔴' : '🟢' ?></a>
              <button class="btn btn-ghost btn-sm" onclick="window.open('<?= BASE ?>/barcode.php?product_id=<?= $p['id'] ?>', '_blank')">🏷️</button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this product? This cannot be undone.')">
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
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a href="?p=<?= $i ?>&search=<?= urlencode($search) ?>&cat=<?= $cat_filter ?>" class="page-link <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- PRODUCT MODAL (Add / Edit) -->
<div class="modal-backdrop" id="product-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="prod-modal-title"><?= __('add_product') ?></div>
      <button class="modal-close" onclick="closeModal('product-modal')">✕</button>
    </div>
    <form method="POST" id="prod-form">
      <input type="hidden" name="action" value="add" id="prod-action">
      <input type="hidden" name="product_id" value="" id="prod-id">
      <div class="modal-body" style="max-height:60vh;overflow-y:auto">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('product_name') ?> *</label><input class="form-input" name="name" id="prod-name" required placeholder="e.g. Chanel Mini Bag"></div>
          <div class="form-group"><label class="form-label"><?= __('sku') ?> *</label><input class="form-input" name="sku" id="prod-sku" required placeholder="e.g. BAG-2841" style="font-family:var(--mono)"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('barcode') ?></label><input class="form-input" name="barcode" id="prod-barcode" placeholder="e.g. 1234567890123" style="font-family:var(--mono)"></div>
        <div class="form-group"><label class="form-label"><?= __('product_name_ar') ?></label><input class="form-input" name="name_ar" id="prod-name-ar" placeholder="مثال: حقيبة شنيل مصغرة" style="direction:rtl"></div>
        <div class="form-group"><label class="form-label">Emoji</label><input class="form-input" name="emoji" id="prod-emoji" placeholder="e.g. 👜, 👗, 💍" style="font-size:24px;text-align:center"></div>
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
          <div class="form-group"><label class="form-label">Origin Country</label>
            <select class="form-select" name="origin_country" id="prod-origin">
            <option value="">-- Select Country --</option>
            <option value="Afghanistan">Afghanistan</option>
            <option value="Albania">Albania</option>
            <option value="Algeria">Algeria</option>
            <option value="Andorra">Andorra</option>
            <option value="Angola">Angola</option>
            <option value="Argentina">Argentina</option>
            <option value="Armenia">Armenia</option>
            <option value="Australia">Australia</option>
            <option value="Austria">Austria</option>
            <option value="Azerbaijan">Azerbaijan</option>
            <option value="Bahrain">Bahrain</option>
            <option value="Bangladesh">Bangladesh</option>
            <option value="Belgium">Belgium</option>
            <option value="Bolivia">Bolivia</option>
            <option value="Brazil">Brazil</option>
            <option value="Bulgaria">Bulgaria</option>
            <option value="Cambodia">Cambodia</option>
            <option value="Canada">Canada</option>
            <option value="Chile">Chile</option>
            <option value="China">China</option>
            <option value="Colombia">Colombia</option>
            <option value="Costa Rica">Costa Rica</option>
            <option value="Croatia">Croatia</option>
            <option value="Cuba">Cuba</option>
            <option value="Cyprus">Cyprus</option>
            <option value="Czech Republic">Czech Republic</option>
            <option value="Denmark">Denmark</option>
            <option value="Egypt">Egypt</option>
            <option value="Estonia">Estonia</option>
            <option value="Finland">Finland</option>
            <option value="France">France</option>
            <option value="Georgia">Georgia</option>
            <option value="Germany">Germany</option>
            <option value="Greece">Greece</option>
            <option value="Hong Kong">Hong Kong</option>
            <option value="Hungary">Hungary</option>
            <option value="India">India</option>
            <option value="Indonesia">Indonesia</option>
            <option value="Iran">Iran</option>
            <option value="Iraq">Iraq</option>
            <option value="Ireland">Ireland</option>
            <option value="Israel">Israel</option>
            <option value="Italy">Italy</option>
            <option value="Japan">Japan</option>
            <option value="Jordan">Jordan</option>
            <option value="Kazakhstan">Kazakhstan</option>
            <option value="Kuwait">Kuwait</option>
            <option value="Latvia">Latvia</option>
            <option value="Lebanon">Lebanon</option>
            <option value="Lithuania">Lithuania</option>
            <option value="Malaysia">Malaysia</option>
            <option value="Mexico">Mexico</option>
            <option value="Monaco">Monaco</option>
            <option value="Morocco">Morocco</option>
            <option value="Netherlands">Netherlands</option>
            <option value="New Zealand">New Zealand</option>
            <option value="Nigeria">Nigeria</option>
            <option value="Norway">Norway</option>
            <option value="Oman">Oman</option>
            <option value="Pakistan">Pakistan</option>
            <option value="Panama">Panama</option>
            <option value="Peru">Peru</option>
            <option value="Philippines">Philippines</option>
            <option value="Poland">Poland</option>
            <option value="Portugal">Portugal</option>
            <option value="Qatar">Qatar</option>
            <option value="Romania">Romania</option>
            <option value="Russia">Russia</option>
            <option value="Saudi Arabia">Saudi Arabia</option>
            <option value="Singapore">Singapore</option>
            <option value="South Africa">South Africa</option>
            <option value="South Korea">South Korea</option>
            <option value="Spain">Spain</option>
            <option value="Sweden">Sweden</option>
            <option value="Switzerland">Switzerland</option>
            <option value="Syria">Syria</option>
            <option value="Taiwan">Taiwan</option>
            <option value="Thailand">Thailand</option>
            <option value="Turkey">Turkey</option>
            <option value="Ukraine">Ukraine</option>
            <option value="United Arab Emirates">United Arab Emirates</option>
            <option value="United Kingdom">United Kingdom</option>
            <option value="United States">United States</option>
            <option value="Vietnam">Vietnam</option>
            <option value="Yemen">Yemen</option>
          </select>
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group"><label class="form-label"><?= __('cost_price') ?> (<?= get_setting('currency', 'KWD') ?>)</label><input class="form-input" name="cost_price" id="prod-cost" type="number" step="0.001" min="0" value="0.000" required></div>
          <div class="form-group"><label class="form-label"><?= __('retail_price') ?> (<?= get_setting('currency', 'KWD') ?>)</label><input class="form-input" name="retail_price" id="prod-retail" type="number" step="0.001" min="0" value="0.000" required></div>
          <div class="form-group"><label class="form-label"><?= __('wholesale_price') ?> (<?= get_setting('currency', 'KWD') ?>)</label><input class="form-input" name="wholesale_price" id="prod-wholesale" type="number" step="0.001" min="0" value="0.000" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('color') ?></label><input class="form-input" name="color" id="prod-color" placeholder="e.g. Black, Red"></div>
          <div class="form-group"><label class="form-label"><?= __('size') ?></label><input class="form-input" name="size" id="prod-size" placeholder="e.g. S, M, L, XL or -1.00, +2.50"></div>
        </div>
        <div class="form-row">
          <div class="form-group" id="prod-stock-wrap"><label class="form-label"><?= __('initial_stock') ?></label><input class="form-input" name="initial_stock" id="prod-stock" type="number" min="0" value="0"></div>
          <div class="form-group"><label class="form-label"><?= __('unit') ?></label>
            <select class="form-select" name="unit_type" id="prod-unit">
              <?php foreach ($units as $u): ?>
              <option value="<?= htmlspecialchars($u['abbreviation']) ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['abbreviation']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
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
$extra_js = '<script>
const CATEGORIES = ' . json_encode($categories) . ';

function openAddProduct() {
  document.getElementById("prod-action").value = "add";
  document.getElementById("prod-id").value = "";
  document.getElementById("prod-form").reset();
  document.getElementById("prod-submit-btn").textContent = "' . __('add_product') . '";
  document.getElementById("prod-stock-wrap").style.display = "block";
  openModal("product-modal");
}

function editProduct(p) {
  document.getElementById("prod-action").value = "edit";
  document.getElementById("prod-id").value = p.id;
  document.getElementById("prod-name").value = p.name;
  document.getElementById("prod-name-ar").value = p.name_ar || "";
  document.getElementById("prod-emoji").value = p.emoji || "";
  document.getElementById("prod-sku").value = p.sku;
  document.getElementById("prod-barcode").value = p.barcode || "";
  document.getElementById("prod-cat").value = p.category_id;
  document.getElementById("prod-subcat").value = p.sub_category_id || "";
  document.getElementById("prod-brand").value = p.brand || "";
  document.getElementById("prod-origin").value = p.origin_country || "";
  document.getElementById("prod-cost").value = parseFloat(p.cost_price).toFixed(3);
  document.getElementById("prod-retail").value = parseFloat(p.retail_price).toFixed(3);
  document.getElementById("prod-wholesale").value = parseFloat(p.wholesale_price).toFixed(3);
  document.getElementById("prod-color").value = p.color || "";
  document.getElementById("prod-size").value = p.size || "";
  document.getElementById("prod-unit").value = p.unit_type || "pc";
  document.getElementById("prod-desc").value = p.description || "";
  document.getElementById("prod-submit-btn").textContent = "' . __('save_product') . '";
  document.getElementById("prod-stock-wrap").style.display = "";
  openModal("product-modal");
}
</script>';
require __DIR__ . '/includes/footer.php';
?>
