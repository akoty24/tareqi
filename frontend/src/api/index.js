import client from './client'

// Thin, typed-by-convention wrappers over the REST API.
// Every call resolves to the backend envelope: { success, message, data, meta? }.

export const authApi = {
  register: (payload) => client.post('/auth/register', payload),
  login: (payload) => client.post('/auth/login', payload),
  logout: () => client.post('/auth/logout'),
  me: () => client.get('/auth/me'),
  forgotPassword: (email) => client.post('/auth/forgot-password', { email }),
  resetPassword: (payload) => client.post('/auth/reset-password', payload),
  resendVerification: () => client.post('/auth/email/verification-notification'),
}

export const profileApi = {
  show: () => client.get('/profile'),
  update: (payload) => client.put('/profile', payload),
  updatePassword: (payload) => client.put('/profile/password', payload),
  updatePhoto: (file) => {
    const form = new FormData()
    form.append('photo', file)
    return client.post('/profile/photo', form)
  },
  publicProfile: (id) => client.get(`/users/${id}`),
  updateNotificationSettings: (payload) => client.put('/profile/notification-settings', payload),
}

export const vehiclesApi = {
  list: () => client.get('/vehicles'),
  create: (payload) => client.post('/vehicles', payload),
  update: (id, payload) => client.put(`/vehicles/${id}`, payload),
  remove: (id) => client.delete(`/vehicles/${id}`),
}

export const tripsApi = {
  browse: (params) => client.get('/trips', { params }),
  mine: (params) => client.get('/trips', { params: { ...params, mine: 1 } }),
  search: (params) => client.get('/trips/search', { params }),
  show: (id) => client.get(`/trips/${id}`),
  create: (payload) => client.post('/trips', payload),
  update: (id, payload) => client.put(`/trips/${id}`, payload),
  remove: (id) => client.delete(`/trips/${id}`),
  publish: (id) => client.patch(`/trips/${id}/publish`),
  cancel: (id, reason) => client.patch(`/trips/${id}/cancel`, { reason }),
  start: (id) => client.patch(`/trips/${id}/start`),
  complete: (id) => client.patch(`/trips/${id}/complete`),
  addReturn: (id, payload) => client.post(`/trips/${id}/return-trip`, payload),
  places: (q) => client.get('/places', { params: { q } }),
}

export const bookingsApi = {
  list: (params) => client.get('/bookings', { params }),
  show: (id) => client.get(`/bookings/${id}`),
  create: (tripId, payload) => client.post(`/trips/${tripId}/bookings`, payload),
  confirm: (id) => client.patch(`/bookings/${id}/confirm`),
  reject: (id, reason) => client.patch(`/bookings/${id}/reject`, { reason }),
  cancel: (id, reason) => client.patch(`/bookings/${id}/cancel`, { reason }),
  rate: (id, payload) => client.post(`/bookings/${id}/rating`, payload),
}

export const tripRequestsApi = {
  list: (params) => client.get('/trip-requests', { params }),
  show: (id) => client.get(`/trip-requests/${id}`),
  create: (payload) => client.post('/trip-requests', payload),
  update: (id, payload) => client.put(`/trip-requests/${id}`, payload),
  cancel: (id) => client.delete(`/trip-requests/${id}`),
}

export const ratingsApi = {
  list: (type, page = 1) => client.get('/ratings', { params: { type, page } }),
}

export const notificationsApi = {
  list: (params) => client.get('/notifications', { params }),
  unreadCount: () => client.get('/notifications/unread-count'),
  markRead: (id) => client.patch(`/notifications/${id}/read`),
  markAllRead: () => client.patch('/notifications/read-all'),
  remove: (id) => client.delete(`/notifications/${id}`),
  clearRead: () => client.delete('/notifications/read'),
}

// Push notification registration of the mobile app (FCM token).
export const devicesApi = {
  register: (token, platform) => client.post('/devices', { token, platform }),
  unregister: (token) => client.delete('/devices', { data: { token } }),
}

export const reportsApi = {
  create: (payload) => client.post('/reports', payload),
}

export const adminApi = {
  dashboard: () => client.get('/admin/dashboard'),
  // Users & roles
  users: (params) => client.get('/admin/users', { params }),
  user: (id) => client.get(`/admin/users/${id}`),
  updateUser: (id, payload) => client.put(`/admin/users/${id}`, payload),
  blockUser: (id) => client.patch(`/admin/users/${id}/block`),
  unblockUser: (id) => client.patch(`/admin/users/${id}/unblock`),
  assignRole: (id, roleId) => client.patch(`/admin/users/${id}/role`, { role_id: roleId }),
  revokeSessions: (id) => client.delete(`/admin/users/${id}/sessions`),
  roles: () => client.get('/admin/roles'),
  permissions: () => client.get('/admin/permissions'),
  createRole: (payload) => client.post('/admin/roles', payload),
  updateRole: (id, payload) => client.put(`/admin/roles/${id}`, payload),
  deleteRole: (id) => client.delete(`/admin/roles/${id}`),
  // Content
  trips: (params) => client.get('/admin/trips', { params }),
  cancelTrip: (id, reason) => client.patch(`/admin/trips/${id}/cancel`, { reason }),
  bookings: (params) => client.get('/admin/bookings', { params }),
  cancelBooking: (id, reason) => client.patch(`/admin/bookings/${id}/cancel`, { reason }),
  tripRequests: (params) => client.get('/admin/trip-requests', { params }),
  cancelTripRequest: (id) => client.patch(`/admin/trip-requests/${id}/cancel`),
  vehicles: (params) => client.get('/admin/vehicles', { params }),
  deleteVehicle: (id) => client.delete(`/admin/vehicles/${id}`),
  ratings: (params) => client.get('/admin/ratings', { params }),
  deleteRating: (id) => client.delete(`/admin/ratings/${id}`),
  reports: (params) => client.get('/admin/reports', { params }),
  report: (id) => client.get(`/admin/reports/${id}`),
  updateReport: (id, payload) => client.patch(`/admin/reports/${id}`, payload),
  // Communication & audit
  announcements: (params) => client.get('/admin/announcements', { params }),
  sendAnnouncement: (payload) => client.post('/admin/announcements', payload),
  activity: (params) => client.get('/admin/activity', { params }),
}
