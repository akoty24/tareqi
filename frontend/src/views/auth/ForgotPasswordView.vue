<script setup>
import { ref } from 'vue'
import { authApi } from '@/api'
import { useForm } from '@/composables/useForm'
import FormField from '@/components/FormField.vue'

const sent = ref(false)
const { fields, error, submitting, submit } = useForm({ email: '' })

async function onSubmit() {
  await submit((data) => authApi.forgotPassword(data.email))
  sent.value = true
}
</script>

<template>
  <form class="card space-y-4" novalidate @submit.prevent="onSubmit">
    <h1 class="text-2xl font-extrabold">{{ $t('auth.forgotPassword') }}</h1>
    <p class="text-slate-600">{{ sent ? $t('auth.resetSent') : $t('auth.forgotHint') }}</p>

    <template v-if="!sent">
      <FormField v-slot="{ id, invalid }" :label="$t('auth.email')" :error="error('email')">
        <input :id="id" v-model.trim="fields.email" type="email" dir="ltr" class="input text-start" :class="{ 'input-error': invalid }" required />
      </FormField>
      <button type="submit" class="btn-primary w-full" :disabled="submitting">{{ $t('auth.sendResetLink') }}</button>
    </template>

    <p class="text-center text-sm">
      <RouterLink :to="{ name: 'login' }" class="font-bold text-brand-700 hover:underline">{{ $t('auth.backToLogin') }}</RouterLink>
    </p>
  </form>
</template>
