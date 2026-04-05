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
  name: 'IhtPlanningFeature',
  components: { FeaturePageLayout },
  mounted() {
    document.title = 'Inheritance Tax Planning — calculate and reduce your IHT | Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'Plan your Inheritance Tax with Fynla. Calculate your Inheritance Tax liability, track nil-rate bands, model gifting strategies, and protect your estate for your family.');
  },

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
      faq: getFeatureFaqs('iht-planning'),
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
