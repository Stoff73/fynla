/**
 * savetax-plan-v4.js — drives the /savetax/plan/v4 mockup.
 *
 * Personalises the page from the Save Tax funnel answers. The funnel carries
 * answers in both localStorage and the plan URL so private browsing or blocked
 * storage does not break the registration handoff.
 *
 * Answer shape: { employment, income, spouse, spouseIncome, assets: [] }
 *   employment   : not-employed | part-time | full-time | self-employed | retired
 *   income       : personal-allowance | basic | higher | additional
 *   spouse       : yes | no
 *   spouseIncome : personal-allowance | basic | higher | additional | null
 *   assets       : [bank, savings, pension, property, isa, investments]
 *
 */
(function () {
  'use strict';
  var fmt = function (n) { return '£' + Number(n).toLocaleString('en-GB'); };
  // HTML-escape any string before it goes into innerHTML (defense in depth —
  // estimate strings are server-generated, but never trust-interpolate).
  var esc = function (s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
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
    var state = ['available', 'used_automatically', 'not_applicable'].indexOf(a.state) !== -1
      ? a.state : 'not_applicable';
    var stateText = {
      available: 'Available to you',
      used_automatically: 'Used automatically',
      not_applicable: 'Not applicable',
    }[state];
    var cls = 'sp4-alw sp4-alw--' + state.replace('_', '-');
    var mark = state === 'available' ? '&#10003;' : '&#8211;';
    var saving = state === 'available' || a.key === 'personal_allowance'
      ? savingByKey(SAVING_FOR[a.key]) : null;
    var reasonHtml = a.explanation
      ? '<p class="sp4-alw__reason">' + esc(a.explanation) + '</p>'
      : '';
    if (saving && saving.amount > 0) {
      reasonHtml += '<p class="sp4-alw__reason"><strong>Could save ' + fmt(saving.amount) + '/yr.</strong> ' + esc(saving.reason) + '</p>';
    } else if (saving && saving.reason) {
      reasonHtml += '<p class="sp4-alw__reason">' + esc(saving.reason) + '</p>';
    }
    return (
      '<div class="' + cls + '">' +
        '<span class="sp4-alw__check" aria-hidden="true">' + mark + '</span>' +
        '<div class="sp4-alw__body">' +
          '<div class="sp4-alw__row">' +
            '<span class="sp4-alw__label">' + esc(a.label) + '</span>' +
            '<span class="sp4-alw__amount">' + fmt(a.amount) + '</span>' +
          '</div>' +
          '<p class="sp4-alw__state">' + stateText + '</p>' +
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

  // --- Compact register form (mockup behaviour) ---------------------------
  // --- Real account creation from the compact funnel form ----------------
  // Base path (subdirectory-aware: '/fynla' on csjones, '' at root).
  function base() { return window.FYNLA_BASE || ''; }

  function readCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }

  function realFunnelAnswers() {
    var params = new URLSearchParams(window.location.search);
    var queryAnswers = {
      campaign: 'savetax',
      employment: params.get('employment') || null,
      income: params.get('income') || null,
      spouse: params.get('spouse') || null,
      spouseIncome: params.get('spouseIncome') || null,
      assets: (params.get('assets') || '').split(',').map(function (asset) {
        return asset.trim();
      }).filter(Boolean).slice(0, 12),
    };

    try {
      var raw = localStorage.getItem('savetax_answers');
      if (raw) {
        var a = JSON.parse(raw);
        if (a && typeof a === 'object') {
          var spouse = params.has('spouse') ? queryAnswers.spouse : (a.spouse || null);
          return {
            campaign: 'savetax',
            employment: params.has('employment') ? queryAnswers.employment : (a.employment || null),
            income: params.has('income') ? queryAnswers.income : (a.income || null),
            spouse: spouse,
            spouseIncome: spouse === 'yes'
              ? (params.has('spouseIncome') ? queryAnswers.spouseIncome : (a.spouseIncome || null))
              : null,
            assets: params.has('assets')
              ? queryAnswers.assets
              : (Array.isArray(a.assets) ? a.assets.slice(0, 12) : []),
          };
        }
      }
    } catch { /* ignore */ }
    return queryAnswers;
  }

  function showRegError(msg, includeSignIn) {
    var el = document.getElementById('reg-error');
    if (!el) {
      var btn = document.getElementById('register-btn');
      if (!btn) return;
      el = document.createElement('p');
      el.id = 'reg-error';
      el.className = 'sp4-register__error';
      el.setAttribute('role', 'alert');
      btn.parentNode.insertBefore(el, btn);
    }
    el.replaceChildren();
    if (msg) el.appendChild(document.createTextNode(msg));
    if (msg && includeSignIn) {
      el.appendChild(document.createTextNode(' '));
      var link = document.createElement('a');
      link.href = base() + '/login?from=savetax';
      link.textContent = 'Sign in to your account';
      el.appendChild(link);
    }
    el.hidden = !msg;
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
    } catch { /* private mode */ }
  }
  function storedSignupSource() {
    try {
      var v = sessionStorage.getItem('fynla.signup_source');
      return v && SIGNUP_SOURCES.indexOf(v) !== -1 ? v : null;
    } catch { return null; }
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
            showRegError('That email already has an account.', true);
          } else if (data.errors) {
            var firstKey = Object.keys(data.errors)[0];
            showRegError((data.errors[firstKey] && data.errors[firstKey][0]) || 'Please check your details and try again.');
          } else {
            showRegError(data.message || 'Registration failed. Please try again.');
          }
          if (btn) { btn.disabled = false; btn.textContent = orig; }
          return;
        }

        // Soft-deleted but restorable — continue through the same encrypted
        // campaign handoff so the user does not repeat their details.
        if (data.account_deleted_restorable && data.handoff_token) {
          window.location.href = base() + '/register?from=savetax&handoff=' + encodeURIComponent(data.handoff_token);
          return;
        }

        // Account pending + code emailed. The encrypted, expiring handoff is
        // the only state carried to the existing verification screen.
        if (data.requires_verification && data.data && data.data.handoff_token) {
          window.location.href = base() + '/register?from=savetax&handoff=' + encodeURIComponent(data.data.handoff_token);
          return;
        }

        // Unexpected shape — fall back to the full register page.
        window.location.href = base() + '/register?from=savetax';
      } catch {
        showRegError('Network error. Please try again.');
        if (btn) { btn.disabled = false; btn.textContent = orig; }
      }
    });
  }

  // --- Init ----------------------------------------------------------------
  captureSignupSource();
  renderAllowances();
  wireRegister();
}());
