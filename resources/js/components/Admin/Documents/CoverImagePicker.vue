<template>
    <div class="space-y-3">
        <label class="block text-sm font-bold text-horizon-700">Cover image</label>
        <div v-if="extractedImages.length > 0" class="grid grid-cols-3 gap-2">
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
        <p v-else class="text-sm text-horizon-500">No images were extracted from this document.</p>
        <button
            v-if="modelValue"
            type="button"
            class="text-sm text-horizon-500 hover:text-raspberry-500 underline"
            @click="$emit('update:modelValue', null)"
        >
            Clear cover image
        </button>
    </div>
</template>

<script>
export default {
    name: 'CoverImagePicker',
    props: {
        modelValue: { type: String, default: null }, // current cover_image_path
        htmlBody: { type: String, default: '' }, // article html body — we scan for <img src> within /storage/document-articles/
    },
    emits: ['update:modelValue'],
    computed: {
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
