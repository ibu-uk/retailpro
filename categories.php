<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'categories';
$page_title   = __('category_management');
$db = db();

// ── HANDLE ACTIONS ──
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CATEGORY ACTIONS ──
    if ($action === 'add_cat') {
        $db->prepare("INSERT INTO categories (name,name_ar,emoji,parent_id,description) VALUES (?,?,?,?,?)")->execute([
            trim($_POST['name']), trim($_POST['name_ar'] ?? ''), trim($_POST['emoji'] ?: '📦'), $_POST['parent_id'] ?: null, trim($_POST['description'])
        ]);
        header('Location: ' . BASE . '/categories.php?success=' . urlencode('Category added'));
        exit;
    }
    if ($action === 'edit_cat') {
        $db->prepare("UPDATE categories SET name=?,name_ar=?, emoji=?, parent_id=?, description=? WHERE id=?")->execute([
            trim($_POST['name']), trim($_POST['name_ar'] ?? ''), trim($_POST['emoji'] ?: '📦'), $_POST['parent_id'] ?: null, trim($_POST['description']), (int)$_POST['id']
        ]);
        header('Location: ' . BASE . '/categories.php?success=' . urlencode('Category updated'));
        exit;
    }
    if ($action === 'delete_cat') {
        $cid = (int)$_POST['id'];
        // Check if products use this category
        $count = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
        $count->execute([$cid]);
        if ($count->fetchColumn() > 0) {
            header('Location: ' . BASE . '/categories.php?error=' . urlencode('Cannot delete: category has products'));
            exit;
        }
        // Also reassign sub-categories to no parent
        $db->prepare("UPDATE categories SET parent_id=NULL WHERE parent_id=?")->execute([$cid]);
        $db->prepare("DELETE FROM categories WHERE id=?")->execute([$cid]);
        header('Location: ' . BASE . '/categories.php?success=' . urlencode('Category deleted'));
        exit;
    }
    if ($action === 'toggle_cat') {
        $db->prepare("UPDATE categories SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_POST['id']]);
        header('Location: ' . BASE . '/categories.php?success=' . urlencode('Status updated'));
        exit;
    }

    // ── UNIT ACTIONS ──
    if ($action === 'add_unit') {
        $db->prepare("INSERT INTO units (name,abbreviation) VALUES (?,?)")->execute([
            trim($_POST['unit_name']), trim($_POST['abbreviation'])
        ]);
        header('Location: ' . BASE . '/categories.php?tab=units&success=' . urlencode('Unit added'));
        exit;
    }
    if ($action === 'edit_unit') {
        $db->prepare("UPDATE units SET name=?, abbreviation=? WHERE id=?")->execute([
            trim($_POST['unit_name']), trim($_POST['abbreviation']), (int)$_POST['id']
        ]);
        header('Location: ' . BASE . '/categories.php?tab=units&success=' . urlencode('Unit updated'));
        exit;
    }
    if ($action === 'delete_unit') {
        $uid = (int)$_POST['id'];
        $abbr = $db->prepare("SELECT abbreviation FROM units WHERE id=?");
        $abbr->execute([$uid]);
        $abbr_val = $abbr->fetchColumn();
        $count = $db->prepare("SELECT COUNT(*) FROM products WHERE unit_type=?");
        $count->execute([$abbr_val]);
        if ($count->fetchColumn() > 0) {
            header('Location: ' . BASE . '/categories.php?tab=units&error=' . urlencode('Cannot delete: unit is used by products'));
            exit;
        }
        $db->prepare("DELETE FROM units WHERE id=?")->execute([$uid]);
        header('Location: ' . BASE . '/categories.php?tab=units&success=' . urlencode('Unit deleted'));
        exit;
    }
    if ($action === 'toggle_unit') {
        $db->prepare("UPDATE units SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_POST['id']]);
        header('Location: ' . BASE . '/categories.php?tab=units&success=' . urlencode('Status updated'));
        exit;
    }
}

// ── LOAD DATA ──
$all_cats = $db->query("SELECT c.*, pc.name as parent_name, pc.emoji as parent_emoji,
    (SELECT COUNT(*) FROM products WHERE category_id=c.id) as product_count,
    (SELECT COUNT(*) FROM categories WHERE parent_id=c.id) as sub_count
    FROM categories c
    LEFT JOIN categories pc ON pc.id = c.parent_id
    ORDER BY COALESCE(c.parent_id,c.id), c.parent_id IS NOT NULL, c.name")->fetchAll();

$parent_cats = $db->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();
$units = $db->query("SELECT u.*, (SELECT COUNT(*) FROM products WHERE unit_type=u.abbreviation) as product_count FROM units u ORDER BY u.name")->fetchAll();

$active_tab = $_GET['tab'] ?? 'categories';

require __DIR__ . '/includes/header.php';
?>

<div class="tabs">
  <div class="tab <?= $active_tab==='categories'?'active':'' ?>" onclick="switchTab('tab-categories',this)">📂 <?= __('nav_categories') ?></div>
  <div class="tab <?= $active_tab==='units'?'active':'' ?>" onclick="switchTab('tab-units',this)">📏 <?= __('units') ?></div>
</div>

<!-- ══════════════════════════════════════ CATEGORIES TAB ══════════════════════════════════════ -->
<div id="tab-categories" style="<?= $active_tab==='units'?'display:none':'' ?>">

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="stat-card blue"><div class="stat-label"><?= __('total_categories') ?></div><div class="stat-value text-blue"><?= count($all_cats) ?></div></div>
  <div class="stat-card green"><div class="stat-label"><?= __('parent_categories') ?></div><div class="stat-value text-green"><?= count($parent_cats) ?></div></div>
  <div class="stat-card purple"><div class="stat-label"><?= __('sub_categories') ?></div><div class="stat-value text-accent"><?= count($all_cats) - count($parent_cats) ?></div></div>
  <div class="stat-card amber"><div class="stat-label"><?= __('active') ?></div><div class="stat-value text-amber"><?= count(array_filter($all_cats, fn($c) => $c['is_active'])) ?></div></div>
</div>

<div class="card">
  <div class="card-title">
    <span>📂 <?= __('all_categories') ?></span>
    <button class="btn btn-primary btn-sm" onclick="openModal('cat-modal');document.getElementById('cat-form').reset();document.getElementById('cat-action').value='add_cat';document.getElementById('cat-id').value='';document.getElementById('cat-modal-title').textContent='<?= __('add_category') ?>'">+ <?= __('add_category') ?></button>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th><?= __('category') ?></th><th><?= __('parent') ?></th><th><?= __('total_products') ?></th><th><?= __('sub_categories') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr></thead>
      <tbody>
        <?php foreach ($all_cats as $c): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px;<?= $c['parent_id'] ? 'padding-left:24px' : '' ?>">
              <?php if ($c['parent_id']): ?><span style="color:var(--text3);font-size:11px">↳</span><?php endif; ?>
              <span style="font-size:18px"><?= $c['emoji'] ?></span>
              <div>
                <div style="font-weight:500"><?= htmlspecialchars($c['name']) ?><?php if ($c['name_ar']) echo '<br><span style="font-size:11px;color:var(--text3)">' . htmlspecialchars($c['name_ar']) . '</span>'; ?></div>
                <?php if ($c['description']): ?><div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($c['description']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td><?= $c['parent_name'] ? $c['parent_emoji'] . ' ' . htmlspecialchars($c['parent_name']) : '<span class="text-muted">—</span>' ?></td>
          <td style="font-weight:600"><?= $c['product_count'] ?></td>
          <td><?= $c['sub_count'] ?: '<span class="text-muted">—</span>' ?></td>
          <td>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_cat"><input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="badge <?= $c['is_active'] ? 'badge-green' : 'badge-gray' ?>" style="cursor:pointer;border:none"><span class="dot"></span><?= $c['is_active'] ? __('active') : __('inactive') ?></button>
            </form>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn btn-ghost btn-sm" onclick="editCat(<?= htmlspecialchars(json_encode($c)) ?>)">✏️ <?= __('edit') ?></button>
              <?php if ($c['product_count'] == 0): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($c['name'])) ?>?')">
                <input type="hidden" name="action" value="delete_cat"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">🗑️</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($all_cats)): ?>
        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text3)"><?= __('no_data') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- ══════════════════════════════════════ UNITS TAB ══════════════════════════════════════ -->
<div id="tab-units" style="<?= $active_tab!=='units'?'display:none':'' ?>">

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
  <div class="stat-card blue"><div class="stat-label"><?= __('total_units') ?></div><div class="stat-value text-blue"><?= count($units) ?></div></div>
  <div class="stat-card green"><div class="stat-label"><?= __('active') ?></div><div class="stat-value text-green"><?= count(array_filter($units, fn($u) => $u['is_active'])) ?></div></div>
  <div class="stat-card purple"><div class="stat-label"><?= __('in_use') ?></div><div class="stat-value text-accent"><?= count(array_filter($units, fn($u) => $u['product_count'] > 0)) ?></div></div>
</div>

<div class="card">
  <div class="card-title">
    <span>📏 <?= __('units') ?></span>
    <button class="btn btn-primary btn-sm" onclick="openModal('unit-modal');document.getElementById('unit-form').reset();document.getElementById('unit-action').value='add_unit';document.getElementById('unit-id').value='';document.getElementById('unit-modal-title').textContent='<?= __('add_unit') ?>'">+ <?= __('add_unit') ?></button>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th><?= __('unit_name') ?></th><th><?= __('abbreviation') ?></th><th><?= __('total_products') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr></thead>
      <tbody>
        <?php foreach ($units as $u): ?>
        <tr>
          <td style="font-weight:500"><?= htmlspecialchars($u['name']) ?></td>
          <td><span class="badge badge-blue"><?= htmlspecialchars($u['abbreviation']) ?></span></td>
          <td style="font-weight:600"><?= $u['product_count'] ?></td>
          <td>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_unit"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-gray' ?>" style="cursor:pointer;border:none"><span class="dot"></span><?= $u['is_active'] ? __('active') : __('inactive') ?></button>
            </form>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn btn-ghost btn-sm" onclick="editUnit(<?= htmlspecialchars(json_encode($u)) ?>)">✏️ <?= __('edit') ?></button>
              <?php if ($u['product_count'] == 0): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
                <input type="hidden" name="action" value="delete_unit"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">🗑️</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- ══════════════════════════════════════ CATEGORY MODAL ══════════════════════════════════════ -->
<div class="modal-backdrop" id="cat-modal">
  <div class="modal" style="width:480px">
    <div class="modal-header">
      <div class="modal-title" id="cat-modal-title"><?= __('add_category') ?></div>
      <button class="modal-close" onclick="closeModal('cat-modal')">✕</button>
    </div>
    <form method="POST" id="cat-form">
      <input type="hidden" name="action" value="add_cat" id="cat-action">
      <input type="hidden" name="id" value="" id="cat-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group" style="flex:1"><label class="form-label"><?= __('category_name') ?> *</label><input class="form-input" name="name" id="cat-name" required placeholder="e.g. Bags"></div>
          <div class="form-group" style="width:80px"><label class="form-label"><?= __('emoji') ?></label><input class="form-input" name="emoji" id="cat-emoji" placeholder="📦" style="text-align:center;font-size:18px" maxlength="4"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('category_name_ar') ?></label><input class="form-input" name="name_ar" id="cat-name-ar" placeholder="مثال: حقائب" style="direction:rtl"></div>
        <div class="form-group">
          <label class="form-label"><?= __('parent_category') ?></label>
          <select class="form-select" name="parent_id" id="cat-parent">
            <option value="">— None (Top-level) —</option>
            <?php foreach ($parent_cats as $pc): ?>
            <option value="<?= $pc['id'] ?>"><?= $pc['emoji'] ?> <?= htmlspecialchars($pc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label"><?= __('description') ?></label><input class="form-input" name="description" id="cat-desc"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('cat-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════ UNIT MODAL ══════════════════════════════════════ -->
<div class="modal-backdrop" id="unit-modal">
  <div class="modal" style="width:400px">
    <div class="modal-header">
      <div class="modal-title" id="unit-modal-title"><?= __('add_unit') ?></div>
      <button class="modal-close" onclick="closeModal('unit-modal')">✕</button>
    </div>
    <form method="POST" id="unit-form">
      <input type="hidden" name="action" value="add_unit" id="unit-action">
      <input type="hidden" name="id" value="" id="unit-id">
      <div class="modal-body">
        <div class="form-group"><label class="form-label"><?= __('unit_name') ?> *</label><input class="form-input" name="unit_name" id="unit-name" required placeholder="e.g. Carton"></div>
        <div class="form-group"><label class="form-label"><?= __('abbreviation') ?> *</label><input class="form-input" name="abbreviation" id="unit-abbr" required placeholder="e.g. ctn" style="font-family:var(--mono)"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('unit-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_js = '<script>
function switchTab(id, el) {
  ["tab-categories","tab-units"].forEach(t => document.getElementById(t).style.display="none");
  document.getElementById(id).style.display="block";
  document.querySelectorAll(".tab").forEach(t=>t.classList.remove("active"));
  el.classList.add("active");
}

function editCat(c) {
  document.getElementById("cat-action").value = "edit_cat";
  document.getElementById("cat-id").value = c.id;
  document.getElementById("cat-name").value = c.name;
  document.getElementById("cat-name-ar").value = c.name_ar || "";
  document.getElementById("cat-emoji").value = c.emoji;
  document.getElementById("cat-parent").value = c.parent_id || "";
  document.getElementById("cat-desc").value = c.description || "";
  document.getElementById("cat-modal-title").textContent = "' . __('edit_category') . '";
  openModal("cat-modal");
}

function editUnit(u) {
  document.getElementById("unit-action").value = "edit_unit";
  document.getElementById("unit-id").value = u.id;
  document.getElementById("unit-name").value = u.name;
  document.getElementById("unit-abbr").value = u.abbreviation;
  document.getElementById("unit-modal-title").textContent = "' . __('edit_unit') . '";
  openModal("unit-modal");
}
</script>';
require __DIR__ . '/includes/footer.php';
?>
