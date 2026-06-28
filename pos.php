<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'pos';
$page_title   = __('nav_pos');

$db        = db();
$user      = current_user();
$branch_id = (int)($user['branch_id'] ?? 1);

// ── Branch category filter ──
// super_admin always sees everything regardless of branch assignment
$is_super = ($user['role'] === 'super_admin');

// Products are loaded on-demand via api/search_products.php
$products = [];

// Load categories — super_admin sees all, others see branch-assigned only
if ($is_super) {
    $categories = $db->query("SELECT id, name, COALESCE(name_ar,'') as name_ar, COALESCE(emoji,'📦') as emoji
                               FROM categories WHERE COALESCE(is_active,1)=1 ORDER BY name")->fetchAll();
} else {
    try {
        $has_bc2_stmt = $db->prepare("SELECT COUNT(*) FROM branch_categories WHERE branch_id = ?");
        $has_bc2_stmt->execute([$branch_id]);
        $has_bc2 = $has_bc2_stmt->fetchColumn();
        if ($has_bc2 > 0) {
            $cat_stmt = $db->prepare("SELECT c.id, c.name, COALESCE(c.name_ar,'') as name_ar, COALESCE(c.emoji,'📦') as emoji
                                       FROM categories c
                                       INNER JOIN branch_categories bc ON bc.category_id = c.id
                                       WHERE bc.branch_id = ?
                                       AND COALESCE(c.is_active,1) = 1
                                       ORDER BY c.name");
            $cat_stmt->execute([$branch_id]);
            $categories = $cat_stmt->fetchAll();
        } else {
            // No assignments — show all categories
            $categories = $db->query("SELECT id, name, COALESCE(name_ar,'') as name_ar, COALESCE(c.emoji,'📦') as emoji
                                       FROM categories WHERE COALESCE(is_active,1)=1 ORDER BY name")->fetchAll();
        }
    } catch (Exception $e) {
        $categories = $db->query("SELECT id, name, COALESCE(name_ar,'') as name_ar FROM categories ORDER BY name")->fetchAll();
    }
}
// Customers are loaded on-demand via api/search_customers.php
$customers = [];

$tc       = get_tax_config();
$currency = $tc['currency'] ?: 'KWD';
$decimals = $tc['currency_decimals'];
$tax_rate  = (float)$tc['tax_rate'];
$tax_type  = $tc['tax_type'];
$tax_label = $tc['tax_label'] ?: 'Tax';
$tax_inclusive = $tc['tax_inclusive'] === '1';
$has_tax   = $tax_type !== 'none' && $tax_rate > 0;

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
/* Mobile POS tab switcher */
.pos-mobile-tabs{display:none;background:var(--bg2);border-bottom:1px solid var(--border);margin:-24px -24px 14px}
.pos-mtab{flex:1;padding:12px 8px;font-size:13px;font-weight:600;text-align:center;cursor:pointer;border:none;background:transparent;color:var(--text3);border-bottom:3px solid transparent;transition:all var(--transition)}
.pos-mtab.active{color:var(--accent2);border-bottom-color:var(--accent);background:rgba(124,110,255,.05)}
@media(max-width:900px){.pos-mobile-tabs{display:flex}}
@media(max-width:900px){.pos-products.hidden-mobile{display:none!important}.pos-cart.hidden-mobile{display:none!important}}
</style>

<!-- MOBILE POS TABS -->
<div class="pos-mobile-tabs" id="pos-mobile-tabs">
  <button class="pos-mtab active" id="mtab-products" onclick="showPosPanel('products')">🛍 Products</button>
  <button class="pos-mtab" id="mtab-cart" onclick="showPosPanel('cart')">🛒 Cart <span id="mtab-cart-count" style="background:var(--accent);color:#fff;padding:1px 6px;border-radius:99px;font-size:10px;margin-left:4px">0</span></button>
</div>
<div class="pos-layout">
  <!-- PRODUCT PANEL -->
  <div class="pos-products">
    <!-- BARCODE SCANNER -->
    <div class="barcode-bar" id="barcode-bar">
      <span class="barcode-icon">📷</span>
      <input class="barcode-input" id="barcode-input" placeholder="<?= __('scan_barcode') ?>" autocomplete="off" autofocus dir="ltr" style="unicode-bidi:embed">
      <div class="barcode-status"><span class="pulse"></span> <?= __('scan_barcode') ?></div>
    </div>

    <div class="search-bar">
      <input class="search-input" id="pos-search" placeholder="🔍  <?= __('search_products') ?>" oninput="filterProducts(this.value)" style="flex:2">
      <select class="search-input" id="pos-cat" onchange="filterProducts(document.getElementById('pos-search').value)" style="flex:1">
        <option value=""><?= __('all_categories') ?></option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?><?php if ($cat['name_ar']) echo ' / ' . htmlspecialchars($cat['name_ar']); ?></option>
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
      <button class="btn btn-ghost btn-sm" style="margin-left:auto" onclick="holdSale()"><?= __('hold') ?></button>
      <button class="btn btn-ghost btn-sm" onclick="showHoldModal()"><?= __('resume') ?></button>
      <button class="btn btn-ghost btn-sm" onclick="clearCart()"><?= __('delete') ?></button>
    </div>

    <!-- CUSTOMER SELECTOR -->
    <div class="customer-section">
      <div class="customer-tabs">
        <div class="cust-tab active" data-tab="walkin" onclick="selectCustomerTab(this)">🚶 <?= __('walk_in') ?></div>
        <div class="cust-tab" data-tab="saved" onclick="selectCustomerTab(this)">👤 <?= __('select_customer') ?></div>
        <div class="cust-tab" data-tab="new" onclick="openQuickAddModal()">+ <?= __('add') ?></div>
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
        <div class="cart-row"><span><?= __('subtotal') ?></span><span id="cart-subtotal">0.000</span></div>
        <div id="promo-applied" style="display:none;padding:4px 0">
          <div class="cart-row" style="color:var(--green);font-size:11px"><span id="promo-label">🎯 <?= __('offer_applied') ?></span><span id="promo-amount">- 0.000</span></div>
        </div>
        <div style="display:flex;gap:4px;margin:6px 0">
          <input type="text" id="promo-code-input" placeholder="<?= __('promo_code') ?>" dir="ltr" style="flex:1;padding:4px 8px;font-size:11px;border:1px solid var(--border2);border-radius:4px;background:var(--bg2);color:var(--text);text-transform:uppercase">
          <button type="button" class="btn btn-ghost btn-sm" onclick="applyPromoCode()" style="font-size:10px;padding:4px 8px"><?= __('apply') ?></button>
        </div>
        <div class="cart-row"><span><?= __('extra_disc') ?></span>
          <div style="display:flex;gap:4px;align-items:center">
            <button type="button" class="btn btn-ghost btn-sm" id="disc-type-pct" onclick="setDiscType('pct')" style="padding:2px 8px;font-size:10px;border:1px solid var(--accent);background:rgba(67,97,238,.1);color:var(--accent)">%</button>
            <button type="button" class="btn btn-ghost btn-sm" id="disc-type-fixed" onclick="setDiscType('fixed')" style="padding:2px 8px;font-size:10px;border:1px solid var(--border2);background:var(--bg2);color:var(--text3)"><?= $currency ?></button>
            <input type="number" id="discount-value" value="0" min="0" max="100" dir="ltr" style="width:50px;background:var(--bg4);border:1px solid var(--border2);border-radius:4px;color:var(--text);padding:2px 4px;font-size:11px" onchange="recalc()">
          </div>
          <span id="cart-discount" style="color:var(--red)">- 0.000</span>
        </div>
        <?php if ($has_tax): ?>
        <div class="cart-row" id="tax-row" style="color:var(--amber)">
          <span><?= htmlspecialchars($tax_label) ?> (<?= $tax_rate ?>%<?= $tax_inclusive ? ' incl.' : '' ?>)</span>
          <span id="cart-tax">0.000</span>
        </div>
        <?php endif; ?>
        <div class="cart-row total"><span><?= __('total') ?></span><span id="cart-total" class="text-green">0.000</span></div>
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
        <input type="number" class="form-input" id="partial-amount" step="0.001" min="0.001" dir="ltr" placeholder="0.000" style="font-size:14px;font-weight:600">
        <div style="font-size:11px;color:var(--amber);margin-top:4px"><?= __('remaining_credit') ?></div>
      </div>
      <button class="btn btn-green w-full" onclick="processSale()">✓ <?= __('charge') ?></button>
    </div>
  </div>
</div>

<!-- HOLD / RESUME SALE MODAL -->
<div class="modal-backdrop" id="hold-modal">
  <div class="modal" style="width:420px">
    <div class="modal-header">
      <div class="modal-title"><?= __('held_sales') ?></div>
      <button class="modal-close" onclick="closeModal('hold-modal')">✕</button>
    </div>
    <div class="modal-body">
      <div id="hold-list"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" onclick="closeModal('hold-modal')"><?= __('close') ?></button>
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
        <div class="form-group" id="qc-credit-row">
          <label class="form-label"><?= __('credit_limit') ?> (<?= $currency ?>)
            <span style="font-size:10px;color:var(--text3);font-weight:400"> — max credit allowed for deferred payments</span>
          </label>
          <input class="form-input" id="qc-credit" type="number" step="0.001" min="0" value="0" dir="ltr">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('quick-customer-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save_select') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
// Build data arrays safely — output directly, NOT inside single-quoted string
$products_data = [];

$offers_data = array_map(function($o) {
    return [
        'id'      => (int)$o['id'],
        'title'   => $o['title'],
        'type'    => $o['type'],
        'value'   => (float)$o['discount_value'],
        'code'    => $o['promo_code'] ?? '',
        'applies' => $o['applies_to'],
    ];
}, $active_offers);

$customers_data = [];

ob_start(); ?>
<script>
const PRODUCTS  = <?= json_encode($products_data,  JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) ?>;
const OFFERS    = <?= json_encode($offers_data,     JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) ?>;
const CUSTOMERS = <?= json_encode($customers_data,  JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) ?>;
const LANG      = <?= json_encode([
    'items'                  => __('items'),
    'no_phone'               => __('no_phone'),
    'change'                 => __('change'),
    'customer_selected'      => __('customer_selected'),
    'selected'               => __('selected'),
    'in_stock'               => __('in_stock'),
    'no_customers'           => __('no_customers'),
    'no_data'                => __('no_data'),
    'promo_applied'          => __('promo_applied'),
    'promo_not_found'        => __('promo_not_found'),
    'no_match_promo'         => __('no_match_promo'),
    'enter_promo'            => __('enter_promo'),
    'select_customer_credit' => __('select_customer_credit'),
    'network_error'          => __('network_error'),
    'sale_failed'            => __('sale_failed'),
    'sale_complete'          => __('sale_complete'),
    'invoice'                => __('invoice'),
    'error'                  => __('error'),
    'warning'                => __('warning'),
    'success'                => __('success'),
    'added'                  => __('add'),
    'out_of_stock'           => __('out_of_stock'),
    'customer_added'         => __('customer_added'),
    'name_required'          => __('full_name'),
    'no_products'            => __('no_data'),
    'empty_cart'             => __('add_products_start'),
    'hold_msg'               => __('hold'),
    'hold'                   => __('hold'),
    'resume'                 => __('resume'),
    'held_sales'             => __('held_sales'),
    'no_held_sales'          => __('no_held_sales'),
    'hold_resumed'           => __('hold_resumed'),
], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) ?>;
const CURRENCY = "<?= $currency ?>";
const BASE_URL = "<?= BASE ?>";

let cart = [];
let productCache = {};
let customerCache = {};
let selectedPayMode = "cash";
let selectedCustomerId = 1; // Walk-in by default
let appliedPromo = null; // {id, title, type, value, applies}
let promoDiscount = 0;
let discountType = "pct"; // "pct" for percentage, "fixed" for fixed KWD

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
      const url = BASE_URL + "/api/search_products.php?barcode=" + encodeURIComponent(code) + "&limit=1";
      fetch(url)
        .then(r => r.json())
        .then(data => {
          if (data.products && data.products[0]) {
            const p = data.products[0];
            p.expiry_badge = getExpiryBadge(p);
            productCache[p.id] = p;
            addProductToCart(p);
          } else {
            showToast(LANG.error, "No product found for: " + code, "error");
          }
        })
        .catch(() => showToast(LANG.error, LANG.network_error, "error"))
        .finally(() => {
          this.value = "";
          setTimeout(() => barcodeBar.classList.remove("scanning"), 300);
        });
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
    renderCustomerList([]);
  }
}

let custSearchTimeout = null;
function filterCustomers(q) {
  const dd = document.getElementById("cust-dropdown");
  if (!q) {
    renderCustomerList([]);
    dd.classList.add("open");
    return;
  }
  if (custSearchTimeout) clearTimeout(custSearchTimeout);
  custSearchTimeout = setTimeout(function() { searchCustomers(q); }, 250);
}

function searchCustomers(q) {
  const dd = document.getElementById("cust-dropdown");
  const url = BASE_URL + "/api/search_customers.php?q=" + encodeURIComponent(q) + "&limit=10";
  fetch(url)
    .then(r => r.json())
    .then(data => {
      const list = data.customers || [];
      list.forEach(c => customerCache[c.id] = c);
      renderCustomerList(list);
      dd.classList.add("open");
    })
    .catch(() => showToast(LANG.error, LANG.network_error, "error"));
}

function renderCustomerList(list) {
  const dd = document.getElementById("cust-dropdown");
  if (!list.length) {
    dd.innerHTML = '<div style="padding:12px;text-align:center;font-size:12px;color:var(--text3)">' + LANG.no_customers + '</div>';
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
        <div class="cust-nm">${c.name}${c.company ? '<span style="font-size:10px;color:var(--accent2);margin-left:4px">🏢 '+c.company+'</span>' : ''}</div>
        <div class="cust-ph">${c.phone || LANG.no_phone}</div>
      </div>
      <span class="cust-tp" style="background:${tpBg};color:${txtColor}">${c.type}</span>
      <span style="font-size:11px;font-weight:600;color:${balColor}">${c.balance < 0 ? "-" : ""}<?= $currency ?> ${Math.abs(c.balance).toFixed(3)}</span>
    </div>`;
  }).join("");
}

function openCustDropdown() {
  const dd = document.getElementById("cust-dropdown");
  renderCustomerList([]);
  dd.classList.add("open");
}

function pickCustomerFromData(c) {
  if (!c) return;
  customerCache[c.id] = c;
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
      <div style="font-size:10px;color:var(--text3)">${c.company ? '🏢 '+c.company+' · ' : ''}${c.phone || LANG.no_phone} · ${c.type} · Bal: <span style="color:${balColor};font-weight:600">${c.balance < 0 ? "-" : ""}<?= $currency ?> ${Math.abs(c.balance).toFixed(3)}</span></div>
    </div>
    <span class="cust-change" onclick="changeCustomer()">${LANG.change}</span>
  </div>`;

  updatePriceMode(c.type);
  showToast(LANG.customer_selected, c.name + " " + LANG.selected + ".", "success");
}

function pickCustomer(id) {
  if (customerCache[id]) {
    pickCustomerFromData(customerCache[id]);
    return;
  }
  const url = BASE_URL + "/api/search_customers.php?q=" + encodeURIComponent(id) + "&limit=1";
  fetch(url)
    .then(r => r.json())
    .then(data => {
      const c = data.customers ? data.customers[0] : null;
      if (c) pickCustomerFromData(c);
    })
    .catch(() => showToast(LANG.error, LANG.network_error, "error"));
}

function changeCustomer() {
  document.getElementById("selected-customer-display").style.display = "none";
  document.getElementById("customer-search-box").style.display = "block";
  document.getElementById("cust-search-input").value = "";
  document.getElementById("cust-search-input").focus();
  renderCustomerList([]);
  document.getElementById("cust-dropdown").classList.add("open");
}

function updatePriceMode(type) {
  // Switch cart prices to wholesale or retail based on customer type
  cart.forEach(item => {
    item.price = type === "wholesale"
      ? (item.sell_mode === 'box' ? (item.box_wholesale || item.box_price) : item.wholesale)
      : (item.sell_mode === 'box' ? item.box_price : item.retail_price);
  });
  renderCart();
  // Also re-render product grid so card prices reflect the customer type
  filterProducts(document.getElementById("pos-search").value);
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

  // POST to customers.php then fetch the new customer ID via a quick lookup
  fetch(BASE_URL + "/api/quick_add_customer.php", {method:"POST", body: formData})
    .then(r => r.json())
    .then(data => {
      if (data.success && data.customer) {
        // Cache new customer and select without page reload
        customerCache[data.customer.id] = data.customer;
        showToast(LANG.success, LANG.customer_added + ": " + name, "success");
        closeModal("quick-customer-modal");
        // Auto-select the new customer
        document.querySelector(".cust-tab[data-tab=saved]").click();
        setTimeout(() => pickCustomerFromData(data.customer), 300);
      } else {
        showToast(LANG.error, data.error || LANG.network_error, "error");
      }
    })
    .catch(() => showToast(LANG.error, LANG.network_error, "error"));

  return false;
}

// ── PRODUCT GRID ──
function renderProductGrid(prods) {
  const grid = document.getElementById("product-grid");
  if (!prods.length) { grid.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text3)">' + LANG.search_products + '</div>'; return; }
  const currentCust = customerCache[selectedCustomerId] || null;
  const isWholesale = currentCust && currentCust.type === "wholesale";
  grid.innerHTML = prods.map(p => {
    const displayPrice = isWholesale ? p.wholesale : p.price;
    const priceColor   = isWholesale ? "var(--accent2)" : "var(--green)";
    const priceTag     = isWholesale ? " <span style=\"font-size:9px;opacity:.7\">W</span>" : "";
    const stockColor   = p.stock<=5 ? "var(--red)" : (p.stock<=10 ? "var(--amber)" : "var(--text3)");
    const arName       = p.name_ar ? '<span style="font-size:11px;color:var(--text3);display:block">'+p.name_ar+'</span>' : '';
    const warn         = p.stock<=5 ? " ⚠️" : "";
    // Build price line based on unit type
    let priceHTML;
    if (p.unit === 'box') {
      const bxPrice = isWholesale ? (p.box_wholesale || p.box_price) : p.box_price;
      priceHTML = '<div class="product-price" style="color:'+priceColor+';font-size:11px">'
        + '<?= $currency ?> '+displayPrice.toFixed(3)+'/pc</div>'
        + '<div style="font-size:10px;color:var(--amber,#f59e0b);font-weight:600">'
        + '📦 <?= $currency ?> '+bxPrice.toFixed(3)+'/box ('+p.pack_size+' pcs)</div>';
    } else if (p.unit === 'pr') {
      priceHTML = '<div class="product-price" style="color:'+priceColor+'"><?= $currency ?> '+displayPrice.toFixed(3)+'</div>'
        + '<div style="font-size:10px;color:var(--text3)">per pair (2 pcs)</div>';
    } else if (p.unit === 'doz') {
      priceHTML = '<div class="product-price" style="color:'+priceColor+'"><?= $currency ?> '+displayPrice.toFixed(3)+'</div>'
        + '<div style="font-size:10px;color:var(--text3)">per dozen (12 pcs)</div>';
    } else {
      priceHTML = '<div class="product-price" style="color:'+priceColor+'"><?= $currency ?> '+displayPrice.toFixed(3)+priceTag+'</div>';
    }
    return '<div class="product-card" onclick="addToCart('+p.id+')">'
      + '<div class="product-img" style="background:'+p.bg+'">'+p.emoji+'</div>'
      + '<div class="product-name">'+p.name+arName+'</div>'
      + '<div class="product-sku">'+p.sku+'</div>'
      + priceHTML
      + (p.expiry_badge ? '<div style="font-size:10px;padding:2px 6px;border-radius:4px;margin-top:3px;display:inline-block;background:'+p.expiry_badge.bg+';color:'+p.expiry_badge.color+'">'+p.expiry_badge.label+'</div>' : '')
      + '<div class="product-stock" style="color:'+stockColor+'">'+p.stock+' '+LANG.in_stock+warn+'</div>'
      + '</div>';
  }).join("");
}

let searchTimeout = null;
function filterProducts(query) {
  const cat = parseInt(document.getElementById("pos-cat").value, 10);
  const q = query.toLowerCase();
  if (searchTimeout) clearTimeout(searchTimeout);
  if (!q && !cat) {
    renderProductGrid([]);
    return;
  }
  searchTimeout = setTimeout(function() { searchProducts(q, cat); }, 250);
}

function searchProducts(q, cat) {
  const url = BASE_URL + "/api/search_products.php?q=" + encodeURIComponent(q) +
              "&cat=" + encodeURIComponent(cat) + "&limit=50";
  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (data.products) {
        data.products.forEach(function(p) {
          p.expiry_badge = getExpiryBadge(p);
          productCache[p.id] = p;
        });
        renderProductGrid(data.products);
      } else {
        renderProductGrid([]);
      }
    })
    .catch(() => showToast(LANG.error, LANG.network_error, "error"));
}

function fetchProductById(id, cb) {
  const url = BASE_URL + "/api/search_products.php?q=" + encodeURIComponent(id) +
              "&limit=1";
  fetch(url)
    .then(r => r.json())
    .then(data => {
      const p = data.products ? data.products[0] : null;
      if (p) {
        p.expiry_badge = getExpiryBadge(p);
        productCache[p.id] = p;
      }
      cb(p);
    })
    .catch(() => cb(null));
}

function addToCart(id, sellMode) {
  if (productCache[id]) {
    addProductToCart(productCache[id], sellMode);
    return;
  }
  fetchProductById(id, function(p) {
    if (p) addProductToCart(p, sellMode);
  });
}

function addProductToCart(p, sellMode) {
  if (!p) return;
  if (p.stock <= 0) { showToast(LANG.out_of_stock, p.name, "error"); return; }

  // Box products: if no sellMode given, show picker modal
  if (p.unit === 'box' && !sellMode) {
    openSellModePicker(p);
    return;
  }

  const custType = customerCache[selectedCustomerId] || null;
  const isWhole  = custType && custType.type === "wholesale";
  const mode     = sellMode || 'unit';

  let usePrice, unitLabel, packSize;
  if (p.unit === 'box' && mode === 'box') {
    usePrice  = isWhole ? (p.box_wholesale || p.box_price) : p.box_price;
    unitLabel = p.unit_label;  // "box(12pcs)"
    packSize  = p.pack_size;
  } else {
    // unit/piece mode — also used for pair, dozen, and all others
    usePrice  = isWhole ? p.wholesale : p.price;
    unitLabel = (p.unit === 'pr') ? 'pair' : (p.unit === 'doz') ? 'dozen' : (p.unit || 'pc');
    packSize  = (p.unit === 'pr') ? 2 : (p.unit === 'doz') ? 12 : 1;
  }

  // Cart key = id + sellMode so same product can appear as both piece and box
  const cartKey = p.id + '_' + mode;
  const existing = cart.find(i => i._key === cartKey);
  if (existing) {
    existing.qty++;
  } else {
    cart.push({...p, retail_price: p.price, _key: cartKey, price: usePrice, qty: 1, disc: 0,
               sell_mode: mode, unit_label: unitLabel, pack_size: packSize,
               cat_id: p.cat_id, expiry_badge: p.expiry_badge});
  }
  renderCart();
  showToast(LANG.added, p.name + (p.unit === 'box' ? ' (' + mode + ')' : ''), "success");
}

// ── Sell-mode picker for box products ─────────────────────────────────────
function openSellModePicker(p) {
  const custType = customerCache[selectedCustomerId] || null;
  const isWhole  = custType && custType.type === "wholesale";
  const piecePrice = isWhole ? p.wholesale : p.price;
  const boxPrice   = isWhole ? (p.box_wholesale || p.box_price) : p.box_price;
  const cur = typeof CURRENCY !== 'undefined' ? CURRENCY : 'KWD';
  const DEC = typeof DECIMALS !== 'undefined' ? DECIMALS : 3;

  // Remove existing picker if any
  const old = document.getElementById('sell-mode-modal');
  if (old) old.remove();

  const div = document.createElement('div');
  div.id = 'sell-mode-modal';
  div.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center';
  div.innerHTML = `
    <div style="background:var(--bg);border-radius:14px;padding:24px;width:340px;max-width:94vw;box-shadow:0 8px 40px rgba(0,0,0,.3)">
      <div style="font-size:15px;font-weight:700;margin-bottom:6px;color:var(--text)">${p.emoji} ${p.name}</div>
      <div style="font-size:11px;color:var(--text3);margin-bottom:18px">How would you like to sell this?</div>

      <button onclick="addToCart(${p.id},'unit');document.getElementById('sell-mode-modal').remove()"
        style="width:100%;padding:14px 16px;border:1px solid var(--border2);border-radius:10px;background:var(--bg2);cursor:pointer;text-align:left;margin-bottom:10px">
        <div style="font-weight:600;color:var(--text);font-size:13px">🏷️ Sell by piece</div>
        <div style="font-size:11px;color:var(--text3);margin-top:3px">${cur} ${piecePrice.toFixed(DEC)} each — deducts 1 piece per qty</div>
      </button>

      <button onclick="addToCart(${p.id},'box');document.getElementById('sell-mode-modal').remove()"
        style="width:100%;padding:14px 16px;border:1px solid var(--amber,#f59e0b);border-radius:10px;background:var(--bg2);cursor:pointer;text-align:left;margin-bottom:16px">
        <div style="font-weight:600;color:var(--text);font-size:13px">📦 Sell full box</div>
        <div style="font-size:11px;color:var(--text3);margin-top:3px">${cur} ${boxPrice.toFixed(DEC)} per box — deducts ${p.pack_size} pieces per qty</div>
      </button>

      <button onclick="document.getElementById('sell-mode-modal').remove()"
        style="width:100%;padding:8px;border:none;background:none;cursor:pointer;color:var(--text3);font-size:12px">Cancel</button>
    </div>`;
  document.body.appendChild(div);
  div.addEventListener('click', function(e){ if(e.target===this) this.remove(); });
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
  const discValue = parseFloat(document.getElementById("discount-value").value)||0;
  const globalDisc = discountType === "pct" ? afterPromo * (discValue / 100) : Math.min(discValue, afterPromo);
  const total = afterPromo - globalDisc;
  const totalDisc = itemDiscTotal + globalDisc;

  // Tax calculation
  const TAX_RATE      = <?= $tax_rate ?>;
  const TAX_INCLUSIVE = <?= $tax_inclusive ? 'true' : 'false' ?>;
  const HAS_TAX       = <?= $has_tax ? 'true' : 'false' ?>;
  const DEC           = <?= $decimals ?>;
  let taxAmt = 0, grandTotal = total;
  if (HAS_TAX && TAX_RATE > 0) {
    if (TAX_INCLUSIVE) {
      taxAmt = total - (total / (1 + TAX_RATE/100));
      grandTotal = total;
    } else {
      taxAmt = total * TAX_RATE / 100;
      grandTotal = total + taxAmt;
    }
  }
  document.getElementById("cart-subtotal").textContent = sub.toFixed(DEC);
  document.getElementById("cart-discount").textContent = "- " + totalDisc.toFixed(DEC);
  const taxEl = document.getElementById("cart-tax");
  if (taxEl) taxEl.textContent = (TAX_INCLUSIVE ? "(incl.) " : "+ ") + taxAmt.toFixed(DEC);
  document.getElementById("cart-total").textContent = grandTotal.toFixed(DEC);
  document.getElementById("cart-count").textContent    = cart.reduce((a,i)=>a+i.qty,0) + " " + LANG.items;

  // Show promo
  const promoEl = document.getElementById("promo-applied");
  if (appliedPromo && promoDiscount > 0) {
    promoEl.style.display = "block";
    document.getElementById("promo-label").textContent = "🎯 " + appliedPromo.title;
    document.getElementById("promo-amount").textContent = "- " + promoDiscount.toFixed(3);
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
  var container = document.getElementById('cart-items');
  var empty     = document.getElementById('cart-empty');
  if (!cart.length) {
    container.innerHTML = '';
    if (empty) { container.appendChild(empty); empty.style.display = 'block'; }
    recalc();
    return;
  }
  if (empty) empty.style.display = 'none';
  var html = '';
  cart.forEach(function(item) {
    var lineTotal = item.price * item.qty;
    var lineDisc  = lineTotal * (item.disc / 100);
    var lineNet   = lineTotal - lineDisc;
    var netColor  = item.disc > 0 ? '#16a34a' : 'var(--text)';
    var arSpan    = item.name_ar ? '<div style="font-size:10px;color:var(--text3);direction:rtl">' + item.name_ar + '</div>' : '';
    var discSpan  = item.disc > 0 ? '<span style="font-size:10px;color:#ef4444">-' + lineDisc.toFixed(3) + '</span>' : '';
    var expirySpan = item.expiry_badge ? '<div style="font-size:10px;padding:1px 5px;border-radius:3px;margin-top:2px;background:'+item.expiry_badge.bg+';color:'+item.expiry_badge.color+'">'+item.expiry_badge.label+'</div>' : '';
    html += '<div class="cart-item" style="position:relative">';
    html +=   '<div style="font-size:20px;flex-shrink:0">' + item.emoji + '</div>';
    html +=   '<div style="flex:1;min-width:0">';
    const packLabel = item.unit_label && item.unit_label !== 'pc'
      ? '<div style="font-size:10px;color:var(--amber,#f59e0b);font-weight:500;margin-top:1px">📦 ' + item.unit_label + (item.pack_size>1 && item.sell_mode==='box' ? ' (−'+item.pack_size+' pcs each)' : '') + '</div>'
      : '';
    html +=     '<div style="font-size:12px;font-weight:600">' + item.name + arSpan + '</div>' + packLabel + expirySpan;
    html +=     '<div style="display:flex;align-items:center;gap:4px;margin-top:3px">';
    html +=       '<button class="qty-btn" onclick="changeQty(' + item.id + ',-1)" style="width:22px;height:22px">-</button>';
    html +=       '<span style="font-size:12px;font-weight:700;min-width:20px;text-align:center">' + item.qty + '</span>';
    html +=       '<button class="qty-btn" onclick="changeQty(' + item.id + ',1)" style="width:22px;height:22px">+</button>';
    html +=       '<span style="font-size:10px;color:var(--text3)"> x ' + item.price.toFixed(3) + '</span>';
    html +=     '</div>';
    html +=     '<div style="display:flex;align-items:center;gap:4px;margin-top:3px">';
    html +=       '<span style="font-size:10px;color:var(--text3)">Disc%:</span>';
    html +=       '<input type="number" min="0" max="100" value="' + item.disc + '"';
    html +=         ' onchange="setItemDisc(' + item.id + ',this.value)"';
    html +=         ' style="width:38px;padding:2px 4px;font-size:11px;border:1px solid var(--border2);border-radius:4px;text-align:center">';
    html +=       '<span style="font-size:10px;color:var(--text3)">%</span>' + discSpan;
    html +=     '</div>';
    html +=   '</div>';
    html +=   '<div style="display:flex;flex-direction:column;align-items:flex-end;justify-content:space-between;gap:6px;flex-shrink:0">';
    html +=     '<button onclick="removeFromCart(' + item.id + ')"';
    html +=       ' style="width:18px;height:18px;border-radius:50%;border:1px solid var(--border2);background:var(--bg3);color:var(--text3);cursor:pointer;font-size:12px;line-height:1;display:flex;align-items:center;justify-content:center"';
    html +=       ' onmouseover="this.style.background=\'#ef4444\';this.style.color=\'#fff\'"';
    html +=       ' onmouseout="this.style.background=\'var(--bg3)\';this.style.color=\'var(--text3)\'">x</button>';
    html +=     '<span style="font-size:13px;font-weight:700;color:' + netColor + '">' + lineNet.toFixed(3) + '</span>';
    html +=   '</div>';
    html += '</div>';
  });
  container.innerHTML = html;
  recalc();
}

function removeFromCart(id) {
  cart = cart.filter(function(i) { return i.id !== id; });
  renderCart();
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

function setDiscType(type) {
  discountType = type;
  const pctBtn = document.getElementById("disc-type-pct");
  const fixedBtn = document.getElementById("disc-type-fixed");
  const input = document.getElementById("discount-value");
  
  if (type === "pct") {
    pctBtn.style.borderColor = "var(--accent)";
    pctBtn.style.background = "rgba(67,97,238,.1)";
    pctBtn.style.color = "var(--accent)";
    fixedBtn.style.borderColor = "var(--border2)";
    fixedBtn.style.background = "var(--bg2)";
    fixedBtn.style.color = "var(--text3)";
    input.max = "100";
  } else {
    fixedBtn.style.borderColor = "var(--accent)";
    fixedBtn.style.background = "rgba(67,97,238,.1)";
    fixedBtn.style.color = "var(--accent)";
    pctBtn.style.borderColor = "var(--border2)";
    pctBtn.style.background = "var(--bg2)";
    pctBtn.style.color = "var(--text3)";
    input.removeAttribute("max");
  }
  input.value = "0";
  recalc();
}

function holdSale() {
  if (!cart.length) { showToast(LANG.warning, LANG.empty_cart, "warning"); return; }
  const holds = JSON.parse(localStorage.getItem('retailpro_holds') || '[]');
  const hold = {
    key: 'hold_' + Date.now(),
    time: new Date().toLocaleString(),
    cart: JSON.parse(JSON.stringify(cart)),
    customer_id: selectedCustomerId,
    appliedPromo: appliedPromo,
    promoDiscount: promoDiscount,
    discountType: discountType,
    discountValue: document.getElementById("discount-value").value
  };
  holds.push(hold);
  localStorage.setItem('retailpro_holds', JSON.stringify(holds));
  clearCart();
  document.querySelector(".cust-tab[data-tab=walkin]").click();
  showToast(LANG.success, LANG.hold_msg, "success");
}

function showHoldModal() {
  const holds = JSON.parse(localStorage.getItem('retailpro_holds') || '[]');
  const list = document.getElementById("hold-list");
  if (!holds.length) {
    list.innerHTML = '<div style="text-align:center;color:var(--text3);padding:20px">' + LANG.no_held_sales + '</div>';
  } else {
    list.innerHTML = holds.map(function(h) {
      const total = h.cart.reduce(function(a,i){ return a + i.price*i.qty; }, 0).toFixed(3);
      const count = h.cart.reduce(function(a,i){ return a+i.qty; }, 0);
      return '<div style="display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid var(--border)">'
        + '<div><div style="font-weight:500">' + h.time + '</div>'
        + '<div style="font-size:11px;color:var(--text3)">' + count + ' ' + LANG.items + ' · <?= $currency ?> ' + total + '</div></div>'
        + '<div style="display:flex;gap:6px">'
        + '<button type="button" class="btn btn-primary btn-sm" onclick="resumeHold(\'' + h.key + '\')">' + LANG.resume + '</button>'
        + '<button type="button" class="btn btn-ghost btn-sm" style="color:var(--red)" onclick="deleteHold(\'' + h.key + '\')">🗑️</button>'
        + '</div></div>';
    }).join('');
  }
  openModal("hold-modal");
}

function resumeHold(key) {
  const holds = JSON.parse(localStorage.getItem('retailpro_holds') || '[]');
  const hold = holds.find(function(h){ return h.key === key; });
  if (!hold) return;
  cart = JSON.parse(JSON.stringify(hold.cart));
  selectedCustomerId = hold.customer_id || 1;
  document.getElementById("cart-customer").value = selectedCustomerId;
  appliedPromo = hold.appliedPromo || null;
  promoDiscount = hold.promoDiscount || 0;
  discountType = hold.discountType || "pct";
  document.getElementById("discount-value").value = hold.discountValue || 0;
  setDiscType(discountType);
  if (selectedCustomerId > 1) {
    const url = BASE_URL + "/api/search_customers.php?q=" + encodeURIComponent(selectedCustomerId) + "&limit=1";
    fetch(url)
      .then(r => r.json())
      .then(data => {
        const c = data.customers ? data.customers[0] : null;
        if (c) {
          customerCache[c.id] = c;
          document.querySelector(".cust-tab[data-tab=saved]").click();
          setTimeout(function(){ pickCustomerFromData(c); }, 100);
        }
      });
  } else {
    document.querySelector(".cust-tab[data-tab=walkin]").click();
  }
  deleteHold(key);
  closeModal("hold-modal");
  renderCart();
  showToast(LANG.success, LANG.hold_resumed, "success");
}

function deleteHold(key) {
  let holds = JSON.parse(localStorage.getItem('retailpro_holds') || '[]');
  holds = holds.filter(function(h){ return h.key !== key; });
  localStorage.setItem('retailpro_holds', JSON.stringify(holds));
  showHoldModal();
}

function getExpiryBadge(p) {
  if (!p.has_expiry || !p.expiry_date) return null;
  var today = new Date(); today.setHours(0,0,0,0);
  var exp   = new Date(p.expiry_date);
  var diffMs = exp - today;
  var days  = Math.floor(diffMs / 86400000);
  if (days < 0)  return { label: '⛔ EXPIRED',          bg: '#fee2e2', color: '#b91c1c' };
  if (days === 0) return { label: '⛔ Expires TODAY',    bg: '#fee2e2', color: '#b91c1c' };
  if (days <= p.alert_days) return { label: '⚠️ Exp in '+days+'d', bg: '#fef3c7', color: '#92400e' };
  return null;
}

// Expiry badges are computed when products are fetched via search API

function processSale() {
  if (!cart.length) { showToast(LANG.warning, LANG.empty_cart, "warning"); return; }
  const discValue = parseFloat(document.getElementById("discount-value").value)||0;
  const customerId = document.getElementById("cart-customer").value;
  if (selectedPayMode === "partial") {
    const partialAmt = parseFloat(document.getElementById("partial-amount").value)||0;
    if (partialAmt <= 0) { showToast(LANG.error, "Enter the amount paid now.", "warning"); return; }
    if (customerId == 1) { showToast(LANG.error, LANG.select_customer_credit, "warning"); return; }
  }
  if (selectedPayMode === "credit" && customerId == 1) { showToast(LANG.error, LANG.select_customer_credit, "warning"); return; }
  const paidAmount = selectedPayMode === "partial" ? parseFloat(document.getElementById("partial-amount").value)||0 : null;
  // ── Expiry check before submit ──────────────────────────────────────────
  const expiredItems = cart.filter(i => i.expiry_badge && i.expiry_badge.color === '#b91c1c');
  const warningItems = cart.filter(i => i.expiry_badge && i.expiry_badge.color === '#92400e');
  if (expiredItems.length > 0) {
    const names = expiredItems.map(i => i.name).join(', ');
    showToast('Sale Blocked', 'Cannot sell expired product: ' + names + '. Remove from cart.', 'error');
    return;
  }
  if (warningItems.length > 0) {
    const names = warningItems.map(i => i.name + ' (' + i.expiry_badge.label + ')').join(', ');
    showToast('Expiry Warning', names, 'warning');
    // Allow sale to continue — just a warning
  }

  const payload = { cart, payment_mode: selectedPayMode, discount_type: discountType, discount_value: discValue, customer_id: customerId, offer_id: appliedPromo ? appliedPromo.id : null, promo_discount: promoDiscount, paid_amount: paidAmount };

  fetch(BASE_URL + "/api/sale.php", {
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      showToast(LANG.sale_complete, LANG.invoice + " " + data.invoice_number + " — " + CURRENCY + " " + data.total, "success");
      clearCart();
      // Reset to walk-in
      document.querySelector(".cust-tab[data-tab=walkin]").click();
      // Open invoice for printing
      if (data.invoice_id) {
        window.open(BASE_URL + "/invoice.php?id=" + data.invoice_id + "&print=1", "_blank");
      }
    } else {
      showToast(LANG.error, data.error || LANG.sale_failed, "error");
    }
  }).catch(() => showToast(LANG.error, LANG.network_error, "error"));
}

function openQuickAddModal() {
  // Reset form cleanly each time
  document.getElementById("quick-cust-form").reset();
  document.getElementById("qc-credit").value = "0";
  openModal("quick-customer-modal");
}
// Show/hide credit limit hint based on type selection
document.addEventListener("change", function(e) {
  if (e.target && e.target.id === "qc-type") {
    const row = document.getElementById("qc-credit-row");
    if (row) {
      row.style.opacity = e.target.value === "wholesale" ? "1" : "0.5";
    }
  }
});

renderProductGrid(PRODUCTS);
// Initialize discount type on page load
setTimeout(() => setDiscType("pct"), 100);

// ── MOBILE POS PANEL SWITCHER ──
function showPosPanel(panel) {
  const products = document.querySelector(".pos-products");
  const cart     = document.querySelector(".pos-cart");
  const btnP     = document.getElementById("mtab-products");
  const btnC     = document.getElementById("mtab-cart");
  if (panel === "products") {
    products.classList.remove("hidden-mobile");
    cart.classList.add("hidden-mobile");
    btnP.classList.add("active");
    btnC.classList.remove("active");
  } else {
    cart.classList.remove("hidden-mobile");
    products.classList.add("hidden-mobile");
    btnC.classList.add("active");
    btnP.classList.remove("active");
  }
}
// Update mobile cart badge count on every recalc
const _origRecalc = recalc;
recalc = function() {
  _origRecalc();
  const badge = document.getElementById("mtab-cart-count");
  if (badge) badge.textContent = cart.reduce((a,i)=>a+i.qty,0);
};
</script>
<?php
$extra_js = ob_get_clean();
require __DIR__ . '/includes/footer.php';
?>
