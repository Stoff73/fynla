(function () {
  'use strict';

  var answers = { campaign: 'pensioncheck', employment: null, income: null, age: null, pensions: [], pot: null, spouse: null };
  var current = 'employment';

  // Persist the funnel answers so the plan page personalises from the real
  // answers (pensioncheck-plan reads localStorage('pensioncheck_answers')) and
  // so they can be carried into registration. Then go to the personalised plan.
  function persistAndGoToPlan() {
    try { localStorage.setItem('pensioncheck_answers', JSON.stringify(answers)); } catch (e) { /* private mode */ }
    // Also pass the answers as query params so the plan page can compute the
    // personalised pension figures server-side (PensionEstimateService).
    var qs = 'from=pensioncheck'
      + '&employment=' + encodeURIComponent(answers.employment || '')
      + '&income=' + encodeURIComponent(answers.income || '')
      + '&age=' + encodeURIComponent(answers.age || '')
      + '&pensions=' + encodeURIComponent((answers.pensions || []).join(','))
      + '&pot=' + encodeURIComponent(answers.pot || '')
      + '&spouse=' + encodeURIComponent(answers.spouse || '');
    window.location.href = (window.FYNLA_BASE || '') + '/pensioncheck/plan?' + qs;
  }

  function sequence() {
    return ['employment', 'income', 'age', 'pensions', 'pot', 'spouse'];
  }

  function totalSteps() { return sequence().length; }
  function stepIndex()  { return sequence().indexOf(current); }

  var backBtn      = document.getElementById('qr-back-btn');
  var continueBtn  = document.getElementById('qr-continue-btn');
  var footerArea   = document.getElementById('qr-footer-area');
  var stepLabel    = document.getElementById('qr-step-label');
  var progressFill = document.getElementById('qr-progress-fill');

  // Single-select screens auto-advance on selection; only the pensions
  // multi-select screen shows the Continue button.
  function updateFooter() {
    if (footerArea) footerArea.style.display = (current === 'pensions') ? '' : 'none';
  }

  function updateProgressTicks(total) {
    var bar = progressFill.parentElement;
    // Remove any previously injected ticks
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
    var isPensions = (current === 'pensions');
    continueBtn.textContent = 'Continue';

    if (isPensions) {
      // Pensions screen: zero selection is valid — always enable
      continueBtn.disabled = false;
    } else {
      var answerKey = {
        employment: 'employment',
        income:     'income',
        age:        'age',
        pot:        'pot',
        spouse:     'spouse',
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

  function togglePension(btn) {
    var value = btn.dataset.value;

    // 'none' is exclusive: selecting it clears all other options.
    // Selecting any other option clears 'none'.
    if (value === 'none') {
      answers.pensions = answers.pensions.indexOf('none') === -1 ? ['none'] : [];
    } else {
      var noneIdx = answers.pensions.indexOf('none');
      if (noneIdx !== -1) answers.pensions.splice(noneIdx, 1);
      var idx = answers.pensions.indexOf(value);
      if (idx === -1) {
        answers.pensions.push(value);
      } else {
        answers.pensions.splice(idx, 1);
      }
    }

    // Sync all checkbox states in the pensions screen
    var screen = document.getElementById('s-pensions');
    if (screen) {
      screen.querySelectorAll('.qr-opt--multi').forEach(function (b) {
        var selected = answers.pensions.indexOf(b.dataset.value) !== -1;
        b.classList.toggle('sel', selected);
        b.setAttribute('aria-checked', selected ? 'true' : 'false');
      });
    }
  }

  // Back button
  backBtn.addEventListener('click', goBack);

  // Continue button — only shown on the pensions screen
  continueBtn.addEventListener('click', function () {
    if (current === 'pensions') {
      advance();
    }
  });

  // Wire single-select screens
  var screenMap = [
    { id: 'employment', answerKey: 'employment' },
    { id: 'income',     answerKey: 'income' },
    { id: 'age',        answerKey: 'age' },
    { id: 'pot',        answerKey: 'pot' },
    { id: 'spouse',     answerKey: 'spouse' },
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

  // Wire multi-select pensions screen
  var pensionsScreen = document.getElementById('s-pensions');
  if (pensionsScreen) {
    pensionsScreen.querySelectorAll('.qr-opt--multi').forEach(function (btn) {
      btn.addEventListener('click', function () {
        togglePension(btn);
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
