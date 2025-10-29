import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "./src"),
        },
    },
    define: {
        "process.env.NODE_ENV": JSON.stringify("production"),
        "process.env": {},
    },
    build: {
        lib: {
            entry: path.resolve(__dirname, "src/embed/index.tsx"),
            name: "IonNavbar",
            fileName: () => "ion-navbar.js",
            formats: ["iife"],
        },
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith(".css")) {
                        return "ion-navbar.css";
                    }
                    return "assets/[name][extname]";
                },
            },
        },
        outDir: "dist-navbar",
        emptyOutDir: true,
    },
});


