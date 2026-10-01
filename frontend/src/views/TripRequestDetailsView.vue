<script setup>
import { onMounted, ref } from 'vue'
import { tripRequestsApi } from '@/api'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatTime, formatNumber } from '@/utils/format'
import TripCard from '@/components/TripCard.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'

const props = defineProps({ id: { type: String, required: true } })
const toast = useToastStore()
const request = ref(null)
const trips = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await tripRequestsApi.show(props.id)
    request.value = data.trip_request
    trips.value = data.matching_trips
  } catch (e) {
    toast.error(e.message)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <LoadingState v-if="loading" />
  <div v-else-if="request" class="space-y-5">
    <section class="card">
      <div class="flex flex-wrap items-start justify-between gap-2">
        <h1 class="text-2xl font-extrabold">{{ request.origin }} ← {{ request.destination }}</h1>
        <StatusBadge kind="request" :status="request.status" />
      </div>
      <p class="mt-1 text-slate-600">
        {{ formatDate(request.requested_date) }}
        <template v-if="request.preferred_time_from"> · {{ formatTime(request.preferred_time_from) }} – {{ formatTime(request.preferred_time_to) }}</template>
        · {{ $t('request.passengersCount', { n: formatNumber(request.passengers_count) }, request.passengers_count) }}
      </p>
      <p v-if="request.notes" class="mt-2 text-slate-700">{{ request.notes }}</p>
      <p v-if="request.status === 'active'" class="mt-3 rounded-xl bg-brand-50 p-3 text-sm text-brand-900">{{ $t('request.watching') }}</p>
    </section>

    <section>
      <h2 class="mb-3 text-xl font-extrabold">{{ $t('request.matchingTrips') }}</h2>
      <div v-if="trips.length" class="grid gap-3 md:grid-cols-2">
        <TripCard v-for="trip in trips" :key="trip.id" :trip="trip" />
      </div>
      <EmptyState v-else icon="search" :title="$t('request.noMatchesYet')" :text="$t('request.noMatchesHint')" />
    </section>
  </div>
</template>
