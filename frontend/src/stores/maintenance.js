import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import {
  getMaintenances, createMaintenance, updateMaintenance, deleteMaintenance,
  startMaintenance, completeMaintenance, cancelMaintenance
} from '@/api/maintenance.js'

export const useMaintenanceStore = defineStore('maintenance', () => {
  const items   = ref([])
  const meta    = ref({})
  const loading = ref(false)
  const filters = reactive({ status: '', vehicle_id: '', from: '', to: '' })

  async function fetchAll(page = 1) {
    loading.value = true
    try {
      const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '')
      )
      const { data } = await getMaintenances({ ...params, page })
      items.value = data.data
      meta.value  = data.meta
    } finally {
      loading.value = false
    }
  }

  async function add(payload) {
    const { data } = await createMaintenance(payload)
    await fetchAll(meta.value?.current_page ?? 1)
    return data
  }

  async function edit(id, payload) {
    const { data } = await updateMaintenance(id, payload)
    await fetchAll(meta.value?.current_page ?? 1)
    return data
  }

  async function remove(id) {
    await deleteMaintenance(id)
    await fetchAll(meta.value?.current_page ?? 1)
  }

  function updateItem(id, updated) {
    const idx = items.value.findIndex(i => i.id === id)
    if (idx !== -1) items.value[idx] = updated.data ?? updated
  }

  async function start(id) {
    const { data } = await startMaintenance(id)
    updateItem(id, data)
    return data
  }

  async function complete(id) {
    const { data } = await completeMaintenance(id)
    updateItem(id, data)
    return data
  }

  async function cancel(id) {
    const { data } = await cancelMaintenance(id)
    updateItem(id, data)
    return data
  }

  return { items, meta, loading, filters, fetchAll, add, edit, remove, start, complete, cancel }
})
