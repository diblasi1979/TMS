<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoutesStore } from '@/stores/routes.js'
import { getOrders } from '@/api/orders.js'
import { getVehicles } from '@/api/vehicles.js'
import { getOperators } from '@/api/operators.js'

const store = useRoutesStore()
const showModal     = ref(false)
const showDetail    = ref(false)
const editMode      = ref(false)
const saving        = ref(false)
const actioning     = ref(false)
const error         = ref('')
const selectedRoute = ref(null)
const pendingOrders = ref([])
const vehicles      = ref([])
const operators     = ref([])

const STATUSES = ['draft', 'planned', 'in_transit', 'completed', 'cancelled']
const STATUS_LABELS = {
  draft:      'Borrador',
  planned:    'Planificada',
  in_transit: 'En Camino',
  completed:  'Completada',
  cancelled:  'Cancelada',
}
const STATUS_COLORS = {
  draft:      '#6b7280',
  planned:    '#2563eb',
  in_transit: '#d97706',
  completed:  '#16a34a',
  cancelled:  '#9ca3af',
}

const form = reactive({
  id: null, name: '', planned_date: '',
  vehicle_id: '', operator_id: '',
  total_distance_km: '', notes: '',
})

function resetForm() {
  Object.assign(form, {
    id: null, name: '', planned_date: '',
    vehicle_id: '', operator_id: '',
    total_distance_km: '', notes: '',
  })
}

function openCreate() {
  resetForm()
  editMode.value  = false
  error.value     = ''
  showModal.value = true
}

function openEdit(route) {
  Object.assign(form, {
    id: route.id,
    name: route.name ?? '',
    planned_date: route.planned_date ?? '',
    vehicle_id: route.vehicle_id ?? '',
    operator_id: route.operator_id ?? '',
    total_distance_km: route.total_distance_km ?? '',
    notes: route.notes ?? '',
  })
  editMode.value  = true
  error.value     = ''
  showModal.value = true
}

async function openDetail(route) {
  const { data } = await import('@/api/routes.js').then(m => m.getRoute(route.id))
  selectedRoute.value = data.data
  showDetail.value = true
}

function buildPayload() {
  const raw = { ...form }
  delete raw.id
  ;['notes'].forEach(k => { if (raw[k] === '') raw[k] = null })
  if (raw.vehicle_id !== '') raw.vehicle_id = parseInt(raw.vehicle_id)
  else raw.vehicle_id = null
  if (raw.operator_id !== '') raw.operator_id = parseInt(raw.operator_id)
  else raw.operator_id = null
  if (raw.total_distance_km !== '') raw.total_distance_km = parseFloat(raw.total_distance_km)
  else raw.total_distance_km = null
  return raw
}

async function save() {
  saving.value = true
  error.value  = ''
  try {
    const payload = buildPayload()
    if (editMode.value) {
      await store.editRoute(form.id, payload)
    } else {
      await store.addRoute(payload)
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

async function remove(route) {
  if (!confirm(`¿Eliminar la ruta "${route.name}"?`)) return
  try {
    await store.removeRoute(route.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede eliminar')
  }
}

async function dispatch(route) {
  if (!confirm(`¿Despachar la ruta "${route.name}"?`)) return
  actioning.value = true
  try {
    await store.dispatch(route.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede despachar')
  } finally {
    actioning.value = false
  }
}

async function complete(route) {
  if (!confirm(`¿Cerrar la ruta "${route.name}"?`)) return
  actioning.value = true
  try {
    await store.complete(route.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede cerrar')
  } finally {
    actioning.value = false
  }
}

async function cancelRoute(route) {
  if (!confirm(`¿Cancelar la ruta "${route.name}"?`)) return
  actioning.value = true
  try {
    await store.cancel(route.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede cancelar')
  } finally {
    actioning.value = false
  }
}

function applyFilters() {
  store.fetchRoutes(1)
}

onMounted(async () => {
  await store.fetchRoutes()
  const [vo, oo] = await Promise.all([
    getVehicles({ status: 'available' }),
    getOperators({ status: 'available' }),
  ])
  vehicles.value  = vo.data.data ?? []
  operators.value = oo.data.data ?? []
})
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Rutas de Distribución</h1>
      <button class="btn btn--primary" @click="openCreate">+ Nueva Ruta</button>
    </div>

    <!-- Filtros -->
    <div class="filters card">
      <select v-model="store.filters.status" @change="applyFilters">
        <option value="">Todos los estados</option>
        <option v-for="s in STATUSES" :key="s" :value="s">{{ STATUS_LABELS[s] }}</option>
      </select>
      <input v-model="store.filters.planned_date" type="date" @change="applyFilters" title="Filtrar por fecha planificada" />
    </div>

    <!-- Tabla -->
    <div class="card">
      <div v-if="store.loading" class="loading-text">Cargando…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Fecha planificada</th>
            <th>Vehículo</th>
            <th>Operador</th>
            <th>Pedidos</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!store.routes.length">
            <td colspan="7" class="empty-row">Sin rutas registradas</td>
          </tr>
          <tr v-for="route in store.routes" :key="route.id">
            <td><strong>{{ route.name }}</strong></td>
            <td>{{ route.planned_date }}</td>
            <td>{{ route.vehicle?.plate ?? '—' }}</td>
            <td>{{ route.operator?.name ?? '—' }}</td>
            <td>{{ route.orders_count ?? 0 }}</td>
            <td>
              <span class="badge" :style="{ background: STATUS_COLORS[route.status] }">
                {{ STATUS_LABELS[route.status] }}
              </span>
            </td>
            <td class="actions">
              <button class="btn btn--sm btn--outline" @click="openDetail(route)">Ver</button>
              <button
                v-if="['draft', 'planned'].includes(route.status)"
                class="btn btn--sm btn--outline"
                @click="openEdit(route)"
              >Editar</button>
              <button
                v-if="route.status === 'planned'"
                class="btn btn--sm btn--primary"
                :disabled="actioning"
                @click="dispatch(route)"
              >Despachar</button>
              <button
                v-if="route.status === 'in_transit'"
                class="btn btn--sm btn--primary"
                :disabled="actioning"
                @click="complete(route)"
              >Cerrar</button>
              <button
                v-if="['draft', 'planned'].includes(route.status)"
                class="btn btn--sm btn--danger"
                :disabled="actioning"
                @click="cancelRoute(route)"
              >Cancelar</button>
              <button
                v-if="route.status === 'draft'"
                class="btn btn--sm btn--danger"
                @click="remove(route)"
              >Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.meta?.last_page > 1" class="pagination">
        <button :disabled="store.meta.current_page === 1"
                @click="store.fetchRoutes(store.meta.current_page - 1)">‹</button>
        <span>{{ store.meta.current_page }} / {{ store.meta.last_page }}</span>
        <button :disabled="store.meta.current_page === store.meta.last_page"
                @click="store.fetchRoutes(store.meta.current_page + 1)">›</button>
      </div>
    </div>

    <!-- Modal crear/editar ruta -->
    <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ editMode ? 'Editar Ruta' : 'Nueva Ruta' }}</h3>
          <button class="modal-close" @click="showModal = false">✕</button>
        </div>
        <form @submit.prevent="save">
          <div v-if="error" class="alert alert--error">{{ error }}</div>
          <div class="form-grid">
            <div class="field field--full">
              <label>Nombre de la ruta *</label>
              <input v-model="form.name" type="text" required />
            </div>
            <div class="field">
              <label>Fecha planificada *</label>
              <input v-model="form.planned_date" type="date" required />
            </div>
            <div class="field">
              <label>Distancia estimada (km)</label>
              <input v-model="form.total_distance_km" type="number" step="0.01" min="0" />
            </div>
            <div class="field">
              <label>Vehículo</label>
              <select v-model="form.vehicle_id">
                <option value="">— Sin asignar —</option>
                <option v-for="v in vehicles" :key="v.id" :value="v.id">
                  {{ v.plate }} — {{ v.brand }} {{ v.model }}
                </option>
              </select>
            </div>
            <div class="field">
              <label>Operador</label>
              <select v-model="form.operator_id">
                <option value="">— Sin asignar —</option>
                <option v-for="op in operators" :key="op.id" :value="op.id">
                  {{ op.name }}
                </option>
              </select>
            </div>
            <div class="field field--full">
              <label>Notas</label>
              <input v-model="form.notes" type="text" />
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

    <!-- Panel de detalle de ruta -->
    <div v-if="showDetail && selectedRoute" class="modal-backdrop" @click.self="showDetail = false">
      <div class="modal modal--wide">
        <div class="modal-header">
          <h3>{{ selectedRoute.name }}</h3>
          <button class="modal-close" @click="showDetail = false">✕</button>
        </div>
        <div class="route-detail">
          <div class="route-meta">
            <span><strong>Fecha:</strong> {{ selectedRoute.planned_date }}</span>
            <span><strong>Estado:</strong>
              <span class="badge" :style="{ background: STATUS_COLORS[selectedRoute.status] }">
                {{ STATUS_LABELS[selectedRoute.status] }}
              </span>
            </span>
            <span v-if="selectedRoute.vehicle"><strong>Vehículo:</strong> {{ selectedRoute.vehicle.plate }}</span>
            <span v-if="selectedRoute.operator"><strong>Operador:</strong> {{ selectedRoute.operator.name }}</span>
          </div>
          <h4>Pedidos ({{ selectedRoute.orders?.length ?? 0 }})</h4>
          <table class="data-table">
            <thead>
              <tr><th>#</th><th>Referencia</th><th>Cliente</th><th>Dirección</th><th>Estado</th></tr>
            </thead>
            <tbody>
              <tr v-if="!selectedRoute.orders?.length">
                <td colspan="5" class="empty-row">Sin pedidos asignados</td>
              </tr>
              <tr v-for="order in selectedRoute.orders" :key="order.id">
                <td>{{ order.sort_order }}</td>
                <td>{{ order.reference_number }}</td>
                <td>{{ order.client?.name ?? '—' }}</td>
                <td>{{ order.delivery_address }}</td>
                <td>
                  <span class="badge" :style="{ background: STATUS_COLORS[order.status] }">
                    {{ STATUS_LABELS[order.status] ?? order.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button class="btn btn--outline" @click="showDetail = false">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import '@/assets/admin.css';
.filters { display: flex; gap: 1rem; margin-bottom: 1rem; padding: 0.75rem 1rem; }
.filters select, .filters input[type="date"] {
  padding: 0.4rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px;
}
.modal--wide { max-width: 860px; width: 95%; }
.route-detail { padding: 0 0 1rem; }
.route-meta {
  display: flex; flex-wrap: wrap; gap: 1.5rem;
  margin-bottom: 1.25rem; font-size: 0.9rem;
}
</style>
