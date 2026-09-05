import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost/UAP-MC/website',
        changeOrigin: true,
        secure: false,
      },
      '/auth': {
        target: 'http://localhost/UAP-MC',
        changeOrigin: true,
        secure: false,
      },
      '/admin': {
        target: 'http://localhost/UAP-MC',
        changeOrigin: true,
        secure: false,
      },
      '/member': {
        target: 'http://localhost/UAP-MC',
        changeOrigin: true,
        secure: false,
      },
      '/includes': {
        target: 'http://localhost/UAP-MC',
        changeOrigin: true,
        secure: false,
      },
      '/uploads': {
        target: 'http://localhost/UAP-MC',
        changeOrigin: true,
        secure: false,
      },
      '/public': {
        target: 'http://localhost/UAP-MC',
        changeOrigin: true,
        secure: false,
      },
      // Backward compatibility for legacy /UAP-MINDORO and /UAP-MC absolute links
      '/UAP-MINDORO': {
        target: 'http://localhost',
        changeOrigin: true,
        secure: false,
      },
      '/UAP-MC': {
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

