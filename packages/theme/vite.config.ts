import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

const bindHost = process.env.BLOCKY_BIND_HOST ?? '0.0.0.0';
const publicHost = process.env.BLOCKY_DEV_HOST ?? '192.168.191.242';
const devPort = 5173;

export default defineConfig({
  plugins: [tailwindcss()],

  root: '.',

  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        theme: resolve(__dirname, 'assets/js/theme.ts'),
        editor: resolve(__dirname, 'assets/css/editor.css'),
      },
      output: {
        entryFileNames: '[name]-[hash].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
      },
    },
    cssCodeSplit: true,
    sourcemap: process.env.NODE_ENV === 'development',
    minify: 'esbuild',
    target: 'es2022',
  },

  server: {
    host: bindHost,
    port: devPort,
    strictPort: true,
    cors: true,
    origin: `http://${publicHost}:${devPort}`,
    hmr: {
      host: publicHost,
      port: devPort,
      clientPort: devPort,
    },
  },

  resolve: {
    alias: {
      '@tokens': resolve(__dirname, '../tokens/build'),
      '@ui': resolve(__dirname, '../ui-primitives/src'),
    },
  },

  // Optimize deps for faster cold starts
  optimizeDeps: {
    include: [],
  },
});
