import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import PersonalInformation from '../../UserProfile/PersonalInformation.vue';

describe('PersonalInformation.vue', () => {
  let wrapper;
  let store;
  let updatePersonalInfo;
  let updateIncomeOccupation;
  let updateDomicile;

  const personalInfo = {
    id: 1,
    first_name: 'John',
    surname: 'Doe',
    email: 'john@example.com',
    date_of_birth: '1990-01-01',
    gender: 'male',
    marital_status: 'single',
    address: {
      line_1: '123 Main Street',
      line_2: 'Flat 4',
      city: 'London',
      county: 'Greater London',
      postcode: 'SW1A 1AA',
    },
    phone: '07700900123',
  };

  const incomeOccupation = {
    occupation: 'Engineer',
    employer: 'Example Limited',
    industry: 'Technology',
    employment_status: 'employed',
    target_retirement_age: 65,
    annual_employment_income: 75000,
    annual_self_employment_income: 0,
    annual_dividend_income: 1000,
    annual_interest_income: 500,
    annual_trust_income: 0,
  };

  beforeEach(() => {
    updatePersonalInfo = vi.fn(() => Promise.resolve());
    updateIncomeOccupation = vi.fn(() => Promise.resolve());
    updateDomicile = vi.fn(() => Promise.resolve());

    store = createStore({
      modules: {
        userProfile: {
          namespaced: true,
          state: () => ({
            profile: {},
            personalInfo: structuredClone(personalInfo),
            incomeOccupation: structuredClone(incomeOccupation),
            user: {
              country_of_birth: 'United Kingdom',
              uk_arrival_date: null,
              domicile_status: 'uk_domiciled',
            },
          }),
          getters: {
            profile: (state) => state.profile,
            personalInfo: (state) => state.personalInfo,
            incomeOccupation: (state) => state.incomeOccupation,
            user: (state) => state.user,
          },
          actions: {
            updatePersonalInfo,
            updateIncomeOccupation,
            updateDomicile,
          },
        },
        auth: {
          namespaced: true,
          getters: {
            currentUser: () => ({
              first_name: 'John',
              last_name: 'Doe',
              email: 'john@example.com',
            }),
          },
        },
        lifeStage: {
          namespaced: true,
          getters: {
            isFieldVisible: () => () => true,
          },
        },
        preview: {
          namespaced: true,
          getters: {
            isPreviewMode: () => false,
          },
        },
      },
    });

    wrapper = mount(PersonalInformation, {
      global: {
        plugins: [store],
        stubs: {
          CountrySelector: {
            name: 'CountrySelector',
            props: ['modelValue'],
            emits: ['update:modelValue'],
            template: '<div class="country-selector-stub" />',
          },
          OccupationAutocomplete: {
            name: 'OccupationAutocomplete',
            props: ['modelValue'],
            emits: ['update:modelValue'],
            template: '<div class="occupation-autocomplete-stub" />',
          },
        },
      },
    });
  });

  const enterEditMode = async () => {
    const editButton = wrapper.findAll('button').find((button) => button.text() === 'Edit');
    await editButton.trigger('click');
  };

  it('renders the standalone personal-information form in view mode', () => {
    expect(wrapper.get('form').exists()).toBe(true);
    expect(wrapper.text()).toContain('Personal Information');
    expect(wrapper.vm.isEditing).toBe(false);
  });

  it('displays the current personal, address, and occupation data', () => {
    const text = wrapper.text();
    expect(text).toContain('John Doe');
    expect(text).toContain('john@example.com');
    expect(text).toContain('123 Main Street');
    expect(text).toContain('Engineer');
    expect(text).toContain('Example Limited');
  });

  it('initialises the editable form from the current store modules', async () => {
    await enterEditMode();

    expect(wrapper.get('#first_name').element.value).toBe('John');
    expect(wrapper.get('#surname').element.value).toBe('Doe');
    expect(wrapper.get('#email').element.value).toBe('john@example.com');
    expect(wrapper.get('#date_of_birth').element.value).toBe('1990-01-01');
    expect(wrapper.get('#gender').element.value).toBe('male');
    expect(wrapper.get('#address_line_1').element.value).toBe('123 Main Street');
  });

  it('enables edit mode from the Edit action', async () => {
    await enterEditMode();

    expect(wrapper.vm.isEditing).toBe(true);
    expect(wrapper.text()).toContain('Edit Personal Information');
    expect(wrapper.get('#first_name').element.disabled).toBe(false);
  });

  it('submits personal, occupation, and domicile updates together', async () => {
    await enterEditMode();
    await wrapper.get('#first_name').setValue('Jane');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(updatePersonalInfo).toHaveBeenCalledWith(expect.any(Object), expect.objectContaining({
      first_name: 'Jane',
      surname: 'Doe',
      email: 'john@example.com',
    }));
    expect(updateIncomeOccupation).toHaveBeenCalledWith(expect.any(Object), expect.objectContaining({
      occupation: 'Engineer',
      employment_status: 'employed',
      annual_employment_income: 75000,
    }));
    expect(updateDomicile).toHaveBeenCalledWith(expect.any(Object), {
      country_of_birth: 'United Kingdom',
      uk_arrival_date: null,
      domicile_status: 'uk_domiciled',
    });
  });

  it('returns to view mode with a success message after saving', async () => {
    await enterEditMode();
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(wrapper.vm.isEditing).toBe(false);
    expect(wrapper.vm.successMessage).toBe('Personal information updated successfully!');
    expect(wrapper.text()).toContain('Personal information updated successfully!');
  });

  it('keeps edit mode open and displays a failed update', async () => {
    updatePersonalInfo.mockRejectedValueOnce(new Error('Update failed'));
    await enterEditMode();
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(wrapper.vm.isEditing).toBe(true);
    expect(wrapper.vm.errorMessage).toBe('Update failed');
    expect(wrapper.text()).toContain('Update failed');
  });

  it('uses the appropriate browser input types', async () => {
    await enterEditMode();

    expect(wrapper.get('#email').attributes('type')).toBe('email');
    expect(wrapper.get('#date_of_birth').attributes('type')).toBe('date');
    expect(wrapper.get('#phone').attributes('type')).toBe('tel');
  });

  it('constrains date of birth to supported ages', async () => {
    await enterEditMode();

    expect(wrapper.get('#date_of_birth').attributes('max')).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(wrapper.get('#date_of_birth').attributes('min')).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });

  it('cancels edits and restores the store values', async () => {
    await enterEditMode();
    await wrapper.get('#first_name').setValue('Changed');
    const cancelButton = wrapper.findAll('button').find((button) => button.text() === 'Cancel');
    await cancelButton.trigger('click');

    expect(wrapper.vm.isEditing).toBe(false);
    expect(wrapper.vm.form.first_name).toBe('John');
  });

  it('shows UK arrival details for someone born abroad', async () => {
    store.state.userProfile.user = {
      country_of_birth: 'France',
      uk_arrival_date: '2015-06-01',
      domicile_status: 'non_uk_domiciled',
    };
    await wrapper.vm.$nextTick();
    await enterEditMode();

    expect(wrapper.get('#uk_arrival_date').element.value).toBe('2015-06-01');
    expect(wrapper.vm.shouldShowUKArrivalDate).toBe(true);
  });
});
