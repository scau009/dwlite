import { test, expect } from '@playwright/test';

const LOGIN_EMAIL = process.env.E2E_LOGIN_EMAIL ?? 'admin@example.com';
const LOGIN_PASSWORD = process.env.E2E_LOGIN_PASSWORD ?? 'Admin123!';

test('login via UI and access authenticated route', async ({ page }) => {
  await page.goto('/login');

  const emailInput = page
    .locator('input[name="email"], input[placeholder="you@example.com"], input[placeholder="邮箱"]')
    .first();
  const passwordInput = page.locator('input[name="password"], input[type="password"]').first();

  await emailInput.fill(LOGIN_EMAIL);
  await passwordInput.fill(LOGIN_PASSWORD);

  await page.getByRole('button', { name: /login|登录/i }).click();
  await expect(page).toHaveURL(/\/dashboard$/);

  const accessToken = await page.evaluate(() => localStorage.getItem('dwlite_access_token'));
  const refreshToken = await page.evaluate(() => localStorage.getItem('dwlite_refresh_token'));

  expect(accessToken).toBeTruthy();
  expect(refreshToken).toBeTruthy();
});
