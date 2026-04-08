<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useMaintenanceStore } from '@/stores/maintenance.js'

const store    = useMaintenanceStore()
const showModal = ref(false)
const editMode  = ref(false)
const saving    = ref(false)
const error     = ref('')

const TYPES = ['preventive', 'corrective', 'predictive', 'inspection']
const TYPE_LABELS = {
  preventive:  'Preventivo',
  corrective:  'Correctivo',
  predictive:  'Predictivo',
  inspection:  'Inspección',
}

const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled']
const STATUS_LABELS = {
  scheduled:   'Programado',
  in_progress: 'En proceso',
  completed:   'Completado',
  cancelled:   'Cancelado',
}
const STATUS_COLORS = {
  scheduled:   '#2563eb',
  in_progress: '#d97706',
  completed:   '#16a34a',
  cancelled:   '#9ca3af',
}

const form = reactive({
  id: null, vehicle_id: '', maintenance_type: 'preventive', description: '',
  scheduled_date: '', estimated_duration_days: 1, workshop: '',
  estimated_cost: '', mileage_at_service: '', next_service_km: '',
  next_service_date: '', notes: '',
})

function resetForm() {
  Object.assign(form, {
    id: null, vehicle_id: '', maintenance_type: 'preventive', description: '',
    scheduled_date: '', estimated_duration_days: 1, workshop: '',
    estimated_cost: '', mileage_at_service: '', next_service_km: '',
    next_service_date: '', notes: '',
  })
}

function openCreate() {
  resetForm()
  editMode.value  = false
  error.value     = ''
  showModal.value = true
}

function openEdit(item) {
  Object.assign(form, {
    id:                  item.id,
    vehicle_id:          item.vehicle_id ?? '',
    maintenance_type:    item.maintenance_type ?? 'preventive',
    description:         item.description ?? '',
    scheduled_date:      item.scheduled_date ?? '',
    estimated_duration_days: item.estimated_duration_days ?? 1,
    workshop:            item.workshop ?? '',
    estimated_cost:      item.estimated_cost ?? '',
    mileage_at_service:  item.mileage_at_service ?? '',
    next_service_km:     item.next_service_km ?? '',
    next_service_date:   item.next_service_date ?? '',
    notes:               item.notes ?? '',
  })
  editMode.value  = true
  error.value     = ''
  showModal.value = true
}

function buildPayload() {
  const raw = { ...form }
  delete raw.id
  ;['workshop', 'notes', 'next_service_date'].forEach(k => {
    if (raw[k] === '') raw[k] = null
  })
  ;['estimated_cost'].forEach(k => {
    raw[k] = raw[k] !== '' && raw[k] !== null ? parseFloat(raw[k]) : null
  })
  ;['vehicle_id', 'mileage_at_service', 'next_service_km', 'estimated_duration_days'].forEach(k => {
    raw[k] = raw[k] !== '' && raw[k] !== null ? parseInt(raw[k]) : null
  })
  return raw
}

async function save() {
  saving.value = true
  error.value  = ''
  try {
    const payload = buildPayload()
    if (editMode.value) {
      await store.edit(form.id, payload)
    } else {
      await store.add(payload)
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

async function startItem(item) {
  if (!confirm(`¿Confirmar ingreso al taller para vehículo ${item.vehicle?.plate ?? item.vehicle_id}?`)) return
  try {
    await store.start(item.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede iniciar')
  }
}

async function completeItem(item) {
  if (!confirm(`¿Confirmar egreso del taller para vehículo ${item.vehicle?.plate ?? item.vehicle_id}?`)) return
  try {
    await store.complete(item.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede completar')
  }
}

async function cancelItem(item) {
  if (!confirm(`¿Cancelar el mantenimiento para vehículo ${item.vehicle?.plate ?? item.vehicle_id}?`)) return
  try {
    await store.cancel(item.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede cancelar')
  }
}

async function removeItem(item) {
  if (!confirm(`¿Eliminar este mantenimiento?`)) return
  try {
    await store.remove(item.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede eliminar')
  }
}

function applyFilters() {
  store.fetchAll(1)
}

onMounted(() => store.fetchAll())
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Mantenimiento Programado</h1>
      <button class="btn btn--primary" @click="openCreate">+ Programar Mantenimiento</button>
    </div>

    <!-- Filtros -->
    <div class="filters card">
      <select v-model="store.filters.status" @change="applyFilters">
        <option value="">Todos los estados</option>
        <option v-for="s in STATUSES" :key="s" :value="s">{{ STATUS_LABELS[s] }}</option>
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
            <th>Vehículo</th>
            <th>Tipo</th>
            <th>Descripción</th>
            <th>Fecha programada</th>
            <th>Días estimated</th>
            <th>Taller</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!store.items.length">
            <td colspan="8" class="empty-row">Sin mantenimientos programados</td>
          </tr>
          <tr v-for="item in store.items" :key="item.id">
            <td><strong>{{ item.vehicle?.plate ?? item.vehicle_id }}</strong></td>
            <td>{{ TYPE_LABELS[item.maintenance_type] ?? item.maintenance_type }}</td>
            <td>{{ item.description?.substring(0, 50) }}{{ item.description?.length > 50 ? '…' : '' }}</td>
            <td>{{ item.scheduled_date }}</td>
            <td>{{ item.estimated_duration_days }} día(s)</td>
            <td>{{ item.workshop ?? '—' }}</td>
            <td>
              <span class="badge" :style="{ background: STATUS_COLORS[item.status] }">
                {{ STATUS_LABELS[item.status] }}
              </span>
            </td>
            <td class="actions">
              <button
                v-if="item.status === 'scheduled'"
                class="btn btn--sm btn--outline"
                @click="openEdit(item)"
              >Editar</button>
              <button
                v-if="item.status === 'scheduled'"
                class="btn btn--sm btn--primary"
                @click="startItem(item)"
              >Iniciar</button>
              <button
                v-if="item.status === 'in_progress'"
                class="btn btn--sm btn--outline"
                @click="completeItem(item)"
              >Completar</button>
              <button
                v-if="item.status === 'scheduled'"
                class="btn btn--sm btn--danger"
                @click="cancelItem(item)"
              >Cancelar</button>
              <button
                v-if="item.status === 'scheduled'"
                class="btn btn--sm btn--danger"
                @click="removeItem(item)"
              >Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.meta?.last_page > 1" class="pagination">
        <button :disabled="store.meta.current_page === 1"
                @click="store.fetchAll(store.meta.current_page - 1)">‹</button>
        <span>{{ store.meta.current_page }} / {{ store.meta.last_page }}</span>
        <button :disabled="store.meta.current_page === store.meta.last_page"
                @click="store.fetchAll(store.meta.current_page + 1)">›</button>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ editMode ? 'Editar Mantenimiento' : 'Programar Mantenimiento' }}</h3>
          <button class="modal-close" @click="showModal = false">✕</button>
        </div>
        <form @submit.prevent="save">
          <div v-if="error" class="alert alert--error">{{ error }}</div>
          <div class="form-grid">
            <div class="field">
              <label>ID Vehículo *</label>
              <input v-model="form.vehicle_id" type="number" required min="1" />
            </div>
            <div class="field">
              <label>Tipo *</label>
              <select v-model="form.maintenance_type" required>
                <option v-for="t in TYPES" :key="t" :value="t">{{ TYPE_LABELS[t] }}</option>
              </select>
            </div>
            <div class="field field--full">
              <label>Descripción *</label>
              <textarea v-model="form.description" rows="2" required></textarea>
            </div>
            <div class="field">
              <label>Fecha programada *</label>
              <input v-model="form.scheduled_date" type="date" required />
            </div>
            <div class="field">
              <label>Días estimados</label>
              <input v-model="form.estimated_duration_days" type="number" min="1" />
            </div>
            <div class="field field--full">
              <label>Taller</label>
              <input v-model="form.workshop" type="text" />
            </div>
            <div class="field">
              <label>Costo estimado</label>
              <input v-model="form.estimated_cost" type="number" step="0.01" min="0" />
            </div>
            <div class="field">
              <label>Km al servicio</label>
              <input v-model="form.mileage_at_service" type="number" min="0" />
            </div>
            <div class="field">
              <label>Próximo servicio (km)</label>
              <input v-model="form.next_service_km" type="number" min="0" />
            </div>
            <div class="field">
              <label>Próximo servicio (fecha)</label>
              <input v-model="form.next_service_date" type="date" />
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
