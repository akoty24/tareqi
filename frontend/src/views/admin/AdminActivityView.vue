<script setup>
import { onMounted, reactive } from 'vue'
import { adminApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { formatDateTime, relativeTime } from '@/utils/format'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import AppIcon from '@/components/AppIcon.vue'

const filters = reactive({ action: '' })
const { items, meta, loading, load } = usePaginated((p) => adminApi.activity(p), filters)

const groups = ['user', 'role', 'trip', 'booking', 'trip_request', 'vehicle', 'rating', 'report', 'announcement']
const icons = {
  user: 'user', role: 'lock', trip: 'pin', booking: 'ticket', trip_request: 'hand',
  vehicle: 'car', rating: 'star', report: 'flag', announcement: 'megaphone',
}
const iconFor = (action) => icons[action.split('.')[0]] || 'info'
const danger = (action) => /blocked$|deleted$|cancelled$|revoked$/.test(action) && !action.endsWith('unblocked')

onMounted(() => load(1))
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <h1 class="page-title !mb-0">{{ $t('admin.activity') }}</h1>
      <select v-model="filters.action" class="input !w-auto" :aria-label="$t('admin.activityType')">
        <option value="">{{ $t('common.all') }}</option>
        <option v-for="g in groups" :key="g" :value="`${g}.`">{{ $t(`admin.activityGroups.${g}`) }}</option>
      </select>
    </div>

    <LoadingState v-if="loading" />
    <template v-else>
      <ol v-if="items.length" class="relative space-y-3 border-s-2 border-slate-200 ps-5">
        <li v-for="log in items" :key="log.id" class="relative">
          <span
            class="absolute -start-[1.95rem] top-2 rounded-full p-1.5 ring-4 ring-slate-50"
            :class="danger(log.action) ? 'bg-red-100 text-red-700' : 'bg-brand-100 text-brand-800'"
          ><AppIcon :name="iconFor(log.action)" class="size-4" /></span>
          <div class="card !p-3">
            <p class="font-semibold">{{ log.description }}</p>
            <p v-if="log.properties?.reason" class="text-sm text-slate-600">{{ $t('common.reason') }}: {{ log.properties.reason }}</p>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
              <UserAvatar v-if="log.causer" :user="log.causer" size="sm" class="!size-5 !text-[10px]" />
              <span>{{ log.causer?.name || $t('admin.system') }}</span>
              <span :title="formatDateTime(log.created_at)">· {{ relativeTime(log.created_at) }}</span>
              <span v-if="log.ip_address" dir="ltr">· {{ log.ip_address }}</span>
            </p>
          </div>
        </li>
      </ol>
      <EmptyState v-else icon="list" :title="$t('admin.noActivity')" />
      <PaginationBar :meta="meta" @change="load" />
    </template>
  </div>
</template>
