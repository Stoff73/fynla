<template>
  <section class="bg-white rounded-lg border border-light-gray p-6" aria-labelledby="employer-benefits-heading">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
      <div>
        <h3 id="employer-benefits-heading" class="text-lg font-semibold text-horizon-500">Employer benefits</h3>
        <p class="text-sm text-neutral-500">
          {{ answered ? employerLine : 'Tell us what cover your job gives you, so your shortfall counts it.' }}
        </p>
      </div>
      <button
        v-preview-disabled="'edit'"
        type="button"
        class="px-4 py-2 border-2 border-violet-600 text-violet-600 bg-white rounded-lg hover:bg-violet-50 transition-colors font-medium"
        @click="$emit('edit')"
      >
        {{ answered ? 'Edit' : 'Add employer benefits' }}
      </button>
    </div>

    <p v-if="answered && !hasAny" class="text-sm text-neutral-500">Your employer provides none of these.</p>

    <dl v-else-if="answered" class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
      <div v-for="row in rows" :key="row.label">
        <dt class="text-xs text-neutral-500">{{ row.label }}</dt>
        <dd class="text-sm font-medium text-horizon-500">{{ row.value }}</dd>
      </div>
    </dl>
  </section>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

/** What the user's job provides, as recorded; "Not provided" where it is blank. */
export default {
  name: 'EmployerBenefitsCard',
  mixins: [currencyMixin],
  props: {
    profile: { type: Object, default: null },
  },
  emits: ['edit'],
  computed: {
    p() { return this.profile || {}; },
    answered() { return Boolean(this.p.employer_benefits_recorded_at); },
    hasAny() {
      return [this.p.death_in_service_multiple, this.p.group_ip_benefit_percent, this.p.group_ci_amount]
        .some((v) => v !== null && v !== undefined) || Boolean(this.p.has_employer_pmi);
    },
    employerLine() { return this.p.employer_name ? `Through ${this.p.employer_name}` : 'Through your job'; },
    rows() {
      const p = this.p;
      const none = 'Not provided';
      const ipParts = [];
      if (p.group_ip_benefit_percent !== null && p.group_ip_benefit_percent !== undefined) {
        ipParts.push(`${Number(p.group_ip_benefit_percent)}% of your salary`);
        if (p.group_ip_benefit_months) ipParts.push(`for ${p.group_ip_benefit_months} months`);
        if (p.group_ip_definition === 'own') ipParts.push('if you cannot do your own job');
        if (p.group_ip_definition === 'any') ipParts.push('only if you cannot do any job');
      }

      return [
        { label: 'Death in service', value: p.death_in_service_multiple ? `${Number(p.death_in_service_multiple)} times your salary` : none },
        { label: 'Group income protection', value: ipParts.length ? ipParts.join(' ') : none },
        { label: 'Group critical illness cover', value: p.group_ci_amount ? this.formatCurrency(p.group_ci_amount) : none },
        { label: 'Private medical insurance', value: p.has_employer_pmi ? 'Yes' : none },
      ];
    },
  },
};
</script>
