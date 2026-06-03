/**
 * savetax-plan-v2.js — interactions for /savetax/plan/v2
 *
 * Responsibilities:
 *   1. Detect ?from=savetax and personalise the hero heading + subtext.
 *   2. Show the fixed Fyn chat panel at desktop (lg: 1024px+).
 *   3. Wire the "Or find out more with Fyn" toggle (expand/collapse inline card chat).
 *   4. Redirect all chat inputs / "Get started" buttons to /register?from=savetax.
 *   5. Fetch live tax allowances and update the savings figure + allowance lists.
 *
 * Graceful degradation:
 *   - Without JS the page is fully readable; hero shows organic copy;
 *     savings figure shows the hardcoded £103,330 fallback.
 *   - If the API fetch fails the hardcoded HTML values remain in place.
 */

(function () {
  'use strict';

  var REGISTER_URL = '/register?from=savetax';

  /* ------------------------------------------------------------------
     1. Hero personalisation — swap heading when coming from /savetax
  ------------------------------------------------------------------ */
  function initHeroPersonalisation() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('from') !== 'savetax') { return; }

    /* Personalised heading */
    var heading = document.getElementById('hero-heading');
    if (heading) {
      heading.innerHTML =
        'Great news, you could <span class="campaign-hero__heading-accent">save tax!</span>';
    }

    /* Personalised subtext */
    var subtext = document.getElementById('hero-subtext');
    if (subtext) {
      subtext.textContent =
        'Based on the allowances available to you, here\'s what you could save. ' +
        'Register for free and Fyn will build your personalised tax strategy.';
    }

    /* Hide the organic register CTA — savings card already has the primary CTA */
    var organicCta = document.getElementById('hero-organic-cta');
    if (organicCta) { organicCta.hidden = true; }
  }

  /* ------------------------------------------------------------------
     2. Show the fixed Fyn chat panel on desktop (lg: 1024px+)
        Same logic as savetax-plan.js — panel starts [hidden].
  ------------------------------------------------------------------ */
  function initFynPanel() {
    var panel = document.getElementById('fyn-panel');
    if (!panel) { return; }

    if (window.innerWidth >= 1024) {
      panel.removeAttribute('hidden');
    }

    var alreadyShown = window.innerWidth >= 1024;
    window.addEventListener('resize', function () {
      if (!alreadyShown && window.innerWidth >= 1024) {
        panel.removeAttribute('hidden');
        alreadyShown = true;
      }
    });
  }

  /* ------------------------------------------------------------------
     3. Fyn chat toggle — expand / collapse the inline card chat
  ------------------------------------------------------------------ */
  function initFynToggle() {
    var toggle = document.getElementById('fyn-toggle');
    var chat   = document.getElementById('fyn-chat');
    if (!toggle || !chat) { return; }

    toggle.addEventListener('click', function () {
      var expanded = toggle.getAttribute('aria-expanded') === 'true';

      if (expanded) {
        toggle.setAttribute('aria-expanded', 'false');
        chat.hidden = true;
      } else {
        toggle.setAttribute('aria-expanded', 'true');
        chat.hidden = false;
        /* Scroll the chat into view on mobile so it's immediately visible */
        chat.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
  }

  /* ------------------------------------------------------------------
     4. Chat compose — redirect to register on any interaction
        Wires both the inline hero card AND the desktop panel.
        All inputs are readonly; any interaction signals register intent.
  ------------------------------------------------------------------ */
  function wireCompose(inputId, sendId) {
    var input   = document.getElementById(inputId);
    var sendBtn = document.getElementById(sendId);
    if (input) {
      input.addEventListener('click', function () { window.location.href = REGISTER_URL; });
      input.addEventListener('focus', function () { window.location.href = REGISTER_URL; });
    }
    if (sendBtn) {
      sendBtn.addEventListener('click', function () { window.location.href = REGISTER_URL; });
    }
  }

  function initChatCompose() {
    wireCompose('fyn-input-card', 'fyn-send-card'); /* hero savings card */
    wireCompose('fyn-input',      'fyn-send');       /* desktop panel     */
  }

  /* ------------------------------------------------------------------
     4. Live tax allowances fetch
  ------------------------------------------------------------------ */

  function fmt(amount) {
    return '£' + Number(amount).toLocaleString('en-GB');
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function buildAllowanceItem(label, note, amount) {
    var li = document.createElement('li');
    li.className = 'allowance-item';
    li.innerHTML =
      '<div class="allowance-item__text">' +
        '<p class="allowance-item__label">' + escapeHtml(label) + '</p>' +
        '<p class="allowance-item__note">' + escapeHtml(note) + '</p>' +
      '</div>' +
      '<span class="allowance-item__amount">' + fmt(amount) + '</span>';
    return li;
  }

  function renderList(listId, items) {
    var ul = document.getElementById(listId);
    if (!ul || !Array.isArray(items)) { return; }
    ul.innerHTML = '';
    items.forEach(function (item) {
      ul.appendChild(buildAllowanceItem(item.label, item.note, item.amount));
    });
  }

  function computeTotal(a, b) {
    var total = 0;
    [].concat(a, b).forEach(function (item) { total += Number(item.amount) || 0; });
    return total;
  }

  function updateSavingsFigures(total) {
    var fmtd = fmt(total);
    /* Hero savings card */
    var figure = document.getElementById('savings-figure');
    if (figure) { figure.textContent = fmtd; }
    /* Meaning section */
    var meaning = document.getElementById('allowances-total');
    if (meaning) { meaning.textContent = fmtd; }
  }

  function fetchAllowances() {
    fetch((window.FYNLA_BASE||'')+'/api/public/tax-allowances', {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    })
    .then(function (res) {
      if (!res.ok) { throw new Error('HTTP ' + res.status); }
      return res.json();
    })
    .then(function (data) {
      var taxYear = document.getElementById('tax-year');
      if (taxYear && data.tax_year) { taxYear.textContent = data.tax_year; }

      if (Array.isArray(data.income_allowances)) {
        renderList('income-allowances', data.income_allowances);
      }
      if (Array.isArray(data.investment_allowances)) {
        renderList('investment-allowances', data.investment_allowances);
      }
      if (Array.isArray(data.income_allowances) && Array.isArray(data.investment_allowances)) {
        updateSavingsFigures(computeTotal(data.income_allowances, data.investment_allowances));
      }
    })
    .catch(function () {
      /* API unavailable — hardcoded HTML fallback values remain. */
    });
  }

  /* ------------------------------------------------------------------
     Bootstrap
  ------------------------------------------------------------------ */
  document.addEventListener('DOMContentLoaded', function () {
    initHeroPersonalisation();
    initFynPanel();
    initFynToggle();
    initChatCompose();
    fetchAllowances();
  });

}());
