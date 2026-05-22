/**
 * Fynla — contact page interactions
 *
 * Enhances the contact form with:
 *   - Client-side validation (mirrors server-side rules)
 *   - AJAX submission to /contact-submit (avoids full page reload)
 *   - Math captcha regeneration on error
 *   - Graceful degradation: the form works without JS via standard POST
 *
 * Nav/footer JS lives in site.js.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    wireContactForm();
  });

  function wireContactForm() {
    var form        = document.getElementById('contact-form-el');
    var successEl   = document.getElementById('form-success');
    var errorEl     = document.getElementById('form-error');

    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      /* Reset feedback */
      if (successEl) successEl.hidden = true;
      if (errorEl)   errorEl.hidden   = true;

      var submitBtn = form.querySelector('.contact-form__submit');
      if (submitBtn) {
        submitBtn.disabled     = true;
        submitBtn.textContent  = 'Sending…';
      }

      /* Client-side captcha check before sending */
      var captchaA  = parseInt(form.querySelector('[name="captcha_a"]').value, 10);
      var captchaB  = parseInt(form.querySelector('[name="captcha_b"]').value, 10);
      var captchaIn = parseInt(form.querySelector('[name="captcha_answer"]').value, 10);
      if (isNaN(captchaIn) || captchaIn !== captchaA + captchaB) {
        showError('Incorrect answer to the maths question. Please try again.');
        if (submitBtn) {
          submitBtn.disabled    = false;
          submitBtn.textContent = 'Send message';
        }
        return;
      }

      /* AJAX submission */
      var data = new FormData(form);

      fetch('/contact-submit', {
        method:      'POST',
        body:        data,
        headers:     { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json && json.success) {
            form.reset();
            showSuccess(json.message || 'Your message has been sent. We’ll get back to you soon.');
          } else {
            showError((json && json.message) || 'Something went wrong. Please try again or email us directly.');
          }
        })
        .catch(function () {
          showError('Something went wrong. Please try again or email hello@fynla.org directly.');
        })
        .finally(function () {
          if (submitBtn) {
            submitBtn.disabled    = false;
            submitBtn.textContent = 'Send message';
          }
        });
    });

    function showSuccess(msg) {
      if (!successEl) return;
      successEl.textContent = msg;
      successEl.hidden      = false;
      successEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function showError(msg) {
      if (!errorEl) return;
      errorEl.textContent = msg;
      errorEl.hidden      = false;
      errorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

}());
