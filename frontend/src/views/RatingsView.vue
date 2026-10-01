<script setup>
import { onMounted, reactive } from 'vue'
import { ratingsApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useAuthStore } from '@/stores/auth'
import { formatShortDate, formatNumber } from '@/utils/format'
import StarRating from '@/components/StarRating.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationBar from '@/components/PaginationBar.vue'

const auth = useAuthStore()
const filters = reactive({ type: 'received' })
const { items, meta, loading, load } = usePaginated((p) => ratingsApi.list(p.type, p.page), filters)

onMounted(() => load(1))
</script>

<template>
  <div class="mx-auto max-w-2xl">
    <h1 class="page-title">{{ $t('nav.ratings') }}</h1>

    <section class="card mb-4 flex items-center gap-4">
      <span class="text-4xl font-extrabold text-amber-500">{{ formatNumber(auth.user?.rating_average) }}</span>
      <div>
        <StarRating :model-value="auth.user?.rating_average || 0" readonly size="size-5" />
        <p class="text-sm text-slate-600">{{ $t('rating.basedOn', { n: formatNumber(auth.user?.ratings_count ?? 0) }) }}</p>
      </div>
      <RouterLink :to="{ name: 'my-bookings', query: { status: 'completed' } }" class="ms-auto text-sm font-semibold text-brand-700 hover:underline">
        {{ $t('rating.pendingToRate') }}
      </RouterLink>
    </section>

    <div class="mb-4 inline-flex rounded-xl bg-white p-1 ring-1 ring-slate-200" role="tablist">
      <button
        v-for="type in ['received', 'given']"
        :key="type"
        type="button"
        role="tab"
        :aria-selected="filters.type === type"
        class="rounded-lg px-4 py-1.5 text-sm font-semibold"
        :class="filters.type === type ? 'bg-brand-700 text-white' : 'text-slate-600'"
        @click="filters.type = type"
      >{{ $t(`rating.${type}`) }}</button>
    </div>

    <LoadingState v-if="loading" />
    <template v-else>
      <ul v-if="items.length" class="space-y-3">
        <li v-for="r in items" :key="r.id" class="card">
          <div class="flex items-center gap-3">
            <UserAvatar :user="filters.type === 'received' ? r.rater : r.rated_user" size="sm" />
            <div class="min-w-0 flex-1">
              <p class="font-bold">{{ (filters.type === 'received' ? r.rater : r.rated_user)?.name }}</p>
              <p v-if="r.trip" class="text-xs text-slate-500">{{ r.trip.origin }} ← {{ r.trip.destination }} · {{ formatShortDate(r.trip.departure_date) }}</p>
            </div>
            <StarRating :model-value="r.stars" readonly size="size-4" />
          </div>
          <p v-if="r.review" class="mt-2 text-slate-700">"{{ r.review }}"</p>
        </li>
      </ul>
      <EmptyState v-else icon="star" :title="$t('rating.empty')" />
      <PaginationBar :meta="meta" @change="load" />
    </template>
  </div>
</template>
