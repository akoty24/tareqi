import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { i18n } from '@/i18n'
import { adminSections, firstAllowedSection } from './adminSections'

const AppLayout = () => import('@/layouts/AppLayout.vue')
const AuthLayout = () => import('@/layouts/AuthLayout.vue')
const AdminLayout = () => import('@/layouts/AdminLayout.vue')

const routes = [
  // Main app. Home, search, trip details and public profiles are open to
  // guests; every other page (meta.auth) sends them to the login page first.
  {
    path: '/',
    component: AppLayout,
    children: [
      { path: '', name: 'home', component: () => import('@/views/HomeView.vue'), meta: { title: 'nav.home' } },
      { path: 'search', name: 'search', component: () => import('@/views/SearchView.vue'), meta: { title: 'nav.search' } },
      { path: 'trips/new', name: 'trip-create', component: () => import('@/views/TripFormView.vue'), meta: { auth: true, title: 'trip.create' } },
      { path: 'trips/:id', name: 'trip', component: () => import('@/views/TripDetailsView.vue'), props: true, meta: { title: 'trip.details' } },
      { path: 'trips/:id/edit', name: 'trip-edit', component: () => import('@/views/TripFormView.vue'), props: true, meta: { auth: true, title: 'trip.edit' } },
      { path: 'my-trips', name: 'my-trips', component: () => import('@/views/MyTripsView.vue'), meta: { auth: true, title: 'nav.myTrips' } },
      { path: 'my-bookings', name: 'my-bookings', component: () => import('@/views/MyBookingsView.vue'), meta: { auth: true, title: 'nav.myBookings' } },
      { path: 'requests', name: 'requests', component: () => import('@/views/TripRequestsView.vue'), meta: { auth: true, title: 'nav.requests' } },
      { path: 'requests/new', name: 'request-create', component: () => import('@/views/TripRequestFormView.vue'), meta: { auth: true, title: 'request.create' } },
      { path: 'requests/:id', name: 'request', component: () => import('@/views/TripRequestDetailsView.vue'), props: true, meta: { auth: true, title: 'request.details' } },
      { path: 'notifications', name: 'notifications', component: () => import('@/views/NotificationsView.vue'), meta: { auth: true, title: 'nav.notifications' } },
      { path: 'profile', name: 'profile', component: () => import('@/views/ProfileView.vue'), meta: { auth: true, title: 'nav.profile' } },
      { path: 'vehicles', name: 'vehicles', component: () => import('@/views/VehiclesView.vue'), meta: { auth: true, title: 'nav.vehicles' } },
      { path: 'ratings', name: 'ratings', component: () => import('@/views/RatingsView.vue'), meta: { auth: true, title: 'nav.ratings' } },
      { path: 'users/:id', name: 'user', component: () => import('@/views/UserProfileView.vue'), props: true, meta: { title: 'nav.profile' } },
    ],
  },
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
    path: '/admin',
    component: AdminLayout,
    meta: { auth: true, admin: true },
    children: [
      ...adminSections.map((s) => ({ path: s.path, name: s.name, component: s.component, meta: { title: s.label, permission: s.permission } })),
      { path: 'users/:id', name: 'admin-user', component: () => import('@/views/admin/AdminUserView.vue'), props: true, meta: { title: 'admin.userDetails', permission: 'users.view' } },
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
  // Staff without the section's permission land on the first section they may open.
  if (to.meta.permission && !auth.can(to.meta.permission)) {
    const fallback = firstAllowedSection(auth.can)
    return fallback && fallback.name !== to.name ? { name: fallback.name } : { name: 'home' }
  }
})

router.afterEach((to) => {
  const appName = i18n.global.t('app.name')
  document.title = to.meta.title ? `${i18n.global.t(to.meta.title)} · ${appName}` : appName
})

export default router
