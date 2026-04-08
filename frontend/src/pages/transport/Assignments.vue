<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAssignmentsStore } from '@/stores/assignments.js'
import { useVehiclesStore }    from '@/stores/vehicles.js'
import { useOperatorsStore }   from '@/stores/operators.js'

const assignStore  = useAssignmentsStore()
const vehicleStore = useVehiclesStore()
const opStore      = useOperatorsStore()

const tab              = ref('active')
const showModal        = ref(false)
const saving           = ref(false)
const error            = ref('')
const selectedVehicle  = ref(null)
const selectedOperator = ref(null)
const notes            = ref('')

const availableVehicles  = computed(() =>
  vehicleStore.vehicles.filter(v => v.status === 'available')
)
const availableOperators = computed(() =>
  opStore.operators.filter(o => o.status === 'available')
)

function openAssign() {
  selectedVehicle.value  = null
  selectedOperator.value = null
  notes.value  = ''
  error.value  = ''
  showModal.value = true
}

async function save() {
  if (!selectedVehicle.value || !selectedOperator.value) {
    error.value = 'Debe seleccionar un vehículo y un operador'
    return
  }
  saving.value = true
  error.value  = ''
  try {
    await assignStore.assign({
      vehicle_id:  selectedVehicle.value,
      operator_id: selectedOperator.value,
      notes:       notes.value || null,
    })
    showModal.value = false
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al asignar'
  } finally {
    saving.value = false
  }
}

async function release(id) {
  if (!confirm('¿Liberar esta asignación?')) return
  try {
    await assignStore.release(id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'Error al liberar')
  }
}

function switchTab(t) {
  tab.value = t
  if (t === 'history') assignStore.fetchHistory(1)
}

function formatDate(str) {
  if (!str) return '—'
  return new Date(str).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(async () => {
  await Promise.all([
    assignStore.fetchActive(),
    vehicleStore.fetchVehicles(),
    opStore.fetchOperators(),
  ])
})
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Asignaciones</h1>
      <button class="btn btn--primary" @click="openAssign">+ Nueva Asignación</button>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button :class="['tab', { active: tab === 'active' }]" @click="switchTab('active')">
        Activas ({{ assignStore.activeAssignments.length }})
      </button>
      <button :class="['tab', { active: tab === 'history' }]" @click="switchTab('history')">
        Historial
      </button>
    </div>

    <!-- Asignaciones activas -->
    <div v-if="tab === 'active'" class="card">
      <div v-if="assignStore.loading" class="loading-text">Cargando…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Vehículo</th>
            <th>Operador</th>
            <th>Asignado</th>
            <th>Notas</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!assignStore.activeAssignments.length">
            <td colspan="5" class="empty-row">No hay asignaciones activas</td>
          </tr>
          <tr v-for="a in assignStore.activeAssignments" :key="a.id">
            <td>{{ a.vehicle?.plate }} — {{ a.vehicle?.brand }} {{ a.vehicle?.model }}</td>
            <td>{{ a.operator?.name }}</td>
            <td>{{ formatDate(a.assigned_at) }}</td>
            <td>{{ a.notes ?? '—' }}</td>
            <td class="actions">
              <button class="btn btn--sm btn--danger" @click="release(a.id)">Liberar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Historial -->
    <div v-if="tab === 'history'" class="card">
      <div v-if="assignStore.loading" class="loading-text">Cargando…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Vehículo</th>
            <th>Operador</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Notas</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!assignStore.history.length">
            <td colspan="5" class="empty-row">Sin registros</td>
          </tr>
          <tr v-for="a in assignStore.history" :key="a.id">
            <td>{{ a.vehicle?.plate }} — {{ a.vehicle?.brand }} {{ a.vehicle?.model }}</td>
            <td>{{ a.operator?.name }}</td>
            <td>{{ formatDate(a.assigned_at) }}</td>
            <td>{{ formatDate(a.released_at) }}</td>
            <td>{{ a.notes ?? '—' }}</td>
          </tr>
        </tbody>
      </table>

      <div v-if="assignStore.historyMeta?.last_page > 1" class="pagination">
        <button :disabled="assignStore.historyMeta.current_page === 1"
                @click="assignStore.fetchHistory(assignStore.historyMeta.current_page - 1)">‹</button>
        <span>{{ assignStore.historyMeta.current_page }} / {{ assignStore.historyMeta.last_page }}</span>
        <button :disabled="assignStore.historyMeta.current_page === assignStore.historyMeta.last_page"
                @click="assignStore.fetchHistory(assignStore.historyMeta.current_page + 1)">›</button>
      </div>
    </div>

    <!-- Modal nueva asignación -->
    <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
      <div class="modal modal--wide">
        <div class="modal-header">
          <h3>Nueva Asignación</h3>
          <button class="modal-close" @click="showModal = false">✕</button>
        </div>
        <form @submit.prevent="save">
          <div v-if="error" class="alert alert--error">{{ error }}</div>

          <div class="assign-panels">
            <div class="assign-panel">
              <h4>Vehículos disponibles</h4>
              <div
                v-for="v in availableVehicles" :key="v.id"
                class="assign-card"
                :class="{ selected: selectedVehicle === v.id }"
                @click="selectedVehicle = v.id"
              >
                <strong>{{ v.plate }}</strong>
                <span>{{ v.brand }} {{ v.model }}</span>
              </div>
              <p v-if="!availableVehicles.length" class="empty-panel">Sin vehículos disponibles</p>
            </div>

            <div class="assign-panel">
              <h4>Operadores disponibles</h4>
              <div
                v-for="op in availableOperators" :key="op.id"
                class="assign-card"
                :class="{ selected: selectedOperator === op.id }"
                @click="selectedOperator = op.id"
              >
                <strong>{{ op.name }}</strong>
                <span>Lic. {{ op.license_type }}</span>
              </div>
              <p v-if="!availableOperators.length" class="empty-panel">Sin operadores disponibles</p>
            </div>
          </div>

          <div class="field" style="margin-top:1rem">
            <label>Notas</label>
            <textarea v-model="notes" rows="2" placeholder="Opcional…"></textarea>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn--outline" @click="showModal = false">Cancelar</button>
            <button type="submit" class="btn btn--primary" :disabled="saving">
              {{ saving ? 'Asignando…' : 'Confirmar Asignación' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import '@/assets/admin.css';

.tabs { display: flex; margin-bottom: 1rem; border-bottom: 2px solid #e5e7eb; }
.tab {
  padding: 0.6rem 1.25rem;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  cursor: pointer;
  color: #6b7280;
  font-size: 0.9rem;
  transition: color 0.15s, border-color 0.15s;
}
.tab.active { color: #1e3a5f; border-bottom-color: #1e3a5f; font-weight: 600; }
.tab:hover  { color: #1e3a5f; }

.modal--wide { max-width: 720px; }

.assign-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.assign-panel {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 0.75rem;
  max-height: 240px;
  overflow-y: auto;
}
.assign-panel h4 {
  font-size: 0.8rem;
  color: #6b7280;
  margin: 0 0 0.6rem;
  text-transform: uppercase;
  font-weight: 600;
}
.assign-card {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  padding: 0.55rem 0.75rem;
  border: 2px solid transparent;
  border-radius: 6px;
  cursor: pointer;
  margin-bottom: 0.4rem;
  font-size: 0.875rem;
  transition: border-color 0.15s, background 0.15s;
}
.assign-card:hover    { background: #f0f4ff; }
.assign-card.selected { border-color: #2563eb; background: #eff6ff; }
.empty-panel { color: #9ca3af; font-size: 0.8rem; text-align: center; padding: 0.75rem; }
</style>
