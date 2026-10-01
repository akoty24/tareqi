<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import PromptDialog from '@/components/PromptDialog.vue'
import { formatShortDate, formatMoney, formatNumber } from '@/utils/format'
import AdminTable from '@/components/AdminTable.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const filters = reactive({ search: '', status: '' })
const search = ref('')
const { items, meta, loading, load, page } = usePaginated((p) => adminApi.bookings(p), filters)
const statuses = ['pending', 'confirmed', 'rejected', 'cancelled', 'completed']
const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()
const cancelling = ref(null)
const cancellable = (b) => ['pending', 'confirmed'].includes(b.status) && ['published', 'full'].includes(b.trip?.status)

async function cancel(reason) {
  try {
    toast.success((await adminApi.cancelBooking(cancelling.value.id, reason)).message)
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
    <h1 class="page-title">{{ $t('admin.bookings') }}</h1>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="filters.search = search">
      <input v-model="search" type="search" class="input !w-64" :placeholder="$t('admin.searchPassenger')" :aria-label="$t('common.search')" />
      <select v-model="filters.status" class="input !w-auto" :aria-label="$t('common.status')">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ $t(`status.booking.${s}`) }}</option>
      </select>
      <button type="submit" class="btn-primary btn-sm">{{ $t('common.search') }}</button>
    </form>

    <AdminTable :columns="['admin.passenger', 'admin.route', 'home.date', 'booking.seats', 'booking.total', 'common.status', 'admin.created', 'common.actions']" :items="items" :meta="meta" :loading="loading" @page="load">
      <tr v-for="b in items" :key="b.id">
        <td class="px-3 py-2 font-semibold">{{ b.passenger?.name }}</td>
        <td class="px-3 py-2"><RouterLink :to="{ name: 'trip', params: { id: b.trip_id } }" class="hover:underline">{{ b.trip?.origin }} ← {{ b.trip?.destination }}</RouterLink></td>
        <td class="px-3 py-2">{{ formatShortDate(b.trip?.departure_date) }}</td>
        <td class="px-3 py-2">{{ formatNumber(b.seats) }}</td>
        <td class="px-3 py-2">{{ formatMoney(b.total_price) }}</td>
        <td class="px-3 py-2"><StatusBadge kind="booking" :status="b.status" /></td>
        <td class="px-3 py-2">{{ formatShortDate(b.created_at) }}</td>
        <td class="px-3 py-2">
          <button v-if="auth.can('bookings.manage') && cancellable(b)" type="button" class="btn-danger btn-sm" @click="cancelling = b">{{ $t('booking.cancel') }}</button>
        </td>
      </tr>
    </AdminTable>

    <PromptDialog
      :open="!!cancelling"
      :title="t('admin.cancelBookingTitle')"
      :message="t('admin.cancelBookingMessage')"
      :confirm-label="t('booking.cancel')"
      with-reason
      danger
      @close="cancelling = null"
      @confirm="cancel"
    />
  </div>
</template>
