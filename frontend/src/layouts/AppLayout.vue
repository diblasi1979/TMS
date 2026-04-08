<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'

const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()

const navItems = computed(() => {
  const items = [
    { name: 'dashboard',  label: 'Dashboard',  icon: '📊', to: { name: 'dashboard' } },
  ]
  if (auth.isAdmin) {
    items.push(
      { name: 'companies', label: 'Empresas',  icon: '🏢', to: { name: 'companies' } },
      { name: 'users',     label: 'Usuarios',  icon: '👥', to: { name: 'users' } },
      { name: 'clients',   label: 'Clientes',  icon: '🤝', to: { name: 'clients' } },
    )
  }
  return items
})

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

function isActive(name) {
  return route.name === name
}
</script>

<template>
  <div class="app-shell">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-brand">
        <span>🚛</span>
        <strong>TMS</strong>
      </div>

      <nav class="sidebar-nav">
        <RouterLink
          v-for="item in navItems"
          :key="item.name"
          :to="item.to"
          class="nav-item"
          :class="{ active: isActive(item.name) }"
        >
          <span class="nav-icon">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>

      <div class="sidebar-footer">
        <div class="user-info">
          <div class="user-avatar">{{ auth.user?.name?.charAt(0).toUpperCase() }}</div>
          <div>
            <div class="user-name">{{ auth.user?.name }}</div>
            <div class="user-role">{{ auth.user?.role }}</div>
          </div>
        </div>
        <button class="logout-btn" @click="handleLogout" title="Cerrar sesión">⏻</button>
      </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.app-shell {
  display: flex;
  min-height: 100vh;
  background: #f3f4f6;
}

/* ─── Sidebar ─── */
.sidebar {
  width: 240px;
  background: #1e3a5f;
  color: #e2e8f0;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  position: sticky;
  top: 0;
  height: 100vh;
  overflow-y: auto;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 1.25rem 1.5rem;
  font-size: 1.2rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-brand strong {
  font-size: 1.1rem;
  letter-spacing: 2px;
}

.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.5rem;
  color: #9ab0cc;
  text-decoration: none;
  font-size: 0.9rem;
  transition: background 0.15s, color 0.15s;
  border-left: 3px solid transparent;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.06);
  color: #ffffff;
}

.nav-item.active {
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  border-left-color: #60a5fa;
}

.nav-icon {
  font-size: 1rem;
  width: 1.2rem;
  text-align: center;
}

/* ─── Footer ─── */
.sidebar-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  overflow: hidden;
}

.user-avatar {
  width: 32px;
  height: 32px;
  background: #2d6a9f;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.user-name {
  font-size: 0.82rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 0.7rem;
  color: #9ab0cc;
  text-transform: capitalize;
}

.logout-btn {
  background: transparent;
  border: none;
  color: #9ab0cc;
  font-size: 1.1rem;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 4px;
  transition: color 0.15s;
}

.logout-btn:hover {
  color: #f87171;
}

/* ─── Main ─── */
.main-content {
  flex: 1;
  overflow-x: hidden;
  padding: 2rem;
}
</style>
