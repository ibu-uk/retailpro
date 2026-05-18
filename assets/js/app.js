// ============================================================
// RetailPro ERP — Main JS
// ============================================================

// TOAST
function showToast(title, msg, type = 'success') {
  const icons = { success: '✅', error: '❌', warning: '⚠️' };
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<div class="toast-icon">${icons[type]}</div><div class="toast-text"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;
  document.getElementById('toast-container').appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

// MODAL
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('open');
  }
});

// TABS
function activateTab(el, contentClass) {
  const parent = el.closest('.tabs');
  parent.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  if (contentClass) {
    document.querySelectorAll('.' + contentClass).forEach(c => c.style.display = 'none');
    const target = el.dataset.target;
    if (target) document.getElementById(target).style.display = 'block';
  }
}

// FILTER CHIPS
document.addEventListener('click', e => {
  if (e.target.classList.contains('filter-chip') && !e.target.dataset.noauto) {
    const parent = e.target.parentElement;
    parent.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    e.target.classList.add('active');
  }
});

// CONFIRM DELETE
function confirmDelete(msg, url) {
  if (confirm(msg || 'Are you sure?')) {
    window.location.href = url;
  }
}

// AJAX helper
async function apiFetch(url, data = null) {
  const opts = { headers: { 'Content-Type': 'application/json' } };
  if (data) { opts.method = 'POST'; opts.body = JSON.stringify(data); }
  const r = await fetch(url, opts);
  return r.json();
}

// Auto-dismiss alerts
document.querySelectorAll('.alert[data-dismiss]').forEach(el => {
  setTimeout(() => el.remove(), parseInt(el.dataset.dismiss) || 4000);
});

// Flash messages via URL param
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('success')) showToast('Success', decodeURIComponent(urlParams.get('success')), 'success');
if (urlParams.get('error'))   showToast('Error',   decodeURIComponent(urlParams.get('error')),   'error');
if (urlParams.get('warning')) showToast('Warning', decodeURIComponent(urlParams.get('warning')), 'warning');
