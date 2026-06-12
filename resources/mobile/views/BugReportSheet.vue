<template>
  <div v-if="show" class="m-sheet-root" role="dialog" aria-modal="true" aria-label="Report a problem">
    <div class="m-sheet-overlay" @click="close"></div>
    <div class="m-sheet">
      <div class="m-sheet-handle"></div>

      <div v-if="submitted" class="m-sheet-done">
        <h2 class="m-h1">Report logged</h2>
        <p class="m-sub">Your report has been logged and sent to our team, with technical details attached. We'll look into it.</p>
        <button class="m-btn" type="button" @click="close">Done</button>
      </div>

      <form v-else @submit.prevent="submit">
        <h2 class="m-h1">Report a problem</h2>
        <p class="m-sub">Tell us what went wrong. We'll attach technical details automatically to help us fix it.</p>

        <label class="m-sheet-label" for="m-bug-desc">What went wrong?</label>
        <textarea
          id="m-bug-desc"
          class="m-field m-textarea"
          v-model="description"
          rows="4"
          maxlength="5000"
          required
          placeholder="Describe what happened, and what you were trying to do…"
        ></textarea>

        <label class="m-sheet-label" for="m-bug-cat">Area</label>
        <select id="m-bug-cat" class="m-field" v-model="category">
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>

        <label class="m-sheet-label" for="m-bug-sev">How serious is it?</label>
        <select id="m-bug-sev" class="m-field" v-model="severity">
          <option v-for="s in severities" :key="s" :value="s">{{ s }}</option>
        </select>

        <p v-if="error" class="m-err">{{ error }}</p>

        <div class="m-sheet-actions">
          <button class="m-btn-ghost" type="button" :disabled="submitting" @click="close">Cancel</button>
          <button class="m-btn" type="submit" :disabled="submitting || !description.trim()">
            {{ submitting ? 'Sending…' : 'Send report' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { store } from '../store.js';
import { apiPost } from '../api.js';
import { gatherDiagnostics } from '../diagnostics.js';
import consoleCapture from '../../js/services/consoleCapture.js';

export default {
  name: 'MobileBugReportSheet',
  props: {
    show: { type: Boolean, default: false },
  },
  emits: ['close'],
  data() {
    return {
      description: '',
      category: 'General',
      severity: 'Medium',
      submitting: false,
      submitted: false,
      error: '',
      categories: ['Protection', 'Savings', 'Investment', 'Retirement', 'Estate', 'Goals', 'Coordination', 'General'],
      severities: ['Low', 'Medium', 'High', 'Critical'],
    };
  },
  watch: {
    show(open) {
      if (open) {
        this.description = '';
        this.category = 'General';
        this.severity = 'Medium';
        this.submitting = false;
        this.submitted = false;
        this.error = '';
      }
    },
  },
  methods: {
    close() {
      if (!this.submitting) this.$emit('close');
    },
    async submit() {
      if (!this.description.trim() || this.submitting) return;
      this.submitting = true;
      this.error = '';

      try {
        const route = this.$route?.fullPath || (typeof window !== 'undefined' ? window.location.pathname : '');
        const diagnostics = await gatherDiagnostics(route);

        const payload = {
          description: this.description.trim(),
          category: this.category,
          severity: this.severity,
          console_logs: consoleCapture.getLogs(),
          page_url: typeof window !== 'undefined' ? window.location.href : null,
          user_agent: typeof navigator !== 'undefined' ? navigator.userAgent : null,
          screen_size: typeof window !== 'undefined' ? `${window.screen.width}x${window.screen.height}` : null,
          viewport_size: typeof window !== 'undefined' ? `${window.innerWidth}x${window.innerHeight}` : null,
          client_timestamp: new Date().toISOString(),
          // Set when the sheet was opened from the Fyn chat header — the backend
          // attaches the conversation transcript so we can see what Fyn did.
          conversation_id: store.bugReport.conversationId,
          ...diagnostics,
        };

        const { ok, status, data } = await apiPost('/api/bug-report', payload, store.token);

        if (ok) {
          this.submitted = true;
        } else if (status === 429) {
          this.error = "You've sent a few reports recently. Please try again a little later.";
        } else {
          this.error = data?.message || 'Could not send your report. Please try again.';
        }
      } catch (e) {
        this.error = 'Network error. Please try again.';
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>
