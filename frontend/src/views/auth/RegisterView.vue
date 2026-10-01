<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useForm } from '@/composables/useForm'
import FormField from '@/components/FormField.vue'

const auth = useAuthStore()
const router = useRouter()
const { fields, error, submitting, submit } = useForm({
  name: '', phone: '', email: '', password: '', password_confirmation: '',
})

async function onSubmit() {
  await submit((data) => auth.register(data), { successMessage: false })
  router.replace({ name: 'home' })
}
</script>

<template>
  <form class="card space-y-4" novalidate @submit.prevent="onSubmit">
    <h1 class="text-2xl font-extrabold">{{ $t('auth.register') }}</h1>

    <FormField v-slot="{ id, invalid }" :label="$t('auth.name')" :error="error('name')">
      <input :id="id" v-model.trim="fields.name" type="text" autocomplete="name" class="input" :class="{ 'input-error': invalid }" required />
    </FormField>

    <FormField v-slot="{ id, invalid }" :label="$t('auth.phone')" :error="error('phone')" :hint="$t('auth.phoneHint')">
      <input :id="id" v-model.trim="fields.phone" type="tel" inputmode="tel" dir="ltr" autocomplete="tel" placeholder="01xxxxxxxxx" class="input text-start" :class="{ 'input-error': invalid }" required />
    </FormField>

    <FormField v-slot="{ id, invalid }" :label="$t('auth.email')" :error="error('email')">
      <input :id="id" v-model.trim="fields.email" type="email" dir="ltr" autocomplete="email" class="input text-start" :class="{ 'input-error': invalid }" required />
    </FormField>

    <FormField v-slot="{ id, invalid }" :label="$t('auth.password')" :error="error('password')" :hint="$t('auth.passwordHint')">
      <input :id="id" v-model="fields.password" type="password" autocomplete="new-password" class="input" :class="{ 'input-error': invalid }" required />
    </FormField>

    <FormField v-slot="{ id }" :label="$t('auth.passwordConfirm')">
      <input :id="id" v-model="fields.password_confirmation" type="password" autocomplete="new-password" class="input" required />
    </FormField>

    <button type="submit" class="btn-primary w-full" :disabled="submitting">{{ $t('auth.createAccount') }}</button>

    <p class="text-center text-sm text-slate-600">
      {{ $t('auth.haveAccount') }}
      <RouterLink :to="{ name: 'login' }" class="font-bold text-brand-700 hover:underline">{{ $t('auth.login') }}</RouterLink>
    </p>
  </form>
</template>
