import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import {
  getRoutes, createRoute, updateRoute, deleteRoute,
  addOrderToRoute, removeOrderFromRoute,
  dispatchRoute, completeRoute, cancelRoute,
} from '@/api/routes.js'

export const useRoutesStore = defineStore('routes', () => {
  const routes  = ref([])
  const meta    = ref({})
  const loading = ref(false)
  const filters = reactive({ status: '', planned_date: '', vehicle_id: '', trip_plan_id: '' })

  async function fetchRoutes(page = 1) {
    loading.value = true
    try {
      const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '')
      )
      const { data } = await getRoutes({ ...params, page })
      routes.value = data.data
      meta.value   = data.meta
    } finally {
      loading.value = false
    }
  }

  async function addRoute(payload) {
    const { data } = await createRoute(payload)
    await fetchRoutes(meta.value?.current_page ?? 1)
    return data
  }

  async function editRoute(id, payload) {
    const { data } = await updateRoute(id, payload)
    await fetchRoutes(meta.value?.current_page ?? 1)
    return data
  }

  async function removeRoute(id) {
    await deleteRoute(id)
    await fetchRoutes(meta.value?.current_page ?? 1)
  }

  async function attachOrder(routeId, orderId) {
    const { data } = await addOrderToRoute(routeId, orderId)
    const idx = routes.value.findIndex(r => r.id === routeId)
    if (idx !== -1) routes.value[idx] = data.data
    return data
  }

  async function detachOrder(routeId, orderId) {
    const { data } = await removeOrderFromRoute(routeId, orderId)
    const idx = routes.value.findIndex(r => r.id === routeId)
    if (idx !== -1) routes.value[idx] = data.data
    return data
  }

  async function dispatch(routeId) {
    const { data } = await dispatchRoute(routeId)
    await fetchRoutes(meta.value?.current_page ?? 1)
    return data
  }

  async function complete(routeId) {
    const { data } = await completeRoute(routeId)
    await fetchRoutes(meta.value?.current_page ?? 1)
    return data
  }

  async function cancel(routeId) {
    const { data } = await cancelRoute(routeId)
    await fetchRoutes(meta.value?.current_page ?? 1)
    return data
  }

  return {
    routes, meta, loading, filters,
    fetchRoutes, addRoute, editRoute, removeRoute,
    attachOrder, detachOrder, dispatch, complete, cancel,
  }
})
