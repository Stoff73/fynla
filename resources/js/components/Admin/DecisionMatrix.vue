<template>
  <div>
    <!-- Module sub-tabs -->
    <div class="bg-white px-6 pt-3 flex gap-2 rounded-t-xl border border-b-0 border-light-gray">
      <button
        v-for="tab in moduleTabs"
        :key="tab.key"
        @click="activeModule = tab.key"
        :class="[
          'px-4 py-2 text-xs font-semibold rounded-t-lg border border-light-gray border-b-0 cursor-pointer transition-all duration-150',
          activeModule === tab.key
            ? 'bg-eggshell-500 text-raspberry-500'
            : 'bg-white text-neutral-500 hover:text-horizon-500 hover:bg-savannah-100',
        ]"
      >
        {{ tab.label }}
        <span
          :class="[
            'text-[10px] font-bold px-1.5 py-px rounded-full ml-1.5',
            activeModule === tab.key
              ? 'bg-raspberry-500 text-white'
              : 'bg-neutral-500 text-white',
          ]"
        >
          {{ tab.count }}
        </span>
      </button>
    </div>

    <!-- Content area -->
    <div class="border border-light-gray border-t-0 rounded-b-xl bg-eggshell-500 p-6">
      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-12">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin" />
      </div>

      <!-- Error -->
      <div v-else-if="error" class="text-center py-12 text-raspberry-500">
        {{ error }}
      </div>

      <!-- Tree -->
      <DecisionTree
        v-else
        :module="activeModule"
        :definitions="allDefinitions"
        :stats="stats"
        :module-label="activeModuleLabel"
        @edit="openDrawer"
        @add="openNewDrawer"
      />
    </div>

    <!-- Drawer -->
    <ActionDefinitionDrawer
      :definition="drawerDefinition"
      :module="activeModule"
      :module-config="activeModuleConfig"
      :saving="saving"
      @save="handleSave"
      @close="closeDrawer"
    />
  </div>
</template>

<script>
import actionDefinitionService from '../../services/actionDefinitionService';
import { MODULE_CONFIGS } from '../../constants/moduleConfigs';
import DecisionTree from './DecisionTree.vue';
import ActionDefinitionDrawer from './ActionDefinitionDrawer.vue';

import logger from '@/utils/logger';
export default {
  name: 'DecisionMatrix',

  components: {
    DecisionTree,
    ActionDefinitionDrawer,
  },

  data() {
    return {
      activeModule: 'protection',
      loading: false,
      error: null,
      matrixData: null,
      drawerDefinition: null,
      moduleCounts: {},
      saving: false,
    };
  },

  computed: {
    moduleTabs() {
      return [
        { key: 'protection', label: 'Protection', count: this.moduleCounts.protection || 0 },
        { key: 'savings', label: 'Cash & Savings', count: this.moduleCounts.savings || 0 },
        { key: 'investment', label: 'Investments', count: this.moduleCounts.investment || 0 },
        { key: 'retirement', label: 'Retirement', count: this.moduleCounts.retirement || 0 },
        { key: 'estate', label: 'Estate Planning', count: this.moduleCounts.estate || 0 },
        { key: 'tax', label: 'Tax', count: this.moduleCounts.tax || 0 },
      ];
    },

    activeModuleLabel() {
      return MODULE_CONFIGS[this.activeModule]?.label || this.activeModule;
    },

    activeModuleConfig() {
      return MODULE_CONFIGS[this.activeModule] || {};
    },

    stats() {
      return this.matrixData?.stats || { total: 0, enabled: 0, disabled: 0, critical_high: 0, medium: 0 };
    },

    allDefinitions() {
      if (!this.matrixData?.categories) return [];
      return this.matrixData.categories.flatMap((cat) => cat.definitions || []);
    },
  },

  watch: {
    activeModule: {
      immediate: true,
      handler() {
        this.loadMatrix();
      },
    },
  },

  async mounted() {
    await this.loadAllCounts();
  },

  methods: {
    async loadMatrix() {
      this.loading = true;
      this.error = null;
      try {
        const response = await actionDefinitionService.getDecisionMatrix(this.activeModule);
        this.matrixData = response.data.data;
        // Update count for this module
        this.moduleCounts[this.activeModule] = this.matrixData?.stats?.total || 0;
      } catch (err) {
        this.error = 'Failed to load decision matrix data.';
        logger.error('Decision matrix load error:', err);
      } finally {
        this.loading = false;
      }
    },

    async loadAllCounts() {
      const modules = ['protection', 'savings', 'investment', 'retirement', 'estate', 'tax'];
      const results = await Promise.allSettled(
        modules.map((m) => actionDefinitionService.getDefinitions(m)),
      );
      results.forEach((result, i) => {
        if (result.status === 'fulfilled') {
          this.moduleCounts[modules[i]] = result.value.data?.data?.length || 0;
        }
      });
    },

    openDrawer(definition) {
      this.drawerDefinition = { ...definition };
    },

    openNewDrawer() {
      this.drawerDefinition = {
        key: '',
        source: 'agent',
        title_template: '',
        description_template: '',
        action_template: '',
        category: '',
        priority: 'medium',
        scope: 'portfolio',
        what_if_impact_type: 'default',
        trigger_config: { condition: '' },
        is_enabled: true,
        sort_order: 100,
        notes: '',
      };
    },

    async handleSave(formData) {
      this.saving = true;
      try {
        if (formData.id) {
          await actionDefinitionService.updateDefinition(this.activeModule, formData.id, formData);
        } else {
          await actionDefinitionService.createDefinition(this.activeModule, formData);
        }
        this.closeDrawer();
        await this.loadMatrix();
      } catch (err) {
        logger.error('Save error:', err);
        alert('Failed to save. Check the console for details.');
      } finally {
        this.saving = false;
      }
    },

    closeDrawer() {
      this.drawerDefinition = null;
    },
  },
};
</script>
