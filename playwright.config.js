import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const reuseExistingServer = process.env.PLAYWRIGHT_REUSE_SERVER === '1' && !process.env.CI;
const chromeChannel = process.env.PLAYWRIGHT_CHROME_CHANNEL || undefined;

export default defineConfig({
  testDir: './tests/E2E',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { open: 'never' }],
    ['json', { outputFile: 'test-results/results.json' }],
  ],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'desktop-chromium',
      testIgnore: /mobile\.spec\.js$/,
      use: { ...devices['Desktop Chrome'], channel: chromeChannel },
    },
    {
      name: 'mobile-chromium',
      testMatch: /mobile\.spec\.js$/,
      use: { ...devices['Pixel 7'] },
    },
    {
      name: 'mobile-webkit',
      testMatch: [/mobile\.spec\.js$/, /freemium\/upgrade-entry-points\.spec\.js$/],
      use: { ...devices['iPhone 14'] },
    },
  ],
  webServer: [
    {
      command: 'bash scripts/e2e/serve.sh laravel',
      url: baseURL,
      reuseExistingServer,
      timeout: 120_000,
    },
    {
      command: 'bash scripts/e2e/serve.sh vite',
      url: 'http://127.0.0.1:5173/@vite/client',
      reuseExistingServer,
      timeout: 120_000,
    },
  ],
});
