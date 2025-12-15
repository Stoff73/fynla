<template>
  <div>
    <!-- Pension Type Selection Modal -->
    <div v-if="!mainPensionType" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="$emit('close')">
      <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full p-8">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-2xl font-semibold text-gray-900">Add Pension</h3>
          <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <p class="text-gray-600 mb-6">Select the type of pension you want to add:</p>

        <div class="grid grid-cols-3 gap-4">
          <button
            type="button"
            @click="mainPensionType = 'dc'"
            class="p-6 border-2 border-gray-300 rounded-lg text-center hover:border-blue-500 hover:bg-blue-50 transition-all"
          >
            <div class="text-blue-600 mb-2">
              <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
              </svg>
            </div>
            <div class="font-semibold text-gray-900 mb-1">Money Purchase Pension</div>
            <div class="text-xs text-gray-500">Your pot grows with contributions</div>
          </button>
          <button
            type="button"
            @click="mainPensionType = 'db'"
            class="p-6 border-2 border-gray-300 rounded-lg text-center hover:border-purple-500 hover:bg-purple-50 transition-all"
          >
            <div class="text-purple-600 mb-2">
              <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
            </div>
            <div class="font-semibold text-gray-900 mb-1">Final Salary Pension</div>
            <div class="text-xs text-gray-500">Guaranteed income for life</div>
          </button>
          <button
            type="button"
            @click="mainPensionType = 'state'"
            class="p-6 border-2 border-gray-300 rounded-lg text-center hover:border-green-500 hover:bg-green-50 transition-all"
          >
            <div class="text-green-600 mb-2">
              <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
              </svg>
            </div>
            <div class="font-semibold text-gray-900 mb-1">State Pension</div>
            <div class="text-xs text-gray-500">UK State Pension</div>
          </button>
        </div>
      </div>
    </div>

    <!-- DC Pension Form -->
    <DCPensionForm
      v-if="mainPensionType === 'dc'"
      :pension="pension"
      :is-edit="isEdit"
      @close="handleClose"
      @save="handleSave"
    />

    <!-- DB Pension Form -->
    <DBPensionForm
      v-if="mainPensionType === 'db'"
      :pension="pension"
      :is-edit="isEdit"
      @close="handleClose"
      @save="handleSave"
    />

    <!-- State Pension Form -->
    <StatePensionForm
      v-if="mainPensionType === 'state'"
      :state-pension="statePension"
      @close="handleClose"
      @save="handleSave"
    />
  </div>
</template>

<script>
import DCPensionForm from './DCPensionForm.vue';
import DBPensionForm from './DBPensionForm.vue';
import StatePensionForm from './StatePensionForm.vue';

export default {
  name: 'UnifiedPensionForm',

  components: {
    DCPensionForm,
    DBPensionForm,
    StatePensionForm,
  },

  props: {
    pension: {
      type: Object,
      default: null,
    },
    statePension: {
      type: Object,
      default: null,
    },
    isEdit: {
      type: Boolean,
      default: false,
    },
    initialPensionType: {
      type: String,
      default: null,
    },
  },

  data() {
    return {
      // Use initialPensionType if provided (edit mode), otherwise null for type selection
      mainPensionType: this.initialPensionType || null,
    };
  },

  methods: {
    handleClose() {
      this.mainPensionType = null;
      this.$emit('close');
    },

    handleSave(data) {
      const pensionType = this.mainPensionType;
      this.mainPensionType = null;
      this.$emit('save', { ...data, _pensionType: pensionType });
    },
  },
};
</script>

<style scoped>
/* Modal styling already handled by parent classes */
</style>
