import { describe, it, expect } from 'vitest';
import { wrapListItems } from '@/utils/fynListItems';
import { renderFynText } from '../../../resources/mobile/utils/fynText.js';

describe('wrapListItems (walk R28)', () => {
  it('makes items separated by blank lines one list', () => {
    expect(wrapListItems('<li>a</li>\n\n<li>b</li>\n\n<li>c</li>'))
      .toBe('<ul><li>a</li><li>b</li><li>c</li></ul>');
  });

  it('keeps a tight list as one list, with the attributes given', () => {
    expect(wrapListItems('Intro\n<li>a</li>\n<li>b</li>\nAfter', ' class="list-disc"'))
      .toBe('Intro\n<ul class="list-disc"><li>a</li><li>b</li></ul>After');
  });

  it('keeps two lists apart when text sits between them', () => {
    expect(wrapListItems('<li>a</li>\n\nMiddle\n\n<li>b</li>'))
      .toBe('<ul><li>a</li></ul>\nMiddle\n\n<ul><li>b</li></ul>');
  });
});

describe('/m renders a loose Fyn list as one list', () => {
  it('has no line breaks between the items', () => {
    expect(renderFynText('Working:\n\n- one\n\n- two'))
      .toBe('Working:<br><br><ul><li>one</li><li>two</li></ul>');
  });
});
