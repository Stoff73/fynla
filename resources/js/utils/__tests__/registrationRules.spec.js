import { describe, it, expect } from 'vitest';
import { validateRegistration, PASSWORD_COMPLEXITY_MESSAGE } from '../registrationRules';

/**
 * W-0542. The server sent three password errors for one attempt; the form
 * showed one and ran no checks of its own, so a user learned the rules one
 * throttled request at a time.
 */
describe('validateRegistration', () => {
  it('reports every failing password rule at once, in the server wording', () => {
    const errors = validateRegistration({
      first_name: 'A', last_name: 'B', email: 'a@b.co', password: 'abc', password_confirmation: 'abd',
    });

    expect(errors.password).toEqual([
      'The password field must be at least 8 characters.',
      PASSWORD_COMPLEXITY_MESSAGE,
      'The password field confirmation does not match.',
    ]);
  });

  it('accepts a password that meets the same expression RegisterRequest uses', () => {
    const errors = validateRegistration({
      first_name: 'A', last_name: 'B', email: 'a@b.co', password: 'Passw0rd_x', password_confirmation: 'Passw0rd_x',
    });

    expect(errors).toEqual({});
  });

  it('checks every field, not only the password', () => {
    const errors = validateRegistration({ first_name: ' ', last_name: '', email: 'nope', password: '', password_confirmation: '' });

    expect(Object.keys(errors).sort()).toEqual(['email', 'first_name', 'last_name', 'password']);
    expect(errors.email).toEqual(['The email field must be a valid email address.']);
  });
});
