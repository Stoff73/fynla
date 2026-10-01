<template>
  <div class="card care-costs">
    <div class="care-costs__head">
      <div>
        <h3 class="care-costs__title">Care costs in retirement</h3>
        <p class="care-costs__caption">{{ caption }}</p>
      </div>
      <button
        v-if="!editing"
        v-preview-disabled="'edit'"
        type="button"
        class="care-costs__edit"
        data-testid="care-costs-edit"
        @click="startEditing"
      >
        {{ answered ? 'Change' : 'Add care costs' }}
      </button>
    </div>

    <p v-if="errorMessage" class="care-costs__error" role="alert" data-testid="care-costs-error">
      {{ errorMessage }}
    </p>

    <dl v-if="!editing && answered" class="care-costs__figures">
      <div class="care-costs__figure">
        <dt>Each year</dt>
        <dd data-testid="care-costs-annual">{{ annual > 0 ? `${formatCurrency(annual)} a year` : 'None planned' }}</dd>
      </div>
      <div v-if="annual > 0" class="care-costs__figure">
        <dt>From age</dt>
        <dd data-testid="care-costs-start-age">{{ startAge || 'Not set' }}</dd>
      </div>
    </dl>

    <form v-else-if="editing" class="care-costs__form" @submit.prevent="handleSubmit">
      <div class="care-costs__field">
        <label for="care-costs-annual-input">Care costs each year</label>
        <div class="care-costs__input-wrap">
          <span class="care-costs__prefix">£</span>
          <input
            id="care-costs-annual-input"
            v-model="form.care_cost_annual"
            type="number"
            min="0"
            step="500"
            inputmode="numeric"
            class="care-costs__input care-costs__input--prefixed"
            data-testid="care-costs-annual-input"
          />
        </div>
      </div>

      <div class="care-costs__field">
        <label for="care-costs-start-age-input">Age care might start</label>
        <input
          id="care-costs-start-age-input"
          v-model="form.care_start_age"
          type="number"
          :min="MIN_AGE"
          :max="MAX_AGE"
          inputmode="numeric"
          class="care-costs__input"
          data-testid="care-costs-start-age-input"
        />
      </div>

      <p class="care-costs__note">Enter 0 if you plan for no care costs.</p>

      <div class="care-costs__actions">
        <button type="button" class="care-costs__cancel" :disabled="saving" @click="cancelEditing">Cancel</button>
        <button type="submit" class="care-costs__save" :disabled="saving" data-testid="care-costs-save">
          {{ saving ? 'Saving…' : 'Save care costs' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

/**
 * Care costs the user plans for in retirement (CSJ 2026-10-01). They feed the
 * decumulation analysis. Writes through `PUT /api/retirement/goals`, the one
 * endpoint web, /m and Fyn's `capture_retirement_goals` use; the parent owns the
 * call (Rule 3), so this emits `save` and waits to be told the outcome.
 *
 * `care_cost_annual` null is "never asked", 0 is "none planned" — the card shows
 * the difference and the care costs action asks only while it is null.
 */
export default {
  name: 'RetirementCareCostsCard',

  mixins: [currencyMixin],

  emits: ['save'],

  props: {
    /** `retirement_profiles` row from GET /api/retirement. */
    profile: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      editing: false,
      saving: false,
      errorMessage: null,
      form: { care_cost_annual: null, care_start_age: null },
      // Matches App\Constants\ValidationLimits (MIN_RETIREMENT_AGE, MAX_AGE), which the endpoint validates against.
      MIN_AGE: 50,
      MAX_AGE: 125,
    };
  },

  computed: {
    answered() {
      return this.profile?.care_cost_annual !== null && this.profile?.care_cost_annual !== undefined;
    },
    annual() {
      return Number(this.profile?.care_cost_annual) || 0;
    },
    startAge() {
      return this.profile?.care_start_age ?? null;
    },
    caption() {
      return this.answered
        ? 'Your decumulation analysis includes this.'
        : 'Add what you plan for care in later life, or say you plan for none, and your decumulation analysis will include it.';
    },
  },

  methods: {
    startEditing() {
      this.errorMessage = null;
      this.form = {
        care_cost_annual: this.answered ? this.annual : null,
        care_start_age: this.startAge,
      };
      this.editing = true;
    },

    cancelEditing() {
      this.editing = false;
      this.errorMessage = null;
    },

    handleSubmit() {
      const annual = this.toNumber(this.form.care_cost_annual);
      const startAge = this.toNumber(this.form.care_start_age);

      if (annual === null) {
        this.errorMessage = 'Enter a yearly amount, or 0 if you plan for none.';
        return;
      }
      if (annual < 0) {
        this.errorMessage = 'Care costs cannot be negative.';
        return;
      }
      if (startAge !== null && (startAge < this.MIN_AGE || startAge > this.MAX_AGE)) {
        this.errorMessage = `The age care might start must be between ${this.MIN_AGE} and ${this.MAX_AGE}.`;
        return;
      }

      this.errorMessage = null;
      this.saving = true;
      const payload = { care_cost_annual: annual };
      if (startAge !== null) payload.care_start_age = startAge;
      this.$emit('save', payload);
    },

    /** Called by the parent once the API call has settled. */
    saveSucceeded() {
      this.saving = false;
      this.editing = false;
      this.errorMessage = null;
    },

    /** Called by the parent when the API call failed; the form stays open (Rule 3). */
    saveFailed(message) {
      this.saving = false;
      this.errorMessage = message || 'We could not save your care costs. Please try again.';
    },

    toNumber(value) {
      if (value === null || value === undefined || value === '') return null;
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : null;
    },
  },
};
</script>

<style scoped>
.care-costs {
  @apply mb-6;
}

.care-costs__head {
  @apply flex items-start justify-between gap-4 mb-3;
}

.care-costs__title {
  @apply text-lg font-bold text-horizon-500;
}

.care-costs__caption {
  @apply text-sm text-neutral-500 mt-1 max-w-2xl;
}

.care-costs__edit {
  @apply flex-shrink-0 px-4 py-2 text-sm font-semibold text-raspberry-500 border border-raspberry-200 rounded-lg transition-colors;
}

.care-costs__edit:hover {
  @apply bg-raspberry-50;
}

.care-costs__error {
  @apply text-sm text-raspberry-700 bg-raspberry-50 border border-raspberry-200 rounded-lg px-4 py-2 mb-3;
}

.care-costs__figures {
  @apply grid grid-cols-1 sm:grid-cols-2 gap-4;
}

.care-costs__figure dt {
  @apply text-sm text-neutral-500;
}

.care-costs__figure dd {
  @apply text-xl font-bold text-horizon-500 mt-1;
}

.care-costs__form {
  @apply grid grid-cols-1 sm:grid-cols-2 gap-4;
}

.care-costs__field label {
  @apply block text-sm font-medium text-neutral-500 mb-1;
}

.care-costs__input-wrap {
  @apply relative;
}

.care-costs__prefix {
  @apply absolute left-4 top-1/2 -translate-y-1/2 text-neutral-500;
}

.care-costs__input {
  @apply w-full px-4 py-2 border border-horizon-300 rounded-lg;
}

.care-costs__input:focus {
  @apply ring-2 ring-violet-500 border-transparent outline-none;
}

.care-costs__input--prefixed {
  @apply pl-8;
}

.care-costs__note {
  @apply sm:col-span-2 text-xs text-neutral-500;
}

.care-costs__actions {
  @apply sm:col-span-2 flex justify-end gap-3;
}

.care-costs__cancel {
  @apply px-4 py-2 text-sm font-semibold text-neutral-500 border border-horizon-300 rounded-lg;
}

.care-costs__save {
  @apply px-4 py-2 text-sm font-semibold text-white bg-raspberry-500 rounded-lg;
}

.care-costs__save:disabled,
.care-costs__cancel:disabled {
  @apply opacity-60 cursor-not-allowed;
}
</style>
