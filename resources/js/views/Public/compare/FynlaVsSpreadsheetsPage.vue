<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Fynla vs<br/>
          <span class="text-raspberry-300">Spreadsheets</span>
        </h1>
        <p class="text-lg text-white/70">
          Spreadsheets are free and flexible. Here is what you gain by upgrading to a dedicated planning tool.
        </p>
      </div>
    </div>

    <!-- Content -->
    <section class="py-10 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- Overview -->
        <div class="bg-light-blue-100 rounded-xl p-8">
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">We Get It &mdash; Spreadsheets Are Great</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Many serious financial planners start with a spreadsheet. They are free, endlessly customisable, and you
            have full control. But as your finances grow more complex, spreadsheets start to show their limits &mdash;
            and their risks.
          </p>
        </div>

        <!-- Spreadsheet Strengths -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">Where spreadsheets excel</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Free:</strong> no subscription, no commitment.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Fully customisable:</strong> you can model anything, in any way you want.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>You own it:</strong> your data, your formulae, your format.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Familiar:</strong> most people already know how to use Excel or Google Sheets.</span>
            </li>
          </ul>
        </div>

        <!-- What you gain with Fynla -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">What you gain with Fynla</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>No formula errors:</strong> calculations are tested and validated. A single wrong cell reference in a spreadsheet can silently break everything.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Tax rules built in:</strong> income tax bands, National Insurance, ISA allowances, pension annual allowance, and Capital Gains Tax are always current.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Monte Carlo simulations:</strong> run thousands of investment scenarios to understand the range of possible outcomes. Not practical in a spreadsheet.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Inheritance Tax calculations:</strong> nil rate band, residence nil rate band, taper rules, spouse transfers, and the 2027 pension changes &mdash; all automated.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Protection gap analysis:</strong> calculate your life insurance, critical illness, and income protection needs based on your actual circumstances.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Pension allowance tracking:</strong> annual allowance, carry forward, tapered allowance, and Money Purchase Annual Allowance &mdash; tracked automatically.</span>
            </li>
          </ul>
        </div>

        <!-- The hidden cost of spreadsheets -->
        <div>
          <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-horizon-500 mb-4">The hidden cost of spreadsheets</h2>
          <div class="bg-white rounded-lg border border-light-gray p-5">
            <ul class="space-y-1.5 text-sm text-neutral-600">
              <li class="flex items-start gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
                <span><strong>Time:</strong> building and maintaining a comprehensive financial model takes hours every year.</span>
              </li>
              <li class="flex items-start gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
                <span><strong>Tax changes:</strong> you need to update rates and thresholds manually after each Budget.</span>
              </li>
              <li class="flex items-start gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
                <span><strong>Blind spots:</strong> spreadsheets only calculate what you think to include. Fynla highlights gaps you might miss.</span>
              </li>
              <li class="flex items-start gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
                <span><strong>Sharing:</strong> difficult to share securely or access from your phone.</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6 flex flex-wrap items-center gap-x-4 gap-y-1">
          <span class="text-sm font-bold text-horizon-500">Related</span>
          <router-link to="/compare/best-financial-planning-tools-uk" class="text-xs text-raspberry-500 hover:text-light-pink-400 transition-colors">Best Financial Planning Tools UK</router-link>
          <span class="text-neutral-300">|</span>
          <router-link to="/compare/fynla-vs-financial-planning-platform" class="text-xs text-raspberry-500 hover:text-light-pink-400 transition-colors">Fynla vs Financial Planning Platform</router-link>
          <span class="text-neutral-300">|</span>
          <router-link to="/pricing" class="text-xs text-raspberry-500 hover:text-light-pink-400 transition-colors">Fynla Pricing</router-link>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-10 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500 rounded-xl px-6 py-6">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-2">Ditch the spreadsheet headaches</h2>
          <p class="text-lg text-white/70 mb-4">Professional-grade financial planning &mdash; no formulas required.</p>
          <a href="#" @click.prevent="$router.push({ query: { demo: 'true' } })" class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors">
            Try the demo
          </a>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'FynlaVsSpreadsheetsPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Fynla vs Spreadsheets \u2014 Why Upgrade Your Financial Plan? | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Spreadsheets are free and flexible, but they can\'t run Monte Carlo simulations, calculate Inheritance Tax, or track pension allowances. Here\'s what you gain by switching to Fynla.');
    }
  },
};
</script>
