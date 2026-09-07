import { describe, it, expect, vi } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';
import { createStore } from 'vuex';

vi.mock('@/services/api', () => ({
  default: { get: vi.fn(), post: vi.fn() },
}));

const DocumentUploadModal = (await import('../../Shared/DocumentUploadModal.vue')).default;

/**
 * W-0054. The upload modal opened for every tier and refused the file afterwards
 * with a 403 from CheckSubscription's statement_upload map. The gate now runs
 * before entry: without the grant the drop zone is never rendered, so no file can
 * be chosen and no request can be refused.
 */
function makeStore(capabilities) {
  return createStore({
    modules: {
      auth: {
        namespaced: true,
        state: () => ({
          user: { id: 31, is_admin: false, is_preview_user: false },
          tierFlags: { capabilities },
        }),
        getters: {
          hasCapability: (s) => (key) => s.user?.is_admin === true
            || s.user?.is_preview_user === true
            || s.tierFlags?.capabilities?.[key] === 'full',
        },
      },
    },
  });
}

async function mountModal(capabilities) {
  const wrapper = shallowMount(DocumentUploadModal, {
    global: {
      plugins: [makeStore(capabilities)],
      stubs: { 'router-link': true },
    },
  });
  await flushPromises();
  return wrapper;
}

describe('DocumentUploadModal tier gate', () => {
  it('withholds the drop zone from a tier without statement_upload', async () => {
    const wrapper = await mountModal({ statement_upload: 'none' });

    expect(wrapper.vm.canUploadDocuments).toBe(false);
    expect(wrapper.findComponent({ name: 'UploadDropZone' }).exists()).toBe(false);
    expect(wrapper.text()).toContain('Document upload is a Premium feature');
  });

  it('renders the drop zone for a tier that holds the capability', async () => {
    const wrapper = await mountModal({ statement_upload: 'full' });

    expect(wrapper.vm.canUploadDocuments).toBe(true);
    expect(wrapper.findComponent({ name: 'UploadDropZone' }).exists()).toBe(true);
    expect(wrapper.text()).not.toContain('Document upload is a Premium feature');
  });
});
