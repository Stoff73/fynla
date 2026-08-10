<template>
  <section class="m-card mnwf" data-testid="net-worth-forecast">
    <div class="mnwf__heading">
      <div>
        <p class="m-section-label">Projected net worth</p>
        <p class="m-sub">A forward view using your recorded balances, contributions and disclosed assumptions.</p>
      </div>
    </div>

    <div v-if="loading" class="m-state mnwf__state">
      <p class="m-sub">Loading your projection…</p>
    </div>

    <div v-else-if="error" class="m-state mnwf__state">
      <p class="m-err">{{ error }}</p>
      <button type="button" class="m-btn" @click="load()">Try again</button>
    </div>

    <template v-else-if="forecast">
      <div class="mnwf__provenance">
        <span>Recorded starting point: {{ displayDate(forecast.recorded_as_of) }}</span>
        <span v-if="firstProjectedYear">Projected from {{ firstProjectedYear }}</span>
      </div>

      <apexchart
        v-if="chartSeries[0].data.length"
        type="line"
        height="250"
        :options="chartOptions"
        :series="chartSeries"
      />

      <div v-if="latestPoint" class="mnwf__latest">
        <span>Projected in {{ latestPoint.calendar_year }}</span>
        <strong>{{ formatCurrency(latestPoint.net_worth) }}</strong>
      </div>

      <div v-if="forecast.warnings?.length" class="mnwf__warnings">
        <p v-for="warning in forecast.warnings" :key="warning">{{ warning }}</p>
      </div>

      <form class="mnwf__form" @submit.prevent="save">
        <div class="mnwf__editor-heading">
          <div>
            <p class="mnwf__title">Forecast assumptions</p>
            <p class="m-sub">Percentages are applied independently to each recorded category.</p>
          </div>
          <label class="mnwf__basis">
            <span>Basis</span>
            <select v-model="basis" data-testid="forecast-basis" :disabled="busy">
              <option value="nominal">Nominal</option>
              <option value="real">Real</option>
            </select>
          </label>
        </div>

        <div v-for="category in categoryDefinitions" :key="category.key" class="mnwf__assumption">
          <label :for="`forecast-${category.key}`" class="mnwf__field-label">{{ category.label }}</label>
          <div class="mnwf__input-wrap">
            <input
              :id="`forecast-${category.key}`"
              v-model.number="draft[category.key]"
              :data-testid="`forecast-rate-${category.key}`"
              type="number"
              inputmode="decimal"
              step="0.001"
              min="-20"
              max="30"
              :disabled="busy"
            />
            <span>%</span>
          </div>
          <p :data-testid="`forecast-meta-${category.key}`" class="mnwf__meta">
            {{ sourceLabel(assumptionFor(category.key).source) }} ·
            {{ assumptionFor(category.key).effective_from }} ·
            {{ basisLabel(assumptionFor(category.key).basis) }}
          </p>
          <p
            v-if="validationErrors[category.key]?.length"
            :data-testid="`forecast-error-${category.key}`"
            class="m-err mnwf__field-error"
          >
            {{ validationErrors[category.key][0] }}
          </p>
        </div>

        <p v-if="feedback" class="mnwf__feedback" role="status">{{ feedback }}</p>
        <p v-if="saveError" class="m-err" role="alert">{{ saveError }}</p>

        <div class="mnwf__actions">
          <button type="submit" class="m-btn" data-testid="forecast-save" :disabled="busy">
            {{ saving ? 'Saving…' : 'Save assumptions' }}
          </button>
          <button
            type="button"
            class="mnwf__reset"
            data-testid="forecast-reset"
            :disabled="busy"
            @click="reset"
          >
            {{ resetting ? 'Resetting…' : 'Reset to defaults' }}
          </button>
        </div>
      </form>
    </template>
  </section>
</template>

<script>
import VueApexCharts from 'vue3-apexcharts';
import { apiDelete, apiGet, apiPut } from '../api.js';
import { handleAuthExpiry } from '../authExpiry.js';
import { store } from '../store.js';
import { formatCurrency, formatCurrencyCompact } from '../../js/utils/currency.js';
import { CHART_COLORS, CHART_DEFAULTS, TEXT_COLORS } from '../../js/constants/designSystem.js';

const CATEGORY_DEFINITIONS = [
  { key: 'property', label: 'Property' },
  { key: 'investments', label: 'Investments' },
  { key: 'pensions', label: 'Pensions' },
  { key: 'cash', label: 'Cash & savings' },
  { key: 'business', label: 'Business interests' },
  { key: 'valuables', label: 'Valuables' },
  { key: 'mortgages', label: 'Mortgages' },
  { key: 'other_liabilities', label: 'Other liabilities' },
];

export default {
  name: 'NetWorthForecast',
  components: { apexchart: VueApexCharts },
  data: () => ({
    loading: true,
    error: '',
    forecast: null,
    draft: {},
    basis: 'nominal',
    saving: false,
    resetting: false,
    feedback: '',
    saveError: '',
    validationErrors: {},
  }),
  computed: {
    categoryDefinitions() { return CATEGORY_DEFINITIONS; },
    busy() { return this.saving || this.resetting; },
    chartSeries() {
      return [{
        name: 'Projected net worth',
        data: (this.forecast?.points || []).map(point => ({
          x: String(point.calendar_year),
          y: Number(point.net_worth),
        })),
      }];
    },
    chartOptions() {
      return {
        ...CHART_DEFAULTS,
        chart: { ...CHART_DEFAULTS.chart, type: 'line' },
        colors: [CHART_COLORS[0]],
        stroke: { curve: 'smooth', width: 3 },
        markers: { size: 0, hover: { size: 5 } },
        dataLabels: { enabled: false },
        legend: { show: false },
        xaxis: {
          ...CHART_DEFAULTS.xaxis,
          type: 'category',
          tickAmount: 4,
        },
        yaxis: {
          ...CHART_DEFAULTS.yaxis,
          labels: {
            style: { colors: TEXT_COLORS.muted, fontSize: '10px' },
            formatter: value => formatCurrencyCompact(value),
          },
        },
        tooltip: {
          ...CHART_DEFAULTS.tooltip,
          y: { formatter: value => formatCurrency(value) },
        },
      };
    },
    firstProjectedYear() {
      return this.forecast?.points?.find(point => point.source === 'projected')?.calendar_year || null;
    },
    latestPoint() {
      const points = this.forecast?.points || [];
      return points.length ? points[points.length - 1] : null;
    },
  },
  created() { this.load(); },
  methods: {
    formatCurrency,
    assumptionFor(category) { return this.forecast?.assumptions?.[category] || {}; },
    sourceLabel(source) { return source === 'user_override' ? 'Your assumption' : 'Fynla default'; },
    basisLabel(basis) { return basis === 'real' ? 'Real' : 'Nominal'; },
    displayDate(value) {
      if (!value) return '—';
      return new Date(`${value}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
      });
    },
    applyForecast(forecast) {
      this.forecast = forecast;
      this.draft = Object.fromEntries(CATEGORY_DEFINITIONS.map(({ key }) => [
        key,
        Number(forecast?.assumptions?.[key]?.rate_percent ?? 0),
      ]));
      const selected = Object.values(forecast?.assumptions || {})
        .find(assumption => assumption.source === 'user_override')
        || Object.values(forecast?.assumptions || {})[0];
      this.basis = selected?.basis === 'real' ? 'real' : 'nominal';
    },
    async load(showLoading = true) {
      if (showLoading) this.loading = true;
      this.error = '';
      const response = await apiGet('/api/net-worth/forecast', store.token).catch(() => null);
      if (handleAuthExpiry(response, this.$router)) { this.loading = false; return false; }
      if (response?.ok) {
        this.applyForecast(response.data?.data || response.data || {});
        this.loading = false;
        return true;
      }
      this.error = response?.data?.message || 'We could not load your net worth projection.';
      this.loading = false;
      return false;
    },
    payload() {
      return {
        ...Object.fromEntries(CATEGORY_DEFINITIONS.map(({ key }) => [key, Number(this.draft[key])])),
        basis: this.basis,
      };
    },
    async save() {
      this.saving = true;
      this.feedback = '';
      this.saveError = '';
      this.validationErrors = {};
      const response = await apiPut(
        '/api/net-worth/forecast/assumptions',
        this.payload(),
        store.token,
      ).catch(() => null);
      if (handleAuthExpiry(response, this.$router)) { this.saving = false; return; }
      if (!response?.ok) {
        this.validationErrors = response?.data?.errors || {};
        this.saveError = response?.status === 422
          ? 'Check the highlighted assumptions.'
          : 'We could not save your assumptions. Please try again.';
        this.saving = false;
        return;
      }
      await this.load(false);
      this.feedback = 'Assumptions saved.';
      this.saving = false;
    },
    async reset() {
      this.resetting = true;
      this.feedback = '';
      this.saveError = '';
      this.validationErrors = {};
      const response = await apiDelete(
        '/api/net-worth/forecast/assumptions',
        store.token,
      ).catch(() => null);
      if (handleAuthExpiry(response, this.$router)) { this.resetting = false; return; }
      if (!response?.ok) {
        this.saveError = 'We could not reset your assumptions. Please try again.';
        this.resetting = false;
        return;
      }
      await this.load(false);
      this.feedback = 'Assumptions reset to Fynla defaults.';
      this.resetting = false;
    },
  },
};
</script>

<style scoped>
.mnwf { overflow: hidden; }
.mnwf .m-section-label { margin: 0 0 4px; }
.mnwf__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.mnwf__state { padding: 22px 0 4px; }
.mnwf__provenance { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 12px; color: var(--neutral-500); font-size: 11px; font-weight: 700; margin: 14px 0 8px; }
.mnwf__latest { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid var(--horizon-200); color: var(--horizon-500); font-size: 13px; padding-top: 12px; }
.mnwf__latest strong { font-size: 16px; }
.mnwf__warnings { background: var(--savannah-100); border-radius: 8px; color: var(--neutral-600); font-size: 12px; line-height: 1.5; margin-top: 14px; padding: 10px; }
.mnwf__warnings p + p { margin-top: 6px; }
.mnwf__form { border-top: 1px solid var(--horizon-200); margin-top: 18px; padding-top: 18px; }
.mnwf__editor-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.mnwf__title { color: var(--horizon-500); font-size: 16px; font-weight: 800; }
.mnwf__basis { color: var(--neutral-600); display: grid; flex-shrink: 0; font-size: 11px; font-weight: 700; gap: 3px; }
.mnwf__basis select, .mnwf__input-wrap input { background: white; border: 1px solid var(--horizon-300); border-radius: 7px; color: var(--horizon-500); font: inherit; }
.mnwf__basis select { min-height: 36px; padding: 5px 8px; }
.mnwf__assumption { display: grid; grid-template-columns: minmax(0, 1fr) auto; column-gap: 10px; padding: 11px 0; border-bottom: 1px solid var(--horizon-100); }
.mnwf__field-label { align-self: center; color: var(--horizon-500); font-size: 14px; font-weight: 700; }
.mnwf__input-wrap { align-items: center; display: flex; gap: 5px; color: var(--neutral-600); font-size: 13px; font-weight: 700; }
.mnwf__input-wrap input { min-height: 38px; padding: 7px; text-align: right; width: 76px; }
.mnwf__meta { color: var(--neutral-500); font-size: 10px; grid-column: 1 / -1; line-height: 1.4; margin-top: 4px; }
.mnwf__field-error { font-size: 11px; grid-column: 1 / -1; margin-top: 4px; }
.mnwf__feedback { color: var(--spring-700); font-size: 13px; font-weight: 700; margin-top: 12px; }
.mnwf__actions { display: flex; align-items: center; gap: 12px; margin-top: 14px; }
.mnwf__actions .m-btn { margin: 0; }
.mnwf__reset { background: transparent; border: 0; color: var(--raspberry-500); cursor: pointer; font-size: 12px; font-weight: 800; padding: 10px 0; }
.mnwf__reset:disabled { opacity: 0.5; }
</style>
