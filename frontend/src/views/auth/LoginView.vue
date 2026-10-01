<script setup>
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useForm } from '@/composables/useForm'
import FormField from '@/components/FormField.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const { fields, error, submitting, submit } = useForm({ login: '', password: '' })

async function onSubmit() {
  await submit((data) => auth.login(data), { successMessage: false })
  router.replace(typeof route.query.redirect === 'string' ? route.query.redirect : { name: 'home' })
}
</script>

<template>
  <form class="card space-y-4" novalidate @submit.prevent="onSubmit">
    <h1 class="text-2xl font-extrabold">{{ $t('auth.login') }}</h1>

    <FormField v-slot="{ id, invalid }" :label="$t('auth.emailOrPhone')" :error="error('login')">
      <input :id="id" v-model.trim="fields.login" type="text" inputmode="email" autocomplete="username" class="input" :class="{ 'input-error': invalid }" required />
    </FormField>

    <FormField v-slot="{ id, invalid }" :label="$t('auth.password')" :error="error('password')">
      <input :id="id" v-model="fields.password" type="password" autocomplete="current-password" class="input" :class="{ 'input-error': invalid }" required />
    </FormField>

    <div class="text-end">
      <RouterLink :to="{ name: 'forgot-password' }" class="text-sm font-semibold text-brand-700 hover:underline">{{ $t('auth.forgotPassword') }}</RouterLink>
    </div>

    <button type="submit" class="btn-primary w-full" :disabled="submitting">{{ $t('auth.login') }}</button>

    <p class="text-center text-sm text-slate-600">
      {{ $t('auth.noAccount') }}
      <RouterLink :to="{ name: 'register' }" class="font-bold text-brand-700 hover:underline">{{ $t('auth.register') }}</RouterLink>
    </p>
  </form>
</template>
