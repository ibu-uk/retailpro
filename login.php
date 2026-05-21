<?php
require_once __DIR__ . '/includes/config.php';
if (current_user()) { header('Location: ' . BASE . '/index.php'); exit; }

$company_logo  = get_setting('company_logo');
$_co_name      = get_setting('company_name', APP_NAME);
$_co_name_ar   = get_setting('company_name_ar', '');
$_lang = get_lang();
$_rtl  = is_rtl();
$display_name  = ($_rtl && $_co_name_ar) ? $_co_name_ar : ($_co_name ?: APP_NAME);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user'] = $user;
            db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            header('Location: ' . BASE . '/index.php');
            exit;
        }
        $error = __('invalid_credentials');
    } catch (Exception $e) {
        $error = __('db_error');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_lang ?>" dir="<?= $_rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= __('sign_in') ?> — <?= __('app_name') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#f0f2f5;--bg2:#ffffff;--bg3:#f7f8fa;--bg4:#eef0f4;--border:#e3e6ed;--border2:#d0d4dc;--text:#1a1d26;--text2:#4a5568;--text3:#8896a6;--accent:#4361ee;--accent2:#3a56d4;--green:#22c55e;--red:#ef4444;--pink:#ec4899;--r:10px;--r2:14px;--font:'DM Sans',sans-serif}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:14px}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center}
.login-box{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r2);padding:40px;width:400px;max-width:95vw;box-shadow:0 8px 24px rgba(0,0,0,.1)}
.logo-icon{width:56px;height:56px;background:linear-gradient(135deg,var(--accent),#6d83f2);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 14px;box-shadow:0 4px 12px rgba(67,97,238,.25)}
.logo-center{text-align:center;margin-bottom:28px}
.logo-title{font-size:22px;font-weight:600;margin-bottom:4px;color:var(--text)}
.logo-sub{font-size:13px;color:var(--text3)}
.form-group{margin-bottom:14px}
.form-label{font-size:12px;color:var(--text2);font-weight:500;margin-bottom:6px;display:block}
.form-input{width:100%;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--r);color:var(--text);font-size:14px;font-family:var(--font);padding:10px 14px;outline:none;transition:border-color .18s,box-shadow .18s}
.form-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.1)}
.btn-submit{width:100%;background:var(--accent);color:#fff;border:none;border-radius:var(--r);font-size:14px;font-weight:600;font-family:var(--font);padding:12px;cursor:pointer;transition:background .18s,box-shadow .18s;margin-top:4px;box-shadow:0 2px 8px rgba(67,97,238,.25)}
.btn-submit:hover{background:var(--accent2);box-shadow:0 4px 12px rgba(67,97,238,.3)}
.error-box{background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);color:var(--red);border-radius:var(--r);padding:10px 14px;font-size:13px;margin-bottom:14px}
.demo-box{margin-top:20px;padding:14px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--r);font-size:12px;color:var(--text3)}
.demo-box strong{color:var(--text2);display:block;margin-bottom:6px}
code{color:var(--accent);background:var(--bg4);padding:1px 6px;border-radius:4px;font-family:monospace}
</style>
</head>
<body>
<div class="login-box fade-in">
  <div style="text-align:right;margin-bottom:10px">
    <a href="<?= BASE ?>/lang_switch.php?lang=<?= $_lang === 'en' ? 'ar' : 'en' ?>" style="font-size:12px;color:var(--accent);text-decoration:none">🌐 <?= $_lang === 'en' ? __('arabic') : __('english') ?></a>
  </div>
  <div class="logo-center">
    <?php if ($company_logo): ?>
    <img src="<?= htmlspecialchars($company_logo) ?>" alt="Company Logo" style="max-height:80px;max-width:200px;margin:0 auto 14px;display:block;border-radius:12px;object-fit:contain">
    <?php else: ?>
    <div class="logo-icon">🛍️</div>
    <?php endif; ?>
    <div class="logo-title"><?= htmlspecialchars($display_name) ?></div>
    <div class="logo-sub"><?= __('login_subtitle') ?></div>
  </div>
  <?php if ($error): ?>
  <div class="error-box">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label class="form-label"><?= __('email_address') ?></label>
      <input class="form-input" type="email" name="email" placeholder="admin@retailpro.kw" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('password') ?></label>
      <input class="form-input" type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-submit"><?= __('sign_in') ?> →</button>
  </form>
  <div class="demo-box">
    <strong><?= __('demo_credentials') ?></strong>
    <?= __('email') ?>: <code>admin@retailpro.kw</code><br>
    <?= __('password') ?>: <code>password</code>
  </div>
</div>
<div id="toast-container"></div>
<script>
const p=new URLSearchParams(location.search);
if(p.get('error')){const t=document.createElement('div');t.style.cssText='position:fixed;bottom:20px;right:20px;background:#fff;border:1px solid #e3e6ed;border-left:3px solid #ef4444;color:#ef4444;padding:12px 16px;border-radius:10px;font-size:13px;font-family:sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.1)';t.textContent='❌ '+decodeURIComponent(p.get('error'));document.body.appendChild(t);setTimeout(()=>t.remove(),4000)}
</script>
</body>
</html>
