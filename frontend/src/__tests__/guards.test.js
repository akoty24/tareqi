/**
 * Route guards and the login/register flows for a regular (non-admin) user
 * who starts without a stored token.
 */
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { bodyText, mountApp } from './setup'

vi.mock('@/api', async () => {
  const { apiMock, admin } = await import('./fixtures')
  return apiMock({ ...admin, role: 'user', name: 'سارة إبراهيم' })
})

let app

beforeAll(async () => {
  localStorage.clear()
  app = await mountApp()
})

const fill = (selector, value) => {
  const el = document.querySelector(selector)
  el.value = value
  el.dispatchEvent(new Event('input'))
}

describe('route guards', () => {
  it('redirects guests to login and remembers the target', async () => {
    await vi.waitFor(async () => {
      await app.visit('/my-bookings')
      expect(app.router.currentRoute.value.name).toBe('login')
    })
    expect(app.router.currentRoute.value.query.redirect).toBe('/my-bookings')
    expect(bodyText()).toContain('ليس لديك حساب؟')
  })

  it('shows server validation errors next to the field', async () => {
    const { authApi } = await import('@/api')
    authApi.login.mockRejectedValueOnce(Object.assign(new Error('البيانات المدخلة غير صحيحة.'), {
      status: 422, errors: { login: ['حقل البريد أو رقم الموبايل مطلوب.'] },
    }))

    document.querySelector('form').dispatchEvent(new Event('submit'))
    await flushPromises()

    expect(bodyText()).toContain('حقل البريد أو رقم الموبايل مطلوب.')
    expect(document.querySelector('input[autocomplete="username"]').getAttribute('class')).toContain('input-error')
  })

  it('logs in and continues to the remembered page', async () => {
    const { authApi } = await import('@/api')
    fill('input[autocomplete="username"]', 'passenger@mishwar.test')
    fill('input[type="password"]', 'password')

    document.querySelector('form').dispatchEvent(new Event('submit'))
    await vi.waitFor(() => expect(app.router.currentRoute.value.name).toBe('my-bookings'))

    expect(authApi.login).toHaveBeenLastCalledWith({ login: 'passenger@mishwar.test', password: 'password' })
    expect(localStorage.getItem('mishwar_token')).toBe('t')
  })

  it('keeps logged-in users away from guest pages', async () => {
    await app.visit('/login')
    expect(app.router.currentRoute.value.name).toBe('home')
  })

  it('blocks non-admins from the admin area', async () => {
    await app.visit('/admin/users')
    expect(app.router.currentRoute.value.name).toBe('home')
    expect(bodyText()).not.toContain('لوحة الإدارة')
  })
})
