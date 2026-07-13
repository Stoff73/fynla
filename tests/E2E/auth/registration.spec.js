import { test, expect } from '../fixtures/app.js';
import { generateEmail } from '../helpers/common.js';
import { login, logout } from '../helpers/auth.js';

test('registration verification creates a real user who can sign out and sign back in', async ({
  page,
  request,
  registerVerifiedUser,
}) => {
  const runId = process.env.GITHUB_RUN_ID || 'local';
  const email = generateEmail(runId, Date.now());
  const password = 'E2eSecure1!';

  await registerVerifiedUser({
    firstName: 'E2E',
    surname: 'User',
    email,
    password,
  });

  const userResponse = await request.get(`/__e2e/user?email=${encodeURIComponent(email)}`);
  expect(userResponse.ok()).toBeTruthy();

  const user = await userResponse.json();
  expect(user.email).toBe(email);
  expect(user.is_preview_user).toBe(false);

  await logout(page);
  await login(page, request, { email, password });
});
