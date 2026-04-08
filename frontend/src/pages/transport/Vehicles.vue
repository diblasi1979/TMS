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
  <div>
    <div class="page-header">
      <h1>Flota de Vehículos</h1>
      <button class="btn btn--primary" @click="openCreate">+ Nuevo Vehículo</button>
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
      <div v-if="store.loading" class="loading-text">Cargando…</div>

      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Placa</th>
            <th>Marca / Modelo</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!store.vehicles.length">
            <td colspan="5" class="empty-row">Sin registros</td>
          </tr>
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
              <button class="btn btn--sm btn--outline" @click="openEdit(v)">Editar</button>
              <select
                v-if="nextStatuses(v.status).length"
                class="status-select"
                @change="e => { changeStatus(v, e.target.value); e.target.value = '' }"
              >
                <option value="">→ Estado</option>
                <option v-for="s in nextStatuses(v.status)" :key="s" :value="s">
                  {{ STATUS_LABELS[s] }}
                </option>
              </select>
              <button class="btn btn--sm btn--danger" @click="remove(v)">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.meta?.last_page > 1" class="pagination">
        <button :disabled="store.meta.current_page === 1" @click="store.fetchVehicles(store.meta.current_page - 1)">‹</button>
        <span>{{ store.meta.current_page }} / {{ store.meta.last_page }}</span>
        <button :disabled="store.meta.current_page === store.meta.last_page" @click="store.fetchVehicles(store.meta.current_page + 1)">›</button>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ editMode ? 'Editar Vehículo' : 'Nuevo Vehículo' }}</h3>
          <button class="modal-close" @click="showModal = false">✕</button>
        </div>
        <form @submit.prevent="save">
          <div v-if="error" class="alert alert--error">{{ error }}</div>
          <div class="form-grid">
            <div class="field">
              <label>Placa *</label>
              <input v-model="form.plate" type="text" required />
            </div>
            <div class="field">
              <label>Marca *</label>
              <input v-model="form.brand" type="text" required />
            </div>
            <div class="field">
              <label>Modelo *</label>
              <input v-model="form.model" type="text" required />
            </div>
            <div class="field">
              <label>Año *</label>
              <input v-model="form.year" type="number" required />
            </div>
            <div class="field">
              <label>Tipo *</label>
              <select v-model="form.type" required>
                <option value="">— Seleccionar —</option>
                <option v-for="t in TYPES" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
            <div class="field">
              <label>Combustible *</label>
              <select v-model="form.fuel_type" required>
                <option value="">— Seleccionar —</option>
                <option v-for="f in FUEL_TYPES" :key="f" :value="f">{{ f }}</option>
              </select>
            </div>
            <div class="field">
              <label>Color</label>
              <input v-model="form.color" type="text" />
            </div>
            <div class="field">
              <label>Capacidad (kg)</label>
              <input v-model="form.capacity" type="number" />
            </div>
            <div class="field">
              <label>Venc. Seguro</label>
              <input v-model="form.insurance_expiry" type="date" />
            </div>
            <div class="field">
              <label>Venc. Rev. Técnica</label>
              <input v-model="form.technical_review_expiry" type="date" />
            </div>
            <div class="field">
              <label>Venc. Permiso Circ.</label>
              <input v-model="form.circulation_permit_expiry" type="date" />
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
.filters {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 0.75rem 1rem;
}
.filters select {
  padding: 0.4rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
}
.status-select {
  padding: 0.3rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.8rem;
  cursor: pointer;
}
</style>
