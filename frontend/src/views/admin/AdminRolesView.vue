<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { adminApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { useForm } from '@/composables/useForm'
import { formatNumber } from '@/utils/format'
import FormField from '@/components/FormField.vue'
import LoadingState from '@/components/LoadingState.vue'
import ModalDialog from '@/components/ModalDialog.vue'
import PromptDialog from '@/components/PromptDialog.vue'
import AppIcon from '@/components/AppIcon.vue'

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()
const roles = ref(null)
const groups = ref([])
const editing = ref(null) // role object, or {} for a new role
const deleting = ref(null)
const form = useForm({ display_name: '', description: '', permissions: [] })

const totalPermissions = computed(() => groups.value.reduce((n, g) => n + g.permissions.length, 0))
const labelOf = computed(() => Object.fromEntries(groups.value.flatMap((g) => g.permissions.map((p) => [p.value, p.label]))))

async function load() {
  const [r, p] = await Promise.all([adminApi.roles(), adminApi.permissions()])
  roles.value = r.data
  groups.value = p.data
}

function open(role = {}) {
  editing.value = role
  form.reset({ display_name: role.display_name ?? '', description: role.description ?? '', permissions: [...(role.permissions ?? [])] })
}

function toggleGroup(group, checked) {
  const values = group.permissions.map((p) => p.value)
  const rest = form.fields.permissions.filter((v) => !values.includes(v))
  form.fields.permissions = checked ? [...rest, ...values] : rest
}

const groupState = (group) => {
  const n = group.permissions.filter((p) => form.fields.permissions.includes(p.value)).length
  return n === 0 ? 'none' : n === group.permissions.length ? 'all' : 'some'
}

async function save() {
  const id = editing.value.id
  await form.submit((payload) => (id ? adminApi.updateRole(id, payload) : adminApi.createRole(payload)))
  editing.value = null
  await load()
  // Editing my own role changes what I can see.
  if (id && id === auth.user?.role?.id) await auth.refresh()
}

async function remove() {
  try {
    toast.success((await adminApi.deleteRole(deleting.value.id)).message)
    await load()
  } catch (e) {
    toast.error(e.message)
  } finally {
    deleting.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <div>
        <h1 class="page-title !mb-1">{{ $t('admin.rolesNav') }}</h1>
        <p class="text-sm text-slate-600">{{ $t('admin.rolesHint') }}</p>
      </div>
      <button type="button" class="btn-primary btn-sm" @click="open()"><AppIcon name="plus" class="size-4" /> {{ $t('admin.newRole') }}</button>
    </div>

    <LoadingState v-if="!roles" />
    <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="role in roles" :key="role.id" class="card flex flex-col gap-3" :class="{ '!ring-amber-300': role.is_super }">
        <div class="flex items-start justify-between gap-2">
          <div>
            <h2 class="flex items-center gap-2 text-lg font-extrabold">
              <AppIcon :name="role.is_super ? 'shield' : 'lock'" class="size-5" :class="role.is_super ? 'text-amber-500' : 'text-brand-700'" />
              {{ role.display_name }}
            </h2>
            <p class="text-sm text-slate-600">{{ role.description || '—' }}</p>
          </div>
          <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold">{{ $t('admin.usersCount', { n: formatNumber(role.users_count ?? 0) }, role.users_count ?? 0) }}</span>
        </div>
        <p class="text-sm font-semibold text-slate-700">
          {{ role.is_super ? $t('admin.allPermissions') : $t('admin.permissionsCount', { n: formatNumber(role.permissions.length), total: formatNumber(totalPermissions) }) }}
        </p>
        <div v-if="!role.is_super" class="flex flex-wrap gap-1">
          <span v-for="p in role.permissions.slice(0, 8)" :key="p" class="rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-800">{{ labelOf[p] || p }}</span>
          <span v-if="role.permissions.length > 8" class="text-xs text-slate-500">+{{ formatNumber(role.permissions.length - 8) }}</span>
        </div>
        <div class="mt-auto flex gap-2">
          <template v-if="!role.is_system">
            <button type="button" class="btn-secondary btn-sm" @click="open(role)"><AppIcon name="edit" class="size-4" /> {{ $t('common.edit') }}</button>
            <button type="button" class="btn-ghost btn-sm text-red-600" @click="deleting = role"><AppIcon name="trash" class="size-4" /> {{ $t('common.delete') }}</button>
          </template>
          <span v-else class="text-xs text-slate-500">{{ $t('admin.systemRole') }}</span>
          <RouterLink :to="{ name: 'admin-users', query: { role: role.id } }" class="btn-ghost btn-sm ms-auto">{{ $t('admin.users') }}</RouterLink>
        </div>
      </article>
    </div>

    <ModalDialog :open="!!editing" :title="editing?.id ? $t('admin.editRole') : $t('admin.newRole')" @close="editing = null">
      <form id="role-form" class="space-y-4" novalidate @submit.prevent="save">
        <FormField v-slot="{ id, invalid }" :label="$t('admin.roleName')" :error="form.error('display_name')">
          <input :id="id" v-model.trim="form.fields.display_name" type="text" maxlength="100" class="input" :class="{ 'input-error': invalid }" />
        </FormField>
        <FormField v-slot="{ id }" :label="$t('admin.roleDescription')" :error="form.error('description')">
          <input :id="id" v-model.trim="form.fields.description" type="text" maxlength="255" class="input" />
        </FormField>
        <fieldset>
          <legend class="label">{{ $t('admin.permissions') }}</legend>
          <p v-if="form.error('permissions')" class="mb-2 text-sm text-red-600">{{ form.error('permissions') }}</p>
          <div class="space-y-3">
            <div v-for="group in groups" :key="group.key" class="rounded-xl ring-1 ring-slate-200">
              <label class="flex items-center gap-2 rounded-t-xl bg-slate-50 px-3 py-2 text-sm font-bold">
                <input
                  type="checkbox"
                  class="size-4 accent-brand-700"
                  :checked="groupState(group) === 'all'"
                  :indeterminate="groupState(group) === 'some'"
                  @change="toggleGroup(group, $event.target.checked)"
                />
                {{ group.label }}
              </label>
              <div class="grid gap-1 px-3 py-2 sm:grid-cols-2">
                <label v-for="p in group.permissions" :key="p.value" class="flex items-center gap-2 text-sm">
                  <input v-model="form.fields.permissions" type="checkbox" :value="p.value" class="size-4 accent-brand-700" />
                  {{ p.label }}
                </label>
              </div>
            </div>
          </div>
        </fieldset>
      </form>
      <template #actions>
        <button type="button" class="btn-secondary" @click="editing = null">{{ $t('common.cancel') }}</button>
        <button type="submit" form="role-form" class="btn-primary" :disabled="form.submitting.value">{{ $t('common.save') }}</button>
      </template>
    </ModalDialog>

    <PromptDialog
      :open="!!deleting"
      :title="t('admin.deleteRoleTitle')"
      :message="deleting ? t('admin.deleteRoleMessage', { name: deleting.display_name }) : ''"
      :confirm-label="t('common.delete')"
      danger
      @close="deleting = null"
      @confirm="remove"
    />
  </div>
</template>
