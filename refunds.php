<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'refunds';
$page_title   = __('returns_refunds');
$currency = get_setting('currency', 'KWD');
$db = db();

// Recent refunds from payments log
$recent = $db->query("
    SELECT p.*, c.name as customer_name
    FROM payments p
    LEFT JOIN customers c ON c.id = p.reference_id
    WHERE p.type='customer' AND p.notes LIKE 'Refund:%'
    ORDER BY p.created_at DESC LIMIT 20
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
  <div class="stat-card red"><div class="stat-label"><?= __('today') ?></div><div class="stat-value text-red"><?php
    $today = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='customer' AND notes LIKE 'Refund:%' AND DATE(created_at)=CURDATE()")->fetchColumn();
    echo fmt_money($today);
  ?></div></div>
  <div class="stat-card amber"><div class="stat-label"><?= __('this_month') ?></div><div class="stat-value text-amber"><?php
    $month = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='customer' AND notes LIKE 'Refund:%' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();
    echo fmt_money($month);
  ?></div></div>
  <div class="stat-card blue"><div class="stat-label"><?= __('total') ?></div><div class="stat-value text-blue"><?php
    $total = $db->query("SELECT COUNT(*) FROM payments WHERE type='customer' AND notes LIKE 'Refund:%'")->fetchColumn();
    echo $total;
  ?></div></div>
</div>

<!-- SEARCH INVOICE -->
<div class="card" style="margin-bottom:16px">
  <div class="card-title"><span>🔍 <?= __('find_invoice_refund') ?></span></div>
  <div style="display:flex;gap:8px;margin-bottom:12px">
    <input class="form-input" id="inv-search" placeholder="<?= __('enter_invoice_number') ?>" style="flex:1;font-family:var(--mono)">
    <button class="btn btn-primary" onclick="searchInvoice()"><?= __('search') ?></button>
  </div>
  <div id="inv-result" style="display:none">
    <div id="inv-details" style="background:var(--bg2);border-radius:8px;padding:14px;margin-bottom:12px"></div>
    <div class="tbl-wrap">
      <table id="inv-items-table">
        <thead><tr><th><?= __('product') ?></th><th><?= __('qty') ?></th><th><?= __('retail_price') ?></th><th><?= __('total') ?></th><th><?= __('qty_return') ?></th></tr></thead>
        <tbody id="inv-items-body"></tbody>
      </table>
    </div>
    <div style="margin-top:14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <div class="form-group" style="margin:0"><label class="form-label" style="margin:0;font-size:11px"><?= __('reason') ?></label>
        <select class="form-select" id="refund-reason" style="width:200px">
          <option>Customer return</option><option>Defective product</option><option>Wrong item</option><option>Size exchange</option><option>Other</option>
        </select>
      </div>
      <div class="form-group" style="margin:0"><label class="form-label" style="margin:0;font-size:11px"><?= __('refund_to') ?></label>
        <select class="form-select" id="refund-mode" style="width:150px">
          <option value="cash"><?= __('cash') ?></option><option value="knet"><?= __('knet') ?></option><option value="credit"><?= __('credit') ?></option>
        </select>
      </div>
      <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
        <span style="font-size:14px;font-weight:600">Refund: <span id="refund-total" class="text-red"><?= $currency ?> 0.000</span></span>
        <button class="btn btn-primary" onclick="processRefund()" id="refund-btn" disabled><?= __('process_refund') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- RECENT REFUNDS -->
<div class="card">
  <div class="card-title"><span>📋 <?= __('recent_refunds') ?></span></div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th><?= __('date') ?></th><th><?= __('customer_name') ?></th><th><?= __('invoice') ?></th><th><?= __('amount') ?></th><th><?= __('payment_mode') ?></th><th><?= __('reason') ?></th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r):
          $notes_parts = explode(' - ', $r['notes'], 2);
          $inv_ref = str_replace('Refund: ', '', $notes_parts[0] ?? '');
          $reason  = $notes_parts[1] ?? '';
        ?>
        <tr>
          <td style="font-size:12px;color:var(--text3)"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
          <td><?= htmlspecialchars($r['customer_name'] ?? 'Walk-in') ?></td>
          <td class="font-mono" style="font-size:11px"><?= htmlspecialchars($inv_ref) ?></td>
          <td class="text-red" style="font-weight:600"><?= fmt_money($r['amount']) ?></td>
          <td><span class="badge badge-gray"><?= htmlspecialchars($r['payment_mode']) ?></span></td>
          <td style="font-size:12px;color:var(--text3)"><?= htmlspecialchars($reason) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent)): ?>
        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text3)"><?= __('no_data') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$extra_js = '<script>
let currentInvoice = null;
let invoiceItems = [];

function searchInvoice() {
  const q = document.getElementById("inv-search").value.trim();
  if (!q) { showToast("Error","Enter an invoice number.","warning"); return; }

  fetch("' . BASE . '/api/invoice_lookup.php?q=" + encodeURIComponent(q))
    .then(r => r.json())
    .then(data => {
      if (data.error) { showToast("Not Found", data.error, "error"); return; }
      currentInvoice = data.invoice;
      invoiceItems = data.items;
      renderInvoiceResult();
    })
    .catch(() => showToast("Error","Network error.","error"));
}

function renderInvoiceResult() {
  const inv = currentInvoice;
  document.getElementById("inv-result").style.display = "block";
  document.getElementById("inv-details").innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <div>
        <span style="font-weight:600;font-family:var(--mono)">${inv.invoice_number}</span>
        <span class="badge badge-${inv.status==="paid"?"green":inv.status==="refunded"?"gray":"amber"}" style="margin-left:8px">${inv.status}</span>
      </div>
      <div style="font-weight:600">Total: <?= $currency ?> ${parseFloat(inv.total).toFixed(3)}</div>
      <div style="font-size:11px;color:var(--text3)">${inv.invoice_number} · ${inv.created_at}</div>
    </div>
    <table style="width:100%;font-size:11px;margin-top:6px">
      <tr><td style="padding:4px 0;color:var(--text3)">Product</td><td style="padding:4px 0;color:var(--text3)">Qty</td><td style="padding:4px 0;color:var(--text3)">Price</td><td style="padding:4px 0;color:var(--text3)">Total</td></tr>
      ${inv.items.map(item => `
      <tr>
        <td style="padding:4px 0">${item.name}</td>
        <td style="padding:4px 0">${item.qty}</td>
        <td style="padding:4px 0"><?= $currency ?> ${parseFloat(item.unit_price).toFixed(3)}</td>
        <td style="padding:4px 0"><?= $currency ?> ${parseFloat(item.total).toFixed(3)}</td>
      </tr>`).join("")}
    </table>
  `;

  let rows = "";
  invoiceItems.forEach(item => {
    rows += `<tr>
      <td style="font-weight:500">${item.product_name}</td>
      <td>${item.qty}</td>
      <td><?= $currency ?> ${parseFloat(item.unit_price).toFixed(3)}</td>
      <td><?= $currency ?> ${parseFloat(item.total).toFixed(3)}</td>
      <td><input type="number" min="0" max="${item.qty}" value="0" data-item-id="${item.id}" data-max-qty="${item.qty}" data-unit-price="${(item.total/item.qty).toFixed(3)}" class="form-input refund-qty" style="width:60px;text-align:center" onchange="calcRefundTotal()"></td>
    </tr>`;
  });
  document.getElementById("inv-items-body").innerHTML = rows;
  calcRefundTotal();
}

function calcRefundTotal() {
  let total = 0;
  document.querySelectorAll(".refund-qty").forEach(inp => {
    const qty = Math.min(parseInt(inp.value)||0, parseInt(inp.dataset.maxQty));
    inp.value = qty;
    total += qty * parseFloat(inp.dataset.unitPrice);
  });
  document.getElementById("refund-total").textContent = "<?= $currency ?> " + total.toFixed(3);
  document.getElementById("refund-btn").disabled = total <= 0;
}

function processRefund() {
  const items = [];
  document.querySelectorAll(".refund-qty").forEach(inp => {
    const qty = parseInt(inp.value)||0;
    if (qty > 0) items.push({ item_id: parseInt(inp.dataset.itemId), qty_return: qty });
  });
  if (!items.length) { showToast("Error","Select items to return.","warning"); return; }
  if (!confirm("Process this refund? This will return stock and record the refund payment.")) return;

  const payload = {
    invoice_id: currentInvoice.id,
    items: items,
    reason: document.getElementById("refund-reason").value,
    refund_mode: document.getElementById("refund-mode").value
  };

  fetch("' . BASE . '/api/refund.php", {
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(data => {
    if (data.success) {
      showToast("✓ Refund Processed", "<?= $currency ?> " + data.refund_amount + " refunded for " + data.invoice_number, "success");
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast("Error", data.error || "Refund failed.", "error");
    }
  }).catch(() => showToast("Error","Network error.","error"));
}

// Allow Enter key to search
document.getElementById("inv-search").addEventListener("keydown", function(e) {
  if (e.key === "Enter") searchInvoice();
});
</script>';
require __DIR__ . '/includes/footer.php';
?>
