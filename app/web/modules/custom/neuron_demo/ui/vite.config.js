import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// Builds the chat into dist/chat.js and dist/chat.css, loaded by the
// neuron_demo/chat library. Chunks are fetched relative to chat.js.
export default defineConfig({
  plugins: [vue()],
  base: './',
  define: { 'process.env.NODE_ENV': '"production"' },
  build: {
    rollupOptions: {
      input: { chat: 'src/main.js' },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'assets/[name][extname]',
      },
    },
  },
})
