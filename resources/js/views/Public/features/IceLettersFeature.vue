<template>
  <FeaturePageLayout
    :hero="hero"
    :problem="problem"
    :solution="solution"
    :emotional="emotional"
    :faq="faq"
    :finalCta="finalCta"
    :relatedLinks="relatedLinks"
  />
</template>

<script>
import FeaturePageLayout from '@/components/Public/FeaturePageLayout.vue';
import { getFeatureFaqs } from '@/constants/faqData';

export default {
  name: 'IceLettersFeature',
  components: { FeaturePageLayout },
  mounted() {
    document.title = 'In Case of Emergency Letters — secure financial instructions | Fynla';
    document.querySelector('meta[name="description"]')?.setAttribute('content', 'Create an In Case of Emergency letter with Fynla. Store essential financial details, account information, and instructions for your loved ones in one secure place.');
  },

  data() {
    return {
      hero: {
        headline: "If Something Happened to You Tonight, Would Your Family Know What to Do?",
        subheadline: "Where are the pensions? Which provider is the life insurance with? What's the mortgage account number? Fynla's ICE letter puts everything your family needs in one document — automatically populated from your financial plan.",
      },
      problem: {
        headline: "The Conversation Nobody Has — Until It's Too Late",
        points: [
          {
            title: "It's all in your head",
            body: "You know where the savings accounts are, who the pension providers are, and where the will is kept. Your partner probably doesn't. Your children definitely don't.",
          },
          {
            title: "Grief plus admin is brutal",
            body: "Losing someone is devastating. Losing someone and then spending months trying to find their financial accounts, policies, and documents makes it worse.",
          },
          {
            title: "It's not just about death",
            body: "What if you're seriously ill and can't communicate? What if you're in an accident abroad? Your family needs to access information quickly.",
          },
        ],
      },
      solution: {
        headline: "One Document. Everything Your Family Needs.",
        cards: [
          {
            title: "Auto-Populated from Your Plan",
            body: "Every asset, pension, investment, insurance policy, and account you've added to Fynla is automatically pulled into your ICE letter. No retyping.",
          },
          {
            title: "Key Contacts Directory",
            body: "Solicitor, accountant, financial adviser, insurance broker, mortgage provider — all in one section with names, phone numbers, and reference numbers.",
          },
          {
            title: "Account & Policy Register",
            body: "A complete list of every financial account and policy: bank accounts, savings, ISAs, pensions, life insurance — with provider details and policy numbers.",
          },
          {
            title: "Important Documents Location",
            body: "Where's the will? The marriage certificate? The property deeds? Record where physical and digital documents are stored.",
          },
          {
            title: "Immediate Actions Checklist",
            body: "Step-by-step guidance for your family: who to call first, what to claim, what deadlines exist. A roadmap through the worst time of their lives.",
          },
          {
            title: "Secure & Private",
            body: "Your ICE letter is generated from your Fynla account and can be downloaded as a PDF. You choose who gets it and how.",
          },
        ],
      },
      emotional: {
        headline: "The Most Loving Document You'll Ever Write",
        body: "This isn't about spreadsheets or financial planning. It's about making sure the people you love don't have to search through drawers, emails, and filing cabinets during the worst week of their lives.\n\nCreating an ICE letter takes about 15 minutes if your Fynla profile is up to date. It's one of those things that costs nothing to do and everything not to.",
      },
      faq: getFeatureFaqs('ice-letters'),
      finalCta: {
        headline: "15 Minutes Now Could Save Your Family Months of Stress",
        body: "The demo includes a sample ICE letter so you can see exactly what it looks like. It's the feature people didn't know they needed — until they see it.",
      },
      relatedLinks: [
        { to: "/features/protection-gap", label: "Protection Gap" },
        { to: "/features/iht-planning", label: "Inheritance Tax Planning" },
        { to: "/features/net-worth-dashboard", label: "Net Worth Dashboard" },
      ],
    };
  },
};
</script>
