import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import PersonalInfoStep from '@/components/Onboarding/steps/PersonalInfoStep.vue';
import FamilyInfoStep from '@/components/Onboarding/steps/FamilyInfoStep.vue';

vi.mock('@/services/familyMembersService', () => ({
  default: { getFamilyMembers: vi.fn(() => Promise.resolve({ data: [] })) },
}));

// The step wrapper renders its title so the copy can be asserted through it.
const OnboardingStepStub = { props: ['title', 'description'], template: '<section><h1>{{ title }}</h1><p>{{ description }}</p><slot /></section>' };

const store = createStore({
  modules: {
    lifeStage: { namespaced: true, getters: { isFieldVisible: () => () => true } },
    auth: { namespaced: true, getters: { currentUser: () => ({ marital_status: null }) } },
  },
});

/**
 * MB-35. Wizard copy is British and the marital status list matches the
 * column and the Fyn flow: "Dependants", and civil partnership offered.
 */
describe('Onboarding wizard copy and options (MB-35)', () => {
  it('offers civil partnership as a marital status', async () => {
    const wrapper = mount(PersonalInfoStep, { global: { plugins: [store], stubs: { OnboardingStep: OnboardingStepStub } } });
    await flushPromises();
    const options = wrapper.findAll('#marital_status option').map((o) => o.attributes('value'));
    expect(options).toContain('civil_partnership');
    expect(wrapper.find('#marital_status option[value="civil_partnership"]').text()).toBe('Civil partnership');
  });

  it('spells dependants the British way on the family step', async () => {
    const wrapper = mount(FamilyInfoStep, { global: { plugins: [store], stubs: { OnboardingStep: OnboardingStepStub, FamilyMemberFormModal: true, SpouseSuccessModal: true } } });
    await flushPromises();
    expect(wrapper.text()).toContain('Family & Dependants');
    expect(wrapper.text()).not.toMatch(/dependents/i);
  });
});
