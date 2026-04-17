<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">
          Article templates
        </h1>
        <router-link to="/admin/insights" class="text-sm text-neutral-500 hover:text-horizon-500">
          &larr; Articles
        </router-link>
      </header>

      <div class="card overflow-hidden">
        <table class="w-full">
          <thead class="bg-savannah-100 text-xs text-horizon-500 uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Name</th>
              <th class="px-4 py-3 text-left">Description</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in templates" :key="t.id" class="border-t border-light-gray hover:bg-savannah-100">
              <td class="px-4 py-3 font-semibold text-horizon-500">{{ t.name }}</td>
              <td class="px-4 py-3 text-sm text-neutral-500">{{ t.description }}</td>
              <td class="px-4 py-3 text-right space-x-3">
                <button type="button" @click="rename(t)" class="text-sm text-horizon-500 hover:underline">
                  Rename
                </button>
                <button type="button" @click="remove(t)" class="text-sm text-raspberry-500 hover:underline">
                  Delete
                </button>
              </td>
            </tr>
            <tr v-if="!templates.length">
              <td colspan="3" class="p-6 text-center text-sm text-neutral-500">
                No templates yet. Create one by editing an article and clicking &ldquo;Save as template&rdquo;.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';

export default {
  name: 'TemplateListPage',
  components: { AppLayout },
  computed: { ...mapGetters('insights', ['templates']) },
  async mounted() { await this.fetchTemplates(); },
  methods: {
    ...mapActions('insights', ['fetchTemplates', 'renameTemplate', 'deleteTemplate']),
    async rename(t) {
      const name = window.prompt('New name:', t.name);
      if (!name) return;
      try {
        await this.renameTemplate({ id: t.id, name });
      } catch (e) {
        alert('Rename failed: ' + (e.response?.data?.message || e.message));
      }
    },
    async remove(t) {
      if (!window.confirm(`Delete template "${t.name}"? Articles using it will keep their blocks but lose the template reference.`)) return;
      try {
        await this.deleteTemplate(t.id);
      } catch (e) {
        alert('Delete failed: ' + (e.response?.data?.message || e.message));
      }
    },
  },
};
</script>
