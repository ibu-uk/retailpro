<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'settings';
$page_title   = __('settings');
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $filename = 'company_logo.' . $ext;
        $target = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
                ->execute(['company_logo', BASE . '/uploads/' . $filename, BASE . '/uploads/' . $filename]);
        }
    }
    
    // Handle logo delete
    if (isset($_POST['delete_logo']) && $_POST['delete_logo'] === '1') {
        $logo_path = get_setting('company_logo');
        if ($logo_path) {
            $file_path = __DIR__ . str_replace(BASE, '', $logo_path);
            if (file_exists($file_path)) unlink($file_path);
            $db->prepare("DELETE FROM settings WHERE setting_key='company_logo'")->execute();
        }
    }
    
    // Handle other settings
    $fields = ['company_name','company_name_ar','address','phone','currency',
               'invoice_prefix','invoice_footer','show_logo_in_invoice','printer_format','vat_number'];
    foreach ($fields as $f) {
        // Checkboxes: save '0' when unchecked (not posted)
        if ($f === 'show_logo_in_invoice') {
            $val = isset($_POST[$f]) ? '1' : '0';
        } elseif (isset($_POST[$f])) {
            $val = trim($_POST[$f]);
        } else {
            continue;
        }
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$f, $val, $val]);
    }
    header('Location: ' . BASE . '/settings.php?success=' . urlencode('Settings saved successfully'));
    exit;
}

// Load all settings
$settings = [];
$rows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];

require __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-error" style="margin-bottom:16px">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="tabs">
  <div class="tab active" onclick="switchTab('tab-company',this)"><?= __('company') ?></div>
  <div class="tab" onclick="switchTab('tab-invoice',this)"><?= __('invoice') ?></div>
  <div class="tab" onclick="switchTab('tab-backup',this)"><?= __('backup') ?></div>
</div>

<form method="POST" enctype="multipart/form-data">
<div class="grid-2">
  <!-- COMPANY -->
  <div id="tab-company" class="card">
    <div class="card-title"><span>🏢 <?= __('company_settings') ?></span></div>
    <div class="form-group">
      <label class="form-label"><?= __('company_logo') ?></label>
      <?php if (!empty($settings['company_logo'])): ?>
      <div style="margin-bottom:12px">
        <img src="<?= htmlspecialchars($settings['company_logo']) ?>" alt="Company Logo" style="max-height:80px;max-width:200px;border:1px solid var(--border2);border-radius:var(--r);padding:8px;background:var(--bg2)">
        <div style="margin-top:8px">
          <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text3)">
            <input type="checkbox" name="delete_logo" value="1">
            Delete current logo
          </label>
        </div>
      </div>
      <?php endif; ?>
      <input type="file" name="logo" accept="image/*" class="form-input">
      <div style="font-size:11px;color:var(--text3);margin-top:4px">Upload PNG, JPG, or GIF (max 2MB)</div>
    </div>
    <div class="form-group"><label class="form-label"><?= __('company_name') ?></label><input class="form-input" name="company_name" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label"><?= __('company_name') ?> (عربي)</label><input class="form-input" name="company_name_ar" value="<?= htmlspecialchars($settings['company_name_ar'] ?? '') ?>" dir="rtl" placeholder="اسم الشركة بالعربي"></div>
    <div class="form-group"><label class="form-label"><?= __('address') ?></label><textarea class="form-textarea" name="address"><?= htmlspecialchars($settings['address'] ?? '') ?></textarea></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label"><?= __('phone') ?></label><input class="form-input" name="phone" value="<?= htmlspecialchars($settings['phone'] ?? '') ?>"></div>
      <div class="form-group"><label class="form-label"><?= __('currency') ?></label>
        <select class="form-select" name="currency">
          <option value="KWD" <?= ($settings['currency']??'')==='KWD'?'selected':'' ?>>KWD — Kuwaiti Dinar</option>
          <option value="USD" <?= ($settings['currency']??'')==='USD'?'selected':'' ?>>USD — US Dollar</option>
          <option value="AED" <?= ($settings['currency']??'')==='AED'?'selected':'' ?>>AED — UAE Dirham</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
  </div>

  <!-- INVOICE -->
  <div id="tab-invoice" style="display:none" class="card">
    <div class="card-title"><span>⚙️ <?= __('invoice_settings') ?></span></div>
    <div class="form-group"><label class="form-label"><?= __('invoice_prefix') ?></label><input class="form-input" name="invoice_prefix" value="<?= htmlspecialchars($settings['invoice_prefix'] ?? 'INV-') ?>"></div>
    <div class="form-group"><label class="form-label"><?= __('invoice_footer') ?></label><textarea class="form-textarea" name="invoice_footer"><?= htmlspecialchars($settings['invoice_footer'] ?? '') ?></textarea></div>
    <div class="form-group">
      <label class="form-label"><?= __('show_logo_invoice') ?></label>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--r)">
        <label class="toggle-switch" style="flex-shrink:0">
          <input type="checkbox" name="show_logo_in_invoice" value="1" id="toggle-logo" <?= ($settings['show_logo_in_invoice'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span class="toggle-slider"></span>
        </label>
        <div>
          <div style="font-size:13px;font-weight:500;color:var(--text)" id="toggle-logo-label"><?= ($settings['show_logo_in_invoice'] ?? '0') === '1' ? __('enabled') : __('disabled') ?></div>
          <div style="font-size:11px;color:var(--text3);margin-top:2px">Show your company logo at the top of every printed invoice</div>
        </div>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Default Printer Format</label>
      <select class="form-select" name="printer_format">
        <option value="a4" <?= ($settings['printer_format'] ?? 'a4') === 'a4' ? 'selected' : '' ?>>A4 (Standard)</option>
        <option value="thermal" <?= ($settings['printer_format'] ?? 'a4') === 'thermal' ? 'selected' : '' ?>>Thermal (80mm)</option>
      </select>
      <div style="font-size:11px;color:var(--text3);margin-top:4px">Select default format for invoice printing</div>
    </div>
    <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
  </div>


  <!-- BACKUP -->
  <div id="tab-backup" style="display:none" class="card">
    <div class="card-title"><span>🗃️ <?= __('database_backup') ?></span></div>
    <p style="font-size:13px;color:var(--text2);margin-bottom:16px">Create a full backup of your RetailPro database. Run this regularly to prevent data loss.</p>
    <div style="background:var(--bg3);border-radius:var(--r);padding:16px;margin-bottom:16px;font-family:var(--mono);font-size:12px;color:var(--text3)">
      mysqldump -u [user] -p retailpro &gt; retailpro_<?= date('Ymd_His') ?>.sql
    </div>
    <div style="display:flex;gap:8px">
      <button type="button" class="btn btn-primary" onclick="showToast('Backup','Database backup initiated. Check server logs.','success')">🗃️ Create Backup Now</button>
      <button type="button" class="btn btn-ghost" onclick="showToast('Export','Exporting settings as JSON...','success')">📤 Export Settings</button>
    </div>
    <div style="margin-top:20px;padding:14px;background:var(--bg3);border-radius:var(--r);font-size:12px">
      <div style="font-weight:600;margin-bottom:8px;color:var(--text2)">App Info</div>
      <div style="color:var(--text3)">Version: <span style="color:var(--accent2)"><?= APP_VERSION ?></span></div>
      <div style="color:var(--text3)">PHP: <span style="color:var(--accent2)"><?= phpversion() ?></span></div>
      <div style="color:var(--text3)">Database: <span style="color:var(--accent2)">MySQL / <?= DB_NAME ?></span></div>
      <div style="color:var(--text3)">Timezone: <span style="color:var(--accent2)"><?= date_default_timezone_get() ?></span></div>
    </div>
  </div>
</div>
</form>

<?php
ob_start(); ?>
<style>
.toggle-switch{position:relative;display:inline-block;width:44px;height:24px}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;cursor:pointer;inset:0;background:var(--bg5);border-radius:24px;transition:.25s}
.toggle-slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.25s}
.toggle-switch input:checked + .toggle-slider{background:var(--green)}
.toggle-switch input:checked + .toggle-slider:before{transform:translateX(20px)}
</style>
<script>
function switchTab(id, el) {
  ["tab-company","tab-invoice","tab-backup"].forEach(t => document.getElementById(t).style.display="none");
  document.getElementById(id).style.display="block";
  document.querySelectorAll(".tab").forEach(t=>t.classList.remove("active"));
  el.classList.add("active");
}
const logoToggle = document.getElementById("toggle-logo");
const logoLabel  = document.getElementById("toggle-logo-label");
if (logoToggle && logoLabel) {
  logoToggle.addEventListener("change", function() {
    logoLabel.textContent = this.checked ? "Enabled" : "Disabled";
    logoLabel.style.color = this.checked ? "var(--green)" : "var(--text)";
  });
  if (logoToggle.checked) logoLabel.style.color = "var(--green)";
}
const logoInput = document.querySelector('input[name="logo"]');
if (logoInput) {
  logoInput.addEventListener("change", function() {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
      showToast("Error", "File too large — max 2MB", "error");
      this.value = "";
      return;
    }
    const reader = new FileReader();
    reader.onload = e => {
      let preview = document.getElementById("logo-preview-new");
      if (!preview) {
        preview = document.createElement("img");
        preview.id = "logo-preview-new";
        preview.style.cssText = "max-height:70px;max-width:180px;border:1px solid var(--border2);border-radius:var(--r);padding:6px;background:var(--bg2);margin-top:8px;display:block";
        this.after(preview);
      }
      preview.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
}
</script>
<?php
$extra_js = ob_get_clean();
require __DIR__ . '/includes/footer.php'; ?>
