<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard mockup — mobile web + desktop | Fynla</title>
  <meta name="robots" content="noindex, nofollow" />

  <link rel="stylesheet" href="/pages/css/global.css?v=113" />
  <link rel="stylesheet" href="/pages/css/dashboard-mockup.css?v=1" />

  <style>
    /* Token the mobile CSS uses once but global.css lacks */
    :root { --raspberry-50: #FEF2F6; }

    /* ---- Mockup chrome (not part of the design) ---- */
    body { background: #E7E9EF; margin: 0; font-family: var(--font-primary); }
    .mock-bar {
      position: sticky; top: 0; z-index: 100;
      display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
      padding: 0.75rem 1.25rem; background: var(--horizon-600); color: #fff;
    }
    .mock-bar h1 { font-size: 0.95rem; font-weight: 700; margin: 0; }
    .mock-bar p { font-size: 0.75rem; margin: 0; color: rgba(255,255,255,0.7); }
    .mock-toggle { margin-left: auto; display: inline-flex; background: rgba(255,255,255,0.12); border-radius: 999px; padding: 0.25rem; }
    .mock-toggle button {
      border: 0; background: none; color: rgba(255,255,255,0.8); font-family: inherit;
      font-size: 0.8rem; font-weight: 600; padding: 0.4rem 0.9rem; border-radius: 999px; cursor: pointer;
    }
    .mock-toggle button.is-active { background: #fff; color: var(--horizon-600); }
    .mock-stage { padding: 2rem 1rem 4rem; }
    .mock-label { text-align: center; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--horizon-500); margin: 0 0 1rem; }

    /* ---- Phone frame for the mobile mockup (so it reads as a phone on desktop) ---- */
    .mock-phone-wrap { display: flex; justify-content: center; }
    .mock-phone {
      width: 402px; height: 850px; max-width: 100%;
      border-radius: 2.25rem; overflow: hidden; position: relative;
      box-shadow: 0 24px 60px rgba(15,23,42,0.32); border: 10px solid #11161f; background: var(--eggshell-500);
    }
    /* The mobile dashboard.css .phone-frame uses 100svh; inside the mock-phone we
       pin it to the device box instead. */
    .mock-phone .phone-frame { height: 850px; }

    /* ===============================================================
       DESKTOP — gamified content INSIDE the existing app chrome.
       The left sidebar, top bar and "Chat with Fyn" are kept exactly as
       the current app; only the main dashboard content is redesigned.
       =============================================================== */
    .app { display: flex; max-width: 1320px; margin: 0 auto; background: var(--eggshell-500); border-radius: 1rem; overflow: hidden; box-shadow: 0 24px 60px rgba(15,23,42,0.18); min-height: 820px; }

    /* Sidebar (kept as the current app) */
    .app-sidebar { width: 234px; flex-shrink: 0; background: #fff; border-right: 1px solid var(--light-gray); display: flex; flex-direction: column; padding: 1.25rem 0 1rem; }
    .app-sidebar__logo { font-size: 1.4rem; font-weight: 900; color: var(--horizon-500); padding: 0 1.25rem 1.25rem; }
    .app-nav { flex: 1; padding: 0 0.75rem; display: flex; flex-direction: column; gap: 0.125rem; }
    .app-nav__item { display: flex; align-items: center; gap: 0.625rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-lg); font-size: 0.875rem; font-weight: 500; color: var(--neutral-600); }
    .app-nav__item.is-active { background: var(--light-blue-100); color: var(--horizon-500); font-weight: 600; }
    .app-nav__icon { width: 1.25rem; height: 1.25rem; flex-shrink: 0; color: currentColor; display: flex; }
    .app-nav__icon svg { display: block; }
    .app-nav__group { display: flex; align-items: center; justify-content: space-between; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--neutral-400); padding: 0.875rem 0.75rem 0.25rem; }
    .app-sidebar__footer { padding: 0.75rem 0.75rem 0; margin-top: 0.5rem; border-top: 1px solid var(--light-gray); display: flex; flex-direction: column; gap: 0.125rem; }

    /* Top bar (kept) */
    .app-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .app-topbar { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem; background: #fff; border-bottom: 1px solid var(--light-gray); }
    .app-topbar h1 { font-size: 1.4rem; font-weight: 900; color: var(--horizon-500); margin: 0; }
    .app-topbar__right { display: flex; align-items: center; gap: 0.625rem; }
    .app-pill { font-size: 0.8rem; font-weight: 600; color: var(--horizon-500); padding: 0.5rem 0.875rem; border-radius: var(--radius-lg); background: var(--eggshell-500); }
    .app-pill--chat { border: 1px solid var(--raspberry-300); color: var(--raspberry-600); background: #fff; }

    .app-content { flex: 1; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; overflow: hidden; }

    /* Gamified header: gradient bg, white progress bar at top, LEVEL UP under */
    .gd-header { background: linear-gradient(135deg, var(--horizon-500) 0%, var(--raspberry-500) 100%); color: #fff; border-radius: var(--radius-2xl); padding: 1.5rem 1.75rem; box-shadow: 0 10px 28px rgba(232,62,109,0.20); }
    .gd-header__hello { font-size: 1.5rem; font-weight: 900; margin: 0 0 1rem; }
    .gd-header__top { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-bottom: 0.5rem; }
    .gd-header__level { font-size: 1rem; font-weight: 800; }
    .gd-header__meta { font-size: 0.875rem; color: rgba(255,255,255,0.85); }
    .gd-bar { height: 10px; background: rgba(255,255,255,0.25); border-radius: 999px; overflow: hidden; }
    .gd-bar__fill { height: 100%; background: #fff; border-radius: 999px; }
    .gd-header__levelup { display: flex; align-items: center; gap: 1rem; margin-top: 1.125rem; padding-top: 1.125rem; border-top: 1px solid rgba(255,255,255,0.2); }
    .gd-header__lu-badge { font-size: 1.4rem; font-weight: 900; line-height: 1; flex-shrink: 0; letter-spacing: 0.02em; }
    .gd-header__lu-lead { font-size: 1rem; font-weight: 700; margin: 0; }
    .gd-header__lu-lead strong { font-weight: 900; }
    .gd-header__lu-sub { font-size: 0.8125rem; opacity: 0.9; margin: 0.125rem 0 0; }

    /* Focus tabs + recommendations — ONE connected card */
    .gd-card { background: #fff; border: 1px solid var(--horizon-100); border-radius: var(--radius-2xl); padding: 0.5rem 1.5rem 1.5rem; }
    .gd-tabs { display: flex; gap: 0.5rem; border-bottom: 1px solid var(--horizon-100); }
    .gd-tab { flex: 1; display: flex; flex-direction: column; gap: 0.2rem; align-items: flex-start; padding: 1rem 1rem 0.875rem; border: none; background: none; border-bottom: 3px solid transparent; cursor: pointer; font-family: var(--font-primary); text-align: left; }
    .gd-tab__stat { font-size: 1.35rem; font-weight: 900; color: var(--horizon-400); line-height: 1; }
    .gd-tab__area { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--neutral-400); }
    .gd-tab.is-active { border-bottom-color: var(--raspberry-500); }
    .gd-tab.is-active .gd-tab__stat { color: var(--raspberry-600); }
    .gd-tab.is-active .gd-tab__area { color: var(--horizon-500); }
    .gd-recs { margin-top: 1.25rem; }
    .gd-recs__head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 0.625rem; }
    .gd-recs__head h3 { font-size: 1rem; font-weight: 700; color: var(--horizon-600); margin: 0; }
    .gd-recs__head .count { font-size: 0.8125rem; font-weight: 600; color: var(--raspberry-500); }
    .gd-recs__info { font-size: 0.85rem; color: var(--neutral-500); margin: 0 0 0.75rem; line-height: 1.4; }
    .gd-recs__panel { display: none; }
    .gd-recs__panel.is-active { display: block; }

    /* Your finances — max 3 per line */
    .gd-section-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 0.25rem; }
    .gd-section-head h2 { font-size: 1.125rem; font-weight: 800; color: var(--horizon-600); margin: 0; }
    .gd-finances__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.875rem; }
    .gd-finances__grid .md-panel--wide { grid-column: auto; }

    @media (max-width: 900px) {
      .app-sidebar { display: none; }
      .gd-finances__grid { grid-template-columns: 1fr 1fr; }
    }

    /* View switching */
    [data-view].is-hidden { display: none; }
  </style>
</head>
<body>

<?php
  // ---- Hard-coded mock data mirroring the screenshot ----
  $ICON = [
    'saveTax'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'retirement'=> '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'savings'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
    'netWorth'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
    'shield'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
    'card'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
    'clock'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'investment'=> '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>',
  ];
  $check = '<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>';
  $chevR = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>';
  $burger = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>';

  $focus = [
    ['key'=>'save_tax','label'=>'Save tax','icon'=>$ICON['saveTax'],'stat'=>'£130,000','info'=>'Use your full ISA and pension allowances to keep more of what you earn.'],
    ['key'=>'retirement','label'=>'Retirement','icon'=>$ICON['retirement'],'stat'=>'£1,200','info'=>'Close your projected income gap — small increases now compound.'],
    ['key'=>'savings','label'=>'Savings','icon'=>$ICON['savings'],'stat'=>'£3,500','info'=>'Build your emergency fund and earn more on your cash.'],
  ];
  $recs = [
    ['title'=>'Establish discretionary trust','meta'=>'You can save: £130,000'],
    ['title'=>'Start using annual gift allowance','meta'=>'You can save: £1,200'],
  ];
  // Per-focus recommendations (desktop tabs — shows focus → recs are connected)
  $focusRecs = [
    'save_tax'   => [
      ['title'=>'Establish discretionary trust','meta'=>'You can save: £130,000'],
      ['title'=>'Start using annual gift allowance','meta'=>'You can save: £1,200'],
    ],
    'retirement' => [
      ['title'=>'Increase pension contributions','meta'=>'You can save: £1,200 a year'],
      ['title'=>'Consolidate your old pensions','meta'=>'Lower fees, one clear view'],
    ],
    'savings'    => [
      ['title'=>'Build your emergency fund to 6 months','meta'=>'1.8 months to go'],
      ['title'=>'Move cash to a higher-rate account','meta'=>'You can earn: £3,500'],
    ],
  ];
  // Sidebar icons (functional — side nav is the Rule #16 carve-out)
  $navIcon = [
    'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
    'netWorth'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
    'chevron'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>',
  ];
  // Existing dashboard module cards (new panel design)
  $panels = [
    ['label'=>'Net worth','tone'=>'horizon','icon'=>$ICON['netWorth'],'value'=>'£487,500','viz'=>'donut','progress'=>72,'vizNum'=>'+4%','vizCap'=>'Trend','caption'=>'£612,000 assets'],
    ['label'=>'Protection','tone'=>'raspberry','icon'=>$ICON['shield'],'value'=>'£250,000','viz'=>'donut','progress'=>85,'vizNum'=>'Active','vizCap'=>'Cover','caption'=>'Cover in place'],
    ['label'=>'Savings','tone'=>'spring','icon'=>$ICON['card'],'value'=>'£21,400','viz'=>'bar','barFill'=>70,'barValue'=>'4.2','barUnit'=>'/ 6 months','caption'=>'Building your fund'],
    ['label'=>'Retirement','tone'=>'violet','icon'=>$ICON['clock'],'value'=>'£1,200','viz'=>'bar','barFill'=>62,'barValue'=>'62%','barUnit'=>'of target','caption'=>'Towards your target'],
    ['label'=>'Investment','tone'=>'horizon','icon'=>$ICON['investment'],'value'=>'£96,300','viz'=>'donut','progress'=>72,'vizNum'=>'3','vizCap'=>'Accounts','caption'=>'8 holdings','wide'=>true],
  ];

  // Renders the shared "level card" markup
  function levelCard() { ?>
    <section class="md-level" aria-labelledby="lv-h">
      <div class="md-level__pie" role="img" aria-label="Level 1, 0 percent complete">
        <svg class="md-level__pie-svg" viewBox="0 0 100 100" aria-hidden="true">
          <circle class="md-level__pie-track" cx="50" cy="50" r="44" />
          <circle class="md-level__pie-arc" cx="50" cy="50" r="44" style="--progress:0" />
        </svg>
        <div class="md-level__pie-inner">
          <p class="md-level__pie-label">Level</p>
          <p class="md-level__pie-num">1</p>
        </div>
      </div>
      <div class="md-level__copy">
        <h2 class="md-level__heading" id="lv-h">0 of 3 actions complete</h2>
        <p class="md-level__sub">Complete actions to reach <strong>Level 2</strong>.</p>
      </div>
    </section>
  <?php }

  function recRow($r, $check, $chevR) { ?>
    <li class="md-rec">
      <button type="button" class="md-rec__check-btn" aria-label="Mark complete">
        <span class="md-rec__check" aria-hidden="true"><?= $check ?></span>
      </button>
      <a href="#" class="md-rec__action" onclick="return false">
        <span class="md-rec__text">
          <span class="md-rec__title"><?= htmlspecialchars($r['title']) ?></span>
          <span class="md-rec__meta"><?= htmlspecialchars($r['meta']) ?></span>
        </span>
        <?= $chevR ?>
      </a>
      <button type="button" class="md-rec__skip" aria-label="Skip">Skip</button>
    </li>
  <?php }

  function fynDock() { ?>
    <button type="button" class="md-fyn-dock md-fyn-dock--bar" aria-label="Chat with Fyn">
      <span class="md-fyn-dock__avatar" aria-hidden="true">F</span>
      <span class="md-fyn-dock__text">
        <span class="md-fyn-dock__name">Fyn</span>
        <span class="md-fyn-dock__status">Your financial companion</span>
      </span>
      <span class="md-fyn-dock__action" aria-hidden="true">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" /></svg>
      </span>
    </button>
  <?php }

  function panelTile($p) { ?>
    <a href="#" class="md-panel md-panel--<?= $p['tone'] ?><?= !empty($p['wide']) ? ' md-panel--wide' : '' ?>" onclick="return false">
      <?php if ($p['viz'] === 'bar'): ?>
        <div class="md-panel__viz md-panel__viz--bar" aria-hidden="true">
          <div class="md-panel__bar"><div class="md-panel__bar-fill" style="width:<?= $p['barFill'] ?>%"></div></div>
          <p class="md-panel__bar-label"><strong><?= $p['barValue'] ?></strong> <?= $p['barUnit'] ?></p>
        </div>
      <?php else: ?>
        <div class="md-panel__viz" style="--progress:<?= $p['progress'] ?>" aria-hidden="true">
          <div class="md-panel__viz-inner">
            <span class="md-panel__viz-num"><?= $p['vizNum'] ?></span>
            <span class="md-panel__viz-cap"><?= $p['vizCap'] ?></span>
          </div>
        </div>
      <?php endif; ?>
      <div class="md-panel__body">
        <p class="md-panel__label"><span class="md-panel__title-icon" aria-hidden="true"><?= $p['icon'] ?></span><?= $p['label'] ?></p>
        <p class="md-panel__value"><?= $p['value'] ?></p>
        <p class="md-panel__caption"><?= $p['caption'] ?></p>
      </div>
    </a>
  <?php }
?>

  <div class="mock-bar">
    <div>
      <h1>Dashboard mockup — gamified design</h1>
      <p>Mobile web (identical to the mobile app) + desktop (expanded). Static mock — for review only.</p>
    </div>
    <div class="mock-toggle" role="group" aria-label="Choose view">
      <button type="button" data-show="both" class="is-active">Both</button>
      <button type="button" data-show="mobile">Mobile</button>
      <button type="button" data-show="desktop">Desktop</button>
    </div>
  </div>

  <div class="mock-stage">

    <!-- ============================ MOBILE ============================ -->
    <section data-view="mobile" style="margin-bottom:3rem;">
      <p class="mock-label">Mobile web dashboard</p>
      <div class="mock-phone-wrap">
        <div class="mock-phone">
          <div class="phone-frame">
            <header class="md-header" role="banner">
              <button type="button" class="md-hamburger" aria-label="Open menu"><?= $burger ?></button>
              <div class="md-header__greeting"><p class="md-header__hello">Good morning, Azlan</p></div>
              <span class="md-header__spacer" aria-hidden="true"></span>
            </header>

            <main class="md-main">
              <div class="md-scroll-hero">
                <?php levelCard(); ?>
              </div>

              <div class="md-callout" role="note">
                <div class="md-callout__top">
                  <p class="md-callout__levelup">LEVEL<br>UP</p>
                  <div class="md-callout__top-copy">
                    <p class="md-callout__lead">You're ahead of <strong>57%</strong> of people</p>
                    <p class="md-callout__sub">Complete your actions to get further ahead, level up and change your financial future</p>
                  </div>
                </div>

                <div class="md-callout__carousel">
                  <div class="md-accordion" role="tablist" aria-label="Focus areas">
                    <?php foreach ($focus as $i => $c): ?>
                      <button type="button" class="md-accordion__card js-acc<?= $i===0?' is-active':'' ?>" data-i="<?= $i ?>" role="tab" aria-selected="<?= $i===0?'true':'false' ?>" aria-label="<?= htmlspecialchars($c['label']) ?>">
                        <span class="md-accordion__icon" aria-hidden="true"><?= $c['icon'] ?></span>
                        <span class="md-accordion__content">
                          <span class="md-callout__slide-stat"><?= $c['stat'] ?></span>
                          <span class="md-callout__slide-area"><?= htmlspecialchars($c['label']) ?></span>
                          <span class="md-callout__slide-info"><?= htmlspecialchars($c['info']) ?></span>
                        </span>
                      </button>
                    <?php endforeach; ?>
                  </div>

                  <div class="md-callout__dots" role="tablist" aria-label="Focus area navigation">
                    <?php foreach ($focus as $i => $c): ?>
                      <button type="button" class="md-callout__dot js-dot<?= $i===0?' is-active':'' ?>" data-i="<?= $i ?>" aria-label="<?= htmlspecialchars($c['label']) ?>"></button>
                    <?php endforeach; ?>
                  </div>

                  <section class="md-recs is-open" aria-labelledby="m-recs-h">
                    <div class="md-section-head md-section-head--toggle">
                      <h3 class="md-section-head__title" id="m-recs-h">Recommendations</h3>
                      <span class="md-section-head__right"><span class="md-section-head__count">0 / 2</span></span>
                    </div>
                    <div class="md-recs__body">
                      <ul class="md-recs__list">
                        <?php foreach ($recs as $r) recRow($r, $check, $chevR); ?>
                      </ul>
                      <a href="#" class="md-recs__view-all" onclick="return false">View all recommendations</a>
                    </div>
                  </section>
                </div>
              </div>

              <section class="md-panels" aria-labelledby="m-fin-h">
                <div class="md-section-head"><h3 class="md-section-head__title" id="m-fin-h">Your finances</h3></div>
                <div class="md-panels__list">
                  <?php foreach ($panels as $p) panelTile($p); ?>
                </div>
              </section>

              <div class="md-bottom-pad" aria-hidden="true"></div>
            </main>

            <?php fynDock(); ?>
          </div>
        </div>
      </div>
    </section>

    <!-- ============================ DESKTOP ============================ -->
    <section data-view="desktop">
      <p class="mock-label">Desktop dashboard — gamified content inside the existing app chrome</p>
      <div class="app">

        <!-- Left sidebar — KEPT as the current app -->
        <aside class="app-sidebar">
          <div class="app-sidebar__logo">Fynla</div>
          <nav class="app-nav" aria-label="Primary">
            <span class="app-nav__item is-active"><span class="app-nav__icon"><?= $navIcon['dashboard'] ?></span>Dashboard</span>
            <span class="app-nav__item"><span class="app-nav__icon"><?= $navIcon['netWorth'] ?></span>Net Worth</span>
            <span class="app-nav__group">Cash Management <?= $navIcon['chevron'] ?></span>
            <span class="app-nav__group">Finances <?= $navIcon['chevron'] ?></span>
            <span class="app-nav__group">Personal Affairs <?= $navIcon['chevron'] ?></span>
            <span class="app-nav__group">Planning <?= $navIcon['chevron'] ?></span>
          </nav>
          <div class="app-sidebar__footer">
            <span class="app-nav__item">Account</span>
            <span class="app-nav__item">Sign Out</span>
          </div>
        </aside>

        <div class="app-main">
          <!-- Top bar — KEPT (incl. existing "Chat with Fyn") -->
          <header class="app-topbar">
            <h1>Dashboard</h1>
            <div class="app-topbar__right">
              <span class="app-pill">Support</span>
              <span class="app-pill app-pill--chat">Chat with Fyn</span>
              <span class="app-pill">Azlan Raj</span>
            </div>
          </header>

          <div class="app-content">

            <!-- Gamified header: gradient bg + white progress bar + LEVEL UP -->
            <section class="gd-header">
              <p class="gd-header__hello">Good afternoon, Azlan</p>
              <div class="gd-header__top">
                <span class="gd-header__level">Level 1</span>
                <span class="gd-header__meta">0 of 3 actions complete — reach <strong>Level 2</strong></span>
              </div>
              <div class="gd-bar" role="img" aria-label="Level progress 0 percent"><div class="gd-bar__fill" style="width:4%"></div></div>
              <div class="gd-header__levelup">
                <span class="gd-header__lu-badge">LEVEL UP</span>
                <div>
                  <p class="gd-header__lu-lead">You're ahead of <strong>57%</strong> of people</p>
                  <p class="gd-header__lu-sub">Complete your actions to get further ahead, level up and change your financial future</p>
                </div>
              </div>
            </section>

            <!-- Where to focus (tabs) + recommendations — one connected card -->
            <section class="gd-card" aria-labelledby="gd-focus-h">
              <h2 id="gd-focus-h" class="visually-hidden">Where to focus</h2>
              <div class="gd-tabs" role="tablist" aria-label="Where to focus">
                <?php foreach ($focus as $i => $c): ?>
                  <button type="button" class="gd-tab js-tab<?= $i===0?' is-active':'' ?>" data-tab="<?= $c['key'] ?>" role="tab" aria-selected="<?= $i===0?'true':'false' ?>">
                    <span class="gd-tab__stat"><?= $c['stat'] ?></span>
                    <span class="gd-tab__area"><?= htmlspecialchars($c['label']) ?></span>
                  </button>
                <?php endforeach; ?>
              </div>

              <div class="gd-recs">
                <div class="gd-recs__head">
                  <h3>Recommendations</h3>
                  <span class="count">0 / 2</span>
                </div>
                <?php foreach ($focus as $i => $c): ?>
                  <div class="gd-recs__panel js-panel<?= $i===0?' is-active':'' ?>" data-panel="<?= $c['key'] ?>">
                    <p class="gd-recs__info"><?= htmlspecialchars($c['info']) ?></p>
                    <ul class="md-recs__list">
                      <?php foreach ($focusRecs[$c['key']] as $r) recRow($r, $check, $chevR); ?>
                    </ul>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>

            <!-- Your finances — existing module cards, max 3 per line -->
            <section aria-labelledby="gd-fin-h">
              <div class="gd-section-head"><h2 id="gd-fin-h">Your finances</h2></div>
              <div class="gd-finances__grid">
                <?php foreach ($panels as $p) panelTile($p); ?>
              </div>
            </section>

          </div>
        </div>
      </div>
    </section>

  </div>

  <script>
    // View toggle
    var btns = document.querySelectorAll('.mock-toggle button');
    var views = document.querySelectorAll('[data-view]');
    btns.forEach(function (b) {
      b.addEventListener('click', function () {
        btns.forEach(function (x) { x.classList.remove('is-active'); });
        b.classList.add('is-active');
        var show = b.getAttribute('data-show');
        views.forEach(function (v) {
          var k = v.getAttribute('data-view');
          v.classList.toggle('is-hidden', show !== 'both' && show !== k);
        });
      });
    });

    // Mobile accordion / dots — switch the active focus card
    function setActive(i) {
      document.querySelectorAll('.js-acc').forEach(function (el) {
        var on = el.getAttribute('data-i') === String(i);
        el.classList.toggle('is-active', on);
        el.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      document.querySelectorAll('.js-dot').forEach(function (el) {
        el.classList.toggle('is-active', el.getAttribute('data-i') === String(i));
      });
    }
    document.querySelectorAll('.js-acc, .js-dot').forEach(function (el) {
      el.addEventListener('click', function () { setActive(el.getAttribute('data-i')); });
    });

    // Desktop focus tabs — switch the active tab AND its recommendations panel
    document.querySelectorAll('.js-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var key = tab.getAttribute('data-tab');
        document.querySelectorAll('.js-tab').forEach(function (t) {
          var on = t.getAttribute('data-tab') === key;
          t.classList.toggle('is-active', on);
          t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.js-panel').forEach(function (p) {
          p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
        });
      });
    });
</script>

</body>
</html>
