import { expect, test } from '@playwright/test'
import { staffLogin } from '../helpers/auth'

const E2E_CASE_TITLE = 'E2E Smoke Case'
const E2E_TASK_TITLE = 'E2E Smoke Task'

test.describe('case tasks smoke', () => {
  test.beforeEach(async ({ page }) => {
    await staffLogin(page)
  })

  test('open case tasks workspace', async ({ page }) => {
    await page.goto('/cases')
    await page.getByRole('button', { name: E2E_CASE_TITLE }).click()
    await page.getByRole('button', { name: 'Tasks' }).click()
    await expect(page).toHaveURL(/\/tasks/)
    await expect(page.getByText(E2E_TASK_TITLE)).toBeVisible()
  })

  test('kanban view shows seeded task', async ({ page }) => {
    await page.goto('/cases')
    await page.getByRole('button', { name: E2E_CASE_TITLE }).click()
    await page.getByRole('button', { name: 'Tasks' }).click()
    await page.getByRole('button', { name: 'List view' }).click()
    await page.getByRole('button', { name: 'Kanban view' }).click()
    await expect(page.getByRole('heading', { name: 'Not started' })).toBeVisible()
    await expect(page.getByText(E2E_TASK_TITLE)).toBeVisible()
  })

  test('optional drag task to in progress column', async ({ page }) => {
    await page.goto('/cases')
    await page.getByRole('button', { name: E2E_CASE_TITLE }).click()
    await page.getByRole('button', { name: 'Tasks' }).click()
    await page.getByRole('button', { name: 'Kanban view' }).click()

    const card = page.locator('.bw-kanban-card', { hasText: E2E_TASK_TITLE })
    const inProgressColumn = page
      .locator('.bw-kanban-column')
      .filter({ has: page.getByRole('heading', { name: 'In progress' }) })
      .first()

    await expect(card).toBeVisible()
    await card.dragTo(inProgressColumn)
    await expect(inProgressColumn.getByText(E2E_TASK_TITLE)).toBeVisible()
  })
})
