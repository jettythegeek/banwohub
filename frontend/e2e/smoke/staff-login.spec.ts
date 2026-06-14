import { expect, test } from '@playwright/test'
import { staffLogin } from '../helpers/auth'

test.describe('staff smoke', () => {
  test('staff login and dashboard', async ({ page }) => {
    await staffLogin(page)
    await expect(page).toHaveURL(/\/dashboard/)
    await expect(page.getByRole('link', { name: 'New case' })).toBeVisible()
  })

  test('navigate dashboard from sidebar', async ({ page }) => {
    await staffLogin(page)
    const sidebar = page.locator('aside.sidebar-nav, aside nav').first()
    await sidebar.getByRole('link', { name: 'Cases', exact: true }).click()
    await expect(page).toHaveURL(/\/cases/)
    await expect(page.getByRole('heading', { name: 'Cases', exact: true })).toBeVisible()
    await sidebar.getByRole('link', { name: 'Dashboard', exact: true }).click()
    await expect(page).toHaveURL(/\/dashboard/)
  })
})
