<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useToastStore } from '@/stores/toast'
import { formatShortDate, formatTime, formatNumber } from '@/utils/format'
import AdminTable from '@/components/AdminTable.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import PromptDialog from '@/components/PromptDialog.vue'

const { t } = useI18n()
const toast = useToastStore()
const filters = reactive({ search: '', status: '', date: '' })
const search = ref('')
const cancelling = ref(null)
const { items, meta, loading, load, page } = usePaginated((p) => adminApi.trips(p), filters)
const statuses = ['draft', 'published', 'full', 'started', 'completed', 'cancelled']

async function cancel(reason) {
  try {
    toast.success((await adminApi.cancelTrip(cancelling.value.id, reason)).message)
    await load(page.value)
  } catch (e) {
    toast.error(e.message)
  } finally {
    cancelling.value = null
  }
}

onMounted(() => load(1))
</script>

<template>
  <div>
    <h1 class="page-title">{{ $t('admin.trips') }}</h1>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="filters.search = search">
      <input v-model="search" type="search" class="input !w-64" :placeholder="$t('admin.searchTrips')" :aria-label="$t('common.search')" />
      <select v-model="filters.status" class="input !w-auto" :aria-label="$t('common.status')">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ $t(`status.trip.${s}`) }}</option>
      </select>
      <input v-model="filters.date" type="date" class="input !w-auto" :aria-label="$t('home.date')" />
      <button type="submit" class="btn-primary btn-sm">{{ $t('common.search') }}</button>
    </form>

    <AdminTable :columns="['admin.route', 'home.date', 'trip.driver', 'trip.availableSeats', 'admin.bookingsCol', 'common.status', 'common.actions']" :items="items" :meta="meta" :loading="loading" @page="load">
      <tr v-for="trip in items" :key="trip.id">
        <td class="px-3 py-2 font-semibold"><RouterLink :to="{ name: 'trip', params: { id: trip.id } }" class="hover:underline">{{ trip.origin }} ← {{ trip.destination }}</RouterLink></td>
        <td class="px-3 py-2">{{ formatShortDate(trip.departure_date) }} · {{ formatTime(trip.departure_time) }}</td>
        <td class="px-3 py-2">{{ trip.owner?.name }}</td>
        <td class="px-3 py-2">{{ formatNumber(trip.available_seats) }} / {{ formatNumber(trip.total_seats) }}</td>
        <td class="px-3 py-2">{{ formatNumber(trip.bookings_count) }}</td>
        <td class="px-3 py-2"><StatusBadge kind="trip" :status="trip.status" /></td>
        <td class="px-3 py-2">
          <button v-if="['draft', 'published', 'full'].includes(trip.status)" type="button" class="btn-danger btn-sm" @click="cancelling = trip">{{ $t('trip.cancel') }}</button>
        </td>
      </tr>
    </AdminTable>

    <PromptDialog
      :open="!!cancelling"
      :title="t('admin.cancelTripTitle')"
      :message="t('admin.cancelTripMessage')"
      :confirm-label="t('trip.cancel')"
      with-reason
      danger
      @close="cancelling = null"
      @confirm="cancel"
    />
  </div>
</template>
