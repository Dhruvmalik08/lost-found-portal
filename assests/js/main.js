/* ═══════════════════════════════════════════════════════════
   Lost & Found Portal — Global JavaScript
   assets/js/main.js
   ═══════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function () {

  // ── 1. PHOTO PREVIEW ──────────────────────────────────────
  // Automatically wires up any file input with id="photoInput"
  // to show a preview in #preview-wrap > #preview-img
  const photoInput = document.getElementById('photoInput');
  if (photoInput) {
    photoInput.addEventListener('change', function () {
      previewPhoto(this);
    });
  }

  // ── 2. AUTO-DISMISS ALERTS ────────────────────────────────
  // Success alerts disappear after 4 seconds
  const alerts = document.querySelectorAll('.alert-success');
  alerts.forEach(function (alert) {
    setTimeout(function () {
      alert.style.transition = 'opacity 0.5s';
      alert.style.opacity    = '0';
      setTimeout(function () { alert.remove(); }, 500);
    }, 4000);
  });

  // ── 3. CONFIRM DIALOGS (data-confirm attribute) ───────────
  // Add data-confirm="Your message" to any button/link for
  // an automatic confirmation prompt before the action fires.
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(this.dataset.confirm)) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  });

  // ── 4. ACTIVE NAV HIGHLIGHT (fallback) ───────────────────
  // If header.php active class is missing, highlight based on URL
  const currentPath = window.location.pathname.split('/').pop();
  document.querySelectorAll('.nav a').forEach(function (link) {
    const linkPath = link.getAttribute('href').split('/').pop();
    if (linkPath && linkPath === currentPath && !link.classList.contains('admin-link')) {
      link.classList.add('active');
    }
  });

  // ── 5. TABLE SEARCH ───────────────────────────────────────
  // Wire up any input with id="tableSearch" to live-filter
  // rows in the nearest table or #searchTarget
  const tableSearch = document.getElementById('tableSearch');
  if (tableSearch) {
    tableSearch.addEventListener('input', function () {
      const query  = this.value.toLowerCase();
      const target = document.getElementById('searchTarget') || document.querySelector('table tbody');
      if (!target) return;
      target.querySelectorAll('tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
      });
    });
  }

  // ── 6. STATUS SELECT AUTO-SUBMIT ─────────────────────────
  // Any <select class="auto-submit"> submits its parent form on change.
  document.querySelectorAll('select.auto-submit').forEach(function (sel) {
    sel.addEventListener('change', function () {
      this.closest('form').submit();
    });
  });

});

// ── PHOTO PREVIEW (global, callable from inline onchange) ──
function previewPhoto(input) {
  const wrap = document.getElementById('preview-wrap');
  const img  = document.getElementById('preview-img');
  const name = document.getElementById('preview-name');

  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function (e) {
      if (img)  { img.src = e.target.result; }
      if (name) { name.textContent = input.files[0].name; }
      if (wrap) { wrap.style.display = 'block'; }
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// ── SWITCH TAB (used on login/signup page) ─────────────────
function switchTab(tab) {
  const loginForm  = document.getElementById('loginForm');
  const signupForm = document.getElementById('signupForm');
  const tabs       = document.querySelectorAll('.tab');

  if (loginForm)  loginForm.classList.toggle('visible',  tab === 'login');
  if (signupForm) signupForm.classList.toggle('visible', tab === 'signup');

  tabs.forEach(function (t, i) {
    t.classList.toggle('active',
      (i === 0 && tab === 'login') || (i === 1 && tab === 'signup')
    );
  });
}
