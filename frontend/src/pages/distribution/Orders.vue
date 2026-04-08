<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useOrdersStore } from '@/stores/orders.js'

const store = useOrdersStore()
const showModal = ref(false)
const editMode  = ref(false)
const saving    = ref(false)
const error     = ref('')

const STATUSES = ['pending', 'scheduled', 'in_transit', 'delivered', 'failed', 'cancelled']
const STATUS_LABELS = {
  pending:    'Pendiente',
  scheduled:  'Programado',
  in_transit: 'En Camino',
  delivered:  'Entregado',
  failed:     'Fallido',
  cancelled:  'Cancelado',
}
const STATUS_COLORS = {
  pending:    '#6b7280',
  scheduled:  '#2563eb',
  in_transit: '#d97706',
  delivered:  '#16a34a',
  failed:     '#dc2626',
  cancelled:  '#9ca3af',
}

const form = reactive({
  id: null, client_id: '', reference_number: '', description: '',
  weight_kg: '', volume_m3: '', delivery_address: '',
  delivery_lat: '', delivery_lng: '',
  contact_name: '', contact_phone: '',
  requested_date: '', notes: '',
})

function resetForm() {
  Object.assign(form, {
    id: null, client_id: '', reference_number: '', description: '',
    weight_kg: '', volume_m3: '', delivery_address: '',
    delivery_lat: '', delivery_lng: '',
    contact_name: '', contact_phone: '',
    requested_date: '', notes: '',
  })
}

function openCreate() {
  resetForm()
  editMode.value  = false
  error.value     = ''
  showModal.value = true
}

function openEdit(order) {
  Object.assign(form, {
    id: order.id,
    client_id: order.client_id ?? '',
    reference_number: order.reference_number ?? '',
    description: order.description ?? '',
    weight_kg: order.weight_kg ?? '',
    volume_m3: order.volume_m3 ?? '',
    delivery_address: order.delivery_address ?? '',
    delivery_lat: order.delivery_lat ?? '',
    delivery_lng: order.delivery_lng ?? '',
    contact_name: order.contact_name ?? '',
    contact_phone: order.contact_phone ?? '',
    requested_date: order.requested_date ?? '',
    notes: order.notes ?? '',
  })
  editMode.value  = true
  error.value     = ''
  showModal.value = true
}

function buildPayload() {
  const raw = { ...form }
  delete raw.id
  ;['description', 'contact_name', 'contact_phone', 'notes', 'delivery_lat', 'delivery_lng'].forEach(k => {
    if (raw[k] === '') raw[k] = null
  })
  if (raw.weight_kg !== '' && raw.weight_kg !== null) raw.weight_kg = parseFloat(raw.weight_kg)
  else raw.weight_kg = null
  if (raw.volume_m3 !== '' && raw.volume_m3 !== null) raw.volume_m3 = parseFloat(raw.volume_m3)
  else raw.volume_m3 = null
  if (raw.client_id !== '') raw.client_id = parseInt(raw.client_id)
  return raw
}

async function save() {
  saving.value = true
  error.value  = ''
  try {
    const payload = buildPayload()
    if (editMode.value) {
      await store.editOrder(form.id, payload)
    } else {
      await store.addOrder(payload)
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

async function cancel(order) {
  if (!confirm(`¿Cancelar el pedido ${order.reference_number}?`)) return
  try {
    await store.cancelOrderById(order.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede cancelar')
  }
}

async function remove(order) {
  if (!confirm(`¿Eliminar el pedido ${order.reference_number}?`)) return
  try {
    await store.removeOrder(order.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede eliminar')
  }
}

function applyFilters() {
  store.fetchOrders(1)
}

onMounted(() => store.fetchOrders())
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Pedidos de Entrega</h1>
      <button class="btn btn--primary" @click="openCreate">+ Nuevo Pedido</button>
    </div>

    <!-- Filtros -->
    <div class="filters card">
      <select v-model="store.filters.status" @change="applyFilters">
        <option value="">Todos los estados</option>
        <option v-for="s in STATUSES" :key="s" :value="s">{{ STATUS_LABELS[s] }}</option>
      </select>
      <input v-model="store.filters.requested_date" type="date" @change="applyFilters" title="Filtrar por fecha solicitada" />
    </div>

    <!-- Tabla -->
    <div class="card">
      <div v-if="store.loading" class="loading-text">Cargando…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Referencia</th>
            <th>Cliente</th>
            <th>Dirección entrega</th>
            <th>Fecha solicitada</th>
            <th>Estado</th>
            <th>Ruta</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!store.orders.length">
            <td colspan="7" class="empty-row">Sin pedidos registrados</td>
          </tr>
          <tr v-for="order in store.orders" :key="order.id">
            <td><strong>{{ order.reference_number }}</strong></td>
            <td>{{ order.client?.name ?? '—' }}</td>
            <td>{{ order.delivery_address }}</td>
            <td>{{ order.requested_date }}</td>
            <td>
              <span class="badge" :style="{ background: STATUS_COLORS[order.status] }">
                {{ STATUS_LABELS[order.status] }}
              </span>
            </td>
            <td>{{ order.route?.name ?? '—' }}</td>
            <td class="actions">
              <button
                v-if="['pending', 'scheduled'].includes(order.status)"
                class="btn btn--sm btn--outline"
                @click="openEdit(order)"
              >Editar</button>
              <button
                v-if="['pending', 'scheduled'].includes(order.status)"
                class="btn btn--sm btn--danger"
                @click="cancel(order)"
              >Cancelar</button>
              <button
                v-if="['pending', 'cancelled'].includes(order.status)"
                class="btn btn--sm btn--danger"
                @click="remove(order)"
              >Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.meta?.last_page > 1" class="pagination">
        <button :disabled="store.meta.current_page === 1"
                @click="store.fetchOrders(store.meta.current_page - 1)">‹</button>
        <span>{{ store.meta.current_page }} / {{ store.meta.last_page }}</span>
        <button :disabled="store.meta.current_page === store.meta.last_page"
                @click="store.fetchOrders(store.meta.current_page + 1)">›</button>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ editMode ? 'Editar Pedido' : 'Nuevo Pedido' }}</h3>
          <button class="modal-close" @click="showModal = false">✕</button>
        </div>
        <form @submit.prevent="save">
          <div v-if="error" class="alert alert--error">{{ error }}</div>
          <div class="form-grid">
            <div class="field">
              <label>N° Referencia *</label>
              <input v-model="form.reference_number" type="text" required />
            </div>
            <div class="field">
              <label>ID Cliente *</label>
              <input v-model="form.client_id" type="number" required min="1" />
            </div>
            <div class="field field--full">
              <label>Dirección de entrega *</label>
              <input v-model="form.delivery_address" type="text" required />
            </div>
            <div class="field">
              <label>Nombre receptor</label>
              <input v-model="form.contact_name" type="text" />
            </div>
            <div class="field">
              <label>Teléfono receptor</label>
              <input v-model="form.contact_phone" type="text" />
            </div>
            <div class="field">
              <label>Fecha solicitada *</label>
              <input v-model="form.requested_date" type="date" required />
            </div>
            <div class="field">
              <label>Peso (kg)</label>
              <input v-model="form.weight_kg" type="number" step="0.01" min="0" />
            </div>
            <div class="field">
              <label>Volumen (m³)</label>
              <input v-model="form.volume_m3" type="number" step="0.01" min="0" />
            </div>
            <div class="field field--full">
              <label>Descripción de la carga</label>
              <textarea v-model="form.description" rows="2"></textarea>
            </div>
            <div class="field field--full">
              <label>Notas internas</label>
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
  </div>
</template>

<style scoped>
@import '@/assets/admin.css';
.filters { display: flex; gap: 1rem; margin-bottom: 1rem; padding: 0.75rem 1rem; }
.filters select, .filters input[type="date"] {
  padding: 0.4rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px;
}
</style>
