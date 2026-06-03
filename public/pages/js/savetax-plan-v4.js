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
    income: 'higher',
    spouse: 'yes',
    spouseIncome: 'personal-allowance',
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

  // --- Human-readable labels for the "remembered" chips -------------------
  var EMP = {
    'not-employed': 'Not employed', 'part-time': 'Part-time', 'full-time': 'Full-time',
    'self-employed': 'Self-employed', 'retired': 'Retired',
  };
  var INC = {
    'personal-allowance': 'Income up to £12,570', 'basic': 'Basic-rate income',
    'higher': 'Higher-rate income', 'additional': 'Additional-rate income',
  };
  var ASSET = {
    bank: 'Bank accounts', savings: 'Savings', pension: 'Pension',
    property: 'Property', isa: 'ISA', investments: 'Investments',
  };

  // --- Allowance model: applicability + personalised reason ---------------
  // Marginal income-tax rate by band (used for illustrative saving estimates).
  var RATE = { 'personal-allowance': 0.20, 'basic': 0.20, 'higher': 0.40, 'additional': 0.45 };
  var rate = RATE[ans.income] || 0.20;

  // Round a saving estimate to the nearest £50 for a clean headline figure.
  function round50(n) { return Math.round(n / 50) * 50; }

  // Each allowance returns { label, amount, note, on, estSaving, reason }.
  // estSaving = illustrative annual tax saving (mockup figures), summed across
  // applicable allowances to drive the hero "Up to" figure and the social-proof
  // stat. Reasons are deliberately factual / outcome-based — they state the
  // entitlement and the saving, but not the mechanics of how to achieve it.
  function buildAllowances() {
    var higherOrAdd = ans.income === 'higher' || ans.income === 'additional';
    var basic = ans.income === 'basic';
    var lowIncome = ans.income === 'personal-allowance';
    var marriageOn = ans.spouse === 'yes' && (ans.spouseIncome === 'personal-allowance' || ans.income === 'personal-allowance');
    var psaOn = (has('savings') || has('bank')) && ans.income !== 'additional';

    // Illustrative per-allowance savings (band-aware where it matters).
    var isaSave = { 'higher': 850, 'additional': 1100, 'basic': 400, 'personal-allowance': 150 }[ans.income] || 400;
    var pensionSave = { 'higher': 1800, 'additional': 4500, 'basic': 600, 'personal-allowance': 200 }[ans.income] || 600;

    var income = [
      {
        label: 'Personal Allowance', amount: 12570, note: 'Tax-free income each year',
        on: true, estSaving: 0,
        reason: ans.income === 'additional'
          ? "Everyone gets this, though it begins to taper away once income passes £100,000."
          : "Everyone gets this — it covers the first slice of your income tax-free.",
      },
      {
        label: 'Marriage Allowance', amount: 1260, note: 'Transferable to spouse',
        on: marriageOn, estSaving: marriageOn ? 252 : 0,
        reason: ans.spouse !== 'yes'
          ? "Available to married couples and civil partners."
          : marriageOn
            ? "You're married and one of you earns under the Personal Allowance — so you could save up to £252 a year."
            : "Available to married couples when one partner earns under the Personal Allowance.",
      },
      {
        label: 'Personal Savings Allowance', amount: basic ? 1000 : 500,
        note: 'Tax-free interest on savings',
        on: psaOn, estSaving: psaOn ? round50((basic ? 1000 : 500) * rate) : 0,
        reason: !(has('savings') || has('bank'))
          ? "Applies once you hold savings or earn bank interest."
          : ans.income === 'additional'
            ? "Additional-rate taxpayers don't receive this allowance."
            : higherOrAdd
              ? "As a higher-rate taxpayer you get a tax-free interest allowance."
              : "As a basic-rate taxpayer you get a tax-free interest allowance.",
      },
      {
        label: 'Starting Rate for Savings', amount: 5000, note: 'If other income is low',
        on: lowIncome, estSaving: lowIncome ? round50(5000 * 0.20) : 0,
        reason: lowIncome
          ? "Your income is low enough to qualify for extra tax-free savings interest."
          : "Applies when your other income is low.",
      },
    ];

    var invest = [
      {
        label: 'ISA Allowance', amount: 20000, note: 'Tax-free saving & investing',
        on: true, estSaving: (has('isa') || has('savings') || has('investments')) ? isaSave : 0,
        reason: has('isa')
          ? "You already have an ISA — you could save up to " + fmt(isaSave) + " a year."
          : (has('savings') || has('investments'))
            ? "Everyone gets a £20,000 ISA allowance each year — you could save up to " + fmt(isaSave) + " a year."
            : "Everyone gets a £20,000 ISA allowance each year.",
      },
      {
        label: 'Pension Annual Allowance', amount: 60000, note: 'Tax-relievable contributions',
        on: true, estSaving: pensionSave,
        reason: higherOrAdd
          ? "As a higher-rate taxpayer, your pension contributions attract valuable tax relief — you could save up to " + fmt(pensionSave) + " a year."
          : has('pension')
            ? "Your pension contributions attract tax relief — you could save up to " + fmt(pensionSave) + " a year."
            : "Pension contributions attract tax relief — you could save up to " + fmt(pensionSave) + " a year.",
      },
      {
        label: 'Dividend Allowance', amount: 500, note: 'Tax-free dividend income',
        on: has('investments'), estSaving: has('investments') ? round50(500 * rate) : 0,
        reason: has('investments')
          ? "You hold investments, so you get a tax-free dividend allowance."
          : "Applies once you hold investments that pay dividends.",
      },
      {
        label: 'Capital Gains Tax Allowance', amount: 3000, note: 'Tax-free gains each year',
        on: has('investments') || has('property'),
        estSaving: (has('investments') || has('property')) ? round50(3000 * 0.20) : 0,
        reason: (has('investments') || has('property'))
          ? "You hold assets that can grow, so you get a tax-free allowance on gains."
          : "Applies once you hold investments or property that can grow in value.",
      },
    ];

    return { income: income, invest: invest };
  }

  // Sum the applicable per-allowance saving estimates into a single figure,
  // rounded to the nearest £50, used by both the hero box and the proof stat.
  function estimatedSaving() {
    var model = buildAllowances();
    var total = 0;
    model.income.concat(model.invest).forEach(function (a) { if (a.on) total += (a.estSaving || 0); });
    return round50(total);
  }

  function allowanceItem(a) {
    var cls = a.on ? 'sp4-alw sp4-alw--on' : 'sp4-alw sp4-alw--off';
    var mark = a.on ? '&#10003;' : '&#8211;';
    return (
      '<div class="' + cls + '">' +
        '<span class="sp4-alw__check" aria-hidden="true">' + mark + '</span>' +
        '<div class="sp4-alw__body">' +
          '<div class="sp4-alw__row">' +
            '<span class="sp4-alw__label">' + a.label + '</span>' +
            '<span class="sp4-alw__amount">' + fmt(a.amount) + '</span>' +
          '</div>' +
          '<p class="sp4-alw__note">' + a.note + '</p>' +
          '<p class="sp4-alw__reason">' + a.reason + '</p>' +
        '</div>' +
      '</div>'
    );
  }

  function renderAllowances() {
    var host = document.getElementById('allowances-render');
    if (!host) return;
    var model = buildAllowances();
    var incomeHtml = model.income.map(allowanceItem).join('');
    var investHtml = model.invest.map(allowanceItem).join('');
    host.innerHTML =
      '<div class="sp4-alw-col sp4-alw-col--horizon">' +
        '<p class="sp4-alw-col__title">Income</p>' +
        '<div class="sp4-alw-list">' + incomeHtml + '</div>' +
      '</div>' +
      '<div class="sp4-alw-col sp4-alw-col--raspberry">' +
        '<p class="sp4-alw-col__title">Investment &amp; Cash</p>' +
        '<div class="sp4-alw-list">' + investHtml + '</div>' +
      '</div>';

    // Combined-section total = sum of applicable ALLOWANCE values.
    var allowTotal = 0;
    model.income.concat(model.invest).forEach(function (a) { if (a.on) allowTotal += a.amount; });
    var totalEl = document.getElementById('allowances-total');
    if (totalEl) totalEl.textContent = fmt(allowTotal);

    // Hero "Up to" figure = estimated SAVING (matches the "Could this be you"
    // stat), derived from the applicable allowances.
    var figure = document.getElementById('savings-figure');
    if (figure) figure.textContent = fmt(estimatedSaving());
  }

  // --- Social proof (illustrative sample content) -------------------------
  function renderProof() {
    var headline = document.getElementById('proof-headline');
    var grid = document.getElementById('proof-grid');
    if (!headline || !grid) return;

    var segment = INC[ans.income] ? INC[ans.income].toLowerCase() : 'people like you';
    var married = ans.spouse === 'yes';

    // Headline stat = the SAME estimated saving shown in the hero box.
    var stat = fmt(estimatedSaving());
    var count = { 'higher': '4,200', 'additional': '1,600', 'basic': '9,800', 'personal-allowance': '3,500' }[ans.income] || '5,000';

    headline.innerHTML =
      '<span class="sp4-proof__headline-stat">' + stat + '</span>' +
      '<p class="sp4-proof__headline-text">Average first-year tax saving identified for <strong>' +
      count + '</strong> ' + segment + ' members who completed onboarding with Fyn.</p>';

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
      el.style.cssText = 'color:#A93257;font-size:13px;line-height:1.4;margin:8px 0 0;';
      btn.parentNode.insertBefore(el, btn);
    }
    el.textContent = msg || '';
    el.style.display = msg ? 'block' : 'none';
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
  renderAllowances();
  renderProof();
  wireRegister();
}());
