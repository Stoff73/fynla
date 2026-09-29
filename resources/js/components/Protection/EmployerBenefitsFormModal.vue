<template>
  <Teleport to="body">
  <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="employer-benefits-title">
    <div class="fixed inset-0 bg-black/50 transition-opacity" @click="$emit('close')"></div>

    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="relative inline-block align-bottom bg-white rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full mx-4 sm:mx-0 max-h-[90vh] overflow-y-auto scrollbar-thin">
        <div class="bg-white px-6 pt-6">
          <h3 id="employer-benefits-title" class="text-xl font-semibold text-horizon-500 mb-1">Employer benefits</h3>
          <p class="text-sm text-neutral-500 mb-4">
            The cover your job gives you counts towards what your family would need. Your benefits booklet or HR team can tell you what it is.
          </p>
        </div>

        <form class="px-6 pb-6" @submit.prevent="handleSubmit">
          <div class="space-y-4">
            <div>
              <label for="eb-employer" class="block text-sm font-medium text-neutral-500 mb-1">Employer</label>
              <input
                id="eb-employer"
                v-model="form.employer_name"
                type="text"
                maxlength="255"
                class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
              />
            </div>

            <label class="flex items-start gap-2">
              <input v-model="form.none" type="checkbox" class="mt-1 h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded" />
              <span class="text-sm text-neutral-500">My employer provides none of these</span>
            </label>

            <template v-if="!form.none">
              <div>
                <label for="eb-dis" class="block text-sm font-medium text-neutral-500 mb-1">Death in service, as a multiple of your salary</label>
                <input
                  id="eb-dis"
                  v-model.number="form.death_in_service_multiple"
                  type="number"
                  min="0"
                  max="20"
                  step="0.5"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                />
                <p class="text-xs text-neutral-500 mt-1">For example 4 if it pays four times your salary. Leave blank if none.</p>
              </div>

              <fieldset class="space-y-3">
                <legend class="text-sm font-medium text-neutral-500">Group income protection</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label for="eb-ip-pct" class="block text-xs text-neutral-500 mb-1">Percentage of your salary it pays</label>
                    <input
                      id="eb-ip-pct"
                      v-model.number="form.group_ip_benefit_percent"
                      type="number"
                      min="0"
                      max="100"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                    />
                  </div>
                  <div>
                    <label for="eb-ip-months" class="block text-xs text-neutral-500 mb-1">How many months it pays for</label>
                    <input
                      id="eb-ip-months"
                      v-model.number="form.group_ip_benefit_months"
                      type="number"
                      min="0"
                      max="600"
                      step="1"
                      class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                    />
                  </div>
                </div>
                <div>
                  <label for="eb-ip-def" class="block text-xs text-neutral-500 mb-1">It pays if you cannot do</label>
                  <select
                    id="eb-ip-def"
                    v-model="form.group_ip_definition"
                    class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                  >
                    <option :value="null">Not sure</option>
                    <option value="own">Your own job</option>
                    <option value="any">Any job at all</option>
                  </select>
                </div>
              </fieldset>

              <div>
                <label for="eb-ci" class="block text-sm font-medium text-neutral-500 mb-1">Group critical illness cover</label>
                <input
                  id="eb-ci"
                  v-model.number="form.group_ci_amount"
                  type="number"
                  min="0"
                  step="1000"
                  class="w-full px-3 py-2 border border-horizon-300 rounded-lg focus:ring-2 focus:ring-violet-500 focus:border-transparent"
                />
                <p class="text-xs text-neutral-500 mt-1">The lump sum it would pay. Leave blank if none.</p>
              </div>

              <label class="flex items-start gap-2">
                <input v-model="form.has_employer_pmi" type="checkbox" class="mt-1 h-4 w-4 text-violet-600 focus:ring-violet-500 border-horizon-300 rounded" />
                <span class="text-sm text-neutral-500">My employer provides private medical insurance</span>
              </label>
            </template>
          </div>

          <p v-if="errorMessage" class="mt-4 text-sm text-raspberry-600">{{ errorMessage }}</p>

          <div class="flex gap-3 mt-6">
            <button
              type="button"
              class="flex-1 px-6 py-3 border border-horizon-300 text-horizon-500 font-medium rounded-button hover:bg-eggshell-500 transition-colors"
              @click="$emit('close')"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="flex-1 px-6 py-3 bg-raspberry-500 text-white font-medium rounded-button hover:bg-raspberry-600 disabled:bg-savannah-300 disabled:cursor-not-allowed transition-colors"
            >
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  </Teleport>
</template>

<script>
/**
 * The employer benefits form. Emits `save` with the answer; the parent makes
 * the API call and closes it on success (Rule 3). A blank field means the
 * employer does not provide it.
 */
const blankOrNumber = (value) => (value === '' || value === null || value === undefined ? null : Number(value));

export default {
  name: 'EmployerBenefitsFormModal',
  props: {
    profile: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
  },
  emits: ['save', 'close'],
  data() {
    const p = this.profile || {};
    const answered = Boolean(p.employer_benefits_recorded_at);
    const hasAny = [p.death_in_service_multiple, p.group_ip_benefit_percent, p.group_ci_amount].some((v) => v !== null && v !== undefined) || p.has_employer_pmi;

    return {
      form: {
        employer_name: p.employer_name || '',
        none: answered && !hasAny,
        death_in_service_multiple: p.death_in_service_multiple ?? null,
        group_ip_benefit_percent: p.group_ip_benefit_percent ?? null,
        group_ip_benefit_months: p.group_ip_benefit_months ?? null,
        group_ip_definition: p.group_ip_definition ?? null,
        group_ci_amount: p.group_ci_amount ?? null,
        has_employer_pmi: Boolean(p.has_employer_pmi),
      },
    };
  },
  methods: {
    handleSubmit() {
      const f = this.form;
      this.$emit('save', {
        employer_name: f.employer_name.trim() || null,
        none: f.none,
        death_in_service_multiple: f.none ? null : blankOrNumber(f.death_in_service_multiple),
        group_ip_benefit_percent: f.none ? null : blankOrNumber(f.group_ip_benefit_percent),
        group_ip_benefit_months: f.none ? null : blankOrNumber(f.group_ip_benefit_months),
        group_ip_definition: f.none ? null : f.group_ip_definition,
        group_ci_amount: f.none ? null : blankOrNumber(f.group_ci_amount),
        has_employer_pmi: f.none ? false : f.has_employer_pmi,
      });
    },
  },
};
</script>
