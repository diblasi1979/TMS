<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useVehiclesStore } from '@/stores/vehicles.js'
import { getExpiringVehicles } from '@/api/vehicles.js'

const store    = useVehiclesStore()
const expiring = ref([])
const showModal = ref(false)
const editMode  = ref(false)
const saving    = ref(false)
const error     = ref('')

const TYPES      = ['sedan','suv','pickup','van','truck','trailer','bus','motorcycle','other']
const FUEL_TYPES = ['gasoline','diesel','electric','hybrid','gas']
const STATUSES   = ['available','on_route','maintenance','inactive']

const STATUS_LABELS = {
  available:   'Disponible',
  on_route:    'En Ruta',
  maintenance: 'Mantenimiento',
  inactive:    'Inactivo',
}

const STATUS_COLORS = {
  available:   '#16a34a',
  on_route:    '#2563eb',
  maintenance: '#d97706',
  inactive:    '#6b7280',
}

const form = reactive({
  id: null, plate: '', brand: '', model: '', year: '',
  type: '', fuel_type: '', color: '', capacity: '',
  insurance_expiry: '', technical_review_expiry: '',
  circulation_permit_expiry: '', notes: '', company_id: '',
})

function resetForm() {
  Object.assign(form, {
    id: null, plate: '', brand: '', model: '', year: '',
    type: '', fuel_type: '', color: '', capacity: '',
    insurance_expiry: '', technical_review_expiry: '',
    circulation_permit_expiry: '', notes: '', company_id: '',
  })
}

function openCreate() {
  resetForm()
  editMode.value = false
  error.value    = ''
  showModal.value = true
}

function openEdit(v) {
  Object.assign(form, {
    id: v.id, plate: v.plate, brand: v.brand, model: v.model,
    year: v.year, type: v.type, fuel_type: v.fuel_type,
    color: v.color ?? '', capacity: v.capacity ?? '',
    insurance_expiry: v.insurance_expiry ?? '',
    technical_review_expiry: v.technical_review_expiry ?? '',
    circulation_permit_expiry: v.circulation_permit_expiry ?? '',
    notes: v.notes ?? '', company_id: v.company_id ?? '',
  })
  editMode.value  = true
  error.value     = ''
  showModal.value = true
}

async function save() {
  saving.value = true
  error.value  = ''
  try {
    const payload = { ...form }
    delete payload.id
    if (editMode.value) {
      await store.editVehicle(form.id, payload)
    } else {
      await store.addVehicle(payload)
    }
    showModal.value = false
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al guardar'
  } finally {
    saving.value = false
  }
}

async function remove(v) {
  if (!confirm(`¿Eliminar ${v.plate}?`)) return
  try {
    await store.removeVehicle(v.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede eliminar')
  }
}

async function changeStatus(v, status) {
  try {
    await store.changeStatus(v.id, status)
  } catch (e) {
    alert(e.response?.data?.message ?? 'Transición no permitida')
  }
}

function nextStatuses(current) {
  const map = {
    available:   ['on_route','maintenance','inactive'],
    on_route:    ['available'],
    maintenance: ['available','inactive'],
    inactive:    [],
  }
  return map[current] ?? []
}

function applyFilters() {
  store.fetchVehicles(1)
}

onMounted(async () => {
  await store.fetchVehicles()
  const { data } = await getExpiringVehicles()
  expiring.value = data.data ?? []
})
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1>Flota de Vehículos</h1>
      <button class="btn btn-primary" @click="openCreate">+ Nuevo Vehículo</button>
    </div>

    <!-- Alertas de documentos próximos a vencer -->
    <div v-if="expiring.length" class="alert-banner">
      <strong>⚠ Documentos próximos a vencer:</strong>
      <span v-for="a in expiring" :key="a.id + a.type" class="alert-tag">
        {{ a.plate }} — {{ a.type }} ({{ a.days_left }} días)
      </span>
    </div>

    <!-- Filtros -->
    <div class="filters card">
      <select v-model="store.filters.status" @change="applyFilters">
        <option value="">Todos los estados</option>
        <option v-for="s in STATUSES" :key="s" :value="s">{{ STATUS_LABELS[s] }}</option>
      </select>
      <select v-model="store.filters.type" @change="applyFilters">
        <option value="">Todos los tipos</option>
        <option v-for="t in TYPES" :key="t" :value="t">{{ t }}</option>
      </select>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div v-if="store.loading" class="loading">Cargando…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Placa</th><th>Marca / Modelo</th><th>Tipo</th>
            <th>Estado</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in store.vehicles" :key="v.id">
            <td><strong>{{ v.plate }}</strong></td>
            <td>{{ v.brand }} {{ v.model }} ({{ v.year }})</td>
            <td>{{ v.type }}</td>
            <td>
              <span class="badge" :style="{ background: STATUS_COLORS[v.status] }">
                {{ STATUS_LABELS[v.status] }}
              </span>
            </td>
            <td class="actions">
              <button class="btn btn-sm btn-secondary" @click="openEdit(v)">Editar</button>
              <select
                v-if="nextStatuses(v.status).length"
                class="btn btn-sm"
                @change="e => { changeStatus(v, e.target.value); e.target.value = '' }"
              >
                <option value="">→ Estado</option>
                <option v-for="s in nextStatuses(v.status)" :key="s" :value="s">
                  {{ STATUS_LABELS[s] }}
                </option>
              </select>
              <button class="btn btn-sm btn-danger" @click="remove(v)">Eliminar</button>
            </td>
          </tr>
          <tr v-if="!store.vehicles.length">
            <td colspan="5" class="empty">No hay vehículos registrados</td>
          </tr>
        </tbody>
      </table>

      <!-- Paginación -->
      <div v-if="store.meta?.last_page > 1" class="pagination">
        <button
          v-for="p in store.meta.last_page" :key="p"
          class="btn btn-sm"
          :class="{ 'btn-primary': p === store.meta.current_page }"
          @click="store.fetchVehicles(p)"
        >{{ p }}</button>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal">
          <div class="modal-header">
            <h2>{{ editMode ? 'Editar Vehículo' : 'Nuevo Vehículo' }}</h2>
            <button class="close-btn" @click="showModal = false">✕</button>
          </div>
          <div class="modal-body">
            <p v-if="error" class="form-error">{{ error }}</p>
            <div class="form-grid">
              <div class="form-group">
                <label>Placa *</label>
                <input v-model="form.plate" placeholder="ABC-123" />
              </div>
              <div class="form-group">
                <label>Marca *</label>
                <input v-model="form.brand" placeholder="Toyota" />
              </div>
              <div class="form-group">
                <label>Modelo *</label>
                <input v-model="form.model" placeholder="Hilux" />
              </div>
              <div class="form-group">
                <label>Año *</label>
                <input v-model="form.year" type="number" placeholder="2023" />
              </div>
              <div class="form-group">
                <label>Tipo *</label>
                <select v-model="form.type">
                  <option value="">Seleccionar</option>
                  <option v-for="t in TYPES" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Combustible *</label>
                <select v-model="form.fuel_type">
                  <option value="">Seleccionar</option>
                  <option v-for="f in FUEL_TYPES" :key="f" :value="f">{{ f }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Color</label>
                <input v-model="form.color" placeholder="Blanco" />
              </div>
              <div class="form-group">
                <label>Capacidad (kg)</label>
                <input v-model="form.capacity" type="number" placeholder="1500" />
              </div>
              <div class="form-group">
                <label>Venc. Seguro</label>
                <input v-model="form.insurance_expiry" type="date" />
              </div>
              <div class="form-group">
                <label>Venc. Rev. Técnica</label>
                <input v-model="form.technical_review_expiry" type="date" />
              </div>
              <div class="form-group">
                <label>Venc. Permiso Circ.</label>
                <input v-model="form.circulation_permit_expiry" type="date" />
              </div>
              <div class="form-group form-group-full">
                <label>Notas</label>
                <textarea v-model="form.notes" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="showModal = false">Cancelar</button>
            <button class="btn btn-primary" :disabled="saving" @click="save">
              {{ saving ? 'Guardando…' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@import '@/assets/admin.css';

.alert-banner {
  background: #fef3c7;
  border: 1px solid #f59e0b;
  border-radius: 6px;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  font-size: 0.85rem;
}
.alert-tag {
  background: #fde68a;
  border-radius: 4px;
  padding: 0.2rem 0.5rem;
}
.filters { display: flex; gap: 1rem; margin-bottom: 1rem; padding: 0.75rem 1rem; }
.filters select { padding: 0.4rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; }
.actions { display: flex; gap: 0.5rem; align-items: center; }
.empty { text-align: center; color: #6b7280; padding: 2rem; }
.pagination { display: flex; gap: 0.5rem; margin-top: 1rem; justify-content: center; }
.loading { text-align: center; padding: 2rem; color: #6b7280; }
.form-error { color: #dc2626; font-size: 0.875rem; margin-bottom: 0.5rem; }
.form-group-full { grid-column: 1 / -1; }
</style>
