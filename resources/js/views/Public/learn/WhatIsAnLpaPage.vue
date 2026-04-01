<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">What is a Lasting Power of Attorney?</h1>
        <p class="text-lg text-white/70">
          How an LPA protects you and your family &mdash; explained without the legal jargon.
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
            A Lasting Power of Attorney (LPA) is a legal document that lets you appoint someone you trust to make
            decisions on your behalf if you lose mental capacity. There are two types: one for finances, one for
            health and welfare.
          </p>
        </div>

        <!-- The Two Types -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-3">The Two Types</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Property and Financial Affairs</p>
              <p class="text-xs text-neutral-500 leading-relaxed mb-2">
                Covers bank accounts, bills, property, investments, and tax.
              </p>
              <p class="text-xs text-neutral-500 leading-relaxed">
                Can be used while you still have capacity &mdash; useful if you are travelling or unwell.
              </p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Health and Welfare</p>
              <p class="text-xs text-neutral-500 leading-relaxed mb-2">
                Covers medical treatment, care, daily routine, and life-sustaining treatment.
              </p>
              <p class="text-xs text-neutral-500 leading-relaxed">
                Can only be used when you lack capacity.
              </p>
            </div>
          </div>
        </div>

        <!-- Why You Need One -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Why You Need One</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Without an LPA, your family would need to apply to the Court of Protection to manage your finances &mdash;
            a slow, expensive process that takes months, not days.
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-xs font-semibold text-spring-600 mb-1">Lasting Power of Attorney</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>Around &pound;82 to register</li>
                <li>Takes 8&ndash;10 weeks to process</li>
                <li>You choose who acts for you</li>
              </ul>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-xs font-semibold text-raspberry-500 mb-1">Court of Protection</p>
              <ul class="space-y-1 text-xs text-neutral-500">
                <li>&pound;400+ in fees</li>
                <li>Much longer to arrange</li>
                <li>The court decides who manages your affairs</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Who Should Be Your Attorney? -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Who Should Be Your Attorney?</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Someone you trust completely.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>They must be over 18 and have mental capacity.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You can appoint more than one person (jointly or jointly and severally).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>Consider: spouse, adult child, trusted friend, or a professional such as a solicitor.</span>
            </li>
          </ul>
        </div>

        <!-- Common Misconceptions -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Common Misconceptions</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>&ldquo;I&rsquo;m too young.&rdquo;</strong> Accidents and illness do not check your age.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>&ldquo;My spouse can do everything anyway.&rdquo;</strong> They cannot access your sole accounts or sell jointly-owned property without an LPA.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>&ldquo;A will covers this.&rdquo;</strong> A will only takes effect after death, not during incapacity.</span>
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
  name: 'WhatIsAnLpaPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  mounted() {
    document.title = 'What is a Lasting Power of Attorney? — LPA Explained | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'A Lasting Power of Attorney lets someone you trust make decisions for you if you can\'t. Learn about the two types, how to set one up, and why it matters.');
    }
  },
};
</script>
