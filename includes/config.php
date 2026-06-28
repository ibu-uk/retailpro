<?php
// ============================================================
// RetailPro ERP — Configuration
// DB credentials are now read from environment variables or a .env file.
// Copy .env.example to .env and fill in live credentials.
// NEVER commit .env to version control.
// ============================================================

// Load environment variables from .env file if present
function load_env(string $path): void {
    if (!file_exists($path) || !is_readable($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') continue;
        // Remove surrounding quotes if present
        if (strlen($value) > 1 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
load_env(__DIR__ . '/../.env');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'retailpro');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

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
            log_error('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . __('error') . '</title><style>
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f2f5;color:#1a1d26;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
            .card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:40px;max-width:480px;width:100%;text-align:center}
            .icon{font-size:40px;margin-bottom:16px}
            h1{font-size:20px;margin-bottom:8px}
            p{color:#6b7280;line-height:1.6}
            </style></head><body><div class="card"><div class="icon">🔌</div><h1>' . __('db_error') . '</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p style="font-size:12px;color:#9ca3af;margin-top:16px">RetailPro ERP</p></div></body></html>');
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

function check_session_timeout(): void {
    $timeout = (int)(getenv('SESSION_TIMEOUT') ?: 7200);
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: ' . BASE . '/login.php?error=' . urlencode(__('session_expired')));
        exit;
    }
}

function touch_session(): void {
    $_SESSION['last_activity'] = time();
}

function require_login(): void {
    if (!current_user()) {
        header('Location: ' . BASE . '/login.php');
        exit;
    }
    check_session_timeout();
    touch_session();
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
    $year = date('Y');
    $pattern = $prefix . $year . '%';
    $stmt = db()->prepare("SELECT COALESCE(MAX(id),0) as max_id FROM invoices WHERE invoice_number LIKE ?");
    $stmt->execute([$pattern]);
    $seq = $stmt->fetch()['max_id'] + 1;
    return $prefix . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function next_po_number(): string {
    $year = date('Y');
    $pattern = 'PO-' . $year . '%';
    $stmt = db()->prepare("SELECT COALESCE(MAX(id),0) as max_id FROM purchase_orders WHERE po_number LIKE ?");
    $stmt->execute([$pattern]);
    $seq = $stmt->fetch()['max_id'] + 1;
    return 'PO-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function next_quote_number(): string {
    $prefix = get_setting('quote_prefix', 'QUO-');
    $year = date('Y');
    $pattern = $prefix . $year . '%';
    try {
        $stmt = db()->prepare("SELECT COALESCE(MAX(id),0) as max_id FROM quotations WHERE quote_number LIKE ?");
        $stmt->execute([$pattern]);
        $seq = $stmt->fetch()['max_id'] + 1;
    } catch (Exception $e) {
        $seq = 1;
    }
    return $prefix . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
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

// ============================================================
// Security: CSRF protection
// ============================================================
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): void {
    if (PHP_SAPI === 'cli') return;
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$token)) {
        log_error('CSRF validation failed for ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        $is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || stripos($content_type, 'application/json') !== false;
        if ($is_ajax) {
            json_response(['error' => 'Invalid CSRF token — please refresh the page'], 403);
        }
        http_response_code(403);
        die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . __('security_error') . '</title><style>
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f2f5;color:#1a1d26;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
            .card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:40px;max-width:480px;width:100%;text-align:center}
            .icon{font-size:40px;margin-bottom:16px}
            h1{font-size:20px;margin-bottom:8px}
            p{color:#6b7280;line-height:1.6}
            </style></head><body><div class="card"><div class="icon">🛡️</div><h1>' . __('security_error') . '</h1><p>' . __('invalid_csrf') . '</p><p style="font-size:12px;color:#9ca3af;margin-top:16px">RetailPro ERP</p></div></body></html>');
    }
}

// ============================================================
// Audit logging
// ============================================================
function ensure_audit_log_table(): void {
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(50) NOT NULL,
            table_name VARCHAR(80),
            record_id INT,
            old_values TEXT,
            new_values TEXT,
            ip_address VARCHAR(45),
            user_agent VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        log_error('Could not create audit_log table: ' . $e->getMessage());
    }
}

function audit_log(string $action, string $table_name, int $record_id, ?array $old = null, ?array $new = null): void {
    try {
        ensure_audit_log_table();
        db()->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([
               current_user()['id'] ?? null,
               $action,
               $table_name,
               $record_id,
               $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
               $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
               $_SERVER['REMOTE_ADDR'] ?? null,
               $_SERVER['HTTP_USER_AGENT'] ?? null
           ]);
    } catch (Exception $e) {
        log_error('Audit log failed: ' . $e->getMessage());
    }
}

// ============================================================
// Error logging
// ============================================================
function log_error(string $message): void {
    $log_dir = __DIR__ . '/../logs';
    $log_file = $log_dir . '/retailpro.log';
    try {
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0750, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // silently ignore to avoid breaking the app
    }
}

// ============================================================
// Global CSRF protection for POST requests
// ============================================================
if (PHP_SAPI !== 'cli' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}