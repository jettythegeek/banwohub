import '@/styles/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router, { prefetchAll } from './router'
import { useAuthStore } from './stores/auth'
import { usePortalAuthStore } from './stores/portalAuth'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

const auth = useAuthStore()
const portalAuth = usePortalAuthStore()
void auth.ensureLoaded()
void portalAuth.ensureLoaded()

app.mount('#app')

// Warm likely-needed route chunks once the browser is idle so subsequent
// navigation feels instant instead of waiting on a code-split download.
type IdleScheduler = (cb: () => void) => void
const win = window as unknown as {
  requestIdleCallback?: (cb: () => void) => number
}
const idle: IdleScheduler = win.requestIdleCallback
  ? (cb) => win.requestIdleCallback!(cb)
  : (cb) => window.setTimeout(cb, 1200)
idle(() => prefetchAll())
