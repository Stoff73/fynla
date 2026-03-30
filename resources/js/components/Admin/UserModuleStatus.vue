<template>
  <div>
    <!-- Compact module dots (inline in table) -->
    <div v-if="!expanded" class="flex gap-1">
      <span
        v-for="mod in modules"
        :key="mod.key"
        :class="[
          'w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold',
          dotClass(mod.status),
        ]"
        :title="mod.label + ': ' + mod.status"
      >
        {{ mod.letter }}
      </span>
    </div>

    <!-- Expanded detail view -->
    <div v-if="expanded" class="flex-1">
      <h4 class="text-sm font-bold text-horizon-500 mb-3">Module Status</h4>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center gap-2 text-neutral-500 text-sm">
        <div class="w-4 h-4 border-2 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin" />
        Loading...
      </div>

      <!-- Module cards -->
      <div v-else class="grid grid-cols-5 gap-3">
        <div
          v-for="mod in modulesWithData"
          :key="mod.key"
          class="bg-white border border-light-gray rounded-lg p-3"
        >
          <div class="flex items-center gap-2 mb-2">
            <span
              :class="[
                'w-6 h-6 rounded flex items-center justify-center text-[10px] font-bold',
                dotClass(mod.status),
              ]"
            >
              {{ mod.letter }}
            </span>
            <span class="text-xs font-semibold text-horizon-500">{{ mod.label }}</span>
          </div>

          <!-- Sub-areas -->
          <div v-if="mod.subAreas" class="space-y-1">
            <div
              v-for="(area, areaKey) in mod.subAreas"
              :key="areaKey"
              class="flex items-center justify-between text-[11px]"
            >
              <span class="text-neutral-500">{{ formatAreaLabel(areaKey) }}</span>
              <span class="text-horizon-500 font-medium">
                <template v-if="area.count !== undefined">{{ area.count }}</template>
                <template v-else-if="area.exists !== undefined">
                  <span v-if="area.exists" class="text-spring-500">Yes</span>
                  <span v-else class="text-neutral-400">No</span>
                </template>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import adminService from '../../services/adminService';

import logger from '@/utils/logger';
export default {
  name: 'UserModuleStatus',

  props: {
    userId: {
      type: [Number, String],
      required: true,
    },
    expanded: {
      type: Boolean,
      default: false,
    },
  },

  data() {
    return {
      loading: false,
      statusData: null,
    };
  },

  computed: {
    modules() {
      const defaultModules = [
        { key: 'protection', letter: 'P', label: 'Protection', status: 'empty' },
        { key: 'savings', letter: 'S', label: 'Savings', status: 'empty' },
        { key: 'investment', letter: 'I', label: 'Investment', status: 'empty' },
        { key: 'retirement', letter: 'R', label: 'Retirement', status: 'empty' },
        { key: 'estate', letter: 'E', label: 'Estate', status: 'empty' },
      ];

      if (!this.statusData) return defaultModules;

      return defaultModules.map((mod) => ({
        ...mod,
        status: this.statusData[mod.key]?.status || 'empty',
      }));
    },

    modulesWithData() {
      if (!this.statusData) return this.modules;

      return this.modules.map((mod) => ({
        ...mod,
        subAreas: this.statusData[mod.key]?.sub_areas || null,
      }));
    },
  },

  watch: {
    expanded: {
      immediate: true,
      handler(val) {
        if (val && !this.statusData) {
          this.loadStatus();
        }
      },
    },
  },

  async mounted() {
    if (!this.expanded) {
      await this.loadStatus();
    }
  },

  methods: {
    async loadStatus() {
      this.loading = true;
      try {
        const response = await adminService.getUserModuleStatus(this.userId);
        if (response.data.success) {
          this.statusData = response.data.data;
        }
      } catch (err) {
        logger.error('Failed to load module status:', err);
      } finally {
        this.loading = false;
      }
    },

    dotClass(status) {
      switch (status) {
        case 'complete':
          return 'bg-spring-500 text-white';
        case 'partial':
          return 'bg-violet-500 text-white';
        case 'skipped':
          return 'bg-eggshell-500 text-horizon-300 line-through border border-light-gray';
        default:
          return 'bg-light-gray text-neutral-500';
      }
    },

    formatAreaLabel(key) {
      return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
    },
  },
};
</script>
