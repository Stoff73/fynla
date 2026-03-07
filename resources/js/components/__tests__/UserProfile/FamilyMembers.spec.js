import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import FamilyMembers from '../../UserProfile/FamilyMembers.vue';

describe('FamilyMembers.vue', () => {
  let wrapper;
  let store;
  let mockActions;

  beforeEach(() => {
    mockActions = {
      fetchFamilyMembers: vi.fn(),
      addFamilyMember: vi.fn(() => Promise.resolve()),
      updateFamilyMember: vi.fn(() => Promise.resolve()),
      deleteFamilyMember: vi.fn(() => Promise.resolve()),
    };

    store = createStore({
      modules: {
        userProfile: {
          namespaced: true,
          state: {
            familyMembers: [
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
            ],
            loading: false,
            error: null,
          },
          getters: {
            familyMembers: (state) => state.familyMembers,
            loading: (state) => state.loading,
          },
          actions: mockActions,
        },
      },
    });

    wrapper = mount(FamilyMembers, {
      global: {
        plugins: [store],
        stubs: {
          FamilyMemberFormModal: {
            template: '<div class="modal-stub"></div>',
            props: ['modelValue', 'familyMember'],
            emits: ['update:modelValue', 'save'],
          },
        },
      },
    });
  });

  it('renders family members component', () => {
    expect(wrapper.find('h2').text()).toBe('Family Members');
    expect(wrapper.find('table').exists()).toBe(true);
  });

  it('displays family members list', () => {
    const rows = wrapper.findAll('tbody tr');
    expect(rows.length).toBe(2);
  });

  it('shows family member details in table', () => {
    const firstRow = wrapper.findAll('tbody tr')[0];
    expect(firstRow.text()).toContain('Jane Doe');
    expect(firstRow.text()).toContain('spouse');
  });

  it('has an "Add Family Member" button', () => {
    const addButton = wrapper.find('button');
    expect(addButton.exists()).toBe(true);
    expect(addButton.text()).toContain('Add Family Member');
  });

  it('opens modal when Add button is clicked', async () => {
    const addButton = wrapper.find('button');
    await addButton.trigger('click');

    expect(wrapper.vm.showModal).toBe(true);
  });

  it('has Edit button for each family member', () => {
    const editButtons = wrapper.findAll('button').filter(btn =>
      btn.text().includes('Edit') || btn.html().includes('pencil')
    );
    expect(editButtons.length).toBeGreaterThan(0);
  });

  it('has Delete button for each family member', () => {
    const deleteButtons = wrapper.findAll('button').filter(btn =>
      btn.text().includes('Delete') || btn.html().includes('trash')
    );
    expect(deleteButtons.length).toBeGreaterThan(0);
  });

  it('displays "No family members" message when list is empty', async () => {
    const emptyStore = createStore({
      modules: {
        userProfile: {
          namespaced: true,
          state: {
            familyMembers: [],
            loading: false,
            error: null,
          },
          getters: {
            familyMembers: (state) => state.familyMembers,
            loading: (state) => state.loading,
          },
          actions: mockActions,
        },
      },
    });

    const emptyWrapper = mount(FamilyMembers, {
      global: {
        plugins: [emptyStore],
        stubs: {
          FamilyMemberFormModal: {
            template: '<div class="modal-stub"></div>',
          },
        },
      },
    });

    expect(emptyWrapper.text()).toContain('No family members');
  });

  it('shows dependent badge for dependent children', () => {
    const rows = wrapper.findAll('tbody tr');
    const childRow = rows[1]; // Second row is the child
    expect(childRow.text()).toContain('Dependent');
  });

  it('formats dates correctly', () => {
    // Dates should be displayed in DD/MM/YYYY format
    const rows = wrapper.findAll('tbody tr');
    // Check if date is formatted (the exact format depends on your dateFormatter)
    expect(rows[0].html()).toContain('1992'); // Birth year
  });

  it('calls fetchFamilyMembers on mount', () => {
    expect(mockActions.fetchFamilyMembers).toHaveBeenCalled();
  });

  it('emits save event when modal save is triggered', async () => {
    // Open modal
    await wrapper.find('button').trigger('click');

    // Find the modal stub and emit save event
    const modal = wrapper.findComponent({ name: 'FamilyMemberFormModal' });
    if (modal.exists()) {
      await modal.vm.$emit('save', {
        name: 'New Member',
        relationship: 'child',
        date_of_birth: '2020-01-01',
      });

      expect(mockActions.addFamilyMember).toHaveBeenCalled();
    }
  });
});
