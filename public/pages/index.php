<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Fyn, your financial companion | Fynla</title>
  <meta name="description" content="Fynla is a UK personal finance platform that helps you plan savings, investments, pensions, retirement and estate. See your complete financial picture in one place." />
  <link rel="canonical" href="https://fynla.org/" />

  <!-- Open Graph -->
  <meta property="og:type" content="website" />
  <meta property="og:title" content="Fyn, your financial companion | Fynla" />
  <meta property="og:description" content="Fynla is a UK personal finance platform that helps you plan savings, investments, pensions, retirement and estate. See your complete financial picture in one place." />
  <meta property="og:image" content="https://fynla.org/images/og/index.jpg" />
  <meta property="og:url" content="https://fynla.org/" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Fyn, your financial companion | Fynla" />
  <meta name="twitter:description" content="Fynla is a UK personal finance platform that helps you plan savings, investments, pensions, retirement and estate. See your complete financial picture in one place." />
  <meta name="twitter:image" content="https://fynla.org/images/og/index.jpg" />

  <!-- hreflang -->
  <link rel="alternate" hreflang="en-GB" href="https://fynla.org/" />
  <link rel="alternate" hreflang="x-default" href="https://fynla.org/" />

  <!-- Preload LCP hero image -->
  <link rel="preload" as="image" href="/images/Website/Homepage-Header-Desktopv3.png" fetchpriority="high" />

  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Fyn, your financial companion",
    "url": "https://fynla.org/",
    "description": "Fynla is a UK personal finance platform that helps you plan savings, investments, pensions, retirement and estate.",
    "publisher": {
      "@type": "Organization",
      "name": "Fynla",
      "url": "https://fynla.org"
    }
  }
  </script>

  <style>
    /* ===================================================================
       DESIGN TOKENS — all hex values defined here once; nowhere else
       =================================================================== */
    :root {
      /* Raspberry — CTAs, errors, highlights */
      --raspberry-300: #F472B6;
      --raspberry-400: #EC4899;
      --raspberry-500: #E83E6D;
      --raspberry-600: #DB2777;

      /* Horizon — text, nav, headings */
      --horizon-100: #F1F5F9;
      --horizon-200: #E2E8F0;
      --horizon-300: #CBD5E1;
      --horizon-400: #94A3B8;
      --horizon-500: #1F2A44;
      --horizon-600: #0F172A;
      --horizon-700: #020617;

      /* Spring — success, positive CTAs */
      --spring-400: #34D399;
      --spring-500: #20B486;
      --spring-600: #059669;
      --spring-700: #047857;

      /* Violet — warnings, focus */
      --violet-500: #5854E6;

      /* Savannah — hover, subtle backgrounds */
      --savannah-100: #FDFAF7;
      --savannah-200: #FAF5F0;
      --savannah-300: #F5EDE5;
      --savannah-400: #EFDCD1;
      --savannah-500: #E6C9A8;

      /* Eggshell — page background */
      --eggshell-500: #F7F6F4;

      /* Neutrals */
      --neutral-400: #9CA3AF;
      --neutral-500: #717171;
      --neutral-600: #4B5563;

      /* Light Blue */
      --light-blue-100: #DDE2EF;
      --light-blue-500: #6C83BC;

      /* Light Pink */
      --light-pink-50:  #FDF0F4;
      --light-pink-100: #FAD6E0;
      --light-pink-200: #F5B3C5;

      /* Utility */
      --light-gray: #EEEEEE;
      --white:      #FFFFFF;

      /* Alpha variants (rgba overlays — defined once here, used via var()) */
      --white-80:       rgba(255, 255, 255, 0.80);
      --white-70:       rgba(255, 255, 255, 0.70);
      --white-40:       rgba(255, 255, 255, 0.40);
      --white-30:       rgba(255, 255, 255, 0.30);
      --white-25:       rgba(255, 255, 255, 0.25);
      --white-20:       rgba(255, 255, 255, 0.20);
      --white-15:       rgba(255, 255, 255, 0.15);
      --white-10:       rgba(255, 255, 255, 0.10);
      --white-8:        rgba(255, 255, 255, 0.08);
      --white-12:       rgba(255, 255, 255, 0.12);
      --white-90:       rgba(255, 255, 255, 0.90);
      --horizon-500-30: rgba(31,  42,  68,  0.30);
      --horizon-500-20: rgba(31,  42,  68,  0.20);
      --horizon-700-60: rgba(2,   6,   23,  0.60);
      --horizon-700-80: rgba(2,   6,   23,  0.80);
      --black-05:       rgba(0,   0,   0,   0.05);
      --black-10:       rgba(0,   0,   0,   0.10);

      /* Yellow (star ratings) */
      --yellow-400: #FBBF24;

      /* Typography */
      --font-primary: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;

      /* Radius */
      --radius-sm:     0.375rem;
      --radius-md:     0.5rem;
      --radius-lg:     0.75rem;
      --radius-xl:     0.75rem;
      --radius-2xl:    1rem;
      --radius-3xl:    1.5rem;
      --radius-button: 0.5rem;
      --radius-card:   0.75rem;
      --radius-full:   9999px;

      /* Shadows */
      --shadow-sm: 0 1px 2px 0 var(--black-05);
      --shadow-md: 0 4px 6px -1px var(--black-10), 0 2px 4px -2px var(--black-10);
      --shadow-lg: 0 10px 15px -3px var(--black-10), 0 4px 6px -4px var(--black-10);
    }

    /* ===================================================================
       RESET & BASE
       =================================================================== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html  { scroll-behavior: smooth; }
    body  { font-family: var(--font-primary); background: var(--eggshell-500); color: var(--horizon-500); line-height: 1.5; min-height: 100vh; }
    img   { display: block; max-width: 100%; }
    a     { color: inherit; text-decoration: none; }
    ul    { list-style: none; }
    button { cursor: pointer; font-family: inherit; }

    /* ===================================================================
       SKIP NAV
       =================================================================== */
    .skip-nav {
      position: absolute; top: -100%; left: 1rem;
      background: var(--raspberry-500); color: var(--white);
      padding: 0.5rem 1rem; border-radius: var(--radius-md);
      font-weight: 600; z-index: 9999; transition: top 0.2s;
    }
    .skip-nav:focus { top: 1rem; }

    /* ===================================================================
       NAV — styles for partials/nav.php
       =================================================================== */
    .site-header {
      position: sticky; top: 0; z-index: 50;
      background: var(--white);
      box-shadow: var(--shadow-sm);
      border-bottom: 1px solid var(--light-gray);
    }
    .nav-primary__inner {
      max-width: 80rem; margin: 0 auto; padding: 0 1rem;
      display: flex; align-items: center; justify-content: space-between;
      height: 4rem; position: relative;
    }
    .nav-logo__img { height: 3.5rem; width: auto; }
    .nav-desktop   { display: none; align-items: center; gap: 1.5rem; margin-left: 2rem; }
    .nav-link {
      display: inline-flex; align-items: center; gap: 0.25rem;
      padding: 0.25rem; font-size: 0.875rem; font-weight: 500;
      color: var(--neutral-500); background: none; border: none;
      transition: color 0.15s ease;
    }
    .nav-link:hover, .nav-link.is-active { color: var(--raspberry-500); }
    .nav-link__chevron { transition: transform 0.2s ease; flex-shrink: 0; }
    .nav-dropdown { position: relative; }

    /* Mega menu */
    .mega-menu {
      position: absolute; top: calc(100% + 0.5rem);
      left: 50%; transform: translateX(-50%);
      width: min(60rem, calc(100vw - 2rem));
      background: var(--white); border-radius: var(--radius-xl);
      box-shadow: var(--shadow-lg); border: 1px solid var(--light-gray);
      padding: 1.5rem; z-index: 50;
    }
    .mega-menu__inner  { display: flex; gap: 2rem; }
    .mega-menu__col--fixed { width: 16rem; flex-shrink: 0; }
    .mega-menu__col--grow  { flex: 1; }
    .mega-menu__divider    { width: 1px; background: var(--neutral-400); opacity: 0.3; }
    .mega-menu__label {
      font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
      letter-spacing: 0.05em; color: var(--neutral-600); margin-bottom: 1rem;
    }
    .mega-menu__links { display: flex; flex-direction: column; gap: 0.5rem; }
    .mega-menu__grid  { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
    .mega-menu__grid--full { grid-template-columns: repeat(3, 1fr); }
    .mega-menu__item {
      display: flex; align-items: flex-start; gap: 0.75rem;
      padding: 0.75rem; border-radius: var(--radius-lg);
      background: var(--eggshell-500); transition: background 0.15s ease;
    }
    .mega-menu__item:hover { background: var(--light-pink-100); }
    .mega-menu__icon { color: var(--horizon-500); flex-shrink: 0; margin-top: 0.125rem; }
    .mega-menu__item-title {
      display: block; font-size: 1rem; font-weight: 700; color: var(--horizon-500);
      transition: color 0.15s ease;
    }
    .mega-menu__item:hover .mega-menu__item-title { color: var(--raspberry-500); }
    .mega-menu__item-sub {
      display: block; font-size: 0.75rem; color: var(--neutral-500);
      margin-top: 0.25rem; line-height: 1.4;
    }

    /* Sign in */
    .nav-actions { display: none; align-items: center; }
    .nav-signin-btn {
      display: inline-flex; align-items: center; gap: 0.5rem;
      padding: 0.5rem 0.75rem; border-radius: var(--radius-md);
      font-size: 0.875rem; font-weight: 500;
      color: var(--horizon-500); background: var(--light-pink-100);
      transition: background 0.15s ease;
    }
    .nav-signin-btn:hover { background: var(--light-pink-200); }

    /* Hamburger */
    .nav-hamburger {
      display: flex; align-items: center; justify-content: center;
      padding: 0.5rem; border-radius: var(--radius-md);
      background: none; border: none; color: var(--horizon-400);
      transition: background 0.15s ease, color 0.15s ease;
    }
    .nav-hamburger:hover { background: var(--savannah-100); color: var(--neutral-500); }
    .hamburger-icon--close { display: none; }
    .nav-hamburger[aria-expanded="true"] .hamburger-icon--open  { display: none; }
    .nav-hamburger[aria-expanded="true"] .hamburger-icon--close { display: block; }

    /* Mobile menu */
    .mobile-menu { border-top: 1px solid var(--light-gray); background: var(--white); }
    .mobile-menu__body { padding: 0.5rem 0 0.75rem; }
    .mobile-menu__link {
      display: block; padding: 0.5rem 1rem 0.5rem 0.75rem;
      font-size: 1rem; font-weight: 500; color: var(--horizon-500);
      transition: background 0.15s ease, color 0.15s ease;
    }
    .mobile-menu__link:hover { background: var(--savannah-100); color: var(--raspberry-500); }
    .mobile-accordion__trigger {
      display: flex; width: 100%; align-items: center; justify-content: space-between;
      padding: 0.5rem 1rem 0.5rem 0.75rem; font-size: 1rem; font-weight: 500;
      color: var(--horizon-500); background: none; border: none; text-align: left;
      transition: background 0.15s ease, color 0.15s ease;
    }
    .mobile-accordion__trigger:hover { background: var(--savannah-100); color: var(--raspberry-500); }
    .mobile-accordion__chevron { transition: transform 0.2s ease; flex-shrink: 0; }
    .mobile-accordion__panel  { padding-left: 1.5rem; padding-bottom: 0.25rem; }
    .mobile-menu__sublink {
      display: block; padding: 0.375rem 0; font-size: 0.875rem;
      color: var(--neutral-500); transition: color 0.15s ease;
    }
    .mobile-menu__sublink:hover { color: var(--raspberry-500); }
    .mobile-menu__sublabel {
      font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
      letter-spacing: 0.05em; color: var(--neutral-400);
      padding-top: 0.5rem; padding-bottom: 0.25rem;
    }

    /* ===================================================================
       FOOTER — styles for partials/footer.php
       =================================================================== */
    .site-footer {
      background: linear-gradient(to right, var(--horizon-600), var(--horizon-700));
      padding-top: 10rem;
    }
    .site-footer__inner { max-width: 80rem; margin: 0 auto; padding: 0 1rem 3rem; }
    .site-footer__grid  { display: grid; grid-template-columns: 1fr; gap: 2rem; }
    .site-footer__logo  { height: 3.5rem; width: auto; margin-bottom: 1rem; margin-top: -0.75rem; }
    .site-footer__tagline { font-size: 0.875rem; color: var(--white-70); line-height: 1.625; }
    .site-footer__heading { font-size: 0.875rem; font-weight: 700; color: var(--white); margin-bottom: 1rem; }
    .site-footer__list  { display: flex; flex-direction: column; gap: 0.5rem; }
    .site-footer__link  { font-size: 0.875rem; color: var(--white-70); transition: color 0.15s ease; }
    .site-footer__link:hover { color: var(--white); }
    .site-footer__bottom {
      border-top: 1px solid var(--white-20); margin-top: 2rem; padding-top: 2rem;
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 1rem;
    }
    .site-footer__copy        { font-size: 0.875rem; color: var(--white-70); }
    .site-footer__social      { display: flex; align-items: center; gap: 1rem; }
    .site-footer__social-link { color: var(--white-70); transition: color 0.15s ease; }
    .site-footer__social-link:hover { color: var(--white); }

    /* ===================================================================
       HERO MODULE  [id=hero]
       =================================================================== */
    .hero { background: linear-gradient(to right, var(--horizon-500), var(--raspberry-500)); overflow-x: hidden; }
    .hero__inner { max-width: 80rem; margin: 0 auto; padding: 2.5rem 1rem 0; }
    .hero__heading {
      position: relative; z-index: 1;
      font-size: clamp(2.25rem, 8vw, 5rem); line-height: 1.1;
      margin-bottom: 0.75rem; color: var(--white); font-weight: 900;
    }
    .hero__heading-accent { color: var(--raspberry-300); }
    .hero__body {
      position: relative; z-index: 1;
      color: var(--white-80); margin-bottom: 1rem;
      max-width: 42rem; line-height: 1.625;
    }
    .hero__cta { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: flex-start; gap: 0.75rem; }
    .btn-cta-primary {
      display: inline-block; padding: 0.625rem 4rem;
      font-size: 1.125rem; font-weight: 500;
      color: var(--white); background: var(--spring-500);
      border-radius: var(--radius-button); border: none;
      transition: background-color 0.15s ease; white-space: nowrap;
    }
    .btn-cta-primary:hover { background: var(--spring-600); }
    .hero__sublinks {
      font-size: 0.875rem; color: var(--white-70);
      display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;
    }
    .hero__sublink     { color: var(--white-90); transition: color 0.15s ease; }
    .hero__sublink:hover { color: var(--spring-400); }
    .hero__sublink-sep { color: var(--white-40); }

    /* Mobile caption panels */
    .hero__mobile-panels { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1.5rem; padding-bottom: 1.5rem; }
    .hero__panel         { background: var(--white); border-radius: var(--radius-md); padding: 1rem; box-shadow: var(--shadow-sm); }
    .hero__panel-title   { font-size: 1.125rem; font-weight: 700; color: var(--horizon-500); margin-bottom: 0.25rem; }
    .hero__panel-body    { font-size: 0.875rem; color: var(--neutral-500); line-height: 1.25; }
    .hero__panel--brain  { display: flex; justify-content: center; }
    .hero__panel--brain img { width: 7rem; border-radius: var(--radius-md); }

    /* Desktop composite */
    .hero__desktop-composite {
      display: none; position: relative; overflow: hidden; margin: 0 auto;
      width: 108%; margin-left: -4%; margin-top: -80px; margin-bottom: 8px;
    }
    .hero__desktop-img  { width: 100%; height: auto; display: block; }
    .hero__caption      { position: absolute; padding: 0.75rem 1rem; }
    .hero__caption--left  { top: 62px; left: 4%; max-width: 28%; padding-top: 54px; }
    .hero__caption--center {
      top: 56px; bottom: 0; left: 50%; transform: translateX(-50%);
      max-width: 28%; text-align: center;
      display: flex; flex-direction: column; overflow: hidden;
    }
    .hero__caption--right { top: 62px; right: 4%; max-width: 28%; padding-top: 54px; text-align: right; }
    .hero__caption-title  { font-size: 1.5rem; font-weight: 700; color: var(--horizon-500); margin-bottom: 0.25rem; }
    .hero__caption-body   { font-size: 0.75rem; color: var(--neutral-500); line-height: 1.25; }
    .hero__caption-brain  { flex: 1; display: flex; align-items: flex-end; justify-content: center; overflow: hidden; margin-top: 0.25rem; }
    .hero__caption-brain img { width: auto; max-width: 100%; max-height: 100%; object-fit: contain; object-position: bottom; }

    /* ===================================================================
       MEET FYN MODULE  [id=meet-fyn]
       =================================================================== */
    .meet-fyn { background: var(--light-pink-100); padding-top: 1.5rem; padding-bottom: 2rem; }
    .meet-fyn__inner {
      max-width: 80rem; margin: 0 auto; padding: 0 1rem;
      display: flex; flex-direction: column; gap: 2.5rem;
    }
    .meet-fyn__content { flex: 1; }
    .meet-fyn__mobile-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 0.25rem; }
    .meet-fyn__mobile-char   { height: 7rem; width: auto; margin-bottom: -0.5rem; }
    .meet-fyn__heading { font-size: clamp(3rem, 10vw, 6rem); font-weight: 700; color: var(--horizon-500); line-height: 1; margin-bottom: 0.25rem; }
    .meet-fyn__subheading { font-size: clamp(1.25rem, 4vw, 2.25rem); font-weight: 600; color: var(--neutral-500); margin-bottom: 0.5rem; }
    .meet-fyn__body { font-size: 0.875rem; color: var(--neutral-500); max-width: 42rem; line-height: 1.625; margin-bottom: 1.25rem; }

    .fyn-accordion { max-width: 42rem; margin-bottom: 1.25rem; }
    .fyn-accordion__trigger {
      display: flex; align-items: center; gap: 0.5rem;
      font-size: 0.875rem; font-weight: 600; color: var(--horizon-500);
      background: none; border: none; padding: 0; transition: color 0.15s ease;
    }
    .fyn-accordion__trigger:hover { color: var(--raspberry-500); }
    .fyn-accordion__chevron { transition: transform 0.2s ease; flex-shrink: 0; }
    .fyn-accordion__panel {
      margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;
      overflow: hidden; max-height: 0; transition: max-height 0.3s ease;
    }
    .fyn-accordion__panel.is-open { max-height: 20rem; }
    .fyn-accordion__item { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.875rem; color: var(--neutral-500); }
    .fyn-accordion__check { color: var(--spring-500); flex-shrink: 0; margin-top: 0.125rem; }

    .btn-fyn-start {
      display: inline-block; padding: 0.75rem 3rem; font-size: 1.125rem; font-weight: 500;
      color: var(--white); background: var(--light-blue-500);
      border-radius: var(--radius-button); border: none;
      transition: opacity 0.15s ease; white-space: nowrap;
    }
    .btn-fyn-start:hover { opacity: 0.9; }

    .meet-fyn__character { display: none; align-items: center; justify-content: flex-end; flex-shrink: 0; }
    .meet-fyn__character img { width: auto; height: 427px; margin-bottom: -3rem; }

    /* ===================================================================
       FEATURE GRID MODULE  [id=features]
       =================================================================== */
    .feature-grid { background: linear-gradient(to right, var(--horizon-600), var(--horizon-700)); padding: 2.5rem 0; }
    .feature-grid__inner { max-width: 80rem; margin: 0 auto; padding: 0 1rem; }
    .feature-grid__heading { font-size: clamp(1.25rem, 4vw, 2.25rem); text-align: center; margin-bottom: 1rem; color: var(--white); }
    .feature-grid__intro { text-align: center; color: var(--white-70); font-size: 0.875rem; max-width: 42rem; margin: 0 auto 2.5rem; line-height: 1.625; }
    .feature-grid__cards { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
    .feature-card { background: var(--horizon-500); border-radius: var(--radius-card); padding: 1.5rem; transition: all 0.2s ease; }
    .feature-card__icon-wrap {
      width: 2.5rem; height: 2.5rem; border-radius: var(--radius-full);
      display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;
    }
    .feature-card__icon-wrap--raspberry { background: var(--raspberry-500); }
    .feature-card__icon-wrap--spring    { background: var(--spring-500); }
    .feature-card__icon-wrap--violet    { background: var(--violet-500); }
    .feature-card__icon-wrap--blue      { background: var(--light-blue-500); }
    .feature-card__icon-wrap--savannah  { background: var(--savannah-500); }
    .feature-card__icon-wrap--slate     { background: var(--horizon-400); }
    .feature-card__icon  { color: var(--white); }
    .feature-card__title { color: var(--white); margin-bottom: 0.5rem; font-size: 1rem; font-weight: 600; }
    .feature-card__body  { font-size: 0.875rem; color: var(--horizon-300); line-height: 1.625; margin-bottom: 1rem; }
    .feature-card__tags  { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .feature-card__tag   { display: inline-flex; padding: 0.125rem 0.5rem; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 500; background: var(--white-10); color: var(--white-70); }
    .feature-grid__footer { text-align: center; margin-top: 2.5rem; display: flex; align-items: center; justify-content: center; gap: 0.75rem; }
    .feature-grid__footer-link { color: var(--white); font-weight: 500; transition: color 0.15s ease; }
    .feature-grid__footer-link:hover { color: var(--white-70); }
    .feature-grid__footer-sep { color: var(--white-30); }

    /* ===================================================================
       DASHBOARD VIDEO MODULE  [id=dashboard]
       =================================================================== */
    .dashboard-preview { background: var(--eggshell-500); padding: 2.5rem 0; }
    .dashboard-preview__inner { max-width: 80rem; margin: 0 auto; padding: 0 1rem; }
    .dashboard-preview__heading { font-size: clamp(1.25rem, 4vw, 2.25rem); text-align: center; margin-bottom: 3rem; color: var(--horizon-500); }
    .dashboard-preview__video-wrap {
      position: relative; border-radius: var(--radius-2xl);
      overflow: hidden; box-shadow: var(--shadow-lg); cursor: pointer;
    }
    .dashboard-preview__video { width: 100%; height: auto; display: block; }
    .dashboard-preview__overlay {
      position: absolute; inset: 0; background: var(--horizon-500-30);
      display: flex; align-items: center; justify-content: center;
      transition: background 0.15s ease;
    }
    .dashboard-preview__video-wrap:hover .dashboard-preview__overlay { background: var(--horizon-500-20); }
    .dashboard-preview__play-btn {
      width: 5rem; height: 5rem; border-radius: var(--radius-full);
      background: var(--white-90); display: flex; align-items: center; justify-content: center;
      box-shadow: var(--shadow-lg); transition: transform 0.15s ease;
    }
    .dashboard-preview__video-wrap:hover .dashboard-preview__play-btn { transform: scale(1.1); }
    .dashboard-preview__play-icon { color: var(--raspberry-500); margin-left: 0.25rem; }

    /* ===================================================================
       REVIEW CAROUSEL MODULE  [id=reviews]
       =================================================================== */
    .review-carousel { background: var(--horizon-500); padding: 3rem 0; }
    .review-carousel__inner { max-width: 80rem; margin: 0 auto; padding: 0 1rem; }
    .review-carousel__heading { font-size: clamp(1.25rem, 4vw, 2.25rem); font-weight: 700; color: var(--white); text-align: center; margin-bottom: 2rem; }
    .review-carousel__track-wrapper { overflow: hidden; }
    .review-carousel__track-desktop { display: none; transition: transform 0.5s ease-in-out; }
    .review-carousel__page-desktop  { display: flex; gap: 1.25rem; min-width: 100%; }
    .review-carousel__track-mobile  { display: flex; transition: transform 0.5s ease-in-out; }
    .review-carousel__slide-mobile  { min-width: 100%; padding: 0 0.25rem; }
    .review-card {
      flex: 1; background: var(--white-8); border: 1px solid var(--white-12);
      border-radius: var(--radius-2xl); padding: 1.5rem 1.25rem; display: flex; flex-direction: column;
    }
    .review-card__stars  { display: flex; gap: 0.125rem; margin-bottom: 0.75rem; }
    .review-card__star   { color: var(--yellow-400); }
    .review-card__text   { font-size: 0.875rem; color: var(--white-80); line-height: 1.625; flex: 1; margin-bottom: 0.75rem; }
    .review-card__footer { border-top: 1px solid var(--white-10); padding-top: 0.75rem; }
    .review-card__name   { font-size: 0.875rem; font-weight: 600; color: var(--white); }
    .review-carousel__nav { display: flex; justify-content: center; align-items: center; gap: 1.25rem; margin-top: 1.75rem; }
    .review-carousel__arrow {
      width: 2.5rem; height: 2.5rem; border-radius: var(--radius-full);
      border: 1px solid var(--white-25); background: var(--white-8); color: var(--white);
      display: flex; align-items: center; justify-content: center;
      transition: background 0.15s ease, border-color 0.15s ease;
    }
    .review-carousel__arrow:hover { background: var(--white-15); border-color: var(--white-40); }
    .review-carousel__dots { display: flex; gap: 0.5rem; align-items: center; }
    .review-carousel__dot  {
      height: 0.625rem; border-radius: var(--radius-full);
      background: var(--white-25); border: none; cursor: pointer;
      transition: width 0.3s ease, background 0.3s ease; width: 0.625rem;
    }
    .review-carousel__dot.is-active { width: 1.75rem; background: var(--raspberry-500); }

    /* ===================================================================
       PERSONAL JOURNEY MODULE  [id=solutions]
       =================================================================== */
    .journey-stages { background: var(--eggshell-500); padding-top: 2.5rem; padding-bottom: 6rem; }
    .journey-stages__inner { max-width: 80rem; margin: 0 auto; padding: 0 1rem; }
    .journey-stages__heading { font-size: clamp(1.25rem, 4vw, 2.25rem); text-align: center; margin-bottom: 3rem; color: var(--horizon-500); }
    .journey-stages__grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2.5rem; }
    .stage-card {
      background: linear-gradient(135deg, var(--horizon-600), var(--horizon-700));
      border-radius: var(--radius-card); border: 1px solid var(--white-10);
      box-shadow: var(--shadow-sm); padding: 1.25rem;
      display: flex; flex-direction: column; align-items: flex-start; justify-content: center;
      text-decoration: none; transition: border-color 0.2s ease, transform 0.2s ease;
    }
    .stage-card:hover { border-color: var(--white-30); transform: translateY(-2px); }
    .stage-card__title  { font-size: 1.5rem; font-weight: 700; color: var(--white); margin-bottom: 0.25rem; line-height: 1.25; }
    .stage-card__accent { color: var(--raspberry-400); }
    .stage-card__sub    { font-size: 0.75rem; color: var(--white-70); line-height: 1.35; }
    .journey-stages__cta { text-align: center; }
    .btn-demo {
      display: inline-block; padding: 0.5rem 2rem;
      background: var(--spring-500); color: var(--white);
      border-radius: var(--radius-button); font-weight: 500; border: none;
      transition: background 0.15s ease, box-shadow 0.15s ease; box-shadow: var(--shadow-sm);
    }
    .btn-demo:hover { background: var(--spring-600); box-shadow: var(--shadow-md); }

    /* ===================================================================
       LATEST INSIGHTS MODULE  [id=insights]
       =================================================================== */
    .latest-insights { background: var(--light-pink-100); padding-top: 3rem; padding-bottom: 7rem; }
    .latest-insights__inner { max-width: 80rem; margin: 0 auto; padding: 0 1rem; }
    .latest-insights__heading { font-size: clamp(1.25rem, 4vw, 2.25rem); font-weight: 700; color: var(--horizon-500); text-align: center; margin-bottom: 1.5rem; letter-spacing: -0.02em; }

    /* Dynamic layout */
    .insights-dynamic  { display: grid; grid-template-columns: 1fr; gap: 1.25rem; max-width: 72rem; margin: 0 auto; }
    .insights-featured {
      position: relative; display: block; border-radius: var(--radius-3xl);
      overflow: hidden; background: var(--horizon-500); min-height: 320px; text-decoration: none;
    }
    .insights-featured__img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.8; transition: opacity 0.7s ease, transform 0.7s ease; }
    .insights-featured:hover .insights-featured__img { opacity: 0.9; transform: scale(1.05); }
    .insights-featured__overlay {
      position: absolute; inset: 0;
      background: linear-gradient(to top, var(--horizon-700-80), var(--horizon-500-30) 50%, transparent);
    }
    .insights-featured__body { position: relative; display: flex; flex-direction: column; justify-content: flex-end; padding: 1.5rem; height: 100%; min-height: 320px; }
    .insights-featured__badge {
      display: inline-block; font-size: 0.65rem; font-weight: 700;
      padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);
      text-transform: uppercase; letter-spacing: 0.05em;
      background: var(--raspberry-500); color: var(--white);
      margin-bottom: 0.75rem; align-self: flex-start;
    }
    .insights-featured__title {
      font-size: clamp(1.25rem, 3vw, 1.875rem); font-weight: 700; color: var(--white);
      margin-bottom: 0.75rem; line-height: 1.25; letter-spacing: -0.02em;
      transition: color 0.15s ease;
    }
    .insights-featured:hover .insights-featured__title { color: var(--raspberry-300); }
    .insights-featured__summary { font-size: 0.875rem; color: var(--white-80); line-height: 1.625; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }
    .insights-supporting { display: grid; grid-template-rows: repeat(2, 1fr); gap: 1.25rem; }
    .insights-support-card {
      position: relative; display: flex; border-radius: var(--radius-3xl);
      overflow: hidden; background: var(--white); min-height: 150px;
      text-decoration: none; transition: box-shadow 0.2s ease;
    }
    .insights-support-card:hover { box-shadow: var(--shadow-lg); }
    .insights-support-card__thumb { width: 40%; position: relative; overflow: hidden; background: var(--horizon-100); }
    .insights-support-card__thumb img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
    .insights-support-card:hover .insights-support-card__thumb img { transform: scale(1.1); }
    .insights-support-card__body  { flex: 1; padding: 1rem; display: flex; align-items: center; }
    .insights-support-card__title { font-size: 0.875rem; font-weight: 700; color: var(--horizon-500); line-height: 1.25; transition: color 0.15s ease; }
    .insights-support-card:hover .insights-support-card__title { color: var(--raspberry-500); }

    /* Static fallback */
    .insights-static { display: grid; grid-template-columns: 1fr; gap: 1rem; max-width: 60rem; margin: 0 auto; }
    .insight-static-card { display: flex; flex-direction: column; background: var(--white); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-sm); text-decoration: none; transition: box-shadow 0.2s ease, transform 0.2s ease; }
    .insight-static-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); }
    .insight-static-card__thumb { aspect-ratio: 16/9; overflow: hidden; background: var(--horizon-100); }
    .insight-static-card__thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
    .insight-static-card:hover .insight-static-card__thumb img { transform: scale(1.05); }
    .insight-static-card__body { padding: 0.875rem; flex: 1; display: flex; flex-direction: column; }
    .insight-static-card__date  { font-size: 0.65rem; color: var(--neutral-400); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
    .insight-static-card__title { font-size: 0.875rem; font-weight: 700; color: var(--horizon-500); line-height: 1.35; margin-bottom: 0.375rem; transition: color 0.15s ease; }
    .insight-static-card:hover .insight-static-card__title { color: var(--raspberry-500); }
    .insight-static-card__summary { font-size: 0.75rem; color: var(--neutral-500); line-height: 1.625; flex: 1; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }

    .insights-footer { text-align: center; margin-top: 1.5rem; }
    .insights-footer__link { font-size: 0.875rem; font-weight: 600; color: var(--horizon-500); transition: color 0.15s ease; }
    .insights-footer__link:hover { color: var(--raspberry-500); }

    /* ===================================================================
       STATS BAR MODULE  [id=stats]
       =================================================================== */
    .stats-bar { position: relative; z-index: 1; margin-top: -3.5rem; margin-bottom: -6rem; }
    .stats-bar__inner { max-width: 56rem; margin: 0 auto; padding: 0 1rem; }
    .stats-bar__card {
      background: var(--white); border-radius: var(--radius-2xl); box-shadow: var(--shadow-lg);
      display: flex; flex-direction: row; align-items: flex-start;
      justify-content: space-around; gap: 0.75rem; padding: 1.5rem 1rem;
    }
    .stats-bar__stat    { text-align: center; flex: 1; min-width: 0; }
    .stats-bar__number  { font-size: clamp(1.5rem, 4vw, 2.25rem); font-weight: 700; color: var(--horizon-500); }
    .stats-bar__label   { font-size: clamp(0.65rem, 1.5vw, 0.875rem); font-weight: 600; color: var(--neutral-500); margin-top: 0.25rem; line-height: 1.25; }
    .stats-bar__divider { width: 1px; align-self: stretch; background: var(--light-gray); }

    /* ===================================================================
       RESPONSIVE
       =================================================================== */
    @media (min-width: 640px) {
      .hero__mobile-panels  { display: none; }
      .journey-stages__grid { grid-template-columns: repeat(2, 1fr); }
      .insights-static      { grid-template-columns: repeat(3, 1fr); }
    }
    @media (min-width: 768px) {
      .feature-grid__cards                { grid-template-columns: repeat(2, 1fr); }
      .review-carousel__track-desktop     { display: flex; }
      .review-carousel__track-mobile      { display: none; }
    }
    @media (min-width: 1024px) {
      .nav-desktop             { display: flex; }
      .nav-actions             { display: flex; }
      .nav-hamburger           { display: none; }
      .meet-fyn__inner         { flex-direction: row; align-items: center; }
      .meet-fyn__mobile-header { display: none; }
      .meet-fyn__heading       { display: block; }
      .meet-fyn__character     { display: flex; }
      .feature-grid__cards     { grid-template-columns: repeat(3, 1fr); }
      .hero__mobile-panels     { display: none; }
      .hero__desktop-composite { display: block; }
      .journey-stages__grid    { grid-template-columns: repeat(5, 1fr); }
      .stage-card              { aspect-ratio: 1; }
      .insights-dynamic        { grid-template-columns: 2fr 1fr; }
      .insights-featured       { min-height: 420px; }
      .insights-featured__body { min-height: 420px; padding: 2rem; }
      .site-footer__grid       { grid-template-columns: 2fr repeat(5, 1fr); }
    }
    @media (min-width: 1280px) {
      .hero__caption--left  { padding-top: 6rem; }
      .hero__caption--right { padding-top: 6rem; }
      .hero__caption--center { top: 3.5rem; }
      .hero__caption-title  { font-size: 1.5rem; }
    }
  </style>
</head>
<body>

  <a href="#main-content" class="skip-nav">Skip to main content</a>

  <?php include __DIR__ . '/partials/nav.php'; ?>

  <main id="main-content">

    <!-- ================================================================
         HERO MODULE  [id=hero]
         ================================================================ -->
    <section id="hero" class="hero" aria-labelledby="hero-heading">
      <div class="hero__inner">
        <h1 id="hero-heading" class="hero__heading">
          Change your<br />
          <span class="hero__heading-accent">financial future</span>
        </h1>
        <p class="hero__body">
          We help create your path to financial freedom<br />
          with clear recommendations from our proprietary Fynla Brain&reg;
        </p>
        <div class="hero__cta">
          <a href="/register" class="btn-cta-primary">Get started</a>
          <p class="hero__sublinks">
            <a href="#meet-fyn" class="hero__sublink" id="scroll-meet-fyn">Meet Fyn</a>
            <span class="hero__sublink-sep" aria-hidden="true">|</span>
            <a href="#dashboard" class="hero__sublink" id="scroll-dashboard">View the video</a>
            <span class="hero__sublink-sep" aria-hidden="true">|</span>
            <a href="/register?from=demo" class="hero__sublink">See our demo</a>
          </p>
        </div>

        <!-- Mobile caption panels (hidden above 640px by CSS) -->
        <div class="hero__mobile-panels" aria-hidden="true">
          <div class="hero__panel hero__panel--brain">
            <img src="/images/Website/Fyn-Brain-Animation-Whitev2M.gif" alt="" width="112" height="112" loading="eager" />
          </div>
          <div class="hero__panel">
            <p class="hero__panel-title">One financial view.</p>
            <p class="hero__panel-body">Use Fynla to securely centralise and view all your financial data.</p>
          </div>
          <div class="hero__panel">
            <p class="hero__panel-title">One financial brain.</p>
            <p class="hero__panel-body">Our proprietary brain does the calculations so you don't have to.</p>
          </div>
          <div class="hero__panel">
            <p class="hero__panel-title">One financial voice.</p>
            <p class="hero__panel-body">We will give you clear, simple and tailored advice to help your financial freedom.</p>
          </div>
        </div>

        <!-- Desktop composite (hidden below 1024px by CSS) -->
        <div class="hero__desktop-composite" aria-hidden="true">
          <img
            src="/images/Website/Homepage-Header-Desktopv3.png"
            alt="Fynla Brain — your financial planning intelligence"
            class="hero__desktop-img"
            width="1315" height="489"
            loading="eager" fetchpriority="high"
          />
          <div class="hero__caption hero__caption--left">
            <p class="hero__caption-title">One financial view.</p>
            <p class="hero__caption-body">Use Fynla to securely centralise and view all your financial data.</p>
          </div>
          <div class="hero__caption hero__caption--center">
            <div>
              <p class="hero__caption-title">One financial brain.</p>
              <p class="hero__caption-body">Our proprietary brain does the calculations so you don't have to.</p>
            </div>
            <div class="hero__caption-brain">
              <img src="/images/Website/Fyn-Brain-Animation-Whitev2M.gif" alt="" width="200" height="200" loading="eager" />
            </div>
          </div>
          <div class="hero__caption hero__caption--right">
            <p class="hero__caption-title">One financial voice.</p>
            <p class="hero__caption-body">We will give you clear, simple and tailored advice to help your financial freedom.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ================================================================
         MEET FYN MODULE  [id=meet-fyn]
         ================================================================ -->
    <section id="meet-fyn" class="meet-fyn" aria-labelledby="meet-fyn-heading">
      <div class="meet-fyn__inner">
        <div class="meet-fyn__content">
          <!-- Mobile: heading + character side by side -->
          <div class="meet-fyn__mobile-header">
            <h2 id="meet-fyn-heading" class="meet-fyn__heading">Meet Fyn</h2>
            <img src="/images/Fyn/Design Character 001a.webp" alt="Fyn — your AI financial companion" class="meet-fyn__mobile-char" width="324" height="427" loading="lazy" />
          </div>
          <p class="meet-fyn__subheading">Your financial companion for life</p>
          <p class="meet-fyn__body">
            Need help? Fyn is the face of our Fynla brain and will help you with your finance goals by giving you financial clarity. Fyn will help with everything from planning to saving and investments, through to your net worth and real estate. Tell Fyn what you'd like to do and he'll walk you through your dashboard step-by-step.
          </p>
          <div class="fyn-accordion">
            <button type="button" class="fyn-accordion__trigger" aria-expanded="false" aria-controls="fyn-accordion-panel" id="fyn-accordion-btn">
              <span>What can Fyn help you with?</span>
              <svg class="fyn-accordion__chevron" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            <div class="fyn-accordion__panel" id="fyn-accordion-panel" role="region" aria-labelledby="fyn-accordion-btn">
              <div class="fyn-accordion__item">
                <svg class="fyn-accordion__check" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                Your financial companion to answer any questions you have
              </div>
              <div class="fyn-accordion__item">
                <svg class="fyn-accordion__check" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                Help understand and complete your dashboard
              </div>
              <div class="fyn-accordion__item">
                <svg class="fyn-accordion__check" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" width="16" height="16"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                Support you to achieve your financial goals
              </div>
            </div>
          </div>
          <a href="/register?from=fyn" class="btn-fyn-start">Quick start with Fyn</a>
        </div>
        <!-- Desktop character -->
        <div class="meet-fyn__character" aria-hidden="true">
          <img src="/images/Fyn/Design Character 001a.webp" alt="" width="324" height="427" loading="lazy" />
        </div>
      </div>
    </section>

    <!-- ================================================================
         FEATURE GRID MODULE  [id=features]
         ================================================================ -->
    <section id="features" class="feature-grid" aria-labelledby="features-heading">
      <div class="feature-grid__inner">
        <h2 id="features-heading" class="feature-grid__heading">How Fyn can help you</h2>
        <p class="feature-grid__intro">We leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.</p>
        <div class="feature-grid__cards">
          <article class="feature-card" aria-label="Protection">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--raspberry" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </div>
            <h3 class="feature-card__title">Protection</h3>
            <p class="feature-card__body">Analyse life insurance, critical illness, and income protection coverage gaps to ensure your family is fully protected.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Life Cover</span><span class="feature-card__tag">Income Protection</span></div>
          </article>
          <article class="feature-card" aria-label="Savings">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--spring" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="feature-card__title">Savings</h3>
            <p class="feature-card__body">Track emergency funds, ISA allowances, and savings goals across all your accounts with smart benchmarking.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Emergency Fund</span><span class="feature-card__tag">ISA</span></div>
          </article>
          <article class="feature-card" aria-label="Investment">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--violet" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
            </div>
            <h3 class="feature-card__title">Investment</h3>
            <p class="feature-card__body">Portfolio analysis, risk profiling, and Monte Carlo projections to optimise your investment strategy.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Portfolio</span><span class="feature-card__tag">Risk Profile</span></div>
          </article>
          <article class="feature-card" aria-label="Retirement">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--blue" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
            <h3 class="feature-card__title">Retirement</h3>
            <p class="feature-card__body">Defined Contribution, Defined Benefit, and State Pension tracking with retirement income projections.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Pension</span><span class="feature-card__tag">State Pension</span></div>
          </article>
          <article class="feature-card" aria-label="Estate planning">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--savannah" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
            </div>
            <h3 class="feature-card__title">Estate</h3>
            <p class="feature-card__body">Inheritance Tax calculations, gifting strategies, and estate value projections for effective planning.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Inheritance Tax</span><span class="feature-card__tag">Trusts</span></div>
          </article>
          <article class="feature-card" aria-label="Net worth">
            <div class="feature-card__icon-wrap feature-card__icon-wrap--slate" aria-hidden="true">
              <svg class="feature-card__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
            </div>
            <h3 class="feature-card__title">Net Worth</h3>
            <p class="feature-card__body">Complete balance sheet with properties, assets, and liabilities tracking for a clear financial picture.</p>
            <div class="feature-card__tags"><span class="feature-card__tag">Assets</span><span class="feature-card__tag">Liabilities</span></div>
          </article>
        </div>
        <div class="feature-grid__footer">
          <a href="/features" class="feature-grid__footer-link">View detailed list of features &rsaquo;</a>
          <span class="feature-grid__footer-sep" aria-hidden="true">|</span>
          <a href="/register?from=demo" class="feature-grid__footer-link">View demos &rsaquo;</a>
        </div>
      </div>
    </section>

    <!-- ================================================================
         DASHBOARD VIDEO MODULE  [id=dashboard]
         ================================================================ -->
    <section id="dashboard" class="dashboard-preview" aria-labelledby="dashboard-heading">
      <div class="dashboard-preview__inner">
        <h2 id="dashboard-heading" class="dashboard-preview__heading">Your Fynla dashboard</h2>
        <div class="dashboard-preview__video-wrap" id="video-wrap" role="button" tabindex="0" aria-label="Play Fynla dashboard product tour video" aria-pressed="false">
          <video id="product-video" src="/images/Homepage-Fynla-ProductVideov2.mp4" playsinline class="dashboard-preview__video">
            Your browser does not support the video tag.
          </video>
          <div class="dashboard-preview__overlay" id="video-overlay" aria-hidden="true">
            <div class="dashboard-preview__play-btn">
              <svg class="dashboard-preview__play-icon" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24" width="40" height="40"><path d="M8 5v14l11-7z" /></svg>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ================================================================
         REVIEW CAROUSEL MODULE  [id=reviews]
         ================================================================ -->
    <section id="reviews" class="review-carousel" aria-labelledby="reviews-heading">
      <div class="review-carousel__inner">
        <h2 id="reviews-heading" class="review-carousel__heading">What our customers say</h2>
        <div class="review-carousel__track-wrapper" aria-live="polite" aria-atomic="false">
          <div class="review-carousel__track-desktop" id="carousel-desktop-track"></div>
          <div class="review-carousel__track-mobile"  id="carousel-mobile-track"></div>
        </div>
        <div class="review-carousel__nav" role="group" aria-label="Carousel navigation">
          <button class="review-carousel__arrow" id="carousel-prev" aria-label="Previous reviews">
            <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
          </button>
          <div class="review-carousel__dots" id="carousel-dots" role="tablist" aria-label="Review pages"></div>
          <button class="review-carousel__arrow" id="carousel-next" aria-label="Next reviews">
            <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
          </button>
        </div>
      </div>
    </section>

    <!-- ================================================================
         PERSONAL JOURNEY MODULE  [id=solutions]
         ================================================================ -->
    <section id="solutions" class="journey-stages" aria-labelledby="journey-heading">
      <div class="journey-stages__inner">
        <h2 id="journey-heading" class="journey-stages__heading">Your personal journey</h2>
        <div class="journey-stages__grid">
          <a href="/stage/starting-out" class="stage-card">
            <p class="stage-card__title"><span class="stage-card__accent">Starting</span><br />out</p>
            <p class="stage-card__sub">Build strong financial habits for your future.</p>
          </a>
          <a href="/stage/building-foundations" class="stage-card">
            <p class="stage-card__title"><span class="stage-card__accent">Building</span><br />foundations</p>
            <p class="stage-card__sub">Save, invest, and grow your wealth with confidence.</p>
          </a>
          <a href="/stage/protecting-and-growing" class="stage-card">
            <p class="stage-card__title"><span class="stage-card__accent">Protecting</span><br />and growing</p>
            <p class="stage-card__sub">Ensure your family and assets are fully covered.</p>
          </a>
          <a href="/stage/planning-your-future" class="stage-card">
            <p class="stage-card__title"><span class="stage-card__accent">Planning</span><br />your future</p>
            <p class="stage-card__sub">Retirement projections, pension tracking, and estate planning.</p>
          </a>
          <a href="/stage/enjoying-your-wealth" class="stage-card">
            <p class="stage-card__title"><span class="stage-card__accent">Enjoying</span><br />your wealth</p>
            <p class="stage-card__sub">Make the most of your financial success.</p>
          </a>
        </div>
        <div class="journey-stages__cta">
          <a href="/register?from=demo" class="btn-demo">View demo</a>
        </div>
      </div>
    </section>

    <!-- ================================================================
         LATEST INSIGHTS MODULE  [id=insights]
         ================================================================ -->
    <section id="insights" class="latest-insights" aria-labelledby="insights-heading">
      <div class="latest-insights__inner">
        <h2 id="insights-heading" class="latest-insights__heading">Latest insights</h2>

        <!-- Dynamic: populated by JS fetch (hidden until API responds) -->
        <div id="insights-dynamic" class="insights-dynamic" hidden aria-live="polite">
          <a id="insight-featured" class="insights-featured" href="/insights" aria-label="Featured insight">
            <img id="insight-featured-img" src="" alt="" class="insights-featured__img" width="800" height="420" loading="lazy" />
            <div class="insights-featured__overlay" aria-hidden="true"></div>
            <div class="insights-featured__body">
              <span class="insights-featured__badge">Featured</span>
              <h3 id="insight-featured-title" class="insights-featured__title"></h3>
              <p  id="insight-featured-summary" class="insights-featured__summary"></p>
            </div>
          </a>
          <div class="insights-supporting" id="insights-supporting"></div>
        </div>

        <!-- Static fallback: always visible until API succeeds -->
        <div id="insights-static" class="insights-static">
          <a href="/insights/how-much-to-retire-uk" class="insight-static-card">
            <div class="insight-static-card__thumb">
              <img src="/images/insights/how-much-to-retire-uk.jpg" alt="How Much Do I Need to Retire in the UK?" width="400" height="225" loading="lazy" />
            </div>
            <div class="insight-static-card__body">
              <p class="insight-static-card__date">14 April 2026</p>
              <h3 class="insight-static-card__title">How Much Do I Need to Retire in the UK?</h3>
              <p class="insight-static-card__summary">Calculate your UK retirement number using 2026 PLSA living standards and bridge the State Pension gap.</p>
            </div>
          </a>
          <a href="/insights/stocks-shares-isa-uk" class="insight-static-card">
            <div class="insight-static-card__thumb">
              <img src="/images/insights/stocks-shares-isa.jpg" alt="What Is a Stocks and Shares ISA?" width="400" height="225" loading="lazy" />
            </div>
            <div class="insight-static-card__body">
              <p class="insight-static-card__date">13 April 2026</p>
              <h3 class="insight-static-card__title">What Is a Stocks and Shares ISA?</h3>
              <p class="insight-static-card__summary">How they work, what you can invest in, tax benefits, risks, fees, and how to choose a platform.</p>
            </div>
          </a>
          <a href="/insights/isa-guide-uk" class="insight-static-card">
            <div class="insight-static-card__thumb">
              <img src="/images/insights/isa-guide-uk.jpg" alt="The Ultimate Guide to ISAs in the UK" width="400" height="225" loading="lazy" />
            </div>
            <div class="insight-static-card__body">
              <p class="insight-static-card__date">8 April 2026</p>
              <h3 class="insight-static-card__title">The Ultimate Guide to ISAs in the UK</h3>
              <p class="insight-static-card__summary">Everything you need to know about ISAs in 2026 — types, allowances, rules, and choosing the right one.</p>
            </div>
          </a>
        </div>

        <p class="insights-footer">
          <a href="/insights" class="insights-footer__link">See all insights &rarr;</a>
        </p>
      </div>
    </section>

    <!-- ================================================================
         STATS BAR MODULE  [id=stats]
         ================================================================ -->
    <section id="stats" class="stats-bar" aria-label="Fynla key statistics">
      <div class="stats-bar__inner">
        <div class="stats-bar__card">
          <div class="stats-bar__stat">
            <div class="stats-bar__number">91%</div>
            <div class="stats-bar__label">UK adults don't get financial advice</div>
          </div>
          <div class="stats-bar__divider" aria-hidden="true"></div>
          <div class="stats-bar__stat">
            <div class="stats-bar__number">1000's</div>
            <div class="stats-bar__label">of financial plans created for people like you</div>
          </div>
          <div class="stats-bar__divider" aria-hidden="true"></div>
          <div class="stats-bar__stat">
            <div class="stats-bar__number">30+</div>
            <div class="stats-bar__label">Fynla features for financial planning</div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <?php include __DIR__ . '/partials/footer.php'; ?>

  <!-- Shared interactive wiring (nav active state, menus, etc.) -->
  <script src="/pages/js/site.js" defer></script>

  <!-- Page-specific JS -->
  <script defer>
    'use strict';

    /* ----------------------------------------------------------------
       REVIEW CAROUSEL
       ---------------------------------------------------------------- */
    (function () {
      var reviews = [
        { name: 'Stephen D.', text: '"I found the dashboard screens interesting, but the chat agent stole the show. Absolutely incredible. I spent an hour or so with it on Friday night and I left with the information I needed — very technical analysis of workplace pension options broken down in a very easy to understand way."' },
        { name: 'Mia R.',     text: '"It was so easy to see all my finances in one place. I had all current and savings accounts in different locations and had no idea what to do with my money. Fyn was really helpful in getting my information into Fynla, and then I had clear next steps on what I needed to do with my money."' },
        { name: 'Anne L.',    text: '"Fynla has been a useful way to get a clearer picture of my finances without it feeling overwhelming. It pulls everything into one place, making it much easier to see where things stand. The recommendations are sensible and practical, giving me clear next steps rather than trying to do everything at once."' },
        { name: 'Neil S.',    text: '"Fynla alerted me that my fixed rate mortgage was expiring and that I could lock in a new rate up to six months before my term ended. I secured a competitive deal, and shortly after, interest rates jumped. I will save £1,400 a year!"' },
        { name: 'Ron B.',     text: '"Finally, everything in one place. Our family’s assets, liabilities, insurance, and key financial information are all organised and easy to access. Fyn makes the whole experience feel effortless — intuitive, genuinely useful, and asks exactly the right questions. I can’t imagine going back to spreadsheets."' },
        { name: 'Michael H.', text: '"I was sceptical about another finance app, but Fynla is different. It actually understands UK tax rules — ISA allowances, pension annual allowance, inheritance tax. Everything is calculated correctly."' },
      ];

      var PER_PAGE = 3, desktopPages = [], desktopPage = 0, mobilePage = 0, isMobile = false, autoTimer = null;
      for (var i = 0; i < reviews.length; i += PER_PAGE) desktopPages.push(reviews.slice(i, i + PER_PAGE));

      function starHtml() {
        var s = '';
        for (var n = 0; n < 5; n++) s += '<svg class="review-card__star" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
        return s;
      }

      function cardHtml(r) {
        return '<div class="review-card"><div class="review-card__stars">' + starHtml() + '</div>' +
               '<p class="review-card__text">' + r.text + '</p>' +
               '<div class="review-card__footer"><span class="review-card__name">' + r.name + '</span></div></div>';
      }

      function totalPages() { return isMobile ? reviews.length : desktopPages.length; }
      function currentPage() { return isMobile ? mobilePage : desktopPage; }

      function applyTransform() {
        var dt = document.getElementById('carousel-desktop-track');
        var mt = document.getElementById('carousel-mobile-track');
        if (dt) dt.style.transform = 'translateX(-' + (desktopPage * 100) + '%)';
        if (mt) mt.style.transform  = 'translateX(-' + (mobilePage  * 100) + '%)';
        updateDots();
      }

      function updateDots() {
        var el = document.getElementById('carousel-dots'); if (!el) return;
        el.innerHTML = '';
        var total = totalPages(), cur = currentPage();
        for (var d = 0; d < total; d++) {
          var btn = document.createElement('button');
          btn.className = 'review-carousel__dot' + (d === cur ? ' is-active' : '');
          btn.setAttribute('role', 'tab');
          btn.setAttribute('aria-selected', d === cur ? 'true' : 'false');
          btn.setAttribute('aria-label', 'Page ' + (d + 1));
          btn.setAttribute('data-page', d);
          btn.addEventListener('click', function () { goToPage(parseInt(this.getAttribute('data-page'), 10)); });
          el.appendChild(btn);
        }
      }

      function goToPage(p) {
        var t = totalPages(), c = (p + t) % t;
        if (isMobile) mobilePage = c; else desktopPage = c;
        applyTransform(); clearInterval(autoTimer); autoTimer = setInterval(function () { goToPage(currentPage() + 1); }, 10000);
      }

      function checkMobile() {
        var was = isMobile; isMobile = window.innerWidth < 768;
        if (was !== isMobile) { desktopPage = 0; mobilePage = 0; applyTransform(); }
      }

      var dt = document.getElementById('carousel-desktop-track');
      var mt = document.getElementById('carousel-mobile-track');
      if (!dt || !mt) return;

      dt.innerHTML = desktopPages.map(function (p) { return '<div class="review-carousel__page-desktop">' + p.map(cardHtml).join('') + '</div>'; }).join('');
      mt.innerHTML = reviews.map(function (r) { return '<div class="review-carousel__slide-mobile">' + cardHtml(r) + '</div>'; }).join('');

      checkMobile(); applyTransform();
      autoTimer = setInterval(function () { goToPage(currentPage() + 1); }, 10000);
      document.getElementById('carousel-prev').addEventListener('click', function () { goToPage(currentPage() - 1); });
      document.getElementById('carousel-next').addEventListener('click', function () { goToPage(currentPage() + 1); });
      window.addEventListener('resize', checkMobile);
    })();

    /* ----------------------------------------------------------------
       VIDEO TOGGLE
       ---------------------------------------------------------------- */
    (function () {
      var wrap = document.getElementById('video-wrap');
      var video = document.getElementById('product-video');
      var overlay = document.getElementById('video-overlay');
      if (!wrap || !video) return;
      function toggle() {
        if (video.paused) {
          video.play(); overlay.hidden = true;
          wrap.setAttribute('aria-pressed', 'true');
          wrap.setAttribute('aria-label', 'Pause Fynla dashboard product tour video');
        } else {
          video.pause(); overlay.hidden = false;
          wrap.setAttribute('aria-pressed', 'false');
          wrap.setAttribute('aria-label', 'Play Fynla dashboard product tour video');
        }
      }
      wrap.addEventListener('click', toggle);
      wrap.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
      video.addEventListener('ended', function () { overlay.hidden = false; wrap.setAttribute('aria-pressed', 'false'); wrap.setAttribute('aria-label', 'Play Fynla dashboard product tour video'); });
    })();

    /* ----------------------------------------------------------------
       FYN ACCORDION
       ---------------------------------------------------------------- */
    (function () {
      var btn = document.getElementById('fyn-accordion-btn');
      var panel = document.getElementById('fyn-accordion-panel');
      if (!btn || !panel) return;
      var chevron = btn.querySelector('.fyn-accordion__chevron');
      btn.addEventListener('click', function () {
        var open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!open));
        panel.classList.toggle('is-open', !open);
        if (chevron) chevron.style.transform = open ? '' : 'rotate(180deg)';
      });
    })();

    /* ----------------------------------------------------------------
       SMOOTH SCROLL (hero sub-links)
       ---------------------------------------------------------------- */
    (function () {
      function smoothScrollTo(targetId) {
        var el = document.getElementById(targetId); if (!el) return;
        var navH = (document.querySelector('.site-header') || {}).offsetHeight || 0;
        var target = el.getBoundingClientRect().top + window.scrollY - navH;
        var start = window.scrollY, dist = target - start, dur = 1500, startTime = null;
        function ease(t) { return t < 0.5 ? 4*t*t*t : 1 - Math.pow(-2*t+2,3)/2; }
        function step(ts) {
          if (!startTime) startTime = ts;
          var p = Math.min((ts - startTime) / dur, 1);
          window.scrollTo(0, start + dist * ease(p));
          if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
      }
      var mf = document.getElementById('scroll-meet-fyn');
      var db = document.getElementById('scroll-dashboard');
      if (mf) mf.addEventListener('click', function (e) { e.preventDefault(); smoothScrollTo('meet-fyn'); });
      if (db) db.addEventListener('click', function (e) { e.preventDefault(); smoothScrollTo('dashboard'); });
    })();

    /* ----------------------------------------------------------------
       LATEST INSIGHTS — fetch from API, fall back to static grid
       ---------------------------------------------------------------- */
    (function () {
      var dynEl    = document.getElementById('insights-dynamic');
      var staticEl = document.getElementById('insights-static');

      fetch('/api/insights?featured=1')
        .then(function (res) { if (!res.ok) throw new Error('API unavailable'); return res.json(); })
        .then(function (data) {
          var featured = data.featured || null;
          var supporting = data.supporting || [];
          if (!featured) throw new Error('No featured insight');

          var featuredEl  = document.getElementById('insight-featured');
          var featuredImg = document.getElementById('insight-featured-img');
          var featuredTit = document.getElementById('insight-featured-title');
          var featuredSum = document.getElementById('insight-featured-summary');
          featuredEl.href = '/insights/' + featured.slug;
          featuredEl.setAttribute('aria-label', featured.title);
          if (featured.image_card) { featuredImg.src = featured.image_card; featuredImg.alt = featured.title; }
          featuredTit.textContent = featured.title;
          featuredSum.textContent = featured.summary;

          var supEl = document.getElementById('insights-supporting');
          supEl.innerHTML = supporting.slice(0, 2).map(function (a) {
            return '<a href="/insights/' + a.slug + '" class="insights-support-card">' +
              '<div class="insights-support-card__thumb">' +
              (a.image_card ? '<img src="' + a.image_card + '" alt="' + a.title.replace(/"/g, '&quot;') + '" width="300" height="200" loading="lazy" />' : '') +
              '</div><div class="insights-support-card__body"><h4 class="insights-support-card__title">' + a.title + '</h4></div></a>';
          }).join('');

          if (dynEl)    dynEl.hidden    = false;
          if (staticEl) staticEl.hidden = true;
        })
        .catch(function () {
          if (dynEl)    dynEl.hidden    = true;
          if (staticEl) staticEl.hidden = false;
        });
    })();
  </script>

</body>
</html>
