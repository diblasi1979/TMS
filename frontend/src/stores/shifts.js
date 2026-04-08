import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import { getShifts, createShift, updateShift, deleteShift } from '@/api/shifts.js'

export const useShiftsStore = defineStore('shifts', () => {
  const shifts  = ref([])
  const meta    = ref({})
  const loading = ref(false)
  const filters = reactive({ operator_id: '', shift_type: '', from: '', to: '' })

  async function fetchShifts(page = 1) {
    loading.value = true
    try {
      const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '')
      )
      const { data } = await getShifts({ ...params, page })
      shifts.value = data.data
      meta.value   = data.meta
    } finally {
      loading.value = false
    }
  }

  async function addShift(payload) {
    const { data } = await createShift(payload)
    await fetchShifts(meta.value?.current_page ?? 1)
    return data
  }

  async function editShift(id, payload) {
    const { data } = await updateShift(id, payload)
    await fetchShifts(meta.value?.current_page ?? 1)
    return data
  }

  async function removeShift(id) {
    await deleteShift(id)
    await fetchShifts(meta.value?.current_page ?? 1)
  }

  return { shifts, meta, loading, filters, fetchShifts, addShift, editShift, removeShift }
})
