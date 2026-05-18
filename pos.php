<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'pos';
$page_title   = __('nav_pos');

$db = db();
$branch_id = current_user()['branch_id'] ?? 1;

// Category emoji map
$cat_emojis = ['Bags'=>'👜','Watches'=>'⌚','Clothes'=>'👕','Accessories'=>'💍','Shoes'=>'👟','Wallets'=>'👛'];
$cat_bgs    = ['Bags'=>'rgba(67,97,238,0.08)','Watches'=>'rgba(59,130,246,0.08)','Clothes'=>'rgba(34,197,94,0.08)',
                'Accessories'=>'rgba(236,72,153,0.08)','Shoes'=>'rgba(20,184,166,0.08)','Wallets'=>'rgba(245,158,11,0.08)'];

$products = $db->query("
  SELECT p.id, p.name, p.name_ar, p.sku, p.barcode, p.category_id, c.name as category, c.name_ar as category_ar, p.retail_price, p.wholesale_price, COALESCE(s.qty,0) as stock
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  LEFT JOIN stock s ON s.product_id = p.id AND s.branch_id = $branch_id
  WHERE p.is_active = 1
  ORDER BY c.name, p.name
")->fetchAll();

$categories = $db->query("SELECT DISTINCT name, name_ar FROM categories ORDER BY name")->fetchAll();
$customers  = $db->query("SELECT id, name, phone, type, balance FROM customers WHERE is_active=1 ORDER BY id ASC")->fetchAll();
$currency = get_setting('currency', 'KWD');

// Active offers for POS
$active_offers = $db->query("
  SELECT * FROM offers
  WHERE is_active=1 AND start_date <= CURDATE() AND end_date >= CURDATE()
  AND (usage_limit=0 OR usage_count < usage_limit)
  ORDER BY discount_value DESC
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<style>
/* Barcode scanner bar */
.barcode-bar{display:flex;align-items:center;gap:8px;background:var(--bg2);border:1.5px solid var(--green);border-radius:var(--r2);padding:10px 14px;margin-bottom:12px;position:relative;box-shadow:0 2px 8px rgba(34,197,94,.1)}
.barcode-bar.scanning{border-color:var(--amber);box-shadow:0 0 12px rgba(245,158,11,.15)}
.barcode-icon{font-size:22px;flex-shrink:0}
.barcode-input{flex:1;background:transparent;border:none;color:var(--text);font-size:16px;font-family:var(--mono);font-weight:500;outline:none;letter-spacing:1px}
.barcode-input::placeholder{color:var(--text3);font-family:var(--font);font-weight:400;font-size:13px;letter-spacing:0}
.barcode-status{font-size:11px;color:var(--green);font-weight:500;display:flex;align-items:center;gap:4px;flex-shrink:0}
.barcode-status .pulse{width:8px;height:8px;border-radius:50%;background:var(--green);animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* Customer selector */
.customer-section{padding:10px;border-bottom:1px solid var(--border)}
.customer-tabs{display:flex;gap:4px;margin-bottom:8px}
.cust-tab{flex:1;padding:6px 10px;border-radius:8px;font-size:11px;font-weight:600;text-align:center;cursor:pointer;border:1.5px solid var(--border2);color:var(--text3);transition:all var(--transition);text-transform:uppercase;letter-spacing:.3px;background:var(--bg2)}
.cust-tab:hover{border-color:var(--text3);color:var(--text2)}
.cust-tab.active{border-color:var(--accent);color:var(--accent);background:rgba(67,97,238,.06)}
.cust-search-wrap{position:relative;display:none}
.cust-search-wrap.visible{display:block}
.cust-search{width:100%;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--r);color:var(--text);font-size:12px;font-family:var(--font);padding:8px 10px 8px 30px;outline:none;transition:border-color var(--transition)}
.cust-search:focus{border-color:var(--accent)}
.cust-search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--text3)}
.cust-dropdown{position:absolute;top:100%;left:0;right:0;background:var(--bg2);border:1px solid var(--border2);border-radius:0 0 var(--r) var(--r);max-height:200px;overflow-y:auto;z-index:20;display:none;box-shadow:var(--shadow-md)}
.cust-dropdown.open{display:block}
.cust-option{padding:8px 10px;font-size:12px;cursor:pointer;display:flex;align-items:center;gap:8px;transition:background var(--transition)}
.cust-option:hover{background:var(--bg3)}
.cust-option .cust-av{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;flex-shrink:0}
.cust-option .cust-det{flex:1;min-width:0}
.cust-option .cust-nm{font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text)}
.cust-option .cust-ph{font-size:10px;color:var(--text3)}
.cust-option .cust-tp{font-size:10px;padding:1px 6px;border-radius:99px;font-weight:500}
.cust-selected{display:flex;align-items:center;gap:8px;padding:6px 8px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--r);font-size:12px}
.cust-selected .cust-av{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;flex-shrink:0}
.cust-selected .cust-info{flex:1;min-width:0}
.cust-selected .cust-change{font-size:11px;color:var(--accent);cursor:pointer;flex-shrink:0}
.cust-selected .cust-change:hover{color:var(--accent2)}
.walkin-label{display:flex;align-items:center;gap:8px;padding:6px 8px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--r);font-size:12px}
.walkin-label .walkin-icon{width:28px;height:28px;border-radius:50%;background:rgba(34,197,94,.1);color:var(--green);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.walkin-label .walkin-text{flex:1;font-weight:500;color:var(--text)}
.walkin-label .walkin-badge{font-size:10px;padding:2px 8px;border-radius:99px;background:rgba(34,197,94,.1);color:var(--green2);font-weight:500}
</style>

<div class="pos-layout">
  <!-- PRODUCT PANEL -->
  <div class="pos-products">
    <!-- BARCODE SCANNER -->
    <div class="barcode-bar" id="barcode-bar">
      <span class="barcode-icon">📷</span>
      <input class="barcode-input" id="barcode-input" placeholder="<?= __('scan_barcode') ?>" autocomplete="off" autofocus>
      <div class="barcode-status"><span class="pulse"></span> <?= __('scan_barcode') ?></div>
    </div>

    <div class="search-bar">
      <input class="search-input" id="pos-search" placeholder="🔍  <?= __('search_products') ?>" oninput="filterProducts(this.value)" style="flex:2">
      <select class="search-input" id="pos-cat" onchange="filterProducts(document.getElementById('pos-search').value)" style="flex:1">
        <option value=""><?= __('all_categories') ?></option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?><?php if ($cat['name_ar']) echo ' / ' . htmlspecialchars($cat['name_ar']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="product-grid" id="product-grid"></div>
  </div>

  <!-- CART -->
  <div class="pos-cart">
    <div class="cart-header">
      <span style="font-size:18px">🛒</span>
      <h3><?= __('shopping_cart') ?></h3>
      <span class="badge badge-purple" id="cart-count">0 <?= __('items') ?></span>
      <button class="btn btn-ghost btn-sm" style="margin-left:auto" onclick="clearCart()"><?= __('delete') ?></button>
    </div>

    <!-- CUSTOMER SELECTOR -->
    <div class="customer-section">
      <div class="customer-tabs">
        <div class="cust-tab active" data-tab="walkin" onclick="selectCustomerTab(this)">🚶 <?= __('walk_in') ?></div>
        <div class="cust-tab" data-tab="saved" onclick="selectCustomerTab(this)">👤 <?= __('select_customer') ?></div>
        <div class="cust-tab" data-tab="new" onclick="openModal('quick-customer-modal')">+ <?= __('add') ?></div>
      </div>
      <!-- Walk-in display -->
      <div id="walkin-display" class="walkin-label">
        <div class="walkin-icon">🚶</div>
        <div class="walkin-text"><?= __('walk_in_customer') ?></div>
        <div class="walkin-badge"><?= __('retail') ?></div>
      </div>
      <!-- Saved customer search -->
      <div id="saved-customer-wrap" class="cust-search-wrap">
        <div id="selected-customer-display" style="display:none"></div>
        <div id="customer-search-box">
          <span class="cust-search-icon">🔍</span>
          <input class="cust-search" id="cust-search-input" placeholder="<?= __('search') ?>..." oninput="filterCustomers(this.value)" onfocus="openCustDropdown()">
          <div class="cust-dropdown" id="cust-dropdown"></div>
        </div>
      </div>
      <input type="hidden" id="cart-customer" value="1">
    </div>

    <div class="cart-items" id="cart-items">
      <div id="cart-empty" style="text-align:center;padding:40px 20px;color:var(--text3)">
        <div style="font-size:40px;margin-bottom:8px">🛒</div>
        <div style="font-size:13px"><?= __('add_products_start') ?></div>
      </div>
    </div>

    <div class="cart-footer">
      <div class="cart-totals">
        <div class="cart-row"><span><?= __('subtotal') ?></span><span id="cart-subtotal"><?= $currency ?> 0.000</span></div>
        <div id="promo-applied" style="display:none;padding:4px 0">
          <div class="cart-row" style="color:var(--green);font-size:11px"><span id="promo-label">🎯 <?= __('offer_applied') ?></span><span id="promo-amount">- <?= $currency ?> 0.000</span></div>
        </div>
        <div style="display:flex;gap:4px;margin:6px 0">
          <input type="text" id="promo-code-input" placeholder="<?= __('promo_code') ?>" style="flex:1;padding:4px 8px;font-size:11px;border:1px solid var(--border2);border-radius:4px;background:var(--bg2);color:var(--text);text-transform:uppercase">
          <button type="button" class="btn btn-ghost btn-sm" onclick="applyPromoCode()" style="font-size:10px;padding:4px 8px"><?= __('apply') ?></button>
        </div>
        <div class="cart-row"><span><?= __('extra_disc') ?> <input type="number" id="discount-pct" value="0" min="0" max="100" style="width:36px;background:var(--bg4);border:1px solid var(--border2);border-radius:4px;color:var(--text);padding:2px 4px;font-size:11px" onchange="recalc()">%</span><span id="cart-discount" style="color:var(--red)">- <?= $currency ?> 0.000</span></div>
        <div class="cart-row total"><span><?= __('total') ?></span><span id="cart-total" class="text-green"><?= $currency ?> 0.000</span></div>
      </div>
      <div style="margin-bottom:10px;font-size:12px;color:var(--text3);font-weight:500;text-transform:uppercase;letter-spacing:.5px"><?= __('payment_mode') ?></div>
      <div class="payment-modes">
        <div class="pay-mode active" data-mode="cash" onclick="selectPayMode(this)">💵 <?= __('cash') ?></div>
        <div class="pay-mode" data-mode="knet" onclick="selectPayMode(this)">💳 <?= __('knet') ?></div>
        <div class="pay-mode" data-mode="wamd" onclick="selectPayMode(this)">📱 <?= __('wamd') ?></div>
        <div class="pay-mode" data-mode="transfer" onclick="selectPayMode(this)">🏦 <?= __('transfer') ?></div>
        <div class="pay-mode" data-mode="partial" onclick="selectPayMode(this)">📊 <?= __('partial') ?></div>
        <div class="pay-mode" data-mode="credit" onclick="selectPayMode(this)">💰 <?= __('credit') ?></div>
      </div>
      <div id="partial-pay-box" style="display:none;margin-bottom:8px">
        <label style="font-size:11px;color:var(--text3);margin-bottom:4px;display:block"><?= __('amount_paid_now') ?> (<?= $currency ?>)</label>
        <input type="number" class="form-input" id="partial-amount" step="0.001" min="0.001" placeholder="0.000" style="font-size:14px;font-weight:600">
        <div style="font-size:11px;color:var(--amber);margin-top:4px"><?= __('remaining_credit') ?></div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-ghost w-full" onclick="holdSale()">⏸ <?= __('hold') ?></button>
        <button class="btn btn-green w-full" onclick="processSale()">✓ <?= __('charge') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- QUICK ADD CUSTOMER MODAL -->
<div class="modal-backdrop" id="quick-customer-modal">
  <div class="modal" style="width:440px">
    <div class="modal-header">
      <div class="modal-title"><?= __('quick_add_customer') ?></div>
      <button class="modal-close" onclick="closeModal('quick-customer-modal')">✕</button>
    </div>
    <form id="quick-cust-form" onsubmit="return quickAddCustomer(event)">
      <div class="modal-body">
        <div class="form-group"><label class="form-label"><?= __('full_name') ?> *</label><input class="form-input" id="qc-name" required placeholder="<?= __('customer_name') ?>"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('phone') ?></label><input class="form-input" id="qc-phone" placeholder="+965 9988-7766"></div>
          <div class="form-group"><label class="form-label"><?= __('type') ?></label>
            <select class="form-select" id="qc-type">
              <option value="retail"><?= __('retail') ?></option>
              <option value="wholesale"><?= __('wholesale') ?></option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('credit_limit') ?> (<?= $currency ?>)</label><input class="form-input" id="qc-credit" type="number" step="0.001" min="0" value="0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('quick-customer-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save_select') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_js = '<script>
const PRODUCTS = ' . json_encode(array_map(function($p) use ($cat_emojis, $cat_bgs) {
    return [
        'id'        => (int)$p['id'],
        'name'      => $p['name'],
        'sku'       => $p['sku'],
        'cat'       => $p['category'],
        'cat_id'    => (int)$p['category_id'],
        'price'     => (float)$p['retail_price'],
        'wholesale' => (float)$p['wholesale_price'],
        'stock'     => (int)$p['stock'],
        'emoji'     => $cat_emojis[$p['category']] ?? '📦',
        'bg'        => $cat_bgs[$p['category']] ?? 'rgba(67,97,238,0.08)',
    ];
}, $products)) . ';

const OFFERS = ' . json_encode(array_map(function($o) {
    return [
        'id'        => (int)$o['id'],
        'title'     => $o['title'],
        'type'      => $o['type'],
        'value'     => (float)$o['discount_value'],
        'code'      => $o['promo_code'] ?? '',
        'applies'   => $o['applies_to'],
    ];
}, $active_offers)) . ';

const CUSTOMERS = ' . json_encode(array_map(function($c) {
    return [
        'id'      => (int)$c['id'],
        'name'    => $c['name'],
        'phone'   => $c['phone'] ?? '',
        'type'    => $c['type'],
        'balance' => (float)$c['balance'],
    ];
}, $customers)) . ';

const LANG = ' . json_encode([
    'items' => __('items'),
    'no_phone' => __('no_phone'),
    'change' => __('change'),
    'customer_selected' => __('customer_selected'),
    'selected' => __('selected'),
    'in_stock' => __('in_stock'),
    'no_customers' => __('no_customers'),
    'no_data' => __('no_data'),
    'promo_applied' => __('promo_applied'),
    'promo_not_found' => __('promo_not_found'),
    'no_match_promo' => __('no_match_promo'),
    'enter_promo' => __('enter_promo'),
    'select_customer_credit' => __('select_customer_credit'),
    'network_error' => __('network_error'),
    'sale_failed' => __('sale_failed'),
    'sale_complete' => __('sale_complete'),
    'invoice' => __('invoice'),
    'error' => __('error'),
    'warning' => __('warning'),
    'success' => __('success'),
    'added' => __('add'),
    'out_of_stock' => __('out_of_stock'),
    'customer_added' => __('customer_added'),
    'name_required' => __('full_name'),
    'no_products' => __('no_data'),
    'empty_cart' => __('add_products_start'),
    'hold_msg' => __('hold'),
]) . ';

let cart = [];
let selectedPayMode = "cash";
let selectedCustomerId = 1; // Walk-in by default
let appliedPromo = null; // {id, title, type, value, applies}
let promoDiscount = 0;

// ── BARCODE SCANNER ──
(function() {
  const barcodeInput = document.getElementById("barcode-input");
  const barcodeBar   = document.getElementById("barcode-bar");
  let scanTimeout = null;

  barcodeInput.addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
      e.preventDefault();
      const code = this.value.trim().toUpperCase();
      if (!code) return;

      barcodeBar.classList.add("scanning");
      const found = PRODUCTS.find(p => p.sku.toUpperCase() === code || p.sku.toUpperCase().replace(/[^A-Z0-9]/g,"") === code.replace(/[^A-Z0-9]/g,"") || (p.barcode && p.barcode.toUpperCase() === code) || (p.barcode && p.barcode.toUpperCase().replace(/[^A-Z0-9]/g,"") === code.replace(/[^A-Z0-9]/g,"")));
      if (found) {
        addToCart(found.id);
      } else {
        showToast(LANG.error, "No product found for: " + code, "error");
      }
      this.value = "";
      setTimeout(() => barcodeBar.classList.remove("scanning"), 300);
    }
  });

  // Auto-focus barcode input when typing starts (barcode scanner pattern)
  document.addEventListener("keydown", function(e) {
    const active = document.activeElement;
    const isInput = active && (active.tagName === "INPUT" || active.tagName === "TEXTAREA" || active.tagName === "SELECT");
    if (!isInput && e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
      barcodeInput.focus();
    }
  });
})();

// ── CUSTOMER SELECTOR ──
function selectCustomerTab(el) {
  const tab = el.dataset.tab;
  if (tab === "new") return; // handled by modal

  document.querySelectorAll(".cust-tab").forEach(t => t.classList.remove("active"));
  el.classList.add("active");

  const walkinDisplay = document.getElementById("walkin-display");
  const savedWrap     = document.getElementById("saved-customer-wrap");

  if (tab === "walkin") {
    walkinDisplay.style.display = "flex";
    savedWrap.classList.remove("visible");
    selectedCustomerId = 1;
    document.getElementById("cart-customer").value = 1;
    updatePriceMode("retail");
  } else {
    walkinDisplay.style.display = "none";
    savedWrap.classList.add("visible");
    document.getElementById("selected-customer-display").style.display = "none";
    document.getElementById("customer-search-box").style.display = "block";
    document.getElementById("cust-search-input").value = "";
    document.getElementById("cust-search-input").focus();
    renderCustomerList(CUSTOMERS.filter(c => c.id !== 1));
  }
}

function filterCustomers(q) {
  q = q.toLowerCase();
  const filtered = CUSTOMERS.filter(c => c.id !== 1 && (c.name.toLowerCase().includes(q) || c.phone.includes(q)));
  renderCustomerList(filtered);
  document.getElementById("cust-dropdown").classList.add("open");
}

function renderCustomerList(list) {
  const dd = document.getElementById("cust-dropdown");
  if (!list.length) {
    dd.innerHTML = "<div style=\"padding:12px;text-align:center;font-size:12px;color:var(--text3)\">" + LANG.no_customers + "</div>";
    return;
  }
  dd.innerHTML = list.map(c => {
    const initials = c.name.split(" ").map(w=>w[0]).join("").substring(0,2).toUpperCase();
    const bgColor  = c.type === "wholesale" ? "rgba(67,97,238,.1)" : "rgba(59,130,246,.1)";
    const txtColor = c.type === "wholesale" ? "var(--accent)" : "var(--blue)";
    const tpBg     = c.type === "wholesale" ? "rgba(67,97,238,.1)" : "rgba(59,130,246,.1)";
    const balColor = c.balance < 0 ? "var(--red)" : c.balance > 0 ? "var(--green)" : "var(--text3)";
    return `<div class="cust-option" onclick="pickCustomer(${c.id})">
      <div class="cust-av" style="background:${bgColor};color:${txtColor}">${initials}</div>
      <div class="cust-det">
        <div class="cust-nm">${c.name}</div>
        <div class="cust-ph">${c.phone || LANG.no_phone}</div>
      </div>
      <span class="cust-tp" style="background:${tpBg};color:${txtColor}">${c.type}</span>
      <span style="font-size:11px;font-weight:600;color:${balColor}">${c.balance < 0 ? "-" : ""}<?= $currency ?> ${Math.abs(c.balance).toFixed(3)}</span>
    </div>`;
  }).join("");
}

function openCustDropdown() {
  const dd = document.getElementById("cust-dropdown");
  renderCustomerList(CUSTOMERS.filter(c => c.id !== 1));
  dd.classList.add("open");
}

function pickCustomer(id) {
  const c = CUSTOMERS.find(x => x.id === id);
  if (!c) return;
  selectedCustomerId = c.id;
  document.getElementById("cart-customer").value = c.id;
  document.getElementById("cust-dropdown").classList.remove("open");
  document.getElementById("customer-search-box").style.display = "none";

  const initials = c.name.split(" ").map(w=>w[0]).join("").substring(0,2).toUpperCase();
  const bgColor  = c.type === "wholesale" ? "rgba(67,97,238,.1)" : "rgba(59,130,246,.1)";
  const txtColor = c.type === "wholesale" ? "var(--accent)" : "var(--blue)";
  const balColor = c.balance < 0 ? "var(--red)" : c.balance > 0 ? "var(--green)" : "var(--text3)";

  const display = document.getElementById("selected-customer-display");
  display.style.display = "block";
  display.innerHTML = `<div class="cust-selected">
    <div class="cust-av" style="background:${bgColor};color:${txtColor}">${initials}</div>
    <div class="cust-info">
      <div style="font-weight:500">${c.name}</div>
      <div style="font-size:10px;color:var(--text3)">${c.phone || LANG.no_phone} · ${c.type} · Bal: <span style="color:${balColor};font-weight:600">${c.balance < 0 ? "-" : ""}<?= $currency ?> ${Math.abs(c.balance).toFixed(3)}</span></div>
    </div>
    <span class="cust-change" onclick="changeCustomer()">${LANG.change}</span>
  </div>`;

  updatePriceMode(c.type);
  showToast(LANG.customer_selected, c.name + " " + LANG.selected + ".", "success");
}

function changeCustomer() {
  document.getElementById("selected-customer-display").style.display = "none";
  document.getElementById("customer-search-box").style.display = "block";
  document.getElementById("cust-search-input").value = "";
  document.getElementById("cust-search-input").focus();
  renderCustomerList(CUSTOMERS.filter(c => c.id !== 1));
  document.getElementById("cust-dropdown").classList.add("open");
}

function updatePriceMode(type) {
  // Switch cart prices to wholesale or retail based on customer type
  cart.forEach(item => {
    const p = PRODUCTS.find(x => x.id === item.id);
    if (p) item.price = type === "wholesale" ? p.wholesale : p.price;
  });
  renderCart();
}

// Close dropdown on outside click
document.addEventListener("click", function(e) {
  const dd = document.getElementById("cust-dropdown");
  const wrap = document.getElementById("saved-customer-wrap");
  if (dd && !wrap.contains(e.target)) dd.classList.remove("open");
});

// Quick add customer
function quickAddCustomer(e) {
  e.preventDefault();
  const name = document.getElementById("qc-name").value.trim();
  const phone = document.getElementById("qc-phone").value.trim();
  const type = document.getElementById("qc-type").value;
  const credit = document.getElementById("qc-credit").value;

  if (!name) { showToast(LANG.error, LANG.name_required + ".", "error"); return false; }

  const formData = new FormData();
  formData.append("action","add");
  formData.append("name",name);
  formData.append("phone",phone);
  formData.append("email","");
  formData.append("type",type);
  formData.append("credit_limit",credit);
  formData.append("address","");

  fetch("<?= BASE ?>/customers.php", {method:"POST", body: formData})
    .then(() => {
      showToast(LANG.success, LANG.customer_added + ". " + name, "success");
      closeModal("quick-customer-modal");
      setTimeout(() => location.reload(), 800);
    })
    .catch(() => showToast(LANG.error, LANG.network_error, "error"));

  return false;
}

// ── PRODUCT GRID ──
function renderProductGrid(prods) {
  const grid = document.getElementById("product-grid");
  if (!prods.length) { grid.innerHTML = "<div style=\"padding:40px;text-align:center;color:var(--text3)\">" + LANG.no_products + "</div>"; return; }
  grid.innerHTML = prods.map(p => `
    <div class="product-card" onclick="addToCart(${p.id})">
      <div class="product-img" style="background:${p.bg}">${p.emoji}</div>
      <div class="product-name">${p.name}${p.name_ar ? \'<span style="font-size:11px;color:var(--text3);display:block">\'+p.name_ar+\'</span>\' : \'\'}</div>
      <div class="product-sku">${p.sku}</div>
      <div class="product-price"><?= $currency ?> ${p.price.toFixed(3)}</div>
      <div class="product-stock" style="color:${p.stock<=5?"var(--red)":p.stock<=10?"var(--amber)":"var(--text3)"}">${p.stock} ${LANG.in_stock}${p.stock<=5?" ⚠️":""}</div>
    </div>
  `).join("");
}

function filterProducts(query) {
  const cat = document.getElementById("pos-cat").value.toLowerCase();
  const q   = query.toLowerCase();
  renderProductGrid(PRODUCTS.filter(p =>
    (p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q)) &&
    (!cat || p.cat.toLowerCase() === cat)
  ));
}

function addToCart(id) {
  const p = PRODUCTS.find(x => x.id === id);
  if (!p) return;
  if (p.stock <= 0) { showToast(LANG.out_of_stock, p.name, "error"); return; }
  const existing = cart.find(i => i.id === id);
  // Determine price based on selected customer type
  const custType = CUSTOMERS.find(c => c.id === selectedCustomerId);
  const usePrice = (custType && custType.type === "wholesale") ? p.wholesale : p.price;
  if (existing) {
    existing.qty++;
  } else {
    cart.push({...p, price: usePrice, qty:1, disc:0, cat_id: p.cat_id});
  }
  renderCart();
  showToast(LANG.added, p.name, "success");
}

function changeQty(id, d) {
  const item = cart.find(i => i.id === id);
  if (!item) return;
  item.qty += d;
  if (item.qty <= 0) cart = cart.filter(i => i.id !== id);
  renderCart();
}

function clearCart() { cart = []; appliedPromo = null; promoDiscount = 0; document.getElementById("promo-code-input").value = ""; renderCart(); }

function recalc() {
  const sub = cart.reduce((a,i) => a + i.price*i.qty, 0);
  const itemDiscTotal = cart.reduce((a,i) => a + (i.price*i.qty*(i.disc/100)), 0);
  const afterItemDisc = sub - itemDiscTotal;

  // Auto-apply best matching offer (non-promo-code offers)
  promoDiscount = 0;
  const autoOffer = findBestAutoOffer();
  if (autoOffer && !appliedPromo) { appliedPromo = autoOffer; }
  if (appliedPromo && cart.length) {
    promoDiscount = calcPromoDiscount(appliedPromo, afterItemDisc);
  }
  if (!cart.length) { appliedPromo = null; promoDiscount = 0; }

  const afterPromo = afterItemDisc - promoDiscount;
  const globalDisc = afterPromo * (parseFloat(document.getElementById("discount-pct").value)||0) / 100;
  const total = afterPromo - globalDisc;
  const totalDisc = itemDiscTotal + globalDisc;

  document.getElementById("cart-subtotal").textContent = "<?= $currency ?> " + sub.toFixed(3);
  document.getElementById("cart-discount").textContent = "- <?= $currency ?> " + totalDisc.toFixed(3);
  document.getElementById("cart-total").textContent    = "<?= $currency ?> " + total.toFixed(3);
  document.getElementById("cart-count").textContent    = cart.reduce((a,i)=>a+i.qty,0) + " " + LANG.items;

  // Show promo
  const promoEl = document.getElementById("promo-applied");
  if (appliedPromo && promoDiscount > 0) {
    promoEl.style.display = "block";
    document.getElementById("promo-label").textContent = "🎯 " + appliedPromo.title;
    document.getElementById("promo-amount").textContent = "- <?= $currency ?> " + promoDiscount.toFixed(3);
  } else {
    promoEl.style.display = "none";
  }
}

function findBestAutoOffer() {
  // Find best non-code offer that applies to items in cart
  let best = null;
  let bestVal = 0;
  for (const o of OFFERS) {
    if (o.code) continue; // skip promo-code offers (manual only)
    if (o.type === "bogo") { best = best || o; continue; }
    const disc = calcPromoDiscount(o, cart.reduce((a,i) => a + i.price*i.qty, 0));
    if (disc > bestVal) { bestVal = disc; best = o; }
  }
  return best;
}

function calcPromoDiscount(offer, amount) {
  // Determine which items the offer applies to
  let applicableAmount = 0;
  if (offer.applies === "all") {
    applicableAmount = amount;
  } else {
    const catId = parseInt(offer.applies);
    applicableAmount = cart.filter(i => i.cat_id === catId).reduce((a,i) => a + (i.price*i.qty - i.price*i.qty*(i.disc/100)), 0);
  }
  if (applicableAmount <= 0) return 0;

  if (offer.type === "percent") return applicableAmount * (offer.value / 100);
  if (offer.type === "fixed") return Math.min(offer.value, applicableAmount);
  if (offer.type === "bogo") {
    // BOGO: cheapest applicable item free
    const items = cart.filter(i => offer.applies === "all" || i.cat_id === parseInt(offer.applies));
    if (items.length > 0) {
      const cheapest = Math.min(...items.map(i => i.price));
      return cheapest;
    }
  }
  return 0;
}

function applyPromoCode() {
  const code = document.getElementById("promo-code-input").value.trim().toUpperCase();
  if (!code) { showToast(LANG.error, LANG.enter_promo, "warning"); return; }
  const offer = OFFERS.find(o => o.code && o.code.toUpperCase() === code);
  if (!offer) { showToast(LANG.error, LANG.promo_not_found, "error"); return; }
  appliedPromo = offer;
  recalc();
  if (promoDiscount > 0) {
    showToast(LANG.promo_applied, offer.title + " — <?= $currency ?> " + promoDiscount.toFixed(3), "success");
  } else {
    showToast(LANG.warning, LANG.no_match_promo, "warning");
    appliedPromo = null;
  }
}

function renderCart() {
  const container = document.getElementById("cart-items");
  const empty     = document.getElementById("cart-empty");
  if (!cart.length) { container.innerHTML = ""; container.appendChild(empty||document.createElement("div")); empty&&(empty.style.display="block"); recalc(); return; }
  empty && (empty.style.display = "none");
  let html = "";
  cart.forEach(item => {
    const lineTotal = item.price * item.qty;
    const lineDisc  = lineTotal * (item.disc / 100);
    const lineNet   = lineTotal - lineDisc;
    html += `<div class="cart-item">
      <div class="cart-item-emoji">${item.emoji}</div>
      <div class="cart-item-info">
        <div class="cart-item-name">${item.name}${item.name_ar ? \'<span style="font-size:11px;color:var(--text3);display:block">\'+item.name_ar+\'</span>\' : \'\'}</div>
        <div class="cart-item-price"><?= $currency ?> ${item.price.toFixed(3)} × ${item.qty}${item.disc > 0 ? \' <span style="color:var(--red)">-\'+item.disc+\'%</span>\' : \'\'}</div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
        <div class="cart-item-controls">
          <button class="qty-btn" onclick="changeQty(${item.id},-1)">−</button>
          <div class="qty-num">${item.qty}</div>
          <button class="qty-btn" onclick="changeQty(${item.id},1)">+</button>
        </div>
        <div style="display:flex;align-items:center;gap:3px">
          <input type="number" min="0" max="100" value="${item.disc}" onchange="setItemDisc(${item.id},this.value)" style="width:36px;padding:2px 4px;font-size:10px;border:1px solid var(--border2);border-radius:4px;text-align:center;background:var(--bg2);color:var(--text)">
          <span style="font-size:10px;color:var(--text3)">%</span>
          <span style="font-size:11px;font-weight:600;color:${item.disc>0?\'var(--green2)\':\'var(--text2)\'};min-width:52px;text-align:right">${lineNet.toFixed(3)}</span>
        </div>
      </div>
    </div>`;
  });
  container.innerHTML = html;
  recalc();
}

function setItemDisc(id, val) {
  const item = cart.find(i => i.id === id);
  if (!item) return;
  item.disc = Math.max(0, Math.min(100, parseFloat(val)||0));
  renderCart();
}

function selectPayMode(el) {
  document.querySelectorAll(".pay-mode").forEach(m => m.classList.remove("active"));
  el.classList.add("active");
  selectedPayMode = el.dataset.mode;
  document.getElementById("partial-pay-box").style.display = selectedPayMode === "partial" ? "block" : "none";
}

function holdSale() {
  if (!cart.length) { showToast(LANG.warning, LANG.empty_cart, "warning"); return; }
  showToast(LANG.hold_msg, LANG.success, "warning");
}

function processSale() {
  if (!cart.length) { showToast(LANG.warning, LANG.empty_cart, "warning"); return; }
  const disc = parseFloat(document.getElementById("discount-pct").value)||0;
  const customerId = document.getElementById("cart-customer").value;
  if (selectedPayMode === "partial") {
    const partialAmt = parseFloat(document.getElementById("partial-amount").value)||0;
    if (partialAmt <= 0) { showToast(LANG.error, LANG.error, "warning"); return; }
    if (customerId == 1) { showToast(LANG.error, LANG.select_customer_credit, "warning"); return; }
  }
  if (selectedPayMode === "credit" && customerId == 1) { showToast(LANG.error, LANG.select_customer_credit, "warning"); return; }
  const paidAmount = selectedPayMode === "partial" ? parseFloat(document.getElementById("partial-amount").value)||0 : null;
  const payload = { cart, payment_mode: selectedPayMode, discount_pct: disc, customer_id: customerId, offer_id: appliedPromo ? appliedPromo.id : null, promo_discount: promoDiscount, paid_amount: paidAmount };

  fetch("<?= BASE ?>/api/sale.php", {
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      showToast(LANG.sale_complete, LANG.invoice + " " + data.invoice_number + " — <?= $currency ?> " + data.total, "success");
      clearCart();
      // Reset to walk-in
      document.querySelector(".cust-tab[data-tab=walkin]").click();
    } else {
      showToast(LANG.error, data.error || LANG.sale_failed, "error");
    }
  }).catch(() => showToast(LANG.error, LANG.network_error, "error"));
}

renderProductGrid(PRODUCTS);
</script>';
require __DIR__ . '/includes/footer.php';
?>
