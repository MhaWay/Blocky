import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

const bindHost = process.env.BLOCKY_BIND_HOST ?? '0.0.0.0';
const publicHost = process.env.BLOCKY_DEV_HOST ?? '192.168.191.242';
const devPort = 5174;

export default defineConfig({
  plugins: [tailwindcss(), preact()],

  resolve: {
    alias: {
      react: 'preact/compat',
      'react-dom': 'preact/compat',
    },
  },

  build: {
    outDir: 'dist',
    emptyOutDir: true,
    target: 'es2022',
    manifest: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'src/main.tsx'),
        gutenberg: resolve(__dirname, 'src/gutenberg.ts'),
        preview: resolve(__dirname, 'src/styles/preview.css'),
      },
    },
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
});
