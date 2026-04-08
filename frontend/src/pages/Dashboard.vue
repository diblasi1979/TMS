<script setup>
import { useAuthStore } from '@/stores/auth.js'

const auth = useAuthStore()
</script>

<template>
  <div>
    <div class="page-header">
      <h1>Dashboard</h1>
      <p>Bienvenido, <strong>{{ auth.user?.name }}</strong></p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">🏢</div>
        <div class="stat-label">Módulo</div>
        <div class="stat-value">Distribución</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🚚</div>
        <div class="stat-label">Módulo</div>
        <div class="stat-value">Transporte</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-label">Módulo</div>
        <div class="stat-value">Planificación</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📍</div>
        <div class="stat-label">Módulo</div>
        <div class="stat-value">Seguimiento</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-header {
  margin-bottom: 2rem;
}

.page-header h1 {
  font-size: 1.6rem;
  font-weight: 700;
  color: #1e3a5f;
  margin: 0 0 0.25rem;
}

.page-header p {
  color: #6b7280;
  margin: 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1.25rem;
}

.stat-card {
  background: #ffffff;
  border-radius: 10px;
  padding: 1.5rem;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.stat-icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.stat-label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #9ca3af;
  margin-bottom: 0.25rem;
}

.stat-value {
  font-size: 1rem;
  font-weight: 700;
  color: #1e3a5f;
}
</style>
