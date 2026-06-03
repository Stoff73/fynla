<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contact &amp; Support | Fynla</title>
  <meta name="description" content="Get in touch with the Fynla team. Questions about your account, technical support, or general enquiries â€” we'd love to hear from you." />
  <link rel="canonical" href="https://fynla.org/contact" />

  <!-- Open Graph -->
  <meta property="og:type"        content="website" />
  <meta property="og:title"       content="Contact &amp; Support | Fynla" />
  <meta property="og:description" content="Get in touch with the Fynla team. Questions about your account, technical support, or general enquiries â€” we'd love to hear from you." />
  <meta property="og:image"       content="https://fynla.org/images/og/contact.jpg" />
  <meta property="og:url"         content="https://fynla.org/contact" />

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image" />
  <meta name="twitter:title"       content="Contact &amp; Support | Fynla" />
  <meta name="twitter:description" content="Get in touch with the Fynla team. Questions about your account, technical support, or general enquiries â€” we'd love to hear from you." />
  <meta name="twitter:image"       content="https://fynla.org/images/og/contact.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB"    href="https://fynla.org/contact" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/contact" />

  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "ContactPage",
    "name": "Contact & Support",
    "url": "https://fynla.org/contact",
    "description": "Get in touch with the Fynla team for questions, technical support, or general enquiries.",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org",
      "email": "hello@fynla.org"
    }
  }
  </script>

  <!-- Critical CSS â€” above-fold: tokens, reset, skip-nav, nav skeleton, hero -->
  <style>
    :root{--raspberry-300:#F472B6;--raspberry-400:#EC4899;--raspberry-500:#E83E6D;--raspberry-600:#DB2777;--horizon-100:#F1F5F9;--horizon-200:#E2E8F0;--horizon-300:#CBD5E1;--horizon-400:#94A3B8;--horizon-500:#1F2A44;--horizon-600:#0F172A;--horizon-700:#020617;--spring-500:#20B486;--spring-600:#059669;--violet-500:#5854E6;--savannah-100:#FDFAF7;--eggshell-500:#F7F6F4;--neutral-500:#717171;--light-blue-100:#DDE2EF;--light-pink-100:#FAD6E0;--light-pink-200:#F5B3C5;--light-gray:#EEEEEE;--white:#FFFFFF;--white-80:rgba(255,255,255,0.80);--white-70:rgba(255,255,255,0.70);--white-60:rgba(255,255,255,0.60);--black-05:rgba(0,0,0,0.05);--font-primary:'Segoe UI','Inter',-apple-system,BlinkMacSystemFont,sans-serif;--radius-md:0.5rem;--radius-lg:0.75rem;--radius-2xl:1rem;--radius-button:0.5rem;--radius-full:9999px;--shadow-sm:0 1px 2px 0 rgba(0,0,0,0.05);}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    [hidden]{display:none !important;}
    html{scroll-behavior:smooth;}
    body{font-family:var(--font-primary);background:var(--eggshell-500);color:var(--horizon-500);line-height:1.5;min-height:100vh;}
    img{display:block;max-width:100%;}
    a{color:inherit;text-decoration:none;}
    ul{list-style:none;}
    button{cursor:pointer;font-family:inherit;}
    .skip-nav{position:absolute;top:-100%;left:1rem;background:var(--raspberry-500);color:var(--white);padding:0.5rem 1rem;border-radius:var(--radius-md);font-weight:600;z-index:9999;transition:top 0.2s;}
    .skip-nav:focus{top:1rem;}
    .site-header{position:sticky;top:0;z-index:50;background:var(--white);box-shadow:var(--shadow-sm);border-bottom:1px solid var(--light-gray);}
    .nav-primary__inner{max-width:80rem;margin:0 auto;padding:0 1rem;display:flex;align-items:center;justify-content:flex-start;height:4rem;position:relative;}
    /* Hero critical */
    .contact-hero{background:linear-gradient(to right,var(--horizon-500),var(--raspberry-500));overflow:hidden;}
    .contact-hero__inner{max-width:80rem;margin:0 auto;padding:2.5rem 1rem;}
    .contact-hero__heading{font-size:clamp(2.25rem,8vw,4.5rem);line-height:1;font-weight:900;color:var(--white);margin-bottom:1rem;}
    .contact-hero__accent{color:var(--raspberry-300);}
    .contact-hero__lead{font-size:1.125rem;color:var(--white-70);max-width:42rem;line-height:1.625;}
    @media(min-width:1024px){.contact-hero__inner{padding-left:2rem;padding-right:2rem;}}
  </style>

  <link rel="stylesheet" href="/pages/css/global.css?v=113"   />
  <link rel="stylesheet" href="/pages/css/contact.css?v=1"  />
</head>
<body>

  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__ . '/partials/nav.php'; ?>

  <main id="main-content">

    <!-- ================================================================
         HERO  [id=hero]
         ================================================================ -->
    <section id="hero" class="contact-hero" aria-labelledby="hero-heading">
      <div class="contact-hero__inner">
        <h1 id="hero-heading" class="contact-hero__heading">
          Get in <span class="contact-hero__accent">touch</span>
        </h1>
        <p class="contact-hero__lead">
          Questions, feedback, or just want to say hello? We would love to hear from you.
        </p>
      </div>
    </section>

    <!-- ================================================================
         CONTACT FORM  [id=contact-form]
         The Vue page used api.post('/contact'). The static page submits
         the form to the Laravel /contact-submit route which handles
         the POST server-side. A math captcha is rendered via PHP to
         prevent automated submissions without requiring JS.
         ================================================================ -->
    <section id="contact-form" class="contact-form-section" aria-labelledby="form-heading">
      <div class="contact-form-section__inner">
        <h2 id="form-heading" class="contact-form-section__heading">Send us a message</h2>

        <?php
        /* Simple server-side math captcha â€” generate two random numbers.
           The POST handler in Laravel will validate the answer. */
        $captchaA = rand(1, 10);
        $captchaB = rand(1, 10);
        ?>

        <form
          class="contact-form"
          method="POST"
          action="/contact-submit"
          novalidate
          id="contact-form-el"
        >
          <?php if (function_exists('csrf_field')): ?>
            <?= csrf_field() ?>
          <?php else: ?>
            <!-- CSRF token is injected by the Laravel route handler -->
          <?php endif; ?>

          <!-- Name -->
          <div class="contact-form__field">
            <label class="contact-form__label" for="contact-name">
              Name <span class="contact-form__required" aria-label="required">*</span>
            </label>
            <input
              class="contact-form__input"
              id="contact-name"
              name="name"
              type="text"
              autocomplete="name"
              required
              placeholder="Your name"
            />
          </div>

          <!-- Email -->
          <div class="contact-form__field">
            <label class="contact-form__label" for="contact-email">
              Email <span class="contact-form__required" aria-label="required">*</span>
            </label>
            <input
              class="contact-form__input"
              id="contact-email"
              name="email"
              type="email"
              autocomplete="email"
              required
              placeholder="your@email.com"
            />
          </div>

          <!-- Reason -->
          <div class="contact-form__field">
            <label class="contact-form__label" for="contact-reason">
              Reason for contact <span class="contact-form__required" aria-label="required">*</span>
            </label>
            <select class="contact-form__input contact-form__select" id="contact-reason" name="reason" required>
              <option value="" disabled selected>Select a reason</option>
              <option value="general">General enquiry</option>
              <option value="support">Technical support</option>
              <option value="press">Press and media</option>
            </select>
          </div>

          <!-- Message -->
          <div class="contact-form__field">
            <label class="contact-form__label" for="contact-message">
              Message <span class="contact-form__required" aria-label="required">*</span>
            </label>
            <textarea
              class="contact-form__input contact-form__textarea"
              id="contact-message"
              name="message"
              rows="5"
              required
              placeholder="How can we help?"
            ></textarea>
          </div>

          <!-- Math captcha â€” rendered server-side -->
          <div class="contact-form__field">
            <label class="contact-form__label" for="contact-captcha">
              Verify you're human â€” what is <?= (int)$captchaA ?> + <?= (int)$captchaB ?>?
              <span class="contact-form__required" aria-label="required">*</span>
            </label>
            <input type="hidden" name="captcha_a" value="<?= (int)$captchaA ?>" />
            <input type="hidden" name="captcha_b" value="<?= (int)$captchaB ?>" />
            <input
              class="contact-form__input contact-form__input--narrow"
              id="contact-captcha"
              name="captcha_answer"
              type="number"
              required
              placeholder="?"
              min="0"
              max="20"
            />
          </div>

          <!-- Submit -->
          <div class="contact-form__actions">
            <button type="submit" class="contact-form__submit">Send message</button>
          </div>

          <!-- Status messages â€” shown by JS after AJAX, or by server redirect -->
          <p class="contact-form__success" id="form-success" hidden>Your message has been sent. We'll get back to you soon.</p>
          <p class="contact-form__error"   id="form-error"   hidden>Something went wrong. Please try again or email us directly.</p>
        </form>

        <aside class="contact-direct" aria-label="Direct email addresses">
          <p class="contact-direct__note">Or email us directly:</p>
          <a href="mailto:hello@fynla.org" class="contact-direct__link">hello@fynla.org</a>
        </aside>
      </div>
    </section>

    <!-- ================================================================
         FAQ, DEMO, ASK FYN  [id=contact-panels]
         ================================================================ -->
    <section id="contact-panels" class="contact-panels" aria-labelledby="panels-heading">
      <div class="contact-panels__inner">
        <h2 id="panels-heading" class="contact-panels__heading visually-hidden">Other ways to get help</h2>
        <div class="contact-panels__grid">

          <article class="contact-panel">
            <h2 class="contact-panel__title">Frequently asked questions</h2>
            <p class="contact-panel__body">
              Many common questions are answered in our FAQ. It covers pricing, data security, supported features, and more.
            </p>
            <a href="/faq" class="contact-panel__btn">View FAQ</a>
          </article>

          <article class="contact-panel">
            <h2 class="contact-panel__title">Try the demo</h2>
            <p class="contact-panel__body">
              Want to see Fynla in action before getting in touch? Our interactive demo lets you explore the full platform with sample data &mdash; no sign-up required.
            </p>
            <a href="/?demo=true" class="contact-panel__btn">Launch demo</a>
          </article>

          <article class="contact-panel">
            <h2 class="contact-panel__title">Ask Fyn</h2>
            <p class="contact-panel__body">
              Don't want to wait for a human? You can always ask Fyn. Our AI assistant answers financial planning questions instantly.
            </p>
            <a href="/?demo=true" class="contact-panel__btn">Ask Fyn</a>
          </article>

        </div>
      </div>
    </section>

    <!-- ================================================================
         EMAIL BOXES  [id=contact-emails]
         ================================================================ -->
    <section id="contact-emails" class="contact-emails" aria-labelledby="emails-heading">
      <div class="contact-emails__inner">
        <h2 id="emails-heading" class="contact-emails__heading">Contact us the traditional way</h2>
        <div class="contact-emails__grid">

          <article class="contact-email-card">
            <p class="contact-email-card__title">General enquiries</p>
            <a href="mailto:hello@fynla.org" class="contact-email-card__address">hello@fynla.org</a>
            <p class="contact-email-card__note">Questions about Fynla, partnerships, or anything else.</p>
          </article>

          <article class="contact-email-card">
            <p class="contact-email-card__title">Technical support</p>
            <a href="mailto:support@fynla.org" class="contact-email-card__address">support@fynla.org</a>
            <p class="contact-email-card__note">Help with your account, data, or technical issues.</p>
          </article>

          <article class="contact-email-card">
            <p class="contact-email-card__title">Marketing and media</p>
            <a href="mailto:marketing@fynla.org" class="contact-email-card__address">marketing@fynla.org</a>
            <p class="contact-email-card__note">Partnerships, press enquiries, and media resources.</p>
          </article>

        </div>
      </div>
    </section>

    <!-- ================================================================
         CTA  [id=start-planning]
         ================================================================ -->
    <?php
    $module = [
      'id'      => 'start-planning',
      'heading' => 'Just want to start planning?',
      'subtext' => 'Create your free account and see where you stand financially.',
      'actions' => [
        ['text' => 'Get started', 'href' => '/register', 'primary' => true],
      ],
    ];
    include __DIR__ . '/partials/modules/cta-band.php';
    ?>

  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>

  <script src="/pages/js/site.js?v=1" defer></script>
  <script src="/pages/js/contact.js?v=1" defer></script>

</body>
</html>
