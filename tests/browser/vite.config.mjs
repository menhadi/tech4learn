import { defineConfig } from "vite";
import { fileURLToPath } from "node:url";

export default defineConfig({
  root: fileURLToPath(new URL("../../", import.meta.url)),
  define: { "import.meta.env.VITE_API_URL": JSON.stringify("/api/v1") },
  esbuild: { jsx: "automatic" },
  server: { host: "127.0.0.1", port: 5195, strictPort: true },
});
