import { reactive, ref } from 'vue'
import { useToastStore } from '@/stores/toast'

/**
 * Small form helper: holds field values, submitting state and server-side
 * validation errors (422 -> errors[field][0]).
 */
export function useForm(initial) {
  const fields = reactive({ ...initial })
  const errors = ref({})
  const submitting = ref(false)
  const toast = useToastStore()

  function error(field) {
    return errors.value[field]?.[0]
  }

  async function submit(action, { successMessage = true, onError } = {}) {
    submitting.value = true
    errors.value = {}
    try {
      const response = await action({ ...fields })
      if (successMessage && response?.message) toast.success(response.message)
      return response
    } catch (e) {
      errors.value = e.errors || {}
      if (onError) onError(e)
      else toast.error(e.message)
      throw e
    } finally {
      submitting.value = false
    }
  }

  function reset(values = initial) {
    Object.assign(fields, values)
    errors.value = {}
  }

  return { fields, errors, submitting, error, submit, reset }
}
