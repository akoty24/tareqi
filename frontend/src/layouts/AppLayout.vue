<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLogo from '@/components/AppLogo.vue'
import AppIcon from '@/components/AppIcon.vue'
import UserAvatar from '@/components/UserAvatar.vue'

const auth = useAuthStore()
const router = useRouter()
const menuOpen = ref(false)

// Main destinations. On mobile the first four sit in a bottom tab bar.
const primary = [
  { to: { name: 'home' }, label: 'nav.home', icon: 'home' },
  { to: { name: 'search' }, label: 'nav.search', icon: 'search' },
  { to: { name: 'my-bookings' }, label: 'nav.myBookings', icon: 'ticket' },
  { to: { name: 'my-trips' }, label: 'nav.myTrips', icon: 'car' },
]
const secondary = [
  { to: { name: 'requests' }, label: 'nav.requests', icon: 'hand' },
  { to: { name: 'vehicles' }, label: 'nav.vehicles', icon: 'car' },
  { to: { name: 'ratings' }, label: 'nav.ratings', icon: 'star' },
  { to: { name: 'profile' }, label: 'nav.profile', icon: 'user' },
]

const unread = computed(() => auth.unreadNotifications)

// Keep the unread badge fresh without websockets: poll /auth/me while the tab
// is visible, and refresh right away when the user comes back to the tab.
const POLL_MS = 60_000
let timer = null
function refreshUnread() {
  if (document.visibilityState === 'visible' && auth.isAuthenticated) {
    auth.refresh().catch(() => {})
  }
}
onMounted(() => {
  timer = setInterval(refreshUnread, POLL_MS)
  window.addEventListener('focus', refreshUnread)
})
onUnmounted(() => {
  clearInterval(timer)
  window.removeEventListener('focus', refreshUnread)
})

async function logout() {
  menuOpen.value = false
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen pb-20 md:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:p-2">
      {{ $t('nav.skipToContent') }}
    </a>

    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4">
        <RouterLink :to="{ name: 'home' }" class="shrink-0"><AppLogo /></RouterLink>

        <nav class="ms-4 hidden items-center gap-1 md:flex" :aria-label="$t('nav.main')">
          <RouterLink
            v-for="item in [...primary, secondary[0]]"
            :key="item.label"
            :to="item.to"
            class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"
            active-class="!bg-brand-50 !text-brand-800"
          >
            {{ $t(item.label) }}
          </RouterLink>
        </nav>

        <div class="ms-auto flex items-center gap-2">
          <RouterLink :to="{ name: 'trip-create' }" class="btn-primary btn-sm hidden sm:inline-flex">
            <AppIcon name="plus" class="size-4" /> {{ $t('nav.createTrip') }}
          </RouterLink>

          <RouterLink
            :to="{ name: 'notifications' }"
            class="relative rounded-full p-2 text-slate-600 hover:bg-slate-100"
            :aria-label="$t('nav.notifications')"
          >
            <AppIcon name="bell" class="size-6" />
            <span
              v-if="unread > 0"
              class="absolute -top-0.5 -end-0.5 min-w-5 rounded-full bg-red-600 px-1 text-center text-xs font-bold leading-5 text-white"
            >{{ unread > 99 ? '99+' : unread }}</span>
          </RouterLink>

          <div class="relative">
            <button
              type="button"
              class="flex items-center gap-2 rounded-full p-1 hover:bg-slate-100"
              :aria-expanded="menuOpen"
              aria-haspopup="menu"
              :aria-label="$t('nav.accountMenu')"
              @click="menuOpen = !menuOpen"
            >
              <UserAvatar :user="auth.user" size="sm" />
            </button>
            <div v-if="menuOpen" class="fixed inset-0 z-30" @click="menuOpen = false" />
            <div
              v-if="menuOpen"
              role="menu"
              class="absolute end-0 z-40 mt-2 w-56 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-200"
            >
              <p class="truncate px-3 py-2 text-sm font-bold">{{ auth.user?.name }}</p>
              <RouterLink
                v-for="item in secondary"
                :key="item.label"
                :to="item.to"
                role="menuitem"
                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-slate-100"
                @click="menuOpen = false"
              >
                <AppIcon :name="item.icon" class="size-4 text-slate-500" /> {{ $t(item.label) }}
              </RouterLink>
              <RouterLink
                v-if="auth.isAdmin"
                :to="{ name: 'admin' }"
                role="menuitem"
                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-brand-800 hover:bg-brand-50"
                @click="menuOpen = false"
              >
                <AppIcon name="shield" class="size-4" /> {{ $t('nav.admin') }}
              </RouterLink>
              <button type="button" role="menuitem" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50" @click="logout">
                <AppIcon name="logout" class="size-4" /> {{ $t('nav.logout') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main id="main" class="mx-auto max-w-6xl px-4 py-6">
      <RouterView />
    </main>

    <!-- Mobile bottom navigation -->
    <nav
      class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-slate-200 bg-white md:hidden"
      :aria-label="$t('nav.main')"
    >
      <RouterLink
        v-for="item in primary.slice(0, 2)"
        :key="item.label"
        :to="item.to"
        class="flex flex-col items-center gap-0.5 py-2 text-xs text-slate-500"
        exact-active-class="!text-brand-700 font-bold"
      >
        <AppIcon :name="item.icon" class="size-6" />{{ $t(item.label) }}
      </RouterLink>
      <RouterLink :to="{ name: 'trip-create' }" class="flex flex-col items-center gap-0.5 py-1 text-xs font-bold text-brand-700">
        <span class="-mt-5 rounded-full bg-brand-700 p-3 text-white shadow-lg"><AppIcon name="plus" class="size-6" /></span>
        {{ $t('nav.addTripShort') }}
      </RouterLink>
      <RouterLink
        v-for="item in primary.slice(2)"
        :key="item.label"
        :to="item.to"
        class="flex flex-col items-center gap-0.5 py-2 text-xs text-slate-500"
        active-class="!text-brand-700 font-bold"
      >
        <AppIcon :name="item.icon" class="size-6" />{{ $t(item.label) }}
      </RouterLink>
    </nav>
  </div>
</template>
