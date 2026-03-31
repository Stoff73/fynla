<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">When Should I Make a Will?</h1>
        <p class="text-lg text-white/70">
          A practical UK guide to wills, intestacy, and protecting your family.
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
            If you own anything, have children, or have people who depend on you, you need a will. Without one, the law
            decides who gets what &mdash; and it might not match your wishes.
          </p>
        </div>

        <!-- What Happens Without a Will (Intestacy) -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">What Happens Without a Will (Intestacy)</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            In England and Wales, intestacy rules apply. Your spouse gets the first &pound;322,000 plus personal
            possessions, then half the remainder. The rest goes to your children.
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            <strong>Unmarried partners get nothing.</strong> This catches many cohabiting couples by surprise.
          </p>
        </div>

        <!-- When You Definitely Need One -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">When You Definitely Need One</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You have <strong>children</strong> (to appoint guardians).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You're <strong>not married but live with a partner</strong>.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You <strong>own property</strong>.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You have <strong>specific wishes</strong> about who gets what.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You want to <strong>minimise Inheritance Tax</strong>.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-spring-500 mt-1.5 flex-shrink-0"></span>
              <span>You have a <strong>business</strong>.</span>
            </li>
          </ul>
        </div>

        <!-- What Goes in a Will -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">What Goes in a Will</h2>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Who gets what</strong> (beneficiaries).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Who looks after your children</strong> (guardians).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Who manages the process</strong> (executors).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Funeral wishes</strong> (optional but helpful).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Trusts for children under 18.</strong></span>
            </li>
          </ul>
        </div>

        <!-- How to Make One -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">How to Make One</h2>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">DIY online</p>
              <p class="text-xs text-neutral-500 leading-relaxed">&pound;30&ndash;100. Fine for simple situations with no property or dependants.</p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Solicitor</p>
              <p class="text-xs text-neutral-500 leading-relaxed">&pound;200&ndash;500. Recommended if you have property, children, or complex assets.</p>
            </div>
            <div class="bg-eggshell-500 rounded-lg p-5">
              <p class="text-sm font-semibold text-horizon-500 mb-1">Will-writing service</p>
              <p class="text-xs text-neutral-500 leading-relaxed">&pound;100&ndash;300. A mid-range option between DIY and a solicitor.</p>
            </div>
          </div>
          <p class="text-xs text-neutral-500 mt-3">
            Review your will every 3&ndash;5 years or after major life events such as marriage, divorce, or the birth of a child.
          </p>
        </div>

        <!-- Will + LPA + ICE Letter -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Will + Lasting Power of Attorney + ICE Letter</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            A will covers what happens <strong>after you die</strong>. A Lasting Power of Attorney covers what happens
            <strong>if you lose capacity while alive</strong>. An In Case of Emergency letter tells your family
            <strong>where everything is</strong>.
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            You ideally need all three.
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
  name: 'WhenShouldIMakeAWillPage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  mounted() {
    document.title = 'When Should I Make a Will? — UK Guide | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Don\'t have a will? You\'re not alone — but you should. Learn when you need one, what happens without one, and how to get started.');
    }
  },
};
</script>
