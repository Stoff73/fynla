<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Starting Out</h1>
        <p class="text-lg text-white/70 max-w-xl mx-auto">
          Your first steps in financial planning.
        </p>
      </div>
    </div>

    <!-- Content -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- Intro -->
        <div class="bg-white rounded-lg border border-light-gray p-5">
          <h2 class="text-lg font-bold text-horizon-500 mb-2">You Don't Need to Know Everything</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            You just need to get the basics right. This guide covers what matters most when you are starting out &mdash;
            no jargon, no complicated strategies, just practical steps you can take today.
          </p>
        </div>

        <!-- Step 1 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">1</span>
            <h2 class="text-lg font-bold text-horizon-500">Build an Emergency Fund</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed mb-2">
            Aim for <strong>3&ndash;6 months of essential expenses</strong> in an easy-access savings account.
            This is your financial foundation &mdash; it protects you from unexpected costs like car repairs, boiler
            breakdowns, or a gap between jobs.
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Everything else in this guide comes after your emergency fund. Without one, a single surprise expense
            can push you into debt.
          </p>
        </div>

        <!-- Step 2 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">2</span>
            <h2 class="text-lg font-bold text-horizon-500">Understand Your Workplace Pension</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed mb-2">
            Your employer is required to enrol you in a pension and contribute money on your behalf. At minimum,
            make sure you are enrolled and getting the <strong>full employer match</strong>.
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            This is genuinely free money. If your employer matches up to 5% and you only contribute 3%, you are
            leaving 2% of your salary on the table every month.
          </p>
        </div>

        <!-- Step 3 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">3</span>
            <h2 class="text-lg font-bold text-horizon-500">Open an ISA</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed mb-2">
            You can save up to <strong>&pound;20,000 per year</strong> in an ISA, completely tax-free. Which type depends on your goals:
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Cash ISA</strong> &mdash; for short-term goals or topping up your emergency fund.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Stocks and Shares ISA</strong> &mdash; for goals 5+ years away, where you can ride out volatility.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Lifetime ISA</strong> &mdash; if you are under 40 and saving for your first home (25% government bonus on up to &pound;4,000/year).</span>
            </li>
          </ul>
        </div>

        <!-- Step 4 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">4</span>
            <h2 class="text-lg font-bold text-horizon-500">Know Your Student Loan</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed mb-2">
            Repayments are automatic once you earn above the threshold. Do not panic about the balance &mdash;
            it is written off after 25&ndash;40 years depending on your plan type.
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            For most people, it works more like a graduate tax than a traditional loan. Focus on earning more,
            not repaying early &mdash; unless you are close to clearing the balance entirely.
          </p>
        </div>

        <!-- Step 5 -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-spring-500 text-white text-xs font-bold flex-shrink-0">5</span>
            <h2 class="text-lg font-bold text-horizon-500">Start Tracking Your Net Worth</h2>
          </div>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Even if it is small (or negative). Your net worth is simply what you own minus what you owe.
            Knowing your number is the first step to improving it. Track it monthly and watch it grow over time.
          </p>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/stage/starting-out" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Starting Out stage</router-link>
            <router-link to="/learn/what-is-an-isa" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">What is an ISA?</router-link>
            <router-link to="/calculators" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Calculators</router-link>
            <router-link to="/learn/glossary" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Glossary</router-link>
            <router-link to="/learn" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">All guides</router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-white">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h2 class="text-xl font-bold text-horizon-500 mb-2">Start tracking your finances in Fynla</h2>
        <p class="text-sm text-neutral-500 mb-6">See your net worth, ISA allowance, pensions, and emergency fund &mdash; all in one place.</p>
        <a href="/?demo=true" class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors">
          Try the demo
        </a>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'StartingOutGuidePage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Financial Planning for Beginners — UK Starting Out Guide | Fynla';
  },
};
</script>
