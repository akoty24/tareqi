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
  announcement: 'megaphone', booking_cancelled_by_admin: 'shield', booking: 'ticket', trip_request_matched: 'search',
  trip_cancelled_by_admin: 'shield', trip_cancelled: 'x', trip_approaching: 'clock', return_trip_approaching: 'return', new_booking: 'user',
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

async function remove(n) {
  try {
    await notificationsApi.remove(n.id)
    items.value = items.value.filter((i) => i.id !== n.id)
    if (!n.read_at) auth.unreadNotifications = Math.max(0, auth.unreadNotifications - 1)
  } catch (e) {
    toast.error(e.message)
  }
}

async function clearRead() {
  try {
    toast.success((await notificationsApi.clearRead()).message)
    await load(1)
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
      <div class="flex flex-wrap gap-2">
        <button type="button" class="btn-ghost btn-sm" :disabled="!auth.unreadNotifications" @click="readAll">
          <AppIcon name="check" class="size-4" /> {{ $t('notifications.readAll') }}
        </button>
        <button type="button" class="btn-ghost btn-sm" @click="clearRead">
          <AppIcon name="trash" class="size-4" /> {{ $t('notifications.clearRead') }}
        </button>
      </div>
    </div>

    <div class="mb-4 inline-flex rounded-xl bg-white p-1 ring-1 ring-slate-200" role="tablist">
      <button
        v-for="tab in [{ v: '', l: 'common.all' }, { v: '1', l: 'notifications.unreadTab' }]"
        :key="tab.v"
        type="button"
        role="tab"
        :aria-selected="filters.unread === tab.v"
        class="rounded-lg px-4 py-1.5 text-sm font-semibold"
        :class="filters.unread === tab.v ? 'bg-brand-700 text-white' : 'text-slate-600'"
        @click="filters.unread = tab.v"
      >
        {{ $t(tab.l) }}<span v-if="tab.v && auth.unreadNotifications" class="ms-1">({{ auth.unreadNotifications }})</span>
      </button>
    </div>

    <LoadingState v-if="loading" />
    <template v-else>
      <ul v-if="items.length" class="space-y-2">
        <li v-for="n in items" :key="n.id" class="group relative">
          <button
            type="button"
            class="card flex w-full items-start gap-3 pe-12 text-start transition hover:ring-brand-200"
            :class="{ '!bg-brand-50 !ring-brand-200': !n.read_at, '!ring-amber-200': n.type === 'announcement' && !n.read_at }"
            @click="open(n)"
          >
            <span class="rounded-full bg-white p-2 ring-1 ring-slate-200"><AppIcon :name="iconFor(n.type)" class="size-5 text-brand-700" /></span>
            <span class="min-w-0 flex-1">
              <span class="flex items-center gap-2 font-bold">
                {{ n.title }}
                <span v-if="!n.read_at" class="size-2 shrink-0 rounded-full bg-brand-600" :aria-label="$t('notifications.unread')" />
              </span>
              <span class="block whitespace-pre-line text-sm text-slate-600">{{ n.message }}</span>
              <span class="mt-1 block text-xs text-slate-400">{{ relativeTime(n.created_at) }}</span>
            </span>
          </button>
          <button
            type="button"
            class="absolute end-2 top-2 rounded-full p-2 text-slate-400 hover:bg-red-50 hover:text-red-600"
            :aria-label="$t('notifications.delete')"
            @click="remove(n)"
          >
            <AppIcon name="trash" class="size-4" />
          </button>
        </li>
      </ul>
      <EmptyState v-else icon="bell" :title="filters.unread ? $t('notifications.emptyUnread') : $t('notifications.empty')" />
      <PaginationBar :meta="meta" @change="load" />
    </template>

    <p class="mt-6 text-center text-sm text-slate-500">
      <RouterLink :to="{ name: 'profile', hash: '#notification-settings' }" class="hover:underline">{{ $t('notifications.settingsLink') }}</RouterLink>
    </p>
  </div>
</template>
