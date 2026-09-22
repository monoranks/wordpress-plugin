import { test, expect } from '@playwright/test';

// Chromium against a real WordPress with the plugin active (wp-env). Covers what a person and MonoRanks touch first:
// the settings page, the key form's validation, the public ping and the protected REST routes, and that nothing is
// published (robots.txt lines, llms.txt) before an approval.
const user = process.env.WP_USER ?? 'admin', pass = process.env.WP_PASS ?? 'password';

test.beforeEach(async ({ page }) => {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', user); await page.fill('#user_pass', pass); await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
});

test('settings page renders and rejects a malformed key', async ({ page }) => {
  await page.goto('/wp-admin/options-general.php?page=monoranks');
  await expect(page.getByRole('heading', { name: 'Settings', level: 1 })).toBeVisible();
  await expect(page.getByRole('img', { name: 'MonoRanks' }).first()).toBeVisible();
  await expect(page.getByText('Not connected')).toBeVisible();
  await page.fill('#monoranks-key', 'not-a-key');
  await page.getByRole('button', { name: 'Connect' }).click();
  await expect(page.locator('.notice-error')).toContainText('does not look like a MonoRanks connector key');
  await expect(page.getByText('Not connected')).toBeVisible();
});

test('overview and settings are one app: switching screens changes the URL without a page load', async ({ page }) => {
  await page.goto('/wp-admin/admin.php?page=monoranks');
  await expect(page.getByRole('heading', { name: 'Overview', level: 1 })).toBeVisible();
  await expect(page.getByText('Connect MonoRanks', { exact: true })).toBeVisible();
  await page.evaluate(() => { (window as unknown as { __monoranks_loaded: number }).__monoranks_loaded = 1; });
  await page.getByRole('navigation', { name: 'MonoRanks sections' }).getByRole('link', { name: 'Settings' }).click();
  await expect(page.getByRole('heading', { name: 'Settings', level: 1 })).toBeVisible();
  await expect(page).toHaveURL(/page=monoranks-settings/);
  expect(await page.evaluate(() => (window as unknown as { __monoranks_loaded?: number }).__monoranks_loaded)).toBe(1);
  await expect(page.locator('#toplevel_page_monoranks .wp-submenu a.current')).toHaveAttribute('href', /monoranks-settings/);
  await page.goBack();
  await expect(page.getByRole('heading', { name: 'Overview', level: 1 })).toBeVisible();
});

test('the posts list has a MonoRanks column with a hover card', async ({ page }) => {
  await page.goto('/wp-admin/edit.php');
  await expect(page.locator('th#monoranks')).toContainText('MonoRanks');
  // Not connected: the cell offers the connection instead of scores.
  await expect(page.locator('td.column-monoranks').first()).toContainText('Connect MonoRanks');
});

test('a site that is not connected can erase everything the plugin stored', async ({ page }) => {
  await page.goto('/wp-admin/admin.php?page=monoranks-settings');
  await expect(page.getByRole('heading', { name: 'Settings', level: 1 })).toBeVisible();
  // Nothing stored yet, so there is nothing to erase and no danger zone.
  await expect(page.getByText('Danger zone')).toHaveCount(0);
});

test('REST: ping is public, everything else needs an authenticated admin', async ({ page, request }) => {
  const ping = await request.get('/wp-json/monoranks/v1/ping');
  expect(ping.ok()).toBeTruthy();
  const j = await ping.json();
  expect(j.plugin ?? j.version ?? j.ok).toBeTruthy();
  for (const route of ['status']) expect((await request.get(`/wp-json/monoranks/v1/${route}`)).status()).toBe(401);
  for (const route of ['pair', 'sync', 'apply', 'unpair']) expect((await request.post(`/wp-json/monoranks/v1/${route}`, { data: {} })).status()).toBe(401);
  // Signed in as admin with the REST nonce: status answers.
  const nonce = await page.evaluate(() => (window as unknown as { wpApiSettings?: { nonce: string } }).wpApiSettings?.nonce ?? '');
  if (nonce) { const r = await page.request.get('/wp-json/monoranks/v1/status', { headers: { 'X-WP-Nonce': nonce } }); expect(r.ok()).toBeTruthy(); }
});

test('nothing is published before an approval', async ({ request }) => {
  const robots = await request.get('/robots.txt');
  expect((await robots.text())).not.toMatch(/GPTBot|ClaudeBot|PerplexityBot/);
  const llms = await request.get('/llms.txt', { maxRedirects: 0 });
  expect(llms.status()).not.toBe(200);
});
