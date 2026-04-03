<template>
  <PublicLayout>
    <!-- Hero -->
    <div class="relative flex items-center bg-gradient-to-r from-horizon-500 to-raspberry-500 overflow-hidden">
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-left w-full">
        <h1 class="text-4xl md:text-6xl font-black text-white mb-4">Glossary</h1>
        <p class="text-lg text-white/70">
          Financial terms explained in plain English.
        </p>
      </div>
    </div>

    <!-- Alphabet Nav -->
    <section class="bg-white border-b border-light-gray sticky top-16 z-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap gap-1.5 py-4">
          <a
            v-for="letter in activeLetters"
            :key="letter"
            :href="`#letter-${letter}`"
            class="w-9 h-9 flex items-center justify-center text-sm font-bold rounded-lg transition-colors"
            :class="glossary[letter] ? 'text-horizon-500 hover:bg-raspberry-100 hover:text-raspberry-500' : 'text-neutral-300 pointer-events-none'"
          >
            {{ letter }}
          </a>
        </div>
      </div>
    </section>

    <!-- Glossary Content -->
    <section class="py-12 bg-eggshell-500">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div v-for="letter in activeLetters" :key="letter">
          <template v-if="glossary[letter]">
            <div :id="`letter-${letter}`" class="pt-6 mb-4">
              <h2 class="text-2xl font-black text-raspberry-500 bg-light-pink-100 rounded-lg px-4 py-1.5">{{ letter }}</h2>
            </div>
            <div class="space-y-3 mb-8">
              <div
                v-for="term in glossary[letter]"
                :key="term.name"
                :id="term.anchor"
                class="bg-white rounded-lg border border-light-gray p-4"
              >
                <h3 class="text-base font-bold text-horizon-500 mb-1">{{ term.name }}</h3>
                <p class="text-sm text-neutral-600 leading-relaxed">{{ term.definition }}</p>
                <router-link
                  v-if="term.link"
                  :to="term.link.to"
                  class="inline-block mt-1.5 text-sm text-raspberry-500 hover:text-light-pink-400 transition-colors"
                >
                  {{ term.link.label }}
                </router-link>
              </div>
            </div>
          </template>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="py-10 bg-light-pink-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-r from-horizon-500 to-raspberry-500 rounded-xl px-6 py-6">
          <h2 class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-2">See these concepts in action</h2>
          <p class="text-lg text-white/70 mb-4">Fynla brings all of these terms to life in your personal financial plan.</p>
          <a href="#" @click.prevent="$router.push({ query: { demo: 'true' } })" class="inline-block px-6 py-2.5 bg-spring-500 text-white text-sm font-semibold rounded-lg hover:bg-spring-600 transition-colors">
            Try the demo
          </a>
        </div>
      </div>
    </section>
  </PublicLayout>
</template>

<script>
import PublicLayout from '@/layouts/PublicLayout.vue';
import { getCurrentTaxYear } from '@/utils/dateFormatter';

export default {
  name: 'GlossaryPage',
  components: { PublicLayout },

  data() {
    return {
      alphabet: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split(''),
    };
  },

  computed: {
    activeLetters() {
      return this.alphabet;
    },

    glossary() {
      return {
        A: [
          {
            name: 'Annuity',
            anchor: 'annuity',
            definition: 'An insurance product that converts your pension pot into a guaranteed income for life. Once purchased, the decision is irreversible. Rates vary depending on your age, health, and market conditions.',
            link: { to: '/learn/what-is-drawdown', label: 'Compare with drawdown' },
          },
          {
            name: 'Annual Allowance',
            anchor: 'annual-allowance',
            definition: 'The maximum amount you can contribute to pensions in a tax year while still receiving tax relief. Currently \u00A360,000 per year, though this is reduced for very high earners.',
            link: { to: '/features/pension-tracker', label: 'Track your pension contributions' },
          },
        ],
        B: [
          {
            name: 'Basic Rate',
            anchor: 'basic-rate',
            definition: `The 20% income tax band that applies to taxable income between \u00A312,571 and \u00A350,270 (${getCurrentTaxYear()}). Most UK taxpayers fall within this band.`,
            link: null,
          },
          {
            name: 'Beneficiary',
            anchor: 'beneficiary',
            definition: 'A person or organisation you nominate to receive a benefit, such as a pension pot or life insurance payout, after your death.',
            link: null,
          },
        ],
        C: [
          {
            name: 'Capital Gains Tax',
            anchor: 'capital-gains-tax',
            definition: 'Tax on the profit you make when you sell an asset (such as a second property or shares) for more than you paid for it. There is an annual tax-free allowance, and rates depend on the asset type and your income tax band.',
            link: null,
          },
          {
            name: 'Carry Forward',
            anchor: 'carry-forward',
            definition: 'A rule that lets you use any unused pension annual allowance from the previous three tax years, as long as you were a member of a pension scheme during those years.',
            link: null,
          },
          {
            name: 'Cash ISA',
            anchor: 'cash-isa',
            definition: 'A savings account where the interest is completely tax-free. Best suited for short-term goals or emergency funds where you need easy access to your money.',
            link: { to: '/learn/what-is-an-isa', label: 'Learn about ISAs' },
          },
          {
            name: 'Critical Illness Cover',
            anchor: 'critical-illness-cover',
            definition: 'An insurance policy that pays out a tax-free lump sum if you are diagnosed with a specified serious illness, such as cancer, heart attack, or stroke.',
            link: null,
          },
        ],
        D: [
          {
            name: 'Death in Service',
            anchor: 'death-in-service',
            definition: 'A workplace benefit that pays a lump sum (typically 2\u20134 times your salary) to your nominated beneficiaries if you die while employed. It is essentially free life insurance from your employer.',
            link: null,
          },
          {
            name: 'Defined Benefit Pension',
            anchor: 'defined-benefit-pension',
            definition: 'A pension scheme that promises a specific annual income in retirement, usually based on your salary and years of service. Also known as a final salary or career average pension. These are increasingly rare in the private sector.',
            link: { to: '/features/pension-tracker', label: 'Track your pensions' },
          },
          {
            name: 'Defined Contribution Pension',
            anchor: 'defined-contribution-pension',
            definition: 'A pension where you and your employer contribute money into a pot that is invested. The amount you get in retirement depends on how much was contributed and how the investments performed. Most workplace pensions are this type.',
            link: { to: '/features/pension-tracker', label: 'Track your pensions' },
          },
          {
            name: 'Drawdown',
            anchor: 'drawdown',
            definition: 'A way of taking income from your Defined Contribution pension while keeping it invested. You choose how much to withdraw and when, but you bear the risk of your pot running out.',
            link: { to: '/learn/what-is-drawdown', label: 'What is drawdown?' },
          },
        ],
        E: [
          {
            name: 'Emergency Fund',
            anchor: 'emergency-fund',
            definition: 'Money set aside in an easy-access account to cover unexpected expenses or a loss of income. A common target is 3\u20136 months of essential living costs.',
            link: { to: '/learn/guide/starting-out', label: 'Starting out guide' },
          },
          {
            name: 'Estate',
            anchor: 'estate',
            definition: 'Everything you own at the time of your death \u2014 property, savings, investments, pensions, and personal possessions \u2014 minus any debts. Your estate is what Inheritance Tax is calculated on.',
            link: null,
          },
        ],
        F: [
          {
            name: 'Financial Adviser (Independent Financial Adviser)',
            anchor: 'financial-adviser',
            definition: 'A qualified professional who gives personalised financial advice. An Independent Financial Adviser can recommend products from the whole market, unlike a restricted adviser who only covers certain providers.',
            link: null,
          },
        ],
        G: [
          {
            name: 'Gift Allowance (Annual Exemption)',
            anchor: 'gift-allowance',
            definition: 'You can give away \u00A33,000 per tax year without it counting towards your estate for Inheritance Tax purposes. Small gifts of up to \u00A3250 per person are also exempt.',
            link: null,
          },
          {
            name: 'Gross Income',
            anchor: 'gross-income',
            definition: 'Your total income before any deductions such as tax, National Insurance, or pension contributions.',
            link: null,
          },
        ],
        H: [
          {
            name: 'Higher Rate',
            anchor: 'higher-rate',
            definition: `The 40% income tax band that applies to taxable income between \u00A350,271 and \u00A3125,140 (${getCurrentTaxYear()}). If you earn in this band, you get less Personal Savings Allowance and more benefit from pension tax relief.`,
            link: null,
          },
        ],
        I: [
          {
            name: 'Income Protection',
            anchor: 'income-protection',
            definition: 'Insurance that pays a regular income (typically 50\u201370% of your salary) if you cannot work due to illness or injury. Unlike critical illness cover, it pays out monthly until you recover, reach retirement, or the policy ends.',
            link: null,
          },
          {
            name: 'Income Tax',
            anchor: 'income-tax',
            definition: 'Tax paid on your earnings above the Personal Allowance. The UK has progressive bands: basic rate (20%), higher rate (40%), and additional rate (45%).',
            link: null,
          },
          {
            name: 'Individual Savings Account (ISA)',
            anchor: 'isa',
            definition: 'A tax-free wrapper for savings or investments. You can contribute up to \u00A320,000 per tax year across all ISA types, and you pay no tax on any interest, dividends, or capital gains earned inside it.',
            link: { to: '/learn/what-is-an-isa', label: 'What is an ISA?' },
          },
          {
            name: 'Inheritance Tax',
            anchor: 'inheritance-tax',
            definition: 'Tax charged at 40% on the value of your estate above the nil rate band (\u00A3325,000). A residence nil rate band of \u00A3175,000 can apply if you leave your home to direct descendants.',
            link: { to: '/features/iht-planning', label: 'Inheritance Tax planning' },
          },
        ],
        L: [
          {
            name: 'Lasting Power of Attorney',
            anchor: 'lasting-power-of-attorney',
            definition: 'A legal document that lets you appoint someone to make decisions on your behalf if you lose mental capacity. There are two types: one for finances and one for health and welfare.',
            link: null,
          },
          {
            name: 'Lifetime Allowance (Abolished)',
            anchor: 'lifetime-allowance',
            definition: 'Previously a cap on the total amount you could hold in pensions without incurring a tax charge. It was abolished in April 2024, though some transitional protections remain.',
            link: null,
          },
          {
            name: 'Lifetime ISA',
            anchor: 'lifetime-isa',
            definition: 'An ISA for those aged 18\u201339, designed for saving towards a first home or retirement. The government adds a 25% bonus on contributions up to \u00A34,000 per year. Withdrawals for purposes other than a first home or retirement incur a 25% penalty.',
            link: { to: '/learn/what-is-an-isa', label: 'Learn about ISAs' },
          },
          {
            name: 'Loan to Value',
            anchor: 'loan-to-value',
            definition: 'The size of your mortgage as a percentage of the property\u2019s value. A \u00A3180,000 mortgage on a \u00A3200,000 property is 90% loan to value. Lower loan to value usually means better mortgage rates.',
            link: null,
          },
        ],
        M: [
          {
            name: 'Money Purchase Annual Allowance',
            anchor: 'money-purchase-annual-allowance',
            definition: 'A reduced pension annual allowance of \u00A310,000 that applies once you have flexibly accessed your Defined Contribution pension (e.g. through drawdown). This limits how much you can contribute going forward while still getting tax relief.',
            link: null,
          },
        ],
        N: [
          {
            name: 'National Insurance',
            anchor: 'national-insurance',
            definition: `A tax on earnings that funds the State Pension and other benefits. Employees pay 8% on earnings between \u00A312,570 and \u00A350,270, and 2% above that (${getCurrentTaxYear()} rates).`,
            link: null,
          },
          {
            name: 'Net Income',
            anchor: 'net-income',
            definition: 'Your income after all deductions \u2014 income tax, National Insurance, pension contributions, and student loan repayments. Also called take-home pay.',
            link: null,
          },
          {
            name: 'Net Worth',
            anchor: 'net-worth',
            definition: 'The total value of everything you own (assets) minus everything you owe (liabilities). It is the single best measure of your overall financial health.',
            link: { to: '/features/net-worth-dashboard', label: 'Track your net worth' },
          },
          {
            name: 'Nil Rate Band',
            anchor: 'nil-rate-band',
            definition: 'The threshold below which no Inheritance Tax is charged on your estate. Currently \u00A3325,000 per person, and it has been frozen at this level since 2009.',
            link: { to: '/features/iht-planning', label: 'Inheritance Tax planning' },
          },
        ],
        P: [
          {
            name: 'Pension Annual Allowance',
            anchor: 'pension-annual-allowance',
            definition: 'The maximum total pension contributions (from you, your employer, and any third party) that qualify for tax relief in a single tax year. Currently \u00A360,000, reduced for those earning over \u00A3260,000.',
            link: null,
          },
          {
            name: 'Personal Allowance',
            anchor: 'personal-allowance',
            definition: 'The amount of income you can earn each year before paying income tax. Currently \u00A312,570, though it reduces by \u00A31 for every \u00A32 earned above \u00A3100,000.',
            link: null,
          },
          {
            name: 'Personal Savings Allowance',
            anchor: 'personal-savings-allowance',
            definition: 'The amount of savings interest you can earn tax-free outside of an ISA. \u00A31,000 for basic rate taxpayers, \u00A3500 for higher rate, and \u00A30 for additional rate.',
            link: null,
          },
        ],
        R: [
          {
            name: 'Residence Nil Rate Band',
            anchor: 'residence-nil-rate-band',
            definition: 'An additional Inheritance Tax allowance of \u00A3175,000 that applies when you leave your main home to direct descendants (children or grandchildren). Combined with the nil rate band, a couple can potentially pass on \u00A31,000,000 tax-free.',
            link: { to: '/features/iht-planning', label: 'Inheritance Tax planning' },
          },
        ],
        S: [
          {
            name: 'Salary Sacrifice',
            anchor: 'salary-sacrifice',
            definition: 'An arrangement where you give up part of your salary in exchange for a non-cash benefit, most commonly extra pension contributions. You save on income tax and National Insurance, and your employer saves on National Insurance too.',
            link: null,
          },
          {
            name: 'Self-Invested Personal Pension (SIPP)',
            anchor: 'sipp',
            definition: 'A personal pension that gives you full control over how your money is invested. You choose from a wide range of funds, shares, and other investments rather than being limited to your employer\u2019s default options.',
            link: { to: '/learn/should-i-consolidate-pensions', label: 'Should I consolidate pensions?' },
          },
          {
            name: 'Sequence of Returns Risk',
            anchor: 'sequence-of-returns-risk',
            definition: 'The risk that poor investment returns early in retirement permanently reduce the amount your pot can sustain, even if returns recover later. This is the biggest risk for anyone in drawdown.',
            link: { to: '/learn/what-is-drawdown', label: 'What is drawdown?' },
          },
          {
            name: 'State Pension',
            anchor: 'state-pension',
            definition: `A regular payment from the government in retirement, based on your National Insurance record. The full new State Pension is \u00A3221.20 per week (${getCurrentTaxYear()}). You need 35 qualifying years for the full amount.`,
            link: null,
          },
          {
            name: 'Stocks and Shares ISA',
            anchor: 'stocks-and-shares-isa',
            definition: 'An ISA that lets you invest in funds, shares, bonds, and other assets with no tax on gains, dividends, or interest. Best suited for goals that are at least 5 years away.',
            link: { to: '/learn/what-is-an-isa', label: 'Learn about ISAs' },
          },
          {
            name: 'Student Loan',
            anchor: 'student-loan',
            definition: 'A government loan for tuition fees and maintenance. Repayments are income-contingent (you only pay above a salary threshold) and the balance is written off after 25\u201340 years depending on your plan type.',
            link: { to: '/calculators?calc=student-loan', label: 'Student loan calculator' },
          },
        ],
        T: [
          {
            name: 'Taper Relief',
            anchor: 'taper-relief',
            definition: 'A mechanism that reduces certain tax allowances as your income rises. For pensions, the annual allowance is reduced by \u00A31 for every \u00A32 of adjusted income above \u00A3260,000, down to a minimum of \u00A310,000.',
            link: null,
          },
          {
            name: 'Tax-Free Cash',
            anchor: 'tax-free-cash',
            definition: 'The portion of your pension you can take as a tax-free lump sum when you access it. For most people this is 25% of the pot, though some older pensions offer a higher percentage.',
            link: { to: '/learn/what-is-drawdown', label: 'What is drawdown?' },
          },
          {
            name: 'Tax Year',
            anchor: 'tax-year',
            definition: 'The UK tax year runs from 6 April to 5 April. Most tax allowances (ISA, pension, Capital Gains Tax) reset at the start of each tax year.',
            link: null,
          },
        ],
      };
    },
  },

  mounted() {
    document.title = 'Financial Planning Glossary A\u2013Z | Fynla';
  },
};
</script>
