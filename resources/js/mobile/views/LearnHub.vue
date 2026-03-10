<template>
  <div class="px-4 pt-4 pb-6">
    <!-- Fyn intro card -->
    <div class="bg-horizon-500 rounded-xl p-5 mb-5 flex items-start gap-3">
      <img
        src="/images/logos/favicon.png"
        alt="Fyn"
        class="w-10 h-10 rounded-full flex-shrink-0"
      />
      <div>
        <h3 class="text-white font-bold text-base mb-1">Learn with Fyn</h3>
        <p class="text-white text-sm leading-relaxed opacity-90">
          Explore key financial topics at your own pace.
          Tap any topic to learn more, or ask me to explain anything.
        </p>
      </div>
    </div>

    <!-- Topic grid -->
    <div class="grid grid-cols-2 gap-3">
      <LearnTopicCard
        v-for="topic in topics"
        :key="topic.id"
        :topic="topic"
        @click="navigateToTopic(topic.id)"
      />
    </div>
  </div>
</template>

<script>
import learnTopics from '@/mobile/learn/learnTopics';
import LearnTopicCard from '@/mobile/learn/LearnTopicCard.vue';

export default {
  name: 'LearnHub',

  components: {
    LearnTopicCard,
  },

  data() {
    return {
      topics: learnTopics,
    };
  },

  methods: {
    navigateToTopic(topicId) {
      this.$router.push(`/m/learn/${topicId}`);
    },
  },
};
</script>
