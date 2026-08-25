import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost/UAP-MINDORO/uap_mc/website',
        changeOrigin: true,
        secure: false,
      },
      '/auth': {
        target: 'http://localhost/UAP-MINDORO/uap_mc',
        changeOrigin: true,
        secure: false,
      },
      '/admin': {
        target: 'http://localhost/UAP-MINDORO/uap_mc',
        changeOrigin: true,
        secure: false,
      },
      '/member': {
        target: 'http://localhost/UAP-MINDORO/uap_mc',
        changeOrigin: true,
        secure: false,
      },
      '/includes': {
        target: 'http://localhost/UAP-MINDORO/uap_mc',
        changeOrigin: true,
        secure: false,
      },
      '/uploads': {
        target: 'http://localhost/UAP-MINDORO/uap_mc',
        changeOrigin: true,
        secure: false,
      },
      '/public': {
        target: 'http://localhost/UAP-MINDORO/uap_mc',
        changeOrigin: true,
        secure: false,
      },
      // Catch-all: PHP pages generate absolute URLs like /UAP-MINDORO/uap_mc/includes/theme.css
      // Forward them to Apache so stylesheets, fonts, and images resolve correctly in dev mode
      '/UAP-MINDORO': {
        target: 'http://localhost',
        changeOrigin: true,
        secure: false,
      },
    },
  },
  build: {
    outDir: 'dist',
  },
});

