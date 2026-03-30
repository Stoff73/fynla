<template>
  <div class="what-if-scenarios bg-white rounded-lg shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b border-light-gray">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h2 class="text-2xl font-bold text-horizon-500">What-If Scenarios</h2>
          <p class="mt-1 text-sm text-neutral-500">Model portfolio changes and compare outcomes</p>
        </div>
        <button
          @click="showCreateModal = true"
          class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 hover:bg-raspberry-600 rounded-button transition-colors duration-200"
        >
          + Create Scenario
        </button>
      </div>

      <!-- Statistics Cards -->
      <div v-if="scenarioStats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="text-sm font-medium text-violet-600 mb-1">Total</div>
          <div class="text-2xl font-bold text-violet-900">{{ scenarioStats.total }}</div>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4 border border-light-gray">
          <div class="text-sm font-medium text-neutral-500 mb-1">Draft</div>
          <div class="text-2xl font-bold text-horizon-500">{{ scenarioStats.draft }}</div>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="text-sm font-medium text-violet-600 mb-1">Running</div>
          <div class="text-2xl font-bold text-violet-900">{{ scenarioStats.running }}</div>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="text-sm font-medium text-spring-600 mb-1">Completed</div>
          <div class="text-2xl font-bold text-spring-900">{{ scenarioStats.completed }}</div>
        </div>
        <div class="bg-eggshell-500 rounded-lg p-4">
          <div class="text-sm font-medium text-violet-600 mb-1">Saved</div>
          <div class="text-2xl font-bold text-violet-900">{{ scenarioStats.saved }}</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-4">
        <div>
          <label class="block text-sm font-medium text-neutral-500 mb-1">Status</label>
          <select
            v-model="filters.status"
            @change="applyFilters"
            class="px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
          >
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="running">Running</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-neutral-500 mb-1">Type</label>
          <select
            v-model="filters.type"
            @change="applyFilters"
            class="px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
          >
            <option value="">All Types</option>
            <option value="custom">Custom</option>
            <option value="template">Template</option>
            <option value="comparison">Comparison</option>
          </select>
        </div>

        <div class="flex items-end">
          <label class="flex items-center cursor-pointer">
            <input
              type="checkbox"
              v-model="filters.saved_only"
              @change="applyFilters"
              class="w-4 h-4 text-violet-600 border-horizon-300 rounded focus:ring-violet-500"
            />
            <span class="ml-2 text-sm font-medium text-neutral-500">Saved only</span>
          </label>
        </div>

        <div class="flex items-end">
          <button
            @click="clearFilters"
            class="px-4 py-2 text-sm font-medium text-neutral-500 bg-savannah-100 hover:bg-savannah-200 rounded-button transition-colors duration-200"
          >
            Clear Filters
          </button>
        </div>
      </div>
    </div>

    <!-- Scenarios List -->
    <div class="p-6">
      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <svg class="animate-spin h-12 w-12 mx-auto text-violet-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="mt-4 text-neutral-500">Loading scenarios...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="!scenarios || scenarios.length === 0" class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-horizon-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-horizon-500">No scenarios found</h3>
        <p class="mt-2 text-neutral-500">Create your first what-if scenario to explore different outcomes.</p>
        <button
          @click="showCreateModal = true"
          class="mt-4 px-4 py-2 text-sm font-medium text-white bg-raspberry-500 hover:bg-raspberry-600 rounded-button transition-colors duration-200"
        >
          Create Scenario
        </button>
      </div>

      <!-- Scenarios Cards -->
      <div v-else class="space-y-4">
        <div
          v-for="scenario in scenarios"
          :key="scenario.id"
          class="border rounded-lg p-5 hover:shadow-md transition-shadow duration-200"
          :class="getScenarioBorderClass(scenario)"
        >
          <!-- Header Row -->
          <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-2">
                <!-- Status Badge -->
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getStatusClass(scenario.status)"
                >
                  {{ formatStatus(scenario.status) }}
                </span>

                <!-- Type Badge -->
                <span
                  class="px-2 py-1 text-xs font-semibold rounded"
                  :class="getTypeClass(scenario.scenario_type)"
                >
                  {{ formatType(scenario.scenario_type) }}
                </span>

                <!-- Saved Star -->
                <button
                  v-if="scenario.is_saved"
                  @click="unsaveScenario(scenario.id)"
                  class="text-violet-500 hover:text-violet-600"
                  title="Remove bookmark"
                >
                  <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                </button>
                <button
                  v-else
                  @click="saveScenario(scenario.id)"
                  class="text-horizon-400 hover:text-violet-500"
                  title="Bookmark scenario"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                  </svg>
                </button>
              </div>

              <h3 class="text-lg font-semibold text-horizon-500">{{ scenario.scenario_name }}</h3>
              <p v-if="scenario.description" class="text-sm text-neutral-500 mt-1">{{ scenario.description }}</p>
              <p v-if="scenario.template_name" class="text-xs text-neutral-500 mt-1">Template: {{ scenario.template_name }}</p>
            </div>

            <!-- Action Menu -->
            <div class="relative">
              <button
                @click="toggleActionMenu(scenario.id)"
                class="p-2 text-horizon-400 hover:text-neutral-500 rounded-lg hover:bg-savannah-100"
              >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                </svg>
              </button>

              <!-- Dropdown Menu -->
              <div
                v-if="activeMenuId === scenario.id"
                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-light-gray z-10"
              >
                <button
                  v-if="scenario.status === 'draft'"
                  @click="runScenario(scenario.id)"
                  class="block w-full text-left px-4 py-2 text-sm text-spring-700 hover:bg-spring-50"
                >
                  Run Simulation
                </button>
                <button
                  v-if="scenario.status === 'completed'"
                  @click="viewResults(scenario)"
                  class="block w-full text-left px-4 py-2 text-sm text-violet-700 hover:bg-violet-50"
                >
                  View Results
                </button>
                <button
                  @click="selectForComparison(scenario)"
                  class="block w-full text-left px-4 py-2 text-sm text-violet-700 hover:bg-violet-50"
                >
                  Compare
                </button>
                <button
                  @click="deleteScenario(scenario.id)"
                  class="block w-full text-left px-4 py-2 text-sm text-raspberry-700 hover:bg-raspberry-50"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>

          <!-- Scenario Info -->
          <div class="flex flex-wrap gap-4 text-sm text-neutral-500">
            <div>Created: {{ formatDate(scenario.created_at) }}</div>
            <div v-if="scenario.completed_at">Completed: {{ formatDate(scenario.completed_at) }}</div>
          </div>
        </div>
      </div>

      <!-- Comparison Selection Bar -->
      <div
        v-if="selectedForComparison.length > 0"
        class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-white rounded-lg shadow-xl border border-light-gray p-4 z-50"
      >
        <div class="flex items-center gap-4">
          <span class="text-sm font-medium text-neutral-500">
            {{ selectedForComparison.length }} scenario(s) selected for comparison
          </span>
          <button
            @click="compareSelectedScenarios"
            :disabled="selectedForComparison.length < 2"
            class="px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 disabled:bg-savannah-300 disabled:cursor-not-allowed rounded-button transition-colors duration-200"
          >
            Compare
          </button>
          <button
            @click="selectedForComparison = []"
            class="px-4 py-2 text-sm font-medium text-neutral-500 bg-savannah-100 hover:bg-savannah-200 rounded-button transition-colors duration-200"
          >
            Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Create Scenario Modal -->
    <div
      v-if="showCreateModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="closeCreateModal"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <h3 class="text-lg font-semibold text-horizon-500 mb-4">Create What-If Scenario</h3>

          <!-- Template Selection -->
          <div class="mb-6">
            <h4 class="text-sm font-medium text-neutral-500 mb-3">Choose a Template or Create Custom</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <button
                v-for="template in scenarioTemplates"
                :key="template.id"
                @click="selectTemplate(template)"
                class="text-left p-4 border rounded-lg hover:border-violet-500 hover:bg-violet-50 transition-colors duration-200"
                :class="selectedTemplate?.id === template.id ? 'border-violet-500 bg-violet-50' : 'border-light-gray'"
              >
                <div class="font-medium text-horizon-500">{{ template.name }}</div>
                <div class="text-xs text-neutral-500 mt-1">{{ template.description }}</div>
              </button>
              <button
                @click="selectTemplate(null)"
                class="text-left p-4 border rounded-lg hover:border-violet-500 hover:bg-violet-50 transition-colors duration-200"
                :class="selectedTemplate === null ? 'border-violet-500 bg-violet-50' : 'border-light-gray'"
              >
                <div class="font-medium text-horizon-500">Custom Scenario</div>
                <div class="text-xs text-neutral-500 mt-1">Build your own scenario from scratch</div>
              </button>
            </div>
          </div>

          <!-- Scenario Details Form -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">Scenario Name</label>
              <input
                v-model="newScenario.scenario_name"
                type="text"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                placeholder="e.g., Early Retirement Scenario"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-neutral-500 mb-1">Description</label>
              <textarea
                v-model="newScenario.description"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                rows="3"
                placeholder="Describe what this scenario models..."
              ></textarea>
            </div>

            <!-- Parameter Inputs (simplified for this example) -->
            <div v-if="selectedTemplate" class="bg-eggshell-500 rounded-lg p-4">
              <p class="text-sm text-violet-800">
                This scenario will use the pre-configured parameters from the "{{ selectedTemplate.name }}" template.
              </p>
            </div>
          </div>

          <!-- Modal Actions -->
          <div class="flex justify-end gap-3 mt-6">
            <button
              @click="closeCreateModal"
              class="px-4 py-2 text-sm font-medium text-neutral-500 bg-savannah-100 hover:bg-savannah-200 rounded-button transition-colors duration-200"
            >
              Cancel
            </button>
            <button
              @click="createScenario"
              :disabled="!newScenario.scenario_name"
              class="px-4 py-2 text-sm font-medium text-white bg-raspberry-500 hover:bg-raspberry-600 disabled:bg-savannah-300 disabled:cursor-not-allowed rounded-button transition-colors duration-200"
            >
              Create Scenario
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapGetters, mapActions } from 'vuex';

import logger from '@/utils/logger';
export default {
  name: 'WhatIfScenariosBuilder',

  data() {
    return {
      filters: {
        status: '',
        type: '',
        saved_only: false,
      },
      activeMenuId: null,
      showCreateModal: false,
      selectedTemplate: undefined,
      newScenario: {
        scenario_name: '',
        description: '',
        scenario_type: 'custom',
        template_name: null,
        parameters: {},
      },
      selectedForComparison: [],
    };
  },

  computed: {
    ...mapState('investment', ['loading', 'error']),
    ...mapGetters('investment', [
      'scenarioTemplates',
      'investmentScenarios',
      'scenarioStats',
    ]),

    scenarios() {
      return this.investmentScenarios;
    },
  },

  mounted() {
    this.loadScenarios();
    this.loadTemplates();
  },

  methods: {
    ...mapActions('investment', [
      'fetchScenarioTemplates',
      'fetchInvestmentScenarios',
      'createInvestmentScenario',
      'runInvestmentScenario',
      'saveInvestmentScenario',
      'unsaveInvestmentScenario',
      'deleteInvestmentScenario',
      'compareInvestmentScenarios',
    ]),

    async loadTemplates() {
      try {
        await this.fetchScenarioTemplates();
      } catch (error) {
        logger.error('Failed to load templates:', error);
      }
    },

    async loadScenarios() {
      try {
        await this.fetchInvestmentScenarios(this.filters);
      } catch (error) {
        logger.error('Failed to load scenarios:', error);
      }
    },

    applyFilters() {
      this.loadScenarios();
    },

    clearFilters() {
      this.filters = {
        status: '',
        type: '',
        saved_only: false,
      };
      this.loadScenarios();
    },

    toggleActionMenu(id) {
      this.activeMenuId = this.activeMenuId === id ? null : id;
    },

    selectTemplate(template) {
      this.selectedTemplate = template;
      if (template) {
        this.newScenario.scenario_name = template.name;
        this.newScenario.description = template.description;
        this.newScenario.scenario_type = 'template';
        this.newScenario.template_name = template.id;
        this.newScenario.parameters = template.parameters;
      } else {
        this.newScenario.scenario_type = 'custom';
        this.newScenario.template_name = null;
        this.newScenario.parameters = {};
      }
    },

    async createScenario() {
      if (!this.newScenario.scenario_name) return;

      try {
        await this.createInvestmentScenario(this.newScenario);
        this.closeCreateModal();
        await this.loadScenarios();
      } catch (error) {
        logger.error('Failed to create scenario:', error);
      }
    },

    closeCreateModal() {
      this.showCreateModal = false;
      this.selectedTemplate = undefined;
      this.newScenario = {
        scenario_name: '',
        description: '',
        scenario_type: 'custom',
        template_name: null,
        parameters: {},
      };
    },

    async runScenario(id) {
      try {
        await this.runInvestmentScenario(id);
        this.activeMenuId = null;
        await this.loadScenarios();
      } catch (error) {
        logger.error('Failed to run scenario:', error);
      }
    },

    viewResults(scenario) {
      // This would open a results modal/view
      this.activeMenuId = null;
    },

    selectForComparison(scenario) {
      if (scenario.status !== 'completed') {
        alert('Only completed scenarios can be compared');
        return;
      }

      const index = this.selectedForComparison.findIndex(s => s.id === scenario.id);
      if (index === -1) {
        this.selectedForComparison.push(scenario);
      } else {
        this.selectedForComparison.splice(index, 1);
      }
      this.activeMenuId = null;
    },

    async compareSelectedScenarios() {
      if (this.selectedForComparison.length < 2) {
        alert('Please select at least 2 scenarios to compare');
        return;
      }

      try {
        const scenarioIds = this.selectedForComparison.map(s => s.id);
        await this.compareInvestmentScenarios(scenarioIds);
        // Show comparison results (would open a modal/view)
        this.selectedForComparison = [];
      } catch (error) {
        // Comparison failed silently
      }
    },

    async saveScenario(id) {
      try {
        await this.saveInvestmentScenario(id);
      } catch (error) {
        logger.error('Failed to save scenario:', error);
      }
    },

    async unsaveScenario(id) {
      try {
        await this.unsaveInvestmentScenario(id);
      } catch (error) {
        logger.error('Failed to unsave scenario:', error);
      }
    },

    async deleteScenario(id) {
      if (!confirm('Are you sure you want to delete this scenario? This action cannot be undone.')) {
        return;
      }

      try {
        await this.deleteInvestmentScenario(id);
        this.activeMenuId = null;
      } catch (error) {
        logger.error('Failed to delete scenario:', error);
      }
    },

    getScenarioBorderClass(scenario) {
      if (scenario.status === 'completed') return 'border-l-4 border-spring-500 bg-white';
      if (scenario.status === 'running') return 'border-l-4 border-violet-500 bg-white';
      if (scenario.status === 'failed') return 'border-l-4 border-raspberry-500 bg-white';
      return 'border-light-gray bg-white';
    },

    getStatusClass(status) {
      const classes = {
        draft: 'bg-savannah-100 text-horizon-500',
        running: 'bg-violet-500 text-white',
        completed: 'bg-spring-500 text-white',
        failed: 'bg-raspberry-500 text-white',
      };
      return classes[status] || 'bg-savannah-100 text-horizon-500';
    },

    getTypeClass(type) {
      const classes = {
        custom: 'bg-violet-500 text-white',
        template: 'bg-violet-500 text-white',
        comparison: 'bg-violet-500 text-white',
      };
      return classes[type] || 'bg-savannah-100 text-horizon-500';
    },

    formatStatus(status) {
      const labels = {
        draft: 'Draft',
        running: 'Running',
        completed: 'Completed',
        failed: 'Failed',
      };
      return labels[status] || status;
    },

    formatType(type) {
      const labels = {
        custom: 'Custom',
        template: 'Template',
        comparison: 'Comparison',
      };
      return labels[type] || type;
    },

    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },
  },
};
</script>

<style scoped>
/* Additional custom styles if needed */
</style>
