<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useShiftsStore } from '@/stores/shifts.js'

const store    = useShiftsStore()
const showModal = ref(false)
const editMode  = ref(false)
const saving    = ref(false)
const error     = ref('')

const TYPES = ['work', 'rest', 'vacation', 'leave', 'standby']
const TYPE_LABELS = {
  work:     'Trabajo',
  rest:     'Descanso',
  vacation: 'Vacaciones',
  leave:    'Licencia',
  standby:  'Disponible',
}
const TYPE_COLORS = {
  work:     '#2563eb',
  rest:     '#6b7280',
  vacation: '#16a34a',
  leave:    '#d97706',
  standby:  '#7c3aed',
}

const form = reactive({
  id: null, operator_id: '', shift_type: 'work',
  start_datetime: '', end_datetime: '', notes: '',
})

function resetForm() {
  Object.assign(form, {
    id: null, operator_id: '', shift_type: 'work',
    start_datetime: '', end_datetime: '', notes: '',
  })
}

function openCreate() {
  resetForm()
  editMode.value  = false
  error.value     = ''
  showModal.value = true
}

function openEdit(shift) {
  Object.assign(form, {
    id:             shift.id,
    operator_id:    shift.operator_id ?? '',
    shift_type:     shift.shift_type ?? 'work',
    start_datetime: shift.start_datetime ? shift.start_datetime.substring(0, 16) : '',
    end_datetime:   shift.end_datetime   ? shift.end_datetime.substring(0, 16)   : '',
    notes:          shift.notes ?? '',
  })
  editMode.value  = true
  error.value     = ''
  showModal.value = true
}

function buildPayload() {
  const raw = { ...form }
  delete raw.id
  if (raw.notes === '') raw.notes = null
  if (raw.operator_id !== '') raw.operator_id = parseInt(raw.operator_id)
  return raw
}

async function save() {
  saving.value = true
  error.value  = ''
  try {
    const payload = buildPayload()
    if (editMode.value) {
      await store.editShift(form.id, payload)
    } else {
      await store.addShift(payload)
    }
    showModal.value = false
  } catch (e) {
    const errors = e.response?.data?.errors
    error.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Error al guardar')
  } finally {
    saving.value = false
  }
}

async function removeShift(shift) {
  if (!confirm(`¿Eliminar este turno del operador ${shift.operator?.name ?? shift.operator_id}?`)) return
  try {
    await store.removeShift(shift.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede eliminar')
  }
}

function applyFilters() {
  store.fetchShifts(1)
}

function formatDateTime(dt) {
  if (!dt) return '—'
  return dt.substring(0, 16).replace('T', ' ')
}

onMounted(() => store.fetchShifts())
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Turnos de Operadores</h1>
      <button class="btn btn--primary" @click="openCreate">+ Nuevo Turno</button>
    </div>

    <!-- Filtros -->
    <div class="filters card">
      <select v-model="store.filters.shift_type" @change="applyFilters">
        <option value="">Todos los tipos</option>
        <option v-for="t in TYPES" :key="t" :value="t">{{ TYPE_LABELS[t] }}</option>
      </select>
      <input v-model="store.filters.from" type="date" @change="applyFilters" title="Desde" />
      <input v-model="store.filters.to"   type="date" @change="applyFilters" title="Hasta" />
    </div>

    <!-- Tabla -->
    <div class="card">
      <div v-if="store.loading" class="loading-text">Cargando…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Operador</th>
            <th>Tipo</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Observaciones</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!store.shifts.length">
            <td colspan="6" class="empty-row">Sin turnos registrados</td>
          </tr>
          <tr v-for="shift in store.shifts" :key="shift.id">
            <td><strong>{{ shift.operator?.name ?? shift.operator_id }}</strong></td>
            <td>
              <span class="badge" :style="{ background: TYPE_COLORS[shift.shift_type] }">
                {{ TYPE_LABELS[shift.shift_type] }}
              </span>
            </td>
            <td>{{ formatDateTime(shift.start_datetime) }}</td>
            <td>{{ formatDateTime(shift.end_datetime) }}</td>
            <td>{{ shift.notes ?? '—' }}</td>
            <td class="actions">
              <button class="btn btn--sm btn--outline" @click="openEdit(shift)">Editar</button>
              <button class="btn btn--sm btn--danger"  @click="removeShift(shift)">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.meta?.last_page > 1" class="pagination">
        <button :disabled="store.meta.current_page === 1"
                @click="store.fetchShifts(store.meta.current_page - 1)">‹</button>
        <span>{{ store.meta.current_page }} / {{ store.meta.last_page }}</span>
        <button :disabled="store.meta.current_page === store.meta.last_page"
                @click="store.fetchShifts(store.meta.current_page + 1)">›</button>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ editMode ? 'Editar Turno' : 'Nuevo Turno' }}</h3>
          <button class="modal-close" @click="showModal = false">✕</button>
        </div>
        <form @submit.prevent="save">
          <div v-if="error" class="alert alert--error">{{ error }}</div>
          <div class="form-grid">
            <div class="field">
              <label>ID Operador *</label>
              <input v-model="form.operator_id" type="number" required min="1" />
            </div>
            <div class="field">
              <label>Tipo de turno *</label>
              <select v-model="form.shift_type" required>
                <option v-for="t in TYPES" :key="t" :value="t">{{ TYPE_LABELS[t] }}</option>
              </select>
            </div>
            <div class="field">
              <label>Inicio *</label>
              <input v-model="form.start_datetime" type="datetime-local" required />
            </div>
            <div class="field">
              <label>Fin *</label>
              <input v-model="form.end_datetime" type="datetime-local" required />
            </div>
            <div class="field field--full">
              <label>Observaciones</label>
              <textarea v-model="form.notes" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn--outline" @click="showModal = false">Cancelar</button>
            <button type="submit" class="btn btn--primary" :disabled="saving">
              {{ saving ? 'Guardando…' : 'Guardar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
