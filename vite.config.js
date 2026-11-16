import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    outDir: 'client/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        gallery: 'client/src/gallery.js',
        photoswipe: 'client/src/photoswipe.css',
      },
      output: {
        entryFileNames: (chunk) => chunk.name === 'gallery' ? 'gallery.js' : '[name].js',
        chunkFileNames: '[name]-[hash].mjs',
        assetFileNames: '[name][extname]',
      },
    },
  },
});
