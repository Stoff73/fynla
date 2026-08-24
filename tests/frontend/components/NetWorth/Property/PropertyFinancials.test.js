import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PropertyFinancials from '@/components/NetWorth/Property/PropertyFinancials.vue';

describe('PropertyFinancials mortgage liability', () => {
  it('uses the borrower-based mortgage payment instead of the property ownership share', () => {
    const wrapper = mount(PropertyFinancials, {
      props: {
        property: {
          ownership_type: 'tenants_in_common',
          ownership_percentage: 30,
          mortgage_user_monthly_payment: 1300,
          mortgages: [{
            monthly_payment: 1300,
            ownership_type: 'individual',
            ownership_percentage: 100,
          }],
        },
      },
    });

    expect(wrapper.vm.userMonthlyMortgagePayments).toBe(1300);
  });
});
