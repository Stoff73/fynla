<template>
  <PublicLayout>
    <div class="campaign-body">
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="campaign-inner relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4" v-html="campaign.heroTitle"></h1>
        <p class="text-lg text-white/70 max-w-2xl">{{ campaign.heroSubtitle }}</p>
      </div>
    </div>

    <!-- Meet Fyn -->
    <div class="bg-light-pink-100 pt-6 pb-8 lg:pt-[10px]" :class="fynDetailsOpen ? 'lg:pb-14' : 'lg:pb-0'">
      <div class="campaign-inner max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:gap-10">
          <div class="flex-1 lg:self-start lg:pt-10">
            <div class="flex items-end justify-between lg:hidden mb-1">
              <h2 class="text-6xl font-bold text-horizon-500 leading-none">Meet Fyn</h2>
              <img src="/images/Fyn/Design Character 001a.webp" alt="Fyn — your AI financial companion" loading="lazy" width="324" height="427" class="h-28 w-auto -mb-2" />
            </div>
            <h2 class="hidden lg:block text-8xl font-bold text-horizon-500 leading-none mb-1 lg:-mt-[24px]">Meet Fyn</h2>
            <p class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-semibold text-neutral-500 mb-2">Your financial companion for life</p>
            <p class="text-sm text-neutral-500 mb-4 max-w-2xl leading-relaxed">
              Need help? Fyn is the face of our Fynla brain and will help you with your finance goals by giving you financial clarity. Fyn will help with everything from planning to saving and investments, through to your net worth and real estate. Tell Fyn what you'd like to do and he'll walk you through your dashboard step-by-step.
            </p>
            <div class="max-w-2xl mb-5">
              <button type="button" class="flex items-center gap-2 text-sm font-semibold text-horizon-500 hover:text-raspberry-500 transition-colors" @click="fynDetailsOpen = !fynDetailsOpen">
                <span>What can Fyn help you with?</span>
                <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': fynDetailsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
              </button>
              <transition name="expand">
                <ul v-if="fynDetailsOpen" class="mt-3 space-y-2 text-sm text-neutral-500">
                  <li class="flex items-start gap-2"><svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>Your financial companion to answer any questions you have</li>
                  <li class="flex items-start gap-2"><svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>Help understand and complete your dashboard</li>
                  <li class="flex items-start gap-2"><svg class="w-4 h-4 text-spring-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>Support you to achieve your financial goals</li>
                </ul>
              </transition>
            </div>
            <router-link to="/register?from=fyn" class="inline-block px-12 py-3 text-lg bg-spring-500 text-white rounded-button font-medium hover:bg-spring-600 transition-colors whitespace-nowrap">Quick start with Fyn</router-link>
          </div>
          <div class="hidden lg:flex lg:w-auto lg:flex-shrink-0 items-center gap-3" :class="fynDetailsOpen ? 'lg:self-end' : ''">
            <img src="/images/Fyn/Design Character 001a.webp" alt="Fyn — your AI financial companion" loading="lazy" width="324" height="427" :class="fynDetailsOpen ? 'h-[427px] w-auto lg:-mb-[6em]' : 'h-[427px] w-auto lg:-mb-[3em]'" />
            <div class="flex flex-col items-start -mt-4">
              <span class="hand-drawn text-base text-raspberry-500 -rotate-3 whitespace-nowrap mb-1 ml-1">Ask me anything!</span>
              <svg width="80" height="40" viewBox="0 0 80 40" fill="none" class="text-raspberry-500">
                <path d="M4 6 C 15 20, 35 32, 55 28 C 60 27, 64 25, 66 23" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none" />
                <polygon points="76,23 66,17 66,29" fill="currentColor" />
              </svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Did You Know? -->
    <div class="bg-horizon-500 py-14">
      <div class="campaign-inner max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white text-center mb-10">Did you know?</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div v-for="fact in campaign.facts" :key="fact.stat" class="bg-white/10 border border-white/20 rounded-2xl p-6">
            <p class="text-4xl font-black text-white mb-3">{{ fact.stat }}</p>
            <p class="text-sm text-white/80 leading-relaxed">{{ fact.text }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Why Fynla -->
    <div class="bg-eggshell-500 py-12">
      <div class="campaign-inner max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 text-center mb-10">Why Fynla?</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
          <div class="flex flex-col items-center">
            <div class="w-12 h-12 bg-spring-100 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-spring-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </div>
            <h3 class="text-lg font-semibold text-horizon-500 mb-1">7-Day Free Trial</h3>
            <p class="text-sm text-neutral-500">Try any plan risk-free. No credit card required to start.</p>
          </div>
          <div class="flex flex-col items-center">
            <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center mb-3">
              <svg class="w-6 h-6 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            </div>
            <h3 class="text-lg font-semibold text-horizon-500 mb-1">UK Data Security</h3>
            <p class="text-sm text-neutral-500">AES-256 encryption. Data stored in UK data centres. GDPR compliant.</p>
          </div>
          <div class="flex flex-col items-center">
            <div class="w-12 h-12 bg-light-blue-100 rounded-xl flex items-center justify-center mb-3 border border-light-gray">
              <svg class="w-6 h-6 text-horizon-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
            </div>
            <h3 class="text-lg font-semibold text-horizon-500 mb-1">Cancel Anytime</h3>
            <p class="text-sm text-neutral-500">No lock-in contracts. Downgrade or cancel whenever you like.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Reviews -->
    <ReviewCarousel :per-page="2" inner-class="campaign-inner" />

    <!-- Video -->
    <div class="bg-eggshell-500 py-10">
      <div class="campaign-inner max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 text-center mb-8">Your Fynla dashboard</h2>
        <div class="relative rounded-2xl overflow-hidden shadow-lg cursor-pointer group" @click="toggleVideo">
          <video
            ref="productVideo"
            src="/images/Homepage-Fynla-ProductVideo.mp4"
            playsinline
            class="w-full h-auto block"
          >
            Your browser does not support the video tag.
          </video>
          <div v-if="!videoPlaying" class="absolute inset-0 bg-horizon-500/30 flex items-center justify-center transition-opacity group-hover:bg-horizon-500/20">
            <div class="w-20 h-20 rounded-full bg-white/90 flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-10 h-10 text-raspberry-500 ml-1" fill="currentColor" viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z" />
              </svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Features -->
    <div class="bg-gradient-to-r from-horizon-600 to-horizon-700 py-10 lg:py-12">
      <div class="campaign-inner max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white text-center mb-4">How Fyn can help you</h2>
        <p class="text-center text-white/70 text-sm max-w-2xl mx-auto mb-10 leading-relaxed">We leverage tools designed for individuals and families to plan savings, investments, retirement and estate with confidence and within local regulations.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div v-for="f in features" :key="f.title" class="bg-horizon-500 rounded-card p-6">
            <div class="w-10 h-10 rounded-full flex items-center justify-center mb-4" :class="f.iconBg">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="f.icon" /></svg>
            </div>
            <h3 class="text-white mb-2">{{ f.title }}</h3>
            <p class="text-sm text-horizon-300 leading-relaxed mb-4">{{ f.desc }}</p>
            <div class="flex flex-wrap gap-2">
              <span v-for="tag in f.tags" :key="tag" class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/70">{{ tag }}</span>
            </div>
          </div>
        </div>
        <div class="text-center mt-10">
          <router-link to="/features" class="text-white font-medium hover:text-white/70 transition-colors">View detailed list of features &gt;</router-link>
        </div>
      </div>
    </div>

    <!-- Latest insights -->
    <div class="bg-light-pink-100 py-12">
      <div class="campaign-inner max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 text-center mb-6">Latest {{ campaign.insightLabel }} insights</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <router-link
            v-for="article in filteredInsights"
            :key="article.slug"
            :to="article.slug"
            class="group bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all flex flex-col no-underline"
          >
            <div class="aspect-[16/9] overflow-hidden bg-horizon-100">
              <img v-if="getImage(article.image)" :src="getImage(article.image)" :alt="article.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
            </div>
            <div class="p-3.5 flex-1 flex flex-col">
              <p class="text-[0.65rem] text-neutral-400 mb-1 uppercase tracking-wide">{{ article.date }}</p>
              <h3 class="text-sm font-bold text-horizon-500 group-hover:text-raspberry-500 transition-colors mb-1.5 leading-snug">{{ article.title }}</h3>
              <p class="text-xs text-neutral-500 leading-relaxed flex-1 line-clamp-2">{{ article.summary }}</p>
            </div>
          </router-link>
        </div>
        <div class="text-center mt-6">
          <router-link to="/insights" class="text-sm font-semibold text-horizon-500 hover:text-raspberry-500">See all insights &rarr;</router-link>
        </div>
      </div>
    </div>

    <!-- Journey -->
    <div class="bg-eggshell-500 pt-10 lg:pt-12 pb-14">
      <div class="campaign-inner max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 text-center mb-12">Your personal journey</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
          <router-link v-for="s in stages" :key="s.slug" :to="'/stage/' + s.slug" class="lg:aspect-square bg-gradient-to-br from-horizon-600 to-horizon-700 rounded-card border border-white/10 shadow-sm p-5 flex flex-col items-start justify-center cursor-pointer hover:border-white/30 hover:-translate-y-0.5 transition-all duration-200 no-underline">
            <p class="text-2xl font-bold text-white mb-1 leading-tight"><span class="text-raspberry-400">{{ s.accent }}</span><br/>{{ s.rest }}</p>
            <p class="text-xs text-white/70 leading-snug">{{ s.desc }}</p>
          </router-link>
        </div>
      </div>
    </div>

    <!-- Final CTA -->
    <div class="bg-light-pink-100 py-14">
      <div class="campaign-inner max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">Ready to take control?</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">Start your free 7-day trial today. No credit card required.</p>
        <router-link to="/register?from=fyn" class="inline-block px-8 py-3 bg-spring-500 text-white text-lg font-semibold rounded-lg hover:bg-spring-600 transition-colors">Get started for free</router-link>
      </div>
    </div>
    </div>
    <StaticFynChat />
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import ReviewCarousel from '@/components/Public/ReviewCarousel.vue';
import StaticFynChat from '@/components/Public/StaticFynChat.vue';

const insightImages = import.meta.glob('@/assets/insights/*.{jpg,png,webp}', { eager: true, import: 'default' });

const CAMPAIGNS = {
  '/savetax': {
    heroTitle: 'Save more on <span class="text-raspberry-300">tax</span>',
    heroSubtitle: 'Understand your tax position, maximise your allowances, and keep more of what you earn with Fynla\u2019s complete financial planning platform.',
    metaTitle: 'Save Tax \u2014 Maximise Your Allowances | Fynla',
    metaDesc: 'Maximise ISA allowances, pension tax relief, and Marriage Allowance. See your full tax position with Fynla.',
    facts: [
      { stat: '60%', text: 'of UK adults don\u2019t use their full \u00A320,000 annual ISA allowance \u2014 missing out on tax-free growth every single year.' },
      { stat: '20%', text: 'extra tax relief is available to higher-rate taxpayers on pension contributions through self-assessment \u2014 but many forget to claim it.' },
      { stat: '2.4m', text: 'eligible couples don\u2019t claim the Marriage Allowance \u2014 worth \u00A3252 per year, and you can backdate it up to 4 years.' },
    ],
    insightTags: ['Savings & ISA', 'Pensions', 'Tax changes'],
    insightLabel: 'tax',
  },
  '/biggerpension': {
    heroTitle: 'Grow a bigger <span class="text-raspberry-300">pension</span>',
    heroSubtitle: 'Track your pensions, maximise contributions, and project your retirement income with Fynla\u2019s complete financial planning platform.',
    metaTitle: 'Bigger Pension \u2014 Maximise Your Retirement Pot | Fynla',
    metaDesc: 'Track workplace and personal pensions, use carry forward, and project your retirement income with Fynla.',
    facts: [
      { stat: '10m+', text: 'UK workers are auto-enrolled into workplace pensions but most never increase their contributions beyond the minimum 5% \u2014 missing significant employer match potential.' },
      { stat: '\u00A3100k+', text: 'can be contributed in a single tax year by carrying forward up to 3 years of unused \u00A360,000 annual allowance \u2014 a powerful catch-up strategy most people overlook.' },
      { stat: '35 years', text: 'of National Insurance contributions are needed for the full State Pension of \u00A311,973 per year. Check your record \u2014 you may be able to buy missing years at a significant return.' },
    ],
    insightTags: ['Pensions'],
    insightLabel: 'pension',
  },
  '/paymortgage': {
    heroTitle: 'Pay off your <span class="text-raspberry-300">mortgage</span> smarter',
    heroSubtitle: 'Track your mortgage, get remortgage alerts, and see how overpayments could save you thousands with Fynla\u2019s complete financial planning platform.',
    metaTitle: 'Pay Your Mortgage Smarter \u2014 Overpayment & Rate Alerts | Fynla',
    metaDesc: 'Track your mortgage, get rate expiry alerts, and model overpayment savings with Fynla.',
    facts: [
      { stat: '\u00A317k', text: 'in interest could be saved by overpaying your mortgage by just \u00A3100 a month on a \u00A3250,000 loan \u2014 and you\u2019d cut 4 years off your term.' },
      { stat: '6 months', text: 'before your fix ends is when most lenders let you lock in a new rate. Missing this window means defaulting to the SVR \u2014 often 3\u20134% higher.' },
      { stat: '1 in 3', text: 'UK homeowners regularly check how much equity they have in their property \u2014 a key input for remortgage decisions and understanding your net worth.' },
    ],
    insightTags: ['Savings & ISA', 'Pensions'],
    insightLabel: 'mortgage',
  },
  '/managedebt': {
    heroTitle: 'Take control of your <span class="text-raspberry-300">debt</span>',
    heroSubtitle: 'See all your debts in one place, understand the true cost, and build a clear repayment strategy with Fynla\u2019s complete financial planning platform.',
    metaTitle: 'Manage Your Debt \u2014 See the Full Picture | Fynla',
    metaDesc: 'Consolidate your debts, understand the true cost, and build a repayment strategy with Fynla.',
    facts: [
      { stat: '\u00A34,100', text: 'is the average unsecured debt per UK adult \u2014 not including mortgages. Most people don\u2019t have a clear picture of what they owe across all accounts.' },
      { stat: '27 years', text: 'is how long it takes to clear a \u00A33,000 credit card at 22% APR paying only the minimum \u2014 costing over \u00A34,000 in interest alone.' },
      { stat: '60%', text: 'of balance transfer card users don\u2019t clear the balance before the promotional 0% period ends \u2014 reverting to rates as high as 25%.' },
    ],
    insightTags: ['Savings & ISA'],
    insightLabel: 'debt',
  },
  '/wealth': {
    heroTitle: 'Build your <span class="text-raspberry-300">wealth</span>',
    heroSubtitle: 'See your complete net worth, track investments, and grow your financial future with Fynla\u2019s complete financial planning platform.',
    metaTitle: 'Build Your Wealth \u2014 Track Net Worth & Investments | Fynla',
    metaDesc: 'Track your net worth, investments, and estate. Plan for long-term wealth with Fynla.',
    facts: [
      { stat: '\u00A3405k', text: 'is what \u00A3500 a month grows to over 25 years at 7% average return \u2014 but only \u00A3150,000 is your money. Compound growth does the rest.' },
      { stat: '18%', text: 'of UK adults know their net worth. Without a clear picture of assets minus liabilities, you can\u2019t set realistic goals or measure progress.' },
      { stat: '40%', text: 'less portfolio volatility is possible by diversifying across equities, bonds, property, and cash \u2014 yet the average UK investor holds just 2 asset classes.' },
    ],
    insightTags: ['Estate planning', 'Savings & ISA'],
    insightLabel: 'wealth',
  },
};

const ALL_INSIGHTS = [
  { slug: '/insights/how-much-to-retire-uk', title: 'How Much Do I Need to Retire in the UK?', date: '14 April 2026', summary: 'Calculate your UK retirement number using 2026 PLSA living standards.', tags: ['Pensions'], image: 'how-much-to-retire-uk.jpg' },
  { slug: '/insights/stocks-shares-isa-uk', title: 'What Is a Stocks and Shares ISA?', date: '13 April 2026', summary: 'How they work, what you can invest in, tax benefits, risks, fees, and how to choose a platform.', tags: ['Savings & ISA'], image: 'stocks-shares-isa.jpg' },
  { slug: '/insights/isa-guide-uk', title: 'The Ultimate Guide to ISAs in the UK', date: '8 April 2026', summary: 'Types, allowances, rules, and choosing the right ISA for your goals.', tags: ['Savings & ISA'], image: 'isa-guide-uk.jpg' },
  { slug: '/insights/retirement-planning-uk', title: 'The Complete Guide to Retirement Planning in the UK', date: '8 April 2026', summary: 'Pensions, State Pension, ISAs, drawdown strategies, tax and what you will need.', tags: ['Pensions'], image: 'retirement-planning-uk.jpg' },
  { slug: '/insights/inheritance-tax-uk', title: 'Inheritance Tax Explained: Thresholds & Rules', date: 'April 2026', summary: 'IHT thresholds, nil rate bands and strategies to reduce your estate\u2019s tax bill.', tags: ['Estate planning'], image: 'inheritance-tax-uk.jpg' },
  { slug: '/insights/pension-contribution-limits-uk', title: 'Pension Contribution Limits UK 2026/27', date: 'April 2026', summary: 'Annual allowance, tax relief rates and carry forward rules for UK savers.', tags: ['Pensions'], image: 'pension-contribution-limits.jpg' },
  { slug: '/insights/pension-iht-changes-2027', title: 'Pension Inheritance Tax Changes: April 2027', date: 'March 2026', summary: 'Unused pension pots will be included in your estate for IHT from April 2027.', tags: ['Pensions', 'Estate planning'], image: 'pension-iht-changes.jpg' },
  { slug: '/insights/isa-allowance-2025-26', title: 'ISA Allowance 2026/27: Make the Most of \u00A320,000', date: 'April 2025', summary: 'Types, deadlines, and strategies for maximising your tax-free savings.', tags: ['Savings & ISA'], image: 'isa-allowance.jpg' },
];

export default {
  name: 'CampaignPage',
  components: { PublicLayout, ReviewCarousel, StaticFynChat },

  data() {
    return {
      fynDetailsOpen: false,
      videoPlaying: false,
      features: [
        { title: 'Protection', desc: 'Analyse life insurance, critical illness, and income protection coverage gaps to ensure your family is fully protected.', iconBg: 'bg-raspberry-500', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', tags: ['Life Cover', 'Income Protection'] },
        { title: 'Savings', desc: 'Track emergency funds, ISA allowances, and savings goals across all your accounts with smart benchmarking.', iconBg: 'bg-spring-500', icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', tags: ['Emergency Fund', 'ISA'] },
        { title: 'Investment', desc: 'Portfolio analysis, risk profiling, and Monte Carlo projections to optimise your investment strategy.', iconBg: 'bg-violet-500', icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6', tags: ['Portfolio', 'Risk Profile'] },
        { title: 'Retirement', desc: 'Defined Contribution, Defined Benefit, and State Pension tracking with retirement income projections.', iconBg: 'bg-light-blue-500', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', tags: ['Pension', 'State Pension'] },
        { title: 'Estate', desc: 'Inheritance Tax calculations, gifting strategies, and estate value projections for effective planning.', iconBg: 'bg-savannah-500', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', tags: ['Inheritance Tax', 'Trusts'] },
        { title: 'Net Worth', desc: 'Complete balance sheet with properties, assets, and liabilities tracking for a clear financial picture.', iconBg: 'bg-horizon-400', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', tags: ['Assets', 'Liabilities'] },
      ],
      stages: [
        { slug: 'starting-out', accent: 'Starting', rest: 'out', desc: 'Build strong financial habits for your future.' },
        { slug: 'building-foundations', accent: 'Building', rest: 'foundations', desc: 'Save, invest, and grow your wealth with confidence.' },
        { slug: 'protecting-and-growing', accent: 'Protecting', rest: 'and growing', desc: 'Ensure your family and assets are fully covered.' },
        { slug: 'planning-your-future', accent: 'Planning', rest: 'your future', desc: 'Retirement projections, pension tracking, and estate planning.' },
        { slug: 'enjoying-your-wealth', accent: 'Enjoying', rest: 'your wealth', desc: 'Make the most of your financial success.' },
      ],
    };
  },

  computed: {
    campaign() {
      return CAMPAIGNS[this.$route.path] || CAMPAIGNS['/savetax'];
    },
    filteredInsights() {
      const tags = this.campaign.insightTags;
      return ALL_INSIGHTS
        .filter(a => a.tags.some(t => tags.includes(t)))
        .slice(0, 3);
    },
  },

  methods: {
    toggleVideo() {
      const video = this.$refs.productVideo;
      if (!video) return;
      if (video.paused) {
        video.play();
        this.videoPlaying = true;
      } else {
        video.pause();
        this.videoPlaying = false;
      }
    },

    getImage(filename) {
      if (!filename) return null;
      const key = Object.keys(insightImages).find(k => k.endsWith('/' + filename));
      return key ? insightImages[key] : null;
    },
  },

  mounted() {
    document.title = this.campaign.metaTitle;
    const meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute('content', this.campaign.metaDesc);
  },

  watch: {
    '$route.path'() {
      document.title = this.campaign.metaTitle;
      const meta = document.querySelector('meta[name="description"]');
      if (meta) meta.setAttribute('content', this.campaign.metaDesc);
    },
  },
};
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Caveat:wght@500;600&display=swap');
</style>

<style scoped>
.hand-drawn { font-family: 'Caveat', cursive; }
/* Push content right of chat panel on desktop */
.campaign-body {
  margin-right: 0;
}
@media (min-width: 1024px) {
  .campaign-body {
    margin-right: 356px;
  }
  /* Align inner containers with nav logo: same left offset as max-w-7xl mx-auto on full viewport */
  .campaign-body :deep(.campaign-inner) {
    max-width: none;
    margin-left: max(1rem, calc((100vw - 80rem) / 2));
    margin-right: 0;
    padding-left: 1rem;
    padding-right: 2rem;
  }
}
</style>
