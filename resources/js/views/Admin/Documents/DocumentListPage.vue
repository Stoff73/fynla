<template>
    <AppLayout>
        <div class="space-y-8">
            <header>
                <h1 class="text-3xl font-black text-horizon-700">CMS Upload</h1>
                <p class="text-horizon-500 mt-1">Drop a Word document to create a new article.</p>
            </header>

            <DropZone
                @imported="onImported"
                @error="onError"
            />

            <div v-if="errorMessage" class="bg-raspberry-50 border border-raspberry-200 text-raspberry-700 rounded p-4">
                {{ errorMessage }}
            </div>

            <section>
                <h2 class="text-xl font-bold text-horizon-700 mb-4">All documents</h2>
                <div v-if="loading" class="flex items-center gap-3 text-horizon-500">
                    <div class="w-6 h-6 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
                    Loading…
                </div>
                <table v-else-if="items.length > 0" class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-horizon-200">
                            <th class="py-3">Title</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Imported</th>
                            <th class="py-3">By</th>
                            <th class="py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in items" :key="item.id" class="border-b border-horizon-100">
                            <td class="py-3">
                                <router-link :to="`/admin/documents/${item.id}/edit`" class="font-bold text-horizon-700 hover:text-raspberry-500">
                                    {{ item.title }}
                                </router-link>
                            </td>
                            <td class="py-3">
                                <span
                                    class="inline-block px-2 py-1 rounded text-xs font-bold"
                                    :class="item.status === 'published' ? 'bg-spring-100 text-spring-700' : 'bg-savannah-100 text-savannah-700'"
                                >
                                    {{ item.status }}
                                </span>
                            </td>
                            <td class="py-3 text-horizon-500">{{ formatDate(item.created_at) }}</td>
                            <td class="py-3 text-horizon-500">{{ item.importer?.name || '—' }}</td>
                            <td class="py-3 text-right space-x-2">
                                <router-link
                                    :to="`/admin/documents/${item.id}/edit`"
                                    class="text-horizon-500 hover:text-raspberry-500 underline"
                                >Edit</router-link>
                                <button
                                    v-if="item.status === 'published'"
                                    class="text-horizon-500 hover:text-raspberry-500 underline"
                                    @click="unpublish(item.id)"
                                >Unpublish</button>
                                <button
                                    v-else
                                    class="text-horizon-500 hover:text-raspberry-500 underline"
                                    @click="publish(item.id)"
                                >Publish</button>
                                <button class="text-raspberry-500 hover:text-raspberry-700 underline" @click="confirmDelete(item)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-horizon-500">No documents yet — drop a .docx above.</p>
            </section>
        </div>
    </AppLayout>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import DropZone from '@/components/Admin/Documents/DropZone.vue';

export default {
    name: 'DocumentListPage',
    components: { AppLayout, DropZone },
    data() {
        return { errorMessage: '' };
    },
    computed: {
        ...mapState('documentArticles', ['items', 'loading']),
    },
    async created() {
        await this.list();
    },
    methods: {
        ...mapActions('documentArticles', ['list', 'publish', 'unpublish', 'destroy']),
        onImported(created) {
            this.errorMessage = '';
            this.$router.push(`/admin/documents/${created.id}/edit`);
        },
        onError(message) {
            this.errorMessage = message;
        },
        async confirmDelete(item) {
            if (!window.confirm(`Delete "${item.title}"? This cannot be undone.`)) return;
            await this.destroy(item.id);
        },
        formatDate(s) {
            if (!s) return '';
            const d = new Date(s);
            return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        },
    },
};
</script>
