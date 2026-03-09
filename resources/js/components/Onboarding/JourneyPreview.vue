<template>
  <transition name="preview-slide">
    <div v-if="selections.length > 0" class="mt-4 mb-2 overflow-hidden">
      <div class="bg-eggshell-500 rounded-lg border border-light-gray p-4">
        <!-- Summary -->
        <p class="text-body-sm text-horizon-500 mb-3">
          We'll ask about <strong>{{ previewData?.personal_count || 0 }} personal details</strong>
          and <strong>{{ previewData?.financial_count || 0 }} financial areas</strong>.
          <span class="text-neutral-500">About {{ previewData?.estimated_minutes || 1 }} minutes.</span>
        </p>

        <!-- Show/Hide Details Toggle -->
        <button
          type="button"
          class="text-body-sm text-raspberry-500 hover:text-raspberry-600 font-medium mb-3 transition-colors"
          @click="showDetails = !showDetails"
        >
          {{ showDetails ? 'Hide details' : 'Show details' }}
          <svg
            class="w-4 h-4 inline-block ml-0.5 transition-transform duration-200"
            :class="{ 'rotate-180': showDetails }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <!-- Details (collapsible) -->
        <transition name="preview-slide">
          <div v-if="showDetails" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Personal Details -->
            <div v-if="previewData?.personal_fields?.length">
              <h4 class="text-caption font-semibold text-horizon-500 mb-2 uppercase tracking-wide">Personal Details</h4>
              <ul class="space-y-1.5">
                <li v-for="field in previewData.personal_fields" :key="field.key" class="flex items-center text-body-sm text-horizon-500">
                  <span class="flex-1">{{ field.label }}</span>
                  <InfoTooltip :why="field.why" position="left" />
                </li>
              </ul>
            </div>

            <!-- Financial Details -->
            <div v-if="previewData?.financial_fields?.length">
              <h4 class="text-caption font-semibold text-horizon-500 mb-2 uppercase tracking-wide">Financial Details</h4>
              <ul class="space-y-1.5">
                <li v-for="field in previewData.financial_fields" :key="field.key" class="flex items-center text-body-sm text-horizon-500">
                  <span class="flex-1">{{ field.label }}</span>
                  <InfoTooltip :why="field.why" position="left" />
                </li>
              </ul>
            </div>
          </div>
        </transition>

        <!-- Reassuring footer -->
        <p class="text-caption text-neutral-500 mt-3">
          You can skip any question and come back to it later.
        </p>
      </div>
    </div>
  </transition>
</template>

<script>
import InfoTooltip from '../Shared/InfoTooltip.vue';
import api from '@/services/api';

export default {
  name: 'JourneyPreview',
  components: { InfoTooltip },

  props: {
    selections: {
      type: Array,
      required: true,
    },
  },

  data() {
    return {
      previewData: null,
      showDetails: false,
      loading: false,
    };
  },

  watch: {
    selections: {
      handler(newVal) {
        if (newVal.length > 0) {
          this.fetchPreview();
        } else {
          this.previewData = null;
        }
      },
      deep: true,
      immediate: true,
    },
  },

  methods: {
    async fetchPreview() {
      this.loading = true;
      try {
        const params = this.selections.map(j => `journeys[]=${encodeURIComponent(j)}`).join('&');
        const response = await api.get(`/journeys/preview?${params}`);
        this.previewData = response.data?.data || response.data;
      } catch (error) {
        // Silently fail — preview is optional enhancement
        this.previewData = null;
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.preview-slide-enter-active,
.preview-slide-leave-active {
  transition: max-height 0.3s ease, opacity 0.3s ease;
  max-height: 500px;
  overflow: hidden;
}

.preview-slide-enter-from,
.preview-slide-leave-to {
  max-height: 0;
  opacity: 0;
}
</style>
