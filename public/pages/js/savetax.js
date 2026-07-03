(function () {
  'use strict';

  var answers = { campaign: 'savetax', employment: null, income: null, spouse: null, spouseIncome: null, assets: [] };
  var current = 'employment';

  // Persist the funnel answers so the plan page personalises from the real
  // answers (savetax-plan reads localStorage('savetax_answers')) and so they
  // can be carried into registration. Then go to the personalised plan.
  function persistAndGoToPlan() {
    try { localStorage.setItem('savetax_answers', JSON.stringify(answers)); } catch (e) { /* private mode */ }
    // Also pass the answers as query params so the plan page can compute the
    // personalised tax figures server-side (SaveTaxEstimateService).
    var qs = 'from=savetax'
      + '&employment=' + encodeURIComponent(answers.employment || '')
      + '&income=' + encodeURIComponent(answers.income || '')
      + '&spouse=' + encodeURIComponent(answers.spouse || '')
      + '&spouseIncome=' + encodeURIComponent(answers.spouseIncome || '')
      + '&assets=' + encodeURIComponent((answers.assets || []).join(','));
    window.location.href = (window.FYNLA_BASE || '') + '/savetax/plan?' + qs;
  }

  function sequence() {
    var s = ['employment', 'income', 'spouse'];
    if (answers.spouse === 'yes') s.push('spouse-income');
    s.push('assets');
    return s;
  }

  function totalSteps() { return sequence().length; }
  function stepIndex()  { return sequence().indexOf(current); }

  var backBtn      = document.getElementById('qr-back-btn');
  var continueBtn  = document.getElementById('qr-continue-btn');
  var footerArea   = document.getElementById('qr-footer-area');
  var stepLabel    = document.getElementById('qr-step-label');
  var progressFill = document.getElementById('qr-progress-fill');

  // Single-select screens auto-advance on selection; only the final
  // multi-select (assets) screen shows the Continue button.
  function updateFooter() {
    if (footerArea) footerArea.style.display = (current === 'assets') ? '' : 'none';
  }

  function updateProgressTicks(total) {
    var bar = progressFill.parentElement;
    // Remove any previously injected ticks (total can change when spouse=yes adds a stage)
    bar.querySelectorAll('.qr-progress__tick').forEach(function (t) { t.remove(); });
    // Insert (total - 1) dividers at evenly spaced positions
    for (var i = 1; i < total; i++) {
      var tick = document.createElement('span');
      tick.className = 'qr-progress__tick';
      tick.setAttribute('aria-hidden', 'true');
      tick.style.left = ((i / total) * 100) + '%';
      bar.appendChild(tick);
    }
  }

  function updateHeader() {
    var idx   = stepIndex();
    var total = totalSteps();
    var pct   = ((idx + 1) / total) * 100;

    stepLabel.textContent = (idx + 1) + ' of ' + total;
    // Extend width by 14px past the section boundary so the rounded right
    // edge of the fill fully covers the tick line behind it. Container
    // overflow:hidden clips any overshoot at the 100% mark.
    progressFill.style.width = 'calc(' + pct + '% + 14px)';
    progressFill.parentElement.setAttribute('aria-valuenow', Math.round(pct));
    updateProgressTicks(total);

    if (idx === 0) {
      backBtn.classList.add('invisible');
      backBtn.setAttribute('aria-hidden', 'true');
    } else {
      backBtn.classList.remove('invisible');
      backBtn.removeAttribute('aria-hidden');
    }
  }

  function updateContinue() {
    var isAssets = (current === 'assets');
    continueBtn.textContent = isAssets ? 'See your tax insights' : 'Continue';

    if (isAssets) {
      // Assets screen: zero selection is valid — always enable
      continueBtn.disabled = false;
    } else {
      var answerKey = {
        employment:     'employment',
        income:         'income',
        spouse:         'spouse',
        'spouse-income': 'spouseIncome'
      }[current];
      continueBtn.disabled = !answers[answerKey];
    }
  }

  function goTo(targetId, dir) {
    var fromEl = document.getElementById('s-' + current);
    var toEl   = document.getElementById('s-' + targetId);
    if (!toEl) return;

    fromEl.classList.remove('is-active', 'from-left');

    // Force reflow so the animation class is applied fresh
    void toEl.offsetWidth;

    toEl.classList.remove('from-left');
    if (dir === 'back') toEl.classList.add('from-left');
    toEl.classList.add('is-active');

    current = targetId;
    updateHeader();
    updateContinue();
    updateFooter();

    // Move focus to the screen heading for keyboard / screen-reader users
    var heading = toEl.querySelector('[tabindex="-1"]');
    if (heading) heading.focus();
  }

  function advance() {
    var seq = sequence();
    var idx = seq.indexOf(current);
    if (idx < seq.length - 1) {
      goTo(seq[idx + 1], 'forward');
    } else {
      persistAndGoToPlan();
    }
  }

  function goBack() {
    var seq = sequence();
    var idx = seq.indexOf(current);
    if (idx > 0) {
      goTo(seq[idx - 1], 'back');
    }
  }

  function selectSingle(screenId, value, answerKey) {
    answers[answerKey] = value;

    // If Q3 answer changes to 'no', clear any previously stored spouse income selection
    if (screenId === 'spouse' && value === 'no') {
      answers.spouseIncome = null;
      var spouseScreen = document.getElementById('s-spouse-income');
      if (spouseScreen) {
        spouseScreen.querySelectorAll('.qr-opt').forEach(function (btn) {
          btn.classList.remove('sel');
          btn.setAttribute('aria-pressed', 'false');
        });
      }
    }

    // Update visual selected state on all options in this screen
    var screen = document.getElementById('s-' + screenId);
    screen.querySelectorAll('.qr-opt').forEach(function (btn) {
      var sel = btn.dataset.value === value;
      btn.classList.toggle('sel', sel);
      btn.setAttribute('aria-pressed', sel ? 'true' : 'false');
    });

    updateContinue();

    // Auto-advance to the next question after a brief highlight so the user
    // sees their choice register. Back re-shows this screen with the choice
    // still highlighted (the .sel state persists in the DOM).
    window.setTimeout(advance, 220);
  }

  function toggleAsset(btn) {
    var value = btn.dataset.value;
    var idx   = answers.assets.indexOf(value);
    if (idx === -1) {
      answers.assets.push(value);
      btn.classList.add('sel');
      btn.setAttribute('aria-checked', 'true');
    } else {
      answers.assets.splice(idx, 1);
      btn.classList.remove('sel');
      btn.setAttribute('aria-checked', 'false');
    }
  }

  // Back button
  backBtn.addEventListener('click', goBack);

  // Continue button
  continueBtn.addEventListener('click', function () {
    if (current === 'assets') {
      persistAndGoToPlan();
    } else {
      advance();
    }
  });

  // Wire single-select screens
  var screenMap = [
    { id: 'employment',    answerKey: 'employment' },
    { id: 'income',        answerKey: 'income' },
    { id: 'spouse',        answerKey: 'spouse' },
    { id: 'spouse-income', answerKey: 'spouseIncome' },
  ];

  screenMap.forEach(function (s) {
    var el = document.getElementById('s-' + s.id);
    if (!el) return;
    el.querySelectorAll('.qr-opt').forEach(function (btn) {
      btn.addEventListener('click', function () {
        selectSingle(s.id, btn.dataset.value, s.answerKey);
      });
    });
  });

  // Wire multi-select assets screen
  var assetsScreen = document.getElementById('s-assets');
  if (assetsScreen) {
    assetsScreen.querySelectorAll('.qr-opt--multi').forEach(function (btn) {
      btn.addEventListener('click', function () {
        toggleAsset(btn);
      });
    });
  }

  // Keyboard arrow-key navigation within an option list
  document.querySelectorAll('.qr-screen').forEach(function (screen) {
    screen.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
      var opts    = Array.from(screen.querySelectorAll('.qr-opt'));
      var focused = document.activeElement;
      var idx     = opts.indexOf(focused);
      if (idx === -1) return;
      e.preventDefault();
      var next = e.key === 'ArrowDown' ? opts[idx + 1] : opts[idx - 1];
      if (next) next.focus();
    });
  });

  // Initialise header + continue state
  updateHeader();
  updateContinue();
  updateFooter();

}());
