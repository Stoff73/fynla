<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Should I Consolidate My Pensions?</h1>
        <p class="text-lg text-white/70 max-w-xl mx-auto">
          When combining your pension pots makes sense &mdash; and when it does not.
        </p>
      </div>
    </div>

    <!-- Content -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

        <!-- The Quick Answer -->
        <div class="bg-white rounded-lg border border-light-gray p-5">
          <h2 class="text-lg font-bold text-horizon-500 mb-2">The Quick Answer</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Consolidating can simplify your life and reduce fees, but some pensions have valuable benefits you would
            lose by transferring. Check before you move anything.
          </p>
        </div>

        <!-- Reasons to Consolidate -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">Reasons to Consolidate</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Easier to manage.</strong> One pot is simpler to track than five scattered across old employers.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Potentially lower fees.</strong> Older workplace pensions often charge 1%+ per year. A modern Self-Invested Personal Pension can be much cheaper.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Clearer picture.</strong> Seeing your total retirement savings in one place makes planning much simpler.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Better fund choices.</strong> Older schemes may offer limited, outdated investment options.</span>
            </li>
          </ul>
        </div>

        <!-- Reasons to Be Careful -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">Reasons to Be Careful</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Guaranteed annuity rates.</strong> Some older pensions offer annuity rates far above what is available on the open market. Transferring out means losing them permanently.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Protected tax-free cash.</strong> Some pensions offer more than the standard 25% tax-free lump sum. Transferring can forfeit this.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Defined Benefit pensions.</strong> These guarantee a specific income in retirement. Never transfer out without professional financial advice.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-raspberry-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Exit penalties.</strong> Some schemes charge a fee to transfer out, especially if you are within an initial period.</span>
            </li>
          </ul>
        </div>

        <!-- How to Compare -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">How to Compare</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Before consolidating, compare each pension on these criteria:
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Annual management charges (the ongoing percentage fee)</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Fund choices and investment flexibility</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Platform features (online access, drawdown options)</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Exit fees and any guaranteed benefits</span>
            </li>
          </ul>
        </div>

        <!-- The Fynla Approach -->
        <div>
          <h2 class="text-lg font-bold text-horizon-500 mb-2">The Fynla Approach</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Add all your pensions to Fynla to see the full picture first. Compare fees and projected outcomes side
            by side before deciding whether to consolidate. Sometimes the best decision is to leave things where they are.
          </p>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/features/pension-tracker" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Pension tracker</router-link>
            <router-link to="/learn/what-is-drawdown" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">What is drawdown?</router-link>
            <router-link to="/calculators" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Pension growth calculator</router-link>
            <router-link to="/stage/planning-your-future" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Planning Your Future stage</router-link>
            <router-link to="/learn" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">All guides</router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-white">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h2 class="text-xl font-bold text-horizon-500 mb-2">See all your pensions in one place</h2>
        <p class="text-sm text-neutral-500 mb-6">Add every pension &mdash; workplace, personal, and Self-Invested Personal Pension &mdash; and compare them side by side.</p>
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
  name: 'ShouldIConsolidatePensionsPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Should I Consolidate My Pensions? — UK Guide | Fynla';
  },
};
</script>
