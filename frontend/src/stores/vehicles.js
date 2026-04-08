import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import {
  getVehicles, createVehicle, updateVehicle, deleteVehicle,
  updateVehicleStatus, getExpiringVehicles,
} from '@/api/vehicles.js'

export const useVehiclesStore = defineStore('vehicles', () => {
  const vehicles = ref([])
  const meta     = ref({})
  const loading  = ref(false)
  const filters  = reactive({ status: '', type: '', company_id: '' })

  async function fetchVehicles(page = 1) {
    loading.value = true
    try {
      const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '')
      )
      const { data } = await getVehicles({ ...params, page })
      vehicles.value = data.data
      meta.value     = data.meta
    } finally {
      loading.value = false
    }
  }

  async function addVehicle(payload) {
    const { data } = await createVehicle(payload)
    await fetchVehicles(meta.value?.current_page ?? 1)
    return data
  }

  async function editVehicle(id, payload) {
    const { data } = await updateVehicle(id, payload)
    await fetchVehicles(meta.value?.current_page ?? 1)
    return data
  }

  async function removeVehicle(id) {
    await deleteVehicle(id)
    await fetchVehicles(meta.value?.current_page ?? 1)
  }

  async function changeStatus(id, status) {
    const { data } = await updateVehicleStatus(id, status)
    const idx = vehicles.value.findIndex(v => v.id === id)
    if (idx !== -1) vehicles.value[idx] = data.data
    return data
  }

  return { vehicles, meta, loading, filters, fetchVehicles, addVehicle, editVehicle, removeVehicle, changeStatus }
})
