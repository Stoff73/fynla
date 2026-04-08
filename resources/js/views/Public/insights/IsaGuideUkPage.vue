<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">
          The Ultimate Guide to ISAs in the UK
        </h1>
      </div>
    </div>

    <!-- Article -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Back link + categories -->
        <div class="flex items-center gap-3 mb-2">
          <router-link to="/insights" class="text-sm text-raspberry-500 hover:underline font-medium">&larr; Back to Insights</router-link>
          <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-violet-50 text-violet-700">ISAs</span>
          <span class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded-md uppercase tracking-wide bg-violet-50 text-violet-700">Savings</span>
        </div>
        <p class="text-xs text-neutral-400 mb-8">By Chris Slater-Jones, DipPFS &amp; Brett Isenberg, FCA &middot; Published on 8 April 2026</p>

        <article class="space-y-10">

          <!-- Intro -->
          <div>
            <p class="text-sm text-neutral-500 leading-relaxed italic">
              Individual Savings Accounts (ISAs) are one of the most tax-efficient ways to save and invest in the UK. With an annual allowance of &pound;20,000, you can earn interest, dividends and investment gains completely free of tax. This comprehensive guide walks you through everything you need to know about ISAs in 2026 &mdash; from how they work to which type suits your financial goals.
            </p>
          </div>

          <!-- What Is an ISA? -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">What is an ISA?</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              An ISA is a tax-free savings or investment account that lets you earn interest, dividends and capital gains without paying tax. The name stands for Individual Savings Account &mdash; only you can hold and manage your own ISA.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              ISAs have been a cornerstone of UK personal finance since 1999. They were created to encourage saving and investing, and today millions of people use them as part of their financial strategy. The beauty of an ISA is its simplicity: you save or invest within your annual allowance, and anything you earn is tax-free. No tax returns needed, no forms to fill out &mdash; the tax efficiency is automatic.
            </p>
          </div>

          <!-- ISA Allowance 2026/27 -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">The ISA allowance 2026/27</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              For the 2026/27 tax year, your ISA allowance is &pound;20,000 per tax year. This is your total limit across all ISAs combined &mdash; you cannot save or invest more than &pound;20,000 per year in ISAs.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              The allowance resets on 6 April each year. If you do not use your full &pound;20,000 in one year, you cannot carry it forward to the next. Unlike pension allowances, any unused ISA allowance is lost permanently.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              The &pound;20,000 is your combined limit across all types of ISA. For example, you could put &pound;20,000 into a Cash ISA, or split &pound;10,000 into a Cash ISA and &pound;10,000 into a Stocks and Shares ISA. The Lifetime ISA has its own annual limit of &pound;4,000, but this counts towards your overall &pound;20,000 allowance. The government's 25% bonus on Lifetime ISA contributions sits outside the allowance.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Junior ISAs have a separate allowance of &pound;9,000 per child per tax year, completely independent of the adult &pound;20,000 limit.
            </p>
          </div>

          <!-- ISA Types Explained -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">ISA types explained</h2>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">Cash ISA</h3>
            <p class="text-sm text-neutral-500 leading-relaxed mb-2">
              A Cash ISA is the simplest type. You deposit money and it earns interest, just like a regular savings account, but completely tax-free.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-2">
              <strong class="text-horizon-500">Best for:</strong> People who want guaranteed returns, those saving for a short-term goal, and anyone uncomfortable with investment risk.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              <strong class="text-horizon-500">Key features:</strong> No investment risk &mdash; your capital is safe. Interest rates vary by provider, so shop around. Some Cash ISAs are flexible, allowing you to withdraw and replace money within the same tax year without losing your allowance. Perfect for emergency funds or saving for a deposit.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">Stocks and Shares ISA</h3>
            <p class="text-sm text-neutral-500 leading-relaxed mb-2">
              A Stocks and Shares ISA lets you invest in stocks, bonds, funds, exchange-traded funds (ETFs) and other investments. Any growth, dividends and capital gains are all tax-free.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-2">
              <strong class="text-horizon-500">Best for:</strong> Long-term investors, people with a higher risk tolerance, and those investing for retirement or distant goals.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              <strong class="text-horizon-500">Key features:</strong> You can invest in individual shares, funds, investment trusts, ETFs and more. Dividends earned are tax-free &mdash; a significant advantage given that dividend tax outside an ISA starts at 8.75%. Capital gains are tax-free, saving you up to 20% Capital Gains Tax on profits. Your capital is at risk, but over long time periods (10+ years) equities have historically outperformed cash.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">Lifetime ISA</h3>
            <p class="text-sm text-neutral-500 leading-relaxed mb-2">
              A Lifetime ISA is designed for two purposes: saving for your first home or saving for retirement. The government adds a 25% bonus to everything you save &mdash; up to &pound;1,000 per year on the maximum &pound;4,000 contribution.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-2">
              <strong class="text-horizon-500">Best for:</strong> First-time homebuyers saving for a deposit, and young people saving for retirement.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              <strong class="text-horizon-500">Key rules:</strong> You must open a Lifetime ISA before turning 40, but can continue contributing until age 50. You can withdraw penalty-free for a first home (under &pound;450,000) or after age 60. Withdrawing for any other reason before age 60 incurs a 25% withdrawal charge, which typically wipes out the bonus and eats into your capital.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">Innovative Finance ISA</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              An Innovative Finance ISA lets you invest in peer-to-peer loans and earn tax-free interest. Returns can be higher than Cash ISAs, but your capital is at risk if borrowers default. This is a higher-risk option and not suitable for everyone.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">Junior ISA</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              A Junior ISA is for children under 18, with a separate &pound;9,000 annual limit. Parents, guardians or relatives can contribute. The money is locked until the child turns 18. Can be a Cash or Stocks and Shares Junior ISA.
            </p>
          </div>

          <!-- Comparison Table -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">ISA types comparison</h2>
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left border-collapse">
                <thead>
                  <tr class="bg-horizon-500 text-white">
                    <th class="px-4 py-2 font-semibold rounded-tl-lg">ISA Type</th>
                    <th class="px-4 py-2 font-semibold">Annual Limit</th>
                    <th class="px-4 py-2 font-semibold">Best For</th>
                    <th class="px-4 py-2 font-semibold">Risk Level</th>
                    <th class="px-4 py-2 font-semibold rounded-tr-lg">Key Benefit</th>
                  </tr>
                </thead>
                <tbody class="text-neutral-500">
                  <tr class="border-b border-neutral-200">
                    <td class="px-4 py-2 font-medium text-horizon-500">Cash ISA</td>
                    <td class="px-4 py-2">&pound;20,000*</td>
                    <td class="px-4 py-2">Short-term saving, safety</td>
                    <td class="px-4 py-2">Very Low</td>
                    <td class="px-4 py-2">Guaranteed tax-free returns</td>
                  </tr>
                  <tr class="border-b border-neutral-200 bg-eggshell-500/50">
                    <td class="px-4 py-2 font-medium text-horizon-500">Stocks and Shares ISA</td>
                    <td class="px-4 py-2">&pound;20,000*</td>
                    <td class="px-4 py-2">Long-term investing</td>
                    <td class="px-4 py-2">Medium&ndash;High</td>
                    <td class="px-4 py-2">Tax-free dividends and gains</td>
                  </tr>
                  <tr class="border-b border-neutral-200">
                    <td class="px-4 py-2 font-medium text-horizon-500">Lifetime ISA</td>
                    <td class="px-4 py-2">&pound;4,000*</td>
                    <td class="px-4 py-2">First-home buyers, young savers</td>
                    <td class="px-4 py-2">Low&ndash;Medium</td>
                    <td class="px-4 py-2">25% government bonus</td>
                  </tr>
                  <tr class="border-b border-neutral-200 bg-eggshell-500/50">
                    <td class="px-4 py-2 font-medium text-horizon-500">Innovative Finance ISA</td>
                    <td class="px-4 py-2">&pound;20,000*</td>
                    <td class="px-4 py-2">Higher return seekers</td>
                    <td class="px-4 py-2">High</td>
                    <td class="px-4 py-2">Tax-free peer-to-peer returns</td>
                  </tr>
                  <tr>
                    <td class="px-4 py-2 font-medium text-horizon-500">Junior ISA</td>
                    <td class="px-4 py-2">&pound;9,000**</td>
                    <td class="px-4 py-2">Saving for children</td>
                    <td class="px-4 py-2">Low&ndash;Medium</td>
                    <td class="px-4 py-2">Long-term tax-free growth</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="text-xs text-neutral-400 mt-2">*Counts towards the &pound;20,000 combined limit. **Separate allowance.</p>
          </div>

          <!-- ISA Rules -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">ISA rules you need to know</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-3">
              <li><strong class="text-horizon-500">One of each type per tax year:</strong> You can only pay into one Cash ISA, one Stocks and Shares ISA, one Innovative Finance ISA and one Lifetime ISA per tax year. However, you can hold multiple ISAs from previous years and transfer between providers freely.</li>
              <li><strong class="text-horizon-500">You must be a UK resident</strong> to open and contribute to an ISA. If you move abroad, you can keep existing ISAs but cannot contribute.</li>
              <li><strong class="text-horizon-500">Flexible ISAs:</strong> Some ISAs are &ldquo;flexible,&rdquo; meaning you can withdraw money and put it back in the same tax year without using additional allowance. Not all ISAs offer this &mdash; check with your provider.</li>
              <li><strong class="text-horizon-500">No carry forward:</strong> Unlike pension allowances, unused ISA allowance cannot be carried forward. Use it or lose it.</li>
              <li><strong class="text-horizon-500">Transfers are not new contributions:</strong> Moving an existing ISA to a new provider via a formal transfer does not count as a new contribution or use your annual allowance. Never withdraw and re-deposit &mdash; that uses your allowance.</li>
            </ul>
          </div>

          <!-- How to Choose -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">How to choose the right ISA</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Match your ISA type to your goal and time horizon. For short-term goals (1&ndash;3 years), a Cash ISA provides certainty. For medium-term goals (3&ndash;10 years), consider a mix of Cash and Stocks and Shares. For long-term goals (10+ years), a Stocks and Shares ISA typically offers better returns. For first-time home buying, a Lifetime ISA is hard to beat.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Compare interest rates on Cash ISAs &mdash; a 0.5% difference on &pound;20,000 is &pound;100 per year. For Stocks and Shares ISAs, compare platform fees &mdash; some charge 0.2%, others 0.75%. Over 20 years, that fee difference compounds significantly.
            </p>
          </div>

          <!-- ISAs vs Pensions -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">ISAs vs pensions</h2>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Both are tax-efficient, but they serve different purposes. ISAs offer tax-free growth and flexible access at any time. Pensions offer tax relief on contributions (up to 45%) but are locked until age 55 (rising to 57 from 6 April 2028). ISAs have a &pound;20,000 annual limit; pensions allow up to &pound;60,000. If your employer matches pension contributions, prioritise that first &mdash; it is free money. Then use ISAs for medium-term goals or additional retirement saving. For most people, a combination of both is optimal.
            </p>
          </div>

          <!-- Quote callout -->
          <blockquote class="border-l-4 border-raspberry-500 bg-light-pink-100 rounded-r-xl px-6 py-5 my-2">
            <p class="text-lg font-semibold text-horizon-500 italic leading-relaxed">
              "The real power of ISAs becomes apparent over long time horizons. &pound;20,000 invested at 7% annual growth over 20 years grows to &pound;77,394 &mdash; with zero tax liability."
            </p>
          </blockquote>

          <!-- Common Mistakes -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Common ISA mistakes</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-3">
              <li><strong class="text-horizon-500">Not using your full allowance</strong> &mdash; it resets yearly and is lost forever if unused.</li>
              <li><strong class="text-horizon-500">Choosing the wrong type for your timeline</strong> &mdash; investing in stocks when you need the money in 2 years is risky.</li>
              <li><strong class="text-horizon-500">Paying unnecessary fees</strong> &mdash; on a &pound;20,000 Stocks and Shares ISA growing at 7% over 20 years, a platform charging 0.2% leaves you with roughly &pound;73,900, while one charging 0.75% leaves you with around &pound;67,500. That 0.55% difference costs you over &pound;6,400 from a single year&rsquo;s contribution alone.</li>
              <li><strong class="text-horizon-500">Withdrawing and re-depositing instead of transferring</strong> &mdash; this wastes your annual allowance.</li>
              <li><strong class="text-horizon-500">Opening a Lifetime ISA without understanding the withdrawal penalty</strong> &mdash; the 25% charge is harsh if you need the money early.</li>
              <li><strong class="text-horizon-500">Not reviewing your ISA annually</strong> &mdash; interest rates and fees change, and better deals appear regularly.</li>
            </ul>
          </div>

          <!-- Real-World Scenarios -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">ISAs in practice: real-world scenarios</h2>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">You have more than &pound;20,000 to save</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Maximise your ISA allowance first. Put the full &pound;20,000 into your ISA, choosing a Stocks and Shares ISA if you can leave it invested for 5+ years, or a Cash ISA if you need accessibility. The remainder should go into a regular savings account or other investment vehicles. This strategy prioritises the most tax-efficient wrapper for as much money as possible, leaving only the excess in taxable accounts.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">First-time buyer saving for a deposit</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              If you are a first-time buyer aged 18&ndash;39, the Lifetime ISA is almost always the right choice. Contribute the maximum &pound;4,000 per year to receive the government&rsquo;s &pound;1,000 bonus (a guaranteed 25% return). Use the remaining &pound;16,000 of your annual allowance in a Cash ISA for flexibility and certainty &mdash; you need the money accessible and do not want stock market risk close to your purchase date.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">Long-term investor in their 30s</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              First, always maximise employer pension contributions to capture the match &mdash; this is free money. Then allocate your full &pound;20,000 ISA allowance to a Stocks and Shares ISA invested in a diversified, low-cost global index fund. Your long time horizon means you can tolerate stock market volatility, and market downturns become opportunities to buy shares at lower prices.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">Self-employed without a workplace pension</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Contribute what you can afford to a Self-Invested Personal Pension to get tax relief at your marginal rate (20&ndash;45%). Then use your ISA allowance for medium-term accessible savings &mdash; perhaps a mix of Cash ISA and Stocks and Shares ISA depending on timelines. This two-pronged approach gives you both the upfront tax relief of a pension and the flexibility and tax-free growth of an ISA.
            </p>
          </div>

          <!-- Power of Tax-Free Growth -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">The power of tax-free growth</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Consider a worked example: &pound;20,000 invested in a Stocks and Shares ISA at 7% annual growth over 20 years grows to &pound;77,394, with zero tax liability. The same &pound;20,000 in a regular investment account would face Capital Gains Tax at 20% on the profit of approximately &pound;57,394 &mdash; that is around &pound;11,479 in tax. The ISA advantage: over &pound;11,000 more in your pocket.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              This demonstrates why ISAs become exponentially more powerful the longer you hold them. Early contributions have decades to compound tax-free, making ISAs ideal for long-term savers and investors.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              The advantage extends beyond capital gains tax. With Stocks and Shares ISAs, dividend tax is also eliminated. Outside an ISA, dividends above the &pound;500 annual dividend allowance (2026/27) are taxed at 8.75% (basic rate), 33.75% (higher rate), or 39.35% (additional rate). These seemingly small taxes compound into substantial amounts over decades.
            </p>
          </div>

          <!-- FAQ -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Frequently asked questions</h2>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">Can I have multiple ISAs?</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Yes, but only one of each type per tax year. You can have one Cash ISA, one Stocks and Shares ISA, one Lifetime ISA, and one Innovative Finance ISA open at the same time during a tax year. However, you can hold multiple ISAs from previous years and keep them all running simultaneously.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">What happens if I move abroad?</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              You cannot contribute to an ISA once you move abroad, as ISAs are only available to UK residents. However, any existing ISAs you have already opened will continue to be held in your name and remain tax-free.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">Can I open an ISA for my spouse?</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              No, ISAs are strictly individual accounts. However, your spouse can open their own ISAs and use their own &pound;20,000 annual allowance. A married couple can collectively save or invest &pound;40,000 per year across their combined ISAs.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">What is the difference between flexible and non-flexible ISAs?</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              Flexible ISAs allow you to withdraw money and replace it within the same tax year without using additional allowance. Non-flexible ISAs do not offer this feature &mdash; any withdrawal permanently uses that portion of your allowance. Flexible ISAs are better for emergency access.
            </p>

            <h3 class="text-lg font-semibold text-horizon-500 mb-2 mt-6">Is there a minimum contribution?</h3>
            <p class="text-sm text-neutral-500 leading-relaxed">
              The minimum varies by provider. Some allow as little as &pound;1, while others require &pound;500 or more. Many providers allow multiple deposits throughout the year, so you do not need to contribute the full amount upfront.
            </p>
          </div>

          <!-- How Fynla Can Help -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">How Fynla can help</h2>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              Fynla helps you see your ISAs alongside your pensions, savings and investments in a single dashboard. You can track your ISA contributions against your annual allowance, model different savings scenarios, and see how your ISAs fit into your broader financial plan.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              <strong class="text-horizon-500">Plan together:</strong> Fynla also lets you link your account with your spouse or partner, so both of you can see the full household picture and plan your ISA and pension strategy together.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed mb-3">
              <strong class="text-horizon-500">Powered by Fynla Brain&reg;:</strong> Our Fynla Brain&reg; runs the calculations behind the scenes &mdash; modelling growth projections, tax efficiency and different allocation strategies &mdash; so you can focus on making decisions, not building spreadsheets.
            </p>
            <p class="text-sm text-neutral-500 leading-relaxed">
              <strong class="text-horizon-500">Track your ISAs as part of your overall net worth:</strong> You can store all your ISAs alongside pensions, savings, investments and property in one place, giving you a clear picture of your total net worth and how your tax-free ISA savings fit into your wider financial plan.
            </p>
          </div>

          <!-- Key Takeaways -->
          <div class="bg-light-blue-100 rounded-2xl p-6">
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Key takeaways</h2>
            <ul class="text-sm text-neutral-500 leading-relaxed list-disc pl-5 space-y-2">
              <li>ISAs let you earn interest, dividends and capital gains completely tax-free.</li>
              <li>Your annual allowance is &pound;20,000, shared across all ISA types. Use it or lose it.</li>
              <li>There are four main types: Cash ISA, Stocks and Shares ISA, Lifetime ISA and Innovative Finance ISA, plus Junior ISAs for children.</li>
              <li>Match the ISA type to your goal: Cash for short-term, Stocks and Shares for long-term, Lifetime ISA for first homes.</li>
              <li>You can only pay into one of each type per tax year, but can transfer freely between providers.</li>
              <li>The Lifetime ISA offers a 25% government bonus but carries a harsh 25% withdrawal penalty for early access.</li>
              <li>Compare fees and interest rates &mdash; small differences compound to thousands over time.</li>
              <li>Combine ISAs with pensions for optimal tax efficiency.</li>
            </ul>
          </div>

          <!-- Related Links -->
          <div class="bg-light-blue-100 rounded-2xl p-6">
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Related</h2>
            <ul class="space-y-2">
              <li>
                <router-link to="/insights/retirement-planning-uk" class="text-sm text-raspberry-500 hover:underline">The Complete Guide to Retirement Planning in the UK</router-link>
              </li>
              <li>
                <router-link to="/insights/isa-allowance-2025-26" class="text-sm text-raspberry-500 hover:underline">ISA Allowance 2025/26: What You Need to Know</router-link>
              </li>
              <li>
                <router-link to="/learn/what-is-an-isa" class="text-sm text-raspberry-500 hover:underline">What Is an ISA?</router-link>
              </li>
              <li>
                <router-link to="/features/pension-tracker" class="text-sm text-raspberry-500 hover:underline">Pension Tracker</router-link>
              </li>
            </ul>
          </div>

          <!-- Sources -->
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-horizon-500 mb-3">Sources</h2>
            <ul class="text-xs text-neutral-400 leading-relaxed list-disc pl-5 space-y-1">
              <li>GOV.UK &mdash; ISA guidance: gov.uk/individual-savings-accounts</li>
              <li>MoneyHelper &mdash; ISA information: moneyhelper.org.uk/en/savings/isas</li>
              <li>GOV.UK &mdash; Lifetime ISA rules: gov.uk/lifetime-isa</li>
              <li>GOV.UK &mdash; Increasing Normal Minimum Pension Age: gov.uk/government/publications/increasing-normal-minimum-pension-age</li>
              <li>GOV.UK &mdash; Junior ISA: gov.uk/junior-individual-savings-accounts</li>
            </ul>
          </div>

          <!-- Disclaimer -->
          <div class="border-t border-neutral-200 pt-6">
            <p class="text-xs text-neutral-400 leading-relaxed">
              <strong>Disclaimer:</strong> This article is for informational and educational purposes only. It does not constitute personalised financial advice. ISA rules and tax treatment depend on individual circumstances and may change. The value of investments in a Stocks and Shares ISA can go down as well as up, and you may get back less than you invest. If you are unsure about your savings or investment options, consider speaking to a qualified financial adviser.
            </p>
          </div>
        </article>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-12 bg-light-pink-100">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-horizon-500 mb-3">See how your ISAs fit into your financial plan</h2>
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
  name: 'IsaGuideUkPage',
  components: { PublicLayout },

  mounted() {
    document.title = 'The Ultimate Guide to ISAs in the UK: Types, Rules & Best Options | Fynla';
  },
};
</script>
