<template>
  <div class="rounded-lg bg-gradient-to-r from-horizon-500 to-raspberry-500 text-white p-6 mb-6">
    <h1 class="text-display font-black mb-1">Your tax strategy</h1>
    <p class="text-body-sm opacity-90" v-if="taxYear">Tax year {{ taxYear }}</p>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';

export default {
  name: 'TaxYearHeader',
  computed: {
    ...mapGetters('taxStrategy', ['taxYear']),
  },
};
</script>
