<template>
  <div id="m-app">
    <router-view />

    <!-- Persistent bug reporter — only once authenticated (needs a token to post). -->
    <button v-if="canReport" class="m-report-fab" type="button" @click="sheetOpen = true">
      Report a problem
    </button>
    <BugReportSheet :show="sheetOpen" @close="sheetOpen = false" />
  </div>
</template>

<script>
import { store } from './store.js';
import BugReportSheet from './views/BugReportSheet.vue';

export default {
  name: 'MobileScaffoldApp',
  components: { BugReportSheet },
  data() {
    return { store, sheetOpen: false };
  },
  computed: {
    canReport() {
      return !!this.store.token;
    },
  },
};
</script>
