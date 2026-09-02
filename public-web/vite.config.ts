import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  // Fixed port so the merchant app's Terms/Privacy links (which point at
  // :5174) always resolve to this marketing site in local dev.
  server: { port: 5174, strictPort: true },
})
