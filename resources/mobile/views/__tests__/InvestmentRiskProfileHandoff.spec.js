import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';

vi.mock('../../api.js', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiStream: vi.fn(),
}));

vi.mock('../../navigation/webHandoff.js', () => ({
  issueWebHandoff: vi.fn(),
}));

import { apiGet } from '../../api.js';
import { issueWebHandoff } from '../../navigation/webHandoff.js';
import { store } from '../../store.js';
import Investment from '../modules/Investment.vue';

/**
 * W-0279 — `/m` showed the risk engine's conclusion and offered no way behind it.
 *
 * `resources/mobile/router.js` has no risk route: no equivalent of `/risk-profile`,
 * `/risk-profile/levels` or `/risk-profile/factor/:factor`. But this screen renders
 * `risk_profile.risk_level` as the attitude to risk shown against the portfolio, so
 * a mobile user was shown the ANSWER with no route to the nine factors behind it, no
 * way to see which figure produced it, and no way to correct one that was wrong.
 *
 * The breakdown is not rebuilt here. It hands off to the web screen that already has
 * it — the same decision the estate screen took for the Inheritance Tax breakdown
 * under W-0469, rather than rendering a subset of it on a second surface.
 */
const payloadWithRisk = (riskProfile) => ({
  ok: true,
  status: 200,
  data: {
    data: {
      accounts: [],
      account_count: 0,
      account_limit: null,
      risk_profile: riskProfile,
    },
  },
});

const mountInvestment = async () => {
  const wrapper = mount(Investment, {
    global: { stubs: { MobileChrome: { template: '<div><slot /></div>' } } },
  });
  await flushPromises();

  return wrapper;
};

const riskButton = (wrapper) => wrapper.findAll('button')
  .find((b) => b.text().includes('See how this was worked out'));

describe('the /m risk profile card hands off to the web breakdown', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    store.token = 'live-token';
    store.user = { id: 7, onboarding_completed: true, onboarding_fyn_step: null };
    apiGet.mockImplementation(async (url) => (
      url === '/api/investment'
        ? payloadWithRisk({ risk_level: 'balanced' })
        : { ok: true, status: 200, data: { data: {} } }
    ));
  });

  it('offers a route to the factors behind the level it prints', async () => {
    const wrapper = await mountInvestment();

    expect(wrapper.text()).toContain('Balanced');
    expect(riskButton(wrapper)).toBeTruthy();
  });

  it('asks for the risk_profile destination, the one the server allowlists', async () => {
    issueWebHandoff.mockResolvedValue();
    const wrapper = await mountInvestment();

    await riskButton(wrapper).trigger('click');
    await flushPromises();

    // The literal matters: Swift derives a raw value from the case name unless told
    // otherwise, so the whole allowlist is snake_case and a camelCase destination is
    // a 422. See WebHandoffTest's native-mirror cases.
    expect(issueWebHandoff).toHaveBeenCalledWith('risk_profile');
  });

  it('says so rather than failing silently when the handoff cannot be issued', async () => {
    issueWebHandoff.mockRejectedValue(new Error('handoff_unavailable'));
    const wrapper = await mountInvestment();

    await riskButton(wrapper).trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('We could not open the web app just now.');
  });

  it('renders no risk card at all when the engine has produced no level', async () => {
    apiGet.mockImplementation(async (url) => (
      url === '/api/investment'
        ? payloadWithRisk(null)
        : { ok: true, status: 200, data: { data: {} } }
    ));

    const wrapper = await mountInvestment();

    // No level means nothing to explain — the card and its button both stay away,
    // rather than offering a route to a breakdown of nothing.
    expect(wrapper.text()).not.toContain('Attitude to risk');
    expect(riskButton(wrapper)).toBeFalsy();
  });
});
