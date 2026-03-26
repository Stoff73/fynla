<template>
  <PublicLayout>
    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-horizon-500 via-horizon-600 to-horizon-700 py-16 sm:py-20">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl sm:text-4xl font-black text-white mb-4">
          Simple to Set Up. Powerful to Use.
        </h1>
        <p class="text-base sm:text-lg text-horizon-200 max-w-2xl mx-auto mb-8">
          Fynla turns your scattered financial data into a clear, actionable plan. Three steps, 15 minutes, and you'll see your complete financial picture for the first time.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <router-link
            to="/?demo=true"
            class="px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
          >
            Try the Free Demo
          </router-link>
          <router-link
            to="/register"
            class="px-6 py-2.5 bg-white/10 text-white text-sm font-semibold rounded-lg border border-white/20 hover:bg-white/20 transition-colors"
          >
            Start Your Free Trial
          </router-link>
        </div>
      </div>
    </section>

    <!-- Steps Section -->
    <section class="py-16 bg-eggshell-500">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Step 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center mb-16">
          <div>
            <div class="inline-flex items-center px-3 py-1 rounded-full bg-raspberry-100 text-raspberry-600 text-xs font-semibold mb-4">
              Step 1
            </div>
            <h2 class="text-2xl font-bold text-horizon-500 mb-3">
              Tell Fynla What You've Got (And What You Owe)
            </h2>
            <p class="text-sm text-neutral-500 mb-4">
              Start by adding your key financial data. You don't need everything on day one — start with the basics and build up over time.
            </p>
            <ul class="space-y-2 mb-4">
              <li class="flex items-start gap-2 text-sm text-horizon-500">
                <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Pensions — workplace, personal, SIPP, state pension forecast
              </li>
              <li class="flex items-start gap-2 text-sm text-horizon-500">
                <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Property — home value, mortgage balance
              </li>
              <li class="flex items-start gap-2 text-sm text-horizon-500">
                <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Savings and investments — ISAs, savings accounts, investment accounts
              </li>
              <li class="flex items-start gap-2 text-sm text-horizon-500">
                <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Insurance — life, income protection, critical illness
              </li>
              <li class="flex items-start gap-2 text-sm text-horizon-500">
                <svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Debts — mortgage, loans, credit cards
              </li>
            </ul>
            <div class="flex items-center gap-4 text-xs text-neutral-500">
              <span class="inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                15-20 minutes initial setup
              </span>
              <span class="inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                No bank logins needed
              </span>
            </div>
          </div>
          <div class="bg-white rounded-xl border border-light-gray p-6">
            <div class="space-y-3">
              <div v-for="item in step1Items" :key="item.label" class="flex items-center justify-between py-2 border-b border-light-gray last:border-0">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm" :class="item.bgClass">
                    {{ item.icon }}
                  </div>
                  <span class="text-sm font-medium text-horizon-500">{{ item.label }}</span>
                </div>
                <span class="text-xs text-spring-600 font-medium">{{ item.status }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center mb-16">
          <div class="order-2 lg:order-1 bg-white rounded-xl border border-light-gray p-6">
            <div class="space-y-3">
              <div class="flex justify-between items-baseline">
                <span class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Net Worth</span>
                <span class="text-lg font-bold text-spring-600">&pound;487,250</span>
              </div>
              <div class="w-full bg-savannah-100 rounded-full h-1.5">
                <div class="h-1.5 rounded-full bg-spring-500" style="width: 72%"></div>
              </div>
              <div class="grid grid-cols-2 gap-3 pt-2">
                <div class="bg-eggshell-500 rounded-lg p-3">
                  <span class="text-xs text-neutral-500">Assets</span>
                  <div class="text-sm font-semibold text-horizon-500">&pound;612,000</div>
                </div>
                <div class="bg-eggshell-500 rounded-lg p-3">
                  <span class="text-xs text-neutral-500">Liabilities</span>
                  <div class="text-sm font-semibold text-raspberry-500">&pound;124,750</div>
                </div>
              </div>
              <div class="border-t border-light-gray pt-3 mt-2">
                <div class="flex justify-between text-xs">
                  <span class="text-neutral-500">Retirement Projection</span>
                  <span class="font-medium text-horizon-500">&pound;28,400/yr</span>
                </div>
                <div class="flex justify-between text-xs mt-1.5">
                  <span class="text-neutral-500">Protection Cover</span>
                  <span class="font-medium text-spring-600">Adequate</span>
                </div>
                <div class="flex justify-between text-xs mt-1.5">
                  <span class="text-neutral-500">Inheritance Tax Exposure</span>
                  <span class="font-medium text-violet-600">&pound;42,000</span>
                </div>
              </div>
            </div>
          </div>
          <div class="order-1 lg:order-2">
            <div class="inline-flex items-center px-3 py-1 rounded-full bg-spring-100 text-spring-600 text-xs font-semibold mb-4">
              Step 2
            </div>
            <h2 class="text-2xl font-bold text-horizon-500 mb-3">
              Your Complete Financial Dashboard
            </h2>
            <p class="text-sm text-neutral-500 mb-4">
              Once your data is in, Fynla shows you everything:
            </p>
            <ul class="space-y-2.5">
              <li class="flex items-start gap-2 text-sm text-horizon-500">
                <span class="font-semibold text-horizon-500">Net worth</span>
                <span class="text-neutral-500">— what you own minus what you owe, tracked over time</span>
              </li>
              <li class="flex items-start gap-2 text-sm text-horizon-500">
                <span class="font-semibold text-horizon-500">Retirement projection</span>
                <span class="text-neutral-500">— when you could afford to stop working</span>
              </li>
              <li class="flex items-start gap-2 text-sm text-horizon-500">
                <span class="font-semibold text-horizon-500">Protection gaps</span>
                <span class="text-neutral-500">— whether your family is adequately covered</span>
              </li>
              <li class="flex items-start gap-2 text-sm text-horizon-500">
                <span class="font-semibold text-horizon-500">Inheritance Tax exposure</span>
                <span class="text-neutral-500">— what HMRC would take from your estate</span>
              </li>
              <li class="flex items-start gap-2 text-sm text-horizon-500">
                <span class="font-semibold text-horizon-500">Pension overview</span>
                <span class="text-neutral-500">— all your pots in one place with growth projections</span>
              </li>
            </ul>
            <div class="mt-4 bg-violet-50 border border-violet-200 rounded-lg p-3">
              <p class="text-xs text-violet-700">
                This is the "aha moment" — the first time most people see their entire financial life on one screen.
              </p>
            </div>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
          <div>
            <div class="inline-flex items-center px-3 py-1 rounded-full bg-violet-100 text-violet-600 text-xs font-semibold mb-4">
              Step 3
            </div>
            <h2 class="text-2xl font-bold text-horizon-500 mb-3">
              Pull the Levers. See What Changes.
            </h2>
            <p class="text-sm text-neutral-500 mb-4">
              The real power of Fynla is scenario modelling. Ask "what if" and get instant answers:
            </p>
            <ul class="space-y-2">
              <li v-for="scenario in scenarios" :key="scenario" class="flex items-start gap-2 text-sm text-horizon-500">
                <svg class="w-4 h-4 text-violet-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                {{ scenario }}
              </li>
            </ul>
            <p class="text-sm text-neutral-500 mt-4">
              Every change updates your dashboard in real time. No waiting for an adviser. No spreadsheet gymnastics. Just answers.
            </p>
          </div>
          <div class="bg-white rounded-xl border border-light-gray p-6">
            <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-3">Scenario: Retire at 58</div>
            <div class="space-y-3">
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Projected Income</span>
                <div class="flex items-center gap-2">
                  <span class="text-neutral-400 line-through">&pound;28,400</span>
                  <span class="font-semibold text-raspberry-500">&pound;22,100/yr</span>
                </div>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Pension Pot at 58</span>
                <span class="font-semibold text-horizon-500">&pound;385,000</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-neutral-500">Shortfall</span>
                <span class="font-semibold text-violet-600">&pound;6,300/yr</span>
              </div>
              <div class="border-t border-light-gray pt-3">
                <div class="text-xs text-neutral-500 mb-2">What could close the gap:</div>
                <div class="space-y-1.5">
                  <div class="flex justify-between text-xs">
                    <span class="text-horizon-500">Increase contributions by 3%</span>
                    <span class="text-spring-600 font-medium">+&pound;4,200/yr</span>
                  </div>
                  <div class="flex justify-between text-xs">
                    <span class="text-horizon-500">Delay retirement to 60</span>
                    <span class="text-spring-600 font-medium">+&pound;8,900/yr</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- What Fynla Does Differently -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-base font-bold text-horizon-500 mb-2">What Fynla Does Differently</h2>
        <p class="text-sm text-neutral-500 mb-6">These aren't features on a list. They're moments where Fynla makes a real difference.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

          <!-- Mortgage rate alert story -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-full bg-spring-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
              </div>
              <div>
                <p class="text-sm font-semibold text-horizon-500 mb-1">Saved Thousands on a Mortgage</p>
                <p class="text-xs text-neutral-500 leading-relaxed">"Fynla alerted me that my fixed-rate mortgage was ending in 6 months. I locked in a new rate early — before rates went up. That single alert saved me thousands over the next 5 years."</p>
              </div>
            </div>
          </div>

          <!-- ICE letter story -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-full bg-raspberry-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-raspberry-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              </div>
              <div>
                <p class="text-sm font-semibold text-horizon-500 mb-1">Everything in One Document</p>
                <p class="text-xs text-neutral-500 leading-relaxed">Your partner doesn't know the mortgage provider's name. Your children have no idea where the pensions are. Fynla's ICE letter puts everything your family needs in one document — auto-populated from your plan. 15 minutes now could save them months of stress.</p>
              </div>
            </div>
          </div>

          <!-- Protection gap story -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
              </div>
              <div>
                <p class="text-sm font-semibold text-horizon-500 mb-1">The Cover You Didn't Know You Were Missing</p>
                <p class="text-xs text-neutral-500 leading-relaxed">Your workplace life insurance pays 2x your salary. Sounds decent — until you realise your mortgage alone is 6x your salary. Fynla calculates exactly what your family would need and shows you the gap in black and white.</p>
              </div>
            </div>
          </div>

          <!-- Pension consolidation story -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-full bg-horizon-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-horizon-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
              </div>
              <div>
                <p class="text-sm font-semibold text-horizon-500 mb-1">Six Jobs, Six Forgotten Pension Pots</p>
                <p class="text-xs text-neutral-500 leading-relaxed">The average UK worker has 11 jobs in their lifetime. That's potentially 11 pension pots with 11 different providers. Fynla brings them all into one dashboard so you can finally see what you've actually got — and whether it's enough.</p>
              </div>
            </div>
          </div>

          <!-- Will guidance story -->
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-full bg-savannah-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-savannah-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>
              </div>
              <div>
                <p class="text-sm font-semibold text-horizon-500 mb-1">54% of UK Adults Don't Have a Will</p>
                <p class="text-xs text-neutral-500 leading-relaxed">Without a will, the law decides who gets what — and unmarried partners get nothing. Fynla helps you understand what's at stake and guides you through getting your estate in order, including wills, Lasting Powers of Attorney, and Inheritance Tax planning.</p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Meet Fyn Section -->
    <section class="py-16 bg-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl font-bold text-horizon-500 mb-3">Stuck? Ask Fyn.</h2>
        <p class="text-sm text-neutral-500 max-w-xl mx-auto mb-8">
          Fyn is your AI financial assistant, built into Fynla. Ask any question in plain English. No jargon, no judgement.
        </p>
        <div class="max-w-lg mx-auto bg-eggshell-500 rounded-xl border border-light-gray p-4 text-left">
          <div class="space-y-3">
            <div v-for="q in fynQuestions" :key="q" class="bg-white rounded-lg px-3 py-2 text-sm text-horizon-500 border border-light-gray">
              "{{ q }}"
            </div>
          </div>
          <div class="mt-3 flex items-center gap-2 bg-white rounded-lg px-3 py-2 border border-light-gray">
            <span class="text-xs text-neutral-400">Ask Fyn anything...</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Demo Section -->
    <section class="py-16 bg-eggshell-500">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl font-bold text-horizon-500 mb-3">See It All in Action — No Sign-Up Required</h2>
        <p class="text-sm text-neutral-500 max-w-xl mx-auto mb-6">
          Our interactive demo lets you explore Fynla using pre-built personas with realistic UK financial data. You'll see the full dashboard, try the scenario modelling, check the Inheritance Tax calculator, and explore every feature.
        </p>
        <p class="text-xs text-neutral-500 mb-6">It takes 5 minutes and requires zero personal information.</p>
        <router-link
          to="/?demo=true"
          class="inline-flex px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
        >
          Explore the Interactive Demo
        </router-link>
      </div>
    </section>

    <!-- What Happens Next -->
    <section class="py-16 bg-white">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-horizon-500 text-center mb-10">From Demo to Your Own Plan</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="(step, idx) in nextSteps" :key="idx" class="bg-eggshell-500 rounded-lg p-4 border border-light-gray">
            <div class="w-7 h-7 rounded-full bg-raspberry-100 text-raspberry-600 text-xs font-bold flex items-center justify-center mb-2">
              {{ idx + 1 }}
            </div>
            <p class="text-sm text-horizon-500">{{ step }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Final CTA -->
    <section class="py-16 bg-gradient-to-br from-horizon-500 to-horizon-600">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-2xl font-bold text-white mb-3">15 Minutes to Clarity</h2>
        <p class="text-sm text-horizon-200 max-w-lg mx-auto mb-6">
          That's all it takes to see your complete financial picture. Start with the demo, or go straight to your free trial.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <router-link
            to="/?demo=true"
            class="px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
          >
            Try the Free Demo
          </router-link>
          <router-link
            to="/register"
            class="px-6 py-2.5 bg-white/10 text-white text-sm font-semibold rounded-lg border border-white/20 hover:bg-white/20 transition-colors"
          >
            Start Your Free Trial
          </router-link>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'HowItWorksPage',

  components: {
    PublicLayout,
  },

  data() {
    return {
      step1Items: [
        { icon: '🏠', label: 'Property', status: 'Added', bgClass: 'bg-spring-100' },
        { icon: '💷', label: 'Pensions', status: 'Added', bgClass: 'bg-violet-100' },
        { icon: '📊', label: 'Investments', status: 'Added', bgClass: 'bg-raspberry-100' },
        { icon: '🛡', label: 'Insurance', status: '2 of 3', bgClass: 'bg-savannah-100' },
        { icon: '💳', label: 'Debts', status: 'Added', bgClass: 'bg-horizon-100' },
      ],
      scenarios: [
        'What if I increase pension contributions by 2%?',
        'What if I retire at 58 instead of 62?',
        'What if I overpay my mortgage by £200/month?',
        'What if I gift £50,000 to my children now?',
        'What if markets crash by 30% in year one of retirement?',
      ],
      fynQuestions: [
        'What\'s the difference between drawdown and an annuity?',
        'Am I using my ISA allowance efficiently?',
        'How does the 7-year gifting rule work?',
        'What does my protection gap mean?',
      ],
      nextSteps: [
        'Explore the demo with sample data',
        'Sign up for your free trial',
        'Add your financial data (15-20 minutes)',
        'See your personal financial dashboard',
        'Model scenarios and refine your plan',
        'Check in monthly or quarterly to keep it current',
      ],
    };
  },
};
</script>
