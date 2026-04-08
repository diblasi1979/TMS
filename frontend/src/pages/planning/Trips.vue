<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useTripsStore } from '@/stores/trips.js'
import { getTrip } from '@/api/trips.js'

const store    = useTripsStore()
const showModal    = ref(false)
const confirmModal = ref(false)
const routesModal  = ref(false)
const editMode     = ref(false)
const saving       = ref(false)
const error        = ref('')
const confirmTarget = ref(null)
const tripDetail    = ref(null)

const STATUSES = ['draft', 'confirmed', 'in_progress', 'completed', 'cancelled']
const STATUS_LABELS = {
  draft:       'Borrador',
  confirmed:   'Confirmado',
  in_progress: 'En Camino',
  completed:   'Completado',
  cancelled:   'Cancelado',
}
const STATUS_COLORS = {
  draft:       '#6b7280',
  confirmed:   '#2563eb',
  in_progress: '#d97706',
  completed:   '#16a34a',
  cancelled:   '#9ca3af',
}
const PRIORITIES = ['low', 'normal', 'high', 'urgent']
const PRIORITY_LABELS = { low: 'Baja', normal: 'Normal', high: 'Alta', urgent: 'Urgente' }

const CARGO_TYPES = ['general', 'refrigerated', 'hazardous', 'fragile', 'bulk', 'livestock', 'machinery']
const CARGO_LABELS = {
  general:      'General',
  refrigerated: 'Refrigerada',
  hazardous:    'Peligrosa',
  fragile:      'Frágil',
  bulk:         'Granel',
  livestock:    'Ganadería',
  machinery:    'Maquinaria',
}

const form = reactive({
  id: null, client_id: '', trip_number: '', origin: '', destination: '',
  scheduled_departure: '', scheduled_arrival: '',
  cargo_type: '', cargo_description: '', weight_kg: '', volume_m3: '', distance_km: '',
  priority: 'normal', notes: '',
})

const confirmForm = reactive({ vehicle_id: '', operator_id: '' })

function resetForm() {
  Object.assign(form, {
    id: null, client_id: '', trip_number: '', origin: '', destination: '',
    scheduled_departure: '', scheduled_arrival: '',
    cargo_type: '', cargo_description: '', weight_kg: '', volume_m3: '', distance_km: '',
    priority: 'normal', notes: '',
  })
}

function openCreate() {
  resetForm()
  editMode.value  = false
  error.value     = ''
  showModal.value = true
}

function openEdit(trip) {
  Object.assign(form, {
    id:                   trip.id,
    client_id:            trip.client_id ?? '',
    trip_number:          trip.trip_number ?? '',
    origin:               trip.origin ?? '',
    destination:          trip.destination ?? '',
    scheduled_departure:  trip.scheduled_departure ? trip.scheduled_departure.substring(0, 16) : '',
    scheduled_arrival:    trip.scheduled_arrival   ? trip.scheduled_arrival.substring(0, 16)   : '',
    cargo_type:           trip.cargo_type ?? '',
    cargo_description:    trip.cargo_description ?? '',
    weight_kg:            trip.weight_kg ?? '',
    volume_m3:            trip.volume_m3 ?? '',
    distance_km:          trip.distance_km ?? '',
    priority:             trip.priority ?? 'normal',
    notes:                trip.notes ?? '',
  })
  editMode.value  = true
  error.value     = ''
  showModal.value = true
}

function openConfirm(trip) {
  confirmTarget.value = trip
  Object.assign(confirmForm, { vehicle_id: trip.vehicle_id ?? '', operator_id: trip.operator_id ?? '' })
  error.value      = ''
  confirmModal.value = true
}

async function openRoutes(trip) {
  const { data } = await getTrip(trip.id)
  tripDetail.value  = data.data ?? data
  routesModal.value = true
}

function buildPayload() {
  const raw = { ...form }
  delete raw.id
  ;['client_id', 'cargo_type', 'cargo_description', 'notes'].forEach(k => {
    if (raw[k] === '') raw[k] = null
  })
  ;['weight_kg', 'volume_m3', 'distance_km'].forEach(k => {
    raw[k] = raw[k] !== '' && raw[k] !== null ? parseFloat(raw[k]) : null
  })
  if (raw.client_id !== null) raw.client_id = parseInt(raw.client_id) || null
  return raw
}

async function save() {
  saving.value = true
  error.value  = ''
  try {
    const payload = buildPayload()
    if (editMode.value) {
      await store.editTrip(form.id, payload)
    } else {
      await store.addTrip(payload)
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

async function saveConfirm() {
  saving.value = true
  error.value  = ''
  try {
    await store.confirm(confirmTarget.value.id, {
      vehicle_id:  parseInt(confirmForm.vehicle_id),
      operator_id: parseInt(confirmForm.operator_id),
    })
    confirmModal.value = false
  } catch (e) {
    const data = e.response?.data
    if (data?.conflicts?.length) {
      error.value = data.conflicts.map(c => c.message).join(' | ')
    } else {
      error.value = data?.message ?? 'Error al confirmar'
    }
  } finally {
    saving.value = false
  }
}

async function startTrip(trip) {
  if (!confirm(`¿Iniciar el viaje ${trip.trip_number}?`)) return
  try {
    await store.start(trip.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede iniciar')
  }
}

async function completeTrip(trip) {
  if (!confirm(`¿Marcar como completado el viaje ${trip.trip_number}?`)) return
  try {
    await store.complete(trip.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede completar')
  }
}

async function cancelTrip(trip) {
  if (!confirm(`¿Cancelar el viaje ${trip.trip_number}?`)) return
  try {
    await store.cancel(trip.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede cancelar')
  }
}

async function removeTrip(trip) {
  if (!confirm(`¿Eliminar el viaje ${trip.trip_number}?`)) return
  try {
    await store.removeTrip(trip.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede eliminar')
  }
}

function applyFilters() {
  store.fetchTrips(1)
}

onMounted(() => store.fetchTrips())
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Viajes Planificados</h1>
      <button class="btn btn--primary" @click="openCreate">+ Nuevo Viaje</button>
    </div>

    <!-- Filtros -->
    <div class="filters card">
      <select v-model="store.filters.status" @change="applyFilters">
        <option value="">Todos los estados</option>
        <option v-for="s in STATUSES" :key="s" :value="s">{{ STATUS_LABELS[s] }}</option>
      </select>
      <select v-model="store.filters.priority" @change="applyFilters">
        <option value="">Todas las prioridades</option>
        <option v-for="p in PRIORITIES" :key="p" :value="p">{{ PRIORITY_LABELS[p] }}</option>
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
            <th>N° Viaje</th>
            <th>Origen → Destino</th>
            <th>Salida planificada</th>
            <th>Vehículo</th>
            <th>Operador</th>
            <th>Rutas</th>
            <th>Prioridad</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!store.trips.length">
            <td colspan="9" class="empty-row">Sin viajes registrados</td>
          </tr>
          <tr v-for="trip in store.trips" :key="trip.id">
            <td><strong>{{ trip.trip_number }}</strong></td>
            <td>{{ trip.origin }} → {{ trip.destination }}</td>
            <td>{{ trip.scheduled_departure ? trip.scheduled_departure.substring(0, 16).replace('T', ' ') : '—' }}</td>
            <td>{{ trip.vehicle?.plate ?? '—' }}</td>
            <td>{{ trip.operator?.name ?? '—' }}</td>
            <td>
              <button
                v-if="trip.routes_count > 0"
                class="btn btn--sm btn--outline"
                @click="openRoutes(trip)"
              >📆 {{ trip.routes_count }}</button>
              <span v-else class="text-muted">0</span>
            </td>
            <td>{{ PRIORITY_LABELS[trip.priority] ?? trip.priority }}</td>
            <td>
              <span class="badge" :style="{ background: STATUS_COLORS[trip.status] }">
                {{ STATUS_LABELS[trip.status] }}
              </span>
            </td>
            <td class="actions">
              <button
                v-if="trip.status === 'draft'"
                class="btn btn--sm btn--outline"
                @click="openEdit(trip)"
              >Editar</button>
              <button
                v-if="trip.status === 'draft'"
                class="btn btn--sm btn--primary"
                @click="openConfirm(trip)"
              >Confirmar</button>
              <button
                v-if="trip.status === 'confirmed'"
                class="btn btn--sm btn--primary"
                @click="startTrip(trip)"
              >Iniciar</button>
              <button
                v-if="trip.status === 'in_progress'"
                class="btn btn--sm btn--outline"
                @click="completeTrip(trip)"
              >Completar</button>
              <button
                v-if="['draft', 'confirmed'].includes(trip.status)"
                class="btn btn--sm btn--danger"
                @click="cancelTrip(trip)"
              >Cancelar</button>
              <button
                v-if="trip.status === 'draft'"
                class="btn btn--sm btn--danger"
                @click="removeTrip(trip)"
              >Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.meta?.last_page > 1" class="pagination">
        <button :disabled="store.meta.current_page === 1"
                @click="store.fetchTrips(store.meta.current_page - 1)">‹</button>
        <span>{{ store.meta.current_page }} / {{ store.meta.last_page }}</span>
        <button :disabled="store.meta.current_page === store.meta.last_page"
                @click="store.fetchTrips(store.meta.current_page + 1)">›</button>
      </div>
    </div>

    <!-- Modal Rutas del viaje -->
    <div v-if="routesModal && tripDetail" class="modal-backdrop" @click.self="routesModal = false">
      <div class="modal modal--wide">
        <div class="modal-header">
          <h3>Rutas del viaje {{ tripDetail.trip_number }}</h3>
          <button class="modal-close" @click="routesModal = false">✕</button>
        </div>
        <p class="trip-detail-meta">
          {{ tripDetail.origin }} → {{ tripDetail.destination }}
          &nbsp;•&nbsp; Salida: {{ tripDetail.scheduled_departure?.substring(0, 16).replace('T', ' ') }}
        </p>
        <table class="data-table">
          <thead>
            <tr>
              <th>Nombre de ruta</th>
              <th>Fecha planificada</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!tripDetail.routes?.length">
              <td colspan="3" class="empty-row">Sin rutas asignadas a este viaje</td>
            </tr>
            <tr v-for="r in tripDetail.routes" :key="r.id">
              <td>{{ r.name }}</td>
              <td>{{ r.planned_date }}</td>
              <td>{{ r.status }}</td>
            </tr>
          </tbody>
        </table>
        <div class="modal-footer">
          <button class="btn btn--outline" @click="routesModal = false">Cerrar</button>
        </div>
      </div>
    </div>

    <!-- Modal Crear/Editar -->
    <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ editMode ? 'Editar Viaje' : 'Nuevo Viaje' }}</h3>
          <button class="modal-close" @click="showModal = false">✕</button>
        </div>
        <form @submit.prevent="save">
          <div v-if="error" class="alert alert--error">{{ error }}</div>
          <div class="form-grid">
            <div class="field">
              <label>N° Viaje *</label>
              <input v-model="form.trip_number" type="text" required />
            </div>
            <div class="field">
              <label>ID Cliente</label>
              <input v-model="form.client_id" type="number" min="1" />
            </div>
            <div class="field field--full">
              <label>Origen *</label>
              <input v-model="form.origin" type="text" required />
            </div>
            <div class="field field--full">
              <label>Destino *</label>
              <input v-model="form.destination" type="text" required />
            </div>
            <div class="field">
              <label>Salida planificada *</label>
              <input v-model="form.scheduled_departure" type="datetime-local" required />
            </div>
            <div class="field">
              <label>Llegada estimada *</label>
              <input v-model="form.scheduled_arrival" type="datetime-local" required />
            </div>
            <div class="field">
              <label>Tipo de carga</label>
              <select v-model="form.cargo_type">
                <option value="">Sin especificar</option>
                <option v-for="t in CARGO_TYPES" :key="t" :value="t">{{ CARGO_LABELS[t] }}</option>
              </select>
            </div>
            <div class="field">
              <label>Prioridad</label>
              <select v-model="form.priority">
                <option v-for="p in PRIORITIES" :key="p" :value="p">{{ PRIORITY_LABELS[p] }}</option>
              </select>
            </div>
            <div class="field">
              <label>Peso (kg)</label>
              <input v-model="form.weight_kg" type="number" step="0.01" min="0" />
            </div>
            <div class="field">
              <label>Distancia (km)</label>
              <input v-model="form.distance_km" type="number" step="0.01" min="0" />
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

    <!-- Modal Confirmar recursos -->
    <div v-if="confirmModal" class="modal-backdrop" @click.self="confirmModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>Confirmar viaje — {{ confirmTarget?.trip_number }}</h3>
          <button class="modal-close" @click="confirmModal = false">✕</button>
        </div>
        <form @submit.prevent="saveConfirm">
          <div v-if="error" class="alert alert--error">{{ error }}</div>
          <div class="form-grid">
            <div class="field">
              <label>ID Vehículo *</label>
              <input v-model="confirmForm.vehicle_id" type="number" required min="1" />
            </div>
            <div class="field">
              <label>ID Operador *</label>
              <input v-model="confirmForm.operator_id" type="number" required min="1" />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn--outline" @click="confirmModal = false">Cancelar</button>
            <button type="submit" class="btn btn--primary" :disabled="saving">
              {{ saving ? 'Verificando…' : 'Confirmar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import '@/assets/admin.css';
.filters { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; padding: 0.75rem 1rem; }
.filters select, .filters input { padding: 0.4rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; }
.modal--wide { max-width: 860px; width: 95%; }
.trip-detail-meta { padding: 0 0 1rem; color: #6b7280; font-size: 0.9rem; }
.text-muted { color: #9ca3af; font-size: 0.85rem; }
</style>
