<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useOperatorsStore } from '@/stores/operators.js'
import { getExpiringLicenses } from '@/api/operators.js'

const store    = useOperatorsStore()
const expiring = ref([])
const showModal = ref(false)
const editMode  = ref(false)
const saving    = ref(false)
const error     = ref('')

const LICENSE_TYPES = ['A1','A2','B','C','D','E']
const STATUSES      = ['available','on_duty','off_duty','inactive']

const STATUS_LABELS = {
  available: 'Disponible',
  on_duty:   'En Servicio',
  off_duty:  'Fuera de Servicio',
  inactive:  'Inactivo',
}

const STATUS_COLORS = {
  available: '#16a34a',
  on_duty:   '#2563eb',
  off_duty:  '#d97706',
  inactive:  '#6b7280',
}

const form = reactive({
  id: null, name: '', document_number: '', phone: '', email: '',
  address: '', license_number: '', license_type: '', license_expiry: '',
  emergency_contact: '', emergency_phone: '', notes: '',
  company_id: '', is_active: true,
})

function resetForm() {
  Object.assign(form, {
    id: null, name: '', document_number: '', phone: '', email: '',
    address: '', license_number: '', license_type: '', license_expiry: '',
    emergency_contact: '', emergency_phone: '', notes: '',
    company_id: '', is_active: true,
  })
}

function openCreate() {
  resetForm()
  editMode.value  = false
  error.value     = ''
  showModal.value = true
}

function openEdit(op) {
  Object.assign(form, {
    id: op.id,
    name: op.name,
    document_number: op.document_number ?? '',
    phone: op.phone ?? '',
    email: op.email ?? '',
    address: op.address ?? '',
    license_number: op.license_number ?? '',
    license_type: op.license_type ?? '',
    license_expiry: op.license_expiry ?? '',
    emergency_contact: op.emergency_contact ?? '',
    emergency_phone: op.emergency_phone ?? '',
    notes: op.notes ?? '',
    company_id: op.company_id ?? '',
    is_active: op.is_active ?? true,
  })
  editMode.value  = true
  error.value     = ''
  showModal.value = true
}

function buildPayload() {
  const raw = { ...form }
  delete raw.id
  ;['phone','email','address','emergency_contact','emergency_phone','notes','company_id'].forEach(k => {
    if (raw[k] === '') raw[k] = null
  })
  return raw
}

async function save() {
  saving.value = true
  error.value  = ''
  try {
    const payload = buildPayload()
    if (editMode.value) {
      await store.editOperator(form.id, payload)
    } else {
      await store.addOperator(payload)
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

async function remove(op) {
  if (!confirm(`¿Eliminar a ${op.name}?`)) return
  try {
    await store.removeOperator(op.id)
  } catch (e) {
    alert(e.response?.data?.message ?? 'No se puede eliminar')
  }
}

async function changeStatus(op, status) {
  try {
    await store.changeStatus(op.id, status)
  } catch (e) {
    alert(e.response?.data?.message ?? 'Transición no permitida')
  }
}

function nextStatuses(current) {
  const map = {
    available: ['on_duty','off_duty','inactive'],
    on_duty:   ['available','off_duty'],
    off_duty:  ['available','inactive'],
    inactive:  [],
  }
  return map[current] ?? []
}

function applyFilters() {
  store.fetchOperators(1)
}

onMounted(async () => {
  await store.fetchOperators()
  const { data } = await getExpiringLicenses()
  expiring.value = data.data ?? []
})
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Operadores</h1>
      <button class="btn btn--primary" @click="openCreate">+ Nuevo Operador</button>
    </div>

    <!-- Alertas de licencias próximas a vencer -->
    <div v-if="expiring.length" class="alert-banner">
      <strong>⚠ Licencias próximas a vencer:</strong>
      <span v-for="op in expiring" :key="op.id" class="alert-tag">
        {{ op.name }} — {{ op.license_type }} ({{ op.license_expiry }})
      </span>
    </div>

    <!-- Filtros -->
    <div class="filters card">
      <select v-model="store.filters.status" @change="applyFilters">
        <option value="">Todos los estados</option>
        <option v-for="s in STATUSES" :key="s" :value="s">{{ STATUS_LABELS[s] }}</option>
      </select>
    </div>

    <!-- Tabla -->
    <div class="card">
      <div v-if="store.loading" class="loading-text">Cargando…</div>

      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Documento</th>
            <th>Licencia</th>
            <th>Venc. Licencia</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!store.operators.length">
            <td colspan="6" class="empty-row">Sin registros</td>
          </tr>
          <tr v-for="op in store.operators" :key="op.id">
            <td><strong>{{ op.name }}</strong></td>
            <td>{{ op.document_number }}</td>
            <td>{{ op.license_type }} — {{ op.license_number }}</td>
            <td>{{ op.license_expiry }}</td>
            <td>
              <span class="badge" :style="{ background: STATUS_COLORS[op.status] }">
                {{ STATUS_LABELS[op.status] }}
              </span>
            </td>
            <td class="actions">
              <button class="btn btn--sm btn--outline" @click="openEdit(op)">Editar</button>
              <select
                v-if="nextStatuses(op.status).length"
                class="status-select"
                @change="e => { changeStatus(op, e.target.value); e.target.value = '' }"
              >
                <option value="">→ Estado</option>
                <option v-for="s in nextStatuses(op.status)" :key="s" :value="s">
                  {{ STATUS_LABELS[s] }}
                </option>
              </select>
              <button class="btn btn--sm btn--danger" @click="remove(op)">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.meta?.last_page > 1" class="pagination">
        <button :disabled="store.meta.current_page === 1"
                @click="store.fetchOperators(store.meta.current_page - 1)">‹</button>
        <span>{{ store.meta.current_page }} / {{ store.meta.last_page }}</span>
        <button :disabled="store.meta.current_page === store.meta.last_page"
                @click="store.fetchOperators(store.meta.current_page + 1)">›</button>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ editMode ? 'Editar Operador' : 'Nuevo Operador' }}</h3>
          <button class="modal-close" @click="showModal = false">✕</button>
        </div>
        <form @submit.prevent="save">
          <div v-if="error" class="alert alert--error">{{ error }}</div>
          <div class="form-grid">
            <div class="field">
              <label>Nombre completo *</label>
              <input v-model="form.name" type="text" required />
            </div>
            <div class="field">
              <label>N° Documento *</label>
              <input v-model="form.document_number" type="text" required />
            </div>
            <div class="field">
              <label>Teléfono</label>
              <input v-model="form.phone" type="text" />
            </div>
            <div class="field">
              <label>Email</label>
              <input v-model="form.email" type="email" />
            </div>
            <div class="field field--full">
              <label>Dirección</label>
              <input v-model="form.address" type="text" />
            </div>
            <div class="field">
              <label>N° Licencia *</label>
              <input v-model="form.license_number" type="text" required />
            </div>
            <div class="field">
              <label>Clase licencia *</label>
              <select v-model="form.license_type" required>
                <option value="">— Seleccionar —</option>
                <option v-for="l in LICENSE_TYPES" :key="l" :value="l">{{ l }}</option>
              </select>
            </div>
            <div class="field">
              <label>Vencimiento licencia *</label>
              <input v-model="form.license_expiry" type="date" required />
            </div>
            <div class="field">
              <label>Contacto emergencia</label>
              <input v-model="form.emergency_contact" type="text" />
            </div>
            <div class="field">
              <label>Teléfono emergencia</label>
              <input v-model="form.emergency_phone" type="text" />
            </div>
            <div class="field field--full">
              <label>Notas</label>
              <input v-model="form.notes" type="text" />
            </div>
            <div class="field">
              <label>
                <input v-model="form.is_active" type="checkbox" />
                Activo
              </label>
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
.alert-tag { background: #fde68a; border-radius: 4px; padding: 0.2rem 0.5rem; }
.filters { display: flex; gap: 1rem; margin-bottom: 1rem; padding: 0.75rem 1rem; }
.filters select { padding: 0.4rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; }
.status-select {
  padding: 0.3rem 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.8rem;
  cursor: pointer;
}
</style>
