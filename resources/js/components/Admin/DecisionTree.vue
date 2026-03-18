<template>
  <div>
    <!-- Stats bar -->
    <div class="flex gap-4 mb-6">
      <div class="bg-white border border-light-gray rounded-xl p-[18px_20px] flex-1 shadow-card">
        <div class="text-[28px] font-black text-horizon-500 tracking-tight">{{ stats.total }}</div>
        <div class="text-xs text-neutral-500 mt-1">Total</div>
      </div>
      <div class="bg-white border border-light-gray rounded-xl p-[18px_20px] flex-1 shadow-card">
        <div class="text-[28px] font-black text-spring-500 tracking-tight">{{ stats.enabled }}</div>
        <div class="text-xs text-neutral-500 mt-1">Enabled</div>
      </div>
      <div class="bg-white border border-light-gray rounded-xl p-[18px_20px] flex-1 shadow-card">
        <div class="text-[28px] font-black text-neutral-500 tracking-tight">{{ stats.disabled }}</div>
        <div class="text-xs text-neutral-500 mt-1">Disabled</div>
      </div>
      <div class="bg-white border border-light-gray rounded-xl p-[18px_20px] flex-1 shadow-card">
        <div class="text-[28px] font-black text-raspberry-500 tracking-tight">{{ stats.critical_high }}</div>
        <div class="text-xs text-neutral-500 mt-1">Critical/High</div>
      </div>
      <div class="bg-white border border-light-gray rounded-xl p-[18px_20px] flex-1 shadow-card">
        <div class="text-[28px] font-black text-violet-500 tracking-tight">{{ stats.medium }}</div>
        <div class="text-xs text-neutral-500 mt-1">Medium</div>
      </div>
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-2xl font-bold text-horizon-500">{{ moduleLabel }} Module &mdash; Decision Tree</h2>
        <p class="text-sm text-neutral-500 mt-0.5">Click any node to view and edit its configuration</p>
      </div>
      <div class="flex gap-2">
        <button
          @click="showSearch = !showSearch"
          class="px-3 py-1.5 border border-light-gray rounded-md text-sm text-neutral-500 hover:bg-savannah-100 transition-colors flex items-center gap-1.5"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          Search
        </button>
        <button
          @click="showFilter = !showFilter"
          class="px-3 py-1.5 border border-light-gray rounded-md text-sm text-neutral-500 hover:bg-savannah-100 transition-colors flex items-center gap-1.5"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          Filter
        </button>
        <button
          @click="collapsed = !collapsed"
          class="px-3 py-1.5 border border-light-gray rounded-md text-sm text-neutral-500 hover:bg-savannah-100 transition-colors"
        >
          {{ collapsed ? 'Expand All' : 'Collapse All' }}
        </button>
        <button
          @click="$emit('add')"
          class="px-3 py-1.5 bg-raspberry-500 text-white rounded-md text-sm hover:bg-raspberry-600 transition-colors font-medium"
        >
          + Add Action
        </button>
      </div>
    </div>

    <!-- Search input -->
    <div v-if="showSearch" class="mb-4">
      <input
        v-model="searchQuery"
        type="text"
        class="px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full text-sm"
        placeholder="Search by key, title, or category..."
      />
    </div>

    <!-- Filter dropdown -->
    <div v-if="showFilter" class="mb-4 flex gap-3">
      <select
        v-model="filterPriority"
        class="px-3 py-1.5 border border-light-gray rounded-md text-sm text-horizon-500 bg-white"
      >
        <option value="">All Priorities</option>
        <option value="critical">Critical</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
      </select>
      <select
        v-model="filterEnabled"
        class="px-3 py-1.5 border border-light-gray rounded-md text-sm text-horizon-500 bg-white"
      >
        <option value="">All States</option>
        <option value="enabled">Enabled</option>
        <option value="disabled">Disabled</option>
      </select>
    </div>

    <!-- Legend bar -->
    <div class="flex gap-5 mb-5 p-3 px-4 bg-savannah-100 rounded-lg border border-light-gray items-center flex-wrap">
      <div class="flex items-center gap-1.5 text-xs text-neutral-500">
        <span class="w-3 h-3 rounded bg-light-blue-500 inline-block" />
        User Data Input
      </div>
      <div class="flex items-center gap-1.5 text-xs text-neutral-500">
        <span class="w-3 h-3 rounded bg-violet-500 inline-block" />
        Trigger Condition
      </div>
      <div class="flex items-center gap-1.5 text-xs text-neutral-500">
        <span class="w-3 h-3 rounded bg-spring-500 inline-block" />
        Decision Logic
      </div>
      <div class="flex items-center gap-1.5 text-xs text-neutral-500">
        <span class="w-3 h-3 rounded bg-raspberry-500 inline-block" />
        Outcome/Action
      </div>
      <div class="flex items-center gap-1.5 text-xs text-neutral-500">
        <span class="w-3 h-3 rounded bg-neutral-500 inline-block" />
        Disabled
      </div>
      <div class="ml-auto flex gap-2">
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white bg-raspberry-700">CRIT</span>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white bg-raspberry-500">HIGH</span>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white bg-violet-500">MED</span>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white bg-spring-500">LOW</span>
      </div>
    </div>

    <!-- Tree canvas -->
    <div class="bg-white border border-light-gray rounded-xl p-8 min-h-[520px] shadow-card overflow-x-auto">
      <!-- Column headers -->
      <div class="flex gap-0 mb-4">
        <div class="min-w-[210px] px-3 text-center">
          <span class="text-[11px] font-bold uppercase tracking-wide text-neutral-500 bg-eggshell-500 rounded px-3 py-1.5 inline-block">
            User Data
          </span>
        </div>
        <div class="min-w-[60px]" />
        <div class="min-w-[210px] px-3 text-center">
          <span class="text-[11px] font-bold uppercase tracking-wide text-neutral-500 bg-eggshell-500 rounded px-3 py-1.5 inline-block">
            Trigger
          </span>
        </div>
        <div class="min-w-[60px]" />
        <div class="min-w-[210px] px-3 text-center">
          <span class="text-[11px] font-bold uppercase tracking-wide text-neutral-500 bg-eggshell-500 rounded px-3 py-1.5 inline-block">
            Logic
          </span>
        </div>
        <div class="min-w-[60px]" />
        <div class="min-w-[210px] px-3 text-center">
          <span class="text-[11px] font-bold uppercase tracking-wide text-neutral-500 bg-eggshell-500 rounded px-3 py-1.5 inline-block">
            Outcome
          </span>
        </div>
      </div>

      <!-- Flow rows -->
      <template v-if="!collapsed">
        <div
          v-for="def in filteredDefinitions"
          :key="def.id"
          class="flex items-center mb-4"
        >
          <!-- Data node -->
          <div class="min-w-[210px] px-3">
            <DecisionNode
              type="data"
              :label="def.tree_nodes?.data?.label || ''"
              :description="def.tree_nodes?.data?.description || ''"
              :priority="def.priority"
              :disabled="!def.is_enabled"
              @click="$emit('edit', def)"
            />
          </div>
          <!-- Arrow -->
          <div class="min-w-[60px] flex items-center justify-center">
            <svg width="40" height="2">
              <line
                x1="0" y1="1" x2="30" y2="1"
                :stroke="def.is_enabled ? 'var(--horizon-300, #8B95A8)' : 'var(--light-gray, #EEEEEE)'"
                stroke-width="2"
                :stroke-dasharray="def.is_enabled ? 'none' : '4,3'"
              />
              <polygon
                v-if="def.is_enabled"
                points="30,0 38,1 30,2"
                :fill="'var(--horizon-300, #8B95A8)'"
              />
            </svg>
          </div>
          <!-- Trigger node -->
          <div class="min-w-[210px] px-3">
            <DecisionNode
              type="trigger"
              :label="def.tree_nodes?.trigger?.label || ''"
              :description="def.tree_nodes?.trigger?.description || ''"
              :disabled="!def.is_enabled"
              @click="$emit('edit', def)"
            />
          </div>
          <!-- Arrow -->
          <div class="min-w-[60px] flex items-center justify-center">
            <svg width="40" height="2">
              <line
                x1="0" y1="1" x2="30" y2="1"
                :stroke="def.is_enabled ? 'var(--horizon-300, #8B95A8)' : 'var(--light-gray, #EEEEEE)'"
                stroke-width="2"
                :stroke-dasharray="def.is_enabled ? 'none' : '4,3'"
              />
              <polygon
                v-if="def.is_enabled"
                points="30,0 38,1 30,2"
                :fill="'var(--horizon-300, #8B95A8)'"
              />
            </svg>
          </div>
          <!-- Logic node -->
          <div class="min-w-[210px] px-3">
            <DecisionNode
              type="logic"
              :label="def.tree_nodes?.logic?.label || ''"
              :description="def.tree_nodes?.logic?.description || ''"
              :disabled="!def.is_enabled"
              @click="$emit('edit', def)"
            />
          </div>
          <!-- Arrow -->
          <div class="min-w-[60px] flex items-center justify-center">
            <svg width="40" height="2">
              <line
                x1="0" y1="1" x2="30" y2="1"
                :stroke="def.is_enabled ? 'var(--horizon-300, #8B95A8)' : 'var(--light-gray, #EEEEEE)'"
                stroke-width="2"
                :stroke-dasharray="def.is_enabled ? 'none' : '4,3'"
              />
              <polygon
                v-if="def.is_enabled"
                points="30,0 38,1 30,2"
                :fill="'var(--horizon-300, #8B95A8)'"
              />
            </svg>
          </div>
          <!-- Outcome node -->
          <div class="min-w-[210px] px-3">
            <DecisionNode
              type="outcome"
              :label="def.tree_nodes?.outcome?.label || ''"
              :description="def.tree_nodes?.outcome?.description || ''"
              :disabled="!def.is_enabled"
              @click="$emit('edit', def)"
            />
          </div>
        </div>
      </template>

      <!-- Collapsed state -->
      <div v-if="collapsed" class="text-center text-neutral-500 text-sm py-12">
        {{ filteredDefinitions.length }} action definitions collapsed.
        <button @click="collapsed = false" class="text-raspberry-500 underline ml-1">Expand</button>
      </div>

      <!-- Empty state -->
      <div v-if="!collapsed && filteredDefinitions.length === 0" class="text-center text-neutral-500 text-sm py-12">
        No action definitions found for this module.
      </div>
    </div>
  </div>
</template>

<script>
import DecisionNode from './DecisionNode.vue';

export default {
  name: 'DecisionTree',

  components: {
    DecisionNode,
  },

  props: {
    module: {
      type: String,
      required: true,
    },
    definitions: {
      type: Array,
      default: () => [],
    },
    stats: {
      type: Object,
      default: () => ({ total: 0, enabled: 0, disabled: 0, critical_high: 0, medium: 0 }),
    },
    moduleLabel: {
      type: String,
      default: '',
    },
  },

  emits: ['edit', 'add'],

  data() {
    return {
      collapsed: false,
      showSearch: false,
      showFilter: false,
      searchQuery: '',
      filterPriority: '',
      filterEnabled: '',
    };
  },

  computed: {
    filteredDefinitions() {
      let result = this.definitions;

      if (this.searchQuery) {
        const q = this.searchQuery.toLowerCase();
        result = result.filter(
          (d) =>
            d.key?.toLowerCase().includes(q) ||
            d.title_template?.toLowerCase().includes(q) ||
            d.category?.toLowerCase().includes(q),
        );
      }

      if (this.filterPriority) {
        result = result.filter((d) => d.priority === this.filterPriority);
      }

      if (this.filterEnabled) {
        const isEnabled = this.filterEnabled === 'enabled';
        result = result.filter((d) => d.is_enabled === isEnabled);
      }

      return result;
    },
  },
};
</script>
