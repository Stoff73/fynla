<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="/images/logos/favicon.png" />
  <link rel="icon" type="image/x-icon" href="/images/logos/favicon.ico" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Security &amp; Privacy — Your Data is Protected | Fynla</title>
  <meta name="description" content="Learn how Fynla protects your financial data. AES-256 encryption, multi-factor authentication, GDPR compliance, and UK-hosted servers. Your data is never sold or shared." />
  <link rel="canonical" href="https://fynla.org/security" />

  <!-- Open Graph -->
  <meta property="og:type"        content="website" />
  <meta property="og:title"       content="Security &amp; Privacy — Your Data is Protected | Fynla" />
  <meta property="og:description" content="Learn how Fynla protects your financial data. AES-256 encryption, multi-factor authentication, GDPR compliance, and UK-hosted servers. Your data is never sold or shared." />
  <meta property="og:image"       content="https://fynla.org/images/og/security.jpg" />
  <meta property="og:url"         content="https://fynla.org/security" />

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image" />
  <meta name="twitter:title"       content="Security &amp; Privacy — Your Data is Protected | Fynla" />
  <meta name="twitter:description" content="Learn how Fynla protects your financial data. AES-256 encryption, multi-factor authentication, GDPR compliance, and UK-hosted servers. Your data is never sold or shared." />
  <meta name="twitter:image"       content="https://fynla.org/images/og/security.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB"    href="https://fynla.org/security" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/security" />

  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Security & Privacy — Your Data is Protected",
    "url": "https://fynla.org/security",
    "description": "Learn how Fynla protects your financial data. AES-256 encryption, multi-factor authentication, GDPR compliance, and UK-hosted servers.",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org"
    }
  }
  </script>

  <!-- Critical CSS — above-fold: tokens, reset, skip-nav, nav skeleton, hero -->
  <style>
    :root{--raspberry-300:#F472B6;--raspberry-400:#EC4899;--raspberry-500:#E83E6D;--raspberry-600:#DB2777;--horizon-100:#F1F5F9;--horizon-200:#E2E8F0;--horizon-300:#CBD5E1;--horizon-400:#94A3B8;--horizon-500:#1F2A44;--horizon-600:#0F172A;--horizon-700:#020617;--spring-500:#20B486;--spring-600:#059669;--violet-500:#5854E6;--savannah-100:#FDFAF7;--eggshell-500:#F7F6F4;--neutral-500:#717171;--light-blue-100:#DDE2EF;--light-pink-100:#FAD6E0;--light-pink-200:#F5B3C5;--light-gray:#EEEEEE;--white:#FFFFFF;--white-70:rgba(255,255,255,0.70);--white-60:rgba(255,255,255,0.60);--black-05:rgba(0,0,0,0.05);--font-primary:'Segoe UI','Inter',-apple-system,BlinkMacSystemFont,sans-serif;--radius-md:0.5rem;--radius-lg:0.75rem;--radius-2xl:1rem;--radius-button:0.5rem;--radius-full:9999px;--shadow-sm:0 1px 2px 0 rgba(0,0,0,0.05);}
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
    .security-hero{background:linear-gradient(to right,var(--horizon-500),var(--raspberry-500));overflow:hidden;}
    .security-hero__inner{max-width:80rem;margin:0 auto;padding:2.5rem 1rem;}
    .security-hero__heading{font-size:clamp(2.25rem,8vw,4.5rem);line-height:1;font-weight:900;color:var(--white);margin-bottom:1rem;}
    .security-hero__accent{color:var(--raspberry-300);}
    .security-hero__lead{font-size:1.125rem;color:var(--white-70);max-width:42rem;line-height:1.625;}
    @media(min-width:1024px){.security-hero__inner{padding-left:2rem;padding-right:2rem;}}
  </style>

  <link rel="stylesheet" href="/pages/css/global.css?v=113"    />
  <link rel="stylesheet" href="/pages/css/security.css?v=1"  />
</head>
<body>

  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__ . '/partials/nav.php'; ?>

  <main id="main-content">

    <!-- ================================================================
         HERO  [id=hero]
         ================================================================ -->
    <section id="hero" class="security-hero" aria-labelledby="hero-heading">
      <div class="security-hero__inner">
        <h1 id="hero-heading" class="security-hero__heading">
          Security &amp;<br />
          <span class="security-hero__accent">privacy</span>
        </h1>
        <p class="security-hero__lead">
          We take the security of your financial data seriously. Learn about the measures we have in place to protect your information.
        </p>
      </div>
    </section>

    <!-- ================================================================
         INTRO  [id=intro]
         ================================================================ -->
    <section id="intro" class="security-intro" aria-labelledby="intro-heading">
      <div class="security-intro__inner">
        <p class="security-intro__body">
          Your financial data is among the most sensitive information you have. At Fynla, protecting it isn't just a technical requirement — it's a core value.
          We don't sell your data, we don't share it with third parties, and we don't earn commission from financial product providers.
          Every decision we make about how your data is stored, accessed, and protected is guided by one principle: your information belongs to you.
        </p>
      </div>
    </section>

    <!-- ================================================================
         SECURITY SECTIONS  [id=security-sections]
         ================================================================ -->
    <section id="security-sections" class="security-blocks" aria-label="Security and privacy details">
      <div class="security-blocks__inner">

        <!-- Authentication & account security -->
        <article class="security-block security-block--pink" aria-labelledby="auth-heading">
          <h2 id="auth-heading" class="security-block__heading">Authentication &amp; account security</h2>
          <div class="security-block__grid">

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Multi-factor authentication
              </h3>
              <p class="security-item__body">App-based TOTP authentication with backup recovery codes for secure account access.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Session management
              </h3>
              <p class="security-item__body">View and revoke active sessions, with automatic logout after periods of inactivity.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Brute-force protection
              </h3>
              <p class="security-item__body">Progressive lockout after repeated failed login attempts protects against unauthorised access.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Password security
              </h3>
              <p class="security-item__body">Password breach checking and prevention of password reuse keeps your account secure.</p>
            </article>

          </div>
        </article>

        <!-- Data protection & encryption -->
        <article class="security-block security-block--blue" aria-labelledby="encryption-heading">
          <h2 id="encryption-heading" class="security-block__heading">Data protection &amp; encryption</h2>
          <div class="security-block__grid">

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Encryption at rest
              </h3>
              <p class="security-item__body">AES-256 encryption for sensitive financial fields including balances and account details.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Key management
              </h3>
              <p class="security-item__body">Centralised key management with regular key rotation policies to maintain security.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Encrypted backups
              </h3>
              <p class="security-item__body">All backups are encrypted with secure retention and deletion policies.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Secrets management
              </h3>
              <p class="security-item__body">Production secrets are stored in secure vault services, never in code or configuration files.</p>
            </article>

          </div>
        </article>

        <!-- Access control -->
        <article class="security-block security-block--pink" aria-labelledby="access-heading">
          <h2 id="access-heading" class="security-block__heading">Access control</h2>
          <div class="security-block__grid">

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Role-based access control
              </h3>
              <p class="security-item__body">Distinct permission levels for different user types ensure appropriate access.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Principle of least privilege
              </h3>
              <p class="security-item__body">Users and systems only have access to the minimum data required for their function.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Internal access logging
              </h3>
              <p class="security-item__body">Every access to user data is logged and auditable for complete transparency.</p>
            </article>

          </div>
        </article>

        <!-- Auditability & monitoring -->
        <article class="security-block security-block--blue" aria-labelledby="audit-heading">
          <h2 id="audit-heading" class="security-block__heading">Auditability &amp; monitoring</h2>
          <div class="security-block__grid">

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Comprehensive audit logs
              </h3>
              <p class="security-item__body">All login attempts, data access, and changes to financial plans are logged.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Immutable logs
              </h3>
              <p class="security-item__body">Append-only, tamper-resistant log storage ensures audit trail integrity.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Suspicious activity alerts
              </h3>
              <p class="security-item__body">Automated alerts for unusual behaviour such as unexpected login locations.</p>
            </article>

          </div>
        </article>

        <!-- GDPR & privacy compliance -->
        <article class="security-block security-block--pink" aria-labelledby="gdpr-heading">
          <h2 id="gdpr-heading" class="security-block__heading">GDPR &amp; privacy compliance</h2>
          <div class="security-block__grid">

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Right to erasure
              </h3>
              <p class="security-item__body">Full deletion workflow including removal from backups after retention period.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Data minimisation
              </h3>
              <p class="security-item__body">We only collect and store data that is necessary for your financial planning.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Consent tracking
              </h3>
              <p class="security-item__body">Timestamped consent records for terms, privacy policy, and any marketing preferences.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Data export
              </h3>
              <p class="security-item__body">Export all your data in a portable format at any time from your account settings.</p>
            </article>

          </div>
        </article>

        <!-- API & application security -->
        <article class="security-block security-block--blue" aria-labelledby="api-heading">
          <h2 id="api-heading" class="security-block__heading">API &amp; application security</h2>
          <div class="security-block__grid">

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Rate limiting
              </h3>
              <p class="security-item__body">Per-user and per-token rate limits protect against abuse and ensure fair usage.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Token security
              </h3>
              <p class="security-item__body">Short-lived access tokens with automatic rotation maintain secure sessions.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Permission scoping
              </h3>
              <p class="security-item__body">Read-only versus write permissions ensure tokens only have necessary access.</p>
            </article>

          </div>
        </article>

        <!-- Business continuity -->
        <article class="security-block security-block--pink" aria-labelledby="continuity-heading">
          <h2 id="continuity-heading" class="security-block__heading">Business continuity</h2>
          <div class="security-block__grid">

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Disaster recovery
              </h3>
              <p class="security-item__body">Defined recovery objectives with regular testing ensure rapid restoration.</p>
            </article>

            <article class="security-item">
              <h3 class="security-item__title">
                <svg class="security-item__check" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                Uptime monitoring
              </h3>
              <p class="security-item__body">24/7 monitoring with automatic failover ensures continuous availability.</p>
            </article>

          </div>
        </article>

      </div>
    </section>

    <!-- ================================================================
         IMPORTANT NOTICE  [id=notice]
         ================================================================ -->
    <section id="notice" class="security-notice" aria-labelledby="notice-heading">
      <div class="security-notice__inner">
        <h2 id="notice-heading" class="security-notice__heading">Important notice</h2>
        <p class="security-notice__body">
          Fynla is a financial planning tool designed to help you organise and visualise your financial information. It does not constitute regulated financial advice. The projections and calculations provided are for illustrative purposes only and should not be relied upon as the sole basis for financial decisions.
        </p>
        <p class="security-notice__body">
          We recommend consulting with a qualified financial adviser for personalised advice tailored to your specific circumstances.
        </p>
      </div>
    </section>

    <!-- ================================================================
         CTA  [id=security-cta]
         ================================================================ -->
    <?php
    $module = [
      'id'      => 'security-cta',
      'heading' => 'Have questions about our security practices?',
      'actions' => [
        ['text' => 'Contact us',  'href' => '/contact',  'primary' => true],
        ['text' => 'Get started', 'href' => '/register', 'primary' => false],
      ],
    ];
    include __DIR__ . '/partials/modules/cta-band.php';
    ?>

  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>

  <script src="/pages/js/site.js?v=1" defer></script>
  <script src="/pages/js/security.js?v=1" defer></script>

</body>
</html>
