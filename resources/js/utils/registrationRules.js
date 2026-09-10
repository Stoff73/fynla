/**
 * The registration rules, checked in the browser before the request goes out
 * (W-0542). They mirror `RegisterRequest` — same rule, same wording — so a
 * user sees everything wrong at once instead of learning one rule per request
 * against the `throttle:auth-5` limit. The server remains the authority.
 */

/** Same expression as RegisterRequest: lower, upper, digit and a non-alphanumeric. */
export const PASSWORD_COMPLEXITY = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/;
export const PASSWORD_MIN_LENGTH = 8;

export const PASSWORD_COMPLEXITY_MESSAGE = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';

const EMAIL_SHAPE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * @param {{first_name?: string, last_name?: string, email?: string, password?: string, password_confirmation?: string}} form
 * @returns {Record<string, string[]>} field => every message that applies; empty when the form is valid
 */
export function validateRegistration(form) {
  const errors = {};
  const add = (field, message) => { (errors[field] ||= []).push(message); };
  const text = (v) => (typeof v === 'string' ? v.trim() : '');

  if (text(form.first_name) === '') add('first_name', 'The first name field is required.');
  if (text(form.last_name) === '') add('last_name', 'The last name field is required.');

  const email = text(form.email);
  if (email === '') add('email', 'The email field is required.');
  else if (!EMAIL_SHAPE.test(email)) add('email', 'The email field must be a valid email address.');

  const password = form.password ?? '';
  if (password === '') {
    add('password', 'The password field is required.');
  } else {
    if (password.length < PASSWORD_MIN_LENGTH) add('password', `The password field must be at least ${PASSWORD_MIN_LENGTH} characters.`);
    if (!PASSWORD_COMPLEXITY.test(password)) add('password', PASSWORD_COMPLEXITY_MESSAGE);
    if (password !== (form.password_confirmation ?? '')) add('password', 'The password field confirmation does not match.');
  }

  return errors;
}
