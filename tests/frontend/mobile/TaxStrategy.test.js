import { describe, expect, it } from 'vitest';
import TaxStrategy from '../../../resources/mobile/views/TaxStrategy.vue';

describe('mobile Tax Strategy', () => {
  it('does not describe unused allowances as well-utilised when no actions are available', () => {
    const message = TaxStrategy.computed.emptyRecommendationsMessage.call({
      totalHeadroom: 79000,
    });

    expect(message).toContain('unused allowances');
    expect(message).not.toContain('well-utilised');
  });
});
