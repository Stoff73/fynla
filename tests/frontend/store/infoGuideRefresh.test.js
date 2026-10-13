import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createStore } from 'vuex';

vi.mock('@/services/api', () => ({ default: { get: vi.fn() } }));
import api from '@/services/api';
import infoGuide from '@/store/modules/infoGuide';
import { resolveModule } from '@/utils/moduleMap';

function makeStore() {
  return createStore({ modules: { infoGuide: { ...infoGuide, state: typeof infoGuide.state === 'function' ? infoGuide.state() : { ...infoGuide.state } } } });
}

describe('info guide requirements (walk R35, R36)', () => {
  beforeEach(() => {
    api.get.mockReset();
    api.get.mockImplementation((url, { params }) => Promise.resolve({ data: { data: { module: params.module, all_requirements: [], filled: [], missing: [] } } }));
  });

  it('maps the Bank Accounts page to the savings list, the map the router now reads', () => {
    expect(resolveModule('/net-worth/cash')).toBe('savings');
  });

  it('skips a second fetch of the same module, and refreshRequirements fetches it again', async () => {
    const store = makeStore();
    await store.dispatch('infoGuide/fetchRequirements', 'savings');
    await store.dispatch('infoGuide/fetchRequirements', 'savings');
    expect(api.get).toHaveBeenCalledTimes(1);

    await store.dispatch('infoGuide/refreshRequirements');
    expect(api.get).toHaveBeenCalledTimes(2);
    expect(api.get.mock.calls[1][1].params.module).toBe('savings');
  });
});
