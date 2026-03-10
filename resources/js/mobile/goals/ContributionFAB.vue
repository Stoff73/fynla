<template>
  <div>
    <!-- FAB button -->
    <button
      class="fixed bottom-20 right-4 w-14 h-14 bg-raspberry-500 text-white rounded-full
             shadow-lg flex items-center justify-center active:bg-raspberry-600 transition-colors z-30"
      @click="openSheet"
    >
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
      </svg>
    </button>

    <!-- Bottom sheet backdrop -->
    <transition name="fade">
      <div
        v-if="isOpen"
        class="fixed inset-0 bg-black bg-opacity-40 z-40"
        @click="closeSheet"
      ></div>
    </transition>

    <!-- Bottom sheet -->
    <transition name="slide-up">
      <div
        v-if="isOpen"
        class="fixed bottom-0 left-0 right-0 bg-white rounded-t-2xl z-50 pb-safe"
      >
        <!-- Drag handle -->
        <div class="flex justify-center pt-3 pb-2">
          <div class="w-10 h-1 bg-neutral-300 rounded-full"></div>
        </div>

        <!-- Form -->
        <div class="px-6 pb-6">
          <h3 class="text-base font-bold text-horizon-500 mb-4">Record contribution</h3>

          <!-- Amount -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-500 mb-1">Amount</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-500">£</span>
              <input
                v-model="form.amount"
                type="number"
                step="0.01"
                min="0.01"
                placeholder="0.00"
                class="w-full pl-7 pr-4 py-2.5 bg-eggshell-500 rounded-xl text-horizon-500
                       text-sm outline-none focus:ring-2 focus:ring-violet-500"
              />
            </div>
          </div>

          <!-- Date -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-horizon-500 mb-1">Date</label>
            <input
              v-model="form.date"
              type="date"
              class="w-full px-4 py-2.5 bg-eggshell-500 rounded-xl text-horizon-500
                     text-sm outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>

          <!-- Note -->
          <div class="mb-6">
            <label class="block text-sm font-medium text-horizon-500 mb-1">Note (optional)</label>
            <input
              v-model="form.note"
              type="text"
              placeholder="e.g. Monthly savings"
              class="w-full px-4 py-2.5 bg-eggshell-500 rounded-xl text-horizon-500
                     text-sm placeholder-neutral-400 outline-none focus:ring-2 focus:ring-violet-500"
            />
          </div>

          <!-- Actions -->
          <div class="flex gap-3">
            <button
              class="flex-1 py-2.5 rounded-xl border border-light-gray text-sm font-medium text-horizon-500"
              @click="closeSheet"
            >
              Cancel
            </button>
            <button
              :disabled="!canSave || saving"
              class="flex-1 py-2.5 rounded-xl text-sm font-medium text-white transition-colors"
              :class="canSave && !saving ? 'bg-raspberry-500' : 'bg-neutral-300'"
              @click="handleSave"
            >
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
import { mapActions } from 'vuex';

export default {
  name: 'ContributionFAB',

  props: {
    goalId: {
      type: [Number, String],
      required: true,
    },
  },

  emits: ['saved'],

  data() {
    return {
      isOpen: false,
      saving: false,
      form: {
        amount: '',
        date: new Date().toISOString().split('T')[0],
        note: '',
      },
    };
  },

  computed: {
    canSave() {
      return parseFloat(this.form.amount) > 0;
    },
  },

  methods: {
    ...mapActions('goals', ['recordContribution']),

    openSheet() {
      this.isOpen = true;
      this.form.amount = '';
      this.form.date = new Date().toISOString().split('T')[0];
      this.form.note = '';
    },

    closeSheet() {
      this.isOpen = false;
    },

    async handleSave() {
      if (!this.canSave || this.saving) return;

      this.saving = true;
      try {
        await this.recordContribution({
          goalId: this.goalId,
          contributionData: {
            amount: parseFloat(this.form.amount),
            date: this.form.date,
            note: this.form.note || null,
          },
        });

        // Haptic feedback
        this.triggerHaptic();

        this.$emit('saved', {
          amount: parseFloat(this.form.amount),
          date: this.form.date,
        });

        this.closeSheet();
      } catch {
        // Error handled in store
      } finally {
        this.saving = false;
      }
    },

    triggerHaptic() {
      if (window.Capacitor?.Plugins?.Haptics) {
        window.Capacitor.Plugins.Haptics.impact({ style: 'medium' });
      }
    },
  },
};
</script>

<style scoped>
.pb-safe {
  padding-bottom: env(safe-area-inset-bottom, 16px);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.3s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
}
</style>
