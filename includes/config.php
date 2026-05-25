<?php
// ============================================================
// RetailPro ERP — Configuration
// Edit DB credentials here
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'retailpro');
define('DB_USER', 'root');       // change to your MySQL user
define('DB_PASS', '');           // change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'RetailPro ERP');
define('APP_VERSION', '2.4.0');
define('CURRENCY', 'KWD');
// No VAT in Kuwait

// Auto-detect base URL (works in any subfolder)
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$base   = rtrim(dirname($script), '/\\');
// If login.php is at /retailpro/login.php, base = /retailpro
define('BASE', $base);

// ============================================================
// PDO Connection
// ============================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<html><body style="background:#f0f2f5;color:#ef4444;font-family:monospace;padding:40px"><h2>Database Connection Failed</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p style="color:#8896a6;margin-top:20px">Edit <strong>includes/config.php</strong> and set DB_USER and DB_PASS correctly.</p></body></html>');
        }
    }
    return $pdo;
}

// ============================================================
// Session & Auth helpers
// ============================================================
session_start();

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: ' . BASE . '/login.php');
        exit;
    }
}

function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (!isset($cache[$key])) {
        try {
            $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $cache[$key] = $row ? $row['setting_value'] : $default;
        } catch (Exception $e) {
            $cache[$key] = $default;
        }
    }
    return $cache[$key];
}

function get_tax_config(): array {
    static $tc = null;
    if ($tc === null) {
        $tc = [
            'currency'         => get_setting('currency',          'KWD'),
            'currency_decimals'=> (int)get_setting('currency_decimals', '3'),
            'tax_type'         => get_setting('tax_type',          'none'),  // none | vat | sales_tax
            'tax_rate'         => (float)get_setting('tax_rate',   '0'),
            'tax_label'        => get_setting('tax_label',         ''),
            'tax_inclusive'    => get_setting('tax_inclusive',     '0'),     // 1 = price includes tax
            'vat_number'       => get_setting('vat_number',        ''),
            'country_code'     => get_setting('country_code',      'KW'),
        ];
    }
    return $tc;
}

function fmt_money(float $amount): string {
    $tc = get_tax_config();
    return $tc['currency'] . ' ' . number_format($amount, $tc['currency_decimals']);
}

function calc_tax(float $subtotal): array {
    $tc = get_tax_config();
    if ($tc['tax_type'] === 'none' || $tc['tax_rate'] <= 0) {
        return ['subtotal' => $subtotal, 'tax' => 0.0, 'total' => $subtotal, 'rate' => 0, 'label' => ''];
    }
    $rate = $tc['tax_rate'] / 100;
    if ($tc['tax_inclusive'] === '1') {
        // Price already includes tax — extract it
        $tax      = $subtotal - ($subtotal / (1 + $rate));
        $pre_tax  = $subtotal - $tax;
        return ['subtotal' => round($pre_tax, $tc['currency_decimals']), 'tax' => round($tax, $tc['currency_decimals']), 'total' => $subtotal, 'rate' => $tc['tax_rate'], 'label' => $tc['tax_label']];
    } else {
        // Tax added on top
        $tax   = $subtotal * $rate;
        $total = $subtotal + $tax;
        return ['subtotal' => $subtotal, 'tax' => round($tax, $tc['currency_decimals']), 'total' => round($total, $tc['currency_decimals']), 'rate' => $tc['tax_rate'], 'label' => $tc['tax_label']];
    }
}

function next_invoice_number(): string {
    $prefix = get_setting('invoice_prefix', 'INV-');
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM invoices");
    $cnt = $stmt->fetch()['cnt'] + 1;
    return $prefix . date('Y') . '-' . str_pad($cnt, 4, '0', STR_PAD_LEFT);
}

function next_po_number(): string {
    $stmt = db()->query("SELECT COUNT(*) as cnt FROM purchase_orders");
    $cnt = $stmt->fetch()['cnt'] + 1;
    return 'PO-' . date('Y') . '-' . str_pad($cnt, 4, '0', STR_PAD_LEFT);
}

function next_quote_number(): string {
    $prefix = get_setting('quote_prefix', 'QUO-');
    // Graceful fallback: if table doesn't exist yet, use 0001
    try {
        $stmt = db()->query("SELECT COUNT(*) as cnt FROM quotations");
        $cnt = $stmt->fetch()['cnt'] + 1;
    } catch (Exception $e) {
        $cnt = 1;
    }
    return $prefix . date('Y') . '-' . str_pad($cnt, 4, '0', STR_PAD_LEFT);
}

// JSON response helper for API calls
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ============================================================
// Language System
// ============================================================
function get_lang(): string {
    return $_SESSION['lang'] ?? 'en';
}

function load_lang(): array {
    static $strings = null;
    if ($strings === null) {
        $lang = get_lang();
        $file = __DIR__ . '/../lang/' . $lang . '.php';
        if (!file_exists($file)) $file = __DIR__ . '/../lang/en.php';
        $strings = require $file;
    }
    return $strings;
}

function __($key, $default = null) {
    $strings = load_lang();
    return $strings[$key] ?? $default ?? $key;
}

function is_rtl(): bool {
    return get_lang() === 'ar';
}

// ============================================================
// NEW: Role helpers (required by reports.php, edit_invoice.php)
// ============================================================
function has_role(string ...$roles): bool {
    $u = current_user();
    return $u && in_array($u['role'], $roles, true);
}

function require_role(string ...$roles): void {
    if (!has_role(...$roles)) {
        http_response_code(403);
        $user = current_user();
        $name = htmlspecialchars($user['name'] ?? 'User');
        $role = ucfirst(str_replace('_',' ', $user['role'] ?? ''));
        $base = BASE;
        die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Access Denied</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f4f5f7;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:48px 40px;max-width:440px;width:100%;text-align:center}
  .icon-wrap{width:72px;height:72px;background:#fff3f3;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:32px}
  h1{font-size:22px;font-weight:600;color:#1a1a2e;margin-bottom:10px}
  .sub{font-size:14px;color:#6b7280;line-height:1.6;margin-bottom:28px}
  .badge{display:inline-flex;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:99px;padding:5px 14px;font-size:12px;font-weight:500;color:#374151;margin-bottom:28px}
  .badge span{width:8px;height:8px;border-radius:50%;background:#6366f1;display:inline-block}
  .btn{display:inline-flex;align-items:center;gap:8px;background:#6366f1;color:#fff;text-decoration:none;padding:11px 24px;border-radius:10px;font-size:14px;font-weight:500;transition:background .2s}
  .btn:hover{background:#4f46e5}
  .btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .footer{margin-top:20px;font-size:12px;color:#9ca3af}
</style>
</head><body>
<div class="card">
  <div class="icon-wrap">🔒</div>
  <h1>Access Restricted</h1>
  <p class="sub">This page requires a higher permission level.<br>Please contact your system administrator if you believe this is a mistake.</p>
  <div class="badge"><span></span>' . $name . ' &nbsp;·&nbsp; ' . $role . '</div><br>
  <a href="' . $base . '/index.php" class="btn">
    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
    Back to Dashboard
  </a>
  <p class="footer">' . htmlspecialchars(get_setting('company_name', APP_NAME)) . ' &nbsp;·&nbsp; Error 403</p>
</div>
</body></html>');
    }
}

// ============================================================
// NEW: Safe date helper (required by reports.php)
// ============================================================
function safe_date(string $val, string $fallback): string {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) ? $val : $fallback;
}