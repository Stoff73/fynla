<template>
  <FeaturePageLayout
    :hero="hero"
    :problem="problem"
    :solution="solution"
    :comparison="comparison"
    :faq="faq"
    :finalCta="finalCta"
    :relatedLinks="relatedLinks"
  />
</template>

<script>
import FeaturePageLayout from '@/components/Public/FeaturePageLayout.vue';
import { getFeatureFaqs } from '@/constants/faqData';

export default {
  name: 'NetWorthDashboardFeature',
  components: { FeaturePageLayout },
  mounted() {
    document.title = 'Net Worth Dashboard — track your complete financial picture | Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'Track your complete net worth with Fynla. See all your assets, liabilities, property, pensions, and investments in one real-time dashboard.');
  },

  data() {
    return {
      hero: {
        headline: "What Are You Actually Worth? (Most People Don't Know)",
        subheadline: "Your home. Your pensions. Your savings. Your debts. Fynla brings it all together into a single number — your net worth — and tracks how it changes over time.",
        socialProof: '"Seeing everything in one place for the first time was a revelation. We\'re in a much better position than we thought."',
      },
      problem: {
        headline: "You Know Your Salary. But Do You Know Your Net Worth?",
        points: [
          {
            title: "Fragmented finances",
            body: "Money spread across bank accounts, ISAs, pensions, property, and investments — each in a different place, none talking to each other. You've never seen the total.",
          },
          {
            title: "Assets minus debts",
            body: "Your house is worth £350,000 — but with a £200,000 mortgage, your equity is £150,000. Most people think in gross terms, not net terms.",
          },
          {
            title: "No trend, no direction",
            body: "Is your financial position improving or deteriorating? Without tracking net worth over time, you're steering without a compass.",
          },
        ],
      },
      solution: {
        headline: "Everything You Own. Everything You Owe. One Screen.",
        cards: [
          {
            title: "Complete Asset Register",
            body: "Property, pensions, investments, ISAs, savings, cash, valuables — add everything and see your total assets at a glance. Categorised and colour-coded.",
          },
          {
            title: "Liability Tracker",
            body: "Mortgage, student loans, credit cards, car finance, personal loans — everything you owe, with balances, rates, and payoff timelines.",
          },
          {
            title: "Net Worth = Assets - Liabilities",
            body: "One clear number. Updated whenever you add or change data. The single most important metric in personal finance.",
          },
          {
            title: "Historical Tracking",
            body: "See how your net worth has changed over months and years. Spot the impact of major decisions — buying a house, starting a pension, paying off a loan.",
          },
          {
            title: "Breakdown by Category",
            body: "Understand your composition: what percentage is locked in property vs liquid savings vs pensions? Are you too concentrated in one asset type?",
          },
          {
            title: "Gateway to Deeper Planning",
            body: "Your net worth dashboard feeds every other Fynla feature — retirement planning, Inheritance Tax calculations, protection needs, and more.",
          },
        ],
      },
      comparison: {
        headline: "Net Worth Tracking: Fynla vs The Alternatives",
        columns: ["Fynla (£8.50-£20/mo)", "Budgeting Apps", "Spreadsheet", "IFA Annual Review"],
        rows: [
          { label: "All asset types including pensions", values: [true, "Partial", "Manual", true] },
          { label: "Liabilities included", values: [true, "Some", "Manual", true] },
          { label: "Historical tracking", values: [true, "Limited", "If you remember", "Annual snapshot"] },
          { label: "Feeds into planning tools", values: [true, false, false, "Within their software"] },
          { label: "Time to set up", values: ["15-20 minutes", "5 mins (limited)", "Hours", "Weeks"] },
        ],
      },
      faq: getFeatureFaqs('net-worth-dashboard'),
      finalCta: {
        headline: "Start With the Number That Matters Most",
        body: "Your net worth is the foundation of every financial decision. See it clearly, track it over time, and build your plan from there.",
      },
      relatedLinks: [
        { to: "/features/pension-tracker", label: "Pension Tracker" },
        { to: "/features/when-can-i-retire", label: "When Can I Retire?" },
        { to: "/features/iht-planning", label: "Inheritance Tax Planning" },
        { to: "/features/protection-gap", label: "Protection Gap" },
        { to: "/how-it-works", label: "How It Works" },
      ],
    };
  },
};
</script>
