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
  name: 'WhenCanIRetireFeature',
  components: { FeaturePageLayout },
  mounted() {
    document.title = 'When Can I Retire — retirement affordability calculator | Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'Find out when you can afford to retire with Fynla. Model different retirement ages, pension drawdown strategies, and see your projected retirement income.');
  },

  data() {
    return {
      hero: {
        headline: "When Can You Actually Afford to Stop Working?",
        subheadline: "Not when your pension provider says. Not when the government says. When YOUR money says you can. Fynla calculates your personal retirement date based on everything.",
        socialProof: '"Turns out I could retire 3 years earlier than I thought. That changes everything."',
      },
      problem: {
        headline: '"67" Is Not the Answer',
        points: [
          {
            title: "State pension age isn't YOUR retirement age",
            body: "The government says 67 (rising to 68). But your retirement date depends on your money, not a political decision.",
          },
          {
            title: "You've never done the real maths",
            body: "Adding up pension pots isn't enough. You need to factor in drawdown rates, inflation, state pension timing, investment growth, and how long your money needs to last.",
          },
          {
            title: "Fear of getting it wrong",
            body: "Retire too early and you run out of money. Retire too late and you waste years you can't get back. The stakes are enormous.",
          },
        ],
      },
      solution: {
        headline: "Your Personal Retirement Date, Calculated Properly",
        cards: [
          {
            title: "Retirement Date Calculator",
            body: "Enter your pensions, savings, investments, and expected spending. Fynla calculates the earliest date you could retire while maintaining your lifestyle.",
          },
          {
            title: "Income Bridge Modelling",
            body: "If you want to retire before state pension age, you need a bridge. Fynla models exactly how much you need from private sources to cover the gap.",
          },
          {
            title: "Spending Scenarios",
            body: "Model the go-go, slow-go, no-go years — higher spending in early retirement, reducing as you age, with potential care cost increases later.",
          },
          {
            title: "\"What If\" Levers",
            body: "What if I increase pension contributions by 2%? What if I downsize? What if I work part-time until 60? See the impact on your retirement date in real time.",
          },
          {
            title: "Monte Carlo Confidence",
            body: "Don't just see one number. Fynla runs thousands of market scenarios and tells you the probability of your plan succeeding.",
          },
          {
            title: "Annual Check-In Tracker",
            body: "Your retirement date isn't set once — it evolves. Track how your position changes year to year so you can see whether you're on track.",
          },
        ],
      },
      comparison: {
        headline: "Retirement Planning: Fynla vs The Alternatives",
        columns: ["Fynla (£8.50-£20/mo)", "Online Calculators (Free)", "IFA Review (£1,000-3,000)"],
        rows: [
          { label: "Factors in ALL income sources", values: [true, "Usually 1-2", true] },
          { label: "Monte Carlo simulations", values: [true, false, "Sometimes"] },
          { label: "Spending scenario modelling", values: [true, false, "Basic"] },
          { label: "\"What if\" adjustments", values: ["Real-time", "Limited", "Per consultation"] },
          { label: "State pension integration", values: [true, "Sometimes", true] },
          { label: "Time to answer", values: ["10 minutes", "5 mins (oversimplified)", "2-4 weeks"] },
        ],
      },
      faq: getFeatureFaqs('when-can-i-retire'),
      finalCta: {
        headline: "Find Out Your Number. It Might Surprise You.",
        body: "Whether it's 55 or 68, you deserve to know. The demo lets you explore with sample data in under 5 minutes.",
      },
      relatedLinks: [
        { to: "/features/pension-tracker", label: "Pension Tracker" },
        { to: "/features/monte-carlo", label: "Monte Carlo Simulations" },
        { to: "/features/net-worth-dashboard", label: "Net Worth Dashboard" },
      ],
    };
  },
};
</script>
