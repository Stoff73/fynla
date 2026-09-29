import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FynQuickReplies from '@/components/Fyn/FynQuickReplies.vue';

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

describe('FynQuickReplies', () => {
  it('emits the tapped bubble straight away on a single-choice step', async () => {
    const w = mount(FynQuickReplies, { props: { bubbles: [{ id: 'yes', label: 'Yes' }, { id: 'no', label: 'No' }] } });
    await chip(w, 'Yes').trigger('click');
    expect(w.emitted('select')).toEqual([[{ id: 'yes', label: 'Yes' }]]);
    expect(chip(w, 'Yes').attributes('aria-pressed')).toBeUndefined();
  });

  it('toggles chips without sending, then submits every pick in one message', async () => {
    const w = mount(FynQuickReplies, { props: { bubbles: assets, multiSelect: true } });
    await chip(w, 'Property').trigger('click');
    await chip(w, 'Bank account').trigger('click');
    await chip(w, 'ISA').trigger('click');
    await chip(w, 'Pension').trigger('click');

    expect(w.emitted('select')).toBeUndefined();
    expect(chip(w, 'ISA').attributes('aria-pressed')).toBe('true');
    expect(chip(w, 'ISA').classes()).toContain('bg-raspberry-500');
    expect(chip(w, 'Savings account').attributes('aria-pressed')).toBe('false');
    expect(chip(w, 'Savings account').classes()).toContain('bg-white');

    await chip(w, "That's everything").trigger('click');
    expect(w.emitted('select')).toEqual([[{ id: 'done', label: "Bank account, ISA, Pension, Property, That's everything" }]]);
  });

  it('sends "That\'s everything" alone when nothing is picked, and untoggles a second tap', async () => {
    const w = mount(FynQuickReplies, { props: { bubbles: assets, multiSelect: true } });
    await chip(w, 'ISA').trigger('click');
    await chip(w, 'ISA').trigger('click');
    await chip(w, "That's everything").trigger('click');
    expect(w.emitted('select')).toEqual([[{ id: 'done', label: "That's everything" }]]);
  });

  it('ignores taps while disabled', async () => {
    const w = mount(FynQuickReplies, { props: { bubbles: assets, multiSelect: true, disabled: true } });
    await chip(w, 'ISA').trigger('click');
    expect(chip(w, 'ISA').attributes('aria-pressed')).toBe('false');
  });

  it('adds no glyph to a selected chip (Rule 15)', async () => {
    const w = mount(FynQuickReplies, { props: { bubbles: assets, multiSelect: true } });
    await chip(w, 'Pension').trigger('click');
    expect(chip(w, 'Pension').text()).toBe('Pension');
    expect(chip(w, 'Pension').find('svg').exists()).toBe(false);
  });
});
