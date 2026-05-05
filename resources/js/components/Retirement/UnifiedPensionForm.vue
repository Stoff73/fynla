<template>
  <div>
    <!-- DB edit: legacy standalone DB form (edit flow only) -->
    <DBPensionForm
      v-if="initialPensionType === 'db'"
      :pension="pension"
      @close="$emit('close')"
      @save="emitSave($event, 'db')"
    />

    <!-- State edit: legacy standalone State form (edit flow only) -->
    <StatePensionForm
      v-else-if="initialPensionType === 'state'"
      :state-pension="statePension"
      @close="$emit('close')"
      @save="emitSave($event, 'state')"
    />

    <!-- Add, or DC edit: unified DC-based "Add Pension" form. Its dropdown
         now covers DC sub-types plus Final Salary and State Pension, and it
         emits its own `_pensionType` so db_pensions / state_pensions records
         still get the correct backend payload shape. -->
    <DCPensionForm
      v-else
      :pension="pension"
      @close="$emit('close')"
      @save="$emit('save', $event)"
    />
  </div>
</template>

<script>
import DCPensionForm from './DCPensionForm.vue';
import DBPensionForm from './DBPensionForm.vue';
import StatePensionForm from './StatePensionForm.vue';

export default {
  name: 'UnifiedPensionForm',

  emits: ['save', 'close'],

  components: {
    DCPensionForm,
    DBPensionForm,
    StatePensionForm,
  },

  props: {
    pension: {
      type: Object,
      default: null,
    },
    statePension: {
      type: Object,
      default: null,
    },
    initialPensionType: {
      type: String,
      default: null,
    },
  },

  methods: {
    // Legacy DB/State edit flows don't set `_pensionType` themselves — tag it here
    // so the parent PensionList.handlePensionSave dispatches to the right API.
    emitSave(data, pensionType) {
      this.$emit('save', { ...data, _pensionType: pensionType });
    },
  },
};
</script>
