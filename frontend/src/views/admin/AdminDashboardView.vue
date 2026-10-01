<script setup>
import { onMounted, ref } from 'vue'
import { adminApi } from '@/api'
import { formatNumber } from '@/utils/format'
import LoadingState from '@/components/LoadingState.vue'

const stats = ref(null)

onMounted(async () => {
  stats.value = (await adminApi.dashboard()).data
})

const tiles = (s) => [
  { label: 'admin.stats.totalUsers', value: s.users.total, to: 'admin-users' },
  { label: 'admin.stats.activeUsers', value: s.users.active, to: 'admin-users' },
  { label: 'admin.stats.totalTrips', value: s.trips.total, to: 'admin-trips' },
  { label: 'admin.stats.activeTrips', value: s.trips.active, to: 'admin-trips' },
  { label: 'admin.stats.completedTrips', value: s.trips.completed, to: 'admin-trips' },
  { label: 'admin.stats.cancelledTrips', value: s.trips.cancelled, to: 'admin-trips' },
  { label: 'admin.stats.totalBookings', value: s.bookings.total, to: 'admin-bookings' },
  { label: 'admin.stats.pendingBookings', value: s.bookings.pending, to: 'admin-bookings' },
  { label: 'admin.stats.tripRequests', value: s.trip_requests.total, sub: s.trip_requests.active, to: 'admin-requests' },
  { label: 'admin.stats.reports', value: s.reports.total, sub: s.reports.pending, to: 'admin-reports', alert: s.reports.pending > 0 },
]
</script>

<template>
  <div>
    <h1 class="page-title">{{ $t('admin.dashboard') }}</h1>
    <LoadingState v-if="!stats" />
    <div v-else class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
      <RouterLink
        v-for="tile in tiles(stats)"
        :key="tile.label"
        :to="{ name: tile.to }"
        class="card hover:ring-brand-200"
        :class="{ '!ring-2 !ring-amber-400': tile.alert }"
      >
        <p class="text-sm text-slate-500">{{ $t(tile.label) }}</p>
        <p class="mt-1 text-3xl font-extrabold">{{ formatNumber(tile.value) }}</p>
        <p v-if="tile.sub !== undefined" class="text-xs text-slate-500">{{ $t('admin.stats.openCount', { n: formatNumber(tile.sub) }) }}</p>
      </RouterLink>
    </div>
  </div>
</template>
