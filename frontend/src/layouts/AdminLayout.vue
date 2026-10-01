<script setup>
import AppLogo from '@/components/AppLogo.vue'
import AppIcon from '@/components/AppIcon.vue'

const links = [
  { name: 'admin', label: 'admin.dashboard', icon: 'chart' },
  { name: 'admin-users', label: 'admin.users', icon: 'user' },
  { name: 'admin-trips', label: 'admin.trips', icon: 'car' },
  { name: 'admin-bookings', label: 'admin.bookings', icon: 'ticket' },
  { name: 'admin-requests', label: 'admin.requests', icon: 'hand' },
  { name: 'admin-reports', label: 'admin.reports', icon: 'flag' },
]
</script>

<template>
  <div class="min-h-screen">
    <header class="sticky top-0 z-30 bg-slate-900 text-white">
      <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4">
        <AppLogo dark />
        <span class="rounded bg-amber-400 px-2 text-xs font-bold text-slate-900">{{ $t('admin.badge') }}</span>
        <RouterLink :to="{ name: 'home' }" class="ms-auto text-sm text-slate-300 hover:text-white">
          {{ $t('admin.backToApp') }}
        </RouterLink>
      </div>
      <nav class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-2 pb-2" :aria-label="$t('admin.nav')">
        <RouterLink
          v-for="link in links"
          :key="link.name"
          :to="{ name: link.name }"
          class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-slate-300 hover:bg-slate-800 hover:text-white"
          exact-active-class="!bg-white !text-slate-900 font-bold"
        >
          <AppIcon :name="link.icon" class="size-4" /> {{ $t(link.label) }}
        </RouterLink>
      </nav>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-6">
      <RouterView />
    </main>
  </div>
</template>
