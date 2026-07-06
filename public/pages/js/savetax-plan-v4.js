/**
 * savetax-plan-v4.js — drives the /savetax/plan/v4 mockup.
 *
 * Personalises the page from the savetax funnel answers. The funnel does not yet
 * persist answers (they live in a JS var and are lost on redirect), so this
 * mockup reads them from localStorage('savetax_answers') when present and falls
 * back to a representative demo persona otherwise. Wiring the real funnel to
 * save answers is a follow-up build.
 *
 * Answer shape: { employment, income, spouse, spouseIncome, assets: [] }
 *   employment   : not-employed | part-time | full-time | self-employed | retired
 *   income       : personal-allowance | basic | higher | additional
 *   spouse       : yes | no
 *   spouseIncome : personal-allowance | basic | higher | additional | null
 *   assets       : [bank, savings, pension, property, isa, investments]
 *
 * NOTE: all social-proof figures and testimonials are illustrative sample
 * content for the mockup only — not real customer data.
 */
(function () {
  'use strict';

  // --- Read answers (or demo persona) -------------------------------------
  var DEMO = {
    employment: 'full-time',
    income: '50271_100000',
    spouse: 'yes',
    spouseIncome: 'zero',
    assets: ['isa', 'pension', 'savings'],
  };

  function readAnswers() {
    try {
      var raw = localStorage.getItem('savetax_answers');
      if (raw) {
        var a = JSON.parse(raw);
        if (a && typeof a === 'object') {
          return {
            employment: a.employment || DEMO.employment,
            income: a.income || DEMO.income,
            spouse: a.spouse || DEMO.spouse,
            spouseIncome: a.spouseIncome || null,
            assets: Array.isArray(a.assets) ? a.assets : [],
          };
        }
      }
    } catch (e) { /* ignore */ }
    return DEMO;
  }

  var ans = readAnswers();
  var has = function (k) { return ans.assets.indexOf(k) !== -1; };
  var fmt = function (n) { return '£' + Number(n).toLocaleString('en-GB'); };
  // HTML-escape any string before it goes into innerHTML (defense in depth —
  // estimate strings are server-generated, but never trust-interpolate).
  var esc = function (s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  };

  // --- Human-readable labels for the "remembered" chips -------------------
  var EMP = {
    'not-employed': 'Not employed', 'part-time': 'Part-time', 'full-time': 'Full-time',
    'self-employed': 'Self-employed', 'retired': 'Retired',
  };
  var INC = {
    'upto_50270': 'income up to £50,270',
    '50271_100000': 'higher-rate income',
    '100001_125140': 'income in the £100k tax-trap',
    'over_125140': 'additional-rate income',
  };
  var ASSET = {
    bank: 'Bank accounts', savings: 'Savings', pension: 'Pension',
    property: 'Property', isa: 'ISA', investments: 'Investments',
  };

  // --- Personalised figures (from SaveTaxEstimateService) ----------------
  // The server computes everything from the funnel answers and injects it as
  // window.SAVETAX_ESTIMATE. We only render it here — no tax math in the page.
  var EST = (window.SAVETAX_ESTIMATE && typeof window.SAVETAX_ESTIMATE === 'object')
    ? window.SAVETAX_ESTIMATE : null;

  // Allowance keys shown in the "Income" column (everything else → Investment & Cash).
  var INCOME_KEYS = ['personal_allowance', 'marriage_allowance', 'psa', 'spouse_pa', 'spouse_starting_rate', 'spouse_psa'];

  // Allowance key → matching saving line (for the "could save" callout + reason).
  // The 60% tax-trap saving now attaches to the (tapered) Personal Allowance card
  // — the standalone trap card has been merged into it.
  // ISA is intentionally absent — its card shows a plain "tax-free account"
  // description (its note) rather than a figure-laden "could save" callout.
  var SAVING_FOR = {
    pension_aa: 'pension', psa: 'psa', dividend: 'dividend',
    cgt: 'cgt', marriage_allowance: 'marriage_allowance', spouse_pa: 'spouse_pa',
    personal_allowance: 'tax_trap_60',
  };

  function estimatedSaving() { return EST ? (EST.savings_total || 0) : 0; }

  function savingByKey(key) {
    if (!EST || !EST.savings || !key) return null;
    for (var i = 0; i < EST.savings.length; i++) {
      if (EST.savings[i].key === key) return EST.savings[i];
    }
    return null;
  }

  function allowanceItem(a) {
    var on = !!a.on;
    var cls = on ? 'sp4-alw sp4-alw--on' : 'sp4-alw sp4-alw--off';
    var mark = on ? '&#10003;' : '&#8211;';
    var saving = on ? savingByKey(SAVING_FOR[a.key]) : null;
    var reasonHtml = '';
    if (saving && saving.amount > 0) {
      reasonHtml = '<p class="sp4-alw__reason"><strong>Could save ' + fmt(saving.amount) + '/yr.</strong> ' + esc(saving.reason) + '</p>';
    } else if (saving && saving.reason) {
      reasonHtml = '<p class="sp4-alw__reason">' + esc(saving.reason) + '</p>';
    } else if (a.note) {
      // e.g. Personal Allowance taper explanation.
      reasonHtml = '<p class="sp4-alw__reason">' + esc(a.note) + '</p>';
    }
    return (
      '<div class="' + cls + '">' +
        '<span class="sp4-alw__check" aria-hidden="true">' + mark + '</span>' +
        '<div class="sp4-alw__body">' +
          '<div class="sp4-alw__row">' +
            '<span class="sp4-alw__label">' + esc(a.label) + '</span>' +
            '<span class="sp4-alw__amount">' + fmt(a.amount) + '</span>' +
          '</div>' +
          reasonHtml +
        '</div>' +
      '</div>'
    );
  }

  function renderAllowances() {
    var items = (EST && EST.allowances && EST.allowances.items) ? EST.allowances.items : [];

    var host = document.getElementById('allowances-render');
    if (host) {
      var incomeHtml = items.filter(function (a) { return INCOME_KEYS.indexOf(a.key) !== -1; }).map(allowanceItem).join('');
      var investHtml = items.filter(function (a) { return INCOME_KEYS.indexOf(a.key) === -1; }).map(allowanceItem).join('');
      host.innerHTML =
        '<div class="sp4-alw-col sp4-alw-col--horizon">' +
          '<p class="sp4-alw-col__title">Income</p>' +
          '<div class="sp4-alw-list">' + incomeHtml + '</div>' +
        '</div>' +
        '<div class="sp4-alw-col sp4-alw-col--raspberry">' +
          '<p class="sp4-alw-col__title">Investment &amp; Cash</p>' +
          '<div class="sp4-alw-list">' + investHtml + '</div>' +
        '</div>';
    }

    var totalEl = document.getElementById('allowances-total');
    if (totalEl && EST && EST.allowances) totalEl.textContent = fmt(EST.allowances.total || 0);

    var figure = document.getElementById('savings-figure');
    if (figure) figure.textContent = fmt(estimatedSaving());

    var ty = document.getElementById('tax-year');
    if (ty && EST && EST.tax_year) ty.textContent = EST.tax_year;
  }

  // --- Social proof (illustrative sample content) -------------------------
  function renderProof() {
    var headline = document.getElementById('proof-headline');
    var grid = document.getElementById('proof-grid');
    if (!headline || !grid) return;

    // "members with income up to £50,270" / "members with income in the
    // £100k tax-trap" — the segment is a qualifier AFTER "members", never
    // jammed between the count and "members" (that produced garbled copy).
    var segmentPhrase = INC[ans.income] ? 'with ' + INC[ans.income].toLowerCase() : 'like you';
    var married = ans.spouse === 'yes';

    // Headline stat = the SAME estimated saving shown in the hero box.
    var stat = fmt(estimatedSaving());
    // Illustrative sample sizes keyed by the funnel's CURRENT income band values.
    var count = {
      'upto_50270': '9,800',
      '50271_100000': '4,200',
      '100001_125140': '2,400',
      'over_125140': '1,600',
    }[ans.income] || '5,000';

    headline.innerHTML =
      '<span class="sp4-proof__headline-stat">' + stat + '</span>' +
      '<p class="sp4-proof__headline-text">Average first-year tax saving identified for <strong>' +
      count + '</strong> members ' + segmentPhrase + ' who completed onboarding with Fyn.</p>';

    // Build 3 testimonials relevant to the answers.
    var cards = [];

    if (married) {
      cards.push({
        name: 'Sarah & Tom', meta: 'Married, one part-time',
        quote: "We had no idea we could transfer Marriage Allowance. Fyn spotted it in minutes — that's <strong>£252 a year</strong> back in our pocket.",
      });
    }
    if (ans.income === 'higher' || ans.income === 'additional') {
      cards.push({
        name: 'James P.', meta: 'Higher-rate earner',
        quote: "Fyn showed me how salary sacrifice into my pension reclaimed <strong>40% relief</strong> I was leaving on the table.",
      });
    }
    if (has('isa') || has('savings') || has('investments')) {
      cards.push({
        name: 'Priya K.', meta: 'Saver & investor',
        quote: "I was paying tax on savings interest needlessly. Fyn moved me into my ISA allowance and <strong>wiped it out</strong>.",
      });
    }
    if (ans.income === 'additional') {
      cards.push({
        name: 'Daniel R.', meta: 'Earns over £100k',
        quote: "I didn't realise I'd hit the <strong>60% tax trap</strong>. Fyn's plan brought my effective rate right back down.",
      });
    }
    // Fallbacks to always show 3.
    var fallback = [
      { name: 'Megan L.', meta: 'New to planning', quote: "I finally understand which allowances are mine. Fyn made tax feel <strong>simple</strong>." },
      { name: 'Olu A.', meta: 'Long-term saver', quote: "Having one place that remembers everything and tells me what to do next is <strong>brilliant</strong>." },
      { name: 'Chris & Dawn', meta: 'Planning together', quote: "We saw our combined allowances for the first time. It changed how we save as a couple." },
    ];
    var i = 0;
    while (cards.length < 3 && i < fallback.length) {
      cards.push(fallback[i]); i++;
    }
    cards = cards.slice(0, 3);

    grid.innerHTML = cards.map(function (c) {
      var initials = c.name.split(/[ &]+/).map(function (w) { return w[0]; }).slice(0, 2).join('');
      return (
        '<article class="sp4-proof-card">' +
          '<div class="sp4-proof-card__stars" aria-label="5 out of 5 stars">' + '★★★★★' + '</div>' +
          '<p class="sp4-proof-card__quote">' + c.quote + '</p>' +
          '<div class="sp4-proof-card__person">' +
            '<span class="sp4-proof-card__avatar" aria-hidden="true">' + initials + '</span>' +
            '<span><span class="sp4-proof-card__name">' + c.name + '</span><br>' +
            '<span class="sp4-proof-card__meta">' + c.meta + '</span></span>' +
          '</div>' +
        '</article>'
      );
    }).join('');
  }

  // --- Compact register form (mockup behaviour) ---------------------------
  // --- Real account creation from the compact funnel form ----------------
  // Base path (subdirectory-aware: '/fynla' on csjones, '' at root).
  function base() { return window.FYNLA_BASE || ''; }

  function readCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }

  // Persist the funnel answers only when the user actually came through the
  // funnel (real localStorage answers) — never the demo persona.
  function realFunnelAnswers() {
    try {
      var raw = localStorage.getItem('savetax_answers');
      if (raw) { var a = JSON.parse(raw); if (a && typeof a === 'object') return a; }
    } catch (e) { /* ignore */ }
    return null;
  }

  function showRegError(msg) {
    var el = document.getElementById('reg-error');
    if (!el) {
      var btn = document.getElementById('register-btn');
      if (!btn) return;
      el = document.createElement('p');
      el.id = 'reg-error';
      el.setAttribute('role', 'alert');
      // Savannah Sand 500 — the palette's yellow tone. White blended into the
      // card's white text and users missed the error (took three attempts on
      // an existing email before noticing). Bold weight for extra contrast.
      el.style.cssText = 'color:#E6C9A8;font-size:13px;font-weight:700;line-height:1.4;margin:8px 0 0;';
      btn.parentNode.insertBefore(el, btn);
    }
    el.textContent = msg || '';
    el.style.display = msg ? 'block' : 'none';
  }

  // Marketing attribution (mirrors sourceCapture.js + the funnel page): pick
  // up ?utm_source= on direct plan-page landings, and read back whatever the
  // funnel page stashed, so the register card can submit signup_source.
  var SIGNUP_SOURCES = ['linkedin', 'facebook', 'instagram', 'tiktok', 'x', 'youtube'];
  function captureSignupSource() {
    try {
      var raw = new URLSearchParams(window.location.search).get('utm_source');
      var norm = (raw || '').trim().toLowerCase();
      if (SIGNUP_SOURCES.indexOf(norm) === -1) return;
      if (!sessionStorage.getItem('fynla.signup_source')) {
        sessionStorage.setItem('fynla.signup_source', norm);
      }
    } catch (e) { /* private mode */ }
  }
  function storedSignupSource() {
    try {
      var v = sessionStorage.getItem('fynla.signup_source');
      return v && SIGNUP_SOURCES.indexOf(v) !== -1 ? v : null;
    } catch (e) { return null; }
  }

  function wireRegister() {
    var form = document.getElementById('register-form');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var btn = document.getElementById('register-btn');
      var firstName = ((document.getElementById('reg-first-name') || {}).value || '').trim();
      var lastName = ((document.getElementById('reg-last-name') || {}).value || '').trim();
      var email = ((document.getElementById('reg-email') || {}).value || '').trim();
      var password = (document.getElementById('reg-password') || {}).value || '';

      showRegError('');
      if (!firstName || !lastName || !email || !password) {
        showRegError('Please enter your name, email and a password.');
        return;
      }

      var orig = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Creating your account…'; }

      try {
        // Stateful same-origin request → prime the CSRF cookie first.
        await fetch(base() + '/sanctum/csrf-cookie', { credentials: 'include' });

        var res = await fetch(base() + '/api/auth/register', {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') || '',
          },
          body: JSON.stringify({
            first_name: firstName,
            surname: lastName,
            email: email,
            password: password,
            password_confirmation: password,
            funnel_answers: realFunnelAnswers(),
            // Allowlist-filtered; undefined is dropped by JSON.stringify so a
            // no-attribution registration sends no signup_source key at all.
            signup_source: storedSignupSource() || undefined,
          }),
        });
        var data = await res.json().catch(function () { return {}; });

        if (!res.ok) {
          if (data.email_exists) {
            showRegError('That email already has an account — please sign in instead.');
          } else if (data.errors) {
            var firstKey = Object.keys(data.errors)[0];
            showRegError((data.errors[firstKey] && data.errors[firstKey][0]) || 'Please check your details and try again.');
          } else {
            showRegError(data.message || 'Registration failed. Please try again.');
          }
          if (btn) { btn.disabled = false; btn.textContent = orig; }
          return;
        }

        // Soft-deleted but restorable — let the full /register page handle restore.
        if (data.account_deleted_restorable) {
          window.location.href = base() + '/register';
          return;
        }

        // Account pending + code emailed. Hand to the EXISTING /register
        // verification screen (reuses the tested Vue verify UI). Stash the
        // pending id + email same-origin so Register.vue opens the code modal
        // directly. from=savetax → after verifying, the user is routed to the
        // dashboard; inside the /m mobile iframe the auth handoff then shows
        // the mobile dashboard (/m/app).
        if (data.requires_verification && data.data) {
          try {
            sessionStorage.setItem('fynla_pending_verify', JSON.stringify({
              pending_id: data.data.pending_id,
              email: data.data.email,
            }));
          } catch (err) { /* private mode — Register.vue falls back to its form */ }
          window.location.href = base() + '/register?from=savetax';
          return;
        }

        // Unexpected shape — fall back to the full register page.
        window.location.href = base() + '/register?from=savetax';
      } catch (err) {
        showRegError('Network error. Please try again.');
        if (btn) { btn.disabled = false; btn.textContent = orig; }
      }
    });
  }

  // --- Init ----------------------------------------------------------------
  captureSignupSource();
  renderAllowances();
  renderProof();
  wireRegister();
}());
