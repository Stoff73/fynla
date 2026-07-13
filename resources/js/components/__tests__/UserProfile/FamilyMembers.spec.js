import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import familyMembersService from '@/services/familyMembersService';
import FamilyMembers from '../../UserProfile/FamilyMembers.vue';

vi.mock('@/services/familyMembersService', () => ({
  default: {
    getFamilyMembers: vi.fn(),
    createFamilyMember: vi.fn(),
    updateFamilyMember: vi.fn(),
    deleteFamilyMember: vi.fn(),
  },
}));

describe('FamilyMembers.vue', () => {
  let wrapper;
  let store;

  const familyMembers = [
    {
      id: 1,
      name: 'Jane Doe',
      relationship: 'spouse',
      date_of_birth: '1992-05-15',
      gender: 'female',
      is_dependent: false,
    },
    {
      id: 2,
      name: 'Johnny Doe',
      relationship: 'child',
      date_of_birth: '2015-08-20',
      gender: 'male',
      is_dependent: true,
    },
  ];

  const createTestStore = (members = familyMembers) => createStore({
    state: {
      aiFormFill: { pendingFill: null },
    },
    modules: {
      userProfile: {
        namespaced: true,
        state: () => ({ familyMembers: structuredClone(members) }),
        actions: {
          fetchProfile: vi.fn(() => Promise.resolve()),
          updatePersonalInfo: vi.fn(() => Promise.resolve()),
        },
      },
      auth: {
        namespaced: true,
        state: () => ({
          user: { marital_status: 'married', charitable_bequest: false },
        }),
        getters: {
          user: (state) => state.user,
        },
        actions: {
          fetchUser: vi.fn(() => Promise.resolve()),
        },
      },
      preview: {
        namespaced: true,
        getters: {
          isPreviewMode: () => false,
        },
      },
      spousePermission: {
        namespaced: true,
        actions: {
          fetchPermissionStatus: vi.fn(() => Promise.resolve()),
        },
      },
    },
  });

  const mountComponent = (testStore = store) => mount(FamilyMembers, {
    global: {
      plugins: [testStore],
      directives: {
        previewDisabled: {},
      },
      stubs: {
        FamilyMemberFormModal: {
          name: 'FamilyMemberFormModal',
          props: ['member'],
          emits: ['save', 'close'],
          template: '<div class="family-member-modal-stub" />',
        },
        ConfirmDialog: {
          name: 'ConfirmDialog',
          props: ['show', 'title', 'message'],
          emits: ['confirm', 'cancel'],
          template: '<div class="confirm-dialog-stub" />',
        },
        SpouseSuccessModal: {
          name: 'SpouseSuccessModal',
          props: ['show'],
          emits: ['close'],
          template: '<div class="spouse-success-stub" />',
        },
      },
    },
  });

  beforeEach(async () => {
    vi.clearAllMocks();
    familyMembersService.getFamilyMembers.mockResolvedValue({ data: { family_members: [] } });
    familyMembersService.createFamilyMember.mockResolvedValue({ data: {} });
    familyMembersService.updateFamilyMember.mockResolvedValue({ data: {} });
    familyMembersService.deleteFamilyMember.mockResolvedValue({ data: {} });

    store = createTestStore();
    wrapper = mountComponent();
    await flushPromises();
  });

  it('renders the family-members section', () => {
    expect(wrapper.findAll('h3').some((heading) => heading.text() === 'Family Members')).toBe(true);
    expect(wrapper.text()).toContain('Manage your family members and dependents');
  });

  it('displays the family members supplied by the profile store', () => {
    expect(wrapper.text()).toContain('Jane Doe');
    expect(wrapper.text()).toContain('Johnny Doe');
  });

  it('shows relationship and personal details', () => {
    const text = wrapper.text();
    expect(text).toContain('spouse');
    expect(text).toContain('child');
    expect(text).toContain('female');
    expect(text).toContain('male');
  });

  it('opens the add modal from the section action', async () => {
    const addButton = wrapper.findAll('button').find((button) => button.text() === 'Add');
    await addButton.trigger('click');

    expect(wrapper.vm.showModal).toBe(true);
    expect(wrapper.findComponent({ name: 'FamilyMemberFormModal' }).props('member')).toBeNull();
  });

  it('offers edit and delete actions only for editable non-spouse members', () => {
    const editButtons = wrapper.findAll('button').filter((button) => button.text() === 'Edit');
    const deleteButtons = wrapper.findAll('button').filter((button) => button.text() === 'Delete');

    expect(editButtons).toHaveLength(1);
    expect(deleteButtons).toHaveLength(1);
  });

  it('opens the child in edit mode', async () => {
    const editButton = wrapper.findAll('button').find((button) => button.text() === 'Edit');
    await editButton.trigger('click');

    expect(wrapper.vm.selectedMember).toEqual(familyMembers[1]);
    expect(wrapper.findComponent({ name: 'FamilyMemberFormModal' }).props('member')).toEqual(familyMembers[1]);
  });

  it('marks dependent family members', () => {
    expect(wrapper.text()).toContain('Dependent');
  });

  it('formats dates in day-month-year order', () => {
    expect(wrapper.text()).toContain('15/05/1992');
    expect(wrapper.text()).toContain('20/08/2015');
  });

  it('uses existing profile family data without an unnecessary request', () => {
    expect(familyMembersService.getFamilyMembers).not.toHaveBeenCalled();
  });

  it('loads family members from the service when the profile store is empty', async () => {
    familyMembersService.getFamilyMembers.mockResolvedValue({
      data: { family_members: [familyMembers[1]] },
    });
    const emptyWrapper = mountComponent(createTestStore([]));
    await flushPromises();

    expect(familyMembersService.getFamilyMembers).toHaveBeenCalledTimes(1);
    expect(emptyWrapper.text()).toContain('Johnny Doe');
  });

  it('shows the empty state when no family members exist', async () => {
    const emptyWrapper = mountComponent(createTestStore([]));
    await flushPromises();

    expect(emptyWrapper.text()).toContain('No family members added yet');
    expect(emptyWrapper.text()).toContain('Add Your First Family Member');
  });

  it('creates a family member from the modal save event', async () => {
    const formData = {
      name: 'New Child',
      relationship: 'child',
      date_of_birth: '2020-01-01',
    };
    const addButton = wrapper.findAll('button').find((button) => button.text() === 'Add');
    await addButton.trigger('click');
    await wrapper.findComponent({ name: 'FamilyMemberFormModal' }).vm.$emit('save', formData);
    await flushPromises();

    expect(familyMembersService.createFamilyMember).toHaveBeenCalledWith(formData);
    expect(wrapper.vm.showModal).toBe(false);
  });

  it('updates an existing family member from the modal save event', async () => {
    const editButton = wrapper.findAll('button').find((button) => button.text() === 'Edit');
    await editButton.trigger('click');
    await wrapper.findComponent({ name: 'FamilyMemberFormModal' }).vm.$emit('save', {
      ...familyMembers[1],
      name: 'Johnny Smith',
    });
    await flushPromises();

    expect(familyMembersService.updateFamilyMember).toHaveBeenCalledWith(2, expect.objectContaining({
      name: 'Johnny Smith',
    }));
  });

  it('deletes the selected editable family member', async () => {
    wrapper.vm.confirmDelete(familyMembers[1]);
    await wrapper.vm.handleDelete();

    expect(familyMembersService.deleteFamilyMember).toHaveBeenCalledWith(2);
    expect(wrapper.vm.showDeleteConfirm).toBe(false);
  });
});
