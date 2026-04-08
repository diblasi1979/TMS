import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import {
  getOperators, createOperator, updateOperator, deleteOperator,
  updateOperatorStatus, getExpiringLicenses,
} from '@/api/operators.js'

export const useOperatorsStore = defineStore('operators', () => {
  const operators = ref([])
  const meta      = ref({})
  const loading   = ref(false)
  const filters   = reactive({ status: '', company_id: '' })

  async function fetchOperators(page = 1) {
    loading.value = true
    try {
      const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '')
      )
      const { data } = await getOperators({ ...params, page })
      operators.value = data.data
      meta.value      = data.meta
    } finally {
      loading.value = false
    }
  }

  async function addOperator(payload) {
    const { data } = await createOperator(payload)
    await fetchOperators(meta.value?.current_page ?? 1)
    return data
  }

  async function editOperator(id, payload) {
    const { data } = await updateOperator(id, payload)
    await fetchOperators(meta.value?.current_page ?? 1)
    return data
  }

  async function removeOperator(id) {
    await deleteOperator(id)
    await fetchOperators(meta.value?.current_page ?? 1)
  }

  async function changeStatus(id, status) {
    const { data } = await updateOperatorStatus(id, status)
    const idx = operators.value.findIndex(o => o.id === id)
    if (idx !== -1) operators.value[idx] = data.data
    return data
  }

  return { operators, meta, loading, filters, fetchOperators, addOperator, editOperator, removeOperator, changeStatus }
})
