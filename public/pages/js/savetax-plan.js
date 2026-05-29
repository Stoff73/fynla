/**
 * savetax-plan.js — page-specific interactions for /savetax/plan
 *
 * Responsibilities:
 *   1. Hero personalisation — swap heading/subtext when ?from=savetax.
 *   2. Show the Fyn chat panel (remove [hidden] attribute) on desktop.
 *   3. Wire the savings card Fyn toggle (expand/collapse inline chat).
 *   4. Wire all chat compose inputs to redirect to /register?from=savetax.
 *   5. Fetch live tax allowances and update savings figure + allowance lists.
 *
 * Graceful degradation:
 *   - Without JS the panel stays hidden, page content is fully readable,
 *     hero shows organic copy, savings figure shows the hardcoded fallback.
 *   - If the API fetch fails, hardcoded HTML fallback values remain in place.
 */

(function () {
  'use strict';

  /* ------------------------------------------------------------------
     1. Hero personalisation — swap heading when coming from /savetax
  ------------------------------------------------------------------ */
  function initHeroPersonalisation() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('from') !== 'savetax') { return; }

    var heading = document.getElementById('hero-heading');
    if (heading) {
      heading.innerHTML =
        'Great news, you could <span class="campaign-hero__heading-accent">save tax!</span>';
    }

    var subtext = document.getElementById('hero-subtext');
    if (subtext) {
      subtext.textContent =
        'Based on the allowances available to you, here\'s what you could save. ' +
        'Register for free and Fyn will build your personalised tax strategy.';
    }

    /* Hide the organic CTA — savings card already has the primary CTA */
    var organicCta = document.getElementById('hero-organic-cta');
    if (organicCta) { organicCta.hidden = true; }
  }

  /* ------------------------------------------------------------------
     2. Show the Fyn panel on desktop (lg: 1024px+)
        The aside starts with [hidden] so it never shows without JS.
        We only reveal it if the viewport is desktop-width.
  ------------------------------------------------------------------ */
  function initFynPanel() {
    var panel = document.getElementById('fyn-panel');
    if (!panel) { return; }

    /* Only show on desktop — matches the CSS media query */
    if (window.innerWidth >= 1024) {
      panel.removeAttribute('hidden');
    }

    /* If the viewport resizes to desktop after initial load, show it.
       Use a one-shot listener to avoid toggling on every resize event. */
    var resizeShown = window.innerWidth >= 1024;
    window.addEventListener('resize', function () {
      if (!resizeShown && window.innerWidth >= 1024) {
        panel.removeAttribute('hidden');
        resizeShown = true;
      }
    });
  }

  /* ------------------------------------------------------------------
     2. Fetch live tax allowances and update the DOM
  ------------------------------------------------------------------ */

  function formatGbp(amount) {
    return '£' + Number(amount).toLocaleString('en-GB');
  }

  function buildAllowanceItem(label, note, amount) {
    var li = document.createElement('li');
    li.className = 'allowance-item';
    li.innerHTML =
      '<div class="allowance-item__text">' +
        '<p class="allowance-item__label">' + escapeHtml(label) + '</p>' +
        '<p class="allowance-item__note">' + escapeHtml(note) + '</p>' +
      '</div>' +
      '<span class="allowance-item__amount">' + formatGbp(amount) + '</span>';
    return li;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderList(listId, items) {
    var ul = document.getElementById(listId);
    if (!ul || !Array.isArray(items)) { return; }
    ul.innerHTML = '';
    items.forEach(function (item) {
      ul.appendChild(buildAllowanceItem(item.label, item.note, item.amount));
    });
  }

  function computeTotal(incomeAllowances, investmentAllowances) {
    var total = 0;
    [].concat(incomeAllowances, investmentAllowances).forEach(function (item) {
      total += Number(item.amount) || 0;
    });
    return total;
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
      /* Update tax year label */
      var taxYearEl = document.getElementById('tax-year');
      if (taxYearEl && data.tax_year) {
        taxYearEl.textContent = data.tax_year;
      }

      /* Re-render income allowances */
      if (Array.isArray(data.income_allowances)) {
        renderList('income-allowances', data.income_allowances);
      }

      /* Re-render investment allowances */
      if (Array.isArray(data.investment_allowances)) {
        renderList('investment-allowances', data.investment_allowances);
      }

      /* Update the grand total + hero savings figure */
      if (Array.isArray(data.income_allowances) && Array.isArray(data.investment_allowances)) {
        var total = computeTotal(data.income_allowances, data.investment_allowances);
        var fmtd  = formatGbp(total);
        var totalEl  = document.getElementById('allowances-total');
        var figureEl = document.getElementById('savings-figure');
        if (totalEl)  { totalEl.textContent  = fmtd; }
        if (figureEl) { figureEl.textContent = fmtd; }
      }
    })
    .catch(function () {
      /* API unavailable — hardcoded HTML fallback values remain in place.
         Silently swallow the error; the page content is still fully usable. */
    });
  }

  /* ------------------------------------------------------------------
     3. Savings card Fyn toggle — expand / collapse inline chat
  ------------------------------------------------------------------ */
  function initFynToggle() {
    var toggle = document.getElementById('fyn-toggle');
    var chat   = document.getElementById('fyn-chat-card');
    if (!toggle || !chat) { return; }

    toggle.addEventListener('click', function () {
      var expanded = toggle.getAttribute('aria-expanded') === 'true';
      if (expanded) {
        toggle.setAttribute('aria-expanded', 'false');
        chat.hidden = true;
      } else {
        toggle.setAttribute('aria-expanded', 'true');
        chat.hidden = false;
        chat.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
  }

  /* ------------------------------------------------------------------
     4. Chat compose — redirect to register on any interaction.
        Wires the savings card inputs AND the desktop panel inputs.
  ------------------------------------------------------------------ */
  function wireCompose(inputId, sendId) {
    var input   = document.getElementById(inputId);
    var sendBtn = document.getElementById(sendId);
    if (input) {
      input.addEventListener('click', function () { window.location.href = (window.FYNLA_BASE||'')+'/register?from=savetax'; });
      input.addEventListener('focus', function () { window.location.href = (window.FYNLA_BASE||'')+'/register?from=savetax'; });
    }
    if (sendBtn) {
      sendBtn.addEventListener('click', function () { window.location.href = (window.FYNLA_BASE||'')+'/register?from=savetax'; });
    }
  }

  /* ------------------------------------------------------------------
     5. Chat panel interactions (desktop panel)
  ------------------------------------------------------------------ */

  function initChatPanel() {
    var input  = document.getElementById('fyn-input');
    var sendBtn = document.getElementById('fyn-send');
    var messages = document.getElementById('fyn-messages');
    var registerUrl = '/register?from=savetax';

    if (!input || !sendBtn || !messages) { return; }

    /* Input click → redirect to register (input is readonly) */
    input.addEventListener('click', function () {
      window.location.href = registerUrl;
    });

    /* Input focus → also redirect (keyboard users) */
    input.addEventListener('focus', function () {
      window.location.href = registerUrl;
    });

    /* Send / Get started button click */
    sendBtn.addEventListener('click', function () {
      var text = input.value.trim();

      if (!text) {
        /* No text typed — redirect straight to register */
        window.location.href = registerUrl;
        return;
      }

      /* Show the user's message bubble */
      appendUserBubble(messages, text);
      input.value = '';

      /* Show the canned Fyn response after a short simulated delay */
      setTimeout(function () {
        appendFynResponse(messages, registerUrl);
      }, 600);
    });
  }

  function appendUserBubble(container, text) {
    var div = document.createElement('div');
    div.className = 'fyn-panel__message fyn-panel__message--user';
    var p = document.createElement('p');
    p.textContent = text;
    div.appendChild(p);
    container.appendChild(div);
    scrollToBottom(container);
  }

  function appendFynResponse(container, registerUrl) {
    var div = document.createElement('div');
    div.className = 'fyn-panel__message fyn-panel__message--fyn';

    var p = document.createElement('p');
    p.textContent = 'I can give you a personalised tax strategy once you register.';
    div.appendChild(p);

    var cta = document.createElement('a');
    cta.href = registerUrl;
    cta.className = 'fyn-panel__register-cta';
    cta.textContent = 'Register now to ask Fyn';
    /* cta is appended directly to div so it appears below the message bubble */
    div.appendChild(cta);

    container.appendChild(div);
    scrollToBottom(container);
  }

  function scrollToBottom(container) {
    container.scrollTop = container.scrollHeight;
  }

  /* ------------------------------------------------------------------
     Bootstrap — run after DOM is ready
     (script tag has defer so DOM is parsed before this executes)
  ------------------------------------------------------------------ */
  document.addEventListener('DOMContentLoaded', function () {
    initHeroPersonalisation();
    initFynPanel();
    initFynToggle();
    wireCompose('fyn-input-card', 'fyn-send-card'); /* savings card */
    wireCompose('fyn-input',      'fyn-send');       /* desktop panel */
    fetchAllowances();
    initChatPanel();
  });

}());
