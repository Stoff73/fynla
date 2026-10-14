import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CountrySelector from '@/components/Shared/CountrySelector.vue';

/**
 * T73 (T67 walk, csjones Personal Info, 2026-10-13): the list opened only on
 * focus. After Save Changes and the browser's "Please fill in this field" the
 * field kept focus with the list closed, so typing "Engl" showed nothing and
 * the form could not be saved without moving focus away and back.
 */
describe('CountrySelector — typing', () => {
  it('opens the list on typing, even when the field already had focus', async () => {
    vi.useFakeTimers();
    const wrapper = mount(CountrySelector, { props: { modelValue: '', defaultCountry: '' }, attachTo: document.body });
    const input = wrapper.find('input');

    await input.trigger('focus');
    await input.trigger('blur');
    vi.runAllTimers();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('ul').isVisible()).toBe(false);

    await input.setValue('Engl');
    expect(wrapper.find('ul').isVisible()).toBe(true);
    expect(wrapper.find('ul').text()).toContain('England');
    vi.useRealTimers();
  });
});
