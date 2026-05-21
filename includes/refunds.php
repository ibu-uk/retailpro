<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'refunds';
$page_title   = __('returns_refunds');
$currency     = get_setting('currency', 'KWD');
$db = db();
$user = current_user();
$is_super = ($user['role'] === 'super_admin' && !$user['branch_id']);
$bfilter  = $is_super ? "" : "AND p.branch_id = " . (int)$user['branch_id'];

// Stats
$today_amt = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments
    WHERE type='customer' AND notes LIKE 'Refund:%' AND DATE(created_at)=CURDATE()")->fetchColumn();
$month_amt = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments
    WHERE type='customer' AND notes LIKE 'Refund:%'
    AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
$total_count = $db->query("SELECT COUNT(*) FROM payments
    WHERE type='customer' AND notes LIKE 'Refund:%'")->fetchColumn();

// Recent refunds — joined to invoices for branch filter
$recent = $db->query("
    SELECT p.*, c.name as customer_name, i.branch_id,
           b.name as branch_name, i.invoice_number as inv_num
    FROM payments p
    LEFT JOIN customers c ON c.id = p.reference_id
    LEFT JOIN invoices i ON i.id = p.invoice_id
    LEFT JOIN branches b ON b.id = i.branch_id
    WHERE p.type='customer' AND p.notes LIKE 'Refund:%'
    $bfilter
    ORDER BY p.created_at DESC
    LIMIT 30
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<!-- STATS -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));margin-bottom:16px">
  <div class="stat-card red">
    <div class="stat-icon">↩️</div>
    <div class="stat-label"><?= __('today') ?></div>
    <div class="stat-value text-red"><?= fmt_money($today_amt) ?></div>
  </div>
  <div class="stat-card amber">
    <div class="stat-icon">📅</div>
    <div class="stat-label"><?= __('this_month') ?></div>
    <div class="stat-value text-amber"><?= fmt_money($month_amt) ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon">📋</div>
    <div class="stat-label">Total Refunds</div>
    <div class="stat-value text-blue"><?= number_format($total_count) ?></div>
  </div>
</div>

<!-- SEARCH & PROCESS REFUND -->
<div class="card" style="margin-bottom:16px">
  <div class="card-title"><span>🔍 Find Invoice to Refund</span></div>

  <!-- Search bar -->
  <div style="display:flex;gap:8px;margin-bottom:12px">
    <input class="form-input" id="inv-search"
           placeholder="Enter invoice number e.g. INV-2026-0001"
           style="flex:1;font-family:var(--mono);letter-spacing:.5px">
    <button class="btn btn-primary" onclick="searchInvoice()" id="search-btn">🔍 Search</button>
  </div>

  <!-- Invoice result (hidden until search) -->
  <div id="inv-result" style="display:none">

    <!-- Invoice summary banner -->
    <div id="inv-banner" style="background:var(--bg3);border-radius:var(--r);padding:14px;margin-bottom:12px;border:1px solid var(--border2)"></div>

    <!-- Items table with return qty inputs -->
    <div class="tbl-wrap">
      <table id="inv-items-table">
        <thead>
          <tr>
            <th>Product</th>
            <th class="hide-mobile">Batch / Supplier</th>
            <th class="hide-mobile">Expiry</th>
            <th>Sold Qty</th>
            <th>Unit Price</th>
            <th>Line Total</th>
            <th>Return Qty</th>
            <th>Refund</th>
          </tr>
        </thead>
        <tbody id="inv-items-body"></tbody>
        <tfoot>
          <tr style="background:var(--bg3)">
            <td colspan="6" style="padding:10px 12px;text-align:right;font-weight:600">Total Refund:</td>
            <td colspan="2" style="padding:10px 12px;font-size:16px;font-weight:700;color:var(--red)">
              <span id="refund-total"><?= $currency ?> 0.000</span>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Refund options -->
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-top:14px;padding:14px;background:var(--bg3);border-radius:var(--r)">
      <div class="form-group" style="margin:0;min-width:180px">
        <label class="form-label">Reason</label>
        <select class="form-select" id="refund-reason">
          <option value="Customer return">Customer return</option>
          <option value="Defective product">Defective product</option>
          <option value="Wrong item">Wrong item</option>
          <option value="Size exchange">Size exchange</option>
          <option value="Expired product">Expired product</option>
          <option value="Damaged in transit">Damaged in transit</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:160px">
        <label class="form-label">Refund Method</label>
        <select class="form-select" id="refund-mode">
          <option value="cash">💵 Cash</option>
          <option value="knet">💳 KNET</option>
          <option value="wamd">📱 WAMD</option>
          <option value="credit">🏦 Store Credit (add to balance)</option>
        </select>
      </div>
      <div id="paid-warning" style="display:none;padding:8px 12px;background:rgba(245,166,35,.1);border:1px solid rgba(245,166,35,.3);border-radius:var(--r);font-size:12px;color:var(--amber);flex:1">
        ⚠️ This invoice was paid <span id="paid-amount-label"></span>. Max cash refund is limited to paid amount.
      </div>
      <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
        <button class="btn btn-ghost" onclick="resetRefund()">↺ Clear</button>
        <button class="btn btn-primary" onclick="processRefund()" id="refund-btn" disabled
                style="min-width:160px">
          ↩️ Process Refund
        </button>
      </div>
    </div>
  </div>
</div>

<!-- RECENT REFUNDS TABLE -->
<div class="card">
  <div class="card-title">
    <span>📋 Recent Refunds</span>
    <a href="<?= BASE ?>/api/export_refunds.php" style="font-size:11px;color:var(--accent)">📊 Export</a>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th><?= __('date') ?></th>
          <th><?= __('customer_name') ?></th>
          <th>Invoice</th>
          <th class="hide-mobile">Branch</th>
          <th><?= __('amount') ?></th>
          <th class="hide-mobile">Method</th>
          <th><?= __('reason') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r):
            $parts   = explode(' - ', $r['notes'], 2);
            $inv_ref = str_replace('Refund: ', '', $parts[0] ?? '');
            $reason  = $parts[1] ?? '';
        ?>
        <tr>
          <td style="font-size:12px;color:var(--text3);white-space:nowrap">
            <?= date('d M Y H:i', strtotime($r['created_at'])) ?>
          </td>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($r['customer_name'] ?? 'Walk-in') ?></div>
          </td>
          <td class="font-mono" style="font-size:11px;color:var(--accent2)">
            <?= htmlspecialchars($inv_ref) ?>
          </td>
          <td class="hide-mobile" style="font-size:12px">
            <?= htmlspecialchars($r['branch_name'] ?? '—') ?>
          </td>
          <td style="font-weight:700;color:var(--red)">
            <?= fmt_money($r['amount']) ?>
          </td>
          <td class="hide-mobile">
            <span class="badge badge-gray"><?= strtoupper(htmlspecialchars($r['payment_mode'])) ?></span>
          </td>
          <td style="font-size:12px;color:var(--text3)">
            <?= htmlspecialchars($reason) ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent)): ?>
        <tr>
          <td colspan="7" style="text-align:center;padding:30px;color:var(--text3)">
            No refunds recorded yet
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$extra_js = '<script>
let currentInvoice = null;
let invoiceItems   = [];

// ── SEARCH ──
function searchInvoice() {
  const q   = document.getElementById("inv-search").value.trim();
  const btn = document.getElementById("search-btn");
  if (!q) { showToast("Error", "Enter an invoice number", "warning"); return; }

  btn.disabled = true;
  btn.textContent = "Searching...";

  fetch("' . BASE . '/api/invoice_lookup.php?q=" + encodeURIComponent(q))
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.textContent = "🔍 Search";
      if (data.error) { showToast("Not Found", data.error, "error"); return; }
      currentInvoice = data.invoice;
      invoiceItems   = data.items;
      renderResult();
    })
    .catch(function() {
      btn.disabled = false;
      btn.textContent = "🔍 Search";
      showToast("Error", "Network error — try again", "error");
    });
}

// ── RENDER INVOICE ──
function renderResult() {
  const inv = currentInvoice;
  const paidAmt = parseFloat(inv.paid_amount).toFixed(3);
  const totAmt  = parseFloat(inv.total).toFixed(3);

  const statusColors = { paid:"var(--green)", credit:"var(--amber)", partial:"var(--blue)", refunded:"var(--text3)" };
  const color = statusColors[inv.status] || "var(--text2)";

  document.getElementById("inv-banner").innerHTML =
    "<div style=\"display:flex;flex-wrap:wrap;gap:12px;align-items:center\">" +
    "<div style=\"font-size:16px;font-weight:700;font-family:var(--mono)\">" + inv.invoice_number + "</div>" +
    "<span style=\"background:rgba(0,0,0,.15);border:1px solid " + color + ";color:" + color + ";padding:2px 10px;border-radius:99px;font-size:12px\">" + inv.status.toUpperCase() + "</span>" +
    "<div style=\"font-size:13px;color:var(--text2)\">👤 " + inv.customer_name + "</div>" +
    "<div style=\"font-size:13px;color:var(--text2)\">🏪 " + inv.branch_name + "</div>" +
    "<div style=\"font-size:13px;color:var(--text2)\">📅 " + inv.created_at + "</div>" +
    "<div style=\"margin-left:auto;text-align:right\">" +
    "<div style=\"font-size:13px;color:var(--text3)\">Invoice Total: <strong style=\"color:var(--text)\"><?= $currency ?> " + totAmt + "</strong></div>" +
    "<div style=\"font-size:12px;color:var(--text3)\">Paid: <strong style=\"color:var(--green)\"><?= $currency ?> " + paidAmt + "</strong>" +
    (inv.status === "credit" ? " <span style=\"color:var(--amber)\">(credit — nothing paid)</span>" : "") +
    "</div></div></div>";

  // Paid amount warning
  if (inv.status === "credit") {
    document.getElementById("paid-warning").style.display = "block";
    document.getElementById("paid-amount-label").textContent = "KWD 0 (full credit)";
  } else if (inv.status === "partial") {
    document.getElementById("paid-warning").style.display = "block";
    document.getElementById("paid-amount-label").textContent = paidAmt;
  } else {
    document.getElementById("paid-warning").style.display = "none";
  }

  // Items
  let rows = "";
  invoiceItems.forEach(function(item) {
    const unitPrice = item.qty > 0 ? (item.total / item.qty) : 0;
    const expBadge = item.expiry_date
      ? "<br><span style=\"font-size:10px;color:var(--amber)\">Exp: " + item.expiry_date + "</span>"
      : "";
    const batchInfo = item.batch_number
      ? "<div style=\"font-size:10px;color:var(--accent2);font-family:monospace\">" + item.batch_number + "</div>"
        + (item.supplier_name ? "<div style=\"font-size:10px;color:var(--text3)\">" + item.supplier_name + "</div>" : "")
      : "<span style=\"color:var(--text3);font-size:11px\">—</span>";

    rows += "<tr>" +
      "<td><div style=\"display:flex;align-items:center;gap:6px\"><span style=\"font-size:18px\">" + item.emoji + "</span><div><div style=\"font-weight:500\">" + item.product_name + "</div>" +
      (item.product_name_ar ? "<div style=\"font-size:10px;color:var(--text3);direction:rtl\">" + item.product_name_ar + "</div>" : "") + "</div></div></td>" +
      "<td class=\"hide-mobile\">" + batchInfo + "</td>" +
      "<td class=\"hide-mobile\">" + (item.expiry_date ? "<span style=\"font-size:11px;color:var(--amber)\">" + item.expiry_date + "</span>" : "<span style=\"color:var(--text3)\">—</span>") + "</td>" +
      "<td style=\"font-weight:600\">" + item.qty + "</td>" +
      "<td><?= $currency ?> " + unitPrice.toFixed(3) + "</td>" +
      "<td style=\"color:var(--green);font-weight:600\"><?= $currency ?> " + parseFloat(item.total).toFixed(3) + "</td>" +
      "<td><input type=\"number\" min=\"0\" max=\"" + item.qty + "\" value=\"0\"" +
        " data-item-id=\"" + item.id + "\"" +
        " data-max-qty=\"" + item.qty + "\"" +
        " data-unit-price=\"" + unitPrice.toFixed(3) + "\"" +
        " data-paid-ratio=\"" + (inv.total > 0 ? inv.paid_amount / inv.total : 1) + "\"" +
        " class=\"form-input refund-qty\" style=\"width:65px;text-align:center;padding:6px\"" +
        " onchange=\"calcRefundTotal()\"></td>" +
      "<td id=\"line-refund-" + item.id + "\" style=\"font-weight:600;color:var(--red)\"><?= $currency ?> 0.000</td>" +
      "</tr>";
  });
  document.getElementById("inv-items-body").innerHTML = rows;
  document.getElementById("inv-result").style.display = "block";
  calcRefundTotal();
}

// ── CALCULATE TOTAL ──
function calcRefundTotal() {
  let total = 0;
  const inv = currentInvoice;
  document.querySelectorAll(".refund-qty").forEach(function(inp) {
    let qty = parseInt(inp.value) || 0;
    const maxQty = parseInt(inp.dataset.maxQty);
    if (qty < 0) qty = 0;
    if (qty > maxQty) qty = maxQty;
    inp.value = qty;
    const lineRefund = qty * parseFloat(inp.dataset.unitPrice);
    total += lineRefund;
    const cell = document.getElementById("line-refund-" + inp.dataset.itemId);
    if (cell) cell.textContent = "<?= $currency ?> " + lineRefund.toFixed(3);
  });

  // Cap to paid amount for non-credit refunds
  const refundMode = document.getElementById("refund-mode").value;
  const maxRefund = refundMode === "credit" ? 999999 : parseFloat(inv.paid_amount);
  const actualRefund = Math.min(total, maxRefund);

  document.getElementById("refund-total").textContent = "<?= $currency ?> " + actualRefund.toFixed(3);
  document.getElementById("refund-btn").disabled = actualRefund <= 0;
}

// Update total when mode changes
document.addEventListener("DOMContentLoaded", function() {
  document.getElementById("refund-mode").addEventListener("change", calcRefundTotal);
});

// ── PROCESS REFUND ──
function processRefund() {
  const items = [];
  document.querySelectorAll(".refund-qty").forEach(function(inp) {
    const qty = parseInt(inp.value) || 0;
    if (qty > 0) items.push({ item_id: parseInt(inp.dataset.itemId), qty_return: qty });
  });

  if (!items.length) { showToast("Error", "Select at least one item to return", "warning"); return; }

  const reason = document.getElementById("refund-reason").value;
  const mode   = document.getElementById("refund-mode").value;
  const total  = document.getElementById("refund-total").textContent;

  appConfirm({
    type: "refund",
    title: "Process Refund",
    message: "Refund " + total + " via " + mode.toUpperCase() + " — Reason: " + reason,
    detail: "Items will be returned to stock. This action cannot be undone.",
    icon: "↩️",
    confirmText: "Yes, Process Refund",
    cancelText: "Cancel",
    onConfirm: function() {
      document.getElementById("refund-btn").disabled = true;
      document.getElementById("refund-btn").textContent = "Processing...";
      _doRefund();
    }
  });
  return; // wait for confirm dialog
}

function _doRefund() {

  fetch("' . BASE . '/api/refund.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      invoice_id:  currentInvoice.id,
      items:       items,
      reason:      reason,
      refund_mode: mode
    })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.success) {
      showToast("✅ Refund Done", "Refunded " + data.refund_amount + " — Invoice: " + data.invoice_number, "success");
      setTimeout(function() { location.reload(); }, 1500);
    } else {
      document.getElementById("refund-btn").disabled = false;
      document.getElementById("refund-btn").textContent = "↩️ Process Refund";
      showToast("Error", data.error || "Refund failed", "error");
    }
  })
  .catch(function() {
    document.getElementById("refund-btn").disabled = false;
    document.getElementById("refund-btn").textContent = "↩️ Process Refund";
    showToast("Error", "Network error — try again", "error");
  });
}

// ── RESET ──
function resetRefund() {
  currentInvoice = null;
  invoiceItems   = [];
  document.getElementById("inv-search").value = "";
  document.getElementById("inv-result").style.display = "none";
  document.getElementById("inv-search").focus();
}

// Enter key to search
document.getElementById("inv-search").addEventListener("keydown", function(e) {
  if (e.key === "Enter") searchInvoice();
});
</script>';
require __DIR__ . '/includes/footer.php';
