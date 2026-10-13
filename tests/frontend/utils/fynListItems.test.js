import { describe, it, expect } from 'vitest';
import { wrapListItems } from '@/utils/fynListItems';
import { renderFynText } from '../../../resources/mobile/utils/fynText.js';

describe('wrapListItems (walk R28)', () => {
  it('makes bullets separated by blank lines one list', () => {
    expect(wrapListItems('- a\n\n- b\n\n- c')).toBe('<ul><li>a</li><li>b</li><li>c</li></ul>');
  });

  it('keeps a tight bullet list as one list, with the attributes given', () => {
    expect(wrapListItems('Intro\n- a\n- b\nAfter', { ul: ' class="list-disc"' }))
      .toBe('Intro\n<ul class="list-disc"><li>a</li><li>b</li></ul>After');
  });

  it('puts numbered lines in one ordered list (Fyn\'s numbered working had none)', () => {
    expect(wrapListItems('Working:\n\n1. one\n2. two\n\n3. three', { ol: ' class="list-decimal"' }))
      .toBe('Working:\n\n<ol class="list-decimal"><li>one</li><li>two</li><li>three</li></ol>');
  });

  it('keeps two lists apart when text sits between them', () => {
    expect(wrapListItems('- a\n\nMiddle\n\n- b')).toBe('<ul><li>a</li></ul>\nMiddle\n\n<ul><li>b</li></ul>');
  });

  it('keeps bullets and numbers in their own lists', () => {
    expect(wrapListItems('- a\n1. b')).toBe('<ul><li>a</li></ul><ol><li>b</li></ol>');
  });
});

describe('/m renders Fyn lists like the web', () => {
  it('has no line breaks between loose bullets', () => {
    expect(renderFynText('Working:\n\n- one\n\n- two')).toBe('Working:<br><br><ul><li>one</li><li>two</li></ul>');
  });

  it('puts numbered working in an ordered list', () => {
    expect(renderFynText('Steps:\n1. one\n2. two')).toBe('Steps:<br><ol><li>one</li><li>two</li></ol>');
  });
});
