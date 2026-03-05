<template>
  <div class="mb-6">
    <PlanSectionHeader title="Conclusion" color="gray" />

    <div class="bg-white rounded-lg shadow-sm border border-light-gray p-6">
      <p class="text-horizon-500 leading-relaxed mb-4">{{ conclusion.summary_text }}</p>

      <!-- Essential actions -->
      <div v-if="essentialActions.length" class="mb-4">
        <h4 class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-2">Priority Actions</h4>
        <ul class="space-y-1.5">
          <li
            v-for="(action, idx) in essentialActions"
            :key="'essential-' + idx"
            class="flex items-start text-sm"
          >
            <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold mr-2 mt-0.5"
              :class="action.priority === 'critical' ? 'bg-raspberry-100 text-raspberry-700' : 'bg-violet-100 text-violet-700'"
            >
              {{ idx + 1 }}
            </span>
            <span class="text-horizon-500">{{ action.title }}</span>
          </li>
        </ul>
      </div>

      <!-- Optional actions -->
      <div v-if="optionalActions.length" class="mb-4">
        <h4 class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-2">Optional Improvements</h4>
        <ul class="space-y-1.5">
          <li
            v-for="(action, idx) in optionalActions"
            :key="'optional-' + idx"
            class="flex items-start text-sm text-neutral-500"
          >
            <span class="flex-shrink-0 text-horizon-400 mr-2 mt-0.5">&mdash;</span>
            <span>{{ action.title }}</span>
          </li>
        </ul>
      </div>

      <!-- No actions state -->
      <div v-if="conclusion.total_actions === 0" class="text-sm text-neutral-500 italic">
        Enable actions above to see your personalised priority list here.
      </div>
    </div>
  </div>
</template>

<script>
import PlanSectionHeader from './PlanSectionHeader.vue';

export default {
  name: 'PlanConclusion',

  components: { PlanSectionHeader },

  props: {
    conclusion: {
      type: Object,
      required: true,
    },
  },

  computed: {
    essentialActions() {
      return this.conclusion.essential_actions || [];
    },

    optionalActions() {
      return this.conclusion.optional_actions || [];
    },
  },
};
</script>
