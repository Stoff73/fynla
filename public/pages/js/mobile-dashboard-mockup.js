(function () {
  'use strict';

  // ───────────────────────────────────────────────────────────
  // Date stamp
  // ───────────────────────────────────────────────────────────
  var dateEl = document.getElementById('md-date');
  if (dateEl) {
    var now = new Date();
    var opts = { weekday: 'long', day: 'numeric', month: 'long' };
    dateEl.textContent = now.toLocaleDateString('en-GB', opts);
  }

  // ───────────────────────────────────────────────────────────
  // Recommendations — tick toggle + progress recalc
  // ───────────────────────────────────────────────────────────
  var recsList   = document.getElementById('md-recs-list');
  var recsCount  = document.getElementById('md-recs-count');
  var levelArc   = document.getElementById('md-level-arc');

  function recalcProgress() {
    var recs  = recsList.querySelectorAll('.md-rec');
    var total = recs.length;
    var done  = recsList.querySelectorAll('.md-rec.is-done').length;
    var pct   = total ? Math.round((done / total) * 100) : 0;

    recsCount.textContent = done + ' / ' + total;
    if (levelArc) levelArc.style.setProperty('--progress', pct);
  }

  if (recsList) {
    // Check-toggle (left zone of the row) toggles done state.
    recsList.addEventListener('click', function (e) {
      var checkBtn = e.target.closest('.md-rec__check-btn');
      if (!checkBtn) return;
      var li = checkBtn.closest('.md-rec');
      if (!li) return;
      var done = li.classList.toggle('is-done');
      li.dataset.done = done ? 'true' : 'false';
      checkBtn.setAttribute('aria-pressed', done ? 'true' : 'false');
      checkBtn.setAttribute('aria-label', done ? 'Mark as not done' : 'Mark complete');

      // Move completed to bottom; pending back to top.
      if (done) recsList.appendChild(li);
      else      recsList.insertBefore(li, recsList.firstElementChild);

      recalcProgress();
    });

    // Action link (right zone) — placeholder for navigation in the mockup.
    recsList.addEventListener('click', function (e) {
      var action = e.target.closest('.md-rec__action');
      if (!action) return;
      e.preventDefault();
      // Real build will navigate to the recommendation detail screen.
      // Mockup: no-op (left intentionally for the design review).
    });
  }

  // ───────────────────────────────────────────────────────────
  // Hamburger drawer
  // ───────────────────────────────────────────────────────────
  var hamburger = document.getElementById('md-hamburger');
  var drawer    = document.getElementById('md-drawer');
  var backdrop  = document.getElementById('md-drawer-backdrop');

  function openDrawer() {
    drawer.hidden = false;
    backdrop.hidden = false;
    // next frame so transition animates
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
    // hide after transition
    setTimeout(function () {
      drawer.hidden = true;
      backdrop.hidden = true;
    }, 280);
  }
  var drawerClose = document.getElementById('md-drawer-close');
  if (hamburger)   hamburger.addEventListener('click', openDrawer);
  if (backdrop)    backdrop.addEventListener('click', closeDrawer);
  if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
  // Close drawer when a nav link is tapped
  if (drawer) {
    drawer.addEventListener('click', function (e) {
      if (e.target.closest('.md-drawer__link')) closeDrawer();
    });
  }

  // ───────────────────────────────────────────────────────────
  // Fyn overlay
  // ───────────────────────────────────────────────────────────
  var fynOpen  = document.getElementById('md-fyn-open');
  var fynClose = document.getElementById('md-fyn-close');
  var fyn      = document.getElementById('md-fyn-overlay');

  function openFyn() {
    fyn.hidden = false;
    requestAnimationFrame(function () {
      fyn.classList.add('is-open');
    });
    document.body.classList.add('is-locked');
  }
  function closeFyn() {
    fyn.classList.remove('is-open');
    document.body.classList.remove('is-locked');
    setTimeout(function () {
      fyn.hidden = true;
    }, 300);
  }
  if (fynOpen)  fynOpen.addEventListener('click', openFyn);
  if (fynClose) fynClose.addEventListener('click', closeFyn);

  // Esc closes whichever is open
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

  // Initial load: the arc starts at --progress:0 (set in HTML). Update the
  // count immediately, but defer the arc fill to the next frame so the CSS
  // stroke-dashoffset transition animates the raspberry line into position.
  if (recsList && recsCount) {
    var total0 = recsList.querySelectorAll('.md-rec').length;
    var done0  = recsList.querySelectorAll('.md-rec.is-done').length;
    recsCount.textContent = done0 + ' / ' + total0;
  }
  requestAnimationFrame(function () {
    requestAnimationFrame(recalcProgress);
  });
}());
