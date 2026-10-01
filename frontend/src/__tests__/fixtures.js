// Response fixtures shaped exactly like the Laravel API Resources.
import { vi } from 'vitest'

export const meta = { current_page: 1, last_page: 2, per_page: 15, total: 20, from: 1, to: 15 }
export const ok = (data, message = 'تم بنجاح.') => Promise.resolve({ success: true, message, data })
export const page = (data, extraMeta = {}) => Promise.resolve({ success: true, message: '', data, meta: { ...meta, ...extraMeta } })

export const ALL_PERMISSIONS = [
  'dashboard.view', 'users.view', 'users.update', 'users.block', 'roles.manage', 'trips.view', 'trips.manage',
  'bookings.view', 'bookings.manage', 'trip_requests.view', 'trip_requests.manage', 'vehicles.view', 'vehicles.manage',
  'ratings.view', 'ratings.manage', 'reports.view', 'reports.manage', 'notifications.send', 'activity.view',
]

export const superRole = {
  id: 1, name: 'super-admin', display_name: 'مدير عام', description: 'صلاحيات كاملة على المنصة.',
  is_system: true, is_super: true, permissions: ALL_PERMISSIONS, users_count: 1, created_at: '2026-09-01T10:00:00+03:00',
}
export const moderatorRole = {
  id: 2, name: 'moderator', display_name: 'مشرف', description: 'متابعة البلاغات.', is_system: false, is_super: false,
  permissions: ['dashboard.view', 'reports.view', 'reports.manage'], users_count: 0, created_at: '2026-09-01T10:00:00+03:00',
}

export const admin = {
  id: 1, name: 'مدير المنصة', phone: '01000000000', email: 'admin@mishwar.test', email_verified: false,
  profile_photo_url: null, role: superRole, permissions: ALL_PERMISSIONS, notification_settings: { email: true, push: true },
  status: 'active', blocked_at: null, rating_average: 4.5, ratings_count: 2,
  completed_trips_as_owner: 3, completed_trips_as_passenger: 1, created_at: '2026-09-01T10:00:00+03:00',
}

/** A regular community member (no staff role). */
export const member = (overrides = {}) => ({ ...admin, id: 7, name: 'مستخدم عادي', role: null, permissions: [], ...overrides })

/** A staff member limited to a role's permissions. */
export const staffUser = (role = moderatorRole) => ({ ...admin, id: 8, name: 'مشرف المنصة', role, permissions: role.permissions })

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
  reporter: admin, reported_user: member({ name: 'مستخدم مُبلَّغ عنه' }), trip: null,
  admin_notes: null, reviewer: null, reviewed_at: null, created_at: '2026-09-29T10:00:00+03:00',
}

export const stats = {
  users: { total: 16, active: 15, blocked: 1 },
  trips: { total: 40, active: 28, completed: 10, cancelled: 1, draft: 1 },
  bookings: { total: 56, pending: 7, confirmed: 30, completed: 19 },
  trip_requests: { total: 12, active: 9 },
  reports: { total: 5, pending: 3 },
  staff: 3,
  vehicles: 9,
  ratings: { total: 20, average: 4.4 },
  new_users_this_week: 4,
}

export const permissionGroups = [
  { key: 'users', label: 'المستخدمون', permissions: [{ value: 'users.view', label: 'عرض المستخدمين وبياناتهم' }, { value: 'users.block', label: 'إيقاف وتفعيل الحسابات' }] },
  { key: 'reports', label: 'البلاغات', permissions: [{ value: 'reports.view', label: 'عرض البلاغات' }, { value: 'reports.manage', label: 'مراجعة البلاغات' }] },
]

export const announcement = {
  id: 1, title: 'أهلاً بيكم في طريقي', message: 'اعرض رحلتك أو اطلب مشوار.', link: '/search', audience: 'all', user_ids: [],
  send_email: false, recipients_count: 15, sender: publicUser, sent_at: '2026-09-30T09:00:00+03:00', created_at: '2026-09-30T09:00:00+03:00',
}

export const activityLog = {
  id: 1, action: 'user.blocked', description: 'إيقاف حساب حساب موقوف', subject_type: 'User', subject_id: 4,
  properties: { name: 'حساب موقوف' }, causer: publicUser, ip_address: '127.0.0.1', created_at: '2026-09-30T09:00:00+03:00',
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
      updateNotificationSettings: vi.fn((d) => ok({ ...user, notification_settings: { email: true, push: true, ...d } }, 'تم حفظ إعدادات الإشعارات.')),
    },
    devicesApi: { register: vi.fn(() => ok(null)), unregister: vi.fn(() => ok(null)) },
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
      unreadCount: vi.fn(() => ok({ unread_count: 2 })),
      remove: vi.fn(() => ok(null, 'تم حذف الإشعار.')),
      clearRead: vi.fn(() => ok(null, 'تم حذف الإشعارات المقروءة.')),
    },
    reportsApi: { create: vi.fn(() => ok(report)) },
    adminApi: {
      dashboard: vi.fn(() => ok(stats)),
      users: vi.fn(() => page([admin, member()])),
      user: vi.fn(() => ok({
        user: member(), vehicles: [vehicle],
        stats: { trips: 4, bookings: 6, trip_requests: 1, ratings_given: 3, ratings_received: 2, reports_against: 1, active_sessions: 2, devices: 1 },
      })),
      updateUser: vi.fn((id, d) => ok(member(d), 'تم تحديث بيانات المستخدم.')),
      blockUser: vi.fn(() => ok(member({ status: 'blocked' }), 'تم إيقاف المستخدم.')),
      unblockUser: vi.fn(() => ok(member())),
      assignRole: vi.fn((id, roleId) => ok(member({ role: roleId ? moderatorRole : null }), 'تم تحديث دور المستخدم.')),
      revokeSessions: vi.fn(() => ok(null)),
      roles: vi.fn(() => ok([superRole, moderatorRole])),
      permissions: vi.fn(() => ok(permissionGroups)),
      createRole: vi.fn((d) => ok({ ...moderatorRole, id: 3, ...d }, 'تم إنشاء الدور.')),
      updateRole: vi.fn((id, d) => ok({ ...moderatorRole, ...d }, 'تم تحديث الدور.')),
      deleteRole: vi.fn(() => ok(null, 'تم حذف الدور.')),
      cancelBooking: vi.fn(() => ok(booking({ status: 'cancelled' }))),
      cancelTripRequest: vi.fn(() => ok({ ...tripRequest, status: 'cancelled' })),
      vehicles: vi.fn(() => page([{ ...vehicle, owner: publicUser, deleted_at: null, created_at: '2026-09-01T10:00:00+03:00' }])),
      deleteVehicle: vi.fn(() => ok(null)),
      ratings: vi.fn(() => page([rating])),
      deleteRating: vi.fn(() => ok(null)),
      announcements: vi.fn(() => page([announcement])),
      sendAnnouncement: vi.fn((d) => ok({ ...announcement, ...d }, 'جارٍ إرسال الإشعار إلى 15 مستخدم.')),
      activity: vi.fn(() => page([activityLog])),
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
