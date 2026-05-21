<?php
// Close the #content and #main divs opened in header.php
?>
  </div><!-- /content -->
</div><!-- /main -->

<!-- ══════════════════════════════════════════
     CUSTOM CONFIRM DIALOG
     Usage: appConfirm({ title, message, detail, type, confirmText, cancelText, onConfirm })
     Or:    appConfirm('Simple message', callback)
     Replaces ALL native confirm() calls
══════════════════════════════════════════ -->
<div id="app-confirm-backdrop" style="
  position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;
  display:none;align-items:center;justify-content:center;
  backdrop-filter:blur(3px);padding:16px;
">
  <div id="app-confirm-box" style="
    background:var(--bg2);border:1px solid var(--border2);border-radius:16px;
    width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.2);
    animation:confirmPop .2s cubic-bezier(.34,1.56,.64,1);
    overflow:hidden;
  ">
    <!-- Colored top bar -->
    <div id="app-confirm-bar" style="height:4px;width:100%"></div>

    <div style="padding:24px 24px 8px">
      <!-- Icon + Title -->
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
        <div id="app-confirm-icon" style="
          width:42px;height:42px;border-radius:12px;display:flex;
          align-items:center;justify-content:center;font-size:22px;flex-shrink:0;
        "></div>
        <div id="app-confirm-title" style="font-size:16px;font-weight:700;color:var(--text)"></div>
      </div>
      <!-- Message -->
      <div id="app-confirm-msg" style="font-size:13px;color:var(--text2);line-height:1.6;margin-left:54px"></div>
      <!-- Detail (smaller) -->
      <div id="app-confirm-detail" style="
        font-size:12px;color:var(--text3);margin-left:54px;margin-top:6px;
        padding:8px 10px;background:var(--bg3);border-radius:8px;border-left:3px solid var(--border2);
        display:none;
      "></div>
    </div>

    <!-- Buttons -->
    <div style="padding:16px 24px 20px;display:flex;justify-content:flex-end;gap:8px">
      <button id="app-confirm-cancel" onclick="appConfirmClose(false)" style="
        padding:9px 20px;border-radius:10px;border:1px solid var(--border2);
        background:var(--bg3);color:var(--text2);font-size:13px;font-weight:500;
        cursor:pointer;font-family:var(--font);transition:all .15s;
      " onmouseover="this.style.background='var(--bg4)'" onmouseout="this.style.background='var(--bg3)'">
        Cancel
      </button>
      <button id="app-confirm-ok" onclick="appConfirmClose(true)" style="
        padding:9px 20px;border-radius:10px;border:none;
        font-size:13px;font-weight:600;cursor:pointer;
        font-family:var(--font);transition:all .15s;color:#fff;
      ">
        Confirm
      </button>
    </div>
  </div>
</div>

<style>
@keyframes confirmPop {
  from { opacity:0; transform:scale(.88) translateY(12px); }
  to   { opacity:1; transform:none; }
}
</style>

<script>
// ══════════════════════════════════
// Custom Confirm System
// ══════════════════════════════════
let _confirmCallback = null;
let _pendingForm     = null;

const CONFIRM_TYPES = {
  danger: {
    bar:   '#ef4444',
    icon:  '🗑️',
    iconBg:'rgba(239,68,68,.1)',
    btn:   '#ef4444',
    btnHov:'#dc2626',
  },
  warning: {
    bar:   '#f59e0b',
    icon:  '⚠️',
    iconBg:'rgba(245,158,11,.1)',
    btn:   '#f59e0b',
    btnHov:'#d97706',
  },
  info: {
    bar:   '#3b82f6',
    icon:  'ℹ️',
    iconBg:'rgba(59,130,246,.1)',
    btn:   '#3b82f6',
    btnHov:'#2563eb',
  },
  success: {
    bar:   '#22c55e',
    icon:  '✅',
    iconBg:'rgba(34,197,94,.1)',
    btn:   '#22c55e',
    btnHov:'#16a34a',
  },
  refund: {
    bar:   '#ec4899',
    icon:  '↩️',
    iconBg:'rgba(236,72,153,.1)',
    btn:   '#ec4899',
    btnHov:'#db2777',
  },
};

function appConfirm(options, simpleCallback) {
  // Support simple string call: appConfirm('Are you sure?', fn)
  if (typeof options === 'string') {
    options = { message: options, onConfirm: simpleCallback };
  }

  const type     = CONFIRM_TYPES[options.type || 'danger'];
  const title    = options.title    || 'Are you sure?';
  const msg      = options.message  || '';
  const detail   = options.detail   || '';
  const confirmT = options.confirmText || 'Yes, Confirm';
  const cancelT  = options.cancelText  || 'Cancel';

  document.getElementById('app-confirm-bar').style.background   = type.bar;
  document.getElementById('app-confirm-icon').style.background  = type.iconBg;
  document.getElementById('app-confirm-icon').textContent       = options.icon || type.icon;
  document.getElementById('app-confirm-title').textContent      = title;
  document.getElementById('app-confirm-msg').textContent        = msg;

  const detailEl = document.getElementById('app-confirm-detail');
  if (detail) {
    detailEl.textContent   = detail;
    detailEl.style.display = 'block';
    detailEl.style.borderLeftColor = type.bar;
  } else {
    detailEl.style.display = 'none';
  }

  const okBtn = document.getElementById('app-confirm-ok');
  okBtn.textContent        = confirmT;
  okBtn.style.background   = type.btn;
  okBtn.onmouseover = function() { this.style.background = type.btnHov; };
  okBtn.onmouseout  = function() { this.style.background = type.btn; };

  document.getElementById('app-confirm-cancel').textContent = cancelT;

  _confirmCallback = options.onConfirm || null;
  _pendingForm     = options._form     || null;

  const backdrop = document.getElementById('app-confirm-backdrop');
  backdrop.style.display = 'flex';
  setTimeout(function() { document.getElementById('app-confirm-ok').focus(); }, 100);
}

function appConfirmClose(confirmed) {
  document.getElementById('app-confirm-backdrop').style.display = 'none';
  if (confirmed) {
    if (_confirmCallback) _confirmCallback();
    if (_pendingForm)     _pendingForm.submit();
  }
  _confirmCallback = null;
  _pendingForm     = null;
}

// Close on backdrop click
document.getElementById('app-confirm-backdrop').addEventListener('click', function(e) {
  if (e.target === this) appConfirmClose(false);
});

// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    if (document.getElementById('app-confirm-backdrop').style.display === 'flex') {
      appConfirmClose(false);
    }
  }
});

// ══════════════════════════════════
// INTERCEPT ALL FORM onsubmit confirms
// Converts: onsubmit="return confirm('...')"
// Into the custom dialog automatically
// ══════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('form[onsubmit]').forEach(function(form) {
    const attr = form.getAttribute('onsubmit');
    if (!attr || !attr.includes('confirm(')) return;

    // Extract the message from confirm('...')
    const match = attr.match(/confirm\(['"](.+?)['"]\)/);
    const msg   = match ? match[1] : 'Are you sure?';

    // Remove original onsubmit
    form.removeAttribute('onsubmit');

    form.addEventListener('submit', function(e) {
      e.preventDefault();
      e.stopPropagation();

      // Detect type based on message keywords
      let type  = 'danger';
      let title = 'Confirm Action';
      let icon  = null;

      const msgLower = msg.toLowerCase();
      if (msgLower.includes('delete') || msgLower.includes('remove')) {
        type  = 'danger';
        title = 'Delete Confirmation';
        icon  = '🗑️';
      } else if (msgLower.includes('refund')) {
        type  = 'refund';
        title = 'Process Refund';
        icon  = '↩️';
      } else if (msgLower.includes('complete') || msgLower.includes('mark')) {
        type  = 'success';
        title = 'Confirm Action';
        icon  = '✅';
      } else if (msgLower.includes('stock') || msgLower.includes('restore')) {
        type  = 'warning';
        title = 'Stock Warning';
        icon  = '📦';
      } else if (msgLower.includes('toggle') || msgLower.includes('status')) {
        type  = 'info';
        title = 'Change Status';
        icon  = '🔄';
      }

      appConfirm({
        type:        type,
        title:       title,
        message:     msg,
        icon:        icon,
        confirmText: type === 'danger' ? 'Yes, Delete' : 'Yes, Confirm',
        cancelText:  'Cancel',
        _form:       form,
      });
    });
  });

  // Also intercept onclick="return confirm(...)" on links/buttons
  document.querySelectorAll('[onclick]').forEach(function(el) {
    const attr = el.getAttribute('onclick');
    if (!attr || !attr.includes('confirm(')) return;

    const match = attr.match(/confirm\(['"](.+?)['"]\)/);
    if (!match) return;
    const msg = match[1];

    // Build new onclick that uses appConfirm
    const newOnclick = attr.replace(
      /return confirm\(['"](.+?)['"]\)/,
      "appConfirmInline(event, '" + msg.replace(/'/g, "\\'") + "')"
    );
    el.setAttribute('onclick', newOnclick);
  });
});

// For inline onclick links (like toggle status links)
function appConfirmInline(event, msg) {
  event.preventDefault();
  const el   = event.currentTarget;
  const href = el.href || null;

  const msgLower = msg.toLowerCase();
  let type  = 'info';
  let title = 'Confirm';
  if (msgLower.includes('delete')) { type = 'danger'; title = 'Delete'; }
  if (msgLower.includes('toggle') || msgLower.includes('status')) { type = 'info'; title = 'Change Status'; }
  if (msgLower.includes('complete')) { type = 'success'; title = 'Mark Complete'; }

  appConfirm({
    type:        type,
    title:       title,
    message:     msg,
    confirmText: 'Yes, Confirm',
    cancelText:  'Cancel',
    onConfirm:   href ? function() { window.location.href = href; } : null,
  });
}
</script>

<?php if (!empty($extra_js)) echo $extra_js; ?>
</body>
</html>
