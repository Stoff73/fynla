import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

// W-0026 — choosing "Life Insurance" replaced Start Date and Policy End Date
// with a term field, so the persona's 2020-01-01 → 2040-01-01 cover had nowhere
// to go; and the three branches of preparePolicyData each assigned the dates
// separately, disagreeing about nulling.
// W-0027 — the beneficiary picker offered only the linked spouse even though the
// children were already loaded, and no joint-life control existed.
vi.mock('@/services/familyMembersService', () => ({
  default: {
    getFamilyMembers: vi.fn(() => Promise.resolve({
      data: {
        family_members: [
          { id: 21, name: 'William Jones', relationship: 'child' },
          { id: 22, name: 'Charlotte Jones', relationship: 'child' },
          { id: 24, name: 'Sarah Jones', relationship: 'spouse' },
        ],
      },
    })),
  },
}));

import { createStore } from 'vuex';
import PolicyFormModal from '@/components/Protection/PolicyFormModal.vue';

function buildStore() {
  return createStore({
    modules: {
      aiFormFill: {
        namespaced: true,
        state: () => ({ pendingFill: null, highlightedField: null, filling: false }),
        actions: { beginFieldSequence: () => {}, cancelFill: () => {} },
      },
      aiChat: {
        namespaced: true,
        mutations: { ADD_MESSAGE: () => {} },
      },
      lifeStage: {
        namespaced: true,
        getters: { formFields: () => () => ({}) },
      },
      preview: {
        namespaced: true,
        getters: { isPreviewMode: () => false },
      },
      userProfile: {
        namespaced: true,
        getters: { spouse: () => ({ id: 17, name: 'Sarah Jones' }) },
      },
    },
  });
}

async function mountModal(props = {}) {
  const wrapper = mount(PolicyFormModal, {
    props,
    global: { plugins: [buildStore()] },
  });
  await flushPromises();

  return wrapper;
}

describe('PolicyFormModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('policy dates', () => {
    it('offers a start date and an end date on a life policy before a life type is chosen', async () => {
      const wrapper = await mountModal();
      await wrapper.setData({ formData: { ...wrapper.vm.formData, policyType: 'life' } });

      const labels = wrapper.findAll('label').map(l => l.text());

      expect(labels).toContain('Start Date');
      expect(labels).toContain('Policy End Date');
    });

    it('offers both dates on every policy type', async () => {
      const wrapper = await mountModal();

      for (const policyType of ['criticalIllness', 'incomeProtection', 'disability', 'sicknessIllness']) {
        await wrapper.setData({ formData: { ...wrapper.vm.formData, policyType } });
        const labels = wrapper.findAll('label').map(l => l.text());

        expect(labels, policyType).toContain('Start Date');
        expect(labels, policyType).toContain('Policy End Date');
      }
    });

    it('sends the end date for a critical illness policy', async () => {
      const wrapper = await mountModal();
      await wrapper.setData({
        formData: {
          ...wrapper.vm.formData,
          policyType: 'criticalIllness',
          start_date: '2020-01-01',
          end_date: '2040-01-01',
        },
      });

      const payload = wrapper.vm.preparePolicyData();

      expect(payload.policy_start_date).toBe('2020-01-01');
      expect(payload.policy_end_date).toBe('2040-01-01');
    });

    it('sends null rather than an empty string when a date is left blank', async () => {
      const wrapper = await mountModal();
      await wrapper.setData({ formData: { ...wrapper.vm.formData, policyType: 'incomeProtection' } });

      const payload = wrapper.vm.preparePolicyData();

      expect(payload.policy_start_date).toBeNull();
      expect(payload.policy_end_date).toBeNull();
    });
  });

  describe('beneficiaries', () => {
    it('offers every family member, not only the linked spouse', async () => {
      const wrapper = await mountModal();

      const labels = wrapper.vm.beneficiaryOptions.map(o => o.label);

      expect(labels).toContain('Sarah Jones (Spouse - Linked Account)');
      expect(labels).toContain('William Jones (child)');
      expect(labels).toContain('Charlotte Jones (child)');
    });

    it('records several beneficiaries with their shares', async () => {
      const wrapper = await mountModal();
      await wrapper.setData({
        formData: { ...wrapper.vm.formData, policyType: 'life', life_policy_type: 'level_term' },
        beneficiaryRows: [
          { selection: 'linked_17', name: 'Sarah Jones', percentage: 50 },
          { selection: 'member_21', name: 'William Jones', percentage: 25 },
          { selection: 'member_22', name: 'Charlotte Jones', percentage: 25 },
        ],
      });

      const payload = wrapper.vm.preparePolicyData();

      expect(payload.beneficiaries).toBe('Sarah Jones: 50%, William Jones: 25%, Charlotte Jones: 25%');
      expect(wrapper.vm.beneficiaryTotal).toBe(100);
    });

    it('reads a stored multi-beneficiary split back into rows when editing', async () => {
      const wrapper = await mountModal({
        isEditing: true,
        policy: {
          policy_type: 'life',
          policy_subtype: 'level_term',
          provider: 'Vitality',
          sum_assured: 500000,
          beneficiaries: 'Sarah Jones: 50%, William Jones: 25%, Charlotte Jones: 25%',
          joint_life: true,
        },
      });

      expect(wrapper.vm.beneficiaryRows).toHaveLength(3);
      expect(wrapper.vm.beneficiaryRows[1]).toMatchObject({ name: 'William Jones', percentage: 25 });
      expect(wrapper.vm.beneficiaryRows[0].selection).toBe('linked_17');
      expect(wrapper.vm.formData.joint_life).toBe(true);
    });

    it('keeps free text that was never a named share', async () => {
      const wrapper = await mountModal({
        isEditing: true,
        policy: {
          policy_type: 'life',
          beneficiaries: 'Children split equally',
        },
      });

      expect(wrapper.vm.beneficiaryRows[0]).toMatchObject({ name: 'Children split equally', percentage: null });
      expect(wrapper.vm.preparePolicyData().beneficiaries).toBe('Children split equally');
    });
  });

  describe('joint life', () => {
    it('offers a joint life control on life policies', async () => {
      const wrapper = await mountModal();
      await wrapper.setData({ formData: { ...wrapper.vm.formData, policyType: 'life' } });

      expect(wrapper.find('#joint_life').exists()).toBe(true);
    });

    it('sends the joint life flag', async () => {
      const wrapper = await mountModal();
      await wrapper.setData({
        formData: { ...wrapper.vm.formData, policyType: 'life', life_policy_type: 'level_term', joint_life: true },
      });

      expect(wrapper.vm.preparePolicyData().joint_life).toBe(true);
    });
  });
});
