import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FynBubbles from '../FynBubbles.vue';

// M4 (live fynla.org /m, 2026-09-29): tapping several asset chips quickly
// kept only the first. On a multi-select step the chips toggle locally and
// "That's everything" sends every pick in one message.
const assets = [
  { id: 'bank', label: 'Bank account' },
  { id: 'savings', label: 'Savings account' },
  { id: 'isa', label: 'ISA' },
  { id: 'pension', label: 'Pension' },
  { id: 'property', label: 'Property' },
  { id: 'done', label: "That's everything" },
];

const chip = (w, label) => w.findAll('button').find((b) => b.text() === label);

describe('FynBubbles', () => {
  it('emits the tapped bubble straight away on a single-choice step', async () => {
    const w = mount(FynBubbles, { props: { bubbles: [{ id: 'yes', label: 'Yes' }, { id: 'no', label: 'No' }] } });
    await chip(w, 'No').trigger('click');
    expect(w.emitted('choose')).toEqual([[{ id: 'no', label: 'No' }]]);
    expect(chip(w, 'No').attributes('aria-pressed')).toBeUndefined();
  });

  it('toggles chips without sending, then submits every pick in one message', async () => {
    const w = mount(FynBubbles, { props: { bubbles: assets, multiSelect: true } });
    await chip(w, 'Pension').trigger('click');
    await chip(w, 'Bank account').trigger('click');
    await chip(w, 'ISA').trigger('click');
    await chip(w, 'Property').trigger('click');
    await chip(w, 'ISA').trigger('click');
    await chip(w, 'ISA').trigger('click');

    expect(w.emitted('choose')).toBeUndefined();
    expect(chip(w, 'ISA').attributes('aria-pressed')).toBe('true');
    expect(chip(w, 'ISA').classes()).toContain('md-fyn__bubble--selected');
    expect(chip(w, 'Savings account').attributes('aria-pressed')).toBe('false');
    expect(chip(w, 'Savings account').classes()).not.toContain('md-fyn__bubble--selected');
    expect(chip(w, "That's everything").attributes('aria-pressed')).toBeUndefined();

    await chip(w, "That's everything").trigger('click');
    // Display order, not tap order; the submit label last (the director's wire format).
    expect(w.emitted('choose')).toEqual([[{ id: 'done', label: "Bank account, ISA, Pension, Property, That's everything" }]]);
  });

  it('untoggles a chip tapped twice', async () => {
    const w = mount(FynBubbles, { props: { bubbles: assets, multiSelect: true } });
    await chip(w, 'ISA').trigger('click');
    await chip(w, 'ISA').trigger('click');
    await chip(w, "That's everything").trigger('click');
    expect(w.emitted('choose')).toEqual([[{ id: 'done', label: "That's everything" }]]);
  });

  it('ignores taps while a turn is in flight', async () => {
    const w = mount(FynBubbles, { props: { bubbles: assets, multiSelect: true, disabled: true } });
    await chip(w, 'ISA').trigger('click');
    expect(chip(w, 'ISA').classes()).not.toContain('md-fyn__bubble--selected');
  });

  it('adds no glyph to a selected chip (Rule 15)', async () => {
    const w = mount(FynBubbles, { props: { bubbles: assets, multiSelect: true } });
    await chip(w, 'ISA').trigger('click');
    expect(chip(w, 'ISA').text()).toBe('ISA');
    expect(chip(w, 'ISA').find('svg').exists()).toBe(false);
  });
});
