<template>
  <section class="rounded-2xl bg-eggshell-50 border border-savannah-100 px-6 py-8 sm:px-10 sm:py-10 mt-10">
    <div class="max-w-2xl mx-auto text-center">
      <h3 class="text-2xl sm:text-3xl font-black text-horizon-500 leading-tight mb-2">
        {{ heading }}
      </h3>
      <p class="text-sm sm:text-base text-horizon-400 leading-relaxed mb-6">
        {{ subheading }}
      </p>
      <a
        :href="href"
        class="inline-flex items-center gap-2 bg-raspberry-500 hover:bg-raspberry-600 text-white font-semibold px-6 py-3 rounded-lg transition-colors"
      >
        {{ buttonText }}
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
      </a>
      <p v-if="!campaign" class="text-xs text-horizon-400 mt-3">
        Takes 60 seconds · no card
      </p>
    </div>
  </section>
</template>

<script>
export default {
  name: 'InsightCtaPanel',
  props: {
    /**
     * The article being rendered. If article.pipelineCampaign is set, the
     * CTA swaps to the campaign copy + landing URL. Otherwise, the
     * default "Register free" CTA renders.
     */
    article: {
      type: Object,
      required: true,
    },
  },
  computed: {
    campaign() {
      return this.article?.pipelineCampaign ?? this.article?.pipeline_campaign ?? null;
    },
    heading() {
      return this.campaign?.cta_heading ?? 'Ready to plan smarter?';
    },
    subheading() {
      return (
        this.campaign?.cta_subheading
        ?? 'Register free and see how much more control you can have over your money.'
      );
    },
    buttonText() {
      return this.campaign?.cta_button_text ?? 'Register free';
    },
    href() {
      if (this.campaign?.landing_url) return this.campaign.landing_url;
      return '/register';
    },
  },
};
</script>
