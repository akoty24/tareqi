<script setup>
import { computed, onMounted, ref } from 'vue'
import { adminApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { formatNumber, formatRating } from '@/utils/format'
import LoadingState from '@/components/LoadingState.vue'
import AppIcon from '@/components/AppIcon.vue'

const auth = useAuthStore()
const stats = ref(null)

onMounted(async () => {
  stats.value = (await adminApi.dashboard()).data
})

// Each tile links to a section; tiles of sections the role cannot open are hidden.
const tiles = computed(() => {
  const s = stats.value
  if (!s) return []
  return [
    { label: 'admin.stats.totalUsers', value: s.users.total, sub: s.new_users_this_week, subLabel: 'admin.stats.newThisWeek', to: 'admin-users', perm: 'users.view' },
    { label: 'admin.stats.activeUsers', value: s.users.active, to: 'admin-users', perm: 'users.view' },
    { label: 'admin.stats.blockedUsers', value: s.users.blocked, to: 'admin-users', perm: 'users.view' },
    { label: 'admin.stats.staff', value: s.staff, to: 'admin-roles', perm: 'roles.manage' },
    { label: 'admin.stats.totalTrips', value: s.trips.total, to: 'admin-trips', perm: 'trips.view' },
    { label: 'admin.stats.activeTrips', value: s.trips.active, to: 'admin-trips', perm: 'trips.view' },
    { label: 'admin.stats.completedTrips', value: s.trips.completed, to: 'admin-trips', perm: 'trips.view' },
    { label: 'admin.stats.cancelledTrips', value: s.trips.cancelled, to: 'admin-trips', perm: 'trips.view' },
    { label: 'admin.stats.totalBookings', value: s.bookings.total, to: 'admin-bookings', perm: 'bookings.view' },
    { label: 'admin.stats.pendingBookings', value: s.bookings.pending, to: 'admin-bookings', perm: 'bookings.view' },
    { label: 'admin.stats.tripRequests', value: s.trip_requests.total, sub: s.trip_requests.active, to: 'admin-requests', perm: 'trip_requests.view' },
    { label: 'admin.stats.vehicles', value: s.vehicles, to: 'admin-vehicles', perm: 'vehicles.view' },
    { label: 'admin.stats.ratings', value: s.ratings.total, extra: s.ratings.total ? `★ ${formatRating(s.ratings.average)}` : null, to: 'admin-ratings', perm: 'ratings.view' },
    { label: 'admin.stats.reports', value: s.reports.total, sub: s.reports.pending, to: 'admin-reports', perm: 'reports.view', alert: s.reports.pending > 0 },
  ].filter((tile) => auth.can(tile.perm) || !tile.perm)
})

const shortcuts = [
  { to: 'admin-reports', label: 'admin.shortcuts.reports', icon: 'flag', perm: 'reports.view' },
  { to: 'admin-announcements', label: 'admin.shortcuts.announce', icon: 'megaphone', perm: 'notifications.send' },
  { to: 'admin-roles', label: 'admin.shortcuts.roles', icon: 'lock', perm: 'roles.manage' },
  { to: 'admin-activity', label: 'admin.shortcuts.activity', icon: 'list', perm: 'activity.view' },
]
</script>

<template>
  <div>
    <div class="mb-4">
      <h1 class="page-title !mb-1">{{ $t('admin.dashboard') }}</h1>
      <p class="text-sm text-slate-600">{{ $t('admin.welcome', { name: auth.user?.name, role: auth.user?.role?.display_name }) }}</p>
    </div>

    <div class="mb-5 flex flex-wrap gap-2">
      <template v-for="s in shortcuts" :key="s.to">
        <RouterLink v-if="auth.can(s.perm)" :to="{ name: s.to }" class="btn-secondary btn-sm">
          <AppIcon :name="s.icon" class="size-4" /> {{ $t(s.label) }}
        </RouterLink>
      </template>
    </div>

    <LoadingState v-if="!stats" />
    <div v-else class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
      <RouterLink
        v-for="tile in tiles"
        :key="tile.label"
        :to="{ name: tile.to }"
        class="card hover:ring-brand-200"
        :class="{ '!ring-2 !ring-amber-400': tile.alert }"
      >
        <p class="text-sm text-slate-500">{{ $t(tile.label) }}</p>
        <p class="mt-1 text-3xl font-extrabold">{{ formatNumber(tile.value) }}</p>
        <p v-if="tile.sub !== undefined" class="text-xs text-slate-500">{{ $t(tile.subLabel || 'admin.stats.openCount', { n: formatNumber(tile.sub) }) }}</p>
        <p v-if="tile.extra" class="text-xs font-semibold text-amber-600">{{ tile.extra }}</p>
      </RouterLink>
    </div>
  </div>
</template>
