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
  name: 'ProtectionGapFeature',
  components: { FeaturePageLayout },
  mounted() {
    document.title = 'Protection Gap Analysis — understand your insurance needs | Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'Analyse your protection gap with Fynla. Understand your life insurance, critical illness, and income protection needs based on your actual financial position.');
  },

  data() {
    return {
      hero: {
        headline: "If You Couldn't Work Tomorrow, What Happens to Your Family?",
        subheadline: "Most people have some life insurance through work but don't know if it's enough. Fynla calculates the gap between what your family would need and what they'd actually get.",
      },
      problem: {
        headline: "Nobody Wants to Think About This. Everyone Should.",
        points: [
          {
            title: "Workplace cover isn't enough",
            body: "Most employer life insurance pays 2-4x salary. If you have a £300,000 mortgage, two kids, and a non-working partner, that doesn't come close.",
          },
          {
            title: "Income protection is forgotten",
            body: "Only 10% of UK workers have income protection insurance. If you're off sick for 6 months, statutory sick pay is £116.75/week. Could you live on that?",
          },
          {
            title: "You're guessing the number",
            body: "How much does your family actually need? It's not just the mortgage — it's childcare, school fees, daily living costs, and maintaining their standard of living.",
          },
        ],
      },
      solution: {
        headline: "See Your Protection Gap in Black and White",
        cards: [
          {
            title: "Family Needs Calculator",
            body: "Enter your mortgage, dependants, living costs, and future obligations. Fynla calculates the total lump sum and ongoing income your family would need.",
          },
          {
            title: "Existing Cover Audit",
            body: "Add your current policies — employer death-in-service, personal life insurance, income protection, critical illness. See what you've got mapped against what you need.",
          },
          {
            title: "The Gap, Visualised",
            body: "A clear visual showing: your family needs this much, your current cover provides this much, your gap is this much. No ambiguity.",
          },
          {
            title: "Scenario Modelling",
            body: "What if you die? What if you can't work for 2 years? What if you're diagnosed with a critical illness? Fynla models all three.",
          },
          {
            title: "Cover-As-You-Grow Tracking",
            body: "Your protection needs change as your mortgage decreases, kids become independent, and savings grow. See how your gap evolves over time.",
          },
          {
            title: "Action Items, Not Sales Pitches",
            body: "Fynla tells you the number — not which provider to buy from. We don't sell insurance. We help you understand what you need.",
          },
        ],
      },
      comparison: {
        headline: "Protection Planning: Fynla vs The Alternatives",
        columns: ["Fynla (£8.50-£20/mo)", "Comparison Sites", "IFA Review (£300-800)", "Doing Nothing"],
        rows: [
          { label: "Calculates your actual need", values: [true, false, true, false] },
          { label: "All 3 cover types", values: [true, "One at a time", true, false] },
          { label: "Shows existing cover gaps", values: [true, false, true, false] },
          { label: "Updates as life changes", values: ["Continuous", "One-off", "Annual", "N/A"] },
          { label: "Independent (no commission)", values: [true, false, "Varies", "N/A"] },
          { label: "Time to see your gap", values: ["10 minutes", "N/A", "2-4 weeks", "Never"] },
        ],
      },
      faq: getFeatureFaqs('protection-gap'),
      finalCta: {
        headline: "The Number You Don't Want to Need — But Need to Know",
        body: "It takes 10 minutes to see your family's protection gap. The demo uses sample data so you can explore without entering personal details.",
      },
      relatedLinks: [
        { to: "/features/ice-letters", label: "ICE Letters" },
        { to: "/features/net-worth-dashboard", label: "Net Worth Dashboard" },
      ],
    };
  },
};
</script>
