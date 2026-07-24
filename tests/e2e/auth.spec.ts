import { test, expect } from '@playwright/test';

test('user can login and see dashboard', async ({ page }) => {
  await page.goto('/login');
  
  await page.fill('input[name="email"]', 'test@example.com'); // Assume this user exists in seeder
  await page.fill('input[name="password"]', 'password');
  
  await page.click('button[type="submit"]');
  
  // Wait for redirect to dashboard or onboarding
  await page.waitForURL(/\/(dashboard|onboarding)/);
  
  // Assert user is logged in by checking URL or elements
  const currentUrl = page.url();
  expect(currentUrl).toMatch(/\/(dashboard|onboarding)/);
});
