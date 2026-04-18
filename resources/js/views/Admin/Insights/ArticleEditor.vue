<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Toolbar -->
      <header class="flex items-center justify-between mb-6 pb-4 border-b border-light-gray flex-wrap gap-3">
        <div>
          <router-link to="/admin/insights" class="text-sm text-neutral-500 hover:text-horizon-500">
            &larr; All articles
          </router-link>
          <h1 class="text-2xl font-black text-horizon-500 mt-1" style="letter-spacing:-0.02em;">
            {{ isNew ? 'New article' : 'Edit article' }}
          </h1>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
          <span
            v-if="form.status"
            :class="statusClass"
            class="text-xs font-semibold px-2 py-1 rounded uppercase"
          >
            {{ form.status }}
          </span>
          <button
            type="button"
            @click="saveDraft"
            :disabled="saving"
            class="px-4 py-2 text-sm font-semibold border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50 disabled:opacity-50"
          >
            {{ saving ? 'Saving\u2026' : 'Save draft' }}
          </button>
          <button
            type="button"
            @click="preview"
            class="px-4 py-2 text-sm font-semibold border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50"
          >
            Preview
          </button>
          <button
            v-if="!isNew && !form.is_bespoke"
            type="button"
            @click="saveAsTemplateModal = true"
            class="px-4 py-2 text-sm font-semibold border border-horizon-500 text-horizon-500 rounded hover:bg-horizon-50"
          >
            Save as template
          </button>
          <button
            type="button"
            @click="publish"
            :disabled="saving"
            class="px-5 py-2 text-sm font-semibold bg-raspberry-500 text-white rounded hover:bg-raspberry-600 disabled:opacity-50"
          >
            {{ form.status === 'published' ? 'Update' : 'Publish' }}
          </button>
        </div>
      </header>

      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        <!-- Field panel -->
        <section class="lg:col-span-2 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Title</label>
            <input
              v-model="form.title"
              class="w-full text-lg font-bold text-horizon-500 px-3 py-2 border border-light-gray rounded"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Subtitle</label>
            <input v-model="form.subtitle" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Summary</label>
            <textarea v-model="form.summary" rows="3" class="w-full text-sm px-3 py-2 border border-light-gray rounded"></textarea>
            <p class="text-xs text-neutral-500 mt-1">
              Shown on the Insights hub and landing page card preview. Not displayed in the article body.
            </p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Category</label>
            <select v-model="form.category" class="w-full text-sm px-3 py-2 border border-light-gray rounded">
              <option value="tax">Tax</option>
              <option value="pensions">Pensions</option>
              <option value="savings-isa">Savings & ISA</option>
              <option value="estate-planning">Estate planning</option>
              <option value="financial-planning">Financial planning</option>
              <option value="ai">Artificial intelligence</option>
              <option value="fintech">Fintech</option>
              <option value="developer">Developer</option>
              <option value="international">International</option>
              <option value="platform-updates">Platform updates</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Tags (comma-separated)</label>
            <input
              :value="(form.tags || []).join(', ')"
              @input="form.tags = $event.target.value.split(',').map(s => s.trim()).filter(Boolean)"
              class="w-full text-sm px-3 py-2 border border-light-gray rounded"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-neutral-500 uppercase mb-1">Hero image</label>
            <div v-if="form.hero_image_card_path" class="relative mb-2">
              <img :src="`/storage/${form.hero_image_card_path}`" class="rounded w-full max-h-40 object-cover" />
              <button
                type="button"
                @click="clearHero"
                class="absolute top-2 right-2 px-2 py-1 text-xs bg-raspberry-500 text-white rounded"
              >
                Replace
              </button>
            </div>
            <input v-else type="file" accept="image/jpeg,image/png,image/webp" @change="handleHeroUpload" />
          </div>
          <label class="flex items-center gap-2 text-sm text-horizon-500">
            <input type="checkbox" v-model="form.is_featured" />
            Featured on landing page
          </label>

          <details class="border border-light-gray rounded p-3">
            <summary class="cursor-pointer text-sm font-semibold text-horizon-500">Search & sharing (SEO)</summary>
            <div class="space-y-3 mt-3">
              <div>
                <label class="block text-xs text-neutral-500 mb-1">Meta title (defaults to article title)</label>
                <input v-model="form.meta_title" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
              </div>
              <div>
                <label class="block text-xs text-neutral-500 mb-1">Meta description (defaults to summary)</label>
                <textarea v-model="form.meta_description" rows="2" class="w-full text-sm px-3 py-2 border border-light-gray rounded"></textarea>
              </div>
              <div>
                <label class="block text-xs text-neutral-500 mb-1">Canonical URL (optional)</label>
                <input v-model="form.canonical_url" class="w-full text-sm px-3 py-2 border border-light-gray rounded" />
              </div>
            </div>
          </details>
        </section>

        <!-- Canvas -->
        <section class="lg:col-span-3 space-y-4">
          <BespokeArticleNotice v-if="form.is_bespoke" :component="form.bespoke_component" />

          <template v-else>
            <div v-if="isNew && !templatePicked" class="p-6 bg-savannah-100 rounded-lg">
              <h3 class="font-bold text-horizon-500 mb-3">Start with a template</h3>
              <div class="space-y-2">
                <button
                  type="button"
                  @click="startBlank"
                  class="w-full p-3 text-left bg-white border border-light-gray rounded hover:bg-light-pink-100"
                >
                  <p class="font-semibold text-horizon-500 text-sm">Blank</p>
                  <p class="text-xs text-neutral-500">Empty canvas, add blocks as you go</p>
                </button>
                <button
                  v-for="t in templates"
                  :key="t.id"
                  type="button"
                  @click="useTemplate(t)"
                  class="w-full p-3 text-left bg-white border border-light-gray rounded hover:bg-light-pink-100"
                >
                  <p class="font-semibold text-horizon-500 text-sm">{{ t.name }}</p>
                  <p class="text-xs text-neutral-500">{{ t.description }}</p>
                </button>
              </div>
            </div>

            <div v-else>
              <div
                v-for="(block, i) in form.body_blocks"
                :key="i"
                class="p-4 bg-white border border-light-gray rounded-lg mb-3"
              >
                <header class="flex items-center justify-between mb-3 pb-2 border-b border-light-gray">
                  <span class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">
                    {{ block.type.replace('_', ' ') }}
                  </span>
                  <div class="flex items-center gap-2 text-xs">
                    <button
                      type="button"
                      aria-label="Move block up"
                      @click="moveBlock(i, -1)"
                      :disabled="i === 0"
                      class="text-neutral-500 hover:text-horizon-500 disabled:opacity-30"
                    >&uarr;</button>
                    <button
                      type="button"
                      aria-label="Move block down"
                      @click="moveBlock(i, 1)"
                      :disabled="i === form.body_blocks.length - 1"
                      class="text-neutral-500 hover:text-horizon-500 disabled:opacity-30"
                    >&darr;</button>
                    <button
                      type="button"
                      @click="duplicateBlock(i)"
                      class="text-neutral-500 hover:text-horizon-500"
                    >Duplicate</button>
                    <button
                      type="button"
                      aria-label="Delete block"
                      @click="removeBlock(i)"
                      class="text-raspberry-500"
                    >&times;</button>
                  </div>
                </header>
                <component
                  :is="editorForType(block.type)"
                  :block="block"
                  :article-slug="form.slug || ''"
                  @update="updateBlock(i, $event)"
                />
              </div>

              <button
                type="button"
                @click="pickerOpen = true"
                class="w-full py-3 bg-horizon-50 hover:bg-horizon-100 border-2 border-dashed border-horizon-500 rounded-lg text-sm font-semibold text-horizon-500"
              >
                + Add block
              </button>
            </div>
          </template>
        </section>
      </div>

      <BlockPickerModal :is-open="pickerOpen" @close="pickerOpen = false" @pick="addBlock" />

      <div
        v-if="saveAsTemplateModal"
        class="fixed inset-0 bg-horizon-500/50 flex items-center justify-center z-50"
      >
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
          <h3 class="text-lg font-bold text-horizon-500 mb-3">Save as template</h3>
          <input
            v-model="newTemplateName"
            placeholder="Template name"
            class="w-full text-sm px-3 py-2 border border-light-gray rounded mb-2"
          />
          <input
            v-model="newTemplateDesc"
            placeholder="Description (optional)"
            class="w-full text-sm px-3 py-2 border border-light-gray rounded mb-4"
          />
          <div class="flex justify-end gap-2">
            <button
              type="button"
              @click="saveAsTemplateModal = false"
              class="px-4 py-2 text-sm text-neutral-500"
            >
              Cancel
            </button>
            <button
              type="button"
              @click="submitSaveAsTemplate"
              class="px-4 py-2 text-sm bg-raspberry-500 text-white rounded"
            >
              Save
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import BespokeArticleNotice from '@/components/Admin/Insights/BespokeArticleNotice.vue';
import BlockPickerModal from '@/components/Admin/Insights/BlockPickerModal.vue';
import insightsService from '@/services/insightsService';

import EditHeadingBlock from '@/components/Admin/Insights/blocks/EditHeadingBlock.vue';
import EditParagraphBlock from '@/components/Admin/Insights/blocks/EditParagraphBlock.vue';
import EditListBlock from '@/components/Admin/Insights/blocks/EditListBlock.vue';
import EditImageBlock from '@/components/Admin/Insights/blocks/EditImageBlock.vue';
import EditPullQuoteBlock from '@/components/Admin/Insights/blocks/EditPullQuoteBlock.vue';
import EditCalloutBlock from '@/components/Admin/Insights/blocks/EditCalloutBlock.vue';
import EditDividerBlock from '@/components/Admin/Insights/blocks/EditDividerBlock.vue';
import EditCtaButtonBlock from '@/components/Admin/Insights/blocks/EditCtaButtonBlock.vue';
import EditTaxYearStatBlock from '@/components/Admin/Insights/blocks/EditTaxYearStatBlock.vue';
import EditRelatedArticlesBlock from '@/components/Admin/Insights/blocks/EditRelatedArticlesBlock.vue';
import EditKeyTakeawaysBlock from '@/components/Admin/Insights/blocks/EditKeyTakeawaysBlock.vue';

const EDITOR_MAP = {
  heading: EditHeadingBlock,
  paragraph: EditParagraphBlock,
  list: EditListBlock,
  image: EditImageBlock,
  pull_quote: EditPullQuoteBlock,
  callout: EditCalloutBlock,
  divider: EditDividerBlock,
  cta_button: EditCtaButtonBlock,
  tax_year_stat: EditTaxYearStatBlock,
  related_articles: EditRelatedArticlesBlock,
  key_takeaways: EditKeyTakeawaysBlock,
};

export default {
  name: 'ArticleEditor',
  components: { AppLayout, BespokeArticleNotice, BlockPickerModal },
  data() {
    return {
      form: {
        title: '',
        subtitle: '',
        summary: '',
        category: 'pensions',
        tags: [],
        hero_image_path: null,
        hero_image_card_path: null,
        hero_image_thumb_path: null,
        body_blocks: [],
        template_id: null,
        status: 'draft',
        is_featured: false,
        is_bespoke: false,
        bespoke_component: null,
        meta_title: '',
        meta_description: '',
        canonical_url: '',
        slug: null,
      },
      saving: false,
      pickerOpen: false,
      templatePicked: false,
      saveAsTemplateModal: false,
      newTemplateName: '',
      newTemplateDesc: '',
      articleId: null,
    };
  },
  computed: {
    ...mapGetters('insights', ['templates']),
    isNew() { return !this.articleId; },
    statusClass() {
      return {
        'bg-neutral-100 text-neutral-500': this.form.status === 'draft',
        'bg-spring-100 text-spring-700': this.form.status === 'published',
        'bg-light-gray text-neutral-500': this.form.status === 'archived',
      };
    },
  },
  async mounted() {
    await this.fetchTemplates();

    if (this.$route.params.id) {
      this.articleId = Number(this.$route.params.id);
      const res = await insightsService.adminGet(this.articleId);
      this.form = { ...this.form, ...res.data };
      this.templatePicked = true;
    }
  },
  methods: {
    ...mapActions('insights', ['fetchTemplates', 'saveAsTemplate']),
    editorForType(type) { return EDITOR_MAP[type] || null; },
    startBlank() {
      this.form.body_blocks = [];
      this.templatePicked = true;
    },
    useTemplate(t) {
      this.form.body_blocks = JSON.parse(JSON.stringify(t.body_blocks || []));
      this.form.template_id = t.id;
      this.templatePicked = true;
    },
    addBlock(block) {
      this.form.body_blocks.push(block);
      this.pickerOpen = false;
    },
    updateBlock(i, block) { this.form.body_blocks.splice(i, 1, block); },
    moveBlock(i, dir) {
      const j = i + dir;
      if (j < 0 || j >= this.form.body_blocks.length) return;
      const blocks = [...this.form.body_blocks];
      [blocks[i], blocks[j]] = [blocks[j], blocks[i]];
      this.form.body_blocks = blocks;
    },
    duplicateBlock(i) {
      const copy = JSON.parse(JSON.stringify(this.form.body_blocks[i]));
      this.form.body_blocks.splice(i + 1, 0, copy);
    },
    removeBlock(i) { this.form.body_blocks.splice(i, 1); },
    async handleHeroUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      const slug = this.form.slug || this.slugify(this.form.title) || 'draft';
      try {
        const res = await insightsService.uploadImage(file, slug);
        this.form.hero_image_path = res.data.path;
        this.form.hero_image_card_path = res.data.card_path;
        this.form.hero_image_thumb_path = res.data.thumb_path;
      } catch (e) {
        alert('Upload failed: ' + (e.response?.data?.message || e.message));
      }
    },
    clearHero() {
      this.form.hero_image_path = null;
      this.form.hero_image_card_path = null;
      this.form.hero_image_thumb_path = null;
    },
    slugify(text) {
      return (text || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    },
    async saveDraft() {
      this.saving = true;
      try {
        if (this.isNew) {
          const res = await insightsService.create(this.form);
          this.articleId = res.data.id;
          this.form = { ...this.form, ...res.data };
          this.$router.replace(`/admin/insights/${this.articleId}/edit`);
        } else {
          const res = await insightsService.update(this.articleId, this.form);
          this.form = { ...this.form, ...res.data };
        }
      } catch (e) {
        alert('Save failed: ' + this.formatSaveError(e));
      } finally {
        this.saving = false;
      }
    },
    formatSaveError(e) {
      const res = e.response?.data;
      const message = res?.message || e.message || 'Unknown error';
      const errors = res?.errors;
      if (errors && typeof errors === 'object') {
        const lines = Object.values(errors).flat();
        if (lines.length) return `${message}\n\n\u2022 ${lines.join('\n\u2022 ')}`;
      }
      return message;
    },
    async publish() {
      await this.saveDraft();
      if (this.articleId) {
        const res = await insightsService.publish(this.articleId);
        this.form = { ...this.form, ...res.data };
        alert('Published.');
      }
    },
    preview() {
      if (!this.form.slug) {
        alert('Save a draft first to generate a slug.');
        return;
      }
      window.open(`/insights/${this.form.slug}?preview=true`, '_blank');
    },
    async submitSaveAsTemplate() {
      if (!this.newTemplateName) return;
      await this.saveAsTemplate({
        articleId: this.articleId,
        name: this.newTemplateName,
        description: this.newTemplateDesc,
      });
      this.saveAsTemplateModal = false;
      this.newTemplateName = '';
      this.newTemplateDesc = '';
      alert('Template saved.');
    },
  },
};
</script>
