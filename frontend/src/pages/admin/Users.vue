<script setup>
import { ref, onMounted, reactive } from 'vue'
import { getUsers, createUser, updateUser, deleteUser } from '@/api/users.js'
import { getCompanies } from '@/api/companies.js'

const users      = ref([])
const companies  = ref([])
const pagination = ref({})
const loading    = ref(false)
const showModal  = ref(false)
const isEditing  = ref(false)
const saving     = ref(false)
const error      = ref('')
const formError  = ref('')

const form = reactive({
  id: null, name: '', email: '', password: '', role: 'user', company_id: '', is_active: true,
})

function resetForm() {
  Object.assign(form, { id: null, name: '', email: '', password: '', role: 'user', company_id: '', is_active: true })
  formError.value = ''
}

async function load(page = 1) {
  loading.value = true
  try {
    const [usersRes, companiesRes] = await Promise.all([getUsers(page), getCompanies()])
    users.value      = usersRes.data.data
    pagination.value = usersRes.data.meta
    companies.value  = companiesRes.data.data
  } catch {
    error.value = 'Error al cargar usuarios.'
  } finally {
    loading.value = false
  }
}

function openCreate() {
  resetForm()
  isEditing.value = false
  showModal.value = true
}

function openEdit(user) {
  Object.assign(form, { ...user, password: '' })
  isEditing.value = true
  showModal.value = true
}

async function save() {
  formError.value = ''
  saving.value    = true
  const payload   = { ...form }
  if (isEditing.value && !payload.password) delete payload.password
  try {
    if (isEditing.value) {
      await updateUser(form.id, payload)
    } else {
      await createUser(payload)
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
  if (!confirm('¿Eliminar este usuario?')) return
  try {
    await deleteUser(id)
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
      <h1>Usuarios</h1>
      <button class="btn btn--primary" @click="openCreate">+ Nuevo usuario</button>
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
            <th>Rol</th>
            <th>Empresa</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!users.length">
            <td colspan="7" class="empty-row">Sin registros</td>
          </tr>
          <tr v-for="u in users" :key="u.id">
            <td>{{ u.id }}</td>
            <td>{{ u.name }}</td>
            <td>{{ u.email }}</td>
            <td><span class="badge" :class="u.role === 'admin' ? 'badge--blue' : 'badge--gray'">{{ u.role }}</span></td>
            <td>{{ u.company?.name ?? '—' }}</td>
            <td><span class="badge" :class="u.is_active ? 'badge--green' : 'badge--red'">{{ u.is_active ? 'Activo' : 'Inactivo' }}</span></td>
            <td class="actions">
              <button class="btn btn--sm btn--outline" @click="openEdit(u)">Editar</button>
              <button class="btn btn--sm btn--danger" @click="remove(u.id)">Eliminar</button>
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
          <h3>{{ isEditing ? 'Editar usuario' : 'Nuevo usuario' }}</h3>
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
              <label>Email *</label>
              <input v-model="form.email" type="email" required />
            </div>
            <div class="field">
              <label>Contraseña {{ isEditing ? '(dejar vacío para no cambiar)' : '*' }}</label>
              <input v-model="form.password" type="password" :required="!isEditing" autocomplete="new-password" />
            </div>
            <div class="field">
              <label>Rol *</label>
              <select v-model="form.role">
                <option value="admin">admin</option>
                <option value="user">user</option>
              </select>
            </div>
            <div class="field">
              <label>Empresa</label>
              <select v-model="form.company_id">
                <option value="">— Sin asignar —</option>
                <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
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
