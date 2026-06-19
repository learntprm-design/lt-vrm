/* LT-VRM — UI behaviours (vanilla JS, no dependencies) */
(function () {
  'use strict';

  /* Animated KPI counters */
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0;
    var dur = 700, start = null;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { el.textContent = target.toLocaleString(); return; }
    function tick(ts) {
      if (!start) start = ts;
      var p = Math.min(1, (ts - start) / dur);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString();
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  });

  /* Meter bars animate to their width */
  requestAnimationFrame(function () {
    document.querySelectorAll('.meter > span[data-w]').forEach(function (el) {
      el.style.width = el.getAttribute('data-w') + '%';
    });
  });

  /* Confirm-before-submit */
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  /* Modals */
  document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var m = document.getElementById(btn.getAttribute('data-open-modal'));
      if (m) m.classList.add('open');
    });
  });
  document.querySelectorAll('.modal-back').forEach(function (back) {
    back.addEventListener('click', function (e) { if (e.target === back) back.classList.remove('open'); });
    var x = back.querySelector('[data-close-modal]');
    if (x) x.addEventListener('click', function () { back.classList.remove('open'); });
  });

  /* Auto-dismiss flash messages */
  document.querySelectorAll('.flash[data-auto]').forEach(function (f) {
    setTimeout(function () {
      f.style.transition = 'opacity .5s'; f.style.opacity = '0';
      setTimeout(function () { f.remove(); }, 550);
    }, 4500);
  });

  /* Toast helper (used by inline scripts) */
  window.toast = function (msg) {
    var zone = document.querySelector('.toast-zone');
    if (!zone) { zone = document.createElement('div'); zone.className = 'toast-zone'; document.body.appendChild(zone); }
    var t = document.createElement('div'); t.className = 'toast'; t.textContent = msg;
    zone.appendChild(t);
    setTimeout(function () { t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 400); }, 3500);
  };

  /* Table quick-filter (client side, current page) */
  document.querySelectorAll('[data-table-filter]').forEach(function (inp) {
    var table = document.getElementById(inp.getAttribute('data-table-filter'));
    if (!table) return;
    inp.addEventListener('input', function () {
      var v = inp.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(v) > -1 ? '' : 'none';
      });
    });
  });

  /* Hints: only one popover open at a time; close on outside click/Escape */
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.hint[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
    var hit = e.target.closest('details.hint');
    if (hit) {
      document.querySelectorAll('details.hint[open]').forEach(function (d) {
        if (d !== hit) d.removeAttribute('open');
      });
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('details.hint[open], details.journey[open]').forEach(function (d) {
        d.removeAttribute('open');
      });
    }
  });

  /* Journey: auto-open once for brand-new users (until first manual open) */
  var j = document.getElementById('journey');
  if (j) {
    try {
      if (!localStorage.getItem('va360_journey_seen')) j.setAttribute('open', '');
      j.querySelector('summary').addEventListener('click', function () {
        localStorage.setItem('va360_journey_seen', '1');
      });
    } catch (err) { /* private mode — ignore */ }
  }

  /* Select-all checkbox */
  document.querySelectorAll('[data-check-all]').forEach(function (master) {
    master.addEventListener('change', function () {
      document.querySelectorAll(master.getAttribute('data-check-all')).forEach(function (c) {
        c.checked = master.checked;
      });
    });
  });
})();
