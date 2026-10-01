<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { tripsApi } from '@/api'
import { todayISO, formatNumber } from '@/utils/format'
import PlaceInput from '@/components/PlaceInput.vue'
import TripCard from '@/components/TripCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import AppIcon from '@/components/AppIcon.vue'

const route = useRoute()
const router = useRouter()

const keys = ['origin', 'destination', 'date', 'time_from', 'time_to', 'passengers', 'cost_type', 'flexible_dates']
const filters = reactive(Object.fromEntries(keys.map((k) => [k, route.query[k] ?? ''])))
if (!filters.passengers) filters.passengers = '1'
const showMore = ref(!!(filters.time_from || filters.time_to || filters.cost_type))

const results = ref([])
const meta = ref(null)
const loading = ref(false)
const error = ref('')

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== false))
    if (params.flexible_dates) params.flexible_dates = 1
    const response = await tripsApi.search({ ...params, page })
    results.value = response.data
    meta.value = response.meta
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function submit() {
  const query = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== false))
  router.replace({ query })
}

watch(() => route.query, () => load(1))
onMounted(() => load(1))
</script>

<template>
  <div>
    <h1 class="page-title">{{ $t('search.title') }}</h1>

    <form class="card mb-5 space-y-3" @submit.prevent="submit">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <PlaceInput v-model="filters.origin" :label="$t('home.from')" :placeholder="$t('home.fromPlaceholder')" />
        <PlaceInput v-model="filters.destination" :label="$t('home.to')" :placeholder="$t('home.toPlaceholder')" />
        <div>
          <label for="s-date" class="label">{{ $t('home.date') }}</label>
          <input id="s-date" v-model="filters.date" type="date" :min="todayISO()" class="input" />
        </div>
        <div>
          <label for="s-pass" class="label">{{ $t('search.passengers') }}</label>
          <select id="s-pass" v-model="filters.passengers" class="input">
            <option v-for="n in 6" :key="n" :value="String(n)">{{ n }}</option>
          </select>
        </div>
      </div>

      <button type="button" class="text-sm font-semibold text-brand-700" :aria-expanded="showMore" @click="showMore = !showMore">
        {{ showMore ? $t('search.fewerFilters') : $t('search.moreFilters') }}
      </button>

      <div v-if="showMore" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label for="s-from" class="label">{{ $t('search.timeFrom') }}</label>
          <input id="s-from" v-model="filters.time_from" type="time" class="input" />
        </div>
        <div>
          <label for="s-to" class="label">{{ $t('search.timeTo') }}</label>
          <input id="s-to" v-model="filters.time_to" type="time" class="input" />
        </div>
        <div>
          <label for="s-cost" class="label">{{ $t('trip.costTypeLabel') }}</label>
          <select id="s-cost" v-model="filters.cost_type" class="input">
            <option value="">{{ $t('common.all') }}</option>
            <option v-for="c in ['free', 'cost_sharing', 'fixed_price']" :key="c" :value="c">{{ $t(`trip.costType.${c}`) }}</option>
          </select>
        </div>
        <label class="flex items-center gap-2 self-end pb-2">
          <input v-model="filters.flexible_dates" type="checkbox" true-value="1" false-value="" class="size-5 accent-brand-700" />
          <span>{{ $t('search.flexibleDates') }}</span>
        </label>
      </div>

      <button type="submit" class="btn-primary w-full sm:w-auto"><AppIcon name="search" class="size-5" />{{ $t('home.searchBtn') }}</button>
    </form>

    <LoadingState v-if="loading" />
    <p v-else-if="error" class="card text-red-600">{{ error }}</p>
    <template v-else>
      <p v-if="meta" class="mb-3 text-sm text-slate-600">{{ $t('search.resultsCount', { n: formatNumber(meta.total) }, meta.total) }}</p>
      <div v-if="results.length" class="grid gap-3 md:grid-cols-2">
        <TripCard v-for="trip in results" :key="trip.id" :trip="trip" />
      </div>
      <EmptyState v-else icon="search" :title="$t('search.noResults')" :text="$t('search.noResultsHint')">
        <RouterLink
          :to="{ name: 'request-create', query: { origin: filters.origin, destination: filters.destination, date: filters.date } }"
          class="btn-primary"
        >{{ $t('home.createRequest') }}</RouterLink>
      </EmptyState>
      <PaginationBar :meta="meta" @change="load" />
    </template>
  </div>
</template>
