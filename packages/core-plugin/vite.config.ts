import { defineConfig } from 'vite';
import { resolve } from 'path';

const bindHost = process.env.BLOCKY_BIND_HOST ?? '0.0.0.0';
const publicHost = process.env.BLOCKY_DEV_HOST ?? '192.168.191.242';
const devPort = 5175;

export default defineConfig({
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    target: 'es2022',
    manifest: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'src/index.ts'),
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