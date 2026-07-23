import { defineConfig } from "tsup";

export default defineConfig({
  entry: ["src/index.ts"],
  external: ["docx", "kysely", "mysql2", "openai", "pdfkit", "remark-parse", "unified", "zod"],
  format: ["esm"],
  noExternal: [/^@job\/(?:core|db|documents)(?:\/|$)/],
  outDir: "dist",
  platform: "node",
  sourcemap: true,
  target: "node24",
  treeshake: true,
});
