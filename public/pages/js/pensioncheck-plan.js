/**
 * pensioncheck-plan.js — drives the /pensioncheck/plan result page.
 *
 * Personalises the page from the pensioncheck funnel answers. Reads from
 * localStorage('pensioncheck_answers') when available (answers persisted by
 * pensioncheck.js) and falls back to a representative demo persona for
 * direct / shared-link visits.
 *
 * Answer shape: { campaign, employment, income, age, pensions: [], pot, spouse }
 *   employment : not-employed | part-time | full-time | self-employed | retired
 *   income     : upto_50270 | 50271_100000 | 100001_125140 | over_125140
 *   age        : under_30 | 30s | 40s | 50s | 60_plus
 *   pensions   : [workplace, personal_sipp, final_salary, none]
 *   pot        : none | under_25k | 25k_100k | 100k_250k | over_250k
 *   spouse     : yes | no
 *
 * Estimate shape (from PensionEstimateService):
 *   projected_pot                : float
 *   retirement_age               : int
 *   years_to_retirement          : int
 *   monthly_contribution_assumed : float
 *   tax_relief_note              : string
 *   already_retired              : bool
 *
 * NOTE: all social-proof figures and testimonials are illustrative sample
 * content — not real customer data.
 */
(function () {
  'use strict';

  // --- Read answers (or demo persona) -------------------------------------
  var DEMO = {
    campaign:   'pensioncheck',
    employment: 'full-time',
    income:     'upto_50270',
    age:        '40s',
    pensions:   ['workplace'],
    pot:        '25k_100k',
    spouse:     'no',
  };

  function readAnswers() {
    var a = realFunnelAnswers();
    return {
      campaign: 'pensioncheck',
      employment: a.employment || DEMO.employment,
      income: a.income || DEMO.income,
      age: a.age || DEMO.age,
      pensions: Array.isArray(a.pensions) ? a.pensions : [],
      pot: a.pot || DEMO.pot,
      spouse: a.spouse || DEMO.spouse,
    };
  }

  var ans = readAnswers();
  // Format a number as whole-pound sterling (no decimal places).
  var fmt = function (n) { return '£' + Math.round(Number(n)).toLocaleString('en-GB'); };
  // HTML-escape before inserting into innerHTML (defense in depth).
  var esc = function (s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  };

  // --- Estimate from PensionEstimateService (server-injected) ------------
  var EST = (window.PENSIONCHECK_ESTIMATE && typeof window.PENSIONCHECK_ESTIMATE === 'object')
    ? window.PENSIONCHECK_ESTIMATE : null;


  // --- Base path (subdirectory-aware on dev) ------------------------------
  function base() { return window.FYNLA_BASE || ''; }

  // --- Update hero heading and projection figure --------------------------
  function renderHero() {
    var headingText    = document.getElementById('hero-heading-text');
    var potFigureEl    = document.getElementById('pot-figure');
    var potLabelEl     = document.getElementById('pot-label');
    var retirementAgeEl = document.getElementById('retirement-age');
    var contributionEl = document.getElementById('contribution-note');
    var taxReliefEl    = document.getElementById('tax-relief-note');

    if (!EST) return;

    var alreadyRetired  = !!EST.already_retired;
    var projectedPot    = EST.projected_pot || 0;
    var retirementAge   = EST.retirement_age || 67;
    var monthlyContrib  = EST.monthly_contribution_assumed || 0;
    var taxReliefNote   = EST.tax_relief_note || '';

    if (alreadyRetired) {
      // Supportive already-retired variant — show current pot, no projection timeline.
      if (headingText) {
        headingText.innerHTML = 'Here is your current <span class="campaign-hero__heading-accent">pension picture</span>';
      }
      if (potFigureEl) potFigureEl.textContent = fmt(projectedPot);
      if (potLabelEl)  potLabelEl.textContent = 'estimated current pension pot';
      if (retirementAgeEl) retirementAgeEl.textContent = '';
    } else {
      // Standard projection variant.
      if (headingText) {
        headingText.innerHTML = 'On track for a <span class="campaign-hero__heading-accent">pension pot</span> of roughly';
      }
      if (potFigureEl) potFigureEl.textContent = fmt(projectedPot);
      if (potLabelEl && retirementAgeEl) {
        retirementAgeEl.textContent = retirementAge;
      }
    }

    // Contribution assumption — hide for non-contributors.
    if (contributionEl) {
      if (!alreadyRetired && monthlyContrib > 0) {
        contributionEl.textContent = 'This assumes ' + fmt(monthlyContrib) + ' a month goes in throughout.';
        contributionEl.style.display = '';
      } else if (alreadyRetired) {
        contributionEl.textContent = 'No further contributions are assumed.';
        contributionEl.style.display = '';
      } else {
        contributionEl.style.display = 'none';
      }
    }

    // Tax relief note.
    if (taxReliefEl) {
      if (taxReliefNote) {
        taxReliefEl.textContent = taxReliefNote;
        taxReliefEl.style.display = '';
      } else {
        taxReliefEl.style.display = 'none';
      }
    }
  }

  // --- Render projection breakdown stats grid ----------------------------
  function renderStats() {
    var host = document.getElementById('stats-render');
    var yearsEl = document.getElementById('years-figure');
    if (!EST || !host) return;

    var alreadyRetired = !!EST.already_retired;
    var yearsToRet     = EST.years_to_retirement || 0;
    var retirementAge  = EST.retirement_age || 67;
    var monthlyContrib = EST.monthly_contribution_assumed || 0;
    var taxReliefNote  = EST.tax_relief_note || '';

    if (yearsEl) {
      yearsEl.textContent = alreadyRetired ? '0' : yearsToRet;
    }

    var rows = [];

    if (!alreadyRetired) {
      rows.push({
        label:  'State Pension age',
        value:  'Age ' + retirementAge,
        note:   'This projection runs to your expected State Pension age.',
      });

      if (monthlyContrib > 0) {
        rows.push({
          label: 'Monthly contributions assumed',
          value: fmt(monthlyContrib),
          note:  'Based on auto-enrolment minimum contribution rates for your income band.',
        });
      }
    }

    if (taxReliefNote) {
      rows.push({
        label: 'Your pension tax relief',
        value: null,
        note:  taxReliefNote,
      });
    }

    rows.push({
      label: 'Growth rate assumed',
      value: '2.5% real terms per year',
      note:  'A conservative real-terms figure after estimated inflation. Actual returns will vary.',
    });

    var leftHtml  = '';
    var rightHtml = '';
    rows.forEach(function (row, i) {
      var html =
        '<div class="sp4-alw sp4-alw--on">' +
          '<span class="sp4-alw__check" aria-hidden="true"></span>' +
          '<div class="sp4-alw__body">' +
            '<div class="sp4-alw__row">' +
              '<span class="sp4-alw__label">' + esc(row.label) + '</span>' +
              (row.value ? '<span class="sp4-alw__amount">' + esc(row.value) + '</span>' : '') +
            '</div>' +
            (row.note ? '<p class="sp4-alw__reason">' + esc(row.note) + '</p>' : '') +
          '</div>' +
        '</div>';
      if (i % 2 === 0) {
        leftHtml += html;
      } else {
        rightHtml += html;
      }
    });

    host.innerHTML =
      '<div class="sp4-alw-col sp4-alw-col--horizon">' +
        '<p class="sp4-alw-col__title">Projection details</p>' +
        '<div class="sp4-alw-list">' + leftHtml + '</div>' +
      '</div>' +
      '<div class="sp4-alw-col sp4-alw-col--raspberry">' +
        '<p class="sp4-alw-col__title">Tax relief</p>' +
        '<div class="sp4-alw-list">' + rightHtml + '</div>' +
      '</div>';
  }

  // --- Compact register form — real account creation ---------------------
  function readCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }

  function realFunnelAnswers() {
    var params = new URLSearchParams(window.location.search);
    var queryAnswers = {
      campaign: 'pensioncheck',
      employment: params.get('employment') || null,
      income: params.get('income') || null,
      age: params.get('age') || null,
      pensions: (params.get('pensions') || '').split(',').map(function (pension) {
        return pension.trim();
      }).filter(Boolean).slice(0, 12),
      pot: params.get('pot') || null,
      spouse: params.get('spouse') || null,
    };

    try {
      var raw = localStorage.getItem('pensioncheck_answers');
      if (raw) {
        var a = JSON.parse(raw);
        if (a && typeof a === 'object') {
          return {
            campaign: 'pensioncheck',
            employment: params.has('employment') ? queryAnswers.employment : (a.employment || null),
            income: params.has('income') ? queryAnswers.income : (a.income || null),
            age: params.has('age') ? queryAnswers.age : (a.age || null),
            pensions: params.has('pensions')
              ? queryAnswers.pensions
              : (Array.isArray(a.pensions) ? a.pensions.slice(0, 12) : []),
            pot: params.has('pot') ? queryAnswers.pot : (a.pot || null),
            spouse: params.has('spouse') ? queryAnswers.spouse : (a.spouse || null),
          };
        }
      }
    } catch { /* ignore */ }
    return queryAnswers;
  }

  function showRegError(msg) {
    var el = document.getElementById('reg-error');
    if (!el) {
      var btn = document.getElementById('register-btn');
      if (!btn) return;
      el = document.createElement('p');
      el.id = 'reg-error';
      el.setAttribute('role', 'alert');
      el.className = 'sp4-reg-error';
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
      var btn       = document.getElementById('register-btn');
      var firstName = ((document.getElementById('reg-first-name') || {}).value || '').trim();
      var lastName  = ((document.getElementById('reg-last-name') || {}).value || '').trim();
      var email     = ((document.getElementById('reg-email') || {}).value || '').trim();
      var password  = (document.getElementById('reg-password') || {}).value || '';

      showRegError('');
      if (!firstName || !lastName || !email || !password) {
        showRegError('Please enter your name, email and a password.');
        return;
      }

      var orig = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = 'Creating your account…'; }

      try {
        // Stateful same-origin request — prime the CSRF cookie first.
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
            first_name:    firstName,
            surname:       lastName,
            email:         email,
            password:      password,
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

        // Soft-deleted but restorable — continue through the same encrypted
        // campaign handoff so the user does not repeat their details.
        if (data.account_deleted_restorable && data.handoff_token) {
          window.location.href = base() + '/register?from=pensioncheck&handoff=' + encodeURIComponent(data.handoff_token);
          return;
        }

        // Account pending + code emailed. The encrypted, expiring handoff is
        // the only state carried to the existing verification screen.
        if (data.requires_verification && data.data && data.data.handoff_token) {
          window.location.href = base() + '/register?from=pensioncheck&handoff=' + encodeURIComponent(data.data.handoff_token);
          return;
        }

        // Unexpected shape — fall back to the full register page.
        window.location.href = base() + '/register?from=pensioncheck';
      } catch {
        showRegError('Network error. Please try again.');
        if (btn) { btn.disabled = false; btn.textContent = orig; }
      }
    });
  }

  // --- Wire login link (FYNLA_BASE-aware) ---------------------------------
  function wireLoginLink() {
    var el = document.getElementById('login-link');
    if (!el) return;
    // Build the redirect URL so Fyn opens the pension journey on login.
    var redirect = encodeURIComponent(base() + '/dashboard?openFyn=journey&from=pensioncheck');
    el.href = base() + '/login?redirect=' + redirect;
  }

  // --- Init ----------------------------------------------------------------
  captureSignupSource();
  renderHero();
  renderStats();
  wireRegister();
  wireLoginLink();

}());
