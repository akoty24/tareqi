<script setup>
import AppIcon from './AppIcon.vue'
import StatusBadge from './StatusBadge.vue'
import UserAvatar from './UserAvatar.vue'
import { formatDate, formatTime, tripPriceLabel, formatNumber, formatRating } from '@/utils/format'

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
          v-else-if="trip.match && trip.match.score < 100"
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

    <div v-if="showOwner && trip.owner" class="mt-3 flex items-center gap-3 border-t border-slate-100 pt-3 text-sm">
      <UserAvatar :user="trip.owner" size="sm" />
      <div class="min-w-0 flex-1">
        <p class="flex items-center gap-2">
          <span class="truncate font-semibold">{{ trip.owner.name }}</span>
          <span v-if="trip.owner.ratings_count" class="inline-flex shrink-0 items-center gap-0.5 text-amber-600">
            <AppIcon name="star" class="size-4 fill-current" /> {{ formatRating(trip.owner.rating_average) }}
          </span>
          <span v-else class="shrink-0 text-xs text-slate-400">{{ $t('rating.new') }}</span>
        </p>
        <p v-if="trip.vehicle" class="truncate text-xs text-slate-500">
          <AppIcon name="car" class="inline size-3.5" /> {{ trip.vehicle.model }} · {{ trip.vehicle.color }}
        </p>
      </div>
    </div>
  </RouterLink>
</template>
