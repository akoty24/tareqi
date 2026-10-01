<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { authApi, profileApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { useForm } from '@/composables/useForm'
import { setLocale, SUPPORTED } from '@/i18n'
import { formatNumber, formatRating } from '@/utils/format'
import FormField from '@/components/FormField.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import AppIcon from '@/components/AppIcon.vue'

const auth = useAuthStore()
const toast = useToastStore()
const route = useRoute()
const { t } = useI18n()
const profile = ref(null)
const uploading = ref(false)

const info = useForm({ name: '', phone: '', email: '' })
const password = useForm({ current_password: '', password: '', password_confirmation: '' })

async function load() {
  profile.value = (await profileApi.show()).data
  info.reset({ name: profile.value.name, phone: profile.value.phone, email: profile.value.email })
}

async function saveInfo() {
  const { data } = await info.submit((d) => profileApi.update(d))
  auth.user = data
  profile.value = { ...profile.value, ...data }
}

async function savePassword() {
  await password.submit((d) => profileApi.updatePassword(d))
  password.reset()
}

async function uploadPhoto(event) {
  const file = event.target.files?.[0]
  if (!file) return
  uploading.value = true
  try {
    const response = await profileApi.updatePhoto(file)
    auth.user = response.data
    profile.value = { ...profile.value, ...response.data }
    toast.success(response.message)
  } catch (e) {
    toast.error(e.errors?.photo?.[0] || e.message)
  } finally {
    uploading.value = false
    event.target.value = ''
  }
}

const savingSettings = ref(false)
async function saveSetting(channel, enabled) {
  savingSettings.value = true
  try {
    const response = await profileApi.updateNotificationSettings({ [channel]: enabled })
    profile.value = { ...profile.value, notification_settings: response.data.notification_settings }
    auth.user = { ...auth.user, notification_settings: response.data.notification_settings }
    toast.success(response.message)
  } catch (e) {
    toast.error(e.message)
  } finally {
    savingSettings.value = false
  }
}

async function resendVerification() {
  try {
    toast.success((await authApi.resendVerification()).message)
  } catch (e) {
    toast.error(e.message)
  }
}

onMounted(async () => {
  await load()
  if (route.query.verified) toast.success(t('profile.emailVerified'))
  if (route.hash) {
    await nextTick()
    document.querySelector(route.hash)?.scrollIntoView({ behavior: 'smooth' })
  }
})
</script>

<template>
  <div v-if="profile" class="mx-auto max-w-2xl space-y-5">
    <section class="card flex flex-wrap items-center gap-4">
      <UserAvatar :user="profile" size="lg" />
      <div class="flex-1">
        <h1 class="text-2xl font-extrabold">{{ profile.name }}</h1>
        <p class="flex items-center gap-1 text-slate-600">
          <AppIcon name="star" class="size-4 fill-current text-amber-500" />
          {{ profile.ratings_count ? `${formatRating(profile.rating_average)} (${formatNumber(profile.ratings_count)})` : $t('rating.new') }}
        </p>
        <p class="text-sm text-slate-500">
          {{ $t('profile.completedTrips', { n: formatNumber(profile.completed_trips_as_owner ?? 0) }) }} ·
          {{ $t('profile.completedRides', { n: formatNumber(profile.completed_trips_as_passenger ?? 0) }) }}
        </p>
      </div>
      <label class="btn-secondary btn-sm cursor-pointer">
        {{ uploading ? $t('common.loading') : $t('profile.changePhoto') }}
        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" :disabled="uploading" @change="uploadPhoto" />
      </label>
    </section>

    <div v-if="!profile.email_verified" class="card flex flex-wrap items-center justify-between gap-3 !bg-amber-50 !ring-amber-200">
      <p class="text-amber-900">{{ $t('profile.emailNotVerified') }}</p>
      <button type="button" class="btn-secondary btn-sm" @click="resendVerification">{{ $t('profile.resendVerification') }}</button>
    </div>

    <form class="card space-y-4" novalidate @submit.prevent="saveInfo">
      <h2 class="font-extrabold">{{ $t('profile.info') }}</h2>
      <FormField v-slot="{ id, invalid }" :label="$t('auth.name')" :error="info.error('name')">
        <input :id="id" v-model.trim="info.fields.name" type="text" class="input" :class="{ 'input-error': invalid }" />
      </FormField>
      <FormField v-slot="{ id, invalid }" :label="$t('auth.phone')" :error="info.error('phone')">
        <input :id="id" v-model.trim="info.fields.phone" type="tel" dir="ltr" class="input text-start" :class="{ 'input-error': invalid }" />
      </FormField>
      <FormField v-slot="{ id, invalid }" :label="$t('auth.email')" :error="info.error('email')">
        <input :id="id" v-model.trim="info.fields.email" type="email" dir="ltr" class="input text-start" :class="{ 'input-error': invalid }" />
      </FormField>
      <button type="submit" class="btn-primary" :disabled="info.submitting.value">{{ $t('common.save') }}</button>
    </form>

    <form class="card space-y-4" novalidate @submit.prevent="savePassword">
      <h2 class="font-extrabold">{{ $t('profile.changePassword') }}</h2>
      <FormField v-slot="{ id, invalid }" :label="$t('profile.currentPassword')" :error="password.error('current_password')">
        <input :id="id" v-model="password.fields.current_password" type="password" autocomplete="current-password" class="input" :class="{ 'input-error': invalid }" />
      </FormField>
      <FormField v-slot="{ id, invalid }" :label="$t('auth.newPassword')" :error="password.error('password')" :hint="$t('auth.passwordHint')">
        <input :id="id" v-model="password.fields.password" type="password" autocomplete="new-password" class="input" :class="{ 'input-error': invalid }" />
      </FormField>
      <FormField v-slot="{ id }" :label="$t('auth.passwordConfirm')">
        <input :id="id" v-model="password.fields.password_confirmation" type="password" autocomplete="new-password" class="input" />
      </FormField>
      <button type="submit" class="btn-primary" :disabled="password.submitting.value">{{ $t('profile.changePassword') }}</button>
    </form>

    <section id="notification-settings" class="card">
      <h2 class="mb-1 font-extrabold">{{ $t('profile.notifications') }}</h2>
      <p class="mb-3 text-sm text-slate-600">{{ $t('profile.notificationsHint') }}</p>
      <div class="divide-y divide-slate-100">
        <label v-for="channel in ['email', 'push']" :key="channel" class="flex cursor-pointer items-center justify-between gap-3 py-3">
          <span class="flex items-start gap-3">
            <AppIcon :name="channel === 'email' ? 'mail' : 'devices'" class="mt-0.5 size-5 text-brand-700" />
            <span>
              <span class="block font-semibold">{{ $t(`profile.channels.${channel}`) }}</span>
              <span class="block text-sm text-slate-500">{{ $t(`profile.channelHints.${channel}`) }}</span>
            </span>
          </span>
          <input
            type="checkbox"
            role="switch"
            class="peer sr-only"
            :checked="profile.notification_settings?.[channel]"
            :disabled="savingSettings"
            @change="saveSetting(channel, $event.target.checked)"
          />
          <span
            class="relative h-6 w-11 shrink-0 rounded-full bg-slate-300 transition after:absolute after:top-0.5 after:start-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-brand-700 peer-checked:after:translate-x-5 rtl:peer-checked:after:-translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400"
            aria-hidden="true"
          />
        </label>
      </div>
    </section>

    <section class="card">
      <h2 class="mb-3 font-extrabold">{{ $t('profile.language') }}</h2>
      <div class="flex gap-2">
        <button
          v-for="(_, code) in SUPPORTED"
          :key="code"
          type="button"
          class="btn-sm"
          :class="$i18n.locale === code ? 'btn-primary' : 'btn-secondary'"
          @click="setLocale(code)"
        >{{ $t(`languages.${code}`) }}</button>
      </div>
    </section>
  </div>
</template>
