<template>
  <PublicLayout>
    <!-- Hero -->
    <section class="bg-gradient-to-r from-horizon-500 to-raspberry-500 py-16 sm:py-20">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-3xl sm:text-4xl font-black text-white mb-3">
          ISA Allowance 2025/26: Make the Most of Your &pound;20,000
        </h1>
        <p class="text-sm text-white/70">Published April 2025 &middot; Updated for 2025/26 tax year</p>
      </div>
    </section>

    <!-- Article -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <article class="space-y-10">

          <!-- The Basics -->
          <div>
            <h2 class="text-lg font-bold text-horizon-500 mb-3">The Basics</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              The ISA allowance for the 2025/26 tax year (6 April 2025 to 5 April 2026) is &pound;20,000. This is unchanged from 2024/25. You can split it across different ISA types, but the total must not exceed &pound;20,000.
            </p>
          </div>

          <!-- ISA Types and Limits -->
          <div>
            <h2 class="text-lg font-bold text-horizon-500 mb-3">ISA Types and Limits</h2>
            <div class="bg-white rounded-lg border border-light-gray overflow-hidden">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-savannah-50">
                    <th class="text-left px-4 py-2.5 text-xs font-semibold text-horizon-500">ISA Type</th>
                    <th class="text-right px-4 py-2.5 text-xs font-semibold text-horizon-500">Annual Limit</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Cash ISA</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;20,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Stocks and Shares ISA</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;20,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Lifetime ISA</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;4,000 <span class="text-xs text-neutral-400">(counts towards &pound;20,000)</span></td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Innovative Finance ISA</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;20,000</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="text-xs text-neutral-400 mt-2">You can hold one of each type per tax year.</p>
          </div>

          <!-- Key Dates -->
          <div>
            <h2 class="text-lg font-bold text-horizon-500 mb-3">Key Dates</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li><strong class="text-horizon-500">Tax year:</strong> 6 April 2025 to 5 April 2026.</li>
              <li><strong class="text-horizon-500">Use it or lose it</strong> &mdash; unused allowance does not carry forward.</li>
              <li><strong class="text-horizon-500">Lifetime ISA:</strong> available to 18&ndash;39 year olds. 25% government bonus on contributions up to &pound;4,000 (maximum &pound;1,000 bonus per year).</li>
            </ul>
          </div>

          <!-- Strategies -->
          <div>
            <h2 class="text-lg font-bold text-horizon-500 mb-3">Strategies</h2>
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
                <strong class="text-horizon-500">If you're a couple, you have &pound;40,000 combined allowance.</strong>
                Each person gets their own &pound;20,000 &mdash; make sure you're both using it.
              </li>
              <li>
                <strong class="text-horizon-500">Don't forget Junior ISAs.</strong>
                &pound;9,000 per child per year, and the money is locked until they turn 18.
              </li>
            </ol>
          </div>

          <!-- Common Mistakes -->
          <div>
            <h2 class="text-lg font-bold text-horizon-500 mb-3">Common Mistakes</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li>Opening two of the same ISA type in one tax year (not allowed).</li>
              <li>Forgetting the Lifetime ISA counts towards your &pound;20,000 total.</li>
              <li>Leaving it until March and rushing.</li>
              <li>Not using the allowance at all.</li>
            </ul>
          </div>

          <!-- Related Links -->
          <div class="border-t border-light-gray pt-8">
            <h2 class="text-sm font-bold text-horizon-500 mb-3">Related</h2>
            <ul class="space-y-1.5">
              <li>
                <router-link to="/learn/what-is-an-isa" class="text-xs text-raspberry-500 hover:underline">What is an ISA?</router-link>
              </li>
              <li>
                <router-link to="/calculators" class="text-xs text-raspberry-500 hover:underline">Savings Goal Calculator</router-link>
              </li>
              <li>
                <router-link to="/learn/glossary#isa" class="text-xs text-raspberry-500 hover:underline">Glossary: ISA</router-link>
              </li>
              <li>
                <router-link to="/stage/starting-out" class="text-xs text-raspberry-500 hover:underline">Starting Out</router-link>
              </li>
              <li>
                <router-link to="/stage/building-foundations" class="text-xs text-raspberry-500 hover:underline">Building Foundations</router-link>
              </li>
            </ul>
          </div>
        </article>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-white">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-lg font-bold text-horizon-500 mb-2">Track your ISA allowance in Fynla</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
          See how much of your &pound;20,000 you've used, across all your ISA accounts, in one place.
        </p>
        <a
          href="/?demo=true"
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

export default {
  name: 'IsaAllowance202526Page',
  components: { PublicLayout },

  mounted() {
    document.title = 'ISA Allowance 2025/26 \u2014 What You Need to Know | Fynla';
  },
};
</script>
