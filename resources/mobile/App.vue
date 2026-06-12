<template>
  <div id="m-app">
    <router-view />

    <!-- Persistent bug reporter — only once authenticated (needs a token to post).
         Hidden while the sheet is open; the Fyn chat header has its own
         "Report a problem" control (the chat overlay covers this FAB). -->
    <button v-if="canReport && !store.bugReport.open" class="m-report-fab" type="button" @click="store.openBugReport()">
      Report a problem
    </button>
    <BugReportSheet :show="store.bugReport.open" @close="store.closeBugReport()" />
  </div>
</template>

<script>
import { store } from './store.js';
import BugReportSheet from './views/BugReportSheet.vue';

export default {
  name: 'MobileScaffoldApp',
  components: { BugReportSheet },
  data() {
    return { store };
  },
  computed: {
    canReport() {
      return !!this.store.token;
    },
  },
};
</script>
