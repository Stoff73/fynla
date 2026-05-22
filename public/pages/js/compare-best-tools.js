/**
 * compare-best-tools.js
 * Page-specific interactions for /compare/best-financial-planning-tools-uk
 *
 * Handles:
 *   - Demo lightbox modal (open/close, persona selection, API login)
 *
 * Shared nav/menu wiring lives in site.js.
 * No Vue, React, or Alpine.js — vanilla JS only.
 */
(function () {
  'use strict';

  /* ----------------------------------------------------------------
     UTILITY — debounce
     ---------------------------------------------------------------- */
  function debounce(fn, ms) {
    var timer;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(null, args); }, ms);
    };
  }

  /* ----------------------------------------------------------------
     INIT
     ---------------------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    wireDemoModal();
  });

  /* ----------------------------------------------------------------
     DEMO LIGHTBOX
     Opens when any .open-demo-modal element is clicked.
     POSTs to /api/preview/login/{personaId} (api/* excluded from CSRF)
     and redirects to /dashboard on success, storing the Sanctum token
     in sessionStorage so Vue router auth guard passes.
     ---------------------------------------------------------------- */
  function wireDemoModal() {
    var modal    = document.getElementById('demo-modal');
    var backdrop = document.getElementById('demo-modal-backdrop');
    var closeBtn = document.getElementById('demo-modal-close');
    var status   = document.getElementById('demo-modal-status');
    if (!modal) return;

    /* Make all persona cards the same height so columns look balanced.
       Only equalise at desktop — mobile uses a stacked single-column layout
       where natural height is correct.
       Runtime inline style is intentional — the value is dynamically computed
       and cannot be expressed as a static CSS class. */
    function equalizeCards() {
      if (window.innerWidth < 900) {
        modal.querySelectorAll('.demo-persona-card').forEach(function (c) {
          c.style.minHeight = '';
        });
        return;
      }
      var cards = modal.querySelectorAll('.demo-persona-card');
      if (!cards.length) return;
      /* Reset first so we measure real content height */
      cards.forEach(function (c) { c.style.minHeight = ''; });
      var maxH = 0;
      cards.forEach(function (c) { maxH = Math.max(maxH, c.offsetHeight); });
      if (maxH > 0) {
        cards.forEach(function (c) { c.style.minHeight = maxH + 'px'; });
      }
    }

    function openModal() {
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      if (closeBtn) closeBtn.focus();
      /* Equalise after the modal is visible so offsetHeight is accurate */
      requestAnimationFrame(equalizeCards);
    }

    function closeModal() {
      modal.hidden = true;
      document.body.style.overflow = '';
      if (status) status.textContent = '';
    }

    /* Re-equalise on resize so mobile/desktop transitions are correct */
    window.addEventListener('resize', debounce(function () {
      if (!modal.hidden) equalizeCards();
    }, 150));

    /* Open triggers */
    document.querySelectorAll('.open-demo-modal').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
      });
    });

    /* Close triggers */
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    /* Persona selection */
    modal.querySelectorAll('.demo-persona-card').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var personaId = btn.getAttribute('data-persona');
        if (!personaId) return;

        /* Disable all cards while logging in */
        modal.querySelectorAll('.demo-persona-card').forEach(function (b) {
          b.disabled = true;
        });
        if (status) status.textContent = 'Loading your demo…';

        fetch('/api/preview/login/' + personaId, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
          credentials: 'same-origin'
        })
          .then(function (res) {
            if (!res.ok) throw new Error('Login failed');
            return res.json();
          })
          .then(function (data) {
            /* Store token so Vue router auth guard finds it on /dashboard */
            if (data.token) sessionStorage.setItem('auth_token', data.token);
            window.location.href = '/dashboard';
          })
          .catch(function () {
            if (status) status.textContent = 'Something went wrong — please try again.';
            modal.querySelectorAll('.demo-persona-card').forEach(function (b) {
              b.disabled = false;
            });
          });
      });
    });
  }

}());
