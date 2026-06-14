import { expect, test } from '@playwright/test'

test.describe('portal smoke', () => {
  test('portal login page loads', async ({ page }) => {
    await page.goto('/portal/login')
    await expect(page.getByText('Client portal sign in')).toBeVisible()
    await expect(page.locator('#portal-email')).toBeVisible()
    await expect(page.locator('#portal-password')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Sign in', exact: true })).toBeVisible()
    await expect(
      page.getByRole('link', { name: 'Sign in to Banwolaw Hub' }),
    ).toBeVisible()
  })
})
