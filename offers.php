<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'offers';
$page_title   = __('offers_promos');
$db = db();
$currency = get_setting('currency', 'KWD');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $end   = $_POST['end_date'];
    $start = $_POST['start_date'];
    if ($end < $start) {
        header('Location: ' . BASE . '/offers.php?error=' . urlencode('End date must be on or after start date'));
        exit;
    }
    $promo = strtoupper(trim($_POST['promo_code']));
    // Warn if duplicate promo code
    if ($promo) {
        $dup = $db->prepare("SELECT COUNT(*) FROM offers WHERE promo_code=? AND end_date >= CURDATE()");
        $dup->execute([$promo]);
        if ($dup->fetchColumn() > 0) {
            header('Location: ' . BASE . '/offers.php?error=' . urlencode('Promo code "' . $promo . '" already exists on an active/upcoming offer'));
            exit;
        }
    }
    $db->prepare("INSERT INTO offers (title,description,type,discount_value,promo_code,applies_to,start_date,end_date,usage_limit) VALUES (?,?,?,?,?,?,?,?,?)")->execute([
        trim($_POST['title']), trim($_POST['description']), $_POST['type'],
        (float)$_POST['discount_value'], $promo, trim($_POST['applies_to']),
        $start, $end, (int)$_POST['usage_limit']
    ]);
    header('Location: ' . BASE . '/offers.php?success=' . urlencode('Offer created'));
    exit;
}

if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE offers SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_GET['toggle']]);
    header('Location: ' . BASE . '/offers.php');
    exit;
}

// Edit offer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_offer') {
    $end   = $_POST['end_date'];
    $start = $_POST['start_date'];
    if ($end < $start) {
        header('Location: ' . BASE . '/offers.php?error=' . urlencode('End date must be on or after start date'));
        exit;
    }
    $db->prepare("UPDATE offers SET title=?,description=?,type=?,discount_value=?,promo_code=?,applies_to=?,start_date=?,end_date=?,usage_limit=? WHERE id=?")->execute([
        trim($_POST['title']), trim($_POST['description']), $_POST['type'],
        (float)$_POST['discount_value'], strtoupper(trim($_POST['promo_code'])),
        trim($_POST['applies_to']), $start, $end, (int)$_POST['usage_limit'], (int)$_POST['offer_id']
    ]);
    header('Location: ' . BASE . '/offers.php?success=' . urlencode('Offer updated'));
    exit;
}

// Delete offer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_offer') {
    $db->prepare("DELETE FROM offers WHERE id=?")->execute([(int)$_POST['offer_id']]);
    header('Location: ' . BASE . '/offers.php?success=' . urlencode('Offer deleted'));
    exit;
}

$filter = $_GET['status'] ?? '';
$where = match($filter) {
    'active'    => "WHERE is_active=1 AND start_date <= CURDATE() AND end_date >= CURDATE()",
    'scheduled' => "WHERE is_active=1 AND start_date > CURDATE()",
    'expired'   => "WHERE end_date < CURDATE()",
    default     => ""
};
$offers = $db->query("SELECT * FROM offers $where ORDER BY created_at DESC")->fetchAll();
$categories = $db->query("SELECT id, name, emoji FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-error" style="margin-bottom:16px">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="inv-filters">
  <a href="<?= BASE ?>/offers.php" class="filter-chip <?= !$filter?'active':'' ?>"><?= __('all') ?></a>
  <a href="<?= BASE ?>/offers.php?status=active" class="filter-chip <?= $filter==='active'?'active':'' ?>"><?= __('active') ?></a>
  <a href="<?= BASE ?>/offers.php?status=scheduled" class="filter-chip <?= $filter==='scheduled'?'active':'' ?>"><?= __('scheduled') ?></a>
  <a href="<?= BASE ?>/offers.php?status=expired" class="filter-chip <?= $filter==='expired'?'active':'' ?>"><?= __('expired') ?></a>
  <div style="margin-left:auto"><button class="btn btn-primary" onclick="openModal('offer-modal')">+ <?= __('create_offer') ?></button></div>
</div>

<div class="grid-3">
  <?php foreach ($offers as $o):
    $now = date('Y-m-d');
    $is_active = $o['is_active'] && $o['start_date'] <= $now && $o['end_date'] >= $now;
    $is_sched  = $o['is_active'] && $o['start_date'] > $now;
    $pct = $o['usage_limit'] > 0 ? round($o['usage_count'] / $o['usage_limit'] * 100) : 0;
    $border_color = $is_active ? 'var(--green)' : ($is_sched ? 'var(--accent)' : 'var(--border2)');
    $badge = $is_active ? '<span class="badge badge-green"><span class="dot"></span>' . __('active') . '</span>'
           : ($is_sched ? '<span class="badge badge-blue">' . __('scheduled') . '</span>'
           : '<span class="badge badge-gray">' . __('expired') . '</span>');
  ?>
  <div class="card" style="border-top:2px solid <?= $border_color ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
      <div style="font-size:20px">🎯</div>
      <?= $badge ?>
    </div>
    <div style="font-size:15px;font-weight:600;margin-bottom:4px"><?= htmlspecialchars($o['title']) ?></div>
    <div style="font-size:12px;color:var(--text3);margin-bottom:6px"><?= htmlspecialchars($o['description']) ?></div>
    <?php if ($o['promo_code']): ?>
    <div style="font-size:12px;color:var(--accent2);margin-bottom:6px">Code: <code style="background:var(--bg4);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($o['promo_code']) ?></code></div>
    <?php endif; ?>
    <?php if ($o['usage_limit'] > 0): ?>
    <div class="progress" style="margin-bottom:6px"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $border_color ?>"></div></div>
    <div style="font-size:11px;color:var(--text3);display:flex;justify-content:space-between;margin-bottom:10px">
      <span><?= date('d M', strtotime($o['start_date'])) ?> – <?= date('d M Y', strtotime($o['end_date'])) ?></span>
      <span><?= $pct ?>% used (<?= $o['usage_count'] ?>/<?= $o['usage_limit'] ?>)</span>
    </div>
    <?php else: ?>
    <div style="font-size:11px;color:var(--text3);margin-bottom:10px"><?= date('d M', strtotime($o['start_date'])) ?> – <?= date('d M Y', strtotime($o['end_date'])) ?></div>
    <?php endif; ?>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <a href="<?= BASE ?>/offers.php?toggle=<?= $o['id'] ?>" class="btn btn-ghost btn-sm"><?= $o['is_active'] ? '⏸ ' . __('disable') : '▶ ' . __('enable') ?></a>
      <button class="btn btn-ghost btn-sm" onclick='editOffer(<?= json_encode($o, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this offer?')">
        <input type="hidden" name="action" value="delete_offer">
        <input type="hidden" name="offer_id" value="<?= $o['id'] ?>">
        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">🗑️</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($offers)): ?>
  <div class="card" style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text3)"><?= __('no_data') ?></div>
  <?php endif; ?>
</div>

<!-- ADD OFFER MODAL -->
<div class="modal-backdrop" id="offer-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?= __('create_offer') ?></div>
      <button class="modal-close" onclick="closeModal('offer-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group"><label class="form-label"><?= __('offer_title') ?> *</label><input class="form-input" name="title" required placeholder="e.g. Summer Sale 2025"></div>
        <div class="form-group"><label class="form-label"><?= __('description') ?></label><textarea class="form-textarea" name="description" placeholder="20% off all Bags category..."></textarea></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('type') ?></label>
            <select class="form-select" name="type" id="offer-type">
              <option value="percent">Percentage Discount (%)</option>
              <option value="fixed">Fixed Amount Off (<?= $currency ?>)</option>
              <option value="bogo">Buy 1 Get 1 Free</option>
              <option value="promo_code">Promo Code (% discount)</option>
            </select>
            <div style="font-size:11px;color:var(--text3);margin-top:4px">Choose <em>Promo Code</em> for codes customers type at checkout</div>
          </div>
          <div class="form-group"><label class="form-label"><?= __('discount_value') ?> (%/<?= $currency ?>)</label><input class="form-input" name="discount_value" type="number" step="0.001" min="0" value="0"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('promo_code_label') ?></label><input class="form-input" name="promo_code" placeholder="e.g. EID2025" style="text-transform:uppercase"></div>
          <div class="form-group"><label class="form-label"><?= __('applies_to') ?></label>
            <select class="form-select" name="applies_to">
              <option value="all"><?= __('all') ?> <?= __('total_products') ?></option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= $cat['emoji'] ?> <?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('start_date') ?> *</label><input class="form-input" name="start_date" type="date" required value="<?= date('Y-m-d') ?>"></div>
          <div class="form-group"><label class="form-label"><?= __('end_date') ?> *</label><input class="form-input" name="end_date" type="date" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('usage_limit') ?></label><input class="form-input" name="usage_limit" type="number" min="0" value="0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('offer-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT OFFER MODAL -->
<div class="modal-backdrop" id="edit-offer-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Offer</div>
      <button class="modal-close" onclick="closeModal('edit-offer-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_offer">
      <input type="hidden" name="offer_id" id="eo-id">
      <div class="modal-body" style="max-height:60vh;overflow-y:auto">
        <div class="form-group"><label class="form-label"><?= __('offer_title') ?> *</label><input class="form-input" name="title" id="eo-title" required></div>
        <div class="form-group"><label class="form-label"><?= __('description') ?></label><textarea class="form-textarea" name="description" id="eo-desc"></textarea></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('type') ?></label>
            <select class="form-select" name="type" id="eo-type">
              <option value="percent">Percentage Discount (%)</option>
              <option value="fixed">Fixed Amount Off (<?= $currency ?>)</option>
              <option value="bogo">Buy 1 Get 1 Free</option>
              <option value="promo_code">Promo Code (% discount)</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label"><?= __('discount_value') ?> (%/<?= $currency ?>)</label><input class="form-input" name="discount_value" id="eo-value" type="number" step="0.001" min="0"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('promo_code_label') ?></label><input class="form-input" name="promo_code" id="eo-code" placeholder="e.g. EID2025" style="text-transform:uppercase"></div>
          <div class="form-group"><label class="form-label"><?= __('applies_to') ?></label>
            <select class="form-select" name="applies_to" id="eo-applies">
              <option value="all"><?= __('all') ?> Products</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= $cat['emoji'] ?> <?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('start_date') ?> *</label><input class="form-input" name="start_date" id="eo-start" type="date" required></div>
          <div class="form-group"><label class="form-label"><?= __('end_date') ?> *</label><input class="form-input" name="end_date" id="eo-end" type="date" required></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('usage_limit') ?> <span style="color:var(--text3);font-weight:400">(0 = unlimited)</span></label><input class="form-input" name="usage_limit" id="eo-limit" type="number" min="0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('edit-offer-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
ob_start(); ?>
<script>
function editOffer(o) {
  document.getElementById("eo-id").value    = o.id;
  document.getElementById("eo-title").value = o.title;
  document.getElementById("eo-desc").value  = o.description || "";
  document.getElementById("eo-type").value  = o.type;
  document.getElementById("eo-value").value = parseFloat(o.discount_value).toFixed(3);
  document.getElementById("eo-code").value  = o.promo_code || "";
  document.getElementById("eo-applies").value = o.applies_to || "all";
  document.getElementById("eo-start").value = o.start_date;
  document.getElementById("eo-end").value   = o.end_date;
  document.getElementById("eo-limit").value = o.usage_limit;
  openModal("edit-offer-modal");
}
// Date validation on add form
document.querySelector('#offer-modal form').addEventListener('submit', function(e) {
  const start = this.querySelector('[name="start_date"]').value;
  const end   = this.querySelector('[name="end_date"]').value;
  if (end < start) {
    e.preventDefault();
    showToast("Error", "End date must be on or after start date", "error");
  }
});
</script>
<?php
$extra_js = ob_get_clean();
require __DIR__ . '/includes/footer.php'; ?>
