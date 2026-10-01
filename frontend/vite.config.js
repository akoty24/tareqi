import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  // Laravel backend (php artisan serve). The dev server proxies /api and
  // /storage to it, so the SPA and API share an origin (no CORS setup needed).
  const backend = env.VITE_BACKEND_URL || 'http://127.0.0.1:8000'

  return {
    plugins: [vue(), tailwindcss()],
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: {
      port: 5173,
      proxy: {
        '/api': { target: backend, changeOrigin: true },
        '/storage': { target: backend, changeOrigin: true },
      },
    },
    test: {
      environment: 'happy-dom',
      include: ['src/**/*.test.js'],
    },
  }
})
