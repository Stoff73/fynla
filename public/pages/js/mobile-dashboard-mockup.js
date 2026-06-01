(function () {
  'use strict';

  var MAX_VISIBLE = 5;

  // Each category has a deeper pool than MAX_VISIBLE; skipping pulls the next
  // unseen pool item into the visible list.
  var REC_DATA = [
    // 0 — Save tax
    [
      { title: 'Maximise your pension contributions',  meta: 'You can save: £1,400', done: false },
      { title: 'Claim Marriage Allowance',             meta: 'You can save: £252',   done: false },
      { title: 'Use your full ISA allowance',          meta: 'You can save: £748',   done: false },
      { title: 'Move dividends into a tax wrapper',     meta: 'You can save: £310',   done: false },
      { title: 'Salary-sacrifice into your pension',    meta: 'You can save: £620',   done: false },
      { title: 'Claim higher-rate pension relief',      meta: 'You can save: £900',   done: false },
      { title: 'Review your ISA allowance',            meta: 'You can save: £180',   done: true  }
    ],
    // 1 — Retirement
    [
      { title: 'Nominate your pension beneficiary',    meta: 'You can save: £2,100', done: false },
      { title: 'Consolidate your old pensions',        meta: 'You can save: £1,300', done: false },
      { title: 'Increase your monthly contribution',   meta: 'You can save: £1,050', done: false },
      { title: 'Check your State Pension forecast',     meta: 'You can save: £450',   done: false },
      { title: 'Review your retirement income plan',    meta: 'You can save: £700',   done: false },
      { title: 'Confirm your retirement age',          meta: 'You can save: £800',   done: true  }
    ],
    // 2 — Savings
    [
      { title: 'Move cash to a higher rate',           meta: 'You can earn: £540', done: false },
      { title: 'Automate a monthly transfer',          meta: 'You can earn: £180', done: false },
      { title: 'Open a fixed-rate savings bond',        meta: 'You can earn: £320', done: false },
      { title: 'Use a regular saver account',           meta: 'You can earn: £210', done: false },
      { title: 'Sweep spare cash weekly',               meta: 'You can earn: £90',  done: false },
      { title: 'Set your emergency fund target',       meta: 'You can earn: £120', done: true  }
    ]
  ];

  var poolCursor = REC_DATA.map(function () { return 0; });

  var CHECK_SVG   = '<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
  var CHEV_SVG    = '<svg class="md-rec__chevron" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
  var REFRESH_SVG = '<svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';

  var doneCounter = 0;   // monotonic — completion order (higher = more recent)

  var recsList  = document.getElementById('md-recs-list');
  var recsCount = document.getElementById('md-recs-count');
  var levelArc  = document.getElementById('md-level-arc');
  var levelNum  = document.getElementById('md-level-num');
  var levelHead = document.getElementById('md-level-heading');
  var levelSub  = document.getElementById('md-level-sub');

  // Visible list per category — up to MAX_VISIBLE recs.
  var visible = REC_DATA.map(function (pool, catIdx) {
    var v = pool.slice(0, MAX_VISIBLE);
    poolCursor[catIdx] = v.length;
    return v;
  });
  var activeCat = 0;
  var fadeInIdx = -1;   // data index of an item that should fade in on next render

  // Parse the £ amount from a meta string ("You can save: £1,400" → 1400).
  function recValue(rec) {
    var m = (rec.meta || '').replace(/[, ]/g, '').match(/£(\d+)/);
    return m ? parseInt(m[1], 10) : 0;
  }

  function buildRecItem(rec, idx) {
    var li = document.createElement('li');
    li.className = 'md-rec' + (rec.done ? ' is-done' : '') + (idx === fadeInIdx ? ' is-entering' : '');
    li.dataset.done = rec.done ? 'true' : 'false';
    li.dataset.idx = idx;
    li.innerHTML =
      '<button type="button" class="md-rec__check-btn" aria-label="' +
        (rec.done ? 'Mark as not done' : 'Mark complete') + '" aria-pressed="' + rec.done + '">' +
        '<span class="md-rec__check" aria-hidden="true">' + CHECK_SVG + '</span>' +
      '</button>' +
      '<a href="#" class="md-rec__action">' +
        '<span class="md-rec__text">' +
          '<span class="md-rec__title">' + rec.title + '</span>' +
          '<span class="md-rec__meta">' + rec.meta + '</span>' +
        '</span>' + CHEV_SVG +
      '</a>' +
      (rec.done
        ? '<button type="button" class="md-rec__skip md-rec__skip--refresh" aria-label="Replace with a new recommendation">' + REFRESH_SVG + '</button>'
        : '<button type="button" class="md-rec__skip" aria-label="Skip this recommendation">Skip</button>');
    return li;
  }

  // Render visible list: pending first (highest value first), completed last.
  function renderRecs(catIdx) {
    if (!recsList) return;
    activeCat = catIdx;
    var data = visible[catIdx] || [];
    var order = data
      .map(function (rec, idx) { return { rec: rec, idx: idx }; })
      .sort(function (a, b) {
        if (a.rec.done !== b.rec.done) return a.rec.done ? 1 : -1;   // pending first
        if (a.rec.done) return (b.rec.doneAt || 0) - (a.rec.doneAt || 0); // completed: most recent first
        return recValue(b.rec) - recValue(a.rec);                    // pending: by value desc
      });
    recsList.innerHTML = '';
    order.forEach(function (o) { recsList.appendChild(buildRecItem(o.rec, o.idx)); });
    updateCount();
    updateLevel();
  }

  function updateCount() {
    if (!recsList || !recsCount) return;
    var total = recsList.querySelectorAll('.md-rec').length;
    var done  = recsList.querySelectorAll('.md-rec.is-done').length;
    recsCount.textContent = done + ' / ' + total;
  }

  // Level progression: L1 needs 3 actions, +2 per level, capped at 10.
  // Completed actions count across EVERY category's visible list.
  function actionsForLevel(level) { return Math.min(10, 3 + (level - 1) * 2); }

  function totalDone() {
    return visible.reduce(function (sum, list) {
      return sum + list.filter(function (r) { return r.done; }).length;
    }, 0);
  }

  var currentLevel = 1;          // tracked so we can detect a level-up
  var percentileEl = document.getElementById('md-percentile');
  var BASE_PCT = 57;             // starting percentile; +4 per level gained

  function updateLevel() {
    var remaining = totalDone();
    var level = 1;
    while (remaining >= actionsForLevel(level)) {
      remaining -= actionsForLevel(level);
      level++;
    }
    var need = actionsForLevel(level);
    var pct  = Math.round((remaining / need) * 100);
    if (levelNum)  levelNum.textContent = level;
    if (levelHead) levelHead.textContent = remaining + ' of ' + need + ' actions complete';
    if (levelSub)  levelSub.innerHTML = 'Complete actions to reach <strong>Level ' + (level + 1) + '</strong>.';
    if (levelArc)  levelArc.style.setProperty('--progress', pct);

    // Percentile rises with level (capped at 99%).
    if (percentileEl) percentileEl.textContent = Math.min(99, BASE_PCT + (level - 1) * 4) + '%';

    // Detect a level-up and celebrate.
    if (level > currentLevel) {
      celebrate(level);
    }
    currentLevel = level;
  }

  // ───────────────────────────────────────────────────────────
  // Level-up celebration — confetti burst + flashing the level number
  // ───────────────────────────────────────────────────────────
  var CONFETTI_COLORS = ['#E83E6D', '#F472B6', '#20B486', '#5854E6', '#E6C9A8', '#FBBF24'];

  function celebrate(level) {
    // Pulse the level pie + number so the change is noticeable.
    var pie = document.querySelector('.md-level__pie');
    if (pie) { pie.classList.remove('is-levelup'); void pie.offsetWidth; pie.classList.add('is-levelup'); }

    // Build a one-shot confetti layer over the phone frame.
    var frame = document.querySelector('.phone-frame');
    if (!frame) return;
    var layer = document.createElement('div');
    layer.className = 'md-confetti';
    var pieces = 80;
    for (var i = 0; i < pieces; i++) {
      var s = document.createElement('span');
      s.className = 'md-confetti__piece';
      var left = Math.round((i / pieces) * 100);
      var color = CONFETTI_COLORS[i % CONFETTI_COLORS.length];
      // Vary delay/duration/drift per piece via inline custom props.
      var delay = (i % 10) * 40;
      var dur = 1400 + (i % 7) * 180;
      var drift = ((i % 11) - 5) * 14;
      var rot = (i % 2 ? 1 : -1) * (180 + (i % 5) * 120);
      s.style.left = left + '%';
      s.style.background = color;
      s.style.animationDelay = delay + 'ms';
      s.style.animationDuration = dur + 'ms';
      s.style.setProperty('--drift', drift + 'px');
      s.style.setProperty('--rot', rot + 'deg');
      if (i % 3 === 0) s.style.borderRadius = '50%';   // mix circles + rectangles
      layer.appendChild(s);
    }
    // Centre "Level X!" banner.
    var banner = document.createElement('div');
    banner.className = 'md-confetti__banner';
    banner.textContent = 'Level ' + level + '!';
    layer.appendChild(banner);

    frame.appendChild(layer);
    setTimeout(function () { layer.remove(); }, 2600);
  }

  // Skip with fade: fade the row out, swap in the next pool item, fade in.
  function skipRec(idx, liEl) {
    if (liEl) liEl.classList.add('is-skipping');
    setTimeout(function () {
      var pool = REC_DATA[activeCat];
      var cursor = poolCursor[activeCat];
      if (cursor < pool.length) {
        visible[activeCat][idx] = pool[cursor];
        poolCursor[activeCat] = cursor + 1;
        fadeInIdx = idx;
      } else {
        visible[activeCat].splice(idx, 1);
        fadeInIdx = -1;
      }
      renderRecs(activeCat);
      fadeInIdx = -1;
    }, 260);
  }

  // Delegated clicks: skip / tick toggle / action link.
  if (recsList) {
    recsList.addEventListener('click', function (e) {
      var skipBtn = e.target.closest('.md-rec__skip');
      if (skipBtn) {
        e.preventDefault();
        var liS = skipBtn.closest('.md-rec');
        if (liS) skipRec(parseInt(liS.dataset.idx, 10), liS);
        return;
      }
      var checkBtn = e.target.closest('.md-rec__check-btn');
      if (checkBtn) {
        var li = checkBtn.closest('.md-rec');
        if (!li) return;
        var vidx = parseInt(li.dataset.idx, 10);
        var r = visible[activeCat][vidx];
        var nowDone = !r.done;
        r.done = nowDone;
        r.doneAt = nowDone ? ++doneCounter : 0;   // stamp completion order

        if (nowDone) {
          // Show the green tick on the row first, hold briefly so the user
          // sees it complete, then fade out and re-order the list.
          li.classList.add('is-done');
          li.querySelector('.md-rec__check-btn').setAttribute('aria-pressed', 'true');
          setTimeout(function () {
            li.classList.add('is-completing');           // fade/collapse
            setTimeout(function () { renderRecs(activeCat); }, 320);
          }, 450);
        } else {
          renderRecs(activeCat);
        }
        return;
      }
      var action = e.target.closest('.md-rec__action');
      if (action) { e.preventDefault(); }
    });
  }

  // ───────────────────────────────────────────────────────────
  // Horizontal accordion — active card expands, dots are alternative nav.
  // Selecting a card re-renders that category's recommendations.
  // ───────────────────────────────────────────────────────────
  var accordion = document.getElementById('md-accordion');
  var carDots   = document.getElementById('md-carousel-dots');
  if (accordion && carDots) {
    var cards = accordion.querySelectorAll('.md-accordion__card');
    var dots  = carDots.querySelectorAll('.md-callout__dot');

    function selectCard(i) {
      var idx = (i + cards.length) % cards.length;
      cards.forEach(function (c, ci) {
        var active = ci === idx;
        c.classList.toggle('is-active', active);
        c.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      dots.forEach(function (d, di) {
        var active = di === idx;
        d.classList.toggle('is-active', active);
        d.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      renderRecs(idx);
    }

    cards.forEach(function (card) {
      card.addEventListener('click', function () { selectCard(parseInt(card.dataset.index, 10)); });
    });
    dots.forEach(function (dot) {
      dot.addEventListener('click', function () { selectCard(parseInt(dot.dataset.index, 10)); });
    });

    renderRecs(0);
  }

  // ───────────────────────────────────────────────────────────
  // Hamburger drawer
  // ───────────────────────────────────────────────────────────
  var hamburger   = document.getElementById('md-hamburger');
  var drawer      = document.getElementById('md-drawer');
  var backdrop    = document.getElementById('md-drawer-backdrop');
  var drawerClose = document.getElementById('md-drawer-close');

  function openDrawer() {
    drawer.hidden = false;
    backdrop.hidden = false;
    requestAnimationFrame(function () {
      drawer.classList.add('is-open');
      backdrop.classList.add('is-open');
    });
    hamburger.setAttribute('aria-expanded', 'true');
    document.body.classList.add('is-locked');
  }
  function closeDrawer() {
    drawer.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    hamburger.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('is-locked');
    setTimeout(function () {
      drawer.hidden = true;
      backdrop.hidden = true;
    }, 280);
  }
  if (hamburger)   hamburger.addEventListener('click', openDrawer);
  if (backdrop)    backdrop.addEventListener('click', closeDrawer);
  if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
  if (drawer) {
    drawer.addEventListener('click', function (e) {
      if (e.target.closest('.md-drawer__link')) closeDrawer();
    });
  }

  // ───────────────────────────────────────────────────────────
  // Fyn overlay — opened by the docked chat input/send.
  // ───────────────────────────────────────────────────────────
  var fynOpen  = document.getElementById('md-fyn-open');
  var fynClose = document.getElementById('md-fyn-close');
  var fyn      = document.getElementById('md-fyn-overlay');

  function openFyn() {
    fyn.hidden = false;
    requestAnimationFrame(function () { fyn.classList.add('is-open'); });
    document.body.classList.add('is-locked');
  }
  function closeFyn() {
    fyn.classList.remove('is-open');
    document.body.classList.remove('is-locked');
    setTimeout(function () { fyn.hidden = true; }, 300);
  }
  var fynBarClose = document.getElementById('md-fyn-bar-close');
  if (fynOpen)     fynOpen.addEventListener('click', openFyn);
  if (fynClose)    fynClose.addEventListener('click', closeFyn);
  if (fynBarClose) fynBarClose.addEventListener('click', closeFyn);

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (fyn && !fyn.hidden) closeFyn();
    else if (drawer && !drawer.hidden) closeDrawer();
  });

  // ───────────────────────────────────────────────────────────
  // Recommendations collapse toggle (open by default)
  // ───────────────────────────────────────────────────────────
  var recsSection = document.getElementById('md-recs-section');
  var recsToggle  = document.getElementById('md-recs-toggle');
  if (recsToggle && recsSection) {
    recsToggle.addEventListener('click', function () {
      var open = recsSection.classList.toggle('is-open');
      recsToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Level ring is driven by updateLevel() (from renderRecs). Defer a frame so
  // the stroke-dashoffset transition animates from 0.
  requestAnimationFrame(function () {
    requestAnimationFrame(updateLevel);
  });

}());
