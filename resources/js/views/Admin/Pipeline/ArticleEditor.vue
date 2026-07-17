<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" v-if="article">
      <header class="flex items-start justify-between mb-6">
        <div>
          <router-link to="/admin/pipeline/articles" class="text-xs text-horizon-400 hover:underline">← Back to article manager</router-link>
          <h1 class="text-2xl font-black text-horizon-500 mt-1" style="letter-spacing:-0.02em;">{{ article.title }}</h1>
          <p class="text-xs text-neutral-500 font-mono mt-1">{{ article.slug }}</p>
        </div>
        <div class="flex items-center gap-2">
          <a
            v-if="article.source_docx.drive_url"
            :href="article.source_docx.drive_url"
            target="_blank"
            rel="noopener"
            class="text-xs px-3 py-1.5 border border-savannah-200 rounded text-horizon-500 hover:bg-savannah-50"
          >Open Word doc</a>
          <button
            v-if="article.source_docx.drive_file_id"
            @click="reimport"
            :disabled="saving"
            class="text-xs px-3 py-1.5 border border-savannah-200 rounded text-horizon-500 hover:bg-savannah-50"
          >Re-import from Drive</button>
          <button
            @click="removeArticle"
            :disabled="saving"
            class="text-xs px-3 py-1.5 border border-raspberry-500 rounded text-raspberry-500 hover:bg-raspberry-50"
          >Delete</button>
        </div>
      </header>

      <div class="grid grid-cols-1 lg:grid-cols-[1.4fr_1fr] gap-6">
        <div class="card">
          <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-3">Body blocks · from Word</h3>
          <div v-if="!(article.body_blocks || []).length" class="text-sm text-neutral-500 italic">No body blocks — has the Word doc been imported?</div>
          <ul v-else class="space-y-2">
            <li
              v-for="(block, i) in article.body_blocks"
              :key="i"
              class="px-3 py-2 bg-eggshell-50 rounded text-sm"
            >
              <span class="text-xs font-mono text-horizon-400 mr-2">{{ blockLabel(block) }}</span>
              <span v-if="block.type === 'heading'" class="font-semibold text-horizon-500">{{ block.text }}</span>
              <span v-else-if="block.type === 'list'" class="text-horizon-500">{{ (block.items || []).length }} item(s)</span>
              <span v-else class="text-horizon-500" v-html="truncate(block.html || block.text || '')"></span>
            </li>
          </ul>
        </div>

        <div class="space-y-4">
          <div class="card">
            <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-3">Metadata</h3>
            <label class="block text-xs font-semibold text-horizon-400 mb-1">Title</label>
            <input v-model="form.title" class="w-full px-3 py-2 text-sm border border-light-gray rounded mb-3" />

            <label class="block text-xs font-semibold text-horizon-400 mb-1">Summary</label>
            <textarea v-model="form.summary" rows="3" class="w-full px-3 py-2 text-sm border border-light-gray rounded mb-3"></textarea>

            <label class="block text-xs font-semibold text-horizon-400 mb-1">Category</label>
            <select v-model="form.category" class="w-full px-3 py-2 text-sm border border-light-gray rounded mb-3">
              <option value="tax">Tax</option>
              <option value="pensions">Pensions</option>
              <option value="savings-isa">Savings & ISA</option>
              <option value="estate-planning">Estate planning</option>
              <option value="financial-planning">Financial planning</option>
              <option value="ai">Artificial intelligence</option>
              <option value="fintech">Fintech</option>
              <option value="platform-updates">Platform updates</option>
            </select>

            <button
              @click="saveMetadata"
              :disabled="saving"
              class="w-full px-3 py-2 text-sm border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50"
            >Save metadata</button>
          </div>

          <div class="card">
            <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-3">Campaign</h3>
            <select v-model="form.pipeline_campaign_id" @change="saveMetadata" class="w-full px-3 py-2 text-sm border border-light-gray rounded">
              <option :value="null">— None (default Register CTA) —</option>
              <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <p class="text-xs text-neutral-500 mt-2">Linking a campaign swaps the article's bottom CTA for the campaign's landing page.</p>
          </div>

          <div class="card border border-spring-200 bg-spring-50">
            <h3 class="text-xs font-semibold uppercase text-spring-500 tracking-wide mb-2">Bottom CTA preview</h3>
            <p class="text-sm font-bold text-horizon-500 mb-1">{{ ctaHeading }}</p>
            <p class="text-xs text-horizon-400 mb-3">{{ ctaSub }}</p>
            <button class="bg-raspberry-500 text-white px-4 py-1.5 rounded text-xs font-semibold">{{ ctaButton }} →</button>
          </div>

          <div class="card">
            <h3 class="text-sm font-bold text-horizon-500 uppercase tracking-wide mb-3">Publishing</h3>
            <div class="space-y-2">
              <button
                @click="publishLocal"
                :disabled="!article.actions.can_publish_local || saving"
                :title="!article.actions.can_publish_local ? 'Already published locally' : ''"
                class="w-full px-3 py-2 text-sm rounded font-semibold"
                :class="article.actions.can_publish_local
                  ? 'bg-raspberry-500 text-white hover:bg-raspberry-600'
                  : 'bg-neutral-100 text-neutral-400 cursor-not-allowed'"
              >
                1. Publish to local
              </button>
              <button
                @click="pushToDev"
                :disabled="!article.actions.can_push_to_dev || saving"
                :title="!article.actions.can_push_to_dev ? 'Publish locally first' : ''"
                class="w-full px-3 py-2 text-sm rounded font-semibold"
                :class="article.actions.can_push_to_dev
                  ? 'bg-horizon-500 text-white hover:bg-horizon-600'
                  : 'bg-neutral-100 text-neutral-400 cursor-not-allowed'"
              >
                2. Push to dev
                <span v-if="article.env_state.dev_synced_at" class="text-xs font-normal">(re-push)</span>
              </button>
              <button
                @click="pushToLive"
                :disabled="!article.actions.can_push_to_live || saving"
                :title="pushToLiveTooltip"
                class="w-full px-3 py-2 text-sm rounded font-semibold"
                :class="article.actions.can_push_to_live
                  ? 'bg-spring-500 text-white hover:bg-spring-600'
                  : 'bg-neutral-100 text-neutral-400 cursor-not-allowed'"
              >
                3. Push to live
              </button>
            </div>
            <p v-if="!article.actions.user_is_publisher" class="text-xs text-violet-500 mt-3">
              You don't have permission to push to live. Ask an admin to add you at /admin/pipeline/publishers.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import pipelineArticlesService from '@/services/pipelineArticlesService';

export default {
  name: 'ArticleEditor',
  components: { AppLayout },
  data() {
    return {
      article: null,
      campaigns: [],
      saving: false,
      form: {
        title: '',
        summary: '',
        category: '',
        pipeline_campaign_id: null,
      },
    };
  },
  computed: {
    campaign() {
      return this.campaigns.find((c) => c.id === this.form.pipeline_campaign_id) || null;
    },
    ctaHeading() {
      return this.campaign?.cta_heading || 'Ready to plan smarter?';
    },
    ctaSub() {
      return this.campaign?.cta_subheading || 'Register free and see how much more control you can have over your money.';
    },
    ctaButton() {
      return this.campaign?.cta_button_text || 'Register free';
    },
    pushToLiveTooltip() {
      if (!this.article) return '';
      if (!this.article.actions.user_is_publisher) return 'You need publisher permission.';
      if (!this.article.env_state.dev_synced_at) return 'Push to dev first.';
      if (this.article.env_state.gate_hours_remaining > 0) {
        return `Wait ${this.article.env_state.gate_hours_remaining} more hour(s).`;
      }
      return '';
    },
  },
  async mounted() {
    await this.loadCampaigns();
    await this.refresh();
  },
  methods: {
    async refresh() {
      const res = await pipelineArticlesService.get(this.$route.params.id);
      this.article = res.data;
      this.form.title = this.article.title;
      this.form.summary = this.article.summary;
      this.form.category = this.article.category;
      this.form.pipeline_campaign_id = this.article.campaign?.id ?? null;
    },
    async loadCampaigns() {
      const res = await pipelineArticlesService.listCampaigns();
      this.campaigns = res.data?.data ?? [];
    },
    async saveMetadata() {
      this.saving = true;
      try {
        await pipelineArticlesService.update(this.article.id, {
          title: this.form.title,
          summary: this.form.summary,
          category: this.form.category,
          pipeline_campaign_id: this.form.pipeline_campaign_id,
        });
        await this.refresh();
      } catch (e) {
        alert('Save failed: ' + (e?.response?.data?.message ?? e.message));
      } finally {
        this.saving = false;
      }
    },
    async publishLocal() {
      if (!confirm('Publish this article locally?')) return;
      this.saving = true;
      try {
        await pipelineArticlesService.publishLocal(this.article.id);
        await this.refresh();
      } catch (e) {
        alert('Publish failed: ' + (e?.response?.data?.message ?? e.message));
      } finally {
        this.saving = false;
      }
    },
    async pushToDev() {
      if (!confirm('Push to dev (csjones.co/fynla)?')) return;
      this.saving = true;
      try {
        await pipelineArticlesService.pushToDev(this.article.id);
        await this.refresh();
      } catch (e) {
        alert('Push to dev failed: ' + (e?.response?.data?.message ?? e.message));
      } finally {
        this.saving = false;
      }
    },
    async pushToLive() {
      if (!confirm(`Publish "${this.article.title}" LIVE on fynla.org?`)) return;
      this.saving = true;
      try {
        await pipelineArticlesService.pushToLive(this.article.id);
        await this.refresh();
      } catch (e) {
        alert('Push to live failed: ' + (e?.response?.data?.message ?? e.message));
      } finally {
        this.saving = false;
      }
    },
    async reimport() {
      if (!confirm('Re-import from the Word doc? Any manual edits will be overwritten.')) return;
      this.saving = true;
      try {
        await pipelineArticlesService.reimport(this.article.id);
        await this.refresh();
      } catch (e) {
        alert('Re-import failed: ' + (e?.response?.data?.message ?? e.message));
      } finally {
        this.saving = false;
      }
    },
    async removeArticle() {
      if (!confirm('Delete this article? Soft-delete — recoverable via DB.')) return;
      await pipelineArticlesService.remove(this.article.id);
      this.$router.push('/admin/pipeline/articles');
    },
    blockLabel(block) {
      if (block.type === 'heading') return `H${block.level || 2}`;
      if (block.type === 'paragraph') return 'P';
      if (block.type === 'list') return 'List';
      if (block.type === 'callout') return 'Callout';
      if (block.type === 'image') return 'Image';
      return block.type;
    },
    truncate(html) {
      const text = String(html).replace(/<[^>]+>/g, '');
      return text.length > 120 ? text.slice(0, 120) + '…' : text;
    },
  },
};
</script>
