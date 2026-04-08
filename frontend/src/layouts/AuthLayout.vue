<template>
  <div class="auth-wrapper">
    <div class="auth-card">
      <div class="auth-brand">
        <span class="brand-icon">🚛</span>
        <h1>TMS</h1>
        <p>Transportation Management System</p>
      </div>
      <RouterView />
    </div>
  </div>
</template>

<style scoped>
.auth-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1e3a5f 0%, #2d6a9f 100%);
  padding: 1rem;
}

.auth-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 2.5rem 2rem;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.auth-brand {
  text-align: center;
  margin-bottom: 2rem;
}

.brand-icon {
  font-size: 2.5rem;
  display: block;
  margin-bottom: 0.5rem;
}

.auth-brand h1 {
  font-size: 1.8rem;
  font-weight: 700;
  color: #1e3a5f;
  margin: 0;
  letter-spacing: 2px;
}

.auth-brand p {
  font-size: 0.8rem;
  color: #6b7280;
  margin: 0.25rem 0 0;
}
</style>
