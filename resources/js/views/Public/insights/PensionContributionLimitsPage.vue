<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Pension Contribution Limits UK 2026/27: How Much Can You Pay In?
        </h1>
      </div>
    </div>

    <!-- Article -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Back link + categories -->
        <div class="flex items-center gap-3 mb-2">
          <router-link to="/insights" class="text-sm text-raspberry-500 hover:underline font-medium">&larr; Back to Insights</router-link>
          <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-violet-50 text-violet-700">Pensions</span>
        </div>
        <p class="text-xs text-neutral-400 mb-1">By Chris Slater-Jones, DipPFS &amp; Brett Isenberg, FCA</p>
        <p class="text-xs text-neutral-400 mb-8">Published April 2026 &middot; Last reviewed April 2026</p>

        <article class="space-y-10">

          <!-- The Annual Allowance Explained -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">The annual allowance explained</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              For the 2026/27 tax year, the Annual Allowance is &pound;60,000 or 100% of your qualifying earnings, whichever is lower. This is the maximum you can contribute to all your pension schemes in a single tax year while still receiving tax relief.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mt-3">
              Both your own contributions and any employer contributions count towards this limit. If you exceed the Annual Allowance, the excess is added to your taxable income and charged at your marginal income tax rate via the Annual Allowance charge.
            </p>
          </div>

          <!-- Tax Relief on Pension Contributions -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Tax relief on pension contributions</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Pension contributions benefit from tax relief, meaning the government tops up your contributions. The amount depends on your tax band:
            </p>
            <div class="bg-light-blue-100 rounded-lg border border-light-gray overflow-hidden">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-horizon-500">
                    <th class="text-left px-4 py-2.5 text-sm font-semibold text-white">Tax Band</th>
                    <th class="text-right px-4 py-2.5 text-sm font-semibold text-white">Rate</th>
                    <th class="text-right px-4 py-2.5 text-sm font-semibold text-white">Effective Cost of &pound;100 Contribution</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Basic rate</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">20%</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;80 <span class="text-xs text-neutral-400">(relief added automatically)</span></td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Higher rate</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">40%</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;60 <span class="text-xs text-neutral-400">(claim extra 20% via self-assessment)</span></td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Additional rate</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">45%</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;55 <span class="text-xs text-neutral-400">(claim extra 25% via self-assessment)</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="text-xs text-neutral-400 mt-2">Basic rate relief is applied at source by your pension provider. Higher and additional rate taxpayers must claim the extra relief through self-assessment.</p>
          </div>

          <!-- Quote callout -->
          <blockquote class="border-l-4 border-raspberry-500 bg-light-pink-100 rounded-r-xl px-6 py-5 my-2">
            <p class="text-lg font-semibold text-horizon-500 italic leading-relaxed">
              "A higher-rate taxpayer contributing &pound;100 to their pension effectively pays just &pound;60 after tax relief &mdash; making pensions one of the most tax-efficient ways to save."
            </p>
          </blockquote>

          <!-- Carry Forward -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Carry forward: using previous years' allowance</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              If you haven't used your full Annual Allowance in the previous three tax years, you can carry forward the unused amount and add it to this year's allowance.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mt-3">
              For example, if you had &pound;15,000 unused allowance in each of the previous three years, you could potentially contribute up to &pound;105,000 in the current year (&pound;60,000 current year + &pound;45,000 carried forward).
            </p>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2 mt-3">
              <li>You must have been a member of a registered pension scheme in each year you carry forward from.</li>
              <li>The current year's allowance is used first, then the earliest unused year.</li>
              <li>Carry forward is <strong class="text-horizon-500">not available</strong> if the Money Purchase Annual Allowance has been triggered.</li>
            </ul>
          </div>

          <!-- Tapered Annual Allowance -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Tapered annual allowance for high earners</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              If your adjusted income exceeds &pound;260,000, your Annual Allowance is reduced by &pound;1 for every &pound;2 of income above that threshold, down to a minimum of &pound;10,000.
            </p>
            <div class="bg-light-blue-100 rounded-lg border border-light-gray overflow-hidden">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-horizon-500">
                    <th class="text-left px-4 py-2.5 text-sm font-semibold text-white">Adjusted Income</th>
                    <th class="text-right px-4 py-2.5 text-sm font-semibold text-white">Annual Allowance</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">&pound;260,000 or less</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;60,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">&pound;280,000</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;50,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">&pound;300,000</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;40,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">&pound;340,000</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;20,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">&pound;360,000 or more</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;10,000</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- MPAA -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Money purchase annual allowance</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              If you have flexibly accessed a defined contribution pension (for example, through flexible income drawdown), a reduced Annual Allowance of &pound;10,000 applies permanently. This is known as the Money Purchase Annual Allowance.
            </p>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2 mt-3">
              <li>Once triggered, the Money Purchase Annual Allowance applies for the rest of your life.</li>
              <li>You lose the ability to carry forward unused allowance from previous years.</li>
              <li>Taking your 25% tax-free lump sum alone does <strong class="text-horizon-500">not</strong> trigger the Money Purchase Annual Allowance.</li>
              <li>Only flexible income drawdown or taking an uncrystallised funds pension lump sum triggers it.</li>
            </ul>
          </div>

          <!-- Quote callout -->
          <blockquote class="border-l-4 border-raspberry-500 bg-light-pink-100 rounded-r-xl px-6 py-5 my-2">
            <p class="text-lg font-semibold text-horizon-500 italic leading-relaxed">
              "Taking your 25% tax-free lump sum does not trigger the Money Purchase Annual Allowance &mdash; a common misconception that could cost you thousands in lost pension contributions."
            </p>
          </blockquote>

          <!-- No Earnings -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">What if you have no earnings?</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Even if you have no qualifying earnings, you can still contribute up to &pound;3,600 gross per year into a pension. You pay &pound;2,880 of your own money and receive &pound;720 in basic rate tax relief automatically.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mt-3">
              This is particularly useful for non-working spouses, carers, or anyone on a career break. It's a straightforward way to keep building retirement savings even during periods without earned income.
            </p>
          </div>

          <!-- Qualifying Earnings and Auto-Enrolment -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Qualifying earnings and auto-enrolment</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Under auto-enrolment, the minimum total contribution is 8% of qualifying earnings, with at least 3% from your employer. Qualifying earnings for 2026/27 are between &pound;6,240 and &pound;50,270.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mt-3">
              It's important to note that qualifying earnings are not the same as your total salary. The contribution percentages only apply to the band of earnings between the lower and upper limits, which means actual contributions may be lower than you expect.
            </p>
          </div>

          <!-- Lifetime Allowance -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">The lifetime allowance: what changed?</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              The Lifetime Allowance was abolished from 6 April 2024. It has been replaced by the Lump Sum Allowance of &pound;268,275, which caps the total tax-free lump sums you can take from your pensions over your lifetime.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mt-3">
              If you had Lifetime Allowance protection before April 2024, you may have a higher Lump Sum Allowance. Check with your pension provider or a financial adviser if you think this applies to you.
            </p>
          </div>

          <!-- How Fynla Can Help -->
          <div>
            <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">How Fynla can help</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Fynla's <router-link to="/features/pension-tracker" class="text-raspberry-500 hover:underline">pension tracker</router-link> lets you model different contribution scenarios, track your Annual Allowance usage, and calculate carry forward from previous tax years &mdash; all in one place.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mt-3">
              <router-link to="/register" class="text-raspberry-500 hover:underline">Create a free account</router-link> to get started.
            </p>
          </div>

          <!-- Key Takeaways -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Key takeaways</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li>The Annual Allowance for 2026/27 is &pound;60,000 (or 100% of earnings if lower).</li>
              <li>Both your own and employer contributions count towards the limit.</li>
              <li>Higher and additional rate taxpayers can claim extra tax relief via self-assessment.</li>
              <li>Unused allowance from the previous three years can be carried forward.</li>
              <li>High earners above &pound;260,000 face a tapered allowance, down to &pound;10,000.</li>
              <li>Flexibly accessing a defined contribution pension triggers the &pound;10,000 Money Purchase Annual Allowance permanently.</li>
              <li>Even with no earnings, you can contribute up to &pound;3,600 gross per year.</li>
              <li>The Lifetime Allowance has been abolished and replaced by the Lump Sum Allowance.</li>
            </ul>
          </div>

          <!-- Sources -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Sources</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li><a href="https://www.gov.uk/tax-on-your-private-pension/annual-allowance" target="_blank" rel="noopener noreferrer" class="text-raspberry-500 hover:underline">GOV.UK &mdash; Pension annual allowance and contribution rates</a></li>
              <li><a href="https://www.moneyhelper.org.uk/en/pensions-and-retirement/tax-and-pensions/tax-relief-and-your-pension" target="_blank" rel="noopener noreferrer" class="text-raspberry-500 hover:underline">MoneyHelper &mdash; Tax relief and your pension</a></li>
              <li><a href="https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief" target="_blank" rel="noopener noreferrer" class="text-raspberry-500 hover:underline">HMRC &mdash; Pension tax relief</a></li>
              <li><a href="https://www.thepensionsregulator.gov.uk/en/employers/auto-enrolment" target="_blank" rel="noopener noreferrer" class="text-raspberry-500 hover:underline">The Pensions Regulator &mdash; Auto-enrolment</a></li>
            </ul>
          </div>

          <!-- Disclaimer -->
          <div class="bg-light-blue-100 rounded-2xl p-6">
            <h2 class="text-sm font-bold text-horizon-500 mb-3">Disclaimer</h2>
            <p class="text-xs text-neutral-400 leading-relaxed">
              This article is for informational purposes only and does not constitute financial advice. Tax rules can change and individual circumstances vary. You should consult a qualified financial adviser before making decisions about your pension contributions. Fynla is not regulated by the Financial Conduct Authority and does not provide financial advice.
            </p>
          </div>

          <!-- Related -->
          <div class="bg-light-blue-100 rounded-2xl p-6">
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Related</h2>
            <ul class="space-y-2">
              <li><router-link to="/insights/pension-iht-changes-2027" class="text-sm text-raspberry-500 hover:underline">Pension inheritance tax changes: April 2027</router-link></li>
              <li><router-link to="/learn/what-is-a-sipp" class="text-sm text-raspberry-500 hover:underline">What is a SIPP?</router-link></li>
              <li><router-link to="/learn/what-is-salary-sacrifice" class="text-sm text-raspberry-500 hover:underline">What is salary sacrifice?</router-link></li>
              <li><router-link to="/features/pension-tracker" class="text-sm text-raspberry-500 hover:underline">Fynla's pension tracker</router-link></li>
              <li><router-link to="/features/when-can-i-retire" class="text-sm text-raspberry-500 hover:underline">When can I retire?</router-link></li>
            </ul>
          </div>

        </article>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">Track your pension allowance in Fynla</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
          Model contributions, check your Annual Allowance, and calculate carry forward &mdash; all in one place.
        </p>
        <router-link
          to="/register"
          class="inline-block px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
        >
          Get started free
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'PensionContributionLimitsPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Pension Contribution Limits UK 2026/27: How Much Can You Pay In? | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Find out the 2026/27 pension contribution limits, annual allowance, tax relief rates and carry forward rules. Updated guide for UK savers.');
    }
  },
};
</script>
