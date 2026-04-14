<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          What Is a Stocks and Shares ISA?
        </h1>
        <p class="text-lg md:text-xl text-white/90 max-w-3xl">
          How it works, benefits, risks and fees explained.
        </p>
      </div>
    </div>

    <!-- Article -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Back link + categories -->
        <div class="flex items-center gap-3 mb-2">
          <router-link to="/insights" class="text-sm text-raspberry-500 hover:underline font-medium">&larr; Back to Insights</router-link>
          <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-violet-50 text-violet-700">ISAs</span>
          <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-violet-50 text-violet-700">Investing</span>
        </div>
        <p class="text-xs text-neutral-400 mb-8">By Chris Slater-Jones, DipPFS &amp; Brett Isenberg, FCA &middot; Published on 13 April 2026</p>

        <article class="space-y-10">

          <!-- Intro -->
          <div>
            <p class="text-sm text-neutral-500 leading-relaxed italic">
              A Stocks and Shares ISA is an investment account where any growth, dividends and capital gains are completely tax-free. If you are planning to invest for long-term goals &mdash; whether retirement, a child&rsquo;s education, or building wealth &mdash; a Stocks and Shares ISA is one of the most tax-efficient wrappers available in the UK.
            </p>
          </div>

          <!-- How It Works -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">How a Stocks and Shares ISA works</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              A Stocks and Shares ISA is a tax-free investment account. You can invest up to &pound;20,000 per tax year (April to April), and any returns you generate are sheltered from tax.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              How it differs from a regular investment account: if you invested &pound;20,000 in shares outside an ISA and those shares grew to &pound;30,000, you would owe Capital Gains Tax on the &pound;10,000 profit. At a 20% rate, that is &pound;2,000 in tax, leaving you with &pound;28,000. Inside an ISA, the same &pound;30,000 is entirely yours &mdash; zero tax.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              This tax shelter is powerful over long time horizons because the benefit compounds. Over 20&ndash;30 years, tax-free growth creates substantially more wealth than taxable growth.
            </p>
          </div>

          <!-- What Can You Invest In -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">What can you invest in?</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Stocks and Shares ISAs offer wide investment choice. Most platforms let you invest in:
            </p>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li><strong class="text-horizon-500">Funds:</strong> mutual funds, unit trusts and investment funds &mdash; professional portfolios managed by fund managers.</li>
              <li><strong class="text-horizon-500">Exchange-Traded Funds (ETFs):</strong> funds that track indices like the FTSE 100 or S&amp;P 500. ETFs often have lower fees than actively managed funds.</li>
              <li><strong class="text-horizon-500">Individual stocks:</strong> buy shares in specific companies directly.</li>
              <li><strong class="text-horizon-500">Investment trusts:</strong> companies whose business is investing in other companies.</li>
              <li><strong class="text-horizon-500">Bonds and fixed-income:</strong> government and corporate bonds for lower-risk, income-focused portfolios.</li>
              <li><strong class="text-horizon-500">Money market funds:</strong> cash-like investments for flexibility and safety.</li>
            </ul>
            <p class="text-sm text-neutral-500 leading-relaxed mt-3">
              Most modern investment platforms give you this full range of choices within a Stocks and Shares ISA, making it easy to build a diversified portfolio tailored to your goals and risk tolerance.
            </p>
          </div>

          <!-- Tax Benefits -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Tax benefits: the core advantage</h2>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">No Capital Gains Tax</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              All growth on your investment is tax-free. Invest &pound;5,000 and it grows to &pound;15,000? No Capital Gains Tax. The entire &pound;10,000 profit is yours.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">No dividend tax</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Dividends paid by shares or funds are taxed at 8.75% for basic-rate taxpayers (rising to 33.75% and 39.35% for higher and additional-rate taxpayers) outside an ISA. Inside a Stocks and Shares ISA, dividends are completely tax-free. This is a significant advantage for income-focused portfolios.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">No annual tax reporting</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              With a taxable investment account, you must report all gains and dividends to HM Revenue &amp; Customs each year. With an ISA, the tax efficiency is automatic &mdash; no paperwork, no tax return complications.
            </p>
          </div>

          <!-- Risks -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Risks: what you need to know</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              The tax efficiency is real, but it does not eliminate investment risk:
            </p>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-3">
              <li><strong class="text-horizon-500">Capital loss:</strong> your investment value can fall. If you invest &pound;20,000 in stocks and the market declines, you could have &pound;15,000. The ISA wrapper protects against tax, but not market risk.</li>
              <li><strong class="text-horizon-500">No capital loss relief:</strong> unlike investment losses outside an ISA (which you can offset against gains), losses inside an ISA cannot be claimed back. This is a trade-off for tax-free growth.</li>
              <li><strong class="text-horizon-500">Volatility:</strong> stocks and funds fluctuate. If you need your money in 2&ndash;3 years, this volatility is a risk. A 5&ndash;10 year time horizon mitigates this significantly.</li>
              <li><strong class="text-horizon-500">Concentration risk:</strong> if you invest heavily in a few companies or sectors, a downturn can hit hard. Diversification is essential.</li>
            </ul>
          </div>

          <!-- Comparison Table -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Stocks and Shares ISA vs Cash ISA</h2>
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left border-collapse">
                <thead>
                  <tr class="bg-horizon-500 text-white">
                    <th class="px-4 py-2 font-semibold rounded-tl-lg">Factor</th>
                    <th class="px-4 py-2 font-semibold">Stocks and Shares ISA</th>
                    <th class="px-4 py-2 font-semibold rounded-tr-lg">Cash ISA</th>
                  </tr>
                </thead>
                <tbody class="text-neutral-500">
                  <tr class="border-b border-neutral-200">
                    <td class="px-4 py-2 font-medium text-horizon-500">Returns</td>
                    <td class="px-4 py-2">Variable (5&ndash;10%+ historically)</td>
                    <td class="px-4 py-2">Fixed interest (1&ndash;5%)</td>
                  </tr>
                  <tr class="border-b border-neutral-200 bg-eggshell-500/50">
                    <td class="px-4 py-2 font-medium text-horizon-500">Risk</td>
                    <td class="px-4 py-2">Medium to High</td>
                    <td class="px-4 py-2">Very Low</td>
                  </tr>
                  <tr class="border-b border-neutral-200">
                    <td class="px-4 py-2 font-medium text-horizon-500">Time horizon</td>
                    <td class="px-4 py-2">5&ndash;10+ years</td>
                    <td class="px-4 py-2">1&ndash;3 years</td>
                  </tr>
                  <tr class="border-b border-neutral-200 bg-eggshell-500/50">
                    <td class="px-4 py-2 font-medium text-horizon-500">Tax benefits</td>
                    <td class="px-4 py-2">Capital gains and dividend tax exempt</td>
                    <td class="px-4 py-2">Interest tax-free</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2 font-medium text-horizon-500">Best for</td>
                    <td class="px-4 py-2">Long-term investors</td>
                    <td class="px-4 py-2">Safety and accessibility</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Fees -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Fees to watch</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Platform fees vary significantly. Watch for:
            </p>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-3">
              <li><strong class="text-horizon-500">Platform fee:</strong> annual charge by your ISA provider, typically 0.2%&ndash;0.75% of your portfolio value.</li>
              <li><strong class="text-horizon-500">Fund charges:</strong> each fund or Exchange-Traded Fund has its own charges (Ongoing Charge Figure, or OCF), ranging from 0.1% (low-cost index funds) to 1%+ (actively managed funds).</li>
              <li><strong class="text-horizon-500">Trading fees:</strong> some platforms charge per trade. Others offer unlimited free trades.</li>
              <li><strong class="text-horizon-500">Inactivity fees:</strong> some charge if you do not make transactions. Avoid these if you buy-and-hold.</li>
            </ul>
            <p class="text-sm text-neutral-500 leading-relaxed mt-3">
              <strong class="text-horizon-500">Example impact:</strong> a &pound;20,000 investment in a Stocks and Shares ISA growing at 6% annually. With a 0.2% platform fee plus 0.3% fund fees (0.5% total), you net approximately 5.5% growth. Over 20 years, that compounds to roughly &pound;59,000. With a 0.75% platform fee plus 0.75% fund fees (1.5% total), you net approximately 4.5% growth, compounding to roughly &pound;48,000. That 1% fee difference costs you over &pound;11,000 from a single year&rsquo;s contribution alone.
            </p>
          </div>

          <!-- Quote callout -->
          <blockquote class="border-l-4 border-raspberry-500 bg-light-pink-100 rounded-r-xl px-6 py-5 my-2">
            <p class="text-lg font-semibold text-horizon-500 italic leading-relaxed">
              &ldquo;A 1% difference in annual fees can cost you over &pound;11,000 on a single year&rsquo;s &pound;20,000 contribution over 20 years. Small percentages compound into life-changing sums.&rdquo;
            </p>
          </blockquote>

          <!-- How to Choose -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">How to choose an ISA platform</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Evaluate platforms on:
            </p>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-3">
              <li><strong class="text-horizon-500">Fees:</strong> check the all-in cost of platform plus fund charges.</li>
              <li><strong class="text-horizon-500">Investment choice:</strong> ensure they offer the investments you want (funds, Exchange-Traded Funds, individual stocks and so on).</li>
              <li><strong class="text-horizon-500">User experience:</strong> is the platform intuitive? Can you easily rebalance and monitor your portfolio?</li>
              <li><strong class="text-horizon-500">Regulation:</strong> ensure the provider is regulated by the Financial Conduct Authority.</li>
              <li><strong class="text-horizon-500">Customer service:</strong> read reviews and check availability of support.</li>
              <li><strong class="text-horizon-500">Minimum investment:</strong> some have minimums of &pound;500 or &pound;1,000; others allow &pound;1 or no minimum.</li>
            </ul>
          </div>

          <!-- Common Mistakes -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Common ISA investing mistakes</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-3">
              <li>Choosing active funds with high fees when index-tracking Exchange-Traded Funds have lower charges.</li>
              <li>Not rebalancing &mdash; your portfolio drifts from your intended risk level.</li>
              <li>Panic selling during downturns &mdash; markets recover, but only if you stay invested.</li>
              <li>Concentrating in a handful of stocks &mdash; lack of diversification increases risk.</li>
              <li>Not reviewing your investments annually &mdash; fees change, strategies become outdated.</li>
              <li>Timing the market &mdash; trying to buy low and sell high is nearly impossible. Regular contributions (&ldquo;pound-cost averaging&rdquo;) are more reliable.</li>
            </ul>
          </div>

          <!-- How Fynla Can Help -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">How Fynla can help</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Fynla helps you track your Stocks and Shares ISA alongside your other investments and savings, see your combined asset allocation, monitor fees, and project growth under different scenarios. You can also visualise whether you are on track for your long-term financial goals.
            </p>
          </div>

          <!-- Key Takeaways -->
          <div class="bg-light-blue-100 rounded-2xl p-6">
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Key takeaways</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li>A Stocks and Shares ISA lets you invest in funds, Exchange-Traded Funds, stocks and bonds with no tax on gains or dividends.</li>
              <li>The &pound;20,000 annual allowance is shared across all ISA types &mdash; use it strategically.</li>
              <li>Best for 5&ndash;10+ year horizons when stock market volatility can be absorbed.</li>
              <li>Compare platform fees carefully &mdash; 0.5% vs 1.5% costs over &pound;10,000 on a &pound;20,000 investment over 20 years.</li>
              <li>Diversification and regular contributions outperform market timing.</li>
              <li>Your money is at risk in a Stocks and Shares ISA &mdash; choose your investments based on time horizon and risk tolerance.</li>
            </ul>
          </div>

          <!-- Related Links -->
          <div class="bg-light-blue-100 rounded-2xl p-6">
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Related</h2>
            <ul class="space-y-2">
              <li>
                <router-link to="/insights/isa-guide-uk" class="text-sm text-raspberry-500 hover:underline">The Ultimate Guide to ISAs in the UK</router-link>
              </li>
              <li>
                <router-link to="/insights/isa-allowance-2025-26" class="text-sm text-raspberry-500 hover:underline">ISA Allowance 2025/26: What You Need to Know</router-link>
              </li>
              <li>
                <router-link to="/learn/what-is-an-isa" class="text-sm text-raspberry-500 hover:underline">What Is an ISA?</router-link>
              </li>
            </ul>
          </div>

          <!-- Sources -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Sources</h2>
            <ul class="text-xs text-neutral-400 leading-relaxed list-disc pl-5 space-y-1">
              <li>GOV.UK &mdash; Stocks and Shares ISA guidance: gov.uk/individual-savings-accounts</li>
              <li>MoneyHelper &mdash; Stocks and Shares ISAs: moneyhelper.org.uk</li>
              <li>Financial Conduct Authority &mdash; regulated ISA platforms and fees comparison</li>
            </ul>
          </div>

          <!-- Disclaimer -->
          <div class="border-t border-neutral-200 pt-6">
            <p class="text-xs text-neutral-400 leading-relaxed">
              <strong>Disclaimer:</strong> This article is for informational and educational purposes only. It does not constitute investment advice. The value of Stocks and Shares ISAs can go down as well as up, and you may get back less than you invest. Past performance is not a guide to future performance. Consider your risk tolerance, time horizon, and financial goals before investing. If you are unsure, consult a qualified financial adviser.
            </p>
          </div>
        </article>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">See your Stocks and Shares ISA in your full financial plan</h2>
        <p class="text-sm text-neutral-500 mb-6 max-w-md mx-auto">
          Track your ISA contributions, model growth scenarios, and plan alongside your pensions and investments.
        </p>
        <router-link
          to="/register"
          class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors"
        >
          Start your free trial
        </router-link>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';

export default {
  name: 'StocksSharesIsaUkPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'What Is a Stocks and Shares ISA? How It Works, Benefits & Risks Explained | Fynla';
  },
};
</script>
