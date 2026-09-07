<template>
  <div class="dissolution-step">
    <h3 class="text-lg font-bold text-horizon-500 mb-1">If a marriage or civil partnership ends</h3>

    <!--
      W-0152. The statutory default first, in the not-a-choice register: the donor
      does not get to decide whether section 13(6)(c) applies, only whether to
      provide otherwise under section 13(11). Stating the default before the
      question is what makes the question answerable — a donor asked to opt out of
      a rule they have never been told about is not making an election.
    -->
    <div class="bg-savannah-100 border border-light-gray rounded-lg p-4 mb-6">
      <p class="text-sm text-neutral-600">
        Under the Mental Capacity Act 2005, if a marriage or civil partnership between you
        and one of your attorneys is dissolved or annulled, that attorney's appointment
        ends automatically (section 13(6)(c)). You can direct otherwise in this Lasting
        Power of Attorney (section 13(11)).
      </p>
      <p class="text-sm text-neutral-600 mt-2">
        This applies only where an attorney is your spouse or civil partner. It is not a
        rule you can switch off, only one you can provide against.
      </p>
    </div>

    <p class="text-sm text-neutral-500 mb-4">
      What would you like this Lasting Power of Attorney to say?
    </p>

    <div class="space-y-3">
      <label
        class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
        :class="modelValue.appointment_survives_dissolution === false ? 'border-violet-500 bg-violet-50' : 'border-light-gray hover:border-savannah-300'"
      >
        <input
          type="radio"
          :checked="modelValue.appointment_survives_dissolution === false"
          @change="update(false)"
          class="mt-1 mr-3 text-violet-500 focus:ring-violet-500"
        />
        <div>
          <p class="text-sm font-medium text-horizon-500">Leave the law as it stands</p>
          <p class="text-xs text-neutral-500 mt-1">
            The appointment of an attorney married to you, or in a civil partnership with
            you, ends if that marriage or civil partnership is dissolved or annulled.
          </p>
        </div>
      </label>

      <label
        class="flex items-start p-4 border rounded-lg cursor-pointer transition-colors"
        :class="modelValue.appointment_survives_dissolution === true ? 'border-violet-500 bg-violet-50' : 'border-light-gray hover:border-savannah-300'"
      >
        <input
          type="radio"
          :checked="modelValue.appointment_survives_dissolution === true"
          @change="update(true)"
          class="mt-1 mr-3 text-violet-500 focus:ring-violet-500"
        />
        <div>
          <p class="text-sm font-medium text-horizon-500">The appointment should continue</p>
          <p class="text-xs text-neutral-500 mt-1">
            Your attorney stays appointed even if your marriage or civil partnership ends.
            Choosing this writes an express direction into your Lasting Power of Attorney.
          </p>
        </div>
      </label>
    </div>

    <!--
      W-0152 / W-0100. Skipping is a real option and is recorded as "not specified"
      in the document. It must never resolve to either election: an unanswered
      question that becomes an answer is the defect W-0100 fixed on the timing
      election, and this one is legally operative in exactly the same way.
    -->
    <p class="text-xs text-neutral-500 mt-4">
      You can leave this unanswered. Your Lasting Power of Attorney will then say nothing
      about it, and the law above applies.
    </p>

    <p v-if="errors.appointment_survives_dissolution" class="text-xs text-raspberry-500 mt-3">
      {{ errors.appointment_survives_dissolution[0] }}
    </p>
  </div>
</template>

<script>
export default {
  name: 'DissolutionStep',

  props: {
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
  },

  emits: ['update:modelValue'],

  methods: {
    update(value) {
      this.$emit('update:modelValue', { ...this.modelValue, appointment_survives_dissolution: value });
    },
  },
};
</script>
