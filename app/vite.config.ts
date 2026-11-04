/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

const pwa = VitePWA({
  strategies: 'injectManifest',
  srcDir: 'src/sw',
  filename: 'sw.ts',
  registerType: 'autoUpdate',
  injectRegister: 'auto',
  includeAssets: ['favicon.svg', 'icons/apple-touch-icon.png'],
  manifest: {
    name: 'GoPilot',
    short_name: 'GoPilot',
    description: 'Selbstgesteuert lernen mit Moodle: Lernpfade, Wochenplan, Reflexion und Lernbiografie.',
    lang: 'de',
    // Relative to the manifest, so the app also works below a path (GitHub Pages).
    start_url: '.',
    scope: '.',
    display: 'standalone',
    orientation: 'portrait',
    theme_color: '#1c7ed6',
    background_color: '#ffffff',
    icons: [
      { src: 'icons/icon-192.png', sizes: '192x192', type: 'image/png' },
      { src: 'icons/icon-512.png', sizes: '512x512', type: 'image/png' },
      { src: 'icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
      { src: 'favicon.svg', sizes: 'any', type: 'image/svg+xml' },
    ],
  },
  injectManifest: {
    globPatterns: ['**/*.{js,css,html,svg,png,ico,woff2}'],
    // The lazily loaded PDF library (~1.2 MB) is not precached to spare mobile
    // data; the PDF export therefore needs a connection.
    globIgnores: ['**/biographyPdf-*.js'],
  },
  devOptions: { enabled: false },
})

export default defineConfig(({ mode }) => ({
  plugins: [react(), ...(mode === 'test' ? [] : [pwa])],
  build: {
    // The lazily loaded PDF chunk (@react-pdf/renderer) is ~1.2 MB by design.
    chunkSizeWarningLimit: 1300,
    rollupOptions: {
      output: {
        manualChunks: {
          react: ['react', 'react-dom', 'react-router', '@tanstack/react-query'],
          mantine: ['@mantine/core', '@mantine/hooks', '@mantine/notifications', '@mantine/dates', 'dayjs'],
        },
      },
    },
  },
  test: {
    environment: 'jsdom',
    // Form tests with userEvent take several seconds on slow machines and CI runners.
    testTimeout: 20000,
    setupFiles: ['./src/test/setup.ts'],
    env: { VITE_MOCK: '1' },
    css: false,
  },
}))
