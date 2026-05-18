<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'users';
$page_title   = __('user_management');
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO users (name,email,password,role,branch_id) VALUES (?,?,?,?,?)")->execute([
        trim($_POST['name']), trim($_POST['email']), $hash,
        $_POST['role'], $_POST['branch_id'] ?: null
    ]);
    header('Location: ' . BASE . '/users.php?success=' . urlencode('User created'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $uid = (int)$_POST['user_id'];
    $db->prepare("UPDATE users SET name=?, email=?, role=?, branch_id=? WHERE id=?")->execute([
        trim($_POST['name']), trim($_POST['email']),
        $_POST['role'], $_POST['branch_id'] ?: null, $uid
    ]);
    if (!empty($_POST['password'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
    }
    header('Location: ' . BASE . '/users.php?success=' . urlencode('User updated'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $uid = (int)$_POST['user_id'];
    if ($uid != 1) {
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
    }
    header('Location: ' . BASE . '/users.php?success=' . urlencode('User deleted'));
    exit;
}

if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE id != 1 AND id=?")->execute([(int)$_GET['toggle']]);
    header('Location: ' . BASE . '/users.php');
    exit;
}

$users = $db->query("
    SELECT u.*, b.name as branch_name
    FROM users u LEFT JOIN branches b ON b.id = u.branch_id
    ORDER BY u.id
")->fetchAll();
$branches = $db->query("SELECT id, name FROM branches WHERE is_active=1")->fetchAll();

$role_badges = ['super_admin'=>'badge-purple','manager'=>'badge-blue','cashier'=>'badge-green','inventory'=>'badge-amber'];
$role_labels = ['super_admin'=>'Super Admin','manager'=>'Branch Manager','cashier'=>'Cashier','inventory'=>'Inventory'];

require __DIR__ . '/includes/header.php';
?>

<div style="display:flex;gap:8px;justify-content:flex-end;margin-bottom:16px">
  <button class="btn btn-ghost" onclick="showToast('Roles','Role configuration opened.','success')">⚙️ Manage Roles</button>
  <button class="btn btn-primary" onclick="openAddUser()">+ <?= __('add') ?></button>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th><?= __('user') ?></th><th><?= __('role') ?></th><th><?= __('nav_branches') ?></th><th><?= __('last_login') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="user-avatar" style="width:28px;height:28px;font-size:11px"><?= strtoupper(substr($u['name'],0,2)) ?></div>
              <div>
                <div style="font-weight:500"><?= htmlspecialchars($u['name']) ?></div>
                <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge <?= $role_badges[$u['role']] ?? 'badge-gray' ?>"><?= $role_labels[$u['role']] ?? $u['role'] ?></span></td>
          <td><?= htmlspecialchars($u['branch_name'] ?? __('all')) ?></td>
          <td style="font-size:12px;color:var(--text3)"><?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : '-' ?></td>
          <td>
            <span class="badge <?= $u['is_active']?'badge-green':'badge-gray' ?>">
              <span class="dot"></span><?= $u['is_active'] ? __('active') : __('inactive') ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn btn-ghost btn-sm" onclick='editUser(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️ <?= __('edit') ?></button>
              <?php if ($u['id'] != 1): ?>
              <a href="<?= BASE ?>/users.php?toggle=<?= $u['id'] ?>" class="btn btn-ghost btn-sm"><?= $u['is_active'] ? __('disable') : __('enable') ?></a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                <input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
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

<!-- USER MODAL (Add/Edit) -->
<div class="modal-backdrop" id="user-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="user-modal-title"><?= __('add') ?> <?= __('user') ?></div>
      <button class="modal-close" onclick="closeModal('user-modal')">✕</button>
    </div>
    <form method="POST" id="user-form">
      <input type="hidden" name="action" value="add" id="user-action">
      <input type="hidden" name="user_id" value="" id="user-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('full_name') ?> *</label><input class="form-input" name="name" id="user-name" required placeholder="Ahmed Karim"></div>
          <div class="form-group"><label class="form-label"><?= __('email') ?> *</label><input class="form-input" name="email" id="user-email" type="email" required placeholder="user@retailpro.kw"></div>
        </div>
        <div class="form-group"><label class="form-label" id="pw-label"><?= __('password') ?> *</label><input class="form-input" name="password" id="user-pw" type="password" minlength="6" placeholder="Minimum 6 characters"></div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label"><?= __('role') ?> *</label>
            <select class="form-select" name="role" id="user-role" required>
              <option value="cashier">Cashier</option>
              <option value="inventory">Inventory</option>
              <option value="manager">Branch Manager</option>
              <option value="super_admin">Super Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label"><?= __('nav_branches') ?></label>
            <select class="form-select" name="branch_id" id="user-branch">
              <option value=""><?= __('all') ?></option>
              <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('user-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary" id="user-submit-btn"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_js = '<script>
function editUser(u) {
  document.getElementById("user-action").value = "edit";
  document.getElementById("user-id").value = u.id;
  document.getElementById("user-name").value = u.name;
  document.getElementById("user-email").value = u.email;
  document.getElementById("user-role").value = u.role;
  document.getElementById("user-branch").value = u.branch_id || "";
  document.getElementById("user-pw").value = "";
  document.getElementById("user-pw").removeAttribute("required");
  document.getElementById("pw-label").textContent = "' . __('password') . '";
  document.getElementById("user-modal-title").textContent = "' . __('edit') . ' ' . __('user') . '";
  document.getElementById("user-submit-btn").textContent = "' . __('save') . '";
  openModal("user-modal");
}
function openAddUser() {
  document.getElementById("user-form").reset();
  document.getElementById("user-action").value = "add";
  document.getElementById("user-id").value = "";
  document.getElementById("user-pw").setAttribute("required","required");
  document.getElementById("pw-label").textContent = "' . __('password') . ' *";
  document.getElementById("user-modal-title").textContent = "' . __('add') . ' ' . __('user') . '";
  document.getElementById("user-submit-btn").textContent = "' . __('save') . '";
  openModal("user-modal");
}
</script>';
require __DIR__ . '/includes/footer.php'; ?>
