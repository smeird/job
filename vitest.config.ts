import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    coverage: {
      provider: "v8",
      reporter: ["text", "html", "lcov"],
    },
    environment: "node",
    exclude: ["**/node_modules/**", "**/dist/**", "**/.next/**", "**/*.integration.test.ts"],
    include: ["packages/**/*.test.ts", "apps/**/*.test.ts"],
  },
});
