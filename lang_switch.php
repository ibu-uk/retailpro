<?php
require_once __DIR__ . '/includes/config.php';
$lang = $_GET['lang'] ?? 'en';
if (in_array($lang, ['en', 'ar'])) {
    $_SESSION['lang'] = $lang;
}
$ref = $_SERVER['HTTP_REFERER'] ?? BASE . '/index.php';
header('Location: ' . $ref);
exit;
