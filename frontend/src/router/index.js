import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { i18n } from '@/i18n'

const AppLayout = () => import('@/layouts/AppLayout.vue')
const AuthLayout = () => import('@/layouts/AuthLayout.vue')
const AdminLayout = () => import('@/layouts/AdminLayout.vue')

const routes = [
  {
    path: '/',
    component: AuthLayout,
    meta: { guest: true },
    children: [
      { path: 'login', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { title: 'auth.login' } },
      { path: 'register', name: 'register', component: () => import('@/views/auth/RegisterView.vue'), meta: { title: 'auth.register' } },
      { path: 'forgot-password', name: 'forgot-password', component: () => import('@/views/auth/ForgotPasswordView.vue'), meta: { title: 'auth.forgotPassword' } },
      { path: 'reset-password', name: 'reset-password', component: () => import('@/views/auth/ResetPasswordView.vue'), meta: { title: 'auth.resetPassword' } },
    ],
  },
  {
    path: '/',
    component: AppLayout,
    meta: { auth: true },
    children: [
      { path: '', name: 'home', component: () => import('@/views/HomeView.vue'), meta: { title: 'nav.home' } },
      { path: 'search', name: 'search', component: () => import('@/views/SearchView.vue'), meta: { title: 'nav.search' } },
      { path: 'trips/new', name: 'trip-create', component: () => import('@/views/TripFormView.vue'), meta: { title: 'trip.create' } },
      { path: 'trips/:id', name: 'trip', component: () => import('@/views/TripDetailsView.vue'), props: true, meta: { title: 'trip.details' } },
      { path: 'trips/:id/edit', name: 'trip-edit', component: () => import('@/views/TripFormView.vue'), props: true, meta: { title: 'trip.edit' } },
      { path: 'my-trips', name: 'my-trips', component: () => import('@/views/MyTripsView.vue'), meta: { title: 'nav.myTrips' } },
      { path: 'my-bookings', name: 'my-bookings', component: () => import('@/views/MyBookingsView.vue'), meta: { title: 'nav.myBookings' } },
      { path: 'requests', name: 'requests', component: () => import('@/views/TripRequestsView.vue'), meta: { title: 'nav.requests' } },
      { path: 'requests/new', name: 'request-create', component: () => import('@/views/TripRequestFormView.vue'), meta: { title: 'request.create' } },
      { path: 'requests/:id', name: 'request', component: () => import('@/views/TripRequestDetailsView.vue'), props: true, meta: { title: 'request.details' } },
      { path: 'notifications', name: 'notifications', component: () => import('@/views/NotificationsView.vue'), meta: { title: 'nav.notifications' } },
      { path: 'profile', name: 'profile', component: () => import('@/views/ProfileView.vue'), meta: { title: 'nav.profile' } },
      { path: 'vehicles', name: 'vehicles', component: () => import('@/views/VehiclesView.vue'), meta: { title: 'nav.vehicles' } },
      { path: 'ratings', name: 'ratings', component: () => import('@/views/RatingsView.vue'), meta: { title: 'nav.ratings' } },
      { path: 'users/:id', name: 'user', component: () => import('@/views/UserProfileView.vue'), props: true, meta: { title: 'nav.profile' } },
    ],
  },
  {
    path: '/admin',
    component: AdminLayout,
    meta: { auth: true, admin: true },
    children: [
      { path: '', name: 'admin', component: () => import('@/views/admin/AdminDashboardView.vue'), meta: { title: 'admin.dashboard' } },
      { path: 'users', name: 'admin-users', component: () => import('@/views/admin/AdminUsersView.vue'), meta: { title: 'admin.users' } },
      { path: 'trips', name: 'admin-trips', component: () => import('@/views/admin/AdminTripsView.vue'), meta: { title: 'admin.trips' } },
      { path: 'bookings', name: 'admin-bookings', component: () => import('@/views/admin/AdminBookingsView.vue'), meta: { title: 'admin.bookings' } },
      { path: 'requests', name: 'admin-requests', component: () => import('@/views/admin/AdminRequestsView.vue'), meta: { title: 'admin.requests' } },
      { path: 'reports', name: 'admin-reports', component: () => import('@/views/admin/AdminReportsView.vue'), meta: { title: 'admin.reports' } },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue') },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: (to, from, saved) => saved || { top: 0 },
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.init()

  if (to.matched.some((r) => r.meta.auth) && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.matched.some((r) => r.meta.guest) && auth.isAuthenticated) {
    return { name: 'home' }
  }
  if (to.matched.some((r) => r.meta.admin) && !auth.isAdmin) {
    return { name: 'home' }
  }
})

router.afterEach((to) => {
  const appName = i18n.global.t('app.name')
  document.title = to.meta.title ? `${i18n.global.t(to.meta.title)} · ${appName}` : appName
})

export default router
