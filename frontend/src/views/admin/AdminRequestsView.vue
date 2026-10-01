<script setup>
import { onMounted, reactive, ref } from 'vue'
import { adminApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { formatShortDate, formatTime, formatNumber } from '@/utils/format'
import AdminTable from '@/components/AdminTable.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const filters = reactive({ search: '', status: '' })
const search = ref('')
const { items, meta, loading, load } = usePaginated((p) => adminApi.tripRequests(p), filters)

onMounted(() => load(1))
</script>

<template>
  <div>
    <h1 class="page-title">{{ $t('admin.requests') }}</h1>
    <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="filters.search = search">
      <input v-model="search" type="search" class="input !w-64" :placeholder="$t('admin.searchPlace')" :aria-label="$t('common.search')" />
      <select v-model="filters.status" class="input !w-auto" :aria-label="$t('common.status')">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option v-for="s in ['active', 'fulfilled', 'cancelled', 'expired']" :key="s" :value="s">{{ $t(`status.request.${s}`) }}</option>
      </select>
      <button type="submit" class="btn-primary btn-sm">{{ $t('common.search') }}</button>
    </form>

    <AdminTable :columns="['admin.passenger', 'admin.route', 'home.date', 'trip.time', 'search.passengers', 'admin.matches', 'common.status']" :items="items" :meta="meta" :loading="loading" @page="load">
      <tr v-for="r in items" :key="r.id">
        <td class="px-3 py-2 font-semibold">{{ r.user?.name }}</td>
        <td class="px-3 py-2">{{ r.origin }} ← {{ r.destination }}</td>
        <td class="px-3 py-2">{{ formatShortDate(r.requested_date) }}</td>
        <td class="px-3 py-2">{{ r.preferred_time_from ? `${formatTime(r.preferred_time_from)} – ${formatTime(r.preferred_time_to)}` : '—' }}</td>
        <td class="px-3 py-2">{{ formatNumber(r.passengers_count) }}</td>
        <td class="px-3 py-2">{{ formatNumber(r.matched_trips_count) }}</td>
        <td class="px-3 py-2"><StatusBadge kind="request" :status="r.status" /></td>
      </tr>
    </AdminTable>
  </div>
</template>
