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

export default {
  name: 'PensionTrackerFeature',
  components: { FeaturePageLayout },
  data() {
    return {
      hero: {
        headline: "Every Pension. One Dashboard. Finally.",
        subheadline: "The average UK worker has 11 jobs in their lifetime. That's potentially 11 different pension pots scattered across providers. Fynla brings them all together.",
        socialProof: '"I found £23,000 in a pension I\'d completely forgotten about."',
      },
      problem: {
        headline: "Your Pensions Are Probably a Mess. That's Normal.",
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
        headline: "Your Complete Pension Picture",
        cards: [
          {
            title: "Multi-Pension Dashboard",
            body: "Add every pension — workplace, personal, SIPP, old employer schemes — and see them on a single screen. Total value, growth trajectory, and projected income.",
          },
          {
            title: "State Pension Forecast",
            body: "See your state pension entitlement, how many qualifying years you have, and whether you have gaps worth filling.",
          },
          {
            title: "Contribution Tracker",
            body: "Track what you're putting in, what your employer matches, and whether you're maximising your tax relief. See the impact of increasing contributions by even 1%.",
          },
          {
            title: "Growth Projections",
            body: "Model your pension value at retirement under different growth scenarios — conservative, moderate, and optimistic.",
          },
          {
            title: "Consolidation Analysis",
            body: "Thinking about consolidating old pensions? Compare fees, fund choices, and projected outcomes for an informed decision.",
          },
          {
            title: "Retirement Income Modelling",
            body: "Translate your pension pots into monthly retirement income. See the gap between what you'll have and what you'll need.",
          },
        ],
      },
      comparison: {
        headline: "Pension Tracking: Fynla vs The Alternatives",
        columns: ["Fynla (£8.50-£20/mo)", "Provider Portals", "IFA Review (£500-1,500)", "Tracing Service"],
        rows: [
          { label: "All pensions in one view", values: [true, "One per provider", "Once a year", "Just finds them"] },
          { label: "Growth projections", values: ["Customisable", "Basic", true, false] },
          { label: "State pension included", values: [true, false, "Usually", false] },
          { label: "Contribution optimisation", values: [true, "Limited", true, false] },
          { label: "Cost", values: ["£102-£240/yr", "Free (fragmented)", "£500-1,500/yr", "Free (limited)"] },
        ],
      },
      faq: [
        {
          q: "Can Fynla connect directly to my pension providers?",
          a: "Currently, you add your pension details manually — provider, value, contribution rate, and fund type. The manual setup takes about 5 minutes per pension.",
        },
        {
          q: "Does this include the state pension?",
          a: "Yes. Enter your state pension forecast from gov.uk and Fynla incorporates it into your total retirement income projection.",
        },
        {
          q: "What about defined benefit (final salary) pensions?",
          a: "Yes, Fynla supports defined benefit pensions. Enter the projected annual pension amount and retirement age, and it's included in your retirement income modelling.",
        },
        {
          q: "Should I consolidate my pensions?",
          a: "That depends on your specific situation — fees, fund performance, guaranteed benefits, and employer match all factor in. Fynla gives you the data to make that decision.",
        },
        {
          q: "How does this connect to the retirement calculator?",
          a: "Your pension data feeds directly into Fynla's \"When Can I Retire?\" calculator. Change a contribution rate here, and your retirement date updates automatically.",
        },
      ],
      finalCta: {
        headline: "Stop Guessing What Your Retirement Looks Like",
        body: "Add your pensions in under 10 minutes. See what you've really got — and what you need to do about it.",
      },
      relatedLinks: [
        { to: "/features/when-can-i-retire", label: "When Can I Retire?" },
        { to: "/features/monte-carlo", label: "Monte Carlo Simulations" },
        { to: "/features/iht-planning", label: "Inheritance Tax Planning" },
      ],
    };
  },
};
</script>
