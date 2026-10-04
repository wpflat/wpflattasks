import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// BizyTasks is a separate bundle that runs inside BizyCore's page: Vue is not bundled again, it is BizyCore's window.Vue.
export default defineConfig( {
	root: 'ui',
	plugins: [ vue() ],
	define: { 'process.env.NODE_ENV': '"production"' },
	build: {
		outDir: '../build',
		emptyOutDir: true,
		cssCodeSplit: false,
		lib: { entry: 'main.js', name: 'BizyTasks', formats: [ 'iife' ], fileName: () => 'bizytasks.js', cssFileName: 'bizytasks' },
		rollupOptions: { external: [ 'vue' ], output: { globals: { vue: 'Vue' } } },
	},
} );
