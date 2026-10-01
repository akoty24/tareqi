<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { tripsApi, bookingsApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { todayISO } from '@/utils/format'
import PlaceInput from '@/components/PlaceInput.vue'
import TripCard from '@/components/TripCard.vue'
import AppIcon from '@/components/AppIcon.vue'

const router = useRouter()
const auth = useAuthStore()
const form = reactive({ origin: '', destination: '', date: todayISO(1) })
const upcoming = ref([])
const nextBooking = ref(null)

function swap() {
  ;[form.origin, form.destination] = [form.destination, form.origin]
}

function search() {
  router.push({ name: 'search', query: Object.fromEntries(Object.entries(form).filter(([, v]) => v)) })
}

onMounted(async () => {
  try {
    const [trips, bookings] = await Promise.all([
      tripsApi.browse({ per_page: 4 }),
      bookingsApi.list({ status: 'confirmed', per_page: 1 }),
    ])
    upcoming.value = trips.data
    nextBooking.value = bookings.data[0] ?? null
  } catch {
    /* home stays usable without these lists */
  }
})
</script>

<template>
  <div class="space-y-6">
    <!-- 1. Where are you going? -->
    <section class="card bg-gradient-to-br from-brand-700 to-brand-900 !ring-0 text-white" aria-labelledby="where-title">
      <p class="text-brand-100">{{ $t('home.greeting', { name: auth.user?.name?.split(' ')[0] }) }}</p>
      <h1 id="where-title" class="mb-4 text-3xl font-extrabold">{{ $t('home.whereTo') }}</h1>

      <form class="grid gap-3 rounded-2xl bg-white p-4 text-slate-800 sm:grid-cols-[1fr_auto_1fr_auto_auto] sm:items-end" @submit.prevent="search">
        <PlaceInput v-model="form.origin" :label="$t('home.from')" :placeholder="$t('home.fromPlaceholder')" />
        <button type="button" class="btn-ghost btn-sm self-end" :aria-label="$t('home.swap')" @click="swap">
          <AppIcon name="swap" class="size-5 rotate-90 sm:rotate-0" />
        </button>
        <PlaceInput v-model="form.destination" :label="$t('home.to')" :placeholder="$t('home.toPlaceholder')" />
        <div>
          <label for="home-date" class="label">{{ $t('home.date') }}</label>
          <input id="home-date" v-model="form.date" type="date" :min="todayISO()" class="input" />
        </div>
        <button type="submit" class="btn-primary">
          <AppIcon name="search" class="size-5" /> {{ $t('home.searchBtn') }}
        </button>
      </form>
    </section>

    <!-- Next confirmed booking -->
    <RouterLink
      v-if="nextBooking"
      :to="{ name: 'trip', params: { id: nextBooking.trip_id } }"
      class="card flex items-center gap-3 border-s-4 !border-emerald-500"
    >
      <AppIcon name="ticket" class="size-8 text-emerald-600" />
      <div>
        <p class="text-sm text-slate-500">{{ $t('home.nextTrip') }}</p>
        <p class="font-bold">{{ nextBooking.trip.origin }} ← {{ nextBooking.trip.destination }}</p>
      </div>
    </RouterLink>

    <!-- 2 & 3: have a car / can't find a trip -->
    <div class="grid gap-4 sm:grid-cols-2">
      <section class="card flex flex-col">
        <AppIcon name="car" class="mb-2 size-10 text-brand-700" />
        <h2 class="text-xl font-extrabold">{{ $t('home.haveCar') }}</h2>
        <p class="mb-4 mt-1 flex-1 text-slate-600">{{ $t('home.haveCarHint') }}</p>
        <RouterLink :to="{ name: 'trip-create' }" class="btn-primary">{{ $t('home.addTrip') }}</RouterLink>
      </section>
      <section class="card flex flex-col">
        <AppIcon name="hand" class="mb-2 size-10 text-amber-600" />
        <h2 class="text-xl font-extrabold">{{ $t('home.noTrip') }}</h2>
        <p class="mb-4 mt-1 flex-1 text-slate-600">{{ $t('home.noTripHint') }}</p>
        <RouterLink :to="{ name: 'request-create' }" class="btn-secondary">{{ $t('home.createRequest') }}</RouterLink>
      </section>
    </div>

    <section v-if="upcoming.length" aria-labelledby="upcoming-title">
      <div class="mb-3 flex items-center justify-between">
        <h2 id="upcoming-title" class="text-xl font-extrabold">{{ $t('home.upcoming') }}</h2>
        <RouterLink :to="{ name: 'search' }" class="text-sm font-bold text-brand-700 hover:underline">{{ $t('home.seeAll') }}</RouterLink>
      </div>
      <div class="grid gap-3 md:grid-cols-2">
        <TripCard v-for="trip in upcoming" :key="trip.id" :trip="trip" />
      </div>
    </section>
  </div>
</template>
