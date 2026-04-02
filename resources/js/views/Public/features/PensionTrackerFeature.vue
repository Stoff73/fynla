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
  name: 'PensionTrackerFeature',
  components: { FeaturePageLayout },
  mounted() {
    document.title = 'Pension Tracker — every pension in one dashboard | Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'Track every pension in one dashboard with Fynla. Workplace, personal, SIPP, and State Pension tracking with retirement income projections and contribution analysis.');
  },

  data() {
    return {
      hero: {
        headline: "Every pension. One dashboard. Finally.",
        subheadline: "The average UK worker has 11 jobs in their lifetime. That's potentially 11 different pension pots scattered across providers. Fynla brings them all together.",
        socialProof: 'I found £23,000 in a pension I\'d completely forgotten about.',
      },
      problem: {
        headline: "Your pensions are probably a mess. That's normal.",
        points: [
          {
            title: "Lost pots everywhere",
            body: "The Pensions Policy Institute estimates £26.6 billion sits in lost or forgotten UK pension pots. Some of that is almost certainly yours.",
          },
          {
            title: "No single view",
            body: "Each provider has their own portal, their own login, their own projected figures using different assumptions. You've never seen the full picture.",
          },
          {
            title: "Contributions vs reality",
            body: "Your payslip says you're contributing 5%. But is that enough? What will it actually be worth in 20 years? Most people have no idea.",
          },
        ],
      },
      solution: {
        headline: "Your complete pension picture",
        cards: [
          {
            title: "Multi-pension dashboard",
            body: "Add every pension — workplace, personal, SIPP, old employer schemes — and see them on a single screen. Total value, growth trajectory, and projected income.",
          },
          {
            title: "State pension forecast",
            body: "See your state pension entitlement, how many qualifying years you have, and whether you have gaps worth filling.",
          },
          {
            title: "Contribution tracker",
            body: "Track what you're putting in, what your employer matches, and whether you're maximising your tax relief. See the impact of increasing contributions by even 1%.",
          },
          {
            title: "Growth projections",
            body: "Model your pension value at retirement under different growth scenarios — conservative, moderate, and optimistic.",
          },
          {
            title: "Consolidation analysis",
            body: "Thinking about consolidating old pensions? Compare fees, fund choices, and projected outcomes for an informed decision.",
          },
          {
            title: "Retirement income modelling",
            body: "Translate your pension pots into monthly retirement income. See the gap between what you'll have and what you'll need.",
          },
        ],
      },
      comparison: {
        headline: "Pension tracking: Fynla vs the alternatives",
        columns: ["Fynla (£8.50-£20/mo)", "Provider portals", "IFA review (£500-1,500)", "Tracing service"],
        rows: [
          { label: "All pensions in one view", values: [true, "One per provider", "Once a year", "Just finds them"] },
          { label: "Growth projections", values: ["Customisable", "Basic", true, false] },
          { label: "State pension included", values: [true, false, "Usually", false] },
          { label: "Contribution optimisation", values: [true, "Limited", true, false] },
          { label: "Cost", values: ["£102-£240/yr", "Free (fragmented)", "£500-1,500/yr", "Free (limited)"] },
        ],
      },
      faq: getFeatureFaqs('pension-tracker'),
      finalCta: {
        headline: "Stop guessing what your retirement looks like",
        body: "Add your pensions in under 10 minutes. See what you've really got — and what you need to do about it.",
      },
      relatedLinks: [
        { to: "/features/when-can-i-retire", label: "When can I retire?" },
        { to: "/features/monte-carlo", label: "Monte Carlo simulations" },
        { to: "/features/iht-planning", label: "Inheritance Tax planning" },
      ],
    };
  },
};
</script>
