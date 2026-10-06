<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          ISA Allowance {{ currentTaxYear }}: Make the Most of Your {{ isaAllowance }}
        </h1>
      </div>
    </div>

    <!-- Article -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Back link + categories -->
        <div class="flex items-center gap-3 mb-2">
          <router-link to="/insights" class="text-sm text-raspberry-500 hover:underline font-medium">&larr; Back to Insights</router-link>
          <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-spring-50 text-spring-700">Savings & ISA</span>
        </div>
        <p class="text-xs text-neutral-400 mb-8">Published April 2025 &middot; Updated for {{ currentTaxYear }} tax year</p>

        <article class="space-y-10">

          <!-- The Basics -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">The basics</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              The ISA allowance for the {{ currentTaxYear }} tax year ({{ taxYearRange }}) is {{ isaAllowance }}. You can split it across different ISA types, but the total must not exceed {{ isaAllowance }}.
            </p>
          </div>

          <!-- ISA Types and Limits -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">ISA types and limits</h2>
            <div class="bg-light-blue-100 rounded-lg border border-light-gray overflow-hidden">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-horizon-500">
                    <th class="text-left px-4 py-2.5 text-sm font-semibold text-white">ISA Type</th>
                    <th class="text-right px-4 py-2.5 text-sm font-semibold text-white">Annual Limit</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Cash ISA</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">{{ isaAllowance }}</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Stocks and Shares ISA</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">{{ isaAllowance }}</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Lifetime ISA</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">{{ lifetimeIsaAllowance }} <span class="text-xs text-neutral-400">(counts towards {{ isaAllowance }})</span></td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Innovative Finance ISA</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">{{ isaAllowance }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="text-xs text-neutral-400 mt-2">You can pay into more than one ISA of the same type in a tax year, except a Lifetime ISA or a Junior ISA (one of each per tax year).</p>
          </div>

          <!-- Quote callout -->
          <blockquote class="border-l-4 border-raspberry-500 bg-light-pink-100 rounded-r-xl px-6 py-5 my-2">
            <p class="text-lg font-semibold text-horizon-500 italic leading-relaxed">
              "You can split your {{ isaAllowance }} ISA allowance across different ISA types in the same tax year &mdash; but once the tax year ends, any unused allowance is lost forever."
            </p>
          </blockquote>

          <!-- Key Dates -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Key dates</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li><strong class="text-horizon-500">Tax year:</strong> {{ taxYearRange }}.</li>
              <li><strong class="text-horizon-500">Use it or lose it</strong> &mdash; unused allowance does not carry forward.</li>
              <li><strong class="text-horizon-500">Lifetime ISA:</strong> available to 18&ndash;39 year olds. 25% government bonus on contributions up to {{ lifetimeIsaAllowance }} (maximum &pound;1,000 bonus per year).</li>
            </ul>
          </div>

          <!-- What changes from April 2027 -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">What changes from April 2027</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              From 6 April 2027 the overall ISA allowance stays at {{ isaAllowance }}, but the amount you can hold in a Cash ISA changes. Savers under 65 will be limited to &pound;12,000 a year in a Cash ISA; those aged 65 and over keep the full {{ isaAllowance }}.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mt-3">
              Two related rules start on the same date: a flat-rate 22% charge applies to interest paid on cash held inside a non-Cash ISA, and transfers from a non-Cash ISA into a Cash ISA will not be permitted for savers under 65.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mt-3">
              That makes {{ currentTaxYear }} the last tax year in which someone under 65 can shelter a full {{ isaAllowance }} in cash.
            </p>
          </div>

          <!-- Strategies -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Strategies</h2>
            <ol class="text-sm text-neutral-500 leading-relaxed list-decimal pl-5 space-y-3">
              <li>
                <strong class="text-horizon-500">Use your allowance early in the tax year.</strong>
                More time in the market (or earning interest) means more potential growth.
              </li>
              <li>
                <strong class="text-horizon-500">Consider Stocks and Shares for goals five or more years away.</strong>
                Historically, investment returns have outpaced cash over longer periods.
              </li>
              <li>
                <strong class="text-horizon-500">If you're a couple, you have {{ coupleIsaAllowance }} combined allowance.</strong>
                Each person gets their own {{ isaAllowance }} &mdash; there is no joint ISA, and one person's unused allowance cannot be transferred to the other.
              </li>
              <li>
                <strong class="text-horizon-500">Don't forget Junior ISAs.</strong>
                {{ juniorIsaAllowance }} per child per year, and the money is locked until they turn 18.
              </li>
            </ol>
          </div>

          <!-- Common Mistakes -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Common mistakes</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li>Paying into more than one Lifetime ISA in a tax year (not allowed &mdash; but you can pay into more than one Cash or Stocks and Shares ISA).</li>
              <li>Forgetting the Lifetime ISA counts towards your {{ isaAllowance }} total.</li>
              <li>Leaving it until March and rushing.</li>
              <li>Not using the allowance at all.</li>
            </ul>
          </div>

          <!-- Related Links -->
          <div class="bg-light-blue-100 rounded-2xl p-6">
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Related</h2>
            <ul class="space-y-2">
              <li>
                <router-link to="/learn/what-is-an-isa" class="text-sm text-raspberry-500 hover:underline">What is an ISA?</router-link>
              </li>
              <li>
                <router-link to="/calculators" class="text-sm text-raspberry-500 hover:underline">Savings Goal Calculator</router-link>
              </li>
              <li>
                <router-link to="/learn/glossary#isa" class="text-sm text-raspberry-500 hover:underline">Glossary: ISA</router-link>
              </li>
              <li>
                <router-link to="/stage/starting-out" class="text-sm text-raspberry-500 hover:underline">Starting Out</router-link>
              </li>
              <li>
                <router-link to="/stage/building-foundations" class="text-sm text-raspberry-500 hover:underline">Building Foundations</router-link>
              </li>
            </ul>
          </div>

          <!-- Disclaimer -->
          <div class="border-t border-neutral-200 pt-6">
            <p class="text-xs text-neutral-400 leading-relaxed">
              <strong>Disclaimer:</strong> This article is for informational and educational purposes only. It does not constitute personalised financial advice. ISA rules, allowances and tax treatment depend on individual circumstances and may change. If you are unsure about your savings or investment options, consider speaking to a qualified financial adviser.
            </p>
          </div>
        </article>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">Track your ISA allowance in Fynla</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
          See how much of your {{ isaAllowance }} you've used, across all your ISA accounts, in one place.
        </p>
        <a
          href="#" @click.prevent="$router.push({ query: { demo: 'true' } })"
          class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors"
        >
          Try the demo
        </a>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import { getCurrentTaxYear, getTaxYearStart, getTaxYearEnd } from '@/utils/dateFormatter';
import { ISA_ANNUAL_ALLOWANCE, LIFETIME_ISA_ALLOWANCE, JUNIOR_ISA_ALLOWANCE } from '@/constants/taxConfig';

export default {
  name: 'IsaAllowance202526Page',
  components: { PublicLayout },

  computed: {
    currentTaxYear() {
      return getCurrentTaxYear();
    },

    taxYearRange() {
      const fmt = (d) => d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
      return `${fmt(getTaxYearStart())} to ${fmt(getTaxYearEnd())}`;
    },

    isaAllowance() {
      return this.formatAllowance(ISA_ANNUAL_ALLOWANCE);
    },

    lifetimeIsaAllowance() {
      return this.formatAllowance(LIFETIME_ISA_ALLOWANCE);
    },

    juniorIsaAllowance() {
      return this.formatAllowance(JUNIOR_ISA_ALLOWANCE);
    },

    coupleIsaAllowance() {
      return this.formatAllowance(ISA_ANNUAL_ALLOWANCE * 2);
    },
  },

  methods: {
    formatAllowance(value) {
      return `\u00a3${value.toLocaleString('en-GB')}`;
    },
  },

  mounted() {
    document.title = `ISA Allowance ${this.currentTaxYear} \u2014 What You Need to Know | Fynla`;
  },
};
</script>
