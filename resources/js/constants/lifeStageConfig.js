/**
 * Fynla Life Stage Configuration
 *
 * Central configuration for all 5 UK financial planning life stages.
 * This is the single source of truth for stage-adaptive behaviour across
 * the entire application: sidebar, dashboard, onboarding, forms, and learning.
 *
 * Referenced by:
 *   - store/modules/lifeStage.js (state management)
 *   - components/SideMenu.vue (adaptive sidebar)
 *   - views/Dashboard.vue (stage-curated cards)
 *   - components/Onboarding/OnboardingWizard.vue (step sequence)
 *   - components/Onboarding/LearningMilestoneSidebar.vue (educational content)
 *   - All unified form components (field visibility)
 *
 * Spec: March/March17Updates/life-stage-journey-design.md
 * Design guide: fynlaDesignGuide.md v1.2.0
 */

// =============================================================================
// LIFE STAGE DEFINITIONS
// =============================================================================

export const LIFE_STAGES = {

  // ---------------------------------------------------------------------------
  // STAGE 1: Starting Out (university / 18–25)
  // Colour: violet-500
  // Persona: student (Janice Taylor, 21)
  // ---------------------------------------------------------------------------
  university: {
    id: 'university',
    label: 'Starting Out',
    tagline: 'Build smart money habits from day one',
    ageRange: '18–25',
    persona: 'student',
    icon: 'graduation-cap',
    colour: 'violet',

    sidebar: {
      primary: [
        'dashboard',
        'bank-accounts',
        'income',
        'expenditure',
        'savings',
        'goals',
        'risk-profile',
      ],
      explore: [
        'investments',
        'retirement',
        'property',
        'protection',
        'will',
        'estate',
        'plans',
        'business',
        'trusts',
        'chattels',
      ],
    },

    dashboard: {
      cards: [
        'budget-tracker',
        'student-loan',
        'savings',
        'goals',
        'life-timeline',
      ],
    },

    onboarding: {
      steps: [
        'personal-info',
        'student-loan',
        'income',
        'expenditure',
        'savings',
        'goals',
      ],
      learningMilestones: {
        'personal-info': {
          didYouKnow: 'Your date of birth determines when you\'ll start repaying your student loan and when you\'ll be automatically enrolled in a workplace pension (from age 22). Getting these dates right means Fynla can flag the exact month things change for you.',
          whyWeAsk: 'Your age and gender affect life expectancy projections, pension eligibility dates, and ISA and Lifetime ISA access rules.',
          howItFits: 'As a student, we keep things simple — no need for address or health details yet. We focus on what matters now: your loan, your income, and building your first financial habits.',
          quickStat: { value: '22', label: 'Age you\'ll be automatically enrolled in a workplace pension' },
        },
        'student-loan': {
          didYouKnow: 'Student loans are not like normal debt. Repayments are income-based and written off after 40 years (Plan 5). The majority of graduates on Plan 5 will never fully repay their loan — meaning the balance written off is never a real cash cost to them.',
          whyWeAsk: 'Your loan plan type determines your repayment threshold, interest rate, and write-off date. This affects whether voluntarily overpaying ever makes financial sense — in most cases, it does not.',
          howItFits: 'Understanding your loan frees you up mentally. Rather than stress about the balance, you can focus on building an emergency fund or opening a Lifetime ISA instead.',
          quickStat: { value: '£27,295', label: 'Plan 5 annual repayment threshold (2025/26)' },
        },
        'income': {
          didYouKnow: 'The average UK student faces a monthly shortfall of £400–600 between income and outgoings. Knowing exactly what comes in — whether from a part-time job, maintenance loan, or family support — is the single most important first step to closing that gap.',
          whyWeAsk: 'Whether it\'s a part-time job, placement salary, maintenance loan, or parental support — knowing what comes in lets us build a realistic spending plan and calculate how long your money will last each term.',
          howItFits: 'Income tracking is the foundation of your budget. Once you know what comes in each month, you can make conscious choices about what goes out — rather than wondering where it all went.',
          quickStat: { value: '£400–600', label: 'Average monthly student income shortfall' },
        },
        'expenditure': {
          didYouKnow: 'Most students overspend by £400–600 per month without realising it. The culprits are usually small daily purchases — coffees, subscriptions, and takeaways — that feel harmless individually but add up fast. Tracking is not about restriction: it is about making conscious choices.',
          whyWeAsk: 'We use your spending to calculate your emergency fund target (three months of expenses) and to identify areas where small reductions could meaningfully accelerate your savings goals.',
          howItFits: 'A clear picture of your spending is the foundation that everything else builds on — savings goals, budgets, and financial confidence. You cannot manage what you cannot see.',
          quickStat: null,
        },
        'savings': {
          didYouKnow: 'Even saving £25 per month from age 21 instead of age 30 could mean tens of thousands more by retirement, thanks to compound interest. Time in the market matters more than the amount — starting small and starting now is always better than waiting.',
          whyWeAsk: 'Knowing your existing accounts lets us track your emergency fund progress, calculate the interest you\'re earning, and monitor how much of your annual ISA allowance you\'ve used.',
          howItFits: 'Your first goal is an emergency fund — three months of living costs. That\'s your safety net before thinking about investing or anything else. Once that\'s in place, a Lifetime ISA for your future home or retirement is a natural next step.',
          quickStat: { value: '£20,000', label: 'Your annual ISA allowance (2025/26)' },
        },
        'goals': {
          didYouKnow: 'People who write down specific, time-bound financial goals are 42% more likely to achieve them than those who simply intend to save. A goal without a deadline is just a wish.',
          whyWeAsk: 'Clear goals let us calculate realistic monthly savings amounts, track your progress on the dashboard, and remind you when you are ahead or behind plan.',
          howItFits: 'Graduate debt-free? Build an emergency fund? Save for a car? Your goals give your money a purpose. Even one clear goal transforms how you think about everyday spending decisions.',
          quickStat: null,
        },
      },
    },

    suggestedGoals: [
      {
        id: 'emergency-fund',
        label: 'Build an emergency fund',
        description: '3 months of living costs',
      },
      {
        id: 'save-for-car',
        label: 'Save for a car',
        description: 'Set a target and timeline',
      },
      {
        id: 'graduate-debt-free',
        label: 'Graduate debt-free',
        description: 'Beyond your student loan',
      },
      {
        id: 'travel-fund',
        label: 'Travel fund',
        description: 'Post-graduation plans',
      },
    ],

    formFields: {
      personalInfo: {
        always: ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone'],
        stage: ['education_level', 'university', 'student_number'],
        onboardingHide: [
          'address_line_1', 'address_line_2', 'city', 'county', 'postcode',
          'marital_status', 'occupation', 'employer', 'employment_status', 'industry',
          'target_retirement_age', 'health_status', 'smoking_status',
          'country_of_birth', 'domicile_status',
        ],
      },
      income: {
        always: ['employment_status'],
        stage: ['part_time_income', 'maintenance_loan', 'parental_support', 'bursary_grant'],
        onboardingHide: [
          'annual_employment_income', 'annual_self_employment_income',
          'annual_rental_income', 'dividend_income',
          'pension_income', 'state_pension',
        ],
      },
      savings: {
        defaultTypes: ['current_account', 'easy_access', 'instant_access', 'cash_isa'],
        emergencyFundProminent: true,
        hideOwnership: true,
        isaGuidanceContext: 'student-lisa-first-home',
      },
      protection: {
        showInOnboarding: false,
        defaultPolicyTypes: [],
        simplifiedBeneficiary: false,
      },
    },

    learning: {
      pensionContext: 'auto-enrolment-from-22',
      savingsContext: 'emergency-fund-basics',
      debtContext: 'student-loan-not-like-debt',
    },
  },

  // ---------------------------------------------------------------------------
  // STAGE 2: Building Foundations (early_career / 23–35)
  // Colour: spring-500
  // Persona: young_saver (John Morgan, ~28)
  // ---------------------------------------------------------------------------
  early_career: {
    id: 'early_career',
    label: 'Building Foundations',
    tagline: 'Save for your first home and grow your career',
    ageRange: '23–35',
    persona: 'young_saver',
    icon: 'briefcase',
    colour: 'spring',

    sidebar: {
      primary: [
        'dashboard',
        'bank-accounts',
        'income',
        'expenditure',
        'savings',
        'investments',
        'retirement',
        'goals',
      ],
      explore: [
        'property',
        'protection',
        'risk-profile',
        'will',
        'estate',
        'plans',
        'business',
        'trusts',
        'chattels',
      ],
    },

    dashboard: {
      cards: [
        'net-worth',
        'savings',
        'investments',
        'retirement',
        'goals',
        'life-timeline',
      ],
    },

    onboarding: {
      steps: [
        'personal-info',
        'income-career',
        'savings-emergency',
        'first-home-lisa',
        'pension-auto-enrolment',
        'investments',
        'goals',
      ],
      learningMilestones: {
        'personal-info': {
          didYouKnow: 'Your early career is one of the most financially formative periods of your life. The habits and accounts you open now — pensions, ISAs, emergency funds — will compound over decades. Getting the foundations right at 25 is worth more than trying to catch up at 45.',
          whyWeAsk: 'Your age, employment status, and occupation shape your pension auto-enrolment eligibility, tax band, National Insurance contributions, and the financial milestones most relevant to your stage.',
          howItFits: 'Building foundations means locking in good habits early: a six-month emergency fund, a growing workplace pension, and a clear plan for your first home. Everything starts with knowing where you are today.',
          quickStat: { value: '£60,000', label: 'Annual pension allowance you can contribute tax-free (2025/26)' },
        },
        'income-career': {
          didYouKnow: 'Every £1 of salary sacrifice into your pension saves you income tax AND National Insurance. At the basic rate, that means an immediate 32p in every £1 you contribute is money that would otherwise have gone to HMRC. Your employer contributions are free money on top.',
          whyWeAsk: 'Your gross salary determines your income tax band, pension contribution amounts, student loan repayments, and the maximum you can contribute to a Lifetime ISA each year.',
          howItFits: 'Understanding your full income picture — salary, any side income, and employer benefits — means we can identify exactly how much you can realistically save and invest each month after tax.',
          quickStat: { value: '£12,570', label: 'Personal Allowance — income you pay no tax on (2025/26)' },
        },
        'savings-emergency': {
          didYouKnow: 'A six-month emergency fund is the single most important financial protection you can have. It means a redundancy, car breakdown, or boiler failure does not derail your longer-term plans. Keep it in an easy-access savings account earning competitive interest — not a current account.',
          whyWeAsk: 'Knowing your savings lets us calculate exactly how many months of expenses you currently have covered, track progress towards your emergency fund target, and flag whether you\'re earning the best available interest rate.',
          howItFits: 'Your emergency fund is the foundation beneath everything else. Once it\'s in place, every pound you save beyond it can be invested with confidence — you\'re not one bad month away from having to sell investments.',
          quickStat: { value: '6 months', label: 'Recommended emergency fund target for this life stage' },
        },
        'first-home-lisa': {
          didYouKnow: 'The Lifetime ISA is one of the most powerful first-home savings tools available. You can save up to £4,000 per year and the government adds a 25% bonus — that\'s up to £1,000 free per year. You must open it before your 40th birthday and can use it for a first home worth up to £450,000.',
          whyWeAsk: 'If you\'re saving for your first home, knowing your current deposit progress lets us calculate how long it will take to reach your target, whether a Lifetime ISA makes sense, and the likely stamp duty on your target purchase.',
          howItFits: 'A house deposit is typically the largest savings goal of your twenties and early thirties. Mapping a clear timeline — with and without a Lifetime ISA bonus — turns an abstract aspiration into a concrete, trackable plan.',
          quickStat: { value: '£1,000', label: 'Maximum annual government bonus on a Lifetime ISA' },
        },
        'pension-auto-enrolment': {
          didYouKnow: 'Auto-enrolment means your employer must contribute to your pension if you earn above £10,000 per year. The minimum total contribution is 8% of qualifying earnings (employee + employer). Opting out is almost always a mistake — you\'re walking away from free money.',
          whyWeAsk: 'Your pension details let us project your retirement income, assess whether you\'re on track, and calculate how increasing contributions now — even by 1% — compounds into significant extra income in retirement.',
          howItFits: 'You don\'t need to understand everything about pensions right now. The key point for your stage: contribute at least enough to get your full employer match, then focus on your house deposit. Revisit pension maximisation in your late thirties.',
          quickStat: { value: '8%', label: 'Minimum total pension contribution under auto-enrolment (2025/26)' },
        },
        'investments': {
          didYouKnow: 'A Stocks & Shares ISA lets you invest up to £20,000 per year with all growth and income completely free of tax — forever. Investing £200/month from age 28 at a 7% average annual return would be worth over £500,000 by age 65.',
          whyWeAsk: 'Knowing your existing investments lets us assess diversification, flag tax inefficiency (investments held outside an ISA), and incorporate your portfolio into your net worth and retirement projections.',
          howItFits: 'Investing is for money you won\'t need for at least five years. The typical order of priority is: emergency fund → pension match → Lifetime ISA (if buying) → Stocks & Shares ISA. Once your foundations are in place, investing for the long term is the most powerful wealth-builder available.',
          quickStat: { value: '£20,000', label: 'Annual ISA allowance — all growth tax-free (2025/26)' },
        },
        'goals': {
          didYouKnow: 'The most financially successful people at this life stage share one habit: they pay themselves first. They set up automatic transfers on payday so saving happens before spending starts. The amount matters less than the habit.',
          whyWeAsk: 'Clear goals with timelines let us calculate exact monthly savings requirements, track your progress, and show you the compounding power of consistency over time.',
          howItFits: 'Your goals at this stage typically cluster around three themes: security (emergency fund), homeownership (deposit), and future wealth (pension + investments). Prioritising them clearly means you make progress on all three simultaneously.',
          quickStat: null,
        },
      },
    },

    suggestedGoals: [
      {
        id: 'house-deposit-lisa',
        label: 'House deposit',
        description: 'Save into a Lifetime ISA for the 25% bonus',
      },
      {
        id: 'six-month-emergency-fund',
        label: '6-month emergency fund',
        description: 'Your financial safety net',
      },
      {
        id: 'start-investing-isa',
        label: 'Start investing',
        description: 'Stocks & Shares ISA — tax-free growth',
      },
      {
        id: 'wedding-fund',
        label: 'Wedding fund',
        description: 'Plan ahead with a dedicated savings goal',
      },
    ],

    formFields: {
      personalInfo: {
        always: ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone'],
        stage: ['marital_status', 'occupation', 'employer', 'employment_status'],
        onboardingHide: [
          'address_line_1', 'address_line_2', 'city', 'county', 'postcode',
          'education_level', 'university', 'student_number',
          'target_retirement_age', 'health_status', 'smoking_status',
          'country_of_birth', 'domicile_status',
        ],
      },
      income: {
        always: ['employment_status'],
        stage: ['annual_employment_income', 'occupation', 'employer'],
        onboardingHide: [
          'part_time_income', 'maintenance_loan', 'parental_support', 'bursary_grant',
          'annual_self_employment_income', 'annual_rental_income',
          'dividend_income', 'pension_income', 'state_pension',
        ],
      },
      savings: {
        defaultTypes: ['easy_access', 'cash_isa', 'lifetime_isa', 'fixed_rate', 'current_account'],
        emergencyFundProminent: true,
        hideOwnership: false,
        isaGuidanceContext: 'early-career-lisa-and-s-and-s',
      },
      protection: {
        showInOnboarding: true,
        optional: true,
        defaultPolicyTypes: ['life'],
        simplifiedBeneficiary: true,
        mortgageProtectionProminent: false,
      },
    },

    learning: {
      pensionContext: 'auto-enrolment-match-first',
      savingsContext: 'emergency-fund-then-lisa',
      investingContext: 'isa-long-term-compounding',
      homeContext: 'lifetime-isa-first-home-bonus',
    },
  },

  // ---------------------------------------------------------------------------
  // STAGE 3: Protecting What Matters (mid_career / 31–50)
  // Colour: raspberry-500
  // Personas: young_family (James & Emily Carter), entrepreneur (Alex Chen)
  // ---------------------------------------------------------------------------
  mid_career: {
    id: 'mid_career',
    label: 'Protecting What Matters',
    tagline: 'Secure your family and grow your wealth',
    ageRange: '31–50',
    persona: 'young_family',
    icon: 'shield',
    colour: 'raspberry',

    sidebar: {
      primary: [
        'dashboard',
        'property',
        'protection',
        'investments',
        'retirement',
        'will',
        'bank-accounts',
        'goals',
      ],
      explore: [
        'income',
        'expenditure',
        'savings',
        'estate',
        'risk-profile',
        'plans',
        'business',
        'trusts',
        'chattels',
      ],
    },

    dashboard: {
      cards: [
        'net-worth',
        'protection',
        'cash-savings',
        'investments',
        'retirement',
        'estate',
        'goals',
        'life-timeline',
      ],
    },

    onboarding: {
      steps: [
        'personal-info',
        'family',
        'income',
        'property-mortgage',
        'protection-insurance',
        'pensions',
        'will-estate',
        'goals',
      ],
      learningMilestones: {
        'personal-info': {
          didYouKnow: 'At this life stage, your financial plan needs to protect the people who depend on you as much as it needs to grow your wealth. The two are inseparable. A gap in your protection can undo decades of careful saving in a single event.',
          whyWeAsk: 'Your age, health status, and smoking status directly affect the cost and availability of life insurance, critical illness cover, and income protection — the three pillars of a family financial safety net.',
          howItFits: 'This stage is about balance: building wealth for the future whilst making sure your family is protected today. We\'ll ask about both so your plan covers all angles.',
          quickStat: { value: '1 in 2', label: 'UK adults will develop cancer during their lifetime — critical illness cover matters' },
        },
        'family': {
          didYouKnow: 'Having dependants is the single biggest trigger for needing life insurance and income protection. A rule of thumb: your life cover should be at least 10 times your annual income, plus any outstanding mortgage balance. Most people are significantly underinsured.',
          whyWeAsk: 'The number, ages, and needs of your dependants determine how much protection you need, your likely childcare and education costs, and whether you should consider a family income benefit policy alongside a lump sum policy.',
          howItFits: 'Your family profile shapes every aspect of your financial plan — from protection gaps and pension planning to inheritance tax and will writing. Getting this right means your plan truly fits your life.',
          quickStat: { value: '10×', label: 'Recommended minimum life cover multiple of annual salary' },
        },
        'income': {
          didYouKnow: 'Income protection insurance pays a monthly benefit if you cannot work due to illness or injury — typically 50–70% of your income. Yet fewer than 10% of UK workers have it. The state safety net (Statutory Sick Pay at £116.75/week for up to 28 weeks) is unlikely to cover your mortgage or rent.',
          whyWeAsk: 'Your income level determines your protection needs, pension contribution capacity, and the size of any income protection policy that would maintain your family\'s standard of living if you were unable to work.',
          howItFits: 'At this stage your income is probably at or near its highest rate of growth. Protecting it — through income protection insurance and a solid emergency fund — is as important as investing it.',
          quickStat: { value: '£116.75', label: 'Weekly Statutory Sick Pay — rarely enough to cover a mortgage' },
        },
        'property-mortgage': {
          didYouKnow: 'Most homeowners overpay their mortgage by making minimum repayments when they could be using offset mortgages, or by not reviewing their rate every two years. Even a 0.5% rate reduction on a £250,000 mortgage saves over £1,200 per year.',
          whyWeAsk: 'Your property and mortgage details let us calculate your equity, net worth, potential remortgage savings, and whether a decreasing term life policy should be tied to your outstanding balance.',
          howItFits: 'Property is likely your largest asset and your mortgage your largest liability. Getting both right — competitive rate, appropriate protection, optimal repayment strategy — has more impact than almost any other financial decision.',
          quickStat: { value: '2 years', label: 'How often you should review your mortgage rate to avoid paying over the odds' },
        },
        'protection-insurance': {
          didYouKnow: 'Life insurance is cheaper than most people think. A healthy 35-year-old can get £500,000 of level term cover for 25 years for around £20–30 per month. Waiting five years to buy the same policy typically costs 30–50% more due to age and health changes.',
          whyWeAsk: 'Reviewing your existing policies lets us identify gaps — cover that has lapsed, policies with inadequate sums assured, missing beneficiary nominations, or protection you\'re paying for but may not need.',
          howItFits: 'At this life stage, protection is not optional — it is foundational. Life insurance, critical illness cover, and income protection work together to ensure a financial crisis does not become a family catastrophe.',
          quickStat: { value: '£20–30/month', label: 'Typical cost of £500,000 life cover for a healthy 35-year-old' },
        },
        'pensions': {
          didYouKnow: 'The pension annual allowance for 2025/26 is £60,000. You can also carry forward up to three years of unused allowance, potentially contributing up to £200,000+ in a single year. For higher earners, salary sacrifice into a pension is one of the most tax-efficient strategies available.',
          whyWeAsk: 'Your pension balances and contribution history let us project your retirement income, identify whether you\'re on track, and flag opportunities to increase contributions in a tax-efficient way — particularly if you\'ve had high-earning years with low contributions.',
          howItFits: 'The thirties and forties are the golden decade for pension building. Contributions made now have 20–30 years to compound. This is the time to review your contribution rate, fund choices, and whether consolidating old workplace pensions makes sense.',
          quickStat: { value: '£60,000', label: 'Annual pension allowance (2025/26) — use it or lose it' },
        },
        'will-estate': {
          didYouKnow: 'If you die without a will, the intestacy rules determine who inherits your estate — and these rules can leave your partner without the legal right to your home if you\'re not married. Over 60% of UK adults do not have a will. With dependants, this is not a risk worth taking.',
          whyWeAsk: 'Knowing whether you have a will and basic estate details lets us assess whether your estate would face an inheritance tax liability and whether your assets would pass to the right people in the right way.',
          howItFits: 'Estate planning at this stage is not about being morbid — it\'s about being responsible. A will, lasting powers of attorney, and appropriate protection policies give your family security regardless of what happens.',
          quickStat: { value: '60%', label: 'UK adults without a valid will — do not be one of them' },
        },
        'goals': {
          didYouKnow: 'Research consistently shows that families who set shared financial goals are significantly more likely to build wealth and significantly less likely to experience financial stress. Goals work because they align daily decisions with long-term outcomes.',
          whyWeAsk: 'Goals at this stage often span multiple timeframes and priorities: paying off the mortgage early, funding education, retiring at a certain age, and closing protection gaps. Mapping them together lets us show you which is most urgent and how progress on one affects the others.',
          howItFits: 'At this stage you\'re likely managing more competing financial priorities than at any other. Goals give you a framework for making trade-off decisions — when money is tight, you know which priority comes first.',
          quickStat: null,
        },
      },
    },

    suggestedGoals: [
      {
        id: 'pay-off-mortgage-early',
        label: 'Pay off mortgage early',
        description: 'Reduce the term and save thousands in interest',
      },
      {
        id: 'childrens-education-fund',
        label: 'Children\'s education fund',
        description: 'University or private schooling costs',
      },
      {
        id: 'retire-at-60',
        label: 'Retire at 60',
        description: 'Plan the pension contributions needed today',
      },
      {
        id: 'close-protection-gaps',
        label: 'Close protection gaps',
        description: 'Full life, critical illness, and income protection cover',
      },
    ],

    formFields: {
      personalInfo: {
        always: ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone'],
        stage: [
          'marital_status', 'occupation', 'employer', 'employment_status',
          'health_status', 'smoking_status', 'target_retirement_age',
        ],
        onboardingHide: [
          'address_line_1', 'address_line_2', 'city', 'county', 'postcode',
          'education_level', 'university', 'student_number',
          'country_of_birth', 'domicile_status',
        ],
      },
      income: {
        always: ['employment_status'],
        stage: [
          'annual_employment_income', 'occupation', 'employer',
          'annual_rental_income', 'annual_self_employment_income',
        ],
        onboardingHide: [
          'part_time_income', 'maintenance_loan', 'parental_support', 'bursary_grant',
          'dividend_income', 'pension_income', 'state_pension',
        ],
      },
      savings: {
        defaultTypes: ['easy_access', 'cash_isa', 'fixed_rate', 'notice', 'current_account'],
        emergencyFundProminent: false,
        hideOwnership: false,
        isaGuidanceContext: 'mid-career-isa-standard',
      },
      protection: {
        showInOnboarding: true,
        optional: false,
        defaultPolicyTypes: ['life', 'critical_illness', 'income_protection'],
        simplifiedBeneficiary: false,
        beneficiaryDependantContext: true,
        mortgageProtectionProminent: true,
      },
    },

    learning: {
      pensionContext: 'pension-growth-phase-maximise',
      savingsContext: 'emergency-fund-and-isa-growth',
      protectionContext: 'family-protection-gaps',
      propertyContext: 'remortgage-and-overpayment-strategy',
      estateContext: 'will-writing-with-dependants',
    },
  },

  // ---------------------------------------------------------------------------
  // STAGE 4: Planning Your Future (peak / 46–65)
  // Colour: light-blue-500
  // Persona: peak_earners (David & Sarah Mitchell, late 40s)
  // ---------------------------------------------------------------------------
  peak: {
    id: 'peak',
    label: 'Planning Your Future',
    tagline: 'Maximise your wealth and prepare for retirement',
    ageRange: '46–65',
    persona: 'peak_earners',
    icon: 'chart-line',
    colour: 'light-blue',

    sidebar: {
      primary: [
        'dashboard',
        'investments',
        'retirement',
        'property',
        'estate',
        'protection',
        'plans',
        'goals',
      ],
      explore: [
        'bank-accounts',
        'income',
        'expenditure',
        'savings',
        'will',
        'risk-profile',
        'business',
        'trusts',
        'chattels',
      ],
    },

    dashboard: {
      cards: [
        'net-worth',
        'retirement',
        'investments',
        'estate',
        'protection',
        'tax-allowances',
        'goals',
        'life-timeline',
      ],
    },

    onboarding: {
      steps: [
        'personal-info',
        'income-tax',
        'pension-review',
        'investments-isa',
        'property-portfolio',
        'estate-iht',
        'goals',
      ],
      learningMilestones: {
        'personal-info': {
          didYouKnow: 'Your late forties and fifties are the most financially consequential decade of your life. Earnings are typically at their peak, the mortgage is shrinking, children are becoming independent — and every year of focused planning now can mean five to ten extra years of financial freedom later.',
          whyWeAsk: 'Your target retirement age, health status, and employment situation are the three most important inputs in projecting whether you can retire when you want to, at the standard of living you expect.',
          howItFits: 'At this stage the question shifts from "am I saving?" to "am I saving enough, in the right places, tax-efficiently?" We need the full picture to answer that honestly.',
          quickStat: { value: '57', label: 'Minimum pension access age rising to in 2028 — check your plans' },
        },
        'income-tax': {
          didYouKnow: 'Higher-rate taxpayers can claim 40% tax relief on pension contributions — meaning a £1,000 net contribution costs just £600. Salary sacrifice is even more efficient as it also saves National Insurance. If you\'re not maximising pension contributions at higher-rate income levels, you\'re leaving significant money with HMRC.',
          whyWeAsk: 'Your income level determines your tax band, available allowances, the value of pension tax relief, and whether strategies like carry-forward, pension salary sacrifice, or dividend extraction are worth exploring.',
          howItFits: 'Tax efficiency at this stage can add tens of thousands to your retirement pot. Understanding your income and tax position is the foundation for a highly effective final-decade saving strategy.',
          quickStat: { value: '40%', label: 'Tax relief on pension contributions for higher-rate taxpayers' },
        },
        'pension-review': {
          didYouKnow: 'You can carry forward up to three years of unused pension annual allowance, potentially contributing £180,000+ in a single year. If you\'ve had years of lower contributions — or have recently seen income increase significantly — this could be a powerful catch-up mechanism.',
          whyWeAsk: 'Reviewing all your pensions — workplace, personal, defined benefit, and defined contribution — lets us build an accurate retirement income projection and identify consolidation opportunities, lost pensions, and contribution optimisation strategies.',
          howItFits: 'The final decade before retirement is when small improvements compound the most. Reviewing your pensions now — rather than at 63 — gives you time to act on what you find.',
          quickStat: { value: '£180,000+', label: 'Maximum pension contribution using carry-forward (3 years + current year)' },
        },
        'investments-isa': {
          didYouKnow: 'ISA gains and income are tax-free forever — there is no capital gains tax or income tax on ISA returns, even in retirement. Building up a large ISA alongside your pension gives you tax-free income flexibility in retirement, as ISA withdrawals do not count towards income for tax purposes.',
          whyWeAsk: 'Your investment portfolio needs to be reviewed for tax efficiency, risk alignment with your timeline, and diversification. Investments held outside an ISA or pension may be generating unnecessary tax liabilities that can easily be addressed.',
          howItFits: 'At this stage, the goal is not just growth — it is tax-efficient growth. Moving towards a portfolio that minimises your tax burden in retirement (pension + ISA combination) can meaningfully increase net retirement income.',
          quickStat: { value: '£3,000', label: 'Annual Capital Gains Tax exemption (2025/26) — use it each year' },
        },
        'property-portfolio': {
          didYouKnow: 'Property remains the largest asset for most UK households, but it is also the least tax-efficient in many respects. Capital gains tax on a buy-to-let sale can be 24% for higher-rate taxpayers. Planning property disposals carefully — including timing, ownership structure, and use of exemptions — can save substantial sums.',
          whyWeAsk: 'Your property portfolio — including your main home and any investment properties — is central to your net worth, retirement income planning, and inheritance tax position. We need the full picture to plan effectively.',
          howItFits: 'Many people at this stage discover their net worth is heavily concentrated in property. Diversification into pensions and ISAs, careful mortgage management, and estate planning around property can significantly improve the overall efficiency of your wealth.',
          quickStat: { value: '24%', label: 'Capital Gains Tax rate on residential property for higher-rate taxpayers (2025/26)' },
        },
        'estate-iht': {
          didYouKnow: 'Inheritance tax is charged at 40% on estates above the nil-rate band (£325,000) and, for qualifying properties passing to direct descendants, the residence nil-rate band (£175,000). For a married couple, the combined threshold can be up to £1 million — but only with careful planning of how assets are held and transferred.',
          whyWeAsk: 'Your estate value and beneficiary intentions determine whether you have an inheritance tax liability and, if so, which strategies — lifetime gifts, trusts, pension nominations, or charitable giving — are most appropriate for your situation.',
          howItFits: 'At this stage you likely have significant wealth and, for the first time, an estate that may face inheritance tax. Getting estate planning right now — while you have time and capacity to act — can save your beneficiaries hundreds of thousands of pounds.',
          quickStat: { value: '£500,000', label: 'Nil-rate band + residence nil-rate band for a single individual (2025/26)' },
        },
        'goals': {
          didYouKnow: 'The clearest predictor of a successful retirement transition is not the size of your pension pot — it is whether you have a clear picture of what retirement costs for the life you want to live. The PLSA Retirement Living Standards benchmark suggests a comfortable retirement for a couple costs around £59,000 per year.',
          whyWeAsk: 'Your goals at this stage — retire by a certain age, downsize, fund education for grandchildren, leave an inheritance — determine the target size of your retirement pot and the strategies needed to reach it.',
          howItFits: 'Your goals are the destination your financial plan is navigating towards. The more precisely you define them, the more precisely we can show you whether you\'re on course — and what to adjust if you\'re not.',
          quickStat: { value: '£59,000', label: 'Annual cost of a comfortable retirement for a couple (PLSA, 2025)' },
        },
      },
    },

    suggestedGoals: [
      {
        id: 'maximise-pension-contributions',
        label: 'Maximise pension contributions',
        description: 'Use carry-forward and salary sacrifice',
      },
      {
        id: 'downsize-property',
        label: 'Downsize property',
        description: 'Release equity and reduce costs',
      },
      {
        id: 'fund-care-costs',
        label: 'Fund care costs',
        description: 'Plan for later-life care needs',
      },
      {
        id: 'leave-inheritance',
        label: 'Leave an inheritance',
        description: 'Minimise inheritance tax, maximise legacy',
      },
    ],

    formFields: {
      personalInfo: {
        always: ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone'],
        stage: [
          'marital_status', 'occupation', 'employer', 'employment_status',
          'health_status', 'smoking_status', 'target_retirement_age',
        ],
        onboardingHide: [
          'address_line_1', 'address_line_2', 'city', 'county', 'postcode',
          'education_level', 'university', 'student_number',
          'country_of_birth', 'domicile_status',
        ],
      },
      income: {
        always: ['employment_status'],
        stage: [
          'annual_employment_income', 'occupation', 'employer',
          'annual_rental_income', 'annual_self_employment_income',
          'dividend_income',
        ],
        onboardingHide: [
          'part_time_income', 'maintenance_loan', 'parental_support', 'bursary_grant',
          'pension_income', 'state_pension',
        ],
      },
      savings: {
        defaultTypes: ['easy_access', 'cash_isa', 'fixed_rate', 'notice', 'premium_bonds'],
        emergencyFundProminent: false,
        hideOwnership: false,
        isaGuidanceContext: 'peak-isa-tax-efficient-drawdown',
      },
      protection: {
        showInOnboarding: true,
        optional: false,
        defaultPolicyTypes: ['life', 'critical_illness', 'income_protection', 'whole_of_life'],
        simplifiedBeneficiary: false,
        beneficiaryDependantContext: false,
        mortgageProtectionProminent: false,
      },
    },

    learning: {
      pensionContext: 'pension-maximise-carry-forward',
      savingsContext: 'isa-drawdown-tax-efficiency',
      investingContext: 'de-risking-timeline-to-retirement',
      estateContext: 'iht-planning-and-gifts',
      taxContext: 'higher-rate-relief-salary-sacrifice',
    },
  },

  // ---------------------------------------------------------------------------
  // STAGE 5: Enjoying Your Wealth (retirement / 65+)
  // Colour: horizon-500
  // Persona: retired_couple (Robert & Patricia Williams / Patricia & Harold Bennett)
  // ---------------------------------------------------------------------------
  retirement: {
    id: 'retirement',
    label: 'Enjoying Your Wealth',
    tagline: 'Make your money last and leave a legacy',
    ageRange: '65+',
    persona: 'retired_couple',
    icon: 'sun',
    colour: 'horizon',

    sidebar: {
      primary: [
        'dashboard',
        'retirement',
        'estate',
        'investments',
        'property',
        'trusts',
        'plans',
        'goals',
      ],
      explore: [
        'bank-accounts',
        'savings',
        'protection',
        'will',
        'income',
        'expenditure',
        'risk-profile',
        'business',
        'chattels',
      ],
    },

    dashboard: {
      cards: [
        'net-worth',
        'retirement-income',
        'estate',
        'investments',
        'tax-allowances',
        'goals',
        'life-timeline',
      ],
    },

    onboarding: {
      steps: [
        'personal-info',
        'pension-drawdown',
        'state-pension',
        'income-tax',
        'estate-legacy',
        'goals',
      ],
      learningMilestones: {
        'personal-info': {
          didYouKnow: 'Retirement is not a financial finish line — it is a new beginning that could last 20–30 years or more. Life expectancy for a 65-year-old woman in the UK is now over 87; for a man, over 84. Your financial plan needs to sustain your lifestyle for decades, not just years.',
          whyWeAsk: 'Your age, health status, and marital situation determine your State Pension amounts, pension access rules, inheritance tax position, and the longevity assumptions we use in your income projections.',
          howItFits: 'At this stage, the plan shifts from accumulation to distribution. We focus on making your money last, generating sustainable income, and ensuring your legacy is structured exactly as you intend.',
          quickStat: { value: '87+', label: 'Average life expectancy for a 65-year-old woman in the UK today' },
        },
        'pension-drawdown': {
          didYouKnow: 'Flexi-access drawdown lets you take as much or as little as you like from your pension each year — but getting the amount right is critical. Withdraw too much and you may pay unnecessary tax (pension income is taxable above your Personal Allowance). Withdraw too little and you may leave money in an estate that faces inheritance tax, as unused pension funds from 2027 will be included in your estate for inheritance tax purposes.',
          whyWeAsk: 'The type, value, and structure of your pension determines your income options, tax planning strategy, and the most efficient sequencing of withdrawals from different account types.',
          howItFits: 'Drawdown strategy is the single most important financial decision in retirement. Done well, it can reduce your income tax bill by tens of thousands over 20 years. We need to understand your pension fully before making recommendations.',
          quickStat: { value: '25%', label: 'Maximum tax-free cash lump sum you can take from a defined contribution pension' },
        },
        'state-pension': {
          didYouKnow: 'The full new State Pension is £11,502.40 per year (2025/26). It is uprated annually by the triple lock (highest of earnings growth, CPI inflation, or 2.5%), making it one of the most valuable guaranteed income streams available. Every year of National Insurance contributions matters — you can check and fill gaps in your record via the government\'s Check Your State Pension tool.',
          whyWeAsk: 'Your State Pension amount and start date are foundational inputs in your retirement income model. Combined with your private pensions, they determine the total guaranteed income you can rely on each year.',
          howItFits: 'State Pension income is index-linked and cannot be outlived — it is the foundation of your retirement income. Once we know this figure, we can calculate exactly how much additional income your private pensions and investments need to generate.',
          quickStat: { value: '£11,502', label: 'Full new State Pension annual amount (2025/26)' },
        },
        'income-tax': {
          didYouKnow: 'Retirement offers significant tax planning opportunities that employed workers cannot access. By carefully sequencing withdrawals from ISAs (tax-free), pensions (taxable above Personal Allowance), and other savings, couples can structure retirement income to pay minimal income tax whilst maintaining a high standard of living.',
          whyWeAsk: 'Understanding your full income picture in retirement — pensions, State Pension, rental income, dividends — lets us identify the most tax-efficient withdrawal strategy and flag any allowances you may not be using.',
          howItFits: 'Tax efficiency in retirement is not about evasion — it is about sequencing. Using ISA withdrawals to top up pension income to the Personal Allowance, for example, can save thousands in unnecessary tax each year.',
          quickStat: { value: '£12,570', label: 'Personal Allowance — income you pay no tax on (2025/26)' },
        },
        'estate-legacy': {
          didYouKnow: 'Inheritance tax raised £7.5 billion for HMRC in 2024/25 — much of it paid by families who could have avoided it with earlier planning. The most effective strategies (lifetime gifts, trusts, pension nominations, charitable giving) all require time to implement. Starting now, whilst your health and capacity are strong, gives you the most options.',
          whyWeAsk: 'Your estate value, existing will, trust arrangements, and legacy intentions determine whether your estate will face an inheritance tax liability and which planning strategies are most appropriate.',
          howItFits: 'Legacy planning is not about hoarding wealth — it is about ensuring the people and causes you care about receive what you intend, in the most efficient way possible. A clear estate plan gives both you and your beneficiaries certainty and peace of mind.',
          quickStat: { value: '40%', label: 'Inheritance tax rate on estates above the nil-rate band threshold' },
        },
        'goals': {
          didYouKnow: 'Retirees with a clear sense of financial purpose — specific goals like funding grandchildren\'s education, travelling each year, or leaving a defined legacy — report significantly higher levels of wellbeing than those who simply "try to make the money last". Goals in retirement are just as important as goals at any other stage.',
          whyWeAsk: 'Your goals shape your income requirements, spending patterns, and the priority of different planning strategies. A goal to leave a specific inheritance requires different planning to a goal of maximising lifetime income.',
          howItFits: 'Goals in retirement are your anchor. They tell us what "success" looks like for your financial plan — not just a number on a spreadsheet, but a life you have consciously chosen and structured your finances to support.',
          quickStat: null,
        },
      },
    },

    suggestedGoals: [
      {
        id: 'sustainable-income-target',
        label: 'Sustainable income target',
        description: 'Set a monthly income that your portfolio can support indefinitely',
      },
      {
        id: 'gift-to-family',
        label: 'Gift to family',
        description: 'Tax-efficient lifetime giving to reduce your estate',
      },
      {
        id: 'fund-care-needs',
        label: 'Fund care needs',
        description: 'Plan and ring-fence funds for later-life care',
      },
      {
        id: 'legacy-plan',
        label: 'Legacy plan',
        description: 'Define what you want to leave and to whom',
      },
    ],

    formFields: {
      personalInfo: {
        always: ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone'],
        stage: ['marital_status', 'health_status', 'smoking_status'],
        onboardingHide: [
          'address_line_1', 'address_line_2', 'city', 'county', 'postcode',
          'education_level', 'university', 'student_number',
          'occupation', 'employer', 'employment_status', 'industry',
          'target_retirement_age',
          'country_of_birth', 'domicile_status',
        ],
      },
      income: {
        always: [],
        stage: ['pension_income', 'state_pension', 'annual_rental_income', 'dividend_income'],
        onboardingHide: [
          'employment_status',
          'annual_employment_income', 'annual_self_employment_income',
          'part_time_income', 'maintenance_loan', 'parental_support', 'bursary_grant',
        ],
      },
      savings: {
        defaultTypes: ['easy_access', 'cash_isa', 'fixed_rate', 'notice', 'premium_bonds'],
        emergencyFundProminent: false,
        hideOwnership: false,
        isaGuidanceContext: 'retirement-isa-tax-free-income',
      },
      protection: {
        showInOnboarding: true,
        optional: true,
        defaultPolicyTypes: ['whole_of_life', 'funeral_plan'],
        simplifiedBeneficiary: false,
        beneficiaryDependantContext: false,
        mortgageProtectionProminent: false,
      },
    },

    learning: {
      pensionContext: 'drawdown-sequencing-and-tax',
      savingsContext: 'isa-tax-free-retirement-income',
      investingContext: 'capital-preservation-with-income',
      estateContext: 'iht-gifts-and-trust-strategies',
      incomeContext: 'state-pension-triple-lock-planning',
    },
  },
};

// =============================================================================
// STAGE ORDER & PERSONA MAPPING
// =============================================================================

/**
 * Canonical order of life stages, from earliest to latest.
 * Used for progress indicators, stage transition logic, and ordered iteration.
 */
export const STAGE_ORDER = [
  'university',
  'early_career',
  'mid_career',
  'peak',
  'retirement',
];

/**
 * Maps preview persona IDs to their life stage.
 * Used by the preview mode system to auto-set the active stage when a
 * preview persona is loaded (PersonaSelector, PreviewController).
 *
 * Spec §2.2: 6 personas across 5 stages (mid_career has 2 personas).
 */
export const PERSONA_TO_STAGE = {
  student: 'university',
  young_saver: 'early_career',
  young_family: 'mid_career',
  entrepreneur: 'mid_career',
  peak_earners: 'peak',
  retired_couple: 'retirement',
};
