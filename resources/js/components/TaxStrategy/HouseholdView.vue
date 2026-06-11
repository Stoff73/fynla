<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <section>
      <h2 class="text-h4 font-bold text-horizon-500 mb-3">{{ firstName }}'s allowances</h2>
      <AllowanceGrid :allowances="userAllowances" />
    </section>
    <section>
      <h2 class="text-h4 font-bold text-horizon-500 mb-3">{{ spouseLabel }}</h2>
      <AllowanceGrid :allowances="spouseAllowances" />
    </section>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
import AllowanceGrid from './AllowanceGrid.vue';

export default {
  name: 'HouseholdView',
  components: { AllowanceGrid },
  computed: {
    ...mapGetters('taxStrategy', ['userAllowances', 'spouseAllowances', 'calculationMode']),
    ...mapGetters('auth', ['currentUser']),
    firstName() {
      return this.currentUser?.first_name || 'You';
    },
    spouseLabel() {
      return this.calculationMode === 'single_earner_couple'
        ? "Spouse's allowances (non-working)"
        : "Spouse's allowances";
    },
  },
};
</script>
