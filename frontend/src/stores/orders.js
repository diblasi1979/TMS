import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import { getOrders, createOrder, updateOrder, deleteOrder, cancelOrder, exportPendingOrders } from '@/api/orders.js'

export const useOrdersStore = defineStore('orders', () => {
  const orders  = ref([])
  const meta    = ref({})
  const loading = ref(false)
  const exporting = ref(false)
  const exportResult = ref(null)
  const filters = reactive({ status: '', client_id: '', requested_date: '', route_id: '' })

  async function fetchOrders(page = 1) {
    loading.value = true
    try {
      const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '')
      )
      const { data } = await getOrders({ ...params, page })
      orders.value = data.data
      meta.value   = data.meta
    } finally {
      loading.value = false
    }
  }

  async function addOrder(payload) {
    const { data } = await createOrder(payload)
    await fetchOrders(meta.value?.current_page ?? 1)
    return data
  }

  async function editOrder(id, payload) {
    const { data } = await updateOrder(id, payload)
    await fetchOrders(meta.value?.current_page ?? 1)
    return data
  }

  async function removeOrder(id) {
    await deleteOrder(id)
    await fetchOrders(meta.value?.current_page ?? 1)
  }

  async function cancelOrderById(id) {
    const { data } = await cancelOrder(id)
    const idx = orders.value.findIndex(o => o.id === id)
    if (idx !== -1) orders.value[idx] = data.data
    return data
  }

  async function exportPending(filtersPayload = {}) {
    exporting.value = true
    try {
      const payload = Object.fromEntries(
        Object.entries(filtersPayload).filter(([, value]) => value !== '' && value !== null)
      )
      const { data } = await exportPendingOrders(payload)
      exportResult.value = data
      await fetchOrders(meta.value?.current_page ?? 1)
      return data
    } finally {
      exporting.value = false
    }
  }

  function clearExportResult() {
    exportResult.value = null
  }

  return {
    orders, meta, loading, exporting, exportResult, filters,
    fetchOrders, addOrder, editOrder, removeOrder, cancelOrderById,
    exportPending, clearExportResult,
  }
})
