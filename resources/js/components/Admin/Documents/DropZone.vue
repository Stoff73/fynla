<template>
    <div
        class="drop-zone"
        :class="{ 'drop-zone--active': isDragging, 'drop-zone--busy': isProcessing }"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="onDrop"
        @click="$refs.input.click()"
    >
        <input ref="input" type="file" accept=".docx" class="hidden" @change="onPick" />
        <div v-if="!isProcessing" class="text-center">
            <p class="text-base font-bold text-horizon-700">Drop a Word document here</p>
            <p class="text-sm text-horizon-500 mt-1">or click to choose a .docx file (max 10 MB)</p>
        </div>
        <div v-else class="text-center">
            <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin mx-auto"></div>
            <p class="text-sm text-horizon-500 mt-3">{{ progressMessage }}</p>
        </div>
    </div>
</template>

<script>
import mammoth from 'mammoth';
import JSZip from 'jszip';

export default {
    name: 'DropZone',
    emits: ['imported', 'error'],
    data() {
        return {
            isDragging: false,
            isProcessing: false,
            progressMessage: '',
        };
    },
    methods: {
        onDrop(event) {
            this.isDragging = false;
            const file = event.dataTransfer.files[0];
            if (file) this.handleFile(file);
        },
        onPick(event) {
            const file = event.target.files[0];
            if (file) this.handleFile(file);
            event.target.value = '';
        },
        async handleFile(file) {
            if (!file.name.toLowerCase().endsWith('.docx')) {
                this.$emit('error', 'Only .docx files are supported.');
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                this.$emit('error', 'File is larger than 10 MB.');
                return;
            }

            this.isProcessing = true;
            try {
                this.progressMessage = 'Reading document…';
                const arrayBuffer = await file.arrayBuffer();

                this.progressMessage = 'Extracting metadata…';
                const metadata = await this.extractMetadata(arrayBuffer);

                this.progressMessage = 'Converting body…';
                const { html, images } = await this.convertWithMammoth(arrayBuffer);

                this.progressMessage = 'Uploading…';
                const created = await this.$store.dispatch('documentArticles/import', {
                    docx: file,
                    html,
                    images,
                    metadata,
                });

                this.$emit('imported', created);
            } catch (err) {
                this.$emit('error', err?.response?.data?.message || err.message || 'Import failed.');
            } finally {
                this.isProcessing = false;
                this.progressMessage = '';
            }
        },
        async extractMetadata(arrayBuffer) {
            try {
                const zip = await JSZip.loadAsync(arrayBuffer);
                const xml = await zip.file('docProps/core.xml')?.async('string');
                if (!xml) return {};
                const parser = new DOMParser();
                const doc = parser.parseFromString(xml, 'application/xml');
                const get = (ns, tag) => {
                    const el = doc.getElementsByTagNameNS(ns, tag)[0];
                    return el ? el.textContent : null;
                };
                return {
                    title: get('http://purl.org/dc/elements/1.1/', 'title'),
                    subtitle: get('http://purl.org/dc/elements/1.1/', 'subject'),
                    description: get('http://purl.org/dc/elements/1.1/', 'description'),
                    author_name: get('http://purl.org/dc/elements/1.1/', 'creator'),
                    keywords: get('http://schemas.openxmlformats.org/package/2006/metadata/core-properties', 'keywords'),
                };
            } catch {
                return {};
            }
        },
        async convertWithMammoth(arrayBuffer) {
            const images = {};
            let counter = 0;
            const result = await mammoth.convertToHtml(
                { arrayBuffer },
                {
                    convertImage: mammoth.images.imgElement(async (image) => {
                        const idx = counter++;
                        const buffer = await image.read();
                        const blob = new Blob([buffer], { type: image.contentType || 'image/png' });
                        images[idx] = blob;
                        return { 'data-pending-image': String(idx) };
                    }),
                }
            );
            return { html: result.value, images };
        },
    },
};
</script>

<style scoped>
.drop-zone {
    @apply border-2 border-dashed border-horizon-300 rounded-lg p-12 cursor-pointer flex items-center justify-center min-h-[180px] transition-colors;
    background: rgba(255, 255, 255, 0.5);
}
.drop-zone--active {
    @apply border-raspberry-500 bg-raspberry-50;
}
.drop-zone--busy {
    @apply cursor-default;
}
.hidden {
    display: none;
}
</style>
