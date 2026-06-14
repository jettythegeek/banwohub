import { expect, type Page } from '@playwright/test'

export const STAFF_EMAIL = process.env.E2E_STAFF_EMAIL ?? 'admin@banwolaw.com'
export const STAFF_PASSWORD = process.env.E2E_STAFF_PASSWORD ?? 'ChangeMe123!'

export async function staffLogin(page: Page): Promise<void> {
  await page.goto('/login')
  await page.locator('#email').fill(STAFF_EMAIL)
  await page.locator('#password').fill(STAFF_PASSWORD)
  await page.getByRole('button', { name: 'Sign in', exact: true }).click()
  await expect(page.getByText(/Welcome back/i)).toBeVisible()
}
