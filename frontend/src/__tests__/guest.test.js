/**
 * Guests (no token) can browse the home page, search and trip details; any
 * action sends them to the login page and back afterwards.
 */
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { bodyText, captureProblems, mountApp } from './setup'

vi.mock('@/api', async () => {
  const { apiMock, trip } = await import('./fixtures')
  const api = apiMock()
  // Public trip as a guest sees it: not mine, no booking, no phone.
  api.tripsApi.show = vi.fn(() => Promise.resolve({ success: true, message: '', data: trip({ id: 10, match: undefined, my_booking: null }) }))
  return api
})

let app
let problems

beforeAll(async () => {
  localStorage.clear()
  problems = captureProblems()
  app = await mountApp()
  await app.router.isReady()
})

afterEach(() => {
  expect(app.errors.map(String)).toEqual([])
  expect(problems).toEqual([])
})

describe('guest browsing', () => {
  it('opens the home page with trips and login / sign-up buttons', async () => {
    const { bookingsApi } = await import('@/api')
    await app.visit('/')
    await vi.waitFor(() => expect(bodyText()).toContain('رايح فين؟'))
    expect(app.router.currentRoute.value.name).toBe('home')
    const text = bodyText()
    expect(text).toContain('أهلاً بيك في طريقي')
    expect(text).toContain('رحلات قريبة')
    expect(text).toContain('تسجيل الدخول')
    expect(text).toContain('حساب جديد')
    expect(bookingsApi.list).not.toHaveBeenCalled()
  })

  it('shows a trip and asks to log in before booking', async () => {
    const { bookingsApi } = await import('@/api')
    await app.visit('/trips/10')
    const submit = [...document.querySelectorAll('button[type="submit"]')].find((b) => b.textContent.includes('سجّل الدخول للحجز'))
    expect(submit).toBeTruthy()

    // happy-dom does not submit a form from a button click.
    submit.closest('form').dispatchEvent(new Event('submit', { cancelable: true }))
    await flushPromises()

    expect(bookingsApi.create).not.toHaveBeenCalled()
    await vi.waitFor(() => expect(app.router.currentRoute.value.name).toBe('login'))
    expect(app.router.currentRoute.value.query.redirect).toBe('/trips/10')
  })

  it('sends private pages to login and offers to keep browsing', async () => {
    await app.visit('/trips/new')
    expect(app.router.currentRoute.value.name).toBe('login')
    expect(bodyText()).toContain('تصفّح الرحلات بدون حساب')
  })
})
