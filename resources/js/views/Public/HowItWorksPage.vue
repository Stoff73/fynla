<template>
  <PublicLayout>
    <!-- Hero Section — pricing page style, left-aligned -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Getting started<br/>
          <span class="text-raspberry-300">with Fynla</span>
        </h1>
        <p class="text-xl text-white/90 font-semibold mb-2">Simple to set up. Powerful to use.</p>
        <p class="text-lg text-white/70 max-w-2xl mb-8">
          Fynla turns your scattered financial data into a clear, actionable plan. Three steps, 15 minutes, and you'll see your complete financial picture for the first time.
        </p>
        <div class="flex flex-col sm:flex-row items-start gap-3">
          <a
            href="#" @click.prevent="$router.push({ query: { demo: 'true' } })"
            class="px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
          >
            Try the free demo
          </a>
          <router-link
            to="/register"
            class="px-6 py-2.5 bg-light-pink-100 text-horizon-500 text-sm font-semibold rounded-lg hover:bg-light-pink-200 transition-colors"
          >
            Start your free trial
          </router-link>
        </div>
      </div>
    </div>

    <!-- Steps Section -->
    <section class="py-16 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-10">Fynla is as easy as 1-2-3...</h2>

        <!-- Step 1 — dark grey to light grey gradient -->
        <div class="rounded-2xl overflow-hidden mb-16" style="background: linear-gradient(135deg, #4A4A4A, #9E9E9E);">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center p-8 lg:p-10">
            <div>
              <h2 class="text-3xl lg:text-4xl font-black text-white mb-2">Step 1</h2>
              <h3 class="text-lg font-semibold text-white/80 mb-3">Tell Fynla what you've got (and what you owe)</h3>
              <p class="text-sm text-white/60 mb-4">Start by adding your key financial data. You don't need everything on day one — start with the basics and build up over time.</p>
              <ul class="space-y-2 mb-4">
                <li class="flex items-start gap-2 text-sm text-white/80"><svg class="w-4 h-4 text-spring-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Pensions — workplace, personal, Self-Invested Personal Pension, state pension forecast</li>
                <li class="flex items-start gap-2 text-sm text-white/80"><svg class="w-4 h-4 text-spring-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Property — home value, mortgage balance</li>
                <li class="flex items-start gap-2 text-sm text-white/80"><svg class="w-4 h-4 text-spring-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Savings and investments — ISAs, savings accounts, investment accounts</li>
                <li class="flex items-start gap-2 text-sm text-white/80"><svg class="w-4 h-4 text-spring-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Insurance — life, income protection, critical illness</li>
                <li class="flex items-start gap-2 text-sm text-white/80"><svg class="w-4 h-4 text-spring-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Debts — mortgage, loans, credit cards</li>
              </ul>
              <div class="flex items-center gap-4 text-xs text-white/50">
                <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>15-20 minutes initial setup</span>
                <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>No bank logins needed</span>
              </div>
            </div>
            <div class="bg-white rounded-xl border border-light-gray p-6 shadow-sm">
              <div class="space-y-3">
                <div v-for="item in step1Items" :key="item.label" class="flex items-center justify-between py-2 border-b border-light-gray last:border-0">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm" :class="item.bgClass">{{ item.icon }}</div>
                    <span class="text-sm font-medium text-horizon-500">{{ item.label }}</span>
                  </div>
                  <span class="text-xs text-spring-600 font-medium">{{ item.status }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2 — light blue to horizon blue gradient -->
        <div class="rounded-2xl overflow-hidden mb-16" style="background: linear-gradient(135deg, #B8D4F0, #1F2A44);">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center p-8 lg:p-10">
            <div class="bg-white rounded-xl border border-light-gray p-6 shadow-sm order-2 lg:order-1">
              <div class="space-y-3">
                <div class="flex justify-between items-baseline">
                  <span class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Net worth</span>
                  <span class="text-lg font-bold text-spring-600">&pound;487,250</span>
                </div>
                <div class="w-full bg-savannah-100 rounded-full h-1.5"><div class="h-1.5 rounded-full bg-spring-500" style="width: 72%"></div></div>
                <div class="grid grid-cols-2 gap-3 pt-2">
                  <div class="bg-eggshell-500 rounded-lg p-3"><span class="text-xs text-neutral-500">Assets</span><div class="text-sm font-semibold text-horizon-500">&pound;612,000</div></div>
                  <div class="bg-eggshell-500 rounded-lg p-3"><span class="text-xs text-neutral-500">Liabilities</span><div class="text-sm font-semibold text-raspberry-500">&pound;124,750</div></div>
                </div>
                <div class="border-t border-light-gray pt-3 mt-2">
                  <div class="flex justify-between text-xs"><span class="text-neutral-500">Retirement projection</span><span class="font-medium text-horizon-500">&pound;28,400/yr</span></div>
                  <div class="flex justify-between text-xs mt-1.5"><span class="text-neutral-500">Protection cover</span><span class="font-medium text-spring-600">Adequate</span></div>
                  <div class="flex justify-between text-xs mt-1.5"><span class="text-neutral-500">Inheritance Tax exposure</span><span class="font-medium text-violet-600">&pound;42,000</span></div>
                </div>
              </div>
            </div>
            <div class="order-1 lg:order-2">
              <h2 class="text-3xl lg:text-4xl font-black text-white mb-2">Step 2</h2>
              <h3 class="text-lg font-semibold text-white/80 mb-3">Your complete financial dashboard</h3>
              <p class="text-sm text-white/60 mb-4">Once your data is in, Fynla shows you everything:</p>
              <ul class="space-y-2.5">
                <li class="flex items-start gap-3 text-sm">
                  <svg class="w-5 h-5 text-white/70 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                  <div><span class="font-semibold text-white">Net worth</span> <span class="text-white/60">— what you own minus what you owe</span></div>
                </li>
                <li class="flex items-start gap-3 text-sm">
                  <svg class="w-5 h-5 text-white/70 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  <div><span class="font-semibold text-white">Retirement projection</span> <span class="text-white/60">— when you could afford to stop</span></div>
                </li>
                <li class="flex items-start gap-3 text-sm">
                  <svg class="w-5 h-5 text-white/70 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                  <div><span class="font-semibold text-white">Protection gaps</span> <span class="text-white/60">— whether your family is covered</span></div>
                </li>
                <li class="flex items-start gap-3 text-sm">
                  <svg class="w-5 h-5 text-white/70 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                  <div><span class="font-semibold text-white">Inheritance Tax exposure</span> <span class="text-white/60">— what HMRC would take</span></div>
                </li>
                <li class="flex items-start gap-3 text-sm">
                  <svg class="w-5 h-5 text-white/70 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                  <div><span class="font-semibold text-white">Pension overview</span> <span class="text-white/60">— all your pots in one place</span></div>
                </li>
              </ul>
              <div class="mt-4 bg-white/10 border border-white/20 rounded-lg p-3">
                <p class="text-xs text-white/70">This is the "aha moment" — the first time most people see their entire financial life on one screen.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 3 — raspberry pink to light pink gradient -->
        <div class="rounded-2xl overflow-hidden" style="background: linear-gradient(135deg, #E8456B, #F5B3C5);">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center p-8 lg:p-10">
            <div>
              <h2 class="text-3xl lg:text-4xl font-black text-white mb-2">Step 3</h2>
              <h3 class="text-lg font-semibold text-white/80 mb-3">Pull the levers. See what changes.</h3>
              <p class="text-sm text-white/60 mb-4">The real power of Fynla is scenario modelling. Ask "what if" and get instant answers:</p>
              <ul class="space-y-2">
                <li v-for="scenario in scenarios" :key="scenario" class="flex items-start gap-2 text-sm text-white/80">
                  <svg class="w-4 h-4 text-white mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                  {{ scenario }}
                </li>
              </ul>
              <p class="text-sm text-white/60 mt-4">Every change updates your dashboard in real time. No waiting for an adviser. No spreadsheet gymnastics. Just answers.</p>
            </div>
            <div class="bg-white rounded-xl border border-light-gray p-6 shadow-sm">
              <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-3">Scenario: Retire at 58</div>
              <div class="space-y-3">
                <div class="flex justify-between text-sm"><span class="text-neutral-500">Projected income</span><div class="flex items-center gap-2"><span class="text-neutral-400 line-through">&pound;28,400</span><span class="font-semibold text-raspberry-500">&pound;22,100/yr</span></div></div>
                <div class="flex justify-between text-sm"><span class="text-neutral-500">Pension pot at 58</span><span class="font-semibold text-horizon-500">&pound;385,000</span></div>
                <div class="flex justify-between text-sm"><span class="text-neutral-500">Shortfall</span><span class="font-semibold text-violet-600">&pound;6,300/yr</span></div>
                <div class="border-t border-light-gray pt-3">
                  <div class="text-xs text-neutral-500 mb-2">What could close the gap:</div>
                  <div class="space-y-1.5">
                    <div class="flex justify-between text-xs"><span class="text-horizon-500">Increase contributions by 3%</span><span class="text-spring-600 font-medium">+&pound;4,200/yr</span></div>
                    <div class="flex justify-between text-xs"><span class="text-horizon-500">Delay retirement to 60</span><span class="text-spring-600 font-medium">+&pound;8,900/yr</span></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Next Steps -->
    <section class="py-16 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-8 text-center">Next steps</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="(step, idx) in nextSteps" :key="idx" class="rounded-lg p-5 bg-white border border-light-gray">
            <p class="text-lg font-bold text-horizon-500 mb-2">{{ step.label }}</p>
            <p class="text-sm text-neutral-500">{{ step.text }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- What Fynla Does Differently — horizon blue bg -->
    <section class="py-16 bg-gradient-to-r from-horizon-600 to-horizon-700">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-2 text-center">What Fynla does differently</h2>
        <p class="text-sm text-white/60 mb-8 text-center">These aren't features on a list. They're moments where Fynla makes a real difference.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="story in stories" :key="story.title" class="bg-horizon-500 rounded-card p-5">
            <div class="flex items-start gap-3">
              <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" :class="story.iconBg">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="story.icon" /></svg>
              </div>
              <div>
                <p class="text-lg font-bold text-white mb-1">{{ story.title }}</p>
                <p class="text-xs text-white/60 leading-relaxed">{{ story.text }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Stuck? Ask Fyn — light pink bg, homepage layout -->
    <section class="py-16 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:gap-10">
          <div class="flex-1">
            <h2 class="text-3xl lg:text-4xl font-bold text-horizon-500 mb-2">Stuck? Ask Fyn.</h2>
            <p class="text-sm text-neutral-500 max-w-xl mb-6">Fyn is your AI financial assistant, built into Fynla. Ask any question in plain English.<br/>No jargon, no judgement.</p>
            <div class="flex flex-col sm:flex-row gap-3 max-w-lg">
              <input type="text" placeholder="Enter your text here" class="input-field flex-1 !py-3" readonly />
              <button type="button" class="px-12 py-3 text-lg bg-light-blue-500 text-white rounded-button font-medium hover:opacity-90 transition-colors whitespace-nowrap">Ask Fyn</button>
            </div>
          </div>
          <div class="flex-1 mt-8 lg:mt-0">
            <div class="space-y-3">
              <div v-for="q in fynQuestions" :key="q" class="bg-white rounded-lg px-4 py-3 text-sm text-horizon-500 border border-light-gray shadow-sm">"{{ q }}"</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- From demo to your own plan — horizon blue -->
    <section class="py-16 bg-gradient-to-r from-horizon-600 to-horizon-700">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white text-center mb-10">From demo to your own plan</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="(step, idx) in demoSteps" :key="idx" class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full bg-raspberry-500 flex items-center justify-center mb-4">
              <span class="text-white text-sm font-bold">{{ idx + 1 }}</span>
            </div>
            <h3 class="text-white font-semibold mb-2">{{ step.title }}</h3>
            <p class="text-sm text-horizon-300 leading-relaxed">{{ step.text }}</p>
          </div>
        </div>
        <div class="text-center mt-10">
          <a href="#" @click.prevent="$router.push({ query: { demo: 'true' } })" class="inline-flex px-6 py-2.5 bg-light-blue-500 text-white text-sm font-semibold rounded-lg hover:bg-light-blue-600 transition-colors">Explore demo</a>
        </div>
      </div>
    </section>

    <!-- 15 Minutes to Clarity -->
    <section class="py-16 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">15 minutes to clarity</h2>
        <p class="text-sm text-neutral-500 max-w-lg mx-auto mb-6">That's all it takes to see your complete financial picture. Start with the demo, or go straight to your free trial.</p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
          <a href="#" @click.prevent="$router.push({ query: { demo: 'true' } })" class="px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors">Try the free demo</a>
          <router-link to="/register" class="px-6 py-2.5 bg-light-blue-500 text-white text-sm font-semibold rounded-lg hover:bg-light-blue-600 transition-colors">Start your free trial</router-link>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'HowItWorksPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'How Fynla works — simple financial planning in 3 steps | Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'See how Fynla helps you centralise your finances, get personalised insights, and take action on your savings, investments, pensions, and estate planning.');
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
        { label: 'Explore', text: 'Try the interactive demo with sample personas and realistic UK financial data.' },
        { label: 'Personalise', text: 'Add your own pensions, property, savings, and insurance details.' },
        { label: 'Understand', text: 'See your complete net worth, retirement projection, and protection gaps.' },
        { label: 'Model', text: 'Run what-if scenarios to test different life decisions.' },
        { label: 'Plan', text: 'Follow personalised action plans to improve your financial position.' },
        { label: 'Review', text: 'Check in monthly or quarterly to keep your plan current.' },
      ],
      nextStepBgs: [
        'bg-light-pink-100',
        'bg-light-blue-100',
        'bg-violet-50',
        'bg-spring-50',
        'bg-savannah-100',
        'bg-light-pink-100',
      ],
      stories: [
        {
          title: 'Saved thousands on a mortgage',
          text: '"Fynla alerted me that my fixed-rate mortgage was ending in 6 months. I locked in a new rate early — before rates went up. That single alert saved me thousands over the next 5 years."',
          icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
          iconBg: 'bg-spring-500',
        },
        {
          title: 'Everything in one document',
          text: 'Your partner doesn\'t know the mortgage provider\'s name. Your children have no idea where the pensions are. Fynla\'s In Case of Emergency letter puts everything your family needs in one document — auto-populated from your plan.',
          icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
          iconBg: 'bg-raspberry-500',
        },
        {
          title: 'The cover you didn\'t know you were missing',
          text: 'Your workplace life insurance pays 2x your salary. Sounds decent — until you realise your mortgage alone is 6x your salary. Fynla calculates exactly what your family would need and shows you the gap.',
          icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
          iconBg: 'bg-violet-500',
        },
        {
          title: 'Six jobs, six forgotten pension pots',
          text: 'The average UK worker has 11 jobs in their lifetime. That\'s potentially 11 pension pots with 11 different providers. Fynla brings them all into one dashboard so you can finally see what you\'ve actually got.',
          icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
          iconBg: 'bg-light-blue-500',
        },
        {
          title: '54% of UK adults don\'t have a will',
          text: 'Without a will, the law decides who gets what — and unmarried partners get nothing. Fynla helps you understand what\'s at stake and guides you through getting your estate in order.',
          icon: 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
          iconBg: 'bg-savannah-500',
        },
        {
          title: 'Clarity in complexity',
          text: 'Pensions, ISAs, Inheritance Tax, allowances, reliefs — financial planning is overwhelming. Fynla simplifies confusing decisions into clear, actionable steps so you always know what to do next.',
          icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
          iconBg: 'bg-horizon-400',
        },
      ],
      demoSteps: [
        { title: 'Explore the demo', text: 'Try Fynla with pre-built personas and realistic financial data — no sign-up needed.' },
        { title: 'Sign up free', text: 'Create your account with a 7-day free trial. No credit card required.' },
        { title: 'Add your data', text: '15-20 minutes to enter your pensions, property, savings, and insurance.' },
        { title: 'See your dashboard', text: 'Your complete financial picture — net worth, projections, and gaps.' },
        { title: 'Model scenarios', text: 'Test decisions before you make them. What if you retire early? Overpay your mortgage?' },
        { title: 'Keep it current', text: 'Check in monthly or quarterly. Fynla updates projections as your life changes.' },
      ],
    };
  },
};
</script>
