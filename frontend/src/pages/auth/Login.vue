<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'

const auth   = useAuthStore()
const router = useRouter()

const email    = ref('')
const password = ref('')
const loading  = ref(false)
const error    = ref('')

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al iniciar sesión.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form class="login-form" @submit.prevent="submit" novalidate>
    <h2>Iniciar sesión</h2>

    <div v-if="error" class="alert alert--error">{{ error }}</div>

    <div class="field">
      <label for="email">Correo electrónico</label>
      <input
        id="email"
        v-model="email"
        type="email"
        autocomplete="email"
        placeholder="usuario@empresa.com"
        required
      />
    </div>

    <div class="field">
      <label for="password">Contraseña</label>
      <input
        id="password"
        v-model="password"
        type="password"
        autocomplete="current-password"
        placeholder="••••••••"
        required
      />
    </div>

    <button type="submit" class="btn btn--primary btn--full" :disabled="loading">
      <span v-if="loading">Ingresando…</span>
      <span v-else>Ingresar</span>
    </button>
  </form>
</template>

<style scoped>
.login-form h2 {
  font-size: 1.2rem;
  font-weight: 600;
  color: #1e3a5f;
  margin: 0 0 1.5rem;
  text-align: center;
}

.field {
  margin-bottom: 1rem;
}

.field label {
  display: block;
  font-size: 0.82rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.3rem;
}

.field input {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.9rem;
  transition: border-color 0.15s;
  box-sizing: border-box;
}

.field input:focus {
  outline: none;
  border-color: #2d6a9f;
  box-shadow: 0 0 0 3px rgba(45, 106, 159, 0.15);
}

.alert {
  padding: 0.6rem 0.75rem;
  border-radius: 6px;
  font-size: 0.85rem;
  margin-bottom: 1rem;
}

.alert--error {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.65rem 1.25rem;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: background 0.15s;
}

.btn--primary {
  background: #1e3a5f;
  color: #ffffff;
}

.btn--primary:hover:not(:disabled) {
  background: #2d6a9f;
}

.btn--primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn--full {
  width: 100%;
  margin-top: 0.5rem;
}
</style>
