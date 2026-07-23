import { resolve } from "node:path";
import { loadAppConfig } from "@job/core/config";
import { loadDatabaseConfig } from "@job/db/config";
import { createDatabase } from "@job/db/database";
import { JobsRepository } from "@job/db/repositories/jobs";
import { TailorJobHandler } from "./handler.js";
import { isTransientJobError, retryDelaySeconds } from "./retry.js";

let stopping = false;

/** Wait between empty queue polls without blocking shutdown signals. */
function delay(milliseconds: number): Promise<void> {
  return new Promise((resolveDelay) => setTimeout(resolveDelay, milliseconds));
}

/** Load the conventional repository .env file when the platform has not already supplied variables. */
function loadLocalEnvironment(): void {
  try {
    process.loadEnvFile(resolve(import.meta.dirname, "../../..", ".env"));
  } catch {
    // Production services receive configuration directly from systemd and Apache-compatible env files.
  }
}

/** Run the queue loop until a termination signal arrives. */
async function main(): Promise<void> {
  loadLocalEnvironment();
  const config = loadAppConfig();
  const database = createDatabase(loadDatabaseConfig());
  const jobs = new JobsRepository(database);
  const handler = new TailorJobHandler(database, config);
  const once = process.env.WORKER_ONCE === "true";

  try {
    while (!stopping) {
      const job = await jobs.reserveNext();
      if (job === null) {
        if (once) {
          return;
        }
        await delay(1_000);
        continue;
      }

      try {
        if (job.type !== "tailor_cv") {
          throw new Error(`Unsupported job type: ${job.type}`);
        }
        await handler.handle(job.payload);
        await jobs.markCompleted(job.id);
      } catch (error) {
        const message = error instanceof Error ? error.message : "Unknown worker error.";
        const retrying = job.attempts < 3 && isTransientJobError(error);
        await handler.recordFailure(job.payload, message, retrying);
        if (retrying) {
          await jobs.scheduleRetry(job.id, message, new Date(Date.now() + retryDelaySeconds(job.attempts) * 1_000));
        } else {
          await jobs.markFailed(job.id, message);
        }
      }

      if (once) {
        return;
      }
    }
  } finally {
    await database.destroy();
  }
}

/** Request a graceful stop after the current database or provider operation completes. */
function stop(): void {
  stopping = true;
}

process.once("SIGINT", stop);
process.once("SIGTERM", stop);
await main();
