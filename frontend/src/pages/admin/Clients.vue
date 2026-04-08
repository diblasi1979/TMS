<script setup>
import { ref, onMounted, reactive } from 'vue'
import { getClients, createClient, updateClient, deleteClient } from '@/api/clients.js'
import { getCompanies } from '@/api/companies.js'

const clients    = ref([])
const companies  = ref([])
const pagination = ref({})
const loading    = ref(false)
const showModal  = ref(false)
const isEditing  = ref(false)
const saving     = ref(false)
const error      = ref('')
const formError  = ref('')

const form = reactive({
  id: null, name: '', email: '', phone: '', address: '', company_id: '', is_active: true,
})

function resetForm() {
  Object.assign(form, { id: null, name: '', email: '', phone: '', address: '', company_id: '', is_active: true })
  formError.value = ''
}

async function load(page = 1) {
  loading.value = true
  try {
    const [clientsRes, companiesRes] = await Promise.all([getClients(page), getCompanies()])
    clients.value    = clientsRes.data.data
    pagination.value = clientsRes.data.meta
    companies.value  = companiesRes.data.data
  } catch {
    error.value = 'Error al cargar clientes.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  resetForm()
  isEditing.value = false
  showModal.value = true
}

function openEdit(client) {
  Object.assign(form, { ...client })
  isEditing.value = true
  showModal.value = true
}

async function save() {
  formError.value = ''
  saving.value    = true
  try {
    if (isEditing.value) {
      await updateClient(form.id, form)
    } else {
      await createClient(form)
    }
    showModal.value = false
    await load(pagination.value?.current_page ?? 1)
  } catch (e) {
    const errors = e.response?.data?.errors
    formError.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message ?? 'Error al guardar.')
  } finally {
    saving.value = false
  }
}

async function remove(id) {
  if (!confirm('¿Eliminar este cliente?')) return
  try {
    await deleteClient(id)
    await load(pagination.value?.current_page ?? 1)
  } catch {
    error.value = 'Error al eliminar.'
  }
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Clientes</h1>
      <button class="btn btn--primary" @click="openCreate">+ Nuevo cliente</button>
    </div>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div class="card">
      <div v-if="loading" class="loading-text">Cargando…</div>

      <table v-else class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Empresa</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!clients.length">
            <td colspan="7" class="empty-row">Sin registros</td>
          </tr>
          <tr v-for="c in clients" :key="c.id">
            <td>{{ c.id }}</td>
            <td>{{ c.name }}</td>
            <td>{{ c.email ?? '—' }}</td>
            <td>{{ c.phone ?? '—' }}</td>
            <td>{{ c.company?.name ?? '—' }}</td>
            <td><span class="badge" :class="c.is_active ? 'badge--green' : 'badge--red'">{{ c.is_active ? 'Activo' : 'Inactivo' }}</span></td>
            <td class="actions">
              <button class="btn btn--sm btn--outline" @click="openEdit(c)">Editar</button>
              <button class="btn btn--sm btn--danger" @click="remove(c.id)">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="pagination.last_page > 1" class="pagination">
        <button :disabled="pagination.current_page === 1" @click="load(pagination.current_page - 1)">‹</button>
        <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button :disabled="pagination.current_page === pagination.last_page" @click="load(pagination.current_page + 1)">›</button>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ isEditing ? 'Editar cliente' : 'Nuevo cliente' }}</h3>
          <button class="modal-close" @click="showModal = false">✕</button>
        </div>
        <form @submit.prevent="save">
          <div v-if="formError" class="alert alert--error">{{ formError }}</div>
          <div class="form-grid">
            <div class="field">
              <label>Nombre *</label>
              <input v-model="form.name" type="text" required />
            </div>
            <div class="field">
              <label>Email</label>
              <input v-model="form.email" type="email" />
            </div>
            <div class="field">
              <label>Teléfono</label>
              <input v-model="form.phone" type="text" />
            </div>
            <div class="field">
              <label>Empresa</label>
              <select v-model="form.company_id">
                <option value="">— Sin asignar —</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="field field--full">
              <label>Dirección</label>
              <input v-model="form.address" type="text" />
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
            <button type="submit" class="btn btn--primary" :disabled="saving">{{ saving ? 'Guardando…' : 'Guardar' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import '@/assets/admin.css';
</style>
