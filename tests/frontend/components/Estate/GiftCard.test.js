import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import GiftCard from '@/components/Estate/GiftCard.vue';

describe('GiftCard', () => {
  const dateYearsAgo = (years) => {
    const date = new Date();
    date.setMonth(date.getMonth() - Math.round(years * 12));
    return date.toISOString().split('T')[0];
  };

  const mockRecentGift = {
    id: 1,
    gift_date: dateYearsAgo(2),
    recipient: 'John Smith',
    gift_value: 50000,
    gift_type: 'pet',
  };

  const mockTaperGift = {
    id: 2,
    gift_date: dateYearsAgo(5),
    recipient: 'Jane Doe',
    gift_value: 75000,
    gift_type: 'pet',
  };

  const mockSurvivedGift = {
    id: 3,
    gift_date: dateYearsAgo(8),
    recipient: 'Bob Johnson',
    gift_value: 100000,
    gift_type: 'pet',
  };

  it('renders with gift prop', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays recipient name', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    const html = wrapper.html();
    expect(html).toContain('John Smith');
  });

  it('displays gift value formatted as currency', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/£50,000|50000/);
  });

  it('calculates years elapsed correctly', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    const yearsElapsed = wrapper.vm.yearsElapsed;
    expect(yearsElapsed).toBeGreaterThan(1.9);
    expect(yearsElapsed).toBeLessThan(2.1);
  });

  it('calculates years remaining until 7-year survival', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    const yearsRemaining = parseFloat(wrapper.vm.yearsRemaining);
    expect(yearsRemaining).toBeGreaterThan(4.9);
    expect(yearsRemaining).toBeLessThan(5.1);
  });

  it('calculates survival percentage correctly', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    const percentage = parseInt(wrapper.vm.survivalPercentage);
    expect(percentage).toBeGreaterThanOrEqual(28);
    expect(percentage).toBeLessThanOrEqual(30);
  });

  it('shows taper relief for gifts 3-7 years old', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockTaperGift,
      },
    });

    expect(wrapper.vm.showTaperRelief).toBe(true);
    const relief = wrapper.vm.taperReliefPercentage;
    expect(relief).toBeGreaterThan(0);
    expect(relief).toBeLessThanOrEqual(100);
  });

  it('does not show taper relief for gifts within 3 years', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    expect(wrapper.vm.showTaperRelief).toBe(false);
  });

  it('calculates taper relief percentage correctly for 3-4 years', () => {
    const giftAt35Years = {
      id: 100,
      gift_date: dateYearsAgo(3.5),
      recipient: 'Test',
      gift_value: 50000,
      gift_type: 'pet',
    };

    const wrapper = mount(GiftCard, {
      props: {
        gift: giftAt35Years,
      },
    });

    expect(wrapper.vm.taperReliefPercentage).toBe(20);
  });

  it('calculates taper relief percentage correctly for 4-5 years', () => {
    const giftAt45Years = {
      id: 101,
      gift_date: dateYearsAgo(4.5),
      recipient: 'Test',
      gift_value: 50000,
      gift_type: 'pet',
    };

    const wrapper = mount(GiftCard, {
      props: {
        gift: giftAt45Years,
      },
    });

    expect(wrapper.vm.taperReliefPercentage).toBe(40);
  });

  it('calculates taper relief percentage correctly for 5-6 years', () => {
    const giftAt55Years = {
      id: 102,
      gift_date: dateYearsAgo(5.5),
      recipient: 'Test',
      gift_value: 50000,
      gift_type: 'pet',
    };

    const wrapper = mount(GiftCard, {
      props: {
        gift: giftAt55Years,
      },
    });

    expect(wrapper.vm.taperReliefPercentage).toBe(60);
  });

  it('calculates taper relief percentage correctly for 6-7 years', () => {
    const giftAt65Years = {
      id: 103,
      gift_date: dateYearsAgo(6.5),
      recipient: 'Test',
      gift_value: 50000,
      gift_type: 'pet',
    };

    const wrapper = mount(GiftCard, {
      props: {
        gift: giftAt65Years,
      },
    });

    expect(wrapper.vm.taperReliefPercentage).toBe(80);
  });

  it('shows fully exempt status for gifts survived 7+ years', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockSurvivedGift,
      },
    });

    const statusText = wrapper.vm.statusText;
    expect(statusText).toMatch(/exempt|survived/i);
  });

  it('calculates effective IHT rate with taper relief', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockTaperGift,
      },
    });

    const effectiveRate = parseInt(wrapper.vm.effectiveIhtRate);
    expect(effectiveRate).toBeGreaterThanOrEqual(0);
    expect(effectiveRate).toBeLessThan(40); // Should be less than standard 40%
  });

  it('displays gift type correctly', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: {
          ...mockRecentGift,
          gift_type: 'pet',
        },
      },
    });

    const typeDisplay = wrapper.vm.giftTypeDisplay;
    expect(typeDisplay).toMatch(/potentially exempt transfer|pet/i);
  });

  it('applies taxable status class for recent gifts', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    const statusClass = wrapper.vm.statusClass;
    expect(statusClass).toBe('status-taxable');
  });

  it('applies taper status class for mid-period gifts', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockTaperGift,
      },
    });

    const statusClass = wrapper.vm.statusClass;
    expect(statusClass).toBe('status-taper');
  });

  it('applies exempt status class for survived gifts', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockSurvivedGift,
      },
    });

    const statusClass = wrapper.vm.statusClass;
    expect(statusClass).toBe('status-exempt');
  });

  it('emits edit event when edit button clicked', async () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    await wrapper.vm.handleEdit();
    expect(wrapper.emitted()).toHaveProperty('edit');
    expect(wrapper.emitted('edit')[0]).toEqual([mockRecentGift]);
  });

  it('emits delete event when delete confirmed', async () => {
    global.confirm = () => true; // Mock confirmation

    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    await wrapper.vm.handleDelete();
    expect(wrapper.emitted()).toHaveProperty('delete');
    expect(wrapper.emitted('delete')[0]).toEqual([1]); // gift ID
  });

  it('does not emit delete when cancelled', async () => {
    global.confirm = () => false; // Mock cancellation

    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    await wrapper.vm.handleDelete();
    expect(wrapper.emitted('delete')).toBeUndefined();
  });

  it('formats date correctly', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    const formatted = wrapper.vm.formatDate(mockRecentGift.gift_date);
    expect(formatted).toMatch(/\d{1,2}.*\w+.*\d{4}/); // e.g., "15 January 2022"
  });

  it('displays progress bar', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/progress.*bar/i);
  });
});
