<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="translate-x-full opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="translate-x-full opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed right-0 top-0 bottom-0 w-96 max-w-full bg-white shadow-xl z-50
               flex flex-col border-l border-light-gray"
      >
        <!-- Header -->
        <div class="p-4 border-b bg-violet-50">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-horizon-500">
              What powers this view?
            </h2>
            <button
              @click="close"
              class="p-1 text-horizon-400 hover:text-neutral-500 rounded-full hover:bg-violet-100 transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <p class="text-sm text-neutral-500 mt-1">
            {{ moduleDescription }}
          </p>
        </div>

        <!-- Progress Bar -->
        <div class="px-4 py-3 bg-savannah-100 border-b">
          <div class="flex items-center justify-between text-sm mb-1">
            <span class="font-medium text-neutral-500">Data completeness</span>
            <span class="font-semibold" :class="progressColor">
              {{ completionPercentage }}%
            </span>
          </div>
          <div class="w-full bg-savannah-200 rounded-full h-2">
            <div
              class="h-2 rounded-full transition-all duration-300"
              :class="progressBarColor"
              :style="{ width: `${completionPercentage}%` }"
            ></div>
          </div>
        </div>

        <!-- Content - scrollable -->
        <div class="flex-1 overflow-y-auto p-4">
          <!-- Loading state -->
          <div v-if="loading" class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-violet-600"></div>
          </div>

          <!-- Requirements list -->
          <div v-else>
            <h3 class="text-sm font-semibold text-horizon-500 mb-3">
              Data that drives this view:
            </h3>

            <div class="space-y-3">
              <!-- All requirements, showing status -->
              <div
                v-for="item in allRequirements"
                :key="item.key"
                class="p-3 rounded-lg border"
                :class="item.status === 'filled' ? 'bg-spring-50 border-spring-200' : 'bg-violet-50 border-violet-200'"
              >
                <div class="flex items-start gap-2">
                  <!-- Status icon -->
                  <div class="flex-shrink-0 mt-0.5">
                    <!-- Checkmark for filled -->
                    <svg
                      v-if="item.status === 'filled'"
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                      class="w-5 h-5 text-spring-600"
                    >
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                    <!-- Warning for missing -->
                    <svg
                      v-else
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                      class="w-5 h-5 text-violet-600"
                    >
                      <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                  </div>

                  <div class="flex-1 min-w-0">
                    <!-- Label -->
                    <div class="flex items-center gap-2">
                      <span
                        class="font-medium text-sm"
                        :class="item.status === 'filled' ? 'text-spring-800' : 'text-violet-800'"
                      >
                        {{ item.label }}
                      </span>
                      <span
                        v-if="item.status === 'missing'"
                        class="text-xs px-1.5 py-0.5 rounded bg-violet-200 text-violet-800"
                      >
                        missing
                      </span>
                    </div>

                    <!-- Why explanation -->
                    <p class="text-xs mt-1" :class="item.status === 'filled' ? 'text-spring-700' : 'text-violet-700'">
                      {{ item.why }}
                    </p>

                    <!-- Add link for missing items -->
                    <router-link
                      v-if="item.status === 'missing'"
                      :to="item.link"
                      @click="close"
                      class="inline-flex items-center gap-1 mt-2 text-xs font-medium text-violet-600 hover:text-violet-800"
                    >
                      Add now
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                      </svg>
                    </router-link>
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty state if no requirements -->
            <div v-if="allRequirements.length === 0 && !loading" class="text-center py-8">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto text-horizon-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p class="mt-2 text-sm text-neutral-500">No specific data requirements for this view.</p>
            </div>
          </div>
        </div>

        <!-- Footer with toggle -->
        <div class="p-4 border-t bg-savannah-100">
          <label class="flex items-center text-sm cursor-pointer">
            <input
              type="checkbox"
              v-model="guideEnabled"
              @change="onToggleChange"
              class="rounded border-horizon-300 text-violet-600 focus:ring-violet-500 h-4 w-4"
            />
            <span class="ml-2 text-neutral-500">Show this help button</span>
          </label>
          <p v-if="isPreviewMode" class="text-xs text-neutral-500 mt-1">
            (Always shown in preview mode)
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script>
import { mapGetters, mapActions } from 'vuex';
import { resolveModule } from '@/utils/moduleMap';

export default {
  name: 'InfoGuidePanel',

  data() {
    return {
      guideEnabled: true,
    };
  },

  computed: {
    ...mapGetters('infoGuide', [
      'isOpen',
      'isEnabled',
      'loading',
      'allRequirements',
      'completionPercentage',
      'moduleDescription',
    ]),
    ...mapGetters('preview', ['isPreviewMode']),

    progressColor() {
      if (this.completionPercentage >= 80) return 'text-spring-600';
      if (this.completionPercentage >= 50) return 'text-violet-600';
      return 'text-raspberry-600';
    },

    progressBarColor() {
      if (this.completionPercentage >= 80) return 'bg-spring-500';
      if (this.completionPercentage >= 50) return 'bg-violet-500';
      return 'bg-raspberry-500';
    },
  },

  watch: {
    isEnabled: {
      immediate: true,
      handler(newVal) {
        this.guideEnabled = newVal;
      },
    },

    // Watch route changes and update module context
    '$route.path': {
      immediate: true,
      handler(newPath) {
        // Skip public routes
        const publicRoutes = ['/login', '/register', '/', '/calculators', '/learn'];
        if (publicRoutes.some(route => newPath === route || newPath.startsWith('/forgot') || newPath.startsWith('/reset'))) {
          return;
        }

        // Resolve module from shared map
        const module = resolveModule(newPath);

        // Fetch requirements for this module
        this.fetchRequirementsForModule(module);
      },
    },
  },

  methods: {
    ...mapActions('infoGuide', ['close', 'updatePreference', 'fetchRequirements']),

    onToggleChange() {
      this.updatePreference(this.guideEnabled);
    },

    fetchRequirementsForModule(module) {
      // Force fetch by clearing current module first if different
      this.fetchRequirements(module);
    },
  },
};
</script>
