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

/* ====================================================================
   MODULE INITIALISERS
   Each initialiser guards itself with a data-* presence check so it
   only runs when the corresponding module is on the page.
   ==================================================================== */

/* ── FAQ ACCORDION ─────────────────────────────────────────────────── */
(function () {
  'use strict';

  var items = document.querySelectorAll('[data-faq-item]');
  if (!items.length) return;

  items.forEach(function (item) {
    var btn    = item.querySelector('.faq__toggle');
    var answer = item.querySelector('.faq__answer');
    if (!btn || !answer) return;

    btn.addEventListener('click', function () {
      var isOpen = btn.getAttribute('aria-expanded') === 'true';
      /* Close all other items in the same list first */
      var siblings = item.parentElement.querySelectorAll('[data-faq-item]');
      siblings.forEach(function (sib) {
        if (sib === item) return;
        var sibBtn    = sib.querySelector('.faq__toggle');
        var sibAnswer = sib.querySelector('.faq__answer');
        if (sibBtn)    sibBtn.setAttribute('aria-expanded', 'false');
        if (sibAnswer) sibAnswer.hidden = true;
      });
      btn.setAttribute('aria-expanded', String(!isOpen));
      answer.hidden = isOpen;
    });
  });
}());

/* ── REVIEW CAROUSEL ────────────────────────────────────────────────── */
/**
 * initReviewCarousel(container)
 *
 * Initialises one review carousel. `container` is the element with
 * [data-module="review-carousel"]. Review data is read from the
 * [data-reviews] JSON attribute set by the PHP partial.
 *
 * Called automatically for every [data-module="review-carousel"] on the
 * page. Page-specific JS (e.g. how-it-works.js) no longer defines this —
 * removing it from that file stops the duplication.
 */
function initReviewCarousel(container) {
  'use strict';

  if (!container) return;

  var reviews;
  try {
    reviews = JSON.parse(container.getAttribute('data-reviews') || '[]');
  } catch (e) {
    return;
  }
  if (!reviews.length) return;

  var desktopTrack = container.querySelector('.review-carousel__track-desktop');
  var mobileTrack  = container.querySelector('.review-carousel__track-mobile');
  if (!desktopTrack || !mobileTrack) return;

  /* Navigation elements are siblings of the wrapper, inside __inner */
  var section   = container.closest('.review-carousel');
  var prevBtn   = section ? section.querySelector('.review-carousel__arrow--prev') : null;
  var nextBtn   = section ? section.querySelector('.review-carousel__arrow--next') : null;
  var dotsEl    = section ? section.querySelector('.review-carousel__dots')         : null;

  var PER_PAGE     = 3;
  var desktopPages = [];
  var desktopPage  = 0;
  var mobilePage   = 0;
  var isMobile     = false;
  var autoTimer    = null;

  /* Build desktop page groups */
  for (var i = 0; i < reviews.length; i += PER_PAGE) {
    desktopPages.push(reviews.slice(i, i + PER_PAGE));
  }

  /* ---- HTML builders ---- */
  function starHtml() {
    var s = '';
    for (var n = 0; n < 5; n++) {
      s += '<svg class="review-card__star" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">' +
           '<path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
    }
    return s;
  }

  function cardHtml(r) {
    var safeText = String(r.text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    var safeName = String(r.name || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return '<article class="review-card">' +
           '<div class="review-card__stars" aria-label="5 stars">' + starHtml() + '</div>' +
           '<p class="review-card__text">' + safeText + '</p>' +
           '<div class="review-card__footer"><span class="review-card__name">' + safeName + '</span></div>' +
           '</article>';
  }

  /* ---- State helpers ---- */
  function totalPages()  { return isMobile ? reviews.length : desktopPages.length; }
  function currentPage() { return isMobile ? mobilePage : desktopPage; }

  /* ---- Render ---- */
  function applyTransform() {
    desktopTrack.style.transform = 'translateX(-' + (desktopPage * 100) + '%)';
    mobileTrack.style.transform  = 'translateX(-' + (mobilePage  * 100) + '%)';
    updateDots();
  }

  function updateDots() {
    if (!dotsEl) return;
    var frag  = document.createDocumentFragment();
    var total = totalPages();
    var cur   = currentPage();
    for (var d = 0; d < total; d++) {
      var btn = document.createElement('button');
      btn.className = 'review-carousel__dot' + (d === cur ? ' is-active' : '');
      btn.setAttribute('role', 'tab');
      btn.setAttribute('aria-selected', d === cur ? 'true' : 'false');
      btn.setAttribute('aria-label', 'Page ' + (d + 1));
      btn.setAttribute('data-page', String(d));
      btn.addEventListener('click', function () {
        goToPage(parseInt(this.getAttribute('data-page'), 10));
      });
      frag.appendChild(btn);
    }
    dotsEl.innerHTML = '';
    dotsEl.appendChild(frag);
  }

  /* ---- Navigation ---- */
  function goToPage(p) {
    var t = totalPages();
    var c = ((p % t) + t) % t; /* wrap, handling negative */
    if (isMobile) { mobilePage = c; } else { desktopPage = c; }
    applyTransform();
    clearInterval(autoTimer);
    autoTimer = setInterval(function () { goToPage(currentPage() + 1); }, 10000);
  }

  /* ---- Responsive breakpoint check ---- */
  function checkMobile() {
    var was  = isMobile;
    isMobile = window.innerWidth < 768;
    if (was !== isMobile) {
      desktopPage = 0;
      mobilePage  = 0;
      applyTransform();
    }
  }

  /* ---- Build track HTML ---- */
  desktopTrack.innerHTML = desktopPages.map(function (page) {
    return '<div class="review-carousel__page-desktop">' + page.map(cardHtml).join('') + '</div>';
  }).join('');

  mobileTrack.innerHTML = reviews.map(function (r) {
    return '<div class="review-carousel__slide-mobile">' + cardHtml(r) + '</div>';
  }).join('');

  /* ---- Initial render ---- */
  checkMobile();
  applyTransform();

  /* ---- Auto-advance ---- */
  autoTimer = setInterval(function () { goToPage(currentPage() + 1); }, 10000);

  /* ---- Button wiring ---- */
  if (prevBtn) prevBtn.addEventListener('click', function () { goToPage(currentPage() - 1); });
  if (nextBtn) nextBtn.addEventListener('click', function () { goToPage(currentPage() + 1); });

  /* ---- Debounced resize ---- */
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(checkMobile, 150);
  });
}

/* Auto-init all carousels on the page */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-module="review-carousel"]').forEach(function (container) {
    initReviewCarousel(container);
  });
});

/* ============================================================
   Demo persona login — [data-demo-persona]
   ============================================================
   Any <a data-demo-persona="slug"> intercepts its click, POSTs
   to /api/preview/login/{slug}, and redirects to /dashboard.
   The href="/register" remains intact as the no-JS fallback.
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {
  var demoLinks = document.querySelectorAll('[data-demo-persona]');
  if (!demoLinks.length) return;

  demoLinks.forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var personaId = link.getAttribute('data-demo-persona');
      var originalText = link.textContent;

      link.textContent = 'Loading demo…';
      link.setAttribute('aria-disabled', 'true');

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
          if (!data.token) throw new Error('No token in response');
          /* Store the Sanctum token exactly where the Vue app expects it */
          sessionStorage.setItem('auth_token', data.token);
          window.location.href = '/dashboard';
        })
        .catch(function () {
          /* Fallback to register if the preview API is unavailable */
          link.textContent = originalText;
          link.removeAttribute('aria-disabled');
          window.location.href = link.getAttribute('href');
        });
    });
  });
});
