<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { useForm } from '@/composables/useForm'
import { formatDateTime, formatNumber, formatRating } from '@/utils/format'
import FormField from '@/components/FormField.vue'
import LoadingState from '@/components/LoadingState.vue'
import PromptDialog from '@/components/PromptDialog.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import AppIcon from '@/components/AppIcon.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })
const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()

const details = ref(null)
const roles = ref([])
const roleId = ref('')
const savingRole = ref(false)
const confirm = ref(null) // 'block' | 'unblock' | 'sessions'
const busy = ref(false)
const form = useForm({ name: '', phone: '', email: '', email_verified: false })

const user = computed(() => details.value?.user)
const isSelf = computed(() => user.value?.id === auth.user?.id)
// Mirrors UserPolicy: super admins are protected, staff only by a super admin.
const canModerate = computed(() => user.value && !isSelf.value && !user.value.role?.is_super && (!user.value.role || auth.isSuperAdmin))
const canChangeRole = computed(() => auth.can('roles.manage') && user.value && !isSelf.value && (!user.value.role || auth.isSuperAdmin))
const assignableRoles = computed(() => roles.value.filter((r) => !r.is_super || auth.isSuperAdmin))

async function load() {
  details.value = (await adminApi.user(props.id)).data
  const u = details.value.user
  form.reset({ name: u.name, phone: u.phone, email: u.email, email_verified: u.email_verified })
  roleId.value = u.role ? String(u.role.id) : ''
}

async function save() {
  const { data } = await form.submit((payload) => adminApi.updateUser(user.value.id, payload))
  details.value.user = { ...details.value.user, ...data }
}

async function saveRole() {
  savingRole.value = true
  try {
    const response = await adminApi.assignRole(user.value.id, roleId.value ? Number(roleId.value) : null)
    details.value.user = { ...details.value.user, ...response.data }
    toast.success(response.message)
  } catch (e) {
    toast.error(e.message)
  } finally {
    savingRole.value = false
  }
}

async function runConfirmed() {
  busy.value = true
  try {
    const action = confirm.value
    const response = action === 'block'
      ? await adminApi.blockUser(user.value.id)
      : action === 'unblock' ? await adminApi.unblockUser(user.value.id) : await adminApi.revokeSessions(user.value.id)
    toast.success(response.message)
    await load()
  } catch (e) {
    toast.error(e.message)
  } finally {
    busy.value = false
    confirm.value = null
  }
}

const statTiles = computed(() => {
  const s = details.value?.stats ?? {}
  return [
    ['admin.userStats.trips', s.trips], ['admin.userStats.bookings', s.bookings], ['admin.userStats.requests', s.trip_requests],
    ['admin.userStats.ratingsGiven', s.ratings_given], ['admin.userStats.reportsAgainst', s.reports_against],
    ['admin.userStats.sessions', s.active_sessions], ['admin.userStats.devices', s.devices],
  ]
})

watch(() => props.id, load)
onMounted(async () => {
  await load()
  if (auth.can('roles.manage')) roles.value = (await adminApi.roles()).data
})
</script>

<template>
  <LoadingState v-if="!details" />
  <div v-else class="space-y-5">
    <RouterLink :to="{ name: 'admin-users' }" class="inline-flex items-center gap-1 text-sm text-slate-600 hover:underline">
      <AppIcon name="arrow" class="size-4 rtl:rotate-180" /> {{ $t('admin.users') }}
    </RouterLink>

    <section class="card flex flex-wrap items-center gap-4">
      <UserAvatar :user="user" size="lg" />
      <div class="min-w-0 flex-1">
        <h1 class="text-2xl font-extrabold">{{ user.name }}</h1>
        <p class="flex flex-wrap items-center gap-2 text-sm text-slate-600">
          <StatusBadge kind="user" :status="user.status" />
          <span v-if="user.role" class="rounded bg-indigo-100 px-1.5 py-0.5 text-xs font-semibold text-indigo-800">{{ user.role.display_name }}</span>
          <span v-else>{{ $t('admin.member') }}</span>
          <span>· <AppIcon name="star" class="inline size-4 fill-current text-amber-500" /> {{ user.ratings_count ? `${formatRating(user.rating_average)} (${formatNumber(user.ratings_count)})` : $t('rating.new') }}</span>
        </p>
        <p class="text-xs text-slate-500">{{ $t('admin.joined') }}: {{ formatDateTime(user.created_at) }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink :to="{ name: 'user', params: { id: user.id } }" class="btn-secondary btn-sm"><AppIcon name="eye" class="size-4" /> {{ $t('admin.publicProfile') }}</RouterLink>
        <template v-if="auth.can('users.block') && canModerate">
          <button type="button" class="btn-secondary btn-sm" @click="confirm = 'sessions'"><AppIcon name="logout" class="size-4" /> {{ $t('admin.revokeSessions') }}</button>
          <button v-if="user.status === 'active'" type="button" class="btn-danger btn-sm" @click="confirm = 'block'">{{ $t('admin.block') }}</button>
          <button v-else type="button" class="btn-primary btn-sm" @click="confirm = 'unblock'">{{ $t('admin.unblock') }}</button>
        </template>
      </div>
    </section>

    <section class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
      <div v-for="[label, value] in statTiles" :key="label" class="card !p-3">
        <p class="text-xs text-slate-500">{{ $t(label) }}</p>
        <p class="text-xl font-extrabold">{{ formatNumber(value ?? 0) }}</p>
      </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-2">
      <form class="card space-y-4" novalidate @submit.prevent="save">
        <h2 class="font-extrabold">{{ $t('admin.accountDetails') }}</h2>
        <fieldset :disabled="!auth.can('users.update') || !canModerate" class="space-y-4">
          <FormField v-slot="{ id, invalid }" :label="$t('auth.name')" :error="form.error('name')">
            <input :id="id" v-model.trim="form.fields.name" type="text" class="input" :class="{ 'input-error': invalid }" />
          </FormField>
          <FormField v-slot="{ id, invalid }" :label="$t('auth.phone')" :error="form.error('phone')">
            <input :id="id" v-model.trim="form.fields.phone" type="tel" dir="ltr" class="input text-start" :class="{ 'input-error': invalid }" />
          </FormField>
          <FormField v-slot="{ id, invalid }" :label="$t('auth.email')" :error="form.error('email')">
            <input :id="id" v-model.trim="form.fields.email" type="email" dir="ltr" class="input text-start" :class="{ 'input-error': invalid }" />
          </FormField>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="form.fields.email_verified" type="checkbox" class="size-4 accent-brand-700" />
            {{ $t('admin.emailVerified') }}
          </label>
          <button v-if="auth.can('users.update') && canModerate" type="submit" class="btn-primary" :disabled="form.submitting.value">{{ $t('common.save') }}</button>
        </fieldset>
        <p v-if="!canModerate" class="text-sm text-slate-500">{{ isSelf ? $t('admin.selfHint') : $t('admin.protectedHint') }}</p>
      </form>

      <div class="space-y-5">
        <section class="card space-y-3">
          <h2 class="font-extrabold">{{ $t('admin.staffRole') }}</h2>
          <p class="text-sm text-slate-600">{{ $t('admin.staffRoleHint') }}</p>
          <div v-if="canChangeRole" class="flex flex-wrap gap-2">
            <select v-model="roleId" class="input !w-auto flex-1" :aria-label="$t('admin.role')">
              <option value="">{{ $t('admin.noRole') }}</option>
              <option v-for="r in assignableRoles" :key="r.id" :value="String(r.id)">{{ r.display_name }}</option>
            </select>
            <button type="button" class="btn-primary" :disabled="savingRole" @click="saveRole">{{ $t('admin.saveRole') }}</button>
          </div>
          <p v-else class="text-sm font-semibold">{{ user.role?.display_name || $t('admin.member') }}</p>
          <div v-if="user.permissions?.length" class="flex flex-wrap gap-1">
            <span v-for="p in user.permissions" :key="p" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700" dir="ltr">{{ p }}</span>
          </div>
        </section>

        <section class="card">
          <h2 class="mb-3 font-extrabold">{{ $t('admin.vehicles') }}</h2>
          <ul v-if="details.vehicles.length" class="divide-y divide-slate-100 text-sm">
            <li v-for="v in details.vehicles" :key="v.id" class="flex items-center justify-between gap-2 py-2">
              <span>{{ v.model }} · {{ v.color }} · <span dir="ltr">{{ v.plate_number }}</span></span>
              <span class="text-xs text-slate-500">{{ v.deleted_at ? $t('admin.deleted') : $t('vehicle.tripsCount', { n: formatNumber(v.trips_count ?? 0) }) }}</span>
            </li>
          </ul>
          <p v-else class="text-sm text-slate-500">{{ $t('vehicle.empty') }}</p>
        </section>
      </div>
    </div>

    <PromptDialog
      :open="!!confirm"
      :title="confirm ? t(`admin.confirm.${confirm}.title`) : ''"
      :message="confirm ? t(`admin.confirm.${confirm}.message`) : ''"
      :confirm-label="confirm ? t(`admin.confirm.${confirm}.action`) : ''"
      :danger="confirm !== 'unblock'"
      :busy="busy"
      @close="confirm = null"
      @confirm="runConfirmed"
    />
  </div>
</template>
