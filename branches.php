<?php
require_once __DIR__ . '/includes/config.php';
require_login();
require_role('super_admin', 'manager');
$current_page = 'branches';
$page_title   = __('branch_management');
$db = db();

// ── ADD BRANCH ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO branches (name,address,phone,manager_name) VALUES (?,?,?,?)")->execute([
            trim($_POST['name']), trim($_POST['address']), trim($_POST['phone']), trim($_POST['manager_name'])
        ]);
        $new_id = $db->lastInsertId();

        // Assign categories based on selection
        $assign = $_POST['assign_cats'] ?? 'all';
        if ($assign === 'all') {
            $db->prepare("INSERT IGNORE INTO branch_categories (branch_id, category_id)
                SELECT ?, id FROM categories WHERE COALESCE(is_active,1)=1")->execute([$new_id]);
        } elseif ($assign === 'specific' && !empty($_POST['category_ids'])) {
            $stmt = $db->prepare("INSERT IGNORE INTO branch_categories (branch_id, category_id) VALUES (?,?)");
            foreach ($_POST['category_ids'] as $cid) {
                $stmt->execute([$new_id, (int)$cid]);
            }
        }
        // none = no categories assigned yet
        $db->commit();
        header('Location: ' . BASE . '/branches.php?success=' . urlencode('Branch added')); exit;
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: ' . BASE . '/branches.php?error=' . urlencode($e->getMessage())); exit;
    }
}

// ── EDIT BRANCH ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    $bid = (int)$_POST['branch_id'];
    $db->prepare("UPDATE branches SET name=?,address=?,phone=?,manager_name=? WHERE id=?")->execute([
        trim($_POST['name']), trim($_POST['address']), trim($_POST['phone']),
        trim($_POST['manager_name']), $bid
    ]);
    header('Location: ' . BASE . '/branches.php?success=' . urlencode('Branch updated')); exit;
}

// ── TOGGLE BRANCH ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'toggle') {
    $db->prepare("UPDATE branches SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_POST['branch_id']]);
    header('Location: ' . BASE . '/branches.php?success=' . urlencode('Status updated')); exit;
}

// ── SAVE CATEGORY ASSIGNMENTS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_cats') {
    $bid = (int)$_POST['branch_id'];
    $db->beginTransaction();
    try {
        // Remove all current assignments
        $db->prepare("DELETE FROM branch_categories WHERE branch_id=?")->execute([$bid]);
        // Re-insert selected
        if (!empty($_POST['category_ids'])) {
            $stmt = $db->prepare("INSERT IGNORE INTO branch_categories (branch_id, category_id) VALUES (?,?)");
            foreach ($_POST['category_ids'] as $cid) {
                $stmt->execute([$bid, (int)$cid]);
            }
        }
        $db->commit();
        header('Location: ' . BASE . '/branches.php?success=' . urlencode('Categories updated for branch')); exit;
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: ' . BASE . '/branches.php?error=' . urlencode($e->getMessage())); exit;
    }
}

// ── LOAD DATA ──
$branches = $db->query("SELECT * FROM branches ORDER BY id")->fetchAll();
$all_cats = $db->query("SELECT id, name, COALESCE(name_ar,'') as name_ar, COALESCE(emoji,'📦') as emoji
                         FROM categories WHERE COALESCE(is_active,1)=1 ORDER BY name")->fetchAll();

// Get assigned categories per branch
$bc_map = [];
$bc_rows = $db->query("SELECT branch_id, category_id FROM branch_categories")->fetchAll();
foreach ($bc_rows as $r) {
    $bc_map[$r['branch_id']][] = $r['category_id'];
}

// Stats per branch
$branch_stats = [];
foreach ($branches as $b) {
    $s = $db->prepare("SELECT COALESCE(SUM(total),0) as sales,
                               COALESCE(SUM(CASE WHEN payment_mode='cash' THEN total ELSE 0 END),0) as cash,
                               COUNT(*) as orders
                        FROM invoices WHERE branch_id=? AND DATE(created_at)=CURDATE()");
    $s->execute([$b['id']]); $row = $s->fetch();

    $month = $db->prepare("SELECT COALESCE(SUM(total),0) as s FROM invoices
                            WHERE branch_id=? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
    $month->execute([$b['id']]); $mrow = $month->fetch();

    $staff = $db->prepare("SELECT COUNT(*) FROM users WHERE branch_id=? AND is_active=1");
    $staff->execute([$b['id']]);

    $branch_stats[$b['id']] = [
        'sales'   => $row['sales'],
        'cash'    => $row['cash'],
        'orders'  => $row['orders'],
        'monthly' => $mrow['s'],
        'staff'   => $staff->fetchColumn(),
        'cats'    => count($bc_map[$b['id']] ?? []),
    ];
}

$border_colors = ['var(--accent)','var(--blue)','var(--green)','var(--pink)','var(--amber)','var(--teal)'];
require __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-error" style="margin-bottom:16px">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
  <div>
    <div style="font-size:13px;color:var(--text3)"><?= count($branches) ?> branches · <?= count($all_cats) ?> categories total</div>
  </div>
  <button class="btn btn-primary" onclick="openAddBranch()">+ <?= __('add') ?> Branch</button>
</div>

<!-- BRANCH CARDS GRID -->
<div class="grid-2">
  <?php foreach ($branches as $i => $b):
    $s   = $branch_stats[$b['id']];
    $assigned = $bc_map[$b['id']] ?? [];
    $color = $border_colors[$i % count($border_colors)];
  ?>
  <div class="card" style="border-left:3px solid <?= $color ?>;opacity:<?= $b['is_active'] ? '1' : '.5' ?>">
    <!-- Branch Header -->
    <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:14px">
      <div style="width:42px;height:42px;border-radius:10px;background:<?= $color ?>;opacity:.15;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">🏪</div>
      <div style="flex:1;min-width:0">
        <div style="font-size:15px;font-weight:600"><?= htmlspecialchars($b['name']) ?></div>
        <div style="font-size:12px;color:var(--text3)"><?= htmlspecialchars($b['address']) ?></div>
        <div style="font-size:12px;color:var(--text3)">👤 <?= htmlspecialchars($b['manager_name']) ?> · 📞 <?= htmlspecialchars($b['phone']) ?></div>
      </div>
      <span class="badge <?= $b['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $b['is_active'] ? 'Active' : 'Inactive' ?></span>
    </div>

    <!-- Stats Row -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px">
      <div style="background:var(--bg3);border-radius:8px;padding:10px;text-align:center">
        <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.3px">Today Sales</div>
        <div style="font-size:15px;font-weight:600;color:var(--green)"><?= fmt_money($s['sales']) ?></div>
      </div>
      <div style="background:var(--bg3);border-radius:8px;padding:10px;text-align:center">
        <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.3px">This Month</div>
        <div style="font-size:15px;font-weight:600;color:var(--blue)"><?= fmt_money($s['monthly']) ?></div>
      </div>
      <div style="background:var(--bg3);border-radius:8px;padding:10px;text-align:center">
        <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.3px">Orders Today</div>
        <div style="font-size:15px;font-weight:600;color:var(--accent2)"><?= $s['orders'] ?></div>
      </div>
    </div>

    <!-- Category Assignment Badge -->
    <div style="background:var(--bg3);border-radius:8px;padding:10px;margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:var(--text3)">
          📂 Assigned Categories
          <span class="badge badge-blue" style="margin-left:6px"><?= count($assigned) ?> / <?= count($all_cats) ?></span>
          <?php if (count($assigned) === count($all_cats)): ?>
          <span class="badge badge-green" style="margin-left:4px">All Products</span>
          <?php elseif (count($assigned) === 0): ?>
          <span class="badge badge-red" style="margin-left:4px">No Products!</span>
          <?php endif; ?>
        </div>
        <button class="btn btn-ghost btn-sm" onclick="openCatModal(<?= $b['id'] ?>, '<?= htmlspecialchars(addslashes($b['name'])) ?>', <?= json_encode($assigned) ?>)">
          ✏️ Edit
        </button>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:4px">
        <?php foreach ($all_cats as $cat): ?>
        <span class="badge <?= in_array($cat['id'], $assigned) ? 'badge-green' : 'badge-gray' ?>"
              style="opacity:<?= in_array($cat['id'], $assigned) ? '1' : '.35' ?>">
          <?= htmlspecialchars($cat['emoji'] . ' ' . $cat['name']) ?>
        </span>
        <?php endforeach; ?>
        <?php if (empty($all_cats)): ?>
        <span style="font-size:12px;color:var(--text3)">No categories yet — add some first</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
      <a href="<?= BASE ?>/reports.php?branch_id=<?= $b['id'] ?>"
         class="btn btn-ghost btn-sm"
         title="View sales report filtered to this branch only">
        📈 Report
      </a>
      <a href="<?= BASE ?>/inventory.php?branch_id=<?= $b['id'] ?>"
         class="btn btn-ghost btn-sm"
         title="View and adjust stock levels for this branch">
        📦 Stock
      </a>
      <button class="btn btn-ghost btn-sm"
              onclick='editBranch(<?= json_encode($b, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'
              title="Edit branch name, manager, phone and address">
        ✏️ Edit
      </button>
      <form method="POST" style="display:inline">
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="branch_id" value="<?= $b['id'] ?>">
        <button type="submit"
                class="btn btn-ghost btn-sm"
                style="color:<?= $b['is_active'] ? 'var(--red)' : 'var(--green)' ?>"
                title="<?= $b['is_active'] ? 'Disable branch — hides it from POS and reports' : 'Enable branch — makes it visible again' ?>">
          <?= $b['is_active'] ? '⏸ Disable' : '▶ Enable' ?>
        </button>
      </form>
      <div style="margin-left:auto;font-size:11px;color:var(--text3);display:flex;align-items:center;gap:6px">
        <span title="Active staff assigned to this branch">👥 <?= $s['staff'] ?> staff</span>
        <?php if ($s['staff'] === 0 && $b['is_active']): ?>
        <span class="badge badge-amber" style="font-size:9px" title="No users assigned to this branch">No users!</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══════════════════════════════════════
     ADD BRANCH MODAL
══════════════════════════════════════ -->
<div class="modal-backdrop" id="branch-add-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🏪 Add New Branch</div>
      <button class="modal-close" onclick="closeModal('branch-add-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Branch Name *</label>
            <input class="form-input" name="name" required placeholder="e.g. Salmiya Branch">
          </div>
          <div class="form-group">
            <label class="form-label">Manager Name</label>
            <input class="form-input" name="manager_name" placeholder="e.g. Ahmed Karim">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input class="form-input" name="phone" placeholder="+965 2200-0000">
          </div>
          <div class="form-group">
            <label class="form-label">Address</label>
            <input class="form-input" name="address" placeholder="Block 4, Salmiya">
          </div>
        </div>

        <!-- Category Assignment -->
        <div style="background:var(--bg3);border-radius:var(--r);padding:14px;margin-top:4px">
          <div style="font-weight:600;margin-bottom:10px;font-size:13px">📂 Which products can this branch sell?</div>
          <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px">
            <label class="radio-option-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px;border-radius:var(--r);border:1.5px solid var(--accent);background:rgba(67,97,238,.06);transition:all .15s">
              <input type="radio" name="assign_cats" value="all" checked onchange="toggleCatPicker(this)">
              <div>
                <div style="font-weight:500">✅ All Products</div>
                <div style="font-size:11px;color:var(--text3)">Branch can sell everything</div>
              </div>
            </label>
            <label class="radio-option-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px;border-radius:var(--r);border:1.5px solid var(--border2);transition:all .15s">
              <input type="radio" name="assign_cats" value="specific" onchange="toggleCatPicker(this)">
              <div>
                <div style="font-weight:500">🎯 Specific Categories Only</div>
                <div style="font-size:11px;color:var(--text3)">Choose which categories this branch sells</div>
              </div>
            </label>
            <label class="radio-option-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px;border-radius:var(--r);border:1.5px solid var(--border2);transition:all .15s">
              <input type="radio" name="assign_cats" value="none" onchange="toggleCatPicker(this)">
              <div>
                <div style="font-weight:500">⏸ None (setup later)</div>
                <div style="font-size:11px;color:var(--text3)">Assign categories from branch card later</div>
              </div>
            </label>
          </div>

          <!-- Category checkboxes (shown when specific selected) -->
          <div id="cat-picker-add" style="display:none">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <div style="font-size:12px;color:var(--text3)">Select categories:</div>
              <div style="display:flex;gap:6px">
                <button type="button" class="btn btn-ghost btn-sm" onclick="checkAllAddCats(true)">✅ All</button>
                <button type="button" class="btn btn-ghost btn-sm" onclick="checkAllAddCats(false)">☐ None</button>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
              <?php foreach ($all_cats as $cat): ?>
              <label style="display:flex;align-items:center;gap:6px;padding:6px 8px;background:var(--bg4);border-radius:6px;cursor:pointer;font-size:12px">
                <input type="checkbox" name="category_ids[]" value="<?= $cat['id'] ?>">
                <?= htmlspecialchars($cat['emoji'] . ' ' . $cat['name']) ?>
                <?php if ($cat['name_ar']): ?><span style="color:var(--text3);font-size:10px;direction:rtl"><?= htmlspecialchars($cat['name_ar']) ?></span><?php endif; ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('branch-add-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary">💾 Save Branch</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════
     EDIT BRANCH MODAL
══════════════════════════════════════ -->
<div class="modal-backdrop" id="branch-edit-modal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Branch</div>
      <button class="modal-close" onclick="closeModal('branch-edit-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="branch_id" id="edit-branch-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Branch Name *</label><input class="form-input" name="name" id="edit-branch-name" required></div>
          <div class="form-group"><label class="form-label">Manager</label><input class="form-input" name="manager_name" id="edit-branch-manager"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone" id="edit-branch-phone"></div>
          <div class="form-group"><label class="form-label">Address</label><input class="form-input" name="address" id="edit-branch-address"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('branch-edit-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary">💾 Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════
     CATEGORY ASSIGNMENT MODAL
══════════════════════════════════════ -->
<div class="modal-backdrop" id="cat-assign-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">📂 Categories for <span id="cat-modal-branch-name"></span></div>
      <button class="modal-close" onclick="closeModal('cat-assign-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save_cats">
      <input type="hidden" name="branch_id" id="cat-modal-branch-id">
      <div class="modal-body">
        <!-- Quick actions -->
        <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
          <button type="button" class="btn btn-ghost btn-sm" onclick="checkAllCats(true)">✅ Select All</button>
          <button type="button" class="btn btn-ghost btn-sm" onclick="checkAllCats(false)">☐ Deselect All</button>
          <span style="font-size:12px;color:var(--text3);display:flex;align-items:center">
            <span id="cat-selected-count">0</span> of <?= count($all_cats) ?> selected
          </span>
        </div>

        <!-- Category checkboxes -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px" id="cat-checkbox-grid">
          <?php foreach ($all_cats as $cat): ?>
          <label class="cat-assign-item" style="display:flex;align-items:center;gap:8px;padding:10px;background:var(--bg3);border-radius:var(--r);cursor:pointer;border:1.5px solid var(--border);transition:all .15s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="updateCatStyle(this)">
            <input type="checkbox" name="category_ids[]" value="<?= $cat['id'] ?>"
                   class="cat-cb" onchange="updateCatCount();updateCatStyle(this.closest('label'))">
            <span style="font-size:18px"><?= htmlspecialchars($cat['emoji']) ?></span>
            <div style="min-width:0">
              <div style="font-weight:500;font-size:13px"><?= htmlspecialchars($cat['name']) ?></div>
              <?php if ($cat['name_ar']): ?>
              <div style="font-size:10px;color:var(--text3);direction:rtl"><?= htmlspecialchars($cat['name_ar']) ?></div>
              <?php endif; ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>

        <?php if (empty($all_cats)): ?>
        <div style="text-align:center;padding:30px;color:var(--text3)">
          No categories found. Add categories first from the Categories page.
        </div>
        <?php endif; ?>

        <div style="margin-top:12px;padding:10px;background:rgba(245,166,35,.08);border:1px solid rgba(245,166,35,.2);border-radius:var(--r);font-size:12px;color:var(--amber)">
          ⚠️ Only checked categories will appear on this branch's POS. Unchecked categories and their products will be hidden from cashiers.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('cat-assign-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary">💾 Save Category Assignment</button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_js = '<script>
// ── Add branch modal ──
function openAddBranch() {
  document.querySelector("[name=assign_cats][value=all]").checked = true;
  document.getElementById("cat-picker-add").style.display = "none";
  // Reset radio styles - first one active
  document.querySelectorAll(".radio-option-label").forEach(function(lbl, i) {
    const rb = lbl.querySelector("input[type=radio]");
    lbl.style.borderColor = (rb && rb.checked) ? "var(--accent)" : "var(--border2)";
    lbl.style.background  = (rb && rb.checked) ? "rgba(67,97,238,.06)" : "";
  });
  openModal("branch-add-modal");
}

function checkAllAddCats(state) {
  document.querySelectorAll("#cat-picker-add input[type=checkbox]").forEach(function(cb) {
    cb.checked = state;
  });
}

function toggleCatPicker(radio) {
  document.getElementById("cat-picker-add").style.display =
    radio.value === "specific" ? "block" : "none";
  // Update visual style of all radio option labels
  document.querySelectorAll(".radio-option-label").forEach(function(lbl) {
    const rb = lbl.querySelector("input[type=radio]");
    lbl.style.borderColor = (rb && rb.checked) ? "var(--accent)" : "var(--border2)";
    lbl.style.background  = (rb && rb.checked) ? "rgba(67,97,238,.06)" : "";
  });
}

// ── Edit branch modal ──
function editBranch(b) {
  document.getElementById("edit-branch-id").value      = b.id;
  document.getElementById("edit-branch-name").value    = b.name;
  document.getElementById("edit-branch-manager").value = b.manager_name || "";
  document.getElementById("edit-branch-phone").value   = b.phone || "";
  document.getElementById("edit-branch-address").value = b.address || "";
  openModal("branch-edit-modal");
}

// ── Category assignment modal ──
function openCatModal(branchId, branchName, assignedIds) {
  document.getElementById("cat-modal-branch-id").value   = branchId;
  document.getElementById("cat-modal-branch-name").textContent = branchName;

  // Set checkboxes
  document.querySelectorAll(".cat-cb").forEach(function(cb) {
    cb.checked = assignedIds.includes(parseInt(cb.value));
    updateCatStyle(cb.closest("label"));
  });
  updateCatCount();
  openModal("cat-assign-modal");
}

function checkAllCats(state) {
  document.querySelectorAll(".cat-cb").forEach(function(cb) {
    cb.checked = state;
    updateCatStyle(cb.closest("label"));
  });
  updateCatCount();
}

function updateCatCount() {
  const count = document.querySelectorAll(".cat-cb:checked").length;
  document.getElementById("cat-selected-count").textContent = count;
}

function updateCatStyle(label) {
  const cb = label.querySelector(".cat-cb");
  if (cb && cb.checked) {
    label.style.borderColor = "var(--green)";
    label.style.background  = "rgba(45,204,122,.08)";
  } else {
    label.style.borderColor = "var(--border)";
    label.style.background  = "var(--bg3)";
  }
}
</script>';
require __DIR__ . '/includes/footer.php';
