import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({ apiGet: vi.fn(), apiPost: vi.fn(), apiPut: vi.fn(), apiStream: vi.fn() }));
vi.mock('../../navigation/webHandoff.js', () => ({ issueWebHandoff: vi.fn() }));

import { apiGet, apiPost, apiPut } from '../../api.js';
import { store } from '../../store.js';
import ActionCard from '../ActionCard.vue';

/*
 * /m action card (design C). Payload keys are ActionCardService::open()'s — the
 * view renders them as sent (CSJ 2026-08-23: /m never works anything out).
 */
const card = (over = {}) => ({
  id: 'tax_pension_tax_relief', type: 'recommendation', module: 'tax', module_label: 'Tax', topic: 'Income Band',
  deadline: { label: 'Closes 5 April', closes_on: '2027-04-05' },
  title: 'Pay £3,700 more into your pension and save £1,480 in tax',
  description: 'Pension contributions get tax relief at your highest rate.',
  why: ['£3,700 of your income is taxed at 40%'], what_this_changes: [],
  key_figure: { label: 'Saves about', value: '£1,480 a year', sub: null },
  how_to: ['Decide how much.'], conflict_note: null, disclaimer: null,
  ask_fyn: { kind: 'prompt', prompt: 'Tell me more about: Pay £3,700 more' },
  primary: { kind: 'mark_done', recommendation_id: 'tax_pension_tax_relief' },
  funding: { accounts: [{ id: 7, type: 'savings', name: 'Nationwide Instant Access', balance: 40000, warning: null }], selected_id: 7, selected_type: 'savings' },
  done: false, completed_at: null, ...over,
});

const chrome = { openFyn: vi.fn(() => Promise.resolve()), send: vi.fn(), openContextualFyn: vi.fn(() => Promise.resolve()) };
const stubs = {
  MobileChrome: { template: '<div><slot /></div>', methods: chrome },
};

const mountCard = (payload) => {
  apiGet.mockResolvedValue({ ok: true, status: 200, data: { success: true, data: payload } });
  apiPost.mockResolvedValue({ ok: true, status: 200, data: { success: true } });
  apiPut.mockResolvedValue({ ok: true, status: 200, data: { success: true } });
  return mount(ActionCard, { global: { stubs, mocks: { $route: { params: { id: payload.id } }, $router: { push: vi.fn(), back: vi.fn() } } } });
};

describe('/m action card', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
  });

  it('renders the card from the payload', async () => {
    const w = mountCard(card());
    await flushPromises();
    const text = w.text();

    expect(apiGet).toHaveBeenCalledWith('/api/recommendations/actions/tax_pension_tax_relief', 'live-token');
    ['Closes 5 April', 'Tax · Income Band', card().title, 'Why this matters for you', card().why[0], '£1,480 a year', 'Fund from', 'Nationwide Instant Access', 'How to do it', 'Decide how much.']
      .forEach((s) => expect(text).toContain(s));
  });

  it('marks the action done', async () => {
    const w = mountCard(card());
    await flushPromises();
    apiGet.mockResolvedValue({ ok: true, status: 200, data: { data: card({ done: true, completed_at: '2026-09-26T10:00:00Z' }) } });

    await w.get('[data-testid="mark-done"]').trigger('click');
    await flushPromises();

    expect(apiPost).toHaveBeenCalledWith('/api/recommendations/tax_pension_tax_relief/mark-done', expect.objectContaining({ module: 'tax' }), 'live-token');
    expect(w.find('[data-testid="mark-done"]').exists()).toBe(false);
  });

  it('asks Fyn with the card prompt through the chrome', async () => {
    const w = mountCard(card());
    await flushPromises();

    await w.get('[data-testid="ask-fyn"]').trigger('click');
    await flushPromises();

    expect(chrome.openFyn).toHaveBeenCalled();
    expect(chrome.send).toHaveBeenCalledWith(card().ask_fyn.prompt);
  });

  it('saves the funding pick', async () => {
    const w = mountCard(card({ funding: { accounts: [
      { id: 7, type: 'savings', name: 'A', balance: 1, warning: null },
      { id: 9, type: 'savings', name: 'B', balance: 1, warning: null },
    ], selected_id: 7, selected_type: 'savings' } }));
    await flushPromises();

    await w.get('input[value="savings:9"]').setValue(true);
    await flushPromises();

    expect(apiPut).toHaveBeenCalledWith('/api/plans/tax/funding-source', {
      action_category: 'pension_tax_relief', target_account_id: 0, funding_source_type: 'savings', funding_source_id: 9,
    }, 'live-token');
  });

  it('gives an unlock item the waiting-on-you shape', async () => {
    const w = mountCard(card({ id: 'unlock:retirement', type: 'unlock', why: [], key_figure: null, funding: null, how_to: [],
      what_this_changes: ['Your retirement projection is understated until this is in.'],
      primary: { kind: 'capture', prompt: 'Help me add my pension details' } }));
    await flushPromises();

    expect(w.text()).not.toContain('Why this matters for you');
    expect(w.text()).toContain('What this changes');
    await w.get('[data-testid="primary-capture"]').trigger('click');
    await flushPromises();
    expect(chrome.send).toHaveBeenCalledWith('Help me add my pension details');
  });

  it('offers Go to it when the card carries the page the action is done on', async () => {
    const w = mountCard(card({ go_to: { destination: { screen: 'tax_strategy', params: {} }, payload: '/tax-strategy' } }));
    await flushPromises();

    expect(w.find('[data-testid="go-to"]').exists()).toBe(true);
  });
});
