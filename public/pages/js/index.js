/**
 * Fynla landing page — page-specific interactions
 *
 * Handles: review carousel, video toggle, Fyn accordion,
 * smooth scroll (hero sub-links), and latest insights API fetch.
 *
 * Shared nav/menu wiring lives in site.js.
 * Never injects structural HTML outside of intentional placeholders
 * (carousel tracks, insights containers) that exist in the PHP source.
 *
 * Performance notes:
 * - Nav height is cached at startup and on resize (avoids forced reflow in scroll handler)
 * - Resize handler is debounced to reduce layout-thrash during window resize
 * - Insights API swap uses opacity fade to reduce CLS impact
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
     NAV HEIGHT CACHE — read once, update on resize (avoids forced
     reflow inside scroll animation frames)
     ---------------------------------------------------------------- */
  var navHeight = 0;
  function refreshNavHeight() {
    var nav = document.querySelector('.site-header');
    navHeight = nav ? nav.offsetHeight : 0;
  }

  /* ----------------------------------------------------------------
     INIT
     ---------------------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', function () {
    refreshNavHeight();
    wireCarousel();
    wireVideo();
    wireAccordion();
    wireSmoothScroll();
    fetchInsights();
    wireDemoModal();
  });

  window.addEventListener('resize', debounce(refreshNavHeight, 150));

  /* ----------------------------------------------------------------
     REVIEW CAROUSEL
     ---------------------------------------------------------------- */
  function wireCarousel() {
    var desktopTrack = document.getElementById('carousel-desktop-track');
    var mobileTrack  = document.getElementById('carousel-mobile-track');
    if (!desktopTrack || !mobileTrack) return;

    var reviews = [
      { name: 'Stephen D.', text: '"I found the dashboard screens interesting, but the chat agent stole the show. Absolutely incredible. I spent an hour or so with it on Friday night and I left with the information I needed — very technical analysis of workplace pension options broken down in a very easy to understand way."' },
      { name: 'Mia R.',     text: '"It was so easy to see all my finances in one place. I had all current and savings accounts in different locations and had no idea what to do with my money. Fyn was really helpful in getting my information into Fynla, and then I had clear next steps on what I needed to do with my money."' },
      { name: 'Anne L.',    text: '"Fynla has been a useful way to get a clearer picture of my finances without it feeling overwhelming. It pulls everything into one place, making it much easier to see where things stand. The recommendations are sensible and practical, giving me clear next steps rather than trying to do everything at once."' },
      { name: 'Neil S.',    text: '"Fynla alerted me that my fixed rate mortgage was expiring and that I could lock in a new rate up to six months before my term ended. I secured a competitive deal, and shortly after, interest rates jumped. I will save £1,400 a year!"' },
      { name: 'Ron B.',     text: '"Finally, everything in one place. Our family\'s assets, liabilities, insurance, and key financial information are all organised and easy to access. Fyn makes the whole experience feel effortless — intuitive, genuinely useful, and asks exactly the right questions. I can\'t imagine going back to spreadsheets."' },
      { name: 'Michael H.', text: '"I was sceptical about another finance app, but Fynla is different. It actually understands UK tax rules — ISA allowances, pension annual allowance, inheritance tax. Everything is calculated correctly."' },
    ];

    var PER_PAGE     = 3;
    var desktopPages = [];
    var desktopPage  = 0;
    var mobilePage   = 0;
    var isMobile     = false;
    var autoTimer    = null;

    for (var i = 0; i < reviews.length; i += PER_PAGE) {
      desktopPages.push(reviews.slice(i, i + PER_PAGE));
    }

    function starHtml() {
      var s = '';
      for (var n = 0; n < 5; n++) {
        s += '<svg class="review-card__star" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">' +
             '<path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
      }
      return s;
    }

    function cardHtml(r) {
      return '<div class="review-card">' +
             '<div class="review-card__stars">' + starHtml() + '</div>' +
             '<p class="review-card__text">' + r.text + '</p>' +
             '<div class="review-card__footer"><span class="review-card__name">' + r.name + '</span></div>' +
             '</div>';
    }

    function totalPages()  { return isMobile ? reviews.length : desktopPages.length; }
    function currentPage() { return isMobile ? mobilePage : desktopPage; }

    function applyTransform() {
      desktopTrack.style.transform = 'translateX(-' + (desktopPage * 100) + '%)';
      mobileTrack.style.transform  = 'translateX(-' + (mobilePage  * 100) + '%)';
      updateDots();
    }

    function updateDots() {
      var dotsEl = document.getElementById('carousel-dots');
      if (!dotsEl) return;
      /* Batch: build fragment first, then single DOM write */
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

    function goToPage(p) {
      var t = totalPages();
      var c = (p + t) % t;
      if (isMobile) { mobilePage = c; } else { desktopPage = c; }
      applyTransform();
      clearInterval(autoTimer);
      autoTimer = setInterval(function () { goToPage(currentPage() + 1); }, 10000);
    }

    /* Read window.innerWidth once per debounced resize — not inside animation frames */
    function checkMobile() {
      var was = isMobile;
      isMobile = window.innerWidth < 768;
      if (was !== isMobile) { desktopPage = 0; mobilePage = 0; applyTransform(); }
    }

    desktopTrack.innerHTML = desktopPages.map(function (page) {
      return '<div class="review-carousel__page-desktop">' + page.map(cardHtml).join('') + '</div>';
    }).join('');

    mobileTrack.innerHTML = reviews.map(function (r) {
      return '<div class="review-carousel__slide-mobile">' + cardHtml(r) + '</div>';
    }).join('');

    checkMobile();
    applyTransform();
    autoTimer = setInterval(function () { goToPage(currentPage() + 1); }, 10000);

    var prevBtn = document.getElementById('carousel-prev');
    var nextBtn = document.getElementById('carousel-next');
    if (prevBtn) prevBtn.addEventListener('click', function () { goToPage(currentPage() - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { goToPage(currentPage() + 1); });

    /* Debounced resize — avoids layout thrash on every pixel of resize */
    window.addEventListener('resize', debounce(checkMobile, 150));
  }

  /* ----------------------------------------------------------------
     VIDEO TOGGLE
     ---------------------------------------------------------------- */
  function wireVideo() {
    var wrap    = document.getElementById('video-wrap');
    var video   = document.getElementById('product-video');
    var overlay = document.getElementById('video-overlay');
    if (!wrap || !video) return;

    function toggle() {
      if (video.paused) {
        video.play();
        overlay.hidden = true;
        wrap.setAttribute('aria-pressed', 'true');
        wrap.setAttribute('aria-label', 'Pause Fynla dashboard product tour video');
      } else {
        video.pause();
        overlay.hidden = false;
        wrap.setAttribute('aria-pressed', 'false');
        wrap.setAttribute('aria-label', 'Play Fynla dashboard product tour video');
      }
    }

    wrap.addEventListener('click', toggle);
    wrap.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    });
    video.addEventListener('ended', function () {
      overlay.hidden = false;
      wrap.setAttribute('aria-pressed', 'false');
      wrap.setAttribute('aria-label', 'Play Fynla dashboard product tour video');
    });
  }

  /* ----------------------------------------------------------------
     FYN ACCORDION
     ---------------------------------------------------------------- */
  function wireAccordion() {
    var btn   = document.getElementById('fyn-accordion-btn');
    var panel = document.getElementById('fyn-accordion-panel');
    if (!btn || !panel) return;
    var chevron = btn.querySelector('.fyn-accordion__chevron');
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!open));
      panel.classList.toggle('is-open', !open);
      if (chevron) chevron.style.transform = open ? '' : 'rotate(180deg)';
    });
  }

  /* ----------------------------------------------------------------
     SMOOTH SCROLL (hero sub-links)
     Uses the module-level navHeight cache — no layout reads mid-scroll
     ---------------------------------------------------------------- */
  function wireSmoothScroll() {
    function smoothScrollTo(targetId) {
      var el = document.getElementById(targetId);
      if (!el) return;
      /* Disable CSS scroll-behavior:smooth for the duration of our JS animation.
         If left enabled, the browser's native smooth-scroll buffers our scrollTo()
         calls, producing a noticeable delay before movement starts — then the
         buffered calls fire all at once, making the scroll feel jerky/rushed. */
      document.documentElement.style.scrollBehavior = 'auto';
      /* Use cached navHeight — avoids forced reflow inside rAF loop */
      var target    = el.getBoundingClientRect().top + window.scrollY - navHeight;
      var start     = window.scrollY;
      var dist      = target - start;
      var dur       = 1300;
      var startTime = null;
      /* Cubic ease-out: starts immediately at full speed, decelerates smoothly.
         1300ms gives a relaxed, unhurried feel without being sluggish. */
      function ease(t) { return 1 - Math.pow(1 - t, 3); }
      function step(ts) {
        if (!startTime) startTime = ts;
        var p = Math.min((ts - startTime) / dur, 1);
        window.scrollTo(0, start + dist * ease(p));
        if (p < 1) {
          requestAnimationFrame(step);
        } else {
          /* Restore CSS smooth-scroll once our animation is complete */
          document.documentElement.style.scrollBehavior = '';
        }
      }
      requestAnimationFrame(step);
    }

    var meetFynLink   = document.getElementById('scroll-meet-fyn');
    var dashboardLink = document.getElementById('scroll-dashboard');
    if (meetFynLink)   meetFynLink.addEventListener('click',  function (e) { e.preventDefault(); smoothScrollTo('meet-fyn'); });
    if (dashboardLink) dashboardLink.addEventListener('click', function (e) { e.preventDefault(); smoothScrollTo('dashboard'); });
  }

  /* ----------------------------------------------------------------
     LATEST INSIGHTS — fetch from API, fall back to static grid
     CLS mitigation: fade static out rather than instant-hide so the
     section height doesn't abruptly collapse when the dynamic layout
     (which is taller) takes over.
     ---------------------------------------------------------------- */
  function fetchInsights() {
    var dynEl    = document.getElementById('insights-dynamic');
    var staticEl = document.getElementById('insights-static');

    fetch('/api/insights/featured')
      .then(function (res) {
        if (!res.ok) throw new Error('API unavailable');
        return res.json();
      })
      .then(function (data) {
        /* API returns { data: { featured: {...}|null, supporting: [...] } } */
        var payload    = (data && data.data) ? data.data : data;
        var featured   = payload.featured   || null;
        var supporting = payload.supporting || [];
        /* If no explicitly-featured article, promote the first supporting one */
        if (!featured && supporting.length) {
          featured   = supporting[0];
          supporting = supporting.slice(1);
        }
        if (!featured) throw new Error('No featured insight');

        var featuredEl  = document.getElementById('insight-featured');
        var featuredImg = document.getElementById('insight-featured-img');
        var featuredTit = document.getElementById('insight-featured-title');
        var featuredSum = document.getElementById('insight-featured-summary');

        featuredEl.href = '/insights/' + featured.slug;
        featuredEl.setAttribute('aria-label', featured.title);
        if (featured.image_card) {
          /* Clear any display:none set by onerror on the empty initial src="" */
          featuredImg.style.display = '';
          featuredImg.src = featured.image_card;
          featuredImg.alt = ''; /* decorative: title already in adjacent h3 */
        }
        featuredTit.textContent = featured.title;
        featuredSum.textContent = featured.summary;

        var supEl = document.getElementById('insights-supporting');
        supEl.innerHTML = supporting.slice(0, 2).map(function (a) {
          return '<a href="/insights/' + a.slug + '" class="insights-support-card">' +
                 '<div class="insights-support-card__thumb">' +
                 (a.image_card
                   ? '<img src="' + a.image_card + '" alt="" width="300" height="200" loading="lazy" onerror="this.parentElement.style.display=\'none\'" />'
                   : '') + /* alt="" decorative: title in adjacent h4; onerror hides thumb container to avoid empty grey box */
                 '</div>' +
                 '<div class="insights-support-card__body">' +
                 '<h4 class="insights-support-card__title">' + a.title + '</h4>' +
                 '</div></a>';
        }).join('');

        /* Show dynamic layout, fade-then-hide static to reduce CLS */
        if (dynEl) dynEl.hidden = false;
        if (staticEl) {
          staticEl.style.transition = 'opacity 0.25s ease';
          staticEl.style.opacity    = '0';
          setTimeout(function () { staticEl.hidden = true; }, 280);
        }
      })
      .catch(function () {
        if (dynEl)    dynEl.hidden    = true;
        if (staticEl) { staticEl.hidden = false; staticEl.style.opacity = '1'; }
      });
  }

  /* ----------------------------------------------------------------
     DEMO LIGHTBOX
     Opens when any .open-demo-modal link is clicked.
     POSTs to /api/preview/login/{personaId} (no CSRF — api/* excluded)
     and redirects to /dashboard on success.
     ---------------------------------------------------------------- */
  function wireDemoModal() {
    var modal    = document.getElementById('demo-modal');
    var backdrop = document.getElementById('demo-modal-backdrop');
    var closeBtn = document.getElementById('demo-modal-close');
    var status   = document.getElementById('demo-modal-status');
    if (!modal) return;

    /* Make all persona cards the same height so the three columns look
       balanced. Cards have variable content (different focus-tag counts,
       detail line lengths) so we measure after layout then set min-height.
       Runtime inline style is intentional here — the value is computed
       dynamically and cannot be expressed as a static CSS class. */
    function equalizeCards() {
      /* Only equalise at desktop where cards sit side by side — on mobile
         the single-column stacked layout needs each card at its natural height */
      if (window.innerWidth < 900) {
        modal.querySelectorAll('.demo-persona-card').forEach(function (c) { c.style.minHeight = ''; });
        return;
      }
      var cards = modal.querySelectorAll('.demo-persona-card');
      if (!cards.length) return;
      /* Reset to natural height first so we measure real content height */
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
      /* Equalize after the modal is visible so offsetHeight is accurate */
      requestAnimationFrame(equalizeCards);
    }

    /* Re-equalise (or clear) on resize so mobile→desktop transitions are correct */
    window.addEventListener('resize', debounce(function () {
      if (!modal.hidden) equalizeCards();
    }, 150));

    function closeModal() {
      modal.hidden = true;
      document.body.style.overflow = '';
      if (status) status.textContent = '';
    }

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
        modal.querySelectorAll('.demo-persona-card').forEach(function (b) { b.disabled = true; });
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
          .then(function () {
            window.location.href = '/dashboard';
          })
          .catch(function () {
            if (status) status.textContent = 'Something went wrong — please try again.';
            modal.querySelectorAll('.demo-persona-card').forEach(function (b) { b.disabled = false; });
          });
      });
    });
  }

})();
