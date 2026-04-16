<template>
  <PublicLayout>
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-x-hidden">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10">

        <!-- Heading -->
        <h1 class="relative z-10 text-4xl sm:text-5xl lg:text-8xl leading-tight mb-3 text-white">
          Change your<br />
          <span class="text-raspberry-300">financial future</span>
        </h1>

        <!-- Body paragraph -->
        <p class="relative z-10 text-white/80 mb-4 max-w-2xl leading-relaxed">
          We help create your path to financial freedom<br />
          with clear recommendations from our proprietary Fynla Brain&reg;
        </p>

        <!-- CTA -->
        <div class="relative z-10 flex flex-col items-start gap-3 mb-0">
          <router-link to="/register" class="px-16 py-2.5 text-lg bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-all" @click="trackGA('cta_click', 'get_started')">Get started</router-link>
          <p class="text-sm text-white/70 flex flex-wrap items-center gap-2">
            <a href="#meet-fyn" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="scrollToMeetFyn">Meet Fyn</a>
            <span class="text-white/40">|</span>
            <a href="#dashboard" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="scrollToDashboard">View the video</a>
            <span class="text-white/40">|</span>
            <a href="/?demo=true" class="text-white/90 no-underline hover:text-spring-400 transition-colors" @click.prevent="trackGA('cta_click', 'see_demo'); enterPreviewMode()">See our demo</a>
          </p>
        </div>

        <!-- Mobile: brain GIF + stacked caption cards (shown below lg) -->
        <div class="flex flex-col gap-3 mt-6 pb-6 lg:hidden">
          <!-- Brain GIF in its own box above caption cards -->
          <div class="bg-white rounded-lg px-4 py-4 shadow-sm flex justify-center">
            <img
              src="/images/Website/Fyn-Brain-Animation-Whitev2M.gif"
              alt="Fynla Brain animation"
              class="w-28 h-auto rounded-lg"
            />
          </div>
          <div class="bg-white rounded-lg px-4 py-4 shadow-sm">
            <p class="text-lg font-bold text-horizon-500 mb-1">One financial view.</p>
            <p class="text-sm text-neutral-500 leading-tight">Use Fynla to securely centralise and view all your financial data.</p>
          </div>
          <div class="bg-white rounded-lg px-4 py-4 shadow-sm">
            <p class="text-lg font-bold text-horizon-500 mb-1">One financial brain.</p>
            <p class="text-sm text-neutral-500 leading-tight">Our proprietary brain does the calculations so you don't have to.</p>
          </div>
          <div class="bg-white rounded-lg px-4 py-4 shadow-sm">
            <p class="text-lg font-bold text-horizon-500 mb-1">One financial voice.</p>
            <p class="text-sm text-neutral-500 leading-tight">We will give you clear, simple and tailored advice to help your financial freedom.</p>
          </div>
        </div>

        <!-- Desktop: three-panel composite as background with caption cards on top -->
        <div
          class="relative hidden lg:block overflow-hidden mx-auto"
          style="width: 108%; margin-left: -4%; margin-top: -80px; margin-bottom: 8px;"
        >
          <img
            src="/images/Website/Homepage-Header-Desktopv3.png"
            alt="Fynla Brain — your financial planning intelligence"
            width="1315"
            height="489"
            class="w-full h-auto block"
          />
          <!-- Caption card: left -->
          <div class="absolute top-[62px] xl:top-[4.5rem] left-[4%] px-4 lg:pt-[54px] xl:pt-24 py-3 max-w-[28%] xl:max-w-xs">
            <p class="lg:text-base xl:text-2xl font-bold text-horizon-500 mb-1">One financial view.</p>
            <p class="text-xs xl:text-sm text-neutral-500 leading-tight">Use Fynla to securely centralise and view all your financial data.</p>
          </div>

          <!-- Caption card: centre -->
          <div class="absolute top-14 bottom-0 left-1/2 -translate-x-1/2 py-3 max-w-[28%] xl:max-w-xs text-center flex flex-col overflow-hidden">
            <div class="px-4">
              <p class="lg:text-lg xl:text-2xl font-bold text-horizon-500 mb-1">One financial brain.</p>
              <p class="text-xs xl:text-sm text-neutral-500 leading-tight">Our proprietary brain does the calculations so you don't have to.</p>
            </div>
            <div class="flex-1 flex items-end justify-center overflow-hidden mt-1">
              <img
                src="/images/Website/Fyn-Brain-Animation-Whitev2M.gif"
                alt="Fynla Brain"
                class="w-auto max-w-full max-h-full object-contain object-bottom"
              />
            </div>
          </div>

          <!-- Caption card: right -->
          <div class="absolute top-[62px] xl:top-[4.5rem] right-[4%] px-4 lg:pt-[54px] xl:pt-24 py-3 max-w-[28%] xl:max-w-xs text-right">
            <p class="lg:text-base xl:text-2xl font-bold text-horizon-500 mb-1">One financial voice.</p>
            <p class="text-xs xl:text-sm text-neutral-500 leading-tight">We will give you clear, simple and tailored advice to help your financial freedom.</p>
          </div>
        </div>

      </div>
    </div>

    <!-- Meet Fyn Section -->
    <div id="meet-fyn" class="bg-light-pink-100 pt-6 pb-8 lg:-mt-2 lg:pt-[10px]" :class="fynDetailsOpen ? 'lg:pb-14' : 'lg:pb-0'">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:gap-10">

          <!-- Left: Meet Fyn text -->
          <div class="flex-1 lg:self-start lg:pt-10">
            <!-- Mobile: Meet Fyn heading + small Fyn character side by side -->
            <div class="flex items-end justify-between lg:hidden mb-1">
              <h2 class="text-6xl font-bold text-horizon-500 leading-none">Meet Fyn</h2>
              <img
                src="/images/Fyn/Design Character 001a.webp"
                alt="Fyn — your AI financial companion"
                loading="lazy"
                width="324"
                height="427"
                class="h-28 w-auto -mb-2"
              />
            </div>
            <!-- Desktop: Meet Fyn heading only (Fyn character shown separately on right) -->
            <h2 class="hidden lg:block text-8xl font-bold text-horizon-500 leading-none mb-1 lg:-mt-[24px]">Meet Fyn</h2>
            <p class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-semibold text-neutral-500 mb-2">
              Your financial companion for life
            </p>
            <p class="text-sm text-neutral-500 mb-4 max-w-2xl leading-relaxed">
              Need help? Fyn is the face of our Fynla brain and will help you with your finance goals by giving you financial clarity. Fyn will help with everything from planning to saving and investments, through to your net worth and real estate. Tell Fyn what you'd like to do and he'll walk you through your dashboard step-by-step.
            </p>

            <!-- Expandable: What can Fyn help you with? -->
            <div class="max-w-2xl mb-5">
              <button
                type="button"
                class="flex items-center gap-2 text-sm font-semibold text-horizon-500 hover:text-raspberry-500 transition-colors"
                @click="fynDetailsOpen = !fynDetailsOpen"
              >
                <span>What can Fyn help you with?</span>
                <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': fynDetailsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <transition name="expand">
                <ul v-if="fynDetailsOpen" class="mt-3 space-y-2 text-sm text-neutral-500">
                  <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    Your financial companion to answer any questions you have
                  </li>
                  <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    Help understand and complete your dashboard
                  </li>
                  <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    Support you to achieve your financial goals
                  </li>
                </ul>
              </transition>
            </div>

            <!-- Get started with Fyn (hidden until new-user Fyn flow is fixed — see April/April9Updates/fynQuickStartBugs.md) -->
            <!-- <router-link
              to="/register?from=fyn"
              class="inline-block px-12 py-3 text-lg bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-colors whitespace-nowrap"
            >
              Quick start with Fyn
            </router-link> -->
          </div>

          <!-- Right: Fyn character -->
          <div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center justify-end" :class="fynDetailsOpen ? 'lg:self-end' : ''">
            <img src="/images/Fyn/Design Character 001a.webp" alt="Fyn — your AI financial companion" loading="lazy" width="324" height="427" :class="fynDetailsOpen ? 'h-[427px] w-auto lg:-mb-[6em]' : 'h-[427px] w-auto lg:-mb-[3em]'" />
          </div>

        </div>
      </div>
    </div>

    <!-- How Fyn Can Help You -->
    <div id="features" class="bg-gradient-to-r from-horizon-600 to-horizon-700 py-10 lg:py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl text-center mb-4 text-white">How Fyn can help you</h2>
        <p class="text-center text-white/70 text-sm max-w-2xl mx-auto mb-10 leading-relaxed">
          We leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Protection -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-raspberry-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Protection</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Analyse life insurance, critical illness, and income protection coverage gaps to ensure your family is fully protected.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Life Cover</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Income Protection</span>
            </div>
          </div>

          <!-- Savings -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-spring-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Savings</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Track emergency funds, ISA allowances, and savings goals across all your accounts with smart benchmarking.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Emergency Fund</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">ISA</span>
            </div>
          </div>

          <!-- Investment -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-violet-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Investment</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Portfolio analysis, risk profiling, and Monte Carlo projections to optimise your investment strategy.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Portfolio</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Risk Profile</span>
            </div>
          </div>

          <!-- Retirement -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-light-blue-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Retirement</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Defined Contribution, Defined Benefit, and State Pension tracking with retirement income projections.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Pension</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">State Pension</span>
            </div>
          </div>

          <!-- Estate -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-savannah-500 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Estate</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Inheritance Tax calculations, gifting strategies, and estate value projections for effective planning.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Inheritance Tax</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Trusts</span>
            </div>
          </div>

          <!-- Net Worth -->
          <div class="bg-horizon-500 rounded-card p-6 transition-all duration-200">
            <div class="w-10 h-10 rounded-full bg-horizon-400 flex items-center justify-center mb-4">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <h3 class="text-white mb-2">Net Worth</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">Complete balance sheet with properties, assets, and liabilities tracking for a clear financial picture.</p>
            <div class="flex flex-wrap gap-2">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Assets</span>
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">Liabilities</span>
            </div>
          </div>
        </div>

        <div class="text-center mt-10 flex items-center justify-center gap-3">
          <router-link to="/features" class="text-white font-medium hover:text-white/70 transition-colors">
            View detailed list of features &gt;
          </router-link>
          <span class="text-white/30">|</span>
          <a href="/?demo=true" class="text-white font-medium hover:text-white/70 transition-colors" @click.prevent="enterPreviewMode">
            View demos &gt;
          </a>
        </div>
      </div>
    </div>

    <!-- Your Fynla Dashboard -->
    <div id="dashboard" class="bg-eggshell-500 py-10 lg:py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl text-center mb-12">Your Fynla dashboard</h2>
        <div class="card-lg overflow-hidden p-0">
          <div class="flex items-center gap-2 px-4 py-2.5 bg-savannah-100 border-b border-light-gray">
            <span class="w-3 h-3 rounded-full bg-raspberry-500"></span>
            <span class="w-3 h-3 rounded-full bg-violet-500"></span>
            <span class="w-3 h-3 rounded-full bg-spring-500"></span>
            <span class="ml-3 text-xs text-neutral-500 font-mono">fynla.org</span>
          </div>
          <video
            src="/images/fynla-dashboard-walkthrough.mp4"
            autoplay
            loop
            muted
            playsinline
            width="1346"
            height="665"
            class="w-full h-auto block"
          >
            Your browser does not support the video tag.
          </video>
        </div>
      </div>
    </div>

    <!-- Review Carousel -->
    <ReviewCarousel />

    <!-- Scenarios -->
    <div id="solutions" class="bg-eggshell-500 pt-10 lg:pt-12 pb-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl text-center mb-12">Your personal journey</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-10">
          <!-- Starting out -->
          <router-link to="/stage/starting-out" class="lg:aspect-square bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-5 flex flex-col items-start justify-center cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200 no-underline">
            <p class="text-2xl font-bold text-white mb-1 leading-tight"><span class="text-raspberry-400">Starting</span><br/>out</p>
            <p class="text-xs text-white/70 leading-snug">Build strong financial habits for your future.</p>
          </router-link>

          <!-- Building foundations -->
          <router-link to="/stage/building-foundations" class="lg:aspect-square bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-5 flex flex-col items-start justify-center cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200 no-underline">
            <p class="text-2xl font-bold text-white mb-1 leading-tight"><span class="text-raspberry-400">Building</span><br/>foundations</p>
            <p class="text-xs text-white/70 leading-snug">Save, invest, and grow your wealth with confidence.</p>
          </router-link>

          <!-- Protecting and growing -->
          <router-link to="/stage/protecting-and-growing" class="lg:aspect-square bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-5 flex flex-col items-start justify-center cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200 no-underline">
            <p class="text-2xl font-bold text-white mb-1 leading-tight"><span class="text-raspberry-400">Protecting</span><br/>and growing</p>
            <p class="text-xs text-white/70 leading-snug">Ensure your family and assets are fully covered.</p>
          </router-link>

          <!-- Planning your future -->
          <router-link to="/stage/planning-your-future" class="lg:aspect-square bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-5 flex flex-col items-start justify-center cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200 no-underline">
            <p class="text-2xl font-bold text-white mb-1 leading-tight"><span class="text-raspberry-400">Planning</span><br/>your future</p>
            <p class="text-xs text-white/70 leading-snug">Retirement projections, pension tracking, and estate planning.</p>
          </router-link>

          <!-- Enjoying your wealth -->
          <router-link to="/stage/enjoying-your-wealth" class="lg:aspect-square bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-5 flex flex-col items-start justify-center cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200 no-underline">
            <p class="text-2xl font-bold text-white mb-1 leading-tight"><span class="text-raspberry-400">Enjoying</span><br/>your wealth</p>
            <p class="text-xs text-white/70 leading-snug">Make the most of your financial success.</p>
          </router-link>
        </div>

        <div class="text-center">
          <button type="button" @click="trackGA('cta_click', 'view_demo'); enterPreviewMode()" :disabled="enteringPreview" class="px-8 py-2 bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 active:bg-spring-700 transition-all duration-150 shadow-sm hover:shadow-md">
            View demo
          </button>
        </div>

      </div>
    </div>

    <!-- Latest insights -->
    <div class="bg-light-pink-100 pt-12 pb-28">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 text-center mb-6" style="letter-spacing:-0.02em;">Latest insights</h2>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-5xl mx-auto">
          <router-link
            v-for="article in latestInsights"
            :key="article.slug"
            :to="article.slug"
            class="group bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all flex flex-col no-underline"
          >
            <div class="aspect-[16/9] overflow-hidden bg-horizon-100">
              <img
                :src="getInsightImage(article.image)"
                :alt="article.title"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
              />
            </div>
            <div class="p-3.5 flex-1 flex flex-col">
              <p class="text-[0.65rem] text-neutral-400 mb-1 uppercase tracking-wide">{{ article.date }}</p>
              <h3 class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors mb-1.5 leading-snug">
                {{ article.title }}
              </h3>
              <p class="text-xs text-neutral-500 mb-2 leading-relaxed flex-1 line-clamp-2">{{ article.summary }}</p>
              <span class="text-xs font-semibold text-raspberry-500">Read &rarr;</span>
            </div>
          </router-link>
        </div>

        <div class="text-center mt-6">
          <router-link to="/insights" class="text-sm font-semibold text-horizon-500 hover:text-raspberry-500">
            See all insights &rarr;
          </router-link>
        </div>
      </div>
    </div>

    <!-- Stats Bar - Straddles latest insights section and footer -->
    <div class="relative z-10 -mt-14 -mb-24">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="card-lg flex flex-row items-start justify-around gap-3 sm:gap-6 py-6 sm:py-8">
          <div class="text-center flex-1 min-w-0">
            <div class="text-2xl sm:text-4xl font-bold text-horizon-500">91%</div>
            <div class="text-[0.7rem] sm:text-sm font-semibold text-neutral-500 mt-1 leading-tight">UK adults don't get financial advice</div>
          </div>
          <div class="block w-px self-stretch bg-light-gray"></div>
          <div class="text-center flex-1 min-w-0">
            <div class="text-2xl sm:text-4xl font-bold text-horizon-500">1</div>
            <div class="text-[0.7rem] sm:text-sm font-semibold text-neutral-500 mt-1 leading-tight">The only UK platform designed for students to retirees</div>
          </div>
          <div class="block w-px self-stretch bg-light-gray"></div>
          <div class="text-center flex-1 min-w-0">
            <div class="text-2xl sm:text-4xl font-bold text-horizon-500">30+</div>
            <div class="text-[0.7rem] sm:text-sm font-semibold text-neutral-500 mt-1 leading-tight">Fynla features for financial planning</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Persona Selection Modal -->
    <PersonaSelectionModal
      :is-open="showSelectionModal"
      :personas="availablePersonas"
      :error="previewError"
      @close="cancelPreview"
      @select="handlePersonaSelect"
    />
  </PublicLayout>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import { nextTick } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import PersonaSelectionModal from '@/components/Preview/PersonaSelectionModal.vue';
import ReviewCarousel from '@/components/Public/ReviewCarousel.vue';

import logger from '@/utils/logger';

// Auto-import insight images so the latest-insights panels resolve the same
// bundled URLs as the Insights hub page.
const insightImages = import.meta.glob('@/assets/insights/*.{jpg,png,webp}', { eager: true, import: 'default' });

export default {
  name: 'LandingPage',

  components: {
    PublicLayout,
    PersonaSelectionModal,
    ReviewCarousel,
  },

  data() {
    return {
      enteringPreview: false,
      showSelectionModal: false,
      previewError: '',
      chatInput: '',
      fynDetailsOpen: false,
      latestInsights: [
        {
          slug: '/insights/how-much-to-retire-uk',
          title: 'How Much Do I Need to Retire in the UK?',
          date: '14 April 2026',
          summary: 'Calculate your UK retirement number using 2026 PLSA living standards and bridge the State Pension gap.',
          image: 'how-much-to-retire-uk.jpg',
        },
        {
          slug: '/insights/stocks-shares-isa-uk',
          title: 'What Is a Stocks and Shares ISA?',
          date: '13 April 2026',
          summary: 'How they work, what you can invest in, tax benefits, risks, fees, and how to choose a platform.',
          image: 'stocks-shares-isa.jpg',
        },
        {
          slug: '/insights/isa-guide-uk',
          title: 'The Ultimate Guide to ISAs in the UK',
          date: '8 April 2026',
          summary: 'Everything you need to know about ISAs in 2026 — types, allowances, rules, and choosing the right one.',
          image: 'isa-guide-uk.jpg',
        },
      ],
    };
  },

  computed: {
    ...mapGetters('preview', ['availablePersonas']),
  },

  mounted() {
    document.title = 'Fyn, your financial companion | Fynla is your complete personal finance platform for planning, savings and investments';
    this.setMetaDescription('Fynla is a UK personal finance platform that helps you plan savings, investments, pensions, retirement and estate. See your complete financial picture in one place.');
    this.checkDemoParam();
  },

  watch: {
    '$route.query.demo': {
      handler(newVal) {
        if (newVal === 'true') {
          this.checkDemoParam();
        }
      },
    },
  },

  methods: {
    ...mapActions('preview', ['loadPersona']),

    getInsightImage(filename) {
      if (!filename) return null;
      const key = Object.keys(insightImages).find(k => k.endsWith('/' + filename));
      return key ? insightImages[key] : null;
    },

    setMetaDescription(content) {
      let meta = document.querySelector('meta[name="description"]');
      if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute('name', 'description');
        document.head.appendChild(meta);
      }
      meta.setAttribute('content', content);
    },

    trackGA(eventName, label) {
      if (typeof gtag === 'function') {
        gtag('event', eventName, { event_label: label });
      }
    },

    checkDemoParam() {
      if (this.$route.query.demo === 'true') {
        this.showSelectionModal = true;
        this.$router.replace({ path: '/', query: {} });
      }
    },

    handleAskFyn() {
      this.enterPreviewMode();
    },

    scrollToMeetFyn() {
      this.smoothScrollTo('meet-fyn');
    },

    scrollToDashboard() {
      this.smoothScrollTo('dashboard', 2000);
    },

    smoothScrollTo(elementId, duration = 1500) {
      const el = document.getElementById(elementId);
      if (!el) return;
      const navbarHeight = document.querySelector('nav')?.offsetHeight || 0;
      const target = el.getBoundingClientRect().top + window.scrollY - navbarHeight;
      const start = window.scrollY;
      const distance = target - start;
      const dur = duration;
      let startTime = null;

      function easeInOutCubic(t) {
        return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
      }

      function step(timestamp) {
        if (!startTime) startTime = timestamp;
        const elapsed = timestamp - startTime;
        const progress = Math.min(elapsed / dur, 1);
        window.scrollTo(0, start + distance * easeInOutCubic(progress));
        if (progress < 1) requestAnimationFrame(step);
      }

      requestAnimationFrame(step);
    },

    enterPreviewMode() {
      this.previewError = '';
      this.showSelectionModal = true;
    },

    cancelPreview() {
      this.showSelectionModal = false;
      this.previewError = '';
    },

    async handlePersonaSelect(persona) {
      if (this.enteringPreview) return;
      this.enteringPreview = true;
      this.previewError = '';

      try {
        await this.$store.dispatch('preview/loadPersona', persona.id);
        await nextTick();
        this.$router.push('/dashboard');
      } catch (error) {
        logger.error('Failed to enter preview mode:', error);
        this.previewError = 'Unable to load demo. Please try again or check your connection.';
        this.enteringPreview = false;
      }
    },
  },
};
</script>
