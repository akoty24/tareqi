<script setup>
import { onMounted, ref, watch } from 'vue'
import { profileApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { formatNumber, formatShortDate } from '@/utils/format'
import UserAvatar from '@/components/UserAvatar.vue'
import StarRating from '@/components/StarRating.vue'
import LoadingState from '@/components/LoadingState.vue'
import ReportDialog from '@/components/ReportDialog.vue'
import AppIcon from '@/components/AppIcon.vue'

const props = defineProps({ id: { type: String, required: true } })
const auth = useAuthStore()
const toast = useToastStore()
const data = ref(null)
const reportOpen = ref(false)

async function load() {
  try {
    data.value = (await profileApi.publicProfile(props.id)).data
  } catch (e) {
    toast.error(e.message)
  }
}

watch(() => props.id, load)
onMounted(load)
</script>

<template>
  <LoadingState v-if="!data" />
  <div v-else class="mx-auto max-w-2xl space-y-5">
    <section class="card flex flex-wrap items-center gap-4">
      <UserAvatar :user="data.user" size="lg" />
      <div class="flex-1">
        <h1 class="text-2xl font-extrabold">{{ data.user.name }}</h1>
        <div class="flex items-center gap-2">
          <StarRating :model-value="data.user.rating_average" readonly size="size-5" />
          <span class="text-sm text-slate-600">{{ data.user.ratings_count ? `${formatNumber(data.user.rating_average)} (${formatNumber(data.user.ratings_count)})` : $t('rating.new') }}</span>
        </div>
        <p class="text-sm text-slate-500">
          {{ $t('profile.completedTrips', { n: formatNumber(data.user.completed_trips_as_owner ?? 0) }) }} ·
          {{ $t('profile.completedRides', { n: formatNumber(data.user.completed_trips_as_passenger ?? 0) }) }} ·
          {{ $t('profile.memberSince', { date: formatShortDate(data.user.member_since) }) }}
        </p>
      </div>
    </section>

    <section class="card">
      <h2 class="mb-3 font-extrabold">{{ $t('rating.recent') }}</h2>
      <ul v-if="data.recent_ratings.length" class="divide-y divide-slate-100">
        <li v-for="r in data.recent_ratings" :key="r.id" class="py-3">
          <div class="flex items-center justify-between">
            <span class="font-semibold">{{ r.rater?.name }}</span>
            <StarRating :model-value="r.stars" readonly size="size-4" />
          </div>
          <p v-if="r.review" class="text-slate-700">"{{ r.review }}"</p>
        </li>
      </ul>
      <p v-else class="text-slate-500">{{ $t('rating.empty') }}</p>
    </section>

    <button v-if="auth.user?.id !== data.user.id" type="button" class="w-full text-center text-sm text-slate-500 hover:text-red-600" @click="reportOpen = true">
      <AppIcon name="flag" class="inline size-4" /> {{ $t('report.reportUser') }}
    </button>
    <ReportDialog :open="reportOpen" :target="{ reported_user_id: data.user.id }" @close="reportOpen = false" />
  </div>
</template>
