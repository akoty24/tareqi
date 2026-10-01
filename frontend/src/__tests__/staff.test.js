/**
 * A staff member with a limited role (moderator: dashboard + reports) only
 * sees and reaches the admin sections their permissions allow.
 */
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { bodyText, captureProblems, mountApp } from './setup'

vi.mock('@/api', async () => {
  const { apiMock, staffUser } = await import('./fixtures')
  return apiMock(staffUser())
})

let app
let problems

beforeAll(async () => {
  localStorage.setItem('mishwar_token', 'test-token')
  problems = captureProblems()
  app = await mountApp()
  // Let the initial navigation ("/") finish before visiting admin pages.
  await app.router.isReady()
  await app.visit('/')
})

afterEach(() => {
  expect(app.errors.map(String)).toEqual([])
  expect(problems).toEqual([])
})

const navLabels = () => [...document.querySelectorAll('aside nav a')].map((a) => a.textContent.trim())

describe('limited staff role', () => {
  it('shows only the allowed sections in the admin navigation', async () => {
    await app.visit('/admin')
    // First visit also lazy-loads the admin layout chunk.
    await vi.waitFor(() => expect(navLabels()).toEqual(['لوحة التحكم', 'البلاغات']))
    expect(bodyText()).toContain('دورك: مشرف')
  })

  it('hides dashboard tiles of sections the role cannot open', async () => {
    await app.visit('/admin')
    const text = bodyText()
    expect(text).toContain('البلاغات')
    expect(text).not.toContain('إجمالي المستخدمين')
    expect(text).not.toContain('إرسال إشعار')
  })

  it('redirects to the first allowed section when opening a forbidden one', async () => {
    await app.visit('/admin/users')
    expect(app.router.currentRoute.value.name).toBe('admin')

    await app.visit('/admin/roles')
    expect(app.router.currentRoute.value.name).toBe('admin')
  })

  it('can review reports (reports.manage)', async () => {
    const { adminApi } = await import('@/api')
    await app.visit('/admin/reports')
    const review = [...document.querySelectorAll('button')].find((b) => b.textContent.trim() === 'مراجعة')
    review.click()
    await flushPromises()
    expect(adminApi.report).toHaveBeenCalled()
    expect(document.querySelector('#review-form')).not.toBeNull()
  })
})
