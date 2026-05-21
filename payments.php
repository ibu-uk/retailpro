<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'payments';
$page_title   = __('payments_dues');
$db = db();
$currency = get_setting('currency', 'KWD');

// Record payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    $type   = in_array($_POST['pay_type'] ?? '', ['customer','supplier']) ? $_POST['pay_type'] : 'customer';
    $ref_id = (int)$_POST['ref_id'];
    $amount = (float)$_POST['amount'];
    $mode   = $_POST['payment_mode'] ?? 'cash';
    $uid    = current_user()['id'];

    if ($amount <= 0) {
        header('Location: ' . BASE . '/payments.php?error=' . urlencode('Amount must be greater than zero'));
        exit;
    }

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO payments (type,reference_id,amount,payment_mode,notes,created_by) VALUES (?,?,?,?,?,?)")
           ->execute([$type, $ref_id, $amount, $mode, trim($_POST['notes'] ?? ''), $uid]);

        if ($type === 'customer') {
            $db->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?")->execute([$amount, $ref_id]);
            // Settle unpaid invoices oldest-first; excess becomes advance credit on customer balance
            $unpaid_invs = $db->prepare("SELECT id, total, paid_amount FROM invoices WHERE customer_id=? AND status IN ('credit','partial') ORDER BY created_at ASC");
            $unpaid_invs->execute([$ref_id]);
            $remaining = $amount;
            while ($remaining > 0.001 && ($inv = $unpaid_invs->fetch())) {
                $owed  = $inv['total'] - $inv['paid_amount'];
                $apply = min($remaining, $owed);
                $new_paid   = round($inv['paid_amount'] + $apply, 3);
                $new_status = ($new_paid >= $inv['total'] - 0.001) ? 'paid' : 'partial';
                $db->prepare("UPDATE invoices SET paid_amount=?, status=? WHERE id=?")->execute([$new_paid, $new_status, $inv['id']]);
                $remaining -= $apply;
            }
        } else {
            $db->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ?")->execute([$amount, $ref_id]);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: ' . BASE . '/payments.php?error=' . urlencode('Payment failed: ' . $e->getMessage()));
        exit;
    }
    header('Location: ' . BASE . '/payments.php?success=' . urlencode('Payment of ' . fmt_money($amount) . ' recorded'));
    exit;
}

// Edit payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_payment') {
    $pid = (int)$_POST['payment_id'];
    $db->prepare("UPDATE payments SET payment_mode=?, notes=? WHERE id=?")->execute([
        $_POST['payment_mode'], trim($_POST['notes'] ?? ''), $pid
    ]);
    header('Location: ' . BASE . '/payments.php?success=' . urlencode('Payment updated'));
    exit;
}

// Delete payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_payment') {
    $pid = (int)$_POST['payment_id'];
    $pay = $db->prepare("SELECT * FROM payments WHERE id=?"); $pay->execute([$pid]); $pay_data = $pay->fetch();
    if ($pay_data) {
        $db->beginTransaction();
        try {
            // Reverse the balance change
            if ($pay_data['type'] === 'customer') {
                $db->prepare("UPDATE customers SET balance = balance - ? WHERE id = ?")->execute([$pay_data['amount'], $pay_data['reference_id']]);
                // Also reverse invoice settlements: reduce paid_amount on newest settled invoices first
                $amount_to_reverse = (float)$pay_data['amount'];
                $settled = $db->prepare("SELECT id, total, paid_amount, status FROM invoices WHERE customer_id=? AND status IN ('paid','partial') ORDER BY created_at DESC");
                $settled->execute([$pay_data['reference_id']]);
                while ($amount_to_reverse > 0.001 && ($inv = $settled->fetch())) {
                    $reduce = min($amount_to_reverse, $inv['paid_amount']);
                    $new_paid = max(0, $inv['paid_amount'] - $reduce);
                    $new_status = ($new_paid <= 0) ? 'credit' : (($new_paid < $inv['total']) ? 'partial' : 'paid');
                    $db->prepare("UPDATE invoices SET paid_amount=?, status=? WHERE id=?")->execute([$new_paid, $new_status, $inv['id']]);
                    $amount_to_reverse -= $reduce;
                }
            } else {
                $db->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?")->execute([$pay_data['amount'], $pay_data['reference_id']]);
            }
            $db->prepare("DELETE FROM payments WHERE id=?")->execute([$pid]);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            header('Location: ' . BASE . '/payments.php?error=' . urlencode('Delete failed: ' . $e->getMessage()));
            exit;
        }
    }
    header('Location: ' . BASE . '/payments.php?success=' . urlencode('Payment deleted & balance reversed'));
    exit;
}

// Stats
$rcvd_today   = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='customer' AND DATE(created_at)=CURDATE()")->fetchColumn();
$cust_dues    = $db->query("SELECT COALESCE(SUM(ABS(balance)),0) FROM customers WHERE balance<0")->fetchColumn();
$adv_rcvd     = $db->query("SELECT COALESCE(SUM(balance),0) FROM customers WHERE balance>0")->fetchColumn();
$paid_sup     = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='supplier' AND DATE(created_at)=CURDATE()")->fetchColumn();

// Due customers
$due_customers = $db->query("SELECT *, COALESCE(company_name,'') as company_name FROM customers WHERE balance < 0 ORDER BY balance ASC")->fetchAll();
// Due suppliers
$due_suppliers = $db->query("SELECT * FROM suppliers WHERE balance < 0 ORDER BY balance ASC")->fetchAll();
// Payment history with pagination
$page_num = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset   = ($page_num - 1) * $per_page;
$total_payments = $db->query("SELECT COUNT(*) FROM payments")->fetchColumn();
$total_pages = ceil($total_payments / $per_page);

$history = $db->query("
    SELECT p.*, u.name as user_name,
           CASE WHEN p.type='customer' THEN (SELECT name FROM customers WHERE id=p.reference_id)
                ELSE (SELECT company FROM suppliers WHERE id=p.reference_id) END as entity_name,
           CASE WHEN p.type='customer' THEN COALESCE((SELECT company_name FROM customers WHERE id=p.reference_id),'')
                ELSE '' END as entity_company
    FROM payments p LEFT JOIN users u ON u.id=p.created_by
    ORDER BY p.created_at DESC LIMIT $per_page OFFSET $offset
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-error" style="margin-bottom:16px">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="tabs">
  <div class="tab active" onclick="switchTab('customers-tab',this)"><?= __('nav_customers') ?></div>
  <div class="tab" onclick="switchTab('suppliers-tab',this)"><?= __('nav_suppliers') ?></div>
  <div class="tab" onclick="switchTab('history-tab',this)"><?= __('payment_history') ?></div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card green"><div class="stat-label"><?= __('received_today') ?></div><div class="stat-value text-green"><?= fmt_money($rcvd_today) ?></div></div>
  <div class="stat-card red"><div class="stat-label"><?= __('pending_collection') ?></div><div class="stat-value text-red"><?= fmt_money($cust_dues) ?></div></div>
  <div class="stat-card amber"><div class="stat-label"><?= __('advance') ?></div><div class="stat-value text-amber"><?= fmt_money($adv_rcvd) ?></div></div>
  <div class="stat-card blue"><div class="stat-label"><?= __('paid_to_suppliers') ?></div><div class="stat-value text-blue"><?= fmt_money($paid_sup) ?></div></div>
</div>

<!-- CUSTOMER DUES -->
<div id="customers-tab">
  <div class="card" style="margin-top:16px">
    <div class="card-title">
      <span>👥 <?= __('overdue_customers') ?></span>
      <div style="display:flex;gap:6px">
        <a href="<?= BASE ?>/api/export_dues.php?type=customer" class="btn btn-ghost btn-sm">📊 Excel</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="printTable('cust-dues-table','Customer Dues')">🖨️ Print</button>
      </div>
    </div>
    <div class="tbl-wrap">
      <table id="cust-dues-table">
        <thead><tr><th><?= __('customer_name') ?></th><th><?= __('type') ?></th><th><?= __('balance') ?></th><th><?= __('credit_limit') ?></th><th><?= __('actions') ?></th></tr></thead>
        <tbody>
          <?php foreach ($due_customers as $c): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="ledger-avatar" style="background:rgba(239,68,68,.08);color:var(--red);font-size:11px"><?= strtoupper(substr($c['name'],0,2)) ?></div>
                <div>
                  <div style="font-weight:500"><?= htmlspecialchars($c['name']) ?></div>
                  <?php if ($c['company_name']): ?><div style="font-size:11px;color:var(--accent2)">🏢 <?= htmlspecialchars($c['company_name']) ?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td><span class="badge badge-blue"><?= ucfirst($c['type']) ?></span></td>
            <td class="text-red" style="font-weight:600"><?= fmt_money(abs($c['balance'])) ?></td>
            <td><?= fmt_money($c['credit_limit']) ?></td>
            <td>
              <button class="btn btn-sm btn-green" onclick="openPayModal('customer',<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>', <?= abs($c['balance']) ?>)">💰 <?= __('collect') ?></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($due_customers)): ?><tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text3)">✅ <?= __('no_data') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- SUPPLIER DUES -->
<div id="suppliers-tab" style="display:none">
  <div class="card" style="margin-top:16px">
    <div class="card-title">
      <span>🏭 <?= __('overdue_suppliers') ?></span>
      <div style="display:flex;gap:6px">
        <a href="<?= BASE ?>/api/export_dues.php?type=supplier" class="btn btn-ghost btn-sm">📊 Excel</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="printTable('sup-dues-table','Supplier Dues')">🖨️ Print</button>
      </div>
    </div>
    <div class="tbl-wrap">
      <table id="sup-dues-table">
        <thead><tr><th><?= __('nav_suppliers') ?></th><th><?= __('payment_terms') ?></th><th><?= __('amount') ?></th><th><?= __('actions') ?></th></tr></thead>
        <tbody>
          <?php foreach ($due_suppliers as $s): ?>
          <tr>
            <td style="font-weight:500">🏭 <?= htmlspecialchars($s['company']) ?></td>
            <td><?= htmlspecialchars($s['payment_terms']) ?></td>
            <td class="text-red" style="font-weight:600"><?= fmt_money(abs($s['balance'])) ?></td>
            <td>
              <button class="btn btn-sm btn-green" onclick="openPayModal('supplier',<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['company'])) ?>', <?= abs($s['balance']) ?>)"><?= __('pay_now') ?></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($due_suppliers)): ?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text3)">✅ <?= __('no_data') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- PAYMENT HISTORY -->
<div id="history-tab" style="display:none">
  <div class="card" style="margin-top:16px">
    <div class="card-title">
      <span>📋 <?= __('payment_history') ?></span>
      <div style="display:flex;gap:6px">
        <a href="<?= BASE ?>/api/export_payments.php" class="btn btn-ghost btn-sm">📊 Excel</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="printPayments()">🖨️ Print</button>
      </div>
    </div>
    <div class="tbl-wrap">
      <table id="payments-table">
        <thead><tr><th><?= __('date') ?></th><th><?= __('name') ?></th><th><?= __('type') ?></th><th><?= __('amount') ?></th><th><?= __('payment_mode') ?></th><th><?= __('notes') ?></th><th><?= __('created_by') ?></th><th><?= __('actions') ?></th></tr></thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <tr>
            <td class="font-mono" style="font-size:11px;color:var(--text3)"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
            <td>
                <div style="font-weight:500"><?= htmlspecialchars($h['entity_name'] ?? '—') ?></div>
                <?php if (!empty($h['entity_company'])): ?><div style="font-size:11px;color:var(--accent2)">🏢 <?= htmlspecialchars($h['entity_company']) ?></div><?php endif; ?>
              </td>
            <td><span class="badge <?= $h['type']==='customer'?'badge-green':'badge-red' ?>"><?= ucfirst($h['type']) ?></span></td>
            <td class="text-green" style="font-weight:600"><?= fmt_money($h['amount']) ?></td>
            <td><?= strtoupper($h['payment_mode']) ?></td>
            <td style="font-size:11px;color:var(--text3);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($h['notes'] ?? '') ?></td>
            <td style="font-size:12px;color:var(--text3)"><?= htmlspecialchars($h['user_name'] ?? '—') ?></td>
            <td>
              <div style="display:flex;gap:4px">
                <button class="btn btn-ghost btn-sm" onclick='editPayment(<?= json_encode($h, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this payment and reverse the balance?')">
                  <input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?= $h['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">🗑️</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($history)): ?><tr><td colspan="8" style="text-align:center;padding:20px;color:var(--text3)"><?= __('no_data') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--text3)">
      <span><?= __('showing') ?> <?= count($history) ?> <?= __('of') ?> <?= $total_payments ?></span>
      <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?p=<?= $i ?>" class="page-link <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</div>

<!-- PAYMENT MODAL -->
<div class="modal-backdrop" id="pay-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="pay-modal-title"><?= __('record_payment') ?></div>
      <button class="modal-close" onclick="closeModal('pay-modal')">✕</button>
    </div>
    <form method="POST" id="pay-form">
      <input type="hidden" name="action" value="pay">
      <input type="hidden" name="pay_type" id="pay-type">
      <input type="hidden" name="ref_id" id="pay-ref-id">
      <div class="modal-body">
        <div class="form-group"><label class="form-label"><?= __('amount') ?> (<?= $currency ?>) *</label><input class="form-input" name="amount" id="pay-amount" type="number" step="0.001" min="0.001" required></div>
        <div class="form-group"><label class="form-label"><?= __('payment_mode') ?></label>
          <select class="form-select" name="payment_mode">
            <option value="cash">💵 <?= __('cash') ?></option>
            <option value="knet">💳 <?= __('knet') ?></option>
            <option value="wamd">📱 <?= __('wamd') ?></option>
            <option value="transfer">🏦 <?= __('transfer') ?></option>
          </select>
        </div>
        <div class="form-group"><label class="form-label"><?= __('notes') ?></label><textarea class="form-textarea" name="notes"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('pay-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-green">✓ <?= __('record_payment') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT PAYMENT MODAL -->
<div class="modal-backdrop" id="edit-pay-modal">
  <div class="modal" style="width:400px">
    <div class="modal-header">
      <div class="modal-title"><?= __('edit') ?> <?= __('nav_payments') ?></div>
      <button class="modal-close" onclick="closeModal('edit-pay-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_payment">
      <input type="hidden" name="payment_id" id="ep-id">
      <div class="modal-body">
        <div class="form-group"><label class="form-label"><?= __('payment_mode') ?></label>
          <select class="form-select" name="payment_mode" id="ep-mode">
            <option value="cash">💵 <?= __('cash') ?></option><option value="knet">💳 <?= __('knet') ?></option><option value="wamd">📱 <?= __('wamd') ?></option><option value="transfer">🏦 <?= __('transfer') ?></option>
          </select>
        </div>
        <div class="form-group"><label class="form-label"><?= __('notes') ?></label><input class="form-input" name="notes" id="ep-notes"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('edit-pay-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_js = '<script>
function switchTab(id, el) {
  document.querySelectorAll("#customers-tab,#suppliers-tab,#history-tab").forEach(t => t.style.display="none");
  document.getElementById(id).style.display = "block";
  document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
  el.classList.add("active");
}
function openPayModal(type, id, name, amount) {
  document.getElementById("pay-type").value = type;
  document.getElementById("pay-ref-id").value = id;
  document.getElementById("pay-amount").value = amount.toFixed(3);
  document.getElementById("pay-modal-title").textContent = "Collect from: " + name;
  openModal("pay-modal");
}
function editPayment(p) {
  document.getElementById("ep-id").value = p.id;
  document.getElementById("ep-mode").value = p.payment_mode;
  document.getElementById("ep-notes").value = p.notes || "";
  openModal("edit-pay-modal");
}
function printTable(tableId, title) {
  const table = document.getElementById(tableId);
  const win = window.open("","_blank");
  win.document.write("<html><head><title>" + title + "</title><style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}th{background:#f0f2f5;font-weight:600}.header{text-align:center;margin-bottom:20px}</style></head><body>");
  win.document.write("<div class=header><h2>RetailPro — " + title + "</h2></div>");
  const clone = table.cloneNode(true);
  clone.querySelectorAll("tr").forEach(row => { const cells = row.querySelectorAll("th,td"); if(cells.length>=4) cells[cells.length-1].remove(); });
  win.document.write(clone.outerHTML);
  win.document.write("</body></html>");
  win.document.close();
  win.print();
}
function printPayments() {
  const table = document.getElementById("payments-table");
  const win = window.open("","_blank");
  win.document.write("<html><head><title>Payment History</title><style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}th{background:#f0f2f5;font-weight:600}.header{text-align:center;margin-bottom:20px}</style></head><body>");
  win.document.write("<div class=header><h2>RetailPro — Payment History</h2></div>");
  const clone = table.cloneNode(true);
  clone.querySelectorAll("tr").forEach(row => { const cells = row.querySelectorAll("th,td"); if(cells.length>=8) cells[cells.length-1].remove(); });
  win.document.write(clone.outerHTML);
  win.document.write("</body></html>");
  win.document.close();
  win.print();
}
</script>';
require __DIR__ . '/includes/footer.php'; ?>
