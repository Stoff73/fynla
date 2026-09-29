import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

// Regression coverage for the adjacent D3 race pattern flagged (not fixed) in
// the CSJ report (2026-07-21): MobileChrome's own openFyn()/verifyAnswer()
// had the same unawaited-open -> immediate-send race as
// Dashboard.vue's openRecChat/openFynForCapture (see Dashboard.spec.js). Fixed
// the same way: openFyn() is now async and returns the open+init promise chain
// (mirrors Dashboard.vue's openFyn), and initFyn()'s onboarding branch now
// `return`s resumeOnboardingInDock() instead of firing it unawaited, so a
// caller that awaits openFyn() genuinely waits for the resume stream to settle
// before sending a follow-up message. The campaign verify flow's sequencing
// (open, then resume, then send) is unchanged — only the race is removed.
vi.mock('../../api.js', () => ({
  apiGet: vi.fn(() => Promise.resolve({ ok: true, status: 200, data: { data: {} } })),
  apiPost: vi.fn(() => Promise.resolve({ ok: true, status: 200, data: {} })),
  apiStream: vi.fn(() => Promise.resolve({ ok: true, status: 200, text: '' })),
}));

vi.mock('../../navigation/webHandoff.js', () => ({
  issueWebHandoff: vi.fn(),
}));

import { apiGet, apiPost, apiStream } from '../../api.js';
import { issueWebHandoff } from '../../navigation/webHandoff.js';
import MobileChrome from '../MobileChrome.vue';
import Dashboard from '../../views/Dashboard.vue';
import { store } from '../../store.js';

function mountChrome(props = {}) {
  return mount(MobileChrome, {
    props,
    global: {
      mocks: {
        $route: { path: '/income', query: {} },
        $router: { push: vi.fn() },
      },
    },
  });
}

describe('MobileChrome.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    apiStream.mockResolvedValue({ ok: true, status: 200, text: '' });
    issueWebHandoff.mockResolvedValue();
    store.subscriptionStatus = { tier: 'free', payment_enabled: false };
  });

  describe('shared primary navigation', () => {
    it('uses the same frozen navigation groups as the dashboard', () => {
      const chromeSections = MobileChrome.computed.navSections.call({});
      const dashboardSections = Dashboard.computed.navSections.call({});
      const labels = chromeSections.flatMap((section) => section.links.map((link) => link.label));

      expect(chromeSections).toBe(dashboardSections);
      expect(Object.isFrozen(chromeSections)).toBe(true);
      expect(labels).toEqual(expect.arrayContaining([
        'Dashboard',
        'Bank Accounts',
        'Achievements',
        'Personal Information',
        'Subscription',
        'Settings',
      ]));
    });
  });

  it('opens Admin through a single-use handoff without copying the bearer', async () => {
    store.token = 'live-token';
    store.user = { id: 1, is_admin: true, onboarding_completed: true };
    const storageSpy = vi.spyOn(window.sessionStorage.__proto__, 'setItem');
    const wrapper = mountChrome();

    await wrapper.vm.gotoAdmin();

    expect(issueWebHandoff).toHaveBeenCalledWith('admin');
    expect(storageSpy).not.toHaveBeenCalled();
    storageSpy.mockRestore();
  });

  describe('trusted contextual launch', () => {
    const request = {
      action: 'edit',
      resource_type: 'savings',
      current_destination: { screen: 'savings', params: {}, fallback: 'dashboard' },
      origin: { kind: 'surface_action', recommendation_id: null },
    };

    it('shows the server-persisted opening without sending client-authored prose or resuming onboarding', async () => {
      store.token = 'live-token';
      store.user = { id: 1, onboarding_completed: false, onboarding_fyn_step: 'campaign_verify_navigate' };
      const wrapper = mountChrome({ contextualRequest: request });
      await flushPromises();

      const createSpy = vi.spyOn(wrapper.vm, 'createContextualConversation').mockImplementation(async () => {
        wrapper.vm.conversationId = 101;
        wrapper.vm.messages = [{ role: 'fyn', text: 'Trusted opening from Laravel.' }];
        return 101;
      });
      const resumeSpy = vi.spyOn(wrapper.vm, 'resumeOnboardingInDock');
      const sendSpy = vi.spyOn(wrapper.vm, 'send').mockImplementation(() => {});

      await wrapper.vm.openContextualFyn(request);

      expect(createSpy).toHaveBeenCalledWith(request);
      expect(wrapper.vm.fynOpen).toBe(true);
      expect(wrapper.vm.messages[0].text).toBe('Trusted opening from Laravel.');
      expect(resumeSpy).not.toHaveBeenCalled();
      expect(sendSpy).not.toHaveBeenCalled();
    });

    it('issues a fresh creation call on every Edit tap', async () => {
      store.user = { id: 1, onboarding_completed: true, onboarding_fyn_step: null };
      const wrapper = mountChrome({ contextualRequest: request });
      const createSpy = vi.spyOn(wrapper.vm, 'createContextualConversation')
        .mockResolvedValueOnce(101)
        .mockResolvedValueOnce(102);

      await wrapper.vm.openContextualFyn(request);
      wrapper.vm.fynOpen = false;
      await wrapper.vm.openContextualFyn(request);

      expect(createSpy).toHaveBeenCalledTimes(2);
    });

    it('keeps the page visible and exposes retry copy when creation fails', async () => {
      store.user = { id: 1, onboarding_completed: true, onboarding_fyn_step: null };
      const wrapper = mountChrome({ contextualRequest: request });
      vi.spyOn(wrapper.vm, 'createContextualConversation').mockResolvedValue(null);

      await wrapper.get('.md-edit-details').trigger('click');
      await flushPromises();

      expect(wrapper.vm.fynOpen).toBe(false);
      expect(wrapper.text()).toContain('Fyn could not start that conversation. Please try again.');
      expect(wrapper.get('.md-edit-details').exists()).toBe(true);
    });

    it.each([
      ['savings', 'Add bank account'],
      ['investment', 'Add investment account'],
      ['retirement', 'Add pension'],
      ['protection', 'Add policy'],
      ['goals', 'Add goal'],
    ])('labels an overview %s request as %s', (resourceType, expectedLabel) => {
      const wrapper = mountChrome({
        contextualRequest: {
          action: 'add',
          resource_type: resourceType,
          current_destination: { screen: resourceType, params: {}, fallback: 'dashboard' },
          origin: { kind: 'surface_action', recommendation_id: null },
        },
      });

      expect(wrapper.get('.md-edit-details').text()).toBe(expectedLabel);
    });
  });

  describe('verifyAnswer() awaits openFyn() before resuming + sending, preserving verify sequencing (adjacent D3)', () => {
    it('waits for openFyn(), THEN resumes the onboarding conversation, THEN sends the answer', async () => {
      store.token = 'live-token';
      store.user = {
        id: 1, onboarding_completed: false, onboarding_fyn_step: 'campaign_verify_navigate', active_campaign: null,
      };
      const wrapper = mountChrome();
      await flushPromises();

      let resolveOpen;
      const openPromise = new Promise((resolve) => { resolveOpen = resolve; });
      const openSpy = vi.spyOn(wrapper.vm, 'openFyn').mockReturnValue(openPromise);

      let resolveResume;
      const resumePromise = new Promise((resolve) => { resolveResume = resolve; });
      const resumeSpy = vi.spyOn(wrapper.vm, 'resumeOnboardingInDock').mockReturnValue(resumePromise);

      const sendSpy = vi.spyOn(wrapper.vm, 'send').mockImplementation(() => {});

      const tapPromise = wrapper.vm.verifyAnswer("Yes, that's right");
      await Promise.resolve();
      await Promise.resolve();
      expect(openSpy).toHaveBeenCalled();
      expect(resumeSpy).not.toHaveBeenCalled(); // resume waits for openFyn() to settle first
      expect(sendSpy).not.toHaveBeenCalled();

      resolveOpen();
      await Promise.resolve();
      await Promise.resolve();
      expect(resumeSpy).toHaveBeenCalled(); // resume kicks off once openFyn() has settled
      expect(sendSpy).not.toHaveBeenCalled(); // send still waits on the resume

      resolveResume();
      await tapPromise;
      expect(sendSpy).toHaveBeenCalledWith("Yes, that's right");
    });

    it('skips the resume call once a conversation is already resumed, but still waits for openFyn()', async () => {
      store.token = 'live-token';
      store.user = {
        id: 1, onboarding_completed: false, onboarding_fyn_step: 'campaign_verify_navigate', active_campaign: null,
      };
      const wrapper = mountChrome();
      await flushPromises();
      wrapper.vm.conversationId = 'conv-1'; // already resumed on a prior open

      let resolveOpen;
      const openPromise = new Promise((resolve) => { resolveOpen = resolve; });
      vi.spyOn(wrapper.vm, 'openFyn').mockReturnValue(openPromise);
      const resumeSpy = vi.spyOn(wrapper.vm, 'resumeOnboardingInDock');
      const sendSpy = vi.spyOn(wrapper.vm, 'send').mockImplementation(() => {});

      const tapPromise = wrapper.vm.verifyAnswer('No, change something');
      await Promise.resolve();
      await Promise.resolve();
      expect(sendSpy).not.toHaveBeenCalled();

      resolveOpen();
      await tapPromise;
      expect(resumeSpy).not.toHaveBeenCalled();
      expect(sendSpy).toHaveBeenCalledWith('No, change something');
    });
  });

  describe('openFyn() genuinely waits for the onboarding resume stream (initFyn returns resumeOnboardingInDock)', () => {
    it('does not resolve until the resume stream settles, and sending clears when it does', async () => {
      store.token = 'live-token';
      store.user = {
        id: 1, first_name: 'Jo', onboarding_completed: false, onboarding_fyn_step: 'campaign_verify_navigate', active_campaign: null,
      };

      let resolveStream;
      apiStream.mockImplementation(() => new Promise((resolve) => { resolveStream = resolve; }));

      const wrapper = mountChrome();
      await flushPromises();

      let openResolved = false;
      const openPromise = wrapper.vm.openFyn().then(() => { openResolved = true; });
      // openFyn() awaits $nextTick() before calling initFyn(), which itself
      // awaits loadUser() before starting the resume — flush a few ticks so
      // resumeOnboardingInDock has actually set `sending` by the time we check.
      await flushPromises();
      expect(openResolved).toBe(false);
      expect(wrapper.vm.sending).toBe(true); // resumeOnboardingInDock's own flag, chained through initFyn

      resolveStream({ ok: true, status: 200, text: '' });
      await openPromise;
      expect(openResolved).toBe(true);
      expect(wrapper.vm.sending).toBe(false);
    });
  });
});

// Laura, 2026-09-18 (production conversation 897): the on-page Continue/Edit
// pills sent "Yes, that's right" and "No, change something" from the
// investments FORM step, where neither is an answer. They are verify answers,
// so they show only on a verify step; every other step gets one way back in.
describe('on-page onboarding actions by step', () => {
  it('shows Continue and Edit on a verify step', async () => {
    store.token = 'live-token';
    store.user = { id: 1, onboarding_completed: false, onboarding_fyn_step: 'campaign_verify_navigate', active_campaign: null };
    const wrapper = mountChrome();
    await flushPromises();
    const labels = wrapper.findAll('.md-verify-actions button').map((b) => b.text());
    expect(labels).toEqual(['Continue', 'Edit']);
  });

  it('shows only Continue with Fyn on a form step', async () => {
    store.token = 'live-token';
    store.user = { id: 1, onboarding_completed: false, onboarding_fyn_step: 'campaign_investment_accounts', active_campaign: null };
    const wrapper = mountChrome();
    await flushPromises();
    const openSpy = vi.spyOn(wrapper.vm, 'openFyn').mockResolvedValue();
    const sendSpy = vi.spyOn(wrapper.vm, 'send').mockImplementation(() => {});
    const buttons = wrapper.findAll('.md-verify-actions button');
    expect(buttons.map((b) => b.text())).toEqual(['Continue with Fyn']);
    await buttons[0].trigger('click');
    expect(openSpy).toHaveBeenCalled();
    expect(sendSpy).not.toHaveBeenCalled();
  });
});

// M6 (live fynla.org /m, 2026-09-29): the docked Fyn bar on every module screen
// resumes the conversation already under way this session, instead of
// greeting afresh; a contextual launch still opens its own conversation.
describe('MobileChrome.vue — M6: resuming the current conversation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    apiStream.mockResolvedValue({ ok: true, status: 200, text: '' });
    store.token = 'live-token';
    store.subscriptionStatus = { tier: 'free', payment_enabled: false };
    store.user = { id: 7, first_name: 'Jo', onboarding_completed: true, onboarding_fyn_step: null, onboarding_fyn_needs_start: false, active_campaign: null };
    store.setFynConversation(null);
  });

  it('opening the dock resumes the conversation this session was in', async () => {
    store.setFynConversation('conv-9');
    apiGet.mockImplementation((path) => Promise.resolve(path === '/api/ai-chat/conversations/conv-9'
      ? { ok: true, status: 200, data: { data: { messages: [
        { role: 'user', content: 'How much can I put in my pension?', metadata: {} },
        { role: 'assistant', content: 'Here is how the annual allowance works.', metadata: {} },
      ] } } }
      : { ok: true, status: 200, data: { data: {} } }));
    const wrapper = mountChrome();
    await flushPromises();

    await wrapper.vm.openFyn();

    expect(wrapper.vm.conversationId).toBe('conv-9');
    expect(wrapper.vm.messages.map((m) => m.text)).toEqual([
      'How much can I put in my pension?',
      'Here is how the annual allowance works.',
    ]);
    apiGet.mockImplementation(() => Promise.resolve({ ok: true, status: 200, data: { data: {} } }));
  });

  it('a contextual launch opens its own conversation, not the stored one', async () => {
    store.setFynConversation('conv-9');
    apiPost.mockResolvedValueOnce({
      ok: true,
      status: 200,
      data: { data: { conversation: { id: 'conv-ctx' }, opening_message: { role: 'assistant', content: 'Which account would you like to change?' } } },
    });
    const request = {
      action: 'edit',
      resource_type: 'savings',
      current_destination: { screen: 'savings', params: {}, fallback: 'dashboard' },
      origin: { kind: 'surface_action', recommendation_id: null },
    };
    const wrapper = mountChrome({ contextualRequest: request });
    await flushPromises();

    await wrapper.vm.openContextualFyn(request);
    await flushPromises();

    expect(wrapper.vm.conversationId).toBe('conv-ctx');
    expect(apiGet).not.toHaveBeenCalledWith('/api/ai-chat/conversations/conv-9', expect.anything());
    expect(wrapper.vm.messages[0].text).toBe('Which account would you like to change?');
    expect(store.currentFynConversationId()).toBe('conv-ctx');
  });

  it('signing out clears the conversation so the next person on the device cannot resume it', async () => {
    store.setFynConversation('conv-9');
    const wrapper = mountChrome();

    await wrapper.vm.signOut();

    expect(store.currentFynConversationId()).toBeNull();
    expect(window.sessionStorage.getItem('m_fyn_conversation')).toBeNull();
  });
});
