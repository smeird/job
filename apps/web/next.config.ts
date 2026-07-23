import { resolve } from "node:path";
import type { NextConfig } from "next";

const workspaceRoot = resolve(import.meta.dirname, "../..");

interface WebpackConfiguration {
  resolve?: {
    extensionAlias?: Record<string, string[]>;
  };
}

/** Let webpack resolve source-level ESM .js specifiers to their TypeScript implementations. */
function configureWebpack(configuration: WebpackConfiguration): WebpackConfiguration {
  configuration.resolve ??= {};
  configuration.resolve.extensionAlias = {
    ...configuration.resolve.extensionAlias,
    ".js": [".ts", ".tsx", ".js"],
  };
  return configuration;
}

const nextConfig: NextConfig = {
  output: "standalone",
  outputFileTracingRoot: workspaceRoot,
  poweredByHeader: false,
  reactStrictMode: true,
  serverExternalPackages: ["argon2", "pdfkit"],
  transpilePackages: ["@job/core", "@job/db", "@job/documents"],
  turbopack: { root: workspaceRoot },
  webpack: configureWebpack,
};

export default nextConfig;
