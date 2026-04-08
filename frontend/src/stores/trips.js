import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import {
  getTrips, createTrip, updateTrip, deleteTrip,
  confirmTrip, startTrip, completeTrip, cancelTrip
} from '@/api/trips.js'

export const useTripsStore = defineStore('trips', () => {
  const trips   = ref([])
  const meta    = ref({})
  const loading = ref(false)
  const filters = reactive({ status: '', priority: '', vehicle_id: '', operator_id: '', from: '', to: '' })

  async function fetchTrips(page = 1) {
    loading.value = true
    try {
      const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '')
      )
      const { data } = await getTrips({ ...params, page })
      trips.value = data.data
      meta.value  = data.meta
    } finally {
      loading.value = false
    }
  }

  async function addTrip(payload) {
    const { data } = await createTrip(payload)
    await fetchTrips(meta.value?.current_page ?? 1)
    return data
  }

  async function editTrip(id, payload) {
    const { data } = await updateTrip(id, payload)
    await fetchTrips(meta.value?.current_page ?? 1)
    return data
  }

  async function removeTrip(id) {
    await deleteTrip(id)
    await fetchTrips(meta.value?.current_page ?? 1)
  }

  async function confirm(id, payload) {
    const { data } = await confirmTrip(id, payload)
    const idx = trips.value.findIndex(t => t.id === id)
    if (idx !== -1) trips.value[idx] = data.data ?? data
    return data
  }

  async function start(id) {
    const { data } = await startTrip(id)
    const idx = trips.value.findIndex(t => t.id === id)
    if (idx !== -1) trips.value[idx] = data.data ?? data
    return data
  }

  async function complete(id) {
    const { data } = await completeTrip(id)
    const idx = trips.value.findIndex(t => t.id === id)
    if (idx !== -1) trips.value[idx] = data.data ?? data
    return data
  }

  async function cancel(id) {
    const { data } = await cancelTrip(id)
    const idx = trips.value.findIndex(t => t.id === id)
    if (idx !== -1) trips.value[idx] = data.data ?? data
    return data
  }

  return {
    trips, meta, loading, filters,
    fetchTrips, addTrip, editTrip, removeTrip,
    confirm, start, complete, cancel
  }
})
