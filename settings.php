<?php
require_once __DIR__ . '/includes/config.php';
require_login();
require_role('super_admin');
$current_page = 'settings';
$page_title   = __('settings');
$db = db();

// ── Country profiles (editable defaults) ─────────────────────────────────────
$country_profiles = [
    'KW' => ['flag'=>'🇰🇼','name'=>'Kuwait',       'currency'=>'KWD','decimals'=>3,'tax_type'=>'none', 'tax_rate'=>0,  'tax_label'=>'',          'tax_inclusive'=>0,'vat_req'=>false],
    'AE' => ['flag'=>'🇦🇪','name'=>'UAE',           'currency'=>'AED','decimals'=>2,'tax_type'=>'vat',  'tax_rate'=>5,  'tax_label'=>'VAT',        'tax_inclusive'=>0,'vat_req'=>true],
    'SA' => ['flag'=>'🇸🇦','name'=>'Saudi Arabia',  'currency'=>'SAR','decimals'=>2,'tax_type'=>'vat',  'tax_rate'=>15, 'tax_label'=>'VAT',        'tax_inclusive'=>0,'vat_req'=>true],
    'BH' => ['flag'=>'🇧🇭','name'=>'Bahrain',       'currency'=>'BHD','decimals'=>3,'tax_type'=>'vat',  'tax_rate'=>10, 'tax_label'=>'VAT',        'tax_inclusive'=>0,'vat_req'=>true],
    'QA' => ['flag'=>'🇶🇦','name'=>'Qatar',         'currency'=>'QAR','decimals'=>2,'tax_type'=>'none', 'tax_rate'=>0,  'tax_label'=>'',          'tax_inclusive'=>0,'vat_req'=>false],
    'OM' => ['flag'=>'🇴🇲','name'=>'Oman',          'currency'=>'OMR','decimals'=>3,'tax_type'=>'vat',  'tax_rate'=>5,  'tax_label'=>'VAT',        'tax_inclusive'=>0,'vat_req'=>true],
    'GB' => ['flag'=>'🇬🇧','name'=>'United Kingdom','currency'=>'GBP','decimals'=>2,'tax_type'=>'vat',  'tax_rate'=>20, 'tax_label'=>'VAT',        'tax_inclusive'=>1,'vat_req'=>true],
    'US' => ['flag'=>'🇺🇸','name'=>'USA',           'currency'=>'USD','decimals'=>2,'tax_type'=>'sales_tax','tax_rate'=>8.5,'tax_label'=>'Sales Tax','tax_inclusive'=>0,'vat_req'=>false],
    'EU' => ['flag'=>'🇪🇺','name'=>'Europe (EUR)',  'currency'=>'EUR','decimals'=>2,'tax_type'=>'vat',  'tax_rate'=>20, 'tax_label'=>'VAT',        'tax_inclusive'=>1,'vat_req'=>true],
    'IN' => ['flag'=>'🇮🇳','name'=>'India',         'currency'=>'INR','decimals'=>2,'tax_type'=>'vat',  'tax_rate'=>18, 'tax_label'=>'GST',        'tax_inclusive'=>0,'vat_req'=>true],
];

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Apply country profile (load defaults into form) ---
    if ($action === 'load_profile') {
        $code = $_POST['country_code'] ?? 'KW';
        $p    = $country_profiles[$code] ?? $country_profiles['KW'];
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['country_code',       $code,                         $code]);
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['currency',           $p['currency'],                $p['currency']]);
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['currency_decimals',  $p['decimals'],                $p['decimals']]);
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['tax_type',           $p['tax_type'],                $p['tax_type']]);
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['tax_rate',           $p['tax_rate'],                $p['tax_rate']]);
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['tax_label',          $p['tax_label'],               $p['tax_label']]);
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['tax_inclusive',      $p['tax_inclusive'],           $p['tax_inclusive']]);
        audit_log('load_country_profile', 'settings', 0, null, ['country_code' => $code, 'profile' => $p['name']]);
        header('Location: ' . BASE . '/settings.php?success=' . urlencode('Country profile loaded — review and save your custom values below') . '&tab=tax');
        exit;
    }

    // --- Save company settings ---
    if ($action === 'save_company') {
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $target = $upload_dir . 'company_logo.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
                $val = BASE . '/uploads/company_logo.' . $ext;
                $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['company_logo', $val, $val]);
            }
        }
        if (isset($_POST['delete_logo']) && $_POST['delete_logo'] === '1') {
            $lp = get_setting('company_logo');
            if ($lp) { $fp = __DIR__ . str_replace(BASE,'',$lp); if(file_exists($fp)) unlink($fp); }
            $db->prepare("DELETE FROM settings WHERE setting_key='company_logo'")->execute();
        }
        $new_values = [];
        foreach (['company_name','company_name_ar','address','phone'] as $f) {
            if (isset($_POST[$f])) { $v=trim($_POST[$f]); $new_values[$f] = $v; $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$f,$v,$v]); }
        }
        audit_log('save_company_settings', 'settings', 0, null, $new_values);
        header('Location: ' . BASE . '/settings.php?success=' . urlencode('Company settings saved') . '&tab=company');
        exit;
    }

    // --- Save invoice settings ---
    if ($action === 'save_invoice') {
        foreach (['invoice_prefix','invoice_footer','printer_format','refund_period_days'] as $f) {
            if (isset($_POST[$f])) { $v=trim($_POST[$f]); $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$f,$v,$v]); }
        }
        $sli = isset($_POST['show_logo_in_invoice']) ? '1' : '0';
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['show_logo_in_invoice',$sli,$sli]);
        audit_log('save_invoice_settings', 'settings', 0, null, [
            'invoice_prefix' => trim($_POST['invoice_prefix'] ?? ''),
            'refund_period_days' => trim($_POST['refund_period_days'] ?? ''),
            'show_logo_in_invoice' => $sli
        ]);
        header('Location: ' . BASE . '/settings.php?success=' . urlencode('Invoice settings saved') . '&tab=invoice');
        exit;
    }

    // --- Save tax/currency settings ---
    if ($action === 'save_tax') {
        $tax_type  = $_POST['tax_type']  ?? 'none';
        $tax_rate  = (float)($_POST['tax_rate'] ?? 0);
        $tax_label = trim($_POST['tax_label'] ?? '');
        $inclusive = isset($_POST['tax_inclusive']) ? '1' : '0';
        $currency  = trim($_POST['currency'] ?? 'KWD');
        $decimals  = max(0, min(3, (int)($_POST['currency_decimals'] ?? 2)));
        $vat_num   = trim($_POST['vat_number'] ?? '');
        $cc        = trim($_POST['country_code'] ?? 'KW');

        if ($tax_type === 'none') { $tax_rate = 0; $tax_label = ''; $inclusive = '0'; }

        foreach ([
            'country_code'      => $cc,
            'currency'          => $currency,
            'currency_decimals' => $decimals,
            'tax_type'          => $tax_type,
            'tax_rate'          => $tax_rate,
            'tax_label'         => $tax_label,
            'tax_inclusive'     => $inclusive,
            'vat_number'        => $vat_num,
        ] as $k => $v) {
            $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k, $v, $v]);
        }
        audit_log('save_tax_settings', 'settings', 0, null, [
            'country_code' => $cc,
            'currency' => $currency,
            'currency_decimals' => $decimals,
            'tax_type' => $tax_type,
            'tax_rate' => $tax_rate,
            'tax_label' => $tax_label,
            'tax_inclusive' => $inclusive
        ]);
        header('Location: ' . BASE . '/settings.php?success=' . urlencode('Tax & currency settings saved') . '&tab=tax');
        exit;
    }
}

// ── Load all settings ─────────────────────────────────────────────────────────
$s = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $r) {
    $s[$r['setting_key']] = $r['setting_value'];
}

$active_tab = $_GET['tab'] ?? 'company';
$active_cc  = $s['country_code'] ?? 'KW';

require __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-error" style="margin-bottom:16px">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="tabs" id="main-tabs">
  <div class="tab <?= $active_tab==='company'?'active':'' ?>" onclick="switchTab('tab-company',this)">🏢 <?= __('company') ?></div>
  <div class="tab <?= $active_tab==='tax'?'active':'' ?>"     onclick="switchTab('tab-tax',this)">💱 Currency & Tax</div>
  <div class="tab <?= $active_tab==='invoice'?'active':'' ?>" onclick="switchTab('tab-invoice',this)">🧾 <?= __('invoice') ?></div>
  <div class="tab <?= $active_tab==='backup'?'active':'' ?>"  onclick="switchTab('tab-backup',this)">🗃️ <?= __('backup') ?></div>
</div>

<!-- ═══ COMPANY TAB ═══════════════════════════════════════════════════════════ -->
<div id="tab-company" class="card" style="<?= $active_tab!=='company'?'display:none':'' ?>">
  <div class="card-title"><span>🏢 <?= __('company_settings') ?></span></div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_company">
    <div class="form-group">
      <label class="form-label"><?= __('company_logo') ?></label>
      <?php if (!empty($s['company_logo'])): ?>
      <div style="margin-bottom:12px">
        <img src="<?= htmlspecialchars($s['company_logo']) ?>" alt="Logo" style="max-height:80px;max-width:200px;border:1px solid var(--border2);border-radius:var(--r);padding:8px;background:var(--bg2)">
        <div style="margin-top:8px"><label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text3)"><input type="checkbox" name="delete_logo" value="1"> Delete current logo</label></div>
      </div>
      <?php endif; ?>
      <input type="file" name="logo" accept="image/*" class="form-input">
      <div style="font-size:11px;color:var(--text3);margin-top:4px">PNG, JPG or GIF — max 2MB</div>
    </div>
    <div class="form-group"><label class="form-label"><?= __('company_name') ?></label><input class="form-input" name="company_name" value="<?= htmlspecialchars($s['company_name'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label"><?= __('company_name') ?> (عربي)</label><input class="form-input" name="company_name_ar" value="<?= htmlspecialchars($s['company_name_ar'] ?? '') ?>" dir="rtl" placeholder="اسم الشركة بالعربي"></div>
    <div class="form-group"><label class="form-label"><?= __('address') ?></label><textarea class="form-textarea" name="address"><?= htmlspecialchars($s['address'] ?? '') ?></textarea></div>
    <div class="form-group"><label class="form-label"><?= __('phone') ?></label><input class="form-input" name="phone" value="<?= htmlspecialchars($s['phone'] ?? '') ?>"></div>
    <button type="submit" class="btn btn-primary">💾 <?= __('save') ?></button>
  </form>
</div>

<!-- ═══ CURRENCY & TAX TAB ════════════════════════════════════════════════════ -->
<div id="tab-tax" style="<?= $active_tab!=='tax'?'display:none':'' ?>">

  <!-- Country profile picker -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-title"><span>🌍 Load Country Profile</span></div>
    <p style="font-size:13px;color:var(--text2);margin-bottom:14px">Select a country to auto-fill currency and tax defaults. You can then fine-tune any value below.</p>
    <form method="POST" id="profile-form">
      <input type="hidden" name="action" value="load_profile">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:16px">
        <?php foreach ($country_profiles as $code => $p): ?>
        <label class="country-tile <?= $active_cc===$code?'active':'' ?>" data-code="<?= $code ?>">
          <input type="radio" name="country_code" value="<?= $code ?>" <?= $active_cc===$code?'checked':'' ?> style="display:none">
          <div style="font-size:24px;margin-bottom:4px"><?= $p['flag'] ?></div>
          <div style="font-size:12px;font-weight:600;color:var(--text)"><?= $p['name'] ?></div>
          <div style="font-size:11px;color:var(--text3)"><?= $p['currency'] ?></div>
          <div style="margin-top:4px">
            <?php if ($p['tax_type']==='none'): ?>
              <span style="font-size:10px;background:var(--green-bg,rgba(34,197,94,.12));color:var(--green);padding:1px 6px;border-radius:99px">No tax</span>
            <?php else: ?>
              <span style="font-size:10px;background:rgba(245,166,35,.12);color:var(--amber);padding:1px 6px;border-radius:99px"><?= $p['tax_label'] ?> <?= $p['tax_rate'] ?>%</span>
            <?php endif; ?>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-primary" id="load-btn">⬇️ Load Profile Defaults</button>
      <span style="font-size:12px;color:var(--text3);margin-left:12px">This only loads defaults — nothing is saved until you click Save below.</span>
    </form>
  </div>

  <!-- Manual edit form -->
  <div class="card">
    <div class="card-title"><span>⚙️ Currency & Tax Configuration</span></div>
    <form method="POST" id="tax-form">
      <input type="hidden" name="action" value="save_tax">
      <input type="hidden" name="country_code" id="cc-hidden" value="<?= htmlspecialchars($active_cc) ?>">

      <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px">Currency</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Currency code</label>
          <input class="form-input" name="currency" id="f-currency" value="<?= htmlspecialchars($s['currency'] ?? 'KWD') ?>" maxlength="5" placeholder="KWD">
          <span style="font-size:11px;color:var(--text3)">3-letter ISO code, e.g. KWD, AED, USD</span>
        </div>
        <div class="form-group">
          <label class="form-label">Decimal places</label>
          <select class="form-select" name="currency_decimals" id="f-decimals">
            <?php foreach ([0,1,2,3] as $d): ?>
            <option value="<?= $d ?>" <?= ($s['currency_decimals']??'3')==$d?'selected':'' ?>><?= $d ?> decimals<?= $d===3?' (KWD, BHD, OMR)':($d===2?' (most currencies)':'') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="border-top:1px solid var(--border2);margin:16px 0 14px"></div>
      <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px">Tax</div>

      <div class="form-group">
        <label class="form-label">Tax type</label>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <?php foreach (['none'=>'❌ No tax','vat'=>'🏛️ VAT','sales_tax'=>'🏷️ Sales tax'] as $val=>$lbl): ?>
          <label style="display:flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid var(--border2);border-radius:var(--r);cursor:pointer;font-size:13px" id="tt-<?= $val ?>">
            <input type="radio" name="tax_type" value="<?= $val ?>" <?= ($s['tax_type']??'none')===$val?'checked':'' ?> onchange="onTaxTypeChange()">
            <?= $lbl ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div id="tax-fields" style="<?= ($s['tax_type']??'none')==='none'?'display:none':'' ?>">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tax rate (%)</label>
            <div style="display:flex;align-items:center;gap:8px">
              <input class="form-input" type="number" name="tax_rate" id="f-taxrate" min="0" max="100" step="0.1" value="<?= htmlspecialchars($s['tax_rate'] ?? '0') ?>" style="max-width:120px" oninput="updatePreview()">
              <span style="font-size:13px;color:var(--text2)">%</span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Tax label on invoice</label>
            <input class="form-input" name="tax_label" id="f-taxlabel" value="<?= htmlspecialchars($s['tax_label'] ?? '') ?>" placeholder="e.g. VAT, GST, Sales Tax" oninput="updatePreview()">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Tax calculation method</label>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <label style="display:flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid var(--border2);border-radius:var(--r);cursor:pointer;font-size:13px">
              <input type="radio" name="tax_inclusive" value="exclusive" <?= ($s['tax_inclusive']??'0')==='0'?'checked':'' ?> onchange="updatePreview()">
              ➕ Exclusive — tax added on top of price
            </label>
            <label style="display:flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid var(--border2);border-radius:var(--r);cursor:pointer;font-size:13px">
              <input type="radio" name="tax_inclusive" value="1" <?= ($s['tax_inclusive']??'0')==='1'?'checked':'' ?> onchange="updatePreview()">
              🔢 Inclusive — tax already inside price
            </label>
          </div>
          <span style="font-size:11px;color:var(--text3);margin-top:4px;display:block">Exclusive: product KWD 100 + VAT 5% = KWD 105. Inclusive: product KWD 105 already contains KWD 5 VAT.</span>
        </div>
        <div class="form-group">
          <label class="form-label">VAT / Tax registration number</label>
          <input class="form-input" name="vat_number" id="f-vatnum" value="<?= htmlspecialchars($s['vat_number'] ?? '') ?>" placeholder="e.g. TRN-100234567800003">
          <span style="font-size:11px;color:var(--text3)">Printed on every invoice when set</span>
        </div>
      </div>

      <!-- Live invoice preview -->
      <div style="border-top:1px solid var(--border2);margin:20px 0 16px"></div>
      <div style="font-size:12px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px">Live invoice preview</div>
      <div id="inv-preview" style="background:var(--bg3);border-radius:var(--r);padding:14px;font-size:13px;max-width:360px"></div>

      <div style="margin-top:20px">
        <button type="submit" class="btn btn-primary">💾 Save Currency & Tax</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ INVOICE TAB ════════════════════════════════════════════════════════════ -->
<div id="tab-invoice" class="card" style="<?= $active_tab!=='invoice'?'display:none':'' ?>">
  <div class="card-title"><span>🧾 <?= __('invoice_settings') ?></span></div>
  <form method="POST">
    <input type="hidden" name="action" value="save_invoice">
    <div class="form-group"><label class="form-label"><?= __('invoice_prefix') ?></label><input class="form-input" name="invoice_prefix" value="<?= htmlspecialchars($s['invoice_prefix'] ?? 'INV-') ?>"></div>
    <div class="form-group"><label class="form-label"><?= __('invoice_footer') ?></label><textarea class="form-textarea" name="invoice_footer"><?= htmlspecialchars($s['invoice_footer'] ?? '') ?></textarea></div>
    <div class="form-group">
      <label class="form-label"><?= __('show_logo_invoice') ?></label>
      <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--r)">
        <label class="toggle-switch" style="flex-shrink:0">
          <input type="checkbox" name="show_logo_in_invoice" value="1" id="toggle-logo" <?= ($s['show_logo_in_invoice']??'0')==='1'?'checked':'' ?>>
          <span class="toggle-slider"></span>
        </label>
        <div>
          <div style="font-size:13px;font-weight:500;color:var(--text)" id="toggle-logo-label"><?= ($s['show_logo_in_invoice']??'0')==='1'?__('enabled'):__('disabled') ?></div>
          <div style="font-size:11px;color:var(--text3);margin-top:2px">Show company logo on every printed invoice</div>
        </div>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Default printer format</label>
      <select class="form-select" name="printer_format">
        <option value="a4"      <?= ($s['printer_format']??'a4')==='a4'?'selected':'' ?>>A4 (Standard)</option>
        <option value="thermal" <?= ($s['printer_format']??'a4')==='thermal'?'selected':'' ?>>Thermal (80mm)</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Refund period (days)</label>
      <input class="form-input" type="number" name="refund_period_days" min="0" max="365" value="<?= (int)($s['refund_period_days'] ?? 0) ?>" style="max-width:160px">
      <span style="font-size:11px;color:var(--text3);display:block;margin-top:4px">Number of days a customer can return items after purchase. Set to <strong>0</strong> to allow refunds at any time.</span>
    </div>
    <button type="submit" class="btn btn-primary">💾 <?= __('save') ?></button>
  </form>
</div>

<!-- ═══ BACKUP TAB ════════════════════════════════════════════════════════════ -->
<div id="tab-backup" class="card" style="<?= $active_tab!=='backup'?'display:none':'' ?>">
  <div class="card-title"><span>🗃️ <?= __('database_backup') ?></span></div>

  <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
    <div style="flex:1;min-width:220px">
      <p style="font-size:13px;color:var(--text2);margin-bottom:6px;line-height:1.6">
        Downloads a complete <code>.sql</code> file of your entire database — all tables and data.
        Store it somewhere safe. Run this regularly.
      </p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:20px">
        File: <code><?= preg_replace('/[^A-Za-z0-9_-]/','_',trim(get_setting('company_name','retailpro'))) ?>_backup_<?= date('Ymd_His') ?>.sql</code>
      </p>
      <a href="<?= BASE ?>/api/backup.php"
         class="btn btn-primary"
         style="display:inline-flex;align-items:center;gap:8px;text-decoration:none">
        ⬇️ Download Database Backup
      </a>
    </div>
    <div style="background:var(--bg3);border-radius:var(--r);padding:14px;font-size:12px;min-width:180px">
      <div style="font-weight:600;margin-bottom:8px;color:var(--text2)">System info</div>
      <div style="color:var(--text3);margin-bottom:4px">Version: <span style="color:var(--accent2)"><?= APP_VERSION ?></span></div>
      <div style="color:var(--text3);margin-bottom:4px">PHP: <span style="color:var(--accent2)"><?= phpversion() ?></span></div>
      <div style="color:var(--text3);margin-bottom:4px">Database: <span style="color:var(--accent2)"><?= DB_NAME ?></span></div>
      <div style="color:var(--text3)">Timezone: <span style="color:var(--accent2)"><?= date_default_timezone_get() ?></span></div>
    </div>
  </div>
</div>

<?php
$cur      = $s['currency'] ?? 'KWD';
$dec      = $s['currency_decimals'] ?? '3';
$ttype    = $s['tax_type'] ?? 'none';
$trate    = $s['tax_rate'] ?? '0';
$tlabel   = $s['tax_label'] ?? '';
$tinc     = $s['tax_inclusive'] ?? '0';
ob_start(); ?>
<style>
.toggle-switch{position:relative;display:inline-block;width:44px;height:24px}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;cursor:pointer;inset:0;background:var(--bg5);border-radius:24px;transition:.25s}
.toggle-slider:before{content:"";position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.25s}
.toggle-switch input:checked + .toggle-slider{background:var(--green)}
.toggle-switch input:checked + .toggle-slider:before{transform:translateX(20px)}
.country-tile{display:flex;flex-direction:column;align-items:center;text-align:center;padding:12px 8px;border:1px solid var(--border2);border-radius:var(--r);cursor:pointer;transition:border-color .15s,background .15s}
.country-tile:hover{border-color:var(--accent);background:var(--bg3)}
.country-tile.active{border:2px solid var(--accent);background:var(--bg2)}
</style>
<script>
const TABS = ['tab-company','tab-tax','tab-invoice','tab-backup'];
function switchTab(id, el) {
  TABS.forEach(t => { const d = document.getElementById(t); if(d) d.style.display='none'; });
  const active = document.getElementById(id);
  if (active) active.style.display = 'block';
  document.querySelectorAll('#main-tabs .tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}

function selectCountry(code) {
  document.querySelectorAll('.country-tile').forEach(t => t.classList.remove('active'));
  const tile = document.querySelector('.country-tile[data-code="' + code + '"]');
  if (tile) tile.classList.add('active');
  const radio = document.querySelector('input[name="country_code"][value="' + code + '"]');
  if (radio) radio.checked = true;
  document.getElementById('cc-hidden').value = code;
  document.getElementById('profile-form').submit();
}

function onTaxTypeChange() {
  const v = document.querySelector('input[name="tax_type"]:checked')?.value ?? 'none';
  document.getElementById('tax-fields').style.display = v === 'none' ? 'none' : 'block';
  updatePreview();
}

function updatePreview() {
  const cur    = document.getElementById('f-currency')?.value || '<?= $cur ?>';
  const dec    = parseInt(document.getElementById('f-decimals')?.value ?? '<?= $dec ?>');
  const ttype  = document.querySelector('input[name="tax_type"]:checked')?.value ?? 'none';
  const trate  = parseFloat(document.getElementById('f-taxrate')?.value ?? '0') || 0;
  const tlabel = document.getElementById('f-taxlabel')?.value || 'Tax';
  const tinc   = document.querySelector('input[name="tax_inclusive"]:checked')?.value === '1';
  const sub    = 100;

  let taxAmt = 0, total = sub, preTax = sub;
  if (ttype !== 'none' && trate > 0) {
    if (tinc) {
      taxAmt = sub - sub / (1 + trate/100);
      preTax = sub - taxAmt;
      total  = sub;
    } else {
      taxAmt = sub * trate / 100;
      total  = sub + taxAmt;
    }
  }
  const f = v => cur + ' ' + v.toFixed(dec);
  let html = '<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border2)"><span style="color:var(--text3)">Subtotal</span><span>' + f(preTax) + '</span></div>';
  if (ttype !== 'none' && trate > 0) {
    html += '<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border2);color:var(--amber)"><span>' + tlabel + ' (' + trate + '%' + (tinc?' incl.':'') + ')</span><span>+ ' + f(taxAmt) + '</span></div>';
  } else {
    html += '<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border2);color:var(--green)"><span>No tax</span><span>—</span></div>';
  }
  html += '<div style="display:flex;justify-content:space-between;padding:8px 0;font-weight:700"><span>Total</span><span>' + f(total) + '</span></div>';
  const el = document.getElementById('inv-preview');
  if (el) el.innerHTML = html;
}

const logoToggle = document.getElementById('toggle-logo');
const logoLabel  = document.getElementById('toggle-logo-label');
if (logoToggle && logoLabel) {
  logoToggle.addEventListener('change', function() { logoLabel.textContent = this.checked ? 'Enabled' : 'Disabled'; });
}

document.querySelectorAll('.country-tile').forEach(t => {
  const radio = t.querySelector('input[name="country_code"]');
  if (radio) {
    radio.addEventListener('change', function() {
      selectCountry(this.value);
    });
  }
});

updatePreview();
</script>
<?php
$extra_js = ob_get_clean();
require __DIR__ . '/includes/footer.php';
