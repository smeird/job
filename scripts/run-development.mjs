import { spawn } from "node:child_process";
import { resolve } from "node:path";

const repositoryRoot = resolve(import.meta.dirname, "..");

try {
  process.loadEnvFile(resolve(repositoryRoot, ".env"));
} catch {
  // A local env file is optional; validated defaults and shell variables still apply.
}

const targets = {
  web: [resolve(repositoryRoot, "node_modules/next/dist/bin/next"), "dev", "apps/web", "--webpack", "--hostname", "127.0.0.1", "--port", "3000"],
  worker: [resolve(repositoryRoot, "node_modules/tsx/dist/cli.mjs"), "watch", "apps/worker/src/index.ts"],
};
const target = process.argv[2];
const argumentsForTarget = targets[target];
if (argumentsForTarget === undefined) {
  throw new Error("Development target must be web or worker.");
}

const child = spawn(process.execPath, argumentsForTarget, {
  cwd: repositoryRoot,
  env: process.env,
  stdio: "inherit",
});

/** Forward termination requests so development processes stop cleanly. */
function forwardSignal(signal) {
  child.kill(signal);
}

process.once("SIGINT", () => forwardSignal("SIGINT"));
process.once("SIGTERM", () => forwardSignal("SIGTERM"));

await new Promise((resolveExit, reject) => {
  child.once("error", reject);
  child.once("exit", (code, signal) => {
    process.exitCode = code ?? (signal === null ? 1 : 0);
    resolveExit();
  });
});
