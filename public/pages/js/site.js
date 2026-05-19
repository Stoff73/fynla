/**
 * Fynla public pages — shared interactive wiring
 *
 * Nav and footer are rendered by PHP includes — this file handles
 * interactive behaviour only. Never injects structural HTML.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    setActiveNavLink();
    wireMobileMenu();
    wireDesktopMenus();
  });

  /* ----------------------------------------------------------------
     Active nav link
     ---------------------------------------------------------------- */
  function setActiveNavLink() {
    var path = window.location.pathname;
    document.querySelectorAll('[data-nav-link]').forEach(function (el) {
      var link = el.getAttribute('data-nav-link');
      if (link === path || (link !== '/' && path.startsWith(link))) {
        el.classList.add('is-active');
        el.setAttribute('aria-current', 'page');
      }
    });
  }

  /* ----------------------------------------------------------------
     Mobile hamburger + accordion panels
     ---------------------------------------------------------------- */
  function wireMobileMenu() {
    var btn  = document.getElementById('hamburger-btn');
    var menu = document.getElementById('mobile-menu');
    if (!btn || !menu) return;

    btn.addEventListener('click', function () {
      var expanded = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!expanded));
      menu.hidden = expanded;
    });

    document.querySelectorAll('.mobile-accordion__trigger').forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        var panelId  = trigger.getAttribute('aria-controls');
        var panel    = document.getElementById(panelId);
        var expanded = trigger.getAttribute('aria-expanded') === 'true';
        var chevron  = trigger.querySelector('.mobile-accordion__chevron');
        trigger.setAttribute('aria-expanded', String(!expanded));
        panel.hidden = expanded;
        if (chevron) chevron.style.transform = expanded ? '' : 'rotate(180deg)';
      });
    });
  }

  /* ----------------------------------------------------------------
     Mega-menu grid item height equalisation
     All .mega-menu__item elements inside a panel are measured and
     given a uniform min-height so rows look balanced even when
     sub-text lengths differ. Called once per open() — reset on close
     so the natural height is re-measured next time.
     ---------------------------------------------------------------- */
  function equalizeGridItems(panel) {
    var items = Array.prototype.slice.call(panel.querySelectorAll('.mega-menu__item'));
    if (!items.length) return;
    items.forEach(function (el) { el.style.minHeight = ''; });
    var max = 0;
    items.forEach(function (el) { max = Math.max(max, el.offsetHeight); });
    if (max > 0) items.forEach(function (el) { el.style.minHeight = max + 'px'; });
  }

  /* ----------------------------------------------------------------
     Desktop mega menus (hover + keyboard)
     ---------------------------------------------------------------- */
  function wireDesktopMenus() {
    var closeTimer;
    var wrappers = Array.prototype.slice.call(document.querySelectorAll('[data-dropdown]'));

    /* Close every open panel except the one passed as `except`.
       Called both when opening a new panel (close the others) and
       when the mouse leaves the nav entirely (close all, except = none).
       Bug this fixes: the old code shared a single closeTimer across all
       dropdowns. Moving from menu A to menu B would cancel A's close timer
       but never explicitly close A — both menus stayed open simultaneously.
       Now, opening B always closes A first via closeAll(). */
    function closeAll(except) {
      wrappers.forEach(function (w) {
        if (w === except) return;
        var p = w.querySelector('.mega-menu');
        var t = w.querySelector('[data-dropdown-trigger]');
        var c = t ? t.querySelector('.nav-link__chevron') : null;
        if (p) p.hidden = true;
        if (t) t.setAttribute('aria-expanded', 'false');
        if (c) c.style.transform = '';
      });
    }

    wrappers.forEach(function (wrapper) {
      var key     = wrapper.getAttribute('data-dropdown');
      var trigger = wrapper.querySelector('[data-dropdown-trigger="' + key + '"]');
      var panel   = wrapper.querySelector('.mega-menu');
      if (!trigger || !panel) return;

      var chevron = trigger.querySelector('.nav-link__chevron');

      function open() {
        closeAll(wrapper); /* close all other panels before opening this one */
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
        /* Equalise grid item heights after the panel is visible so offsetHeight
           is accurate. requestAnimationFrame ensures layout has settled. */
        requestAnimationFrame(function () { equalizeGridItems(panel); });
      }
      function close() {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (chevron) chevron.style.transform = '';
      }

      wrapper.addEventListener('mouseenter', function () {
        clearTimeout(closeTimer);
        open();
      });
      wrapper.addEventListener('mouseleave', function () {
        /* Close ALL panels after the grace delay, not just this one.
           Any pending open from another wrapper would have already fired
           its mouseenter (which calls open() → closeAll) before this fires. */
        closeTimer = setTimeout(function () { closeAll(); }, 150);
      });

      trigger.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          panel.hidden ? open() : closeAll();
        }
        if (e.key === 'Escape') closeAll();
      });

      wrapper.addEventListener('focusout', function (e) {
        if (!wrapper.contains(e.relatedTarget)) closeAll();
      });
    });
  }

})();
