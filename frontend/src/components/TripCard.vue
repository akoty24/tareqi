<script setup>
import AppIcon from './AppIcon.vue'
import StatusBadge from './StatusBadge.vue'
import UserAvatar from './UserAvatar.vue'
import { formatDate, formatTime, tripPriceLabel, formatNumber } from '@/utils/format'

defineProps({
  trip: { type: Object, required: true },
  showStatus: { type: Boolean, default: false },
  showOwner: { type: Boolean, default: true },
})
</script>

<template>
  <RouterLink
    :to="{ name: 'trip', params: { id: trip.id } }"
    class="card block transition hover:shadow-md hover:ring-brand-200"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="flex flex-wrap items-center gap-x-2 text-lg font-extrabold text-slate-900">
          <span>{{ trip.origin }}</span>
          <AppIcon name="arrow" class="size-5 shrink-0 text-brand-600 ltr:rotate-180" />
          <span>{{ trip.destination }}</span>
        </p>
        <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600">
          <span class="inline-flex items-center gap-1"><AppIcon name="calendar" class="size-4" />{{ formatDate(trip.departure_date) }}</span>
          <span class="inline-flex items-center gap-1"><AppIcon name="clock" class="size-4" />{{ formatTime(trip.departure_time) }}</span>
        </p>
      </div>
      <div class="shrink-0 text-end">
        <StatusBadge v-if="showStatus" kind="trip" :status="trip.status" />
        <span
          v-else-if="trip.match"
          class="inline-block rounded-full bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-800"
          :title="$t('search.matchScore')"
        >{{ $t('search.match', { score: formatNumber(trip.match.score) }) }}</span>
      </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
      <span class="rounded-lg bg-slate-100 px-2 py-1 font-semibold">{{ tripPriceLabel(trip) }}</span>
      <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1">
        <AppIcon name="seat" class="size-4" />
        {{ $t('trip.seatsLeft', { n: formatNumber(trip.available_seats) }, trip.available_seats) }}
      </span>
      <span v-if="trip.is_return_trip" class="inline-flex items-center gap-1 rounded-lg bg-sky-50 px-2 py-1 text-sky-800">
        <AppIcon name="return" class="size-4" /> {{ $t('trip.returnTrip') }}
      </span>
      <span v-if="trip.auto_confirm_bookings" class="rounded-lg bg-emerald-50 px-2 py-1 text-emerald-800">{{ $t('trip.instant') }}</span>
      <span v-if="trip.pending_bookings_count" class="rounded-lg bg-amber-100 px-2 py-1 font-bold text-amber-800">
        {{ $t('trip.pendingRequests', { n: formatNumber(trip.pending_bookings_count) }) }}
      </span>
    </div>

    <div v-if="showOwner && trip.owner" class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3 text-sm">
      <UserAvatar :user="trip.owner" size="sm" />
      <span class="font-semibold">{{ trip.owner.name }}</span>
      <span v-if="trip.owner.ratings_count" class="inline-flex items-center gap-0.5 text-amber-600">
        <AppIcon name="star" class="size-4 fill-current" /> {{ formatNumber(trip.owner.rating_average) }}
      </span>
      <span v-if="trip.vehicle" class="ms-auto truncate text-slate-500">{{ trip.vehicle.model }} · {{ trip.vehicle.color }}</span>
    </div>
  </RouterLink>
</template>
