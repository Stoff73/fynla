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

  /**
   * C4 (tax-compliance-reviewer F4). These asserted taper relief from the gift's
   * AGE alone — 3.5 years → 20%, 4.5 → 40%, and so on — which is what the component
   * used to compute from a hardcoded schedule.
   *
   * IHTM14611 says the opposite in terms: relief applies only where "tax is due on
   * the transfer in its own right", and "if no tax is payable on the transfer
   * because it does not exceed the nil-rate band (after cumulation), there can be
   * no relief". Whether a gift bears tax depends on the whole estate's cumulation,
   * which this component cannot know — so the server answers, and the component
   * displays. A gift with no `taper` entry bears no tax.
   *
   * The old tests could not have caught the defect they were written around: every
   * gift in the seeded personas sits inside the allowance, so the badge they
   * asserted was wrong on essentially all real data.
   */
  const taperedGift = (yearsAgo, taper) => ({
    id: 100,
    gift_date: dateYearsAgo(yearsAgo),
    recipient: 'Test',
    gift_value: 50000,
    gift_type: 'pet',
    taper,
  });

  it('shows taper relief when the server says the gift bears tax', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: taperedGift(3.5, {
          chargeable_amount: 25000,
          tax_rate_percent: 32,
          taper_relief_percent: 20,
          taper_saving: 2000,
        }),
      },
    });

    expect(wrapper.vm.showTaperRelief).toBe(true);
    expect(wrapper.vm.taperReliefPercentage).toBe(20);
    expect(wrapper.vm.effectiveIhtRate).toBe(32);
  });

  it('shows NO taper relief for an old gift that sits within the allowance', () => {
    // Six years old — the old component showed "80% Taper Relief" on this. The
    // gift bears no tax, so there is nothing to taper.
    const wrapper = mount(GiftCard, {
      props: { gift: taperedGift(6.0, null) },
    });

    expect(wrapper.vm.showTaperRelief).toBe(false);
    expect(wrapper.vm.taperReliefPercentage).toBe(0);
    expect(wrapper.vm.statusText).toBe('Within Your Allowance — No Tax');
  });

  it('says a gift is taxable when it is above the allowance but too recent to taper', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: taperedGift(1.0, {
          chargeable_amount: 25000,
          tax_rate_percent: 40,
          taper_relief_percent: 0,
          taper_saving: 0,
        }),
      },
    });

    expect(wrapper.vm.showTaperRelief).toBe(false);
    expect(wrapper.vm.statusText).toBe('Taxable — Above Your Allowance');
  });

  it('does not show taper relief for gifts within 3 years', () => {
    const wrapper = mount(GiftCard, {
      props: {
        gift: mockRecentGift,
      },
    });

    expect(wrapper.vm.showTaperRelief).toBe(false);
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
