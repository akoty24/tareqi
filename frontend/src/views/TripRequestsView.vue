<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { tripRequestsApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useToastStore } from '@/stores/toast'
import { formatDate, formatTime, formatNumber } from '@/utils/format'
import StatusBadge from '@/components/StatusBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import PromptDialog from '@/components/PromptDialog.vue'

const { t } = useI18n()
const toast = useToastStore()
const filters = reactive({ status: '' })
const { items, meta, loading, load, page } = usePaginated((params) => tripRequestsApi.list(params), filters)
const cancelling = ref(null)
const busy = ref(false)

async function cancel() {
  busy.value = true
  try {
    toast.success((await tripRequestsApi.cancel(cancelling.value.id)).message)
    cancelling.value = null
    await load(page.value)
  } catch (e) {
    toast.error(e.message)
  } finally {
    busy.value = false
  }
}

onMounted(() => load(1))
</script>

<template>
  <div>
    <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
      <h1 class="page-title !mb-0">{{ $t('nav.requests') }}</h1>
      <RouterLink :to="{ name: 'request-create' }" class="btn-primary btn-sm">{{ $t('request.create') }}</RouterLink>
    </div>
    <p class="mb-4 text-slate-600">{{ $t('request.intro') }}</p>

    <label class="sr-only" for="tr-status">{{ $t('common.status') }}</label>
    <select id="tr-status" v-model="filters.status" class="input mb-4 !w-auto !min-h-10 py-1">
      <option value="">{{ $t('common.allStatuses') }}</option>
      <option v-for="s in ['active', 'fulfilled', 'cancelled', 'expired']" :key="s" :value="s">{{ $t(`status.request.${s}`) }}</option>
    </select>

    <LoadingState v-if="loading" />
    <template v-else>
      <ul v-if="items.length" class="grid gap-3 md:grid-cols-2">
        <li v-for="r in items" :key="r.id" class="card">
          <div class="flex items-start justify-between gap-2">
            <RouterLink :to="{ name: 'request', params: { id: r.id } }" class="min-w-0 hover:underline">
              <p class="text-lg font-extrabold">{{ r.origin }} ← {{ r.destination }}</p>
            </RouterLink>
            <StatusBadge kind="request" :status="r.status" />
          </div>
          <p class="mt-1 text-sm text-slate-600">
            {{ formatDate(r.requested_date) }}
            <template v-if="r.preferred_time_from"> · {{ formatTime(r.preferred_time_from) }} – {{ formatTime(r.preferred_time_to) }}</template>
            · {{ $t('request.passengersCount', { n: formatNumber(r.passengers_count) }, r.passengers_count) }}
          </p>
          <p v-if="r.matched_trips_count" class="mt-2 text-sm font-semibold text-brand-700">{{ $t('request.matchedCount', { n: formatNumber(r.matched_trips_count) }) }}</p>
          <div class="mt-3 flex gap-2">
            <RouterLink :to="{ name: 'request', params: { id: r.id } }" class="btn-secondary btn-sm">{{ $t('request.viewMatches') }}</RouterLink>
            <button v-if="r.status === 'active'" type="button" class="btn-ghost btn-sm text-red-600" @click="cancelling = r">{{ $t('request.cancel') }}</button>
          </div>
        </li>
      </ul>
      <EmptyState v-else icon="hand" :title="$t('request.empty')" :text="$t('request.emptyHint')">
        <RouterLink :to="{ name: 'request-create' }" class="btn-primary">{{ $t('request.create') }}</RouterLink>
      </EmptyState>
      <PaginationBar :meta="meta" @change="load" />
    </template>

    <PromptDialog
      :open="!!cancelling"
      :title="t('request.cancel')"
      :message="t('request.cancelMessage')"
      :confirm-label="t('request.cancel')"
      danger
      :busy="busy"
      @close="cancelling = null"
      @confirm="cancel"
    />
  </div>
</template>
