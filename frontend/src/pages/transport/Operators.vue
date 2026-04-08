<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useOperatorsStore } from '@/stores/operators.js'
import { getExpiringLicenses } from '@/api/operators.js'

const store   = useOperatorsStore()
const expiring = ref([])
const showModal = ref(false)
const editMode  = ref(false)
const saving    = ref(false)
const error     = ref('')

const LICENSE_TYPES = ['A1','A2','B','C','D','E']
const STATUSES       = ['available','on_duty','off_duty','inactive']

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
  id: null, name: '', dni: '', phone: '', email: '',
  license_number: '', license_type: '', license_expiry: '',
  notes: '', company_id: '',
})

function resetForm() {
  Object.assign(form, {
    id: null, name: '', dni: '', phone: '', email: '',
    license_number: '', license_type: '', license_expiry: '',
    notes: '', company_id: '',
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
    id: op.id, name: op.name, dni: op.dni ?? '', phone: op.phone ?? '',
    email: op.email ?? '', license_number: op.license_number,
    license_type: op.license_type, license_expiry: op.license_expiry ?? '',
    notes: op.notes ?? '', company_id: op.company_id ?? '',
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
      await store.editOperator(form.id, payload)
    } else {
      await store.addOperator(payload)
    }
    showModal.value = false
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al guardar'
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
  <div class="page">
    <div class="page-header">
      <h1>Operadores</h1>
      <button class="btn btn-primary" @click="openCreate">+ Nuevo Operador</button>
    </div>

    <!-- Alerta licencias próximas a vencer -->
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
      <div v-if="store.loading" class="loading">Cargando…</div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Nombre</th><th>DNI</th><th>Licencia</th>
            <th>Venc. Licencia</th><th>Estado</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="op in store.operators" :key="op.id">
            <td><strong>{{ op.name }}</strong></td>
            <td>{{ op.dni }}</td>
            <td>{{ op.license_type }} — {{ op.license_number }}</td>
            <td>{{ op.license_expiry }}</td>
            <td>
              <span class="badge" :style="{ background: STATUS_COLORS[op.status] }">
                {{ STATUS_LABELS[op.status] }}
              </span>
            </td>
            <td class="actions">
              <button class="btn btn-sm btn-secondary" @click="openEdit(op)">Editar</button>
              <select
                v-if="nextStatuses(op.status).length"
                class="btn btn-sm"
                @change="e => { changeStatus(op, e.target.value); e.target.value = '' }"
              >
                <option value="">→ Estado</option>
                <option v-for="s in nextStatuses(op.status)" :key="s" :value="s">
                  {{ STATUS_LABELS[s] }}
                </option>
              </select>
              <button class="btn btn-sm btn-danger" @click="remove(op)">Eliminar</button>
            </td>
          </tr>
          <tr v-if="!store.operators.length">
            <td colspan="6" class="empty">No hay operadores registrados</td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.meta?.last_page > 1" class="pagination">
        <button
          v-for="p in store.meta.last_page" :key="p"
          class="btn btn-sm"
          :class="{ 'btn-primary': p === store.meta.current_page }"
          @click="store.fetchOperators(p)"
        >{{ p }}</button>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal">
          <div class="modal-header">
            <h2>{{ editMode ? 'Editar Operador' : 'Nuevo Operador' }}</h2>
            <button class="close-btn" @click="showModal = false">✕</button>
          </div>
          <div class="modal-body">
            <p v-if="error" class="form-error">{{ error }}</p>
            <div class="form-grid">
              <div class="form-group">
                <label>Nombre completo *</label>
                <input v-model="form.name" placeholder="Juan Pérez" />
              </div>
              <div class="form-group">
                <label>DNI / RUT *</label>
                <input v-model="form.dni" placeholder="12345678-9" />
              </div>
              <div class="form-group">
                <label>Teléfono</label>
                <input v-model="form.phone" placeholder="+56 9 1234 5678" />
              </div>
              <div class="form-group">
                <label>Email</label>
                <input v-model="form.email" type="email" placeholder="juan@empresa.com" />
              </div>
              <div class="form-group">
                <label>N° Licencia *</label>
                <input v-model="form.license_number" placeholder="LIC-001" />
              </div>
              <div class="form-group">
                <label>Clase licencia *</label>
                <select v-model="form.license_type">
                  <option value="">Seleccionar</option>
                  <option v-for="l in LICENSE_TYPES" :key="l" :value="l">{{ l }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Vencimiento licencia</label>
                <input v-model="form.license_expiry" type="date" />
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
.alert-tag { background: #fde68a; border-radius: 4px; padding: 0.2rem 0.5rem; }
.filters { display: flex; gap: 1rem; margin-bottom: 1rem; padding: 0.75rem 1rem; }
.filters select { padding: 0.4rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; }
.actions { display: flex; gap: 0.5rem; align-items: center; }
.empty { text-align: center; color: #6b7280; padding: 2rem; }
.pagination { display: flex; gap: 0.5rem; margin-top: 1rem; justify-content: center; }
.loading { text-align: center; padding: 2rem; color: #6b7280; }
.form-error { color: #dc2626; font-size: 0.875rem; margin-bottom: 0.5rem; }
.form-group-full { grid-column: 1 / -1; }
</style>
