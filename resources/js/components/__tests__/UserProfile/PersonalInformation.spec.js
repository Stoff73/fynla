import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import PersonalInformation from '../../UserProfile/PersonalInformation.vue';

describe('PersonalInformation.vue', () => {
  let wrapper;
  let store;
  let mockActions;

  beforeEach(() => {
    mockActions = {
      fetchProfile: vi.fn(),
      updatePersonalInfo: vi.fn(() => Promise.resolve()),
    };

    store = createStore({
      modules: {
        userProfile: {
          namespaced: true,
          state: {
            personalInfo: {
              id: 1,
              name: 'John Doe',
              email: 'john@example.com',
              date_of_birth: '1990-01-01',
              gender: 'male',
              marital_status: 'single',
              address: {
                line_1: '123 Main St',
                line_2: 'Apt 4',
                city: 'London',
                county: 'Greater London',
                postcode: 'SW1A 1AA',
              },
              phone: '07700900123',
            },
            loading: false,
            error: null,
          },
          getters: {
            personalInfo: (state) => state.personalInfo,
            loading: (state) => state.loading,
          },
          actions: mockActions,
        },
      },
    });

    wrapper = mount(PersonalInformation, {
      global: {
        plugins: [store],
        stubs: {
          teleport: true,
        },
      },
    });
  });

  it('renders personal information form', () => {
    expect(wrapper.find('h2').text()).toBe('Personal Information');
    expect(wrapper.find('form').exists()).toBe(true);
  });

  it('displays user data in form fields', () => {
    expect(wrapper.find('#name').element.value).toBe('John Doe');
    expect(wrapper.find('#email').element.value).toBe('john@example.com');
    expect(wrapper.find('#date_of_birth').element.value).toBe('1990-01-01');
    expect(wrapper.find('#gender').element.value).toBe('male');
  });

  it('form fields are disabled by default', () => {
    expect(wrapper.find('#name').element.disabled).toBe(true);
    expect(wrapper.find('#email').element.disabled).toBe(true);
    expect(wrapper.find('#gender').element.disabled).toBe(true);
  });

  it('enables form fields when Edit button is clicked', async () => {
    const editButton = wrapper.find('button[type="button"]');
    await editButton.trigger('click');

    expect(wrapper.find('#name').element.disabled).toBe(false);
    expect(wrapper.find('#email').element.disabled).toBe(false);
    expect(wrapper.find('#gender').element.disabled).toBe(false);
  });

  it('calls updatePersonalInfo action on form submission', async () => {
    // Enable editing
    await wrapper.find('button[type="button"]').trigger('click');

    // Change a value
    await wrapper.find('#name').setValue('Jane Doe');

    // Submit form
    await wrapper.find('form').trigger('submit');

    expect(mockActions.updatePersonalInfo).toHaveBeenCalled();
  });

  it('displays success message after successful update', async () => {
    await wrapper.find('button[type="button"]').trigger('click');
    await wrapper.find('form').trigger('submit');

    await wrapper.vm.$nextTick();

    // Check if success message appears (component should set successMessage)
    // Note: This test assumes the component sets a successMessage data property
    expect(wrapper.vm.successMessage).toBeTruthy();
  });

  it('displays error message when update fails', async () => {
    mockActions.updatePersonalInfo = vi.fn(() => Promise.reject(new Error('Update failed')));

    wrapper = mount(PersonalInformation, {
      global: {
        plugins: [store],
      },
    });

    await wrapper.find('button[type="button"]').trigger('click');
    await wrapper.find('form').trigger('submit');

    await wrapper.vm.$nextTick();

    expect(wrapper.vm.errorMessage).toBeTruthy();
  });

  it('validates required fields', () => {
    const nameInput = wrapper.find('#name');
    const emailInput = wrapper.find('#email');

    expect(nameInput.attributes('required')).toBeDefined();
    expect(emailInput.attributes('required')).toBeDefined();
  });

  it('has correct input types', () => {
    expect(wrapper.find('#email').attributes('type')).toBe('email');
    expect(wrapper.find('#date_of_birth').attributes('type')).toBe('date');
    expect(wrapper.find('#phone').attributes('type')).toBe('tel');
  });
});
