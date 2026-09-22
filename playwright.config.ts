import { defineConfig } from '@playwright/test';
// Runs against the wp-env site (wp-env start): http://localhost:8889, admin / password.
export default defineConfig({ testDir: 'tests/e2e', globalSetup: './tests/e2e/global-setup.ts', timeout: 60_000, retries: process.env.CI ? 1 : 0, use: { baseURL: process.env.WP_URL ?? 'http://localhost:8889', ignoreHTTPSErrors: true, screenshot: 'only-on-failure' }, reporter: process.env.CI ? 'github' : 'list' });
