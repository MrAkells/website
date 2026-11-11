import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
	plugins: [tailwindcss()],
	// Относительные пути: CSS, скрипт и шрифты лежат рядом в dist/assets и не зависят от адреса сайта.
	base: './',
	build: {
		outDir: 'dist',
		emptyOutDir: true,
		// functions.php находит файлы с хэшем в имени через dist/.vite/manifest.json.
		manifest: true,
		rollupOptions: {
			input: [ 'src/css/app.css', 'src/js/app.js' ],
		},
	},
});
