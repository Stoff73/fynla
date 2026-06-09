import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

// The component reaches out to the mobile store, api, diagnostics and the
// shared console-capture service on submit. Stub them so the test exercises
// only the success-state copy (the regression target for issue #510).
vi.mock('../../store.js', () => ({ store: { token: 'test-token' } }));
vi.mock('../../api.js', () => ({
  apiPost: vi.fn(() => Promise.resolve({ ok: true, status: 200, data: {} })),
}));
vi.mock('../../diagnostics.js', () => ({
  gatherDiagnostics: vi.fn(() => Promise.resolve({})),
}));
vi.mock('../../../js/services/consoleCapture.js', () => ({
  default: { getLogs: () => '' },
}));

import BugReportSheet from '../BugReportSheet.vue';

describe('BugReportSheet.vue', () => {
  let wrapper;

  beforeEach(() => {
    wrapper = mount(BugReportSheet, {
      props: { show: true },
      global: {
        mocks: {
          $route: { fullPath: '/m/app' },
        },
      },
    });
  });

  it('confirms the report was logged on success, without generic copy', async () => {
    await wrapper.find('#m-bug-desc').setValue('Something is broken');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();

    const done = wrapper.find('.m-sheet-done');
    expect(done.exists()).toBe(true);

    const text = done.text();
    expect(text).toContain('Report logged');
    expect(text).toContain('logged and sent');
    // Regression guard: the old generic copy must not return.
    expect(text).not.toContain('Your report has been sent. We');
  });
});
