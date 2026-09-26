import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import PersonalInformation from '@/components/UserProfile/PersonalInformation.vue';

/*
 * 2026-09-26, walked on csjones: a user never asked where they were born was
 * shown "Country of Birth: United Kingdom", and any save of personal details
 * wrote it back, recording them as UK-born and so (from their date of birth) a
 * long-term UK resident for Inheritance Tax. The page must show what the server
 * says, and save domicile only once the user has given a country.
 */
const makeStore = (user, domicileInfo) => {
  const dispatch = vi.fn(() => Promise.resolve());
  const store = createStore({
    getters: {
      'userProfile/profile': () => ({}),
      'userProfile/personalInfo': () => ({ first_name: 'Idef', surname: 'Walk', email: 'i@example.com' }),
      'userProfile/incomeOccupation': () => ({ employment_status: 'employed' }),
      'userProfile/user': () => user,
      'userProfile/domicileInfo': () => domicileInfo,
      'lifeStage/isFieldVisible': () => () => true,
      'auth/currentUser': () => user,
      'preview/isPreviewMode': () => false,
    },
  });
  store.dispatch = dispatch;
  return { store, dispatch };
};

const mountWith = (user, domicileInfo) => {
  const { store, dispatch } = makeStore(user, domicileInfo);
  const wrapper = mount(PersonalInformation, {
    global: { plugins: [store], stubs: { CountrySelector: true, OccupationAutocomplete: true } },
  });
  return { wrapper, dispatch };
};

describe('PersonalInformation — country of birth and Inheritance Tax residence', () => {
  const unknown = { is_long_term_uk_resident: null, explanation: 'Tell us where you were born and when you came to live in the UK, and we can work out whether you are a long-term UK resident for Inheritance Tax.' };

  it('does not invent a country of birth the user never gave', () => {
    const { wrapper } = mountWith({ id: 1, country_of_birth: null, domicile_status: null }, unknown);

    expect(wrapper.text()).not.toContain('United Kingdom');
    expect(wrapper.text()).toContain('Not known yet');
    expect(wrapper.text()).toContain(unknown.explanation);
  });

  it('does not save domicile when no country of birth was given', async () => {
    const { wrapper, dispatch } = mountWith({ id: 1, country_of_birth: null, domicile_status: null }, unknown);

    await wrapper.vm.handleSubmit();

    expect(dispatch.mock.calls.map((c) => c[0])).not.toContain('userProfile/updateDomicile');
  });

  it('saves domicile once the user has given a country', async () => {
    const info = { is_long_term_uk_resident: true, explanation: 'You are a long-term UK resident for Inheritance Tax.' };
    const { wrapper, dispatch } = mountWith({ id: 1, country_of_birth: 'Australia', uk_arrival_date: '2015-06-01', domicile_status: 'non_uk_domiciled' }, info);

    await wrapper.vm.handleSubmit();

    const call = dispatch.mock.calls.find((c) => c[0] === 'userProfile/updateDomicile');
    expect(call[1]).toMatchObject({ country_of_birth: 'Australia', uk_arrival_date: '2015-06-01', domicile_status: 'non_uk_domiciled' });
    expect(wrapper.text()).toContain('Long-term UK resident');
  });
});
