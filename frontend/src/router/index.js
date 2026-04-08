import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth.js'

const routes = [
  {
    path: '/login',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      { path: '', name: 'login', component: () => import('@/pages/auth/Login.vue') },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('@/pages/Dashboard.vue') },
      {
        path: 'admin',
        meta: { requiresAdmin: true },
        children: [
          { path: 'companies', name: 'companies', component: () => import('@/pages/admin/Companies.vue') },
          { path: 'users',     name: 'users',     component: () => import('@/pages/admin/Users.vue') },
          { path: 'clients',   name: 'clients',   component: () => import('@/pages/admin/Clients.vue') },
        ],
      },
      {
        path: 'transport',
        meta: { requiresAdmin: true },
        children: [
          { path: 'vehicles',    name: 'vehicles',    component: () => import('@/pages/transport/Vehicles.vue') },
          { path: 'operators',   name: 'operators',   component: () => import('@/pages/transport/Operators.vue') },
          { path: 'assignments', name: 'assignments', component: () => import('@/pages/transport/Assignments.vue') },
        ],
      },
      {
        path: 'distribution',
        meta: { requiresAdmin: true },
        children: [
          { path: 'orders', name: 'orders', component: () => import('@/pages/distribution/Orders.vue') },
          { path: 'routes', name: 'routes', component: () => import('@/pages/distribution/Routes.vue') },
        ],
      },
      {
        path: 'planning',
        meta: { requiresAdmin: true },
        children: [
          { path: 'trips',       name: 'trips',       component: () => import('@/pages/planning/Trips.vue') },
          { path: 'maintenance', name: 'maintenance', component: () => import('@/pages/planning/Maintenance.vue') },
          { path: 'shifts',      name: 'shifts',      component: () => import('@/pages/planning/Shifts.vue') },
        ],
      },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'dashboard' }
  }
  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
