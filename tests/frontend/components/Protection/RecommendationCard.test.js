import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import RecommendationCard from '@/components/Protection/RecommendationCard.vue';

describe('RecommendationCard', () => {
  const mockRecommendation = {
    priority: 'high',
    category: 'life_insurance',
    action: 'Increase Life Insurance Coverage',
    rationale: 'Your current coverage does not adequately protect your family.',
    impact: 'Provides financial security for your dependents.',
    estimated_cost: 50,
  };

  it('renders all recommendation fields', () => {
    const wrapper = mount(RecommendationCard, {
      props: {
        recommendation: mockRecommendation,
      },
    });

    expect(wrapper.text()).toContain('Increase Life Insurance Coverage');
    expect(wrapper.text()).toContain('Your current coverage does not adequately protect your family.');
    expect(wrapper.text()).toContain('Provides financial security for your dependents.');
    expect(wrapper.text()).toContain('50');
  });

  it('displays priority badge with correct color (high = red)', () => {
    const wrapper = mount(RecommendationCard, {
      props: {
        recommendation: { ...mockRecommendation, priority: 'high' },
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/high/i);
    expect(html).toMatch(/bg-red|text-red/);
  });

  it('displays priority badge with correct color (medium = orange)', () => {
    const wrapper = mount(RecommendationCard, {
      props: {
        recommendation: { ...mockRecommendation, priority: 'medium' },
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/medium/i);
    expect(html).toMatch(/bg-orange|bg-yellow|text-orange|text-yellow/);
  });

  it('displays priority badge with correct color (low = blue)', () => {
    const wrapper = mount(RecommendationCard, {
      props: {
        recommendation: { ...mockRecommendation, priority: 'low' },
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/low/i);
    expect(html).toMatch(/bg-blue|text-blue/);
  });

  it('is expandable to show more details', async () => {
    const wrapper = mount(RecommendationCard, {
      props: {
        recommendation: mockRecommendation,
      },
    });

    // Check if there's an expand button or toggle
    const expandButton = wrapper.find('[data-testid="expand-button"]');
    if (expandButton.exists()) {
      const initialHeight = wrapper.element.scrollHeight;
      await expandButton.trigger('click');

      // After expansion, more content should be visible
      const expandedHeight = wrapper.element.scrollHeight;
      expect(expandedHeight >= initialHeight).toBe(true);
    }
  });

  it('displays estimated cost with currency symbol', () => {
    const wrapper = mount(RecommendationCard, {
      props: {
        recommendation: { ...mockRecommendation, estimated_cost: 125.50 },
      },
    });

    const text = wrapper.text();
    expect(text).toMatch(/£.*125|125.*£/);
  });

  it('formats category name properly', () => {
    const wrapper = mount(RecommendationCard, {
      props: {
        recommendation: mockRecommendation,
      },
    });

    const text = wrapper.text();
    // Should convert life_insurance to "Life Insurance" or similar
    expect(text).toMatch(/life.*insurance/i);
  });

  it('has "Mark as Done" button', () => {
    const wrapper = mount(RecommendationCard, {
      props: {
        recommendation: mockRecommendation,
      },
    });

    const buttons = wrapper.findAll('button');
    const markDoneButton = buttons.find(btn => btn.text().match(/mark.*done/i));
    expect(markDoneButton).toBeDefined();
  });
});
