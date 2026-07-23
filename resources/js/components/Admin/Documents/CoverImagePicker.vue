<template>
    <div class="space-y-3">
        <label class="block text-sm font-bold text-horizon-700">Cover image</label>

        <!-- Preview of the effective cover as it appears on the public listing /
             homepage card: the selected image, or the default Fynla cover when
             none is set (matches the public fallback). -->
        <div class="rounded overflow-hidden border border-horizon-200 bg-eggshell-100 aspect-video">
            <img :src="effectiveCover" alt="Cover image preview" class="w-full h-full object-cover" />
        </div>
        <p class="text-xs text-horizon-500">
            {{ modelValue ? 'This image is used on listing and homepage cards.' : 'No cover chosen — the default Fynla cover will be used on cards.' }}
        </p>

        <div v-if="extractedImages.length > 0">
            <p class="text-xs font-bold text-horizon-700 mb-2">Choose an image from the document</p>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="img in extractedImages"
                    :key="img.path"
                    type="button"
                    class="aspect-square border-2 rounded overflow-hidden bg-eggshell-100 transition-colors"
                    :class="img.path === modelValue ? 'border-raspberry-500' : 'border-horizon-200 hover:border-horizon-400'"
                    @click="$emit('update:modelValue', img.path)"
                >
                    <img :src="'/storage/' + img.path" alt="" class="w-full h-full object-cover" />
                </button>
            </div>
        </div>

        <!-- Upload a cover image (for articles with no suitable embedded image). -->
        <div class="flex flex-wrap items-center gap-3 pt-1">
            <label
                class="text-sm font-bold rounded px-3 py-2 border border-horizon-200 bg-eggshell-100 hover:bg-eggshell-500 cursor-pointer"
                :class="{ 'opacity-60 cursor-not-allowed hover:bg-eggshell-100': uploading || !articleId }"
            >
                {{ uploading ? 'Uploading…' : 'Upload cover image' }}
                <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    class="hidden"
                    :disabled="uploading || !articleId"
                    @change="onUpload"
                />
            </label>
            <button
                v-if="modelValue"
                type="button"
                class="text-sm text-horizon-500 hover:text-raspberry-500 underline"
                @click="$emit('update:modelValue', null)"
            >
                Clear cover image
            </button>
        </div>
        <p v-if="uploadError" class="text-sm text-raspberry-500">{{ uploadError }}</p>

        <!-- Auto-find a relevant stock photo (Pexels) by article keywords. -->
        <div class="flex flex-wrap items-center gap-2 pt-1">
            <input
                v-model="stockQuery"
                type="text"
                placeholder="Search stock photos…"
                class="flex-1 min-w-[10rem] border border-horizon-200 rounded px-2 py-1.5 text-sm"
            />
            <button
                type="button"
                class="text-sm font-bold rounded px-3 py-2 border border-horizon-200 bg-eggshell-100 hover:bg-eggshell-500"
                :class="{ 'opacity-60 cursor-not-allowed hover:bg-eggshell-100': stockLoading || !articleId }"
                :disabled="stockLoading || !articleId"
                @click="findStock"
            >{{ stockLoading ? 'Searching…' : 'Find stock photo' }}</button>
        </div>
        <p v-if="stockError" class="text-sm text-raspberry-500">{{ stockError }}</p>
        <p v-if="!articleId" class="text-xs text-horizon-500">Save the article once before adding a cover image.</p>
    </div>
</template>

<script>
import documentArticleService from '@/services/documentArticleService';

export default {
    name: 'CoverImagePicker',
    props: {
        modelValue: { type: String, default: null }, // current cover_image_path
        htmlBody: { type: String, default: '' }, // article html body — we scan for <img src> within /storage/document-articles/
        articleId: { type: [Number, String], default: null }, // document article id — required to upload
        searchQuery: { type: String, default: '' }, // default stock-search terms (e.g. article title)
    },
    emits: ['update:modelValue'],
    data() {
        return { uploading: false, uploadError: '', stockQuery: this.searchQuery, stockLoading: false, stockError: '' };
    },
    watch: {
        searchQuery(value) {
            if (!this.stockQuery) this.stockQuery = value;
        },
    },
    methods: {
        async findStock() {
            if (!this.articleId) return;
            this.stockLoading = true;
            this.stockError = '';
            try {
                const { data } = await documentArticleService.stockCover(this.articleId, this.stockQuery);
                this.$emit('update:modelValue', data.path);
            } catch (err) {
                this.stockError = err?.response?.data?.message || 'Stock photo search failed.';
            } finally {
                this.stockLoading = false;
            }
        },
        async onUpload(event) {
            const file = event.target.files && event.target.files[0];
            event.target.value = ''; // reset so the same file can be re-selected
            if (!file || !this.articleId) return;
            this.uploading = true;
            this.uploadError = '';
            try {
                const { data } = await documentArticleService.uploadCover(this.articleId, file);
                this.$emit('update:modelValue', data.path);
            } catch (err) {
                this.uploadError = err?.response?.data?.message || 'Upload failed. Use a JPG, PNG or WebP under 5MB.';
            } finally {
                this.uploading = false;
            }
        },
    },
    computed: {
        effectiveCover() {
            return this.modelValue
                ? '/storage/' + this.modelValue
                : '/images/insights/insight-default.jpg';
        },
        extractedImages() {
            const matches = [...this.htmlBody.matchAll(/<img[^>]+src="\/storage\/(document-articles\/[^"]+)"/g)];
            const seen = new Set();
            return matches
                .map(m => ({ path: m[1] }))
                .filter(({ path }) => {
                    if (seen.has(path)) return false;
                    seen.add(path);
                    return true;
                });
        },
    },
};
</script>
