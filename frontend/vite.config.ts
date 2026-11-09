import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import { fileURLToPath } from "node:url";

export default defineConfig({
  server: { host: "::", port: 8080 },
  build: { outDir: "dist" },
  plugins: [react()],
  resolve: {
    alias: { "@": fileURLToPath(new URL("./client", import.meta.url)) },
  },
});