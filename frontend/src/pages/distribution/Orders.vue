<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useOrdersStore } from '@/stores/orders.js'

const store = useOrdersStore()
const showModal = ref(false)
const editMode  = ref(false)
const saving    = ref(false)
const exportError = ref('')
const error     = ref('')

const exportFilters = reactive({
  client_id: '',
  requested_date: '',
})

const STATUSES = ['pending', 'sent', 'scheduled', 'in_transit', 'delivered', 'failed', 'cancelled']
const STATUS_LABELS = {
  pending:    'Pendiente',
  sent:       'Enviado',
  scheduled:  'Programado',
  in_transit: 'En Camino',
  delivered:  'Entregado',
  failed:     'Fallido',
  cancelled:  'Cancelado',
}
const STATUS_COLORS = {
  pending:    '#6b7280',
  sent:       '#0f766e',
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

async function exportPendingOrders() {
  exportError.value = ''
  try {
    await store.exportPending({
      client_id: exportFilters.client_id !== '' ? parseInt(exportFilters.client_id) : '',
      requested_date: exportFilters.requested_date,
    })
  } catch (e) {
    exportError.value = e.response?.data?.message ?? 'No se pudieron exportar los pedidos pendientes'
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
      <div class="page-header__actions">
        <button class="btn btn--outline" @click="store.clearExportResult()" :disabled="!store.exportResult">Limpiar Resultado</button>
        <button class="btn btn--primary" @click="openCreate">+ Nuevo Pedido</button>
      </div>
    </div>

    <div class="card export-panel">
      <div class="export-panel__header">
        <div>
          <h2>Exportar Pedidos Pendientes</h2>
          <p>Envia al optimizador solo pedidos pendientes no exportados. Requiere coordenadas y external_id en la respuesta.</p>
        </div>
        <button class="btn btn--primary" @click="exportPendingOrders" :disabled="store.exporting">
          {{ store.exporting ? 'Exportando…' : 'Exportar Pendientes' }}
        </button>
      </div>

      <div class="export-panel__filters">
        <div class="field">
          <label>ID Cliente</label>
          <input v-model="exportFilters.client_id" type="number" min="1" placeholder="Opcional" />
        </div>
        <div class="field">
          <label>Fecha solicitada</label>
          <input v-model="exportFilters.requested_date" type="date" />
        </div>
      </div>

      <div v-if="exportError" class="alert alert--error export-panel__alert">{{ exportError }}</div>

      <div v-if="store.exportResult" class="export-result">
        <div class="export-result__summary">
          <span class="badge badge--blue">Pendientes: {{ store.exportResult.total_pending }}</span>
          <span class="badge badge--green">Enviados: {{ store.exportResult.sent_count }}</span>
          <span class="badge badge--red">Fallidos: {{ store.exportResult.failed_count }}</span>
        </div>
        <p class="export-result__message">{{ store.exportResult.message }}</p>

        <div v-if="store.exportResult.sent?.length" class="export-result__block">
          <h3>Enviados</h3>
          <table class="data-table data-table--compact">
            <thead>
              <tr>
                <th>Pedido</th>
                <th>External ID</th>
                <th>Dirección</th>
                <th>Notas</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in store.exportResult.sent" :key="`sent-${item.order_id}`">
                <td>{{ item.reference_number }}</td>
                <td>{{ item.response_body?.external_id ?? item.response_body?.id ?? '—' }}</td>
                <td>{{ item.payload?.address }}</td>
                <td>{{ item.payload?.notes || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="store.exportResult.failed?.length" class="export-result__block">
          <h3>Fallidos</h3>
          <table class="data-table data-table--compact">
            <thead>
              <tr>
                <th>Pedido</th>
                <th>Motivo</th>
                <th>Dirección</th>
                <th>Estado respuesta</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in store.exportResult.failed" :key="`failed-${item.order_id}`">
                <td>{{ item.reference_number }}</td>
                <td>{{ item.reason ?? 'Error externo' }}</td>
                <td>{{ item.payload?.address ?? '—' }}</td>
                <td>{{ item.response_status ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Filtros -->
    <div class="filters card">
      <select v-model="store.filters.status" @change="applyFilters">
        <option value="">Todos los estados</option>
        <option v-for="s in STATUSES" :key="s" :value="s">{{ STATUS_LABELS[s] }}</option>
      </select>
      <input v-model="store.filters.client_id" type="number" min="1" @change="applyFilters" placeholder="ID Cliente" />
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
            <th>Exportación</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!store.orders.length">
            <td colspan="8" class="empty-row">Sin pedidos registrados</td>
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
            <td>
              <div class="export-status">
                <span v-if="order.optimizer_external_id" class="badge badge--green">{{ order.optimizer_external_id }}</span>
                <span v-else-if="order.optimizer_last_error" class="badge badge--red">Falló</span>
                <span v-else class="badge badge--gray">Pendiente</span>
                <small v-if="order.optimizer_exported_at" class="export-status__meta">{{ new Date(order.optimizer_exported_at).toLocaleString() }}</small>
                <small v-else-if="order.optimizer_last_error" class="export-status__meta export-status__meta--error">{{ order.optimizer_last_error }}</small>
              </div>
            </td>
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
.page-header__actions { display: flex; gap: 0.75rem; }
.export-panel { padding: 1rem; margin-bottom: 1rem; }
.export-panel__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}
.export-panel__header h2 {
  margin: 0 0 0.25rem;
  color: #1e3a5f;
  font-size: 1rem;
}
.export-panel__header p {
  margin: 0;
  color: #6b7280;
  font-size: 0.88rem;
}
.export-panel__filters {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 220px));
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.export-panel__alert { margin-bottom: 0; }
.export-result {
  margin-top: 1rem;
  border-top: 1px solid #e5e7eb;
  padding-top: 1rem;
}
.export-result__summary {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.75rem;
}
.export-result__message {
  margin: 0 0 1rem;
  color: #374151;
}
.export-result__block + .export-result__block { margin-top: 1rem; }
.export-result__block h3 {
  margin: 0 0 0.5rem;
  font-size: 0.95rem;
  color: #1f2937;
}
.data-table--compact th,
.data-table--compact td {
  padding: 0.55rem 0.75rem;
}
.filters { display: flex; gap: 1rem; margin-bottom: 1rem; padding: 0.75rem 1rem; }
.filters select, .filters input[type="date"], .filters input[type="number"] {
  padding: 0.4rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px;
}
.export-status {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.export-status__meta {
  font-size: 0.72rem;
  color: #6b7280;
  max-width: 220px;
  line-height: 1.3;
}
.export-status__meta--error { color: #b91c1c; }
@media (max-width: 900px) {
  .export-panel__header { flex-direction: column; }
  .export-panel__filters { grid-template-columns: 1fr; }
  .page-header { align-items: flex-start; gap: 0.75rem; }
  .page-header__actions { width: 100%; justify-content: flex-end; }
}
</style>
