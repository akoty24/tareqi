/**
 * Runtime smoke test: every route of the SPA is rendered through the real
 * router, guards and layouts with a mocked API. A page fails if it throws,
 * emits a Vue warning, logs an error, or uses a missing translation key.
 */
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { ok, ownedTrip, trip } from './fixtures'
import { bodyText, captureProblems, mountApp } from './setup'

vi.mock('@/api', async () => (await import('./fixtures')).apiMock())

let app
let problems

beforeAll(async () => {
  localStorage.setItem('mishwar_token', 'test-token')
  problems = captureProblems()
  app = await mountApp()
})

afterEach(() => {
  expect(app.errors.map(String)).toEqual([])
  expect(problems).toEqual([])
})

const pages = [
  ['/', ['رايح فين؟', 'مسافر بعربيتك؟', 'مش لاقي رحلة مناسبة؟', 'رحلات قريبة']],
  ['/search?origin=ميت خاقان&date=2026-10-05', ['البحث عن رحلة', 'تطابق ٩٠٪', '٢ مقاعد متاحة', '٢٠ رحلات', 'صفحة ١ من ٢']],
  ['/trips/new', ['إضافة رحلة', 'مشاركة تكلفة', 'إضافة رحلة عودة']],
  ['/trips/10', ['طلبات تنتظر موافقتك', 'قبول', 'لهذه الرحلة رحلة عودة', 'إدارة الرحلة', '01011112222']],
  ['/trips/10/edit', ['تعديل الرحلة', 'لا يمكن تغيير المسار']],
  ['/my-trips', ['رحلاتي', 'القادمة', 'طلب في الانتظار']],
  ['/my-bookings', ['حجوزاتي كراكب', 'تمت الرحلة', 'قيّم السواق', '01000000001']],
  ['/requests', ['طلبات الرحلات', 'تم إيجاد', 'إلغاء الطلب']],
  ['/requests/new', ['طلب رحلة جديد', 'إرسال الطلب']],
  ['/requests/3', ['الإسكندرية', 'رحلات مناسبة متاحة الآن']],
  ['/notifications', ['تمت الموافقة على حجزك', 'تعليم الكل كمقروء', 'حذف المقروءة', 'إعدادات الإشعارات']],
  ['/profile', ['البيانات الأساسية', 'تغيير كلمة المرور', 'بريدك الإلكتروني غير مفعّل', 'إشعارات الموبايل', 'English']],
  ['/vehicles', ['سياراتي', 'نيسان صني 2018', 'سيدان']],
  ['/ratings', ['التقييمات', 'سواق محترم']],
  ['/users/2', ['أحمد محمود الشافعي', 'آخر التقييمات', 'الإبلاغ عن هذا المستخدم']],
  ['/admin', ['لوحة التحكم', 'إجمالي المستخدمين', '٣ مفتوح']],
  ['/admin/users', ['المستخدمون', 'مستخدم عادي', 'إيقاف']],
  ['/admin/trips', ['الرحلات', 'ميت خاقان']],
  ['/admin/bookings', ['الحجوزات', 'في الانتظار']],
  ['/admin/requests', ['طلبات الرحلات', 'الإسكندرية']],
  ['/admin/reports', ['البلاغات', 'احتيال أو طلب فلوس زيادة', 'مراجعة']],
  ['/admin/users/7', ['مستخدم عادي', 'بيانات الحساب', 'دور الإدارة', 'بلاغات ضده', 'نيسان صني 2018']],
  ['/admin/roles', ['الأدوار والصلاحيات', 'مدير عام', 'كل الصلاحيات', 'مشرف', '٣ من ٤ صلاحية', 'لا يوجد أعضاء']],
  ['/admin/vehicles', ['السيارات', 'نيسان صني 2018', 'أحمد محمود الشافعي']],
  ['/admin/ratings', ['التقييمات', 'سواق محترم']],
  ['/admin/announcements', ['الإشعارات الجماعية', 'كل المستخدمين', 'أهلاً بيكم في مشوار', '١٥ مستلم']],
  ['/admin/activity', ['سجل النشاط', 'إيقاف حساب حساب موقوف']],
  ['/no-such-page', ['الصفحة غير موجودة']],
]

// Runs first: later pages (notifications) legitimately update the unread counter.
describe('layout', () => {
  it('shows the unread notifications badge from /auth/me', async () => {
    await app.visit('/')
    expect(document.querySelector('a[href="/notifications"]').textContent).toContain('2')
  })

  it('renders RTL Arabic by default', () => {
    expect(document.documentElement.dir).toBe('rtl')
    expect(document.documentElement.lang).toBe('ar')
  })
})

describe('every page renders cleanly', () => {
  it.each(pages)('%s', async (path, expectedTexts) => {
    await app.visit(path)
    const text = bodyText()
    for (const expected of expectedTexts) {
      expect(text, `"${expected}" on ${path}`).toContain(expected)
    }
  })
})

describe('interactions', () => {
  it('owner approves a pending booking and the trip reloads', async () => {
    const { bookingsApi, tripsApi } = await import('@/api')
    await app.visit('/trips/10')
    tripsApi.show.mockClear()

    const accept = [...document.querySelectorAll('button')].find((b) => b.textContent.includes('قبول'))
    accept.click()
    await flushPromises()

    expect(bookingsApi.confirm).toHaveBeenCalledWith(5)
    expect(tripsApi.show).toHaveBeenCalledTimes(1)
    expect(bodyText()).toContain('تمت الموافقة على الحجز.')
  })

  it('owner cancels a trip through the confirmation dialog with a reason', async () => {
    const { tripsApi } = await import('@/api')
    await app.visit('/trips/10')

    const cancel = [...document.querySelectorAll('button')].find((b) => b.textContent.trim() === 'إلغاء الرحلة')
    cancel.click()
    await flushPromises()

    const dialog = document.querySelector('[role="dialog"]')
    expect(dialog.textContent).toContain('سيتم إلغاء كل الحجوزات')
    const input = dialog.querySelector('#prompt-reason')
    input.value = 'ظرف طارئ'
    input.dispatchEvent(new Event('input'))
    ;[...dialog.querySelectorAll('button')].find((b) => b.textContent.trim() === 'إلغاء الرحلة').click()
    await flushPromises()

    expect(tripsApi.cancel).toHaveBeenCalledWith(10, 'ظرف طارئ')
    expect(document.querySelector('[role="dialog"]')).toBeNull()
  })

  it('passenger books seats on someone else\'s trip', async () => {
    const { tripsApi, bookingsApi } = await import('@/api')
    tripsApi.show.mockImplementation(() => ok(trip({ match: undefined, is_mine: false, my_booking: null })))
    await app.visit('/trips/99')

    const select = document.querySelector('#book-seats')
    select.value = '2'
    select.dispatchEvent(new Event('change'))
    await flushPromises()
    expect(bodyText()).toContain('٥٠') // total = 2 × 25 EGP

    document.querySelector('#book-seats').closest('form').dispatchEvent(new Event('submit'))
    await flushPromises()
    expect(bookingsApi.create).toHaveBeenCalledWith(10, { seats: 2, notes: null })
    tripsApi.show.mockImplementation(() => ok(ownedTrip()))
  })

  it('search form writes filters to the URL and queries the API', async () => {
    const { tripsApi } = await import('@/api')
    await app.visit('/search')
    tripsApi.search.mockClear()

    const date = document.querySelector('#s-date')
    date.value = '2026-10-07'
    date.dispatchEvent(new Event('input'))
    document.querySelector('#s-date').closest('form').dispatchEvent(new Event('submit'))
    await flushPromises()
    await flushPromises()

    expect(app.router.currentRoute.value.query.date).toBe('2026-10-07')
    expect(tripsApi.search).toHaveBeenCalledWith(expect.objectContaining({ date: '2026-10-07', passengers: '1', page: 1 }))
  })

  it('logout clears the session and returns to login', async () => {
    const { authApi } = await import('@/api')
    await app.visit('/')
    document.querySelector('button[aria-haspopup="menu"]').click()
    await flushPromises()
    ;[...document.querySelectorAll('[role="menuitem"]')].find((el) => el.textContent.includes('تسجيل الخروج')).click()
    // The login page is lazy-loaded, so wait for the navigation to finish.
    await vi.waitFor(() => expect(app.router.currentRoute.value.name).toBe('login'))

    expect(authApi.logout).toHaveBeenCalled()
    expect(localStorage.getItem('mishwar_token')).toBeNull()
    expect(bodyText()).toContain('البريد الإلكتروني أو رقم الموبايل')
  })
})
