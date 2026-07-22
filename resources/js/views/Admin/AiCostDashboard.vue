<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
          <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">AI cost attribution</h1>
          <p class="text-sm text-neutral-500 mt-1">Fyn spend by action, mode and stage over the last {{ rangeDays }} days</p>
        </div>
        <div class="flex gap-2">
          <button
            v-for="opt in rangeOptions"
            :key="opt"
            type="button"
            class="px-3 py-1.5 text-sm rounded-full transition-colors"
            :class="days === opt ? 'bg-horizon-500 text-white' : 'bg-light-gray text-horizon-500 hover:bg-savannah-100'"
            @click="setDays(opt)"
          >
            {{ opt }}d
          </button>
        </div>
      </header>

      <div v-if="loading" class="flex items-center justify-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <div v-else-if="error" class="card-lg text-center text-raspberry-500 py-10">{{ error }}</div>

      <template v-else>
        <!-- Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
          <div class="card-lg">
            <p class="text-sm text-neutral-500">Total spend</p>
            <p class="text-2xl font-black text-horizon-500 mt-1">{{ gbp(data.total_gbp) }}</p>
          </div>
          <div class="card-lg">
            <p class="text-sm text-neutral-500">LLM calls</p>
            <p class="text-2xl font-black text-horizon-500 mt-1">{{ asCount(data.total_calls) }}</p>
          </div>
          <div class="card-lg">
            <p class="text-sm text-neutral-500">Unpriced calls</p>
            <p class="text-2xl font-black text-horizon-500 mt-1">{{ asCount(data.unpriced_calls) }}</p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <section v-for="section in sections" :key="section.key" class="card-lg">
            <h2 class="text-lg font-bold text-horizon-500 mb-3">{{ section.title }}</h2>

            <div v-if="section.rows && section.rows.length" class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-left text-neutral-500 border-b border-light-gray">
                    <th class="py-2 pr-3 font-medium">{{ section.label }}</th>
                    <th class="py-2 px-3 font-medium text-right">Calls</th>
                    <th class="py-2 pl-3 font-medium text-right">Spend</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in section.rows" :key="i" class="border-b border-light-gray last:border-0">
                    <td class="py-2 pr-3 text-horizon-500">{{ row[section.key] }}</td>
                    <td class="py-2 px-3 text-right text-neutral-500">{{ asCount(row.calls) }}</td>
                    <td class="py-2 pl-3 text-right font-medium text-horizon-500">{{ gbp(row.gbp_cost) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-sm text-neutral-500">No spend recorded in this range.</p>
          </section>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/services/api';

export default {
  name: 'AiCostDashboard',
  components: { AppLayout },
  data() {
    return {
      loading: true,
      error: null,
      days: 30,
      rangeOptions: [7, 30, 90],
      data: {
        range_days: 30,
        total_gbp: 0,
        total_calls: 0,
        unpriced_calls: 0,
        by_action_type: [],
        by_session_mode: [],
        by_stage: [],
        by_day: [],
      },
    };
  },
  computed: {
    rangeDays() {
      return this.data.range_days ?? this.days;
    },
    sections() {
      return [
        { title: 'By action type', label: 'Action type', key: 'action_type', rows: this.data.by_action_type },
        { title: 'By session mode', label: 'Session mode', key: 'session_mode', rows: this.data.by_session_mode },
        { title: 'By stage', label: 'Stage', key: 'stage', rows: this.data.by_stage },
        { title: 'By day', label: 'Day', key: 'day', rows: this.data.by_day },
      ];
    },
  },
  mounted() {
    this.fetchData();
  },
  methods: {
    async fetchData() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await api.get('/admin/ai-cost-dashboard', { params: { days: this.days } });
        this.data = data.data;
      } catch (e) {
        this.error = 'Could not load cost attribution. Please try again.';
      } finally {
        this.loading = false;
      }
    },
    setDays(days) {
      this.days = days;
      this.fetchData();
    },
    asCount(value) {
      return Number(value || 0).toLocaleString();
    },
    // Spend is sub-penny per call, so show four decimals of GBP rather than
    // rounding to whole pence (which would read as £0.00).
    gbp(value) {
      return `£${Number(value || 0).toFixed(4)}`;
    },
  },
};
</script>
