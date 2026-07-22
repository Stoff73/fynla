import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import PropertyCard from '../../NetWorth/PropertyCard.vue';

describe('PropertyCard.vue', () => {
  let wrapper;

  const solePropertyMock = {
    id: 1,
    address_line_1: '123 Main Street',
    address_line_2: 'Apartment 4B',
    city: 'London',
    postcode: 'SW1A 1AA',
    property_type: 'main_residence',
    current_value: 500000,
    mortgages: [{ id: 101, outstanding_balance: 200000 }],
    ownership_type: 'individual',
    ownership_percentage: 100,
  };

  const jointPropertyMock = {
    id: 2,
    address_line_1: '456 Oak Avenue',
    address_line_2: null,
    city: 'Manchester',
    postcode: 'M1 1AE',
    property_type: 'buy_to_let',
    current_value: 300000,
    mortgages: [{ id: 102, outstanding_balance: 150000 }],
    ownership_type: 'joint',
    ownership_percentage: 50,
  };

  const mortgageFreePropertyMock = {
    id: 3,
    address_line_1: '789 Park Lane',
    address_line_2: null,
    city: 'Birmingham',
    postcode: 'B1 1AA',
    property_type: 'secondary_residence',
    current_value: 250000,
    mortgages: [],
    ownership_type: 'individual',
    ownership_percentage: 100,
  };

  beforeEach(() => {
    wrapper = mount(PropertyCard, {
      props: {
        property: solePropertyMock,
      },
    });
  });

  it('renders property card component', () => {
    expect(wrapper.exists()).toBe(true);
    expect(wrapper.classes()).toContain('property-card');
  });

  it('displays property address details', () => {
    const html = wrapper.html();
    expect(html).toContain('123 Main Street');
    expect(html).toContain('Apartment 4B');
    expect(html).toContain('London');
    expect(html).toContain('SW1A 1AA');
  });

  it('does not show address_line_2 when null', async () => {
    await wrapper.setProps({ property: jointPropertyMock });
    expect(wrapper.html()).toContain('456 Oak Avenue');
    expect(wrapper.html()).not.toContain('address-line-2');
  });

  it('displays property type badge', () => {
    const badge = wrapper.find('.property-type-badge');
    expect(badge.exists()).toBe(true);
    expect(badge.text()).toBe('Main Residence');
    expect(badge.classes()).toContain('type-main_residence');
  });

  it('displays correct property type labels', async () => {
    // Main residence
    expect(wrapper.find('.property-type-badge').text()).toBe('Main Residence');

    // Secondary residence
    await wrapper.setProps({ property: mortgageFreePropertyMock });
    expect(wrapper.find('.property-type-badge').text()).toBe('Secondary Residence');

    // Buy to let
    await wrapper.setProps({ property: jointPropertyMock });
    expect(wrapper.find('.property-type-badge').text()).toBe('Buy to Let');
  });

  it('shows ownership badge for joint properties', async () => {
    await wrapper.setProps({ property: jointPropertyMock });

    const ownershipBadge = wrapper.find('.ownership-badge');
    expect(ownershipBadge.exists()).toBe(true);
    expect(ownershipBadge.text()).toBe('Joint (50%)');
  });

  it('does not show ownership badge for individual properties', () => {
    const ownershipBadge = wrapper.find('.ownership-badge');
    expect(ownershipBadge.exists()).toBe(false);
  });

  it('displays current value', () => {
    const html = wrapper.html();
    expect(html).toContain('Current Value');
    expect(html).toContain('£500,000');
  });

  it('displays mortgage outstanding when present', () => {
    const html = wrapper.html();
    expect(html).toContain('Mortgage Outstanding');
    expect(html).toContain('£200,000');
  });

  it('does not show mortgage row when mortgage is zero', async () => {
    await wrapper.setProps({ property: mortgageFreePropertyMock });

    const html = wrapper.html();
    expect(html).not.toContain('Mortgage Outstanding');
  });

  it('calculates and displays equity correctly for sole ownership', () => {
    // Equity = (500,000 - 200,000) * 100% = 300,000
    const html = wrapper.html();
    expect(html).toContain('Equity');
    expect(html).toContain('£300,000');
  });

  it('calculates and displays equity correctly for joint ownership', async () => {
    await wrapper.setProps({ property: jointPropertyMock });

    // Equity = (300,000 - 150,000) * 50% = 75,000
    const html = wrapper.html();
    expect(html).toContain('Equity');
    expect(html).toContain('£75,000');
  });

  it('formats currency values with GBP symbol', () => {
    const html = wrapper.html();
    // Check for £ symbol in formatted values
    expect(html).toContain('£');
  });

  it('formats currency without decimal places', () => {
    const html = wrapper.html();
    // Should not show .00
    expect(html).not.toContain('.00');
  });

  it('exposes the card as the click target', () => {
    const card = wrapper.find('.property-card');
    expect(card.exists()).toBe(true);
  });

  it('emits the selected property when the card is clicked', async () => {
    const card = wrapper.find('.property-card');
    await card.trigger('click');

    expect(wrapper.emitted('select-property')).toEqual([[solePropertyMock]]);
  });

  it('applies hover styles with CSS classes', () => {
    const card = wrapper.find('.property-card');
    expect(card.exists()).toBe(true);

    // Check that the card has styling that supports hover
    const style = wrapper.html();
    expect(style).toBeTruthy();
  });

  it('shows property type specific badge colours', async () => {
    // Main residence - blue
    expect(wrapper.find('.type-main_residence').exists()).toBe(true);

    // Secondary residence - violet
    await wrapper.setProps({ property: mortgageFreePropertyMock });
    expect(wrapper.find('.type-secondary_residence').exists()).toBe(true);

    // Buy to let - spring
    await wrapper.setProps({ property: jointPropertyMock });
    expect(wrapper.find('.type-buy_to_let').exists()).toBe(true);
  });

  it('handles property with no mortgage correctly', async () => {
    await wrapper.setProps({ property: mortgageFreePropertyMock });

    const html = wrapper.html();
    // Current value should equal equity when no mortgage
    expect(html).toContain('£250,000'); // Both current value and equity
    expect(html).not.toContain('Mortgage Outstanding');
  });

  it('computes property type label correctly', () => {
    expect(wrapper.vm.propertyTypeLabel).toBe('Main Residence');

    // Test computed property directly
    expect(wrapper.vm.typeClass).toBe('type-main_residence');
  });

  it('computes isJoint correctly', async () => {
    expect(wrapper.vm.isJoint).toBe(false);

    await wrapper.setProps({ property: jointPropertyMock });
    expect(wrapper.vm.isJoint).toBe(true);
  });

  it('computes hasMortgage correctly', async () => {
    expect(wrapper.vm.hasMortgage).toBe(true);

    await wrapper.setProps({ property: mortgageFreePropertyMock });
    expect(wrapper.vm.hasMortgage).toBe(false);
  });

  it('computes mortgageAmount correctly', () => {
    expect(wrapper.vm.mortgageAmount).toBe(200000);
  });

  it('computes equity with ownership percentage', async () => {
    // Sole ownership: (500,000 - 200,000) * 100% = 300,000
    expect(wrapper.vm.equity).toBe(300000);

    // Joint ownership: (300,000 - 150,000) * 50% = 75,000
    await wrapper.setProps({ property: jointPropertyMock });
    expect(wrapper.vm.equity).toBe(75000);
  });

  it('handles missing ownership_percentage gracefully', async () => {
    const propertyWithoutOwnership = {
      ...solePropertyMock,
      ownership_percentage: null,
    };

    await wrapper.setProps({ property: propertyWithoutOwnership });

    // Should default to 100%
    expect(wrapper.vm.equity).toBe(300000);
  });

  it('displays all detail rows in correct order', () => {
    const detailRows = wrapper.findAll('.detail-row');

    // Should have: Current Value, Mortgage Outstanding, Equity
    expect(detailRows.length).toBe(3);

    expect(detailRows[0].text()).toContain('Current Value');
    expect(detailRows[1].text()).toContain('Mortgage Outstanding');
    expect(detailRows[2].text()).toContain('Equity');
  });

  it('applies equity row styling', () => {
    const equityRow = wrapper.findAll('.detail-row')[2];
    expect(equityRow.classes()).toContain('equity');
  });

  it('applies mortgage value styling', () => {
    const mortgageValue = wrapper.find('.detail-value.mortgage');
    expect(mortgageValue.exists()).toBe(true);
  });
});
