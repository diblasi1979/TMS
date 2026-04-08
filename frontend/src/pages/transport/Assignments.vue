<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAssignmentsStore } from '@/stores/assignments.js'
import { useVehiclesStore }    from '@/stores/vehicles.js'
import { useOperatorsStore }   from '@/stores/operators.js'

const assignStore  = useAssignmentsStore()
const vehicleStore = useVehiclesStore()
const opStore      = useOperatorsStore()

const tab         = ref('active')   // 'active' | 'history'
const showModal   = ref(false)
const saving      = ref(false)
const error       = ref('')
const selectedVehicle  = ref(null)
const selectedOperator = ref(null)
const notes       = ref('')

const availableVehicles  = computed(() =>
  vehicleStore.vehicles.filter(v => v.status === 'available')
)
const availableOperators = computed(() =>
  opStore.operators.filter(o => o.status === 'available')
)

function openAssign() {
  selectedVehicle.value  = null
  selectedOperator.value = null
  notes.value = ''
  error.value = ''
  showModal.value = true
}

async function save() {
  if (!selectedVehicle.value || !selectedOperator.value) {
    error.value = 'Debe seleccionar vehículo y operador'
    return
  }
  saving.value = true
  error.value  = ''
  try {
    await assignStore.assign({
      vehicle_id:  selectedVehicle.value,
      operator_id: selectedOperator.value,
      notes:       notes.value,
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
  <div class="page">
    <div class="page-header">
      <h1>Asignaciones</h1>
      <button class="btn btn-primary" @click="openAssign">+ Nueva Asignación</button>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button :class="['tab', { active: tab === 'active' }]"   @click="switchTab('active')">
        Activas ({{ assignStore.activeAssignments.length }})
      </button>
      <button :class="['tab', { active: tab === 'history' }]"  @click="switchTab('history')">
        Historial
      </button>
    </div>

    <!-- Asignaciones activas -->
    <div v-if="tab === 'active'" class="card">
      <div v-if="assignStore.loading" class="loading">Cargando…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Vehículo</th><th>Operador</th><th>Asignado</th>
            <th>Notas</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in assignStore.activeAssignments" :key="a.id">
            <td>{{ a.vehicle?.plate }} — {{ a.vehicle?.brand }} {{ a.vehicle?.model }}</td>
            <td>{{ a.operator?.name }}</td>
            <td>{{ formatDate(a.assigned_at) }}</td>
            <td>{{ a.notes ?? '—' }}</td>
            <td>
              <button class="btn btn-sm btn-danger" @click="release(a.id)">Liberar</button>
            </td>
          </tr>
          <tr v-if="!assignStore.activeAssignments.length">
            <td colspan="5" class="empty">No hay asignaciones activas</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Historial -->
    <div v-if="tab === 'history'" class="card">
      <div v-if="assignStore.loading" class="loading">Cargando…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Vehículo</th><th>Operador</th><th>Inicio</th>
            <th>Fin</th><th>Notas</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in assignStore.history" :key="a.id">
            <td>{{ a.vehicle?.plate }} — {{ a.vehicle?.brand }} {{ a.vehicle?.model }}</td>
            <td>{{ a.operator?.name }}</td>
            <td>{{ formatDate(a.assigned_at) }}</td>
            <td>{{ formatDate(a.released_at) }}</td>
            <td>{{ a.notes ?? '—' }}</td>
          </tr>
          <tr v-if="!assignStore.history.length">
            <td colspan="5" class="empty">Sin registros</td>
          </tr>
        </tbody>
      </table>

      <div v-if="assignStore.historyMeta?.last_page > 1" class="pagination">
        <button
          v-for="p in assignStore.historyMeta.last_page" :key="p"
          class="btn btn-sm"
          :class="{ 'btn-primary': p === assignStore.historyMeta.current_page }"
          @click="assignStore.fetchHistory(p)"
        >{{ p }}</button>
      </div>
    </div>

    <!-- Modal nueva asignación -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal">
          <div class="modal-header">
            <h2>Nueva Asignación</h2>
            <button class="close-btn" @click="showModal = false">✕</button>
          </div>
          <div class="modal-body">
            <p v-if="error" class="form-error">{{ error }}</p>

            <div class="assign-panels">
              <!-- Vehículos disponibles -->
              <div class="assign-panel">
                <h3>Vehículos disponibles</h3>
                <div
                  v-for="v in availableVehicles" :key="v.id"
                  class="assign-card"
                  :class="{ selected: selectedVehicle === v.id }"
                  @click="selectedVehicle = v.id"
                >
                  <strong>{{ v.plate }}</strong>
                  <span>{{ v.brand }} {{ v.model }}</span>
                  <span class="badge badge-green">Disponible</span>
                </div>
                <p v-if="!availableVehicles.length" class="empty-panel">Sin vehículos disponibles</p>
              </div>

              <!-- Operadores disponibles -->
              <div class="assign-panel">
                <h3>Operadores disponibles</h3>
                <div
                  v-for="op in availableOperators" :key="op.id"
                  class="assign-card"
                  :class="{ selected: selectedOperator === op.id }"
                  @click="selectedOperator = op.id"
                >
                  <strong>{{ op.name }}</strong>
                  <span>Lic. {{ op.license_type }}</span>
                  <span class="badge badge-green">Disponible</span>
                </div>
                <p v-if="!availableOperators.length" class="empty-panel">Sin operadores disponibles</p>
              </div>
            </div>

            <div class="form-group" style="margin-top:1rem">
              <label>Notas</label>
              <textarea v-model="notes" rows="2" placeholder="Opcional…"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showModal = false">Cancelar</button>
            <button class="btn btn-primary" :disabled="saving" @click="save">
              {{ saving ? 'Asignando…' : 'Confirmar Asignación' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@import '@/assets/admin.css';

.tabs { display: flex; gap: 0; margin-bottom: 1rem; border-bottom: 2px solid #e5e7eb; }
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
.tab.active  { color: #1e3a5f; border-bottom-color: #1e3a5f; font-weight: 600; }
.tab:hover   { color: #1e3a5f; }

.assign-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.assign-panel  { border: 1px solid #e5e7eb; border-radius: 8px; padding: 0.75rem; }
.assign-panel h3 { font-size: 0.85rem; color: #6b7280; margin: 0 0 0.6rem; text-transform: uppercase; }

.assign-card {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  padding: 0.6rem;
  border: 2px solid transparent;
  border-radius: 6px;
  cursor: pointer;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  transition: border-color 0.15s, background 0.15s;
}
.assign-card:hover { background: #f0f4ff; }
.assign-card.selected { border-color: #2563eb; background: #eff6ff; }

.badge-green { background: #16a34a; }
.empty-panel { color: #6b7280; font-size: 0.8rem; text-align: center; padding: 0.5rem; }
.empty { text-align: center; color: #6b7280; padding: 2rem; }
.pagination { display: flex; gap: 0.5rem; margin-top: 1rem; justify-content: center; }
.loading { text-align: center; padding: 2rem; color: #6b7280; }
.form-error { color: #dc2626; font-size: 0.875rem; margin-bottom: 0.5rem; }
</style>
