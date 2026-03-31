<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Should I Consolidate My Pensions?</h1>
        <p class="text-lg text-white/70">
          When combining your pension pots makes sense &mdash; and when it does not.
        </p>
      </div>
    </div>

    <GuideNav />

    <!-- Content -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl border border-light-gray p-6 sm:p-8 space-y-8">

        <!-- The Quick Answer -->
        <div class="bg-eggshell-500 rounded-lg p-5">
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

        </div>
      </div>
    </section>

    <GuideArticleFooter />
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import GuideNav from '@/components/Shared/GuideNav.vue';
import GuideArticleFooter from '@/components/Shared/GuideArticleFooter.vue';

export default {
  name: 'ShouldIConsolidatePensionsPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  mounted() {
    document.title = 'Should I Consolidate My Pensions? — UK Guide | Fynla';
    let meta = document.querySelector('meta[name="description"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'description');
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', 'Consolidating multiple pensions into one can simplify management and reduce fees. Learn the pros, cons, and when consolidation makes sense for your retirement planning.');
  },
};
</script>
