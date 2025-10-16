import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";

// Separate Vite config to build the navbar as an embeddable library
export default defineConfig({
	plugins: [react()],
	resolve: {
		alias: {
			"@": path.resolve(__dirname, "./src"),
		},
	},
	define: {
		"process.env.NODE_ENV": JSON.stringify("production"),
		// Guard any loose `process` checks in dependencies
		"process.env": "{}",
		// Some libs expect `global` in browser
		global: "window",
	},
	build: {
		outDir: "dist-navbar",
		emptyOutDir: true,
		cssCodeSplit: true,
		lib: {
			entry: path.resolve(__dirname, "src/embed/navbar.tsx"),
			name: "IONNavbar",
			fileName: "ion-navbar",
			formats: ["iife", "umd"],
		},
		rollupOptions: {
			// Bundle all dependencies to make it drop-in on any site (WordPress/PHP)
			external: [],
			output: {
				// Emit a stable CSS filename for linking from PHP
				assetFileNames: (assetInfo) => {
					if (assetInfo.name && /\.css$/i.test(assetInfo.name)) return `ion-navbar.css`;
					return "assets/[name]-[hash][extname]";
				},
			},
		},
	},
});


