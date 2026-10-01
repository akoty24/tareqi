<script setup>
import { useRoute, useRouter } from 'vue-router'
import { authApi } from '@/api'
import { useForm } from '@/composables/useForm'
import FormField from '@/components/FormField.vue'

const route = useRoute()
const router = useRouter()
const { fields, error, submitting, submit } = useForm({
  token: String(route.query.token || ''),
  email: String(route.query.email || ''),
  password: '',
  password_confirmation: '',
})

async function onSubmit() {
  await submit((data) => authApi.resetPassword(data))
  router.replace({ name: 'login' })
}
</script>

<template>
  <form class="card space-y-4" novalidate @submit.prevent="onSubmit">
    <h1 class="text-2xl font-extrabold">{{ $t('auth.resetPassword') }}</h1>

    <FormField v-slot="{ id, invalid }" :label="$t('auth.email')" :error="error('email') || error('token')">
      <input :id="id" v-model.trim="fields.email" type="email" dir="ltr" class="input text-start" :class="{ 'input-error': invalid }" required />
    </FormField>
    <FormField v-slot="{ id, invalid }" :label="$t('auth.newPassword')" :error="error('password')" :hint="$t('auth.passwordHint')">
      <input :id="id" v-model="fields.password" type="password" autocomplete="new-password" class="input" :class="{ 'input-error': invalid }" required />
    </FormField>
    <FormField v-slot="{ id }" :label="$t('auth.passwordConfirm')">
      <input :id="id" v-model="fields.password_confirmation" type="password" autocomplete="new-password" class="input" required />
    </FormField>

    <button type="submit" class="btn-primary w-full" :disabled="submitting || !fields.token">{{ $t('auth.resetPassword') }}</button>
  </form>
</template>
