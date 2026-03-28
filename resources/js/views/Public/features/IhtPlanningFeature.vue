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
  name: 'IhtPlanningFeature',
  components: { FeaturePageLayout },
  data() {
    return {
      hero: {
        headline: "Know Exactly What HMRC Will Take — And How to Reduce It",
        subheadline: "Fynla maps your full estate — property, pensions, investments, life insurance — and shows your Inheritance Tax exposure in real time. Then helps you model strategies to reduce it.",
        socialProof: '"I had no idea our Inheritance Tax bill was going to be £180,000. Fynla showed us in 5 minutes."',
      },
      problem: {
        headline: "Most People Don't Know What They'll Owe",
        points: [
          {
            title: "The £325,000 trap",
            body: "The nil rate band hasn't changed since 2009. Property prices have. If your estate exceeds the threshold, your family pays 40% on the excess.",
          },
          {
            title: "Pensions are changing",
            body: "From April 2027, unused pension pots will be included in your estate for Inheritance Tax purposes. This is a massive shift that most people haven't planned for.",
          },
          {
            title: "Guesswork isn't a strategy",
            body: "Gifting rules, taper relief, trust structures, business property relief — these are powerful tools, but only if you can see the numbers.",
          },
        ],
      },
      solution: {
        headline: "Your Full Inheritance Tax Picture, Updated in Real Time",
        cards: [
          {
            title: "Estate Valuation Dashboard",
            body: "See your total estate value broken down by asset type — property, pensions, investments, savings, life insurance. Updated automatically as you add or change data.",
          },
          {
            title: "Inheritance Tax Liability Calculator",
            body: "Instant calculation under current UK rules, including nil rate band, residence nil rate band, transferable allowances, and the tapered residence nil rate band for estates over £2m.",
          },
          {
            title: "Pension Inheritance Tax Modelling (April 2027)",
            body: "Model the impact of the upcoming pension inclusion rules before they take effect. See how your pension pot changes your position.",
          },
          {
            title: "Gifting Strategy Modeller",
            body: "Model the 7-year gifting rule, annual exemptions, small gifts, and gifts out of normal expenditure. See how different timelines affect your estate.",
          },
          {
            title: "Scenario Comparison",
            body: "Run side-by-side comparisons: What if we downsize? What if we gift £50,000 now? What if we draw down pensions faster?",
          },
          {
            title: "ICE Letter Integration",
            body: "Your estate plan feeds directly into your ICE letter — so your family knows not just what you own, but what to do with it.",
          },
        ],
      },
      comparison: {
        headline: "Inheritance Tax Planning: Fynla vs The Alternatives",
        columns: ["Fynla (£8.50-£20/mo)", "IFA Estate Planning (£1,500-5,000)", "DIY Spreadsheet"],
        rows: [
          { label: "Real-time estate valuation", values: [true, "One-off snapshot", "Manual updates"] },
          { label: "Pension Inheritance Tax modelling (2027)", values: [true, "Depends on adviser", "Not feasible"] },
          { label: "Gifting scenario modelling", values: [true, "Usually extra cost", "Very difficult"] },
          { label: "Updated when tax rules change", values: ["Automatic", "Requires new consultation", "You research it"] },
          { label: "Time to first insight", values: ["10 minutes", "2-4 weeks", "Hours of setup"] },
          { label: "Annual cost", values: ["£102-£240", "£1,500-5,000+", "Free but risky"] },
        ],
      },
      faq: [
        {
          q: "Is Fynla giving me tax advice?",
          a: "No. Fynla is a planning tool that helps you understand your position based on current UK tax rules. For complex estate planning, we'd recommend consulting a qualified adviser — and Fynla gives you the numbers to make that conversation productive.",
        },
        {
          q: "How does Fynla handle the April 2027 pension changes?",
          a: "We've built the new pension inclusion rules into our modelling engine. You can toggle between current rules and post-April 2027 rules to see exactly how the change affects your estate.",
        },
        {
          q: "Does this work for married couples?",
          a: "Yes. Fynla models transferable nil rate bands, residence nil rate band, and joint estate planning. You can see your position individually and as a couple.",
        },
        {
          q: "How accurate is the Inheritance Tax calculation?",
          a: "Fynla applies current HMRC rules including all standard bands, taper thresholds, and exemptions. Accuracy depends on the completeness of your asset data.",
        },
        {
          q: "What about trusts and business property relief?",
          a: "Fynla covers the most common planning strategies including gifting, pension drawdown, and standard exemptions. For complex trust structures, we'd recommend using Fynla's numbers alongside professional advice.",
        },
      ],
      finalCta: {
        headline: "Don't Leave Your Family a Tax Bill They Didn't Expect",
        body: "It takes 10 minutes to see your Inheritance Tax exposure. The demo lets you explore with sample data — no sign-up needed.",
      },
      relatedLinks: [
        { to: "/features/pension-tracker", label: "Pension Tracker" },
        { to: "/features/ice-letters", label: "ICE Letters" },
        { to: "/features/net-worth-dashboard", label: "Net Worth Dashboard" },
      ],
    };
  },
};
</script>
