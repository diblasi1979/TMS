import { defineStore } from 'pinia'
import { ref } from 'vue'
import {
  getAssignments, getActiveAssignments, createAssignment,
  releaseAssignment, getAlerts,
} from '@/api/assignments.js'

export const useAssignmentsStore = defineStore('assignments', () => {
  const activeAssignments = ref([])
  const history           = ref([])
  const historyMeta       = ref({})
  const alerts            = ref({ vehicle_alerts: [], operator_alerts: [], total: 0 })
  const loading           = ref(false)

  async function fetchActive() {
    loading.value = true
    try {
      const { data } = await getActiveAssignments()
      activeAssignments.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function fetchHistory(page = 1) {
    loading.value = true
    try {
      const { data } = await getAssignments(page)
      history.value     = data.data
      historyMeta.value = data.meta
    } finally {
      loading.value = false
    }
  }

  async function assign(payload) {
    const { data } = await createAssignment(payload)
    await fetchActive()
    return data
  }

  async function release(id) {
    await releaseAssignment(id)
    await fetchActive()
  }

  async function fetchAlerts() {
    const { data } = await getAlerts()
    alerts.value = data
  }

  return { activeAssignments, history, historyMeta, alerts, loading, fetchActive, fetchHistory, assign, release, fetchAlerts }
})
