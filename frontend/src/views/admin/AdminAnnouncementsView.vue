<script setup>
import { computed, onMounted, ref } from 'vue'
import { adminApi } from '@/api'
import { usePaginated } from '@/composables/usePaginated'
import { useForm } from '@/composables/useForm'
import { formatDateTime, formatNumber } from '@/utils/format'
import FormField from '@/components/FormField.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'
import AppIcon from '@/components/AppIcon.vue'

const audiences = ['all', 'drivers', 'staff', 'selected']
const form = useForm({ title: '', message: '', link: '', audience: 'all', user_ids: [], send_email: false })
const { items, meta, loading, load } = usePaginated((p) => adminApi.announcements(p))

// Picking recipients for audience "selected".
const userSearch = ref('')
const userResults = ref([])
const picked = ref([])
const searching = ref(false)

async function findUsers() {
  if (!userSearch.value.trim()) return
  searching.value = true
  try {
    userResults.value = (await adminApi.users({ search: userSearch.value, status: 'active', per_page: 8 })).data
  } finally {
    searching.value = false
  }
}

function pick(user) {
  if (!picked.value.some((u) => u.id === user.id)) picked.value.push(user)
}

const preview = computed(() => ({ title: form.fields.title || '—', message: form.fields.message || '—' }))

async function send() {
  const payload = (data) => adminApi.sendAnnouncement({
    ...data,
    link: data.link || null,
    user_ids: data.audience === 'selected' ? picked.value.map((u) => u.id) : undefined,
  })
  await form.submit(payload)
  form.reset()
  picked.value = []
  userResults.value = []
  await load(1)
}

onMounted(() => load(1))
</script>

<template>
  <div>
    <h1 class="page-title">{{ $t('admin.announcements') }}</h1>

    <div class="grid gap-5 lg:grid-cols-5">
      <form class="card space-y-4 lg:col-span-3" novalidate @submit.prevent="send">
        <h2 class="font-extrabold">{{ $t('admin.newAnnouncement') }}</h2>
        <FormField v-slot="{ id, invalid }" :label="$t('admin.announcementTitle')" :error="form.error('title')">
          <input :id="id" v-model.trim="form.fields.title" type="text" maxlength="120" class="input" :class="{ 'input-error': invalid }" />
        </FormField>
        <FormField v-slot="{ id, invalid }" :label="$t('admin.announcementMessage')" :error="form.error('message')">
          <textarea :id="id" v-model.trim="form.fields.message" rows="4" maxlength="1000" class="input" :class="{ 'input-error': invalid }" />
        </FormField>
        <FormField v-slot="{ id, invalid }" :label="$t('admin.announcementLink')" :error="form.error('link')" :hint="$t('admin.announcementLinkHint')">
          <input :id="id" v-model.trim="form.fields.link" type="text" dir="ltr" placeholder="/search" class="input text-start" :class="{ 'input-error': invalid }" />
        </FormField>

        <fieldset>
          <legend class="label">{{ $t('admin.audience') }}</legend>
          <div class="grid gap-2 sm:grid-cols-2">
            <label
              v-for="a in audiences"
              :key="a"
              class="flex cursor-pointer items-start gap-2 rounded-xl p-3 ring-1"
              :class="form.fields.audience === a ? 'bg-brand-50 ring-brand-300' : 'ring-slate-200'"
            >
              <input v-model="form.fields.audience" type="radio" :value="a" class="mt-1 accent-brand-700" />
              <span>
                <span class="block text-sm font-bold">{{ $t(`admin.audiences.${a}`) }}</span>
                <span class="block text-xs text-slate-500">{{ $t(`admin.audienceHints.${a}`) }}</span>
              </span>
            </label>
          </div>
        </fieldset>

        <div v-if="form.fields.audience === 'selected'" class="space-y-2 rounded-xl bg-slate-50 p-3">
          <div class="flex gap-2">
            <input v-model="userSearch" type="search" class="input" :placeholder="$t('admin.searchUsers')" :aria-label="$t('admin.searchUsers')" @keydown.enter.prevent="findUsers" />
            <button type="button" class="btn-secondary btn-sm" :disabled="searching" @click="findUsers">{{ $t('common.search') }}</button>
          </div>
          <ul v-if="userResults.length" class="divide-y divide-slate-200 rounded-lg bg-white text-sm ring-1 ring-slate-200">
            <li v-for="u in userResults" :key="u.id" class="flex items-center justify-between gap-2 px-3 py-1.5">
              <span>{{ u.name }} <span class="text-xs text-slate-500" dir="ltr">{{ u.phone }}</span></span>
              <button type="button" class="btn-ghost btn-sm" @click="pick(u)"><AppIcon name="plus" class="size-4" /></button>
            </li>
          </ul>
          <div class="flex flex-wrap gap-1">
            <span v-for="u in picked" :key="u.id" class="inline-flex items-center gap-1 rounded-full bg-brand-100 px-2 py-0.5 text-sm text-brand-900">
              {{ u.name }}
              <button type="button" :aria-label="$t('common.delete')" @click="picked = picked.filter((p) => p.id !== u.id)"><AppIcon name="x" class="size-3.5" /></button>
            </span>
          </div>
          <p v-if="form.error('user_ids')" class="text-sm text-red-600">{{ form.error('user_ids') }}</p>
        </div>

        <label class="flex items-center gap-2 text-sm">
          <input v-model="form.fields.send_email" type="checkbox" class="size-4 accent-brand-700" />
          <AppIcon name="mail" class="size-4 text-slate-500" /> {{ $t('admin.alsoEmail') }}
        </label>

        <div class="rounded-xl bg-slate-50 p-3">
          <p class="mb-2 text-xs font-semibold text-slate-500">{{ $t('admin.preview') }}</p>
          <div class="flex items-start gap-3 rounded-xl bg-white p-3 ring-1 ring-slate-200">
            <span class="rounded-full bg-brand-50 p-2"><AppIcon name="megaphone" class="size-5 text-brand-700" /></span>
            <span>
              <span class="block font-bold">{{ preview.title }}</span>
              <span class="block whitespace-pre-line text-sm text-slate-600">{{ preview.message }}</span>
            </span>
          </div>
        </div>

        <button type="submit" class="btn-primary" :disabled="form.submitting.value || (form.fields.audience === 'selected' && !picked.length)">
          <AppIcon name="megaphone" class="size-4" /> {{ $t('admin.send') }}
        </button>
      </form>

      <section class="lg:col-span-2">
        <h2 class="mb-3 font-extrabold">{{ $t('admin.sentAnnouncements') }}</h2>
        <LoadingState v-if="loading" />
        <template v-else>
          <ul v-if="items.length" class="space-y-2">
            <li v-for="a in items" :key="a.id" class="card !p-3">
              <p class="font-bold">{{ a.title }}</p>
              <p class="line-clamp-2 text-sm text-slate-600">{{ a.message }}</p>
              <p class="mt-1 flex flex-wrap gap-x-2 text-xs text-slate-500">
                <span>{{ $t(`admin.audiences.${a.audience}`) }}</span>
                <span>· {{ $t('admin.recipients', { n: formatNumber(a.recipients_count) }, a.recipients_count) }}</span>
                <span v-if="a.send_email">· {{ $t('admin.withEmail') }}</span>
                <span>· {{ a.sent_at ? formatDateTime(a.sent_at) : $t('admin.sending') }}</span>
                <span v-if="a.sender">· {{ a.sender.name }}</span>
              </p>
            </li>
          </ul>
          <EmptyState v-else icon="megaphone" :title="$t('admin.noAnnouncements')" />
          <PaginationBar :meta="meta" @change="load" />
        </template>
      </section>
    </div>
  </div>
</template>
