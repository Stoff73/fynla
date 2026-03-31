<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Protecting and Growing &mdash; Family Finances Guide</h1>
        <p class="text-lg text-white/70">
          A practical guide to life insurance, income protection, investment basics, and joint financial planning for UK families.
        </p>
      </div>
    </div>

    <GuideNav />

    <!-- Content -->
    <section class="py-8 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl border border-light-gray p-6 sm:p-8 space-y-8">

        <!-- This Guide is For You If... -->
        <div class="bg-eggshell-500 rounded-lg p-5">
          <h2 class="text-base font-bold text-horizon-500 mb-2">This Guide is For You If&hellip;</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You have a mortgage and/or dependants.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You&rsquo;re not sure if your life insurance is enough.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You want to start investing but aren&rsquo;t sure about risk.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You need to plan finances as a couple.</span>
            </li>
          </ul>
        </div>

        <!-- Protection Comes First -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Protection Comes First</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Before you grow your wealth, make sure your family is protected if something goes wrong.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Life insurance:</strong> enough to clear the mortgage and replace your income for the years your children are dependent.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Income protection:</strong> replaces your income if you can&rsquo;t work through illness or injury &mdash; the most underrated insurance.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Critical illness cover:</strong> pays a lump sum on diagnosis of a specified condition.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Employer death-in-service:</strong> check what you have &mdash; it&rsquo;s usually 2&ndash;4 times salary.</span>
            </li>
          </ul>
        </div>

        <!-- The Protection Gap -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Protection Gap</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Most families are underinsured. The gap between what you need and what you have is your protection gap.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>What does your family need per year?</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>For how many years?</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>What cover do you already have?</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>The difference is the gap you need to fill.</span>
            </li>
          </ul>
        </div>

        <!-- Getting Started with Investing -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Getting Started with Investing</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Investing is how you grow your wealth over the long term. You don&rsquo;t need to be an expert to start.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Time in the market beats timing the market.</strong></span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Start with a low-cost global index fund if unsure.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Understand your risk profile: how long until you need the money? Can you stomach a 30% drop?</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Use an ISA wrapper for tax-free growth.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Don&rsquo;t invest money you will need within 5 years.</span>
            </li>
          </ul>
        </div>

        <!-- Joint Financial Planning -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Joint Financial Planning</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            If you are managing money as a couple, you need the full household picture.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Combine net worth views &mdash; you need to see both sides.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Both partners should know where everything is &mdash; In Case of Emergency letters help.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Consider: who is more vulnerable if the other dies? Plan for that scenario.</span>
            </li>
          </ul>
        </div>

        <!-- Pension Housekeeping -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Pension Housekeeping</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            If you have changed jobs a few times, you likely have pension pots scattered around. Time for a tidy-up.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Consolidate old workplace pensions &mdash; but check for guaranteed benefits first.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Make sure both partners are building adequate retirement provision.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Consider the pension impact of career breaks &mdash; common for parents.</span>
            </li>
          </ul>
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
  name: 'ProtectingAndGrowingGuidePage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  mounted() {
    document.title = 'Family Financial Protection & Investment Guide UK | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Got a mortgage and dependants? A practical guide to life insurance, income protection, investment basics, and joint financial planning for UK families.');
    }
  },
};
</script>
