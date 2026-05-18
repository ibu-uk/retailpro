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

function fmt_money(float $amount): string {
    $currency = get_setting('currency', 'KWD');
    $decimals = ($currency === 'KWD') ? 3 : 2;
    return $currency . ' ' . number_format($amount, $decimals);
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
