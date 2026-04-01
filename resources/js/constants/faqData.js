/**
 * Centralised FAQ data — single source of truth.
 *
 * Every FAQ question/answer lives here. Pages import the items they need
 * via the helper functions at the bottom of the file.
 *
 * To add a new FAQ:
 *   1. Add it to the appropriate category in FAQ_CATEGORIES below.
 *   2. If it should appear on a feature page, add its `id` to the
 *      FEATURE_PAGE_FAQS mapping.
 *   3. If it should appear on the pricing page, add its `id` to
 *      PRICING_PAGE_FAQS.
 *   4. The main FAQ page (/faq) automatically shows ALL items.
 */

export const FAQ_CATEGORIES = [
  {
    id: 'about',
    label: 'About Fynla',
    items: [
      {
        id: 'what-is-fynla',
        q: 'What is Fynla?',
        a: 'Fynla is a UK financial planning platform that helps you see your complete financial picture — pensions, property, investments, protection, tax, and retirement planning — in one place. It\'s designed for people who want to plan their finances properly but don\'t want to pay thousands for an IFA.',
      },
      {
        id: 'is-fynla-financial-adviser',
        q: 'Is Fynla a financial adviser?',
        a: 'No. Fynla is a planning tool, not a regulated financial adviser. We help you understand your financial position, model scenarios, and make informed decisions — but we don\'t recommend specific financial products or give personalised financial advice. For complex situations, we\'d encourage you to use Fynla alongside a qualified adviser.',
      },
      {
        id: 'who-is-fynla-for',
        q: 'Who is Fynla for?',
        a: 'Anyone in the UK who wants to understand and plan their finances. We have features for every stage of life — from students building their first savings habits to pre-retirees planning drawdown strategies. Most of our users are people who know they should be planning but haven\'t had the right tools to do it properly.',
      },
      {
        id: 'different-from-budgeting-apps',
        q: 'How is Fynla different from budgeting apps like Emma or Plum?',
        a: 'Budgeting apps track where your money went (backward-looking). Fynla plans where your money is going (forward-looking). Fynla covers pensions, retirement projections, Inheritance Tax planning, protection analysis, and long-term financial modelling — things budgeting apps don\'t touch.',
      },
      {
        id: 'different-from-ifa',
        q: 'How is Fynla different from an IFA or wealth manager?',
        a: 'An IFA provides personalised advice and can execute transactions (buy products, transfer pensions). Fynla provides the planning, projections, and analysis — the part most people actually need — at a fraction of the cost. Many users find that Fynla either replaces their need for an IFA or makes their IFA sessions shorter and more productive.',
      },
      {
        id: 'is-fynla-tax-advice',
        q: 'Is Fynla giving me tax advice?',
        a: 'No. Fynla is a planning tool that helps you understand your position based on current UK tax rules. For complex estate planning, we\'d recommend consulting a qualified adviser — and Fynla gives you the numbers to make that conversation productive.',
      },
    ],
  },
  {
    id: 'getting-started',
    label: 'Getting Started',
    items: [
      {
        id: 'how-long-to-set-up',
        q: 'How long does it take to set up?',
        a: 'Most people complete their initial setup in 15-20 minutes. You\'ll add your key financial data — pensions, property, savings, insurance — and immediately see your dashboard. You can add more detail over time.',
      },
      {
        id: 'try-before-signing-up',
        q: 'Can I try it before signing up?',
        a: 'Yes. Our interactive demo lets you explore every feature using pre-built personas with realistic sample data. No account needed, no personal data required.',
      },
      {
        id: 'connect-bank-accounts',
        q: 'Do I need to connect my bank accounts?',
        a: 'No. Fynla doesn\'t require bank connections. You enter your data manually, which means you stay in full control and don\'t need to share banking credentials. We\'re exploring optional open banking integrations for the future.',
      },
      {
        id: 'what-data-needed',
        q: 'What data do I need to get started?',
        a: 'At minimum: your pension values (check provider statements or online portals), property value estimate, savings/investment balances, and any outstanding debts. The more complete your data, the more accurate your plan — but you can start with the basics and add detail over time.',
      },
      {
        id: 'how-value-property',
        q: 'How do I value my property?',
        a: 'Enter your best estimate of current market value. You can update this periodically — many people check Zoopla or Rightmove for comparable sales. Fynla doesn\'t auto-value property, so you stay in control of the estimate.',
      },
      {
        id: 'how-often-update',
        q: 'How often should I update my data?',
        a: 'Property and pension values: every 3-6 months. Savings and investment balances: monthly if you want precision, quarterly if you want simplicity. Fynla prompts you when data is getting stale.',
      },
    ],
  },
  {
    id: 'features',
    label: 'Features & Capabilities',
    items: [
      {
        id: 'what-features',
        q: 'What features does Fynla include?',
        a: 'Fynla has 32 features across financial planning, retirement, pensions, property, investments, protection, tax, and estate planning. Key highlights include: net worth dashboard, pension tracker, "When Can I Retire?" calculator, Inheritance Tax planning, protection gap analysis, Monte Carlo simulations, ICE letters, and Fyn AI assistant. See our features page for the full list.',
      },
      {
        id: 'what-is-fyn',
        q: 'What is Fyn?',
        a: 'Fyn is our AI assistant built into the platform. Ask Fyn any financial planning question in plain English — from "what\'s an ISA?" to "how does taper relief work?" — and get a clear, jargon-free answer. Fyn also helps you navigate the platform and understand your plan.',
      },
      {
        id: 'monte-carlo',
        q: 'Does Fynla do Monte Carlo simulations?',
        a: 'Yes (Premium tier). Fynla runs thousands of market scenarios against your financial plan to give you a probability-based confidence level rather than a single projection. This is the same approach used by professional financial planners.',
      },
      {
        id: 'monte-carlo-confidence',
        q: 'What confidence level should I aim for?',
        a: 'Financial planners generally consider 80%+ a solid plan and 90%+ a very robust one. Below 70% suggests meaningful risk. Fynla shows you what changes would improve your level.',
      },
      {
        id: 'monte-carlo-same-as-ifa',
        q: 'Is the Monte Carlo analysis the same as what an IFA would do?',
        a: 'Many IFAs use similar Monte Carlo tools — but charge \u00A31,000+ for the privilege. Fynla puts the same analytical power in your hands for a fraction of the cost.',
      },
      {
        id: 'monte-carlo-scenario-paths',
        q: 'Can I see individual scenario paths?',
        a: 'Yes. The fan chart shows the distribution, and you can explore specific scenario paths including worst-case, best-case, and median outcomes.',
      },
      {
        id: 'plan-as-couple',
        q: 'Can I plan as a couple?',
        a: 'Yes. A single Fynla subscription supports both individual and joint planning. See combined net worth, joint retirement income projections, household protection needs, and Inheritance Tax for your combined estate.',
      },
      {
        id: 'connect-pension-providers',
        q: 'Can Fynla connect directly to my pension providers?',
        a: 'Currently, you add your pension details manually — provider, value, contribution rate, and fund type. The manual setup takes about 5 minutes per pension.',
      },
      {
        id: 'state-pension-included',
        q: 'Does the retirement calculator include the state pension?',
        a: 'Yes. Enter your state pension forecast from gov.uk and Fynla incorporates it into your total retirement income projection.',
      },
      {
        id: 'defined-benefit-pensions',
        q: 'What about defined benefit (final salary) pensions?',
        a: 'Yes, Fynla supports defined benefit pensions. Enter the projected annual pension amount and retirement age, and it\'s included in your retirement income modelling.',
      },
      {
        id: 'consolidate-pensions',
        q: 'Should I consolidate my pensions?',
        a: 'That depends on your specific situation — fees, fund performance, guaranteed benefits, and employer match all factor in. Fynla gives you the data to make that decision.',
      },
      {
        id: 'retirement-date-accuracy',
        q: 'How accurate is the retirement date?',
        a: 'The calculation is only as good as the data you provide. Fynla uses evidence-based defaults for growth, inflation, and longevity — but lets you adjust all of them. The Monte Carlo analysis gives you a probability range rather than a single number.',
      },
      {
        id: 'inflation-accounted',
        q: 'Does the retirement calculator account for inflation?',
        a: 'Yes. All projections are shown in today\'s money by default, so you can understand what your retirement income actually buys.',
      },
      {
        id: 'early-retirement-fire',
        q: 'What about early retirement / FIRE?',
        a: 'Fynla is particularly useful for early retirement planning because it models the income bridge between stopping work and accessing state pension, including changing spending patterns and sequence of returns risk.',
      },
      {
        id: 'model-part-time',
        q: 'Can I model part-time work / semi-retirement?',
        a: 'Yes. You can model reduced income over a transition period — for example, earning \u00A320,000/year from part-time work between ages 58-63, then fully retiring.',
      },
      {
        id: 'does-fynla-sell-insurance',
        q: 'Does Fynla sell insurance?',
        a: 'No. Fynla calculates your protection gap — we don\'t sell insurance products, earn commissions, or recommend specific providers.',
      },
      {
        id: 'insurance-types-difference',
        q: 'What\'s the difference between life insurance, income protection, and critical illness?',
        a: 'Life insurance pays out when you die. Income protection replaces your income if you can\'t work. Critical illness pays a lump sum if diagnosed with a specified condition. You may need all three.',
      },
      {
        id: 'state-benefits-factored',
        q: 'Does the protection analysis factor in state benefits?',
        a: 'Yes. Fynla includes bereavement support payment, child benefit, and statutory sick pay in the calculations. But these are typically far less than most families need.',
      },
      {
        id: 'death-in-service',
        q: 'What about my employer\'s death-in-service benefit?',
        a: 'Add it in. Most employers offer 2-4x salary. Fynla includes this in your existing cover and shows the remaining gap.',
      },
      {
        id: 'partner-works-protection',
        q: 'My partner works — does that change my protection needs?',
        a: 'Absolutely. A dual-income household has a different protection need. Fynla models both scenarios and accounts for the surviving partner\'s income.',
      },
      {
        id: 'iht-2027-changes',
        q: 'Does Fynla cover the April 2027 pension Inheritance Tax changes?',
        a: 'Yes. Our Inheritance Tax planning suite (Premium tier) lets you model your estate under both current rules and the post-April 2027 rules where unused pension pots are included in your estate for Inheritance Tax purposes.',
      },
      {
        id: 'iht-accuracy',
        q: 'How accurate is the Inheritance Tax calculation?',
        a: 'Fynla applies current HMRC rules including all standard bands, taper thresholds, and exemptions. Accuracy depends on the completeness of your asset data.',
      },
      {
        id: 'iht-married-couples',
        q: 'Does Inheritance Tax planning work for married couples?',
        a: 'Yes. Fynla models transferable nil rate bands, residence nil rate band, and joint estate planning. You can see your position individually and as a couple.',
      },
      {
        id: 'trusts-bpr',
        q: 'What about trusts and business property relief?',
        a: 'Fynla covers the most common planning strategies including gifting, pension drawdown, and standard exemptions. For complex trust structures, we\'d recommend using Fynla\'s numbers alongside professional advice.',
      },
      {
        id: 'ice-letter-contents',
        q: 'What is included in the ICE letter?',
        a: 'The In Case of Emergency letter is a comprehensive summary of your entire financial life — accounts, policies, pensions, property, debts, contacts, and instructions — designed so your family can quickly understand and access everything if the worst happens.',
      },
      {
        id: 'ice-auto-updated',
        q: 'Is the ICE letter automatically kept up to date?',
        a: 'Yes. As you update your financial data in Fynla, the ICE letter reflects those changes automatically. You can download a fresh copy at any time.',
      },
      {
        id: 'ice-customise',
        q: 'Can I customise the ICE letter?',
        a: 'Yes. You can add personal notes, instructions, and wishes that go beyond the financial data. Some people include messages to family members, funeral preferences, or specific instructions.',
      },
      {
        id: 'ice-who-to-give',
        q: 'Who should I give the ICE letter to?',
        a: 'At minimum, your partner/spouse and your solicitor. Some people also give a copy to an adult child or trusted family member. Store a physical copy in a fireproof safe or with your will.',
      },
      {
        id: 'ice-vs-will',
        q: 'Do I need a will as well as an ICE letter?',
        a: 'Yes. An ICE letter complements a will — it doesn\'t replace one. Your will is the legal document that determines who gets what. Your ICE letter is the practical guide that helps your family navigate the process.',
      },
      {
        id: 'net-worth-vs-wealth',
        q: 'Is net worth the same as wealth?',
        a: 'Net worth is a snapshot of assets minus liabilities at a point in time. It doesn\'t account for your earning capacity, pension income rights, or state benefits — but it\'s the best single measure of your financial position.',
      },
    ],
  },
  {
    id: 'pricing',
    label: 'Pricing & Plans',
    items: [
      {
        id: 'how-much',
        q: 'How much does Fynla cost?',
        a: 'Student tier from approximately \u00A33/month, Standard at \u00A38.50/month, and Premium at \u00A320/month. Annual billing gives you a discount. No hidden fees, no commission, no lock-in. See our pricing page for full details.',
      },
      {
        id: 'plan-differences',
        q: 'What is the difference between the plans?',
        a: 'Each tier adds more depth. Student covers budgeting, savings, and basic pension tracking. Standard adds full retirement projections, property, investments, and protection analysis. Premium adds Monte Carlo simulations, Inheritance Tax planning, ICE letters, and advanced scenario modelling.',
      },
      {
        id: 'free-trial',
        q: 'Is there a free trial?',
        a: 'Yes. Try Fynla free with full access to all features in your chosen tier. No credit card required to start.',
      },
      {
        id: 'trial-ends',
        q: 'What happens when my free trial ends?',
        a: 'You\'ll be prompted to choose a plan. If you don\'t subscribe, your account remains accessible in read-only mode — you won\'t lose any data. You can subscribe at any time to regain full access.',
      },
      {
        id: 'change-plans',
        q: 'Can I change plans?',
        a: 'Yes, upgrade or downgrade at any time. Changes take effect from your next billing cycle.',
      },
      {
        id: 'payment-methods',
        q: 'What payment methods do you accept?',
        a: 'We accept all major credit and debit cards. Payments are processed securely through Stripe.',
      },
      {
        id: 'cancel',
        q: 'What if I cancel?',
        a: 'You can export your data at any time. Cancel monthly plans any time; annual plans run to the end of the 12-month period. Your data is retained for 30 days after cancellation, then permanently deleted.',
      },
    ],
  },
  {
    id: 'security',
    label: 'Security & Privacy',
    items: [
      {
        id: 'data-safe',
        q: 'Is my financial data safe?',
        a: 'Yes. Your data is encrypted in transit and at rest using industry-standard encryption. We don\'t share your data with third parties, don\'t sell data to advertisers, and don\'t earn commission from financial product providers.',
      },
      {
        id: 'data-stored',
        q: 'Where is my data stored?',
        a: 'Fynla data is stored on secure UK-hosted servers.',
      },
      {
        id: 'sell-data',
        q: 'Does Fynla sell my data?',
        a: 'Never. Your subscription is our only revenue source. We don\'t sell data, don\'t share it with product providers, and don\'t display advertising.',
      },
      {
        id: 'export-data',
        q: 'Can I export my data?',
        a: 'Yes. You can export your data at any time in standard formats.',
      },
    ],
  },
  {
    id: 'technical',
    label: 'Technical',
    items: [
      {
        id: 'mobile',
        q: 'Does Fynla work on mobile?',
        a: 'Yes. Fynla is fully responsive and works on smartphones, tablets, and desktops.',
      },
      {
        id: 'browsers',
        q: 'Which browsers are supported?',
        a: 'Fynla works on all modern browsers — Chrome, Firefox, Safari, and Edge.',
      },
      {
        id: 'install',
        q: 'Do I need to install anything?',
        a: 'No. Fynla is a web application — just sign up and start using it in your browser.',
      },
    ],
  },
];

// ─── Mappings: which FAQ ids appear on which feature/pricing pages ───

export const FEATURE_PAGE_FAQS = {
  'pension-tracker': [
    'connect-pension-providers',
    'state-pension-included',
    'defined-benefit-pensions',
    'consolidate-pensions',
    'retirement-date-accuracy',
  ],
  'when-can-i-retire': [
    'retirement-date-accuracy',
    'inflation-accounted',
    'early-retirement-fire',
    'state-pension-included',
    'model-part-time',
  ],
  'monte-carlo': [
    'monte-carlo',
    'monte-carlo-confidence',
    'monte-carlo-same-as-ifa',
    'monte-carlo-scenario-paths',
  ],
  'net-worth-dashboard': [
    'how-value-property',
    'connect-bank-accounts',
    'how-often-update',
    'net-worth-vs-wealth',
  ],
  'protection-gap': [
    'does-fynla-sell-insurance',
    'insurance-types-difference',
    'state-benefits-factored',
    'death-in-service',
    'partner-works-protection',
  ],
  'iht-planning': [
    'is-fynla-tax-advice',
    'iht-2027-changes',
    'iht-married-couples',
    'iht-accuracy',
    'trusts-bpr',
  ],
  'ice-letters': [
    'ice-letter-contents',
    'ice-auto-updated',
    'ice-customise',
    'ice-who-to-give',
    'ice-vs-will',
  ],
};

export const PRICING_PAGE_FAQS = [
  'trial-ends',
  'change-plans',
  'payment-methods',
  'data-safe',
  'plan-differences',
  'cancel',
];

// ─── Helper functions ───

/** All categories with all items (for the main FAQ page). */
export function getAllFaqCategories() {
  return FAQ_CATEGORIES;
}

/** Get FAQ items for a specific feature page by its slug. */
export function getFeatureFaqs(featureSlug) {
  const ids = FEATURE_PAGE_FAQS[featureSlug] || [];
  const allItems = FAQ_CATEGORIES.flatMap(cat => cat.items);
  return ids.map(id => allItems.find(item => item.id === id)).filter(Boolean);
}

/** Get FAQ items for the pricing page. */
export function getPricingFaqs() {
  const allItems = FAQ_CATEGORIES.flatMap(cat => cat.items);
  return PRICING_PAGE_FAQS.map(id => allItems.find(item => item.id === id)).filter(Boolean);
}
