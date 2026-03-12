<template>
  <div class="px-4 pt-4 pb-6">
    <template v-if="topic">
      <!-- Fyn context card -->
      <div class="bg-horizon-500 rounded-xl p-4 mb-5 flex items-start gap-3">
        <img
          src="/images/logos/favicon.png"
          alt="Fyn"
          class="w-8 h-8 rounded-full flex-shrink-0"
        />
        <p class="text-white text-sm leading-relaxed">
          {{ topic.fynIntro }}
        </p>
      </div>

      <!-- Key info -->
      <h3 class="text-sm font-bold text-horizon-500 mb-3">Key information</h3>
      <div class="space-y-3 mb-6">
        <LearnInfoCard
          v-for="(info, index) in topic.keyInfo"
          :key="index"
          :info="info"
        />
      </div>

      <!-- Guides -->
      <h3 class="text-sm font-bold text-horizon-500 mb-3">Guides and resources</h3>
      <div class="space-y-2 mb-6">
        <LearnGuideLink
          v-for="(guide, index) in topic.guides"
          :key="index"
          :guide="guide"
        />
      </div>

      <!-- Ask Fyn CTA -->
      <button
        class="w-full py-3 bg-raspberry-500 text-white rounded-xl text-sm font-bold
               flex items-center justify-center gap-2"
        @click="askFyn"
      >
        <img src="/images/logos/favicon.png" alt="" class="w-5 h-5 rounded-full" />
        Ask Fyn about {{ topic.label.toLowerCase() }}
      </button>
    </template>

    <!-- Topic not found -->
    <div v-else class="text-center py-16">
      <p class="text-neutral-500">Topic not found</p>
    </div>
  </div>
</template>

<script>
import { getTopicById } from '@/mobile/learn/learnTopics';
import LearnInfoCard from '@/mobile/learn/LearnInfoCard.vue';
import LearnGuideLink from '@/mobile/learn/LearnGuideLink.vue';

export default {
  name: 'LearnTopicDetail',

  components: {
    LearnInfoCard,
    LearnGuideLink,
  },

  computed: {
    topic() {
      return getTopicById(this.$route.params.topic);
    },
  },

  methods: {
    askFyn() {
      if (this.topic?.fynPrompt) {
        this.$store.dispatch('aiChat/prefillPrompt', this.topic.fynPrompt);
        this.$router.push('/m/fyn');
      }
    },
  },
};
</script>
