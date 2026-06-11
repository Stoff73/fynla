<template>
  <div id="m-app">
    <router-view />

    <!-- Persistent bug reporter — only once authenticated (needs a token to post).
         Hidden while the sheet is open; the Fyn chat header has its own
         "Report a problem" control (the chat overlay covers this FAB). -->
    <button v-if="canReport && !store.bugReport.open" class="m-report-fab" type="button" aria-label="Report a problem" title="Report a problem" @click="store.openBugReport()">
      <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
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
