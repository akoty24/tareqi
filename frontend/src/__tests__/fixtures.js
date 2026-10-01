// Response fixtures shaped exactly like the Laravel API Resources.
import { vi } from 'vitest'

export const meta = { current_page: 1, last_page: 2, per_page: 15, total: 20, from: 1, to: 15 }
export const ok = (data, message = 'تم بنجاح.') => Promise.resolve({ success: true, message, data })
export const page = (data, extraMeta = {}) => Promise.resolve({ success: true, message: '', data, meta: { ...meta, ...extraMeta } })

export const admin = {
  id: 1, name: 'مدير المنصة', phone: '01000000000', email: 'admin@mishwar.test', email_verified: false,
  profile_photo_url: null, role: 'admin', status: 'active', blocked_at: null, rating_average: 4.5, ratings_count: 2,
  completed_trips_as_owner: 3, completed_trips_as_passenger: 1, created_at: '2026-09-01T10:00:00+03:00',
}

export const publicUser = {
  id: 2, name: 'أحمد محمود الشافعي', profile_photo_url: null, rating_average: 4.8, ratings_count: 6,
  completed_trips_as_owner: 5, completed_trips_as_passenger: 0, member_since: '2026-01-01',
}

export const vehicle = { id: 1, vehicle_type: 'sedan', model: 'نيسان صني 2018', color: 'أبيض', plate_number: 'ن ص ر 4521', trips_count: 2 }

const baseTrip = {
  id: 10, origin: 'ميت خاقان', destination: 'شبين الكوم', departure_date: '2026-10-05', departure_time: '07:30',
  departure_at: '2026-10-05T07:30:00+03:00', total_seats: 4, available_seats: 2, booked_seats: 2,
  cost_type: 'fixed_price', price_per_seat: 25, estimated_cost_per_passenger: null, seat_price: 25,
  notes: 'التجمع أمام المسجد الكبير.', auto_confirm_bookings: false, status: 'published', is_return_trip: false,
  parent_trip_id: null, cancellation_reason: null, is_mine: false, owner: publicUser, vehicle,
  created_at: '2026-09-29T10:00:00+03:00',
}

export const trip = (overrides = {}) => ({ ...baseTrip, match: { score: 90, breakdown: {} }, ...overrides })

export const booking = (overrides = {}) => ({
  id: 5, trip_id: 10, seats: 1, status: 'pending', notes: 'هستناك عند المدخل', price_per_seat: 25, total_price: 25,
  confirmed_at: null, cancelled_at: null, cancellation_reason: null, is_mine: true, trip: trip(), passenger: publicUser,
  rated_by_me: false, can_rate: false, created_at: '2026-09-29T10:00:00+03:00', ...overrides,
})

/** Trip as the owner sees it: with bookings and a return trip. */
export const ownedTrip = () => trip({
  is_mine: true, owner_phone: '01000000001', pending_bookings_count: 1, bookings_count: 2, match: undefined,
  return_trip: { ...baseTrip, id: 11, origin: 'شبين الكوم', destination: 'ميت خاقان', departure_time: '17:00', is_return_trip: true, parent_trip_id: 10 },
  parent_trip: null,
  my_booking: null,
  bookings: [
    booking({ id: 5, status: 'pending', trip: undefined }),
    booking({ id: 6, status: 'confirmed', passenger_phone: '01011112222', trip: undefined }),
  ],
})

export const tripRequest = {
  id: 3, origin: 'ميت خاقان', destination: 'الإسكندرية', requested_date: '2026-10-06', preferred_time_from: '07:00',
  preferred_time_to: '10:00', passengers_count: 2, notes: 'رايحة أنا ووالدتي', status: 'active', matched_trips_count: 1,
  user: publicUser, created_at: '2026-09-29T10:00:00+03:00',
}

export const notification = {
  id: '9f1c', type: 'booking_confirmed', title: 'تمت الموافقة على حجزك', message: 'وافق صاحب الرحلة على حجزك.',
  link: '/trips/10', data: { trip_id: 10 }, read_at: null, created_at: '2026-09-30T09:00:00+03:00',
}

export const rating = {
  id: 1, booking_id: 5, stars: 5, review: 'سواق محترم ومواعيده مظبوطة.', rater: publicUser, rated_user: admin,
  trip: { id: 10, origin: 'ميت خاقان', destination: 'شبين الكوم', departure_date: '2026-09-20' }, created_at: '2026-09-21T10:00:00+03:00',
}

export const report = {
  id: 1, reason: 'fraud', description: 'طلب فلوس أكتر من المتفق عليه.', status: 'pending', trip_id: 10, booking_id: null,
  reporter: admin, reported_user: { ...admin, id: 7, name: 'مستخدم مُبلَّغ عنه', role: 'user' }, trip: null,
  admin_notes: null, reviewer: null, reviewed_at: null, created_at: '2026-09-29T10:00:00+03:00',
}

export const stats = {
  users: { total: 16, active: 15, blocked: 1 },
  trips: { total: 40, active: 28, completed: 10, cancelled: 1, draft: 1 },
  bookings: { total: 56, pending: 7, confirmed: 30, completed: 19 },
  trip_requests: { total: 12, active: 9 },
  reports: { total: 5, pending: 3 },
}

/** Builds the vi.mock factory for '@/api' with sensible defaults for every endpoint. */
export function apiMock(user = admin) {
  return {
    authApi: {
      me: vi.fn(() => ok({ user, unread_notifications: 2 })),
      login: vi.fn(() => ok({ user, token: 't' })),
      register: vi.fn(() => ok({ user, token: 't' })),
      logout: vi.fn(() => ok(null)),
      forgotPassword: vi.fn(() => ok(null)),
      resetPassword: vi.fn(() => ok(null)),
      resendVerification: vi.fn(() => ok(null)),
    },
    profileApi: {
      show: vi.fn(() => ok(user)),
      update: vi.fn((d) => ok({ ...user, ...d })),
      updatePassword: vi.fn(() => ok(null)),
      updatePhoto: vi.fn(() => ok(user)),
      publicProfile: vi.fn(() => ok({ user: publicUser, recent_ratings: [rating] })),
    },
    vehiclesApi: {
      list: vi.fn(() => ok([vehicle])),
      create: vi.fn(() => ok(vehicle)),
      update: vi.fn(() => ok(vehicle)),
      remove: vi.fn(() => ok(null)),
    },
    tripsApi: {
      browse: vi.fn(() => page([trip({ match: undefined })])),
      mine: vi.fn(() => page([trip({ is_mine: true, match: undefined, pending_bookings_count: 1 })])),
      search: vi.fn(() => page([trip()])),
      show: vi.fn(() => ok(ownedTrip())),
      create: vi.fn(() => ok(trip({ id: 12 }))),
      update: vi.fn(() => ok(trip())),
      remove: vi.fn(() => ok(null)),
      publish: vi.fn(() => ok(trip())),
      cancel: vi.fn(() => ok(trip({ status: 'cancelled' }), 'تم إلغاء الرحلة.')),
      start: vi.fn(() => ok(trip())),
      complete: vi.fn(() => ok(trip())),
      addReturn: vi.fn(() => ok(trip())),
      places: vi.fn(() => ok(['ميت خاقان', 'شبين الكوم'])),
    },
    bookingsApi: {
      list: vi.fn(() => page([booking({ status: 'completed', can_rate: true, owner_phone: '01000000001' })])),
      show: vi.fn(() => ok(booking())),
      create: vi.fn(() => ok(booking(), 'تم إرسال طلب الحجز.')),
      confirm: vi.fn(() => ok(booking({ status: 'confirmed' }), 'تمت الموافقة على الحجز.')),
      reject: vi.fn(() => ok(booking({ status: 'rejected' }))),
      cancel: vi.fn(() => ok(booking({ status: 'cancelled' }))),
      rate: vi.fn(() => ok(rating)),
    },
    tripRequestsApi: {
      list: vi.fn(() => page([tripRequest])),
      show: vi.fn(() => ok({ trip_request: tripRequest, matching_trips: [trip()] })),
      create: vi.fn(() => ok({ trip_request: tripRequest, matching_trips: [] })),
      update: vi.fn(() => ok(tripRequest)),
      cancel: vi.fn(() => ok({ ...tripRequest, status: 'cancelled' })),
    },
    ratingsApi: { list: vi.fn(() => page([rating])) },
    notificationsApi: {
      list: vi.fn(() => page([notification], { unread_count: 1 })),
      markRead: vi.fn(() => ok(notification)),
      markAllRead: vi.fn(() => ok(null)),
    },
    reportsApi: { create: vi.fn(() => ok(report)) },
    adminApi: {
      dashboard: vi.fn(() => ok(stats)),
      users: vi.fn(() => page([admin, { ...admin, id: 7, role: 'user', name: 'مستخدم عادي' }])),
      blockUser: vi.fn(() => ok(admin)),
      unblockUser: vi.fn(() => ok(admin)),
      trips: vi.fn(() => page([trip({ bookings_count: 2 })])),
      cancelTrip: vi.fn(() => ok(trip())),
      bookings: vi.fn(() => page([booking()])),
      tripRequests: vi.fn(() => page([tripRequest])),
      reports: vi.fn(() => page([report])),
      report: vi.fn(() => ok(report)),
      updateReport: vi.fn(() => ok(report)),
    },
  }
}
