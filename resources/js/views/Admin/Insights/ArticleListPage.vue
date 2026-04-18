<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Content Management System</h1>
          <p class="text-sm text-neutral-500 mt-1">Manage published and draft articles.</p>
        </div>
        <div class="flex items-center gap-2">
          <router-link
            to="/admin/insights/templates"
            class="px-4 py-2.5 text-sm font-semibold border border-horizon-500 text-horizon-500 rounded-lg hover:bg-horizon-50"
          >
            Templates
          </router-link>
          <router-link
            to="/admin/insights/new"
            class="px-5 py-2.5 bg-raspberry-500 text-white rounded-lg text-sm font-semibold hover:bg-raspberry-600"
          >
            + New article
          </router-link>
        </div>
      </header>

      <div class="flex flex-wrap gap-3 mb-6">
        <select v-model="filters.status" class="px-3 py-2 text-sm border border-light-gray rounded-lg">
          <option value="">All statuses</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="archived">Archived</option>
        </select>
        <select v-model="filters.category" class="px-3 py-2 text-sm border border-light-gray rounded-lg">
          <option value="">All categories</option>
          <option value="tax-changes">Tax changes</option>
          <option value="pensions">Pensions</option>
          <option value="savings-isa">Savings & ISA</option>
          <option value="estate-planning">Estate planning</option>
          <option value="platform-updates">Platform updates</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-horizon-500">
          <input type="checkbox" v-model="filters.featured" class="rounded" />
          Featured only
        </label>
      </div>

      <div class="card overflow-hidden">
        <table class="w-full">
          <thead class="bg-savannah-100 text-xs text-horizon-500 uppercase tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Title</th>
              <th class="px-4 py-3 text-left">Category</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Published</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="article in articles"
              :key="article.slug"
              class="border-t border-light-gray hover:bg-savannah-100"
            >
              <td class="px-4 py-3">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-horizon-500">{{ article.title }}</span>
                  <span
                    v-if="article.is_bespoke"
                    class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded uppercase bg-violet-100 text-violet-700"
                  >Bespoke</span>
                  <span
                    v-if="article.is_featured"
                    class="text-[0.6rem] font-semibold px-1.5 py-0.5 rounded uppercase bg-raspberry-100 text-raspberry-700"
                  >Featured</span>
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-neutral-500">{{ article.category }}</td>
              <td class="px-4 py-3">
                <span :class="statusClass(article.status)" class="text-xs font-semibold px-2 py-1 rounded uppercase">
                  {{ article.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-neutral-500">{{ formatPublished(article.published_at) }}</td>
              <td class="px-4 py-3 text-right">
                <router-link
                  :to="`/admin/insights/${article.id}/edit`"
                  class="text-sm text-raspberry-500 hover:underline"
                >
                  Edit
                </router-link>
              </td>
            </tr>
            <tr v-if="!articles.length">
              <td colspan="5" class="px-4 py-10 text-center text-sm text-neutral-500">
                No articles match the current filters.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateLong } from '@/utils/dateFormatter';

export default {
  name: 'ArticleListPage',
  components: { AppLayout },
  data() {
    return {
      filters: { status: '', category: '', featured: false },
    };
  },
  computed: {
    ...mapState('insights', { articles: 'adminList' }),
  },
  watch: {
    filters: { deep: true, handler() { this.reload(); } },
  },
  async mounted() { await this.reload(); },
  methods: {
    ...mapActions('insights', ['fetchAdminList']),
    async reload() {
      await this.fetchAdminList({
        status: this.filters.status || undefined,
        category: this.filters.category || undefined,
        featured: this.filters.featured || undefined,
      });
    },
    formatPublished(iso) {
      return iso ? formatDateLong(iso) : '—';
    },
    statusClass(status) {
      return {
        'bg-neutral-100 text-neutral-500': status === 'draft',
        'bg-spring-100 text-spring-700': status === 'published',
        'bg-light-gray text-neutral-500': status === 'archived',
      };
    },
  },
};
</script>
