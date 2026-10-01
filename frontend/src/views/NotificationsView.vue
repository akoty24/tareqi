<script setup>
import { onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { notificationsApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { relativeTime } from '@/utils/format'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import AppIcon from '@/components/AppIcon.vue'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()
const filters = reactive({ unread: '' })
const { items, meta, loading, load } = usePaginated(async (params) => {
  const response = await notificationsApi.list(params)
  auth.unreadNotifications = response.meta.unread_count
  return response
}, filters)

const icons = {
  booking: 'ticket', trip_request_matched: 'search', trip_cancelled: 'x', trip_cancelled_by_admin: 'shield',
  trip_approaching: 'clock', return_trip_approaching: 'return', new_booking: 'user',
}
const iconFor = (type) => icons[type] || icons[Object.keys(icons).find((k) => type?.startsWith(k))] || 'bell'

async function open(n) {
  if (!n.read_at) {
    try {
      await notificationsApi.markRead(n.id)
      n.read_at = new Date().toISOString()
      auth.unreadNotifications = Math.max(0, auth.unreadNotifications - 1)
    } catch {
      /* navigation still works */
    }
  }
  if (n.link) router.push(n.link)
}

async function readAll() {
  try {
    toast.success((await notificationsApi.markAllRead()).message)
    auth.unreadNotifications = 0
    items.value.forEach((n) => (n.read_at ??= new Date().toISOString()))
  } catch (e) {
    toast.error(e.message)
  }
}

onMounted(() => load(1))
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h1 class="page-title !mb-0">{{ $t('nav.notifications') }}</h1>
      <div class="flex gap-2">
        <label class="flex items-center gap-2 text-sm">
          <input v-model="filters.unread" type="checkbox" true-value="1" false-value="" class="size-4 accent-brand-700" />
          {{ $t('notifications.unreadOnly') }}
        </label>
        <button type="button" class="btn-ghost btn-sm" :disabled="!auth.unreadNotifications" @click="readAll">{{ $t('notifications.readAll') }}</button>
      </div>
    </div>

    <LoadingState v-if="loading" />
    <template v-else>
      <ul v-if="items.length" class="space-y-2">
        <li v-for="n in items" :key="n.id">
          <button
            type="button"
            class="card flex w-full items-start gap-3 text-start transition hover:ring-brand-200"
            :class="{ '!bg-brand-50 !ring-brand-200': !n.read_at }"
            @click="open(n)"
          >
            <span class="rounded-full bg-white p-2 ring-1 ring-slate-200"><AppIcon :name="iconFor(n.type)" class="size-5 text-brand-700" /></span>
            <span class="min-w-0 flex-1">
              <span class="block font-bold">{{ n.title }}</span>
              <span class="block text-sm text-slate-600">{{ n.message }}</span>
              <span class="mt-1 block text-xs text-slate-400">{{ relativeTime(n.created_at) }}</span>
            </span>
            <span v-if="!n.read_at" class="mt-2 size-2.5 shrink-0 rounded-full bg-brand-600" :aria-label="$t('notifications.unread')" />
          </button>
        </li>
      </ul>
      <EmptyState v-else icon="bell" :title="$t('notifications.empty')" />
      <PaginationBar :meta="meta" @change="load" />
    </template>
  </div>
</template>
