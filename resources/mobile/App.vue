<template>
  <div id="m-app">
    <router-view />

    <!-- Persistent bug reporter — only once authenticated (needs a token to post).
         Hidden while the sheet is open; the Fyn chat header has its own
         "Report a problem" control (the chat overlay covers this FAB). -->
    <button v-if="canReport && !store.bugReport.open" class="m-report-fab" type="button" aria-label="Report a problem" title="Report a problem" @click="store.openBugReport()">
      <svg aria-hidden="true" fill="currentColor" viewBox="0 0 24 24" width="20" height="20"><path d="M20 8h-2.81a5.985 5.985 0 00-1.82-1.96l1.55-1.55-1.41-1.41-2.0 2.0a5.987 5.987 0 00-3.0 0l-2.0-2.0-1.41 1.41 1.55 1.55A5.985 5.985 0 006.81 8H4v2h2.09c-.05.33-.09.66-.09 1v1H4v2h2v1c0 .34.04.67.09 1H4v2h2.81a5.998 5.998 0 0010.38 0H20v-2h-2.09c.05-.33.09-.66.09-1v-1h2v-2h-2v-1c0-.34-.04-.67-.09-1H20V8zm-6 8h-4v-2h4v2zm0-4h-4v-2h4v2z" /></svg>
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
