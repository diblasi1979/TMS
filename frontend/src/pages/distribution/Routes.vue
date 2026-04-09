<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoutesStore } from '@/stores/routes.js'
import { getRoute } from '@/api/routes.js'
import { getOrders } from '@/api/orders.js'
import { getVehicles } from '@/api/vehicles.js'
import { getOperators } from '@/api/operators.js'
import { getTrips } from '@/api/trips.js'

const store = useRoutesStore()
const showModal     = ref(false)
const showDetail    = ref(false)
const editMode      = ref(false)
const saving        = ref(false)
const actioning     = ref(false)
const error         = ref('')
const selectedRoute   = ref(null)
const pendingOrders   = ref([])
const selectedOrderId = ref('')
const detailLoading   = ref(false)
const vehicles      = ref([])
const operators     = ref([])
const availableTrips = ref([])

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
  trip_plan_id: '',
  vehicle_id: '', operator_id: '',
  total_distance_km: '', notes: '',
})

function resetForm() {
  Object.assign(form, {
    id: null, name: '', planned_date: '',
    trip_plan_id: '',
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
    trip_plan_id: route.trip_plan_id ?? '',
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
  detailLoading.value = true
  showDetail.value = true
  selectedRoute.value = null
  pendingOrders.value = []
  selectedOrderId.value = ''
  try {
    const [routeRes, ordersRes] = await Promise.all([
      getRoute(route.id),
      getOrders({ status: 'pending', per_page: 200 }),
    ])
    selectedRoute.value = routeRes.data.data
    pendingOrders.value = ordersRes.data.data ?? []
  } finally {
    detailLoading.value = false
  }
}

async function addOrder() {
  if (!selectedOrderId.value) return
  const orderId = parseInt(selectedOrderId.value)
  try {
    await store.attachOrder(selectedRoute.value.id, orderId)
    const { data } = await getRoute(selectedRoute.value.id)
    selectedRoute.value = data.data
    pendingOrders.value = pendingOrders.value.filter(o => o.id !== orderId)
    selectedOrderId.value = ''
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se pudo agregar el pedido')
  }
}

async function removeOrder(order) {
  if (!confirm(`¿Quitar el pedido "${order.reference_number}" de esta ruta?`)) return
  try {
    await store.detachOrder(selectedRoute.value.id, order.id)
    const { data } = await getRoute(selectedRoute.value.id)
    selectedRoute.value = data.data
    pendingOrders.value.push(order)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se pudo quitar el pedido')
  }
}

function buildPayload() {
  const raw = { ...form }
  delete raw.id
  ;['notes'].forEach(k => { if (raw[k] === '') raw[k] = null })
  if (raw.vehicle_id !== '') raw.vehicle_id = parseInt(raw.vehicle_id)
  else raw.vehicle_id = null
  if (raw.operator_id !== '') raw.operator_id = parseInt(raw.operator_id)
  else raw.operator_id = null
  if (raw.trip_plan_id !== '') raw.trip_plan_id = parseInt(raw.trip_plan_id)
  else raw.trip_plan_id = null
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
  const [vo, oo, to] = await Promise.all([
    getVehicles({ status: 'available' }),
    getOperators({ status: 'available' }),
    getTrips({ status: 'confirmed,draft', per_page: 100 }),
  ])
  vehicles.value      = vo.data.data ?? []
  operators.value     = oo.data.data ?? []
  availableTrips.value = to.data.data ?? []
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
      <select v-model="store.filters.trip_plan_id" @change="applyFilters">
        <option value="">Todos los viajes</option>
        <option v-for="t in availableTrips" :key="t.id" :value="t.id">
          {{ t.trip_number }} — {{ t.origin }} → {{ t.destination }}
        </option>
      </select>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div v-if="store.loading" class="loading-text">Cargando…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Viaje</th>
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
            <td colspan="8" class="empty-row">Sin rutas registradas</td>
          </tr>
          <tr v-for="route in store.routes" :key="route.id">
            <td><strong>{{ route.name }}</strong></td>
            <td>
              <span v-if="route.trip_plan" class="trip-badge" :title="route.trip_plan.origin + ' → ' + route.trip_plan.destination">
                🗓️ {{ route.trip_plan.trip_number }}
              </span>
              <span v-else class="text-muted">—</span>
            </td>
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
              <label>Viaje Planificado</label>
              <select v-model="form.trip_plan_id">
                <option value="">— Sin asociar a viaje —</option>
                <option v-for="t in availableTrips" :key="t.id" :value="t.id">
                  {{ t.trip_number }} — {{ t.origin }} → {{ t.destination }}
                </option>
              </select>
            </div>
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
          <h3>{{ selectedRoute?.name ?? '…' }}</h3>
          <button class="modal-close" @click="showDetail = false">✕</button>
        </div>
        <div v-if="detailLoading" class="loading-text" style="padding:1.5rem">Cargando…</div>
        <div v-else-if="selectedRoute" class="route-detail">
          <div class="route-meta">
            <span><strong>Fecha:</strong> {{ selectedRoute.planned_date }}</span>
            <span><strong>Estado:</strong>
              <span class="badge" :style="{ background: STATUS_COLORS[selectedRoute.status] }">
                {{ STATUS_LABELS[selectedRoute.status] }}
              </span>
            </span>
            <span v-if="selectedRoute.trip_plan">
              <strong>Viaje:</strong> 🗓️ {{ selectedRoute.trip_plan.trip_number }}
              ({{ selectedRoute.trip_plan.origin }} → {{ selectedRoute.trip_plan.destination }})
            </span>
            <span v-if="selectedRoute.vehicle"><strong>Vehículo:</strong> {{ selectedRoute.vehicle.plate }}</span>
            <span v-if="selectedRoute.operator"><strong>Operador:</strong> {{ selectedRoute.operator.name }}</span>
          </div>
          <h4>Pedidos ({{ selectedRoute.orders?.length ?? 0 }})</h4>

          <!-- Agregar pedido (solo en rutas editables) -->
          <div v-if="['draft','planned'].includes(selectedRoute.status)" class="add-order-row">
            <select v-model="selectedOrderId" class="add-order-select">
              <option value="">— Seleccionar pedido pendiente —</option>
              <option v-for="o in pendingOrders" :key="o.id" :value="o.id">
                {{ o.reference_number }} — {{ o.delivery_address }}
              </option>
            </select>
            <button
              class="btn btn--primary btn--sm"
              :disabled="!selectedOrderId"
              @click="addOrder"
            >+ Agregar</button>
          </div>

          <table class="data-table">
            <thead>
              <tr>
                <th>#</th><th>Referencia</th><th>Cliente</th><th>Dirección</th><th>Estado</th>
                <th v-if="['draft','planned'].includes(selectedRoute.status)">Acción</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!selectedRoute.orders?.length">
                <td :colspan="['draft','planned'].includes(selectedRoute.status) ? 6 : 5" class="empty-row">Sin pedidos asignados</td>
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
                <td v-if="['draft','planned'].includes(selectedRoute.status)">
                  <button class="btn btn--sm btn--danger" @click="removeOrder(order)">Quitar</button>
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
.modal--wide { max-width: 920px; width: 95%; }
.add-order-row { display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.75rem; }
.add-order-select { flex: 1; padding: 0.4rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; }
.route-detail { padding: 0 0 1rem; }
.route-meta {
  display: flex; flex-wrap: wrap; gap: 1.5rem;
  margin-bottom: 1.25rem; font-size: 0.9rem;
}
.trip-badge {
  display: inline-block;
  background: #ede9fe; color: #5b21b6;
  padding: 0.15rem 0.5rem; border-radius: 999px;
  font-size: 0.8rem; font-weight: 500;
  white-space: nowrap;
}
.text-muted { color: #9ca3af; }
</style>
