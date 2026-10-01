<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { bookingsApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatTime, formatMoney, formatNumber } from '@/utils/format'
import StatusBadge from '@/components/StatusBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import PromptDialog from '@/components/PromptDialog.vue'
import RatingDialog from '@/components/RatingDialog.vue'
import AppIcon from '@/components/AppIcon.vue'

const { t } = useI18n()
const toast = useToastStore()
const route = useRoute()
const filters = reactive({ role: 'passenger', status: String(route.query.status || '') })
const { items, meta, loading, load, page } = usePaginated((params) => bookingsApi.list(params), filters)
const statuses = ['pending', 'confirmed', 'completed', 'cancelled', 'rejected']

const cancelling = ref(null)
const rating = ref(null)
const busy = ref(false)

async function run(action) {
  busy.value = true
  try {
    toast.success((await action()).message)
    await load(page.value)
  } catch (e) {
    toast.error(e.message)
  } finally {
    busy.value = false
  }
}

async function cancel(reason) {
  await run(() => bookingsApi.cancel(cancelling.value.id, reason))
  cancelling.value = null
}

onMounted(() => load(1))
</script>

<template>
  <div>
    <h1 class="page-title">{{ $t('nav.myBookings') }}</h1>

    <div class="mb-4 flex flex-wrap gap-2">
      <div class="inline-flex rounded-xl bg-white p-1 ring-1 ring-slate-200" role="tablist">
        <button
          v-for="r in ['passenger', 'owner']"
          :key="r"
          type="button"
          role="tab"
          :aria-selected="filters.role === r"
          class="rounded-lg px-4 py-1.5 text-sm font-semibold"
          :class="filters.role === r ? 'bg-brand-700 text-white' : 'text-slate-600'"
          @click="filters.role = r"
        >{{ $t(`myBookings.${r}`) }}</button>
      </div>
      <label class="sr-only" for="mb-status">{{ $t('common.status') }}</label>
      <select id="mb-status" v-model="filters.status" class="input !w-auto !min-h-10 py-1">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ $t(`status.booking.${s}`) }}</option>
      </select>
    </div>

    <LoadingState v-if="loading" />
    <template v-else>
      <ul v-if="items.length" class="space-y-3">
        <li v-for="b in items" :key="b.id" class="card">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <RouterLink :to="{ name: 'trip', params: { id: b.trip_id } }" class="min-w-0">
              <p class="text-lg font-extrabold hover:underline">{{ b.trip.origin }} ← {{ b.trip.destination }}</p>
              <p class="text-sm text-slate-600">{{ formatDate(b.trip.departure_date) }} · {{ formatTime(b.trip.departure_time) }}</p>
            </RouterLink>
            <StatusBadge kind="booking" :status="b.status" />
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-700">
            <span v-if="filters.role === 'owner'" class="font-semibold">{{ b.passenger.name }}</span>
            <span v-else>{{ $t('trip.driver') }}: <strong>{{ b.trip.owner?.name }}</strong></span>
            <span>{{ $t('booking.seatsCount', { n: formatNumber(b.seats) }, b.seats) }}</span>
            <span>{{ formatMoney(b.total_price) }}</span>
            <a v-if="b.owner_phone" :href="`tel:${b.owner_phone}`" class="inline-flex items-center gap-1 font-semibold text-brand-700" dir="ltr"><AppIcon name="phone" class="size-4" />{{ b.owner_phone }}</a>
            <a v-if="b.passenger_phone" :href="`tel:${b.passenger_phone}`" class="inline-flex items-center gap-1 font-semibold text-brand-700" dir="ltr"><AppIcon name="phone" class="size-4" />{{ b.passenger_phone }}</a>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <RouterLink v-if="filters.role === 'owner' && b.status === 'pending'" :to="{ name: 'trip', params: { id: b.trip_id } }" class="btn-primary btn-sm">{{ $t('booking.review') }}</RouterLink>
            <button
              v-if="filters.role === 'passenger' && ['pending', 'confirmed'].includes(b.status) && ['published', 'full'].includes(b.trip.status)"
              type="button"
              class="btn-secondary btn-sm text-red-600"
              @click="cancelling = b"
            >{{ $t('booking.cancel') }}</button>
            <button v-if="b.can_rate" type="button" class="btn-primary btn-sm" @click="rating = b">
              <AppIcon name="star" class="size-4" />{{ filters.role === 'owner' ? $t('rating.ratePassenger') : $t('rating.rateDriver') }}
            </button>
            <span v-else-if="b.rated_by_me" class="text-sm text-emerald-700">✓ {{ $t('rating.done') }}</span>
          </div>
        </li>
      </ul>
      <EmptyState v-else icon="ticket" :title="$t('myBookings.empty')" :text="$t('myBookings.emptyHint')">
        <RouterLink :to="{ name: 'search' }" class="btn-primary">{{ $t('home.searchBtn') }}</RouterLink>
      </EmptyState>
      <PaginationBar :meta="meta" @change="load" />
    </template>

    <PromptDialog
      :open="!!cancelling"
      :title="t('booking.cancelTitle')"
      :message="t('booking.cancelMessage')"
      :confirm-label="t('booking.cancel')"
      with-reason
      danger
      :busy="busy"
      @close="cancelling = null"
      @confirm="cancel"
    />
    <RatingDialog
      :open="!!rating"
      :booking="rating"
      :person-name="rating ? (filters.role === 'owner' ? rating.passenger?.name : rating.trip.owner?.name) : ''"
      @close="rating = null"
      @rated="load(page)"
    />
  </div>
</template>
