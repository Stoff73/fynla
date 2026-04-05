<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          Inheritance Tax Explained: Thresholds, Rules &amp; How to Calculate IHT
        </h1>
      </div>
    </div>

    <!-- Article -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Back link + categories -->
        <div class="flex items-center gap-3 mb-2">
          <router-link to="/insights" class="text-sm text-raspberry-500 hover:underline font-medium">&larr; Back to Insights</router-link>
          <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-spring-50 text-spring-700">Estate Planning</span>
        </div>
        <p class="text-xs text-neutral-400 mb-1">By Chris Slater-Jones, DipPFS &amp; Brett Isenberg, FCA</p>
        <p class="text-xs text-neutral-400 mb-8">Published April 2026 &middot; Last reviewed April 2026</p>

        <article class="space-y-10">

          <!-- What Is Inheritance Tax? -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">What is inheritance tax?</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Inheritance Tax is a tax on the estate of someone who has died. It is charged at <strong class="text-horizon-500">40%</strong> on the value of the estate above the Nil-Rate Band. With rising property prices and frozen thresholds, more estates than ever are now being caught by Inheritance Tax. Understanding how the tax works is the first step towards effective estate planning.
            </p>
          </div>

          <!-- Current Inheritance Tax Thresholds for 2026/27 -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Current Inheritance Tax thresholds for 2026/27</h2>
            <div class="bg-light-blue-100 rounded-lg border border-light-gray overflow-hidden">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-horizon-500">
                    <th class="text-left px-4 py-2.5 text-sm font-semibold text-white">Threshold</th>
                    <th class="text-right px-4 py-2.5 text-sm font-semibold text-white">Amount</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Nil-Rate Band (NRB)</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;325,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Residence Nil-Rate Band (RNRB)</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;175,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Combined Individual Allowance</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">Up to &pound;500,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Combined Married Couple Allowance</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">Up to &pound;1,000,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">RNRB Taper Threshold</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">Estates over &pound;2,000,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Inheritance Tax Rate</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">40%</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="mt-4 space-y-3">
              <p class="text-sm text-neutral-500 leading-relaxed">
                The <strong class="text-horizon-500">Nil-Rate Band (NRB)</strong> is the threshold below which no Inheritance Tax is payable. It has been frozen at &pound;325,000 since 2009 and is expected to remain so until at least April 2030.
              </p>
              <p class="text-sm text-neutral-500 leading-relaxed">
                The <strong class="text-horizon-500">Residence Nil-Rate Band (RNRB)</strong> provides an additional &pound;175,000 allowance when you leave your home to direct descendants (children or grandchildren). However, it tapers away by &pound;1 for every &pound;2 your estate exceeds &pound;2,000,000, disappearing entirely at &pound;2,350,000.
              </p>
              <p class="text-sm text-neutral-500 leading-relaxed">
                <strong class="text-horizon-500">Spouse and civil partner transfers:</strong> Any unused NRB and RNRB can be transferred to the surviving spouse or civil partner, effectively doubling the available allowances. Assets passed between spouses are exempt from Inheritance Tax entirely.
              </p>
            </div>
          </div>

          <!-- Quote callout -->
          <blockquote class="border-l-4 border-raspberry-500 bg-light-pink-100 rounded-r-xl px-6 py-5 my-2">
            <p class="text-lg font-semibold text-horizon-500 italic leading-relaxed">
              "Married couples can potentially shelter up to &pound;1,000,000 from inheritance tax by combining their nil-rate bands and residence nil-rate bands."
            </p>
          </blockquote>

          <!-- How Inheritance Tax Is Calculated — A Worked Example -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">How Inheritance Tax is calculated &mdash; a worked example</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-4">
              Consider an estate valued at <strong class="text-horizon-500">&pound;750,000</strong>. Here is how the Inheritance Tax liability varies depending on circumstances:
            </p>
            <div class="bg-light-blue-100 rounded-lg border border-light-gray overflow-hidden">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-horizon-500">
                    <th class="text-left px-4 py-2.5 text-sm font-semibold text-white">Scenario</th>
                    <th class="text-right px-4 py-2.5 text-sm font-semibold text-white">Allowances Used</th>
                    <th class="text-right px-4 py-2.5 text-sm font-semibold text-white">Inheritance Tax Payable</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-light-gray">
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Single person, no property left to descendants</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;325,000 NRB</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;170,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Single person, home left to children</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;325,000 NRB + &pound;175,000 RNRB</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;100,000</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2.5 text-neutral-500">Married couple, both allowances transferred</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;650,000 NRB + &pound;350,000 RNRB</td>
                    <td class="px-4 py-2.5 text-neutral-500 text-right">&pound;0</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="text-xs text-neutral-400 mt-2">Calculated as: (Estate Value &minus; Allowances) &times; 40%.</p>
          </div>

          <!-- Gifts and the 7-Year Rule -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Gifts and the 7-year rule</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Most gifts made during your lifetime are treated as <strong class="text-horizon-500">Potentially Exempt Transfers (PETs)</strong>. If you survive for seven years after making the gift, it falls outside your estate entirely and is free from Inheritance Tax.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              If you die within seven years of making a gift, <strong class="text-horizon-500">taper relief</strong> may reduce the Inheritance Tax due on that gift. The relief increases the longer you survive: gifts made 3&ndash;4 years before death are taxed at 32%, reducing to 8% for gifts made 6&ndash;7 years before death.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              There are also a number of <strong class="text-horizon-500">annual exemptions</strong> that allow you to give away money Inheritance Tax-free each year:
            </p>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li><strong class="text-horizon-500">&pound;3,000 annual exemption</strong> &mdash; you can give away up to &pound;3,000 each tax year. Any unused allowance can be carried forward one year.</li>
              <li><strong class="text-horizon-500">&pound;250 small gifts</strong> &mdash; you can give up to &pound;250 to any number of individuals, provided they have not received any part of your &pound;3,000 exemption.</li>
              <li><strong class="text-horizon-500">Wedding gifts</strong> &mdash; up to &pound;5,000 to a child, &pound;2,500 to a grandchild, or &pound;1,000 to anyone else.</li>
            </ul>
          </div>

          <!-- Quote callout -->
          <blockquote class="border-l-4 border-raspberry-500 bg-light-pink-100 rounded-r-xl px-6 py-5 my-2">
            <p class="text-lg font-semibold text-horizon-500 italic leading-relaxed">
              "If you survive for 7 years after making a gift, it falls completely outside your estate for inheritance tax purposes."
            </p>
          </blockquote>

          <!-- Common Ways to Reduce Your Inheritance Tax Bill -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Common ways to reduce your Inheritance Tax bill</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li><strong class="text-horizon-500">Make gifts during your lifetime</strong> &mdash; use your annual exemptions and make larger gifts where possible, taking advantage of the 7-year rule.</li>
              <li><strong class="text-horizon-500">Write a will</strong> &mdash; ensure your estate is distributed according to your wishes and that available allowances (particularly the RNRB) are claimed.</li>
              <li><strong class="text-horizon-500">Leave a charitable legacy</strong> &mdash; if you leave at least 10% of your net estate to charity, the Inheritance Tax rate on the remainder reduces from 40% to 36%.</li>
              <li><strong class="text-horizon-500">Pensions</strong> &mdash; pension funds generally fall outside your estate for Inheritance Tax purposes, making them a tax-efficient way to pass on wealth.</li>
              <li><strong class="text-horizon-500">Business Property Relief</strong> &mdash; qualifying business assets can receive 50% or 100% relief from Inheritance Tax, significantly reducing the taxable estate.</li>
            </ul>
          </div>

          <!-- How Fynla Can Help -->
          <div>
            <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">How Fynla can help</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Fynla's estate planning tools let you model different scenarios and see how gifts, allowances, and reliefs affect your potential Inheritance Tax liability. You can adjust property values, add gifts, and explore strategies to reduce your estate's tax bill &mdash; all in one place.
              <router-link to="/register" class="text-raspberry-500 hover:underline font-medium">Create a free account</router-link> to get started, or
              <router-link to="/features/iht-planning" class="text-raspberry-500 hover:underline font-medium">learn more about our IHT planning features</router-link>.
            </p>
          </div>

          <!-- Key Takeaways -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Key takeaways</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li>Inheritance Tax is charged at 40% on the value of your estate above the Nil-Rate Band (&pound;325,000).</li>
              <li>The Residence Nil-Rate Band adds up to &pound;175,000 when you leave your home to direct descendants.</li>
              <li>Married couples and civil partners can combine allowances for up to &pound;1,000,000 of Inheritance Tax-free transfers.</li>
              <li>Gifts made more than seven years before death are generally exempt from Inheritance Tax.</li>
              <li>Leaving 10% of your net estate to charity reduces the Inheritance Tax rate to 36%.</li>
              <li>Pensions usually fall outside your estate, making them a powerful planning tool.</li>
              <li>Planning ahead &mdash; including writing a will &mdash; can make a significant difference to your estate's tax position.</li>
            </ul>
          </div>

          <!-- Sources -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Sources</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li>
                <a href="https://www.gov.uk/inheritance-tax" target="_blank" rel="noopener noreferrer" class="text-raspberry-500 hover:underline">GOV.UK &mdash; Inheritance Tax</a>
              </li>
              <li>
                <a href="https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual" target="_blank" rel="noopener noreferrer" class="text-raspberry-500 hover:underline">HMRC &mdash; Inheritance Tax Manual</a>
              </li>
              <li>
                <a href="https://www.moneyhelper.org.uk/en/family-and-care/death-and-bereavement/inheritance-tax-planning" target="_blank" rel="noopener noreferrer" class="text-raspberry-500 hover:underline">MoneyHelper &mdash; Inheritance Tax Planning</a>
              </li>
              <li>
                <a href="https://www.gov.uk/guidance/inheritance-tax-residence-nil-rate-band" target="_blank" rel="noopener noreferrer" class="text-raspberry-500 hover:underline">GOV.UK &mdash; Residence Nil-Rate Band Guidance</a>
              </li>
            </ul>
          </div>

          <!-- Disclaimer -->
          <div class="bg-light-blue-100 rounded-2xl p-6">
            <h2 class="text-sm font-bold text-horizon-500 mb-3">Disclaimer</h2>
            <p class="text-xs text-neutral-400 leading-relaxed">
              This article is for general information purposes only and does not constitute personalised financial or tax advice. Tax rules and thresholds can change. You should consult a qualified financial adviser before making decisions about your estate planning. Fynla is not a financial advisory firm and does not provide regulated advice.
            </p>
          </div>

          <!-- Related -->
          <div class="bg-light-blue-100 rounded-2xl p-6">
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Related</h2>
            <ul class="space-y-2">
              <li><router-link to="/insights/pension-iht-changes-2027" class="text-sm text-raspberry-500 hover:underline">Pension inheritance tax changes: April 2027</router-link></li>
              <li><router-link to="/learn/what-is-inheritance-tax" class="text-sm text-raspberry-500 hover:underline">What is inheritance tax?</router-link></li>
              <li><router-link to="/learn/when-should-i-make-a-will" class="text-sm text-raspberry-500 hover:underline">When should I make a will?</router-link></li>
              <li><router-link to="/features/iht-planning" class="text-sm text-raspberry-500 hover:underline">Fynla's IHT planning tools</router-link></li>
              <li><router-link to="/calculators" class="text-sm text-raspberry-500 hover:underline">Financial calculators</router-link></li>
            </ul>
          </div>

        </article>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">Plan your estate with Fynla</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
          Model your Inheritance Tax liability, explore gift strategies, and see how your allowances work together &mdash; all in one place.
        </p>
        <router-link
          to="/register"
          class="inline-block px-6 py-2.5 bg-raspberry-500 text-white text-sm font-semibold rounded-lg hover:bg-raspberry-600 transition-colors"
        >
          Create your free account
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'InheritanceTaxExplainedPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'Inheritance Tax Explained: Thresholds, Rules & How to Calculate IHT | Fynla';
    const meta = document.querySelector('meta[name="description"]');
    if (meta) {
      meta.setAttribute('content', 'Understand UK inheritance tax with our 2026 guide. Learn IHT thresholds, nil rate bands, calculation methods and strategies to reduce your estate\'s tax bill.');
    }
  },
};
</script>
