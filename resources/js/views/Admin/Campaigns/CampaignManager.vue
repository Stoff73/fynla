<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <AdminNav />

      <header class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Campaigns</h1>
          <p class="text-sm text-neutral-500 mt-1">Landing-page campaigns. Linking one to an article swaps the article's bottom call-to-action.</p>
        </div>
        <button
          @click="openCreate"
          class="px-5 py-2.5 bg-raspberry-500 text-white rounded-lg text-sm font-semibold hover:bg-raspberry-600"
        >
          + New campaign
        </button>
      </header>

      <div v-if="loading" class="flex justify-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <div v-else-if="campaigns.length === 0" class="text-center py-16 text-neutral-500">
        No campaigns yet. Create one to link it to articles.
      </div>

      <div v-else class="card overflow-hidden p-0">
        <table class="w-full text-sm">
          <thead class="bg-eggshell-50 text-horizon-500 font-semibold">
            <tr>
              <th class="text-left px-4 py-3">Campaign</th>
              <th class="text-left px-4 py-3 w-40">Slug</th>
              <th class="text-left px-4 py-3">Landing URL</th>
              <th class="text-left px-4 py-3 w-40">Active window</th>
              <th class="text-right px-4 py-3 w-32">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in campaigns" :key="c.id" class="border-t border-savannah-100 hover:bg-eggshell-50">
              <td class="px-4 py-3">
                <p class="font-semibold text-horizon-500">{{ c.name }}</p>
                <p v-if="c.cta_heading" class="text-xs text-neutral-500 mt-0.5">CTA: {{ c.cta_heading }}</p>
              </td>
              <td class="px-4 py-3 font-mono text-xs text-horizon-400">{{ c.slug }}</td>
              <td class="px-4 py-3 text-horizon-400 truncate max-w-[220px]">
                <a :href="c.landing_url" target="_blank" rel="noopener" class="hover:underline">{{ c.landing_url }}</a>
              </td>
              <td class="px-4 py-3 text-horizon-400 text-xs">
                {{ formatWindow(c) }}
              </td>
              <td class="px-4 py-3 text-right space-x-1">
                <button @click="openEdit(c)" class="px-3 py-1.5 text-xs border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50">Edit</button>
                <button @click="remove(c)" class="px-3 py-1.5 text-xs border border-raspberry-500 text-raspberry-500 rounded hover:bg-raspberry-50">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Create / edit modal -->
      <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-horizon-900/40 p-4" @click.self="showForm = false">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="px-6 py-4 border-b border-light-gray flex items-center justify-between">
            <h2 class="text-lg font-bold text-horizon-500">{{ form.id ? 'Edit campaign' : 'New campaign' }}</h2>
            <button @click="showForm = false" class="text-neutral-500 hover:text-horizon-500 text-xl leading-none">&times;</button>
          </div>
          <div class="px-6 py-4 space-y-4">
            <div>
              <label class="block text-xs font-semibold text-horizon-400 mb-1">Name *</label>
              <input v-model="form.name" @input="maybeSlugify" maxlength="100" class="w-full px-3 py-2 text-sm border border-light-gray rounded" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-horizon-400 mb-1">Slug *</label>
              <input v-model="form.slug" maxlength="80" class="w-full px-3 py-2 text-sm border border-light-gray rounded font-mono" placeholder="save-tax-2026" />
              <p class="text-xs text-neutral-500 mt-1">Lowercase letters, numbers and hyphens only.</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-horizon-400 mb-1">Landing URL *</label>
              <input v-model="form.landing_url" maxlength="2048" class="w-full px-3 py-2 text-sm border border-light-gray rounded" placeholder="https://fynla.org/savetax" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-horizon-400 mb-1">Description</label>
              <textarea v-model="form.description" rows="2" maxlength="2000" class="w-full px-3 py-2 text-sm border border-light-gray rounded"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-horizon-400 mb-1">Active from</label>
                <input v-model="form.active_from" type="date" class="w-full px-3 py-2 text-sm border border-light-gray rounded" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-horizon-400 mb-1">Active to</label>
                <input v-model="form.active_to" type="date" class="w-full px-3 py-2 text-sm border border-light-gray rounded" />
              </div>
            </div>
            <div class="border-t border-light-gray pt-4">
              <p class="text-xs font-semibold text-horizon-500 uppercase tracking-wide mb-2">Bottom call-to-action</p>
              <div class="space-y-3">
                <div>
                  <label class="block text-xs font-semibold text-horizon-400 mb-1">Heading</label>
                  <input v-model="form.cta_heading" maxlength="120" class="w-full px-3 py-2 text-sm border border-light-gray rounded" placeholder="Ready to save tax?" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-horizon-400 mb-1">Subheading</label>
                  <input v-model="form.cta_subheading" maxlength="500" class="w-full px-3 py-2 text-sm border border-light-gray rounded" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-horizon-400 mb-1">Button text</label>
                  <input v-model="form.cta_button_text" maxlength="60" class="w-full px-3 py-2 text-sm border border-light-gray rounded" placeholder="Check now" />
                </div>
              </div>
            </div>
            <p v-if="error" class="text-sm text-raspberry-500">{{ error }}</p>
          </div>
          <div class="px-6 py-4 border-t border-light-gray flex justify-end gap-2">
            <button @click="showForm = false" class="px-4 py-2 text-sm font-semibold border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50">Cancel</button>
            <button @click="submit" :disabled="saving" class="px-5 py-2 text-sm font-semibold bg-raspberry-500 text-white rounded hover:bg-raspberry-600 disabled:opacity-50">
              {{ saving ? 'Saving…' : (form.id ? 'Save changes' : 'Create campaign') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import AdminNav from '@/components/Admin/AdminNav.vue';
import pipelineCampaignsService from '@/services/pipelineCampaignsService';

function emptyForm() {
  return {
    id: null,
    name: '',
    slug: '',
    landing_url: '',
    description: '',
    active_from: '',
    active_to: '',
    cta_heading: '',
    cta_subheading: '',
    cta_button_text: '',
  };
}

export default {
  name: 'CampaignManager',
  components: { AppLayout, AdminNav },
  data() {
    return {
      campaigns: [],
      loading: true,
      showForm: false,
      saving: false,
      error: '',
      slugTouched: false,
      form: emptyForm(),
    };
  },
  async created() {
    await this.refresh();
  },
  methods: {
    async refresh() {
      this.loading = true;
      try {
        const res = await pipelineCampaignsService.list();
        // The endpoint returns a paginator: { data: { data: [...] } }.
        const body = res?.data;
        this.campaigns = Array.isArray(body) ? body : (Array.isArray(body?.data) ? body.data : []);
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = emptyForm();
      this.slugTouched = false;
      this.error = '';
      this.showForm = true;
    },
    openEdit(c) {
      this.form = {
        id: c.id,
        name: c.name || '',
        slug: c.slug || '',
        landing_url: c.landing_url || '',
        description: c.description || '',
        active_from: (c.active_from || '').slice(0, 10),
        active_to: (c.active_to || '').slice(0, 10),
        cta_heading: c.cta_heading || '',
        cta_subheading: c.cta_subheading || '',
        cta_button_text: c.cta_button_text || '',
      };
      this.slugTouched = true;
      this.error = '';
      this.showForm = true;
    },
    maybeSlugify() {
      if (this.form.id || this.slugTouched) return;
      this.form.slug = (this.form.name || '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    },
    async submit() {
      this.saving = true;
      this.error = '';
      const payload = { ...this.form };
      delete payload.id;
      // Drop empty optional fields so backend validation (nullable) is happy.
      Object.keys(payload).forEach((k) => { if (payload[k] === '') payload[k] = null; });
      try {
        if (this.form.id) {
          await pipelineCampaignsService.update(this.form.id, payload);
        } else {
          await pipelineCampaignsService.create(payload);
        }
        this.showForm = false;
        await this.refresh();
      } catch (e) {
        const errs = e?.response?.data?.errors;
        this.error = errs ? Object.values(errs).flat().join(' ') : (e?.response?.data?.message || 'Save failed.');
      } finally {
        this.saving = false;
      }
    },
    async remove(c) {
      if (!window.confirm(`Delete campaign "${c.name}"? Articles linked to it fall back to the default CTA.`)) return;
      await pipelineCampaignsService.remove(c.id);
      await this.refresh();
    },
    formatWindow(c) {
      const f = (c.active_from || '').slice(0, 10);
      const t = (c.active_to || '').slice(0, 10);
      if (!f && !t) return 'Always on';
      return `${f || '…'} → ${t || '…'}`;
    },
  },
};
</script>
