<template>
  <FeaturePageLayout
    :hero="hero"
    :problem="problem"
    :solution="solution"
    :explainer="explainer"
    :faq="faq"
    :finalCta="finalCta"
    :relatedLinks="relatedLinks"
  />
</template>

<script>
import FeaturePageLayout from '@/components/Public/FeaturePageLayout.vue';
import { getFeatureFaqs } from '@/constants/faqData';

export default {
  name: 'MonteCarloFeature',
  components: { FeaturePageLayout },
  mounted() {
    document.title = 'Monte Carlo Simulations — model investment outcomes | Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'Run Monte Carlo simulations on your investment portfolio with Fynla. Model thousands of market scenarios to understand the probability of reaching your financial goals.');
  },

  data() {
    return {
      hero: {
        headline: "One Projection Is a Guess. A Thousand Is a Plan.",
        subheadline: "Markets don't move in straight lines. Fynla runs thousands of simulated scenarios — booms, crashes, and everything between — to show you the real probability of your financial plan succeeding.",
      },
      problem: {
        headline: "Your Plan Probably Assumes Everything Goes to Plan",
        points: [
          {
            title: "Straight-line projections lie",
            body: "A 6% average return sounds fine. But what if you get -20% in year one of retirement? The order of returns matters enormously, and most tools ignore it.",
          },
          {
            title: "One number isn't a plan",
            body: "\"You'll have £450,000 at retirement\" tells you nothing about how likely that is, or what happens if markets underperform for a decade.",
          },
          {
            title: "False confidence is dangerous",
            body: "A plan that works in the average case but fails in 30% of scenarios isn't a plan — it's a bet.",
          },
        ],
      },
      solution: {
        headline: "Real Confidence, Not False Precision",
        cards: [
          {
            title: "Thousands of Scenarios, One Confidence Level",
            body: "Fynla runs Monte Carlo simulations across your entire financial plan and gives you a clear confidence level: your plan succeeds in a given percentage of scenarios.",
          },
          {
            title: "Visual Outcome Distribution",
            body: "See the range of possible outcomes as a fan chart. The best case, worst case, and most likely case — all visible at once.",
          },
          {
            title: "Stress Test Your Assumptions",
            body: "What happens if inflation runs at 5% for a decade? What if you face a 2008-style crash in year one of retirement? Test specific scenarios.",
          },
          {
            title: "Sequence of Returns Risk",
            body: "The biggest risk retirees face isn't low average returns — it's bad returns at the wrong time. Fynla explicitly models this risk.",
          },
          {
            title: "Actionable Recommendations",
            body: "Confidence level below 80%? Fynla shows you which levers to pull — increase contributions, delay retirement, reduce drawdown — and how each change helps.",
          },
          {
            title: "Updates as Life Changes",
            body: "Your confidence level updates automatically as you add data, change plans, or as markets move. It's a living dashboard, not a one-off report.",
          },
        ],
      },
      explainer: {
        headline: "What Is Monte Carlo Simulation? (In Plain English)",
        body: "Imagine you're planning a road trip. You could assume the motorway is always clear and you'll average 60mph. But in reality, you might hit traffic, roadworks, or detours.\n\nMonte Carlo simulation is like running that road trip 10,000 times with random traffic conditions each time. Instead of one estimated arrival time, you get a range: you'll arrive between 2pm and 4pm, with 85% confidence you'll be there by 3pm.\n\nFynla does the same thing with your finances. Instead of one projection with one assumed growth rate, we run thousands of scenarios using historical market data — including crashes, booms, and flat periods. The result isn't a single number. It's a probability.",
      },
      faq: getFeatureFaqs('monte-carlo'),
      finalCta: {
        headline: "How Confident Is Your Plan? Find Out in Minutes.",
        body: "The demo runs Monte Carlo simulations on sample data so you can see exactly how it works — no maths degree required.",
      },
      relatedLinks: [
        { to: "/features/when-can-i-retire", label: "When Can I Retire?" },
        { to: "/features/pension-tracker", label: "Pension Tracker" },
        { to: "/features/net-worth-dashboard", label: "Net Worth Dashboard" },
      ],
    };
  },
};
</script>
