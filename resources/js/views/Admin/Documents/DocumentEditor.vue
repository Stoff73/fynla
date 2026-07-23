<template>
    <AppLayout>
        <div v-if="article" class="space-y-6">
            <router-link to="/admin/documents" class="detail-inline-back">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to CMS Upload
            </router-link>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <section class="space-y-4">
                    <header>
                        <h1 class="text-3xl font-black text-horizon-700">{{ article.title }}</h1>
                        <p class="text-horizon-500 mt-1">Status: {{ article.status }}</p>
                    </header>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Title</label>
                <input v-model="form.title" type="text" class="w-full border border-horizon-200 rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Slug</label>
                <input v-model="form.slug" type="text" class="w-full border border-horizon-200 rounded px-3 py-2 font-mono text-sm" />
                <p class="text-xs text-horizon-500 mt-1">Public URL: <code>/insights/{{ form.slug }}</code></p>
            </div>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Subtitle</label>
                <input v-model="form.subtitle" type="text" class="w-full border border-horizon-200 rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Meta description</label>
                <textarea v-model="form.description" rows="3" class="w-full border border-horizon-200 rounded px-3 py-2"></textarea>
                <p class="text-xs text-horizon-500 mt-1">Truncated to 160 chars on the public page.</p>
            </div>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Keywords (comma-separated)</label>
                <input v-model="form.keywords" type="text" class="w-full border border-horizon-200 rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-bold text-horizon-700 mb-1">Author byline</label>
                <input v-model="form.author_byline" type="text" class="w-full border border-horizon-200 rounded px-3 py-2" />
            </div>

            <CoverImagePicker v-model="form.cover_image_path" :html-body="form.html_body" :article-id="article && article.id" />

            <div class="border border-horizon-200 rounded p-3">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-sm font-bold text-horizon-700">Campaign</label>
                    <router-link to="/admin/campaigns" class="text-xs text-raspberry-500 hover:text-raspberry-700 font-semibold">+ New campaign</router-link>
                </div>
                <select v-model="form.pipeline_campaign_id" class="w-full border border-horizon-200 rounded px-3 py-2 text-sm">
                    <option :value="null">— None (default Register CTA) —</option>
                    <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <p class="text-xs text-horizon-500 mt-1">Linking a campaign swaps the article's bottom call-to-action for the campaign's landing page.</p>
            </div>

            <div class="border border-horizon-200 rounded p-3">
                <label class="block text-sm font-bold text-horizon-700 mb-1">Social videos</label>
                <p v-if="!socialClips.length" class="text-xs text-horizon-500">
                    {{ socialStatus ? 'Pipeline status: ' + socialStatus + ' — no clips generated yet.' : 'No social clips linked to this article yet.' }}
                </p>
                <ul v-else class="space-y-1">
                    <li v-for="clip in socialClips" :key="clip.index" class="flex items-center justify-between text-sm">
                        <span class="text-horizon-700">Clip {{ clip.index }} <span class="text-horizon-400">({{ clip.filename }})</span></span>
                        <a :href="clip.url" target="_blank" rel="noopener" class="text-raspberry-500 hover:text-raspberry-700 font-semibold">Preview ↗</a>
                    </li>
                </ul>
            </div>

            <div class="flex flex-wrap gap-3 pt-4 border-t border-horizon-200">
                <button class="bg-horizon-700 text-eggshell-50 rounded px-4 py-2 font-bold hover:bg-horizon-800" @click="save">Save</button>
                <button class="bg-eggshell-100 text-horizon-700 rounded px-4 py-2 font-bold hover:bg-eggshell-500" @click="openPreview">Preview</button>
                <a
                    v-if="article.status === 'published'"
                    :href="`/insights/${form.slug}`"
                    target="_blank"
                    rel="noopener"
                    class="bg-eggshell-100 text-horizon-700 rounded px-4 py-2 font-bold hover:bg-eggshell-500 inline-flex items-center"
                >Open live ↗</a>
                <button
                    v-if="article.status !== 'published'"
                    class="bg-raspberry-500 text-eggshell-50 rounded px-4 py-2 font-bold hover:bg-raspberry-600"
                    @click="onPublish"
                >Publish</button>
                <button
                    v-else
                    class="bg-raspberry-500 text-eggshell-50 rounded px-4 py-2 font-bold hover:bg-raspberry-600"
                    @click="onUnpublish"
                >Unpublish</button>
                <button class="ml-auto text-raspberry-500 hover:text-raspberry-700 underline" @click="onDelete">Delete</button>
            </div>

            <p v-if="successMessage" class="text-spring-700">{{ successMessage }}</p>
            <p v-if="errorMessage" class="text-raspberry-700">{{ errorMessage }}</p>
        </section>

        <section class="space-y-2">
            <label class="block text-sm font-bold text-horizon-700">Body</label>
            <div class="border border-horizon-200 rounded p-4 bg-white min-h-[600px]">
                <editor-content v-if="editor" :editor="editor" class="prose max-w-none" />
            </div>
        </section>
            </div>
        </div>
        <div v-else class="text-horizon-500">Loading…</div>
    </AppLayout>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Underline } from '@tiptap/extension-underline';
import { Link } from '@tiptap/extension-link';
import { Image } from '@tiptap/extension-image';
import { Table } from '@tiptap/extension-table';
import { TableRow } from '@tiptap/extension-table-row';
import { TableCell } from '@tiptap/extension-table-cell';
import { TableHeader } from '@tiptap/extension-table-header';
import { TextStyle } from '@tiptap/extension-text-style';
import { Color } from '@tiptap/extension-color';
import { Highlight } from '@tiptap/extension-highlight';
import AppLayout from '@/layouts/AppLayout.vue';
import CoverImagePicker from '@/components/Admin/Documents/CoverImagePicker.vue';
import pipelineCampaignsService from '@/services/pipelineCampaignsService';
import documentArticleService from '@/services/documentArticleService';

export default {
    name: 'DocumentEditor',
    components: { AppLayout, EditorContent, CoverImagePicker },
    data() {
        return {
            form: {
                title: '',
                slug: '',
                subtitle: '',
                description: '',
                keywords: '',
                author_byline: '',
                cover_image_path: null,
                html_body: '',
                pipeline_campaign_id: null,
            },
            campaigns: [],
            socialClips: [],
            socialStatus: null,
            editor: null,
            successMessage: '',
            errorMessage: '',
        };
    },
    computed: {
        ...mapState('documentArticles', ['current']),
        article() { return this.current; },
    },
    async created() {
        const id = parseInt(this.$route.params.id, 10);
        await this.get(id);
        this.hydrateForm();
        this.mountEditor();
        this.loadCampaigns();
        this.loadSocialClips();
    },
    beforeUnmount() {
        if (this.editor) this.editor.destroy();
    },
    methods: {
        ...mapActions('documentArticles', ['get', 'update', 'publish', 'unpublish', 'destroy', 'previewUrl']),
        hydrateForm() {
            if (!this.article) return;
            this.form = {
                title: this.article.title || '',
                slug: this.article.slug || '',
                subtitle: this.article.subtitle || '',
                description: this.article.description || '',
                keywords: this.article.keywords || '',
                author_byline: this.article.author_byline || '',
                cover_image_path: this.article.cover_image_path,
                html_body: this.article.html_body || '',
                pipeline_campaign_id: this.article.pipeline_campaign_id ?? this.article.campaign?.id ?? null,
            };
        },
        async loadSocialClips() {
            if (!this.article?.id) return;
            try {
                const { data } = await documentArticleService.socialClips(this.article.id);
                this.socialClips = data?.data?.clips || [];
                this.socialStatus = data?.data?.status || null;
            } catch (e) {
                this.socialClips = [];
                this.socialStatus = null;
            }
        },
        async loadCampaigns() {
            try {
                const res = await pipelineCampaignsService.list();
                // The endpoint returns a paginator: { data: { data: [...] } }.
                const body = res?.data;
                this.campaigns = Array.isArray(body) ? body : (Array.isArray(body?.data) ? body.data : []);
            } catch (e) {
                this.campaigns = [];
            }
        },
        mountEditor() {
            this.editor = new Editor({
                content: this.form.html_body,
                extensions: [
                    StarterKit,
                    Underline,
                    Link.configure({ openOnClick: false }),
                    Image,
                    Table.configure({ resizable: false }),
                    TableRow,
                    TableHeader,
                    TableCell,
                    TextStyle,
                    Color,
                    Highlight,
                ],
                editorProps: {
                    attributes: { class: 'tiptap-editor focus:outline-none' },
                },
                onUpdate: ({ editor }) => {
                    this.form.html_body = editor.getHTML();
                },
            });
        },
        async save({ redirect = true } = {}) {
            this.errorMessage = '';
            this.successMessage = '';
            try {
                await this.update({ id: this.article.id, ...this.form });
                if (redirect) {
                    this.$router.push('/admin/documents');
                    return true;
                }
                this.successMessage = 'Saved.';
                return true;
            } catch (e) {
                this.errorMessage = e?.response?.data?.message || 'Save failed.';
                return false;
            }
        },
        async openPreview() {
            const url = await this.previewUrl(this.article.id);
            window.open(url, '_blank');
        },
        async onPublish() {
            const ok = await this.save({ redirect: false });
            if (!ok) return;
            try {
                await this.publish(this.article.id);
                this.$router.push('/admin/documents');
            } catch (e) {
                this.errorMessage = e?.response?.data?.message || 'Publish failed.';
            }
        },
        async onUnpublish() {
            try {
                await this.unpublish(this.article.id);
                this.$router.push('/admin/documents');
            } catch (e) {
                this.errorMessage = e?.response?.data?.message || 'Unpublish failed.';
            }
        },
        async onDelete() {
            if (!window.confirm(`Delete "${this.article.title}"? This cannot be undone.`)) return;
            await this.destroy(this.article.id);
            this.$router.push('/admin/documents');
        },
    },
};
</script>

<style scoped>
:deep(.tiptap-editor) {
    min-height: 540px;
    outline: none;
}
:deep(.tiptap-editor table) { border-collapse: collapse; width: 100%; margin: 12px 0; }
:deep(.tiptap-editor th), :deep(.tiptap-editor td) { @apply border border-eggshell-900 px-2.5 py-1.5; }
:deep(.tiptap-editor th) { @apply bg-savannah-300; }
:deep(.tiptap-editor img) { max-width: 100%; height: auto; }
</style>
