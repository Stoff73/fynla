<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Enjoying Your Wealth &mdash; Estate Planning Guide</h1>
        <p class="text-lg text-white/70">
          A practical estate planning guide covering Inheritance Tax, gifting strategies, wills, Lasting Powers of Attorney, and protecting your family&rsquo;s inheritance.
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
              <span>You&rsquo;re retired or approaching retirement.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Your estate could be liable for Inheritance Tax.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You want to ensure your family is provided for.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You&rsquo;re thinking about gifting or trust strategies.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>You want your affairs in order.</span>
            </li>
          </ul>
        </div>

        <!-- Understanding Your Estate -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Understanding Your Estate</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Your estate is everything you own at death: property, pensions (from April 2027), savings, investments, life insurance (unless in trust), and personal possessions &mdash; minus debts and funeral costs.
          </p>
          <p class="text-sm text-neutral-600 leading-relaxed">
            If the total is above &pound;325,000 (the nil rate band), Inheritance Tax is charged at <strong>40% on the excess</strong>.
          </p>
        </div>

        <!-- The Allowances -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">The Allowances</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Understanding the allowances is the foundation of estate planning.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Nil rate band:</strong> &pound;325,000 per person.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Residence nil rate band:</strong> &pound;175,000 (if passing your home to direct descendants).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Both are transferable between spouses.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Maximum tax-free amount for a couple: &pound;1,000,000.</strong></span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>But: the residence nil rate band tapers for estates over &pound;2 million.</span>
            </li>
          </ul>
        </div>

        <!-- Gifting Strategies -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Gifting Strategies</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Gifting is one of the most effective ways to reduce your estate &mdash; but you need to know the rules.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Annual exemption:</strong> &pound;3,000 per year (can carry forward one year).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Small gifts:</strong> &pound;250 per person per year.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Wedding/civil partnership gifts:</strong> &pound;5,000 from parents, &pound;2,500 from grandparents.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Gifts from surplus income:</strong> unlimited, but must be regular and from income (not capital).</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>The 7-year rule:</strong> gifts over the annual exemption become tax-free if you survive 7 years.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Taper relief:</strong> reduces the tax if you die between 3&ndash;7 years after gifting.</span>
            </li>
          </ul>
        </div>

        <!-- The April 2027 Pension Change -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">The April 2027 Pension Change</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            From April 2027, unused pension pots will be included in your estate for Inheritance Tax purposes. This is a significant change that affects many families.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Consider drawing down faster.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Gift from other assets instead.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Review your overall estate strategy.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span>Model both scenarios (current rules and 2027 rules) to understand the impact.</span>
            </li>
          </ul>
        </div>

        <!-- Getting Your Documents in Order -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Getting Your Documents in Order</h2>
          <p class="text-sm text-neutral-600 leading-relaxed mb-3">
            Estate planning is not just about money. The paperwork matters too.
          </p>
          <ul class="space-y-1.5 text-sm text-neutral-600">
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Will:</strong> essential &mdash; review every 3&ndash;5 years.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Lasting Power of Attorney:</strong> for finances and health decisions.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>In Case of Emergency letter:</strong> tells your family where everything is and what to do.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1.5 h-1.5 rounded-full bg-violet-500 mt-1.5 flex-shrink-0"></span>
              <span><strong>Life insurance:</strong> ensure policies are written in trust (otherwise they form part of your estate).</span>
            </li>
          </ul>
        </div>

        <!-- Working with Professionals -->
        <div>
          <h2 class="text-base font-bold text-horizon-500 mb-2">Working with Professionals</h2>
          <p class="text-sm text-neutral-600 leading-relaxed">
            For complex estates &mdash; trusts, business assets, cross-border situations &mdash; work with a solicitor and/or financial adviser.
            Fynla gives you the numbers to make those conversations productive and efficient.
          </p>
        </div>

        <!-- Related Links -->
        <div class="border-t border-light-gray pt-6">
          <h2 class="text-xs font-bold text-horizon-500 mb-3">Related</h2>
          <div class="flex flex-wrap gap-2">
            <router-link to="/stage/enjoying-your-wealth" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Enjoying Your Wealth stage</router-link>
            <router-link to="/features/iht-planning" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Inheritance Tax planning</router-link>
            <router-link to="/features/ice-letters" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">ICE letters</router-link>
            <router-link to="/learn/what-is-inheritance-tax" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">What is Inheritance Tax?</router-link>
            <router-link to="/learn/what-is-an-lpa" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">What is a Lasting Power of Attorney?</router-link>
            <router-link to="/insights/pension-iht-changes-2027" class="text-xs text-raspberry-500 hover:text-raspberry-600 underline">Pension Inheritance Tax changes 2027</router-link>
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
  name: 'EnjoyingYourWealthGuidePage',
  components: { PublicLayout, GuideNav, GuideArticleFooter },

  mounted() {
    document.title = 'Estate Planning Guide UK — Protect Your Legacy | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'A practical estate planning guide covering Inheritance Tax, gifting strategies, wills, Lasting Powers of Attorney, and protecting your family\'s inheritance.');
    }
  },
};
</script>
