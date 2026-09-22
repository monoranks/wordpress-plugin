import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

/**
 * The admin app is built into build/ (main.js, main.css) next to the Posts column's stylesheet (column.css, from
 * resources/assets/) and enqueued by src/Assets.php. build/ is committed so a checkout without Node still works.
 *
 * `@wordpress/i18n` and `@wordpress/api-fetch` are never bundled: WordPress ships both, and wp_set_script_translations()
 * loads the catalogue into WordPress's own wp.i18n, so a bundled copy would read an empty catalogue. The shim below turns
 * each import into a read of the global WordPress provides (the enqueued script declares both as dependencies).
 */
const WORDPRESS_MODULES = {
  '@wordpress/i18n': { global: 'wp.i18n', named: ['__', '_n', '_x', 'sprintf', 'isRTL'], hasDefault: false },
  '@wordpress/api-fetch': { global: 'wp.apiFetch', named: [], hasDefault: true },
};

function wordpressGlobals() {
  const PREFIX = '\0monoranks:wordpress/';
  return {
    name: 'monoranks:wordpress-globals',
    enforce: 'pre',
    resolveId(id) {
      return Object.hasOwn(WORDPRESS_MODULES, id) ? PREFIX + id : null;
    },
    load(id) {
      if (!id.startsWith(PREFIX)) return null;
      const { global, named, hasDefault } = WORDPRESS_MODULES[id.slice(PREFIX.length)];
      const lines = [`const provided = window.${global};`];
      if (named.length) lines.push(`const { ${named.join(', ')} } = provided;`, `export { ${named.join(', ')} };`);
      if (hasDefault) lines.push('export default provided;');
      return lines.join('\n');
    },
  };
}

export default defineConfig({
  plugins: [react(), tailwindcss(), wordpressGlobals()],
  resolve: { alias: { '@': resolve(import.meta.dirname, 'resources/admin/src') } },
  define: { 'process.env.NODE_ENV': JSON.stringify('production') },
  root: 'resources/admin',
  publicDir: false,
  base: './',
  build: {
    outDir: resolve(import.meta.dirname, 'build'),
    emptyOutDir: true,
    cssCodeSplit: true,
    modulePreload: false,
    rollupOptions: {
      input: {
        main: resolve(import.meta.dirname, 'resources/admin/src/main.tsx'),
        column: resolve(import.meta.dirname, 'resources/assets/column.css'),
      },
      output: {
        format: 'es',
        // One entry, no code splitting, committed to the repository so the plugin works wherever it is checked out
        // (the app's Docker tests mount this folder, the in-app zip and the WordPress.org deploy copy it). Cache busting
        // is the ?ver query WordPress adds from the file's mtime (src/Assets.php).
        entryFileNames: '[name].js',
        chunkFileNames: 'chunk-[hash].js',
        assetFileNames: (asset) => (/\.css$/.test(asset.names[0] ?? '') ? '[name].css' : 'static/[name]-[hash][extname]'),
      },
      onwarn(warning, warn) {
        if (warning.code === 'MISSING_EXPORT') throw new Error(warning.message);
        warn(warning);
      },
    },
  },
});
