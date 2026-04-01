<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Lifetime ISA vs ISA</h1>
        <p class="text-lg text-white/70">
          Compare the 25% government bonus against withdrawal penalties, and find out which is right for your goals.
        </p>
      </div>
    </div>

    <GuideNav />

    <!-- Content -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl border border-light-gray p-6 sm:p-8 space-y-8">

        <!-- The Short Answer -->
        <div class="bg-eggshell-500 rounded-lg p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Short Answer</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            If you're saving for your first home (under &pound;450,000) or for retirement, a Lifetime ISA gives you a
            <strong>25% government bonus</strong>. But if you might need the money for anything else, a regular ISA is
            more flexible.
          </p>
        </div>

        <!-- Lifetime ISA: The Basics -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Lifetime ISA: The Basics</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Must be aged <strong>18&ndash;39</strong> to open.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Contribute up to <strong>&pound;4,000/year</strong> (counts towards your &pound;20,000 ISA allowance).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Government adds <strong>25% bonus</strong> (up to &pound;1,000/year).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Penalty-free withdrawal only for: <strong>first home purchase</strong> (up to &pound;450,000) or <strong>after age 60</strong>.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Early withdrawal penalty: <strong>25%</strong> of the amount withdrawn (you lose more than the bonus).</span>
            </li>
          </ul>
        </div>

        <!-- When the Lifetime ISA Wins -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">When the Lifetime ISA Wins</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You're saving for a <strong>first home under &pound;450,000</strong>.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You want extra retirement savings with a <strong>guaranteed 25% return</strong>.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You're confident you <strong>won't need the money before 60</strong> (if not buying a home).</span>
            </li>
          </ul>
        </div>

        <!-- When a Regular ISA Wins -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">When a Regular ISA Wins</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You might need the money <strong>before 60</strong> or before buying a home.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You're buying a property <strong>over &pound;450,000</strong>.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You're <strong>over 39</strong> (can't open a new Lifetime ISA).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You want <strong>full flexibility</strong> with no penalties.</span>
            </li>
          </ul>
        </div>

        <!-- Can I Have Both? -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Can I Have Both?</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Yes. Your &pound;20,000 ISA allowance can be split. For example: &pound;4,000 in a Lifetime ISA +
            &pound;16,000 in a Stocks and Shares ISA.
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            Many people use a Lifetime ISA for their house deposit and a regular ISA for other savings.
          </p>
        </div>

        <!-- The Penalty Trap -->
        <div class="bg-eggshell-500 rounded-lg p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Penalty Trap</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            The 25% withdrawal penalty on a Lifetime ISA is calculated on the <strong>total amount</strong> (including bonus).
            This means you actually lose <strong>6.25% of your own money</strong> if you withdraw early.
          </p>
          <div class="bg-eggshell-500 rounded-lg p-4 text-xs text-neutral-600 leading-relaxed">
            <p class="font-semibold text-horizon-500 mb-1">Example</p>
            <p>Put in &pound;4,000 &rarr; get &pound;1,000 bonus = &pound;5,000 total.</p>
            <p>Withdraw early: 25% penalty = &pound;1,250 taken.</p>
            <p class="font-semibold mt-1">You get back &pound;3,750 &mdash; less than you put in.</p>
          </div>
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
  name: 'ShouldIUseALisaOrIsaPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  mounted() {
    document.title = 'LISA vs ISA — Which Should You Choose? | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Lifetime ISA or regular ISA? Compare the 25% government bonus against the withdrawal penalties, and find out which is right for your goals.');
    }
  },
};
</script>
