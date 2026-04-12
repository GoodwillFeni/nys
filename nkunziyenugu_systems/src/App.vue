<template>
  <div id="app" class="app-layout">

    <!-- Mobile top bar (hamburger + title) -->
    <header v-if="isAuthenticated" class="mobile-topbar">
      <button class="hamburger" @click="sidebarOpen = !sidebarOpen" aria-label="Menu">
        <i :class="sidebarOpen ? 'bi bi-x-lg' : 'bi bi-list'"></i>
      </button>
      <span class="mobile-title">NYS</span>
    </header>

    <!-- Overlay — closes sidebar when tapped on mobile -->
    <div v-if="isAuthenticated && sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

    <!-- Sidebar -->
    <SideNav v-if="isAuthenticated" :open="sidebarOpen" @close="sidebarOpen = false"/>

    <!-- Main content -->
    <main class="main-content" :class="{ 'sidebar-visible': isAuthenticated }">
      <RouterView />
    </main>

  </div>
</template>

<script>
import SideNav from '@/components/SideNav.vue'

export default {
  name: 'App',
  components: { SideNav },

  data() {
    return { sidebarOpen: false }
  },

  computed: {
    isAuthenticated() {
      return !!localStorage.getItem('token')
    }
  },

  watch: {
    // Close sidebar on route change (mobile navigation)
    '$route'() {
      this.sidebarOpen = false
    }
  }
}
</script>

<style>
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  color: #2c3e50;
  background: linear-gradient(135deg, #27253f, #605a6d) !important;
}

.app-layout {
  display: flex;
  min-height: 100vh;
  align-items: flex-start;
  background: linear-gradient(135deg, #27253f, #605a6d) !important;
}

.main-content {
  flex: 1;
  min-width: 0;
  min-height: 100vh;
  background: linear-gradient(135deg, #27253f, #605a6d) !important;
  padding: 10px;
}

/* ── Mobile top bar ─────────────────────────────────── */
.mobile-topbar {
  display: none;
  position: fixed;
  top: 0; left: 0; right: 0;
  height: 52px;
  background: linear-gradient(135deg, #27253f, #3d3650);
  z-index: 1100;
  align-items: center;
  padding: 0 16px;
  gap: 12px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
}

.mobile-title {
  font-size: 18px;
  font-weight: 700;
  color: #fff;
  letter-spacing: 2px;
}

.hamburger {
  background: none;
  border: none;
  color: #fff;
  font-size: 24px;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
}

/* Dim overlay behind open sidebar */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 1050;
}

/* ── Mobile breakpoint ──────────────────────────────── */
@media (max-width: 768px) {
  .mobile-topbar    { display: flex; }
  .sidebar-overlay  { display: block; }
  .main-content     { padding-top: 62px; } /* clear the topbar */
}
</style>
