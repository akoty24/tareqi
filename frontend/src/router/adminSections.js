// Admin panel sections. Each one requires a permission (same names as the
// backend `can:` middleware); the layout hides what the role cannot open.
export const adminSections = [
  { name: 'admin', path: '', label: 'admin.dashboard', icon: 'chart', permission: 'dashboard.view', component: () => import('@/views/admin/AdminDashboardView.vue') },
  { name: 'admin-users', path: 'users', label: 'admin.users', icon: 'user', permission: 'users.view', component: () => import('@/views/admin/AdminUsersView.vue') },
  { name: 'admin-roles', path: 'roles', label: 'admin.rolesNav', icon: 'lock', permission: 'roles.manage', component: () => import('@/views/admin/AdminRolesView.vue') },
  { name: 'admin-trips', path: 'trips', label: 'admin.trips', icon: 'pin', permission: 'trips.view', component: () => import('@/views/admin/AdminTripsView.vue') },
  { name: 'admin-bookings', path: 'bookings', label: 'admin.bookings', icon: 'ticket', permission: 'bookings.view', component: () => import('@/views/admin/AdminBookingsView.vue') },
  { name: 'admin-requests', path: 'requests', label: 'admin.requests', icon: 'hand', permission: 'trip_requests.view', component: () => import('@/views/admin/AdminRequestsView.vue') },
  { name: 'admin-vehicles', path: 'vehicles', label: 'admin.vehicles', icon: 'car', permission: 'vehicles.view', component: () => import('@/views/admin/AdminVehiclesView.vue') },
  { name: 'admin-ratings', path: 'ratings', label: 'admin.ratings', icon: 'star', permission: 'ratings.view', component: () => import('@/views/admin/AdminRatingsView.vue') },
  { name: 'admin-reports', path: 'reports', label: 'admin.reports', icon: 'flag', permission: 'reports.view', component: () => import('@/views/admin/AdminReportsView.vue') },
  { name: 'admin-announcements', path: 'announcements', label: 'admin.announcements', icon: 'megaphone', permission: 'notifications.send', component: () => import('@/views/admin/AdminAnnouncementsView.vue') },
  { name: 'admin-activity', path: 'activity', label: 'admin.activity', icon: 'list', permission: 'activity.view', component: () => import('@/views/admin/AdminActivityView.vue') },
]

/** First section the current staff member may open (null if none). */
export function firstAllowedSection(can) {
  return adminSections.find((s) => can(s.permission)) ?? null
}
