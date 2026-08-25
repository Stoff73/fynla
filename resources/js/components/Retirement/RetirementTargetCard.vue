<template>
  <div class="card retirement-target">
    <div class="retirement-target__head">
      <div>
        <h3 class="retirement-target__title">Your retirement target</h3>
        <p class="retirement-target__caption">{{ sourceCaption }}</p>
      </div>
      <button
        v-if="!editing"
        v-preview-disabled="'edit'"
        type="button"
        class="retirement-target__edit"
        data-testid="retirement-target-edit"
        @click="startEditing"
      >
        {{ hasStatedTarget ? 'Change' : 'Set your target' }}
      </button>
    </div>

    <p v-if="errorMessage" class="retirement-target__error" role="alert" data-testid="retirement-target-error">
      {{ errorMessage }}
    </p>

    <dl v-if="!editing" class="retirement-target__figures">
      <div class="retirement-target__figure">
        <dt>Income you want each year</dt>
        <dd data-testid="retirement-target-income">{{ displayIncome }}</dd>
      </div>
      <div class="retirement-target__figure">
        <dt>Age you want to retire</dt>
        <dd data-testid="retirement-target-age">{{ displayAge }}</dd>
      </div>
    </dl>

    <form v-else class="retirement-target__form" @submit.prevent="handleSubmit">
      <div class="retirement-target__field">
        <label for="retirement-target-income-input">Income you want each year</label>
        <div class="retirement-target__input-wrap">
          <span class="retirement-target__prefix">£</span>
          <input
            id="retirement-target-income-input"
            v-model="form.target_retirement_income"
            type="number"
            min="0"
            step="500"
            inputmode="numeric"
            class="retirement-target__input retirement-target__input--prefixed"
            data-testid="retirement-target-income-input"
          />
        </div>
      </div>

      <div class="retirement-target__field">
        <label for="retirement-target-age-input">Age you want to retire</label>
        <input
          id="retirement-target-age-input"
          v-model="form.target_retirement_age"
          type="number"
          :min="MIN_RETIREMENT_AGE"
          :max="MAX_RETIREMENT_AGE"
          inputmode="numeric"
          class="retirement-target__input"
          data-testid="retirement-target-age-input"
        />
      </div>

      <p class="retirement-target__note">
        This is the figure every retirement projection is built on — required capital,
        your income projection, decumulation and capital adequacy all move with it.
      </p>

      <div class="retirement-target__actions">
        <button type="button" class="retirement-target__cancel" :disabled="saving" @click="cancelEditing">
          Cancel
        </button>
        <button type="submit" class="retirement-target__save" :disabled="saving" data-testid="retirement-target-save">
          {{ saving ? 'Saving…' : 'Save target' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script>
import { currencyMixin } from '@/mixins/currencyMixin';

/**
 * The module-screen entry point for a retirement target (W-0035).
 *
 * `retirement_profiles.target_retirement_income` is the figure every retirement
 * projection is built on, and until this card existed no form anywhere could write
 * it — only Fyn's `capture_retirement_goals` tool could. Everyone who had not
 * chatted to Fyn had required capital, the income projection, decumulation, capital
 * adequacy and Monte Carlo all built on `RequiredCapitalCalculator`'s fallback of
 * (gross income − pension contributions) × 75%, shown to them as their own target.
 *
 * So the card does two jobs, and the second matters as much as the first: it lets
 * the user state a target, and when they have not, it says out loud that the figure
 * on screen was worked out for them rather than chosen by them.
 *
 * It writes through `PUT /api/retirement/goals` — the one endpoint web, /m and
 * native all use (Rule 20). Per Rule 3 the parent owns the API call, so this emits
 * `save` and waits to be told the outcome.
 */
export default {
  name: 'RetirementTargetCard',

  mixins: [currencyMixin],

  emits: ['save'],

  props: {
    /** `retirement_profiles` row from GET /api/retirement, or null when none exists. */
    profile: {
      type: Object,
      default: null,
    },
    /** GET /api/retirement/required-capital — supplies the derived figure and its source. */
    requiredCapital: {
      type: Object,
      default: null,
    },
  },

  data() {
    return {
      editing: false,
      saving: false,
      errorMessage: null,
      form: {
        target_retirement_income: null,
        target_retirement_age: null,
      },
      // Matches App\Constants\ValidationLimits, which the endpoint validates against.
      MIN_RETIREMENT_AGE: 50,
      MAX_RETIREMENT_AGE: 100,
    };
  },

  computed: {
    /**
     * `income_source` is the API being honest about what it did: 'profile' when the
     * user stated a target, 'calculated' when the calculator fell back. Treating a
     * missing value as "not stated" keeps the caption conservative.
     */
    hasStatedTarget() {
      return this.requiredCapital?.income_source === 'profile'
        || Number(this.profile?.target_retirement_income) > 0;
    },

    targetIncome() {
      const stated = Number(this.profile?.target_retirement_income);
      if (Number.isFinite(stated) && stated > 0) return stated;

      const derived = Number(this.requiredCapital?.required_income);
      return Number.isFinite(derived) && derived > 0 ? derived : null;
    },

    displayIncome() {
      return this.targetIncome === null ? 'Not set' : `${this.formatCurrency(this.targetIncome)} a year`;
    },

    displayAge() {
      return this.profile?.target_retirement_age || 'Not set';
    },

    sourceCaption() {
      if (this.hasStatedTarget) {
        return 'The figure you told us you want. Every retirement projection is built on it.';
      }

      if (this.targetIncome === null) {
        return 'Tell us what you want to retire on, and every retirement projection will be built on it.';
      }

      return 'Worked out for you from your income, because you have not set a target yet. Set one and every retirement projection will use it instead.';
    },
  },

  methods: {
    startEditing() {
      this.errorMessage = null;
      this.form = {
        // Only ever pre-fills a stated figure. Putting the derived one in the box
        // would turn "we guessed this" into "you chose this" the moment they save.
        target_retirement_income: Number(this.profile?.target_retirement_income) > 0
          ? Number(this.profile.target_retirement_income)
          : null,
        target_retirement_age: this.profile?.target_retirement_age ?? null,
      };
      this.editing = true;
    },

    cancelEditing() {
      this.editing = false;
      this.errorMessage = null;
    },

    handleSubmit() {
      const income = this.toNumber(this.form.target_retirement_income);
      const age = this.toNumber(this.form.target_retirement_age);

      if (income === null && age === null) {
        this.errorMessage = 'Enter a target income, a target retirement age, or both.';
        return;
      }

      if (income !== null && income < 0) {
        this.errorMessage = 'Target income cannot be negative.';
        return;
      }

      if (age !== null && (age < this.MIN_RETIREMENT_AGE || age > this.MAX_RETIREMENT_AGE)) {
        this.errorMessage = `Target retirement age must be between ${this.MIN_RETIREMENT_AGE} and ${this.MAX_RETIREMENT_AGE}.`;
        return;
      }

      this.errorMessage = null;
      this.saving = true;

      // Only send what was answered — the endpoint leaves an omitted value alone
      // rather than clearing it, and there is no way for the user to say "unset".
      const payload = {};
      if (income !== null) payload.target_retirement_income = income;
      if (age !== null) payload.target_retirement_age = age;

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
      this.errorMessage = message || 'We could not save your retirement target. Please try again.';
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
.retirement-target {
  @apply mb-6;
}

.retirement-target__head {
  @apply flex items-start justify-between gap-4 mb-3;
}

.retirement-target__title {
  @apply text-lg font-bold text-horizon-500;
}

.retirement-target__caption {
  @apply text-sm text-neutral-500 mt-1 max-w-2xl;
}

.retirement-target__edit {
  @apply flex-shrink-0 px-4 py-2 text-sm font-semibold text-raspberry-500 border border-raspberry-200 rounded-lg transition-colors;
}

.retirement-target__edit:hover {
  @apply bg-raspberry-50;
}

.retirement-target__error {
  @apply text-sm text-raspberry-700 bg-raspberry-50 border border-raspberry-200 rounded-lg px-4 py-2 mb-3;
}

.retirement-target__figures {
  @apply grid grid-cols-1 sm:grid-cols-2 gap-4;
}

.retirement-target__figure dt {
  @apply text-sm text-neutral-500;
}

.retirement-target__figure dd {
  @apply text-xl font-bold text-horizon-500 mt-1;
}

.retirement-target__form {
  @apply grid grid-cols-1 sm:grid-cols-2 gap-4;
}

.retirement-target__field label {
  @apply block text-sm font-medium text-neutral-500 mb-1;
}

.retirement-target__input-wrap {
  @apply relative;
}

.retirement-target__prefix {
  @apply absolute left-4 top-1/2 -translate-y-1/2 text-neutral-500;
}

.retirement-target__input {
  @apply w-full px-4 py-2 border border-horizon-300 rounded-lg;
}

.retirement-target__input:focus {
  @apply ring-2 ring-violet-500 border-transparent outline-none;
}

.retirement-target__input--prefixed {
  @apply pl-8;
}

.retirement-target__note {
  @apply sm:col-span-2 text-xs text-neutral-500;
}

.retirement-target__actions {
  @apply sm:col-span-2 flex justify-end gap-3;
}

.retirement-target__cancel {
  @apply px-4 py-2 text-sm font-semibold text-neutral-500 border border-horizon-300 rounded-lg;
}

.retirement-target__save {
  @apply px-4 py-2 text-sm font-semibold text-white bg-raspberry-500 rounded-lg;
}

.retirement-target__save:disabled,
.retirement-target__cancel:disabled {
  @apply opacity-60 cursor-not-allowed;
}
</style>
