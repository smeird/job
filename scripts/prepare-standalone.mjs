import { cpSync, existsSync, mkdirSync } from "node:fs";
import { resolve } from "node:path";

const repositoryRoot = resolve(import.meta.dirname, "..");
const webRoot = resolve(repositoryRoot, "apps/web");
const standaloneWebRoot = resolve(webRoot, ".next/standalone/apps/web");

/** Copy one optional asset directory into the self-contained Next.js service bundle. */
function copyAssetDirectory(source, destination) {
  if (!existsSync(source)) {
    return;
  }
  mkdirSync(destination, { recursive: true });
  cpSync(source, destination, { force: true, recursive: true });
}

copyAssetDirectory(resolve(webRoot, ".next/static"), resolve(standaloneWebRoot, ".next/static"));
copyAssetDirectory(resolve(webRoot, "public"), resolve(standaloneWebRoot, "public"));
