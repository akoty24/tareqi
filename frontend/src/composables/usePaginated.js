import { ref, watch } from 'vue'
import { useToastStore } from '@/stores/toast'

/**
 * Loads a paginated endpoint: fetcher(params) -> { data, meta }.
 * Re-fetches when `filters` (a reactive object) changes.
 */
export function usePaginated(fetcher, filters = null) {
  const items = ref([])
  const meta = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const page = ref(1)
  const toast = useToastStore()

  async function load(p = page.value) {
    loading.value = true
    error.value = null
    try {
      const cleaned = Object.fromEntries(
        Object.entries(filters ? { ...filters } : {}).filter(([, v]) => v !== '' && v !== null && v !== undefined),
      )
      const response = await fetcher({ ...cleaned, page: p })
      items.value = response.data
      meta.value = response.meta ?? null
      page.value = p
    } catch (e) {
      error.value = e
      toast.error(e.message)
    } finally {
      loading.value = false
    }
  }

  if (filters) {
    watch(() => ({ ...filters }), () => load(1), { deep: true })
  }

  return { items, meta, loading, error, page, load }
}
