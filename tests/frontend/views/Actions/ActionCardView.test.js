import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ActionCardView from '@/views/Actions/ActionCardView.vue';
import api from '@/services/api';

vi.mock('@/services/api', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }));

/*
 * The web action card (design C). Payload keys are ActionCardService::open()'s
 * (app/Services/Actions/ActionCardService.php) — the view renders them as sent.
 */
const card = (over = {}) => ({
  id: 'tax_pension_tax_relief',
  type: 'recommendation',
  module: 'tax',
  module_label: 'Tax',
  topic: 'Income Band',
  deadline: { label: 'Closes 5 April', closes_on: '2027-04-05' },
  title: 'Pay £3,700 more into your pension and save £1,480 in tax',
  description: 'Pension contributions get tax relief at your highest rate.',
  why: ['£3,700 of your income is taxed at 40%', 'Pension contributions get tax relief at that 40% rate'],
  what_this_changes: [],
  key_figure: { label: 'Saves about', value: '£1,480 a year', sub: null },
  how_to: [],
  conflict_note: null,
  disclaimer: null,
  ask_fyn: { kind: 'prompt', prompt: 'Tell me more about: Pay £3,700 more into your pension and save £1,480 in tax' },
  primary: { kind: 'mark_done', recommendation_id: 'tax_pension_tax_relief' },
  funding: {
    accounts: [
      { id: 7, type: 'savings', name: 'Nationwide Instant Access', balance: 40000, warning: null },
      { id: 9, type: 'savings', name: 'Santander Everyday', balance: 4200, warning: 'Withdrawing would reduce your emergency fund below 6 months of expenditure.' },
    ],
    selected_id: 7,
    selected_type: 'savings',
  },
  done: false,
  completed_at: null,
  ...over,
});

const mountCard = (payload) => {
  api.get.mockResolvedValue({ data: { success: true, data: payload } });
  api.post.mockResolvedValue({ data: { success: true } });
  api.put.mockResolvedValue({ data: { success: true } });
  const dispatch = vi.fn(() => Promise.resolve());
  const wrapper = mount(ActionCardView, {
    global: {
      stubs: { AppLayout: { template: '<div><slot /></div>' }, 'router-link': true },
      mocks: { $route: { params: { actionId: payload.id } }, $router: { push: vi.fn() }, $store: { dispatch } },
    },
  });
  return { wrapper, dispatch };
};

describe('ActionCardView', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders the card sections in the design order from the payload', async () => {
    const { wrapper } = mountCard(card());
    await flushPromises();
    const text = wrapper.text();

    expect(api.get).toHaveBeenCalledWith('/recommendations/actions/tax_pension_tax_relief');
    expect(text).toContain('Closes 5 April');
    expect(text).toContain('Tax · Income Band');
    expect(text).toContain(card().title);
    expect(text).toContain('Why this matters for you');
    card().why.forEach((line) => expect(text).toContain(line));
    expect(text).toContain('Saves about');
    expect(text).toContain('£1,480 a year');
    expect(text).toContain('Fund from');
    expect(text).toContain('Nationwide Instant Access');
    expect(text).toContain('below 6 months');
    expect(text).not.toContain('How to do it');
    expect(text.indexOf('Why this matters for you')).toBeLessThan(text.indexOf('Saves about'));
  });

  it('shows approved steps when the payload carries them', async () => {
    const { wrapper } = mountCard(card({ how_to: ['Decide how much.', 'Pay it in.'] }));
    await flushPromises();

    expect(wrapper.text()).toContain('How to do it');
    expect(wrapper.findAll('[data-testid="how-to-step"]').map((s) => s.text())).toEqual(['Decide how much.', 'Pay it in.']);
  });

  it('marks the action done and then shows it as done', async () => {
    const { wrapper } = mountCard(card());
    await flushPromises();
    api.get.mockResolvedValue({ data: { success: true, data: card({ done: true, completed_at: '2026-09-26T10:00:00Z' }) } });

    await wrapper.get('[data-testid="mark-done"]').trigger('click');
    await flushPromises();

    expect(api.post).toHaveBeenCalledWith('/recommendations/tax_pension_tax_relief/mark-done', expect.objectContaining({ module: 'tax' }));
    expect(wrapper.text()).toContain('Done');
    expect(wrapper.find('[data-testid="mark-done"]').exists()).toBe(false);
  });

  it('asks Fyn with the card prompt', async () => {
    const { wrapper, dispatch } = mountCard(card());
    await flushPromises();

    await wrapper.get('[data-testid="ask-fyn"]').trigger('click');

    expect(dispatch).toHaveBeenCalledWith('aiChat/prefillPrompt', card().ask_fyn.prompt);
    expect(dispatch).toHaveBeenCalledWith('aiChat/open');
  });

  it('starts a contextual conversation when the card carries one', async () => {
    const request = { action: 'update', resource_type: 'savings_account', resource_id: null };
    const { wrapper, dispatch } = mountCard(card({ ask_fyn: { kind: 'contextual', request } }));
    await flushPromises();

    await wrapper.get('[data-testid="ask-fyn"]').trigger('click');
    await flushPromises();

    expect(dispatch).toHaveBeenCalledWith('aiChat/startContextualConversation', request);
  });

  it('saves the funding pick', async () => {
    const { wrapper } = mountCard(card());
    await flushPromises();

    await wrapper.get('input[value="savings:9"]').setValue(true);
    await flushPromises();

    expect(api.put).toHaveBeenCalledWith('/plans/tax/funding-source', {
      action_category: 'pension_tax_relief', target_account_id: 0, funding_source_type: 'savings', funding_source_id: 9,
    });
  });

  it('gives an unlock item the waiting-on-you shape', async () => {
    const { wrapper, dispatch } = mountCard(card({
      id: 'unlock:retirement', type: 'unlock', module: 'retirement', module_label: 'Retirement', topic: null, deadline: null,
      title: 'Unlock Retirement advice', description: 'A few quick questions', why: [], key_figure: null, funding: null,
      what_this_changes: ['Your retirement projection is understated until this is in.'],
      primary: { kind: 'capture', prompt: 'Help me add my pension details' },
    }));
    await flushPromises();
    const text = wrapper.text();

    expect(text).not.toContain('Why this matters for you');
    expect(text).toContain('What this changes');
    await wrapper.get('[data-testid="primary-capture"]').trigger('click');
    expect(dispatch).toHaveBeenCalledWith('aiChat/prefillPrompt', 'Help me add my pension details');
  });

  it('offers Go to it when the card carries the page the action is done on', async () => {
    const { wrapper } = mountCard(card({ go_to: { destination: { screen: 'tax_strategy', params: {} }, payload: '/tax-strategy' } }));
    await flushPromises();

    expect(wrapper.find('[data-testid="go-to"]').exists()).toBe(true);
    expect(wrapper.find('[data-testid="mark-done"]').exists()).toBe(true);
  });
});
