<?php
$current_page = $current_page ?? 'dashboard';
$user = current_user();
$_lang = get_lang();
$_rtl = is_rtl();
$_dir = $_rtl ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?= $_lang ?>" dir="<?= $_dir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title ?? __('nav_dashboard')) ?> — <?= __('app_name') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#f0f2f5;--bg2:#ffffff;--bg3:#f7f8fa;--bg4:#eef0f4;--bg5:#e2e5eb;--border:#e3e6ed;--border2:#d0d4dc;--text:#1a1d26;--text2:#4a5568;--text3:#8896a6;--accent:#4361ee;--accent2:#3a56d4;--green:#22c55e;--green2:#16a34a;--red:#ef4444;--red2:#dc2626;--amber:#f59e0b;--amber2:#d97706;--blue:#3b82f6;--blue2:#2563eb;--pink:#ec4899;--teal:#14b8a6;--r:10px;--r2:14px;--font:'DM Sans',sans-serif;--mono:'DM Mono',monospace;--sidebar:240px;--transition:0.18s cubic-bezier(.4,0,.2,1);--shadow:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);--shadow-md:0 4px 12px rgba(0,0,0,.07);--shadow-lg:0 8px 24px rgba(0,0,0,.1)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:14px}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;overflow-x:hidden}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--border2);border-radius:99px}
#sidebar{width:var(--sidebar);min-height:100vh;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0;z-index:100;transition:transform var(--transition);box-shadow:1px 0 4px rgba(0,0,0,.04)}
#sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99;opacity:0;transition:opacity var(--transition)}
#sidebar-overlay.visible{opacity:1}
.sidebar-logo{padding:20px 18px 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border)}
.logo-icon{width:36px;height:36px;background:linear-gradient(135deg,var(--accent),#6d83f2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;box-shadow:0 2px 8px rgba(67,97,238,.25)}
.logo-text{font-size:15px;font-weight:600;letter-spacing:-.3px;color:var(--text)}
.logo-sub{font-size:11px;color:var(--text3);margin-top:1px}
.sidebar-nav{flex:1;padding:10px 0;overflow-y:auto}
.nav-section{margin:8px 0}
.nav-label{padding:4px 18px;font-size:10px;font-weight:600;letter-spacing:1.2px;text-transform:uppercase;color:var(--text3)}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 18px;cursor:pointer;font-size:13px;color:var(--text2);text-decoration:none;transition:background var(--transition),color var(--transition);position:relative;user-select:none;margin:1px 8px;border-radius:8px;min-height:40px}
.nav-item:hover{background:var(--bg3);color:var(--text)}
.nav-item.active{color:#fff;background:var(--accent);box-shadow:0 2px 8px rgba(67,97,238,.3)}
.nav-item.active::before{content:none}
.nav-icon{font-size:16px;width:20px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:600;padding:1px 6px;border-radius:99px}
.nav-item.active .nav-badge{background:rgba(255,255,255,.3);color:#fff}
.sidebar-footer{padding:14px 18px;border-top:1px solid var(--border);display:flex;align-items:center;gap:10px}
.user-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#6d83f2);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0}
.user-name{font-size:13px;font-weight:500;color:var(--text)}
.user-role{font-size:11px;color:var(--text3)}
#hamburger{display:none;width:36px;height:36px;align-items:center;justify-content:center;background:var(--bg3);border:1px solid var(--border2);border-radius:var(--r);cursor:pointer;font-size:18px;flex-shrink:0}
#main{margin-left:var(--sidebar);flex:1;min-height:100vh;display:flex;flex-direction:column}
#topbar{height:56px;background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;gap:12px;position:sticky;top:0;z-index:50;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.page-title{font-size:15px;font-weight:600;flex:1;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.topbar-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--r);font-size:13px;font-weight:500;cursor:pointer;border:none;font-family:var(--font);text-decoration:none;transition:all var(--transition);white-space:nowrap}
.btn-primary{background:var(--accent);color:#fff;box-shadow:0 2px 6px rgba(67,97,238,.25)}
.btn-primary:hover{background:var(--accent2);box-shadow:0 4px 10px rgba(67,97,238,.3)}
.btn-ghost{background:var(--bg2);color:var(--text2);border:1px solid var(--border2)}
.btn-ghost:hover{background:var(--bg3);color:var(--text);border-color:var(--text3)}
.btn-sm{padding:5px 10px;font-size:12px}
.btn-green{background:var(--green);color:#fff;box-shadow:0 2px 6px rgba(34,197,94,.25)}
.btn-green:hover{background:var(--green2)}
.btn-red{background:var(--red);color:#fff}
.btn-amber{background:var(--amber);color:#fff}
#content{flex:1;padding:24px;overflow-y:auto}
.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r2);padding:20px;box-shadow:var(--shadow)}
.card-title{font-size:13px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.card-title span{flex:1}
.card-title a{font-size:11px;color:var(--accent);cursor:pointer;font-weight:400;text-transform:none;letter-spacing:0;text-decoration:none}
.card-title a:hover{color:var(--accent2)}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:20px}
.stat-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r2);padding:18px;position:relative;overflow:hidden;cursor:default;transition:border-color var(--transition),transform var(--transition),box-shadow var(--transition);box-shadow:var(--shadow)}
.stat-card:hover{border-color:var(--border2);transform:translateY(-1px);box-shadow:var(--shadow-md)}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat-card.green::before{background:linear-gradient(90deg,#22c55e,#4ade80)}
.stat-card.purple::before{background:linear-gradient(90deg,#4361ee,#818cf8)}
.stat-card.amber::before{background:linear-gradient(90deg,#f59e0b,#fbbf24)}
.stat-card.blue::before{background:linear-gradient(90deg,#3b82f6,#60a5fa)}
.stat-card.red::before{background:linear-gradient(90deg,#ef4444,#f87171)}
.stat-card.pink::before{background:linear-gradient(90deg,#ec4899,#f472b6)}
.stat-card.teal::before{background:linear-gradient(90deg,#14b8a6,#2dd4bf)}
.stat-icon{font-size:22px;margin-bottom:10px}
.stat-label{font-size:11px;color:var(--text3);font-weight:500;letter-spacing:.3px;text-transform:uppercase}
.stat-value{font-size:24px;font-weight:600;margin:4px 0 2px;letter-spacing:-.5px}
.stat-delta{font-size:11px;display:flex;align-items:center;gap:3px}
.stat-delta.up{color:var(--green)}
.stat-delta.down{color:var(--red)}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
.grid-70-30{display:grid;grid-template-columns:1fr 340px;gap:16px}
.grid-60-40{display:grid;grid-template-columns:1fr .65fr;gap:16px}
.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse}
th{font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;padding:8px 12px;border-bottom:2px solid var(--border);text-align:left;white-space:nowrap;background:var(--bg3)}
td{padding:10px 12px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text)}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--bg3)}
.hide-mobile{display:table-cell}
.hide-tablet{display:table-cell}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:500}
.badge-green{background:rgba(34,197,94,.1);color:var(--green2)}
.badge-red{background:rgba(239,68,68,.1);color:var(--red)}
.badge-amber{background:rgba(245,158,11,.1);color:var(--amber2)}
.badge-blue{background:rgba(59,130,246,.1);color:var(--blue2)}
.badge-purple{background:rgba(67,97,238,.1);color:var(--accent)}
.badge-gray{background:var(--bg4);color:var(--text2)}
.dot{width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0}
.progress{height:4px;background:var(--bg4);border-radius:99px;overflow:hidden}
.progress-fill{height:100%;border-radius:99px;transition:width .6s ease}
.pos-layout{display:grid;grid-template-columns:1fr 380px;gap:16px;height:calc(100vh - 100px)}
.pos-products{display:flex;flex-direction:column;gap:14px;overflow:hidden}
.search-bar{display:flex;gap:8px;align-items:center}
.search-input{background:var(--bg2);border:1px solid var(--border2);border-radius:var(--r);color:var(--text);font-size:14px;font-family:var(--font);padding:10px 14px;outline:none;transition:border-color var(--transition),box-shadow var(--transition)}
.search-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.1)}
.search-input::placeholder{color:var(--text3)}
select.search-input{cursor:pointer;background:var(--bg2)}
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;overflow-y:auto;flex:1;padding:2px}
.product-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r2);padding:12px;cursor:pointer;transition:all var(--transition);user-select:none;box-shadow:var(--shadow)}
.product-card:hover{border-color:var(--accent);box-shadow:var(--shadow-md);transform:translateY(-2px)}
.product-card:active{transform:scale(.97)}
.product-img{width:100%;aspect-ratio:1;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:36px;margin-bottom:8px}
.product-name{font-size:12px;font-weight:500;margin-bottom:2px;color:var(--text)}
.product-sku{font-size:10px;color:var(--text3);margin-bottom:4px;font-family:var(--mono)}
.product-price{font-size:13px;font-weight:600;color:var(--green2)}
.product-stock{font-size:10px;color:var(--text3)}
.pos-cart{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r2);display:flex;flex-direction:column;overflow:hidden;box-shadow:var(--shadow)}
.cart-header{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.cart-header h3{flex:1;font-size:14px;font-weight:600;color:var(--text)}
.cart-items{flex:1;overflow-y:auto;padding:10px}
.cart-item{display:flex;align-items:center;gap:10px;padding:10px;border-radius:var(--r);background:var(--bg3);margin-bottom:8px;border:1px solid var(--border)}
.cart-item-emoji{font-size:22px}
.cart-item-info{flex:1;min-width:0}
.cart-item-name{font-size:12px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text)}
.cart-item-price{font-size:11px;color:var(--text3)}
.cart-item-controls{display:flex;align-items:center;gap:6px}
.qty-btn{width:24px;height:24px;border-radius:6px;background:var(--bg4);border:1px solid var(--border2);color:var(--text);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:background var(--transition)}
.qty-btn:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
.qty-num{font-size:13px;font-weight:600;min-width:18px;text-align:center;color:var(--text)}
.cart-footer{padding:14px 16px;border-top:1px solid var(--border)}
.cart-totals{margin-bottom:12px}
.cart-row{display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;color:var(--text2)}
.cart-row.total{color:var(--text);font-size:16px;font-weight:600;border-top:2px solid var(--border);padding-top:8px;margin-top:4px}
.payment-modes{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px}
.pay-mode{padding:8px;border-radius:8px;border:1.5px solid var(--border2);font-size:11px;font-weight:500;text-align:center;cursor:pointer;transition:all var(--transition);color:var(--text2);background:var(--bg2)}
.pay-mode:hover,.pay-mode.active{border-color:var(--accent);color:var(--accent);background:rgba(67,97,238,.06)}
.inv-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center}
.filter-chip{padding:5px 12px;border-radius:99px;border:1px solid var(--border2);font-size:12px;color:var(--text2);cursor:pointer;transition:all var(--transition);text-decoration:none;background:var(--bg2)}
.filter-chip:hover,.filter-chip.active{background:var(--accent);border-color:var(--accent);color:#fff}
.form-group{margin-bottom:14px}
.form-label{font-size:12px;color:var(--text2);font-weight:500;margin-bottom:5px;display:block}
.form-input,.form-select,.form-textarea{width:100%;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--r);color:var(--text);font-size:13px;font-family:var(--font);padding:9px 12px;outline:none;transition:border-color var(--transition),box-shadow var(--transition)}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(67,97,238,.1)}
.form-textarea{resize:vertical;min-height:80px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.form-select option{background:var(--bg2)}
.toast-container{position:fixed;bottom:24px;right:24px;z-index:999;display:flex;flex-direction:column;gap:8px;max-width:calc(100vw - 32px)}
.toast{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r2);padding:12px 16px;display:flex;align-items:center;gap:10px;min-width:280px;font-size:13px;animation:slideIn .25s cubic-bezier(.4,0,.2,1);box-shadow:var(--shadow-lg)}
@keyframes slideIn{from{transform:translateX(60px);opacity:0}to{transform:none;opacity:1}}
.toast-icon{font-size:18px}
.toast-text{flex:1}
.toast-title{font-weight:600;margin-bottom:1px;color:var(--text)}
.toast-msg{color:var(--text2);font-size:12px}
.toast.success{border-left:3px solid var(--green)}
.toast.error{border-left:3px solid var(--red)}
.toast.warning{border-left:3px solid var(--amber)}
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s;backdrop-filter:blur(2px)}
.modal-backdrop.open{opacity:1;pointer-events:all}
.modal{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r2);width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;transform:scale(.95) translateY(20px);transition:transform .2s;box-shadow:var(--shadow-lg)}
.modal-backdrop.open .modal{transform:none}
.modal-header{padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;position:sticky;top:0;background:var(--bg2);z-index:1}
.modal-title{font-size:15px;font-weight:600;flex:1;color:var(--text)}
.modal-close{font-size:20px;cursor:pointer;color:var(--text3);transition:color var(--transition);padding:0;background:none;border:none;width:30px;height:30px;display:flex;align-items:center;justify-content:center}
.modal-close:hover{color:var(--text)}
.modal-body{padding:20px}
.modal-footer{padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;position:sticky;bottom:0;background:var(--bg2)}
.tabs{display:flex;gap:2px;border-bottom:2px solid var(--border);margin-bottom:20px;overflow-x:auto}
.tabs::-webkit-scrollbar{height:0}
.tab{padding:9px 16px;font-size:13px;font-weight:500;color:var(--text3);cursor:pointer;border-bottom:2px solid transparent;transition:all var(--transition);margin-bottom:-2px;white-space:nowrap}
.tab:hover{color:var(--text2)}
.tab.active{color:var(--accent);border-bottom-color:var(--accent);font-weight:600}
.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.bar{width:100%;border-radius:4px 4px 0 0;transition:height .4s cubic-bezier(.4,0,.2,1);cursor:pointer}
.bar:hover{opacity:.85}
.bar-label{font-size:10px;color:var(--text3)}
.ledger-row{display:flex;align-items:center;padding:9px 0;border-bottom:1px solid var(--border);gap:10px;font-size:13px}
.ledger-row:last-child{border-bottom:none}
.ledger-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0}
.ledger-info{flex:1;min-width:0}
.ledger-name{font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text)}
.ledger-sub{font-size:11px;color:var(--text3)}
.ledger-amount{font-weight:600}
.notif-btn{position:relative;width:36px;height:36px;border-radius:var(--r);background:var(--bg3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px;text-decoration:none;transition:all var(--transition)}
.notif-btn:hover{background:var(--bg4)}
.notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;background:var(--red);border-radius:50%;border:2px solid var(--bg2)}
.branch-pill{display:flex;align-items:center;gap:6px;padding:5px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--r);font-size:12px;font-weight:500;cursor:pointer;color:var(--text2)}
.branch-dot{width:8px;height:8px;border-radius:50%;background:var(--green);flex-shrink:0}
.pagination{display:flex;gap:6px;align-items:center;margin-top:14px;justify-content:flex-end;flex-wrap:wrap}
.page-link{padding:5px 10px;border-radius:var(--r);font-size:12px;background:var(--bg2);border:1px solid var(--border);color:var(--text2);cursor:pointer;transition:all var(--transition);text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.page-link:hover,.page-link.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.page-info{font-size:12px;color:var(--text3)}
.fade-in{animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.login-page{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg)}
.login-box{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r2);padding:40px;width:380px;max-width:95vw;box-shadow:var(--shadow-lg)}
.alert{padding:12px 16px;border-radius:var(--r);font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.alert-success{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);color:var(--green2)}
.alert-error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:var(--red)}
.alert-warning{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);color:var(--amber2)}
.flex{display:flex}.items-center{align-items:center}.gap-8{gap:8px}.gap-12{gap:12px}
.mb-4{margin-bottom:4px}.mb-8{margin-bottom:8px}.mb-12{margin-bottom:12px}.mb-16{margin-bottom:16px}.mb-20{margin-bottom:20px}
.text-sm{font-size:12px}.text-muted{color:var(--text2)}.text-xs{font-size:11px}.text-right{text-align:right}
.font-mono{font-family:var(--mono)}.w-full{width:100%}.truncate{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.text-green{color:var(--green2)}.text-red{color:var(--red)}.text-amber{color:var(--amber2)}.text-blue{color:var(--blue2)}.text-accent{color:var(--accent)}

/* ── RESPONSIVE ── */
@media(max-width:900px){
  #sidebar{transform:translateX(-100%)}
  #sidebar.open{transform:none}
  #sidebar-overlay{display:block}
  #hamburger{display:flex}
  #main{margin-left:0!important;margin-right:0!important}
  .grid-2,.grid-3,.grid-70-30,.grid-60-40{grid-template-columns:1fr}
  .pos-layout{grid-template-columns:1fr;height:auto}
  .payment-modes{grid-template-columns:repeat(3,1fr)}
  #content{padding:14px}
  .stats-grid{grid-template-columns:repeat(2,1fr);gap:10px}
}
@media(max-width:640px){
  .hide-mobile{display:none!important}
  #topbar{padding:0 12px;gap:8px}
  #content{padding:10px}
  .card{padding:14px}
  .stats-grid{grid-template-columns:1fr 1fr;gap:8px}
  .stat-value{font-size:20px}
  .modal{max-height:95vh;border-radius:var(--r2) var(--r2) 0 0}
  .modal-backdrop{align-items:flex-end}
  .toast-container{left:10px;right:10px;bottom:12px}
  .toast{min-width:0;width:100%}
  .form-row,.form-row-3{grid-template-columns:1fr}
  .branch-pill span:not(.branch-dot){display:none}
}
@media(max-width:900px){.hide-tablet{display:none!important}}

.lang-btn{display:flex;align-items:center;gap:4px;padding:5px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--r);font-size:12px;font-weight:500;cursor:pointer;color:var(--text2);text-decoration:none;transition:all var(--transition)}
.lang-btn:hover{background:var(--bg4);color:var(--text)}

/* ── RTL ── */
<?php if ($_rtl): ?>
html[dir="rtl"] body{direction:rtl;text-align:right}
html[dir="rtl"] #sidebar{left:auto;right:0;border-right:none;border-left:1px solid var(--border);box-shadow:-1px 0 4px rgba(0,0,0,.04)}
html[dir="rtl"] #main{margin-left:0;margin-right:var(--sidebar)}
html[dir="rtl"] th{text-align:right}
html[dir="rtl"] .nav-badge{margin-left:0;margin-right:auto}
html[dir="rtl"] .modal-footer{justify-content:flex-start}
html[dir="rtl"] .text-right{text-align:left}
html[dir="rtl"] .toast-container{right:auto;left:24px}
html[dir="rtl"] .pagination{justify-content:flex-start}
html[dir="rtl"] .cart-item{flex-direction:row-reverse}
html[dir="rtl"] .stat-delta{flex-direction:row-reverse}
html[dir="rtl"] .sidebar-footer{flex-direction:row-reverse}
html[dir="rtl"] .nav-item{flex-direction:row-reverse}
html[dir="rtl"] .sidebar-logo{flex-direction:row-reverse}
html[dir="rtl"] .topbar-actions{flex-direction:row-reverse}
html[dir="rtl"] .cart-header{flex-direction:row-reverse}
html[dir="rtl"] .search-bar{flex-direction:row-reverse}
html[dir="rtl"] .cust-search-icon{left:auto;right:10px}
html[dir="rtl"] .cust-dropdown{left:0;right:0}
html[dir="rtl"] .pos-mobile-tabs{margin:-24px -24px 14px}
html[dir="rtl"] .barcode-bar{flex-direction:row-reverse}
html[dir="rtl"] .cart-totals .cart-row{flex-direction:row-reverse}
html[dir="rtl"] .customer-tabs{flex-direction:row-reverse}
html[dir="rtl"] .cust-option{flex-direction:row-reverse}
html[dir="rtl"] .cust-selected{flex-direction:row-reverse}
html[dir="rtl"] .walkin-label{flex-direction:row-reverse}
html[dir="rtl"] .payment-modes .pay-mode{direction:rtl}
html[dir="rtl"] .form-row{direction:rtl}
html[dir="rtl"] .nav-item.active::before{left:auto;right:0;border-radius:3px 0 0 3px}
html[dir="rtl"] .stat-card::before{left:0;right:0}
html[dir="rtl"] .tabs{flex-direction:row-reverse}
html[dir="rtl"] .tab{border-bottom:2px solid transparent}
html[dir="rtl"] .modal-close{margin-left:0;margin-right:auto}
html[dir="rtl"] .inv-filters{flex-direction:row-reverse}
html[dir="rtl"] .topbar-actions .btn{flex-direction:row-reverse}
html[dir="rtl"] td{text-align:right}
html[dir="rtl"] .badge{direction:rtl}
html[dir="rtl"] .pos-cart .cart-footer .cart-row{flex-direction:row-reverse}
html[dir="rtl"] #pos-cat{text-align:right}
html[dir="rtl"] .ledger-row{flex-direction:row-reverse}
html[dir="rtl"] .alert{flex-direction:row-reverse}
html[dir="rtl"] .toast{flex-direction:row-reverse}
html[dir="rtl"] .toast.success{border-left:none;border-right:3px solid var(--green)}
html[dir="rtl"] .toast.error{border-left:none;border-right:3px solid var(--red)}
html[dir="rtl"] .toast.warning{border-left:none;border-right:3px solid var(--amber)}
@media(max-width:900px){
  html[dir="rtl"] #sidebar{transform:translateX(100%)}
  html[dir="rtl"] #sidebar.open{transform:none}
  html[dir="rtl"] #main{margin-right:0!important}
}
<?php endif; ?>

@media print{
  #sidebar,#topbar,#hamburger,.btn,.pagination,.inv-filters{display:none!important}
  #main{margin:0!important}
  #content{padding:0}
  body{background:#fff;color:#000}
}
</style>
</head>
<body>

<!-- Sidebar overlay (mobile) -->
<div id="sidebar-overlay" onclick="closeSidebar()"></div>

<nav id="sidebar">
  <div class="sidebar-logo">
    <?php
      $company_logo    = get_setting('company_logo');
      $_co_name        = get_setting('company_name', APP_NAME);
      $_co_name_ar     = get_setting('company_name_ar', '');
      $display_name    = (is_rtl() && $_co_name_ar) ? $_co_name_ar : ($_co_name ?: APP_NAME);
    ?>
    <?php if ($company_logo): ?>
    <img src="<?= htmlspecialchars($company_logo) ?>" alt="Logo" style="max-height:36px;max-width:36px;border-radius:8px;object-fit:contain;flex-shrink:0">
    <?php else: ?>
    <div class="logo-icon" style="flex-shrink:0">🛍️</div>
    <?php endif; ?>
    <div style="min-width:0;overflow:hidden">
      <div class="logo-text" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($display_name) ?>"><?= htmlspecialchars($display_name) ?></div>
      <div class="logo-sub">v<?= APP_VERSION ?></div>
    </div>
  </div>

  <div class="sidebar-nav">

    <div class="nav-section">
      <div class="nav-label"><?= __('nav_core') ?></div>
      <a class="nav-item <?= $current_page==='dashboard'?'active':'' ?>" href="<?= BASE ?>/index.php"><span class="nav-icon">📊</span> <?= __('nav_dashboard') ?></a>
      <a class="nav-item <?= $current_page==='pos'?'active':'' ?>" href="<?= BASE ?>/pos.php"><span class="nav-icon">🏪</span> <?= __('nav_pos') ?></a>
      <a class="nav-item <?= $current_page==='refunds'?'active':'' ?>" href="<?= BASE ?>/refunds.php"><span class="nav-icon">↩️</span> <?= __('nav_refunds') ?></a>
      <a class="nav-item <?= $current_page==='products'?'active':'' ?>" href="<?= BASE ?>/products.php"><span class="nav-icon">🏷️</span> <?= __('nav_products') ?></a>
      <a class="nav-item <?= $current_page==='categories'?'active':'' ?>" href="<?= BASE ?>/categories.php"><span class="nav-icon">📂</span> <?= __('nav_categories') ?></a>
      <a class="nav-item <?= $current_page==='inventory'?'active':'' ?>" href="<?= BASE ?>/inventory.php">
        <span class="nav-icon">🗄️</span> <?= __('nav_inventory') ?>
        <?php try { $low=db()->query("SELECT COUNT(*) as c FROM stock WHERE qty<=5 AND qty>0")->fetch()['c']; if($low>0) echo "<span class='nav-badge'>$low</span>"; } catch(Exception $e){} ?>
      </a>
      <!-- ── NEW: Batches & Expiry ── -->
      <a class="nav-item <?= $current_page==='batches'?'active':'' ?>" href="<?= BASE ?>/batches.php">
        <span class="nav-icon">🏷️</span> Batches &amp; Expiry
        <?php try {
          $exp = db()->query("SELECT COUNT(*) FROM stock_batches WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND qty_remaining > 0 AND status != 'expired'")->fetchColumn();
          if ($exp > 0) echo "<span class='nav-badge'>$exp</span>";
        } catch(Exception $e){} ?>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label"><?= __('nav_people') ?></div>
      <a class="nav-item <?= $current_page==='customers'?'active':'' ?>" href="<?= BASE ?>/customers.php"><span class="nav-icon">👥</span> <?= __('nav_customers') ?></a>
      <a class="nav-item <?= $current_page==='suppliers'?'active':'' ?>" href="<?= BASE ?>/suppliers.php"><span class="nav-icon">🏭</span> <?= __('nav_suppliers') ?></a>
    </div>

    <div class="nav-section">
      <div class="nav-label"><?= __('nav_finance') ?></div>
      <a class="nav-item <?= $current_page==='purchases'?'active':'' ?>" href="<?= BASE ?>/purchases.php"><span class="nav-icon">🛒</span> <?= __('nav_purchases') ?></a>
      <a class="nav-item <?= $current_page==='payments'?'active':'' ?>" href="<?= BASE ?>/payments.php">
        <span class="nav-icon">💳</span> <?= __('nav_payments') ?>
        <?php try { $due=db()->query("SELECT COUNT(*) as c FROM customers WHERE balance<0")->fetch()['c']; if($due>0) echo "<span class='nav-badge'>$due</span>"; } catch(Exception $e){} ?>
      </a>
      <a class="nav-item <?= $current_page==='expenses'?'active':'' ?>" href="<?= BASE ?>/expenses.php"><span class="nav-icon">💸</span> <?= __('nav_expenses') ?></a>
    </div>

    <div class="nav-section">
      <div class="nav-label"><?= __('nav_management') ?></div>
      <a class="nav-item <?= $current_page==='branches'?'active':'' ?>" href="<?= BASE ?>/branches.php"><span class="nav-icon">🏢</span> <?= __('nav_branches') ?></a>
      <a class="nav-item <?= $current_page==='offers'?'active':'' ?>" href="<?= BASE ?>/offers.php"><span class="nav-icon">🎯</span> <?= __('nav_offers') ?></a>
      <a class="nav-item <?= $current_page==='reports'?'active':'' ?>" href="<?= BASE ?>/reports.php"><span class="nav-icon">📈</span> <?= __('nav_reports') ?></a>
      <a class="nav-item <?= $current_page==='accounting'?'active':'' ?>" href="<?= BASE ?>/accounting.php"><span class="nav-icon">📒</span> <?= __('nav_accounting') ?></a>
      <a class="nav-item <?= $current_page==='users'?'active':'' ?>" href="<?= BASE ?>/users.php"><span class="nav-icon">🔐</span> <?= __('nav_users') ?></a>
      <a class="nav-item <?= $current_page==='settings'?'active':'' ?>" href="<?= BASE ?>/settings.php"><span class="nav-icon">⚙️</span> <?= __('nav_settings') ?></a>
    </div>

  </div>

  <div class="sidebar-footer">
    <div class="user-avatar"><?= strtoupper(substr($user['name'],0,2)) ?></div>
    <div style="flex:1;min-width:0">
      <div class="user-name truncate"><?= htmlspecialchars($user['name']) ?></div>
      <div class="user-role"><?= ucfirst(str_replace('_',' ',$user['role'])) ?></div>
    </div>
    <a href="<?= BASE ?>/logout.php" style="font-size:16px;color:var(--text3);text-decoration:none" title="<?= __('logout') ?>">🚪</a>
  </div>
</nav>

<!-- Main -->
<div id="main">
  <div id="topbar">
    <button id="hamburger" onclick="toggleSidebar()" aria-label="Menu">☰</button>
    <div class="page-title"><?= htmlspecialchars($page_title ?? __('nav_dashboard')) ?></div>
    <div class="topbar-actions">
      <a href="<?= BASE ?>/lang_switch.php?lang=<?= $_lang === 'en' ? 'ar' : 'en' ?>" class="lang-btn" title="<?= __('switch_language') ?>">🌐 <?= $_lang === 'en' ? __('arabic') : __('english') ?></a>
      <div class="branch-pill"><div class="branch-dot"></div><span><?= __('all_branches') ?></span></div>
      <a href="<?= BASE ?>/payments.php" class="notif-btn">
        <span>🔔</span>
        <?php try { $d2=db()->query("SELECT COUNT(*) as c FROM customers WHERE balance<0")->fetch()['c']; if($d2>0) echo "<div class='notif-dot'></div>"; } catch(Exception $e){} ?>
      </a>
      <a href="<?= BASE ?>/pos.php" class="btn btn-primary"><?= __('new_sale') ?></a>
    </div>
  </div>
  <div id="content">

<?php
// Show success / error alerts from URL params
if (!empty($_GET['success'])): ?>
<div class="alert alert-success">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php endif;
if (!empty($_GET['error'])): ?>
<div class="alert alert-error">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<!-- Toast container -->
<div class="toast-container" id="toast-container"></div>

<script>
// ── Toast ──
function showToast(title, msg, type) {
  type = type || 'success';
  const icons = { success:'✅', error:'❌', warning:'⚠️' };
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = '<div class="toast-icon">' + icons[type] + '</div>' +
    '<div class="toast-text"><div class="toast-title">' + title + '</div>' +
    (msg ? '<div class="toast-msg">' + msg + '</div>' : '') + '</div>';
  document.getElementById('toast-container').appendChild(t);
  setTimeout(function() { t.style.opacity='0'; t.style.transform='translateX(60px)'; t.style.transition='all .3s'; setTimeout(function(){t.remove()},300); }, 3500);
}

// ── Modals ──
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-backdrop.open').forEach(function(m) {
      m.classList.remove('open');
    });
    document.body.style.overflow = '';
  }
});

// ── Mobile Sidebar ──
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const o = document.getElementById('sidebar-overlay');
  const open = s.classList.toggle('open');
  o.classList.toggle('visible', open);
  document.body.style.overflow = open ? 'hidden' : '';
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('visible');
  document.body.style.overflow = '';
}
window.addEventListener('resize', function() {
  if (window.innerWidth > 900) closeSidebar();
});

// Auto-dismiss alerts after 4 seconds
document.querySelectorAll('.alert').forEach(function(a) {
  setTimeout(function() {
    a.style.transition = 'opacity .4s';
    a.style.opacity = '0';
    setTimeout(function() { a.remove(); }, 400);
  }, 4000);
});
</script>
